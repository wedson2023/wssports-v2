---

description: "Lista de tarefas da feature Tela principal de apostas esportivas (área /)"
---

# Tasks: Tela principal de apostas esportivas (área `/`)

**Input**: Documentos de design em `specs/005-tela-principal/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md),
[data-model.md](data-model.md), [contracts/paginas.md](contracts/paginas.md),
[quickstart.md](quickstart.md)

**Tests**: NÃO há tarefas de teste: a constituição proíbe testes automatizados. Cada user story é
validada manualmente pelos cenários do [quickstart.md](quickstart.md).

**Organization**: tarefas agrupadas por user story, na ordem de prioridade da spec (US1, US2, US3
são P1; US4 a US8 são P2; US9 é P3).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: pode rodar em paralelo (arquivos diferentes, sem dependências pendentes)
- **[Story]**: user story da tarefa (US1 a US9)
- Caminhos relativos à raiz do repositório

## Regras que valem para TODAS as tarefas

- **Sistema antigo (Princípios VII e VIII)**: `C:\Users\wedso\OneDrive\Área de Trabalho\projetos\wssports.bet`
  é **somente leitura**. Cada componente novo copia medidas, cores, espaçamentos, textos e
  breakpoints do componente antigo indicado (inventário em [research.md](research.md), R-07 e
  R-21), trocando cores fixas pelos tokens do tema (R-19) e `900px` pelo token `telas.mobile`.
  Diferenças só as aprovadas: FR-003a a FR-003c, botão dia/noite, modo claro.
- **Nomes (Princípio I, Constituição 3.2.0)**:
  - backend: classes em português `PascalCase`; métodos, variáveis e chaves em `snake_case`;
    métodos exigidos pelo framework ou pelo Inertia mantêm o nome (`handle`, `version`, `share`,
    `rules`, `messages`, `prepareForValidation`, `failedValidation`);
  - frontend: componentes e styled-components em inglês `PascalCase` (exigência do React);
    variáveis, funções, props, chaves de objetos criados no frontend e constantes em
    `snake_case` português (`alternar_palpite`, `ao_clicar`, `chave_cupom`); hooks no padrão do
    React, em português (`useCupom`, arquivo `hooks/useCupom.js`); chaves vindas do backend como
    chegam (`time_casa`); APIs de bibliotecas e do navegador com o nome original (`useState`,
    `router.reload`, `localStorage`). Fora os hooks, nenhum nome criado pelo projeto em
    `camelCase`;
  - props passadas a styled-components só para o estilo usam o prefixo `$` + snake_case
    português (ex.: o `OddButton` recebe `selecionado` e repassa `$selecionado` ao styled);
  - arquivos de utilitários em inglês snake_case (`utils/money.js`, `utils/storage.js`,
    `utils/alerts.js`, `utils/dates.js`, `utils/api.js`); hooks em `hooks/useXxx.js`.
- **Componentes (Princípio III)**: cada componente, página e layout em pasta própria
  (`PascalCase`) com `index.jsx` (lógica e marcação) e `styles.jsx` (styled-components). Nenhum
  componente em arquivo solto.
- **Idioma (Princípio II)**: comentários, textos e mensagens em português.
- **Escopo (Princípio IV)**: arquivos existentes marcados com ⚠️ estão na tabela do
  [plan.md](plan.md) e foram confirmados pelo responsável em 2026-10-09. Nenhum outro arquivo
  existente pode ser alterado.
- **Legibilidade (Princípio V)**: ao concluir cada tarefa, revisar em conjunto o código alterado e
  informar se houve ou não refatoração.
- **Fakes (Princípio IX)**: dados fake só em `app/Fakes/DadosFake.php` e `public/fakes/`. Jogos e
  cotações nunca são fake. Nenhum fake vai no envio da aposta.
- **Dinheiro**: nunca `float` no cálculo; centavos inteiros e `BigInt` (`utils/money.js`).
- **Armazenamento**: todo acesso a `localStorage` passa por `utils/storage.js` (com
  `try/catch`).
- **Cores**: nos `styles.jsx`, só tokens do tema (`props.theme.*`); nenhuma cor fixa escrita
  direto, exceto as que o sistema antigo usa iguais nos dois modos e que o R-19 não lista (ex.:
  verde `#28b351` do botão de código, `rgba(0,0,0,0.8)` do fundo dos modais).

---

## Phase 1: Setup (infraestrutura compartilhada)

**Purpose**: dependências, build e arquivos estáticos.

- [X] T001 ⚠️ Instalar `inertiajs/inertia-laravel` ^2.0 com `composer require inertiajs/inertia-laravel:^2.0` (altera `composer.json` e `composer.lock`)
- [X] T002 ⚠️ Atualizar `package.json` e `package-lock.json` (R-12): `npm install react@^19 react-dom@^19 @inertiajs/react@^2 styled-components@^6.1 sweetalert2@^11 @fontsource/roboto@^5 material-icons@^1.13` e `npm install -D @vitejs/plugin-react@^5 vite-plugin-pwa@^1`; remover `tailwindcss` e `@tailwindcss/vite` com `npm uninstall`
- [X] T003 ⚠️ Reescrever `vite.config.js` (R-12, R-15): `laravel({ input: ['resources/js/app.jsx'], refresh: true })`, `react()`, sem Tailwind, e `VitePWA` com `strategies: 'generateSW'`, `registerType: 'autoUpdate'`, `injectRegister: false`, `manifest: false` (o manifest vem do servidor), `filename: 'sw.js'`, `workbox: { globPatterns: ['**/*.{js,css,woff,woff2,png,svg,ico}'], navigateFallback: null, skipWaiting: true, clientsClaim: true, cleanupOutdatedCaches: true, additionalManifestEntries: [{ url: '/offline.html', revision: <md5 do arquivo, calculado no próprio vite.config.js com node:crypto> }], runtimeCaching: [navegação (`request.mode === 'navigate'`) com `NetworkOnly` e `options: { precacheFallback: { fallbackURL: '/offline.html' } }`; `/api/`, `/fakes/` e imagens de outros domínios com `NetworkOnly`] }` (nenhuma página da tela em cache, R-15); manter o `server.watch.ignored` atual. Criar também `public/offline.html`: página estática sem dados e sem dependências externas (estilo embutido), fundo `#000`, Roboto/sans-serif 14px, texto `#fff`, com o título "Sem conexão" e a mensagem "Não foi possível conectar. Verifique sua internet e tente novamente." e um botão "Tentar novamente" na cor `#c40808` que recarrega a página (`location.reload()`)
- [X] T004 ⚠️ Remover `resources/js/app.js`, `resources/css/app.css` e `resources/views/welcome.blade.php` (R-23); manter `resources/js/bootstrap.js` sem mudança
- [X] T005 [P] Copiar do sistema antigo (somente leitura) para `public/images/` as imagens fixas usadas pela tela: `dollar.png`, `trofeu.png`, `cotacao.png`, `vendedor_paga.png`, `instagran.png`, `youtube.png`, `twitter.png`, `facebook.png`, `gordon_moody.png`, `whatsapp.png`, `banceira_categoria_especial.png` (de `wssports.bet/public/images/`); conferir em `wssports.bet/resources/js/screens/main/styles.js` (`FooterLogo`, `FooterImage`, `Call`) se usam outras imagens e copiá-las também
- [X] T006 [P] Criar as imagens fake em `public/fakes/` (Princípio IX): `logo.png` (cópia de `wssports.bet/public/upload/images/logo/logo.png`), `icone.png` (cópia de `.../logo/icon.png`), `icones/icon-32.png` a `icones/icon-512.png` e os 4 `touch-icon-*.png` (cópias de `wssports.bet/public/upload/images/icons/`) e `banners/1.png` e `banners/2.png` (imagens de banner de exemplo, 1200 × 300px)

**Checkpoint**: `npm run build` conclui e gera `public/build/sw.js`.

---

## Phase 2: Foundational (pré-requisitos de todas as stories)

**Purpose**: Inertia funcionando, fakes, tema, utilitários e layout base.

**⚠️ CRITICAL**: nenhuma user story começa antes desta fase.

### Backend

