# Research: Painel — Clientes

**Spec**: [spec.md](spec.md) · **Data**: 2026-10-22

## R-01 — O que existe hoje e como o antigo funcionava (Princípio VII)

- **No v2** (sem tela no painel), e continua valendo salvo o que este plano altera:
  - gestão de clientes (spec 002): `ClientesController` (`index`, `excluidos`, `show`, `update`,
    `alterar_situacao`, `destroy`, `restaurar`), `ClientesTransacoesController` (extrato e ajuste
    manual por `SaldoClientes`), `ClientesConfiguracoesController`, `ClientesMeiosPagamentoController`,
    `ClientesResource` (máscara sem `clientes.ver_dados_completos`) e as permissões `clientes.*` de
    `Funcao::PERMISSOES_CLIENTES` (excluir e restaurar restritas a Admin e Supervisor);
  - pagamentos (spec 007): `SaquesController` (`index` por situação, `pagar` manual ou Mercado Pago,
    `recusar`) → `PagamentoSaques` (trava `lockForUpdate`, "Este saque já foi decidido."),
    `SaquesResource`, `AcessosAssistidosController` → `AcessoAssistido`, permissões
    `clientes.acessar_conta` e `saques.gerenciar` (Admin e Supervisor);
  - pagamento de prêmios com janela de segurança (spec 009): `LiquidacaoApostas::liquidar` (marca
    `apostas.liquidacao_pendente` enquanto `apurada_em + configuracoes.minutos_pagamento_premio` não
    passou) e o comando `apostas:liquidar_premios`; encerramentos (spec 010):
    `LiquidacaoEncerramentos::liquidar` credita os `apostas_encerramentos` `Registrado` com
    `creditar_em <= agora`;
  - notificações push (spec 010): `NotificacoesPush` (`registrar` com chave única por cliente,
    `avisar` → job `EnviarNotificacaoPush`, `enviar` só para inscrições com a coluna de preferência
    do tipo ligada), `ClientesInscricoesNotificacoes` (`receber_*`), `ClientesNotificacoes`,
    `TipoNotificacao` e o modal do site `NotificationSettingsModal`;
  - afiliados (spec 012): `afiliados.usuarios_id` = gerente responsável **opcional**; o Gerente só vê
    os afiliados dele (`GarantirAlcanceAfiliado`), Admin e Supervisor veem todos; `regra_gerente`
    (`Concerns/DadosAfiliado`) só confere a função Gerente;
  - Caixa clientes (spec 017, `CaixaClientes`, `CaixaOnline`, `RelatorioCassino`): banca toda, só
    Admin e Supervisor; a senha que confirma operações de dinheiro (`Concerns/ConfirmacaoCaixa::
    regra_senha`, "Senha incorreta."); `PlanilhaPainel` (specs 017 e 018);
  - Apostas (spec 018, `ListagemApostas`, `AlcanceApostas`): apostas do site para Admin e Supervisor
    (banca toda); cancelar/editar aposta de cliente por qualquer Gerente para cima.
- **Sistema antigo consultado** (`wssports.bet`, `main` `408cf730`, somente leitura):
  - `app/Http/Controllers/CreditosController.php`: `index` (abas cadastros, transações, saques,
    lixeira, filtro e ranking), `update` (adicionar créditos com senha), `subirCreditos`, `movimento`,
    `alterarSaque`, `getEmailTelefoneCreditos`;
  - `app/Http/Controllers/CadastroController.php@destroy` (excluir/restaurar, apagando apostas e
    transações e devolvendo o limite de créditos do vendedor);
  - `routes/api.php` (`creditos`, `alterar-saque`, `subir-creditos`, `get-email-telefone-creditos`,
    `cadastros-movimento`, `notificacao-push`, `get-inscritos`);
  - telas `resources/js/screens/adm/credits/index.js` e `styles.js`, `screens/adm/modals/filter`,
    `screens/adm/modals/push-notification`, `screens/adm/components/bar`, `screens/credits`.
