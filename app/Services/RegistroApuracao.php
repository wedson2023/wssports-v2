<?php

namespace App\Services;

use App\Enums\Carteira;
use App\Enums\OrigemApuracao;
use App\Enums\TipoOperacaoApuracao;
use App\Models\OperacoesApuracao;

/**
 * Grava as operações da apuração (spec 009, FR-036 a FR-038). Só insere; deve ser chamado dentro
 * da transação da própria operação, para o registro existir só se ela for gravada.
 */
class RegistroApuracao
{
    /**
     * @param  array{confrontos_id?: ?int, apostas_id?: ?int, apostas_palpites_id?: ?int, clientes_id?: ?int}  $referencias
     * @param  array<string, mixed>|null  $antes
     * @param  array<string, mixed>|null  $depois
     */
    public function registrar(
        TipoOperacaoApuracao $tipo,
        OrigemApuracao $origem,
        array $referencias,
        ?array $antes,
        ?array $depois,
        ?string $motivo,
        ?string $valor = null,
        ?Carteira $carteira = null,
        ?int $clientes_transacoes_id = null,
    ): OperacoesApuracao {
        return OperacoesApuracao::create([
            'tipo' => $tipo,
            'origem' => $origem,
            'confrontos_id' => $referencias['confrontos_id'] ?? null,
            'apostas_id' => $referencias['apostas_id'] ?? null,
            'apostas_palpites_id' => $referencias['apostas_palpites_id'] ?? null,
            'clientes_id' => $referencias['clientes_id'] ?? null,
            'carteira' => $carteira,
            'clientes_transacoes_id' => $clientes_transacoes_id,
            'valor' => $valor,
            'antes' => $antes,
            'depois' => $depois,
            'motivo' => $motivo !== null ? mb_substr($motivo, 0, 255) : null,
        ]);
    }
}
