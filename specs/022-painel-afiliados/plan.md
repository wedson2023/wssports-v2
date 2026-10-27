# Implementation Plan: Painel — Afiliados (lista, aprovação, gaveta, regra de ganho e prestação de contas)

**Branch**: `022-painel-afiliados` | **Date**: 2026-10-27 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/022-painel-afiliados/spec.md`

## Summary

Cria a tela "Afiliados" do painel (a antiga "Lista de filiados"), sobre a gestão de afiliados que já
existe como API (specs 012 e 019), e faz as mudanças de regra decididas na spec:

- **Lista e cartões** (R-02 a R-04): `GET /afiliados` ganha o grupo (Ativos+Inativos / Em
  análise+Recusados), o período padrão "este mês" e a coluna Entradas; rota nova
  `GET /afiliados/resumo` para os cartões.
- **Regra de ganho no cadastro** (R-05): tipo de ganho e percentual obrigatórios no cadastro pelo
  painel e na aprovação; o cadastro público entra sem regra; sai a regra padrão das Configurações
  (colunas, rotas, controller, request, resource e a seção do frontend).
- **Aprovação só do Admin** (R-06) e **senha na exclusão** (R-07).
- **Perfil do afiliado** (R-08, R-13): rede social, link do perfil, seguidores e "sobre você" no
  banco, na gestão, no cadastro público e em "Meus dados".
- **Frontend** (R-01, R-09 a R-12): página `PanelAffiliates` (`/painel/afiliados`) com abas Ativos,
  Em análise e Lixeira, gaveta `AffiliateDrawer` e os modais em grade, sem interruptores.

Decisões em [research.md](research.md) (R-01 a R-14).

## Technical Context

**Language/Version**: PHP 8.2 (^8.2); JavaScript (ES2022) com JSX

**Primary Dependencies**: as instaladas (Laravel 12, Inertia 2, React 19, styled-components 6,
sweetalert2, jwt-auth, spatie/laravel-permission). Nenhuma nova.

**Storage**: MySQL; 2 migrations: colunas de perfil em `afiliados` e remoção das 2 colunas de
afiliados em `configuracoes`. Nenhuma tabela nova.

**Testing**: nenhum teste automatizado (Constituição); validação pelo [quickstart.md](quickstart.md)

**Target Platform**: navegadores atuais, desktop e celular; deploy contínuo (spec 013)

**Project Type**: monólito Laravel + Inertia com API JWT

**Performance Goals**: primeira página de cada aba em até 2 s (SC-008): totais por subconsulta só
da página (como hoje) e resumo por consultas agregadas sobre os índices existentes de
`afiliados_lancamentos` (`afiliados_id` + prestação, `afiliados_id` + `ocorrido_em`).

**Constraints**: alcance e permissões sempre no servidor (rede da spec 019, `GarantirAlcanceAfiliado`);
bcmath nos valores; query builder com parâmetros; paginação padrão 20 (gaveta 30), máximo 100;
visual e componentes do painel (specs 014 a 021) e regras de modais, cabeçalho e campos da
constituição; sem `ToggleSwitch` nos modais.

**Scale/Scope**:

| Item | Quantidade |
|---|---|
| Migrations | 2 |
| Rotas da API novas / alteradas / removidas | 1 (`afiliados/resumo`) / 10 (ver contracts/api §2) / 2 (`configuracoes/afiliados`) |
| Página nova | 1 (`PanelAffiliates`) + 1 rota web |
| Componentes novos | 10 |
| Componentes alterados | 4 (`PanelShell`, `AffiliateSignupModal`, `AffiliateProfileModal`, `utils/settings_fields.js`*) |

\* arquivo de utilitário, não componente.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Princípio | Situação |
|---|---|
| I. Nomenclatura | ✅ colunas em português (`rede_social`, `link_perfil`, `quantidade_seguidores`, `sobre`); enum `RedeSocialAfiliado` com casos PascalCase e valores com maiúscula; rota `afiliados/resumo` em kebab-case; componentes em inglês PascalCase, hook `useAfiliadosPainel` |
| II. Português | ✅ artefatos e comentários em português |
| III. Componentes | ✅ cada componente novo com pasta própria, `index.jsx` e `styles.jsx` |
| IV. Escopo estrito | ⚠️ esta spec altera código entregue nas specs 012, 019 e 021 — autorizado pelo responsável na spec (Clarifications 2026-10-27); a lista de arquivos existentes alterados está em "Project Structure" e deve ser confirmada antes do `/speckit-implement`. `RestoreConflictModal` e `ToggleSwitch` não são alterados (R-11, R-12) |
| V. Revisão de legibilidade | ✅ prevista no fim de cada tarefa (tasks.md) |
| VI. Padrões | ✅ página, hook, gaveta e modais no padrão de `PanelBonus`; senha na exclusão pelo mesmo padrão do `PrestacaoContasAfiliadoRequest`; nenhuma permissão nova (R-06) |
| VII. Sistema antigo | ✅ consultado e listado em research.md; diferenças aprovadas na spec |
| VIII. Visual | ✅ diferenças visuais aprovadas na spec (padrão do painel novo; modais em grade; seleção no lugar de interruptor); telas antigas listadas na spec |
| IX. Dados fake | ✅ nenhum |
| Stack e restrições | ✅ `->comment()` nas colunas novas; paginação ≤ 100; modais com `ModalHeader`, altura e celular da constituição; gerente e rede social escolhidos de lista |
| Fluxo | ✅ sem testes; tarefa explícita para regenerar o Postman |

**Resultado**: aprovado. Nenhuma violação a justificar (o ⚠️ do Princípio IV é a confirmação de
arquivos prevista pelo próprio princípio, não uma violação).

**Re-check pós-design**: mantido — o data-model e os contratos não trouxeram tabela, dependência
ou padrão novo.

## Project Structure

### Documentation (this feature)

```text
specs/022-painel-afiliados/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── api.md
│   └── paginas.md
├── checklists/requirements.md
└── tasks.md             # /speckit-tasks
```

### Source Code (repository root)

```text
# novos
database/migrations/2026_10_27_000001_add_perfil_afiliados_table.php
database/migrations/2026_10_27_000002_remove_afiliados_configuracoes_table.php
app/Enums/RedeSocialAfiliado.php
app/Http/Requests/ExcluirAfiliadoRequest.php
resources/js/pages/PanelAffiliates/{index,styles}.jsx
resources/js/hooks/useAfiliadosPainel.js
resources/js/components/AffiliatesTable/{index,styles}.jsx
resources/js/components/AffiliatesFilters/{index,styles}.jsx
resources/js/components/AffiliateDrawer/{index,styles}.jsx
resources/js/components/AffiliateFormModal/{index,styles}.jsx
resources/js/components/AffiliateApprovalModal/{index,styles}.jsx
resources/js/components/AffiliateRuleModal/{index,styles}.jsx
resources/js/components/AffiliateSettlementModal/{index,styles}.jsx
resources/js/components/AffiliateDeleteModal/{index,styles}.jsx
resources/js/components/AffiliateRestoreModal/{index,styles}.jsx
resources/js/components/AffiliateProfileFields/{index,styles}.jsx   # rede, link, seguidores, sobre (gestão, cadastro público e Meus dados)

