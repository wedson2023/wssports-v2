# Implementation Plan: Painel — Clientes (cadastros, saques, ranking, lixeira, exportação e notificações)

**Branch**: `019-painel-clientes` | **Date**: 2026-10-22 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/019-painel-clientes/spec.md`

## Summary

Cria a tela "Clientes" do painel (a antiga "Créditos"), backend e frontend, reaproveitando a gestão
de clientes (spec 002), os saques e o acesso assistido (spec 007), a liquidação com janela de
segurança (specs 009 e 010) e o Web Push (spec 010):

- **Alcance por rede via afiliado** (`AlcanceClientes`, R-02/R-03): Admin vê todos; Supervisor, os
  clientes dos afiliados dos gerentes abaixo dele; Gerente, os dos afiliados dele. Vale em todas as
  rotas de cliente, na fila de saques, nas cotações por cliente, nos encerramentos, no Caixa clientes
  e nas apostas de clientes (muda o que foi construído nas specs 002, 007, 017 e 018, autorizado).
- **Afiliados** (R-04): gerente responsável obrigatório no cadastro, na edição e na aprovação; filtro
  "Sem gerente responsável"; gerente escolhido da rede do Supervisor.
- **Cadastros** (R-05): saldo líquido (depósitos + créditos manuais em Saldo − saques − débitos
  manuais em Saldo) e os filtros do antigo (depósito, Pix em aberto, modalidade e aniversariantes no
  período).
- **Ajuste de saldo com senha** e **antecipação de pendentes com senha** (R-06): prêmios e
  encerramentos na janela de segurança, pelas mesmas regras de pagamento, registrados em
  `clientes_antecipacoes`.
- **Saques** (R-07): busca, período, cartões e máscara do telefone.
- **Ranking** (R-08): Depósitos, Valor apostado, Resultado para a banca (o cálculo do Caixa clientes)
  e Saques pagos.
- **Exportação** .xlsx por aba até 50.000 linhas (`PlanilhaPainel`, R-09).
- **Notificação push manual** (R-10): tipo "Aviso da banca", público Todos / Por ID / Sem interagir,
  limite diário configurável (padrão 3), envio em fila.
- **Filtro por cliente na tela Apostas** (R-11) e **permissões novas** (R-12).
- **Frontend**: página `PanelClients` (`/painel/clientes`) com abas, gaveta de detalhe e modais
  (R-13).

Decisões em [research.md](research.md) (R-01 a R-16).

## Technical Context

**Language/Version**: PHP 8.2 (^8.2); JavaScript (ES2022) com JSX

**Primary Dependencies**: as instaladas (Laravel 12, Inertia 2, React 19, styled-components 6,
sweetalert2, jwt-auth, spatie/laravel-permission, phpoffice/phpspreadsheet, minishlink/web-push).
Nenhuma nova.

**Storage**: MySQL; 2 tabelas novas (`clientes_antecipacoes`, `clientes_notificacoes_envios`), 3
colunas novas, índices (R-14) e 1 migration de dados (permissões).

**Testing**: nenhum teste automatizado (Constituição); validação pelo [quickstart.md](quickstart.md)

**Target Platform**: navegadores atuais, desktop e celular (prioridade); deploy contínuo (spec 013)

**Project Type**: monólito Laravel + Inertia com API JWT

**Performance Goals**: primeira página de cada aba em até 2 s com 100.000 clientes (SC-008):
alcance por subconsulta no banco, saldo líquido por subselect só da página, ranking ordenado no banco
(exceto Resultado, que reaproveita o Caixa clientes), índices novos; planilha em blocos até 50.000
linhas; notificações em fila, em blocos de 500.

**Constraints**: alcance e permissões sempre no servidor; bcmath nos valores; query builder com
parâmetros; liquidação e saques só pelos services existentes; paginação padrão 20 (saques 30),
máximo 100; visual e componentes do painel (specs 014 a 018) e regras de modais, cabeçalho e campos
da constituição.

**Scale/Scope**:

| Item | Quantidade |
|---|---|
| Migrations | 5 (2 tabelas, 1 de colunas, 1 de índices, 1 de dados) |
| Rotas da API novas / alteradas | 10 / ~20 (alcance e campos novos) |
| Rotas web novas | 1 (`/painel/clientes`) |
| Services novos / alterados | 6 (`AlcanceClientes`, `AntecipacaoPendentes`, `RankingClientes`, `ExportacaoClientes`, `NotificacoesManuais`, `FiltrosClientes`) / 10 (ver lista abaixo) |
| Controllers novos | 4 (`ClientesPendentesController`, `ClientesRankingController`, `ClientesExportacaoController`, `ClientesNotificacoesController`) |
| Jobs / Enums novos | 1 (`EnviarNotificacoesManuais`) / 1 (`PublicoNotificacao`) |
| Componentes novos / páginas novas | 16 (R-13) / 1 (`PanelClients`) |
| Hooks novos | 1 (`useClientesPainel`) |

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Princípio / Regra | Verificação | Status |
|---|---|---|
| I. Nomes no backend | Tabelas `clientes_antecipacoes`, `clientes_notificacoes_envios` (prefixo da tabela principal); colunas em português; enum `AvisoBanca = 'Aviso da banca'`, `Antecipação = 'Antecipação de pendentes'`; permissões `clientes.ver_ranking`, `clientes.exportar`, `clientes.enviar_notificacao`, `clientes.antecipar_pendentes`; rotas kebab-case, query snake_case | ✅ Pass |
| I. Nomes no frontend | Componentes em inglês PascalCase (`PanelClients`, `ClientsTable`, `ClientDrawer` etc.); hook `useClientesPainel`; variáveis e props snake_case em português | ✅ Pass |
| II. Idioma | Artefatos, comentários, mensagens e textos em português | ✅ Pass |
| III. Componentes | Cada componente em pasta própria com `index.jsx` e `styles.jsx` | ✅ Pass |
| IV. Escopo estrito | Arquivos existentes listados abaixo; o alcance nas specs 002, 007, 012, 017 e 018 foi autorizado pelo responsável (Clarifications); **a lista completa e os pontos P-1 e P-2 aguardam confirmação** | ⚠️ Aguardando confirmação |
| V. Legibilidade | Revisão ao final de cada tarefa | ✅ Pass |
| VI. Consistência | Permissões em `Funcao.php` + migration de dados (specs 017/018); alcance em service + trait de controller (como `GarantirAlcanceAfiliado`/`GarantirAlcanceCaixa`); senha pelo `ConfirmacaoCaixa`; planilha pelo `PlanilhaPainel`; push pelo `NotificacoesPush`; componentes do painel reaproveitados | ✅ Pass |
| VII. Sistema antigo | `CreditosController`, `CadastroController@destroy`, telas `credits`, `modals/filter`, `modals/push-notification` analisados na `main` `408cf730` (R-01); diferenças aprovadas em Clarifications | ✅ Pass |
| VIII. Fidelidade visual | Diferenças aprovadas na spec (sem Transações, sem login/saldo anterior, visual do painel novo); referências em [contracts/paginas.md](contracts/paginas.md) §12 | ✅ Pass |
| IX. Dados fake | Nenhum | ✅ Pass |
| Stack | Sem dependência nova | ✅ Pass |
| Timestamps / soft delete | As 2 tabelas novas com `timestamps()` + `softDeletes()` e models com `SoftDeletes` | ✅ Pass |
| Comentários de colunas | Todas as colunas novas com `->comment()` ([data-model.md](data-model.md)) | ✅ Pass |
| Referências a tabelas inexistentes | Nenhuma | ✅ Pass |
| Paginação ≤ 100 | Cadastros, Lixeira e Ranking padrão 20; Saques 30; extrato 20; seletores até 20; máximo 100; a planilha é exportação limitada a 50.000 | ✅ Pass |
| Atualização do frontend | Sem mudança de cache/PWA | ✅ Pass |
| Tamanho dos modais | Gaveta com altura fixa (abas internas); modais de ação com altura do conteúdo; tela cheia no celular; cabeçalho e rodapé sem encolher | ✅ Pass |
| Cabeçalho dos modais | Todos com `ModalHeader` (ícone sem fundo, título, descrição, fechar) | ✅ Pass |
| Campos de itens existentes | Afiliado, gerente e clientes do público escolhidos de listas com busca; carteira, tipo, critério, período e situação em chips; esportes pelo `BettingRulesFields` | ✅ Pass |
| Sem testes | Nenhum arquivo, tarefa ou dependência de teste | ✅ Pass |
| Postman | Regenerado na entrega | ✅ Pass |

**Resultado do gate**: sem violação; o Princípio IV fica pendente da confirmação da lista abaixo e
dos pontos:

- **P-1** (R-03): a Caixa online da Dashboard (spec 014) continua mostrando ao Supervisor a banca
  toda? Proposta: sim, não muda nesta spec.
- **P-2** (R-04): o Supervisor passa a ver só os afiliados dos gerentes da rede dele
  (`GarantirAlcanceAfiliado`)? Proposta: sim, para o FR-003b funcionar.

Reavaliado depois da Fase 1: sem violação nova.

### Arquivos existentes que serão alterados (Princípio IV)

| Arquivo | Alteração | Motivo |
|---|---|---|
| `app/Enums/Funcao.php` | 4 permissões em `PERMISSOES_CLIENTES`; 3 em `PERMISSOES_CLIENTES_RESTRITAS`; comentário | R-12 |
| `app/Enums/TipoNotificacao.php` | Caso `AvisoBanca` e `coluna_preferencia` | R-10 |
| `app/Enums/OrigemApuracao.php` | Caso `Antecipação` | R-06 |
| `app/Models/Configuracoes.php`, `ClientesInscricoesNotificacoes.php`, `ClientesNotificacoes.php` | Campos novos no `fillable`/`casts`; relação com o envio | data-model §2 |
| `app/Http/Controllers/ClientesController.php` | Alcance, saldo líquido, filtros novos (via `FiltrosClientes`) | R-02, R-05 |
| `app/Http/Controllers/ClientesTransacoesController.php`, `ClientesConfiguracoesController.php`, `ClientesMeiosPagamentoController.php`, `AcessosAssistidosController.php` | Garantir o cliente no alcance | R-03 |
| `app/Http/Controllers/SaquesController.php` | Alcance, busca, período, `resumo` | R-07 |
| `app/Http/Controllers/CotacoesPainelController.php` (`clientes`), `PorcentagensClientesController.php`, `ApostasEncerramentosController.php` | Alcance | R-03 |
| `app/Http/Controllers/CaixaClientesController.php`, `app/Services/CaixaClientes.php`, `CaixaOnline.php`, `RelatorioCassino.php`, `ExportacaoCaixa.php` | Subconsulta opcional do alcance (sem ela, igual a hoje); campo de entradas do cassino por cliente | R-03, R-08 |
| `app/Services/ListagemApostas.php`, `AlcanceApostas.php`, `app/Http/Requests/ApostasFiltrosRequest.php` | Apostas do site pela rede; filtro `clientes_id` | R-03, R-11 |
| `app/Services/LiquidacaoApostas.php` | `liquidar(..., ignorar_janela, autor)` opcionais | R-06 |
| `app/Services/LiquidacaoEncerramentos.php` | Método público `creditar_encerramento($id, $autor)` extraído do privado | R-06 |
| `app/Services/NotificacoesPush.php`, `InscricoesNotificacoes.php`, `app/Http/Controllers/AreaClienteNotificacoesController.php` (ou request) | Tag/URL do aviso da banca; preferência `receber_avisos_banca` | R-10 |
| `app/Http/Resources/ClientesResource.php`, `SaquesResource.php`, `AfiliadosResource.php` | `saldo_liquido`; telefone mascarado e saldo; `sem_gerente` | R-05, R-07, R-04 |
| `app/Http/Requests/ClientesTransacoesRequest.php`, `UpdateClientesRequest.php` | `senha`; afiliado da rede | R-06, R-15 |
| `app/Http/Requests/StoreAfiliadosRequest.php`, `UpdateAfiliadosRequest.php`, `AprovarAfiliadoRequest.php`, `Concerns/DadosAfiliado.php`, `app/Services/CadastroAfiliados.php`, `ListagemAfiliados.php`, `app/Http/Controllers/AfiliadosController.php` (validar `sem_gerente`), `app/Http/Controllers/Concerns/GarantirAlcanceAfiliado.php` | Gerente obrigatório e da rede; filtro `sem_gerente`; alcance do Supervisor (P-2) | R-04 |
| `app/Http/Controllers/PainelController.php`, `routes/api.php`, `routes/web.php` | Página e rotas | contracts |
| `resources/js/components/PanelShell/index.jsx` | Item "Clientes" | R-13 |
| `resources/js/pages/PanelBets/index.jsx`, `resources/js/hooks/useApostasPainel.js` | Filtro e etiqueta de cliente | R-11 |
| `resources/js/components/NotificationSettingsModal/index.jsx` | Item "Avisos da banca" | R-10 |
| `resources/js/utils/permissions.js` | Nomes e descrições das 4 permissões | R-12 |
| `docs/postman/wssports_api.postman_collection.json` | Regenerada | Constituição |
| `specs/002-clientes/spec.md`, `007-painel-cliente-pagamentos/spec.md`, `012-afiliados/spec.md`, `017-painel-caixa/spec.md`, `018-painel-apostas/spec.md` | Nota "Alterado pela spec 019" nos requisitos de alcance, gerente do afiliado e ajuste com senha | coerência dos artefatos |

## Project Structure

### Documentation (this feature)

```text
specs/019-painel-clientes/
├── plan.md · research.md · data-model.md · quickstart.md
├── contracts/ (api.md, paginas.md)
├── checklists/requirements.md
└── tasks.md   (/speckit-tasks)
```

### Source Code

```text
app/
├── Enums/{Funcao,TipoNotificacao,OrigemApuracao}.php                          # alterados
├── Enums/PublicoNotificacao.php                                               # novo
├── Models/{ClientesAntecipacoes,ClientesNotificacoesEnvios}.php                # novos
├── Jobs/EnviarNotificacoesManuais.php                                         # novo
├── Services/{AlcanceClientes,FiltrosClientes,AntecipacaoPendentes,RankingClientes,ExportacaoClientes,NotificacoesManuais}.php  # novos
├── Services/{CaixaClientes,CaixaOnline,RelatorioCassino,ExportacaoCaixa,ListagemApostas,AlcanceApostas,LiquidacaoApostas,LiquidacaoEncerramentos,NotificacoesPush,InscricoesNotificacoes,CadastroAfiliados,ListagemAfiliados}.php  # alterados
└── Http/
    ├── Controllers/{ClientesPendentesController,ClientesRankingController,ClientesExportacaoController,ClientesNotificacoesController}.php  # novos
    ├── Controllers/Concerns/GarantirAlcanceCliente.php                        # novo
    ├── Controllers/… (ver lista de alterados)
    ├── Requests/{AnteciparPendentesRequest,RankingClientesRequest,NotificacaoManualRequest}.php  # novos
    └── Resources/{RankingClientesResource,ClientesNotificacoesEnviosResource}.php  # novos
