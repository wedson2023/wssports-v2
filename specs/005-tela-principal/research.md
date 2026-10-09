# Research: Tela principal de apostas esportivas (área `/`)

**Feature**: `005-tela-principal` | **Data**: 2026-10-09 | **Spec**: [spec.md](spec.md)

Pesquisa feita antes da spec, a pedido do responsável (Princípio VII). O sistema antigo foi
consultado somente para leitura. Os valores de medidas e cores abaixo foram extraídos dos
`styles.js` (styled-components) do sistema antigo e são a referência do "visualmente idêntico"
(Princípio VIII). O plano (`/speckit-plan`) complementa este arquivo com as decisões técnicas.

## R-01. Arquivos do sistema antigo consultados

Base: `C:\Users\wedso\OneDrive\Área de Trabalho\projetos\wssports.bet`.

| Arquivo | O que foi visto |
|---|---|
| `resources/js/screens/main/index.js` (2.413 linhas) | Estado da tela, carga dos jogos, ao vivo, cupom, envio, consulta de bilhete, menu, rodapé |
| `resources/js/screens/main/styles.js` (72 componentes) | Medidas, cores e breakpoints da tela |
| `resources/js/screens/main/components/matches/*` | Card de jogo, botões de odd, letra, cadeado, variação, "+N" |
| `resources/js/screens/main/components/specials/*` | Lista de especiais |
| `resources/js/screens/main/components/casinos/*` | Jogos de cassino na home (fora do escopo) |
| `resources/js/modals/odd`, `success`, `ticket`, `sports`, `table` | Modais usados pela tela |
| `resources/js/styles/index.js` | Estilo global: Roboto 14px, `#333`, reset, altura 100vh |
| `resources/js/routes.js` | Troca de "tema" SITE/APP/CASINO por `localStorage` |
| `resources/js/themes/app/*` (≈5.800 linhas) | Modo APP do vendedor (login, home, config, financeiro) |
| `resources/views/app.blade.php` | Inputs ocultos de config (`temas`, `cor_fundo`, `letter` etc.), manifest, ícones iOS |
| `routes/web.php` | Mapeamento cor principal → `letter` |
| `database/migrations/2021_03_11_145447_create_configs_table.php` | Opções de `temas` e `cor_fundo` |
| `public/manifest.webmanifest`, `public/sw.js` | PWA atual |
| Prints do responsável (desktop 1366px, mobile ≈415px, menu mobile) | Conferência visual |

## R-02. Como a tela funcionava

- **Uma tela só**: todo o comportamento estava em `screens/main/index.js`, com cerca de 40
  estados e 11 modais controlados por booleanos.
- **Configuração**: o Blade gravava as configs em `<input type="hidden">`, lidas com
  `document.getElementById(...)`. Token, `pin` e `config` (base64) ficavam no `localStorage`.
- **Jogos**: `GET home?de&ate&pin&tipo_esporte&pesquisar` trazia todos os jogos; a rolagem
  infinita só paginava no navegador (`paginator`) quando faltavam 300px para o fim.
- **Ao vivo**: `GET futebol-ao-vivo` a cada 7 segundos (`setInterval` guardado em estado). A
  variação da cotação era guardada em `variations` e o botão piscava em verde (subiu) ou vermelho
  (desceu) por 0,5s, 4 vezes. Com "Jogos travados" ou "não autorizado", voltava ao futebol e
  esvaziava o cupom.
- **Cupom**: `bet()` alterava os objetos diretamente (`match.active`, `palpites.splice`); um
  palpite por jogo; o retorno era calculado no navegador com ponto flutuante, aplicando
  multiplicador, prêmio máximo e "ganho múltiplos palpites"; o "vendedor paga" descontava a
  comissão por prêmio.
- **Visitante**: "Finalizar" chamava `generate_code` (`POST home?pin`) e mostrava o código.
- **Conferir bilhete**: Enter no campo de código chamava `GET home/{code}`.
- **Busca**: Enter no campo de time; apagar o texto voltava à lista.
- **Responsivo**: CSS com `max-width:900px`; o JS lia `window.innerWidth` uma vez só (não reagia
  a girar a tela) e usava 1024px para impressão e menu; a barra de esportes no mobile era
  dimensionada por manipulação direta do DOM (`(largura - 20) / 5` por item).
