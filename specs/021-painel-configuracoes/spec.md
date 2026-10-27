# Feature Specification: Painel — Configurações gerais

**Feature Branch**: `021-painel-configuracoes`

**Created**: 2026-10-25

**Status**: Draft

**Input**: User description: "Painel de configurações gerais (tela 'Configurações' do painel, Admin e
Supervisor), consultando o sistema antigo (screens/adm/config, ConfigController, WhatsAppController)
e reaproveitando os endpoints já existentes. Abas: (1) Banca: nome do sistema, telefone/WhatsApp de
atendimento com botão flutuante, mensagem do bilhete, redes sociais e link de jogo responsável, cor
do tema (as 6 cores do sistema antigo), substituindo DadosFake::contatos e DadosFake::tema; GTM-ID
do Google Tag Manager, injetado no head/body do site quando preenchido, com os disparos de evento no
dataLayer nos mesmos pontos do sistema antigo: 'subscribe' ao concluir o cadastro do cliente,
'addtocart' com o valor ao gerar o Pix de depósito e 'pucharse' com o valor ao confirmar o depósito
pago. (2) Visual: upload da logo gerando automaticamente os ícones do PWA nos tamanhos do manifest
(32 a 512) e os ícones Apple, substituindo DadosFake::icones; gestão dos banners do slide da página
inicial (enviar, ordenar, ativar/desativar, link, visualizar e remover) usando o CRUD de banners; e
o popup 500x500 com link, como no sistema antigo. (3) Regras: editor de texto rico amigável
(TipTap) para as regras da banca, gravadas em HTML sanitizado no backend e exibidas na página
/regras, convertendo o texto atual em parágrafos. (4) Integrações: botão de vincular/sincronizar
com o Mercado Pago (endpoints de gateway da spec 007, com situação, expiração e desvincular) e
botão do robô do WhatsApp pela Evolution API (conectar por QR code, ver status e desconectar; o
envio de mensagens fica para uma spec futura, que também substituirá o CallMeBot da spec 014). (5)
Operação: telas para as configurações que já têm show/update no backend (pagamentos, criar aposta,
apuração, encerramento, cassino, afiliados, aviso de combinação pelo WhatsApp). Seguir o padrão das
telas do painel 014–020 (PanelShell, PanelTabs, SaveBar, hooks usePainel)."

## Clarifications

### Session 2026-10-25 (decisões tomadas antes do `/speckit-specify`)

- Q: Qual provedor faz a conexão do robô do WhatsApp? → A: A Evolution API (no lugar da Z-API do
  sistema antigo). Nesta spec entram só conectar por QR code, ver a situação e desconectar; o envio
  de mensagens fica para uma spec futura.
- Q: O aviso de combinação de risco pelo CallMeBot (spec 014) continua? → A: Continua como está
  nesta spec; a spec futura de mensagens pelo robô vai substituí-lo.
- Q: O popup de 500x500 do sistema antigo entra? → A: Sim. No v2 ele já existe como "aviso da banca"
  (spec 006); esta spec traz a gestão dele para a tela.
- Q: O GTM volta? → A: Sim, com os disparos de evento nos mesmos pontos do sistema antigo: cadastro
  concluído, Pix de depósito gerado e depósito confirmado.
- Q: Como o administrador escreve as regras? → A: Num editor de texto com formatação (títulos,
  negrito, itálico, listas, alinhamento, link e cor), parecido com um editor de documentos, sem
  precisar de chave de serviço externo (o antigo usava o TinyMCE com chave). O texto atual vira
  parágrafos formatados.
- Q: O evento de depósito confirmado mantém o nome `pucharse` do antigo (com erro de digitação)?
  → A: Não. Usa o nome correto `purchase`; as bancas que já tinham gatilho no GTM com `pucharse`
  precisam ajustá-lo (FR-010).

### Session 2026-10-25 (`/speckit-clarify`)

- Q: O Supervisor também pode vincular/desvincular o Mercado Pago e conectar/desconectar o robô do
  WhatsApp? → A: Não. Só o Admin age nas duas integrações; o Supervisor vê a situação, sem os
  botões (FR-022, FR-024 e FR-025).
- Q: A situação do robô é atualizada pelo aviso automático da Evolution API ou só ao abrir a aba?
  → A: Pelo aviso automático (webhook de mudança de conexão), que grava a situação na hora; a aba
  também consulta ao abrir, como reforço (FR-026a).
- Q: O GTM é carregado só nas páginas do apostador ou também no painel e na área do afiliado? → A:
  Só nas páginas do apostador (`/`, `/app`, `/cassino`, área do cliente, regras e bilhete); o painel
  e a área do afiliado não carregam o GTM (FR-009).
- Q: As alterações feitas na tela de Configurações ficam registradas? → A: Sim. Cada salvamento é
  registrado com quem alterou, quando, a aba/seção e os valores de antes e depois, sem tela de
  consulta nesta spec (FR-003a).
