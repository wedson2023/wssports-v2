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
- Frontend (Constituição 3.1.0, Princípios I e III): componentes e styled-components em inglês e
  PascalCase (ex.: `OddButton`, `Container`), com a pasta do componente com o mesmo nome;
  variáveis, funções, props, chaves de objetos criados no frontend e constantes em snake_case e
  português (ex.: `adicionar_palpite`, `ao_clicar`, `chave_cupom`); hooks no padrão do React, em
  português (ex.: `useCupom`, arquivo `hooks/useCupom.js`);
  chaves vindas do backend usadas como chegam (ex.: `time_casa`); APIs de bibliotecas e do
  navegador com o nome original (`useState`, `localStorage`). Demais pastas em inglês e
  `snake_case`.

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

---

# Fase 0 do plano (decisões técnicas)

As seções R-12 a R-24 foram acrescentadas pelo `/speckit-plan` em 2026-10-09.

## R-12. Dependências novas (Stack: justificar no plano)

| Pacote | Versão | Motivo |
|---|---|---|
| `inertiajs/inertia-laravel` (composer) | ^2.0 | Páginas Inertia no servidor; versão do build por requisição (R-15) |
| `@inertiajs/react` | ^2.0 | Lado React do Inertia; `usePoll`, `router.reload` com `only` (recargas parciais) |
| `react`, `react-dom` | ^19 | Stack do frontend definida pelo responsável |
| `@vitejs/plugin-react` | ^5 | JSX e recarga rápida no Vite 7 |
| `styled-components` | ^6.1 | Stack definida; estilos em `styles.jsx` (Princípio III) |
| `vite-plugin-pwa` | ^1.0 | Service worker gerado no build com a lista de arquivos com hash (R-15) |
| `sweetalert2` | ^11 | Alertas e confirmações idênticos aos atuais (FR-047); o sistema antigo usa a mesma biblioteca |
| `@fontsource/roboto` | ^5 | Roboto 400/500 servida pelo próprio site (o antigo buscava no Google); entra no cache do PWA |
| `material-icons` | ^1.13 | Fonte Material Icons servida pelo próprio site (o antigo usava `/css/material.icon.css`) |

**Removidos** (vieram no esqueleto do Laravel e não são usados): `tailwindcss`, `@tailwindcss/vite`
e `resources/css/app.css`. **Mantidos**: `axios`, `laravel-vite-plugin`, `vite`, `concurrently`.

**Não usados** do sistema antigo: `react-router-dom` (o Inertia faz as rotas), `redux` (estado do
cupom em contexto), `moment` (`Intl.DateTimeFormat`), `react-responsive-carousel` e
`react-loader-spinner` (componentes próprios que reproduzem o visual, sem depender de bibliotecas
antigas sem suporte ao React 19).

## R-13. Fluxo de dados: Inertia para a página, API pública para as ações

- **Página (Inertia)**: `GET /` entrega como props a listagem (o mesmo serviço
  `ListagemConfrontos` da API, com `Publico::visitante()`), as configurações reais e os fakes.
  Trocar esporte, dia, busca e campeonato faz `router.reload` só da prop `listagem`, com os
  filtros na URL (`?esporte=BASQUETE&dia=amanha`), preservando o estado e a rolagem.
- **Rolagem infinita**: `router.reload({ only: ['listagem'], data: { pagina } })`; a página
  `Home` soma as páginas recebidas no próprio estado, juntando o mesmo campeonato que venha
  dividido entre duas páginas (por `id`); páginas de 50 (máximo 100, Constituição). O `merge` do
  Inertia não é usado porque repetiria o cabeçalho do campeonato dividido entre páginas.
- **Ao vivo**: `usePoll(7000, { only: ['listagem'] })`, ativo só com a aba "Ao vivo" aberta.
- **Ações (API pública existente, sem token, mesma origem)**: detalhe do "+N"
  (`GET /api/publico/confrontos/{id}` e `.../confrontos-ao-vivo/{id}`), código da aposta
  (`POST /api/publico/apostas`) e bilhete por código (`GET /api/publico/apostas/{codigo}`).
  Assim nenhuma regra de aposta é duplicada em rotas web.
- **Motivo**: a página e as listas passam pelo Inertia, que confere a versão a cada requisição
  (R-15); as ações reaproveitam rotas já validadas nas specs 003 e 004.
