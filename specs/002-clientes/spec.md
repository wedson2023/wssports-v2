# Feature Specification: Clientes (Apostadores)

**Feature Branch**: `002-clientes`

**Created**: 2026-09-29

**Status**: Draft

**Input**: User description: "Spec: clientes. Clientes são os apostadores do sistema, separados
dos usuários do painel e sem vínculo com gerentes ou vendedores; vínculo opcional com afiliado
por código de indicação. Tabela `clientes` (substitui a antiga `creditos`). Cadastro público,
login com telefone e senha por JWT em guard próprio, saldo com transações de crédito e débito
(saldo anterior e posterior), travas específicas de cada cliente criadas do zero, gestão de
clientes pelo painel com novas permissões do spatie. Fora do escopo: apostas/bilhetes, gestão
de afiliados, depósito via PIX/gateway e saque, bônus, rollover, cashback e cassino."

## Clarifications

### Session 2026-09-29

- Q: Como fica o vínculo com afiliado, se a tabela de afiliados ainda não existe? → A: Colunas
  que dependem de tabelas ainda não criadas ficam sem ligação (texto/varchar, sem chave
  estrangeira) e serão ajustadas em outra spec; o código de afiliado é guardado como texto, sem
  validação.
- Q: Haverá envio de WhatsApp? → A: Sim, existirá um disparo de WhatsApp na criação da conta.
- Q: O que acontece com os dados únicos de um cliente excluído? → A: Telefone, CPF e qualquer
  outro dado único recebem o sufixo `_deleted_<timestamp>` (ex.: `11988887777_deleted_1759150000`),
  liberando o valor para novos cadastros. Excluir e restaurar só por Admin ou Supervisor com
  permissão; na restauração, se algum dado único já estiver em uso, ele precisa ser alterado para
  o cliente voltar.
- Q: Qual a regra de senha? → A: Senha forte, com letras e números e no mínimo 8 caracteres,
  digitada duas vezes (cadastro, troca e recuperação).
- Q: Haverá recuperação de senha? → A: Sim, área de "esqueci minha senha"; o cliente informa a
  nova senha duas vezes.
- Q: Quem edita as travas e quem gere os clientes? → A: Admin, Supervisor ou Gerente com
  permissão, por uma área de gestão de clientes (saldo manual, excluir, editar, extrato e, no
  futuro, movimentação de apostas), tudo por permissão.
- Q: O telefone tem código do país? → A: Sim, o cliente informa o código do país, com padrão 55
  (Brasil).
- Q: O cliente nasce sempre com saldo zero? → A: Só quando não houver promoção. A antiga tabela
  de bônus passa a se chamar promoção (tabela `clientes_promocoes`) e já é criada nesta spec. O cliente tem três saldos
  separados: `saldo`, `saldo_promocao_esportes` e `saldo_promocao_cassino`.
- Q: Os nomes de colunas e tipos precisam ser iguais aos do sistema antigo? → A: Não; podem ser
  melhorados (por exemplo, boolean no lugar de enum Sim/Não), a critério do plano.
- Q: Haverá log de alteração de travas? → A: Não nesta spec; um sistema de logs geral será criado
  futuramente.
- Q: Nesta spec, o sistema já deve enviar as mensagens de WhatsApp de verdade ou só deixar o
  disparo preparado? → A: Só deixar preparado: esta spec dispara os eventos "conta criada" e
  "código de recuperação gerado"; uma spec própria de WhatsApp fará o envio. Até lá, a mensagem é
  apenas registrada no log da aplicação.
- Q: Com quais valores de travas um cliente novo é criado, e onde esses valores padrão ficam
  definidos? → A: Num registro único de travas padrão (`clientes_configuracoes_padrao`), criado
  pelo seeder com os valores da antiga `travas_gerentes` e editável pelo painel; cada cliente novo
  recebe uma cópia. As travas do cliente ficam na tabela `clientes_configuracoes`, e toda tabela
  ligada a uma tabela principal leva o nome dela como prefixo (ex.: `clientes_transacoes`,
  `clientes_promocoes`).
- Q: Qual deve ser o tamanho mínimo da senha do cliente? → A: Mínimo de 8 caracteres, com pelo
  menos uma letra e um número.
- Q: Na gestão de clientes, CPF e telefone aparecem completos ou mascarados? → A: Mascarados por
  padrão; completos só para quem tem a permissão `ver_dados_completos_clientes` (Admin recebe por
  padrão). A busca pelo valor exato funciona para todos.
- Q: No cadastro, o cliente recebe a promoção de cadastro automaticamente ou só se aceitar? → A:
  Só se aceitar: campo `aceita_promocao` no cadastro, marcado por padrão, que o cliente pode
  alterar depois em "meus dados".

## User Scenarios & Testing *(mandatory)*

> Conforme a constituição (v1.8.0), o projeto não terá testes automatizados. Os cenários
> abaixo são critérios de aceite validados manualmente.

### User Story 1 - Cadastro público do cliente (Priority: P1)

Um visitante da área externa do site cria a própria conta de cliente, sem passar pelo painel,
informando nome, código do país (padrão 55), telefone, senha (duas vezes), CPF, data de
nascimento, gênero e, opcionalmente, um código de indicação de afiliado. Ao concluir, a conta
fica ativa, com os saldos zerados ou com o valor da promoção de cadastro vigente, com as travas
padrão, e é disparada uma mensagem de WhatsApp de boas-vindas.

**Why this priority**: sem clientes cadastrados não existe apostador; é a base de todas as
outras stories.

**Independent Test**: validar manualmente cadastrando um cliente pela rota pública e conferindo,
pelo painel, que ele existe ativo, com saldos e travas corretos; repetir com uma promoção de
cadastro ativa e conferir o saldo promocional e a transação correspondente.

