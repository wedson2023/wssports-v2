# Research: Painel — Bônus (promoções, bônus recebidos e estornos)

**Feature**: [spec.md](spec.md) | **Plano**: [plan.md](plan.md) | **Data**: 2026-10-23

## R-01 — O que existe hoje e como o antigo funcionava (Princípio VII)

**Sistema antigo consultado** (`wssports.bet`, `main` `408cf730`, somente leitura):

| Arquivo | O que faz |
|---|---|
| `app/Http/Controllers/BonusController.php` | `index` (aba Todos: `creditos_bonuses` com busca por nome e `total_rollover`; aba Ativos: `creditos_transacaos` com `operacao = 'Bônus'` e saldo > 0); `store`/`update` (validações, nome único, término no futuro, sobreposição por "ganho em" + afiliado, modalidade fixa depois de criada); `show`; `destroy` comentado; `rollover` (reverter geral ou individual, com senha só no geral) |
| `app/Console/Commands/BonusCommand.php` | `command:bonus`: expira os créditos de bônus vencidos (zera rollover e saldo do crédito, recalcula o saldo de bônus da modalidade) e zera o rollover cumprido |
| `app/Models/CreditosBonus.php` e migrations `creditos_bonuses` (2023-09 a 2025-07) | Campos do bônus: nome único, `ganho_em`, `tipo_ganho`, valor, `qtde_indicacoes`, datas, ativo, `usuarios_id`, modalidade, rollover, `dias_cumprir_bonus`, valores de aposta, conversão e depósito, odds mínimas, `rollback`, `rollover_primeiro_deposito`, `filiados_id` |
| `resources/js/screens/adm/bonus/index.js` | "Lista de bônus - total N", abas Todos e Ativos, busca, botão Cadastrar (Admin, Supervisor e Gerente com `funcao_pin`), ícones reverter (interruptor) e editar |
| `resources/js/screens/adm/modals/bonus/index.js` | "Cadastrar bônus" / "Atualizar bônus": campos e habilitação por "ganho em", tipo e modalidade |

Resumo do comportamento e dos problemas: spec, seção "Referências do sistema antigo".

**O que já existe no v2** (reaproveitado):

| Recurso | Onde |
|---|---|
| CRUD de promoções, travas depois de aplicada, sobreposição, estorno geral em fila | `ClientesPromocoesController`, `ClientesPromocoesRequest`, `EstornarPromocaoRequest`, `ClientesPromocoesResource`, `Jobs/EstornarPromocao` |
| Crédito do bônus (cadastro, depósito, indicação), sempre com origem `Promoção` e `referencia_id` = promoção | `CadastroClientes`, `ProcessamentoDepositos`, `Indicacoes` + `SaldoClientes::creditar` |
| Rollover do bônus (só quando a promoção exige rollover > 0), expiração, converter e excluir pelo cliente | `RolloverClientes::criar_bonus`, `GestaoBonus`, `ExpirarBonusCommand` (`bonus:expirar`, a cada 10 min) |
| Cancelamentos automáticos do bônus | `ProcessamentoDepositos::cancelar_bonus` (estorno total do depósito), `Indicacoes::cancelar_grupo` (estorno do depósito do indicado) |
| Alcance por rede | `AlcanceClientes` (spec 019), traits `GarantirAlcanceCliente` e `GarantirAlcanceAfiliado` |
| Senha nas operações de dinheiro | `Requests/Concerns/ConfirmacaoCaixa::regra_senha` ("Senha incorreta.") |
| Componentes do painel | `PanelShell`, `PanelTabs`, `StatCard`, `ActionsMenu`, `ConfirmModal`, `ActiveFilters`, `PeriodFilter`, `Pagination`, `ModalHeader`, `EmptyMessage`, `AffiliatePicker`, `ClientPicker`, `ClientDrawer`, `useCampoValor` |

## R-02 — O que é um "bônus recebido"

**Decision**: o bônus recebido é a **transação de crédito de origem `Promoção`** (`clientes_transacoes`,
`referencia_id` = promoção), com o rollover dela por `clientes_rollovers.clientes_transacoes_id`
(LEFT JOIN, porque promoção com rollover 0 não cria rollover) e o estorno por
`clientes_promocoes_estornos.clientes_transacoes_id` (LEFT JOIN). O id do bônus nas rotas é o id
dessa transação.

**Rationale**: todas as formas de crédito (cadastro, depósito, indicação) já gravam a transação
`Promoção`; usar o rollover como base esconderia os bônus sem rollover (Primeiro cadastro com
rollover 0, edge case da spec). Não muda `RolloverClientes::criar_bonus` nem o que o cliente vê.