- **Alternativas descartadas**: tudo pela API JSON (perde a checagem de versão e a página pronta
  do servidor); ações também por rotas web (duplicaria `CriacaoApostas` e os limites de
  tentativas).

## R-14. Backend web (arquivos novos)

| Arquivo | Papel |
|---|---|
| `app/Http/Middleware/TratarRequisicoesInertia.php` | Middleware do Inertia: `rootView` `app`, `version()` (hash do manifesto do build), `share()` com tema, contatos, nome do sistema e versão; `Cache-Control: no-cache, private` nas respostas de página |
| `app/Http/Controllers/PaginaInicialController.php` | `GET /` → `Inertia::render('Home', …)` |
| `app/Http/Controllers/RegrasController.php` | `GET /regras` → `Inertia::render('Rules', …)` com o texto fake |
| `app/Http/Controllers/ArquivosPwaController.php` | `GET /sw.js` (service worker com `Service-Worker-Allowed: /`, sem cache) e `GET /manifest.webmanifest` (montado no servidor: nome real, ícones fake) |
| `app/Http/Requests/PaginaInicialRequest.php` | Filtros da página: estende `ListagemPublicaRequest` (mesmas regras e mesmo limite por IP) |
| `app/Fakes/DadosFake.php` | **Local único dos dados fake** (Princípio IX): `tema()`, `contatos()`, `banners()`, `indicadores()`, `regras()`, `icones()` |
| `public/fakes/` | Imagens fake: logo, ícones do PWA e banners |
| `resources/views/app.blade.php` | Página raiz do Inertia: `@vite`, `@inertiaHead`, viewport com zoom bloqueado, manifest e o script que aplica o modo salvo antes de desenhar (R-18) |

Nomes de classes do backend em português, como os demais controllers (Princípio VI).

## R-15. Atualização instantânea: implementação

1. **Build com hash**: o Vite já gera `public/build/assets/*-[hash].js|css`; nenhum arquivo do
   site é servido sem hash, exceto `sw.js`, o manifest e a página.
2. **Versão**: `TratarRequisicoesInertia::version()` devolve o hash do
   `public/build/manifest.json`. Toda requisição Inertia leva a versão do navegador; se for
   diferente, o servidor responde 409 e o Inertia faz a recarga completa sozinho.
3. **Voltar para a aba**: `useAtualizacaoVersao` escuta `visibilitychange` e, ao voltar, faz
   `router.reload({ only: ['versao'] })` (prop mínima); no ao vivo o `usePoll` já faz isso.
4. **Service worker** (`vite-plugin-pwa`, `generateSW`, `registerType: 'autoUpdate'`,
   `skipWaiting`, `clientsClaim`, `cleanupOutdatedCaches`):
   - pré-cache só dos arquivos do build com hash, das fontes, das imagens fixas e da página
     offline (`public/offline.html`);
   - navegação (página): `NetworkOnly`; sem conexão, o service worker responde a página offline
     estática pré-armazenada, sem dados, com a mensagem de erro de conexão. A página da tela
     nunca é guardada em cache, porque traz os jogos e as cotações embutidos (props do Inertia);
     assim nenhuma cotação antiga aparece, nem com internet lenta (FR-049, SC-006). Decisão do
     responsável (2026-10-09), no lugar do `NetworkFirst` com tempo limite;
   - `/api/*`, `/fakes/*` e escudos/bandeiras externos: `NetworkOnly` (nunca do cache);
   - o `sw.js` é servido pela rota `/sw.js`, sem cache, para valer no escopo `/`.
5. **Envio em andamento** (FR-050a): a recarga de versão acontece em requisições Inertia, que
   poderiam cortar o envio do código pela API. Por isso, enquanto `enviando` for verdadeiro, a
   checagem ao voltar para a aba (`useAtualizacaoVersao`) e a atualização do ao vivo (`usePoll`)
   ficam pausadas; terminado o envio, a checagem que ficou pendente roda na hora e o ao vivo
   volta. Decisão do responsável (2026-10-09). O cupom está no aparelho e sobrevive à recarga.
6. **Servidor de produção**: `/build/assets/*` com `Cache-Control: public, max-age=31536000,
   immutable`; página, `sw.js` e manifest com `no-cache` (registrado no `quickstart.md`).

