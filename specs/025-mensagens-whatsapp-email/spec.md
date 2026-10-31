# Feature Specification: Disparo de mensagens por WhatsApp e e-mail

**Feature Branch**: `025-mensagens-whatsapp-email`

**Created**: 2026-10-31

**Status**: Draft

**Input**: User description: "Finalizar o disparo de mensagens aos usuários do sistema por WhatsApp, usando o robô da Evolution API já conectado pelo painel (spec 021), e por e-mail, usando as credenciais de e-mail que já existem no .env. Hoje o WhatsApp só registra as mensagens no log, inclusive o código de recuperação de senha em texto puro, e não há envio pelo robô. Já prometido nas specs: spec 002 (boas-vindas e código de recuperação por WhatsApp) e spec 021 (mensagens pelo robô substituem o CallMeBot da spec 014 e tratam o alerta ao Admin quando o robô desconecta)."

## Clarifications

### Session 2026-10-31

- Q: Quais mensagens ao cliente entram? → A: Só boas-vindas e código de recuperação. Prêmio, saque e depósito continuam na notificação push onde ela já existe.
- Q: Para onde vai o código de recuperação com o robô fora? → A: Para o e-mail do cliente, se ele tiver. Sem e-mail, o código não chega até o robô voltar e o cliente pede outro.
- Q: Como o Admin é avisado de um saque? → A: Depois de 30 minutos parado, uma vez por saque, com cliente, valor, chave Pix e link para o painel com login. A baixa sem login do antigo não é trazida.
- Diferenças aprovadas em relação ao antigo: Evolution API no lugar da Z-API, telefone do Admin configurável, aviso único por saque, baixa só com login, botão de teste dos canais e registro de envios.

- Q: O CallMeBot continua em algum lugar? → A: Sai do sistema, que passa a usar a Evolution API. Continua só no aviso do deploy no GitHub, que não muda.
- Q: Quem define o canal de cada mensagem? → A: O Admin, em Configurações: WhatsApp, e-mail ou os dois, mensagem por mensagem.
- Q: Os e-mails precisam de telas novas? → A: Sim. Cada mensagem que pode sair por e-mail tem o seu modelo, simples e fácil de entender.
- Q: De onde vêm as credenciais de e-mail? → A: Do `.env` do sistema antigo, copiadas para o `.env` deste sistema.
- Q: Quando um aviso ao Admin não puder sair pelo canal escolhido, ele deve ir pelo outro canal? → A: Sim, se o campo do outro canal estiver preenchido; só fica como falhou se os dois falharem.
- Q: O aviso de saque parado deve sair a qualquer hora ou só das 6h às 23h, como no sistema antigo? → A: A qualquer hora, assim que o saque completar 30 minutos parado.
- Q: As boas-vindas precisam confirmar o e-mail? → A: Sim, quando o painel exigir confirmação de cadastro: a mensagem leva um botão para o cliente validar o e-mail. Isso substitui a decisão da spec 012 de não trazer a confirmação por link.
- Q: Qual é o padrão da confirmação de cadastro? → A: Desligada. O cliente já nasce ativo e entra direto, e recebe as boas-vindas do mesmo jeito, sem o botão de confirmação.

## Referências do sistema antigo *(Princípio VII)*

Consultado `wssports.bet`, `main` `408cf730`, somente leitura:

- `app/Jobs/EnviarMensagemWhatsApp.php`: fila própria, 3 tentativas (10, 30 e 60 segundos), texto, imagem e vídeo, pela Z-API.
- `app/Http/Controllers/WhatsAppController.php`: situação, QR code, desconexão e envio; todas as rotas, inclusive a de envio, ficavam sem login.
- `app/Console/Commands/VerificarSaquesCommand.php`: a cada 30 minutos, das 6h às 23h, avisava de saques com mais de 30 minutos. Mandava três mensagens (aviso, chave Pix e link de baixa com token) para dois telefones fixos no código, com domínio fixo no link.
- `app/Http/Controllers/CadastroController.php` e `app/Mail/RecuperarSenha.php`: o cliente recuperava a senha por link enviado ao e-mail. `CadastroMail` e `FiliadoMail` cuidavam dos e-mails de cadastro.
- `resources/js/screens/adm/components/header`: consultava o robô a cada 5 segundos e mostrava "Robô do whats app desconectado." na tela.

