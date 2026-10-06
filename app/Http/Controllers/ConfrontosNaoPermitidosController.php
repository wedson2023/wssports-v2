<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Controllers\Concerns\GerenciarNaoPermitidos;
use App\Models\ConfrontosNaoPermitidos;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Confrontos do pré-jogo que não são exibidos para um alvo.
 */
class ConfrontosNaoPermitidosController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente, GerenciarNaoPermitidos;

    public static function middleware(): array
    {
        return [
            self::permissao_cliente('confrontos_nao_permitidos.gerenciar'),
        ];
    }

    protected function modelo(): string
    {
        return ConfrontosNaoPermitidos::class;
    }

    protected function coluna_item(): string
    {
        return 'confrontos_id';
    }
}
