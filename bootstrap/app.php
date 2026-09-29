<?php

use App\Http\Middleware\GarantirAcesso;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'permission' => PermissionMiddleware::class,
            'garantir_acesso' => GarantirAcesso::class,
        ]);

        // a API não redireciona visitantes não autenticados (responde 401); não há tela de login
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : '/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // rotas da API sempre respondem erros em JSON, mesmo sem o cabeçalho Accept
        $e_api = fn (Request $request) => $request->is('api/*') || $request->expectsJson();

        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $e_api($request));

        // respostas de erro da API em português
        $exceptions->render(function (AuthenticationException $excecao, Request $request) use ($e_api) {
            if ($e_api($request)) {
                return response()->json(['message' => 'Não autenticado.'], 401);
            }
        });

        $exceptions->render(function (UnauthorizedException $excecao, Request $request) use ($e_api) {
            if ($e_api($request)) {
                return response()->json(['message' => 'Você não tem permissão para esta ação.'], 403);
            }
        });
    })->create();
