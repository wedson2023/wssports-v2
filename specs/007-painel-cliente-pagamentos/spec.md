# Feature Specification: Painel do cliente online com depósito e saque

**Feature Branch**: `007-painel-cliente-pagamentos`

**Created**: 2026-10-11

**Status**: Draft

**Input**: User description: "Painel do cliente online com depósito (Pix, cartão de crédito e NuPay)
e saque via Mercado Pago: rota /minha-conta (substitui a antiga /creditos); lógica da tela
screens/credits do sistema antigo com design novo e melhorado da tela e dos modais; uma única
credencial do Mercado Pago para todo o sistema, vinculada por OAuth à aplicação da conta master;
comissão (application_fee) para a conta master com percentual nas configurações do sistema,
padrão 3%; cartão com parcelamento e juros pagos pelo cliente; estorno de cartão deixa o saldo
negativo; saque com baixa manual no painel ou pagamento automático pela API; admin e supervisor
acessam a conta de um cliente pelo painel e podem depositar e sacar por ele; notificações fora
do escopo."

> Referência de comportamento (Princípio VII): no sistema antigo (`wssports.bet`), as telas
> `resources/js/screens/credits` e os modais `modals/credits` (depósito Pix),
> `modals/edit_register` (meus dados), `modals/indication` (indique um amigo) e
> `modals/details_casino`; no backend, `CadastroController` (`index`, `update`,
> `converter_bonus`, `deletar_bonus`) e `PagamentosController` (`cobranca`, `mp`, `mp_token`,
> `webhook`, `payment`, `consulta`, `saque`, `dar_baixa_saque`), com as tabelas `creditos`,
> `creditos_transacaos`, `creditos_bonuses`, `gateways`, `travas_vendedor` e `validar_tokens`.
> Referência visual (Princípio VIII): a tela `screens/credits` (com `styles.js`) é reproduzida com
> o mesmo visual; os modais (`modals/credits`, `modals/edit_register`, `modals/indication` e o
> de saque, que no antigo era um alerta) servem de base para o conteúdo, os textos e a ordem das
> informações, com visual novo (ver Clarifications). No painel, `screens/adm/config` (campo
> "GATEWAY de pagamentos").

## Clarifications

### Session 2026-10-11 (decisões do responsável antes da spec)

- Q: A tela copia o sistema antigo? → A: A tela de créditos mantém o visual do antigo
  (`screens/credits`: cabeçalho, saldos, filtros, tabela e menu lateral). Só os modais ganham
  design novo e melhorado, no padrão dos modais já refeitos na spec 006 (cartão com cantos
  arredondados, cabeçalho escuro com faixa na cor do tema, botões com hierarquia, folha que sobe
  de baixo no mobile). As cores continuam vindo do tema da banca. **Exceção aprovada ao
  Princípio VIII** (fidelidade visual) só para os modais desta tela. (Atualizado na sessão de
  2026-10-12: antes, a tela também teria visual novo.)
- Q: Qual a rota do painel do cliente? → A: `/minha-conta`, diferente da antiga `/creditos`.
- Q: Quais meios de depósito? → A: Pix, cartão de crédito e, se disponível na conta do Mercado
  Pago, NuPay.
- Q: Cada gerente tem a própria conta do Mercado Pago, como no antigo? → A: Não. O sistema não
  trabalha com múltiplos admins: há uma única conta do Mercado Pago vinculada por sistema (por
  banca), feita pelo admin.
- Q: Como funciona a comissão? → A: Cada pagamento leva uma comissão para a conta master (dona da
  aplicação do Mercado Pago); a conta da banca funciona como subconta (vendedor do marketplace).
  O percentual fica nas configurações do sistema, só no banco de dados (não aparece em tela
  nenhuma), com padrão de 3%. O mesmo percentual vale para todos os meios.
- Q: O cartão pode ser parcelado? → A: Sim, com os juros pagos pelo cliente.
- Q: O limite fixo de saque do antigo (R$ 3.000 só para o cliente de id 1027) continua? → A: Não.
  É removido; os limites de saque vêm das configurações do cliente.
- Q: Como o saque é pago? → A: Manual (baixa no painel) ou automático pela API do Mercado Pago,
  conforme a configuração do sistema.
- Q: Notificações entram nesta spec? → A: Não.
- Q: Admin ou supervisor podem acessar o painel de um cliente? → A: Sim, pelo painel. No acesso,
  podem fazer tudo que o cliente faz, inclusive depositar e sacar.
- Q: E se um estorno de cartão chegar e o cliente não tiver mais saldo? → A: O saldo fica
  negativo.

### Session 2026-10-11 (clarify)

- Q: Quando o bônus de indicação é pago ao indicador? → A: Uma indicação só conta quando o
  indicado faz o primeiro depósito aprovado. Ao atingir a quantidade de indicações exigida pela
  promoção de Indicação (ex.: 5), o indicador recebe o valor do bônus. O apostador tem uma área
  para ver quem indicou e quem ainda está pendente de depósito.
- Q: Depois de receber o bônus ao atingir a meta, o indicador pode ganhar de novo? → A: Sim,
  recorrente: a cada novo grupo da quantidade exigida (5, 10, 15…), recebe o bônus de novo.
- Q: No modo automático, saques acima de um valor esperam aprovação manual? → A: Sim. Acima do
  valor configurado (padrão R$ 1.000,00), o saque vai para a fila manual; até esse valor, é pago
  automaticamente.
- Q: Se o primeiro depósito de um indicado for estornado depois de contar como indicação válida?
  → A: A indicação volta para "Pendente de depósito"; se ela tinha completado um grupo, o bônus
  desse grupo é cancelado com o rollover, debitando o saldo de bônus mesmo que fique negativo.

### Session 2026-10-11 (bônus: expiração e campos da tela antiga)

- Q: O bônus tem data de expiração? → A: Sim. Se o rollover não for cumprido no prazo, o bônus é
  retirado, como no antigo (`dias_cumprir_bonus` e a rotina `BonusCommand`). Hoje o sistema novo
  não tem esse prazo.
- Q: A promoção do sistema novo tem todos os campos da tela "Cadastrar bônus" do antigo? → A: Não.
  Faltam "Dias para cumprir o bônus", "Rollover primeiro depósito" e "Afiliado"; esta spec os
  acrescenta (FR-018e a FR-018h). Os demais campos (nome, ganho em, tipo de ganho, valor, começa
  em, termina em, ativo, modalidade, rollover, valor mínimo e máximo das apostas, valor converter
  bônus, depósito máximo bônus e odds mínimas) já existem desde a spec 002.