- [X] T007 Criar `app/Fakes/DadosFake.php` (classe `App\Fakes\DadosFake`, local único dos fakes, Princípio IX), com comentário em cada método dizendo que é fake e qual spec o substitui (R-09). Métodos estáticos que devolvem arrays no formato do [data-model.md](data-model.md): `tema()` → `temas` `'#c40808'`, `letter` derivada pela tabela do R-04 (`#c40808`→`#a41f1a`, `#d0af01`→`#9f8601`, `#008000`→`#005400`, `#006eb1`→`#024b77`, `#fe6a00`→`#b94e02`, `#b91552`→`#930137`), `cor_fundo` `'#000000'`, `logo` `'/fakes/logo.png'`; `contatos()` → `whatsapp` (texto só com dígitos, com DDI), `mensagem_whatsapp` `'Olá! Vim pelo site e gostaria de fazer uma aposta esportiva. Pode me ajudar?'`, `instagram`, `youtube`, `twitter`, `facebook` (URL ou `null`), `jogo_responsavel` `'https://www.gamblingtherapy.org/pt-br/'`; `indicadores()` → `acumuladao` `true`, `cassino` `true`; `banners()` → lista de `['imagem' => '/fakes/banners/1.png', 'link' => null]`; `regras()` → lista de parágrafos em texto simples; `icones()` → lista de `['src', 'sizes', 'type']` com os tamanhos 32, 64, 96, 128, 168, 192, 256 e 512
- [X] T008 Criar `app/Http/Middleware/TratarRequisicoesInertia.php` (estende `Inertia\Middleware`): `$rootView = 'app'`; `version()` devolve `md5_file(public_path('build/manifest.json'))` quando o arquivo existe (senão `parent::version()`); `share()` devolve as props compartilhadas do [contracts/paginas.md](contracts/paginas.md): `versao` (a mesma versão), `nome_sistema` (`Configuracoes::atual()->nome_sistema`), `tema`, `contatos` e `indicadores` (de `DadosFake`); `handle()` chama o pai e, na resposta de página (não JSON), acrescenta `Cache-Control: no-cache, private`
- [X] T009 ⚠️ Em `bootstrap/app.php`, dentro de `withMiddleware`, acrescentar `$middleware->web(append: [TratarRequisicoesInertia::class]);` com o `use` correspondente; não mudar o restante
- [X] T010 Criar `resources/views/app.blade.php` (raiz do Inertia, R-14 e R-18): `lang="pt-br"`; `<meta charset="utf-8">`; `<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">` (FR-003d); `<meta name="robots" content="noindex">`; `<link rel="manifest" href="/manifest.webmanifest">`; `<link rel="icon" href="/fakes/icone.png">`; `apple-touch-icon` com os `touch-icon-*` de `public/fakes/icones/`; `<meta name="apple-mobile-web-app-capable" content="yes">`; um `<script>` curto, antes de tudo, que lê `localStorage['wssports.modo']` em `try/catch` e marca `document.documentElement.dataset.modo` (`claro`/`escuro`; sem valor salvo, não marca) e a cor de fundo do `<html>` (`#ffffff` ou `#000000`); `@viteReactRefresh`, `@vite('resources/js/app.jsx')`, `@inertiaHead` e `@inertia`
- [X] T011 Criar `app/Http/Controllers/ArquivosPwaController.php` com `service_worker()`: devolve `public/build/sw.js` (404 se não existir) com `Content-Type: application/javascript`, `Service-Worker-Allowed: /` e `Cache-Control: no-cache`; e `manifest()`: devolve o JSON do [contracts/paginas.md](contracts/paginas.md) (`name` e `short_name` = `nome_sistema` de `Configuracoes::atual()`, `display` `standalone`, `start_url`/`scope`/`id` `/`, `background_color` e `theme_color` = `cor_fundo` de `DadosFake::tema()`, `icons` = `DadosFake::icones()`) com `Content-Type: application/manifest+json` e `Cache-Control: no-cache`
- [X] T012 ⚠️ Em `routes/web.php`, trocar a rota de boas-vindas por `Route::get('sw.js', [ArquivosPwaController::class, 'service_worker'])` e `Route::get('manifest.webmanifest', [ArquivosPwaController::class, 'manifest'])` (as rotas `/` e `/regras` entram em T030 e T087)

### Frontend: tema, estilo global e utilitários

- [X] T013 [P] Criar `resources/js/theme/tokens.js`: `telas = { mobile: 900 }`; `cores_derivadas` (mapa cor principal → `letter` do R-04); `paletas = { escuro: {…}, claro: {…} }` com os 11 tokens e valores exatos do R-19 (`fundo_pagina`, `superficie_pais`, `superficie_titulo`, `superficie_barra`, `superficie_cupom`, `superficie_campeonato`, `superficie_jogo`, `texto_principal`, `texto_secundario`, `texto_apagado`, `texto_jogo`); função `montar_tema(tema, modo)` que devolve `{ principal: tema.temas, derivada: tema.letter, modo, telas, ...paletas[modo] }` e o helper `mobile` (`@media (max-width: 900px)`) para os `styles.jsx`
- [X] T014 [P] Criar `resources/js/components/GlobalStyle/index.jsx` e `styles.jsx`: `createGlobalStyle` igual a `wssports.bet/resources/js/styles/index.js` (reset de margin/padding/outline, `box-sizing`, `ul` sem marcador, `font: 14px 'Roboto', sans-serif`, antialiased), com `html, body, #app` em `height: 100vh; height: 100dvh;` (FR-003b), sem rolagem na página, fundo `fundo_pagina` e cor do texto do token; importar `@fontsource/roboto/400.css`, `@fontsource/roboto/500.css` e `material-icons/iconfont/material-icons.css` no `index.jsx`
- [X] T015 [P] Criar `resources/js/utils/storage.js`: `chave_cupom = 'wssports.cupom'`, `chave_modo = 'wssports.modo'`; `ler_json(chave)` (devolve `null` se ausente, inválido ou sem acesso), `gravar_json(chave, valor)`, `ler_texto`, `gravar_texto`, `remover(chave)` e `limpar_dados_locais()` (remove as duas chaves); tudo em `try/catch`, sem lançar erro
- [X] T016 [P] Criar `resources/js/utils/alerts.js` com `sweetalert2`, copiando títulos, textos de botão e cores de `wssports.bet/resources/js/utils/helpers.js` (linhas 561 a 610: `swal_success`, `swal_warning`, `swal_ask`, `message`): `alerta_sucesso(texto)` ("Sucesso", "OK"), `alerta_atencao(texto)` ("Atenção", "Entendi"), `confirmar(texto, cor_confirmar)` ("Confirme por favor", "Sim"/"Não", cancelar `#999`, devolve `true`/`false`), `alerta_erro(erro)` (lê `erro.response.data.message`; 400/401 → "Atenção!" + warning; demais → "Erro!" + error; sem resposta → mensagem de erro de conexão) e importar o CSS do sweetalert2
- [X] T017 [P] Criar `resources/js/utils/api.js`: instância do axios com `baseURL: '/api'` e `Accept: application/json`, sem token (só rotas públicas)
- [X] T018 [P] Criar `resources/js/utils/dates.js` com `Intl.DateTimeFormat` no fuso `America/Sao_Paulo` (`-03:00`): `formatar_hora(data_inicio)` → `"19:30"`; `formatar_dia_mes(data_inicio)` → `"08/10"`; `nome_dia_depois_de_amanha()` → nome do dia da semana de hoje + 2, com os mesmos textos do objeto `week` de `wssports.bet/resources/js/screens/main/index.js` (linha 145)
- [X] T019 [P] Criar `resources/js/hooks/useTelaMobile.js`: `useTelaMobile()` com `window.matchMedia('(max-width: 900px)')` (valor de `telas.mobile`), atualizado no evento `change` (girar a tela e redimensionar, FR-003)
- [X] T020 [P] Criar `resources/js/hooks/useModo.js`: `useModo(cor_fundo)` devolve `{ modo, alternar_modo }`; modo inicial = `document.documentElement.dataset.modo` ou, sem ele, `cor_fundo === '#FFFFFF'` → `claro`, senão `escuro` (FR-055); `alternar_modo` grava em `chave_modo` (via `storage.js`), atualiza `dataset.modo` e a cor de fundo do `<html>` sem recarregar (FR-056)
- [X] T021 [P] Criar `resources/js/hooks/useAtualizacaoVersao.js`: `useAtualizacaoVersao(enviando = false)`; no `visibilitychange` para `visible`, chama `router.reload({ only: ['versao'] })` (R-15, FR-050); enquanto `enviando` for verdadeiro, não chama e marca a checagem como pendente; quando `enviando` volta a falso com checagem pendente, chama na hora (FR-050a); remove o listener ao desmontar

### Frontend: componentes base, layout e entrada

