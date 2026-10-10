# Feature Specification: Tela principal de apostas esportivas (área `/`)

**Feature Branch**: `005-tela-principal`

**Created**: 2026-10-09

**Status**: Draft

**Input**: User description: "Tela principal de apostas esportivas (área `/`, antigo modo SITE) —
responsiva e PWA. Reconstruir no frontend novo a tela principal de apostas esportivas do sistema
antigo (`wssports.bet/resources/js/screens/main`), para o visitante (sem login), visualmente
idêntica à atual no desktop e no mobile, com tema só de cores, áreas por rota (`/`, `/app`,
`/cassino`), dados fake para o que o backend ainda não fornece, PWA, e o `research.md` com o
inventário visual do sistema antigo. Fora do escopo: login e cadastro, ações do Acumuladão e do
Cassino, impressão, áreas `/app` e `/cassino` e as visões de usuários logados."

> Esta é a primeira spec de frontend do projeto. A referência visual e de comportamento é o sistema
> antigo (Princípios VII e VIII); os valores de medidas, cores e breakpoints de cada elemento estão
> no inventário visual do `research.md`. Os dados que o backend ainda não fornece são fake
> (Princípio IX) e estão listados em FR-060 a FR-063.

## Clarifications

### Session 2026-10-09 (análise do sistema antigo, antes do pedido)

- Q: Inertia, API ou os dois? → A: Os dois. O site web usa Inertia (as páginas recebem os dados
  prontos do servidor); a API JWT continua para app e integrações. A escolha foi deixada a critério
  da análise pelo responsável.
- Q: O que é "tema"? → A: Só as cores: cor principal (`temas`, 6 opções), cor escura derivada
  (`letter`) e cor de fundo (`cor_fundo`). Os antigos modos SITE, APP e CASINO deixam de ser tema
  e viram áreas com rota própria: `/` (site de apostas), `/app` (layout de aplicativo) e `/cassino`.
  A área é definida pela URL, não pelo aparelho. Constituição 2.0.0, Princípio VIII.
- Q: Quais áreas esta spec constrói? → A: Só a área `/` (antigo SITE), responsiva e PWA.
- Q: O visual pode mudar? → A: Não. A tela é visualmente idêntica à atual (Princípio VIII);
  sugestões de melhoria visual só entram com aprovação do responsável.
- Q: Dados que o backend ainda não tem? → A: Fake até uma nova spec trazer o dado real
  (Princípio IX).
- Q: Idioma da spec? → A: Português (Constituição 2.0.0, Princípio II).
- Q: O visitante vê as opções de impressora (Impressão e Largura) no menu? → A: Não.
- Q: O Acumuladão aparece? → A: Sim, visível e sem ação nesta spec.
- Q: O "Limpar cache" preserva o cupom? → A: Não. Apaga todos os dados locais, inclusive o cupom,
  e recarrega a página.
- Q: Os breakpoints mudam? → A: O 900px continua. As sugestões de melhoria (research.md, R-06)
  foram decididas na sessão seguinte.

### Session 2026-10-09 (decisões do responsável sobre as sugestões)

- Q: Unificar as regras de 900px (layout) e 1024px (comportamento)? → A: Sim. Uma regra só, de
  900px, para layout e comportamento.
- Q: Largura mínima das colunas "Menu" e "Cupom" no desktop? → A: Autorizado (240px; a coluna
  central absorve a diferença).
- Q: Altura da tela no celular? → A: Autorizado usar a altura visível real, para a barra do
  navegador não esconder o fim da tela.
- Q: Liberar o zoom? → A: Não. O zoom continua bloqueado, como hoje.
- Q: Botão do WhatsApp sobre as odds no mobile? → A: Autorizado deixar espaço livre no fim da
  lista para o botão não cobrir o último jogo.
- Q: Atualização do sistema e cache? → A: As atualizações devem valer na hora, sem depender do
  frontend: o servidor é a fonte da versão; na próxima interação ou ao voltar para a aba, o
  usuário já está na versão nova, sem limpar cache e sem reinstalar (Constituição 2.1.0, Stack).
- Q: Cor de fundo? → A: Vira um botão de alternar dia/noite (claro/escuro). O modo escuro é o
  visual atual; o modo claro é novo (diferença visual aprovada pelo responsável).
- Q: Nomes dos componentes? → A: Em inglês e PascalCase, escolhidos para serem intuitivos
  (Constituição 2.1.0, Princípios I e III).

### Session 2026-10-09 (/speckit-clarify)

- Q: Quando o visitante clica numa cotação do ao vivo, o que acontece, já que o backend não
  aceita aposta ao vivo de visitante? → A: Deixa adicionar ao cupom; o "Finalizar" mostra a
  recusa do backend ("Para apostar no ao vivo é preciso fazer login.") e o cupom continua montado.
- Q: O que a aba "Especiais" mostra, se o backend novo ainda não tem os especiais? → A: A aba
  aparece na barra de esportes e, ao clicar, não faz nada (igual ao Cassino), até a spec dos
  especiais.
- Q: Que valor o "vendedor paga" mostra para o visitante? → A: O "vendedor paga" só aparece
  quando a comissão sobre o prêmio (`comissao_por_premio`, percentual por vendedor) é diferente
  de zero; o valor é o retorno possível menos esse percentual. A opção já existe no backend novo
  (`usuarios_configuracoes.comissao_por_premio`, vinda de `travas_vendedores` do sistema antigo).
  O visitante ainda não tem vendedor (o `pin` do link está fora do escopo), então a comissão dele
  é zero. (Revisto na sessão seguinte: o campo aparece sempre.)
- Q: Ao restaurar o cupom salvo no aparelho, o que fazer com jogos já começados e cotações
  alteradas? → A: Restaura como estava, sem prazo de validade; o backend decide no "Finalizar"
  (jogo começado ou cotação alterada → mensagem do backend, cupom mantido).

### Session 2026-10-09 (decisões do plano)