- Q: O cashback do depósito tem rollover? → A: Sim, como no antigo: o valor do cashback é somado ao
  rollover do depósito que o gerou (ex.: depósito de R$ 100,00 com cashback de R$ 5,00 exige
  R$ 105,00 apostados). Correção em relação ao antigo: lá, o rollover primeiro depósito
  sobrescrevia o rollover e descartava o cashback; aqui o cashback é somado também nesse caso.
- Q: O rollover primeiro depósito é o mesmo rollover de 1x da spec 004? → A: Não. O de 1x vale para
  todo depósito (configuração de rollover de depósito); o rollover primeiro depósito só troca o
  rollover do depósito que deu o bônus por "vezes do rollover da promoção × valor do depósito".

### Session 2026-10-12 (ajustes do responsável)

- Q: A tela de créditos ganha visual novo? → A: Não. A tela mantém o visual do antigo; só os
  modais melhoram o visual.
- Q: Como a banca liga a conta do Mercado Pago? → A: Pelo campo "Gateway de pagamentos" nas
  configurações do painel, como no antigo: o admin escolhe Mercado Pago, é levado à autorização
  do Mercado Pago, entra na conta dele e volta vinculado, sem criar aplicação nem copiar
  credenciais à mão.
- Q: De quem são as credenciais da aplicação? → A: Do programador, dono do sistema (conta master).
  Elas ficam no ambiente do servidor de cada instalação. Cada sistema (banca), pelo admin, vincula
  a própria conta do Mercado Pago à aplicação do programador, e o programador recebe a comissão
  das vendas de cada conta vinculada.
- Q: O que o depósito exige do cadastro? → A: CPF e e-mail. Sem eles, o cliente informa antes de
  depositar.
- Q: O que o saque exige do cadastro? → A: Todos os campos de Meus dados preenchidos.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Cliente acompanha saldos e extrato (Priority: P1)

O cliente logado abre `/minha-conta` e vê a saudação com o nome dele, a logo da banca, os três
saldos (saldo real, bônus esportes e bônus cassino), cada um com o saldo anterior e a meta de
rollover a cumprir, e o extrato das movimentações, com filtros por operação e por situação do
palpite.

**Why this priority**: é a base do painel; sem ver saldo e movimentações, depósito e saque não
fazem sentido, e já entrega valor sozinho (o cliente acompanha o próprio dinheiro).

**Independent Test**: logar como um cliente com apostas, bônus e movimentações cadastradas,
abrir `/minha-conta` e conferir os saldos, as metas e o extrato com cada filtro.

**Acceptance Scenarios**:

1. **Given** um cliente logado com saldo, **When** ele abre `/minha-conta`, **Then** vê "Olá
   {nome}", a logo da banca, os cartões Meu saldo, Bônus esportes e Bônus cassino com valor
   atual, saldo anterior e meta a cumprir, e o aviso "Para apostas premiadas o novo saldo estará
   disponível em até 24 horas."
2. **Given** o extrato aberto, **When** o cliente escolhe a operação "Palpites" e a situação
   "Vencedor", **Then** só aparecem palpites vencedores; com outra operação, o filtro de situação
   fica desabilitado.
3. **Given** um palpite no extrato, **When** o cliente toca na linha, **Then** abre o bilhete
   daquela aposta.
4. **Given** um saque recusado no extrato, **When** o cliente toca na linha, **Then** vê o motivo
   da recusa.
5. **Given** mais movimentações do que cabem numa página, **When** o cliente rola até o fim,
   **Then** a próxima página é carregada.
6. **Given** um visitante sem login, **When** ele abre `/minha-conta`, **Then** é levado ao login
   e, depois de entrar, volta para `/minha-conta`.

---

### User Story 2 - Cliente deposita via Pix (Priority: P1)

O cliente abre "Depositar", informa o valor, decide se aceita bônus, escolhe Pix e recebe o QR
Code e o código "copia e cola". Ao pagar no banco, o painel confirma sozinho e o saldo é
atualizado, com cashback e bônus de depósito aplicados quando houver.

**Why this priority**: o depósito é a entrada de dinheiro do sistema e o Pix é o meio mais usado.

**Independent Test**: com a conta do Mercado Pago vinculada (ambiente de testes), gerar um
depósito Pix, simular o pagamento e conferir o saldo, a transação no extrato, o cashback, o
bônus e a comissão da conta master.

**Acceptance Scenarios**:

1. **Given** um cliente com CPF e e-mail cadastrados, **When** ele informa R$ 50,00, escolhe Pix e
   confirma, **Then** vê "Cobrança gerada", o QR Code (no desktop), o código copia e cola com
   botão de copiar e o aviso "Após o pagamento, é só aguardar nesta tela".
2. **Given** a cobrança na tela, **When** o pagamento é aprovado, **Then** em poucos segundos a
   tela mostra "Depósito realizado com sucesso!", fecha o modal e os saldos e o extrato mostram o
   depósito.
3. **Given** o cliente marcou "Aceito receber bônus" e há promoção de primeiro depósito vigente,
   **When** o primeiro depósito dele é aprovado dentro do valor máximo da promoção, **Then** ele
   recebe o bônus no saldo de bônus da modalidade e o rollover correspondente.
4. **Given** cashback configurado em 5%, **When** um depósito de R$ 100,00 é aprovado, **Then** o
   extrato mostra o depósito de R$ 100,00 e um cashback de R$ 5,00, ambos creditados no saldo, e
   o rollover do depósito passa a R$ 105,00 (o valor do cashback é somado ao rollover).
5. **Given** um cliente sem CPF ou sem e-mail (caso comum no primeiro depósito), **When** ele
   tenta depositar, **Then** vê o aviso com o que falta, o modal Meus dados abre e, ao salvar, o
   depósito é retomado.
6. **Given** um valor abaixo do mínimo configurado, **When** o cliente confirma, **Then** o
   depósito é recusado com o valor mínimo na mensagem.
7. **Given** a mesma confirmação de pagamento chegando duas vezes, **When** o sistema processa,
   **Then** o depósito é creditado uma única vez.
8. **Given** a cobrança Pix não paga até o fim do prazo, **When** o prazo acaba, **Then** a
   cobrança expira, nada é creditado e a tela informa que o código expirou.

---

### User Story 3 - Cliente solicita saque e a banca paga (Priority: P1)

O cliente abre "Sacar", informa o valor e confirma. O saldo é debitado na hora e o saque fica
pendente. Conforme a configuração, a banca paga manualmente e dá baixa no painel, ou o sistema
paga automaticamente pelo Mercado Pago. Se o saque for recusado ou falhar, o valor volta para o
saldo.

