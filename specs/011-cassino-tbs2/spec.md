# Feature Specification: Cassino integrado ao provedor TBS2

**Feature Branch**: `011-cassino-tbs2`

**Created**: 2026-10-16

**Status**: Draft

**Input**: User description: "Cassino (área /cassino) integrado ao provedor TBS2 (gamesapi): catálogo de
jogos sincronizado no próprio banco, página do cassino com o mesmo visual do sistema antigo
(screens/casino), abertura dos jogos, carteira do cliente respondendo ao provedor (getBalance e
writeBet), histórico das rodadas no painel do cliente e relatórios no painel, refatorando e corrigindo
os problemas do sistema antigo. Uma spec só, com prioridades."

## Clarifications

### Session 2026-10-16 (conversa antes do pedido)

- Q: Quantas specs? → A: Uma só, com prioridades.
- Q: O cassino continua bloqueado quando o GGR da banca passa do limite (como no antigo)? → A: Não.
  O bloqueio não é mantido. A porcentagem por provedor e a data da prestação de contas ficam em
  configuração, só para os relatórios.
- Q: Com o cassino desativado, o que muda na Minha Conta? → A: O card "Bônus cassino" não aparece e
  a área /cassino fica indisponível.
- Q: Como ficam as apostas nos canais pré-pagos? → A: O canal tem uma marcação "bônus primeiro":
  a aposta usa primeiro o saldo de bônus de cassino e, se ele não tiver saldo suficiente, usa o saldo
  real.
- Q: Como as rodadas aparecem para o cliente? → A: Como no antigo, uma linha por sessão de jogo no
  extrato da Minha Conta e, ao tocar, o detalhe de cada rodada, corrigindo os problemas do antigo.
- Q: Onde ficam as credenciais do provedor? → A: Só no arquivo de ambiente. Para testes locais, as
  credenciais do hall informadas pelo responsável.
- Q: E a URL de callback? → A: Ao final da implementação, informar a URL a cadastrar no painel do
  provedor (Callback URL do hall).
- Q: Dá para executar jogadas pelo sistema para conferir as respostas? → A: Não pela integração: as
  rodadas acontecem dentro do jogo, no navegador do jogador, e o provedor só chama a carteira (o
  callback). Por isso toda chamada recebida do provedor fica registrada, para conferir os formatos
  reais quando os jogos forem testados com o callback apontando para o sistema.

### Session 2026-10-16 (clarify)

O responsável delegou as decisões ("usar o que for mais correto; se der erro, corrigimos depois").
As respostas abaixo são as recomendações aplicadas.

- Q: Nos canais que não são "bônus primeiro", de qual carteira a aposta sai primeiro? → A: Saldo
  real primeiro; se ele não cobrir a aposta, bônus de cassino. Nos canais "bônus primeiro", o
  contrário. O limite fixo de R$ 0,40 do antigo sai (FR-024).
- Q: Como o saldo mostrado no jogo combina com duas carteiras? → A: Carteira da sessão: a sessão de
  jogo usa uma carteira por vez, escolhida pela regra acima na primeira aposta; o saldo informado ao
  provedor é o dessa carteira; uma aposta nunca é dividida entre carteiras; quando a carteira da
  sessão não cobre a aposta, a sessão passa para a outra carteira; o prêmio volta para a carteira da
  aposta que o gerou (FR-020, FR-024).
- Q: O usuário do painel em acesso assistido pode jogar com o dinheiro do cliente? → A: Não. Ele vê o
  cassino e as rodadas do cliente, mas não abre jogos com dinheiro (FR-013).
- Q: Quais porcentagens de comissão os provedores começam tendo? → A: As do sistema antigo (pgsoft
  15,5%; evolution 18,5%; spribe e aviator 14%; pragmatic, amatic e NetEnt 12%; red tiger e booming
  17,5%; novomatic 14%; demais 0%), editáveis no painel (FR-031).

### Session 2026-10-16 (analyze)

