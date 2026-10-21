# Feature Specification: Painel de controle — base do painel e Dashboard

**Feature Branch**: `014-painel-dashboard`

**Created**: 2026-10-20

**Status**: Draft

**Input**: User description: "Painel de controle — base do painel e Dashboard (primeira tela do
painel). O backend do painel existe (specs 001–013), mas o frontend do painel é só o botão
'Vincular ao Mercado Pago' em /painel. Esta spec cria a base mínima do painel e a Dashboard, com as
mesmas seções, informações e regras por nível da dashboard do sistema antigo (wssports.bet:
screens/adm/dashboard e DashboardController), corrigidas, refatoradas e otimizadas, e com gráficos.
Base mínima do painel (layout, menu por permissão, login que leva Admin, Supervisor e Gerente
direto ao painel e mantém o Vendedor na tela de apostas, com acesso ao painel pelo nome); alcance
dos dados por nível igual ao antigo; uma permissão por seção da Dashboard; seções Apurado do dia,
Situação dos jogos, Apostas, Combinação de risco, Apostas por um jogo, Vendedores, Jogos mais
apostados, Opções mais apostadas e a nova Caixa online; lançamentos, retiradas e comissão do
gerente com dados fake até a spec de caixa; gráficos com Chart.js; seções independentes, em
paralelo, com cache curto; o botão do Mercado Pago sai do frontend."

## Clarifications

### Session 2026-10-19/20 (decisões tomadas antes do `/speckit-specify`)

- Q: Como o Supervisor vê "só o que o Admin permitir"? → A: Uma permissão por seção da Dashboard,
  no mesmo padrão das demais permissões (`Funcao.php`), gerenciada pela API de permissões de
  usuários que já existe. O Supervisor recebe todas ao ser cadastrado; o Admin retira as que quiser.
- Q: Quem vê a Combinação de risco? → A: Todos, menos o Vendedor. Cada nível vê só a sua rede
  (corrige o antigo, em que o Supervisor via a banca toda).
- Q: Lançamentos, Retiradas e Comissão do gerente ainda não existem no sistema novo. O que fazer?
  → A: Mostrar com dados fake (Princípio IX) até a spec de caixa.
- Q: O que substitui "Entradas em créditos" (crédito vendido pelo vendedor, que não existe no
  novo)? → A: Os depósitos online dos clientes, só para Admin e Supervisor, e eles entram no
  apurado geral desses níveis.
- Q: As apostas feitas pelo cliente no site (sem vendedor) entram no caixa dos vendedores? → A:
  Não. Ficam num caixa separado, na seção nova Caixa online.
- Q: Biblioteca de gráficos? → A: Chart.js.
- Q: O botão de pagamento/créditos do Gerente continua? → A: Não; foi retirado. O botão
  "Vincular ao Mercado Pago" do Admin também sai do frontend nesta spec (a tela do vínculo entra
  em outra spec; o backend do vínculo continua como está).
- Q: Para onde o usuário vai depois do login? → A: Admin, Supervisor e Gerente vão direto ao
  painel, na Dashboard. O Vendedor fica na tela de apostas e entra no painel clicando no nome dele.
- Q: A Dashboard pode mudar o visual do antigo (Princípio VIII)? → A: Sim. Aprovado pelo
  responsável: mesmas seções, no mesmo lugar e com as mesmas informações, com melhorias de UX/UI e
  gráficos em cada seção.
- Q: O aviso que abria para o Admin ao entrar na dashboard antiga continua? → A: Não; fica fora.
- Q: Período da Dashboard? → A: Só o dia atual, como no antigo; filtro de período fica fora.

### Session 2026-10-20 (`/speckit-specify`)

- Q: O padrão `<recurso>.<acao>` pede uma tabela como recurso, e a Dashboard não tem tabela. Como
  nomear as permissões de seção? → A: Recurso `dashboard` (ex.: `dashboard.ver_apurado`,
  `dashboard.ver_combinacao_risco`), uma permissão por seção. Desvio do padrão aprovado pelo
  responsável (Princípio VI).
- Q: O que é o Saldo líquido da Caixa online? → A: O resultado das apostas online: Entradas −
  Saídas − Encerramentos. Depósitos e Saques pagos aparecem como informação, fora dessa conta.

### Session 2026-10-20 (`/speckit-clarify`)

- Q: O Supervisor deve ver os depósitos online (no Apurado) e a Caixa online da banca toda, ou só
  dos clientes trazidos pelos afiliados da rede dele? → A: Banca toda. O caixa online é da banca, e
  o acesso é controlado pela permissão de cada seção (Admin decide).
- Q: Os valores fake de Lançamentos, Retiradas e do percentual da Comissão do gerente devem entrar
  na conta do Saldo líquido? → A: Os fake ficam zerados: os cartões aparecem com R$ 0,00 e o selo
  "em breve", sem números inventados; com isso não alteram o Saldo líquido até a spec de caixa.
- Q: A Dashboard deve se atualizar sozinha enquanto está aberta? → A: Sim, a cada 1 minuto, com a
  aba visível, em todas as seções (inclusive a Combinação de risco); o botão de atualizar continua.
