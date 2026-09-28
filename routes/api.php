<?php

use App\Http\Controllers\AutenticacaoController;
use App\Http\Controllers\PermissoesUsuariosController;
use App\Http\Controllers\UsuariosController;
use Illuminate\Support\Facades\Route;

// autenticação por token JWT
Route::prefix('auth')->group(function () {
    Route::post('login', [AutenticacaoController::class, 'login']);

    Route::middleware(['auth:api', 'garantir_acesso'])->group(function () {
        Route::post('refresh', [AutenticacaoController::class, 'refresh']);
        Route::post('logout', [AutenticacaoController::class, 'logout']);
    });
});

// gestão de usuários: exige token válido e usuário ativo
Route::middleware(['auth:api', 'garantir_acesso'])->group(function () {
    Route::apiResource('usuarios', UsuariosController::class)
        ->missing(fn () => abort(404, 'Usuário não encontrado.'));

    // permissões diretas de um subordinado
    Route::controller(PermissoesUsuariosController::class)->group(function () {
        Route::get('usuarios/{usuario}/permissoes', 'index')
            ->missing(fn () => abort(404, 'Usuário não encontrado.'));
        Route::post('usuarios/{usuario}/permissoes', 'store')
            ->missing(fn () => abort(404, 'Usuário não encontrado.'));
        Route::delete('usuarios/{usuario}/permissoes/{permissao}', 'destroy')
            ->missing(fn () => abort(404, 'Usuário não encontrado.'));
    });
});
