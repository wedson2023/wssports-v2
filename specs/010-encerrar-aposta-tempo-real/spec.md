# Feature Specification: Encerrar aposta, bilhete em tempo real e notificações push

**Feature Branch**: `010-encerrar-aposta-tempo-real`

**Created**: 2026-10-15

**Status**: Draft

**Input**: User description: "Encerrar aposta (cash out) com os padrões do mercado e todas as
modalidades (total, parcial e automático), pesquisando as configurações e regras usadas pelas
casas de apostas; bilhete em tempo real em uma URL separada, como no sistema antigo, atualizando
as informações de cada jogo e exibindo o botão de encerrar aposta quando todos os critérios forem
atendidos, com visual melhorado por técnicas de UX/UI, responsivo e nas cores do tema; e
notificações push da PWA para gols, intervalos e encerramento dos jogos. Decisões aprovadas: só o
cliente encerra; visitante não encerra; as três modalidades; margem de 7% no pré-jogo e 10% no ao
vivo; Criar Aposta e Promoção esportes sem encerramento; crédito depois da janela de segurança;
atualização por conexão contínua (WebSocket); push só para cliente logado; campanhas manuais de
push na próxima spec; uma spec só."

## Clarifications

### Session 2026-10-15 (conversa antes do pedido)

- Q: Quem pode encerrar uma aposta? → A: Só o cliente logado, dono da aposta. O vendedor não
  encerra apostas, nem as que ele vendeu.
- Q: A aposta feita por visitante (código sem conta) pode ser encerrada? → A: Não.
- Q: Quais modalidades de encerramento entram? → A: As três: total, parcial e automático, cada uma
  com liga/desliga na configuração geral.
- Q: Qual a margem da casa no valor oferecido? → A: Configurável, com padrão de 7% para apostas
  só com jogos que ainda não começaram e 10% quando há algum jogo ao vivo.
- Q: Apostas com bloco do Criar Aposta ou pagas com a carteira Promoção esportes podem ser
  encerradas? → A: Não. Ficam sempre sem encerramento (sem chave de configuração).
- Q: Quando o valor do encerramento entra no saldo? → A: Depois da janela de segurança da
  apuração (spec 009), a mesma usada para os prêmios.
- Q: Como o bilhete em tempo real recebe as atualizações? → A: Por uma conexão contínua com o
  servidor (o servidor envia as mudanças sem o navegador perguntar), com consulta periódica como
  plano B quando a conexão cair.
- Q: Quem recebe notificações push? → A: Só o cliente logado.
- Q: As campanhas manuais de push do administrador (título, mensagem e link, com cota mensal, do
  sistema antigo) entram? → A: Não. Ficam para a próxima spec.
- Q: Uma spec ou três (bilhete, encerramento e push)? → A: Uma spec só, com prioridades.
- Q: Diferenças em relação ao sistema antigo aprovadas pelo responsável (Princípios VII e VIII):
  - o encerramento de aposta é novo: no sistema antigo, "cashout" era o saque do saldo do
    cliente (tela de créditos), e não havia encerramento de aposta;
  - o bilhete em tempo real ganha **visual novo**, aprovado no protótipo apresentado ao
    responsável: resumo com barra de progresso por palpite, selos "ganhando agora" e "perdendo
    agora" calculados pelo placar atual, cartão de jogo com placar em destaque, bloco de
    encerramento e barra fixa no celular. Cores, fonte (Roboto) e ícones (Material Icons) seguem
    o tema do sistema;
  - a atualização deixa de ser uma consulta a cada 10 segundos e passa a ser contínua;
  - as notificações deixam de depender de um servidor externo e da cota mensal de envios; os
    tipos deixam de ser "Todas / Gol / Avisos / Nenhuma" e passam a ter liga/desliga por tipo de
    evento, por aparelho;
  - o link compartilhado do bilhete passa a abrir o bilhete em tempo real (no v2 o compartilhamento
    levava só o código).

### Session 2026-10-15 (após a spec)

- Q: Quando o cliente encerra uma aposta, o valor dela sai do progresso do rollover (que libera o
  saque)? → A: Sim, padrão do mercado. O encerramento total desfaz o que a aposta somou; no
  parcial, sai a parte encerrada. Evita cumprir o rollover apostando e encerrando sem risco
  (FR-038).

### Session 2026-10-15 (clarify)

- Q: Onde o cliente encontra as apostas em andamento para acompanhar e encerrar? → A: Sem lista
  nova. O cupom (modal "Conferir Bilhete") ganha, no topo, o botão que abre o bilhete em tempo
  real; no extrato de operações da Minha Conta, tocar numa operação de aposta deixa de abrir o
  cupom e abre direto o bilhete em tempo real (FR-015).
