---

description: "Lista de tarefas da feature Gerenciamento de Usuários"
---

# Tasks: Gerenciamento de Usuários

**Input**: Documentos de design em `specs/001-user_management/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md),
[data-model.md](data-model.md), [contracts/api.md](contracts/api.md), [quickstart.md](quickstart.md)

**Tests**: NÃO há tarefas de teste — a constituição (v1.4.0) proíbe testes automatizados. Cada
user story é validada manualmente pelos passos do [quickstart.md](quickstart.md).

**Organization**: tarefas agrupadas por user story, na ordem de prioridade da spec.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: pode rodar em paralelo (arquivos diferentes, sem dependências pendentes)
- **[Story]**: user story da tarefa (US1 a US6)
- Caminhos relativos à raiz do repositório

## Regras que valem para TODAS as tarefas

- **Princípio I**: métodos, variáveis, parâmetros, chaves e nomes de permissões em `snake_case`;
  classes em `PascalCase`; métodos exigidos pelo framework/pacotes mantêm o nome original
  (`getJWTIdentifier`, `getJWTCustomClaims`, `middleware`, `rules`, `messages`, `authorize`,
  `prepareForValidation`, `toArray`, `definition`, `casts`, `up`, `down`, `run`, `handle`).
  Tabelas do spatie mantêm os nomes padrão do pacote (exceção da constituição v1.6.0).
- **Princípio II**: comentários no código e mensagens de erro em português.
- **Princípio IV**: alterar só os arquivos e trechos indicados. Os arquivos existentes marcados
  com ⚠️ já tiveram a alteração autorizada pelo responsável (plan.md, 2026-09-28).
- **Princípio V**: ao concluir cada tarefa, revisar em conjunto o código alterado (nomes,
  duplicação, métodos longos, condicionais aninhadas, comentários) e informar se houve ou não
  refatoração.
- **Banco**: NUNCA rodar `php artisan migrate:fresh` (apagaria o legado); usar
  `php artisan migrate:refresh --seed`.
- **Paginação**: nenhuma listagem retorna mais de 100 registros por página.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: dependências e configuração base

- [ ] T001 Instalar os pacotes com `composer require php-open-source-saver/jwt-auth spatie/laravel-permission` ⚠️ (atualiza composer.json e composer.lock)
- [ ] T002 Publicar a configuração do JWT (`php artisan vendor:publish --provider="PHPOpenSourceSaver\JWTAuth\Providers\LaravelServiceProvider"`, gera config/jwt.php) e gerar a chave com `php artisan jwt:secret` ⚠️ (grava `JWT_SECRET` no .env); acrescentar `JWT_SECRET=` ao .env.example ⚠️
- [ ] T003 Publicar a configuração e a migration do spatie (`php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"`, gera config/permission.php e database/migrations/xxxx_create_permission_tables.php) SEM renomear tabelas ou colunas (research.md R-03)
- [ ] T004 ⚠️ Em config/auth.php, acrescentar o guard `'api' => ['driver' => 'jwt', 'provider' => 'users']`, sem alterar o guard `web` nem o provider `users` (que já aponta para `App\Models\Usuarios`)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: estrutura de dados, model, rotas e tratamento de erros usados por todas as stories

**⚠️ CRITICAL**: nenhuma user story começa antes desta fase terminar