Decisões tomadas depois do `/speckit-analyze`, aprovadas pelo responsável ("sigo suas
recomendações").

- Q: Provedor sem logo na barra de provedores? → A: Mostra o nome do provedor no lugar da imagem.
  Só acontece com provedor ativado além dos do sistema antigo.
- Q: O botão Sacar da Carteira do cassino abre o quê? → A: O saque do v2 (o mesmo da Minha Conta),
  no lugar da janela de alerta com campo de valor do antigo.
- Q: O que aparece em /cassino com o cassino desligado? → A: Igual ao antigo: o alerta "Acesso ao
  cassino não está disponível." e, 3 segundos depois, a volta para a página de esportes.
- Q: E o mesmo jogo cadastrado pelo provedor em dois provedores diferentes (ex.: Aviator em
  `spribe` e em `fast_games`)? → A: Limite conhecido: cada provedor tem o seu jogo. Hoje só um deles
  abre (o outro está num canal pré-pago desativado). O painel lista esses casos e avisa ao ativar um
  jogo que ficaria repetido.
- Q: Que carteira a consulta de saldo mostra antes da primeira aposta da sessão? → A: A primeira
  da ordem da sessão (saldo real e depois bônus de cassino, ou o inverso no canal "bônus primeiro")
  que tiver saldo maior que zero; sem saldo em nenhuma, o saldo real.
- Q: E se a rodada chegar com uma identificação de sessão diferente da recebida na abertura? → A: A
  rodada entra na sessão mais recente do mesmo cliente e jogo aberta nas últimas 12 horas, que passa
  a usar a identificação nova, para não criar linhas repetidas no extrato.

### Diferenças em relação ao sistema antigo (aprovadas pelo responsável, Princípios VII e VIII)

- o catálogo deixa de ter um registro por nome + canal: o jogo é único e os canais do provedor são
  opções dele (o antigo mostrava o mesmo jogo repetido e só um deles abria);
- o bloqueio por limite de GGR sai;
- o callback passa a validar o hall e a chave (o antigo não validava);
- os tipos de ação do provedor passam a ser configuração, e cancelamento e reembolso devolvem a
  aposta (o antigo ignorava o cancelamento);
- cada rodada move o saldo uma única vez, com registro próprio; nenhuma movimentação é reescrita (o
  antigo somava as rodadas numa mesma transação, reescrita a cada jogada);
- o card "Bônus cassino" some com o cassino desativado.

## Referências do sistema antigo

- Página do cassino: `resources/js/screens/casino/index.js` e `styles.js` (com os modais
  `screens/casino/modal/balance` e `modal/bonus`), jogo aberto em `resources/js/modals/game`.
- Cassino na tela principal: `resources/js/screens/main/components/casinos`; cartão do jogo em
  `resources/js/modals/casino/components/thumb`.
- Rodadas do cliente: `resources/js/screens/credits/components/casino` (linha por sessão) e
  `resources/js/modals/details_casino` (detalhe das rodadas).
- Painel: `resources/js/screens/adm/casino`.
- Backend: `app/Console/Commands/GamesCommand.php` (`command:cassino`),
  `app/Http/Controllers/CassinoController.php` (callback desativado desde 27/03/2026, listagem,
  abertura e relatório), models `JogosCassino` e `Cassino`, tabelas `jogos_cassinos`, `cassinos` e a
  coluna `sessao_cassino` de `creditos_transacaos`, colunas `cassino`, `prestar_contas_cassino` e
  `max_ggr_cassino` de `configs`.
- Documentação do provedor: "GAMES API EN" (PDF), desatualizada em relação à API real (ver
  Premissas).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Navegar no cassino (Priority: P1)

Qualquer pessoa abre a área /cassino e encontra os jogos sem repetição, com o mesmo visual do
sistema antigo: banners, busca, provedores, tipos, linhas de populares e de cada tipo, menu lateral e
barra inferior.

**Why this priority**: sem um catálogo correto e navegável não há cassino; é a correção do problema
mais visível do antigo (jogo repetido, um abre e outro não).

**Independent Test**: com o catálogo sincronizado, abrir /cassino, filtrar por provedor e por tipo,
buscar "Fortune Tiger" e conferir que cada jogo aparece uma única vez, com imagem, nome e provedor.

**Acceptance Scenarios**:

1. **Given** o cassino ativo e o catálogo sincronizado, **When** alguém abre /cassino, **Then** vê
   os banners, a busca, os provedores, os tipos, a linha de Populares e uma linha por tipo com até 10
   jogos e "Veja mais", com o mesmo visual do antigo.
2. **Given** a página aberta, **When** a pessoa escolhe um provedor, um tipo ou busca pelo nome,
   **Then** vê só os jogos que atendem ao filtro, carregados em partes conforme rola a tela, e
   "Limpar" volta à página inicial do cassino.
3. **Given** um jogo que o provedor oferece em vários canais, **When** a lista é exibida, **Then** o
   jogo aparece uma única vez.
4. **Given** o celular, **When** a pessoa abre /cassino, **Then** vê só os jogos que funcionam no
   celular; no computador, só os que funcionam no computador.
5. **Given** o cassino desativado na configuração, **When** alguém abre /cassino, **Then** vê o
   alerta de que o cassino não está disponível e volta para a página de esportes, e o atalho do
   cassino some do site.

---

### User Story 2 - Jogar com o próprio saldo (Priority: P1)

O cliente logado escolhe um jogo e joga com o saldo da conta. O provedor consulta o saldo e lança
cada rodada (aposta e prêmio) no sistema, que responde no formato que o provedor exige, sem perder
nem duplicar dinheiro.

**Why this priority**: é o que dá sentido ao cassino e onde estavam os erros graves do antigo
(saldo sem trava, carteira guardada em cache, cancelamento ignorado, callback sem validação).

**Independent Test**: com o callback apontando para o sistema, o cliente com saldo abre a Fortune
Tiger, faz rodadas com e sem prêmio e confere o saldo na tela do jogo e na Minha Conta; o registro
das chamadas mostra cada aposta e prêmio movidos uma única vez.

**Acceptance Scenarios**:

1. **Given** um cliente logado com saldo e o cassino ativo, **When** ele toca em "Jogar", **Then**
   o jogo abre dentro do site (ou numa nova página, quando o provedor não permite moldura) com o saldo
   dele.
2. **Given** um visitante, **When** ele toca em "Jogar", **Then** é convidado a entrar ou se
   cadastrar; vendedores e usuários do painel não jogam.
3. **Given** o jogo aberto, **When** o provedor lança uma rodada com aposta e prêmio, **Then** o
   sistema confere o saldo, debita a aposta, credita o prêmio e devolve o saldo novo; com saldo
   insuficiente, recusa sem mover nada.
4. **Given** uma rodada já lançada, **When** o provedor repete a mesma chamada, **Then** o sistema
   devolve a mesma resposta, sem mover o saldo de novo.
5. **Given** uma rodada cancelada ou reembolsada pelo provedor, **When** a chamada chega, **Then** a
   aposta volta para a carteira de onde saiu (o reembolso não confere saldo).
6. **Given** uma chamada com hall ou chave errados, ou de um cliente inexistente, **When** ela chega,
   **Then** é recusada no formato do provedor, sem mover saldo, e fica registrada.
7. **Given** um jogo que o provedor recusa abrir por um canal, **When** o cliente toca em "Jogar",
   **Then** o sistema tenta o próximo canal ativo do mesmo jogo; sem canal disponível, mostra que o
   jogo está indisponível no momento.
8. **Given** um canal marcado "bônus primeiro" e um cliente com bônus de cassino, **When** chega a
   primeira aposta da sessão, **Then** ela sai do bônus de cassino e a sessão passa a usar essa
   carteira; sem bônus suficiente, a aposta sai do saldo real. Nos demais canais, a ordem é saldo
   real e depois bônus de cassino.
9. **Given** o jogo aberto, **When** o jogo pede para fechar ou o cliente usa o botão voltar, **Then**
   o jogo fecha e o cliente volta ao ponto do cassino em que estava.

---

### User Story 3 - Acompanhar as rodadas na Minha Conta (Priority: P2)

O cliente vê no extrato da Minha Conta uma linha por sessão de jogo, com o jogo, o total apostado e
o total ganho, e ao tocar vê cada rodada.

**Why this priority**: dá transparência ao cliente e é a base do atendimento, mas depende das
rodadas já registradas (US2).

**Independent Test**: depois de jogar duas sessões, abrir o extrato com o filtro "Cassino", conferir
as duas linhas e, ao tocar, as rodadas com aposta, prêmio e saldo antes e depois.

**Acceptance Scenarios**:

1. **Given** um cliente que jogou, **When** ele abre o extrato da Minha Conta, **Then** vê uma linha
   por sessão de jogo com data, jogo, total apostado e total ganho (a coluna prêmio preenchida).
2. **Given** a linha de uma sessão, **When** o cliente toca nela, **Then** vê cada rodada com data,
   identificação da rodada, saldo antes, aposta, prêmio e carteira usada.
3. **Given** o extrato, **When** o cliente filtra por "Cassino", **Then** vê só as sessões de jogo.
4. **Given** o cassino desativado, **When** o cliente abre a Minha Conta, **Then** o card "Bônus
   cassino" não aparece.

---

### User Story 4 - Gerir o catálogo no painel (Priority: P2)

O administrador mantém o catálogo atualizado automaticamente e controla o que aparece: ativa e
desativa jogos, provedores e canais, define a ordem e os populares, e acompanha cada atualização.

**Why this priority**: garante que só jogos que funcionam fiquem visíveis; depende do catálogo
(US1).

**Independent Test**: rodar a sincronização, desativar um provedor e um canal, marcar um jogo como
popular e conferir o efeito na página; conferir o resumo da última atualização.

**Acceptance Scenarios**:

1. **Given** a sincronização agendada, **When** ela roda, **Then** jogos novos entram, jogos
   alterados (inclusive com o código trocado pelo provedor) são atualizados sem duplicar, jogos que
   saíram da lista ficam desativados e o resumo da carga fica registrado.
2. **Given** o painel, **When** o administrador desativa um jogo, um provedor ou um canal, **Then**
   ele some da página (ou o jogo passa a abrir pelo próximo canal).
3. **Given** o painel, **When** o administrador marca um jogo como popular ou muda a ordem, **Then**
   a página reflete a mudança.
4. **Given** um canal pré-pago, **When** o administrador o vê no painel, **Then** ele aparece marcado
   como pré-pago e "bônus primeiro", e vem desativado por padrão.

---

### User Story 5 - Relatórios do cassino (Priority: P3)

O administrador vê o movimento do cassino por período, jogo, provedor e canal, com o GGR e a
comissão por provedor, e consulta o histórico de uma sessão no provedor.

**Why this priority**: necessário para a prestação de contas, mas não impede o cliente de jogar.

**Independent Test**: depois de rodadas em dois provedores, gerar o relatório do período e conferir
rodadas, entradas, saídas, GGR e comissão de cada um.

**Acceptance Scenarios**:

1. **Given** rodadas no período, **When** o administrador gera o relatório, **Then** vê o total e o
   detalhe por jogo, provedor e canal (rodadas, entradas, saídas, GGR e comissão), paginado.
2. **Given** uma sessão de um cliente, **When** o administrador pede o histórico, **Then** vê as
   rodadas como o provedor as registrou.
3. **Given** a configuração, **When** o administrador altera a porcentagem de um provedor ou a data
   da prestação de contas, **Then** o relatório passa a usar os valores novos.

---

### Edge Cases

- O provedor troca o código de um jogo, ou recadastra o mesmo jogo com código novo: o jogo continua
  único e abre pelo código atual.
- O mesmo jogo chega em vários canais e um deles é pré-pago sem crédito no hall: o jogo abre por um
  canal que funciona; se nenhum funciona, aparece como indisponível, sem sumir da busca.
- Chega uma rodada de um jogo que saiu do catálogo ou está desativado: a rodada é processada (a
  sessão já estava aberta) e registrada.
- Chega um tipo de ação que não está na configuração: a chamada é registrada e recusada sem mover
  saldo; o administrador vê no registro e pode cadastrar o tipo.
- Aposta e prêmio de uma mesma rodada chegam em chamadas separadas: o prêmio vai para a carteira da
  sessão gravada no banco, sem depender de memória temporária.
- O cliente tem R$ 3,00 de saldo real e R$ 3,00 de bônus e aposta R$ 5,00: nenhuma carteira cobre a
  aposta sozinha e ela é recusada (as carteiras não se somam numa aposta).
- Duas chamadas simultâneas do mesmo cliente (ex.: Aviator com duas apostas): processadas uma após a
  outra, sem saldo errado.
- O cliente é desativado ou o cassino é desligado com o jogo aberto: novas aberturas são recusadas;
  as rodadas da sessão aberta continuam sendo respondidas (cliente desativado recebe recusa nas
  apostas novas).
- O bônus de cassino vence no meio de uma sessão: as apostas seguintes saem do saldo real.
- O provedor não responde ou demora na abertura: o cliente vê "tente novamente" e nada é cobrado.
- A sincronização recebe resposta vazia ou com erro: nada é desativado; o erro fica registrado.
- Prêmio maior que zero sem aposta (rodada grátis do provedor): é creditado normalmente.

## Requirements *(mandatory)*

### Functional Requirements

**Catálogo**

- **FR-001**: O sistema DEVE sincronizar o catálogo do provedor automaticamente, no máximo uma vez
  por hora, e também sob demanda do administrador.
- **FR-002**: Cada jogo DEVE ser único por provedor real e nome; cada canal em que o provedor oferece
  o jogo DEVE ser guardado como uma opção do jogo, com o código do jogo no provedor, o nome do canal,
  se é pré-pago e se está ativo.
- **FR-003**: Um jogo recadastrado pelo provedor (mesmo nome e canal, código novo) DEVE atualizar o
  canal existente, sem criar outro jogo.
- **FR-004**: Jogos e canais que saírem da lista DEVEM ser desativados, sem apagar o histórico; se
  voltarem, DEVEM ser reativados.
- **FR-005**: Cada sincronização DEVE registrar o resumo (recebidos, novos, alterados, desativados,
  falhas); resposta vazia ou com erro NÃO DEVE desativar nada.
- **FR-006**: O catálogo DEVE guardar, de cada jogo: nome, provedor, tipo (incluindo jogos rápidos e
  "crash"), imagem, aparelhos suportados, se tem demo, se é popular ou novo para o provedor, e DEVE
  ocultar jogos que exigem Flash.
- **FR-007**: Canais pré-pagos DEVEM vir desativados por padrão e marcados "bônus primeiro".

**Página do cassino**

- **FR-008**: A área /cassino DEVE ter o mesmo visual do sistema antigo: carrossel de banners, busca,
  barra de provedores, barra de tipos, linha de Populares, uma linha por tipo com até 10 jogos e "Veja
  mais", cartão do jogo com "Jogar" e "Demo", barra inferior (Limpar, Promoções, Carteira, Minha
  conta), menu lateral (Esportes, Populares, Tipos, Provedores com contagem) e rodapé.
