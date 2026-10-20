# Implementation Plan: Painel de controle — base do painel e Dashboard

**Branch**: `014-painel-dashboard` | **Date**: 2026-10-20 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/014-painel-dashboard/spec.md`

## Summary

Cria a base do painel em `/painel` e a Dashboard, com as seções e regras por nível da dashboard do
sistema antigo, corrigidas e otimizadas:

- **Acesso**: rota `GET /auth/eu` com nome, função e seções do usuário; após o login, Admin,
  Supervisor e Gerente vão ao painel e o Vendedor fica na tela de apostas, com a saudação virando
  link para o painel. A página `Panel` (botão do Mercado Pago) é trocada pela `Dashboard`.
- **Seções**: uma rota por seção (`GET /dashboard/<secao>`), cada uma com permissão
  `dashboard.ver_*`, alcance por nível no servidor (`AlcanceDashboard`), somas no banco com bcmath e
  cache de 60 s por usuário.
- **Risco**: Combinação de risco numa consulta só (assinatura por aposta, `HAVING >= 2`). Os avisos
  de combinação nova vêm **só do agendador** (`apostas:avisar_combinacoes_risco`, a cada minuto),
  que grava em `apostas_combinacoes_risco` e manda o WhatsApp ao Admin; o painel consulta esses
  registros (`/dashboard/combinacoes-risco/avisos`) e nunca gera aviso ao carregar ou atualizar.
- **Caixa online**: apostas de clientes, encerramentos, depósitos e saques, da banca toda.
- **Fake**: lançamentos, retiradas e % do gerente zerados em `DadosFake`, com selo "em breve".
- **Frontend**: `PanelShell` (barra e menu no visual do painel antigo), seções independentes com
  esqueleto/erro/sem dados, atualização automática de 1 min com a aba visível e gráficos Chart.js
  carregados só no painel.

Decisões em [research.md](research.md) (R-01 a R-16).

## Technical Context

**Language/Version**: PHP 8.2 (^8.2) no backend; JavaScript (ES2022) com JSX no frontend

**Primary Dependencies**: as já instaladas (Laravel 12, Inertia 2, React 19, styled-components 6,
sweetalert2, `php-open-source-saver/jwt-auth`, `spatie/laravel-permission`) + **`chart.js` ^4.4**
(nova, aprovada na spec, R-16). CallMeBot pelo `Http` do Laravel.

**Storage**: MySQL: 1 tabela nova (`apostas_combinacoes_risco`), 3 colunas em `configuracoes`, 4
índices novos; cache no store atual (`database`); fila não usada (comando agendado síncrono). No
navegador: sessão existente com `nome` e `funcao`; `localStorage` só para chaves de combinação já
vistas e o som ligado/desligado.

**Testing**: nenhum teste automatizado (Constituição); validação manual pelo
[quickstart.md](quickstart.md)

**Target Platform**: navegadores atuais no desktop e no celular; servidor PHP 8.2 com o agendador
(`schedule:run` no cron, já configurado no servidor)

**Project Type**: aplicação web Laravel (monólito com Inertia) + API JWT existente

**Performance Goals**:

| Meta | Alvo |
|---|---|
| Apurado, Apostas e Situação dos jogos com ~5.000 apostas no dia (SC-003) | ≤ 2 s cada, em paralelo |
| Reabertura em < 1 min (SC-004) | ≤ 1 s (cache) |
| Combinação de risco | 1 consulta agregada, ≤ 100 linhas |
| Aviso no WhatsApp (SC-009) | ≤ 2 min |
| Tela principal (SC-007) | sem `chart.js` no pacote inicial |

**Constraints**:

- alcance sempre no servidor (`whereIn usuarios_id`), inclusive vendedores excluídos (R-03);
- dinheiro em bcmath, strings com 2 casas; nenhuma SQL concatenada com dados da requisição;
- dia em Brasília (`FusoSistema`), banco em UTC;
- `pode_usar` impede o Vendedor (e o Gerente na Caixa online) mesmo com a permissão;
- visual do painel antigo (barra preta, APOSTAR/SAIR, seções em blocos) com as melhorias aprovadas;
- nomes conforme a Constituição (permissões `dashboard.ver_*` aprovadas na spec).

**Scale/Scope**:

| Item | Quantidade |
|---|---|
| Tabelas novas / alteradas | 1 / 1 (`configuracoes`) + 4 índices |
| Migrations | 3 |
| Rotas da API novas / web alteradas | 13 / 1 (`/painel`) |
| Controllers novos | 2 (`DashboardController` com 10 ações, `ConfiguracoesAvisosWhatsappController`) |
| Serviços novos | 7 (`AlcanceDashboard`, `CacheDashboard`, `ApuradoDashboard`, `ApostasDashboard`, `CombinacoesRisco`, `CaixaOnline`, `AvisosWhatsapp`) |
| Comandos novos | 1 (`apostas:avisar_combinacoes_risco`) |
| Enums / models novos | 1 (`SituacaoAvisoWhatsapp`) / 1 (`ApostasCombinacoesRisco`) |
| Requests / Resources novos | 2 / 2 |
| Seeders novos | 1 (`DashboardSeeder`) |
| Componentes novos / páginas novas / removidas | 8 / 1 (`Dashboard`) / 1 (`Panel`) |
| Hooks / utils novos | 2 (`useSecaoDashboard`, `usePainel`) / 1 (`charts.js`) |

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Princípio / Regra | Verificação | Status |
|---|---|---|
| I. Nomes no backend | Tabela `apostas_combinacoes_risco` (prefixo da principal), colunas em português; enum `SituacaoAvisoWhatsapp` com valores em português; permissões `dashboard.ver_*` (recurso sem tabela, aprovado na spec); rotas kebab-case (`/dashboard/situacao-jogos`, `/configuracoes/avisos-whatsapp`); `whatsapp`, `callmebot` como termos consagrados | ✅ Pass |
| I. Nomes no frontend | Componentes em inglês PascalCase (`PanelShell`, `DashboardSection`, `ChartCanvas`…); hooks `useSecaoDashboard`, `usePainel`; util `charts.js`; funções e variáveis em snake_case português; props de estilo com `$` | ✅ Pass |
| II. Idioma | Artefatos, comentários e textos em português | ✅ Pass |
| III. Componentes | Cada componente e a página em pasta própria com `index.jsx` e `styles.jsx` | ✅ Pass |
| IV. Escopo estrito | Arquivos existentes listados abaixo, confirmados pelo responsável em 2026-10-20 | ✅ Pass |
| V. Legibilidade | Revisão ao final de cada tarefa | ✅ Pass |
| VI. Consistência | Permissões em `Funcao.php` + `permissao_cliente`; seeder no padrão do `CassinoSeeder`; configuração no padrão do `ConfiguracoesCassinoController`; segredo `encrypted`/`hidden` como `GatewaysPagamento`; relatório com query builder + bcmath como `RelatorioCassino`; comando agendado em `routes/console.php`. Padrão novo: recurso de permissão sem tabela (`dashboard`), aprovado na spec | ✅ Pass |
| VII. Sistema antigo | Arquivos e funcionamento em R-01; diferenças em R-02, todas aprovadas na spec | ✅ Pass |
| VIII. Fidelidade visual | Barra e seções no visual do `screens/adm`; menu lateral, cartões e gráficos aprovados (Clarifications); referências em contracts/paginas.md | ✅ Pass |
| IX. Dados fake | `DadosFake::caixa_usuario()` e `percentual_comissao_gerente()`, zerados, só exibição, listados na spec e em R-10 | ✅ Pass |
| Stack: dependências | `chart.js` justificado em R-16 (decisão do responsável) | ✅ Pass |
| Timestamps, soft delete e comentários | Tabela nova com `timestamps()` + `softDeletes()`; toda coluna nova com `->comment()` | ✅ Pass |
| Referências a tabelas inexistentes | Nenhuma (lançamentos e % do gerente são fake, sem coluna) | ✅ Pass |
| Paginação ≤ 100 | Seções são agregados; listas limitadas a 10, 50 e 100 | ✅ Pass |
| Sem testes | Nenhum arquivo, tarefa ou dependência de teste | ✅ Pass |
| Postman | Coleção regenerada na mesma entrega | ✅ Pass |

**Resultado do gate**: aprovado; lista de arquivos existentes (Princípio IV) confirmada pelo
responsável em 2026-10-20. Reavaliado depois da Fase 1: sem violação nova.

### Arquivos existentes que serão alterados (Princípio IV)

| Arquivo | Alteração | Motivo |
|---|---|---|
| `app/Enums/Funcao.php` | `PERMISSOES_DASHBOARD`; `permissoes_padrao` (inclui as de dashboard, também para o Vendedor) e `pode_usar` | R-09 |
| `app/Fakes/DadosFake.php` | `caixa_usuario()` e `percentual_comissao_gerente()` | R-10 |
| `app/Models/Configuracoes.php` | `fillable`, `casts` (`encrypted`) e `$hidden` das 3 colunas do aviso | R-13 |
| `app/Http/Controllers/AutenticacaoController.php` | Ação `eu` (dados do usuário logado) | R-14 |
| `app/Http/Controllers/PainelController.php` | Renderiza `Dashboard` no lugar de `Panel` | R-15 |
| `routes/api.php` | 13 rotas novas | contracts/api.md |
| `routes/console.php` | Agenda `apostas:avisar_combinacoes_risco` a cada minuto | R-11 |
| `database/seeders/DatabaseSeeder.php` | Chama o `DashboardSeeder` | R-09 |
| `resources/js/hooks/useSessao.js` | No login de usuário, busca `GET /auth/eu` e guarda `nome` e `funcao` | R-14 |
| `resources/js/components/AuthModal/index.jsx` | Admin, Supervisor e Gerente vão para `/painel` após o login | FR-003 |
| `resources/js/components/Header/index.jsx` | Saudação do usuário do painel vira link para `/painel` | FR-004 |
| `resources/js/pages/Panel/` (`index.jsx`, `styles.jsx`) | Removida (botão do Mercado Pago sai do frontend) | FR-007 |
| `package.json`, `package-lock.json` | `chart.js` | R-16 |
| `docs/postman/wssports_api.postman_collection.json` | Regenerada | Constituição |

Ficam **sem alteração**: `useAdministrador` (ainda usado pelo `SideMenu`), `Usuarios.php` (o alcance
com excluídos fica no serviço novo, R-03), `app.jsx` e `PublicLayout` (o Chart.js é carregado por
`import()` dentro do componente, R-15) e todo o backend do vínculo do Mercado Pago.

## Project Structure

### Documentation (this feature)

```text
specs/014-painel-dashboard/
├── plan.md              # este arquivo
├── research.md          # Fase 0
├── data-model.md        # Fase 1
├── quickstart.md        # Fase 1
├── contracts/
│   ├── api.md           # rotas da API e comando
│   └── paginas.md       # telas e componentes
├── checklists/
│   └── requirements.md
└── tasks.md             # Fase 2 (/speckit-tasks)
```

### Source Code (repository root)

```text
app/
├── Console/Commands/
│   └── AvisarCombinacoesRiscoCommand.php        # novo
├── Enums/
│   ├── Funcao.php                               # alterado
│   └── SituacaoAvisoWhatsapp.php                # novo
├── Fakes/DadosFake.php                          # alterado
├── Http/
│   ├── Controllers/
│   │   ├── AutenticacaoController.php           # alterado (eu)
│   │   ├── PainelController.php                 # alterado
│   │   ├── DashboardController.php              # novo (10 ações)
│   │   └── ConfiguracoesAvisosWhatsappController.php  # novo
│   ├── Requests/
│   │   ├── DashboardRequest.php                 # novo (atualizar)
│   │   └── ConfiguracoesAvisosWhatsappRequest.php     # novo
│   └── Resources/
│       ├── UsuarioLogadoResource.php            # novo
│       └── ConfiguracoesAvisosWhatsappResource.php    # novo
├── Models/
│   ├── ApostasCombinacoesRisco.php              # novo
│   └── Configuracoes.php                        # alterado
└── Services/
    ├── AlcanceDashboard.php                     # novo
    ├── CacheDashboard.php                       # novo
    ├── ApuradoDashboard.php                     # novo
    ├── ApostasDashboard.php                     # novo (situação, apostas, um jogo, rankings)
    ├── CombinacoesRisco.php                     # novo
    ├── CaixaOnline.php                          # novo
    └── AvisosWhatsapp.php                       # novo

