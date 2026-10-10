# Feature Specification: Ajustes da tela principal

**Feature Branch**: `006-ajustes-tela-principal`

**Created**: 2026-10-10

**Status**: Draft

**Input**: User description: "Ajustes da tela principal: tabela de jogos para impressão do vendedor;
popup de avisos com Fechar e Lido (design com UX, tabelas de popups e leituras, um popup padrão
criado na instalação, "Lido" por aparelho e por cliente logado); Especiais no backend e no frontend
(cotação fixa como no antigo, cadastro e encerramento pela API do painel); menu do vendedor com
Impressão PADRÃO/APP, Largura 58/80 mm e impressão Bluetooth no modo site; link do bilhete
(/?code=) montando a validação quando logado como vendedor; promoções padrão de cada categoria
criadas inativas; página de regras com as regras de bônus de cada promoção ativa e os limites de
aposta de quem está vendo."

> Esta spec amplia a tela principal da spec 005 (área `/`). A referência de comportamento é o
> sistema antigo (Princípio VII): `screens/main` (menu, tabela, impressão, link `?code=`),
> `screens/main/components/specials`, `screens/rules` e as tabelas `modalidades`,
> `modalidades_especiais`, `popups`, `slides`, `notificacaos` e `creditos_bonuses`, além do envio
> da logo em `ConfigController`. Ela substitui, na spec 005, o FR-011 (Especiais sem ação), o
> FR-014 (Impressão e Largura fora do menu), a parte do FR-017 e do FR-062 que mostrava o texto
> fake das regras, o FR-062 na parte dos banners fake e o FR-060 na parte da logo fake.

## Clarifications

### Session 2026-10-10 (decisões do responsável antes da spec)

- Q: O que significa "criar todos os tipos de promoções"? → A: As quatro categorias já existem
  (spec 002: Primeiro cadastro, Primeiro depósito, Qualquer depósito e Indicação). O sistema cria
  por padrão uma promoção de cada categoria, e a página de regras gera o texto de cada promoção
  ativa a partir dos campos dela, como no sistema antigo.
- Q: As promoções padrão nascem ativas ou inativas? → A: Inativas. Promoção ativa e vigente é
  aplicada sozinha na ação do cliente (ex.: cadastro), então só o administrador ativa, depois de
  revisar os valores.
- Q: O que a página de regras monta a partir da configuração? → A: As regras de bônus (uma por
  promoção ativa) e os limites de aposta de quem está vendo (visitante, cliente ou vendedor). O
  texto livre do administrador e as regras de cada mercado entram também (ver a sessão de ajustes
  abaixo).
- Q: Como o "Lido" do aviso é identificado? → A: Pelo aparelho (identificador guardado no
  navegador) e, com sessão de cliente, também pelo cliente. Não usa IP, para não esconder o aviso
  de outras pessoas na mesma rede.
- Q: Que cotação os Especiais usam? → A: A cotação fixa cadastrada pelo administrador, como no
  sistema antigo, sem as porcentagens de vendedor/cliente e sem o teto de cotação; valem o prêmio
  máximo e os limites do cupom.
- Q: O visual do aviso segue o antigo? → A: Não. O aviso é redesenhado com práticas de UX
  (diferença visual aprovada).
- Q: Encerrar um especial apura e paga as apostas, se o sistema novo ainda não tem apuração? →
  A: Seguir o sistema antigo no que não depende dos resultados dos jogos. No antigo
  (`ModalidadesController@update` e `OperacaoController@situacao`), informar a vencedora marca o
  palpite especial como Vencedor ou Perdedor só comparando com a opção; o bilhete fica Perdedor se
  algum palpite perdeu, Aguardando se algum ainda não tem resultado e Vencedor se todos ganharam.
  Esta spec grava o resultado dos palpites especiais e o da aposta quando ele já pode ser decidido
  sem os jogos; a apuração dos jogos e o pagamento do prêmio ficam para a spec de apuração
  (decisão do responsável em 2026-10-10).
- Q: A impressão Bluetooth e o modo APP entram? → A: Sim, no modo site, só com sessão de vendedor
  (substitui a decisão da spec 005 de deixá-los para outra spec).

### Session 2026-10-10 (ajustes do responsável)

- Q: "Popup" é o nome certo? → A: Não. O recurso passa a se chamar **aviso** (tabelas, rotas,
  componentes e textos), porque os nomes devem ser intuitivos e não precisam ser idênticos aos do
  sistema antigo (que usava `popups`).