- [ ] T005 [P] Criar o enum `App\Enums\Funcao` (backed `string`) em app/Enums/Funcao.php com os casos `Admin = 'Admin'`, `Supervisor = 'Supervisor'`, `Gerente = 'Gerente'`, `Vendedor = 'Vendedor'` e os métodos `nivel(): int` (1 a 4), `funcao_abaixo(): ?self` (Admin→Supervisor, Supervisor→Gerente, Gerente→Vendedor, Vendedor→null), `funcao_acima(): ?self` (Supervisor→Admin, Gerente→Supervisor, Vendedor→Gerente, Admin→null) e `permissoes_padrao(): array` (Admin, Supervisor e Gerente: `usuarios.listar`, `usuarios.consultar`, `usuarios.cadastrar`, `usuarios.editar`, `usuarios.alterar_situacao`, `usuarios.excluir`, `usuarios.gerenciar_permissoes`; Vendedor: `[]`)
- [ ] T006 [P] ⚠️ Alterar SOMENTE o bloco `Schema::create('usuarios')` em database/migrations/0001_01_01_000000_create_usuarios_table.php para: `id()`; `string('nome')`; `string('login')->unique()`; `string('password')`; `string('telefone', 20)->nullable()`; `string('endereco')->nullable()`; `boolean('ativo')->default(true)`; `foreignId('usuarios_id')->nullable()->constrained('usuarios')` (sem `cascadeOnDelete`); `rememberToken()`; `timestamps()`; `softDeletes()`. Remover `email` e `email_verified_at`; NÃO criar coluna `funcao`; NÃO alterar `password_reset_tokens` nem `sessions`
- [ ] T007 ⚠️ Alterar o model em app/Models/Usuarios.php: traits `HasFactory`, `Notifiable`, `SoftDeletes`, `HasRoles`; implementar `PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject` (`getJWTIdentifier()` retorna `getKey()`, `getJWTCustomClaims()` retorna `[]`); `protected $guard_name = 'api'`; `$fillable` = `nome`, `login`, `password`, `telefone`, `endereco`, `ativo`, `usuarios_id`; `$hidden` = `password`, `remember_token`; `casts()` = `ativo => 'boolean'`, `password => 'hashed'` (remover `email_verified_at`); corrigir o import/docblock da factory para `UsuariosFactory`; relacionamentos `superior()` (`belongsTo(Usuarios::class, 'usuarios_id')`) e `subordinados()` (`hasMany(Usuarios::class, 'usuarios_id')`) (depende de T001, T006)
- [ ] T008 Adicionar ao model app/Models/Usuarios.php: `funcao(): ?Funcao` (lê o nome do único papel com `getRoleNames()->first()` e converte com `Funcao::tryFrom`); `ids_sub_hierarquia(): array` (percorre a árvore nível a nível, uma consulta `whereIn('usuarios_id', ...)` por nível, ignorando excluídos, sem CTE recursiva); `gerencia(Usuarios $alvo): bool` (alvo está em `ids_sub_hierarquia()`; nunca o próprio usuário); `pode_acessar(): bool` (`ativo` e não excluído) (depende de T005, T007)
- [ ] T009 [P] Criar o middleware app/Http/Middleware/GarantirAcesso.php: se o usuário autenticado não `pode_acessar()`, responder `403` com `{"message": "Usuário sem permissão de acesso."}`; caso contrário seguir
- [ ] T010 [P] Criar o seeder database/seeders/PapeisPermissoesSeeder.php: limpar o cache do spatie (`app(PermissionRegistrar::class)->forgetCachedPermissions()`), criar com `firstOrCreate` as 7 permissões (guard `api`) e os 4 papéis de `Funcao` (guard `api`), SEM vincular permissões aos papéis (`role_has_permissions` fica vazia — R-04) (depende de T005)
- [ ] T011 ⚠️ Alterar a factory em database/factories/UsuariosFactory.php: `definition()` com `nome`, `login` = `fake()->unique()->userName()`, `password` (hash de `'password'`), `telefone`, `endereco`, `ativo` = `true`, `usuarios_id` = `null`, `remember_token`; remover `email`, `email_verified_at` e `unverified()`; estados `admin_raiz()` (sem superior; `afterCreating` atribui o papel `Admin` e todas as `Funcao::Admin->permissoes_padrao()`), `subordinado_de(Usuarios $superior)` (`usuarios_id` = superior; `afterCreating` atribui o papel `funcao_abaixo()` do superior e as `permissoes_padrao()` dessa função) e `inativo()` (`ativo` = `false`) (depende de T005, T007, T010)
- [ ] T012 ⚠️ Alterar database/seeders/DatabaseSeeder.php: chamar `PapeisPermissoesSeeder` e criar, via factory, o Admin raiz (nome `Administrador`, login `admin`), 1 Supervisor abaixo dele, 1 Gerente abaixo do Supervisor e 2 Vendedores abaixo do Gerente (depende de T010, T011)
- [ ] T013 [P] Criar o API Resource app/Http/Resources/UsuariosResource.php retornando `id`, `nome`, `funcao` (`$this->funcao()?->value`), `login`, `telefone`, `endereco`, `ativo`, `usuarios_id`, `created_at`, `updated_at`; NUNCA `password`, `remember_token` nem `deleted_at` (depende de T008)
- [ ] T014 [P] Criar routes/api.php vazio (apenas `use` de `Route`), a ser preenchido pelas user stories
- [ ] T015 ⚠️ Alterar bootstrap/app.php: em `withRouting` acrescentar `api: __DIR__.'/../routes/api.php'`; em `withMiddleware` registrar os aliases `permission` (`Spatie\Permission\Middleware\PermissionMiddleware`) e `garantir_acesso` (`App\Http\Middleware\GarantirAcesso`); em `withExceptions`, para requisições JSON, renderizar `AuthenticationException` como `401 {"message": "Não autenticado."}` e `Spatie\Permission\Exceptions\UnauthorizedException` como `403 {"message": "Você não tem permissão para esta ação."}` (depende de T009, T014)
- [ ] T016 Rodar `php artisan migrate:refresh --seed` (NUNCA `migrate:fresh`) e conferir: `usuarios` sem `email`/`funcao`; tabelas `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`; 4 papéis, 7 permissões; 5 usuários com papel e permissões padrão; tabelas do legado intactas (depende de T003, T006, T012, T015)

