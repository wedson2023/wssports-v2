# Feature Specification: Criar Aposta (vários palpites do mesmo jogo)

**Feature Branch**: `008-criar-aposta`

**Created**: 2026-10-13

**Status**: Draft

**Input**: User description: "Criar Aposta (Bet Builder): o apostador combina vários palpites
(mercados) de um MESMO jogo em um único bloco dentro do cupom, com cotação combinada. A cotação
combinada é o produto das cotações já exibidas ao público (que já passaram pelas tabelas
porcentagens_*, valor fixo do confronto e teto) multiplicado por (1 − desconto_criar_aposta ÷ 100).
Campos novos de configuração definidos pelo admin: (1) desconto_criar_aposta (%) — desconto extra
sobre a cotação combinada por causa da correlação entre palpites do mesmo jogo; 0 desliga; nas
configurações gerais e, seguindo o padrão de cotacao_maxima_ao_vivo, também por vendedor; (2)
liquidação quando um palpite do bloco é cancelado/anulado — opção escolhida pelo admin entre:
recalcular a cotação do bloco só com os palpites restantes (mantendo o desconto) ou anular o bloco
inteiro (devolve o valor/cotação 1,00); (3) mínimo e máximo de seleções por Criar Aposta. Tudo ou
nada: o bloco só ganha se todos os palpites acertarem. O prêmio segue o CalculoPremio atual
(multiplicador, prêmio máximo, truncado em centavos). Pontos ainda em aberto para o clarify: se o
bloco recebe o acréscimo ganho_multiplo_palpites; se o Criar Aposta pode ser uma perna de uma
múltipla com outros jogos; regras de combinações proibidas (palpites contraditórios ou redundantes
do mesmo mercado). Toda coluna nova deve ter comentário (constituição 3.3.0)."

## Clarifications

### Session 2026-10-13 (conversa antes do pedido)

- Q: De onde vem a cotação combinada do Criar Aposta: do provedor ou do próprio sistema? → A: Do
  próprio sistema. É o produto das cotações exibidas ao público, que já passaram pelas porcentagens
  (`porcentagens_*`), pelo valor fixo do confronto e pelo teto.
- Q: As porcentagens já existentes bastam ou é preciso um desconto próprio do Criar Aposta? → A:
  É preciso um desconto próprio (`desconto_criar_aposta`). As porcentagens ajustam cada cotação
  isolada e valem igual para aposta simples e múltipla; o desconto novo compensa só a correlação
  entre palpites do mesmo jogo, sem derrubar as cotações das outras apostas.
- Q: Onde fica o desconto? → A: Nas configurações gerais e por vendedor, no mesmo padrão de
  `cotacao_maxima_ao_vivo` (o vendedor usa o dele; cliente e visitante usam o geral). 0 desliga.
- Q: O que acontece quando um palpite do bloco é cancelado ou anulado? → A: O admin escolhe numa
  configuração: recalcular o bloco só com os palpites restantes (mantendo o desconto) ou anular o
  bloco inteiro (cotação 1,00).
- Q: Existe mínimo e máximo de palpites por bloco? → A: Sim, configuráveis.
- Q: Toda coluna nova precisa de comentário no banco? → A: Sim (constituição 3.3.0).

### Session 2026-10-13 (após a spec)

- Q: O Criar Aposta pode ser combinado com outros jogos na mesma aposta? → A: Sim. O bloco entra
  como uma "perna" de uma múltipla, ao lado de palpites de outros jogos.
- Q: O Criar Aposta vale no ao vivo? → A: Não. Só no pré-jogo; no ao vivo as cotações mudam rápido
  e a correlação é maior.
- Q: Qual o visual do bloco no cupom e no comprovante? → A: O visual atual dos palpites, agrupado por
  jogo, com a cotação do bloco. Diferença visual aprovada pelo responsável (Princípio VIII).