- Q: Onde ficam os banners e a logo? → A: No sistema antigo, os banners ficavam na tabela `slides`
  (imagem 1280×405 e link) e a logo era um arquivo fixo (`upload/images/logo/logo.png`). No sistema
  novo, os banners ganham uma tabela própria e a logo fica na configuração, as duas gerenciadas
  pela API do painel e com um registro padrão criado na instalação. Assim, o carrossel e a logo
  deixam de ser fake.
- Q: Como evitar que a logo fique presa no cache do navegador? → A: O arquivo da logo é salvo com
  um hash do conteúdo no nome; ao trocar a logo, o endereço muda e o navegador baixa a nova (a
  mesma regra vale para as imagens dos banners e dos avisos).
- Q: De quem é o texto das regras? → A: Só do administrador: um texto único da banca, guardado na
  configuração, sem texto por gerente como no sistema antigo (`usuarios.regras`).
- Q: Como a página de regras se organiza? → A: Em blocos, como no sistema antigo: no topo, as
  regras editadas pelo administrador; abaixo, as regras de bônus das promoções; abaixo, as regras
  de apostas (cotações de cada mercado). Todos os blocos usam o visual de "Regras de apostas" do
  sistema antigo (título do bloco e cabeçalho de cada item com ícone e linha na cor do tema).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Vendedor valida a aposta pelo link do bilhete (Priority: P1)

O visitante envia ao vendedor o link do código (`/?code=CÓDIGO`). Ao abrir o link logado como
vendedor, a aposta já aparece no cupom pronta para validar.

**Why this priority**: é o caminho mais comum do código até o vendedor e hoje o link abre a tela
sem fazer nada.

**Independent Test**: gerar um código como visitante, abrir o link com sessão de vendedor e
validar; abrir o mesmo link sem sessão e conferir o bilhete.

**Acceptance Scenarios**:

1. **Given** sessão de vendedor e um link de código Pendente, **When** o link abre, **Then** a
   simulação vai para o cupom, o cupom abre (no mobile) e o "Finalizar" vira "Validar", como na
   pesquisa de código da spec 005 (FR-059c).
2. **Given** sem sessão de vendedor (visitante, cliente ou gestor) ou código já validado, **When**
   o link abre, **Then** o modal do bilhete mostra o comprovante.
3. **Given** um código inexistente no link, **When** o link abre, **Then** mostra a mensagem de não
   encontrado e a tela segue normal.
4. **Given** o link aberto, **When** a tela termina de carregar, **Then** o `?code=` sai da barra de
   endereço, para recarregar a página não repetir a ação.

---

### User Story 2 - Vendedor imprime bilhetes e a tabela de jogos (Priority: P1)

Logado como vendedor, o apostador tem no menu as opções de Impressão (PADRÃO ou APP), Largura
(58 ou 80 mm) e Tabela (jogos de hoje, de amanhã e por campeonatos), e imprime bilhetes e tabelas
na impressora Bluetooth pelo celular ou pelo navegador no computador.

**Why this priority**: rotina diária do vendedor de rua, que imprime bilhetes e tabelas.

**Independent Test**: entrar como vendedor no celular Android, parear a impressora térmica,
imprimir um bilhete e a tabela de hoje em 58 mm e em 80 mm; no computador, imprimir pelo navegador.

**Acceptance Scenarios**:

1. **Given** sessão de vendedor no celular, **When** abre o menu, **Then** vê Impressão (PADRÃO ou
   APP), Largura (58 ou 80 mm) e Tabela, além dos itens do visitante; no computador vê só Tabela.
2. **Given** o menu, **When** o vendedor toca em Impressão ou em Largura, **Then** a opção alterna
   e fica salva no aparelho.
3. **Given** Impressão PADRÃO no celular, **When** o vendedor toca em "Imprimir" num bilhete,
   **Then** a primeira vez pede para escolher a impressora Bluetooth, imprime no formato da largura
   escolhida e, nas próximas, imprime direto na mesma impressora.
4. **Given** Impressão APP no celular, **When** o vendedor toca em "Imprimir", **Then** o bilhete é
   enviado ao aplicativo de impressão, como no sistema antigo.
5. **Given** o computador, **When** o vendedor imprime, **Then** usa a impressão do navegador.
6. **Given** o menu, **When** o vendedor toca em Tabela e escolhe "Jogos de Hoje", "Jogos de
   Amanhã" ou "Jogos por Campeonatos" (escolhendo os campeonatos), **Then** a tabela com as
   cotações dele no esporte atual é impressa (Bluetooth no celular, navegador no computador).