- Q: O Supervisor pode alterar todas as seções da aba Operação, inclusive as que mexem com dinheiro?
  → A: Sim. Admin e Supervisor alteram todas as abas e seções, como o servidor já permite; só as
  ações das integrações ficam com o Admin (FR-001 e FR-028). Exceção registrada no plano (research
  R-03): o limite de notificações manuais continua só do Admin, como o servidor já faz (spec 019).

### Session 2026-10-25 (`/speckit-analyze`, correções aceitas pelo responsável: "sigo o recomendado")

- O banner novo entra no fim da ordem (o servidor grava a maior ordem + 10, até 999) — US3, cenário 1.
- O GTM é carregado pelo próprio site, sem o trecho `noscript` (FR-009).
- O limite das regras é de 10.000 caracteres de texto visível e 50.000 com a formatação (FR-018).
- Os FRs das abas dizem "Admin e Supervisor", como no FR-001.

### Session 2026-10-25 (ajustes visuais pedidos pelo responsável depois da implementação)

- A tela ocupa a largura das outras telas do painel (1250 px) e cada aba ganha uma frase explicando
  o que se configura nela.
- Banca em duas colunas com a altura de cada cartão (Identificação e Redes sociais | Atendimento e
  Rastreamento), campos numa coluna nos cartões (redes em duas) e Tema embaixo na largura toda (cor e
  fundo lado a lado). Visual e Integrações em cartões de duas colunas no computador (uma no celular):
  Logo | Avisos, Banners na largura toda; Mercado Pago | Robô do WhatsApp.
- Banners e avisos em galeria: cada cartão com a imagem grande na proporção do site (banner com a
  posição no carrossel), situação, mover para antes/depois (banners) e menu de ações; o último cartão,
  tracejado, cadastra um novo. Ícones gerados da logo em caixas do mesmo tamanho.
- Cartões com linha separando o cabeçalho do conteúdo e espaçamento uniforme.
- Regras: no computador, editor e prévia ("Como fica no site") lado a lado, a prévia acompanhando o
  que é escrito; no celular continua o botão "Ver como fica".
- A aba Operação fica como estava (aprovada pelo responsável), só mais larga; os campos de ligar e
  desligar dela passam a ser seleção Sim/Não.
- Q: O visual pode mudar em relação ao antigo (Princípio VIII)? → A: Sim, no padrão do painel novo
  (specs 014 a 020), como nas specs anteriores. As diferenças estão em "Referências do sistema
  antigo".

### Session 2026-10-27 (alterado pela spec 022)

- A seção Afiliados da aba Operação (regra de ganho padrão do cadastro público) e as rotas
  `GET/PUT /configuracoes/afiliados` foram removidas: o ganho é definido por quem gerencia, no
  cadastro e na aprovação do afiliado (FR-028).

## Referências do sistema antigo *(Princípios VII e VIII)*

- Backend (`wssports.bet`, `main` `408cf730`): `app/Http/Controllers/ConfigController.php` (`index`
  e `store`: dados da banca, cor, logo com geração dos ícones e das imagens de abertura, slide,
  popup e trocas de senha), `app/Http/Controllers/WhatsAppController.php` (`status`, `qrCode`,
  `disconnect` e `send` pela Z-API), `app/Http/Controllers/CadastroController.php` e
  `PagamentosController.php` (envios ao Sellflux), `resources/views/app.blade.php` (código do GTM).
- Telas: `resources/js/screens/adm/config` ("Configuração", rota `/adm/config`), o modal
  `screens/adm/modals/auth-gateway` (PrimePag), e os disparos de `dataLayer` em
  `resources/js/modals/register` (`subscribe`) e `resources/js/modals/credits` (`addtocart` e
  `pucharse`).

**Como funcionava**:

- **Quem via**: Admin e Supervisor (o controller barrava cliente, vendedor e gerente); alguns campos
  só para o programador ou travados para o Supervisor.
- **Uma tela só**, com um botão "Atualizar" que enviava tudo de uma vez: nome do sistema, telefone,
  botão flutuante de atendimento (Sim/Não), senha do admin, versão do aplicativo, gateway de
  pagamentos (Mercado Pago por OAuth ou PrimePag por credenciais), GTM-ID, webhook do Sellflux,
  percentual do cash out, Facebook, Twitter, Instagram, YouTube, cor do sistema (6 cores), logo,
  slide e popup; no modo "somente cassino", limite e valores de saque, aviso do bilhete e o editor
  das regras; nos demais, intervalos de sorteio de "ambas marcam" e travas da banca (ao vivo,
  bilhete ao vivo, créditos, outros esportes, cassino, função PIN, gerente admin, remover jogos).
- **Logo**: redimensionada para 512x512 (distorcendo quando não era quadrada) e copiada para os
  ícones do aplicativo (32, 64, 96, 128, 168, 192, 256, 512), os ícones da Apple (152, 167, 180,
  192) e as imagens de abertura da Apple (640 a 2048), sempre com o mesmo nome de arquivo.
- **Slide e popup**: cada envio criava um registro; a lista mostrava o link com botões de excluir,
  ver e salvar o link. Sem ordem nem ativar/desativar.