- Q: Deve existir uma chave para ligar e desligar o Criar Aposta por público? → A: Sim.
  `criar_aposta_habilitado` (ligada por padrão) na configuração geral (clientes e visitantes) e em
  cada vendedor; desligada, o detalhe do jogo não deixa montar o bloco e o servidor recusa.
- Q: O bloco deve ter uma cotação máxima própria? → A: Não. Vale o teto que já existe nas
  cotações (aplicado a cada palpite antes do produto), mais o multiplicador e o prêmio máximo.
- Q: Nas regras por quantidade de palpites, o bloco conta como um jogo ou pela quantidade de
  palpites? → A: Como um jogo em tudo: comissão, acréscimo por múltiplos palpites, quantidade de
  opções da aposta e limite simples ou duplo.
- Q: Além do mesmo mercado, devem ser proibidas combinações redundantes entre mercados diferentes
  (ex.: Vencedor × Dupla chance)? → A: Sim, por uma lista fixa de pares de mercados, com a mesma
  mensagem dos mercados repetidos.

## User Scenarios & Testing *(mandatory)*

> Conforme a constituição, o projeto não terá testes automatizados. Os cenários abaixo são
> critérios de aceite validados manualmente.

### User Story 1 - Apostador monta um Criar Aposta e conclui a aposta (Priority: P1)

No detalhe de um jogo, o apostador (vendedor, cliente ou visitante) escolhe vários palpites do
mesmo jogo, por exemplo "Casa vence", "Mais de 2,5 gols" e "Ambas marcam". O cupom agrupa esses
palpites em um bloco "Criar Aposta" do jogo e mostra a cotação combinada já com o desconto. O
apostador informa o valor e conclui; o sistema confere tudo de novo no servidor, grava a aposta
com o bloco e devolve o comprovante mostrando o bloco e os palpites dele.

**Why this priority**: é a funcionalidade em si; sem ela nada mais da spec tem uso.

**Independent Test**: com desconto geral de 15%, mínimo 2 e máximo 10, um cliente escolhe três
palpites de um mesmo jogo com cotações exibidas 1,80, 2,10 e 1,50, aposta R$ 10,00 e confere
cotação do bloco 4,81 (1,80 × 2,10 × 1,50 = 5,67 × 0,85 = 4,8195, truncado), prêmio R$ 48,10 e
o comprovante com o bloco.

**Acceptance Scenarios**:

1. **Given** um palpite de um jogo no cupom, **When** o apostador adiciona outro palpite do mesmo
   jogo, **Then** os dois passam a formar o bloco Criar Aposta daquele jogo, em vez de a aposta ser
   recusada como jogo repetido.
2. **Given** um bloco com cotações exibidas 1,80, 2,10 e 1,50 e desconto de 15%, **When** o cupom
   mostra o bloco, **Then** a cotação do bloco é 4,81.
3. **Given** desconto de 0%, **When** o mesmo bloco é montado, **Then** a cotação do bloco é o
   produto puro, 5,67.
4. **Given** um bloco válido e R$ 10,00, **When** a aposta é concluída, **Then** é gravada com o
   bloco, cada palpite com a sua cotação, a cotação do bloco, o desconto usado e a regra de
   liquidação usada, e o prêmio segue as regras atuais (multiplicador, prêmio máximo, centavos
   truncados).
5. **Given** um bloco com menos palpites que o mínimo configurado, **When** a aposta é enviada,
   **Then** é recusada com "O Criar Aposta do jogo CASA x FORA precisa de pelo menos N palpites".
6. **Given** um bloco no máximo configurado, **When** o apostador tenta adicionar mais um palpite
   do mesmo jogo, **Then** o cupom não deixa e avisa "O Criar Aposta aceita no máximo N palpites";
   o servidor recusa o envio com a mesma mensagem.
