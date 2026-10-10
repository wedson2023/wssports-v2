# Feature Specification: Clientes (Apostadores)

**Feature Branch**: `002-clientes`

**Created**: 2026-09-29

**Status**: Draft

**Input**: User description: "Spec: clientes. Clientes são os apostadores do sistema, separados
dos usuários do painel e sem vínculo com gerentes ou vendedores; vínculo opcional com afiliado
por código de indicação. Tabela `clientes` (substitui a antiga `creditos`). Cadastro público,
login com telefone e senha por JWT em guard próprio, saldo com transações de crédito e débito
(saldo anterior e posterior), configurações específicas de cada cliente criadas do zero, gestão de
clientes pelo painel com novas permissões do spatie. Fora do escopo: apostas/bilhetes, gestão
de afiliados, depósito via PIX/gateway e saque, bônus, rollover, cashback e cassino."

> O pedido original acima foi ampliado nas sessões de Clarifications (promoções, meios de
> pagamento e estorno de promoção entraram no escopo). Em caso de divergência, valem as
> Clarifications e os requisitos.

## Clarifications

### Session 2026-09-29

- Q: Como fica o vínculo com afiliado, se a tabela de afiliados ainda não existe? → A: Colunas
  que dependem de tabelas ainda não criadas ficam sem ligação (texto/varchar, sem chave
  estrangeira) e serão ajustadas em outra spec; o código de afiliado é guardado como texto, sem
  validação.
- Q: Haverá envio de WhatsApp? → A: Sim, existirá um disparo de WhatsApp na criação da conta.
- Q: O que acontece com os dados únicos de um cliente excluído? → A: Telefone, CPF, e-mail e
  qualquer outro dado único recebem o sufixo `_deleted_<timestamp>` (ex.:
  `11988887777_deleted_1759150000`), liberando o valor para novos cadastros. Excluir e restaurar
  só por Admin ou Supervisor com permissão; na restauração, se algum dado único já estiver em uso,
  ele precisa ser alterado para o cliente voltar.
- Q: Qual a regra de senha? → A: Senha forte, com letras e números e no mínimo 8 caracteres,
  digitada duas vezes (cadastro, troca e recuperação).
- Q: Haverá recuperação de senha? → A: Sim, área de "esqueci minha senha"; o cliente informa a
  nova senha duas vezes.
- Q: Quem edita as configurações e quem gere os clientes? → A: Admin, Supervisor ou Gerente com
  permissão, por uma área de gestão de clientes (saldo manual, excluir, editar, extrato e, no
  futuro, movimentação de apostas), tudo por permissão.
- Q: O telefone tem código do país? → A: Sim, o DDI, com padrão 55 (Brasil). A coluna se chama
  `ddi` (sigla consagrada, constituição v1.13.0).
- Q: O cliente nasce sempre com saldo zero? → A: Só quando não houver promoção. A antiga tabela
  de bônus passa a se chamar promoção (tabela `clientes_promocoes`) e já é criada nesta spec. O
  cliente tem três saldos separados: `saldo`, `saldo_promocao_esportes` e `saldo_promocao_cassino`.
- Q: Os nomes de colunas e tipos precisam ser iguais aos do sistema antigo? → A: Não; podem ser
  melhorados (por exemplo, boolean no lugar de enum Sim/Não, nomes completos no lugar de
  abreviações como `v_`), a critério da spec e do plano.
- Q: Haverá log de alteração de configurações? → A: Não nesta spec; um sistema de logs geral será
  criado futuramente.
- Q: Nesta spec, o sistema já deve enviar as mensagens de WhatsApp de verdade ou só deixar o
  disparo preparado? → A: Só deixar preparado: esta spec dispara os eventos "conta criada" e
  "código de recuperação gerado"; uma spec própria de WhatsApp fará o envio. Até lá, a mensagem é
  apenas registrada no log da aplicação.
- Q: Com quais valores de configurações um cliente novo é criado, e onde esses valores padrão
  ficam definidos? → A: Num registro único de configurações padrão
  (`clientes_configuracoes_padrao`), criado pelo seeder com os valores da antiga `travas_gerentes`
  e editável pelo painel; cada cliente novo recebe uma cópia. As configurações do cliente ficam na
  tabela `clientes_configuracoes`, e toda tabela ligada a uma tabela principal leva o nome dela
  como prefixo. (Revisto em 2026-10-01 pela spec 003: não existe mais tabela padrão; os valores
  iniciais ficam no padrão das colunas de `clientes_configuracoes`.)
- Q: Qual deve ser o tamanho mínimo da senha do cliente? → A: Mínimo de 8 caracteres, com pelo
  menos uma letra e um número.
- Q: Na gestão de clientes, dados pessoais aparecem completos ou mascarados? → A: Mascarados por
  padrão (CPF, telefone, e-mail e dados de pagamento); completos só para quem tem a permissão
  `clientes.ver_dados_completos` (Admin recebe por padrão). A busca pelo valor exato funciona para
  todos.
- Q: No cadastro, o cliente recebe a promoção de primeiro cadastro automaticamente ou só se
  aceitar? → A: Só se aceitar: `aceita_promocao`, marcado por padrão. O campo fica nas
  configurações do cliente, e o cliente pode alterá-lo depois em "meus dados".
- Q: Qual o formato dos nomes das permissões? → A: `<recurso>.<acao>`, como na spec 001
  (`usuarios.listar`): `clientes.listar`, `clientes.editar`, `clientes_promocoes.gerenciar` etc.
- Q: As travas e as configurações são a mesma coisa? → A: Sim; usa-se a nomenclatura das tabelas
  (`clientes_configuracoes`), inclusive na permissão `clientes.editar_configuracoes`.
- Q: Quando promoções podem ter períodos sobrepostos? → A: Só quando são de categorias
  diferentes (Primeiro cadastro, Primeiro depósito, Qualquer depósito, Indicação). Na mesma
  categoria e modalidade não há sobreposição; a modalidade entra na regra para permitir bônus de
  primeiro cadastro em esportes e em cassino ao mesmo tempo.
- Q: O cadastro já devolve o token de acesso? → A: Sim, mantido.
- Q: E-mail e CPF são obrigatórios? → A: Não. O e-mail é opcional; o CPF deixou de ser
  obrigatório. Quando informados, são únicos e validados.
- Q: Como ficam os valores de enum? → A: Casos em `PascalCase` com acentos (`Promoção`,
  `Estorno`) e valores gravados em português, com a primeira letra maiúscula e acentos
  (`'Promoção'`, `'Primeiro depósito'`), conforme a constituição v1.13.0.
- Q: O que muda nas configurações do cliente? → A: Entram `aceita_promocao`, `bloquear_saque` e
  dois limites de saque (um em reais e outro em quantidade), também nas configurações padrão.
- Q: Onde ficam os dados de pagamento do cliente? → A: Na tabela `clientes_meios_pagamento`; o
  cliente pode ter um ou vários meios, do tipo Pix (nome, tipo e chave) ou Transferência bancária
  (dados principais do banco).