- **FR-009**: Filtros por provedor, tipo e busca pelo nome DEVEM carregar os jogos em partes conforme
  a rolagem (no máximo 100 por vez).
- **FR-010**: Só jogos ativos, de provedor ativo, com pelo menos um canal ativo e que funcionem no
  aparelho de quem vê DEVEM aparecer.
- **FR-011**: O botão "Demo" só DEVE aparecer quando o jogo e o hall permitirem demo.
- **FR-012**: Com o cassino desativado, a área /cassino DEVE avisar que o cassino não está
  disponível e voltar para a página de esportes (como no antigo), e o atalho "Cassino" do site e o card "Bônus cassino" da Minha Conta NÃO DEVEM
  aparecer.

**Abrir jogo**

- **FR-013**: Só o cliente logado e ativo DEVE poder jogar com dinheiro; visitante é convidado a
  entrar ou se cadastrar; vendedores, usuários do painel e o usuário do painel em acesso assistido
  NÃO DEVEM abrir jogos com dinheiro (no acesso assistido, o cassino e as rodadas do cliente ficam
  só para consulta).
- **FR-014**: O jogo DEVE ser aberto com um identificador estável do cliente que não exponha dados
  pessoais, na moeda real, dentro do site quando o provedor permitir moldura e em página própria
  quando não permitir.
