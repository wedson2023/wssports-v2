---

description: "Lista de tarefas da feature Criar Aposta (vários palpites do mesmo jogo)"
---

# Tasks: Criar Aposta (vários palpites do mesmo jogo)

**Input**: Documentos de design em `specs/008-criar-aposta/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md),
[data-model.md](data-model.md), [contracts/api.md](contracts/api.md),
[contracts/paginas.md](contracts/paginas.md), [quickstart.md](quickstart.md)

**Tests**: NÃO há tarefas de teste: a constituição proíbe testes automatizados. Cada user story é
validada manualmente pelos cenários do [quickstart.md](quickstart.md).

**Organization**: tarefas agrupadas por user story. Ordem: US1 e US2 (P1), US3 (P2).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: pode rodar em paralelo (arquivos diferentes, sem dependências pendentes)
- **[Story]**: user story da tarefa (US1 a US3)
- Caminhos relativos à raiz do repositório

## Regras que valem para TODAS as tarefas

- **Sistema antigo (Princípio VII)**: o Criar Aposta não existe no antigo (research, "Sistema
  antigo"); `C:\Users\wedso\OneDrive\Área de Trabalho\projetos\wssports.bet` é **somente leitura**.
- **Visual (Princípio VIII, FR-026)**: nenhum componente com visual novo; o bloco usa os estilos
  atuais do cupom e do comprovante.
- **Nomes (Princípio I)**: backend em português (classes `PascalCase`, resto `snake_case`); métodos
  do framework com o nome original; rotas em kebab-case. Frontend: componentes em inglês
  `PascalCase`; variáveis, funções, props e chaves em `snake_case` português; props de estilo com
  `$`; utilitário novo em inglês `snake_case` (`utils/bet_builder.js`).
- **Idioma (Princípio II)**: comentários, textos e mensagens em português.
- **Escopo (Princípio IV)**: arquivos existentes marcados com ⚠️ estão na tabela do
  [plan.md](plan.md) e foram confirmados pelo responsável em 2026-10-13. Nenhum outro arquivo
  existente pode ser alterado; se for preciso, avisar antes.
- **Comentários de colunas (constituição 3.3.0)**: toda coluna nova com `->comment()` em português,
  com o texto exato do [data-model.md](data-model.md).
- **Banco**: tabela nova com `timestamps()` + `softDeletes()` e model com `SoftDeletes`.
- **Dinheiro e cotação**: `bcmath` no backend e `BigInt` em centésimos no frontend; nunca `float`.
  A cotação do bloco é **truncada** em 2 casas e nunca menor que 1,00 (FR-007).
- **Postman (constituição)**: toda tarefa que cria ou altera rota em `routes/api.php` regenera, na
  mesma tarefa, `docs/postman/wssports_api.postman_collection.json` ⚠️ (substituindo o arquivo,
  mantendo `base_url` e `token`).
- **Legibilidade (Princípio V)**: ao concluir cada tarefa, revisar o código alterado e informar se
  houve ou não refatoração.

---

## Phase 1: Setup

- [X] T001 Conferir o ambiente: branch `008-criar-aposta`, `php artisan migrate:status` sem pendências e `npm run build` funcionando antes de qualquer alteração (nenhum arquivo muda)

---

## Phase 2: Foundational (bloqueia todas as user stories)

**Objetivo**: banco, enums, models, configurações por público e as duas peças centrais
(`MercadosCotacao` e `CalculoBlocos`).

- [X] T002 [P] Criar o enum `LiquidacaoCriarAposta` em app/Enums/LiquidacaoCriarAposta.php com os casos `Recalcular` = `'Recalcular'` e `AnularBloco` = `'Anular bloco'` (backed string, no padrão dos enums da spec 004)
- [X] T003 [P] Criar o enum `SituacaoBloco` em app/Enums/SituacaoBloco.php com os casos `Ativo` = `'Ativo'` e `Anulado` = `'Anulado'`
- [X] T004 [P] Criar a migration database/migrations/2026_10_13_000001_create_apostas_blocos_table.php: `id()`; `apostas_id` FK `apostas` (comentário "Aposta a que o bloco Criar Aposta pertence"); `confrontos_id` FK `confrontos` ("Jogo do pré-jogo cujos palpites formam o bloco"); `cotacao_vista` decimal(8,2) ("Cotação do bloco que o apostador viu no cupom, com o desconto"); `cotacao` decimal(8,2) ("Cotação atual do bloco: produto das cotações dos palpites ativos × (1 − desconto ÷ 100), truncada em 2 casas"); `situacao` string(20) default `'Ativo'` ("Situação do bloco: Ativo ou Anulado (anulado conta como 1,00 na cotação total)"); `timestamps()`; `softDeletes()`; único `(apostas_id, confrontos_id)`; `down()` com `dropIfExists`
- [X] T005 [P] Criar a migration database/migrations/2026_10_13_000002_add_criar_aposta_apostas_palpites_table.php: `apostas_blocos_id` FK `apostas_blocos` nullable ("Bloco Criar Aposta do palpite; nulo no palpite simples"); criar índice simples `(apostas_id, confrontos_id)` **antes** de remover o único `(apostas_id, confrontos_id)` (a FK de `apostas_id` precisa de um índice); `down()` desfaz na ordem inversa (R-02)
- [X] T006 [P] Criar a migration database/migrations/2026_10_13_000003_add_criar_aposta_apostas_table.php: `desconto_criar_aposta` decimal(5,2) nullable ("Percentual de desconto do Criar Aposta usado na confirmação (0 a 100); nulo quando a aposta não tem bloco") e `liquidacao_criar_aposta` string(20) nullable ("Regra de liquidação do Criar Aposta usada na confirmação: Recalcular ou Anular bloco; nulo quando a aposta não tem bloco")
- [X] T007 [P] Criar a migration database/migrations/2026_10_13_000004_add_criar_aposta_configuracoes_table.php com as 5 colunas, padrões e comentários da tabela `configuracoes` do [data-model.md](data-model.md) seção 2 (`criar_aposta_habilitado` true, `desconto_criar_aposta` 0, `quantidade_minima_criar_aposta` 2, `quantidade_maxima_criar_aposta` 10, `liquidacao_criar_aposta` `'Recalcular'`)
- [X] T008 [P] Criar a migration database/migrations/2026_10_13_000005_add_criar_aposta_usuarios_configuracoes_table.php com as 4 colunas, padrões e comentários da tabela `usuarios_configuracoes` do [data-model.md](data-model.md) seção 2
- [X] T009 Rodar `php artisan migrate` e conferir com `SHOW FULL COLUMNS` que todas as colunas novas de `apostas_blocos`, `apostas_palpites`, `apostas`, `configuracoes` e `usuarios_configuracoes` têm comentário e que o único `(apostas_id, confrontos_id)` virou índice simples (depende de T004 a T008)
- [X] T010 [P] Criar o model app/Models/ApostasBlocos.php: tabela `apostas_blocos`, `SoftDeletes`, `$guarded = ['id']` (como `ApostasPalpites`), casts `cotacao_vista`/`cotacao` `decimal:2` e `situacao` `SituacaoBloco`; relações `aposta()`, `confronto()` (`withTrashed`) e `palpites()`; método `e_anulado()`
- [X] T011 [P] ⚠️ Em app/Models/Apostas.php: relação `blocos()` (HasMany `ApostasBlocos`) e casts `desconto_criar_aposta` `decimal:2` e `liquidacao_criar_aposta` `LiquidacaoCriarAposta`
- [X] T012 [P] ⚠️ Em app/Models/ApostasPalpites.php: relação `bloco()` (BelongsTo `ApostasBlocos`, `apostas_blocos_id`) e atualizar o comentário da classe (palpite pode estar num bloco)
- [X] T013 [P] ⚠️ Em app/Models/Configuracoes.php: `fillable` e casts das 5 colunas (`boolean`, `decimal:2`, `integer`, `integer`, `LiquidacaoCriarAposta`), com comentário "Criar Aposta (spec 008)"
- [X] T014 [P] ⚠️ Em app/Models/UsuariosConfiguracoes.php: os 4 campos na constante nova `CAMPOS_CRIAR_APOSTA`, incluída em `CAMPOS` (fora de `CAMPOS_APOSTA`, cujas regras vêm do concern `ConfiguracoesAposta`), e os casts (`boolean`, `decimal:2`, `integer`, `integer`)
- [X] T015 ⚠️ Em app/Support/Apostador.php: métodos `criar_aposta_habilitado(): bool`, `desconto_criar_aposta(): string`, `quantidade_minima_criar_aposta(): int` e `quantidade_maxima_criar_aposta(): int` (vendedor lê de `$this->configuracao`; cliente e visitante de `Configuracoes::atual()`) e `liquidacao_criar_aposta(): LiquidacaoCriarAposta` (sempre de `Configuracoes::atual()`) (R-06; depende de T013, T014)
- [X] T016 [P] Criar app/Support/MercadosCotacao.php (R-05): constante com o grupo de cada código, exatamente pelas faixas do [data-model.md](data-model.md) seção 5 (mesmas categorias de `resources/js/utils/market_groups.js`); `grupo(string $codigo, ?string $nome_jogador): string` (jogador → `jogador:<nome em minúsculas>`; código fora das faixas → o próprio código); constante `PARES_REDUNDANTES` com os pares da seção 5; `redundantes(string $grupo_a, string $grupo_b): bool` (ordem não importa); `grupos(): array` (código → grupo, para as props)
- [X] T017 [P] Criar app/Services/CalculoBlocos.php (R-04): `cotacao_bloco(array $cotacoes, string $desconto): string` (bcmath escala 10; um único palpite → a cotação dele sem desconto; senão produto × (100 − desconto) ÷ 100 **truncado** em 2 casas com `bcadd($x, '0', 2)`; mínimo `'1.00'`); `pernas(array $palpites, string $desconto): array` (agrupa por `confrontos_id` os palpites de jogos repetidos do pré-jogo e devolve, na ordem do primeiro palpite de cada perna, `{tipo: 'palpite'|'bloco', confrontos_id, indices, cotacao}` como no [data-model.md](data-model.md) seção 6); `cotacoes_das_pernas(array $pernas): array`; usar `cotacao_atual` (montados) ou `cotacao_final` (gravados) conforme a chave recebida

**Checkpoint**: banco migrado com comentários; nenhuma tela ou rota mudou ainda.

---

## Phase 3: User Story 1 - Apostador monta um Criar Aposta e conclui a aposta (Priority: P1) 🎯 MVP

**Goal**: vendedor, cliente e visitante montam o bloco no cupom e concluem a aposta com a cotação
combinada e as regras da spec.

**Independent Test**: [quickstart.md](quickstart.md) cenários 1 a 11 e 15 a 18 (com as
configurações ajustadas direto no banco enquanto a US2 não existir).

### Backend

- [X] T018 [US1] ⚠️ Em app/Http/Requests/ApostasRequest.php: campo `blocos` (`nullable`, `array`, `max:25`), `blocos.*.confrontos_id` (`required`, `integer`, `distinct`), `blocos.*.cotacao_vista` (`required`, `numeric`, `min:1`, regex de 2 casas) e mensagens em português; `dados()` devolve `blocos` como lista com `cotacao_vista` em texto (vazia quando ausente) (R-03)
- [X] T019 [US1] ⚠️ Em app/Services/RegrasAposta.php: `conferir_palpites` passa a recusar jogo repetido só quando não pode ser bloco (palpite do ao vivo repetido → "O Criar Aposta não está disponível para jogos ao vivo."; mantém "Não é possível cadastrar jogos repetidos na aposta." para o resto); método novo `conferir_blocos(Apostador $apostador, array $palpites, array $blocos)` na ordem: chave desligada → "O Criar Aposta não está habilitado."; jogo repetido sem item em `blocos` → "Informe a cotação do Criar Aposta do jogo CASA x FORA."; item sem palpites repetidos → "O Criar Aposta precisa de palpites do mesmo jogo."; menos que o mínimo → "O Criar Aposta do jogo CASA x FORA precisa de pelo menos N palpites."; mais que o máximo → "O Criar Aposta aceita no máximo N palpites."; dois palpites no mesmo grupo ou par redundante (`MercadosCotacao`) → "Não é possível combinar dois palpites do mesmo mercado no Criar Aposta." (FR-001 a FR-005a, FR-013, FR-016)
- [X] T020 [US1] ⚠️ Em app/Services/ConferenciaCotacoes.php: `alteracoes` recebe também as pernas e os blocos enviados e acrescenta, para cada bloco cuja `cotacao_vista` difere da calculada fora da preferência, o item `{bloco: true, confrontos_id, cotacao_vista, cotacao_atual}` (R-09; contrato 409)
- [X] T021 [US1] ⚠️ Em app/Services/CriacaoApostas.php: injetar `CalculoBlocos`; em `preparar`, chamar `conferir_blocos`, montar as pernas com `$apostador->desconto_criar_aposta()`, calcular o prêmio com as cotações das pernas, passar as pernas à conferência de cotações e usar `count($pernas)` em `conferir_limites`, devolvendo `pernas` no preparo; em `gravar`, gravar `desconto_criar_aposta` e `liquidacao_criar_aposta` (nulos sem bloco), criar os `ApostasBlocos` (`cotacao_vista` do pedido, `cotacao` calculada) e o `apostas_blocos_id` dos palpites (novo parâmetro de `gravar_palpites`), e confirmar com `count($pernas)`; em `valor_apostado`, somar o valor de cada aposta **uma vez por jogo** (subconsulta `DISTINCT apostas_id, jogo`); em `garantir_limite_por_confronto`, ids sem repetição (FR-006 a FR-012, FR-015, R-07, R-08)
- [X] T022 [US1] ⚠️ Em app/Services/DecisaoAoVivo.php: na aceitação, montar as pernas com o `desconto_criar_aposta` gravado na aposta, calcular o prêmio pelas pernas, conferir limites e confirmar com a quantidade de pernas e atualizar a `cotacao` dos blocos gravados (aposta mista com bloco do pré-jogo)
- [X] T023 [US1] ⚠️ Em app/Services/ValidacaoCodigos.php: `pedidos()` e o retorno incluem os blocos gravados (`confrontos_id`, `cotacao_vista` = `cotacao` gravada); `simular` calcula pernas e prêmio com o desconto do vendedor e devolve os blocos, marcando os palpites de um bloco com `disponivel: false` e o motivo de `conferir_blocos` quando o bloco não passa nas regras do vendedor (chave desligada, mínimo ou máximo); `chave()` passa a ser confronto + código + jogador (`confronto:{id}:{codigo}:{jogador}`); `substituir_palpites` recria os blocos da aposta com os do preparo e confirma com a quantidade de pernas (R-07)
- [X] T024 [US1] ⚠️ Em app/Services/AssinaturaApostas.php: quando a aposta tiver blocos, incluir `blocos` (id, `confrontos_id`, `cotacao`, `situacao`) e o `apostas_blocos_id` de cada palpite no canônico; sem blocos, o canônico fica idêntico ao atual (R-11)
- [X] T025 [P] [US1] ⚠️ Em app/Http/Resources/ComprovanteApostaResource.php: `quantidade_jogos` (palpites ativos fora de bloco + blocos), `blocos` (`id`, `confrontos_id`, `cotacao`, `situacao`; lista vazia sem bloco) e `apostas_blocos_id` em cada palpite; o desconto **não** é exibido (FR-025)
- [X] T026 [P] [US1] ⚠️ Em app/Http/Resources/SimulacaoApostaResource.php: `blocos` (`confrontos_id`, `cotacao`; sem `id`, pois ainda não estão gravados com as regras do vendedor), no formato do contrato
- [X] T027 [P] [US1] ⚠️ Em app/Http/Controllers/PaginaInicialController.php: em `configuracoes()`, chave `criar_aposta` com `habilitado`, `desconto`, `quantidade_minima`, `quantidade_maxima` (do público, pelos mesmos campos do `Apostador`), `grupos` (`MercadosCotacao::grupos()`) e `pares_redundantes`, no formato do [contracts/api.md](contracts/api.md)

### Frontend

- [X] T028 [P] [US1] Criar resources/js/utils/bet_builder.js: `agrupar_pernas(palpites)` (palpites do mesmo `pre_jogo` + `confronto_id` viram um bloco, na ordem do cupom), `grupo_do_palpite(palpite, grupos)` (jogador → `jogador:<nome em minúsculas>`; código sem grupo → o próprio código) e `pode_combinar(palpites_do_jogo, palpite, regras)` devolvendo `{ permitido, motivo }` com as mensagens do contrato (máximo e mesmo mercado/par redundante)
- [X] T029 [P] [US1] ⚠️ Em resources/js/utils/money.js: `cotacao_bloco(cotacoes, desconto)` em `BigInt` espelhando `CalculoBlocos` (um palpite → ele mesmo; truncada; mínimo "1.00") e `calcular_premio` passando a receber as cotações das pernas (sem mudar a fórmula)
- [X] T030 [US1] ⚠️ Em resources/js/hooks/useCupom.js: `alternar_palpite` segue o R-12 (mesmo palpite → remove; mesmo grupo → troca; grupo diferente → soma ao bloco se `criar_aposta.habilitado`, `pre_jogo`, abaixo do máximo e combinável; desligado ou ao vivo → troca como hoje; senão `alerta_atencao` com o motivo e não muda); `remover_palpite` remove um palpite (tipo, confronto, código, jogador); `montar_envio` acrescenta `blocos` com a cotação do bloco; `atualizar_cotacoes` trata os itens `bloco: true` (sem gravar a cotação do bloco no cupom: ela é sempre derivada); os totais usam as pernas e o desconto das props; `versao_formato` continua 1
- [X] T031 [US1] ⚠️ Em resources/js/components/MatchDetailsModal/index.jsx: `selecionado` verdadeiro para **todos** os palpites do jogo no cupom (código e jogador), sem mudar o visual
- [X] T032 [US1] ⚠️ Em resources/js/pages/Home/index.jsx, resources/js/components/MatchList/index.jsx, resources/js/components/MatchCard/index.jsx e resources/js/components/OddGroup/index.jsx: `selecao_do_jogo` devolve a lista de códigos do jogo e a prop `codigo_selecionado` vira `codigos_selecionados` (lista) até o `OddButton`; `palpite_de_outro_mercado` passa a olhar a lista; `abrir_validacao` carrega todos os palpites disponíveis da simulação, inclusive os do bloco
- [X] T033 [US1] ⚠️ Em resources/js/components/BetSlip/index.jsx e resources/js/components/BetSlipItem/index.jsx: o `BetSlip` usa `agrupar_pernas`; o `BetSlipItem` recebe a lista de palpites do jogo e mostra os times uma vez, uma linha `Bottom` por palpite (remover "x" à esquerda no lugar do "mais opções", mercado no meio e cotação à direita; ajuste pedido pelo responsável em 2026-10-13), e, no bloco, uma última linha com "Criar Aposta" e a cotação do bloco, só com os styled-components já existentes em `BetSlipItem/styles.jsx`
- [X] T034 [P] [US1] ⚠️ Em resources/js/components/BetSlipSummary/index.jsx e resources/js/components/BetSlipActions/index.jsx: a contagem passa a ser a quantidade de pernas (`agrupar_pernas(cupom.palpites).length`)
- [X] T035 [P] [US1] ⚠️ Em resources/js/components/TicketModal/index.jsx, resources/js/utils/print.js e resources/js/utils/thermal.js: palpites do bloco seguidos de uma linha "Criar Aposta" com a cotação do bloco (`comprovante.blocos`), no estilo das linhas atuais; "Qtde. jogos" usa `comprovante.quantidade_jogos`
- [ ] T036 [US1] Validar manualmente os cenários 1 a 11 e 15 a 18 do [quickstart.md](quickstart.md) (configurações ajustadas no banco) e registrar o resultado

**Checkpoint**: o Criar Aposta funciona de ponta a ponta para os três públicos.

---

## Phase 4: User Story 2 - Admin configura o Criar Aposta (Priority: P1)

**Goal**: admin edita as configurações gerais e a hierarquia edita as do vendedor.

**Independent Test**: [quickstart.md](quickstart.md) preparação (PUT da configuração) e cenários 2,
8 e 14; valores inválidos recusados com as mensagens do [data-model.md](data-model.md) seção 4.

- [X] T037 [P] [US2] Criar app/Http/Requests/ConfiguracoesCriarApostaRequest.php no padrão de `ConfiguracoesPagamentosRequest`: `criar_aposta_habilitado` boolean; `desconto_criar_aposta` numérico, até 2 casas, `between:0,100`; `quantidade_minima_criar_aposta` e `quantidade_maxima_criar_aposta` inteiros `between:2,20`, mínimo ≤ máximo comparando com o enviado ou com o valor atual de `Configuracoes::atual()`; `liquidacao_criar_aposta` `Rule::enum(LiquidacaoCriarAposta::class)`; todos `sometimes`; mensagens exatas do [data-model.md](data-model.md) seção 4
- [X] T038 [P] [US2] Criar app/Http/Resources/ConfiguracoesCriarApostaResource.php devolvendo os 5 campos no formato do [contracts/api.md](contracts/api.md) (`liquidacao_criar_aposta` pelo `value` do enum)
- [X] T039 [US2] Criar app/Http/Controllers/ConfiguracoesCriarApostaController.php no padrão de `ConfiguracoesPagamentosController` (`HasMiddleware`, `permissao_cliente('configuracoes.editar')`, `show` e `update` com `Configuracoes::atual()`) (depende de T037, T038)
- [X] T040 [US2] ⚠️ Em routes/api.php: `GET` e `PUT configuracoes/criar-aposta` junto das rotas de `configuracoes/pagamentos` e o `use` do controller; regenerar ⚠️ docs/postman/wssports_api.postman_collection.json com as 2 rotas
- [X] T041 [P] [US2] ⚠️ Em app/Http/Requests/UsuariosConfiguracoesRequest.php: regras e mensagens dos 4 campos do vendedor (as mesmas de T037, sem a liquidação; como o alvo pode ser um alcance com vários vendedores, mínimo ≤ máximo só quando os dois vêm no pedido, no padrão de `quantidade_minima_opcoes`)
- [X] T042 [P] [US2] ⚠️ Em app/Http/Resources/UsuariosConfiguracoesResource.php: devolver os 4 campos
- [X] T043 [US2] Validar manualmente a US2 pelo [quickstart.md](quickstart.md) (PUT geral, PATCH do vendedor, valores inválidos e cenários 2, 8 e 14)

**Checkpoint**: configurações editáveis pela API, sem mexer no banco.

---

## Phase 5: User Story 3 - Palpite do bloco cancelado ou anulado (Priority: P2)

**Goal**: a edição de palpites aplica a liquidação gravada na aposta.

**Independent Test**: [quickstart.md](quickstart.md) cenários 12, 13 e 14.

- [X] T044 [US3] ⚠️ Em app/Services/EdicaoApostas.php (R-10): injetar `CalculoBlocos`; ao cancelar palpite de bloco com `Recalcular`, cancelar só ele e recalcular a `cotacao` do bloco com os ativos e o desconto gravado (um único ativo → a cotação dele; nenhum → o bloco deixa de contar); com `AnularBloco`, cancelar todos os palpites ativos do bloco e marcar o bloco `Anulado` (sem a trava "último palpite ativo" quando o palpite é desse bloco); ao restaurar com `AnularBloco`, restaurar todos os palpites do bloco e voltar o bloco a `Ativo`; `recalcular()` usa as pernas (bloco `Anulado` fora da lista); histórico com uma linha por ação no palpite clicado e assinatura refeita, como hoje (FR-018 a FR-021)
- [X] T045 [US3] Validar manualmente os cenários 12, 13 e 14 do [quickstart.md](quickstart.md), conferindo o histórico e a assinatura (`AssinaturaApostas::conferir`)

**Checkpoint**: todas as user stories funcionando.

---

## Phase 6: Polish & Cross-Cutting

- [X] T046 [P] ⚠️ Atualizar specs/004-apostas/spec.md (nota em FR-015 e na entidade "Palpite da aposta": jogo repetido permitido como bloco Criar Aposta, spec 008), specs/004-apostas/contracts/api.md (campo `blocos`, 409 do bloco, `quantidade_jogos` e `blocos` no comprovante, com link para a spec 008) e specs/004-apostas/data-model.md (índice de `apostas_palpites` e `apostas_blocos_id`)
- [X] T047 ⚠️ Regenerar docs/postman/wssports_api.postman_collection.json com o campo `blocos` nos exemplos de aposta (vendedor, cliente, visitante e validação), mantendo `base_url` e `token`
- [X] T048 Revisão de legibilidade de todo o código alterado na feature (Princípio V) e passada completa do [quickstart.md](quickstart.md) (cenários 1 a 18); registrar ao final deste arquivo se houve refatoração

---

## Dependencies & Execution Order

- **Setup (T001)** → **Foundational (T002–T017)** → user stories.
- Dentro da Foundational: T009 depende de T004–T008; T015 depende de T013 e T014; os demais [P]
  são independentes.
- **US1 (T018–T036)**: depende da Foundational. Ordem no backend: T018 → T019 → T020 → T021 →
  T022/T023/T024 → T025–T027. No frontend: T028/T029 → T030 → T031–T035. T036 por último.
- **US2 (T037–T043)**: depende só da Foundational; pode rodar em paralelo com a US1.
- **US3 (T044–T045)**: depende da Foundational e de T021 (blocos gravados) e T024 (assinatura).
- **Polish (T046–T048)**: depois de todas as stories.

## Parallel Example

```text
# Foundational, de uma vez:
T002, T003, T004, T005, T006, T007, T008, T010, T011, T012, T013, T014, T016, T017

