<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * Painel de controle (spec 014): a Dashboard é a primeira tela para Admin, Supervisor, Gerente e
 * Vendedor. Quem pode ver cada seção é conferido na API, com o token do usuário do painel.
 */
class PainelController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Dashboard');
    }
}