7. **Given** um navegador sem Bluetooth (ex.: iPhone), **When** o vendedor imprime em PADRÃO,
   **Then** vê uma mensagem explicando que o aparelho não aceita impressão Bluetooth e sugerindo o
   modo APP.

---

### User Story 3 - Apostador aposta nos Especiais (Priority: P2)

O administrador cadastra categorias especiais (ex.: "Campeão Brasileiro 2026") com as opções e a
cotação de cada uma. O apostador abre "Especiais" na barra de esportes, escolhe uma opção e aposta
nela sozinha ou junto com jogos no mesmo cupom.

**Why this priority**: mercado que existia no sistema antigo e hoje aparece na barra sem ação.

**Independent Test**: cadastrar uma categoria com três opções, abrir "Especiais", escolher uma
opção, juntar com um jogo de futebol, finalizar e, depois, encerrar a categoria informando a
vencedora e conferir o resultado do palpite.

**Acceptance Scenarios**:

1. **Given** categorias especiais em aberto, **When** o apostador clica em "Especiais", **Then** a
   lista mostra cada categoria (nome e data/hora) com as opções e as cotações.
2. **Given** a lista de especiais, **When** o apostador clica numa opção, **Then** o palpite entra
   no cupom como "Vencedor: categoria", com a opção e a cotação; clicar na mesma opção remove e em
   outra opção da mesma categoria troca.
3. **Given** um cupom com especial e jogos, **When** o visitante, o cliente ou o vendedor
   finaliza, **Then** a aposta é aceita com as mesmas regras de valor, prêmio e quantidade de
   palpites dos jogos.
4. **Given** uma categoria encerrada com a opção vencedora, **When** o administrador confirma,
   **Then** os palpites na opção vencedora ficam Vencedor e os demais Perdedor; a aposta com um
   palpite perdido fica Perdedor, a aposta só com especiais vencedores fica Vencedor e a aposta com
   jogos ainda sem resultado continua Aguardando.
5. **Given** uma categoria cancelada, **When** o administrador confirma, **Then** os palpites dela
   ficam cancelados (cotação 1,00) e a cotação total e o prêmio das apostas são recalculados.
6. **Given** a categoria com data/hora já passada, **When** o apostador abre "Especiais", **Then**
   ela não aparece e um palpite dela no cupom é recusado pelo backend no "Finalizar".

---

### User Story 4 - Apostador lê as regras da banca (Priority: P2)

A página de regras mostra, em blocos e nesta ordem, as regras editadas pelo administrador, as
regras de bônus de cada promoção ativa, as regras de apostas de cada mercado e os limites de
aposta de quem está vendo. O sistema já vem com um texto de regras padrão e com uma promoção de
cada categoria, inativa, para o administrador revisar e ativar.

**Why this priority**: transparência para o apostador sobre a banca, bônus, mercados e limites;
não bloqueia a aposta.

**Independent Test**: editar o texto das regras, ativar as promoções padrão, abrir Regras como
visitante, como cliente e como vendedor, mudar um campo de uma promoção e um limite e conferir que
o texto muda.

**Acceptance Scenarios**:

1. **Given** a instalação do sistema, **When** o apostador abre Regras, **Then** vê o bloco de
   regras da banca com o texto padrão (prazo de pagamento, jogos não pagos e jogos definidos nos 90
   minutos).
2. **Given** o administrador altera o texto das regras pela API do painel, **When** o apostador
   recarrega Regras, **Then** vê o texto novo no primeiro bloco, um parágrafo por linha.
3. **Given** a instalação do sistema, **When** o administrador lista as promoções, **Then** há uma
   promoção de cada categoria, inativa, com as regras preenchidas.
4. **Given** promoções ativas, **When** o apostador abre Regras, **Then** vê, abaixo das regras da
   banca, o bloco "Regras de bônus" com um item por promoção (cabeçalho com a categoria e o nome)
   e o texto montado a partir dos campos dela: valor ganho (fixo ou percentual do depósito),
   modalidade, rollover, valor máximo convertido, período de validade, depósito máximo (quando
   houver), valores mínimo e máximo de aposta e cotações mínimas da aposta simples e da múltipla.
5. **Given** nenhuma promoção ativa, **When** abre Regras, **Then** o bloco de bônus não aparece.
6. **Given** a página de regras, **When** o apostador desce abaixo do bônus, **Then** vê o bloco
   "Regras de apostas" com um item por mercado (Principal, Ambas as equipes marcam, Dupla chance,
   etc.) e o que cada cotação precisa para ganhar, com os textos do sistema antigo.
