# Feature Specification: Painel — Clientes (cadastros, saques, ranking, lixeira, exportação e notificações)

**Feature Branch**: `019-painel-clientes`

**Created**: 2026-10-22

**Status**: Draft

**Input**: User description: "Spec 019: tela 'Clientes' no painel (a antiga 'Créditos'), backend e
frontend. O backend de gestão de clientes existe desde a spec 002: listar com busca, filtros e
ordenação, ver, editar, ativar/desativar, excluir com sufixo nos dados únicos, lixeira e restaurar,
extrato, ajuste manual de saldo por carteira com motivo, configurações e meios de pagamento. A spec
007 trouxe a fila de saques (baixa manual, Mercado Pago, recusar com devolução) e o acesso assistido.
Falta a tela. Trazer do antigo (wssports.bet, main 408cf730; backend: CreditosController index,
update, subirCreditos, alterarSaque e getEmailTelefoneCreditos, e CadastroController@destroy; telas:
screens/adm/credits 'Lista de cadastros' /adm/lista-de-creditos, com os modais filter e
push-notification) tudo da tela, com as abas Cadastros, Saques, Ranking e Lixeira. [...] Ficam de
fora: Transações (já estão no Caixa, spec 017), Bônus e promoções (terão a tela 'Bônus'), a comissão
sobre créditos (não existe no v2), 'login' e 'saldo anterior' (não existem no v2)."

## Clarifications

### Session 2026-10-22 (decisões tomadas antes do `/speckit-specify`)

- Q: Qual versão do sistema antigo é a referência? → A: A `main` atual do `wssports.bet`
  (`408cf730`), tela `screens/adm/credits` ("Lista de cadastros", `/adm/lista-de-creditos`).
- Q: A aba Transações (movimento geral de depósitos, saques pagos, bônus, comissão e líquido) entra?
  → A: Não. Essas informações já estão no Caixa clientes (spec 017).
- Q: Quais abas entram? → A: Cadastros, Saques (as transações de saque, com a baixa manual), Ranking
  e Lixeira (os clientes excluídos).
- Q: Quais extras entram? → A: Todos os do antigo: exportação (em Excel, no lugar dos .txt de
  e-mails e telefones), notificação push manual (o sino), antecipar pendentes (o "subir créditos")
  e o detalhe do cliente.
- Q: O ajuste manual de saldo (o antigo "adicionar créditos") pede a senha? → A: Sim, senha de quem
  faz o ajuste + motivo, como no antigo e na prestação de contas da spec 017. Muda a rota de ajuste
  da spec 002.
- Q: Quais filtros do modal antigo entram? → A: Todos: já depositou/nunca depositou, gerou Pix e não
  pagou, jogou esportes/cassino no período e aniversariantes do período.
- Q: Quais critérios o Ranking tem? → A: Os quatro propostos: Depósitos (o do antigo), Valor
  apostado, Resultado para a banca e Saques pagos.
- Q: Antecipar pendentes libera só os prêmios? → A: Não, libera também os encerramentos (cash out)
  que estão na janela de segurança.
- Q: Quem pode antecipar pendentes, exportar e enviar notificação push? → A: Só Admin e Supervisor.
- Q: O visual pode mudar em relação ao antigo (Princípio VIII)? → A: Sim, no padrão do painel novo
  (specs 014 a 018), como nas specs anteriores. As diferenças estão em "Referências do sistema
  antigo".

### Session 2026-10-22 (respostas do responsável às perguntas do `/speckit-specify`)

- Q: O Gerente deve ver o saldo líquido e o Ranking, que são números da banca toda? → A: O problema
  é o alcance, não a coluna: Supervisor e Gerente passam a ter acesso só aos clientes que vieram de
  afiliados da rede deles (o afiliado está ligado à rede pelo gerente responsável, spec 012). O
  Gerente vê os clientes dos afiliados de que é o responsável; o Supervisor, os dos afiliados dos
  gerentes abaixo dele; o Admin, todos (inclusive os sem afiliado e os de afiliado sem gerente). Com
  o alcance restrito, o Gerente vê o saldo líquido e o Ranking dos clientes da rede dele (FR-003).
- O responsável autorizou modificar o que já foi construído para seguir essa regra: a gestão de
  clientes da spec 002 (listar, ver, editar, situação, excluir, restaurar, extrato, ajuste de saldo,
  configurações e meios de pagamento), a fila de saques e o acesso assistido da spec 007 passam a
  aplicar o mesmo alcance no servidor, inclusive por acesso direto (FR-003a). Muda a regra da spec
  002 de que os clientes eram vistos por todo gestor com permissão.

### Session 2026-10-22 (`/speckit-clarify`)

- Q: O Caixa clientes (spec 017) e as apostas de clientes na tela Apostas (spec 018) também devem
  mostrar ao Supervisor só os clientes da rede dele? → A: Sim. As duas telas passam a respeitar a
  rede do Supervisor; o Gerente continua sem o Caixa clientes e sem as apostas de clientes (FR-003c).
- Q: O gerente responsável deve passar a ser obrigatório no afiliado? → A: Sim, no cadastro pelo
  painel, na edição e na aprovação; os afiliados que já existem sem gerente aparecem em destaque para
  o Admin escolher um. Muda a spec 012 (FR-003d).
- Q: Deve haver um limite de notificações push manuais por dia? → A: Sim, um limite por dia para a
  banca toda (padrão 3), configurável pelo Admin, com "N envios restantes hoje" no modal (FR-033a).
- Q: O saldo líquido conta os créditos manuais na carteira real como depósito? → A: Sim: depósitos
  pagos + créditos manuais na carteira real − saques pagos − débitos manuais na carteira real; ajustes
  nas carteiras promocionais não entram (FR-006).
- Q: Antecipar pendentes também pede a senha de quem faz a ação? → A: Sim, senha além da confirmação
  com o aviso, como no ajuste de saldo (FR-019).

## Referências do sistema antigo *(Princípios VII e VIII)*

- Backend (`wssports.bet`, `main` `408cf730`): `app/Http/Controllers/CreditosController.php`
  (`index` com as abas cadastros, saques, lixeira, filtro e ranking; `update` "adicionar créditos";
  `subirCreditos`; `alterarSaque`; `getEmailTelefoneCreditos`), `CadastroController@destroy`
  (excluir e restaurar o cadastro), `NotificacaoPushController` e as travas `TravasSupervisor`
  (`ver_cadastro`, `ver_transacao`, `subir_creditos`, `deletar_creditos`) e `TravasGerentes`
  (`funcao_creditos`), com `config.funcao_pin`.