- **FR-015**: O jogo DEVE abrir pelo canal ativo de maior prioridade; quando o provedor recusar o
  canal (jogo não ativo no hall, crédito do hall insuficiente), o canal DEVE ser marcado indisponível
  e o próximo canal DEVE ser tentado na mesma abertura.
- **FR-016**: O jogo aberto DEVE fechar pelos eventos de fechamento do provedor (mensagem de
  fechamento e página de saída) e pelo botão voltar do navegador, voltando ao ponto da página onde o
  cliente estava, com opção de tela cheia.
- **FR-017**: Cada abertura DEVE registrar a sessão de jogo (cliente, jogo, canal, data).

**Carteira (chamadas do provedor)**

- **FR-018**: O sistema DEVE oferecer um endereço próprio para as chamadas do provedor (consulta de
  saldo e lançamento de rodada) e DEVE validar o hall e a chave (ou a assinatura, quando ligada) em
  toda chamada; chamadas inválidas DEVEM ser recusadas sem mover saldo.
- **FR-019**: Toda resposta DEVE seguir o formato do provedor, inclusive nos erros (cliente não
  encontrado, saldo insuficiente), e nenhuma chamada pode ficar sem resposta.
- **FR-020**: A consulta de saldo DEVE devolver, em reais com duas casas, o saldo da carteira da
  sessão (antes da primeira aposta, o da primeira carteira da ordem do FR-024 com saldo maior que
  zero; sem saldo em nenhuma, o saldo real).
