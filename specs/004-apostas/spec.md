# Feature Specification: Apostas (criação, validação de código e cancelamento)

**Feature Branch**: `004-apostas`

**Created**: 2026-10-06

**Status**: Draft

**Input**: User description: "Spec: criação e cancelamento de apostas. Backend apenas. Reescreve,
com mais segurança e organização, a criação e o cancelamento de apostas dos métodos store, update e
destroy do BilhetesController antigo e dos métodos store, show e palpites do HomeController antigo.
Cobre a aposta do visitante (gerar código), a validação do código pelo vendedor, as apostas diretas
do vendedor e do cliente no pré-jogo e no ao vivo, o delay do ao vivo, a confirmação de mudança de
cotação, o uso das carteiras e do rollover do cliente, o limite de valor apostado por confronto, o
cancelamento pelo próprio apostador, o retorno completo para o comprovante e as configurações de
aposta de cada público. Fora do escopo: cancelamento pela hierarquia, segunda via e layout de
impressão, apuração de resultados e pagamento de prêmios, depósitos, saques, conversão do bônus,
listagens e relatórios de apostas, caixa e prestação de contas, renovação automática da data de
travamento, cashout, pix e vendedor pix, acumuladão, modalidades especiais, pin/device_id, apostas
de cassino e frontend."

> O pedido original acima foi ajustado nas Clarifications de 2026-10-07: o cliente deixou de
> cancelar apostas, entraram o cancelamento pela hierarquia e a edição de palpites (cancelar e
> restaurar), e o pix do visitante ficou para outra spec. Em caso de divergência, valem as
> Clarifications e os requisitos.
>
> O texto completo do pedido (itens 1 a 12) está refletido nos requisitos abaixo. Os nomes de
> tabelas, colunas, enums, permissões e mensagens foram definidos pelo responsável no pedido e
> aparecem aqui por decisão dele, como nas specs 001, 002 e 003. As decisões tomadas antes do
> pedido (análise do sistema antigo) estão registradas em Clarifications.

## Clarifications

### Session 2026-10-06 (antes do pedido, na análise do sistema antigo)

- Q: Como é o código do visitante? → A: Alfanumérico, 8 caracteres.
- Q: Como tratar o delay do ao vivo? → A: Da forma mais segura e prática: a aposta entra "Em
  análise", o delay corre uma única vez sem segurar a requisição e, no fim, o sistema decide sozinho
  (aceita ou recusa), sem perguntar de novo ao apostador.
- Q: O que fazer quando a cotação muda ou fica bloqueada? → A: A cotação bloqueada nunca vira 1,00:
  o palpite volta marcado como indisponível para ser removido. A mudança de cotação é confirmada
  antes do delay, com a preferência "aceitar alterações" (Nenhuma, Somente para maior, Qualquer).
- Q: Onde ficam comissões e limites do vendedor? → A: Em `usuarios_configuracoes`; essas colunas
  são só do vendedor. As comissões ficam em colunas separadas por quantidade de jogos (1 a 12),
  uma série para o pré-jogo e outra para o ao vivo.
- Q: Como funciona o multiplicador? → A: Limita o prêmio a valor × multiplicador (multiplicador
  1.000 e aposta de R$ 5,00 → no máximo R$ 5.000,00), além do prêmio máximo. Confirmado no código
  antigo.
- Q: Como funciona o ganho por múltiplos palpites e qual o padrão? → A: Com 3 ou mais palpites, o
  prêmio recebe um acréscimo percentual. O padrão é 0 em todos os públicos, e o total (prêmio +
  acréscimo) não passa do prêmio máximo.
- Q: Entram nesta spec o cancelamento, a mensagem do bilhete, a data de travamento e o período de
  jogos? → A: Sim: `cancelar_aposta`, `tempo_cancelamento_aposta`, `mensagem_bilhete`,
  `data_travamento_sistema` (trava que deixa de exibir os jogos, só para vendedores e visitantes) e
  `periodo_jogos` (enum Hoje, Amanhã e Depois de amanhã).
- Q: A listagem da spec 003 pode ser ajustada para esconder o que não pode ser apostado? → A: Sim
  (apostar em jogadores, período de jogos e data de travamento).
- Q: Como o cliente usa as carteiras? → A: Prioridade do saldo real; só sem saldo real suficiente
  usa o saldo de bônus de esportes; nunca combina as duas carteiras na mesma aposta. Sem saldo
  suficiente em nenhuma, a aposta é recusada.
- Q: Como funciona o rollover? → A: O depósito de saldo real exige ser apostado pelo menos 1 vez
  antes do saque; o bônus exige um rollover bem maior, definido na promoção.
- Q: Existe limite de valor apostado por confronto? → A: Sim (`limite_valor_apostado`), vale para
  vendedores e clientes.
- Q: As tabelas se chamam `apostas` e `apostas_palpites`? → A: Sim.

### Session 2026-10-07

- Q: O pagamento por pix do visitante (copia e cola ou QR code, com nome e telefone obrigatórios)
  entra nesta spec? → A: Não. Fica para uma spec própria; nesta, o visitante só gera o código.
- Q: Quem cancela apostas? → A: O cliente online NÃO cancela. O vendedor cancela as próprias
  apostas, com permissão e limitado pelo tempo de cancelamento. Os usuários acima (Gerente,
  Supervisor e Admin), com permissão, cancelam as apostas da sua hierarquia sem limite de tempo.
- Q: Existe atualização de aposta além da validação do código? → A: Sim. O usuário do painel edita
  uma aposta Ativa cancelando ou restaurando um palpite (jogo), e o sistema recalcula o prêmio.
- Q: O sistema tem a função pin? → A: Não.
- Q: O prêmio vem da multiplicação das cotações pelo valor apostado? → A: Sim (depois limitado
  pelo multiplicador e pelo prêmio máximo, com o acréscimo por múltiplos palpites).
- Q: Um jogo que está no ao vivo pode ser apostado como pré-jogo? → A: Não, mesmo antes do horário
  de início.
- Q: A aposta de um jogo só abate o limite geral? → A: Sim, abate o `limite_simples` e o
  `limite_geral`.
- Q: Se a cotação mudou entre a geração do código e a validação? → A: O vendedor é informado da
  alteração do prêmio e precisa confirmar antes de validar.
- Q: Pode cancelar uma aposta com resultado já apurado (Vencedor ou Perdedor)? → A: Não. Só apostas
  com resultado Aguardando podem ser canceladas, por qualquer pessoa; a correção de apostas
  apuradas fica para a spec de apuração.
- Q: Uma mesma aposta pode misturar jogos do pré-jogo e do ao vivo? → A: Sim. A aposta mista segue
  inteira as regras do ao vivo (delay, comissão do ao vivo e o vendedor não a cancela); os palpites
  do pré-jogo continuam conferidos como pré-jogo.
- Q: Quem cancela e edita as apostas de clientes online, que não pertencem a nenhum vendedor? → A:
  Qualquer usuário do painel com a permissão (Gerente, Supervisor e Admin); o vendedor continua só
  com as próprias apostas.
- Q: O vendedor novo já nasce podendo cancelar as próprias apostas, e com que prazo? → A: Sim,
  `cancelar_aposta` liberado por padrão, com `tempo_cancelamento_aposta` de 5 minutos.
- Q: Como o apostador vê as demais cotações (além das 4 da listagem) e os jogadores para apostar?
  → A: Por uma rota pública de detalhe de um confronto (pré-jogo e ao vivo), separada da listagem,
  como no sistema antigo; a listagem continua com as 4 cotações principais.

### Session 2026-10-08

- Q: A mensagem do bilhete deve ser copiada para a aposta? → A: Não. Fica só nas configurações (do
  vendedor e do site), e o comprovante usa sempre a mensagem atual.
- Q: (análise de consistência) Quais ajustes foram feitos antes da implementação? → A: palpite em
  jogador precisa ser do mesmo confronto ("Jogador não pertence ao confronto."); repetição de
  jogo conferida pelo confronto já resolvido (pré-jogo ou ao vivo); chave de idempotência
  obrigatória também na validação do código; no cancelamento, o vendedor recebe de volta o limite
  abatido na confirmação, pela quantidade original de palpites; `data_travamento_sistema` sem fuso
  explícito vale -03:00 e é gravada em UTC; idempotência do visitante pelo mesmo IP com a aposta
  ainda Pendente.

## User Scenarios & Testing *(mandatory)*

> Conforme a constituição, o projeto não terá testes automatizados. Os cenários abaixo são
> critérios de aceite validados manualmente.

### User Story 1 - Vendedor faz uma aposta no pré-jogo (Priority: P1)

O vendedor monta uma aposta com um ou mais jogos do pré-jogo, informa o nome do apostador e o
valor, e conclui. O sistema recalcula tudo com as regras e as cotações do vendedor, grava a
aposta como Ativa, abate os limites de venda, calcula a comissão e devolve o comprovante completo.

**Why this priority**: é a forma de aposta principal da banca física e a base de todas as regras
(cotação, prêmio, limites e comissão) que as outras formas reaproveitam.

**Independent Test**: com um vendedor configurado (limites de 5.000,00, comissão de 10% para 2
jogos), apostar R$ 10,00 em 2 jogos com cotações 2,00 e 1,50 e conferir aposta Ativa com prêmio
R$ 30,00, comissão R$ 1,00, limites duplo e geral abatidos em R$ 10,00 e comprovante completo.

**Acceptance Scenarios**:

1. **Given** um vendedor ativo com `realizar_aposta` liberado e 2 jogos futuros com cotações 2,00 e
   1,50 para ele, **When** ele aposta R$ 10,00 nesses jogos, **Then** a aposta é gravada como
   Ativa, com cotação total 3,00, prêmio R$ 30,00 e um código único de 8 caracteres.
