---

description: "Lista de tarefas da feature Apuração de resultados e cancelamento de jogos"
---

# Tasks: Apuração de resultados e cancelamento de jogos

**Input**: Documentos de design em `specs/009-apuracao-resultados/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md),
[data-model.md](data-model.md), [contracts/provedor.md](contracts/provedor.md),
[contracts/api.md](contracts/api.md), [contracts/comandos.md](contracts/comandos.md),
[quickstart.md](quickstart.md)

**Tests**: NÃO há tarefas de teste: a constituição proíbe testes automatizados. Cada user story é
validada manualmente pelos cenários do [quickstart.md](quickstart.md), com o mock do provedor.

**Organization**: tarefas agrupadas por user story. Ordem: US1, US2 e US3 (P1), US4 e US5 (P2),
US6 e US7 (P3).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: pode rodar em paralelo (arquivos diferentes, sem dependências pendentes)
- **[Story]**: user story da tarefa (US1 a US7)
- Caminhos relativos à raiz do repositório

## Regras que valem para TODAS as tarefas

- **Sistema antigo (Princípio VII)**: referência de comportamento em `ResultadosCommand`,
  `CanceladosCommand`, `OperacaoController` e `CreditosCommand` (research, "Sistema antigo");
  `C:\Users\wedso\OneDrive\Área de Trabalho\projetos\wssports.bet` é **somente leitura**. Os 6
  defeitos listados no research NÃO podem ser repetidos.
- **Nomes (Princípio I)**: classes `PascalCase`, métodos, variáveis e chaves em `snake_case`
  português; métodos do framework com o nome original; casos de enum em `PascalCase` com acento e
  valores gravados com espaço (ex.: `DevoluçãoAposta = 'Devolução de aposta'`); rota em
  kebab-case; comandos `recurso:acao`.
- **Idioma (Princípio II)**: comentários, logs, resumos dos comandos e mensagens em português.
- **Escopo (Princípio IV)**: arquivos existentes marcados com ⚠️ estão na tabela do
  [plan.md](plan.md) e foram confirmados pelo responsável em 2026-10-14. Nenhum outro arquivo
  existente pode ser alterado; se for preciso, avisar antes.
- **Comentários de colunas (constituição 3.3.0)**: toda coluna nova ou alterada com `->comment()`
  em português, com o texto exato do [data-model.md](data-model.md).
- **Banco**: tabela nova com `timestamps()` + `softDeletes()` e model com `SoftDeletes`; nenhuma
  exclusão física.
- **Dinheiro**: só `bcmath` ou centavos inteiros; nunca `float`. Saldo só por
  `App\Services\SaldoClientes` (nunca gravar `clientes.saldo` direto, nunca apagar transação).
- **Transações**: uma transação e `lockForUpdate` por aposta; erro numa aposta → log e segue para
  a próxima (FR-040).
- **Registro (FR-036 a FR-038)**: toda operação que muda resultado, situação ou saldo chama
  `RegistroApuracao` dentro da mesma transação.
- **Postman (constituição)**: a tarefa que cria rota em `routes/api.php` regenera, na mesma tarefa,
  `docs/postman/wssports_api.postman_collection.json` ⚠️ (substituindo o arquivo, mantendo
  `base_url` e `token`).
- **Legibilidade (Princípio V)**: ao concluir cada tarefa, revisar o código alterado e informar se
  houve ou não refatoração.

---

## Phase 1: Setup

- [ ] T001 Conferir o ambiente: branch `009-apuracao-resultados`, `php artisan migrate:status` sem pendências e o mock do provedor da spec 003 acessível (nenhum arquivo muda)

---

## Phase 2: Foundational (bloqueia todas as user stories)

**Objetivo**: banco, enums, models e o registro de operações, usados por todas as histórias.

**⚠️ CRITICAL**: nenhuma user story começa antes desta fase.

### Migrations (data-model)

- [ ] T002 [P] Criar database/migrations/2026_10_14_000001_create_confrontos_resultados_table.php: `confrontos_id` foreignId → `confrontos` **único**; `placar_casa`, `placar_fora`, `gols_primeiro_tempo_casa`, `gols_primeiro_tempo_fora`, `gols_segundo_tempo_casa`, `gols_segundo_tempo_fora`, `escanteios_casa`, `escanteios_fora` unsignedSmallInteger **nulo**; `situacao` string(20); `assinatura` char(64); `recebido_em` timestamp; timestamps + softDeletes; comentários da seção 1 do data-model
- [ ] T003 [P] Criar database/migrations/2026_10_14_000002_create_confrontos_gols_table.php: `confrontos_id` foreignId → `confrontos`; `ordem` unsignedTinyInteger; `nome` string(150); `minuto` unsignedSmallInteger nulo; `tempo_jogo` string(20) nulo; único (`confrontos_id`, `ordem`); timestamps + softDeletes; comentários da seção 2
- [ ] T004 [P] Criar database/migrations/2026_10_14_000003_create_operacoes_apuracao_table.php: `tipo` string(30); `origem` string(30); `confrontos_id`, `apostas_id`, `apostas_palpites_id`, `clientes_id`, `clientes_transacoes_id` foreignId **nulos** para as tabelas de mesmo nome; `carteira` string(30) nulo; `valor` decimal(15,2) nulo; `antes` e `depois` json nulos; `motivo` string(255) nulo; índices (`confrontos_id`, `created_at`), (`apostas_id`, `created_at`), (`clientes_id`, `created_at`), (`tipo`, `created_at`); timestamps + softDeletes; comentários da seção 3
- [ ] T005 [P] Criar database/migrations/2026_10_14_000004_add_apuracao_apostas_table.php: em `apostas`, `apurada_em` timestamp nulo, `valor_pago` decimal(15,2) padrão 0, `liquidacao_pendente` boolean padrão falso e índice (`liquidacao_pendente`, `apurada_em`); `down` desfaz; comentários da seção 4
- [ ] T006 [P] Criar database/migrations/2026_10_14_000005_add_motivo_cancelamento_apostas_palpites_table.php: em `apostas_palpites`, `motivo_cancelamento` string(30) nulo depois de `restaurado_por`; comentário da seção 4
- [ ] T007 [P] Criar database/migrations/2026_10_14_000006_alterar_autor_apostas_historico_table.php: `usuarios_id` e `ip` de `apostas_historico` passam a aceitar nulo (`->nullable()->comment(...)->change()`, mantendo a chave estrangeira); `down` volta para não nulo; comentários da seção 4
- [ ] T008 [P] Criar database/migrations/2026_10_14_000007_add_apuracao_configuracoes_table.php: em `configuracoes`, `remover_confronto_cancelado` boolean padrão **verdadeiro** e `minutos_pagamento_premio` unsignedSmallInteger padrão **5**; `down` desfaz; comentários da seção 4
- [ ] T009 Rodar `php artisan migrate` e conferir `SHOW FULL COLUMNS` das 7 tabelas (coluna `Comment` preenchida em toda coluna nova ou alterada)

### Enums

- [ ] T010 [P] ⚠️ Em app/Enums/ResultadoAposta.php: caso `Devolvido = 'Devolvido'` e o comentário da classe atualizado ("só o palpite usa Devolvido; a apuração é da spec 009")
- [ ] T011 [P] ⚠️ Em app/Enums/OrigemTransacao.php: caso `DevoluçãoAposta = 'Devolução de aposta'`
- [ ] T012 [P] Criar app/Enums/MotivoCancelamentoPalpite.php: `JogoCancelado = 'Jogo cancelado'`, `EmpateAnulaAposta = 'Empate anula aposta'`
- [ ] T013 [P] Criar app/Enums/TipoOperacaoApuracao.php: `ResultadoJogo = 'Resultado do jogo'`, `SituaçãoJogo = 'Situação do jogo'`, `ResultadoPalpite = 'Resultado do palpite'`, `ResultadoAposta = 'Resultado da aposta'`, `PalpiteCancelado = 'Palpite cancelado'`, `PalpiteRestaurado = 'Palpite restaurado'`, `PrêmioPago = 'Prêmio pago'`, `PrêmioAjustado = 'Prêmio ajustado'`, `PrêmioEstornado = 'Prêmio estornado'`, `ValorDevolvido = 'Valor devolvido'`
- [ ] T014 [P] Criar app/Enums/OrigemApuracao.php: `CargaResultados = 'Carga de resultados'`, `CargaCancelados = 'Carga de cancelados'`, `PagamentoPrêmios = 'Pagamento de prêmios'`, `ReprocessamentoManual = 'Reprocessamento manual'`, `LançamentoManual = 'Lançamento manual'`, `EncerramentoEspecial = 'Encerramento de especial'`

### Models

- [ ] T015 [P] Criar app/Models/ConfrontosResultados.php (`SoftDeletes`, casts inteiros e `recebido_em`, `situacao` → `SituacaoConfronto`, relação `confronto()`, método `apuravel(): bool` = situação `Encerrado` e os dois placares não nulos, R-03)
- [ ] T016 [P] Criar app/Models/ConfrontosGols.php (`SoftDeletes`, relação `confronto()`)
- [ ] T017 [P] Criar app/Models/OperacoesApuracao.php (`SoftDeletes`, `$guarded = ['id']`, casts `tipo` → `TipoOperacaoApuracao`, `origem` → `OrigemApuracao`, `carteira` → `Carteira`, `valor` `decimal:2`, `antes`/`depois` `array`; sem métodos de alteração)
- [ ] T018 [P] ⚠️ Em app/Models/Confrontos.php: relações `resultado(): HasOne` (`ConfrontosResultados`) e `gols(): HasMany` (`ConfrontosGols`, ordenado por `ordem`)
- [ ] T019 [P] ⚠️ Em app/Models/Apostas.php: casts `apurada_em` `datetime`, `valor_pago` `decimal:2`, `liquidacao_pendente` `boolean`
- [ ] T020 [P] ⚠️ Em app/Models/ApostasPalpites.php: cast `motivo_cancelamento` → `MotivoCancelamentoPalpite`
- [ ] T021 [P] ⚠️ Em app/Models/Configuracoes.php: `remover_confronto_cancelado` e `minutos_pagamento_premio` no `$fillable` e casts `boolean` e `integer`

### Registro de operações

- [ ] T022 Criar app/Services/RegistroApuracao.php: `registrar(TipoOperacaoApuracao $tipo, OrigemApuracao $origem, array $referencias, ?array $antes, ?array $depois, ?string $motivo, ?string $valor = null, ?Carteira $carteira = null, ?int $clientes_transacoes_id = null): OperacoesApuracao` — só insere (R-13); `$referencias` aceita `confrontos_id`, `apostas_id`, `apostas_palpites_id`, `clientes_id`

**Checkpoint**: banco migrado, enums e models prontos.

---

## Phase 3: User Story 1 - Resultado do jogo decide palpites e apostas (Priority: P1) 🎯 MVP

**Goal**: a carga de resultados grava o resultado e decide palpites e apostas, sem pagar.

**Independent Test**: quickstart cenários 1 (até `liquidacao_pendente` verdadeiro), 3 (parte da
carga), 4 a 11.

### Provedor e validação

- [ ] T023 [P] [US1] ⚠️ Em config/services.php, dentro de `provedor_cotacoes`: `'tempo_limite_resultados' => 180` e `'tempo_limite_cancelados' => 60`; atualizar o comentário das rotas (incluir `resultados` e `cancelados`)
- [ ] T024 [US1] ⚠️ Em app/Services/ProvedorCotacoes.php: `buscar_resultados(): array` = `GET {url_pre_jogo}/resultados` com `tempo_limite_resultados`, pelo `enviar('resultados', …)` existente
- [ ] T025 [US1] ⚠️ Em app/Services/ValidacaoCargaProvedor.php: `validar_resultados(array $resposta): array{resultados, motivos}` conforme [contracts/provedor.md](contracts/provedor.md#6-resultados-confrontos_resultadosimportar-a-cada-5-min-03): `fonte_id` inteiro > 0; `situacao` de `SituacaoConfronto`; placares, gols de tempo e escanteios "inteiro ≥ 0 ou nulo"; "um nulo e o outro não → item ignorado" para `placar_casa`/`placar_fora`; `eventos` lista ou `null` (`null` = manter os gols gravados, devolver `gols => null`); `ordem` = número inteiro no início de `tipo_evento` ("10º Gol" → 10, sem número → evento ignorado); `nome` até 150; `tempo_jogo` até 20; nomes traduzidos para os do sistema (`gols_primeiro_tempo_casa`…, `escanteios_casa`…)

### Regras dos mercados

- [ ] T026 [P] [US1] Criar app/Support/PlacarConfronto.php: objeto só leitura montado de `ConfrontosResultados` + gols (`placar_casa`, `placar_fora`, gols de cada tempo, escanteios, lista de gols com `ordem` e `nome`), com `total(string $periodo)`, `gols(string $periodo, string $lado)` e `tem_escanteios()`, `tem_tempos()`
- [ ] T027 [US1] Criar app/Support/ResultadosCotacao.php (R-04): tabela `código → [regra, parâmetros]` para `odd1` a `odd323`, agrupada como `MercadosCotacao`, com o nome de `NomesCotacoes` em comentário; regras parametrizadas da tabela do research (`vencedor`, `dupla_chance`, `ambas_marcam`, `ambas_marcam_tempos`, `handicap`, `intervalo_final`, `placar_exato`, `total_gols`, `par_impar`, `vencedor_ambas`, `vencedor_total`, `empate_anula`, `escanteios`, `tempo_mais_gols`, `time_tempo_mais_gols`, `sem_sofrer_gol`, `margem_vitoria`, `total_time`); método `avaliar(string $codigo, PlacarConfronto $placar): ResultadoAposta`. Cada código reproduz **exatamente** a expressão do `oddN` de `OperacaoController.php` do antigo, inclusive `odd114` (= 3 gols no 2º tempo) e `odd245`/`odd247` (0 não é par), mantidos por decisão do responsável; `odd164`/`odd165` devolvem `Devolvido` no empate; escanteios (`odd166`–`odd224`) sem dado e mercados de tempo sem gols de tempo devolvem `Aguardando`; código desconhecido → `Aguardando` + log
- [ ] T028 [US1] Em app/Support/ResultadosCotacao.php: `avaliar_jogador(string $nome, string $tipo, PlacarConfronto $placar): ResultadoAposta` (R-05): nome comparado sem diferenciar maiúsculas, acentos e espaços nas pontas; "Qualquer Momento" (marcou), "Primeiro" (ordem 1), "Último" (maior ordem); sem gol do jogador → `Perdedor`; tipo desconhecido → `Aguardando` + log

### Cancelamento e restauração pelo sistema

- [ ] T029 [US1] ⚠️ Em app/Services/EdicaoApostas.php (R-06, R-08): `cancelar_palpite_pelo_sistema(Apostas $aposta, ApostasPalpites $palpite, ?Usuarios $autor, ?string $ip, ?string $user_agent, ?MotivoCancelamentoPalpite $motivo = null)` grava `motivo_cancelamento`, aplica `anular_bloco` quando o palpite é de bloco e a aposta usa `Anular bloco` (sem a trava do último palpite); `recalcular_e_registrar` e `anular_bloco`/`restaurar_bloco` aceitam autor nulo (`usuarios_id`, `cancelado_por`, `restaurado_por` e `ip` nulos); a chamada de `EncerramentoEspeciais` continua funcionando sem mudança
- [ ] T030 [US1] ⚠️ Em app/Services/EdicaoApostas.php: método novo `restaurar_palpite_pelo_sistema(Apostas $aposta, ApostasPalpites $palpite): Apostas` — só para `motivo_cancelamento = Empate anula aposta`; volta o palpite (ou o bloco anulado) para `Ativo`, limpa `motivo_cancelamento`, recalcula e registra `RestaurarPalpite` no histórico sem autor; roda dentro da transação de quem chama

### Apuração

- [ ] T031 [US1] Criar app/Services/ImportacaoResultados.php: `importar(OrigemApuracao $origem): array` — busca e valida; carrega os jogos por `codigo_externo` em lotes de 1000; para cada jogo calcula a assinatura SHA-256 (resultado + gols ordenados); igual à gravada → ignora; diferente → numa transação grava/atualiza `confrontos_resultados` (`recebido_em = agora`), sincroniza `confrontos_gols` (atualiza ou restaura as ordens recebidas, soft delete nas que sumiram; `eventos` nulo não mexe), grava `confrontos.situacao = Encerrado` quando a situação recebida for `Encerrado` e registra `ResultadoJogo` com antes/depois; depois chama `ApuracaoApostas::apurar_confronto`. Resumo: recebidos, alterados, apostas apuradas, ignorados, falhas
- [ ] T032 [US1] Criar app/Services/ApuracaoApostas.php (R-09): `apurar_confronto(Confrontos $confronto, OrigemApuracao $origem): array` busca os `apostas_id` com palpite no jogo (apostas `Ativa`) em lotes de 500 e chama `apurar_aposta` para cada um, capturando erro por aposta (log com o id e a classe do erro); `apurar_aposta(int $apostas_id, OrigemApuracao $origem): bool` numa transação com `lockForUpdate`: só `situacao = Ativa`; carrega palpites, resultados e gols dos jogos da aposta de uma vez (`whereIn`); reavalia palpites de jogo `Ativo` e os cancelados com motivo `Empate anula aposta` (jogo não apurável → `Aguardando`; palpite de jogador por `avaliar_jogador`); `Devolvido` de palpite ativo → `cancelar_palpite_pelo_sistema` com motivo `EmpateAnulaAposta`; palpite com motivo de empate cujo novo resultado não é `Devolvido` → `restaurar_palpite_pelo_sistema`; grava `resultado` do palpite e registra `ResultadoPalpite`/`PalpiteCancelado`/`PalpiteRestaurado`; palpites especiais mantêm o resultado gravado; retorna se algo mudou
- [ ] T033 [US1] Em app/Services/ApuracaoApostas.php: `decidir(Apostas $aposta, OrigemApuracao $origem): void` — pelos palpites `Ativo`: algum `Perdedor` → `Perdedor`; algum `Aguardando` → `Aguardando`; senão → `Vencedor` (inclui nenhum ativo, FR-015); quando o resultado ou o total a pagar (`premio + valor_acrescido`) mudam: grava `resultado`, `apurada_em = agora` e `liquidacao_pendente` (verdadeiro só para aposta com `clientes_id` e forma `Saldo` ou `Promoção esportes` cujo valor devido ≠ `valor_pago`), e registra `ResultadoAposta` com antes/depois
- [ ] T034 [US1] Criar app/Console/Commands/ImportarResultadosCommand.php (`confrontos_resultados:importar`, descrição em português) com `ExecutarCargaPreJogo`, chamando `ImportacaoResultados::importar(OrigemApuracao::CargaResultados)` e o resumo de [contracts/comandos.md](contracts/comandos.md#confrontos_resultadosimportar)
- [ ] T035 [US1] ⚠️ Em routes/console.php: `Schedule::command('confrontos_resultados:importar')->cron('3-59/5 * * * *')->runInBackground()->withoutOverlapping(10);` com comentário no padrão das outras cargas

**Checkpoint**: resultados gravados e apostas decididas; nada pago ainda.

---

## Phase 4: User Story 2 - Prêmio creditado no saldo do cliente (Priority: P1)

**Goal**: aposta decidida como `Vencedor` recebe o crédito após a janela, uma vez só.

**Independent Test**: quickstart cenários 1, 2, 3, 16 (parte do crédito), 17, 18, 30.

- [ ] T036 [US2] Criar app/Services/LiquidacaoApostas.php (R-10): `liquidar(Apostas $aposta, OrigemApuracao $origem): void` — chamada com a aposta já travada; valor devido = `premio + valor_acrescido` se `Vencedor`, senão 0 (bcmath); aposta sem `clientes_id` ou com forma `Dinheiro` → só `liquidacao_pendente = false`; diferença > 0 e `apurada_em` + `minutos_pagamento_premio` já passou → `SaldoClientes::creditar` na carteira da forma de pagamento (`Saldo` → `Carteira::Saldo`, `Promoção esportes` → `Carteira::PromoçãoEsportes`), origem `Prêmio` (ou `DevoluçãoAposta` quando a aposta não tem palpite `Ativo`), `referencia_id` = id da aposta; atualiza `valor_pago` e `liquidacao_pendente`; registra `PrêmioPago` (ou `ValorDevolvido`, ou `PrêmioAjustado` quando já havia valor pago) com carteira, valor, transação e saldo antes/depois; diferença = 0 → `liquidacao_pendente = false`
- [ ] T037 [US2] Em app/Services/ApuracaoApostas.php: ao fim de `apurar_aposta`, chamar `LiquidacaoApostas::liquidar` (respeita a janela; sem janela vencida nada é creditado)
- [ ] T038 [US2] Criar app/Console/Commands/LiquidarPremiosCommand.php (`apostas:liquidar_premios`): apostas com `liquidacao_pendente = true` e `apurada_em <= agora − minutos_pagamento_premio`, em lotes de 500 ids; uma transação com `lockForUpdate` por aposta, chamando `liquidar` com origem `PagamentoPrêmios`; erro por aposta → log e segue; resumo de [contracts/comandos.md](contracts/comandos.md#apostasliquidar_premios)
- [ ] T039 [US2] ⚠️ Em routes/console.php: `Schedule::command('apostas:liquidar_premios')->everyMinute()->withoutOverlapping(5);` com comentário
- [ ] T040 [US2] ⚠️ Em app/Services/EncerramentoEspeciais.php: `decidir_apostas` passa a chamar `ApuracaoApostas::decidir` + `LiquidacaoApostas::liquidar` com origem `EncerramentoEspecial` para cada aposta (mesma regra para apostas só de especiais e mistas, FR-011); o comentário da classe deixa de dizer que nenhum saldo é movimentado

**Checkpoint**: prêmios pagos uma vez, na carteira certa, depois da janela.

---

## Phase 5: User Story 3 - Correção de resultado desfaz ou ajusta o pagamento (Priority: P1)

**Goal**: resultado corrigido reapura apostas já decididas e estorna ou ajusta só a diferença.

**Independent Test**: quickstart cenários 10, 12 a 16.

- [ ] T041 [US3] Em app/Services/LiquidacaoApostas.php: diferença < 0 → `SaldoClientes::debitar` **na hora** (sem esperar a janela) na mesma carteira, origem `Estorno`, `permitir_negativo: true`, `referencia_id` = id da aposta; atualiza `valor_pago`; registra `PrêmioEstornado` (devido 0) ou `PrêmioAjustado` (devido > 0) com saldo antes/depois; nenhuma transação apagada e nenhum saldo sobrescrito (FR-027 a FR-029)
- [ ] T042 [US3] Validar US3 pelos cenários 10 e 12 a 16 do quickstart (sem arquivo novo; só corrigir app/Services/ApuracaoApostas.php ou app/Services/LiquidacaoApostas.php se algum cenário falhar): reapuração de jogo alterado inclui apostas já decididas e pagas, inclusive voltando a `Aguardando` (FR-019); palpite devolvido por empate é restaurado quando a correção desfaz o empate (FR-013); aposta `Vencedor` não paga que vira `Perdedor` dentro da janela não gera crédito nem estorno

**Checkpoint**: correções estornam ou ajustam sem perder histórico.

---

## Phase 6: User Story 4 - Jogo cancelado ou adiado (Priority: P2)

**Goal**: jogos cancelados ou adiados cancelam os palpites das apostas sem resultado e devolvem o
valor quando não sobra palpite.

**Independent Test**: quickstart cenários 19 a 24, 31 e 33.

- [ ] T043 [US4] ⚠️ Em app/Services/ProvedorCotacoes.php: `buscar_cancelados(): array` = `GET {url_pre_jogo}/cancelados` com `tempo_limite_cancelados`
- [ ] T044 [US4] ⚠️ Em app/Services/ValidacaoCargaProvedor.php: `validar_cancelados(array $resposta): array{codigos, motivos}` — resposta lista; o primeiro item traz `fonte_id` (lista de inteiros > 0); lista vazia ou `fonte_id` vazio → nenhum código; código inválido ignorado com motivo
- [ ] T045 [US4] Criar app/Services/CancelamentoConfrontos.php (R-15): `importar(OrigemApuracao $origem): array` — busca e valida; para cada jogo existente: situação diferente de `Cancelado`/`Adiado` → grava `Cancelado` e registra `SituaçãoJogo`; com `Configuracoes::atual()->remover_confronto_cancelado`: para cada aposta `Ativa` com resultado `Aguardando` e palpite do jogo `Ativo` com resultado `Aguardando` (lotes de 500), numa transação com `lockForUpdate`: `cancelar_palpite_pelo_sistema` com motivo `JogoCancelado`, registra `PalpiteCancelado` e chama `ApuracaoApostas::decidir` + `LiquidacaoApostas::liquidar`; palpite já cancelado não é tocado; erro por aposta → log e segue; resumo de [contracts/comandos.md](contracts/comandos.md#confrontos_canceladosimportar)
- [ ] T046 [US4] Criar app/Console/Commands/ImportarCanceladosCommand.php (`confrontos_cancelados:importar`) com `ExecutarCargaPreJogo`, chamando `CancelamentoConfrontos::importar(OrigemApuracao::CargaCancelados)`
- [ ] T047 [US4] ⚠️ Em routes/console.php: `Schedule::command('confrontos_cancelados:importar')->cron('4-59/5 * * * *')->runInBackground()->withoutOverlapping(10);` com comentário
- [ ] T048 [US4] ⚠️ Em app/Services/ImportacaoConfrontos.php (R-19, FR-044): tirar `situacao` da lista simples `$atualizadas` do `upsert` e incluí-la como expressão que mantém o valor atual quando ele é `Cancelado`, `Adiado` ou `Encerrado` (ex.: `'situacao' => DB::raw("IF(situacao IN ('Cancelado','Adiado','Encerrado'), situacao, VALUES(situacao))")`, com os valores montados de `SituacaoConfronto`); as demais colunas e o restante da carga não mudam; comentário explicando o motivo
- [ ] T049 [P] [US4] Criar app/Http/Requests/ConfiguracoesApuracaoRequest.php no padrão de `ConfiguracoesCriarApostaRequest`: `remover_confronto_cancelado` `sometimes`, `boolean`; `minutos_pagamento_premio` `sometimes`, `integer`, `between:0,1440`; mensagens exatas de [contracts/api.md](contracts/api.md)
- [ ] T050 [P] [US4] Criar app/Http/Resources/ConfiguracoesApuracaoResource.php (`remover_confronto_cancelado`, `minutos_pagamento_premio`)
- [ ] T051 [US4] Criar app/Http/Controllers/ConfiguracoesApuracaoController.php no padrão de `ConfiguracoesCriarApostaController` (`HasMiddleware`, `permissao_cliente('configuracoes.editar')`, `show` e `update`)
- [ ] T052 [US4] ⚠️ Em routes/api.php: `GET` e `PUT configuracoes/apuracao` logo depois das rotas de `configuracoes/criar-aposta`, com comentário "(spec 009)" e o `use` do controller; regenerar ⚠️ docs/postman/wssports_api.postman_collection.json com as 2 rotas, mantendo `base_url` e `token`

**Checkpoint**: jogos cancelados tratados conforme a configuração.

---

## Phase 7: User Story 5 - Registro de todas as operações (Priority: P2)

**Goal**: toda operação da apuração tem registro com antes/depois.

**Independent Test**: quickstart cenários 1, 2, 12, 20 e 25, conferindo `operacoes_apuracao`.

- [ ] T053 [US5] Revisar app/Services/ImportacaoResultados.php, app/Services/ApuracaoApostas.php, app/Services/LiquidacaoApostas.php e app/Services/CancelamentoConfrontos.php: cada um dos 10 tipos de `TipoOperacaoApuracao` é registrado no ponto certo, na mesma transação, com `origem`, referências (jogo, aposta, palpite, cliente), `carteira`, `valor`, `clientes_transacoes_id`, `antes`/`depois` e `motivo` legível (ex.: "Placar corrigido pelo provedor", "Empate anula aposta", "Jogo cancelado pelo provedor"); nenhuma operação sem mudança real gera registro (SC-007); nenhum código altera ou apaga `operacoes_apuracao` (FR-038)

**Checkpoint**: histórico completo para a futura tela de consulta.

---

## Phase 8: User Story 6 - Reapurar um jogo manualmente (Priority: P3)

**Goal**: comando para reapurar um jogo.

**Independent Test**: quickstart cenários 25 e 26.

- [ ] T054 [US6] Criar app/Console/Commands/ReapurarConfrontoCommand.php (`apostas:reapurar {confronto}`): `confronto` = `confrontos.id`; inexistente → "Jogo não encontrado." e `FAILURE`; senão `ApuracaoApostas::apurar_confronto($confronto, OrigemApuracao::ReprocessamentoManual)` mesmo sem mudança de assinatura; resumo de [contracts/comandos.md](contracts/comandos.md#apostasreapurar-confronto)

**Checkpoint**: comando manual funcionando.

---

## Phase 9: User Story 7 - Administrador lança o resultado de um jogo manual (Priority: P3)

**Goal**: rota para lançar ou corrigir o resultado de um jogo cadastrado à mão.

**Independent Test**: quickstart cenários 34 a 36.

- [ ] T055 [US7] ⚠️ Em app/Enums/Funcao.php: `'confrontos.lancar_resultado'` em `PERMISSOES_CONFRONTOS` (não entra em `PERMISSOES_CONFRONTOS_GERENTE`, logo só Admin e Supervisor); criar database/seeders/ApuracaoSeeder.php no padrão de `ApostasSeeder` (cria a permissão no guard `api` e a dá aos usuários existentes conforme `permissoes_padrao()`); ⚠️ em database/seeders/DatabaseSeeder.php chamar `ApuracaoSeeder` depois do `ApostasSeeder`; rodar `php artisan db:seed --class=ApuracaoSeeder`
- [ ] T056 [US7] Em app/Services/ImportacaoResultados.php: extrair da T031 o método público `gravar_resultado(Confrontos $confronto, array $dados, OrigemApuracao $origem): int` (assinatura, gravação, gols, situação `Encerrado`, registro e apuração; devolve a quantidade de apostas apuradas, 0 quando a assinatura não mudou), usado pela carga e pela rota
- [ ] T057 [P] [US7] Criar app/Http/Requests/ConfrontosResultadosRequest.php com as regras e as mensagens exatas de [contracts/api.md](contracts/api.md#put-apiconfrontosconfrontoresultado): `placar_casa`/`placar_fora` `required`, inteiro, 0 a 99; gols de tempo `nullable`, 0 a 99, "os quatro juntos"; escanteios `nullable`, 0 a 99, "os dois juntos"; `gols` `nullable`, até 99 itens, `ordem` 1 a 99 sem repetição, `nome` até 150, `minuto` 0 a 200, `tempo_jogo` até 20; e app/Http/Resources/ConfrontosResultadosResource.php no formato de resposta do contrato
- [ ] T058 [US7] Criar app/Http/Controllers/ConfrontosResultadosController.php (`HasMiddleware`, `permissao_cliente('confrontos.lancar_resultado')`, ação `update`): jogo não manual → 422 "O resultado de jogos do provedor vem da carga automática."; senão `ImportacaoResultados::gravar_resultado($confronto, $dados, OrigemApuracao::LançamentoManual)` e resposta 200 com `apostas_apuradas`
- [ ] T059 [US7] ⚠️ Em routes/api.php: `PUT confrontos/{confronto}/resultado` no grupo de gestão de confrontos, logo depois de `confrontos/{confronto}/situacao`, com `->missing($confronto_nao_encontrado)` e o `use` do controller; regenerar ⚠️ docs/postman/wssports_api.postman_collection.json com a rota, mantendo `base_url` e `token`

**Checkpoint**: todas as histórias funcionando.

---

## Phase 10: Polish & Cross-Cutting

- [ ] T060 [P] ⚠️ Em specs/004-apostas/data-model.md: nota de que `apostas_historico.usuarios_id` e `ip` aceitam nulo (ação do sistema, spec 009) e de que `resultado` do palpite ganha `Devolvido`
- [ ] T061 [P] ⚠️ Em specs/006-ajustes-tela-principal/spec.md: nota em FR-018 de que a decisão e o pagamento das apostas com especial passam pela apuração da spec 009
- [ ] T062 Revisão de legibilidade (Princípio V) do conjunto: nomes, duplicação entre `ImportacaoResultados` e `CancelamentoConfrontos` (lotes, captura de erro), funções longas em `ApuracaoApostas` e `ResultadosCotacao`, comentários desatualizados; informar o que foi refatorado ou que nada foi preciso
- [ ] T063 Validar todos os cenários do [quickstart.md](quickstart.md) com o mock do provedor, incluindo o 32 (desempenho: tempos do resumo dos comandos contra as metas do plano), e anotar o resultado em "Notas da implementação" neste arquivo

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sem dependências.
- **Foundational (Phase 2)**: depois do Setup; bloqueia todas as histórias.
- **US1 (Phase 3)**: depois da Foundational; base de US2, US3, US4 e US6.
- **US2 (Phase 4)**: depois da US1 (liquidação chamada pela apuração).
- **US3 (Phase 5)**: depois da US2 (débito na mesma classe de liquidação).
- **US4 (Phase 6)**: depois da US2 (devolução usa a liquidação); a rota de configuração (T049 a
  T052) pode ser feita em paralelo a qualquer fase depois da Foundational.
- **US5 (Phase 7)**: depois da US1 a US4 (revisa os pontos de registro).
- **US6 (Phase 8)**: depois da US1.
- **US7 (Phase 9)**: depois da US2 (a rota apura e paga pela mesma gravação da carga).
- **Polish (Phase 10)**: depois de todas.

### Dentro de cada história

- Provedor e validação → regras/serviços → comando → agenda.
- Tarefas no mesmo arquivo seguem a ordem dos IDs (ex.: T029 → T030 em `EdicaoApostas.php`;
  T032 → T033 → T037 em `ApuracaoApostas.php`; T036 → T041 em `LiquidacaoApostas.php`;
  T031 → T056 em `ImportacaoResultados.php`; T052 → T059 em `routes/api.php` e no Postman).

### Parallel Opportunities

- T002 a T008 (migrations), T010 a T014 (enums) e T015 a T021 (models): arquivos diferentes.
- T023, T026 em paralelo no início da US1.
- T049 e T050 em paralelo; T057 em paralelo com T055/T056.
- T060 e T061 em paralelo.

---

## Parallel Example

```bash
# Foundational: migrations juntas
Task: "T002 create_confrontos_resultados_table"
Task: "T003 create_confrontos_gols_table"
Task: "T004 create_operacoes_apuracao_table"

# US1: peças independentes
Task: "T023 tempos limite em config/services.php"
Task: "T026 PlacarConfronto"
```

---

## Implementation Strategy

### MVP (US1)

1. Phase 1 + Phase 2.
2. Phase 3 (US1): resultados gravados e apostas decididas.
3. **Validar** cenários 1 e 4 a 11 do quickstart (sem pagamento).

### Entrega incremental

1. US2 → prêmios pagos (cenários 2, 3, 17, 18, 30).
2. US3 → correções (cenários 10, 12 a 16).
3. US4 → cancelados, configuração e proteção da grade (19 a 24, 31, 33).
4. US5, US6 e US7 → registro completo, comando manual e resultado de jogo manual (25, 26, 34 a 36).
5. Polish → notas nas specs, legibilidade e quickstart completo (com o cenário 32 de desempenho).