- Telas: `resources/js/screens/adm/credits` ("Lista de cadastros - total N"), a barra
  `screens/adm/components/bar`, os modais `screens/adm/modals/filter` ("Filtrar cadastros") e
  `screens/adm/modals/push-notification` ("Envio de notificações push - N restantes") e a tela
  `screens/credits` aberta pelo ícone "acessar as transações".

**Como funcionava**:

- **Quem via o quê**: Admin, todos os cadastros; Gerente, os dos vendedores dele; Vendedor, os
  próprios; Supervisor, conforme as travas `ver_cadastro` e `ver_transacao` (que escondiam as abas).
- **Abas**: cadastros, saques, transações (escondida quando o sistema era "Somente" cassino),
  ranking e lixeira. Título "Lista de cadastros - total N"; 250 por página.
- **Cadastros**: colunas ID, LOGIN, NOME, SALDO, SALDO ANTERIOR, SALDO LÍQUIDO (depósitos pagos menos
  saques pagos, verde se positivo, cinza se zero, vermelho se negativo), ATIVO, CRIADO EM (dd/mm
  hh:mm) e AÇÃO. Busca por nome, login, e-mail, ID, IP ou CPF; sem período.
- **Ações por linha**: deletar (lixeira) ou restaurar; subir saldo pendente; adicionar créditos;
  acessar as transações (entrava na tela do cliente com o token dele).
- **Adicionar créditos**: valor digitado num SweetAlert e confirmação com a senha do usuário; gravava
  um "Depósito manual" com rollover 1x e a comissão do vendedor sobre o crédito.
- **Subir créditos**: subia para o saldo os prêmios das apostas pagas com crédito que estavam
  pendentes, antecipando o tempo de segurança, com o aviso de que o cliente assumia a
  responsabilidade.
- **Saques**: colunas ID, LOGIN, PIX (tipo, chave e nome), OBSERVAÇÃO, SALDO, VALOR, OPERAÇÃO e
  CRIADO EM; ação "alterar saque" para Saque pago ou Saque falhou (com mensagem obrigatória). Saque
  falhou devolvia o valor; voltar de falhou para pago desfazia a devolução apagando a transação.
- **Ranking**: seletor 24 horas, 7, 30 ou 90 dias; clientes com depósito pago no período, ordenados
  pelo valor depositado (quantidade e valor dos depósitos, saldo, saldo líquido).
- **Lixeira**: os cadastros excluídos, com restaurar. Excluir apagava também as apostas e as
  transações do cliente e devolvia o limite de créditos ao vendedor; restaurar fazia o contrário.
- **Filtro ("Filtrar cadastros")**: data início/fim, cadastros ativos (Sim/Não), depósito (Já
  depositou/Nunca depositou), modalidade (Esportes/Cassino, apostou no período), gerou qrcode e não
  pagou (último depósito) e aniversariantes no período.
- **Download**: dois arquivos `email.txt` e `telefone.txt` com os contatos da página atual, sem
  máscara.
- **Notificação push**: título, mensagem e público (Todos, Por ID, Por tempo sem interagir em
  dias), mostrando quantos inscritos seriam alcançados e o saldo de notificações restantes.
- **Problemas encontrados**: a busca usava `orWhere` sem agrupar, e por isso escapava do filtro de
  gerente/vendedor (um gerente via clientes de outros); SQL concatenado com valores do pedido
  (modalidade, aniversariantes, ranking); o filtro era uma consulta separada da lista (ao paginar ou
  trocar de aba o filtro se perdia); o aniversariante comparava dia e mês separados (um período de
  25/01 a 05/02 não achava ninguém); o saque podia voltar de falhou para pago apagando a devolução,
  sem controle nem histórico; o download de contatos não tinha máscara nem permissão própria; o
  ranking não tinha alcance (o gerente via a banca toda); as permissões eram checadas de um jeito na
  tela e de outro no servidor (o construtor do controller trocava o middleware conforme a trava).

**Diferenças aprovadas** (padrão das specs 014 a 018):

- A aba Transações sai (o Caixa clientes da spec 017 já tem o movimento do período).
- "Login" e "saldo anterior" não existem no v2: o cliente entra por telefone, e-mail ou CPF, e o
  histórico do saldo está no extrato. Entram os saldos promocionais (esportes e cassino).
- A comissão do vendedor sobre créditos não existe no v2; o ajuste manual não gera comissão.
- Excluir não apaga as apostas nem as transações do cliente (exclusão lógica da spec 002).
- "Acessar as transações" vira o acesso assistido da spec 007 (código temporário, com registro).
- O saque segue a fila da spec 007: Pendente, Processando, Pago e Recusado; não volta de Pago para
  Recusado nem o contrário.
- Download em Excel da aba filtrada, no lugar dos .txt, respeitando a máscara de dados.
- Filtros num painel de filtros do painel, aplicados à própria lista (não uma consulta separada).
- Visual do painel novo: tema, cabeçalho do painel, abas do painel, cartões (`StatCard`), listas que
  viram cartões no celular, menu de ações de três pontinhos e modais com `ModalHeader`.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Consultar e filtrar os clientes (Priority: P1)

O usuário do painel abre "Clientes", vê os clientes cadastrados (do mais novo para o mais antigo),
busca por nome, telefone, e-mail ou CPF e filtra por situação, data de cadastro, saldo, depósito,
modalidade jogada, Pix em aberto e aniversariantes.

**Why this priority**: é a consulta do dia a dia (achar o cliente que ligou, conferir saldo); sozinha
já substitui a aba "cadastros" do antigo para consulta.

**Independent Test**: com clientes que já depositaram e outros que nunca depositaram, abrir Clientes,
conferir colunas e saldo líquido; filtrar "Nunca depositou" e conferir que só os sem depósito pago
aparecem; buscar pelo telefone completo e conferir que o cliente é encontrado; com clientes de
afiliados de dois gerentes, entrar como um dos gerentes e conferir que só os clientes dos afiliados
dele aparecem.

**Acceptance Scenarios**:

1. **Given** um usuário com a permissão de listar clientes, **When** ele abre Clientes, **Then** vê a
   aba Cadastros com o total de clientes no cabeçalho e a lista com ID, nome, contato, saldo, saldos
   promocionais, saldo líquido, ativo e criado em, paginada.
