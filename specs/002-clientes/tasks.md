---

description: "Lista de tarefas da feature Clientes (Apostadores)"
---

# Tasks: Clientes (Apostadores)

**Input**: Documentos de design em `specs/002-clientes/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md),
[data-model.md](data-model.md), [contracts/api.md](contracts/api.md), [quickstart.md](quickstart.md)

**Tests**: NÃO há tarefas de teste — a constituição proíbe testes automatizados. Cada user story é
validada manualmente pelos passos do [quickstart.md](quickstart.md).

**Organization**: tarefas agrupadas por user story, em ordem de prioridade (P1 → P2 → P3).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: pode rodar em paralelo (arquivos diferentes, sem dependências pendentes)
- **[Story]**: user story da tarefa (US1 a US10)
- Caminhos relativos à raiz do repositório

## Regras que valem para TODAS as tarefas

- **Princípio I**: métodos, variáveis, parâmetros, chaves JSON, rotas e colunas em `snake_case`;
  classes em `PascalCase` (sem acento); métodos exigidos pelo framework/pacotes mantêm o nome
  (`getJWTIdentifier`, `getJWTCustomClaims`, `middleware`, `rules`, `messages`, `authorize`,
  `prepareForValidation`, `toArray`, `definition`, `casts`, `up`, `down`, `run`, `handle`,
  `render`, `uniqueId`). Permissões `<recurso>.<acao>` (v1.11.0). Tabelas com prefixo `clientes_`
  (v1.9.0). `ddi`, `email` e `password` pelas exceções v1.12.0/v1.13.0.
- **Enums (v1.13.0, research R-05)**: casos em `PascalCase` com acento; valores gravados em
  português com a primeira letra maiúscula e acentos. Arquivos salvos em UTF-8.
- **Princípio II**: comentários no código e mensagens de erro/validação em português.
- **Princípio IV**: alterar só os arquivos e trechos indicados. Arquivos existentes marcados com
  ⚠️ já tiveram a alteração autorizada (plan.md, 2026-09-29). NÃO alterar `app/Enums/Funcao.php`,
  `app/Models/Usuarios.php`, `app/Http/Middleware/GarantirAcesso.php`,
  `app/Http/Controllers/PermissoesUsuariosController.php`, `bootstrap/app.php`,
  `config/queue.php` nem as migrations existentes.
- **Princípio V**: ao concluir cada tarefa, revisar em conjunto o código alterado e informar se
  houve ou não refatoração.
- **Banco**: só `php artisan migrate` e `php artisan db:seed --class=ClientesSeeder`. NUNCA
  `migrate:fresh` nem `migrate:refresh` (research R-20).
- **Paginação**: `por_pagina` de 1 a 100, padrão 20.
- **Dinheiro**: nunca `float` em cálculo de saldo; valores monetários como texto com 2 casas.
- **Padrões da spec 001**: FormRequests com `messages()` em português; controllers do painel com
  `HasMiddleware`; erros `{"message": "..."}`.

---

## Phase 1: Setup (Shared Infrastructure)

- [X] T001 ⚠️ Em config/auth.php, acrescentar o guard `'clientes' => ['driver' => 'jwt', 'provider' => 'clientes']` e o provider `'clientes' => ['driver' => 'eloquent', 'model' => App\Models\Clientes::class]`, com comentário em português; NÃO alterar os guards `web`/`api` nem o provider `users` (research R-01)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: enums, tabelas, models, serviço de saldo, middlewares, resources base e eventos

**⚠️ CRITICAL**: nenhuma user story começa antes desta fase terminar

### Enums (app/Enums, backed `string`)

- [X] T002 [P] Criar app/Enums/Genero.php: `Masculino = 'Masculino'`, `Feminino = 'Feminino'`, `Outro = 'Outro'`, `NãoInformado = 'Não informado'`
- [X] T003 [P] Criar app/Enums/Carteira.php: `Saldo = 'Saldo'`, `PromoçãoEsportes = 'Promoção esportes'`, `PromoçãoCassino = 'Promoção cassino'` e método `coluna(): string` (`saldo`, `saldo_promocao_esportes`, `saldo_promocao_cassino`)
- [X] T004 [P] Criar app/Enums/TipoTransacao.php: `Crédito = 'Crédito'`, `Débito = 'Débito'`
- [X] T005 [P] Criar app/Enums/OrigemTransacao.php: `AjusteManual = 'Ajuste manual'`, `Promoção = 'Promoção'`, `Aposta = 'Aposta'`, `Prêmio = 'Prêmio'`, `Estorno = 'Estorno'`
- [X] T006 [P] Criar app/Enums/ModalidadePromocao.php: `Esportes = 'Esportes'`, `Cassino = 'Cassino'` e método `carteira(): Carteira` (Esportes → `PromoçãoEsportes`, Cassino → `PromoçãoCassino`)
- [X] T007 [P] Criar app/Enums/CategoriaPromocao.php: `PrimeiroCadastro = 'Primeiro cadastro'`, `PrimeiroDepósito = 'Primeiro depósito'`, `QualquerDepósito = 'Qualquer depósito'`, `Indicação = 'Indicação'` e método `aceita_percentual(): bool` (true só nas duas de depósito)
- [X] T008 [P] Criar app/Enums/TipoGanho.php (`Fixo`, `Percentual`), app/Enums/SituacaoEstorno.php (`EmAndamento = 'Em andamento'`, `Concluído = 'Concluído'`), app/Enums/TipoMeioPagamento.php (`Pix = 'Pix'`, `TransferênciaBancária = 'Transferência bancária'`), app/Enums/TipoChavePix.php (`Cpf = 'CPF'`, `Cnpj = 'CNPJ'`, `Email = 'E-mail'`, `Telefone = 'Telefone'`, `ChaveAleatória = 'Chave aleatória'`) e app/Enums/TipoConta.php (`Corrente`, `Poupança`)
- [X] T009 [P] ⚠️ Em app/Enums/Funcao.php, acrescentar `PERMISSOES_CLIENTES` (as 10 permissões `clientes.*` e `clientes_promocoes.*`), `PERMISSOES_CLIENTES_RESTRITAS` (`clientes.excluir`, `clientes.restaurar`, `clientes.editar_configuracoes_padrao`, `clientes_promocoes.estornar`), incluir as permissões de clientes em `permissoes_padrao()` (Admin e Supervisor: 10; Gerente: 6; Vendedor: nenhuma), `pode_usar(string): bool` e o estático `usuario_pode(Usuarios, string): bool` (permissão direta E função permitida); em database/seeders/PapeisPermissoesSeeder.php criar também as permissões de clientes (research R-03, Princípio VI — revisado: substitui o enum `PermissaoCliente` da primeira versão)

### Migrations (todas com `timestamps()` e `softDeletes()`)

- [X] T010 [P] Criar database/migrations/2026_09_29_000001_create_clientes_table.php: `id()`; `string('nome', 150)`; `string('ddi', 3)->default('55')`; `string('telefone', 40)`; `string('email', 150)->nullable()->unique()`; `string('password')`; `string('cpf', 40)->nullable()->unique()`; `date('data_nascimento')`; `string('genero', 20)`; `string('codigo_afiliado', 50)->nullable()` (comentário: sem FK, afiliados ainda não existem — constituição v1.10.0); `boolean('ativo')->default(true)`; `decimal('saldo', 15, 2)->default(0)`; `decimal('saldo_promocao_esportes', 15, 2)->default(0)`; `decimal('saldo_promocao_cassino', 15, 2)->default(0)`; `timestamp('tokens_validos_desde')->nullable()`; único (`ddi`, `telefone`); índices `nome` e `created_at`
- [X] T011 [P] Criar database/migrations/2026_09_29_000002_create_clientes_transacoes_table.php: `id()`; `foreignId('clientes_id')->constrained('clientes')`; `string('carteira', 30)`; `string('tipo', 10)`; `string('origem', 30)`; `unsignedBigInteger('referencia_id')->nullable()` (comentário: sem FK — promoção ou, no futuro, aposta); `decimal('valor', 15, 2)`; `decimal('saldo_anterior', 15, 2)`; `decimal('saldo_posterior', 15, 2)`; `foreignId('usuarios_id')->nullable()->constrained('usuarios')`; `string('observacao')->nullable()`; índices (`clientes_id`, `created_at`), (`clientes_id`, `carteira`) e (`origem`, `referencia_id`)
- [X] T012 [P] Criar database/migrations/2026_09_29_000003_create_clientes_configuracoes_table.php: `id()`; `foreignId('clientes_id')->unique()->constrained('clientes')`; `boolean` `realizar_aposta`, `apostar_ao_vivo`, `apostar_outros_esportes`, `cancelar_aposta`, `aceita_promocao`, `bloquear_saque`; `unsignedSmallInteger` `quantidade_minima_opcoes`, `quantidade_maxima_opcoes`, `quantidade_maxima_saques_diaria`; `decimal(15,2)` `valor_minimo_aposta`, `valor_maximo_aposta`, `premio_maximo`, `valor_maximo_diario`, `valor_maximo_saque_diario`; `decimal(8,2)` `odd_minima`, `odd_maxima`; `json('esportes_permitidos')` (comentário: sem FK)
- [X] T013 [P] Criar database/migrations/2026_09_29_000004_create_clientes_configuracoes_padrao_table.php com as mesmas colunas de T012, sem `clientes_id`
- [X] T014 [P] Criar database/migrations/2026_09_29_000005_create_clientes_meios_pagamento_table.php: `id()`; `foreignId('clientes_id')->constrained('clientes')`; `string('tipo', 30)`; `boolean('principal')->default(false)`; nullable: `string('pix_nome_titular', 150)`, `string('pix_tipo_chave', 20)`, `string('pix_chave', 150)`, `string('banco_codigo', 3)`, `string('banco_nome', 100)`, `string('agencia', 10)`, `string('conta', 20)`, `string('conta_digito', 2)`, `string('conta_tipo', 20)`, `string('titular_nome', 150)`, `string('titular_documento', 14)`; índice (`clientes_id`, `principal`)
- [X] T015 [P] Criar database/migrations/2026_09_29_000006_create_clientes_promocoes_table.php: `id()`; `string('nome', 150)`; `text('descricao')->nullable()`; `string('modalidade', 20)`; `string('categoria', 30)`; `string('tipo_ganho', 20)`; `decimal('valor', 15, 2)`; `unsignedSmallInteger('rollover')->default(0)`; `decimal('valor_minimo_aposta', 15, 2)`; `decimal('valor_maximo_aposta', 15, 2)`; `decimal('valor_maximo_deposito', 15, 2)->nullable()`; `decimal('valor_maximo_conversao', 15, 2)`; `decimal('odd_minima_aposta_simples', 8, 2)`; `decimal('odd_minima_aposta_multipla', 8, 2)`; `dateTime('data_inicio')`; `dateTime('data_fim')->nullable()`; `boolean('ativa')->default(true)`; `string('estorno_situacao', 20)->nullable()`; `string('estorno_motivo')->nullable()`; `foreignId('estorno_usuarios_id')->nullable()->constrained('usuarios')`; `dateTime('estorno_iniciado_em')->nullable()`; `dateTime('estorno_concluido_em')->nullable()`; `unsignedInteger` `estorno_total_clientes` e `estorno_clientes_processados` `default(0)`; `decimal('estorno_valor_total', 15, 2)->default(0)`; índice (`categoria`, `modalidade`, `ativa`)
- [X] T016 [P] Criar database/migrations/2026_09_29_000007_create_clientes_codigos_recuperacao_table.php: `id()`; `foreignId('clientes_id')->constrained('clientes')`; `string('codigo')` (hash); `unsignedTinyInteger('tentativas')->default(0)`; `dateTime('expira_em')`; `dateTime('usado_em')->nullable()`; `dateTime('invalidado_em')->nullable()`

### Models (todos com `SoftDeletes`)

- [X] T017 Criar app/Models/Clientes.php: extends `Illuminate\Foundation\Auth\User`, implements `JWTSubject`; `HasFactory`, `SoftDeletes`; `$fillable` = `nome`, `ddi`, `telefone`, `email`, `password`, `cpf`, `data_nascimento`, `genero`, `codigo_afiliado`, `ativo` (NUNCA saldos nem `tokens_validos_desde`); `$hidden` = `password`; `casts()`: `password => 'hashed'`, `data_nascimento => 'date'`, `genero => Genero::class`, `ativo => 'boolean'`, saldos `'decimal:2'`, `tokens_validos_desde => 'datetime'`; JWT (`getKey()`, `[]`); relações `configuracoes()` (hasOne), `transacoes()`, `meios_pagamento()`, `codigos_recuperacao()` (hasMany); `pode_acessar(): bool` (ativo e não excluído); `invalidar_tokens(): void` (`forceFill(['tokens_validos_desde' => now()])->save()`) (depende de T002, T010)
- [X] T018 [P] Criar app/Models/ClientesTransacoes.php: `$fillable` com as colunas de T011; casts `carteira => Carteira::class`, `tipo => TipoTransacao::class`, `origem => OrigemTransacao::class`, valores `'decimal:2'`; relações `cliente()` e `autor()` (`Usuarios`, `usuarios_id`); escopo `scopeExtrato($query, array $filtros)` (dias inteiros de `data_inicial`/`data_final`, `carteira`, ordem `created_at` desc e `id` desc) (depende de T003–T005, T011)
- [X] T019 [P] Criar app/Models/ClientesConfiguracoes.php: constante `CAMPOS` com os 17 campos de configuração de T012 (sem `clientes_id`); `$fillable = CAMPOS + clientes_id`; casts booleanos, `'decimal:2'` nos valores e odds, inteiros, `esportes_permitidos => 'array'`; relação `cliente()` (depende de T012)
- [X] T020 [P] Criar app/Models/ClientesConfiguracoesPadrao.php: `$fillable = ClientesConfiguracoes::CAMPOS`, mesmos casts; estático `atual(): self` (`firstOrFail`) (depende de T013, T019)
- [X] T021 [P] Criar app/Models/ClientesMeiosPagamento.php: `$fillable` com as colunas de T014 exceto `clientes_id` e `principal`; casts `tipo => TipoMeioPagamento::class`, `pix_tipo_chave => TipoChavePix::class`, `conta_tipo => TipoConta::class`, `principal => 'boolean'`; relação `cliente()` (depende de T008, T014)
- [X] T022 [P] Criar app/Models/ClientesPromocoes.php: `$fillable` com os campos cadastráveis (sem os `estorno_*`); casts de enum (`ModalidadePromocao`, `CategoriaPromocao`, `TipoGanho`, `estorno_situacao => SituacaoEstorno::class`), `'decimal:2'`, datas, `ativa => 'boolean'`; relação `autor_estorno()` (`Usuarios`, `estorno_usuarios_id`); escopo `scopeVigentes` (`ativa`, `estorno_situacao` nula, `data_inicio <= now()`, `data_fim` nula ou `>= now()`); métodos `vigente(): bool`, `aplicada(): bool` (existe transação `OrigemTransacao::Promoção` com `referencia_id` = id) e `estornada(): bool` (`estorno_situacao` não nula) (depende de T005–T008, T015)
- [X] T023 [P] Criar app/Models/ClientesCodigosRecuperacao.php: `$fillable` com as colunas de T016; casts de data; `valido(): bool` (`usado_em` e `invalidado_em` nulos e `expira_em` futuro); relação `cliente()` (depende de T016)

### Serviços, regras e infraestrutura

- [X] T024 [P] Criar app/Rules/CpfValido.php e app/Rules/CnpjValido.php (`ValidationRule`): recebem só dígitos; 11/14 dígitos, não todos iguais, dígitos verificadores corretos; mensagens "O CPF informado é inválido." / "O CNPJ informado é inválido." (research R-11)
- [X] T025 [P] Criar app/Exceptions/SaldoInsuficienteException.php com `render()` que responde `422 {"message": "Saldo insuficiente."}`
- [X] T026 Criar app/Services/SaldoClientes.php com `creditar()` e `debitar()` na assinatura do contrato e um método privado comum que, em `DB::transaction`: relê o cliente com `lockForUpdate()` (inclusive excluídos, `withTrashed`); converte saldo e valor de texto decimal para centavos inteiros sem `float` (`para_centavos(string): int`, `para_decimal(int): string`); recusa valor ≤ 0; no débito lança `SaldoInsuficienteException` se valor > saldo; grava em `$carteira->coluna()` com `forceFill`; cria a `ClientesTransacoes` com `saldo_anterior` e `saldo_posterior`; retorna a transação. Comentário explicando o bloqueio (FR-040 a FR-047, research R-04) (depende de T017, T018, T025)
- [X] T027 [P] Criar app/Http/Middleware/GarantirAcessoCliente.php: se `! $request->user('clientes')?->pode_acessar()` → `403 {"message": "Cliente sem permissão de acesso."}`; se `tokens_validos_desde` existe e o `iat` do token (`auth('clientes')->payload()->get('iat')`) é anterior → `401 {"message": "Não autenticado."}` (research R-02) (depende de T017)
- [X] T028 [P] Criar o trait app/Http/Controllers/Concerns/GarantirPermissaoCliente.php com o estático `permissao_cliente(string $permissao, array $only = [], array $except = []): Middleware`, cuja closure responde `403 {"message": "Você não tem permissão para esta ação."}` quando `! Funcao::usuario_pode($request->user(), $permissao)` (research R-03) (depende de T009)
- [X] T029 [P] Criar app/Http/Resources/ClientesResource.php com `id`, `nome`, `ddi`, `telefone`, `email`, `cpf`, `data_nascimento` (Y-m-d), `genero`, `codigo_afiliado`, `aceita_promocao` (de `configuracoes`), `ativo`, os 3 saldos, `created_at`, `updated_at` e `deleted_at` só quando excluído; NUNCA `password` nem `tokens_validos_desde`. Em excluídos, `telefone`/`cpf`/`email` sem o sufixo `_deleted_<timestamp>`. Se a requisição vier do painel (`$request->user() instanceof Usuarios`) sem `clientes.ver_dados_completos`, mascarar: CPF `***.456.789-**`; telefone `(11) *****-7777` (DDI 55) ou só os 4 últimos dígitos; e-mail com a primeira letra e o domínio (`m***@mail.com`) (research R-12, FR-057) (depende de T009, T017)
- [X] T030 [P] Criar app/Http/Resources/ClientesTransacoesResource.php com `id`, `carteira`, `tipo`, `origem`, `referencia_id`, `valor`, `saldo_anterior`, `saldo_posterior`, `observacao`, `created_at` e `autor` (`{id, nome}` ou `null`) SÓ quando a requisição vier do painel (depende de T018)
- [X] T031 [P] Criar app/Http/Resources/ClientesConfiguracoesResource.php com os 17 campos de `ClientesConfiguracoes::CAMPOS` e `updated_at` (serve às configurações do cliente e às padrão) (depende de T019)
- [X] T032 [P] Criar os eventos app/Events/ClienteCadastrado.php (`Clientes $cliente`) e app/Events/CodigoRecuperacaoGerado.php (`Clientes $cliente`, `string $codigo`), com `Dispatchable` e `ShouldDispatchAfterCommit` (research R-14) (depende de T017)
- [X] T033 Criar app/Listeners/RegistrarMensagemWhatsapp.php com `handle(ClienteCadastrado|CodigoRecuperacaoGerado $evento)`: monta o texto (boas-vindas com o nome; ou "Seu código de recuperação é XXXXXX, válido por 15 minutos") e grava com `Log::info('WhatsApp (envio pendente da spec de WhatsApp)', [...])` com DDI e telefone; tudo em `try/catch` que só registra o erro (FR-013, FR-027) (depende de T032)
- [X] T034 [P] Criar database/factories/ClientesFactory.php: `nome`, `ddi` `'55'`, `telefone` único de 11 dígitos, `email` único, `cpf` único e VÁLIDO (9 dígitos + verificadores calculados), `password` (hash de `'senha123'`), `data_nascimento` entre 18 e 70 anos atrás, `genero` aleatório de `Genero`, `ativo` `true`; estados `inativo()`, `sem_cpf()`, `sem_email()`; `afterCreating` cria `configuracoes` copiando `ClientesConfiguracoesPadrao::atual()` (depende de T017, T020)
- [X] T035 Criar database/seeders/ClientesSeeder.php: limpar o cache do spatie; `firstOrCreate` das `Funcao::PERMISSOES_CLIENTES` no guard `api`; dar a cada usuário já existente as permissões de clientes de `permissoes_padrao()` da sua função; `firstOrCreate` do registro único de `clientes_configuracoes_padrao` com os valores do data-model (`realizar_aposta`/`apostar_ao_vivo`/`apostar_outros_esportes`/`aceita_promocao` = `true`, `cancelar_aposta`/`bloquear_saque` = `false`, opções 1 a 20, aposta `2.00` a `1000.00`, prêmio `50000.00`, diário `5000.00`, saque diário `5000.00` e 5 saques, odd `1.90` a `30.00`, esportes `["FUTEBOL", "HOQUEI NO GELO", "BAISEBOL"]`); 5 clientes de exemplo pela factory (1 inativo, 1 sem CPF) (FR-050, FR-062) (depende de T009, T020, T034)
- [X] T036 ⚠️ Em database/seeders/DatabaseSeeder.php, acrescentar SOMENTE `$this->call(ClientesSeeder::class);` ao final de `run()` (depende de T035)
- [X] T037 Rodar `php artisan migrate` e `php artisan db:seed --class=ClientesSeeder` e conferir: 7 tabelas novas; 1 registro de configurações padrão; 10 permissões dadas ao Admin; 5 clientes com configurações; tabelas existentes intactas (depende de T010–T016, T035)

- [X] T038 ⚠️ Em routes/api.php, criar a estrutura vazia das rotas de clientes, sem alterar as rotas da spec 001: o grupo `Route::prefix('area-cliente')` com o subgrupo autenticado `Route::middleware(['auth:clientes', GarantirAcessoCliente::class])` e o grupo do painel `Route::middleware(['auth:api', 'garantir_acesso'])` para clientes, cada um com um comentário em português; as user stories só acrescentam rotas dentro deles (depende de T027)

**Checkpoint**: estrutura pronta — as user stories podem começar

---

## Phase 3: User Story 1 - Cadastro público do cliente (Priority: P1) 🎯 MVP

**Goal**: visitante cria a conta; saldos zerados ou com promoção de primeiro cadastro; configurações
copiadas do padrão; evento de boas-vindas; token devolvido

**Independent Test**: quickstart passos 1 a 7

- [X] T039 [P] [US1] Criar app/Http/Requests/StoreClientesRequest.php: `prepareForValidation()` normaliza `telefone`, `cpf` e `ddi` para só dígitos, `email` para minúsculas sem espaços, strings vazias de `cpf`/`email` para `null`, `ddi` padrão `'55'` e `aceita_promocao` padrão `true`; `rules()`: `nome` required string max:150; `ddi` digits_between:1,3; `telefone` required, 10–11 dígitos se `ddi = 55` e 4–14 nos demais, único por (`ddi`, `telefone`); `email` nullable `email` max:150 único; `cpf` nullable `new CpfValido` único; `password` required `confirmed` `Password::min(8)->letters()->numbers()`; `data_nascimento` required date `before_or_equal` hoje − 18 anos; `genero` required `Rule::enum(Genero::class)`; `codigo_afiliado` nullable string max:50; `aceita_promocao` boolean; `messages()` em português ("O telefone já está cadastrado.", "O CPF já está cadastrado.", "O e-mail já está cadastrado.", "É preciso ter 18 anos ou mais.", "A senha deve ter no mínimo 8 caracteres, com letras e números.", "A confirmação da senha não confere.") (FR-003 a FR-010)
- [X] T040 [US1] Criar app/Services/CadastroClientes.php com `cadastrar(array $dados): Clientes`: em `DB::transaction`, cria o cliente (ativo, saldos 0), cria `configuracoes` copiando `ClientesConfiguracoesPadrao::atual()` (campos de `CAMPOS`) com `aceita_promocao` do cadastro e, se marcado, para cada `ClientesPromocoes::vigentes()->where('categoria', CategoriaPromocao::PrimeiroCadastro)` chama `SaldoClientes::creditar()` em `$promocao->modalidade->carteira()`, origem `Promoção`, `referencia_id` = id, observação com o nome da promoção; dispara `ClienteCadastrado`. Violação de índice único na corrida entre cadastros vira `422` com a mensagem de duplicidade (FR-011, FR-013, FR-075, FR-076, research R-15) (depende de T026, T032)
- [X] T041 [US1] Criar app/Http/Controllers/AreaClienteCadastroController.php com `store(StoreClientesRequest, CadastroClientes)`: cadastra, gera o token com `auth('clientes')->login($cliente)` e responde `201 {"cliente": ClientesResource, "token", "tipo": "bearer", "expira_em"}` (depende de T029, T039, T040)
- [X] T042 [US1] ⚠️ Em routes/api.php, no grupo `area-cliente` (fora do subgrupo autenticado), acrescentar `Route::post('cadastro', ...)->middleware('throttle:5,1')` e o `use` do controller; rotas da spec 001 intactas (FR-001a) (depende de T041)

**Checkpoint**: cadastro validado (quickstart 1–7, com promoção criada direto no banco até a US8)

---

## Phase 4: User Story 2 - Login do cliente (Priority: P1)

**Goal**: token de cliente de 60 min, renovação, logout, limite de tentativas, bloqueio de
inativos e separação entre guards

**Independent Test**: quickstart passos 8 a 10 e 36

- [X] T043 [P] [US2] Criar app/Http/Requests/LoginClienteRequest.php seguindo o `LoginRequest` da spec 001: normaliza `telefone`/`ddi` (padrão `'55'`); `rules()` `telefone` required, `password` required string; `garantir_limite_tentativas()` (429 "Muitas tentativas. Tente novamente em N segundos."), `registrar_tentativa_falha()` e `limpar_tentativas()` com 5 tentativas em 60 s e chave `ddi.telefone|ip` (FR-020)
- [X] T044 [US2] Criar app/Http/Controllers/AreaClienteAutenticacaoController.php: `login()` (limite; `auth('clientes')->attempt(['ddi', 'telefone', 'password'])`; falha → registra e `401 "Telefone ou senha inválidos."`; `! pode_acessar()` → logout e `403 "Cliente sem permissão de acesso."`; sucesso → limpa e `200 {"token", "tipo": "bearer", "expira_em"}`), `refresh()` e `logout()` (`204`) (FR-014 a FR-019) (depende de T043)
- [X] T045 [US2] ⚠️ Em routes/api.php, no grupo `area-cliente`, acrescentar `auth/login` e, no subgrupo autenticado, `auth/refresh` e `auth/logout` (depende de T027, T044)

**Checkpoint**: login validado; token de cliente recusado no painel e vice-versa

---

## Phase 5: User Story 3 - Recuperar senha (Priority: P1)

**Goal**: código de 6 dígitos (log), 15 min, uso único, 5 tentativas; resposta genérica; tokens
antigos invalidados

**Independent Test**: quickstart passos 11 a 14

- [X] T046 [P] [US3] Criar app/Http/Requests/RecuperarSenhaClienteRequest.php (`ddi` padrão `'55'`, `telefone` required, só dígitos) e app/Http/Requests/RedefinirSenhaClienteRequest.php (`ddi`, `telefone`, `codigo` required `digits:6`, `password` required `confirmed` `Password::min(8)->letters()->numbers()`), mensagens em português
- [X] T047 [US3] Criar app/Http/Controllers/AreaClienteRecuperacaoSenhaController.php: `solicitar()` — 1 pedido/min por `ddi.telefone` com `RateLimiter` (429 com o tempo); se o cliente `pode_acessar()`, invalida os códigos válidos anteriores, gera `random_int(0, 999999)` com 6 dígitos (zeros à esquerda), grava o hash com `expira_em = now()->addMinutes(15)` e dispara `CodigoRecuperacaoGerado`; responde SEMPRE `200 {"message": "Se o telefone estiver cadastrado, enviaremos um código."}`. `redefinir()` — busca o código válido mais recente; ausente ou cliente sem acesso → `422 "Código inválido ou expirado."`; errado → incrementa `tentativas` e, na 5ª, preenche `invalidado_em` (`422`); certo → em transação troca a senha, marca `usado_em`, `invalidar_tokens()`, `204` (FR-021 a FR-027) (depende de T023, T032, T046)
- [X] T048 [US3] ⚠️ Em routes/api.php, no grupo `area-cliente` (fora da autenticação), acrescentar `auth/recuperar_senha` e `auth/redefinir_senha` (depende de T047)

**Checkpoint**: recuperação validada com o código lido do log

---

## Phase 6: User Story 4 - Movimentar saldos (Priority: P1)

**Goal**: crédito/débito manual pelo painel em qualquer carteira, com saldo anterior e posterior

**Independent Test**: quickstart passos 26 a 30

- [X] T049 [P] [US4] Criar app/Http/Requests/ClientesTransacoesRequest.php: `carteira` required `Rule::enum(Carteira::class)`; `tipo` required `Rule::enum(TipoTransacao::class)`; `valor` required `regex:/^\d{1,13}(\.\d{1,2})?$/` e maior que zero ("O valor deve ser maior que zero, com até 2 casas decimais."); `observacao` required string max:255 ("O motivo é obrigatório.")
- [X] T050 [US4] Criar app/Http/Controllers/ClientesTransacoesController.php (`HasMiddleware` + `GarantirPermissaoCliente`; `index` → `Listar`, `store` → `MovimentarSaldo`): `store` chama `creditar()`/`debitar()` com origem `AjusteManual` e autor = usuário logado, `201` com `ClientesTransacoesResource`; `index` valida `data_inicial`/`data_final` (`date_format:Y-m-d`, final `after_or_equal` inicial), `carteira` (enum) e `por_pagina` (1–100, padrão 20), aplica `extrato()`, carrega `autor`, paginado com `withQueryString()` (FR-047, FR-056) (depende de T026, T028, T030, T049)
- [X] T051 [US4] ⚠️ Em routes/api.php, no grupo do painel, acrescentar `GET`/`POST clientes/{cliente}/transacoes` com `->missing(fn () => abort(404, 'Cliente não encontrado.'))` (depende de T050)

**Checkpoint**: saldos movimentados pelo painel; débito acima do saldo recusado

---

## Phase 7: User Story 5 - Meus dados, saldos e extrato (Priority: P2)

**Goal**: consultar dados, editar nome/gênero/e-mail/aceita_promocao, trocar senha, extrato

**Independent Test**: quickstart passos 15 a 18

- [X] T052 [P] [US5] Criar app/Http/Requests/UpdateMeusDadosRequest.php (`nome` sometimes max:150; `genero` sometimes enum; `email` sometimes nullable email max:150 único ignorando o próprio cliente, normalizado; `aceita_promocao` sometimes boolean; `ddi`, `telefone`, `cpf` e `data_nascimento` → `prohibited` com "Este dado só pode ser alterado pelo atendimento.") e app/Http/Requests/AlterarSenhaClienteRequest.php (`senha_atual` required `current_password:clientes` com "A senha atual está incorreta."; `password` required `confirmed` regra forte) (FR-029, FR-030)
- [X] T053 [US5] Criar app/Http/Controllers/AreaClienteMeusDadosController.php: `show()` → `ClientesResource` com `load('configuracoes')`; `update()` → salva `nome`/`genero`/`email` no cliente e `aceita_promocao` em `configuracoes`, numa transação; `alterar_senha()` → troca, `invalidar_tokens()`, `204`; `extrato()` → mesma validação de T050, só do cliente logado, com o escopo `extrato()` (FR-028 a FR-031) (depende de T029, T030, T052)
- [X] T054 [US5] ⚠️ Em routes/api.php, no subgrupo autenticado de `area-cliente`, acrescentar `GET`/`PATCH meus_dados`, `PUT meus_dados/senha` e `GET meus_dados/extrato` (depende de T053)

**Checkpoint**: área do cliente completa (sem meios de pagamento)

---

## Phase 8: User Story 6 - Configurações de aposta e saque (Priority: P2)

**Goal**: consultar e alterar configurações do cliente e as padrão, com coerência

**Independent Test**: quickstart passos 31 a 33

- [X] T055 [P] [US6] Criar app/Http/Requests/ClientesConfiguracoesRequest.php: os 6 booleans required boolean; `quantidade_minima_opcoes` required integer min:1 lte:`quantidade_maxima_opcoes`; `quantidade_maxima_opcoes` required integer min:1; `valor_minimo_aposta` required numeric gt:0 lte:`valor_maximo_aposta`; `valor_maximo_aposta`, `premio_maximo`, `valor_maximo_diario`, `valor_maximo_saque_diario` required numeric gt:0; `quantidade_maxima_saques_diaria` required integer min:1; `odd_minima` required numeric min:1 lte:`odd_maxima`; `odd_maxima` required numeric; `esportes_permitidos` required array min:1, itens string max:50 distintos; valores com até 2 casas; mensagens em português (FR-052)
- [X] T056 [US6] Criar app/Http/Controllers/ClientesConfiguracoesController.php (`HasMiddleware` + `GarantirPermissaoCliente`): `show(Clientes)` → `Listar`; `update(ClientesConfiguracoesRequest, Clientes)` → `EditarConfiguracoes`; respondem `ClientesConfiguracoesResource` (FR-053) (depende de T028, T031, T055)
- [X] T057 [P] [US6] Criar app/Http/Controllers/ClientesConfiguracoesPadraoController.php (ambos → `EditarConfiguracoesPadrao`, só Admin e Supervisor): `show()` e `update(ClientesConfiguracoesRequest)` sobre `ClientesConfiguracoesPadrao::atual()`; não altera clientes existentes (FR-049, FR-051) (depende de T028, T031, T055)
- [X] T058 [US6] ⚠️ Em routes/api.php, no grupo do painel, acrescentar `GET`/`PUT clientes/{cliente}/configuracoes` (com `missing` 404) e `GET`/`PUT clientes_configuracoes_padrao` (depende de T056, T057)

**Checkpoint**: configurações editáveis; padrão novo vale só para novos cadastros

---

## Phase 9: User Story 7 - Gerir clientes pelo painel (Priority: P2)

**Goal**: listar com busca/filtros/ordenação, consultar, editar, ativar/desativar, excluir com
sufixo e restaurar com checagem de conflito; mascaramento

**Independent Test**: quickstart passos 34 a 42

- [X] T059 [P] [US7] Criar app/Http/Requests/UpdateClientesRequest.php: todos os campos `sometimes` com as regras de T039 (unicidade de telefone, CPF e e-mail ignorando o próprio cliente); `password` opcional `confirmed` com a regra forte; saldos e `ativo` → `prohibited` (FR-058)
- [X] T060 [US7] Criar app/Http/Controllers/ClientesController.php (`HasMiddleware` + `GarantirPermissaoCliente`: `index`/`show` → `Listar`; `update`/`alterar_situacao` → `Editar`; `destroy` → `Excluir`; `excluidos`/`restaurar` → `Restaurar`) com `index(Request)`: `busca` (nome `LIKE %x%`; telefone, CPF e e-mail por prefixo se tem `clientes.ver_dados_completos`, senão igualdade exata normalizada), `ativo`, `ddi`, `codigo_afiliado`, `cadastro_de`/`cadastro_ate`, `idade_minima`/`idade_maxima` (faixa de `data_nascimento`), `genero`, `saldo_minimo`/`saldo_maximo`, `com_saldo_promocional`, `saque_bloqueado` (join/`whereHas` em `configuracoes`), `com_cpf`, `com_email`, `ordenar_por` (`nome`, `created_at`, `saldo`; padrão `nome`), `direcao`, `por_pagina` (1–100, padrão 20); filtros num método privado reutilizado por `excluidos()`, sempre com `->with('configuracoes')` para evitar uma consulta por cliente (SC-009); e `show(Clientes)` com `load('configuracoes')` (FR-055 a FR-057) (depende de T028, T029)
- [X] T061 [US7] Em app/Http/Controllers/ClientesController.php, acrescentar `update(UpdateClientesRequest, Clientes)` (campos não enviados ficam; senha só se enviada) e `alterar_situacao(Request, Clientes)` (`ativo` required boolean; ao desativar, `invalidar_tokens()`) (FR-058, FR-059) (depende de T059, T060)
- [X] T062 [US7] Em app/Http/Controllers/ClientesController.php, acrescentar `destroy(Clientes)`: em transação, sufixo `_deleted_<now()->timestamp>` em `telefone` e, se preenchidos, `cpf` e `email`; `invalidar_tokens()`; `delete()`; `204` (FR-060, research R-09) (depende de T060)
- [X] T063 [US7] Em app/Http/Controllers/ClientesController.php, acrescentar `excluidos(Request)` (`onlyTrashed()`, mesmos filtros; busca casa com o valor antes do sufixo) e `restaurar(Request, Clientes)` (rota `withTrashed()`): não excluído → `422 "Cliente não está excluído."`; remove `/_deleted_\d+$/` dos dados únicos; aceita `ddi`, `telefone`, `cpf`, `email` novos (normalizados e validados como no cadastro); conflito com não excluídos → `422` com `errors.<campo>` = "Já está em uso por outro cliente; informe um novo valor."; sem conflito, em transação grava e `restore()`; NÃO reaplica promoção; `200` com `ClientesResource` (com `load('configuracoes')`) (FR-061, FR-076) (depende de T062)
- [X] T064 [US7] ⚠️ Em routes/api.php, no grupo do painel, acrescentar ANTES do `apiResource`: `GET clientes/excluidos`, `POST clientes/{cliente}/restaurar` (`->withTrashed()`), `PATCH clientes/{cliente}/situacao`; depois `Route::apiResource('clientes', ClientesController::class)->except('store')`; rotas `{cliente}` com `missing` 404 (depende de T061, T063)

**Checkpoint**: gestão completa, com exclusão/restauração e mascaramento

---

## Phase 10: User Story 9 - Meios de pagamento (Priority: P2)

**Goal**: cliente e painel cadastram Pix e transferências, com principal e sem duplicidade

**Independent Test**: quickstart passos 19 a 25

- [X] T065 [P] [US9] Criar app/Http/Requests/ClientesMeiosPagamentoRequest.php (área do cliente e painel): `prepareForValidation()` normaliza documentos, agência e conta para dígitos e e-mail para minúsculas; `tipo` required (no update `sometimes`) `Rule::enum(TipoMeioPagamento::class)`; Pix (`required_if:tipo,Pix`): `pix_nome_titular` max:150, `pix_tipo_chave` enum `TipoChavePix`, `pix_chave` validada pelo tipo (CPF → `CpfValido`; CNPJ → `CnpjValido`; E-mail → `email` max:150; Telefone → só dígitos 10 a 13; Chave aleatória → UUID `/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i`); Transferência (`required_if:tipo,Transferência bancária`): `banco_codigo` `digits:3`, `banco_nome` max:100, `agencia` digits_between:1,10, `conta` digits_between:1,20, `conta_digito` max:2, `conta_tipo` enum `TipoConta`, `titular_nome` max:150, `titular_documento` CPF ou CNPJ válido; `principal` sometimes boolean; mensagens em português (FR-033, FR-034, research R-11)
- [X] T066 [US9] Criar app/Services/MeiosPagamentoClientes.php com `cadastrar(Clientes, array): ClientesMeiosPagamento`, `atualizar(ClientesMeiosPagamento, array)` e `excluir(ClientesMeiosPagamento)`: em transação com `lockForUpdate` nos meios do cliente; recusa duplicidade (`422 "Este meio de pagamento já está cadastrado."`) de `pix_chave` ou de banco+agência+conta+dígito entre os não excluídos do cliente; zera os campos do outro tipo; o primeiro vira principal; `principal: true` desmarca os demais; excluir o principal promove o mais antigo restante (FR-035, FR-036, research R-18) (depende de T021)
- [X] T067 [P] [US9] Criar app/Http/Resources/ClientesMeiosPagamentoResource.php com os campos do contrato; no painel sem `clientes.ver_dados_completos`, `pix_chave`, `conta` e `titular_documento` com só os 4 últimos caracteres visíveis (FR-057) (depende de T021)
- [X] T068 [US9] Criar app/Http/Controllers/AreaClienteMeiosPagamentoController.php (`index`, `store`, `show`, `update`, `destroy`) sobre os meios do cliente logado; meio de outro cliente → `404 "Meio de pagamento não encontrado."` (FR-037) (depende de T065, T066, T067)
- [X] T069 [US9] Criar app/Http/Controllers/ClientesMeiosPagamentoController.php (painel; `index` → `Listar`; `store`/`update`/`destroy` → `Editar`) com rotas aninhadas `scoped` (FR-038) (depende de T028, T065, T066, T067)
- [X] T070 [US9] ⚠️ Em routes/api.php, acrescentar no subgrupo autenticado de `area-cliente` o `apiResource('meios_pagamento', ...)->parameters(['meios-pagamento' => 'meio_pagamento'])` e no grupo do painel o `apiResource('clientes.meios_pagamento', ...)->except('show')->parameters([...])->scoped()`, ambos com `missing` 404 (depende de T068, T069)

**Checkpoint**: meios de pagamento completos nos dois lados

---

## Phase 11: User Story 8 - Cadastrar promoções (Priority: P3)

**Goal**: CRUD de promoções com categoria, tipo de ganho, rollover e regras de uso; sobreposição
proibida na mesma categoria e modalidade

**Independent Test**: quickstart passos 6, 43 a 46

- [X] T071 [P] [US8] Criar app/Http/Requests/ClientesPromocoesRequest.php: `nome` required max:150; `descricao` nullable; `modalidade` enum `ModalidadePromocao`; `categoria` enum `CategoriaPromocao`; `tipo_ganho` enum `TipoGanho` (Percentual só se `categoria->aceita_percentual()`); `valor` required numeric gt:0 até 2 casas (se Percentual, `max:100`); `rollover` required integer min:0 (min:1 se `'Primeiro depósito'`); `valor_minimo_aposta` gt:0 lte:`valor_maximo_aposta`; `valor_maximo_aposta` gt:0; `valor_maximo_deposito` nullable gt:0 (required se Percentual); `valor_maximo_conversao` required gt:0; `odd_minima_aposta_simples` e `odd_minima_aposta_multipla` required numeric min:1; `data_inicio` required date; `data_fim` nullable `after_or_equal:data_inicio`; `ativa` boolean; no update, regras `sometimes`; mensagens em português (FR-064 a FR-067)
- [X] T072 [P] [US8] Criar app/Http/Resources/ClientesPromocoesResource.php com os campos do contrato, `vigente`, `aplicada` e o objeto `estorno` (`situacao`, `motivo`, `autor`, `iniciado_em`, `concluido_em`, `total_clientes`, `clientes_processados`, `valor_total`) (depende de T022)
- [X] T073 [US8] Criar app/Http/Controllers/ClientesPromocoesController.php (`HasMiddleware` + `GarantirPermissaoCliente`; CRUD → `GerenciarPromocoes`): `index` (filtros `ativa`, `modalidade`, `categoria`; `por_pagina` 1–100, padrão 20; ordem `data_inicio` desc), `show`, `store`, `update`, `destroy` (`204`). Em `store`/`update`: se o resultado for `ativa`, recusar com `422 "Já existe uma promoção ativa desta categoria e modalidade no período."` quando houver outra ativa, não excluída, não estornada, da mesma categoria E modalidade, com período sobreposto (`inicio_a <= fim_b` e `inicio_b <= fim_a`, fim nulo = sem fim), ignorando a própria; em `update`, se `aplicada()`, recusar mudança de `valor`, `tipo_ganho`, `categoria` ou `modalidade` (`422 "Promoção já aplicada: valor, tipo de ganho, categoria e modalidade não podem mudar."`) e, se `estornada()`, recusar qualquer alteração (`422 "Promoção estornada não pode ser alterada."`) (FR-068 a FR-073, research R-16) (depende de T028, T071, T072)
- [X] T074 [US8] ⚠️ Em routes/api.php, no grupo do painel, acrescentar `Route::apiResource('clientes-promocoes', ...)->parameters(['clientes-promocoes' => 'promocao'])` com `missing` 404 "Promoção não encontrada." (depende de T073)

**Checkpoint**: promoções gerenciadas e aplicadas no cadastro

---

## Phase 12: User Story 10 - Estornar promoção (Priority: P3)

**Goal**: estorno em segundo plano, idempotente, só no saldo promocional da modalidade

**Independent Test**: quickstart passos 47 a 50 (com `php artisan queue:work` rodando)

- [X] T075 [P] [US10] Criar app/Http/Requests/EstornarPromocaoRequest.php: `motivo` required string max:255 ("O motivo do estorno é obrigatório.")
- [X] T076 [US10] Criar app/Jobs/EstornarPromocao.php (`ShouldQueue`, `ShouldBeUnique` com `uniqueId()` = id da promoção, `tries = 5`): percorre com `chunkById(500, column: 'clientes_id')` (a consulta agrupada não tem `id`) os `clientes_id` distintos com transação `OrigemTransacao::Promoção` e `referencia_id` da promoção; para cada cliente (inclusive excluídos): pula se já existir transação `OrigemTransacao::Estorno` com o mesmo `referencia_id`; soma o recebido da promoção; lê o saldo da carteira `modalidade->carteira()`; se > 0, `SaldoClientes::debitar()` de `min(recebido, saldo)` com origem `Estorno`, `referencia_id` = promoção, autor = quem estornou e observação = motivo; nunca toca o `saldo` real nem a outra modalidade; ao fim de cada lote RECALCULA (não soma) os contadores a partir do banco — `estorno_clientes_processados` = clientes já avaliados até o lote atual e `estorno_valor_total` = soma das transações `Estorno` com o `referencia_id` da promoção —, para que uma retomada não conte em dobro; no fim grava `estorno_situacao = SituacaoEstorno::Concluído` e `estorno_concluido_em` (FR-077 a FR-080, research R-17) (depende de T026, T022)
- [X] T077 [US10] Em app/Http/Controllers/ClientesPromocoesController.php, acrescentar `estornar(EstornarPromocaoRequest, ClientesPromocoes $promocao)` com a permissão `EstornarPromocoes` (só Admin e Supervisor): se `estornada()` → `422 "Esta promoção já foi estornada."`; senão, em transação, grava `ativa = false`, `estorno_situacao = EmAndamento`, `estorno_motivo`, `estorno_usuarios_id`, `estorno_iniciado_em` e `estorno_total_clientes` (clientes distintos que receberam); despacha `EstornarPromocao` após o commit; responde `202` com `ClientesPromocoesResource` (FR-077, FR-078, FR-081) (depende de T073, T075, T076)
- [X] T078 [US10] ⚠️ Em routes/api.php, no grupo do painel, acrescentar `POST clientes_promocoes/{promocao}/estornar` ANTES do `apiResource` de promoções, com `missing` 404 (depende de T077)

**Checkpoint**: estorno validado com o worker de fila rodando

---

## Phase 13: Polish & Cross-Cutting Concerns

- [X] T079 ⚠️ Regenerar docs/postman/wssports_api.postman_collection.json SUBSTITUINDO o arquivo (constituição): manter as rotas da spec 001 e as variáveis `base_url` e `token`; acrescentar `token_cliente`; pastas "Área do cliente" (15 rotas; script que preenche `token_cliente` no cadastro, login e refresh) e "Clientes (painel)" (23 rotas, usando `token`), com corpos de exemplo do [contracts/api.md](contracts/api.md)
- [X] T080 Conferir `php artisan route:list --path=api/area-cliente` (15 rotas) e `php artisan route:list --path=api/clientes` (23 rotas, sem `store` em `clientes`); rotas da spec 001 iguais
- [ ] T081 Executar o roteiro completo do [quickstart.md](quickstart.md) (passos 1 a 52, com `php artisan queue:work` rodando) e registrar divergências
- [X] T082 Revisão de legibilidade (Princípio V) de todo o código da feature em conjunto: nomes, duplicação (filtros e extrato entre `ClientesController`, `ClientesTransacoesController` e `AreaClienteMeusDadosController`; mascaramento entre os resources), métodos longos, comentários; confirmar que nenhum arquivo fora da lista do plan.md foi alterado

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sem dependências
- **Foundational (Phase 2)**: depende do Setup — BLOQUEIA todas as stories
- **US1 (Phase 3)**: depende da Foundational
- **US2 (Phase 4)**: depende da Foundational; usa clientes da US1 ou do seeder
- **US3 (Phase 5)**: depende da Foundational; validação usa o login da US2
- **US4 (Phase 6)**: depende da Foundational (painel da spec 001 já existe; grupos de rotas criados na T038)
- **US5 (Phase 7)**: depende da US2 (token de cliente); o extrato usa transações da US4
- **US6 (Phase 8)**: depende da Foundational
- **US7 (Phase 9)**: depende da Foundational
- **US9 (Phase 10)**: depende da US2 (área do cliente) e da Foundational (painel)
- **US8 (Phase 11)**: depende da Foundational; a US1 já aplica promoções criadas direto no banco
- **US10 (Phase 12)**: depende da US8 (controller de promoções) e da US1 (promoções aplicadas)
- **Polish (Phase 13)**: depende de todas as stories

### Within Each Story

- Request/resource antes do controller; controller antes da rota
- Tarefas no mesmo arquivo (`routes/api.php`, `ClientesController.php`,
  `ClientesPromocoesController.php`) são sequenciais

### Parallel Opportunities

- Phase 2: enums T002–T009 e migrations T010–T016 em paralelo; depois models T018–T023 (T017
  primeiro); T024, T025, T027–T032, T034 em paralelo após os models
- Requests de stories diferentes (T039, T043, T046, T049, T052, T055, T059, T065, T071, T075) em
  paralelo
- Após a Foundational: US4, US6, US7 e US8 em paralelo (arquivos diferentes, exceto
  `routes/api.php`)

---

## Parallel Example: Phase 2

```bash
Task: "Criar app/Enums/Genero.php"
Task: "Criar app/Enums/Carteira.php"
Task: "Acrescentar as permissões de clientes em app/Enums/Funcao.php"
Task: "Criar database/migrations/2026_09_29_000001_create_clientes_table.php"
Task: "Criar database/migrations/2026_09_29_000005_create_clientes_meios_pagamento_table.php"
```

## Parallel Example: Requests

```bash
Task: "Criar app/Http/Requests/StoreClientesRequest.php"
Task: "Criar app/Http/Requests/LoginClienteRequest.php"
Task: "Criar app/Http/Requests/ClientesMeiosPagamentoRequest.php"
Task: "Criar app/Http/Requests/ClientesPromocoesRequest.php"
```

---

## Implementation Strategy

### MVP First

1. Phase 1 (Setup) + Phase 2 (Foundational)
2. Phase 3 (US1 — cadastro) + Phase 4 (US2 — login)
3. **PARAR e VALIDAR** pelos passos 1–10 do quickstart

### Incremental Delivery

1. Setup + Foundational → banco, serviço de saldo e infraestrutura prontos
2. US1 → cadastro público (MVP)
3. US2 → login do cliente
4. US3 → recuperação de senha
5. US4 → movimentação de saldo pelo painel
6. US5 → área do cliente (dados, senha, extrato)
7. US6 → configurações de aposta e saque
8. US7 → gestão de clientes (exclusão/restauração)
9. US9 → meios de pagamento
10. US8 → promoções
11. US10 → estorno de promoção
12. Polish → Postman, quickstart completo, revisão

---

## Notes

- Revisão pós-implementação (2026-09-29, constituição v1.14.0): permissões de clientes movidas para
  `Funcao` (Princípio VI) e caminhos de rota em kebab-case (constituição v1.16.0); parâmetros de
  query string continuam em snake_case, com autorização do responsável.

- [P] = arquivos diferentes, sem dependências pendentes
- ⚠️ = arquivo existente com alteração já autorizada (Princípio IV)
- Nenhuma tarefa de teste (constituição)
- Commit manual: o responsável roda `/speckit-git-commit` quando quiser
