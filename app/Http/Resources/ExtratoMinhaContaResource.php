<?php

namespace App\Http\Resources;

use App\Enums\OrigemTransacao;
use App\Enums\SituacaoAposta;
use App\Enums\TipoRollover;
use App\Models\ClientesTransacoes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Linha do extrato da tela de créditos (spec 007, contracts/api.md §2). As relações (aposta,
 * rollover, depósito, saque e dados do bônus) chegam carregadas em lote pelo controller.
 *
 * @mixin ClientesTransacoes
 */
class ExtratoMinhaContaResource extends JsonResource
{
    /**
     * Rótulo da coluna OP. para cada origem, como no sistema antigo.
     */
    private const OPERACOES = [
        'Aposta' => 'Palpite',
        'Promoção' => 'Bônus',
        'Ajuste manual' => 'Ajuste',
    ];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $deposito = $this->relationLoaded('deposito') ? $this->getRelation('deposito') : null;
        $data = $deposito?->pago_em ?? $this->created_at;

        return [
            'id' => $this->id,
            'data' => $data->copy()->setTimezone('-03:00')->toIso8601String(),
            'operacao' => self::OPERACOES[$this->origem->value] ?? $this->origem->value,
            'tipo' => $this->tipo,
            'valor' => $this->valor,
            'aposta' => $this->aposta(),
            'rollover' => $this->rollover_deposito(),
            'bonus' => $this->bonus(),
            'saque' => $this->saque(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function aposta(): ?array
    {
        $aposta = $this->relationLoaded('aposta') ? $this->getRelation('aposta') : null;

        if ($aposta === null) {
            return null;
        }

        return [
            'codigo' => $aposta->codigo,
            'premio' => bcadd((string) $aposta->premio, (string) $aposta->valor_acrescido, 2),
            'resultado' => $aposta->resultado,
            'cancelada' => $aposta->situacao === SituacaoAposta::Cancelada,
        ];
    }

    /**
     * @return array{exigido: string, restante: string}|null
     */
    private function rollover_deposito(): ?array
    {
        $rollover = $this->relationLoaded('rollover') ? $this->getRelation('rollover') : null;

        if ($rollover === null || $rollover->tipo !== TipoRollover::Depósito) {
            return null;
        }

        return ['exigido' => (string) $rollover->valor_exigido, 'restante' => $rollover->restante()];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function bonus(): ?array
    {
        $rollover = $this->relationLoaded('rollover') ? $this->getRelation('rollover') : null;

        if ($rollover === null || $rollover->tipo !== TipoRollover::Bônus || $this->origem !== OrigemTransacao::Promoção) {
            return null;
        }

        // liberado, pode_converter e pode_excluir chegam calculados pelo controller (GestaoBonus)
        $acoes = $this->relationLoaded('acoes_bonus')
            ? $this->getRelation('acoes_bonus')
            : ['liberado' => '0.00', 'pode_converter' => false, 'pode_excluir' => false];
        $expirado = $rollover->expirado_em !== null;

        return [
            'rollover_id' => $rollover->id,
            'nome' => $rollover->promocao?->nome,
            'modalidade' => $rollover->promocao?->modalidade,
            'restante' => $rollover->restante(),
            'expira_em_dias' => $expirado || $rollover->expira_em === null || $rollover->cumprido_em !== null
                ? null
                : max(0, (int) ceil(now()->diffInDays($rollover->expira_em, false))),
            'expirado' => $expirado,
            ...$acoes,
        ];
    }

    /**
     * @return array{situacao: mixed, motivo: ?string}|null
     */
    private function saque(): ?array
    {
        $saque = $this->relationLoaded('saque') ? $this->getRelation('saque') : null;

        if ($saque === null) {
            return null;
        }

        return ['situacao' => $saque->situacao, 'motivo' => $saque->motivo_recusa];
    }
}