**Why this priority**: sem saque não há confiança para depositar; é a outra metade do fluxo de
dinheiro.

**Independent Test**: com um cliente com saldo e cadastro completo, solicitar um saque, dar baixa
pelo painel (modo manual) e, em outro caso, recusar com motivo; conferir saldo e extrato em cada
caso.

**Acceptance Scenarios**:

1. **Given** um cliente com R$ 200,00 de saldo, cadastro completo e rollover de depósito
   cumprido, **When** ele pede saque de R$ 100,00, **Then** o saldo passa a R$ 100,00, o extrato
   mostra o saque pendente e ele vê "Saque solicitado com sucesso. O valor estará disponível em
   até 24 horas."
2. **Given** um saque pendente no modo manual, **When** o admin ou supervisor dá baixa no painel,
   **Then** o saque passa a "Saque pago" e o extrato do cliente mostra o ícone de pago.
3. **Given** um saque pendente, **When** o admin ou supervisor recusa com o motivo "Chave Pix
   inválida", **Then** o saque passa a "Saque recusado" com o motivo e o valor volta ao saldo
   como "Devolução de saque".
4. **Given** o modo automático com limite de R$ 1.000,00, **When** o cliente pede um saque de
   R$ 300,00, **Then** o pagamento é enviado para a chave Pix dele e a confirmação marca "Saque
   pago"; se o envio falhar, o saque fica na fila manual do painel.
5. **Given** o modo automático com limite de R$ 1.000,00, **When** o cliente pede um saque de
   R$ 2.500,00, **Then** o saque vai para a fila manual e só é pago depois da aprovação no painel.
6. **Given** um cliente com qualquer campo de Meus dados vazio (nome, e-mail, CPF, data de
   nascimento, gênero, telefone, tipo de chave Pix, chave Pix ou nome do titular), **When** ele
   pede o saque, **Then** vê o que falta, o modal Meus dados abre e, ao salvar, o saque é
   retomado.
7. **Given** um rollover de depósito pendente de R$ 30,00, **When** o cliente pede o saque,
   **Then** é recusado com "Só é possível sacar depois de cumprir o rollover do depósito.
   Faltam R$ 30,00."
8. **Given** o cliente já fez o número máximo de saques do dia ou atingiu o valor máximo diário,
   **When** pede outro, **Then** é recusado com o limite na mensagem.
9. **Given** um cliente com saque bloqueado ou saldo negativo, **When** ele pede saque, **Then** é
   recusado com o motivo.

---

### User Story 4 - Cliente deposita com cartão de crédito, parcelado ou não (Priority: P2)

O cliente escolhe "Cartão de crédito", informa os dados do cartão, escolhe as parcelas (com o
valor de cada parcela e o total com juros) e confirma. Aprovado, o valor do depósito, sem os
juros, entra no saldo.

**Why this priority**: amplia os meios de entrada, mas o Pix já atende o fluxo principal.

**Independent Test**: no ambiente de testes do Mercado Pago, depositar com cartão de teste à
vista e parcelado, e com um cartão de teste recusado; conferir saldo e mensagens.

**Acceptance Scenarios**:

1. **Given** um depósito de R$ 300,00, **When** o cliente informa o cartão, **Then** vê as opções
   de parcelamento com valor da parcela e total com juros.
2. **Given** o cliente escolhe 3 parcelas, **When** o pagamento é aprovado, **Then** o saldo
   recebe R$ 300,00 (os juros ficam com o cliente) e o extrato mostra o depósito com o meio
   "Cartão de crédito".
3. **Given** um cartão recusado pelo banco, **When** o cliente confirma, **Then** vê uma mensagem
   clara (ex.: "Pagamento recusado pelo banco emissor. Tente outro cartão.") e nada é creditado.
4. **Given** um pagamento em análise, **When** ele é aprovado depois, **Then** o saldo é creditado
   nesse momento e o cliente vê o depósito no extrato.
5. **Given** um depósito com cartão já creditado, **When** chega um estorno (chargeback ou
   devolução), **Then** o valor do depósito é debitado do saldo, mesmo que fique negativo, e o
   extrato mostra "Estorno de depósito".

---

### User Story 5 - Cliente converte ou exclui um bônus (Priority: P2)

No extrato, cada bônus tem as ações "Converter" (passa o valor liberado para o saldo real) e
"Excluir" (remove o bônus, o rollover e o saldo de bônus daquela modalidade).

**Why this priority**: regra de negócio já existente no antigo, que dá ao cliente controle sobre o
bônus; depende do extrato (US1).

**Independent Test**: com um bônus de rollover cumprido, converter e conferir o saldo; com um
bônus em andamento, excluir e conferir o saldo de bônus e o rollover.

**Acceptance Scenarios**:

1. **Given** um bônus com R$ 80,00 liberados e valor máximo de conversão de R$ 50,00, **When** o
   cliente confirma "Converter", **Then** R$ 50,00 entram no saldo real e o saldo de bônus da
   modalidade é zerado no valor liberado.
2. **Given** um bônus sem valor liberado, **When** o cliente toca em "Converter", **Then** vê "Você
   não tem valor liberado para conversão."
3. **Given** um bônus em andamento sem valor liberado, **When** o cliente confirma "Excluir",
   **Then** o rollover do bônus é zerado e o saldo de bônus da modalidade é recalculado sem ele.
4. **Given** um bônus com valor liberado, **When** o cliente toca em "Excluir", **Then** vê "Antes
   de excluir o bônus, converta o valor liberado."
5. **Given** um bônus já cumprido ou já excluído, **When** o cliente toca em "Excluir", **Then** vê
   "Este bônus já cumpriu a meta ou o rollover foi removido."
6. **Given** um bônus de R$ 50,00 recebido com 7 dias para cumprir e o rollover não cumprido,
   **When** passam os 7 dias, **Then** o bônus é retirado: o rollover é zerado, o saldo de bônus da
   modalidade perde o que restava dele, o extrato mostra "Bônus expirado" e a coluna "Expira em"
   mostra "Expirado".
7. **Given** um bônus com o rollover cumprido antes do prazo, **When** o prazo passa, **Then** o
   bônus não é retirado e o valor liberado continua disponível para converter.
8. **Given** uma promoção de primeiro depósito com rollover 5x e "Rollover primeiro depósito"
   ligado, **When** o primeiro depósito de R$ 100,00 é aprovado, **Then** o rollover do depósito
   passa a R$ 500,00, no lugar do rollover padrão.
9. **Given** uma promoção vigente do afiliado "AFI10" e outra sem afiliado, **When** um cliente com
   o código "AFI10" faz um depósito, **Then** recebe a promoção do afiliado; um cliente sem
   afiliado recebe a promoção sem afiliado.

