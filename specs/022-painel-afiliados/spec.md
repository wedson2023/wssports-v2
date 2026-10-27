# Feature Specification: Painel — Afiliados (lista, aprovação, gaveta, regra de ganho e prestação de contas)

**Feature Branch**: `022-painel-afiliados`

**Created**: 2026-10-27

**Status**: Draft

**Input**: User description: "Spec 022: tela 'Afiliados' no painel (backend e frontend), no padrão das
specs 014 a 021. A gestão de afiliados existe só como API desde a spec 012, com as mudanças da spec
019 [...]. Falta a tela. Trazer do antigo (wssports.bet, main 408cf730; backend: FiliadosController
index, store, update e destroy, e a rota prestar-contas-filiado; telas: screens/adm/affiliated
'Lista de filiados' /adm/afiliados e o modal screens/adm/modals/affiliated 'Novo Filiado') tudo o
que a tela tem [...]. Melhorias sobre o antigo: StatCards; aba 'Em análise' para aprovar ou recusar
[...]; gaveta do afiliado [...]; prestação de contas e exclusão com senha [...]; sem exclusão
definitiva. Novos campos 'Quantidade de seguidores' e 'Sobre você' [...]. Regra de ganho só no
cadastro do afiliado: sai das Configurações gerais a regra padrão do cadastro público [...]. Fica de
fora: exportação. Alcance por rede como na spec 019 [...]. Item 'Afiliados' no menu, rota
/painel/afiliados. Visual do painel novo [...]; modais com campos e botões organizados em grade,
agrupados por seção; liga/desliga como campo select, não toggle."

## Clarifications

### Session 2026-10-27 (decisões tomadas antes do `/speckit-specify`)

- Q: Qual versão do sistema antigo é a referência? → A: A `main` atual do `wssports.bet`
  (`408cf730`), tela `screens/adm/affiliated` ("Lista de filiados") e o modal
  `screens/adm/modals/affiliated` ("Novo Filiado").
- Q: A prestação de contas e a exclusão pedem a senha de quem faz? → A: Sim, as duas, como no
  antigo e como nas ações sensíveis das specs 019 e 020. A prestação já pede senha na API; a
  exclusão passa a pedir (muda a rota atual).
- Q: A tela tem cartões de resumo no topo? → A: Sim: total de afiliados, em análise, comissão do
  período e total a receber.
- Q: A lista pode ser exportada em planilha? → A: Não, fica de fora desta spec.
- Q: Onde fica a redefinição de senha do afiliado pela gestão? → A: No próprio modal de edição
  (campos de nova senha opcionais), sem ação separada no menu.
- Q: Que dados novos o afiliado informa? → A: "Quantidade de seguidores" (número inteiro) e "Sobre
  você" (texto livre). Ele preenche no cadastro público e altera em "Meus dados"; a gestão vê e
  edita os dois. Muda a spec 012.
- Q: A regra de ganho padrão das Configurações gerais continua? → A: Não. Sai das Configurações a
  regra padrão do cadastro público (spec 012, FR-015; seção Afiliados da spec 021). O tipo de ganho
  e o percentual passam a ser definidos por quem gerencia o afiliado, no cadastro pelo painel e na
  aprovação. Por isso, quem tem `afiliados.cadastrar` (inclusive o Gerente) define a regra inicial
  no cadastro, e `afiliados.editar_comissao` continua sendo exigida só para trocar a regra depois.
  Muda as specs 012 e 021.
- Q: O visual pode mudar em relação ao antigo (Princípio VIII)? → A: Sim, no padrão do painel novo
  (specs 014 a 021), como nas specs anteriores. Nos modais, campos e botões organizados em grade e
  agrupados por seção; as escolhas de liga/desliga são campos de seleção, não interruptores. As
  diferenças estão em "Referências do sistema antigo".

### Session 2026-10-27 (resposta do responsável à pergunta do `/speckit-specify`)

- Q: O Supervisor também vê e aprova os cadastros públicos sem gerente, ou só o Admin? → A: Só o
  Admin. A regra de rede da spec 019 continua sem exceção: os cadastros públicos (sem gerente) só
  aparecem para o Admin, e só ele aprova e recusa. Muda a spec 012 (FR-014 e FR-051), em que
  Supervisor também aprovava e recusava (FR-001, FR-003 e FR-023).

### Session 2026-10-27 (`/speckit-clarify`)

- Q: O campo de seguidores guarda só o número total, ou também a rede social principal e o link do
  perfil? → A: Número total + rede social principal (Instagram, TikTok, YouTube, Facebook, Kwai, X
  ou Outra) + link do perfil, os três obrigatórios nos cadastros novos (FR-016).
- Q: O que acontece ao excluir um afiliado que ainda tem valor a receber? → A: A confirmação avisa
  "Este afiliado tem R$ X a receber." e permite excluir mesmo assim; o valor continua aparecendo na
  lixeira e pode ser prestado depois de restaurar (FR-029 e FR-030).
- Q: Que período a lista mostra ao abrir? → A: Este mês (do dia 1 até hoje), para cadastros,
  depósitos, saques e comissão do período; "A receber" e "Entradas" não dependem do período
  (FR-007). Muda o padrão da listagem atual, que era o dia de hoje.

## Referências do sistema antigo *(Princípios VII e VIII)*

- Backend (`wssports.bet`, `main` `408cf730`): `app/Http/Controllers/FiliadosController.php`
  (`index` com as abas ativos e lixeira, período e pesquisa; `store`; `update`; `destroy` com a
  senha; a rota `prestar-contas-filiado`) e o model `Filiados`.
- Telas: `resources/js/screens/adm/affiliated` ("Lista de filiados - total N", rota
  `/adm/afiliados`, botão "Novo Filiado") e o modal `resources/js/screens/adm/modals/affiliated`
  ("Novo Filiado" / edição).