**Acceptance Scenarios**:

1. **Given** um visitante sem conta e nenhuma promoção de cadastro ativa, **When** ele envia
   dados válidos e é maior de 18 anos, **Then** a conta é criada ativa, com os três saldos em
   0,00 e travas padrão.
2. **Given** uma promoção de cadastro ativa de R$ 20,00 na modalidade esportes, **When** um
   cliente se cadastra, **Then** o `saldo_promocao_esportes` dele começa em 20,00, com uma
   transação de crédito de origem "promoção" que identifica a promoção.
3. **Given** um código do país e telefone já usados por outro cliente ativo ou inativo, **When**
   um novo cadastro usa esse telefone, **Then** o cadastro é recusado informando que o telefone
   já está cadastrado.
4. **Given** um CPF já usado por outro cliente ativo ou inativo, **When** um novo cadastro usa
   esse CPF, **Then** o cadastro é recusado informando que o CPF já está cadastrado.
5. **Given** uma data de nascimento de alguém com menos de 18 anos, **When** o cadastro é
   enviado, **Then** ele é recusado informando a idade mínima.
6. **Given** um CPF com dígitos verificadores inválidos, **When** o cadastro é enviado, **Then**
   ele é recusado como CPF inválido.
7. **Given** uma senha sem número, sem letra, com menos de 8 caracteres ou com a confirmação
   diferente, **When** o cadastro é enviado, **Then** ele é recusado informando a regra de senha.
8. **Given** o código do país não informado, **When** o cadastro é enviado, **Then** é usado o
   código 55.
9. **Given** um código de afiliado informado, **When** o cadastro é concluído, **Then** o código
   é guardado como informado, sem validação.
10. **Given** um cadastro concluído, **When** o disparo da mensagem de boas-vindas falha, **Then**
    a conta continua criada normalmente.
11. **Given** uma promoção de cadastro vigente, **When** um cliente se cadastra desmarcando
    `aceita_promocao`, **Then** a conta é criada com os saldos promocionais em 0,00 e sem
    transação de promoção.

---

### User Story 2 - Login do cliente com telefone e senha (Priority: P1)

O cliente informa código do país (padrão 55), telefone e senha e recebe um token de acesso de
cliente, usado nas rotas da área do cliente. Esse token não dá acesso ao painel, e o token do
painel não dá acesso à área do cliente.

**Why this priority**: sem autenticação o cliente não consulta saldo nem dados; é pré-requisito
para as stories de área do cliente.

**Independent Test**: fazer login com um cliente cadastrado, usar o token para consultar "meus
dados"; tentar usar o mesmo token numa rota do painel e conferir a recusa.

**Acceptance Scenarios**:

1. **Given** um cliente ativo, **When** ele informa telefone e senha corretos, **Then** recebe um
   token de acesso válido por 60 minutos.
2. **Given** telefone ou senha incorretos, **When** o cliente tenta entrar, **Then** o login é
   recusado com mensagem genérica, sem indicar qual dos dois está errado.
3. **Given** um cliente inativo ou excluído, **When** ele tenta entrar com a senha correta,
   **Then** o login é recusado.
4. **Given** um token de cliente, **When** ele é usado numa rota do painel, **Then** a requisição
   é recusada como não autenticada; e o inverso (token do painel em rota de cliente) também.
5. **Given** um token válido, **When** o cliente pede a renovação, **Then** recebe um novo token
   de 60 minutos sem informar a senha.
6. **Given** um token válido, **When** o cliente faz logout, **Then** esse token deixa de ser
   aceito imediatamente.
7. **Given** 5 tentativas erradas em 1 minuto para o mesmo telefone e IP, **When** uma nova
   tentativa é feita, **Then** ela é recusada por 1 minuto, informando o tempo de espera, mesmo
   com a senha correta.
8. **Given** um cliente logado que é desativado ou excluído pelo painel, **When** ele usa o token
   que já tinha, **Then** a requisição é recusada.

---

### User Story 3 - Recuperar senha ("esqueci minha senha") (Priority: P1)

O cliente que esqueceu a senha informa código do país e telefone, recebe um código de
verificação por WhatsApp e, com esse código, define uma nova senha digitada duas vezes.

**Why this priority**: sem recuperação, um cliente que esquece a senha perde o acesso ao próprio
saldo e depende do suporte.

**Independent Test**: pedir a recuperação para um cliente cadastrado, usar o código recebido
para definir uma nova senha e conferir que o login passa a funcionar só com a nova senha.

**Acceptance Scenarios**:

1. **Given** um cliente ativo, **When** ele pede a recuperação informando o telefone, **Then** é
   gerado um código de 6 dígitos, válido por 15 minutos, disparado para envio por WhatsApp
   (enquanto a spec de WhatsApp não existir, registrado no log da aplicação).
2. **Given** um telefone não cadastrado, **When** alguém pede a recuperação, **Then** a resposta é
   a mesma de um telefone cadastrado, sem revelar se ele existe, e nenhum código é enviado.
3. **Given** um código válido, **When** o cliente informa o código e a nova senha duas vezes,
   seguindo a regra de senha, **Then** a senha é trocada, o código deixa de valer e os tokens de
   acesso já emitidos para esse cliente deixam de ser aceitos.
4. **Given** um código expirado, já usado ou errado, **When** o cliente tenta trocar a senha,
   **Then** a troca é recusada.
5. **Given** 5 tentativas com código errado, **When** uma nova tentativa é feita, **Then** o
   código é invalidado e o cliente precisa pedir um novo.
6. **Given** um pedido de recuperação feito há menos de 1 minuto para o mesmo telefone, **When**
   um novo pedido é feito, **Then** ele é recusado informando o tempo de espera.

