<?php

use App\Http\Controllers\ArquivosPwaController;
use App\Http\Controllers\PaginaInicialController;
use App\Http\Controllers\RegrasController;
use Illuminate\Support\Facades\Route;

// tela principal do site (área "/", visitante)
Route::get('/', [PaginaInicialController::class, 'index'])->name('pagina_inicial');
Route::get('regras', [RegrasController::class, 'index'])->name('regras');

// aplicativo instalável (PWA): service worker na raiz do site e manifest montado no servidor
Route::get('sw.js', [ArquivosPwaController::class, 'service_worker'])->name('pwa.service_worker');
Route::get('manifest.webmanifest', [ArquivosPwaController::class, 'manifest'])->name('pwa.manifest');
