---

description: "Lista de tarefas da spec 022 — Painel: Afiliados"
---

# Tasks: Painel — Afiliados (lista, aprovação, gaveta, regra de ganho e prestação de contas)

**Input**: Design documents from `specs/022-painel-afiliados/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md),
[data-model.md](data-model.md), [contracts/api.md](contracts/api.md),
[contracts/paginas.md](contracts/paginas.md), [quickstart.md](quickstart.md)

**Tests**: nenhum teste automatizado (constituição). A validação é manual pelo
[quickstart.md](quickstart.md).

**Organization**: tarefas agrupadas por história da spec (US1 a US7). Cada tarefa cita os arquivos;
arquivos existentes só os listados no plan.md (Princípio IV — a lista deve ser confirmada pelo
responsável antes do `/speckit-implement`). Ao concluir cada tarefa, revisão de legibilidade
(Princípio V). Valores em bcmath, consultas pelo query builder com parâmetros, comentários e
mensagens em português, nomes em snake_case. Alcance sempre pelo `GarantirAlcanceAfiliado`. Modais
com `ModalHeader`, corpo em grade de 2 colunas no computador e 1 no celular, seções com título,
rodapé fixo com "Cancelar" e a ação principal à direita, e `select` no lugar de `ToggleSwitch`
(R-12).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: pode rodar em paralelo (arquivos diferentes, sem depender de tarefa incompleta)
- **[Story]**: história da spec (US1 a US7)

---

## Phase 1: Setup

**Purpose**: banco e enum (data-model §1 e §3).

- [X] T001 [P] Criar `database/migrations/2026_10_27_000005_add_perfil_afiliados_table.php` (data-model §1): em `afiliados`, depois de `endereco`, `rede_social` string(20) nula ("Rede social principal do afiliado: Instagram, TikTok, YouTube, Facebook, Kwai, X ou Outra"), `link_perfil` string(255) nula ("Endereço do perfil do afiliado na rede social principal (http ou https)"), `quantidade_seguidores` unsignedInteger nulo ("Quantidade de seguidores informada pelo afiliado (0 a 999.999.999)") e `sobre` text nulo ("Texto 'Sobre você' do afiliado, até 1.000 caracteres"); `down()` remove as quatro
- [X] T002 [P] Criar `database/migrations/2026_10_27_000006_remove_afiliados_configuracoes_table.php` (data-model §3, R-05): remove `afiliados_tipo_ganho_padrao` e `afiliados_percentual_padrao` de `configuracoes`; `down()` recria as duas como na migration `2026_10_17_000007_add_afiliados_configuracoes_table.php` (string(20) default `'Saldo líquido'` e decimal(5,2) default 0, com os mesmos comentários)
- [X] T003 [P] Criar `app/Enums/RedeSocialAfiliado.php` (R-08) com docblock em português: casos `Instagram = 'Instagram'`, `TikTok = 'TikTok'`, `YouTube = 'YouTube'`, `Facebook = 'Facebook'`, `Kwai = 'Kwai'`, `X = 'X'`, `Outra = 'Outra'`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: model, fim da regra padrão, regras comuns dos requests, resource, página e menu.

**⚠️ CRITICAL**: nenhuma história começa antes desta fase.

- [X] T004 Em `app/Models/Afiliados.php`: `$fillable` + `rede_social`, `link_perfil`, `quantidade_seguidores`, `sobre`; casts `rede_social` → `RedeSocialAfiliado` e `quantidade_seguidores` → `integer`; relações `autor_cadastro(): BelongsTo` (`cadastrado_por` → `Usuarios`, `withTrashed()`) e `autor_decisao(): BelongsTo` (`decidido_por` → `Usuarios`, `withTrashed()`), com docblock (R-10)
- [X] T005 Remover a regra padrão das Configurações (R-05): em `app/Models/Configuracoes.php`, tirar `afiliados_tipo_ganho_padrao` e `afiliados_percentual_padrao` do `$fillable` (linhas 104-106) e dos casts (linhas 216-218, e o `use TipoGanhoAfiliado` se ficar sem uso); apagar `app/Http/Controllers/ConfiguracoesAfiliadosController.php`, `app/Http/Requests/ConfiguracoesAfiliadosRequest.php` e `app/Http/Resources/ConfiguracoesAfiliadosResource.php`; em `routes/api.php`, remover o `use` (linha 66) e as rotas `GET/PUT configuracoes/afiliados` (linhas 567-568)
- [X] T006 Em `app/Services/RegraComissaoAfiliados.php`: `definir_inicial(Afiliados, array $regra, Usuarios $autor)` grava sempre a regra informada (`tipo_ganho`, `percentual` com `bcadd(..., '0', 2)`, `vigente_desde = now()`, `usuarios_id = $autor->id`), sem olhar `afiliados.editar_comissao` nem `Configuracoes`; atualizar o docblock da classe e do método (spec 022, FR-012); remover os `use` que ficarem sem uso
- [X] T007 Em `app/Http/Requests/Concerns/DadosAfiliado.php` (R-05, R-08): novo `regras_perfil_afiliado(string $presenca, ?Afiliados $atual = null): array` — `rede_social` `[$presenca, Rule::enum(RedeSocialAfiliado::class)]`, `link_perfil` `[$presenca, 'string', 'max:255', 'url:http,https']`, `quantidade_seguidores` `[$presenca, 'integer', 'between:0,999999999']`, `sobre` `['sometimes', 'nullable', 'string', 'max:1000']`; com `$presenca = 'sometimes'` e `$atual` com valor não nulo no campo, acrescentar `required` (não volta a vazio); `normalizar_dados_afiliado` passa a tratar `sobre` e `link_perfil` vazios como nulo e `quantidade_seguidores` só com dígitos; novo `regras_comissao_obrigatoria(): array` (`tipo_ganho` `['required', Rule::enum(TipoGanhoAfiliado::class)]`, `percentual` `['required', 'numeric', 'between:0,100', 'decimal:0,2']`); mensagens novas em `mensagens_dados_afiliado()`: "A rede social é obrigatória.", "A rede social informada é inválida.", "O link do perfil é obrigatório.", "O link do perfil deve começar com http:// ou https://.", "O link do perfil deve ter no máximo 255 caracteres.", "A quantidade de seguidores é obrigatória.", "A quantidade de seguidores deve ser um número inteiro.", "A quantidade de seguidores deve estar entre 0 e 999.999.999.", "O texto sobre você deve ter no máximo 1.000 caracteres."
- [X] T008 Em `app/Http/Resources/AfiliadosResource.php` (contracts/api §2.1): acrescentar `rede_social`, `link_perfil`, `quantidade_seguidores`, `sobre`, `entradas` (`$this->when(tem_atributo('entradas'), fn () => self::valor($this->entradas))`), `cadastrado_por` e `decidido_por` (`whenLoaded('autor_cadastro'|'autor_decisao')`, `{ id, nome }` ou `null`); em `app/Http/Controllers/AfiliadosController.php`, `RELACOES` passa a incluir `autor_cadastro` e `autor_decisao`
- [X] T009 Registrar `GET painel/afiliados` em `routes/web.php` (nome `painel.afiliados`, comentário "afiliados do painel (spec 022)", junto das demais rotas `painel/*`) e a ação `afiliados()` em `app/Http/Controllers/PainelController.php` (Inertia `PanelAffiliates`, docblock "Tela "Afiliados" (spec 022); aba, busca e filtros vêm na query string.")
- [X] T010 [P] Em `resources/js/components/PanelShell/index.jsx`: item "Afiliados" (chave `afiliados`, ícone `handshake`, `href: '/painel/afiliados'`) depois de "Bônus", visível com `afiliados.listar` (contracts/paginas.md §1)
- [X] T011 [P] Em `resources/js/utils/settings_fields.js`: remover a seção `afiliados` (linhas 129-139) da aba Operação (contracts/paginas.md §6)
- [X] T012 Criar `resources/js/hooks/useAfiliadosPainel.js` (R-01, no padrão de `useBonusPainel`): filtros no endereço (`aba` = `ativos` | `analise` | `lixeira`, `busca`, `situacao`, `usuarios_id` + `gerente` (nome), `sem_gerente`, `data_inicial`, `data_final`, `page`; período padrão do dia 1 do mês até hoje) com `router.get(..., { preserveState: true, replace: true })`; `mudar(parcial)` volta à página 1; `trocar_aba(aba)` limpa `situacao`; `parametros_api(filtros)` monta `grupo` (`aprovados` / `analise`), `de`, `ate`; carrega a lista (`GET /afiliados` ou `/afiliados/excluidos`, padrão 20) e o resumo (`GET /afiliados/resumo`) com o token do `usePainel`; `recarregar()` sem perder a posição; exporta `abas_afiliados` e `situacoes_por_aba`
- [X] T013 Criar a página `resources/js/pages/PanelAffiliates/index.jsx` e `styles.jsx` (contracts/paginas.md §1): `PanelShell` título "Afiliados", barra "Afiliados — total N", busca com atraso de 400 ms, botões Filtros/Cadastrar/Atualizar, `PanelTabs` (Ativos `group`, Em análise `hourglass_top` só para Admin com `afiliados.alterar_situacao`, Lixeira `delete_outline` só com `afiliados.restaurar`), áreas com esqueleto, vazio e erro com "Tentar novamente" (FR-035); sem `afiliados.listar`, volta para `/painel`

**Checkpoint**: a página abre pelo menu; a seção Afiliados some das Configurações; migrations rodam.

---

## Phase 3: User Story 1 - Consultar os afiliados (Priority: P1) 🎯 MVP

**Goal**: cartões e lista da aba Ativos com busca, filtros, período e rede.

**Independent Test**: quickstart §1.

- [X] T014 [US1] Em `app/Services/ListagemAfiliados.php` (R-02 a R-04): `periodo(?string $de, ?string $ate, string $padrao = 'hoje')` aceita `'mes'` (dia 1 do mês até hoje, fuso `FUSO`); `consulta_gestao` usa `'mes'`, aplica `grupo` (`aprovados` → `situacao in (Ativo, Inativo)`; `analise` → `situacao in (Em análise, Recusado)` e ordem `afiliados.created_at` asc + `id` no lugar do nome), carrega `autor_cadastro` e `autor_decisao` e soma `withSum(['lancamentos as entradas' => fn ($c) => $c->emAberto()], 'base')`; novo `resumo(Builder $consulta, array $filtros, bool $com_em_analise): array` com consultas agregadas (sem carregar linhas) devolvendo `total_afiliados` (Ativo + Inativo do filtro), `em_analise` (contagem de todos os Em análise, sem busca nem filtros — R-16; `null` quando `$com_em_analise` é falso), `comissao_periodo` (no período) e `a_receber` (sem período) (somas de `afiliados_lancamentos.valor` por `whereIn('afiliados_id', subconsulta dos afiliados Ativos e Inativos do filtro)`, no período por `ocorrido_em` e em aberto, respectivamente), valores com `bcadd(..., '0', 2)`, e `periodo` `{ de, ate }` em `Y-m-d`
- [X] T015 [US1] Em `app/Http/Controllers/AfiliadosController.php` (contracts/api §1.1 e §2.1): `listar()` aceita `'grupo' => ['nullable', 'in:aprovados,analise']` (mensagem "O grupo deve ser aprovados ou analise."); nova ação `resumo(Request)` com `afiliados.listar`, mesmos filtros (sem `situacao`, `grupo` e `por_pagina`), `aplicar_alcance` e `com_em_analise` = usuário Admin com `afiliados.alterar_situacao`; resposta `{ data: {...} }`; em `routes/api.php`, `Route::get('afiliados/resumo', ...)` antes de `afiliados/{afiliado}`
- [X] T016 [P] [US1] Criar `resources/js/components/AffiliatesFilters/index.jsx` e `styles.jsx` (no padrão de `PromotionsFilters`): Situação (`select`, opções por aba), Gerente (só Admin e Supervisor; lista por `GET /usuarios?funcao=Gerente&por_pagina=100`, R-15, escolhido de lista, nunca texto livre; sem `usuarios.listar`, o aviso "Você não tem acesso à lista de gerentes."), Sem gerente (`select` Sim/Todos, só Admin), Período (`PeriodFilter`, padrão Este mês, personalizado até 1 ano, "de" ≤ "até"); botões Limpar filtros e Aplicar na mesma linha; exporta `etiquetas_afiliados(filtros)` e `remover_filtro_afiliados(filtros, chave)` para o `ActiveFilters`
- [X] T017 [P] [US1] Criar `resources/js/components/AffiliatesTable/index.jsx` e `styles.jsx` (no padrão de `PromotionsTable`): variantes por aba (contracts/paginas.md §1: colunas de Ativos/Lixeira e de Em análise), "Sem gerente responsável" em destaque só para o Admin, Ganho "A definir" quando `comissao` é `null`, A receber em vermelho quando negativo, Última prestação "Nunca" sem prestação, situação com cor, link do perfil abrindo em nova aba (`rel="noopener noreferrer"`), `ActionsMenu` com os itens recebidos por prop, linha clicável; no celular (até 900 px) vira cartão (nome e código, a receber, situação) com o restante ao expandir
- [X] T018 [US1] Na página `resources/js/pages/PanelAffiliates/index.jsx`: cartões `StatCard` (Total de afiliados, Em análise só Admin, Comissão do período, A receber) com o resumo; `ActiveFilters` com as etiquetas; `AffiliatesFilters` em painel (painel próprio no celular); `AffiliatesTable` + `Pagination`; chips de situação da aba (Ativos: Todos/Ativos/Inativos); estados de carregando, vazio e erro separados para cartões e lista (FR-035)

**Checkpoint**: quickstart §1 passa.

---

## Phase 4: User Story 2 - Cadastrar e editar afiliados (Priority: P1)

**Goal**: modal de cadastro e edição em grade, com regra obrigatória, perfil, situação e senha.

**Independent Test**: quickstart §2.

- [X] T019 [US2] Em `app/Http/Requests/StoreAfiliadosRequest.php`: trocar `regras_comissao_opcional()` por `regras_comissao_obrigatoria()` e acrescentar `regras_perfil_afiliado('required')`; mensagens com `mensagens_comissao()`; em `app/Http/Requests/UpdateAfiliadosRequest.php`: acrescentar `regras_perfil_afiliado('sometimes', $this->route('afiliado'))`; em `app/Services/CadastroAfiliados.php`, `cadastrar_pelo_painel` continua chamando `criar` (que passa a ter os campos de perfil em `$fillable`) e o docblock cita a regra obrigatória (FR-012)
- [X] T020 [P] [US2] Criar `resources/js/components/AffiliateProfileFields/index.jsx` e `styles.jsx` (R-13): campos Rede social principal (`select` com as 7 opções do enum), Link do perfil (`type="url"`, placeholder `https://`), Quantidade de seguidores (só dígitos, `inputMode="numeric"`, até 9 dígitos) e Sobre você (`textarea` com contador "N/1000" e `maxLength=1000`); props `valores`, `ao_mudar`, `erros`, `obrigatorios` e `aparencia` (`painel` | `publico`, para seguir o visual do modal onde é usado)
- [X] T021 [US2] Criar `resources/js/components/AffiliateFormModal/index.jsx` e `styles.jsx` (contracts/paginas.md §2): `ModalHeader` (ícone `person_add`/`edit`, "Cadastrar afiliado"/"Editar afiliado", descrição); seções Dados pessoais, Contato e endereço, PIX (tipo em `select`, máscara conforme o tipo), Perfil (`AffiliateProfileFields`), Ganho (gerente em `select` da lista de `GET /usuarios?funcao=Gerente&por_pagina=100` — R-15; oculto para o Gerente; sem `usuarios.listar`, o aviso no campo —, tipo de ganho em `select`, percentual 0 a 100 com 2 casas); na edição: login só leitura, sem tipo/percentual, código só Admin/Supervisor, Situação (`select` Ativo/Inativo, com `afiliados.alterar_situacao` e só para Ativo/Inativo), Nova senha e Confirmar nova senha opcionais; campos mascarados (sem `clientes.ver_dados_completos`) mostram o valor mascarado e só vão na requisição quando o usuário digita (R-09); envia só os campos alterados; erros do servidor no campo; altura fixa (`height: min(680px, 90dvh)`), rodapé com Cancelar e Salvar; salvar faz `POST /afiliados` ou `PATCH /afiliados/{id}` e, se a situação mudou, `PATCH /afiliados/{id}/situacao`; se os dados salvam e a situação falha, o modal continua aberto com o erro no campo Situação e o aviso "Os dados foram salvos; a situação não foi alterada." (FR-013)
- [X] T022 [US2] Na página `resources/js/pages/PanelAffiliates/index.jsx`: botão Cadastrar (com `afiliados.cadastrar`) e itens "Editar" (com `afiliados.editar`) e "Ativar"/"Inativar" (com `afiliados.alterar_situacao`, `ConfirmModal`, `PATCH .../situacao`) no menu da aba Ativos; depois de salvar, aviso, lista e cartões atualizados sem perder filtros e página (FR-036)

**Checkpoint**: quickstart §2 passa.

---

## Phase 5: User Story 3 - Aprovar ou recusar cadastros públicos (Priority: P1)

**Goal**: aba Em análise só do Admin, aprovação com gerente e regra obrigatórios, recusa com motivo.

**Independent Test**: quickstart §3.

- [X] T023 [US3] Em `app/Http/Requests/AprovarAfiliadoRequest.php` (contracts/api §2.5): `usuarios_id` `['required', 'integer', $this->regra_gerente()]` e `regras_comissao_obrigatoria()`, com as mensagens de comissão e "Escolha o gerente responsável para aprovar."; em `app/Services/CadastroAfiliados.php`: `aprovar` sempre grava o gerente e chama `RegraComissaoAfiliados::trocar` (encerra a vigente, se houver, e abre a nova); `cadastrar_publico` não cria regra (`criar` só chama `definir_inicial` quando há `$autor` e a regra veio nos dados); docblocks atualizados (FR-020, FR-022)
- [X] T024 [US3] Em `app/Http/Controllers/AfiliadosController.php` (R-06): `aprovar` e `recusar` trocam `garantir_gestor_geral` por `abort_unless($request->user()->funcao() === Funcao::Admin, 404, 'Afiliado não encontrado.')`; docblock "Aprovar e recusar o cadastro público é só do Admin (spec 022, FR-023)"; remover a dependência `AlcanceHierarquia` do construtor se ficar sem uso
- [X] T025 [P] [US3] Criar `resources/js/components/AffiliateApprovalModal/index.jsx` e `styles.jsx` e `resources/js/components/AffiliateRejectModal/index.jsx` e `styles.jsx` (R-12). Recusa, no padrão do `WithdrawalDecisionModal`: `ModalHeader` (ícone `person_off`, "Recusar cadastro", "O afiliado recebe o motivo por e-mail."), motivo opcional (`textarea`, até 255, contador, erro do servidor no campo), Cancelar e Recusar, `POST /afiliados/{id}/recusar`. Aprovação: `ModalHeader` (ícone `how_to_reg`, "Aprovar cadastro", "Escolha o gerente e a regra de ganho para liberar o afiliado."); resumo (nome, rede, seguidores, link); gerente (`select` de `GET /usuarios?funcao=Gerente&por_pagina=100`, R-15), tipo de ganho (`select`) e percentual, obrigatórios e preenchidos com a regra atual quando houver; erros no campo; Cancelar e Aprovar; `POST /afiliados/{id}/aprovar`
- [X] T026 [US3] Na página `resources/js/pages/PanelAffiliates/index.jsx`: aba Em análise com chips Em análise (padrão) e Recusados, colunas de Em análise da `AffiliatesTable`, menu Ver detalhe/Aprovar/Recusar (Recusar só em Em análise, abre o `AffiliateRejectModal`); depois da ação, aviso e lista, cartões e aba atualizados

**Checkpoint**: quickstart §3 passa.

---

## Phase 6: User Story 4 - Ver o afiliado e trocar a regra de ganho (Priority: P1)

**Goal**: gaveta com Dados, Extrato, Clientes, Prestações e Regras; troca de regra.

**Independent Test**: quickstart §4.

- [X] T027 [US4] Em `app/Http/Controllers/AfiliadosExtratoController.php`, `lancamentos` (contracts/api §2.8): calcular a soma de `valor` com os mesmos filtros (sem paginação, query builder) e devolver em `meta.soma` (`->additional(['meta' => ['soma' => AfiliadosResource::valor($soma)]])`, conferindo que o `meta` da paginação é mantido)
- [X] T028 [P] [US4] Criar `resources/js/components/AffiliateRuleModal/index.jsx` e `styles.jsx`: `ModalHeader` (ícone `percent`, "Trocar regra de ganho", "A nova regra vale a partir de agora para os próximos lançamentos."); regra vigente só leitura; tipo de ganho (`select`) e percentual; Cancelar e Trocar regra; `PUT /afiliados/{id}/comissao`
- [X] T029 [US4] Criar `resources/js/components/AffiliateDrawer/index.jsx` e `styles.jsx` (R-10, no padrão de `PromotionDrawer`; contracts/paginas.md §3): topo com nome, código + "Copiar link", situação, regra vigente e a receber; abas Dados, Extrato (período + Em aberto/Prestados/Todos, soma), Clientes ("Ver cliente" abre o `ClientDrawer` com `clientes.listar`), Prestações e Regras ("Trocar regra" com `afiliados.editar_comissao`, abre o `AffiliateRuleModal`), cada uma carregada ao abrir, paginada (30), com esqueleto, vazio e erro; "Não informado" para campos nulos; tela cheia no celular
- [X] T030 [US4] Na página `resources/js/pages/PanelAffiliates/index.jsx`: linha e "Ver detalhe" abrem o `AffiliateDrawer` (com `afiliados.consultar`; sem ela, a linha não abre); item "Trocar regra" no menu da aba Ativos (com `afiliados.editar_comissao`); depois da troca, gaveta, lista e cartões atualizados

**Checkpoint**: quickstart §4 passa.

---

## Phase 7: User Story 5 - Prestar contas (Priority: P1)

**Goal**: modal de prestação com o resumo antes de confirmar e a senha.

**Independent Test**: quickstart §5.

- [X] T031 [P] [US5] Criar `resources/js/components/AffiliateSettlementModal/index.jsx` e `styles.jsx` (FR-027, FR-028): `ModalHeader` (ícone `payments`, "Prestar contas", "Confira o valor e confirme com a sua senha."); carrega `GET /afiliados/{id}/lancamentos?situacao=aberto&por_pagina=1` para a quantidade (`meta.total`) e o apurado (`meta.soma`); mostra o valor a pagar (zero quando negativo, com o aviso "O saldo negativo será zerado e não passa para o próximo período."), a chave PIX (como vem da API), observação (até 500, com contador) e senha; Cancelar e Prestar contas; `POST /afiliados/{id}/prestacoes`; "Senha incorreta." no campo; sucesso mostra o valor pago devolvido pela API
- [X] T032 [US5] Na página `resources/js/pages/PanelAffiliates/index.jsx`: item "Prestar contas" no menu da aba Ativos (com `afiliados.prestar_contas` **e** `afiliados.consultar`, porque o modal lê o extrato em aberto); depois da prestação, aviso, lista, cartões e gaveta (se aberta) atualizados

**Checkpoint**: quickstart §5 passa.

---

## Phase 8: User Story 6 - Copiar o link, excluir e restaurar (Priority: P2)

**Goal**: link pelo código, exclusão com aviso e senha, lixeira com restauração.

**Independent Test**: quickstart §6.

- [X] T033 [US6] Criar `app/Http/Requests/ExcluirAfiliadoRequest.php` (R-07): `password` `['required', 'string', regra que confere com Hash::check a senha do usuário logado e falha com "Senha incorreta."]`, mensagens "Confirme com a sua senha."; em `app/Http/Controllers/AfiliadosController.php`, `destroy(ExcluirAfiliadoRequest $request, Afiliados $afiliado)` (validação antes da transação; docblock cita a senha, spec 022 FR-029)
- [X] T034 [P] [US6] Criar `resources/js/components/AffiliateDeleteModal/index.jsx` e `styles.jsx`: `ModalHeader` (ícone `delete_outline`, "Excluir afiliado", "O afiliado vai para a lixeira e pode ser restaurado."); aviso "Este afiliado tem R$ X a receber." quando `valor_a_receber` ≠ 0 (ou o saldo negativo); campo senha; Cancelar e Excluir; `DELETE /afiliados/{id}` com `{ password }` no corpo; "Senha incorreta." no campo
- [X] T035 [P] [US6] Criar `resources/js/components/AffiliateRestoreModal/index.jsx` e `styles.jsx` (R-11, no padrão de `RestoreConflictModal`, sem alterá-lo): recebe `{ afiliado, campos }` (de `errors` do 422), mostra um campo por conflito (login, e-mail, CPF, código) com a mensagem do servidor; Cancelar e Restaurar; `POST /afiliados/{id}/restaurar` com os valores novos
- [X] T036 [US6] Na página `resources/js/pages/PanelAffiliates/index.jsx`: "Copiar link" (`<window.location.origin>/?user=CODIGO` por `navigator.clipboard`, aviso "Link copiado." com o alerta de não Ativo; sem permissão da área de transferência, `ConfirmModal` sem alteração, com o link num campo só leitura passado na `mensagem`) no menu e na gaveta; "Excluir" (com `afiliados.excluir`) abre o `AffiliateDeleteModal`; aba Lixeira com "Restaurar" (`ConfirmModal`; com 422 de conflito, abre o `AffiliateRestoreModal`) e a coluna Excluído em; sem "Excluir para sempre"

**Checkpoint**: quickstart §6 passa.

---

## Phase 9: User Story 7 - Perfil no cadastro público e em "Meus dados" (Priority: P2)

**Goal**: o afiliado informa e altera rede, link, seguidores e "sobre você".

**Independent Test**: quickstart §7.

- [X] T037 [US7] Em `app/Http/Requests/CadastroPublicoAfiliadoRequest.php`: acrescentar `regras_perfil_afiliado('required')`; em `app/Http/Requests/UpdateMeusDadosAfiliadoRequest.php`: acrescentar `regras_perfil_afiliado('sometimes', $this->user())`; em `app/Http/Resources/MeusDadosAfiliadoResource.php`: devolver `rede_social`, `link_perfil`, `quantidade_seguidores` e `sobre`; atualizar os docblocks (spec 022, FR-017)
- [X] T038 [P] [US7] Em `resources/js/components/AffiliateSignupModal/index.jsx` (e `styles.jsx` se precisar): incluir `AffiliateProfileFields` (`aparencia="publico"`, rede, link e seguidores obrigatórios) depois do PIX, no visual atual do modal, com erro por campo
- [X] T039 [P] [US7] Em `resources/js/components/AffiliateProfileModal/index.jsx` (e `styles.jsx` se precisar): incluir `AffiliateProfileFields` editáveis com os valores de `GET /area-afiliado/meus-dados`, enviados no `PATCH`

**Checkpoint**: quickstart §7 passa.

---

## Phase 10: Polish & Cross-Cutting Concerns

- [X] T040 [P] Em `database/seeders/AfiliadosSeeder.php` e `database/seeders/AfiliadosVolumeSeeder.php`: preencher `rede_social`, `link_perfil` e `quantidade_seguidores` nos afiliados criados (alguns em análise sem regra, para a aba Em análise)
- [X] T041 Regenerar `docs/postman/wssports_api.postman_collection.json` substituindo o arquivo (constituição): `GET /afiliados/resumo`, `grupo` em `GET /afiliados`, campos novos em `POST/PATCH /afiliados`, aprovar com os três campos, `DELETE /afiliados/{afiliado}` com `password`, cadastro público e meus dados com os campos de perfil; sem `GET/PUT /configuracoes/afiliados`; mantendo `base_url` e `token`
- [X] T042 Atualizar os artefatos das specs alteradas (plan.md, "Artefatos de specs anteriores"): em `specs/012-afiliados/spec.md`, nota "Session 2026-10-27 (alterado pela spec 022)" em Clarifications e ajuste de FR-008, FR-010, FR-011, FR-014, FR-015, FR-048, FR-051, FR-053 e FR-056; em `specs/012-afiliados/contracts/api.md`, §1.1, §1.10, §2.3, §2.7, §2.8, §2.14 e remoção do §3; em `specs/021-painel-configuracoes/spec.md`, nota em Clarifications e FR-028 sem a seção Afiliados
- [ ] T043 Executar o [quickstart.md](quickstart.md) inteiro (§1 a §10), inclusive 360 px e o `AfiliadosVolumeSeeder` (2 s), e corrigir o que falhar
- [X] T044 Revisão de legibilidade de todo o código alterado na spec (Princípio V): nomes, duplicação (ex.: regra de senha de `ExcluirAfiliadoRequest` x `PrestacaoContasAfiliadoRequest`), métodos longos, `use` sem uso, comentários desatualizados; registrar o resultado em `specs/022-painel-afiliados/tasks.md`

---

## Dependencies & Execution Order

- **Setup (T001–T003)** → **Foundational (T004–T013)** → histórias.
- **US1 (T014–T018)** é o MVP e base das demais (lista e tabela).
- **US2 (T019–T022)**, **US3 (T023–T026)**, **US4 (T027–T030)**, **US5 (T031–T032)** e **US6
  (T033–T036)** dependem de US1 na página (`PanelAffiliates`, `AffiliatesTable`); entre si, só
  disputam o arquivo da página (as tarefas de página são sequenciais).
- **US5** usa `meta.soma` de T027 (US4).
- **US7 (T037–T039)** depende só de T007 e T020 (`AffiliateProfileFields`); pode ser feita junto
  com US2.
- **Polish (T040–T044)** no fim; T041 depois de todas as rotas.

### Parallel Opportunities

- T001, T002 e T003 juntos.
- T010 e T011 junto com T004–T009.
- US1: T016 e T017 em paralelo, depois T018.
- Componentes [P] de histórias diferentes (T020, T025 — aprovação e recusa —, T028, T031, T034, T035, T038, T039) podem
  ser feitos em paralelo; as tarefas na página `PanelAffiliates` não.

### Parallel Example: User Story 1

```text
T016 AffiliatesFilters   |  T017 AffiliatesTable
                 ↓
           T018 página
```

## Implementation Strategy

1. **MVP**: Setup + Foundational + US1 → a banca consulta afiliados, cartões e rede pela tela
   (quickstart §1).
2. **Operação P1**: US2 (cadastro/edição) → US3 (aprovação) → US4 (gaveta/regra) → US5 (prestação).
3. **P2**: US6 (link, exclusão, lixeira) e US7 (perfil no público e em Meus dados).
4. **Fechamento**: seeders, Postman, artefatos das specs 012 e 021, quickstart completo e revisão.

---

## Notas da implementação (2026-10-27)

- **Princípio IV**: a lista de arquivos do plan.md foi tomada como confirmada quando o responsável
  rodou `/speckit-implement`.
- **Migrations**: os prefixos `2026_10_27_000001` a `000004` já existiam; as desta spec ficaram como
  `2026_10_27_000005_add_perfil_afiliados_table.php` e `2026_10_27_000006_remove_afiliados_configuracoes_table.php`
  (plan.md e tarefas atualizados).
- **Exclusão (T033)**: o `destroy` atual não conferia a rede; passou a usar `garantir_alcance`, além
  da senha (FR-002 e FR-029).
- **Aprovação (T024)**: a checagem de Admin ficou no `authorize()` do `AprovarAfiliadoRequest`, para o
  Supervisor receber 404 antes da validação (antes recebia 422); a recusa confere no controller.
- **Regra inicial (T006/T023)**: `CadastroAfiliados::criar` só chama `definir_inicial` quando há
  autor (cadastro pelo painel); o cadastro público fica sem regra.
- **T038/T039**: o cadastro público e "Meus dados" ganharam os quatro campos com o helper de campo
  de cada modal (visual próprio de cada um), em vez do `AffiliateProfileFields`, que ficou só nos
  modais do painel; os dois reaproveitam dele `MAXIMO_SOBRE` e `so_digitos_seguidores`. Em
  `AffiliateSignupModal/styles.jsx`, o `Label` passou a estilizar também o `textarea`.
- **T040**: o `AfiliadosVolumeSeeder` só cria lançamentos (não afiliados) e não mudou.
- **T041**: a coleção não tem gerador no projeto; foi atualizada por script (substituindo o arquivo),
  só na pasta de afiliados e na área do afiliado.
- **T043 (pendente)**: validado pela API no banco local (resumo, grupo, aprovar/recusar só Admin,
  exclusão com senha, `meta.soma`, campos obrigatórios) e `vite build` sem erros; falta o roteiro
  manual das telas no navegador (§1 a §10 do quickstart), inclusive 360 px.

## Revisão de legibilidade (T044)

- Revisado todo o código alterado: nomes em snake_case português, comentários atualizados, `use` sem
  uso removidos (`AlcanceHierarquia` no `AfiliadosController`; `Funcao` e `Configuracoes` no
  `RegraComissaoAfiliados`; `TipoGanhoAfiliado` no `Configuracoes`). Filtros da lista e do resumo
  unificados em `REGRAS_FILTROS`/`mensagens_filtros()` e em `ListagemAfiliados::filtrar_gestao`.
- Duplicação conhecida, **não refatorada** (Princípio IV): a regra de senha do usuário logado está
  igual em `ExcluirAfiliadoRequest` e `PrestacaoContasAfiliadoRequest`. Extrair para um concern
  exigiria alterar o `PrestacaoContasAfiliadoRequest`, fora da lista do plano — fica como sugestão.