2. **Given** um usuário sem a permissão de ver dados completos, **When** a lista aparece, **Then**
   telefone, e-mail e CPF aparecem mascarados, e a busca por esses dados só encontra o valor completo.
3. **Given** a busca, **When** o usuário digita parte do nome, **Then** a lista mostra só os clientes
   que casam, e a busca nunca escapa dos demais filtros aplicados.
4. **Given** o painel de filtros, **When** o usuário escolhe "Nunca depositou", "Gerou Pix e não
   pagou", "Jogou cassino no período" ou "Aniversariantes do período", **Then** a lista é
   recarregada na hora com os filtros combinados, e os filtros ativos aparecem como etiquetas
   removíveis.
5. **Given** filtros aplicados, **When** o usuário muda de página ou de aba e volta, **Then** os
   filtros continuam valendo (corrige o filtro que se perdia no antigo).
6. **Given** uma busca sem resultado, **When** a lista carrega, **Then** aparece a mensagem de vazio
   orientando a trocar a busca ou os filtros.
7. **Given** um Gerente, **When** ele abre Clientes, **Then** vê só os clientes dos afiliados de que é
   o gerente responsável; um Supervisor vê os dos afiliados dos gerentes abaixo dele; o Admin vê
   todos, inclusive os sem afiliado.
8. **Given** um cliente de fora da rede, **When** o usuário tenta abri-lo ou agir sobre ele por acesso
   direto, **Then** recebe "Cliente não encontrado.".

---

### User Story 2 - Ver e cuidar de um cliente (Priority: P1)

O usuário toca num cliente e abre o detalhe: dados, configurações, meios de pagamento e extrato; de
lá (e do menu da linha) edita os dados, ativa ou desativa, ajusta o saldo, acessa a conta do cliente
ou vai para as apostas dele.

**Why this priority**: o atendimento ao cliente (corrigir cadastro, bloquear, conferir extrato)
depende dessas ações, que já existem no backend mas não têm tela.

**Independent Test**: abrir um cliente, conferir dados e extrato; desativar e conferir que ele não
consegue mais entrar; ajustar o saldo com crédito de R$ 10,00 com motivo e senha e conferir o saldo e
a linha no extrato; tocar em "Ver apostas" e conferir a lista de apostas filtrada pelo cliente.

**Acceptance Scenarios**:

1. **Given** um cliente na lista, **When** o usuário toca nele, **Then** abre o detalhe com dados
   (nome, contato, CPF, nascimento, gênero, afiliado, situação, saldos e saldo líquido),
   configurações, meios de pagamento e extrato paginado com filtros.
2. **Given** um usuário com a permissão de editar, **When** ele altera os dados e salva, **Then** os
   dados são gravados pelas regras atuais (dados únicos, validações) e a lista é atualizada.
3. **Given** um cliente ativo, **When** o usuário com a permissão de editar escolhe "Desativar" e
   confirma, **Then** o cliente fica inativo e as sessões dele deixam de valer na hora.
4. **Given** a permissão de movimentar saldo, **When** o usuário escolhe "Ajustar saldo", informa
   carteira, crédito ou débito, valor, motivo e a própria senha, **Then** o saldo muda, a linha
   aparece no extrato com o autor e o motivo, e a lista é atualizada; **When** a senha está errada,
   **Then** nada muda e aparece "Senha incorreta."
5. **Given** a permissão de acessar a conta, **When** o usuário escolhe "Acessar conta", **Then**
   abre a área do cliente pelo acesso assistido da spec 007.
6. **Given** um usuário com a permissão de listar apostas, **When** ele escolhe "Ver apostas" no
   detalhe ou no menu da linha, **Then** abre a tela Apostas (spec 018) filtrada pelo cliente.

---

### User Story 3 - Dar baixa nos saques (Priority: P1)

Admin ou Supervisor abre a aba Saques, vê primeiro os pendentes (do mais antigo para o mais novo),
os totais pendentes em cartões, e paga (baixa manual ou Mercado Pago) ou recusa com motivo.

**Why this priority**: o cliente espera o dinheiro; sem tela, os saques manuais não têm como ser
baixados.

**Independent Test**: com dois saques pendentes, abrir Saques, conferir a ordem e os cartões; dar
baixa manual no primeiro e conferir que ele vai para Pagos com quem decidiu e quando; recusar o
segundo com motivo e conferir a devolução no saldo do cliente.

**Acceptance Scenarios**:

1. **Given** a permissão de gerenciar saques, **When** o usuário abre a aba Saques, **Then** vê a
   situação "Pendentes" selecionada, os cartões com a quantidade e o valor pendentes e a lista com
   cliente, chave Pix (tipo, chave e nome), valor, saldo atual do cliente, situação e data, do mais
   antigo para o mais novo.
2. **Given** um saque pendente, **When** o usuário escolhe "Dar baixa (manual)" e confirma, **Then** o
   saque vai para Pago, com quem decidiu e quando, e a lista e os cartões são atualizados.
3. **Given** um saque pendente e o Mercado Pago vinculado, **When** o usuário escolhe "Pagar pelo
   Mercado Pago", **Then** o saque segue as regras da spec 007 (Processando e depois Pago, ou volta a
   Pendente com a falha).
4. **Given** um saque pendente, **When** o usuário escolhe "Recusar", informa o motivo e confirma,
   **Then** o saque vai para Recusado, o valor volta ao saldo do cliente e o motivo aparece na linha.
5. **Given** as situações Processando, Pagos ou Recusados, **When** o usuário as escolhe, **Then** a
   lista mostra esses saques do mais novo para o mais antigo, com quem decidiu, quando e o motivo, sem
   ações de alterar.
6. **Given** a busca e o período, **When** o usuário procura um cliente ou escolhe um período, **Then**
   a lista e os cartões mostram só os saques que casam.

---

### User Story 4 - Excluir e restaurar clientes (Priority: P2)

Admin ou Supervisor exclui um cliente pelo menu da linha e o encontra na aba Lixeira, de onde pode
restaurá-lo, trocando os dados únicos que já estiverem em uso por outro cliente.

**Why this priority**: é ação de correção pouco frequente, mas que o antigo tinha e a operação usa
para cadastros falsos ou duplicados.

**Independent Test**: excluir um cliente, conferir que ele some de Cadastros e aparece na Lixeira com
a data de exclusão; cadastrar outro cliente com o mesmo telefone; restaurar o primeiro e conferir que
a tela pede um telefone novo.

