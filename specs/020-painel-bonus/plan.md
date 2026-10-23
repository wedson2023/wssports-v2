# Implementation Plan: Painel — Bônus (promoções, bônus recebidos e estornos)

**Branch**: `020-painel-bonus` | **Date**: 2026-10-23 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/020-painel-bonus/spec.md`

## Summary

Cria a tela "Bônus" do painel (a antiga "Lista de bônus"), backend e frontend, reaproveitando o
cadastro e o estorno de promoções (specs 002, 007 e 012), o rollover, a expiração e o converter/excluir
do cliente (spec 007) e o alcance por rede (spec 019):

- **Alcance das promoções** (`AlcancePromocoes`, R-06): Admin vê e altera todas; Supervisor e Gerente
  alteram as dos afiliados da rede e veem as gerais só para leitura.
- **Lista de promoções** (`ListagemPromocoes`, R-07): busca, situação calculada (Vigente, Agendada,
  Encerrada, Desativada, Estornando, Estornada), filtro por afiliado e números de uso.
- **Regras do antigo de volta** (R-08): sobreposição por afiliado, nome único, fim no passado
  recusado; excluir só promoção não aplicada.
- **Bônus recebidos** (`ListagemBonusRecebidos`, R-02, R-03, R-09): o crédito de origem `Promoção`
  com o rollover e o estorno, situação calculada, cartões e detalhe com histórico.
- **Estorno por bônus** (`EstornoBonus` + tabela `clientes_promocoes_estornos`, R-04, R-05):
  individual (novo), geral (o job passa a estornar bônus a bônus, pulando convertidos, expirados,
  excluídos e já estornados) e automático (registro dos cancelamentos por estorno de depósito e de
  indicação); senha e motivo nos dois estornos manuais.
- **Frontend**: página `PanelBonus` (`/painel/bonus`) com as abas Promoções e Bônus recebidos, gavetas
  de detalhe e modais (R-11).

Decisões em [research.md](research.md) (R-01 a R-14).

## Technical Context

**Language/Version**: PHP 8.2 (^8.2); JavaScript (ES2022) com JSX

**Primary Dependencies**: as instaladas (Laravel 12, Inertia 2, React 19, styled-components 6,
sweetalert2, jwt-auth, spatie/laravel-permission). Nenhuma nova.

**Storage**: MySQL; 1 tabela nova (`clientes_promocoes_estornos`), índices novos (R-13) e 1 migration
de dados (estornos já acontecidos, R-12). Nenhuma coluna nova em tabela existente.

**Testing**: nenhum teste automatizado (Constituição); validação pelo [quickstart.md](quickstart.md)

**Target Platform**: navegadores atuais, desktop e celular (prioridade); deploy contínuo (spec 013)

**Project Type**: monólito Laravel + Inertia com API JWT

**Performance Goals**: primeira página de cada aba em até 2 s (SC-008): situação por `CASE` no banco,
números de uso só da página em consultas agrupadas, JOINs por índices; estorno geral em lotes de 500
com uma transação curta por bônus (SC-007).

**Constraints**: alcance e permissões sempre no servidor; bcmath nos valores; query builder com
parâmetros; saldo só pelo `SaldoClientes`; trava cliente → rollover na mesma ordem do `GestaoBonus`;
paginação padrão 20 (bônus recebidos 30), máximo 100; visual e componentes do painel (specs 014 a 019)
e regras de modais, cabeçalho e campos da constituição.

**Scale/Scope**:

| Item | Quantidade |
|---|---|
| Migrations | 3 (1 tabela, 1 de índices, 1 de dados) |
| Rotas da API novas / alteradas | 4 (`clientes-bonus`, `resumo`, `show`, `estornar`) / 6 (`clientes-promocoes` index, show, store, update, destroy, estornar) |
| Rotas web novas | 1 (`/painel/bonus`) |
| Services novos / alterados | 4 (`AlcancePromocoes`, `ListagemPromocoes`, `ListagemBonusRecebidos`, `EstornoBonus`) / 2 (`ProcessamentoDepositos`, `Indicacoes`) |
| Controllers novos / alterados | 1 (`ClientesBonusController`) / 2 (`ClientesPromocoesController`, `PainelController`) |
| Models / Enums novos | 1 (`ClientesPromocoesEstornos`) / 3 (`TipoEstornoBonus`, `SituacaoBonus`, `SituacaoPromocao`) |
| Requests / Resources novos | 2 (`EstornarBonusRequest`, `BonusRecebidosFiltrosRequest`) / 1 (`BonusRecebidosResource`) |
| Traits novas | 1 (`GarantirAlcancePromocao`) |
| Componentes novos / páginas novas | 10 (R-11) / 1 (`PanelBonus`) |
| Hooks novos | 1 (`useBonusPainel`) |

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Princípio / Regra | Verificação | Status |
|---|---|---|
| I. Nomes no backend | Tabela `clientes_promocoes_estornos` (prefixo da tabela principal); colunas em português; enums `TipoEstornoBonus` (`Automático`), `SituacaoBonus` (`ExcluídoPeloCliente = 'Excluído pelo cliente'`), `SituacaoPromocao`; permissões existentes; rotas `clientes-bonus` (kebab-case), query snake_case | ✅ Pass |
| I. Nomes no frontend | Componentes em inglês PascalCase (`PanelBonus`, `PromotionsTable`, `BonusDrawer` etc.); hook `useBonusPainel`; variáveis e props snake_case em português | ✅ Pass |
| II. Idioma | Artefatos, comentários, mensagens e textos em português | ✅ Pass |
| III. Componentes | Cada componente em pasta própria com `index.jsx` e `styles.jsx` | ✅ Pass |
| IV. Escopo estrito | Arquivos existentes listados abaixo, **a confirmar pelo responsável** antes do `/speckit-implement`; as mudanças de regra nas specs 002, 007 e 012 foram pedidas na spec (Clarifications) | ⚠️ Aguarda confirmação da lista |
| V. Legibilidade | Revisão ao final de cada tarefa | ✅ Pass |
| VI. Consistência | Alcance por service + trait de controller (como `AlcanceClientes`/`GarantirAlcanceCliente`); senha pelo `ConfirmacaoCaixa`; listagens em services (como `ListagemSaques`); permissões em `Funcao.php` sem mudança; componentes do painel reaproveitados | ✅ Pass |
| VII. Sistema antigo | `BonusController`, `BonusCommand`, `CreditosBonus`, migrations `creditos_bonuses`, telas `adm/bonus` e `adm/modals/bonus` analisados na `main` `408cf730` (R-01); diferenças aprovadas em Clarifications | ✅ Pass |
| VIII. Fidelidade visual | Diferenças aprovadas na spec (visual do painel novo, abas renomeadas, menu de ações); referências em [contracts/paginas.md](contracts/paginas.md) §10 | ✅ Pass |
| IX. Dados fake | Nenhum | ✅ Pass |
| Stack | Sem dependência nova | ✅ Pass |
| Timestamps / soft delete | Tabela nova com `timestamps()` + `softDeletes()` e model com `SoftDeletes`; promoções continuam com exclusão lógica | ✅ Pass |
| Comentários de colunas | Todas as colunas novas com `->comment()` ([data-model.md](data-model.md) §1) | ✅ Pass |
| Referências a tabelas inexistentes | Nenhuma | ✅ Pass |
| Paginação ≤ 100 | Promoções padrão 20; bônus recebidos 30; seletor de promoção 20; máximo 100 | ✅ Pass |
| Atualização do frontend | Sem mudança de cache/PWA | ✅ Pass |
| Tamanho dos modais | Cadastro com altura fixa (conteúdo longo); estornos e confirmações com altura do conteúdo; gavetas com altura fixa; tela cheia no celular; cabeçalho e rodapé sem encolher | ✅ Pass |
| Cabeçalho dos modais | Todos com `ModalHeader` (ícone sem fundo, título, descrição, fechar) | ✅ Pass |
| Campos de itens existentes | Afiliado (`AffiliatePicker`) e promoção (`PromotionPicker`) escolhidos de listas; categoria, modalidade, tipo de ganho e situação em chips | ✅ Pass |
| Sem testes | Nenhum arquivo, tarefa ou dependência de teste | ✅ Pass |
| Postman | Regenerado na entrega | ✅ Pass |

**Resultado do gate**: aprovado para seguir ao `/speckit-tasks`, com a lista de arquivos existentes
abaixo pendente de confirmação do responsável (Princípio IV), como nas specs anteriores.

Reavaliado depois da Fase 1: sem violação nova.

### Arquivos existentes que serão alterados (Princípio IV)

| Arquivo | Alteração | Motivo |
|---|---|---|
| `app/Http/Controllers/ClientesPromocoesController.php` | Alcance (trait), filtros e números de uso via `ListagemPromocoes`, sobreposição por afiliado com o nome da conflitante, exclusão só não aplicada, 403 nas gerais | R-06, R-07, R-08 |
| `app/Http/Requests/ClientesPromocoesRequest.php` | Nome único, fim no passado (só quando muda), afiliado obrigatório e da rede para Supervisor/Gerente | R-06, R-08 |
| `app/Http/Requests/EstornarPromocaoRequest.php` | Campo `senha` com `ConfirmacaoCaixa::regra_senha` | R-05 |
| `app/Http/Resources/ClientesPromocoesResource.php` | `afiliado` com `excluido`, `situacao`, `uso`, `pode` | R-07 |
| `app/Models/ClientesPromocoes.php` | Relação `estornos` | R-04 |
| `app/Jobs/EstornarPromocao.php` | Percorre os créditos e estorna bônus a bônus pelo `EstornoBonus` (idempotência pela tabela nova) | R-05 |
| `app/Services/ProcessamentoDepositos.php` (`cancelar_bonus`) | Registra o estorno `Automático` do bônus cancelado | R-05 |
| `app/Services/Indicacoes.php` (`cancelar_grupo`) | Registra o estorno `Automático` do bônus de indicação cancelado | R-05 |
| `app/Http/Controllers/PainelController.php`, `routes/api.php`, `routes/web.php` | Página e rotas | contracts |
| `resources/js/components/PanelShell/index.jsx` | Item "Bônus" | R-11 |
| `docs/postman/wssports_api.postman_collection.json` | Regenerada | Constituição |
| `specs/002-clientes/spec.md`, `specs/007-painel-cliente-pagamentos/spec.md`, `specs/012-afiliados/spec.md` | Nota "Alterado pela spec 020" nos requisitos de promoções (alcance, sobreposição, nome único, fim, exclusão, senha e estorno por bônus) | coerência dos artefatos |

`RolloverClientes::cancelar_da_promocao` deixa de ser chamado pelo job; fica como está (Princípio IV) e
pode ser removido numa limpeza autorizada.

## Project Structure

### Documentation (this feature)

```text
specs/020-painel-bonus/
├── plan.md · research.md · data-model.md · quickstart.md
├── contracts/ (api.md, paginas.md)
├── checklists/requirements.md
└── tasks.md   (/speckit-tasks)
```

### Source Code

```text
app/
├── Enums/{TipoEstornoBonus,SituacaoBonus,SituacaoPromocao}.php                # novos
├── Models/ClientesPromocoesEstornos.php                                       # novo
├── Models/ClientesPromocoes.php                                               # alterado
├── Jobs/EstornarPromocao.php                                                  # alterado
├── Services/{AlcancePromocoes,ListagemPromocoes,ListagemBonusRecebidos,EstornoBonus}.php  # novos
├── Services/{ProcessamentoDepositos,Indicacoes}.php                           # alterados
└── Http/
    ├── Controllers/ClientesBonusController.php                                # novo
    ├── Controllers/{ClientesPromocoesController,PainelController}.php         # alterados
    ├── Controllers/Concerns/GarantirAlcancePromocao.php                       # novo
    ├── Requests/{EstornarBonusRequest,BonusRecebidosFiltrosRequest}.php       # novos
    ├── Requests/{ClientesPromocoesRequest,EstornarPromocaoRequest}.php        # alterados
    ├── Resources/BonusRecebidosResource.php                                   # novo
    └── Resources/ClientesPromocoesResource.php                                # alterado
