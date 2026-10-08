---

description: "Lista de tarefas da feature Confrontos (jogos e cotações do provedor)"
---

# Tasks: Confrontos (jogos e cotações do provedor)

**Input**: Documentos de design em `specs/003-confrontos/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md),
[data-model.md](data-model.md), [contracts/api.md](contracts/api.md),
[contracts/provedor.md](contracts/provedor.md), [quickstart.md](quickstart.md)

**Tests**: NÃO há tarefas de teste — a constituição proíbe testes automatizados. Cada user story é
validada manualmente pelos passos do [quickstart.md](quickstart.md).

**Organization**: tarefas agrupadas por user story, em ordem de prioridade (P1 → P2 → P3). Os
rótulos US1…US11 seguem a numeração da spec.

**Revisão 2026-10-05**: a API do provedor continua com as rotas separadas do sistema antigo
(Clarifications 2026-10-05). As tarefas T001, T002, T032, T034–T038, T049, T050, T058 e T060
foram reescritas para o estado final (três cargas do pré-jogo, nomes do provedor, chave na URL,
agendamento escalonado) e revalidadas com o simulador do provedor no formato antigo.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: pode rodar em paralelo (arquivos diferentes, sem dependências pendentes)
- **[Story]**: user story da tarefa (US1 a US11)
- Caminhos relativos à raiz do repositório

## Regras que valem para TODAS as tarefas

- **Princípio I**: métodos, variáveis, parâmetros, chaves JSON, query string e colunas em
  `snake_case`; classes em `PascalCase` (sem acento); métodos exigidos pelo framework mantêm o nome
  (`handle`, `rules`, `messages`, `authorize`, `prepareForValidation`, `toArray`, `casts`,
  `middleware`, `up`, `down`, `run`). Rotas em kebab-case. Permissões `<recurso>.<acao>`.
- **Enums (research R-18)**: casos em `PascalCase`; valores gravados em português como no
  data-model. Arquivos em UTF-8.
- **Princípio II**: comentários no código, mensagens de validação, de erro e de log em português.
- **Princípio IV**: alterar só os arquivos e trechos indicados. Arquivos existentes marcados com
  ⚠️ estão na tabela "Arquivos existentes que serão alterados" do [plan.md](plan.md) e tiveram a
  alteração autorizada pelo responsável (2026-10-01). NÃO alterar
  `bootstrap/app.php`, `config/auth.php`, `GarantirAcesso`, `GarantirAcessoCliente`,
  `GarantirPermissaoCliente` nem migrations existentes.
- **Princípio V**: ao concluir cada tarefa, revisar em conjunto o código alterado e informar se
  houve ou não refatoração.
- **Banco**: só `php artisan migrate` e os seeders indicados. NUNCA `migrate:fresh` nem
  `migrate:refresh` (research R-20).
- **SQL**: nunca montar SQL por concatenação de dados do provedor ou da requisição; usar Query
  Builder/Eloquent com bindings (FR-054). O fuso nunca entra no SQL (R-11).
- **Datas**: sempre UTC no banco (`config/app.php` já está em `UTC`).
- **Paginação**: painel `por_pagina` 1–100, padrão 20; listagem pública 1–100, padrão 50.
- **Padrões das specs 001 e 002**: FormRequests com `messages()` em português; controllers do
  painel com `HasMiddleware` e `self::permissao_cliente('<permissão>', only: [...])` (trait
  `App\Http\Controllers\Concerns\GarantirPermissaoCliente`, que chama `Funcao::usuario_pode`);
  erros `{"message": "..."}`; filtros vazios ignorados com `nullable`.
- **Cotações e regras em JSON (R-01)**: objeto só com códigos diferentes de zero; código ausente =
  0. Usar sempre `App\Support\CodigosCotacao` para validar códigos.

---

## Phase 1: Setup (Shared Infrastructure)

- [X] T001 ⚠️ Em config/services.php, acrescentar o bloco `'provedor_cotacoes' => ['url_pre_jogo' => env('PROVEDOR_COTACOES_URL_PRE_JOGO', 'https://apiprejogo.wssports.bet/api'), 'url_ao_vivo' => env('PROVEDOR_COTACOES_URL_AO_VIVO', 'https://apiaovivo.wssports.bet/api'), 'url_conferencia' => env('PROVEDOR_COTACOES_URL_CONFERENCIA', 'https://api.oddbrasil.com/bet/v2'), 'chave' => env('PROVEDOR_COTACOES_CHAVE'), 'app' => env('PROVEDOR_COTACOES_APP'), 'tempo_limite_campeonatos' => 60, 'tempo_limite_confrontos' => 180, 'tempo_limite_cotacoes' => 180, 'tempo_limite_ao_vivo' => 4, 'tempo_limite_conferencia' => 10]`, com comentário em português (URLs base; chave e `app` vão na URL porque a API exige); não alterar os outros blocos (research R-05)
- [X] T002 [P] ⚠️ Em .env.example, acrescentar `PROVEDOR_COTACOES_URL_PRE_JOGO`, `PROVEDOR_COTACOES_URL_AO_VIVO` e `PROVEDOR_COTACOES_URL_CONFERENCIA` com os endereços públicos do provedor, `PROVEDOR_COTACOES_CHAVE=` sem valor e `PROVEDOR_COTACOES_APP=` (vazio usa o `APP_URL`)
- [X] T003 [P] Criar app/Support/CodigosCotacao.php: constante `JOGADOR = 'jogador'`; estáticos `cotacoes(): array` (`odd1`…`odd323`, gerada uma vez e guardada em propriedade estática), `regras(): array` (as 323 + `jogador`), `e_cotacao(string): bool` e `e_regra(string): bool` (research R-18)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: enums, tabelas, models, permissões, seeder, cliente HTTP do provedor e helper de
hierarquia

**⚠️ CRITICAL**: nenhuma user story começa antes desta fase terminar

### Enums e exceção

- [X] T004 [P] Criar app/Enums/AlvoRegra.php (backed `string`): `Clientes = 'Clientes'`, `Vendedores = 'Vendedores'`, `Todos = 'Todos'`
- [X] T005 [P] Criar app/Enums/SituacaoConfronto.php: `Aguardando = 'Aguardando'`, `Encerrado = 'Encerrado'`, `Cancelado = 'Cancelado'`, `Adiado = 'Adiado'`, `Bloqueado = 'Bloqueado'` (acrescentado em 2026-10-05: o provedor suspende jogos com essa situação); estático `editaveis_manualmente(): array` (`Aguardando`, `Adiado`, `Cancelado`)
- [X] T006 [P] Criar app/Enums/SituacaoAoVivo.php: `PrimeiroTempo = '1 tempo'`, `Intervalo = 'Intervalo'`, `SegundoTempo = '2 tempo'`
- [X] T007 [P] Criar app/Exceptions/FalhaProvedorException.php (estende `RuntimeException`), com mensagem em português e sem nunca incluir a chave do provedor

### Migrations (todas com `timestamps()` e `softDeletes()`; comentários em português)

- [X] T008 [P] Criar database/migrations/2026_10_01_000001_create_configuracoes_table.php: `id()`; `boolean('somente_cassino')->default(false)`; `boolean('permitir_entrada_campeonatos')->default(true)`; `decimal('sorteio_ambas_marcam_minimo', 8, 2)->default(1.30)`; `decimal('sorteio_ambas_marcam_maximo', 8, 2)->default(1.50)`; `decimal('sorteio_ambas_nao_marcam_minimo', 8, 2)->default(1.45)`; `decimal('sorteio_ambas_nao_marcam_maximo', 8, 2)->default(1.55)`; `boolean('ao_vivo_habilitado')->default(true)`; `unsignedSmallInteger('segundos_trava_ao_vivo')->default(15)`; `unsignedSmallInteger('minutos_permanencia_ao_vivo')->default(5)`; `unsignedSmallInteger('minuto_limite_ao_vivo')->default(95)`; `decimal('cotacao_maxima_ao_vivo', 8, 2)->default(30.00)`; `boolean('ao_vivo_travado')->default(false)`; `timestamp('ao_vivo_travado_em')->nullable()`
- [X] T009 [P] Criar database/migrations/2026_10_01_000002_create_visitantes_configuracoes_table.php: `id()`; `json('esportes_permitidos')->default(new Expression("(JSON_ARRAY('FUTEBOL','HOQUEI NO GELO','BAISEBOL'))"))` (comentário: lista de nomes, sem FK; padrão na coluna, MySQL 8.4); `boolean('apostar_outros_esportes')->default(true)`; `boolean('ao_vivo_habilitado')->default(true)`
- [X] T010 [P] Criar database/migrations/2026_10_01_000003_create_usuarios_configuracoes_table.php: `id()`; `foreignId('usuarios_id')->unique()->constrained('usuarios')`; `esportes_permitidos` igual a T009; `boolean('apostar_outros_esportes')->default(true)`; `boolean('ao_vivo_habilitado')->default(true)`; `unsignedSmallInteger('minuto_limite_ao_vivo')->default(95)`; `decimal('cotacao_maxima_ao_vivo', 8, 2)->default(30.00)`
- [X] T011 [P] Criar database/migrations/2026_10_01_000004_create_campeonatos_table.php: `id()`; `unsignedBigInteger('codigo_externo')->nullable()->unique()` (comentário: nulo nos manuais); `string('nome', 150)`; `string('pais', 100)`; `string('bandeira')->nullable()`; `boolean('ativo')->default(true)`; `boolean('favorito')->default(false)`; `boolean('manual')->default(false)`; índices (`nome`, `pais`) e (`ativo`, `favorito`)
- [X] T012 [P] Criar database/migrations/2026_10_01_000005_create_confrontos_table.php: `id()`; `unsignedBigInteger('codigo_externo')->nullable()->unique()`; `foreignId('campeonatos_id')->constrained('campeonatos')`; `string('time_casa', 150)`; `string('escudo_casa')->nullable()`; `string('time_fora', 150)`; `string('escudo_fora')->nullable()`; `string('esporte', 50)` (comentário: como o provedor envia, sem FK); `string('situacao', 20)->default('Aguardando')`; `dateTime('data_inicio')`; `boolean('ativo')->default(true)`; `boolean('manual')->default(false)`; `boolean('odd4_sorteada')->default(false)`; `boolean('odd7_sorteada')->default(false)`; `unsignedSmallInteger('quantidade_cotacoes')->default(0)`; `json('cotacoes')` (comentário: só códigos ≠ 0, R-01); índice (`situacao`, `esporte`, `data_inicio`)
- [X] T013 [P] Criar database/migrations/2026_10_01_000006_create_confrontos_jogadores_table.php: `id()`; `foreignId('confrontos_id')->constrained('confrontos')`; `unsignedBigInteger('codigo_externo')`; `string('nome', 150)`; `string('opcao', 60)`; `string('tipo', 60)`; `decimal('odd', 8, 2)`; único (`confrontos_id`, `codigo_externo`, `tipo`)
- [X] T014 [P] Criar database/migrations/2026_10_01_000007_create_confrontos_ao_vivo_table.php: `id()`; `unsignedBigInteger('codigo_externo')->unique()`; `foreignId('confrontos_id')->nullable()->constrained('confrontos')`; `foreignId('campeonatos_id')->constrained('campeonatos')`; times (`string(…, 150)`) e escudos (`string()->nullable()`) como T012; `string('esporte', 50)`; `dateTime('data_inicio')`; `unsignedSmallInteger` `placar_casa`, `placar_fora` (`default(0)`); `unsignedSmallInteger` nullable `gols_primeiro_tempo_casa`, `gols_primeiro_tempo_fora`, `gols_segundo_tempo_casa`, `gols_segundo_tempo_fora`, `escanteios_casa`, `escanteios_fora`; `unsignedSmallInteger('minuto')->default(0)`; `string('cronometro', 10)->nullable()`; `string('situacao', 20)`; `json('cotacoes')`; `unsignedSmallInteger('quantidade_cotacoes')->default(0)`; `timestamp('ultima_atualizacao_em')`; índice (`situacao`, `ultima_atualizacao_em`)
- [X] T015 [P] Criar database/migrations/2026_10_01_000008_create_confrontos_teto_cotacoes_table.php: `id()`; `json('tetos')->default(new Expression("(JSON_OBJECT())"))` (comentário: registro único; código ausente = sem teto)
- [X] T016 [P] Criar database/migrations/2026_10_01_000009_create_porcentagens_vendedores_table.php e database/migrations/2026_10_01_000010_create_porcentagens_vendedores_ao_vivo_table.php: `id()`; `foreignId('usuarios_id')->unique()->constrained('usuarios')`; `json('valores')->default(new Expression("(JSON_OBJECT())"))` (comentário: −100 a 100 por código, inclusive `jogador`)
- [X] T017 [P] Criar database/migrations/2026_10_01_000011_create_porcentagens_clientes_table.php e database/migrations/2026_10_01_000012_create_porcentagens_clientes_ao_vivo_table.php: `id()`; `foreignId('clientes_id')->nullable()->constrained('clientes')` (comentário: nulo = regra geral de visitantes e clientes); `unsignedBigInteger('chave_cliente')->storedAs('COALESCE(clientes_id, 0)')->unique()` (research R-12); `json('valores')` com padrão `JSON_OBJECT()`
- [X] T018 [P] Criar database/migrations/2026_10_01_000013_create_porcentagens_campeonatos_table.php e database/migrations/2026_10_01_000014_create_porcentagens_confrontos_table.php: `id()`; `foreignId('campeonatos_id')->constrained('campeonatos')` (na de confrontos, `confrontos_id` → `confrontos`); `string('alvo', 20)`; `foreignId('usuarios_id')->nullable()->constrained('usuarios')` (comentário: dono, só no alvo Vendedores); `unsignedBigInteger('chave_usuario')->storedAs('COALESCE(usuarios_id, 0)')`; `json('valores')` com padrão `JSON_OBJECT()`; único (item, `alvo`, `chave_usuario`)
- [X] T019 [P] Criar database/migrations/2026_10_01_000015_create_campeonatos_nao_permitidos_table.php, database/migrations/2026_10_01_000016_create_confrontos_nao_permitidos_table.php e database/migrations/2026_10_01_000017_create_confrontos_ao_vivo_nao_permitidos_table.php: `id()`; item (`campeonatos_id` → `campeonatos` na primeira; `confrontos_id` → `confrontos` nas duas outras — no ao vivo, comentário: jogo da grade, research R-13); `string('alvo', 20)`; `foreignId('usuarios_id')->nullable()->constrained('usuarios')`; `foreignId('clientes_id')->nullable()->constrained('clientes')` (comentário: só no alvo Clientes; nulo = todos os clientes e visitantes); colunas geradas `chave_usuario` e `chave_cliente` (`COALESCE(..., 0)`); único (item, `alvo`, `chave_usuario`, `chave_cliente`)

### Models (todos com `SoftDeletes`)

- [X] T020 [P] Criar app/Models/Configuracoes.php (`$table = 'configuracoes'`): casts booleanos, `'decimal:2'` nos decimais, inteiros e `ao_vivo_travado_em => 'datetime'`; estático `atual(): self` (`firstOrFail`); métodos `intervalo_sorteio(string $codigo): ?array` (`odd4` → ambas marcam; `odd7` → ambas não marcam; `null` se mínimo > máximo) (depende de T008)
- [X] T021 [P] Criar app/Models/VisitantesConfiguracoes.php e app/Models/UsuariosConfiguracoes.php: constante `CAMPOS` com os campos de configuração; casts `esportes_permitidos => 'array'`, booleanos, `minuto_limite_ao_vivo => 'integer'`, `cotacao_maxima_ao_vivo => 'decimal:2'`; `VisitantesConfiguracoes::atual()`; `UsuariosConfiguracoes::usuario()` (belongsTo `Usuarios`); método comum `esportes_visiveis(): array` (só `['FUTEBOL']` quando `apostar_outros_esportes` é falso; senão `esportes_permitidos`) (depende de T009, T010)
- [X] T022 [P] Criar app/Models/Campeonatos.php: `$fillable` `nome`, `pais`, `bandeira` (nunca `codigo_externo`, `ativo`, `favorito`, `manual` por atribuição em massa); casts booleanos; relações `confrontos()`, `porcentagens()` (`PorcentagensCampeonatos`), `nao_permitidos()` (`CampeonatosNaoPermitidos`) (depende de T011)
- [X] T023 [P] Criar app/Models/Confrontos.php e app/Models/ConfrontosJogadores.php: `Confrontos` com casts `situacao => SituacaoConfronto::class`, `data_inicio => 'datetime'`, booleanos, `cotacoes => 'array'`; relações `campeonato()`, `jogadores()`, `ao_vivo()` (hasOne `ConfrontosAoVivo`, `confrontos_id`); método `cotacao(string $codigo): float` (0 se ausente); `ConfrontosJogadores` com `odd => 'decimal:2'` e `confronto()` (depende de T005, T012, T013)
- [X] T024 [P] Criar app/Models/ConfrontosAoVivo.php (`$table = 'confrontos_ao_vivo'`): casts `situacao => SituacaoAoVivo::class`, `data_inicio`/`ultima_atualizacao_em => 'datetime'`, `cotacoes => 'array'`; relações `confronto()` e `campeonato()`; método `travado(Configuracoes $configuracoes): bool` (`ao_vivo_travado` ou mais de `segundos_trava_ao_vivo` desde `ultima_atualizacao_em`, research R-07) (depende de T006, T014)
- [X] T025 [P] Criar app/Models/ConfrontosTetoCotacoes.php (`tetos => 'array'`, `atual()`), app/Models/PorcentagensVendedores.php e app/Models/PorcentagensVendedoresAoVivo.php (`usuarios_id`, `valores => 'array'`, `usuario()`), app/Models/PorcentagensClientes.php e app/Models/PorcentagensClientesAoVivo.php (`clientes_id`, `valores => 'array'`, `cliente()`; `chave_cliente` fora do `$fillable`) (depende de T015–T017)
- [X] T026 [P] Criar app/Models/PorcentagensCampeonatos.php, app/Models/PorcentagensConfrontos.php, app/Models/CampeonatosNaoPermitidos.php, app/Models/ConfrontosNaoPermitidos.php e app/Models/ConfrontosAoVivoNaoPermitidos.php: `alvo => AlvoRegra::class`, `valores => 'array'` (nas de porcentagem), relações com o item, `usuario()` e `cliente()`; colunas geradas fora do `$fillable` (depende de T004, T018, T019)

### Permissões, hierarquia, seeder e provedor

- [X] T027 Em app/Enums/Funcao.php, acrescentar `PERMISSOES_CONFRONTOS` (as 21: `porcentagens_vendedores.editar`, `porcentagens_clientes.editar`, `porcentagens_campeonatos.editar`, `porcentagens_confrontos.editar`, `confrontos_teto_cotacoes.editar`, `campeonatos.listar`, `campeonatos.cadastrar`, `campeonatos.editar`, `campeonatos.excluir`, `campeonatos.alterar_situacao`, `campeonatos.favoritar`, `confrontos.listar`, `confrontos.cadastrar`, `confrontos.editar`, `confrontos.excluir`, `confrontos.alterar_situacao`, `campeonatos_nao_permitidos.gerenciar`, `confrontos_nao_permitidos.gerenciar`, `confrontos_ao_vivo_nao_permitidos.gerenciar`, `usuarios_configuracoes.editar`, `visitantes_configuracoes.editar`) e `PERMISSOES_CONFRONTOS_GERENTE` (`porcentagens_vendedores.editar`, `porcentagens_campeonatos.editar`, `porcentagens_confrontos.editar`, `campeonatos.listar`, `confrontos.listar`, as três `*_nao_permitidos.gerenciar` e `usuarios_configuracoes.editar`); em `permissoes_padrao()` incluí-las (Admin e Supervisor: 21; Gerente: 9; Vendedor: nenhuma); em `pode_usar()`: Admin e Supervisor todas, Gerente só as de `PERMISSOES_CONFRONTOS_GERENTE`, Vendedor nenhuma das de confrontos; manter o comportamento atual das demais (research R-15)
- [X] T028 Em database/seeders/PapeisPermissoesSeeder.php, criar também `Funcao::PERMISSOES_CONFRONTOS` no laço de permissões
- [X] T029 Em app/Models/Usuarios.php, acrescentar `ids_hierarquia_acima(): array` (ids do próprio usuário e de todos os superiores, subindo por `usuarios_id`, ignorando excluídos) e a relação `configuracoes(): HasOne` (`UsuariosConfiguracoes`, `usuarios_id`); não alterar os demais métodos (research R-10, R-16)
- [X] T030 Criar database/seeders/ConfrontosSeeder.php: `forgetCachedPermissions`; `firstOrCreate` das 21 permissões (guard `api`); dar aos usuários existentes as de `PERMISSOES_CONFRONTOS` contidas no `permissoes_padrao()` da função deles; criar (se não existir) o registro único de `configuracoes`, `visitantes_configuracoes` e `confrontos_teto_cotacoes` só com valores padrão das colunas; criar `usuarios_configuracoes` (só `usuarios_id`) para cada usuário com papel `Vendedor` que ainda não tem (FR-076) (depende de T020–T027)
- [X] T031 Em database/seeders/DatabaseSeeder.php, acrescentar `$this->call(ConfrontosSeeder::class);` ao final
- [X] T032 Criar app/Services/ProvedorCotacoes.php, um método por rota do provedor ([contracts/provedor.md](contracts/provedor.md)): `buscar_campeonatos(): array` (GET `url_pre_jogo/campeonatos`), `buscar_confrontos(array $campeonatos_desativados): array` (POST `url_pre_jogo/confrontos`), `buscar_cotacoes(array $campeonatos_desativados): array` (POST `url_pre_jogo/cotacao`), `buscar_ao_vivo(array $campeonatos_desativados): array` (POST `url_ao_vivo/aovivo`) e `consultar_minuto(int $codigo_externo): int` (GET `url_conferencia/confrontos/{codigo}`, campo `minuto_exato`); `Http::acceptJson()->withHeaders(['Accept-Encoding' => 'gzip'])->withQueryParameters(['key' => chave, 'app' => app ou app.url])->timeout(...)` com os tempos de config; corpo `{"campeonatos_id": [...]}`; exceção, status ≠ 2xx ou JSON não decodificável → `FalhaProvedorException` com só o nome da rota, o status ou o nome da classe da exceção (nunca a URL, que tem a chave, nem a mensagem original) (FR-053, FR-056, research R-05) (depende de T001, T007)
- [X] T033 Rodar `php artisan migrate`, `php artisan db:seed --class=PapeisPermissoesSeeder` e `php artisan db:seed --class=ConfrontosSeeder`; conferir as 17 tabelas, os 3 registros únicos e as permissões (quickstart passo 1, exceto a remoção do padrão, que é da US11)

**Checkpoint**: estrutura pronta — as user stories podem começar

---

## Phase 3: User Story 1 - Carga do pré-jogo (Priority: P1) 🎯 MVP

**Goal**: `campeonatos:importar` (10 min), `confrontos:importar` (5 min) e
`confrontos_cotacoes:importar` (5 min), escalonados, trazem e gravam campeonatos, confrontos,
cotações e jogadores pelas rotas separadas do provedor.

**Independent Test**: quickstart passos 1 a 11c (rodar os três comandos contra o mock e conferir o
banco).

- [X] T034 [US1] Criar app/Services/ValidacaoCargaProvedor.php com `validar_campeonatos()`, `validar_confrontos()` e `validar_cotacoes()` (resposta que não é lista → `FalhaProvedorException`, recusa a carga inteira), traduzindo os nomes do provedor (research R-02): campeonato sem `fonte_id` inteiro > 0, `nome` ou `pais` texto → ignorado; confronto sem `fonte_id`, `campeonatos_id`, `casa`, `fora`, `tipo_esporte`, `situacao` ∈ `SituacaoConfronto` ou `horario` válido (UTC; normalizado para `Y-m-d H:i:s`) → ignorado; cotações lidas campo a campo de `CodigosCotacao::cotacoes()` (ausente ou nulo = 0; não numérico ou negativo → item ignorado; só ≠ 0 devolvidos); jogador (`jogador[]`) sem `atletas_id`, `nome`, `opcao`, `tipo` ou com `odd` ≤ 0 → jogador ignorado. Devolve os itens válidos e a lista de motivos (sem o `Validator` do Laravel) (depende de T003, T005, T007)
- [X] T035 [US1] Acrescentar `codigos_desativados(): array` em app/Models/Campeonatos.php (`ativo = false`, `manual = false`, com `codigo_externo`; FR-004, FR-064) e criar, cada um com `importar(): array` (resumo) e gravação em `DB::transaction` (FR-005): app/Services/ImportacaoCampeonatos.php — `buscar_campeonatos()`, herança por nome+país e movimentação em `porcentagens_campeonatos` e `campeonatos_nao_permitidos` (research R-04), upsert só dos novos ou alterados (`nome`, `pais`, `bandeira`, `updated_at`; novo nasce `ativo = permitir_entrada_campeonatos`, `favorito = false`, `manual = false`, FR-006); app/Services/ImportacaoConfrontos.php — `buscar_confrontos(codigos_desativados())`, mapa dos campeonatos por `codigo_externo`, confronto sem campeonato ignorado e contado no log (FR-005a), upsert criando com `cotacoes = {}` e `quantidade_cotacoes = 0` e atualizando só times, escudos, esporte, situação, `data_inicio` e `updated_at`; app/Services/ImportacaoCotacoes.php — `buscar_cotacoes(codigos_desativados())`, leitura dos confrontos existentes (com esporte, marcações e `odd4`/`odd7` atuais), cotação de confronto inexistente ignorada e contada no log (FR-005a), sorteio de `odd4`/`odd7` só para `FUTEBOL` (research R-03), `quantidade_cotacoes` = códigos ≠ 0 + jogadores (FR-009), upsert só de `cotacoes`, `quantidade_cotacoes`, marcações e `updated_at`, e jogadores por (`confrontos_id`, `codigo_externo`, `tipo`) com soft delete dos não recebidos (FR-010) (depende de T020–T023, T032, T034)
- [X] T036 [US1] Criar app/Console/Commands/Concerns/ExecutarCargaPreJogo.php (trait: sai sem chamar o provedor se `somente_cassino`, FR-012; `FalhaProvedorException` ou erro de banco → `Log::error` sem dados sensíveis, nada gravado, FR-011, saída de falha; sucesso → `Log::info` com o resumo e o tempo) e os comandos app/Console/Commands/ImportarCampeonatosCommand.php (`campeonatos:importar`), app/Console/Commands/ImportarConfrontosCommand.php (`confrontos:importar`) e app/Console/Commands/ImportarCotacoesCommand.php (`confrontos_cotacoes:importar`), com descrição em português (depende de T035)
- [X] T037 [US1] Em routes/console.php, agendar com comentário (FR-001, research R-06): `campeonatos:importar` → `cron('*/10 * * * *')`, `confrontos:importar` → `cron('1-59/5 * * * *')`, `confrontos_cotacoes:importar` → `cron('2-59/5 * * * *')`, todos com `->runInBackground()->withoutOverlapping(10)`; não alterar o comando `inspire`
- [X] T038 [US1] Validar pelo specs/003-confrontos/quickstart.md, passos 1 a 11c (incluindo cada carga < 5 s, SC-001) e revisar o código da US1 (Princípio V)

**Checkpoint**: jogos e cotações sendo gravados — MVP de dados

---

## Phase 4: User Story 2 - Listagem pública de jogos (Priority: P1)

**Goal**: `GET /api/publico/confrontos` com os jogos de hoje, amanhã ou depois de amanhã, no fuso
pedido, para visitante, cliente ou usuário do painel.

**Independent Test**: quickstart passos 12 a 19.

- [X] T039 [P] [US2] Criar app/Services/Publico.php (classe `final readonly`): tipo (`visitante`, `cliente`, `vendedor`, `gestor`), `?Clientes $cliente`, `?Usuarios $usuario`, `bool $token_recusado`; métodos `e_visitante()`, `e_cliente()`, `e_vendedor()`, `e_gestor()` (Gerente, Supervisor ou Admin) (research R-09)
- [X] T040 [US2] Criar app/Services/IdentificacaoPublico.php com `identificar(Request $request): Publico`: sem `Authorization` → visitante; senão tenta `auth('clientes')->check()` e depois `auth('api')->check()` dentro de `try/catch` (`JWTException`); cliente precisa de `pode_acessar()` e `iat` ≥ `tokens_validos_desde` (mesma regra do `GarantirAcessoCliente`, sem alterá-lo); usuário precisa de `pode_acessar()`; tipo do usuário pela `funcao()` (Vendedor → `vendedor`; demais → `gestor`); token presente e recusado → visitante com `token_recusado = true` (FR-036) (depende de T039)
- [X] T041 [P] [US2] Criar app/Http/Requests/ListagemPublicaRequest.php: `tipo` `nullable|in:pre_jogo,ao_vivo`; `dia` `nullable|in:hoje,amanha,depois_de_amanha`; `busca` `nullable|string|max:100`; `esporte` `nullable|string|max:50`; `somente_favoritos` `nullable|boolean`; `fuso_horario` `nullable|regex:/^[+-](0\d|1[0-4]):[0-5]\d$/` e entre `-12:00` e `+14:00`; `pagina` `nullable|integer|min:1`; `por_pagina` `nullable|integer|between:1,100`; mensagens em português; limite de 120 requisições por minuto por IP com `RateLimiter` (mesmo padrão do `LoginClienteRequest`), respondendo `429` com "Muitas requisições. Tente novamente em N segundos." (FR-043, FR-044)
- [X] T042 [US2] Criar app/Services/CalculoCotacoes.php com `ajustar(Publico $publico, string $tipo, Collection $itens): array` que devolve, por item, `odd1`–`odd4` ajustadas: base 0 → 0 sem ajuste (FR-048); `base + base × soma_porcentagens / 100 + soma_valores_fixos`; limitar ao teto de `ConfrontosTetoCotacoes::atual()`; mínimo 1,00; `round(…, 2)` (FR-047, FR-051). Nesta fase, `soma_porcentagens` e `soma_valores_fixos` vêm de um método `carregar_regras()` que ainda devolve zeros (a US3 completa); nunca expor valores intermediários (FR-052) (depende de T025, T039)
- [X] T043 [US2] Criar app/Services/ListagemConfrontos.php com `pre_jogo(Publico $publico, array $filtros): array`: janela do `dia` no `fuso_horario` (padrão `-03:00`) com Carbon, convertida para UTC — `hoje` começa em `now()`; com `busca`, de `now()` ao fim de depois de amanhã (FR-038, FR-039, SC-011); `confrontos.ativo`, `campeonatos.ativo`, `situacao = 'Aguardando'`, `data_inicio > now()`; `esporte` (padrão `FUTEBOL`) e `whereIn('esporte', ...)` com os esportes do público (visitante: `VisitantesConfiguracoes::atual()->esportes_visiveis()`; cliente: `clientes_configuracoes` dele, `FUTEBOL` só se `apostar_outros_esportes` falso; vendedor: `UsuariosConfiguracoes` dele — linha ausente = valores padrão; gestor: todos) (FR-041, FR-074); `somente_favoritos`; `busca` em `time_casa`/`time_fora` (`LIKE` com binding); ordem `campeonatos.favorito desc`, `campeonatos.pais`, `campeonatos.nome`, `data_inicio`, `time_casa`; paginação (padrão 50); agrupar a página por campeonato; segunda consulta agregada com a contagem por campeonato e país sobre todo o filtro (FR-042); datas em ISO 8601 no fuso pedido e `minutos_para_inicio`; cotações via `CalculoCotacoes` (depende de T021–T023, T040, T042)
- [X] T044 [US2] Criar app/Http/Controllers/PublicoConfrontosController.php com `index(ListagemPublicaRequest)`: identifica o público (T040), chama `ListagemConfrontos::pre_jogo()` e responde no formato de [contracts/api.md](contracts/api.md) (`token_recusado`, `tipo`, `total`, `campeonatos`, `paises`, `meta`); até a US5 (T056), `tipo=ao_vivo` responde `403` "O ao vivo não está disponível." (depende de T041, T043)
- [X] T045 [US2] Em routes/api.php, acrescentar `Route::get('publico/confrontos', [PublicoConfrontosController::class, 'index']);` fora dos grupos autenticados, com o `use` e comentário em português
- [X] T046 [US2] Validar pelo specs/003-confrontos/quickstart.md, passos 12 a 19 (incluindo SC-007 com 500 jogos) e revisar o código da US2 (Princípio V)

**Checkpoint**: vitrine do pré-jogo funcionando para os três públicos

---

## Phase 5: User Story 3 - Cotação ajustada por porcentagens e teto (Priority: P1)

**Goal**: a listagem aplica as porcentagens do público, do campeonato e o valor fixo do confronto.

**Independent Test**: quickstart passos 20 a 25 (com as regras gravadas direto no banco ou pelas
rotas da US8).

- [X] T047 [US3] Em app/Services/CalculoCotacoes.php, completar `carregar_regras()` com uma consulta por tabela (research R-10): vendedor/gestor → `porcentagens_vendedores` dos `Usuarios::ids_hierarquia_acima()`, somadas; visitante → linha geral de `porcentagens_clientes`; cliente → geral + a dele, somadas (Clarifications 2026-09-30); `porcentagens_campeonatos` dos campeonatos da página nos alvos do público (`Todos`; `Clientes` para visitante e cliente; `Vendedores` com `usuarios_id` na cadeia acima do usuário); `porcentagens_confrontos` dos confrontos da página nos mesmos alvos, somando os valores fixos (FR-049, FR-050); código `jogador` não entra no cálculo de `odd1`–`odd4` (depende de T029, T042)
- [X] T048 [US3] Validar pelo specs/003-confrontos/quickstart.md, passos 20 a 25 (US3-1 a US3-8, SC-008) e revisar o código da US3 (Princípio V)

**Checkpoint**: margem da banca aplicada na vitrine

---

## Phase 6: User Story 4 - Carga do ao vivo com trava automática (Priority: P1)

**Goal**: `confrontos_ao_vivo:importar` atualiza os jogos em andamento a cada 5 s; a trava por
tempo protege o apostador.

**Independent Test**: quickstart passos 26 a 28.

- [X] T049 [US4] Em app/Services/ValidacaoCargaProvedor.php, acrescentar `validar_ao_vivo(array $resposta): array`: resposta que não é lista → `FalhaProvedorException`; confronto sem `fonte_id`, `campeonatos_id`, `casa`, `fora`, `tipo_esporte`, `horario`, `situacao` ∈ `SituacaoAoVivo`, `minuto_exato` inteiro ≥ 0, placar inteiro ≥ 0 ou com cotação inválida (mesma regra de T034) → ignorado com motivo; traduz `minuto_exato` → `minuto`, `tempo` → `cronometro`, `g1_tempo_*`/`g2_tempo_*` → `gols_primeiro_tempo_*`/`gols_segundo_tempo_*`, `escanteio_*` → `escanteios_*` ([contracts/provedor.md](contracts/provedor.md)) (depende de T006, T034)
- [X] T050 [US4] Criar app/Services/ImportacaoAoVivo.php com `importar(): array`: busca (`ProvedorCotacoes::buscar_ao_vivo(Campeonatos::codigos_desativados())`, como em T035), valida (T049), resolve `campeonatos_id` e `confrontos_id` pelo `codigo_externo` (uma consulta cada); campeonato desconhecido → ignorado com `Log::warning` (Edge Cases); `DB::table('confrontos_ao_vivo')->upsert()` por `codigo_externo` com `ultima_atualizacao_em = now()` só para os jogos válidos recebidos (FR-015, FR-018); resposta vazia → nada gravado; sem sorteio (FR-016) (depende de T024, T032, T049)
- [X] T051 [US4] Criar app/Console/Commands/ImportarConfrontosAoVivoCommand.php: assinatura `confrontos_ao_vivo:importar`; sai sem chamar o provedor se `somente_cassino` ou `ao_vivo_habilitado` desmarcado em `configuracoes` (FR-012, FR-014); falha → `Log::error` sem a chave, nada gravado (FR-018) (depende de T050)
- [X] T052 [US4] Em routes/console.php, acrescentar `Schedule::command('confrontos_ao_vivo:importar')->everyFiveSeconds()->withoutOverlapping(1);` com comentário (research R-06)
- [X] T053 [US4] Validar pelo specs/003-confrontos/quickstart.md, passos 26 a 28 (a parte de leitura da trava é conferida na US5) e revisar o código da US4 (Princípio V)

**Checkpoint**: dados do ao vivo atualizados a cada 5 s

---

## Phase 7: User Story 5 - Listagem pública do ao vivo (Priority: P2)

**Goal**: a mesma rota pública com `tipo=ao_vivo`, respeitando trava, permanência, minuto limite e
cotação máxima.

**Independent Test**: quickstart passos 26 a 33.

- [X] T054 [US5] Em app/Services/ListagemConfrontos.php, acrescentar `ao_vivo(Publico $publico, array $filtros): array`: recusa com `403` "O ao vivo não está disponível." se `ao_vivo_habilitado` desmarcado em `configuracoes`, em `visitantes_configuracoes` (visitante) ou na configuração do vendedor (FR-046c); só `situacao` ∈ `SituacaoAoVivo`, `confrontos_id` de confronto `ativo` (FR-046a), campeonato ativo, `minuto` ≤ minuto limite do público (vendedor: a dele; demais: `configuracoes`) (FR-046b), `ultima_atualizacao_em` dentro de `minutos_permanencia_ao_vivo` (FR-020); filtros `busca`, `esporte`, `somente_favoritos`, `fuso_horario` (o `dia` é ignorado); mesma paginação, agrupamento e países de T043; cada item com `placar_casa`, `placar_fora`, `minuto`, `cronometro`, `situacao` e `travado` (`ConfrontosAoVivo::travado()`); travado → cotações zeradas (FR-017, FR-022) (depende de T024, T043)
- [X] T055 [US5] Em app/Services/CalculoCotacoes.php, suportar `tipo = 'ao_vivo'`: usar `porcentagens_vendedores_ao_vivo` e `porcentagens_clientes_ao_vivo`; manter `porcentagens_campeonatos`; NÃO aplicar `porcentagens_confrontos` (FR-046); limitar também à cotação máxima do ao vivo (vendedor: a dele; demais: `configuracoes`), valendo o menor entre ela e o teto (FR-051) (depende de T047)
- [X] T056 [US5] Em app/Http/Controllers/PublicoConfrontosController.php, encaminhar `tipo=ao_vivo` para `ListagemConfrontos::ao_vivo()` (depende de T054)
- [X] T057 [US5] Validar pelo specs/003-confrontos/quickstart.md, passos 26 a 33 (SC-004, SC-005) e revisar o código da US5 (Princípio V)

---

## Phase 8: User Story 6 - Conferência do ao vivo e trava geral (Priority: P2)

**Goal**: a cada minuto, conferir o minuto de um jogo com o segundo provedor e travar ou liberar
todo o ao vivo.

**Independent Test**: quickstart passos 34 a 36.

- [X] T058 [US6] Criar app/Services/ConferenciaAoVivo.php com `conferir(): void`: sorteia (`inRandomOrder()`) um jogo de `confrontos_ao_vivo` em andamento dentro da permanência; sem jogo → nada muda; `ProvedorCotacoes::consultar_minuto()` (campo `minuto_exato` do provedor); falha → `Log::warning`, nada muda (FR-024); minuto do provedor − minuto do sistema > 1 → `ao_vivo_travado = true`, `ao_vivo_travado_em = now()` e `Log::warning` com o `codigo_externo` e os dois minutos (FR-022); diferença ≤ 1 com trava ligada → `ao_vivo_travado = false`, `ao_vivo_travado_em = null` e `Log::info` da liberação (FR-023); sem janela de horário (depende de T020, T024, T032)
- [X] T059 [US6] Criar app/Console/Commands/ConferirConfrontosAoVivoCommand.php: assinatura `confrontos_ao_vivo:conferir`; sai se `somente_cassino` ou `ao_vivo_habilitado` desmarcado; chama T058 (depende de T058)
- [X] T060 [US6] Em routes/console.php, acrescentar `Schedule::command('confrontos_ao_vivo:conferir')->everyMinute()->runInBackground()->withoutOverlapping(2);` com comentário (FR-021; em segundo plano para não atrasar o ao vivo, research R-06)
- [X] T061 [US6] Validar pelo specs/003-confrontos/quickstart.md, passos 34 a 36 (SC-006) e revisar o código da US6 (Princípio V)

---

## Phase 9: User Story 7 - Restrições de exibição (Priority: P2)

**Goal**: a listagem pública esconde campeonatos e confrontos não permitidos para quem está vendo.

**Independent Test**: quickstart passos 41 a 43 (com os registros gravados no banco ou pelas rotas
da US10).

- [X] T062 [US7] Em app/Services/ListagemConfrontos.php, criar `aplicar_nao_permitidos(Builder $consulta, Publico $publico, string $tipo)` com `whereNotExists` parametrizados (FR-040, SC-009): alvo `Todos` para todos; alvo `Clientes` com `clientes_id` nulo para visitante e cliente; alvo `Clientes` com `clientes_id` do cliente logado; alvo `Vendedores` com `usuarios_id` em `Usuarios::ids_hierarquia_acima()` para usuário do painel; aplicar em `campeonatos_nao_permitidos` (pré-jogo e ao vivo), `confrontos_nao_permitidos` (pré-jogo) e `confrontos_ao_vivo_nao_permitidos` pelo `confrontos_id` (ao vivo, research R-13); aplicar também na consulta agregada de países (depende de T043, T054)
- [X] T063 [US7] Validar pelo specs/003-confrontos/quickstart.md, passos 41 a 43 (US7-1 a US7-6) e revisar o código da US7 (Princípio V)

---

## Phase 10: User Story 8 - Alterar as cotações pelo painel (Priority: P2)

**Goal**: rotas do painel para porcentagens de vendedores e de clientes, porcentagem de campeonato,
cotação de confronto e teto.

**Independent Test**: quickstart passos 20 a 23 feitos pelas rotas, mais as recusas de permissão
(US8-2, US8-7 a US8-9).

- [X] T064 [P] [US8] Criar app/Services/AlcanceHierarquia.php: `garantir_dono(Usuarios $solicitante, ?int $usuarios_id): Usuarios` (nulo = o próprio; senão o próprio ou alguém da sub-hierarquia via `gerencia()`, senão `403`); `garantir_gestor_geral(Usuarios $solicitante)` (só Admin ou Supervisor, senão `403`); `garantir_alvo(Usuarios $solicitante, AlvoRegra $alvo, ?int $usuarios_id, ?int $clientes_id)` com as regras de FR-061 e FR-071 (Vendedores: dono válido e sem `clientes_id`; Clientes: só Admin/Supervisor e sem `usuarios_id`; Todos: só Admin/Supervisor, sem `usuarios_id` e sem `clientes_id`)
- [X] T065 [P] [US8] Criar app/Services/RegrasCotacao.php: `aplicar(array $atuais, ?array $valores, int|float|null $todos): array` (`valores` mescla e remove os iguais a 0; `todos` preenche os 324 códigos de `CodigosCotacao::regras()` ou remove todos se 0, research R-14); `diferencas_confronto(Confrontos $confronto, array $cotacoes_desejadas): array` (desejada − cotação do provedor; base 0 → `ValidationException` "A cotação {código} está indisponível no provedor.", FR-060)
- [X] T066 [P] [US8] Criar app/Http/Requests/RegrasCotacaoRequest.php: `tipo` `in:pre_jogo,ao_vivo` (obrigatório nas rotas de vendedores e clientes, via parâmetro do request); `valores` `array` com chaves em `CodigosCotacao::regras()` (closure); `todos` numérico; exatamente um entre `valores` e `todos` (`required_without`/`prohibits`); porcentagens `between:-100,100` com até 2 casas; no teto, valores `min:1` com até 2 casas (FR-034, FR-059); `alvo` `Rule::enum(AlvoRegra::class)`, `usuarios_id` e `clientes_id` `nullable|integer` com `exists`; mensagens em português
- [X] T067 [P] [US8] Criar app/Http/Requests/PorcentagensConfrontosRequest.php: `alvo` obrigatório (`AlvoRegra`); `usuarios_id` `nullable|integer|exists:usuarios,id`; `cotacoes` `required|array|min:1` com chaves em `CodigosCotacao::cotacoes()` e valores `numeric|gt:0` com até 2 casas; mensagens em português
- [X] T068 [US8] Criar app/Http/Controllers/PorcentagensVendedoresController.php (`porcentagens_vendedores.editar`): `show(Usuarios $usuario)` → `{"pre_jogo": {...}, "ao_vivo": {...}}`; `update` → `AlcanceHierarquia::garantir_dono()` e grava em `porcentagens_vendedores` ou `porcentagens_vendedores_ao_vivo` (`firstOrCreate` + `RegrasCotacao::aplicar`) (depende de T064–T066)
- [X] T069 [US8] Criar app/Http/Controllers/PorcentagensClientesController.php (`porcentagens_clientes.editar`, só Admin e Supervisor via `pode_usar`): `show` (query `clientes_id` opcional) e `update` (`clientes_id` vazio = regra geral; cliente inexistente → `404`) nas tabelas de pré-jogo ou ao vivo conforme `tipo` (depende de T065, T066)
- [X] T070 [US8] Criar app/Http/Controllers/PorcentagensCampeonatosController.php (`porcentagens_campeonatos.editar`): `show(Campeonatos)` lista as regras visíveis (Admin/Supervisor: todas; Gerente: alvo Vendedores dele e da sub-hierarquia); `update` → `AlcanceHierarquia::garantir_alvo()` e `withTrashed()->firstOrNew` por (`campeonatos_id`, `alvo`, `usuarios_id`), `restore()` se preciso, `RegrasCotacao::aplicar` (depende de T064–T066)
- [X] T071 [US8] Criar app/Http/Controllers/PorcentagensConfrontosController.php (`porcentagens_confrontos.editar`): `show(Confrontos)` com, por código, a cotação do provedor e as regras visíveis; `update` → `garantir_alvo()` e grava `RegrasCotacao::diferencas_confronto()` mescladas nos `valores` do registro do alvo/dono (FR-060) (depende de T064, T065, T067)
- [X] T072 [US8] Criar app/Http/Controllers/ConfrontosTetoCotacoesController.php (`confrontos_teto_cotacoes.editar`, só Admin e Supervisor): `show` e `update` no registro único (`RegrasCotacao::aplicar` com valores ≥ 1,00) (depende de T065, T066)
- [X] T073 [US8] Em routes/api.php, dentro de um grupo `['auth:api', 'garantir_acesso']` novo (comentário "gestão de confrontos no painel"), acrescentar as 10 rotas de porcentagens e teto de [contracts/api.md](contracts/api.md) com `->missing()` ("Usuário não encontrado.", "Campeonato não encontrado.", "Confronto não encontrado.")
- [X] T074 [US8] Validar pelo specs/003-confrontos/quickstart.md, passos 20 a 23 pelas rotas e as recusas US8-2, US8-7, US8-8 e US8-9 (SC-012, SC-014); revisar o código da US8 (Princípio V)

---

## Phase 11: User Story 10 - Ativar, favoritar e esconder pelo painel (Priority: P2)

**Goal**: listagens de campeonatos e confrontos no painel, ativar/desativar, favoritar e marcar não
permitidos.

**Independent Test**: quickstart passos 41 a 45 pelas rotas.

- [X] T075 [P] [US10] Criar app/Http/Resources/CampeonatosResource.php (`id`, `codigo_externo`, `nome`, `pais`, `bandeira`, `ativo`, `favorito`, `manual`, `nao_permitido`, datas) e app/Http/Resources/ConfrontosResource.php / app/Http/Resources/ConfrontosAoVivoResource.php (campos do data-model, `cotacoes` do provedor — permitido no painel, FR-068 —, `jogadores` quando carregados, `nao_permitido`; no ao vivo, `travado`)
- [X] T076 [P] [US10] Criar app/Http/Requests/NaoPermitidosRequest.php (item `campeonatos_id`/`confrontos_id` obrigatório e existente conforme a rota; `alvo` `Rule::enum(AlvoRegra::class)`; `usuarios_id` e `clientes_id` `nullable|integer|exists`) e app/Http/Resources/NaoPermitidosResource.php
- [X] T077 [US10] Criar app/Http/Controllers/CampeonatosController.php com `index` e `show` (`campeonatos.listar`; filtros `busca`, `ativo`, `favorito`, `manual`, `pais`; ordem por `nome`; `por_pagina` padrão 20), `alterar_situacao` (`campeonatos.alterar_situacao`; `{"ativo": bool}`) e `alterar_favorito` (`campeonatos.favoritar`; `{"favorito": bool}`), ambos só Admin e Supervisor via `pode_usar` (FR-068 a FR-070, FR-072) (depende de T075)
- [X] T078 [US10] Criar app/Http/Controllers/ConfrontosController.php com `index` (`confrontos.listar`; filtros `busca`, `campeonatos_id`, `ativo`, `manual`, `esporte`, `situacao`, `dia` + `fuso_horario`) , `show` (com jogadores) e `alterar_situacao` (`confrontos.alterar_situacao`; `{"ativo": bool}`, some também do ao vivo por FR-046a); e app/Http/Controllers/ConfrontosAoVivoController.php com `index` (`confrontos.listar`; jogos dentro da permanência, com `travado`) (depende de T075)
- [X] T079 [US10] Criar app/Http/Controllers/CampeonatosNaoPermitidosController.php, app/Http/Controllers/ConfrontosNaoPermitidosController.php e app/Http/Controllers/ConfrontosAoVivoNaoPermitidosController.php (`*_nao_permitidos.gerenciar`): `index` (Admin/Supervisor: todos; Gerente: alvo Vendedores dele e da sub-hierarquia; filtros `alvo`, `usuarios_id`, `clientes_id`, item); `store` → `AlcanceHierarquia::garantir_alvo()` (Vendedores sem `usuarios_id` = quem marca), `withTrashed()->firstOrNew`, `restore()` se excluído, `201` novo / `200` existente (FR-071); `destroy` → mesmo alcance, soft delete, `204` (depende de T064, T076)
- [X] T080 [US10] Em routes/api.php, no grupo do painel de T073, acrescentar: `PATCH campeonatos/{campeonato}/situacao`, `PATCH campeonatos/{campeonato}/favorito`, `apiResource('campeonatos')->only(['index', 'show'])`, `GET confrontos-ao-vivo`, `PATCH confrontos/{confronto}/situacao`, `apiResource('confrontos')->only(['index', 'show'])` e as 3 `apiResource` de não permitidos `->only(['index', 'store', 'destroy'])->parameters([... => 'registro'])`, com `->missing()` em português
- [X] T081 [US10] Validar pelo specs/003-confrontos/quickstart.md, passos 41 a 45 e US10-1 a US10-9 e revisar o código da US10 (Princípio V)

---

## Phase 12: User Story 11 - Configurações do visitante e dos vendedores (Priority: P2)

**Goal**: configurações por público, alteração em massa por alcance, vendedor novo com cópia de um
colega e remoção da tabela padrão dos clientes.

**Independent Test**: quickstart passos 46 a 51.

- [X] T082 [P] [US11] Criar app/Services/ConfiguracoesVendedores.php: `criar_para(Usuarios $vendedor): UsuariosConfiguracoes` (cópia de `UsuariosConfiguracoes::CAMPOS` de outro vendedor do mesmo gerente; senão de um vendedor da mesma supervisão; senão só `usuarios_id`, valores padrão das colunas); `alterar(Usuarios $solicitante, Usuarios $alvo, array $campos): int` (Gerente → vendedor dele; Supervisor → gerente ou vendedor dele; Admin → supervisor, gerente ou vendedor abaixo dele; fora disso `403`; vendedores = o alvo se for Vendedor, senão os da sub-hierarquia com papel Vendedor; cria as linhas que faltam; um `UPDATE ... WHERE usuarios_id IN (...)` só com os campos enviados; devolve a quantidade) (FR-075, FR-076, research R-16)
- [X] T083 [P] [US11] Criar app/Http/Requests/UsuariosConfiguracoesRequest.php (`usuarios_id` `required|integer|exists:usuarios,id`; `esportes_permitidos` `sometimes|array|min:1`, itens `string|max:50|distinct`; `apostar_outros_esportes` e `ao_vivo_habilitado` `sometimes|boolean`; `minuto_limite_ao_vivo` `sometimes|integer|between:1,130`; `cotacao_maxima_ao_vivo` `sometimes|numeric|min:1` com até 2 casas; pelo menos um campo de configuração) e app/Http/Requests/VisitantesConfiguracoesRequest.php (os três campos do visitante, obrigatórios) com mensagens em português (FR-078)
- [X] T084 [P] [US11] Criar app/Http/Resources/UsuariosConfiguracoesResource.php (`usuarios_id`, `nome`, campos de configuração; vendedor sem linha com os valores padrão das colunas)
- [X] T085 [US11] Criar app/Http/Controllers/UsuariosConfiguracoesController.php (`usuarios_configuracoes.editar`): `index` com `gerente_id` obrigatório (o próprio ou da sub-hierarquia, senão `404` como o `GarantirGerencia`), lista paginada dos vendedores; `update` → `ConfiguracoesVendedores::alterar()` e responde `{"vendedores_alterados": n}`; e app/Http/Controllers/VisitantesConfiguracoesController.php (`visitantes_configuracoes.editar`, só Admin e Supervisor): `show` e `update` no registro único (FR-077) (depende de T082–T084)
- [X] T086 [US11] ⚠️ Em app/Http/Controllers/UsuariosController.php, no `store`, dentro da transação existente e depois de `atribuir_permissoes_padrao`, chamar `app(ConfiguracoesVendedores::class)->criar_para($usuario)` quando a função for `Funcao::Vendedor`; não alterar o restante (FR-076, decidido na spec) (depende de T082)
- [X] T087 [US11] Em routes/api.php, no grupo do painel, acrescentar `GET`/`PATCH usuarios-configuracoes` e `GET`/`PUT visitantes-configuracoes`
- [X] T088 [US11] Criar database/migrations/2026_10_01_000018_remover_clientes_configuracoes_padrao.php: `up()` define os valores padrão das colunas de `clientes_configuracoes` (`realizar_aposta` true, `apostar_ao_vivo` true, `apostar_outros_esportes` true, `cancelar_aposta` false, `aceita_promocao` true, `bloquear_saque` false, `quantidade_minima_opcoes` 1, `quantidade_maxima_opcoes` 20, `valor_minimo_aposta` 2.00, `valor_maximo_aposta` 1000.00, `premio_maximo` 50000.00, `valor_maximo_diario` 5000.00, `valor_maximo_saque_diario` 5000.00, `quantidade_maxima_saques_diaria` 5, `odd_minima` 1.90, `odd_maxima` 30.00, `esportes_permitidos` `JSON_ARRAY('FUTEBOL','HOQUEI NO GELO','BAISEBOL')`) com `->change()` e apaga `clientes_configuracoes_padrao`; `down()` recria a tabela com as colunas da migration 2026_09_29_000004 e o registro com esses valores, e retira os padrões (FR-079, research R-17)
- [X] T089 [US11] ⚠️ Em app/Services/CadastroClientes.php, trocar a cópia do padrão por `$cliente->configuracoes()->create(['aceita_promocao' => $aceita_promocao])` (valores do banco) e remover o `use` de `ClientesConfiguracoesPadrao`; em database/factories/ClientesFactory.php, `configure()` passa a criar `configuracoes()->create([])`; em database/seeders/ClientesSeeder.php, remover o bloco que cria o padrão e o `use` (FR-079)
- [X] T090 [US11] ⚠️ Remover app/Http/Controllers/ClientesConfiguracoesPadraoController.php e app/Models/ClientesConfiguracoesPadrao.php; em routes/api.php, remover as 2 rotas `clientes-configuracoes-padrao` e o `use` do controller; em app/Enums/Funcao.php, retirar `clientes.editar_configuracoes_padrao` de `PERMISSOES_CLIENTES` e de `PERMISSOES_CLIENTES_RESTRITAS`; em app/Http/Requests/ClientesConfiguracoesRequest.php, ajustar só o comentário da classe ("do cliente ou as padrão" → "do cliente"); em database/seeders/ConfrontosSeeder.php, apagar a permissão `clientes.editar_configuracoes_padrao` (`Permission::where('name', ...)->delete()` após `forgetCachedPermissions`) (FR-079)
- [X] T091 [US11] Rodar `php artisan migrate` e `php artisan db:seed --class=ConfrontosSeeder`; validar pelo quickstart passos 46 a 51 (SC-016, SC-017) e revisar o código da US11 (Princípio V)

---

## Phase 13: User Story 9 - Alimentar um campeonato manualmente (Priority: P3)

**Goal**: cadastro manual de campeonatos e confrontos nas mesmas tabelas, sem provedor.

**Independent Test**: quickstart passos 37 a 40.

- [X] T092 [P] [US9] Criar app/Http/Requests/CampeonatosRequest.php: `nome` `required|string|max:150`; `pais` `required|string|max:100`; `bandeira` `nullable|string|max:255`; único entre manuais não excluídos por nome+país (closure, ignorando o próprio no update) (FR-063a)
- [X] T093 [P] [US9] Criar app/Http/Requests/ConfrontosRequest.php: `campeonatos_id` `required|integer` de campeonato com `manual = true` (closure); `time_casa`/`time_fora` `required|string|max:150`; `escudo_casa`/`escudo_fora` `nullable|string|max:255`; `esporte` `required|string|max:50`; `data_inicio` `required|date_format:Y-m-d H:i` futura no `fuso_horario` informado (`regex` de T041, padrão `-03:00`); `cotacoes` `required|array|min:1` com chaves em `CodigosCotacao::cotacoes()` e valores `numeric|min:1` com até 2 casas; no update, `situacao` `nullable|in:Aguardando,Adiado,Cancelado`; `prepareForValidation`/`validated` convertendo `data_inicio` para UTC (FR-063b, FR-063c)
- [X] T094 [US9] Em app/Http/Controllers/CampeonatosController.php, acrescentar `store` (`campeonatos.cadastrar`; cria com `manual = true`, `ativo = true`, `favorito = false`, `codigo_externo` nulo; `201`), `update` (`campeonatos.editar`) e `destroy` (`campeonatos.excluir`; soft delete do campeonato e dos confrontos dele em transação; `204`); `update` e `destroy` recusam com `422` "Só registros manuais podem ser alterados." quando `manual` é falso (FR-063, FR-064); as três só Admin e Supervisor via `pode_usar` (depende de T077, T092)
- [X] T095 [US9] Em app/Http/Controllers/ConfrontosController.php, acrescentar `store` (`confrontos.cadastrar`; `manual = true`, `situacao = 'Aguardando'`, `ativo = true`, `cotacoes` só com códigos ≠ 0, `quantidade_cotacoes` calculada; `201`), `update` (`confrontos.editar`; recalcula `quantidade_cotacoes`) e `destroy` (`confrontos.excluir`; `204`), recusando registros do provedor com `422` (FR-063b, FR-063c, FR-064) (depende de T078, T093)
- [X] T096 [US9] Em routes/api.php, trocar `apiResource('campeonatos')->only(['index', 'show'])` e `apiResource('confrontos')->only(['index', 'show'])` (T080) pelos `apiResource` completos
- [X] T097 [US9] Validar pelo specs/003-confrontos/quickstart.md, passos 37 a 40 (SC-013, SC-015) e revisar o código da US9 (Princípio V)

---

## Phase 14: Polish & Cross-Cutting Concerns

- [X] T098 Regenerar docs/postman/wssports_api.postman_collection.json substituindo o arquivo: nova pasta "Confrontos" com a rota pública (sem autenticação; exemplos com `tipo`, `dia`, `busca`, `fuso_horario`) e as 37 rotas do painel; remover "Configurações padrão" e "Alterar configurações padrão"; variáveis novas `campeonato_id`, `confronto_id`, `registro_id`; manter `base_url`, `token` e `token_cliente` (constituição)
- [X] T099 [P] Atualizar os artefatos da spec 002 para o funcionamento sem a tabela padrão (FR-080): specs/002-clientes/spec.md (Clarification com a decisão e FR-049 a FR-051), specs/002-clientes/plan.md, specs/002-clientes/research.md (R-03, R-15), specs/002-clientes/data-model.md (padrões nas colunas e tabela removida), specs/002-clientes/contracts/api.md (rotas removidas, 22 no painel), specs/002-clientes/quickstart.md e specs/002-clientes/tasks.md (nota da revisão)
- [X] T100 Rodar `vendor/bin/pint` só nos arquivos criados ou alterados por esta feature e revisar o diff (Princípio IV: nenhum arquivo fora da lista do plano)
- [X] T101 Rodar o [quickstart.md](quickstart.md) completo (passos 1 a 51) com `php artisan schedule:work` ligado e registrar o resultado
- [X] T102 Revisão final de legibilidade de todos os arquivos criados ou alterados listados em specs/003-confrontos/plan.md (Princípio V): nomes em `snake_case`, duplicação entre `ListagemConfrontos::pre_jogo` e `ao_vivo`, métodos longos em `ImportacaoCampeonatos`, `ImportacaoConfrontos` e `ImportacaoCotacoes`, comentários desatualizados; informar se houve refatoração

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sem dependências
- **Foundational (Phase 2)**: depende do Setup — BLOQUEIA todas as user stories
- **User stories (Phases 3–13)**: dependem da Foundational
- **Polish (Phase 14)**: depende das user stories desejadas

### User Story Dependencies

- **US1 (P1)**: só Foundational
- **US2 (P1)**: Foundational; usa dados da US1 para validar (ou registros no banco)
- **US3 (P1)**: depende da US2 (`CalculoCotacoes`)
- **US4 (P1)**: Foundational; reaproveita `ValidacaoCargaProvedor` da US1 (T034)
- **US5 (P2)**: depende de US2, US3 e US4
- **US6 (P2)**: depende da US4 (dados do ao vivo)
- **US7 (P2)**: depende de US2 e US5 (`ListagemConfrontos`)
- **US8 (P2)**: Foundational; para validar o efeito na listagem, depende de US3
- **US10 (P2)**: usa `AlcanceHierarquia` da US8 (T064)
- **US11 (P2)**: Foundational; a leitura das configurações na listagem já existe desde a US2/US5
- **US9 (P3)**: depende da US10 (`CampeonatosController` e `ConfrontosController`)

### Arquivos compartilhados (tarefas em sequência, nunca em paralelo)

- routes/api.php: T045 → T073 → T080 → T087 → T090 → T096
- routes/console.php: T037 → T052 → T060
- app/Services/ListagemConfrontos.php: T043 → T054 → T062
- app/Services/CalculoCotacoes.php: T042 → T047 → T055
- app/Services/ValidacaoCargaProvedor.php: T034 → T049
- app/Enums/Funcao.php: T027 → T090
- app/Http/Controllers/CampeonatosController.php e ConfrontosController.php: T077/T078 → T094/T095

### Parallel Opportunities

- Setup: T002 e T003 em paralelo
- Foundational: T004–T007 em paralelo; T008–T019 (migrations) em paralelo; T020–T026 (models) em paralelo depois das migrations
- US2: T039 e T041 em paralelo
- US8: T064–T067 em paralelo
- US10: T075 e T076 em paralelo
- US11: T082–T084 em paralelo
- US9: T092 e T093 em paralelo
- Polish: T099 em paralelo com T098

---

## Parallel Example: Foundational

```bash
# Migrations (arquivos diferentes, sem dependências entre si):
Task: "T008 create_configuracoes_table"
Task: "T011 create_campeonatos_table"
Task: "T014 create_confrontos_ao_vivo_table"
Task: "T019 create_*_nao_permitidos_table"