---

### User Story 4 - Movimentar saldos do cliente (crédito e débito) (Priority: P1)

Um usuário do painel com permissão lança um crédito ou débito manual num dos saldos de um cliente
(saldo, saldo promocional de esportes ou saldo promocional de cassino), informando o valor e o
motivo. Cada movimentação fica registrada com o saldo anterior e o saldo posterior daquele saldo.
A mesma operação fica disponível internamente para outras partes do sistema (como a futura spec
de apostas).

**Why this priority**: o saldo é o centro da relação com o apostador e precisa ser confiável
desde o início, pois envolve dinheiro.

**Independent Test**: creditar R$ 100,00 no saldo de um cliente, debitar R$ 30,00 e conferir o
saldo R$ 70,00 e duas transações com saldo anterior e posterior corretos; tentar debitar
R$ 100,00 e conferir a recusa sem alteração do saldo.

**Acceptance Scenarios**:

1. **Given** um cliente com saldo 0,00, **When** um usuário com permissão credita 100,00 no
   `saldo` com motivo, **Then** o saldo passa a 100,00 e é registrada uma transação de crédito
   com o saldo afetado, saldo anterior 0,00, saldo posterior 100,00, autor e motivo.
2. **Given** um cliente com saldo 100,00, **When** é debitado 30,00, **Then** o saldo passa a
   70,00 e a transação registra saldo anterior 100,00 e posterior 70,00.
3. **Given** um cliente com saldo 70,00, **When** é pedido um débito de 100,00, **Then** a
   operação é recusada por saldo insuficiente, sem criar transação e sem alterar o saldo.
4. **Given** um crédito no `saldo_promocao_cassino`, **When** ele é processado, **Then** só esse
   saldo muda; `saldo` e `saldo_promocao_esportes` ficam iguais.
5. **Given** duas movimentações simultâneas no mesmo saldo do mesmo cliente, **When** ambas são
   processadas, **Then** o saldo final é igual ao inicial somado às duas movimentações, e o saldo
   anterior de uma é o saldo posterior da outra.
6. **Given** um usuário do painel sem a permissão de movimentar saldo, **When** ele tenta lançar
   crédito ou débito, **Then** a operação é recusada por falta de permissão.
7. **Given** um valor zero, negativo ou com mais de 2 casas decimais, ou sem motivo, **When** a
   movimentação manual é enviada, **Then** ela é recusada como dado inválido.

---

### User Story 5 - Cliente consulta dados, saldos e extrato (Priority: P2)

O cliente logado consulta e edita os próprios dados permitidos, troca a senha, vê os três saldos
e o extrato das suas movimentações, filtrando por período e por saldo.

**Why this priority**: dá autonomia e transparência ao apostador, mas depende do cadastro, login
e movimentação de saldo.

**Independent Test**: com um cliente que tenha movimentações, consultar os saldos e o extrato
filtrado por período e conferir os valores; trocar a senha e entrar com a nova.

**Acceptance Scenarios**:

1. **Given** um cliente logado, **When** ele consulta "meus dados", **Then** vê seus dados
   cadastrais e os três saldos, sem a senha.
2. **Given** um cliente logado, **When** ele altera nome, gênero ou `aceita_promocao`, **Then** a
   alteração é salva.
3. **Given** um cliente logado, **When** ele tenta alterar código do país, telefone, CPF ou data
   de nascimento, **Then** a alteração é recusada, pois esses dados só mudam pelo painel.
4. **Given** um cliente logado, **When** ele troca a senha informando a senha atual correta e a
   nova duas vezes, seguindo a regra de senha, **Then** a nova senha passa a valer; com a senha
   atual errada, a troca é recusada.
5. **Given** um cliente com movimentações em vários dias, **When** ele consulta o extrato com
   data inicial e final, **Then** vê só as movimentações do período, da mais recente para a mais
   antiga, paginadas, cada uma com saldo afetado, tipo, origem, valor, saldo anterior, saldo
   posterior, observação e data.
6. **Given** um cliente logado, **When** ele consulta o extrato, **Then** vê apenas as próprias
   movimentações, nunca as de outro cliente.

---

### User Story 6 - Configurar travas do cliente pelo painel (Priority: P2)

Um Admin, Supervisor ou Gerente com permissão consulta e altera as travas de um cliente:
permissões de aposta, quantidade mínima e máxima de opções por aposta, valores mínimo e máximo
por aposta, prêmio máximo, valor máximo apostado por dia, odd mínima e máxima e esportes
permitidos.

**Why this priority**: as travas controlam o risco da banca por apostador; a aplicação delas
será feita na spec de apostas, mas o cadastro precisa existir antes.

**Independent Test**: alterar a aposta máxima de um cliente e conferir a nova trava na consulta;
tentar a mesma alteração com um Vendedor e conferir a recusa.

**Acceptance Scenarios**:

1. **Given** um cliente com travas padrão, **When** um Gerente com permissão altera o valor
   máximo por aposta, **Then** a nova trava é salva.
2. **Given** uma alteração com mínimo maior que o máximo (opções, valor por aposta ou odd),
   **When** ela é enviada, **Then** é recusada como dado inválido.
3. **Given** um cliente logado, **When** ele tenta alterar as próprias travas, **Then** não há
   rota para isso e a operação é recusada.
4. **Given** um usuário do painel sem a permissão de editar travas (incluindo qualquer Vendedor),
   **When** ele tenta alterá-las, **Then** a operação é recusada por falta de permissão.

---

### User Story 7 - Gerir clientes pelo painel (Priority: P2)

