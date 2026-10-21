<?php

namespace App\Http\Requests\Concerns;

use App\Support\FusoSistema;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Regras comuns das operações de dinheiro do caixa (spec 017): a senha do usuário logado confirma a
 * operação, como no antigo, e as datas e horas chegam no horário de Brasília.
 */
trait ConfirmacaoCaixa
{
    /**
     * A senha informada é a do usuário do painel logado.
     */
    private function regra_senha(): Closure
    {
        return function (string $atributo, mixed $senha, Closure $falhar) {
            if (! Hash::check((string) $senha, $this->user()->password)) {
                $falhar('Senha incorreta.');
            }
        };
    }

    /**
     * Data e hora do campo (horário de Brasília), em UTC; vazia vale agora.
     */
    private function data_hora(string $campo): Carbon
    {
        $valor = $this->validated($campo);

        return $valor ? Carbon::parse($valor, FusoSistema::FUSO)->utc() : now();
    }

    /**
     * A data e hora do campo não pode ser futura.
     */
    private function regra_nao_futura(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $falhar) {
            if (Carbon::parse((string) $valor, FusoSistema::FUSO)->greaterThan(now())) {
                $falhar('A data e hora não pode ser futura.');
            }
        };
    }
}