**Como funcionava**:

- **Lista**: abas "ativos" e "lixeira", filtro de período (de/até) e pesquisa; 250 por página.
  Colunas ID, NOME, GANHO (tipo de ganho), CADASTROS, DEPÓSITOS, SAQUES, COMISSÃO (percentual),
  ENTRADAS (a base do ganho desde a última prestação, conforme o tipo de ganho), APURADO
  (percentual × entradas, o valor a pagar) e BAIXA (data da última prestação).
- **Menu de ações** (três pontinhos, ao passar o mouse): copiar link (`?user=LOGIN`), editar,
  acessar as transações (abria o painel `/afiliados` do próprio afiliado), prestar contas/dar baixa
  (com data e hora livres e a senha), excluir (com a senha); na lixeira, restaurar e deletar para
  sempre (com a senha).
- **Modal**: nome, login, senha, e-mail, CPF, ativo (seleção), cidade, estado, endereço, telefone,
  tipo de PIX, PIX, tipo de ganho e comissão (%), um campo abaixo do outro.
- **Problemas encontrados** (além dos já corrigidos na spec 012): o link usava o login; a data da
  prestação era livre (inclusive no passado); "acessar transações" abria a área do afiliado como se
  fosse ele; existia exclusão definitiva; não havia como aprovar cadastros públicos pela tela; o
  modal era uma coluna só, longa, sem agrupamento.

**Diferenças aprovadas** (padrão das specs 014 a 021):

- O alcance segue a rede da spec 019 (afiliado → gerente responsável).
- A aba "Em análise" para os cadastros públicos, com aprovar (gerente e regra obrigatórios) e
  recusar.
- O link copiado usa o código público.
- "Acessar transações" vira a gaveta do afiliado, sem entrar na área dele.
- A prestação usa o momento da confirmação (sem data livre), mostra o valor antes e fica no
  histórico; a exclusão vai para a lixeira, com senha; não há exclusão definitiva.
- A regra de ganho tem troca própria, com histórico; a regra padrão das Configurações sai.
- Campos novos: rede social principal, link do perfil, quantidade de seguidores e "Sobre você".
- Visual do painel novo: tema, cabeçalho do painel, abas do painel, cartões (`StatCard`), filtro de
  período do painel, painel de filtros com etiquetas, listas que viram cartões no celular, menu de
  ações de três pontinhos e modais com `ModalHeader`, em grade e com campos de seleção.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Consultar os afiliados (Priority: P1)

O usuário do painel abre "Afiliados", vê os cartões de resumo e a lista dos afiliados da rede dele
com os números do período (cadastros, depósitos, saques, comissão) e o que cada um tem a receber,
busca por nome, login ou código e filtra por situação, gerente e período.

**Why this priority**: é a consulta do dia a dia (quanto cada afiliado trouxe e quanto há para
pagar); sozinha já substitui a "Lista de filiados" do antigo para consulta.

**Independent Test**: com dois afiliados ativos de gerentes diferentes, um com depósitos de clientes
no mês, abrir Afiliados como Admin e conferir cartões e colunas; entrar como um dos gerentes e
conferir que só o afiliado dele aparece e que os cartões mudam junto.

**Acceptance Scenarios**:

1. **Given** um usuário com `afiliados.listar`, **When** ele abre Afiliados, **Then** vê os cartões
   Total de afiliados, Em análise, Comissão do período e A receber, e a aba Ativos com o total no
   cabeçalho e a lista (ID, nome e código, gerente, ganho, cadastros, depósitos, saques, comissão
   do período, entradas, a receber, última prestação e situação), paginada, no período "Este mês".
2. **Given** a busca, **When** o usuário digita parte do nome, do login ou do código, **Then** a
   lista e os cartões mostram só os afiliados que casam, sem escapar dos filtros aplicados.
3. **Given** o painel de filtros, **When** o usuário escolhe situação (Ativo, Inativo ou Todos),
   gerente ou período, **Then** a lista é recarregada com os filtros combinados e eles aparecem
   como etiquetas removíveis.
4. **Given** um Gerente, **When** ele abre Afiliados, **Then** vê só os afiliados de que é o gerente
   responsável, sem o filtro de gerente; o Supervisor vê os dos gerentes da rede dele; o Admin vê
   todos.
5. **Given** o Admin e afiliados sem gerente responsável, **When** ele abre a lista, **Then** esses
   afiliados aparecem em destaque ("Sem gerente responsável") e há o filtro "Sem gerente".
6. **Given** um afiliado de fora da rede, **When** o usuário tenta abri-lo ou agir sobre ele por
   acesso direto, **Then** recebe "Afiliado não encontrado.".

---

### User Story 2 - Cadastrar e editar afiliados (Priority: P1)

O usuário cadastra um afiliado (o antigo "Novo Filiado") com dados pessoais, contato, endereço, PIX,
seguidores, "Sobre você", gerente e regra de ganho, e depois edita os dados, a situação e a senha.

**Why this priority**: sem a tela, a banca não tem como cadastrar nem corrigir afiliados.

**Independent Test**: como Gerente, cadastrar um afiliado com "Qualquer depósito" a 10%; conferir que
ele aparece Ativo na lista com o Gerente como responsável e a regra escolhida; editar o telefone e a
senha, entrar como o afiliado com a senha nova e conferir que a antiga deixou de valer.

**Acceptance Scenarios**:

1. **Given** `afiliados.cadastrar`, **When** o usuário escolhe "Cadastrar", **Then** abre o modal em
   grade com as seções Dados pessoais (nome, login, senha e confirmação, e-mail, CPF), Contato e
   endereço (telefone, cidade, estado, endereço), PIX (tipo e chave), Perfil (rede social
   principal, link do perfil, quantidade de seguidores e "Sobre você") e Ganho (gerente, tipo de ganho e percentual), com os botões Cancelar
   e Salvar no rodapé.
2. **Given** o modal preenchido, **When** o usuário salva, **Then** o afiliado é gravado Ativo, com
   origem Painel e a regra escolhida, e a lista é atualizada; **When** algum campo é recusado,
   **Then** a mensagem do servidor aparece no campo.
3. **Given** um Gerente, **When** ele cadastra, **Then** o campo gerente não aparece e ele mesmo fica
   responsável; Admin e Supervisor escolhem o gerente de uma lista dos gerentes da rede deles
   (obrigatório).
4. **Given** um afiliado, **When** o usuário com `afiliados.editar` escolhe "Editar", **Then** abre
   o mesmo modal preenchido, sem o login (só leitura) e sem a regra de ganho (troca própria, US4),
   com a situação (Ativo ou Inativo) como campo de seleção para quem tem
   `afiliados.alterar_situacao`, e os campos "Nova senha" e "Confirmar nova senha" vazios.
5. **Given** a edição com nova senha preenchida, **When** o usuário salva, **Then** a senha do
   afiliado muda e as sessões abertas dele deixam de valer na hora; com os campos vazios, a senha
   não muda.
6. **Given** a edição, **When** o usuário troca a situação para Inativo e salva, **Then** o afiliado
   não consegue mais entrar e os clientes dele continuam ligados.
7. **Given** um usuário sem `clientes.ver_dados_completos`, **When** abre a edição, **Then** CPF,
   e-mail, telefone e chave PIX aparecem mascarados e só podem ser substituídos, não lidos.

---

### User Story 3 - Aprovar ou recusar cadastros públicos (Priority: P1)

O Admin abre a aba "Em análise", vê os afiliados que se cadastraram pelo site, com os seguidores e o
"Sobre você", e aprova (escolhendo gerente e regra de ganho) ou recusa (com motivo).

**Why this priority**: sem a aprovação pela tela, o cadastro público da spec 012 não tem saída; o
afiliado fica parado "Em análise".

**Independent Test**: fazer um cadastro público com 5.000 seguidores; abrir a aba Em análise e
conferir os dados; aprovar com um gerente e "Saldo líquido" a 20%; entrar como o afiliado; fazer
outro cadastro público e recusá-lo com motivo; conferir os e-mails.

**Acceptance Scenarios**:

1. **Given** cadastros públicos em análise, **When** o Admin abre a aba Em análise,
   **Then** vê a lista (nome, login, e-mail, telefone, cidade/estado, rede social, quantidade de
   seguidores, link do perfil, trecho de "Sobre você" e data do cadastro), da mais antiga para a mais nova, com a escolha Em
   análise (padrão) ou Recusados.
2. **Given** um cadastro em análise ou recusado, **When** o usuário escolhe "Aprovar", **Then** abre o
   modal com gerente responsável, tipo de ganho e percentual, todos obrigatórios.
3. **Given** o modal de aprovação preenchido, **When** o usuário confirma, **Then** o afiliado vira
   Ativo com o gerente e a regra escolhidos, recebe o e-mail de aprovação, sai da aba Em análise e
   aparece em Ativos.
4. **Given** um cadastro em análise, **When** o usuário escolhe "Recusar", informa o motivo
   (opcional) e confirma, **Then** o afiliado vira Recusado, recebe o e-mail com o motivo e aparece
   na escolha Recusados, de onde pode ser aprovado depois.
5. **Given** um Supervisor ou Gerente, **When** ele abre Afiliados, **Then** a aba Em análise e o
   cartão Em análise não aparecem, e um acesso direto a aprovar ou recusar é recusado como
   "Afiliado não encontrado.".
6. **Given** um cadastro em análise, **When** o usuário toca na linha, **Then** abre a gaveta com
   todos os dados, inclusive o "Sobre você" completo.

---

### User Story 4 - Ver o afiliado e trocar a regra de ganho (Priority: P1)

O usuário toca num afiliado (o antigo "acessar transações") e abre a gaveta com os dados, o extrato
de lançamentos, os clientes indicados, as prestações e o histórico de regras; quem tem
`afiliados.editar_comissao` troca a regra de ganho.

**Why this priority**: é o atendimento ao afiliado ("por que minha comissão deu isso?") e a
conferência antes de pagar; substitui o "acessar transações" do antigo sem entrar na área dele.

**Independent Test**: com um afiliado com depósito e saque de clientes no mês, abrir a gaveta e
conferir o extrato (filtrar "Em aberto"), os clientes e as prestações; trocar a regra de "Qualquer
depósito" 10% para "Saldo líquido" 15% e conferir o histórico com a regra antiga encerrada.

**Acceptance Scenarios**:

1. **Given** um afiliado na lista, **When** o usuário toca na linha ou escolhe "Ver detalhe", **Then**
   abre a gaveta com as abas Dados, Extrato, Clientes, Prestações e Regras, e no topo o nome, o
   código, a situação, a regra vigente e o valor a receber.
2. **Given** a aba Dados, **When** ela abre, **Then** mostra dados pessoais, contato e endereço
   (mascarados conforme a permissão), PIX, rede social, link do perfil, quantidade de seguidores,
   "Sobre você", origem,
   gerente, quem cadastrou, quem aprovou ou recusou (com data e motivo) e as datas de cadastro e
   alteração.
3. **Given** a aba Extrato, **When** o usuário filtra por período e por Em aberto, Prestados ou
   Todos, **Then** vê os lançamentos (data, cliente, evento, base, percentual e comissão), do mais
   novo para o mais antigo, com a soma do filtro.