---

### User Story 6 - Cliente atualiza os próprios dados (Priority: P2)

Pelo menu "Meus dados", o cliente edita nome, e-mail, CPF, data de nascimento, gênero, telefone,
senha e a chave Pix de saque.

**Why this priority**: depósito exige CPF e e-mail e saque exige todos os campos preenchidos; sem esta tela o cliente
fica preso.

**Independent Test**: editar cada campo e conferir as validações e o salvamento.

**Acceptance Scenarios**:

1. **Given** o modal Meus dados, **When** o cliente informa um CPF válido e livre e salva, **Then**
   o CPF é gravado e passa a aparecer mascarado.
2. **Given** um CPF inválido ou já usado por outro cliente, **When** salva, **Then** vê "CPF
   inválido." ou "CPF já cadastrado."
3. **Given** uma data de nascimento de alguém com menos de 18 anos, **When** salva, **Then** vê "É
   preciso ter pelo menos 18 anos."
4. **Given** o tipo de chave Pix "CPF", **When** o cliente digita a chave, **Then** o campo aplica
   a máscara de CPF; para e-mail, a chave precisa ser um e-mail válido.
5. **Given** uma nova senha com menos de 6 caracteres ou sem letras e números, **When** salva,
   **Then** vê a regra da senha.

---

### User Story 7 - Admin ou supervisor acessa a conta de um cliente (Priority: P2)

No painel, o admin ou supervisor escolhe um cliente e "Acessa a conta". O painel do cliente abre
com uma tarja "Você está acessando a conta de {nome}" e botão "Voltar ao painel". Nesse acesso,
pode fazer tudo que o cliente faz, inclusive depositar e sacar, e tudo fica registrado com o
autor.

**Why this priority**: o atendimento da banca resolve problemas do cliente sem pedir a senha dele,
como o antigo permitia.

**Independent Test**: logar no painel como supervisor, acessar a conta de um cliente, fazer um
depósito e um saque e conferir a tarja, o registro do acesso e o autor das operações.

**Acceptance Scenarios**:

1. **Given** um supervisor no painel, **When** ele acessa a conta de um cliente, **Then** abre
   `/minha-conta` daquele cliente com a tarja e o botão "Voltar ao painel", sem usar a senha nem
   a sessão do cliente.
2. **Given** o acesso assistido, **When** o supervisor solicita um saque pelo cliente, **Then** o
   saque segue as mesmas regras do cliente e a transação registra o supervisor como autor.
3. **Given** um gerente ou vendedor, **When** tenta acessar a conta de um cliente, **Then** o
   acesso é negado.
4. **Given** qualquer acesso assistido, **When** ele começa, **Then** fica registrado quem
   acessou, qual cliente e quando.
5. **Given** o acesso assistido aberto, **When** o supervisor toca em "Voltar ao painel" ou o
   acesso expira, **Then** o acesso ao painel do cliente é encerrado.

---

### User Story 8 - Banca vincula a conta do Mercado Pago (Priority: P1)

Nas configurações do painel, o admin escolhe "Mercado Pago" no campo "Gateway de pagamentos", é
levado à autorização do Mercado Pago, entra na conta da banca e volta com ela vinculada, sem criar
aplicação nem copiar credenciais. A aplicação é do programador, dono do sistema (conta master),
cujas credenciais já ficam no servidor. A partir daí, todos os depósitos e saques da banca usam a
conta vinculada, e o programador recebe a comissão de cada venda.

**Why this priority**: sem o vínculo nenhum depósito funciona; é pré-requisito das US2, US3 e US4.

**Independent Test**: como admin, iniciar o vínculo, autorizar no Mercado Pago (ambiente de
testes) e conferir que a situação passa a "Vinculada" e que um depósito leva a comissão.

**Acceptance Scenarios**:

1. **Given** um admin nas configurações do painel, **When** ele escolhe "Mercado Pago" em
   "Gateway de pagamentos", entra na conta do Mercado Pago e autoriza, **Then** volta ao painel com
   a conta vinculada e o campo mostra "Mercado Pago — vinculada" com a data.
2. **Given** a autorização no Mercado Pago, **When** o admin cancela ou ela falha, **Then** volta ao
   painel com a mensagem do motivo e o vínculo anterior (se houver) continua valendo.
3. **Given** a conta vinculada, **When** a autorização estiver perto de expirar, **Then** o sistema a
   renova sozinho, sem ação do admin.
4. **Given** a conta não vinculada ou com a renovação falhando, **When** um cliente tenta
   depositar, **Then** vê "Depósitos indisponíveis no momento. Tente mais tarde." e o painel
   mostra a falha para o admin.
5. **Given** um depósito de R$ 100,00 aprovado com comissão de 3%, **When** o pagamento é
   processado, **Then** R$ 3,00 vão para a conta master e o cliente recebe R$ 100,00 no saldo.
6. **Given** um supervisor, gerente ou vendedor, **When** tenta vincular ou desvincular a conta,
   **Then** o acesso é negado.

---

### User Story 9 - Cliente indica amigos e acompanha as indicações (Priority: P3)

Com uma promoção de "Indicação" vigente, o menu mostra "Indique um amigo": o cliente compartilha
pelo WhatsApp o link de cadastro com o código dele e acompanha, numa área própria, quem se
cadastrou pelo link, quem já fez o primeiro depósito (indicação válida) e quem ainda está
pendente de depósito. Ao atingir a quantidade de indicações válidas exigida pela promoção, ele
recebe o bônus.

**Why this priority**: recurso de crescimento, não essencial ao fluxo de dinheiro.

**Independent Test**: com promoção de indicação ativa exigindo 5 indicações, cadastrar 6
clientes pelo link, fazer o primeiro depósito de 5 deles e conferir a área de indicações, a
contagem e o bônus pago ao indicador.

**Acceptance Scenarios**:

1. **Given** uma promoção de indicação vigente, **When** o cliente abre o menu, **Then** vê
   "Indique um amigo"; sem promoção vigente, o item não aparece.
2. **Given** o modal aberto, **When** o cliente toca em "Indicar", **Then** o WhatsApp abre com a
   mensagem e o link de cadastro com o código dele.
3. **Given** um novo cliente cadastrado pelo link, **When** o indicador abre a área de
   indicações, **Then** o indicado aparece como "Pendente de depósito" e não conta para a meta.
4. **Given** um indicado pendente, **When** o primeiro depósito dele é aprovado, **Then** ele passa
   a "Depositou" e a contagem de indicações válidas do indicador aumenta em 1.