7. **Given** dois palpites do mesmo mercado do mesmo jogo (ex.: "Casa vence" e "Empate", ou
   "Mais de 1,5 gols" e "Mais de 2,5 gols"), **When** o apostador tenta combiná-los, **Then** o
   cupom não deixa e o servidor recusa com "Não é possível combinar dois palpites do mesmo mercado
   no Criar Aposta"; o mesmo vale para pares redundantes de mercados diferentes, como "Casa vence"
   e "Dupla chance Casa ou Empate" (FR-005a).
8. **Given** a cotação de um palpite do bloco mudou entre o que foi visto e o envio, **When** a
   aposta é enviada, **Then** vale a regra atual de mudança de cotação (preferência aceitar
   alterações), comparando a cotação de cada palpite e a do bloco.
9. **Given** um palpite do bloco com cotação indisponível, **When** a aposta é enviada, **Then**
   ela não é gravada e o palpite volta marcado como indisponível para remoção, como hoje.
10. **Given** o bloco, **When** os resultados forem apurados (outra spec), **Then** o bloco só é
    vencedor se todos os palpites ativos dele acertarem; um erro perde o bloco inteiro.

---

### User Story 2 - Admin configura o Criar Aposta (Priority: P1)

O admin define, nas configurações gerais: o desconto do Criar Aposta, o mínimo e o máximo de
palpites por bloco e a regra de liquidação quando um palpite do bloco é cancelado ou anulado. Os
usuários do painel com acesso às configurações de vendedores (hierarquia, spec 003) definem também
o desconto e o mínimo e máximo de palpites de cada vendedor.

**Why this priority**: sem as configurações, o Criar Aposta ficaria com valores fixos no código e a
banca não teria como controlar a margem.

**Independent Test**: alterar o desconto geral para 20% e conferir que a cotação do bloco de um
cliente cai; alterar o desconto de um vendedor para 10% e conferir que só as apostas dele usam 10%.

**Acceptance Scenarios**:

1. **Given** o admin nas configurações gerais, **When** salva desconto 20%, mínimo 2, máximo 8 e
   liquidação "Anular bloco", **Then** as próximas apostas de clientes e visitantes usam esses
   valores.
2. **Given** um gerente com acesso a um vendedor, **When** altera o desconto do vendedor para 10%,
   **Then** só as apostas desse vendedor usam 10%.
3. **Given** valores inválidos (desconto fora de 0 a 100, mínimo menor que 2, mínimo maior que o
   máximo), **When** a configuração é salva, **Then** é recusada com mensagem clara.
4. **Given** `criar_aposta_habilitado` desligada para um vendedor, **When** ele abre o detalhe de um
   jogo ou envia uma aposta com bloco, **Then** não consegue montar o bloco e o envio é recusado com
   "O Criar Aposta não está habilitado"; os demais vendedores continuam podendo.
5. **Given** uma aposta já gravada com desconto 15% e liquidação "Recalcular", **When** o admin
   muda as configurações, **Then** essa aposta continua usando 15% e "Recalcular".

---

### User Story 3 - Palpite do bloco cancelado ou anulado (Priority: P2)

Um usuário do painel com permissão edita uma aposta Ativa e cancela um palpite do bloco (ex.:
mercado com problema), ou, no futuro, a apuração anula um palpite. O sistema aplica a regra de
liquidação gravada na aposta.

**Why this priority**: sem essa regra, o cancelamento de um palpite do bloco deixaria a cotação e o
prêmio indefinidos; mas acontece bem menos que a criação da aposta.

**Independent Test**: com uma aposta de R$ 10,00 com bloco de três palpites (1,80, 2,10 e 1,50,
desconto 15%), cancelar o palpite de 1,50: com "Recalcular", conferir bloco 3,21 (1,80 × 2,10 =
3,78 × 0,85) e prêmio R$ 32,10; com "Anular bloco", conferir bloco 1,00 e prêmio R$ 10,00.

**Acceptance Scenarios**:

1. **Given** liquidação "Recalcular" gravada na aposta, **When** um palpite do bloco é cancelado,
   **Then** a cotação do bloco é recalculada com os palpites restantes, mantendo o desconto
   gravado, e o prêmio é recalculado.
2. **Given** liquidação "Recalcular" e um bloco com dois palpites, **When** um deles é cancelado,
   **Then** o palpite restante passa a valer pela sua própria cotação, sem desconto.
3. **Given** liquidação "Anular bloco", **When** um palpite do bloco é cancelado, **Then** todos os
   palpites do bloco ficam cancelados, o bloco passa a valer 1,00 e o prêmio é recalculado.
4. **Given** liquidação "Anular bloco" e uma aposta que só tem o bloco, **When** um palpite é
   cancelado, **Then** a aposta continua Ativa com cotação total 1,00 e prêmio igual ao valor
   apostado.
5. **Given** um palpite do bloco cancelado, **When** ele é restaurado, **Then** o bloco volta ao
   estado anterior (com "Anular bloco", todos os palpites do bloco voltam), com as cotações
   gravadas.
6. **Given** qualquer edição do bloco, **When** ela é feita, **Then** fica registrada no histórico
   da aposta, como as edições atuais.

---

### Edge Cases

- Cotação do bloco depois do desconto abaixo de 1,00 (ex.: 1,05 × 1,05 com desconto 15%): o
  bloco vale no mínimo 1,00, como as demais cotações do sistema; a odd mínima da aposta continua
  valendo sobre a cotação total.
- Palpite em jogador dentro do bloco: permitido quando o público pode apostar em jogadores e o
  jogador é do mesmo jogo; dois palpites do mesmo jogador no mesmo mercado não podem ser
  combinados.
- Desconto alterado entre o momento em que o apostador viu o bloco e o envio: tratado como mudança
  de cotação do bloco, pela preferência aceitar alterações.
- Bloco que, com "Recalcular", fica só com um palpite: o desconto deixa de valer (não há mais
  correlação).
- Cancelar o último palpite ativo da aposta continua proibido (para isso, cancela-se a aposta); com
  "Anular bloco" o bloco inteiro vale 1,00 e conta como ativo.
- Aposta do visitante (gerar código) com bloco: o código guarda o bloco; na validação pelo vendedor,
  o bloco é recalculado com o desconto e os limites do vendedor que valida, e o vendedor confirma se
  a cotação mudou (regra atual de validação).
- Jogo que sai do pré-jogo e entra no ao vivo com um bloco no cupom: o envio é recusado pela regra
  atual do pré-jogo ("O confronto CASA x FORA já iniciou, retire-o para concluir") e o bloco precisa
  ser removido; não vira bloco do ao vivo (FR-016).
- Aposta com dois blocos de jogos diferentes e um palpite simples de outro jogo: cotação total =
  bloco 1 × bloco 2 × palpite simples; conta como 3 jogos (FR-011).
- Comissão, limites de venda, quantidade mínima/máxima de opções da aposta e acréscimo por
  múltiplos palpites: o bloco conta como **um jogo** (ver FR-011). Ex.: aposta só com um bloco de 4
  palpites usa `comissao_pre_jogo_1`, abate `limite_simples` e não recebe acréscimo.

## Requirements *(mandatory)*

### Functional Requirements

**Bloco Criar Aposta**

- **FR-001**: O sistema DEVE permitir que uma aposta tenha, para um mesmo jogo, vários palpites
  agrupados em um bloco Criar Aposta. Fora do bloco, a regra atual continua: no máximo um palpite
  por jogo ("Não é possível cadastrar jogos repetidos na aposta").
- **FR-002**: Cada aposta DEVE ter no máximo um bloco por jogo.
- **FR-003**: A aposta DEVE poder combinar um ou mais blocos (de jogos diferentes) com palpites
  simples de outros jogos, como uma múltipla: cada bloco entra como uma "perna" com a sua cotação.