- Q: Os arquivos existentes listados no plano podem ser alterados ou removidos? → A: Sim
  (`composer.json`, `package.json`, `vite.config.js`, `routes/web.php`, `bootstrap/app.php`;
  remoção de `resources/js/app.js`, `resources/css/app.css` e `welcome.blade.php`).
- Q: A paleta do modo claro (research.md, R-19) está aprovada? → A: Sim, como proposta.
- Q: Como fica o "vendedor paga" até existir a configuração própria? → A: O campo aparece sempre,
  no layout do sistema antigo (cotação total e "vendedor paga" lado a lado), com o mesmo valor do
  prêmio (retorno possível). A configuração `vendedor_paga` ainda não existe no backend; o
  responsável cria a coluna em outra spec, e essa spec define o desconto. Substitui a resposta
  anterior sobre o campo aparecer só com comissão diferente de zero.
- Q: Pode acrescentar o filtro por campeonato na listagem da API (código da spec 003)? → A: Sim
  (research.md, R-25), com o contrato da spec 003 e a coleção do Postman atualizados.

### Session 2026-10-09 (correções da análise)

- Q: Sem conexão, o que a tela mostra? → A: Uma página offline estática, sem dados, com a
  mensagem de erro de conexão. A página da tela nunca vem do cache, para nenhuma cotação antiga
  aparecer, nem com internet lenta (research.md, R-15).
- Q: E se a checagem de versão acontecer no meio do envio do código? → A: Enquanto o envio
  estiver em andamento, a checagem de versão e a atualização do ao vivo ficam pausadas; a
  checagem roda logo depois que o envio termina (FR-050a; research.md, R-15).

### Session 2026-10-09 (ajustes do responsável depois da implementação)

- Q: Os escudos dos times não aparecem. → A: O provedor envia o número do escudo (ou "escudo",
  sem escudo próprio); a imagem vem de `https://api.oddbrasil.com/img/mini/m_{escudo}.png`, como no
  sistema antigo.
- Q: Tamanho dos alertas no mobile? → A: 80% da largura da tela, com fonte e ícone menores.
- Q: Mensagem de erro com "(and 1 more error)"? → A: O apostador vê só a primeira mensagem de
  validação, sem o texto técnico acrescentado pelo Laravel.
- Q: "Criar Conta" no mobile? → A: Aparece também no mobile (antes só no desktop).
- Q: Tela de regras? → A: Redesenhada com práticas de UX: barra com "Voltar", título e subtítulo,
  regras numeradas em cartão de leitura confortável e atalho para dúvidas pelo WhatsApp (diferença
  visual aprovada).
- Q: Tela de "Enviar código / Enviar link"? → A: Redesenhada: confirmação "Aposta registrada!",
  código em destaque com botão Copiar, resumo (valor, prêmio, validade), botões grandes com ícone
  ("Compartilhar" no mobile), "Fazer outra aposta" e botão de fechar (diferença visual aprovada).
- Q: Borda da última linha de campeonato no menu? → A: Sem borda na última linha de cada país.
- Q: "Carregando jogos." na lista? → A: Trocado por um indicador animado na cor do tema.
- Q: Login e cadastro entram nesta spec? → A: Sim. Um modal com as abas "Entrar" e "Criar conta".
  Entrar aceita apostador (telefone + senha) e usuário do painel (login + senha) num campo só;
  criar conta cadastra o apostador. Depois de entrar ou cadastrar, mostra a confirmação e continua
  na mesma tela; o cabeçalho troca "Criar Conta / Entrar" por "Olá, nome" e "Sair". O login usa o
  token JWT das rotas da API que já existem, guardado no aparelho. O "Finalizar" continua gerando
  o código do visitante; apostar logado fica para a spec da área do cliente.
- Q: O cliente logado aposta por esta tela? → A: Sim (corrige a resposta anterior). Logado como
  cliente, a tela usa as cotações, os limites e o saldo do cliente, o "Finalizar" aposta com o
  saldo (aposta Ativa, sem código para o vendedor) e o cabeçalho mostra o saldo. No ao vivo a
  aposta fica "Em análise" durante o delay e a tela acompanha até o backend aceitar ou recusar.
  Usuário do painel logado continua apostando como visitante nesta tela.
- Q: O vendedor logado aposta por esta tela? → A: Sim, igual ao sistema antigo (corrige a resposta
  anterior para o vendedor). Logado como vendedor, a tela usa as cotações e os limites dele e o
  "Finalizar" aposta pelo painel (aposta Ativa, com a comissão dele); o sucesso mostra "Bilhete
  cadastrado com sucesso!" com Imprimir (pelo navegador, no formato do comprovante do antigo) e
  Enviar. Gestores (admin, supervisor, gerente) continuam apostando como visitante. Impressora
  Bluetooth e pelo APP ficam para outra spec.
- Q: E o código pendente pesquisado pelo vendedor? → A: Igual ao antigo: com vendedor logado, o
  código Pendente não abre o bilhete; a simulação (cotações e regras do vendedor) vai para o cupom
  e o "Finalizar" fica verde com "Validar". Sem login (ou código já validado), abre o bilhete.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Visitante vê os jogos do dia na tela principal (Priority: P1)

O visitante abre o site e vê a tela principal igual à de hoje: cabeçalho, barra de esportes, menu
de campeonatos por país, banners, busca, abas de data e a lista de jogos do futebol de hoje,
agrupados por campeonato, com as quatro cotações principais (C, E, F, A) e o "+N" de cada jogo.

**Why this priority**: é a porta de entrada do sistema; sem ela nenhuma aposta acontece.

**Independent Test**: abrir `/` no desktop e no celular com o sistema antigo aberto ao lado e
conferir que a tela é a mesma, com os jogos reais do dia.

**Acceptance Scenarios**:

1. **Given** jogos de futebol hoje, **When** o visitante abre `/`, **Then** vê os jogos agrupados
   por campeonato, com cabeçalho do campeonato (nome e data), escudos, nomes dos times, horário e
   os botões C, E, F e A com as cotações, e o "+N" com a quantidade de cotações do jogo.