- [X] T022 [P] Criar `resources/js/components/Backdrop/` (`index.jsx`, `styles.jsx`): fundo `rgba(0, 0, 0, 0.8)` fixo, 100% × 100dvh, `z-index` recebido por prop (padrão 25), com transição de opacidade de `Screen` (`main/styles.js`); `onClick` fecha
- [X] T023 [P] Criar `resources/js/components/Spinner/` (`index.jsx`, `styles.jsx`): SVG próprio que reproduz o "TailSpin" do `react-loader-spinner` (20px, cor por prop, padrão `#fff`), usado no "Finalizar" (R-22)
- [X] T024 [P] Criar `resources/js/components/LoadingScreen/` (`index.jsx`, `styles.jsx`): reproduz o `Loading` de `main/styles.js` com o "Rings" (100px, `#ccc`) em SVG próprio e o texto "Carregando jogos." em `#999` (FR-029, R-22)
- [X] T025 [P] Criar `resources/js/components/EmptyMessage/` (`index.jsx`, `styles.jsx`): `Message` de `main/styles.js` (bloco, margem 35px auto, `texto_apagado`, 0.9em), texto por prop
- [X] T026 Criar `resources/js/layouts/PublicLayout/` (`index.jsx`, `styles.jsx`): `ThemeProvider` com `montar_tema(props.tema, modo)` (props compartilhadas via `usePage().props`, `useModo(tema.cor_fundo)`), `GlobalStyle`, `useAtualizacaoVersao()` e um `Container` (`Container` de `main/styles.js`: altura total) que recebe `children`; o layout cresce nas stories seguintes (cabeçalho, barra de esportes, cupom, WhatsApp)
- [X] T027 Criar `resources/js/app.jsx`: `import './bootstrap'`; `createInertiaApp` com `resolve` por `import.meta.glob('./pages/*/index.jsx', { eager: true })`, aplicando `PublicLayout` como layout persistente padrão (`page.default.layout ??= (pagina) => <PublicLayout>{pagina}</PublicLayout>`); `setup` com `createRoot`; `title` = `nome_sistema`; `progress: false`; registrar o service worker com `registerSW({ immediate: true })` de `virtual:pwa-register`, apontando para `/sw.js` com escopo `/` (R-15)

**Checkpoint**: `npm run build` e `php artisan serve` sobem; `/sw.js` e `/manifest.webmanifest` respondem com os cabeçalhos do contrato.

---

## Phase 3: User Story 1 - Visitante vê os jogos do dia na tela principal (Priority: P1) 🎯 MVP

**Goal**: `/` mostra a tela principal igual à atual (desktop e mobile) com os jogos reais de futebol de hoje, rolagem infinita, carregamento e lista vazia.

**Independent Test**: [quickstart.md](quickstart.md) seções 1, 2 e 3 (sem o cupom, que vem na US2).

### Backend

- [X] T028 [US1] Criar `app/Http/Requests/PaginaInicialRequest.php` estendendo `App\Http\Requests\ListagemPublicaRequest` (mesmas regras, mensagens e limite por IP): sobrescrever `failedValidation()` para não interromper (filtro inválido volta ao padrão, [contracts/paginas.md](contracts/paginas.md)) e criar `filtros(): array` que devolve só os campos válidos (`$this->getValidatorInstance()->valid()`) com `por_pagina` fixo em 50
- [X] T029 [US1] Criar `app/Http/Controllers/PaginaInicialController.php` com `index(PaginaInicialRequest $request, ListagemConfrontos $listagem)`: `Publico::visitante()`; `tipo` `ao_vivo` → `$listagem->ao_vivo(...)`, senão `pre_jogo(...)`; `Inertia::render('Home', ['filtros' => …, 'listagem' => …, 'configuracoes' => …, 'banners' => DadosFake::banners()])` no formato do contrato; `configuracoes` com `mensagem_bilhete` e `ao_vivo_habilitado` de `Configuracoes::atual()` e `esportes_permitidos`, `apostar_outros_esportes`, `ao_vivo_habilitado`, `multiplicador`, `premio_maximo`, `ganho_multiplo_palpites`, `valor_minimo_aposta`, `valor_maximo_aposta`, `quantidade_minima_opcoes`, `quantidade_maxima_opcoes` de `VisitantesConfiguracoes::atual()` (valores decimais como texto de 2 casas) e `comissao_por_premio` `'0.00'`
- [X] T030 [US1] ⚠️ Em `routes/web.php`, acrescentar `Route::get('/', [PaginaInicialController::class, 'index'])->name('pagina_inicial');`

### Frontend: cabeçalho, barra de esportes, menu e colunas

- [X] T031 [P] [US1] Criar `resources/js/components/Header/` (`index.jsx`, `styles.jsx`) a partir de `Nav`, `Logo`, `BtnRegister`, `BtnEnter` de `main/styles.js`: logo (`tema.logo`, 50px) à esquerda; ícone `menu` (40px, cor do tema) só no mobile, chamando `ao_abrir_menu`; "Criar Conta" (fundo do tema) e "Entrar" (contorno do tema), 13px, padding 7px, raio 0.3em, sem ação (FR-008); no mobile só "Entrar" (FR-007); deixar um espaço para o `ThemeToggle` (entra na US8)
- [X] T032 [P] [US1] Criar `resources/js/components/SportsBar/` (`index.jsx`, `styles.jsx`) a partir de `ContainerSports`, `SlideSports`, `ItemSports`, `IconSports`, `TitleSports` de `main/styles.js` e do JSX das linhas 1709 a 1817 de `main/index.js`: lista fixa, nesta ordem, com ícone e rótulo iguais aos atuais (Cassino `casino`, Futebol `sports_soccer`, Ao vivo `live_tv`, Basquete `sports_basketball`, Lutas `sports_mma`, Especiais `sort`, Vôlei `sports_volleyball`, Tênis, Tênis de mesa, E-sports, Futebol americano, Rugby, Hoquei no gelo, Handebol, Baisebol, com os ícones do arquivo antigo); ativo na cor do tema (sublinhado no mobile); no mobile, rolagem horizontal com cada item em `calc((100vw - 20px) / 5)` (R-17); props `esporte_ativo` e `ao_escolher` (a visibilidade por configuração entra na US4)
- [X] T033 [P] [US1] Criar `resources/js/components/PanelHeader/` (`index.jsx`, `styles.jsx`) a partir de `Header` e `Title` de `main/styles.js`: fundo `superficie_titulo`, padding 10px, título 13px centralizado no desktop; no mobile, `space-between` com o ícone `close` na cor do tema (`ao_fechar`)
- [X] T034 [P] [US1] Criar `resources/js/components/SideMenu/` (`index.jsx`, `styles.jsx`) a partir de `Menu`, `MenuItem`, `MenuItemLink`, `MenuTitle`, `Country`, `Flag`, `Championship`, `Count` de `main/styles.js` e do JSX das linhas 1845 a 1990 de `main/index.js`: `PanelHeader` "Menu"; itens fixos "Acumuladão" (só com `indicadores.acumuladao`), "Limpar cache" e "Regras" (ações na US9); depois `listagem.paises` com a linha do país (bandeira `https://api.oddbrasil.com/flags/{bandeira}.png`, ou `/images/banceira_categoria_especial.png` sem bandeira; nome em maiúsculas) e as linhas dos campeonatos com `quantidade_confrontos` no selo; prop `ao_escolher_campeonato` (filtro na US4); sem Impressão e Largura (FR-014); desktop: coluna `width: 20%; min-width: 240px` (FR-003a)
- [X] T035 [P] [US1] Criar `resources/js/components/BannerCarousel/` (`index.jsx`, `styles.jsx`) reproduzindo o `Carousel` de `main/index.js` (linhas 1993 a 2045) e o CSS do `react-responsive-carousel`: troca automática a cada 5s, em loop, sem miniaturas, status nem indicadores, setas no mobile; imagem de largura total; clique abre `link` em nova aba quando houver (FR-019)
- [X] T036 [P] [US1] Criar `resources/js/components/SearchBar/` (`index.jsx`, `styles.jsx`) a partir de `ContainerSearch` e `InputSearch` de `main/styles.js` (linhas 2058 a 2075 de `main/index.js`): dois campos lado a lado, ícone `search` e placeholder "Digite o nome do time.", ícone `receipt` (conferir no antigo) e placeholder "Digite o código aqui."; props `ao_buscar_time(texto)` (Enter), `ao_limpar_busca()` (texto vazio) e `ao_buscar_codigo(codigo)` (Enter)
- [X] T037 [P] [US1] Criar `resources/js/components/DateTabs/` (`index.jsx`, `styles.jsx`) a partir de `Filter` e `BtnFilter` de `main/styles.js` (linhas 2077 a 2100 de `main/index.js`): "Hoje", "Amanhã" e `nome_dia_depois_de_amanha()`; props `dia_ativo` e `ao_escolher('hoje' | 'amanha' | 'depois_de_amanha')`