Problemas que não serão repetidos: rota de envio aberta, telefones e domínio fixos no código e baixa de saque sem login.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Cliente recupera a senha (Priority: P1)

O cliente que esqueceu a senha informa o telefone e recebe o código de 6 dígitos para criar uma senha nova.

**Why this priority**: hoje o código não chega a ninguém, e o cliente sem senha não consegue entrar nem sacar. O código também fica exposto no log.

**Independent Test**: com o robô conectado, pedir a recuperação com o telefone de um cliente, receber o código no WhatsApp, redefinir a senha e conferir que o código não aparece no log.

**Acceptance Scenarios**:

1. **Given** o robô conectado e um cliente ativo, **When** ele pede a recuperação, **Then** recebe o código no WhatsApp em até 1 minuto.
2. **Given** o robô indisponível e um cliente com e-mail, **When** ele pede a recuperação, **Then** o código vai para o e-mail dele em até 1 minuto depois de o WhatsApp falhar.
3. **Given** um telefone sem cadastro, **When** alguém pede a recuperação, **Then** a resposta é a mesma de um telefone cadastrado e nada é enviado.
4. **Given** qualquer pedido de recuperação, **When** o envio é registrado, **Then** o código não aparece em log nem em registro de envio.

---

### User Story 2 - Cliente recebe boas-vindas (Priority: P2)

Ao se cadastrar, o cliente recebe a mensagem de boas-vindas pelo canal configurado; no padrão, pelo WhatsApp e, se informou e-mail, também pelo e-mail que já existe hoje.

**Why this priority**: confirma o cadastro e valida o número, mas não impede o uso do sistema.

**Independent Test**: cadastrar um cliente com telefone real e e-mail e conferir as duas mensagens.

**Acceptance Scenarios**:

1. **Given** o robô conectado, **When** o cliente conclui o cadastro, **Then** recebe "Olá, {nome}! Sua conta foi criada com sucesso." no WhatsApp.
2. **Given** o robô indisponível ou o servidor de e-mail fora do ar, **When** o cliente se cadastra, **Then** o cadastro é concluído normalmente e a falha fica registrada.

---

### User Story 3 - Admin recebe os avisos da operação (Priority: P2)

O Admin recebe os avisos da operação pelo canal configurado, no telefone e no e-mail informados no painel, sem depender do CallMeBot.

**Why this priority**: o aviso de combinação de risco protege o caixa da banca e hoje depende de um serviço externo com chave própria; o saque parado afeta diretamente o cliente.

**Independent Test**: gerar uma combinação de risco nova e um saque parado e conferir as mensagens no telefone do Admin; desconectar o robô, repetir e conferir que chegam no e-mail.

**Acceptance Scenarios**:

1. **Given** o aviso de combinação ligado e o robô conectado, **When** surge uma combinação nova, **Then** o Admin recebe a mensagem com o mesmo conteúdo de hoje, pelo canal configurado.
2. **Given** um saque aguardando decisão, **When** ele completa 30 minutos parado, **Then** o Admin recebe, uma única vez por saque, o cliente, o valor, a chave Pix e o link para o painel, que exige login.
3. **Given** um saque decidido antes dos 30 minutos, **When** o prazo passaria, **Then** nenhum aviso é enviado.
4. **Given** o canal escolhido indisponível e o campo do outro canal preenchido, **When** surge um aviso, **Then** ele sai pelo outro canal.
5. **Given** o telefone e o e-mail do Admin vazios, **When** surge um aviso, **Then** nada é enviado e o registro fica como dispensado.

---

### User Story 4 - Admin sabe quando o robô cai (Priority: P2)

Quando o robô desconecta, o Admin é avisado por um canal que não dependa dele, para reconectar antes que as mensagens se percam.

