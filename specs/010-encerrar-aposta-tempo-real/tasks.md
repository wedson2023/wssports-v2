---

description: "Lista de tarefas da feature Encerrar aposta, bilhete em tempo real e notificações push"
---

# Tasks: Encerrar aposta, bilhete em tempo real e notificações push

**Input**: Documentos de design em `specs/010-encerrar-aposta-tempo-real/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md),
[data-model.md](data-model.md), [contracts/api.md](contracts/api.md),
[contracts/tempo_real.md](contracts/tempo_real.md), [contracts/comandos.md](contracts/comandos.md),
[quickstart.md](quickstart.md)

**Tests**: NÃO há tarefas de teste: a constituição proíbe testes automatizados. Cada user story é
validada manualmente pelos cenários do [quickstart.md](quickstart.md), com o mock do provedor.

**Organization**: tarefas agrupadas por user story, na ordem de prioridade: US1 (P1, bilhete em
tempo real), US2 (P2, encerrar aposta), US3 (P3, notificações).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: pode rodar em paralelo (arquivos diferentes, sem dependências pendentes)
- **[Story]**: user story da tarefa (US1 a US3)
- Caminhos relativos à raiz do repositório
- ⚠️ = arquivo existente; está na tabela do [plan.md](plan.md) e foi confirmado pelo responsável em
  2026-10-15

## Regras que valem para TODAS as tarefas

- **Sistema antigo (Princípio VII)**: referência de comportamento nos arquivos da seção "Sistema
  antigo" do [research.md](research.md). `C:\Users\wedso\OneDrive\Área de Trabalho\projetos\wssports.bet`
  é **somente leitura**. O encerramento de aposta não existe no antigo ("cashout" de lá é saque).
- **Nomes (Princípio I)**: classes `PascalCase`; métodos, variáveis e chaves em `snake_case`
  português; métodos do framework e de pacotes com o nome original (`broadcastOn`, `broadcastAs`,
  `broadcastWith`, `handle`, `rules`…). Casos de enum em `PascalCase` com acento e valores gravados
  com espaço (ex.: `EmAnálise = 'Em análise'`). Rotas em kebab-case e comandos `recurso:acao`.
  Frontend: componentes em inglês `PascalCase`; props, variáveis e funções em `snake_case`
  português; transient props `$` + `snake_case`; hooks `use` + camelCase português.
- **Idioma (Princípio II)**: comentários, logs, mensagens da API, textos da página e das
  notificações em português.
- **Componentes (Princípio III)**: cada componente e a página em pasta própria com `index.jsx` e
  `styles.jsx`.
- **Escopo (Princípio IV)**: só os arquivos existentes marcados com ⚠️ podem mudar, e só no trecho
  descrito. Se for preciso outro, avisar antes.
- **Comentários de colunas (constituição 3.3.0)**: toda coluna nova ou alterada com `->comment()`,
  com o texto exato do [data-model.md](data-model.md).
- **Banco**: tabela nova com `timestamps()` + `softDeletes()` e model com `SoftDeletes`; nenhuma
  exclusão física.
- **Dinheiro**: só `bcmath` ou centavos inteiros, nunca `float`. Saldo só por
  `App\Services\SaldoClientes`. Truncar centavos (nunca arredondar a favor do cliente).
- **Transações**: todo pedido, decisão, crédito ou desfazimento de encerramento roda numa transação
  com `Apostas::lockForUpdate()`. Broadcast, jobs e push vão depois do commit (`afterCommit()` ou
  depois do `DB::transaction`).
- **Tolerância a falhas**: falha do Reverb ou do envio de push vira `Log::warning` e nunca desfaz a
  carga do ao vivo, a apuração ou o encerramento.
- **Privacidade**: cotações ajustadas por cliente e a oferta nunca vão em canal de broadcast
  (contracts/tempo_real.md).
- **Postman (constituição)**: a tarefa que cria rota em `routes/api.php` regenera, na mesma tarefa,
  `docs/postman/wssports_api.postman_collection.json` ⚠️ (substituindo o arquivo, mantendo
  `base_url` e `token`).
- **Legibilidade (Princípio V)**: ao concluir cada tarefa, revisar o código alterado e informar se
  houve ou não refatoração.

---

## Phase 1: Setup

**Objetivo**: dependências e configuração do tempo real e do push.

- [ ] T001 Conferir o ambiente: branch `010-encerrar-aposta-tempo-real`, `php artisan migrate:status` sem pendências, mock do provedor das specs 003/009 acessível e extensões PHP `bcmath`, `mbstring` e `openssl` habilitadas (nenhum arquivo muda)
- [ ] T002 ⚠️ Instalar `composer require laravel/reverb:^1.0 minishlink/web-push:^9.0` (altera composer.json e composer.lock); no composer.json, script `dev`: acrescentar o comando `php artisan reverb:start` ao `concurrently`, com o nome `reverb` e uma cor a mais, e trocar `php artisan queue:listen --tries=1 --timeout=0` por `php artisan queue:listen --queue=apostas,notificacoes,default --tries=1 --timeout=0` (filas dos jobs novos e do `DecidirApostaAoVivo`)
- [ ] T003 [P] ⚠️ Instalar `npm install laravel-echo@^2.0 pusher-js@^8.4` (altera package.json e package-lock.json)
- [ ] T004 Criar config/broadcasting.php (`php artisan config:publish broadcasting`) com a conexão padrão `env('BROADCAST_CONNECTION', 'null')` e a conexão `reverb` lendo `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_APP_ID`, `REVERB_HOST`, `REVERB_PORT` e `REVERB_SCHEME`; criar config/reverb.php (`php artisan vendor:publish --provider="Laravel\Reverb\ReverbServiceProvider"`, sem rodar o `reverb:install`, que mexeria no bootstrap). Não usar `install:broadcasting` (canais só públicos, sem rota de autorização, R-01)
- [ ] T005 [P] ⚠️ Em config/services.php: bloco `notificacoes_push` com `chave_publica` (`VAPID_PUBLIC_KEY`), `chave_privada` (`VAPID_PRIVATE_KEY`) e `assunto` (`VAPID_SUBJECT`, padrão `mailto:` + `MAIL_FROM_ADDRESS`), comentário em português (R-13)
- [ ] T006 [P] ⚠️ Em .env.example: `BROADCAST_CONNECTION=reverb` no lugar de `log`; bloco comentado com `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_HOST=localhost`, `REVERB_PORT=8080`, `REVERB_SCHEME=http`, `REVERB_SERVER_HOST=0.0.0.0`, `REVERB_SERVER_PORT=8080`; bloco comentado com `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY` e `VAPID_SUBJECT` ("gerar com php artisan notificacoes_push:gerar_chaves")

---

## Phase 2: Foundational (bloqueia todas as user stories)

**Objetivo**: banco, enums, models, configurações, eventos de broadcast e cotações atuais usados
pelas três histórias.

**⚠️ CRITICAL**: nenhuma user story começa antes desta fase.

### Migrations (data-model, seção 10)

- [ ] T007 [P] Criar database/migrations/2026_10_15_000001_create_apostas_encerramentos_automaticos_table.php (seção 2): `apostas_id` foreignId → `apostas`; `clientes_id` foreignId → `clientes`; `usuarios_id` foreignId → `usuarios` **nulo**; `valor_alvo` decimal(15,2); `situacao` string(20); `executado_em` e `cancelado_em` timestamp nulo; `motivo_cancelamento` string(100) nulo; índice (`situacao`, `apostas_id`); timestamps + softDeletes; comentário de cada coluna como no data-model
- [ ] T008 [P] Criar database/migrations/2026_10_15_000002_create_apostas_encerramentos_table.php (seção 1, depois da T007): todas as 32 colunas da tabela "1. apostas_encerramentos" com tipos, nulos e `chave_idempotencia` char(36) **único**; foreignIds `usuarios_id`, `apostas_encerramentos_automaticos_id`, `clientes_transacoes_id` e `estorno_clientes_transacoes_id` **nulos**; `palpites` json; `rollovers` json nulo; decimais (15,2) para valores, (5,2) para `margem` e (11,10) para `proporcao`, `fracao_antes` e `fracao_depois`; índices (`apostas_id`, `situacao`), (`situacao`, `creditar_em`), (`clientes_id`, `created_at`) e (`modalidade`, `created_at`); timestamps + softDeletes; comentários da seção 1
- [ ] T009 [P] Criar database/migrations/2026_10_15_000003_add_encerramento_apostas_table.php: em `apostas`, `fracao_restante` decimal(11,10) padrão 1 com o comentário "Parte da aposta que continua valendo depois de encerramentos parciais (1 = inteira, 0 = encerrada); multiplica o total a pagar"; `resultado` ganha `->comment('Resultado da aposta: Aguardando, Vencedor, Perdedor ou Encerrada')->change()` mantendo string(20) e padrão `Aguardando`; `down` remove a coluna e o comentário
- [ ] T010 [P] Criar database/migrations/2026_10_15_000004_add_encerramento_configuracoes_table.php: as 10 colunas da tabela `configuracoes` da seção 6 (booleans padrão verdadeiro; `margem_encerramento_pre_jogo` decimal(5,2) padrão 7.00; `margem_encerramento_ao_vivo` decimal(5,2) padrão 10.00; `valor_minimo_encerramento` decimal(15,2) padrão 1.00; `percentual_minimo_encerramento` decimal(5,2) padrão 5.00; `segundos_validade_oferta` unsignedSmallInteger padrão 8; `limite_encerramentos_parciais` unsignedSmallInteger padrão 5), com os comentários; `down` desfaz
- [ ] T011 [P] Criar database/migrations/2026_10_15_000005_add_encerrar_aposta_clientes_configuracoes_table.php: em `clientes_configuracoes`, `encerrar_aposta` boolean padrão **verdadeiro**, comentário "Permite ao cliente encerrar as próprias apostas"; `down` desfaz
- [ ] T012 [P] Criar database/migrations/2026_10_15_000006_create_confrontos_eventos_table.php (seção 5): `confrontos_id` foreignId → `confrontos`; `confrontos_ao_vivo_id` foreignId → `confrontos_ao_vivo` **nulo**; `tipo` string(20); `placar_casa`, `placar_fora` unsignedSmallInteger; `minuto` unsignedSmallInteger nulo; `chave` string(40) **nulo, único**; `notificado_em` timestamp nulo; índice (`confrontos_id`, `created_at`); timestamps + softDeletes; comentários
- [ ] T013 [P] Criar database/migrations/2026_10_15_000007_create_clientes_inscricoes_notificacoes_table.php (seção 3): `clientes_id` foreignId → `clientes`; `endpoint` text; `endpoint_hash` char(64) **único**; `chave_p256dh`, `chave_auth` string(255); `codificacao` string(20); `aparelho` string(255) nulo; os 5 booleans `receber_*` padrão **verdadeiro**; `ultimo_envio_em` timestamp nulo; índice `clientes_id`; timestamps + softDeletes; comentários
- [ ] T014 [P] Criar database/migrations/2026_10_15_000008_create_clientes_notificacoes_table.php (seção 4, depois da T012): `clientes_id` foreignId → `clientes`; `tipo` string(30); `chave` string(80); `confrontos_eventos_id` foreignId → `confrontos_eventos` nulo; `apostas_id` foreignId → `apostas` nulo; `titulo` string(120); `corpo` string(255) nulo; `url` string(255); `aparelhos` e `falhas` unsignedSmallInteger padrão 0; único (`clientes_id`, `chave`); timestamps + softDeletes; comentários
- [ ] T015 Rodar `php artisan migrate` e conferir `SHOW FULL COLUMNS` das 8 tabelas afetadas (coluna `Comment` preenchida em toda coluna nova ou alterada)

### Enums (data-model, seção 7)

- [ ] T016 [P] Criar app/Enums/ModalidadeEncerramento.php: `Total = 'Total'`, `Parcial = 'Parcial'`, `Automático = 'Automático'`
- [ ] T017 [P] Criar app/Enums/SituacaoEncerramento.php: `EmAnálise = 'Em análise'`, `Recusado`, `Registrado`, `Creditado`, `Desfeito`; método `vale()` (true para `Registrado` e `Creditado`)
- [ ] T018 [P] Criar app/Enums/SituacaoEncerramentoAutomatico.php: `Ativo`, `Executado`, `Cancelado`
- [ ] T019 [P] Criar app/Enums/TipoEventoConfronto.php: `Gol = 'Gol'`, `GolAnulado = 'Gol anulado'`, `Intervalo = 'Intervalo'`, `FimDeJogo = 'Fim de jogo'`
- [ ] T020 [P] Criar app/Enums/TipoNotificacao.php: `Gol`, `GolAnulado = 'Gol anulado'`, `Intervalo`, `FimDeJogo = 'Fim de jogo'`, `ResultadoAposta = 'Resultado da aposta'`, `EncerramentoAutomático = 'Encerramento automático'`; método `coluna_preferencia()` (Gol e GolAnulado → `receber_gols`, Intervalo → `receber_intervalo`, FimDeJogo → `receber_fim_jogo`, ResultadoAposta → `receber_resultado_aposta`, EncerramentoAutomático → `receber_encerramento_automatico`) e `do_evento(TipoEventoConfronto)`
- [ ] T021 [P] ⚠️ Em app/Enums/ResultadoAposta.php: caso `Encerrada = 'Encerrada'`; atualizar o comentário da classe (só a aposta usa Encerrada, spec 010)
- [ ] T022 [P] ⚠️ Em app/Enums/OrigemTransacao.php: caso `EncerramentoAposta = 'Encerramento de aposta'` com comentário "(spec 010)"
- [ ] T023 [P] ⚠️ Em app/Enums/TipoOperacaoApuracao.php: caso `EncerramentoDesfeito = 'Encerramento desfeito'`

### Models

- [ ] T024 [P] Criar app/Models/ApostasEncerramentos.php: `SoftDeletes`, `$guarded = ['id']` (gravado só pelos serviços); casts dos enums (`modalidade`, `situacao`, `aceitar_alteracoes`), decimais (`decimal:2`, `decimal:10` em `proporcao`, `fracao_antes`, `fracao_depois`), `palpites` e `rollovers` `array`, timestamps de decisão, crédito e desfazimento como `datetime`; relações `aposta()`, `cliente()` (`withTrashed`), `usuario()` (`withTrashed`), `automatico()`, `transacao()` e `transacao_estorno()`
- [ ] T025 [P] Criar app/Models/ApostasEncerramentosAutomaticos.php: `SoftDeletes`, `$guarded = ['id']`, casts (`situacao`, `valor_alvo` decimal:2, datas), relações `aposta()`, `cliente()`, `usuario()`, scope `ativos()`
- [ ] T026 [P] Criar app/Models/ClientesInscricoesNotificacoes.php: `SoftDeletes`, `$guarded = ['id']`, casts dos 5 `receber_*` boolean e `ultimo_envio_em`; `$hidden` com `chave_p256dh` e `chave_auth`; relação `cliente()`; método estático `hash_endpoint(string)` (SHA-256)
- [ ] T027 [P] Criar app/Models/ClientesNotificacoes.php: `SoftDeletes`, `$guarded = ['id']`, cast `tipo` → `TipoNotificacao`; relações `cliente()`, `evento()`, `aposta()`
- [ ] T028 [P] Criar app/Models/ConfrontosEventos.php: `SoftDeletes`, `$guarded = ['id']`, cast `tipo` → `TipoEventoConfronto`, `notificado_em` datetime; relações `confronto()` (`withTrashed`) e `confronto_ao_vivo()`
- [ ] T029 ⚠️ Em app/Models/Apostas.php: cast `fracao_restante => 'decimal:10'`; `total_a_pagar()` passa a `truncar((premio + valor_acrescido) × fracao_restante, 2)` com `bcmul` em escala 2 (R-07, comentário explicando o parcial); método novo `valor_em_jogo()` = `bcmul(valor, fracao_restante, 2)`; relações `encerramentos()` (HasMany `ApostasEncerramentos`) e `encerramento_automatico()` (HasOne do `Ativo`)
- [ ] T030 [P] ⚠️ Em app/Models/Configuracoes.php: as 10 colunas novas no `fillable` (bloco "encerramento de aposta (spec 010)") e nos casts (booleans, `decimal:2` e `integer`)
- [ ] T031 [P] ⚠️ Em app/Models/ClientesConfiguracoes.php: `encerrar_aposta` em `CAMPOS` e `'encerrar_aposta' => 'boolean'` em `CASTS`

### Serviços e eventos compartilhados

- [ ] T032 Criar app/Services/ConfiguracoesEncerramento.php: lê `Configuracoes::atual()` uma vez por instância e expõe `habilitado()`, `parcial_habilitado()`, `automatico_habilitado()`, `jogos_nao_iniciados()`, `margem(bool $ao_vivo)`, `valor_minimo()`, `percentual_minimo()`, `segundos_validade()`, `limite_parciais()`, `minutos_janela()` (`minutos_pagamento_premio`) e `cliente_pode(Clientes)` (`encerrar_aposta` da configuração do cliente, criando-a como o `Apostador::cliente`)
- [ ] T033 Criar app/Services/CalculoEncerramento.php só com `cotacoes_atuais(Apostador $apostador, Apostas $aposta, Collection $palpites): array`. Monta os pedidos de cada palpite **em aberto** pela tabela da R-04: não iniciado → `confrontos_id`; iniciado com linha em `confrontos_ao_vivo` (por `confrontos_id`) → `confrontos_ao_vivo_id`; jogador depois do início, especial em aberto, ou iniciado sem ao vivo → sem cotação. Chama `CriacaoApostas::montar` (sem alterá-lo) com `cotacao_vista` = `cotacao_final`. Devolve, por id do palpite, `cotacao_atual` (texto 2 casas ou nulo), `ao_vivo` (bool), `travado`, `jogo_ao_vivo` (objeto da linha ou nulo), `nome_jogo` e `sem_cobertura`. Palpites decididos ficam fora. Consultas em lote (sem N+1)
- [ ] T034 [P] Criar app/Events/ConfrontoAtualizado.php: `ShouldBroadcastNow`, canal público `confrontos.{confrontos_id}`, `broadcastAs()` = `confronto.atualizado`, `broadcastWith()` com o payload de contracts/tempo_real.md (gols por tempo e escanteios nulos fora do futebol)
- [ ] T035 [P] Criar app/Events/ApostaAtualizada.php: `ShouldBroadcastNow`, canal público `apostas.{codigo}`, `broadcastAs()` = `aposta.atualizada`, payload `{codigo, motivo, enviado_em}`; método estático `emitir(string $codigo, string $motivo)` que faz o `broadcast` dentro de `try/catch` com `Log::warning` (usado por todos os pontos de emissão)

**Checkpoint**: base pronta; as histórias podem começar.

---

## Phase 3: User Story 1 - Acompanhar o bilhete em tempo real (Priority: P1) 🎯 MVP

**Goal**: página pública `/bilhete/{codigo}` com o visual aprovado, atualizada continuamente pelo
Reverb, com consulta periódica quando a conexão cai, e acesso pelo cupom e pelo extrato.

**Independent Test**: quickstart cenários 1 a 13. Abrir o bilhete sem login com jogo ao vivo e ver
placar, minuto e selos mudarem sozinhos; abrir uma aposta decidida e ver o resultado final sem
novas requisições.

### Backend

- [ ] T036 [P] [US1] ⚠️ Em app/Support/PlacarConfronto.php: construtor estático `do_ao_vivo(object $jogo)` com placar, gols por tempo e escanteios da linha do ao vivo e autores vazios; no `1 tempo`, gols do 2º tempo nulos viram 0 (R-06)
- [ ] T037 [US1] Criar app/Services/BilheteTempoReal.php com `montar(Apostas $aposta, ?Clientes $visitante_logado, ?string $oferta_anterior, CarbonTimeZone $fuso): array`, no formato de `data` do `GET /publico/bilhetes/{codigo}` (contracts/api.md, seção 1). Regras:
  - `dono`: o cliente logado é o `clientes_id` da aposta;
  - `cliente`: nome abreviado ("José W.") ou completo para o dono;
  - `final` e `canais` (só jogos em aberto, vazio quando final);
  - palpites com `estado`, placar (resultado gravado ou ao vivo, como o `ComprovanteApostaResource`), `minutos_para_inicio` e `cotacao_atual` por `CalculoEncerramento::cotacoes_atuais` (apostador do dono ou `Apostador::visitante()`);
  - `selo` por `ResultadosCotacao::avaliar` sobre `PlacarConfronto::do_ao_vivo` (jogador → "Aguardando");
  - blocos agrupados com a situação;
  - `progresso` contando bloco como um;
  - `valor_em_jogo` e `total_a_pagar` com a fração;
  - `encerramento` = null nesta fase (preenchido na US2);
  - consultas em lote, sem N+1.
- [ ] T038 [US1] Criar app/Http/Resources/BilheteTempoRealResource.php envolvendo o array de `BilheteTempoReal::montar` (datas no fuso do pedido, como o comprovante)
- [ ] T039 [US1] Criar app/Http/Controllers/PublicoBilhetesController.php, método `show(Request, string $codigo)`:
  - limite de 120 por minuto por IP (trait `RespostasApostas::limitar_tentativas`);
  - aposta por `Apostas::buscar_pelo_codigo`;
  - 404 "Bilhete não encontrado ou ainda não validado." para inexistente, `Pendente`, `Em análise`, `Recusada` e `Expirada` (FR-011);
  - cliente logado por `IdentificacaoPublico`; se o token tiver `acesso_assistido_id` de um acesso que não está mais ativo (`ClientesAcessosAssistidos::ativo()`, mesma regra do `GarantirAcessoCliente`), tratar como visitante (sem `dono`);
  - query `oferta_anterior` opcional.
- [ ] T040 [US1] ⚠️ Em routes/api.php: `GET publico/bilhetes/{codigo}` junto das rotas públicas de apostas, com comentário "(spec 010)" e o `use`; regenerar ⚠️ docs/postman/wssports_api.postman_collection.json com a rota
- [ ] T041 [P] [US1] Criar app/Http/Controllers/BilheteController.php: `index(string $codigo)` renderiza a página Inertia `Ticket` com `codigo` (normalizado por `CodigoAposta::normalizar`) e `tempo_real` = `{chave, host, porta, esquema}` lidos de `config('broadcasting.connections.reverb')` a cada carga (constituição: configuração vem do servidor)
- [ ] T042 [US1] ⚠️ Em routes/web.php: `Route::get('bilhete/{codigo}', [BilheteController::class, 'index'])->name('bilhete')`, com comentário "(spec 010)" e o `use`
- [ ] T043 [US1] Criar app/Services/ProcessamentoAoVivo.php com `processar(array $antes, array $depois): void`. Para cada jogo com `confrontos_id`, compara o anterior (por `codigo_externo`) com o gravado e emite `ConfrontoAtualizado` quando mudou placar, situação, minuto, gols por tempo, escanteios ou o `md5` das cotações (`gol` = placar mudou; `cotacoes_alteradas` = hash mudou). Cada emissão em `try/catch` com `Log::warning`. Deixar pontos de extensão comentados para eventos (US3) e automáticos (US2)
- [ ] T044 [US1] ⚠️ Em app/Services/ImportacaoAoVivo.php: antes do `upsert`, ler de `confrontos_ao_vivo` (uma consulta por `whereIn codigo_externo`) `codigo_externo`, `confrontos_id`, placares, gols por tempo, escanteios, `minuto`, `situacao` e `cotacoes`. Depois do `DB::transaction`, chamar `ProcessamentoAoVivo::processar($anteriores, $linhas)` dentro de `try/catch` (`Throwable` → `Log::warning`, a carga continua com sucesso). Injetar o serviço no construtor; nenhuma outra mudança
- [ ] T045 [US1] ⚠️ Em app/Services/ImportacaoResultados.php (`gravar_resultado`): quando o jogo passa a apurável (`! $apuravel_antes && $novo->apuravel()`), depois da transação, emitir `ConfrontoAtualizado` com o placar final e `encerrado = true` (try/catch com warning)
- [ ] T046 [US1] ⚠️ Em app/Services/ImportacaoCotacoes.php (`importar`), R-22: depois de gravar, uma consulta pelos `confrontos_id` da carga que têm palpite `Ativo` em aposta `Ativa` e `Aguardando`; para cada um, emitir `ConfrontoAtualizado` com `cotacoes_alteradas = true`, `gol = false` e o placar do último ao vivo conhecido (nulo antes do início), cada emissão em try/catch com `Log::warning`; deixar o ponto de extensão para os automáticos (US2); nenhuma outra mudança
- [ ] T047 [US1] ⚠️ Em app/Services/ApuracaoApostas.php (`apurar_aposta`): quando `$alterou` for verdadeiro, depois do `DB::transaction`, chamar `ApostaAtualizada::emitir($codigo, 'apuracao')`

### Frontend

- [ ] T048 [P] [US1] Criar resources/js/utils/realtime.js: `criar_conexao(tempo_real)` devolve uma instância `Echo` (`broadcaster: 'reverb'`, `Pusher` do `pusher-js`, `wsHost`, `wsPort`, `wssPort`, `forceTLS` pelo `esquema`, `enabledTransports: ['ws', 'wss']`) e `estado_conexao(echo, ao_mudar)`, que ouve o `state_change` do pusher e devolve a função de cancelar
- [ ] T049 [US1] Criar resources/js/hooks/useBilheteTempoReal.js (R-02):
  - carrega `GET /publico/bilhetes/{codigo}` pelo `api`;
  - assina `bilhete.canais`: `confronto.atualizado` aplica placar, minuto e período na hora, marca `gol` para o destaque e agenda uma nova busca agrupada (≤ 1 a cada 2 s, cancelando a anterior); `aposta.atualizada` busca na hora;
  - com a conexão de pé, verificação a cada 60 s;
  - sem conexão, consulta a cada 10 s (jogo ao vivo) ou 60 s; ao reconectar, uma busca;
  - com `final`, sai dos canais e para os timers;
  - expõe `bilhete`, `carregando`, `erro` (404 → não encontrado), `conexao` (`ao_vivo` | `reconectando`), `atualizado_em`, `recarregar`;
  - limpa tudo ao desmontar.
- [ ] T050 [P] [US1] Criar resources/js/components/ConnectionStatus/index.jsx e styles.jsx: indicador "Ao vivo" (ponto pulsando) ou "Reconectando…" e "atualizado há X s" (contador de 1 s), `aria-live="polite"`, sem animação com `prefers-reduced-motion`
- [ ] T051 [P] [US1] Criar resources/js/components/TicketProgress/index.jsx e styles.jsx: barra com um segmento por palpite ou bloco (decidido, ganhando, perdendo, não iniciado) e legenda em texto com a contagem de cada estado (FR-002); cores de estado próprias (verde, vermelho, amarelo, cinza), distintas de `tema.principal`, legíveis nos dois modos
- [ ] T052 [P] [US1] Criar resources/js/components/TicketSummary/index.jsx e styles.jsx (FR-002, FR-012):
  - prêmio potencial em destaque;
  - situação/resultado;
  - cliente, horário, valor em jogo e cotação total, em números de largura fixa (`font-variant-numeric: tabular-nums`);
  - `TicketProgress` e o `ConnectionStatus` recebido por prop;
  - cores do tema (`principal`, `derivada`, paleta do modo).
- [ ] T053 [P] [US1] Criar resources/js/components/LiveHunchCard/index.jsx e styles.jsx (FR-003 a FR-006, FR-014):
  - campeonato, horário, escudos (com fallback), times, mercado e cotação original com a atual;
  - placar em destaque com período e minuto, gols do 1º e 2º tempo e escanteios (só futebol), ou "começa em X min";
  - selo "Ganhando agora", "Perdendo agora" ou "Aguardando", ou o resultado (Vencedor, Perdedor, Devolvido, Cancelado);
  - destaque passageiro do placar com `$gol`, desligado com `prefers-reduced-motion`;
  - anúncio do gol em região `aria-live`.
- [ ] T054 [P] [US1] Criar resources/js/components/LiveBlockCard/index.jsx e styles.jsx: bloco Criar Aposta agrupado pelo jogo (FR-007), com a lista de mercados e cotações, a cotação do bloco, a situação do bloco e o mesmo cabeçalho de placar do `LiveHunchCard`
- [ ] T055 [US1] Criar resources/js/pages/Ticket/index.jsx e styles.jsx (FR-012, FR-013):
  - página Inertia com `codigo` e `tempo_real` e `<Head>` com o nome do sistema;
  - usa `useBilheteTempoReal`, `TicketSummary`, `LiveHunchCard` e `LiveBlockCard` (agrupar com `agrupar_palpites_comprovante` de utils/bet_builder.js);
  - não encontrado → "Bilhete não encontrado ou ainda não validado", com botão para a tela principal;
  - mobile (`tema.telas.mobile`): uma coluna com resumo, área reservada ao encerramento e palpites;
  - desktop: palpites à esquerda e coluna direita `position: sticky` com resumo e área do encerramento;
  - sem rolagem horizontal de 360 px ao desktop; foco visível.
- [ ] T056 [US1] ⚠️ Em resources/js/hooks/useSessao.js (`aplicar_token`): o token do acesso assistido vale em `/minha-conta` **e** em caminhos que começam com `/bilhete/` (R-03); nenhuma outra mudança nesta tarefa
- [ ] T057 [US1] ⚠️ Em resources/js/components/TicketModal/index.jsx (e styles.jsx se precisar de estilo): no `Icons` do topo, antes do `share`, ícone `live_tv` com o título "Clique para acompanhar o bilhete em tempo real", que faz `router.visit('/bilhete/' + codigo)`; `compartilhar` passa a enviar `*ACOMPANHE SEU BILHETE:* \n\n {origin}/bilhete/{codigo}` (FR-015)
- [ ] T058 [US1] ⚠️ Em resources/js/pages/Account/index.jsx: tocar numa operação de aposta faz `router.visit('/bilhete/' + item.aposta.codigo)` no lugar de buscar o comprovante e abrir o `TicketModal`. Remover o estado `bilhete`, a busca e o `TicketModal` só dessa tela (FR-015); o toque no saque recusado continua igual

**Checkpoint**: US1 funcionando sozinha (quickstart 1 a 13).

---

## Phase 4: User Story 2 - Encerrar a aposta antes do fim (Priority: P2)

**Goal**: o dono vê a oferta e encerra total, parcial ou automático; o valor é creditado depois da
janela; o painel configura e lista; as Regras explicam.

**Independent Test**: quickstart cenários 14 a 36. Com C1 logado e aposta elegível, conferir a
oferta à mão, encerrar (total), ver `Encerrada` e o crédito depois da janela; repetir com parcial e
automático.

### Regras e cálculo

- [ ] T059 [P] [US2] ⚠️ Em app/Services/RolloverClientes.php (FR-038, R-10):
  - método `retirar(Apostas $aposta, string $proporcao): array`: para cada `ApostasRollovers` da aposta sem `desfeito_em` (rollover com `lockForUpdate`), retira `truncar(valor_da_ligacao × proporcao, 2)` do `valor_apostado` do rollover e da ligação, zera `cumprido_em` se ficar abaixo do exigido, e devolve `[['clientes_rollovers_id' => …, 'valor' => '…'], …]`;
  - método `devolver(array $retiradas, int $apostas_id)`: soma de volta no rollover e na ligação e marca `cumprido_em` se atingir o exigido;
  - não muda `somar` nem `desfazer`.
- [ ] T060 [P] [US2] Criar app/Support/TokenOferta.php: `assinar(array $dados): string` (base64url do JSON + HMAC-SHA256 com a `app.key`) e `conferir(string $token): ?array` (`hash_equals`; nulo se adulterado ou vencido pelo `expira_em`) (R-09)
- [ ] T061 [US2] Criar app/Services/ElegibilidadeEncerramento.php com `avaliar(Apostas $aposta, Collection $palpites, array $cotacoes): array{elegivel: bool, motivo: ?string}`, os 11 critérios da R-05 na ordem e com os textos exatos. Inclui o critério 8 (resultado `Encerrado` gravado em `confrontos_resultados` de jogo em aberto) e o 9 (`ConfrontosAoVivo::travado`). Usa `ConfiguracoesEncerramento`
- [ ] T062 [US2] Em app/Services/CalculoEncerramento.php (arquivo da T033) acrescentar:
  - `oferta(Apostas $aposta, ?Apostador $apostador = null): array` com `disponivel`, `motivo`, `oferta`, `minimo`, `margem`, `ao_vivo` e a `fotografia` dos palpites em aberto (id, `cotacao_aposta` = `apostas_palpites.cotacao_final` — a cotação aceita na aposta, que a spec chama de "cotação original"; **não** usar a coluna `cotacao_original`, que é a do provedor —, cotacao_atual, placar, período, minuto, ultima_atualizacao_em, `jogo_com_resultado`);
  - fórmula da R-04 em bcmath: P = `premio × fracao_restante`; C = produto das atuais; margem pré-jogo ou ao vivo; truncar; limitar a P; mínimo = maior entre valor mínimo e `valor_em_jogo × percentual ÷ 100`;
  - `parcial_disponivel(Apostas)`: ligado e quantidade de parciais `vale()` < limite (motivo "Limite de encerramentos parciais atingido.").
- [ ] T063 [US2] Criar app/Services/EncerramentoApostas.php (R-09 a R-11) com:
  - `pedir(Clientes $cliente, ?Usuarios $autor, string $codigo, array $dados, ?string $ip, ?string $user_agent): ApostasEncerramentos`:
    - idempotência por `chave_idempotencia` (reenvio devolve o registro; chave de outra aposta → 422);
    - aposta do cliente (404 senão);
    - token válido (senão exceção de oferta alterada com o bloco atual); token de outra aposta ou com `fracao_restante` diferente da atual da aposta → também oferta alterada (409);
    - modalidade Parcial só com o parcial disponível;
    - com jogo ao vivo em aberto, grava `Em análise` e despacha `DecidirEncerramento` com `delay(Apostas::delay_ao_vivo())` e `afterCommit`;
    - sem jogo ao vivo, decide na hora;
  - `decidir(ApostasEncerramentos $encerramento)`:
    - transação com a aposta travada; refaz elegibilidade e oferta;
    - placar ou período diferente do token → `Recusado` "Houve lance em {jogo} durante a confirmação.";
    - preferência (`Nenhuma` igual, `Somente para maior` ≥, `Qualquer` qualquer) sobre a oferta → `Recusado` "A oferta mudou.";
    - parcial exige `valor_pedido` < nova oferta e ≥ mínimo;
    - aceito: aplica os efeitos;
  - `aplicar(...)` (privado), com os efeitos da tabela da R-10:
    - Total: `resultado = Encerrada` e `fracao_restante = 0`;
    - Parcial: `fracao × (1 − r ÷ O')` em escala 10;
    - `RolloverClientes::retirar` com a proporção, gravando `rollovers`;
    - cancela o automático ativo ("Encerramento manual");
    - grava os campos antes/depois, `margem`, `palpites`, `decidido_em` e `creditar_em` = agora + `minutos_janela`;
    - `Registrado`;
  - `executar_automatico(ApostasEncerramentosAutomaticos, array $oferta)`: Total com modalidade `Automático`, sem autor nem IP, sem delay;
  - `desfazer(ApostasEncerramentos, OrigemApuracao $origem, string $motivo)` (dentro da transação de quem chama, com a aposta travada):
    - `Registrado` → `Desfeito` sem crédito;
    - `Creditado` → débito `OrigemTransacao::Estorno` com `permitir_negativo`;
    - `RolloverClientes::devolver`;
    - `fracao_restante` volta a `fracao_antes`;
    - registro `TipoOperacaoApuracao::EncerramentoDesfeito` por `RegistroApuracao`, com a `$origem` recebida;
  - depois de cada commit, `ApostaAtualizada::emitir($codigo, 'encerramento')`.
- [ ] T064 [P] [US2] Criar app/Jobs/DecidirEncerramento.php: fila `apostas` (constante `FILA`, como `DecidirApostaAoVivo`), `$tries = 1`, `handle(EncerramentoApostas)` → `decidir` só se ainda `Em análise`
- [ ] T065 [US2] ⚠️ Em app/Console/Commands/RecusarAnalisesPresasCommand.php: também marca `Recusado` ("Tempo de confirmação esgotado.") os encerramentos `Em análise` criados há mais que o delay do cliente + 60 s, sem mover nada, e soma no resumo do comando

### Crédito, automático e apuração

- [ ] T066 [US2] Criar app/Services/LiquidacaoEncerramentos.php: `liquidar(): array{creditados, falhas}`. Busca `Registrado` com `creditar_em <= now()` em lotes; para cada um, transação com a aposta e o encerramento travados, confere de novo a situação, `SaldoClientes::creditar(cliente, Carteira::Saldo, valor, OrigemTransacao::EncerramentoAposta, null, apostas_id, "Encerramento da aposta {codigo}")`, grava `clientes_transacoes_id`, `creditado_em` e `Creditado`; erro → log e segue; `ApostaAtualizada` depois do commit
- [ ] T067 [P] [US2] Criar app/Console/Commands/LiquidarEncerramentosCommand.php (`apostas:liquidar_encerramentos`, descrição em português, resumo "Encerramentos: X creditados, Y falhas.")
- [ ] T068 [US2] Criar app/Services/EncerramentosAutomaticos.php (R-12):
  - `programar(Clientes, ?Usuarios, string $codigo, string $valor_alvo)`: automático ligado, aposta elegível, alvo > oferta atual (422 "O valor-alvo deve ser maior que a oferta atual de R$ X.") e ≤ prêmio potencial; substitui o ativo ("Alterado");
  - `cancelar(Clientes, string $codigo, string $motivo)`;
  - `cancelar_da_aposta(Apostas, string $motivo)` (uso interno, sem trava própria);
  - `conferir(?array $confrontos_ids = null): int`: ativos, filtrando as apostas com palpite nesses jogos quando informados; calcula a oferta e, se ≥ alvo, chama `EncerramentoApostas::executar_automatico`; inelegível no momento → segue aguardando; erro por aposta → log.
- [ ] T069 [P] [US2] Criar app/Jobs/ConferirEncerramentosAutomaticos.php: fila `apostas`, `$tries = 1`, recebe `confrontos_ids`, chama `EncerramentosAutomaticos::conferir`
- [ ] T070 [P] [US2] Criar app/Console/Commands/ConferirEncerramentosAutomaticosCommand.php (`apostas:conferir_encerramentos_automaticos`; chama `conferir()` sem filtro; resumo com a quantidade executada)
- [ ] T071 [US2] Em app/Services/ProcessamentoAoVivo.php (arquivo da T043): despachar `ConferirEncerramentosAutomaticos` com os `confrontos_id` cujas cotações, placar ou situação mudaram, só se houver automático `Ativo` (consulta `exists`)
- [ ] T072 [US2] ⚠️ Em app/Services/ImportacaoCotacoes.php (arquivo da T046): despachar `ConferirEncerramentosAutomaticos` com os `confrontos_id` com aposta em aberto já levantados, só se houver automático `Ativo` (consulta `exists`), em try/catch (R-22)
- [ ] T073 [US2] ⚠️ Em routes/console.php: agendar `apostas:liquidar_encerramentos` (`everyMinute()->withoutOverlapping(5)`) e `apostas:conferir_encerramentos_automaticos` (`everyMinute()->withoutOverlapping(2)`), com os comentários de contracts/comandos.md
- [ ] T074 [US2] ⚠️ Em app/Services/ApuracaoApostas.php (R-08, R-11, R-12):
  - `decidir` mantém `resultado = Encerrada` quando já for `Encerrada`, só atualizando `liquidacao_pendente`;
  - em `apurar_aposta`, depois de reavaliar os palpites e dentro da mesma transação, para cada encerramento `vale()` da aposta, chama `EncerramentoApostas::desfazer($encerramento, $origem, …)` (a `OrigemApuracao` do `apurar_aposta`) se um palpite que estava em aberto na fotografia está `Perdedor` e o jogo dele já estava decidido no momento (registro `Resultado do jogo` em `operacoes_apuracao` com `depois->situacao = Encerrado` anterior ao `decidido_em`, ou `jogo_com_resultado`, ou ao vivo travado na fotografia). Motivo "Jogo já decidido antes do encerramento". Na aposta encerrada (total) desfeita, o resultado volta a ser decidido pelos palpites;
  - quando a aposta passa a `Vencedor` ou `Perdedor`, cancela o automático ativo ("Aposta decidida") por `EncerramentosAutomaticos::cancelar_da_aposta`.

- [ ] T075 [US2] ⚠️ Em app/Services/CancelamentoApostas.php (`devolver`), R-21: no ramo do cliente, creditar `$aposta->valor_em_jogo()` no lugar de `(string) $aposta->valor` (comentário: a parte encerrada já é paga pelo encerramento, spec 010); se o valor em jogo for zero, não creditar; `RolloverClientes::desfazer` continua igual (as ligações já foram reduzidas pelo parcial); o ramo do vendedor não muda

### API, painel e configurações

- [ ] T076 [P] [US2] Criar app/Http/Requests/EncerrarApostaRequest.php: `modalidade` required `in:Total,Parcial`; `valor` required numeric > 0 com 2 casas; `token_oferta` required string; `chave_idempotencia` required uuid; `aceitar_alteracoes` nullable `Rule::enum(AceitarAlteracoes)`; mensagens em português
- [ ] T077 [P] [US2] Criar app/Http/Requests/EncerramentoAutomaticoRequest.php: `valor_alvo` required numeric > 0, 2 casas; mensagens em português
- [ ] T078 [P] [US2] Criar app/Http/Resources/EncerramentoResource.php: formato "Encerramento" de contracts/api.md (`chave`, `modalidade`, `situacao`, `valor`, `oferta`, `valor_apostado_depois`, `premio_depois`, `creditar_em`, `motivo_recusa`, `segundos_restantes` só em `Em análise`)
- [ ] T079 [US2] Criar app/Http/Controllers/AreaClienteEncerramentosController.php:
  - `store`: limite de 10 por minuto por cliente; autor = `AcessoAssistido::autor($request)`;
  - respostas 201 (`Registrado`), 202 (`Em análise`), 200 (reenvio), 409 `{message: "A oferta mudou.", encerramento: bloco atual}` e 422 (motivo);
  - `show(codigo, chave)`: 404 se a chave não for desta aposta do cliente.
- [ ] T080 [US2] Criar app/Http/Controllers/AreaClienteEncerramentoAutomaticoController.php: `update` (PUT, 200 com `{valor_alvo, situacao, criado_em}`, 422 com o motivo) e `destroy` (204; 404 "Nenhum encerramento automático ativo." sem ativo), com o autor do acesso assistido
- [ ] T081 [US2] Em app/Services/BilheteTempoReal.php (arquivo da T037): preencher `encerramento` só para o dono e só com a aposta `Ativa`/`Aguardando`, no formato de contracts/api.md:
  - `disponivel`, `motivo`, `oferta`, `variacao` (comparando com `oferta_anterior`), `minimo`, `margem`, `ao_vivo`;
  - `token_oferta` (`TokenOferta::assinar` com a fotografia e `expira_em` = agora + validade), `expira_em`, `segundos_validade`, `minutos_credito`;
  - `parcial` (com `restantes`), `automatico` (com o ativo), `em_analise` (chave e segundos restantes) e `registrados`.
- [ ] T082 [P] [US2] Criar app/Http/Requests/ConfiguracoesEncerramentoRequest.php: as 10 chaves `sometimes` com as faixas da R-18 (margens 0 a 50; valor mínimo 0,01 a 10000; percentual 0 a 100; validade 3 a 60; limite 1 a 50; booleans) e mensagens como "A margem do pré-jogo deve ser de 0 a 50%."
- [ ] T083 [P] [US2] Criar app/Http/Resources/ConfiguracoesEncerramentoResource.php (as 10 chaves, decimais como texto)
- [ ] T084 [US2] Criar app/Http/Controllers/ConfiguracoesEncerramentoController.php no padrão de `ConfiguracoesApuracaoController` (`GarantirPermissaoCliente`, `configuracoes.editar`, `show` e `update` com `validated()`)
- [ ] T085 [P] [US2] ⚠️ Em app/Http/Requests/ClientesConfiguracoesRequest.php: `'encerrar_aposta' => ['sometimes', 'boolean']` e a mensagem em português (o `ClientesConfiguracoesResource` já devolve pelos `CAMPOS`)
- [ ] T086 [P] [US2] ⚠️ Em app/Enums/Funcao.php: constante `PERMISSOES_ENCERRAMENTOS = ['apostas_encerramentos.listar']` com comentário; incluir em `permissoes_padrao` (filtrada por `pode_usar`) e na regra "só Admin e Supervisor" do `pode_usar`, junto de especiais, site e pagamentos (R-19)
- [ ] T087 [US2] Criar database/seeders/EncerramentoSeeder.php no padrão do `ApuracaoSeeder` (cria `apostas_encerramentos.listar` no guard `api` e distribui aos usuários pela `permissoes_padrao` da função, sem tirar nenhuma) e ⚠️ chamar em database/seeders/DatabaseSeeder.php depois do `ApuracaoSeeder`; rodar `php artisan db:seed --class=EncerramentoSeeder`
- [ ] T088 [P] [US2] Criar app/Http/Requests/ListagemEncerramentosRequest.php: `data_inicial`, `data_final` `date_format:Y-m-d` (padrão hoje no fuso do sistema), `clientes_id` integer existente, `modalidade` e `situacao` pelos enums, `pagina` ≥ 1, `por_pagina` 1 a 100 (padrão 20)
- [ ] T089 [P] [US2] Criar app/Http/Resources/ApostasEncerramentosResource.php: item de `GET /apostas-encerramentos` (contracts/api.md), com `autor` ("Cliente", "Sistema" ou nome do usuário) e `teria_pago` (prêmio + acréscimo cheios se todos os palpites ativos estão `Vencedor`; 0 se algum `Perdedor`; nulo em aberto)
- [ ] T090 [US2] Criar app/Http/Controllers/ApostasEncerramentosController.php: `index` com middleware `permission:apostas_encerramentos.listar` (padrão dos controllers do painel), filtros, `with` de aposta, cliente e usuário, ordem `created_at desc`, paginação ≤ 100 e `totais` (`valor` e `teria_pago`) da consulta filtrada
- [ ] T091 [US2] ⚠️ Em routes/api.php (com comentários "(spec 010)" e os `use`):
  - área do cliente autenticada: `POST apostas/{codigo}/encerramentos` com `throttle:10,1`, `GET apostas/{codigo}/encerramentos/{chave}`, `PUT` e `DELETE apostas/{codigo}/encerramento-automatico`;
  - grupo de configurações do painel: `GET` e `PUT configuracoes/encerramento` depois de `configuracoes/apuracao`;
  - grupo de apostas do painel: `GET apostas-encerramentos`;
  - regenerar ⚠️ docs/postman/wssports_api.postman_collection.json com as 7 rotas e o campo `encerrar_aposta` em `PUT clientes/{cliente}/configuracoes`.
- [ ] T092 [US2] ⚠️ Em app/Http/Controllers/AreaClienteMinhaContaController.php: `OrigemTransacao::EncerramentoAposta` no filtro `palpites` e nas duas listas que ligam a transação à aposta (linhas com `[OrigemTransacao::Aposta, OrigemTransacao::Prêmio]`)

### Regras do site

- [ ] T093 [US2] ⚠️ Em app/Http/Controllers/RegrasController.php: prop `encerramento` (nulo quando desligado) com `parcial`, `automatico`, `jogos_nao_iniciados`, `margem_pre_jogo`, `margem_ao_vivo`, `valor_minimo`, `percentual_minimo`, `segundos_validade`, `limite_parciais`, `minutos_janela` (R-20)
- [ ] T094 [P] [US2] Criar resources/js/components/CashOutRules/index.jsx e styles.jsx: seção "Encerramento de aposta" com os itens do FR-039 (quem pode, critérios, como o valor é calculado, margens, mínimo, validade, crédito após a janela, quando pode ser desfeito), montada com os valores da prop e no estilo de `RulesBlock`/`RuleItem`
- [ ] T095 [US2] ⚠️ Em resources/js/pages/Rules/index.jsx e resources/js/components/RulesContent/index.jsx: receber e repassar a prop `encerramento`; `RulesContent` mostra `CashOutRules` quando ela não é nula, depois das regras da banca

### Frontend do encerramento

- [ ] T096 [P] [US2] Criar resources/js/hooks/useEncerramento.js:
  - `encerrar({ modalidade, valor })` gera a `chave_idempotencia` (`crypto.randomUUID`) ao abrir a confirmação e envia com o `token_oferta` e a preferência do cupom (`useCupom` ou o padrão);
  - trata 201, 202 (acompanha por `GET …/encerramentos/{chave}` a cada 2 s até sair de `Em análise`), 409 (devolve o bloco novo) e 422;
  - `programar_automatico(valor_alvo)` e `cancelar_automatico()`;
  - chama `recarregar` do bilhete ao final;
  - impede duplo envio (estado `enviando`).
- [ ] T097 [P] [US2] Criar resources/js/components/CashOutConfirm/index.jsx e styles.jsx (FR-024): confirmação dentro da página com o valor a receber, o que deixa de concorrer (total) ou o valor apostado e o prêmio que continuam (parcial), "entra no seu saldo às HH:MM" e a contagem regressiva pelo `expira_em`. Vencida, chama `ao_expirar`. Botões Confirmar/Cancelar; foco preso enquanto aberta
- [ ] T098 [US2] Criar resources/js/components/CashOutPanel/index.jsx e styles.jsx (FR-023, FR-028 a FR-031):
  - oferta em destaque com seta e texto de "subiu"/"caiu", e o prêmio potencial;
  - sem oferta, o `motivo` no lugar;
  - abas Total, Parcial e Automático (indisponíveis com o motivo);
  - Parcial: campo/controle deslizante entre o mínimo e a oferta, com a prévia em centavos (`utils/money.js`) do valor apostado e do prêmio restantes;
  - Automático: campo com atalhos +20%, +50%, +100% e "recuperar o valor apostado", o alvo ativo com "Desativar", e a validação "maior que a oferta atual";
  - "Em análise" com segundos restantes;
  - sucesso "Aposta encerrada", com valor e horário do crédito;
  - usa `CashOutConfirm` e `useEncerramento`.
- [ ] T099 [P] [US2] Criar resources/js/components/CashOutBar/index.jsx e styles.jsx: barra fixa no rodapé só no mobile, com a oferta e o botão "Encerrar aposta" (rola até o `CashOutPanel` e abre a confirmação do Total), respeitando a área segura (`env(safe-area-inset-bottom)`)
- [ ] T100 [US2] Em resources/js/pages/Ticket/index.jsx (arquivo da T055):
  - com `bilhete.encerramento`: `CashOutPanel` na área reservada e `CashOutBar` no mobile;
  - sem login: convite "Entre para encerrar" (abre o login da tela principal com `?entrar=1&voltar=/bilhete/{codigo}`);
  - a busca envia `oferta_anterior`.
- [ ] T101 [P] [US2] ⚠️ Em resources/js/components/StatementTable/index.jsx: ícone `price_check` com a cor do tema para `item.aposta.resultado === 'Encerrada'`
- [ ] T102 [P] [US2] ⚠️ Em resources/js/components/TicketModal/styles.jsx (ou no index.jsx, onde está a cor do `Status`): cor própria para o resultado `Encerrada`

**Checkpoint**: US1 e US2 funcionando (quickstart 14 a 36).

---

## Phase 5: User Story 3 - Receber notificações dos jogos da aposta (Priority: P3)

**Goal**: o cliente logado ativa o push por aparelho e recebe gol, gol anulado, intervalo, fim de
jogo, resultado da aposta e encerramento automático, com liga/desliga por tipo; tocar abre o
bilhete.

**Independent Test**: quickstart cenários 37 a 47. Ativar no aparelho, gol no mock → aviso em até
15 s; desligar "Gols" → sem aviso de gol.

### Backend

- [ ] T103 [P] [US3] Criar app/Console/Commands/GerarChavesPushCommand.php (`notificacoes_push:gerar_chaves`): `Minishlink\WebPush\VAPID::createVapidKeys()`, mostra as linhas `VAPID_PUBLIC_KEY=` e `VAPID_PRIVATE_KEY=` e avisa que trocar as chaves invalida as inscrições; não grava nada
- [ ] T104 [US3] Criar app/Services/NotificacoesPush.php (R-13, contracts/tempo_real.md):
  - `registrar(Clientes|int $cliente, TipoNotificacao $tipo, string $chave, string $titulo, ?string $corpo, string $url, ?int $confrontos_eventos_id, ?int $apostas_id): ?ClientesNotificacoes` cria a linha só se `(clientes_id, chave)` não existir (`insertOrIgnore`/`firstOrCreate`) e devolve nula quando já existia;
  - `despachar(ClientesNotificacoes)`: `EnviarNotificacaoPush` com `afterCommit` na fila `notificacoes`;
  - `enviar(ClientesNotificacoes)`:
    - não faz nada sem chaves VAPID;
    - busca as inscrições do cliente com `coluna_preferencia()` ligada e usa `WebPush::queueNotification` + `flush()`;
    - payload `{titulo, corpo, url, tag, tipo, icone}` com `tag` `confronto-{id}` ou `aposta-{codigo}` e o ícone de 192 px de `DadosFake::icones()`;
    - TTL 600 (jogo) ou 86400 (aposta), `urgency` high nos gols;
    - 404/410 → soft delete da inscrição; sucesso → `ultimo_envio_em`; atualiza `aparelhos` e `falhas`; outras falhas → `Log::warning`.
- [ ] T105 [P] [US3] Criar app/Jobs/EnviarNotificacaoPush.php: fila `notificacoes`, `$tries = 1`, `handle(NotificacoesPush)` → `enviar`
- [ ] T106 [US3] Criar app/Services/EventosConfrontos.php (R-16, R-17):
  - `registrar(int $confrontos_id, ?int $confrontos_ao_vivo_id, TipoEventoConfronto $tipo, int $placar_casa, int $placar_fora, ?int $minuto): ?ConfrontosEventos`, com `chave` `Intervalo:{id}`/`Fim de jogo:{id}` única (já existente → nulo; gols sem chave);
  - `avisar(ConfrontosEventos $evento)`:
    - clientes com aposta `Ativa`, resultado `Aguardando` e palpite **Ativo** no jogo; para cada um, a aposta mais recente nesse jogo para a `url`;
    - textos da tabela da R-17 (times e campeonato do confronto, minuto);
    - `NotificacoesPush::registrar` com `chave` `evento:{id}` e despacho;
    - marca `notificado_em`;
    - erros por cliente → log.
- [ ] T107 [US3] Em app/Services/ProcessamentoAoVivo.php (arquivo da T043): por jogo com `confrontos_id`, detectar placar total maior (`Gol`) ou menor (`GolAnulado`) e situação nova `Intervalo` (`Intervalo`), registrar por `EventosConfrontos::registrar` e chamar `avisar` (gol e intervalo na mesma carga geram dois eventos; erro → warning)
- [ ] T108 [US3] ⚠️ Em app/Services/ImportacaoResultados.php (`gravar_resultado`, no mesmo ponto da T045): registrar `FimDeJogo` com o placar final (sem `confrontos_ao_vivo_id` e sem minuto) e `avisar`, depois da transação e em try/catch
- [ ] T109 [US3] ⚠️ Em app/Services/ApuracaoApostas.php: quando `decidir` muda o resultado de `Aguardando` para `Vencedor` ou `Perdedor` numa aposta de cliente, depois do commit, `NotificacoesPush::registrar` com o tipo `ResultadoAposta`, `chave` `aposta:{id}:{resultado}` e os textos da R-17 (prêmio = `total_a_pagar()`), e despachar
- [ ] T110 [US3] Em app/Services/EncerramentoApostas.php (arquivo da T063): em `executar_automatico`, depois do commit, aviso `EncerramentoAutomático` com `chave` `encerramento:{id}` e o texto "R$ X entram no seu saldo às HH:MM" (horário no fuso do sistema)
- [ ] T111 [US3] Criar app/Services/InscricoesNotificacoes.php: `gravar(Clientes, array $dados, ?string $user_agent)` faz upsert pelo `endpoint_hash` (inclusive com soft delete), passa a inscrição para o cliente atual, na criação liga todos os tipos, aplica `preferencias` e gera `aparelho` legível a partir do user agent (ex.: "Chrome no Android"); `buscar(Clientes, string $endpoint)` e `remover(Clientes, string $endpoint)` (soft delete)
- [ ] T112 [P] [US3] Criar app/Http/Requests/InscricaoNotificacaoRequest.php: `endpoint` required url https máx. 2000; `chaves.p256dh` e `chaves.auth` required na criação (`required_without` da inscrição existente, conferido no serviço) string máx. 255; `codificacao` `in:aes128gcm,aesgcm` (padrão `aes128gcm`); `preferencias.*` boolean nas 5 chaves; mensagens em português
- [ ] T113 [P] [US3] Criar app/Http/Resources/InscricaoNotificacaoResource.php: `aparelho` e os 5 `receber_*` (nunca as chaves)
- [ ] T114 [US3] Criar app/Http/Controllers/PublicoNotificacoesController.php (`configuracao`: `{chave_publica, habilitado}`) e app/Http/Controllers/AreaClienteNotificacoesController.php (`show`, `update` e `destroy` da inscrição, por `endpoint`; `update` com limite de 20 por minuto por cliente)
- [ ] T115 [US3] ⚠️ Em routes/api.php: `GET publico/notificacoes/configuracao` (pública) e, na área do cliente autenticada, `GET`, `PUT` e `DELETE notificacoes/inscricao`, com comentário "(spec 010)" e os `use`; regenerar ⚠️ docs/postman/wssports_api.postman_collection.json com as 4 rotas

### Service worker

- [ ] T116 [P] [US3] Criar resources/pwa/push_notifications.js (JavaScript puro, comentários em português): `push` → `showNotification(titulo, { body: corpo, icon, badge: icone, tag, renotify: true, data: { url } })` dentro de `event.waitUntil`; `notificationclick` → fecha, foca uma janela já em `data.url` (`clients.matchAll({ type: 'window', includeUncontrolled: true })`) ou `clients.openWindow(data.url)`
- [ ] T117 [US3] ⚠️ Em app/Http/Controllers/ArquivosPwaController.php: método `notificacoes()` que serve `resource_path('pwa/push_notifications.js')` com `Content-Type: application/javascript; charset=utf-8` e `Cache-Control: no-cache` (como o `service_worker`); e ⚠️ em routes/web.php: `Route::get('sw-notificacoes.js', [ArquivosPwaController::class, 'notificacoes'])->name('pwa.notificacoes')` junto das rotas do PWA
- [ ] T118 [US3] ⚠️ Em vite.config.js: `workbox.importScripts: ['/sw-notificacoes.js']`, com comentário "(spec 010, R-14)"; nenhuma outra opção muda

### Frontend

- [ ] T119 [P] [US3] Criar resources/js/utils/web_push.js:
  - `suporta_push()`;
  - `e_iphone()` e `aplicativo_instalado()` (`navigator.standalone` ou `matchMedia('(display-mode: standalone)')`);
  - `precisa_instalar()` (iPhone sem o app instalado);
  - `chave_para_bytes(base64url)` (conversão da chave VAPID).
- [ ] T120 [US3] Criar resources/js/hooks/useNotificacoesPush.js (R-15):
  - lê `GET /publico/notificacoes/configuracao`;
  - estado `situacao`: `indisponivel` | `instalar` | `bloqueada` | `desativada` | `ativa`;
  - `ativar()` só chamado por toque: `Notification.requestPermission()`, `navigator.serviceWorker.ready`, `pushManager.subscribe({ userVisibleOnly: true, applicationServerKey })` e `PUT /area-cliente/notificacoes/inscricao` com `subscription.toJSON()`;
  - `desativar()`: `DELETE` + `unsubscribe()`;
  - `salvar_preferencias(preferencias)` (`PUT` com as preferências);
  - ao montar com a permissão concedida, lê `GET …/inscricao?endpoint=` para as preferências;
  - só para sessão de cliente (ou acesso assistido).
- [ ] T121 [P] [US3] Criar resources/js/components/NotificationSettingsModal/index.jsx e styles.jsx: liga/desliga dos 5 tipos (Gols, Intervalo, Fim de jogo, Resultado da aposta, Encerramento automático) e "Desativar notificações neste aparelho"; usa `ModalCard`/`Backdrop` e `useVoltarFecha` como os demais modais
- [ ] T122 [US3] Criar resources/js/components/NotificationBell/index.jsx e styles.jsx:
  - ícone `notifications` / `notifications_active` / `notifications_off` conforme `useNotificacoesPush`;
  - tocar: `desativada` → `ativar`; `ativa` → `NotificationSettingsModal`; `instalar` → orientação "Para receber notificações no iPhone, toque em Compartilhar e em Adicionar à Tela de Início"; `bloqueada` → como liberar nas configurações do navegador;
  - não aparece sem login ou com `indisponivel`.
- [ ] T123 [US3] Em resources/js/pages/Ticket/index.jsx (arquivo da T055): `NotificationBell` no cabeçalho do resumo, só com sessão de cliente
- [ ] T124 [US3] ⚠️ Em resources/js/components/AccountHeader/index.jsx (e styles.jsx para o espaçamento): `NotificationBell` antes do ícone de menu
- [ ] T125 [US3] ⚠️ Em resources/js/hooks/useSessao.js (`sair`): antes do logout do cliente, se houver inscrição push no aparelho, `DELETE /area-cliente/notificacoes/inscricao?endpoint=` com o token atual e `unsubscribe()`; falhas ignoradas (a saída continua)

**Checkpoint**: as três histórias funcionando (quickstart 37 a 47).

---

## Phase 6: Polish & Cross-Cutting

- [ ] T126 Conferir a coleção ⚠️ docs/postman/wssports_api.postman_collection.json: as 12 rotas novas da API, o campo `encerrar_aposta` e as variáveis `base_url` e `token`; `php artisan route:list --path=api` com os middlewares de contracts/api.md
- [ ] T127 Revisão de legibilidade (Princípio V) do conjunto, informando o que foi refatorado ou que nada foi preciso. Pontos a olhar:
  - duplicação entre `BilheteTempoReal`, `CalculoEncerramento` e `ComprovanteApostaResource` (placar/andamento);
  - tamanho de `EncerramentoApostas` (pedido, decisão, efeitos e desfazimento);
  - consultas repetidas na `ElegibilidadeEncerramento`;
  - nomes no frontend (`useBilheteTempoReal`, `CashOutPanel`);
  - comentários desatualizados.
- [ ] T128 Desempenho (metas do plano): medir `GET /publico/bilhetes/{codigo}` com 20 palpites (< 300 ms, sem N+1 no log de consultas) e o tempo do pós-processamento da carga do ao vivo com 300 jogos (< 1 s); medir de ponta a ponta a mudança no mock → tela (≤ 5 s, SC-001, ao vivo e pré-jogo) e → aviso no aparelho (≤ 15 s, SC-008); anotar em "Notas da implementação"
- [ ] T129 Validar todos os cenários do [quickstart.md](quickstart.md), incluindo a responsividade em 360 px nos 2 modos e o push com o app fechado num Android e num iPhone com o app instalado; anotar o resultado em "Notas da implementação" neste arquivo

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sem dependências.
- **Foundational (Phase 2)**: depois do Setup; bloqueia todas as histórias.
- **US1 (Phase 3)**: depois da Foundational. É a base visual das outras duas (a página `Ticket`
  recebe o painel de encerramento e o sino).
- **US2 (Phase 4)**: depois da Foundational. O backend (T059 a T093) não depende da US1; T081,
  T100 e T071 dependem dos arquivos criados na US1 (T037, T055, T043).
- **US3 (Phase 5)**: depois da Foundational. O backend de push (T103 a T115) não depende da US1 nem
  da US2; T107 depende da T043, T110 da T063 e T123 da T055.
- **Polish (Phase 6)**: depois de todas.

### Arquivos tocados por mais de uma tarefa (executar em ordem)

| Arquivo | Ordem |
|---|---|
| `app/Services/ApuracaoApostas.php` | T047 → T074 → T109 |
| `app/Services/ImportacaoResultados.php` | T045 → T108 |
| `app/Services/ImportacaoCotacoes.php` | T046 → T072 |
| `app/Services/ProcessamentoAoVivo.php` | T043 → T071 → T107 |
| `app/Services/CalculoEncerramento.php` | T033 → T062 |
| `app/Services/BilheteTempoReal.php` | T037 → T081 |
| `app/Services/EncerramentoApostas.php` | T063 → T110 |
| `resources/js/pages/Ticket/index.jsx` | T055 → T100 → T123 |
| `resources/js/hooks/useSessao.js` | T056 → T125 |
| `resources/js/components/TicketModal/*` | T057 → T102 |
| `routes/api.php` + Postman | T040 → T091 → T115 → T126 |
| `routes/web.php` | T042 → T117 |

### Dentro de cada história

- Migrations → enums → models → serviços → controllers/rotas → frontend.
- Serviço antes do controller que o usa; hook antes do componente que o usa; componentes antes da
  página.

### Parallel Opportunities

- Setup: T003, T005 e T006 em paralelo depois da T002.
- Foundational: as 8 migrations (T007 a T014, respeitando T007 → T008 e T012 → T014 na hora do
  `migrate`), os 8 enums (T016 a T023), os 5 models novos (T024 a T028) e T030/T031; T034 e T035.
- US1: T036, T041 e T048 em paralelo; componentes T050 a T054 em paralelo depois do hook T049.
- US2: T059, T060, T064, T067, T069, T070, os requests e resources (T076 a T078, T082, T083, T088,
  T089) e T085, T086, T094, T096, T097, T099, T101 e T102.
- US3: T103, T105, T112, T113, T116 e T119; T121 em paralelo com o hook T120.

---

## Parallel Example: User Story 1

```bash
# depois da Foundational, em paralelo:
Task: "T036 PlacarConfronto::do_ao_vivo em app/Support/PlacarConfronto.php"
Task: "T041 BilheteController em app/Http/Controllers/BilheteController.php"
Task: "T048 utils/realtime.js em resources/js/utils/realtime.js"

# depois do hook T049, os componentes em paralelo:
Task: "T050 ConnectionStatus"
Task: "T051 TicketProgress"
Task: "T052 TicketSummary"
Task: "T053 LiveHunchCard"
Task: "T054 LiveBlockCard"
```

## Parallel Example: User Story 2

```bash
Task: "T059 RolloverClientes::retirar/devolver"
Task: "T060 TokenOferta em app/Support/TokenOferta.php"
Task: "T076 EncerrarApostaRequest"
Task: "T082 ConfiguracoesEncerramentoRequest"
Task: "T088 ListagemEncerramentosRequest"
Task: "T094 CashOutRules"
```

## Parallel Example: User Story 3

```bash
Task: "T103 GerarChavesPushCommand"
Task: "T112 InscricaoNotificacaoRequest"
Task: "T116 resources/pwa/push_notifications.js"
Task: "T119 utils/web_push.js"
```

---

## Implementation Strategy

### MVP First (User Story 1)

1. Phase 1 (Setup) e Phase 2 (Foundational).
2. Phase 3 (US1): bilhete em tempo real, link no cupom e no extrato.
3. **Parar e validar** pelos cenários 1 a 13 do quickstart.

### Entrega incremental

1. Setup + Foundational → base pronta.
2. US1 → bilhete em tempo real (MVP).
3. US2 → encerramento (backend completo, depois o painel na página, depois Regras e painel admin).
4. US3 → notificações push.
5. Polish → Postman, legibilidade, desempenho e quickstart completo.

---

## Notes

- [P] = arquivos diferentes, sem dependências pendentes.
- Commit manual pelo `/speckit-git-commit` (não commitar a cada tarefa sem pedido).
- Cada checkpoint é um ponto de validação independente pelo quickstart.

## Notas da implementação

_(preenchido na implementação: resultados do quickstart e medições de desempenho)_
