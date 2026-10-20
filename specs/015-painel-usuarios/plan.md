# Implementation Plan: Painel — Usuários (Supervisores, Gerentes e Vendedores)

**Branch**: `015-painel-usuarios` | **Date**: 2026-10-20 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/015-painel-usuarios/spec.md`

## Summary

Cria as telas de usuários do painel sobre a API existente (specs 001, 003 e 004), com os ajustes de
backend que a spec pede:

- **Permissões por função alvo**: `usuarios.{cadastrar|editar|excluir}_{supervisor|gerente|vendedor}`
  no lugar das três gerais, com `pode_usar` por nível e uma **migration de dados** que converte as
  permissões dos usuários atuais (roda sozinha no deploy).
- **Cadastro em qualquer nível abaixo** com escolha do superior e comissões 1/2/3 no cadastro do
  vendedor (4 a 12 e ao vivo derivados).
- **Lixeira**: listagem de excluídos e restauração em cascata (mesmo `deleted_at`), sem exclusão
  definitiva.
- **Listas** com superior, comissões e contagens; `GET /usuarios-configuracoes/{usuario}`; `/auth/eu`
  com as permissões usáveis.
- **Frontend**: menu "Usuários" com subitens no `PanelShell`, página `PanelUsers` (tabela/cartões,
  abas Ativos/Lixeira, busca, paginação), `UserFormModal` com abas (Comissões, Limites e regras, Regras
  da equipe/rede) e o modal de permissões agrupadas com chaves.

Decisões em [research.md](research.md) (R-01 a R-10).

## Technical Context

**Language/Version**: PHP 8.2 (^8.2); JavaScript (ES2022) com JSX

**Primary Dependencies**: as já instaladas (Laravel 12, Inertia 2, React 19, styled-components 6,
sweetalert2, jwt-auth, spatie/laravel-permission, chart.js). Nenhum pacote novo.

**Storage**: MySQL; nenhuma tabela ou coluna nova; migration de dados nas tabelas do spatie.

**Testing**: nenhum teste automatizado (Constituição); validação pelo [quickstart.md](quickstart.md)

**Target Platform**: navegadores atuais, desktop e celular; servidor com deploy contínuo (spec 013)

**Project Type**: monólito Laravel + Inertia com API JWT

**Performance Goals**: listas em até 2 s com 500 usuários na rede (SC-005): contagens e comissões por
subconsulta/eager load, sem laço por linha; paginação padrão 15, máximo 100.

**Constraints**: alcance sempre no servidor; permissões checadas no servidor pela função alvo; sem
exclusão física; visual do painel da spec 014 com colunas, nomes e ações do antigo.

**Scale/Scope**:

| Item | Quantidade |
|---|---|
| Migrations | 1 (dados: permissões) |
| Rotas da API novas / alteradas | 3 / 6 |
| Rotas web novas | 3 |
| Componentes novos / páginas novas | 10 / 1 |
| Hooks / utils novos | 1 / 1 |

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Princípio / Regra | Verificação | Status |
|---|---|---|
| I. Nomes no backend | Permissões `usuarios.<acao>_<funcao>` (recurso = tabela `usuarios`); rotas kebab-case (`/usuarios/excluidos`, `/usuarios/{usuario}/restaurar`, `/painel/vendedores`) | ✅ Pass |
| I. Nomes no frontend | Componentes em inglês PascalCase; hook `useUsuariosPainel`; util `permissions.js`; snake_case português | ✅ Pass |
| II. Idioma | Artefatos, comentários e textos em português | ✅ Pass |
| III. Componentes | Pasta própria com `index.jsx` e `styles.jsx` | ✅ Pass |
| IV. Escopo estrito | Arquivos existentes listados abaixo; **aguardando confirmação do responsável** | ⏳ Confirmar |
| V. Legibilidade | Revisão ao final de cada tarefa | ✅ Pass |
| VI. Consistência | Permissões em `Funcao.php` com `pode_usar`; cascata no model como `excluir_em_cascata`; restauração como `clientes/{cliente}/restaurar`; visual e componentes da spec 014 | ✅ Pass |
| VII. Sistema antigo | Arquivos e funcionamento em R-01 e na spec; diferenças aprovadas (Clarifications) | ✅ Pass |
| VIII. Fidelidade visual | Mesmas colunas, nomes e ações; visual do painel aprovado; chaves no lugar das travas aprovadas | ✅ Pass |
| IX. Dados fake | Nenhum | ✅ Pass |
| Stack | Nenhuma dependência nova | ✅ Pass |
| Timestamps / soft delete / comentários | Sem tabela/coluna nova; restauração lógica; sem exclusão física | ✅ Pass |
| Paginação ≤ 100 | Padrão 15, máximo 100 | ✅ Pass |
| Sem testes | Nenhum | ✅ Pass |
| Postman | Regenerado na entrega | ✅ Pass |

**Resultado do gate**: aprovado, pendente a confirmação da lista de arquivos existentes (Princípio IV).

### Arquivos existentes que serão alterados (Princípio IV)

| Arquivo | Alteração | Motivo |
|---|---|---|
| `app/Enums/Funcao.php` | 9 permissões por função em `PERMISSOES_GESTAO`; `pode_usar` por nível; `permissoes_padrao` filtrando a gestão | R-02 |
| `app/Models/Usuarios.php` | `restaurar_em_cascata()` e subida da sub-hierarquia com excluídos | R-05 |
| `app/Http/Controllers/UsuariosController.php` | Permissão pela função alvo; `excluidos` e `restaurar`; colunas da listagem; comissões no cadastro | R-02, R-05, R-06 |
| `app/Http/Requests/StoreUsuariosRequest.php` | Função abaixo, superior obrigatório/proibido, `comissao_1..3` | R-03, R-04 |
| `app/Http/Requests/UpdateUsuariosRequest.php` | Docblock da permissão por função | R-02 |
| `app/Http/Resources/UsuariosResource.php` | `superior`, `comissoes`, contagens, `deleted_at` (quando carregados) | R-06 |
| `app/Http/Resources/UsuarioLogadoResource.php` | `permissoes` usáveis | R-08 |
| `app/Http/Controllers/UsuariosConfiguracoesController.php` | Ação `show` | R-07 |
| `app/Http/Controllers/PermissoesUsuariosController.php` | Recusar permissão que a função do alvo não pode usar | contracts/api §9 |
| `app/Services/ConfiguracoesVendedores.php` | Aplicar as comissões do cadastro (1/2/3 → 4–12 e ao vivo) | R-04 |
| `app/Http/Controllers/PainelController.php` | Ações das três listas | R-09 |
| `routes/api.php`, `routes/web.php` | 3 rotas de API e 3 páginas | contracts |
| `resources/js/components/PanelShell/` (`index.jsx`, `styles.jsx`) | Menu com grupo "Usuários" e subitens | R-09 |
| `docs/postman/wssports_api.postman_collection.json` | Regenerada | Constituição |
| `specs/001-user_management/spec.md` | Nota "Alterado pela spec 015" (cadastro em qualquer nível e permissões por função) | coerência dos artefatos |

## Project Structure

### Documentation (this feature)

```text
specs/015-painel-usuarios/
├── plan.md · research.md · data-model.md · quickstart.md
├── contracts/ (api.md, paginas.md)
├── checklists/requirements.md
└── tasks.md   (/speckit-tasks)
```

### Source Code

```text
app/
├── Enums/Funcao.php                                   # alterado
├── Models/Usuarios.php                                # alterado
├── Services/ConfiguracoesVendedores.php               # alterado
└── Http/
    ├── Controllers/{Usuarios,UsuariosConfiguracoes,PermissoesUsuarios,Painel}Controller.php  # alterados
    ├── Requests/{Store,Update}UsuariosRequest.php     # alterados
    └── Resources/{Usuarios,UsuarioLogado}Resource.php  # alterados
database/migrations/2026_10_21_000001_dividir_permissoes_usuarios_por_funcao.php   # novo
routes/{api,web}.php                                   # alterados
resources/js/
├── pages/PanelUsers/{index,styles}.jsx                # novo
├── components/
│   ├── PanelShell/                                    # alterado
│   ├── UsersTable/ · PanelTabs/ · Pagination/ · UserFormModal/ · CommissionFields/
│   ├── BettingRulesFields/ · PermissionsPanel/ · PermissionsModal/ · ToggleSwitch/   # novos
├── hooks/useUsuariosPainel.js                         # novo
└── utils/permissions.js                               # novo
docs/postman/wssports_api.postman_collection.json      # regenerada
```

**Structure Decision**: monólito existente; páginas Inertia com o `PanelShell` da spec 014.

## Complexity Tracking

Nenhuma violação a justificar.
