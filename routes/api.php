<?php

use App\Http\Controllers\AcessosAssistidosController;
use App\Http\Controllers\AfiliadosComissoesController;
use App\Http\Controllers\AfiliadosController;
use App\Http\Controllers\AfiliadosExtratoController;
use App\Http\Controllers\AfiliadosPrestacoesController;
use App\Http\Controllers\AreaAfiliadoAutenticacaoController;
use App\Http\Controllers\AreaAfiliadoCadastroController;
use App\Http\Controllers\AreaAfiliadoMeusDadosController;
use App\Http\Controllers\AreaAfiliadoPainelController;
use App\Http\Controllers\AreaAfiliadoRecuperacaoSenhaController;
use App\Http\Controllers\ApostasController;
use App\Http\Controllers\ApostasEncerramentosController;
use App\Http\Controllers\ApostasExportacaoController;
use App\Http\Controllers\ApostasListagemController;
use App\Http\Controllers\ApostasPalpitesController;
use App\Http\Controllers\ApostasPremiadosController;
use App\Http\Controllers\ApostasSegundaViaController;
use App\Http\Controllers\AreaClienteAcessoAssistidoController;
use App\Http\Controllers\AreaClienteApostasController;
use App\Http\Controllers\AreaClienteAutenticacaoController;
use App\Http\Controllers\AreaClienteBonusController;
use App\Http\Controllers\AreaClienteCadastroController;
use App\Http\Controllers\AreaClienteCassinoController;
use App\Http\Controllers\AreaClienteDepositosController;
use App\Http\Controllers\AreaClienteEncerramentoAutomaticoController;
use App\Http\Controllers\AreaClienteEncerramentosController;
use App\Http\Controllers\AreaClienteIndicacoesController;
use App\Http\Controllers\AreaClienteMeiosPagamentoController;
use App\Http\Controllers\AreaClienteMeusDadosController;
use App\Http\Controllers\AreaClienteMinhaContaController;
use App\Http\Controllers\AreaClienteNotificacoesController;
use App\Http\Controllers\AreaClienteRecuperacaoSenhaController;
use App\Http\Controllers\AreaClienteSaquesController;
use App\Http\Controllers\AutenticacaoController;
use App\Http\Controllers\AvisosController;
use App\Http\Controllers\BannersController;
use App\Http\Controllers\CaixaClientesController;
use App\Http\Controllers\CaixaController;
use App\Http\Controllers\CaixaExportacaoController;
use App\Http\Controllers\CaixaLancamentosController;
use App\Http\Controllers\CaixaPrestacoesController;
use App\Http\Controllers\CampeonatosController;
use App\Http\Controllers\CampeonatosNaoPermitidosController;
use App\Http\Controllers\CancelamentoApostasController;
use App\Http\Controllers\CassinoCarteiraController;
use App\Http\Controllers\CassinosAcoesController;
use App\Http\Controllers\CassinosChamadasController;
use App\Http\Controllers\CassinosJogosCanaisController;
use App\Http\Controllers\CassinosJogosController;
use App\Http\Controllers\CassinosProvedoresController;
use App\Http\Controllers\CassinosRodadasController;
use App\Http\Controllers\CassinosSessoesController;
use App\Http\Controllers\CassinosSincronizacoesController;
use App\Http\Controllers\ClientesConfiguracoesController;
use App\Http\Controllers\ClientesController;
use App\Http\Controllers\ClientesMeiosPagamentoController;
use App\Http\Controllers\ClientesPromocoesController;
use App\Http\Controllers\ClientesTransacoesController;
use App\Http\Controllers\ConfiguracoesAfiliadosController;
use App\Http\Controllers\ConfiguracoesApuracaoController;
use App\Http\Controllers\ConfiguracoesAvisosWhatsappController;
use App\Http\Controllers\ConfiguracoesCassinoController;
use App\Http\Controllers\ConfiguracoesCriarApostaController;
use App\Http\Controllers\ConfiguracoesEncerramentoController;
use App\Http\Controllers\ConfiguracoesLogoController;
use App\Http\Controllers\ConfiguracoesPagamentosController;
use App\Http\Controllers\ConfiguracoesRegrasController;
use App\Http\Controllers\ConfrontosAoVivoController;
use App\Http\Controllers\ConfrontosAoVivoNaoPermitidosController;
use App\Http\Controllers\ConfrontosController;
use App\Http\Controllers\ConfrontosLimitesController;
use App\Http\Controllers\ConfrontosNaoPermitidosController;
use App\Http\Controllers\ConfrontosResultadosController;
use App\Http\Controllers\ConfrontosTetoCotacoesController;
use App\Http\Controllers\CotacoesPainelController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EncerramentoEspeciaisController;
use App\Http\Controllers\EspeciaisController;
use App\Http\Controllers\EspeciaisOpcoesController;
use App\Http\Controllers\GatewayPagamentoController;
use App\Http\Controllers\PermissoesUsuariosController;
use App\Http\Controllers\PorcentagensCampeonatosController;
use App\Http\Controllers\PorcentagensClientesController;
use App\Http\Controllers\PorcentagensConfrontosController;
use App\Http\Controllers\PorcentagensVendedoresController;
use App\Http\Controllers\PublicoApostasController;
use App\Http\Controllers\PublicoAvisosController;
use App\Http\Controllers\PublicoBilhetesController;
use App\Http\Controllers\PublicoCassinoController;
use App\Http\Controllers\PublicoNotificacoesController;
use App\Http\Controllers\PublicoConfrontosController;
use App\Http\Controllers\PublicoConfrontosDetalheController;
use App\Http\Controllers\PublicoEspeciaisController;
use App\Http\Controllers\RestauracaoApostasController;
use App\Http\Controllers\SaquesController;
use App\Http\Controllers\TabelaJogosController;
use App\Http\Controllers\UsuariosConfiguracoesController;
use App\Http\Controllers\UsuariosController;
use App\Http\Controllers\ValidacaoApostasController;
use App\Http\Controllers\VisitantesConfiguracoesController;
use App\Http\Controllers\WebhooksMercadoPagoController;
use App\Http\Middleware\GarantirAcessoAfiliado;
use App\Http\Middleware\GarantirAcessoCliente;
use Illuminate\Support\Facades\Route;