2. **Given** a tela aberta no desktop (largura maior que 900px), **When** o visitante olha a tela,
   **Then** vê três colunas: "Menu" à esquerda, jogos no centro e "Cupom" à direita, com as
   mesmas medidas, cores e espaçamentos do sistema antigo.
3. **Given** a tela aberta no celular (largura até 900px), **When** o visitante olha a tela,
   **Then** vê o ícone de menu e "Entrar" no cabeçalho, a barra de esportes com rolagem
   horizontal, a barra de resumo (valor, retorno e "Conferir") e os jogos com os botões de odd
   embaixo dos times.
4. **Given** um jogo que começa em menos de 60 minutos, **When** a lista é exibida, **Then** o
   horário desse jogo aparece na cor do tema, como hoje.
5. **Given** mais jogos do que cabem na primeira página, **When** o visitante rola até perto do
   fim, **Then** mais jogos são carregados sem recarregar a tela.
6. **Given** nenhum jogo para o filtro, **When** a lista é exibida, **Then** aparece a mensagem
   de lista vazia do sistema antigo.

---

### User Story 2 - Visitante monta o cupom (Priority: P1)

O visitante clica nas cotações para montar o cupom: um palpite por jogo, com a soma da cotação
total, o retorno possível e o "vendedor paga" atualizados a cada mudança, e escolhe o valor
digitando ou pelos botões de valor rápido.

**Why this priority**: montar o cupom é a ação principal da tela.

**Independent Test**: escolher cotações de três jogos, trocar uma, remover outra, escolher o valor
e conferir os totais e o estado visual dos botões.

**Acceptance Scenarios**:

1. **Given** um jogo sem palpite, **When** o visitante clica na cotação C, **Then** o botão fica
   no estado selecionado (fundo transparente, texto e borda na cor do tema) e o palpite entra no
   cupom.
2. **Given** um palpite C no jogo, **When** o visitante clica em F no mesmo jogo, **Then** o
   palpite passa a ser F; o cupom continua com um palpite desse jogo.
3. **Given** um palpite C no jogo, **When** o visitante clica de novo em C, **Then** o palpite sai
   do cupom e o botão volta ao normal.
4. **Given** palpites no cupom, **When** o visitante digita um valor ou toca em 2, 3, 5, 10, 20
   ou 50, **Then** o valor é aplicado e a cotação total e o retorno possível são recalculados
   e o "vendedor paga" (FR-037a).
5. **Given** palpites no cupom, **When** o visitante clica no ícone de remover de um palpite,
   **Then** ele sai do cupom e o botão da cotação volta ao normal na lista.
6. **Given** palpites no cupom, **When** o visitante clica em "Limpar" e confirma, **Then** o
   cupom fica vazio e mostra "Nenhum jogo selecionado".
7. **Given** um cupom montado, **When** o visitante recarrega a página, **Then** o cupom continua
   igual.
8. **Given** o celular, **When** o visitante toca em "Conferir", **Then** o cupom abre sobre a
   tela, como hoje, com o mesmo conteúdo do desktop.

---

### User Story 3 - Visitante gera o código da aposta (Priority: P1)

Com o cupom montado, o visitante informa o nome, clica em "Finalizar" e recebe o código da aposta
para levar ao vendedor, como no sistema antigo.

**Why this priority**: é o fim do fluxo de aposta do visitante.

**Independent Test**: montar um cupom de pré-jogo, finalizar e conferir o código no modal de
sucesso.

**Acceptance Scenarios**:

1. **Given** um cupom válido de pré-jogo, **When** o visitante clica em "Finalizar", **Then** o
   sistema gera o código da aposta e mostra o modal de sucesso com o código, igual ao atual.
2. **Given** o envio em andamento, **When** o visitante olha o botão "Finalizar", **Then** vê o
   indicador de carregamento no lugar do texto e não consegue enviar de novo.
3. **Given** um cupom recusado pelo sistema (ex.: valor fora do limite, cotação alterada, jogo
   ao vivo, sistema travado), **When** o visitante finaliza, **Then** vê a mensagem devolvida
   pelo backend e o cupom continua montado.
4. **Given** o código gerado, **When** o modal de sucesso fecha, **Then** o cupom fica vazio.

---

### User Story 4 - Visitante troca de esporte, de dia e filtra (Priority: P2)

O visitante usa a barra de esportes, as abas de data, o menu de campeonatos e a busca por time
para chegar ao jogo que quer.

**Why this priority**: navegação essencial, mas a tela já entrega valor só com o futebol de hoje.

**Independent Test**: trocar de esporte, de dia, filtrar por campeonato e buscar um time,
conferindo a lista e o destaque visual de cada controle.

**Acceptance Scenarios**:

1. **Given** a barra de esportes, **When** o visitante clica em um esporte, **Then** o esporte
   fica destacado na cor do tema (sublinhado no celular) e a lista mostra os jogos dele.
2. **Given** as abas Hoje, Amanhã e o dia da semana de depois de amanhã, **When** o visitante
   clica em uma, **Then** a lista mostra os jogos daquele dia.
3. **Given** o menu de campeonatos, **When** o visitante clica em um campeonato, **Then** a lista
   mostra só os jogos dele; no celular, o menu fecha.
4. **Given** o campo "Digite o nome do time.", **When** o visitante digita e pressiona Enter,
   **Then** a lista mostra os jogos dos times encontrados; ao apagar o texto, a lista volta ao
   normal.
5. **Given** esportes que o visitante não pode ver (configurações do visitante e do sistema),
   **When** a barra é exibida, **Then** esses esportes não aparecem.

---

### User Story 5 - Visitante vê mais cotações de um jogo (Priority: P2)

O visitante clica no "+N" de um jogo e vê todas as cotações dele no modal de detalhes, podendo
escolher qualquer uma para o cupom.

