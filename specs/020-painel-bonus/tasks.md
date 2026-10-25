---

description: "Lista de tarefas da spec 020 — Painel: Bônus"
---

# Tasks: Painel — Bônus (promoções, bônus recebidos e estornos)

**Input**: Design documents from `specs/020-painel-bonus/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md),
[data-model.md](data-model.md), [contracts/api.md](contracts/api.md),
[contracts/paginas.md](contracts/paginas.md), [quickstart.md](quickstart.md)

**Tests**: nenhum teste automatizado (constituição). A validação é manual pelo
[quickstart.md](quickstart.md).

**Organization**: tarefas agrupadas por história da spec. Cada tarefa cita os arquivos; arquivos
existentes só os listados no plan.md (Princípio IV, confirmados pelo responsável em 2026-10-23). Ao
concluir cada tarefa, revisão de legibilidade (Princípio V). Valores em bcmath, consultas pelo query
builder com parâmetros, comentários e mensagens em português, nomes em snake_case. Saldo só pelo
`SaldoClientes`; crédito, rollover, conversão e expiração só pelos services existentes, mudando apenas
o que o plano lista.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: pode rodar em paralelo (arquivos diferentes, sem depender de tarefa incompleta)
- **[Story]**: história da spec (US1 a US6)

---

## Phase 1: Setup

**Purpose**: estrutura do banco (data-model §1 e §3).

- [X] T001 [P] Criar `database/migrations/2026_10_26_000001_create_clientes_promocoes_estornos_table.php` (data-model §1.1): `clientes_promocoes_id` FK `clientes_promocoes` ("Promoção do bônus estornado"), `clientes_id` FK `clientes` ("Cliente que recebeu o bônus"), `clientes_transacoes_id` FK `clientes_transacoes` **único** ("Crédito do bônus (origem Promoção) que foi estornado; único para não estornar duas vezes"), `clientes_rollovers_id` FK nula `clientes_rollovers` ("Rollover do bônus cancelado pelo estorno (nulo quando a promoção não exigia rollover)"), `tipo` string(20) ("Como o bônus foi estornado: Individual, Geral ou Automático"), `motivo` string(255) ("Motivo informado no estorno (no automático, o evento que o causou)"), `usuarios_id` FK nula `usuarios` ("Usuário do painel que estornou (nulo no automático)"), `valor_retirado` decimal(15,2) default 0 ("Valor retirado do saldo promocional da modalidade (R$)"), `debito_clientes_transacoes_id` FK nula `clientes_transacoes` ("Transação de débito do estorno (nula quando não havia saldo para retirar)"), `timestamps()`, `softDeletes()`; índices `(clientes_promocoes_id, tipo)` e `(clientes_id)`
- [X] T002 [P] Criar `database/migrations/2026_10_26_000002_add_indices_painel_bonus.php` (data-model §3, R-13): `clientes_promocoes(afiliados_id, data_inicio)`, `clientes_promocoes(nome)`, `clientes_rollovers(clientes_transacoes_id)`, `clientes_transacoes(origem, carteira, created_at)`, cada um só se `! Schema::hasIndex(...)` nas colunas (padrão de `2026_10_25_000004_add_indices_painel_clientes.php`); `down()` remove só os criados aqui

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: enums, model, alcance, estorno por bônus, página e menu.

**⚠️ CRITICAL**: nenhuma história começa antes desta fase.

- [X] T003 [P] Criar os enums (data-model §4) com docblock em português: `app/Enums/TipoEstornoBonus.php` (`Individual = 'Individual'`, `Geral = 'Geral'`, `Automático = 'Automático'`), `app/Enums/SituacaoBonus.php` (`EmAndamento = 'Em andamento'`, `Cumprido = 'Cumprido'`, `Convertido = 'Convertido'`, `Expirado = 'Expirado'`, `ExcluídoPeloCliente = 'Excluído pelo cliente'`, `Estornado = 'Estornado'`; método `estornavel(): bool` true só para Em andamento e Cumprido) e `app/Enums/SituacaoPromocao.php` (`Vigente`, `Agendada`, `Encerrada`, `Desativada`, `Estornando`, `Estornada`, valores iguais ao nome)
- [X] T004 Criar `app/Models/ClientesPromocoesEstornos.php` (tabela `clientes_promocoes_estornos`, `SoftDeletes`, `guarded = ['id']`, casts `tipo` → `TipoEstornoBonus` e `valor_retirado` → `decimal:2`; relações `promocao()` com `withTrashed`, `cliente()` com `withTrashed`, `credito()` e `debito()` → `ClientesTransacoes`, `rollover()` → `ClientesRollovers`, `autor()` → `Usuarios` com `withTrashed`) e, em `app/Models/ClientesPromocoes.php`, a relação `estornos(): HasMany`
- [X] T005 Criar `app/Services/AlcancePromocoes.php` (R-06), apoiado em `AlcanceClientes::ids_gerentes`: `aplicar(EloquentBuilder $consulta, Usuarios): EloquentBuilder` (Admin sem restrição; demais: `afiliados_id IS NULL` ou `afiliados_id IN (afiliados com withTrashed e usuarios_id in ids_gerentes)`), `visivel(Usuarios, ClientesPromocoes): bool`, `alteravel(Usuarios, ClientesPromocoes): bool` (Admin sempre; demais só com afiliado da rede), `garantir_visivel()` → 404 "Promoção não encontrada.", `garantir_alteravel()` → 403 "Só o Admin pode alterar promoções gerais." quando geral (e 404 se não visível), `afiliado_da_rede(Usuarios, int $afiliados_id): bool`; docblock com a regra do FR-002
- [X] T006 Criar `app/Http/Controllers/Concerns/GarantirAlcancePromocao.php` (como `GarantirAlcanceCliente`): `alcance_promocao(): Middleware` que confere a `{promocao}` da rota com `garantir_visivel`, e `garantir_promocao_alteravel(Request, ClientesPromocoes)`
- [X] T007 Criar `app/Services/EstornoBonus.php` (R-05): `estornar(ClientesTransacoes $credito, TipoEstornoBonus $tipo, ?Usuarios $autor, string $motivo): ?ClientesPromocoesEstornos` em `DB::transaction`: trava o cliente (`withTrashed()->lockForUpdate()`) e depois o rollover do crédito (`clientes_transacoes_id`, `lockForUpdate`), na mesma ordem de `GestaoBonus::bloquear`; se já existe estorno do crédito, devolve `null`; calcula a situação pela mesma regra de R-03 e, se não for Em andamento nem Cumprido, recusa com `ValidationException` "Este bônus não pode mais ser estornado." (chave `bonus`) quando `tipo` = Individual ou devolve `null` no Geral; retira `min(saldo da carteira do crédito, valor do crédito)` (só se > 0, sem negativar) por `SaldoClientes::debitar` com origem `Estorno`, autor, `referencia_id` = promoção e observação "Estorno do bônus: {motivo}"; marca o rollover com `cancelado_em = now()` se ainda não cancelado; grava a linha em `clientes_promocoes_estornos`. E `registrar_automatico(int $clientes_transacoes_id, ?ClientesRollovers $rollover, string $motivo, string $valor_retirado, ?ClientesTransacoes $debito): void` que busca o crédito pelo id (sem relação nova em `ClientesRollovers`, que não está na lista do plano) e só grava a linha `Automático` (sem autor) com `insertOrIgnore` pelo índice único. Método estático público `situacao(?ClientesRollovers $rollover, bool $estornado): SituacaoBonus`, a mesma regra do CASE da listagem
- [X] T008 Registrar `GET painel/bonus` em `routes/web.php` (nome `painel.bonus`, comentário "bônus e promoções do painel (spec 020)") e a ação `bonus()` em `app/Http/Controllers/PainelController.php` (Inertia `PanelBonus`)
- [X] T009 [P] Em `resources/js/components/PanelShell/index.jsx`: item "Bônus" (chave `bonus`, ícone `redeem`, `href: '/painel/bonus'`) depois de "Clientes", visível com `clientes_promocoes.gerenciar` (contracts/paginas.md §1)
- [X] T010 Criar `resources/js/hooks/useBonusPainel.js` (R-11, no padrão de `useClientesPainel`): estado no endereço (`aba` = `promocoes` | `recebidos`, `busca`, filtros, `situacao`, `page`) com `router.get(..., { preserveState: true, replace: true })`, `mudar(parcial)` que volta à página 1, `parametros_api(filtros)`, carregamento por aba (Promoções: lista; Bônus recebidos: lista e resumo) com o token do `usePainel`, `recarregar()` sem perder a posição; padrão de `situacao` = `Em andamento` na aba Bônus recebidos
- [X] T011 Criar a página `resources/js/pages/PanelBonus/index.jsx` e `styles.jsx`: `PanelShell` título "Bônus", barra "Bônus — total N", busca com atraso de 400 ms, botões Filtros/Cadastrar/Atualizar, `PanelTabs` (Promoções `card_giftcard`, Bônus recebidos `redeem`), áreas com esqueleto, vazio e erro com "Tentar novamente" (FR-033); sem `clientes_promocoes.gerenciar`, volta para `/painel` (contracts/paginas.md §1)

**Checkpoint**: página abre pelo menu com as duas abas vazias.

---

## Phase 3: User Story 1 - Consultar as promoções (Priority: P1) 🎯 MVP

**Goal**: lista das promoções da rede com situação, números de uso, busca, filtros e detalhe.

**Independent Test**: quickstart §1 e §2.5–§2.6 (alcance; busca e filtros; números de uso).

### Implementation for User Story 1

- [X] T012 [US1] Criar `app/Services/ListagemPromocoes.php` (R-07): `regras()`/`mensagens()` dos filtros (`busca` string até 150; `situacao` enum `SituacaoPromocao`; `modalidade`; `categoria`; `afiliados_id` inteiro ou `geral`; `ativa` boolean; `por_pagina` 1–100, padrão 20); `consulta(Usuarios, array $filtros)` com `AlcancePromocoes::aplicar`, `with('afiliado', 'autor_estorno')`, coluna `situacao` por `CASE` (`estorno_situacao = 'Em andamento'` → Estornando; `'Concluído'` → Estornada; `ativa = 0` → Desativada; `data_inicio > now` → Agendada; `data_fim < now` → Encerrada; senão Vigente) também usada no filtro, busca `nome LIKE %?%`, ordem `data_inicio desc`; `uso(Collection $promocoes, Usuarios): array` com duas consultas agrupadas por `referencia_id` só dos ids da página e com `AlcanceClientes::aplicar` em `clientes_transacoes.clientes_id` para quem não é Admin: clientes distintos e Σ créditos `Promoção`; Σ créditos `Conversão de bônus` na carteira `Saldo` (convertido); Σ débitos `Bônus expirado` + `Bônus excluído` + `Estorno` (retirado); cumpridos = créditos `Promoção` com rollover `cumprido_em` não nulo ou sem rollover (LEFT JOIN `clientes_rollovers` por `clientes_transacoes_id`); valores como string com 2 casas
- [X] T013 [US1] Em `app/Http/Resources/ClientesPromocoesResource.php`: `afiliado` passa a incluir `excluido` (bool por `deleted_at`); `situacao` (atributo da consulta, ou calculado pelo model quando ausente, como no `show`); `uso` (quando enviado pelo controller via `additional`/atributo); `pode` = `{editar, ativar, excluir, estornar}` calculado pela permissão (`Funcao::usuario_pode`), por `AlcancePromocoes::alteravel`, por `estornada()` (estornada não edita/ativa/exclui/estorna) e por `aplicada()` (não exclui)
- [X] T014 [US1] Em `app/Http/Controllers/ClientesPromocoesController.php`: usar a trait `GarantirAlcancePromocao` (middleware `alcance_promocao` em show, update, destroy e estornar); `index` passa a usar `ListagemPromocoes` (validação, consulta, paginação `withQueryString`, números de uso da página anexados a cada item); `show` carrega `afiliado` e `autor_estorno` e anexa `uso` e `situacao` (contracts/api.md §1.1–§1.2)
- [X] T015 [P] [US1] Criar `resources/js/components/PromotionsFilters/` (`index.jsx`, `styles.jsx`): painel lateral (tela cheia no celular) com Situação, Modalidade e Categoria em chips e Afiliado por `AffiliatePicker` com a opção "Geral"; Aplicar e Limpar (contracts/paginas.md §3)
- [X] T016 [P] [US1] Criar `resources/js/components/PromotionsTable/` (`index.jsx`, `styles.jsx`): colunas ID · NOME (categoria embaixo) · MODALIDADE · GANHO (R$ ou %, "até R$ X" no percentual) · ROLLOVER (Nx · N dias) · AFILIADO (nome/código, "Geral", marcado se excluído) · PERÍODO (início — fim ou "Sem fim", dd/mm/aaaa hh:mm em Brasília) · SITUAÇÃO (etiqueta com a cor de contracts/paginas.md §2; "Estornando N de M") · USO (clientes · creditado · convertido) · AÇÕES (`ActionsMenu` com as ações recebidas por prop); celular até 900 px vira cartão com nome, ganho e situação e o resto ao expandir; tocar na linha chama `ao_abrir`
- [X] T017 [US1] Criar `resources/js/components/PromotionDrawer/` (`index.jsx`, `styles.jsx`): gaveta lateral (tela cheia no celular, altura fixa) que busca `GET /clientes-promocoes/{id}` e mostra os campos agrupados como no cadastro, cartões de uso (`StatCard`: Clientes, Creditado, Cumpridos, Convertido, Retirado), datas de cadastro e alteração e o bloco Estorno (situação, barra "N de M clientes", motivo, autor, início, conclusão, valor retirado); rodapé com as ações recebidas por prop (contracts/paginas.md §5)
- [X] T018 [US1] Na página `resources/js/pages/PanelBonus/index.jsx`: aba Promoções com `PromotionsTable`, `PromotionsFilters`, `ActiveFilters` (etiquetas removíveis), `Pagination`, total no cabeçalho e `PromotionDrawer`; menu por linha a partir de `pode` (Ver detalhe; as demais ligadas nas próximas histórias); vazio "Nenhuma promoção encontrada. Troque a busca ou os filtros."

**Checkpoint**: US1 validável pelo quickstart §1 e §2.5.

---

## Phase 4: User Story 2 - Cadastrar e editar promoções (Priority: P1)

**Goal**: cadastrar, editar, ativar e desativar com as regras do antigo de volta.

**Independent Test**: quickstart §2.1–§2.4, §2.6–§2.7 e §1.3–§1.4.

### Implementation for User Story 2

- [X] T019 [US2] Em `app/Http/Requests/ClientesPromocoesRequest.php` (R-06, R-08): guardar o `nome` e o `data_fim` originais da rota antes do merge do `prepareForValidation`; `nome` com `Rule::unique('clientes_promocoes', 'nome')->whereNull('deleted_at')->ignore(promoção)` só quando o nome é novo ou mudou ("Já existe uma promoção com este nome."); `data_fim` com `after:now` só quando enviada e diferente da gravada ("A data de fim já passou."); `afiliados_id` obrigatório para quem não é Admin ("Afiliado não encontrado.", mensagem antes do `'*.required'`, porque o Laravel usa a primeira que casar), e a existência atual (`Rule::exists(...)->whereNull('deleted_at')`) e `AlcancePromocoes::afiliado_da_rede` só quando o afiliado é enviado e diferente do gravado, para a promoção de um afiliado excluído continuar podendo ser desativada (Admin pode `null` ou afiliado sem gerente); no `prepareForValidation`, `AlcancePromocoes::garantir_alteravel` antes de validar (a geral responde 403 para Supervisor e Gerente, e não um 422 do afiliado); demais regras e mensagens atuais sem mudança
- [X] T020 [US2] Em `app/Http/Controllers/ClientesPromocoesController.php`: `store` e `update` chamam `garantir_promocao_alteravel` (update) e recusam promoção geral criada por quem não é Admin (403 "Só o Admin pode alterar promoções gerais."); `garantir_sem_sobreposicao` passa a filtrar pelo mesmo afiliado (`afiliados_id = X` ou `IS NULL` para as gerais) e a mensagem passa a "Já existe uma promoção ativa desta categoria e modalidade no período: {nome} (ID {id})." (FR-015); a resposta usa o resource com `situacao`, `uso` e `pode`
- [X] T021 [P] [US2] Criar `resources/js/components/PromotionFormModal/` (`index.jsx`, `styles.jsx`): `ModalHeader` (`card_giftcard`, "Cadastrar promoção"/"Editar promoção", "Defina quem ganha o bônus, quanto e as regras para usar."), altura fixa `min(680px, 90dvh)`, corpo rolável, rodapé fixo; grupos e campos de contracts/paginas.md §4 com rótulo em cima, em grade de duas colunas; Ativa, Categoria, Modalidade, Tipo de ganho e Rollover no primeiro depósito em campos de seleção (ajuste de 2026-10-25); valores em reais com `ler_valor`, percentual 0–100; Tipo de ganho desabilitado em Fixo fora das categorias de depósito, Depósito máximo só no Percentual, Rollover no primeiro depósito só em Primeiro depósito, Quantidade de indicações só em Indicação; Afiliado por `AffiliatePicker` (`pode_limpar` só para Admin); na edição de promoção `aplicada`, Valor, Tipo de ganho, Categoria e Modalidade travados com "Esta promoção já foi dada a clientes; esses campos não podem mudar."; erros do servidor no campo; Salvar com carregando e sem clique duplo
- [X] T022 [US2] Na página `resources/js/pages/PanelBonus/index.jsx`: botão Cadastrar (aba Promoções) e ações Editar e Ativar/Desativar (`ConfirmModal`, `PATCH { ativa }`) pelo `pode`, com aviso de sucesso e lista, cartões e gaveta atualizados sem perder filtros e página (FR-034)

**Checkpoint**: US1 + US2 entregam o cadastro completo da tela antiga.

---

## Phase 5: User Story 3 - Acompanhar os bônus recebidos (Priority: P1)

**Goal**: aba com cada bônus dado a um cliente, situação, progresso do rollover, cartões e detalhe.

**Independent Test**: quickstart §3.

### Implementation for User Story 3

- [X] T023 [P] [US3] Criar `app/Http/Requests/BonusRecebidosFiltrosRequest.php`: `situacao` em `SituacaoBonus` ou `Todos` (padrão `Em andamento`); `busca` string até 150; `clientes_promocoes_id` inteiro existente; `modalidade` `Esportes`|`Cassino`; `data_inicial`/`data_final` `date_format:Y-m-d`, `data_final` ≥ `data_inicial`, até 366 dias ("O período deve ter no máximo 1 ano."); `por_pagina` 1–100 (padrão 30); mensagens em português
- [X] T024 [US3] Criar `app/Services/ListagemBonusRecebidos.php` (R-02, R-03, R-09): consulta em `clientes_transacoes` origem `Promoção` com JOIN `clientes` (com excluídos) e `clientes_promocoes` (com excluídas), LEFT JOIN `clientes_rollovers` (`clientes_transacoes_id`) e `clientes_promocoes_estornos` (`clientes_transacoes_id`), `AlcanceClientes::aplicar` em `clientes_transacoes.clientes_id` (todos os bônus dos clientes da rede, mesmo de promoção que o usuário não vê na aba Promoções, FR-002); coluna `situacao` por `CASE` na ordem de R-03 (estorno → Estornado; `convertido_em` → Convertido; `expirado_em` → Expirado; `excluido_em` → Excluído pelo cliente; `cancelado_em` → Estornado; `cumprido_em` ou sem rollover → Cumprido; senão Em andamento); `falta` = `GREATEST(valor_exigido - valor_apostado, 0)`; saldo promocional pela coluna da carteira do crédito; filtros de T023 (busca por nome com `LIKE` ou ID quando numérico; modalidade pela carteira; período em Brasília → UTC); ordem Em andamento por `expira_em` asc (nulos por último), demais por `clientes_transacoes.created_at` desc; `resumo(Usuarios, array $filtros)`: Em andamento (quantidade e Σ falta, sem período), Creditado (créditos `Promoção`), Convertido (créditos `Conversão de bônus` na carteira `Saldo`) e Retirado (débitos `Bônus expirado` e `Bônus excluído` mais os estornos `Individual` e `Geral` da tabela de estornos; a origem `Estorno` também é de apostas) no período, com busca, promoção e modalidade; `detalhe(Usuarios, int $id)` com regras gravadas no rollover e histórico (Recebido, Cumprido, Convertido, Expirado, Excluído pelo cliente, Estornado com tipo, autor, motivo e valor), 404 "Bônus não encontrado." fora da rede ou se não for crédito `Promoção`
- [X] T025 [P] [US3] Criar `app/Http/Resources/BonusRecebidosResource.php` no formato de contracts/api.md §2.1 (`cliente`, `promocao`, `modalidade`, `valor`, `saldo_promocional`, `rollover` ou `null`, `recebido_em`, `expira_em`, `situacao`, `encerrado_em`, `valor_encerramento`, `estorno`, `pode.estornar` = permissão `clientes_promocoes.estornar` + `SituacaoBonus::estornavel()`), com `regras` e `historico` quando vierem do detalhe
- [X] T026 [US3] Criar `app/Http/Controllers/ClientesBonusController.php` (`HasMiddleware`, `GarantirPermissaoCliente`: `clientes_promocoes.gerenciar` em `index`, `resumo`, `show`) e registrar em `routes/api.php`, no grupo das rotas de clientes do painel, `GET clientes-bonus/resumo` antes de `GET clientes-bonus` e `GET clientes-bonus/{bonus}` (`whereNumber`), com `missing` → 404 "Bônus não encontrado." (contracts/api.md §2.1–§2.3)
- [X] T027 [P] [US3] Criar `resources/js/components/PromotionPicker/` (`index.jsx`, `styles.jsx`) no padrão do `AffiliatePicker`: busca com atraso em `GET /clientes-promocoes?busca=&por_pagina=20`, só grava uma promoção da lista (`{ id, nome }`), com limpar
- [X] T028 [P] [US3] Criar `resources/js/components/ReceivedBonusFilters/` (`index.jsx`, `styles.jsx`): Promoção (`PromotionPicker`), Modalidade (chips) e Período (`PeriodFilter`: Hoje, Ontem, Esta semana, Este mês, Personalizado até 1 ano; vazio por padrão) (contracts/paginas.md §7)
- [X] T029 [P] [US3] Criar `resources/js/components/ReceivedBonusTable/` (`index.jsx`, `styles.jsx`): colunas CLIENTE (ID · nome) · PROMOÇÃO · MODALIDADE · VALOR · SALDO BÔNUS · ROLLOVER (apostado / exigido com barra de progresso e "falta R$ X"; "Sem rollover" quando nulo) · RECEBIDO EM · EXPIRA EM · SITUAÇÃO (etiqueta com a cor de contracts/paginas.md §7) · AÇÕES (`ActionsMenu`); celular vira cartão com cliente, valor e "falta R$ X" (ou a situação)
- [X] T030 [US3] Criar `resources/js/components/BonusDrawer/` (`index.jsx`, `styles.jsx`): gaveta que busca `GET /clientes-bonus/{id}` e mostra os dados, as regras gravadas e o histórico em linha do tempo (data, valor, autor e motivo); botões "Ver cliente" (com `clientes.listar`) e as ações recebidas por prop (contracts/paginas.md §8)
- [X] T031 [US3] Na página `resources/js/pages/PanelBonus/index.jsx`: aba Bônus recebidos com cartões (`StatCard`: Em andamento, Creditado, Convertido, Retirado — "Desde sempre" sem período), chips de situação (Em andamento padrão, Cumpridos, Convertidos, Expirados, Excluídos pelo cliente, Estornados, Todos), `ReceivedBonusFilters`, `ActiveFilters`, `ReceivedBonusTable`, `Pagination`, `BonusDrawer`; "Ver cliente" abre o `ClientDrawer` da spec 019 sem ações (`acoes` vazio) — se o componente exigir mudança, parar e avisar (Princípio IV); ação "Ver bônus recebidos" da aba Promoções troca de aba com `clientes_promocoes_id` e situação Todos; vazio "Nenhum bônus nesta situação. Troque a situação, a busca ou os filtros."

**Checkpoint**: US3 validável pelo quickstart §3.

---

## Phase 6: User Story 4 - Estornar uma promoção inteira (Priority: P2)

**Goal**: estorno geral com senha e motivo, bônus a bônus, com progresso na tela.

**Independent Test**: quickstart §5.

### Implementation for User Story 4

- [X] T032 [US4] Em `app/Http/Requests/EstornarPromocaoRequest.php`: usar `Concerns\ConfirmacaoCaixa` e acrescentar `senha` (`required`, `string`, `regra_senha()` → "Senha incorreta."; mensagem `senha.required` "Informe a sua senha."), mantendo `motivo` como hoje
- [X] T033 [US4] Em `app/Jobs/EstornarPromocao.php` (R-05): `handle` passa a percorrer os créditos `Promoção` da promoção (`referencia_id`) em lotes de 500 por `id` (`chunkById`) e chamar `EstornoBonus::estornar($credito, TipoEstornoBonus::Geral, $promocao->autor_estorno, $promocao->estorno_motivo)` para cada um (já estornados, convertidos, expirados e excluídos são pulados pelo service); contadores recalculados do banco: `estorno_clientes_processados` = clientes distintos dos créditos já percorridos, `estorno_valor_total` = Σ `valor_retirado` dos estornos `Geral` da promoção; ao final, `Concluído` como hoje; remover os métodos privados que deixam de ser usados (`estornar_cliente`, `consulta_recebimentos`) e o uso de `RolloverClientes::cancelar_da_promocao`; docblock atualizado (idempotente por bônus)
- [X] T034 [US4] Em `app/Http/Controllers/ClientesPromocoesController.php`, `estornar`: `garantir_promocao_alteravel` antes de tudo (geral só Admin), mantendo o resto; resposta 202 com o resource completo (contracts/api.md §1.5)
- [X] T035 [P] [US4] Criar `resources/js/components/PromotionReversalModal/` (`index.jsx`, `styles.jsx`): `ModalHeader` (`undo`, "Estornar promoção", "O bônus será retirado de todos que o receberam e a promoção será desativada."), clientes e valor creditado, o aviso de contracts/paginas.md §6, Motivo (obrigatório, contador 255) e Senha; botão "Estornar" vermelho com carregando; erros do servidor no campo
- [X] T036 [US4] Na página `resources/js/pages/PanelBonus/index.jsx` e em `resources/js/hooks/useBonusPainel.js`: ação "Estornar promoção" (com `pode.estornar`) abre o `PromotionReversalModal`; depois do 202, enquanto houver promoção "Estornando" na página, consultar `GET /clientes-promocoes/{id}` a cada 3 s e atualizar só aquela linha (e a gaveta aberta) até "Estornada" (R-11)

**Checkpoint**: US4 validável pelo quickstart §5.

---

## Phase 7: User Story 5 - Estornar o bônus de um cliente (Priority: P2)

**Goal**: estorno individual com senha e motivo.

**Independent Test**: quickstart §4.

### Implementation for User Story 5

- [X] T037 [P] [US5] Criar `app/Http/Requests/EstornarBonusRequest.php` com `ConfirmacaoCaixa`: `motivo` (`required`, `string`, `max:255`; "O motivo do estorno é obrigatório." / "O motivo deve ter no máximo 255 caracteres.") e `senha` (`required`, `regra_senha()` → "Senha incorreta.")
- [X] T038 [US5] Em `app/Http/Controllers/ClientesBonusController.php`: `estornar(EstornarBonusRequest, int $bonus)` com middleware `clientes_promocoes.estornar`; carrega o crédito pelo `ListagemBonusRecebidos` (alcance; 404 "Bônus não encontrado."), chama `EstornoBonus::estornar($credito, TipoEstornoBonus::Individual, $request->user(), motivo)` e responde 200 com o item atualizado; registrar `POST clientes-bonus/{bonus}/estornar` em `routes/api.php` (contracts/api.md §2.4)
- [X] T039 [P] [US5] Criar `resources/js/components/BonusReversalModal/` (`index.jsx`, `styles.jsx`): `ModalHeader` (`undo`, "Estornar bônus", "O bônus será retirado só deste cliente."), cliente, promoção, valor do bônus e "Será retirado: R$ X" (menor entre o saldo de bônus atual e o valor), Motivo e Senha; botão vermelho com carregando; erros do servidor no campo
- [X] T040 [US5] Na página `resources/js/pages/PanelBonus/index.jsx`: ação "Estornar bônus" (com `pode.estornar`) no menu da linha e no `BonusDrawer`, abrindo o `BonusReversalModal`; depois do sucesso, aviso e lista, cartões e gaveta atualizados (FR-034)

**Checkpoint**: US5 validável pelo quickstart §4.

---

## Phase 8: User Story 6 - Excluir promoção não usada (Priority: P3)

**Goal**: excluir só a promoção que ninguém recebeu.

**Independent Test**: quickstart §6.

### Implementation for User Story 6

- [X] T041 [US6] Em `app/Http/Controllers/ClientesPromocoesController.php`, `destroy`: `garantir_promocao_alteravel`; se `aplicada()` ou `estornada()`, `ValidationException` (chave `promocao`) "Promoção já aplicada não pode ser excluída; desative ou estorne."; senão exclusão lógica como hoje (204) (FR-019)
- [X] T042 [US6] Na página `resources/js/pages/PanelBonus/index.jsx`: ação "Excluir" (com `pode.excluir`) com `ConfirmModal`; depois do sucesso, lista atualizada sem perder filtros e página

**Checkpoint**: todas as histórias funcionando.

---

## Phase 9: Cancelamentos automáticos e dados antigos (R-05, R-12)

**Purpose**: os bônus cancelados pelo sistema e os estornos anteriores aparecem como Estornados.

- [X] T043 Em `app/Services/ProcessamentoDepositos.php`, `cancelar_bonus`: guardar a transação de débito devolvida por `SaldoClientes::debitar` e chamar `EstornoBonus::registrar_automatico($bonus->clientes_transacoes_id, $bonus, 'Cancelado pelo estorno do depósito', $retirar ou '0.00', $debito)`; injetar `EstornoBonus` no construtor; sem outra mudança
- [X] T044 Em `app/Services/Indicacoes.php`, `cancelar_grupo`: guardar o débito e chamar `EstornoBonus::registrar_automatico($credito->id, $grupo->rollover, 'Cancelado pelo estorno do depósito de um indicado', valor do débito, $debito)`; injetar `EstornoBonus`; sem outra mudança
- [X] T045 Criar a migration de dados `database/migrations/2026_10_26_000003_preencher_clientes_promocoes_estornos.php` (R-12), idempotente com `insertOrIgnore`: (a) para promoções com `estorno_situacao = 'Concluído'`, um registro `Geral` por crédito `Promoção` cujo rollover tem `cancelado_em` sem `convertido_em`/`expirado_em`/`excluido_em` (ou sem rollover), com `usuarios_id` = `estorno_usuarios_id`, `motivo` = `estorno_motivo`, `valor_retirado` 0; (b) um registro `Automático` por rollover de bônus cancelado cujo débito `Bônus excluído` do mesmo cliente e promoção tem a observação "Bônus cancelado pelo estorno do depósito" ou "Bônus de indicação cancelado pelo estorno do depósito de um indicado", com o valor e o id desse débito; `down()` apaga só os registros criados por ela (marcados pelo motivo/tipo e `created_at` da execução)

---

## Phase 10: Polish & Cross-Cutting Concerns

- [X] T046 [P] Regenerar `docs/postman/wssports_api.postman_collection.json` (substituindo o arquivo) com as rotas novas (`clientes-bonus`, `clientes-bonus/resumo`, `clientes-bonus/{bonus}`, `clientes-bonus/{bonus}/estornar`) e os campos novos das alteradas (`senha` no estorno da promoção; filtros `busca`, `situacao` e `afiliados_id` na lista de promoções)
- [X] T047 [P] Nota "Alterado pela spec 020" nos requisitos de promoções de `specs/002-clientes/spec.md` (exclusão só não aplicada, nome único, fim no passado), `specs/007-painel-cliente-pagamentos/spec.md` (estorno por bônus com senha, cancelamentos registrados como estorno automático, sobreposição por afiliado) e `specs/012-afiliados/spec.md` (alcance das promoções pela rede do afiliado)
- [ ] T048 Conferir responsividade a 360 px (sem rolagem horizontal, alvos ≥ 40 px), modais e gavetas pela constituição (tamanho, `ModalHeader`, só o corpo rola) e os estados de carregando/vazio/erro nas duas abas (FR-031 a FR-034)
- [ ] T049 Executar o [quickstart.md](quickstart.md) inteiro e registrar o resultado em "Notas da implementação" neste arquivo; revisão de legibilidade final do conjunto (Princípio V)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)** → **Foundational (Phase 2)** → histórias.
- **US1 (Phase 3)**: depende só da Phase 2. É o MVP.
- **US2 (Phase 4)**: usa a lista e o resource da US1 (T013, T014, T018).
- **US3 (Phase 5)**: depende só da Phase 2 (aba independente); T031 liga "Ver bônus recebidos" da US1.
- **US4 (Phase 6)**: depende de T007 (EstornoBonus) e da US1 (linha e gaveta da promoção).
- **US5 (Phase 7)**: depende de T007 e da US3 (lista, detalhe e controller dos bônus recebidos).
- **US6 (Phase 8)**: depende da US1.
- **Phase 9**: depende de T001 e T007; pode rodar em paralelo às histórias.
- **Polish (Phase 10)**: depois de tudo.

### Within Each User Story

- Backend (requests, services, controllers, rotas) antes da tela; componentes [P] em paralelo; a
  página por último.

### Parallel Opportunities

- Phase 1: T001 e T002 em paralelo.
- Phase 2: T003 e T009 em paralelo; T004 depois de T001/T003; T005–T007 depois de T004.
- US1: T015 e T016 em paralelo depois de T014.
- US3: T023, T025, T027, T028, T029 em paralelo.
- US4 e US5: T035 e T039 em paralelo; US4 e US5 podem andar juntas depois da US3.
- Phase 9 (T043, T044) em paralelo às histórias depois de T007.

---

## Parallel Example: User Story 3

```text
T023 BonusRecebidosFiltrosRequest · T025 BonusRecebidosResource · T027 PromotionPicker ·
T028 ReceivedBonusFilters · T029 ReceivedBonusTable   (em paralelo; depois T024 → T026 → T030 → T031)
```

---

## Implementation Strategy

### MVP First (User Story 1)

1. Phases 1 e 2 → 2. Phase 3 (US1) → 3. validar quickstart §1 e §2.5 → entregar a lista de promoções
   da rede.

### Incremental Delivery

1. US1 (lista) → US2 (cadastro) → US3 (bônus recebidos) → US4 (estorno geral) → US5 (estorno
   individual) → US6 (excluir) → Phase 9 (automáticos e dados antigos) → Polish.
2. Cada etapa validada pela seção correspondente do quickstart antes da próxima.

---

## Notes

- Sem testes automatizados; nenhuma tarefa de teste.
- Toda rota criada ou alterada entra na coleção do Postman (T046).
- Mudanças fora da lista do plan.md exigem aviso e confirmação antes (Princípio IV).

## Notas da implementação (2026-10-24)

- **Correções da análise aplicadas na implementação**, seguindo a spec (a referência) nos pontos em que
  o texto das tarefas divergia: C1 — afiliado conferido só quando muda (caso de borda "Afiliado
  excluído"); F1 — Bônus recebidos sem o filtro de promoção visível (FR-002); U1 — o crédito do bônus
  é buscado pelo id, sem relação nova em `ClientesRollovers`. F2, F3 e U2 entraram na spec (cenário 5
  da US3, caso de borda do estorno do depósito, FR-022 com "—" nos estornos antigos), no research e no
  contrato. T007, T019, T024, T043 e T044 foram reescritas para descrever o que foi feito.
- **Retirado sem a origem `Estorno`**: a origem `Estorno` também é usada nos estornos de apostas, com
  `referencia_id` = aposta, então somar por referência misturaria apostas com promoções. O retirado
  soma `Bônus expirado` e `Bônus excluído` mais `valor_retirado` dos estornos `Individual` e `Geral` da
  tabela nova (R-07, R-09, data-model §5).
- **Chave estrangeira com nome curto**: `clientes_promocoes_estornos_debito_foreign`; o nome padrão
  passou dos 64 caracteres do MySQL. A primeira tentativa da migration criou a tabela pela metade; ela
  estava vazia e foi apagada antes de rodar de novo.
- **Período dos bônus recebidos**: atalhos em chips (Desde sempre, Hoje, Ontem, Esta semana, Este mês,
  de `utils/periods`) e as datas de/até no painel de filtros, no lugar do `PeriodFilter` do caixa,
  que não tem a opção "sem período" (contracts/paginas §7).
- **Histórico do bônus**: valor no recebimento e no estorno; conversão, expiração e exclusão aparecem
  com a data (as transações delas referenciam a promoção, não o crédito). O campo
  `valor_encerramento` do contrato saiu.
- **Formulário**: as datas são enviadas em UTC (ISO com Z); na edição, data não alterada não é
  enviada (o campo não tem segundos e o fim já passado de uma promoção encerrada seria recusado).
- **"Ver cliente"**: o `ClientDrawer` da spec 019 é aberto sem ações e sem as permissões de alterar,
  sem mudança nele.
- **Validado automaticamente** (scripts fora do projeto, só leituras e recusas): migrations aplicadas;
  `php -l` em todos os arquivos PHP; `npm run build` sem erro; consultas da lista, uso, situação, bônus
  recebidos, resumo e detalhe para Admin, Supervisor e Gerente; senha errada nos dois estornos → 422
  "Senha incorreta." sem gravar; Gerente: geral sem ações, estornar → 403, alterar/excluir geral →
  403 "Só o Admin pode alterar promoções gerais.", cadastrar sem afiliado → 422 "Afiliado não
  encontrado."; bônus inexistente → 404; período maior que 1 ano → 422.
- **Fica com o responsável**: T048 (conferência visual a 360 px e dos estados) e T049 (quickstart
  inteiro, que grava dados: cadastrar, estornar, excluir, depositar).
- **Mensagens do `ClientesPromocoesRequest` (corrigido em 2026-10-25, com autorização)**: as mensagens
  específicas que vinham depois das genéricas (`valor_maximo_deposito.required`,
  `quantidade_indicacoes.required`, `dias_cumprir.min` e `quantidade_indicacoes.min`) nunca apareciam,
  porque o Laravel usa a primeira que casar; as genéricas (`'*.'`) passaram para o fim do array.
  Conferido com cadastros inválidos (recusados, nada gravado).
- **Revisão de legibilidade (Princípio V)**: a checagem repetida da geral no `update` do controller saiu
  (o request já recusa); demais arquivos sem refatoração necessária.
- **Ajustes visuais (2026-10-25, pedido do responsável; spec, Clarifications)**: no
  `PromotionFormModal`, interruptores e chips viraram campos de seleção (`Select` com o visual do
  `Input` do modal) em grade de duas colunas, com Ativa ao lado do Afiliado; na página `PanelBonus`, o
  topo passou a ter estilos próprios (`Top`, `Tabs`, `SearchBox`, `Chips` em `pages/PanelBonus/styles.jsx`)
  — abas do tamanho do texto e sem quebrar o rótulo, busca de 300 px e chips que não alargam a coluna
  das abas no computador; abas na largura toda e chips numa linha com rolagem no celular. Os estilos da
  tela Clientes, que a página reaproveita, não mudaram. `npm run build` sem erro.
- **Filtro dos bônus recebidos e Cadastrar (2026-10-25, pedido do responsável)**: o
  `ReceivedBonusFilters` passou a uma linha (Promoção mais larga, Modalidade e Período em campos de
  seleção, com as datas De/Até só no Personalizado), com grade própria (`Panel`, `Cell`); o botão
  Cadastrar da página aparece nas duas abas. `npm run build` sem erro.
- **Filtros numa linha com os botões (2026-10-25, pedido do responsável)**: nos dois filtros, Limpar
  filtros e Aplicar ficam na mesma linha dos campos (área `acoes` da grade). No `PromotionsFilters`,
  Situação e Modalidade em campos de seleção na linha dos botões; Categoria e Afiliado em chips, cada
  um numa linha embaixo (Afiliado: Todos, Geral ou De um afiliado, com o `AffiliatePicker` ao lado).
  `npm run build` sem erro.