- **FR-021**: Cada rodada DEVE mover o saldo uma única vez, com a conta do cliente travada durante o
  lançamento, e devolver o saldo novo; o mesmo lançamento repetido (mesma identificação de rodada)
  DEVE devolver a mesma resposta sem mover o saldo de novo.
- **FR-022**: Os tipos de ação do provedor (aposta, prêmio, aposta com prêmio, cancelamento,
  reembolso, rodada grátis) DEVEM estar em configuração editável no painel; tipo desconhecido DEVE
  ser registrado e recusado sem mover saldo.
- **FR-023**: Cancelamento e reembolso DEVEM devolver a aposta à carteira de onde ela saiu; o
  reembolso NÃO DEVE conferir saldo.
- **FR-024**: Cada sessão de jogo DEVE usar uma carteira por vez (carteira da sessão), escolhida na
  primeira aposta: saldo real e, se ele não cobrir a aposta, bônus de cassino; nos canais "bônus
  primeiro", bônus de cassino e, se ele não cobrir, saldo real. Uma aposta NÃO DEVE ser dividida entre
  carteiras; quando a carteira da sessão não cobrir a aposta, a sessão DEVE passar para a outra
  carteira (sem nenhuma das duas cobrir, a aposta é recusada). O prêmio DEVE voltar à carteira da
  aposta que o gerou.
