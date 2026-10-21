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

    /**
     * Listas de usuários do painel (spec 015); quem pode ver cada uma é conferido na API.
     */
    public function supervisores(): Response
    {
        return Inertia::render('PanelUsers', ['funcao' => 'Supervisor']);
    }

    public function gerentes(): Response
    {
        return Inertia::render('PanelUsers', ['funcao' => 'Gerente']);
    }

    public function vendedores(): Response
    {
        return Inertia::render('PanelUsers', ['funcao' => 'Vendedor']);
    }
}