database/migrations/
├── 2026_10_25_000001_create_clientes_antecipacoes_table.php
├── 2026_10_25_000002_create_clientes_notificacoes_envios_table.php
├── 2026_10_25_000003_add_avisos_banca_notificacoes_configuracoes.php
├── 2026_10_25_000004_add_indices_painel_clientes.php
└── 2026_10_25_000005_criar_permissoes_clientes_painel.php                     # dados
resources/js/
├── pages/PanelClients/{index,styles}.jsx                                      # novo
├── components/{ClientsTable,ClientsFilters,ClientDrawer,ClientFormModal,ClientSettingsForm,PaymentMethodsList,BalanceAdjustModal,AnticipateModal,WithdrawalsTable,WithdrawalDecisionModal,RankingTable,TrashTable,RestoreConflictModal,PushNotificationModal,AffiliatePicker,ClientPicker}/  # novos
├── hooks/useClientesPainel.js                                                 # novo
└── (alterados: PanelShell, PanelBets, useApostasPainel, NotificationSettingsModal, utils/permissions.js)
docs/postman/wssports_api.postman_collection.json                              # regenerada
```

**Structure Decision**: monólito existente; página Inertia no `PanelShell`; API no padrão dos
controllers do painel (permissão por `GarantirPermissaoCliente`, alcance por service + trait,
services com bcmath e query builder); regras de cliente, saque, liquidação e push reaproveitadas.

## Complexity Tracking

Nenhuma violação a justificar. A mudança de alcance em código de specs anteriores foi pedida e
autorizada pelo responsável (Clarifications da spec, sessão das respostas ao `/speckit-specify`).