- Q: Quando surgir uma combinação de risco nova na rede, onde o aviso deve aparecer e para quem? →
  A: Aviso na tela do painel (balão, destaque da linha nova e som) para quem estiver com a
  Dashboard aberta, só das combinações da sua rede; e mensagem no WhatsApp (CallMeBot) só para o
  Admin, com o telefone dele cadastrado nas configurações gerais do sistema.

### Session 2026-10-20 (`/speckit-analyze`, recomendações aceitas pelo responsável)

- Q: De onde vem o aviso de combinação nova? → A: Só de um agendador no servidor, a cada minuto. O
  carregamento ou a atualização da Dashboard (automática ou pelo botão) nunca gera aviso; o painel
  só mostra os avisos que o agendador registrou. Isso vale para o balão no painel e para o
  WhatsApp do Admin.
- Q: Quais avisos o Gerente e o Supervisor recebem no painel? → A: Os das combinações registradas
  pelo agendador (banca toda, 2 ou mais bilhetes) que têm ao menos um bilhete da rede dele; o
  Admin recebe todas.
- Q: Uma combinação de ontem que continua aberta hoje gera aviso de novo? → A: Não. Já avisada
  ontem ou hoje não avisa outra vez.
- Q: Canceladas conta pela data da aposta ou do cancelamento? → A: Pela data da aposta (criada no
  dia e cancelada), como no antigo.
- Q: Palpite de especial pendente conta em "Apostas por um jogo"? → A: Sim, conta como um jogo.
- Q: Quem pode retirar seções da Dashboard? → A: A regra geral da API de permissões: cada superior
  concede ou retira, na sua rede, as permissões que possui (Admin no Supervisor, Supervisor no
  Gerente, Gerente no Vendedor).

## Referências do sistema antigo *(Princípios VII e VIII)*

- Backend: `app/Http/Controllers/DashboardController.php` (`index`, `apuradoDia`,
  `apuradoDiaAdm`, `rankingConfrontos`, `modalidades`, `vendedores`, `apostas`,
  `apostasPifadas`, `confrontos`, `combinacaoDeRisco`); rotas `resource dashboard`,
  `GET apurado-dia` e `GET combinacao-de-risco` em `routes/api.php`.
- Telas de referência visual: `resources/js/screens/adm/dashboard` (index.js e styles.js),
  `resources/js/screens/adm/components/header` e `bar` (cabeçalho e barra de título do painel),
  `resources/js/modals/Combination` (bilhetes da combinação) e `resources/js/modals/ticket`
  (bilhete).

**Como funcionava**: uma única requisição montava toda a tela com cerca de 8 consultas agregadas
(apostas, palpites, confrontos, créditos e lançamentos), sem cache, sempre do dia atual. O alcance
seguia a hierarquia: Supervisor pela rede (gerentes dele e vendedores desses gerentes), Gerente
pelos vendedores dele, Vendedor pelo que ele vendeu. O Apurado do Admin e do Supervisor vinha vazio
e só era calculado ao clicar em atualizar, em laço por gerente. O Saldo líquido era entradas −
saídas − comissão do vendedor e, do Gerente para cima, menos a comissão do gerente (% do gerente
sobre o líquido, zero quando o líquido era negativo). A Combinação de risco criava uma tabela
temporária a cada clique.

**Problemas corrigidos por esta spec**: Apurado do Admin e do Supervisor vazio ao abrir;
Combinação de risco ignorando a hierarquia; "Situação dos jogos" contando palpites em vez de jogos;
consulta única e pesada sem cache; laço em PHP por gerente; tabela temporária na Combinação de
risco; SQL concatenado com dados do usuário (ranking de vendedores); valores em ponto flutuante;
restrição por nível só na tela (o backend devolvia dados que a tela escondia).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Entrar no painel e ver a Dashboard do seu nível (Priority: P1)

O usuário do painel (Admin, Supervisor, Gerente ou Vendedor) entra com login e senha. Admin,
Supervisor e Gerente caem direto no painel, na Dashboard. O Vendedor continua na tela de apostas e,
ao clicar no próprio nome no cabeçalho, entra no painel, na Dashboard. O painel tem cabeçalho com o
nome do usuário, menu lateral (gaveta no celular) com o item Dashboard, opção de voltar para a tela
de apostas e de sair.

**Why this priority**: sem a base do painel e o acesso, nenhuma seção pode ser vista.

**Independent Test**: entrar com cada um dos quatro níveis e conferir para onde cada um é levado,
o menu e as opções de voltar e sair.

**Acceptance Scenarios**:

1. **Given** um Admin, Supervisor ou Gerente ativo, **When** ele entra pelo modal de login,
   **Then** é levado direto ao painel, na Dashboard.
2. **Given** um Vendedor ativo, **When** ele entra pelo modal de login, **Then** continua na tela
   de apostas, com o nome dele no cabeçalho.
3. **Given** o Vendedor na tela de apostas, **When** clica no próprio nome, **Then** entra no
   painel, na Dashboard.
