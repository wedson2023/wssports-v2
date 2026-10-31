<?php

namespace App\Services;

use App\Enums\CanalMensagem;
use App\Enums\SituacaoEnvioMensagem;
use App\Enums\SituacaoRoboWhatsapp;
use App\Enums\TipoMensagem;
use App\Exceptions\DestinatarioSemWhatsappException;
use App\Exceptions\RoboIndisponivelException;
use App\Jobs\EnviarMensagem;
use App\Models\Configuracoes;
use App\Models\MensagensEnvios;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Caminho único das mensagens do sistema (spec 025, research R-04 e R-05): lê o canal configurado,
 * registra um envio por canal, despacha na fila e aplica o canal de reserva. Nada aqui grava o
 * conteúdo da mensagem. A mensagem vem de TextosMensagens.
 */
class DisparoMensagens
{
    public function __construct(private ClienteEvolution $evolution) {}

    /**
     * Despacha em segundo plano; o job só entra na fila depois do commit.
     *
     * @param  array<string, mixed>  $mensagem
     */
    public function enviar(array $mensagem): void
    {
        foreach ($this->planejar($mensagem) as [$canal, $usar_reserva]) {
            $this->despachar($mensagem, $canal, $usar_reserva, false);
        }
    }

    /**
     * Envia na hora, para quem precisa do resultado (combinação de risco e teste). Verdadeiro quando
     * ao menos um canal entregou.
     *
     * @param  array<string, mixed>  $mensagem
     */
    public function enviar_agora(array $mensagem): bool
    {
        $entregue = false;

        foreach ($this->planejar($mensagem) as [$canal, $usar_reserva]) {
            $entregue = $this->enviar_canal_agora($mensagem, $canal, $usar_reserva, false) || $entregue;
        }

        return $entregue;
    }

    /**
     * Executa um envio já registrado. Mensagem que deixou de valer, como um código substituído, fica
     * dispensada; as exceções voltam para quem chamou decidir entre repetir e usar a reserva.
     *
     * @param  array<string, mixed>  $mensagem
     */
    public function executar(int $envio_id, array $mensagem): void
    {
        $envio = MensagensEnvios::findOrFail($envio_id);

        if (! $this->ainda_vale($mensagem)) {
            $envio->update(['situacao' => SituacaoEnvioMensagem::Dispensado, 'erro' => 'Código sem validade.']);

            return;
        }

        $envio->increment('tentativas');

        if ($envio->canal === CanalMensagem::WhatsApp) {
            $this->evolution->enviar_texto($envio->destinatario, $mensagem['texto']);
        } else {
            Mail::to($envio->destinatario)->send($mensagem['email_mensagem']);
        }

        $envio->update(['situacao' => SituacaoEnvioMensagem::Enviado, 'enviado_em' => now(), 'erro' => null]);
    }

    /**
     * Marca a falha definitiva e aciona a reserva, sem encadear outra (R-05).
     *
     * @param  array<string, mixed>  $mensagem
     */
    public function registrar_falha(int $envio_id, array $mensagem, ?Throwable $erro, bool $usar_reserva): void
    {
        $envio = MensagensEnvios::findOrFail($envio_id);

        $envio->update(['situacao' => SituacaoEnvioMensagem::Falhou, 'erro' => $this->resumo_erro($erro)]);

        Log::warning('Falha no envio de mensagem.', ['envio_id' => $envio->id, 'tipo' => $envio->tipo->value, 'canal' => $envio->canal->value]);

        if ($usar_reserva && $this->ainda_vale($mensagem)) {
            $this->despachar($mensagem, $envio->canal->outro(), false, true);
        }
    }

    /**
     * Canais do envio. Só com um canal há reserva: com "Os dois", cada um já sai por conta própria.
     *
     * @param  array<string, mixed>  $mensagem
     * @return list<array{0: CanalMensagem, 1: bool}>
     */
    private function planejar(array $mensagem): array
    {
        $tipo = $mensagem['tipo'];
        $canais = ($mensagem['canal'] ?? Configuracoes::atual()->canal_mensagem($tipo))->canais_de_envio();
        $usar_reserva = $tipo->tem_reserva() && count($canais) === 1;

        return array_map(fn (CanalMensagem $canal) => [$canal, $usar_reserva], $canais);
    }

