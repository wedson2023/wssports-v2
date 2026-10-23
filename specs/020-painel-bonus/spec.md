# Feature Specification: Painel — Bônus (promoções, bônus recebidos e estornos)

**Feature Branch**: `020-painel-bonus`

**Created**: 2026-10-23

**Status**: Draft

**Input**: User description: "Spec 020: tela 'Bônus' no painel (backend e frontend), no padrão das
specs 014 a 019. O cadastro de promoções (clientes_promocoes) existe desde as specs 002/007/012:
listar com filtros, ver, cadastrar, editar (com travas após aplicada e sem sobreposição), excluir e
estorno geral em segundo plano com motivo; a expiração e o converter/excluir do cliente são da spec
007. Falta a tela. Trazer do antigo (wssports.bet, main 408cf730; backend: BonusController index,
store, show, update e rollover, e o BonusCommand; telas: screens/adm/bonus 'Lista de bônus'
/adm/bonus e o modal screens/adm/modals/bonus 'Cadastrar bônus') tudo da tela. [...] Excluir só
promoção que ninguém recebeu; a já aplicada só pode ser desativada ou estornada. Visual do painel
novo (abas, StatCard, filtros com etiquetas, ModalHeader, campo de reais, menu de três pontinhos,
lista que vira cartões no celular)."

## Clarifications

### Session 2026-10-23 (decisões tomadas antes do `/speckit-specify`)

- Q: Qual versão do sistema antigo é a referência? → A: A `main` atual do `wssports.bet`
  (`408cf730`), tela `screens/adm/bonus` ("Lista de bônus", `/adm/bonus`) e o modal
  `screens/adm/modals/bonus` ("Cadastrar bônus").
- Q: Qual o nome da tela no menu? → A: "Bônus", na rota `/painel/bonus`, como no antigo e como a
  spec 019 já tinha registrado. Por dentro o recurso continua sendo a promoção (`clientes_promocoes`).
- Q: A aba "Ativos" do antigo (os bônus recebidos por cada cliente) entra? → A: Sim, como a aba
  "Bônus recebidos", com todas as situações (não só os em andamento) e a ação nova de estornar o
  bônus de um cliente só.
- Q: Quem vê e gerencia as promoções? → A: Por rede, como na spec 019: o Admin vê e gerencia todas,
  inclusive as gerais (sem afiliado); Supervisor e Gerente veem e gerenciam só as dos afiliados da
  rede deles e só cadastram para esses afiliados. Estornar continua só de Admin e Supervisor.
- Q: O estorno pede a senha de quem faz? → A: Sim, senha e motivo, no estorno geral e no
  individual, como no antigo e como o ajuste de saldo da spec 019. Muda a rota de estorno atual.
- Q: O que acontece ao excluir uma promoção que já foi creditada? → A: Não pode ser excluída; só
  desativada ou estornada. Só a promoção que ninguém recebeu pode ser excluída.
- Q: O visual pode mudar em relação ao antigo (Princípio VIII)? → A: Sim, no padrão do painel novo
  (specs 014 a 019), como nas specs anteriores. As diferenças estão em "Referências do sistema
  antigo".

### Session 2026-10-23 (`/speckit-clarify`)

- Q: Duas promoções ativas da mesma categoria e modalidade podem valer ao mesmo tempo quando uma é
  geral e a outra é de um afiliado? → A: Sim. Só não podem se cruzar duas do mesmo afiliado ou duas
  gerais (regra do antigo); a do afiliado do cliente vale antes da geral (spec 007). Muda a regra
  atual do v2, que bloqueava qualquer promoção ativa da mesma categoria e modalidade (FR-015).
- Q: O nome da promoção deve ser único entre as promoções não excluídas? → A: Sim, único entre as
  não excluídas, como no antigo; as já gravadas com nome repetido só precisam mudar quando forem
  editadas (FR-016).
- Q: O servidor deve recusar uma data de fim que já passou? → A: Sim, só quando o fim é informado
  ou alterado para uma data passada; editar outros campos de uma promoção já encerrada continua
  permitido (FR-016).
- Q: Supervisor e Gerente devem ver as promoções gerais (sem afiliado), mesmo sem poder alterá-las?
  → A: Sim, só leitura: aparecem na lista (marcadas "Geral") e nos filtros, com os números de uso
  só dos clientes da rede deles; não editam, não ativam/desativam, não excluem nem estornam
  (FR-002, FR-006, FR-008 e FR-010).
- Q: O estorno de um bônus que o cliente já converteu para o saldo real deve retirar esse valor do
  saldo real? → A: Não. Bônus convertido não é estornado, nem no estorno individual nem no geral; a
  correção é pelo ajuste manual de saldo da spec 019 (FR-027 e FR-028).

## Referências do sistema antigo *(Princípios VII e VIII)*

- Backend (`wssports.bet`, `main` `408cf730`): `app/Http/Controllers/BonusController.php` (`index`
  com as abas Todos e Ativos, `store`, `show`, `update` e `rollover`, que revertia o bônus geral ou
  o de um cliente), `app/Console/Commands/BonusCommand.php` (`command:bonus`, expiração dos bônus e
  zeragem do rollover), o model `CreditosBonus` e as migrations de `creditos_bonuses`.
