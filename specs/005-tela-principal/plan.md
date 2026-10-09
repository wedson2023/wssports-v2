# Implementation Plan: Tela principal de apostas esportivas (área `/`)

**Branch**: `005-tela-principal` | **Date**: 2026-10-09 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/005-tela-principal/spec.md`

## Summary

Primeiro frontend do sistema novo: a tela principal de apostas para o visitante, na rota `/`,
visualmente idêntica à `screens/main` do sistema antigo (modo escuro), responsiva e instalável
como aplicativo.

- **Inertia + React**: o Laravel entrega a página `Home` com os dados prontos (listagem de jogos
  pelo mesmo serviço da API pública, configurações reais e dados fake). Filtros, rolagem infinita
  e ao vivo (a cada 7s) fazem recargas parciais do Inertia.
- **Ações pela API pública existente**: detalhe do "+N", código da aposta e bilhete por código
  usam as rotas das specs 003 e 004, sem duplicar regra de aposta.
- **Cupom**: estado imutável num contexto do layout persistente, guardado no aparelho, com o
  cálculo do prêmio em inteiros espelhando o `CalculoPremio` do backend.
- **Atualização instantânea**: arquivos com hash, página nunca do cache com conexão, versão
  conferida pelo servidor em toda requisição Inertia e ao voltar para a aba, service worker que se
  atualiza sozinho. Configurações vêm do servidor a cada carga.
- **Tema**: tokens de cor com dois modos (escuro = sistema antigo; claro = nova paleta) e botão
  dia/noite; as cores do tema vêm das props.
- **Fakes**: um único arquivo no backend (`app/Fakes/DadosFake.php`) e imagens em `public/fakes/`.

Decisões em [research.md](research.md) (R-12 a R-25).

## Technical Context

**Language/Version**: PHP 8.2 (^8.2) no backend; JavaScript (ES2022) com JSX no frontend

**Primary Dependencies**:

- Já instalados: Laravel 12, Vite 7, `laravel-vite-plugin`, `axios`.
- Novos (justificados no R-12): `inertiajs/inertia-laravel` ^2, `@inertiajs/react` ^2,
  `react`/`react-dom` ^19, `@vitejs/plugin-react` ^5, `styled-components` ^6.1,
  `vite-plugin-pwa` ^1, `sweetalert2` ^11, `@fontsource/roboto` ^5, `material-icons` ^1.13.
- Removidos: `tailwindcss`, `@tailwindcss/vite`.

**Storage**: nenhuma mudança no banco. No aparelho: `localStorage` (`wssports.cupom`,
`wssports.modo`) e Cache Storage do service worker (só arquivos estáticos com hash).

**Testing**: nenhum teste automatizado (Constituição); validação manual pelo
[quickstart.md](quickstart.md)

**Target Platform**: navegadores atuais (Chrome, Edge, Firefox, Safari) no desktop e no celular
(Android e iOS), no navegador ou instalado como PWA; servidor PHP 8.2

**Project Type**: aplicação web Laravel (monólito com Inertia) + API existente

**Performance Goals**:

| Meta | Alvo |
|---|---|
| Lista de jogos visível ao abrir, celular comum em 4G (SC-008) | ≤ 3 s |
| Cotações do ao vivo na tela após a mudança no backend (SC-005) | ≤ 7 s |
| Troca para a versão nova após o deploy (SC-009) | na próxima interação ou ao voltar para a aba |
| JavaScript inicial (comprimido) | ≤ 250 KB |

**Constraints**:

- visual idêntico ao sistema antigo no modo escuro (Princípio VIII), com as diferenças aprovadas;
- dinheiro em centavos inteiros, sem ponto flutuante;
- nenhuma cotação ou jogo fake; fakes só em `app/Fakes/DadosFake.php` e `public/fakes/`;
- página, API e fakes nunca servidos do cache com conexão;
- um breakpoint (900px); zoom bloqueado;
- nomes: componentes em inglês PascalCase; variáveis e funções em camelCase português; chaves da
  API como chegam.

**Scale/Scope**:

| Item | Quantidade |
|---|---|
| Páginas Inertia | 2 (`Home`, `Rules`) |
| Layout | 1 (`PublicLayout`, persistente) |
| Componentes | 30 |
| Hooks | 5 |
| Rotas web | 4 (`/`, `/regras`, `/sw.js`, `/manifest.webmanifest`) |
| Rotas da API | 0 novas (1 parâmetro novo, R-25, aguardando autorização) |
| Tabelas / migrations | 0 |

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Princípio / Regra | Verificação | Status |
|---|---|---|
| I. Nomes no backend | Classes em português PascalCase (`PaginaInicialController`, `TratarRequisicoesInertia`, `DadosFake`); métodos e variáveis em `snake_case`; métodos do pacote mantêm o nome (`version`, `share`, `rootView`) | ✅ Pass |
| I. Exceção do frontend | Componentes em inglês PascalCase (`OddButton`, `BetSlip`); funções e hooks em camelCase português (`alternarPalpite`, `useCupom`); chaves da API como chegam (`time_casa`) | ✅ Pass |
| I. Pastas | `pages`, `layouts`, `components`, `hooks`, `theme`, `utils` em inglês `snake_case`; pastas de componentes em PascalCase; `app/Fakes` em PascalCase (PSR-4) | ✅ Pass |
| I. Rotas em kebab-case | `/regras`, `/sw.js`, `/manifest.webmanifest`; query em `snake_case` (`depois_de_amanha`) | ✅ Pass |
| II. Idioma | Artefatos e comentários em português | ✅ Pass |
| III. Componentes | Cada componente em pasta própria com `index.jsx` e `styles.jsx`; nenhum componente solto (o provedor do cupom fica no `PublicLayout`) | ✅ Pass |
| IV. Escopo estrito | Arquivos existentes alterados listados abaixo, **aguardando confirmação do responsável** | ⏳ Pendente |
| V. Legibilidade | Revisão ao final de cada tarefa | ✅ Pass |
| VI. Consistência | Controllers e request no padrão existente; serviços da spec 003/004 reaproveitados sem cópia; ações pela API pública já existente | ✅ Pass |
| VII. Sistema antigo | Arquivos consultados e funcionamento no research.md (R-01, R-02, R-21); diferenças de comportamento aprovadas em Clarifications | ✅ Pass |
| VIII. Fidelidade visual | Inventário R-07/R-21; diferenças aprovadas (FR-003a a FR-003c, botão dia/noite, modo claro). **Paleta do modo claro (R-19) e linha da cotação (R-20) aguardando aprovação** | ⏳ Pendente |
| VIII. Áreas por rota | Só `/` (e `/regras`); área definida pela URL; nada de área no `localStorage` | ✅ Pass |
| IX. Dados fake | Local único (`app/Fakes/DadosFake.php`, `public/fakes/`), formato dos dados reais, nunca no envio da aposta; lista na spec (FR-060 a FR-063) e no research.md (R-09) | ✅ Pass |
| Stack: dependências novas | Justificadas no R-12 | ✅ Pass |
| Stack: atualização e cache | R-15: arquivos com hash, página sem cache, versão pelo servidor, configurações por carga | ✅ Pass |
| Timestamps e soft delete | Sem tabelas novas | ➖ N/A |
| Paginação ≤ 100 | Lista em páginas de 50 | ✅ Pass |
| Sem testes | Nenhum arquivo, tarefa ou dependência de teste | ✅ Pass |
| Postman | Rotas web não entram na coleção. Só se o R-25 for autorizado (parâmetro novo na API) a coleção é regenerada na mesma entrega | ✅ Pass |

**Resultado do gate**: aprovado com três pendências que dependem do responsável antes da
implementação: confirmação dos arquivos existentes (Princípio IV), paleta do modo claro e linha
da cotação (Princípio VIII), e filtro por campeonato (R-25).

### Arquivos existentes que serão alterados ou removidos (Princípio IV)

| Arquivo | Alteração | Motivo | Situação |
|---|---|---|---|
| `composer.json`, `composer.lock` | `inertiajs/inertia-laravel` | R-12 | aguardando confirmação |
| `package.json`, `package-lock.json` | Dependências do R-12; remove Tailwind | R-12 | aguardando confirmação |
| `vite.config.js` | Plugins React e PWA; entrada `app.jsx`; remove Tailwind | R-12, R-15 | aguardando confirmação |
| `resources/js/app.js` | Removido (substituído por `app.jsx`) | R-13 | aguardando confirmação |
| `resources/css/app.css` | Removido (só tinha o Tailwind) | R-12 | aguardando confirmação |
| `resources/views/welcome.blade.php` | Removido (substituído por `app.blade.php`) | R-14 | aguardando confirmação |
| `routes/web.php` | Rotas `/`, `/regras`, `/sw.js`, `/manifest.webmanifest` no lugar da rota de boas-vindas | R-14 | aguardando confirmação |
| `bootstrap/app.php` | `TratarRequisicoesInertia` no grupo `web` | R-14 | aguardando confirmação |
| `app/Http/Requests/ListagemPublicaRequest.php` | Regra e mensagem do filtro `campeonato` | R-25 | aguardando autorização |
| `app/Services/ListagemConfrontos.php` | Filtro `campeonato` no pré-jogo e no ao vivo | R-25 | aguardando autorização |
| `specs/003-confrontos/contracts/api.md`, `docs/postman/wssports_api.postman_collection.json` | Parâmetro `campeonato` | R-25 e Constituição (Postman) | aguardando autorização |

`resources/js/bootstrap.js` continua igual. Nenhum outro arquivo das specs 001 a 004 muda.

## Project Structure

### Documentation (this feature)

```text
specs/005-tela-principal/
├── spec.md
├── plan.md              # este arquivo
├── research.md          # R-01 a R-25
├── data-model.md        # dados recebidos e estado no aparelho
├── quickstart.md        # validação manual
├── contracts/
│   └── paginas.md       # rotas web, props das páginas, manifest
├── checklists/
│   └── requirements.md
└── tasks.md             # /speckit-tasks
```

### Source Code (repository root)

```text
app/
├── Fakes/
│   └── DadosFake.php                       # local único dos fakes (Princípio IX)
└── Http/
    ├── Controllers/
    │   ├── PaginaInicialController.php     # GET /
    │   ├── RegrasController.php            # GET /regras
    │   └── ArquivosPwaController.php       # GET /sw.js e /manifest.webmanifest
    ├── Middleware/
    │   └── TratarRequisicoesInertia.php    # versão, props compartilhadas, sem cache na página
    └── Requests/
        └── PaginaInicialRequest.php        # filtros da página (estende ListagemPublicaRequest)