- **Problemas encontrados** (corrigidos na reescrita, sem mudar o visual): estado mutável,
  dinheiro em ponto flutuante, fluxo de imprimir/WhatsApp copiado 4 vezes, "retry" copiado 3
  vezes, `setInterval` sem limpeza garantida, largura lida uma vez, token em `localStorage`.

## R-03. Decisão: Inertia para o site e API JWT mantida

- **Decisão**: o site web usa Inertia; as páginas recebem os dados prontos do servidor, e os
  controllers web reaproveitam os `app/Services` existentes. A API JWT continua para app e
  integrações, sem mudança.
- **Motivo**: o responsável sugeriu Inertia e deixou a escolha a critério da análise. Usar os dois
  mantém a API pronta e dá ao site navegação sem camada de API no navegador. Os dados fake ficam no
  servidor e trocá-los pelo real não muda os componentes (Princípio IX).
- **Alternativas descartadas**: Inertia só como casca chamando a API JWT (complexidade sem ganho);
  SPA sem Inertia (não é a stack pedida).
- **Ponto para o plano**: o login web por sessão fica para a spec de login; nesta spec só há
  visitante. Os detalhes (rotas web, controllers, props) são definidos no `/speckit-plan`.

## R-04. Decisão: tema só de cores e áreas por rota

- **Antes**: "tema" misturava a cor (`temas`, `cor_fundo`, `letter`, vindos do banco) com o modo
  de layout guardado no aparelho (`localStorage.theme` = SITE, APP ou CASINO), que trocava a
  árvore inteira de rotas e exigia recarregar a página.
- **Agora**: tema = cores. Áreas = rotas: `/` (site, esta spec), `/app` (layout de aplicativo) e
  `/cassino`. Aprovado pelo responsável em 2026-10-09; registrado na Constituição 2.0.0
  (Princípio VIII).
- **Cores**:

| Cor principal (`temas`) | Nome | Cor derivada (`letter`) |
|---|---|---|
| `#c40808` | Vermelho (padrão) | `#a41f1a` |
| `#d0af01` | Amarelo | `#9f8601` |
| `#008000` | Verde | `#005400` |
| `#006eb1` | Azul | `#024b77` |
| `#fe6a00` | Laranja | `#b94e02` |
| `#b91552` | Rosa | `#930137` |

Cor de fundo (`cor_fundo`): `#000000` (padrão) ou `#FFFFFF`. **Achado**: o sistema antigo lê
`cor_fundo` (`main/index.js`, linha 212), mas nenhum estilo da tela principal a aplica; os fundos
são fixos (ver R-07). **Decisão (2026-10-09)**: `cor_fundo` passa a definir o modo inicial da
tela (escuro ou claro), e o visitante pode alternar com o botão dia/noite (ver R-11).

## R-05. Decisão: idioma e nomes

- Spec, plano, tarefas e comentários em português (Constituição 2.0.0, Princípio II).
- Frontend (Constituição 2.1.0, Princípios I e III): componentes e styled-components em inglês e
  PascalCase (ex.: `OddButton`), com a pasta do componente com o mesmo nome; variáveis, funções e
  hooks em camelCase e português (ex.: `adicionarPalpite`, `useCupom`); chaves vindas do backend
  usadas como chegam (ex.: `time_casa`). Demais pastas em inglês e `snake_case`.

## R-06. Breakpoints

**Encontrado no sistema antigo**:

| Onde | Breakpoint | Uso |
|---|---|---|
| `main/styles.js` | `max-width:900px` (11×), `min-width:900px` (2×) | Troca desktop × mobile |
| `matches/styles.js` | `max-width:900px` (5×) | Card de jogo empilhado |
| `modals/odd`, `success`, `ticket`, `sports`, `table` | `max-width:900px` | Modais em tela cheia |
| `styles/index.js` | `max-width:1024px` | Altura mínima `calc(100vh - 56px)` |
| `main/index.js` (JS) | `width <= 900` | Barra de esportes por DOM |
| `main/index.js` (JS) | `width > 1024` / `< 1024` | Imprimir × compartilhar, itens de impressão do menu |
| `casinos/styles.js` | 400px, 600px | Fora do escopo |

