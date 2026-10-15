<?php

namespace App\Services;

use App\Enums\Carteira;
use App\Enums\OrigemTransacao;
use App\Enums\SituacaoEncerramento;
use App\Events\ApostaAtualizada;
use App\Models\Apostas;
use App\Models\ApostasEncerramentos;
use App\Models\Clientes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Crédito do valor encerrado depois da janela de segurança da apuração (spec 010, FR-032): um
 * encerramento por transação, com a aposta travada, sempre pelo SaldoClientes.
 */
class LiquidacaoEncerramentos
{
    private const LOTE = 200;

    public function __construct(private SaldoClientes $saldo) {}

    /**
     * @return array{creditados: int, falhas: int}
     */
    public function liquidar(): array
    {
        $resumo = ['creditados' => 0, 'falhas' => 0];

        $ids = ApostasEncerramentos::where('situacao', SituacaoEncerramento::Registrado)
            ->where('creditar_em', '<=', now())
            ->orderBy('creditar_em')
            ->limit(self::LOTE)
            ->pluck('id');

        foreach ($ids as $id) {
            try {
                $codigo = $this->creditar((int) $id);

                if ($codigo !== null) {
                    $resumo['creditados']++;
                    ApostaAtualizada::emitir($codigo, 'encerramento');
                }
            } catch (Throwable $erro) {
                $resumo['falhas']++;
                Log::error("Crédito do encerramento {$id} desfeito: ".class_basename($erro).' - '.$erro->getMessage());
            }
        }

        return $resumo;
    }

    /**
     * @return string|null o código da aposta quando creditou
     */
    private function creditar(int $id): ?string
    {
        return DB::transaction(function () use ($id) {
            $apostas_id = ApostasEncerramentos::whereKey($id)->value('apostas_id');
            // ordem de bloqueio: aposta → encerramento (a mesma da apuração e do pedido)
            $aposta = Apostas::lockForUpdate()->findOrFail($apostas_id);
            $encerramento = ApostasEncerramentos::lockForUpdate()->findOrFail($id);

            // desfeito ou já creditado entre a busca e a trava
            if ($encerramento->situacao !== SituacaoEncerramento::Registrado) {
                return null;
            }

            $transacao = $this->saldo->creditar(
                Clientes::withTrashed()->findOrFail($encerramento->clientes_id),
                Carteira::Saldo,
                (string) $encerramento->valor,
                OrigemTransacao::EncerramentoAposta,
                null,
                $aposta->id,
                "Encerramento da aposta {$aposta->codigo}",
            );

            $encerramento->forceFill([
                'situacao' => SituacaoEncerramento::Creditado,
                'creditado_em' => now(),
                'clientes_transacoes_id' => $transacao->id,
            ])->save();

            return $aposta->codigo;
        });
    }
}