2. **Given** `comissao_pre_jogo_2` = 10 para o vendedor, **When** ele aposta R$ 10,00 em 2 jogos do
   pré-jogo, **Then** a comissão da aposta é R$ 1,00.
3. **Given** `limite_duplo` e `limite_geral` de R$ 5.000,00, **When** o vendedor aposta R$ 10,00 em
   2 jogos, **Then** os dois limites passam a R$ 4.990,00 e o `limite_simples` não muda.
4. **Given** `limite_simples` de R$ 5,00, **When** o vendedor aposta R$ 10,00 em 1 jogo, **Then** a
   aposta é recusada com "Restam R$ 5,00 do seu limite simples" e nada é gravado.
5. **Given** um vendedor com `realizar_aposta` bloqueado ou inativo, **When** ele tenta apostar,
   **Then** a aposta é recusada com "Você não tem permissão para realizar apostas" ou "Seu login
   está inativo".
6. **Given** um jogo que começou há 1 minuto, **When** o vendedor o inclui na aposta, **Then** a
   aposta é recusada com "O confronto CASA x FORA já iniciou, retire-o para concluir".
6a. **Given** um jogo com início previsto para daqui a 10 minutos que já está no ao vivo (começou
    adiantado), **When** o vendedor o inclui como pré-jogo, **Then** a aposta é recusada com a
    mesma mensagem, e o jogo não aparece na listagem do pré-jogo.
7. **Given** dois palpites no mesmo confronto, **When** a aposta é enviada, **Then** é recusada com
   "Não é possível cadastrar jogos repetidos na aposta".
8. **Given** um Admin, Supervisor ou Gerente logado, **When** tenta apostar, **Then** é recusado.
9. **Given** o mesmo envio repetido com a mesma chave de idempotência (duplo clique), **When** os
   dois pedidos chegam, **Then** existe uma única aposta e os limites são abatidos uma vez só.

---

### User Story 2 - Cliente aposta com o próprio saldo (Priority: P1)

O cliente logado na área do cliente aposta no pré-jogo com o próprio saldo. O sistema usa o saldo
real quando ele cobre o valor; só quando não cobre usa o saldo de bônus de esportes; nunca combina
os dois. O débito fica no histórico de transações.

**Why this priority**: é a forma de aposta do site e movimenta dinheiro do cliente; precisa ser
correta e à prova de gasto duplo.

**Independent Test**: com um cliente com R$ 50,00 de saldo real e R$ 20,00 de bônus de esportes,
apostar R$ 30,00 (sai do saldo real), depois R$ 30,00 (sai do saldo real, restam R$ 20,00 e R$
20,00), depois R$ 25,00 (recusada) e conferir as transações.

**Acceptance Scenarios**:

1. **Given** um cliente com R$ 50,00 de saldo real e R$ 20,00 de bônus de esportes, **When** aposta
   R$ 30,00, **Then** a aposta é paga com o saldo real, o saldo real passa a R$ 20,00 e existe uma
   transação de origem Aposta, tipo Débito, carteira Saldo, ligada à aposta.
2. **Given** um cliente com R$ 5,00 de saldo real e R$ 20,00 de bônus de esportes, **When** aposta
   R$ 10,00, **Then** a aposta é paga com o bônus de esportes e o saldo real não muda.
3. **Given** um cliente com R$ 5,00 de saldo real e R$ 8,00 de bônus de esportes, **When** aposta
   R$ 10,00, **Then** a aposta é recusada com "Você não tem saldo suficiente para realizar esta
   aposta", sem combinar as carteiras e sem nenhum débito.
4. **Given** um cliente com saldo apenas no bônus de cassino, **When** aposta em esportes, **Then**
   a aposta é recusada por falta de saldo (o bônus de cassino nunca é usado em esportes).
5. **Given** um cliente com R$ 10,00 de saldo real, **When** dois pedidos de aposta de R$ 10,00
   chegam ao mesmo tempo, **Then** só um é aceito e o outro é recusado por falta de saldo.
6. **Given** um cliente que já apostou o `valor_maximo_diario` hoje, **When** tenta apostar mais,
   **Then** a aposta é recusada.
7. **Given** um cliente com R$ 100,00 de saldo, **When** a aposta é aceita, **Then** ela não
   consome limite nem gera comissão de nenhum vendedor.

---

### User Story 3 - Visitante gera um código de aposta (Priority: P1)

O visitante, sem login, monta uma aposta só com jogos do pré-jogo e recebe um código de 8
caracteres. A aposta fica Pendente até um vendedor validá-la; enquanto isso não vale como aposta.

**Why this priority**: é como a banca física recebe apostas feitas pelo celular do apostador.

**Independent Test**: como visitante, montar uma aposta em 2 jogos do pré-jogo e conferir o código
de 8 caracteres e a aposta Pendente; tentar incluir um jogo do ao vivo e conferir a recusa.

**Acceptance Scenarios**:

1. **Given** jogos futuros dentro do período do visitante, **When** o visitante envia a aposta,
   **Then** recebe um código alfanumérico de 8 caracteres (sem 0, O, 1 e I) e a aposta fica
   Pendente, sem movimentar saldo nem contar em limite.
2. **Given** uma aposta com um jogo do ao vivo, **When** o visitante a envia, **Then** é recusada
   com "Para apostar no ao vivo é preciso fazer login".
3. **Given** a regra do visitante de no mínimo R$ 2,00 e no máximo 20 palpites, **When** ele envia
   R$ 1,00 ou 21 palpites, **Then** a aposta é recusada com o motivo.
4. **Given** uma `data_travamento_sistema` do visitante já passada, **When** ele tenta apostar,
   **Then** é recusado com "Sistema travado, procure seu gerente".
5. **Given** o mesmo IP enviando muitas apostas em sequência, **When** passa do limite de
   tentativas, **Then** os pedidos seguintes são recusados por um tempo.
6. **Given** o visitante com vários códigos guardados no aparelho, **When** consulta até 50 códigos
   de uma vez, **Then** recebe código, valor, prêmio, situação, resultado e data de cada um.

---

### User Story 4 - Vendedor valida o código do visitante (Priority: P1)

O vendedor informa o código. O sistema mostra a aposta como uma simulação, recalculada com as
cotações e as regras do vendedor. O vendedor pode ajustar jogos, valor e nome e então "Validar".
A mesma aposta vira Ativa, ligada ao vendedor.

**Why this priority**: sem a validação o código do visitante não vira aposta; é o fechamento do
fluxo da US3.

**Independent Test**: gerar um código como visitante, consultar como vendedor e conferir as
cotações do vendedor; validar e conferir a aposta Ativa com o mesmo código, limites abatidos e
comprovante; validar de novo e conferir a recusa.

**Acceptance Scenarios**:

1. **Given** um código Pendente, **When** o vendedor o consulta, **Then** recebe os palpites com as
   cotações dele, o valor, o nome e o prêmio calculado com as regras dele, e nada é gravado.
2. **Given** um código Pendente com um jogo que já começou, **When** o vendedor o consulta,
   **Then** esse palpite vem marcado com o motivo, e a validação só passa depois de removê-lo.
3. **Given** a simulação, **When** o vendedor retira um jogo, inclui outro do pré-jogo, muda o
   valor e valida, **Then** a mesma aposta (mesmo código) vira Ativa com os palpites e o valor
   validados, `validada_em` preenchido, ligada ao vendedor, e os limites dele são abatidos.
4. **Given** um código já validado, **When** outro pedido de validação chega (inclusive ao mesmo
   tempo), **Then** é recusado com "Aposta não encontrada ou já validada".
5. **Given** a simulação, **When** o vendedor tenta incluir um jogo do ao vivo, **Then** a
   validação é recusada.
6. **Given** um código cuja validade (`horas_validade_codigo`) passou ou cujo primeiro jogo
   começou, **When** é consultado, **Then** vem como Expirado e não pode ser validado.
7. **Given** um vendedor errando códigos repetidamente, **When** passa do limite de tentativas,
   **Then** as consultas seguintes são recusadas por um tempo.
8. **Given** um vendedor com `ganho_multiplo_palpites` de 10% e uma aposta de 3 jogos, **When** ele
   valida o código, **Then** o acréscimo é aplicado (no sistema antigo não era).
9. **Given** um código gerado há horas e uma cotação que mudou entre a simulação mostrada ao
   vendedor e a validação, **When** o vendedor valida, **Then** a aposta não é validada e a resposta
   informa a alteração do prêmio (cotação anterior e nova de cada palpite e o novo prêmio); o
   vendedor confirma validando de novo com as cotações novas.

---

### User Story 5 - Cotação alterada ou indisponível no momento da aposta (Priority: P1)

Quando a cotação de um palpite mudou entre o que o apostador viu e o envio, o sistema não grava a
aposta sem o consentimento dele: devolve as cotações novas e o novo prêmio para ele confirmar, a
menos que a preferência "aceitar alterações" já cubra a mudança. Cotação bloqueada nunca vira 1,00.

**Why this priority**: resolve a principal reclamação dos apostadores no sistema antigo (odd
virando 1,00) e impede apostas com cotação diferente da combinada.

**Independent Test**: ver uma cotação 2,00, alterá-la para 1,80 no banco e enviar a aposta com
cada preferência; zerar uma cotação e enviar.

**Acceptance Scenarios**:

1. **Given** o apostador viu 2,00 e a cotação atual é 1,80, com preferência Nenhuma, **When** envia
   a aposta, **Then** ela não é gravada e a resposta traz o palpite com 2,00 → 1,80, o novo prêmio
   e um aviso para confirmar.
2. **Given** a resposta do cenário 1, **When** o apostador reenvia com 1,80, **Then** a aposta é
   validada do zero e gravada com 1,80.