4. **Given** a aba Clientes, **When** ela abre, **Then** mostra os clientes indicados (ID, nome,
   contato mascarado conforme a permissão, data do cadastro e total depositado), com atalho para o
   detalhe do cliente da tela Clientes (spec 019) para quem tem `clientes.listar`.
5. **Given** a aba Prestações, **When** ela abre, **Then** mostra cada prestação (data, período,
   valor apurado, valor pago, quantidade de lançamentos, autor e observação), da mais nova para a
   mais antiga.
6. **Given** a aba Regras, **When** o usuário com `afiliados.editar_comissao` escolhe "Trocar
   regra", informa tipo de ganho e percentual e confirma, **Then** a regra vigente é encerrada
   naquele momento, a nova passa a valer para os próximos lançamentos e o histórico mostra as
   duas, com quem definiu e as datas.
7. **Given** um usuário sem `afiliados.editar_comissao`, **When** abre a aba Regras, **Then** vê o
   histórico sem o botão "Trocar regra".

---

### User Story 5 - Prestar contas (Priority: P1)

O usuário escolhe "Prestar contas" num afiliado, vê quanto será apurado e quanto será pago, informa
observação e a própria senha e confirma; o valor a receber zera e a prestação vai para o histórico.

**Why this priority**: é o pagamento do afiliado; sem a tela, só pela API.

**Independent Test**: com um afiliado com R$ 85,00 a receber, prestar contas com observação "Pago via
Pix" e a senha; conferir que a lista mostra R$ 0,00 a receber e a última prestação com a data de
agora; conferir na gaveta a prestação e os lançamentos marcados como prestados; repetir com senha
errada e conferir que nada muda.

**Acceptance Scenarios**:

1. **Given** um afiliado fora da lixeira, **When** o usuário com `afiliados.prestar_contas` escolhe
   "Prestar contas", **Then** abre o modal com a quantidade de lançamentos em aberto, o valor
   apurado, o valor que será pago (zero quando o apurado for negativo, com o aviso de que o saldo
   negativo será zerado), a chave PIX do afiliado (conforme a permissão), a observação e a senha.
2. **Given** a senha correta, **When** o usuário confirma, **Then** a prestação é gravada com a data
   e a hora da confirmação, os lançamentos em aberto até esse momento ficam prestados, o valor a
   receber vai a zero e a lista, os cartões e a gaveta são atualizados.
3. **Given** a senha errada, **When** o usuário confirma, **Then** nada muda e aparece "Senha
   incorreta." no campo.
4. **Given** um afiliado sem lançamentos em aberto, **When** o usuário presta contas, **Then** a
   prestação é gravada com valores zero, como na spec 012.
5. **Given** um Gerente, **When** presta contas de um afiliado dele, **Then** funciona; de um
   afiliado de fora da rede, é recusado como inexistente.

---

### User Story 6 - Copiar o link, excluir e restaurar (Priority: P2)

O usuário copia o link de indicação do afiliado, exclui um afiliado para a lixeira com a própria
senha e, na aba Lixeira, restaura.

**Why this priority**: operações do antigo menos frequentes que a consulta, o cadastro e a
prestação.

**Independent Test**: copiar o link e abrir numa janela anônima, conferindo o código no cadastro de
cliente; excluir com a senha e conferir que o afiliado sai de Ativos e aparece na Lixeira; restaurar
e conferir que volta com a situação anterior.

**Acceptance Scenarios**:

1. **Given** um afiliado, **When** o usuário escolhe "Copiar link", **Then** o endereço
   `<site>/?user=CODIGO` vai para a área de transferência com a confirmação "Link copiado."; se o
   afiliado não está Ativo, a confirmação avisa que o link só liga clientes quando ele estiver
   Ativo.
2. **Given** Admin ou Supervisor com `afiliados.excluir`, **When** escolhe "Excluir", **Then** a
   confirmação mostra o aviso "Este afiliado tem R$ X a receber." quando houver valor em aberto;
   **When** informa a senha e confirma, **Then** o afiliado vai para a lixeira, as sessões dele deixam de valer, o
   código deixa de ligar novos clientes e a lista e os cartões são atualizados; com a senha
   errada, nada muda e aparece "Senha incorreta.".
3. **Given** a aba Lixeira (visível para quem tem `afiliados.restaurar`), **When** o usuário escolhe
   "Restaurar" e confirma, **Then** o afiliado volta com a situação que tinha.
4. **Given** um afiliado na lixeira cujo login, e-mail, CPF ou código já foi usado por outro,
   **When** o usuário restaura, **Then** abre o pedido do novo valor só para o campo em conflito,
   com a mensagem do servidor, e a restauração acontece quando o valor novo é aceito.
5. **Given** um Gerente, **When** ele abre o menu, **Then** Excluir não aparece e a aba Lixeira não
   aparece; um acesso direto é recusado.
6. **Given** a lixeira, **When** o usuário abre o menu de um afiliado, **Then** não existe "Excluir
   para sempre".

---

### User Story 7 - Seguidores e "Sobre você" no cadastro público e em "Meus dados" (Priority: P2)

O afiliado informa a rede social principal, o link do perfil, a quantidade de seguidores e um texto
sobre ele ao se cadastrar pelo site e pode alterá-los em "Meus dados" no painel `/afiliados`.

**Why this priority**: ajuda a banca a decidir a aprovação, mas o cadastro funciona sem a tela da
gestão.

**Independent Test**: abrir `/cadastro-afiliado`, tentar enviar sem seguidores e com um link sem
`https://` e conferir as recusas; enviar com Instagram, o link do perfil, 12.000 seguidores e um
texto; conferir na aba Em análise que o link abre o perfil; aprovar; entrar como o afiliado, alterar para 15.000 em
"Meus dados" e conferir o valor novo na gaveta da gestão.

