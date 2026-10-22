# Feature Specification: Painel — Apostas (lista de apostas, bilhete, cancelamento, premiados e exportação)

**Feature Branch**: `018-painel-apostas`

**Created**: 2026-10-22

**Status**: Draft

**Input**: User description: "Spec 018: tela 'Apostas' no painel (backend + frontend). O backend de
listagem NÃO existe: hoje o v2 tem criar, validar e cancelar aposta, cancelar e restaurar palpite, a
página do bilhete em tempo real (spec 010) e a exportação em planilha (spec 017), mas não tem a
lista de apostas do painel. Trazer do antigo (wssports.bet na main 408cf730: BilhetesController@index,
excelApostas, premiados, destroy, ConfrontosController@destroy e a tela screens/adm/bets 'Lista de
apostas' /adm/lista-de-apostas, com o modal modals/ticket) a lista de apostas por nível com período
de/até, busca por código ou nome do cliente, filtro por resultado e por prêmio mínimo, filtro por
vendedor, filtro por forma de pagamento (Dinheiro, Saldo, Promoção esportes) no lugar da aba
'créditos', abas Todas / Ao vivo / Canceladas; colunas código, vendedor, cliente, valor, comissão,
prêmio (com o valor acrescido do ganho múltiplo de palpites), situação, jogos restantes/total e
horário; totais do filtro (entradas, quantidade, comissão e saídas); ver o bilhete com cancelar e
restaurar palpite pelas regras atuais; cancelar aposta pelas regras atuais; compartilhar o link do
bilhete e acompanhar em tempo real; imprimir 2ª via; imagem dos premiados do período para
compartilhar; exportação em Excel. Ligar o atalho 'ver apostas do vendedor' do Caixa (deixado de fora
na spec 017). Corrigir os problemas do antigo. Créditos e acumuladão não entram. Tela 'Apostas' no
painel no padrão das specs 014, 015, 016 e 017, responsiva com prioridade ao celular."

## Clarifications

### Session 2026-10-22 (decisões tomadas antes do `/speckit-specify`)

- Q: Qual versão do sistema antigo é a referência? → A: A `main` atual do `wssports.bet`
  (`408cf730`, 28/09/2026). A primeira leitura foi feita num commit de 2023 (`f2aad6fe`); as
  diferenças da `main` que afetam esta tela já estão incorporadas: o prêmio exibido soma o valor
  acrescido do ganho múltiplo de palpites, o bilhete mostra "Valor acrescido" e "Vendedor paga"
  soma o acréscimo, e as apostas pagas com bônus apareciam na aba "créditos".
- Q: As abas "créditos" e "acumuladão" do antigo entram? → A: Não. Não existem no v2. O que a aba
  "créditos" mostrava (apostas pagas com saldo do cliente ou com bônus) passa a ser o filtro por
  forma de pagamento (Dinheiro, Saldo, Promoção esportes).
- Q: O atalho "ver apostas do vendedor" do Caixa entra? → A: Sim. Ficou de fora da spec 017 porque
  não havia lista de apostas; esta spec o liga no menu da linha do vendedor do Caixa (FR-025).
- Q: O visual pode mudar em relação ao antigo (Princípio VIII)? → A: Sim, no padrão do painel novo
  (specs 014 a 017), como nas specs anteriores. As diferenças estão em "Referências do sistema
  antigo".

### Session 2026-10-22 (respostas do responsável às perguntas do `/speckit-specify`)

- Q: A aba Canceladas é só de consulta, ou traz o "restaurar" da lixeira do antigo? → A: Só
  consulta. O v2 não restaura aposta cancelada; uma aposta cancelada por engano é refeita (US3,
  cenário 5).
- Q: Quais níveis veem as apostas dos clientes do site? → A: Só Admin e Supervisor, como o Caixa
  clientes (spec 017); a regra de cancelamento atual não muda (FR-003).
- Q: Quais situações entram na lista? → A: Só as efetivadas, como no antigo: Ativa, e Cancelada na
  aba Canceladas; os códigos de visitante Pendente, Em análise, Recusada e Expirada ficam de fora
  (FR-004).

### Session 2026-10-22 (`/speckit-clarify`)

- Q: Nos totais, o valor pago nos encerramentos (cash out) entra em Saídas ou num cartão separado?
  → A: Cartão separado "Encerramentos", só para Admin e Supervisor; Saídas = prêmios + devoluções,
  igual ao Caixa (FR-012).
- Q: A 2ª via pode ser impressa só com a aposta em aberto ou também depois de decidida? → A: Só em
  aposta ativa ainda aguardando resultado; decididas, encerradas e canceladas não têm 2ª via
  (FR-016). Diferença em relação ao antigo, que imprimia em qualquer aposta efetivada.
- Q: A planilha tem limite de linhas por download? → A: Sim, até 50.000 linhas; acima disso a tela
  avisa e pede para reduzir o período ou usar filtros, e o servidor recusa (FR-021).
- Q: Na aba Canceladas, o período filtra pela data em que a aposta foi feita ou pela do
  cancelamento? → A: Pela data em que a aposta foi feita, como no antigo e nas outras abas; a data
  e o autor do cancelamento aparecem na linha (FR-006).
- Q: Na imagem de premiados, o período considera a data em que a aposta foi feita ou a da decisão?
  → A: A data em que foi feita, como no antigo e na lista; a imagem bate com a lista filtrada por
  "Premiadas" no mesmo período (FR-020).

## Referências do sistema antigo *(Princípios VII e VIII)*

- Backend (`wssports.bet`, `main` `408cf730`): `app/Http/Controllers/BilhetesController.php`
  (`index`, `excelApostas`, `premiados`, `destroy`), `ConfrontosController@destroy` (remover e
  restaurar palpite de um bilhete), as travas `TravasGerentes` (`cancelar_aposta`,
  `cancelar_aposta_iniciado`, `t_cancelar_bilhete`, `funcao_admin`, `jogos_ao_vivo`) e
  `TravasVendedor` (`cancelar_aposta`, `segunda_via`), e as rotas `bilhetes`, `premiados`,
  `excel-apostas` e `home/{codigo}`.
- Telas: `resources/js/screens/adm/bets` ("Lista de apostas", `/adm/lista-de-apostas`), a barra
  `screens/adm/components/bar` (busca e "Filtrar por prêmio"), `utils/helpers.js` (`data_tabs`,
  `printWinner`), o modal `modals/ticket` ("Conferir Bilhete") e o atalho da tela
  `screens/adm/financial/seller` ("Clique para ver a lista de apostas desse vendedor").

**Como funcionava**:

- **Quem via o quê**: Vendedor, só as próprias; Gerente, as dos vendedores dele; Supervisor, as dos
  vendedores dos gerentes dele; Admin, todas. O mesmo menu "Lista de Apostas" para todos os níveis.
- **Filtros**: período de/até (padrão hoje), busca por nome do cliente ou código, "Filtrar por
  prêmio" (prêmio maior ou igual), status (Aguardando, Premiadas, Perdidas) e "Todo Sistema / Nessa
  Data" (a busca procurava em todo o sistema, ou só no período). O atalho do Caixa abria a lista já
  filtrada pelo vendedor e pelo período.
- **Abas**: ativos (apostas em dinheiro), ao vivo (com ao menos um palpite do ao vivo), créditos
  (pagas com saldo ou bônus do cliente), acumuladão e lixeira (canceladas). Ao vivo, créditos e
  acumuladão dependiam das configurações e das travas do gerente; a lixeira, só nessa tela.
- **Colunas**: CÓDIGO, VENDEDOR, CLIENTE, VALOR, COMISSÃO, PRÊMIO (prêmio + valor acrescido),
  STATUS, JOGOS (restantes/total) e HORÁRIO (dd/mm hh:mm); título "Lista de apostas - total N";
  250 por página.
- **Totais**: só para o Vendedor e só com busca: Filtro, Entradas, Num. Entradas, Comissão e Saídas.
- **Ações por linha**: ver o bilhete; deletar (cancelar) para Admin, Supervisor, e Gerente e
  Vendedor com a trava `cancelar_aposta`; restaurar na lixeira (Admin e Supervisor), que devolvia
  limite e saldo ao contrário; compartilhar o link do bilhete pelo WhatsApp; na aba créditos,
  "subir créditos" do prêmio pendente.
- **Bilhete ("Conferir Bilhete")**: código, cliente, vendedor, horário, situação; por palpite: jogo,
  horário, campeonato, palpite, cotação, tipo e, depois do jogo, placar, 1º e 2º tempo, escanteios,
  período e situação; no rodapé: quantidade de jogos, cotação total, comissão, valor, prêmio, valor
  acrescido e "Vendedor paga". Ícones: compartilhar, acompanhar em tempo real, imprimir 2ª via
  (Vendedor com a trava `segunda_via`, e os níveis de cima) e, para Admin, Supervisor e Gerente com
  `funcao_admin`, remover ou restaurar um palpite (o prêmio e a comissão eram recalculados).
- **Premiados**: ícone de compartilhar (não para Vendedor) que gerava uma imagem com o logo e os 23
  maiores prêmios premiados do período (código, vendedor, cliente, valor, prêmio, status).
- **Planilha**: `apostas.xlsx` com o período, o filtro aplicado e as colunas da lista.
- **Problemas encontrados**: cinco consultas quase iguais (uma por aba), com regras que divergiam
  entre elas (a aba ativos aceitava o filtro por cliente e as outras não); a lixeira juntava duas
  listas inteiras em memória e paginava depois; o seletor "Nessa Data" só mudava o estado da tela,
  sem recarregar, e só valia com busca; os premiados filtravam só o Gerente (o Supervisor via a
  banca toda); a planilha era gravada num arquivo fixo no servidor (dois downloads ao mesmo tempo se
  sobrescreviam) e contava os jogos restantes por outra regra (placar vazio) que a lista (situação
  do jogo); a permissão de cancelar era checada de um jeito na tela e de outro no servidor (o
  servidor trocava o id do usuário por 1 para quem não era Gerente); restaurar aposta devolvia o
  limite sem conferir se ainda havia limite.

**Diferenças aprovadas** (padrão das specs 014 a 017):

- Abas Todas, Pré-jogo, Ao vivo e Canceladas, como as abas de tipo do Caixa (spec 017).
- Forma de pagamento como filtro, no lugar da aba "créditos".
- Atalhos de período (Hoje, Ontem, Esta semana, Este mês, Personalizado) e o fuso do sistema.
- Filtros num painel de filtros do painel, no lugar dos SweetAlert com HTML.
- Totais do filtro em cartões, para todos os níveis, sempre visíveis (não só com busca).
- Visual do painel novo: tema, cabeçalho do painel, abas do painel, cartões (`StatCard`), listas
  que viram cartões no celular, menu de ações de três pontinhos e modais com `ModalHeader`.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Consultar as apostas da rede no período (Priority: P1)

O usuário do painel abre "Apostas", vê as apostas da rede dele no período (padrão: hoje), da mais
nova para a mais antiga, com os totais do filtro em cartões, e filtra por período, resultado,
vendedor, forma de pagamento e prêmio mínimo, ou busca um bilhete pelo código ou pelo nome do
cliente.

**Why this priority**: é a consulta do dia a dia de todos os níveis (conferir o que foi vendido,
achar o bilhete de um cliente); sozinha já substitui a "Lista de apostas" do antigo para consulta.

**Independent Test**: com apostas de dois vendedores de gerentes diferentes, como Admin, abrir
Apostas em "Hoje" e conferir as duas; como Gerente, conferir que só as do vendedor dele aparecem;
como Vendedor, conferir que só as próprias aparecem; buscar um código de outro dia e conferir que
ele é encontrado.

**Acceptance Scenarios**:

1. **Given** um usuário com a permissão de listar apostas, **When** ele abre Apostas, **Then** vê o
   período "Hoje", os cartões de totais (Apostas, Entradas, Comissão, Saídas, Em aberto) e a lista
   com código, vendedor, cliente, valor, comissão, prêmio, situação, jogos (restantes/total) e
   horário, paginada.
2. **Given** a lista, **When** o usuário escolhe um período nos atalhos ou em Personalizado,
   **Then** lista e cartões passam a mostrar as apostas feitas naquele período.
3. **Given** a busca, **When** o usuário digita um código completo, **Then** a aposta é encontrada
   em qualquer data, sem depender do período; **When** digita parte do nome do cliente, **Then** a
   busca respeita o período, salvo se ele marcar "Buscar em todo o período".
4. **Given** os filtros, **When** o usuário escolhe resultado (Aguardando, Premiadas, Perdidas,
   Devolvidas, Encerradas), vendedor, forma de pagamento ou prêmio mínimo, **Then** lista e
   cartões são recarregados na hora, e os filtros ativos aparecem como etiquetas removíveis.
5. **Given** um Vendedor, **When** ele abre Apostas, **Then** não vê o filtro por vendedor e só vê
   as próprias apostas.
6. **Given** um período sem apostas, **When** a lista carrega, **Then** os cartões mostram zero e a
   lista mostra a mensagem de vazio orientando a trocar o período ou os filtros.

---

### User Story 2 - Conferir o bilhete de uma aposta (Priority: P1)

O usuário toca numa aposta e abre o bilhete com todos os dados, os palpites, o andamento de cada
jogo e os valores, e de lá compartilha o link, acompanha em tempo real e imprime a 2ª via.

**Why this priority**: conferir o bilhete é o motivo mais comum de abrir a lista (cliente
perguntando, conferência de prêmio).

**Independent Test**: abrir uma aposta com três palpites, um já decidido, conferir os dados de cada
palpite, a cotação total, o valor acrescido e o "Vendedor paga"; compartilhar o link e abri-lo;
imprimir a 2ª via.

**Acceptance Scenarios**:

1. **Given** uma aposta na lista, **When** o usuário toca nela, **Then** abre o bilhete com código,
   cliente, vendedor, horário, situação, forma de pagamento e, por palpite, jogo, horário,
   campeonato, palpite, cotação, tipo e, com o jogo em andamento ou decidido, placar, 1º e 2º tempo,
   escanteios, período e situação; no rodapé, quantidade de jogos, cotação total, comissão, valor,
   prêmio, valor acrescido (quando houver) e "Vendedor paga" (quando diferente do prêmio).
2. **Given** o bilhete, **When** o usuário escolhe compartilhar, **Then** o link da página do
   bilhete em tempo real (spec 010) é enviado pelo compartilhamento do aparelho (ou WhatsApp no
   computador).
3. **Given** o bilhete, **When** o usuário escolhe acompanhar em tempo real, **Then** abre a página
   do bilhete em tempo real da spec 010.
4. **Given** um usuário com a permissão de imprimir 2ª via e uma aposta ativa aguardando resultado,
   **When** ele escolhe
   imprimir, **Then** o comprovante sai com a marca "2ª via", pelo mesmo meio de impressão já usado
   no painel; sem a permissão, o ícone não aparece e o servidor recusa.
5. **Given** um palpite cancelado, **When** o bilhete aparece, **Then** o palpite aparece riscado
   com o motivo.

---

### User Story 3 - Cancelar aposta e editar palpites (Priority: P2)

Quem pode cancelar uma aposta faz isso pela lista ou pelo bilhete, com confirmação; quem pode
editar cancela ou restaura um palpite dentro do bilhete. As regras são as que já existem no v2
(spec 004); a tela só mostra a ação a quem pode usá-la.

**Why this priority**: é a ação de correção mais comum (aposta feita errado); as regras já existem,
mas hoje não há tela de painel para chegar à aposta.

**Independent Test**: como Vendedor dentro do tempo de cancelamento, cancelar uma aposta própria e
conferir que ela vai para Canceladas e o limite volta; depois do tempo, conferir que a ação é
recusada com a mensagem; como Gerente com a permissão de editar, cancelar um palpite e conferir o
prêmio recalculado.

**Acceptance Scenarios**:

1. **Given** uma aposta ativa e um usuário que pode cancelá-la, **When** ele escolhe "Cancelar
   aposta" e confirma, **Then** a aposta sai das abas de ativas, aparece em Canceladas, e lista e
   cartões são atualizados sem recarregar a página.
2. **Given** um caso que as regras atuais recusam (tempo esgotado, jogo iniciado sem a permissão,
   jogo ao vivo para o Vendedor, aposta já apurada, sistema travado), **When** o usuário tenta
   cancelar, **Then** aparece a mensagem do servidor e nada muda.
3. **Given** um usuário sem a permissão de cancelar, **When** a lista aparece, **Then** a ação não
   aparece; e um acesso direto é recusado pelo servidor.
4. **Given** o bilhete de uma aposta ativa e um usuário com a permissão de editar, **When** ele
   cancela ou restaura um palpite, **Then** o bilhete mostra o palpite cancelado ou restaurado e o
   prêmio, a cotação total e a comissão recalculados pelas regras atuais.
5. **Given** a aba Canceladas, **When** ela aparece, **Then** cada linha mostra também quando e por
   quem a aposta foi cancelada, sem ação de restaurar (a aba é só de consulta).

---

### User Story 4 - Ver as apostas de um vendedor a partir do Caixa (Priority: P3)

No Caixa (spec 017), o usuário escolhe "Ver apostas" no menu da linha de um vendedor e cai na tela
Apostas já filtrada por aquele vendedor e pelo período que estava no Caixa.

**Why this priority**: liga as duas telas e evita refazer o filtro; depende da US1.

**Independent Test**: no Caixa em "Esta semana", escolher "Ver apostas" de um vendedor e conferir que
a lista abre com o filtro do vendedor e o mesmo período, e que a quantidade de apostas bate com a
coluna Apostas do Caixa.

**Acceptance Scenarios**:

1. **Given** a linha de um vendedor no Caixa, **When** o usuário escolhe "Ver apostas", **Then** abre
   Apostas com o vendedor e o período do Caixa já aplicados, e a aba de tipo correspondente (Todas,
   Pré-jogo ou Ao vivo).
2. **Given** a lista aberta pelo atalho, **When** o usuário remove o filtro do vendedor, **Then** volta
   a ver toda a rede dele, no mesmo período.
3. **Given** o mesmo período e tipo, **When** o usuário compara, **Then** a quantidade e as entradas da
   lista filtrada são iguais às da linha do vendedor no Caixa.

---

### User Story 5 - Premiados do período e exportação (Priority: P4)

O usuário gera a imagem dos maiores prêmios do período para compartilhar (Gerente para cima) e
baixa a lista filtrada em planilha Excel.

**Why this priority**: são recursos de apoio e divulgação; a consulta funciona sem eles.

**Independent Test**: com cinco apostas premiadas no período, gerar a imagem e conferir as cinco em
ordem de prêmio, com o logo da banca; baixar a planilha com um filtro de resultado e conferir que as
linhas e os totais batem com a tela.

**Acceptance Scenarios**:

1. **Given** um Gerente, Supervisor ou Admin com a permissão de exportar, **When** ele escolhe
   "Premiados", **Then** recebe uma imagem com o logo da banca e as até 23 apostas premiadas de
   maior prêmio do período, dentro da rede dele (código, vendedor, cliente, valor, prêmio,
   situação), pronta para compartilhar ou baixar.
2. **Given** a permissão de exportar, **When** o usuário escolhe "Baixar planilha", **Then** recebe um
   arquivo .xlsx com o período, os filtros aplicados, os totais e uma linha por aposta com as
   colunas da lista, com os mesmos valores da tela.
3. **Given** a exportação em andamento, **When** o usuário espera, **Then** o botão mostra carregando e
   não permite clicar de novo.
4. **Given** um Vendedor, **When** ele abre Apostas, **Then** o botão Premiados não aparece.

---

### Edge Cases

- Período personalizado com "de" maior que "até", ou maior que 1 ano: recusado no próprio campo.
- Código buscado que existe, mas está fora da rede do usuário: tratado como "Aposta não encontrada."
  (não revela que existe).
- Aposta com palpites cancelados: a coluna Jogos conta só os palpites ativos (restantes/total
  ativos); o bilhete mostra todos.
- Aposta totalmente encerrada pelo cliente (cash out, spec 010): aparece com o resultado
  "Encerrada" e o valor pago no lugar do prêmio.
- Aposta devolvida (todos os palpites anulados): aparece como "Devolvido"; o valor devolvido entra
  em Saídas, como na Dashboard e no Caixa.
- Aposta cancelada depois de apurada: não pode ser cancelada (regra atual); a ação não aparece.
- Duas pessoas cancelando a mesma aposta ao mesmo tempo: só a primeira grava; a segunda recebe
  "Esta aposta já foi cancelada." e a lista é atualizada.
- Vendedor excluído ou inativo: as apostas dele continuam na lista da rede, com o nome marcado como
  "inativo" ou "excluído".
- Vendedor que mudou de gerente: as apostas seguem o vendedor e aparecem para o gerente atual.
- Planilha com mais de 50.000 apostas no filtro: não é gerada; a tela pede para reduzir o período
  ou usar filtros (FR-021).
- Imagem de premiados sem nenhuma aposta premiada no período: a tela informa "Nenhuma aposta
  premiada no período." e não gera imagem.
- Aparelho sem compartilhamento nativo: o link é copiado e a tela avisa "Link copiado.".
- Permissão retirada com a tela aberta: a próxima ação é recusada com a mensagem do servidor e a ação
  some ao recarregar.
- Sessão expirada: leva ao login como no restante do painel.
- Celular: as linhas viram cartões com código, cliente, valor, prêmio e situação em destaque, e o
  restante (vendedor, comissão, jogos, horário) ao expandir.

## Requirements *(mandatory)*

### Functional Requirements

**Acesso e alcance**

- **FR-001**: O menu do painel DEVE ter o item "Apostas", visível para quem tem a permissão de listar
  apostas, levando à tela da lista.
- **FR-002**: Cada nível DEVE ver só as apostas da própria rede: Admin, todas; Supervisor e Gerente,
  as dos vendedores da sub-hierarquia (inclusive os excluídos); Vendedor, só as próprias. O servidor
  DEVE recusar qualquer consulta ou ação fora da rede, mesmo por acesso direto, respondendo como
  aposta inexistente.
- **FR-003**: As apostas feitas por clientes no site (sem vendedor) DEVEM aparecer só para Admin e
  Supervisor, como o Caixa clientes (spec 017), com a coluna Vendedor mostrando "Site"; Gerente e
  Vendedor NÃO DEVEM vê-las na lista, no bilhete, nos totais, nos premiados nem na planilha. A regra
  de cancelamento atual (spec 004) não muda.

**Lista, situação e período**

- **FR-004**: A lista DEVE mostrar só as apostas efetivadas, como no antigo: situação Ativa nas
  abas Todas, Pré-jogo e Ao vivo, e Cancelada na aba Canceladas; os códigos de visitante Pendente,
  Em análise, Recusada e Expirada NÃO DEVEM aparecer. As apostas são as feitas no período, da mais
  nova para a mais antiga, paginada (padrão 20, máximo 100), com o total de apostas no cabeçalho.
- **FR-005**: A tela DEVE ter o filtro de período com os atalhos Hoje (padrão), Ontem, Esta semana,
  Este mês e Personalizado (de/até, no máximo 1 ano), no fuso do sistema (Brasília); o período vale
  pela data em que a aposta foi feita, como na Dashboard e no Caixa.
- **FR-006**: A tela DEVE ter as abas Todas, Pré-jogo, Ao vivo e Canceladas: Pré-jogo e Ao vivo pela
  mesma regra do Caixa (Ao vivo = ao menos um palpite do ao vivo); Todas, Pré-jogo e Ao vivo mostram
  as apostas não canceladas; Canceladas mostra as canceladas no período pela data em que foram
  feitas (não pela data do cancelamento: uma aposta feita ontem e cancelada hoje aparece em
  "Ontem"), com data, hora e autor do cancelamento.
- **FR-007**: Cada linha DEVE mostrar: código (em maiúsculas), vendedor ("Site" para aposta de
  cliente), cliente (nome informado na aposta ou nome do cliente), valor, comissão, prêmio (prêmio +
  valor acrescido, isto é, o total a pagar; nas encerradas, o valor pago), situação (resultado quando
  já apurada, senão a situação), jogos (palpites ativos ainda sem resultado / palpites ativos) e
  horário (dd/mm hh:mm), além da forma de pagamento como etiqueta quando não for Dinheiro.
- **FR-008**: As cores da situação DEVEM seguir o padrão do painel: Aguardando neutro, Vencedor
  verde, Perdedor vermelho, Devolvido e Encerrada em cor própria, Cancelada cinza, nos dois temas.

**Busca e filtros**

- **FR-009**: A busca DEVE aceitar código ou parte do nome do cliente: um código completo é
  procurado em qualquer data (ignora o período); a busca por nome respeita o período, com a opção
  "Buscar em todo o período" para ignorá-lo. A busca e cada filtro DEVEM recarregar a lista na hora
  (corrige o "Nessa Data" do antigo).
- **FR-010**: A tela DEVE ter os filtros: resultado (Aguardando, Premiadas, Perdidas, Devolvidas,
  Encerradas), vendedor (escolhido de uma lista da rede do usuário, com busca; não aparece para o
  Vendedor), forma de pagamento (Dinheiro, Saldo, Promoção esportes) e prêmio mínimo (valor em reais,
  prêmio maior ou igual). Os filtros ativos DEVEM aparecer como etiquetas removíveis e todos juntos
  DEVEM valer igual em todas as abas (corrige as cinco consultas divergentes do antigo).
- **FR-011**: Os filtros, o período, a aba e a página DEVEM ficar no endereço da tela, para que
  recarregar, voltar do bilhete ou abrir pelo atalho do Caixa mantenha a mesma lista.

**Totais**

- **FR-012**: A tela DEVE mostrar, para todos os níveis e sempre (não só com busca), os cartões com os
  totais de todas as apostas do filtro (não só da página): Apostas (quantidade), Entradas (soma do
  valor), Comissão (soma da comissão), Saídas (prêmios pagos das premiadas + valores devolvidos,
  como no Caixa) e Em aberto (quantidade e valor das aguardando). Para Admin e Supervisor, que veem
  as apostas dos clientes do site, há também o cartão Encerramentos (valor pago nos encerramentos,
  cash out), separado das Saídas, como no Caixa clientes (spec 017). Na aba Canceladas, os cartões
  mostram só a quantidade e o valor cancelado.
- **FR-013**: Os totais DEVEM usar as mesmas regras de soma da Dashboard e do Caixa (specs 014 e
  017), calculados no servidor em reais com duas casas, sem arredondamento por ponto flutuante, de
  modo que, para o mesmo período, tipo e vendedor, Entradas, Comissão e Saídas batam com o Caixa.

**Bilhete**

- **FR-014**: Tocar numa aposta DEVE abrir o bilhete com os dados do FR-007 e, por palpite: jogo
  (ou categoria, nos especiais), horário de início, campeonato, palpite, cotação, tipo e, com o jogo
  em andamento ou decidido, placar, 1º e 2º tempo, escanteios, período e situação; no rodapé:
  quantidade de jogos, cotação total, comissão, valor, prêmio, valor acrescido (quando maior que
  zero) e "Vendedor paga" (quando diferente do total a pagar). O bilhete DEVE reaproveitar o modal
  de bilhete que o v2 já usa no site e na Dashboard, acrescentando as ações do painel.
- **FR-015**: O bilhete DEVE ter as ações compartilhar (link da página do bilhete em tempo real da
  spec 010, pelo compartilhamento do aparelho ou pelo WhatsApp no computador, ou copiando o link
  quando não houver compartilhamento) e acompanhar em tempo real (abre essa página).
- **FR-016**: O bilhete DEVE ter a ação imprimir 2ª via para quem tem a permissão, só em apostas
  ativas ainda aguardando resultado (decididas, encerradas e canceladas não têm 2ª via; a ação não
  aparece e o servidor recusa com "A 2ª via só pode ser impressa em apostas aguardando resultado."),
  imprimindo o comprovante com a marca "2ª via" pelo mesmo meio de impressão já usado pelo
  vendedor no painel; o servidor DEVE recusar a 2ª via sem a permissão ou fora da rede.

**Cancelamento e edição**

- **FR-017**: A lista e o bilhete DEVEM ter a ação "Cancelar aposta", com confirmação, usando a regra
  de cancelamento que já existe no v2 (spec 004: alcance, permissão, tempo de cancelamento do
  vendedor, jogo iniciado, ao vivo, aposta já apurada, sistema travado) sem mudá-la. A tela DEVE
  mostrar a ação só a quem tem a permissão de cancelar e só em aposta ativa ainda não apurada; a
  decisão final é sempre do servidor, e a mensagem dele aparece quando ele recusa (corrige a checagem
  divergente do antigo).
- **FR-018**: O bilhete DEVE ter, por palpite, as ações cancelar e restaurar palpite para quem tem a
  permissão de editar apostas, usando as regras que já existem no v2 sem mudá-las, e mostrar o
  bilhete atualizado (prêmio, cotação total e comissão recalculados).
- **FR-019**: Depois de cancelar a aposta ou editar um palpite, a lista, os cartões e o bilhete
  DEVEM ser atualizados sem recarregar a página.

**Premiados e exportação**

- **FR-020**: A tela DEVE ter a ação "Premiados" para Gerente, Supervisor e Admin com a permissão de
  exportar, gerando uma imagem com o logo da banca e as até 23 apostas premiadas de maior prêmio
  feitas no período (pela data em que a aposta foi feita, como a lista), dentro da rede do usuário (código, vendedor, cliente, valor, prêmio, situação), para
  compartilhar ou baixar. O Vendedor NÃO DEVE poder usá-la, mesmo com a permissão (corrige o
  Supervisor sem alcance do antigo).
- **FR-021**: A tela DEVE ter "Baixar planilha" para quem tem a permissão de exportar, gerando um
  arquivo .xlsx com o período, os filtros aplicados, os totais e uma linha por aposta do filtro (não
  só da página), com as mesmas colunas, a mesma contagem de jogos e os mesmos valores da tela, em
  formato de moeda. Cada exportação DEVE ser independente (dois downloads ao mesmo tempo não se
  misturam). A planilha DEVE ter no máximo 50.000 linhas: se o filtro passar disso, a tela avisa
  antes de baixar ("O filtro tem N apostas; o máximo por planilha é 50.000. Reduza o período ou use
  filtros.") e o servidor recusa com a mesma mensagem.

**Permissões**

- **FR-022**: DEVEM existir as permissões, no mesmo padrão das demais (lista, padrão por função e
  bloqueio por função): `apostas.listar`, `apostas.exportar` e `apostas.imprimir_segunda_via`. O
  cancelamento e a edição continuam com as permissões que já existem (`apostas.cancelar`,
  `apostas.cancelar_iniciada` e `apostas.editar`).
- **FR-023**: Padrão por função: Admin, Supervisor e Gerente recebem as três; Vendedor recebe
  `apostas.listar`, `apostas.exportar` (só a planilha; os premiados são bloqueados para ele pelo
  FR-020) e `apostas.imprimir_segunda_via`. As permissões DEVEM ser aplicadas no servidor em toda
  consulta e ação.
- **FR-024**: Os usuários já cadastrados DEVEM receber as permissões padrão da função deles ao
  publicar esta spec.

**Caixa**

- **FR-025**: O menu de ações da linha do vendedor no Caixa (spec 017) DEVE ganhar "Ver apostas",
  visível para quem tem `apostas.listar`, que abre Apostas com o vendedor, o período e a aba de tipo
  do Caixa já aplicados.

**Visual, responsividade e padrões do painel**

- **FR-026**: A tela DEVE seguir os padrões do painel (specs 014 a 017 e constituição): cores do
  tema, cabeçalho do painel, abas do painel, cartões de valor, filtro de período já usado no
  painel, cabeçalho padrão nos modais (ícone, título e descrição), campos com rótulo em cima, campo
  de valor em reais já usado no painel, paginação e menu de três pontinhos nas ações.
- **FR-027**: No celular (até 900 px), as linhas DEVEM virar cartões com código, cliente, valor,
  prêmio e situação em destaque e o restante ao expandir; os cartões de totais DEVEM ficar em duas
  colunas; os filtros DEVEM abrir num painel próprio; nada pode exigir rolagem horizontal.
- **FR-028**: Cada área (cartões, lista, bilhete) DEVE ter estados de carregando (esqueleto), vazio
  (com orientação) e erro com "tentar novamente", sem travar as outras.
- **FR-029**: Toda ação DEVE dar retorno imediato (botão com carregando, confirmação de sucesso ou
  mensagem do servidor).
- **FR-030**: Abrir o bilhete, cancelar ou editar NÃO DEVE perder a posição, os filtros nem a página
  da lista.

### Key Entities

- **Aposta (na lista)**: aposta já existente (specs 004, 008, 009 e 010), vista com código,
  vendedor, cliente, valor, comissão, prêmio (total a pagar), valor acrescido, situação, resultado,
  tipo (pré-jogo ou ao vivo), forma de pagamento, jogos restantes/total, horário e, nas canceladas,
  quando e por quem foi cancelada. Nada novo é gravado na aposta por esta spec.
- **Totais do filtro**: valores calculados a cada consulta (quantidade, entradas, comissão, saídas,
  em aberto); não são gravados.
- **Premiados do período**: até 23 apostas premiadas de maior prêmio no período e na rede do
  usuário, usadas para a imagem.
- **Permissões de apostas**: as três permissões novas (listar, exportar e imprimir 2ª via) somadas às
  que já existem.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Um usuário encontra o bilhete de uma aposta pelo código e o abre em menos de 15
  segundos a partir do menu.
- **SC-002**: Em 100% das conferências manuais, para o mesmo período, tipo e vendedor, a quantidade,
  as entradas, a comissão e as saídas da lista batem com o Caixa, e a planilha exportada bate com a
  tela.
- **SC-003**: Um usuário nunca vê, cancela, edita ou imprime aposta de fora da rede dele em nenhum
  dos testes, inclusive por acesso direto.
- **SC-004**: Em 100% dos testes, uma ação recusada pelas regras de cancelamento mostra a mesma
  mensagem do servidor e não muda nada; nenhuma ação aparece para quem não pode usá-la.
- **SC-005**: Um gestor do sistema antigo encontra na tela nova cada informação da "Lista de
  apostas" e do "Conferir Bilhete" (colunas, filtros, totais e ações), exceto créditos e
  acumuladão.
- **SC-006**: A tela funciona no celular (360 px de largura) sem rolagem horizontal e com todos os
  alvos de toque com pelo menos 40 px.
- **SC-007**: Abrir Apostas ou trocar de filtro mostra os cartões e a primeira página em até 2
  segundos numa conexão comum, com até 50.000 apostas no período consultado.

## Assumptions

- Criar, validar, cancelar aposta, cancelar e restaurar palpite, apurar, encerrar e a página do
  bilhete em tempo real já existem (specs 004, 008, 009 e 010) e não mudam; esta spec só lista,
  mostra e chama essas ações.
- As regras de soma dos totais são as da Dashboard e do Caixa (specs 014 e 017).
- A biblioteca de planilhas autorizada na spec 017 é reaproveitada; não há dependência nova.
- A 2ª via usa o comprovante e o meio de impressão que o vendedor já usa no painel (impressão do
  navegador no computador, impressora ou aplicativo no celular); a regra nova é só a permissão.
- O logo da imagem de premiados é o logo da banca já configurado (configurações do sistema).
- A data de uma aposta, para o período, é a data em que foi feita, no fuso do sistema (Brasília).
- "Subir créditos" da aba créditos do antigo não entra: no v2 o prêmio do cliente vai para o saldo
  pela apuração (spec 009).
- As apostas do sistema antigo não são migradas; a lista mostra as apostas do v2.
- Não há dados fake nesta spec.
- Sem testes automatizados (constituição); a validação é manual pelo quickstart da spec.