**Acceptance Scenarios**:

1. **Given** a permissão de excluir, **When** o usuário escolhe "Excluir" e confirma, **Then** o
   cliente sai de Cadastros, as sessões dele deixam de valer e ele aparece na Lixeira.
2. **Given** a Lixeira, **When** ela aparece, **Then** cada linha mostra ID, nome, contato, saldos,
   criado em e excluído em, e só a ação "Restaurar".
3. **Given** um cliente excluído sem conflito de dados, **When** o usuário restaura e confirma,
   **Then** ele volta a Cadastros com os dados originais.
4. **Given** um telefone, e-mail ou CPF já em uso por outro cliente, **When** o usuário tenta
   restaurar, **Then** a tela mostra quais dados estão em conflito e pede os valores novos antes de
   concluir.
5. **Given** um Gerente, **When** ele abre Clientes, **Then** não vê a aba Lixeira nem a ação
   Excluir.

---

### User Story 5 - Ranking de clientes (Priority: P2)

O usuário abre a aba Ranking, escolhe o período (24 horas, 7, 30, 90 dias ou personalizado) e o
critério (Depósitos, Valor apostado, Resultado para a banca ou Saques pagos) e vê os clientes em
ordem, com a posição.

**Why this priority**: ajuda a banca a achar os melhores clientes e os de risco; a operação funciona
sem ele.

**Independent Test**: com três clientes que depositaram valores diferentes nos últimos 7 dias, abrir
Ranking em "7 dias" e "Depósitos" e conferir a ordem, a quantidade e o valor; trocar para "Resultado
para a banca" e conferir que o cliente que mais perdeu aparece em primeiro.

**Acceptance Scenarios**:

1. **Given** a aba Ranking, **When** ela abre, **Then** o período é "30 dias" e o critério
   "Depósitos", como no antigo, com a lista de posição, ID, nome, saldo, saldo líquido, ativo e as
   colunas do critério.
2. **Given** o critério Depósitos, **When** a lista aparece, **Then** mostra a quantidade e o valor
   dos depósitos pagos no período, do maior valor para o menor, só com clientes que depositaram.
3. **Given** o critério Valor apostado, **When** a lista aparece, **Then** mostra o valor apostado em
   esportes, em cassino e o total no período, só com saldo real, do maior total para o menor.
4. **Given** o critério Resultado para a banca, **When** a lista aparece, **Then** mostra o quanto a
   banca ganhou (positivo) ou perdeu (negativo) com o cliente no período, do maior ganho para a maior
   perda, com a cor do valor.
5. **Given** o critério Saques pagos, **When** a lista aparece, **Then** mostra a quantidade e o valor
   dos saques pagos no período, do maior valor para o menor.
6. **Given** uma linha do ranking, **When** o usuário toca nela, **Then** abre o detalhe do cliente.

---

### User Story 6 - Antecipar os pendentes de um cliente (Priority: P3)

Admin ou Supervisor escolhe "Antecipar pendentes" num cliente que tem prêmios ou encerramentos
esperando a janela de segurança e, depois de confirmar o aviso, o valor vai para o saldo na hora.

**Why this priority**: é uma exceção pedida pelo cliente (o antigo "subir créditos"); a janela de
segurança paga sozinha depois do prazo.

**Independent Test**: com uma aposta do cliente premiada há poucos minutos (ainda na janela),
escolher "Antecipar pendentes", conferir o valor no aviso, confirmar e conferir o saldo e a linha
"Prêmio" no extrato; tentar de novo e conferir "Nenhum prêmio ou encerramento pendente."

**Acceptance Scenarios**:

1. **Given** um cliente com prêmios ou encerramentos na janela de segurança, **When** o usuário
   escolhe "Antecipar pendentes", **Then** vê o aviso do antigo (a antecipação dispensa o tempo de
   segurança para análise das apostas) com a quantidade e o valor que vão subir.
2. **Given** o aviso, **When** o usuário informa a própria senha e confirma, **Then** os prêmios e
   encerramentos pendentes vão para o saldo do cliente pelas mesmas regras de pagamento das specs 009
   e 010, e a tela informa o valor creditado e o saldo atual; **When** a senha está errada, **Then**
   nada muda e aparece "Senha incorreta.".
3. **Given** um cliente sem pendentes, **When** o usuário escolhe a ação, **Then** aparece "Nenhum
   prêmio ou encerramento pendente." e nada muda.
4. **Given** um Gerente, **When** ele abre o menu da linha, **Then** a ação não aparece, e um acesso
   direto é recusado.

---

### User Story 7 - Exportar a lista e enviar notificação (Priority: P3)

Admin ou Supervisor baixa a aba atual filtrada em planilha Excel e envia uma notificação push manual
aos clientes inscritos (todos, IDs escolhidos ou sem apostar nem depositar há N dias).

**Why this priority**: são recursos de apoio e marketing; a gestão funciona sem eles.

**Independent Test**: filtrar "Nunca depositou" e baixar a planilha, conferindo que as linhas batem
com a tela; enviar uma notificação para um ID inscrito e conferir que ela chega no aparelho do
cliente.

**Acceptance Scenarios**:

1. **Given** a permissão de exportar, **When** o usuário escolhe "Baixar planilha", **Then** recebe um
   arquivo .xlsx da aba atual com os filtros aplicados, todas as linhas do filtro (não só a página) e
   as mesmas colunas e valores da tela, com contatos mascarados para quem não vê os dados completos.
2. **Given** um filtro com mais linhas que o máximo por planilha, **When** o usuário tenta baixar,
   **Then** a tela avisa e pede para reduzir com filtros, e o servidor recusa.
3. **Given** a permissão de enviar notificação, **When** o usuário escolhe o sino, **Then** abre o
   modal com público (Todos, Por ID, Sem interagir há N dias), título e mensagem, e mostra quantos
   inscritos serão alcançados e quantos envios restam hoje antes de enviar.
4. **Given** o modal preenchido, **When** o usuário confirma o envio, **Then** a notificação é enviada
   aos inscritos do público e a tela informa quantos foram alcançados.
5. **Given** um Gerente, **When** ele abre Clientes, **Then** não vê o botão de planilha nem o sino.

---

### Edge Cases

- Cliente com saldo líquido zero (nunca depositou, nem recebeu crédito manual, nem sacou): aparece em
  cinza; negativo (sacou mais do que entrou, com prêmios), em vermelho.