### Frontend: lista de jogos

- [X] T038 [P] [US1] Criar `resources/js/components/OddButton/` (`index.jsx`, `styles.jsx`) a partir de `BtnOption`, `Letter`, `Odd`, `Lock` de `matches/styles.js`: 19.5% × 40px, borda 2px do tema, raio 0.5em, 14px negrito; selo da letra (C, E, F, A) 20 × 40px com fundo na cor `derivada`; estados `normal` (fundo do tema, texto `#fff`), `selecionado` (fundo transparente, texto do tema), `bloqueado` (ícone `lock`, sem clique, quando a cotação é `0` ou ausente); props `letra`, `cotacao`, `selecionado`, `variacao` (`'subiu' | 'desceu' | null`, animação na US6), `ao_clicar`; variação larga (80 × 40px, sem letra) para o modal do "+N" (US5)
- [X] T039 [P] [US1] Criar `resources/js/components/OddGroup/` (`index.jsx`, `styles.jsx`) a partir de `Options` e `QtdeOptions` de `matches/styles.js`: 420px (100% no mobile) com os 4 `OddButton` (`odd1` C, `odd2` E, `odd3` F, `odd4` A) e o "+N" (`+{quantidade_cotacoes}`, 15%, 40px, 14px negrito, `#000`; cor do tema quando `palpite_de_outro_mercado`); props `confronto`, `codigo_selecionado`, `ao_escolher(codigo, cotacao)`, `ao_abrir_detalhes`
- [X] T040 [P] [US1] Criar `resources/js/components/ChampionshipHeader/` (`index.jsx`, `styles.jsx`) a partir de `EventChampionships`, `TextChampionships`, `TextDateMatch` de `matches/styles.js`: fundo `superficie_titulo`, padding 10px, 500, nome à esquerda e `formatar_dia_mes` à direita (12px no mobile)
- [X] T041 [US1] Criar `resources/js/components/MatchCard/` (`index.jsx`, `styles.jsx`) a partir de `Item`, `Match`, `Teams`, `Team`, `Shield`, `NameTeam`, `Time` de `matches/styles.js` e do JSX de `matches/index.js`: fundo `superficie_jogo`, escudos 22 × 22px (espaço mantido se a imagem falhar), nomes em negrito, relógio `access_time` + `formatar_hora(data_inicio)` (70px; cor do tema quando `minutos_para_inicio < 60`, senão `texto_jogo`) e o `OddGroup`; no mobile, coluna com os times empilhados, horário à direita e o `OddGroup` embaixo (FR-022); espaço para placar e período do ao vivo (US6) (depende de T039)
- [X] T042 [US1] Criar `resources/js/components/MatchList/` (`index.jsx`, `styles.jsx`) a partir de `Matches` de `main/styles.js`: rolagem própria (barra de 6px no desktop); para cada campeonato, `ChampionshipHeader` e os `MatchCard`; `EmptyMessage` quando não houver jogos (FR-028); chama `ao_chegar_perto_do_fim` quando faltarem 300px para o fim (comportamento de `handleScroll`); no mobile, 85px livres no fim da lista (FR-003c, R-17) (depende de T040 e T041)
- [X] T043 [US1] Criar `resources/js/components/Footer/` (`index.jsx`, `styles.jsx`) a partir de `Footer`, `FooterLogo`, `FooterText`, `FooterImage`, `FooterIcons`, `FooterIcon`, `FooterLinks`, `FooterLink`, `FooterCopy` de `main/styles.js` e do JSX das linhas 2120 a 2140 de `main/index.js`: imagem de jogo responsável (abre `contatos.jogo_responsavel`); ícones de redes sociais só com URL; "Regras" (link para `/regras`); copyright `"{ano} © {NOME_SISTEMA}. Todos os direitos reservados."` e `"V.: {versao}"` (FR-046, FR-050b); fica no fim da `MatchList`
- [X] T044 [US1] Criar `resources/js/pages/Home/` (`index.jsx`, `styles.jsx`): monta, no `Content` de `main/styles.js` (flex, altura `calc(100dvh - 118px)`, ou `- 60px` sem a barra de esportes; no mobile `- 175px` / `- 105px`), o `SideMenu` (coluna no desktop), a coluna central (`BannerCarousel`, `SearchBar`, `DateTabs`, `MatchList`) e o espaço da coluna "Cupom" (entra na US2); guarda em estado os campeonatos acumulados: ao receber a página 1 substitui, nas seguintes soma, juntando o campeonato com o mesmo `id` que venha dividido entre páginas (R-13); `ao_chegar_perto_do_fim` chama `router.reload({ only: ['listagem'], data: { ...filtros, pagina: atual + 1 }, preserveState: true, preserveScroll: true })` enquanto `meta.pagina_atual < meta.ultima_pagina`; mostra `LoadingScreen` até a primeira lista
- [X] T045 [US1] Em `resources/js/layouts/PublicLayout/index.jsx` e `styles.jsx`, acrescentar o `Header` e a `SportsBar` acima do conteúdo (a barra some quando só o futebol é permitido, regra completa na US4)

**Checkpoint**: `/` com os jogos reais de hoje, igual ao sistema antigo no modo escuro (sem o cupom).

---

## Phase 4: User Story 2 - Visitante monta o cupom (Priority: P1)

**Goal**: escolher cotações (um palpite por jogo), valor e nome; totais recalculados em centavos; cupom salvo no aparelho; Limpar; Conferir no mobile.

**Independent Test**: [quickstart.md](quickstart.md) seção 4.