**Why this priority**: com o robô fora, todas as mensagens por WhatsApp param sem que ninguém perceba.

**Independent Test**: desconectar o aparelho do robô e conferir o e-mail do Admin e o alerta no painel; reconectar e conferir que o alerta some.

**Acceptance Scenarios**:

1. **Given** o robô conectado, **When** ele cai sem o Admin ter pedido, **Then** o Admin recebe um e-mail e vê o alerta no painel enquanto a queda durar.
2. **Given** o Admin desconectou o robô pelo painel, **When** a situação muda, **Then** nenhum e-mail é enviado.
3. **Given** a conexão oscilando, **When** o robô cai várias vezes em pouco tempo, **Then** é enviado no máximo um e-mail a cada 30 minutos.

---

### User Story 5 - Admin testa os canais (Priority: P3)

Na aba Integrações, o Admin envia uma mensagem de teste por WhatsApp e por e-mail para confirmar que estão funcionando.

**Why this priority**: o projeto não tem testes automatizados, e isso permite conferir o canal sem depender de um cliente real. É um acréscimo em relação ao sistema antigo.

**Independent Test**: enviar um teste por cada canal e conferir o resultado na tela e a mensagem recebida.

**Acceptance Scenarios**:

1. **Given** o canal funcionando, **When** o Admin envia o teste, **Then** recebe a mensagem e vê a confirmação na tela.
2. **Given** o canal com falha, **When** o Admin envia o teste, **Then** vê uma mensagem clara, sem credenciais nem detalhes técnicos.

---

### User Story 6 - Admin escolhe o canal de cada mensagem (Priority: P2)

Em Configurações, o Admin vê a lista das mensagens do sistema e escolhe, para cada uma, se ela sai por WhatsApp, por e-mail ou pelos dois.

**Why this priority**: cada banca decide o canal sem depender de alteração no sistema.

**Independent Test**: mudar as boas-vindas para "E-mail", cadastrar um cliente e conferir que só chega o e-mail; mudar para "Os dois" e conferir as duas mensagens.

**Acceptance Scenarios**:

1. **Given** a lista de mensagens, **When** o Admin muda o canal e salva, **Then** os próximos envios seguem a escolha, sem afetar o que já está na fila.
2. **Given** a mensagem em "Os dois", **When** um dos canais falha, **Then** o outro é enviado normalmente e cada resultado fica registrado.
3. **Given** um usuário que não é Admin, **When** acessa as configurações, **Then** não consegue alterar os canais.

---

### User Story 7 - Destinatário entende o e-mail de imediato (Priority: P2)

Quem recebe um e-mail entende em poucos segundos do que se trata e o que fazer, inclusive no celular.

**Why this priority**: com o e-mail como opção em todas as mensagens, cada uma precisa de um modelo próprio.

**Independent Test**: disparar cada mensagem por e-mail e abrir no celular e no computador.

**Acceptance Scenarios**:

1. **Given** um e-mail de código, **When** é aberto, **Then** o código aparece em destaque, com a validade, sem precisar rolar a tela.
2. **Given** um aviso ao Admin, **When** é aberto, **Then** traz os dados essenciais e um único botão que leva à tela certa do painel, que exige login.

---

### User Story 8 - Cliente confirma o e-mail no cadastro (Priority: P2)

Quando o Admin liga a confirmação de cadastro, o cliente recebe no e-mail um botão para confirmar o endereço e só entra na conta depois disso, como no sistema antigo.

**Why this priority**: garante que o e-mail informado existe e pertence ao cliente, mas é opcional para a banca.

**Independent Test**: ligar a opção, cadastrar um cliente, tentar entrar antes de confirmar, clicar no botão e conferir que a conta abre.

**Acceptance Scenarios**:

1. **Given** a opção ligada, **When** o cliente se cadastra, **Then** o e-mail é obrigatório, ele recebe o botão de confirmação e a tela avisa para verificar o e-mail, sem entrar na conta.
2. **Given** um cadastro não confirmado, **When** ele tenta entrar, **Then** vê o aviso para confirmar e a opção de reenviar o e-mail.
3. **Given** um link válido, **When** o cliente clica no botão, **Then** o e-mail é confirmado e ele entra na conta.
4. **Given** um link já usado, vencido ou inválido, **When** o cliente o abre, **Then** vê "Link expirado" e pode pedir um novo.
5. **Given** a opção desligada, **When** o cliente se cadastra, **Then** o cadastro funciona como hoje, sem botão de confirmação.
6. **Given** um cliente que digitou o e-mail errado, **When** procura a banca, **Then** quem tem acesso ao cadastro pode corrigir o e-mail e confirmá-lo manualmente pelo painel.

---

### Edge Cases

- Telefone que não tem WhatsApp: o envio é marcado como falhou, sem novas tentativas.
- Robô já registrado como desconectado no momento do envio: mensagens com reserva (FR-008 e FR-011a) vão direto pelo outro canal; as demais seguem as tentativas de FR-005 e, ao final, ficam como falhou. Falha inesperada de conexão segue as tentativas antes de usar a reserva.
- Código de recuperação que passou da validade de 15 minutos sem ser enviado: não é mais enviado.
- Novo pedido de recuperação com um código anterior ainda na fila: o código invalidado não é enviado.
- Muitas mensagens ao mesmo tempo: os códigos de recuperação saem antes das demais.
- Boas-vindas ou mensagem de afiliado sem o dado do canal escolhido (e-mail ou telefone): fica como dispensada.
- Telefone ou e-mail do Admin vazio com um aviso configurado para esse canal: a tela de Configurações alerta sobre o campo vazio ao salvar, e o aviso usa o outro canal, se houver (FR-011a).
- Boas-vindas configuradas só por WhatsApp com a confirmação ligada: o e-mail com o botão é enviado mesmo assim, e o WhatsApp avisa para verificar o e-mail.
- Confirmação desligada com cadastros ainda pendentes: esses clientes passam a entrar normalmente.
- Credenciais de e-mail erradas ou ausentes: a falha é registrada e nenhuma operação principal é desfeita.
- Evolution API não configurada no servidor: o envio é marcado como falhou, sem erro para o usuário final.

## Requirements *(mandatory)*

### Functional Requirements

**Envio**

- **FR-001**: O sistema DEVE enviar mensagens de texto por WhatsApp pelo robô da banca conectado no painel (spec 021).
- **FR-002**: O sistema DEVE enviar e-mails com as credenciais já existentes na configuração do servidor, sem campos novos no painel.
- **FR-003**: Todo envio DEVE acontecer em segundo plano; uma falha NÃO DEVE desfazer nem atrasar o cadastro, a recuperação de senha ou qualquer outra operação de origem.
- **FR-004**: O telefone DEVE ser enviado com DDI e DDD, usando os dados do próprio cadastro.
- **FR-005**: Um envio com falha temporária DEVE ser tentado até 3 vezes, após 10, 30 e 60 segundos, como no sistema antigo. Falha definitiva, como número sem WhatsApp, NÃO DEVE ser repetida.
- **FR-006**: Códigos de recuperação DEVEM ter prioridade sobre as demais mensagens.

**Mensagens**