7. **Given** visitante, cliente ou vendedor, **When** abre Regras, **Then** vê, no último bloco, os
   limites de aposta da configuração dele: valor mínimo e máximo, prêmio máximo, multiplicador,
   quantidade mínima e máxima de palpites e período de jogos.
8. **Given** um texto, uma promoção ou um limite alterado no painel, **When** o apostador recarrega
   Regras, **Then** vê o texto novo, sem publicar versão.

---

### User Story 5 - Apostador vê o aviso da banca (Priority: P3)

A banca cadastra um aviso com imagem (e link opcional). Ao abrir a tela, o apostador vê o aviso,
fecha por agora ou marca como lido para não ver mais.

**Why this priority**: comunicação da banca; a aposta funciona sem ele.

**Independent Test**: abrir a tela com o aviso padrão, fechar, recarregar (o aviso volta), marcar
como lido e recarregar (o aviso não volta).

**Acceptance Scenarios**:

1. **Given** um aviso ativo ainda não lido neste aparelho, **When** o apostador abre a tela,
   **Then** o aviso aparece sobre a tela com a imagem, "Fechar" e "Lido".
2. **Given** o aviso aberto, **When** o apostador toca em "Fechar" ou fora dele, **Then** o aviso
   fecha e volta na próxima abertura da tela.
3. **Given** o aviso aberto, **When** o apostador toca em "Lido" e confirma, **Then** o aviso fecha
   e não aparece mais neste aparelho (nem para o cliente logado, em outros aparelhos).
4. **Given** um aviso com link, **When** o apostador toca na imagem, **Then** o link abre em outra
   aba.
5. **Given** a tela aberta pelo link de um bilhete (`/?code=`), **When** a tela carrega, **Then** o
   aviso não aparece, para não cobrir o bilhete ou a validação.

---

### User Story 6 - A banca mostra os próprios banners e a logo (Priority: P3)

O administrador envia os banners do carrossel (com link opcional) e a logo da banca. A tela
principal e a página de regras passam a mostrar essas imagens no lugar das fake.

**Why this priority**: identidade e divulgação da banca; a aposta funciona com as imagens padrão.

**Independent Test**: enviar dois banners e uma logo nova pela API do painel, abrir a tela e
conferir o carrossel e a logo; trocar a logo e conferir que a nova aparece sem limpar o cache.

**Acceptance Scenarios**:

1. **Given** a instalação do sistema, **When** o apostador abre a tela, **Then** vê o banner
   padrão no carrossel e a logo padrão no cabeçalho.
2. **Given** banners ativos, **When** o apostador abre a tela, **Then** o carrossel mostra só os
   ativos, na ordem definida pelo administrador; tocar num banner com link abre o link.
3. **Given** nenhum banner ativo, **When** o apostador abre a tela, **Then** o carrossel não
   aparece e o restante da coluna sobe.
4. **Given** o administrador envia uma logo nova, **When** o apostador recarrega a tela, **Then**
   vê a logo nova no cabeçalho e na página de regras, mesmo com a anterior guardada no cache do
   navegador.

---

### Edge Cases

- Link `/?code=` aberto com o cupom já montado: o vendedor confirma antes de substituir os palpites
  (como na spec 005); o visitante só vê o bilhete e o cupom não muda.
- Impressora Bluetooth desligada, fora de alcance ou desconectada: mensagem de erro, a impressora
  salva é esquecida e a próxima impressão pede para escolher de novo; o bilhete continua válido.
- O vendedor cancela a escolha da impressora: nada é impresso e nenhuma mensagem de erro aparece.
- Tabela sem jogos no dia ou nos campeonatos escolhidos: mensagem "Nenhum jogo encontrado." e nada
  é impresso.
- Especial com opção removida ou cotação alterada pelo administrador depois de entrar no cupom: o
  backend recusa ou pede confirmação da cotação nova, como nos jogos.
- Categoria especial sem nenhuma opção ativa: não aparece na lista.
- Categoria especial encerrada com uma opção vencedora que não pertence a ela: recusado.
- Categoria especial já encerrada ou cancelada: não pode ser encerrada de novo nem receber palpites.
- Aviso sem imagem carregada (arquivo removido): o aviso não aparece.
- Vários avisos ativos e não lidos: aparece um por abertura da tela, sorteado, como no sistema
  antigo.
- "Lido" sem conexão: mensagem de erro e o aviso continua aberto.
- Promoção sem data de fim ou com campos opcionais vazios: o texto omite o trecho correspondente.
- Texto das regras vazio: o bloco de regras da banca não aparece.
- Texto das regras com marcação HTML ou script: aparece como texto, sem ser interpretado.
- Banner ou logo com arquivo que não é imagem ou acima do tamanho permitido: recusado com
  mensagem clara.