## R-16. Cupom: estado, armazenamento e cálculo

- **Estado**: contexto React com `useReducer` (ações `alternar_palpite`, `remover_palpite`,
  `definir_valor`, `definir_nome`, `limpar`, `atualizar_cotacoes`), sempre imutável. O estado
  "selecionado" do botão é derivado do cupom; os objetos dos jogos nunca são alterados.
- **Onde fica**: o provedor do contexto fica no `PublicLayout`, layout persistente do Inertia,
  então o cupom não reinicia ao navegar entre `/` e `/regras`.
- **Armazenamento**: `localStorage`, chave `wssports.cupom`, com `versao_formato: 1`; leitura
  validada (formato inválido → cupom vazio, FR-039); escrita a cada mudança; leitura e escrita
  em `try/catch`.
- **Cálculo**: `utils/money.js` reproduz `CalculoPremio` com inteiros (`BigInt`):
  - valor em centavos × produto das cotações em centésimos, truncado em centavos;
  - o menor entre esse valor, valor × `multiplicador` e `premio_maximo`;
  - acréscimo de `ganho_multiplo_palpites`% com 3 ou mais palpites, limitado ao prêmio máximo;
  - "vendedor paga" = o mesmo valor do prêmio (retorno possível), sempre visível (FR-037a); o
    desconto virá da configuração `vendedor_paga`, a ser criada em outra spec.
  Com mais de 5 palpites o backend trunca o produto em 10 casas; diferenças de centavos são
  possíveis e o backend decide (estimativa).
- **Envio**: `POST /api/publico/apostas` com `chave_idempotencia` (`crypto.randomUUID()`, uma por
  tentativa), `nome`, `valor` em texto com 2 casas, `aceitar_alteracoes: "Nenhuma"` e os palpites
  com `confrontos_id` ou `confrontos_ao_vivo_id`, `codigo_cotacao` e `cotacao_vista`.
- **Respostas**:
  - 201 → modal de sucesso com o código e cupom limpo;
  - 409 (cotação alterada) → confirmação com a mensagem e o novo prêmio do backend; "Sim"
    atualiza as cotações do cupom e reenvia; "Não" mantém o cupom com as cotações novas;
  - 422 e 429 → alerta com a mensagem do backend; o cupom continua montado.

## R-17. Responsivo (decisões aprovadas)

- Um ponto de troca: `tema.telas.mobile = 900` (CSS `@media (max-width: 900px)` e o hook
  `useTelaMobile` com `matchMedia`, que reage a girar e redimensionar). As regras de 1024px do
  sistema antigo passam para 900px (FR-003).
- Colunas laterais: `width: 20%; min-width: 240px` (FR-003a).
- Altura: `100dvh`, com `100vh` de reserva para navegadores antigos (FR-003b).
- Espaço do WhatsApp: no mobile, a lista de jogos termina com 85px livres (botão de 55px + 15px
  de margem + 15px de folga) (FR-003c).
- Zoom bloqueado: viewport com `maximum-scale=1, user-scalable=no` (FR-003d).
- Barra de esportes no mobile: cada item com `calc((100vw - 20px) / 5)`, em CSS, sem mexer no
  DOM.

## R-18. Tema e modo claro/escuro: implementação

- **Tokens**: `resources/js/theme/` define as cores do tema (principal e derivada, vindas das
  props) e as cores fixas de cada modo (R-19). Os `styles.jsx` usam só tokens
  (`props.theme.superficie_titulo` etc.), nunca a cor escrita direto.
- **Provedor**: `ThemeProvider` do styled-components no `PublicLayout`, com o modo atual.
- **Modo salvo**: `localStorage` `wssports.modo` (`claro` ou `escuro`); sem escolha, vale
  `cor_fundo`. "Limpar cache" apaga a escolha.
- **Sem piscar**: um script curto no `app.blade.php` lê o modo salvo antes do React, marca
  `<html data-modo="…">` e a cor de fundo da página; o React lê esse valor na primeira
  renderização.

## R-19. Paleta do modo claro (aprovada pelo responsável em 2026-10-09)

O modo escuro usa exatamente os valores do sistema antigo. O modo claro troca só as cores fixas;
a cor principal e a derivada do tema ficam iguais.

