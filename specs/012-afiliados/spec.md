# Feature Specification: Afiliados

**Feature Branch**: `012-afiliados`

**Created**: 2026-10-17

**Status**: Draft

**Input**: User description: "Afiliados. Migrar e corrigir o sistema de afiliados do sistema antigo
(wssports.bet: FiliadosController, model Filiados, FiliadoMail, migrations filiados, telas
new-affiliated, affiliated, adm/affiliated e modais affiliated). A gestão pelo painel (Admin,
Supervisor e Gerente) é só backend (API), no mesmo padrão de usuarios e clientes. O frontend desta
spec é a página pública /cadastro-afiliado, o modal de cadastro e o painel do afiliado /afiliados,
com o mesmo visual do sistema antigo e as melhorias aprovadas. Cadastro pelo painel e público (com
aprovação), login pela mesma tela dos demais usuários com guard próprio, código público de
indicação separado do login, comissão por Saldo líquido, Qualquer depósito e Primeiro depósito com
lançamentos gravados na hora, prestação de contas manual que sempre zera o valor a receber e fica
no histórico, painel do afiliado, permissões `afiliados.*` e correção dos problemas do sistema
antigo."

## Clarifications

### Session 2026-10-17 (decisões tomadas antes do `/speckit-specify`)

- Q: A gestão de afiliados pelo painel terá tela nesta spec? → A: Não. A parte do Admin,
  Supervisor e Gerente é só backend (API), como em `usuarios` e `clientes`. O frontend desta spec
  é a página pública `/cadastro-afiliado`, o modal de cadastro e o painel do afiliado `/afiliados`.
- Q: O que acontece com o saldo negativo do afiliado (Saldo líquido com mais saques que
  depósitos)? → A: É zerado na prestação de contas. Não passa para o próximo período e nada é pago.
- Q: O código do link de indicação continua sendo o login do afiliado? → A: Não. O código público
  é separado do login. Os afiliados migrados recebem como código o login antigo, para os links já
  divulgados continuarem funcionando.
- Q: O afiliado pode pedir saque do que tem a receber? → A: Não. O pagamento é só por prestação de
  contas manual, que sempre zera todo o valor a receber, e cada prestação fica no histórico.
- Q: As melhorias no painel do afiliado e nos modais estão aprovadas, mesmo mudando o visual do
  antigo? → A: Sim. Filtro de período no extrato, lista de clientes indicados, botão "copiar meu
  link", histórico de prestações, "Meus dados" e troca de senha no painel; modais no padrão do
  `AuthModal` (erro por campo, máscaras, mostrar/ocultar senha, Esc e Voltar fecham, carregando
  no botão).
- Q: Haverá comissão sobre o resultado das apostas e do cassino (GGR)? → A: Não nesta spec. O
  Saldo líquido (depósitos menos saques) já cobre o ganho sobre o lucro da banca; o GGR pode
  entrar depois como mais um tipo de ganho.

### Session 2026-10-17 (`/speckit-clarify`)

- Q: Um afiliado inativo deve continuar ganhando comissão sobre os depósitos e saques dos clientes
  dele? → A: Sim. Inativar bloqueia só o acesso ao painel; os lançamentos continuam sendo gerados.
  Na lixeira, o afiliado para de ganhar (como no antigo, que calculava sem olhar o "ativo").
- Q: O Gerente pode definir e trocar a regra de comissão dos afiliados dele? → A: Sim, caso tenha
  a permissão para isso. Nova permissão `afiliados.editar_comissao`: Admin e Supervisor recebem por
  padrão; o Gerente pode usá-la quando receber (não vem por padrão). Sem ela, o afiliado cadastrado
  recebe a regra padrão da configuração e os campos de regra enviados são ignorados.
- Q: Quando um cliente se cadastra com o código de um afiliado que não está ativo, o que deve
  acontecer? → A: O cliente é cadastrado sem ligação com o afiliado (em análise, recusado, inativo
  ou na lixeira); só código inexistente recusa o cadastro com "Código de afiliado não encontrado.".
- Q: O afiliado que esquecer a senha deve poder recuperá-la sozinho? → A: Sim, por "Esqueci minha
  senha" com código enviado por e-mail, no padrão da recuperação do cliente (código de 6 dígitos,
  validade de 15 minutos, até 5 tentativas, nova senha digitada duas vezes).

### Session 2026-10-17 (`/speckit-plan`)

- Q: As alterações em arquivos existentes listadas no plano estão autorizadas? → A: Sim
  (Princípio IV), inclusive `ProcessamentoDepositos`, `PagamentoSaques`, `StoreUsuariosRequest`, a
  extração do trait `ChavePix`, as máscaras públicas do `ClientesResource` e os arquivos que usam
  `codigo_afiliado`.
- Q: O envio de e-mail pelo Laravel (Mailables na fila) está aprovado como padrão novo? → A: Sim
  (Princípio VI), e aproveitar para criar também o e-mail do cadastro de clientes: o cliente que
  informar e-mail no cadastro recebe um e-mail de boas-vindas. A confirmação de cadastro por link
  do antigo (`CadastroMail`, ligada pela configuração `confirmacao_cadastro`) não é trazida, porque
  no sistema novo a conta do cliente já nasce ativa e conectada (spec 002).

### Session 2026-10-17 (`/speckit-analyze`, recomendações aceitas pelo responsável)

- Q: Como fica o código dos afiliados migrados, cujo login antigo pode ter ponto, hífen ou
  sublinhado? → A: O código aceita letras, números, ponto, hífen e sublinhado (4 a 50); o código
  gerado no cadastro continua com 8 letras e números. O SC-006 é verificado nesta spec com um
  afiliado de código igual a um login antigo; a importação real fica para a spec de importação.
- Q: Telefone pode faltar? → A: Só nos afiliados migrados; nos cadastros novos é obrigatório, como
  e-mail e CPF.