- Q: O que muda nas promoções? → A: Entram `tipo_ganho` (Fixo ou Percentual), `rollover`
  (quantas vezes o bônus precisa ser apostado antes de poder ser recuperado), obrigatório nas
  promoções de primeiro depósito, e as regras de uso: valor mínimo e máximo de aposta, valor
  máximo de depósito, valor máximo de conversão do bônus, odd mínima de aposta simples e odd
  mínima de aposta múltipla. Também entra um mecanismo para estornar (rollback) a promoção de
  todos os clientes que a receberam.
- Q: Onde ficam as permissões de clientes e quem as recebe por padrão? → A: No mesmo lugar e
  padrão das permissões de usuários (`Funcao`), com Admin e Supervisor recebendo todas e o Gerente
  as que pode usar (constituição v1.14.0, Princípio VI).
- Q: Como ficam as URLs? → A: Caminhos de rota e prefixos em kebab-case (ex.:
  `/area-cliente/meus-dados`); parâmetros de query string e chaves JSON continuam em snake_case
  (ex.: `?por_pagina=20`) (constituição v1.16.0).
- Q: As regras de uso das promoções e dos saques são aplicadas nesta spec? → A: Não. Esta spec
  cria a estrutura, o cadastro e a validação dessas regras e o estorno de promoções; a aplicação
  do rollover, da conversão do bônus, dos limites de depósito e dos limites de saque fica para as
  specs de apostas, depósitos e saques, onde esses eventos acontecem.
- Q: O que significa a regra `v_converter_bonus`? → A: É o valor máximo, em reais, que o bônus
  pode virar saldo real depois de cumprido o rollover; o que passar disso é descartado.
- Q: O "nome da chave Pix" é o titular ou um apelido? → A: É o nome do titular da chave, como
  aparece no banco, obrigatório.
- Q: Os limites de saque valem por qual período? → A: Por dia do calendário, os dois (valor em
  reais e quantidade de saques).

- Q: No estorno, o que acontece quando o saldo promocional não cobre o valor recebido? → A: Retira
  só do saldo promocional da modalidade: o valor recebido ou, se não houver tudo isso, zera o
  saldo promocional. Nada fica pendente e o saldo real nunca é usado.

### Session 2026-10-01 (alteração feita pela spec 003-confrontos)

- Q: Existe tabela padrão de configurações? → A: Não, para nenhum público (decisão do responsável
  na spec 003). A tabela `clientes_configuracoes_padrao`, as rotas `clientes-configuracoes-padrao`
  e a permissão `clientes.editar_configuracoes_padrao` foram removidas. O cliente novo nasce com os
  valores padrão das colunas de `clientes_configuracoes` (os mesmos de FR-050).

## User Scenarios & Testing *(mandatory)*

> Conforme a constituição, o projeto não terá testes automatizados. Os cenários
> abaixo são critérios de aceite validados manualmente.

### User Story 1 - Cadastro público do cliente (Priority: P1)

Um visitante da área externa do site cria a própria conta de cliente, sem passar pelo painel,
informando nome, DDI (padrão 55), telefone, senha (duas vezes), data de nascimento, gênero e,
opcionalmente, e-mail, CPF, código de indicação de afiliado e se aceita receber promoções. Ao
concluir, a conta fica ativa, com os saldos zerados ou com o valor da promoção de primeiro cadastro
vigente, com as configurações padrão, e é disparada uma mensagem de WhatsApp de boas-vindas.

**Why this priority**: sem clientes cadastrados não existe apostador; é a base de todas as
outras stories.

**Independent Test**: validar manualmente cadastrando um cliente pela rota pública e conferindo,
pelo painel, que ele existe ativo, com saldos e configurações corretos; repetir com uma promoção de
primeiro cadastro ativa e conferir o saldo promocional e a transação correspondente.

**Acceptance Scenarios**:

1. **Given** um visitante sem conta e nenhuma promoção de primeiro cadastro ativa, **When** ele
   envia dados válidos e é maior de 18 anos, **Then** a conta é criada ativa, com os três saldos
   em 0,00 e configurações padrão, e ele recebe um token de acesso.
2. **Given** uma promoção de primeiro cadastro ativa de R$ 20,00 na modalidade Esportes, **When**
   um cliente se cadastra, **Then** o `saldo_promocao_esportes` dele começa em 20,00, com uma
   transação de crédito de origem "Promoção" que identifica a promoção.
3. **Given** um DDI e telefone já usados por outro cliente ativo ou inativo, **When** um novo
   cadastro usa esse telefone, **Then** o cadastro é recusado informando que o telefone já está
   cadastrado.
4. **Given** um CPF ou e-mail já usado por outro cliente ativo ou inativo, **When** um novo
   cadastro usa esse dado, **Then** o cadastro é recusado informando a duplicidade.
5. **Given** um cadastro sem CPF e sem e-mail, **When** ele é enviado com os demais dados
   válidos, **Then** a conta é criada normalmente.
6. **Given** uma data de nascimento de alguém com menos de 18 anos, **When** o cadastro é
   enviado, **Then** ele é recusado informando a idade mínima.
7. **Given** um CPF com dígitos verificadores inválidos ou um e-mail em formato inválido, **When**
   o cadastro é enviado, **Then** ele é recusado informando o dado inválido.
8. **Given** uma senha sem número, sem letra, com menos de 8 caracteres ou com a confirmação
   diferente, **When** o cadastro é enviado, **Then** ele é recusado informando a regra de senha.
9. **Given** o DDI não informado, **When** o cadastro é enviado, **Then** é usado o DDI 55.
10. **Given** um código de afiliado informado, **When** o cadastro é concluído, **Then** o código
    é guardado como informado, sem validação.
11. **Given** um cadastro concluído, **When** o disparo da mensagem de boas-vindas falha,
    **Then** a conta continua criada normalmente.
12. **Given** uma promoção de primeiro cadastro vigente, **When** um cliente se cadastra
    desmarcando `aceita_promocao`, **Then** a conta é criada com os saldos promocionais em 0,00,
    sem transação de promoção, e com `aceita_promocao` desmarcado nas configurações.

---

### User Story 2 - Login do cliente com telefone e senha (Priority: P1)

O cliente informa DDI (padrão 55), telefone e senha e recebe um token de acesso de cliente, usado
nas rotas da área do cliente. Esse token não dá acesso ao painel, e o token do painel não dá acesso
à área do cliente.

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

O cliente que esqueceu a senha informa DDI e telefone, recebe um código de verificação por
WhatsApp e, com esse código, define uma nova senha digitada duas vezes.

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
   cadastrais, os três saldos e se aceita promoções, sem a senha.
2. **Given** um cliente logado, **When** ele altera nome, gênero, e-mail ou `aceita_promocao`,
   **Then** a alteração é salva (o e-mail continua único).
3. **Given** um cliente logado, **When** ele tenta alterar DDI, telefone, CPF ou data de
   nascimento, **Then** a alteração é recusada, pois esses dados só mudam pelo painel.
4. **Given** um cliente logado, **When** ele troca a senha informando a senha atual correta e a
   nova duas vezes, seguindo a regra de senha, **Then** a nova senha passa a valer e o token
   atual deixa de ser aceito; com a senha atual errada, a troca é recusada.
5. **Given** um cliente com movimentações em vários dias, **When** ele consulta o extrato com
   data inicial e final, **Then** vê só as movimentações do período, da mais recente para a mais
   antiga, paginadas, cada uma com saldo afetado, tipo, origem, valor, saldo anterior, saldo
   posterior, observação e data.