- **Robô do WhatsApp (Z-API)**: situação da instância, QR code para conectar, desconectar e envio
  em fila; a situação ficava gravada como "Conectado"/"Desconectado".
- **GTM**: com o GTM-ID preenchido, o código do Google Tag Manager entrava no início da página e o
  `noscript` no corpo; eventos `subscribe` no cadastro, `addtocart` (com `amount`) ao gerar o Pix e
  `pucharse` (com `amount`, nome com erro de digitação) quando a consulta do Pix voltava paga.
- **Problemas encontrados**: um único botão salvava campos sem relação entre si e reenviava
  arquivos; a logo distorcia; trocar a logo não renovava o cache do navegador (mesmo nome); slide
  sem ordem; erro ao excluir arquivo interrompia o salvamento; a chave do editor de regras ficava no
  código; o `pucharse` só disparava com o modal de depósito aberto.

**Diferenças aprovadas** (padrão das specs 014 a 020):

- Uma tela com abas (Banca, Visual, Regras, Integrações e Operação); cada aba salva só os seus
  campos.
- Ficam de fora: senha do admin (já está em "Minha conta"), versão do aplicativo (a atualização é
  automática pela constituição), PrimePag, Sellflux e as travas que viraram configuração por usuário
  ou por cliente nas specs 002, 003 e 015 (créditos, outros esportes, função PIN, gerente admin).
- A logo gera os ícones sem distorcer e com endereço novo a cada troca; as imagens de abertura da
  Apple não são geradas (o aplicativo usa o manifest).
- Banners com ordem e ativo/inativo; popup como "aviso da banca", com título e período de exibição.
- Robô do WhatsApp pela Evolution API.
- O evento de depósito confirmado no GTM passa de `pucharse` para `purchase` e dispara mesmo com o
  modal de depósito fechado.
- O GTM é carregado só nas páginas do apostador; o antigo carregava também no painel.
- As alterações passam a ficar registradas (quem, quando, antes e depois); o antigo não registrava.
- Visual do painel novo: tema, cabeçalho do painel, abas do painel, barra de salvar, modais com
  `ModalHeader`, listas que viram cartões no celular.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Dados da banca e contatos do site (Priority: P1)

O administrador abre "Configurações" no menu do painel e, na aba "Banca", ajusta o nome do sistema,
a mensagem do bilhete, o WhatsApp de atendimento (número, mensagem inicial e se o botão flutuante
aparece), as redes sociais (Instagram, Facebook, Twitter/X, YouTube), o link de jogo responsável, a
cor principal do tema e a cor de fundo padrão. Ao salvar, o site passa a mostrar os dados novos na
próxima carga de página, sem publicar versão.

**Why this priority**: são os dados que hoje aparecem no site como fake (contatos e tema); sem eles
a banca não consegue colocar o próprio WhatsApp, redes e cor no ar.

**Independent Test**: alterar o WhatsApp, uma rede social e a cor; abrir o site em outra aba e ver o
botão flutuante com o número novo, o link da rede e a cor nova; apagar uma rede e ver o ícone sumir.

**Acceptance Scenarios**:

1. **Given** o Admin na aba Banca, **When** troca a cor principal para Azul e salva, **Then** o
   site, o painel e os e-mails passam a usar o azul e a cor escura derivada dele na próxima carga.
2. **Given** o campo Instagram vazio, **When** salva, **Then** o ícone do Instagram não aparece no
   site.
3. **Given** "Botão flutuante" desligado, **When** o apostador abre o site, **Then** o botão de
   WhatsApp não aparece, mesmo com número preenchido.
4. **Given** um link de rede social sem `http(s)://` ou um número de WhatsApp incompleto, **When**
   tenta salvar, **Then** recebe a mensagem do campo com erro e nada é gravado.
5. **Given** um Gerente ou Vendedor, **When** acessa o painel, **Then** não vê "Configurações" no
   menu e a rota responde acesso negado.

---

### User Story 2 - Logo e ícones do aplicativo (Priority: P1)

Na aba "Visual", o administrador envia a logo da banca. O sistema mostra a prévia, grava a logo e
gera a partir dela todos os ícones do aplicativo instalável (tamanhos do manifest e ícones da Apple)
e o ícone da aba do navegador. O site, o aplicativo instalado e as notificações passam a usar a
logo e os ícones novos.

**Why this priority**: os ícones do aplicativo ainda são fake; quem instala o site no celular vê um
ícone que não é o da banca.

**Independent Test**: enviar uma logo, abrir o manifest e a página inicial e conferir que todos os
ícones apontam para imagens novas geradas da logo, nos tamanhos certos; reinstalar o aplicativo no
celular e ver o ícone da banca.

**Acceptance Scenarios**:

1. **Given** uma logo quadrada PNG de 512x512, **When** o Admin envia, **Then** a prévia mostra a
   logo nova e os ícones de 32, 64, 96, 128, 168, 192, 256 e 512 e os da Apple (152, 167, 180) são
   gerados e usados no manifest e nas páginas.
2. **Given** uma logo retangular, **When** envia, **Then** os ícones são gerados sem distorcer (a
   imagem é centralizada num quadrado com fundo transparente).