**Checkpoint**: estrutura pronta — as user stories podem começar

---

## Phase 3: User Story 1 - Autenticar com login e senha (Priority: P1) 🎯 MVP

**Goal**: login gera token JWT de 60 min; renovação e logout; limite de tentativas

**Independent Test**: quickstart.md passos 1, 2, 3, 22 e 23

- [ ] T017 [US1] Criar app/Http/Requests/LoginRequest.php: `rules()` com `login` "obrigatório, texto" e `password` "obrigatório, texto"; `prepareForValidation()` com `trim()` no `login`; `messages()` em português; métodos `garantir_limite_tentativas()` (se `RateLimiter::tooManyAttempts($this->chave_limite(), 5)`, responder `429` com "Muitas tentativas. Tente novamente em N segundos.", N = `RateLimiter::availableIn`), `registrar_tentativa_falha()` (`RateLimiter::hit(chave, 60)`), `limpar_tentativas()` e `chave_limite()` (`mb_strtolower(login).'|'.ip()`) (research.md R-10)
- [ ] T018 [US1] Criar app/Http/Controllers/AutenticacaoController.php com: `login(LoginRequest $request)` — aplica o limite, tenta `auth('api')->attempt(['login' => ..., 'password' => ...])`; falha → registra tentativa e `401 {"message": "Login ou senha inválidos."}`; sucesso com usuário que não `pode_acessar()` → invalida o token e `403 {"message": "Usuário sem permissão de acesso."}`; sucesso → limpa tentativas e `200 {"token", "tipo": "bearer", "expira_em": ttl*60}`; `refresh()` — `auth('api')->refresh()` com o mesmo formato de resposta; `logout()` — `auth('api')->logout()` e `204` (depende de T017)
- [ ] T019 [US1] Acrescentar em routes/api.php o grupo `prefix('auth')`: `POST login` (sem middleware) e, com `['auth:api', 'garantir_acesso']`, `POST refresh` e `POST logout` apontando para `AutenticacaoController` (depende de T018)

**Checkpoint**: US1 validável isoladamente pelo quickstart

---

## Phase 4: User Story 2 - Cadastrar usuário subordinado (Priority: P1)

**Goal**: gestor cadastra a função imediatamente abaixo, vinculada a ele, com papel e permissões padrão

**Independent Test**: quickstart.md passos 6, 7, 8 e 9