6. **Given** um cliente logado, **When** ele consulta o extrato, **Then** vê apenas as próprias
   movimentações, nunca as de outro cliente.

---

### User Story 6 - Configurar as configurações de aposta e saque do cliente pelo painel (Priority: P2)

Um Admin, Supervisor ou Gerente com permissão consulta e altera as configurações de um cliente:
permissões de aposta, bloqueio de saque, quantidade mínima e máxima de opções por aposta, valores
mínimo e máximo por aposta, prêmio máximo, valor máximo apostado por dia, limites de saque (em
reais e em quantidade), odd mínima e máxima, esportes permitidos e se aceita promoções.

**Why this priority**: as configurações controlam o risco da banca por apostador; a aplicação delas
será feita nas specs de apostas e saques, mas o cadastro precisa existir antes.

**Independent Test**: alterar a aposta máxima e bloquear o saque de um cliente e conferir as novas
configurações na consulta; tentar a mesma alteração com um Vendedor e conferir a recusa.

**Acceptance Scenarios**:

1. **Given** um cliente com configurações padrão, **When** um Gerente com permissão altera o valor
   máximo por aposta e marca `bloquear_saque`, **Then** as novas configurações são salvas.
2. **Given** uma alteração com mínimo maior que o máximo (opções, valor por aposta ou odd) ou com
   limite de saque negativo, **When** ela é enviada, **Then** é recusada como dado inválido.
3. **Given** um cliente logado, **When** ele tenta alterar as próprias configurações (exceto
   `aceita_promocao`, que ele altera em "meus dados"), **Then** não há rota para isso e a operação
   é recusada.
4. **Given** um usuário do painel sem a permissão de editar configurações (incluindo qualquer
   Vendedor), **When** ele tenta alterá-las, **Then** a operação é recusada por falta de
   permissão.

---

### User Story 7 - Gerir clientes pelo painel (Priority: P2)

Um Admin, Supervisor ou Gerente com permissão acessa a gestão de clientes: busca e filtra
clientes, consulta os dados, os meios de pagamento e o extrato, edita, ativa ou desativa e
movimenta saldo. A exclusão e a restauração ficam restritas a Admin e Supervisor com permissão.

**Why this priority**: o suporte e a gestão precisam atender e bloquear apostadores, mas o
sistema já entrega valor com cadastro, login e saldo.

**Independent Test**: buscar um cliente pelo CPF, desativá-lo e conferir que o login dele passa a
ser recusado; reativá-lo; excluí-lo e conferir que telefone, CPF e e-mail ganharam o sufixo de
exclusão; restaurá-lo e conferir que voltam ao original.

**Acceptance Scenarios**:

1. **Given** vários clientes, **When** um usuário com permissão busca por nome, telefone, CPF ou
   e-mail e aplica filtros (FR-056), **Then** vê só os clientes correspondentes, paginados, sem
   senha.
2. **Given** um Gerente com `clientes.listar` e sem `clientes.ver_dados_completos`, **When** ele
   lista ou consulta clientes, **Then** vê CPF, telefone, e-mail e dados de pagamento mascarados;
   buscando pelo CPF completo, encontra o cliente; buscando por parte do CPF, não encontra.
3. **Given** um cliente ativo, **When** o usuário com permissão o desativa, **Then** o cliente
   não consegue mais entrar nem usar tokens que já tinha; ao reativar, volta a conseguir entrar.
4. **Given** um cliente com telefone `11988887777`, CPF `12345678909` e e-mail `ana@mail.com`,
   **When** um Admin o exclui, **Then** ele deixa de aparecer na listagem e de conseguir entrar,
   os três dados passam a ter o sufixo `_deleted_<timestamp>`, e os dados, saldos e transações são
   preservados.
5. **Given** um cliente excluído cujos dados únicos originais não estão em uso, **When** um
   Supervisor com permissão o restaura, **Then** o cliente volta com os dados originais.
6. **Given** um cliente excluído cujo telefone original já está em uso por outro cliente, **When**
   alguém tenta restaurá-lo sem informar um novo telefone, **Then** a restauração é recusada
   indicando o dado em conflito; informando um telefone novo e livre, a restauração é concluída
   com ele.
7. **Given** um Gerente, mesmo com qualquer permissão, **When** ele tenta excluir ou restaurar um
   cliente, **Then** a operação é recusada por falta de permissão.
8. **Given** um usuário do painel sem a permissão correspondente, **When** ele tenta a operação,
   **Then** ela é recusada por falta de permissão.
9. **Given** uma edição pelo painel que muda o telefone, o CPF ou o e-mail para um já usado por
   outro cliente, **When** ela é enviada, **Then** é recusada informando a duplicidade.

---

### User Story 8 - Cadastrar promoções (Priority: P3)

Um usuário do painel com permissão cadastra, edita, ativa, desativa e exclui promoções. Cada
promoção tem uma categoria (Primeiro cadastro, Primeiro depósito, Qualquer depósito ou
Indicação), uma modalidade (Esportes ou Cassino), um tipo de ganho (Fixo ou Percentual), um
rollover e regras de uso. Nesta spec, só a categoria "Primeiro cadastro" é aplicada: ao se
cadastrar, o cliente recebe o valor da promoção vigente no saldo promocional da modalidade. As
demais categorias e as regras de uso já podem ser cadastradas e serão aplicadas pelas specs de
apostas, depósito e afiliados.

**Why this priority**: atrai novos apostadores, mas o cadastro funciona sem promoção (saldos
zerados).

**Independent Test**: cadastrar uma promoção de primeiro cadastro de R$ 20,00 para Esportes,
ativá-la, cadastrar um cliente e conferir o saldo promocional; desativá-la e conferir que o
próximo cliente nasce com saldos zerados.

**Acceptance Scenarios**:

1. **Given** um usuário com permissão, **When** ele cadastra uma promoção com nome, modalidade,
   categoria "Primeiro cadastro", tipo de ganho "Fixo", valor, rollover, regras de uso e período,
   **Then** a promoção é criada.
2. **Given** uma promoção ativa de primeiro cadastro para Esportes, **When** outra promoção ativa
   de primeiro cadastro para Esportes com período sobreposto é cadastrada ou ativada, **Then** a
   operação é recusada, pois a mesma categoria não pode ter períodos sobrepostos na mesma
   modalidade.
3. **Given** uma promoção ativa de primeiro cadastro para Esportes, **When** uma promoção ativa
   de primeiro depósito para Esportes é cadastrada com o mesmo período, **Then** ela é aceita,
   pois as categorias são diferentes.
4. **Given** uma promoção de primeiro depósito sem rollover, ou uma promoção de primeiro cadastro
   com tipo de ganho "Percentual", **When** ela é enviada, **Then** é recusada como dado inválido.
5. **Given** uma promoção fora do período ou inativa, **When** um cliente se cadastra, **Then**
   ela não é aplicada.
6. **Given** promoções de primeiro cadastro vigentes para Esportes e para Cassino, **When** um
   cliente se cadastra, **Then** ele recebe as duas, cada uma no seu saldo promocional, com uma
   transação para cada.