- **FR-025**: As apostas DEVEM somar no rollover de depósito (saldo real) e no do bônus de cassino
  (bônus), e cancelamento e reembolso DEVEM desfazer o que somaram.
- **FR-026**: Toda chamada recebida do provedor DEVE ser registrada (sem a chave), com a resposta
  dada, para conferir os formatos reais.

**Rodadas na Minha Conta**

- **FR-027**: Cada rodada DEVE guardar: cliente, sessão, jogo, canal, tipo de ação, identificação da
  rodada no provedor, aposta, prêmio, carteira, saldo antes e depois, e data.
- **FR-028**: O extrato da Minha Conta DEVE mostrar uma linha por sessão de jogo (data, jogo, total
  apostado, total ganho) e, ao tocar, o detalhe de cada rodada; o filtro "Cassino" DEVE mostrar só as
  sessões de jogo.
- **FR-029**: Nenhuma movimentação de saldo já gravada DEVE ser reescrita: totais da sessão são
  calculados a partir das rodadas.

**Painel**

- **FR-030**: O painel DEVE permitir ativar e desativar jogos, provedores e canais, definir a ordem e
  marcar populares, e mostrar o resumo das sincronizações e as chamadas recusadas.
- **FR-031**: A configuração geral DEVE ter: cassino ativo; porcentagem de cada provedor (padrão: as
  do sistema antigo, ver Clarifications); data da prestação de contas. NÃO DEVE haver bloqueio do
  cassino por limite de GGR.
- **FR-032**: O relatório DEVE mostrar, no período, o total e o detalhe por jogo, provedor e canal
  (rodadas, entradas, saídas, GGR e comissão pela porcentagem do provedor), paginado.
- **FR-033**: O administrador DEVE poder consultar o histórico de uma sessão como o provedor a
  registrou.
- **FR-034**: As ações do painel DEVEM exigir permissões próprias, dadas por padrão ao Admin e ao
  Supervisor.