4. **Given** um usuário no painel, **When** escolhe voltar para a tela de apostas, **Then** vai
   para a tela principal ainda logado; **When** escolhe sair, **Then** a sessão é encerrada e ele
   volta à tela principal como visitante.
5. **Given** um visitante, cliente ou afiliado, **When** tenta abrir o endereço do painel,
   **Then** não vê o painel e é levado à tela principal.
6. **Given** o painel aberto no celular, **When** o usuário toca no ícone do menu, **Then** o menu
   abre como gaveta e fecha ao escolher um item, tocar fora ou voltar.
7. **Given** o Admin no painel, **When** a tela abre, **Then** o botão "Vincular ao Mercado Pago"
   não aparece mais.

---

### User Story 2 - Apurado do dia, Apostas e Situação dos jogos com o alcance do nível (Priority: P1)

Cada usuário vê, do dia atual, o apurado financeiro, o resumo das apostas e a situação dos jogos,
sempre limitado à sua rede: Admin a banca toda, Supervisor a rede dele, Gerente os vendedores dele
e Vendedor só o que ele vendeu.

**Why this priority**: é a informação que todos os níveis usam todo dia para acompanhar o caixa.

**Independent Test**: com apostas de vendedores de duas gerências diferentes, entrar com cada nível
e conferir que os números batem com as apostas da rede de cada um.

**Acceptance Scenarios**:

1. **Given** apostas do dia feitas por vendedores de dois gerentes, **When** um dos gerentes abre a
   Dashboard, **Then** o Apurado, as Apostas e a Situação dos jogos contam só as apostas dos
   vendedores dele.
2. **Given** um Vendedor, **When** abre a Dashboard, **Then** vê só o que ele vendeu, e o Apurado
   mostra também os limites geral, duplo e simples dele, com cadeado aberto quando o limite é maior
   que zero e fechado quando é zero.
3. **Given** um Admin, **When** abre a Dashboard, **Then** o Apurado já vem calculado (sem precisar
   clicar em atualizar), com os depósitos online somados às entradas e os saques pagos somados às
   saídas.
4. **Given** um Gerente ou nível acima, **When** abre o Apurado, **Then** vê a Comissão do gerente
   e os cartões de Lançamentos e Retiradas com R$ 0,00 e o selo "em breve" (dados fake zerados).
5. **Given** apostas com palpites no mesmo jogo, **When** a Situação dos jogos é exibida, **Then**
   cada jogo é contado uma única vez.
6. **Given** a seção Apostas, **When** é exibida, **Then** mostra Total, Abertas, Premiadas,
   Perdidas e Canceladas, cada uma com quantidade e valor, e um gráfico de rosca.
7. **Given** uma aposta feita pelo cliente no site (sem vendedor), **When** o Admin abre a
   Dashboard, **Then** ela não entra no Apurado, nas Apostas nem na Situação dos jogos do balcão.

---

### User Story 3 - Acompanhar o risco: Combinação de risco e Apostas por um jogo (Priority: P1)

Gerente, Supervisor e Admin acompanham as combinações de jogos e opções repetidas em vários
bilhetes abertos (risco de pagar vários prêmios juntos) e todos os níveis veem as apostas abertas
que dependem de um único jogo para fechar.

**Why this priority**: é o que permite agir antes de um prejuízo grande (bloquear jogo, limitar
venda).

**Independent Test**: criar três bilhetes abertos com os mesmos jogos e as mesmas opções e um
bilhete com só um jogo ainda em aberto; conferir as duas seções em cada nível.

**Acceptance Scenarios**:

1. **Given** três bilhetes abertos de ontem e hoje com os mesmos jogos e as mesmas opções, **When**
   o Gerente da rede abre a Combinação de risco, **Then** vê uma linha com os jogos, as opções, 3
   bilhetes, o valor apostado somado e o prêmio somado.
2. **Given** a mesma combinação feita por vendedores de outra gerência, **When** o Gerente abre a
   seção, **Then** ela não aparece para ele; **When** o Admin abre, **Then** aparece.
3. **Given** um Vendedor, **When** abre a Dashboard, **Then** a Combinação de risco não aparece e
   não pode ser consultada.
4. **Given** a Combinação de risco, **When** o usuário pede para ver os bilhetes de uma linha,
   **Then** vê a lista dos bilhetes e pode abrir cada um.
5. **Given** apostas abertas em que só falta um jogo para decidir, **When** a seção Apostas por um
   jogo abre, **Then** mostra até 50, da maior para a menor por prêmio, com apostador, prêmio e
   botão para ver o bilhete.
6. **Given** a Dashboard aberta com a aba visível, **When** passa 1 minuto, **Then** a Combinação
   de risco é recalculada sozinha, junto com as demais seções.
7. **Given** um Gerente com a Dashboard aberta, **When** o agendador registra uma combinação nova
   com bilhete de vendedor dele, **Then** em até 2 minutos aparece um balão de aviso com som e a
   linha fica destacada; combinações sem bilhete da rede dele não geram aviso para ele.
