<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * Páginas dos afiliados (spec 012): a pública de cadastro e o painel do afiliado. Os dados do
 * painel vêm da API com o token do afiliado.
 */
class AfiliadosPaginasController extends Controller
{
    public function cadastro(): Response
    {
        return Inertia::render('AffiliateLanding');
    }

    public function painel(): Response
    {
        return Inertia::render('AffiliatePanel');
    }
}
