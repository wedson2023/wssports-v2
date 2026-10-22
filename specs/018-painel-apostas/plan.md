# Implementation Plan: Painel — Apostas (lista de apostas, bilhete, cancelamento, premiados e exportação)

**Branch**: `018-painel-apostas` | **Date**: 2026-10-22 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/018-painel-apostas/spec.md`

## Summary

Cria a tela "Apostas" do painel, backend e frontend, reaproveitando as regras de aposta que já
existem:

- **Consulta única** (`ListagemApostas`): alcance por nível (o da Dashboard; apostas do site só para
  Admin e Supervisor), situações Ativa/Cancelada, período pela data da aposta, abas Todas / Pré-jogo /
  Ao vivo / Canceladas, busca por código (qualquer data) ou nome, e filtros de resultado, vendedor,
  forma de pagamento e prêmio mínimo, usada pela lista, pelos totais, pela planilha e pelos
  premiados (R-02).
- **Totais** com as mesmas somas do Caixa (spec 017) e o cartão Encerramentos para Admin e
  Supervisor (R-03).
- **Bilhete**: o `ComprovanteApostaResource` com comissão e um bloco `painel` com as ações
  permitidas; o `TicketModal` existente ganha as ações do painel por props opcionais (R-05).
- **Cancelar aposta e editar palpites** pelas rotas e services que já existem, sem mudança.
- **2ª via** com regra no servidor (só aguardando resultado) e marca "2ª via" nos três meios de
  impressão (R-06).
- **Premiados**: até 23 premiadas do período, imagem desenhada no navegador com Canvas 2D (R-07).
- **Planilha** .xlsx até 50.000 linhas, arquivo temporário por pedido (R-08).
- **Permissões** `apostas.listar`, `apostas.exportar`, `apostas.imprimir_segunda_via` em
  `Funcao::PERMISSOES_APOSTAS`, distribuídas por migration de dados (R-09).
- **Frontend**: página `PanelBets` (`/painel/apostas`) e o atalho "Ver apostas" no Caixa (R-11).

Decisões em [research.md](research.md) (R-01 a R-12).

## Technical Context

**Language/Version**: PHP 8.2 (^8.2); JavaScript (ES2022) com JSX

**Primary Dependencies**: as instaladas (Laravel 12, Inertia 2, React 19, styled-components 6,
sweetalert2, jwt-auth, spatie/laravel-permission, phpoffice/phpspreadsheet). Nenhuma nova: a imagem
de premiados usa a API Canvas 2D do navegador.

**Storage**: MySQL; nenhuma tabela ou coluna nova; 1 índice (`apostas(situacao, created_at)`) e 1
migration de dados (permissões).

**Testing**: nenhum teste automatizado (Constituição); validação pelo [quickstart.md](quickstart.md)

**Target Platform**: navegadores atuais, desktop e celular (prioridade); deploy contínuo (spec 013)

**Project Type**: monólito Laravel + Inertia com API JWT

**Performance Goals**: cartões e primeira página em até 2 s com até 50.000 apostas no período
(SC-007): uma consulta paginada e uma agregada por requisição, subconsultas de palpites por aposta
da página (sem N+1), índices `(usuarios_id, situacao, created_at)`, `(clientes_id, situacao,
created_at)` e o novo `(situacao, created_at)`; planilha em blocos (`chunkById`) até 50.000 linhas.

**Constraints**: somas iguais às do Caixa (`ApuracaoCaixa`); bcmath nos valores; query builder com
parâmetros (busca escapada no `LIKE`); alcance e permissões sempre no servidor; cancelamento e
edição só pelos services existentes; paginação padrão 20, máximo 100; visual e componentes do
painel (specs 014 a 017) e regras de modais, cabeçalho e campos da constituição.

**Scale/Scope**:

| Item | Quantidade |
|---|---|
| Migrations | 2 (1 índice, 1 de dados) |
| Rotas da API novas | 7 |
| Rotas web novas | 1 (`/painel/apostas`) |
| Services novos / alterados | 3 (`ListagemApostas`, `ExportacaoApostas`, `PremiadosApostas`) / 1 (`ExportacaoCaixa`, se R-08 for confirmado) |
| Controllers novos | 4 (`ApostasListagemController`, `ApostasSegundaViaController`, `ApostasPremiadosController`, `ApostasExportacaoController`) |
| Requests / Resources novos | 1 (`ApostasFiltrosRequest`) / 1 (`ApostasListaResource`) |
| Support novo | 1 (`PlanilhaPainel`, se R-08 for confirmado) |
| Componentes novos / páginas novas | 5 (`BetsTable`, `BetsFilters`, `SellerPicker`, `WinnersModal`, `ActiveFilters`) / 1 (`PanelBets`) |
| Hooks / utils novos | 1 (`useApostasPainel`) / 1 (`winners_image.js`) |

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Princípio / Regra | Verificação | Status |
|---|---|---|
| I. Nomes no backend | Classes `ListagemApostas`, `ApostasListagemController` etc.; índice em `apostas`; permissões `apostas.listar`, `apostas.exportar`, `apostas.imprimir_segunda_via` no padrão `<recurso>.<acao>`; rotas kebab-case (`/apostas/segunda-via`) e query em snake_case (`premio_minimo`, `todo_periodo`) | ✅ Pass |
| I. Nomes no frontend | Componentes em inglês PascalCase (`PanelBets`, `BetsTable`, `BetsFilters`, `SellerPicker`, `WinnersModal`, `ActiveFilters`); hook `useApostasPainel`; util `winners_image.js`; variáveis e props snake_case em português | ✅ Pass |
| II. Idioma | Artefatos, comentários, mensagens e textos em português | ✅ Pass |
| III. Componentes | Cada componente em pasta própria com `index.jsx` e `styles.jsx` | ✅ Pass |
| IV. Escopo estrito | Arquivos existentes listados abaixo; **aguardando confirmação do responsável** | ⏳ Pendente |
| V. Legibilidade | Revisão ao final de cada tarefa | ✅ Pass |
| VI. Consistência | Permissões em `Funcao.php` + migration de dados como a da spec 017; alcance pelo `AlcanceDashboard`; somas do `ApuracaoCaixa`; planilha no estilo do Caixa; `PanelShell`, `PanelTabs`, `PeriodFilter`, `StatCard`, `ActionsMenu`, `ModalHeader`, `Pagination`, `TicketModal`, `useCampoValor`, `useImpressao` reaproveitados | ✅ Pass |
| VII. Sistema antigo | `BilhetesController` (`index`, `excelApostas`, `premiados`, `destroy`), `ConfrontosController@destroy`, travas e telas `bets`, `bar`, `modals/ticket`, `helpers.js` analisados na `main` `408cf730` (R-01); diferenças aprovadas e registradas em Clarifications | ✅ Pass |
| VIII. Fidelidade visual | Diferenças aprovadas na spec (abas do Caixa, filtros em painel, cartões sempre visíveis, visual do painel novo); referências antigas listadas em [contracts/paginas.md](contracts/paginas.md) §10 | ✅ Pass |
| IX. Dados fake | Nenhum | ✅ Pass |
| Stack | Sem dependência nova (Canvas 2D no lugar de `html2canvas`, R-07) | ✅ Pass |
| Timestamps / soft delete | Sem tabela nova; nada é excluído | ✅ Pass |
| Comentários de colunas | Sem coluna nova (só índice) | ✅ Pass |
| Referências a tabelas inexistentes | Nenhuma | ✅ Pass |
| Paginação ≤ 100 | Lista padrão 20, máximo 100; vendedores do filtro até 20; premiados até 23; a planilha é exportação, limitada a 50.000 linhas (Clarifications) | ✅ Pass |
| Atualização do frontend | Sem mudança de cache/PWA | ✅ Pass |
| Tamanho dos modais | `TicketModal` mantém o tamanho atual; `WinnersModal` com altura do conteúdo; filtros em tela cheia no celular; cabeçalho e rodapé sem encolher | ✅ Pass |
| Cabeçalho dos modais | `WinnersModal` e o modal de filtros (celular) com `ModalHeader`; o `TicketModal` mantém o cabeçalho próprio do bilhete, como nas specs 006 e 014 | ✅ Pass |
| Campos de itens existentes | Vendedor escolhido na busca com resultados; resultado e forma de pagamento em chips; só busca e prêmio mínimo digitados | ✅ Pass |
| Sem testes | Nenhum arquivo, tarefa ou dependência de teste | ✅ Pass |
| Postman | Regenerado na entrega (7 rotas novas) | ✅ Pass |

**Resultado do gate**: aprovado, com a lista de arquivos existentes (Princípio IV) pendente de
confirmação do responsável. Reavaliado depois da Fase 1: sem violação nova.

### Arquivos existentes que serão alterados (Princípio IV)

| Arquivo | Alteração | Motivo |
|---|---|---|
| `app/Enums/Funcao.php` | `apostas.listar`, `apostas.exportar`, `apostas.imprimir_segunda_via` em `PERMISSOES_APOSTAS`; em `pode_usar`, as três valem para todas as funções (como `apostas.cancelar`); comentário atualizado | R-09 |
| `app/Http/Controllers/PainelController.php` | Ação `apostas` | contracts/api.md §5 |
| `routes/api.php`, `routes/web.php` | 7 rotas de API e 1 página | contracts |
| `app/Services/ExportacaoCaixa.php` | Usar `PlanilhaPainel` no lugar do método privado `gerar` (sem mudar a planilha do Caixa) | R-08 (**opcional**: só se o responsável preferir não duplicar) |
| `resources/js/components/PanelShell/index.jsx` | Item "Apostas" no menu | R-11 |
| `resources/js/components/TicketModal/index.jsx` e `styles.jsx` | Props opcionais: comissão, forma de pagamento, faixa de cancelada, imprimir 2ª via, cancelar aposta, cancelar/restaurar palpite; sem as props, site e Dashboard ficam iguais | R-05 |
| `resources/js/hooks/useImpressao.js` | `imprimir_bilhete(comprovante, { segunda_via })`; modo APP com `true` no último segmento | R-06 |
| `resources/js/utils/print.js`, `resources/js/utils/thermal.js` | Linha "2ª via" opcional no topo do comprovante | R-06 |
| `resources/js/pages/PanelCash/index.jsx` | Item "Ver apostas" no menu da linha do vendedor | R-11, FR-025 |
| `resources/js/utils/permissions.js` | Nomes e descrições das três permissões novas | R-09 |
| `docs/postman/wssports_api.postman_collection.json` | Regenerada | Constituição |
| `specs/017-painel-caixa/spec.md` | Nota "Alterado pela spec 018" na Clarification do atalho "ver apostas do vendedor" | coerência dos artefatos |

`database/seeders/PapeisPermissoesSeeder.php` não muda: já espalha `Funcao::PERMISSOES_APOSTAS`.

## Project Structure

### Documentation (this feature)

```text
specs/018-painel-apostas/
├── plan.md · research.md · data-model.md · quickstart.md
├── contracts/ (api.md, paginas.md)
├── checklists/requirements.md
└── tasks.md   (/speckit-tasks)
```

### Source Code

```text
app/
├── Enums/Funcao.php                                                           # alterado
├── Services/{ListagemApostas,ExportacaoApostas,PremiadosApostas}.php           # novos
├── Services/ExportacaoCaixa.php                                               # alterado (se R-08 confirmado)
├── Support/PlanilhaPainel.php                                                 # novo (se R-08 confirmado)
└── Http/
    ├── Controllers/{ApostasListagemController,ApostasSegundaViaController,ApostasPremiadosController,ApostasExportacaoController}.php  # novos
    ├── Controllers/PainelController.php                                       # alterado
    ├── Requests/ApostasFiltrosRequest.php                                     # novo
    └── Resources/ApostasListaResource.php                                     # novo
