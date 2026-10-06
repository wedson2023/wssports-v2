<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Controllers\Concerns\GerenciarNaoPermitidos;
use App\Models\ConfrontosAoVivoNaoPermitidos;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Jogos da grade que não são exibidos no ao vivo para um alvo.
 */
class ConfrontosAoVivoNaoPermitidosController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente, GerenciarNaoPermitidos;

    public static function middleware(): array
    {
        return [
            self::permissao_cliente('confrontos_ao_vivo_nao_permitidos.gerenciar'),
        ];
    }

    protected function modelo(): string
    {
        return ConfrontosAoVivoNaoPermitidos::class;
    }

    protected function coluna_item(): string
    {
        return 'confrontos_id';
    }
}
