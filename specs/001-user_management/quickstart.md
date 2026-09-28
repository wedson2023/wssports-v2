# Quickstart: validação manual do Gerenciamento de Usuários

**Feature**: `001-user_management` | **Plano**: [plan.md](plan.md)

Sem testes automatizados (constituição v1.4.0). Validação por requisições HTTP. Campos e
respostas: [data-model.md](data-model.md) e [contracts/api.md](contracts/api.md).

## Pré-requisitos

- `composer install` com os pacotes `php-open-source-saver/jwt-auth` e
  `spatie/laravel-permission`.
- `php artisan jwt:secret` (gera `JWT_SECRET` no `.env`).
- Cliente HTTP (Postman, Insomnia ou `curl`) com `Accept: application/json`.

## 1. Atualizar o banco sem apagar o legado

```bash
php artisan migrate:refresh --seed
```

⚠️ **Nunca use `migrate:fresh`**: apagaria as tabelas do legado ([research.md](research.md) R-15).

**Esperado**: `usuarios` sem `email`/`funcao`; tabelas `roles`, `permissions`, `model_has_roles`,
`model_has_permissions` e `role_has_permissions` (vazia); 4 papéis e 7 permissões;
Admin raiz (`admin` / `password`), 1 Supervisor, 1 Gerente e 2 Vendedores, cada um com seu
papel e com as permissões padrão da função em `model_has_permissions`. Tabelas do legado intactas.

## 2. Conferir as rotas

```bash
php artisan serve
php artisan route:list --path=api
```

**Esperado**: 3 rotas de `auth`, 5 de `usuarios` (sem `create`/`edit`) e 3 de `permissoes`.

## 3. Roteiro

| # | Ação | Esperado | Ref. |
|---|---|---|---|
| 1 | Login `admin`/`password` | `200` com token | US1 |
| 2 | `GET /api/usuarios` sem token | `401 Não autenticado.` | FR-029 |
| 3 | 5 logins errados + 1 certo (mesmo login/IP) | 6º → `429` com tempo de espera | FR-033 |
| 4 | Admin lista usuários | Todos, exceto o Admin; `funcao` preenchida; sem `password` | US3 |
| 5 | Gerente lista usuários | Só os 2 Vendedores | FR-004 |
| 6 | Gerente cadastra Vendedor | `201`; `usuarios_id` = Gerente; `funcao` Vendedor | US2 |
| 7 | Gerente cadastra Supervisor | `422` | US2 |
| 8 | Login repetido em maiúsculas | `422` | FR-009 |
| 9 | Vendedor tenta `POST /api/usuarios` | `403` (sem permissão) | FR-034 |
| 10 | Gerente consulta a si mesmo e o Admin | `404` | FR-022 |
| 11 | Gerente edita telefone de Vendedor seu, sem senha | `200`; senha antiga continua válida | US4 |
| 12 | `PATCH` com `funcao` ou `login` | `422` | FR-013 |
| 13 | `GET /api/usuarios?por_pagina=101` | `422` | Constituição |
| 14 | Supervisor desativa o Gerente | Gerente e Vendedores inativos | FR-018 |
| 15 | Login de Vendedor inativo | `403` | FR-028 |
| 16 | Supervisor reativa o Gerente | Todos ativos | FR-018 |
| 17 | Supervisor: `DELETE /api/usuarios/{gerente}/permissoes/usuarios.excluir` | `200`; `usuarios.excluir` some da lista do Gerente (linha apagada de `model_has_permissions`) | US6 |
| 18 | Gerente tenta excluir um Vendedor | `403` (sem a permissão) | FR-036 |
| 19 | Supervisor devolve (`POST .../permissoes` com `usuarios.excluir`) | Gerente volta a excluir | US6 |
| 20 | Gerente sem `usuarios.excluir` tenta dá-la a um Vendedor | `403 Você só pode conceder permissões que possui.` | FR-038 |
| 21 | Supervisor exclui o Gerente | `204`; Gerente e Vendedores somem da listagem e ficam com `deleted_at` | FR-019 |
| 22 | `refresh` com token válido | Novo token; o antigo dá `401` | FR-031 |
| 23 | `logout` e reusar o token | `204`; depois `401` | FR-032 |
| 24 | Listagem com 1.000 Vendedores (criados via `tinker` + factory) | < 2 s | SC-005 |