**Why this priority**: aumenta as opções de aposta; o fluxo principal funciona sem ele.

**Independent Test**: abrir o "+N" de um jogo, escolher uma cotação e conferir o cupom.

**Acceptance Scenarios**:

1. **Given** um jogo com "+N", **When** o visitante clica nele, **Then** abre o modal de detalhes
   com os mercados e cotações, igual ao atual.
2. **Given** o modal aberto, **When** o visitante escolhe uma cotação, **Then** ela entra no
   cupom seguindo a regra de um palpite por jogo e aparece selecionada no modal.
3. **Given** um palpite de mercado do "+N", **When** o jogo aparece na lista, **Then** o "+N"
   fica na cor do tema, indicando que o palpite é de outro mercado, como hoje.

---

### User Story 6 - Visitante acompanha o ao vivo (Priority: P2)

Com o ao vivo habilitado, o visitante abre a aba "Ao vivo" e vê os jogos em andamento com placar,
período e minuto, com as cotações se atualizando sozinhas.

**Why this priority**: valor alto para o apostador, mas o visitante não finaliza aposta no ao vivo
(regra da spec 004).

**Independent Test**: abrir a aba "Ao vivo" e acompanhar a troca de cotações e o piscar dos
botões.

**Acceptance Scenarios**:

1. **Given** o ao vivo habilitado para o visitante, **When** ele abre "Ao vivo", **Then** vê os
   jogos em andamento com placar, período e minuto, como hoje.
2. **Given** a aba "Ao vivo" aberta, **When** uma cotação sobe ou desce, **Then** a lista se
   atualiza sozinha (a cada 7 segundos, como hoje) e o botão pisca em verde (subiu) ou vermelho
   (desceu).
3. **Given** o visitante sai da aba "Ao vivo", **When** está em outro esporte, **Then** as
   atualizações automáticas param.
4. **Given** o ao vivo desabilitado ou travado, **When** a tela carrega ou a atualização roda,
   **Then** a aba não aparece ou a tela volta para o futebol e mostra a mensagem do backend.

---

### User Story 7 - Visitante confere um bilhete pelo código (Priority: P2)

O visitante digita o código de um bilhete no campo "Digite o código aqui." e vê o comprovante.

**Why this priority**: o visitante usa para acompanhar a aposta feita.

**Independent Test**: digitar um código existente e um inexistente.

**Acceptance Scenarios**:

1. **Given** um código existente, **When** o visitante digita e pressiona Enter, **Then** abre o
   modal do bilhete com o comprovante, igual ao atual.
2. **Given** um código inexistente, **When** o visitante pressiona Enter, **Then** vê a mensagem
   de bilhete não encontrado.

---

### User Story 8 - Tema de cores (Priority: P2)

A tela usa a cor principal, a cor escura derivada e a cor de fundo configuradas, em todos os
elementos que hoje usam essas cores.

**Why this priority**: identidade visual de cada banca; a tela funciona com o tema padrão.

**Independent Test**: trocar a cor principal entre as 6 opções e a cor de fundo entre preto e
branco no dado fake e conferir a tela.

**Acceptance Scenarios**:

1. **Given** a cor principal configurada, **When** a tela abre, **Then** "Criar Conta", "Entrar",
   esporte ativo, botões de odd, botões de valor rápido, "Finalizar", "Conferir", ícone de menu e
   o X de fechar usam essa cor.
2. **Given** a cor principal, **When** os botões de odd são exibidos, **Then** o selo da letra
   (C, E, F, A) usa a cor escura derivada correspondente.
3. **Given** a cor de fundo configurada como preta, **When** o visitante abre a tela pela
   primeira vez, **Then** a tela abre no modo escuro, idêntico ao sistema antigo; com a cor de
   fundo branca, abre no modo claro.
4. **Given** a tela aberta, **When** o visitante toca no botão dia/noite, **Then** a tela troca
   entre claro e escuro na hora, sem recarregar, e o ícone mostra sol (claro) ou lua (escuro).
5. **Given** o visitante escolheu um modo, **When** volta ao site depois, **Then** a tela abre no
   modo que ele escolheu.

---

### User Story 9 - Menu, links e instalação como aplicativo (Priority: P3)

O visitante usa os itens do menu (Acumuladão, Limpar cache, Regras), o botão do WhatsApp, o
rodapé e pode instalar o site como aplicativo no celular ou no computador.

**Why this priority**: complementos da tela; a aposta funciona sem eles.

**Independent Test**: abrir o menu no celular, usar cada item, tocar no WhatsApp e instalar o
site.

**Acceptance Scenarios**:

1. **Given** o celular, **When** o visitante toca no ícone de menu, **Then** a gaveta abre da
   esquerda (70% da largura) sobre um fundo escurecido, com "Menu", o X de fechar, Acumuladão,
   Limpar cache, Regras e a lista de países e campeonatos, sem Impressão e Largura.
2. **Given** o menu, **When** o visitante clica em "Acumuladão", **Then** nada acontece nesta spec.
3. **Given** o menu, **When** o visitante clica em "Limpar cache" e confirma, **Then** todos os
   dados locais são apagados, inclusive o cupom, e a página recarrega.
4. **Given** o menu, **When** o visitante clica em "Regras", **Then** vê as regras da banca.
5. **Given** o botão flutuante do WhatsApp, **When** o visitante toca nele, **Then** abre a
   conversa com a banca com a mensagem padrão do sistema antigo.
6. **Given** um navegador compatível, **When** o visitante instala o site, **Then** ele abre como
   aplicativo, em tela cheia, com o nome e o ícone da banca.
7. **Given** o site aberto (no navegador ou instalado), **When** uma versão nova é publicada,
   **Then** na próxima interação ou ao voltar para a aba o visitante já está na versão nova, sem
   limpar cache, sem reinstalar e sem perder o cupom.
8. **Given** uma configuração alterada no servidor (ex.: banner ou texto), **When** o visitante
   recarrega ou navega, **Then** vê a configuração nova sem que uma versão tenha sido publicada.