- Q: Um usuário do painel em acesso assistido na conta do cliente pode encerrar as apostas dele?
  → A: Sim, as três modalidades, como as demais operações do acesso assistido (spec 007); o
  registro guarda o usuário do painel como autor (FR-016, FR-035).
- Q: Como o nome do cliente aparece na página pública do bilhete? → A: Primeiro nome e inicial do
  sobrenome para todos (ex.: "José W."); nome completo só para o dono logado (ou no acesso
  assistido). Diferença em relação ao sistema antigo (15 primeiros caracteres) aprovada pelo
  responsável (FR-002).
- Q: O encerramento vem ligado para apostas cujos jogos ainda não começaram? → A: Sim. "Permitir
  encerrar com jogos ainda não iniciados" vem ligado por padrão; a aposta pode ser encerrada desde
  a criação, com a margem do pré-jogo (FR-036).
- Q: Quais funções do painel veem a lista de encerramentos? → A: Permissão nova
  `apostas_encerramentos.listar`, dada por padrão ao Admin e ao Supervisor, no mesmo padrão das
  permissões de clientes (FR-037).

### Session 2026-10-15 (analyze)

- Q: O painel cancela uma aposta que já teve encerramento parcial. Quanto volta para o cliente?
  → A: Só o valor apostado que continua valendo (valor × fração restante), e só o rollover que essa
  parte ainda soma. A parte encerrada já foi (ou será) paga pelo encerramento. A aposta encerrada
  (total) não pode ser cancelada, porque o resultado dela já não é Aguardando.
- Q: A mudança de cotação do pré-jogo também chega ao bilhete em até 5 segundos (SC-001)? → A: Sim.
  A carga de cotações do pré-jogo avisa o bilhete dos jogos que têm aposta em aberto, como a carga
  do ao vivo.

## Referências do sistema antigo

- Bilhete em tempo real: `resources/js/screens/ticket/index.js` e `styles.js` (rota
  `/bilhete/:code`), alimentado pela rota `futebol-ao-vivo/{codigo}` (`AoVivoController`).