**Acceptance Scenarios**:

1. **Given** o modal de cadastro público, **When** ele abre, **Then** tem os campos "Rede social
   principal" (seleção, obrigatório), "Link do perfil" (obrigatório), "Quantidade de seguidores"
   (obrigatório, só números) e "Sobre você" (opcional, com o contador de caracteres), no padrão
   visual do modal da spec 012.
2. **Given** rede social vazia, link do perfil vazio ou sem `http://`/`https://`, ou seguidores
   vazio, negativo ou com letras, **When** o afiliado envia, **Then** o campo mostra a mensagem do
   servidor e nada é gravado.
3. **Given** "Sobre você" com mais caracteres que o limite, **When** o afiliado digita, **Then** o
   campo não aceita além do limite, e o servidor também recusa.
4. **Given** "Meus dados" no painel do afiliado, **When** ele altera a rede, o link, os seguidores
   ou o texto e salva,
   **Then** os valores novos ficam gravados e aparecem na gestão.

---

### Edge Cases

- Afiliado com saldo apurado negativo (Saldo líquido com mais saques que depósitos): "A receber"
  mostra o valor negativo em vermelho; a prestação paga zero e zera o saldo (spec 012).
- Afiliado sem regra de ganho (cadastro público feito depois desta spec, ainda em análise ou
  recusado): a coluna Ganho mostra "A definir"; ele não gera lançamentos, porque só afiliado Ativo
  liga clientes novos, e ganha a regra na aprovação.
- Cadastro público feito antes desta spec (já com a regra padrão antiga): a aprovação abre com essa
  regra preenchida, que pode ser trocada.
- Afiliado migrado sem e-mail, CPF ou telefone: aparece "Não informado"; esses campos só passam a
  ser exigidos na edição quando alterados (regra da spec 012).
- Afiliado já existente sem rede social, link do perfil ou quantidade de seguidores: aparece "Não
  informado" e pode ser salvo assim na edição; depois de informado, cada um não pode voltar a vazio.
- Rede social "Outra": o link do perfil continua obrigatório e mostra de qual rede se trata.
- Duas prestações do mesmo afiliado ao mesmo tempo (duas abas): só uma fecha os lançamentos; a outra
  grava zero ou recebe a mensagem do servidor (spec 012, FR-040).
- Lançamento novo durante a abertura do modal de prestação: a prestação fecha o que estiver em aberto
  na hora da confirmação, e o valor gravado aparece na confirmação de sucesso.
- Afiliado que muda de gerente: sai da rede do gerente antigo e entra na do novo na hora; os
  clientes dele vão junto (spec 019).
- Afiliado excluído com promoção ou clientes ligados: continuam ligados; o código só deixa de ligar
  clientes novos.
- Período personalizado com "de" maior que "até", ou maior que 1 ano: recusado no próprio campo.
- Copiar link sem permissão de área de transferência no navegador: mostra o link num campo para
  copiar à mão.
- Permissão retirada com a tela aberta: a próxima ação é recusada com a mensagem do servidor e a ação
  some ao recarregar.
- Sessão expirada: leva ao login como no restante do painel.
- Celular: as linhas viram cartões (nome e código, a receber e situação) com o restante ao
  expandir; gaveta e modais ocupam a tela toda.

## Requirements *(mandatory)*

### Functional Requirements

**Acesso e abas**

- **FR-001**: O menu do painel DEVE ter o item "Afiliados", visível para quem tem
  `afiliados.listar`, levando à tela `/painel/afiliados` com as abas **Ativos** (afiliados Ativos e
  Inativos), **Em análise** (só para o Admin com `afiliados.alterar_situacao`) e
  **Lixeira** (só para quem tem `afiliados.restaurar`).
- **FR-002**: Cada nível DEVE ver e agir só sobre os afiliados da própria rede, como na spec 019
  (FR-003d): **Admin**, todos, inclusive os sem gerente; **Supervisor**, os dos gerentes da rede
  dele; **Gerente**, os de que é o gerente responsável; **Vendedor** não acessa a tela. Qualquer
  consulta ou ação fora da rede DEVE ser respondida como "Afiliado não encontrado.".
- **FR-003**: Os cadastros públicos em análise ou recusados, que ainda não têm gerente, DEVEM
  aparecer só para o Admin, na aba Em análise, sem exceção à regra de rede (FR-002). Supervisor e
  Gerente NÃO DEVEM vê-los na tela, nos cartões nem por acesso direto.

**Cartões e lista**

- **FR-004**: A tela DEVE mostrar os cartões **Total de afiliados** (Ativos e Inativos), **Em
  análise** (quantidade de cadastros em análise; só para o Admin), **Comissão do período**
  (soma da comissão gerada no período) e **A receber** (soma dos lançamentos em aberto), calculados
  no servidor em reais com duas casas, sem ponto flutuante, respeitando a rede, a busca, o gerente e
  o período.
- **FR-005**: A aba Ativos DEVE listar os afiliados não excluídos Ativos e Inativos, ordenados por
  nome, paginada (padrão 20, máximo 100), com o total do filtro no cabeçalho e a escolha de
  situação Todos (padrão), Ativos ou Inativos.
- **FR-006**: Cada linha DEVE mostrar: ID, nome e código, gerente (ou "Sem gerente responsável", em
  destaque, só para o Admin), ganho (tipo e percentual, ou "A definir"), e, no período: cadastros
  (clientes ligados), depósitos aprovados, saques pagos e comissão gerada; **Entradas** (soma das
  bases dos lançamentos em aberto, a coluna do antigo), **A receber** (soma dos lançamentos em
  aberto, o antigo "Apurado"), **Última prestação** (data e valor pago, o antigo "Baixa"; "Nunca"
  quando não houver) e situação com cor.