Um Admin, Supervisor ou Gerente com permissão acessa a gestão de clientes: busca e filtra
clientes, consulta os dados e o extrato, edita, ativa ou desativa e movimenta saldo. A exclusão e
a restauração ficam restritas a Admin e Supervisor com permissão.

**Why this priority**: o suporte e a gestão precisam atender e bloquear apostadores, mas o
sistema já entrega valor com cadastro, login e saldo.

**Independent Test**: buscar um cliente pelo CPF, desativá-lo e conferir que o login dele passa a
ser recusado; reativá-lo; excluí-lo e conferir que o telefone e o CPF ganharam o sufixo de
exclusão; restaurá-lo e conferir que voltam ao original.

**Acceptance Scenarios**:

1. **Given** vários clientes, **When** um usuário com permissão busca por nome, telefone ou CPF e
   aplica filtros (FR-052), **Then** vê só os clientes correspondentes, paginados, sem senha.
2. **Given** um Gerente com `ver_clientes` e sem `ver_dados_completos_clientes`, **When** ele
   lista ou consulta clientes, **Then** vê CPF e telefone mascarados; buscando pelo CPF completo,
   encontra o cliente; buscando por parte do CPF, não encontra.
3. **Given** um cliente ativo, **When** o usuário com permissão o desativa, **Then** o cliente
   não consegue mais entrar nem usar tokens que já tinha; ao reativar, volta a conseguir entrar.
4. **Given** um cliente com telefone `11988887777` e CPF `12345678909`, **When** um Admin o
   exclui, **Then** ele deixa de aparecer na listagem e de conseguir entrar, o telefone passa a
   `11988887777_deleted_<timestamp>` e o CPF a `12345678909_deleted_<timestamp>`, e os dados,
   saldos e transações são preservados.
5. **Given** um cliente excluído cujo telefone e CPF originais não estão em uso, **When** um
   Supervisor com permissão o restaura, **Then** o cliente volta com telefone e CPF originais.
6. **Given** um cliente excluído cujo telefone original já está em uso por outro cliente, **When**
   alguém tenta restaurá-lo sem informar um novo telefone, **Then** a restauração é recusada
   indicando o dado em conflito; informando um telefone novo e livre, a restauração é concluída
   com ele.
7. **Given** um Gerente, mesmo com qualquer permissão, **When** ele tenta excluir ou restaurar um
   cliente, **Then** a operação é recusada por falta de permissão.
8. **Given** um usuário do painel sem a permissão correspondente, **When** ele tenta a operação,
   **Then** ela é recusada por falta de permissão.
9. **Given** uma edição pelo painel que muda o telefone ou o CPF para um já usado por outro
   cliente, **When** ela é enviada, **Then** é recusada informando a duplicidade.

---

### User Story 8 - Cadastrar promoções (Priority: P3)

Um usuário do painel com permissão cadastra, edita, ativa, desativa e exclui promoções. Nesta
spec, a promoção usada é a de cadastro: ao se cadastrar, o cliente recebe o valor da promoção de
cadastro vigente no saldo promocional da modalidade correspondente (esportes ou cassino).

**Why this priority**: atrai novos apostadores, mas o cadastro funciona sem promoção (saldos
zerados).

**Independent Test**: cadastrar uma promoção de cadastro de R$ 20,00 para esportes, ativá-la,
cadastrar um cliente e conferir o saldo promocional; desativá-la e conferir que o próximo cliente
nasce com saldos zerados.

**Acceptance Scenarios**:

1. **Given** um usuário com permissão, **When** ele cadastra uma promoção com nome, modalidade,
   gatilho "cadastro", valor e período, **Then** a promoção é criada.
2. **Given** uma promoção de cadastro ativa e dentro do período para esportes, **When** outra
   promoção de cadastro para esportes com período sobreposto é ativada, **Then** a operação é
   recusada, pois só pode haver uma promoção de cadastro vigente por modalidade.
3. **Given** uma promoção fora do período ou inativa, **When** um cliente se cadastra, **Then**
   ela não é aplicada.
4. **Given** promoções de cadastro vigentes para esportes e para cassino, **When** um cliente se
   cadastra, **Then** ele recebe as duas, cada uma no seu saldo promocional, com uma transação
   para cada.

---

### Edge Cases

- Telefone é comparado apenas pelos dígitos, junto com o código do país: `(11) 98888-7777` e
  `11988887777` com código 55 são o mesmo telefone, tanto no cadastro quanto no login e na
  recuperação de senha.
- CPF é comparado apenas pelos dígitos (com ou sem pontuação é o mesmo CPF).
- Telefone e CPF de um cliente excluído ficam livres para novos cadastros, pois recebem o sufixo
  de exclusão.
- Cliente que faz 18 anos exatamente na data do cadastro é aceito.
- Débito exatamente igual ao saldo é aceito e deixa o saldo em 0,00.
- Desativar ou excluir um cliente com saldo diferente de zero é permitido; os saldos e as
  transações são preservados.
- Movimentação de saldo em cliente inexistente ou excluído é recusada com "não encontrado".
- Consulta de extrato com data inicial maior que a final é recusada como dado inválido.
- Restaurar um cliente que não está excluído é recusado.
- Recuperação de senha para cliente inativo ou excluído não gera código, com a mesma resposta
  genérica.
- Uma transação registrada nunca é editada nem excluída; correções são feitas por uma nova
  transação (por exemplo, estorno).
- A promoção de cadastro é aplicada uma única vez por cliente; restaurar um cliente excluído não
  aplica a promoção de novo.

## Requirements *(mandatory)*

### Functional Requirements

**Cadastro público**

- **FR-001**: O sistema DEVE permitir que um visitante se cadastre como cliente por uma rota
  pública, sem autenticação, separada do cadastro de usuários do painel.
