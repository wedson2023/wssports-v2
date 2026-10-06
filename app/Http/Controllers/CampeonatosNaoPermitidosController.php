<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Controllers\Concerns\GerenciarNaoPermitidos;
use App\Models\CampeonatosNaoPermitidos;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Campeonatos que não são exibidos para um alvo.
 */
class CampeonatosNaoPermitidosController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente, GerenciarNaoPermitidos;

    public static function middleware(): array
    {
        return [
            self::permissao_cliente('campeonatos_nao_permitidos.gerenciar'),
        ];
    }

    protected function modelo(): string
    {
        return CampeonatosNaoPermitidos::class;
    }

    protected function coluna_item(): string
    {
        return 'campeonatos_id';
    }
}