- Cliente que pagou por fora e recebeu crédito manual na carteira real: o crédito conta como entrada
  no saldo líquido; um crédito manual na carteira promocional não conta.
- Cliente com saldo negativo (spec 007): o saldo aparece em vermelho; o ajuste de débito continua
  permitido (pode negativar, como hoje).
- Busca por telefone com máscara ou espaços: os não dígitos são ignorados, como na API atual.
- Cliente sem afiliado, ou de afiliado sem gerente responsável: só o Admin vê.
- Afiliado que muda de gerente: os clientes dele saem da rede do gerente antigo e entram na do novo na
  hora, inclusive os saques pendentes deles.
- Gerente movido para outro supervisor: os clientes dos afiliados dele passam para a rede do novo
  supervisor.
- Notificação "Todos os inscritos" enviada por um Supervisor: alcança só os inscritos da rede dele.
- Restaurar um cliente excluído de fora da rede: tratado como cliente inexistente.
- Período personalizado com "de" maior que "até", ou maior que 1 ano: recusado no próprio campo.
- Aniversariantes num período que atravessa o mês ou o ano (ex.: 25/12 a 05/01): os clientes com
  aniversário nesses dias aparecem (corrige o antigo).
- "Gerou Pix e não pagou": considera o último depósito do cliente; se o último foi pago, o cliente não
  aparece, mesmo com cobranças antigas em aberto.
- Cliente sem data de nascimento: não aparece no filtro de aniversariantes.
- Dois usuários dando baixa no mesmo saque ao mesmo tempo: só o primeiro grava; o segundo recebe a
  mensagem do servidor ("Este saque já foi decidido.") e a lista é atualizada.
- Saque em Processando no Mercado Pago: não pode receber baixa manual nem ser recusado até o retorno
  do Mercado Pago (regra da spec 007).
- Antecipar pendentes com prêmio já pago pela rotina da janela no mesmo instante: só o que ainda
  estava pendente é creditado; nada é pago duas vezes.
- Antecipar com aposta reaberta pela apuração (resultado corrigido): segue a regra de estorno da spec
  009, que pode negativar o saldo.
- Cliente excluído com saque pendente: o saque continua na aba Saques e pode ser decidido.
- Acesso assistido a cliente excluído ou inativo: negado (regra da spec 007).
- Notificação para "Por ID" com IDs sem inscrição: a contagem de alcançados mostra só os inscritos; com
  zero alcançados o envio fica desabilitado.
- Cliente que desligou as notificações da banca no aparelho: não recebe a notificação manual.
- Dois envios ao mesmo tempo com 1 envio restante no dia: só o primeiro passa; o segundo recebe
  "Limite de envios de hoje atingido.".
- Planilha com mais de 50.000 linhas: não é gerada (FR-030).
- Permissão retirada com a tela aberta: a próxima ação é recusada com a mensagem do servidor e a ação
  some ao recarregar.
- Sessão expirada: leva ao login como no restante do painel.
- Celular: as linhas viram cartões com nome, saldo e saldo líquido em destaque, e o restante ao
  expandir.

## Requirements *(mandatory)*

### Functional Requirements

**Acesso e abas**

- **FR-001**: O menu do painel DEVE ter o item "Clientes", visível para quem tem a permissão de listar
  clientes, levando à tela com as abas Cadastros, Saques, Ranking e Lixeira.
- **FR-002**: Cada aba DEVE aparecer só para quem tem a permissão dela: Cadastros, `clientes.listar`;
  Saques, `saques.gerenciar`; Ranking, a permissão nova `clientes.ver_ranking`; Lixeira,
  `clientes.restaurar`. Quem não tem a permissão de uma aba não a vê, e o servidor recusa o acesso
  direto.
- **FR-003**: Cada nível DEVE ver e agir só sobre os clientes da própria rede, definida pelo afiliado
  que trouxe o cliente e pelo gerente responsável desse afiliado (spec 012):
  - **Admin**: todos os clientes, inclusive os sem afiliado e os de afiliado sem gerente responsável;
  - **Supervisor**: os clientes dos afiliados cujo gerente responsável está abaixo dele (inclusive
    gerentes inativos ou excluídos);
  - **Gerente**: os clientes dos afiliados de que ele é o gerente responsável;
  - **Vendedor**: não acessa a tela.
  A rede do cliente segue o afiliado: se o afiliado mudar de gerente, os clientes dele passam para a
  rede do novo gerente; afiliado excluído continua levando os clientes para a rede do gerente dele.
  Com esse alcance, o Gerente vê o saldo líquido e o Ranking dos clientes da rede dele. As listas, os
  totais, o ranking, a planilha e o público das notificações DEVEM considerar só os clientes da rede.
- **FR-003a**: O mesmo alcance DEVE valer no servidor para tudo o que já existe sobre clientes no
  painel, mudando o que foi construído nas specs 002 e 007: listar, excluídos, ver, editar, alterar
  situação, excluir, restaurar, extrato, ajuste de saldo, configurações, meios de pagamento, acesso
  assistido e a fila de saques (o Supervisor só vê e decide os saques dos clientes da rede dele).
  Qualquer consulta ou ação sobre um cliente fora da rede DEVE ser respondida como cliente
  inexistente ("Cliente não encontrado."), sem revelar que ele existe.
- **FR-003b**: Ao editar um cliente, Supervisor e Gerente só PODEM escolher um afiliado da rede deles
  (de uma lista) e NÃO PODEM deixar o cliente sem afiliado; só o Admin pode tirar o afiliado ou
  escolher um afiliado sem gerente responsável.
- **FR-003c**: O mesmo alcance DEVE valer para o Supervisor no Caixa clientes (spec 017: totais,
  lista por cliente e planilha) e nas apostas de clientes da tela Apostas (spec 018: lista, totais,
  bilhete, premiados, planilha, cancelar e restaurar): ele vê e age só sobre os clientes da rede dele;
  o Admin continua vendo a banca toda. O Gerente continua sem o Caixa clientes e sem as apostas de
  clientes, como hoje. Com isso, para o mesmo período, os números do Supervisor batem entre Clientes,
  Caixa clientes e Apostas.