- **FR-007**: No WhatsApp, o código de recuperação do cliente DEVE usar o texto "Seu código de recuperação é {codigo}, válido por 15 minutos."
- **FR-008**: Códigos de recuperação DEVEM seguir o canal configurado, com reserva: se o canal escolhido falhar de vez ou não puder ser usado (robô desconectado ou destinatário sem e-mail), o código DEVE ir pelo outro canal, se houver. Com "Os dois", o código sai pelos dois canais. A resposta da tela DEVE continuar a mesma em todos os casos.
- **FR-009**: As boas-vindas do cliente DEVEM seguir o canal configurado, com o texto de hoje no WhatsApp e o modelo de cadastro já existente no e-mail.
- **FR-010**: O aviso de combinação de risco DEVE seguir o canal configurado, com o conteúdo, o agrupamento e o limite de 3 tentativas atuais (spec 014).
- **FR-011**: O saque aguardando decisão há 30 minutos DEVE gerar um único aviso ao Admin, a qualquer hora, com cliente, valor, chave Pix e link para o painel. O link NÃO DEVE permitir dar baixa sem login. Saques pagos automaticamente ou já decididos NÃO DEVEM gerar aviso.
- **FR-011a**: Os avisos de combinação de risco e de saque parado DEVEM usar o outro canal quando o escolhido falhar de vez ou não puder ser usado, se o campo do outro canal estiver preenchido. O aviso só fica como falhou se os dois canais falharem. Boas-vindas e mensagens de afiliados não têm essa reserva.
- **FR-012**: As mensagens dos afiliados (spec 012: cadastro recebido, aprovado, recusado e código de recuperação) DEVEM continuar saindo por e-mail por padrão e passam a aceitar a escolha de canal de FR-022.
- **FR-013**: Ao cliente, só as boas-vindas e o código de recuperação DEVEM ser enviados nesta spec; avisos de prêmio, saque e depósito NÃO entram.

**Robô e CallMeBot**

- **FR-014**: A queda do robô sem pedido do Admin DEVE gerar um e-mail para o endereço de FR-014a, com no máximo um envio a cada 30 minutos, e um alerta visível no painel enquanto durar.
- **FR-014a**: A tela de Configurações DEVE ter um campo novo, opcional, "E-mail do Admin", porque hoje o sistema não guarda e-mail de usuários do painel. Vazio, a queda só aparece como alerta no painel.
- **FR-015**: O CallMeBot DEVE ser removido do sistema: o campo da chave sai da tela e a chave guardada é apagada. O telefone do Admin continua configurável.
- **FR-016**: O aviso do deploy no GitHub (spec 013) DEVE continuar pelo CallMeBot, sem alteração.

**Canal de cada mensagem**

- **FR-022**: A tela de Configurações DEVE ter uma seção "Mensagens", só para o Admin, com a lista das mensagens e a escolha de WhatsApp, E-mail ou Os dois para cada uma, junto do telefone e do e-mail do Admin. Toda alteração entra no histórico de configurações.
- **FR-023**: Os canais padrão DEVEM ser:

  | Mensagem | Destinatário | Padrão |
  |---|---|---|
  | Código de recuperação | Cliente | WhatsApp |
  | Boas-vindas | Cliente | Os dois |
  | Combinação de risco | Admin | WhatsApp |
  | Saque parado há 30 minutos | Admin | WhatsApp |
  | Cadastro recebido, aprovado e recusado | Afiliado | E-mail |
  | Código de recuperação | Afiliado | E-mail |

- **FR-024**: O alerta de queda do robô DEVE ser sempre por e-mail e NÃO DEVE aparecer na lista de escolha, porque o WhatsApp é justamente o canal que caiu.

**Modelos de e-mail**

- **FR-025**: Cada mensagem da lista DEVE ter um modelo de e-mail próprio. São novos: código de recuperação do cliente, combinação de risco, saque parado, queda do robô e teste. Os modelos já existentes de afiliados e boas-vindas são mantidos.
- **FR-026**: Todos os modelos DEVEM usar a base visual já existente (logo, cor da banca e fonte), ter um assunto que diga do que se trata, texto curto, no máximo um botão e leitura confortável no celular.
- **FR-027**: Códigos DEVEM aparecer em destaque com a validade e o aviso para ignorar se não foi a pessoa que pediu. Avisos ao Admin DEVEM trazer os dados da mensagem de WhatsApp correspondente e o botão para a tela do painel.

**Confirmação de cadastro**

