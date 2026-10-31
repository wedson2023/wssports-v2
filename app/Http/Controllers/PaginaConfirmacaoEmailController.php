<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Página do link de confirmação de e-mail (spec 025, FR-031): abrir não confirma nada, para leitores
 * automáticos de link não acionarem a confirmação; só o botão da página confirma.
 */
class PaginaConfirmacaoEmailController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('ConfirmEmail', ['token' => (string) $request->query('token', '')]);
    }
}