3. **Given** um arquivo que não é imagem, ou maior que o limite, **When** envia, **Then** recebe a
   mensagem de logo inválida e a logo atual continua.
4. **Given** a logo trocada, **When** o apostador abre o site, **Then** vê a logo nova sem limpar o
   cache (endereço novo a cada troca).

---

### User Story 3 - Banners do slide e aviso da banca (popup) (Priority: P1)

Ainda na aba "Visual", o administrador gerencia os banners do carrossel da página inicial: envia
imagens (ajustadas para 1280x405), põe link, muda a ordem, ativa ou desativa, vê em tamanho real,
troca a imagem e exclui. Na mesma aba gerencia os avisos da banca (antigo popup de 500x500): envia
a imagem, título e link opcionais, período de exibição e se está ativo.

**Why this priority**: o carrossel e o aviso são a vitrine de promoções da banca e hoje só podem ser
alterados pela API.

**Independent Test**: cadastrar dois banners, inverter a ordem, desativar um e conferir o carrossel
do site; cadastrar um aviso ativo e vê-lo ao abrir o site.

**Acceptance Scenarios**:

1. **Given** a lista de banners, **When** o Admin envia uma imagem com link, **Then** o banner entra
   no fim da ordem, ativo, e aparece no carrossel do site.
2. **Given** dois banners, **When** move o segundo para cima, **Then** a nova ordem vale no
   carrossel.
3. **Given** um banner ativo, **When** desativa, **Then** ele some do carrossel mas continua na
   lista do painel; sem nenhum ativo, o carrossel não aparece.
4. **Given** um banner, **When** exclui e confirma, **Then** ele sai da lista e do carrossel.
5. **Given** a lista de avisos, **When** cadastra um aviso com início e fim, **Then** ele aparece no
   site só dentro do período, até o apostador marcar como lido (regra da spec 006).

---

### User Story 4 - Regras da banca com editor de texto (Priority: P2)

Na aba "Regras", o administrador escreve as regras da banca num editor com formatação simples
(títulos, negrito, itálico, sublinhado, listas, alinhamento, link e cor do texto), vê o resultado
como vai aparecer e salva. A página pública de regras mostra o texto com a formatação.

**Why this priority**: o texto já pode ser alterado hoje, mas só como texto puro, um parágrafo por
linha; o editor dá liberdade para o cliente organizar as regras sem conhecer código.

**Independent Test**: escrever um título, uma lista e um trecho em negrito, salvar e abrir a página
de regras do site conferindo a mesma formatação.

**Acceptance Scenarios**:

1. **Given** as regras atuais em texto puro, **When** a atualização é publicada, **Then** cada linha
   vira um parágrafo e a página de regras continua mostrando o mesmo texto.
2. **Given** o editor, **When** o Admin formata e salva, **Then** a página de regras mostra a
   formatação.
3. **Given** um conteúdo colado de outro site com script, estilo ou elemento não permitido, **When**
   salva, **Then** só a formatação permitida é gravada e nada executa na página de regras.
4. **Given** o editor vazio, **When** salva, **Then** o bloco de regras da banca some da página (como
   hoje), sem voltar ao texto padrão.
5. **Given** alterações não salvas, **When** tenta sair da aba, **Then** é avisado de que vai perder
   as alterações.

---

### User Story 5 - Integrações: Mercado Pago e robô do WhatsApp (Priority: P2)

Na aba "Integrações", o Admin vê a situação do Mercado Pago (vinculado, expirando, com falha na
renovação ou desvinculado, com as datas) e pode vincular/sincronizar a conta (abre o Mercado Pago e
volta vinculado) ou desvincular. No mesmo lugar vê a situação do robô do WhatsApp e pode conectar
(aparece o QR code para ler no WhatsApp do celular da banca), acompanhar até conectar e desconectar.

**Why this priority**: o vínculo do Mercado Pago já funciona pela API, mas sem tela; o robô é a base
da spec futura de mensagens.

**Independent Test**: com a conta desvinculada, clicar em Vincular, autorizar no Mercado Pago e ver
"Vinculado" com a validade; clicar em Conectar o robô, ler o QR code e ver "Conectado" com o número.

**Acceptance Scenarios**:

1. **Given** o Mercado Pago desvinculado, **When** o Admin clica em "Vincular Mercado Pago", **Then**
   abre a autorização numa nova aba e, ao voltar, a aba mostra "Vinculado" com a data de vínculo e
   de expiração.
2. **Given** o vínculo com falha de renovação, **When** abre a aba, **Then** vê o aviso e o botão
   "Sincronizar novamente", que refaz o vínculo.
3. **Given** a conta vinculada, **When** clica em "Desvincular" e confirma, **Then** a situação vira
   "Desvinculado" e os depósitos por Mercado Pago deixam de ser oferecidos (regra da spec 007).
4. **Given** o robô desconectado, **When** clica em "Conectar", **Then** vê o QR code, que é
   renovado sozinho enquanto não for lido, e ao ler no celular a situação vira "Conectado" com o
   número conectado.
