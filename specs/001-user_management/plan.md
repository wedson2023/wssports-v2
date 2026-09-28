# Implementation Plan: Gerenciamento de Usuários

**Branch**: `001-user_management` | **Date**: 2026-09-28 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/001-user_management/spec.md`

## Summary

API de gerenciamento hierárquico de usuários (Admin > Supervisor > Gerente > Vendedor) com
autenticação JWT e permissões pelo `spatie/laravel-permission`. A função do usuário é o seu papel
(sem coluna `funcao`) e não concede permissões; cada ação de gestão é uma permissão atribuída
diretamente ao usuário (padrão da função copiado no cadastro), e o gestor pode dar ou tirar
permissões de um subordinado inserindo ou apagando o vínculo, como no outro sistema do
responsável. A regra "só a própria equipe" é aplicada no controller (404), separada da
checagem de permissão (403). Rotas em `routes/api.php` com `apiResource('usuarios')`, cascata de
situação e exclusão em transação e paginação de 15 (máximo 100). Decisões em
[research.md](research.md).

## Technical Context

**Language/Version**: PHP 8.2 (^8.2)

**Primary Dependencies**: Laravel 12; **novas**: `php-open-source-saver/jwt-auth` (R-01) e
`spatie/laravel-permission` (R-02)

**Storage**: MySQL local `wssports` (compartilhado com o legado — só `migrate:refresh`, nunca
`migrate:fresh`, R-15); cache `database` para blacklist JWT e cache de permissões

**Testing**: nenhum teste automatizado (constituição v1.4.0); validação manual pelo
[quickstart.md](quickstart.md)

**Target Platform**: servidor web com PHP 8.2

**Project Type**: API web Laravel (frontend fora do escopo)

**Performance Goals**: listagem de até 1.000 subordinados em < 2 s (SC-005)

**Constraints**: paginação ≤ 100; token de 60 min; 5 tentativas de login/min por login + IP;
mensagens em português; cascatas atômicas

**Scale/Scope**: milhares de usuários; 4 papéis; 7 permissões; 11 rotas

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Princípio / Regra | Verificação | Status |
|---|---|---|
| I. `snake_case` | Métodos, variáveis, parâmetros, chaves e nomes de permissões em `snake_case` (`pode_acessar()`, `usuarios.alterar_situacao`, `por_pagina`); métodos exigidos pelo framework/pacotes (`getJWTIdentifier`, `middleware`, `rules`...) mantêm o nome (exceção do Princípio I) | ✅ Pass |
| I. Banco em português | `usuarios` em português; tabelas do spatie com os nomes padrão em inglês (`roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`) por exceção pedida pelo responsável | ⚠️ Exceção justificada |
| I. Pastas | Novas pastas PSR-4 em `PascalCase` (`app/Enums`, `app/Http/Middleware`, `app/Http/Requests`, `app/Http/Resources`) — exceção do Princípio I | ✅ Pass |
| II. Idioma | Artefatos e comentários do backend em português | ✅ Pass |
| III. Componentes React | Sem frontend | ➖ N/A |
| IV. Escopo estrito | Arquivos existentes listados abaixo; alteração autorizada pelo responsável em 2026-09-28 | ✅ Pass (confirmado) |
| V. Legibilidade | Revisão ao final de cada tarefa | ✅ Pass |
| Timestamps e soft delete | `usuarios` com `timestamps()` + `softDeletes()`; tabelas do spatie sem `deleted_at` e pivôs sem timestamps | ⚠️ Exceção justificada |
| Paginação ≤ 100 | `por_pagina` 1–100, padrão 15 | ✅ Pass |
| Sem testes | Nenhum arquivo/tarefa/dependência de teste | ✅ Pass |
| Novas dependências | JWT (R-01) e spatie (R-02) justificadas | ✅ Pass |

**Resultado do gate**: aprovado, com duas exceções justificadas (Complexity Tracking) e aceitas
pelo responsável.

**Confirmações do responsável (2026-09-28)**:

- Distribuição de permissões (R-06): Admin, Supervisor e Gerente com todas; Vendedor nenhuma.
- Exceções do spatie (colunas fixas em inglês e tabelas do pacote sem `deleted_at`): aceitas.
- Alteração dos arquivos existentes listados abaixo: autorizada.
- `php artisan migrate:refresh --seed` no banco local: autorizado.

### Arquivos existentes que serão alterados (Princípio IV)

| Arquivo | Alteração | Motivo |
|---|---|---|
| `database/migrations/0001_01_01_000000_create_usuarios_table.php` | Só o bloco `usuarios`: sai `email`/`email_verified_at`; entram `login`, `telefone`, `endereco`, `ativo`, `usuarios_id`, `softDeletes()` | FR-007, FR-025 |
| `app/Models/Usuarios.php` | `SoftDeletes`, `HasRoles`, `JWTSubject`, `$guard_name`, relacionamentos, métodos de hierarquia e permissões | Model da feature |
| `database/factories/UsuariosFactory.php` | Troca `email` por `login`/`ativo`; estados `admin_raiz()`, `subordinado_de()`, `inativo()` (atribuem o papel) | Factory quebra sem `email` |
| `database/seeders/DatabaseSeeder.php` | Chama `PapeisPermissoesSeeder` e cria Admin raiz + hierarquia de exemplo | FR-026, FR-034 |
| `bootstrap/app.php` | Registrar `routes/api.php`; aliases `permission` (spatie) e `garantir_acesso`; `401`/`403` em português | R-08, R-07, R-11 |
| `config/auth.php` | Guard `api` com driver `jwt` | R-01 |
| `composer.json` / `composer.lock` | Pacotes JWT e spatie | R-01, R-02 |
| `.env` / `.env.example` | `JWT_SECRET` | R-01 |

Arquivos novos publicados pelos pacotes: `config/jwt.php`, `config/permission.php` (padrão do
pacote, sem renomear tabelas, R-03) e a migration de tabelas do spatie.

**Impacto no banco local**: `php artisan migrate:refresh --seed` (só migrations do projeto; a
`usuarios` atual está vazia; legado intacto).

### Re-check pós-design (Phase 1)

Após [data-model.md](data-model.md) e [contracts/api.md](contracts/api.md): nomes em
português/`snake_case`; paginação ≤ 100; nenhuma tarefa de teste; exceções limitadas às colunas
fixas e à ausência de soft delete nas tabelas do spatie. **Status: aprovado** (todas as
confirmações recebidas).

## Project Structure

### Documentation (this feature)

```text
specs/001-user_management/
├── plan.md              # Este arquivo
├── research.md          # Phase 0
├── data-model.md        # Phase 1
├── quickstart.md        # Phase 1: validação manual
├── contracts/
│   └── api.md           # Phase 1: contrato HTTP
├── checklists/
│   └── requirements.md
└── tasks.md             # Phase 2 (/speckit-tasks)
```

### Source Code (repository root)

```text
app/
├── Enums/
│   └── Funcao.php                              # NOVO: papéis e ordem hierárquica
├── Http/
│   ├── Controllers/
│   │   ├── AutenticacaoController.php          # NOVO: login, refresh, logout
│   │   ├── UsuariosController.php              # NOVO: apiResource + regra de sub-hierarquia
│   │   └── PermissoesUsuariosController.php    # NOVO: consultar, dar e tirar permissões
│   ├── Middleware/
│   │   └── GarantirAcesso.php                  # NOVO: 403 para inativo/excluído
│   ├── Requests/
│   │   ├── LoginRequest.php                    # NOVO: credenciais + limite de tentativas
│   │   ├── StoreUsuariosRequest.php            # NOVO
│   │   ├── UpdateUsuariosRequest.php           # NOVO
│   │   └── PermissaoUsuarioRequest.php         # NOVO: validação de dar permissão
│   └── Resources/
│       └── UsuariosResource.php                # NOVO
└── Models/
    └── Usuarios.php                            # ALTERADO