O layout passa a reagir à largura atual (girar ou redimensionar), o que não muda o visual em
nenhuma largura.

**Sugestões apresentadas e decisão do responsável (2026-10-09)**:

| Sugestão | Decisão | Requisito |
|---|---|---|
| 1. Unificar 900px e 1024px | Aprovada: uma regra de 900px para layout e comportamento | FR-003 |
| 2. Mínimo de 240px nas colunas laterais | Aprovada | FR-003a |
| 3. Altura visível real no celular | Aprovada | FR-003b |
| 4. Liberar o zoom | Recusada: o zoom continua bloqueado | FR-003d |
| 5. Espaço para o botão do WhatsApp | Aprovada (espaço livre no fim da lista) | FR-003c |

Texto original das sugestões:

1. **Faixa 900–1024px**: hoje o CSS mostra o layout desktop, mas o JS trata como mobile (ex.:
   "Enviar" no lugar de "Fechar"). Sugestão: unificar a regra para que o comportamento siga o
   layout que está na tela.
2. **Colunas de 20% em telas médias (900–1200px)**: a 901px, "Menu" e "Cupom" ficam com cerca de
   180px, e os 6 botões de valor e as linhas de dois campos ficam apertados. Sugestão: largura
   mínima para as colunas laterais (ex.: 240px), com a coluna central absorvendo a diferença.
3. **Altura `100vh` no celular**: a barra do navegador corta o fim da tela e o botão "Finalizar"
   pode ficar escondido. Sugestão: usar a altura visível real da tela.
4. **Zoom bloqueado** (`user-scalable=no`): impede ampliar a tela. Sugestão: liberar o zoom
   (acessibilidade).
5. **Botão do WhatsApp sobre as odds no mobile**: no print do mobile ele cobre a odd "C" e o nome
   do time. Sugestão: espaço livre no fim da lista ou recuo do botão durante a rolagem.

## R-07. Inventário visual (componente antigo → valores → componente novo)

Valores tirados dos `styles.js`. "tema" = cor principal; "derivada" = `letter`. Os nomes
dos componentes novos são em inglês e PascalCase, escolhidos para serem intuitivos
(Constituição 2.1.0, Princípios I e III); o plano pode ajustá-los.

**Estrutura e cabeçalho** (`screens/main/styles.js`)

| Antigo | Valores | Novo |
|---|---|---|
| Global (`styles/index.js`) e `Nav`/`Loading` fundo `#000` | Roboto 400/500, 14px, `#333`, antialiased; `html, body` 100vh, sem rolagem; reset de margin/padding | `GlobalStyle` |
| `Nav` | padding 5px 7px; fundo `#000`; grid `auto 100px` (+100px com cadastro), gap 10px; ícone de menu 40px na cor do tema, só ≤ 900px (48px de largura) | `Header` |
| `Logo` | largura 50px | `Header` |
| `BtnRegister` / `BtnEnter` | padding 7px; raio 0.3em; borda fina na cor do tema; texto `#fff` 13px (Criar Conta preenchido) | `Header` |
| — (novo) | botão dia/noite no cabeçalho, ícone sol/lua (R-11) | `ThemeToggle` |
| `ContainerSports` | fundo `#333`; 100%; padding 8px 10px (12px 10px ≤ 900px); some sem "outros esportes" | `SportsBar` |
| `ItemSports` / `TitleSports` | coluna centralizada; ativo na cor do tema, inativo `#ccc`; rótulo 0.75em, máx. 15ch; hover escala 1.1 | `SportsBar` |
| `Content` | flex; altura `calc(100vh - 118px)` com esportes ou `- 60px` (≤ 900px: `- 175px` / `- 105px`) | `PublicLayout` |
| `Screen` | fundo `rgba(0,0,0,0.8)`, absoluto, z-index 25 | `Backdrop` |
| `Loading` / `Message` | carregamento centralizado; mensagem `#999`, 0.9em, margem 35px | `LoadingScreen`, `EmptyMessage` |

