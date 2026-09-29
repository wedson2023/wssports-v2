# Research: Gerenciamento de Usuários

**Feature**: `001-user_management` | **Data**: 2026-09-28 | **Plano**: [plan.md](plan.md)

Decisões técnicas que resolvem as incógnitas do Technical Context. Formato: decisão, motivo e
alternativas avaliadas.

## R-01. Pacote JWT

- **Decisão**: `php-open-source-saver/jwt-auth`, guard `api` com driver `jwt`.
- **Motivo**: compatível com Laravel 12; TTL configurável (60 min é o padrão), renovação
  (`refresh`) e *blacklist* para invalidar tokens no logout (FR-027, FR-031, FR-032). A blacklist
  usa o cache da aplicação (`CACHE_STORE=database`, tabela `cache` já existente).
- **Alternativas**: `tymon/jwt-auth` (sem manutenção ativa); Sanctum (tokens opacos, não JWT);
  `firebase/php-jwt` puro (reimplementa refresh e blacklist).

## R-02. Pacote de permissões

- **Decisão**: `spatie/laravel-permission` (clarificação), com os papéis e permissões no guard
  `api`. O model `Usuarios` usa a trait `HasRoles` e `protected $guard_name = 'api'`.
- **Motivo**: pedido do responsável (FR-034), em substituição às Policies do Laravel.
- **Alternativas**: Policies (descartadas pelo responsável).

## R-03. Tabelas do spatie com os nomes padrão do pacote (exceção)

- **Decisão**: manter a migration e o `config/permission.php` do spatie **sem renomear nada**:
  tabelas `roles`, `permissions`, `model_has_roles`, `model_has_permissions` e
  `role_has_permissions`, com as colunas padrão (`name`, `guard_name`, `role_id`,
  `permission_id`, `model_type`, `model_id`). Essas tabelas não recebem `deleted_at`, e as pivô
  não recebem timestamps.
- **Motivo**: exceção pedida pelo responsável, para manter o mesmo padrão do seu outro sistema.
  Além disso, o spatie não reconhece soft delete (um papel "excluído" continuaria valendo).
- **Constituição**: coberto pela exceção de tabelas de pacotes de terceiros do Princípio I
  (constituição v1.6.0).
- **Alternativas**: renomear tabelas e chaves para português via `config/permission.php`
  (versão anterior deste plano) — descartada pelo responsável.

## R-04. Permissões diretas no usuário (modelo do outro sistema do responsável)

- **Decisão**: o papel identifica apenas a função e **não** tem permissões (`role_has_permissions`
  fica vazia). Cada usuário recebe permissões diretas (`model_has_permissions`):
  - **Cadastro**: o novo usuário recebe `Funcao::permissoes_padrao()` da sua função, filtradas
    pelas permissões que o cadastrante possui (FR-036, FR-038).
  - **Dar**: `givePermissionTo()` (exige que o solicitante tenha a permissão — FR-038).
  - **Tirar**: `revokePermissionTo()`, que apaga o vínculo usuário–permissão.
- **Motivo**: é o padrão já usado pelo responsável em outro sistema e um uso documentado do
  spatie (permissões diretas). Torna "tirar só deste usuário" nativo, sem tabela extra nem
  sobrescrita de métodos do pacote.
- **Alternativas**: permissões no papel + tabela `permissoes_negadas` com `hasPermissionTo()`
  sobrescrito (versão anterior deste plano) — mais complexa e sem necessidade com permissões
  diretas.
- **Consequência**: mudar a lista padrão de uma função não altera usuários já cadastrados.

## R-05. Função do usuário como papel (sem coluna `funcao`)

- **Decisão**: sem coluna `funcao`. O enum `App\Enums\Funcao` (Admin, Supervisor, Gerente,
  Vendedor) define os nomes dos papéis e a ordem hierárquica. O model expõe `funcao(): ?Funcao`,
  lido do único papel do usuário. Listagem com `with('roles')` para evitar N+1; filtro por função
  com o escopo `role()` do spatie.
- **Motivo**: clarificação (papel como única fonte de verdade).

## R-06. Permissões e distribuição padrão

- **Decisão**: permissões (guard `api`): `usuarios.listar`, `usuarios.consultar`,
  `usuarios.cadastrar`, `usuarios.editar`, `usuarios.alterar_situacao`, `usuarios.excluir`,
  `usuarios.gerenciar_permissoes`. Padrão por função, definido em `Funcao::permissoes_padrao()`:
  Admin, Supervisor e Gerente todas; Vendedor nenhuma. Papéis e permissões criados por um seeder
  próprio (`PapeisPermissoesSeeder`), chamado pelo `DatabaseSeeder`; o Admin raiz recebe todas
  as permissões diretamente.