# alterados (confirmar antes de implementar — Princípio IV)
app/Models/Afiliados.php                              # fillable, casts, autor_cadastro, autor_decisao
app/Models/Configuracoes.php                          # sai afiliados_*_padrao
app/Http/Requests/Concerns/DadosAfiliado.php          # regras_perfil_afiliado; regra de comissão obrigatória
app/Http/Requests/StoreAfiliadosRequest.php           # regra obrigatória + perfil
app/Http/Requests/UpdateAfiliadosRequest.php          # perfil
app/Http/Requests/AprovarAfiliadoRequest.php          # gerente e regra obrigatórios
app/Http/Requests/CadastroPublicoAfiliadoRequest.php  # perfil
app/Http/Requests/UpdateMeusDadosAfiliadoRequest.php  # perfil
app/Http/Controllers/AfiliadosController.php          # resumo, grupo, destroy com senha, aprovar/recusar só Admin
app/Http/Controllers/AfiliadosExtratoController.php   # meta.soma do extrato
app/Http/Resources/AfiliadosResource.php              # entradas, perfil, autores
app/Http/Resources/MeusDadosAfiliadoResource.php      # perfil
app/Services/ListagemAfiliados.php                    # grupo, período "este mês", entradas, resumo
app/Services/CadastroAfiliados.php                    # público sem regra; aprovação com regra obrigatória
app/Services/RegraComissaoAfiliados.php               # definir_inicial sem a configuração
app/Http/Controllers/PainelController.php             # afiliados()
routes/api.php                                        # resumo; remove configuracoes/afiliados
routes/web.php                                        # painel/afiliados
database/seeders/AfiliadosSeeder.php                  # campos de perfil (a regra já é criada direto)
database/seeders/AfiliadosVolumeSeeder.php            # campos de perfil
resources/js/components/PanelShell/index.jsx          # item Afiliados
resources/js/components/AffiliateSignupModal/index.jsx
resources/js/components/AffiliateProfileModal/index.jsx
resources/js/utils/settings_fields.js                 # sai a seção afiliados
docs/postman/wssports_api.postman_collection.json     # regenerada

# removidos
app/Http/Controllers/ConfiguracoesAfiliadosController.php
app/Http/Requests/ConfiguracoesAfiliadosRequest.php
app/Http/Resources/ConfiguracoesAfiliadosResource.php
```

Artefatos de specs anteriores atualizados na mesma entrega (constituição, "Alterações manuais" e
memória "emenda atualiza o plano"): `specs/012-afiliados/spec.md` (FR-008, FR-010, FR-011, FR-014,
FR-015, FR-048, FR-051, FR-053, FR-056 — nota "alterado pela spec 022"),
`specs/012-afiliados/contracts/api.md` (§1.1, §1.10, §2.3, §2.7, §2.8, §2.14, §3) e
`specs/021-painel-configuracoes/spec.md` (FR-028, seção Afiliados).

**Structure Decision**: monólito atual (Laravel em `app/`, `routes/`, `database/`; React/Inertia em
`resources/js/`), sem pastas novas além das de componentes.

## Complexity Tracking

Nenhuma violação a justificar.