# US1, depois de T024:
T025 (ComprovanteApostaResource), T026 (SimulacaoApostaResource), T027 (PaginaInicialController)

# US1 frontend:
T028 (bet_builder.js) e T029 (money.js); depois T034 e T035 junto com T031–T033

# US2 inteira em paralelo com a US1:
T037, T038, T041, T042
```

## Implementation Strategy

1. **MVP**: Setup + Foundational + US1. Com as configurações no banco (padrão: habilitado, desconto
   0, mínimo 2, máximo 10, Recalcular), o Criar Aposta já funciona e pode ser validado.
2. **US2**: libera a edição das configurações pela API (desconto real da banca).
3. **US3**: liquidação na edição de palpites.
4. **Polish**: artefatos da spec 004, Postman e revisão final.

## Notas da implementação (2026-10-13)

**Validação feita** (scripts de rascunho fora do projeto, numa transação desfeita no fim, sem
arquivos de teste):

- cálculo do bloco no backend e no cupom: 4,81 (15%), 5,67 (0%), 3,21 (Recalcular), mínimo 1,00;
- preparo da aposta com jogo real: sem `blocos` → 422; cotação vista errada → 409 com
  `{bloco: true}`; mesmo mercado → 422; bloco certo → perna única;
- aposta de vendedor com bloco + palpite simples: cotação total, prêmio, comissão da faixa de 2
  jogos, desconto e liquidação gravados, comprovante (`quantidade_jogos`, `blocos`), assinatura
  válida e limite por confronto contando o valor uma vez;
- edição: Recalcular (bloco 3,49 → 2,28) e Anular bloco (bloco Anulado, só a perna simples),
  restaurações e histórico;
- rotas `configuracoes/criar-aposta` (GET, PUT válido e os 422) e `PATCH usuarios-configuracoes`;
- `npm run build` e `pint --test` sem erros.

**Pendente (T036)**: a conferência visual no navegador (cupom, modal de mercados, listagem e
impressão) não foi feita nesta execução; fica para a validação manual do responsável pelos cenários
do [quickstart.md](quickstart.md).

**Ajustes feitos durante a implementação** (registrados nos artefatos):

- `UsuariosConfiguracoes::CAMPOS_CRIAR_APOSTA` no lugar de `CAMPOS_APOSTA`, para não alterar o
  concern `ConfiguracoesAposta` (data-model, T014);
- validação mínimo ≤ máximo do vendedor só com os dois campos no pedido, porque o alvo pode ser um
  alcance (data-model seção 4, T041);
- comentário das chaves estrangeiras antes do `constrained()` (senão o comentário não vai para a
  coluna);
- simulação do código devolve `blocos` sem `id` (contrato, T026).

**Revisão de legibilidade (Princípio V)**: a gravação dos blocos e palpites, repetida nos três
caminhos de `CriacaoApostas::gravar`, foi extraída para `gravar_palpites_e_blocos`; o
`TicketModal` passou a montar cada palpite por `linha_palpite`, para intercalar a linha do bloco.
Nenhuma outra refatoração foi necessária.
