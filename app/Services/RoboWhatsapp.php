<?php

namespace App\Services;

use App\Enums\AcaoConfiguracao;
use App\Enums\SituacaoRoboWhatsapp;
use App\Exceptions\RoboIndisponivelException;
use App\Models\Configuracoes;
use App\Models\Usuarios;
use Illuminate\Support\Facades\Cache;

/**
 * Robô do WhatsApp da banca pela Evolution API (spec 021, US5, research R-10 a R-12): situação,
 * conectar por QR code, desconectar e o aviso automático de mudança de conexão. A situação fica em
 * configuracoes (robo_whatsapp_*); o envio de mensagens fica em DisparoMensagens (spec 025). A queda
 * sem pedido do Admin gera o alerta da spec 025.
 */
class RoboWhatsapp
{
    private const CHAVE_FALHAS = 'robo_whatsapp_falhas_conferencia';

    private const FALHAS_PARA_QUEDA = 2;

    private const MINUTOS_ENTRE_ALERTAS = 30;

    public function __construct(
        private ClienteEvolution $evolution,
        private HistoricoConfiguracoes $historico,
        private DisparoMensagens $disparo,
        private TextosMensagens $textos,
    ) {}

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
        // marca antes de sair: o aviso automático da desconexão pode chegar primeiro e não é queda
        $configuracoes = Configuracoes::atual();
        $configuracoes->forceFill(['robo_whatsapp_desconexao_pedida' => true])->save();

        try {
            $this->evolution->sair();
        } catch (RoboIndisponivelException $erro) {
            $configuracoes->forceFill(['robo_whatsapp_desconexao_pedida' => false])->save();

            throw $erro;
        }

        $this->gravar(SituacaoRoboWhatsapp::Desconectado);
        $this->historico->registrar($autor, 'robo_whatsapp', AcaoConfiguracao::Desconexão);
    }

    /**
     * Conferência periódica (spec 025, research R-08). Sem resposta do servidor em conferências
     * seguidas, trata como queda; com a Evolution não configurada, não há robô a conferir.
     */
    public function conferir(): void
    {
        if (! $this->evolution->configurado()) {
            return;
        }

        try {
            $estado = $this->evolution->estado();
        } catch (RoboIndisponivelException) {
            if (Cache::increment(self::CHAVE_FALHAS) >= self::FALHAS_PARA_QUEDA) {
                $this->aplicar_estado('close');
            }

            return;
        }

        Cache::forget(self::CHAVE_FALHAS);
        $this->aplicar_estado($estado);
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

        $anterior = $configuracoes->robo_whatsapp_situacao;

        // reconexão automática do aparelho: continua conectado até a Evolution dizer que caiu
        if ($anterior === $situacao || ($anterior === SituacaoRoboWhatsapp::Conectado && $situacao === SituacaoRoboWhatsapp::AguardandoLeitura)) {
            return;
        }

        $this->gravar($situacao);

        if ($anterior === SituacaoRoboWhatsapp::Conectado && $situacao === SituacaoRoboWhatsapp::Desconectado) {
            $this->alertar_queda();
        }
    }

    /**
     * E-mail ao Admin quando o robô cai sem ele ter pedido, no máximo um a cada 30 minutos (FR-014).
     */
    private function alertar_queda(): void
    {
        $configuracoes = Configuracoes::atual();

        if ($configuracoes->robo_whatsapp_desconexao_pedida) {
            return;
        }

        $ultimo = $configuracoes->robo_whatsapp_alerta_queda_em;
        $configuracoes->forceFill(['robo_whatsapp_alerta_queda_em' => now()])->save();

        if ($ultimo === null || $ultimo->lte(now()->subMinutes(self::MINUTOS_ENTRE_ALERTAS))) {
            $this->disparo->enviar($this->textos->robo_desconectado());
        }
    }

    private function gravar(SituacaoRoboWhatsapp $situacao, ?string $numero = null, ?string $perfil = null): void
    {
        $dados = [
            'robo_whatsapp_situacao' => $situacao,
            'robo_whatsapp_numero' => $numero,
            'robo_whatsapp_perfil' => $perfil !== null ? mb_substr($perfil, 0, 100) : null,
            'robo_whatsapp_atualizado_em' => now(),
        ];

        // conectar de novo encerra o pedido de desconexão (spec 025)
        if ($situacao !== SituacaoRoboWhatsapp::Desconectado) {
            $dados['robo_whatsapp_desconexao_pedida'] = false;
        }

        Configuracoes::atual()->forceFill($dados)->save();
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
