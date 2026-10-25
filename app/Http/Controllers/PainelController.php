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

    /**
     * Tela "Cotações" (spec 016); a aba vem na query string (?aba=).
     */
    public function cotacoes(): Response
    {
        return Inertia::render('PanelQuotes');
    }

    /**
     * Tela "Caixa" (spec 017); aba, tipo, período e foco vêm na query string.
     */
    public function caixa(): Response
    {
        return Inertia::render('PanelCash');
    }

    /**
     * Tela "Apostas" (spec 018); aba, período e filtros vêm na query string.
     */
    public function apostas(): Response
    {
        return Inertia::render('PanelBets');
    }

    /**
     * Tela "Clientes" (spec 019); aba, busca e filtros vêm na query string.
     */
    public function clientes(): Response
    {
        return Inertia::render('PanelClients');
    }

    /**
     * Tela "Bônus" (spec 020); aba, busca, situação e filtros vêm na query string.
     */
    public function bonus(): Response
    {
        return Inertia::render('PanelBonus');
    }
}