- **FR-002**: Cada cliente DEVE possuir: nome, código do país, telefone, senha, CPF, data de
  nascimento, gênero, código de afiliado (opcional), `aceita_promocao`, indicação de ativo, `saldo`,
  `saldo_promocao_esportes`, `saldo_promocao_cassino` e as datas `created_at`, `updated_at` e
  `deleted_at`.
- **FR-003**: Nome, telefone, senha (com confirmação), CPF, data de nascimento e gênero DEVEM ser
  obrigatórios no cadastro; o código do país DEVE ser opcional, com padrão 55; o código de
  afiliado DEVE ser opcional; `aceita_promocao` (aceito receber promoções) DEVE ser opcional, com
  padrão marcado (sim).
- **FR-004**: O código do país DEVE ter de 1 a 3 dígitos. O telefone DEVE ser armazenado e
  comparado somente com dígitos; para o código 55 DEVE ter 10 ou 11 dígitos (DDD + número) e,
  para os demais, de 4 a 14 dígitos.
- **FR-005**: A combinação código do país + telefone DEVE ser única entre os clientes não
  excluídos.
- **FR-006**: O CPF DEVE ser único entre os clientes não excluídos, armazenado e comparado
  somente com dígitos, e DEVE ter dígitos verificadores válidos.
- **FR-007**: Só DEVEM ser aceitos cadastros de pessoas com 18 anos ou mais na data do cadastro.
- **FR-008**: O gênero DEVE ser um dos valores: masculino, feminino, outro ou não informado.
- **FR-009**: O código de afiliado, quando informado, DEVE ser guardado como texto, sem validação e
  sem ligação com outra tabela, pois a gestão de afiliados ainda não existe (será ligado em outra
  spec).
- **FR-010**: A senha do cliente DEVE ter no mínimo 8 caracteres, com pelo menos uma letra e um
  número, e DEVE ser informada duas vezes (senha e confirmação) no cadastro, na troca e na
  recuperação; a confirmação diferente DEVE recusar a operação. A senha DEVE ser armazenada de
  forma irreversível e NUNCA DEVE ser retornada em consultas ou listagens.
- **FR-011**: Todo novo cliente DEVE ser criado ativo, com os três saldos em 0,00, e em seguida
  receber as promoções de cadastro vigentes (FR-064), e com um registro de travas preenchido com
  os valores padrão (FR-042).
- **FR-012**: Clientes NÃO DEVEM ter vínculo com usuários do painel (gerentes, vendedores ou
  outros).
- **FR-013**: Após o cadastro, o sistema DEVE disparar o evento "conta criada", com os dados
  necessários para a mensagem de boas-vindas por WhatsApp (nome, código do país e telefone). O
  envio real por WhatsApp (provedor, conexão, textos e reenvio) NÃO faz parte desta spec e será
  feito por uma spec própria; até lá, a mensagem DEVE ser apenas registrada no log da aplicação.
  Falha no disparo NÃO DEVE impedir nem desfazer o cadastro.

**Autenticação do cliente**

- **FR-014**: O cliente DEVE se autenticar com código do país (padrão 55), telefone e senha e
  receber um token de acesso JWT válido por 60 minutos, emitido por um guard próprio de clientes.
- **FR-015**: Tokens de cliente NÃO DEVEM ser aceitos nas rotas do painel, e tokens do painel NÃO
  DEVEM ser aceitos nas rotas da área do cliente.
- **FR-016**: O login DEVE ser recusado com mensagem genérica para telefone ou senha incorretos, e
  recusado para clientes inativos ou excluídos.
- **FR-017**: O sistema DEVE disponibilizar uma verificação única de "cliente pode acessar", que
  retorna negativo para clientes inativos ou excluídos, usada no login, na renovação, na
  recuperação de senha e em toda requisição autenticada da área do cliente.
- **FR-018**: O sistema DEVE permitir renovar um token válido de cliente, gerando um novo token de
  60 minutos sem exigir a senha, recusando a renovação se o cliente não puder acessar (FR-017).
- **FR-019**: O sistema DEVE permitir o logout do cliente, invalidando o token usado
  imediatamente.
- **FR-020**: Após 5 tentativas de login erradas em 1 minuto para o mesmo telefone e IP, o
  sistema DEVE recusar novas tentativas desse par por 1 minuto, informando o tempo de espera.

**Recuperação de senha**

- **FR-021**: O sistema DEVE permitir que o cliente peça a recuperação de senha informando código
  do país (padrão 55) e telefone, por rota pública.
- **FR-022**: Para um cliente que pode acessar (FR-017), o sistema DEVE gerar um código numérico de
  6 dígitos, de uso único, válido por 15 minutos, e enviá-lo conforme FR-027; um novo pedido DEVE
  invalidar o código anterior.
- **FR-023**: A resposta ao pedido de recuperação DEVE ser a mesma para telefone cadastrado, não
  cadastrado, inativo ou excluído, sem revelar se o cliente existe.
- **FR-024**: O sistema DEVE recusar um novo pedido de recuperação para o mesmo telefone feito há
  menos de 1 minuto, informando o tempo de espera.
- **FR-025**: Com um código válido, o cliente DEVE poder definir uma nova senha, informada duas
  vezes e seguindo FR-010; após a troca, o código DEVE deixar de valer e todos os tokens de acesso
  já emitidos para o cliente DEVEM deixar de ser aceitos.
- **FR-026**: Após 5 tentativas com código errado, o código DEVE ser invalidado.
- **FR-027**: O envio do código DEVE seguir o mesmo mecanismo de FR-013: o sistema dispara o
  evento "código de recuperação gerado" (com código do país, telefone e código), e, até existir a
  spec de WhatsApp, a mensagem DEVE ser apenas registrada no log da aplicação.