---

### User Story 9 - Meios de pagamento do cliente (Priority: P2)

O cliente logado cadastra, consulta, edita e exclui os próprios meios de pagamento: um ou vários,
do tipo Pix (nome, tipo e chave) ou Transferência bancária (dados principais do banco), e escolhe
qual é o principal. Pelo painel, quem tem permissão consulta e edita esses dados. Os meios de
pagamento serão usados pela futura spec de saques.

**Why this priority**: prepara os dados para o saque, mas o cliente já aposta sem eles.

**Independent Test**: cadastrar um Pix e uma conta bancária para um cliente, marcar a conta como
principal e conferir a listagem; tentar cadastrar a mesma chave Pix de novo e conferir a recusa.

**Acceptance Scenarios**:

1. **Given** um cliente logado, **When** ele cadastra um Pix com nome, tipo "CPF" e uma chave de
   CPF válida, **Then** o meio de pagamento é criado; se for o primeiro, ele fica como principal.
2. **Given** um cliente logado, **When** ele cadastra uma Transferência bancária com banco,
   agência, conta, dígito, tipo de conta e titular, **Then** o meio de pagamento é criado.
3. **Given** uma chave Pix que não combina com o tipo informado (ex.: tipo "E-mail" com um
   telefone), **When** ela é enviada, **Then** é recusada como dado inválido.
4. **Given** um cliente que já tem uma chave Pix cadastrada, **When** ele cadastra a mesma chave
   de novo, **Then** o cadastro é recusado por duplicidade.
5. **Given** um cliente com dois meios, **When** ele marca o segundo como principal, **Then** o
   primeiro deixa de ser principal.
6. **Given** um cliente, **When** ele tenta ver ou alterar um meio de pagamento de outro cliente,
   **Then** a resposta é "não encontrado".
7. **Given** um usuário do painel com `clientes.listar` e sem `clientes.ver_dados_completos`,
   **When** ele consulta os meios de pagamento de um cliente, **Then** vê a chave Pix e a conta
   mascaradas.

---

### User Story 10 - Estornar uma promoção de todos que a receberam (Priority: P3)

Um Admin ou Supervisor com permissão estorna uma promoção: o sistema retira, do saldo promocional
de cada cliente que recebeu aquela promoção, o valor recebido, registrando uma transação de
estorno para cada um. Se o cliente já tiver usado parte do bônus, o saldo promocional daquela
modalidade é zerado e nada fica pendente. A promoção é desativada e o andamento pode ser
acompanhado pelo painel.

**Why this priority**: protege a banca contra promoções criadas por engano ou abusadas, mas só é
necessário quando algo dá errado.

**Independent Test**: cadastrar uma promoção de primeiro cadastro, cadastrar 3 clientes (um deles
com parte do bônus já debitada), estornar a promoção e conferir os saldos promocionais e as
transações de estorno.

**Acceptance Scenarios**:

1. **Given** uma promoção que 3 clientes receberam com R$ 20,00 cada, **When** um Admin a
   estorna informando o motivo, **Then** os 3 saldos promocionais perdem R$ 20,00, cada um com uma
   transação de origem "Estorno" que referencia a promoção, e a promoção fica desativada.
2. **Given** um cliente que recebeu R$ 20,00 da promoção e já tem só R$ 5,00 no saldo
   promocional, **When** a promoção é estornada, **Then** R$ 5,00 é retirado, o saldo promocional
   fica em 0,00 e nada fica pendente.
3. **Given** um cliente com saldo promocional 0,00, **When** a promoção é estornada, **Then**
   nenhuma transação é criada para ele e ele aparece no resumo como "sem saldo para estornar".
4. **Given** um cliente que tem R$ 50,00 no `saldo` real e R$ 0,00 no saldo promocional,
   **When** a promoção é estornada, **Then** o `saldo` real continua R$ 50,00.
5. **Given** uma promoção já estornada, **When** alguém tenta estorná-la de novo, **Then** a
   operação é recusada.
6. **Given** um Gerente, mesmo com qualquer permissão, **When** ele tenta estornar uma promoção,
   **Then** a operação é recusada por falta de permissão.
7. **Given** um estorno em andamento com muitos clientes, **When** o usuário consulta a promoção,
   **Then** vê a situação do estorno (em andamento ou concluído), quantos clientes foram
   processados e o total estornado.

---

### Edge Cases

- Telefone é comparado apenas pelos dígitos, junto com o DDI: `(11) 98888-7777` e `11988887777`
  com DDI 55 são o mesmo telefone, tanto no cadastro quanto no login e na recuperação de senha.
- CPF é comparado apenas pelos dígitos (com ou sem pontuação é o mesmo CPF); e-mail é comparado
  sem diferenciar maiúsculas e minúsculas e sem espaços nas pontas.
- CPF e e-mail vazios não contam como duplicidade: vários clientes podem estar sem CPF ou sem
  e-mail.
- Telefone, CPF e e-mail de um cliente excluído ficam livres para novos cadastros, pois recebem o
  sufixo de exclusão.
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
- A promoção de primeiro cadastro é aplicada uma única vez por cliente; restaurar um cliente
  excluído não aplica a promoção de novo.
- O estorno de promoção alcança também clientes inativos ou excluídos que receberam a promoção.
- Excluir o meio de pagamento principal faz o mais antigo restante virar o principal.
- Um estorno interrompido (queda do servidor) pode ser retomado sem estornar duas vezes o mesmo
  cliente.

## Requirements *(mandatory)*

### Functional Requirements

**Cadastro público**

- **FR-001**: O sistema DEVE permitir que um visitante se cadastre como cliente por uma rota
  pública, sem autenticação, separada do cadastro de usuários do painel.
- **FR-001a**: O cadastro público DEVE aceitar no máximo 5 tentativas por minuto do mesmo IP;
  acima disso, DEVE recusar informando o tempo de espera.
- **FR-002**: Cada cliente DEVE possuir: nome, DDI, telefone, e-mail (opcional), senha, CPF
  (opcional), data de nascimento, gênero, código de afiliado (opcional), indicação de ativo,
  `saldo`, `saldo_promocao_esportes`, `saldo_promocao_cassino` e as datas `created_at`,
  `updated_at` e `deleted_at`.
- **FR-003**: Nome, telefone, senha (com confirmação), data de nascimento e gênero DEVEM ser
  obrigatórios no cadastro; DDI (padrão 55), e-mail, CPF, código de afiliado e `aceita_promocao`
  (padrão marcado) DEVEM ser opcionais.
- **FR-004**: O DDI DEVE ter de 1 a 3 dígitos. O telefone DEVE ser armazenado e comparado somente
  com dígitos; para o DDI 55 DEVE ter 10 ou 11 dígitos (DDD + número) e, para os demais, de 4 a 14
  dígitos.
- **FR-005**: A combinação DDI + telefone DEVE ser única entre os clientes não excluídos.
- **FR-006**: O CPF, quando informado, DEVE ser único entre os clientes não excluídos, armazenado
  e comparado somente com dígitos, e DEVE ter dígitos verificadores válidos.
- **FR-006a**: O e-mail, quando informado, DEVE ter formato válido, ser armazenado em minúsculas e
  sem espaços nas pontas, e ser único entre os clientes não excluídos.