# Models, depois das migrations:
Task: "T020 Configuracoes"
Task: "T022 Campeonatos"
Task: "T024 ConfrontosAoVivo"
```

## Parallel Example: User Story 8

```bash
Task: "T064 AlcanceHierarquia"
Task: "T065 RegrasCotacao"
Task: "T066 RegrasCotacaoRequest"
Task: "T067 PorcentagensConfrontosRequest"
```

---

## Implementation Strategy

### MVP First (US1 + US2)

1. Phase 1 (Setup) e Phase 2 (Foundational)
2. Phase 3: US1 — jogos gravados pela carga
3. Phase 4: US2 — vitrine do pré-jogo
4. **PARAR e VALIDAR**: quickstart passos 1 a 19

### Incremental Delivery

1. MVP (US1 + US2) → margem (US3) → ao vivo (US4 + US5) → conferência (US6)
2. Restrições na vitrine (US7) → painel de cotações (US8) → painel de campeonatos e não permitidos
   (US10) → configurações e remoção do padrão dos clientes (US11)
3. Cadastro manual (US9) → Polish (Postman, documentos da spec 002, revisão final)

### Antes de começar

- Os arquivos existentes da lista do [plan.md](plan.md) foram autorizados em 2026-10-01
  (Princípio IV); só eles podem ser alterados.

---

## Notes

- [P] = arquivos diferentes, sem dependências pendentes
- [Story] liga a tarefa à user story da spec
- Sem testes automatizados: cada fase termina com a validação manual do quickstart e a revisão do
  Princípio V
- Parar em qualquer checkpoint para validar a story de forma independente
- Revisão 2026-10-08 (alteração manual): a ordenação da listagem pública (T043 e ao vivo) passou a
  incluir o país do campeonato depois do favorito, em app/Services/ListagemConfrontos.php
  (Clarification 2026-10-08 da spec)
- Revisão 2026-10-08 (alteração manual): app/Services/ValidacaoCargaProvedor.php (T049) retira o
  sufixo ` AO VIVO` do `tipo_esporte` do ao vivo, e app/Services/RegrasExibicao.php compara os
  esportes permitidos sem diferenciar acentos (FR-014 e FR-041; Clarifications 2026-10-08)