- Modal "Conferir Bilhete": `resources/js/modals/ticket/index.js` (ícone "acompanhar em tempo
  real" que abre `/bilhete/:codigo`).
- Notificações: `resources/js/utils/helpers.js` (inscrição, `subscribe`/`unsubscribe` no servidor
  externo `messages.wssports.bet`), `InscritosController`, `NotificacaoPushController`,
  `RenovarPushNotificationCommand` (cota mensal) e `HomeController::notificacao_gols` (lista os
  clientes com palpite nos jogos para o servidor externo enviar o gol, com link para o bilhete).
- "Cashout" do antigo: `resources/js/screens/credits/index.js` (é o saque de saldo, não o
  encerramento de aposta).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Acompanhar o bilhete em tempo real (Priority: P1)

Qualquer pessoa com o código abre o bilhete em uma página própria e acompanha cada jogo da aposta
enquanto acontece: placar, período e minuto, gols de cada tempo, escanteios e se cada palpite está
ganhando ou perdendo naquele momento. As informações mudam na tela sozinhas, sem recarregar a
página. É o link enviado no compartilhamento e aberto pelas notificações.

**Why this priority**: é a base das outras duas histórias (o botão de encerrar e as notificações
levam para esta página) e já entrega valor sozinha: o apostador acompanha a aposta sem procurar
cada jogo.

**Independent Test**: abrir o bilhete de uma aposta com jogos ao vivo, sem login, e conferir que
placar, minuto e selos mudam sozinhos quando os dados do jogo mudam; abrir o bilhete de uma aposta
decidida e conferir o resultado final de cada palpite.

**Acceptance Scenarios**:

1. **Given** uma aposta com jogos ainda não iniciados, ao vivo e encerrados, **When** alguém abre o
   bilhete pelo código, **Then** vê o resumo (prêmio potencial, situação, cliente como "José W.",
   horário da aposta, valor e cotação total; o dono logado vê o nome completo) e, em cada palpite: campeonato, horário, times com escudos,
   mercado, cotação, e o placar com período e minuto (jogo ao vivo), o placar final, os gols de
   cada tempo e os escanteios (jogo encerrado) ou "começa em X min" (jogo não iniciado).
2. **Given** o bilhete aberto e um jogo ao vivo da aposta, **When** sai um gol, **Then** o placar,
   os gols do tempo e o selo do palpite mudam na tela em poucos segundos, sem recarregar, e o
   placar recebe um destaque passageiro.
3. **Given** um palpite em jogo ao vivo, **When** o placar atual faria o palpite ganhar se o jogo
   terminasse agora, **Then** ele mostra o selo "Ganhando agora"; caso contrário, "Perdendo agora".
4. **Given** um palpite já apurado, **When** o bilhete é exibido, **Then** ele mostra o resultado
   (Vencedor, Perdedor ou Devolvido) no lugar do selo provisório, e o palpite cancelado aparece
   como Cancelado.
5. **Given** o bilhete aberto, **When** a conexão com o servidor cai, **Then** o indicador muda de
   "Ao vivo" para "Reconectando…", a página continua buscando as informações periodicamente e
   volta ao modo contínuo quando a conexão volta.
6. **Given** uma aposta já decidida, **When** o bilhete é aberto, **Then** ele mostra o resultado
   final e não fica buscando atualizações.
7. **Given** um código inexistente, de aposta Pendente, Recusada ou Expirada, **When** alguém abre
   o bilhete, **Then** recebe uma mensagem clara de que o bilhete não está disponível.
8. **Given** o cupom (modal "Conferir Bilhete") aberto, **When** o usuário toca no botão
   "acompanhar em tempo real" do topo ou compartilha o bilhete, **Then** abre (ou envia) o link do
   bilhete em tempo real.
9. **Given** o extrato de operações da Minha Conta, **When** o cliente toca numa operação de
   aposta, **Then** abre direto o bilhete em tempo real dessa aposta, sem passar pelo cupom.

---

### User Story 2 - Encerrar a aposta antes do fim (Priority: P2)

O cliente dono da aposta, logado, vê no bilhete quanto receberia para encerrar a aposta agora e
pode encerrá-la inteira, encerrar só uma parte ou deixar um encerramento automático programado
para quando a oferta chegar a um valor-alvo. O valor oferecido muda conforme as cotações e só
aparece quando todos os critérios são atendidos.

**Why this priority**: é a funcionalidade nova de maior valor para o cliente e de receita para a
banca (margem), mas depende do bilhete em tempo real para ser usada.

**Independent Test**: com um cliente logado e uma aposta elegível, conferir a oferta calculada,
encerrar a aposta (total), conferir a aposta como Encerrada e o crédito no saldo depois da janela
de segurança; repetir com encerramento parcial e com encerramento automático.

**Acceptance Scenarios**:

1. **Given** o cliente dono, logado, e uma aposta elegível, **When** ele abre o bilhete, **Then**
   vê a oferta de encerramento, o prêmio potencial e as abas Total, Parcial e Automático; quem não
   é o dono (ou não está logado) não vê o bloco de encerramento.
2. **Given** a oferta exibida, **When** as cotações dos jogos em aberto mudam, **Then** o valor da
   oferta é atualizado na tela, com indicação de que subiu ou caiu.
3. **Given** a aba Total, **When** o cliente toca em "Encerrar" e confirma dentro do prazo de
   validade da oferta, **Then** a aposta fica Encerrada, o valor é registrado para crédito e o
   cliente vê "Aposta encerrada" com o valor e o momento previsto do crédito.
4. **Given** a confirmação aberta, **When** a validade da oferta acaba antes de o cliente
   confirmar, **Then** a confirmação é desfeita e a oferta atual é exibida de novo.
5. **Given** um jogo ao vivo na aposta, **When** o cliente confirma o encerramento, **Then** o
   sistema aguarda o tempo de confirmação do ao vivo e, se a cotação ou o placar mudou nesse
   intervalo, recusa o pedido e mostra a nova oferta (respeitando a preferência de aceitar
   alterações).
6. **Given** a aba Parcial, **When** o cliente escolhe receber parte da oferta e confirma, **Then**
   recebe esse valor (depois da janela de segurança) e a aposta continua valendo com valor
   apostado e prêmio reduzidos na mesma proporção, exibidos antes da confirmação.
7. **Given** a aba Automático, **When** o cliente define um valor-alvo maior que a oferta atual,
   **Then** o encerramento automático fica ativo e, quando a oferta alcançar o alvo, a aposta é
   encerrada inteira sem ação do cliente.
8. **Given** um encerramento automático ativo, **When** o cliente faz um encerramento manual
   (total ou parcial), **Then** o automático é cancelado.
9. **Given** uma aposta com um palpite Perdedor, um jogo travado ou mercado suspenso, sem cotação
   atual em algum palpite em aberto, com bloco do Criar Aposta ou paga com Promoção esportes,
   **When** o cliente abre o bilhete, **Then** não há oferta e a página informa o motivo.
10. **Given** uma aposta encerrada (total), **When** os jogos terminam, **Then** os palpites
    continuam sendo apurados para o histórico, mas a aposta não recebe prêmio nem perde o valor
    encerrado.

---

### User Story 3 - Receber notificações dos jogos da aposta (Priority: P3)

O cliente logado ativa as notificações no aparelho e passa a receber avisos dos jogos das suas
apostas ativas mesmo com o aplicativo fechado: gol (e gol anulado), intervalo, fim de jogo,
resultado da aposta e encerramento automático. Ele escolhe quais tipos quer receber. Tocar no
aviso abre o bilhete em tempo real.

**Why this priority**: aumenta o engajamento, mas depende do bilhete (destino do aviso) e é
complementar às duas histórias anteriores.

**Independent Test**: com um cliente logado, ativar as notificações no aparelho, ter uma aposta
ativa em um jogo ao vivo e conferir o aviso ao sair um gol e no intervalo; desligar o tipo "Gols"
e conferir que o próximo gol não gera aviso.

**Acceptance Scenarios**:

1. **Given** o cliente logado no bilhete ou na Minha Conta, **When** toca no sino e permite as
   notificações no aparelho, **Then** o aparelho fica inscrito, com todos os tipos ligados.
2. **Given** um cliente inscrito com aposta ativa em um jogo ao vivo, **When** sai um gol, **Then**
   ele recebe um aviso com os times, o placar e o minuto, e tocar no aviso abre o bilhete.
3. **Given** o mesmo cenário, **When** o placar diminui (gol anulado), **Then** ele recebe o aviso
   de gol anulado com o placar corrigido.
4. **Given** o mesmo cenário, **When** o jogo entra no intervalo ou termina, **Then** ele recebe o
   aviso correspondente com o placar.
5. **Given** uma aposta do cliente, **When** ela é decidida (ganhou ou perdeu) ou encerrada
   automaticamente, **Then** ele recebe o aviso com o resultado e o valor.
6. **Given** um cliente com várias apostas no mesmo jogo, **When** sai um gol, **Then** ele recebe
   um único aviso desse gol em cada aparelho.
7. **Given** um cliente que desligou o tipo "Gols", **When** sai um gol, **Then** ele não recebe o
   aviso, mas continua recebendo os outros tipos ligados.
8. **Given** um iPhone com o aplicativo aberto no navegador (não instalado na tela de início),
   **When** o cliente toca no sino, **Then** vê a orientação de instalar o aplicativo para receber
   notificações.

---

### Edge Cases

- Gol e pedido de encerramento quase ao mesmo tempo: o pedido confirmado durante o tempo de
  confirmação do ao vivo é recusado se o placar ou a cotação mudou, e uma nova oferta é mostrada.
- Resultado de um jogo lançado com atraso, mostrando que um palpite já estava perdido antes do
  encerramento: o encerramento é desfeito (o valor não é creditado ou é estornado) e a operação
  fica registrada.
- Resultado corrigido depois do encerramento (reapuração da spec 009): não altera o valor
  encerrado, salvo o caso acima; a parte que continua valendo (encerramento parcial) é reapurada
  normalmente.
- Jogo cancelado ou adiado (palpite cancelado, cotação 1,00) em uma aposta com encerramento
  parcial: a parte restante segue as regras da spec 009 com o valor e o prêmio já reduzidos.
- Todos os palpites em aberto ficam sem cotação (jogo travado, mercado suspenso): a oferta some e
  o encerramento automático aguarda; se a oferta não voltar até o fim, a aposta segue até o
  resultado.
- Oferta calculada abaixo do mínimo configurado: não há oferta (nem total, nem parcial).
- Valor-alvo do automático menor ou igual à oferta atual: não é aceito; a página sugere um valor
  maior.
- Limite de encerramentos parciais atingido: a aba Parcial fica indisponível e informa o motivo.
- Prêmio limitado pelo prêmio máximo: a oferta é calculada sobre o prêmio limitado.
- Cliente desativado ou com encerramento desligado na configuração dele: não há oferta.
- Duas confirmações iguais (toque duplo, rede lenta): só um encerramento é registrado.
- Cancelamento pelo painel de uma aposta com encerramento parcial: devolve só o valor apostado que
  continua valendo e desfaz só o rollover que essa parte soma.
- Inscrição de notificação expirada ou removida pelo aparelho: é descartada no primeiro envio que
  falhar, sem afetar os demais aparelhos.
- Jogo com várias mudanças seguidas (gol e intervalo na mesma atualização): cada evento gera um
  aviso, sem repetir eventos já enviados.
- Cliente sem nenhum aparelho inscrito: os eventos são registrados e nada é enviado.

## Requirements *(mandatory)*

### Functional Requirements

**Bilhete em tempo real**

- **FR-001**: O sistema DEVE oferecer uma página pública do bilhete, aberta pelo código da aposta,
  sem exigir login.
- **FR-002**: A página DEVE exibir o resumo da aposta: prêmio potencial, situação, cliente
  (primeiro nome e inicial do sobrenome, ex.: "José W."; nome completo só para o dono logado ou no
  acesso assistido), data e hora da aposta, valor, cotação total e uma barra de progresso com um segmento por palpite
  (ou bloco), indicando decidido, ganhando agora, perdendo agora ou não iniciado, acompanhada de
  uma legenda em texto com a contagem de cada estado.
- **FR-003**: Cada palpite DEVE exibir campeonato, data e hora, times com escudos, mercado,
  cotação original e, com o jogo ao vivo ou não iniciado, a cotação atual do mesmo mercado.
- **FR-004**: Com o jogo ao vivo, o palpite DEVE exibir placar, período e minuto, gols de cada
  tempo e escanteios, com os dados mais recentes do ao vivo; com o jogo encerrado, o placar final,
  os gols de cada tempo e os escanteios gravados pela apuração (spec 009). Gols por tempo e
  escanteios só no futebol.
- **FR-005**: Com o jogo ao vivo, o palpite DEVE exibir o selo "Ganhando agora" ou "Perdendo
  agora", calculado pelas mesmas regras de resultado da apuração aplicadas ao placar atual; quando
  a regra precisar de um dado que ainda não existe (ex.: escanteios não enviados), o selo é
  "Aguardando".
- **FR-006**: Palpite apurado DEVE exibir o resultado (Vencedor, Perdedor, Devolvido); palpite
  cancelado DEVE aparecer como Cancelado; jogo não iniciado DEVE exibir quanto falta para começar.
- **FR-007**: Os blocos do Criar Aposta DEVEM aparecer agrupados por jogo, como no comprovante,
  com a situação do bloco.
- **FR-008**: A página DEVE receber as mudanças da aposta e dos jogos dela de forma contínua, sem
  recarregar, em até 5 segundos depois de os dados chegarem ao sistema.
- **FR-009**: Quando a conexão contínua cair, a página DEVE indicar "Reconectando…", passar a
  buscar os dados periodicamente (no máximo a cada 10 segundos com jogo ao vivo e a cada 60
  segundos sem jogo ao vivo) e voltar ao modo contínuo quando a conexão voltar. A página DEVE
  mostrar há quanto tempo os dados foram atualizados.
- **FR-010**: Aposta decidida, encerrada (total) ou cancelada DEVE ser exibida no estado final, sem
  manter a atualização ligada.
- **FR-011**: Código inexistente ou de aposta Pendente, Em análise, Recusada ou Expirada DEVE
  mostrar a mensagem "Bilhete não encontrado ou ainda não validado", sem expor dados.
- **FR-012**: A página DEVE seguir o visual aprovado no protótipo: cores do tema da banca (cor
  principal e derivada, modo escuro e claro), Roboto e Material Icons; cores próprias para os
  estados (verde para ganhando/vencedor, vermelho para perdendo/perdedor, amarelo para
  aguardando/suspenso), distintas da cor principal; números com largura fixa.
- **FR-013**: A página DEVE ser responsiva: no celular, uma coluna (resumo, encerramento,
  palpites) com uma barra fixa no rodapé mostrando a oferta e o botão "Encerrar aposta" (só para o
  dono); a partir do ponto de quebra do site, palpites à esquerda e resumo e encerramento numa
  coluna à direita que acompanha a rolagem.
- **FR-014**: A página DEVE ser acessível: contraste legível nos dois modos, foco visível no
  teclado, anúncio das mudanças importantes para leitores de tela e respeito à preferência de
  reduzir movimento do aparelho.
- **FR-015**: O cupom (modal "Conferir Bilhete") DEVE ter, no topo junto às ações existentes, o
  botão "acompanhar em tempo real", que abre a página. No extrato de operações da Minha Conta,
  tocar numa operação de aposta DEVE abrir direto o bilhete em tempo real, no lugar do cupom. O
  compartilhamento do bilhete DEVE enviar o link da página. Não há lista de apostas nova.

**Encerrar aposta**

- **FR-016**: O encerramento DEVE estar disponível só para o cliente logado dono da aposta; o
  bloco de encerramento e a barra fixa NÃO DEVEM aparecer para outras pessoas, para vendedores ou
  em apostas de visitante. No acesso assistido (spec 007), o usuário do painel pode encerrar como o
  cliente, nas três modalidades.
- **FR-017**: Uma aposta DEVE ser elegível só quando: estiver Ativa com resultado Aguardando;
  tiver sido feita pelo cliente com a carteira Saldo; não tiver bloco do Criar Aposta; não tiver
  palpite Perdedor; todo palpite em aberto tiver cotação atual do mesmo mercado (a do pré-jogo
  antes do início do jogo, a do ao vivo depois); nenhum jogo em aberto estiver travado ou com o
  mercado suspenso; o encerramento estiver ligado na configuração geral e na do cliente; e o
  cliente estiver ativo.
- **FR-018**: Palpites Vencedor, Devolvido e Cancelado DEVEM entrar no cálculo com cotação atual
  1,00; palpite especial ou de jogador sem cotação atual torna a aposta inelegível enquanto estiver
  em aberto.
- **FR-019**: A oferta DEVE ser: prêmio base (valor × cotações originais, limitado pelo prêmio
  máximo, sem o valor acrescido por promoção) dividido pelo produto das cotações atuais dos
  palpites em aberto, menos a margem da casa; arredondada para baixo em centavos e nunca maior que
  o prêmio base.
- **FR-020**: As cotações atuais usadas na oferta DEVEM ser as ajustadas para aquele cliente
  (mesmas porcentagens, tetos e limites usados na criação da aposta).
- **FR-021**: A margem DEVE ser configurável, com padrão de 7% quando todos os jogos em aberto
  ainda não começaram e 10% quando algum jogo em aberto está ao vivo.
- **FR-022**: Oferta abaixo do mínimo configurado (valor mínimo em reais e percentual mínimo do
  valor apostado, valendo o maior) NÃO DEVE ser exibida.
- **FR-023**: A oferta DEVE ser atualizada no bilhete sempre que as cotações dos jogos em aberto
  mudarem, indicando se subiu ou caiu; quando a aposta ficar inelegível, a página DEVE informar o
  motivo (ex.: "Mercado suspenso em Time A x Time B") em vez de esconder o bloco sem explicação.
- **FR-024**: O encerramento DEVE ter confirmação dentro da própria página, mostrando quanto o
  cliente recebe, o que deixa de concorrer (ou o que continua valendo, no parcial) e quando o valor
  entra no saldo, com contagem regressiva da validade da oferta (padrão de 8 segundos,
  configurável); vencida a validade, a confirmação é desfeita.
- **FR-025**: Ao confirmar, o sistema DEVE recalcular a oferta; com jogo ao vivo, DEVE aguardar o
  tempo de confirmação do ao vivo já usado nas apostas e recusar o pedido se o placar ou a cotação
  de um palpite em aberto mudou nesse intervalo, mostrando a nova oferta. A preferência de aceitar
  alterações (Nenhuma, Somente para maior, Qualquer) DEVE ser respeitada como na criação da aposta.
- **FR-026**: Um pedido repetido (toque duplo ou reenvio) NÃO DEVE gerar mais de um encerramento.
- **FR-027**: No encerramento total, a aposta DEVE ficar com o resultado Encerrada e o valor
  encerrado registrado para crédito.
- **FR-028**: No encerramento parcial, o cliente DEVE escolher quanto recebe, entre o mínimo e a
  oferta total; o valor apostado e o prêmio que continuam valendo DEVEM ser reduzidos na mesma
  proporção (valor recebido ÷ oferta total) e exibidos antes da confirmação.
- **FR-029**: O número de encerramentos parciais por aposta DEVE ser limitado por configuração
  (padrão 5); atingido o limite, só o encerramento total continua disponível.
- **FR-030**: No encerramento automático, o cliente DEVE definir um valor-alvo maior que a oferta
  atual, com atalhos (+20%, +50%, +100% e "recuperar o valor apostado"); o sistema DEVE conferir a
  oferta a cada mudança de cotação dos jogos da aposta e, ao alcançar o alvo, encerrar a aposta
  inteira pelo valor da oferta daquele momento.
- **FR-031**: O cliente DEVE poder alterar ou desativar o valor-alvo; um encerramento manual
  (total ou parcial) DEVE cancelar o automático; se o alvo nunca for alcançado, a aposta segue até
  o resultado.
- **FR-032**: O valor encerrado DEVE ser creditado na carteira Saldo depois da janela de segurança
  da apuração (spec 009), contada a partir do encerramento, com uma transação de origem nova
  "Encerramento de aposta" ligada à aposta.
- **FR-033**: Se, dentro ou depois da janela, ficar provado que algum palpite em aberto no momento
  do encerramento já estava decidido como Perdedor antes desse momento (resultado lançado com
  atraso), o encerramento DEVE ser desfeito: não creditado, ou estornado se já creditado, com
  registro da operação. Correções de resultado posteriores que não se enquadrem nesse caso NÃO
  DEVEM alterar o valor encerrado.
- **FR-034**: A apuração (spec 009) DEVE continuar apurando os palpites de uma aposta encerrada
  para o histórico, sem pagar prêmio pela parte encerrada; numa aposta com encerramento parcial,
  DEVE apurar e pagar só a parte que continuou valendo.
- **FR-035**: Todo encerramento (total, parcial, automático e desfeito) DEVE ficar registrado com:
  aposta, cliente, modalidade, oferta, valor pago, margem, cotações usadas de cada palpite, placar
  de cada jogo no momento, valores antes e depois (valor apostado e prêmio), data e hora, IP,
  aparelho e autor (o cliente, o usuário do painel no acesso assistido ou o sistema no
  encerramento automático).
- **FR-036**: A configuração geral DEVE ter: encerramento ligado/desligado; parcial
  ligado/desligado; automático ligado/desligado; permitir encerrar com jogos ainda não iniciados
  (ligado: a aposta pode ser encerrada desde a criação; desligado: só depois que algum jogo da
  aposta começar); margem do pré-jogo; margem do ao vivo; valor mínimo e percentual mínimo da
  oferta; validade da oferta; limite de encerramentos parciais. Padrões: tudo ligado, margens de
  7% e 10%, mínimo de R$ 1,00 e 5%, validade de 8 segundos, limite de 5 parciais. A configuração do cliente DEVE ter encerramento
  ligado/desligado (padrão ligado).
- **FR-037**: O painel DEVE listar os encerramentos, com filtros por período, cliente e
  modalidade, mostrando o valor pago e quanto a aposta teria pago pelo resultado final, paginada
  conforme a constituição. O acesso DEVE exigir a permissão nova `apostas_encerramentos.listar`,
  dada por padrão ao Admin e ao Supervisor.
- **FR-038**: O encerramento total DEVE desfazer exatamente o que a aposta somou no progresso dos
  rollovers do cliente (como no cancelamento de aposta); no parcial, DEVE retirar do progresso a
  parte encerrada (valor apostado × proporção encerrada), e só a parte que continua valendo segue
  contando. Se o encerramento for desfeito (FR-033), o valor retirado DEVE voltar ao progresso.
- **FR-039**: A página de Regras do site DEVE ganhar uma seção sobre o encerramento de aposta
  (quem pode, critérios, como o valor é calculado, margem, mínimo, validade, crédito após a janela
  e quando o encerramento pode ser desfeito), exigida pela regulamentação de apostas.

**Notificações push**

- **FR-040**: O cliente logado DEVE poder ativar as notificações por aparelho a partir do sino no
  bilhete em tempo real e na Minha Conta; o pedido de permissão só pode ser feito depois de um
  toque do cliente.
- **FR-041**: Cada aparelho inscrito DEVE ter liga/desliga por tipo de aviso: gols (inclui gol
  anulado), intervalo, fim de jogo, resultado da aposta e encerramento automático; todos ligados
  na inscrição. O cliente DEVE poder desativar as notificações do aparelho.
- **FR-042**: O sistema DEVE detectar nos jogos ao vivo: gol (aumento do placar), gol anulado
  (redução do placar), início do intervalo e fim do jogo, cada evento uma única vez por jogo.
- **FR-043**: Cada evento DEVE gerar avisos só para os clientes com aposta Ativa e resultado
  Aguardando naquele jogo, nos aparelhos com aquele tipo ligado, um aviso por cliente e aparelho
  mesmo com várias apostas no jogo.
- **FR-044**: O sistema DEVE avisar o cliente quando uma aposta dele for decidida (ganhou, com o
  prêmio; perdeu) e quando um encerramento automático for executado (com o valor).
- **FR-045**: O aviso DEVE conter times, placar e minuto (eventos de jogo) ou o código e o valor
  (eventos da aposta); tocar nele DEVE abrir o bilhete em tempo real da aposta (com várias
  apostas no jogo, a mais recente); um aviso novo do mesmo jogo DEVE substituir o anterior na
  central de notificações do aparelho.
- **FR-046**: Inscrições recusadas pelo serviço de notificações do aparelho (expiradas ou
  removidas) DEVEM ser descartadas automaticamente.
- **FR-047**: Em aparelhos que só recebem notificações com o aplicativo instalado (iPhone), a
  página DEVE orientar o cliente a instalar o aplicativo na tela de início.
- **FR-048**: Os avisos de jogo DEVEM chegar ao aparelho em até 15 segundos depois de o evento
  chegar ao sistema.

### Key Entities

- **Encerramento de aposta**: cada encerramento feito em uma aposta. Modalidade (total, parcial,
  automático), situação (em análise, recusado, registrado, creditado, desfeito), oferta, valor pago, margem aplicada,
  cotações e placares usados por palpite, valor apostado e prêmio antes e depois, data e hora,
  IP, aparelho e autor. Ligado à aposta, ao cliente e, no acesso assistido, ao usuário do painel.
- **Encerramento automático**: o valor-alvo programado pelo cliente para uma aposta, com situação
  (ativo, executado, cancelado) e datas.
- **Inscrição de notificação**: um aparelho do cliente que aceitou receber avisos, com os dados
  de entrega fornecidos pelo aparelho, a descrição do aparelho e o liga/desliga de cada tipo de
  aviso. Um cliente pode ter vários.
- **Evento de jogo**: gol, gol anulado, intervalo ou fim de um jogo, com placar e minuto, usado
  para enviar cada aviso uma única vez.
- **Aposta** (existente): ganha o resultado Encerrada e passa a guardar o valor apostado e o
  prêmio que continuam valendo depois de encerramentos parciais.
- **Transação do cliente** (existente): ganha a origem "Encerramento de aposta".
- **Configuração geral e do cliente** (existentes): ganham as opções do FR-036.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Mudanças de placar, período e cotação aparecem no bilhete aberto em até 5 segundos
  depois de chegarem ao sistema, em 95% das vezes.
- **SC-002**: Com a conexão contínua indisponível, o bilhete continua atualizando em no máximo 10
  segundos com jogo ao vivo.
- **SC-003**: O cliente encerra uma aposta (total) em até 3 toques a partir do bilhete aberto.
- **SC-004**: 100% dos encerramentos confirmados geram um único registro e um único crédito,
  igual ao valor exibido na confirmação, mesmo com toques repetidos.
- **SC-005**: Nenhum encerramento é feito com cotação ou placar diferente do que o cliente viu
  (verificado pelos registros: placar e cotações no momento da confirmação iguais aos da oferta
  aceita, ou pedido recusado).
- **SC-006**: O encerramento automático executa em até 10 segundos depois de a oferta alcançar o
  valor-alvo.
- **SC-007**: O valor encerrado fica disponível no saldo em até a janela de segurança mais 1
  minuto.
- **SC-008**: Avisos de gol, intervalo e fim de jogo chegam ao aparelho inscrito em até 15
  segundos, sem avisos duplicados para o mesmo evento.
- **SC-009**: A página do bilhete funciona sem rolagem horizontal de 360 px de largura até
  desktop, nos modos escuro e claro e nas 6 cores de tema.
- **SC-010**: Nenhuma pessoa além do cliente dono (ou do usuário do painel em acesso assistido a
  esse cliente) vê ou consegue usar o encerramento de uma aposta.

## Assumptions

- A margem incide sobre o valor justo da oferta (prêmio base ÷ cotações atuais); as porcentagens
  de ajuste de cotação do cliente já embutem a margem das cotações, como na criação da aposta.
- O valor acrescido por promoção (ganho por múltiplos palpites) não entra na oferta: o
  encerramento considera só o prêmio pelas cotações, como as casas fazem com cotações turbinadas.
- O percentual mínimo padrão da oferta é 5% do valor apostado e o valor mínimo padrão é R$ 1,00;
  o mínimo de um encerramento parcial é o mesmo mínimo da oferta.
- "Mercado suspenso" é a ausência da cotação daquele mercado na carga mais recente do jogo; "jogo
  travado" segue as regras de trava do ao vivo já existentes (trava geral e jogo sem atualização
  há mais tempo que o limite).
- Jogo que já começou mas não tem cobertura ao vivo (sem cotação do ao vivo) torna a aposta
  inelegível até o fim desse jogo.
- A comissão de afiliados ou vendedores sobre a aposta do cliente, quando existir, continua
  calculada sobre o valor apostado no momento da aposta; o encerramento não a altera.
- O limite de valor apostado por jogo (exposição) não é alterado pelo encerramento.
- A página do bilhete identifica o dono pela sessão do cliente já usada no site; o cliente não
  logado vê a página só para acompanhamento, com um convite para entrar se quiser encerrar.
- As notificações usam o padrão de notificações da web da própria PWA, sem serviço de terceiros
  pago; exigem o site em HTTPS.
- Os eventos de jogo são detectados comparando cada carga do ao vivo com a anterior; o fim do
  jogo pode vir do ao vivo ou do resultado gravado pela apuração, o que chegar primeiro, sem
  repetir o aviso.
- A janela de segurança, a apuração e a liquidação seguem a spec 009; o encerramento reutiliza a
  mesma liquidação para creditar o valor.
- Campanhas manuais de notificação do administrador e notificações para vendedores ficam fora
  desta spec.
- Não há dados fake: todas as informações exibidas vêm de dados reais do sistema.
