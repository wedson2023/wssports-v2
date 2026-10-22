# Contrato das páginas: Painel — Clientes

Página Inertia `PanelClients` em `/painel/clientes`, dentro do `PanelShell` (título "Clientes"). Visual
do painel (specs 014 a 018): cores do tema, `PanelTabs`, `StatCard`, `ActionsMenu` (três pontinhos),
`ActiveFilters`, `Pagination`, modais com `ModalHeader`, campos com rótulo em cima, `useCampoValor`
nos valores. Celular até 900 px: linhas viram cartões, filtros e detalhe em tela cheia, sem rolagem
horizontal, alvos de toque ≥ 40 px.

## 1. Cabeçalho e abas

- Menu do painel: item **Clientes** (ícone `credit_card`), entre Caixa e Apostas, visível com
  `clientes.listar`.
- Barra superior: título "Clientes — total N" (total da aba e do filtro), busca (atraso de 400 ms),
  botão **Filtros** (`filter_alt`, com contador), **Baixar planilha** (`file_download`, com
  `clientes.exportar`), **Notificação** (`notifications`, com `clientes.enviar_notificacao`) e
  **Atualizar**.
- Abas (`PanelTabs`): **Cadastros** (`receipt`), **Saques** (`download`, com `saques.gerenciar`),
  **Ranking** (`filter_list`, com `clientes.ver_ranking`), **Lixeira** (`delete_outline`, com
  `clientes.restaurar`). Aba sem permissão não aparece.
- Estado no endereço: `aba`, `busca`, filtros, `ordenar_por`, `direcao`, `page` (FR-009).

## 2. Cadastros (`ClientsTable`)

- Colunas: ID · NOME · CONTATO (telefone com DDI e e-mail, uma linha cada) · SALDO · PROMOÇÃO
  (esportes / cassino) · SALDO LÍQUIDO (verde > 0, cinza = 0, vermelho < 0) · ATIVO (Sim verde / Não
  cinza) · CRIADO EM (dd/mm/aaaa hh:mm) · AÇÕES.
- Ordenação pelos títulos ID/CRIADO EM, NOME e SALDO; padrão `created_at desc`.
- Tocar na linha abre o detalhe. Menu de ações (conforme permissão): Ver detalhe (`visibility`),
  Editar (`edit`), Ativar/Desativar (`toggle_on`/`toggle_off`), Ajustar saldo (`attach_money`),
  Antecipar pendentes (`arrow_circle_up`), Acessar conta (`launch`), Ver apostas (`receipt_long`),
  Excluir (`delete`, vermelho).
- Celular: cartão com nome e ID, saldo e saldo líquido em destaque, ativo; o restante ao expandir.
- Vazio: "Nenhum cliente encontrado. Troque a busca ou os filtros."

## 3. Filtros (`ClientsFilters`)

Painel lateral (tela cheia no celular), com **Aplicar** e **Limpar filtros**:

- Situação (Todos / Ativos / Inativos), Gênero, DDI, Afiliado (`AffiliatePicker`, só da rede),
  Cadastro de/até, Idade mínima/máxima, Saldo mínimo/máximo (campo de reais), Com saldo promocional,
  Saque bloqueado, Com CPF, Com e-mail (chips Sim/Não);
- **Depósito**: Já depositou / Nunca depositou; **Gerou Pix e não pagou** (chip);
- **Período do filtro** (de/até): habilita **Modalidade** (Esportes / Cassino) e **Aniversariantes**;
  sem período, os dois ficam desabilitados com a dica "Escolha o período".
- Filtros ativos como etiquetas removíveis (`ActiveFilters`).

## 4. Detalhe do cliente (`ClientDrawer`)

- Gaveta à direita (`min(560px, 100vw)`), tela cheia no celular; `ModalHeader` com ícone `person`,
  o nome e "Dados, configurações, pagamentos e extrato do cliente."; abas internas **Dados**,
  **Configurações**, **Pagamentos**, **Extrato**.
- **Dados**: nome, telefone, e-mail, CPF (mascarados conforme permissão), nascimento, gênero,
  afiliado, situação, saldos (real, promoção esportes, promoção cassino), saldo líquido, criado e
  alterado em; botões **Editar** (`ClientFormModal`), **Ajustar saldo**, **Antecipar pendentes**,
  **Acessar conta**, **Ver apostas** (abre `/painel/apostas?clientes_id={id}&cliente={nome}&de={1º
  dia do mês}&ate={hoje}`).
- **Configurações** (`ClientSettingsForm`): os campos de `clientes_configuracoes` (spec 002), com os
  esportes permitidos pelo `BettingRulesFields`; **Salvar** com `clientes.editar_configuracoes`.
- **Pagamentos** (`PaymentMethodsList`): chaves Pix com adicionar, editar e excluir.
- **Extrato**: `StatementFilters` + `StatementTable` (data, tipo, origem, carteira, valor, saldo
  depois, autor, observação), paginado.

## 5. Modais de ação

- **Editar** (`ClientFormModal`, `edit`, "Altere os dados do cliente."): nome, DDI e telefone,
  e-mail, CPF, nascimento, gênero, afiliado (`AffiliatePicker`; obrigatório para Supervisor e
  Gerente), nova senha (opcional).
