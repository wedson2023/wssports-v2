# Data Model: Gerenciamento de Usuários

**Feature**: `001-user_management` | **Data**: 2026-09-28 | **Plano**: [plan.md](plan.md)

## Tabela `usuarios` (migration existente, só o bloco `usuarios` muda)

Model `App\Models\Usuarios`: `SoftDeletes`, `HasRoles` (guard `api`), `JWTSubject`.

| Coluna | Tipo | Nulo | Padrão | Regras |
|---|---|---|---|---|
| `id` | bigint unsigned, PK | não | — | — |
| `nome` | string(255) | não | — | Obrigatório |
| `login` | string(255), `unique` | não | — | `trim`; único sem diferenciar caixa, incluindo excluídos; não editável |
| `password` | string(255) | não | — | Hash (cast `hashed`); mínimo 6 na entrada; nunca retornado |
| `telefone` | string(20) | sim | `null` | Opcional |
| `endereco` | string(255) | sim | `null` | Opcional |
| `ativo` | boolean | não | `true` | FR-020 |
| `usuarios_id` | bigint unsigned, FK → `usuarios.id`, índice | sim | `null` | Superior; `null` só para o Admin raiz |
| `remember_token` | string(100) | sim | `null` | Coluna padrão do `Authenticatable` |
| `created_at`, `updated_at` | timestamp | sim | — | `timestamps()` |
| `deleted_at` | timestamp | sim | `null` | `softDeletes()` |

Sem coluna `funcao`: a função é o papel do spatie (R-05). Removidas: `email`,
`email_verified_at`. FK sem `cascadeOnDelete` (exclusão sempre lógica).

## Tabelas do spatie (migration publicada pelo pacote, nomes padrão — exceção R-03)

| Tabela | Colunas | Observação |
|---|---|---|
| `roles` | `id`, `name`, `guard_name`, `created_at`, `updated_at` | Admin, Supervisor, Gerente, Vendedor (guard `api`) |
| `permissions` | `id`, `name`, `guard_name`, `created_at`, `updated_at` | Ver lista abaixo |
| `model_has_roles` | `role_id`, `model_type`, `model_id` | Um papel por usuário (identifica a função) |
| `model_has_permissions` | `permission_id`, `model_type`, `model_id` | **Todas** as permissões do usuário (diretas) |
| `role_has_permissions` | `permission_id`, `role_id` | Não usada: papéis não concedem permissões (R-04) |

Nomes em inglês e ausência de `deleted_at` (e de timestamps nas pivô) são exceção pedida pelo
responsável ([research.md](research.md) R-03).

## Enum `App\Enums\Funcao`

| Caso / nome do papel | Nível | Cadastra (`funcao_abaixo()`) | Superior exigido (`funcao_acima()`) |
|---|---|---|---|
| `Admin` | 1 | `Supervisor` | nenhum |
| `Supervisor` | 2 | `Gerente` | `Admin` |
| `Gerente` | 3 | `Vendedor` | `Supervisor` |
| `Vendedor` | 4 | ninguém | `Gerente` |

## Permissões (guard `api`) e distribuição padrão

| Permissão | Ação | Admin | Supervisor | Gerente | Vendedor |
|---|---|---|---|---|---|
| `usuarios.listar` | `GET /usuarios` | ✓ | ✓ | ✓ | — |
| `usuarios.consultar` | `GET /usuarios/{id}` | ✓ | ✓ | ✓ | — |
| `usuarios.cadastrar` | `POST /usuarios` | ✓ | ✓ | ✓ | — |
| `usuarios.editar` | `PUT/PATCH /usuarios/{id}` | ✓ | ✓ | ✓ | — |
| `usuarios.alterar_situacao` | `ativo` no `update` | ✓ | ✓ | ✓ | — |
| `usuarios.excluir` | `DELETE /usuarios/{id}` | ✓ | ✓ | ✓ | — |
| `usuarios.gerenciar_permissoes` | rotas `/usuarios/{id}/permissoes` | ✓ | ✓ | ✓ | — |

Distribuição confirmada pelo responsável em 2026-09-28 (R-06).

Padrão definido em `Funcao::permissoes_padrao()`. **Permissões do usuário** (FR-036) = as
diretas em `model_has_permissions`. No cadastro, recebe o padrão da função filtrado pelas
permissões do cadastrante; dar = `givePermissionTo()`; tirar = `revokePermissionTo()` (R-04).

## Model `Usuarios`: métodos

| Nome | Descrição |
|---|---|
| `superior()` / `subordinados()` | `belongsTo` / `hasMany` por `usuarios_id` |
| `funcao(): ?Funcao` | Função a partir do papel do usuário |
| `ids_sub_hierarquia(): array` | Ids de todos os usuários abaixo, em qualquer nível |
| `gerencia(Usuarios $alvo): bool` | Alvo está na sub-hierarquia (nunca ele mesmo) |
| `pode_acessar(): bool` | Ativo e não excluído (FR-020) |
| `alterar_situacao_em_cascata(bool)` / `excluir_em_cascata()` | Cascatas em transação |
| `atribuir_permissoes_padrao(Usuarios $cadastrante)` | Copia o padrão da função, filtrado pelo cadastrante (R-04) |
| `getJWTIdentifier()` / `getJWTCustomClaims()` | Exigidos pelo pacote JWT |

## Regras de validação

**Login**: `login` e `password` obrigatórios; 5 tentativas erradas/min por login + IP.

**Cadastro**: `nome` obrigatório (até 255); `funcao` obrigatória e igual a `funcao_abaixo()` de
quem cadastra; `login` obrigatório, até 255, único (caixa e excluídos); `password` obrigatório,
mínimo 6; `telefone` opcional até 20; `endereco` opcional até 255; `usuarios_id` e `ativo`
recusados. O papel `funcao` é atribuído ao novo usuário na mesma transação.

**Edição**: `nome`, `telefone`, `endereco` opcionais; `password` opcional (mínimo 6; ausente
mantém); `ativo` opcional (exige `usuarios.alterar_situacao`; dispara cascata); `usuarios_id`
opcional (superior válido, R-12); `funcao` e `login` recusados.

**Listagem**: `funcao`, `ativo`, `busca` (até 255), `por_pagina` (1 a 100, padrão 15).

**Permissões**: `permissao` deve existir no guard `api`; dar exige que quem solicita tenha a
permissão — FR-038.

## Estados

```text
ATIVO ──desativar (cascata)──▶ INATIVO ──reativar (cascata)──▶ ATIVO
ATIVO/INATIVO ──excluir (cascata, soft delete)──▶ EXCLUÍDO (final)
```

INATIVO/EXCLUÍDO: login, renovação e qualquer rota de gestão recusados.
