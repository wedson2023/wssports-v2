# Feature Specification: Apuração de resultados e cancelamento de jogos

**Feature Branch**: `009-apuracao-resultados`

**Created**: 2026-10-14

**Status**: Draft

**Input**: User description: "Apuração de resultados e cancelamento de jogos: atualizar o
resultado dos jogos do pré-jogo, decidir o resultado dos palpites e das apostas, pagar os prêmios
no saldo dos clientes, desfazer pagamentos quando o resultado for corrigido e tratar jogos
cancelados ou adiados — com registro de tudo o que acontecer. Base: sistema antigo
(ResultadosCommand, CanceladosCommand, OperacaoController::operacao e
CreditosCommand/apostas:premiadas). NÃO é cópia: as regras de negócio servem de referência, e o
código deve ser reescrito no padrão do v2, corrigindo os defeitos do antigo. (1) Carga de
resultados: placar final, placar de cada tempo, escanteios, situação e gols por jogador; reapurar
só quando o resultado mudar. (2) Apuração: palpite Vencedor, Perdedor, Aguardando ou Devolvido
(1,00); regras dos 323 códigos organizadas por mercado; jogador (Qualquer Momento, Primeiro,
Último); empate anula aposta sem apagar palpite; especiais; blocos do Criar Aposta; ao vivo.
(3) Pagamento: Saldo → carteira Saldo; Promoção esportes → carteira de promoção, sujeito ao
rollover; Dinheiro → sem saldo; janela de segurança configurável; idempotente. (4) Correção:
reapura apostas já decididas, estorna o valor exato pago na mesma carteira (saldo pode ficar
negativo), paga quem passou a ganhar, ajusta só a diferença. (5) Cancelados/adiados: configuração
'remover jogo cancelado automaticamente'; ligada cancela os palpites do jogo (1,00); desligada só
muda a situação; aposta sem palpite restante devolve o valor na mesma carteira e termina Vencedor
com prêmio igual ao valor. (6) Registro de todas as operações (só gravação; tela fica para spec
futura). (7) Robustez: transação por aposta, falha isolada, sem sobreposição, idempotência,
comando manual para reapurar um jogo."

## Clarifications

### Session 2026-10-14 (conversa antes do pedido)

- Q: Para onde vai o prêmio de uma aposta paga com a carteira Promoção esportes? → A: Para a
  carteira Promoção esportes, como no sistema antigo, sujeito às regras de rollover do cliente.
- Q: Quando todos os palpites de uma aposta são cancelados por jogo cancelado ou adiado, onde o
  valor é devolvido? → A: Na mesma carteira usada para pagar a aposta. A aposta termina como
  Vencedor com prêmio igual ao valor apostado (cotação total 1,00), como no sistema antigo.
- Q: A remoção automática dos jogos cancelados continua opcional? → A: Sim. Continua como
  configuração geral (equivalente ao antigo `remover_confronto_automatico`), ligada por padrão
  como no sistema antigo.
- Q: O registro das operações tem tela de consulta nesta spec? → A: Não. Nesta spec entra só a
  gravação; a tela de consulta no painel fica para uma spec futura.
- Q: Quais diferenças em relação ao sistema antigo foram aprovadas? → A: As correções listadas
  abaixo, apresentadas ao responsável na descrição desta spec (Princípio VII):
  - jogo cancelado não apaga palpites: o palpite é cancelado só na aposta afetada (o antigo
    apagava o palpite daquele jogo em todos os bilhetes);
  - o estorno de prêmio debita só o valor pago naquela aposta, com uma transação nova (o antigo
    apagava todas as transações de prêmio do cliente e voltava o saldo para o "saldo anterior",
    perdendo movimentações feitas depois);
  - "empate anula aposta" deixa o palpite como Devolvido (1,00), sem apagar o palpite e sem
    alterar dados dentro da regra de avaliação;
  - as apostas de um jogo só são reapuradas quando o resultado dele muda (o antigo reprocessava
    todos os bilhetes de todos os jogos a cada execução);
  - a aposta com todos os jogos cancelados recebe a devolução de fato (o antigo só marcava o
    bilhete como Vencedor);
  - toda operação de resultado e de saldo fica registrada.

### Session 2026-10-14 (após a spec)

- Q: Quando um jogo é apurado: só quando estiver Encerrado ou sempre que houver placar (como no
  sistema antigo)? → A: Só quando estiver Encerrado e com placar final. Com placar parcial, o
  resultado é guardado, mas nenhum palpite é decidido nem pago. Diferença em relação ao sistema
  antigo aprovada pelo responsável (Princípio VII).