**Área do cliente ("meus dados")**

- **FR-028**: O cliente autenticado DEVE poder consultar os próprios dados e os três saldos.
- **FR-029**: O cliente autenticado DEVE poder alterar somente nome, gênero e `aceita_promocao`;
  código do país,
  telefone, CPF e data de nascimento só DEVEM ser alterados pelo painel.
- **FR-030**: O cliente autenticado DEVE poder trocar a senha informando a senha atual e a nova
  duas vezes, seguindo FR-010.
- **FR-031**: O cliente autenticado DEVE poder consultar o próprio extrato, filtrando por data
  inicial e final e por saldo afetado, ordenado da transação mais recente para a mais antiga e
  paginado (padrão de 20 e máximo de 100 por página).

**Saldos e transações**

- **FR-032**: Cada cliente DEVE ter três saldos independentes: `saldo` (dinheiro real),
  `saldo_promocao_esportes` e `saldo_promocao_cassino`. Todos são valores monetários com 2 casas
  decimais e NUNCA DEVEM ficar negativos.
- **FR-033**: Um saldo SÓ DEVE ser alterado por meio de uma transação; toda alteração DEVE gerar
  exatamente um registro de transação, que afeta um único saldo.
- **FR-034**: Cada transação DEVE registrar: cliente, saldo afetado, tipo (crédito ou débito),
  origem (ajuste manual, promoção, aposta, prêmio, estorno), referência opcional ao registro de
  origem (por exemplo, a promoção ou, no futuro, a aposta), valor (maior que zero), saldo
  anterior, saldo posterior, autor da operação (sistema ou usuário do painel identificado),
  observação e data.
- **FR-035**: A referência ao registro de origem DEVE ser guardada sem ligação obrigatória com
  outra tabela, pois origens como apostas ainda não existem (serão ligadas em outra spec).
- **FR-036**: O saldo posterior de uma transação DEVE ser o saldo anterior somado ao valor
  (crédito) ou subtraído do valor (débito), e DEVE ser igual ao valor atual do saldo afetado logo
  após a operação.
- **FR-037**: Um débito maior que o saldo afetado DEVE ser recusado, sem criar transação nem
  alterar o saldo.
- **FR-038**: Movimentações simultâneas no mesmo cliente DEVEM ser processadas uma de cada vez,
  de forma que nenhuma se perca e os saldos nunca fiquem inconsistentes com as transações.
- **FR-039**: Transações NÃO DEVEM ser editadas nem excluídas; correções DEVEM ser feitas por
  novas transações.
- **FR-040**: A operação de crédito e débito DEVE ser única e reutilizável por outras partes do
  sistema (como a futura spec de apostas), aplicando sempre FR-032 a FR-039.
- **FR-041**: Usuários do painel com a permissão `movimentar_saldo_clientes` DEVEM poder lançar
  crédito ou débito manual (origem "ajuste manual") em qualquer um dos três saldos de um cliente,
  informando valor e motivo (obrigatório, gravado como observação).

**Travas do cliente**

- **FR-042**: Cada cliente DEVE ter exatamente um registro de travas (tabela
  `clientes_configuracoes`), com:
  - permissões (liberado/bloqueado): realizar aposta, apostar ao vivo, apostar em outros
    esportes, cancelar aposta;
  - quantidade mínima e máxima de opções (palpites) por aposta;
  - valor mínimo e máximo por aposta;
  - prêmio máximo;
  - valor máximo apostado por dia;
  - odd mínima e odd máxima;
  - esportes permitidos (lista).
- **FR-043**: O sistema DEVE ter um registro único de travas padrão (tabela
  `clientes_configuracoes_padrao`), com os mesmos campos de FR-042. No cadastro, cada cliente novo
  DEVE receber uma cópia desses valores em `clientes_configuracoes`; alterar o padrão depois NÃO
  DEVE mudar as travas de clientes já cadastrados.
- **FR-043a**: O seeder DEVE criar o registro de travas padrão com os valores da antiga
  `travas_gerentes` (`database.sql`):
  - realizar aposta: liberado; apostar ao vivo: liberado; apostar em outros esportes: liberado;
    cancelar aposta: bloqueado;
  - opções por aposta: mínimo 1 e máximo 20;
  - valor por aposta: mínimo R$ 2,00 e máximo R$ 1.000,00;
  - prêmio máximo: R$ 50.000,00;
  - valor máximo apostado por dia: R$ 5.000,00 (antigo `limite_geral` de `travas_vendedors`, pois
    `travas_gerentes` não tem esse campo);
  - odd mínima 1,90 e odd máxima 30,00;
  - esportes permitidos: FUTEBOL, HOQUEI NO GELO e BAISEBOL (grafados como no sistema antigo).
- **FR-043b**: Somente Admin e Supervisor com a permissão `editar_configuracoes_padrao_clientes`
  DEVEM poder consultar e alterar as travas padrão, seguindo as regras de coerência de FR-044.
- **FR-044**: Os limites DEVEM ser coerentes: quantidade mínima de opções ≥ 1 e ≤ quantidade
  máxima; valor mínimo por aposta > 0 e ≤ valor máximo; odd mínima ≥ 1,00 e ≤ odd máxima; prêmio
  máximo e valor máximo por dia > 0. Alterações incoerentes DEVEM ser recusadas.
- **FR-045**: O cliente NÃO DEVE poder alterar as próprias travas.
- **FR-046**: Usuários do painel com a permissão `ver_clientes` DEVEM poder consultar as travas de
  um cliente, e somente Admin, Supervisor ou Gerente com a permissão `editar_travas_clientes` DEVEM
  poder alterá-las.