| Token | Uso | Escuro (antigo) | Claro (proposta) |
|---|---|---|---|
| `fundo_pagina` | página, cabeçalho, filtros de data, formulário do cupom | `#000` | `#ffffff` |
| `superficie_pais` | linha do país no menu | `#111` | `#e6e6e6` |
| `superficie_titulo` | títulos de painel, cabeçalho do campeonato, busca, copyright | `#222` | `#eeeeee` |
| `superficie_barra` | barra de esportes, itens do menu, rodapé, palpite do cupom | `#333` | `#f5f5f5` |
| `superficie_cupom` | fundo do cupom, campos da busca, palpite alternado | `#444` | `#fafafa` |
| `superficie_campeonato` | linha do campeonato no menu, fundo das colunas, "Limpar" | `#666` | `#d9d9d9` |
| `superficie_jogo` | card do jogo | `#f0f0f0` | `#ffffff` |
| `texto_principal` | textos sobre superfícies escuras | `#fff` | `#222222` |
| `texto_secundario` | esporte inativo, filtros, nome do 2º time no cupom | `#ccc` | `#555555` |
| `texto_apagado` | mensagens vazias, versão | `#999` | `#777777` |
| `texto_jogo` | times e horário no card | `#000` | `#000000` |

Botões na cor do tema (odds, valor rápido, Finalizar, Criar Conta) mantêm o texto `#fff` nos dois
modos. Na implementação entrou o token `fundo_lista` (`#fff` nos dois modos), para as linhas
brancas da lista de jogos que se alternam com `superficie_jogo`. Os ajustes que o responsável pedir entram aqui antes da implementação (Princípio VIII).

## R-20. Linha da cotação e "vendedor paga" (decidido)

**Decisão do responsável (2026-10-09)**: a linha fica igual ao sistema antigo, com "cotação total"
e "vendedor paga" lado a lado (50% cada). O "vendedor paga" aparece sempre e mostra o mesmo valor
do prêmio. A configuração `vendedor_paga` não existe no backend novo (só `comissao_por_premio`,
por vendedor); o responsável cria a coluna em outra spec, que define o desconto. A proposta
anterior (cotação ocupando a linha inteira) foi descartada.

## R-21. Inventário visual dos modais e da tela de regras

| Antigo | Valores | Novo |
|---|---|---|
| `modals/odd` `Container` | fundo `#fff`; 40% × 450px, centralizado (topo 25%); ≤ 900px: 100% × 100vh, topo 20% | `MatchDetailsModal` |
| `modals/odd` `Header` / `TextMatch` | 45px; fundo `#222`; texto `#fff` | `MatchDetailsModal` |
| `modals/odd` `ButtonTabs` / `TextTabs` | padding 10px; ativa: fundo tema e texto `#fff`; inativa: fundo `#fff` e texto tema; 12px 500 | `MatchDetailsModal` |
| `modals/odd` `Category` / `Item` | categoria: padding 10px, fundo tema, texto `#fff`; item: padding 10px 15px, `space-between` | `MatchDetailsModal` |
| `modals/odd` `ValueOdd` | 80 × 40px; borda 2px tema; 14px negrito; selecionado transparente com texto tema | `OddButton` (variação larga) |
| `modals/success` `Container` | fundo `#fff`; 40%, padding 10px, topo 25%; ≤ 900px: 100% × 100vh | `SuccessModal` |
| `modals/success` `Title` / `Code` | 15px 500; código em `h1` negrito | `SuccessModal` |
| `modals/success` `Button` | padding 12px 5px; 500; `#fff`; código `#28b351`, link tema, fechar vermelho; opacidade 0.8 | `SuccessModal` |
| `modals/ticket` `Container` / `Header` | fundo `#fff`; 40%, topo 25%; cabeçalho 40px, padding 10px, fundo `#222` | `TicketModal` |
| `modals/ticket` `Row` / `Label` / `Status` | linha `space-between`, 0.9em, padding 3px 0; rótulo 500; situação: verde (Vencedor), vermelho (Perdedor), `#222` (demais), 0.7em, raio 0.3em | `TicketModal` |
| `modals/ticket` `ContainerHunches` | borda fina `#ccc`; margem 10px 0; padding 5px 10px; altura 290px com rolagem | `TicketModal` |
| `screens/rules` `Container` / `section` | padding 25px; fundo `#000`; seção branca de 90%, padding 25px; título "REGULAMENTO" e logo | `RulesContent` (página `Rules`) |
| Fundo dos modais (`Screen`) | `rgba(0,0,0,0.8)`, fixo, z-index 25 | `Backdrop` |
| Alertas (`helpers.js`) | sweetalert2: "Sucesso"/OK, "Atenção"/Entendi, "Confirme por favor" Sim/Não (confirmar na cor do tema, cancelar `#999`), erros "Erro!"/Entendi | `utils/alerts.js` |

