---

description: "Lista de tarefas da feature 014 — Painel de controle: base do painel e Dashboard"
---

# Tasks: Painel de controle — base do painel e Dashboard

**Input**: Documentos de design em `specs/014-painel-dashboard/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md),
[data-model.md](data-model.md), [contracts/api.md](contracts/api.md),
[contracts/paginas.md](contracts/paginas.md), [quickstart.md](quickstart.md)

**Tests**: o projeto não tem testes automatizados (Constituição). Nenhuma tarefa de teste; a
validação é manual pelo [quickstart.md](quickstart.md).

**Organization**: tarefas agrupadas por história de usuário (US1 a US7 da spec).

**Regras para toda tarefa**: alterar só os arquivos citados (Princípio IV; lista aprovada no
plan.md); nomes em snake_case português, componentes em inglês PascalCase com `index.jsx` +
`styles.jsx` (Princípios I e III); comentários em português; dinheiro em bcmath com strings de 2
casas; nenhuma SQL concatenada com dados da requisição; toda coluna nova com `->comment()`; ao fim
de cada tarefa, revisão de legibilidade do código alterado (Princípio V).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: pode rodar em paralelo (arquivos diferentes, sem dependência pendente)
- **[Story]**: história da spec (US1 a US7)

---

## Phase 1: Setup

**Purpose**: dependência nova aprovada (research R-16)

- [X] T001 Adicionar `chart.js` `^4.4` às dependências com `npm install chart.js@^4.4` (altera `package.json` e `package-lock.json`); não instalar `react-chartjs-2`

---

## Phase 2: Foundational (pré-requisitos de todas as histórias)

**Purpose**: permissões, alcance por nível, cache, índices e a infraestrutura das seções no frontend

**⚠️ CRITICAL**: nenhuma história começa antes desta fase

### Backend

- [X] T002 Em `app/Enums/Funcao.php`: criar `PERMISSOES_DASHBOARD` com as 9 permissões na ordem da tela (`dashboard.ver_apurado`, `dashboard.ver_situacao_jogos`, `dashboard.ver_apostas`, `dashboard.ver_combinacao_risco`, `dashboard.ver_apostas_um_jogo`, `dashboard.ver_vendedores`, `dashboard.ver_jogos_mais_apostados`, `dashboard.ver_opcoes_mais_apostadas`, `dashboard.ver_caixa_online`) e a constante `PERMISSOES_DASHBOARD_VENDEDOR` (apurado, situação dos jogos, apostas, apostas por um jogo); em `pode_usar`, para as de dashboard: Admin e Supervisor todas, Gerente todas menos `dashboard.ver_caixa_online`, Vendedor só as de `PERMISSOES_DASHBOARD_VENDEDOR`; em `permissoes_padrao`, incluir as de dashboard filtradas por `pode_usar` para todas as funções (o Vendedor passa a receber as de apostas + as 4 de dashboard); atualizar os docblocks de `pode_usar` (data-model §5, research R-09)
- [X] T003 Criar `database/seeders/DashboardSeeder.php` no padrão do método `permissoes()` de `database/seeders/CassinoSeeder.php`: `firstOrCreate` das 9 permissões no guard `api`, limpar o cache do spatie e dar a cada usuário existente `array_intersect(permissoes_padrao(), PERMISSOES_DASHBOARD)` com `givePermissionTo` (sem tirar nenhuma); docblock citando a spec 014 (depende de T002)
- [X] T004 Em `database/seeders/DatabaseSeeder.php`, chamar `$this->call(DashboardSeeder::class);` depois do `BannersSeeder` (depende de T003)
- [X] T005 [P] Criar a migration `database/migrations/2026_10_20_000003_add_indices_dashboard.php` com os índices `apostas (situacao, created_at)`, `depositos (situacao, pago_em)`, `saques (situacao, pago_em)` e `apostas_encerramentos (situacao, decidido_em)`, e o `down` removendo os quatro (data-model §4, research R-08)
- [X] T006 [P] Criar `app/Services/AlcanceDashboard.php`: `ids_vendedores(Usuarios $usuario): ?array` que devolve `null` para Admin (banca toda), `[$usuario->id]` para Vendedor e, para Gerente e Supervisor, os ids da sub-hierarquia **incluindo excluídos** (laço por nível com `Usuarios::withTrashed()->whereIn('usuarios_id', ...)`), sem alterar `app/Models/Usuarios.php`; `aplicar(Builder $consulta, ?array $ids, string $coluna = 'usuarios_id')` que faz `whereIn` só quando `$ids !== null`; e `periodo_do_dia(): array{0: Carbon, 1: Carbon}` (início e fim do dia em Brasília convertidos para UTC com `App\Support\FusoSistema`) e `periodo_de_ontem_e_hoje()` (research R-03 e R-04)
- [X] T007 [P] Criar `app/Services/CacheDashboard.php`: `lembrar(string $secao, Usuarios $usuario, bool $atualizar, Closure $calcular): array` com chave `"dashboard:{secao}:{usuarios_id}:{Y-m-d em Brasília}"`, `Cache::forget` antes quando `$atualizar`, `Cache::remember` por 60 s, e devolvendo `['data' => ..., 'gerado_em' => ISO 8601 em -03:00]` (research R-08, FR-042)
- [X] T008 [P] Criar `app/Http/Requests/DashboardRequest.php` com a regra `atualizar` => `['sometimes', 'boolean']`, `authorize` devolvendo true (permissão no controller) e mensagem em português
- [X] T009 Criar `app/Http/Controllers/DashboardController.php` (ainda sem ações de seção) implementando `HasMiddleware` com o trait `GarantirPermissaoCliente`, um método privado `responder(string $secao, DashboardRequest $request, Closure $calcular): JsonResponse` que usa `CacheDashboard` e o `middleware()` que receberá um `permissao_cliente('dashboard.ver_<secao>', only: [<ação>])` por ação; docblock citando a spec 014 (depende de T006, T007, T008)

### Frontend

- [X] T010 [P] Criar `resources/js/utils/charts.js` com `cores_graficos(tema)` (cor principal, cor escura derivada, verde para entradas, vermelho para saídas, cinza para neutros, legíveis no fundo claro e escuro) e `opcoes_padrao(tipo, tema)` com fonte Roboto, legenda embaixo no celular, tooltips em R$ formatado via `utils/money.js` e `maintainAspectRatio: false`
- [X] T011 [P] Criar `resources/js/components/ChartCanvas/index.jsx` e `styles.jsx`: props `tipo` (`bar`, `doughnut`, `horizontal`), `dados`, `opcoes`, `altura`; carrega o Chart.js com `import('chart.js/auto')` dentro de `useEffect` (pedaço separado do build, FR-043), cria o gráfico num `<canvas>` com `useRef`, atualiza com `chart.update()` quando `dados` mudam e destrói ao desmontar; esqueleto do tamanho do gráfico enquanto a biblioteca carrega (depende de T001, T010)
- [X] T012 [P] Criar `resources/js/components/StatCard/index.jsx` e `styles.jsx`: ícone Material Icons na cor do tema (como o `Icon` de `screens/adm/dashboard/styles.js`), rótulo, valor (`R$` formatado ou número), `title` explicativo, props `$destaque` (Saldo líquido), `em_breve` (selo "em breve") e `cadeado` (`aberto`/`fechado`); responsivo em 2 colunas no celular (contracts/paginas §4)
- [X] T013 [P] Criar `resources/js/components/DashboardSection/index.jsx` e `styles.jsx`: cabeçalho escuro com título e ícone ⟳ (girando ao atualizar), como o `Header` de `screens/adm/dashboard/styles.js`; estados carregando (esqueleto), sem dados ("Sem movimento hoje."), erro (mensagem + "Tentar novamente") e conteúdo; prop `acoes_extras` para ícones no título (contracts/paginas §3)
- [X] T014 Criar `resources/js/hooks/useSecaoDashboard.js`: `useSecaoDashboard(caminho, { habilitada })` que busca `GET /dashboard/<caminho>` com o token da sessão de usuário, devolve `{ dados, gerado_em, carregando, atualizando, erro, sem_permissao, atualizar }`, mantém os dados antigos durante a atualização, `atualizar()` envia `?atualizar=1`, marca `sem_permissao` no 403 (a seção some) e trata 401 com `expirar()` do `useSessao`; atualização automática a cada 60 s só com `document.visibilityState === 'visible'` (parando com a aba oculta e atualizando ao voltar, `visibilitychange`, sem pedidos sobrepostos, limpando o intervalo ao desmontar); a atualização automática usa o cache, exceto com a opção `ignorar_cache: true` (usada pela Combinação de risco), que envia `?atualizar=1` (FR-044, research R-15)

**Checkpoint**: permissões, alcance, cache e a moldura das seções prontos

---

## Phase 3: User Story 1 — Entrar no painel e ver a Dashboard do seu nível (Priority: P1) 🎯 MVP

**Goal**: login leva cada nível ao lugar certo; `/painel` mostra o painel com barra, menu e a Dashboard (ainda sem seções)

**Independent Test**: quickstart §1

- [X] T015 [P] [US1] Criar `app/Http/Resources/UsuarioLogadoResource.php` com `id`, `nome`, `login`, `funcao` (valor do enum) e `secoes` = permissões de `Funcao::PERMISSOES_DASHBOARD` em que `Funcao::usuario_pode` é verdadeiro, sem o prefixo `dashboard.ver_`, na ordem da constante (contracts/api §1)
- [X] T016 [US1] Em `app/Http/Controllers/AutenticacaoController.php`, criar a ação `eu(Request $request)` que devolve `new UsuarioLogadoResource($request->user())`; em `routes/api.php`, registrar `Route::get('eu', [AutenticacaoController::class, 'eu']);` dentro do grupo `auth` já protegido por `['auth:api', 'garantir_acesso']` (depende de T015)
- [X] T017 [US1] Em `resources/js/hooks/useSessao.js`, criar `dados_do_usuario(token)` que chama `GET /auth/eu` e incluir o usuário em `nome_da_sessao`, gravando na sessão `nome` (o `nome` vindo da API; sem resposta, o login digitado) e `funcao`; manter `criar_sessao` recebendo os campos novos (research R-14)
- [X] T018 [US1] Em `resources/js/components/AuthModal/index.jsx`, após o login de `tipo === 'usuario'` com `funcao` diferente de `Vendedor`, fazer `router.visit('/painel')` e retornar, no mesmo ponto em que o afiliado vai para `/afiliados` (FR-003)
- [X] T019 [US1] Em `resources/js/components/Header/index.jsx`, fazer a saudação do usuário do painel (`sessao.tipo === 'usuario'`) virar `Link` para `/painel` com `title="Painel"`, mantendo o comportamento do cliente e do afiliado (FR-004)
- [X] T020 [P] [US1] Criar `resources/js/hooks/usePainel.js`: lê a sessão; sem sessão de `tipo === 'usuario'`, faz `router.visit('/')`; busca `GET /auth/eu` e devolve `{ usuario, carregando }` (com `secoes`); 401 → `expirar()` e volta para `/` (FR-006)
- [X] T021 [P] [US1] Criar `resources/js/components/PanelShell/index.jsx` e `styles.jsx` no visual de `screens/adm/components/header` do antigo: barra superior preta com logo (`tema.logo`), ícone ☰ (celular), botões `APOSTAR` (vai para `/` mantendo a sessão) e `SAIR` (confirmação com `sweetalert2`, `sair()` e `/`); menu lateral fixo no desktop e gaveta de 70% com fundo escurecido no celular (≤ 900 px), fechando ao tocar fora, escolher item ou no voltar (`useVoltarFecha`); item único "Dashboard" (ícone `bar_chart`) exibido quando `secoes` não está vazio; barra de título "Dashboard" com "Olá, Nome · Função" (FR-001, FR-002, FR-005)
- [X] T022 [US1] Criar `resources/js/pages/Dashboard/index.jsx` e `styles.jsx`: usa `usePainel`, `Head` com o nome do sistema e `PanelShell`; enquanto carrega, `Spinner`; o conteúdo das seções entra nas histórias seguintes (depende de T020, T021)
- [X] T023 [US1] Em `app/Http/Controllers/PainelController.php`, renderizar `Inertia::render('Dashboard')` e atualizar o docblock (painel da spec 014); remover a pasta `resources/js/pages/Panel/` (`index.jsx` e `styles.jsx`), sem tocar em `resources/js/hooks/useAdministrador.js` nem no backend do Mercado Pago (FR-007) (depende de T022)

**Checkpoint**: US1 validada pelo quickstart §1

---

## Phase 4: User Story 2 — Apurado do dia, Apostas e Situação dos jogos (Priority: P1)

**Goal**: as três seções principais com os números da rede de cada nível

**Independent Test**: quickstart §2

- [X] T024 [P] [US2] Em `app/Fakes/DadosFake.php`, criar `caixa_usuario(): array{lancamentos: string, retiradas: string}` devolvendo `'0.00'` e `'0.00'` e `percentual_comissao_gerente(): string` devolvendo `'0.00'`, cada um com o comentário "FAKE — … Substituído pela spec de caixa" no padrão dos demais métodos (research R-10)
- [X] T025 [US2] Criar `app/Services/ApuradoDashboard.php` com `calcular(Usuarios $usuario): array` conforme research R-05 e contracts/api §2.1: base `apostas` Ativa do dia com `clientes_id` nulo e alcance de `AlcanceDashboard`; `SUM` de `valor`, `valor` por resultado Aguardando/Vencedor/Perdedor, `premio` das Vencedor + `valor` das Devolvido (saídas) e `comissao` numa consulta agregada; comissão do gerente agrupando pelo gerente (`usuarios.usuarios_id` dos vendedores) com `percentual_comissao_gerente()` × líquido do grupo, zero quando ≤ 0, somando os grupos (`null` para o Vendedor); limites de `UsuariosConfiguracoes::do_vendedor` só para o Vendedor; para Admin e Supervisor, `depositos_online` (`depositos` Aprovado com `pago_em` no dia) somado às entradas e `saques_pagos` (`saques` Pago com `pago_em` no dia) somado às saídas, da banca toda; `lancamentos`/`retiradas` de `DadosFake::caixa_usuario()`; `saldo_liquido` = entradas − saídas − comissão vendedor − comissão gerente + lançamentos − retiradas; `provisorios` (o Vendedor sem `comissao_gerente`); tudo em bcmath (depende de T006, T024)
- [X] T026 [US2] Criar `app/Services/ApostasDashboard.php` com `situacao_jogos(Usuarios $usuario): array` (`COUNT(DISTINCT confrontos.id)` por situação a partir de `apostas_palpites` com `situacao = Ativo` e `confrontos_id` não nulo das apostas Ativa do dia do balcão no alcance; Bloqueado soma em Aguardando; chaves `total`, `aguardando`, `encerrados`, `adiados`, `cancelados`) e `resumo_apostas(Usuarios $usuario): array` (`quantidade` e `valor` de total/abertas/premiadas/perdidas das Ativa e de canceladas com `situacao = Cancelada`, contracts/api §2.3) (research R-06) (depende de T006)
- [X] T027 [US2] Em `app/Http/Controllers/DashboardController.php`, criar as ações `apurado`, `situacao_jogos` e `apostas` usando `responder()` e os serviços, com `permissao_cliente('dashboard.ver_apurado', only: ['apurado'])`, `dashboard.ver_situacao_jogos` e `dashboard.ver_apostas`; em `routes/api.php`, no grupo `['auth:api', 'garantir_acesso']`, registrar `GET dashboard/apurado`, `GET dashboard/situacao-jogos` e `GET dashboard/apostas` (depende de T009, T025, T026)
- [X] T028 [US2] Na página `resources/js/pages/Dashboard/index.jsx` (e `styles.jsx`), montar a grade de seções na ordem do antigo, exibindo só as presentes em `usuario.secoes`; criar a seção "APURADO DO DIA" com `DashboardSection` + `StatCard` na ordem e com os `title` do antigo (contracts/paginas §4), selo "em breve" para as chaves de `provisorios`, cadeado pelos limites e gráfico de barras `ChartCanvas` (Entradas × Saídas × Comissões × Saldo líquido) (depende de T011, T012, T013, T014, T022, T027)
- [X] T029 [US2] Na mesma página, criar as seções "SITUAÇÃO DOS JOGOS" (rosca + legenda Total/Aguardando/Encerrados/Adiados/Cancelados com os ícones do antigo) e "APOSTAS" (rosca + lista com quantidade e R$ de Total/Abertas/Premiadas/Perdidas/Canceladas) (contracts/paginas §5) (depende de T028)

**Checkpoint**: US2 validada pelo quickstart §2

---

## Phase 5: User Story 3 — Combinação de risco, Apostas por um jogo e avisos (Priority: P1)

**Goal**: risco por rede na tela, aviso de combinação nova no painel e no WhatsApp do Admin

**Independent Test**: quickstart §3 e §4

### Risco na Dashboard

- [X] T030 [US3] Criar `app/Services/CombinacoesRisco.php` com `listar(?array $ids_vendedores): array` conforme research R-07: subconsulta por aposta Ativa + Aguardando de `periodo_de_ontem_e_hoje()` do balcão (alcance pelos ids; `null` = banca toda), com palpites `situacao = Ativo`, assinatura por `GROUP_CONCAT(DISTINCT ... ORDER BY ... SEPARATOR '|')` de jogo/especial + `codigo_cotacao` + jogador + opção de especial, só apostas com pelo menos um jogo não encerrado; consulta externa `GROUP BY assinatura HAVING COUNT(*) >= 2` com quantidade, `SUM(valor)`, `SUM(premio)`, códigos, ids distintos dos vendedores (`usuarios_ids`, usados pelo agendador) e primeiro horário, ordenada pelo prêmio somado, limite 100; em PHP, montar `jogos[]` (`casa x fora` ou nome do especial) e `opcoes[]` (`App\Support\NomesCotacoes::nome`) só para as linhas devolvidas, `chave = sha1(assinatura)`, valores em string de 2 casas; SQL fixa em `selectRaw` com bindings (contracts/api §2.4) (depende de T006)
- [X] T031 [US3] Em `app/Services/ApostasDashboard.php`, criar `apostas_um_jogo(Usuarios $usuario): array`: apostas Ativa + Aguardando de ontem e hoje do balcão no alcance com exatamente 1 item não decidido (jogo distinto com palpite ativo em confronto Aguardando ou Bloqueado, ou palpite de especial com `resultado` Aguardando), ordenadas por prêmio, limite 50, com `codigo`, `apostador` (`apostas.nome`), `valor` e `premio` (research R-06, contracts/api §2.5) (depende de T026)
- [X] T032 [US3] Em `app/Http/Controllers/DashboardController.php`, criar as ações `combinacoes_risco` (usa `CombinacoesRisco::listar` com `AlcanceDashboard::ids_vendedores`) e `apostas_um_jogo`, com `permissao_cliente('dashboard.ver_combinacao_risco', only: ['combinacoes_risco'])` e `dashboard.ver_apostas_um_jogo`; em `routes/api.php`, `GET dashboard/combinacoes-risco` e `GET dashboard/apostas-um-jogo` (depende de T027, T030, T031)
- [X] T033 [P] [US3] Criar `resources/js/components/CombinationModal/index.jsx` e `styles.jsx` no visual de `modals/Combination` do antigo: título com os jogos e opções, lista dos códigos; tocar num código busca `GET /publico/apostas/{codigo}` e abre o `TicketModal` existente; fecha com X, fundo e voltar
- [X] T034 [P] [US3] Criar `resources/js/components/RiskCombinations/index.jsx` e `styles.jsx`: tabela Jogos · Opções · Bilhetes · Valor · Prêmio (barra proporcional ao maior prêmio) · 👁 (abre `CombinationModal`), rolagem horizontal só dentro da seção no celular, prop `chaves_novas` para destacar linhas novas por 60 s (contracts/paginas §6)
- [X] T035 [P] [US3] Criar `resources/js/components/SingleGameBets/index.jsx` e `styles.jsx`: lista posição · Apostador · Prêmio (barra proporcional) · 👁 que abre o `TicketModal` com `GET /publico/apostas/{codigo}` (contracts/paginas §7)
- [X] T036 [US3] Na página `resources/js/pages/Dashboard/index.jsx`, adicionar as seções "COMBINAÇÃO DE RISCO" (com `useSecaoDashboard('combinacoes-risco', { ignorar_cache: true })`) e "APOSTAS POR UM JOGO" na posição do antigo; nenhuma comparação de listas nem aviso nesta tarefa — o carregamento e o ⟳ nunca geram aviso (FR-046) (depende de T028, T032, T034, T035)

### Aviso no WhatsApp do Admin

- [X] T037 [P] [US3] Criar `app/Enums/SituacaoAvisoWhatsapp.php` com os casos `Pendente`, `Enviado`, `Falhou`, `Dispensado` (valores em português) e docblock
- [X] T038 [P] [US3] Criar a migration `database/migrations/2026_10_20_000001_create_apostas_combinacoes_risco_table.php` conforme data-model §2: `id`; `dia` date ("Dia (Brasília) em que a combinação surgiu"); `chave` char(40) ("sha1 da assinatura de jogos e opções"); `jogos` json; `opcoes` json; `codigos` json ("Códigos dos bilhetes da combinação quando surgiu"); `usuarios_ids` json ("Ids dos vendedores dos bilhetes, para filtrar os avisos do painel pela rede"); `quantidade_bilhetes` unsignedInteger; `valor_apostado` decimal(15,2); `premio` decimal(15,2); `situacao_whatsapp` string(20) ("Pendente, Enviado, Falhou ou Dispensado"); `tentativas_whatsapp` unsignedTinyInteger default 0 ("máx. 3"); `enviado_em` timestamp null; `erro_whatsapp` string(255) null; `timestamps()`; `softDeletes()`; `unique(['dia', 'chave'])`; `index(['situacao_whatsapp', 'tentativas_whatsapp'])`; toda coluna com `->comment()`
- [X] T039 [P] [US3] Criar a migration `database/migrations/2026_10_20_000002_add_avisos_whatsapp_configuracoes_table.php` em `configuracoes`: `aviso_combinacao_whatsapp` boolean default false ("Liga o aviso de combinação de risco nova no WhatsApp do Admin"), `telefone_whatsapp_admin` string(15) nullable ("Telefone do WhatsApp do Admin, só dígitos com DDI (ex.: 5582999999999)"), `chave_callmebot` text nullable ("Chave do CallMeBot do telefone do Admin, criptografada"); `down` removendo as três (data-model §3)
- [X] T040 [US3] Criar `app/Models/ApostasCombinacoesRisco.php` com `SoftDeletes`, `fillable` das colunas, casts `dia` → `date`, `jogos`/`opcoes`/`codigos`/`usuarios_ids` → `array`, `valor_apostado`/`premio` → `decimal:2`, `situacao_whatsapp` → `SituacaoAvisoWhatsapp`, `enviado_em` → `datetime` (depende de T037, T038)
- [X] T041 [US3] Em `app/Models/Configuracoes.php`, adicionar `aviso_combinacao_whatsapp`, `telefone_whatsapp_admin` e `chave_callmebot` ao `fillable`; casts `boolean` e `encrypted` (este para `chave_callmebot`); `chave_callmebot` em `$hidden` (criar a propriedade se não existir), como os tokens de `GatewaysPagamento` (depende de T039)
- [X] T042 [P] [US3] Criar `app/Http/Requests/ConfiguracoesAvisosWhatsappRequest.php` (campos `sometimes`): `aviso_combinacao_whatsapp` boolean; `telefone_whatsapp_admin` nulo ou `regex:/^\d{12,15}$/`; `chave_callmebot` nulo ou string `max:64`; validação extra (`after`) que recusa ligar o aviso quando, somando o enviado e o já gravado, faltar telefone ou chave, com a mensagem "Informe o telefone e a chave do CallMeBot para ligar o aviso."; mensagens em português
- [X] T043 [P] [US3] Criar `app/Http/Resources/ConfiguracoesAvisosWhatsappResource.php` com `aviso_combinacao_whatsapp`, `telefone_whatsapp_admin`, `chave_callmebot_definida` (bool) e `chave_callmebot_final` (4 últimos caracteres ou null), nunca a chave inteira (contracts/api §3)
- [X] T044 [US3] Criar `app/Http/Controllers/ConfiguracoesAvisosWhatsappController.php` no padrão de `ConfiguracoesCassinoController` (`permissao_cliente('configuracoes.editar')`, `show` e `update` com `Configuracoes::atual()`; `chave_callmebot` ausente mantém a atual); em `routes/api.php`, `GET` e `PUT configuracoes/avisos-whatsapp` junto das demais rotas de configurações (depende de T041, T042, T043)
- [X] T045 [US3] Criar `app/Services/AvisosWhatsapp.php`: `enviar(string $telefone, string $chave, string $texto): void` com `Http::timeout(30)->get('https://api.callmebot.com/whatsapp.php', ['phone' => ..., 'text' => ..., 'apikey' => ...])`, lançando exceção com mensagem em português quando a resposta falhar; e `texto_combinacoes(Collection $combinacoes): string` no formato de contracts/api §4 (valores em R$ formatados)
- [X] T046 [US3] Criar `app/Console/Commands/AvisarCombinacoesRiscoCommand.php` (`apostas:avisar_combinacoes_risco`): única origem dos avisos (FR-046); chama `CombinacoesRisco::listar(null)` (banca toda, sem cache; o serviço devolve também os ids dos vendedores de cada combinação); grava em `ApostasCombinacoesRisco` as chaves que não existem **nem hoje nem ontem** (Brasília), com `codigos` e `usuarios_ids`, como `Pendente` ou, se o aviso estiver desligado ou faltar telefone/chave, `Dispensado`; reúne todas as `Pendente` com `tentativas_whatsapp < 3` numa única mensagem por `AvisosWhatsapp`; sucesso → `Enviado` + `enviado_em`; falha → `tentativas + 1`, `erro_whatsapp` e `Log::warning`, e `Falhou` na 3ª; nunca lança para o agendador (research R-11, FR-045 a FR-049) (depende de T030, T040, T041, T045)
- [X] T047 [US3] Em `routes/console.php`, agendar `Schedule::command('apostas:avisar_combinacoes_risco')->everyMinute()->withoutOverlapping(2);` com comentário em português (spec 014) junto dos comandos de apostas (depende de T046)

### Aviso no painel (vindo do agendador)

- [X] T048 [US3] Em `app/Http/Controllers/DashboardController.php`, criar a ação `avisos_combinacoes_risco` com `permissao_cliente('dashboard.ver_combinacao_risco', only: [..., 'avisos_combinacoes_risco'])`, sem cache e sem gravar nada: valida `depois_de` (inteiro opcional); sem ele devolve `data: []` e `ultimo_id` (maior id de ontem e hoje); com ele, os registros de `ApostasCombinacoesRisco` de ontem e hoje com `id > depois_de` (todos para o Admin; para Supervisor e Gerente, só os que têm algum id de `AlcanceDashboard::ids_vendedores` em `usuarios_ids`), no formato de contracts/api §2.4a; em `routes/api.php`, `GET dashboard/combinacoes-risco/avisos` registrada **antes** de outras rotas `dashboard/combinacoes-risco/*` (depende de T032, T040)
- [X] T049 [US3] Na página `resources/js/pages/Dashboard/index.jsx`, com a seção de Combinação de risco visível, consultar a cada 60 s (aba visível) `GET /dashboard/combinacoes-risco/avisos?depois_de=<último id visto>`; o último id fica em `localStorage` com a chave `dashboard_ultimo_aviso_combinacao:{usuarios_id}` (`try/catch`, fallback em memória); na primeira consulta sem id, só guardar o `ultimo_id`; para cada aviso, toast do `sweetalert2` "Nova combinação de risco" com jogos e prêmio, som curto e as chaves passadas em `chaves_novas` ao `RiskCombinations` (destaque por 60 s); ícone 🔔 no título liga/desliga o som (guardado em `localStorage`); recarregar ou tocar em ⟳ nunca gera aviso (FR-047, research R-12) (depende de T036, T048)

**Checkpoint**: US3 validada pelo quickstart §3 e §4

---

## Phase 6: User Story 4 — Rankings (Priority: P2)

**Goal**: Vendedores, Jogos e Opções mais apostados da rede

**Independent Test**: quickstart §5 (itens 1 e 2)

- [X] T050 [US4] Em `app/Services/ApostasDashboard.php`, criar `vendedores(Usuarios $usuario)` (top 10 por `SUM(valor)` das apostas Ativa do dia do balcão no alcance, agrupando por `apostas.usuarios_id` com o nome do usuário, inclusive excluídos: `id`, `nome`, `quantidade`, `entradas`), `jogos_mais_apostados(Usuarios $usuario)` (top 10 jogos por apostas distintas com palpite ativo: `confrontos_id`, `confronto` "casa x fora", `quantidade`, `entradas`) e `opcoes_mais_apostadas(Usuarios $usuario)` (top 10 `codigo_cotacao` por palpites ativos: `codigo_cotacao`, `opcao` por `NomesCotacoes::nome`, `quantidade`) (contracts/api §2.6 a §2.8) (depende de T026)
- [X] T051 [US4] Em `app/Http/Controllers/DashboardController.php`, criar as ações `vendedores`, `jogos_mais_apostados` e `opcoes_mais_apostadas` com as permissões `dashboard.ver_vendedores`, `dashboard.ver_jogos_mais_apostados` e `dashboard.ver_opcoes_mais_apostadas`; em `routes/api.php`, `GET dashboard/vendedores`, `GET dashboard/jogos-mais-apostados` e `GET dashboard/opcoes-mais-apostadas` (depende de T027, T050)
- [X] T052 [P] [US4] Criar `resources/js/components/RankingChart/index.jsx` e `styles.jsx`: barras horizontais com `ChartCanvas` (`tipo="horizontal"`) e, abaixo, a lista posição · nome · quantidade · valor (quando houver), como as tabelas do antigo (contracts/paginas §8) (depende de T011)
- [X] T053 [US4] Na página `resources/js/pages/Dashboard/index.jsx`, adicionar as seções "VENDEDORES", "JOGOS MAIS APOSTADOS" e "OPÇÕES MAIS APOSTADAS" com `RankingChart`, na posição do antigo (depende de T049, T051, T052)

**Checkpoint**: US4 validada

---

## Phase 7: User Story 5 — Caixa online (Priority: P2)

**Goal**: caixa das apostas dos clientes no site, da banca toda

**Independent Test**: quickstart §5 (itens 3 e 4)

- [X] T054 [US5] Criar `app/Services/CaixaOnline.php` com `calcular(): array` conforme research R-05 e contracts/api §2.9: apostas Ativa do dia com `clientes_id` não nulo e `forma_pagamento = Saldo` (promoção fora): entradas, em aberto, premiadas, perdidas e saídas (prêmio das Vencedor + valor das Devolvido); `encerramentos` = `SUM(valor)` de `apostas_encerramentos` Registrado ou Creditado com `decidido_em` no dia; `depositos` (Aprovado, `pago_em` no dia) e `saques_pagos` (Pago, `pago_em` no dia); `saldo_liquido` = entradas − saídas − encerramentos; bcmath (depende de T006)
- [X] T055 [US5] Em `app/Http/Controllers/DashboardController.php`, criar a ação `caixa_online` com `permissao_cliente('dashboard.ver_caixa_online', only: ['caixa_online'])`; em `routes/api.php`, `GET dashboard/caixa-online` (depende de T027, T054)
- [X] T056 [US5] Na página `resources/js/pages/Dashboard/index.jsx`, adicionar a seção "CAIXA ONLINE" no fim: `StatCard` de Entradas, Em aberto, Premiadas, Perdidas, Saídas, Encerramentos, Depósitos, Saques pagos e Saldo líquido online (destaque), gráfico de barras Entradas × Saídas × Encerramentos × Saldo e outro Depósitos × Saques (contracts/paginas §9) (depende de T053, T055)

**Checkpoint**: US5 validada

---

## Phase 8: User Story 6 — Seções liberadas por permissão (Priority: P2)

**Goal**: conferir e ajustar o controle de seções por permissão em todas as camadas

**Independent Test**: quickstart §6

- [X] T057 [US6] Em `app/Http/Controllers/DashboardController.php`, conferir que cada uma das 10 ações (9 seções + avisos da combinação) tem o seu `permissao_cliente` em `middleware()` e nenhuma ação ficou sem permissão; em `app/Enums/Funcao.php`, conferir que `pode_usar` recusa ao Vendedor as 5 seções de gestão e ao Gerente a Caixa online mesmo com a permissão direta (FR-011, FR-012)
- [X] T058 [US6] Na página `resources/js/pages/Dashboard/index.jsx`, garantir que cada seção só é montada (e só faz requisição) quando está em `usuario.secoes` e que uma seção que receber 403 (`sem_permissao` do `useSecaoDashboard`) some da tela sem afetar as outras (FR-012, edge case "permissão retirada com a Dashboard aberta")
- [X] T059 [US6] Rodar `php artisan db:seed --class=DashboardSeeder` (`database/seeders/DashboardSeeder.php`) duas vezes no banco local e conferir que não duplica nem retira permissões e que admin, supervisor, gerente e vendedores receberam as do seu nível (FR-013) — sem alterar arquivos se estiver correto

**Checkpoint**: US6 validada

---

## Phase 9: User Story 7 — Dashboard leve, clara e responsiva (Priority: P3)

**Goal**: atualização automática, estados das seções e peso da tela principal

**Independent Test**: quickstart §7

- [X] T060 [US7] Em `resources/js/hooks/useSecaoDashboard.js`, revisar a atualização automática criada no T014 com todas as seções montadas: conferir no Network que, com a aba oculta, nenhuma requisição sai; ao voltar, cada seção atualiza uma vez; sem pedidos sobrepostos; só a Combinação de risco usa `?atualizar=1` (FR-044)
- [X] T061 [US7] Em `resources/js/components/DashboardSection/index.jsx` e `styles.jsx`, revisar os esqueletos no formato de cada tipo de seção (cartões, rosca, tabela, barras) e o estado "Atualizando" sem piscar o conteúdo; em `resources/js/pages/Dashboard/styles.jsx`, conferir a grade responsiva (1 coluna ≤ 900 px, sem rolagem horizontal da página, SC-008) e as cores nos temas claro e escuro
- [X] T062 [US7] Rodar `npm run build` e conferir em `public/build/manifest.json` que o `chart.js` está num pedaço separado, carregado só pela página `Dashboard`, e que o pacote da tela principal não cresceu (SC-007); corrigir o `import()` em `resources/js/components/ChartCanvas/index.jsx` se não estiver

**Checkpoint**: US7 validada

---

## Phase 10: Polish & Cross-Cutting

- [X] T063 Regenerar `docs/postman/wssports_api.postman_collection.json` substituindo o arquivo, com todas as rotas atuais, incluindo as 13 novas (`auth/eu`, as 9 de `dashboard/*` com o parâmetro `atualizar`, `dashboard/combinacoes-risco/avisos` com `depois_de` e `GET`/`PUT configuracoes/avisos-whatsapp`), mantendo as variáveis `base_url` e `token` (Constituição)
- [X] T064 Revisão de legibilidade de todo o código alterado na feature (Princípio V): nomes, duplicação entre `ApuradoDashboard`, `ApostasDashboard` e `CaixaOnline` (extrair consulta base comum só se reduzir repetição sem esconder regra), métodos longos, comentários; registrar em nota no fim de `specs/014-painel-dashboard/tasks.md` o que foi refatorado ou que nada foi necessário
- [ ] T065 Executar todo o [quickstart.md](quickstart.md) (§1 a §8) no ambiente local (para o §7 item 7, usar uma cópia do banco de teste ou apostas geradas à mão até cerca de 5.000 no dia) e registrar em nota no fim de `specs/014-painel-dashboard/tasks.md` o resultado de cada seção

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sem dependências
- **Foundational (Phase 2)**: depende do Setup (T011 usa o `chart.js`); bloqueia todas as histórias
- **US1 (Phase 3)**: depende da Phase 2
- **US2 (Phase 4)**: depende da Phase 2 e da página da US1 (T022)
- **US3 (Phase 5)**: depende da US2 (controller e página); o bloco do agendador e do WhatsApp (T037 a T047) depende só de T030; o aviso no painel (T048, T049) depende do agendador (T040, T046) e da seção (T036) — o painel nunca gera aviso sozinho
- **US4 e US5 (Phases 6 e 7)**: dependem da US2; entre si, só pela ordem das seções na página (T053 → T056)
- **US6 (Phase 8)**: depois das seções existirem (US2 a US5)
- **US7 (Phase 9)**: depois da Phase 2; melhor depois das seções
- **Polish**: depois de tudo

### Within Each Story

- Serviços → ações do controller + rotas → componentes → página
- A página `Dashboard` e o `DashboardController` são arquivos únicos: as tarefas que os alteram são sequenciais

## Parallel Example

```text
# Phase 2, em paralelo:
T005 migration de índices · T006 AlcanceDashboard · T007 CacheDashboard · T008 DashboardRequest
T010 utils/charts.js · T011 ChartCanvas · T012 StatCard · T013 DashboardSection

# US3, em paralelo depois de T030:
T033 CombinationModal · T034 RiskCombinations · T035 SingleGameBets
T037 enum · T038 migration · T039 migration de configurações · T042 request · T043 resource
```

## Implementation Strategy

### MVP

1. Phase 1 + Phase 2
2. US1 (login e painel) → validar quickstart §1
3. US2 (Apurado, Apostas, Situação dos jogos) → validar quickstart §2 — primeira entrega útil

### Incremental

US3 (risco + avisos) → US4 (rankings) → US5 (Caixa online) → US6 (conferência de permissões) →
US7 (atualização automática e acabamento) → Polish. Cada história é validada pelo quickstart antes da
próxima.

## Notes

- Sem tarefas de teste (Constituição).
- Commits só pelo `/speckit-git-commit`.
- Qualquer necessidade de alterar arquivo fora da lista do plan.md: parar e pedir confirmação
  (Princípio IV).

## Notas da implementação (2026-10-20)

**Revisão de legibilidade (T064)**: código da feature revisado em conjunto. Refatorado: tooltip dos
gráficos (`utils/charts.js`) sem a variável redundante; `ApostasDashboard::situacao_jogos` com uma
expressão por situação em vez de repetir parâmetros; somas do `ApuradoDashboard` com parâmetros
(bindings) em vez de valores no texto da SQL. A consulta base de `ApuradoDashboard`, `ApostasDashboard`
e `CaixaOnline` não foi unificada: as três diferem no público (balcão × online) e nas colunas, e um
método comum esconderia a regra de cada seção.

**Ajustes em relação às tarefas** (sem mudar o comportamento da spec):

- O bilhete abre por `GET /publico/apostas/{codigo}` (o mesmo da tela principal); `research.md` e as
  tarefas foram corrigidos. A página guarda o bilhete aberto e passa `ao_ver_bilhete` aos componentes
  `CombinationModal` e `SingleGameBets` (sem busca duplicada em cada componente).
- `utils/session.js` não foi alterado: a função do usuário entra na sessão pelo `atualizar_sessao`
  já existente, dentro de `useSessao.js`.
- Índice de `apostas_combinacoes_risco (situacao_whatsapp, tentativas_whatsapp)` com nome curto
  (`apostas_combinacoes_risco_whatsapp_index`): o nome automático passava do limite do MySQL.
- O Pint reordenou `use` já existentes em `routes/api.php`; a ordem original foi mantida (Princípio
  IV) e só os `use` novos foram incluídos.
- O `chart.js` sai num pedaço separado (`auto-*.js`, ~207 kB, 71 kB gzip) carregado só pela Dashboard;
  o service worker do PWA ainda o inclui no precache em segundo plano (configuração do
  `vite.config.js`, fora do escopo aprovado).

**Validação feita (T065, parcial)**:

- `php -l` e Pint nos arquivos PHP; `npm run build` sem erros.
- Migrations e `DashboardSeeder` (duas vezes, sem duplicar) no banco local; Admin e Supervisor com 9
  seções, Gerente com 8, Vendedor com 4 (quickstart §6).
- HTTP com o servidor local: as 13 rotas novas respondem 200 a quem tem a permissão e 403 a quem não
  tem (Gerente na Caixa online; Vendedor nas 5 de gestão, mesmo com a permissão dada à força); retirar
  `dashboard.ver_combinacao_risco` do Supervisor → 403 e a seção sai de `secoes` (quickstart §6).
- Somas conferidas com as apostas do banco local num período ampliado: 13 apostas = 8 abertas + 3
  premiadas + 2 perdidas; combinação de 2 bilhetes encontrada para o Admin e para a rede do gerente.
- Agendador: 1ª execução registra a combinação (`Dispensado` com o aviso desligado), 2ª não repete;
  rota de avisos devolve o aviso ao Admin e ao gerente da rede e nada a quem é de fora.
- `PUT /configuracoes/avisos-whatsapp`: 422 ao ligar sem telefone/chave; chave nunca devolvida inteira.

**Falta validar manualmente no navegador** (quickstart §1 a §5 e §7): login e destino de cada nível,
gaveta no celular, gráficos e estados das seções, balão/som do aviso, envio real pelo CallMeBot e o
teste de volume (SC-003).

**Correção após teste no navegador (2026-10-20)**: só o "Apurado do dia" aparecia porque o estilo
global (igual ao antigo) trava `html`, `body` e `#app` na altura da tela com `overflow: hidden`, e o
`PanelShell` não tinha área de rolagem própria. O `PanelShell` passou a ocupar a altura da tela com a
barra fixa e o conteúdo rolando (`resources/js/components/PanelShell/styles.jsx`); o estilo global
não foi alterado.

**Ajuste pedido pelo responsável (2026-10-20)**: o modal dos bilhetes da combinação foi redesenhado
(`resources/js/components/CombinationModal/`): resumo do risco (bilhetes, apostado e prêmio em risco em
destaque), palpites em comum com a opção em etiqueta e um cartão por bilhete com apostador, vendedor,
valor, prêmio e "Ver bilhete" — como a lista CLIENTE/VALOR/PRÊMIO do `modals/Combination` antigo. Para
isso, `CombinacoesRisco` passou a devolver `bilhetes` (contracts/api.md §2.4), montado com
`JSON_ARRAYAGG` (MySQL 8.4 no local e no servidor).