- **FR-035**: O indicador "Cassino" do site, hoje um dado provisório, DEVE passar a vir da
  configuração real.

### Key Entities *(include if feature involves data)*

- **Jogo do cassino**: nome, provedor, tipo, imagem, aparelhos, demo, popular, ordem, ativo, número
  de aberturas.
- **Canal do jogo**: o jogo num canal do provedor: código no provedor, nome do canal, pré-pago,
  "bônus primeiro", prioridade, ativo, indisponível desde (motivo).
- **Provedor do cassino**: nome exibido, ativo, ordem, porcentagem para a comissão.
- **Sincronização**: data, recebidos, novos, alterados, desativados, falhas.
- **Sessão de jogo**: cliente, jogo, canal, identificação da sessão no provedor, data de abertura,
  carteira da sessão (a que está em uso), totais apostado e ganho.
- **Rodada**: sessão, tipo de ação, identificação da rodada no provedor (única), aposta, prêmio,
  carteira, saldo antes e depois, data.
- **Tipo de ação**: valor que o provedor envia e o efeito no saldo (aposta, prêmio, aposta com
  prêmio, cancelamento, reembolso, rodada grátis).
- **Chamada do provedor**: comando, dados recebidos (sem a chave), resposta, resultado, data.
- **Configuração geral** (existente): ganha cassino ativo e data da prestação de contas.
- **Transação do cliente** (existente): ganha as origens das movimentações do cassino.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Nenhum jogo aparece repetido na página do cassino depois de qualquer sincronização.
- **SC-002**: 100% dos jogos visíveis abrem para um cliente com saldo, ou aparecem como
  indisponíveis, nunca com erro sem explicação.
- **SC-003**: 100% das rodadas lançadas pelo provedor movem o saldo uma única vez, mesmo com
  chamadas repetidas; a soma das rodadas de cada sessão é igual à movimentação de saldo dela.
- **SC-004**: O provedor recebe resposta em até 2 segundos em 99% das chamadas.
- **SC-005**: 100% das chamadas com hall ou chave inválidos são recusadas sem mover saldo.
- **SC-006**: A página do cassino mostra a primeira tela de jogos em até 3 segundos no celular.
- **SC-007**: O cliente encontra qualquer sessão de jogo do dia e o detalhe das rodadas em até 2
  toques a partir da Minha Conta.
- **SC-008**: Uma mudança no catálogo do provedor aparece na página em até 1 hora e 5 minutos.

## Assumptions

- A documentação do provedor (PDF) está desatualizada. A API real devolve na lista de jogos campos
  que o PDF não traz (canal, categoria, demo, popular/novo, nome técnico), códigos de erro não
  documentados (jogo não ativo no hall, crédito do hall insuficiente) e tipos de ação além do
  exemplo do PDF; vale o comportamento real, registrado pelas chamadas (FR-026).
- Levantamento da API real (2026-10-16): 6.365 jogos em 77 canais de 49 provedores; 360 jogos
  repetidos em mais de um canal; os canais pré-pagos recusaram a abertura por crédito insuficiente do
  hall; o demo está desligado no hall; a abertura em modo real funcionou para PG Soft (Fortune Tiger),
  Aviator, Spribe (Mines) e Pragmatic (Sweet Bonanza).
- O provedor envia valores em reais com duas casas; a moeda do hall é real (BRL).
- O identificador do cliente enviado ao provedor é gerado pelo sistema e nunca muda (não é o
  telefone).
- Os banners do cassino são os banners já cadastrados no painel (spec 006); as promoções exibidas
  são as promoções de cassino (spec 007).
- O cassino usa as carteiras já existentes (Saldo e Promoção cassino) e o mesmo controle de saldo e
  rollover das apostas esportivas.
- O endereço de chamadas do provedor é público (o provedor não usa o login do site) e protegido pela
  chave do hall.
- As credenciais do provedor (endereço, hall e chave) ficam no ambiente; nada disso vai para o
  código ou para o banco.
- Não há dados fake: o indicador provisório "Cassino" do site é substituído pela configuração real
  (Princípio IX).
- Fora do escopo: cassino ao vivo próprio, rodadas grátis concedidas pela banca (recurso "bm" do
  provedor), jackpots e campanhas de push.