- **FR-003d**: O gerente responsável DEVE passar a ser obrigatório no afiliado (muda a spec 012): no
  cadastro pelo painel (o Gerente continua sendo ele mesmo; Admin e Supervisor escolhem de uma lista
  de gerentes da rede deles), na edição (não pode ser retirado) e na aprovação do cadastro público
  (o afiliado que se cadastra pelo site fica "Em análise" sem gerente e só é aprovado com um gerente
  escolhido). Os afiliados que já existem sem gerente DEVEM aparecer em destaque na lista de
  afiliados do Admin ("Sem gerente responsável"), para ele escolher um; enquanto isso, os clientes
  deles ficam só para o Admin.

**Cadastros**

- **FR-004**: A aba Cadastros DEVE listar os clientes não excluídos, do mais novo para o mais antigo
  (com a opção de ordenar por nome e saldo, já existente), paginada (padrão 20, máximo 100), com o
  total de clientes do filtro no cabeçalho.
- **FR-005**: Cada linha DEVE mostrar: ID, nome, contato (telefone com DDI e e-mail, mascarados sem
  `clientes.ver_dados_completos`), saldo, saldos promocionais (esportes e cassino), saldo líquido,
  ativo (Sim/Não com cor) e criado em (dd/mm/aaaa hh:mm, fuso do sistema).
- **FR-006**: O saldo líquido DEVE ser, desde o cadastro do cliente: depósitos pagos + créditos
  manuais na carteira real − saques pagos − débitos manuais na carteira real (o antigo somava o
  "Depósito manual"), calculado no servidor em reais com duas casas, sem ponto flutuante; verde se
  positivo, cinza se zero, vermelho se negativo. Depósitos estornados não contam, e ajustes nas
  carteiras promocionais (esportes e cassino) não entram. O critério Depósitos do Ranking continua
  contando só os depósitos pagos (FR-028).
- **FR-007**: A busca DEVE usar a regra atual da API (nome por trecho; telefone, CPF e e-mail por
  prefixo para quem vê os dados completos e só pelo valor completo para os demais) e DEVE sempre
  combinar com os filtros (corrige o `orWhere` do antigo).
- **FR-008**: O painel de filtros DEVE ter os filtros atuais da API (situação, DDI, afiliado,
  cadastro de/até, idade mínima/máxima, gênero, saldo mínimo/máximo, com saldo promocional, saque
  bloqueado, com CPF, com e-mail) e os novos do antigo:
  - **Depósito**: Já depositou (ao menos um depósito pago) ou Nunca depositou;
  - **Gerou Pix e não pagou**: o último depósito do cliente está em aberto ou expirou sem pagamento;
  - **Modalidade no período**: apostou em esportes, ou jogou cassino, no período escolhido;
  - **Aniversariantes no período**: dia e mês do nascimento dentro do período, inclusive quando ele
    atravessa o mês ou o ano.
  Afiliado DEVE ser escolhido de uma lista (constituição, "Campos de itens existentes"). Os filtros
  ativos DEVEM aparecer como etiquetas removíveis.
- **FR-009**: Os filtros, a busca, a aba, a ordenação e a página DEVEM ficar no endereço da tela, para
  que recarregar, voltar do detalhe ou mudar de página mantenha a mesma lista (corrige a consulta
  separada do antigo).
- **FR-010**: O menu de ações (três pontinhos) de cada linha DEVE ter, conforme a permissão: Ver
  detalhe, Editar, Ativar/Desativar, Ajustar saldo, Antecipar pendentes, Acessar conta, Ver apostas e
  Excluir. Tocar na linha abre o detalhe.

**Detalhe do cliente**

- **FR-011**: O detalhe DEVE abrir numa gaveta (modal lateral; tela cheia no celular) com as seções
  Dados, Configurações, Meios de pagamento e Extrato, sem perder a lista por trás.
- **FR-012**: Dados DEVE mostrar nome, contato, CPF, data de nascimento, gênero, afiliado, situação,
  saldos, saldo líquido e datas de cadastro e alteração, com a ação Editar para quem tem
  `clientes.editar` (regras e validações atuais da spec 002, inclusive a senha opcional).
- **FR-013**: Configurações DEVE mostrar e, com `clientes.editar_configuracoes`, alterar as
  configurações do cliente da spec 002 (mesmos campos e regras, esportes escolhidos de lista).
- **FR-014**: Meios de pagamento DEVE listar, cadastrar, editar e excluir as chaves Pix do cliente
  pelas regras atuais da spec 002.
- **FR-015**: Extrato DEVE mostrar as transações do cliente (data, tipo, origem, carteira, valor,
  saldo depois, autor e observação) paginadas, com os filtros atuais do extrato.
- **FR-016**: O detalhe DEVE ter o atalho "Ver apostas", para quem tem `apostas.listar`, que abre a
  tela Apostas (spec 018) filtrada pelo cliente, no período "Este mês". A tela Apostas ainda não tem
  o filtro por cliente: esta spec o acrescenta nela (servidor e tela), como uma etiqueta removível
  igual ao filtro por vendedor, sem mudar as demais regras da spec 018 (só Admin e Supervisor veem
  as apostas de clientes, FR-003 da spec 018).

**Ações sobre o cliente**

- **FR-017**: Ativar/Desativar DEVE usar a regra atual (desativar invalida as sessões na hora), com
  confirmação.
- **FR-018**: Ajustar saldo DEVE pedir carteira (real, promoção esportes ou promoção cassino), tipo
  (crédito ou débito), valor (campo de reais do painel), motivo (obrigatório, até 255 caracteres) e a
  senha de quem faz o ajuste. O servidor DEVE conferir a senha antes de mexer no saldo e recusar com
  "Senha incorreta." sem gravar nada. Esta é uma mudança na rota de ajuste da spec 002, que passa a
  exigir a senha também por acesso direto.
- **FR-019**: Antecipar pendentes DEVE creditar na hora, para o cliente escolhido, todos os prêmios
  (spec 009) e os valores de encerramento (spec 010) que estão esperando a janela de segurança, pelas
  mesmas regras de pagamento (sem pagar duas vezes o que a rotina da janela já pagou), depois de uma
  confirmação que mostra a quantidade e o valor, o aviso de que a antecipação dispensa o tempo de
  segurança para análise e o campo da senha de quem faz a ação. O servidor DEVE conferir a senha
  antes de creditar e recusar com "Senha incorreta." sem gravar nada. Sem pendentes, DEVE responder
  "Nenhum prêmio ou encerramento pendente.".
  Cada antecipação DEVE ficar registrada com quem fez, quando e o valor.