5. **Given** o robô conectado, **When** clica em "Desconectar" e confirma, **Then** a situação vira
   "Desconectado".
6. **Given** a Evolution API fora do ar ou não configurada no servidor, **When** abre a aba,
   **Then** vê "Serviço do robô indisponível" com a orientação, e o restante da tela funciona.
7. **Given** um Supervisor, **When** abre a aba, **Then** vê as situações, mas não os botões de
   vincular, desvincular, conectar e desconectar (só o Admin).

---

### User Story 6 - Rastreamento com Google Tag Manager (Priority: P2)

Na aba "Banca", o administrador informa o código do contêiner do Google Tag Manager. Com ele
preenchido, as páginas do apostador carregam o GTM (o painel e a área do afiliado não) e enviam os eventos de marketing nos mesmos
pontos do sistema antigo: cadastro concluído, Pix de depósito gerado (com o valor) e depósito
confirmado (com o valor).

**Why this priority**: as bancas medem as campanhas de anúncio por esses eventos; sem eles o v2
perde o rastreamento que o antigo tinha.

**Independent Test**: preencher um GTM-ID de teste, abrir o site com o modo de visualização do GTM,
cadastrar um cliente, gerar um Pix e pagar, conferindo os três eventos com os valores.

**Acceptance Scenarios**:

1. **Given** o GTM-ID vazio, **When** o site abre, **Then** nenhum código do GTM é carregado.
2. **Given** o GTM-ID `GTM-XXXXXXX` salvo, **When** o site abre, **Then** o GTM é carregado com esse
   contêiner; **When** o painel ou a área do afiliado abre, **Then** o GTM não é carregado.
3. **Given** o GTM ativo, **When** um cliente conclui o cadastro, **Then** o evento `subscribe` é
   enviado uma vez.
4. **Given** o GTM ativo, **When** o cliente gera um Pix de depósito de R$ 50,00, **Then** o evento
   `addtocart` é enviado com `amount` 50.
5. **Given** o GTM ativo e o Pix gerado, **When** o depósito é confirmado com a tela do cliente
   aberta, **Then** o evento `purchase` é enviado uma vez com `amount` 50, mesmo que o cliente tenha
   fechado o modal de depósito.
6. **Given** um GTM-ID fora do formato `GTM-` seguido de letras e números, **When** salva, **Then**
   recebe a mensagem de formato inválido.

---

### User Story 7 - Configurações de operação (Priority: P3)

Na aba "Operação", o administrador encontra, separadas em seções, as configurações que hoje só
podem ser alteradas pela API: pagamentos (depósitos, saques, Pix, cashback, rollover), Criar Aposta,
apuração, encerramento de aposta, cassino, afiliados, aviso de combinação pelo WhatsApp, ao vivo
(habilitado, segundos de trava, permanência, minuto limite e cotação máxima), sorteio de "ambas
marcam"/"ambas não marcam" e o limite diário de notificações manuais. Cada seção salva só os seus
campos, com as mesmas regras de validação que o servidor já aplica.

**Why this priority**: as regras já existem e funcionam com os valores padrão; a tela só tira a
dependência da API para mudá-las.

**Independent Test**: em cada seção, alterar um campo, salvar, recarregar a página e conferir o valor
gravado; provocar um erro de validação e ver a mensagem no campo.

**Acceptance Scenarios**:

1. **Given** a seção Pagamentos, **When** altera o depósito mínimo e salva, **Then** o valor fica
   gravado e o modal de depósito do cliente passa a usar o novo mínimo.
2. **Given** um valor inválido (ex.: mínimo maior que o máximo do sorteio de "ambas marcam"),
   **When** salva, **Then** vê a mensagem no campo e nada é gravado.
3. **Given** a seção Aviso de combinação pelo WhatsApp, **When** abre, **Then** a chave do CallMeBot
   não é mostrada (só se está preenchida) e pode ser trocada.
4. **Given** alterações numa seção, **When** salva outra seção, **Then** as alterações da primeira
   não são enviadas nem perdidas sem aviso.

---

### Edge Cases

- Dois administradores salvam a mesma aba ao mesmo tempo: vale o último que salvou; cada aba envia
  só os seus campos, então abas diferentes não se sobrescrevem.
- A geração dos ícones falha no meio (disco cheio, imagem corrompida): a logo e os ícones anteriores
  continuam valendo e o Admin vê o erro.
- Logo muito pequena (ex.: 64x64): é aceita, com aviso de que os ícones grandes ficarão borrados;
  tamanho recomendado 512x512.
- Banner excluído enquanto o carrossel está aberto num aparelho: some na próxima carga.
- Ordem de banners com o mesmo número: desempata pela data de cadastro, como hoje.
- QR code do robô expira sem ser lido: é renovado automaticamente enquanto a aba estiver aberta;
  ao sair da aba a renovação para.
- Robô desconectado pelo próprio celular (WhatsApp → aparelhos conectados → sair): a situação passa
  para "Desconectado" assim que chega o aviso da Evolution API.
- Aviso da Evolution API com identificação errada ou de outra instância: é recusado e a situação
  gravada não muda.