---

### Edge Cases

- Sem conexão: abre a página offline do aplicativo, sem dados, com a mensagem de erro de
  conexão; jogos, cotações e bilhetes nunca são exibidos de cache.
- Cotação bloqueada: o botão mostra o cadeado e não pode ser escolhido.
- Palpite no cupom de um jogo que saiu da lista (começou, foi removido ou ficou bloqueado): o
  palpite continua no cupom até o envio; o backend decide e a mensagem dele é mostrada.
- Palpite do ao vivo no cupom do visitante: "Finalizar" mostra a mensagem do backend ("Para
  apostar no ao vivo é preciso fazer login.").
- Cupom salvo no aparelho com formato antigo ou corrompido: o cupom é descartado e começa vazio,
  sem erro na tela.
- Valor digitado com vírgula, vazio, zero ou negativo: tratado como no sistema antigo (o retorno
  fica zerado e o backend valida no envio).
- Muitos palpites: a lista do cupom rola dentro da coluna, sem empurrar os botões de baixo.
- Excesso de requisições (429): a mensagem do backend é mostrada e a tela não trava.
- Escudo ou bandeira que não carrega: o espaço da imagem é mantido e o layout não se desloca.
- Tela girada ou janela redimensionada: o layout e o comportamento passam entre desktop e
  mobile ao cruzar 900px, sem recarregar.
- Versão nova publicada enquanto o visitante monta o cupom: a troca de versão mantém o cupom
  (ele fica no aparelho) e não interrompe um envio em andamento.
- Visitante sem conexão no momento da troca de versão: continua na versão aberta até a conexão
  voltar; nunca fica preso numa versão antiga depois disso.
- Nome do sistema, telefone e links de redes sociais ausentes: o elemento correspondente não
  aparece.

## Requirements *(mandatory)*

### Functional Requirements

**Área, tema e fidelidade visual**

- **FR-001**: A tela principal DEVE ficar na rota `/` (área "site de apostas"). As áreas `/app`
  e `/cassino` não fazem parte desta spec. A área NÃO DEVE ser guardada no aparelho.
- **FR-002**: A tela DEVE ser visualmente idêntica à tela `screens/main` do sistema antigo no
  desktop e no mobile: layout, cores, fonte (Roboto), ícones (Material Icons), imagens,
  espaçamentos, tamanhos, textos e rótulos, animações e comportamento responsivo, seguindo o
  inventário visual do `research.md`. Os textos dos esportes são os atuais ("Hoquei no gelo",
  "Baisebol"). As únicas diferenças visuais são as aprovadas em Clarifications (FR-003a a
  FR-003d e o modo claro do FR-053).
- **FR-003**: Uma única regra de 900px DEVE definir o layout e o comportamento: acima de 900px,
  desktop; até 900px, mobile. As regras de 1024px do sistema antigo passam a seguir os 900px. O
  layout e o comportamento DEVEM acompanhar a largura atual da janela (girar a tela ou
  redimensionar).
- **FR-003a**: No desktop, as colunas "Menu" e "Cupom" DEVEM ter 20% da largura, com mínimo de
  240px; a coluna central absorve a diferença.
- **FR-003b**: No celular, a altura da tela DEVE ser a área visível real, para que a barra do
  navegador não esconda o fim da lista nem os botões do cupom.
- **FR-003c**: No mobile, o fim da lista de jogos DEVE ter espaço livre suficiente para o botão
  flutuante do WhatsApp não cobrir o último jogo.
- **FR-003d**: O zoom da página DEVE continuar bloqueado, como hoje.
- **FR-004**: O tema DEVE ter três cores: cor principal (`#c40808`, `#d0af01`, `#008000`,
  `#006eb1`, `#fe6a00` ou `#b91552`), cor escura derivada, na mesma ordem (`#a41f1a`, `#9f8601`,
  `#005400`, `#024b77`, `#b94e02`, `#930137`), e cor de fundo (`#000000` ou `#FFFFFF`), que
  define o modo inicial (escuro ou claro) do FR-053.
- **FR-005**: Todo elemento que no sistema antigo usa a cor principal ou a cor derivada DEVE usar
  a mesma cor do tema na tela nova, nos dois modos (claro e escuro).
- **FR-006**: Os componentes de cotação, card de jogo e cupom DEVEM poder ser reutilizados pelas
  áreas `/app` e `/cassino` em specs futuras.

**Cabeçalho, barra de esportes e menu**

- **FR-007**: O cabeçalho DEVE mostrar, no desktop, o logo à esquerda e "Criar Conta" (preenchido
  na cor do tema) e "Entrar" (contornado na cor do tema) à direita; no mobile, o ícone de menu na
  cor do tema à esquerda e "Criar Conta" e "Entrar" à direita.
- **FR-008**: "Criar Conta" e "Entrar" DEVEM abrir o modal de acesso, nas abas "Criar conta" e
  "Entrar" (FR-057 a FR-059).
- **FR-009**: A barra de esportes DEVE mostrar, nesta ordem, com ícone e rótulo: Cassino, Futebol,
  Ao vivo, Basquete, Lutas, Especiais, Vôlei, Tênis, Tênis de mesa, E-sports, Futebol americano,
  Rugby, Hoquei no gelo, Handebol, Baisebol; o esporte ativo fica na cor do tema (sublinhado no
  mobile).
- **FR-010**: Cada esporte DEVE aparecer conforme as configurações do visitante e do sistema:
  "Ao vivo" só com o ao vivo habilitado; os esportes além do futebol só com "outros esportes"
  permitido; "Cassino" só com o indicador de cassino ligado. A barra inteira some quando só o
  futebol é permitido, como hoje.
- **FR-011**: Clicar em "Cassino" ou em "Especiais" NÃO DEVE fazer nada nesta spec (os
  especiais ainda não existem no backend novo; entram na spec dos especiais).
- **FR-012**: No mobile, a barra de esportes DEVE rolar na horizontal, com cada item ocupando 1/5
  da largura útil.
- **FR-013**: A coluna "Menu" (desktop) e a gaveta de menu (mobile, 70% da largura, abrindo da
  esquerda sobre fundo escurecido, com "Menu" e X de fechar na cor do tema) DEVEM mostrar
  Acumuladão, Limpar cache e Regras e, depois, os campeonatos agrupados por país (linha escura com
  bandeira e nome do país em maiúsculas; linhas com nome do campeonato e selo com a quantidade de
  jogos).
- **FR-014**: O visitante NÃO DEVE ver os itens Impressão e Largura do menu.
- **FR-015**: "Acumuladão" DEVE aparecer quando o indicador de acumuladão estiver ligado e NÃO
  DEVE fazer nada nesta spec.
- **FR-016**: "Limpar cache" DEVE pedir confirmação, apagar todos os dados locais da tela
  (inclusive o cupom) e recarregar a página.
- **FR-017**: "Regras" DEVE mostrar as regras da banca.
- **FR-018**: Clicar em um campeonato DEVE filtrar a lista por ele; no mobile, a gaveta fecha.

**Lista de jogos**

- **FR-019**: A coluna central DEVE mostrar, nesta ordem: carrossel de banners (troca automática
  a cada 5 segundos, em loop; com setas no mobile), busca por time ("Digite o nome do time."),
  conferência de bilhete ("Digite o código aqui."), abas de data (Hoje, Amanhã e o dia da semana
  de depois de amanhã) e a lista de jogos.
- **FR-020**: Os jogos DEVEM vir agrupados por campeonato, com cabeçalho escuro (nome do
  campeonato e data).
- **FR-021**: Cada jogo DEVE mostrar escudo e nome dos times da casa e de fora, ícone de relógio
  e horário (na cor do tema quando faltar menos de 60 minutos para o início), os botões C (casa),
  E (empate), F (fora) e A (ambas) com o selo da letra e a cotação, e o "+N" com a quantidade de
  cotações do jogo.
- **FR-022**: No mobile, o card do jogo DEVE mostrar os times empilhados com o horário à direita
  e, abaixo, a linha com os quatro botões e o "+N".
- **FR-023**: O botão de cotação DEVE ter quatro estados visuais iguais aos atuais: normal (fundo
  na cor do tema), selecionado (fundo transparente, texto e borda na cor do tema), bloqueado (com
  cadeado, sem clique) e variação (pisca em verde quando a cotação sobe e em vermelho quando
  desce).
- **FR-024**: A lista DEVE carregar mais jogos ao rolar perto do fim, sem recarregar a tela.
- **FR-025**: Trocar de esporte DEVE mostrar os jogos daquele esporte ("Especiais" e "Cassino"
  seguem o FR-011).
- **FR-026**: As abas de data DEVEM mostrar os jogos de hoje, de amanhã e de depois de amanhã.
- **FR-027**: A busca por time DEVE rodar ao pressionar Enter; apagar o texto DEVE voltar à lista
  normal. No ao vivo, a busca segue o comportamento atual.
- **FR-028**: Lista vazia DEVE mostrar a mensagem do sistema antigo.
- **FR-029**: Enquanto os jogos carregam pela primeira vez, a tela DEVE mostrar o carregamento do
  sistema antigo ("Carregando jogos.").

**Detalhes do jogo ("+N")**

- **FR-030**: Clicar no "+N" DEVE abrir o modal de detalhes com todos os mercados e cotações do
  jogo, igual ao atual.
- **FR-031**: Escolher uma cotação no modal DEVE seguir a mesma regra de palpite do FR-034 e
  aparecer selecionada no modal.
- **FR-032**: Quando o palpite do jogo for de um mercado do "+N", o "+N" na lista DEVE ficar na
  cor do tema.

**Ao vivo**

- **FR-033**: A aba "Ao vivo" DEVE mostrar os jogos em andamento com placar, período e minuto e
  DEVE atualizar as cotações automaticamente a cada 7 segundos, só enquanto a aba estiver aberta.
  Se o backend responder que o ao vivo não está disponível, a tela DEVE voltar ao futebol e
  mostrar a mensagem.
- **FR-033a**: O visitante DEVE poder adicionar cotações do ao vivo ao cupom, como no pré-jogo.
  Ao finalizar um cupom com palpite ao vivo, a tela DEVE mostrar a recusa do backend ("Para
  apostar no ao vivo é preciso fazer login.") e manter o cupom montado (FR-043).

**Cupom**

- **FR-034**: O cupom DEVE ter um palpite por jogo: clicar numa cotação de um jogo sem palpite
  adiciona; clicar na mesma cotação remove; clicar em outra cotação do mesmo jogo troca.
- **FR-035**: O cupom DEVE mostrar a lista de palpites (times, mercado, cotação, remover e "mais
  opções") ou "Nenhum jogo selecionado"; o campo "Nome apostador"; o valor; o retorno possível;
  a cotação total; o "vendedor paga" (FR-037a); os botões de valor rápido 2, 3, 5, 10, 20 e 50; "Limpar"
  (cinza) e "Finalizar" (cor do tema) com o selo da quantidade de palpites.
- **FR-036**: No mobile, a barra de resumo (valor, retorno possível e "Conferir" com a quantidade
  de palpites) DEVE ficar abaixo da barra de esportes, e "Conferir" DEVE abrir o cupom sobre a
  tela, como hoje.
- **FR-037**: A cotação total, o retorno possível e o "vendedor paga" DEVEM ser recalculados a
  cada mudança de palpite ou de valor, com valores em dinheiro em centavos inteiros (sem ponto
  flutuante), seguindo as regras e limites do visitante informados pelo backend. Esses valores
  são estimativas; o valor final é o do backend.
- **FR-037a**: O "vendedor paga" DEVE aparecer sempre, ao lado da cotação total, como no sistema
  antigo, e DEVE mostrar o mesmo valor do prêmio (retorno possível). O desconto sobre esse valor
  depende da configuração `vendedor_paga`, que ainda não existe no backend e será criada em outra
  spec.
- **FR-038**: "Limpar" DEVE pedir confirmação e esvaziar o cupom.
- **FR-039**: O cupom DEVE ser mantido no aparelho ao recarregar a página. Um cupom salvo em
  formato inválido DEVE ser descartado sem erro.
  O cupom restaurado NÃO DEVE ser alterado pela tela (sem remover jogos começados, sem atualizar
  cotações e sem prazo de validade); jogos começados e cotações alteradas são tratados pelo
  backend no "Finalizar" (FR-043).
- **FR-040**: "Mais opções" de um palpite DEVE abrir o modal de detalhes do jogo (FR-030).

**Código da aposta e bilhete**

- **FR-041**: "Finalizar" DEVE enviar o cupom do visitante para gerar o código da aposta e, com
  sucesso, mostrar o modal de sucesso com o código e esvaziar o cupom.
- **FR-042**: Durante o envio, "Finalizar" DEVE mostrar o indicador de carregamento e bloquear
  novos envios.
- **FR-043**: Recusas do backend (validação, cotação alterada, jogo ao vivo, sistema travado,
  excesso de requisições) DEVEM mostrar a mensagem devolvida e manter o cupom.
- **FR-044**: O campo "Digite o código aqui." DEVE, ao pressionar Enter, buscar o bilhete pelo
  código e abrir o modal do bilhete com o comprovante; código inexistente DEVE mostrar a mensagem
  de não encontrado.

**Rodapé, WhatsApp e mensagens**

- **FR-045**: O botão flutuante do WhatsApp DEVE ficar no canto inferior esquerdo e abrir a
  conversa com o telefone da banca e a mensagem padrão do sistema antigo.
- **FR-046**: O rodapé DEVE mostrar as redes sociais, o link de jogo responsável, o copyright com
  o nome do sistema e a versão, como hoje.
- **FR-047**: Alertas, confirmações e mensagens DEVEM ter o mesmo visual e texto dos atuais.

**PWA, atualização e cache**

- **FR-048**: O site DEVE ser instalável como aplicativo, com nome, ícones e abertura em tela
  cheia equivalentes ao manifest atual.
- **FR-049**: O cache do aplicativo DEVE guardar só arquivos estáticos com identificação de
  versão no nome (código, estilos, fontes, ícones e imagens fixas). A página (HTML), os jogos, as
  cotações, os bilhetes e as configurações NÃO DEVEM ser servidos do cache quando houver conexão.
- **FR-050**: O servidor DEVE ser a fonte da versão: depois de publicada uma versão nova, o
  visitante DEVE passar a usá-la automaticamente na próxima interação com o servidor ou ao voltar
  para a aba, sem limpar cache, sem reinstalar e sem aviso para confirmar.
- **FR-050a**: A troca de versão NÃO DEVE perder o cupom nem interromper um envio em andamento.
- **FR-050b**: Não DEVE existir número de versão mantido à mão para o cache. A versão mostrada no
  rodapé DEVE vir do servidor.
- **FR-050c**: Configurações (tema, banners, textos, contatos) DEVEM vir do servidor a cada
  carga, para que mudar uma configuração não exija publicar uma versão.

**Modo claro e escuro (dia/noite)**

- **FR-053**: A tela DEVE ter um botão de alternar dia/noite: no desktop, no cabeçalho, à
  esquerda de "Criar Conta"; no mobile, no cabeçalho, à esquerda de "Entrar". O ícone mostra sol
  no modo claro e lua no modo escuro.
- **FR-054**: O modo escuro DEVE ser idêntico ao visual do sistema antigo. O modo claro é novo:
  troca os fundos e textos fixos (pretos e cinzas) por equivalentes claros e mantém as cores do
  tema. A paleta do modo claro é definida no plano e aprovada pelo responsável antes da
  implementação (Princípio VIII).
- **FR-055**: O modo inicial DEVE seguir a cor de fundo configurada (`#000000` → escuro,
  `#FFFFFF` → claro). A escolha do visitante DEVE ficar salva no aparelho e valer nas próximas
  visitas; "Limpar cache" volta ao modo configurado.
- **FR-056**: A troca de modo DEVE acontecer na hora, sem recarregar a página e sem piscar o
  modo errado ao abrir.

**Login e cadastro**

- **FR-057**: "Entrar" DEVE aceitar, num campo só, o telefone do apostador (com DDD) ou o login do
  usuário do painel, e a senha; um telefone é tentado primeiro como apostador e um login primeiro
  como usuário do painel. Credenciais recusadas mostram a mensagem do backend.
- **FR-058**: "Criar conta" DEVE cadastrar o apostador com nome, telefone, data de nascimento (18
  anos ou mais), gênero e senha (com confirmação), e opcionalmente e-mail, CPF, código de afiliado
  (preenchido pelo link `?user=`) e aceite de promoções; erros de validação aparecem em cada campo.
  O cadastro já deixa o apostador conectado.
- **FR-059**: Com sessão, o cabeçalho DEVE mostrar "Olá, nome" e "Sair" (com confirmação); para o
  cliente, também o saldo. A sessão (token JWT) fica no aparelho até vencer ou até "Sair"/"Limpar
  cache"; token recusado pelo servidor encerra a sessão com aviso.
- **FR-059a**: Com sessão de cliente, a lista, o "+N" e o cupom DEVEM usar as cotações e os limites
  do cliente, e o "Finalizar" DEVE apostar pela área do cliente com o saldo dele (nome da conta,
  campo do nome bloqueado), mostrando "Aposta confirmada!" e atualizando o saldo. No ao vivo, a
  aposta "Em análise" é acompanhada até ser aceita (confirmação) ou recusada (motivo do backend).
- **FR-059b**: Com sessão de vendedor, a lista, o "+N" e o cupom DEVEM usar as cotações e os
  limites do vendedor, e o "Finalizar" DEVE apostar pelo painel (`POST /api/apostas`), com o nome
  do apostador digitado, mostrando "Bilhete cadastrado com sucesso!" com Imprimir (impressão do
  navegador) e Enviar (WhatsApp/compartilhar). No ao vivo, o "Em análise" é acompanhado como no
  cliente. Sessão de gestor do painel aposta como visitante.
- **FR-059c**: Com sessão de vendedor, pesquisar um código Pendente DEVE carregar a simulação no
  cupom (nome, valor e palpites disponíveis; os indisponíveis saem com o motivo) e trocar o
  "Finalizar" por "Validar" (verde); validar mostra "Bilhete validado com sucesso!" com Imprimir e
  Enviar. Cupom esvaziado ou "Limpar" sai da validação.

**Dados reais**

- **FR-051**: Os jogos (pré-jogo e ao vivo), os campeonatos por país com a quantidade de jogos,
  os detalhes do jogo, o código da aposta do visitante e a consulta do bilhete DEVEM vir das
  funcionalidades já existentes no backend (specs 003 e 004).
- **FR-052**: O nome do sistema, a mensagem do bilhete e as configurações do visitante (esportes
  permitidos, outros esportes, ao vivo habilitado e limites de aposta) DEVEM vir do backend.

**Dados fake (Princípio IX)**

- **FR-060**: Tema (cor principal, cor derivada e cor de fundo), logo, favicon e ícones do
  aplicativo DEVEM ser fake até a spec de configurações visuais.
- **FR-061**: Telefone do WhatsApp e links das redes sociais DEVEM ser fake até a spec de
  configurações visuais.
- **FR-062**: Banners DEVEM ser fake até a spec de banners; indicadores de Acumuladão e de
  Cassino DEVEM ser fake até as specs próprias; o texto das regras DEVE ser fake até a spec de
  regras.
- **FR-063**: Os dados fake DEVEM ficar em um local único e identificável, ter o mesmo formato
  dos dados reais e nunca entrar no envio da aposta.

### Key Entities

- **Tema**: cor principal, cor escura derivada e cor de fundo da banca (fake nesta spec); a cor
  de fundo define o modo inicial.
- **Preferência de modo**: claro ou escuro, escolhido pelo visitante e guardado no aparelho.
- **Esporte**: item da barra de esportes (nome, ícone, se está visível para o visitante).
- **País e campeonato**: agrupamento do menu, com bandeira, nome e quantidade de jogos.
- **Jogo (confronto)**: times, escudos, início, minutos para o início, cotações principais
  (C, E, F, A), quantidade de cotações e, no ao vivo, placar, período e minuto.
- **Palpite**: jogo, mercado escolhido e cotação no momento da escolha.
- **Cupom**: palpites, nome do apostador, valor e os totais estimados; guardado no aparelho.
- **Código da aposta**: código gerado para o visitante levar ao vendedor.
- **Bilhete (comprovante)**: dados do bilhete exibidos na consulta por código.
- **Banner**: imagem e link do carrossel (fake nesta spec).
- **Contato da banca**: telefone do WhatsApp e redes sociais (fake nesta spec).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Comparando lado a lado com o sistema antigo, no modo escuro, nas larguras 360, 414,
  768, 900, 901, 1024, 1366 e 1920 pixels, 100% dos itens do inventário visual do `research.md`
  ficam iguais (medidas, cores, espaçamentos, textos e posição), exceto as diferenças aprovadas
  (FR-003a a FR-003c e o botão dia/noite).
- **SC-002**: Com cada uma das 6 cores principais, todos os elementos tematizados ficam com a
  mesma cor do sistema antigo, nos modos claro e escuro; o modo escuro fica idêntico ao sistema
  antigo.
- **SC-009**: Depois de publicar uma versão nova, 100% dos visitantes com a tela aberta passam a
  usá-la na próxima interação ou ao voltar para a aba, sem nenhuma ação manual (limpar cache,
  reinstalar, confirmar aviso).
- **SC-010**: Uma configuração alterada no servidor aparece para o visitante na próxima carga,
  sem publicar versão.
- **SC-003**: Um visitante monta um cupom com 3 palpites e recebe o código da aposta em menos de
  1 minuto.
- **SC-004**: Em 100% das recargas de página, o cupom montado continua igual.
- **SC-005**: Na aba "Ao vivo", as cotações mudam na tela no máximo 7 segundos depois de mudarem
  no backend, e nenhuma atualização roda com a aba fechada.
- **SC-006**: Sem conexão, nenhuma cotação ou jogo antigo é exibido como se fosse atual.
- **SC-007**: O site é instalável como aplicativo no celular (Android e iOS) e no computador.
- **SC-008**: Em um celular comum com internet 4G, a lista de jogos aparece em até 3 segundos
  após abrir a tela.

## Assumptions

- O visitante é quem acessa sem login; vendedor, cliente e administrador logados ficam para
  outras specs, assim como o login web por sessão.
- As funcionalidades públicas do backend (listagem de confrontos, detalhes, código da aposta do
  visitante e consulta do bilhete) das specs 003 e 004 são usadas como estão; se a tela precisar
  de algum dado público que elas não entregam, a diferença é tratada no plano (Princípio IV).
- Os limites de aposta do visitante (valor mínimo, máximo, prêmio máximo) já existem nas
  configurações de visitante da spec 004 e são entregues à tela para a estimativa do cupom.
- A versão exibida no rodapé vem do servidor (FR-050b).
- Imagens fixas do sistema antigo (ícones de dinheiro, troféu, cotação, vendedor paga, redes
  sociais, jogo responsável) são copiadas para o projeto novo; os escudos e as bandeiras vêm do
  backend.
- O esporte "Especiais" aparece na barra, mas sem ação, até a spec dos especiais (o backend novo
  ainda não tem especiais e cotação fake nunca é exibida).
- Cotações e jogos nunca são fake: tudo que pode virar aposta vem do backend.