    /**
     * @param  array<string, mixed>  $mensagem
     */
    private function despachar(array $mensagem, CanalMensagem $canal, bool $usar_reserva, bool $e_reserva): void
    {
        $motivo = $this->motivo_para_nao_usar($mensagem, $canal, $usar_reserva);

        if ($motivo !== null) {
            $this->registrar($mensagem, $canal, SituacaoEnvioMensagem::Dispensado, $e_reserva, $motivo);

            if ($usar_reserva) {
                $this->despachar($mensagem, $canal->outro(), false, true);
            }

            return;
        }

        $envio = $this->registrar($mensagem, $canal, SituacaoEnvioMensagem::Pendente, $e_reserva);

        EnviarMensagem::dispatch($envio->id, $mensagem, $usar_reserva)
            ->onQueue($mensagem['tipo']->e_codigo() ? EnviarMensagem::FILA_CODIGOS : EnviarMensagem::FILA_DEMAIS)
            ->afterCommit();
    }

    /**
     * @param  array<string, mixed>  $mensagem
     */
    private function enviar_canal_agora(array $mensagem, CanalMensagem $canal, bool $usar_reserva, bool $e_reserva): bool
    {
        $motivo = $this->motivo_para_nao_usar($mensagem, $canal, $usar_reserva);

        if ($motivo !== null) {
            $this->registrar($mensagem, $canal, SituacaoEnvioMensagem::Dispensado, $e_reserva, $motivo);

            return $usar_reserva && $this->enviar_canal_agora($mensagem, $canal->outro(), false, true);
        }

        $envio = $this->registrar($mensagem, $canal, SituacaoEnvioMensagem::Pendente, $e_reserva);

        try {
            $this->executar($envio->id, $mensagem);

            return true;
        } catch (Throwable $erro) {
            $envio->update(['situacao' => SituacaoEnvioMensagem::Falhou, 'erro' => $this->resumo_erro($erro)]);

            return $usar_reserva && $this->enviar_canal_agora($mensagem, $canal->outro(), false, true);
        }
    }

    /**
     * Falta do dado do canal sempre dispensa. Com o robô fora, só dispensa quem tem reserva, que sai
     * na hora pelo outro canal; os demais seguem as tentativas (R-05).
     *
     * @param  array<string, mixed>  $mensagem
     */
    private function motivo_para_nao_usar(array $mensagem, CanalMensagem $canal, bool $usar_reserva): ?string
    {
        if (blank($this->destinatario($mensagem, $canal))) {
            return $canal === CanalMensagem::WhatsApp ? 'Sem telefone.' : 'Sem e-mail.';
        }

        if ($canal === CanalMensagem::WhatsApp && $usar_reserva && Configuracoes::atual()->robo_whatsapp_situacao !== SituacaoRoboWhatsapp::Conectado) {
            return 'Robô indisponível.';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $mensagem
     */
    private function destinatario(array $mensagem, CanalMensagem $canal): ?string
    {
        if ($canal === CanalMensagem::WhatsApp) {
            return blank($mensagem['texto'] ?? null) ? null : ($mensagem['telefone'] ?? null);
        }

        return blank($mensagem['email_mensagem'] ?? null) ? null : ($mensagem['email'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $mensagem
     */
    private function registrar(array $mensagem, CanalMensagem $canal, SituacaoEnvioMensagem $situacao, bool $e_reserva, ?string $erro = null): MensagensEnvios
    {
        return MensagensEnvios::create([
            'tipo' => $mensagem['tipo'],
            'canal' => $canal,
            'destinatario' => (string) $this->destinatario($mensagem, $canal),
            'situacao' => $situacao,
            'reserva' => $e_reserva,
            'erro' => $erro,
            'referencia_tipo' => $mensagem['referencia_tipo'] ?? null,
            'referencia_id' => $mensagem['referencia_id'] ?? null,
        ]);
    }

    /**
     * Código de recuperação ou link de origem: precisa continuar válido na hora do envio.
     *
     * @param  array<string, mixed>  $mensagem
     */
    private function ainda_vale(array $mensagem): bool
    {
        if (! isset($mensagem['validar'])) {
            return true;
        }

        [$classe, $id] = $mensagem['validar'];

        return (bool) $classe::find($id)?->valido();
    }

    /**
     * Texto fixo por tipo de falha: a mensagem original de um serviço externo pode trazer dados.
     */
    private function resumo_erro(?Throwable $erro): string
    {
        return match (true) {
            $erro instanceof DestinatarioSemWhatsappException => mb_substr($erro->getMessage(), 0, 255),
            $erro instanceof RoboIndisponivelException => 'Serviço do WhatsApp indisponível.',
            default => 'Falha no serviço de envio.',
        };
    }
}