**Menu** (`screens/main/styles.js`)

| Antigo | Valores | Novo |
|---|---|---|
| `Menu` | fundo `#666`; 20% (desktop), rolagem com barra de 5px; ≤ 900px: absoluto, z-index 50, `left: -70%` → 0, largura 70%, 100vh | `SideMenu` |
| `Header` (do menu e do cupom) | fundo `#222`; padding 10px; texto `#fff` 13px; centralizado (desktop) ou `space-between` com X (≤ 900px) | `PanelHeader` |
| `MenuItem` / `MenuItemLink` | fundo `#333` (hover `#666`); grid `32px 65% auto`; padding 10px; título 13px `#fff` | `SideMenu` |
| `Country` | fundo `#111`; padding 10px; `#fff`; bandeira (`Flag`) 22px | `SideMenu` |
| `Championship` | padding 10px; fundo `#666`; `#fff` 13px; `Count` fundo `#222`, padding 3px 7px, 10px, peso 500 | `SideMenu` |

**Coluna central** (`screens/main/styles.js`)

| Antigo | Valores | Novo |
|---|---|---|
| `LinkSlide` / `ImageSlide` | carrossel automático 5s, loop, sem miniaturas, indicadores e status | `BannerCarousel` |
| `ContainerSearch` / `InputSearch` | fundo `#222`, campos `#444`; padding 7px 10px; dois campos lado a lado; input transparente, sem borda, `#fff`, 1em | `SearchBar` |
| `Filter` / `BtnFilter` | fundo `#000`; botão padding 7px, `#ccc`, 13px, peso 500, fundo `#222` (≤ 900px: padding 15px 7px, 11px) | `DateTabs` |
| `CheckTicket` | só ≤ 900px; padding 10px 7px; fundo `#222` (barra de resumo com "Conferir") | `BetSlipSummary` |
| `Footer` / `FooterIcons` / `FooterLinks` / `FooterCopy` | fundo `#333` (copyright `#222`); coluna centralizada `#fff`; ícones 28px em 200px; links em 280px; copyright 0.9em, padding `10px 5px 75px` | `Footer` |
| `Call` | fundo `#35cd96` (hover `#075e54`); absoluto, inferior esquerdo (15px/15px), 55×55px, padding 15px, redondo, z-index 25 | `WhatsAppButton` |

**Card de jogo** (`screens/main/components/matches/styles.js`)

| Antigo | Valores | Novo |
|---|---|---|
| `EventChampionships` | fundo `#222`; padding 10px; `#fff`; peso 500; `space-between` (≤ 900px: 12px) | `ChampionshipHeader` |
| `Item` | linha; padding 3px 5px; fundo `#f0f0f0` (≤ 900px: coluna, padding 5px 7px) | `MatchCard` |
| `Team` / `Shield` / `NameTeam` | padding 2px 0; escudo 22×22px; nome em negrito | `MatchCard` |
| `Time` | 70px; padding 0 5px; peso 500; cor do tema se faltar < 60 min, senão `#000` | `MatchCard` |
| `Placar` / `PeriodMatch` | fundo vermelho, raio 0.3em, padding 5px 15px, `#fff` 500; período 0.9em 500 | `MatchCard` |
| `Options` | 420px (≤ 900px: 100%, `space-between`) | `OddGroup` |
| `BtnOption` | 19.5%; altura 40px; borda 2px na cor do tema; raio 0.5em; 14px negrito; normal: fundo tema e texto `#fff`; selecionado: fundo transparente e texto tema; variação: pisca verde/vermelho 0.5s × 4 | `OddButton` |
| `Letter` | 20×40px; padding 12px 0; texto `#fff`; fundo na cor derivada | `OddButton` |
| `Lock` | ícone `lock` no lugar da cotação bloqueada | `OddButton` |
| `QtdeOptions` | 15%; 40px; 14px negrito; cor do tema quando o palpite é de outro mercado, senão `#000` | `OddGroup` |

**Cupom** (`screens/main/styles.js`)

