# Implementation Plan: Painel — Cotações (porcentagens, valor do jogo e teto)

**Branch**: `016-painel-cotacoes` | **Date**: 2026-10-21 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/016-painel-cotacoes/spec.md`

## Summary

Cria a tela "Cotações" do painel sobre as regras de cotação da spec 003 (porcentagens de vendedores
e clientes, de campeonato, valor do jogo e teto), sem mudar o cálculo do site, com:

- **Consultas novas** (`CotacoesPainelController`, prefixo `/cotacoes`): nomes das cotações, públicos
  editáveis, clientes, porcentagens de um usuário com a soma dos superiores e os cadeados, países,
  campeonatos e jogos com o resumo das regras, prévia (partes do cálculo e jogos para trocar),
  Regras ativas e histórico.
- **Cadeado por cotação** (do antigo): tabela `porcentagens_cadeados`; Admin e Supervisor trancam um
  código na tela de um usuário; vale para ele e quem está abaixo; respeitado na gravação das
  porcentagens de vendedores e de campeonatos com alvo Vendedores.
- **Histórico simples**: tabela `porcentagens_historicos`, gravada pelas cinco rotas de alteração e
  pelos cadeados; lido em Regras ativas.
- **Frontend**: página `PanelQuotes` com abas Público, Campeonatos, Jogos, Tetos e Regras ativas;
  editor único por mercado (`QuoteGrid`) com prévia em tempo real (`QuotePreview`) e salvar de uma vez
  (`SaveBar`); listas com selo de regras; modais no padrão do painel.

Decisões em [research.md](research.md) (R-01 a R-10).

## Technical Context

**Language/Version**: PHP 8.2 (^8.2); JavaScript (ES2022) com JSX

**Primary Dependencies**: as já instaladas (Laravel 12, Inertia 2, React 19, styled-components 6,
sweetalert2, jwt-auth, spatie/laravel-permission). Nenhum pacote novo.

**Storage**: MySQL; 2 tabelas novas (`porcentagens_cadeados`, `porcentagens_historicos`); tabelas
de regra da spec 003 sem mudança.

**Testing**: nenhum teste automatizado (Constituição); validação pelo [quickstart.md](quickstart.md)

**Target Platform**: navegadores atuais, desktop e celular (prioridade); deploy contínuo (spec 013)

**Project Type**: monólito Laravel + Inertia com API JWT

**Performance Goals**: grade em até 2 s e salvar com retorno em até 1 s (SC-006); listas com o resumo
das regras da página numa consulta (sem N+1); Regras ativas por UNION paginada no banco; prévia
recalculada no navegador a cada tecla (sem requisição).

**Constraints**: cálculo e alcance da spec 003 inalterados; alcance sempre no servidor; cadeado e
histórico na mesma transação da gravação; paginação padrão 20, máximo 100; visual e componentes do
painel (specs 014 e 015) e regras de modais, cabeçalho e campos da constituição v3.4.0 a v3.7.0.

**Scale/Scope**:

| Item | Quantidade |
|---|---|
| Migrations | 2 (tabelas novas) |
| Rotas da API novas / alteradas | 13 / 5 (só gravação) |
| Rotas web novas | 1 |
| Services novos / alterados | 2 (`CadeadosCotacoes`, `HistoricoCotacoes`) / 1 (`CalculoCotacoes::detalhar`) |
| Componentes novos / páginas novas | 10 / 1 |
| Hooks / utils novos | 3 / 1 |

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Princípio / Regra | Verificação | Status |
|---|---|---|
| I. Nomes no backend | Tabelas em português com o prefixo das regras (`porcentagens_cadeados`, `porcentagens_historicos`), colunas snake_case (`trancado_por` como `decidido_por`); rotas kebab-case (`/cotacoes/regras-ativas`) | ✅ Pass |
| I. Nomes no frontend | Componentes em inglês PascalCase (`QuoteGrid`, `QuotePreview`...); hooks `useGradeCotacoes`, `useNomesCotacoes`, `useListaCotacoes`; util `quotes.js`; variáveis e props snake_case em português | ✅ Pass |
| II. Idioma | Artefatos, comentários, mensagens e textos em português | ✅ Pass |
| III. Componentes | Cada componente em pasta própria com `index.jsx` e `styles.jsx` | ✅ Pass |
| IV. Escopo estrito | Arquivos existentes listados abaixo, confirmados pelo responsável em 2026-10-21 | ✅ Pass |
| V. Legibilidade | Revisão ao final de cada tarefa | ✅ Pass |
| VI. Consistência | Rotas de gravação e alcance da spec 003 reaproveitados; permissões existentes; visual, `PanelTabs`, `Pagination`, `ActionsMenu`, `ModalHeader` e padrão de grupos do painel de permissões (spec 015); nomes de cotação de `NomesCotacoes` e grupos de `market_groups.js` | ✅ Pass |
| VII. Sistema antigo | `price`, `price-sellers`, `championships-price`, `matches-price` e `bloquear-porcentagem-cotacao` analisados (R-01 e spec) | ✅ Pass |
| VIII. Fidelidade visual | Diferenças aprovadas pelo responsável e registradas na spec (uma tela, grupos, prévia, salvar de uma vez, selos, Regras ativas) | ✅ Pass |
| IX. Dados fake | Nenhum | ✅ Pass |
| Stack | Nenhuma dependência nova | ✅ Pass |
| Timestamps / soft delete | Tabelas novas com `timestamps()` e `softDeletes()`; destrancar = soft delete | ✅ Pass |
| Comentários de colunas (v3.3.0) | Todas as colunas novas com `->comment()` (data-model.md) | ✅ Pass |
| Referências a tabelas inexistentes | Nenhuma: as FKs apontam para tabelas que já existem | ✅ Pass |
| Paginação ≤ 100 | Padrão 20, máximo 100 em todas as listas novas | ✅ Pass |
| Atualização do frontend | Sem mudança de cache/PWA | ✅ Pass |
| Tamanho dos modais (v3.6.0) | `QuoteEditorModal` e `QuoteHistoryModal` com altura fixa desde a abertura (conteúdo longo e que carrega depois); 100% no celular; cabeçalho, alvo e `SaveBar` sem encolher, só o corpo rola | ✅ Pass |
| Cabeçalho dos modais (v3.7.0) | Todos os modais com `ModalHeader` (ícone sem fundo, título e descrição) | ✅ Pass |
| Campos de itens existentes (v3.7.0) | Público, cliente, alvo, usuário, país, campeonato e jogo escolhidos de lista; nada digitado à mão além dos valores | ✅ Pass |
| Sem testes | Nenhum arquivo, tarefa ou dependência de teste | ✅ Pass |
| Postman | Regenerado na entrega (13 rotas novas e 5 alteradas) | ✅ Pass |

**Resultado do gate**: aprovado; lista de arquivos existentes (Princípio IV) confirmada pelo
responsável em 2026-10-21. Reavaliado depois da Fase 1: sem violação nova.

### Arquivos existentes que serão alterados (Princípio IV)

| Arquivo | Alteração | Motivo |
|---|---|---|
| `app/Http/Controllers/PorcentagensVendedoresController.php` | Respeitar cadeados (422 / `pulados`) e gravar histórico | R-04, R-05 |
| `app/Http/Controllers/PorcentagensCampeonatosController.php` | Respeitar cadeados no alvo Vendedores e gravar histórico | R-04, R-05 |
| `app/Http/Controllers/PorcentagensClientesController.php` | Gravar histórico | R-05 |
| `app/Http/Controllers/PorcentagensConfrontosController.php` | Gravar histórico e recusar jogo que já começou | R-05, caso de borda da spec |
| `app/Http/Controllers/ConfrontosTetoCotacoesController.php` | Gravar histórico | R-05 |
| `app/Services/CalculoCotacoes.php` | Método público `detalhar()` com as partes do cálculo (sem mudar `ajustar()`) | R-03 |
| `app/Http/Controllers/PainelController.php` | Ação `cotacoes` | R-09 |
| `routes/api.php`, `routes/web.php` | 13 rotas de API e 1 página | contracts |
| `resources/js/components/PanelShell/index.jsx` | Item "Cotações" no menu | R-10 |
| `docs/postman/wssports_api.postman_collection.json` | Regenerada | Constituição |
| `specs/003-confrontos/spec.md` | Nota "Alterado pela spec 016" (cadeado e histórico nas rotas de cotação) | coerência dos artefatos |

## Project Structure

### Documentation (this feature)

```text
specs/016-painel-cotacoes/
├── plan.md · research.md · data-model.md · quickstart.md
├── contracts/ (api.md, paginas.md)
├── checklists/requirements.md
└── tasks.md   (/speckit-tasks)
```

### Source Code

```text
app/
├── Models/{PorcentagensCadeados,PorcentagensHistoricos}.php                   # novos
├── Services/{CadeadosCotacoes,HistoricoCotacoes}.php                          # novos
├── Services/CalculoCotacoes.php                                               # alterado (detalhar)
└── Http/
    ├── Controllers/CotacoesPainelController.php                               # novo
    ├── Controllers/Porcentagens{Vendedores,Clientes,Campeonatos,Confrontos}Controller.php  # alterados
    ├── Controllers/ConfrontosTetoCotacoesController.php                       # alterado
    ├── Controllers/PainelController.php                                       # alterado
    └── Requests/CadeadosCotacoesRequest.php                                   # novo
database/migrations/
├── 2026_10_22_000001_create_porcentagens_cadeados_table.php                   # novo
└── 2026_10_22_000002_create_porcentagens_historicos_table.php                 # novo
routes/{api,web}.php                                                           # alterados
resources/js/
├── pages/PanelQuotes/{index,styles}.jsx                                       # novo
├── components/
│   ├── PanelShell/                                                            # alterado (menu)
│   ├── QuoteGrid/ · QuotePreview/ · SaveBar/ · AudiencePicker/ · TargetPicker/          # novos
│   └── QuoteEditorModal/ · RuleBadge/ · QuoteListTable/ · QuoteHistoryModal/            # novos
├── hooks/{useGradeCotacoes,useNomesCotacoes,useListaCotacoes}.js              # novos
└── utils/quotes.js                                                            # novo
docs/postman/wssports_api.postman_collection.json                              # regenerada
```

**Structure Decision**: monólito existente; página Inertia no `PanelShell` da spec 014; API no padrão
dos controllers do painel (permissão por `Funcao::usuario_pode`, alcance por `AlcanceHierarquia`).

## Complexity Tracking

Nenhuma violação a justificar.