// autenticação por token JWT
Route::prefix('auth')->group(function () {
    Route::post('login', [AutenticacaoController::class, 'login']);

    Route::middleware(['auth:api', 'garantir_acesso'])->group(function () {
        Route::post('refresh', [AutenticacaoController::class, 'refresh']);
        Route::post('logout', [AutenticacaoController::class, 'logout']);
        // dados do usuário logado para o painel (spec 014)
        Route::get('eu', [AutenticacaoController::class, 'eu']);
    });
});

// gestão de usuários: exige token válido e usuário ativo
Route::middleware(['auth:api', 'garantir_acesso'])->group(function () {
    // Lixeira de usuários (spec 015): antes do apiResource, para não cair em usuarios/{usuario}
    Route::get('usuarios/excluidos', [UsuariosController::class, 'excluidos']);
    Route::post('usuarios/{usuario}/restaurar', [UsuariosController::class, 'restaurar'])
        ->withTrashed()
        ->missing(fn () => abort(404, 'Usuário não encontrado.'));

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
    // acesso assistido (spec 007): troca o código gerado pelo painel por um token do cliente
    Route::post('acesso-assistido', [AreaClienteAcessoAssistidoController::class, 'iniciar'])->middleware('throttle:10,1');

    // rotas autenticadas: exigem token de cliente, cliente ativo e token posterior à data de corte
    Route::middleware(['auth:clientes', GarantirAcessoCliente::class])->group(function () {
        Route::post('auth/refresh', [AreaClienteAutenticacaoController::class, 'refresh']);
        Route::post('auth/logout', [AreaClienteAutenticacaoController::class, 'logout']);

        Route::get('meus-dados', [AreaClienteMeusDadosController::class, 'show']);
        Route::patch('meus-dados', [AreaClienteMeusDadosController::class, 'update']);
        Route::put('meus-dados/senha', [AreaClienteMeusDadosController::class, 'alterar_senha']);
        Route::get('meus-dados/extrato', [AreaClienteMeusDadosController::class, 'extrato']);

        Route::post('acesso-assistido/encerrar', [AreaClienteAcessoAssistidoController::class, 'encerrar']);

        // tela de créditos (spec 007): saldos e extrato
        Route::get('minha-conta', [AreaClienteMinhaContaController::class, 'show']);
        Route::get('minha-conta/extrato', [AreaClienteMinhaContaController::class, 'extrato']);
        Route::post('bonus/{rollover}/converter', [AreaClienteBonusController::class, 'converter'])->whereNumber('rollover');
        Route::post('bonus/{rollover}/excluir', [AreaClienteBonusController::class, 'excluir'])->whereNumber('rollover');

        // depósitos pelo Mercado Pago (spec 007)
        Route::get('depositos/opcoes', [AreaClienteDepositosController::class, 'opcoes']);
        Route::post('depositos', [AreaClienteDepositosController::class, 'store'])->middleware('throttle:10,1');
        Route::get('depositos/{codigo}', [AreaClienteDepositosController::class, 'show']);

        // saque via Pix (spec 007)
        Route::post('saques', [AreaClienteSaquesController::class, 'store'])->middleware('throttle:10,1');

        // minhas indicações (spec 007)
        Route::get('indicacoes', [AreaClienteIndicacoesController::class, 'index']);

        Route::apiResource('meios-pagamento', AreaClienteMeiosPagamentoController::class)
            ->parameters(['meios-pagamento' => 'meio_pagamento'])
            ->missing(fn () => abort(404, 'Meio de pagamento não encontrado.'));

        // apostas do cliente com o próprio saldo (o cliente não cancela nem edita)
        Route::post('apostas', [AreaClienteApostasController::class, 'store']);
        Route::get('apostas/{codigo}/situacao', [AreaClienteApostasController::class, 'situacao']);

        // encerramento de aposta pelo dono (spec 010): total, parcial e automático
        Route::post('apostas/{codigo}/encerramentos', [AreaClienteEncerramentosController::class, 'store'])->middleware('throttle:10,1');
        Route::get('apostas/{codigo}/encerramentos/{chave}', [AreaClienteEncerramentosController::class, 'show']);
        Route::put('apostas/{codigo}/encerramento-automatico', [AreaClienteEncerramentoAutomaticoController::class, 'update']);
        Route::delete('apostas/{codigo}/encerramento-automatico', [AreaClienteEncerramentoAutomaticoController::class, 'destroy']);

        // notificações push do aparelho (spec 010): inscrição e preferências por tipo de aviso
        Route::get('notificacoes/inscricao', [AreaClienteNotificacoesController::class, 'show']);
        Route::put('notificacoes/inscricao', [AreaClienteNotificacoesController::class, 'update']);
        Route::delete('notificacoes/inscricao', [AreaClienteNotificacoesController::class, 'destroy']);

        // cassino (spec 011): abrir jogo com dinheiro
        Route::post('cassino/jogos/{jogo}/abrir', [AreaClienteCassinoController::class, 'abrir'])
            ->middleware('throttle:20,1')
            ->whereNumber('jogo')
            ->missing(fn () => abort(422, 'Jogo indisponível no momento.'));
        // rodadas de uma sessão de jogo (linha do extrato)
        Route::get('cassino/sessoes/{sessao}/rodadas', [AreaClienteCassinoController::class, 'rodadas'])
            ->whereNumber('sessao')
            ->missing(fn () => abort(404, 'Sessão não encontrada.'));
    });
});

