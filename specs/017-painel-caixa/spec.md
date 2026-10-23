# Feature Specification: Painel — Caixa (vendedores, gerentes, clientes, lançamentos e prestação de contas)

**Feature Branch**: `017-painel-caixa`

**Created**: 2026-10-21

**Status**: Draft

**Input**: User description: "Spec 017: tela de Caixa no painel (backend + frontend). O backend NÃO
existe: hoje só há a Caixa online e o Apurado do dia na Dashboard (spec 014), com lançamentos,
retiradas e o percentual da comissão do gerente como dados fake zerados, que esta spec substitui por
dados reais e remove. Trazer do antigo (CaixaController e telas screens/adm/financial) o caixa por
nível com período, lista de vendedores com saldo de caixa, abas Todas/Pré-jogo/Ao vivo, Caixa
clientes com período, lançamentos (suprimento e retirada), prestação de contas com senha e em lote
pelo gerente, histórico de transações, ajuste de limites, comissão do gerente configurável, gráfico
por quantidade de palpites e exportação em Excel. Corrigir os problemas do antigo (SQL concatenado,
cálculo duplicado, ponto flutuante, permissões não aplicadas). Tela 'Caixa' no painel no padrão das
specs 014, 015 e 016, responsiva com prioridade ao celular."

## Clarifications

### Session 2026-10-21 (decisões tomadas antes do `/speckit-specify`)

- Q: Já existe backend de caixa? → A: Não. Só existem as somas do dia da Dashboard (Apurado e Caixa
  online, spec 014). Lançamentos, retiradas, prestações de contas e o percentual da comissão do
  gerente são novos nesta spec, e os dados fake da Dashboard (lançamentos, retiradas e percentual
  da comissão do gerente) são substituídos e removidos na mesma entrega (Princípio IX).
- Q: O "Caixa Clientes" e o "Caixa cadastros" do antigo entram? → A: Sim, juntos, como **Caixa
  clientes**: o caixa das apostas online dos clientes do site com filtro de período. Na Dashboard a
  Caixa online mostra só o apurado do dia; aqui o responsável escolhe o período.
- Q: A exportação em planilha pode usar uma biblioteca nova? → A: Sim. O responsável autorizou
  adicionar a mesma biblioteca de planilhas usada no antigo (a justificativa fica no plano, como
  pede a constituição).
- Q: O atalho "ver apostas do vendedor" do antigo entra? → A: Não por enquanto. O painel do v2 ainda
  não tem lista de apostas; o atalho fica fora desta spec. Alterado pela spec 018: o atalho "Ver
  apostas" passou a existir no menu da linha do vendedor.
- Q: Acumuladão e "créditos" do vendedor (abas do antigo) entram? → A: Não. Não existem no v2.
- Q: O visual pode mudar em relação ao antigo (Princípio VIII)? → A: Sim. O responsável pediu UI e UX
  bem aplicadas no padrão do painel novo (specs 014, 015 e 016). As diferenças aprovadas estão em
  "Referências do sistema antigo".

### Session 2026-10-21 (respostas do responsável às perguntas do `/speckit-specify`)

- Q: Em qual caixa entram o prêmio ou a devolução de uma aposta feita antes da prestação de contas
  e decidida depois? → A: No período seguinte, pela data em que a aposta foi decidida. A prestação
  pode ser feita a qualquer hora, com apostas em aberto; o que for pago depois entra no próximo
  caixa do vendedor (corrige o antigo, em que esse prêmio não entrava em caixa nenhum).
- Q: Quando o valor recebido na prestação é diferente do apurado, o que acontece com a diferença?
  → A: Fica como saldo do vendedor para a próxima prestação. O saldo de caixa só zera quando o valor
  recebido é igual ao apurado (no antigo zerava sempre).
- Q: O movimento do cassino e os bônus entram no Caixa clientes? → A: Sim, como no "Caixa cadastros"
  do antigo: o Caixa clientes mostra também o cassino dos clientes (entradas, saídas e resultado das
  rodadas) e os bônus (concedidos e convertidos em saldo real).

### Session 2026-10-21 (`/speckit-clarify`)

- Q: Quando alguém registra uma prestação de contas errada, deve existir uma forma de desfazê-la?
  → A: Sim, só a última prestação de cada vendedor, por quem a fez ou um superior dele com a
  permissão de prestar contas, com senha e motivo; a prestação é excluída logicamente (guarda quem
  desfez, quando e o motivo) e os lançamentos dela voltam a ficar abertos (FR-022a).
- Q: A comissão do gerente é só um valor mostrado no caixa, ou o sistema também registra que ela foi
  paga ao gerente? → A: Só mostrada, como no antigo: aparece nos cartões e nas linhas e desconta do
  líquido da rede; o pagamento ao gerente acontece fora do sistema (FR-007).
- Q: Num período de vários dias, a comissão do gerente é calculada sobre o líquido do período
  inteiro ou somando a de cada dia? → A: Sobre o líquido do período inteiro, como no antigo: um dia
  de prejuízo abate o lucro dos outros; a tela avisa que a soma de dias separados pode não bater com
  o período (FR-007).
- Q: O dinheiro que o gerente recebe nas prestações é repassado ao supervisor e ao Admin pelo
  sistema? → A: Não; o repasse fica fora do sistema. Para consulta, as linhas de gerente e de
  supervisor mostram "Saldo de caixa" (soma dos saldos de caixa dos vendedores abaixo) e "Recebido
  no período" (soma dos valores recebidos nas prestações do período) (FR-011).
- Q: O ajuste de limite do vendedor no Caixa deve só somar, ou também reduzir e definir um valor
  exato? → A: Somar ou subtrair: o campo aceita valor negativo, o limite nunca fica abaixo de zero e
  a tela mostra a prévia do novo valor; não há "definir valor exato" (FR-028).