5. **Given** uma promoção que exige 5 indicações válidas e o indicador com 4, **When** o quinto
   indicado faz o primeiro depósito, **Then** o indicador recebe o valor do bônus da promoção no
   saldo de bônus da modalidade, com o rollover dela, e a área mostra "Bônus recebido".
6. **Given** o modal aberto, **When** o cliente o vê, **Then** encontra "Você tem X indicações
   válidas; faltam Y para ganhar R$ Z de bônus".
7. **Given** o indicador já recebeu o bônus com 5 indicações válidas, **When** a décima indicação
   fica válida, **Then** ele recebe o bônus de novo e a área mostra 2 bônus recebidos.

---

### Edge Cases

- **Conta do Mercado Pago sem a chave Pix cadastrada**: o Pix não fica disponível; os demais meios
  continuam.
- **NuPay indisponível na conta**: a opção não aparece no depósito.
- **Cliente fecha a tela antes de pagar o Pix**: o pagamento continua valendo; ao ser aprovado,
  o saldo é creditado pela confirmação do Mercado Pago, sem depender da tela aberta.
- **Confirmação do Mercado Pago com assinatura inválida**: é ignorada e registrada; nada é
  creditado.
- **Confirmação de um pagamento que não é de nenhum depósito do sistema**: é ignorada e registrada.
- **Estorno parcial de cartão**: debita só o valor estornado.
- **Saldo negativo**: o cliente não aposta nem saca; o próximo depósito cobre o negativo primeiro,
  e o restante vira saldo disponível.
- **Bônus de depósito sobre um depósito depois estornado**: o bônus e o rollover ligados àquele
  depósito são cancelados junto com o estorno.
- **Bônus expira com parte do saldo dele já usada em apostas em aberto**: a retirada tira só o que
  restava daquele bônus; apostas já feitas com ele seguem normalmente.
- **Promoção do afiliado e promoção geral vigentes ao mesmo tempo**: vale só a do afiliado para
  os clientes dele (FR-018g).
- **Primeiro depósito de um indicado estornado**: a indicação volta a pendente e, se tinha
  completado um grupo, o bônus de indicação desse grupo é cancelado (FR-044a).
- **Dois saques pedidos ao mesmo tempo**: só passam enquanto houver saldo; o segundo é recusado
  se o primeiro já consumiu o saldo.
- **Saque automático sem saldo na conta do Mercado Pago**: o envio falha e o saque vai para a
  fila manual do painel, sem devolver o valor ao cliente até a decisão.
- **Cliente desativado com o painel aberto**: na próxima ação, vê "Pagamento pendente ou conta
  desativada." com "Atualizar" e "Sair".
- **Cliente com cobrança Pix em aberto pede outra**: a nova cobrança é gerada normalmente; cada
  uma é creditada se for paga.
- **Parcelamento acima do máximo configurado**: as opções acima do máximo não aparecem.
- **Acesso assistido a um cliente excluído**: é negado.
- **Comissão maior que o valor líquido do pagamento** (depósito muito baixo): o depósito é
  recusado na criação com o valor mínimo na mensagem.

## Requirements *(mandatory)*

### Functional Requirements

**Tela e navegação**

- **FR-001**: O sistema DEVE oferecer o painel do cliente na rota `/minha-conta`, só para clientes
  logados; sem login, o visitante DEVE ir para o login e voltar para `/minha-conta` depois de
  entrar.
- **FR-002**: O painel DEVE mostrar o botão voltar (para `/`), a saudação "Olá {nome}", a logo da
  banca e o menu, que DEVE ficar fixo na lateral no desktop e abrir como gaveta no mobile.
- **FR-003**: O menu DEVE ter: Meus dados, Realizar aposta, Depositar, Sacar, Indique um amigo (só
  com promoção de Indicação vigente), Atendimento (WhatsApp da banca, só se configurado), Regras e
  Sair (com confirmação).
- **FR-004**: A tela de créditos (cabeçalho, logo, saldos, aviso, filtros, tabela do extrato e
  menu lateral) DEVE manter o visual de `screens/credits` do antigo. Só os modais (depósito, saque,
  Meus dados, Indique um amigo, Minhas indicações e confirmações) DEVEM usar o design novo
  aprovado (cartões arredondados, cabeçalho escuro com faixa na cor do tema, botões com
  hierarquia, folha de baixo no mobile), com as cores do tema da banca.
- **FR-005**: Se o cliente estiver desativado, o painel DEVE mostrar só "Pagamento pendente ou
  conta desativada." com os botões "Atualizar" e "Sair".

**Saldos e extrato**

- **FR-006**: O painel DEVE mostrar três cartões — Meu saldo, Bônus esportes e Bônus cassino — com
  o valor atual, o saldo anterior e a meta a cumprir (rollover pendente de depósito, no saldo
  real, e de bônus da modalidade, nos cartões de bônus).
- **FR-007**: Saldo negativo DEVE aparecer em destaque de erro com o aviso "Saldo negativo por
  estorno de pagamento".
- **FR-008**: O painel DEVE mostrar o aviso "Para apostas premiadas o novo saldo estará disponível
  em até 24 horas."
- **FR-009**: O extrato DEVE listar as movimentações do cliente da mais recente para a mais antiga,
  paginado (padrão de 30 por página, máximo de 100), com carregamento da próxima página ao rolar.
- **FR-010**: O extrato DEVE ter filtro de operação (Todas, Palpites, Depósitos, Saques, Bônus,
  Cashback, Estornos) e, só com "Palpites", filtro de situação (Todos, Aguardando, Vencedor,
  Perdedor). "Saques" inclui saques pendentes, pagos e recusados e suas devoluções.
- **FR-011**: Cada linha DEVE mostrar data (no depósito, a data do pagamento), operação, valor,
  prêmio (só em palpite, com o acréscimo), expira em (só em bônus), rollover (só em depósito e
  bônus), valor liberado (só em bônus), situação e ação.
- **FR-012**: A situação DEVE usar os ícones do antigo: palpite aguardando (relógio), vencedor
  (troféu), perdedor (X), cancelado (bloqueado); crédito ("+"), débito ("−"), saque pago
  (cifrão) e saque recusado (ícone que mostra o motivo ao tocar).
- **FR-013**: Tocar num palpite DEVE abrir o bilhete da aposta; tocar num saque recusado DEVE
  mostrar o motivo.
- **FR-014**: Sem movimentações, o extrato DEVE mostrar "Nenhum registro encontrado".

**Bônus**

- **FR-015**: Cada bônus no extrato DEVE ter as ações Converter e Excluir, com confirmação.
- **FR-016**: Converter DEVE passar para o saldo real o valor liberado do bônus, limitado ao valor
  máximo de conversão da promoção, e tirar o valor liberado do saldo de bônus da modalidade,
  guardando o saldo anterior das duas carteiras e registrando a movimentação.