- **FR-028**: A seção "Mensagens" DEVE ter a opção "Exigir confirmação de cadastro", só para o Admin, desligada por padrão.
- **FR-029**: Com a opção ligada, o e-mail DEVE ser obrigatório no cadastro público, a conta NÃO DEVE receber acesso até a confirmação, e o cadastro NÃO DEVE devolver token de acesso.
- **FR-030**: As boas-vindas DEVEM levar o botão "Confirmar e-mail" no e-mail, sempre enviado nesse caso, qualquer que seja o canal escolhido. No WhatsApp, quando configurado, o texto avisa para verificar o e-mail.
- **FR-031**: O link DEVE ser de uso único e valer por 24 horas; um novo link invalida o anterior. Só uma ação na página de confirmação DEVE confirmar o e-mail, para que leitores automáticos de link não a acionem.
- **FR-032**: O cliente DEVE poder pedir o reenvio, com limite de tentativas e a mesma resposta exista ou não o cadastro.
- **FR-033**: Quem tem permissão de editar clientes DEVE poder corrigir o e-mail e confirmar o cadastro manualmente no painel.
- **FR-034**: Clientes já cadastrados NÃO DEVEM ser afetados ao ligar a opção. O cadastro de afiliados não muda; clientes que se cadastram com código de afiliado seguem a regra normalmente.

**Segurança e registro**

- **FR-017**: Códigos de recuperação e demais segredos NÃO DEVEM aparecer em logs nem em registros de envio.
- **FR-018**: O registro atual que grava as mensagens no log DEVE ser removido.
- **FR-019**: Cada envio DEVE ser registrado com canal, tipo, destinatário, situação, tentativas, data e erro resumido, sem o conteúdo das mensagens de recuperação.
- **FR-020**: O envio de teste DEVE ser exclusivo do Admin, com limite de 5 envios por minuto, sempre para o telefone e o e-mail do Admin configurados, sem destinatário livre.
- **FR-021**: NÃO DEVE existir rota que permita enviar mensagem a um destinatário livre.

### Key Entities

- **Envio de mensagem**: registro de cada mensagem, com canal, tipo, destinatário, situação (Pendente, Enviado, Falhou ou Dispensado), tentativas, data de envio e erro resumido. Pode apontar para o cliente, usuário ou saque de origem.
- **Canal da mensagem**: para cada tipo de mensagem, o canal escolhido pelo Admin (WhatsApp, E-mail ou Os dois).
- **Situação do robô**: já existe na spec 021; passa a guardar se a desconexão foi pedida pelo Admin e quando foi o último alerta de queda.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Com o robô conectado, 95% dos códigos de recuperação chegam em até 1 minuto.
- **SC-002**: Nenhum código de recuperação aparece em logs ou registros de envio, conferido em uma busca após o teste manual.
- **SC-003**: Uma falha de WhatsApp ou e-mail nunca impede a conclusão de um cadastro ou de um pedido de recuperação.
- **SC-004**: O Admin é avisado em até 5 minutos de qualquer queda do robô que não tenha pedido.
- **SC-005**: Nenhuma mensagem do sistema em operação usa mais o CallMeBot.
- **SC-007**: Uma troca de canal feita em Configurações vale para os envios seguintes em até 1 minuto, sem ajuda técnica.
- **SC-008**: Quem recebe um e-mail identifica o assunto e a ação esperada em até 10 segundos, conferido no teste manual em celular e computador.
- **SC-009**: Com a confirmação ligada, nenhum cadastro novo entra na conta antes de confirmar o e-mail, e o cliente consegue confirmar em até 1 minuto depois de abrir o e-mail.
- **SC-006**: Todo envio pode ser consultado no registro com a situação final, mesmo quando falha.

## Assumptions

- Usa as credenciais de e-mail do ambiente, trazidas do sistema antigo (`mail.wssports.bet`); em produção, `MAIL_MAILER` deve estar como `smtp`.
- A Evolution API e o robô da spec 021 estão disponíveis, com as variáveis do servidor preenchidas.
- O destinatário dos avisos é o telefone do Admin já configurado (`telefone_whatsapp_admin`), um só número. O e-mail é o do campo novo de FR-014a.
- Só texto: imagem e vídeo, que existiam no antigo mas não tinham uso nos fluxos consultados, ficam de fora.
- O registro de envios não tem tela nesta spec.
- Disparo em massa ou campanhas para clientes ficam de fora; o aviso manual continua sendo a notificação push da spec 019.
- O afiliado continua pedindo a recuperação de senha na mesma tela (spec 012); o canal do código segue FR-008 e FR-023.
