# Implementation Plan: Painel — Especiais (lista, cadastro com opções, exposição, encerramento com uma ou mais vencedoras e anulação de opção)

**Branch**: `024-painel-especiais` | **Date**: 2026-10-28 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/024-painel-especiais/spec.md`

## Summary

Cria a tela "Especiais" do painel (a antiga "Lista de modalidades") sobre o backend de especiais da
spec 006, e faz as mudanças de regra decididas na spec:

- **Lista, cartões e lixeira** (R-01 a R-03, R-11): `GET /especiais` passa a usar o serviço
  `ListagemEspeciaisPainel` (situação "Todas", lixeira, apostas e valor em aberto no alcance de quem
  consulta, ações); rotas novas `GET /especiais/resumo` e `POST /especiais/{id}/restaurar`.
- **Exposição** (R-04): serviço `ExposicaoEspeciais` e `GET /especiais/{id}/exposicao`, por opção,
  separada em simples e múltiplas.
- **Várias vencedoras e divisão por empate** (R-05 a R-07): `quantidade_vencedoras` e
  `dividir_empate` na categoria; `vencedora`/`empatada` na opção (sai `especiais_opcoes_id_vencedora`);
  `cotacao_empate` no palpite, usada no recálculo do prêmio; encerrar recebe a lista de vencedoras.
- **Anular opção** (R-08): `POST /especiais/{id}/opcoes/{opcao}/anular`, com o motivo "Opção
  anulada" e a decisão das apostas pela apuração da spec 009.
- **Corrigir resultado** (R-09, R-10): `POST /especiais/{id}/corrigir-resultado`, só Admin
  (permissão nova `especiais.corrigir_resultado`) e com senha; reapura todas as apostas da categoria
  pelo `liquidar` da spec 009 (estorno e crédito da diferença) e guarda o histórico em
  `especiais_correcoes`.
- **Frontend** (R-01, R-12 a R-14): página `PanelSpecials` (`/painel/especiais`) com as abas
  Especiais e Lixeira, a gaveta `SpecialDrawer`, os modais em grade e a cotação dividida no
  comprovante.

Decisões em [research.md](research.md) (R-01 a R-15).

## Technical Context

**Language/Version**: PHP 8.2 (^8.2); JavaScript (ES2022) com JSX

**Primary Dependencies**: as instaladas (Laravel 12, Inertia 2, React 19, styled-components 6,
sweetalert2, jwt-auth, spatie/laravel-permission). Nenhuma nova.

**Storage**: MySQL; 4 migrations: colunas de vencedoras/anulação em `especiais` e
`especiais_opcoes` (com a migração e a remoção de `especiais_opcoes_id_vencedora`),
`apostas_palpites.cotacao_empate`, tabela `especiais_correcoes` e a permissão
`especiais.corrigir_resultado`.

**Testing**: nenhum teste automatizado (Constituição); validação pelo [quickstart.md](quickstart.md)

**Target Platform**: navegadores atuais, desktop e celular; deploy contínuo (spec 013)

**Project Type**: monólito Laravel + Inertia com API JWT

**Performance Goals**: lista e cartões em até 2 s com centenas de categorias e milhares de
palpites: agregações por `apostas_palpites.especiais_id` (índice da FK) com `JOIN` em `apostas`,
só para as categorias da página; encerramento, anulação e correção numa transação com trava por
categoria e por aposta, como o encerramento atual.

**Constraints**: alcance e permissões sempre no servidor (`ListagemApostas::aplicar_alcance`,
`Funcao::pode_usar`); dinheiro e cotações com `bcmath`/`DECIMAL` (cotação dividida truncada em duas
casas); query builder com parâmetros e curingas escapados; paginação padrão 20, máximo 100; visual e
componentes do painel (specs 014 a 023) e regras de modais, cabeçalho e campos da constituição; sem
`ToggleSwitch` nos modais.

**Scale/Scope**:

| Item | Quantidade |
|---|---|
| Migrations | 4 |
| Rotas da API novas / alteradas | 5 / 6 (ver contracts/api §1 e §2) |
| Página nova | 1 (`PanelSpecials`) + 1 rota web |
| Componentes novos | 8 |
| Componentes alterados | 2 (`PanelShell`, `TicketModal`) |

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Princípio | Situação |
|---|---|
| I. Nomenclatura | ✅ serviços `ListagemEspeciaisPainel` e `ExposicaoEspeciais`, requests `FiltrosEspeciaisPainelRequest` e `CorrigirResultadoEspecialRequest`, model `EspeciaisCorrecoes`, tabela `especiais_correcoes` (prefixo da principal), colunas em português (`quantidade_vencedoras`, `dividir_empate`, `vencedora`, `empatada`, `anulada_em`, `cotacao_empate`), casos de enum `OpçãoAnulada`, `CorreçãoEspecial`, `DivisãoEmpate`; permissão `especiais.corrigir_resultado`; rotas kebab-case (`corrigir-resultado`); componentes em inglês PascalCase, hook `useEspeciaisPainel` |
| II. Português | ✅ artefatos, mensagens e comentários em português |
| III. Componentes | ✅ cada componente novo com pasta própria, `index.jsx` e `styles.jsx` |
| IV. Escopo estrito | ⚠️ altera código das specs 006, 009 e do comprovante — autorizado pelo responsável na spec (Clarifications 2026-10-28); a lista de arquivos existentes alterados está em "Project Structure" e deve ser confirmada antes do `/speckit-implement` |
| V. Revisão de legibilidade | ✅ prevista no fim de cada tarefa (tasks.md) |
| VI. Padrões | ✅ página, hook, gaveta e modais no padrão de `PanelAffiliates`/`PanelMatches`; serviço de listagem no padrão de `ListagemAfiliados`, exposição no de `ExposicaoConfrontos`; restaurar como `AfiliadosController::restaurar`; senha como `ExcluirAfiliadoRequest`; permissão nova por migration como `criar_permissao_robo_whatsapp`, decidida pelo responsável no clarify |
| VII. Sistema antigo | ✅ consultado e listado em research.md; diferenças aprovadas na spec |
| VIII. Visual | ✅ diferenças visuais aprovadas na spec (padrão do painel novo; modais em grade; seleção no lugar de interruptor); telas antigas listadas na spec |
| IX. Dados fake | ✅ nenhum |
| Stack e restrições | ✅ timestamps e soft delete na tabela nova; comentário em toda coluna nova; paginação ≤ 100; modais com `ModalHeader`, altura e celular da constituição; vencedoras e empatadas escolhidas de lista; cotações com `DecimalField` |
| Fluxo | ✅ sem testes; tarefa explícita para regenerar o Postman e atualizar as specs 006 e 009 |

**Resultado**: aprovado. Nenhuma violação a justificar (o ⚠️ do Princípio IV é a confirmação de
arquivos prevista pelo próprio princípio, não uma violação).

**Re-check pós-design**: mantido. O data-model trouxe uma tabela nova (`especiais_correcoes`), colunas
novas em três tabelas e uma permissão nova, todas decididas na spec; a remoção de
`especiais_opcoes_id_vencedora` é coberta pela migração dos dados (data-model §2).

## Project Structure

### Documentation (this feature)

```text
specs/024-painel-especiais/
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
database/migrations/2026_10_28_000001_add_vencedoras_especiais_table.php      # especiais + especiais_opcoes; migra e remove especiais_opcoes_id_vencedora
database/migrations/2026_10_28_000002_add_cotacao_empate_apostas_palpites_table.php
database/migrations/2026_10_28_000003_create_especiais_correcoes_table.php
database/migrations/2026_10_28_000004_criar_permissao_corrigir_resultado_especiais.php
app/Models/EspeciaisCorrecoes.php
app/Services/ListagemEspeciaisPainel.php           # lista, lixeira, contagens em aberto, ações e resumo
app/Services/ExposicaoEspeciais.php                # exposição por opção (simples/múltiplas)
app/Http/Requests/FiltrosEspeciaisPainelRequest.php
app/Http/Requests/CorrigirResultadoEspecialRequest.php
app/Http/Requests/Concerns/VencedorasEspecial.php  # regras de quantidade/empate comuns a encerrar e corrigir
app/Http/Resources/EspeciaisCorrecoesResource.php
resources/js/pages/PanelSpecials/{index,styles}.jsx
resources/js/hooks/useEspeciaisPainel.js
resources/js/components/SpecialsTable/{index,styles}.jsx
resources/js/components/SpecialsFilters/{index,styles}.jsx
resources/js/components/SpecialDrawer/{index,styles}.jsx
resources/js/components/SpecialExposure/{index,styles}.jsx
resources/js/components/SpecialFormModal/{index,styles}.jsx
resources/js/components/SpecialCloseModal/{index,styles}.jsx     # encerrar e corrigir resultado
resources/js/components/SpecialVoidOptionModal/{index,styles}.jsx
resources/js/components/SpecialCorrections/{index,styles}.jsx    # histórico de correções na gaveta