- Telas: `resources/js/screens/adm/bonus` ("Lista de bônus - total N", rota `/adm/bonus`), a barra
  `screens/adm/components/bar` e o modal `screens/adm/modals/bonus` ("Cadastrar bônus" /
  "Atualizar bônus").

**Como funcionava**:

- **Quem via o quê**: Admin e Supervisor, todos os bônus; Gerente, só os que ele mesmo cadastrou
  (`usuarios_id`), e só podia cadastrar com a trava `funcao_pin` ligada. Afiliados em lista com
  todos os afiliados da banca.
- **Aba Todos**: colunas ID, NOME, MODALIDADE, VALOR (R$ ou %), DIAS CUMPRIR, ROLLOVER, ATIVO, COMEÇA
  EM, TERMINA EM e AÇÃO (reverter e editar). Busca por nome; 250 por página; o filtro de período
  estava comentado.
- **Aba Ativos**: os créditos de bônus com saldo maior que zero, com ID, USUÁRIO, BÔNUS (campanha),
  SALDO, MODALIDADE, ROLLOVER, CRIADO EM e EXPIRA EM, e a ação reverter só daquele cliente; ordem
  pelo rollover.
- **Cadastrar/Atualizar bônus**: nome (único), ganho em (Primeiro cadastro, Primeiro depósito,
  Qualquer depósito, Indicação de cadastro), tipo de ganho (Valor fixo ou Percentual, só nos de
  depósito), valor, começa em, termina em, ativo, modalidade (Esportes ou Cassino, não muda depois
  de criada), dias para cumprir, rollover, valor mínimo e máximo de aposta e odds mínimas (só
  esportes), valor para converter, depósito máximo (só percentual), rollover no primeiro depósito
  (só primeiro depósito) e, conforme o tipo, quantidade de indicações ou afiliado.
- **Validações**: término não pode estar no passado nem antes do início; não podia haver dois
  bônus com o mesmo "ganho em" e o mesmo afiliado (ou dois gerais) em períodos que se cruzam
  ("Já existe uma ação de bônus com o(s) parâmetro(s) especificados, remova o registro de id N").
- **Reverter**: pedia confirmação e a senha. O geral tirava o saldo de bônus de todos que
  receberam, zerava os rollovers, apagava as apostas feitas com o bônus e marcava o bônus como
  revertido; o individual zerava o rollover e o saldo do crédito e apagava as apostas daquele
  cliente com o bônus.
- **Excluir**: a rota existia, mas estava desligada (comentada).
- **Problemas encontrados**: o reverter geral rodava tudo numa única transação (travava com muitos
  clientes); o individual não pedia senha, não tirava o saldo de bônus do cliente e não guardava
  motivo nem autor; apagar as apostas feitas com o bônus mudava o histórico; o ícone de "reverter"
  era um interruptor que parecia "ativar/desativar"; a aba Ativos não mostrava os bônus já
  cumpridos, convertidos ou expirados; a regra de período sobreposto no editar era diferente da do
  cadastrar; datas convertidas com o fuso enviado pelo navegador.

**Diferenças aprovadas** (padrão das specs 014 a 019):

- O alcance segue a rede da spec 019 (afiliado → gerente responsável), no lugar do "Gerente vê só
  os que cadastrou" e da trava `funcao_pin`.
- O estorno (geral e individual) pede senha e motivo, fica registrado com autor e data, mexe só no
  saldo promocional da modalidade (retira o menor entre o saldo e o recebido) e NÃO apaga apostas.
  O geral roda em segundo plano, com progresso (spec 007).
- A aba Ativos vira "Bônus recebidos", com todas as situações e filtro por situação.
- Excluir passa a existir, só para promoção que ninguém recebeu.
- Os campos são os do v2 (specs 002, 007 e 012): descrição, datas com fim opcional (sem fim =
  vale até ser desativada), valor máximo de conversão obrigatório; as regras de aposta continuam
  obrigatórias nas duas modalidades, como hoje.
- Visual do painel novo: tema, cabeçalho do painel, abas do painel, cartões (`StatCard`), painel de
  filtros com etiquetas, listas que viram cartões no celular, menu de ações de três pontinhos e
  modais com `ModalHeader`.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Consultar as promoções (Priority: P1)

O usuário do painel abre "Bônus", vê as promoções (das mais novas para as mais antigas) com a
situação de cada uma e os números de uso, busca pelo nome e filtra por situação, modalidade,
categoria e afiliado.

**Why this priority**: é a consulta do dia a dia (qual promoção está valendo, quanto já foi dado);
sozinha já substitui a aba "Todos" do antigo para consulta.

**Independent Test**: com uma promoção vigente que três clientes receberam e outra desativada, abrir
Bônus e conferir colunas, situação e os números (3 clientes, valor creditado); filtrar "Vigentes" e
conferir que só a primeira aparece; entrar como um Gerente e conferir que só as promoções dos
afiliados dele aparecem.