- [ ] T020 [US2] Adicionar ao model app/Models/Usuarios.php o método `atribuir_permissoes_padrao(Usuarios $cadastrante): void` — `givePermissionTo()` das `funcao()->permissoes_padrao()` que o cadastrante também possui (`$cadastrante->hasPermissionTo(...)`) (FR-036, FR-038)
- [ ] T021 [P] [US2] Criar app/Http/Requests/StoreUsuariosRequest.php: `prepareForValidation()` com `trim()` no `login`; `rules()`: `nome` "obrigatório, texto, até 255"; `funcao` "obrigatório, DEVE ser igual a `funcao_abaixo()` de quem cadastra" (`Rule::enum(Funcao::class)` + closure); `login` "obrigatório, até 255, único sem diferenciar caixa, incluindo excluídos" (closure com `Usuarios::withTrashed()->whereRaw('LOWER(login) = ?', [mb_strtolower($valor)])`); `password` "obrigatório, mínimo 6"; `telefone` "opcional, até 20"; `endereco` "opcional, até 255"; `usuarios_id` e `ativo` `prohibited`; `messages()` em português (ex.: "O login informado já está em uso.")
- [ ] T022 [US2] Criar app/Http/Controllers/UsuariosController.php implementando `HasMiddleware`, com `middleware()` retornando `permission:usuarios.listar` (only `index`), `permission:usuarios.consultar` (only `show`), `permission:usuarios.cadastrar` (only `store`), `permission:usuarios.editar` (only `update`), `permission:usuarios.excluir` (only `destroy`); método privado `garantir_gerencia(Request $request, Usuarios $alvo): void` que faz `abort(404, 'Usuário não encontrado.')` se `! $request->user()->gerencia($alvo)`; e `store(StoreUsuariosRequest $request)`: em `DB::transaction`, cria o usuário com os dados validados, `usuarios_id` = id de quem solicita, `ativo` = true, `assignRole($funcao)` e `atribuir_permissoes_padrao($request->user())`; retorna `UsuariosResource` (201) (depende de T013, T020, T021)
- [ ] T023 [US2] Acrescentar em routes/api.php, dentro de `middleware(['auth:api', 'garantir_acesso'])`, `Route::apiResource('usuarios', UsuariosController::class)->missing(fn () => abort(404, 'Usuário não encontrado.'))` (depende de T022)

**Checkpoint**: US1 e US2 funcionam

---

## Phase 5: User Story 3 - Listar e consultar a própria hierarquia (Priority: P1)

**Goal**: listagem paginada da sub-hierarquia com filtros e busca; consulta individual com 404 fora da equipe

**Independent Test**: quickstart.md passos 4, 5, 10 e 13

- [ ] T024 [US3] Implementar `index(Request $request)` em app/Http/Controllers/UsuariosController.php: validar `funcao` (`Rule::enum(Funcao::class)`), `ativo` (booleano), `busca` (texto até 255), `por_pagina` (inteiro entre 1 e 100) com mensagens em português; consulta `Usuarios::with('roles')->whereIn('id', $request->user()->ids_sub_hierarquia())`, filtro de função com o escopo `role($funcao)` do spatie, filtro `ativo`, `busca` com `LIKE` em `nome` ou `login`; `orderBy('nome')`; `paginate($por_pagina ?? 15)->withQueryString()`; retornar `UsuariosResource::collection` (depende de T022)
- [ ] T025 [US3] Implementar `show(Request $request, Usuarios $usuario)` em app/Http/Controllers/UsuariosController.php: `garantir_gerencia()` e retornar `UsuariosResource` (depende de T022)

**Checkpoint**: US1 a US3 funcionam

---

## Phase 6: User Story 4 - Editar dados de um subordinado (Priority: P2)

**Goal**: editar nome, senha, telefone, endereço e superior; função e login imutáveis

**Independent Test**: quickstart.md passos 11 e 12