3. **Given** o apostador viu 2,00 e a atual é 2,10, com preferência Somente para maior, **When**
   envia, **Then** a aposta é gravada direto com 2,10.
4. **Given** o apostador viu 2,00 e a atual é 1,80, com preferência Somente para maior, **When**
   envia, **Then** a aposta não é gravada e a confirmação é pedida.
5. **Given** preferência Qualquer, **When** a cotação mudou para mais ou para menos, **Then** a
   aposta é gravada com a cotação atual.
6. **Given** um palpite cuja cotação ficou zerada ou travada, **When** a aposta é enviada, **Then**
   ela não é gravada e a resposta lista o palpite como indisponível para remoção; em nenhum caso a
   aposta é gravada com cotação 1,00 no lugar da bloqueada.

---

### User Story 6 - Aposta no ao vivo com delay (Priority: P1)

Vendedor e cliente apostam em jogos do ao vivo. Como os dados do ao vivo chegam com atraso, a
aposta fica "Em análise" pelo tempo de delay do apostador. No fim, o sistema confere com os dados
mais novos se algo aconteceu e decide sozinho, uma única vez, se aceita ou recusa.

**Why this priority**: o ao vivo é onde o risco de apostar sabendo o resultado é maior; sem o
delay seguro a banca fica exposta.

**Independent Test**: com delay de 15 segundos, apostar num jogo do ao vivo e conferir "Em
análise"; deixar o jogo atualizar sem mudanças e conferir a aceitação após 15 segundos; repetir
alterando o placar durante o delay e conferir a recusa sem débito.

**Acceptance Scenarios**:

1. **Given** um cliente com `delay_ao_vivo` 15 e um jogo do ao vivo dentro do minuto limite,
   **When** aposta, **Then** a resposta é imediata com situação "Em análise" e o tempo restante, sem
   débito de saldo.
2. **Given** a aposta em análise e o jogo atualizado depois do envio sem mudança de placar nem de
   situação, **When** termina o delay, **Then** a aposta vira Ativa, o saldo é debitado e o
   comprovante fica disponível na consulta.
3. **Given** a aposta em análise, **When** o placar muda durante o delay, **Then** a aposta é
   Recusada com o motivo e nada é debitado.
4. **Given** a aposta em análise, **When** o jogo não recebe nenhuma atualização do provedor depois
   do envio até o fim do delay, **Then** a aposta é Recusada.
5. **Given** a aposta em análise com preferência Nenhuma, **When** uma cotação cai durante o delay,
   **Then** a aposta é Recusada; com preferência Qualquer, é aceita com a cotação nova.
6. **Given** a aposta em análise, **When** uma cotação sobe durante o delay, **Then** a aposta é
   aceita (com a cotação nova em Somente para maior ou Qualquer, com a congelada em Nenhuma), sem
   perguntar de novo ao apostador.
7. **Given** um apostador com uma aposta em análise, **When** envia outra aposta com ao vivo,
   **Then** ela é recusada até a primeira ser decidida.
8. **Given** uma aposta em análise cuja decisão falhou, **When** passa o delay mais a margem de
   segurança, **Then** ela é Recusada automaticamente sem débito.
9. **Given** um jogo encerrado, além do minuto limite, travado ou com a trava geral ligada, **When**
   o apostador tenta apostar nele, **Then** é recusado com "O confronto CASA x FORA já foi
   encerrado ou passou do tempo para aposta" (ou o motivo da trava).
10. **Given** um palpite em jogador num jogo do ao vivo, **When** a aposta é enviada, **Then** é
    recusada.
11. **Given** um vendedor ou cliente sem permissão de ao vivo, **When** aposta no ao vivo, **Then**
    é recusado com "Você não tem permissão para apostar em jogos ao vivo".

---

### User Story 7 - Limites da banca e cálculo do prêmio (Priority: P1)

Toda aposta respeita os limites da banca: multiplicador, prêmio máximo, odd mínima e máxima, valor
mínimo e máximo, quantidade de palpites e limite de valor apostado por confronto. O ganho por
múltiplos palpites aumenta o prêmio sem passar do prêmio máximo.

**Why this priority**: protege a banca contra prêmios fora do combinado e concentração de risco
num confronto.

**Independent Test**: com multiplicador 1.000, prêmio máximo 5.000,00, ganho por múltiplos
palpites de 10% e um confronto com limite de R$ 100,00, apostar combinações e conferir os valores.

**Acceptance Scenarios**:

1. **Given** multiplicador 1.000 e prêmio máximo R$ 10.000,00, **When** se aposta R$ 5,00 com
   cotação total 1.500,00, **Then** o prêmio é R$ 5.000,00.
2. **Given** prêmio máximo R$ 3.000,00 no mesmo cenário, **When** se aposta, **Then** o prêmio é
   R$ 3.000,00.
3. **Given** ganho por múltiplos palpites de 10%, **When** se aposta R$ 10,00 em 3 jogos com
   cotação total 5,00, **Then** o prêmio é R$ 50,00, o acréscimo R$ 5,00 e o total R$ 55,00.
4. **Given** ganho de 10% e prêmio máximo R$ 5.000,00, **When** o prêmio calculado é R$ 4.900,00,
   **Then** o acréscimo é R$ 100,00 e o total R$ 5.000,00.
5. **Given** ganho de 10%, **When** a aposta tem 2 jogos, **Then** não há acréscimo.
6. **Given** um confronto com `limite_valor_apostado` R$ 100,00 e R$ 95,00 já em apostas Ativas ou
   Em análise, **When** um vendedor ou cliente aposta R$ 10,00 nele, **Then** é recusado com
   "Restam R$ 5,00 de limite de aposta no confronto CASA x FORA".
7. **Given** o mesmo confronto, **When** uma aposta Ativa nele é cancelada, **Then** o valor dela
   volta a ficar disponível no limite do confronto.
8. **Given** odd mínima 1,50 sobre a cotação total, **When** a cotação total é 1,40, **Then** a
   aposta é recusada com o motivo.

---

### User Story 8 - Rollover e regras do bônus do cliente (Priority: P2)

As apostas do cliente abatem o rollover: apostas com saldo real abatem o rollover de depósito, e
apostas com bônus de esportes abatem o rollover do bônus. Enquanto o bônus tem rollover pendente,
as apostas pagas com ele seguem as regras de uso da promoção.

**Why this priority**: sem o abatimento o cliente nunca cumpre o rollover; sem as regras o bônus é
retirado fácil demais. Depende da US2.

**Independent Test**: dar a um cliente o bônus de Primeiro cadastro de R$ 20,00 com rollover 5 e
regras de uso; apostar com o bônus dentro e fora das regras e conferir o valor apostado no
registro de rollover.

**Acceptance Scenarios**:

1. **Given** um cliente recém-cadastrado com bônus de Primeiro cadastro de R$ 20,00 e rollover 5,
   **When** o bônus é creditado, **Then** existe um registro de rollover do tipo Bônus exigindo R$
   100,00, com as regras de uso da promoção gravadas.
2. **Given** esse bônus pendente com valor mínimo de aposta R$ 5,00, **When** o cliente aposta R$
   2,00 pagando com o bônus, **Then** é recusado com "Para apostas usando saldo do bônus o valor
   mínimo é R$ 5,00".
3. **Given** odd mínima de aposta simples 1,80 no bônus, **When** o cliente aposta com o bônus em 1
   palpite de 1,50, **Then** é recusado com "Para apostas simples usando saldo do bônus a cotação
   mínima é 1,80"; com 2 ou mais palpites vale a odd mínima de aposta múltipla.
4. **Given** o bônus pendente, **When** o cliente aposta R$ 10,00 pagando com o bônus, **Then** o
   registro de rollover do bônus passa a R$ 10,00 apostados.
5. **Given** um registro de rollover de Depósito pendente (criado no futuro pela spec de
   depósitos), **When** o cliente aposta R$ 30,00 com saldo real, **Then** o registro de depósito
   soma R$ 30,00 e o do bônus não muda.
6. **Given** dois registros pendentes do mesmo tipo, **When** uma aposta passa do que falta no mais
   antigo, **Then** o excedente vai para o seguinte e o mais antigo fica cumprido (`cumprido_em`).
7. **Given** uma aposta que somou no rollover, **When** ela é cancelada, **Then** o que ela somou é
   desfeito exatamente.
8. **Given** um cliente pagando com saldo real, **When** aposta, **Then** as regras de uso do bônus
   não se aplicam.

---

### User Story 9 - Cancelamento de aposta pelo vendedor e pela hierarquia (Priority: P2)

O vendedor pode cancelar uma aposta Ativa própria dentro do tempo permitido, antes de qualquer jogo
começar e sem jogo ao vivo. Os usuários acima dele (Gerente, Supervisor e Admin), com permissão,
cancelam apostas da sua hierarquia sem limite de tempo. O cliente online não cancela. Uma aposta de
cliente cancelada devolve o valor na carteira de onde saiu; a de vendedor devolve os limites de
venda.

**Why this priority**: corrige erros de digitação e jogos com problema, mas o sistema funciona sem
ele.

**Independent Test**: com `tempo_cancelamento_aposta` de 5 minutos, o vendedor aposta e cancela em
1 minuto; tenta cancelar outra depois de 6 minutos; o gerente dele cancela essa outra; um Admin
cancela uma aposta de cliente; conferir saldo, limites e transações.

**Acceptance Scenarios**:

1. **Given** um vendedor com `cancelar_aposta` liberado e uma aposta Ativa de 2 jogos feita há 1
   minuto (tempo 5), **When** cancela, **Then** a aposta fica Cancelada e os limites duplo e geral
   voltam a ter o valor.
2. **Given** uma aposta do vendedor feita há 6 minutos com tempo 5, **When** ele cancela, **Then** é
   recusado com "O seu tempo de 5 minuto(s) para cancelar terminou".