- Q: Afiliado Recusado pode ser aprovado depois? → A: Sim (reavaliação), sem nova recusa.
- Q: A cor do tema usada nas páginas e nos e-mails é dado real? → A: Não; é o fake existente
  `DadosFake::tema()` (spec 005), registrado nas premissas (Princípio IX).

### Session 2026-10-22 (alterado pela spec 019)

- O gerente responsável passa a ser obrigatório no cadastro pelo painel, na edição (não pode ser
  retirado) e na aprovação do cadastro público; o Supervisor só escolhe gerentes da rede dele
  (spec 019, FR-003d).
- O Supervisor passa a ver e agir só sobre os afiliados dos gerentes da rede dele; o Admin vê todos,
  inclusive os sem gerente, com o filtro `sem_gerente` (spec 019, FR-003d).

## Referências do sistema antigo *(Princípios VII e VIII)*

- Backend: `app/Http/Controllers/FiliadosController.php`, `app/Models/Filiados.php`,
  `app/Mail/FiliadoMail.php`, `resources/views/emails/filiado/cadastro.blade.php`, trecho de login
  de afiliado em `app/Http/Controllers/AuthController.php`, trecho do código de indicação em
  `app/Http/Controllers/CadastroController.php`, bônus por afiliado em
  `app/Http/Controllers/BonusController.php` e `app/Console/Commands/MPCommand.php`.
- Migrations: `create_filiados_table`, `add_filiados_id_creditos_table`,
  `add_usuarios_id_filiados_table`, `add_email_filiados_table`.
- Rotas: `resource filiados`, `GET get-filiado/{id}` (pública), `POST prestar-contas-filiado`.
- Telas de referência visual: `screens/new-affiliated` (página pública `/cadastro-afiliado`),
  `modals/affiliated` (modal de cadastro público), `screens/affiliated` (painel do afiliado
  `/afiliados`), `modals/login` (entrada). As telas `screens/adm/affiliated` e
  `screens/adm/modals/affiliated` servem só de referência de regras, pois a gestão nesta spec é só
  API.

**Como funcionava**: tabela `filiados` com login, senha, comissão (float, %), tipo de ganho, saldo
anterior e data da última prestação. O cliente ficava ligado pelo `creditos.filiados_id`,
informando o login do afiliado como código. A comissão era calculada em SQL a cada consulta, sobre
os depósitos e saques posteriores à última prestação. A prestação gravava só o último saldo e a
nova data. O login do afiliado não gerava token: o id ficava no navegador e o painel lia uma rota
pública.

**Problemas corrigidos por esta spec**: rota pública `get-filiado/{id}` expondo dados de qualquer
afiliado; login sem token; cadastro público que nunca funcionou (rota protegida e `auth=no` que
enviava e-mail mesmo assim); gerente escolhido sobrescrito no cadastro; JOIN errado
(`creditos.id = ct.id`) nos totais de depósitos e saques; "Primeiro depósito" pago de novo a cada
período; saldo da prestação e da troca de tipo de ganho calculado com um cliente só; checagem de
CPF repetido que nunca encontrava repetição; falta de histórico de prestações; data da prestação
livre no passado; SQL concatenado com dados da requisição; valores em float; paginação de 250;
situação "ativo" aceita do formulário.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Cadastrar afiliado pelo painel (Priority: P1)

Admin, Supervisor ou Gerente cadastra um afiliado pela API informando os dados pessoais, o login,
a senha, a chave PIX e a regra de comissão (tipo de ganho e percentual). O afiliado nasce ativo,
com um código público de indicação gerado pelo sistema. Quando quem cadastra é um Gerente, ele
fica como responsável; Admin e Supervisor podem escolher um gerente responsável ou deixar sem.

**Why this priority**: sem afiliado cadastrado não existe indicação, comissão nem painel; é a base
de todas as outras histórias.

**Independent Test**: cadastrar um afiliado pela API e consultá-lo, conferindo situação Ativo,
código gerado, regra de comissão vigente e gerente responsável.

**Acceptance Scenarios**:

1. **Given** um Admin com `afiliados.cadastrar`, **When** cadastra um afiliado com todos os campos
   válidos, tipo "Qualquer depósito" e 10%, **Then** o afiliado fica Ativo, com origem Painel,
   código público único e regra de comissão vigente desde o cadastro.
2. **Given** um Gerente com `afiliados.cadastrar`, **When** cadastra um afiliado informando outro
   gerente como responsável, **Then** o responsável gravado é o próprio Gerente que cadastrou.
3. **Given** um Gerente sem `afiliados.editar_comissao`, **When** cadastra um afiliado informando
   tipo de ganho e percentual, **Then** o afiliado recebe a regra padrão da configuração; com a
   permissão, recebe a regra informada.
4. **Given** um login já usado por um usuário do painel ou por outro afiliado, **When** o cadastro
   é enviado, **Then** é recusado com erro no campo login.
5. **Given** um CPF inválido ou já usado por outro afiliado, **When** o cadastro é enviado,
   **Then** é recusado com erro no campo CPF.
6. **Given** um Vendedor, **When** tenta cadastrar um afiliado, **Then** recebe "sem permissão".

---

### User Story 2 - Cadastro público com aprovação (Priority: P1)

Um visitante abre `/cadastro-afiliado` (ou o link "Afiliados" do rodapé), lê a apresentação "Seja
um afiliado!" e preenche o modal de cadastro. O cadastro entra como "Em análise" e o visitante
recebe um e-mail de recebimento. Um Admin ou Supervisor aprova ou recusa pela API; o afiliado
recebe um e-mail de aprovação (com o endereço para entrar) ou de recusa.

**Why this priority**: o cadastro público é a principal novidade pedida e nunca funcionou no
sistema antigo.

**Independent Test**: cadastrar pela página pública, conferir a situação "Em análise" e o e-mail;
aprovar pela API e conferir o e-mail de aprovação e o login liberado.

**Acceptance Scenarios**:

1. **Given** um visitante na página pública, **When** envia o cadastro com dados válidos e senhas
   iguais, **Then** o afiliado é criado como "Em análise", origem Público, sem gerente, com a regra
   de comissão padrão da configuração, e a tela mostra que o cadastro será analisado.
2. **Given** o formulário público, **When** a requisição tenta enviar situação, gerente, tipo de
   ganho ou percentual, **Then** esses campos são ignorados.
3. **Given** um campo inválido (ex.: e-mail já usado), **When** o cadastro é enviado, **Then** o
   modal mostra o erro embaixo do campo e mantém os demais dados preenchidos.
4. **Given** um afiliado "Em análise", **When** um Admin o aprova, **Then** ele passa a Ativo e
   recebe o e-mail de aprovação.
5. **Given** um afiliado "Em análise", **When** um Supervisor o recusa informando um motivo,
   **Then** ele passa a Recusado, recebe o e-mail de recusa e não consegue entrar.
6. **Given** um Gerente, **When** tenta aprovar ou recusar um cadastro público, **Then** recebe
   "sem permissão".

---

### User Story 3 - Entrar pela mesma tela dos demais usuários (Priority: P1)

O afiliado entra pela mesma tela de entrada do site (o modal "Entrar", usado por clientes e
usuários do painel), informando login e senha. O botão "Entrar" da página pública de afiliados
abre esse mesmo modal. Ao entrar, ele vai para `/afiliados`.

**Why this priority**: sem login seguro não há painel do afiliado; corrige a falha mais grave do
antigo (sessão sem token).

**Independent Test**: entrar com um afiliado ativo pelo modal do site e cair em `/afiliados`;
tentar com afiliado em análise, recusado e inativo e ser barrado.

**Acceptance Scenarios**:

1. **Given** um afiliado Ativo, **When** entra com login e senha corretos no modal do site,
   **Then** recebe um token próprio de afiliado e é levado a `/afiliados`.
2. **Given** um afiliado "Em análise", **When** tenta entrar, **Then** vê "Seu cadastro ainda está
   em análise." e não recebe token.
3. **Given** um afiliado Recusado ou Inativo, **When** tenta entrar, **Then** vê que o acesso não
   está liberado e não recebe token.
4. **Given** login ou senha errados, **When** tenta entrar, **Then** vê a mesma mensagem de erro
   genérica dos demais usuários, e as tentativas seguem o mesmo limite.
5. **Given** um token de afiliado, **When** é usado em rotas do painel ou da área do cliente,
   **Then** é recusado; e tokens do painel ou de cliente não acessam a área do afiliado.
6. **Given** um afiliado Ativo que esqueceu a senha, **When** pede "Esqueci minha senha" com o
   e-mail dele, recebe o código e informa o código e a nova senha duas vezes, **Then** a senha é
   trocada, os tokens antigos deixam de valer e ele entra com a senha nova.
7. **Given** um e-mail que não pertence a nenhum afiliado, **When** pede a recuperação, **Then** a
   resposta é a mesma de um e-mail existente, e nenhum código é enviado.

---

### User Story 4 - Indicação de clientes pelo link (Priority: P1)

O afiliado divulga o link `?user=CODIGO`. O visitante que abre o link e se cadastra como cliente
fica ligado ao afiliado. As promoções criadas para um afiliado valem só para os clientes dele.

**Why this priority**: a ligação cliente → afiliado é o que gera comissão.

**Independent Test**: abrir o site com `?user=CODIGO`, cadastrar um cliente e conferir a ligação;
conferir que uma promoção do afiliado vale para esse cliente.

**Acceptance Scenarios**:

1. **Given** o link de um afiliado Ativo, **When** o visitante se cadastra, **Then** o cliente fica
   ligado ao afiliado.
2. **Given** o código digitado em maiúsculas, minúsculas ou com espaços nas pontas, **When** o
   cadastro é enviado, **Then** o código é reconhecido do mesmo jeito.
3. **Given** um código que não existe, **When** o cadastro é enviado, **Then** é recusado com
   "Código de afiliado não encontrado." no campo.
4. **Given** o código de um afiliado não Ativo (em análise, recusado, inativo ou excluído),
   **When** o cadastro é enviado, **Then** o cliente é cadastrado sem ligação com afiliado.
5. **Given** um afiliado migrado do sistema antigo, **When** alguém usa o link antigo com o login
   dele, **Then** o cadastro é ligado a esse afiliado.
6. **Given** uma promoção do afiliado e uma geral vigentes, **When** um cliente do afiliado
   deposita, **Then** a regra de escolha de promoção da spec 007 continua valendo, agora pela
   ligação com o afiliado.

---

### User Story 5 - Comissão gerada a cada movimento do cliente (Priority: P1)

Cada depósito aprovado, saque pago ou estorno de depósito de um cliente ligado gera um lançamento
no extrato do afiliado, com a base, o percentual e a comissão gravados no momento, conforme a regra
de comissão vigente.

**Why this priority**: é o dinheiro do afiliado; corrige os cálculos errados do antigo.

**Independent Test**: com afiliados de cada tipo de ganho, aprovar depósitos, pagar saques e
estornar depósitos de clientes ligados, conferindo os lançamentos e o valor a receber.

**Acceptance Scenarios**:

1. **Given** um afiliado "Qualquer depósito" com 10%, **When** um cliente dele tem um depósito de
   R$ 100,00 aprovado, **Then** é gerado um lançamento de + R$ 10,00.
2. **Given** um afiliado "Primeiro depósito" com 20%, **When** um cliente faz o primeiro depósito
   aprovado de R$ 50,00 e depois outros, **Then** só o primeiro gera lançamento (+ R$ 10,00), mesmo
   depois de uma prestação de contas.
3. **Given** um afiliado "Saldo líquido" com 30%, **When** um cliente deposita R$ 200,00 e saca
   R$ 50,00 (saque pago), **Then** são gerados + R$ 60,00 e − R$ 15,00.