bootstrap/app.php                               # ALTERADO
config/
├── auth.php                                    # ALTERADO: guard api (jwt)
├── jwt.php                                     # NOVO (publicado)
└── permission.php                              # NOVO (publicado, tabelas em português)

database/
├── factories/UsuariosFactory.php               # ALTERADO
├── migrations/
│   ├── 0001_01_01_000000_create_usuarios_table.php          # ALTERADO (bloco usuarios)
│   └── xxxx_create_permission_tables.php                    # NOVO (publicado pelo spatie)
└── seeders/
    ├── DatabaseSeeder.php                      # ALTERADO
    └── PapeisPermissoesSeeder.php              # NOVO: papéis, permissões e distribuição

routes/
└── api.php                                     # NOVO
```

**Structure Decision**: aplicação Laravel única, estrutura padrão. Sem Policies (substituídas
pelo spatie) e sem arquivos em `tests/`.

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| Tabelas e colunas do spatie com os nomes padrão em inglês (Princípio I pede banco em português) | Exceção pedida pelo responsável, para manter o mesmo padrão do seu outro sistema | Renomear via `config/permission.php` foi descartado pelo responsável |
| Tabelas do spatie sem `deleted_at` e pivôs sem timestamps (constituição exige `created_at`, `updated_at`, `deleted_at` em toda tabela) | O spatie não reconhece soft delete: um papel ou vínculo "excluído" continuaria concedendo permissões | Adicionar soft delete exigiria reescrever as consultas do pacote; papéis e permissões são fixos (seeder) e não são excluídos pelo sistema |