- **FR-020**: Acessar conta DEVE usar o acesso assistido da spec 007 sem mudá-lo.
- **FR-021**: Excluir DEVE usar a exclusão atual da spec 002 (lógica, com sufixo nos dados únicos e
  sessões invalidadas), com confirmação; apostas, saques e transações do cliente NÃO DEVEM ser
  apagados (diferença em relação ao antigo).

**Saques**

- **FR-022**: A aba Saques DEVE ter a escolha de situação Pendentes (padrão), Processando, Pagos e
  Recusados; Pendentes do mais antigo para o mais novo, as demais do mais novo para o mais antigo,
  paginada (padrão 30, máximo 100).
- **FR-023**: A aba Saques DEVE ter busca por cliente (nome, ou ID do cliente) e período (data do
  pedido, atalhos Hoje, Ontem, Esta semana, Este mês e Personalizado, até 1 ano; sem período por
  padrão em Pendentes, para não esconder pedidos antigos).
- **FR-024**: A aba Saques DEVE mostrar os cartões Pendentes (quantidade e valor) e Processando
  (quantidade e valor), de todos os saques nessas situações, e, com período escolhido, Pagos no
  período (quantidade e valor).
- **FR-025**: Cada linha DEVE mostrar: cliente (ID e nome), chave Pix (tipo, chave e nome do
  titular), valor, saldo atual do cliente, situação (com cor), data do pedido e, quando decidido, quem
  decidiu, quando, a forma (manual ou Mercado Pago) e o motivo da recusa.
- **FR-026**: Os saques pendentes DEVEM ter as ações Dar baixa (manual), Pagar pelo Mercado Pago
  (quando o gateway estiver vinculado) e Recusar (motivo obrigatório, com devolução do valor), pelas
  regras da spec 007 sem mudá-las. Saques já decididos NÃO DEVEM ter ações de alterar (corrige o
  falhou→pago do antigo).

**Ranking**

- **FR-027**: A aba Ranking DEVE ter o período 24 horas, 7 dias, 30 dias (padrão), 90 dias ou
  Personalizado (até 1 ano) e o critério Depósitos (padrão), Valor apostado, Resultado para a banca
  ou Saques pagos, paginada (padrão 20, máximo 100), com a posição (1º, 2º, ...) que continua entre
  as páginas.
- **FR-028**: Cada linha DEVE mostrar posição, ID, nome, saldo, saldo líquido, ativo e as colunas do
  critério:
  - **Depósitos**: quantidade e valor dos depósitos pagos no período (ordem: valor);
  - **Valor apostado**: valor apostado em esportes, em cassino e total, só com saldo real, no período
    (ordem: total);
  - **Resultado para a banca**: apostado menos prêmios, devoluções e encerramentos pagos, em esportes
    e cassino, só saldo real, no período, pelas mesmas regras do Caixa clientes (spec 017) (ordem: do
    maior ganho da banca para a maior perda);
  - **Saques pagos**: quantidade e valor dos saques pagos no período (ordem: valor).
  Só entram os clientes com movimento do critério no período. Tocar na linha abre o detalhe.

**Lixeira**

- **FR-029**: A aba Lixeira DEVE listar os clientes excluídos (mais recentes primeiro), com as colunas
  de Cadastros mais "excluído em", busca e paginação, e só a ação Restaurar, pela regra atual da spec
  002: quando telefone, e-mail ou CPF estiverem em uso por outro cliente, a tela DEVE mostrar os
  campos em conflito e pedir os valores novos antes de concluir.

**Exportação**

- **FR-030**: A tela DEVE ter "Baixar planilha" para quem tem a permissão nova `clientes.exportar`,
  gerando um .xlsx da aba atual (Cadastros, Saques, Ranking ou Lixeira) com a data de geração, os
  filtros aplicados e uma linha por registro do filtro (não só da página), com as mesmas colunas e
  valores da tela em formato de moeda; contato e CPF mascarados para quem não tem
  `clientes.ver_dados_completos`. Cada exportação DEVE ser independente (dois downloads ao mesmo tempo
  não se misturam). A planilha DEVE ter no máximo 50.000 linhas: acima disso, a tela avisa antes de
  baixar ("O filtro tem N registros; o máximo por planilha é 50.000. Use filtros para reduzir.") e o
  servidor recusa com a mesma mensagem.

**Notificação push manual**

- **FR-031**: A tela DEVE ter o botão de notificação (sino) para quem tem a permissão nova
  `clientes.enviar_notificacao`, abrindo um modal com: público (Todos os inscritos; Por ID, com os
  clientes escolhidos por busca; Sem apostar nem depositar há N dias, N de 1 a 365), título (até 50
  caracteres), mensagem (até 150 caracteres) e a contagem de inscritos que serão alcançados,
  atualizada ao mudar o público.
- **FR-032**: O envio DEVE usar as notificações push da spec 010 (inscrições do aparelho do cliente),
  só para clientes ativos e não excluídos, com um tipo próprio "Aviso da banca" que o cliente pode
  desligar como os demais tipos; ao tocar na notificação, o site abre na página inicial. O envio DEVE
  ser feito em segundo plano, e a tela DEVE informar quantos foram alcançados.
- **FR-033**: Cada envio DEVE ficar registrado com autor, data, público, título, mensagem,
  alcançados e entregues. Com zero alcançados, o botão de enviar fica desabilitado e o servidor recusa.
- **FR-033a**: Os envios manuais DEVEM ter um limite por dia para a banca toda (somando os envios de
  todos os usuários, no dia do fuso do sistema), com padrão 3 e configurável só pelo Admin (de 1 a
  50). O modal DEVE mostrar "N envios restantes hoje"; sem envios restantes, o botão de enviar fica
  desabilitado com "Limite de envios de hoje atingido." e o servidor recusa com a mesma mensagem. Um
  envio recusado por zero alcançados não conta no limite.

**Permissões**

- **FR-034**: DEVEM existir as permissões novas, no mesmo padrão das demais (lista, padrão por função e
  bloqueio por função em `Funcao`): `clientes.ver_ranking`, `clientes.exportar`,
  `clientes.enviar_notificacao` e `clientes.antecipar_pendentes`. As três últimas DEVEM entrar na
  lista das permissões de clientes restritas a Admin e Supervisor (o Gerente não usa, mesmo que a
  receba).
- **FR-035**: Padrão por função: Admin e Supervisor recebem as quatro; o Gerente recebe
  `clientes.ver_ranking` (o ranking dos clientes da rede dele, FR-003); o Vendedor nenhuma. As permissões DEVEM ser
  aplicadas no servidor em toda consulta e ação. As permissões existentes (`clientes.*`,
  `saques.gerenciar`) continuam como estão.