4. **Given** um afiliado "Qualquer depósito" ou "Saldo líquido", **When** um depósito que gerou
   comissão é estornado total ou parcialmente, **Then** é gerado um lançamento negativo
   proporcional ao valor estornado, com o mesmo percentual do lançamento original.
5. **Given** um afiliado "Qualquer depósito" ou "Primeiro depósito", **When** um cliente dele tem
   um saque pago, **Then** nenhum lançamento é gerado.
6. **Given** a regra trocada de 10% para 15% hoje, **When** novos depósitos são aprovados,
   **Then** usam 15%, e os lançamentos anteriores continuam com 10%.
7. **Given** um depósito pendente, recusado ou expirado, ou um saque recusado, **When** a situação
   muda, **Then** nenhum lançamento é gerado.

---

### User Story 6 - Prestação de contas (Priority: P1)

Admin, Supervisor ou o Gerente responsável presta contas com o afiliado pela API, confirmando com
a própria senha e, se quiser, uma observação. A prestação fecha todos os lançamentos em aberto,
zera o valor a receber e fica no histórico.

**Why this priority**: é como o afiliado é pago; o antigo não guardava histórico.

**Independent Test**: com lançamentos em aberto, prestar contas e conferir o histórico, o valor a
receber zerado e os lançamentos fechados; repetir com saldo negativo e sem lançamentos.

**Acceptance Scenarios**:

1. **Given** lançamentos em aberto somando R$ 85,00, **When** a prestação é feita com a senha
   correta, **Then** é gravada com valor apurado R$ 85,00, valor pago R$ 85,00, período, autor,
   data e observação, e o valor a receber passa a R$ 0,00.
2. **Given** lançamentos em aberto somando − R$ 40,00, **When** a prestação é feita, **Then** é
   gravada com valor apurado − R$ 40,00 e valor pago R$ 0,00, e o próximo período começa do zero.
3. **Given** nenhum lançamento em aberto, **When** a prestação é feita, **Then** é gravada com
   valores zerados.
4. **Given** a senha errada, **When** a prestação é enviada, **Then** é recusada e nada muda.
5. **Given** um Gerente que não é o responsável pelo afiliado, **When** tenta prestar contas,
   **Then** recebe "sem permissão" (ou "não encontrado", como nas demais consultas fora do alcance).
6. **Given** duas prestações enviadas ao mesmo tempo para o mesmo afiliado, **When** são
   processadas, **Then** os lançamentos ficam em uma só prestação, e a outra sai sem lançamentos
   ou é recusada, nunca pagando duas vezes.

---

### User Story 7 - Painel do afiliado (Priority: P2)

O afiliado vê, em `/afiliados`, com o visual do antigo: "Olá, nome", o valor a receber, o valor da
última prestação, a regra de comissão vigente e a lista do extrato. Além disso: filtro de período
no extrato, lista de clientes indicados, histórico de prestações, botão "copiar meu link", "Meus
dados" (contato e PIX), troca de senha e sair.

**Why this priority**: é a tela que o afiliado usa; depende das histórias P1 para ter dados.

**Independent Test**: entrar como afiliado e conferir cada bloco do painel com os dados gerados nas
histórias 5 e 6.

**Acceptance Scenarios**:

1. **Given** um afiliado com lançamentos em aberto, **When** abre o painel, **Then** vê o valor a
   receber, a última prestação, o texto "Sua comissão é de X% em cima de <tipo>" e o extrato.
2. **Given** o filtro de período, **When** escolhe datas, **Then** o extrato mostra só os
   lançamentos do período, paginado.
3. **Given** clientes indicados, **When** abre a lista, **Then** vê o nome parcialmente oculto, a
   data do cadastro e o total depositado de cada um, sem telefone, e-mail ou CPF.
4. **Given** o botão "copiar meu link", **When** clica, **Then** o link com o código dele é
   copiado e um aviso confirma a cópia.
5. **Given** "Meus dados", **When** altera telefone, cidade, estado, endereço ou PIX, **Then** os
   dados são salvos; nome, login, CPF, e-mail e regra de comissão não podem ser alterados por ele.
6. **Given** "Alterar senha", **When** informa a senha atual e a nova duas vezes, **Then** a senha
   muda e as outras sessões dele deixam de valer.
7. **Given** alguém sem token de afiliado, **When** abre `/afiliados`, **Then** é levado para a
   página inicial com o modal de entrada aberto.
8. **Given** um afiliado, **When** tenta ver dados de outro afiliado, **Then** não existe rota que
   permita isso.

---

### User Story 8 - Gerir afiliados pela API (Priority: P2)

Admin, Supervisor e Gerente listam, consultam, editam, ativam/inativam, excluem e restauram
afiliados, consultam o extrato, os clientes indicados, o histórico de prestações e as regras de
comissão de cada um, e trocam a regra de comissão.

**Why this priority**: operação do dia a dia da banca; a parte de cadastro e prestação já está nas
P1.

**Independent Test**: com afiliados em várias situações, usar cada filtro e ação da API.

**Acceptance Scenarios**:

1. **Given** afiliados em várias situações, **When** a listagem é pedida com filtro de situação,
   busca por nome, login ou código, gerente e período, **Then** volta paginada (no máximo 100 por
   página) com cadastros, depósitos, saques e comissão do período, valor a receber e última
   prestação de cada um.
2. **Given** um Gerente, **When** lista ou consulta afiliados, **Then** só vê aqueles de quem é
   responsável.
3. **Given** um afiliado Ativo, **When** é inativado, **Then** os tokens dele deixam de valer na
   hora e ele não consegue entrar; os clientes continuam ligados e gerando lançamentos.
4. **Given** um afiliado, **When** um Admin o exclui, **Then** ele vai para a lixeira, os dados
   únicos recebem o sufixo de exclusão, os tokens deixam de valer e o código deixa de ligar novos
   clientes.
