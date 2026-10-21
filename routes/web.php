<?php

use App\Http\Controllers\AfiliadosPaginasController;
use App\Http\Controllers\ArquivosPwaController;
use App\Http\Controllers\BilheteController;
use App\Http\Controllers\CassinoController;
use App\Http\Controllers\MinhaContaController;
use App\Http\Controllers\PaginaInicialController;
use App\Http\Controllers\PainelController;
use App\Http\Controllers\RegrasController;
use App\Http\Controllers\RetornoMercadoPagoController;
use Illuminate\Support\Facades\Route;

// tela principal do site (área "/", visitante)
Route::get('/', [PaginaInicialController::class, 'index'])->name('pagina_inicial');
Route::get('regras', [RegrasController::class, 'index'])->name('regras');
// bilhete em tempo real (spec 010)
Route::get('bilhete/{codigo}', [BilheteController::class, 'index'])->name('bilhete');
// área do cassino (spec 011)
Route::get('cassino', [CassinoController::class, 'index'])->name('cassino');
// saída do jogo do cassino: endereço fixo usado pelo provedor no home_url do jogo
Route::get('close.php', [CassinoController::class, 'fechar'])->name('cassino.fechar');
// tela de créditos do cliente (spec 007)
Route::get('minha-conta', [MinhaContaController::class, 'index'])->name('minha_conta');
// retorno da autorização do Mercado Pago (vínculo da conta da banca, spec 007)
Route::get('mercado-pago/retorno', [RetornoMercadoPagoController::class, 'index'])->name('mercado_pago.retorno');
// tela do Admin com o botão de vínculo do Mercado Pago
Route::get('painel', [PainelController::class, 'index'])->name('painel');
// listas de usuários do painel (spec 015)
Route::get('painel/supervisores', [PainelController::class, 'supervisores'])->name('painel.supervisores');
Route::get('painel/gerentes', [PainelController::class, 'gerentes'])->name('painel.gerentes');
Route::get('painel/vendedores', [PainelController::class, 'vendedores'])->name('painel.vendedores');
// cotações do painel (spec 016)
Route::get('painel/cotacoes', [PainelController::class, 'cotacoes'])->name('painel.cotacoes');
// caixa do painel (spec 017)
Route::get('painel/caixa', [PainelController::class, 'caixa'])->name('painel.caixa');
// afiliados (spec 012): página pública de cadastro e painel do afiliado
Route::get('cadastro-afiliado', [AfiliadosPaginasController::class, 'cadastro'])->name('cadastro_afiliado');
Route::get('afiliados', [AfiliadosPaginasController::class, 'painel'])->name('afiliados');

// aplicativo instalável (PWA): service worker na raiz do site e manifest montado no servidor
Route::get('sw.js', [ArquivosPwaController::class, 'service_worker'])->name('pwa.service_worker');
Route::get('manifest.webmanifest', [ArquivosPwaController::class, 'manifest'])->name('pwa.manifest');
// notificações push (spec 010): importado pelo service worker
Route::get('sw-notificacoes.js', [ArquivosPwaController::class, 'notificacoes'])->name('pwa.notificacoes');
