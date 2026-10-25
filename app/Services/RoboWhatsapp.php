<?php

namespace App\Services;

use App\Enums\AcaoConfiguracao;
use App\Enums\SituacaoRoboWhatsapp;
use App\Exceptions\RoboIndisponivelException;
use App\Models\Configuracoes;
use App\Models\Usuarios;

/**
 * Robô do WhatsApp da banca pela Evolution API (spec 021, US5, research R-10 a R-12): situação,
 * conectar por QR code, desconectar e o aviso automático de mudança de conexão. A situação fica em
 * configuracoes (robo_whatsapp_*) para a spec futura de mensagens; o envio de mensagens não é daqui.
 */
class RoboWhatsapp
{
    public function __construct(private ClienteEvolution $evolution, private HistoricoConfiguracoes $historico) {}

    /**
     * Consulta a Evolution e corrige a situação gravada (reforço do aviso automático). Sem resposta,
     * devolve a gravada com disponivel = false.
     *
     * @return array{situacao: SituacaoRoboWhatsapp, numero: ?string, perfil: ?string, atualizado_em: mixed, disponivel: bool, mensagem: ?string}
     */
    public function situacao(): array
    {
        try {
            $this->aplicar_estado($this->evolution->estado());

            return $this->gravada(true);
        } catch (RoboIndisponivelException $erro) {
            return $this->gravada(false, $erro->getMessage());
        }
    }

    /**
     * Cria (ou reaproveita) a instância com o webhook e devolve o QR code; já conectado, sem QR.
     *
     * @return array{situacao: SituacaoRoboWhatsapp, qrcode: ?string}
     */
    public function conectar(Usuarios $autor): array
    {
        $url_webhook = route('webhooks.evolution');

        if ($this->evolution->existe()) {
            $this->evolution->configurar_webhook($url_webhook);
        } else {
            $this->evolution->criar($url_webhook);
        }

        if ($this->evolution->estado() === 'open') {
            $this->aplicar_estado('open');

            return ['situacao' => SituacaoRoboWhatsapp::Conectado, 'qrcode' => null];
        }

        $anterior = Configuracoes::atual()->robo_whatsapp_situacao;
        $qrcode = $this->evolution->qrcode();
        $this->gravar(SituacaoRoboWhatsapp::AguardandoLeitura);

        // uma linha por tentativa, não a cada QR renovado
        if ($anterior === SituacaoRoboWhatsapp::Desconectado) {
            $this->historico->registrar($autor, 'robo_whatsapp', AcaoConfiguracao::Conexão);
        }

        return ['situacao' => SituacaoRoboWhatsapp::AguardandoLeitura, 'qrcode' => $qrcode];
    }

    public function desconectar(Usuarios $autor): void
    {
        $this->evolution->sair();
        $this->gravar(SituacaoRoboWhatsapp::Desconectado);
        $this->historico->registrar($autor, 'robo_whatsapp', AcaoConfiguracao::Desconexão);
    }

    /**
     * Aviso automático da Evolution (só connection.update); os demais eventos são ignorados.
     *
     * @param  array<string, mixed>  $aviso
     */
    public function tratar_aviso(array $aviso): void
    {
        $evento = strtolower(str_replace('_', '.', (string) ($aviso['event'] ?? '')));

        if ($evento !== 'connection.update') {
            return;
        }

        $this->aplicar_estado((string) ($aviso['data']['state'] ?? 'close'), [
            'numero' => $this->evolution->numero($aviso['data']['wuid'] ?? null),
            'perfil' => $aviso['data']['profileName'] ?? null,
        ]);
    }

    /**
     * Converte o estado da Evolution e grava só se mudou; ao conectar, busca número e perfil quando o
     * aviso não os trouxe.
     *
     * @param  array{numero?: ?string, perfil?: ?string}  $perfil
     */
    private function aplicar_estado(string $estado, array $perfil = []): void
    {
        $situacao = SituacaoRoboWhatsapp::do_estado($estado);
        $configuracoes = Configuracoes::atual();

        if ($situacao === SituacaoRoboWhatsapp::Conectado) {
            // sem o perfil agora, grava a conexão assim mesmo; a próxima consulta completa
            if (empty($perfil['numero'])) {
                try {
                    $perfil = $this->evolution->perfil();
                } catch (RoboIndisponivelException) {
                    $perfil = ['numero' => null, 'perfil' => null];
                }
            }

            if ($configuracoes->robo_whatsapp_situacao !== $situacao || $configuracoes->robo_whatsapp_numero !== $perfil['numero']) {
                $this->gravar($situacao, $perfil['numero'] ?? null, $perfil['perfil'] ?? null);
            }

            return;
        }

        if ($configuracoes->robo_whatsapp_situacao !== $situacao) {
            $this->gravar($situacao);
        }
    }

    private function gravar(SituacaoRoboWhatsapp $situacao, ?string $numero = null, ?string $perfil = null): void
    {
        Configuracoes::atual()->forceFill([
            'robo_whatsapp_situacao' => $situacao,
            'robo_whatsapp_numero' => $numero,
            'robo_whatsapp_perfil' => $perfil !== null ? mb_substr($perfil, 0, 100) : null,
            'robo_whatsapp_atualizado_em' => now(),
        ])->save();
    }

    /**
     * @return array{situacao: SituacaoRoboWhatsapp, numero: ?string, perfil: ?string, atualizado_em: mixed, disponivel: bool, mensagem: ?string}
     */
    private function gravada(bool $disponivel, ?string $mensagem = null): array
    {
        $configuracoes = Configuracoes::atual();

        return [
            'situacao' => $configuracoes->robo_whatsapp_situacao,
            'numero' => $configuracoes->robo_whatsapp_numero,
            'perfil' => $configuracoes->robo_whatsapp_perfil,
            'atualizado_em' => $configuracoes->robo_whatsapp_atualizado_em?->copy()->setTimezone('-03:00')->toIso8601String(),
            'disponivel' => $disponivel,
            'mensagem' => $mensagem,
        ];
    }
}