- Banner com imagem que não carrega (arquivo removido): o banner fica de fora do carrossel.
- Logo enviada igual à atual: o endereço não muda (mesmo hash).
- Rodar a criação dos dados padrão de novo (aviso, banner, logo, texto das regras e promoções):
  não duplica os registros nem sobrescreve o que o administrador já alterou.

## Requirements *(mandatory)*

### Functional Requirements

**Link do bilhete**

- **FR-001**: Abrir a tela com `?code=CÓDIGO` DEVE buscar o código como a pesquisa de código da
  spec 005 (FR-044): com sessão de vendedor e código Pendente, carregar a validação no cupom
  (spec 005, FR-059c) e abrir o cupom no mobile; nos demais casos, abrir o modal do bilhete.
- **FR-002**: O `?code=` DEVE sair da barra de endereço depois de usado, sem recarregar a página.
- **FR-003**: Código inexistente no link DEVE mostrar a mensagem de não encontrado e deixar a tela
  normal.
- **FR-004**: Com o cupom já montado, o vendedor DEVE confirmar antes de substituir os palpites
  pela validação.

**Impressão do vendedor e tabela de jogos**

- **FR-005**: Com sessão de vendedor, o menu DEVE mostrar, como no sistema antigo: no mobile,
  Impressão (PADRÃO/APP), Largura (58/80 mm) e Tabela; no desktop, só Tabela. Impressão e Largura
  alternam ao tocar e ficam salvas no aparelho (padrão: PADRÃO e 80 mm). Visitante, cliente e
  gestor NÃO DEVEM ver esses itens.
- **FR-006**: No mobile com Impressão PADRÃO, "Imprimir" (bilhete cadastrado, bilhete validado e
  tabela) DEVE imprimir na impressora térmica Bluetooth, no formato de texto do sistema antigo para
  a largura escolhida (58 ou 80 mm). A primeira impressão pede para escolher a impressora; as
  seguintes usam a mesma enquanto a conexão estiver ativa.
- **FR-007**: No mobile com Impressão APP, "Imprimir" DEVE enviar o bilhete ao aplicativo de
  impressão pelo mesmo endereço do sistema antigo (`app://{site}/{codigo}/{largura}/false`).
- **FR-008**: No desktop, "Imprimir" DEVE usar a impressão do navegador (já existente) e a tabela
  DEVE ser impressa pelo navegador no layout do sistema antigo.
- **FR-009**: Navegador sem suporte a Bluetooth, impressora fora de alcance ou falha na conexão
  DEVEM mostrar mensagem clara (sugerindo o modo APP quando não houver suporte); cancelar a escolha
  da impressora NÃO DEVE mostrar erro.
- **FR-010**: "Tabela" DEVE abrir as opções "Jogos de Hoje", "Jogos de Amanhã" e "Jogos por
  Campeonatos" (este com a escolha dos campeonatos do menu). O backend DEVE entregar, só para o
  vendedor logado, a tabela do esporte atual (o ao vivo usa o futebol) com o nome do sistema, a
  data/hora de atualização e, por campeonato, os jogos com horário, times e as cotações do vendedor
  nas colunas do sistema antigo: Casa, Empate, Fora, Ambas, +2.5, DPC, CGF, GMC, GMF, 2GMC, N.A,
  -2.5, DPF e FGC.
- **FR-011**: A tabela DEVE seguir as mesmas regras de visibilidade da lista do vendedor
  (campeonatos e confrontos não permitidos, período de jogos e jogos não iniciados).

**Especiais**

- **FR-012**: O administrador DEVE poder cadastrar, editar, ativar/desativar e remover categorias
  especiais pela API do painel (nome e data/hora limite para apostar) e as opções de cada categoria
  (nome e cotação), com permissões por função no padrão dos recursos existentes. O nome da opção é
  único dentro da categoria.
- **FR-013**: A lista pública de especiais DEVE mostrar só categorias ativas, em aberto, com
  data/hora limite futura e com ao menos uma opção ativa, ordenadas por nome e data/hora, com a
  busca por nome da categoria. "Especiais" segue a mesma regra de visibilidade dos outros esportes
  (spec 005, FR-010).
- **FR-014**: A tela DEVE mostrar cada categoria com o cabeçalho de campeonato (nome e data/hora) e
  as opções com nome e cotação, no visual do componente de especiais do sistema antigo.