- [X] T046 [P] [US2] Criar `resources/js/utils/money.js` (R-16): `para_centavos(texto)` (aceita vírgula ou ponto; vazio, inválido ou negativo → 0), `centavos_para_texto(centavos)` (`1000` → `"10.00"`), `formatar_real(centavos)` (`"10,00"` com `toLocaleString('pt-BR', { minimumFractionDigits: 2 })`), `cotacao_total(cotacoes)` (produto, arredondado em 2 casas, meio para cima, como `CalculoPremio::arredondar`) e `calcular_premio({ valor_centavos, cotacoes, multiplicador, premio_maximo, ganho_multiplo_palpites })` com `BigInt`, espelhando `app/Services/CalculoPremio.php`: prêmio = menor entre (valor × produto das cotações em centésimos, truncado em centavos), valor × multiplicador e prêmio máximo; acréscimo de `ganho_multiplo_palpites`% com 3 ou mais palpites, truncado, limitado à folga até o prêmio máximo; devolve `{ cotacao_total, premio_centavos, acrescimo_centavos, total_centavos }`; nunca usar `float` no cálculo
- [X] T047 [P] [US2] Criar `resources/js/hooks/useCupom.js` (R-16, [data-model.md](data-model.md) seção 2): `CupomContext`; `cupom_reducer` imutável com `alternar_palpite` (sem palpite no jogo → adiciona; mesmo `codigo_cotacao` → remove; outro → troca; jogo identificado por `tipo` + `confronto_id`; no máximo 50 palpites), `remover_palpite`, `definir_valor` (substitui o valor), `definir_nome` (até 100 caracteres), `atualizar_cotacoes` e `limpar`; `useEstadoCupom()` (usado pelo provedor) que lê `chave_cupom` uma vez (formato com `versao_formato: 1`, `nome` texto, `valor_centavos` inteiro ≥ 0, `palpites` lista válida; qualquer outra coisa → cupom vazio, FR-039), grava a cada mudança e não altera o cupom restaurado; `useCupom()` para os componentes (estado, ações e os valores derivados de `calcular_premio`, com `vendedor_paga_centavos` = `total_centavos`, FR-037a)
- [X] T048 [US2] Em `resources/js/layouts/PublicLayout/index.jsx`, envolver o conteúdo com `CupomContext.Provider` usando `useEstadoCupom()`, para o cupom persistir entre `/` e `/regras` (R-16), e passar o `enviando` do cupom para `useAtualizacaoVersao(enviando)` (FR-050a) (depende de T047)
- [X] T049 [US2] Em `resources/js/components/MatchCard/index.jsx` e `resources/js/components/OddGroup/index.jsx`, ligar ao `useCupom()`: `codigo_selecionado` derivado do palpite do jogo e `ao_escolher` chamando `alternar_palpite` com `tipo`, `confronto_id`, `codigo_cotacao`, `mercado` ("Casa", "Empate", "Fora", "Ambas"), `cotacao` (texto com 2 casas), `time_casa`, `time_fora`, `campeonato`, `data_inicio`; os objetos da listagem nunca são alterados
- [X] T050 [P] [US2] Criar `resources/js/components/BetSlipItem/` (`index.jsx`, `styles.jsx`) a partir de `Hunches`, `HunchesTop`, `HunchesMatches`, `HunchesBottom`, `HunchesOption`, `HunchesOdd` de `main/styles.js` (linhas 2172 a 2190 de `main/index.js`): fundo `superficie_barra` (alternado `superficie_cupom`), times (13 caracteres, 500), ícone `delete` (remover), ícone `launch` ("mais opções", na US5), mercado e cotação
- [X] T051 [P] [US2] Criar `resources/js/components/QuickValues/` (`index.jsx`, `styles.jsx`) a partir de `BtnValue` de `main/styles.js`: botões 2, 3, 5, 10, 20 e 50 (35px, 12px negrito, fundo do tema, `title` igual ao antigo) chamando `definir_valor` com o valor em centavos
- [X] T052 [P] [US2] Criar `resources/js/components/BetSlipForm/` (`index.jsx`, `styles.jsx`) a partir de `InfoTicket`, `RowTicket`, `Label`, `TextValue`, `IconTicket`, `Input` de `main/styles.js` (linhas 2192 a 2256 de `main/index.js`): fundo `fundo_pagina`; "Nome apostador" (ícone `person`); linha valor (`/images/dollar.png`, campo numérico) | retorno (`/images/trofeu.png`, `formatar_real(total_centavos)`); linha cotação total (`/images/cotacao.png`) | "vendedor paga" (`/images/vendedor_paga.png`, mesmo valor do prêmio, sempre visível, FR-037a); `QuickValues` abaixo
- [X] T053 [P] [US2] Criar `resources/js/components/BetSlipActions/` (`index.jsx`, `styles.jsx`) a partir de `LabelClear`, `TextClear`, `LabelFinish`, `TextFinish`, `QtdeHunches` de `main/styles.js`: "Limpar" (ícone `clear_all`, 50%, `superficie_campeonato`, 37px) com `confirmar(...)` antes de `limpar()` (FR-038); "Finalizar" (fundo do tema, 37px) com o selo da quantidade de palpites (37 × 37px) e o `Spinner` quando `enviando`; prop `ao_finalizar` (envio na US3)
- [X] T054 [US2] Criar `resources/js/components/BetSlip/` (`index.jsx`, `styles.jsx`) a partir de `Ticket` e `List` de `main/styles.js`: `PanelHeader` "Cupom"; lista de `BetSlipItem` com rolagem própria (mobile `calc(100dvh - 337px)`) ou `EmptyMessage` "Nenhum jogo selecionado"; `BetSlipForm` e `BetSlipActions`; desktop: coluna `width: 20%; min-width: 240px` (FR-003a); mobile: painel absoluto (z-index 250, topo 10%, 100% × 100dvh) aberto e fechado por prop, com transição de opacidade como no antigo (FR-036) (depende de T050 a T053)
- [X] T055 [P] [US2] Criar `resources/js/components/BetSlipSummary/` (`index.jsx`, `styles.jsx`) a partir de `CheckTicket` de `main/styles.js` (linhas 1820 a 1840 de `main/index.js`): só no mobile; fundo `superficie_titulo`, padding 10px 7px; valor, retorno e "Conferir" (cor do tema) com o selo da quantidade de palpites; `ao_conferir` abre o cupom
- [X] T056 [US2] Em `resources/js/pages/Home/index.jsx`, colocar o `BetSlip` na coluna da direita (desktop) e, no mobile, a `BetSlipSummary` abaixo da barra de esportes abrindo o `BetSlip` sobre a tela (FR-036) (depende de T054 e T055)

**Checkpoint**: cupom completo, com totais certos, salvo ao recarregar e com Limpar.

---

## Phase 5: User Story 3 - Visitante gera o código da aposta (Priority: P1)

**Goal**: "Finalizar" gera o pré-bilhete pela API pública e mostra o código; recusas mostram a mensagem do backend.

**Independent Test**: [quickstart.md](quickstart.md) seção 5.

- [X] T057 [P] [US3] Criar `resources/js/components/SuccessModal/` (`index.jsx`, `styles.jsx`) a partir de `wssports.bet/resources/js/modals/success` (inventário R-21): `Backdrop`; caixa branca 40% (100% × 100dvh no mobile), topo 25%; título (15px 500), código em `h1` negrito, `mensagem_bilhete` e os botões do antigo que fazem sentido sem login (copiar código em `#28b351` e fechar em vermelho; opacidade 0.8)
- [X] T058 [US3] Em `resources/js/hooks/useCupom.js`, criar `enviar_cupom()` (R-16, [data-model.md](data-model.md) seção 3): bloqueia novo envio enquanto houver um em andamento (`enviando`); `POST /publico/apostas` via `utils/api.js` com `chave_idempotencia` (`crypto.randomUUID()` por tentativa), `nome`, `valor` (`centavos_para_texto`), `aceitar_alteracoes: 'Nenhuma'` e `palpites` (`confrontos_id` ou `confrontos_ao_vivo_id` conforme `tipo`, `codigo_cotacao`, `confrontos_jogadores_id` só com `jogador`, `cotacao_vista`); 201 → devolve o comprovante e chama `limpar()`; 409 → `confirmar(...)` com a `message` e o `total_a_pagar` devolvidos; "Sim" aplica `atualizar_cotacoes(alteracoes)` (`cotacao_atual`) e reenvia; "Não" aplica as cotações e mantém o cupom; 422, 429 e erro de conexão → `alerta_erro` e o cupom continua (FR-041 a FR-043, FR-033a). `enviando` fica verdadeiro do início ao fim do envio, inclusive durante a confirmação do 409 e o reenvio; é o sinal que pausa a checagem de versão (T021) e o ao vivo (T071), para nenhuma recarga cortar o envio (FR-050a, R-15)
- [X] T059 [US3] Em `resources/js/components/BetSlipActions/index.jsx` e `resources/js/components/BetSlip/index.jsx`, ligar "Finalizar" ao `enviar_cupom()` e abrir o `SuccessModal` com o comprovante devolvido (depende de T057 e T058)

**Checkpoint**: MVP (US1 + US2 + US3) completo: o visitante vê os jogos, monta o cupom e gera o código.

---

## Phase 6: User Story 4 - Visitante troca de esporte, de dia e filtra (Priority: P2)

**Goal**: barra de esportes com as regras de visibilidade, abas de data, filtro por campeonato (parâmetro novo na API) e busca por time.

**Independent Test**: [quickstart.md](quickstart.md) seção 6.

### Backend: filtro por campeonato (R-25, autorizado)

- [X] T060 [US4] ⚠️ Em `app/Http/Requests/ListagemPublicaRequest.php`, acrescentar a regra `'campeonato' => ['nullable', 'integer', 'min:1']` e a mensagem `'campeonato.integer' => 'O campeonato deve ser um número inteiro.'` (e `campeonato.min` com o mesmo texto); não mudar o restante
- [X] T061 [US4] ⚠️ Em `app/Services/ListagemConfrontos.php`, nos filtros comuns do pré-jogo e do ao vivo (onde ficam `busca`, `esporte` e `somente_favoritos`), acrescentar `when(filled($filtros['campeonato'] ?? null), …)` com `where` em `campeonatos_id` (`co.` no pré-jogo, `av.` no ao vivo) usando o valor como parâmetro (nunca concatenado); a lista de `paises` continua sobre o resultado filtrado, como já é; não mudar o restante
- [X] T062 [US4] ⚠️ Atualizar `specs/003-confrontos/contracts/api.md` (tabela de parâmetros de `GET /api/publico/confrontos`: linha `campeonato`, "id do campeonato", sem padrão, com nota "spec 005, R-25") e regenerar `docs/postman/wssports_api.postman_collection.json` substituindo o arquivo, com todas as rotas atuais, as variáveis `base_url` e `token` e o parâmetro `campeonato` (desativado) na requisição da listagem pública (Constituição, Fluxo)

### Frontend