## R-22. Carregamento, carrossel e ícones sem bibliotecas antigas

- `LoadingScreen` reproduz o "Rings" (100px, `#ccc`) com o texto "Carregando jogos."; o
  indicador do "Finalizar" reproduz o "TailSpin" (20px, `#fff`), em SVG próprio.
- `BannerCarousel` reproduz o `react-responsive-carousel` usado (troca automática a cada 5s, em
  loop, sem miniaturas, status nem indicadores; setas no mobile), com CSS próprio.
- Ícones pela fonte Material Icons (pacote `material-icons`), com os mesmos nomes do sistema
  antigo (`sports_soccer`, `live_tv`, `lock`, `delete`, `launch`, `menu`, `close` etc.); sol e lua
  do `ThemeToggle`: `light_mode` e `dark_mode`.

## R-23. Arquivos existentes alterados ou removidos (Princípio IV)

| Arquivo | Alteração | Motivo |
|---|---|---|
| `composer.json` / `composer.lock` | `inertiajs/inertia-laravel` | R-12 |
| `package.json` / `package-lock.json` | Dependências do R-12; remove o Tailwind | R-12 |
| `vite.config.js` | Plugins React e PWA; entrada `resources/js/app.jsx`; remove o Tailwind | R-12, R-15 |
| `resources/js/app.js` | Removido; substituído por `resources/js/app.jsx` (entrada do Inertia) | R-13 |
| `resources/css/app.css` | Removido (era só o Tailwind) | R-12 |
| `resources/views/welcome.blade.php` | Removido; substituído por `app.blade.php` | R-14 |
| `routes/web.php` | Troca a rota de boas-vindas por `/`, `/regras`, `/sw.js` e `/manifest.webmanifest` | R-14 |
| `bootstrap/app.php` | Adiciona `TratarRequisicoesInertia` ao grupo `web` | R-14 |

`resources/js/bootstrap.js` é mantido sem mudança (importado pelo `app.jsx`). Nenhum arquivo do
backend das specs 001 a 004 é alterado; os serviços são só usados.

## R-24. Nomes de pastas e arquivos do frontend

- Pastas comuns em inglês e `snake_case`: `pages`, `layouts`, `components`, `hooks`, `theme`,
  `utils`.
- Componentes (inclusive páginas e layouts) em pastas `PascalCase` com `index.jsx` e
  `styles.jsx` (Princípio III). Não há componente sem estilo próprio: o provedor do cupom fica
  dentro do `PublicLayout`.
- Hooks em arquivos com o nome do hook (`hooks/useCupom.js`); utilitários em arquivos com nome
  em inglês e `snake_case` (`utils/money.js`, `utils/storage.js`, `utils/alerts.js`,
  `utils/dates.js`, `utils/api.js`); funções e variáveis em `snake_case` português; hooks no
  padrão do React; props só de estilo nos styled-components com prefixo `$` (ex.:
  `$selecionado`) (Constituição 3.2.0).

## R-25. Filtro por campeonato no menu (alteração de código da spec 003, autorizada em 2026-10-09)

- **Problema**: clicar num campeonato do menu filtra a lista (FR-018). No sistema antigo o filtro
  era feito no navegador sobre todos os jogos já carregados. No sistema novo a listagem é paginada
  (Constituição), então filtrar no navegador mostraria só os jogos das páginas já carregadas.
- **Proposta**: acrescentar o filtro opcional `campeonato` (id) em `ListagemPublicaRequest` e em
  `ListagemConfrontos` (pré-jogo e ao vivo): `where campeonatos_id = ?`, sem mudar o restante.
  Como muda uma rota da API, a mesma entrega atualiza o contrato da spec 003 e regenera a coleção
  do Postman (Constituição, Fluxo).
- **Alternativa**: filtro só no navegador sobre as páginas carregadas (incompleto; não
  recomendado).
