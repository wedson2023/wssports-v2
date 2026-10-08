---

description: "Lista de tarefas da feature Apostas (criação, validação de código e cancelamento)"
---

# Tasks: Apostas (criação, validação de código e cancelamento)

**Input**: Documentos de design em `specs/004-apostas/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md),
[data-model.md](data-model.md), [contracts/api.md](contracts/api.md), [quickstart.md](quickstart.md)

**Tests**: NÃO há tarefas de teste — a constituição proíbe testes automatizados. Cada user story é
validada manualmente pelos cenários do [quickstart.md](quickstart.md).

**Organization**: tarefas agrupadas por user story. As stories P1 seguem a ordem de dependência
(US1 → US5 → US7 → US2 → US3 → US4 → US6), depois as P2 (US8 → US9 → US10 → US11 → US12). Os
rótulos US1…US12 seguem a numeração da spec.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: pode rodar em paralelo (arquivos diferentes, sem dependências pendentes)
- **[Story]**: user story da tarefa (US1 a US12)
- Caminhos relativos à raiz do repositório

## Regras que valem para TODAS as tarefas

- **Princípio I**: métodos, variáveis, parâmetros, chaves JSON e colunas em `snake_case`; classes em
  `PascalCase` (sem acento); métodos exigidos pelo framework mantêm o nome (`handle`, `rules`,
  `messages`, `authorize`, `prepareForValidation`, `toArray`, `casts`, `middleware`, `up`, `down`,
  `run`, `render`). Rotas em kebab-case. Permissões `<recurso>.<acao>`.
- **Enums**: casos em `PascalCase` português; valores gravados exatamente como no
  [data-model.md](data-model.md) (ex.: `'Em análise'`, `'Pré-jogo'`, `'Depois de amanhã'`).
  Arquivos em UTF-8.
- **Princípio II**: comentários, mensagens de validação, de erro e de log em português.
- **Princípio IV**: alterar só os arquivos e trechos indicados. Arquivos existentes marcados com ⚠️
  estão na tabela "Arquivos existentes que serão alterados" do [plan.md](plan.md) e foram
  autorizados (2026-10-07). NÃO alterar `SaldoClientes`, `IdentificacaoPublico`, `Publico`,
  `AlcanceHierarquia`, `ConfiguracoesVendedores`, middlewares, `bootstrap/app.php`, `config/*`,
  migrations existentes nem os controllers de confrontos da spec 003.
- **Princípio V**: ao concluir cada tarefa, revisar em conjunto o código alterado e informar se
  houve ou não refatoração.
- **Banco**: só `php artisan migrate` e os seeders indicados. NUNCA `migrate:fresh` nem
  `migrate:refresh`.
- **Dinheiro (R-06)**: nunca `float`. Valores em centavos com `SaldoClientes::para_centavos` e
  `para_decimal`; produto das cotações e prêmio com `bcmath` (escala 10 no intermediário); prêmio
  truncado em centavos. Respostas com valores como texto de 2 casas (`"10.00"`).
- **SQL**: nunca montar SQL com dados da requisição; códigos de cotação só pela
  `App\Support\CodigosCotacao` (FR-006).
- **Concorrência (R-07)**: bloqueios com `lockForUpdate` sempre nesta ordem: aposta → confrontos /
  confrontos_ao_vivo (em ordem de id) → `usuarios_configuracoes` do vendedor ou `clientes` (via
  `SaldoClientes`) → `clientes_rollovers` (em ordem de id).
- **Datas**: UTC no banco; "dia" e período no fuso `App\Support\FusoSistema` (`-03:00`, R-05);
  respostas no fuso pedido em `fuso_horario`.
- **Padrões das specs 001 a 003**: FormRequests com `messages()` em português; controllers com
  `HasMiddleware` e `self::permissao_cliente('<permissão>', only: [...])` (trait
  `GarantirPermissaoCliente`); erros `{"message": "..."}`; limite de tentativas com `RateLimiter`
  no FormRequest (padrão do `StoreClientesRequest`); serviços em `app/Services`.
- **Respostas**: 422 para regra (`RegraApostaException`), 409 para cotação alterada
  (`CotacoesAlteradasException`), 404 "Aposta não encontrada." para inexistente ou fora do alcance.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: utilitários sem dependências, usados por várias stories

- [X] T001 [P] Criar `App\Support\FusoSistema` em app/Support/FusoSistema.php: constante `FUSO = '-03:00'` e métodos `inicio_do_dia(?Carbon $momento = null): Carbon` e `fim_do_dia_em(int $dias_a_frente): Carbon`, ambos devolvendo em UTC (R-05)
- [X] T002 [P] Criar `App\Support\CodigoAposta` em app/Support/CodigoAposta.php: constante `ALFABETO = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'` (32 símbolos, "sem 0, O, 1, I"), `gerar(): string` com 8 caracteres via `random_int` e `normalizar(string $codigo): string` (maiúsculas, sem espaços) (R-09)
- [X] T003 [P] Criar `App\Support\NomesCotacoes` em app/Support/NomesCotacoes.php: mapa `odd1`…`odd323` → nome legível copiado de `ConfigController::categorias()` do sistema antigo (`C:\Users\wedso\OneDrive\Área de Trabalho\projetos\wssports.bet\app\Http\Controllers\ConfigController.php`, linha 1585), com a capitalização "Casa", "Empate"…; método `nome(string $codigo, ?string $jogador_tipo = null): string` que devolve "Jogador: {tipo}" para `jogador` (R-17)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: banco, enums, models, permissões e os serviços centrais usados por todas as stories

**⚠️ CRITICAL**: nenhuma user story começa antes desta fase

### Enums (app/Enums)

- [X] T004 [P] Criar `SituacaoAposta` em app/Enums/SituacaoAposta.php: `Pendente`, `EmAnálise = 'Em análise'`, `Ativa`, `Recusada`, `Expirada`, `Cancelada`
- [X] T005 [P] Criar `ResultadoAposta` em app/Enums/ResultadoAposta.php: `Aguardando`, `Vencedor`, `Perdedor`
- [X] T006 [P] Criar `TipoAposta` em app/Enums/TipoAposta.php: `PréJogo = 'Pré-jogo'`, `AoVivo = 'Ao vivo'`
- [X] T007 [P] Criar `FormaPagamento` em app/Enums/FormaPagamento.php: `Dinheiro`, `Saldo`, `PromoçãoEsportes = 'Promoção esportes'`
- [X] T008 [P] Criar `AceitarAlteracoes` em app/Enums/AceitarAlteracoes.php: `Nenhuma`, `SomenteParaMaior = 'Somente para maior'`, `Qualquer`
- [X] T009 [P] Criar `SituacaoPalpite` em app/Enums/SituacaoPalpite.php: `Ativo`, `Cancelado`
- [X] T010 [P] Criar `AcaoHistoricoAposta` em app/Enums/AcaoHistoricoAposta.php: `CancelarPalpite = 'Cancelar palpite'`, `RestaurarPalpite = 'Restaurar palpite'`
- [X] T011 [P] Criar `PeriodoJogos` em app/Enums/PeriodoJogos.php: `Hoje`, `Amanhã`, `DepoisDeAmanhã = 'Depois de amanhã'`, com método `dias_a_frente(): int` (0, 1, 2)
- [X] T012 [P] Criar `TipoRollover` em app/Enums/TipoRollover.php: `Depósito`, `Bônus`

### Migrations (database/migrations) — colunas, tipos e padrões exatamente como no [data-model.md](data-model.md)

- [X] T013 [P] Criar database/migrations/2026_10_07_000001_create_apostas_table.php: tabela `apostas` com todas as colunas da seção "Tabela `apostas`" (`codigo` char(8) único; `chave_idempotencia` char(36) única; `chave_validacao` char(36) null única; `nome` varchar(100); `situacao` varchar(20); `resultado` varchar(20) padrão `'Aguardando'`; `tipo` varchar(20); `forma_pagamento` varchar(30) null; `aceitar_alteracoes` varchar(20) padrão `'Nenhuma'`; `valor`, `premio`, `valor_acrescido` (padrão 0), `comissao` (padrão 0) e `premio_maximo` decimal(15,2); `cotacao_total` decimal(14,2); `percentual_comissao`, `comissao_por_premio`, `ganho_multiplo_palpites` decimal(5,2) padrão 0; `multiplicador` unsigned int; `tempo_cancelamento_aposta` unsigned smallint null; FKs null `usuarios_id`, `clientes_id`, `cancelada_por`; timestamps `recebida_em`, `validada_em`, `decidida_em`, `confirmada_em`, `expira_em`, `cancelada_em`; `motivo_recusa` varchar(255) null; `assinatura` char(64) null; `ip_*` varchar(45) null; `user_agent_*` varchar(255) null), `timestamps()`, `softDeletes()` e os índices listados
- [X] T014 [P] Criar database/migrations/2026_10_07_000002_create_apostas_palpites_table.php: tabela `apostas_palpites` (FKs `apostas_id`, `confrontos_id`, `campeonatos_id`; FKs null `confrontos_ao_vivo_id`, `confrontos_jogadores_id`, `cancelado_por`, `restaurado_por`; `esporte` varchar(50); `codigo_cotacao` varchar(10); `jogador_tipo` varchar(60) null; `cotacao_vista`, `cotacao_original`, `cotacao_final` decimal(8,2); `dados_ao_vivo_envio` e `dados_ao_vivo_decisao` json null; `situacao` varchar(20) padrão `'Ativo'`; `cancelado_em`, `restaurado_em` timestamp null), `timestamps()`, `softDeletes()`, único (`apostas_id`, `confrontos_id`) e índices (`confrontos_id`), (`confrontos_ao_vivo_id`)
- [X] T015 [P] Criar database/migrations/2026_10_07_000003_create_apostas_historico_table.php: tabela `apostas_historico` (FKs `apostas_id`, `apostas_palpites_id`, `usuarios_id`; `acao` varchar(30); `cotacao_total_anterior`/`_posterior` decimal(14,2); `premio_anterior`/`_posterior` e `valor_acrescido_anterior`/`_posterior` decimal(15,2); `ip` varchar(45); `user_agent` varchar(255) null), `timestamps()`, `softDeletes()`, índice (`apostas_id`, `created_at`)
- [X] T016 [P] Criar database/migrations/2026_10_07_000004_create_clientes_rollovers_table.php: tabela `clientes_rollovers` (FKs `clientes_id`, `clientes_transacoes_id`; FK null `clientes_promocoes_id`; `tipo` varchar(20); `carteira` varchar(30); `valor_creditado`, `valor_exigido` decimal(15,2); `vezes` unsigned smallint; `valor_apostado` decimal(15,2) padrão 0; `valor_minimo_aposta`, `valor_maximo_aposta` decimal(15,2) null; `odd_minima_aposta_simples`, `odd_minima_aposta_multipla` decimal(8,2) null; `cumprido_em`, `cancelado_em` timestamp null), `timestamps()`, `softDeletes()`, índice (`clientes_id`, `tipo`, `carteira`, `cumprido_em`, `cancelado_em`)
- [X] T017 [P] Criar database/migrations/2026_10_07_000005_create_apostas_rollovers_table.php: tabela `apostas_rollovers` (FKs `apostas_id`, `clientes_rollovers_id`; `valor` decimal(15,2); `desfeito_em` timestamp null), `timestamps()`, `softDeletes()`, único (`apostas_id`, `clientes_rollovers_id`)
- [X] T018 [P] Criar database/migrations/2026_10_07_000006_add_apostas_usuarios_configuracoes_table.php: em `usuarios_configuracoes`, as colunas e padrões da seção "`usuarios_configuracoes`" do data-model (`realizar_aposta` true, `cancelar_aposta` true, `tempo_cancelamento_aposta` 5, `apostar_jogadores` true, `periodo_jogos` `'Depois de amanhã'`, `data_travamento_sistema` dateTime null, `mensagem_bilhete` varchar(500) `'BOA SORTE!'`, `delay_ao_vivo` 15, `quantidade_minima_opcoes` 1, `quantidade_maxima_opcoes` 20, `valor_minimo_aposta` 2.00, `valor_maximo_aposta` 1000.00, `odd_minima` 1.00, `premio_maximo` 5000.00, `multiplicador` 1000, `ganho_multiplo_palpites` 0, `comissao_pre_jogo_1`…`_12` e `comissao_ao_vivo_1`…`_12` decimal(5,2) 0, `comissao_por_premio` 0, `limite_simples`/`limite_duplo`/`limite_geral` 5000.00); `down()` remove as colunas
- [X] T019 [P] Criar database/migrations/2026_10_07_000007_add_apostas_clientes_configuracoes_table.php: em `clientes_configuracoes`, `apostar_jogadores` true, `periodo_jogos` `'Depois de amanhã'`, `delay_ao_vivo` 15, `multiplicador` 1000, `ganho_multiplo_palpites` 0; `down()` remove
- [X] T020 [P] Criar database/migrations/2026_10_07_000008_add_apostas_visitantes_configuracoes_table.php: em `visitantes_configuracoes`, as colunas da seção "`visitantes_configuracoes`" (incluindo `horas_validade_codigo` 48 e `ganho_multiplo_palpites` 0); `down()` remove
- [X] T021 [P] Criar database/migrations/2026_10_07_000009_add_bilhete_configuracoes_table.php: em `configuracoes`, `nome_sistema` varchar(100) `'WSSports'` e `mensagem_bilhete` varchar(500) `'BOA SORTE!'`; `down()` remove
- [X] T022 [P] Criar database/migrations/2026_10_07_000010_add_limite_valor_apostado_confrontos_table.php: `confrontos.limite_valor_apostado` decimal(15,2) padrão 50000.00 e `confrontos_ao_vivo.limite_valor_apostado` decimal(15,2) padrão 5000.00; `down()` remove

### Models

- [X] T023 [P] Criar `Apostas` em app/Models/Apostas.php (`SoftDeletes`, tabela `apostas`, casts dos enums de T004–T008, decimais como string, datas; relações `palpites()`, `palpites_ativos()`, `historico()`, `rollovers()`, `vendedor()` → `Usuarios` por `usuarios_id`, `cliente()`, `autor_cancelamento()`; método estático `buscar_pelo_codigo(string $codigo): Builder` usando `CodigoAposta::normalizar` — não usar scope local, para não depender da convenção `scope*` do framework)
- [X] T024 [P] Criar `ApostasPalpites` em app/Models/ApostasPalpites.php (`SoftDeletes`; casts `situacao` → `SituacaoPalpite`, `dados_ao_vivo_*` → array; relações `aposta()`, `confronto()`, `confronto_ao_vivo()`, `campeonato()`, `jogador()` → `ConfrontosJogadores`)
- [X] T025 [P] Criar `ApostasHistorico` em app/Models/ApostasHistorico.php (`SoftDeletes`; tabela `apostas_historico`; cast `acao` → `AcaoHistoricoAposta`; relações `aposta()`, `palpite()`, `usuario()`)
- [X] T026 [P] Criar `ClientesRollovers` em app/Models/ClientesRollovers.php (`SoftDeletes`; casts `tipo` → `TipoRollover`, `carteira` → `Carteira`; scope `pendentes()` = `cumprido_em` e `cancelado_em` nulos, ordenado por id)
- [X] T027 [P] Criar `ApostasRollovers` em app/Models/ApostasRollovers.php (`SoftDeletes`; relações `aposta()`, `rollover()`)
- [X] T028 [P] ⚠️ Alterar app/Models/UsuariosConfiguracoes.php: acrescentar as colunas novas de T018 em `CAMPOS`, `fillable` e `casts` (`periodo_jogos` → `PeriodoJogos`, `data_travamento_sistema` → datetime, booleanos); método `percentual_comissao(int $quantidade_palpites, bool $ao_vivo): string` (coluna `min($quantidade, 12)`)
- [X] T029 [P] ⚠️ Alterar app/Models/ClientesConfiguracoes.php: `fillable` e `casts` das colunas de T019
- [X] T030 [P] ⚠️ Alterar app/Models/VisitantesConfiguracoes.php: `CAMPOS`, `fillable` e `casts` das colunas de T020
- [X] T031 [P] ⚠️ Alterar app/Models/Configuracoes.php: `fillable` de `nome_sistema` e `mensagem_bilhete`
- [X] T032 [P] ⚠️ Alterar app/Models/Confrontos.php e app/Models/ConfrontosAoVivo.php: `limite_valor_apostado` em `fillable` e `casts` (decimal:2)

### Permissões

- [X] T033 ⚠️ Alterar app/Enums/Funcao.php (R-15): constante `PERMISSOES_APOSTAS` = `apostas.criar`, `apostas.validar`, `apostas.cancelar`, `apostas.cancelar_iniciada`, `apostas.editar`; `confrontos.alterar_limite` em `PERMISSOES_CONFRONTOS` (fora de `PERMISSOES_CONFRONTOS_GERENTE`); `pode_usar()`: `apostas.criar`/`apostas.validar` só Vendedor, `apostas.cancelar` todas, `apostas.cancelar_iniciada`/`apostas.editar` só Gerente, Supervisor e Admin; `permissoes_padrao()`: Vendedor passa a receber `apostas.criar`, `apostas.validar` e `apostas.cancelar`, e os gestores as de apostas que podem usar
- [X] T034 ⚠️ Alterar database/seeders/PapeisPermissoesSeeder.php: incluir `...Funcao::PERMISSOES_APOSTAS` na criação das permissões
- [X] T035 Criar database/seeders/ApostasSeeder.php: dar a cada usuário existente as permissões de apostas (e `confrontos.alterar_limite` a Admin e Supervisor) conforme `Funcao::pode_usar`, sem remover as que ele já tem (idempotente)
- [X] T036 ⚠️ Alterar database/seeders/DatabaseSeeder.php: `$this->call(ApostasSeeder::class)` ao final

### Serviços centrais

- [X] T037 [P] Criar `App\Exceptions\RegraApostaException` em app/Exceptions/RegraApostaException.php: mensagem + `indisponiveis` (lista opcional `{indice, confrontos_id|confrontos_ao_vivo_id, motivo}`); `render()` devolve 422 `{"message", "indisponiveis"?}` (contrato)
- [X] T038 ⚠️ Alterar app/Services/CalculoCotacoes.php (R-03): `ajustar(Publico, string $tipo, Collection $itens, array $codigos = self::CODIGOS_LISTAGEM)` usando `$codigos` no lugar da constante (listagem continua igual); novo `ajustar_jogadores(Publico, Collection $jogadores): array` que aplica ao `odd` as porcentagens do código `jogador` (público + campeonato) e o teto `jogador`, com o mesmo arredondamento e o piso de 1,00 só para cotação > 0
- [X] T039 Criar `App\Services\RegrasExibicao` em app/Services/RegrasExibicao.php (R-04): mover de `ListagemConfrontos` a lógica de `esportes_permitidos`, `excluir_nao_permitidos` e `garantir_ao_vivo_habilitado` (mesmo comportamento) e acrescentar: `fim_do_periodo(Publico): Carbon` (FR-018, `PeriodoJogos` do cliente, vendedor ou visitante; gestor = depois de amanhã), `data_travamento(Publico): ?Carbon` (só vendedor e visitante, FR-019), `excluir_jogos_no_ao_vivo(Builder, string $coluna)` (whereNotExists em `confrontos_ao_vivo` não excluído com situação em `SituacaoAoVivo`, FR-017) e `pode_apostar_jogadores(Publico): bool`
- [X] T040 ⚠️ Alterar app/Services/ListagemConfrontos.php: usar `RegrasExibicao` no lugar dos métodos movidos; no pré-jogo, limitar o fim da janela a `min(fim do dia pedido, fim_do_periodo, data_travamento)`, devolver vazio quando a data de travamento já passou e excluir os jogos que estão no ao vivo; no ao vivo, devolver vazio com a data de travamento passada (FR-017, FR-065)
- [X] T041 [P] Criar `App\Services\CalculoPremio` em app/Services/CalculoPremio.php (R-06, FR-025): `calcular(string $valor, array $cotacoes, string $multiplicador, string $premio_maximo, string $ganho_multiplo_palpites): array` devolvendo `cotacao_total` (2 casas, meio para cima), `premio` (menor entre valor × produto truncado em centavos, valor × multiplicador e prêmio máximo), `valor_acrescido` (só com 3+ cotações: ganho% do prêmio, reduzido para o total não passar do prêmio máximo) e `total_a_pagar`; tudo com `bcmath`
- [X] T042 [P] Criar `App\Services\AssinaturaApostas` em app/Services/AssinaturaApostas.php (R-11): `assinar(Apostas): string` e `conferir(Apostas): bool` com HMAC-SHA256 do JSON canônico (chaves ordenadas: código, valor, cotação total, prêmio, acréscimo, `confirmada_em` e palpites ativos com confronto, código, jogador, tipo e cotação final), chave `hash_hmac('sha256', 'apostas', config('app.key'))`, comparação com `hash_equals`
- [X] T043 Criar `App\Services\RegrasAposta` em app/Services/RegrasAposta.php: resolve a configuração do apostador (vendedor `UsuariosConfiguracoes::do_vendedor`, cliente `clientes_configuracoes`, visitante `VisitantesConfiguracoes::atual`) num objeto com os campos usados; confere, na ordem e com as mensagens exatas da spec, FR-014 (ativo, `realizar_aposta`), FR-015 (≥ 1 palpite; repetição conferida pelo `confrontos_id` já resolvido — o palpite ao vivo é convertido de `confrontos_ao_vivo_id` para o `confrontos_id` do jogo antes da conferência — com "Não é possível cadastrar jogos repetidos na aposta"), FR-016 (existente, ativo, esporte permitido, não permitidos via `RegrasExibicao`, jogador só com `apostar_jogadores` e só pré-jogo, e o `confrontos_jogadores_id` precisa pertencer ao `confrontos_id` do palpite, senão "Jogador não pertence ao confronto."), FR-017, FR-018, FR-019, FR-020 (ao vivo habilitado incluindo `apostar_ao_vivo` do cliente, trava geral, situação em andamento, minuto limite, `segundos_trava_ao_vivo`; visitante sem ao vivo) e FR-021 (odd mínima e máxima sobre a cotação total, valor mínimo e máximo, quantidade mínima e máxima de opções); lança `RegraApostaException`
- [X] T044 Criar `App\Http\Requests\ApostasRequest` em app/Http/Requests/ApostasRequest.php (contrato "Corpo do pedido de aposta"): `chave_idempotencia` required uuid (também na validação do código); `nome` required string max:100 (remover HTML em `prepareForValidation` com `strip_tags`); `valor` required numeric > 0 regex 2 casas; `aceitar_alteracoes` in dos valores de `AceitarAlteracoes` (padrão `Nenhuma`); `palpites` required array 1–50; cada palpite com exatamente um de `confrontos_id`/`confrontos_ao_vivo_id` (integer), `codigo_cotacao` válido por `CodigosCotacao::e_regra`, `confrontos_jogadores_id` required_if `jogador` (proibido no ao vivo), `cotacao_vista` numeric ≥ 1 regex 2 casas; `fuso_horario` opcional; mensagens em português
- [X] T045 Criar `App\Http\Resources\ComprovanteApostaResource` em app/Http/Resources/ComprovanteApostaResource.php (contrato "Comprovante"): todos os campos do JSON do contrato; `vendedor` = nome do vendedor ou `"Cliente"` (nulo na Pendente); `nome_sistema` e `mensagem_bilhete` lidos na hora das configurações (vendedor da aposta ou `configuracoes`); `premio_liquido` = total − `comissao_por_premio`% só em `Dinheiro`; `comissao`/`percentual_comissao` só para o vendedor da aposta ou a hierarquia dele; palpites com `mercado` por `NomesCotacoes`, datas no fuso pedido; Em análise só `codigo`, `situacao`, `segundos_restantes`; Recusada/Expirada com `motivo_recusa`; nunca porcentagens, `cotacao_original`, IPs ou user agents (FR-054)
- [X] T046 Criar `App\Services\CriacaoApostas` em app/Services/CriacaoApostas.php (núcleo comum, sem as regras específicas de cada público): idempotência pela `chave_idempotencia` (mesmo vendedor ou cliente → devolve a existente; visitante → devolve a existente só se ainda Pendente e criada pelo mesmo IP; qualquer outro caso → 422 "Chave de idempotência já usada."); carga dos confrontos/jogos ao vivo/jogadores dos palpites; cotações atuais via `CalculoCotacoes::ajustar(..., códigos dos palpites)` e `ajustar_jogadores`; `RegrasAposta`; `CalculoPremio` com a configuração do apostador; gravação de `apostas` (código por `CodigoAposta::gerar` com até 5 tentativas na colisão, `recebida_em = now()`, IP e user agent de criação, valores da configuração copiados — FR-026, sem `mensagem_bilhete`) e `apostas_palpites` (`cotacao_vista`, `cotacao_original` do provedor, `cotacao_final`, `jogador_tipo` copiado de `confrontos_jogadores.tipo`), tudo em `DB::transaction`; ponto de extensão por público (vendedor, cliente, visitante) para as tarefas das stories

**Checkpoint**: banco migrado, seeders rodados, listagem da spec 003 funcionando como antes (mais as regras novas de período, travamento e jogo no ao vivo)

---

## Phase 3: User Story 1 - Vendedor faz uma aposta no pré-jogo (Priority: P1) 🎯 MVP

**Goal**: o vendedor aposta no pré-jogo; a aposta fica Ativa com prêmio, comissão e limites de venda abatidos

**Independent Test**: quickstart cenários 1 a 5

- [X] T047 [US1] Em app/Services/CriacaoApostas.php, completar o fluxo do vendedor: dentro da transação, bloquear `usuarios_configuracoes` do vendedor, conferir e abater `limite_simples` (1 palpite) ou `limite_duplo` (2 ou mais) e `limite_geral` com a mensagem "Restam R$ X do seu limite simples/duplo/geral" (FR-023); comissão = valor × `percentual_comissao(quantidade, ao_vivo)` gravando `comissao` e `percentual_comissao` (FR-024); `forma_pagamento = Dinheiro`, `situacao = Ativa`, `confirmada_em = now()`, `tempo_cancelamento_aposta` da configuração; assinatura por `AssinaturaApostas`
- [X] T048 [US1] Criar app/Http/Controllers/ApostasController.php: `store(ApostasRequest)` com `permissao_cliente('apostas.criar', only: ['store'])`, chamando `CriacaoApostas` para o vendedor logado e devolvendo 201 `{"data": ComprovanteApostaResource}` (200 no envio repetido); `situacao(string $codigo)` só para o vendedor da aposta (senão 404 "Aposta não encontrada.") devolvendo o comprovante
- [X] T049 [US1] ⚠️ Alterar routes/api.php: no grupo `auth:api` + `garantir_acesso`, `POST apostas` e `GET apostas/{codigo}/situacao` (`ApostasController`), com o `use`

**Checkpoint**: vendedor aposta no pré-jogo e recebe o comprovante (MVP)

---

## Phase 4: User Story 5 - Cotação alterada ou indisponível (Priority: P1)

**Goal**: aposta com cotação diferente da vista pede confirmação (409); cotação indisponível recusa (422), nunca 1,00

**Independent Test**: quickstart cenários 7 a 9

- [X] T050 [P] [US5] Criar `App\Exceptions\CotacoesAlteradasException` em app/Exceptions/CotacoesAlteradasException.php: `alteracoes` (`indice`, `confrontos_id`/`confrontos_ao_vivo_id`, `codigo_cotacao`, `cotacao_vista`, `cotacao_atual`), `cotacao_total`, `premio`, `valor_acrescido`, `total_a_pagar`; `render()` devolve 409 com a mensagem "Houve alteração nas cotações. Confira o novo prêmio e confirme para continuar." (contrato)
- [X] T051 [P] [US5] Criar `App\Services\ConferenciaCotacoes` em app/Services/ConferenciaCotacoes.php (R-16, FR-027 a FR-029): `indisponiveis(array $palpites, array $cotacoes_atuais): array` (cotação zerada/ausente, jogo travado, trava geral) e `conferir(array $palpites, array $cotacoes_atuais, AceitarAlteracoes $preferencia): array` que devolve as cotações a usar ou as alterações fora da preferência (Nenhuma: qualquer diferença; Somente para maior: só as que caíram; Qualquer: nenhuma)
- [X] T052 [US5] Em app/Services/CriacaoApostas.php, antes das regras de prêmio: lançar `RegraApostaException` com `indisponiveis` quando houver; usar `ConferenciaCotacoes::conferir` e lançar `CotacoesAlteradasException` com o prêmio recalculado pelas cotações atuais quando houver alteração fora da preferência; nunca substituir cotação por 1,00 (FR-027, FR-030)

**Checkpoint**: confirmação de cotação funcionando para o vendedor

---

## Phase 5: User Story 7 - Limites da banca e cálculo do prêmio (Priority: P1)

**Goal**: multiplicador, prêmio máximo, acréscimo e limite de valor apostado por confronto

**Independent Test**: quickstart cenários 10 a 12

- [X] T053 [US7] Em app/Services/CriacaoApostas.php, limite por confronto (FR-022): bloquear as linhas de `confrontos`/`confrontos_ao_vivo` dos palpites em ordem de id; somar `valor` das apostas `Ativa` e `Em análise` com o confronto (pré-jogo por `confrontos_id` sem `confrontos_ao_vivo_id`; ao vivo por `confrontos_ao_vivo_id`); recusar com "Restam R$ X de limite de aposta no confronto CASA x FORA" quando a soma + valor passar de `limite_valor_apostado`; vale para vendedor e cliente, não para a Pendente
- [X] T054 [P] [US7] Criar app/Http/Requests/LimiteConfrontoRequest.php: `limite_valor_apostado` required numeric > 0 regex 2 casas; mensagens em português
- [X] T055 [US7] Criar app/Http/Controllers/ConfrontosLimitesController.php: `pre_jogo(LimiteConfrontoRequest, Confrontos)` e `ao_vivo(LimiteConfrontoRequest, ConfrontosAoVivo)` com `permissao_cliente('confrontos.alterar_limite')`, gravando o limite e devolvendo `{"data": {"id", "limite_valor_apostado", "valor_apostado"}}` (soma das Ativas e Em análise)
- [X] T056 [US7] ⚠️ Alterar routes/api.php: `PATCH confrontos/{confronto}/limite` e `PATCH confrontos-ao-vivo/{confronto_ao_vivo}/limite` (`ConfrontosLimitesController`), com `->missing()` "Confronto não encontrado."

**Checkpoint**: prêmio e limites conferidos nas apostas do vendedor

---

## Phase 6: User Story 2 - Cliente aposta com o próprio saldo (Priority: P1)

**Goal**: o cliente aposta pela área do cliente com uma única carteira (saldo real primeiro)

**Independent Test**: quickstart cenários 13 a 16

- [X] T057 [P] [US2] Criar `App\Services\EscolhaCarteira` em app/Services/EscolhaCarteira.php (R-14, FR-043): com o cliente já bloqueado, saldo real ≥ valor → `Carteira::Saldo`; senão `saldo_promocao_esportes` ≥ valor → `Carteira::PromoçãoEsportes`; senão `RegraApostaException` "Você não tem saldo suficiente para realizar esta aposta."; nunca combina nem usa a Promoção cassino
- [X] T058 [US2] Em app/Services/CriacaoApostas.php, fluxo do cliente: na transação, bloquear a linha do cliente; conferir o valor máximo diário (soma das apostas Ativa e Em análise de hoje no `FusoSistema` + valor ≤ `valor_maximo_diario`, FR-021); escolher a carteira; debitar por `SaldoClientes::debitar(cliente, carteira, valor, OrigemTransacao::Aposta, referencia_id: aposta->id)` (FR-044); `forma_pagamento` `Saldo` ou `Promoção esportes`; `situacao = Ativa`; sem comissão e sem limites de venda; assinatura
- [X] T059 [US2] Criar app/Http/Controllers/AreaClienteApostasController.php: `store(ApostasRequest)` para o cliente do guard `clientes` (201/200) e `situacao(string $codigo)` só para o cliente da aposta (senão 404)
- [X] T060 [US2] ⚠️ Alterar routes/api.php: dentro do grupo autenticado de `area-cliente`, `POST apostas` e `GET apostas/{codigo}/situacao` (`AreaClienteApostasController`)

**Checkpoint**: cliente aposta com saldo, sem gasto duplo

---

## Phase 7: User Story 3 - Visitante gera um código de aposta (Priority: P1)

**Goal**: o visitante gera uma aposta Pendente com código, só pré-jogo

**Independent Test**: quickstart cenários 18 e 19

- [X] T061 [P] [US3] Criar app/Http/Requests/ApostaVisitanteRequest.php estendendo `ApostasRequest`: proibir `confrontos_ao_vivo_id` com "Para apostar no ao vivo é preciso fazer login." e aplicar `RateLimiter` de 10/min por IP com "Muitas tentativas. Tente novamente em N segundos." (R-10)
- [X] T062 [US3] Em app/Services/CriacaoApostas.php, fluxo do visitante (FR-037): regras de `visitantes_configuracoes`; `situacao = Pendente`, `expira_em = recebida_em + horas_validade_codigo`, sem vendedor, cliente, forma de pagamento, limites ou assinatura; FR-019 com "Sistema travado, procure seu gerente."
- [X] T063 [US3] Criar app/Http/Controllers/PublicoApostasController.php com `store(ApostaVisitanteRequest)` devolvendo 201 com o comprovante da Pendente (inclui `expira_em`)
- [X] T064 [US3] ⚠️ Alterar routes/api.php: rota pública `POST publico/apostas` (`PublicoApostasController@store`)

**Checkpoint**: visitante gera código

---

## Phase 8: User Story 4 - Vendedor valida o código do visitante (Priority: P1)

**Goal**: simulação com as cotações do vendedor e validação do código, uma única vez

**Independent Test**: quickstart cenários 20 a 24

- [X] T065 [P] [US4] Criar app/Http/Resources/SimulacaoApostaResource.php no formato do contrato (`GET /api/apostas/pendentes/{codigo}`): código, nome, valor, `expira_em`, prêmio pelas regras do vendedor e palpites com `indice`, `mercado`, times, início, `cotacao` do vendedor, `disponivel` e `motivo`
- [X] T066 [US4] Criar `App\Services\ValidacaoCodigos` em app/Services/ValidacaoCodigos.php (FR-038 a FR-041, R-12): `simular(Usuarios, string $codigo)` sem gravar (recalcula com `CalculoCotacoes` e marca cada palpite com `RegrasAposta`); `validar(Usuarios, string $codigo, array $dados)` com lock da aposta, conferência de Pendente e de expiração (primeiro jogo iniciado ou `expira_em` passado → grava Expirada e recusa), só pré-jogo, todas as regras do vendedor via `CriacaoApostas` (inclusive `ConferenciaCotacoes` → 409 com a cotação da simulação como vista, FR-039a), troca dos palpites (soft delete dos antigos e criação dos novos), `usuarios_id`, `validada_em`, `confirmada_em`, IP/user agent de validação, limites, comissão e assinatura; mesmo código; grava a `chave_idempotencia` do pedido em `chave_validacao`; repetição da validação com a mesma chave pelo mesmo vendedor → 200 com o comprovante já validado; já validada (com outra chave ou outro vendedor) ou inexistente → 404 "Aposta não encontrada ou já validada."
- [X] T067 [US4] Criar app/Http/Controllers/ValidacaoApostasController.php: `show(string $codigo)` e `store(ApostasRequest, string $codigo)` com `permissao_cliente('apostas.validar')` e `RateLimiter` de 20/min por vendedor e 60/min por IP (R-10)
- [X] T068 [P] [US4] Criar app/Console/Commands/ExpirarApostasPendentesCommand.php (`apostas:expirar_pendentes`): marca Expirada (com `motivo_recusa`) as Pendentes com `expira_em` passado ou com algum jogo já iniciado (FR-041), por atualização condicional (`UPDATE ... WHERE situacao = 'Pendente'`), para não disputar com uma validação simultânea (que trava a linha e confere a situação)
- [X] T069 [US4] ⚠️ Alterar routes/api.php (`GET apostas/pendentes/{codigo}` e `POST apostas/pendentes/{codigo}/validar`) e routes/console.php (`Schedule::command('apostas:expirar_pendentes')->everyMinute()->withoutOverlapping(2)`)

**Checkpoint**: fluxo do código completo (visitante → vendedor)

---

## Phase 9: User Story 6 - Aposta no ao vivo com delay (Priority: P1)

**Goal**: aposta com ao vivo fica Em análise e é decidida uma única vez pelo servidor no fim do delay

**Independent Test**: quickstart cenários 25 a 29

- [X] T070 [US6] Criar `App\Services\DecisaoAoVivo` em app/Services/DecisaoAoVivo.php (R-02, FR-034): com lock da aposta e só se ainda `Em análise`, relê os jogos, grava `dados_ao_vivo_decisao`, recusa nas condições de FR-034a (placar, gols por tempo, escanteios ou situação mudaram; `ultima_atualizacao_em` ≤ `recebida_em`; fora de andamento, minuto limite, trava por tempo ou trava geral; cotação indisponível ou caiu sem preferência Qualquer) e aceita nas de FR-034b (cotações finais pela preferência; prêmio recalculado); na aceitação confere de novo e aplica saldo/limites/valor diário (mesmo código de T047 e T058, reaproveitado de `CriacaoApostas`), marca Ativa com `decidida_em` e `confirmada_em`, assina; na recusa grava `motivo_recusa` e `decidida_em` sem debitar
- [X] T071 [P] [US6] Criar app/Jobs/DecidirApostaAoVivo.php (`ShouldQueue`, fila `apostas`, `tries = 1`) que chama `DecisaoAoVivo` para o id da aposta
- [X] T072 [US6] Em app/Services/CriacaoApostas.php, fluxo do ao vivo (FR-031 a FR-033, FR-031a): aposta com pelo menos um palpite ao vivo vira `tipo = Ao vivo`, `situacao = Em análise`, grava `dados_ao_vivo_envio` dos palpites ao vivo, não debita nem abate limites (mas conta no limite por confronto); recusa se o apostador já tiver uma Em análise (conferido após o lock do vendedor ou cliente); despacha `DecidirApostaAoVivo::dispatch($id)->onQueue('apostas')->delay(now()->addSeconds(delay_ao_vivo))` após o commit
- [X] T073 [US6] Alterar app/Http/Controllers/ApostasController.php e app/Http/Controllers/AreaClienteApostasController.php: `store` devolve 202 `{"data": {"codigo", "situacao", "segundos_restantes"}}` para Em análise; `situacao` devolve `segundos_restantes` (Em análise), `motivo_recusa` (Recusada) ou o comprovante (Ativa), com `RateLimiter` de 60/min por apostador
- [X] T074 [P] [US6] Criar app/Console/Commands/RecusarAnalisesPresasCommand.php (`apostas:recusar_analises_presas`): recusa com "Não foi possível concluir a análise." as Em análise com `recebida_em + delay + 60 s` no passado (FR-035)
- [X] T075 [US6] ⚠️ Alterar routes/console.php: `Schedule::command('apostas:recusar_analises_presas')->everyMinute()->withoutOverlapping(2)`

**Checkpoint**: ao vivo com delay único, sem sleep e sem loop

---

## Phase 10: User Story 8 - Rollover e regras do bônus do cliente (Priority: P2)

**Goal**: apostas do cliente abatem o rollover; apostas com bônus seguem as regras da promoção

**Independent Test**: quickstart cenário 17

- [X] T076 [US8] Criar `App\Services\RolloverClientes` em app/Services/RolloverClientes.php (R-13, FR-045 a FR-048): `criar_bonus(Clientes, ClientesTransacoes, ClientesPromocoes)` (só com rollover > 0, copiando as regras de uso); `regras_bonus_pendente(Clientes): ?ClientesRollovers` (o Bônus de esportes pendente mais antigo); `somar(Apostas)` (Saldo → pendentes de Depósito; Promoção esportes → pendentes de Bônus da carteira Promoção esportes; mais antigo primeiro, excedente ao seguinte, `cumprido_em` ao completar; grava `apostas_rollovers`); `desfazer(Apostas)` (subtrai o que foi somado, limpa `cumprido_em` se voltar a faltar, marca `desfeito_em`), sempre com lock dos rollovers em ordem de id
- [X] T077 [US8] Em app/Services/CriacaoApostas.php e app/Services/DecisaoAoVivo.php: quando a carteira for Promoção esportes e houver bônus pendente, aplicar as regras do bônus com as mensagens de FR-045 (valor mínimo/máximo; odd mínima simples para 1 palpite e múltipla para 2 ou mais); após o débito confirmado, chamar `RolloverClientes::somar`
- [X] T078 [US8] ⚠️ Alterar app/Services/CadastroClientes.php: em `aplicar_promocoes_de_cadastro`, guardar a transação devolvida por `creditar` e chamar `RolloverClientes::criar_bonus` dentro da mesma transação (FR-047)
- [X] T079 [US8] ⚠️ Alterar app/Jobs/EstornarPromocao.php: ao estornar um cliente, marcar `cancelado_em` nos `clientes_rollovers` pendentes daquela promoção (R-13)

**Checkpoint**: rollover e regras do bônus aplicados

---

## Phase 11: User Story 9 - Cancelamento pelo vendedor e pela hierarquia (Priority: P2)

**Goal**: cancelamento com devolução exata, uma única vez

**Independent Test**: quickstart cenários 30 a 35

- [X] T080 [P] [US9] Criar `App\Services\AlcanceApostas` em app/Services/AlcanceApostas.php (R-15): `garantir_pode_cancelar(Usuarios, Apostas)` e `garantir_pode_editar(Usuarios, Apostas)`: vendedor só as próprias; Gerente/Supervisor/Admin as de `usuarios_id` na sub-hierarquia (`ids_sub_hierarquia`; Admin todas); apostas de cliente por qualquer Gerente, Supervisor ou Admin; fora do alcance → 404 "Aposta não encontrada."
- [X] T081 [US9] Criar `App\Services\CancelamentoApostas` em app/Services/CancelamentoApostas.php (FR-050 a FR-052): lock da aposta; só Ativa com resultado Aguardando ("Não é possível cancelar uma aposta já apurada"); vendedor: `cancelar_aposta`, prazo `tempo_cancelamento_aposta` gravado na aposta desde `confirmada_em`, sem jogo iniciado, sem ao vivo e sem data de travamento passada, com as mensagens da spec; gestor: jogo iniciado só com `apostas.cancelar_iniciada` (`Funcao::usuario_pode`); marca Cancelada com `cancelada_em`, `cancelada_por`, IP e user agent; cliente: `SaldoClientes::creditar` na carteira da aposta com `OrigemTransacao::Estorno` e `referencia_id` + `RolloverClientes::desfazer`; vendedor: devolve `limite_geral` e o limite abatido na confirmação — `limite_simples` se a aposta tinha 1 palpite, `limite_duplo` se tinha 2 ou mais, contando todos os palpites da confirmação (inclusive os cancelados depois por edição) — com lock da configuração
- [X] T082 [US9] Criar app/Http/Controllers/CancelamentoApostasController.php: `store(Request, string $codigo)` com `permissao_cliente('apostas.cancelar')`, usando `AlcanceApostas` e `CancelamentoApostas`, devolvendo 200 com o comprovante
- [X] T083 [US9] ⚠️ Alterar routes/api.php: `POST apostas/{codigo}/cancelar` no grupo do painel

**Checkpoint**: cancelamento com devolução e sem duplicidade

---

## Phase 12: User Story 10 - Editar a aposta cancelando ou restaurando um palpite (Priority: P2)

**Goal**: correção de palpite pelo painel com recálculo do prêmio e histórico

**Independent Test**: quickstart cenários 36 a 38

- [X] T084 [US10] Criar `App\Services\EdicaoApostas` em app/Services/EdicaoApostas.php (R-19, FR-052a a FR-052e): lock da aposta; só Ativa com resultado Aguardando; `cancelar_palpite` (recusa o último ativo com "Não é possível cancelar o último palpite ativo. Cancele a aposta.") e `restaurar_palpite`; recalcula `cotacao_total`, `premio` e `valor_acrescido` com `CalculoPremio` usando as cotações finais dos palpites ativos e `multiplicador`, `premio_maximo` e `ganho_multiplo_palpites` gravados na aposta; grava `cancelado_em/por` ou `restaurado_em/por`; cria `apostas_historico` com valores antes e depois, autor, IP e user agent; refaz a assinatura; não mexe em valor, saldo, limites, comissão nem rollover
- [X] T085 [US10] Criar app/Http/Controllers/ApostasPalpitesController.php: `cancelar` e `restaurar` (`string $codigo`, `int $palpite`) com `permissao_cliente('apostas.editar')` e `AlcanceApostas::garantir_pode_editar`; palpite de outra aposta → 404; devolve o comprovante recalculado
- [X] T086 [US10] ⚠️ Alterar routes/api.php: `POST apostas/{codigo}/palpites/{palpite}/cancelar` e `.../restaurar`

**Checkpoint**: edição com histórico

---

## Phase 13: User Story 11 - Comprovante e consulta da aposta (Priority: P2)

**Goal**: consulta pública por código e por lista de códigos

**Independent Test**: quickstart cenários 39, 40 e 48

- [X] T087 [P] [US11] Criar app/Http/Requests/ConsultarApostasRequest.php: `codigos` required array 1–50, cada um string de 8 caracteres; `RateLimiter` de 60/min por IP
- [X] T088 [US11] Alterar app/Http/Controllers/PublicoApostasController.php: `show(string $codigo)` (60/min por IP; confere expiração da Pendente; 404 "Aposta não encontrada.") e `consultar(ConsultarApostasRequest)` devolvendo `{codigo, valor, premio, total_a_pagar, situacao, resultado, criada_em}` e ignorando códigos inexistentes (FR-056)
- [X] T089 [US11] ⚠️ Alterar routes/api.php: `GET publico/apostas/{codigo}` e `POST publico/apostas/consultar` (a rota `consultar` antes da rota com `{codigo}`)

**Checkpoint**: comprovante público

---

## Phase 14: User Story 12 - Configurações de aposta por público e reflexo na listagem (Priority: P2)

**Goal**: novas configurações editáveis pelas rotas existentes e detalhe do confronto com todas as cotações

**Independent Test**: quickstart cenários 41 a 47

- [X] T090 [P] [US12] ⚠️ Alterar app/Http/Requests/UsuariosConfiguracoesRequest.php e app/Http/Resources/UsuariosConfiguracoesResource.php: regras e mensagens dos campos novos (FR-064: mínimos ≤ máximos, percentuais 0–100, tempos e limites ≥ 0, multiplicador e prêmio máximo > 0, `periodo_jogos` no enum, `data_travamento_sistema` data ISO 8601 ou nulo — sem fuso explícito vale `FusoSistema` (-03:00), gravada em UTC e devolvida no fuso pedido —, `mensagem_bilhete` até 500) e devolução dos campos
- [X] T091 [P] [US12] ⚠️ Alterar app/Http/Requests/ClientesConfiguracoesRequest.php e app/Http/Resources/ClientesConfiguracoesResource.php: campos de T019 com as regras de FR-064
- [X] T092 [P] [US12] ⚠️ Alterar app/Http/Requests/VisitantesConfiguracoesRequest.php: campos de T020 com as regras de FR-064 (`horas_validade_codigo` ≥ 1; `data_travamento_sistema` com a mesma regra de fuso de T090: sem fuso explícito vale -03:00, gravada em UTC)
- [X] T093 [US12] Criar `App\Services\DetalheConfrontos` em app/Services/DetalheConfrontos.php (FR-065a, FR-065b): `pre_jogo(Publico, int $id, CarbonTimeZone)` e `ao_vivo(...)` aplicando as mesmas restrições de `RegrasExibicao` da listagem (inexistente ou não visível → 404 "Confronto não encontrado."), todas as cotações > 0 por `CalculoCotacoes::ajustar` com todos os códigos, `mercado` por `NomesCotacoes`, jogadores por `ajustar_jogadores` só no pré-jogo e com `pode_apostar_jogadores`; ao vivo com placar, minuto, cronômetro, situação e `travado` (cotações `"0.00"` quando travado)
- [X] T094 [US12] Criar app/Http/Controllers/PublicoConfrontosDetalheController.php: `pre_jogo` e `ao_vivo` com `IdentificacaoPublico`, `fuso_horario` e o mesmo limite de 120/min por IP da listagem pública
- [X] T095 [US12] ⚠️ Alterar routes/api.php: `GET publico/confrontos/{confronto}` e `GET publico/confrontos-ao-vivo/{confronto_ao_vivo}`

**Checkpoint**: todas as stories funcionais

---

## Phase 15: Polish & Cross-Cutting Concerns

- [X] T096 ⚠️ Regenerar docs/postman/wssports_api.postman_collection.json substituindo o arquivo: pasta "Apostas" com as 16 rotas novas (corpos de exemplo do contrato) e os campos novos nas rotas de configurações; manter `base_url`, `token` e `token_cliente`
- [X] T097 [P] ⚠️ Atualizar specs/002-clientes (`spec.md`, `data-model.md`, `research.md`): registrar que o crédito do bônus de Primeiro cadastro cria o rollover em `clientes_rollovers`, o estorno da promoção cancela os rollovers pendentes e `cancelar_aposta` fica sem uso (FR-050b da spec 004)
- [X] T098 [P] ⚠️ Atualizar specs/003-confrontos (`spec.md`, `data-model.md`, `contracts/api.md`, `quickstart.md`): listagem com período de jogos, data de travamento e exclusão do jogo que está no ao vivo; `limite_valor_apostado`; rotas de detalhe pertencem à spec 004; `CalculoCotacoes` e `RegrasExibicao` compartilhados
- [X] T099 Revisão de legibilidade de todo o código da feature em conjunto (Princípio V): nomes, duplicação entre `CriacaoApostas`, `DecisaoAoVivo` e `ValidacaoCodigos`, métodos longos, condicionais aninhadas e comentários; informar o resultado
- [ ] T100 Rodar o [quickstart.md](quickstart.md) completo (seções 1 a 4, cenários 1 a 48) e registrar o resultado, incluindo: tempo de resposta de uma aposta do pré-jogo com 20 palpites (meta < 1 s, SC-001), tempo entre o fim do delay e a decisão do ao vivo (meta ≤ 5 s, SC-002) e uma rajada de 50 apostas simultâneas do mesmo cliente pelo Postman Runner ou script (nenhum débito além do saldo e nenhum limite ultrapassado, SC-007)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sem dependências
- **Foundational (Phase 2)**: depende do Setup — BLOQUEIA todas as stories
- **Stories**: começam depois da Foundational, na ordem abaixo
- **Polish**: depois de todas as stories

### User Story Dependencies

- **US1 (P1)**: Foundational
- **US5 (P1)**: US1 (usa o fluxo de `CriacaoApostas` do vendedor para validar)
- **US7 (P1)**: US1
- **US2 (P1)**: US1 (núcleo de `CriacaoApostas`); independente de US5/US7 para testar
- **US3 (P1)**: Foundational (núcleo de `CriacaoApostas`)
- **US4 (P1)**: US3 (precisa de código Pendente) e US1 (regras do vendedor)
- **US6 (P1)**: US1 e US2 (aplica saldo e limites na decisão)
- **US8 (P2)**: US2
- **US9 (P2)**: US1 e US2 (devoluções); usa `RolloverClientes` da US8 se presente
- **US10 (P2)**: US1 e `AlcanceApostas` da US9
- **US11 (P2)**: US3
- **US12 (P2)**: Foundational (configurações) e `RegrasExibicao`

### Arquivos compartilhados (tarefas em sequência, nunca em paralelo)

- app/Services/CriacaoApostas.php: T046 → T047 → T052 → T053 → T058 → T062 → T072 → T077
- routes/api.php: T049 → T056 → T060 → T064 → T069 → T083 → T086 → T089 → T095
- routes/console.php: T069 → T075
- app/Http/Controllers/ApostasController.php: T048 → T073
- app/Http/Controllers/AreaClienteApostasController.php: T059 → T073
- app/Http/Controllers/PublicoApostasController.php: T063 → T088
- app/Services/DecisaoAoVivo.php: T070 → T077
- app/Services/ListagemConfrontos.php: T039 → T040

### Parallel Opportunities

- Setup: T001–T003
- Foundational: enums T004–T012; migrations T013–T022; models T023–T032 (depois das migrations); T037, T041 e T042
- US5: T050 e T051
- US7: T054 em paralelo com T053
- US2: T057 antes de T058
- US4: T065 e T068
- US6: T071 e T074
- US9: T080
- US11: T087
- US12: T090, T091 e T092
- Polish: T097 e T098

---

## Parallel Example: Foundational

```bash
# Enums (arquivos diferentes):
Task: "T004 SituacaoAposta"
Task: "T008 AceitarAlteracoes"
Task: "T011 PeriodoJogos"

# Migrations:
Task: "T013 create_apostas_table"
Task: "T016 create_clientes_rollovers_table"
Task: "T018 add_apostas_usuarios_configuracoes_table"

# Serviços sem dependência entre si:
Task: "T041 CalculoPremio"
Task: "T042 AssinaturaApostas"
```

## Parallel Example: User Story 12

```bash
Task: "T090 UsuariosConfiguracoesRequest + Resource"
Task: "T091 ClientesConfiguracoesRequest + Resource"
Task: "T092 VisitantesConfiguracoesRequest"
```

---

## Implementation Strategy

### MVP First (US1)

1. Phase 1 (Setup) e Phase 2 (Foundational)
2. Phase 3: US1 — vendedor aposta no pré-jogo
3. **PARAR e VALIDAR**: quickstart cenários 1 a 5

### Incremental Delivery

1. MVP (US1) → confirmação de cotação (US5) → limites e prêmio (US7)
2. Cliente com saldo (US2) → visitante (US3) → validação do código (US4)
3. Ao vivo com delay (US6)
4. Rollover (US8) → cancelamento (US9) → edição (US10) → comprovante público (US11) →
   configurações e detalhe (US12)
5. Polish (Postman, documentos das specs 002 e 003, revisão final e quickstart completo)

### Antes de começar

- Os arquivos existentes da lista do [plan.md](plan.md) foram autorizados em 2026-10-07
  (Princípio IV); só eles podem ser alterados.
- Para validar a US6, o worker da fila precisa estar rodando (`php artisan queue:work
  --queue=apostas,default`).

---

## Notes

- [P] = arquivos diferentes, sem dependências pendentes
- [Story] liga a tarefa à user story da spec
- Sem testes automatizados: cada fase termina com a validação manual do quickstart e a revisão do
  Princípio V
- Parar em qualquer checkpoint para validar a story de forma independente

## Registro da implementação (2026-10-08)

- **Arquivos novos além dos previstos** (sem alterar arquivos existentes fora da lista do plano):
  `app/Support/Apostador.php` (apostador e as regras do seu público), 
  `app/Http/Controllers/Concerns/RespostasApostas.php` (limite de tentativas e status da criação)
  e `app/Http/Requests/Concerns/ConfiguracoesAposta.php` (regras FR-064 e conversão de fuso da
  data de travamento, usadas pelos 3 requests de configuração).
- **Não precisou mudar**: `ClientesConfiguracoesResource` (já devolve `ClientesConfiguracoes::CAMPOS`)
  e `VisitantesConfiguracoesController` (usa `VisitantesConfiguracoes::CAMPOS`).
- **Permissões (T033)**: os gestores recebem as 5 permissões de apostas para poder repassá-las aos
  vendedores que cadastram (`atribuir_permissoes_padrao` só repassa o que o cadastrante tem); o
  `pode_usar` continua impedindo que apostem ou validem códigos.
- **Validação do código (T066)**: o índice único (`apostas_id`, `confrontos_id`) vale também para
  palpites excluídos; por isso o palpite de um confronto que continua na aposta é restaurado e
  atualizado, e só os demais são excluídos logicamente.
- **Ajuste pedido pelo responsável (2026-10-08)**: `ConfiguracoesVendedores::criar_para` (spec 003)
  deixou de copiar do colega os limites de venda (`UsuariosConfiguracoes::LIMITES_VENDA`); o
  vendedor novo nasce com o padrão da coluna (5.000,00). Conferido no banco local em transação
  desfeita: limites 5.000,00 e demais campos copiados.
- **Campos novos das configurações** entram como opcionais (`sometimes`), para não quebrar as rotas
  PUT existentes das specs 002 e 003.
- **Revisão (T099)**: imports sem uso conferidos (nenhum); leitura do fuso do pedido unificada em
  `FusoSistema::do_pedido`; corrigido o bug da soma do limite por confronto (o `pluck` de uma
  expressão SUM não era lido), encontrado na validação.
- **Validação (T100, parcial)**: roteiros executados no banco local dentro de transação desfeita
  (nenhum dado alterado): 38 cenários de serviço (US1 a US12, incluindo ao vivo aceito, recusado por
  lance e sem atualização) e 24 cenários HTTP (status, permissões, 409, 422, comprovante sem dados
  internos, configurações, limite e detalhe), todos aprovados; aposta de 20 palpites em 71 ms
  (SC-001). **Pendente de execução manual**: a rajada de 50 apostas simultâneas (SC-007) e o ao vivo
  ponta a ponta com o worker da fila e o provedor simulado (SC-002), que precisam de requisições
  HTTP reais em paralelo.