- [X] T063 [US4] Em `resources/js/components/SportsBar/index.jsx`, aplicar a visibilidade (FR-010): "Ao vivo" só com `configuracoes.ao_vivo_habilitado`; esportes além do futebol só com `apostar_outros_esportes` e se estiverem em `esportes_permitidos`; "Cassino" só com `indicadores.cassino`; "Cassino" e "Especiais" sem ação (FR-011); a barra inteira some quando só o futebol é permitido (prop para o `PublicLayout` ajustar a altura do `Content`)
- [X] T064 [US4] Em `resources/js/pages/Home/index.jsx`, criar `aplicar_filtros(novos)`: `router.reload({ only: ['listagem', 'filtros'], data: { ...filtros, ...novos, pagina: 1 }, preserveState: true, replace: true })`, voltando a lista acumulada para a página 1 e rolando para o topo; ligar `SportsBar` (`esporte`, `tipo` `pre_jogo`), `DateTabs` (`dia`), `SideMenu` (`campeonato`; no mobile fecha a gaveta) e `SearchBar` (`busca` no Enter; texto vazio limpa `busca`) (FR-018, FR-025 a FR-027)
- [X] T065 [US4] Em `resources/js/app.jsx`, tratar respostas que não são do Inertia (evento `invalid` do `router`): com corpo JSON que tenha `message` (ex.: 429 "Muitas requisições. Tente novamente em N segundos."), `preventDefault()` e `alerta_erro`, mantendo a lista atual na tela

**Checkpoint**: navegação completa por esporte, dia, campeonato e busca.

---

## Phase 7: User Story 5 - Visitante vê mais cotações de um jogo (Priority: P2)

**Goal**: "+N" abre o modal com todos os mercados; escolher uma cotação entra no cupom.

**Independent Test**: [quickstart.md](quickstart.md) seção 7, item 1.

- [X] T066 [US5] Criar `resources/js/components/MatchDetailsModal/` (`index.jsx`, `styles.jsx`) a partir de `wssports.bet/resources/js/modals/odd` (inventário R-21): `Backdrop`; caixa branca 40% × 450px (100% × 100dvh no mobile, topo 20%); cabeçalho 45px `superficie_titulo` com os times e `close`; carrega `GET /publico/confrontos/{id}` (ou `/publico/confrontos-ao-vivo/{id}` no ao vivo) via `utils/api.js`, com indicador de carregamento; abas e categorias como no antigo, com as `cotacoes` (`mercado`, `cotacao`) e os `jogadores` (`nome`, `tipo`, `cotacao`); cada cotação é um `OddButton` largo ligado a `alternar_palpite` (`codigo_cotacao` = `codigo_cotacao`, ou `jogador` com `jogador_id` = `confrontos_jogadores_id`), aparecendo selecionado; 404 → `alerta_erro` e fecha (FR-030, FR-031)
- [X] T067 [US5] Em `resources/js/pages/Home/index.jsx`, `resources/js/components/OddGroup/index.jsx` e `resources/js/components/BetSlipItem/index.jsx`, abrir o `MatchDetailsModal` pelo "+N" e pelo ícone `launch` do palpite (FR-040); `palpite_de_outro_mercado` = palpite do jogo com código fora de `odd1`…`odd4` (FR-032) (depende de T066)

**Checkpoint**: todos os mercados de um jogo apostáveis pelo "+N".

---

## Phase 8: User Story 6 - Visitante acompanha o ao vivo (Priority: P2)

**Goal**: aba "Ao vivo" com placar, período e minuto, atualização a cada 7s só com a aba aberta e botões piscando na variação.

**Independent Test**: [quickstart.md](quickstart.md) seção 7, item 2.

- [X] T068 [P] [US6] Criar `resources/js/hooks/useVariacaoCotacoes.js`: guarda em `useRef` as cotações anteriores de cada jogo (`id` + código) e, a cada nova listagem, devolve `variacoes[id][codigo]` = `'subiu'` | `'desceu'` | `null`, limpando o valor depois de 2s (4 × 0,5s); não grava nada no aparelho ([data-model.md](data-model.md) seção 1)
- [X] T069 [US6] Em `resources/js/components/OddButton/styles.jsx`, criar as animações `green` e `red` de `matches/styles.js` (`keyframes`, 0,5s, 4 repetições) aplicadas por `variacao` (FR-023)
- [X] T070 [US6] Em `resources/js/components/MatchCard/index.jsx` e `styles.jsx`, no ao vivo mostrar o placar (`Placar`: fundo vermelho, raio 0.3em, padding 5px 15px, `#fff` 500) e o período (`PeriodMatch`/`TextPeriod`: ícone `access_time` e `"{situacao} - {minuto} minuto(s)"`) no lugar do horário, como em `matches/index.js`
- [X] T071 [US6] Em `resources/js/pages/Home/index.jsx`, com `filtros.tipo === 'ao_vivo'`, ativar `usePoll(7000, { only: ['listagem'] }, { autoStart: false })` e controlar com `start()`/`stop()`: ligado só no ao vivo e com `enviando` do `useCupom()` falso; ao terminar o envio, chama `router.reload({ only: ['listagem'] })` na hora e volta a ligar (FR-033, FR-050a); passar as `variacoes` do `useVariacaoCotacoes` aos `MatchCard`; "Ao vivo" na `SportsBar` aplica `tipo: 'ao_vivo'` e `esporte: 'FUTEBOL'`; se a resposta indicar ao vivo indisponível (403 "O ao vivo não está disponível." ou `ao_vivo_habilitado` falso), voltar para `tipo: 'pre_jogo'`, `esporte: 'FUTEBOL'` e mostrar a mensagem com `alerta_atencao` (depende de T068)
- [X] T072 [US6] Em `app/Http/Controllers/PaginaInicialController.php`, com `tipo=ao_vivo` e o ao vivo indisponível para o visitante, responder a página com `tipo` `pre_jogo` e a prop `aviso` com a mensagem (`"O ao vivo não está disponível."`), em vez de erro; registrar a prop `aviso` (texto ou nulo) em [contracts/paginas.md](contracts/paginas.md)

**Checkpoint**: ao vivo funcionando, sem atualizações com a aba fechada.

---

## Phase 9: User Story 7 - Visitante confere um bilhete pelo código (Priority: P2)

**Goal**: Enter no campo de código abre o comprovante.

**Independent Test**: [quickstart.md](quickstart.md) seção 7, item 3.

- [X] T073 [US7] Criar `resources/js/components/TicketModal/` (`index.jsx`, `styles.jsx`) a partir de `wssports.bet/resources/js/modals/ticket` (inventário R-21): `Backdrop`; caixa branca 40% (100% no mobile, z-index 150), topo 25%; cabeçalho 40px `superficie_titulo` com o título e `close`; linhas (rótulo 500, 0.9em) com `codigo`, `nome`, `criada_em`, `valor`, `cotacao_total`, `total_a_pagar` e `situacao`/`resultado` (selo verde "Vencedor", vermelho "Perdedor", `#222` nos demais); lista de `palpites` (campeonato, times, data, `mercado`, `cotacao`, cancelado com opacidade 0.2) em caixa de 290px com rolagem; `mensagem_bilhete` ([data-model.md](data-model.md) seção 4)
- [X] T074 [US7] Em `resources/js/pages/Home/index.jsx`, ligar `ao_buscar_codigo`: `GET /publico/apostas/{codigo}` via `utils/api.js` (código sem espaços, em maiúsculas); 200 → abre o `TicketModal`; 404 → `alerta_atencao` com a mensagem do backend; 429 e erro de conexão → `alerta_erro` (FR-044) (depende de T073)

**Checkpoint**: bilhete consultado pelo código.

---

## Phase 10: User Story 8 - Tema de cores (Priority: P2)

**Goal**: cores do tema em todos os elementos, nos dois modos, e o botão dia/noite.

**Independent Test**: [quickstart.md](quickstart.md) seção 8.

- [X] T075 [P] [US8] Criar `resources/js/components/ThemeToggle/` (`index.jsx`, `styles.jsx`): botão com o ícone `light_mode` (modo claro) ou `dark_mode` (modo escuro), na cor do tema, do mesmo tamanho e alinhamento dos ícones do `Header`; `ao_clicar` = `alternar_modo` (FR-053, R-22)
- [X] T076 [US8] Em `resources/js/components/Header/index.jsx` e `styles.jsx`, colocar o `ThemeToggle` à esquerda de "Criar Conta" no desktop e à esquerda de "Entrar" no mobile, acrescentando a coluna do grid sem mudar o restante do cabeçalho (FR-053) (depende de T075)
- [X] T077 [US8] Em `resources/js/layouts/PublicLayout/index.jsx`, passar `alternar_modo` e `modo` do `useModo` ao `Header`; conferir que a troca é imediata, sem recarregar (FR-056)
- [X] T078 [US8] Revisar todos os `styles.jsx` em `resources/js/components/`, `resources/js/pages/` e `resources/js/layouts/`: nenhuma cor fixa que deveria ser token (R-19); `principal` em todos os elementos que usam `temas.value` no antigo (busca por `temas.value` e `color={` em `main/index.js`, `matches/index.js` e nos modais) e `derivada` no selo da letra (FR-005)

