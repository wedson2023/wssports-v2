<?php

namespace App\Console\Commands;

use App\Enums\SituacaoAvisoWhatsapp;
use App\Models\ApostasCombinacoesRisco;
use App\Models\Configuracoes;
use App\Services\AvisosWhatsapp;
use App\Services\CombinacoesRisco;
use App\Services\DisparoMensagens;
use App\Services\TextosMensagens;
use App\Support\FusoSistema;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Agendador dos avisos de combinação de risco nova (spec 014, FR-045 a FR-049, R-11): única origem
 * dos avisos. A cada minuto registra as combinações novas da banca toda (ainda não registradas hoje
 * nem ontem) e manda ao Admin uma mensagem com todas elas, pelo canal configurado (spec 025); o
 * painel só lê esses registros. Falha no envio é tentada de novo no minuto seguinte, até 3 vezes.
 */
class AvisarCombinacoesRiscoCommand extends Command
{
    protected $signature = 'apostas:avisar_combinacoes_risco';

    protected $description = 'Registra as combinações de risco novas e avisa o Admin';

    private const TENTATIVAS_MAXIMAS = 3;

    public function handle(CombinacoesRisco $combinacoes, AvisosWhatsapp $avisos, DisparoMensagens $disparo, TextosMensagens $textos): int
    {
        $configuracoes = Configuracoes::atual();
        $pode_enviar = $configuracoes->aviso_combinacao_whatsapp
            && (filled($configuracoes->telefone_whatsapp_admin) || filled($configuracoes->email_admin));

        $novas = $this->registrar_novas($combinacoes->listar(null), $pode_enviar);
        $enviadas = $pode_enviar ? $this->enviar_pendentes($avisos, $disparo, $textos) : 0;

        $this->info("Combinações novas: {$novas}; mensagens enviadas: {$enviadas}.");

        return self::SUCCESS;
    }

    /**
     * Grava as combinações que ainda não foram registradas hoje nem ontem (a combinação usa apostas
     * de ontem e hoje; assim não avisa de novo à meia-noite).
     *
     * @param  list<array<string, mixed>>  $lista
     */
    private function registrar_novas(array $lista, bool $pode_enviar): int
    {
        if ($lista === []) {
            return 0;
        }

        $hoje = now()->setTimezone(FusoSistema::FUSO)->startOfDay();
        $ja_registradas = ApostasCombinacoesRisco::withTrashed()
            ->whereIn('dia', [$hoje->toDateString(), $hoje->copy()->subDay()->toDateString()])
            ->whereIn('chave', array_column($lista, 'chave'))
            ->pluck('chave')
            ->all();

        $novas = array_filter($lista, fn (array $combinacao) => ! in_array($combinacao['chave'], $ja_registradas, true));

        foreach ($novas as $combinacao) {
            ApostasCombinacoesRisco::create([
                'dia' => $hoje->toDateString(),
                'chave' => $combinacao['chave'],
                'jogos' => $combinacao['jogos'],
                'opcoes' => $combinacao['opcoes'],
                'codigos' => $combinacao['codigos'],
                'usuarios_ids' => $combinacao['usuarios_ids'],
                'quantidade_bilhetes' => $combinacao['quantidade_bilhetes'],
                'valor_apostado' => $combinacao['valor_apostado'],
                'premio' => $combinacao['premio'],
                'situacao_whatsapp' => $pode_enviar ? SituacaoAvisoWhatsapp::Pendente : SituacaoAvisoWhatsapp::Dispensado,
            ]);
        }

        return count($novas);
    }

    /**
     * Uma mensagem só com todas as pendentes; a falha conta uma tentativa em cada uma.
     */
    private function enviar_pendentes(AvisosWhatsapp $avisos, DisparoMensagens $disparo, TextosMensagens $textos): int
    {
        $pendentes = ApostasCombinacoesRisco::where('situacao_whatsapp', SituacaoAvisoWhatsapp::Pendente)
            ->where('tentativas_whatsapp', '<', self::TENTATIVAS_MAXIMAS)
            ->orderBy('id')
            ->get();

        if ($pendentes->isEmpty()) {
            return 0;
        }

        if (! $disparo->enviar_agora($textos->combinacao_risco($avisos->texto_combinacoes($pendentes)))) {
            Log::warning('Falha ao avisar combinação de risco.');

            foreach ($pendentes as $combinacao) {
                $tentativas = $combinacao->tentativas_whatsapp + 1;

                $combinacao->update([
                    'tentativas_whatsapp' => $tentativas,
                    'erro_whatsapp' => 'Nenhum canal entregou o aviso.',
                    'situacao_whatsapp' => $tentativas >= self::TENTATIVAS_MAXIMAS ? SituacaoAvisoWhatsapp::Falhou : SituacaoAvisoWhatsapp::Pendente,
                ]);
            }

            return 0;
        }

        ApostasCombinacoesRisco::whereIn('id', $pendentes->pluck('id'))->update([
            'situacao_whatsapp' => SituacaoAvisoWhatsapp::Enviado->value,
            'tentativas_whatsapp' => DB::raw('tentativas_whatsapp + 1'),
            'enviado_em' => now(),
            'erro_whatsapp' => null,
        ]);

        return $pendentes->count();
    }
}