- Q: Um jogo adiado deve ter os palpites cancelados na hora, igual a um jogo cancelado? → A: Sim.
  Adiado é tratado igual a cancelado, sem prazo de espera pela remarcação, como no sistema antigo.
- Q: Até quantos dias depois do jogo uma correção de resultado ainda deve reapurar as apostas e
  mexer no saldo? → A: Sem limite. Qualquer correção, a qualquer tempo, reapura e estorna.
  Diferença em relação ao sistema antigo (que só olhava apostas dos últimos 7 dias) aprovada pelo
  responsável (Princípio VII).
- Q: Se o provedor cancelar um jogo e depois mandar o resultado dele, o que acontece com os
  palpites que o sistema já cancelou? → A: Continuam cancelados. O resultado é guardado e
  registrado, e a restauração fica a critério manual, pela edição da aposta.

### Session 2026-10-14 (plano)

- Q: Três mercados têm regra diferente do nome no sistema antigo (`odd114` "Mais de 3 gols - 2º
  tempo" paga só com exatamente 3 gols; `odd245`/`odd247` "Casa par"/"Fora par" não contam 0 gol
  como par). Manter ou corrigir? → A: Manter como no sistema antigo.

### Session 2026-10-14 (análise)

- Q: Os arquivos existentes listados no plano podem ser alterados (Princípio IV)? → A: Sim, todos.
- Q: Palpite devolvido por "empate anula aposta" é reavaliado se o placar for corrigido? → A: Sim.
  Só os palpites que o próprio sistema devolveu por empate são reavaliados e restaurados; os
  cancelados pelo painel, por categoria especial ou por jogo cancelado continuam fora (FR-013).
- Q: Quando um jogo é cancelado, quais apostas têm o palpite cancelado automaticamente? → A: Só
  apostas Ativas com resultado ainda Aguardando (FR-032).
- Q: A carga de confrontos pode voltar para Aguardando um jogo já Cancelado, Adiado ou Encerrado?
  → A: Não. A carga de confrontos passa a preservar essas situações, para a grade não aceitar
  apostas em jogo cancelado (FR-044). Diferença em relação ao sistema antigo aprovada pelo
  responsável (Princípio VII).
- Q: Como os jogos cadastrados à mão recebem resultado? → A: Por uma rota da API (sem tela nesta
  spec) em que o administrador lança o resultado do jogo manual; depois a apuração roda normalmente
  (US7, FR-045 a FR-047).
- Q: As metas de desempenho do plano são verificadas? → A: Sim, num cenário com massa grande no
  quickstart, anotando o tempo do resumo dos comandos.

### Session 2026-10-27 (alterado pela spec 023)