3. **Given** uma aposta do vendedor com um jogo já iniciado ou com jogo do ao vivo, **When** ele
   cancela, **Then** é recusado com o motivo.
4. **Given** um vendedor com `cancelar_aposta` bloqueado ou sem a permissão, **When** cancela,
   **Then** é recusado.
5. **Given** um Gerente com a permissão `apostas.cancelar` e uma aposta de um vendedor dele feita há
   2 horas, **When** cancela, **Then** a aposta fica Cancelada, sem limite de tempo.
6. **Given** um Gerente sem a permissão `apostas.cancelar_iniciada`, **When** cancela uma aposta
   com jogo já iniciado, **Then** é recusado; com a permissão, o cancelamento é aceito.
7. **Given** um Gerente, **When** tenta cancelar a aposta de um vendedor de outro gerente, **Then** é
   recusado (fora da hierarquia).
8. **Given** um Gerente, Supervisor ou Admin com a permissão e uma aposta Ativa de cliente paga com
   saldo real, **When** cancela, **Then** a aposta fica Cancelada, o valor volta ao saldo real por uma transação de
   origem Estorno e o rollover somado é desfeito; se foi paga com o bônus de esportes, o valor volta
   ao bônus.
9. **Given** um cliente logado, **When** tenta cancelar uma aposta própria, **Then** não existe essa
   opção para ele.
10. **Given** dois pedidos de cancelamento da mesma aposta ao mesmo tempo, **When** chegam, **Then**
    o valor é devolvido uma única vez.
11. **Given** uma aposta Pendente de visitante, Em análise, Recusada ou Expirada, **When** alguém
    tenta cancelá-la, **Then** é recusado.
11a. **Given** uma aposta Ativa com resultado Vencedor ou Perdedor, **When** qualquer usuário,
     inclusive Admin, tenta cancelá-la, **Then** é recusado com "Não é possível cancelar uma aposta
     já apurada".
12. **Given** uma aposta validada a partir de um código, **When** o tempo de cancelamento do
    vendedor é conferido, **Then** ele conta a partir da validação.

---

### User Story 10 - Editar a aposta cancelando ou restaurando um palpite (Priority: P2)

Um usuário do painel com permissão edita uma aposta Ativa da sua hierarquia: cancela um palpite
(por exemplo, um jogo adiado ou com problema) ou restaura um palpite cancelado. O sistema recalcula
a cotação total e o prêmio só com os palpites ativos e registra quem alterou.

**Why this priority**: resolve jogos cancelados ou com erro sem cancelar a aposta inteira; depende
das apostas já gravadas.

**Independent Test**: com uma aposta de R$ 10,00 em 3 jogos (2,00 × 1,50 × 2,00, prêmio R$ 60,00),
cancelar o palpite de 1,50 e conferir prêmio R$ 40,00; restaurar e conferir R$ 60,00; conferir o
histórico.

**Acceptance Scenarios**:

1. **Given** uma aposta Ativa de R$ 10,00 com cotações 2,00, 1,50 e 2,00 (prêmio R$ 60,00), **When**
   um usuário com a permissão `apostas.editar` cancela o palpite de 1,50, **Then** o palpite fica
   cancelado, a cotação total passa a 4,00 e o prêmio a R$ 40,00.
2. **Given** o palpite cancelado do cenário 1, **When** o usuário o restaura, **Then** a cotação
   total volta a 6,00 e o prêmio a R$ 60,00.
3. **Given** a edição, **When** o prêmio é recalculado, **Then** valem o multiplicador, o prêmio
   máximo e o ganho por múltiplos palpites gravados na aposta (o acréscimo some se ficarem menos de
   3 palpites ativos e volta se forem restaurados).
4. **Given** uma aposta com um único palpite ativo, **When** o usuário tenta cancelá-lo, **Then** é
   recusado com a orientação de cancelar a aposta inteira.
5. **Given** uma edição, **When** é concluída, **Then** existe um registro com o palpite, a ação
   (cancelar ou restaurar), cotação total e prêmio antes e depois, autor, data, IP e user agent, e
   a assinatura da aposta é refeita.
6. **Given** a edição de uma aposta de cliente ou de vendedor, **When** é concluída, **Then** o
   valor apostado, o saldo do cliente, os limites de venda, a comissão e o rollover não mudam.
7. **Given** um usuário sem a permissão ou fora da hierarquia, ou um vendedor ou cliente, **When**
   tenta editar, **Then** é recusado.
8. **Given** uma aposta Cancelada, Pendente, Em análise, Recusada ou Expirada, ou com resultado já
   apurado, **When** alguém tenta editar, **Then** é recusado.
9. **Given** a aposta editada, **When** é consultada, **Then** o comprovante mostra o palpite
   cancelado marcado como cancelado e fora da cotação total.

---

### User Story 11 - Comprovante e consulta da aposta (Priority: P2)

Ao criar, validar ou consultar uma aposta, o sistema devolve todos os dados para o front montar o
comprovante ou enviar o bilhete, sem expor dados internos.

**Why this priority**: o apostador precisa do comprovante, mas o comprovante depende das apostas
já gravadas (US1 a US4).

**Independent Test**: criar uma aposta de vendedor e consultar o código como visitante e como o
vendedor; conferir os campos e a ausência de dados internos.

**Acceptance Scenarios**:

1. **Given** uma aposta Ativa de vendedor, **When** é criada ou consultada pelo código, **Then** o
   retorno traz código, situação, nome do apostador, nome do vendedor, nome do sistema, data e hora
   no fuso pedido, valor, cotação total, prêmio, acréscimo, total a pagar, prêmio líquido, forma de
   pagamento, mensagem do bilhete, assinatura e os palpites com campeonato, times, início, esporte,
   mercado com nome legível, jogador e tipo quando houver e cotação.
2. **Given** uma aposta de cliente, **When** é consultada, **Then** o campo de quem vendeu mostra
   "Cliente" e a mensagem do bilhete é a do site.
3. **Given** `comissao_por_premio` de 10% do vendedor e total a pagar de R$ 100,00, **When** a
   aposta paga em dinheiro é consultada, **Then** o prêmio líquido é R$ 90,00; em aposta de cliente
   o prêmio líquido é igual ao total.
4. **Given** qualquer consulta pública, **When** a resposta é montada, **Then** não traz
   porcentagens, cotação original do provedor, IPs nem a comissão do vendedor.
5. **Given** uma aposta do ao vivo, **When** é consultada, **Then** cada palpite ao vivo traz o
   placar e o minuto no momento da aposta.
6. **Given** uma aposta Em análise ou Recusada, **When** é consultada, **Then** traz a situação e o
   tempo restante ou o motivo da recusa.
7. **Given** uma aposta alterada direto no banco (valor ou prêmio), **When** a assinatura é
   conferida, **Then** a adulteração é detectada.

---

### User Story 12 - Configurações de aposta por público e reflexo na listagem (Priority: P2)

Cada público tem as próprias regras de aposta: o vendedor em `usuarios_configuracoes`, o cliente em
`clientes_configuracoes` e o visitante em `visitantes_configuracoes`. A hierarquia altera as dos
vendedores como já faz na spec 003. A listagem de jogos passa a esconder o que o público não pode
apostar.

**Why this priority**: as regras precisam existir para as stories P1 funcionarem com valores
próprios de cada público; os valores padrão já permitem operar.

**Independent Test**: alterar o período de jogos de um vendedor para Hoje, listar e conferir só os
jogos de hoje; tentar apostar num jogo de amanhã e conferir a recusa; desligar `apostar_jogadores`
e conferir que as cotações de jogador somem da listagem e são recusadas na aposta.

**Acceptance Scenarios**:

1. **Given** um vendedor novo, **When** é cadastrado, **Then** tem as configurações de aposta com
   os valores padrão (ou copiadas de um colega, como na spec 003).
2. **Given** um gerente com acesso ao vendedor, **When** altera a comissão de 3 jogos do pré-jogo
   dele, **Then** a próxima aposta de 3 jogos usa o novo percentual e as anteriores mantêm o
   gravado.
3. **Given** `periodo_jogos` Hoje, **When** o público lista os jogos ou aposta num jogo de amanhã,
   **Then** o jogo não aparece e a aposta é recusada.
4. **Given** `data_travamento_sistema` de um vendedor amanhã às 12h, **When** ele lista ou aposta,
   **Then** não vê nem aposta em jogos que comecem depois disso; a partir dessa hora não vê jogo
   nenhum, não aposta, não valida código e não cancela.
5. **Given** `apostar_jogadores` desligado, **When** o público lista ou aposta, **Then** as
   cotações de jogador não aparecem e o palpite em jogador é recusado.
6. **Given** um usuário do painel com a permissão própria, **When** altera o
   `limite_valor_apostado` de um confronto, **Then** o novo limite vale para as próximas apostas.
7. **Given** alterações de configuração com valores incoerentes (mínimo maior que o máximo, tempo
   negativo, percentual fora de 0 a 100), **When** são enviadas, **Then** são recusadas.
8. **Given** um confronto visível ao público, **When** o detalhe dele é pedido, **Then** vêm todas
   as cotações disponíveis já ajustadas para quem pede, com o nome do mercado, e os jogadores
   quando `apostar_jogadores` estiver liberado; a cotação de cada código é igual à aceita na aposta.
9. **Given** um confronto não permitido, fora do período ou de esporte não permitido para o
   público, **When** o detalhe é pedido, **Then** a resposta é 404.

---

### Edge Cases

- Cotação vista igual à atual, mas o prêmio calculado bate no multiplicador ou no prêmio máximo: a
  aposta é gravada sem pedir confirmação; o prêmio limitado aparece no comprovante.
- Código da cotação inexistente ou fora da lista dos códigos válidos: a aposta é recusada; o
  código nunca é usado para montar consulta ao banco.