public/fakes/                               # logo, ícones do PWA e banners fake

resources/
├── views/
│   └── app.blade.php                       # raiz do Inertia + script do modo sem piscar
└── js/
    ├── app.jsx                             # entrada: createInertiaApp, registro do service worker
    ├── bootstrap.js                        # existente (axios)
    ├── pages/
    │   ├── Home/                           # tela principal
    │   └── Rules/                          # regulamento
    ├── layouts/
    │   └── PublicLayout/                   # ThemeProvider, provedor do cupom, cabeçalho, barra de esportes, WhatsApp
    ├── components/
    │   ├── GlobalStyle/                    # reset, Roboto 14px, altura visível
    │   ├── Header/                         # logo, menu (mobile), Criar Conta, Entrar
    │   ├── ThemeToggle/                    # botão dia/noite
    │   ├── SportsBar/                      # barra de esportes
    │   ├── SideMenu/                       # coluna/gaveta "Menu": itens e países/campeonatos
    │   ├── PanelHeader/                    # título de painel com X (Menu e Cupom)
    │   ├── BannerCarousel/                 # carrossel de banners
    │   ├── SearchBar/                      # busca por time e código do bilhete
    │   ├── DateTabs/                       # Hoje, Amanhã, dia da semana
    │   ├── MatchList/                      # campeonatos, rolagem infinita, vazio
    │   ├── ChampionshipHeader/             # cabeçalho do campeonato
    │   ├── MatchCard/                      # jogo: times, horário/placar, cotações
    │   ├── OddGroup/                       # C, E, F, A e "+N"
    │   ├── OddButton/                      # botão de cotação (normal, selecionado, cadeado, variação)
    │   ├── BetSlipSummary/                 # barra de resumo do mobile (Conferir)
    │   ├── BetSlip/                        # coluna/painel "Cupom"
    │   ├── BetSlipItem/                    # palpite do cupom
    │   ├── BetSlipForm/                    # nome, valor, retorno, cotação, vendedor paga
    │   ├── QuickValues/                    # 2, 3, 5, 10, 20, 50
    │   ├── BetSlipActions/                 # Limpar e Finalizar
    │   ├── MatchDetailsModal/              # modal do "+N"
    │   ├── SuccessModal/                   # código da aposta
    │   ├── TicketModal/                    # bilhete pelo código
    │   ├── RulesContent/                   # REGULAMENTO
    │   ├── WhatsAppButton/                 # botão flutuante
    │   ├── Footer/                         # redes sociais, jogo responsável, copyright e versão
    │   ├── Backdrop/                       # fundo escurecido dos modais e da gaveta
    │   ├── LoadingScreen/                  # "Carregando jogos."
    │   ├── Spinner/                        # indicador do Finalizar
    │   └── EmptyMessage/                   # mensagens de lista vazia
    ├── hooks/
    │   ├── useCupom.js                     # reducer e persistência do cupom
    │   ├── useModo.js                      # modo claro/escuro
    │   ├── useTelaMobile.js                # matchMedia 900px
    │   ├── useAtualizacaoVersao.js         # conferir versão ao voltar para a aba
    │   └── useVariacaoCotacoes.js          # cotação anterior × atual (piscar)
    ├── theme/
    │   └── tokens.js                       # cores do tema e paletas dos dois modos, breakpoint
    └── utils/
        ├── dinheiro.js                     # centavos, cálculo do prêmio (espelho do CalculoPremio)
        ├── armazenamento.js                # localStorage com try/catch
        ├── alertas.js                      # sweetalert2 com os textos do sistema antigo
        ├── api.js                          # axios para a API pública
        └── datas.js                        # horário e nome do dia (Intl)
```

Cada pasta de componente, página e layout tem `index.jsx` e `styles.jsx` (Princípio III).

**Structure Decision**: monólito Laravel com Inertia. O frontend fica em `resources/js`, com
páginas, layout e componentes por pasta; o backend ganha um controller por página, o middleware
do Inertia e o arquivo único de fakes. A API e os serviços das specs 003 e 004 são reaproveitados
sem mudança, exceto o filtro por campeonato (R-25), se autorizado.

## Pendências antes do `/speckit-tasks`

1. Confirmar os arquivos existentes alterados ou removidos (tabela acima).
2. Aprovar ou ajustar a paleta do modo claro ([research.md](research.md), R-19).
3. Aprovar a linha da cotação sem o "vendedor paga" ocupando a linha inteira (R-20).
4. Autorizar o filtro `campeonato` na listagem (R-25).

## Complexity Tracking

Nenhuma violação da constituição.
