<?php

namespace App\Console\Commands;

use App\Enums\OrigemApuracao;
use App\Models\Apostas;
use App\Services\LiquidacaoApostas;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Paga os prêmios cuja janela de segurança já passou (spec 009, R-11; substitui
 * apostas:premiadas). A apuração já estorna na hora; aqui ficam os créditos que esperavam a janela.
 */
class LiquidarPremiosCommand extends Command
{
    /**
     * Apostas por lote.
     */
    private const LOTE = 500;

    protected $signature = 'apostas:liquidar_premios';

    protected $description = 'Credita no saldo dos clientes os prêmios das apostas apuradas cuja janela de pagamento já passou';

    public function handle(LiquidacaoApostas $liquidacao): int
    {
        $limite = now()->subMinutes($liquidacao->minutos_janela());
        $resumo = ['liquidadas' => 0, 'creditado' => '0.00', 'estornado' => '0.00', 'falhas' => 0];

        Apostas::where('liquidacao_pendente', true)
            ->where(fn ($consulta) => $consulta->whereNull('apurada_em')->orWhere('apurada_em', '<=', $limite))
            ->orderBy('id')
            ->select('id')
            ->chunkById(self::LOTE, function ($apostas) use ($liquidacao, &$resumo) {
                foreach ($apostas as $aposta) {
                    $this->liquidar($liquidacao, $aposta->id, $resumo);
                }
            });

        $mensagem = "{$resumo['liquidadas']} apostas liquidadas, R$ {$resumo['creditado']} creditados, R$ {$resumo['estornado']} estornados, {$resumo['falhas']} falhas.";

        Log::info("Pagamento de prêmios: {$mensagem}");
        $this->info($mensagem);

        return self::SUCCESS;
    }

    /**
     * @param  array{liquidadas: int, creditado: string, estornado: string, falhas: int}  $resumo
     */
    private function liquidar(LiquidacaoApostas $liquidacao, int $apostas_id, array &$resumo): void
    {
        try {
            DB::transaction(function () use ($liquidacao, $apostas_id, &$resumo) {
                $aposta = Apostas::lockForUpdate()->find($apostas_id);
                $antes = (string) $aposta->valor_pago;

                $liquidacao->liquidar($aposta, OrigemApuracao::PagamentoPrêmios);

                $diferenca = bcsub((string) $aposta->valor_pago, $antes, 2);

                if (bccomp($diferenca, '0', 2) !== 0) {
                    $resumo['liquidadas']++;
                    $chave = bccomp($diferenca, '0', 2) > 0 ? 'creditado' : 'estornado';
                    $resumo[$chave] = bcadd($resumo[$chave], ltrim($diferenca, '-'), 2);
                }
            });
        } catch (Throwable $erro) {
            $resumo['falhas']++;
            Log::error("Pagamento do prêmio da aposta {$apostas_id} desfeito: ".class_basename($erro).' - '.$erro->getMessage());
        }
    }
}