- **FR-007**: Só DEVEM ser aceitos cadastros de pessoas com 18 anos ou mais na data do cadastro.
- **FR-008**: O gênero DEVE ser um dos valores: Masculino, Feminino, Outro ou Não informado.
- **FR-009**: O código de afiliado, quando informado, DEVE ser guardado como texto, sem validação e
  sem ligação com outra tabela, pois a gestão de afiliados ainda não existe (será ligado em outra
  spec).
- **FR-010**: A senha do cliente DEVE ter no mínimo 8 caracteres, com pelo menos uma letra e um
  número, e DEVE ser informada duas vezes (senha e confirmação) no cadastro, na troca e na
  recuperação; a confirmação diferente DEVE recusar a operação. A senha DEVE ser armazenada de
  forma irreversível e NUNCA DEVE ser retornada em consultas ou listagens.
- **FR-011**: Todo novo cliente DEVE ser criado ativo, com os três saldos em 0,00 e com um registro
  de configurações preenchido com os valores padrão (FR-048), exceto `aceita_promocao`, que recebe
  o valor informado no cadastro. Em seguida, se `aceita_promocao` estiver marcado, DEVE receber as
  promoções de primeiro cadastro vigentes (FR-075). Após o cadastro, o sistema DEVE devolver um
  token de acesso do cliente (60 minutos), como no login, para que ele já entre logado.
- **FR-012**: Clientes NÃO DEVEM ter vínculo com usuários do painel (gerentes, vendedores ou
  outros).
- **FR-013**: Após o cadastro, o sistema DEVE disparar o evento "conta criada", com os dados
  necessários para a mensagem de boas-vindas por WhatsApp (nome, DDI e telefone). O envio real por
  WhatsApp (provedor, conexão, textos e reenvio) NÃO faz parte desta spec e será feito por uma spec
  própria; até lá, a mensagem DEVE ser apenas registrada no log da aplicação. Falha no disparo NÃO
  DEVE impedir nem desfazer o cadastro.

**Autenticação do cliente**

- **FR-014**: O cliente DEVE se autenticar com DDI (padrão 55), telefone e senha e receber um token
  de acesso JWT válido por 60 minutos, emitido por um guard próprio de clientes.
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

- **FR-021**: O sistema DEVE permitir que o cliente peça a recuperação de senha informando DDI
  (padrão 55) e telefone, por rota pública.
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
  evento "código de recuperação gerado" (com DDI, telefone e código), e, até existir a spec de
  WhatsApp, a mensagem DEVE ser apenas registrada no log da aplicação.

**Área do cliente ("meus dados")**

- **FR-028**: O cliente autenticado DEVE poder consultar os próprios dados, os três saldos e o
  valor de `aceita_promocao`.
- **FR-029**: O cliente autenticado DEVE poder alterar somente nome, gênero, e-mail (respeitando
  FR-006a) e `aceita_promocao`; DDI, telefone, CPF e data de nascimento só DEVEM ser alterados pelo
  painel.
- **FR-030**: O cliente autenticado DEVE poder trocar a senha informando a senha atual e a nova
  duas vezes, seguindo FR-010. Após a troca, todos os tokens emitidos antes dela, inclusive o
  atual, DEVEM deixar de ser aceitos, e o cliente precisa entrar de novo.
- **FR-031**: O cliente autenticado DEVE poder consultar o próprio extrato, filtrando por data
  inicial e final e por saldo afetado, ordenado da transação mais recente para a mais antiga e
  paginado (padrão de 20 e máximo de 100 por página).

**Meios de pagamento**

- **FR-032**: Cada cliente PODE ter um ou vários meios de pagamento (tabela
  `clientes_meios_pagamento`), de um dos tipos: Pix ou Transferência bancária.
- **FR-033**: Um meio do tipo Pix DEVE ter: nome do titular da chave (como aparece no banco,
  obrigatório), tipo de chave (CPF, CNPJ,
  E-mail, Telefone ou Chave aleatória) e a chave. A chave DEVE ser validada conforme o tipo (CPF e
  CNPJ com dígitos verificadores; e-mail em formato válido; telefone só com dígitos; chave
  aleatória no formato de identificador de 32 caracteres hexadecimais com hífens).
- **FR-034**: Um meio do tipo Transferência bancária DEVE ter: código do banco (3 dígitos), nome do
  banco, agência, conta, dígito da conta, tipo de conta (Corrente ou Poupança), nome do titular e
  CPF ou CNPJ do titular.
- **FR-035**: Um cliente NÃO DEVE ter duas vezes a mesma chave Pix, nem duas vezes a mesma conta
  (mesmo banco, agência e conta), entre os seus meios não excluídos.
- **FR-036**: Cada cliente com meios de pagamento DEVE ter exatamente um marcado como principal: o
  primeiro cadastrado vira principal; marcar outro como principal desmarca o anterior; excluir o
  principal torna principal o mais antigo restante.
- **FR-037**: O cliente autenticado DEVE poder listar, cadastrar, editar e excluir (soft delete) os
  próprios meios de pagamento e marcar o principal; qualquer tentativa sobre meio de outro cliente
  DEVE retornar "não encontrado".
- **FR-038**: Usuários do painel com `clientes.listar` DEVEM poder consultar os meios de pagamento
  de um cliente, e com `clientes.editar` DEVEM poder cadastrar, editar e excluir, com as mesmas
  regras de FR-033 a FR-036.
- **FR-039**: Esta spec só armazena os meios de pagamento; o uso deles em saques NÃO faz parte
  desta spec.

**Saldos e transações**

- **FR-040**: Cada cliente DEVE ter três saldos independentes: `saldo` (dinheiro real),
  `saldo_promocao_esportes` e `saldo_promocao_cassino`. Todos são valores monetários com 2 casas
  decimais e NUNCA DEVEM ficar negativos.
- **FR-041**: Um saldo SÓ DEVE ser alterado por meio de uma transação; toda alteração DEVE gerar
  exatamente um registro de transação, que afeta um único saldo.
- **FR-042**: Cada transação DEVE registrar: cliente, saldo afetado (Saldo, Promoção esportes ou
  Promoção cassino), tipo (Crédito ou Débito), origem (Ajuste manual, Promoção, Aposta, Prêmio ou
  Estorno), referência opcional ao registro de origem (por exemplo, a promoção ou, no futuro, a
  aposta), valor (maior que zero), saldo anterior, saldo posterior, autor da operação (sistema ou
  usuário do painel identificado), observação e data.
- **FR-043**: A referência ao registro de origem DEVE ser guardada sem ligação obrigatória com
  outra tabela, pois origens como apostas ainda não existem (serão ligadas em outra spec).
- **FR-044**: O saldo posterior de uma transação DEVE ser o saldo anterior somado ao valor
  (crédito) ou subtraído do valor (débito), e DEVE ser igual ao valor atual do saldo afetado logo
  após a operação.
- **FR-045**: Um débito maior que o saldo afetado DEVE ser recusado, sem criar transação nem
  alterar o saldo.
- **FR-046**: Movimentações simultâneas no mesmo cliente DEVEM ser processadas uma de cada vez,
  de forma que nenhuma se perca e os saldos nunca fiquem inconsistentes com as transações.
