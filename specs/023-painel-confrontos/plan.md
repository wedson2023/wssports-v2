# Implementation Plan: Painel — Confrontos (lista de jogos, limite, situação, mais apostados, pendentes, campeonatos e cadastro manual)

**Branch**: `023-painel-confrontos` | **Date**: 2026-10-27 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/023-painel-confrontos/spec.md`

## Summary

Cria a tela "Confrontos" do painel (o antigo "Lista de Jogos" e a antiga tela "Campeonatos") sobre a
gestão de confrontos que já existe como API (specs 003, 009 e 016), e faz as mudanças de regra
decididas na spec:

- **Lista e cartões** (R-02 a R-08): `GET /confrontos` passa a usar o serviço
  `ListagemConfrontosPainel`, com o dia de 30 dias atrás a depois de amanhã, o tipo Todos/Pré-jogo/Ao
  vivo, a busca pelo campeonato e os campos novos (ao vivo, limite, resultado, restrições por rede e
  ações); rota nova `GET /confrontos/resumo`.
- **Exposição** (R-09 a R-11): rotas novas de Mais apostados (aba e ranking do jogo) e Apostas do
  jogo, com o alcance de `ListagemApostas`.
- **Pendentes e placar como o antigo** (R-12): rota nova `GET /confrontos/pendentes`; o lançamento
  de resultado passa a aceitar jogos do provedor, com tempos e escanteios obrigatórios e a regra da
  soma; `confrontos.lancar_resultado` só do Admin.
- **Campeonatos e cadastro manual** (R-14 a R-16): quantidade de jogos, restrições e ordem na
  listagem; `GET /campeonatos/paises` e `GET /confrontos/esportes`; exclusão manual recusada com
  apostas.
- **Frontend** (R-01, R-17 a R-19): página `PanelMatches` (`/painel/confrontos`) com as abas Jogos,
  Mais apostados, Pendentes e Campeonatos, a gaveta `MatchDrawer`, os modais em grade e a abertura do
  editor da tela Cotações pela URL.

Decisões em [research.md](research.md) (R-01 a R-20).

## Technical Context

**Language/Version**: PHP 8.2 (^8.2); JavaScript (ES2022) com JSX

**Primary Dependencies**: as instaladas (Laravel 12, Inertia 2, React 19, styled-components 6,
sweetalert2, jwt-auth, spatie/laravel-permission). Nenhuma nova.

**Storage**: MySQL; 2 migrations: índice em `confrontos.data_inicio` e retirada de
`confrontos.lancar_resultado` dos não Admin. Nenhuma tabela nem coluna nova.

**Testing**: nenhum teste automatizado (Constituição); validação pelo [quickstart.md](quickstart.md)

**Target Platform**: navegadores atuais, desktop e celular; deploy contínuo (spec 013)

**Project Type**: monólito Laravel + Inertia com API JWT

**Performance Goals**: primeira página de cada aba em até 2 s (SC-006): lista por dia sobre o índice
novo de `data_inicio`; ao vivo, restrições e resultado por `withExists`/`with` só da página;
agregações de apostas por `apostas_palpites.confrontos_id` (índice existente) com `JOIN` em
`apostas`; atualização automática só nas duas visões ao vivo (30 s, pausada com a aba escondida).

**Constraints**: alcance e permissões sempre no servidor (`AlcanceHierarquia`,
`ListagemApostas::aplicar_alcance`, `Funcao::usuario_pode`); somas em `DECIMAL` no SQL; query
builder com parâmetros e curingas escapados; paginação padrão 20 (gaveta 30), máximo 100; visual e
componentes do painel (specs 014 a 022) e regras de modais, cabeçalho e campos da constituição; sem
`ToggleSwitch` nos modais.

**Scale/Scope**:

| Item | Quantidade |
|---|---|
| Migrations | 2 |
| Rotas da API novas / alteradas | 7 (ver contracts/api §2) / 7 (§1 e `cotacoes/nomes`) |
| Página nova | 1 (`PanelMatches`) + 1 rota web |
| Componentes novos | 12 |
| Componentes alterados | 2 (`PanelShell`, `PanelQuotes`*) |

\* página, não componente.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Princípio | Situação |
|---|---|
| I. Nomenclatura | ✅ serviços `ListagemConfrontosPainel` e `ExposicaoConfrontos`, request `FiltrosConfrontosPainelRequest`, métodos e chaves em snake_case português (`mais_apostados`, `pendentes`, `nao_permitido_proprio`, `redes_desativadas`); rotas kebab-case (`confrontos/mais-apostados`); componentes em inglês PascalCase, hook `useConfrontosPainel` |
| II. Português | ✅ artefatos, mensagens e comentários em português |
| III. Componentes | ✅ cada componente novo com pasta própria, `index.jsx` e `styles.jsx` |
| IV. Escopo estrito | ⚠️ altera código das specs 003, 009, 016 e 018 — autorizado pelo responsável na spec (Clarifications 2026-10-27); a lista de arquivos existentes alterados está em "Project Structure" e deve ser confirmada antes do `/speckit-implement` |
| V. Revisão de legibilidade | ✅ prevista no fim de cada tarefa (tasks.md) |
| VI. Padrões | ✅ página, hook, gaveta e modais no padrão de `PanelAffiliates`; serviço de listagem no padrão de `ListagemAfiliados`; migration de permissão no padrão de `criar_permissao_restaurar_apostas`; nenhuma permissão nova |
| VII. Sistema antigo | ✅ consultado e listado em research.md; diferenças aprovadas na spec (inclusive o placar "como no sistema anterior") |
| VIII. Visual | ✅ diferenças visuais aprovadas na spec (padrão do painel novo; modais em grade; seleção no lugar de interruptor); telas antigas listadas na spec |
| IX. Dados fake | ✅ nenhum |
| Stack e restrições | ✅ paginação ≤ 100; modais com `ModalHeader`, altura e celular da constituição; campeonato, esporte e país escolhidos de lista; valores em reais com `DecimalField` |
| Fluxo | ✅ sem testes; tarefa explícita para regenerar o Postman e atualizar as specs 003 e 009 |

**Resultado**: aprovado. Nenhuma violação a justificar (o ⚠️ do Princípio IV é a confirmação de
arquivos prevista pelo próprio princípio, não uma violação).

**Re-check pós-design**: mantido — o data-model e os contratos não trouxeram tabela, coluna,
dependência nem permissão nova; a única mudança de permissão (lançar resultado só do Admin) foi
decidida pelo responsável.

## Project Structure

### Documentation (this feature)

```text
specs/023-painel-confrontos/
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
database/migrations/2026_10_27_000007_add_indice_data_inicio_confrontos_table.php
database/migrations/2026_10_27_000008_restringir_lancar_resultado_admin.php
app/Services/ListagemConfrontosPainel.php          # filtros, dia, tipo, ao vivo, restrições, ações e resumo
app/Services/ExposicaoConfrontos.php               # mais apostados (aba e ranking), apostas do jogo e pendentes
app/Http/Requests/FiltrosConfrontosPainelRequest.php
app/Http/Controllers/ConfrontosApostasController.php   # mais_apostados, pendentes, ranking, apostas
app/Http/Resources/ApostasConfrontoResource.php
resources/js/pages/PanelMatches/{index,styles}.jsx
resources/js/hooks/useConfrontosPainel.js
resources/js/components/MatchesTable/{index,styles}.jsx
resources/js/components/MostBetMatchesTable/{index,styles}.jsx
resources/js/components/PendingMatchesTable/{index,styles}.jsx
resources/js/components/ChampionshipsTable/{index,styles}.jsx
resources/js/components/MatchesFilters/{index,styles}.jsx
resources/js/components/MatchDrawer/{index,styles}.jsx
resources/js/components/MatchPicksRanking/{index,styles}.jsx
resources/js/components/MatchBetsList/{index,styles}.jsx
resources/js/components/MatchLimitModal/{index,styles}.jsx
resources/js/components/MatchResultModal/{index,styles}.jsx
resources/js/components/MatchFormModal/{index,styles}.jsx
resources/js/components/ChampionshipFormModal/{index,styles}.jsx