- Confronto inexistente, excluído, inativo, não permitido para o público ou de esporte não
  permitido: a aposta é recusada citando o jogo.
- Palpite em jogador sem jogador, ou com jogador que não pertence ao confronto do palpite:
  recusado ("Jogador não pertence ao confronto."). O tipo (Primeiro, Último, Qualquer momento) vem
  do registro do jogador, nunca do pedido.
- Um jogo do pré-jogo que entra no ao vivo entre a montagem e o envio: o palpite enviado como
  pré-jogo é recusado ("já iniciou"); para apostar nele, o apostador precisa escolhê-lo no ao vivo,
  com as cotações e as regras do ao vivo.
- Edição de palpite que leva a cotação total abaixo da odd mínima ou o total a pagar a mudar de
  faixa: a edição é aceita (é uma correção do painel) e o prêmio é recalculado com os valores
  gravados na aposta.
- Restaurar um palpite cujo jogo já tem resultado ou foi excluído: o palpite volta com a cotação
  gravada; a apuração é de outra spec.
- Cancelar pela hierarquia uma aposta de cliente: a devolução ao cliente e o desfazer do rollover
  são iguais aos do cancelamento do vendedor.
- Valor com mais de duas casas decimais, zero, negativo ou não numérico: recusado.
- Nome do apostador com HTML ou acima do tamanho máximo: o HTML é removido; acima do tamanho é
  recusado.
- Relógio do aparelho do apostador errado: não interfere, porque nenhum horário do front é usado.
- Aposta com 13 ou mais palpites: usa a comissão da coluna 12.
- Aposta mista (pré-jogo e ao vivo): é tratada como ao vivo (delay, comissão do ao vivo, o vendedor
  não a cancela; a hierarquia sim, com permissão) e o pré-jogo dela continua conferido como pré-jogo.
- Limites de venda: aposta de 1 palpite abate `limite_simples` e `limite_geral`; de 2 ou mais
  abate `limite_duplo` e `limite_geral`.
- Configuração do vendedor alterada enquanto a aposta está em análise: a decisão usa as
  configurações do momento da decisão e grava os valores usados.
- Cliente que muda de saldo durante o delay (outra aposta, ajuste do painel): a decisão confere o
  saldo de novo; se não houver mais, recusa.
- Saldo real que não cobre a aposta e bônus que cobre, mas a aposta fere as regras do bônus: é
  recusada com a regra do bônus (não tenta combinar com o saldo real).
- Bônus de esportes sem rollover pendente: a aposta paga com ele não segue regras de uso.
- Expiração de código e validação ao mesmo tempo: o lock garante que só uma das duas vale.
- Consulta de vários códigos com mais de 50: recusada; códigos inexistentes são ignorados.
- Visitante consultando código de aposta de cliente ou de vendedor pelo código: recebe só os
  dados do comprovante.
- Promoção de Primeiro cadastro com rollover 0: o crédito não cria registro de rollover.

## Requirements *(mandatory)*

### Functional Requirements

**Públicos e permissões**

- **FR-001**: O sistema DEVE permitir apostas de três públicos: visitante (sem login), vendedor
  (usuário com função Vendedor, autenticado no painel) e cliente (autenticado na área do cliente).
  Usuários com função Admin, Supervisor e Gerente NÃO DEVEM apostar nem validar códigos.
- **FR-002**: Cada público DEVE usar apenas as próprias configurações: visitante em
  `visitantes_configuracoes`, vendedor em `usuarios_configuracoes`, cliente em
  `clientes_configuracoes`, e todos as gerais em `configuracoes`. Nenhuma configuração de vendedor
  DEVE valer para cliente, e a aposta do cliente NÃO DEVE consumir limite nem gerar comissão de
  vendedor.
- **FR-003**: As ações do painel DEVEM exigir as permissões spatie do padrão `<recurso>.<acao>`:
  `apostas.criar` e `apostas.validar` (padrão: Vendedor); `apostas.cancelar` (padrão: Vendedor,
  Gerente, Supervisor e Admin); `apostas.cancelar_iniciada` e `apostas.editar` (padrão: Gerente,
  Supervisor e Admin). O cliente aposta pela área do cliente, sem permissões do painel, e NÃO
  cancela nem edita apostas. O vendedor pix e a função pin do sistema antigo não existem no v2.

**Envio da aposta (o servidor decide tudo)**

- **FR-004**: O pedido de aposta DEVE conter apenas: os palpites (id do confronto e código da
  cotação; no pré-jogo, jogador e tipo — Primeiro, Último ou Qualquer momento — quando for aposta
  em jogador), a cotação vista pelo apostador em cada palpite, o valor, o nome do apostador, a
  preferência `aceitar_alteracoes` e uma chave de idempotência.
- **FR-005**: Se o jogo é pré-jogo ou ao vivo, o esporte, as cotações, o prêmio, o acréscimo, a
  comissão, a carteira usada e todos os horários DEVEM ser definidos pelo servidor a partir do
  banco e do relógio do servidor. Nenhum valor desses enviado pelo front DEVE ser usado.
- **FR-006**: O código da cotação DEVE ser conferido contra a lista fechada de códigos válidos
  (`odd1` a `odd323` e os de jogador) e NÃO DEVE ser usado para montar consulta ao banco por
  concatenação.
- **FR-007**: A cotação de cada palpite DEVE ser calculada pelo mesmo cálculo da listagem da spec
  003 (FR-047 e seguintes da spec 003), com as porcentagens do público (visitante e cliente:
  porcentagens de clientes; vendedor: supervisor + gerente + vendedor), campeonato, confronto,
  teto e, no ao vivo, a cotação máxima do ao vivo do público, de modo que a cotação apostada seja
  igual à exibida ao mesmo público no mesmo momento.
- **FR-008**: A aposta DEVE respeitar as mesmas regras de exibição da listagem: esportes
  permitidos, outros esportes, ao vivo habilitado, campeonatos, confrontos e ao vivo não
  permitidos, apostar em jogadores, período de jogos e data de travamento. Um jogo ou cotação que
  não aparece para o público NÃO DEVE poder ser apostado.
- **FR-009**: A chave de idempotência DEVE garantir que o mesmo envio repetido pelo mesmo
  apostador devolva a mesma aposta, sem gravar outra nem debitar de novo. Para o visitante, "mesmo
  apostador" é a mesma aposta ainda Pendente criada pelo mesmo IP. A validação do código também
  exige a chave, e a repetição pelo mesmo vendedor devolve o comprovante já validado.
- **FR-010**: O nome do apostador DEVE ser limpo de qualquer HTML e ter tamanho máximo de 100
  caracteres.
- **FR-011**: Valores em dinheiro DEVEM ter duas casas decimais e cotações duas casas decimais,
  guardados em tipo decimal exato (nunca ponto flutuante).
- **FR-012**: Erros de regra DEVEM ser respondidos como erro de validação (422), com mensagem em
  português que diga o motivo e, quando for o caso, o jogo e o palpite.
- **FR-013**: Nenhuma regra DEVE depender de ids fixos no código; toda regra vem das
  configurações, com um registro por vendedor, por cliente e um do visitante.

**Regras da aposta** (conferidas nesta ordem, com as configurações do público)

- **FR-014**: Permissão: o apostador DEVE estar ativo ("Seu login está inativo") e com
  `realizar_aposta` liberado ("Você não tem permissão para realizar apostas").
- **FR-015**: Palpites: DEVE haver pelo menos um ("Escolha os jogos antes de concluir a aposta") e
  no máximo um por confronto ("Não é possível cadastrar jogos repetidos na aposta").
