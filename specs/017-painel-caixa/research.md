# Research: Painel — Caixa

**Spec**: [spec.md](spec.md) · **Data**: 2026-10-21

## R-01 — O que existe hoje e como o antigo funcionava (Princípio VII)

- **No v2**: não há backend de caixa. Existem só as somas do dia da Dashboard (spec 014):
  `ApuradoDashboard` (apostas do balcão por gerente, comissão do gerente, depósitos e saques para
  Admin/Supervisor) e `CaixaOnline` (apostas online com saldo real, encerramentos, depósitos e
  saques). Lançamentos, retiradas e o percentual da comissão do gerente vêm de
  `DadosFake::caixa_usuario()` e `DadosFake::percentual_comissao_gerente()`, zerados, e o frontend
  mostra "em breve" pela lista `provisorios` da resposta.
- **Sistema antigo consultado** (`wssports.bet`, somente leitura):
  - `app/Http/Controllers/CaixaController.php` (4.228 linhas): `caixaAdmin`, `caixaGerentes`,
    `caixaVendedores`, `getVendedores`, `caixaClientes`, `caixaBilhetes`, `lancamentos`,
    `prestarContas`, `deletarLancamentos`, `getLancamentos`, `excelAdmin`, `excelGerente`,
    `ExcelVendedor`, `graficos`; rotas em `routes/api.php` (linhas 167–182).
  - Models `Lancamentos` (tipos `Crédito`, `Débito`, `Prestação de Contas`), `TravasSupervisor` e
    `TravasGerentes` (`ver_caixa`, `lancamentos_caixa`, `prestar_conta_caixa`, `comissao`).
  - Telas `resources/js/screens/adm/financial/{adm,manager,seller,client,online}`,
    `themes/app/screens/financial` e os modais `screens/adm/modals/{transactions,graphic}`.