5. **Given** um afiliado na lixeira, **When** é restaurado e algum dado único já está em uso,
   **Then** a restauração é recusada indicando o campo a alterar.
6. **Given** um Gerente, **When** tenta excluir ou restaurar, **Then** recebe "sem permissão".
7. **Given** a troca de regra de comissão, **When** é salva, **Then** a regra anterior é encerrada
   naquele momento e a nova vale para os lançamentos seguintes, sem exigir saldo zerado.

---

### Edge Cases

- **Login igual entre usuário do painel e afiliado**: proibido nos dois sentidos; o cadastro de
  usuário do painel também passa a conferir os logins dos afiliados.
- **Login que parece telefone**: a tela de entrada tenta painel, afiliado e cliente na ordem já
  usada; um login de afiliado só com dígitos continua funcionando.
- **Depósito aprovado de cliente ligado a afiliado inativo**: o lançamento é gerado; inativar
  bloqueia só o acesso.
- **Depósito aprovado de cliente ligado a afiliado excluído**: nenhum lançamento é gerado
  enquanto o afiliado estiver na lixeira.
- **Estorno de depósito cujo lançamento já foi prestado**: o lançamento negativo entra no período
  em aberto e pode deixá-lo negativo, que é zerado na próxima prestação.
- **Estorno de depósito que não gerou comissão** (ex.: depósito que não era o primeiro no tipo
  Primeiro depósito): nenhum lançamento.
- **Cliente excluído**: os lançamentos já gerados continuam; não há novos.
- **Troca de gerente responsável**: o novo gerente passa a ver e prestar contas de todo o saldo em
  aberto.
- **Prestação com afiliado na lixeira**: não permitida; é preciso restaurar antes.
- **Arredondamento**: a comissão é arredondada para 2 casas (meio para cima) em cada lançamento.
- **Percentual zero**: permitido; os lançamentos são gerados com valor zero e aparecem no extrato.
- **Código público**: único, sem diferenciar maiúsculas e minúsculas, com letras, números, ponto,
  hífen e sublinhado; não
  pode ser trocado pelo afiliado.
- **Afiliados migrados sem e-mail ou CPF**: continuam válidos; esses campos só são obrigatórios em
  cadastros novos.

## Requirements *(mandatory)*

### Functional Requirements

**Cadastro e dados**

- **FR-001**: O sistema DEVE guardar os afiliados em cadastro próprio, separado dos usuários do
  painel e dos clientes, com: nome, login, senha, e-mail, CPF, DDI (padrão 55), telefone, cidade,
  estado, endereço, código público, situação, origem do cadastro, gerente responsável (opcional) e
  data a partir da qual os tokens valem.
- **FR-002**: O login DEVE ser único entre afiliados e usuários do painel. E-mail, CPF e código
  público DEVEM ser únicos entre afiliados.
- **FR-003**: O CPF DEVE ser validado pelos dígitos verificadores; a checagem de repetição DEVE
  comparar o valor normalizado (só dígitos).
- **FR-004**: A senha DEVE seguir a regra dos clientes (mínimo de 8 caracteres, com letra e número)
  e ser digitada duas vezes no cadastro e na troca.
- **FR-005**: A chave PIX DEVE ter tipo (CPF, CNPJ, E-mail, Telefone ou Chave aleatória) e chave validada
  conforme o tipo, guardada como meio de pagamento do afiliado, no padrão dos meios de pagamento do
  cliente.
- **FR-006**: O código público DEVE ser gerado pelo sistema no cadastro (único, 8 letras maiúsculas
  e números) e reconhecido sem diferenciar maiúsculas e minúsculas. Admin e Supervisor PODEM
  alterá-lo pela API, respeitando a unicidade, com letras, números, ponto, hífen e sublinhado (de 4
  a 50 caracteres), para caber o login antigo dos afiliados migrados.
- **FR-007**: A situação DEVE ser uma de: Em análise, Ativo, Inativo, Recusado. A origem DEVE ser
  Painel ou Público.

**Cadastro pelo painel**

- **FR-008**: Admin, Supervisor e Gerente com `afiliados.cadastrar` DEVEM poder cadastrar
  afiliados pela API, que nascem Ativos e com origem Painel; e-mail e CPF são obrigatórios em
  cadastros novos (só os migrados podem não ter). A regra de comissão informada só vale
  para quem tem `afiliados.editar_comissao`; sem ela, o afiliado recebe a regra padrão da
  configuração (FR-015) e os campos de regra enviados são ignorados.
- **FR-009**: Quando quem cadastra é Gerente, o responsável DEVE ser ele mesmo, ignorando o valor
  enviado. Admin e Supervisor PODEM informar um gerente responsável (que deve ter a função Gerente)
  ou deixar sem.

**Cadastro público**

- **FR-010**: O sistema DEVE oferecer o cadastro público em `/cadastro-afiliado`, sem login, com os
  campos nome, login, senha e confirmação, e-mail, CPF, telefone, cidade, estado, endereço e PIX
  (tipo e chave). E-mail e CPF são obrigatórios nesse caminho.
- **FR-011**: O cadastro público DEVE criar o afiliado como "Em análise", origem Público, sem
  gerente, com a regra de comissão padrão da configuração. Situação, gerente, tipo de ganho e
  percentual enviados por esse caminho DEVEM ser ignorados.
- **FR-012**: O cadastro público DEVE seguir o mesmo limite de tentativas por IP usado no cadastro
  público de clientes.
- **FR-013**: O sistema DEVE enviar e-mail ao afiliado no cadastro público (recebido, em análise),
  na aprovação (com o endereço para entrar) e na recusa (com o motivo, se informado). A falha no
  envio NÃO DEVE desfazer o cadastro nem a decisão.