- **FR-015**: O cupom DEVE aceitar um palpite por categoria especial, com a regra de
  adicionar/remover/trocar dos jogos (spec 005, FR-034), mostrando "Vencedor" e o nome da categoria
  no lugar dos times; palpites especiais e de jogos podem estar no mesmo cupom.
- **FR-016**: A cotação do especial DEVE ser a cotação fixa cadastrada, sem porcentagens de
  vendedor, cliente, campeonato ou confronto e sem teto de cotação; o prêmio máximo, o
  multiplicador e os limites de valor e de quantidade de palpites valem como para os jogos.
- **FR-017**: Visitante (código), cliente (saldo) e vendedor (painel e validação de código) DEVEM
  poder apostar em especiais; o backend DEVE recusar categoria fechada, data/hora passada, opção
  inativa e cotação alterada, com a mesma mensagem e confirmação de cotação nova dos jogos.
- **FR-018**: O administrador DEVE poder encerrar a categoria informando a opção vencedora ou
  cancelá-la. Ao encerrar, cada palpite especial ativo DEVE receber o resultado, como no sistema
  antigo: Vencedor na opção vencedora e Perdedor nas demais. Ao cancelar, os palpites da categoria
  DEVEM ficar Cancelados (fora da cotação total, como cotação 1,00), com a cotação total e o prêmio
  das apostas recalculados.
- **FR-018a**: Depois de encerrar ou cancelar, o resultado de cada aposta afetada DEVE ser: Perdedor
  quando algum palpite perdeu; Vencedor quando todos os palpites ativos são especiais vencedores ou
  quando não restou palpite ativo (prêmio igual ao valor apostado); Aguardando nos demais casos (há
  jogos ainda sem resultado). O pagamento do prêmio e a apuração dos jogos ficam para a spec de
  apuração.
- **FR-019**: O bilhete, o comprovante impresso e a consulta pelo código DEVEM mostrar o palpite
  especial como "Vencedor: categoria", com a opção e a cotação.

**Página de regras e promoções padrão**

- **FR-020**: O sistema DEVE criar na instalação uma promoção de cada categoria (Primeiro cadastro,
  Primeiro depósito, Qualquer depósito e Indicação), com nome, descrição e regras preenchidas com
  os valores padrão do sistema antigo, editáveis pelo painel como qualquer promoção da spec 002.
- **FR-021**: As promoções padrão DEVEM ser criadas inativas: não dão bônus nem aparecem nas regras
  até o administrador revisar os valores e ativar.
- **FR-021a**: A página de regras DEVE mostrar, nesta ordem: a logo, o bloco de regras da banca
  (FR-021b), o bloco "Regras de bônus" (FR-022), o bloco "Regras de apostas" (FR-023a) e o bloco
  "Limites de aposta" (FR-024).