# alterados (confirmar antes de implementar — Princípio IV)
app/Enums/Funcao.php                                   # lancar_resultado só Admin
app/Http/Controllers/ConfrontosController.php          # index pelo serviço, show com gaveta, resumo, esportes, destroy com apostas
app/Http/Controllers/CampeonatosController.php         # quantidade de jogos, restrições, ordem, paises, destroy com apostas
app/Http/Controllers/ConfrontosResultadosController.php # aceita jogos do provedor
app/Http/Controllers/CotacoesPainelController.php      # nomes aceita confrontos.listar
app/Http/Requests/ConfrontosResultadosRequest.php      # tempos e escanteios obrigatórios; soma
app/Http/Resources/ConfrontosResource.php              # limite, ao vivo, resultado, restrições, ações, cotações do site
app/Http/Resources/CampeonatosResource.php             # quantidade de jogos, restrições, ações
app/Services/ListagemApostas.php                       # aplicar_alcance público
app/Http/Controllers/PainelController.php              # confrontos()
routes/api.php                                         # rotas novas antes dos apiResource
routes/web.php                                         # painel/confrontos
resources/js/components/PanelShell/index.jsx           # item Confrontos
resources/js/pages/PanelQuotes/index.jsx               # abre o editor por confrontos_id/campeonatos_id da URL
docs/postman/wssports_api.postman_collection.json      # regenerada
```

Artefatos de specs anteriores atualizados na mesma entrega (constituição, "Alterações manuais" e
memória "emenda atualiza o plano"): `specs/003-confrontos/spec.md` (FR-063; filtro de dia e busca da
listagem do painel — nota "alterado pela spec 023") e `specs/003-confrontos/contracts/api.md`
(listagem, exclusão), `specs/009-apuracao-resultados/spec.md` (FR-045, FR-046; permissão só do
Admin) e o contrato dela, e `specs/016-painel-cotacoes/contracts/` (parâmetros de URL da tela
Cotações e `cotacoes/nomes`).

**Structure Decision**: monólito atual (Laravel em `app/`, `routes/`, `database/`; React/Inertia em
`resources/js/`), sem pastas novas além das de componentes.

## Complexity Tracking

Nenhuma violação a justificar.
