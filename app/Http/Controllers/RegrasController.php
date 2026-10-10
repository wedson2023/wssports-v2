<?php

namespace App\Http\Controllers;

use App\Fakes\DadosFake;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Regulamento da banca exibido no site. O texto ainda é fake (Princípio IX) até a spec de regras.
 */
class RegrasController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Rules', ['regras' => DadosFake::regras()]);
    }
}