# alterados (confirmar antes de implementar — Princípio IV)
app/Enums/Funcao.php                                   # especiais.corrigir_resultado em PERMISSOES_ESPECIAIS, só Admin
app/Enums/MotivoCancelamentoPalpite.php                # OpçãoAnulada
app/Enums/OrigemApuracao.php                           # CorreçãoEspecial
app/Enums/AcaoHistoricoAposta.php                      # DivisãoEmpate
app/Models/Especiais.php                               # casts e fillable novos, correcoes(), sai opcao_vencedora()
app/Models/EspeciaisOpcoes.php                         # casts novos, anulada()
app/Models/ApostasPalpites.php                         # cast cotacao_empate
app/Services/EncerramentoEspeciais.php                 # encerrar com várias vencedoras e divisão; anular_opcao; corrigir
app/Services/EdicaoApostas.php                         # recalcular usa cotacao_empate; recalcular_pelo_sistema público
app/Services/ApuracaoApostas.php                       # motivo da origem "Correção de especial" (autorizado no /speckit-implement)
app/Http/Controllers/EspeciaisController.php           # index pelo serviço, show com opções/correções, resumo, exposicao, restaurar
app/Http/Controllers/EspeciaisOpcoesController.php     # recusa opção anulada
app/Http/Controllers/EncerramentoEspeciaisController.php # encerrar novo, anular, corrigir
app/Http/Requests/EspeciaisRequest.php                 # quantidade_vencedoras
app/Http/Requests/EncerrarEspecialRequest.php          # vencedoras, empatadas, dividir_empate
app/Http/Resources/EspeciaisResource.php               # vencedoras, quantidade, divisão, contagens, ações, correções
app/Http/Resources/EspeciaisOpcoesResource.php         # situacao, vencedora, empatada, anulada_em
app/Http/Resources/ComprovanteApostaResource.php       # cotacao_empate no palpite especial
app/Http/Controllers/PainelController.php              # especiais()
routes/api.php                                         # rotas novas antes do apiResource
routes/web.php                                         # painel/especiais
resources/js/components/PanelShell/index.jsx           # item Especiais
resources/js/components/TicketModal/index.jsx          # cotação dividida por empate
resources/js/utils/permissions.js                      # especiais.corrigir_resultado
docs/postman/wssports_api.postman_collection.json      # regenerada
```

Artefatos de specs anteriores atualizados na mesma entrega (constituição, "Alterações manuais" e
memória "emenda atualiza o plano"): `specs/006-ajustes-tela-principal/spec.md` (FR-018: várias
vencedoras, divisão por empate, anulação de opção, restaurar — nota "alterado pela spec 024") e
`specs/006-ajustes-tela-principal/contracts/api.md` (encerrar, opções, restaurar), e
`specs/009-apuracao-resultados/spec.md` (origem "Correção de especial").

**Structure Decision**: monólito atual (Laravel em `app/`, `routes/`, `database/`; React/Inertia em
`resources/js/`), sem pastas novas além das de componentes.

## Complexity Tracking

Nenhuma violação a justificar.
