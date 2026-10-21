# Implementation Plan: Painel — Caixa (vendedores, gerentes, clientes, lançamentos e prestação de contas)

**Branch**: `017-painel-caixa` | **Date**: 2026-10-21 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/017-painel-caixa/spec.md`

## Summary

Cria o caixa do painel, backend e frontend, e troca os dados fake de caixa da Dashboard por reais:

- **Cálculo único** (`ApuracaoCaixa`): somas das apostas do balcão por vendedor num período e tipo
  (Todas / Pré-jogo / Ao vivo), lançamentos do período, comissão do gerente por gerente sobre o
  período inteiro, e o **saldo de caixa** como "total devido − total recebido" (R-03), que cobre
  prêmio depois da prestação, cancelamento, diferença do valor recebido, prestação desfeita e
  re-apuração.
- **Lançamentos e prestação de contas**: tabelas `caixa_lancamentos` e `caixa_prestacoes_contas`;
  prestar com resumo e senha, em lote pelo gerente (tudo ou nada), desfazer só a última, com motivo.
- **Comissão do gerente** (coluna em `usuarios_configuracoes`) e **adicionar limite** ao vendedor.
- **Caixa clientes** (`CaixaClientes`): esportes (reaproveita `CaixaOnline` com período), cassino
  com saldo real (somas do `RelatorioCassino`), bônus, depósitos, saques, resultado do site e lista
  por cliente.
- **Exportação .xlsx** (`phpoffice/phpspreadsheet`, autorizada) e **gráfico** por quantidade de
  palpites.
- **Permissões** `caixa.*` no padrão de `Funcao.php`, distribuídas aos usuários existentes por
  migration de dados.
- **Frontend**: página `PanelCash` (`/painel/caixa`) com abas Rede e Clientes, filtro de período,
  trilha de níveis, cartões, lista que vira cartões no celular e modais do painel.
- **Dashboard**: Lançamentos, Retiradas e Comissão do gerente reais, sem "em breve"; fakes removidos.

Decisões em [research.md](research.md) (R-01 a R-10).

## Technical Context

**Language/Version**: PHP 8.2 (^8.2); JavaScript (ES2022) com JSX

**Primary Dependencies**: as instaladas (Laravel 12, Inertia 2, React 19, styled-components 6,
sweetalert2, jwt-auth, spatie/laravel-permission, chart.js) + **`phpoffice/phpspreadsheet`** (nova,
autorizada pelo responsável na spec; exige as extensões PHP `zip`, `xml`, `xmlwriter` e `gd`).

**Storage**: MySQL; 2 tabelas novas (`caixa_lancamentos`, `caixa_prestacoes_contas`), 1 coluna nova
(`usuarios_configuracoes.percentual_comissao_gerente`) e 1 migration de dados (permissões).

**Testing**: nenhum teste automatizado (Constituição); validação pelo [quickstart.md](quickstart.md)

**Target Platform**: navegadores atuais, desktop e celular (prioridade); deploy contínuo (spec 013)

**Project Type**: monólito Laravel + Inertia com API JWT

**Performance Goals**: cartões e primeira página em até 2 s com até 500 vendedores na rede (SC-007):
uma consulta agrupada de apostas, uma de lançamentos e uma de prestações por requisição (sem N+1);
saldo de caixa pelas somas indexadas (`apostas(usuarios_id, situacao, created_at)`); exportação até
5.000 linhas.

**Constraints**: somas das apostas iguais às da Dashboard (spec 014); bcmath em todo valor;
consultas pelo query builder; alcance e permissões sempre no servidor; prestar, desfazer e lançar
com bloqueio da linha do vendedor; paginação padrão 20, máximo 100; visual e componentes do painel
(specs 014 a 016) e regras de modais, cabeçalho e campos da constituição.

**Scale/Scope**:

| Item | Quantidade |
|---|---|
| Migrations | 4 (2 tabelas, 1 coluna, 1 de dados) |
| Rotas da API novas | 16 (+ `GET /dashboard/apurado` com dados reais, sem mudança de formato) |
| Rotas web novas | 1 (`/painel/caixa`) |
| Services novos / alterados | 6 (`ApuracaoCaixa`, `SaldoCaixa`, `LancamentosCaixa`, `PrestacaoContasCaixa`, `CaixaClientes`, `ExportacaoCaixa`) / 3 (`ApuradoDashboard`, `CaixaOnline`, `RelatorioCassino`) |
| Controllers novos | 5 |
| Componentes novos / páginas novas | 11 / 1 |
| Hooks / utils novos | 2 / 1 |

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Princípio / Regra | Verificação | Status |
|---|---|---|
| I. Nomes no backend | Tabelas e colunas em português snake_case (`caixa_lancamentos`, `caixa_prestacoes_contas`, `prestado_por`, `desfeita_por`, `percentual_comissao_gerente`); enum `TipoLancamentoCaixa`; rotas kebab-case (`/caixa/clientes/resumo`) | ✅ Pass |
| I. Nomes no frontend | Componentes em inglês PascalCase (`PanelCash`, `PeriodFilter`, `CashTable`, `SettlementModal`...); hooks `useCaixa`, `useTransacoesCaixa`; util `periods.js`; variáveis e props snake_case em português | ✅ Pass |
| II. Idioma | Artefatos, comentários, mensagens e textos em português | ✅ Pass |
| III. Componentes | Cada componente em pasta própria com `index.jsx` e `styles.jsx` | ✅ Pass |
| IV. Escopo estrito | Arquivos existentes listados abaixo, confirmados pelo responsável em 2026-10-21 | ✅ Pass |
| V. Legibilidade | Revisão ao final de cada tarefa | ✅ Pass |
| VI. Consistência | Prestação no padrão de `afiliados_prestacoes_contas` (spec 012); permissões no padrão de `Funcao.php` e migration de dados como a da spec 015; alcance como `AlcanceDashboard`; somas das apostas iguais às da Dashboard; `PanelShell`, `PanelTabs`, `StatCard`, `ActionsMenu`, `ModalHeader`, `Pagination`, `ChartCanvas`, `useCampoValor` reaproveitados | ✅ Pass |
| VII. Sistema antigo | `CaixaController`, `Lancamentos`, travas e telas `financial/*` analisados (R-01); diferenças de comportamento aprovadas e registradas em Clarifications | ✅ Pass |
| VIII. Fidelidade visual | Diferenças aprovadas pelo responsável e registradas na spec (uma tela com abas e trilha, atalhos de período, modais do painel, cartões no celular) | ✅ Pass |
| IX. Dados fake | Nenhum fake novo; remove `DadosFake::caixa_usuario` e `DadosFake::percentual_comissao_gerente` na mesma entrega | ✅ Pass |
| Stack | `phpoffice/phpspreadsheet` justificada em R-09 e autorizada na spec | ✅ Pass |
| Timestamps / soft delete | Tabelas novas com `timestamps()` e `softDeletes()`; excluir lançamento e desfazer prestação = soft delete | ✅ Pass |
| Comentários de colunas | Todas as colunas novas com `->comment()` ([data-model.md](data-model.md)) | ✅ Pass |
| Referências a tabelas inexistentes | Nenhuma: as FKs apontam para tabelas que já existem ou são criadas na mesma spec (ordem das migrations) | ✅ Pass |
| Paginação ≤ 100 | Padrão 20, máximo 100 em todas as listas novas | ✅ Pass |
| Atualização do frontend | Sem mudança de cache/PWA | ✅ Pass |
| Tamanho dos modais | `CashTransactionsModal` com altura fixa desde a abertura (carrega depois); demais com altura do conteúdo; 100% no celular; cabeçalho e botões sem encolher | ✅ Pass |
| Cabeçalho dos modais | Todos com `ModalHeader` (ícone sem fundo, título e descrição) | ✅ Pass |
| Campos de itens existentes | Vendedor, gerente e período escolhidos de lista/calendário; só valores, motivo, observação e senha digitados | ✅ Pass |
| Sem testes | Nenhum arquivo, tarefa ou dependência de teste | ✅ Pass |
| Postman | Regenerado na entrega (16 rotas novas) | ✅ Pass |

**Resultado do gate**: aprovado; lista de arquivos existentes (Princípio IV) confirmada pelo responsável em 2026-10-21.
Reavaliado depois da Fase 1: sem violação nova.

### Arquivos existentes que serão alterados (Princípio IV)

| Arquivo | Alteração | Motivo |
|---|---|---|
| `app/Enums/Funcao.php` | Constante `PERMISSOES_CAIXA`, regra em `pode_usar` e entrada em `permissoes_padrao` | R-08 |
| `app/Services/ApuradoDashboard.php` | Lançamentos, retiradas e comissão do gerente do dia vindos do `ApuracaoCaixa` (percentual de cada gerente); `provisorios` vazio | R-02, FR-034 |
| `app/Fakes/DadosFake.php` | Remover `caixa_usuario()` e `percentual_comissao_gerente()` | Princípio IX |
| `app/Services/CaixaOnline.php` | `calcular()` aceita um intervalo (padrão: o dia, como hoje) e as somas por cliente | R-06 |
| `app/Services/RelatorioCassino.php` | Método público com as somas de um intervalo, filtro por carteira e por cliente, sem mudar `gerar()` | R-06 |
| `app/Models/UsuariosConfiguracoes.php` | `percentual_comissao_gerente` em `fillable` e `casts` | R-05 |
| `app/Http/Controllers/PainelController.php` | Ação `caixa` | contracts/api.md (rotas web) |
| `routes/api.php`, `routes/web.php` | 16 rotas de API e 1 página | contracts |
| `database/seeders/PapeisPermissoesSeeder.php` | Incluir `Funcao::PERMISSOES_CAIXA` | R-08 |
| `composer.json`, `composer.lock` | `phpoffice/phpspreadsheet` | R-09 |
| `resources/js/components/PanelShell/index.jsx` | Item "Caixa" no menu | R-10 |
| `resources/js/utils/permissions.js` | Nomes e descrições das permissões `caixa.*` (tela de permissões) | R-08 |
| `docs/postman/wssports_api.postman_collection.json` | Regenerada | Constituição |
| `specs/014-painel-dashboard/spec.md` | Nota "Alterado pela spec 017" em FR-021 e FR-022 (dados reais) | coerência dos artefatos |

O frontend da Dashboard não muda: o selo "em breve" já depende da lista `provisorios` da API.

## Project Structure

### Documentation (this feature)

```text
specs/017-painel-caixa/
├── plan.md · research.md · data-model.md · quickstart.md
├── contracts/ (api.md, paginas.md)
├── checklists/requirements.md
└── tasks.md   (/speckit-tasks)
```

### Source Code

```text
app/
├── Enums/TipoLancamentoCaixa.php                                              # novo
├── Enums/Funcao.php                                                           # alterado
├── Models/{CaixaLancamentos,CaixaPrestacoesContas}.php                        # novos
├── Models/UsuariosConfiguracoes.php                                           # alterado
├── Services/{ApuracaoCaixa,SaldoCaixa,LancamentosCaixa,PrestacaoContasCaixa,CaixaClientes,ExportacaoCaixa}.php  # novos
├── Services/{ApuradoDashboard,CaixaOnline,RelatorioCassino}.php               # alterados
├── Fakes/DadosFake.php                                                        # alterado (remove fakes)
└── Http/
    ├── Controllers/{CaixaController,CaixaLancamentosController,CaixaPrestacoesController,CaixaClientesController,CaixaExportacaoController}.php  # novos
    ├── Controllers/Concerns/GarantirAlcanceCaixa.php                          # novo
    ├── Controllers/PainelController.php                                       # alterado
    ├── Requests/{CaixaPeriodoRequest,StoreCaixaLancamentosRequest,PrestacaoContasCaixaRequest,PrestacaoContasLoteCaixaRequest,DesfazerPrestacaoCaixaRequest,LimitesVendedorCaixaRequest,ComissaoGerenteCaixaRequest}.php  # novos
    └── Resources/{CaixaLancamentosResource,CaixaPrestacoesContasResource}.php # novos
database/
├── migrations/2026_10_23_000001_create_caixa_prestacoes_contas_table.php      # novo
├── migrations/2026_10_23_000002_create_caixa_lancamentos_table.php            # novo
├── migrations/2026_10_23_000003_add_comissao_gerente_usuarios_configuracoes_table.php  # novo
├── migrations/2026_10_23_000004_criar_permissoes_caixa.php                    # novo (dados)
└── seeders/PapeisPermissoesSeeder.php                                         # alterado
routes/{api,web}.php                                                           # alterados
resources/js/
├── pages/PanelCash/{index,styles}.jsx                                         # novo
├── components/
│   ├── PanelShell/                                                            # alterado (menu)
│   ├── PeriodFilter/ · CashBreadcrumb/ · CashTable/                           # novos
│   └── CashEntryModal/ · SettlementModal/ · UndoSettlementModal/ · CashTransactionsModal/ · SellerLimitsModal/ · ManagerCommissionModal/ · BetsByPicksModal/  # novos
├── hooks/{useCaixa,useTransacoesCaixa}.js                                     # novos
└── utils/periods.js                                                           # novo; utils/permissions.js alterado
composer.json · composer.lock                                                  # alterados
docs/postman/wssports_api.postman_collection.json                              # regenerada
```

**Structure Decision**: monólito existente; página Inertia no `PanelShell` da spec 014; API no padrão
dos controllers do painel (permissão por `GarantirPermissaoCliente`/`Funcao::usuario_pode`, alcance
pela sub-hierarquia como `AlcanceDashboard`, services com bcmath e query builder).

## Complexity Tracking

Nenhuma violação a justificar.