- O lançamento à mão (FR-045 e FR-046) passa a valer para qualquer jogo, do provedor ou manual,
  como no sistema antigo, e só depois do início ("O resultado só pode ser lançado depois do início
  do jogo."); 1º e 2º tempo e escanteios ficam obrigatórios e a soma dos tempos tem de bater com o
  placar ("A soma dos gols dos times não bate com os gols dos tempos."). Um resultado diferente
  trazido depois pela carga do provedor substitui o lançado e reapura as apostas.
- `confrontos.lancar_resultado` passa a ser só do Admin (o Supervisor perde a permissão).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Resultado do jogo decide palpites e apostas (Priority: P1)

O provedor publica o resultado de um jogo encerrado (placar final, placar de cada tempo,
escanteios e gols por jogador). O sistema grava o resultado, decide cada palpite feito nesse jogo
(Vencedor, Perdedor ou Devolvido) e, a partir dos palpites, decide cada aposta (Vencedor,
Perdedor ou Aguardando, quando ainda há jogos em aberto). Apostador, vendedor e administrador
passam a ver o resultado no comprovante e nas listagens.

**Why this priority**: sem o resultado nenhuma aposta é decidida e nenhum prêmio pode ser pago; é
a base de todo o resto.

**Independent Test**: com apostas ativas em um jogo, simular a carga de resultados com um placar
e conferir o resultado de cada palpite e de cada aposta conforme o mercado escolhido.

**Acceptance Scenarios**:

1. **Given** uma aposta simples em "Casa vence" num jogo que terminou 2 x 1, **When** a carga de
   resultados roda, **Then** o palpite e a aposta ficam Vencedor.
2. **Given** uma múltipla com dois jogos, um encerrado com o palpite certo e outro ainda sem
   resultado, **When** a carga roda, **Then** o palpite do primeiro jogo fica Vencedor e a aposta
   continua Aguardando.
3. **Given** uma múltipla em que um palpite perdeu, **When** a carga roda, **Then** a aposta fica
   Perdedor mesmo com outros jogos em aberto.
4. **Given** um palpite de escanteios e um jogo encerrado sem dado de escanteio, **When** a carga
   roda, **Then** esse palpite continua Aguardando até o dado chegar.
5. **Given** um palpite "jogador marca a qualquer momento" e o jogador marcou, **When** a carga
   roda, **Then** o palpite fica Vencedor; com "primeiro gol", só se o gol dele for o 1º do jogo;
   com "último gol", só se for o último.
6. **Given** um palpite "empate anula aposta – Casa" e o jogo empatou, **When** a carga roda,
   **Then** o palpite fica Devolvido, passa a valer 1,00 na cotação total e o prêmio da aposta é
   recalculado; o palpite continua na aposta.
7. **Given** um palpite de um bloco Criar Aposta que perdeu, **When** a carga roda, **Then** a
   aposta fica Perdedor (o bloco é tudo ou nada).
8. **Given** a carga de resultados roda de novo com o mesmo placar, **When** nada mudou no jogo,
   **Then** nenhuma aposta é reapurada e nenhum registro novo é criado.

---

### User Story 2 - Prêmio creditado no saldo do cliente (Priority: P1)

Quando uma aposta de cliente fica Vencedor, o prêmio é creditado na carteira usada para pagar a
aposta, depois de uma janela de segurança configurável. O cliente vê o valor no saldo e a
transação de prêmio no extrato. Aposta de vendedor (paga em dinheiro) só recebe o resultado.

**Why this priority**: é o que o cliente espera de uma aposta vencedora; erro aqui é dinheiro real.

**Independent Test**: com uma aposta de cliente paga com Saldo decidida como Vencedor, esperar a
janela de segurança e conferir o crédito e a transação ligada à aposta; rodar de novo e conferir
que não há segundo crédito.

**Acceptance Scenarios**:

1. **Given** uma aposta paga com Saldo decidida como Vencedor há mais tempo que a janela de
   segurança, **When** o pagamento roda, **Then** o prêmio (prêmio + valor acrescido) é creditado
   na carteira Saldo com origem Prêmio e referência à aposta.
2. **Given** uma aposta paga com Promoção esportes decidida como Vencedor, **When** o pagamento
   roda, **Then** o prêmio é creditado na carteira Promoção esportes e fica sujeito às regras de
   rollover do cliente.
3. **Given** uma aposta de vendedor (Dinheiro) decidida como Vencedor, **When** o pagamento roda,
   **Then** nenhum saldo é movimentado.
4. **Given** uma aposta decidida como Vencedor há menos tempo que a janela de segurança, **When**
   o pagamento roda, **Then** o prêmio ainda não é creditado.
5. **Given** uma aposta já paga, **When** o pagamento roda de novo, **Then** nenhum crédito novo é
   feito.

---

### User Story 3 - Correção de resultado desfaz ou ajusta o pagamento (Priority: P1)

O provedor corrige o placar de um jogo já apurado. O sistema reapura todas as apostas do jogo,
inclusive as já decididas e pagas. Quem deixou de ganhar tem o prêmio estornado; quem passou a
ganhar recebe; quem ganhava um valor diferente tem só a diferença ajustada.

**Why this priority**: o provedor corrige resultados com frequência; pagar errado sem corrigir é
prejuízo, e corrigir apagando dados (como no antigo) destrói o histórico do cliente.

**Independent Test**: pagar uma aposta, simular a carga com um placar corrigido que a torna
Perdedor e conferir o débito do valor exato na mesma carteira, com o saldo das outras
movimentações preservado.

**Acceptance Scenarios**:

1. **Given** uma aposta paga de R$ 100,00 em Saldo e o placar corrigido a torna Perdedor,
   **When** a carga roda, **Then** a aposta fica Perdedor e R$ 100,00 são debitados da carteira
   Saldo com uma transação de estorno ligada ao crédito original, sem apagar transações.
2. **Given** o cliente já sacou ou apostou o prêmio e o saldo não cobre o estorno, **When** o
   estorno é feito, **Then** o saldo fica negativo pelo valor que faltou.
3. **Given** uma aposta Perdedor que passa a Vencedor com a correção, **When** a carga roda,
   **Then** ela é paga normalmente, respeitando a janela de segurança.
4. **Given** uma aposta paga cujo prêmio muda com a correção (ex.: um palpite passou a Devolvido),
   **When** a carga roda, **Then** só a diferença é creditada ou debitada.
5. **Given** uma aposta decidida como Vencedor e ainda não paga, **When** a correção a torna
   Perdedor dentro da janela de segurança, **Then** nenhum crédito nem estorno acontece.

---

### User Story 4 - Jogo cancelado ou adiado (Priority: P2)

O provedor informa que um jogo foi cancelado ou adiado. O jogo muda de situação. Com a
configuração "remover jogo cancelado automaticamente" ligada, os palpites desse jogo nas apostas
ativas ainda sem resultado (Aguardando) são cancelados pelo sistema (valem 1,00), o prêmio é recalculado e a
aposta é reapurada com os palpites restantes. Se não sobrar palpite, o valor apostado é devolvido.

**Why this priority**: jogos cancelados são menos frequentes que resultados, mas deixam apostas
presas para sempre se não forem tratados.

**Independent Test**: com uma múltipla de dois jogos, simular a carga de cancelados para um
deles e conferir que o palpite foi cancelado, o prêmio recalculado e a aposta reapurada.

**Acceptance Scenarios**:

1. **Given** a configuração ligada e uma múltipla de dois jogos em que um foi cancelado, **When**
   a carga de cancelados roda, **Then** o palpite do jogo cancelado é cancelado (vale 1,00), o
   prêmio é recalculado com histórico e a aposta é decidida só pelo outro jogo.
2. **Given** a configuração ligada e uma aposta simples paga com Saldo cujo único jogo foi
   cancelado, **When** a carga roda, **Then** a aposta termina Vencedor com prêmio igual ao valor
   apostado e o valor volta para a carteira Saldo.
3. **Given** a mesma situação numa aposta de vendedor, **When** a carga roda, **Then** a aposta
   termina Vencedor com prêmio igual ao valor, sem movimentação de saldo.
4. **Given** a configuração desligada, **When** a carga roda, **Then** o jogo muda de situação e
   as apostas ficam Aguardando para tratamento manual.
5. **Given** um palpite cancelado que pertence a um bloco Criar Aposta, **When** a carga roda,
   **Then** vale a liquidação gravada na aposta: Recalcular o bloco sem o palpite ou Anular o
   bloco inteiro.
6. **Given** uma aposta paga em que o outro palpite pertencia ao jogo cancelado, **When** a carga
   roda, **Then** a aposta não é mexida (já liquidada).
7. **Given** um jogo marcado Cancelado, **When** a carga de confrontos recebe o jogo como
   Aguardando, **Then** ele continua Cancelado e não aparece para apostas.

---

### User Story 5 - Registro de todas as operações (Priority: P2)

Cada mudança de resultado, de situação de jogo, de resultado de palpite ou de aposta e cada
movimentação de prêmio, estorno, ajuste ou devolução fica gravada com data, origem, jogo, aposta,
cliente, carteira, valores antes e depois e motivo. O responsável consegue saber depois o que
aconteceu com qualquer aposta.

**Why this priority**: sem registro não há como explicar ao cliente ou ao gerente por que um
saldo mudou; a tela de consulta virá em outra spec, mas os dados precisam existir desde já.

**Independent Test**: executar um ciclo completo (resultado, pagamento, correção com estorno,
cancelamento) e conferir no banco um registro para cada passo, com valores antes e depois.

**Acceptance Scenarios**:

1. **Given** um jogo com placar alterado pelo provedor, **When** a carga roda, **Then** fica
   registrado o resultado anterior e o novo.
2. **Given** um prêmio pago e depois estornado, **When** a correção acontece, **Then** existem
   registros do pagamento e do estorno, com o saldo antes e depois de cada um.
3. **Given** uma reapuração manual de um jogo, **When** o comando roda, **Then** os registros
   gerados indicam a origem "reprocessamento manual".

---

### User Story 6 - Reapurar um jogo manualmente (Priority: P3)

O administrador técnico executa um comando para reapurar um jogo específico (ex.: depois de
corrigir um dado ou de uma falha), com o mesmo efeito da carga automática.

**Why this priority**: ferramenta de suporte; o fluxo normal é automático.

**Independent Test**: rodar o comando para um jogo já apurado sem mudanças e conferir que nada
muda; alterar o resultado gravado e rodar de novo, conferindo a reapuração.

**Acceptance Scenarios**:

1. **Given** um jogo já apurado e sem mudança, **When** o comando roda para ele, **Then** nenhuma
   aposta muda e nenhum saldo é movimentado.
2. **Given** um código de jogo inexistente, **When** o comando roda, **Then** uma mensagem clara
   informa que o jogo não foi encontrado.

---

### User Story 7 - Administrador lança o resultado de um jogo manual (Priority: P3)

O administrador cadastrou um jogo à mão (sem provedor). Quando o jogo termina, ele informa pela
API o placar final e, se quiser, os gols de cada tempo, os escanteios e os autores dos gols. O jogo
fica Encerrado e as apostas dele são apuradas e pagas como as dos jogos do provedor.

**Why this priority**: jogos manuais são exceção; sem isso, as apostas neles ficariam Aguardando
para sempre.

**Independent Test**: com uma aposta num jogo manual, lançar o placar pela rota e conferir o
resultado da aposta e o registro com origem "Lançamento manual".

**Acceptance Scenarios**:

1. **Given** um jogo manual com aposta em "Casa", **When** o administrador lança 2 x 0, **Then** o
   jogo fica Encerrado, a aposta fica Vencedor e o registro tem origem "Lançamento manual".
2. **Given** o resultado já lançado, **When** o administrador corrige para 0 x 1, **Then** as
   apostas são reapuradas, com estorno quando já pagas.
3. **Given** um jogo do provedor, **When** alguém tenta lançar o resultado, **Then** a rota
   recusa com "O resultado de jogos do provedor vem da carga automática.".
4. **Given** um usuário sem a permissão, **When** tenta lançar o resultado, **Then** recebe 403.

### Edge Cases

- Provedor fora do ar ou carga inválida: a execução é registrada no log e nada é alterado; a
  próxima execução tenta de novo.
- Jogo recebido que não existe no sistema (codigo_externo desconhecido): é ignorado.
- Jogo cadastrado à mão (sem codigo_externo): não recebe resultado do provedor; o resultado é
  lançado pelo administrador (US7).
- Falha ao apurar ou pagar uma aposta: a transação daquela aposta é desfeita, o erro é registrado
  e as demais apostas seguem normalmente.
- Duas execuções ao mesmo tempo: a segunda não começa enquanto a primeira não termina, e a trava
  por aposta impede dupla liquidação.
- Aposta Pendente ou Em análise com palpite em jogo encerrado ou cancelado: não é apurada nem
  mexida; só apostas Ativas entram na apuração.
- Aposta Cancelada, Recusada ou Expirada: nunca é apurada nem paga.
- Palpite já cancelado por edição: fica fora da apuração (vale 1,00), como já acontece na edição.
- Jogo cancelado depois de já ter resultado apurado e pago: as apostas já liquidadas não são
  mexidas; o cancelamento só atinge apostas com resultado ainda Aguardando.
- Jogo cancelado que depois volta com resultado (o provedor desfaz o cancelamento): o resultado é
  guardado e registrado, os palpites cancelados pelo sistema continuam cancelados e a restauração
  fica a critério manual, pela edição da aposta.
- Placar com um só lado preenchido ou valores negativos: o item do jogo é ignorado na carga, com o
  motivo no log.
- Jogo Cancelado que o provedor ainda manda na rota de confrontos como Aguardando: continua
  Cancelado na grade e não aceita apostas novas (FR-044).
- Sistema configurado como "somente cassino": as cargas de resultados e de cancelados não rodam.
- Prêmio maior que o prêmio máximo após recálculo: vale o limite gravado na aposta.
- Estorno de aposta paga com Promoção esportes cujo bônus já foi convertido, expirado ou excluído:
  o débito é feito na carteira Promoção esportes mesmo assim, podendo deixá-la negativa.
- Palpite especial dentro de uma múltipla com jogos: o resultado do especial vem do encerramento
  da categoria; a aposta só é decidida quando todos os palpites tiverem resultado.

## Requirements *(mandatory)*

### Functional Requirements

**Carga de resultados**

- **FR-001**: O sistema DEVE buscar periodicamente no provedor os resultados dos jogos do
  pré-jogo, pela mesma integração e no mesmo padrão das cargas existentes (tempo limite, validação
  da carga, registro no log, sem execuções sobrepostas).
- **FR-002**: Para cada jogo recebido e existente no sistema, DEVEM ser guardados: placar final de
  cada time, placar de cada time no 1º e no 2º tempo, escanteios de cada time, situação do jogo e
  os gols por jogador (nome, minuto, ordem do gol e tempo de jogo).
- **FR-003**: O sistema DEVE comparar o resultado recebido com o gravado e só reapurar as apostas
  do jogo quando algo tiver mudado.
- **FR-004**: A apuração de um jogo DEVE ocorrer só quando ele estiver na situação Encerrado e
  com placar final dos dois times. Jogo com placar mas ainda não Encerrado tem o resultado
  guardado, mas seus palpites continuam Aguardando.
- **FR-005**: As cargas de resultados e de cancelados NÃO DEVEM rodar quando o sistema estiver
  configurado como somente cassino.

**Apuração dos palpites**

- **FR-006**: Cada palpite DEVE ter resultado próprio: Aguardando, Vencedor, Perdedor ou
  Devolvido. Devolvido vale 1,00 na cotação total.
- **FR-007**: As regras de avaliação dos 323 códigos de cotação DEVEM ficar organizadas por
  mercado (vencedor da partida, ambas marcam, dupla chance, handicap, total de gols, escanteios,
  resultados por tempo, placar exato e demais), reproduzindo o comportamento do sistema antigo
  para cada código.
- **FR-008**: Palpites de mercados de escanteio DEVEM ficar Aguardando enquanto o jogo não tiver
  dado de escanteio.
- **FR-009**: Palpites de jogador DEVEM ser decididos pelos gols registrados: "Qualquer Momento"
  (o jogador marcou), "Primeiro" (marcou o 1º gol do jogo) e "Último" (marcou o último gol do
  jogo); sem o jogador entre os autores, o palpite é Perdedor.
- **FR-010**: Nos mercados "empate anula aposta" (Casa e Fora), o empate DEVE deixar o palpite
  Devolvido, sem apagar o palpite; vitória do lado escolhido é Vencedor e derrota é Perdedor.
- **FR-011**: Palpites especiais DEVEM usar o resultado definido no encerramento da categoria; a
  apuração dos jogos não altera esse resultado.
- **FR-012**: Palpites do ao vivo DEVEM ser apurados pelo resultado do jogo da grade a que estão
  ligados.
- **FR-013**: Palpites cancelados DEVEM ficar fora da apuração (valem 1,00), exceto os que o
  próprio sistema devolveu por "empate anula aposta": esses são reavaliados quando o resultado do
  jogo muda e, se deixarem de ser empate, são restaurados com o novo resultado. Os cancelados pelo
  painel, por categoria especial ou por jogo cancelado nunca são restaurados pelo sistema.

**Apuração das apostas**

- **FR-014**: Só apostas na situação Ativa DEVEM ser apuradas.
- **FR-015**: O resultado da aposta DEVE ser: Perdedor se algum palpite ativo for Perdedor;
  Aguardando se algum palpite ativo ainda estiver Aguardando; Vencedor se todos os palpites ativos
  forem Vencedor ou Devolvido, ou se não restar palpite ativo.
- **FR-016**: Quando um palpite ficar Devolvido, a cotação total e o prêmio da aposta DEVEM ser
  recalculados com as regras gravadas na aposta (multiplicador, prêmio máximo, acréscimo por
  múltiplos palpites e comissão), da mesma forma que no cancelamento de palpite pela edição.
- **FR-017**: Em blocos Criar Aposta, o palpite Devolvido ou cancelado DEVE seguir a liquidação
  gravada na aposta: Recalcular (o bloco segue com os palpites restantes e o desconto gravado) ou
  Anular bloco (o bloco inteiro vale 1,00). O bloco só é Vencedor se todos os seus palpites
  ativos forem Vencedor.
- **FR-018**: A aposta DEVE guardar quando foi apurada (última mudança de resultado ou de valor a
  pagar), base da janela de segurança do pagamento.
- **FR-019**: Ao ser reapurada, uma aposta já decidida DEVE poder mudar de resultado, inclusive
  voltar a Aguardando.

**Pagamento dos prêmios**

- **FR-020**: Aposta de cliente decidida como Vencedor DEVE ter o prêmio (prêmio + valor
  acrescido) creditado depois da janela de segurança configurável, contada a partir da apuração
  (FR-018).
- **FR-021**: O crédito DEVE ir para a carteira usada no pagamento da aposta: Saldo para apostas
  pagas com Saldo, Promoção esportes para apostas pagas com Promoção esportes. Na carteira de
  promoção, o valor fica sujeito às regras de rollover já existentes do cliente.
- **FR-022**: Aposta paga em Dinheiro (vendedor) NÃO DEVE movimentar saldo.
- **FR-023**: Toda movimentação DEVE ser feita pelo mecanismo de saldo existente, gerando
  transação no extrato do cliente com origem Prêmio (crédito) ou Estorno (débito) e referência à
  aposta.
- **FR-024**: Uma aposta NÃO DEVE ser paga duas vezes: a aposta guarda o valor já pago e cada
  execução só movimenta a diferença entre o valor devido e o valor pago.
- **FR-025**: A janela de segurança DEVE ser uma configuração geral, em minutos, com padrão de 5
  minutos (como no sistema antigo).

**Correção de resultado**

- **FR-026**: Quando o resultado de um jogo mudar, TODAS as apostas Ativas com palpites nesse jogo
  DEVEM ser reapuradas, inclusive as já decididas e pagas, sem limite de tempo desde o jogo ou o
  pagamento.
- **FR-027**: Aposta paga que deixar de ser Vencedor DEVE ter o valor pago debitado na mesma
  carteira em que foi creditado, por uma transação nova de estorno ligada à aposta, sem apagar
  nenhuma transação e sem sobrescrever o saldo.
- **FR-028**: O estorno DEVE ser feito mesmo que o saldo fique negativo.
- **FR-029**: Aposta paga cujo prêmio mudar de valor DEVE receber crédito ou débito apenas da
  diferença.

**Jogos cancelados ou adiados**

- **FR-030**: O sistema DEVE buscar periodicamente no provedor os jogos cancelados ou adiados e
  atualizar a situação de cada jogo (Cancelado ou Adiado). Adiado e Cancelado DEVEM ter o mesmo
  tratamento, sem esperar a remarcação do jogo adiado.
- **FR-031**: DEVE existir a configuração geral "remover jogo cancelado automaticamente",
  editável pela rota de configurações da API (sem tela nesta spec), ligada por padrão.
- **FR-032**: Com a configuração ligada, os palpites ativos dos jogos cancelados ou adiados, nas
  apostas Ativas com resultado ainda Aguardando, DEVEM ser cancelados pelo sistema
  (valem 1,00), com recálculo do prêmio e registro no histórico de edição da aposta, pelo mesmo
  fluxo do cancelamento de palpite já existente.
- **FR-033**: O cancelamento pelo sistema DEVE ser permitido mesmo quando for o último palpite
  ativo da aposta (diferente da edição manual, que exige cancelar a aposta).
- **FR-034**: Com a configuração desligada, o jogo DEVE apenas mudar de situação; os palpites
  ficam Aguardando para tratamento manual.
- **FR-035**: Depois do cancelamento, a aposta DEVE ser reapurada. Sem palpite ativo restante, a
  aposta termina Vencedor com cotação total 1,00 e prêmio igual ao valor apostado, devolvido na
  mesma carteira usada no pagamento (nada para Dinheiro), pelas regras de pagamento desta spec.

**Registro de operações**

- **FR-036**: Cada uma destas operações DEVE gerar um registro: resultado de jogo alterado
  (recebido do provedor ou lançado à mão); situação de jogo alterada (cancelado ou adiado); resultado de palpite alterado;
  resultado de aposta alterado; palpite cancelado por jogo cancelado; prêmio pago; prêmio
  estornado; prêmio ajustado; valor apostado devolvido.
- **FR-037**: Cada registro DEVE guardar: data e hora, tipo da operação, origem (carga de
  resultados, carga de cancelados, pagamento, reprocessamento manual, lançamento manual,
  encerramento de especial), jogo, aposta, palpite e
  cliente quando houver, carteira, valores antes e depois (resultado, placar, prêmio ou saldo,
  conforme a operação) e o motivo. (Alterado pela spec 024: nova origem "Correção de especial",
  da troca das vencedoras de uma categoria encerrada, que reapura as apostas com estorno e crédito
  da diferença; e novo motivo de cancelamento de palpite "Opção anulada", da opção especial anulada,
  devolvida como cotação 1,00.)
- **FR-038**: Os registros NÃO DEVEM ser alterados nem apagados pelo sistema.
- **FR-039**: A tela de consulta dos registros está fora desta spec.

**Robustez**

- **FR-040**: A apuração e o pagamento de cada aposta DEVEM acontecer numa transação com a aposta
  travada; uma falha desfaz só aquela aposta, é registrada no log e não interrompe as demais.
- **FR-041**: As execuções automáticas NÃO DEVEM se sobrepor, e reprocessar o mesmo resultado NÃO
  DEVE mudar nada (idempotência).
- **FR-042**: DEVE existir um comando manual para reapurar um jogo específico, com o mesmo efeito
  da carga automática e origem "reprocessamento manual" nos registros.
- **FR-043**: Os agendadores DEVEM seguir o padrão dos já existentes, em minutos escalonados para
  não coincidir com as outras cargas do pré-jogo.

**Proteção da grade**

- **FR-044**: A carga de confrontos NÃO DEVE voltar para Aguardando um jogo que já esteja
  Cancelado, Adiado ou Encerrado; as demais informações do jogo continuam sendo atualizadas.

**Resultado de jogo manual**

- **FR-045**: O administrador DEVE poder lançar ou corrigir, por uma rota da API, o resultado de
  um jogo cadastrado à mão: placar final (obrigatório), gols de cada tempo, escanteios e gols por
  jogador (opcionais). Ao lançar, o jogo passa a Encerrado e as apostas dele são apuradas como na
  carga automática, com origem "Lançamento manual" nos registros.
- **FR-046**: A rota DEVE recusar jogos do provedor (não manuais), com a mensagem "O resultado de
  jogos do provedor vem da carga automática.", e exigir uma permissão própria, dada por padrão só
  a Admin e Supervisor. (Alterado pela spec 023: aceita qualquer jogo depois do início, com tempos
  e escanteios obrigatórios e a regra da soma; permissão só do Admin.)
- **FR-047**: Lançar de novo o mesmo resultado NÃO DEVE mudar nada (mesma regra de assinatura da
  carga automática).

### Key Entities

- **Resultado do jogo**: placar final, placar de cada tempo e escanteios de cada time, ligados ao
  jogo da grade, com a data da última atualização recebida do provedor.
- **Gol do jogo**: autor (nome do jogador), minuto, ordem do gol no jogo e tempo de jogo; serve
  para os palpites de jogador.
- **Palpite** (existente): ganha o resultado Devolvido além de Aguardando, Vencedor e Perdedor.
- **Aposta** (existente): passa a guardar quando foi apurada (última mudança de resultado ou de
  valor a pagar, base da janela de pagamento) e quanto já foi pago ao cliente.
- **Transação do cliente** (existente): créditos de prêmio e débitos de estorno ligados à aposta.
- **Operação da apuração**: uma linha por operação (FR-036), com tipo, origem, referências (jogo,
  aposta, palpite, cliente), carteira, valores antes e depois e motivo.
- **Configurações gerais** (existente): ganham "remover jogo cancelado automaticamente" e a
  janela de segurança do pagamento (minutos).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% das apostas Ativas com todos os jogos encerrados ficam decididas até a execução
  seguinte à chegada do último resultado.
- **SC-002**: Nenhuma aposta é paga duas vezes, mesmo com execuções repetidas ou simultâneas.
- **SC-003**: Após uma correção de resultado, a soma creditada menos a soma estornada de cada
  aposta é igual ao prêmio que ela deve pagar no resultado final (zero se Perdedor).
- **SC-004**: Nenhuma correção ou cancelamento apaga transações ou altera saldos de movimentações
  que não sejam da aposta corrigida.
- **SC-005**: 100% das mudanças de resultado e de saldo feitas por esta feature têm um registro
  correspondente com valores antes e depois.
- **SC-006**: Prêmios de apostas vencedoras ficam disponíveis no saldo do cliente em até a janela
  de segurança mais o intervalo de uma execução.
- **SC-007**: Reexecutar a carga com os mesmos dados não gera nenhuma alteração nem registro novo.
- **SC-008**: A validação manual dos cenários de aceite (quickstart) passa para todos os
  mercados de exemplo escolhidos no plano, incluindo vencedor, ambas marcam, handicap, total de
  gols, escanteios, jogador e empate anula aposta.

## Assumptions

- O provedor já oferece as rotas "resultados" e "cancelados" usadas pelo sistema antigo, com os
  mesmos campos (placar, placar por tempo, escanteios, situação e eventos de gol); o formato exato
  é confirmado no plano.
- O cancelamento automático de palpites atinge só apostas Ativas com resultado Aguardando, como no
  sistema antigo; apostas Pendentes e Em análise não são tocadas.
- O cancelamento e a restauração de palpite pelo sistema ficam no histórico de edição da aposta
  sem autor (nulo = ação do sistema), como as transações de saldo feitas pelo sistema.
- A tela para lançar o resultado de jogos manuais fica para uma spec futura; nesta entra só a rota.
- O rollover conta o valor apostado no momento da aposta (regra existente); o resultado da aposta
  não altera o progresso do rollover.
- A apuração não muda a comissão do vendedor além do que o recálculo de prêmio da edição de
  apostas já faz.
- Prêmios e estornos usam cálculo em centavos, sem ponto flutuante, como o mecanismo de saldo
  existente.
- A tela de consulta dos registros, notificações ao cliente sobre prêmios e relatórios
  financeiros ficam para specs futuras.
- As operações da apuração seguem a regra de soft delete do projeto, mas o sistema nunca as
  exclui.