- [ ] T026 [P] [US4] Criar app/Http/Requests/UpdateUsuariosRequest.php: `rules()`: `nome` `sometimes|string|max:255`; `password` "opcional, mínimo 6; ausente mantém" (`sometimes|nullable|string|min:6`); `telefone` "opcional, até 20"; `endereco` "opcional, até 255"; `ativo` `sometimes|boolean`; `usuarios_id` "opcional; o novo superior DEVE existir, não estar excluído, ter a função imediatamente acima da do usuário e ser quem solicita ou alguém da sua sub-hierarquia" (closure com `funcao()->funcao_acima()` e `ids_sub_hierarquia()`); `funcao` e `login` `prohibited`; `messages()` em português
- [ ] T027 [US4] Implementar `update(UpdateUsuariosRequest $request, Usuarios $usuario)` em app/Http/Controllers/UsuariosController.php: `garantir_gerencia()`; aplicar os campos validados exceto `password` e `ativo`; gravar `password` só se preenchida; retornar `UsuariosResource` (o tratamento de `ativo` é feito na US5) (depende de T022, T026)

**Checkpoint**: US1 a US4 funcionam

---

## Phase 7: User Story 5 - Desativar, reativar e excluir (Priority: P2)

**Goal**: cascata de situação e exclusão lógica em toda a sub-hierarquia

**Independent Test**: quickstart.md passos 14, 15, 16 e 21

- [ ] T028 [US5] Adicionar ao model app/Models/Usuarios.php: `alterar_situacao_em_cascata(bool $ativo): void` (um `update(['ativo' => $ativo])` com `whereIn('id', [id + ids_sub_hierarquia()])` em `DB::transaction`) e `excluir_em_cascata(): void` (mesmo conjunto com `delete()` — soft delete — em `DB::transaction`) (research.md R-12)
- [ ] T029 [US5] Em app/Http/Controllers/UsuariosController.php: no `update`, se `ativo` vier e for diferente do atual, exigir `usuarios.alterar_situacao` (`abort_unless($request->user()->hasPermissionTo('usuarios.alterar_situacao'), 403, 'Você não tem permissão para esta ação.')`) e chamar `alterar_situacao_em_cascata()`; implementar `destroy(Request $request, Usuarios $usuario)` com `garantir_gerencia()`, `excluir_em_cascata()` e `204` (depende de T027, T028)

**Checkpoint**: US1 a US5 funcionam

---

## Phase 8: User Story 6 - Ajustar permissões de um subordinado (Priority: P3)

**Goal**: consultar, dar e tirar permissões diretas de um subordinado

**Independent Test**: quickstart.md passos 17, 18, 19 e 20

- [ ] T030 [P] [US6] Criar app/Http/Requests/PermissaoUsuarioRequest.php: `rules()` com `permissao` "obrigatório, deve existir no guard `api`" (`Rule::exists('permissions', 'name')->where('guard_name', 'api')`); `messages()` em português
- [ ] T031 [US6] Criar app/Http/Controllers/PermissoesUsuariosController.php implementando `HasMiddleware` com `permission:usuarios.gerenciar_permissoes`; métodos `index(Request, Usuarios $usuario)` (404 se fora da sub-hierarquia; retorna `{"permissoes": [...]}` com `getPermissionNames()`), `store(PermissaoUsuarioRequest, Usuarios $usuario)` (404 fora da equipe; `403 "Você só pode conceder permissões que possui."` se o solicitante não tem a permissão; `givePermissionTo()`; retorna o objeto do `index`) e `destroy(Request, Usuarios $usuario, string $permissao)` (404 fora da equipe; `422` se a permissão não existe ou o usuário não a tem; `revokePermissionTo()`; retorna o objeto do `index`) (depende de T008, T030)
- [ ] T032 [US6] Acrescentar em routes/api.php, no mesmo grupo autenticado, `GET` e `POST usuarios/{usuario}/permissoes` e `DELETE usuarios/{usuario}/permissoes/{permissao}` para `PermissoesUsuariosController`, cada rota com `->missing(fn () => abort(404, 'Usuário não encontrado.'))` (depende de T031)

**Checkpoint**: todas as user stories funcionam