**Checkpoint**: 6 cores e 2 modos conferidos.

---

## Phase 11: User Story 9 - Menu, links e instalação como aplicativo (Priority: P3)

**Goal**: gaveta de menu no mobile, Acumuladão sem ação, Limpar cache, Regras, WhatsApp e PWA instalável.

**Independent Test**: [quickstart.md](quickstart.md) seções 9 e 10.

- [X] T079 [US9] Em `resources/js/components/SideMenu/index.jsx` e `styles.jsx`, no mobile transformar o menu em gaveta: `position: absolute`, `z-index: 50`, `left: -70%` (fechado) → `0` (aberto), largura 70%, altura 100dvh, com a transição do antigo e o `Backdrop` atrás; fecha pelo X, pelo fundo e ao escolher campeonato (FR-013)
- [X] T080 [US9] Em `resources/js/layouts/PublicLayout/index.jsx`, controlar a abertura da gaveta (`menu_aberto`) pelo ícone de menu do `Header` (depende de T079)
- [X] T081 [US9] Em `resources/js/components/SideMenu/index.jsx`, ações dos itens: "Acumuladão" sem ação (FR-015); "Limpar cache" com `confirmar(...)` e, confirmado, `limpar_dados_locais()` e `window.location.reload()` (cupom e modo voltam ao padrão, FR-016); "Regras" com `<Link href="/regras">` do Inertia (FR-017)
- [X] T082 [P] [US9] Criar `resources/js/components/WhatsAppButton/` (`index.jsx`, `styles.jsx`) a partir de `Call` de `main/styles.js` (linhas 2405 a 2410 de `main/index.js`): fixo no canto inferior esquerdo (15px/15px), 55 × 55px, padding 15px, redondo, fundo `#35cd96` (hover `#075e54`), z-index 25, imagem `/images/whatsapp.png`; abre `https://api.whatsapp.com/send?phone={whatsapp}&text={mensagem_whatsapp codificada}`; não aparece sem telefone (FR-045)
- [X] T083 [US9] Em `resources/js/layouts/PublicLayout/index.jsx`, acrescentar o `WhatsAppButton` (depende de T082)
- [X] T084 [P] [US9] Criar `app/Http/Controllers/RegrasController.php` com `index()`: `Inertia::render('Rules', ['regras' => DadosFake::regras()])`
- [X] T085 [P] [US9] Criar `resources/js/components/RulesContent/` (`index.jsx`, `styles.jsx`) a partir de `wssports.bet/resources/js/screens/rules/styles.js` (inventário R-21): padding 25px, fundo `fundo_pagina`, rolagem própria (barra de 7px no desktop), título "REGULAMENTO", logo (`tema.logo`) e seção branca de 90% com padding 25px; parágrafos em texto simples (sem HTML)
- [X] T086 [US9] Criar `resources/js/pages/Rules/` (`index.jsx`, `styles.jsx`) com o `RulesContent` e o ícone de voltar para `/` (conferir no antigo como a tela de regras volta) (depende de T085)
- [X] T087 [US9] ⚠️ Em `routes/web.php`, acrescentar `Route::get('regras', [RegrasController::class, 'index'])->name('regras');` (depende de T084)

**Checkpoint**: menu, links, regras e PWA instalável.

---

## Phase 12: Polish & Cross-Cutting Concerns

**Purpose**: conferência de fidelidade, desempenho e revisão final.

- [ ] T088 Conferir lado a lado com o sistema antigo cada linha do inventário visual ([research.md](research.md), R-07 e R-21) nas larguras do SC-001 (360, 414, 768, 900, 901, 1024, 1366, 1920px), no modo escuro, e corrigir os `styles.jsx` com diferença não aprovada; repetir no modo claro com a paleta do R-19
- [X] T089 Rodar `npm run build` e conferir o JavaScript inicial comprimido ≤ 250 KB (plan.md, Performance Goals); se passar do limite, carregar sob demanda (`React.lazy`) os modais (`MatchDetailsModal`, `TicketModal`, `SuccessModal`) e o `sweetalert2`
- [ ] T090 Conferir a atualização instantânea e o cache seguindo o [quickstart.md](quickstart.md) seção 9 (dois builds seguidos, troca de esporte, volta para a aba, PWA reaberto, Cache Storage só com arquivos com hash, modo offline sem cotações antigas)
- [X] T091 Revisão de legibilidade de todo o código da feature (Princípio V): `app/Fakes`, controllers, middleware, request, `resources/js/**`; nomes (Princípio I, exceção do frontend), duplicação, componentes longos, comentários desatualizados; informar se houve refatoração
- [ ] T092 Executar o [quickstart.md](quickstart.md) completo (seções 1 a 10) e registrar o resultado

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sem dependências.
- **Foundational (Phase 2)**: depende do Setup; BLOQUEIA todas as stories.
- **US1 (Phase 3)**: depende da fase 2. Base visual de todas as outras stories.
- **US2 (Phase 4)**: depende da US1 (cards e botões de cotação).
- **US3 (Phase 5)**: depende da US2 (cupom).
- **US4 a US8 (Phases 6 a 10)**: dependem da US1; US5 e US6 também da US2 (palpites). Entre si são independentes.
- **US9 (Phase 11)**: depende da US1.
- **Polish (Phase 12)**: depois das stories desejadas.

### User Story Dependencies

| Story | Depende de | Pode rodar em paralelo com |
|---|---|---|
| US1 (P1) | Fase 2 | — |
| US2 (P1) | US1 | US4, US8, US9 |
| US3 (P1) | US2 | US4 a US9 |
| US4 (P2) | US1 | US2, US3, US5 a US9 |
| US5 (P2) | US2 | US4, US6 a US9 |
| US6 (P2) | US2 | US4, US5, US7 a US9 |
| US7 (P2) | US1 | US2 a US6, US8, US9 |
| US8 (P2) | US1 | US2 a US7, US9 |
| US9 (P3) | US1 | US2 a US8 |

### Within Each User Story

- Backend (request, controller, rota) antes do frontend que o consome.
- Componentes folha ([P]) antes dos que os compõem (`MatchCard` → `MatchList` → `Home`).
- Tarefas que alteram o mesmo arquivo rodam em sequência (ex.: `routes/web.php`: T012 → T030 → T087; `pages/Home/index.jsx`: T044 → T056 → T064 → T067 → T071 → T074).

### Parallel Opportunities

- Setup: T005 e T006.
- Fase 2: T013 a T021 entre si; T022 a T025 entre si.
- US1: T031 a T040 entre si (cada um em sua pasta).
- US2: T046 e T047; T050 a T053 e T055.
- US8: T075 em paralelo com qualquer tarefa de outra story.
- US9: T082, T084 e T085.

---

## Parallel Example: User Story 1

```text
Em paralelo (pastas diferentes):
T031 Header  •  T032 SportsBar  •  T033 PanelHeader  •  T034 SideMenu  •  T035 BannerCarousel
T036 SearchBar  •  T037 DateTabs  •  T038 OddButton  •  T039 OddGroup  •  T040 ChampionshipHeader

Depois, em sequência:
T041 MatchCard → T042 MatchList → T043 Footer → T044 Home → T045 PublicLayout
```

## Parallel Example: User Story 2

```text
Em paralelo: T046 money.js  •  T047 useCupom.js
Em paralelo: T050 BetSlipItem  •  T051 QuickValues  •  T052 BetSlipForm  •  T053 BetSlipActions  •  T055 BetSlipSummary
Depois: T048 → T049 → T054 → T056
```

---

## Implementation Strategy

### MVP (US1 + US2 + US3)

1. Fases 1 e 2 (setup e fundação).
2. US1: tela com os jogos reais, igual ao antigo.
3. US2: cupom.
4. US3: código da aposta.
5. **Parar e validar** pelo quickstart (seções 1 a 5): o visitante já aposta pela tela nova.

### Entrega incremental