- **FR-016**: Confronto: DEVE existir, estar ativo ("O confronto CASA x FORA está inativo,
  retire-o para concluir"), ter esporte permitido e não estar entre os campeonatos, confrontos e
  ao vivo não permitidos do público (spec 003). Aposta em jogador só com `apostar_jogadores`
  liberado e com jogador do mesmo confronto do palpite ("Jogador não pertence ao confronto.").
- **FR-017**: Pré-jogo: o jogo NÃO DEVE ter começado nem estar no ao vivo, mesmo antes do horário
  de início ("O confronto CASA x FORA já iniciou, retire-o para concluir"). A listagem do pré-jogo
  da spec 003 DEVE deixar de exibir o jogo que já está no ao vivo.
- **FR-018**: Período de jogos (vendedor, cliente e visitante): só DEVEM ser aceitos jogos que
  começam até o fim do último dia do período do público, no fuso do sistema: Hoje = até o fim de
  hoje; Amanhã = até o fim de amanhã; Depois de amanhã = até o fim de depois de amanhã.
- **FR-019**: Data de travamento do sistema (só vendedor e visitante): a partir de
  `data_travamento_sistema`, o público NÃO DEVE ver jogos nem apostar ("Sistema travado, procure
  seu gerente"); antes dela, NÃO DEVE apostar em jogo que comece depois dela. Vazia = sem trava.
  Para o vendedor, a trava também bloqueia a validação de códigos e o cancelamento.
- **FR-020**: Ao vivo: DEVE estar habilitado para o público (`apostar_ao_vivo` do cliente,
  `ao_vivo_habilitado` do vendedor e geral: "Você não tem permissão para apostar em jogos ao
  vivo"); a trava geral (`configuracoes.ao_vivo_travado`) DEVE estar desligada; o jogo NÃO DEVE
  estar encerrado nem além do minuto limite do público ("O confronto CASA x FORA já foi encerrado
  ou passou do tempo para aposta") e DEVE ter sido atualizado dentro de
  `configuracoes.segundos_trava_ao_vivo`. Aposta em jogador no ao vivo NÃO DEVE ser aceita. O
  visitante NÃO DEVE apostar no ao vivo ("Para apostar no ao vivo é preciso fazer login").
- **FR-021**: Limites da aposta: odd mínima e máxima (sobre a cotação total), valor mínimo e
  máximo, quantidade mínima e máxima de palpites e, para o cliente, o valor máximo apostado por dia
  (soma das apostas Ativas e Em análise do dia, no fuso do sistema).
- **FR-022**: Limite por confronto (vendedor e cliente): a soma do valor das apostas Ativas e Em
  análise que têm o confronto, mais o valor da aposta, NÃO DEVE passar do `limite_valor_apostado`
  do confronto ("Restam R$ X de limite de aposta no confronto CASA x FORA"). O pré-jogo usa o
  limite de `confrontos` e o ao vivo o de `confrontos_ao_vivo`. Apostas Pendentes de visitante não
  contam; contam a partir da validação. A conferência DEVE valer para todos os confrontos da
  aposta.
- **FR-023**: Limites de venda (só vendedor): `limite_simples` (aposta de 1 palpite),
  `limite_duplo` (aposta de 2 ou mais palpites) e `limite_geral` (toda aposta) são saldos
  disponíveis. A aposta DEVE ser recusada se o valor passar do limite aplicável ("Restam R$ X do
  seu limite simples/duplo/geral"); na confirmação, o valor DEVE ser abatido dos limites
  aplicáveis; no cancelamento, devolvido.
- **FR-024**: Comissão (só vendedor): valor × percentual da coluna da quantidade de palpites
  (`comissao_pre_jogo_1` a `comissao_pre_jogo_12`, ou `comissao_ao_vivo_1` a
  `comissao_ao_vivo_12` quando a aposta tem pelo menos um jogo ao vivo); a coluna 12 vale para 12
  ou mais palpites.
- **FR-025**: Prêmio:
  a) prêmio bruto = valor × produto das cotações;
  b) prêmio = menor entre o prêmio bruto, valor × `multiplicador` e `premio_maximo`;
  c) com 3 ou mais palpites e `ganho_multiplo_palpites` > 0, acréscimo
     (`valor_acrescido`) = `ganho_multiplo_palpites`% do prêmio do passo b;
  d) o total a pagar (prêmio + acréscimo) NÃO DEVE passar de `premio_maximo`: o acréscimo é
     reduzido até o total ficar igual ao prêmio máximo.
  O cálculo vale para todos os públicos e também na validação de código.
- **FR-026**: A aposta DEVE gravar os valores usados no momento da confirmação (multiplicador,
  prêmio máximo, ganho por múltiplos palpites, comissão, comissão sobre o prêmio e tempo de
  cancelamento), que não mudam se a configuração mudar depois. A mensagem do bilhete NÃO é gravada
  na aposta: o comprovante usa sempre a mensagem atual das configurações (do vendedor da aposta ou,
  nas apostas de cliente e de visitante, a do site).

**Mudança de cotação e cotação indisponível**

- **FR-027**: Cotação indisponível (zerada, ausente, mercado travado, jogo travado ou trava geral)
  NUNCA DEVE ser substituída por 1,00. A aposta NÃO DEVE ser gravada e a resposta DEVE listar os
  palpites indisponíveis para remoção.
- **FR-028**: A preferência `aceitar_alteracoes` DEVE aceitar Nenhuma (padrão), Somente para maior
  ou Qualquer.
- **FR-029**: No envio, para cada palpite, o servidor DEVE comparar a cotação atual com a vista:
  igual, ou diferente dentro da preferência (subiu, com Somente para maior; qualquer mudança, com
  Qualquer) → segue com a cotação atual; diferente fora da preferência → a aposta NÃO DEVE ser
  gravada, e a resposta DEVE trazer cada palpite com a cotação vista e a atual, o novo prêmio e um
  aviso. A confirmação é um novo envio com as cotações novas, validado do zero, sem nenhum atalho
  guardado do envio anterior.
- **FR-030**: A confirmação de mudança de cotação DEVE acontecer antes do delay do ao vivo; depois
  que a aposta entra em análise, o apostador NÃO DEVE ser consultado de novo.

**Delay do ao vivo**

- **FR-031**: Toda aposta com pelo menos um jogo ao vivo, depois de passar por FR-014 a FR-030,
  DEVE ser gravada com situação Em análise, com as cotações aceitas congeladas e `recebida_em` pelo
  relógio do servidor. Ela NÃO DEVE debitar saldo nem abater limites de venda, mas DEVE contar no
  limite por confronto e no valor máximo diário.
- **FR-031a**: A aposta PODE misturar jogos do pré-jogo e do ao vivo. A aposta mista DEVE seguir
  inteira as regras do ao vivo (delay, comissão do ao vivo, tipo Ao vivo e sem cancelamento pelo
  vendedor), e cada palpite do pré-jogo DEVE continuar conferido pelas regras do pré-jogo.
- **FR-032**: Cada apostador DEVE ter no máximo uma aposta Em análise por vez.
- **FR-033**: O tempo de espera DEVE ser o `delay_ao_vivo` do apostador (cliente ou vendedor), em
  segundos, nunca informado pelo front. O delay DEVE correr uma única vez por aposta e NÃO DEVE
  segurar o pedido do apostador: a resposta é imediata e o apostador acompanha a situação pela
  consulta da aposta.
- **FR-034**: No fim do delay, o sistema DEVE reler os jogos ao vivo da aposta e decidir sozinho:
  a) Recusar se: o placar, a situação ou outro dado de lance de algum jogo mudou desde
     `recebida_em`; algum jogo travou, encerrou ou passou do minuto limite; a trava geral foi
     ligada; algum jogo não recebeu atualização do provedor depois de `recebida_em`; alguma
     cotação ficou indisponível; ou alguma cotação caiu e a preferência não é Qualquer.
  b) Aceitar nos demais casos: cotação que subiu vale a nova com Somente para maior ou Qualquer e
     a congelada com Nenhuma; cotação que caiu vale a nova com Qualquer. O prêmio é recalculado
     pelas cotações finais (FR-025).
  c) Na aceitação, numa operação única e protegida contra concorrência: conferir de novo saldo,
     regras do bônus, limites de venda e valor diário; debitar ou abater; somar ao rollover; e
     marcar a aposta como Ativa. Faltando saldo ou limite, recusar.
- **FR-035**: A recusa DEVE gravar o motivo e a data da decisão, sem debitar nada. Uma aposta Em
  análise sem decisão depois do delay mais uma margem de segurança de 60 segundos DEVE ser
  recusada automaticamente por uma rotina agendada.
- **FR-036**: A aposta do ao vivo DEVE guardar, por palpite ao vivo, placar, minuto, situação e
  última atualização do jogo no envio e na decisão.

**Aposta do visitante e validação do código**

- **FR-037**: A aposta do visitante DEVE ser gravada como Pendente, com código único de 8
  caracteres alfanuméricos maiúsculos sem caracteres ambíguos (0, O, 1, I), gerado por gerador
  aleatório criptograficamente seguro. A aposta Pendente NÃO DEVE movimentar saldo nem contar em
  limites. As regras do visitante (FR-014 a FR-030, exceto as de ao vivo) DEVEM ser aplicadas.
- **FR-038**: O vendedor DEVE poder consultar um código Pendente e receber a simulação: os
  palpites recalculados com as cotações dele, o valor, o nome e o prêmio pelas regras dele, com
  cada palpite fora das regras do vendedor marcado com o motivo. A consulta NÃO DEVE gravar nada.
- **FR-039**: Na validação, o vendedor DEVE poder enviar os palpites (só pré-jogo), o valor e o
  nome ajustados. O sistema DEVE travar a aposta Pendente contra validação simultânea, conferir
  que continua Pendente e aplicar todas as regras de uma aposta do vendedor (FR-014 a FR-030),
  inclusive limites de venda e limite por confronto. Passando, a mesma aposta (mesmo código) DEVE
  virar Ativa, ligada ao vendedor, com os palpites substituídos pelos validados e `validada_em`
  preenchido, sem alterar `created_at`.
- **FR-039a**: Na validação, a cotação vista é a da simulação mostrada ao vendedor. Se alguma
  cotação mudou desde então (por exemplo, código gerado há muito tempo), a validação NÃO DEVE ser
  concluída: a resposta DEVE informar a alteração do prêmio (cotação anterior e nova de cada
  palpite e o novo prêmio), e o vendedor confirma validando de novo com as cotações novas (FR-029).
- **FR-040**: Um código já validado, inexistente ou expirado DEVE ser recusado com "Aposta não
  encontrada ou já validada" (ou "expirada").
- **FR-041**: Uma aposta Pendente DEVE passar a Expirada quando o primeiro jogo dela começar ou
  quando passar `horas_validade_codigo` desde a criação, por uma rotina agendada e também na hora
  da consulta e da validação.
- **FR-042**: A criação de aposta do visitante DEVE ter limite de tentativas por IP, e a consulta e
  a validação de código DEVEM ter limite de tentativas por vendedor e por IP.

**Saldo, bônus e rollover do cliente**

- **FR-043**: A carteira da aposta do cliente DEVE ser escolhida assim: se o saldo real cobre o
  valor inteiro, usa o saldo real; senão, se o `saldo_promocao_esportes` cobre o valor inteiro, usa
  a Promoção esportes; senão, recusa com "Você não tem saldo suficiente para realizar esta aposta".
  Uma aposta NUNCA DEVE combinar carteiras, e a aposta esportiva NUNCA DEVE usar a Promoção cassino.
- **FR-044**: O débito DEVE gerar uma transação em `clientes_transacoes` (origem Aposta, tipo
  Débito, carteira usada, referência à aposta), seguindo as regras de movimentação da spec 002
  (FR-040 a FR-047 da spec 002), protegida contra apostas simultâneas do mesmo cliente.