**Alternatives considered**: criar sempre um rollover (vezes 0) para todo bônus — muda o comportamento
da área do cliente (o bônus sem rollover passaria a ter "Converter") e exigiria migrar dados; rejeitado.

## R-03 — Situação do bônus (FR-021)

**Decision**: calculada no banco por `CASE`, nesta ordem (a primeira que bater vale):

1. existe estorno em `clientes_promocoes_estornos` → **Estornado**;
2. `convertido_em` → **Convertido**;
3. `expirado_em` → **Expirado**;
4. `excluido_em` → **Excluído pelo cliente**;
5. `cancelado_em` (cancelamento antigo sem registro de estorno) → **Estornado**;
6. `cumprido_em`, ou sem rollover → **Cumprido**;
7. senão → **Em andamento**.

O mesmo `CASE` serve de filtro, de contagem dos cartões e de coluna da lista. Enum novo
`SituacaoBonus` com os seis valores em português.

**Rationale**: um único lugar define a situação; os cancelamentos automáticos passam a gravar um
estorno "Automático" (R-05), então a ordem 1 vem antes de 4 (o estorno do depósito marca também
`excluido_em`, e sem a ordem ele apareceria como "Excluído pelo cliente").

## R-04 — Tabela de estornos por bônus (`clientes_promocoes_estornos`)

**Decision**: tabela nova, uma linha por bônus estornado, com `clientes_transacoes_id` **único** (o
crédito do bônus): tipo (`Individual`, `Geral`, `Automático`, enum `TipoEstornoBonus`), autor (nulo no
automático), motivo, valor retirado e a transação de débito (nula quando não havia saldo para
retirar). Detalhes em [data-model.md](data-model.md) §1.

**Rationale**: dá autor, data, motivo e valor por bônus (FR-022, FR-027) e torna o estorno idempotente
por bônus (FR-028): o índice único impede retirar duas vezes, inclusive entre o estorno geral e o
individual. Hoje o job decide a idempotência por cliente (existe transação `Estorno` da promoção para o
cliente), o que falha com dois bônus da mesma promoção (indicação recorrente) e com o estorno
individual.

**Alternatives considered**: colunas de estorno em `clientes_rollovers` — não cobre bônus sem rollover;
rejeitado.

## R-05 — Estorno do bônus: individual, geral e automático (FR-026 a FR-028)

**Decision**: service novo `EstornoBonus`:

- `estornar(ClientesTransacoes $credito, TipoEstornoBonus $tipo, ?Usuarios $autor, string $motivo)`:
  numa transação, trava o cliente e o rollover (`lockForUpdate`, mesma ordem de `GestaoBonus::bloquear`:
  cliente → rollover), confere que o bônus está **Em andamento** ou **Cumprido** (R-03; senão recusa com
  "Este bônus não pode mais ser estornado." no individual e pula no geral), retira do saldo promocional
  da modalidade o menor entre o saldo atual e `valor` do crédito (sem negativar) com
  `SaldoClientes::debitar` (origem `Estorno`, referência a promoção, observação "Estorno do bônus:
  {motivo}"), marca o rollover com `cancelado_em` e grava a linha em `clientes_promocoes_estornos`.
- `registrar_automatico(...)`: só grava a linha (tipo `Automático`, sem autor) para os cancelamentos que
  já debitam por conta própria (`ProcessamentoDepositos::cancelar_bonus`, motivo "Cancelado pelo estorno
  do depósito"; `Indicacoes::cancelar_grupo`, motivo "Cancelado pelo estorno do depósito de um
  indicado"), com o valor e a transação de débito que eles geraram.

O **job `EstornarPromocao`** passa a percorrer os créditos `Promoção` da promoção em lotes de 500
(por id) e chamar `estornar(..., Geral, autor do estorno, motivo)` para cada um; os já estornados,
convertidos, expirados ou excluídos são pulados. Os contadores continuam recalculados do banco
(clientes processados = clientes distintos dos créditos já percorridos; valor = soma dos estornos
`Geral` da promoção).

**Senha** (FR-026, FR-027): `EstornarPromocaoRequest` e o novo `EstornarBonusRequest` usam
`ConfirmacaoCaixa::regra_senha` no campo `senha` ("Senha incorreta."), conferida antes de qualquer
gravação.

**Rationale**: um só lugar para retirar um bônus; o geral fica exatamente igual a N estornos
individuais, e a trava por cliente/rollover serializa com converter, excluir e expirar (edge cases).
Nenhuma aposta é tocada (diferença aprovada).

**Alternatives considered**: manter o débito agregado por cliente no job — retiraria saldo de bônus
convertidos ou de outras promoções da mesma carteira; rejeitado pela Clarification 5.

## R-06 — Alcance das promoções (FR-002, FR-003, FR-014)

**Decision**: service novo `AlcancePromocoes`, apoiado em `AlcanceClientes::ids_gerentes`:

- **visíveis**: Admin, todas; Supervisor e Gerente, `afiliados_id IS NULL` (gerais, só leitura) ou
  afiliado com `usuarios_id` (gerente responsável) em `ids_gerentes` (inclui afiliados excluídos);
- **alteráveis**: Admin, todas; Supervisor e Gerente, só as de afiliado da rede (gerais → 403 "Só o Admin
  pode alterar promoções gerais.");
- fora do alcance → 404 "Promoção não encontrada." (como o cliente, spec 019);
- **afiliado no cadastro/edição**: `ClientesPromocoesRequest` passa a exigir `afiliados_id` para
  Supervisor e Gerente e a validar que o afiliado é da rede (`GarantirAlcanceAfiliado`/`ids_gerentes`;
  "Afiliado não encontrado."); só o Admin envia nulo ou afiliado sem gerente.
- Trait de controller `GarantirAlcancePromocao` (como `GarantirAlcanceCliente`) com middleware que
  confere a `{promocao}` da rota.

**Bônus recebidos**: `AlcanceClientes::aplicar` sobre `clientes_transacoes.clientes_id` (os clientes
da rede, inclusive bônus de promoções gerais); o `{bonus}` da rota é conferido pelo cliente dele.

**Números de uso para Supervisor/Gerente** (FR-006): as somas da página aplicam o mesmo
`AlcanceClientes::aplicar` nos créditos e débitos.

**Rationale**: a rede da promoção é a do afiliado dela, mesma regra dos clientes; não precisa de
coluna de autor. O 403 nas gerais (em vez de 404) é porque elas são visíveis.

## R-07 — Lista de promoções: situação, filtros e números de uso (FR-004 a FR-009)

**Decision**: service novo `ListagemPromocoes`:

- **situação** (`SituacaoPromocao`) por `CASE` no banco: `estorno_situacao = 'Em andamento'` →
  Estornando; `estorno_situacao = 'Concluído'` → Estornada; `ativa = 0` → Desativada;
  `data_inicio > agora` → Agendada; `data_fim < agora` → Encerrada; senão Vigente. Filtro por situação
  usa o mesmo `CASE`.
- **filtros**: `busca` (nome, `LIKE %trecho%` com parâmetro), `situacao`, `modalidade`, `categoria`,
  `afiliados_id` (id ou `geral`); ordem `data_inicio` desc; padrão 20, máximo 100. Os filtros atuais
  (`ativa`, `modalidade`, `categoria`, `por_pagina`) continuam aceitos.
- **números de uso** só para as promoções da página, em consultas agrupadas por `referencia_id` (com o
  alcance de clientes para Supervisor/Gerente): clientes distintos e soma dos créditos `Promoção`;
  soma dos créditos `Conversão de bônus` na carteira `Saldo` (convertido); retirado = soma dos débitos
  `Bônus expirado` e `Bônus excluído` mais a soma de `valor_retirado` dos estornos `Individual` e
  `Geral` em `clientes_promocoes_estornos`. A origem `Estorno` não entra pela referência porque também
  é usada nos estornos de apostas (com `referencia_id` = aposta); os automáticos não entram de novo
  porque já debitaram como `Bônus excluído`. **Cumpridos** = créditos com rollover cumprido ou sem
  rollover (LEFT JOIN em `clientes_rollovers`). Valores em string com 2 casas (bcmath na montagem).
- **ações permitidas** por linha no resource (`pode`: editar, ativar, excluir, estornar), calculadas
  pela permissão, pelo alcance (geral só Admin), pela situação (estornada não altera) e por `aplicada()`
  (excluir).

**Rationale**: o `CASE` no banco permite filtrar e paginar pela situação; os números só da página
mantêm a lista rápida (SC-008). `data_inicio`/`data_fim` são gravados em UTC; a comparação usa
`now()` do servidor.

## R-08 — Regras do cadastro que mudam (FR-015 a FR-019)

**Decision** (todas no `ClientesPromocoesRequest` / `ClientesPromocoesController`):

- **Sobreposição por afiliado** (FR-015): `garantir_sem_sobreposicao` passa a filtrar também por
  afiliado (`afiliados_id = X`, ou `IS NULL` para as gerais) e a citar a promoção em conflito:
  "Já existe uma promoção ativa desta categoria e modalidade no período: {nome} (ID {id}).".
- **Nome único** (FR-016): `Rule::unique('clientes_promocoes', 'nome')->whereNull('deleted_at')`,
  ignorando a própria promoção na edição; "Já existe uma promoção com este nome.". Os nomes repetidos já
  gravados só são cobrados quando o nome for enviado e mudar (`Rule::when` com o nome gravado).
- **Fim no passado** (FR-016): regra `after:now` aplicada só quando `data_fim` vem no pedido e é
  diferente da gravada; "A data de fim já passou.". Como o `prepareForValidation` completa os campos
  não enviados com os gravados, a comparação usa o valor original da rota antes do merge.
- **Excluir** (FR-019): `destroy` recusa com 422 "Promoção já aplicada não pode ser excluída; desative
  ou estorne." quando `aplicada()`; estornada também não exclui.
- **Ativar** (FR-018): já passa por `garantir_sem_sobreposicao` no `update` (só quando `ativa`).

**Rationale**: regras do antigo confirmadas no `/speckit-clarify`; mudanças pequenas e localizadas.

## R-09 — Bônus recebidos: lista, cartões e detalhe (FR-020 a FR-025)

**Decision**: service novo `ListagemBonusRecebidos` sobre `clientes_transacoes` (origem `Promoção`)
com JOIN em `clientes` (com excluídos) e `clientes_promocoes` (com excluídas), LEFT JOIN em
`clientes_rollovers` e `clientes_promocoes_estornos`, e o alcance de clientes (R-06):

- **filtros**: `situacao` (padrão `Em andamento`; `Todos`), `busca` (nome por trecho ou ID numérico),
  `clientes_promocoes_id`, `modalidade` (pela carteira do crédito), `data_inicial`/`data_final`
  (recebimento, fuso do sistema → UTC, até 1 ano); padrão 30, máximo 100;
- **ordem**: Em andamento por `expira_em` asc (sem prazo por último), as demais por recebimento desc;
- **colunas**: as da FR-022; "saldo promocional atual" vem da coluna da carteira em `clientes`;
  "falta" = `valor_exigido − valor_apostado` (mínimo 0) calculado em SQL `GREATEST`;
- **cartões** (`resumo`): Em andamento (quantidade e soma do que falta, sem período); Creditado
  (créditos `Promoção` no período), Convertido (créditos `Conversão de bônus` no período) e Retirado
  (débitos `Bônus expirado` e `Bônus excluído` mais os estornos `Individual` e `Geral` da tabela de
  estornos, no período; a origem `Estorno` não é usada, pelo mesmo motivo de R-07), com a busca e os
  filtros de promoção e modalidade;
- **detalhe** (`show`): a linha + regras gravadas no rollover + histórico (recebido em, cumprido em,
  convertido em, expirado em, excluído em, estornado em, com autor e motivo do estorno). As apostas que
  somaram no rollover ficam fora do detalhe (o progresso já mostra o valor apostado); ver Riscos.

**Rationale**: uma consulta paginada com JOINs indexados (`origem, referencia_id`, `clientes_transacoes_id`
do rollover e do estorno) atende 2 s (SC-008).

## R-10 — Permissões (FR-030)

**Decision**: nenhuma permissão nova e nenhuma mudança em `Funcao.php`. `clientes_promocoes.gerenciar`
libera a tela, as duas abas, cadastrar, editar, ativar e excluir; `clientes_promocoes.estornar`
(restrita a Admin e Supervisor, já em `PERMISSOES_CLIENTES_RESTRITAS`) libera os dois estornos. O
middleware `GarantirPermissaoCliente::permissao_cliente` já aplica `Funcao::usuario_pode`.

**Ponto de atenção**: o `AffiliatePicker` busca em `GET /afiliados`, que exige `afiliados.listar`. Os
padrões por função já dão essa permissão a Admin, Supervisor e Gerente; quem tiver só
`clientes_promocoes.gerenciar` sem `afiliados.listar` verá a lista de afiliados vazia. Aceito (mesmo
comportamento da tela Clientes, spec 019).

## R-11 — Frontend da tela

**Decision**: página Inertia `PanelBonus` em `/painel/bonus` (`PainelController::bonus`), item "Bônus"
(ícone `redeem`) no `PanelShell` depois de "Clientes", visível com `clientes_promocoes.gerenciar`.
Hook `useBonusPainel` (aba, filtros e página na URL, como `useClientesPainel`). Componentes novos:

| Componente | Uso |
|---|---|
| `PromotionsTable` | Lista de promoções (tabela no desktop, cartões no celular), situação com cor, números de uso, `ActionsMenu` |
| `PromotionsFilters` | Painel de filtros (situação, modalidade, categoria, afiliado) |
| `PromotionFormModal` | Cadastrar/editar, campos por grupo, visibilidade por categoria/ganho/modalidade, campos travados com aviso |
| `PromotionDrawer` | Detalhe da promoção (campos, números, estorno com progresso) |
| `PromotionReversalModal` | Estorno geral: aviso, clientes e valor creditado, motivo e senha |
| `ReceivedBonusTable` | Lista de bônus recebidos (tabela/cartões), `ActionsMenu` |
| `ReceivedBonusFilters` | Promoção, modalidade e período (chips "Desde sempre", Hoje, Ontem, Esta semana, Este mês, de `utils/periods`, e as datas de/até); a situação fica em chips acima da lista |
| `BonusDrawer` | Detalhe do bônus: regras, histórico, "Ver cliente" |
| `BonusReversalModal` | Estorno individual: cliente, bônus, valor a retirar, motivo e senha |
| `PromotionPicker` | Escolha de promoção por busca (`GET /clientes-promocoes?busca=`), só da lista |

Reaproveitados sem mudança: `PanelShell` (só o item novo), `PanelTabs`, `StatCard`, `ActionsMenu`,
`ConfirmModal` (ativar/desativar e excluir), `ActiveFilters`, `PeriodFilter`, `Pagination`,
`ModalHeader`, `EmptyMessage`, `AffiliatePicker`, `useCampoValor`. "Ver cliente" abre o `ClientDrawer`
da spec 019 dentro da página Bônus, sem ações (`acoes` vazio), para quem tem `clientes.listar`.

**Progresso do estorno geral**: enquanto alguma promoção da página estiver "Estornando", o hook
consulta `GET /clientes-promocoes/{id}` a cada 3 s até "Estornada" (sem recarregar a lista inteira).

## R-12 — Dados antigos (migração de dados)

**Decision**: migration de dados que preenche `clientes_promocoes_estornos` para o que já aconteceu:

- promoções com estorno concluído: um registro `Geral` por crédito cujo rollover tem `cancelado_em`
  sem `excluido_em`/`expirado_em`/`convertido_em` (ou sem rollover), com autor e motivo da promoção e o
  valor 0 (o débito antigo foi agregado por cliente e não é rateável) — a soma por promoção continua no
  campo `estorno_valor_total`;
- cancelamentos pelo estorno de depósito e de indicação: um registro `Automático` por rollover/grupo
  cancelado, achado pela transação de débito com a observação gravada pelos services ("Bônus cancelado
  pelo estorno do depósito" e "Bônus de indicação cancelado pelo estorno do depósito de um indicado").

**Rationale**: sem isso, esses bônus apareceriam como "Excluído pelo cliente" ou "Estornado" sem autor.
É idempotente (`insertOrIgnore` pelo índice único).

## R-13 — Desempenho (SC-007, SC-008)

- Índices novos: `clientes_promocoes (afiliados_id, data_inicio)`, `clientes_promocoes (nome)`,
  `clientes_rollovers (clientes_transacoes_id)` e `clientes_transacoes (origem, carteira, created_at)`
  (cartões por período). O de `clientes_transacoes (origem, referencia_id)` já existe.
- Estorno geral em lotes de 500 por id, cada bônus na sua transação curta (não trava o site; SC-007).
- Números de uso só da página (≤ 100 promoções) em 2 consultas agrupadas.

## R-14 — Dados fake e testes

Nenhum dado fake (Princípio IX). Nenhum teste automatizado (constituição); validação pelo
[quickstart.md](quickstart.md).

## Riscos

- O `ClientDrawer` (spec 019) aceita `acoes` vazio sem mudança: "Ver cliente" o abre com `acoes={() => []}`
  e sem as permissões de alterar (só consulta).
- O histórico do bônus não lista as apostas que somaram no rollover (só o valor apostado); se o
  responsável quiser a lista, é uma consulta a mais em `apostas_rollovers` / `cassinos_rodadas_rollovers`.
- O histórico mostra o valor só no recebimento e no estorno; a data da conversão, da expiração e da
  exclusão aparece, mas o valor delas não é ligado ao bônus (as transações referenciam a promoção, não
  o crédito).