- **FR-047**: Esta spec só armazena e gerencia as travas; a aplicação delas nas apostas NÃO faz
  parte desta spec.

**Gestão de clientes no painel**

- **FR-048**: A gestão de clientes DEVE ser acessível somente a Admin, Supervisor e Gerente, com o
  token do painel e as permissões correspondentes:
  - `ver_clientes`: listar, consultar, ver extrato e travas;
  - `ver_dados_completos_clientes`: ver CPF e telefone completos (FR-052a);
  - `editar_clientes`: editar, ativar e desativar;
  - `editar_travas_clientes`: alterar travas;
  - `movimentar_saldo_clientes`: crédito e débito manual;
  - `excluir_clientes`: excluir (somente Admin e Supervisor);
  - `restaurar_clientes`: listar excluídos e restaurar (somente Admin e Supervisor).
- **FR-049**: Vendedores NÃO DEVEM ter acesso à gestão de clientes, e Gerentes NÃO DEVEM poder
  excluir nem restaurar clientes, mesmo que recebam a permissão.
- **FR-050**: Os clientes não pertencem a nenhum usuário do painel; quem tem a permissão
  correspondente enxerga e gerencia todos os clientes (sem recorte por hierarquia).
- **FR-051**: Usuários com `ver_clientes` DEVEM poder consultar o extrato de qualquer cliente, com
  os mesmos filtros e paginação de FR-031.
- **FR-052**: A listagem de clientes DEVE ser paginada (padrão de 20 e máximo de 100 por página),
  NÃO DEVE incluir clientes excluídos (salvo pelo filtro de FR-057) e DEVE oferecer:
  - busca por parte do nome, parte do telefone ou CPF (com ou sem pontuação);
  - filtros por: ativo/inativo, código do país, código de afiliado, período de cadastro, faixa
    de idade (pela data de nascimento), gênero, faixa de `saldo` e clientes com saldo
    promocional (esportes ou cassino) maior que zero;
  - ordenação por nome, data de cadastro ou `saldo`, crescente ou decrescente.
- **FR-052a**: Em todas as respostas da gestão de clientes (listagem, consulta, excluídos), CPF e
  telefone DEVEM vir mascarados para quem não tem a permissão `ver_dados_completos_clientes`: CPF
  no formato `***.456.789-**` e telefone com apenas os 4 últimos dígitos visíveis (ex.:
  `(11) *****-7777`). Sem essa permissão, a busca por CPF e por telefone DEVE aceitar somente o
  valor completo (busca exata), sem busca por parte do número.
- **FR-053**: Pelo painel DEVE ser possível editar nome, código do país, telefone, CPF, data de
  nascimento, gênero, código de afiliado e senha do cliente, respeitando FR-004 a FR-010; campos
  não informados (especialmente a senha) DEVEM permanecer inalterados. Os saldos NÃO DEVEM ser
  alterados pela edição (só por FR-041).
- **FR-054**: Pelo painel DEVE ser possível ativar e desativar um cliente; o cliente desativado
  perde o acesso imediatamente, inclusive com tokens já emitidos (FR-017).
- **FR-055**: A exclusão de clientes DEVE ser lógica (preenchendo `deleted_at`) e DEVE acrescentar
  o sufixo `_deleted_<timestamp>` a todos os dados únicos do cliente (telefone e CPF), preservando
  o valor original antes do sufixo, os saldos e as transações. O cliente excluído perde o acesso
  imediatamente.
- **FR-056**: A restauração DEVE remover o sufixo, devolvendo os dados únicos originais, e limpar
  `deleted_at`. Antes, o sistema DEVE verificar se algum dado único original já está em uso por
  outro cliente não excluído; se estiver, a restauração DEVE ser recusada indicando os dados em
  conflito, e só DEVE ser concluída se forem informados novos valores válidos e livres para esses
  dados na própria restauração.
- **FR-057**: Usuários com `restaurar_clientes` DEVEM poder listar os clientes excluídos, com a
  mesma busca e paginação de FR-052 (a busca por telefone e CPF considera o valor original).
- **FR-058**: As novas permissões (`ver_clientes`, `editar_clientes`, `excluir_clientes`,
  `restaurar_clientes`, `editar_travas_clientes`, `movimentar_saldo_clientes`,
  `editar_configuracoes_padrao_clientes`, `ver_dados_completos_clientes` e
  `gerenciar_promocoes`) DEVEM ser criadas no `spatie/laravel-permission` pelo seeder e seguir as
  regras de atribuição da spec 001 (permissões diretas no usuário; um gestor só dá a subordinados
  permissões que ele mesmo tem), respeitando também as restrições por função de FR-048 e FR-049.
- **FR-059**: Operações recusadas DEVEM retornar mensagens claras em português indicando o motivo
  (dado inválido, duplicidade, conflito na restauração, saldo insuficiente, sem permissão, não
  encontrado, não autenticado).

**Promoções**

- **FR-060**: O sistema DEVE ter um cadastro de promoções (tabela `clientes_promocoes`, substitui
  a antiga de bônus), com: nome, descrição, modalidade (esportes ou cassino), gatilho (nesta spec, somente
  "cadastro"), valor, data de início, data de fim (opcional), indicação de ativa e as datas
  `created_at`, `updated_at` e `deleted_at`.
- **FR-061**: Usuários do painel com a permissão `gerenciar_promocoes` DEVEM poder listar
  (paginado), consultar, cadastrar, editar, ativar, desativar e excluir logicamente promoções.
- **FR-062**: Uma promoção está vigente quando está ativa, não excluída e a data atual está entre
  a data de início e a data de fim (ou sem data de fim).