- **FR-017**: Converter sem valor liberado DEVE ser recusado com "Você não tem valor liberado para
  conversão."
- **FR-018**: Excluir DEVE zerar o rollover e o saldo do bônus e recalcular o saldo de bônus da
  modalidade sem ele; DEVE ser recusado com "Antes de excluir o bônus, converta o valor
  liberado." quando houver valor liberado, com "Seu bônus está expirado." quando o bônus já tiver
  expirado, e com "Este bônus já cumpriu a meta ou o rollover foi removido." quando o bônus já
  estiver cumprido ou excluído.
- **FR-018a**: Todo bônus recebido DEVE guardar a data de expiração, igual à data do recebimento
  mais os "dias para cumprir o bônus" da promoção; alterar a promoção depois NÃO DEVE mudar a
  expiração dos bônus já entregues.
- **FR-018b**: Uma rotina periódica (pelo menos a cada hora) DEVE retirar os bônus expirados que não
  cumpriram o rollover: zerar o rollover, tirar o saldo restante daquele bônus do saldo de bônus da
  modalidade (guardando o saldo anterior) e registrar a movimentação "Bônus expirado".
- **FR-018c**: Bônus com o rollover já cumprido NÃO DEVE expirar; o valor liberado continua
  disponível para conversão.
- **FR-018d**: A coluna "Expira em" do extrato DEVE mostrar, para cada bônus, os dias restantes
  ("3 dia(s)") ou "Expirado", como no antigo.

**Promoções (ajustes da spec 002)**

- **FR-018e**: O cadastro e a edição de promoções no painel DEVEM ganhar os campos da tela
  "Cadastrar bônus" do antigo que faltam: "Dias para cumprir o bônus" (obrigatório, inteiro, pelo
  menos 1, padrão 30), "Rollover primeiro depósito" (sim ou não, só habilitado na categoria
  Primeiro depósito, padrão não), "Afiliado" (opcional, código do afiliado) e, na categoria
  Indicação, a quantidade de indicações válidas exigida (FR-044b).
- **FR-018f**: Com "Rollover primeiro depósito" ligado, o rollover do próprio depósito que deu o
  bônus DEVE passar a ser o número de vezes do rollover da promoção multiplicado pelo valor do
  depósito, no lugar do rollover de depósito padrão das configurações.
- **FR-018g**: Uma promoção com afiliado DEVE valer só para clientes com aquele código de afiliado.
  Na escolha da promoção de depósito, o sistema DEVE preferir a promoção vigente do afiliado do
  cliente e, sem ela, usar a promoção vigente sem afiliado; clientes sem afiliado só recebem
  promoções sem afiliado.
- **FR-018h**: As promoções padrão criadas na instalação (spec 006) DEVEM receber os valores padrão
  dos campos novos.

**Depósito**

- **FR-019**: O modal de depósito DEVE ter as etapas: valor (com atalhos de valores rápidos),
  aceite de bônus ("Aceito receber bônus", com o aviso de que aceita os termos do rollover) e
  escolha do meio entre os habilitados nas configurações: Pix, Cartão de crédito e NuPay (este só
  se disponível na conta do Mercado Pago).
- **FR-020**: O depósito DEVE exigir cliente ativo, depósitos habilitados, conta do Mercado Pago
  vinculada, CPF e e-mail cadastrados (faltando algum, mostrar o aviso com o que falta, abrir Meus
  dados e, depois de salvar, retomar o depósito) e valor entre o mínimo e o máximo configurados.
- **FR-021**: A escolha de aceitar bônus DEVE ficar gravada no cliente e valer para a aplicação de
  bônus desse depósito.
- **FR-022**: No Pix, o sistema DEVE gerar a cobrança no Mercado Pago e mostrar o QR Code (no
  desktop), o código copia e cola com botão de copiar (aviso "Código Pix copiado") e o aviso "Após
  o pagamento, é só aguardar nesta tela"; a tela DEVE acompanhar a situação e, aprovado, mostrar
  "Depósito realizado com sucesso!", fechar e atualizar saldos e extrato.
- **FR-023**: A cobrança Pix DEVE expirar após o prazo configurado; expirada, a tela DEVE informar
  e permitir gerar outra.
- **FR-024**: No cartão de crédito, os dados do cartão DEVEM ser capturados direto pelo Mercado
  Pago no navegador, sem passar nem ficar guardados no sistema; o cliente DEVE ver as opções de
  parcelamento (até o máximo configurado) com valor da parcela e total com juros, pagos por ele.
- **FR-025**: O valor creditado no saldo DEVE ser sempre o valor do depósito, sem os juros do
  parcelamento.
- **FR-026**: Recusas de pagamento DEVEM mostrar mensagens claras em português conforme o motivo
  informado pelo Mercado Pago (ex.: dados inválidos, saldo insuficiente, recusado pelo banco).
- **FR-027**: Cada depósito DEVE levar a comissão da conta master, calculada com o percentual das
  configurações do sistema (padrão 3%) sobre o valor do depósito, igual para todos os meios.
- **FR-028**: O percentual de comissão NÃO DEVE aparecer em nenhuma tela nem resposta da API; é
  alterado só direto no banco de dados.
- **FR-029**: O sistema DEVE receber as confirmações do Mercado Pago, validar a assinatura,
  consultar o pagamento no Mercado Pago e processá-lo uma única vez por pagamento, mesmo que a
  confirmação chegue repetida.
- **FR-030**: Com o pagamento aprovado, o sistema DEVE, numa única operação: creditar o depósito no
  saldo (cobrindo antes um saldo negativo, se houver); criar o rollover de depósito quando a
  configuração exigir, com o número de vezes configurado; creditar o cashback configurado
  (percentual do depósito) como movimentação "Cashback", somando o valor do cashback ao rollover
  daquele depósito (inclusive quando o rollover primeiro depósito do FR-018f estiver ligado, e
  mesmo com o rollover de depósito desligado, caso em que o rollover passa a ser só o valor do
  cashback); e aplicar a promoção de "Primeiro
  depósito" ou "Qualquer depósito" vigente (escolhida pela regra de afiliado do FR-018g), se o
  cliente aceitou bônus e o valor não passa do máximo da promoção, com o rollover e a expiração
  dela (FR-018a) e, quando ligado, o rollover primeiro depósito (FR-018f).