- **FR-013a**: O cliente que informar e-mail no cadastro DEVE
  receber um e-mail de boas-vindas com o nome dele, a logo da banca e o endereço do site, no visual
  dos e-mails do antigo. Sem e-mail, nada é enviado. A falha no envio NÃO DEVE desfazer o cadastro
  nem impedir a entrada do cliente.
- **FR-014**: Só Admin e Supervisor com `afiliados.alterar_situacao` DEVEM poder aprovar ou recusar
  um afiliado "Em análise"; um afiliado "Recusado" também PODE ser aprovado depois (reavaliação),
  mas não recusado de novo. Na aprovação, PODEM informar gerente responsável e regra de comissão;
  sem isso, ficam os atuais.

**Configuração**

- **FR-015**: A configuração geral DEVE ter a regra de comissão padrão do cadastro público (tipo de
  ganho e percentual), editável por quem tem `configuracoes.editar`. O padrão inicial é "Saldo
  líquido" com 0%.

**Login e sessão**

- **FR-016**: O afiliado DEVE entrar com login e senha pela mesma tela de entrada do site. A tela
  DEVE tentar, na ordem, usuário do painel, afiliado e cliente, mantendo a regra atual de tentar
  cliente primeiro quando o texto parece telefone.
- **FR-017**: O login do afiliado DEVE gerar um token de acesso próprio, que só vale na área do
  afiliado. Tokens do painel e de cliente NÃO DEVEM valer na área do afiliado, e vice-versa.
- **FR-018**: Só afiliados Ativos e fora da lixeira DEVEM conseguir entrar. Em análise, Recusado e
  Inativo DEVEM receber mensagem própria, sem token.
- **FR-019**: O login do afiliado DEVE seguir o mesmo limite de tentativas falhas do login do
  painel.
- **FR-020**: Inativar, excluir ou trocar a senha DEVE invalidar na hora os tokens já emitidos para
  o afiliado (na troca de senha pelo próprio afiliado, a sessão atual recebe um token novo).
- **FR-021**: Após entrar, o afiliado DEVE ser levado a `/afiliados`. O botão "Entrar" da página
  pública de afiliados DEVE abrir o mesmo modal de entrada do site.
- **FR-021a**: O afiliado DEVE poder recuperar a senha por "Esqueci minha senha", na página pública
  de afiliados: informa o login ou o e-mail, recebe por e-mail um código de 6 dígitos válido por 15
  minutos e informa o código e a nova senha duas vezes (regra do FR-004). Um pedido novo invalida
  o código anterior; 5 tentativas erradas invalidam o código; a resposta ao pedido NÃO DEVE revelar
  se o login ou o e-mail existe; o pedido segue o mesmo limite de tentativas da recuperação do
  cliente. Só afiliados Ativos recebem o código. Trocar a senha por recuperação invalida os tokens
  já emitidos.

**Indicação**

- **FR-022**: O link de indicação DEVE ser `?user=CODIGO`, como no antigo. O código DEVE chegar ao
  cadastro de cliente pelo campo "Código de afiliado" já existente.
- **FR-023**: No cadastro de cliente, código inexistente DEVE recusar o cadastro com "Código de
  afiliado não encontrado."; código de afiliado não Ativo (Em análise, Recusado, Inativo) ou na
  lixeira DEVE cadastrar o cliente sem ligação. Para isso, o código de um afiliado na lixeira DEVE
  continuar sendo reconhecido pelo valor original, sem o sufixo de exclusão.
- **FR-024**: A ligação do cliente com o afiliado DEVE passar a ser por referência ao cadastro do
  afiliado, no lugar do texto livre atual; o mesmo DEVE valer para o afiliado das promoções. Os
  dados existentes DEVEM ser convertidos pelo código; textos sem afiliado correspondente ficam sem
  ligação.
- **FR-025**: As regras de promoção por afiliado da spec 007 (FR-018g e a escolha da promoção no
  cadastro, depósito e indicação) DEVEM continuar iguais, passando a usar a nova ligação.
- **FR-026**: A ligação do cliente com o afiliado NÃO DEVE ser alterada pelo cliente. A gestão de
  clientes PODE filtrar clientes pelo afiliado.

**Comissão**

- **FR-027**: Cada afiliado DEVE ter uma regra de comissão vigente, com tipo de ganho (Saldo
  líquido, Qualquer depósito ou Primeiro depósito), percentual de 0 a 100 com até 2 casas e data de
  início. As regras anteriores DEVEM ficar guardadas com início e fim.
- **FR-028**: Trocar a regra (permissão `afiliados.editar_comissao`) DEVE encerrar a vigente naquele momento
  e começar a nova, sem exigir saldo zerado e sem recalcular lançamentos já gerados.
- **FR-029**: Para clientes ligados a afiliado fora da lixeira (Ativo, Inativo, Em análise ou
  Recusado), o sistema DEVE gerar lançamentos no momento do evento, usando a regra vigente nesse
  momento; afiliado na lixeira não gera lançamentos:
  - **Qualquer depósito**: cada depósito aprovado gera + percentual × valor do depósito;
  - **Primeiro depósito**: só o primeiro depósito aprovado do cliente em toda a vida da conta gera
    + percentual × valor; depósitos seguintes não geram, nem depois de prestações;
  - **Saldo líquido**: cada depósito aprovado gera + percentual × valor; cada saque pago gera −
    percentual × valor.
- **FR-030**: Estorno total ou parcial de um depósito que gerou lançamento DEVE gerar um lançamento
  negativo com o mesmo percentual do original, sobre o valor estornado.
- **FR-031**: A base da comissão de depósito DEVE ser o valor do depósito creditado ao cliente (sem
  taxas do meio de pagamento); a de saque, o valor do saque pago.
- **FR-032**: Cada lançamento DEVE guardar: afiliado, cliente, evento de origem (depósito, saque ou
  estorno), tipo de ganho, base, percentual, valor da comissão (2 casas, arredondamento meio para
  cima), data do evento e a prestação que o fechou (vazia enquanto em aberto).
