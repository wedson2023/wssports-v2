<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * Tela do Admin no site com o botão de vínculo da conta do Mercado Pago (spec 007, US8). Quem
 * pode vincular é conferido na API, com o token do usuário do painel.
 */
class PainelController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Panel');
    }
}