- **FR-036**: Os usuários já cadastrados DEVEM receber as permissões padrão novas da função deles ao
  publicar esta spec.

**Visual, responsividade e padrões do painel**

- **FR-037**: A tela DEVE seguir os padrões do painel (specs 014 a 018 e constituição): cores do tema,
  cabeçalho do painel, abas do painel, cartões de valor, filtro de período já usado no painel, painel
  de filtros com etiquetas, cabeçalho padrão nos modais (`ModalHeader`), campos com rótulo em cima,
  campo de valor em reais, paginação e menu de três pontinhos nas ações.
- **FR-038**: No celular (até 900 px), as linhas DEVEM virar cartões com nome, saldo e saldo líquido
  em destaque (Saques: cliente, valor e situação; Ranking: posição, nome e o valor do critério) e o
  restante ao expandir; o detalhe ocupa a tela toda; os filtros abrem num painel próprio; nada pode
  exigir rolagem horizontal.
- **FR-039**: Cada área (cartões, lista, detalhe) DEVE ter estados de carregando (esqueleto), vazio
  (com orientação) e erro com "tentar novamente", sem travar as outras.
- **FR-040**: Toda ação DEVE dar retorno imediato (botão com carregando e sem clique duplo,
  confirmação de sucesso ou mensagem do servidor) e atualizar a lista, os cartões e o detalhe sem
  recarregar a página nem perder a posição, os filtros e a página.

### Key Entities

- **Cliente (na lista)**: cliente já existente (spec 002), visto com ID, nome, contato, saldos,
  saldo líquido (calculado a cada consulta), situação e datas. Nada novo é gravado no cliente; a
  rede dele vem do afiliado e do gerente responsável do afiliado.
- **Saque (na fila)**: saque já existente (spec 007), com situação, chave Pix, valor, quem decidiu,
  quando, forma e motivo. Nada novo é gravado no saque.
- **Linha do ranking**: valores calculados por cliente no período e critério (depósitos, valor
  apostado, resultado para a banca, saques pagos); não são gravados.
- **Antecipação de pendentes**: registro de cada antecipação (cliente, autor, data, quantidade e valor
  de prêmios e de encerramentos creditados), ligado às transações de crédito geradas.
- **Envio de notificação manual**: registro de cada envio (autor, data, público, título, mensagem,
  alcançados, entregues), com as notificações por cliente no mesmo modelo da spec 010.
- **Permissões de clientes**: as quatro permissões novas somadas às que já existem.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Um usuário encontra um cliente pelo telefone completo e abre o detalhe em menos de 15
  segundos a partir do menu.
- **SC-002**: Em 100% das conferências manuais, o saldo líquido da lista bate com o extrato do cliente
  (depósitos pagos e ajustes manuais na carteira real, menos saques pagos, FR-006), e a planilha
  exportada bate com a tela.
- **SC-003**: Um saque pendente recebe baixa manual em até 3 toques a partir da aba Saques, e nenhum
  saque é decidido duas vezes nos testes de clique simultâneo.
- **SC-004**: Em 100% dos testes, um ajuste de saldo ou uma antecipação com senha errada não muda
  nada, e uma antecipação nunca paga o mesmo prêmio ou encerramento duas vezes.
- **SC-005**: Nenhuma ação ou aba aparece para quem não tem a permissão dela, todo acesso direto sem
  permissão é recusado pelo servidor, e em nenhum teste um Supervisor ou Gerente vê, exporta, notifica
  ou altera cliente (ou saque de cliente) de fora da rede dele.
- **SC-006**: Um gestor do sistema antigo encontra na tela nova cada informação e ação da "Lista de
  cadastros" (colunas, filtros, saques, ranking, lixeira, download e notificação), exceto a aba
  Transações, o login e o saldo anterior.
- **SC-007**: A tela funciona no celular (360 px de largura) sem rolagem horizontal e com todos os
  alvos de toque com pelo menos 40 px.
- **SC-008**: Abrir Clientes, trocar de aba ou de filtro mostra a primeira página em até 2 segundos
  numa conexão comum, com até 100.000 clientes cadastrados.

## Assumptions

- Listar, ver, editar, situação, excluir, restaurar, extrato, ajuste de saldo, configurações e meios
  de pagamento de clientes (spec 002), a fila de saques e o acesso assistido (spec 007), o pagamento
  de prêmios e encerramentos com janela de segurança (specs 009 e 010) e as notificações push (spec
  010) já existem; esta spec os mostra e chama, mudando o ajuste de saldo (senha) e o alcance por rede
  (FR-003a), e acrescentando a antecipação, os filtros novos, o saldo líquido, o ranking, a
  exportação e o envio manual.
- O alcance por rede vale para a gestão de clientes, a fila de saques, o Caixa clientes (spec 017) e
  as apostas de clientes da tela Apostas (spec 018) (FR-003a e FR-003c).
- O gerente responsável do afiliado passa a ser obrigatório (FR-003d); só os afiliados antigos sem
  gerente, até o Admin escolher um, e os clientes sem afiliado ficam só para o Admin.
- A lista de afiliados do painel ainda não tem tela (spec 012 é só API): o destaque "Sem gerente
  responsável" do FR-003d é um filtro e um indicador na resposta da API, para a futura tela.
- A spec 018 dizia que o "subir créditos" não entraria porque o prêmio vai ao saldo pela apuração;
  esta spec traz a antecipação a pedido do responsável, sem mudar a janela de segurança.
- A biblioteca de planilhas autorizada na spec 017 é reaproveitada; não há dependência nova.
- As regras de soma do Resultado para a banca e do valor apostado são as do Caixa clientes (spec 017).
- O antigo tinha um saldo de notificações restantes contratado; o v2 envia pelo próprio Web Push, sem
  custo por envio, e usa o limite diário configurável do FR-033a no lugar da cota.
- "Sem apostar nem depositar há N dias" considera apostas em esportes, rodadas de cassino e depósitos
  pagos.
- Datas no fuso do sistema (Brasília).
- Bônus e promoções (`clientes_promocoes`) ficam para a tela "Bônus".
- Os clientes do sistema antigo não são migrados; a tela mostra os clientes do v2.
- Não há dados fake nesta spec.
- Sem testes automatizados (constituição); a validação é manual pelo quickstart da spec.