database/
├── migrations/
│   ├── 2026_10_20_000001_create_apostas_combinacoes_risco_table.php
│   ├── 2026_10_20_000002_add_avisos_whatsapp_configuracoes_table.php
│   └── 2026_10_20_000003_add_indices_dashboard.php
└── seeders/
    ├── DashboardSeeder.php                      # novo
    └── DatabaseSeeder.php                       # alterado

routes/
├── api.php                                      # alterado
└── console.php                                  # alterado

resources/js/
├── pages/
│   ├── Dashboard/{index,styles}.jsx             # novo
│   └── Panel/                                   # removido
├── components/
│   ├── PanelShell/{index,styles}.jsx            # novo
│   ├── DashboardSection/{index,styles}.jsx      # novo
│   ├── StatCard/{index,styles}.jsx              # novo
│   ├── ChartCanvas/{index,styles}.jsx           # novo
│   ├── RiskCombinations/{index,styles}.jsx      # novo
│   ├── SingleGameBets/{index,styles}.jsx        # novo
│   ├── RankingChart/{index,styles}.jsx          # novo
│   ├── CombinationModal/{index,styles}.jsx      # novo
│   ├── AuthModal/index.jsx                      # alterado
│   └── Header/index.jsx                         # alterado
├── hooks/
│   ├── useSecaoDashboard.js                     # novo
│   ├── usePainel.js                             # novo
│   └── useSessao.js                             # alterado
└── utils/charts.js                              # novo

docs/postman/wssports_api.postman_collection.json  # regenerada
```

**Structure Decision**: monólito Laravel + Inertia já existente. Backend no padrão de
`app/Services` + controllers finos; frontend em `resources/js` com uma página Inertia nova que usa o
`PublicLayout` atual (só providers) e monta o próprio `PanelShell`.

## Complexity Tracking

Nenhuma violação da Constituição a justificar.