10. **Given** a Dashboard aberta, **When** o usuário recarrega a página ou toca em atualizar,
    **Then** nenhum aviso de combinação é gerado por isso; só aparecem os registrados pelo
    agendador depois da última vez que ele viu.
8. **Given** o telefone e a chave do WhatsApp do Admin cadastrados e o aviso ligado nas
   configurações gerais, **When** surge uma combinação nova em qualquer rede, **Then** o Admin
   recebe uma mensagem no WhatsApp com os jogos, as opções, o número de bilhetes e o prêmio somado,
   mesmo que ninguém esteja com o painel aberto.
9. **Given** uma combinação que já gerou aviso, **When** entra mais um bilhete nela, **Then** não é
   enviado um novo aviso.

---

### User Story 4 - Rankings: Vendedores, Jogos e Opções mais apostados (Priority: P2)

Gerente, Supervisor e Admin veem os 10 vendedores que mais venderam, os 10 jogos mais apostados e
as 10 opções mais apostadas do dia, na sua rede, em barras horizontais.

**Why this priority**: ajuda a gestão, mas não é necessária para operar o dia.

**Independent Test**: com vendas de vários vendedores, conferir a ordem e os valores dos três
rankings para um Gerente e para o Admin.

**Acceptance Scenarios**:

1. **Given** vendas de 12 vendedores da rede, **When** o Gerente abre Vendedores, **Then** vê os 10
   com mais entradas, com nome, quantidade e valor, do maior para o menor.
2. **Given** apostas em vários jogos, **When** abre Jogos mais apostados, **Then** vê os 10 jogos
   com mais apostas, com confronto, quantidade e valor.
3. **Given** apostas em várias opções, **When** abre Opções mais apostadas, **Then** vê as 10
   opções mais apostadas e quantas vezes cada uma foi apostada.
4. **Given** um Vendedor, **When** abre a Dashboard, **Then** nenhum dos três rankings aparece.

---

### User Story 5 - Caixa online (Priority: P2)

Admin e Supervisor (conforme permissão) acompanham, separado do caixa dos vendedores, o movimento
do dia das apostas feitas pelos clientes no site e o dinheiro que entrou e saiu por depósitos e
saques.

**Why this priority**: as apostas online são outro canal de dinheiro e precisam de acompanhamento
próprio, mas não bloqueiam a operação do balcão.

**Independent Test**: fazer um depósito, uma aposta online, um cash out e um saque pago no dia e
conferir os cartões da seção.

**Acceptance Scenarios**:

1. **Given** apostas online do dia, **When** o Admin abre a Caixa online, **Then** vê Entradas, Em
   aberto, Premiadas, Perdidas, Saídas (prêmios), Encerramentos (cash out), Depósitos, Saques pagos
   e Saldo líquido online, em cartões e gráfico.
2. **Given** um Gerente ou Vendedor, **When** abre a Dashboard, **Then** a Caixa online não
   aparece e não pode ser consultada.
3. **Given** apostas pagas com saldo de promoção, **When** a Caixa online é calculada, **Then**
   elas não contam como dinheiro de verdade nas entradas.

---

### User Story 6 - Seções liberadas por permissão (Priority: P2)

O Admin decide quais seções cada Supervisor vê. Gerente e Vendedor recebem as seções do seu nível.
O Vendedor nunca vê as seções de gestão, mesmo que receba a permissão.

**Why this priority**: controla o que cada nível enxerga, mas o padrão já atende a operação.

**Independent Test**: retirar de um Supervisor a permissão da Combinação de risco pela API de
permissões e conferir que a seção some para ele e o acesso direto é recusado.

**Acceptance Scenarios**:

1. **Given** um Supervisor recém-cadastrado, **When** abre a Dashboard, **Then** vê todas as seções.
2. **Given** o Admin retirou do Supervisor a permissão de uma seção, **When** o Supervisor abre a
   Dashboard, **Then** a seção não aparece e a consulta direta dela é recusada.
3. **Given** um Vendedor que recebeu a permissão da Combinação de risco, **When** tenta consultá-la,
   **Then** a consulta é recusada.
4. **Given** usuários cadastrados antes desta spec, **When** o seeder roda, **Then** cada um recebe
   as permissões de seção do seu nível.

---

### User Story 7 - Dashboard leve, clara e responsiva (Priority: P3)

A Dashboard abre rápido, cada seção carrega sozinha com esqueleto, mostra "sem dados" quando não há
movimento e "tentar novamente" quando falha, sem travar as outras, no celular e no computador, nos
temas claro e escuro.

**Why this priority**: melhora a experiência e corrige o peso da tela antiga; os números já estão
nas histórias anteriores.

**Independent Test**: abrir a Dashboard com rede lenta e com uma seção falhando de propósito;
conferir que as outras aparecem e que a com erro oferece tentar de novo.

**Acceptance Scenarios**:

1. **Given** a Dashboard abrindo, **When** as seções ainda carregam, **Then** cada uma mostra seu
   esqueleto e aparece assim que fica pronta, independente das outras.
2. **Given** uma seção que falhou, **When** o usuário toca em "tentar novamente", **Then** só ela é
   consultada de novo.