- **Como funcionava** e **problemas encontrados**: resumidos na spec ("Referências do sistema
  antigo").

## R-02 — Alcance por rede via afiliado (`AlcanceClientes`)

- **Decision**: um service novo `AlcanceClientes` concentra a regra do FR-003:
  - `ids_gerentes(Usuarios $usuario): ?array` — `null` para o Admin (todos); o próprio id para o
    Gerente; para o Supervisor, os gerentes da sub-hierarquia **com os excluídos**
    (`Usuarios::ids_sub_hierarquia_com_excluidos`, filtrado por função Gerente); `[]` para o Vendedor;
  - `subconsulta_ids(Usuarios $usuario): ?QueryBuilder` — `null` (sem restrição) para o Admin; senão
    `select clientes.id from clientes join afiliados on afiliados.id = clientes.afiliados_id where
    afiliados.usuarios_id in (...)`, com `withTrashed` nos dois lados (cliente excluído e afiliado
    excluído continuam na rede do gerente);
  - `aplicar(Builder $consulta, Usuarios $usuario, string $coluna = 'clientes.id')` — `whereIn` da
    subconsulta; `contem(Usuarios, Clientes|int): bool`; `garantir(Usuarios, Clientes)` →
    `abort(404, 'Cliente não encontrado.')`.
  - Um trait `Concerns/GarantirAlcanceCliente` (`garantir_cliente($request, $cliente)`) chama o
    service nos controllers, no mesmo estilo de `GarantirAlcanceAfiliado` e `GarantirAlcanceCaixa`.
- **Rationale**: uma única regra para lista, ações, saques, ranking, planilha, push, Caixa clientes e
  Apostas (SC-005); subconsulta no banco (sem carregar ids em memória) aguenta 100.000 clientes
  (SC-008); a rede segue o afiliado na hora, sem coluna nova no cliente (FR-003: afiliado que muda de
  gerente leva os clientes).
- **Alternatives considered**: coluna `usuarios_id` no cliente (duplicaria o vínculo do afiliado e
  exigiria sincronizar ao trocar o gerente); global scope no model `Clientes` (afetaria a área do
  cliente, jobs e comandos, que não têm usuário do painel); `resolveRouteBinding` com usuário
  autenticado (model dependeria da autenticação; menos explícito).

## R-03 — Onde o alcance passa a valer (FR-003a, FR-003c)

- **Decision**: aplicar `AlcanceClientes` em:
  - `ClientesController` (`listar`, `show`, `update`, `alterar_situacao`, `destroy`, `restaurar`),
    `ClientesTransacoesController`, `ClientesConfiguracoesController`,
    `ClientesMeiosPagamentoController`, `AcessosAssistidosController` (garantir o cliente da rota);
  - `SaquesController` (`index`, `resumo` novo, `pagar`, `recusar`: o saque é do cliente da rede);
  - `CotacoesPainelController::clientes` (seletor de cliente das cotações) e
    `PorcentagensClientesController` (com `clientes_id`): cliente fora da rede = não encontrado;
  - `ApostasEncerramentosController::index` (filtro `clientes_id` e a lista): só encerramentos de
    clientes da rede;
  - Caixa clientes: `CaixaClientes::resumo` e `clientes` recebem a subconsulta do alcance;
    `CaixaOnline::calcular` e `RelatorioCassino::somas_periodo` ganham um parâmetro opcional
    `?QueryBuilder $restringir_clientes` (sem ele, nada muda: Dashboard igual);
  - Apostas: `ListagemApostas::aplicar_alcance` passa a incluir as apostas do site só dos clientes da
    rede do Supervisor (`orWhereIn('apostas.clientes_id', subconsulta)`); `AlcanceApostas::alcanca`,
    para aposta de cliente, passa a exigir o cliente no alcance (Admin todos; Supervisor e Gerente, a
    rede) no lugar de "qualquer Gerente para cima".
- **Rationale**: o FR-003a pede o alcance em "qualquer consulta ou ação sobre um cliente"; o
  seletor de cotações e os encerramentos também revelam clientes. O Gerente continua sem a lista de
  apostas de clientes e sem o Caixa clientes (FR-003c), mas no cancelamento por acesso direto a
  aposta de cliente passa a valer só para os da rede dele.
- **Alternatives considered**: deixar Cotações e Encerramentos de fora (o Supervisor continuaria
  vendo clientes de fora da rede nesses pontos, contrariando o FR-003a).
- **Confirmado pelo responsável (P-1, 2026-10-22)**: a Caixa online da Dashboard (spec 014) não muda
  e continua mostrando ao Supervisor a banca toda. Também confirmados o alcance nas Cotações e nos
  Encerramentos e a regra do Gerente nas apostas de clientes (só as da rede).

## R-04 — Afiliados: gerente obrigatório e alcance do Supervisor (FR-003d)

- **Decision**:
  - `Concerns/DadosAfiliado::regra_gerente` passa a conferir também a rede: Admin escolhe qualquer
    gerente; Supervisor, só gerentes da sub-hierarquia dele ("O gerente escolhido não é da sua rede.");
  - `StoreAfiliadosRequest`: `usuarios_id` `required` para Admin e Supervisor (o Gerente continua
    sendo ele mesmo);
  - `UpdateAfiliadosRequest`: para Admin e Supervisor, `sometimes|required` (não pode virar `null`;
    "O gerente responsável é obrigatório.");
  - `AprovarAfiliadoRequest`: `required` quando o afiliado ainda não tem gerente; `CadastroAfiliados::
    aprovar` recusa aprovar sem gerente;
  - cadastro público (`AreaAfiliadoCadastroController`) não muda: fica "Em análise" sem gerente;
  - `ListagemAfiliados::consulta_gestao` ganha o filtro `sem_gerente=1`; `AfiliadosResource` ganha
    `sem_gerente: bool`;
  - `GarantirAlcanceAfiliado` passa a restringir o Supervisor aos afiliados dos gerentes da rede
    dele (Admin vê todos, inclusive os sem gerente).
- **Rationale**: com a rede definida pelo afiliado, o Supervisor que vê afiliados de outras redes
  conseguiria escolhê-los para os clientes (FR-003b) e enxergar os clientes deles pelo extrato do
  afiliado; o gerente obrigatório evita clientes sem dono.
- **Alternatives considered**: manter o Supervisor vendo todos os afiliados (incoerente com o
  FR-003b); migração que preenche gerente automaticamente nos afiliados antigos (sem regra segura de
  escolha: o Admin decide pelo filtro "Sem gerente responsável").
- **Confirmado pelo responsável (P-2, 2026-10-22)**: a restrição do Supervisor em
  `GarantirAlcanceAfiliado` entra (FR-003d).

## R-05 — Lista de Cadastros: saldo líquido e filtros novos

- **Decision**: a montagem da consulta sai de `ClientesController::listar` para um service
  `FiltrosClientes` (mesma consulta para a lista, a Lixeira, a planilha e o público de notificação),
  que passa a ter:
  - `addSelect('saldo_liquido')` por subconsultas: depósitos pagos `SUM(valor - valor_estornado)` com
    situação `Aprovado` ou `Estornado` (a mesma de `ListagemAfiliados::subconsulta_total_depositado`)
    (no estorno parcial, conta só a parte não estornada) + créditos manuais na carteira `Saldo` (`clientes_transacoes` origem `Ajuste manual`, tipo
    `Crédito`) − saques `Pago` − débitos manuais na carteira `Saldo`; resultado com `CAST(... AS
    DECIMAL(15,2))` e formatado em string com duas casas no resource (FR-006);
  - filtros novos validados no `listar`:
    - `deposito` = `ja_depositou` | `nunca_depositou` → `whereExists`/`whereNotExists` de depósito
      `Aprovado` ou `Estornado` com `pago_em` não nulo;
    - `pix_em_aberto` = `1` → o depósito mais recente do cliente (`MAX(id)`) com meio Pix e situação
      `Pendente` ou `Expirado`;
    - `modalidade` = `esportes` | `cassino` + `periodo_de`/`periodo_ate` (obrigatórios com
      modalidade ou aniversariantes; até 366 dias) → `whereExists` de aposta `Ativa` do cliente no
      período, ou de rodada de cassino do cliente no período;
    - `aniversariantes` = `1` + período → `DATE_FORMAT(data_nascimento, '%m%d')` entre `mmdd` de início
      e de fim; quando o período atravessa o ano (`fim < início`), `>= início OR <= fim`; período de
      366 dias = todos com data de nascimento;
  - ordenação: o frontend pede `ordenar_por=created_at&direcao=desc` por padrão (FR-004); a API
    mantém o padrão atual (`nome asc`) para não mudar quem já a usa;
  - tudo pelo query builder, com parâmetros (corrige o SQL concatenado do antigo); a busca continua
    agrupada num `where(fn)` (corrige o `orWhere` do antigo, FR-007).
- **Rationale**: uma consulta só para lista, total, exportação e público de notificação; os filtros
  ficam no endereço (FR-009).
- **Alternatives considered**: calcular o saldo líquido em PHP por página (não serviria para a
  planilha nem para ordenar); coluna gravada no cliente (precisaria ser mantida em todo movimento).

## R-06 — Ajuste de saldo e antecipação com senha (FR-018, FR-019)

- **Decision**:
  - `ClientesTransacoesRequest` ganha `senha` `required` com a `regra_senha` de
    `Concerns/ConfirmacaoCaixa` (o trait é reaproveitado como está, apesar do nome de Caixa: é a
    confirmação das operações de dinheiro do painel, e um segundo trait com a mesma regra seria
    duplicação; mensagem "Senha incorreta.");
    a validação acontece antes de qualquer gravação;
  - antecipação: `AntecipacaoPendentes` (service novo):
    - `pendentes(Clientes): array` — apostas do cliente com `liquidacao_pendente = true` e
      `LiquidacaoApostas::pendente()` com diferença positiva, e encerramentos `Registrado` com
      `creditar_em > agora`; quantidade e valor de cada grupo;
    - `antecipar(Clientes, Usuarios $autor): array` — numa transação por aposta/encerramento (mesma
      ordem de trava: aposta → encerramento), chama `LiquidacaoApostas::liquidar($aposta,
      OrigemApuracao::Antecipação, ignorar_janela: true, autor: $autor)` e
      `LiquidacaoEncerramentos::creditar_encerramento($id, autor: $autor)` (método público novo,
      extraído do `creditar` privado, sem mudar a regra); depois grava `clientes_antecipacoes`;
    - "Nenhum prêmio ou encerramento pendente." (422) quando os dois grupos estão vazios;
  - `LiquidacaoApostas::liquidar` ganha os parâmetros opcionais `bool $ignorar_janela = false` e
    `?Usuarios $autor = null` (repassado ao `SaldoClientes::creditar` para o extrato mostrar quem
    antecipou); sem eles, o comportamento é o atual;
  - `OrigemApuracao` ganha o caso `Antecipação = 'Antecipação de pendentes'`.
- **Rationale**: reaproveita as regras de pagamento (crédito só da diferença, nada pago duas vezes:
  a trava da aposta e o `valor_pago` garantem, mesmo com a rotina da janela rodando ao mesmo tempo,
  SC-004); a senha é a mesma das operações de dinheiro do Caixa.
- **Alternatives considered**: zerar `apurada_em` para a rotina pagar (perderia o registro de quem
  antecipou e o retorno imediato); copiar a lógica de crédito (duas regras de pagamento).

## R-07 — Saques: filtros, cartões e máscara

- **Decision**: a consulta da fila sai do `SaquesController` para o service novo `ListagemSaques`
  (`consulta(Usuarios, array $filtros)` e `resumo(...)`), usado pela lista, pelos cartões e pela
  planilha. `SaquesController::index` ganha `busca` (nome por trecho ou ID do cliente) e
  `de`/`ate` (data do pedido, Brasília, até 366 dias) além de `situacao`, e o alcance (R-03);
  `GET /saques/resumo` (novo) devolve `pendentes` e `processando` (quantidade e valor, sem período)
  e, com período, `pagos` (por `pago_em`). `SaquesResource` passa a mascarar o telefone sem
  `clientes.ver_dados_completos` (`ClientesResource::mascarar_telefone`) e a trazer `cliente.saldo`
  e `modo` já existente. Pagar e recusar não mudam (FR-026).
- **Rationale**: FR-022 a FR-026; a fila já existe e só ganha filtro, alcance e cartões.
- **Alternatives considered**: totais calculados na página do frontend (só da página; errado).

## R-08 — Ranking (FR-027, FR-028)

- **Decision**: `RankingClientes` (service novo), sempre com o alcance:
  - período: `24h` = agora − 24 h até agora; `7d`, `30d` (padrão), `90d` = agora − N dias até agora;
    `personalizado` = `de`/`ate` em dias de Brasília (até 366 dias);
  - **Depósitos**: `GROUP BY clientes_id` em `depositos` pagos (`Aprovado`/`Estornado`, `pago_em`
    no período, `valor - valor_estornado`), `COUNT` e `SUM`, ordem pelo valor, paginado no banco;
  - **Saques pagos**: o mesmo em `saques` `Pago` por `pago_em`;
  - **Valor apostado**: soma das entradas de esportes com saldo real (as de `CaixaOnline::por_cliente`,
    apostas `Ativa` pagas com `Saldo` no período) + entradas do cassino com saldo real
    (`RelatorioCassino::somas_periodo(..., Carteira::Saldo)`) — feito em SQL por `UNION ALL` das duas
    fontes agrupadas por cliente, ordenado no banco;
  - **Resultado para a banca**: reaproveita `CaixaClientes::clientes(..., ordenar: 'resultado',
    direcao: 'desc')`, que já calcula o resultado do site por cliente (esportes − encerramentos +
    cassino − comissão dos provedores − bônus convertidos) e ordena (FR-028 "mesmas regras do Caixa
    clientes"), com a subconsulta do alcance;
  - a posição = `(página − 1) × por_página + índice + 1`; as colunas comuns (saldo, saldo líquido,
    ativo) vêm de uma consulta dos clientes da página.
- **Rationale**: três critérios ordenáveis no banco; o resultado reaproveita o cálculo do Caixa para
  bater com ele (SC-002).
- **Alternatives considered**: ordenar todos em memória (lento com muitos clientes); uma tabela de
  ranking pré-calculada (dados desatualizados e mais uma rotina).

## R-09 — Exportação (FR-030)

- **Decision**: `ExportacaoClientes` (service novo) usa `PlanilhaPainel::gerar` com as colunas de
  cada aba (Cadastros, Saques, Ranking, Lixeira), cabeçalho com a data de geração e os filtros, linhas
  por `lazyById`/`chunkById` (500) da mesma consulta da tela, contato e CPF mascarados pela mesma
  regra do resource; conta antes e recusa acima de 50.000 com 422 e a mensagem do FR-030; arquivo
  temporário por pedido, apagado depois do download (`deleteFileAfterSend`), como na spec 018. A tela
  lê o total já mostrado e avisa antes de pedir.
- **Rationale**: mesmo padrão e mesma biblioteca das specs 017 e 018 (sem dependência nova).
- **Alternatives considered**: CSV (sem formato de moeda); fila assíncrona (desnecessária até
  50.000 linhas).

## R-10 — Notificação push manual (FR-031 a FR-033a)

- **Decision**:
  - `TipoNotificacao::AvisoBanca = 'Aviso da banca'`, `coluna_preferencia()` → `receber_avisos_banca`
    (coluna nova em `clientes_inscricoes_notificacoes`, padrão `true`); `NotificacoesPush::conteudo`
    usa a tag `aviso-{envio}` e a URL `/`;
  - tabela nova `clientes_notificacoes_envios` (autor, público, ids, dias, título, mensagem,
    alcançados, entregues, falhas); coluna `clientes_notificacoes_envios_id` (nulo) em
    `clientes_notificacoes` para ligar cada aviso ao envio;
  - `NotificacoesManuais` (service novo): `publico(Usuarios, array $publico): Builder` (clientes
    ativos, não excluídos, no alcance, com inscrição com `receber_avisos_banca = true`; "Por ID" com
    `whereIn` dos ids escolhidos; "Sem interagir há N dias" = sem aposta, rodada de cassino nem
    depósito pago depois de agora − N dias, incluindo quem nunca apostou nem depositou); `contar`; `restantes_hoje()`; `enviar(Usuarios,
    dados)`: numa transação com `lockForUpdate` na linha de `configuracoes`, confere o limite do dia
    (envios com `alcancados > 0` desde o início do dia em Brasília), grava o envio e dispara o job
    `EnviarNotificacoesManuais` (fila das notificações), que registra um `ClientesNotificacoes` por
    cliente (chave `aviso-{envio_id}`) em blocos de 500 e chama `NotificacoesPush::enviar` para cada,
    somando `entregues` e `falhas` no envio;
  - limite: coluna nova `configuracoes.limite_notificacoes_manuais_dia` (padrão 3, de 1 a 50), lida e
    alterada pelo Admin no próprio modal (`PUT /clientes/notificacoes/limite`, só Admin);
  - site: `NotificationSettingsModal` ganha o item "Avisos da banca"; a lista de preferências de
    `InscricoesNotificacoes` e o `InscricaoNotificacaoResource` passam a ter `receber_avisos_banca`.
- **Rationale**: reaproveita o Web Push e as inscrições da spec 010 (sem serviço externo); o cliente
  continua podendo desligar; o limite com trava evita dois envios passando juntos (caso de borda).
- **Alternatives considered**: tópico único para todos (o Web Push não tem tópicos); enviar na
  própria requisição (lento para milhares de inscrições); limite gravado só no `.env` (o Admin não
  conseguiria mudar).

## R-11 — Filtro por cliente na tela Apostas (FR-016)

- **Decision**: `ApostasFiltrosRequest` ganha `clientes_id` (inteiro, opcional);
  `ListagemApostas::consulta` filtra `apostas.clientes_id` depois de conferir o cliente no alcance
  (`AlcanceClientes::contem`, senão 403 como o filtro de vendedor); só Admin e Supervisor (para os
  demais, o parâmetro é ignorado, como o `usuarios_id` para o Vendedor). A página `PanelBets` lê
  `clientes_id` e `cliente_nome` do endereço e mostra a etiqueta removível "Cliente: Nome" no
  `ActiveFilters`.
- **Rationale**: o atalho "Ver apostas" do detalhe abre a lista já filtrada (FR-016).
- **Alternatives considered**: buscar pelo nome do cliente (pegaria homônimos).

## R-12 — Permissões (FR-034 a FR-036)

- **Decision**: em `Funcao.php`, `clientes.ver_ranking`, `clientes.exportar`,
  `clientes.enviar_notificacao` e `clientes.antecipar_pendentes` entram em `PERMISSOES_CLIENTES`; as
  três últimas também em `PERMISSOES_CLIENTES_RESTRITAS`. O padrão por função segue a regra atual
  (Admin e Supervisor todas; o Gerente as não restritas → recebe `clientes.ver_ranking`; o Vendedor
  nenhuma). Migration de dados `criar_permissoes_clientes_painel` cria as permissões no spatie e as
  dá aos usuários atuais pela função (como a da spec 018). `resources/js/utils/permissions.js` ganha
  nomes e descrições. A aba Ranking usa `clientes.ver_ranking`; Lixeira, `clientes.restaurar`;
  Saques, `saques.gerenciar`.
- **Rationale**: mesmo padrão das specs 017 e 018 (Princípio VI).
- **Alternatives considered**: permissão única "clientes.gestao_avancada" (sem granularidade).

## R-13 — Frontend da tela

- **Decision**: página Inertia `PanelClients` em `/painel/clientes` (`PainelController::clientes`),
  item "Clientes" no `PanelShell` (visível com `clientes.listar`), hook `useClientesPainel` (estado no
  endereço: aba, busca, filtros, ordenação, página; requisições por aba; recarregar sem perder
  posição). Reaproveita `PanelShell`, `PanelTabs`, `PeriodFilter`, `StatCard`, `ActionsMenu`,
  `ActiveFilters`, `Pagination`, `ModalHeader`, `ConfirmModal`, `EmptyMessage`, `Spinner`,
  `useCampoValor`, `useTelaMobile`, `StatementTable`/`StatementFilters` (extrato) e
  `BettingRulesFields` (esportes das configurações). Componentes novos: `ClientsTable`,
  `ClientsFilters`, `ClientDrawer`, `ClientFormModal`, `ClientSettingsForm`, `PaymentMethodsList`,
  `BalanceAdjustModal`, `AnticipateModal`, `WithdrawalsTable`, `WithdrawalDecisionModal`,
  `RankingTable`, `TrashTable`, `RestoreConflictModal`, `PushNotificationModal`, `AffiliatePicker`,
  `ClientPicker`.
- **Rationale**: padrão das specs 014 a 018; detalhe em gaveta (FR-011) para não perder a lista.
- **Alternatives considered**: páginas separadas por aba (perde o estado ao trocar).

## R-14 — Desempenho (SC-008)

- **Decision**: índices novos `depositos(clientes_id, situacao, pago_em)`, `saques(clientes_id,
  situacao, pago_em)`, `clientes_transacoes(clientes_id, origem, carteira)` (saldo líquido) e
  `clientes(afiliados_id)` se ainda não existir (alcance); conferir na migration com
  `Schema::hasIndex`. Paginação no banco (padrão 20, máximo 100); `withCount`/subconsultas só para a
  página (o saldo líquido é subselect por linha da página).
- **Rationale**: a lista e o ranking filtram e somam por cliente; sem índices, 100.000 clientes
  passariam de 2 s.
- **Alternatives considered**: cache curto das listas (dados de dinheiro precisam estar atuais).

## R-15 — Edição do afiliado do cliente (FR-003b)

- **Decision**: `UpdateClientesRequest` (`afiliados_id`): para Supervisor e Gerente, `required` e o
  afiliado deve estar na rede (`afiliados.usuarios_id` em `AlcanceClientes::ids_gerentes`), senão
  "Escolha um afiliado da sua rede."; o Admin pode `null` ou qualquer afiliado. A tela usa
  `AffiliatePicker` (busca na API `GET /afiliados`, já com o alcance).
- **Rationale**: impede que um gestor tire o cliente da própria rede ou o mande para outra.
- **Alternatives considered**: proibir a troca de afiliado para não Admin (mais restrito do que o
  pedido).

## R-16 — Dados fake e testes

- Massa para conferir o SC-008: gerar clientes pelo `Database\Factories\ClientesFactory` no Tinker
  (`Clientes::factory()->count(100000)->create()`), só no ambiente local, nunca em produção.

- Nenhum dado fake (Princípio IX). Sem testes automatizados; validação pelo
  [quickstart.md](quickstart.md).