- **FR-047**: Transações NÃO DEVEM ser editadas nem excluídas; correções DEVEM ser feitas por
  novas transações. A operação de crédito e débito DEVE ser única e reutilizável por outras partes
  do sistema (como a futura spec de apostas), aplicando sempre FR-040 a FR-047. Usuários do painel
  com a permissão `clientes.movimentar_saldo` DEVEM poder lançar crédito ou débito manual (origem
  "Ajuste manual") em qualquer um dos três saldos, informando valor e motivo (obrigatório,
  gravado como observação).

**Configurações do cliente**

- **FR-048**: Cada cliente DEVE ter exatamente um registro de configurações (tabela
  `clientes_configuracoes`; no sistema antigo, "travas"), com:
  - permissões (liberado/bloqueado): realizar aposta, apostar ao vivo, apostar em outros
    esportes, cancelar aposta;
  - `aceita_promocao` (aceita receber promoções);
  - `bloquear_saque` (quando marcado, o cliente não pode sacar);
  - quantidade mínima e máxima de opções (palpites) por aposta;
  - valor mínimo e máximo por aposta;
  - prêmio máximo;
  - valor máximo apostado por dia;
  - limites de saque por dia do calendário: valor máximo em reais e quantidade máxima de saques;
  - odd mínima e odd máxima;
  - esportes permitidos (lista).
- **FR-049**: Não existe tabela padrão (revisão de 2026-10-01, spec 003). No cadastro, a
  configuração do cliente novo DEVE ser criada com os valores padrão das colunas de
  `clientes_configuracoes` (FR-011); mudar esses valores exige uma migration e NÃO muda as
  configurações de clientes já cadastrados.
- **FR-050**: Os valores padrão das colunas de `clientes_configuracoes` DEVEM ser os da antiga
  `travas_gerentes` e `travas_vendedors` (`database.sql`):
  - realizar aposta, apostar ao vivo e apostar em outros esportes: liberado; cancelar aposta:
    bloqueado;
  - `aceita_promocao`: marcado; `bloquear_saque`: desmarcado;
  - opções por aposta: mínimo 1 e máximo 20;
  - valor por aposta: mínimo R$ 2,00 e máximo R$ 1.000,00;
  - prêmio máximo: R$ 50.000,00;
  - valor máximo apostado por dia: R$ 5.000,00 (antigo `limite_geral`);
  - limite de saque por dia: R$ 5.000,00 e 5 saques (antigo `limite_saque`);
  - odd mínima 1,90 e odd máxima 30,00;
  - esportes permitidos: FUTEBOL, HOQUEI NO GELO e BAISEBOL (grafados como no sistema antigo).
    Padrão alterado na spec 005 para todos os esportes do provedor.
- **FR-051**: (Removido em 2026-10-01 pela spec 003.) Não há rota nem permissão para editar
  configurações padrão.
- **FR-052**: Os limites DEVEM ser coerentes: quantidade mínima de opções ≥ 1 e ≤ quantidade
  máxima; valor mínimo por aposta > 0 e ≤ valor máximo; odd mínima ≥ 1,00 e ≤ odd máxima; prêmio
  máximo e valor máximo por dia > 0; valor máximo de saque por dia > 0; quantidade máxima de saques
  por dia ≥ 1. Alterações incoerentes DEVEM ser recusadas.
- **FR-053**: O cliente NÃO DEVE poder alterar as próprias configurações, exceto `aceita_promocao`
  (FR-029). Usuários do painel com `clientes.listar` DEVEM poder consultá-las, e somente Admin,
  Supervisor ou Gerente com `clientes.editar_configuracoes` DEVEM poder alterá-las.
- **FR-054**: Esta spec só armazena e gerencia as configurações; a aplicação delas nas apostas e
  nos saques NÃO faz parte desta spec.

**Gestão de clientes no painel**

- **FR-055**: A gestão de clientes DEVE ser acessível somente a Admin, Supervisor e Gerente, com o
  token do painel e as permissões correspondentes:
  - `clientes.listar`: listar, consultar, ver extrato, configurações e meios de pagamento;
  - `clientes.ver_dados_completos`: ver CPF, telefone, e-mail e dados de pagamento completos
    (FR-057);
  - `clientes.editar`: editar dados e meios de pagamento, ativar e desativar;
  - `clientes.editar_configuracoes`: alterar configurações;
  - `clientes.movimentar_saldo`: crédito e débito manual;
  - `clientes.excluir`: excluir (somente Admin e Supervisor);
  - `clientes.restaurar`: listar excluídos e restaurar (somente Admin e Supervisor).
  Vendedores NÃO DEVEM ter acesso à gestão de clientes, e Gerentes NÃO DEVEM poder excluir nem
  restaurar clientes, mesmo que recebam a permissão. Os clientes não pertencem a nenhum usuário do
  painel; quem tem a permissão enxerga e gerencia todos (sem recorte por hierarquia).
- **FR-056**: A listagem de clientes DEVE ser paginada (padrão de 20 e máximo de 100 por página),
  NÃO DEVE incluir clientes excluídos (salvo pela listagem de FR-061) e DEVE oferecer:
  - busca por parte do nome, parte do telefone, CPF (com ou sem pontuação) ou e-mail;
  - filtros por: ativo/inativo, DDI, código de afiliado, período de cadastro, faixa de idade
    (pela data de nascimento), gênero, faixa de `saldo`, clientes com saldo promocional
    (esportes ou cassino) maior que zero, saque bloqueado, com ou sem CPF e com ou sem e-mail;
  - ordenação por nome, data de cadastro ou `saldo`, crescente ou decrescente.
  Usuários com `clientes.listar` DEVEM poder consultar o extrato de qualquer cliente, com os
  mesmos filtros e paginação de FR-031.
- **FR-057**: Em todas as respostas da gestão de clientes, CPF, telefone, e-mail e dados de
  pagamento DEVEM vir mascarados para quem não tem `clientes.ver_dados_completos`: CPF
  `***.456.789-**`; telefone com só os 4 últimos dígitos visíveis (ex.: `(11) *****-7777`);
  e-mail com a primeira letra e o domínio (ex.: `a***@mail.com`); chave Pix e conta bancária com
  só os 4 últimos caracteres visíveis. Sem essa permissão, a busca por CPF, telefone e e-mail DEVE
  aceitar somente o valor completo (busca exata).
- **FR-058**: Pelo painel DEVE ser possível editar nome, DDI, telefone, e-mail, CPF, data de
  nascimento, gênero, código de afiliado e senha do cliente, respeitando FR-004 a FR-010; campos
  não informados (especialmente a senha) DEVEM permanecer inalterados. Os saldos NÃO DEVEM ser
  alterados pela edição (só por FR-047).
- **FR-059**: Pelo painel DEVE ser possível ativar e desativar um cliente; o cliente desativado
  perde o acesso imediatamente, inclusive com tokens já emitidos (FR-017).
- **FR-060**: A exclusão de clientes DEVE ser lógica (preenchendo `deleted_at`) e DEVE acrescentar
  o sufixo `_deleted_<timestamp>` a todos os dados únicos preenchidos do cliente (telefone, CPF e
  e-mail), preservando o valor original antes do sufixo, os saldos, as transações e os meios de
  pagamento. O cliente excluído perde o acesso imediatamente.
