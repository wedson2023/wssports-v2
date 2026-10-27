---

description: "Lista de tarefas da spec 023 — Painel: Confrontos"
---

# Tasks: Painel — Confrontos (lista de jogos, limite, situação, mais apostados, pendentes, campeonatos e cadastro manual)

**Input**: Design documents from `specs/023-painel-confrontos/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md),
[data-model.md](data-model.md), [contracts/api.md](contracts/api.md),
[contracts/paginas.md](contracts/paginas.md), [quickstart.md](quickstart.md)

**Tests**: nenhum teste automatizado (constituição). A validação é manual pelo
[quickstart.md](quickstart.md).

**Organization**: tarefas agrupadas por história da spec (US1 a US9). Cada tarefa cita os arquivos;
arquivos existentes só os listados no plan.md (Princípio IV — lista aprovada pelo responsável em
2026-10-27, "pode fazer as alterações"). Ao concluir cada tarefa, revisão de legibilidade
(Princípio V). Somas em `DECIMAL` no SQL e devolvidas com 2 casas (texto), consultas pelo query
builder com parâmetros e curingas escapados (`addcslashes(..., '%_\\')`), comentários e mensagens em
português, nomes em snake_case. Permissões sempre por `Funcao::usuario_pode`/`permissao_cliente`.
Modais com `ModalHeader`, corpo em grade de 2 colunas no computador e 1 no celular, seções com
título, rodapé fixo com "Cancelar" e a ação principal à direita, e `select` no lugar de
`ToggleSwitch` (R-19). Rotas novas `confrontos/*` e `campeonatos/paises` **antes** dos
`apiResource` em `routes/api.php`.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: pode rodar em paralelo (arquivos diferentes, sem depender de tarefa incompleta)
- **[Story]**: história da spec (US1 a US9)

---

## Phase 1: Setup

**Purpose**: banco (data-model §1).

- [ ] T001 [P] Criar `database/migrations/2026_10_27_000007_add_indice_data_inicio_confrontos_table.php` (data-model §1): `$table->index('data_inicio')` em `confrontos`, com comentário no topo "Índice de data_inicio para a lista do painel por dia (spec 023)"; `down()` remove o índice
- [ ] T002 [P] Criar `database/migrations/2026_10_27_000008_restringir_lancar_resultado_admin.php` (data-model §1, R-12) no padrão de `2026_10_24_000003_criar_permissao_restaurar_apostas.php`: limpa o cache de permissões, `Usuarios::withTrashed()->with(['roles', 'permissions'])` e, para todo usuário com `funcao() !== Funcao::Admin` que tenha `confrontos.lancar_resultado`, `revokePermissionTo('confrontos.lancar_resultado')`; limpa o cache de novo; `down()` devolve a permissão aos Supervisores

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: filtros, serviço da lista, campos comuns do resource, alcance das apostas, página e menu.

**⚠️ CRITICAL**: nenhuma história começa antes desta fase.

- [ ] T003 Criar `app/Http/Requests/FiltrosConfrontosPainelRequest.php` (data-model §4): `authorize()` true (a permissão é do middleware); regras dos filtros atuais do `ConfrontosController@index` (`busca` `nullable|string|max:150`, `campeonatos_id` `nullable|integer`, `ativo` e `manual` `nullable|boolean`, `esporte` `nullable|string|max:50`, `situacao` `Rule::enum(SituacaoConfronto::class)`, `fuso_horario` `regex:/^[+-](0\d|1[0-4]):[0-5]\d$/`, `por_pagina` `integer|between:1,100`) com as mesmas mensagens, mais `dia` `nullable|in:ontem,hoje,amanha,depois_de_amanha` ("O dia deve ser ontem, hoje, amanha ou depois_de_amanha."), `data` `nullable|date_format:Y-m-d` + closure "entre hoje −30 e hoje +2 no fuso_horario (padrão -03:00)" ("A data deve estar entre os últimos 30 dias e depois de amanhã.") e `tipo` `nullable|in:todos,pre_jogo,ao_vivo` ("O tipo deve ser todos, pre_jogo ou ao_vivo."); método `filtros(): array` com os valores validados
- [ ] T004 Em `app/Services/ListagemApostas.php`: `aplicar_alcance(Builder $consulta, Usuarios $usuario, ?int $usuarios_id)` passa de `private` a `public`, sem mudar a regra; atualizar o docblock dizendo que também é usado pela tela Confrontos (spec 023, R-09)
- [ ] T005 Criar `app/Services/ListagemConfrontosPainel.php` (R-02 a R-06) com docblock em português: `consulta(Usuarios $usuario, array $filtros): Builder` sobre `Confrontos::query()->with('campeonato')` — `busca` em `time_casa`, `time_fora` e `campeonato.nome` dentro de um `where` agrupado (R-05); `campeonatos_id`, `ativo`, `manual`, `esporte` (`mb_strtoupper`), `situacao`; dia pelo `data` (vence) ou `dia` (`ontem` −1, `hoje` 0, `amanha` +1, `depois_de_amanha` +2) como `whereBetween('data_inicio', [início, fim do dia em UTC])` no `fuso_horario` (padrão `-03:00`, como o `dia_em_utc` atual); `tipo` `ao_vivo`/`pre_jogo` por `whereHas`/`whereDoesntHave('ao_vivo', ...)` com a regra de ao vivo agora (data-model §3: `situacao` em `RegrasExibicao::situacoes_ao_vivo()` e `ultima_atualizacao_em >= now()->subMinutes(Configuracoes::atual()->minutos_permanencia_ao_vivo)`); ordem `data_inicio`, `time_casa`. `com_detalhes(Builder, Usuarios): Builder` acrescenta `with` do ao vivo agora (`ao_vivo` restrito pela mesma regra), `with('resultado')`, `withExists('nao_permitidos as nao_permitido')` (regra atual de `AlcanceHierarquia::nao_permitidos_do_usuario`), os ids próprios (`nao_permitido_proprio_pre_jogo_id` e `nao_permitido_proprio_ao_vivo_id`: registro com `alvo = Vendedores` e `usuarios_id = $usuario->id`, por subconsulta `select id ... limit 1`), para Admin e Supervisor (`AlcanceHierarquia::e_gestor_geral`), `redes_desativadas` (`COUNT(DISTINCT usuarios_id)` de `confrontos_nao_permitidos` com `alvo = Vendedores`) e `apostas_em_aberto` (R-21: subconsulta com a quantidade de apostas `Ativa` distintas com palpite `Ativo` e `resultado = Aguardando` no jogo, no alcance de `ListagemApostas::aplicar_alcance` para o usuário)
- [ ] T006 Em `app/Http/Resources/ConfrontosResource.php` (contracts/api §1.1): acrescentar `campeonato.ativo`, `limite_valor_apostado` (texto, 2 casas), `apostas_em_aberto` (inteiro, quando o atributo veio), `ao_vivo` (`whenLoaded`, `{ id, minuto, cronometro, situacao, placar_casa, placar_fora, limite_valor_apostado }` ou `null`), `resultado` (`whenLoaded`, `{ placar_casa, placar_fora }` ou `null`), `restricoes` (`{ nao_permitido_proprio: { pre_jogo_id, ao_vivo_id }, redes_desativadas }`, `redes_desativadas` `null` quando o atributo não veio) e `acoes` (método privado `acoes(Usuarios)`: `alterar_situacao` = `confrontos.alterar_situacao`; `desativar_rede` = não gestor geral e `confrontos_nao_permitidos.gerenciar`; `alterar_limite` = `confrontos.alterar_limite`; `lancar_resultado` = `confrontos.lancar_resultado` e `data_inicio <= now()` (R-21); `alterar_cotacoes` = `porcentagens_confrontos.editar`; `editar` = `manual` e `confrontos.editar`; `excluir` = `manual` e `confrontos.excluir`, todas por `Funcao::usuario_pode($request->user(), ...)`); manter os campos atuais
- [ ] T007 Em `app/Http/Controllers/ConfrontosController.php`: `index(FiltrosConfrontosPainelRequest $request, ListagemConfrontosPainel $listagem)` passa a usar `$listagem->com_detalhes($listagem->consulta($request->user(), $request->filtros()), $request->user())->paginate($filtros['por_pagina'] ?? 20)->withQueryString()`; remover `dia_em_utc` e a validação inline que foram para o serviço e o request; atualizar o docblock da classe ("... e a lista da tela Confrontos, spec 023")
- [ ] T008 Registrar `GET painel/confrontos` em `routes/web.php` (nome `painel.confrontos`, comentário "confrontos do painel (spec 023)", junto das demais rotas `painel/*`) e a ação `confrontos()` em `app/Http/Controllers/PainelController.php` (Inertia `PanelMatches`, docblock "Tela "Confrontos" (spec 023); aba, busca e filtros vêm na query string.")
- [ ] T009 [P] Em `resources/js/components/PanelShell/index.jsx`: item "Confrontos" (chave `confrontos`, rótulo `Confrontos`, ícone `sports_soccer`, `href: '/painel/confrontos'`) logo antes de "Cotações", visível com `confrontos.listar` (contracts/paginas.md §1)
- [ ] T010 Criar `resources/js/hooks/useConfrontosPainel.js` (R-01, no padrão de `useAfiliadosPainel`): filtros no endereço (`aba` = `jogos` | `mais_apostados` | `pendentes` | `campeonatos`, `busca`, `tipo` (`todos` padrão), `dia` (`hoje` padrão) ou `data`, `campeonatos_id` + `campeonato` (nome), `situacao` (`ativos` | `desativados`), `manual`, `pais`, `favorito`, `page`) com `router.get(..., { preserveState: true, replace: true })`; `mudar(parcial)` volta à página 1; `trocar_aba(aba)`; `parametros_api(filtros)` (situação → `ativo`, `fuso_horario` do navegador); carrega a lista da aba (`GET /confrontos`, `/confrontos/mais-apostados`, `/confrontos/pendentes` ou `/campeonatos`, padrão 20) e o resumo (`GET /confrontos/resumo`, fora da aba Campeonatos) com o token do `usePainel`; `recarregar({ silencioso })` sem perder a posição; exporta `abas_confrontos`, `atalhos_dia` e `tipos_jogo`
- [ ] T011 Criar a página `resources/js/pages/PanelMatches/index.jsx` e `styles.jsx` (contracts/paginas.md §1): `PanelShell` título "Confrontos", barra "Confrontos — total N", busca com atraso de 400 ms, botões Filtros/Atualizar, `PanelTabs` (Jogos `sports_soccer`, Mais apostados `local_fire_department`, Pendentes `pending_actions` só com `confrontos.lancar_resultado`, Campeonatos `emoji_events` só com `campeonatos.listar`), áreas com esqueleto, vazio e erro com "Tentar novamente" (FR-036); sem `confrontos.listar`, volta para `/painel`

**Checkpoint**: a página abre pelo menu; `GET /api/confrontos?dia=ontem&tipo=ao_vivo` responde com os campos novos; migrations rodam.

---

## Phase 3: User Story 1 - Consultar os confrontos (Priority: P1) 🎯 MVP

**Goal**: lista dos jogos com cartões, dia, tipo, busca e filtros (FR-004 a FR-008).

**Independent Test**: quickstart §1.

- [ ] T012 [US1] Em `app/Services/ListagemConfrontosPainel.php`: `resumo(Usuarios $usuario, array $filtros): array` (contracts/api §2.1, R-08) — `jogos` (count da `consulta` sem `situacao`), `ao_vivo` (count de jogos ao vivo agora, sem o filtro de dia), `desativados` (count com `ativo = false`; para quem não é gestor geral, soma os que têm `nao_permitido_proprio_pre_jogo_id`), `pendentes` (`null` sem `Funcao::usuario_pode($usuario, 'confrontos.lancar_resultado')`; senão o total de `ExposicaoConfrontos::pendentes`, T031 — até lá, `null`)
- [ ] T013 [US1] Em `app/Http/Controllers/ConfrontosController.php`: ação `resumo(FiltrosConfrontosPainelRequest, ListagemConfrontosPainel)` → `response()->json(['data' => ...])`; incluir `resumo` no middleware de `confrontos.listar`; em `routes/api.php`, `Route::get('confrontos/resumo', ...)` antes do `apiResource('confrontos')`
- [ ] T014 [P] [US1] Criar `resources/js/components/MatchesFilters/index.jsx` e `styles.jsx` (contracts/paginas.md §1 "Filtros"): painel com Dia (atalhos Ontem, Hoje, Amanhã, Depois de amanhã e "Escolher data" com `min` hoje −30 e `max` hoje +2, erro no campo fora da faixa), Campeonato (seleção com busca em `GET /campeonatos?busca=`, guarda id e nome), Situação (`select` Todos, Ativos, Desativados), Só manuais (`select` Não/Sim); na aba Campeonatos: País (`GET /campeonatos/paises`), Situação, Só favoritos, Só manuais; etiquetas removíveis pelo `ActiveFilters`
- [ ] T015 [P] [US1] Criar `resources/js/components/MatchesTable/index.jsx` e `styles.jsx` (contracts/paginas.md §1 "Colunas — Jogos"): colunas Jogo (casa x fora, selo "Ao vivo 63' 1–0" e marca "Manual"), Campeonato (país), Início (`dd/mm hh:mm` no fuso do navegador), ID, Situação (Ativo/Desativado, "Desativado para a minha rede" quando há `nao_permitido_proprio`, "Campeonato desativado", "Desativado em N redes" para gestor geral, Encerrado com o placar), Limite (R$) e o menu ⋮ (`ActionsMenu`) montado por `acoes`; prop `ao_acao(chave, jogo)`; tocar na linha chama `ao_abrir(jogo)`; no celular, cartões com casa x fora, início e situação e o resto ao expandir
- [ ] T016 [US1] Em `resources/js/pages/PanelMatches/index.jsx`: aba Jogos com `StatCard` Jogos · Ao vivo agora · Desativados · Pendentes de resultado (só quando `pendentes !== null`), chips de tipo Todos/Pré-jogo/Ao vivo, `MatchesFilters`, `MatchesTable`, `Pagination`; o dia sempre enviado à API

**Checkpoint**: US1 completa (quickstart §1).

---

## Phase 4: User Story 2 - Ativar e desativar o jogo (Priority: P1)

**Goal**: desativar para todos e para a rede (FR-010 a FR-012).

**Independent Test**: quickstart §2.

- [ ] T017 [US2] Em `resources/js/pages/PanelMatches/index.jsx`: ações `desativar`/`ativar` (gestor geral) pelo `ConfirmModal` → `PATCH /confrontos/{id}/situacao` `{ ativo }`; `desativar_rede`/`ativar_rede` (demais) → `POST /confrontos-nao-permitidos` `{ confrontos_id, alvo: 'Vendedores', usuarios_id: logado.id }` e, com `confrontos_ao_vivo_nao_permitidos.gerenciar`, `POST /confrontos-ao-vivo-nao-permitidos` igual; reativar → `DELETE /confrontos-nao-permitidos/{pre_jogo_id}` e `DELETE /confrontos-ao-vivo-nao-permitidos/{ao_vivo_id}` (os que existirem); a confirmação avisa "As apostas já feitas continuam valendo; o jogo só deixa de receber apostas novas." só quando `apostas_em_aberto > 0` (FR-012); retorno imediato (botão com carregando, `sucesso`/mensagem do servidor) e `recarregar()` da lista e do resumo (FR-037)

**Checkpoint**: US2 completa (quickstart §2).

---

## Phase 5: User Story 3 - Alterar o limite de valor apostado (Priority: P1)

**Goal**: modal de limite do pré-jogo e do ao vivo (FR-013).

**Independent Test**: quickstart §3.

- [ ] T018 [P] [US3] Criar `resources/js/components/MatchLimitModal/index.jsx` e `styles.jsx` (contracts/paginas.md §2): `ModalHeader` (ícone `price_check`, "Alterar limite", casa x fora); seções Pré-jogo (limite em `DecimalField`, R$) e Ao vivo (só quando `jogo.ao_vivo`); Salvar envia `PATCH /confrontos/{id}/limite` `{ limite_valor_apostado }` e, se o do ao vivo mudou, `PATCH /confrontos-ao-vivo/{ao_vivo.id}/limite`; erros do servidor no campo ("maior que zero, duas casas" vem da `LimiteConfrontoRequest`); o modal não fecha com erro; `ao_salvo(jogo)`
- [ ] T019 [US3] Em `resources/js/pages/PanelMatches/index.jsx`: ação `alterar_limite` abre o `MatchLimitModal`; ao salvar, atualiza a linha e a gaveta sem recarregar a página

**Checkpoint**: US3 completa (quickstart §3).

---

## Phase 6: User Story 4 - Ver o confronto e os mais apostados (Priority: P1)

**Goal**: gaveta com Dados, Mais apostados, Apostas e Resultado (FR-014 a FR-018).

**Independent Test**: quickstart §4 (gaveta).

- [ ] T020 [US4] Criar `app/Services/ExposicaoConfrontos.php` (R-09 a R-11) com docblock em português: base `palpites_da_rede(Usuarios $usuario): Builder` = `apostas_palpites` `JOIN apostas` com `apostas.situacao = Ativa`, palpite `situacao = Ativo`, `whereNull` nos `deleted_at`, e o alcance por `ListagemApostas::aplicar_alcance($consulta, $usuario, null)`; subconsulta `palpites_por_aposta` (COUNT de palpites `Ativo` por `apostas_id`) para separar simples (=1) e múltiplas (>1); `ranking(Confrontos, Usuarios): Collection` agrupado por `codigo_cotacao` + `confrontos_jogadores_id` (com `confrontos_jogadores.nome`), colunas `quantidade`, `simples` e `multiplas` (`apostas`, `valor` = `SUM(apostas.valor)`, `retorno` = `SUM(apostas.premio)`, a múltipla inteira em cada jogo — FR-016), ordem `quantidade` desc; `apostas(Confrontos, Usuarios, int $por_pagina): LengthAwarePaginator` com `apostas.codigo`, `apostas.created_at`, vendedor (`usuarios.nome`) ou cliente (`clientes.nome`), `palpites`, `codigo_cotacao`, jogador, `valor`, `premio`, `apostas.resultado`, ordem `created_at` desc; somas em `DECIMAL` devolvidas com `bcadd(..., '0', 2)`
- [ ] T021 [US4] Criar `app/Http/Resources/ApostasConfrontoResource.php` (contracts/api §2.5) e `app/Http/Controllers/ConfrontosApostasController.php` com docblock, `HasMiddleware` + `GarantirPermissaoCliente` (`confrontos.listar` em `ranking` e `apostas`): `ranking(Request, Confrontos)` → `{ data: [...] }` (contracts/api §2.4) e `apostas(Request, Confrontos)` (`por_pagina` `integer|between:1,100`, padrão 30) → `ApostasConfrontoResource::collection`; em `routes/api.php`, `GET confrontos/{confronto}/mais-apostados` e `GET confrontos/{confronto}/apostas` com `->missing($confronto_nao_encontrado)`
- [ ] T022 [US4] Em `app/Http/Controllers/ConfrontosController.php` `show` e `app/Http/Resources/ConfrontosResource.php` (contracts/api §1.2, R-13): `show` usa `ListagemConfrontosPainel::com_detalhes` + `with(['campeonato', 'jogadores', 'resultado', 'gols'])`; o resource ganha, só quando `resultado` e `gols` foram carregados no show, o resultado completo (placar, tempos, escanteios, `gols` `{ ordem, nome, minuto, tempo_jogo }` e `origem` = `origem` da `operacoes_apuracao` mais recente com o `confrontos_id`, ou "Carga de resultados"/"Lançamento manual" pelo `manual`), `cotacoes_site` (`odd1`, `odd2`, `odd3` por `CalculoCotacoes::ajustar(Publico::visitante(), CalculoCotacoes::PRE_JOGO, ...)`, `null` para código ausente) e `restricoes.nao_permitido_todos` (há registro com `alvo = Todos`)
- [ ] T023 [P] [US4] Criar `resources/js/components/MatchPicksRanking/index.jsx` e `styles.jsx` (contracts/paginas.md §3): tabela opção (nome por `useNomesCotacoes` + nome do jogador quando houver, como no cupom), total, Simples (qtd · valor · retorno) e Múltiplas (qtd · valor · retorno), legenda "Múltiplas contam inteiras em cada jogo."; vazio "Nenhuma aposta neste jogo."
- [ ] T024 [P] [US4] Criar `resources/js/components/MatchBetsList/index.jsx` e `styles.jsx` (contracts/paginas.md §3): código, data, vendedor ou cliente, opção, "Simples" ou "Múltipla (N jogos)", valor, retorno e resultado; "Abrir aposta" (com `apostas.listar`) → `/painel/apostas?busca=CODIGO`; `Pagination` (30)
- [ ] T025 [US4] Criar `resources/js/components/MatchDrawer/index.jsx` e `styles.jsx` (contracts/paginas.md §3, no padrão do `AffiliateDrawer`): tela cheia no celular; topo casa x fora, campeonato, início, situação e selo Ao vivo; abas Dados (`GET /confrontos/{id}`: ID, campeonato e país, esporte, início, origem, situação, desativações, limites, cotações do site), Mais apostados (`MatchPicksRanking`), Apostas (`MatchBetsList`) e Resultado (placar, tempos, escanteios, gols, origem; "Sem resultado"; botão Lançar/Corrigir resultado só com `acoes.lancar_resultado`, que chama `ao_lancar_resultado`); cada aba carregada ao abrir, com esqueleto, vazio e erro com "Tentar novamente"; prop `aba_inicial`
- [ ] T026 [US4] Em `resources/js/pages/PanelMatches/index.jsx`: tocar na linha ou "Ver detalhe" abre o `MatchDrawer`; a gaveta não fecha com a atualização da lista
- [ ] T027 [P] [US4] Em `app/Http/Controllers/CotacoesPainelController.php` `nomes`: aceitar também `confrontos.listar` em `exigir(...)` (R-10), com comentário "a tela Confrontos (spec 023) mostra os nomes das opções"

**Checkpoint**: US4 completa (quickstart §4, gaveta).

---

## Phase 7: User Story 5 - Jogos pendentes de resultado e placar manual (Priority: P1)

**Goal**: aba Pendentes e lançamento do placar como o antigo, só pelo Admin (FR-003, FR-022, FR-023).

**Independent Test**: quickstart §5.

- [ ] T028 [US5] Em `app/Enums/Funcao.php` `pode_usar`: `confrontos.lancar_resultado` só para `self::Admin` (junto da regra de `gateway_pagamento.vincular`, com comentário "lançar resultado vale para todas as redes e segue o antigo: só o Admin (spec 023)"); atualizar o comentário de `PERMISSOES_CONFRONTOS` ("resultado de jogo manual (spec 009): só Admin, spec 023") e o docblock de `pode_usar`
- [ ] T029 [US5] Em `app/Http/Requests/ConfrontosResultadosRequest.php` (data-model §4): `gols_primeiro_tempo_casa`, `gols_primeiro_tempo_fora`, `gols_segundo_tempo_casa`, `gols_segundo_tempo_fora`, `escanteios_casa` e `escanteios_fora` passam a `['required', 'integer', 'between:0,99']`; `after()` (ou `withValidator`) recusa quando `placar_casa + placar_fora` ≠ soma dos quatro campos dos tempos, com o erro em `placar_casa`: "A soma dos gols dos times não bate com os gols dos tempos."; mensagens `required` dos campos novos no padrão das atuais
- [ ] T030 [US5] Em `app/Http/Controllers/ConfrontosResultadosController.php`: remover a recusa de jogos do provedor ("O resultado de jogos do provedor vem da carga automática."); recusar jogo que não começou (`data_inicio > now()`) com `ValidationException::withMessages(['confronto' => 'O resultado só pode ser lançado depois do início do jogo.'])` (R-21); atualizar o docblock ("qualquer jogo, como o antigo; um resultado diferente da carga do provedor substitui o lançado — spec 023")
- [ ] T031 [US5] Em `app/Services/ExposicaoConfrontos.php`: `pendentes(Usuarios $usuario, ?string $busca): Builder` (R-12) — confrontos não excluídos, `ativo`, `situacao = Aguardando`, `data_inicio` entre `now()->subDays(7)` e `now()->subHours(2)`, com palpite `Ativo` + `resultado = Aguardando` de aposta `Ativa` na rede (`palpites_da_rede`); colunas `atraso_minutos` (`TIMESTAMPDIFF(MINUTE, data_inicio, NOW())`), `apostas` (COUNT DISTINCT `apostas_id`) e `valor_em_aberto` (`SUM(apostas.valor)` por aposta distinta); `busca` em times e campeonato; ordem `atraso_minutos` desc
- [ ] T032 [US5] Em `app/Http/Controllers/ConfrontosApostasController.php`: ação `pendentes(Request)` (`busca` `nullable|string|max:150`, `por_pagina`) com `confrontos.lancar_resultado` → paginada (contracts/api §2.3); em `routes/api.php`, `GET confrontos/pendentes` antes do `apiResource('confrontos')`; em `ListagemConfrontosPainel::resumo`, `pendentes` passa a usar o total de `pendentes()` (T012)
- [ ] T033 [P] [US5] Criar `resources/js/components/PendingMatchesTable/index.jsx` e `styles.jsx` (contracts/paginas.md §1 "Colunas — Pendentes"): Jogo (e "Manual"), Campeonato, Início, Atraso (h min), Apostas, Em aberto (R$), menu ⋮ Lançar resultado · Ver detalhe; cartões no celular
- [ ] T034 [P] [US5] Criar `resources/js/components/MatchResultModal/index.jsx` e `styles.jsx` (contracts/paginas.md §2): `ModalHeader` (ícone `scoreboard`, "Lançar resultado"/"Corrigir resultado", casa x fora); seções Placar final, 1º tempo, 2º tempo e Escanteios (casa e fora, inteiros 0 a 99, todos obrigatórios) e Gols (lista jogador, minuto, tempo; "Adicionar gol" e remover); na correção, preenchido pelo `resultado` do jogo; Salvar → `PUT /confrontos/{id}/resultado`; erro da soma em `placar_casa` e demais erros do servidor no campo; o modal não fecha com erro; `ao_salvo()`
- [ ] T035 [US5] Em `resources/js/pages/PanelMatches/index.jsx`: aba Pendentes com os mesmos cartões (`StatCard` do resumo), `PendingMatchesTable` e `Pagination`; ação `lancar_resultado` (Pendentes, Jogos e gaveta) abre o `MatchResultModal`; ao salvar, recarrega a lista, o resumo e a gaveta aberta

**Checkpoint**: US5 completa (quickstart §5).

---

## Phase 8: User Story 6 - Alterar as cotações do jogo (Priority: P2)

**Goal**: atalho para a tela Cotações com o editor aberto (FR-024).

**Independent Test**: quickstart §6.

- [ ] T036 [US6] Em `resources/js/pages/PanelQuotes/index.jsx` (R-17): ler da URL `confrontos_id` + `nome` (com `aba=jogos`) ou `campeonatos_id` + `nome` (com `aba=campeonatos`) e, depois de carregar o usuário, abrir o `QuoteEditorModal` por `definir_editando({ camada: 'confrontos' | 'campeonatos', item: { id, nome } })`, como já é feito a partir de Regras ativas; ao fechar o modal, remover esses parâmetros da URL (mantém `aba`) por `window.history.replaceState`
- [ ] T037 [US6] Em `resources/js/pages/PanelMatches/index.jsx`: ação `alterar_cotacoes` → `router.visit('/painel/cotacoes?aba=jogos&confrontos_id=ID&nome=CASA x FORA')` (com `encodeURIComponent`)

**Checkpoint**: US6 completa (quickstart §6).

---

## Phase 9: User Story 7 - Jogos mais apostados (Priority: P2)

**Goal**: aba Mais apostados (FR-019 a FR-021).

**Independent Test**: quickstart §4 (aba Mais apostados).

- [ ] T038 [US7] Em `app/Services/ExposicaoConfrontos.php`: `mais_apostados(Usuarios $usuario, Builder $jogos, int $por_pagina): LengthAwarePaginator` (R-10) — sobre a `consulta` + `com_detalhes` de `ListagemConfrontosPainel` (mesmos filtros e os mesmos campos da lista, para o resource trazer `acoes`), `JOIN` na agregação de `palpites_da_rede` por `confrontos_id` (só jogos com aposta), colunas `quantidade`, `simples` e `multiplas` (`apostas`, `valor`, `retorno`, mesma regra do `ranking`), ordem `quantidade` desc e `data_inicio`; a resposta usa o `ConfrontosResource` com os campos de exposição acrescentados (contracts/api §2.2)
- [ ] T039 [US7] Em `app/Http/Controllers/ConfrontosApostasController.php`: ação `mais_apostados(FiltrosConfrontosPainelRequest, ListagemConfrontosPainel, ExposicaoConfrontos)` com `confrontos.listar` → paginada (contracts/api §2.2); em `routes/api.php`, `GET confrontos/mais-apostados` antes do `apiResource('confrontos')`
- [ ] T040 [P] [US7] Criar `resources/js/components/MostBetMatchesTable/index.jsx` e `styles.jsx` (contracts/paginas.md §1 "Colunas — Mais apostados"): Jogo, Campeonato, Início, Apostas (total), Simples (qtd · valor · retorno), Múltiplas (qtd · valor · retorno), legenda "Múltiplas contam inteiras em cada jogo.", menu ⋮ com as mesmas ações da aba Jogos, pelo `acoes` do item (FR-020); cartões no celular
- [ ] T041 [US7] Em `resources/js/pages/PanelMatches/index.jsx`: aba Mais apostados com os mesmos chips, filtros e cartões da aba Jogos, `MostBetMatchesTable` e `Pagination`; tocar na linha abre o `MatchDrawer` com `aba_inicial="mais_apostados"`

**Checkpoint**: US7 completa.

---

## Phase 10: User Story 8 - Gerenciar os campeonatos (Priority: P2)

**Goal**: aba Campeonatos (FR-025 a FR-028).

**Independent Test**: quickstart §7.

- [ ] T042 [US8] Em `app/Http/Controllers/CampeonatosController.php` `index` (R-14): `withCount(['confrontos as quantidade_jogos' => ativos, Aguardando, data_inicio > now()])` (relação `Campeonatos::confrontos()`), `withCount('confrontos as total_jogos')` (todos os não excluídos, para o aviso da exclusão), ordem `favorito desc, nome`, `nao_permitido_proprio_id` (subconsulta em `campeonatos_nao_permitidos` com `alvo = Vendedores` e `usuarios_id` = quem consulta) e, para gestor geral, `redes_desativadas`; ação nova `paises(Request)` → `{ data: [países distintos, ordenados] }` com `campeonatos.listar`; em `routes/api.php`, `GET campeonatos/paises` antes do `apiResource('campeonatos')`
- [ ] T043 [US8] Em `app/Http/Resources/CampeonatosResource.php` (contracts/api §1.5): `quantidade_jogos`, `total_jogos`, `restricoes` (`{ nao_permitido_proprio_id, redes_desativadas }`) e `acoes` (`alterar_situacao`, `desativar_rede` = não gestor geral e `campeonatos_nao_permitidos.gerenciar`, `favoritar`, `alterar_cotacoes` = `porcentagens_campeonatos.editar`, `editar`/`excluir`/`adicionar_jogo` só manuais com `campeonatos.editar`/`campeonatos.excluir`/`confrontos.cadastrar`), por `Funcao::usuario_pode`
- [ ] T044 [P] [US8] Criar `resources/js/components/ChampionshipsTable/index.jsx` e `styles.jsx` (contracts/paginas.md §1 "Colunas — Campeonatos"): Campeonato (bandeira, nome, "Manual"), País, Jogos disponíveis, Situação ("Desativado para a minha rede", "Desativado em N redes"), Favorito (estrela), menu ⋮ por `acoes`; cartões no celular (nome, jogos e situação)
- [ ] T045 [US8] Em `resources/js/pages/PanelMatches/index.jsx`: aba Campeonatos (sem cartões) com `ChampionshipsTable`, filtros da aba e `Pagination`; ações Ver jogos (aba Jogos com `campeonatos_id` + nome e dia Hoje), Alterar cotações (`/painel/cotacoes?aba=campeonatos&campeonatos_id=ID&nome=NOME`), Favoritar/Desfavoritar (`PATCH /campeonatos/{id}/favorito`), Desativar/Ativar (`PATCH /campeonatos/{id}/situacao`) ou para a rede (`POST /campeonatos-nao-permitidos` `{ campeonatos_id, alvo: 'Vendedores', usuarios_id }` / `DELETE /campeonatos-nao-permitidos/{id}`), todas com `ConfirmModal` e retorno imediato

**Checkpoint**: US8 completa (quickstart §7).

---

## Phase 11: User Story 9 - Cadastrar campeonatos e jogos manuais (Priority: P2)

**Goal**: cadastro, edição e exclusão manuais (FR-029 a FR-032).

**Independent Test**: quickstart §8.

- [ ] T046 [US9] Em `app/Http/Controllers/ConfrontosController.php`: `destroy` recusa (R-16) quando existir palpite `Ativo` de aposta viva (`situacao` em `Ativa`, `Pendente` ou `Em análise`; `Cancelada`, `Recusada` e `Expirada` não contam) no jogo — `ValidationException::withMessages(['confronto' => "Este jogo tem {N} apostas. Cancele o jogo em vez de excluir."])` (singular "1 aposta" quando N = 1); ação nova `esportes()` → `{ data: [esportes distintos de confrontos, ordenados] }` com `confrontos.listar`; em `routes/api.php`, `GET confrontos/esportes` antes do `apiResource('confrontos')`
- [ ] T047 [US9] Em `app/Http/Controllers/CampeonatosController.php` `destroy`: antes da transação, recusa quando algum confronto do campeonato cair na regra de T046 — `['campeonato' => 'Este campeonato tem jogos com apostas.']`
- [ ] T048 [P] [US9] Criar `resources/js/components/ChampionshipFormModal/index.jsx` e `styles.jsx` (contracts/paginas.md §2): `ModalHeader` (ícone `emoji_events`, "Cadastrar campeonato"/"Editar campeonato"); seção Campeonato com nome (até 150), país (`select` de `GET /campeonatos/paises` + "Outro país" que mostra um campo de texto, até 100) e bandeira (opcional, até 255); `POST /campeonatos` / `PUT /campeonatos/{id}`; erros no campo (nome repetido vem do servidor)
- [ ] T049 [P] [US9] Criar `resources/js/components/MatchFormModal/index.jsx` e `styles.jsx` (contracts/paginas.md §2, R-15): `ModalHeader` (ícone `sports_soccer`, "Cadastrar jogo"/"Editar jogo"); seções Jogo (campeonato manual — `select` de `GET /campeonatos?manual=1&por_pagina=100` —, esporte — `select` de `GET /confrontos/esportes` —, data e hora `Y-m-d H:i` no fuso do navegador enviado em `fuso_horario`), Times (casa e fora até 150, escudos opcionais até 255), Cotações (Casa `odd1`, Empate `odd2`, Fora `odd3` e "Adicionar cotação" com os códigos de `useNomesCotacoes` agrupados por `grupos_mercado` de `utils/quotes.js`; cada valor ≥ 1,00 com 2 casas) e, na edição, Situação (`select` Aguardando, Adiado, Cancelado); `POST /confrontos` / `PUT /confrontos/{id}`; erros no campo (inclusive `cotacoes`); prop `campeonato_inicial` para "Adicionar jogo"
- [ ] T050 [US9] Em `resources/js/pages/PanelMatches/index.jsx`: botão "Cadastrar jogo" (aba Jogos, com `confrontos.cadastrar`) e "Cadastrar campeonato" (aba Campeonatos, com `campeonatos.cadastrar`); ações Editar e Excluir dos jogos e campeonatos manuais (`ConfirmModal` com "Este campeonato tem N jogos, que também serão excluídos." no campeonato, com N = `total_jogos`; mensagem do servidor quando recusar) e "Adicionar jogo" no menu do campeonato manual

**Checkpoint**: US9 completa (quickstart §8).

---

## Phase 12: Polish & Cross-Cutting Concerns

- [ ] T051 Em `resources/js/hooks/useConfrontosPainel.js` (R-18, FR-039): intervalo de 30 s ativo só com `tipo=ao_vivo` (abas Jogos e Mais apostados) ou na aba Pendentes, chamando `recarregar({ silencioso: true })` (sem esqueleto; erro mostra "Não foi possível atualizar" e mantém os dados); pausa com `document.visibilityState === 'hidden'` e recarrega ao voltar (`visibilitychange`); limpa o intervalo ao trocar de aba ou de tipo e ao desmontar
- [ ] T052 Regenerar `docs/postman/wssports_api.postman_collection.json` (FR-038) na pasta de confrontos: rotas novas de contracts/api §2 e as alteradas de §1 (filtros novos de `GET /confrontos`, corpo completo do resultado, mensagens de exclusão)
- [ ] T053 [P] Atualizar artefatos das specs anteriores (R-20), com a nota "alterado pela spec 023": `specs/003-confrontos/spec.md` (FR-063 e a listagem do painel: dia e busca) e `specs/003-confrontos/contracts/api.md` (listagem e exclusão); `specs/009-apuracao-resultados/spec.md` (FR-045 e FR-046: qualquer jogo, tempos e escanteios obrigatórios, soma, só Admin, provedor vence) e o contrato dela; `specs/016-painel-cotacoes/contracts/` (parâmetros de URL da tela Cotações e `cotacoes/nomes` com `confrontos.listar`)
- [ ] T054 Conferir FR-034 a FR-037 em todas as abas, gaveta e modais: esqueleto, vazio e erro com "Tentar novamente" por área; botões com carregando e sem clique duplo; 360 px sem rolagem horizontal; alvos de toque ≥ 40 px; nenhum `ToggleSwitch`
- [ ] T055 Rodar `php artisan migrate`, `npm run build` e o roteiro do [quickstart.md](quickstart.md) §1 a §10, conferindo também que o painel de permissões (`PermissionsPanel`) não oferece `confrontos.lancar_resultado` ao Supervisor (data-model §2)
- [ ] T056 Revisão de legibilidade de todo o código alterado (Princípio V): nomes, comentários, `use` sem uso (o `CarbonTimeZone` e o `Rule` do `ConfrontosController` depois de T007), duplicações; registrar o resultado no fim deste arquivo

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sem dependências.
- **Foundational (Phase 2)**: depende do Setup (T001 para o índice usado pela lista); bloqueia todas
  as histórias.
- **US1 (Phase 3)**: depende da Foundational. É o MVP.
- **US2, US3**: dependem da US1 (tabela e página).
- **US4**: depende da US1; T020 é base de US5 e US7.
- **US5**: depende de T002 (migration) e de T020 (`palpites_da_rede`).
- **US6**: depende da US1 (ação na tabela); T036 é independente.
- **US7**: depende de T020 e da US1 (filtros).
- **US8**: depende da Foundational (página); independente das demais.
- **US9**: depende da US8 (aba Campeonatos) para o "Adicionar jogo" e de T049 para o cadastro de jogo.
- **Polish**: depois das histórias desejadas.

### Within Each User Story

- Backend (serviço → controller → rotas) antes dos componentes que chamam as rotas novas.
- Componentes [P] antes da integração na página `PanelMatches` (as tarefas na página não são
  paralelas entre si).

### Parallel Opportunities

- T001 e T002 juntos.
- T009 junto com T003–T008.
- US1: T014 e T015 em paralelo, depois T016.
- Componentes [P] de histórias diferentes (T018, T023, T024, T033, T034, T040, T044, T048, T049) e
  T027 podem ser feitos em paralelo; as tarefas em `PanelMatches/index.jsx` e em
  `ExposicaoConfrontos.php` não.

### Parallel Example: User Story 4

```text
T020 ExposicaoConfrontos → T021 controller e rotas → T022 show
T023 MatchPicksRanking | T024 MatchBetsList | T027 cotacoes/nomes
                    ↓
          T025 MatchDrawer → T026 página
```

## Implementation Strategy

1. **MVP**: Setup + Foundational + US1 → a banca consulta os jogos por dia e tipo, com os cartões
   (quickstart §1).
2. **Operação P1**: US2 (desativar) → US3 (limite) → US4 (gaveta) → US5 (pendentes e placar).
3. **P2**: US6 (atalho de cotações), US7 (mais apostados), US8 (campeonatos) e US9 (cadastro
   manual).
4. **Fechamento**: atualização automática, Postman, artefatos das specs 003, 009 e 016, quickstart
   completo e revisão.