- Depósito confirmado com a página do cliente fechada: o evento de compra não é enviado (o
  rastreamento depende do navegador do cliente), como no sistema antigo.
- O mesmo depósito confirmado duas vezes (aviso repetido do Mercado Pago): o evento de compra é
  enviado uma vez só.
- Regras com mais conteúdo que o limite: o servidor recusa com a mensagem de tamanho máximo.
- Troca da cor do tema com o painel aberto em outra aba: a cor nova vale na próxima carga.
- Mercado Pago com credenciais da aplicação não configuradas no servidor: o botão de vincular mostra
  a mensagem atual ("Credenciais da aplicação não configuradas no servidor.").

## Requirements *(mandatory)*

### Functional Requirements

**Tela e acesso**

- **FR-001**: O painel DEVE ter a tela "Configurações" (rota `/painel/configuracoes`, ícone de
  engrenagem no menu), visível para quem tem a permissão de editar configurações (Admin e
  Supervisor); as demais funções não veem o item e recebem acesso negado na rota e nas chamadas.
  Admin e Supervisor alteram todas as abas e seções; só as ações das integrações (FR-022, FR-024 e
  FR-025) são exclusivas do Admin.
- **FR-002**: A tela DEVE ter as abas Banca, Visual, Regras, Integrações e Operação, no padrão das
  telas do painel (specs 014 a 020); a aba aberta DEVE ficar no endereço, para recarregar na mesma
  aba.
- **FR-003**: Cada aba (e cada seção da aba Operação) DEVE carregar os valores atuais do servidor e
  salvar só os seus campos, com a barra de salvar aparecendo quando houver alteração e aviso ao sair
  com alterações não salvas.
- **FR-003a**: Cada salvamento feito pela tela DEVE ser registrado com quem alterou, quando, a aba
  ou seção e só os campos que mudaram, com o valor de antes e o de depois (regras e logo incluídas;
  da logo, o endereço da imagem). Cadastro, edição e exclusão de banners e avisos, e vincular,
  desvincular, conectar e desconectar nas integrações também são registrados (quem, quando, a ação
  e o item). Chaves secretas (ex.: chave do CallMeBot) aparecem no registro só como "alterada",
  nunca o valor. Não há tela de consulta nesta spec; salvar sem nenhuma mudança não gera registro.
- **FR-004**: Erros de validação DEVEM aparecer junto ao campo, com as mensagens em português
  devolvidas pelo servidor; nada é gravado quando há erro.

**Aba Banca**

- **FR-005**: Admin e Supervisor DEVEM poder editar: nome do sistema (obrigatório, até 100 caracteres), mensagem
  do bilhete (até 500), WhatsApp de atendimento (com DDI e DDD, opcional), mensagem inicial do
  WhatsApp (opcional), botão flutuante de atendimento (ligado/desligado), Instagram, Facebook,
  Twitter/X, YouTube e link de jogo responsável (endereços `http`/`https`, opcionais).
- **FR-006**: Admin e Supervisor DEVEM poder escolher a cor principal do tema entre as 6 cores do sistema antigo
  (vermelho, amarelo, verde, azul, laranja, rosa), vendo uma amostra de cada, e a cor de fundo padrão
  (preto ou branco); a cor escura derivada é calculada pelo sistema.
- **FR-007**: Campos de contato vazios DEVEM esconder o item correspondente no site; o botão
  flutuante só aparece ligado e com número preenchido.
- **FR-008**: Os dados de contato e de tema DEVEM vir da configuração gravada, substituindo os dados
  fake atuais (contatos e tema) em todos os lugares que os usam: site, painel, manifest do
  aplicativo, e-mails e notificações; os fakes correspondentes DEVEM ser removidos.
- **FR-009**: Admin e Supervisor DEVEM poder informar o GTM-ID (formato `GTM-` seguido de letras maiúsculas e
  números, opcional); preenchido, as páginas do apostador (`/`, `/app`, `/cassino`, área do cliente,
  regras e bilhete) DEVEM carregar o Google Tag Manager com esse contêiner, pelo próprio site ao
  abrir a página (sem o trecho alternativo `noscript`, porque o site não funciona sem JavaScript); o
  painel e a área do afiliado NÃO DEVEM carregá-lo; vazio, nada é carregado.
- **FR-010**: Com o GTM ativo, o site DEVE enviar os eventos: `subscribe` ao concluir o cadastro do
  cliente; `addtocart` com `amount` (valor em reais) ao gerar a cobrança Pix de depósito; e o evento
  de compra com `amount` quando a tela do cliente recebe a confirmação do depósito pago, uma vez por
  depósito, mesmo com o modal de depósito fechado. O evento de compra DEVE se chamar `purchase` (o
  `pucharse` do sistema antigo, com erro de digitação, não é enviado).

**Aba Visual**

- **FR-011**: Admin e Supervisor DEVEM poder enviar a logo (png, jpg, jpeg ou webp, até 1 MB, como hoje) e ver a
  prévia da logo atual; a troca DEVE gerar, a partir da logo, os ícones do aplicativo de 32, 64, 96,
  128, 168, 192, 256 e 512 pixels, os ícones da Apple de 152, 167 e 180 pixels e o ícone da aba do
  navegador, sem distorcer (imagem centralizada em quadrado com fundo transparente).
