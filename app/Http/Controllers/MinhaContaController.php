<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Página da tela de créditos do cliente (/minha-conta, spec 007). Os dados vêm da API com o token
 * do cliente; o código "acesso" chega quando o painel abre a conta em acesso assistido.
 */
class MinhaContaController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Account', [
            'acesso' => $request->query('acesso'),
        ]);
    }
}