- **Ajustar saldo** (`BalanceAdjustModal`, `attach_money`, "Crédito ou débito manual no saldo do
  cliente."): carteira (chips Saldo / Promoção esportes / Promoção cassino), tipo (Crédito /
  Débito), valor, motivo (até 255), **sua senha**; mostra o saldo atual da carteira e o saldo depois.
- **Antecipar pendentes** (`AnticipateModal`, `arrow_circle_up`, "Libera agora os prêmios e
  encerramentos que estão na janela de segurança."): carrega `pendentes`; lista "Prêmios: N · R$" e
  "Encerramentos: N · R$", o aviso do antigo ("Ao antecipar, o tempo de segurança para análise das
  apostas é dispensado e o valor vai para o saldo do cliente agora.") e **sua senha**; sem
  pendentes, mostra "Nenhum prêmio ou encerramento pendente." e só **Fechar**. Sucesso: "R$ X
  creditados. Saldo atual: R$ Y."
- **Ativar/Desativar, Acessar conta, Excluir**: `ConfirmModal` com a consequência ("As sessões do
  cliente deixam de valer na hora.").

## 6. Saques (`WithdrawalsTable`)

- Chips de situação: **Pendentes** (padrão) · Processando · Pagos · Recusados; período (`PeriodFilter`,
  vazio por padrão em Pendentes); busca por nome ou ID.
- Cartões (`StatCard`): Pendentes (quantidade e valor), Processando (quantidade e valor) e, com
  período, Pagos no período.
- Colunas: CLIENTE (ID e nome) · PIX (tipo e chave; nome do titular embaixo) · VALOR · SALDO DO
  CLIENTE · SITUAÇÃO (selo colorido) · PEDIDO EM · DECISÃO (quem, quando, Manual/Mercado Pago, motivo).
- Ações só em Pendente: **Dar baixa (manual)** (`check_circle`), **Pagar pelo Mercado Pago**
  (`account_balance`, só com o gateway vinculado), **Recusar** (`block`, `WithdrawalDecisionModal`
  com motivo obrigatório até 255 e o aviso "O valor volta para o saldo do cliente.").

## 7. Ranking (`RankingTable`)

- Período: chips 24 horas · 7 dias · **30 dias** · 90 dias · Personalizado (de/até); critério: chips
  **Depósitos** · Valor apostado · Resultado para a banca · Saques pagos.
- Colunas comuns: POSIÇÃO (1º, 2º, 3º com destaque) · ID · NOME · SALDO · SALDO LÍQUIDO · ATIVO; mais
  as do critério: QTD. DEPÓSITOS / VALOR · ESPORTES / CASSINO / TOTAL · RESULTADO (verde ganho da
  banca, vermelho perda) · QTD. SAQUES / VALOR.
- Tocar na linha abre o detalhe.

## 8. Lixeira (`TrashTable`)

- Colunas de Cadastros + EXCLUÍDO EM; só a ação **Restaurar** (`restore`, verde) com confirmação.
- Conflito de dados únicos (422 com `errors`): `RestoreConflictModal` (`warning`, "Alguns dados já
  estão em uso por outro cliente.") com os campos em conflito para preencher e **Restaurar**.

## 9. Notificação (`PushNotificationModal`)

- `ModalHeader` `notifications`, "Enviar notificação", "Aviso no celular dos clientes inscritos.";
  linha "N envios restantes hoje" e, para o Admin, "Limite por dia: [ 3 ] Salvar".
- Público (chips): **Todos os inscritos** · Por ID (`ClientPicker`, busca com resultados, até 500) ·
  Sem interagir há [N] dias; título (contador 0/50); mensagem (contador 0/150); prévia da notificação.
- "Alcançados: N" atualizado ao mudar o público; **Enviar** desabilitado com zero alcançados ou sem
  envios restantes ("Limite de envios de hoje atingido."); confirmação "Enviar para N clientes?";
  sucesso: "Notificação enviada para N clientes."

## 10. Planilha

- **Baixar planilha** com carregando; se o total da aba passar de 50.000, a tela avisa antes ("O
  filtro tem N registros; o máximo por planilha é 50.000. Use filtros para reduzir.") e não baixa.

## 11. Alterações em outras telas

- **Apostas (`PanelBets`)**: lê `clientes_id` e `cliente` do endereço e mostra a etiqueta
  "Cliente: Nome" removível (R-11).
- **Site (`NotificationSettingsModal`)**: item novo "Avisos da banca — Novidades e promoções enviadas
  pela banca" (`receber_avisos_banca`).

## 12. Telas do sistema antigo usadas como referência (Princípio VIII)

`screens/adm/credits` (barra com download, filtro e sino; abas cadastros, saques, transações,
ranking, lixeira; colunas e ícones `delete`, `refresh`, `arrow_circle_up`, `attach_money`,
`launch`, `more_vert`; cores do saldo líquido), `screens/adm/modals/filter` ("Filtrar cadastros"),
`screens/adm/modals/push-notification` ("Envio de notificações push - N restantes", público Todos /
Por ID / Por tempo, "Alcançados"), `screens/adm/components/bar` e `screens/credits` (acesso à conta).
