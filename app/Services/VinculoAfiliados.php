<?php

namespace App\Services;

use App\Models\Afiliados;

/**
 * Código do link de indicação (?user=CODIGO) → afiliado (spec 012, R-05, FR-022 e FR-023). O código
 * é reconhecido sem diferenciar maiúsculas, inclusive o de um afiliado na lixeira, pelo valor
 * original (sem o sufixo de exclusão).
 */
class VinculoAfiliados
{
    /**
     * Afiliado dono do código, preferindo o fora da lixeira; null quando o código não existe.
     */
    public function resolver(?string $codigo): ?Afiliados
    {
        $codigo = mb_strtoupper(trim((string) $codigo));

        if ($codigo === '') {
            return null;
        }

        return Afiliados::withTrashed()
            ->where(fn ($consulta) => $consulta
                ->where('codigo', $codigo)
                ->orWhere('codigo', 'like', addcslashes($codigo, '\\%_').'\_deleted\_%'))
            ->orderByRaw('deleted_at is not null')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Afiliado a gravar no cliente novo: só o que pode receber clientes (Ativo e fora da lixeira);
     * os demais deixam o cliente sem ligação.
     */
    public function afiliados_id_para_cliente(?string $codigo): ?int
    {
        $afiliado = $this->resolver($codigo);

        return $afiliado?->pode_receber_clientes() ? $afiliado->id : null;
    }
}
