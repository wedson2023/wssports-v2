<?php

use App\Http\Controllers\ArquivosPwaController;
use App\Http\Controllers\MinhaContaController;
use App\Http\Controllers\PaginaInicialController;
use App\Http\Controllers\PainelController;
use App\Http\Controllers\RegrasController;
use App\Http\Controllers\RetornoMercadoPagoController;
use Illuminate\Support\Facades\Route;

// tela principal do site (área "/", visitante)
Route::get('/', [PaginaInicialController::class, 'index'])->name('pagina_inicial');
Route::get('regras', [RegrasController::class, 'index'])->name('regras');
// tela de créditos do cliente (spec 007)
Route::get('minha-conta', [MinhaContaController::class, 'index'])->name('minha_conta');
// retorno da autorização do Mercado Pago (vínculo da conta da banca, spec 007)
Route::get('mercado-pago/retorno', [RetornoMercadoPagoController::class, 'index'])->name('mercado_pago.retorno');
// tela do Admin com o botão de vínculo do Mercado Pago
Route::get('painel', [PainelController::class, 'index'])->name('painel');

// aplicativo instalável (PWA): service worker na raiz do site e manifest montado no servidor
Route::get('sw.js', [ArquivosPwaController::class, 'service_worker'])->name('pwa.service_worker');
Route::get('manifest.webmanifest', [ArquivosPwaController::class, 'manifest'])->name('pwa.manifest');