- **Como funcionava** (resumo; detalhes na spec, "Referências do sistema antigo"): cada tela
  repetia as mesmas três consultas (bilhetes, acumuladão e créditos) com `IFNULL(SUM(...))` e juntava
  em PHP; o saldo de caixa somava o que veio depois da última "Prestação de Contas" (ou de "1 ano
  atrás"); a prestação gravava um lançamento com o saldo e, se negativo, um crédito automático antes;
  as travas estavam comentadas no construtor; o Excel era gerado com PhpSpreadsheet em três métodos
  quase iguais.
- **Problemas corrigidos**: SQL concatenado (injeção), cálculo copiado ~15 vezes, ponto flutuante,
  permissões não aplicadas, exclusão de qualquer lançamento (inclusive prestações), prêmio de aposta
  já prestada que saía depois ficava fora de qualquer caixa, aba "créditos" com números misturados.

## R-02 — Uma regra única de cálculo (`ApuracaoCaixa`)

- **Decision**: um service `ApuracaoCaixa` com as somas das apostas do balcão por vendedor num
  intervalo e tipo (Todas / Pré-jogo / Ao vivo), numa consulta agrupada por `apostas.usuarios_id`
  (query builder, sem SQL concatenado), mais os lançamentos do intervalo agrupados por vendedor. A
  partir das linhas por vendedor, o service monta as linhas e os totais de gerente, supervisor e
  banca em PHP (bcmath), usando o mapa vendedor → gerente → supervisor da rede. A comissão do gerente
  é calculada por gerente sobre a soma do líquido dos vendedores dele no intervalo inteiro
  (Clarifications) e só depois somada para cima.
- **Mesmas regras da Dashboard**: apostas `situacao = Ativa`, `clientes_id` nulo, sem excluídas,
  pela `created_at`; Em aberto / Premiadas / Perdidas pelo `resultado`; Saídas = `premio` das
  vencedoras + `valor` das devolvidas; comissão = `apostas.comissao`. "Ao vivo" = existe palpite com
  `confrontos_ao_vivo_id` (EXISTS em `apostas_palpites`); "Pré-jogo" = não existe.
- **Reuso pela Dashboard**: `ApuradoDashboard` passa a pedir ao `ApuracaoCaixa` os lançamentos,
  retiradas e a comissão do gerente do dia (com o percentual de cada gerente) e os fakes saem de
  `DadosFake`; `provisorios` vira lista vazia, e o frontend da Dashboard tira o selo sozinho (já usa
  essa lista). As somas de apostas que a Dashboard já faz não mudam de lugar (Princípio IV): só
  as três partes fake são trocadas.
- **Rationale**: uma regra, três usos (tela, exportação e Dashboard), sem N+1 (uma consulta de
  apostas, uma de lançamentos e uma de prestações por página), conferível pelo SC-002/SC-003.
- **Alternatives considered**: uma consulta por nível (repetiria o antigo); tabela de totais por dia
  (cache materializado) — desnecessária para até 500 vendedores e cria risco de divergência.

## R-03 — Saldo de caixa como "total devido − total recebido"

- **Decision**: o saldo de caixa de um vendedor até um instante T é calculado assim:
  `Σ (valor − comissão − saída atual) das apostas ativas do balcão feitas até T`
  `+ Σ suprimentos − Σ retiradas com ocorrido_em ≤ T`
  `− Σ valor_recebido das prestações não desfeitas`.
  A "saída atual" é a da situação da aposta agora (prêmio se Vencedor, valor se Devolvido, zero nas
  demais); aposta cancelada não entra (vale zero).
- **Por que é o FR-009**: entre duas prestações, essa conta é exatamente o saldo anterior (diferença
  da última prestação) + entradas − comissão das apostas novas − saídas que aconteceram − estornos +
  suprimentos − retiradas. Ela trata sozinha os casos de borda decididos (prêmio depois da prestação,
  cancelamento depois da prestação, diferença do valor recebido, prestação desfeita) e também a
  re-apuração de uma aposta (spec 009), que o FR-009 por datas não cobria, sem depender das datas de
  decisão.
- **Resumo da prestação**: o modal mostra o valor apurado (o saldo acima em T) decomposto em: saldo
  anterior (diferença da última prestação), entradas e comissão das apostas feitas no intervalo,
  saídas das apostas apuradas no intervalo (`apurada_em`), suprimentos e retiradas do intervalo, e
  "Ajustes de apostas anteriores" (o resto: estornos e re-apurações), para que a soma das partes seja
  sempre igual ao apurado (SC-005). Mostra também as apostas em aberto (quantidade e valor).
- **Desempenho**: as somas usam o índice `apostas(usuarios_id, situacao, created_at)`; o histórico de
  um vendedor no v2 começa do zero (os lançamentos do antigo não são migrados).
- **Alternatives considered**: somar só o que aconteceu depois da última prestação, por datas
  (como o antigo; perde re-apurações); livro-razão de movimentos gravado em cada evento (exigiria
  alterar criação, cancelamento e apuração de apostas — Princípio IV).

## R-04 — Lançamentos e prestação de contas

- **Decision**: tabelas `caixa_lancamentos` e `caixa_prestacoes_contas` ([data-model.md](data-model.md)),
  no padrão de `afiliados_prestacoes_contas` (spec 012). Services `LancamentosCaixa` (lançar,
  excluir) e `PrestacaoContasCaixa` (resumo, prestar, prestar em lote, desfazer).
- **Prestar**: transação com `lockForUpdate` na linha do vendedor em `usuarios`; confere que o fim é
  posterior ao fim da última prestação não desfeita e não futuro; calcula o apurado (R-03); grava a
  prestação com `saldo_anterior`, `valor_apurado`, `valor_recebido` e `diferenca` (apurado −
  recebido); marca os lançamentos abertos até o fim com o id da prestação. Sem movimento (apurado
  zero e nenhum lançamento ou aposta no intervalo) → 422 "Nenhum movimento a prestar no período.".
- **Lote do gerente**: mesma operação para cada vendedor do gerente (ordem por id para evitar
  deadlock), numa transação só, com o mesmo `lote` (UUID); vendedores sem movimento são pulados e a
  resposta traz `pulados`. O valor recebido no lote é o apurado de cada um (o lote não aceita valor
  recebido diferente; para isso, presta-se o vendedor sozinho).
- **Desfazer (FR-022a)**: só a última prestação não desfeita do vendedor; quem fez ou um superior
  dele (`ids_hierarquia_acima` do autor contém o solicitante) com `caixa.prestar_contas` e alcance
  sobre o vendedor; senha e motivo; grava `desfeita_por` e `motivo_desfazer` e aplica o soft delete;
  os lançamentos dela voltam a `caixa_prestacoes_contas_id = null`.
- **Senha**: regra de validação no request, como em `AlterarSenhaAfiliadoRequest`
  (`Hash::check` com a senha do usuário logado); erro "Senha incorreta.".
- **Concorrência**: o bloqueio da linha do vendedor serializa prestar, desfazer e lançar; a segunda
  prestação simultânea encontra o fim já usado e recebe 409 "O caixa deste vendedor mudou; confira e
  tente de novo." (FR-022).
- **Lançamentos**: `ocorrido_em` não futuro e posterior ao fim da última prestação; exclusão só sem
  prestação; soft delete com `excluido_por`.

## R-05 — Comissão do gerente e limites

- **Decision**: coluna `percentual_comissao_gerente` (decimal 5,2, padrão 0) em
  `usuarios_configuracoes`, a tabela de configurações por usuário que já existe (registro criado com
  `firstOrCreate` para o gerente, como faz `ConfiguracoesVendedores`). Rota
  `PUT /caixa/gerentes/{usuario}/comissao` com `caixa.editar_comissao_gerente`.
- **Limites**: `POST /caixa/vendedores/{usuario}/limites` soma valores aos limites (com
  `lockForUpdate`, porque as apostas consomem os limites ao mesmo tempo, como em `CriacaoApostas`),
  exigindo `usuarios_configuracoes.editar`, a permissão que já edita essas configurações.
- **Alternatives considered**: coluna na tabela `usuarios` (mistura cadastro com regra financeira);
  tabela própria (uma linha por gerente, sem ganho); editar o limite pela rota existente de
  configurações com o valor somado no navegador (corrida com o consumo das apostas).

## R-06 — Caixa clientes (`CaixaClientes`)

- **Decision**: service novo `CaixaClientes` com o período. A parte de esportes reaproveita
  `CaixaOnline`, que passa a aceitar um intervalo (o padrão continua sendo o dia, então a Dashboard
  não muda). Partes novas, todas pelo query builder:
  - **Cassino**: as somas de `RelatorioCassino` (entradas = apostas − devoluções; saídas = prêmios;
    comissão pela porcentagem de cada provedor) só com as rodadas da carteira `Saldo` (dinheiro de
    verdade, como nas apostas esportivas, FR-035 da spec 014). O relatório do cassino soma todas as
    carteiras; por isso, no quickstart a comparação com ele é feita num período sem rodadas com saldo
    de promoção. O `RelatorioCassino` ganha um método público que devolve as somas de um intervalo,
    filtro por carteira e por cliente, sem mudar o relatório (Princípio IV).
  - **Bônus**: concedidos = `clientes_transacoes` de origem `Promoção`, tipo `Crédito`, nas carteiras
    de promoção; convertidos = origem `Conversão de bônus`, tipo `Crédito`, carteira `Saldo`.
  - **Depósitos / saques**: como `CaixaOnline` (aprovados por `pago_em`; pagos por `pago_em`).
  - **Resultado do site** = saldo líquido de esportes + resultado do cassino − comissão dos provedores
    − bônus convertidos.
  - **Lista por cliente**: só clientes com movimento no período (UNION dos ids de apostas, rodadas,
    transações de bônus, depósitos e saques do período), paginada no banco, e as somas só para os
    ids da página.
- **Fora desta spec**: cashback e bônus de indicação de clientes (origens `Cashback` e de indicação)
  não entram no resultado; ficam registrados aqui para uma spec futura, se o responsável quiser.
- **Rationale**: mesmas regras das telas que já existem (Dashboard e relatório do cassino), sem uma
  segunda definição de "entrada" ou de "comissão do provedor".

## R-07 — Período, fuso e paginação

- **Decision**: a API recebe `de` e `ate` como datas (`AAAA-MM-DD`) e converte para o início e o fim
  dos dias em Brasília com `FusoSistema` (como a Dashboard); `ate` ≥ `de`, até 366 dias, não futuro.
  Os atalhos (Hoje, Ontem, Esta semana — de segunda a hoje —, Este mês) são calculados no navegador
  (`utils/periods.js`) e viram `de`/`ate`. Listas: padrão 20, máximo 100.
- **Ordenação**: `ordenar=nome|entradas|liquido|saldo_caixa` e `direcao=asc|desc`; nome e entradas no
  banco; líquido e saldo de caixa ordenados depois das somas, sobre todos os usuários do nível
  (no máximo algumas centenas por nível), e só então paginados.

## R-08 — Permissões

- **Decision**: constante `Funcao::PERMISSOES_CAIXA` com `caixa.ver`, `caixa.ver_clientes`,
  `caixa.lancar`, `caixa.excluir_lancamento`, `caixa.prestar_contas`, `caixa.exportar` e
  `caixa.editar_comissao_gerente`; `pode_usar`: Admin e Supervisor todas; Gerente todas menos
  `caixa.ver_clientes` e `caixa.editar_comissao_gerente`; Vendedor só `caixa.ver`. Entram em
  `permissoes_padrao`, em `utils/permissions.js` (nomes e descrições na tela de permissões, spec 015)
  e no `PapeisPermissoesSeeder`.
- **Usuários já cadastrados (FR-033)**: migration de dados (como
  `2026_10_21_000001_dividir_permissoes_usuarios_por_funcao`, porque o deploy contínuo roda
  migrations e não seeders) que cria as permissões e dá a cada usuário as que a função dele recebe
  por padrão.
- **Alcance**: Admin tudo; demais pela sub-hierarquia (`Usuarios::gerencia`, com os excluídos para o
  histórico, como `AlcanceDashboard`); o Vendedor só ele. Toda rota com `{usuario}` confere a função
  esperada (vendedor ou gerente) e o alcance; `caixa.ver_clientes` só para Admin e Supervisor também
  no servidor.

## R-09 — Exportação em planilha

- **Decision**: adicionar `phpoffice/phpspreadsheet` (autorizada pelo responsável; a mesma do antigo).
  Service `ExportacaoCaixa` monta uma planilha com o cabeçalho (sistema, período, tipo, nível) e os
  totais, e uma linha por usuário (rede) ou por cliente (Clientes), com as mesmas somas do
  `ApuracaoCaixa`/`CaixaClientes` e formato de moeda; até 5.000 linhas por arquivo (paginado
  internamente de 100 em 100). A rota devolve o arquivo (`Content-Disposition: attachment`) e o
  frontend baixa com o token (`responseType: 'blob'`).
- **Requisito do servidor**: as extensões PHP `zip`, `xml`, `xmlwriter` e `gd` (exigidas pela
  biblioteca) DEVEM estar no servidor do deploy contínuo (spec 013); conferir no quickstart.
- **Alternatives considered**: CSV (sem formato de moeda e com problema de acentos no Excel);
  biblioteca de streaming (desnecessária no volume do caixa).

## R-10 — Frontend

- **Decision**: página `PanelCash` (`/painel/caixa`, Inertia, `PanelShell`) com:
  - `PeriodFilter` (atalhos e período personalizado), abas do painel (`PanelTabs`) para Rede e
    Clientes e para Todas / Pré-jogo / Ao vivo;
  - `CashBreadcrumb` (trilha; no celular, "‹ Voltar" com o nível atual);
  - cartões com `StatCard` (já aceita valor negativo e destaque) num grid de 2 colunas no celular;
  - `CashTable` (tabela no desktop e cartões expansíveis no celular, colunas por configuração, menu
    `ActionsMenu`), usada pela rede e pelos clientes;
  - modais `CashEntryModal`, `SettlementModal` (vendedor e lote), `UndoSettlementModal`,
    `CashTransactionsModal`, `SellerLimitsModal`, `ManagerCommissionModal` e `BetsByPicksModal`
    (Chart.js por `ChartCanvas`), todos com `ModalHeader` e campos com rótulo em cima; valores com
    o campo de reais do painel (`useCampoValor`); senha num campo próprio dentro do modal (sem
    SweetAlert com HTML).
  - hooks `useCaixa` (resumo e lista com os filtros, recarga depois de cada ação) e
    `useTransacoesCaixa`; util `periods.js`.
- **Menu**: item "Caixa" (ícone `point_of_sale`) no `PanelShell`, visível com `caixa.ver` ou
  `caixa.ver_clientes`.
- **Estado na URL**: aba, tipo, período e foco (`?aba=&tipo=&de=&ate=&usuarios_id=`) para o voltar do
  navegador e para compartilhar o link, como a aba de Cotações (spec 016).