- **FR-063**: Só DEVE existir no máximo uma promoção de cadastro vigente por modalidade; cadastrar,
  editar ou ativar uma promoção que cause sobreposição DEVE ser recusado.
- **FR-064**: No cadastro de um cliente com `aceita_promocao` marcado, para cada promoção de
  cadastro vigente, o sistema DEVE
  creditar o valor dela no saldo promocional da modalidade (esportes → `saldo_promocao_esportes`,
  cassino → `saldo_promocao_cassino`) por uma transação de origem "promoção" que referencia a
  promoção (FR-034).
- **FR-065**: A promoção de cadastro DEVE ser aplicada uma única vez por cliente, somente no
  momento do cadastro; marcar `aceita_promocao` depois NÃO DEVE aplicar a promoção de cadastro
  retroativamente, e desmarcar depois NÃO DEVE retirar saldo promocional já recebido.
- **FR-066**: Regras de uso do saldo promocional (rollover, conversão em saldo real, expiração)
  NÃO fazem parte desta spec.

### Key Entities

- **Cliente**: apostador do sistema (tabela `clientes`, substitui a antiga `creditos`).
  Atributos: nome, código do país, telefone (login), senha, CPF, data de nascimento, gênero,
  código de afiliado (texto, sem ligação), `aceita_promocao`, ativo, `saldo`,
  `saldo_promocao_esportes`,
  `saldo_promocao_cassino`, `created_at`, `updated_at` e `deleted_at`. Não pertence a nenhum
  usuário do painel.
- **Transação do cliente** (tabela `clientes_transacoes`): registro imutável de cada movimentação de um dos saldos. Atributos:
  cliente, saldo afetado, tipo (crédito/débito), origem, referência de origem (sem ligação
  obrigatória), valor, saldo anterior, saldo posterior, autor (sistema ou usuário do painel),
  observação e data. A sequência de transações de cada saldo explica o valor atual dele.
- **Travas do cliente** (tabela `clientes_configuracoes`): configuração de limites e permissões de
  aposta de um cliente (uma por cliente), criada no cadastro como cópia das travas padrão e
  alterada só pelo painel.
- **Travas padrão** (tabela `clientes_configuracoes_padrao`): registro único com os valores que
  cada cliente novo recebe; criado pelo seeder e editável pelo painel.
- **Promoção** (tabela `clientes_promocoes`): benefício concedido ao cliente em saldo promocional de uma
  modalidade (esportes ou cassino) quando ocorre um gatilho (nesta spec, o cadastro).
- **Código de recuperação de senha** (tabela `clientes_codigos_recuperacao`): código temporário de 6 dígitos, de uso único, ligado a um
  cliente, com validade, contagem de tentativas e situação (válido, usado ou invalidado).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Um visitante conclui o cadastro e o primeiro login em menos de 2 minutos.
- **SC-002**: Em 100% dos clientes, cada um dos três saldos é igual ao saldo posterior da sua
  última transação (ou 0,00 sem transações), mesmo após movimentações simultâneas.
- **SC-003**: Em 100% das tentativas, nenhum saldo fica negativo.
- **SC-004**: Em 100% das tentativas, um token de cliente não acessa rotas do painel e um token do
  painel não acessa rotas da área do cliente.
- **SC-005**: Em 100% das tentativas, clientes inativos ou excluídos não conseguem entrar nem usar
  tokens já emitidos.
- **SC-006**: Um cliente que esqueceu a senha recupera o acesso sozinho em menos de 3 minutos.
- **SC-007**: Em 100% das restaurações, nenhum cliente volta com telefone ou CPF duplicado.
- **SC-008**: Nenhuma consulta ou listagem expõe a senha de clientes.
- **SC-009**: A listagem e a busca de clientes com até 100.000 cadastros e o extrato de um cliente
  com até 10.000 transações são exibidos em menos de 2 segundos.

## Assumptions

- O envio real de mensagens por WhatsApp fica numa spec própria; nesta spec, boas-vindas e código
  de recuperação são disparados como eventos e registrados no log da aplicação, o que permite
  validar a recuperação de senha manualmente.
- Telas (frontend) estão fora do escopo; esta spec cobre somente o backend que a futura área de
  gestão de clientes e a área do cliente vão usar.
- "Movimentação de apostas" na gestão de clientes depende da spec de apostas e será acrescentada
  por ela.
- Permissões padrão por função: o Admin recebe todas as permissões de clientes e de promoções;
  Supervisor, Gerente e Vendedor não recebem nenhuma por padrão e podem recebê-las pela gestão de
  permissões da spec 001, dentro das restrições por função de FR-048 e FR-049.
- Os nomes de colunas e os tipos (por exemplo, boolean no lugar de enum Sim/Não) não precisam
  seguir o sistema antigo e serão definidos no plano.
- Colunas que dependem de tabelas ainda inexistentes (código de afiliado, referência de origem das
  transações, esportes permitidos) ficam sem chave estrangeira e serão ligadas em outras specs.
- A lista de esportes permitidos é guardada como lista de nomes de esporte, pois ainda não existe
  cadastro de esportes no sistema.
- O timestamp do sufixo de exclusão é o momento da exclusão em segundos (Unix).
- Valores monetários são em reais (BRL) com 2 casas decimais.
- O "valor máximo apostado por dia" considera o dia do calendário no fuso horário do sistema; a
  contagem será aplicada pela spec de apostas.
- A tabela antiga `creditos` não é migrada; os dados do sistema anterior não são importados nesta
  spec.
- Logs de alteração (travas, cadastro e outros) ficam fora do escopo; um sistema de logs geral
  será criado em spec própria.
- Depósito via PIX/gateway, saque, rollover, cashback, cassino, apostas/bilhetes e gestão de
  afiliados estão fora do escopo.