- **FR-061**: A restauração DEVE remover o sufixo, devolvendo os dados únicos originais, e limpar
  `deleted_at`. Antes, o sistema DEVE verificar se algum dado único original já está em uso por
  outro cliente não excluído; se estiver, a restauração DEVE ser recusada indicando os dados em
  conflito, e só DEVE ser concluída se forem informados novos valores válidos e livres para esses
  dados na própria restauração. Usuários com `clientes.restaurar` DEVEM poder listar os clientes
  excluídos, com a mesma busca e paginação de FR-056 (a busca considera o valor original).
- **FR-062**: As novas permissões (`clientes.listar`, `clientes.ver_dados_completos`,
  `clientes.editar`, `clientes.editar_configuracoes`, `clientes.movimentar_saldo`,
  `clientes.excluir`, `clientes.restaurar`, `clientes_promocoes.gerenciar` e
  `clientes_promocoes.estornar`; a `clientes.editar_configuracoes_padrao` foi removida pela spec
  003) DEVEM ser criadas no
  `spatie/laravel-permission` pelo seeder e seguir as regras de atribuição da spec 001 (permissões
  diretas no usuário; um gestor só dá a subordinados permissões que ele mesmo tem), respeitando as
  restrições por função de FR-055 e FR-081.
- **FR-063**: Operações recusadas DEVEM retornar mensagens claras em português indicando o motivo
  (dado inválido, duplicidade, conflito na restauração, saldo insuficiente, sem permissão, não
  encontrado, não autenticado).

**Promoções**

- **FR-064**: O sistema DEVE ter um cadastro de promoções (tabela `clientes_promocoes`, substitui
  a antiga de bônus), com: nome, descrição, modalidade (Esportes ou Cassino), categoria (Primeiro
  cadastro, Primeiro depósito, Qualquer depósito ou Indicação), tipo de ganho (Fixo ou
  Percentual), valor, rollover, regras de uso (FR-067), data de início, data de fim (opcional),
  indicação de ativa, dados do estorno (FR-078) e as datas `created_at`, `updated_at` e
  `deleted_at`.
- **FR-065**: No tipo de ganho Fixo, o valor é a quantia em reais creditada; no Percentual, o valor
  é o percentual (maior que 0 e até 100) aplicado sobre o depósito. O tipo Percentual só DEVE ser
  aceito nas categorias de depósito (Primeiro depósito e Qualquer depósito); Primeiro cadastro e
  Indicação DEVEM ser Fixo.
- **FR-066**: O rollover é um número inteiro de vezes que o valor do bônus precisa ser apostado
  antes de poder ser recuperado (ex.: rollover 5 sobre R$ 20,00 exige R$ 100,00 em apostas). DEVE
  ser ≥ 0, e nas promoções de Primeiro depósito DEVE ser ≥ 1.
- **FR-067**: Cada promoção DEVE ter as regras de uso do bônus: valor mínimo de aposta, valor
  máximo de aposta, valor máximo de depósito (base de cálculo do Percentual), valor máximo de
  conversão do bônus em saldo real (teto, em reais, do que o bônus pode virar saldo real depois de
  cumprido o rollover; o excedente é descartado), odd mínima de aposta simples e odd mínima de aposta múltipla.
  As regras DEVEM ser coerentes: valores > 0; valor mínimo de aposta ≤ máximo; odds ≥ 1,00. O
  valor máximo de depósito é obrigatório nas promoções Percentuais e opcional nas demais.
- **FR-068**: Usuários do painel com a permissão `clientes_promocoes.gerenciar` DEVEM poder listar
  (paginado, com filtros por ativa, modalidade e categoria), consultar, cadastrar, editar, ativar,
  desativar e excluir logicamente promoções. Uma promoção estornada NÃO DEVE poder ser reativada
  nem editada.
- **FR-069**: Uma promoção está vigente quando está ativa, não excluída, não estornada e a data
  atual está entre a data de início e a data de fim (ou sem data de fim).
- **FR-070**: Promoções ativas e não excluídas da **mesma categoria e mesma modalidade** NÃO
  DEVEM ter períodos que se sobreponham (inclusive futuros; data de fim vazia = sem fim).
  Promoções de categorias diferentes PODEM ter períodos sobrepostos. Cadastrar, editar ou ativar
  uma promoção que cause sobreposição proibida DEVE ser recusado.
- **FR-071**: Nesta spec, só a categoria Primeiro cadastro é aplicada (FR-075). Primeiro depósito,
  Qualquer depósito e Indicação são cadastradas agora e aplicadas pelas specs de depósito e de
  afiliados.
- **FR-072**: O rollover, as regras de uso (FR-067) e a conversão do bônus em saldo real são
  cadastrados e validados nesta spec, mas a aplicação deles nas apostas, nos depósitos e na
  conversão NÃO faz parte desta spec.
- **FR-073**: Uma promoção que já foi aplicada a algum cliente NÃO DEVE ter valor, tipo de ganho,
  categoria ou modalidade alterados; os demais campos podem ser editados.
- **FR-074**: Regras de expiração do saldo promocional e cashback NÃO fazem parte desta spec.
- **FR-075**: No cadastro de um cliente com `aceita_promocao` marcado, para cada promoção de
  Primeiro cadastro vigente, o sistema DEVE creditar o valor dela no saldo promocional da
  modalidade (Esportes → `saldo_promocao_esportes`, Cassino → `saldo_promocao_cassino`) por uma
  transação de origem "Promoção" que referencia a promoção (FR-042). Desde a spec 004 (FR-047 de lá),
  quando a promoção tem rollover maior que zero, o crédito também cria o registro de
  acompanhamento em `clientes_rollovers`, com as regras de uso da promoção gravadas.
- **FR-076**: A promoção de Primeiro cadastro DEVE ser aplicada uma única vez por cliente, somente
  no momento do cadastro; marcar `aceita_promocao` depois NÃO DEVE aplicar a promoção
  retroativamente, e desmarcar depois NÃO DEVE retirar saldo promocional já recebido.

**Estorno de promoção (rollback)**

- **FR-077**: Admin ou Supervisor com a permissão `clientes_promocoes.estornar` DEVE poder estornar
  uma promoção, informando o motivo. O estorno alcança todos os clientes que têm transação de
  origem "Promoção" referenciando essa promoção, inclusive inativos e excluídos. Desde a spec 004,
  o estorno também marca como cancelados (`cancelado_em`) os rollovers pendentes daquela promoção,
  para que as regras de uso do bônus deixem de valer.
- **FR-078**: Ao iniciar o estorno, a promoção DEVE ser desativada e passar a guardar: situação do
  estorno (Em andamento ou Concluído), quem estornou, quando, o motivo, a quantidade de clientes a
  processar, a quantidade processada e o total estornado.
- **FR-079**: Para cada cliente, o sistema DEVE debitar, do saldo promocional da modalidade, o
  menor valor entre o total recebido daquela promoção e o saldo promocional atual, por uma transação
  de origem "Estorno" que referencia a promoção, com o motivo como observação. Se o saldo
  promocional for 0,00, nenhuma transação é criada e o cliente conta como "sem saldo para
  estornar". O que faltar NÃO fica pendente, o saldo NUNCA DEVE ficar negativo e o `saldo` real e
  o saldo promocional da outra modalidade NUNCA DEVEM ser alterados pelo estorno.