- **FR-021b**: O administrador DEVE poder editar, pela API do painel, um texto único de regras da
  banca, guardado na configuração. A instalação DEVE preencher o texto padrão ("Prazo de pagamento
  até 2 dias úteis.", "Não pagará jogos já realizados ou que já estejam rolando e, por falha,
  continuem no sistema, por erro de hora, cotação ou por jogo antecipado." e "Todos os jogos são
  definidos ao final dos 90 minutos de jogo, incluindo acréscimos definidos pelos árbitros. Não
  valerá prorrogação nem disputa de pênaltis."). A página DEVE mostrar cada linha do texto como um
  parágrafo, sem interpretar HTML; com o texto vazio, o bloco não aparece.
- **FR-022**: A página de regras DEVE mostrar o bloco "Regras de bônus" com um item por promoção
  ativa e dentro do período (cabeçalho com a categoria e o nome, e o texto), sem o bloco quando
  não houver nenhuma.
- **FR-023**: O texto de cada promoção DEVE ser montado a partir dos campos dela, como no sistema
  antigo: valor ganho (valor fixo em reais ou percentual do depósito), modalidade (Esportes ou
  Cassino), rollover, valor máximo convertido, validade, depósito máximo (Primeiro depósito e
  Qualquer depósito), valores mínimo e máximo de aposta e cotações mínimas da aposta simples e da
  múltipla (Esportes); trechos de campos vazios não aparecem.
- **FR-023a**: A página de regras DEVE mostrar o bloco "Regras de apostas" com um item por mercado
  e, em cada um, a cotação e o que ela precisa para ganhar, com os mercados e os textos do sistema
  antigo (`screens/rules`): Principal, Ambas as equipes marcam, Dupla chance, Handicap asiático,
  Intervalo/Final de jogo, Resultado exato, Total de gols, Gols mais/menos, Gols par/ímpar,
  Vencedor e ambas equipes, Resultados e total de gols, Empate anula aposta, Escanteios
  acima/abaixo, Escanteios exatos, Total de escanteios, Time ímpar/par, Ambas marcam 1º/2º tempo,
  Tempo com mais gols, Time e tempo com mais gols, Time sem sofrer gol, Time sofre gol, Margem de
  vitória e Time - total de gols. Esses textos são fixos (não editáveis nesta spec).
- **FR-024**: A página de regras DEVE mostrar o bloco "Limites de aposta" com a configuração de
  quem está vendo (visitante, cliente ou vendedor): valor mínimo e máximo da aposta, prêmio máximo,
  multiplicador máximo do prêmio, quantidade mínima e máxima de palpites e período de jogos.
- **FR-025**: Valores em dinheiro DEVEM aparecer em reais (R$ 0,00) e as cotações com duas casas.
  A página mantém a barra aprovada na spec 005 ("Voltar" e atalho do WhatsApp), e todos os blocos
  DEVEM usar o visual de "Regras de apostas" do sistema antigo: bloco branco com o título
  centralizado em caixa alta e cada item com cabeçalho (ícone, título em caixa alta e linha
  inferior na cor do tema) seguido do texto. O texto fake das regras da spec 005 deixa de existir.

**Avisos**

- **FR-026**: O administrador DEVE poder cadastrar, editar, ativar/desativar e remover avisos pela
  API do painel: imagem (enviada por upload), link opcional, título opcional e período de exibição
  opcional (início e fim).
- **FR-027**: O sistema DEVE criar um aviso padrão ativo na instalação (imagem padrão e sem link),
  que o administrador pode editar ou desativar.
- **FR-028**: Ao abrir a tela, DEVE aparecer um aviso ativo, dentro do período e ainda não lido
  pelo aparelho (nem pelo cliente logado); havendo vários, um sorteado por abertura. O aviso NÃO
  DEVE aparecer quando a tela abre pelo link de um bilhete (FR-001).
- **FR-029**: O aviso DEVE ter "Fechar" (fecha só agora; volta na próxima abertura da tela), fechar
  ao tocar fora ou na tecla Esc e "Lido" com a confirmação "Realizando essa ação esse aviso não irá
  aparecer mais para você, confirma?". Tocar na imagem com link abre o link em outra aba.
- **FR-030**: "Lido" DEVE registrar a leitura no servidor pelo identificador do aparelho (gerado e
  guardado no navegador) e, com sessão de cliente, também pelo cliente, para não aparecer em outros
  aparelhos dele. "Limpar cache" gera um aparelho novo e os avisos lidos só pelo aparelho voltam.
- **FR-031**: O aviso DEVE ser redesenhado com práticas de UX (diferença visual aprovada): cartão
  centralizado sobre fundo escurecido no desktop e folha que sobe de baixo no mobile, imagem
  inteira sem corte, botão de fechar visível no canto, "Lido" como ação principal na cor do tema e
  "Fechar" como ação secundária, entrada suave, foco preso no aviso enquanto aberto e funcionamento
  nos modos claro e escuro.
- **FR-032**: O aviso NÃO DEVE bloquear o carregamento da lista: a lista carrega por trás e o aviso
  só aparece depois que a imagem carregar.

**Banners e logo**

- **FR-032a**: O administrador DEVE poder cadastrar, editar, ativar/desativar, ordenar e remover
  banners pela API do painel: imagem (enviada por upload e ajustada para 1280×405, como no sistema
  antigo), link opcional e ordem.
- **FR-032b**: O sistema DEVE criar um banner padrão ativo na instalação (imagem padrão e sem link).
- **FR-032c**: O carrossel da tela principal (spec 005, FR-019) DEVE mostrar os banners ativos na
  ordem definida, com o mesmo comportamento da spec 005; sem banner ativo, o carrossel não aparece.
- **FR-032d**: O administrador DEVE poder enviar a logo da banca pela API do painel; ela fica
  guardada na configuração. A instalação DEVE preencher a logo padrão do projeto.
- **FR-032e**: O cabeçalho da tela principal e a página de regras DEVEM usar a logo da
  configuração. Favicon e ícones do aplicativo continuam fake até a spec de configurações visuais.
- **FR-032f**: Os arquivos da logo, dos banners e dos avisos DEVEM ser salvos com um hash do
  conteúdo no nome, para que trocar a imagem mude o endereço e o navegador não mostre a versão
  antiga guardada no cache. Ao trocar ou remover a imagem, o arquivo anterior DEVE ser apagado.

**Geral**

- **FR-033**: Os recursos novos de gerenciamento (especiais, avisos, banners, logo e texto das
  regras) DEVEM ser documentados na coleção do Postman, como os recursos existentes.
- **FR-034**: A criação dos dados padrão (aviso, banner, logo, texto das regras e promoções) DEVE
  poder ser repetida sem duplicar registros nem sobrescrever o que o administrador já alterou.

### Key Entities

- **Categoria especial**: aposta de vencedor (ex.: campeão de um campeonato), com nome,
  data/hora limite, situação (em aberto, encerrada, cancelada), opção vencedora e se está ativa.
- **Opção especial**: escolha de uma categoria especial, com nome, cotação fixa e se está ativa.
- **Aviso**: comunicado da banca com imagem, link e título opcionais, período de exibição e se
  está ativo.
- **Leitura de aviso**: registro de "Lido" por aparelho e, quando houver, por cliente.
- **Aparelho**: identificador gerado e guardado no navegador, usado nas leituras de aviso.
- **Banner**: imagem do carrossel com link opcional, ordem e se está ativo.
- **Configuração (logo e regras)**: a logo da banca e o texto único das regras, na configuração
  já existente.
- **Preferências de impressão**: modo (PADRÃO ou APP) e largura (58 ou 80 mm), guardados no
  aparelho do vendedor.
- **Tabela de jogos**: jogos de um dia ou de campeonatos escolhidos, com as cotações do vendedor
  nas colunas da impressão.
- **Promoção**: entidade da spec 002 (categoria, modalidade, tipo e valor do ganho, rollover e
  regras de uso), usada para montar as regras de bônus.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Abrindo o link de um código Pendente logado como vendedor, a aposta fica pronta para
  validar sem nenhuma digitação.
- **SC-002**: Num celular Android com impressora térmica pareada, o vendedor imprime um bilhete em
  até 10 segundos depois de tocar em "Imprimir" (depois da primeira escolha da impressora), nas
  larguras de 58 e 80 mm, sem texto cortado.
- **SC-003**: A tabela impressa mostra as mesmas cotações que o vendedor vê na lista, em 100% dos
  jogos.
- **SC-004**: Um apostador monta e finaliza um cupom com um especial e dois jogos em menos de 1
  minuto, e o resultado do especial é aplicado a 100% dos palpites e das apostas que já podem ser
  decididas quando a categoria é encerrada.
- **SC-005**: Toda mudança no texto das regras, nos campos de uma promoção ou nos limites de
  aposta aparece na página de regras na próxima carga, sem publicar versão.
- **SC-006**: Nenhum cliente recebe bônus de uma promoção padrão antes de o administrador ativá-la.
- **SC-007**: Um aviso marcado como "Lido" não volta a aparecer no mesmo aparelho em 100% das
  aberturas seguintes; um aviso apenas fechado volta na próxima abertura.
- **SC-008**: Depois de trocar a logo ou um banner, 100% das aberturas seguintes da tela mostram a
  imagem nova, sem o apostador limpar o cache do navegador.

## Assumptions

- O painel web ainda não existe: especiais, avisos, banners, logo e texto das regras são
  gerenciados pela API do painel, com as permissões por função no padrão do projeto.
- As imagens dos avisos, dos banners e da logo ficam no armazenamento público do sistema; as
  imagens padrão vêm com o projeto.
- A impressão Bluetooth usa o recurso de Bluetooth do navegador (Chrome no Android e no
  computador); o Safari do iPhone não tem suporte e usa o modo APP.
- O aplicativo de impressão do modo APP é o mesmo usado com o sistema antigo e não faz parte desta
  spec.
- O cancelamento dos palpites de uma categoria cancelada reaproveita a regra de cancelar palpite
  da spec 004 (palpite fora da cotação total, prêmio recalculado e registro no histórico).
- A apuração dos jogos e o pagamento do prêmio não existem no sistema novo e ficam para a spec de
  apuração; esta spec não credita prêmio a ninguém.
- Os valores das promoções padrão seguem os padrões do sistema antigo (`creditos_bonuses`) e podem
  ser ajustados pelo administrador antes de ativar.
- Os textos das regras de cada mercado são fixos; torná-los editáveis fica para outra spec.
- Ações do Acumuladão e do Cassino, tema, favicon, ícones do aplicativo e contatos reais continuam
  fora (fake conforme a spec 005).