1. MVP → validar → demonstrar.
2. US4 (navegação e filtro por campeonato, com Postman) → validar.
3. US5 (+N) e US6 (ao vivo) → validar.
4. US7 (bilhete) e US8 (tema dia/noite) → validar.
5. US9 (menu, regras, WhatsApp, PWA) → validar.
6. Polish (fidelidade em todas as larguras, tamanho do build, cache, revisão final).

---

## Notes

- [P] = arquivos diferentes, sem dependência pendente.
- ⚠️ = arquivo existente confirmado pelo responsável (plan.md); alterar só o trecho indicado.
- Sem testes automatizados; validação pelo [quickstart.md](quickstart.md).
- Ao concluir cada tarefa: revisão de legibilidade (Princípio V).
- Commits só quando o responsável rodar `/speckit-git-commit`.

## Notas da implementação (2026-10-09)

Ajustes feitos durante a implementação, para o código e os documentos dizerem a mesma coisa:

- **T027**: o service worker é registrado direto com `navigator.serviceWorker.register('/sw.js',
  { scope: '/' })` (só no build de produção), no lugar do `virtual:pwa-register`, que registraria
  em `/build/sw.js` com escopo `/build/`. No `vite.config.js`, `inlineWorkboxRuntime` e
  `modifyURLPrefix: { '': '/build/' }` fazem o `sw.js` funcionar servido na raiz.
- **T045 e T083**: cabeçalho, barra de esportes, barra de resumo e botão do WhatsApp ficam na página
  `Home`, não no `PublicLayout`, porque dependem das props da tela e não aparecem na tela de regras
  (como no sistema antigo). O layout guarda o tema, o modo (`ModoContext`) e o cupom
  (`CupomContext`).
- **T049**: `MatchCard` e `OddGroup` recebem a seleção e as ações por props (a `Home` liga ao
  `useCupom`), para continuarem reutilizáveis pelas áreas `/app` e `/cassino` (FR-006).
- **T052 e T055**: o hook `useCampoValor` (novo) liga o texto do campo valor ao valor em centavos
  do cupom, usado no cupom e na barra de resumo do mobile.
- **T061**: o filtro `campeonato` vale só para os jogos da página; a lista de `paises` do menu
  continua com todos os campeonatos (senão o menu mostraria só o campeonato escolhido).
- **T066**: as abas (Favoritas, 90 min, 1º Tempo, 2º Tempo) e as categorias do "+N" vêm do mapa fixo
  do sistema antigo (`HomeController`, array `$cotacao`), copiado para `utils/market_groups.js`; o
  nome de cada mercado vem da API. Jogadores aparecem na aba 90 min, como "TIPO A MARCAR GOL".
- **T081**: "Limpar cache" também apaga o Cache Storage do aplicativo, como o sistema antigo fazia.
- **Menu "Ao vivo"**: sem a barra de esportes (só futebol permitido), o item "Ao vivo" aparece no
  menu, como no sistema antigo.
- **Abas de data**: quantas aparecem depende do `periodo_jogos` do visitante (prop nova); a aba
  escolhida não muda de cor, como no sistema antigo.
- **Tema**: token `fundo_lista` (`#fff` nos dois modos) para as linhas brancas da lista de jogos,
  que se alternam com `superficie_jogo`.
- **Footer**: "Quem somos", "Afiliados" (sem tela no sistema novo, sem ação) e "Termos e condições"
  (abre `/regras`), como no sistema antigo.
- **Utilitário `share.js`**: compartilhamento comum ao modal de sucesso e ao modal do bilhete.
- **Validado**: `npm run build` (JS inicial 192 KB comprimido, meta ≤ 250 KB); `/`, `/regras`,
  `/sw.js` e `/manifest.webmanifest` com os cabeçalhos do contrato; recarga parcial com filtro por
  campeonato; versão antiga → 409; ao vivo; envio do código pela API (código gerado, prêmio igual ao
  calculado no cupom). T088, T090 e T092 são conferências no navegador, feitas pelo responsável.

### Ajustes do responsável (2026-10-09, depois da implementação)

- **Escudos** (`MatchCard`): endereço montado a partir do número do escudo, como no sistema antigo.
- **Alertas** (`GlobalStyle`, `utils/alerts.js`): 80% da largura no mobile; só a primeira mensagem
  de validação, sem "(and N more errors)".
- **Cabeçalho**: "Criar Conta" também no mobile (FR-007 atualizado).
- **Regras** (`RulesContent`) e **sucesso** (`SuccessModal`): redesenhados com práticas de UX,
  diferenças visuais aprovadas (Clarifications da spec).
- **Menu**: sem borda na última linha de campeonato de cada país.
- **Lista**: indicador animado no lugar de "Carregando jogos.".
- **Modo claro**: imagens brancas do rodapé com contorno.
- **Correção**: o modal do "+N" quebrava a tela (componente `TextOdd` sem import).
- **Login e cadastro** (FR-057 a FR-059, no escopo por decisão do responsável): `AuthModal` (abas
  Entrar e Criar conta), `hooks/useSessao.js` (`SessaoContext` no `PublicLayout`), `utils/session.js`
  (token JWT em `wssports.sessao`); rotas usadas: `POST /api/auth/login`, `POST
  /api/area-cliente/auth/login`, `POST /api/area-cliente/cadastro`, `GET /api/area-cliente/meus-dados`
  e os logouts. Nenhuma mudança no backend.
- **Aposta do cliente logado** (FR-059a): `PaginaInicialController` identifica o cliente pelo token
  (`IdentificacaoPublico`) e usa as configurações dele, com a prop `saldo`; a sessão de cliente
  envia o token em todas as requisições e recarrega as props ao entrar e sair; o cupom aposta em
  `POST /api/area-cliente/apostas` e acompanha o "Em análise" do ao vivo; o modal mostra "Aposta
  confirmada!". A decisão do ao vivo precisa do worker da fila `apostas`
  (`php artisan queue:work --queue=apostas,default`, spec 004).
- **Esportes liberados por padrão** (pedido do responsável): migration
  `2026_10_09_000001_alterar_padrao_esportes_permitidos` muda o padrão de `esportes_permitidos` em
  `visitantes_configuracoes`, `clientes_configuracoes` e `usuarios_configuracoes` para todos os
  esportes do provedor (só o padrão; configurações já gravadas não mudam). No banco local, as
  configurações existentes também foram liberadas, por ajuste direto. Specs 002 e 003 atualizadas.
- **Bilhete e botão voltar** (pedido do responsável): o modal do bilhete fica centralizado com 80% da
  altura da tela no desktop e tela cheia no mobile; a lista dos palpites ocupa o espaço livre (em
  telas muito baixas o conteúdo rola por dentro, sem cortar) e o cabeçalho ganhou o ícone
  fechar, contornado como no sistema antigo. O hook `useVoltarFecha` faz o botão voltar do aparelho
  fechar os modais (bilhete, sucesso, detalhes do jogo e acesso), como o `browser-back-button` do
  sistema antigo; o ouvinte é registrado no `app.jsx` antes do Inertia.
- **Ao vivo → pré-jogo** (correção): uma atualização do ao vivo ainda a caminho ao trocar de filtro
  chegava depois e devolvia a lista e a URL do ao vivo, e o palpite novo saía como ao vivo ("Para
  apostar no ao vivo é preciso fazer login."). Ao trocar de filtro, a tela para o timer do ao vivo e
  cancela a atualização em andamento; continuando no ao vivo, o timer volta ao fim da troca.
- **Aposta do vendedor logado** (FR-059b, igual ao sistema antigo): `PaginaInicialController` aceita
  o token do vendedor (gestor segue como visitante) e devolve a prop `apostador`; a sessão do painel
  manda o token enquanto não se sabe se é gestor e guarda o `apostador` informado pela tela; o cupom
  aposta em `POST /api/apostas` (com o "Em análise" do ao vivo); o modal mostra "Bilhete cadastrado
  com sucesso!" com Imprimir (`utils/print.js`, formato do `print_ticket` do antigo) e Enviar.
- **Carregamento da lista** (pedido do responsável): spinner no fim da rolagem enquanto houver mais
  páginas, além do de troca de filtro; a área dos jogos tem altura mínima igual à da lista visível,
  então o rodapé fica abaixo da tela durante o carregamento (desktop e mobile).
- **Validação do código pelo vendedor** (FR-059c, igual ao sistema antigo): com vendedor logado,
  o código Pendente pesquisado carrega a simulação no cupom (`carregar_validacao` do `useCupom`) e o
  botão vira "Validar" (verde); o envio vai para `POST /api/apostas/pendentes/{codigo}/validar` e o
  modal mostra "Bilhete validado com sucesso!".