- **FR-080**: O estorno DEVE ser processado em segundo plano, em lotes, sem travar o painel, e
  DEVE poder ser retomado após uma interrupção sem estornar duas vezes o mesmo cliente (um cliente
  que já tem transação de "Estorno" dessa promoção é pulado).
- **FR-081**: Uma promoção já estornada (ou com estorno em andamento) NÃO DEVE poder ser estornada
  de novo. Gerentes e Vendedores NÃO DEVEM poder estornar, mesmo que recebam a permissão.

### Key Entities

- **Cliente**: apostador do sistema (tabela `clientes`, substitui a antiga `creditos`).
  Atributos: nome, DDI, telefone (login), e-mail (opcional), senha, CPF (opcional), data de
  nascimento, gênero, código de afiliado (texto, sem ligação), ativo, `saldo`,
  `saldo_promocao_esportes`, `saldo_promocao_cassino`, `created_at`, `updated_at` e
  `deleted_at`. Não pertence a nenhum usuário do painel.
- **Transação do cliente** (tabela `clientes_transacoes`): registro imutável de cada movimentação
  de um dos saldos. Atributos: cliente, saldo afetado, tipo, origem, referência de origem (sem
  ligação obrigatória), valor, saldo anterior, saldo posterior, autor (sistema ou usuário do
  painel), observação e data. A sequência de transações de cada saldo explica o valor atual dele.
- **Configurações do cliente** (tabela `clientes_configuracoes`): limites e permissões de aposta e
  de saque de um cliente e se ele aceita promoções (uma por cliente), criadas no cadastro com os
  valores padrão das colunas e alteradas pelo painel (exceto `aceita_promocao`, que o cliente
  também altera). A tabela padrão (`clientes_configuracoes_padrao`) foi removida pela spec 003.
- **Meio de pagamento do cliente** (tabela `clientes_meios_pagamento`): Pix (nome do titular, tipo e
  chave) ou Transferência bancária (banco, agência, conta, dígito, tipo de conta e titular); um
  deles é o principal.
- **Promoção** (tabela `clientes_promocoes`): benefício concedido em saldo promocional de uma
  modalidade, com categoria, tipo de ganho, valor, rollover, regras de uso, período e dados do
  estorno; nesta spec só o Primeiro cadastro é aplicado.
- **Código de recuperação de senha** (tabela `clientes_codigos_recuperacao`): código temporário de
  6 dígitos, de uso único, ligado a um cliente, com validade, contagem de tentativas e situação
  (válido, usado ou invalidado).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Um visitante conclui o cadastro e o primeiro login em menos de 2 minutos.
- **SC-002**: Em 100% dos clientes, cada um dos três saldos é igual ao saldo posterior da sua
  última transação (ou 0,00 sem transações), mesmo após movimentações simultâneas e estornos.
- **SC-003**: Em 100% das tentativas, nenhum saldo fica negativo, inclusive no estorno de
  promoções.
- **SC-004**: Em 100% das tentativas, um token de cliente não acessa rotas do painel e um token do
  painel não acessa rotas da área do cliente.
- **SC-005**: Em 100% das tentativas, clientes inativos ou excluídos não conseguem entrar nem usar
  tokens já emitidos.
- **SC-006**: Um cliente que esqueceu a senha recupera o acesso sozinho em menos de 3 minutos.
- **SC-007**: Em 100% das restaurações, nenhum cliente volta com telefone, CPF ou e-mail
  duplicado.
- **SC-008**: Nenhuma consulta ou listagem expõe a senha de clientes.
- **SC-009**: A listagem e a busca de clientes com até 100.000 cadastros e o extrato de um cliente
  com até 10.000 transações são exibidos em menos de 2 segundos.
- **SC-010**: O estorno de uma promoção recebida por 10.000 clientes termina em menos de 10
  minutos, sem nenhum cliente estornado duas vezes.

## Assumptions

- O envio real de mensagens por WhatsApp fica numa spec própria; nesta spec, boas-vindas e código
  de recuperação são disparados como eventos e registrados no log da aplicação, o que permite
  validar a recuperação de senha manualmente.
- Telas (frontend) estão fora do escopo; esta spec cobre somente o backend que a futura área de
  gestão de clientes e a área do cliente vão usar.
- "Movimentação de apostas" na gestão de clientes depende da spec de apostas e será acrescentada
  por ela.
- Permissões padrão por função, no mesmo padrão das permissões de usuários (`Funcao`): Admin e
  Supervisor recebem as 9 permissões de clientes e de promoções; Gerente recebe as 6 que pode usar
  (todas, exceto excluir, restaurar e estorno); Vendedor não recebe nenhuma.
  Ajustes individuais continuam pela gestão de permissões da spec 001.
- Os valores de enum seguem a constituição v1.13.0: gravados em português, com a primeira letra
  maiúscula e acentos (ex.: `'Não informado'`, `'Ajuste manual'`, `'Primeiro depósito'`,
  `'Transferência bancária'`).
- As regras de uso da promoção têm nomes completos no lugar das abreviações do pedido
  (`v_apostas_minima` → valor mínimo de aposta, `v_apostas_maxima` → valor máximo de aposta,
  `v_deposito_maximo` → valor máximo de depósito, `v_converter_bonus` → valor máximo de conversão
  do bônus, `odd_minima_apostas_simples`/`multipla` → odd mínima de aposta simples/múltipla).
- Os valores padrão dos limites de saque (R$ 5.000,00 e 5 saques por dia) vêm da antiga
  `travas_vendedors`.
- A titularidade dos meios de pagamento (se o titular é o próprio cliente) não é validada nesta
  spec; será tratada na spec de saques.
- Colunas que dependem de tabelas ainda inexistentes (código de afiliado, referência de origem das
  transações, esportes permitidos) ficam sem chave estrangeira e serão ligadas em outras specs.
- A lista de esportes permitidos é guardada como lista de nomes de esporte, pois ainda não existe
  cadastro de esportes no sistema.
- Se um estorno for retomado depois de uma interrupção, um cliente que não tinha saldo
  promocional na primeira passada é avaliado de novo; se nesse intervalo ele recebeu outro bônus da
  mesma modalidade, esse saldo pode ser estornado. O risco é aceito, pois retomadas são raras.
- O timestamp do sufixo de exclusão é o momento da exclusão em segundos (Unix).
- Valores monetários são em reais (BRL) com 2 casas decimais.
- O "valor máximo apostado por dia" e os limites de saque por dia consideram o dia do calendário no
  fuso horário do sistema; a contagem será aplicada pelas specs de apostas e saques.
- A tabela antiga `creditos` não é migrada; os dados do sistema anterior não são importados nesta
  spec.
- Logs de alteração (configurações, cadastro e outros) ficam fora do escopo; um sistema de logs
  geral será criado em spec própria.
- Depósito via PIX/gateway, saque, aplicação do rollover e das regras de uso das promoções,
  conversão do bônus, cashback, cassino, apostas/bilhetes e gestão de afiliados estão fora do
  escopo.