3. **Given** um dia sem movimento, **When** a Dashboard abre, **Then** cada seção mostra a mensagem
   de sem dados, sem gráfico vazio quebrado.
4. **Given** o usuário abriu a Dashboard há menos de um minuto, **When** abre de novo, **Then** os
   dados vêm do cache; **When** toca em atualizar numa seção, **Then** ela é recalculada na hora.
5. **Given** um visitante na tela principal, **When** a tela carrega, **Then** os arquivos dos
   gráficos não são baixados.

---

### Edge Cases

- Vendedor sem configurações de limite cadastradas: os limites aparecem como os padrões das
  configurações do vendedor.
- Gerente sem vendedores ou Supervisor sem gerentes: todas as seções mostram "sem dados".
- Vendedor, gerente ou supervisor inativo ou excluído: as vendas já feitas por ele continuam
  contando na rede do superior no dia.
- Usuário trocado de gerente durante o dia: conta na rede do gerente atual (a hierarquia é lida no
  momento da consulta, como no antigo).
- Aposta cancelada: não entra nas entradas; aparece só em Canceladas.
- Aposta pendente, em análise, recusada ou expirada: não entra em nenhuma seção.
- Aposta devolvida (todos os palpites anulados): o valor devolvido conta nas saídas.
- Combinação com bilhetes de valores diferentes: valor e prêmio são somados; a linha só aparece com
  2 ou mais bilhetes.
- Palpites cancelados (spec 004): não contam na combinação, nos rankings nem na Situação dos jogos.
- Jogo bloqueado: na Situação dos jogos conta como Aguardando; em Apostas por um jogo continua em
  aberto.
- Virada do dia: o dia atual segue o horário de Brasília.
- Sessão expirada durante o uso do painel: o usuário é levado ao login como no restante do site.
- Permissão retirada com a Dashboard aberta: a próxima consulta da seção é recusada e a seção
  some ao recarregar.
- Várias combinações novas no mesmo minuto: um aviso por combinação na tela; no WhatsApp, uma
  mensagem só com todas elas, para não lotar o celular do Admin.
- Combinação que deixa de existir (bilhete cancelado ou jogo encerrado) e volta a aparecer no mesmo
  período: não gera novo aviso.
- Combinação avisada ontem que continua aberta hoje: não gera novo aviso.
- Usuário que abre a Dashboard depois de várias combinações novas: vê só as registradas depois da
  última que já viu; na primeira abertura do dia, nenhuma (só a lista da seção).
- CallMeBot fora do ar ou chave inválida: a Dashboard funciona normalmente; o envio é tentado de
  novo até 3 vezes e depois desiste, registrando a falha.

## Requirements *(mandatory)*

### Functional Requirements

**Base do painel e acesso**

- **FR-001**: O sistema DEVE ter a área do painel em `/painel`, com layout próprio: cabeçalho com o
  nome do usuário, menu lateral e área de conteúdo; no celular o menu vira gaveta.
- **FR-002**: O menu DEVE ser montado pelas permissões do usuário. Nesta spec, o único item é
  Dashboard, exibido a quem tiver ao menos uma permissão de seção.
- **FR-003**: Após o login de usuário pelo modal de login atual, Admin, Supervisor e Gerente DEVEM
  ser levados ao painel, na Dashboard; o Vendedor DEVE continuar na tela de apostas.
- **FR-004**: Na tela de apostas, o nome do usuário logado no cabeçalho DEVE levar ao painel, na
  Dashboard.
- **FR-005**: O painel DEVE ter as opções de voltar para a tela de apostas (mantendo a sessão) e de
  sair (encerrando a sessão).
- **FR-006**: Visitantes, clientes e afiliados NÃO DEVEM acessar o painel; quem tentar é levado à
  tela principal.
- **FR-007**: O botão "Vincular ao Mercado Pago" DEVE ser removido do frontend; o backend do
  vínculo não é alterado.

**Alcance e permissões**

- **FR-008**: Todas as seções DEVEM respeitar o alcance do nível, aplicado no servidor: Admin, a
  banca toda; Supervisor, os gerentes dele e os vendedores desses gerentes; Gerente, os vendedores
  dele; Vendedor, só ele. Exceção: os Depósitos online e os saques pagos do Apurado e a Caixa
  online não têm rede (o cliente online não tem vendedor) e são sempre da banca toda, para o Admin
  e para o Supervisor.
- **FR-009**: O sistema DEVE ter uma permissão por seção da Dashboard (Apurado do dia, Situação dos
  jogos, Apostas, Combinação de risco, Apostas por um jogo, Vendedores, Jogos mais apostados,
  Opções mais apostadas e Caixa online), no mesmo padrão das permissões existentes (lista, padrão
  por função e regra de uso por função), gerenciadas pela API de permissões de usuários existente.
  Nome das permissões, com o recurso `dashboard`: `dashboard.ver_apurado`,
  `dashboard.ver_situacao_jogos`, `dashboard.ver_apostas`, `dashboard.ver_combinacao_risco`,
  `dashboard.ver_apostas_um_jogo`, `dashboard.ver_vendedores`, `dashboard.ver_jogos_mais_apostados`,
  `dashboard.ver_opcoes_mais_apostadas` e `dashboard.ver_caixa_online`.