| Antigo | Valores | Novo |
|---|---|---|
| `Ticket` | fundo `#444`; 20% (desktop); ≤ 900px: absoluto, z-index 250, topo 10%, 100% × 100vh, aparece com opacidade | `BetSlip` |
| `List` | fundo `#666`; rolagem 6px; ≤ 900px altura `calc(100vh - 337px)` | `BetSlip` |
| `Hunches` / `HunchesTop` / `HunchesBottom` | fundo `#333` (alternado `#444`); padding 10px / 5px 10px; times 13px 500 `#fff`/`#ccc`; mercado e cotação 12px | `BetSlipItem` |
| `InfoTicket` / `RowTicket` / `Label` | fundo `#000`; padding `10px 10px 2px`; linhas de dois campos; label raio 5px, padding 8px; ícone 18px; texto `#f1f1f1` | `BetSlipForm` |
| `BtnValue` | sem borda; altura 35px; 12px negrito; `#fff`; fundo na cor do tema | `QuickValues` |
| `LabelClear` / `TextClear` | 50%; fundo `#666`; altura 37px; 14px `#fff` | `BetSlipActions` |
| `LabelFinish` / `TextFinish` / `QtdeHunches` | fundo tema (verde ao validar); altura 37px; 14px `#fff`; selo 37×37px fundo `#666` | `BetSlipActions` |
| `PopUp` | fixo; 500px (≤ 900px: 90%); padding 10px; botões 30px raio 0.3em | `NotificationPopup` (fora do escopo) |

O inventário completo, com todos os componentes dos modais (`odd`, `success`, `ticket`,
`sports`, `table`), é fechado no plano e vira o checklist da validação manual (SC-001).

## R-08. Dados reais disponíveis no backend novo

| Necessidade da tela | Fonte | Observação |
|---|---|---|
| Jogos pré-jogo e ao vivo, agrupados por campeonato | `GET /api/publico/confrontos` (spec 003) | `tipo`, `dia` (hoje, amanha, depois_de_amanha), `busca`, `esporte`, `pagina` (até 100) |
| Países e campeonatos com quantidade de jogos | mesma resposta (`paises`) | Bandeira (`bandeira`) e escudos (`escudo_casa`, `escudo_fora`) já vêm |
| Cotações C/E/F/A e "+N" | `cotacoes.odd1..odd4`, `quantidade_cotacoes` | |
| Horário na cor do tema | `minutos_para_inicio` | < 60 → cor do tema |
| Detalhes do jogo ("+N") | `GET /api/publico/confrontos/{confronto}` e `.../confrontos-ao-vivo/{confronto_ao_vivo}` | |
| Código da aposta do visitante | `POST /api/publico/apostas` (spec 004) | Só pré-jogo; 10/min por IP; ao vivo → 422 |
| Bilhete pelo código | `GET /api/publico/apostas/{codigo}` | |
| Comissão sobre o prêmio ("vendedor paga") | `usuarios_configuracoes.comissao_por_premio` (spec 004) | Por vendedor; no sistema antigo era `travas_vendedores.comissao_por_premio`. Visitante sem vendedor = 0, campo oculto |
| Nome do sistema, mensagem do bilhete | `configuracoes.nome_sistema`, `mensagem_bilhete` | |
| Esportes permitidos, outros esportes, ao vivo | `visitantes_configuracoes` e `configuracoes.ao_vivo_habilitado` | |

No novo sistema, a rolagem infinita usa a paginação do backend (até 100 por página,
Constituição), e não a paginação no navegador do sistema antigo.

## R-09. Dados fake (Princípio IX)

| Dado fake | Formato previsto | Será substituído por |
|---|---|---|
| `temas`, `cor_fundo`, `letter` | textos de cor hexadecimal | Spec de configurações visuais |
| Logo, favicon, ícones do PWA | caminhos de imagem | Spec de configurações visuais |
| Telefone do WhatsApp | texto com DDI | Spec de configurações visuais |
| Redes sociais (Instagram, YouTube, Twitter, Facebook) | URLs | Spec de configurações visuais |
| Banners | lista de `{imagem, link}` | Spec de banners |
| Indicador de Acumuladão | booleano | Spec do Acumuladão |
| Indicador de Cassino | booleano | Spec do Cassino |
| Texto das regras | texto | Spec de regras |

