---

description: "Lista de tarefas da spec 019 — Painel: Clientes"
---

# Tasks: Painel — Clientes (cadastros, saques, ranking, lixeira, exportação e notificações)

**Input**: Design documents from `specs/019-painel-clientes/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md),
[data-model.md](data-model.md), [contracts/api.md](contracts/api.md),
[contracts/paginas.md](contracts/paginas.md), [quickstart.md](quickstart.md)

**Tests**: nenhum teste automatizado (constituição). A validação é manual pelo
[quickstart.md](quickstart.md).

**Organization**: tarefas agrupadas por história da spec. Cada tarefa cita os arquivos; arquivos
existentes só os listados no plan.md (Princípio IV, confirmados pelo responsável em 2026-10-22, com
P-1: Caixa online da Dashboard não muda, e P-2: Supervisor só vê os afiliados da rede). Ao concluir cada tarefa, revisão de legibilidade (Princípio V). Valores em bcmath, consultas pelo
query builder com parâmetros, comentários e mensagens em português, nomes em snake_case. Saques,
liquidação e push só pelos services existentes, mudando apenas o que o plano lista.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: pode rodar em paralelo (arquivos diferentes, sem depender de tarefa incompleta)
- **[Story]**: história da spec (US1 a US7)

---

## Phase 1: Setup

**Purpose**: estrutura do banco (data-model §1 a §3).

- [X] T001 [P] Criar `database/migrations/2026_10_25_000001_create_clientes_antecipacoes_table.php` (data-model §1.1): `clientes_id` FK `clientes`, `usuarios_id` FK `usuarios`, `quantidade_premios` unsigned int, `valor_premios` decimal(15,2), `quantidade_encerramentos` unsigned int, `valor_encerramentos` decimal(15,2), `ip` varchar(45) nulo, `timestamps()`, `softDeletes()`, índice `(clientes_id, created_at)`; todas as colunas com `->comment()` em português (ex.: "Soma creditada das apostas, em reais")
- [X] T002 [P] Criar `database/migrations/2026_10_25_000002_create_clientes_notificacoes_envios_table.php` (data-model §1.2): `usuarios_id` FK, `publico` varchar(20) ("Público do envio: Todos, Por ID ou Sem interagir"), `clientes_ids` json nulo, `dias_sem_interagir` unsigned smallint nulo ("1 a 365"), `titulo` varchar(50), `mensagem` varchar(150), `alcancados` unsigned int, `entregues` e `falhas` unsigned int default 0, `concluido_em` timestamp nulo, `timestamps()`, `softDeletes()`, índice `(created_at)`; comentários em todas as colunas
- [X] T003 Criar `database/migrations/2026_10_25_000003_add_avisos_banca_notificacoes_configuracoes.php` (data-model §2): `clientes_inscricoes_notificacoes.receber_avisos_banca` boolean default true ("Se o aparelho recebe os avisos manuais da banca (tipo Aviso da banca)"); `clientes_notificacoes.clientes_notificacoes_envios_id` FK nula para `clientes_notificacoes_envios` ("Envio manual que gerou o aviso; nulo nos avisos automáticos"); `configuracoes.limite_notificacoes_manuais_dia` unsigned tinyint default 3 ("Máximo de envios manuais de notificação push por dia, para a banca toda (1 a 50)"); `down()` remove as três
- [X] T004 [P] Criar `database/migrations/2026_10_25_000004_add_indices_painel_clientes.php` (data-model §3, R-14): índices `depositos(clientes_id, situacao, pago_em)`, `saques(clientes_id, situacao, pago_em)`, `clientes_transacoes(clientes_id, origem, carteira)`, `clientes(afiliados_id)`, `afiliados(usuarios_id)`, cada um só se `! Schema::hasIndex(...)` nas colunas; `down()` remove só os criados aqui

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: enums, models, permissões, alcance por rede, consulta de clientes, página e menu.

**⚠️ CRITICAL**: nenhuma história começa antes desta fase.

- [X] T005 [P] Enums (data-model §4): em `app/Enums/TipoNotificacao.php` o caso `AvisoBanca = 'Aviso da banca'` com `coluna_preferencia()` → `receber_avisos_banca` (não é de jogo); em `app/Enums/OrigemApuracao.php` o caso `Antecipação = 'Antecipação de pendentes'`; criar `app/Enums/PublicoNotificacao.php` com `Todos = 'Todos'`, `PorId = 'Por ID'`, `SemInteragir = 'Sem interagir'`
- [X] T006 [P] Models: criar `app/Models/ClientesAntecipacoes.php` e `app/Models/ClientesNotificacoesEnvios.php` (`SoftDeletes`, `fillable`, `casts` de decimais como string, `clientes_ids` array, `publico` enum, relações `cliente`/`usuario`/`autor`); em `app/Models/Configuracoes.php` acrescentar `limite_notificacoes_manuais_dia` ao `fillable` e cast int; em `app/Models/ClientesInscricoesNotificacoes.php` `receber_avisos_banca` (fillable, cast bool); em `app/Models/ClientesNotificacoes.php` `clientes_notificacoes_envios_id` e a relação `envio()`
- [X] T007 Em `app/Enums/Funcao.php` (R-12): acrescentar `clientes.ver_ranking`, `clientes.exportar`, `clientes.enviar_notificacao` e `clientes.antecipar_pendentes` em `PERMISSOES_CLIENTES`, e as três últimas em `PERMISSOES_CLIENTES_RESTRITAS`; atualizar os docblocks. `PapeisPermissoesSeeder` não muda (já espalha `PERMISSOES_CLIENTES`)
- [X] T008 Criar a migration de dados `database/migrations/2026_10_25_000005_criar_permissoes_clientes_painel.php` no padrão de `2026_10_24_000001_criar_permissoes_apostas_painel.php`: cria as 4 permissões no guard `api` e dá a cada usuário (`withTrashed`) as de `permissoes_padrao()` da função dele; `down()` remove as 4 (FR-036)
- [X] T009 [P] Em `resources/js/utils/permissions.js`, grupo "Clientes": `clientes.ver_ranking` ("Ver ranking" — "Abre o ranking dos clientes da rede."), `clientes.exportar` ("Baixar clientes" — "Baixa a planilha das abas de clientes."), `clientes.enviar_notificacao` ("Enviar notificação" — "Envia avisos push aos clientes inscritos."), `clientes.antecipar_pendentes` ("Antecipar pendentes" — "Libera prêmios e encerramentos antes da janela de segurança.")
- [X] T010 Criar `app/Services/AlcanceClientes.php` (R-02): `ids_gerentes(Usuarios): ?array` (Admin `null`; Gerente `[id]`; Supervisor os gerentes de `ids_sub_hierarquia_com_excluidos()` com função Gerente; Vendedor `[]`), `subconsulta_ids(Usuarios): ?QueryBuilder` (`clientes` com `withTrashed` join `afiliados` com `withTrashed`, `afiliados.usuarios_id in ids_gerentes`), `aplicar(Builder|EloquentBuilder $consulta, Usuarios, string $coluna = 'clientes.id')`, `contem(Usuarios, Clientes|int): bool`, `garantir(Usuarios, Clientes): void` → 404 "Cliente não encontrado."; docblock com a regra do FR-003 (a rede segue o afiliado)
- [X] T011 Criar `app/Http/Controllers/Concerns/GarantirAlcanceCliente.php` com `garantir_cliente(Request, Clientes)` e `aplicar_alcance_cliente(Request, Builder, string $coluna)`, chamando `AlcanceClientes` (estilo de `GarantirAlcanceAfiliado`)
- [X] T012 Criar `app/Services/FiltrosClientes.php` (R-05): mover de `ClientesController::listar` as regras de validação, a busca agrupada (`aplicar_busca`) e os filtros atuais para `consulta(Usuarios, Builder $base, array $filtros): Builder`, aplicando `AlcanceClientes::aplicar` e o `addSelect('saldo_liquido')` (subconsultas: depósitos `Aprovado`/`Estornado` com `pago_em` não nulo `SUM(valor - valor_estornado)` + `clientes_transacoes` origem `Ajuste manual` tipo `Crédito` carteira `Saldo` − saques `Pago` − ajustes `Débito` carteira `Saldo`, sem `deleted_at`); `regras()` e `mensagens()` públicas para a planilha e o público de notificação. Alterar `app/Http/Controllers/ClientesController.php` para usar o service em `index` e `excluidos` (mesma resposta de antes + `saldo_liquido`)
- [X] T013 Em `app/Http/Resources/ClientesResource.php`: `saldo_liquido` (string com duas casas por `bcadd`) quando o atributo vier na consulta (`$this->when(isset($this->saldo_liquido), ...)`)
- [X] T014 Registrar `GET painel/clientes` em `routes/web.php` (nome `painel.clientes`, comentário "clientes do painel (spec 019)") e a ação `clientes()` em `app/Http/Controllers/PainelController.php` (Inertia `PanelClients`)
- [X] T015 [P] Em `resources/js/components/PanelShell/index.jsx`: item "Clientes" (chave `clientes`, ícone `credit_card`, `href: '/painel/clientes'`) entre "Caixa" e "Apostas", visível com `clientes.listar` (contracts/paginas.md §1)
- [X] T016 Criar `resources/js/hooks/useClientesPainel.js` (R-13): estado no endereço (`aba`, `busca`, filtros, `ordenar_por`, `direcao`, `page`) com `router.get(..., { preserveState: true, replace: true })`, `mudar(parcial)` que volta à página 1, `parametros_api(filtros)`, carregamento por aba (lista e, em Saques, resumo) com token do `usePainel`, `recarregar()` sem perder a posição; padrão `ordenar_por=created_at&direcao=desc` em Cadastros
- [X] T017 Criar a página `resources/js/pages/PanelClients/index.jsx` e `styles.jsx`: `PanelShell` título "Clientes", barra com "Clientes — total N", busca com atraso de 400 ms, botões Filtros/Baixar planilha/Notificação/Atualizar conforme permissão, `PanelTabs` (Cadastros, Saques, Ranking, Lixeira, cada uma só com a permissão do FR-002), áreas com esqueleto, vazio e erro com "Tentar novamente" (FR-039); sem `clientes.listar`, volta para `/painel` (contracts/paginas.md §1)

**Checkpoint**: `php artisan migrate` cria tabelas, colunas, índices e permissões; o menu mostra "Clientes" e a página abre com as abas permitidas.

---

## Phase 3: User Story 1 - Consultar e filtrar os clientes (Priority: P1) 🎯 MVP

**Goal**: lista de Cadastros da rede com saldo líquido, busca e filtros do antigo.

**Independent Test**: quickstart §1 (itens 1 e 2) e §2.

### Implementation for User Story 1

- [X] T018 [US1] Em `app/Services/FiltrosClientes.php`, filtros novos (R-05, contracts/api.md §1.1): `deposito` in `ja_depositou,nunca_depositou` (`whereExists`/`whereNotExists` de depósito `Aprovado`/`Estornado` com `pago_em`); `pix_em_aberto` boolean (último depósito do cliente por `MAX(id)` com meio Pix e situação `Pendente` ou `Expirado`); `modalidade` in `esportes,cassino` e `aniversariantes` boolean exigindo `periodo_de`/`periodo_ate` (`date_format:Y-m-d`, `ate` ≥ `de`, até 366 dias; mensagens "Informe o período do filtro." e "O período do filtro pode ter no máximo 366 dias."); modalidade = `whereExists` de aposta `Ativa` do cliente ou de `cassinos_rodadas` do cliente no período (Brasília → UTC por `FusoSistema`); aniversariantes por `DATE_FORMAT(data_nascimento, '%m%d')` entre `mmdd` de início e fim, com `>= início OR <= fim` quando atravessa o ano e todos com nascimento quando o período tem 366 dias; `afiliados_id` conferido pelo alcance (fora → lista vazia)
- [X] T019 [P] [US1] Criar `resources/js/components/ClientsTable/index.jsx` e `styles.jsx` (contracts/paginas.md §2): colunas ID, NOME, CONTATO, SALDO, PROMOÇÃO, SALDO LÍQUIDO (verde/cinza/vermelho), ATIVO, CRIADO EM, AÇÕES; ordenação pelos títulos; tocar na linha chama `ao_abrir`; `ActionsMenu` com as ações recebidas por prop conforme permissão; cartões no celular (nome e ID, saldo e saldo líquido em destaque, resto ao expandir); prop `lixeira` para mostrar EXCLUÍDO EM e só Restaurar (reuso na US4)
- [X] T020 [P] [US1] Criar `resources/js/components/AffiliatePicker/index.jsx` e `styles.jsx`: busca com resultados em `GET /afiliados?busca=` (até 20), mostra nome e código, valor = `afiliados_id`; sem texto livre (constituição, "Campos de itens existentes")
- [X] T021 [US1] Criar `resources/js/components/ClientsFilters/index.jsx` e `styles.jsx` (contracts/paginas.md §3): painel lateral (tela cheia no celular, `ModalHeader` `filter_alt` "Filtros" "Refine a lista de clientes."), com os filtros atuais e os novos; Modalidade e Aniversariantes desabilitados sem período ("Escolha o período"); Aplicar e Limpar filtros; exportar `rotulos_filtros(filtros)` para o `ActiveFilters`
- [X] T022 [US1] Na página `resources/js/pages/PanelClients/index.jsx`: aba Cadastros com `ClientsTable`, `ClientsFilters`, `ActiveFilters`, `Pagination`, total no título e vazio "Nenhum cliente encontrado. Troque a busca ou os filtros."

**Checkpoint**: US1 validada pelo quickstart §2, com o alcance do §1.

---

## Phase 4: User Story 2 - Ver e cuidar de um cliente (Priority: P1)

**Goal**: detalhe em gaveta e as ações sobre o cliente, com o alcance em todas as rotas de cliente.

**Independent Test**: quickstart §1 (item 3) e §3.

### Implementation for User Story 2

- [X] T023 [US2] Alcance nas rotas existentes de cliente (FR-003a, R-03), usando `GarantirAlcanceCliente`: `ClientesController` (`show`, `update`, `alterar_situacao`, `destroy`), `ClientesTransacoesController` (`index`, `store`), `ClientesConfiguracoesController` (`show`, `update`), `ClientesMeiosPagamentoController` (todas) e `AcessosAssistidosController::store` em `app/Http/Controllers/`; fora da rede → 404 "Cliente não encontrado."
- [X] T024 [US2] Em `app/Http/Requests/UpdateClientesRequest.php` (R-15, FR-003b): para Supervisor e Gerente, `afiliados_id` `required` e com afiliado cujo `usuarios_id` está em `AlcanceClientes::ids_gerentes` ("Escolha um afiliado da sua rede."); Admin mantém `nullable` e qualquer afiliado
- [X] T025 [US2] Em `app/Http/Requests/ClientesTransacoesRequest.php` (R-06, FR-018): `senha` `required` com `regra_senha()` do trait `App\Http\Requests\Concerns\ConfirmacaoCaixa` ("Senha incorreta."), validada antes de qualquer gravação; `senha` não vai para a transação
- [X] T026 [P] [US2] Criar `resources/js/components/ClientFormModal/index.jsx` e `styles.jsx` (contracts/paginas.md §5): `ModalHeader` `edit` "Editar cliente" "Altere os dados do cliente."; nome, DDI/telefone, e-mail, CPF, nascimento, gênero, `AffiliatePicker` (obrigatório para Supervisor e Gerente), nova senha opcional; erros do servidor nos campos
- [X] T027 [P] [US2] Criar `resources/js/components/BalanceAdjustModal/index.jsx` e `styles.jsx`: `ModalHeader` `attach_money` "Ajustar saldo" "Crédito ou débito manual no saldo do cliente."; carteira em chips (Saldo / Promoção esportes / Promoção cassino), tipo (Crédito / Débito), valor com `useCampoValor`, motivo (até 255, contador), sua senha; saldo atual e saldo depois; `POST /clientes/{id}/transacoes`
- [X] T028 [P] [US2] Criar `resources/js/components/ClientSettingsForm/index.jsx` e `styles.jsx`: campos de `ClientesConfiguracoesRequest` (aposta, ao vivo, outros esportes, cancelar, promoção, bloquear saque, quantidades, valores com `useCampoValor`, odds, encerrar aposta) e esportes em chips da lista do site, com os estilos do `BettingRulesFields` (ajustado na implementação: o `BettingRulesFields` tem os grupos do vendedor fixos); Salvar com `clientes.editar_configuracoes` (`PUT /clientes/{id}/configuracoes`)
- [X] T029 [P] [US2] Criar `resources/js/components/PaymentMethodsList/index.jsx` e `styles.jsx`: lista das chaves Pix (`GET /clientes/{id}/meios-pagamento`), adicionar, editar e excluir pelas rotas existentes, com `ConfirmModal` na exclusão
- [X] T030 [US2] Criar `resources/js/components/ClientDrawer/index.jsx` e `styles.jsx` (contracts/paginas.md §4): gaveta `min(560px, 100vw)`, altura fixa, `ModalHeader` `person` com o nome; abas Dados (com Editar, Ajustar saldo, Antecipar pendentes, Acessar conta, Ver apostas conforme permissão), Configurações (`ClientSettingsForm`), Pagamentos (`PaymentMethodsList`) e Extrato (componente `ClientStatement` com `GET /clientes/{id}/transacoes`; ajustado na implementação: o `StatementTable` é do extrato da área do cliente, com outro formato); atualiza a lista ao fechar se algo mudou
- [X] T031 [US2] Na página `PanelClients`: abrir a gaveta pela linha; ações do menu Ver detalhe, Editar, Ativar/Desativar (`ConfirmModal`, "As sessões do cliente deixam de valer na hora."), Ajustar saldo, Acessar conta (`POST /clientes/{id}/acesso-assistido` e abrir a área do cliente como na spec 007), Ver apostas (`/painel/apostas?clientes_id={id}&cliente={nome}&de={1º dia do mês}&ate={hoje}`), Excluir (só com `clientes.excluir`, US4); retorno com aviso e recarga sem perder posição (FR-040)
- [X] T032 [US2] Filtro por cliente na tela Apostas (R-11, FR-016): `clientes_id` inteiro opcional em `app/Http/Requests/ApostasFiltrosRequest.php` (e no `filtros()`); em `app/Services/ListagemApostas.php::consulta`, só para Admin e Supervisor, conferir `AlcanceClientes::contem` (senão 403 "Você não tem permissão para esta ação.") e filtrar `apostas.clientes_id`
- [X] T033 [US2] Em `resources/js/hooks/useApostasPainel.js` e `resources/js/pages/PanelBets/index.jsx`: ler `clientes_id` e `cliente` do endereço, enviar `clientes_id` à API e mostrar a etiqueta removível "Cliente: {nome}" no `ActiveFilters`

**Checkpoint**: US2 validada pelo quickstart §3.

---

## Phase 5: User Story 3 - Dar baixa nos saques (Priority: P1)

**Goal**: fila de saques da rede com busca, período, cartões e decisão.

**Independent Test**: quickstart §1 (item 4) e §4.

### Implementation for User Story 3

- [X] T034 [US3] Criar `app/Services/ListagemSaques.php` (R-07) com `consulta(Usuarios, array $filtros): Builder` (a consulta que hoje está no `SaquesController::index`, com a mesma ordem por situação) e `resumo(Usuarios, array $filtros): array`; em `app/Http/Controllers/SaquesController.php` (contracts/api.md §3) o `index` passa a usá-lo; alcance por `AlcanceClientes::aplicar(..., 'saques.clientes_id')`; filtros `busca` (string até 60: nome do cliente por trecho ou ID exato) e `de`/`ate` (`date_format:Y-m-d`, até 366 dias, pela data do pedido em Brasília); `pagar` e `recusar` garantem o saque no alcance (fora → 404 "Saque não encontrado."); `with('cliente')` passa a trazer o saldo
- [X] T035 [US3] Ação `resumo` em `app/Http/Controllers/SaquesController.php` (pelo `ListagemSaques::resumo`) e rota `GET saques/resumo` (antes de `saques/{saque}`) em `routes/api.php`: `pendentes` e `processando` (quantidade e soma, sem período) e `pagos` por `pago_em` só com `de`/`ate` (senão `null`), com alcance e `busca` (contracts/api.md §3.2)
- [X] T036 [P] [US3] Em `app/Http/Resources/SaquesResource.php`: `cliente.telefone` mascarado com `ClientesResource::mascarar_telefone` quando `ClientesResource::deve_mascarar($request)`; `cliente.saldo`
- [X] T037 [P] [US3] Criar `resources/js/components/WithdrawalsTable/index.jsx` e `styles.jsx` (contracts/paginas.md §6): colunas CLIENTE, PIX, VALOR, SALDO DO CLIENTE, SITUAÇÃO (selo), PEDIDO EM, DECISÃO; ações só em Pendente (Dar baixa, Pagar pelo Mercado Pago quando vinculado, Recusar); cartões no celular
- [X] T038 [P] [US3] Criar `resources/js/components/WithdrawalDecisionModal/index.jsx` e `styles.jsx`: `ModalHeader` `block` "Recusar saque" "O valor volta para o saldo do cliente."; motivo obrigatório (até 255); `POST /saques/{id}/recusar`
- [X] T039 [US3] Na página `PanelClients`: aba Saques com chips de situação (Pendentes padrão), `PeriodFilter` (vazio em Pendentes), busca, `StatCard` Pendentes/Processando/Pagos, `WithdrawalsTable`, `Pagination`; Dar baixa e Pagar pelo Mercado Pago com `ConfirmModal` (`POST /saques/{id}/pagar` com `forma`); erro "Este saque já foi decidido." recarrega a lista

**Checkpoint**: US3 validada pelo quickstart §4.

---

## Phase 6: User Story 4 - Excluir e restaurar clientes (Priority: P2)

**Goal**: excluir pela lista e restaurar pela Lixeira, com conflito de dados únicos.

**Independent Test**: quickstart §5.

### Implementation for User Story 4

- [X] T040 [US4] Em `app/Http/Controllers/ClientesController.php`: `excluidos` com `FiltrosClientes` (busca e paginação, `deleted_at` desc) e `restaurar` com o alcance (o cliente excluído da rede; fora → 404)
- [X] T041 [P] [US4] Criar `resources/js/components/RestoreConflictModal/index.jsx` e `styles.jsx`: `ModalHeader` `warning` "Dados em uso" "Alguns dados já estão em uso por outro cliente."; um campo por chave de `errors` (telefone com DDI, e-mail, CPF) e Restaurar (`POST /clientes/{id}/restaurar` com os valores novos)
- [X] T042 [US4] Na página `PanelClients`: aba Lixeira (`ClientsTable` com `lixeira`, `GET /clientes/excluidos`), Restaurar com `ConfirmModal` e, no 422 com `errors`, o `RestoreConflictModal`; ação Excluir na aba Cadastros com `ConfirmModal` (`DELETE /clientes/{id}`)

**Checkpoint**: US4 validada pelo quickstart §5.

---

## Phase 7: User Story 5 - Ranking de clientes (Priority: P2)

**Goal**: ranking por período e critério, com posição, na rede do usuário.

**Independent Test**: quickstart §6.

### Implementation for User Story 5

- [X] T043 [US5] Em `app/Services/CaixaOnline.php` e `app/Services/RelatorioCassino.php`: parâmetro opcional `?QueryBuilder $restringir_clientes` nos métodos usados pelo Caixa clientes (`calcular`, `somas_periodo`), aplicando `whereIn('clientes_id', $restringir_clientes)`; sem ele, o resultado é o atual (Dashboard e relatório do cassino iguais). Em `app/Services/CaixaClientes.php`: aceitar a mesma restrição em `resumo` e `clientes` e incluir `entradas_cassino` em cada linha
- [X] T044 [US5] Criar `app/Http/Requests/RankingClientesRequest.php`: `periodo` in `24h,7d,30d,90d,personalizado` (padrão `30d`), `de`/`ate` obrigatórios com `personalizado` (até 366 dias), `criterio` in `depositos,valor_apostado,resultado,saques` (padrão `depositos`), `por_pagina` 1 a 100 (padrão 20); método `intervalo()` (24h = agora − 24 h; N dias = agora − N dias; personalizado = dias de Brasília em UTC)
- [X] T045 [US5] Criar `app/Services/RankingClientes.php` (R-08): `Depósitos` e `Saques pagos` agrupados por `clientes_id` com `COUNT`/`SUM` no período (`pago_em`, depósitos `valor - valor_estornado` em `Aprovado`/`Estornado`), ordenados pelo valor e paginados no banco; `Valor apostado` por `UNION ALL` das entradas de esportes com saldo real (apostas `Ativa` com `forma_pagamento = Saldo` e `clientes_id`) e das rodadas de cassino com carteira `Saldo` no período, agrupado e ordenado pelo total; `Resultado` por `CaixaClientes::clientes(..., ordenar: 'resultado', direcao: 'desc')` com a restrição do alcance; sempre com `AlcanceClientes`; colunas comuns (saldo, saldo líquido, ativo) por uma consulta dos clientes da página; `posicao` contínua entre páginas
- [X] T046 [US5] Criar `app/Http/Resources/RankingClientesResource.php` e `app/Http/Controllers/ClientesRankingController.php` (`clientes.ver_ranking`) e a rota `GET clientes/ranking` antes de `clientes/{cliente}` em `routes/api.php` (contracts/api.md §4)
- [X] T047 [P] [US5] Criar `resources/js/components/RankingTable/index.jsx` e `styles.jsx` (contracts/paginas.md §7): posição com destaque 1º a 3º, colunas comuns e as do critério, resultado verde/vermelho, tocar abre o detalhe; cartões no celular (posição, nome, valor do critério)
- [X] T048 [US5] Na página `PanelClients`: aba Ranking com chips de período (Personalizado com de/até) e de critério, `RankingTable`, `Pagination`; tocar na linha abre o `ClientDrawer`

**Checkpoint**: US5 validada pelo quickstart §6.

---

## Phase 8: User Story 6 - Antecipar os pendentes de um cliente (Priority: P3)

**Goal**: creditar na hora prêmios e encerramentos na janela de segurança, com senha.

**Independent Test**: quickstart §7.

### Implementation for User Story 6

- [X] T049 [US6] Em `app/Services/LiquidacaoApostas.php`: `liquidar(Apostas $aposta, OrigemApuracao $origem, bool $ignorar_janela = false, ?Usuarios $autor = null)`; com `$ignorar_janela`, o crédito não espera a janela; `$autor` é repassado ao `SaldoClientes::creditar` (hoje `null`); sem os parâmetros, nada muda (comando `apostas:liquidar_premios` e apuração iguais)
- [X] T050 [US6] Em `app/Services/LiquidacaoEncerramentos.php`: extrair do `creditar` privado o método público `creditar_encerramento(int $id, ?Usuarios $autor = null): ?string`, com a mesma trava (aposta → encerramento) e o mesmo crédito, repassando o autor; `liquidar()` passa a chamá-lo (comportamento igual)
- [X] T051 [US6] Criar `app/Services/AntecipacaoPendentes.php` (R-06): `pendentes(Clientes): array` (apostas do cliente com `liquidacao_pendente = true` e diferença positiva por `LiquidacaoApostas::devido` − `valor_pago`; encerramentos `Registrado` com `creditar_em > now()`; quantidade e valor de cada grupo e total); `antecipar(Clientes, Usuarios $autor, ?string $ip): array` — uma transação por aposta (`lockForUpdate`, `liquidar(..., OrigemApuracao::Antecipação, ignorar_janela: true, autor: $autor)`) e por encerramento (`creditar_encerramento`), somando só o que foi de fato creditado; emite `ApostaAtualizada` como a rotina; grava `ClientesAntecipacoes`; sem nada creditado → 422 "Nenhum prêmio ou encerramento pendente."
- [X] T052 [US6] Criar `app/Http/Requests/AnteciparPendentesRequest.php` (`senha` obrigatória com `ConfirmacaoCaixa::regra_senha`, "Senha incorreta.") e `app/Http/Controllers/ClientesPendentesController.php` (`clientes.antecipar_pendentes`, alcance do cliente): `show` → `GET clientes/{cliente}/pendentes`, `store` → `POST clientes/{cliente}/antecipar` (resposta com `creditado`, grupos e `saldo`), rotas em `routes/api.php` (contracts/api.md §2)
- [X] T053 [P] [US6] Criar `resources/js/components/AnticipateModal/index.jsx` e `styles.jsx` (contracts/paginas.md §5): carrega `pendentes`; lista Prêmios e Encerramentos com quantidade e valor; aviso do antigo; sua senha; sem pendentes, a mensagem e só Fechar; sucesso "R$ X creditados. Saldo atual: R$ Y."
- [X] T054 [US6] Na página `PanelClients` e no `ClientDrawer`: ação "Antecipar pendentes" (`arrow_circle_up`) só com `clientes.antecipar_pendentes`, abrindo o `AnticipateModal` e recarregando a lista e o detalhe

**Checkpoint**: US6 validada pelo quickstart §7.

---

## Phase 9: User Story 7 - Exportar a lista e enviar notificação (Priority: P3)

**Goal**: planilha por aba e notificação push manual com limite diário.

**Independent Test**: quickstart §8.

### Implementation for User Story 7

- [X] T055 [US7] Criar `app/Services/ExportacaoClientes.php` (R-09): por aba (`cadastros`, `saques`, `ranking`, `lixeira`) a mesma consulta da tela (`FiltrosClientes`, `ListagemSaques`, `RankingClientes`), contagem antes (acima de 50.000 → 422 "O filtro tem N registros; o máximo por planilha é 50.000. Use filtros para reduzir."), linhas em blocos de 500, contato e CPF mascarados pela regra do `ClientesResource`, `PlanilhaPainel::gerar` com título, data de geração e filtros no cabeçalho e colunas em moeda
- [X] T056 [US7] Criar `app/Http/Controllers/ClientesExportacaoController.php` (`clientes.exportar` + a permissão da aba: `clientes.listar`, `saques.gerenciar`, `clientes.ver_ranking` ou `clientes.restaurar`) com `download(...)->deleteFileAfterSend()` (`clientes-{aba}-{AAAA-MM-DD}.xlsx`) e a rota `GET clientes/exportacao` antes de `clientes/{cliente}` em `routes/api.php`
- [X] T057 [US7] Em `app/Services/NotificacoesPush.php`: no `conteudo()`, para `AvisoBanca`, tag `aviso-{clientes_notificacoes_envios_id}` e URL `/`; `registrar` aceita o id do envio. Em `app/Services/InscricoesNotificacoes.php` acrescentar `receber_avisos_banca` à lista de preferências, e em `app/Http/Resources/InscricaoNotificacaoResource.php` devolvê-lo, como os demais `receber_*`
- [X] T058 [US7] Criar `app/Services/NotificacoesManuais.php` (R-10): `publico(Usuarios, array $dados): Builder` (clientes ativos, não excluídos, no alcance, com inscrição `receber_avisos_banca = true`; `Por ID` com os ids no alcance; `Sem interagir` = sem aposta, rodada de cassino nem depósito pago depois de agora − N dias); `contar`; `situacao_limite(): array` (`limite`, `enviados_hoje` com `alcancados > 0` desde 00:00 de Brasília, `restantes`); `enviar(Usuarios, array $dados): ClientesNotificacoesEnvios` numa transação com `lockForUpdate` em `configuracoes` (sem restantes → 422 "Limite de envios de hoje atingido."; zero alcançados → 422 "Nenhum cliente inscrito neste público."), gravando o envio e disparando o job depois do commit
- [X] T059 [US7] Criar `app/Jobs/EnviarNotificacoesManuais.php` (fila `EnviarNotificacaoPush::FILA`): percorre o público do envio em blocos de 500, `NotificacoesPush::registrar` com `TipoNotificacao::AvisoBanca` e chave `aviso-{envio_id}`, `enviar` cada aviso, soma `entregues`/`falhas` no envio e grava `concluido_em`
- [X] T060 [US7] Criar `app/Http/Requests/NotificacaoManualRequest.php` (`publico` enum `PublicoNotificacao`; `clientes_ids` array 1 a 500 inteiros com `Por ID`; `dias` 1 a 365 com `Sem interagir`; `titulo` obrigatório até 50; `mensagem` obrigatória até 150), `app/Http/Resources/ClientesNotificacoesEnviosResource.php` e `app/Http/Controllers/ClientesNotificacoesController.php` (`clientes.enviar_notificacao`): `limite` (GET), `alterar_limite` (PUT, só Admin, `limite` 1 a 50), `alcance` (POST), `store` (POST, 201); rotas `clientes/notificacoes/*` antes de `clientes/{cliente}` em `routes/api.php` (contracts/api.md §6)
- [X] T061 [P] [US7] Criar `resources/js/components/ClientPicker/index.jsx` e `styles.jsx`: busca de clientes (`GET /clientes?busca=`, até 20) com chips dos escolhidos (até 500)
- [X] T062 [P] [US7] Criar `resources/js/components/PushNotificationModal/index.jsx` e `styles.jsx` (contracts/paginas.md §9): `ModalHeader` `notifications` "Enviar notificação" "Aviso no celular dos clientes inscritos."; restantes hoje e, para o Admin, o limite editável; público em chips (Todos / Por ID com `ClientPicker` / Sem interagir há N dias); título 0/50, mensagem 0/150, prévia; "Alcançados: N" ao mudar o público; Enviar desabilitado com zero alcançados ou sem restantes; confirmação "Enviar para N clientes?"
- [X] T063 [US7] Na página `PanelClients`: botão Baixar planilha (com `clientes.exportar`; avisa antes acima de 50.000 pelo total da aba; lê o erro em blob como na `PanelBets`) e botão Notificação (com `clientes.enviar_notificacao`) abrindo o `PushNotificationModal`
- [X] T064 [P] [US7] Em `resources/js/components/NotificationSettingsModal/index.jsx`: item "Avisos da banca" — "Novidades e promoções enviadas pela banca" (`receber_avisos_banca`)

**Checkpoint**: US7 validada pelo quickstart §8.

---

## Phase 10: Rede nas telas existentes e afiliados (FR-003a, FR-003c, FR-003d)

**Purpose**: aplicar o alcance por rede no que já existe fora da tela Clientes e tornar o gerente do afiliado obrigatório.

- [X] T065 Alcance em `app/Http/Controllers/CotacoesPainelController.php` (`clientes`: só clientes da rede) e `app/Http/Controllers/PorcentagensClientesController.php` (`clientes_id` fora da rede → 404 "Cliente não encontrado.") (R-03)
- [X] T066 Alcance em `app/Http/Controllers/ApostasEncerramentosController.php`: lista e filtro `clientes_id` só de clientes da rede (R-03)
- [X] T067 Caixa clientes (FR-003c): em `app/Http/Controllers/CaixaClientesController.php` e `app/Services/ExportacaoCaixa.php`, passar a subconsulta de `AlcanceClientes` (Supervisor) às somas e linhas de `CaixaClientes` (T043); Admin sem restrição
- [X] T068 Apostas (FR-003c): em `app/Services/ListagemApostas.php::aplicar_alcance`, apostas do site só com `apostas.clientes_id` na subconsulta do alcance para o Supervisor (Admin, todas); em `app/Services/AlcanceApostas.php::alcanca`, aposta de cliente só quando `AlcanceClientes::contem` (Admin, todas; Supervisor e Gerente, os clientes da rede; o Gerente continua sem a lista de apostas de clientes, mas cancela ou edita pelo código as da rede) — lista, resumo, exportação, premiados, bilhete, cancelar, restaurar e palpites passam a respeitar a rede
- [X] T069 Afiliados — gerente obrigatório (R-04, FR-003d): em `app/Http/Requests/Concerns/DadosAfiliado.php::regra_gerente`, para o Supervisor exigir gerente da sub-hierarquia ("O gerente escolhido não é da sua rede."); `app/Http/Requests/StoreAfiliadosRequest.php` com `usuarios_id` `required` para Admin e Supervisor; `app/Http/Requests/UpdateAfiliadosRequest.php` `sometimes|required` ("O gerente responsável é obrigatório."); `app/Http/Requests/AprovarAfiliadoRequest.php` `required` quando o afiliado não tem gerente e `app/Services/CadastroAfiliados.php::aprovar` recusando aprovar sem gerente
- [X] T070 Afiliados — filtro e indicador: em `app/Services/ListagemAfiliados.php::consulta_gestao` o filtro `sem_gerente` (`whereNull('afiliados.usuarios_id')`), validado no `AfiliadosController::listar`; em `app/Http/Resources/AfiliadosResource.php` o campo `sem_gerente`
- [X] T071 Em `app/Http/Controllers/Concerns/GarantirAlcanceAfiliado.php`: o Supervisor passa a ver e agir só sobre os afiliados cujo gerente está na sub-hierarquia dele (Admin, todos); atualizar o docblock

---

## Phase 11: Polish & Cross-Cutting Concerns

- [X] T072 [P] Regenerar `docs/postman/wssports_api.postman_collection.json` (substituindo o arquivo) com as rotas novas (`clientes/ranking`, `clientes/exportacao`, `clientes/notificacoes/*`, `clientes/{cliente}/pendentes`, `clientes/{cliente}/antecipar`, `saques/resumo`) e os campos novos das alteradas (`senha` no ajuste, filtros de clientes e saques, `clientes_id` em apostas, `usuarios_id` obrigatório em afiliados, `receber_avisos_banca`)
- [X] T073 [P] Nota "Alterado pela spec 019" nos requisitos afetados de `specs/002-clientes/spec.md` (alcance, ajuste com senha), `specs/004-apostas/spec.md` (Gerente só cancela ou edita apostas de clientes da rede), `specs/007-painel-cliente-pagamentos/spec.md` (alcance dos saques e do acesso assistido), `specs/012-afiliados/spec.md` (gerente obrigatório e alcance do Supervisor), `specs/017-painel-caixa/spec.md` (Caixa clientes pela rede) e `specs/018-painel-apostas/spec.md` (apostas do site pela rede, filtro por cliente, "subir créditos" agora existe)
- [X] T074 Conferir responsividade a 360 px (sem rolagem horizontal, alvos ≥ 40 px), modais e gaveta pela constituição, e os estados de carregando/vazio/erro em todas as abas (FR-037 a FR-040)
- [X] T075 Executar o [quickstart.md](quickstart.md) inteiro e registrar o resultado em "Notas da implementação" neste arquivo; revisão de legibilidade final do conjunto (Princípio V)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)** → **Foundational (Phase 2)** → histórias.
- **US1 (Phase 3)**: depende só da Phase 2. É o MVP.
- **US2 (Phase 4)**: depende da US1 (a gaveta abre pela lista); T032/T033 só da Phase 2.
- **US3 (Phase 5)**: depende só da Phase 2 (aba independente).
- **US4 (Phase 6)**: usa o `ClientsTable` da US1 (T019).
- **US5 (Phase 7)**: depende da Phase 2; T043 também é usado pela Phase 10 (T067).
- **US6 (Phase 8)**: depende da US2 (gaveta) para a ação no detalhe; backend só da Phase 2.
- **US7 (Phase 9)**: exportação depende das consultas das abas (US1, US3, US4, US5); notificação só da Phase 2.
- **Phase 10**: T067 depende de T043; T068 de T010.
- **Polish (Phase 11)**: depois de tudo.

### Within Each User Story

- Backend (requests, services, controllers, rotas) antes da tela; componentes [P] em paralelo; a
  página por último.

### Parallel Opportunities

- Phase 1: T001, T002, T004 em paralelo; T003 depois de T002 (FK para `clientes_notificacoes_envios`).
- Phase 2: T005, T006, T009, T015 em paralelo.
- Componentes marcados [P] de cada história em paralelo entre si.
- US3, US5 e a parte de notificação da US7 podem andar em paralelo depois da Phase 2.

---

## Parallel Example: User Story 2

```text
T026 ClientFormModal · T027 BalanceAdjustModal · T028 ClientSettingsForm · T029 PaymentMethodsList
(em paralelo, depois de T023–T025)
```

---

## Implementation Strategy

### MVP First (User Story 1)

1. Phases 1 e 2 → 2. Phase 3 (US1) → 3. validar quickstart §1–§2 → entregar a lista de clientes da rede.

### Incremental Delivery

1. US1 (lista) → US2 (detalhe e ações) → US3 (saques) → US4 (lixeira) → US5 (ranking) → US6
   (antecipação) → US7 (planilha e notificação) → Phase 10 (rede nas telas existentes e afiliados)
   → Polish.
2. Cada etapa validada pela seção correspondente do quickstart antes da próxima.

---

## Notes

- Sem testes automatizados; nenhuma tarefa de teste.
- Toda rota criada ou alterada entra na coleção do Postman (T072).
- Mudanças fora da lista do plan.md exigem aviso e confirmação antes (Princípio IV).

## Notas da implementação (2026-10-22)

**Validação feita** (banco local; dados de teste sempre dentro de transação desfeita no fim):

- `php artisan migrate` → `migrate:rollback --step=5` → `migrate` sem erros; 12 permissões `clientes.*`
  e o padrão por função conferido (Admin e Supervisor com as 4 novas; Gerente só
  `clientes.ver_ranking`; Vendedor nenhuma).
- Pela pilha HTTP (token JWT de cada nível):
  - Admin vê os 13 clientes; Supervisor e Gerente, só o da rede deles; Vendedor recebe 403;
  - cliente fora da rede → 404 "Cliente não encontrado." (detalhe e pendentes);
  - Lixeira, Saques, notificação e Caixa clientes → 403 para o Gerente;
  - ajuste de saldo sem senha → "Informe a sua senha para confirmar."; com senha errada →
    "Senha incorreta.";
  - filtro `clientes_id` de cliente fora da rede na tela Apostas → 403 para o Supervisor;
  - planilhas das 4 abas geradas (xlsx), inclusive pelos serviços;
  - afiliado pelo Supervisor sem gerente, ou com gerente de fora da rede → "O gerente responsável é
    obrigatório.".
- Serviços:
  - filtros novos (depósito, Pix em aberto, modalidade, aniversariantes atravessando o ano);
  - saldo líquido;
  - os 4 critérios do ranking;
  - antecipação de um prêmio simulado: creditou uma vez, com o autor no extrato; a segunda vez
    recusou com "Nenhum prêmio ou encerramento pendente.";
  - notificação manual com uma inscrição de teste: contagem, registro do aviso, job e limite diário;
  - Caixa clientes restrito para o Supervisor, com a Dashboard igual.
- Desempenho (SC-008), com 100.013 clientes: lista, busca, filtros e ranking entre 21 e 671 ms
  para Admin e Supervisor.
- Chrome sem janela em `/painel/clientes`, 1366 px e 360 px, como Admin, Supervisor e Gerente:
  - abas por permissão;
  - gaveta com as 4 abas (560 px; tela cheia no celular);
  - painel de filtros abrindo;
  - sem erro de página e sem rolagem horizontal.
- `npm run build` sem erros. O Pint passou nos 71 arquivos PHP alterados ou criados; o único
  aviso, a ordem dos imports em `routes/api.php`, já existia antes desta spec.

**Ajustes na implementação** (artefatos atualizados):

- `CaixaExportacaoController` passa o solicitante à planilha do Caixa clientes, para ela respeitar a
  rede (FR-003c). Incluído na lista de arquivos do plan.md.
- O extrato do detalhe virou o componente `ClientStatement`, porque o `StatementTable` é da área do
  cliente e usa outro formato de dados.
- As configurações usam o `ClientSettingsForm` com os campos do cliente, porque o
  `BettingRulesFields` tem os grupos do vendedor fixos.
- A Lixeira usa a `ClientsTable` com a prop `lixeira` (não houve `TrashTable`).
- A validação dos filtros de saques foi para o `ListagemSaques::validar`, usada pela fila, pelos
  cartões e pela planilha.
- A antecipação avisa o bilhete em tempo real com o motivo `apuracao`, o mesmo da apuração.
- Os índices de `clientes.afiliados_id` e `afiliados.usuarios_id` já existiam pelas chaves
  estrangeiras; a migration de índices cria só os três compostos.

**Falta validar no navegador, com o usuário logado** (quickstart):

- entrega real da notificação push (precisa das chaves VAPID);
- pagar saque pelo Mercado Pago;
- acesso assistido abrindo a conta do cliente;
- download da planilha pelo navegador;
- tema claro;
- fluxo completo de editar, ajustar, antecipar, excluir e restaurar pela tela.

**Revisão de legibilidade (Princípio V)**: feita no conjunto.

- A regra de rede está num lugar só (`AlcanceClientes`), usada por controllers, serviços e pelo
  trait `GarantirAlcanceCliente` (middleware de controller para o `{cliente}` da rota).
- A consulta de clientes fica num service só (`FiltrosClientes`), usado pela lista, pela Lixeira e
  pela planilha; a de saques, no `ListagemSaques`.
- Os componentes de tela reaproveitam a moldura `CashModal` e os estilos das telas anteriores por
  reexportação.
- Refatorações feitas: a validação de saques saiu do controller; o extrato saiu da gaveta para o
  componente próprio; o import fora de ordem nas rotas foi corrigido; um trecho pouco claro do
  `WithdrawalDecisionModal` ganhou comentário.

**Ajustes pedidos pelo responsável depois da implementação (2026-10-23)**:

- Ajustar saldo: nos cartões de carteira, o valor ficava em cima do nome. Cada carteira ganhou o
  ícone (`account_balance_wallet`, `sports_soccer`, `casino`), e nome e valor ficam um embaixo do
  outro, como nos cartões de tipo (`components/BalanceAdjustModal/index.jsx`).
- Ranking: trocar o critério deixava a tela em branco, porque a tabela desenhava as linhas do
  critério anterior antes da lista nova chegar. A `RankingTable` agora ignora as linhas sem o bloco
  do critério atual (`components/RankingTable/index.jsx`).
- Saques e Ranking: as datas ganharam rótulo ("Pedido de" / "até", "De" / "até") ao lado do campo,
  com a mesma largura (170 px) e alinhadas na mesma linha do "Todo o período". No celular, ficam em
  duas colunas iguais, com o rótulo em cima (`pages/PanelClients/{index,styles}.jsx`). Conferido no
  Chrome sem janela em 1366 e 360 px, sem erro de página.
