<?php

namespace App\Http\Requests\Concerns;

use App\Enums\TipoChavePix;
use App\Rules\CnpjValido;
use App\Rules\CpfValido;
use Closure;

/**
 * Validação e normalização da chave Pix conforme o tipo informado em pix_tipo_chave, usadas pelos
 * meios de pagamento do cliente e pelo Pix do afiliado (spec 012, R-13).
 */
trait ChavePix
{
    /**
     * A chave Pix é validada conforme o tipo de chave informado.
     */
    private function regra_chave_pix(): Closure
    {
        return function (string $atributo, mixed $chave, Closure $falhar) {
            $valida = match (TipoChavePix::tryFrom((string) $this->input('pix_tipo_chave'))) {
                TipoChavePix::Cpf => CpfValido::valido($chave),
                TipoChavePix::Cnpj => CnpjValido::valido($chave),
                TipoChavePix::Email => filter_var($chave, FILTER_VALIDATE_EMAIL) !== false,
                TipoChavePix::Telefone => (bool) preg_match('/^\d{10,13}$/', $chave),
                TipoChavePix::ChaveAleatória => (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $chave),
                null => true, // o tipo inválido já é apontado pela regra de pix_tipo_chave
            };

            if (! $valida) {
                $falhar('A chave Pix não corresponde ao tipo de chave informado.');
            }
        };
    }

    /**
     * CPF, CNPJ e telefone ficam só com dígitos; e-mail em minúsculas; chave aleatória como veio.
     */
    private function normalizar_chave_pix(string $chave): string
    {
        return match (TipoChavePix::tryFrom((string) $this->input('pix_tipo_chave'))) {
            TipoChavePix::Cpf, TipoChavePix::Cnpj, TipoChavePix::Telefone => preg_replace('/\D/', '', $chave),
            TipoChavePix::Email => mb_strtolower(trim($chave)),
            default => trim($chave),
        };
    }
}
