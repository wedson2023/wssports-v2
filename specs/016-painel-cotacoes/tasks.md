---

description: "Lista de tarefas da spec 016 — Painel: Cotações"
---

# Tasks: Painel — Cotações (porcentagens, valor do jogo e teto)

**Input**: Design documents from `specs/016-painel-cotacoes/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md),
[data-model.md](data-model.md), [contracts/api.md](contracts/api.md),
[contracts/paginas.md](contracts/paginas.md), [quickstart.md](quickstart.md)

**Tests**: nenhum teste automatizado (constituição). A validação é manual pelo
[quickstart.md](quickstart.md) e por um passeio no Chrome sem janela pelas telas novas (abrir cada
aba, editor e modal e conferir que não há erro de página).

**Organization**: tarefas agrupadas por história da spec. Cada tarefa cita os arquivos; arquivos
existentes só os listados no plan.md (Princípio IV). Ao concluir cada tarefa, revisão de
legibilidade (Princípio V).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: pode rodar em paralelo (arquivos diferentes, sem depender de tarefa incompleta)
- **[Story]**: história da spec (US1 a US4)

---

## Phase 1: Setup

**Purpose**: nada a instalar (sem dependência nova); só as tabelas novas.

- [X] T001 Criar a migration `database/migrations/2026_10_22_000001_create_porcentagens_cadeados_table.php` conforme data-model.md: `usuarios_id` FK `usuarios` ("Usuário travado: o cadeado vale para ele e para todos abaixo dele"), `trancado_por` FK `usuarios` ("Quem trancou (Admin ou Supervisor); só ele ou um superior dele destranca"), `camada` varchar(20) ("`vendedores` ou `campeonatos`"), `tipo` varchar(10) nullable ("`pre_jogo` ou `ao_vivo` na camada vendedores; nulo em campeonatos"), `campeonatos_id` FK `campeonatos` nullable, `codigo` varchar(10) ("`odd1` a `odd323` ou `jogador`"), colunas geradas `chave_tipo` = `COALESCE(tipo, '')` e `chave_campeonato` = `COALESCE(campeonatos_id, 0)`, `timestamps()`, `softDeletes()`, índice único (`usuarios_id`, `trancado_por`, `camada`, `chave_tipo`, `chave_campeonato`, `codigo`) com nome explícito curto e índice (`camada`, `usuarios_id`, `codigo`); todas as colunas com `->comment()` em português
- [X] T002 [P] Criar a migration `database/migrations/2026_10_22_000002_create_porcentagens_historicos_table.php` conforme data-model.md: `usuarios_id` FK `usuarios` (autor), `camada` varchar(20) (`vendedores`, `clientes`, `campeonatos`, `confrontos` ou `tetos`), `acao` varchar(20) (`alterar`, `trancar` ou `destrancar`), `tipo` varchar(10) nullable, `alvo` varchar(20) nullable (`Todos`, `Clientes` ou `Vendedores`), `alvo_usuarios_id` FK `usuarios` nullable, `clientes_id` FK `clientes` nullable, `campeonatos_id` FK `campeonatos` nullable, `confrontos_id` FK `confrontos` nullable, `alteracoes` json ("`{codigo: {antes, depois}}` só dos códigos alterados; null = sem ajuste"), `timestamps()`, `softDeletes()`, índices (`camada`, `alvo_usuarios_id`), (`campeonatos_id`), (`confrontos_id`), (`clientes_id`), (`created_at`); todas as colunas com `->comment()`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: models, histórico gravado por todas as rotas, prévia, página e editor comuns.

**⚠️ CRITICAL**: nenhuma história começa antes desta fase.

- [X] T003 [P] Criar o model `app/Models/PorcentagensCadeados.php` (SoftDeletes; fillable das colunas de T001; relações `usuario`, `autor` (`trancado_por`) e `campeonato`) seguindo o padrão de `app/Models/PorcentagensCampeonatos.php`
- [X] T004 [P] Criar o model `app/Models/PorcentagensHistoricos.php` (SoftDeletes; cast `alteracoes` array; relação `autor` com `withTrashed()` para o nome continuar aparecendo se o usuário for excluído)
- [X] T005 Criar `app/Services/HistoricoCotacoes.php` com `registrar(Usuarios $autor, string $camada, string $acao, array $contexto, array $antes, array $depois): void` que calcula `{codigo: {antes, depois}}` só dos códigos que mudaram (ausente = null) e não grava nada sem diferença (R-05)
- [X] T006 [P] Gravar histórico em `app/Http/Controllers/PorcentagensClientesController.php` (update: guardar `valores` antes, salvar e registrar camada `clientes`, tipo, `clientes_id`, dentro de `DB::transaction`)
- [X] T007 [P] Gravar histórico em `app/Http/Controllers/PorcentagensConfrontosController.php` (camada `confrontos`, alvo, `alvo_usuarios_id`, `confrontos_id`; transação) e recusar a gravação de jogo que já começou (`data_inicio` ≤ agora) com 422 em `confronto` "O jogo já começou; o valor do jogo vale só antes do início." (caso de borda da spec)
- [X] T008 [P] Gravar histórico em `app/Http/Controllers/ConfrontosTetoCotacoesController.php` (camada `tetos`; transação)
- [X] T009 [P] Gravar histórico em `app/Http/Controllers/PorcentagensVendedoresController.php` (camada `vendedores`, tipo, `alvo_usuarios_id`; transação) — o cadeado entra em T025
- [X] T010 [P] Gravar histórico em `app/Http/Controllers/PorcentagensCampeonatosController.php` (camada `campeonatos`, alvo, `alvo_usuarios_id`, `campeonatos_id`; transação) — o cadeado entra em T031
- [X] T011 Adicionar em `app/Services/CalculoCotacoes.php` o método público `detalhar(Publico $publico, string $tipo, Confrontos $confronto, array $codigos, ?array $excluir = null): array` que devolve por código `provedor`, `publico`, `superiores`, `geral`, `campeonato`, `jogo`, `teto`, `maximo_ao_vivo` reaproveitando `porcentagens_publico`, `regras_por_item`, os tetos e `cotacao_maxima_ao_vivo`, sem mudar `ajustar()` nem `aplicar()` (R-03); `excluir` tira a regra em edição (camada, alvo, dono) das parcelas
- [X] T012 Criar `app/Http/Controllers/CotacoesPainelController.php` com o helper de permissão "qualquer permissão de cotação" e "permissão da camada" via `Funcao::usuario_pode` (403 "Você não tem permissão para esta ação.") e a ação `nomes` (`GET /cotacoes/nomes`, a partir de `App\Support\NomesCotacoes` + `jogador`, contracts/api §1)
- [X] T013 Registrar em `routes/api.php` o grupo autenticado `cotacoes` com `GET cotacoes/nomes`; em `routes/web.php` `GET painel/cotacoes` → `PainelController@cotacoes`; adicionar a ação `cotacoes()` em `app/Http/Controllers/PainelController.php` (Inertia `PanelQuotes`)
- [X] T014 [P] Criar `resources/js/utils/quotes.js`: `grupos_mercado()` a partir de `abas_cotacoes` de `utils/market_groups.js` sem "Favoritas", mais "Jogadores" (`jogador`) e "Outras" (códigos sem grupo); `calcular_cotacao(partes, pendentes)` igual a `CalculoCotacoes::aplicar` (provedor ≤ 0 → 0; `provedor + provedor × soma ÷ 100 + jogo`, teto, máximo, mínimo 1,00, 2 casas) devolvendo também `limitada_teto`/`limitada_minimo`; `formatar_ajuste(valor, unidade)` com "+"/"−"; `ler_numero(texto)` aceitando vírgula ou ponto
- [X] T015 [P] Criar `resources/js/hooks/useNomesCotacoes.js` (GET `/cotacoes/nomes` uma vez por sessão, cache em memória do módulo)
- [X] T016 [P] Criar `resources/js/hooks/useGradeCotacoes.js`: `original`, `pendentes`, `erros`, `trancados`; `mudar`, `aplicar_a_todas(codigos, valor)` que pula trancados e devolve quantos pulou, `desfazer`, `quantidade_pendentes`, `alteracoes_para_salvar()` (só as alteradas, vazio = 0 para remover), validação de faixa (−100 a 100; teto ≥ 1,00) e aviso ao sair com pendentes (`beforeunload` e `router.on('before')` do Inertia com `confirmar` de `utils/alerts.js`); depois de salvar, `original` passa a ser o que a resposta da gravação devolver (quem salva por último vê os valores gravados)
- [X] T017 [P] Criar `resources/js/hooks/useListaCotacoes.js` (lista paginada com filtros e busca com atraso de 400 ms, no padrão de `hooks/useUsuariosPainel.js`, limpando a lista ao trocar de filtro)
- [X] T018 Criar `resources/js/components/QuoteGrid/` (`index.jsx`, `styles.jsx`) conforme contracts/paginas §2: barra com busca sem acento, contador, "Aplicar a todas" com confirmação da quantidade, abrir/fechar todos; grupos por mercado que abrem e fecham com "N de M ajustadas" (verde/amarelo/cinza) e "aplicar a todas do grupo"; linha com nome, campo com sufixo, "era X" quando pendente, cor de aumento/redução, destaque de pendente, erro no campo, cadeado (fechado/aberto/desabilitado com "trancada por {nome}"), modos `porcentagem`, `jogo` (provedor e diferença ao lado, indisponível desabilitado) e `teto`; altura mínima de 44 px por linha no celular; rótulo em cima e bordas do tema claro como nas specs 014/015
- [X] T019 Criar `resources/js/components/QuotePreview/` e `resources/js/components/SaveBar/` (`index.jsx`, `styles.jsx`) conforme contracts/paginas §2: prévia com jogo de exemplo, "Trocar jogo" (lista com busca de `GET /cotacoes/previa/jogos`), parcelas da cotação em foco com selo "não salvo", resultado em destaque e avisos (teto, mínimo, indisponível), coluna fixa de 320 px no computador e faixa fixa embaixo no celular que expande ao tocar; SaveBar com "N alterações não salvas", Desfazer e Salvar com carregando
- [X] T020 Criar a página `resources/js/pages/PanelQuotes/` (`index.jsx`, `styles.jsx`) no `PanelShell` com `PanelTabs` só das abas permitidas (`?aba=`), estados de carregando/vazio/erro, volta para `/painel` sem nenhuma permissão de cotação, e adicionar o item "Cotações" (ícone `price_change`, visível com qualquer uma das cinco permissões) em `resources/js/components/PanelShell/index.jsx`

**Checkpoint**: tabelas, histórico gravado nas cinco rotas, prévia no servidor, página com abas e editor comum prontos.

---

## Phase 3: User Story 1 - Ajustar as cotações de um público (Priority: P1) 🎯 MVP

**Goal**: aba Público com clientes do site, cliente específico e usuários da rede, pré-jogo e ao vivo, prévia e cadeado.

**Independent Test**: quickstart §2 e §3.

- [X] T021 [US1] Adicionar em `app/Http/Controllers/CotacoesPainelController.php` as ações `publicos` (`GET /cotacoes/publicos`, ele mesmo e a rede via `ids_sub_hierarquia`; Admin e Supervisor: todos; ativos; busca; paginado ≤ 100), `clientes` (`GET /cotacoes/clientes`, busca por nome, login ou telefone, paginado) e `usuario` (`GET /cotacoes/usuarios/{usuario}` com `garantir_dono`, `pre_jogo`, `ao_vivo`, `superiores` = soma dos superiores sem ele e `cadeados` que valem para ele com `pode_destrancar`) conforme contracts/api §2 a §4; registrar as três rotas em `routes/api.php`
- [X] T022 [US1] Adicionar a ação `previa` (`GET /cotacoes/previa`, contracts/api §8) em `app/Http/Controllers/CotacoesPainelController.php` usando `CalculoCotacoes::detalhar` e montando o `Publico` do público escolhido (clientes, cliente ou usuário); sem `confrontos_id`, o próximo jogo de pré-jogo com a cotação disponível; e a ação `previa_jogos` (`GET /cotacoes/previa/jogos`, próximos 20 de pré-jogo com busca por time, qualquer permissão de cotação, contracts/api §8b); registrar as duas rotas
- [X] T023 [US1] Criar `app/Services/CadeadosCotacoes.php` (`codigos_trancados(Usuarios $solicitante, string $camada, Usuarios $usuario, ?string $tipo, ?int $campeonatos_id): array` com `{codigo: autor}` pela regra do data-model.md; `trancar`; `destrancar` só do autor ou de quem está abaixo do solicitante, devolvendo `nao_destrancados`; gravar histórico `trancar`/`destrancar` via `HistoricoCotacoes`) e `app/Http/Requests/CadeadosCotacoesRequest.php` (`camada` in:vendedores,campeonatos; `tipo` required na camada vendedores e prohibited em campeonatos; `campeonatos_id` required/exists só em campeonatos; `usuarios_id` exists; `codigos` array de códigos válidos por `CodigosCotacao::e_regra`; mensagens em português)
- [X] T024 [US1] Adicionar as ações `trancar` (`POST /cotacoes/cadeados`) e `destrancar` (`DELETE /cotacoes/cadeados`) em `app/Http/Controllers/CotacoesPainelController.php` (só Admin e Supervisor com a permissão da camada; Gerente → 403; `garantir_dono` no usuário) conforme contracts/api §11–12; registrar as rotas
- [X] T025 [US1] Respeitar o cadeado em `app/Http/Controllers/PorcentagensVendedoresController.php`: `valores` com código trancado → 422 em `valores.{codigo}` "A cotação {nome} está trancada por {autor}."; `todos` aplica só aos não trancados e a resposta ganha `pulados` (quantidade)
- [X] T026 [P] [US1] Criar `resources/js/components/AudiencePicker/` (`index.jsx`, `styles.jsx`): "Clientes do site", "Cliente específico" com busca e resultados, usuários da rede agrupados por função com busca, só as opções permitidas; tudo escolhido de lista (constituição v3.7.0)
- [X] T027 [US1] Montar a aba Público em `resources/js/pages/PanelQuotes/index.jsx`: `AudiencePicker`, alternador Pré-jogo/Ao vivo, linha "Superiores somam"/"Regra geral dos clientes soma", `QuoteGrid` modo porcentagem com cadeados (clicáveis para Admin/Supervisor, toast ao trancar/destrancar), `QuotePreview` (`GET /cotacoes/previa` com a regra em edição excluída), `SaveBar` gravando em `PATCH /porcentagens-vendedores/{usuario}` ou `PATCH /porcentagens-clientes` com `valores`, mensagens do servidor nos campos e aviso de `pulados`; vazio inicial "Escolha um público para ajustar as cotações"

**Checkpoint**: a aba Público substitui "Alterar cotação – geral" do antigo.

---

## Phase 4: User Story 2 - Ajustar as cotações de um campeonato (Priority: P2)

**Goal**: lista de campeonatos com selo, editor por alvo em modal, cadeado no alvo Vendedores.

**Independent Test**: quickstart §4.

- [X] T028 [US2] Adicionar as ações `paises` (`GET /cotacoes/paises`) e `campeonatos` (`GET /cotacoes/campeonatos`: busca, país, `com_ajuste` via `whereExists`, `jogos_futuros` via `withCount` de pré-jogo a partir de agora, `resumo` das regras visíveis com algum código montado numa consulta para os ids da página, ordenado pelos favoritos primeiro e depois pelo nome, paginado padrão 20 máx. 100) em `app/Http/Controllers/CotacoesPainelController.php` conforme contracts/api §5–6 e R-07; registrar as rotas
- [X] T029 [P] [US2] Criar `resources/js/components/RuleBadge/` ("sem ajuste" cinza; "+3% · Clientes"; "2 regras · −5% a +3%") e `resources/js/components/QuoteListTable/` (tabela no computador; no celular só a coluna principal com subtítulo, selo e `ActionsMenu`; textos longos cortados; ações Editar e Histórico; `Pagination`) com `index.jsx` e `styles.jsx`
- [X] T030 [P] [US2] Criar `resources/js/components/TargetPicker/` (Todos, Clientes, "Vendedores de" + usuário da rede escolhido de lista; só as opções permitidas: Gerente só Vendedores da rede, Todos e Clientes só Admin e Supervisor) e `resources/js/components/QuoteEditorModal/` (Backdrop, altura fixa desde a abertura, 100% no celular, `ModalHeader` com ícone/título/descrição, `TargetPicker`, `QuoteGrid`, `QuotePreview`, `SaveBar` no rodapé, confirmação ao fechar com pendentes e confirmação ao salvar com alvo Todos: "Esta regra vai valer para visitantes, clientes e todos os vendedores. Continuar?" (FR-023)) com `index.jsx` e `styles.jsx`
- [X] T031 [US2] Respeitar o cadeado da camada `campeonatos` em `app/Http/Controllers/PorcentagensCampeonatosController.php` no alvo Vendedores (mesma regra e mensagens de T025, com `campeonatos_id`)
- [X] T032 [US2] Montar a aba Campeonatos em `resources/js/pages/PanelQuotes/index.jsx`: busca, filtro de país (lista com bandeira de `GET /cotacoes/paises`), chave "Só com ajuste", `QuoteListTable` com `RuleBadge`, `QuoteEditorModal` (ícone `emoji_events`, "Cotações · {campeonato}", "Ajuste as porcentagens deste campeonato para o público escolhido. Vale para pré-jogo e ao vivo.") gravando em `PATCH /porcentagens-campeonatos/{campeonato}` com `alvo`/`usuarios_id`, cadeados no alvo Vendedores para Admin/Supervisor, e atualização do selo da linha após salvar sem recarregar a página

**Checkpoint**: campeonatos ajustáveis por alvo com selo e cadeado.

---

## Phase 5: User Story 3 - Cotação de um jogo e teto das cotações (Priority: P3)

**Goal**: aba Jogos (cotação desejada por alvo, só pré-jogo) e aba Tetos (só quem tem a permissão).

**Independent Test**: quickstart §5.

- [X] T033 [US3] Adicionar a ação `confrontos` (`GET /cotacoes/confrontos`: pré-jogo de hoje, amanhã e depois de amanhã em Brasília, busca por time, `campeonatos_id`, `dia`, `com_ajuste`, `resumo`, paginado) em `app/Http/Controllers/CotacoesPainelController.php` conforme contracts/api §7; registrar a rota
- [X] T034 [US3] Montar a aba Jogos em `resources/js/pages/PanelQuotes/index.jsx`: busca por time, filtro de campeonato (lista com busca), dia, "Só com ajuste", `QuoteListTable`, `QuoteEditorModal` (ícone `sports_soccer`, "Cotações · {casa} x {fora}", "Informe a cotação desejada para o público escolhido. Vale só para o pré-jogo.") com `QuoteGrid` modo jogo (cotações do provedor de `GET /porcentagens-confrontos/{confronto}`), gravando em `PATCH /porcentagens-confrontos/{confronto}` com `alvo`, `usuarios_id` e `cotacoes`
- [X] T035 [US3] Montar a aba Tetos em `resources/js/pages/PanelQuotes/index.jsx`: aviso "O teto vale para todos os públicos: nenhuma cotação passa deste valor.", `QuoteGrid` modo teto sem cadeado, `QuotePreview`, `SaveBar` gravando em `PATCH /confrontos-teto-cotacoes`; aba só com `confrontos_teto_cotacoes.editar`

**Checkpoint**: as quatro camadas editáveis.

---

## Phase 6: User Story 4 - Regras ativas (Priority: P4)

**Goal**: lista única do que está ajustado, com histórico e atalho para editar.

**Independent Test**: quickstart §6.

- [X] T036 [US4] Adicionar as ações `regras_ativas` (`GET /cotacoes/regras-ativas`: UNION das seis tabelas de regra e do teto com `JSON_LENGTH(valores) > 0`, normalizadas em camada, referência, alvo, usuário (buscado com `withTrashed`, marcado "inativo" ou "excluído"), tipo, quantidade, mínimo, máximo, `updated_at`; cada camada só com a sua permissão e o alcance; filtro por camada; paginado) e `historico` (`GET /cotacoes/historico` filtrado pela referência e pelo alcance, mais nova primeiro, paginado) em `app/Http/Controllers/CotacoesPainelController.php` conforme contracts/api §9–10 e R-08; registrar as rotas
- [X] T037 [P] [US4] Criar `resources/js/components/QuoteHistoryModal/` (`index.jsx`, `styles.jsx`): altura fixa, `ModalHeader` (ícone `history`, "Histórico · {regra}", "Alterações desta regra, da mais nova para a mais antiga."), itens com autor, data e ação ("alterou 12 cotações", "trancou Casa") que abrem a lista "Casa: −5% → −10%", paginado
- [X] T038 [US4] Montar a aba Regras ativas em `resources/js/pages/PanelQuotes/index.jsx` (filtro por camada, `QuoteListTable` com a camada por ícone, selo "inativo", faixa de valores) e ligar "Histórico" nas abas Campeonatos e Jogos e em Regras ativas ao `QuoteHistoryModal`; "Editar" abre a aba e o item certos (público e tipo na aba Público; modal no campeonato/jogo)

**Checkpoint**: todas as histórias funcionando.

---

## Phase 7: Polish & Cross-Cutting Concerns

- [X] T039 Regenerar `docs/postman/wssports_api.postman_collection.json` com a pasta "Cotações do painel" (13 rotas novas) e os exemplos de `pulados` e do 422 de cadeado nas rotas alteradas, substituindo o arquivo e mantendo `base_url` e `token`
- [X] T040 [P] Acrescentar em `specs/003-confrontos/spec.md` a nota "Alterado pela spec 016": as rotas de alteração de cotação gravam histórico e respeitam o cadeado por cotação
- [X] T041 Rodar `php artisan migrate` (e `migrate:rollback` + `migrate` das duas migrations), `npm run build` e um passeio no Chrome sem janela por `/painel/cotacoes` (cada aba, editor, modais, 1366 px e 360 px, temas claro e escuro) sem erro de página; registrar o resultado nas notas abaixo
- [ ] T042 Validação manual pelo [quickstart.md](quickstart.md) §1 a §8 (SC-001 a SC-008), registrando o que foi conferido

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sem dependência.
- **Foundational (Phase 2)**: depende de T001–T002; bloqueia todas as histórias.
- **US1 (Phase 3)**: depende da fase 2. **MVP**.
- **US2 (Phase 4)**: depende da fase 2; usa `CadeadosCotacoes` de T023 (fazer depois de US1).
- **US3 (Phase 5)**: depende da fase 2 e de `QuoteListTable`/`QuoteEditorModal`/`TargetPicker` (T029–T030).
- **US4 (Phase 6)**: depende da fase 2 (histórico gravado) e de `QuoteListTable` (T029).
- **Polish**: depois das histórias desejadas.

### Within Each Story

- Backend (ações e rotas) antes da aba no frontend.
- `PanelQuotes/index.jsx` é o mesmo arquivo em T020, T027, T032, T034, T035 e T038: em sequência.
- `CotacoesPainelController.php` e `routes/api.php` são os mesmos em T012, T021, T022, T024, T028, T033 e T036: em sequência.

### Parallel Opportunities

- T001 ∥ T002; T003 ∥ T004; T006–T010 (controllers diferentes); T014 ∥ T015 ∥ T016 ∥ T017.
- US1: T026 enquanto T021–T025 andam no backend.
- US2: T029 ∥ T030 enquanto T028/T031 andam no backend.
- US4: T037 enquanto T036.

## Parallel Example: User Story 2

```bash
Task: "Criar RuleBadge e QuoteListTable em resources/js/components/"
Task: "Criar TargetPicker e QuoteEditorModal em resources/js/components/"
Task: "Adicionar paises e campeonatos em app/Http/Controllers/CotacoesPainelController.php"
```

---

## Implementation Strategy

### MVP First (User Story 1)

1. Fases 1 e 2.
2. Fase 3 (Público + cadeado).
3. **Parar e validar** pelo quickstart §2 e §3: a aba Público já substitui a tela geral do antigo.

### Incremental Delivery

1. Fundação → US1 (MVP) → US2 (campeonatos) → US3 (jogos e tetos) → US4 (regras ativas e histórico).
2. Cada história é validada sozinha antes da próxima; deploy só quando o responsável pedir.

---

## Notes

- Commits só via `/speckit-git-commit`.
- Arquivos existentes alterados: só os da tabela do plan.md (Princípio IV), confirmados pelo
  responsável em 2026-10-21.
- Datas de exemplo na validação: horário de Brasília.

## Notas da implementação (2026-10-21)

**Ajustes em relação ao plano e aos contratos** (artefatos corrigidos):

- A prévia (`QuotePreview`) busca sozinha `GET /cotacoes/previa` a partir dos parâmetros do público,
  em vez de receber os dados da página, para a aba Público, a aba Tetos e o `QuoteEditorModal` não
  repetirem a mesma lógica. Com um jogo de outro campeonato (escolhido em "Trocar jogo"), o valor em
  edição do campeonato não entra na conta.
- `GET /cotacoes/usuarios/{usuario}` aceita `?campeonatos_id=` e devolve `cadeados_campeonato`
  (cadeados do editor de campeonato); `GET /cotacoes/previa` aceita `campeonatos_id` e `camada`.
- `GET /cotacoes/clientes` busca por nome, telefone ou e-mail (a tabela `clientes` não tem login).
- O cadeado não pode ser posto no próprio usuário (422 "Escolha um usuário da sua rede para trancar a
  cotação."), para não virar um "trancar a rede inteira" (o responsável escolheu o cadeado por usuário).
- `CadeadosCotacoes::manter_trancados()` devolve aos códigos trancados o valor que tinham quando a
  gravação usa `todos`; vendedores e campeonatos usam o mesmo método.
- As abas da página ficam num único componente (`PanelQuotes`) com os hooks no topo, sem componentes
  de aba novos fora do plano.

**Validação feita**:

- Migrations: `migrate` → `migrate:rollback --step=2` → `migrate` sem erro.
- API no servidor local (Admin 1, Supervisor 2, Gerente 3 da rede dele, Vendedor 4): nomes (324);
  públicos do Gerente só ele e a rede; Supervisor tranca "Casa" do Gerente; Gerente tranca → 403;
  Gerente altera "Casa" dele → 422 "A cotação Casa está trancada por ..."; altera "Casa" do vendedor
  dele → 422 (vale para quem está abaixo); `todos` −1 → `pulados=1` e "Casa" intacta; Supervisor altera
  "Casa" do Gerente → 200; soma dos superiores e `pode_destrancar=false` para o Gerente; prévia com as
  partes; campeonatos com `com_ajuste` e resumo "+3% · Clientes"; valor de jogo gravado; Regras ativas
  (Admin 3, Gerente 1); histórico com autor e antes → depois; Supervisor destranca. Dados de teste
  devolvidos ao estado anterior depois.
- `npm run build` sem erros.
- Chrome sem janela em `/painel/cotacoes` como Admin, 1366 px e 360 px: aba Público (clientes do site e
  um gerente, com prévia e cadeados), histórico, Campeonatos com o editor em modal, Jogos com o editor em
  modo cotação, Tetos e Regras ativas — sem erro de página e sem rolagem horizontal. Gravação pela tela:
  −10 em "Casa" para os clientes do site gravou; 150 mostra o erro no campo; apagar e salvar remove o
  ajuste.

**Falta validar no navegador** (T042, quickstart §1 a §8): os cenários com Supervisor e Gerente
logados na tela (cadeado clicável e campo trancado), o tema claro e o confronto de valores da prévia
com o site (SC-002) num jogo com cotação do provedor estável.
