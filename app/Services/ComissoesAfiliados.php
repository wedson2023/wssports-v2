<?php

namespace App\Services;

use App\Enums\EventoLancamentoAfiliado;
use App\Enums\TipoGanhoAfiliado;
use App\Models\Afiliados;
use App\Models\AfiliadosComissoes;
use App\Models\AfiliadosLancamentos;
use App\Models\Clientes;
use App\Models\Depositos;
use App\Models\Saques;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Lançamentos de comissão do afiliado, gravados dentro da transação do movimento do cliente, com a
 * regra vigente na hora (spec 012, FR-029 a FR-034, research R-06). Cada evento tem uma chave única:
 * o mesmo aviso repetido nunca gera dois lançamentos.
 */
class ComissoesAfiliados
{
    /**
     * Depósito aprovado: Qualquer depósito e Saldo líquido sempre; Primeiro depósito só no primeiro
     * depósito aprovado do cliente em toda a vida da conta.
     */
    public function registrar_deposito(Depositos $deposito, bool $primeiro_deposito): void
    {
        [$afiliado, $regra] = $this->afiliado_e_regra($deposito->clientes_id);

        if ($regra === null || ($regra->tipo_ganho === TipoGanhoAfiliado::PrimeiroDepósito && ! $primeiro_deposito)) {
            return;
        }

        $base = (string) $deposito->valor;

        $this->gravar($afiliado, $deposito->clientes_id, [
            'evento' => EventoLancamentoAfiliado::Depósito,
            'depositos_id' => $deposito->id,
            'chave_evento' => "deposito:{$deposito->id}",
            'tipo_ganho' => $regra->tipo_ganho,
            'base' => $base,
            'percentual' => (string) $regra->percentual,
            'valor' => EscolhaPromocoes::percentual($base, (string) $regra->percentual),
            'ocorrido_em' => $deposito->pago_em ?? now(),
        ]);
    }

    /**
     * Estorno total ou parcial de um depósito que gerou comissão: lançamento negativo com o mesmo
     * tipo e percentual do original, sobre o valor estornado agora (FR-030). Deve ser chamado depois
     * de gravar o novo valor_estornado do depósito, que entra na chave do evento.
     */
    public function registrar_estorno(Depositos $deposito, string $valor_estornado_agora): void
    {
        $original = AfiliadosLancamentos::where('chave_evento', "deposito:{$deposito->id}")->first();

        if ($original === null || ! Afiliados::whereKey($original->afiliados_id)->exists()) {
            return;
        }

        $total_estornado = bcadd((string) $deposito->valor_estornado, '0', 2);

        $this->gravar($original->afiliado, $deposito->clientes_id, [
            'evento' => EventoLancamentoAfiliado::EstornoDepósito,
            'depositos_id' => $deposito->id,
            'chave_evento' => "estorno:{$deposito->id}:{$total_estornado}",
            'tipo_ganho' => $original->tipo_ganho,
            'base' => $valor_estornado_agora,
            'percentual' => (string) $original->percentual,
            'valor' => $this->negativo(EscolhaPromocoes::percentual($valor_estornado_agora, (string) $original->percentual)),
            'ocorrido_em' => now(),
        ]);
    }

    /**
     * Saque pago: só no Saldo líquido, descontando a comissão.
     */
    public function registrar_saque(Saques $saque): void
    {
        [$afiliado, $regra] = $this->afiliado_e_regra($saque->clientes_id);

        if ($regra === null || ! $regra->tipo_ganho->desconta_saque()) {
            return;
        }

        $base = (string) $saque->valor;

        $this->gravar($afiliado, $saque->clientes_id, [
            'evento' => EventoLancamentoAfiliado::Saque,
            'saques_id' => $saque->id,
            'chave_evento' => "saque:{$saque->id}",
            'tipo_ganho' => $regra->tipo_ganho,
            'base' => $base,
            'percentual' => (string) $regra->percentual,
            'valor' => $this->negativo(EscolhaPromocoes::percentual($base, (string) $regra->percentual)),
            'ocorrido_em' => $saque->pago_em ?? now(),
        ]);
    }

    /**
     * Afiliado do cliente (fora da lixeira, em qualquer situação) e a regra vigente agora.
     *
     * @return array{0: ?Afiliados, 1: ?AfiliadosComissoes}
     */
    private function afiliado_e_regra(int $clientes_id): array
    {
        $afiliados_id = Clientes::withTrashed()->whereKey($clientes_id)->value('afiliados_id');
        $afiliado = $afiliados_id === null ? null : Afiliados::with('comissao_vigente')->find($afiliados_id);

        return [$afiliado, $afiliado?->comissao_vigente];
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    private function gravar(Afiliados $afiliado, int $clientes_id, array $dados): void
    {
        if (AfiliadosLancamentos::where('chave_evento', $dados['chave_evento'])->exists()) {
            return;
        }

        try {
            AfiliadosLancamentos::create([...$dados, 'afiliados_id' => $afiliado->id, 'clientes_id' => $clientes_id]);
        } catch (UniqueConstraintViolationException) {
            // gravado ao mesmo tempo por outro processo: nada a fazer (FR-033)
        }
    }

    private function negativo(string $valor): string
    {
        return bcmul($valor, '-1', 2);
    }
}
