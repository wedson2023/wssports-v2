# Contrato HTTP: API de autenticação, usuários e permissões

**Feature**: `001-user_management` | **Data**: 2026-09-28 | **Plano**: [../plan.md](../plan.md)

Rotas em `routes/api.php` (prefixo `/api`):

```php
Route::prefix('auth')->group(function () {
    Route::post('login', [AutenticacaoController::class, 'login']);
    Route::middleware(['auth:api', 'garantir_acesso'])->group(function () {
        Route::post('refresh', [AutenticacaoController::class, 'refresh']);
        Route::post('logout', [AutenticacaoController::class, 'logout']);
    });
});

Route::middleware(['auth:api', 'garantir_acesso'])->group(function () {
    Route::apiResource('usuarios', UsuariosController::class)
        ->missing(fn () => abort(404, 'Usuário não encontrado.'));

    Route::get('usuarios/{usuario}/permissoes', [PermissoesUsuariosController::class, 'index']);
    Route::post('usuarios/{usuario}/permissoes', [PermissoesUsuariosController::class, 'store']);
    Route::delete('usuarios/{usuario}/permissoes/{permissao}', [PermissoesUsuariosController::class, 'destroy']);
});
```

## Regras gerais

- JSON (`Accept: application/json`); rotas protegidas exigem `Authorization: Bearer <token>`.
- `401 {"message": "Não autenticado."}`: sem token, token inválido, expirado ou invalidado.
- `403 {"message": "Usuário sem permissão de acesso."}`: quem solicita está inativo/excluído.
- `403 {"message": "Você não tem permissão para esta ação."}`: o usuário não tem a permissão
  direta da ação (spatie).
- `404 {"message": "Usuário não encontrado."}`: alvo fora da sub-hierarquia, inexistente,
  excluído, o próprio usuário ou o Admin raiz.
- `422`: validação, mensagens em português (`{"message": "...", "errors": {...}}`).
- `password` e `remember_token` nunca aparecem nas respostas.

## Autenticação

| Rota | Corpo | Sucesso | Erros |
|---|---|---|---|
| `POST /api/auth/login` | `{"login": "admin", "password": "password"}` | `200 {"token", "tipo": "bearer", "expira_em": 3600}` | `401 "Login ou senha inválidos."`; `403` inativo; `422`; `429 "Muitas tentativas. Tente novamente em N segundos."` |
| `POST /api/auth/refresh` | — | `200` com novo token (o antigo é invalidado) | `401`; `403` inativo |
| `POST /api/auth/logout` | — | `204` (token invalidado) | `401` |

## Objeto `usuario`

```json
{
  "id": 5,
  "nome": "Plinio",
  "funcao": "Vendedor",
  "login": "plinio",
  "telefone": "(69) 99999-0000",
  "endereco": "Rua A, 10",
  "ativo": true,
  "usuarios_id": 4,
  "created_at": "2026-09-28T14:10:28.000000Z",
  "updated_at": "2026-09-28T14:10:28.000000Z"
}
```

`funcao` vem do papel do spatie (o papel não concede permissões).

## Usuários (`apiResource`)

| Rota | Permissão | Sucesso | Erros específicos |
|---|---|---|---|
| `GET /api/usuarios` | `usuarios.listar` | `200` paginado (`data`, `links`, `meta`), sub-hierarquia sem excluídos | `422` filtros inválidos / `por_pagina` > 100 |
| `POST /api/usuarios` | `usuarios.cadastrar` | `201` com `usuario` (vinculado a quem solicita, ativo, papel = `funcao`) | `422` (login duplicado, `funcao` ≠ nível abaixo, senha < 6, `usuarios_id`/`ativo` enviados) |
| `GET /api/usuarios/{usuario}` | `usuarios.consultar` | `200` com `usuario` | `404` |
| `PUT/PATCH /api/usuarios/{usuario}` | `usuarios.editar` (+ `usuarios.alterar_situacao` se `ativo` vier) | `200` com `usuario`; `ativo` aplica cascata | `404`; `422` (`funcao`/`login` enviados, superior inválido) |
| `DELETE /api/usuarios/{usuario}` | `usuarios.excluir` | `204`, soft delete em cascata | `404` |

Parâmetros de `GET /api/usuarios`: `funcao` (`Supervisor`/`Gerente`/`Vendedor`), `ativo`
(`1`/`0`), `busca` (nome ou login), `por_pagina` (1–100, padrão 15), `page`.

Corpo do `POST`: `nome`, `funcao`, `login`, `password`, `telefone`, `endereco`.
Corpo do `PATCH` (todos opcionais): `nome`, `password`, `telefone`, `endereco`, `ativo`,
`usuarios_id`.

## Permissões do usuário (US6)

Todas exigem `usuarios.gerenciar_permissoes` e que o alvo esteja na sub-hierarquia (senão `404`).

### `GET /api/usuarios/{usuario}/permissoes`

```json
{
  "permissoes": ["usuarios.listar", "usuarios.consultar", "usuarios.cadastrar"]
}
```

### `POST /api/usuarios/{usuario}/permissoes` — dar

```json
{ "permissao": "usuarios.excluir" }
```

| Status | Quando |
|---|---|
| `200` | Permissão concedida; retorna o mesmo objeto do `GET` |
| `403` | Quem solicita não tem essa permissão (FR-038): `{"message": "Você só pode conceder permissões que possui."}` |
| `422` | Permissão inexistente |

### `DELETE /api/usuarios/{usuario}/permissoes/{permissao}` — tirar

| Status | Quando |
|---|---|
| `200` | Vínculo usuário–permissão removido; retorna o objeto do `GET` |
| `422` | Permissão inexistente ou que o usuário não tem |