database/migrations/
├── 2026_10_26_000001_create_clientes_promocoes_estornos_table.php
├── 2026_10_26_000002_add_indices_painel_bonus.php
└── 2026_10_26_000003_preencher_clientes_promocoes_estornos.php                # dados
routes/{api,web}.php                                                           # alterados
resources/js/
├── pages/PanelBonus/{index,styles}.jsx                                        # novo
├── components/{PromotionsTable,PromotionsFilters,PromotionFormModal,PromotionDrawer,PromotionReversalModal,ReceivedBonusTable,ReceivedBonusFilters,BonusDrawer,BonusReversalModal,PromotionPicker}/  # novos
├── hooks/useBonusPainel.js                                                    # novo
└── components/PanelShell/index.jsx                                            # alterado
docs/postman/wssports_api.postman_collection.json                              # regenerada
```

**Structure Decision**: monólito existente; página Inertia no `PanelShell`; API no padrão dos
controllers do painel (permissão por `GarantirPermissaoCliente`, alcance por service + trait, listagens
em services com bcmath e query builder); crédito, rollover, conversão, expiração e saldo reaproveitados.

## Complexity Tracking

Nenhuma violação a justificar. A tabela nova de estornos substitui a idempotência por cliente do job
atual (R-04) porque o estorno passou a ser por bônus (Clarification 5); a alternativa de colunas em
`clientes_rollovers` não cobre bônus sem rollover.