- **Motivo**: a spec deixou a distribuição para o plano; todo gestor nasce podendo gerir a própria
  equipe, e o ajuste fino é feito pela US6. Os nomes seguem o Princípio I (português,
  `snake_case`).
- **Confirmado** pelo responsável em 2026-09-28.

## R-07. Onde ficam as checagens (sem Policies)

- **Decisão**:
  1. Rotas protegidas por `auth:api` e pelo middleware próprio `garantir_acesso`
     (`App\Http\Middleware\GarantirAcesso`), que devolve `403` se o usuário não `pode_acessar()`
     (FR-021).
  2. Permissão de cada ação pelo middleware `permission:` do spatie, declarado no controller
     (`HasMiddleware`) por método, exceto o `update`, que checa por campo: `usuarios.editar` para
     campos de dados e `usuarios.alterar_situacao` para `ativo`, de forma independente (decisão
     U1 do responsável); `alterar_situacao` é checada no `update` quando `ativo` vem na
     requisição.
  3. Sub-hierarquia (FR-004, FR-022, FR-035) no controller: se o alvo não está em
     `ids_sub_hierarquia()` de quem solicita, `abort(404, 'Usuário não encontrado.')`.
- **Motivo**: separa "o que pode" (spatie) de "sobre quem" (regra de negócio), conforme FR-035.
- **Alternativas**: Policies (descartadas pelo responsável).

## R-08. Registro de `routes/api.php`

- **Decisão**: criar `routes/api.php` e registrá-lo em `bootstrap/app.php`
  (`api: __DIR__.'/../routes/api.php'`).
- **Motivo**: `php artisan install:api` instalaria o Sanctum, desnecessário com JWT.

## R-09. Rotas

- **Decisão**: `POST /api/auth/login`, `POST /api/auth/refresh`, `POST /api/auth/logout`;
  `Route::apiResource('usuarios', ...)` (resource sem `create`/`edit`, FR-024); permissões do
  usuário em `GET|POST /api/usuarios/{usuario}/permissoes` e
  `DELETE /api/usuarios/{usuario}/permissoes/{permissao}`.

## R-10. Login, usuários inativos e limite de tentativas

- **Decisão**: `auth('api')->attempt(['login' => trim($login), 'password' => $senha])`; excluídos
  já são ignorados pelo provider (`SoftDeletes`) → `401` genérico; inativo → `403`. Limite com
  `RateLimiter` no `LoginRequest`, chave `mb_strtolower(login)|ip`, 5 tentativas/60 s → `429`
  com os segundos de espera.
- **Motivo**: FR-027, FR-028, FR-033, sem dependência extra.

## R-11. Mensagens em português

- **Decisão**: em `bootstrap/app.php` (`withExceptions`), JSON de `AuthenticationException` →
  `401 "Não autenticado."` e de `Spatie\Permission\Exceptions\UnauthorizedException` →
  `403 "Você não tem permissão para esta ação."`; rotas com `->missing()` → `404 "Usuário não
  encontrado."`; Form Requests com `messages()` em português.
- **Motivo**: FR-023; as mensagens padrão do Laravel e do spatie são em inglês.

## R-12. Sub-hierarquia, cascata e troca de superior

- **Decisão**: sub-hierarquia nível a nível (uma consulta `whereIn` por nível, no máximo 4);
  cascata de `ativo` e soft delete em lote dentro de `DB::transaction()`; novo superior deve
  existir, não estar excluído, ter a função imediatamente acima e ser o solicitante ou alguém da
  sua sub-hierarquia (impede ciclos, FR-006).

## R-13. Paginação

- **Decisão**: padrão 15, `por_pagina` de 1 a 100; acima de 100 → `422` (constituição v1.5.0).

## R-14. Login único sem diferenciar maiúsculas

- **Decisão**: `trim()`; índice `unique` + validação `LOWER(login) = LOWER(?)` com
  `withTrashed()` (FR-009).

## R-15. Banco local sem apagar o legado

- **Decisão**: aplicar com `php artisan migrate:refresh --seed`; `migrate:fresh` é proibido.
- **Motivo**: o banco `wssports` tem 248 tabelas do legado e as 3 migrations do projeto (batch 1).
  O `refresh` só desfaz/refaz migrations registradas; a tabela `usuarios` atual está vazia.

## R-16. Validação sem testes automatizados

- **Decisão**: nenhum teste (constituição v1.4.0); roteiro manual no [quickstart.md](quickstart.md).