database/
├── migrations/2026_10_24_000001_add_indice_situacao_apostas_table.php         # novo
└── migrations/2026_10_24_000002_criar_permissoes_apostas_painel.php            # novo (dados)
routes/{api,web}.php                                                           # alterados
resources/js/
├── pages/PanelBets/{index,styles}.jsx                                         # novo
├── pages/PanelCash/index.jsx                                                  # alterado (Ver apostas)
├── components/
│   ├── PanelShell/ · TicketModal/                                             # alterados
│   └── BetsTable/ · BetsFilters/ · ActiveFilters/ · SellerPicker/ · WinnersModal/   # novos
├── hooks/useApostasPainel.js                                                  # novo; hooks/useImpressao.js alterado
└── utils/winners_image.js                                                     # novo; utils/{print,thermal,permissions}.js alterados
docs/postman/wssports_api.postman_collection.json                              # regenerada
```

**Structure Decision**: monólito existente; página Inertia no `PanelShell` da spec 014; API no padrão
dos controllers do painel (permissão por `GarantirPermissaoCliente`/`Funcao::usuario_pode`, alcance
pela sub-hierarquia como `AlcanceDashboard`, services com bcmath e query builder); bilhete,
cancelamento, edição e impressão reaproveitados.

## Complexity Tracking

Nenhuma violação a justificar.
