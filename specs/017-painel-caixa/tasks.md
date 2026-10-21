---

description: "Lista de tarefas da spec 017 — Painel: Caixa"
---

# Tasks: Painel — Caixa (vendedores, gerentes, clientes, lançamentos e prestação de contas)

**Input**: Design documents from `specs/017-painel-caixa/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md),
[data-model.md](data-model.md), [contracts/api.md](contracts/api.md),
[contracts/paginas.md](contracts/paginas.md), [quickstart.md](quickstart.md)

**Tests**: nenhum teste automatizado (constituição). A validação é manual pelo
[quickstart.md](quickstart.md) e por um passeio no Chrome sem janela pelas telas novas (cada aba,
lista e modal, sem erro de página).

**Organization**: tarefas agrupadas por história da spec. Cada tarefa cita os arquivos; arquivos
existentes só os listados no plan.md (Princípio IV, confirmados pelo responsável em 2026-10-21). Ao
concluir cada tarefa, revisão de legibilidade (Princípio V). Valores em bcmath, consultas pelo query
builder, comentários e mensagens em português, nomes em snake_case.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: pode rodar em paralelo (arquivos diferentes, sem depender de tarefa incompleta)
- **[Story]**: história da spec (US1 a US6)

---

## Phase 1: Setup

**Purpose**: dependência nova e estrutura do banco.

- [ ] T001 Adicionar `phpoffice/phpspreadsheet` com `composer require phpoffice/phpspreadsheet` (atualiza `composer.json` e `composer.lock`) e conferir as extensões `zip`, `xml`, `xmlwriter` e `gd` com `php -m` (R-09)
- [ ] T002 Criar `database/migrations/2026_10_23_000001_create_caixa_prestacoes_contas_table.php` conforme data-model.md: `usuarios_id` FK `usuarios` ("Vendedor cujo caixa foi prestado"), `periodo_inicio` dateTime nullable ("Início do período da prestação (fim da prestação anterior); vazio na primeira prestação do vendedor"), `periodo_fim` dateTime ("Fim do período da prestação: data e hora até onde o caixa foi fechado"), `saldo_anterior`, `valor_apurado`, `valor_recebido`, `diferenca` decimal(15,2) com os comentários do data-model, `lote` char(36) nullable ("Identificador da prestação em lote feita pela linha do gerente; vazio na prestação individual"), `prestado_por` FK `usuarios`, `observacao` string(500) nullable, `desfeita_por` FK `usuarios` nullable, `motivo_desfazer` string(500) nullable, `timestamps()`, `softDeletes()`, índices (`usuarios_id`, `periodo_fim`) e (`lote`); todas as colunas com `->comment()` em português
- [ ] T003 Criar `database/migrations/2026_10_23_000002_create_caixa_lancamentos_table.php` conforme data-model.md: `usuarios_id` FK `usuarios`, `tipo` string(20) ("Tipo do lançamento: Suprimento (dinheiro entregue ao vendedor, aumenta o que ele deve) ou Retirada (dinheiro recolhido, diminui o que ele deve)"), `valor` decimal(15,2) ("Valor do lançamento em reais"), `ocorrido_em` dateTime, `observacao` string(500) nullable, `lancado_por` FK `usuarios`, `caixa_prestacoes_contas_id` FK `caixa_prestacoes_contas` nullable ("Prestação de contas em que o lançamento foi incluído; vazio enquanto está aberto"), `excluido_por` FK `usuarios` nullable, `timestamps()`, `softDeletes()`, índices (`usuarios_id`, `ocorrido_em`) e (`usuarios_id`, `caixa_prestacoes_contas_id`); todas com `->comment()`
- [ ] T004 [P] Criar `database/migrations/2026_10_23_000003_add_comissao_gerente_usuarios_configuracoes_table.php` com `percentual_comissao_gerente` decimal(5,2) default 0 ("Percentual (%) da comissão do gerente sobre o líquido dos vendedores dele no período (zero quando o líquido for ≤ 0)") e o `down()` que remove a coluna

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: permissões, models, cálculo único, alcance, página e filtro comuns.

**⚠️ CRITICAL**: nenhuma história começa antes desta fase.

- [ ] T005 Em `app/Enums/Funcao.php`: constante `PERMISSOES_CAIXA` (`caixa.ver`, `caixa.ver_clientes`, `caixa.lancar`, `caixa.excluir_lancamento`, `caixa.prestar_contas`, `caixa.exportar`, `caixa.editar_comissao_gerente`) com docblock; regra em `pode_usar` (Admin e Supervisor todas; Gerente todas menos `caixa.ver_clientes` e `caixa.editar_comissao_gerente`; Vendedor só `caixa.ver`) atualizando o docblock do método; entrada em `permissoes_padrao` para gestores e para o Vendedor (R-08)
- [ ] T006 [P] Incluir `...Funcao::PERMISSOES_CAIXA` em `database/seeders/PapeisPermissoesSeeder.php`
- [ ] T007 Criar a migration de dados `database/migrations/2026_10_23_000004_criar_permissoes_caixa.php` no padrão de `2026_10_21_000001_dividir_permissoes_usuarios_por_funcao.php`: limpar o cache, `Permission::firstOrCreate` de cada `caixa.*` no guard `api`, dar a cada usuário (com `withTrashed`) as de `PERMISSOES_CAIXA` que estão em `permissoes_padrao()` da função dele; `down()` remove as permissões (FR-033)
- [ ] T008 [P] Acrescentar em `resources/js/utils/permissions.js` o grupo "Caixa" com nome e descrição de cada `caixa.*` (tela de permissões da spec 015)
- [ ] T009 [P] Criar `app/Enums/TipoLancamentoCaixa.php` (`Suprimento`, `Retirada`) no padrão de `app/Enums/TipoTransacao.php`
- [ ] T010 [P] Criar `app/Models/CaixaPrestacoesContas.php` (SoftDeletes; fillable das colunas de T002; casts decimal:2 e datetime; relações `vendedor` (`usuarios_id`), `autor` (`prestado_por`) e `desfeita_por_usuario`, todas com `withTrashed()`, e `lancamentos`) com docblock "criada só por PrestacaoContasCaixa; nunca editada; desfazer = soft delete", no padrão de `app/Models/AfiliadosPrestacoesContas.php`
- [ ] T011 [P] Criar `app/Models/CaixaLancamentos.php` (SoftDeletes; fillable; casts `tipo` → `TipoLancamentoCaixa`, `valor` decimal:2, `ocorrido_em` datetime; relações `vendedor`, `autor` (`lancado_por`), `excluido_por_usuario`, `prestacao`; scope `emAberto` = sem `caixa_prestacoes_contas_id`)
- [ ] T012 [P] Adicionar `percentual_comissao_gerente` em `fillable` e em `casts` (`decimal:2`) de `app/Models/UsuariosConfiguracoes.php`
- [ ] T013 Criar `app/Http/Requests/CaixaPeriodoRequest.php`: `de`/`ate` obrigatórios (`date_format:Y-m-d`), `ate` ≥ `de`, ≤ hoje em Brasília e no máximo 366 dias; `tipo` in `todas,pre_jogo,ao_vivo` (padrão `todas`); `usuarios_id` inteiro opcional; `busca` string até 100; `ordenar` in `nome,entradas,liquido,saldo_caixa`; `direcao` in `asc,desc`; `por_pagina` 1 a 100; mensagens em português; método `intervalo()` que devolve `[inicio, fim]` em UTC pelos dias de Brasília com `App\Support\FusoSistema` (R-07)
- [ ] T014 Criar `app/Http/Controllers/Concerns/GarantirAlcanceCaixa.php`: `foco(Request, ?int $usuarios_id): ?Usuarios` (vazio = o próprio; Admin no topo = null = banca; outro usuário só se `gerencia` ou for ele, inclusive excluídos, senão 403 "Você não tem permissão para esta ação."); `garantir_vendedor(Request, Usuarios)` e `garantir_gerente(Request, Usuarios)` (aceitando inativos e excluídos: as rotas com `{usuario}` usam `->withTrashed()`, FR-014a) (função errada → 404 "Usuário não encontrado."; fora da rede → 403) (R-08)
- [ ] T015 Criar `app/Services/ApuracaoCaixa.php` (R-02, data-model "Apuração do caixa"): `somas_por_vendedor(array $ids_vendedores, Carbon $inicio, Carbon $fim, string $tipo)` numa consulta agrupada por `apostas.usuarios_id` (apostas `Ativa`, `clientes_id` nulo, sem excluídas, por `created_at`; quantidades e valores por `resultado`; saídas = `premio` das vencedoras + `valor` das devolvidas; `comissao`; tipo `ao_vivo` = EXISTS em `apostas_palpites` com `confrontos_ao_vivo_id`, `pre_jogo` = NOT EXISTS); `lancamentos_por_vendedor(ids, inicio, fim)` (suprimentos e retiradas não excluídos por `ocorrido_em`); `recebido_por_vendedor(ids, inicio, fim)` (Σ `valor_recebido` das prestações não desfeitas criadas no intervalo); `agrupar(...)` que monta a linha de um vendedor, de um gerente (com a comissão do gerente = `percentual_comissao_gerente` × líquido dos vendedores dele no intervalo inteiro, zero se ≤ 0) e de supervisor/banca (soma das linhas de gerente); `liquido` = entradas − saídas − comissão do vendedor − comissão do gerente; tudo em bcmath com duas casas
- [ ] T016 Criar `app/Services/SaldoCaixa.php` (R-03): `saldos(array $ids_vendedores, ?Carbon $ate = null): array` = Σ (valor − comissão − saída atual) das apostas ativas do balcão feitas até `ate` + Σ suprimentos − Σ retiradas com `ocorrido_em` ≤ `ate` − Σ `valor_recebido` das prestações não desfeitas, numa consulta por fonte agrupada por vendedor; docblock explicando por que equivale ao FR-009
- [ ] T017 Criar a estrutura do alcance da rede em `app/Services/ApuracaoCaixa.php`: `rede(Usuarios|null $foco)` devolve o mapa vendedor → gerente → supervisor do foco (com excluídos, como `AlcanceDashboard::ids_sub_hierarquia_com_excluidos`), numa consulta por nível, e os ids dos vendedores
- [ ] T018 Registrar em `routes/web.php` `GET painel/caixa` → `PainelController@caixa` (nome `painel.caixa`) e adicionar a ação `caixa()` em `app/Http/Controllers/PainelController.php` (Inertia `PanelCash`, docblock "Tela Caixa (spec 017); aba, tipo, período e foco vêm na query string")
- [ ] T019 [P] Adicionar o item "Caixa" (ícone `point_of_sale`, `href: '/painel/caixa'`, visível com `caixa.ver` ou `caixa.ver_clientes`) em `itens_menu` de `resources/js/components/PanelShell/index.jsx`, atualizando o comentário da lista
- [ ] T020 [P] Criar `resources/js/utils/periods.js`: `atalhos_periodo` (Hoje, Ontem, Esta semana — segunda a hoje —, Este mês, Personalizado), `periodo_do_atalho(chave)` → `{de, ate}` em `AAAA-MM-DD` no fuso de Brasília, `validar_periodo(de, ate)` (de ≤ até, até 1 ano, não futuro) e `texto_periodo(de, ate)` ("21/10/2026" ou "14/10/2026 a 21/10/2026")
- [ ] T021 [P] Criar `resources/js/components/PeriodFilter/` (`index.jsx`, `styles.jsx`) conforme contracts/paginas §2: atalhos (ativo na cor principal), Personalizado com "De"/"Até" de rótulo em cima, erro no campo e **Aplicar**, texto do período; celular com atalhos numa linha rolável
- [ ] T022 Criar `resources/js/pages/PanelCash/` (`index.jsx`, `styles.jsx`): `PanelShell` com título "Caixa", `usePainel`, volta para `/painel` sem `caixa.ver` nem `caixa.ver_clientes`, abas principais `PanelTabs` (Rede e Clientes, Clientes só Admin/Supervisor com `caixa.ver_clientes`; Vendedor sem abas), estado `aba`, `tipo`, `de`, `ate`, `usuarios_id` lido e gravado na query string (como `PanelQuotes`), `PeriodFilter` no cabeçalho e o carregamento (`Spinner`) no padrão de `pages/PanelUsers`; áreas de conteúdo vazias para as histórias

**Checkpoint**: fundação pronta — a tela abre, o menu aparece para quem pode, e o cálculo existe.

---

## Phase 3: User Story 1 - Acompanhar o caixa da rede no período (Priority: P1) 🎯 MVP

**Goal**: cartões e listas do período por nível, com trilha, abas de tipo e o caixa do próprio vendedor.

**Independent Test**: quickstart §1 e §2.

- [ ] T023 [US1] Criar `app/Http/Controllers/CaixaController.php` (HasMiddleware com `GarantirPermissaoCliente` e `caixa.ver`; usa `GarantirAlcanceCaixa`) com a ação `resumo` (`GET /caixa/resumo`, contracts/api §1.1): foco, trilha (do foco até o topo visível ao solicitante; o Admin começa em "Banca"), período e totais pelo `ApuracaoCaixa` + `SaldoCaixa`; `lancamentos`, `retiradas` e `saldo_caixa` null fora de `todas`; `comissao_gerente` null no Vendedor; `percentual_comissao_gerente` só no foco Gerente; `limites` só no foco Vendedor
- [ ] T024 [US1] Adicionar a ação `usuarios` (`GET /caixa/usuarios`, contracts/api §1.2) em `app/Http/Controllers/CaixaController.php`: nível abaixo do foco (Vendedor → 403), busca por nome ou login, inclui inativos e excluídos só com movimento ou saldo (campo `situacao`), ordenação por nome/entradas no banco e por líquido/saldo de caixa depois das somas (R-07), paginação padrão 20 e máximo 100, somas só das linhas da página (sem N+1), `limites` nas linhas de vendedor e `acoes` de cada linha conforme permissão e alcance (`lancar`, `prestar_contas`, `transacoes`, `ajustar_limite`, `grafico`, `exportar`; no gerente `prestar_contas`, `comissao_gerente`, `exportar`)
- [ ] T025 [US1] Registrar em `routes/api.php`, no grupo `auth:api` + `garantir_acesso`, `GET caixa/resumo` e `GET caixa/usuarios` (com `use` do controller em ordem alfabética)
- [ ] T026 [P] [US1] Criar `resources/js/hooks/useCaixa.js`: busca `resumo` e `usuarios` com os filtros (aba, tipo, período, foco, busca com atraso de 400 ms, ordenação, página) e o token, no padrão de `hooks/useUsuariosPainel.js`; estados carregando/erro/vazio separados para cartões e lista; `recarregar()` para depois das ações; 401 → `expirar()`
- [ ] T027 [P] [US1] Criar `resources/js/components/CashBreadcrumb/` (`index.jsx`, `styles.jsx`) conforme contracts/paginas §3.1: partes clicáveis, atual em negrito; no celular "‹ Voltar" + nome do nível atual
- [ ] T028 [P] [US1] Criar `resources/js/components/CashTable/` (`index.jsx`, `styles.jsx`) conforme contracts/paginas §3.4: colunas por configuração (`colunas = [{ chave, rotulo, tipo: 'dinheiro'|'numero'|'texto'|'limites', destaque }]`), nome clicável para descer (`ao_abrir`), selo inativo/excluído, cores verde/vermelho/cinza no líquido e no saldo, `ActionsMenu` com as ações recebidas, busca e ordenação na barra, estados vazio ("Nenhum movimento neste período. Experimente outro período.") e erro com "Tentar novamente", e no celular (até 900 px) cartões com nome, Líquido e Saldo de caixa em destaque e "ver mais" que expande o resto em pares rótulo/valor
- [ ] T029 [US1] Montar a aba Rede em `resources/js/pages/PanelCash/index.jsx`: `CashBreadcrumb`, `PanelTabs` de tipo (Todas, Pré-jogo, Ao vivo) com o aviso fora de Todas, cartões `StatCard` na ordem de contracts/paginas §3.3 (com a dica da comissão do gerente e os limites com cadeado no foco Vendedor) em grid 4/2 colunas com esqueleto, `CashTable` com as colunas de vendedor ou de gestor, descer de nível pelo nome, `Pagination`, botão de atualizar; para o Vendedor, o caixa próprio com os botões Transações e Gráfico (abrem nas histórias US3 e US6)

**Checkpoint**: consulta do caixa completa (MVP de leitura).

---

## Phase 4: User Story 2 - Prestar contas de um vendedor ou de um gerente (Priority: P1)

**Goal**: prestação com resumo e senha, em lote pelo gerente, sem períodos sobrepostos.

**Independent Test**: quickstart §5 (passos 1 a 5, 7 e 8).

- [ ] T030 [US2] Criar `app/Services/PrestacaoContasCaixa.php` (R-04): `resumo(Usuarios $vendedor, Carbon $ate)` com período, saldo anterior, entradas, comissão, saídas (por `apurada_em`), suprimentos, retiradas, `ajustes` (resto para fechar com o apurado), `valor_apurado` (`SaldoCaixa` em `ate`), apostas em aberto (quantidade e valor) e `tem_movimento`; `prestar(Usuarios $vendedor, Usuarios $autor, Carbon $ate, string $valor_recebido, ?string $observacao, ?string $lote = null)` em `DB::transaction` com `lockForUpdate` na linha do vendedor em `usuarios`, recusando `ate` futuro ou não posterior ao fim da última prestação não desfeita (422), sem movimento (422 "Nenhum movimento a prestar no período.") e fim já usado por prestação concorrente (409 "O caixa deste vendedor mudou; confira e tente de novo."), gravando `saldo_anterior`, `valor_apurado`, `valor_recebido`, `diferenca` = apurado − recebido e marcando os lançamentos abertos até `ate` com o id da prestação; `prestar_lote(Usuarios $gerente, Usuarios $autor, Carbon $ate, ?string $observacao)` numa transação só, vendedores em ordem de id, valor recebido = apurado de cada um, mesmo `lote` (UUID), pulando os sem movimento e devolvendo `prestacoes`, `pulados` e `total`
- [ ] T031 [P] [US2] Criar `app/Http/Requests/PrestacaoContasCaixaRequest.php` (`ate` data/hora obrigatória; `valor_recebido` numérico com até duas casas; `observacao` até 500; `senha` obrigatória com regra `Hash::check` na senha do usuário logado → "Senha incorreta.", no padrão de `AlterarSenhaAfiliadoRequest`) e `app/Http/Requests/PrestacaoContasLoteCaixaRequest.php` (mesmos campos sem `valor_recebido`)
- [ ] T032 [P] [US2] Criar `app/Http/Resources/CaixaPrestacoesContasResource.php` com os campos de contracts/api §2.1 (item de prestação), datas em ISO no fuso de Brasília e `pode_desfazer` vindo do controller
- [ ] T033 [US2] Criar `app/Http/Controllers/CaixaPrestacoesController.php` (`caixa.prestar_contas`; `GarantirAlcanceCaixa`) com `resumo_vendedor`, `store_vendedor`, `resumo_gerente` e `store_gerente` (contracts/api §3.1 a §3.4) e registrar as quatro rotas em `routes/api.php`
- [ ] T034 [US2] Criar `resources/js/components/SettlementModal/` (`index.jsx`, `styles.jsx`) conforme contracts/paginas §4: `ModalHeader` "Prestar contas"; modo vendedor com "Até", quadro do resumo (apurado em destaque com cor e "a banca deve ao vendedor" se negativo), apostas em aberto, valor recebido com `useCampoValor` preenchido com o apurado, aviso "Fica para a próxima prestação: R$ X" quando diferente, observação com contador, **Continuar** → passo de senha → **Confirmar prestação**; modo lote com a lista dos vendedores e o total, "N sem movimento serão pulados" e senha; carregando, erros do servidor (inclusive 409, que recarrega o resumo) e 100% da tela no celular
- [ ] T035 [US2] Ligar em `resources/js/pages/PanelCash/index.jsx` a ação "Prestar contas" da linha do vendedor e "Prestar contas de todos" da linha do gerente e do cabeçalho (foco Gerente com `caixa.prestar_contas`), abrindo o `SettlementModal`, com `alerta_sucesso` e `recarregar()` ao concluir

**Checkpoint**: caixa fecha com prestação individual e em lote.

---

## Phase 5: User Story 3 - Lançamentos, retiradas e histórico do vendedor (Priority: P2)

**Goal**: suprimentos e retiradas, histórico de transações, exclusão de lançamento aberto e desfazer a última prestação.

**Independent Test**: quickstart §4 e §5 (passos 6 e 9).

- [ ] T036 [US3] Criar `app/Services/LancamentosCaixa.php`: `lancar(Usuarios $vendedor, Usuarios $autor, TipoLancamentoCaixa $tipo, string $valor, Carbon $ocorrido_em, ?string $observacao)` com `lockForUpdate` na linha do vendedor, recusando data futura, "Data anterior à última prestação de contas." e suprimento para vendedor inativo ou excluído ("Vendedor inativo ou excluído não recebe suprimento.", FR-014a) (422); `excluir(CaixaLancamentos $lancamento, Usuarios $autor)` recusando o já prestado ("Este lançamento já entrou numa prestação de contas e não pode ser excluído.", 422) e gravando `excluido_por` antes do soft delete
- [ ] T037 [US3] Adicionar `desfazer(CaixaPrestacoesContas $prestacao, Usuarios $autor, string $motivo)` em `app/Services/PrestacaoContasCaixa.php` (FR-022a): `lockForUpdate` no vendedor; só a última não desfeita ("Só a última prestação de contas do vendedor pode ser desfeita.", 422); autor = quem fez ou superior dele (`ids_hierarquia_acima` do autor da prestação contém o solicitante), senão 403; grava `desfeita_por`, `motivo_desfazer`, soft delete e devolve os lançamentos dela para `caixa_prestacoes_contas_id = null`
- [ ] T038 [P] [US3] Criar `app/Http/Requests/StoreCaixaLancamentosRequest.php` (`tipo` in `Suprimento,Retirada`; `valor` > 0 com até duas casas; `ocorrido_em` data/hora obrigatória; `observacao` até 500) e `app/Http/Requests/DesfazerPrestacaoCaixaRequest.php` (`motivo` obrigatório até 500; `senha` com `Hash::check` → "Senha incorreta.")
- [ ] T039 [P] [US3] Criar `app/Http/Resources/CaixaLancamentosResource.php` com os campos de contracts/api §2.1 (item de lançamento) e `pode_excluir`
- [ ] T040 [US3] Criar `app/Http/Controllers/CaixaLancamentosController.php` (`GarantirAlcanceCaixa`): `transacoes` (`caixa.ver`; o Vendedor só o próprio; lançamentos e prestações do vendedor, inclusive excluídos e desfeitos, juntos do mais novo ao mais antigo por data/hora, filtro `tipo` e período opcional, paginação no banco por UNION das duas tabelas e 20/100 por página), `store` (`caixa.lancar`, 201) e `destroy` (`caixa.excluir_lancamento`, 204); adicionar `desfazer` em `app/Http/Controllers/CaixaPrestacoesController.php` (`caixa.prestar_contas`); registrar as quatro rotas de contracts/api §2.1 a §2.3 e §3.5 em `routes/api.php`
- [ ] T041 [P] [US3] Criar `resources/js/hooks/useTransacoesCaixa.js` (lista paginada com filtro de tipo e período, recarga depois de excluir/desfazer) e `resources/js/components/CashEntryModal/` (`index.jsx`, `styles.jsx`) conforme contracts/paginas §4: tipo em dois botões com a explicação (só Retirada para vendedor inativo ou excluído, FR-014a), valor com `useCampoValor`, data e hora (padrão agora), observação com contador e **Lançar**
- [ ] T042 [P] [US3] Criar `resources/js/components/CashTransactionsModal/` (`index.jsx`, `styles.jsx`, altura fixa desde a abertura) e `resources/js/components/UndoSettlementModal/` (`index.jsx`, `styles.jsx`) conforme contracts/paginas §4: filtro por tipo e período, itens com ícone e cor por tipo, excluídos e desfeitos riscados com autor e motivo, Excluir (com `confirmar`) e Desfazer conforme `pode_excluir`/`pode_desfazer`, paginação; o desfazer pede motivo e senha e usa cor de perigo
- [ ] T043 [US3] Ligar em `resources/js/pages/PanelCash/index.jsx` as ações "Lançamento" e "Transações" da linha do vendedor e o botão Transações do caixa próprio do Vendedor (só leitura), com `recarregar()` depois de lançar, excluir ou desfazer

**Checkpoint**: saldo de caixa completo com lançamentos e correção de prestação.

---

## Phase 6: User Story 4 - Caixa clientes no período (Priority: P2)

**Goal**: esportes, cassino, bônus, depósitos, saques, resultado do site e lista por cliente.

**Independent Test**: quickstart §7.

- [ ] T044 [US4] Alterar `app/Services/CaixaOnline.php`: `calcular(?Carbon $inicio = null, ?Carbon $fim = null)` (sem intervalo = o dia, como hoje, para a Dashboard não mudar) e método `por_cliente(array $ids_clientes, Carbon $inicio, Carbon $fim)` com as mesmas somas agrupadas por `clientes_id`; atualizar o docblock citando a spec 017
- [ ] T045 [US4] Adicionar em `app/Services/RelatorioCassino.php` o método público `somas(Carbon $inicio, Carbon $fim, ?string $carteira = null, ?array $ids_clientes = null): array` (entradas, saídas, resultado e comissão dos provedores, total e por cliente) reaproveitando `base()` e `somas()` privados, sem mudar `gerar()` (R-06)
- [ ] T046 [US4] Criar `app/Services/CaixaClientes.php` (R-06): `resumo(inicio, fim)` com `esportes` (`CaixaOnline`), `cassino` (`RelatorioCassino::somas` com carteira `Saldo`), `bonus` (`concedidos` = `clientes_transacoes` origem `Promoção`, tipo `Crédito`, carteiras de promoção; `convertidos` = origem `Conversão de bônus`, tipo `Crédito`, carteira `Saldo`), `depositos`, `saques` e `resultado_site` = saldo líquido de esportes + resultado do cassino − comissão dos provedores − bônus convertidos; `clientes(inicio, fim, busca, ordenar, direcao, por_pagina)` com os clientes com movimento no período (UNION dos ids das fontes, paginado no banco) e as somas só dos ids da página
- [ ] T047 [US4] Criar `app/Http/Controllers/CaixaClientesController.php` (`caixa.ver_clientes` e recusa 403 para quem não é Admin nem Supervisor, mesmo com a permissão) com `resumo` e `index` (contracts/api §4) e registrar `GET caixa/clientes/resumo` e `GET caixa/clientes` em `routes/api.php`
- [ ] T048 [US4] Montar a aba Clientes em `resources/js/pages/PanelCash/index.jsx` conforme contracts/paginas §5: blocos de `StatCard` (Esportes, Cassino, Bônus com a dica "não é dinheiro de verdade", Movimento e Resultado do site em destaque com a fórmula na dica) e `CashTable` com as colunas de cliente, busca e ordenação, sem ações; buscas do hook `useCaixa` com `aba=clientes`

**Checkpoint**: caixa do site completo.

---

## Phase 7: User Story 5 - Comissão do gerente e limites do vendedor (Priority: P3)

**Goal**: percentual da comissão do gerente, ajustar limite e a Dashboard com dados reais.

**Independent Test**: quickstart §3, §6.1 e §9.

- [ ] T049 [P] [US5] Criar `app/Http/Requests/ComissaoGerenteCaixaRequest.php` (`percentual` 0 a 100 com até duas casas) e `app/Http/Requests/LimitesVendedorCaixaRequest.php` (`geral`, `duplo`, `simples` numéricos positivos ou negativos com até duas casas, ao menos um diferente de zero)
- [ ] T050 [US5] Adicionar em `app/Http/Controllers/CaixaController.php` as ações `comissao_gerente` (`caixa.editar_comissao_gerente` + `garantir_gerente`; `UsuariosConfiguracoes::firstOrCreate` do gerente e grava `percentual_comissao_gerente`) e `limites` (`usuarios_configuracoes.editar` + `garantir_vendedor`; soma os valores positivos ou negativos aos limites com `lockForUpdate` e recusa com 422 "O limite {nome} ficaria negativo; o disponível agora é R$ X." sem gravar nenhum dos três quando algum ficaria abaixo de zero, R-05, FR-028) e registrar `PUT caixa/gerentes/{usuario}/comissao` e `POST caixa/vendedores/{usuario}/limites` em `routes/api.php`
- [ ] T051 [P] [US5] Criar `resources/js/components/ManagerCommissionModal/` e `resources/js/components/SellerLimitsModal/` (`index.jsx`, `styles.jsx` em cada) conforme contracts/paginas §4 (no `SellerLimitsModal`, campo "somar ou subtrair" por limite com prévia do novo valor e erro no campo quando ficaria abaixo de zero) e ligar as ações "Comissão do gerente" e "Ajustar limite" em `resources/js/pages/PanelCash/index.jsx`, com `recarregar()` ao salvar
- [ ] T052 [US5] Alterar `app/Services/ApuradoDashboard.php`: lançamentos e retiradas do dia pelo `ApuracaoCaixa::lancamentos_por_vendedor` no alcance do usuário e a comissão do gerente pelo percentual de cada gerente (`usuarios_configuracoes.percentual_comissao_gerente`) no lugar de `DadosFake`; `provisorios` = `[]`; atualizar o docblock (FR-034)
- [ ] T053 [US5] Remover `caixa_usuario()` e `percentual_comissao_gerente()` de `app/Fakes/DadosFake.php` (Princípio IX) e conferir com busca que nenhum outro arquivo os usa

**Checkpoint**: líquido da rede e Dashboard com dados reais.

---

## Phase 8: User Story 6 - Gráfico e exportação (Priority: P4)

**Goal**: gráfico por quantidade de palpites e planilhas .xlsx.

**Independent Test**: quickstart §6.2 e §8.

- [ ] T054 [US6] Adicionar a ação `grafico` (`GET /caixa/vendedores/{usuario}/grafico`, contracts/api §1.3) em `app/Http/Controllers/CaixaController.php`: quantidade de apostas ativas do vendedor no período por número de palpites (subconsulta agrupada por aposta), faixas "1" a "10" e "Mais de 10"; registrar a rota em `routes/api.php`
- [ ] T055 [P] [US6] Criar `resources/js/components/BetsByPicksModal/` (`index.jsx`, `styles.jsx`) com `ChartCanvas` em barras e as cores de `utils/charts.js`, vazio "Nenhuma aposta no período", e ligar a ação "Gráfico" (linha do vendedor e caixa próprio) em `resources/js/pages/PanelCash/index.jsx`
- [ ] T056 [US6] Criar `app/Services/ExportacaoCaixa.php` com PhpSpreadsheet (R-09): cabeçalho (nome do sistema, período, tipo, nível), totais e uma linha por usuário do nível (rede, pelo `ApuracaoCaixa`/`SaldoCaixa`, paginado internamente de 100 em 100, até 5.000 linhas) ou por cliente (`CaixaClientes`), colunas iguais às da tela, valores em formato de moeda e largura automática; devolve o arquivo temporário
- [ ] T057 [US6] Criar `app/Http/Controllers/CaixaExportacaoController.php` (`caixa.exportar`, mais `caixa.ver_clientes` e Admin/Supervisor no escopo `clientes`; alcance pelo foco) com `GET /caixa/exportar` (contracts/api §5.1, `Content-Disposition: attachment; filename="caixa-<nivel>-<de>-<ate>.xlsx"`, arquivo apagado depois do envio) e registrar a rota em `routes/api.php`
- [ ] T058 [US6] Ligar "Baixar planilha" no cabeçalho (rede e clientes) e nas linhas de gerente e vendedor em `resources/js/pages/PanelCash/index.jsx`: `api.get` com `responseType: 'blob'` e o token, download pelo nome do `Content-Disposition`, botão com carregando e sem clique duplo

**Checkpoint**: todas as histórias funcionando.

---

## Phase 9: Polish & Cross-Cutting Concerns

- [ ] T059 Regenerar `docs/postman/wssports_api.postman_collection.json` com a pasta "Caixa do painel" (16 rotas novas, exemplos de corpo e das respostas 201/409/422), substituindo o arquivo e mantendo `base_url` e `token`
- [ ] T060 [P] Acrescentar em `specs/014-painel-dashboard/spec.md`, em FR-021 e FR-022, a nota "Alterado pela spec 017: lançamentos, retiradas e o percentual da comissão do gerente passaram a ser reais; sem o selo em breve"
- [ ] T061 Rodar `php artisan migrate` (e `migrate:rollback` + `migrate` das quatro migrations), conferir as permissões `caixa.*` dos usuários existentes, `npm run build` e um passeio no Chrome sem janela por `/painel/caixa` (abas Rede e Clientes, cada nível, cada modal, 1366 px e 360 px, temas claro e escuro) sem erro de página; registrar o resultado nas notas abaixo
- [ ] T062 Validação manual pelo [quickstart.md](quickstart.md) §0 a §10 (SC-001 a SC-007), registrando o que foi conferido

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sem dependência; T003 depende de T002 (FK para `caixa_prestacoes_contas`).
- **Foundational (Phase 2)**: depende da fase 1; bloqueia todas as histórias. T007 depende de T005;
  T015–T017 dependem de T010–T012; T022 depende de T018–T021.
- **US1 (Phase 3)**: depende da fase 2. **MVP**.
- **US2 (Phase 4)**: depende da fase 2 e de US1 (linhas e ações da lista).
- **US3 (Phase 5)**: depende de US2 (desfazer e histórico de prestações).
- **US4 (Phase 6)**: depende só da fase 2 (pode andar em paralelo a US1–US3 no backend).
- **US5 (Phase 7)**: depende de US1 (lista) e de T015 (lançamentos do dia para a Dashboard).
- **US6 (Phase 8)**: depende de US1 (e de US4 para a planilha de clientes).
- **Polish**: depois das histórias desejadas.

### Within Each Story

- Service → request/resource → controller e rotas → frontend.
- `resources/js/pages/PanelCash/index.jsx` é o mesmo arquivo em T022, T029, T035, T043, T048, T051,
  T055 e T058: em sequência.
- `routes/api.php` é o mesmo em T025, T033, T040, T047, T050, T054 e T057: em sequência.
- `app/Http/Controllers/CaixaController.php` em T023, T024, T050 e T054: em sequência.
- `app/Services/PrestacaoContasCaixa.php` em T030 e T037: em sequência.

### Parallel Opportunities

- T004 ∥ T002; T006 ∥ T008 ∥ T009 ∥ T010 ∥ T011 ∥ T012; T019 ∥ T020 ∥ T021.
- US1: T026 ∥ T027 ∥ T028 enquanto T023–T025 andam no backend.
- US2: T031 ∥ T032 enquanto T030.
- US3: T038 ∥ T039; T041 ∥ T042 enquanto T040.
- US4 (backend T044–T047) em paralelo a US2/US3.

## Parallel Example: User Story 1

```bash
Task: "Criar resources/js/hooks/useCaixa.js"
Task: "Criar resources/js/components/CashBreadcrumb/"
Task: "Criar resources/js/components/CashTable/"
```

---

## Implementation Strategy

### MVP First (User Story 1)

1. Fases 1 e 2.
2. Fase 3 (consulta do caixa por nível).
3. **Parar e validar** pelo quickstart §1 e §2: a consulta já substitui as telas Caixa Geral,
   Gerentes e Vendedores do antigo.

### Incremental Delivery

1. Fundação → US1 (MVP) → US2 (prestação) → US3 (lançamentos e histórico) → US4 (clientes) → US5
   (comissão, limites e Dashboard real) → US6 (gráfico e planilha).
2. Cada história é validada sozinha antes da próxima; deploy só quando o responsável pedir (conferir
   antes as extensões PHP da planilha no servidor).

---

## Notes

- Commits só via `/speckit-git-commit`.
- Arquivos existentes alterados: só os da tabela do plan.md (Princípio IV).
- Datas de exemplo na validação: horário de Brasília.
