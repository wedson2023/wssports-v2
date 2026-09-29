---

description: "Lista de tarefas da feature Clientes (Apostadores)"
---

# Tasks: Clientes (Apostadores)

**Input**: Documentos de design em `specs/002-clientes/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md),
[data-model.md](data-model.md), [contracts/api.md](contracts/api.md), [quickstart.md](quickstart.md)

**Tests**: NÃO há tarefas de teste — a constituição proíbe testes automatizados. Cada user story é
validada manualmente pelos passos do [quickstart.md](quickstart.md).

**Organization**: tarefas agrupadas por user story, na ordem de prioridade da spec.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: pode rodar em paralelo (arquivos diferentes, sem dependências pendentes)
- **[Story]**: user story da tarefa (US1 a US8)
- Caminhos relativos à raiz do repositório

## Regras que valem para TODAS as tarefas

- **Princípio I**: métodos, variáveis, parâmetros, chaves JSON, rotas e permissões em
  `snake_case`; classes em `PascalCase`; métodos exigidos pelo framework/pacotes mantêm o nome
  original (`getJWTIdentifier`, `getJWTCustomClaims`, `middleware`, `rules`, `messages`,
  `authorize`, `prepareForValidation`, `toArray`, `definition`, `casts`, `up`, `down`, `run`,
  `handle`, `render`). Permissões no formato `<recurso>.<acao>` (v1.11.0). Tabelas ligadas a
  `clientes` com prefixo `clientes_` (v1.9.0).
- **Princípio II**: comentários no código e mensagens de erro/validação em português.
- **Princípio IV**: alterar só os arquivos e trechos indicados. Arquivos existentes marcados com
  ⚠️ já tiveram a alteração autorizada pelo responsável (plan.md, 2026-09-29). NÃO alterar
  `app/Enums/Funcao.php`, `app/Models/Usuarios.php`, `app/Http/Middleware/GarantirAcesso.php`,
  `app/Http/Controllers/PermissoesUsuariosController.php`, `bootstrap/app.php` nem as
  migrations da spec 001.
- **Princípio V**: ao concluir cada tarefa, revisar em conjunto o código alterado (nomes,
  duplicação, métodos longos, condicionais aninhadas, comentários) e informar se houve ou não
  refatoração.
- **Banco**: só `php artisan migrate` e `php artisan db:seed --class=ClientesSeeder`. NUNCA
  `migrate:fresh` nem `migrate:refresh` (research.md R-17).
- **Paginação**: `por_pagina` de 1 a 100, padrão 20; nenhuma listagem retorna mais de 100.
- **Dinheiro**: nunca usar `float` em cálculo de saldo; valores monetários trafegam como texto
  com 2 casas (`"100.00"`) (research.md R-04).
- **Padrões da spec 001**: FormRequests com `messages()` em português; controllers do painel com
  `HasMiddleware`; respostas de erro `{"message": "..."}`.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: guard de clientes

- [ ] T001 ⚠️ Em config/auth.php, acrescentar o guard `'clientes' => ['driver' => 'jwt', 'provider' => 'clientes']` e o provider `'clientes' => ['driver' => 'eloquent', 'model' => App\Models\Clientes::class]`, com comentário em português; NÃO alterar os guards `web`/`api` nem o provider `users` (research.md R-01)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: enums, tabelas, models, serviço de saldo, middlewares, resources e eventos usados por
todas as stories

**⚠️ CRITICAL**: nenhuma user story começa antes desta fase terminar

### Enums

- [ ] T002 [P] Criar app/Enums/Genero.php (backed `string`): `Masculino = 'masculino'`, `Feminino = 'feminino'`, `Outro = 'outro'`, `NaoInformado = 'nao_informado'`
- [ ] T003 [P] Criar app/Enums/Carteira.php (backed `string`, o valor é o nome da coluna em `clientes`): `Saldo = 'saldo'`, `PromocaoEsportes = 'saldo_promocao_esportes'`, `PromocaoCassino = 'saldo_promocao_cassino'`
- [ ] T004 [P] Criar app/Enums/TipoTransacao.php: `Credito = 'credito'`, `Debito = 'debito'`
- [ ] T005 [P] Criar app/Enums/OrigemTransacao.php: `AjusteManual = 'ajuste_manual'`, `Promocao = 'promocao'`, `Aposta = 'aposta'`, `Premio = 'premio'`, `Estorno = 'estorno'`
- [ ] T006 [P] Criar app/Enums/ModalidadePromocao.php: `Esportes = 'esportes'`, `Cassino = 'cassino'`, com o método `carteira(): Carteira` (Esportes → `Carteira::PromocaoEsportes`, Cassino → `Carteira::PromocaoCassino`)
- [ ] T007 [P] Criar app/Enums/CategoriaPromocao.php: `PrimeiroCadastro = 'primeiro_cadastro'`, `PrimeiroDeposito = 'primeiro_deposito'`, `QualquerDeposito = 'qualquer_deposito'`, `Indicacao = 'indicacao'` (só `PrimeiroCadastro` é aplicada nesta spec)
- [ ] T008 [P] Criar app/Enums/PermissaoCliente.php (backed `string`) com os 9 casos: `Listar = 'clientes.listar'`, `VerDadosCompletos = 'clientes.ver_dados_completos'`, `Editar = 'clientes.editar'`, `EditarConfiguracoes = 'clientes.editar_configuracoes'`, `MovimentarSaldo = 'clientes.movimentar_saldo'`, `GerenciarPromocoes = 'clientes_promocoes.gerenciar'`, `Excluir = 'clientes.excluir'`, `Restaurar = 'clientes.restaurar'`, `EditarConfiguracoesPadrao = 'clientes.editar_configuracoes_padrao'`; método `funcoes_permitidas(): array` de `App\Enums\Funcao` (Excluir, Restaurar e EditarConfiguracoesPadrao → Admin e Supervisor; os demais → Admin, Supervisor e Gerente; Vendedor nunca) e método `permitida_para(Usuarios $usuario): bool` (tem a permissão direta com `hasPermissionTo($this->value)` E `$usuario->funcao()` está em `funcoes_permitidas()`) (research.md R-03)

### Migrations (todas com `timestamps()` e `softDeletes()`)

- [ ] T009 [P] Criar database/migrations/2026_09_29_000001_create_clientes_table.php: `id()`; `string('nome', 150)`; `string('codigo_pais', 3)->default('55')`; `string('telefone', 40)`; `string('cpf', 40)->unique()`; `string('password')`; `date('data_nascimento')`; `string('genero', 20)`; `string('codigo_afiliado', 50)->nullable()` (comentário: sem FK, afiliados ainda não existem — constituição v1.10.0); `boolean('aceita_promocao')->default(true)`; `boolean('ativo')->default(true)`; `decimal('saldo', 15, 2)->default(0)`; `decimal('saldo_promocao_esportes', 15, 2)->default(0)`; `decimal('saldo_promocao_cassino', 15, 2)->default(0)`; `timestamp('tokens_validos_desde')->nullable()`; índice único (`codigo_pais`, `telefone`); índices em `nome` e `created_at`
- [ ] T010 [P] Criar database/migrations/2026_09_29_000002_create_clientes_transacoes_table.php: `id()`; `foreignId('clientes_id')->constrained('clientes')`; `string('carteira', 30)`; `string('tipo', 10)`; `string('origem', 30)`; `unsignedBigInteger('referencia_id')->nullable()` (comentário: sem FK — origem pode ser promoção ou, no futuro, aposta); `decimal('valor', 15, 2)`; `decimal('saldo_anterior', 15, 2)`; `decimal('saldo_posterior', 15, 2)`; `foreignId('usuarios_id')->nullable()->constrained('usuarios')`; `string('observacao')->nullable()`; índices (`clientes_id`, `created_at`) e (`clientes_id`, `carteira`)
- [ ] T011 [P] Criar database/migrations/2026_09_29_000003_create_clientes_configuracoes_table.php: `id()`; `foreignId('clientes_id')->unique()->constrained('clientes')`; `boolean` `realizar_aposta`, `apostar_ao_vivo`, `apostar_outros_esportes`, `cancelar_aposta`; `unsignedSmallInteger` `quantidade_minima_opcoes`, `quantidade_maxima_opcoes`; `decimal(15,2)` `valor_minimo_aposta`, `valor_maximo_aposta`, `premio_maximo`, `valor_maximo_diario`; `decimal(8,2)` `odd_minima`, `odd_maxima`; `json('esportes_permitidos')` (comentário: sem FK, cadastro de esportes ainda não existe)
- [ ] T012 [P] Criar database/migrations/2026_09_29_000004_create_clientes_configuracoes_padrao_table.php com as mesmas colunas de T011, sem `clientes_id` (registro único)
- [ ] T013 [P] Criar database/migrations/2026_09_29_000005_create_clientes_promocoes_table.php: `id()`; `string('nome', 150)`; `text('descricao')->nullable()`; `string('modalidade', 20)`; `string('categoria', 30)`; `decimal('valor', 15, 2)`; `dateTime('data_inicio')`; `dateTime('data_fim')->nullable()`; `boolean('ativa')->default(true)`; índice (`categoria`, `modalidade`, `ativa`)
- [ ] T014 [P] Criar database/migrations/2026_09_29_000006_create_clientes_codigos_recuperacao_table.php: `id()`; `foreignId('clientes_id')->constrained('clientes')`; `string('codigo')` (hash); `unsignedTinyInteger('tentativas')->default(0)`; `dateTime('expira_em')`; `dateTime('usado_em')->nullable()`; `dateTime('invalidado_em')->nullable()`

### Models

- [ ] T015 Criar app/Models/Clientes.php: extends `Illuminate\Foundation\Auth\User`, implements `JWTSubject`; traits `HasFactory`, `SoftDeletes`; `$table = 'clientes'`; `$fillable` = `nome`, `codigo_pais`, `telefone`, `cpf`, `password`, `data_nascimento`, `genero`, `codigo_afiliado`, `aceita_promocao`, `ativo` (NUNCA os saldos nem `tokens_validos_desde`); `$hidden` = `password`; `casts()` = `password => 'hashed'`, `data_nascimento => 'date'`, `genero => Genero::class`, `aceita_promocao`/`ativo` => `'boolean'`, saldos => `'decimal:2'`, `tokens_validos_desde => 'datetime'`; `getJWTIdentifier()` → `getKey()`; `getJWTCustomClaims()` → `[]`; relações `configuracoes()` (hasOne `ClientesConfiguracoes`), `transacoes()` (hasMany `ClientesTransacoes`), `codigos_recuperacao()` (hasMany); métodos `pode_acessar(): bool` (`ativo` e não excluído) e `invalidar_tokens(): void` (grava `tokens_validos_desde = now()` sem passar pelo `$fillable`) (depende de T002, T009)
- [ ] T016 [P] Criar app/Models/ClientesTransacoes.php: `SoftDeletes`; `$table = 'clientes_transacoes'`; `$fillable` com todas as colunas de T010 exceto `id`/timestamps; `casts()` = `carteira => Carteira::class`, `tipo => TipoTransacao::class`, `origem => OrigemTransacao::class`, `valor`/`saldo_anterior`/`saldo_posterior` => `'decimal:2'`; relações `cliente()` (belongsTo `Clientes`, `clientes_id`) e `autor()` (belongsTo `Usuarios`, `usuarios_id`); escopo `scopeExtrato($query, array $filtros)` que aplica `data_inicial`/`data_final` (dias inteiros) e `carteira` e ordena por `created_at` desc e `id` desc, reutilizado pelo extrato do painel e da área do cliente (depende de T003–T005, T010)
- [ ] T017 [P] Criar app/Models/ClientesConfiguracoes.php: `SoftDeletes`; `$table = 'clientes_configuracoes'`; constante `CAMPOS` com os 13 campos de configuração (reutilizada pelo padrão, pelo request e pelo resource); `$fillable` = `CAMPOS` + `clientes_id`; `casts()` = booleans, `'decimal:2'` nos valores e odds, `esportes_permitidos => 'array'`; relação `cliente()` (depende de T011)
- [ ] T018 [P] Criar app/Models/ClientesConfiguracoesPadrao.php: `SoftDeletes`; `$table = 'clientes_configuracoes_padrao'`; `$fillable = ClientesConfiguracoes::CAMPOS`; mesmos casts; método estático `atual(): self` (retorna o primeiro registro; `firstOrFail`) (depende de T012, T017)
- [ ] T019 [P] Criar app/Models/ClientesPromocoes.php: `SoftDeletes`; `$table = 'clientes_promocoes'`; `$fillable` = `nome`, `descricao`, `modalidade`, `categoria`, `valor`, `data_inicio`, `data_fim`, `ativa`; casts de enum (`ModalidadePromocao`, `CategoriaPromocao`), `valor => 'decimal:2'`, datas `'datetime'`, `ativa => 'boolean'`; escopo `scopeVigentes($query)` (`ativa = true` e `data_inicio <= now()` e (`data_fim` nula ou `>= now()`)); método `vigente(): bool` (depende de T006, T007, T013)
- [ ] T020 [P] Criar app/Models/ClientesCodigosRecuperacao.php: `SoftDeletes`; `$table = 'clientes_codigos_recuperacao'`; `$fillable` = `clientes_id`, `codigo`, `tentativas`, `expira_em`, `usado_em`, `invalidado_em`; casts de data; método `valido(): bool` (`usado_em` e `invalidado_em` nulos e `expira_em` no futuro); relação `cliente()` (depende de T014)

### Serviços, regras e infraestrutura

- [ ] T021 [P] Criar a regra app/Rules/CpfValido.php (`ValidationRule`): recebe só dígitos; exige 11 dígitos, não todos iguais, e os 2 dígitos verificadores corretos; mensagem "O CPF informado é inválido." (research.md R-11)
- [ ] T022 [P] Criar app/Exceptions/SaldoInsuficienteException.php com o método `render()` que responde `422 {"message": "Saldo insuficiente."}` (sem alterar bootstrap/app.php)
- [ ] T023 Criar o serviço app/Services/SaldoClientes.php com `creditar()` e `debitar()` na assinatura do contrato (`Clientes $cliente, Carteira $carteira, string $valor, OrigemTransacao $origem, ?Usuarios $autor = null, ?int $referencia_id = null, ?string $observacao = null): ClientesTransacoes`) e um método privado comum que, dentro de `DB::transaction`: relê o cliente com `lockForUpdate()`; converte saldo e valor de texto decimal para centavos inteiros sem `float` (métodos privados `para_centavos(string): int` e `para_decimal(int): string`); recusa valor ≤ 0; no débito, lança `SaldoInsuficienteException` se o valor for maior que o saldo da carteira; grava o novo saldo na coluna `$carteira->value` com `forceFill` (os saldos não estão no `$fillable`); cria a `ClientesTransacoes` com `saldo_anterior` e `saldo_posterior`; retorna a transação. Comentário em português explicando o bloqueio (FR-032 a FR-040, research.md R-04) (depende de T015, T016, T022)
- [ ] T024 [P] Criar o middleware app/Http/Middleware/GarantirAcessoCliente.php: se `! $request->user('clientes')?->pode_acessar()`, responder `403 {"message": "Cliente sem permissão de acesso."}`; se o cliente tem `tokens_validos_desde` e o claim `iat` do token (`auth('clientes')->payload()->get('iat')`) for anterior a ele, responder `401 {"message": "Não autenticado."}` (research.md R-02) (depende de T015)
- [ ] T025 [P] Criar o trait app/Http/Controllers/Concerns/GarantirPermissaoCliente.php com o método estático `permissao_cliente(PermissaoCliente $permissao, array $only = [], array $except = []): Middleware`, que devolve um `Illuminate\Routing\Controllers\Middleware` com closure: se `! $permissao->permitida_para($request->user())`, responder `403 {"message": "Você não tem permissão para esta ação."}` (research.md R-03) (depende de T008)
- [ ] T026 [P] Criar app/Http/Resources/ClientesResource.php com `id`, `nome`, `codigo_pais`, `telefone`, `cpf`, `data_nascimento` (Y-m-d), `genero`, `codigo_afiliado`, `aceita_promocao`, `ativo`, `saldo`, `saldo_promocao_esportes`, `saldo_promocao_cassino`, `created_at`, `updated_at` e `deleted_at` só quando excluído; NUNCA `password` nem `tokens_validos_desde`. Na exclusão, `telefone`/`cpf` saem sem o sufixo `_deleted_<timestamp>`. Quando a requisição vier de um usuário do painel (`$request->user() instanceof Usuarios`) sem `clientes.ver_dados_completos`, mascarar: CPF `***.456.789-**` e telefone `(11) *****-7777` para código 55 ou só os 4 últimos dígitos (`******4567`) nos demais (research.md R-12, FR-052a) (depende de T008, T015)
- [ ] T027 [P] Criar app/Http/Resources/ClientesTransacoesResource.php com `id`, `carteira`, `tipo`, `origem`, `referencia_id`, `valor`, `saldo_anterior`, `saldo_posterior`, `observacao`, `created_at` e `autor` (`{id, nome}` ou `null`) SOMENTE quando a requisição vier do painel (na área do cliente o campo não aparece) (depende de T016)
- [ ] T028 [P] Criar app/Http/Resources/ClientesConfiguracoesResource.php com os 13 campos de `ClientesConfiguracoes::CAMPOS` e `updated_at` (serve às configurações do cliente e às configurações padrão) (depende de T017)
- [ ] T029 [P] Criar os eventos app/Events/ClienteCadastrado.php (propriedade pública `Clientes $cliente`) e app/Events/CodigoRecuperacaoGerado.php (`Clientes $cliente`, `string $codigo`), ambos com `Dispatchable` e `ShouldDispatchAfterCommit` (research.md R-14) (depende de T015)
- [ ] T030 Criar o listener app/Listeners/RegistrarMensagemWhatsapp.php com `handle(ClienteCadastrado|CodigoRecuperacaoGerado $evento)`: monta o texto (boas-vindas com o nome; ou "Seu código de recuperação é XXXXXX, válido por 15 minutos") e grava com `Log::info('WhatsApp (envio pendente da spec de WhatsApp)', [...])` com código do país e telefone; tudo em `try/catch` que só registra o erro, nunca relança (FR-013, FR-027) (depende de T029)
- [ ] T031 [P] Criar database/factories/ClientesFactory.php: `definition()` com `nome`, `codigo_pais` `'55'`, `telefone` único de 11 dígitos, `cpf` único e VÁLIDO (gerar 9 dígitos e calcular os verificadores), `password` (hash de `'senha123'`), `data_nascimento` entre 18 e 70 anos atrás, `genero` aleatório de `Genero`, `aceita_promocao` `true`, `ativo` `true`; estados `inativo()` e `com_codigo_afiliado(string)`; `afterCreating` cria as `configuracoes` copiando `ClientesConfiguracoesPadrao::atual()` (depende de T015, T018)
- [ ] T032 Criar database/seeders/ClientesSeeder.php: limpar o cache do spatie; criar com `firstOrCreate` as 9 permissões de `PermissaoCliente` no guard `api`; dar essas permissões aos usuários com papel `Admin`; criar com `firstOrCreate` o registro único de `clientes_configuracoes_padrao` com os valores do data-model (`realizar_aposta`/`apostar_ao_vivo`/`apostar_outros_esportes` = `true`, `cancelar_aposta` = `false`, opções 1 a 20, aposta `2.00` a `1000.00`, prêmio máximo `50000.00`, diário `5000.00`, odd `1.90` a `30.00`, esportes `["FUTEBOL", "HOQUEI NO GELO", "BAISEBOL"]`); criar 5 clientes de exemplo pela factory (1 inativo) (FR-043a, FR-058) (depende de T008, T018, T031)
- [ ] T033 ⚠️ Em database/seeders/DatabaseSeeder.php, acrescentar SOMENTE `$this->call(ClientesSeeder::class);` ao final de `run()` (depois da hierarquia de exemplo, pois o seeder dá permissões ao Admin) (depende de T032)
- [ ] T034 Rodar `php artisan migrate` e `php artisan db:seed --class=ClientesSeeder` (NUNCA `migrate:fresh`/`migrate:refresh`) e conferir: 6 tabelas novas; 1 registro de configurações padrão; 9 permissões novas no guard `api` dadas ao Admin; 5 clientes com `clientes_configuracoes`; tabelas existentes intactas (depende de T009–T014, T032)

**Checkpoint**: estrutura pronta — as user stories podem começar

---

## Phase 3: User Story 1 - Cadastro público do cliente (Priority: P1) 🎯 MVP

**Goal**: visitante cria a conta; saldos zerados ou com promoção; configurações copiadas do padrão; evento
de boas-vindas

**Independent Test**: quickstart passos 1 a 7

- [ ] T035 [P] [US1] Criar app/Http/Requests/StoreClientesRequest.php: `authorize()` true; `prepareForValidation()` normaliza `telefone`, `cpf` e `codigo_pais` para só dígitos, `codigo_pais` padrão `'55'` e `aceita_promocao` padrão `true`; `rules()`: `nome` required string max:150; `codigo_pais` digits_between:1,3; `telefone` required, só dígitos, 10–11 se `codigo_pais = 55` e 4–14 nos demais, único por (`codigo_pais`, `telefone`) entre os não excluídos; `cpf` required, `new CpfValido`, único; `password` required, `confirmed`, `Password::min(8)->letters()->numbers()`; `data_nascimento` required date `before_or_equal` hoje − 18 anos; `genero` required `Rule::enum(Genero::class)`; `codigo_afiliado` nullable string max:50; `aceita_promocao` boolean; `messages()` em português ("O telefone já está cadastrado.", "O CPF já está cadastrado.", "É preciso ter 18 anos ou mais.", "A senha deve ter no mínimo 8 caracteres, com letras e números.", "A confirmação da senha não confere.") (FR-003 a FR-010, R-10, R-11)
- [ ] T036 [US1] Criar o serviço app/Services/CadastroClientes.php com `cadastrar(array $dados): Clientes`: em `DB::transaction`, cria o cliente (ativo, saldos 0), cria `configuracoes` copiando `ClientesConfiguracoesPadrao::atual()` (campos de `ClientesConfiguracoes::CAMPOS`) e, se `aceita_promocao`, para cada `ClientesPromocoes::vigentes()->where('categoria', CategoriaPromocao::PrimeiroCadastro)` chama `SaldoClientes::creditar()` na carteira `$promocao->modalidade->carteira()`, origem `promocao`, `referencia_id` = id da promoção, observação com o nome da promoção; depois dispara `ClienteCadastrado` (sai após o commit). Violação de índice único na corrida entre dois cadastros vira `422` com a mensagem de duplicidade (FR-010, FR-011, FR-013, FR-064, FR-065, R-15) (depende de T023, T029)
- [ ] T037 [US1] Criar app/Http/Controllers/AreaClienteCadastroController.php com `store(StoreClientesRequest $request, CadastroClientes $cadastro)`: cadastra, gera o token com `auth('clientes')->login($cliente)` e responde `201 {"cliente": ClientesResource, "token", "tipo": "bearer", "expira_em"}` (depende de T026, T035, T036)
- [ ] T038 [US1] ⚠️ Em routes/api.php, acrescentar o grupo `Route::prefix('area_cliente')` com `Route::post('cadastro', [AreaClienteCadastroController::class, 'store'])` e o `use` do controller; rotas da spec 001 intactas (depende de T037)

**Checkpoint**: cadastro validado (quickstart 1–7, usando promoção criada direto no banco até a US8)

---

## Phase 4: User Story 2 - Login do cliente com telefone e senha (Priority: P1)

**Goal**: token de cliente de 60 min, renovação, logout, limite de tentativas, bloqueio de
inativos e separação entre guards

**Independent Test**: quickstart passos 8 a 11 e 25

- [ ] T039 [P] [US2] Criar app/Http/Requests/LoginClienteRequest.php seguindo o `LoginRequest` da spec 001: normaliza `telefone`/`codigo_pais` (padrão `'55'`); `rules()` `telefone` required, `password` required string; métodos `garantir_limite_tentativas()` (429 "Muitas tentativas. Tente novamente em N segundos."), `registrar_tentativa_falha()` e `limpar_tentativas()` com 5 tentativas em 60 s e chave `codigo_pais.telefone|ip` (FR-020)
- [ ] T040 [US2] Criar app/Http/Controllers/AreaClienteAutenticacaoController.php: `login()` (garante o limite; `auth('clientes')->attempt(['codigo_pais', 'telefone', 'password'])`; falha → registra tentativa e `401 "Telefone ou senha inválidos."`; `! pode_acessar()` → logout e `403 "Cliente sem permissão de acesso."`; sucesso → limpa tentativas e `200 {"token", "tipo": "bearer", "expira_em"}`), `refresh()` (`auth('clientes')->refresh()`) e `logout()` (`auth('clientes')->logout()`, `204`) (FR-014 a FR-019) (depende de T039)
- [ ] T041 [US2] ⚠️ Em routes/api.php, no grupo `area_cliente`, acrescentar `Route::post('auth/login', ...)` e o subgrupo `Route::middleware(['auth:clientes', GarantirAcessoCliente::class])` com `auth/refresh` e `auth/logout` (depende de T024, T040)

**Checkpoint**: login do cliente validado; token de cliente recusado no painel e vice-versa

---

## Phase 5: User Story 3 - Recuperar senha (Priority: P1)

**Goal**: código de 6 dígitos por evento (log), válido 15 min, uso único, 5 tentativas; resposta
genérica; tokens antigos invalidados

**Independent Test**: quickstart passos 32 a 35

- [ ] T042 [P] [US3] Criar app/Http/Requests/RecuperarSenhaClienteRequest.php (`codigo_pais` padrão `'55'`, `telefone` required, normalizados para dígitos) e app/Http/Requests/RedefinirSenhaClienteRequest.php (`codigo_pais`, `telefone`, `codigo` required `digits:6`, `password` required `confirmed` `Password::min(8)->letters()->numbers()`), mensagens em português
- [ ] T043 [US3] Criar app/Http/Controllers/AreaClienteRecuperacaoSenhaController.php: `solicitar()` — limite de 1 pedido por minuto por `codigo_pais.telefone` com `RateLimiter` (429 com o tempo de espera); se existir cliente que `pode_acessar()`, invalida os códigos válidos anteriores (`invalidado_em = now()`), gera `random_int(0, 999999)` com 6 dígitos (zeros à esquerda), grava o hash com `expira_em = now()->addMinutes(15)` e dispara `CodigoRecuperacaoGerado`; responde SEMPRE `200 {"message": "Se o telefone estiver cadastrado, enviaremos um código."}`. `redefinir()` — busca o código válido mais recente do cliente; ausente ou cliente sem acesso → `422 "Código inválido ou expirado."`; código errado → incrementa `tentativas` e, na 5ª, preenche `invalidado_em`, respondendo `422`; certo → em transação troca a senha, marca `usado_em`, chama `invalidar_tokens()` e responde `204` (FR-021 a FR-027, R-13) (depende de T020, T029, T042)
- [ ] T044 [US3] ⚠️ Em routes/api.php, no grupo `area_cliente` (fora do middleware de autenticação), acrescentar `auth/recuperar_senha` e `auth/redefinir_senha` (depende de T043)

**Checkpoint**: recuperação validada com o código lido do log

---

## Phase 6: User Story 4 - Movimentar saldos do cliente (Priority: P1)

**Goal**: crédito/débito manual pelo painel em qualquer carteira, com saldo anterior e posterior

**Independent Test**: quickstart passos 15 a 19

- [ ] T045 [P] [US4] Criar app/Http/Requests/ClientesTransacoesRequest.php: `carteira` required `Rule::enum(Carteira::class)`; `tipo` required `Rule::enum(TipoTransacao::class)`; `valor` required, `regex:/^\d{1,13}(\.\d{1,2})?$/` e maior que zero ("O valor deve ser maior que zero, com até 2 casas decimais."); `observacao` required string max:255 ("O motivo é obrigatório.") (FR-041)
- [ ] T046 [US4] Criar app/Http/Controllers/ClientesTransacoesController.php (`HasMiddleware`, trait `GarantirPermissaoCliente`): `index` exige `PermissaoCliente::Listar`, `store` exige `PermissaoCliente::MovimentarSaldo`. `store(ClientesTransacoesRequest, Clientes $cliente, SaldoClientes $saldo)` chama `creditar()` ou `debitar()` com origem `AjusteManual` e autor = usuário logado; responde `201` com `ClientesTransacoesResource`. `index(Request, Clientes $cliente)` valida `data_inicial`/`data_final` (`date_format:Y-m-d`, `data_final` `after_or_equal:data_inicial`), `carteira` (enum) e `por_pagina` (1–100, padrão 20); aplica o escopo `extrato()`; carrega `autor`; paginado com `withQueryString()` (FR-031, FR-041, FR-051) (depende de T023, T025, T027, T045)
- [ ] T047 [US4] ⚠️ Em routes/api.php, no grupo `Route::middleware(['auth:api', 'garantir_acesso'])` novo para clientes, acrescentar `GET` e `POST clientes/{cliente}/transacoes` com `->missing(fn () => abort(404, 'Cliente não encontrado.'))` (depende de T046)

**Checkpoint**: saldos movimentados pelo painel; débito acima do saldo recusado

---

## Phase 7: User Story 5 - Cliente consulta dados, saldos e extrato (Priority: P2)

**Goal**: "meus dados", edição de nome/gênero/aceita_promocao, troca de senha e extrato

**Independent Test**: quickstart passos 12 a 14 e 20

- [ ] T048 [P] [US5] Criar app/Http/Requests/UpdateMeusDadosRequest.php (`nome` sometimes string max:150; `genero` sometimes enum; `aceita_promocao` sometimes boolean; `codigo_pais`, `telefone`, `cpf` e `data_nascimento` → `prohibited` com "Este dado só pode ser alterado pelo atendimento.") e app/Http/Requests/AlterarSenhaClienteRequest.php (`senha_atual` required `current_password:clientes` com "A senha atual está incorreta."; `password` required `confirmed` `Password::min(8)->letters()->numbers()`) (FR-029, FR-030)
- [ ] T049 [US5] Criar app/Http/Controllers/AreaClienteMeusDadosController.php: `show()` → `ClientesResource` do cliente logado; `update(UpdateMeusDadosRequest)` → salva e devolve o resource; `alterar_senha(AlterarSenhaClienteRequest)` → troca a senha, chama `invalidar_tokens()` e responde `204`; `extrato(Request)` → mesma validação e ordenação de `ClientesTransacoesController@index`, só das transações do cliente logado, usando o escopo `extrato()` de T016 (FR-028 a FR-031) (depende de T026, T027, T048)
- [ ] T050 [US5] ⚠️ Em routes/api.php, no subgrupo autenticado de `area_cliente`, acrescentar `GET` e `PATCH meus_dados`, `PUT meus_dados/senha` e `GET meus_dados/extrato` (depende de T049)

**Checkpoint**: área do cliente completa

---

## Phase 8: User Story 6 - Configurar as configurações de aposta do cliente pelo painel (Priority: P2)

**Goal**: consultar e alterar configurações do cliente e as configurações padrão, com regras de coerência

**Independent Test**: quickstart passos 21 e 22

- [ ] T051 [P] [US6] Criar app/Http/Requests/ClientesConfiguracoesRequest.php (serve às configurações do cliente e às padrão): os 4 booleans required boolean; `quantidade_minima_opcoes` required integer min:1 lte:`quantidade_maxima_opcoes`; `quantidade_maxima_opcoes` required integer min:1; `valor_minimo_aposta` required numeric gt:0 lte:`valor_maximo_aposta`; `valor_maximo_aposta`, `premio_maximo`, `valor_maximo_diario` required numeric gt:0; `odd_minima` required numeric min:1 lte:`odd_maxima`; `odd_maxima` required numeric; `esportes_permitidos` required array min:1, itens string max:50 distintos; valores monetários com até 2 casas; mensagens em português (FR-044)
- [ ] T052 [US6] Criar app/Http/Controllers/ClientesConfiguracoesController.php (`HasMiddleware` + `GarantirPermissaoCliente`): `show(Clientes $cliente)` exige `Listar`; `update(ClientesConfiguracoesRequest, Clientes $cliente)` exige `EditarConfiguracoes`; ambos respondem `ClientesConfiguracoesResource` (FR-046) (depende de T025, T028, T051)
- [ ] T053 [P] [US6] Criar app/Http/Controllers/ClientesConfiguracoesPadraoController.php (`HasMiddleware` + `GarantirPermissaoCliente`, ambos os métodos exigem `EditarConfiguracoesPadrao`, que só Admin e Supervisor usam): `show()` e `update(ClientesConfiguracoesRequest)` sobre `ClientesConfiguracoesPadrao::atual()`; a alteração NÃO muda as configurações dos clientes já cadastrados (FR-043, FR-043b) (depende de T025, T028, T051)
- [ ] T054 [US6] ⚠️ Em routes/api.php, no grupo do painel, acrescentar `GET`/`PUT clientes/{cliente}/configuracoes` (com `missing` 404) e `GET`/`PUT clientes_configuracoes_padrao` (depende de T052, T053)

**Checkpoint**: configurações editáveis pelo painel; padrão novo vale só para novos cadastros

---

## Phase 9: User Story 7 - Gerir clientes pelo painel (Priority: P2)

**Goal**: listar com busca/filtros/ordenação, consultar, editar, ativar/desativar, excluir com
sufixo e restaurar com checagem de conflito; mascaramento

**Independent Test**: quickstart passos 23 a 31 e 36

- [ ] T055 [P] [US7] Criar app/Http/Requests/UpdateClientesRequest.php: todos os campos `sometimes` com as mesmas regras do cadastro (T035), inclusive a unicidade de telefone e CPF ignorando o próprio cliente; `password` opcional com `confirmed` e regra forte; `saldo`, `saldo_promocao_esportes`, `saldo_promocao_cassino` e `ativo` → `prohibited` (FR-053)
- [ ] T056 [US7] Criar app/Http/Controllers/ClientesController.php (`HasMiddleware` + `GarantirPermissaoCliente`: `index`/`show` → `Listar`; `update`/`alterar_situacao` → `Editar`; `destroy` → `Excluir`; `excluidos`/`restaurar` → `Restaurar`) com `index(Request)`: valida e aplica `busca` (nome `LIKE %x%`; telefone e CPF por prefixo `LIKE x%` se o usuário tem `clientes.ver_dados_completos`, senão por igualdade exata dos dígitos), `ativo`, `codigo_pais`, `codigo_afiliado`, `cadastro_de`/`cadastro_ate`, `idade_minima`/`idade_maxima` (convertidas em faixa de `data_nascimento`), `genero`, `saldo_minimo`/`saldo_maximo`, `com_saldo_promocional` (esportes ou cassino > 0), `ordenar_por` (`nome`, `created_at`, `saldo`, padrão `nome`), `direcao` (`asc`/`desc`) e `por_pagina` (1–100, padrão 20); extrair a montagem dos filtros para um método privado reutilizado por `excluidos()`; e `show(Clientes $cliente)` (FR-050, FR-052, FR-052a, R-12) (depende de T025, T026)
- [ ] T057 [US7] Em app/Http/Controllers/ClientesController.php, acrescentar `update(UpdateClientesRequest, Clientes $cliente)` (campos não enviados ficam como estão; senha nova só se enviada) e `alterar_situacao(Request, Clientes $cliente)` (valida `ativo` required boolean; ao desativar chama `invalidar_tokens()`) (FR-053, FR-054) (depende de T055, T056)
- [ ] T058 [US7] Em app/Http/Controllers/ClientesController.php, acrescentar `destroy(Clientes $cliente)`: em transação, acrescenta `_deleted_<now()->timestamp>` ao `telefone` e ao `cpf`, chama `invalidar_tokens()` e `delete()` (soft delete); responde `204` (FR-055, R-09) (depende de T056)
- [ ] T059 [US7] Em app/Http/Controllers/ClientesController.php, acrescentar `excluidos(Request)` (só `onlyTrashed()`, mesmos filtros; a busca por telefone/CPF casa com o valor antes do sufixo) e `restaurar(Request, Clientes $cliente)` (rota com `withTrashed()`): não excluído → `422 "Cliente não está excluído."`; calcula os valores originais removendo `/_deleted_\d+$/`; aceita no corpo `codigo_pais`, `telefone` e `cpf` novos (normalizados e validados como no cadastro); verifica conflito com clientes não excluídos e, se houver, responde `422` com `errors.telefone`/`errors.cpf` = "Já está em uso por outro cliente; informe um novo valor."; sem conflito, em transação grava os valores e `restore()`; NÃO reaplica promoção; responde `200` com `ClientesResource` (FR-056, FR-057, FR-065) (depende de T058)
- [ ] T060 [US7] ⚠️ Em routes/api.php, no grupo do painel, acrescentar ANTES do `apiResource`: `GET clientes/excluidos`, `POST clientes/{cliente}/restaurar` (`->withTrashed()`), `PATCH clientes/{cliente}/situacao`; depois `Route::apiResource('clientes', ClientesController::class)->except('store')`; todas as rotas `{cliente}` com `->missing(fn () => abort(404, 'Cliente não encontrado.'))` (depende de T057, T059)

**Checkpoint**: gestão completa, com exclusão/restauração e mascaramento por permissão

---

## Phase 10: User Story 8 - Cadastrar promoções (Priority: P3)

**Goal**: CRUD de promoções por categoria; mesma categoria e modalidade não podem ter períodos sobrepostos

**Independent Test**: quickstart passos 5 a 7 e 37

- [ ] T061 [P] [US8] Criar app/Http/Requests/ClientesPromocoesRequest.php: `nome` required string max:150; `descricao` nullable string; `modalidade` required `Rule::enum(ModalidadePromocao::class)`; `categoria` required `Rule::enum(CategoriaPromocao::class)`; `valor` required numeric gt:0 com até 2 casas; `data_inicio` required date; `data_fim` nullable date `after_or_equal:data_inicio`; `ativa` boolean (no update, regras `sometimes`) (FR-060)
- [ ] T062 [P] [US8] Criar app/Http/Resources/ClientesPromocoesResource.php com os campos do contrato e `vigente` (`$this->vigente()`)
- [ ] T063 [US8] Criar app/Http/Controllers/ClientesPromocoesController.php (`HasMiddleware` + `GarantirPermissaoCliente`, todas as ações exigem `GerenciarPromocoes`): `index` (filtros `ativa`, `modalidade`, `categoria`; `por_pagina` 1–100, padrão 20; ordem `data_inicio` desc), `show`, `store`, `update` e `destroy` (soft delete, `204`). Em `store`/`update`, quando o resultado for promoção `ativa`, recusar com `422 "Já existe uma promoção ativa desta categoria e modalidade no período."` se existir outra ativa, não excluída, da mesma categoria E mesma modalidade (categorias diferentes podem se sobrepor), com período sobreposto (`inicio_a <= fim_b` e `inicio_b <= fim_a`, fim nulo = sem fim), ignorando a própria (FR-061 a FR-063, R-15) (depende de T025, T061, T062)
- [ ] T064 [US8] ⚠️ Em routes/api.php, no grupo do painel, acrescentar `Route::apiResource('clientes_promocoes', ClientesPromocoesController::class)` com parâmetro `promocao` (`->parameters(['clientes_promocoes' => 'promocao'])`) e `->missing(fn () => abort(404, 'Promoção não encontrada.'))` (depende de T063)

**Checkpoint**: promoções gerenciadas pelo painel e aplicadas no cadastro

---

## Phase 11: Polish & Cross-Cutting Concerns

- [ ] T065 ⚠️ Regenerar docs/postman/wssports_api.postman_collection.json SUBSTITUINDO o arquivo (constituição): manter todas as rotas da spec 001 e as variáveis `base_url` e `token`; acrescentar a variável `token_cliente`; criar as pastas "Área do cliente" (10 rotas, com script que preenche `token_cliente` no cadastro, login e refresh) e "Clientes (painel)" (18 rotas, usando `token`), com corpos de exemplo do [contracts/api.md](contracts/api.md)
- [ ] T066 Conferir `php artisan route:list --path=api/area_cliente` (10 rotas) e `php artisan route:list --path=api/clientes` (18 rotas, sem `store` em `clientes`); conferir que as rotas da spec 001 continuam iguais
- [ ] T067 Executar o roteiro completo do [quickstart.md](quickstart.md) (passos 1 a 38) e registrar qualquer divergência
- [ ] T068 Revisão de legibilidade (Princípio V) de todo o código da feature em conjunto: nomes, duplicação entre `ClientesController`/`ClientesTransacoesController`/`AreaClienteMeusDadosController` (filtros e extrato), métodos longos, comentários; confirmar que nenhum arquivo fora da lista do plan.md foi alterado

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sem dependências
- **Foundational (Phase 2)**: depende do Setup — BLOQUEIA todas as stories
- **US1 (Phase 3)**: depende da Foundational
- **US2 (Phase 4)**: depende da Foundational; usa clientes criados pela US1 ou pelo seeder
- **US3 (Phase 5)**: depende da Foundational; validação usa o login da US2
- **US4 (Phase 6)**: depende da Foundational (painel da spec 001 já existe)
- **US5 (Phase 7)**: depende da US2 (token de cliente) e usa transações da US4 para o extrato
- **US6 (Phase 8)**: depende da Foundational
- **US7 (Phase 9)**: depende da Foundational
- **US8 (Phase 10)**: depende da Foundational; a US1 já aplica promoções criadas direto no banco
- **Polish (Phase 11)**: depende de todas as stories

### Within Each Story

- Request/resource antes do controller; controller antes da rota
- Tarefas no mesmo arquivo (`routes/api.php`, `ClientesController.php`) são sequenciais

### Parallel Opportunities

- Phase 2: enums T002–T008 e migrations T009–T014 em paralelo; depois models T016–T020 em
  paralelo (T015 primeiro); T021, T022, T024–T029, T031 em paralelo após os models
- Requests de stories diferentes (T035, T039, T042, T045, T048, T051, T055, T061) em paralelo
- US4, US6, US7 e US8 podem avançar em paralelo após a Foundational (arquivos diferentes, exceto
  `routes/api.php`)

---

## Parallel Example: Phase 2

```bash
Task: "Criar app/Enums/Genero.php"
Task: "Criar app/Enums/Carteira.php"
Task: "Criar app/Enums/PermissaoCliente.php"
Task: "Criar database/migrations/2026_09_29_000001_create_clientes_table.php"
Task: "Criar database/migrations/2026_09_29_000002_create_clientes_transacoes_table.php"
```

## Parallel Example: Requests

```bash
Task: "Criar app/Http/Requests/StoreClientesRequest.php"
Task: "Criar app/Http/Requests/LoginClienteRequest.php"
Task: "Criar app/Http/Requests/ClientesTransacoesRequest.php"
Task: "Criar app/Http/Requests/ClientesConfiguracoesRequest.php"
```

---

## Implementation Strategy

### MVP First

1. Phase 1 (Setup) + Phase 2 (Foundational)
2. Phase 3 (US1 — cadastro) + Phase 4 (US2 — login)
3. **PARAR e VALIDAR** pelos passos 1–11 do quickstart

### Incremental Delivery

1. Setup + Foundational → banco, serviço de saldo e infraestrutura prontos
2. US1 → cadastro público (MVP)
3. US2 → login do cliente
4. US3 → recuperação de senha
5. US4 → movimentação de saldo pelo painel
6. US5 → área do cliente (dados, senha, extrato)
7. US6 → configurações
8. US7 → gestão de clientes (exclusão/restauração)
9. US8 → promoções
10. Polish → Postman, quickstart completo, revisão

---

## Notes

- [P] = arquivos diferentes, sem dependências pendentes
- ⚠️ = arquivo existente com alteração já autorizada (Princípio IV)
- Nenhuma tarefa de teste (constituição)
- Commit manual: o responsável roda `/speckit-git-commit` quando quiser