- **FR-031**: Pagamento recusado, cancelado ou expirado DEVE ser marcado no depósito, sem crédito.
- **FR-032**: Estorno ou chargeback de um depósito creditado DEVE debitar do saldo o valor
  estornado, mesmo que o saldo fique negativo, registrar a movimentação "Estorno de depósito" e
  cancelar o bônus e o rollover ligados àquele depósito.
- **FR-033**: A consulta da situação de um depósito DEVE responder só ao dono do depósito (ou ao
  acesso assistido daquele cliente).

**Saque**

- **FR-034**: O modal de saque DEVE ter o valor, a chave Pix de destino (a principal do cliente) e
  o resumo do saldo depois do saque.
- **FR-035**: O saque DEVE exigir: saques habilitados, cliente ativo, saque não bloqueado nas
  configurações do cliente, saldo não negativo e suficiente ("Só restam R$ X do seu saldo."),
  todos os campos de Meus dados preenchidos (nome, e-mail, CPF, data de nascimento, gênero,
  telefone, tipo de chave Pix, chave Pix e nome do titular), valor mínimo de
  saque, quantidade máxima de saques por dia e valor máximo de saque por dia das configurações do
  cliente, e rollover de depósito cumprido ("Só é possível sacar depois de cumprir o rollover do
  depósito. Faltam R$ X.").
- **FR-036**: Faltando dado do cadastro, o sistema DEVE informar o que falta, abrir Meus dados e,
  depois de salvar, retomar o saque.
- **FR-037**: Ao solicitar, o sistema DEVE debitar o saldo na hora, guardando o saldo anterior, e
  registrar o saque como pendente, com proteção contra dois saques simultâneos consumirem o mesmo
  saldo.
- **FR-038**: No modo manual, o saque DEVE ir para uma fila no painel, onde admin e supervisor
  podem dar baixa ("Saque pago") ou recusar com motivo ("Saque recusado"); a recusa DEVE devolver
  o valor ao saldo como "Devolução de saque".
- **FR-039**: No modo automático, saques até o valor máximo de saque automático configurado DEVEM
  ser pagos pelo Mercado Pago na chave Pix do cliente, com "Saque pago" marcado na confirmação;
  saques acima desse valor DEVEM ir para a fila manual do painel e, se aprovados lá, ser pagos
  pelo Mercado Pago. Se o envio falhar, o saque DEVE ir para a
  fila manual do painel com o motivo da falha, sem devolver o valor até a decisão.
- **FR-040**: O limite fixo de saque por cliente do sistema antigo NÃO DEVE existir; todo limite de
  saque vem das configurações do cliente.

**Meus dados**

- **FR-041**: Meus dados DEVE permitir editar nome, e-mail, CPF, data de nascimento, gênero,
  telefone, nova senha (opcional) e a chave Pix de saque (tipo, chave com máscara conforme o tipo
  e nome do titular); o login aparece só para leitura.
- **FR-042**: Meus dados DEVE validar: idade mínima de 18 anos, CPF válido e não usado por outro
  cliente, e-mail não usado por outro cliente, senha com no mínimo 6 caracteres com letras e
  números, e chave Pix do tipo e-mail como e-mail válido.

**Indique um amigo**

- **FR-043**: Com promoção de Indicação vigente, o modal "Indique um amigo" DEVE mostrar quantas
  indicações válidas o cliente tem, quantas faltam para a meta da promoção e o valor do bônus, e
  o botão "Indicar" DEVE abrir o WhatsApp com o link de cadastro contendo o código do cliente.
- **FR-044**: O cadastro feito pelo link DEVE registrar o cliente indicador.
- **FR-044a**: Uma indicação só DEVE contar como válida quando o indicado tiver o primeiro
  depósito aprovado; cadastro sem depósito fica como "Pendente de depósito". Se esse primeiro
  depósito for estornado, a indicação DEVE voltar para "Pendente de depósito" (um novo depósito
  aprovado a valida de novo) e, se ela tinha completado um grupo, o bônus desse grupo DEVE ser
  cancelado com o rollover, debitando o saldo de bônus da modalidade mesmo que fique negativo.
- **FR-044b**: A promoção de Indicação DEVE ter a quantidade de indicações válidas exigida (padrão
  5). A cada grupo completo dessa quantidade (5, 10, 15…), o indicador DEVE receber o valor do
  bônus da promoção no saldo de bônus da modalidade, com o rollover dela, se aceitar bônus e
  estiver ativo; cada indicação válida conta para um único grupo.
- **FR-044c**: O painel do cliente DEVE ter a área "Minhas indicações", com cada indicado (nome,
  data do cadastro e situação: "Pendente de depósito" ou "Depositou"), a contagem de válidas, a
  meta, quantas faltam para o próximo bônus e quantos bônus já recebeu; a lista NÃO DEVE mostrar telefone, e-mail, CPF nem valores
  depositados pelo indicado, e DEVE ser paginada (máximo de 100 por página).

**Acesso assistido (painel)**

- **FR-045**: Admin e supervisor DEVEM poder, pelo painel, acessar a conta de qualquer cliente não
  excluído; gerente e vendedor NÃO DEVEM ter esse acesso.
- **FR-046**: O acesso assistido NÃO DEVE usar a senha nem a sessão do cliente; DEVE abrir
  `/minha-conta` daquele cliente com a tarja "Você está acessando a conta de {nome}" e o botão
  "Voltar ao painel", e DEVE expirar sozinho após o tempo configurado.
- **FR-047**: No acesso assistido, o usuário do painel DEVE poder fazer tudo que o cliente faz,
  inclusive depositar e sacar, com as mesmas regras.
- **FR-048**: Todo acesso assistido DEVE ser registrado (quem, qual cliente, início e fim), e toda
  operação feita nele DEVE gravar o usuário do painel como autor.

**Conta do Mercado Pago e configurações**

- **FR-049**: Cada sistema (banca) DEVE usar uma única conta do Mercado Pago, vinculada pelo admin
  nas configurações do painel, no campo "Gateway de pagamentos": ao escolher "Mercado Pago", o
  admin DEVE ser levado à autorização do Mercado Pago na aplicação do programador (conta master)
  e voltar ao painel com a conta vinculada, sem criar aplicação nem informar credenciais à mão;
  supervisor, gerente e vendedor NÃO DEVEM vincular nem desvincular.
- **FR-049a**: O retorno da autorização DEVE aceitar só pedidos de vínculo iniciados por aquele
  admin naquele sistema (proteção contra retorno forjado); cancelamento ou falha DEVE voltar ao
  painel com o motivo, mantendo o vínculo anterior.
- **FR-050**: As credenciais da aplicação da conta master DEVEM ficar só no ambiente do servidor; a
  autorização da conta da banca DEVE ser guardada criptografada e renovada sozinha antes de
  expirar; nenhuma credencial DEVE ficar no código nem ir para o navegador (exceto a chave pública
  usada para capturar o cartão).
- **FR-051**: O campo "Gateway de pagamentos" DEVE mostrar ao admin a situação do vínculo
  (vinculada com a data, não vinculada ou com falha na renovação) e permitir refazê-lo ou
  desvinculá-lo; desvinculado, os depósitos ficam indisponíveis.
- **FR-052**: As configurações do sistema DEVEM ter, com padrões: percentual de comissão (3%),
  valor mínimo (R$ 1,00) e máximo (R$ 50.000,00) de depósito, valor mínimo de saque (R$ 10,00),
  rollover de depósito ligado com 1 vez, cashback (0%), modo de saque (manual), valor máximo de saque automático (R$ 1.000,00), meios de depósito
  habilitados (Pix e cartão), máximo de parcelas (12), prazo da cobrança Pix (30 minutos) e tempo
  do acesso assistido (60 minutos).
- **FR-053**: Toda rota nova ou alterada da API DEVE entrar na coleção do Postman do projeto.
- **FR-054**: Toda recusa DEVE ter mensagem clara em português com o motivo.

### Key Entities

- **Depósito**: pedido de entrada de dinheiro de um cliente: valor, meio (Pix, cartão, NuPay),
  parcelas e total pago (no cartão), aceite de bônus, situação (pendente, aprovado, recusado,
  expirado, estornado), identificador do pagamento no Mercado Pago, comissão da conta master,
  data do pagamento, prazo (no Pix), autor (cliente ou usuário do painel no acesso assistido).
- **Saque**: pedido de saída de dinheiro: valor, chave Pix de destino, modo (manual ou
  automático), situação (pendente, pago, recusado), motivo da recusa ou falha, identificador do
  pagamento no Mercado Pago (no automático), quem deu baixa ou recusou e quando, autor.
- **Movimentação do cliente** (já existente): passa a registrar depósito, cashback, saque,
  devolução de saque, estorno de depósito e conversão de bônus, ligadas ao depósito ou saque de
  origem.
- **Confirmação do Mercado Pago**: registro de cada aviso recebido (identificador, tipo, assinatura
  válida ou não, processado ou ignorado e por quê), para auditoria e para não processar duas vezes.
- **Vínculo do Mercado Pago**: a autorização única da conta da banca: situação, data do vínculo,
  validade, última renovação e falha da renovação.
- **Acesso assistido**: quem do painel acessou, qual cliente, início, fim e expiração.
- **Indicação**: ligação entre o cliente indicador e o cliente cadastrado pelo link dele, com a
  situação (pendente de depósito ou válida), a data em que ficou válida e a promoção em que contou.
- **Promoção** (já existente, spec 002): ganha "dias para cumprir o bônus", "rollover primeiro
  depósito", afiliado (código, sem chave estrangeira, porque a tabela de afiliados ainda não
  existe) e, na categoria Indicação, a quantidade de indicações válidas exigida.