- **FR-012**: O manifest do aplicativo, as páginas do site e as notificações push DEVEM usar os
  ícones gerados; sem logo enviada, os ícones padrão do projeto. Cada troca DEVE gerar endereços
  novos, para o navegador não mostrar os antigos, e os arquivos anteriores DEVEM sair do disco.
- **FR-013**: A tela DEVE recomendar a logo quadrada de 512x512 e avisar quando a imagem enviada for
  menor que 192x192.
- **FR-014**: Admin e Supervisor DEVEM ver os banners do carrossel com miniatura, link, ordem e situação, e poder
  cadastrar (imagem obrigatória e link opcional), trocar a imagem, alterar o link, ativar/desativar,
  mudar a ordem (subir e descer), ver em tamanho real e excluir com confirmação, usando o cadastro de
  banners já existente (imagem ajustada para 1280x405).
- **FR-015**: Admin e Supervisor DEVEM ver os avisos da banca (antigo popup) com miniatura, título, link, período
  e situação, e poder cadastrar, editar, ativar/desativar, ver e excluir com confirmação, usando o
  cadastro de avisos já existente; a tela DEVE recomendar a imagem de 500x500.
- **FR-016**: As listas de banners e avisos DEVEM ser paginadas (até 20 por página) e virar cartões
  no celular.

**Aba Regras**

- **FR-017**: Admin e Supervisor DEVEM editar as regras da banca num editor com formatação: títulos, parágrafo,
  negrito, itálico, sublinhado, lista com marcadores e numerada, alinhamento, link, cor do texto,
  desfazer e refazer.
- **FR-018**: O servidor DEVE gravar as regras como texto formatado, mantendo só os elementos e
  atributos permitidos (os do editor) e removendo scripts, estilos livres, eventos e qualquer outro
  conteúdo, com o limite atual de 10.000 caracteres de texto visível (e até 50.000 contando a
  formatação).
- **FR-019**: A página pública de regras DEVE mostrar as regras da banca com a formatação; regras
  vazias escondem o bloco, como hoje.
- **FR-020**: Na publicação, as regras já gravadas em texto puro DEVEM ser convertidas em parágrafos
  (uma linha por parágrafo), e o texto padrão das regras DEVE passar a ser o mesmo texto em
  parágrafos.

**Aba Integrações**

- **FR-021**: A aba DEVE mostrar a situação do Mercado Pago (vinculado, desvinculado, com falha de
  renovação), com data de vínculo, expiração e última renovação, para Admin e Supervisor.
- **FR-022**: Só o Admin DEVE poder vincular/sincronizar (abre a autorização do Mercado Pago em nova
  aba e, ao voltar, a situação é atualizada) e desvincular com confirmação, usando o vínculo da spec
  007.
- **FR-023**: A aba DEVE mostrar a situação do robô do WhatsApp (desconectado, aguardando leitura do
  QR code, conectado, serviço indisponível) e, conectado, o número e o nome do perfil.
- **FR-024**: Só o Admin DEVE poder conectar o robô: o sistema cria (ou reaproveita) a instância da
  banca na Evolution API e mostra o QR code, renovado automaticamente enquanto não for lido e a aba
  estiver aberta, até a situação virar conectado.
- **FR-025**: Só o Admin DEVE poder desconectar o robô, com confirmação; a situação vira
  desconectado. Conectar e desconectar o robô seguem a mesma regra de acesso do vínculo do Mercado
  Pago (só Admin); o servidor DEVE recusar esses pedidos vindos de outra função.
- **FR-026**: O endereço e a chave da Evolution API DEVEM ficar só no servidor e nunca ir para o
  navegador; a situação do robô DEVE ser gravada para uso pela spec futura de mensagens.
- **FR-026a**: O sistema DEVE receber o aviso automático da Evolution API a cada mudança de conexão
  da instância da banca e gravar na hora a situação, o número e o nome do perfil; o aviso DEVE ser
  aceito só com a identificação secreta combinada com a Evolution API e só para a instância da
  banca (os demais são recusados). Ao abrir a aba, a situação também é consultada, para corrigir um
  aviso que não tenha chegado.
- **FR-027**: Falha ou demora da Evolution API (até 10 segundos) DEVE mostrar "Serviço do robô
  indisponível" sem travar a tela e sem afetar o Mercado Pago.

**Aba Operação**

- **FR-028**: A aba DEVE ter uma seção para cada grupo de configurações já existente no servidor —
  Pagamentos, Criar Aposta, Apuração, Encerramento de aposta, Cassino e Aviso de
  combinação pelo WhatsApp — com os campos e as validações atuais de cada um; Admin e Supervisor
  alteram todas as seções.
  *(Alterado pela spec 022: a seção Afiliados saiu; a regra de ganho passa a ser definida no
  cadastro e na aprovação do afiliado.)*