- **FR-007**: A lista DEVE ter busca por nome, login ou código e o painel de filtros com situação,
  gerente (escolhido de uma lista dos gerentes da rede, só para Admin e Supervisor), "Sem gerente"
  (só para o Admin) e período (atalhos Hoje, Ontem, Esta semana, Este mês e Personalizado, até 1
  ano; padrão Este mês, do dia 1 até hoje), com etiquetas removíveis. "A receber" e "Entradas" não
  dependem do período.
- **FR-008**: Os filtros, a busca, a aba e a página DEVEM ficar no endereço da tela, para que
  recarregar ou voltar da gaveta mantenha a mesma lista.
- **FR-009**: O menu de ações (três pontinhos) DEVE ter, conforme a permissão, a aba e a situação:
  na aba Ativos, Ver detalhe, Editar, Ativar/Inativar, Trocar regra, Prestar contas, Copiar link e
  Excluir; na aba Em análise, Ver detalhe, Aprovar e Recusar (Recusar só para Em análise); na
  Lixeira, Ver detalhe e Restaurar. Tocar na linha abre a gaveta. Ativar/Inativar pede confirmação.

**Cadastro e edição**

- **FR-010**: O modal de cadastro DEVE ter, em grade de duas colunas no computador (uma no
  celular) e agrupados por seção, os campos: **Dados pessoais** (nome, login, senha e confirmação,
  e-mail, CPF); **Contato e endereço** (telefone com DDI, cidade, estado, endereço); **PIX** (tipo
  e chave, com a máscara conforme o tipo); **Perfil** (rede social principal, link do perfil,
  quantidade de seguidores, "Sobre você");
  **Ganho** (gerente responsável, tipo de ganho e percentual). Os botões Cancelar e Salvar DEVEM
  ficar no rodapé fixo do modal, alinhados à direita (lado a lado e com a largura toda no celular).
- **FR-011**: Os campos de escolha DEVEM ser campos de seleção: estado, tipo de PIX, rede social, gerente (lista
  dos gerentes da rede, constituição "Campos de itens existentes"), tipo de ganho (Saldo líquido,
  Qualquer depósito, Primeiro depósito) e, na edição, a situação (Ativo ou Inativo). NÃO DEVE haver
  interruptor (toggle) nos modais desta tela. O percentual DEVE ser um campo de 0 a 100 com duas
  casas.
- **FR-012**: O cadastro pelo painel DEVE criar o afiliado Ativo, com origem Painel e a regra de
  ganho informada, que é obrigatória para quem tem `afiliados.cadastrar` (inclusive o Gerente). O
  Gerente fica sempre como responsável (o campo gerente não aparece para ele); Admin e Supervisor
  escolhem um gerente da rede deles (obrigatório, spec 019). Muda a spec 012 (FR-008), em que sem
  `afiliados.editar_comissao` valia a regra padrão da configuração.
- **FR-013**: O modal de edição DEVE ter os mesmos campos e seções do cadastro, com estas
  diferenças: login só leitura; sem a seção Ganho de tipo e percentual (troca própria, FR-019), mas
  com o gerente (só Admin e Supervisor, não pode ser retirado); a situação Ativo/Inativo como
  seleção, só para quem tem `afiliados.alterar_situacao` e só para afiliados Ativos ou Inativos; o
  código público editável só para Admin e Supervisor (regra da spec 012); e os campos "Nova senha"
  e "Confirmar nova senha", opcionais (vazios, a senha não muda; preenchidos, seguem a regra de
  senha e encerram as sessões do afiliado).
- **FR-014**: Na edição, CPF, e-mail, telefone e chave PIX DEVEM chegar mascarados para quem não tem
  `clientes.ver_dados_completos` (spec 012, FR-046); o campo mostra o valor mascarado e só é
  enviado quando o usuário digita um valor novo.
- **FR-015**: As validações e mensagens DEVEM ser as do servidor de hoje (spec 012 e 019),
  mostradas no campo que as causou; o modal não fecha com erro.

**Seguidores e "Sobre você"**

- **FR-016**: O afiliado DEVE ganhar os dados **quantidade de seguidores** (número inteiro de 0 a
  999.999.999), **rede social principal** (seleção: Instagram, TikTok, YouTube, Facebook, Kwai, X
  ou Outra), **link do perfil** (endereço começando com `http://` ou `https://`, até 255
  caracteres) e **sobre você** (texto livre de até 1.000 caracteres). Seguidores, rede social e
  link do perfil são obrigatórios nos cadastros novos (público e painel); nos afiliados já
  existentes ficam vazios ("Não informado") até serem preenchidos e, depois de preenchidos, não
  podem voltar a vazio. "Sobre você" é opcional.
- **FR-017**: O cadastro público (`/cadastro-afiliado` e o modal de cadastro) e "Meus dados" no painel
  `/afiliados` DEVEM ter os quatro campos, no padrão visual da spec 012, e o afiliado DEVE poder
  alterá-los em "Meus dados". Muda a spec 012 (FR-010, FR-053 e FR-056).
- **FR-018**: A gestão DEVE ver os quatro na gaveta e na aba Em análise (rede, seguidores e o link
  do perfil, que abre em nova aba; o texto resumido na linha e completo na gaveta) e editá-los no
  modal (`afiliados.editar`).

**Regra de ganho**

- **FR-019**: A troca de regra DEVE ser uma ação própria (menu "Trocar regra" e botão na aba Regras
  da gaveta), só para quem tem `afiliados.editar_comissao`, com modal de tipo de ganho e
  percentual, mostrando a regra vigente e o aviso de que a nova vale a partir de agora para os
  próximos lançamentos; os lançamentos já gravados não mudam (spec 012, FR-028).