- **FR-004**: A quantidade de palpites do bloco DEVE ficar entre o mínimo e o máximo do público
  (FR-013), com as mensagens "O Criar Aposta do jogo CASA x FORA precisa de pelo menos N palpites"
  e "O Criar Aposta aceita no máximo N palpites".
- **FR-005**: O bloco NÃO DEVE ter dois palpites do mesmo mercado do jogo (mesmo grupo de mercado
  exibido no detalhe do jogo, ex.: Resultado final, Total de gols, Ambas marcam; para jogadores,
  o mesmo jogador no mesmo mercado), o que impede combinações contraditórias e redundantes ("Não é
  possível combinar dois palpites do mesmo mercado no Criar Aposta").
- **FR-005a**: O bloco também NÃO DEVE combinar mercados diferentes em que um já contém o resultado
  do outro, por uma lista fixa de pares (mesma mensagem do FR-005). No mínimo, do tempo
  correspondente (jogo todo, 1º tempo, 2º tempo):
  - Vencedor × Dupla chance, Empate anula aposta, Vencedor e ambas equipes, Resultado e total de
    gols, Margem de vitória, Intervalo | final de jogo e Resultado exato;
  - Ambas as equipes × Vencedor e ambas equipes e Resultado exato;
  - Gols mais/menos × Total de gols, Resultado e total de gols e Resultado exato;
  - Resultado exato × qualquer outro mercado de gols ou de resultado do mesmo tempo.
  A lista completa por código de cotação é fechada no plano. Combinações contraditórias entre
  mercados diferentes (que nunca ganham) não são bloqueadas.

**Cotação do bloco**

- **FR-006**: A cotação do bloco DEVE ser o produto das cotações exibidas ao público de cada
  palpite (já ajustadas pelas porcentagens, valor fixo do confronto e teto) multiplicado por
  (1 − desconto ÷ 100), com o desconto do público (FR-013). O bloco NÃO tem cotação máxima própria:
  o teto existente limita cada cotação antes do produto, e o multiplicador e o prêmio máximo limitam
  o prêmio.
- **FR-007**: O cálculo DEVE ser feito sem ponto flutuante e o resultado DEVE ter 2 casas,
  truncado (nunca arredondado a favor do apostador), e nunca menor que 1,00.
- **FR-008**: A cotação total da aposta DEVE usar a cotação do bloco como a cotação de um jogo;
  o prêmio DEVE seguir as regras atuais (multiplicador, prêmio máximo, centavos truncados).
- **FR-009**: Na mudança de cotação (preferência aceitar alterações), o servidor DEVE comparar
  cada palpite do bloco e a cotação do bloco (que muda também se o desconto mudar) e seguir a regra
  atual de confirmação. Cotação indisponível em qualquer palpite do bloco NÃO DEVE virar 1,00.
- **FR-010**: O bloco só DEVE ser vencedor se todos os seus palpites ativos acertarem (regra a ser
  aplicada pela spec de apuração).

**Contagem do bloco nas regras da aposta**

- **FR-011**: Nas regras que dependem da quantidade de palpites da aposta (quantidade mínima e
  máxima de opções, comissão por quantidade de jogos, limite simples ou duplo e acréscimo por
  múltiplos palpites), o bloco DEVE contar como **um jogo**.
- **FR-012**: O limite por confronto (`limite_valor_apostado`) DEVE contar o valor da aposta uma
  vez para o jogo do bloco.

**Configurações**

- **FR-013**: DEVEM existir as configurações, com padrões:
  - `criar_aposta_habilitado` (padrão ligado): nas configurações gerais (clientes e visitantes) e
    nas de cada vendedor. Desligada, o detalhe do jogo NÃO DEVE deixar montar o bloco e o servidor
    DEVE recusar a aposta com bloco ("O Criar Aposta não está habilitado"); na validação de código
    do visitante, vale a chave do vendedor que valida;
  - `desconto_criar_aposta` (%, padrão 0 = sem desconto) e `quantidade_minima_criar_aposta`
    (padrão 2) e `quantidade_maxima_criar_aposta` (padrão 10): nas configurações gerais (usadas por
    clientes e visitantes) e nas de cada vendedor (usadas nas apostas do vendedor), no mesmo padrão
    de `cotacao_maxima_ao_vivo`;
  - `liquidacao_criar_aposta` (Recalcular, padrão; ou Anular bloco): só nas configurações gerais,
    escolhida pelo admin, valendo para todas as apostas.
- **FR-014**: As alterações DEVEM ser coerentes: desconto entre 0 e 100 com até 2 casas; mínimo
  e máximo entre 2 e 20; mínimo ≤ máximo; liquidação entre os valores do enum. As configurações do vendedor seguem o
  fluxo de consulta e alteração por hierarquia da spec 003; as gerais, o fluxo das configurações
  gerais existentes, com a permissão do admin.
- **FR-015**: A aposta DEVE gravar o desconto e a regra de liquidação usados na confirmação, que não
  mudam se a configuração mudar depois.
- **FR-016**: O Criar Aposta DEVE valer só para jogos do pré-jogo. O detalhe do jogo do ao vivo NÃO
  DEVE permitir montar bloco, e o servidor DEVE recusar um bloco de jogo do ao vivo ("O Criar Aposta
  não está disponível para jogos ao vivo"). Os outros palpites da aposta podem ser do ao vivo
  (aposta mista, regras atuais).
- **FR-017**: Toda coluna nova DEVE ter comentário no banco explicando o uso (constituição 3.3.0).

**Liquidação (palpite do bloco cancelado ou anulado)**

- **FR-018**: Com "Recalcular", o cancelamento de um palpite do bloco DEVE recalcular a cotação do
  bloco só com os palpites ativos restantes, mantendo o desconto gravado; com um único palpite
  restante, vale a cotação dele sem desconto.
- **FR-019**: Com "Anular bloco", o cancelamento de um palpite do bloco DEVE cancelar todos os
  palpites do bloco e o bloco DEVE valer 1,00. Se a aposta só tiver o bloco, ela continua Ativa com
  cotação total 1,00 e prêmio igual ao valor apostado.
- **FR-020**: Restaurar um palpite do bloco DEVE desfazer o efeito do cancelamento (com "Anular
  bloco", todos os palpites do bloco voltam), com as cotações gravadas.
- **FR-021**: As edições do bloco DEVEM seguir as regras atuais de edição: permissão, hierarquia,
  só apostas Ativas com resultado Aguardando, histórico, assinatura refeita, proteção contra edições
  simultâneas e sem mudar valor, saldo, limites, comissão e rollover.
- **FR-022**: A mesma regra de liquidação DEVE ser usada pela spec de apuração quando um palpite do
  bloco for anulado (ex.: jogador que não entrou em campo, mercado cancelado).

**Cupom, detalhe do jogo e comprovante**

- **FR-023**: No detalhe do jogo, o apostador DEVE poder escolher vários palpites do mesmo jogo; o
  cupom DEVE agrupar os palpites do mesmo jogo em um bloco Criar Aposta, mostrando os palpites, a
  cotação de cada um e a cotação do bloco com o desconto já aplicado, e DEVE permitir remover um
  palpite do bloco.
- **FR-024**: O cupom DEVE impedir, com aviso, passar do máximo de palpites do bloco e combinar dois
  palpites do mesmo mercado, antes do envio. O servidor confere tudo de novo.
- **FR-025**: O comprovante (tela, impressão e consulta pelo código) DEVE mostrar o bloco com seus
  palpites, a cotação de cada palpite e a cotação do bloco, e marcar palpites cancelados. O
  percentual de desconto NÃO DEVE ser exibido (dado interno).
- **FR-026**: O bloco no cupom e no comprovante DEVE usar o visual atual dos palpites, agrupados
  por jogo, com a cotação do bloco abaixo deles; nenhum componente com visual novo é criado
  (diferença aprovada, Princípio VIII).

**Documentação**

- **FR-027**: A coleção do Postman DEVE ser regenerada se rotas forem criadas ou alteradas.

### Key Entities

- **Bloco Criar Aposta**: grupo de palpites de um mesmo jogo dentro de uma aposta. Jogo, cotação do
  bloco, desconto usado e situação (Ativo, Anulado). Pertence a uma aposta; tem 2 ou mais palpites.
- **Palpite da aposta** (`apostas_palpites`): passa a poder pertencer a um bloco; sem bloco,
  continua no máximo um por jogo.
- **Aposta** (`apostas`): passa a gravar o desconto do Criar Aposta e a regra de liquidação usados
  na confirmação.
- **Configurações**: novas colunas em `configuracoes` (habilitado, desconto, mínimo, máximo e
  liquidação) e em `usuarios_configuracoes` (habilitado, desconto, mínimo e máximo do vendedor).
- **Enum de liquidação do Criar Aposta**: Recalcular, Anular bloco.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: O apostador monta um Criar Aposta de 3 palpites e conclui a aposta em menos de 1
  minuto a partir do detalhe do jogo.
- **SC-002**: Em 100% das apostas com bloco, a cotação do bloco gravada é igual ao produto das
  cotações exibidas com o desconto do público, em 2 casas truncadas, e nunca menor que 1,00.
- **SC-003**: Em 100% dos envios com bloco fora do mínimo/máximo, com dois palpites do mesmo
  mercado ou com um par redundante da lista fixa, a aposta não é gravada e a mensagem diz o motivo e
  o jogo.
- **SC-004**: Mudar uma configuração do Criar Aposta não altera nenhuma aposta já gravada.
- **SC-005**: Em 100% dos cancelamentos de palpite do bloco, a cotação e o prêmio resultantes
  seguem a regra de liquidação gravada na aposta, e a edição aparece no histórico.
- **SC-006**: Com desconto 0%, a cotação do bloco é exatamente o produto das cotações exibidas.

## Assumptions

- **Sistema antigo**: o Criar Aposta não existe no sistema antigo (`wssports.bet`); a busca por
  "criar aposta", "bet builder", "mesmo jogo" e similares não encontrou nada. É uma funcionalidade
  nova, sem comportamento antigo a preservar (Princípio VII); o registro vai para o `research.md`.
- O público que usa cada configuração segue o padrão de `cotacao_maxima_ao_vivo`: o vendedor usa
  a dele; cliente e visitante usam a geral. A liquidação é única, geral, para a regra de apuração
  ser a mesma em todo o sistema.
- O bloco conta como um jogo nas regras por quantidade de palpites (FR-011, confirmado em
  Clarifications): como a cotação do bloco já é reduzida pela correlação, contar cada palpite
  inflaria a comissão, o acréscimo por múltiplos palpites e a quantidade de opções. Por isso o
  bloco sozinho não recebe o acréscimo por múltiplos palpites e usa a comissão de 1 jogo e o
  limite simples.
- Combinações proibidas ficam fixas no código: "um palpite por mercado do jogo" (FR-005) e a lista
  de pares redundantes (FR-005a), sem tela de configuração nesta spec.
- Apuração de resultados e pagamento de prêmios continuam fora do escopo; esta spec só define a
  regra (tudo ou nada e liquidação) que a spec de apuração usará.
- O desconto padrão 0 deixa o Criar Aposta sem desconto até o admin configurar.
- O detalhe do jogo, o cupom e o comprovante já existentes (specs 004, 005 e 006) são reaproveitados.
- Os comentários nas colunas já existentes do banco ficam para uma spec própria.