**Acceptance Scenarios**:

1. **Given** um usuário com a permissão de gerenciar promoções, **When** ele abre Bônus, **Then** vê
   a aba Promoções com o total no cabeçalho e a lista com ID, nome, modalidade, categoria, ganho (R$
   ou %), rollover, dias para cumprir, afiliado (ou "Geral"), início, fim, situação e os números de
   uso, paginada.
2. **Given** a busca, **When** o usuário digita parte do nome, **Then** a lista mostra só as
   promoções que casam, sem escapar dos filtros aplicados.
3. **Given** o painel de filtros, **When** o usuário escolhe situação, modalidade, categoria ou
   afiliado, **Then** a lista é recarregada com os filtros combinados e eles aparecem como etiquetas
   removíveis.
4. **Given** um Gerente, **When** ele abre Bônus, **Then** vê as promoções dos afiliados de que é o
   gerente responsável e as gerais (só leitura, com os números só dos clientes da rede dele); o
   Supervisor vê as dos afiliados dos gerentes abaixo dele e as gerais, do mesmo jeito; o Admin vê
   e gerencia todas.
5. **Given** uma promoção de fora da rede, **When** o usuário tenta abri-la ou agir sobre ela por
   acesso direto, **Then** recebe "Promoção não encontrada.".
6. **Given** uma promoção na lista, **When** o usuário toca nela, **Then** abre o detalhe com todos
   os campos, os números de uso e, quando houver, o andamento ou o resultado do estorno.

---

### User Story 2 - Cadastrar e editar promoções (Priority: P1)

O usuário cadastra uma promoção (o antigo "Cadastrar bônus") escolhendo categoria, modalidade,
ganho, rollover, prazo, regras de aposta e afiliado, e depois a edita, ativa ou desativa.

**Why this priority**: sem a tela, a banca não tem como criar ou ajustar promoções.

**Independent Test**: cadastrar uma promoção de primeiro depósito, percentual de 100% até R$ 200,00,
rollover 5x, 7 dias, para um afiliado; conferir na lista; fazer um primeiro depósito com um cliente
desse afiliado e conferir o bônus; tentar mudar o valor e conferir a recusa; desativar e conferir
que um novo cliente não recebe mais.

**Acceptance Scenarios**:

1. **Given** a permissão de gerenciar, **When** o usuário escolhe "Cadastrar", **Then** abre o modal
   com os campos agrupados (Identificação, Ganho, Período, Rollover e prazo, Regras de aposta,
   Afiliado ou indicações) e os que não se aplicam ficam escondidos conforme a categoria, o tipo de
   ganho e a modalidade.
2. **Given** o modal preenchido, **When** o usuário salva, **Then** a promoção é gravada pelas regras
   atuais e a lista é atualizada; **When** algum campo é recusado, **Then** a mensagem do servidor
   aparece no campo.
3. **Given** uma promoção que já tem um período que se cruza com outra ativa da mesma categoria,
   modalidade e afiliado (ou duas gerais), **When** o usuário salva, **Then** aparece "Já existe uma
   promoção ativa desta categoria e modalidade no período." com o nome da outra.
4. **Given** uma promoção já creditada a algum cliente, **When** o usuário a edita, **Then** valor,
   tipo de ganho, categoria e modalidade aparecem travados com o aviso do motivo, e o resto pode ser
   alterado.
5. **Given** uma promoção ativa, **When** o usuário escolhe "Desativar" no menu e confirma, **Then**
   ela deixa de ser dada a novos clientes, e quem já recebeu continua com o bônus e o rollover.
6. **Given** um Gerente ou Supervisor, **When** ele cadastra, **Then** o afiliado é obrigatório e
   escolhido da lista dos afiliados da rede dele; só o Admin pode deixar sem afiliado (promoção
   geral).
7. **Given** uma promoção estornada, **When** o usuário abre o menu, **Then** editar, ativar e
   excluir não aparecem.

---

### User Story 3 - Acompanhar os bônus recebidos (Priority: P1)

O usuário abre a aba "Bônus recebidos" e vê cada bônus dado a um cliente, com o saldo, o rollover
exigido e apostado, o prazo e a situação; filtra por situação, promoção, modalidade e período, e vê
os totais em cartões.