- **FR-020**: A regra padrão do cadastro público DEVE sair das Configurações gerais: some a seção
  Afiliados (ou os campos dela) da aba Operação das Configurações (spec 021) e a rota de
  configuração de afiliados (spec 012, `GET/PUT /configuracoes/afiliados`). O cadastro público
  DEVE criar o afiliado "Em análise" sem regra de ganho. Muda as specs 012 (FR-011 e FR-015) e
  021 (FR-028).

**Aprovação e recusa**

- **FR-021**: A aba Em análise DEVE listar os cadastros públicos com a escolha Em análise (padrão)
  ou Recusados, da mais antiga para a mais nova, paginada (padrão 20, máximo 100), com nome, login,
  e-mail e telefone (mascarados conforme a permissão), cidade/estado, rede social, quantidade de
  seguidores, link do perfil, trecho de "Sobre você" e data do cadastro; nos recusados, também quem recusou, quando e o motivo.
- **FR-022**: Aprovar DEVE exigir gerente responsável (lista dos gerentes da rede), tipo de ganho e
  percentual, todos obrigatórios; se o afiliado já tiver uma regra (cadastros anteriores a esta
  spec), ela vem preenchida. Muda a spec 012 (FR-014, em que gerente e regra eram opcionais na
  aprovação). Aprovar é permitido para Em análise e Recusado.
- **FR-023**: Recusar DEVE pedir confirmação com o motivo opcional (até 255 caracteres) e só valer
  para Em análise. Aprovar e recusar DEVEM ser só do Admin com `afiliados.alterar_situacao`, com
  os e-mails da spec 012; o servidor DEVE recusar o Supervisor também por acesso direto. Muda a
  spec 012 (FR-014 e FR-051), em que o Supervisor também aprovava e recusava.

**Gaveta do afiliado**

- **FR-024**: A gaveta (tela cheia no celular) DEVE ter no topo nome, código (com o botão de copiar o
  link), situação, regra vigente e valor a receber, e as abas **Dados**, **Extrato**, **Clientes**,
  **Prestações** e **Regras**, cada uma carregada ao ser aberta e paginada (padrão 30, máximo 100).
- **FR-025**: **Dados**: dados pessoais, contato, endereço e PIX (mascarados conforme a permissão),
  rede social, link do perfil, seguidores, "Sobre você", origem, gerente, quem cadastrou, quem
  aprovou ou recusou (data e
  motivo) e as datas de cadastro e alteração. **Extrato**: lançamentos com data, cliente (ID e
  nome), evento, base, percentual e comissão, filtro de período e de Em aberto, Prestados ou
  Todos, e a soma do filtro. **Clientes**: clientes indicados com ID, nome, contato (mascarado
  conforme a permissão), data do cadastro e total depositado, e o atalho para o detalhe do cliente
  (spec 019) para quem tem `clientes.listar`. **Prestações**: data, período, valor apurado, valor
  pago, quantidade de lançamentos, autor e observação. **Regras**: histórico com tipo, percentual,
  vigência (início e fim) e quem definiu.
- **FR-026**: A gaveta DEVE exigir `afiliados.consultar`; sem ela, a linha não abre a gaveta e o
  menu não mostra "Ver detalhe".

**Prestação de contas**

- **FR-027**: O modal de prestação DEVE mostrar, antes de confirmar, a quantidade de lançamentos em
  aberto, o valor apurado, o valor que será pago (zero quando o apurado é negativo, com o aviso) e
  a chave PIX do afiliado (mascarada conforme a permissão), e pedir a observação (opcional, até 500
  caracteres, limite atual da spec 012) e a senha de quem presta contas.
- **FR-028**: A prestação DEVE usar a data e a hora da confirmação (sem data livre, diferença em
  relação ao antigo), seguir as regras da spec 012 (FR-035 a FR-041: fecha os lançamentos em
  aberto até o momento, zera o negativo, pode ser zerada, não se edita nem se exclui) e recusar a
  senha errada com "Senha incorreta." sem gravar nada.

**Exclusão e restauração**

- **FR-029**: Excluir DEVE pedir a confirmação com a senha de quem exclui; o servidor DEVE conferir a
  senha antes de qualquer mudança e recusar com "Senha incorreta." sem gravar nada, também por
  acesso direto. Muda a rota de exclusão atual (spec 012, FR-048), que não pedia senha. As demais
  regras da exclusão continuam (lixeira, sufixo nos dados únicos, sessões encerradas, só Admin e
  Supervisor com `afiliados.excluir`). NÃO DEVE existir exclusão definitiva. Quando o afiliado
  tiver lançamentos em aberto com valor diferente de zero, a confirmação DEVE avisar "Este afiliado
  tem R$ X a receber." (ou o saldo negativo) e permitir excluir mesmo assim.
- **FR-030**: A aba Lixeira DEVE listar os afiliados excluídos, com os mesmos filtros e colunas da aba
  Ativos (inclusive o valor a receber que ficou em aberto) e a data da exclusão; a prestação de
  contas de afiliado na lixeira continua recusada (spec 012) e só pode ser feita depois de
  restaurar; Restaurar pede confirmação e, quando o servidor indicar conflito em
  login, e-mail, CPF ou código, DEVE pedir o valor novo só do campo em conflito e tentar de novo.

**Link de indicação**

- **FR-031**: "Copiar link" DEVE copiar `<endereço do site>/?user=CODIGO` com o código público (não o
  login, diferença em relação ao antigo), com a confirmação "Link copiado." e, para afiliado que não
  está Ativo, o aviso de que o link só liga clientes quando ele estiver Ativo.