- **FR-045**: Quando a aposta é paga com a Promoção esportes e o cliente tem bônus de esportes com
  rollover pendente, DEVEM valer as regras de uso gravadas do bônus pendente mais antigo: valor
  mínimo e máximo de aposta ("Para apostas usando saldo do bônus o valor mínimo/máximo é R$ X"),
  odd mínima de aposta simples para 1 palpite e odd mínima de aposta múltipla para 2 ou mais
  ("Para apostas simples/múltiplas usando saldo do bônus a cotação mínima é X").
- **FR-046**: O sistema DEVE manter o acompanhamento de rollover do cliente (`clientes_rollovers`),
  um registro por crédito que exige rollover: tipo (Depósito ou Bônus), carteira, origem, valor
  creditado, vezes exigidas, valor exigido (creditado × vezes), valor já apostado, regras de uso
  gravadas (só Bônus) e `cumprido_em`.
- **FR-047**: O crédito do bônus de Primeiro cadastro (FR-075 da spec 002) DEVE passar a criar o
  registro de rollover do tipo Bônus com o rollover e as regras de uso da promoção, quando o
  rollover for maior que zero. O rollover de depósito é de 1 vez e será criado pela spec de
  depósitos.
- **FR-048**: Cada aposta confirmada do cliente DEVE somar o valor apostado aos rollovers
  pendentes: paga com saldo real → rollovers de Depósito; paga com Promoção esportes → rollovers de
  Bônus de esportes. A soma vai para o registro pendente mais antigo; o que passar do exigido vai
  para o seguinte; o registro que atinge o exigido recebe `cumprido_em`. O sistema DEVE guardar
  quanto cada aposta somou em cada registro.
- **FR-049**: Conversão do bônus em saldo real e liberação de saque NÃO fazem parte desta spec.

**Cancelamento**