- **FR-029**: A aba DEVE ter também a seção Ao vivo (habilitado, segundos de trava, minutos de
  permanência, minuto limite e cotação máxima), a seção Sorteio de cotação (mínimo e máximo de
  "ambas marcam" e de "ambas não marcam", com o mínimo menor ou igual ao máximo) e o limite diário de
  notificações manuais (1 a 50), que hoje não têm como ser alterados. O limite de notificações
  manuais mantém a regra da spec 019: só o Admin altera; o Supervisor vê o valor sem poder mudá-lo.
- **FR-030**: A trava geral do ao vivo (ligada pela conferência) DEVE aparecer só como informação na
  seção Ao vivo, sem poder ser alterada pela tela.
- **FR-031**: A chave do CallMeBot NÃO DEVE ser mostrada; a seção mostra só se está preenchida e
  permite informar uma nova.

**Geral**

- **FR-032**: Todas as rotas novas ou alteradas DEVEM entrar na coleção do Postman, regenerada na
  mesma entrega.

### Key Entities

- **Configurações** (registro único já existente): ganha os contatos (WhatsApp, mensagem inicial,
  botão flutuante, redes sociais, jogo responsável), a cor principal e a cor de fundo padrão, o
  GTM-ID, os ícones gerados da logo e a situação do robô do WhatsApp; as regras passam a ser texto
  formatado.
- **Banners** (já existente): imagem, link, ordem e ativo.
- **Avisos** (já existente, antigo popup): imagem, título, link, período e ativo.
- **Gateway de pagamento** (já existente, spec 007): situação do vínculo do Mercado Pago.
- **Robô do WhatsApp**: instância da banca na Evolution API, situação (desconectado, aguardando QR
  code, conectado), número e nome do perfil conectados e data da última mudança de situação.
- **Histórico de configurações**: cada alteração feita pela tela, com o usuário, a data, a aba ou
  seção, a ação e os campos alterados (antes e depois, sem valores secretos).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: O administrador muda o WhatsApp, as redes e a cor da banca e vê o resultado no site em
  menos de 2 minutos, sem pedir ajuda ao suporte.
- **SC-002**: 100% dos dados fake de contatos, tema e ícones deixam de ser usados no site, no painel,
  no manifest, nos e-mails e nas notificações.
- **SC-003**: Depois de trocar a logo, o aplicativo instalado de novo e a aba do navegador mostram o
  ícone da banca em todos os tamanhos do manifest, sem distorção.
- **SC-004**: O administrador formata as regras (título, lista e negrito) e publica em menos de 5
  minutos, e a página de regras mostra exatamente a formatação vista no editor.
- **SC-005**: Nenhum script ou estilo colado nas regras chega à página pública.
- **SC-006**: O robô do WhatsApp é conectado lendo o QR code em menos de 1 minuto, e a tela mostra
  "Conectado" em até 5 segundos depois da leitura.
- **SC-007**: Com o GTM preenchido, os três eventos (cadastro, Pix gerado e depósito confirmado)
  aparecem no modo de visualização do GTM com os valores corretos, sem duplicar.
- **SC-008**: Todas as configurações que hoje só são alteradas pela API podem ser alteradas pela
  tela.

## Assumptions

- O cadastro de banners e avisos, a logo, as regras, o vínculo do Mercado Pago e as configurações de
  pagamentos, Criar Aposta, apuração, encerramento, cassino, afiliados e aviso de combinação já
  existem no servidor (specs 006, 007, 008, 009, 010, 011, 012 e 014); esta spec os mostra e chama,
  acrescentando os campos de contato, tema e GTM, a geração dos ícones, o texto formatado das regras,
  o robô do WhatsApp e as seções de ao vivo, sorteio e limite de notificações.
- A cor de fundo configurada é o padrão do site; a escolha claro/escuro feita pelo próprio apostador
  no aparelho continua valendo sobre ela.
- O robô usa uma instância só, da banca; o endereço e a chave global da Evolution API ficam na
  configuração do servidor, e o nome da instância é derivado da banca.
- A situação do robô é gravada pelo aviso automático da Evolution API (FR-026a) e consultada ao
  abrir a aba; não há alerta ao Admin quando o robô desconecta nesta spec (fica para a spec de
  mensagens).
- Sellflux, PrimePag, senha do admin e versão do aplicativo ficam fora (ver "Diferenças aprovadas").
- `somente_cassino` e `permitir_entrada_campeonatos` continuam sem tela (só no banco), porque não têm
  uso no site novo definido por spec.
- O evento de compra é enviado pelo navegador do cliente; não há envio pelo servidor (Measurement
  Protocol) nesta spec.
- As imagens de abertura da Apple (splash) não são geradas.
- Dados fake removidos nesta spec: `DadosFake::tema` (spec 005), `DadosFake::contatos` (spec 005) e
  `DadosFake::icones` (spec 005), com os arquivos de `public/fakes/icones` e o ícone fake da página.
  Continua fake só `DadosFake::indicadores` (Acumuladão), de spec própria.
- Datas no fuso do sistema (Brasília).
- Sem testes automatizados (constituição); a validação é manual pelo quickstart da spec.