// área do afiliado (spec 012): guard próprio, separado do painel e dos clientes
Route::prefix('area-afiliado')->group(function () {
    // limite de 5 cadastros por minuto por IP aplicado no CadastroPublicoAfiliadoRequest
    Route::post('cadastro', [AreaAfiliadoCadastroController::class, 'store']);
    Route::post('auth/login', [AreaAfiliadoAutenticacaoController::class, 'login']);
    Route::post('auth/recuperar-senha', [AreaAfiliadoRecuperacaoSenhaController::class, 'solicitar']);
    Route::post('auth/redefinir-senha', [AreaAfiliadoRecuperacaoSenhaController::class, 'redefinir']);

    // rotas autenticadas: exigem token de afiliado, afiliado ativo e token posterior à data de corte
    Route::middleware(['auth:afiliados', GarantirAcessoAfiliado::class])->group(function () {
        Route::post('auth/refresh', [AreaAfiliadoAutenticacaoController::class, 'refresh']);
        Route::post('auth/logout', [AreaAfiliadoAutenticacaoController::class, 'logout']);

        // painel do afiliado (US7): só os dados do próprio afiliado do token
        Route::get('painel', [AreaAfiliadoPainelController::class, 'painel']);
        Route::get('extrato', [AreaAfiliadoPainelController::class, 'extrato']);
        Route::get('clientes', [AreaAfiliadoPainelController::class, 'clientes']);
        Route::get('prestacoes', [AreaAfiliadoPainelController::class, 'prestacoes']);
        Route::get('meus-dados', [AreaAfiliadoMeusDadosController::class, 'show']);
        Route::patch('meus-dados', [AreaAfiliadoMeusDadosController::class, 'update']);
        Route::put('meus-dados/senha', [AreaAfiliadoMeusDadosController::class, 'alterar_senha']);
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

    Route::post('clientes-promocoes/{promocao}/estornar', [ClientesPromocoesController::class, 'estornar'])
        ->missing(fn () => abort(404, 'Promoção não encontrada.'));
    Route::apiResource('clientes-promocoes', ClientesPromocoesController::class)
        ->parameters(['clientes-promocoes' => 'promocao'])
        ->missing(fn () => abort(404, 'Promoção não encontrada.'));
});

// gestão de afiliados no painel (spec 012): mesmo token dos usuários; Gerente só vê os seus
Route::middleware(['auth:api', 'garantir_acesso'])->group(function () {
    $afiliado_nao_encontrado = fn () => abort(404, 'Afiliado não encontrado.');

    // rotas específicas antes das de afiliados/{afiliado}
    Route::get('afiliados', [AfiliadosController::class, 'index']);
    Route::get('afiliados/excluidos', [AfiliadosController::class, 'excluidos']);
    Route::post('afiliados/{afiliado}/restaurar', [AfiliadosController::class, 'restaurar'])
        ->withTrashed()
        ->missing($afiliado_nao_encontrado);

    Route::post('afiliados', [AfiliadosController::class, 'store']);
    Route::get('afiliados/{afiliado}', [AfiliadosController::class, 'show'])
        ->whereNumber('afiliado')
        ->missing($afiliado_nao_encontrado);
    Route::patch('afiliados/{afiliado}', [AfiliadosController::class, 'update'])
        ->missing($afiliado_nao_encontrado);
    Route::delete('afiliados/{afiliado}', [AfiliadosController::class, 'destroy'])
        ->missing($afiliado_nao_encontrado);
    Route::patch('afiliados/{afiliado}/situacao', [AfiliadosController::class, 'alterar_situacao'])
        ->missing($afiliado_nao_encontrado);
    Route::get('afiliados/{afiliado}/lancamentos', [AfiliadosExtratoController::class, 'lancamentos'])
        ->missing($afiliado_nao_encontrado);
    Route::get('afiliados/{afiliado}/clientes', [AfiliadosExtratoController::class, 'clientes'])
        ->missing($afiliado_nao_encontrado);
    Route::post('afiliados/{afiliado}/aprovar', [AfiliadosController::class, 'aprovar'])
        ->missing($afiliado_nao_encontrado);
    Route::post('afiliados/{afiliado}/recusar', [AfiliadosController::class, 'recusar'])
        ->missing($afiliado_nao_encontrado);
    Route::get('afiliados/{afiliado}/comissoes', [AfiliadosComissoesController::class, 'index'])
        ->missing($afiliado_nao_encontrado);
    Route::put('afiliados/{afiliado}/comissao', [AfiliadosComissoesController::class, 'update'])
        ->missing($afiliado_nao_encontrado);
    Route::get('afiliados/{afiliado}/prestacoes', [AfiliadosPrestacoesController::class, 'index'])
        ->missing($afiliado_nao_encontrado);
    Route::post('afiliados/{afiliado}/prestacoes', [AfiliadosPrestacoesController::class, 'store'])
        ->missing($afiliado_nao_encontrado);
});

// listagem pública de jogos (pré-jogo e ao vivo): sem login; aceita token de cliente ou do painel
Route::get('publico/confrontos', [PublicoConfrontosController::class, 'index']);

// detalhe de um jogo com todas as cotações: mesmas regras e mesmo limite da listagem
Route::get('publico/confrontos/{confronto}', [PublicoConfrontosDetalheController::class, 'pre_jogo'])
    ->whereNumber('confronto');
Route::get('publico/confrontos-ao-vivo/{confronto_ao_vivo}', [PublicoConfrontosDetalheController::class, 'ao_vivo'])
    ->whereNumber('confronto_ao_vivo');

// apostas pelo site sem login: o visitante gera o código; qualquer um consulta pelo código
Route::post('publico/apostas/consultar', [PublicoApostasController::class, 'consultar']);
Route::post('publico/apostas', [PublicoApostasController::class, 'store']);
Route::get('publico/apostas/{codigo}', [PublicoApostasController::class, 'show']);

// bilhete em tempo real (spec 010): público pelo código; o token do cliente identifica o dono
Route::get('publico/bilhetes/{codigo}', [PublicoBilhetesController::class, 'show']);

// notificações push (spec 010): chave pública VAPID para o navegador inscrever o aparelho
Route::get('publico/notificacoes/configuracao', [PublicoNotificacoesController::class, 'configuracao']);

// cassino (spec 011): tela inicial e grade de jogos, sem login
Route::middleware('throttle:120,1')->group(function () {
    Route::get('publico/cassino', [PublicoCassinoController::class, 'inicio']);
    Route::get('publico/cassino/jogos', [PublicoCassinoController::class, 'jogos']);
});
Route::post('publico/cassino/jogos/{jogo}/demo', [PublicoCassinoController::class, 'demo'])
    ->middleware('throttle:20,1')
    ->whereNumber('jogo')
    ->missing(fn () => abort(422, 'Demo indisponível para este jogo.'));

// callback do provedor do cassino (spec 011): URL a cadastrar no hall (APP_URL/api/cassino/carteira);
// público, protegido pela chave do hall conferida em toda chamada
Route::post('cassino/carteira', CassinoCarteiraController::class)->middleware('throttle:1200,1');

// gestão de confrontos no painel: mesmo token dos usuários da spec 001
Route::middleware(['auth:api', 'garantir_acesso'])->group(function () {
    $usuario_nao_encontrado = fn () => abort(404, 'Usuário não encontrado.');
    $campeonato_nao_encontrado = fn () => abort(404, 'Campeonato não encontrado.');
    $confronto_nao_encontrado = fn () => abort(404, 'Confronto não encontrado.');

    // campeonatos e confrontos: listagem, ativar e desativar, favoritar e cadastro manual
    Route::patch('campeonatos/{campeonato}/situacao', [CampeonatosController::class, 'alterar_situacao'])
        ->missing($campeonato_nao_encontrado);
    Route::patch('campeonatos/{campeonato}/favorito', [CampeonatosController::class, 'alterar_favorito'])
        ->missing($campeonato_nao_encontrado);
    Route::apiResource('campeonatos', CampeonatosController::class)
        ->missing($campeonato_nao_encontrado);

    Route::get('confrontos-ao-vivo', [ConfrontosAoVivoController::class, 'index']);
    Route::patch('confrontos/{confronto}/situacao', [ConfrontosController::class, 'alterar_situacao'])
        ->missing($confronto_nao_encontrado);
    // resultado de jogo manual (spec 009)
    Route::put('confrontos/{confronto}/resultado', [ConfrontosResultadosController::class, 'update'])
        ->missing($confronto_nao_encontrado);
    Route::apiResource('confrontos', ConfrontosController::class)
        ->missing($confronto_nao_encontrado);

    // não permitidos (pré-jogo e ao vivo): desmarcar é exclusão lógica
    Route::apiResource('campeonatos-nao-permitidos', CampeonatosNaoPermitidosController::class)
        ->only(['index', 'store', 'destroy'])
        ->parameters(['campeonatos-nao-permitidos' => 'registro']);
    Route::apiResource('confrontos-nao-permitidos', ConfrontosNaoPermitidosController::class)
        ->only(['index', 'store', 'destroy'])
        ->parameters(['confrontos-nao-permitidos' => 'registro']);
    Route::apiResource('confrontos-ao-vivo-nao-permitidos', ConfrontosAoVivoNaoPermitidosController::class)
        ->only(['index', 'store', 'destroy'])
        ->parameters(['confrontos-ao-vivo-nao-permitidos' => 'registro']);

    // regras de cotação: porcentagens, cotação de um confronto e teto
    Route::get('porcentagens-vendedores/{usuario}', [PorcentagensVendedoresController::class, 'show'])
        ->missing($usuario_nao_encontrado);
    Route::patch('porcentagens-vendedores/{usuario}', [PorcentagensVendedoresController::class, 'update'])
        ->missing($usuario_nao_encontrado);
    Route::get('porcentagens-clientes', [PorcentagensClientesController::class, 'show']);
    Route::patch('porcentagens-clientes', [PorcentagensClientesController::class, 'update']);
    Route::get('porcentagens-campeonatos/{campeonato}', [PorcentagensCampeonatosController::class, 'show'])
        ->missing($campeonato_nao_encontrado);
    Route::patch('porcentagens-campeonatos/{campeonato}', [PorcentagensCampeonatosController::class, 'update'])
        ->missing($campeonato_nao_encontrado);
    Route::get('porcentagens-confrontos/{confronto}', [PorcentagensConfrontosController::class, 'show'])
        ->missing($confronto_nao_encontrado);
    Route::patch('porcentagens-confrontos/{confronto}', [PorcentagensConfrontosController::class, 'update'])
        ->missing($confronto_nao_encontrado);
    Route::get('confrontos-teto-cotacoes', [ConfrontosTetoCotacoesController::class, 'show']);
    Route::patch('confrontos-teto-cotacoes', [ConfrontosTetoCotacoesController::class, 'update']);

    // tela "Cotações" do painel (spec 016): consultas, prévia, Regras ativas, histórico e cadeados
    Route::prefix('cotacoes')->controller(CotacoesPainelController::class)->group(function () use ($usuario_nao_encontrado) {
        Route::get('nomes', 'nomes');
        Route::get('publicos', 'publicos');
        Route::get('clientes', 'clientes');
        Route::get('usuarios/{usuario}', 'usuario')->missing($usuario_nao_encontrado);
        Route::get('previa', 'previa');
        Route::get('previa/jogos', 'previa_jogos');
        Route::get('paises', 'paises');
        Route::get('campeonatos', 'campeonatos');
        Route::get('confrontos', 'confrontos');
        Route::get('regras-ativas', 'regras_ativas');
        Route::get('historico', 'historico');
        Route::post('cadeados', 'trancar');
        Route::delete('cadeados', 'destrancar');
    });

    // configurações dos vendedores (por alcance) e dos visitantes
    Route::get('usuarios-configuracoes', [UsuariosConfiguracoesController::class, 'index']);
    // configuração de um vendedor da rede (spec 015)
    Route::get('usuarios-configuracoes/{usuario}', [UsuariosConfiguracoesController::class, 'show'])
        ->missing(fn () => abort(404, 'Usuário não encontrado.'));
    Route::patch('usuarios-configuracoes', [UsuariosConfiguracoesController::class, 'update']);
    Route::get('visitantes-configuracoes', [VisitantesConfiguracoesController::class, 'show']);
    Route::put('visitantes-configuracoes', [VisitantesConfiguracoesController::class, 'update']);

    // limite de valor apostado por confronto (pré-jogo e ao vivo)
    Route::patch('confrontos/{confronto}/limite', [ConfrontosLimitesController::class, 'pre_jogo'])
        ->missing($confronto_nao_encontrado);
    Route::patch('confrontos-ao-vivo/{confronto_ao_vivo}/limite', [ConfrontosLimitesController::class, 'ao_vivo'])
        ->missing($confronto_nao_encontrado);
});

// apostas no painel: vendedor aposta, acompanha e valida código; vendedor e hierarquia cancelam;
// a hierarquia edita palpites
Route::middleware(['auth:api', 'garantir_acesso'])->group(function () {
    // lista de apostas do painel (spec 018): as rotas fixas vêm antes de apostas/{codigo}
    Route::get('apostas', [ApostasListagemController::class, 'index']);
    Route::get('apostas/resumo', [ApostasListagemController::class, 'resumo']);
    Route::get('apostas/vendedores', [ApostasListagemController::class, 'vendedores']);
    Route::get('apostas/premiados', ApostasPremiadosController::class);
    Route::get('apostas/exportacao', ApostasExportacaoController::class);
    Route::get('apostas/{codigo}', [ApostasListagemController::class, 'show'])->where('codigo', '[A-Za-z0-9]{8}');
    Route::get('apostas/{codigo}/segunda-via', ApostasSegundaViaController::class)->where('codigo', '[A-Za-z0-9]{8}');

    Route::post('apostas', [ApostasController::class, 'store']);
    Route::get('apostas/{codigo}/situacao', [ApostasController::class, 'situacao']);

    Route::get('apostas/pendentes/{codigo}', [ValidacaoApostasController::class, 'show']);
    Route::post('apostas/pendentes/{codigo}/validar', [ValidacaoApostasController::class, 'store']);

    Route::post('apostas/{codigo}/cancelar', [CancelamentoApostasController::class, 'store']);
    // restaurar aposta cancelada (spec 018): Admin, Supervisor e Gerente
    Route::post('apostas/{codigo}/restaurar', [RestauracaoApostasController::class, 'store']);

    Route::post('apostas/{codigo}/palpites/{palpite}/cancelar', [ApostasPalpitesController::class, 'cancelar'])
        ->whereNumber('palpite');
    Route::post('apostas/{codigo}/palpites/{palpite}/restaurar', [ApostasPalpitesController::class, 'restaurar'])
        ->whereNumber('palpite');

    // tabela de jogos impressa pelo vendedor, com as cotações dele
    Route::get('tabela-jogos', [TabelaJogosController::class, 'index']);

    // encerramentos de aposta dos clientes (spec 010): Admin e Supervisor
    Route::get('apostas-encerramentos', [ApostasEncerramentosController::class, 'index']);
});

// aviso da banca no site (sem login; aceita token de cliente): o aviso a mostrar e o "Lido"
Route::get('publico/avisos/atual', [PublicoAvisosController::class, 'atual'])->middleware('throttle:30,1');
Route::post('publico/avisos/{aviso}/leituras', [PublicoAvisosController::class, 'ler'])
    ->middleware('throttle:30,1')
    ->missing(fn () => abort(404, 'Aviso não encontrado.'));

// recursos do site no painel: texto das regras da banca, logo, avisos e banners
Route::middleware(['auth:api', 'garantir_acesso'])->group(function () {
    Route::get('configuracoes/regras', [ConfiguracoesRegrasController::class, 'show']);
    Route::put('configuracoes/regras', [ConfiguracoesRegrasController::class, 'update']);
    Route::post('configuracoes/logo', [ConfiguracoesLogoController::class, 'update']);

    Route::apiResource('banners', BannersController::class)
        ->missing(fn () => abort(404, 'Banner não encontrado.'));

    Route::apiResource('avisos', AvisosController::class)
        ->parameters(['avisos' => 'aviso'])
        ->missing(fn () => abort(404, 'Aviso não encontrado.'));
});

// especiais (aposta de vencedor com cotação fixa): lista pública sem login
Route::get('publico/especiais', [PublicoEspeciaisController::class, 'index']);

// especiais no painel: categorias, opções, encerramento e cancelamento
Route::middleware(['auth:api', 'garantir_acesso'])->group(function () {
    $especial_nao_encontrado = fn () => abort(404, 'Categoria especial não encontrada.');

    Route::post('especiais/{especial}/encerrar', [EncerramentoEspeciaisController::class, 'encerrar'])
        ->missing($especial_nao_encontrado);
    Route::post('especiais/{especial}/cancelar', [EncerramentoEspeciaisController::class, 'cancelar'])
        ->missing($especial_nao_encontrado);
    Route::apiResource('especiais', EspeciaisController::class)
        ->parameters(['especiais' => 'especial'])
        ->missing($especial_nao_encontrado);
    Route::apiResource('especiais.opcoes', EspeciaisOpcoesController::class)
        ->except(['index', 'show'])
        ->parameters(['especiais' => 'especial', 'opcoes' => 'opcao'])
        ->missing(fn () => abort(404, 'Categoria ou opção não encontrada.'));
});

// avisos do Mercado Pago (spec 007): públicos, conferidos pela assinatura e processados na fila
Route::post('webhooks/mercado-pago', [WebhooksMercadoPagoController::class, 'store'])->middleware('throttle:120,1');

// pagamentos no painel (spec 007): gateway, configurações de pagamento, saques e acesso assistido
Route::middleware(['auth:api', 'garantir_acesso'])->group(function () {
    Route::get('configuracoes/gateway', [GatewayPagamentoController::class, 'show']);
    Route::post('configuracoes/gateway/mercado-pago/autorizar', [GatewayPagamentoController::class, 'autorizar']);
    Route::delete('configuracoes/gateway', [GatewayPagamentoController::class, 'destroy']);

    Route::get('configuracoes/pagamentos', [ConfiguracoesPagamentosController::class, 'show']);
    Route::put('configuracoes/pagamentos', [ConfiguracoesPagamentosController::class, 'update']);

    // configurações gerais do Criar Aposta (spec 008)
    Route::get('configuracoes/criar-aposta', [ConfiguracoesCriarApostaController::class, 'show']);
    Route::put('configuracoes/criar-aposta', [ConfiguracoesCriarApostaController::class, 'update']);

    // configurações gerais da apuração (spec 009)
    Route::get('configuracoes/apuracao', [ConfiguracoesApuracaoController::class, 'show']);
    Route::put('configuracoes/apuracao', [ConfiguracoesApuracaoController::class, 'update']);

    // configurações gerais do encerramento de aposta (spec 010)
    Route::get('configuracoes/encerramento', [ConfiguracoesEncerramentoController::class, 'show']);
    Route::put('configuracoes/encerramento', [ConfiguracoesEncerramentoController::class, 'update']);

    // configurações do cassino (spec 011)
    Route::get('configuracoes/cassino', [ConfiguracoesCassinoController::class, 'show']);
    Route::put('configuracoes/cassino', [ConfiguracoesCassinoController::class, 'update']);
    // regra de comissão padrão do cadastro público de afiliados (spec 012)
    Route::get('configuracoes/afiliados', [ConfiguracoesAfiliadosController::class, 'show']);
    Route::put('configuracoes/afiliados', [ConfiguracoesAfiliadosController::class, 'update']);
    // aviso de combinação de risco nova no WhatsApp do Admin (spec 014)
    Route::get('configuracoes/avisos-whatsapp', [ConfiguracoesAvisosWhatsappController::class, 'show']);
    Route::put('configuracoes/avisos-whatsapp', [ConfiguracoesAvisosWhatsappController::class, 'update']);

    Route::post('clientes/{cliente}/acesso-assistido', [AcessosAssistidosController::class, 'store'])
        ->missing(fn () => abort(404, 'Cliente não encontrado.'));

    $saque_nao_encontrado = fn () => abort(404, 'Saque não encontrado.');
    Route::get('saques', [SaquesController::class, 'index']);
    Route::post('saques/{saque}/pagar', [SaquesController::class, 'pagar'])->missing($saque_nao_encontrado);
    Route::post('saques/{saque}/recusar', [SaquesController::class, 'recusar'])->missing($saque_nao_encontrado);
});

// cassino no painel (spec 011): catálogo, sincronização, tipos de ação e chamadas do provedor
// (cassinos_jogos.gerenciar); relatório e histórico da sessão (cassinos_rodadas.listar)
Route::middleware(['auth:api', 'garantir_acesso'])->group(function () {
    $jogo_nao_encontrado = fn () => abort(404, 'Jogo não encontrado.');
    Route::get('cassinos-jogos', [CassinosJogosController::class, 'index']);
    Route::put('cassinos-jogos/{jogo}', [CassinosJogosController::class, 'update'])->missing($jogo_nao_encontrado);
    Route::put('cassinos-jogos-canais/{canal}', [CassinosJogosCanaisController::class, 'update'])
        ->missing(fn () => abort(404, 'Canal não encontrado.'));

    Route::get('cassinos-provedores', [CassinosProvedoresController::class, 'index']);
    Route::put('cassinos-provedores/{provedor}', [CassinosProvedoresController::class, 'update'])
        ->missing(fn () => abort(404, 'Provedor não encontrado.'));

    Route::get('cassinos-sincronizacoes', [CassinosSincronizacoesController::class, 'index']);
    Route::post('cassinos-sincronizacoes', [CassinosSincronizacoesController::class, 'store'])->middleware('throttle:1,1');

    Route::apiResource('cassinos-acoes', CassinosAcoesController::class)
        ->except('show')
        ->parameters(['cassinos-acoes' => 'acao'])
        ->missing(fn () => abort(404, 'Tipo de ação não encontrado.'));

    Route::get('cassinos-chamadas', [CassinosChamadasController::class, 'index']);

    Route::get('cassinos-rodadas/relatorio', [CassinosRodadasController::class, 'relatorio']);
    Route::get('cassinos-sessoes/{sessao}/historico', [CassinosSessoesController::class, 'historico'])
        ->missing(fn () => abort(404, 'Sessão não encontrada.'));
});

// Dashboard do painel (spec 014): uma rota por seção, com a permissão de cada uma no controller; os
// avisos de combinação só leem o que o agendador registrou
Route::middleware(['auth:api', 'garantir_acesso'])->prefix('dashboard')->controller(DashboardController::class)->group(function () {
    Route::get('apurado', 'apurado');
    Route::get('situacao-jogos', 'situacao_jogos');
    Route::get('apostas', 'apostas');
    Route::get('combinacoes-risco/avisos', 'avisos_combinacoes_risco');
    Route::get('combinacoes-risco', 'combinacoes_risco');
    Route::get('apostas-um-jogo', 'apostas_um_jogo');
    Route::get('vendedores', 'vendedores');
    Route::get('jogos-mais-apostados', 'jogos_mais_apostados');
    Route::get('opcoes-mais-apostadas', 'opcoes_mais_apostadas');
    Route::get('caixa-online', 'caixa_online');
});

// Caixa do painel (spec 017): caixa da rede, lançamentos, prestação de contas, Caixa clientes e
// planilha; cada controller confere a permissão e o alcance. Os vendedores inativos e excluídos
// continuam alcançáveis para baixar o que devem (FR-014a)
Route::middleware(['auth:api', 'garantir_acesso'])->prefix('caixa')->group(function () {
    $usuario_nao_encontrado = fn () => abort(404, 'Usuário não encontrado.');

    Route::get('resumo', [CaixaController::class, 'resumo']);
    Route::get('usuarios', [CaixaController::class, 'usuarios']);
    Route::get('vendedores/{usuario}/grafico', [CaixaController::class, 'grafico'])->withTrashed()->missing($usuario_nao_encontrado);
    Route::post('vendedores/{usuario}/limites', [CaixaController::class, 'limites'])->withTrashed()->missing($usuario_nao_encontrado);
    Route::put('gerentes/{usuario}/comissao', [CaixaController::class, 'comissao_gerente'])->withTrashed()->missing($usuario_nao_encontrado);

    Route::get('vendedores/{usuario}/transacoes', [CaixaLancamentosController::class, 'transacoes'])->withTrashed()->missing($usuario_nao_encontrado);
    Route::post('vendedores/{usuario}/lancamentos', [CaixaLancamentosController::class, 'store'])->withTrashed()->missing($usuario_nao_encontrado);
    Route::delete('lancamentos/{lancamento}', [CaixaLancamentosController::class, 'destroy'])
        ->missing(fn () => abort(404, 'Lançamento não encontrado.'));

    Route::get('vendedores/{usuario}/prestacao', [CaixaPrestacoesController::class, 'resumo_vendedor'])->withTrashed()->missing($usuario_nao_encontrado);
    Route::post('vendedores/{usuario}/prestacoes', [CaixaPrestacoesController::class, 'store_vendedor'])->withTrashed()->missing($usuario_nao_encontrado);
    Route::get('gerentes/{usuario}/prestacao', [CaixaPrestacoesController::class, 'resumo_gerente'])->withTrashed()->missing($usuario_nao_encontrado);
    Route::post('gerentes/{usuario}/prestacoes', [CaixaPrestacoesController::class, 'store_gerente'])->withTrashed()->missing($usuario_nao_encontrado);
    Route::post('prestacoes/{prestacao}/desfazer', [CaixaPrestacoesController::class, 'desfazer'])
        ->missing(fn () => abort(404, 'Prestação de contas não encontrada.'));

    Route::get('clientes/resumo', [CaixaClientesController::class, 'resumo']);
    Route::get('clientes', [CaixaClientesController::class, 'index']);

    Route::get('exportar', CaixaExportacaoController::class);
});
