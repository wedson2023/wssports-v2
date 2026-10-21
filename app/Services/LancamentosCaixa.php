<?php

namespace App\Services;

use App\Enums\TipoLancamentoCaixa;
use App\Models\CaixaLancamentos;
use App\Models\Usuarios;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Suprimentos e retiradas no caixa do vendedor (spec 017, FR-014 a FR-015): lançar com o vendedor
 * bloqueado (como a prestação) e excluir só o que ainda não foi prestado. Vendedor inativo ou
 * excluído só recebe retirada (FR-014a).
 */
class LancamentosCaixa
{
    public function __construct(private readonly PrestacaoContasCaixa $prestacoes) {}

    public function lancar(Usuarios $vendedor, Usuarios $autor, TipoLancamentoCaixa $tipo, string $valor, Carbon $ocorrido_em, ?string $observacao): CaixaLancamentos
    {
        return DB::transaction(function () use ($vendedor, $autor, $tipo, $valor, $ocorrido_em, $observacao) {
            $vendedor = $this->prestacoes->bloquear($vendedor->id);

            if ($tipo === TipoLancamentoCaixa::Suprimento && ($vendedor->trashed() || ! $vendedor->ativo)) {
                throw ValidationException::withMessages(['tipo' => 'Vendedor inativo ou excluído não recebe suprimento.']);
            }

            $ultima = $this->prestacoes->ultima($vendedor);

            if ($ultima !== null && $ocorrido_em->lessThanOrEqualTo($ultima->periodo_fim)) {
                throw ValidationException::withMessages(['ocorrido_em' => 'Data anterior à última prestação de contas.']);
            }

            return CaixaLancamentos::create([
                'usuarios_id' => $vendedor->id,
                'tipo' => $tipo,
                'valor' => $valor,
                'ocorrido_em' => $ocorrido_em,
                'observacao' => $observacao,
                'lancado_por' => $autor->id,
            ]);
        });
    }

    public function excluir(CaixaLancamentos $lancamento, Usuarios $autor): void
    {
        DB::transaction(function () use ($lancamento, $autor) {
            $this->prestacoes->bloquear($lancamento->usuarios_id);
            $lancamento->refresh();

            if ($lancamento->caixa_prestacoes_contas_id !== null) {
                throw ValidationException::withMessages(['lancamento' => 'Este lançamento já entrou numa prestação de contas e não pode ser excluído.']);
            }

            $lancamento->excluido_por = $autor->id;
            $lancamento->save();
            $lancamento->delete();
        });
    }
}