- **FR-010**: Admin DEVE ter todas as permissões de seção. Supervisor DEVE receber todas ao ser
  cadastrado e pode ter qualquer uma retirada pelo Admin. Gerente DEVE receber Apurado, Situação
  dos jogos, Apostas, Combinação de risco, Apostas por um jogo, Vendedores, Jogos mais apostados e
  Opções mais apostadas. Vendedor DEVE receber Apurado, Situação dos jogos, Apostas e Apostas por
  um jogo.
- **FR-011**: O Vendedor NÃO DEVE poder usar Combinação de risco, Vendedores, Jogos mais
  apostados, Opções mais apostadas nem Caixa online, mesmo que receba a permissão; o Gerente NÃO
  DEVE poder usar Caixa online.
- **FR-012**: Uma seção sem permissão NÃO DEVE aparecer na tela, e a consulta direta dela DEVE ser
  recusada.
- **FR-013**: O seeder DEVE dar as permissões de seção do seu nível aos usuários já cadastrados,
  como nas specs anteriores.

**Regras gerais dos números**

- **FR-014**: O período de todas as seções é o dia atual no horário de Brasília, exceto
  Combinação de risco (ontem e hoje) e Apostas por um jogo (abertas de ontem e hoje), como no
  antigo.
- **FR-015**: As seções do balcão (Apurado, Situação dos jogos, Apostas, Combinação de risco,
  Apostas por um jogo e rankings) DEVEM considerar só apostas feitas por vendedor; as apostas feitas
  pelo cliente no site entram só na Caixa online.
- **FR-016**: Entram nos números só apostas ativas (confirmadas); canceladas aparecem só na
  contagem de Canceladas (apostas criadas no dia e canceladas, pela data da aposta, como no
  antigo); pendentes, em análise, recusadas e expiradas não entram.
- **FR-017**: Todos os valores em dinheiro DEVEM ser calculados sem ponto flutuante e exibidos em
  reais formatados.

**4.1 Apurado do dia**

- **FR-018**: O Apurado DEVE mostrar, para todos os níveis: Entradas (valor das apostas), Em aberto,
  Premiadas e Perdidas (valor apostado de cada grupo), Saídas (prêmios das premiadas e valores
  devolvidos) e Comissão do vendedor.
- **FR-019**: Só para o Vendedor, o Apurado DEVE mostrar os limites geral, duplo e simples das
  configurações dele, com cadeado aberto quando o limite for maior que zero e fechado quando for
  zero.
- **FR-020**: Para Admin e Supervisor, o Apurado DEVE mostrar Depósitos online (depósitos de
  clientes confirmados no dia), somados às Entradas, e os saques pagos no dia somados às Saídas,
  como faziam os créditos no antigo.
- **FR-021**: Lançamentos e Retiradas DEVEM ser exibidos para todos os níveis com dados fake
  zerados (R$ 0,00) e o selo "em breve", sem números inventados, até a spec de caixa.
- **FR-022**: Do Gerente para cima, o Apurado DEVE mostrar a Comissão do gerente: percentual do
  gerente sobre o líquido dos vendedores dele (entradas − saídas − comissão do vendedor), zero
  quando esse líquido for negativo ou zero, somada por gerente da rede. O percentual é dado fake
  zerado (0%) até a spec de caixa, então o cartão mostra R$ 0,00 com o selo "em breve".

> Alterado pela spec 017 (FR-034): lançamentos, retiradas e o percentual da comissão do gerente
> passaram a ser reais (suprimentos e retiradas do caixa no dia e o percentual de cada gerente,
> definido na tela Caixa); sem o selo "em breve". Os dados fake correspondentes foram removidos.
- **FR-023**: O Saldo líquido DEVE ser: Entradas − Saídas − Comissão do vendedor − Comissão do
  gerente + Lançamentos − Retiradas (para o Vendedor, sem comissão do gerente).
- **FR-024**: O Apurado DEVE ter um gráfico de barras comparando Entradas, Saídas, Comissões e
  Saldo líquido, e botão de atualizar; DEVE vir calculado ao abrir a Dashboard, para todos os
  níveis.

**4.2 Situação dos jogos**

- **FR-025**: A seção DEVE mostrar Total, Aguardando, Encerrados, Adiados e Cancelados dos jogos
  presentes nas apostas do dia, contando cada jogo uma única vez, com gráfico de rosca. Jogos
  bloqueados contam como Aguardando.

**4.3 Apostas**

- **FR-026**: A seção DEVE mostrar Total, Abertas, Premiadas, Perdidas e Canceladas, cada uma com
  quantidade e valor, com gráfico de rosca.

**4.4 Combinação de risco**

- **FR-027**: A seção DEVE listar as combinações de jogos e opções iguais presentes em 2 ou mais
  apostas abertas de ontem e hoje, com jogos ainda não encerrados, mostrando jogos, opções, número
  de bilhetes, valor apostado somado e prêmio somado, ordenadas pelo maior prêmio, com barra
  proporcional ao prêmio.