- Q: Um vendedor desativado ou excluído que ainda tem saldo de caixa pode receber lançamentos e
  prestação de contas? → A: Pode prestar contas e lançar retiradas; suprimento é recusado; o caixa
  dele continua na lista enquanto houver saldo (FR-014a).

### Session 2026-10-22 (alterado pela spec 019)

- O Caixa clientes (totais, lista por cliente e planilha) passa a mostrar ao Supervisor só os
  clientes da rede dele (afiliado → gerente responsável); o Admin continua vendo a banca toda. A
  Caixa online da Dashboard não muda (spec 019, FR-003c).

## Referências do sistema antigo *(Princípios VII e VIII)*

- Backend: `app/Http/Controllers/CaixaController.php` (`caixaAdmin`, `caixaGerentes`,
  `caixaVendedores`, `getVendedores`, `caixaClientes`, `caixaBilhetes`, `lancamentos`,
  `prestarContas`, `deletarLancamentos`, `getLancamentos`, `excelAdmin`, `excelGerente`,
  `excelVendedor`, `graficos`), model `Lancamentos` e as travas `TravasSupervisor` /
  `TravasGerentes` (`ver_caixa`, `lancamentos_caixa`, `prestar_conta_caixa`, `comissao`).
- Telas: `screens/adm/financial/adm` ("Caixa Geral", `/adm/caixa-admin`), `manager` ("Caixa
  Gerentes", `/adm/caixa-gerentes`), `seller` ("Caixa Vendedores", `/adm/caixa-vendedores`),
  `client` ("Caixa Clientes", `/adm/caixa-clientes`), `online` ("Caixa cadastros",
  `/adm/caixa-cadastros`), `themes/app/screens/financial` (caixa do próprio vendedor) e os modais
  `modals/transactions` (Transações) e `modals/graphic` (Gráficos).

**Como funcionava**:

- **Período**: campos de/até obrigatórios em todas as telas; seletor de fuso (Brasília, Noronha,
  Amazonas, Rio Branco) na tela de vendedores.
- **Caixa Geral (Admin/Supervisor)**: cartões de totais (apostas abertas, premiadas e perdidas;
  entradas, entradas em aberto, perdidas e premiadas; comissão; comissão do gerente; saídas;
  lançamentos; retiradas; líquido), filtro por supervisor e gerente, lista de gerentes que abria os
  vendedores de cada um ("clique para carregar mais"), e planilha geral.
- **Caixa Gerentes**: uma linha por gerente (entradas, em aberto, perdidas, premiadas, comissão,
  comissão do gerente, saídas, líquido), com "Prestar contas" (de todos os vendedores do gerente) e
  "Baixar planilha".
- **Caixa Vendedores**: abas todos / ao vivo / créditos / acumuladão; cartões de totais quando um
  gerente estava escolhido; uma linha por vendedor (apostas, entradas, lançamentos, retiradas,
  limite geral/duplo/simples, comissão, saídas, líquido e saldo de caixa) e menu por linha:
  Adicionar limite, Prestar contas, Lançamentos, Transações, Baixar planilha e Gráficos.
- **Lançamentos**: SweetAlert com tipo (Crédito = suprimento, Débito = retirada), valor, data/hora e
  observação.
- **Prestar contas**: data final e senha do usuário; gravava um registro "Prestação de Contas" com o
  saldo do período desde a última prestação; se o saldo fosse negativo, criava antes, escondido, um
  "lançamento automático" de crédito para zerar.
- **Saldo de caixa**: entradas − saídas − comissão desde a última prestação, mais lançamentos, menos
  retiradas; sem prestação anterior, contava a partir de "1 ano atrás".
- **Transações**: lista dos lançamentos e prestações do vendedor, com excluir em qualquer um.
- **Gráficos**: pizza com a quantidade de apostas por número de palpites (1 a 10 e mais de 10).
- **Caixa Clientes / Caixa cadastros**: totais e lista por cliente (apostas, entradas, comissão,
  saídas, líquido) e o movimento online (depósitos, saques, bônus, rollover).
- **Problemas encontrados**: SQL montado por concatenação de texto; a mesma consulta copiada cerca
  de 15 vezes; contas em ponto flutuante; travas de permissão comentadas (ninguém era bloqueado);
  exclusão de lançamento sem regra, inclusive de prestações; prêmio de aposta de um período já
  prestado que saía depois não entrava em período nenhum; a aba "créditos" misturava números de
  apostas e de créditos.

**Diferenças aprovadas** (pedido do responsável):

- Uma tela só, "Caixa", com abas (Rede e Clientes) e navegação em camadas com trilha
  (Admin → Supervisor → Gerente → Vendedor), no lugar de cinco telas separadas.
- Atalhos de período (Hoje, Ontem, Esta semana, Este mês, Personalizado) e o fuso do sistema, sem
  seletor de fuso.
- Abas por tipo de aposta: Todas, Pré-jogo e Ao vivo (sem créditos e acumuladão).
- Modais próprios do painel (`ModalHeader`, campos com rótulo em cima) no lugar dos SweetAlert com
  HTML.
- Prestação de contas que mostra o resumo antes de confirmar e registra a diferença de forma
  explícita, sem "lançamento automático" escondido.
- Exclusão só de lançamentos ainda não prestados, com registro de quem excluiu.
- Comissão do gerente com percentual editável na própria tela.
- Visual do painel novo: tema (cor principal, fundo claro ou escuro), cartões (`StatCard`), abas do
  painel, listas do celular com as colunas principais e menu de ações de três pontinhos.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Acompanhar o caixa da rede no período (Priority: P1)

O usuário do painel abre "Caixa", escolhe o período (padrão: hoje) e vê os totais da rede dele em
cartões e a lista dos níveis abaixo (para o Admin, os supervisores; para o Supervisor, os gerentes
dele; para o Gerente, os vendedores dele), podendo descer de nível até o vendedor e voltar pela
trilha. O Vendedor vê só o próprio caixa.

**Why this priority**: é a consulta que todos os níveis fazem todo dia para saber quanto cada
vendedor vendeu, pagou e deve; sozinha já substitui as telas "Caixa Geral", "Caixa Gerentes" e
"Caixa Vendedores" para consulta.

**Independent Test**: com apostas de dois vendedores de gerentes diferentes, como Admin, abrir Caixa
em "Hoje", conferir que os cartões somam as duas redes, descer até um gerente e um vendedor e
conferir as colunas; como Gerente, conferir que só a rede dele aparece; como Vendedor, conferir que
só o próprio caixa aparece.

**Acceptance Scenarios**:

1. **Given** um Admin, **When** ele abre Caixa, **Then** vê o período "Hoje", os cartões de totais da
   banca (quantidade de apostas abertas, premiadas e perdidas; entradas; em aberto; premiadas;
   perdidas; comissão do vendedor; comissão do gerente; saídas; lançamentos; retiradas; líquido) e
   a lista de supervisores com os mesmos valores por linha.
2. **Given** a lista de um nível, **When** o usuário toca numa linha de supervisor ou gerente,
   **Then** a tela desce para os subordinados dele, os cartões passam a ser os daquele usuário e a
   trilha mostra o caminho (ex.: "Banca › Supervisor A › Gerente B"), com cada parte clicável.
3. **Given** a lista de vendedores, **When** ela aparece, **Then** cada linha mostra apostas,
   entradas, lançamentos, retiradas, limites (geral / duplo / simples), comissão, saídas, líquido e
   saldo de caixa, com busca por nome ou login e paginação.
4. **Given** um período escolhido nos atalhos (Hoje, Ontem, Esta semana, Este mês) ou em
   Personalizado (de/até), **When** o usuário aplica, **Then** cartões e listas são recalculados para
   aquele período, e o período fica visível no cabeçalho.
5. **Given** as abas Todas, Pré-jogo e Ao vivo, **When** o usuário troca de aba, **Then** os valores
   das apostas passam a considerar só aquele tipo, e lançamentos, retiradas e saldo de caixa (que
   não são por tipo) aparecem só em Todas.
6. **Given** um Vendedor, **When** ele abre Caixa, **Then** vê só os próprios cartões, os limites, o
   saldo de caixa, o histórico de transações e o gráfico, sem lista, sem trilha e sem ações de
   lançar ou prestar contas.
7. **Given** um líquido ou saldo positivo, negativo ou zero, **When** ele é exibido, **Then** aparece
   em verde, vermelho ou cinza, nos dois temas.

---

### User Story 2 - Prestar contas de um vendedor ou de um gerente (Priority: P1)

O gerente (ou um superior) recebe o dinheiro do vendedor e dá baixa: escolhe "Prestar contas" na
linha do vendedor, vê o resumo do período desde a última prestação até a data escolhida, informa o
valor recebido, confirma com a própria senha e o saldo de caixa do vendedor volta a zero (ou fica
com a diferença, se o valor recebido for diferente do apurado). Na linha
de um gerente, a prestação vale para todos os vendedores dele de uma vez.

**Why this priority**: é a operação de dinheiro do caixa; sem ela o saldo de caixa só cresce e o
caixa não fecha.

**Independent Test**: com um vendedor que vendeu R$ 100,00, pagou R$ 30,00 de prêmio e tem R$ 10,00
de comissão, prestar contas até hoje informando R$ 60,00 recebidos, conferir o saldo de caixa zerado,
a prestação no histórico e que um novo período começa a partir dela; repetir na linha do gerente com
dois vendedores e conferir as duas prestações gravadas juntas.

**Acceptance Scenarios**:

1. **Given** um vendedor com saldo de caixa, **When** o usuário escolhe "Prestar contas", **Then**
   abre um modal com o período (da última prestação até a data escolhida, padrão hoje), o resumo
   (entradas, saídas, comissão, lançamentos, retiradas e valor apurado) e o campo do valor recebido,
   já preenchido com o valor apurado.
2. **Given** o modal preenchido, **When** o usuário confirma, **Then** a tela pede a senha dele; com a
   senha certa a prestação é gravada e o saldo de caixa do vendedor passa a contar a partir dela;
   com a senha errada nada é gravado e aparece "Senha incorreta.".
3. **Given** a linha de um gerente, **When** o usuário escolhe "Prestar contas", **Then** o modal lista
   os vendedores do gerente com o valor apurado de cada um e o total, e a confirmação grava todas as
   prestações juntas: ou todas, ou nenhuma.
4. **Given** um vendedor sem nenhum movimento desde a última prestação, **When** o usuário tenta
   prestar contas, **Then** a tela informa "Nenhum movimento a prestar no período." e não grava nada
   (no lote do gerente, esse vendedor é pulado e o modal diz quantos foram pulados).
5. **Given** um valor apurado negativo (a banca deve ao vendedor), **When** o modal aparece, **Then**
   o valor aparece em vermelho com a indicação "a banca deve ao vendedor", e a prestação grava esse
   valor como está, sem lançamento automático escondido.
6. **Given** uma data final no futuro ou anterior à última prestação, **When** o usuário tenta
   confirmar, **Then** a data é recusada no próprio campo.
7. **Given** um valor apurado de R$ 100,00 e R$ 80,00 informados como recebidos, **When** o usuário
   confirma, **Then** o modal avisa antes "Fica para a próxima prestação: R$ 20,00", e depois o
   saldo de caixa do vendedor mostra R$ 20,00 como saldo anterior.
8. **Given** um vendedor com apostas ainda em aberto, **When** o modal de prestação aparece, **Then**
   mostra quantas estão em aberto e o valor delas, avisando que o resultado delas entra na próxima
   prestação, e permite prestar contas assim mesmo.

---

### User Story 3 - Lançamentos, retiradas e histórico do vendedor (Priority: P2)

O usuário registra no caixa de um vendedor um suprimento (dinheiro entregue a ele, que aumenta o que
ele deve) ou uma retirada (dinheiro recolhido, que diminui o que ele deve), com valor, data/hora e
observação, e consulta o histórico de lançamentos, retiradas e prestações do vendedor, podendo
excluir um lançamento que ainda não foi prestado.

**Why this priority**: completa o saldo de caixa com o dinheiro que circula fora das apostas;
depende da US1 para aparecer, mas é testável sozinha.

**Independent Test**: lançar um suprimento de R$ 50,00 e uma retirada de R$ 20,00 num vendedor,
conferir o saldo de caixa +R$ 30,00 e os dois itens no histórico; excluir a retirada e conferir o
saldo; prestar contas e conferir que o suprimento não pode mais ser excluído.

**Acceptance Scenarios**:

1. **Given** a linha de um vendedor, **When** o usuário escolhe "Lançamento", **Then** abre um modal
   com o tipo (Suprimento ou Retirada), valor, data/hora (padrão agora) e observação opcional (até
   500 caracteres).
2. **Given** um lançamento gravado, **When** a lista e os cartões são atualizados, **Then** o valor
   aparece em Lançamentos ou Retiradas no período da data/hora informada e no saldo de caixa.
3. **Given** a linha de um vendedor, **When** o usuário escolhe "Transações", **Then** abre o
   histórico do vendedor (suprimentos, retiradas e prestações), do mais novo para o mais antigo,
   com tipo, valor, data/hora, observação e quem registrou, paginado e com filtro por tipo e
   período.
4. **Given** um lançamento ainda não incluído em prestação de contas, **When** o usuário com a
   permissão escolhe excluir e confirma, **Then** o lançamento sai dos valores e do saldo, e o
   histórico guarda quem excluiu e quando.
5. **Given** um lançamento já incluído numa prestação de contas, **When** o usuário olha o
   histórico, **Then** não há a opção de excluir.
6. **Given** a última prestação de contas do vendedor e um usuário que pode desfazê-la (FR-022a),
   **When** ele escolhe "Desfazer prestação", informa o motivo e a senha, **Then** a prestação fica
   marcada como "desfeita" no histórico, os lançamentos dela voltam a ser excluíveis e o saldo de
   caixa volta ao valor de antes; nas prestações anteriores, a opção não aparece.
7. **Given** um valor zero, negativo ou com mais de duas casas, **When** o usuário tenta gravar,
   **Then** o campo mostra o erro e nada é gravado.

---

### User Story 4 - Caixa clientes no período (Priority: P2)

Admin e Supervisor abrem a aba "Clientes" do Caixa e veem, para o período escolhido, o caixa das
apostas online dos clientes do site (as mesmas contas da Caixa online da Dashboard), o cassino, os
bônus, os depósitos e saques, o resultado do site e uma lista por cliente.

**Why this priority**: a Dashboard só mostra o dia; fechar a semana ou o mês do site exige o período.

**Independent Test**: com apostas online de dois clientes em dias diferentes, abrir a aba Clientes em
"Hoje" e conferir que os cartões batem com a Caixa online da Dashboard; trocar para "Esta semana" e
conferir os dois clientes na lista; como Gerente, conferir que a aba não aparece.

**Acceptance Scenarios**:

1. **Given** um Admin na aba Clientes, **When** ela abre, **Then** vê os cartões do período: Entradas
   (apostas pagas com saldo real), Em aberto, Premiadas, Perdidas, Saídas, Encerramentos (cash out),
   Depósitos aprovados, Saques pagos e Saldo líquido (Entradas − Saídas − Encerramentos).
2. **Given** o período "Hoje", **When** o usuário compara com a Caixa online da Dashboard, **Then**
   os valores são os mesmos.
3. **Given** a aba Clientes, **When** a lista aparece, **Then** cada linha mostra o cliente (nome e
   telefone ou e-mail), apostas, entradas, saídas, encerramentos, depósitos, saques e líquido, com busca
   por nome, telefone ou e-mail (o cliente não tem login), só os clientes com movimento no período, e paginação.
4. **Given** apostas pagas com saldo de promoção, **When** o caixa é calculado, **Then** elas não
   contam nas Entradas.
5. **Given** um Gerente ou Vendedor, **When** abre Caixa, **Then** a aba Clientes não aparece e o
   acesso direto é recusado.
6. **Given** rodadas de cassino e bônus no período, **When** a aba Clientes abre, **Then** mostra os
   blocos Cassino (entradas, saídas, resultado e comissão dos provedores) e Bônus (concedidos e
   convertidos), e o Resultado do site (esportes + cassino − comissão dos provedores − bônus
   convertidos), com os valores do cassino iguais aos do relatório do cassino para o mesmo período
   quando não houver rodadas jogadas com saldo de promoção (o Caixa clientes conta só o saldo real;
   o relatório do cassino conta todas as carteiras).

---

### User Story 5 - Comissão do gerente e limites do vendedor (Priority: P3)

Quem tem a permissão define o percentual da comissão do gerente (sobre o líquido dos vendedores
dele) direto na linha do gerente, e adiciona limite a um vendedor direto na linha dele, sem sair do
Caixa.

**Why this priority**: são ajustes pouco frequentes, mas a comissão do gerente é necessária para o
líquido da rede ficar certo (hoje é 0% fake).

**Independent Test**: definir 10% para um gerente cujos vendedores tiveram líquido de R$ 200,00 no
período e conferir R$ 20,00 de comissão do gerente e o líquido R$ 180,00; com líquido negativo,
conferir comissão R$ 0,00; adicionar R$ 500,00 ao limite geral de um vendedor e conferir o novo
limite na linha.

**Acceptance Scenarios**:

1. **Given** a linha de um gerente e a permissão de editar a comissão do gerente, **When** o usuário
   escolhe "Comissão do gerente", **Then** abre um modal com o percentual atual (0 a 100, duas casas)
   e, ao salvar, cartões e linhas são recalculados.
2. **Given** o líquido dos vendedores de um gerente no período ≤ 0, **When** a comissão é calculada,
   **Then** ela é R$ 0,00.
3. **Given** a linha de um vendedor e a permissão de editar as configurações dele, **When** o usuário
   escolhe "Ajustar limite", **Then** abre um modal com os limites atuais (geral, duplo, simples) e
   o valor a somar ou subtrair em cada um (negativo reduz), com a prévia do novo valor, e ao salvar
   a linha mostra os novos limites; um ajuste que deixaria um limite abaixo de zero é recusado no
   próprio campo.
4. **Given** a Dashboard, **When** ela é aberta depois desta spec, **Then** Lançamentos, Retiradas e
   Comissão do gerente do Apurado mostram os valores reais do dia, sem o selo "em breve".

---

### User Story 6 - Gráfico e exportação (Priority: P4)

O usuário abre o gráfico de um vendedor (apostas por quantidade de palpites no período) e baixa o
caixa do período em planilha Excel: geral, de um gerente, de um vendedor ou da aba Clientes.

**Why this priority**: são consultas de apoio; a operação do caixa funciona sem elas.

**Independent Test**: com apostas de 1, 2 e 12 palpites de um vendedor, abrir o gráfico e conferir
as barras "1", "2" e "Mais de 10"; baixar a planilha do vendedor e conferir que os totais batem com
a tela.

**Acceptance Scenarios**:

1. **Given** a linha de um vendedor, **When** o usuário escolhe "Gráfico", **Then** abre um modal com a
   quantidade de apostas do período por número de palpites (1 a 10 e "Mais de 10").
2. **Given** a permissão de exportar, **When** o usuário escolhe "Baixar planilha" no cabeçalho (nível
   atual) ou na linha de um gerente ou vendedor, **Then** recebe um arquivo .xlsx com o período, os
   totais e uma linha por usuário (ou por cliente, na aba Clientes), com os mesmos valores da tela.
3. **Given** a exportação em andamento, **When** o usuário espera, **Then** o botão mostra
   carregando e não permite clicar de novo.

---

### Edge Cases

- Período sem nenhum movimento: cartões em R$ 0,00 e lista com a mensagem de vazio orientando a
  trocar o período.
- Período personalizado com "de" maior que "até", ou maior que 1 ano: recusado no próprio campo.
- Vendedor que nunca prestou contas: o saldo de caixa conta desde o primeiro movimento dele (não
  desde "1 ano atrás", como no antigo).
- Aposta feita antes de uma prestação e decidida depois dela: o prêmio ou a devolução entra no saldo
  de caixa do período seguinte, pela data da decisão (Clarifications 2026-10-21); o resumo da
  prestação mostra quantas apostas ainda estão em aberto e o valor delas, como informação.
- Aposta cancelada: não entra em nenhum valor do caixa. Se for cancelada depois de entrar numa
  prestação, o valor dela volta como estorno no saldo de caixa do período seguinte, pela data do
  cancelamento.
- Prestação com valor recebido diferente do apurado: a diferença (para mais ou para menos) passa a
  ser o saldo anterior da próxima prestação e aparece no saldo de caixa como "saldo anterior".
- Aposta devolvida: o valor devolvido entra em Saídas, como na Dashboard.
- Duas pessoas prestando contas do mesmo vendedor ao mesmo tempo: só a primeira grava; a segunda
  recebe "O caixa deste vendedor mudou; confira e tente de novo." e a tela é atualizada.
- Desfazer uma prestação com lançamentos ou outra prestação feitos depois dela: só a última pode ser
  desfeita; lançamentos posteriores continuam valendo no saldo de caixa.
- Lançamento com data/hora anterior à última prestação de contas: recusado ("Data anterior à última
  prestação de contas.").
- Vendedor que muda de gerente: o caixa e o histórico seguem o vendedor; a comissão do gerente do
  período é calculada pelo gerente atual do vendedor.
- Vendedor inativo ou excluído: continua na lista enquanto tiver movimento ou saldo de caixa no
  período, marcado como "inativo" ou "excluído"; sem saldo e sem movimento, não aparece. Ele ainda
  pode receber retiradas e prestação de contas (para baixar o que deve), mas não suprimento
  (FR-014a).
- Permissão retirada com a tela aberta: a próxima ação é recusada com a mensagem do servidor e o
  item some ao recarregar.
- Sessão expirada: leva ao login como no restante do painel.
- Celular: as linhas viram cartões com nome, líquido e saldo de caixa em destaque e o restante ao
  expandir; a trilha encurta para "‹ Voltar" com o nome do nível atual.

## Requirements *(mandatory)*

### Functional Requirements

**Acesso e navegação**

- **FR-001**: O menu do painel DEVE ter o item "Caixa", visível para quem tem a permissão de ver o
  caixa ou de ver o caixa clientes, levando à tela com as abas Rede e Clientes que o usuário pode
  usar.
- **FR-002**: Cada nível DEVE ver e agir só na própria rede: Admin na banca toda; Supervisor nos
  gerentes e vendedores dele; Gerente nos vendedores dele; Vendedor só no próprio caixa. O servidor
  DEVE recusar qualquer consulta ou ação fora da rede, mesmo por acesso direto.
- **FR-003**: Na aba Rede, a tela DEVE mostrar a lista do nível abaixo do usuário e permitir descer
  (Supervisor → Gerente → Vendedor) e voltar por uma trilha clicável; os cartões DEVEM mostrar os
  totais do usuário em foco (a banca, para o Admin no topo).

**Período e tipo de aposta**

- **FR-004**: A tela DEVE ter o filtro de período com os atalhos Hoje (padrão), Ontem, Esta semana,
  Este mês e Personalizado (de/até, no máximo 1 ano), no fuso do sistema (Brasília), valendo para
  cartões, listas, gráfico e exportação.
- **FR-005**: A aba Rede DEVE ter as abas de tipo Todas, Pré-jogo e Ao vivo; uma aposta é "Ao vivo"
  quando tem ao menos um palpite do ao vivo, e "Pré-jogo" quando não tem nenhum. Lançamentos,
  retiradas e saldo de caixa aparecem só em Todas.

**Contas do caixa da rede**

- **FR-006**: Os valores das apostas DEVEM seguir as regras da Dashboard (spec 014), só trocando o
  dia pelo período: consideram as apostas ativas do balcão (feitas por vendedor; as do cliente no
  site ficam só no Caixa clientes) criadas no período; Entradas = valor apostado; Em aberto,
  Premiadas e Perdidas = valor apostado de cada grupo, com as quantidades; Saídas = prêmios das
  premiadas + valores devolvidos; Comissão do vendedor = comissão gravada em cada aposta.
- **FR-007**: A Comissão do gerente DEVE ser o percentual do gerente sobre o líquido dos vendedores
  dele no período (Entradas − Saídas − Comissão do vendedor), zero quando esse líquido for ≤ 0, e
  somada por gerente nos níveis de cima. O líquido usado é o do período inteiro escolhido (não a
  soma da comissão de cada dia), e o cartão DEVE ter a dica "calculada sobre o período inteiro; a
  soma de dias separados pode ser diferente". Ela é só informativa: desconta do líquido da rede, mas não
  entra no saldo de caixa nem na prestação de contas dos vendedores, e o sistema não registra o
  pagamento dela ao gerente.
- **FR-008**: O Líquido DEVE ser Entradas − Saídas − Comissão do vendedor − Comissão do gerente (para
  o Vendedor, sem a comissão do gerente).
- **FR-009**: O Saldo de caixa do vendedor DEVE ser o que ele deve desde a última prestação de contas
  até agora (independente do período filtrado): Saldo anterior (diferença da última prestação,
  FR-019) + Entradas − Comissão do vendedor das apostas feitas no intervalo − Saídas das apostas
  decididas no intervalo (prêmios e devoluções pela data da decisão, mesmo de apostas feitas antes)
  − estornos de apostas já prestadas e canceladas no intervalo + Suprimentos − Retiradas; sem
  prestação anterior, desde o primeiro movimento dele. Assim nenhum valor fica fora de caixa nem é
  contado duas vezes. Inclui também as correções de resultado de apostas já prestadas (re-apuração),
  que aparecem no resumo da prestação como "Ajustes de apostas anteriores".
- **FR-009a**: Os cartões e as listas por período (FR-006) continuam com a regra da Dashboard (apostas
  pela data em que foram feitas, com o grupo "Em aberto"); a regra pela data da decisão vale para o
  saldo de caixa e para a prestação de contas.
- **FR-010**: Todos os valores DEVEM ser calculados no servidor em reais com duas casas, sem
  arredondamento por ponto flutuante, pela mesma regra única usada pela tela, pela exportação e pela
  Dashboard, de modo que os três mostrem o mesmo número para o mesmo período.

**Lista de vendedores e de gestores**

- **FR-011**: A lista de vendedores DEVE mostrar por linha: nome, login, apostas, entradas,
  lançamentos, retiradas, limites (geral / duplo / simples), comissão, saídas, líquido e saldo de
  caixa; a de supervisores e gerentes: nome, apostas, entradas, em aberto, premiadas, perdidas,
  comissão do vendedor, comissão do gerente, saídas, lançamentos, retiradas, líquido, saldo de
  caixa (soma dos saldos de caixa dos vendedores abaixo, só na aba Todas) e recebido no período
  (soma dos valores recebidos nas prestações de contas feitas no período, desconsiderando as
  desfeitas). Gerente e supervisor não têm prestação de contas própria: o repasse do dinheiro a eles
  e deles para cima fica fora do sistema.
- **FR-012**: As listas DEVEM ter busca por nome ou login, ordenação por nome (padrão) ou por
  entradas, líquido ou saldo de caixa, e paginação (padrão 20, máximo 100).
- **FR-013**: Cada linha DEVE ter o menu de ações (três pontinhos) só com as ações que o usuário pode
  usar: no vendedor — Lançamento, Prestar contas, Transações, Ajustar limite, Gráfico e Baixar
  planilha; no gerente — Prestar contas (todos os vendedores), Comissão do gerente e Baixar planilha.

**Lançamentos**

- **FR-014**: O usuário com a permissão DEVE poder registrar no caixa de um vendedor um Suprimento ou
  uma Retirada com valor (maior que zero, duas casas), data/hora (não futura e não anterior à última
  prestação do vendedor) e observação opcional (até 500 caracteres); o registro guarda quem lançou.
- **FR-014a**: Para vendedor inativo ou excluído, o caixa DEVE continuar aceitando retiradas,
  prestação de contas (individual e no lote do gerente) e desfazer a última prestação, e DEVE
  recusar suprimento com "Vendedor inativo ou excluído não recebe suprimento."; o modal de
  lançamento desses vendedores mostra só a opção Retirada.
- **FR-015**: O usuário com a permissão DEVE poder excluir um lançamento ainda não incluído em
  prestação de contas, com confirmação; a exclusão é lógica e guarda quem excluiu e quando.
  Lançamentos já prestados NÃO DEVEM poder ser excluídos; prestações só podem ser desfeitas pela
  regra do FR-022a.
- **FR-016**: O histórico de Transações do vendedor DEVE listar suprimentos, retiradas e prestações
  (tipo, valor, data/hora, observação, quem registrou e, nos excluídos, quem excluiu), do mais novo
  para o mais antigo, com filtro por tipo e período, paginado (padrão 20, máximo 100).

**Prestação de contas**

- **FR-017**: O usuário com a permissão DEVE poder prestar contas de um vendedor até uma data/hora
  escolhida (padrão agora; não futura; posterior à última prestação), vendo antes o resumo do
  período (entradas, saídas, comissão, suprimentos, retiradas e valor apurado).
- **FR-018**: A prestação DEVE exigir a senha do usuário que a faz, gravar o período (início e fim),
  o valor apurado, o valor recebido, quem fez, a observação opcional e os lançamentos incluídos, e
  fazer o saldo de caixa do vendedor passar a contar a partir do fim do período.
- **FR-019**: O valor recebido DEVE vir preenchido com o valor apurado e poder ser alterado; quando
  for diferente do apurado, a diferença (apurado − recebido) DEVE ficar como saldo anterior do
  vendedor para a próxima prestação, o modal DEVE mostrar "Fica para a próxima prestação: R$ X"
  antes de confirmar, e o saldo de caixa só zera quando o recebido é igual ao apurado
  (Clarifications 2026-10-21).
- **FR-020**: Valor apurado negativo (a banca deve ao vendedor) DEVE ser gravado como está, com a
  indicação na tela, sem criar lançamento automático.
- **FR-021**: Na linha de um gerente, a prestação DEVE valer para todos os vendedores dele com
  movimento, mostrando o valor de cada um e o total, e gravar todas juntas ou nenhuma; os vendedores
  sem movimento são pulados e a tela informa quantos. No lote, o valor recebido é o apurado de cada
  vendedor; para registrar um valor recebido diferente, presta-se aquele vendedor sozinho.
- **FR-022**: Duas prestações do mesmo vendedor ao mesmo tempo NÃO DEVEM gerar períodos sobrepostos:
  a segunda é recusada com mensagem e a tela é atualizada.
- **FR-022a**: Só a última prestação de contas de cada vendedor DEVE poder ser desfeita, por quem a
  fez ou por um superior dele na rede que tenha a permissão de prestar contas, com a senha do
  usuário e um motivo obrigatório (até 500 caracteres). Desfazer é exclusão lógica: guarda quem
  desfez, quando e o motivo; os lançamentos incluídos nela voltam a ficar abertos, o saldo anterior
  volta a ser o da prestação anterior e o saldo de caixa volta a contar a partir dela. A prestação
  desfeita continua no histórico, marcada como "desfeita", com o motivo. Uma prestação feita em lote
  pelo gerente é desfeita vendedor a vendedor.

**Caixa clientes**

- **FR-023**: A aba Clientes DEVE mostrar, das apostas online dos clientes do site no período, os
  mesmos valores da Caixa online da Dashboard (spec 014, FR-034 a FR-036): Entradas (só saldo real,
  sem saldo de promoção), Em aberto, Premiadas, Perdidas, Saídas, Encerramentos, Depósitos
  aprovados, Saques pagos e Saldo líquido (Entradas − Saídas − Encerramentos; depósitos e saques são
  informativos).
- **FR-024**: A aba Clientes DEVE listar os clientes com movimento no período (nome, telefone ou
  e-mail, apostas, entradas, saídas, encerramentos, depósitos, saques e líquido), com busca por nome,
  telefone ou e-mail (a tabela de clientes não tem login),
  ordenação por nome (padrão), entradas ou resultado e paginação (padrão 20, máximo 100).
- **FR-025**: A aba Clientes DEVE mostrar também, no período (Clarifications 2026-10-21):
  - **Cassino**: entradas (valor apostado nas rodadas com saldo real), saídas (ganhos pagos), resultado
    (entradas − saídas) e comissão dos provedores, com as mesmas regras do relatório do cassino
    (spec 011);
  - **Bônus**: bônus concedidos (valor das promoções creditadas, que não é dinheiro de verdade) e
    bônus convertidos (valor que passou do saldo de promoção para o saldo real);
  - **Resultado do site**: Saldo líquido das apostas esportivas + Resultado do cassino − Comissão dos
    provedores − Bônus convertidos.
  A lista por cliente (FR-024) DEVE ganhar as colunas resultado do cassino e bônus convertidos, e o
  líquido do cliente passa a ser o resultado do site daquele cliente.
- **FR-026**: A aba Clientes DEVE ser só de Admin e Supervisor (banca toda), como a Caixa online;
  Gerente e Vendedor NÃO DEVEM poder usá-la, mesmo que recebam a permissão.

**Comissão do gerente e limites**

- **FR-027**: Cada gerente DEVE ter um percentual de comissão (0 a 100, duas casas, padrão 0)
  editável na linha dele por quem tem a permissão; o percentual vale para os cálculos de qualquer
  período consultado, e as prestações já feitas guardam os valores do momento.
- **FR-028**: "Ajustar limite" DEVE somar ao limite geral, duplo e/ou simples do vendedor (os mesmos
  das configurações dele, consumidos pelas apostas) o valor informado em cada um, positivo para
  aumentar e negativo para reduzir, mostrando os atuais e a prévia do novo valor; o servidor DEVE
  recusar o ajuste que deixaria algum limite abaixo de zero no momento da gravação ("O limite
  {nome} ficaria negativo; o disponível agora é R$ X."), sem gravar nenhum dos três. Não há opção
  de definir um valor exato. Usa a permissão de editar as configurações do usuário que já existe.

**Gráfico e exportação**

- **FR-029**: O Gráfico do vendedor DEVE mostrar a quantidade de apostas ativas do período por número
  de palpites (1 a 10 e "Mais de 10"), em barras.
- **FR-030**: A exportação DEVE gerar um arquivo .xlsx com o período, os filtros, os totais e uma
  linha por usuário do nível exportado (geral, gerente, vendedor) ou por cliente (aba Clientes), com
  os mesmos valores da tela e valores em formato de moeda.

**Permissões**

- **FR-031**: DEVEM existir as permissões, no mesmo padrão das demais (lista, padrão por função e
  bloqueio por função): `caixa.ver`, `caixa.ver_clientes`, `caixa.lancar`,
  `caixa.excluir_lancamento`, `caixa.prestar_contas`, `caixa.exportar` e
  `caixa.editar_comissao_gerente`.
- **FR-032**: Padrão por função: Admin e Supervisor recebem todas; Gerente recebe `caixa.ver`,
  `caixa.lancar`, `caixa.excluir_lancamento`, `caixa.prestar_contas` e `caixa.exportar`, e NÃO PODE
  usar `caixa.ver_clientes` nem `caixa.editar_comissao_gerente`; Vendedor recebe só `caixa.ver` (o
  próprio caixa) e NÃO PODE usar as demais. As permissões DEVEM ser aplicadas no servidor em toda
  consulta e ação.
- **FR-033**: Os usuários já cadastrados DEVEM receber as permissões padrão da função deles ao
  publicar esta spec.

**Dashboard**

- **FR-034**: O Apurado da Dashboard DEVE passar a mostrar Lançamentos, Retiradas e Comissão do
  gerente reais do dia, pela mesma regra deste caixa, sem o selo "em breve", e os dados fake
  correspondentes DEVEM ser removidos.

**Visual, responsividade e padrões do painel**

- **FR-035**: A tela DEVE seguir os padrões do painel (specs 014, 015 e 016 e constituição): cores do
  tema, cabeçalho do painel, abas do painel, cartões de valor, cabeçalho padrão nos modais (ícone,
  título e descrição), campos com rótulo em cima, campo de valor em reais já usado no painel,
  paginação e menu de três pontinhos nas ações.
- **FR-036**: No celular (até 900 px), as linhas DEVEM virar cartões com nome, líquido e saldo de
  caixa em destaque e o restante ao expandir; os cartões de totais DEVEM ficar em duas colunas; nada
  pode exigir rolagem horizontal.
- **FR-037**: Cada área (cartões, lista, modais) DEVE ter estados de carregando (esqueleto), vazio
  (com orientação) e erro com "tentar novamente", sem travar as outras.
- **FR-038**: Toda gravação DEVE dar retorno imediato (botão com carregando, confirmação de sucesso
  ou mensagem do servidor) e atualizar cartões e linhas sem recarregar a página.

### Key Entities

- **Caixa (apuração)**: valores calculados de um usuário ou grupo num período e tipo de aposta:
  quantidades e valores de apostas, comissões, saídas, lançamentos, retiradas, líquido e saldo de
  caixa. Não é gravado; é calculado a cada consulta.
- **Lançamento de caixa**: suprimento ou retirada no caixa de um vendedor: tipo, valor, data/hora,
  observação, quem lançou, a prestação em que foi incluído (quando já prestado) e quem excluiu.
- **Prestação de contas**: fechamento do caixa de um vendedor: período (início e fim), saldo
  anterior, valor apurado, valor recebido, diferença que fica para a próxima, quem fez, observação,
  se foi feita em lote pelo gerente, os lançamentos incluídos e, quando desfeita, quem desfez,
  quando e o motivo.
- **Comissão do gerente**: percentual de cada gerente sobre o líquido dos vendedores dele.
- **Caixa clientes**: valores das apostas online, encerramentos, cassino, bônus, depósitos e saques
  dos clientes do site num período, no total e por cliente.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Um gerente encontra o saldo de caixa de um vendedor dele e presta contas em menos de 1
  minuto a partir do menu, sem ajuda.
- **SC-002**: Em 100% das conferências manuais, os valores do período "Hoje" no Caixa são iguais aos
  do Apurado e da Caixa online da Dashboard, e a planilha exportada bate com a tela.
- **SC-003**: Em 100% das conferências, a soma das linhas de um nível é igual aos cartões do nível de
  cima para o mesmo período e tipo (a comissão do gerente é somada por gerente, cada uma sobre o
  período inteiro).
- **SC-004**: Um usuário nunca vê nem altera o caixa de fora da rede dele em nenhum dos testes,
  inclusive por acesso direto.
- **SC-005**: Nenhum centavo diverge entre o valor apurado de uma prestação e a soma dos movimentos
  do período dela em 100% das conferências.
- **SC-006**: A tela funciona no celular (360 px de largura) sem rolagem horizontal e com todos os
  alvos de toque com pelo menos 40 px.
- **SC-007**: Abrir o Caixa ou trocar de período mostra os cartões e a primeira página da lista em até
  2 segundos numa conexão comum, com até 500 vendedores na rede.

## Assumptions

- As apostas, comissões, limites, encerramentos, depósitos e saques já existem (specs 004, 007, 008,
  009 e 010) e não mudam; esta spec só os soma.
- As regras de soma das apostas são as da Dashboard (spec 014); onde esta spec diz "período", a
  Dashboard continua usando "o dia".
- O Apurado da Dashboard continua somando, para Admin e Supervisor, os depósitos online e os saques
  pagos (spec 014, FR-020); no Caixa, o online fica só na aba Clientes.
- Nos cartões e listas por período, a data de uma aposta é a data em que foi feita, como no antigo e
  na Dashboard; no saldo de caixa e na prestação, prêmios, devoluções e estornos entram pela data em
  que aconteceram (FR-009, FR-009a).
- A Caixa online da Dashboard continua só com as apostas esportivas, depósitos e saques do dia
  (spec 014); cassino e bônus aparecem só no Caixa clientes desta spec.
- O Gerente não tem caixa próprio: o caixa dele é a soma dos vendedores dele, e a prestação "do
  gerente" é a dos vendedores dele em lote, como no antigo; o repasse ao supervisor e ao Admin fica
  fora do sistema (Clarifications 2026-10-21).
- O percentual da comissão do gerente vale para qualquer período consultado (não há histórico de
  percentuais); valores já prestados ficam gravados na prestação.
- Os lançamentos do antigo não são migrados; o caixa do v2 começa do zero.
- O fuso é o do sistema (Brasília), como o restante do painel.
- Não há dados fake nesta spec; ela remove os fakes de caixa da Dashboard.
- Sem testes automatizados (constituição); a validação é manual pelo quickstart da spec.