- **Rollover do bônus** (já existente, spec 004): ganha a data de expiração e a data em que
  expirou.
- **Configurações de pagamento**: os valores do FR-052, nas configurações do sistema.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Depois de pagar o Pix, o cliente vê o depósito confirmado na tela em até 10
  segundos em 95% dos casos.
- **SC-002**: Um cliente com cadastro completo gera um depósito Pix em menos de 1 minuto e pede um
  saque em menos de 30 segundos.
- **SC-003**: Nenhum depósito é creditado duas vezes, mesmo com confirmações repetidas.
- **SC-004**: 100% dos depósitos aprovados levam a comissão da conta master no percentual
  configurado.
- **SC-005**: 100% dos saques recusados ou com falha devolvem o valor ao saldo ou ficam na fila
  manual; nenhum some sem decisão.
- **SC-006**: Toda operação feita em acesso assistido identifica o usuário do painel que a fez.
- **SC-007**: O cliente encontra qualquer movimentação dos últimos 90 dias pelo extrato com no
  máximo dois filtros e rolagem.
- **SC-008**: Os modais funcionam em telas de 360 px de largura sem rolagem horizontal; a tela de
  créditos se comporta como a do antigo nessa largura (a tabela do extrato rola na horizontal).

## Assumptions

- **Login do cliente**: reaproveita a área do cliente da spec 002 (cadastro, login, meus dados,
  meios de pagamento e extrato); esta spec amplia o que já existe.
- **Atendimento**: os clientes do site não têm vendedor responsável no sistema novo; o item
  "Atendimento" usa o WhatsApp de contato da banca (spec 005).
- **Bônus já entregues antes desta spec**: recebem a expiração calculada com o padrão de 30 dias a
  partir da data em que foram recebidos; os que já passaram desse prazo expiram na primeira
  execução da rotina.
- **Spec 002 (FR-074)**: a expiração do saldo promocional, que a spec 002 deixou de fora, passa a
  ser definida por esta spec; a expiração de cashback continua fora (o cashback entra no saldo
  real, com o valor somado ao rollover do depósito).
- **NuPay**: depende de o Mercado Pago oferecer o meio na conta da banca; se não oferecer, a opção
  não aparece e uma integração direta com a NuPay fica para outra spec.
- **Saque automático**: depende da liberação da API de saques (Payouts) pelo Mercado Pago na conta
  da banca e de saldo nessa conta.
- **Comissão**: o Mercado Pago desconta a taxa dele antes; a comissão da conta master sai do que
  sobra. A conta master e a aplicação dela já existem; o programador deixa as credenciais no
  ambiente do servidor de cada instalação.
- **Endereço de retorno da autorização**: o Mercado Pago só devolve a autorização para endereços
  cadastrados na aplicação do programador. Cada instalação precisa ter o seu endereço de retorno
  cadastrado na aplicação, ou usar um endereço central que repasse o vínculo para a instalação
  certa (como o antigo fazia por `demo.wssports.bet`); a escolha fica para o plano.
- **Saque**: só por Pix, para a chave principal do cliente; transferência bancária fica fora.
- **Acesso assistido**: os clientes do site não pertencem à hierarquia de usuários, então admin e
  supervisor acessam qualquer cliente; a permissão é exclusiva dessas funções.
- **Fila de saques, vínculo e acesso assistido no painel**: são oferecidos pela API do painel, como
  nas specs anteriores.
- **Dinheiro**: valores calculados sem ponto flutuante.
- **Fora do escopo**: notificações, cassino (saldo, filtro e detalhes de sessão), PrimePag,
  Sellflux e transferência bancária no saque.
- **Dados fake**: nenhum; todos os dados da tela vêm do backend.