- **FR-028**: Cada linha DEVE permitir ver os bilhetes da combinação e abrir cada bilhete.
- **FR-029**: A seção DEVE ser atualizada como as demais (FR-044) e DEVE respeitar o alcance do
  nível (FR-008).

**4.5 Apostas por um jogo**

- **FR-030**: A seção DEVE listar até 50 apostas abertas de ontem e hoje que têm exatamente um jogo
  ainda não decidido (aguardando ou bloqueado; palpite de especial pendente conta como um jogo), da maior para a menor por prêmio, com apostador,
  prêmio, barra proporcional e botão para ver o bilhete.

**4.6 a 4.8 Rankings**

- **FR-031**: Vendedores DEVE mostrar os 10 vendedores da rede com mais entradas no dia (nome,
  quantidade de apostas e valor), em barras horizontais.
- **FR-032**: Jogos mais apostados DEVE mostrar os 10 jogos com mais apostas no dia (confronto,
  quantidade e valor), em barras horizontais.
- **FR-033**: Opções mais apostadas DEVE mostrar as 10 opções mais apostadas no dia e quantas vezes
  cada uma foi apostada, com o nome da opção legível, em barras horizontais.

**4.9 Caixa online**

- **FR-034**: A seção DEVE mostrar, das apostas feitas pelos clientes no site no dia: Entradas, Em
  aberto, Premiadas, Perdidas, Saídas (prêmios), Encerramentos (valor pago em cash out), além de
  Depósitos confirmados e Saques pagos no dia, em cartões e gráfico.
- **FR-035**: Apostas pagas com saldo de promoção NÃO DEVEM contar como dinheiro de verdade nas
  Entradas da Caixa online.
- **FR-036**: A seção DEVE mostrar o Saldo líquido online, que é o resultado das apostas online do
  dia: Entradas − Saídas − Encerramentos. Depósitos e Saques pagos são exibidos como informação e
  não entram nessa conta.

**UX/UI**

- **FR-037**: As seções DEVEM ficar na mesma ordem e com as mesmas informações do antigo, com
  cartões, ícones e gráficos.
- **FR-038**: Cada seção DEVE carregar de forma independente, com esqueleto enquanto carrega,
  mensagem de sem dados e mensagem de erro com "tentar novamente", sem travar as demais.
- **FR-039**: A Dashboard DEVE ser responsiva, com prioridade para o celular, e seguir o tema do
  sistema (cor principal e fundo claro ou escuro).

**Desempenho**

- **FR-040**: Cada seção DEVE ter sua própria consulta, e as seções DEVEM ser pedidas em paralelo.
- **FR-041**: Os números DEVEM ser calculados no banco, de forma agregada, sem laços por usuário, e
  só sobre a rede do nível.
- **FR-042**: O resultado de cada seção DEVE ficar em cache por usuário por cerca de 60 segundos;
  o botão de atualizar DEVE ignorar o cache.
- **FR-043**: Os arquivos dos gráficos DEVEM ser carregados só no painel, sem aumentar o peso da
  tela principal.
- **FR-044**: Com a Dashboard aberta e a aba visível, todas as seções DEVEM se atualizar sozinhas a
  cada 1 minuto, sem piscar a tela (os dados antigos ficam até chegarem os novos); com a aba
  oculta a atualização para e volta ao reabrir a aba. O botão de atualizar de cada seção continua.

**Aviso de combinação de risco nova**

- **FR-045**: Uma combinação é nova quando um conjunto de jogos e opções passa a estar em 2 ou mais
  apostas abertas (FR-027) da banca toda pela primeira vez. Cada combinação gera aviso uma única
  vez, mesmo que continue aberta de um dia para o outro; novos bilhetes na mesma combinação não
  geram outro aviso.
- **FR-046**: Um agendador no servidor DEVE verificar a cada minuto as combinações novas (banca
  toda, sem depender de alguém com o painel aberto) e registrar cada uma com os bilhetes e os
  vendedores dela. Só esse agendador gera avisos: carregar ou atualizar a Dashboard (automático ou
  pelo botão) NUNCA gera aviso.
- **FR-047**: Na Dashboard aberta, o painel DEVE consultar a cada minuto os avisos registrados pelo
  agendador depois do último que o usuário já viu e, para cada um, mostrar um balão com som e
  destacar a linha na Combinação de risco. O Admin recebe todos; Supervisor e Gerente, só os que
  têm ao menos um bilhete de vendedor da rede dele; quem não pode ver a Combinação de risco não
  recebe. Na primeira abertura do dia, os avisos anteriores ficam marcados como vistos, sem balão.
  O usuário pode silenciar o som no painel.
- **FR-047a**: A cada combinação nova registrada, o agendador DEVE enviar ao Admin uma mensagem no
  WhatsApp pelo CallMeBot com os jogos, as opções, o número de bilhetes, o valor apostado e o
  prêmio somado (várias no mesmo minuto vão numa mensagem só).