Local único dos fakes: definido no plano. Nenhum fake participa do envio da aposta; jogos e
cotações nunca são fake.

## R-10. Atualização instantânea e cache (problema do sistema antigo)

**Como era** (`public/sw.js` do sistema antigo):

- O service worker respondia **do cache primeiro** a tudo que não fosse `/api/` ou `/upload/`,
  inclusive a página (`/`, `/index.php`) e o `app.js`, que não tinha hash no nome.
- Tudo que passava por ele ficava guardado para sempre; o cache só era trocado quando alguém
  mudava à mão o `cacheName` (`'5.1.114'`).
- Sem `skipWaiting`/`clients.claim`, a versão nova do service worker ficava esperando todas as
  abas e o PWA instalado fecharem. Resultado: usuários presos em versões antigas, dependentes de
  "Limpar cache" e de reinstalar.

**Decisão** (Constituição 2.1.0, Stack; FR-049 a FR-050c): o servidor decide a versão e o
frontend só obedece.

1. **Arquivos com hash**: o build gera nomes com hash (ex.: `app-3f9a1c.js`). Arquivo novo tem
   nome novo; nenhum arquivo antigo é reaproveitado por engano. Esses arquivos são servidos com
   cache longo e imutável.
2. **HTML nunca do cache com conexão**: a página é sempre pedida ao servidor (rede primeiro); o
   cache da página só é usado sem conexão, para abrir a estrutura.
3. **Versão pelo servidor**: o Inertia compara, a cada requisição, a versão do build que o
   navegador tem com a do servidor (hash do manifesto do build). Se forem diferentes, o servidor
   responde pedindo recarga completa e o navegador carrega a versão nova sozinho. Não existe
   número de versão mantido à mão.
4. **Voltar para a aba**: ao voltar para a aba (ou reabrir o PWA), a tela faz uma checagem leve
   da versão; se mudou, recarrega. Na aba "Ao vivo", as atualizações a cada 7s já fazem essa
   checagem.
5. **Service worker que se atualiza sozinho**: ao detectar uma versão nova, ativa na hora
   (sem esperar as abas fecharem) e apaga os caches antigos.
6. **Recarga segura**: a troca de versão espera um envio em andamento terminar; o cupom fica no
   aparelho e sobrevive à recarga (FR-050a).
7. **Configuração não é versão**: tema, banners, textos e contatos vêm do servidor nas props de
   cada página; mudar configuração não exige build nem deploy (FR-050c).

**Alternativas descartadas**: aviso "nova versão disponível, clique para atualizar" (deixa a
decisão com o usuário, que ignora o aviso); versão manual no service worker (o problema atual).

**Fora do escopo desta spec**: notificações push (o service worker antigo tratava push de gol e
notificações); entram em spec própria.

## R-11. Modo claro e escuro (dia/noite)

- **Decisão** (2026-10-09): botão de alternar dia/noite no cabeçalho (`ThemeToggle`), à esquerda
  de "Criar Conta" no desktop e de "Entrar" no mobile; ícone de sol (claro) e lua (escuro).
- **Modo escuro**: é o visual atual, idêntico ao sistema antigo (inventário R-07).
- **Modo claro**: novo. Troca os fundos e textos fixos (`#000`, `#111`, `#222`, `#333`, `#444`,
  `#666`, `#f0f0f0`, `#fff`, `#ccc`) por equivalentes claros e mantém a cor principal e a
  derivada. A paleta é proposta no plano e aprovada pelo responsável antes da implementação.
- **Modo inicial**: `cor_fundo` (`#000000` → escuro, `#FFFFFF` → claro). A escolha do visitante
  fica salva no aparelho (preferência pessoal, não é área nem dado de negócio); "Limpar cache"
  volta ao modo configurado.
- **Sem piscar**: o modo salvo é aplicado antes de a página aparecer, para não abrir no modo
  errado e trocar em seguida.
- **Ponto para o plano**: as cores fixas viram tokens do tema (ex.: fundo do cabeçalho, fundo do
  card, texto secundário), com um valor para cada modo; os componentes usam só os tokens.