**Why this priority**: é o atendimento ao cliente ("por que não consigo sacar?", "meu bônus
sumiu") e o controle do que está em aberto; substitui a aba "Ativos" do antigo.

**Independent Test**: com um bônus em andamento, um convertido e um expirado, abrir a aba e conferir
que "Em andamento" vem selecionado e mostra só o primeiro, com o quanto falta apostar; trocar para
"Todos" e conferir as três situações; conferir os cartões.

**Acceptance Scenarios**:

1. **Given** a aba Bônus recebidos, **When** ela abre, **Then** a situação "Em andamento" vem
   selecionada, sem período, do que expira primeiro para o que expira por último, com cliente (ID e
   nome), promoção, modalidade, valor recebido, saldo promocional atual do cliente na modalidade,
   rollover exigido, apostado e quanto falta, recebido em, expira em e situação.
2. **Given** a escolha de situação, **When** o usuário escolhe Cumpridos, Convertidos, Expirados,
   Excluídos pelo cliente, Estornados ou Todos, **Then** a lista mostra esses bônus do mais novo
   para o mais antigo, com a data e, quando houver, o autor e o motivo do estorno.
3. **Given** a busca e os filtros, **When** o usuário procura um cliente (nome ou ID) ou escolhe
   promoção, modalidade ou período de recebimento, **Then** a lista e os cartões mostram só os
   bônus que casam.
4. **Given** os cartões, **When** a aba carrega, **Then** mostra Em andamento (quantidade e valor
   que falta apostar) e, para o período escolhido, Creditado, Convertido e Retirado (expirados,
   excluídos e estornados).
5. **Given** um bônus, **When** o usuário toca na linha, **Then** abre o detalhe do bônus com o
   histórico (recebido, apostas que somaram no rollover, cumprido, convertido, expirado, excluído
   ou estornado) e o atalho para o detalhe do cliente da tela Clientes (spec 019).
6. **Given** uma promoção no detalhe, **When** o usuário escolhe "Ver bônus recebidos", **Then** abre
   esta aba filtrada pela promoção, na situação Todos.
7. **Given** um Gerente, **When** ele abre a aba, **Then** vê só os bônus dos clientes da rede dele
   (spec 019, FR-003).

---

### User Story 4 - Estornar uma promoção inteira (Priority: P2)

Admin ou Supervisor estorna uma promoção (o antigo "reverter bônus"): informa motivo e a própria
senha, e o bônus é retirado de todos que o receberam, em segundo plano, com o progresso na tela.

**Why this priority**: é a correção de uma promoção criada errada ou abusada; pouco frequente, mas
o antigo tinha e a operação depende dela.

**Independent Test**: com uma promoção recebida por três clientes (um deles já gastou parte do
bônus), estornar com motivo e senha; conferir o progresso até "Concluído", o saldo promocional de
cada um e as linhas "Estorno" no extrato; conferir que as apostas feitas com o bônus continuam lá.

**Acceptance Scenarios**:

1. **Given** uma promoção não estornada, **When** o usuário escolhe "Estornar promoção", **Then**
   abre a confirmação com o aviso, a quantidade de clientes que receberam, o valor creditado, o
   campo do motivo e o da senha.
2. **Given** motivo e senha corretos, **When** o usuário confirma, **Then** a promoção é desativada,
   o estorno começa em segundo plano e a linha mostra "Estornando N de M clientes" até "Estornada",
   com o valor retirado, o autor e a data.
3. **Given** a senha errada, **When** o usuário confirma, **Then** nada muda e aparece "Senha
   incorreta.".
4. **Given** um cliente que já gastou parte do bônus, **When** o estorno passa por ele, **Then** sai
   do saldo promocional o menor entre o saldo atual e o valor dos bônus dele ainda em andamento ou
   cumpridos, sem negativar, e o rollover deles deixa de valer; os bônus que ele já converteu,
   que expiraram ou que ele excluiu não são retirados de novo.
5. **Given** um Gerente, **When** ele abre o menu da promoção, **Then** a ação não aparece, e um
   acesso direto é recusado.

---

### User Story 5 - Estornar o bônus de um cliente (Priority: P2)

Admin ou Supervisor escolhe "Estornar" num bônus recebido (o antigo reverter da aba Ativos),
informa motivo e senha, e o bônus é retirado só daquele cliente.

**Why this priority**: resolve o caso de um cliente que abusou da promoção sem mexer nos demais.

**Independent Test**: com um bônus de R$ 50,00 em andamento e o saldo promocional do cliente em R$
30,00, estornar com motivo e senha; conferir que o saldo promocional vai a zero, o rollover deixa de
valer, o extrato mostra "Estorno" com o autor e o motivo e o bônus aparece em Estornados.

**Acceptance Scenarios**:

1. **Given** um bônus em andamento ou cumprido e não convertido, **When** o usuário escolhe
   "Estornar", **Then** abre a confirmação com o cliente, o bônus, o valor que será retirado, o
   motivo e a senha.
2. **Given** motivo e senha corretos, **When** o usuário confirma, **Then** sai do saldo promocional
   da modalidade o menor entre o saldo atual e o valor do bônus, o rollover do bônus deixa de
   valer, o bônus vai para Estornado com autor, data e motivo, e a lista e os cartões são
   atualizados.
3. **Given** um bônus convertido, expirado, excluído ou já estornado, **When** o usuário abre o menu,
   **Then** a ação não aparece, e um acesso direto é recusado com "Este bônus não pode mais ser
   estornado.".
4. **Given** a senha errada, **When** o usuário confirma, **Then** nada muda e aparece "Senha
   incorreta.".

---

### User Story 6 - Excluir promoção não usada (Priority: P3)

O usuário exclui uma promoção cadastrada por engano, desde que ninguém a tenha recebido.

**Why this priority**: limpeza da lista; a operação funciona só desativando.

**Independent Test**: excluir uma promoção nova e conferir que ela some da lista; tentar excluir uma
promoção já creditada e conferir a recusa.

**Acceptance Scenarios**:

1. **Given** uma promoção que ninguém recebeu, **When** o usuário escolhe "Excluir" e confirma,
   **Then** ela sai da lista.
2. **Given** uma promoção já creditada a algum cliente, **When** o usuário abre o menu, **Then**
   "Excluir" não aparece, e um acesso direto é recusado com "Promoção já aplicada não pode ser
   excluída; desative ou estorne.".

---

### Edge Cases

- Promoção com início no futuro: aparece como "Agendada" e ainda não é dada a ninguém.
- Promoção com fim no passado: aparece como "Encerrada"; pode ser editada para estender o fim.
- Promoção sem fim: aparece "Sem fim" e vale até ser desativada.
- Promoção de "Primeiro cadastro" com rollover zero: o bônus recebido aparece direto como Cumprido.
- Promoção de "Indicação": os números de uso contam cada bônus pago a um indicador (um indicador
  pode receber várias vezes, spec 007).
- Bônus de indicação cancelado porque a indicação voltou a "Pendente de depósito" (spec 007): aparece
  como Estornado, com a observação do motivo automático.
- Estorno geral com um cliente excluído ou inativo: o bônus dele também é retirado.
- Estorno geral interrompido (queda do servidor): retoma de onde parou, sem retirar duas vezes.
- Estorno individual e estorno geral do mesmo bônus ao mesmo tempo: o bônus é retirado uma vez só.
- Estorno individual enquanto o cliente converte o bônus: só uma das duas ações passa; a outra
  recebe a mensagem do servidor.
- Bônus estornado individualmente: o estorno geral posterior da promoção não retira de novo.
- Afiliado da promoção que muda de gerente: a promoção passa para a rede do novo gerente na hora.
- Afiliado excluído: a promoção dele continua na lista (com o afiliado marcado como excluído) e pode
  ser desativada ou estornada.
- Promoção de afiliado sem gerente responsável: só o Admin vê.
- Período personalizado com "de" maior que "até", ou maior que 1 ano: recusado no próprio campo.
- Permissão retirada com a tela aberta: a próxima ação é recusada com a mensagem do servidor e a ação
  some ao recarregar.
- Sessão expirada: leva ao login como no restante do painel.
- Celular: as linhas viram cartões (Promoções: nome, ganho e situação; Bônus recebidos: cliente,
  valor e quanto falta) com o restante ao expandir.

## Requirements *(mandatory)*

### Functional Requirements

**Acesso e abas**

- **FR-001**: O menu do painel DEVE ter o item "Bônus", visível para quem tem a permissão
  `clientes_promocoes.gerenciar`, levando à tela `/painel/bonus` com as abas Promoções e Bônus
  recebidos.
- **FR-002**: Cada nível DEVE ver e agir só sobre as promoções da própria rede, definida pelo
  afiliado da promoção e pelo gerente responsável desse afiliado (mesma regra da spec 019, FR-003):
  - **Admin**: todas, inclusive as gerais (sem afiliado) e as de afiliado sem gerente responsável;
  - **Supervisor**: gerencia as dos afiliados cujo gerente responsável está abaixo dele;
  - **Gerente**: gerencia as dos afiliados de que ele é o gerente responsável;
  - **Vendedor**: não acessa a tela.
  Supervisor e Gerente também veem as promoções gerais, só para leitura (lista, filtros, detalhe e
  "Ver bônus recebidos"): não editam, não ativam/desativam, não excluem nem estornam, e o servidor
  recusa essas ações com "Só o Admin pode alterar promoções gerais.". As promoções de afiliado sem
  gerente responsável e as de afiliados de outras redes continuam invisíveis para eles.
  A aba Bônus recebidos DEVE mostrar só os bônus dos clientes da rede do usuário (spec 019,
  FR-003), inclusive os de promoções gerais. Qualquer consulta ou ação fora da rede DEVE ser
  respondida como inexistente ("Promoção não encontrada." / "Bônus não encontrado.").
- **FR-003**: O mesmo alcance DEVE valer no servidor para as rotas de promoções que já existem
  (listar, ver, cadastrar, editar, excluir e estornar), mudando o que foi construído nas specs 002,
  007 e 012, em que todo usuário com a permissão via e alterava todas as promoções.

**Promoções**

- **FR-004**: A aba Promoções DEVE listar as promoções não excluídas, da mais nova para a mais antiga
  pela data de início, paginada (padrão 20, máximo 100), com o total do filtro no cabeçalho.
- **FR-005**: Cada linha DEVE mostrar: ID, nome, modalidade, categoria, ganho (valor em reais ou
  percentual, com o máximo de depósito quando percentual), rollover (vezes), dias para cumprir,
  afiliado (nome e código, ou "Geral"), início e fim (dd/mm/aaaa hh:mm, fuso do sistema; "Sem fim"
  quando não houver), situação e os números de uso (FR-006).
- **FR-006**: Os números de uso de cada promoção DEVEM ser calculados no servidor em reais com duas
  casas, sem ponto flutuante: **Clientes** (quantos receberam), **Creditado** (soma dos bônus
  dados), **Cumpridos** (quantos bônus cumpriram o rollover), **Convertido** (soma do que passou
  para o saldo real) e **Retirado** (soma do que saiu por expiração, exclusão pelo cliente e
  estorno). Para Supervisor e Gerente, os números consideram só os clientes da rede deles (nas
  promoções gerais, só os bônus recebidos por esses clientes).
- **FR-007**: A situação da promoção DEVE ser uma de: **Vigente** (ativa e dentro do período),
  **Agendada** (ativa e com início no futuro), **Encerrada** (ativa e com fim no passado),
  **Desativada**, **Estornando** (estorno em andamento, com "N de M clientes") ou **Estornada**,
  cada uma com cor própria.
- **FR-008**: A aba DEVE ter busca por nome (trecho) e o painel de filtros com situação (FR-007),
  modalidade, categoria e afiliado (escolhido de uma lista dos afiliados da rede, constituição
  "Campos de itens existentes", com a opção "Geral" para todos). Os filtros ativos DEVEM
  aparecer como etiquetas removíveis.
- **FR-009**: Os filtros, a busca, a aba, a situação e a página DEVEM ficar no endereço da tela, para
  que recarregar ou voltar do detalhe mantenha a mesma lista.
- **FR-010**: O menu de ações (três pontinhos) de cada promoção DEVE ter, conforme a permissão e a
  situação: Ver detalhe, Editar, Ativar/Desativar, Ver bônus recebidos, Estornar promoção e
  Excluir. Nas promoções gerais, Supervisor e Gerente veem só Ver detalhe e Ver bônus recebidos.
  Tocar na linha abre o detalhe.
- **FR-011**: O detalhe DEVE abrir numa gaveta (tela cheia no celular) com todos os campos da
  promoção, os números de uso (FR-006), as datas de cadastro e alteração e, quando houver, o
  estorno (situação, progresso, motivo, autor, início, conclusão e valor retirado).

**Cadastro e edição**

- **FR-012**: O modal de cadastro e edição DEVE ter os campos atuais da promoção: nome, descrição,
  modalidade (Esportes ou Cassino), categoria (Primeiro cadastro, Primeiro depósito, Qualquer
  depósito ou Indicação), tipo de ganho (Fixo ou Percentual), valor, valor máximo de depósito,
  rollover (vezes), rollover no primeiro depósito, dias para cumprir, valor mínimo e máximo de
  aposta, odd mínima da aposta simples e da múltipla, valor máximo de conversão, início, fim,
  ativa, afiliado e quantidade de indicações, com as mesmas regras e mensagens do servidor de hoje.
- **FR-013**: O modal DEVE mostrar só os campos que se aplicam: tipo de ganho Percentual só nas
  categorias de depósito (nas outras fica Fixo); valor máximo de depósito só no Percentual;
  rollover no primeiro depósito só em Primeiro depósito; quantidade de indicações só em Indicação.
  Valores em reais DEVEM usar o campo de reais do painel, e o percentual um campo de 0 a 100.
- **FR-014**: O afiliado DEVE ser escolhido de uma lista dos afiliados da rede do usuário. Para
  Supervisor e Gerente ele DEVE ser obrigatório; só o Admin pode deixar sem afiliado (promoção
  geral) ou escolher um afiliado sem gerente responsável. O servidor DEVE recusar o afiliado de
  fora da rede com "Afiliado não encontrado.".
- **FR-015**: Duas promoções ativas e não estornadas da mesma categoria, modalidade e afiliado (ou
  duas gerais) NÃO PODEM ter períodos que se cruzam, como no antigo; promoções de afiliados
  diferentes, ou uma geral e uma de afiliado, PODEM (a do afiliado vale antes da geral, regra da
  spec 007). Muda a regra atual do v2, que não considerava o afiliado. A mensagem DEVE dizer qual
  promoção conflita ("Já existe uma promoção ativa desta
  categoria e modalidade no período: {nome} (ID {id}).").
- **FR-016**: O nome DEVE ser único entre as promoções não excluídas ("Já existe uma promoção com
  este nome."), e o fim, quando informado no cadastro ou alterado na edição, NÃO PODE estar no
  passado ("A data de fim já passou."), como no antigo; editar outros campos de uma promoção já
  encerrada, sem mexer no fim, continua permitido.
- **FR-017**: Depois de a promoção ser creditada a algum cliente, valor, tipo de ganho, categoria e
  modalidade NÃO PODEM mudar (regra atual); o modal DEVE mostrar esses campos travados com o aviso.
  Promoção estornada NÃO PODE ser editada, ativada nem excluída.
- **FR-018**: Ativar/Desativar DEVE ter confirmação, valer só para novos recebimentos (quem já
  recebeu continua com o bônus e o rollover) e conferir a sobreposição (FR-015) ao ativar.

**Exclusão**

- **FR-019**: Excluir DEVE ser permitido só para a promoção que ninguém recebeu, com confirmação. A
  promoção já creditada DEVE ser recusada pelo servidor com "Promoção já aplicada não pode ser
  excluída; desative ou estorne.", e a ação NÃO DEVE aparecer no menu. Muda a exclusão atual, que
  permitia excluir qualquer promoção.

**Bônus recebidos**

- **FR-020**: A aba Bônus recebidos DEVE listar cada bônus dado a um cliente por uma promoção (de
  cadastro, depósito ou indicação), paginada (padrão 30, máximo 100), com a escolha de situação Em
  andamento (padrão), Cumpridos, Convertidos, Expirados, Excluídos pelo cliente, Estornados e
  Todos. Em andamento DEVE vir do que expira primeiro para o último; as demais, do mais novo para o
  mais antigo.
- **FR-021**: A situação do bônus DEVE ser: **Em andamento** (rollover não cumprido, dentro do
  prazo), **Cumprido** (rollover cumprido ou promoção sem rollover, ainda não convertido),
  **Convertido**, **Expirado**, **Excluído pelo cliente** ou **Estornado** (estorno geral,
  individual ou o cancelamento automático do bônus de indicação).
- **FR-022**: Cada linha DEVE mostrar: cliente (ID e nome), promoção (nome), modalidade, valor
  recebido, saldo promocional atual do cliente na modalidade, rollover exigido, apostado e quanto
  falta, recebido em, expira em, situação e, quando encerrado, a data, o valor retirado ou
  convertido e, no estorno, o autor e o motivo.
- **FR-023**: A aba DEVE ter busca por cliente (nome ou ID) e os filtros promoção (escolhida de
  lista), modalidade e período de recebimento (atalhos Hoje, Ontem, Esta semana, Este mês e
  Personalizado, até 1 ano; sem período por padrão em Em andamento), com etiquetas removíveis e
  guardados no endereço da tela.
- **FR-024**: A aba DEVE mostrar os cartões **Em andamento** (quantidade e valor que falta apostar,
  de todos os bônus nessa situação) e, para o período escolhido (ou desde sempre, sem período),
  **Creditado**, **Convertido** e **Retirado** (quantidade e valor), respeitando a busca e os
  filtros.
- **FR-025**: O detalhe do bônus DEVE mostrar os dados da linha, as regras de uso gravadas no
  recebimento (valor mínimo e máximo de aposta e odds mínimas), o histórico (recebido, cumprido,
  convertido, expirado, excluído ou estornado, com datas) e o atalho "Ver cliente", que abre o
  detalhe do cliente da tela Clientes (spec 019) para quem tem `clientes.listar`.

**Estornos**

- **FR-026**: O estorno da promoção inteira DEVE usar o estorno atual em segundo plano (spec 007) e
  passar a exigir, além do motivo (até 255 caracteres), a senha de quem estorna: o servidor DEVE
  conferir a senha antes de mexer em qualquer coisa e recusar com "Senha incorreta." sem gravar
  nada, também por acesso direto (muda a rota atual). A confirmação DEVE mostrar a quantidade de
  clientes e o valor creditado, e a tela DEVE mostrar o progresso até a conclusão sem recarregar.
- **FR-027**: DEVE existir o estorno do bônus de um cliente, para bônus Em andamento ou Cumprido:
  com motivo (até 255 caracteres) e a senha de quem estorna, o servidor DEVE retirar do saldo
  promocional da modalidade o menor entre o saldo atual e o valor do bônus (sem negativar), fazer o
  rollover do bônus deixar de valer, marcar o bônus como Estornado com autor, data e motivo e
  registrar a linha "Estorno" no extrato do cliente com o autor e o motivo. Bônus em outra
  situação DEVE ser recusado com "Este bônus não pode mais ser estornado.".
- **FR-028**: Os dois estornos NÃO DEVEM apagar nem alterar apostas (diferença em relação ao
  antigo), NÃO DEVEM mexer no saldo real e NÃO DEVEM retirar duas vezes o mesmo bônus: o estorno
  geral pula os bônus já estornados individualmente e os já convertidos, expirados ou excluídos
  pelo cliente (retira de cada cliente só o que ainda está em andamento ou cumprido, até o saldo
  promocional atual), mudando o estorno geral atual, que retirava o total recebido sem olhar a
  situação de cada bônus. Bônus convertido é corrigido, se preciso, pelo ajuste manual de saldo
  da spec 019. Ações simultâneas sobre o mesmo bônus (estorno, conversão, exclusão pelo
  cliente, expiração) passam uma de cada vez.
- **FR-029**: Os estornos DEVEM ser só de Admin e Supervisor (`clientes_promocoes.estornar`, já
  restrita), dentro da rede (FR-002).

**Permissões**

- **FR-030**: As permissões continuam as atuais: `clientes_promocoes.gerenciar` (ver a tela, as duas
  abas, cadastrar, editar, ativar/desativar e excluir) e `clientes_promocoes.estornar` (os dois
  estornos). Não há permissão nova. Padrão por função como hoje: Admin e Supervisor as duas, Gerente
  só gerenciar, Vendedor nenhuma. As permissões e o alcance DEVEM ser aplicados no servidor em toda
  consulta e ação.

**Visual, responsividade e padrões do painel**

- **FR-031**: A tela DEVE seguir os padrões do painel (specs 014 a 019 e constituição): cores do tema,
  cabeçalho do painel, abas do painel, cartões de valor, filtro de período já usado no painel,
  painel de filtros com etiquetas, cabeçalho padrão nos modais (`ModalHeader`), campos com rótulo
  em cima, campo de valor em reais, paginação e menu de três pontinhos nas ações.
- **FR-032**: No celular (até 900 px), as linhas DEVEM virar cartões (Promoções: nome, ganho e
  situação; Bônus recebidos: cliente, valor e quanto falta) com o restante ao expandir; o detalhe
  e o modal ocupam a tela toda; os filtros abrem num painel próprio; nada pode exigir rolagem
  horizontal.
- **FR-033**: Cada área (cartões, lista, detalhe) DEVE ter estados de carregando (esqueleto), vazio
  (com orientação) e erro com "tentar novamente", sem travar as outras.
- **FR-034**: Toda ação DEVE dar retorno imediato (botão com carregando e sem clique duplo,
  confirmação de sucesso ou mensagem do servidor) e atualizar a lista, os cartões e o detalhe sem
  recarregar a página nem perder os filtros e a página.

### Key Entities

- **Promoção**: promoção já existente (`clientes_promocoes`, specs 002, 007 e 012), vista com os
  campos, a situação calculada, o afiliado e os números de uso (calculados a cada consulta). A rede
  dela vem do afiliado e do gerente responsável do afiliado.
- **Bônus recebido**: cada crédito de uma promoção a um cliente, com o rollover dele (spec 007:
  exigido, apostado, prazo, cumprido, convertido, expirado, excluído). Ganha o registro do estorno
  individual (autor, data, motivo e valor retirado).
- **Estorno da promoção**: o registro atual (situação, motivo, autor, início, conclusão, clientes
  processados e valor), agora com a senha conferida antes de começar.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Um usuário cadastra uma promoção de primeiro depósito completa em menos de 2 minutos a
  partir do menu.
- **SC-002**: Em 100% das conferências manuais, os números de uso de uma promoção batem com a soma
  dos bônus recebidos dela e com os extratos dos clientes.
- **SC-003**: Um atendente encontra o bônus de um cliente e vê quanto falta apostar em até 3 toques a
  partir da tela Bônus.
- **SC-004**: Em 100% dos testes, um estorno com senha errada não muda nada, nenhum bônus é retirado
  duas vezes e nenhuma aposta é apagada ou alterada pelo estorno.
- **SC-005**: Nenhuma ação ou aba aparece para quem não tem a permissão dela, todo acesso direto sem
  permissão é recusado pelo servidor, e em nenhum teste um Supervisor ou Gerente vê promoção de
  afiliado de fora da rede dele (ou bônus de cliente de fora da rede), nem cadastra, altera ou
  estorna promoção geral ou de fora da rede.
- **SC-006**: Um gestor do sistema antigo encontra na tela nova cada informação e ação da "Lista de
  bônus" (abas Todos e Ativos, cadastrar, atualizar e reverter).
- **SC-007**: O estorno de uma promoção recebida por 10.000 clientes termina sem travar o painel nem
  o site, com o progresso visível na tela.
- **SC-008**: A tela funciona no celular (360 px de largura) sem rolagem horizontal e com todos os
  alvos de toque com pelo menos 40 px; abrir a tela, trocar de aba ou de filtro mostra a primeira
  página em até 2 segundos numa conexão comum.

## Assumptions

- O cadastro, a edição, as travas, o estorno geral em segundo plano, o crédito dos bônus (cadastro,
  depósito e indicação), o rollover, a expiração e o converter/excluir pelo cliente já existem
  (specs 002, 004, 007, 011 e 012); esta spec os mostra e chama, mudando o alcance por rede, a senha
  no estorno, o estorno geral por situação do bônus (FR-028), a exclusão (só não aplicada), a
  sobreposição por afiliado, o nome único e o fim no passado, e acrescentando os números de uso, a aba Bônus recebidos e o estorno individual.
- O nome único (FR-016) vale para as promoções novas e editadas; promoções já gravadas com nomes
  repetidos continuam como estão até serem editadas.
- O alcance usa a mesma rede da spec 019 (afiliado → gerente responsável), sem coluna nova de autor
  na promoção.
- O estorno individual de um bônus já cumprido retira o saldo promocional que ainda houver (o valor
  liberado não convertido), até o valor do bônus.
- Não há exportação em planilha nem notificação nesta tela (o antigo não tinha).
- Datas no fuso do sistema (Brasília).
- Não há dados fake nesta spec.
- Sem testes automatizados (constituição); a validação é manual pelo quickstart da spec.