- **FR-048**: As configurações gerais do sistema DEVEM ter o telefone do WhatsApp do Admin (com
  DDI), a chave do CallMeBot e a opção de ligar ou desligar o aviso, editáveis por quem tem a
  permissão de configurações (`configuracoes.editar`). Sem telefone, sem chave ou com o aviso
  desligado, nenhuma mensagem é enviada; a chave nunca é devolvida por inteiro nas consultas.
- **FR-049**: Uma falha no envio pelo WhatsApp NÃO DEVE afetar a Dashboard; ela fica registrada no
  log e o envio é tentado de novo na verificação seguinte, até 3 vezes.

### Dados fake desta spec *(Princípio IX)*

| Dado fake | Onde aparece | Substituído por |
|---|---|---|
| Lançamentos do dia (suprimentos do caixa), sempre R$ 0,00 | Apurado do dia | spec de caixa |
| Retiradas do dia, sempre R$ 0,00 | Apurado do dia | spec de caixa |
| Percentual de comissão do gerente, sempre 0% | Comissão do gerente no Apurado | spec de caixa |

### Key Entities

- **Usuário do painel**: Admin, Supervisor, Gerente ou Vendedor; o superior define a rede.
- **Rede**: conjunto de vendedores alcançados por um usuário, conforme o nível.
- **Seção da Dashboard**: bloco de informação com permissão própria, alcance pelo nível e
  carregamento independente.
- **Aposta do balcão**: aposta feita ou validada por vendedor; base do Apurado, Apostas, Situação
  dos jogos, risco e rankings.
- **Aposta online**: aposta feita pelo cliente no site, sem vendedor; base da Caixa online.
- **Configuração do vendedor**: limites geral, duplo e simples e comissão do vendedor.
- **Depósito e Saque**: movimentos de dinheiro dos clientes, usados no Apurado do Admin e do
  Supervisor e na Caixa online.
- **Aviso de combinação**: registro feito pelo agendador de cada combinação de risco nova (jogos e
  opções que a identificam, bilhetes e vendedores, quando surgiu, se a mensagem ao Admin foi
  enviada e quantas tentativas); é a única origem dos avisos do painel e do WhatsApp e impede
  avisar duas vezes.
- **Configurações gerais (aviso por WhatsApp)**: telefone do Admin, chave do CallMeBot e opção de
  ligar ou desligar o aviso.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Admin, Supervisor e Gerente chegam à Dashboard em um passo após o login; o Vendedor
  chega em um toque a partir da tela de apostas.
- **SC-002**: Em 100% das conferências manuais, cada nível vê só os números da sua rede, inclusive
  na Combinação de risco.
- **SC-003**: Com o volume de um dia movimentado (ex.: 5.000 apostas no dia), as seções principais
  (Apurado, Apostas e Situação dos jogos) aparecem em até 2 segundos numa conexão móvel comum.
- **SC-004**: Uma reabertura da Dashboard dentro de 1 minuto exibe as seções em até 1 segundo.
- **SC-005**: A falha de uma seção não impede as outras de aparecer em nenhum dos testes.
- **SC-006**: Os números de cada seção batem com a soma manual das apostas do dia da rede em 100%
  dos casos conferidos.
- **SC-007**: O peso da tela principal (visitante) não aumenta com a chegada dos gráficos.
- **SC-008**: Um vendedor consegue ler o Saldo líquido e os limites no celular sem rolar
  horizontalmente.
- **SC-009**: Uma combinação de risco nova chega ao WhatsApp do Admin e aparece no painel aberto em
  até 2 minutos, sem aviso repetido para a mesma combinação e sem aviso gerado por recarregar a
  página.

## Assumptions

- A hierarquia de usuários, a API de permissões, a sessão de usuário e o modal de login atuais
  (specs 001 e 005) são reutilizados.
- "Aposta do balcão" é a aposta com vendedor; "aposta online" é a do cliente sem vendedor.
- As opções de voltar, sair, menu e cabeçalho seguem o visual do painel antigo
  (`screens/adm/components/header` e `bar`), com as melhorias aprovadas.
- Ver bilhete usa a visualização de bilhete que o site já tem.
- A comissão do vendedor é a já gravada em cada aposta (spec 004).
- Os limites do vendedor mostrados são os valores configurados, como no antigo.
- O seeder que dá as permissões é o mesmo usado nas specs anteriores, sem tirar permissões que os
  usuários já tenham.
- Esta spec não cria tabelas de caixa; lançamentos, retiradas e o % do gerente são fake.
- O bloqueio de `/painel` para quem não é usuário do painel é feito na página; os dados ficam
  protegidos porque toda consulta da Dashboard exige o token e a permissão no servidor.
- A validação de desempenho (SC-003) usa uma cópia do banco de teste ou apostas geradas à mão até
  cerca de 5.000 no dia.
- O telefone e a chave do WhatsApp do Admin são configurados pela API de configurações nesta spec;
  a tela de configurações gerais do painel entra na spec das configurações.
- O CallMeBot é o mesmo serviço já usado no aviso do deploy (spec 013), mas com telefone e chave
  próprios, guardados nas configurações do sistema e não nos segredos do GitHub.