- **FR-033**: Um mesmo evento NÃO DEVE gerar mais de um lançamento, mesmo que a confirmação do
  depósito ou do saque chegue repetida.
- **FR-034**: Todos os valores em dinheiro DEVEM ser decimais exatos, nunca ponto flutuante.

**Prestação de contas**

- **FR-035**: Admin, Supervisor ou o Gerente responsável, com `afiliados.prestar_contas`, DEVEM
  poder prestar contas pela API, confirmando com a própria senha e com observação opcional.
- **FR-036**: A prestação DEVE fechar todos os lançamentos em aberto do afiliado até o momento da
  prestação. Não há pagamento parcial nem data escolhida.
- **FR-037**: A prestação DEVE gravar: afiliado, valor apurado (soma dos lançamentos fechados, pode
  ser negativo), valor pago (o apurado quando positivo; zero quando negativo ou zero), início do
  período (fim da prestação anterior ou cadastro do afiliado), fim do período (momento da
  prestação), quantidade de lançamentos, usuário que prestou, data e observação.
- **FR-038**: Saldo negativo DEVE ser zerado na prestação e não passar para o período seguinte.
- **FR-039**: A prestação DEVE ser permitida mesmo sem lançamentos em aberto, gravando valores
  zero.
- **FR-040**: Duas prestações simultâneas do mesmo afiliado NÃO DEVEM fechar o mesmo lançamento nem
  pagar duas vezes.
- **FR-041**: Prestações NÃO DEVEM ser editadas nem excluídas.
- **FR-042**: Não DEVE existir saque solicitado pelo afiliado.

**Gestão pela API**

- **FR-043**: A listagem DEVE ser paginada (padrão 20, no máximo 100 por página), com filtros por
  situação (Em análise, Ativo, Inativo, Recusado) ou lixeira, busca por nome, login ou código,
  gerente responsável e período, ordenada por nome.
- **FR-044**: Cada item da listagem DEVE trazer (formato no `contracts/api.md` §2.1): id, nome,
  login, código, e-mail, CPF, telefone, origem, situação, gerente, regra
  vigente, quantidade de clientes cadastrados no período, total de depósitos aprovados e de saques
  pagos dos clientes no período, comissão gerada no período, valor a receber (lançamentos em
  aberto) e data e valor da última prestação.
- **FR-045**: A API DEVE permitir consultar um afiliado (`afiliados.consultar`), editar os dados
  (`afiliados.editar`), trocar a regra (`afiliados.editar_comissao`), ativar e inativar (`afiliados.alterar_situacao`), consultar o extrato
  de lançamentos com filtro de período e de aberto/prestado, os clientes indicados, o histórico de
  prestações e o histórico de regras. Todas as listagens são paginadas (no máximo 100).
- **FR-046**: Na gestão, CPF, telefone, e-mail e chave PIX DEVEM sair mascarados para quem não tem
  `clientes.ver_dados_completos`, como nos clientes.
- **FR-047**: A senha do afiliado PODE ser redefinida pela API por quem tem `afiliados.editar`,
  seguindo a regra de senha (tokens invalidados pelo FR-020).
- **FR-048**: Excluir DEVE ser lógico (lixeira), por Admin ou Supervisor com `afiliados.excluir`;
  login, e-mail, CPF e código recebem o sufixo de exclusão, como nos clientes. Não DEVE existir
  exclusão definitiva.
- **FR-049**: Restaurar, por Admin ou Supervisor com `afiliados.restaurar`, DEVE recusar quando
  algum dado único já estiver em uso, indicando o campo a alterar.
- **FR-050**: Gerente DEVE ver e agir só sobre os afiliados de quem é responsável; Admin e
  Supervisor, sobre todos. Afiliado fora do alcance DEVE ser tratado como inexistente.

**Permissões**

- **FR-051**: DEVEM existir as permissões `afiliados.listar`, `afiliados.consultar`,
  `afiliados.cadastrar`, `afiliados.editar`, `afiliados.alterar_situacao`, `afiliados.excluir`,
  `afiliados.restaurar`, `afiliados.prestar_contas` e `afiliados.editar_comissao`, no padrão das
  demais (`Funcao`). Admin e Supervisor recebem todas; Gerente recebe e usa todas menos excluir,
  restaurar e editar comissão; `afiliados.editar_comissao` não vem por padrão para o Gerente, mas
  ele pode usá-la quando receber; Vendedor não recebe nem usa nenhuma. Aprovar e recusar cadastros públicos é só de Admin e Supervisor, mesmo
  com `afiliados.alterar_situacao`.

**Painel do afiliado (frontend)**

- **FR-052**: A página pública `/cadastro-afiliado` DEVE ter o visual da tela `new-affiliated` do
  antigo (cabeçalho com logo e botões Cadastrar-se e Entrar, texto "Seja um afiliado!", botão
  "Quero me afiliar" e imagem), e o link "Afiliados" do rodapé DEVE levar a ela.
- **FR-053**: O modal de cadastro público DEVE manter os campos e o visual do modal `affiliated`
  do antigo, com as melhorias aprovadas: rótulos, erro por campo, máscaras (telefone, CPF e PIX
  conforme o tipo), confirmação de senha, mostrar/ocultar senha, Esc e Voltar fecham, carregando no
  botão e mensagem final de "cadastro em análise".
- **FR-054**: O painel `/afiliados` DEVE manter o visual da tela `affiliated` do antigo (cabeçalho
  "Olá, nome", "Meu Saldo", "Saldo anterior" renomeado como última prestação, texto da comissão e
  tabela do extrato com ID, operação, valor, comissão e data) e acrescentar: filtro de período no
  extrato, clientes indicados, histórico de prestações, botão "copiar meu link", "Meus dados",
  "Alterar senha" e "Sair".
- **FR-055**: O afiliado DEVE ver só os próprios dados. Nos clientes indicados, DEVE ver o nome
  parcialmente oculto, a data do cadastro e o total depositado, nunca telefone, e-mail ou CPF.