- **FR-050**: O vendedor DEVE poder cancelar uma aposta Ativa própria se: tiver a permissão
  `apostas.cancelar` e `cancelar_aposta` liberado; não tiver passado `tempo_cancelamento_aposta`
  minutos desde a confirmação (ou a validação do código) ("O seu tempo de X minuto(s) para
  cancelar terminou"); nenhum jogo tiver começado ("Não é possível cancelar depois do início de um
  jogo"); e não houver jogo ao vivo ("Não é possível cancelar apostas com jogos ao vivo").
- **FR-050a**: Gerente, Supervisor e Admin com a permissão `apostas.cancelar` DEVEM poder cancelar
  apostas Ativas da sua hierarquia (Gerente: dos vendedores dele; Supervisor: dos vendedores dos
  gerentes dele; Admin: de todos), sem limite de tempo e com jogo ao vivo. Aposta com jogo já
  iniciado só com a permissão `apostas.cancelar_iniciada`. Apostas de cliente não pertencem a
  nenhuma hierarquia de vendedor e podem ser canceladas por qualquer Gerente, Supervisor ou Admin
  com a permissão.
- **FR-050b**: O cliente online NÃO DEVE cancelar apostas. A coluna `cancelar_aposta` de
  `clientes_configuracoes` (spec 002) fica sem uso nesta spec e não é removida.
- **FR-051**: O cancelamento DEVE, numa operação única e protegida contra concorrência: marcar a
  aposta como Cancelada com data, autor, IP e user agent, mantendo a aposta e os palpites; devolver
  ao cliente o valor na carteira de onde saiu (transação de origem Estorno referenciando a aposta)
  e desfazer exatamente o que a aposta somou nos rollovers; ou devolver ao vendedor os limites de
  venda abatidos na confirmação (`limite_geral` e, pela quantidade de palpites da confirmação,
  inclusive os cancelados depois por edição, `limite_simples` ou `limite_duplo`). Uma aposta já
  cancelada NÃO DEVE ser cancelada de novo.
- **FR-052**: Apostas Pendentes de visitante, Em análise, Recusadas ou Expiradas NÃO DEVEM ser
  canceladas. Só apostas Ativas com resultado Aguardando DEVEM poder ser canceladas, por qualquer
  pessoa ("Não é possível cancelar uma aposta já apurada"); a correção de apostas apuradas fica para
  a spec de apuração. Restaurar uma aposta inteira cancelada NÃO faz parte desta spec.

**Edição da aposta (cancelar e restaurar palpite)**

- **FR-052a**: Usuários do painel com a permissão `apostas.editar` DEVEM poder, nas apostas Ativas
  com resultado Aguardando da sua hierarquia (mesmo alcance de FR-050a), cancelar um palpite ativo
  ou restaurar um palpite cancelado. Vendedor e cliente NÃO DEVEM editar apostas.
- **FR-052b**: A cotação total e o prêmio DEVEM ser recalculados só com os palpites ativos, pelas
  regras de FR-025 e com os valores de configuração gravados na aposta (multiplicador, prêmio
  máximo e ganho por múltiplos palpites, que vale só com 3 ou mais palpites ativos). O palpite
  restaurado volta com a cotação gravada.
- **FR-052c**: NÃO DEVE ser possível cancelar o último palpite ativo (para isso, cancela-se a
  aposta). A edição NÃO DEVE alterar o valor apostado, o saldo do cliente, os limites de venda, a
  comissão nem o rollover.
- **FR-052d**: Cada edição DEVE ser registrada no histórico da aposta (`apostas_historico`): palpite,
  ação (Cancelar palpite, Restaurar palpite), cotação total e prêmio antes e depois, autor, data,
  IP e user agent. A assinatura da aposta DEVE ser refeita após a edição, e a edição DEVE ser
  protegida contra edições e cancelamentos simultâneos da mesma aposta.
- **FR-052e**: O comprovante DEVE mostrar o palpite cancelado marcado como cancelado e fora da
  cotação total.

**Retorno e consulta**

- **FR-053**: Criar, validar, consultar a situação (quando Ativa) e consultar pelo código DEVEM
  devolver o mesmo formato de comprovante: código, situação, nome do apostador, quem vendeu (nome
  do vendedor ou "Cliente"), nome do sistema, data e hora da aposta e da validação no fuso pedido,
  valor, cotação total, prêmio, acréscimo, total a pagar, prêmio líquido (total menos
  `comissao_por_premio`%, só em apostas pagas em dinheiro), forma de pagamento, mensagem do
  bilhete, assinatura e os palpites (campeonato, times, início, esporte, mercado com nome legível,
  jogador e tipo quando houver, cotação e, no ao vivo, placar e minuto no momento da aposta).
- **FR-054**: As respostas NÃO DEVEM trazer dados internos: porcentagens, cotação original do
  provedor, IPs, user agents e, para quem não é o vendedor da aposta, a comissão.
- **FR-055**: A aposta Em análise DEVE devolver a situação e o tempo restante; a Recusada, o
  motivo; a Pendente, os dados da aposta do visitante.
- **FR-056**: Qualquer pessoa DEVE poder consultar uma aposta pelo código e consultar até 50
  códigos de uma vez (devolvendo código, valor, prêmio, situação, resultado e data); códigos
  inexistentes são ignorados.
- **FR-057**: Cada aposta DEVE ter uma assinatura criptográfica (HMAC com a chave secreta da
  aplicação) dos campos principais (código, valor, cotações, prêmio, acréscimo, data e palpites),
  gravada na confirmação, que permita detectar alteração posterior.

**Auditoria**

- **FR-058**: A aposta DEVE guardar IP e user agent de quem criou, validou, editou e cancelou, e cada
  palpite DEVE guardar a cotação vista, a cotação original do provedor e a cotação final.

**Configurações**

- **FR-059**: `usuarios_configuracoes` (só vendedor) DEVE ganhar, com padrões: `realizar_aposta`
  (liberado), `cancelar_aposta` (liberado), `tempo_cancelamento_aposta` (5 minutos),
  `apostar_jogadores` (liberado), `periodo_jogos` (Depois de amanhã), `data_travamento_sistema`
  (vazia), `mensagem_bilhete` ("BOA SORTE!"), `delay_ao_vivo` (15 segundos),
  `quantidade_minima_opcoes` (1), `quantidade_maxima_opcoes` (20), `valor_minimo_aposta` (2,00),
  `valor_maximo_aposta` (1.000,00), `odd_minima` (1,00), `premio_maximo` (5.000,00),
  `multiplicador` (1.000), `ganho_multiplo_palpites` (0%), `comissao_pre_jogo_1` a
  `comissao_pre_jogo_12` e `comissao_ao_vivo_1` a `comissao_ao_vivo_12` (0%),
  `comissao_por_premio` (0%), `limite_simples`, `limite_duplo` e `limite_geral` (5.000,00 cada).
  Essas colunas seguem o fluxo de consulta e alteração por hierarquia das configurações de
  vendedores da spec 003.
- **FR-060**: `clientes_configuracoes` DEVE ganhar: `apostar_jogadores` (liberado), `periodo_jogos` (Depois de amanhã), `delay_ao_vivo` (15),
  `multiplicador` (1.000) e `ganho_multiplo_palpites` (0%). `cancelar_aposta` já existe (spec 002).
- **FR-061**: `visitantes_configuracoes` DEVE ganhar: `apostar_jogadores` (liberado),
  `periodo_jogos` (Depois de amanhã), `data_travamento_sistema` (vazia), `quantidade_minima_opcoes`
  (1), `quantidade_maxima_opcoes` (20), `valor_minimo_aposta` (2,00), `valor_maximo_aposta`
  (1.000,00), `odd_minima` (1,00), `premio_maximo` (5.000,00), `multiplicador` (1.000),
  `ganho_multiplo_palpites` (0%) e `horas_validade_codigo` (48).
- **FR-062**: `configuracoes` DEVE ganhar a `mensagem_bilhete` do site (usada nas apostas sem
  vendedor: de clientes e de visitantes ainda Pendentes) e o nome do sistema, se ainda não existir, para o comprovante.
- **FR-063**: `confrontos` DEVE ganhar `limite_valor_apostado` (padrão 50.000,00) e
  `confrontos_ao_vivo` também (padrão 5.000,00). Usuários do painel com a permissão própria (ex.:
  `confrontos.alterar_limite`) DEVEM poder alterar o limite de um confronto.
- **FR-064**: As alterações de configuração DEVEM ser coerentes: mínimos ≤ máximos; valores e
  tempos ≥ 0; percentuais entre 0 e 100; multiplicador e prêmio máximo > 0; `periodo_jogos` entre
  os valores do enum.
- **FR-065**: A listagem da spec 003 DEVE passar a respeitar `apostar_jogadores` (esconder as
  cotações de jogador), `periodo_jogos` (esconder os dias além do período) e
  `data_travamento_sistema` (vendedor e visitante: nada a partir da data e só jogos que começam
  antes dela), para que o exibido seja igual ao que pode ser apostado.

**Detalhe do confronto**

- **FR-065a**: O sistema DEVE ter uma rota pública de detalhe de um confronto do pré-jogo e uma de
  um jogo do ao vivo, separadas da listagem, que identificam o público pelo token como a listagem
  (spec 003) e devolvem todas as cotações disponíveis (diferentes de zero) já ajustadas pelo mesmo
  cálculo (FR-007), com o nome legível de cada mercado, e os jogadores com as cotações ajustadas
  quando `apostar_jogadores` estiver liberado (só pré-jogo). No ao vivo, jogo travado devolve as
  cotações zeradas e marcado como travado.
- **FR-065b**: O detalhe DEVE seguir as mesmas regras de exibição da listagem (FR-008): confronto
  não visível para o público responde como inexistente (404). O detalhe tem o mesmo limite de
  tentativas da listagem pública.

**Rotinas e documentação**

- **FR-066**: Rotinas agendadas DEVEM expirar as apostas Pendentes (FR-041) e recusar as apostas
  Em análise presas (FR-035).
- **FR-067**: A coleção do Postman DEVE ser regenerada com todas as rotas novas e alteradas.

### Key Entities

- **Aposta** (`apostas`): uma aposta de visitante, vendedor ou cliente. Código único, nome do
  apostador, valor, cotação total, prêmio, valor acrescido, comissão, valores de configuração
  usados (comissão sobre o prêmio, multiplicador, prêmio máximo, ganho por múltiplos palpites,
  tempo de cancelamento), situação (Pendente, Em análise, Ativa, Recusada,
  Expirada, Cancelada), resultado (Aguardando, Vencedor, Perdedor; apurado em outra spec), tipo
  (Pré-jogo, Ao vivo), forma de pagamento (Dinheiro, Saldo, Promoção esportes), preferência de
  aceitar alterações, vendedor (usuário), cliente, datas de recebimento, decisão, validação e
  cancelamento, autor do cancelamento, motivo da recusa, assinatura, chave de idempotência, IPs e
  user agents.
- **Palpite da aposta** (`apostas_palpites`): um palpite de uma aposta. Confronto do pré-jogo ou do
  ao vivo, esporte, código da cotação, jogador e tipo (quando houver), cotação vista, cotação
  original do provedor, cotação final, dados do ao vivo no envio e na decisão e situação (Ativo,
  Cancelado), com quem e quando cancelou ou restaurou. No máximo um por confronto em cada aposta.
- **Histórico da aposta** (`apostas_historico`): cada edição de palpite (cancelar ou restaurar),
  com cotação total e prêmio antes e depois, autor, data, IP e user agent.
- **Rollover do cliente** (`clientes_rollovers`): valor que o cliente precisa apostar por causa de
  um crédito (depósito ou bônus). Tipo, carteira, origem, valor creditado, vezes, valor exigido,
  valor apostado, regras de uso do bônus e data de cumprimento; com o registro de quanto cada
  aposta somou nele.
- **Configurações de aposta**: novas colunas em `usuarios_configuracoes` (vendedor),
  `clientes_configuracoes` (cliente), `visitantes_configuracoes` (visitante) e `configuracoes`
  (gerais).
- **Limite por confronto**: `limite_valor_apostado` em `confrontos` e `confrontos_ao_vivo`.
- **Transação do cliente** (`clientes_transacoes`, spec 002): recebe os débitos de aposta (origem
  Aposta) e as devoluções do cancelamento (origem Estorno).
- **Enums**: situação, resultado, tipo, forma de pagamento e aceitar alterações da aposta;
  período de jogos (Hoje, Amanhã, Depois de amanhã); tipo de rollover (Depósito, Bônus).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Uma aposta do pré-jogo com até 20 palpites é confirmada e devolve o comprovante em
  menos de 1 segundo.
- **SC-002**: Uma aposta do ao vivo é decidida (aceita ou recusada) em até 5 segundos depois do
  fim do delay do apostador, e 100% das apostas em análise recebem uma decisão.
- **SC-003**: Em 100% das apostas gravadas, nenhuma cotação é 1,00 por substituição de cotação
  bloqueada.
- **SC-004**: Em 100% das apostas gravadas, a cotação de cada palpite é igual à cotação da
  listagem do mesmo público no momento da confirmação (ou à congelada/nova do ao vivo, conforme a
  preferência).
- **SC-005**: Em 100% das apostas do ao vivo em que o placar ou a situação do jogo mudou durante o
  delay, a aposta é recusada.
- **SC-006**: Nenhuma aposta fica em delay mais de uma vez, e nenhum apostador recebe pedido de
  confirmação depois que a aposta entrou em análise.
- **SC-007**: Com 50 pedidos simultâneos de aposta do mesmo cliente, o total debitado nunca passa
  do saldo disponível, e nenhum limite de venda, limite por confronto ou valor diário é
  ultrapassado.
- **SC-008**: Em 100% dos envios repetidos com a mesma chave de idempotência, existe uma única
  aposta e um único débito.
- **SC-009**: Em 100% das apostas, o total a pagar (prêmio + acréscimo) não passa do prêmio máximo
  nem de valor × multiplicador acrescido do ganho permitido.
- **SC-010**: Em 100% dos cancelamentos, o valor devolvido é igual ao debitado, na mesma carteira,
  e o rollover volta exatamente ao valor anterior à aposta.
- **SC-011**: Um código só é validado uma vez, mesmo com 10 validações simultâneas.
- **SC-012**: Encontrar por tentativa um código Pendente válido exige mais tentativas do que o
  limite permite em 24 horas.
- **SC-013**: Em 100% das respostas, não aparecem porcentagens, cotação original do provedor, IPs
  nem comissão para quem não é o vendedor da aposta.
- **SC-014**: Em 100% das apostas, qualquer alteração direta no banco de valor, prêmio ou cotação é
  detectada pela assinatura.
- **SC-015**: Em 100% das tentativas, um jogo ou cotação que não aparece na listagem para o público
  não é aceito na aposta dele.
- **SC-016**: Em 100% das edições, o prêmio recalculado é igual ao cálculo de FR-025 com os
  palpites ativos, e toda edição aparece no histórico da aposta com o autor.
- **SC-017**: Em 100% das tentativas, cliente, vendedor fora do tempo e usuário fora da hierarquia
  ou sem permissão não conseguem cancelar nem editar apostas.
- **SC-018**: Em 100% das validações de código com cotação alterada desde a simulação, o vendedor
  recebe o novo prêmio antes de a aposta ser validada.

## Assumptions

- O pagamento por pix do visitante (copia e cola ou QR code, com nome e telefone obrigatórios,
  prazo para pagar e acesso a esses apostadores) fica para uma spec própria.
- Telas (frontend) estão fora do escopo. A tela de validação do vendedor (simulação com o botão
  "Validar") e o acompanhamento da aposta em análise serão feitos em outra spec, com base nas rotas
  desta.
- Esta spec altera código já implementado de outras specs, por decisão do responsável (Princípio IV
  da constituição): a listagem da spec 003 (FR-065), as configurações de vendedores, clientes e
  visitantes (FR-059 a FR-061) e o crédito do bônus de Primeiro cadastro da spec 002 (FR-047).
- O cálculo de cotação da listagem da spec 003 é reaproveitado sem mudança de regra; esta spec só o
  usa para um conjunto de palpites.
- O dia do valor máximo diário, do período de jogos e da expiração segue o fuso do sistema,
  como nas specs 002 e 003.
- A trava geral, a trava por tempo sem atualização e o minuto limite do ao vivo continuam
  definidos pela spec 003; esta spec só os confere na aposta.
- O vendedor do painel e o cliente da área do cliente já têm autenticação (specs 001 e 002); o
  visitante é identificado só pelo IP para limite de tentativas.
- Limites de tentativas (valores iniciais, ajustáveis no plano): criação de aposta do visitante, 10
  por minuto por IP; consulta e validação de código, 20 por minuto por vendedor e 60 por minuto por
  IP.
- `referencia_id` de `clientes_transacoes` continua sem chave estrangeira, porque também aponta
  para promoções.
- A reposição dos limites de venda (`limite_simples`, `limite_duplo`, `limite_geral`) fica para a
  spec de caixa; até lá, o painel ajusta os limites pelas configurações do vendedor.
- O resultado (Vencedor, Perdedor) e o pagamento do prêmio, inclusive em qual carteira o prêmio cai,
  ficam para a spec de apuração.
- Os registros de rollover de Depósito serão criados pela spec de depósitos e a trava de saque por
  rollover pela spec de saques; esta spec cria a tabela e faz o abatimento.
- A aposta paga com saldo real abate só os rollovers de Depósito, e a paga com bônus abate só os de
  Bônus, como no sistema antigo.
- O tamanho máximo do nome do apostador é 100 caracteres.