---

## Phase 9: Polish & Cross-Cutting Concerns

- [ ] T033 Rodar `vendor/bin/pint --dirty` para formatar só os arquivos alterados nesta feature
- [ ] T034 Rodar `php artisan route:list --path=api` e confirmar 3 rotas de `auth`, 5 de `usuarios` (sem `create`/`edit`) e 3 de `permissoes`
- [ ] T035 Executar manualmente todos os passos de specs/001-user_management/quickstart.md (incluindo o de 1.000 Vendedores, SC-005) e registrar o resultado de cada um
- [ ] T036 Revisão de legibilidade final (Princípio V) do conjunto: app/Enums/Funcao.php, app/Models/Usuarios.php, app/Http/Middleware/GarantirAcesso.php, app/Http/Controllers/AutenticacaoController.php, app/Http/Controllers/UsuariosController.php, app/Http/Controllers/PermissoesUsuariosController.php, app/Http/Requests/*.php, app/Http/Resources/UsuariosResource.php, routes/api.php, database/seeders/PapeisPermissoesSeeder.php; informar explicitamente se houve ou não refatoração

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sem dependências
- **Foundational (Phase 2)**: depende do Setup — BLOQUEIA todas as stories
- **User Stories (Phases 3–8)**: dependem da Foundational
- **Polish (Phase 9)**: depende das stories desejadas

### User Story Dependencies

- **US1 (P1)**: só Foundational — é a porta de entrada (sem token não há como validar as demais)
- **US2 (P1)**: Foundational; cria `UsuariosController` e a rota `apiResource`
- **US3 (P1)**: depende de US2 (usa o `UsuariosController` e a rota criados em T022/T023)
- **US4 (P2)**: depende de US2
- **US5 (P2)**: depende de US4 (T029 altera o `update` criado em T027)
- **US6 (P3)**: só Foundational (controller e rotas próprios)

### Within Each Story

- Request/model antes do controller; controller antes da rota
- Tarefas no mesmo arquivo (`UsuariosController.php`, `Usuarios.php`, `routes/api.php`) são sequenciais

### Parallel Opportunities

- Phase 2: T005, T006, T009, T014 em paralelo; T010, T013 após as dependências indicadas
- T021 (US2), T026 (US4) e T030 (US6) — Form Requests em arquivos próprios
- US6 pode ser feita em paralelo a US3–US5 (arquivos diferentes, exceto `routes/api.php`)

---

## Parallel Example: Phase 2

```bash
Task: "Criar o enum App\Enums\Funcao em app/Enums/Funcao.php"
Task: "Alterar o bloco usuarios da migration 0001_01_01_000000_create_usuarios_table.php"
Task: "Criar o middleware app/Http/Middleware/GarantirAcesso.php"
Task: "Criar routes/api.php vazio"
```

## Parallel Example: Form Requests

```bash
Task: "Criar app/Http/Requests/StoreUsuariosRequest.php"
Task: "Criar app/Http/Requests/UpdateUsuariosRequest.php"
Task: "Criar app/Http/Requests/PermissaoUsuarioRequest.php"
```

---

## Implementation Strategy

### MVP First

1. Phase 1 (Setup) + Phase 2 (Foundational)
2. Phase 3 (US1 — autenticação) + Phase 4 (US2 — cadastro)
3. **PARAR e VALIDAR** pelos passos 1–3 e 6–9 do quickstart

### Incremental Delivery

1. Setup + Foundational → banco e estrutura prontos
2. US1 → login, renovação e logout
3. US2 → cadastro (MVP)
4. US3 → listagem e consulta
5. US4 → edição
6. US5 → desativar, reativar e excluir em cascata
7. US6 → ajuste de permissões
8. Polish

---

## Notes

- [P] = arquivos diferentes, sem dependências pendentes
- ⚠️ = arquivo existente com alteração já autorizada (Princípio IV)
- Nenhuma tarefa de teste (constituição v1.4.0)
- Commit manual: o responsável roda `/speckit-git-commit` quando quiser
