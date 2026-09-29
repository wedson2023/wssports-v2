<?php

use App\Http\Controllers\AreaClienteAutenticacaoController;
use App\Http\Controllers\AreaClienteCadastroController;
use App\Http\Controllers\AreaClienteMeiosPagamentoController;
use App\Http\Controllers\AreaClienteMeusDadosController;
use App\Http\Controllers\AreaClienteRecuperacaoSenhaController;
use App\Http\Controllers\AutenticacaoController;
use App\Http\Controllers\ClientesConfiguracoesController;
use App\Http\Controllers\ClientesController;
use App\Http\Controllers\ClientesMeiosPagamentoController;
use App\Http\Controllers\ClientesPromocoesController;
use App\Http\Controllers\ClientesConfiguracoesPadraoController;
use App\Http\Controllers\ClientesTransacoesController;
use App\Http\Controllers\PermissoesUsuariosController;
use App\Http\Controllers\UsuariosController;
use App\Http\Middleware\GarantirAcessoCliente;
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

// área do cliente (apostador): guard próprio, separado do painel
Route::prefix('area-cliente')->group(function () {
    // limite de 5 cadastros por minuto por IP aplicado no StoreClientesRequest (mensagem em português)
    Route::post('cadastro', [AreaClienteCadastroController::class, 'store']);
    Route::post('auth/login', [AreaClienteAutenticacaoController::class, 'login']);
    Route::post('auth/recuperar-senha', [AreaClienteRecuperacaoSenhaController::class, 'solicitar']);
    Route::post('auth/redefinir-senha', [AreaClienteRecuperacaoSenhaController::class, 'redefinir']);

    // rotas autenticadas: exigem token de cliente, cliente ativo e token posterior à data de corte
    Route::middleware(['auth:clientes', GarantirAcessoCliente::class])->group(function () {
        Route::post('auth/refresh', [AreaClienteAutenticacaoController::class, 'refresh']);
        Route::post('auth/logout', [AreaClienteAutenticacaoController::class, 'logout']);

        Route::get('meus-dados', [AreaClienteMeusDadosController::class, 'show']);
        Route::patch('meus-dados', [AreaClienteMeusDadosController::class, 'update']);
        Route::put('meus-dados/senha', [AreaClienteMeusDadosController::class, 'alterar_senha']);
        Route::get('meus-dados/extrato', [AreaClienteMeusDadosController::class, 'extrato']);

        Route::apiResource('meios-pagamento', AreaClienteMeiosPagamentoController::class)
            ->parameters(['meios-pagamento' => 'meio_pagamento'])
            ->missing(fn () => abort(404, 'Meio de pagamento não encontrado.'));
    });
});

// gestão de clientes e promoções no painel: mesmo token dos usuários da spec 001
Route::middleware(['auth:api', 'garantir_acesso'])->group(function () {
    $cliente_nao_encontrado = fn () => abort(404, 'Cliente não encontrado.');

    // rotas específicas antes do apiResource, para não serem capturadas por clientes/{cliente}
    Route::get('clientes/excluidos', [ClientesController::class, 'excluidos']);
    Route::post('clientes/{cliente}/restaurar', [ClientesController::class, 'restaurar'])
        ->withTrashed()
        ->missing($cliente_nao_encontrado);
    Route::patch('clientes/{cliente}/situacao', [ClientesController::class, 'alterar_situacao'])
        ->missing($cliente_nao_encontrado);
    Route::apiResource('clientes', ClientesController::class)
        ->except('store')
        ->missing($cliente_nao_encontrado);

    Route::get('clientes/{cliente}/transacoes', [ClientesTransacoesController::class, 'index'])
        ->missing($cliente_nao_encontrado);
    Route::post('clientes/{cliente}/transacoes', [ClientesTransacoesController::class, 'store'])
        ->missing($cliente_nao_encontrado);

    Route::get('clientes/{cliente}/configuracoes', [ClientesConfiguracoesController::class, 'show'])
        ->missing($cliente_nao_encontrado);
    Route::put('clientes/{cliente}/configuracoes', [ClientesConfiguracoesController::class, 'update'])
        ->missing($cliente_nao_encontrado);

    Route::apiResource('clientes.meios-pagamento', ClientesMeiosPagamentoController::class)
        ->except('show')
        ->parameters(['meios-pagamento' => 'meio_pagamento'])
        ->missing(fn () => abort(404, 'Cliente ou meio de pagamento não encontrado.'));

    Route::get('clientes-configuracoes-padrao', [ClientesConfiguracoesPadraoController::class, 'show']);
    Route::put('clientes-configuracoes-padrao', [ClientesConfiguracoesPadraoController::class, 'update']);

    Route::post('clientes-promocoes/{promocao}/estornar', [ClientesPromocoesController::class, 'estornar'])
        ->missing(fn () => abort(404, 'Promoção não encontrada.'));
    Route::apiResource('clientes-promocoes', ClientesPromocoesController::class)
        ->parameters(['clientes-promocoes' => 'promocao'])
        ->missing(fn () => abort(404, 'Promoção não encontrada.'));
});