- **FR-056**: Em "Meus dados", o afiliado DEVE poder alterar telefone, cidade, estado, endereço e
  PIX; nome, login, e-mail, CPF, código e regra de comissão só pela gestão.
- **FR-057**: Os modais do painel ("Meus dados", "Alterar senha") e o de "Esqueci minha senha" da
  página pública DEVEM seguir o mesmo padrão do
  FR-053. A troca de senha DEVE pedir a senha atual.
- **FR-058**: Quem abre `/afiliados` sem sessão de afiliado válida DEVE ser levado à página
  inicial com o modal de entrada aberto; token expirado DEVE encerrar a sessão do mesmo jeito.

**Documentação**

- **FR-059**: A coleção do Postman DEVE ser regenerada com todas as rotas novas e alteradas.

### Key Entities

- **Afiliado** (`afiliados`): parceiro que indica clientes e recebe comissão. Tem dados pessoais,
  login e senha próprios, código público, situação, origem e, opcionalmente, um gerente
  responsável (usuário do painel). Liga-se a clientes, regras de comissão, lançamentos, prestações
  e meio de pagamento.
- **Regra de comissão do afiliado** (`afiliados_comissoes`): tipo de ganho, percentual e
  vigência (início e fim). Só uma vigente por vez.
- **Lançamento do afiliado** (`afiliados_lancamentos`): linha do extrato gerada por um depósito
  aprovado, saque pago ou estorno de cliente ligado, com base, percentual e comissão gravados no
  momento e a prestação que o fechou.
- **Prestação de contas do afiliado** (`afiliados_prestacoes_contas`): acerto que fecha os
  lançamentos em aberto, com valor apurado, valor pago, período, autor, data e observação.
- **Meio de pagamento do afiliado** (`afiliados_meios_pagamento`): chave PIX do afiliado, no
  padrão de `clientes_meios_pagamento`.
- **Código de recuperação do afiliado** (`afiliados_codigos_recuperacao`): código de "Esqueci
  minha senha", com tentativas, validade, uso e invalidação, no padrão de
  `clientes_codigos_recuperacao`.
- **Cliente** (existente): passa a se ligar ao afiliado por referência (`afiliados_id`) no lugar do
  `codigo_afiliado` em texto.
- **Promoção** (existente, `clientes_promocoes`): passa a se ligar ao afiliado por referência
  (`afiliados_id`) no lugar do `codigo_afiliado` em texto.
- **Configuração** (existente): ganha a regra de comissão padrão do cadastro público.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Um visitante conclui o cadastro público de afiliado em menos de 3 minutos.
- **SC-002**: 100% das tentativas de ver dados de um afiliado sem o token dele (ou com token de
  outro afiliado, do painel ou de cliente) são recusadas.
- **SC-003**: Em uma conferência manual com pelo menos 20 movimentos de clientes (depósitos, saques
  e estornos) nos três tipos de ganho, 100% dos lançamentos batem com o cálculo feito à mão,
  centavo a centavo.
- **SC-004**: Nenhum cliente gera mais de um lançamento de "Primeiro depósito", em qualquer
  quantidade de prestações.
- **SC-005**: Depois de cada prestação, o valor a receber do afiliado é R$ 0,00 e a prestação
  aparece no histórico, inclusive as de valor zero ou negativo.
- **SC-006**: 100% dos links antigos `?user=LOGIN` de afiliados migrados continuam ligando novos
  clientes ao afiliado certo. Nesta spec, verificado com um afiliado cadastrado pela API com o
  código igual a um login antigo (ex.: `maria.souza`); a conferência com os dados reais fica para a
  spec de importação dos afiliados.
- **SC-007**: O painel do afiliado abre com os dados em até 2 segundos para afiliados com até
  10.000 lançamentos.
- **SC-008**: Nenhuma listagem de afiliados, lançamentos, clientes indicados ou prestações devolve
  mais de 100 registros por página.

## Assumptions

- A gestão de afiliados pelo painel é só API nesta spec; a tela do painel administrativo fica para
  uma spec futura.
- Supervisor vê e age sobre todos os afiliados, como nas demais regras de gestão geral
  (`AlcanceHierarquia::e_gestor_geral`); só o Gerente é limitado aos seus.
- O cadastro público não tem PIN nem gerente; a banca define o responsável na aprovação ou depois.
- O e-mail usa a configuração de envio do Laravel já existente no projeto; o texto segue o do
  `FiliadoMail` antigo, com a mensagem de recusa acrescentada.
- Os afiliados do sistema antigo serão importados por um processo de migração de dados fora desta
  spec. Esta spec garante que o código aceita o login antigo (FR-006) e que e-mail, CPF e
  telefone podem faltar nos migrados (obrigatórios só nos cadastros novos).
- Dado fake usado (Princípio IX): a cor principal do tema (`DadosFake::tema()`, spec 005) aparece
  nas páginas e no botão dos e-mails. Não participa de operações com dinheiro e será substituída
  pela spec de configurações do tema, sem mudar os componentes nem os e-mails (que leem a cor de um
  ponto único).
- A conversão de `clientes.codigo_afiliado` e `clientes_promocoes.codigo_afiliado` para
  referência ao afiliado atende à regra da constituição sobre colunas criadas sem chave
  estrangeira (specs 002 e 007).
- O cadastro de usuário do painel (spec 001) passa a recusar logins já usados por afiliados; é uma
  alteração necessária fora desta feature e será indicada no plano antes de ser feita
  (Princípio IV).
- Lançamentos são gerados a partir dos fluxos de depósito, saque e estorno já existentes (spec
  007), sem alterar o comportamento desses fluxos para o cliente.
- Comissão sobre o resultado das apostas e do cassino (GGR), saque pelo afiliado, exclusão
  definitiva, tela administrativa e confirmação de cadastro do cliente por link estão fora do
  escopo.