**Permissões**

- **FR-032**: As permissões continuam as da spec 012 (`afiliados.listar`, `consultar`, `cadastrar`,
  `editar`, `alterar_situacao`, `excluir`, `restaurar`, `prestar_contas` e `editar_comissao`), com os
  mesmos padrões por função; não há permissão nova. Mudam só o que a regra inicial no cadastro exige
  (FR-012) e quem aprova e recusa (só o Admin, FR-023). As permissões e o alcance DEVEM ser aplicados no servidor em toda consulta e ação, e as
  ações sem permissão NÃO DEVEM aparecer na tela.

**Visual, responsividade e padrões do painel**

- **FR-033**: A tela DEVE seguir os padrões do painel (specs 014 a 021 e constituição): cores do tema,
  cabeçalho do painel, abas do painel, cartões de valor, filtro de período do painel, painel de
  filtros com etiquetas, gaveta de detalhe, cabeçalho padrão nos modais (`ModalHeader`), campos com
  rótulo em cima, campo de valor em reais, paginação e menu de três pontinhos nas ações.
- **FR-034**: No celular (até 900 px), as linhas DEVEM virar cartões (nome e código, a receber e
  situação) com o restante ao expandir; a gaveta e os modais ocupam a tela toda, com uma coluna de
  campos; os filtros abrem num painel próprio; nada pode exigir rolagem horizontal.
- **FR-035**: Cada área (cartões, lista, gaveta e cada aba dela) DEVE ter estados de carregando
  (esqueleto), vazio (com orientação) e erro com "tentar novamente", sem travar as outras.
- **FR-036**: Toda ação DEVE dar retorno imediato (botão com carregando e sem clique duplo,
  confirmação de sucesso ou mensagem do servidor) e atualizar a lista, os cartões e a gaveta sem
  recarregar a página nem perder os filtros e a página.

**Documentação**

- **FR-037**: A coleção do Postman DEVE ser regenerada com as rotas novas e alteradas (listagem com os
  totais novos, cadastro com a regra obrigatória e os campos novos, aprovação com gerente e regra
  obrigatórios, exclusão com senha, cadastro público e "Meus dados" com os campos novos, e a
  remoção da configuração de afiliados).

### Key Entities

- **Afiliado** (existente, `afiliados`, spec 012): ganha a rede social principal, o link do perfil,
  a quantidade de seguidores e o "sobre você". Pode ficar sem regra de ganho enquanto está Em análise ou Recusado. A rede dele vem do
  gerente responsável.
- **Regra de ganho do afiliado** (existente, `afiliados_comissoes`): passa a ser criada por quem
  cadastra ou aprova; continua só uma vigente por vez.
- **Lançamento do afiliado** e **Prestação de contas do afiliado** (existentes): vistos na lista
  (Entradas, A receber, Última prestação), na gaveta e no modal de prestação; sem mudança de
  estrutura.
- **Configuração** (existente): perde a regra de ganho padrão do cadastro público.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Um usuário cadastra um afiliado completo pelo painel em menos de 3 minutos a partir do
  menu.
- **SC-002**: O Admin aprova um cadastro público (escolhendo gerente e regra) em até 4
  toques a partir da aba Em análise.
- **SC-003**: Em 100% das conferências manuais, "A receber" e "Entradas" de um afiliado batem com a
  soma dos lançamentos em aberto na gaveta, e o valor pago na prestação bate com o apurado (ou zero
  quando negativo).
- **SC-004**: Em 100% dos testes, uma prestação ou exclusão com senha errada não muda nada, e nenhum
  lançamento é prestado duas vezes.
- **SC-005**: Nenhuma ação ou aba aparece para quem não tem a permissão dela, todo acesso direto sem
  permissão é recusado pelo servidor, e em nenhum teste um Supervisor ou Gerente vê, edita ou presta
  contas de afiliado de fora da rede dele.
- **SC-006**: Um gestor do sistema antigo encontra na tela nova cada informação e ação da "Lista de
  filiados" (colunas, copiar link, editar, transações, prestar contas, excluir e restaurar).
- **SC-007**: Nenhum modal desta tela tem interruptor, e todos mostram os campos agrupados por seção,
  sem rolagem horizontal, em 360 px de largura.
- **SC-008**: A tela funciona no celular (360 px de largura) com todos os alvos de toque com pelo
  menos 40 px; abrir a tela, trocar de aba ou de filtro mostra a primeira página em até 2 segundos
  numa conexão comum.

## Assumptions

- A gestão (listar, consultar, cadastrar, editar, situação, aprovar, recusar, trocar regra,
  extrato, clientes, prestações, regras, prestação de contas, excluir e restaurar), a rede da spec
  019, os e-mails e a área do afiliado já existem (specs 012 e 019); esta spec os mostra e chama,
  mudando a regra obrigatória no cadastro e na aprovação, a senha na exclusão, a remoção da regra
  padrão e os campos novos, e acrescentando os cartões e a coluna Entradas.
- Os números do período (cadastros, depósitos, saques e comissão) seguem as regras de cálculo da
  spec 012.
- Os limites dos campos novos (seguidores até 999.999.999; link do perfil até 255 caracteres;
  "Sobre você" até 1.000 caracteres) são
  padrões razoáveis e podem ser ajustados no `/speckit-clarify`.
- Quem tem `afiliados.cadastrar` define a regra inicial no cadastro sem precisar de
  `afiliados.editar_comissao`, que continua necessária para trocar a regra depois.
- Não há exportação em planilha nesta tela.
- Datas no fuso do sistema (Brasília).
- Não há dados fake nesta spec.
- Sem testes automatizados (constituição); a validação é manual pelo quickstart da spec.
