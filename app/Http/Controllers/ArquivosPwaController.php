<?php

namespace App\Http\Controllers;

use App\Fakes\DadosFake;
use App\Models\Configuracoes;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Arquivos do aplicativo instalável (PWA) servidos pelo Laravel: o service worker na raiz do site
 * (escopo "/") e o manifest montado a cada pedido, para que nome e ícones mudem sem publicar versão.
 */
class ArquivosPwaController extends Controller
{
    /**
     * Service worker gerado pelo build (public/build/sw.js), servido em /sw.js e sem cache.
     */
    public function service_worker(): BinaryFileResponse
    {
        $arquivo = public_path('build/sw.js');

        abort_unless(is_file($arquivo), 404);

        return response()->file($arquivo, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Service-Worker-Allowed' => '/',
            'Cache-Control' => 'no-cache',
        ]);
    }

    /**
     * Manifest do aplicativo (contracts/paginas.md).
     */
    public function manifest(): JsonResponse
    {
        $nome = Configuracoes::atual()->nome_sistema;
        $cor_fundo = DadosFake::tema()['cor_fundo'];

        return response()->json([
            'name' => $nome,
            'short_name' => $nome,
            'description' => $nome,
            'display' => 'standalone',
            'start_url' => '/',
            'scope' => '/',
            'id' => '/',
            'background_color' => $cor_fundo,
            'theme_color' => $cor_fundo,
            'icons' => DadosFake::icones(),
        ], 200, [
            'Content-Type' => 'application/manifest+json',
            'Cache-Control' => 'no-cache',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
