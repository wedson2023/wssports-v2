# Implementation Plan: Cassino integrado ao provedor TBS2

**Branch**: `011-cassino-tbs2` | **Date**: 2026-10-16 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/011-cassino-tbs2/spec.md`

## Summary

O cassino do sistema antigo é reescrito sobre o provedor TBS2, com quatro partes.

**Catálogo (US4)**: o comando `cassinos_jogos:sincronizar` roda de hora em hora e grava o
`gamesList` em três níveis (R-02 a R-06):

- **provedor**: o `title` normalizado;
- **jogo**: único por provedor e nome;
- **canal**: o `label`, guardado como opção do jogo, com o código do provedor, a prioridade e as
  marcações pré-pago e "bônus primeiro".

Isso acaba com o jogo repetido do antigo. O jogo recadastrado com ID novo atualiza o canal. O que
sai da lista é desativado sem perder histórico.

**Página `/cassino` (US1)**: página Inertia `Casino`, com o mesmo visual de `screens/casino`. Os
jogos vêm da API pública, paginada até 100 e com cache de 5 minutos para a tela inicial (R-07). A
abertura (US2) escolhe o canal pela prioridade e passa ao próximo quando o provedor recusa. O jogo
abre em moldura (`GameFrame`) ou em página própria e fecha pelas mensagens do provedor e pelo
`/close.php` (R-08, R-09).

**Carteira (US2)**: o callback público `POST /api/cassino/carteira` valida hall e chave em toda
chamada e responde sempre no formato do provedor (R-13). O `writeBet` roda numa transação com a
linha do cliente travada (R-14 a R-19):

- **Idempotência**: pelo `tradeId`.
- **Tipo de ação**: pela tabela `cassinos_acoes`.
- **Carteira**: a carteira da sessão do clarify.
- **Saldo**: um movimento líquido por rodada pelo `SaldoClientes`, com a origem nova `Cassino`.
- **Rollover**: somado com ligação desfazível.
- **Cancelamento e reembolso**: devolvem a aposta.
- **Registro**: toda chamada fica em `cassinos_chamadas`, com retenção.

**Minha Conta e painel (US3, US5)**:

- **Extrato**: mostra uma linha por sessão, a transação mais recente com os totais (R-20).
- **Rodadas**: ao tocar na linha, um modal lista as rodadas.
- **Relatórios**: por jogo, provedor e canal, com GGR e comissão pelo query builder (R-22).
- **Histórico**: consulta ao `gameSessionsLog` (R-23).
- **Cassino desligado**: esconde a área, o atalho e o card "Bônus cassino" (R-21).

Decisões em [research.md](research.md).

## Technical Context

**Language/Version**: PHP 8.2 (^8.2); JavaScript (React 19) com Vite 7 (Node 20)

**Primary Dependencies**: Laravel 12, Inertia 2, styled-components 6, `bcmath`. **Nenhuma nova**

**Storage**: MySQL local `wssports` (só `migrate`); cache do Laravel para a tela inicial do cassino

**Testing**: nenhum teste automatizado (constituição). Validação manual pelo
[quickstart.md](quickstart.md), com o callback simulado no Postman ou com um túnel num hall de teste

**Target Platform**: servidor PHP 8.2 com agendador e worker de fila; navegadores atuais e PWA

**Project Type**: aplicação web Laravel + React (Inertia), backend e frontend

**Performance Goals**:

| Meta | Alvo |
|---|---|
| Resposta ao provedor (SC-004) | < 2 s em 99% (alvo interno < 300 ms: uma transação, consultas por índice) |
| Primeira tela do cassino no celular (SC-006) | ≤ 3 s (props leves + `GET /publico/cassino` em cache) |
| Catálogo atualizado na página (SC-008) | ≤ 1 h 5 min (carga no minuto 5 + limpeza do cache) |
| Sincronização de ~6.400 itens | < 60 s, numa transação, com as tabelas carregadas em memória por chave |

**Constraints**:

- dinheiro sem `float` (centavos do `SaldoClientes` e bcmath);
- trava da linha do cliente em todo `writeBet`;
- credenciais só no `.env`, e a chave nunca vai para o log nem para o banco;
- toda resposta ao provedor em HTTP 200 e no formato dele, nunca vazia;
- o sistema antigo é só leitura;
- não alterar o callback do hall de produção;
- textos, logs e mensagens em português.

**Scale/Scope**:

| Item | Quantidade |
|---|---|
| Tabelas | 9 novas (`cassinos_*`), 2 alteradas (`configuracoes`: 4 colunas; `clientes_transacoes`: comentário de `origem`) |
| Enums | 6 novos, 2 alterados (`OrigemTransacao`, `Funcao`) |
| Serviços | 10 novos, 1 alterado (`RolloverClientes`) |
| Rotas | 22 novas na API, 2 novas na web; 1 existente com filtro novo (extrato) |
| Permissões | 2 novas (`cassinos_jogos.gerenciar`, `cassinos_rodadas.listar`), Admin e Supervisor |
| Comandos / jobs | 2 comandos novos, 1 job novo |
| Frontend | 1 página nova, 13 componentes novos, 2 hooks novos, 1 utilitário novo; 6 arquivos existentes alterados |

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Princípio / Regra | Verificação | Status |
|---|---|---|
| I. `snake_case` | Métodos (`escolher`, `somar_cassino`, `abrir`); chaves (`total_apostado`, `bonus_primeiro`); comandos (`cassinos_jogos:sincronizar`); hooks `useCassino`, `useJogoCassino` | ✅ Pass |
| I. Banco em português | Tabelas e colunas em português; siglas e termos consagrados (`url`, `ip`, `trade_id`, `json`) | ✅ Pass |
| I. Prefixo de tabelas | Prefixo `cassinos_` em todas as tabelas novas, sem tabela `cassinos` (R-25). `cassinos_jogos_canais` e `cassinos_rodadas_rollovers` seguem a tabela a que se ligam | ⚠️ A confirmar |
| I. Enums | Casos em `PascalCase` com acento (`ApostaComPrêmio`, `RodadaGrátis`, `Concluída`); valores com espaço (`'Aposta com prêmio'`, `'Em andamento'`) | ✅ Pass |
| I. Permissões | `cassinos_jogos.gerenciar`, `cassinos_rodadas.listar` (`<tabela>.<acao>`) | ✅ Pass |
| I. Rotas em kebab-case | `/publico/cassino/jogos`, `/cassinos-jogos-canais`, `/configuracoes/cassino`, `/cassino/carteira`. Exceção: `/close.php`, endereço fixo exigido pelo provedor (`home_url`) | ✅ Pass |
| I. Frontend | Componentes em inglês `PascalCase` (`GameCard`, `GameFrame`, `CasinoRoundsModal`); props e variáveis em `snake_case` português; transient props `$ativo`; utilitário `utils/casino_types.js` | ✅ Pass |
| II. Idioma | Artefatos, comentários, logs e mensagens em português | ✅ Pass |
| III. Componentes React | Cada componente novo e a página `Casino` em pasta própria com `index.jsx` e `styles.jsx` | ✅ Pass |
| IV. Escopo estrito | Arquivos existentes listados abaixo, cada um com o motivo | ⏳ Aguardando confirmação |
| V. Legibilidade | Revisão ao final de cada tarefa | ✅ Pass |
| VI. Consistência | Saldo só por `SaldoClientes`; escolha de carteira no padrão do `EscolhaCarteira`; rollover com ligação no padrão de `apostas_rollovers`; cliente HTTP no padrão do `ProvedorCotacoes`; configuração por rota própria no padrão de `configuracoes/encerramento`; permissões no `Funcao` + seeder no padrão do `EncerramentoSeeder`; área do cliente com `GarantirAcessoCliente`. Padrão novo: callback público autenticado pela chave do hall, sem equivalente no projeto (o webhook do Mercado Pago é o mais próximo e também valida assinatura no serviço) | ✅ Pass |
| VII. Sistema antigo | Consultado e listado no [research.md](research.md#sistema-antigo-princípio-vii); diferenças aprovadas na spec (Clarifications) | ✅ Pass |
| VIII. Fidelidade visual | Página e componentes reproduzem `screens/casino`, `modal/balance`, `modal/bonus` e `details_casino`. Diferenças aprovadas: jogo na moldura quando o provedor permite (FR-014) e coluna prêmio preenchida (FR-028). Provedor sem logo mostra o nome (só acontece com provedor ativado além dos 10 do antigo) | ✅ Pass |
| IX. Dados fake | Remove `DadosFake::indicadores()['cassino']` (FR-035); nenhum fake novo | ✅ Pass |
| Timestamps e soft delete | 9 tabelas com `timestamps()` + `softDeletes()`. Exclusão física só em `cassinos_chamadas` (log técnico, R-15), justificada abaixo em Complexity Tracking | ✅ Pass (justificado) |
| Comentários de colunas | Todas as colunas novas e as 4 de `configuracoes` com `->comment()`; `clientes_transacoes.origem` ganha o valor `Cassino` no comentário | ✅ Pass |
| Referências a tabelas inexistentes | Nenhuma; a ordem das migrations respeita as chaves | ✅ Pass |
| Paginação ≤ 100 | Grade 50, rodadas 50, painel 20; todas com máximo de 100 | ✅ Pass |
| Atualização e cache | Configuração do cassino nas props a cada carga; `/close.php` sem cache; o cache de 5 min é só de dados do catálogo, limpo a cada alteração | ✅ Pass |
| Sem testes | Nenhum arquivo, tarefa ou dependência de teste | ✅ Pass |
| Postman | Coleção regenerada com as 22 rotas e o filtro novo | ✅ Pass |
| Novas dependências | Nenhuma | ✅ Pass |

**Resultado do gate**: aprovado, com dois pontos para o responsável confirmar antes do
`/speckit-tasks`:

1. A lista de arquivos existentes abaixo (Princípio IV).
2. O prefixo `cassinos_` sem uma tabela `cassinos` (R-25, Princípio VI). Se não for aceito, a
   alternativa é renomear `cassinos_jogos` para `cassinos` (o jogo como recurso principal) e as
   demais para `cassinos_canais`, `cassinos_sessoes`, etc.

### Arquivos existentes que serão alterados (Princípio IV)

**Backend**

| Arquivo | Alteração | Motivo |
|---|---|---|
| `app/Enums/OrigemTransacao.php` | Caso `Cassino` | R-14, R-20 |
| `app/Enums/Funcao.php` | Grupo `PERMISSOES_CASSINO` em `permissoes_padrao` e na regra de Admin e Supervisor do `pode_usar` | FR-034, R-24 |
| `app/Models/Configuracoes.php` | `fillable` e casts das 4 colunas | R-10, R-21, R-24 |
| `app/Services/RolloverClientes.php` | Métodos `somar_cassino(CassinosRodadas)` e `desfazer_cassino(CassinosRodadas)` | FR-025, R-18 |
| `app/Fakes/DadosFake.php` | `indicadores()` deixa de ter `cassino` | FR-035 |
| `app/Http/Middleware/TratarRequisicoesInertia.php` | `indicadores.cassino` vem de `Configuracoes::atual()->cassino_habilitado` | FR-012, FR-035 |
| `app/Http/Controllers/AreaClienteMinhaContaController.php` | Operação `cassino`; transações `Cassino` só pela mais recente de cada sessão; carrega as sessões em lote | FR-028, R-20 |
| `app/Http/Requests/ExtratoMinhaContaRequest.php` | `operacao` aceita `cassino` | FR-028 |
| `app/Http/Resources/ExtratoMinhaContaResource.php` | Chave `cassino` (sessão, jogo, totais); `valor` = total apostado nas linhas de cassino | FR-028 |
| `routes/api.php` | 22 rotas e `use` dos controllers | contrato |
| `routes/web.php` | `cassino` e `close.php` | contrato |
| `routes/console.php` | Agenda dos 2 comandos | contracts/comandos.md |
| `database/seeders/DatabaseSeeder.php` | Chama `CassinoSeeder` depois do `EncerramentoSeeder` | R-24 |
| `config/services.php` | Bloco `provedor_cassino` (do `.env`) | R-01 |
| `.env.example` | Variáveis `CASSINO_*` sem valores | R-01 |
| `docs/postman/wssports_api.postman_collection.json` | Regenerada | constituição |

**Frontend**

| Arquivo | Alteração | Motivo |
|---|---|---|
| `resources/js/pages/Home/index.jsx` | "Cassino" sai de `esportes_sem_acao` e navega para `/cassino` | FR-012, R-21 |
| `resources/js/pages/Account/index.jsx` | Esconde o card "Bônus cassino" e o filtro Cassino com o cassino desligado; tocar numa linha de cassino abre o `CasinoRoundsModal` | FR-012, FR-028 |
| `resources/js/components/BalanceCards/index.jsx` | Prop `cartoes_ocultos` (lista de chaves que não aparecem) | FR-012 |
| `resources/js/components/StatementFilters/index.jsx` | Opção "Cassino" (prop `com_cassino`) | FR-028 |
| `resources/js/components/StatementTable/index.jsx` | Linha de cassino clicável e coluna prêmio com o total ganho | FR-028 |

**Arquivos estáticos**: `public/images/cassino/provedores/*.png` (novos, copiados das 10 logos de
`wssports.bet/public/images/cassino/provedor`).

**Nenhum outro arquivo existente muda**. Em especial, não mudam:

- `SaldoClientes`: só chamado;
- `EscolhaCarteira`: a do cassino é um serviço próprio;
- `GestaoBonus` e a expiração de bônus: já retiram o bônus de cassino vencido;
- `MinhaContaResource`: o card some pelo frontend, com o indicador;
- `Clientes`: o login vem do id, R-12;
- `SportsBar`: já tem o item;
- `SideMenu`;
- `PublicLayout`.

**Impacto no banco local**: `php artisan migrate` (9 tabelas novas e 4 colunas com padrão em
`configuracoes`) e `php artisan db:seed --class=CassinoSeeder`. As tabelas do antigo não são
importadas: o histórico de rodadas do antigo fica no banco antigo.

### Re-check pós-design (Phase 1)

[data-model.md](data-model.md), [contracts/api.md](contracts/api.md),
[contracts/provedor.md](contracts/provedor.md) e [contracts/comandos.md](contracts/comandos.md)
foram conferidos:

- nomes em português e `snake_case`;
- tabelas com timestamps, soft delete e comentário em todas as colunas;
- enums com valores em português;
- rotas em kebab-case (exceção `/close.php` registrada);
- paginação ≤ 100;
- comandos no padrão `recurso:acao`;
- credenciais só no ambiente;
- nenhuma tarefa de teste.

**Status: aprovado**, sujeito às duas confirmações acima.

## Project Structure

### Documentation (this feature)

```text
specs/011-cassino-tbs2/
├── plan.md              # Este arquivo
├── research.md          # Phase 0
├── data-model.md        # Phase 1
├── quickstart.md        # Phase 1: validação manual
├── contracts/
│   ├── api.md           # rotas REST e páginas
│   ├── provedor.md      # chamadas ao TBS2 e callback
│   └── comandos.md      # comandos, job e agenda
├── checklists/
│   └── requirements.md
└── tasks.md             # Phase 2 (/speckit-tasks)
```

### Source Code (repository root)

```text
app/
├── Console/Commands/
│   ├── SincronizarCatalogoCassinoCommand.php            # NOVO  cassinos_jogos:sincronizar
│   └── LimparChamadasCassinoCommand.php                 # NOVO  cassinos_chamadas:limpar
├── Enums/
│   ├── EfeitoAcaoCassino.php                            # NOVO
│   ├── AparelhoCassino.php                              # NOVO
│   ├── DestaqueProvedorCassino.php                      # NOVO
│   ├── SituacaoSincronizacaoCassino.php                 # NOVO
│   ├── OrigemSincronizacaoCassino.php                   # NOVO
│   ├── ResultadoChamadaCassino.php                      # NOVO
│   └── OrigemTransacao.php, Funcao.php                  # ALTERADOS
├── Exceptions/
│   └── FalhaProvedorCassinoException.php                # NOVO  erro do openGame com o código do provedor
├── Fakes/DadosFake.php                                  # ALTERADO
├── Http/
│   ├── Controllers/
│   │   ├── CassinoController.php                        # NOVO  página Inertia /cassino e /close.php
│   │   ├── PublicoCassinoController.php                 # NOVO  início, grade e demo
│   │   ├── AreaClienteCassinoController.php             # NOVO  abrir jogo e rodadas da sessão
│   │   ├── CassinoCarteiraController.php                # NOVO  callback do provedor
│   │   ├── CassinosJogosController.php                  # NOVO
│   │   ├── CassinosJogosCanaisController.php            # NOVO
│   │   ├── CassinosProvedoresController.php             # NOVO
│   │   ├── CassinosSincronizacoesController.php         # NOVO
│   │   ├── CassinosAcoesController.php                  # NOVO
│   │   ├── CassinosChamadasController.php               # NOVO
│   │   ├── CassinosRodadasController.php                # NOVO  relatório
│   │   ├── CassinosSessoesController.php                # NOVO  histórico no provedor
│   │   ├── ConfiguracoesCassinoController.php           # NOVO
│   │   └── AreaClienteMinhaContaController.php          # ALTERADO
│   ├── Middleware/TratarRequisicoesInertia.php          # ALTERADO
│   ├── Requests/
│   │   ├── ListagemJogosCassinoRequest.php              # NOVO  grade pública
│   │   ├── ListagemCassinosJogosRequest.php             # NOVO  painel
│   │   ├── CassinosJogosRequest.php                     # NOVO
│   │   ├── CassinosJogosCanaisRequest.php               # NOVO
│   │   ├── CassinosProvedoresRequest.php                # NOVO
│   │   ├── CassinosAcoesRequest.php                     # NOVO
│   │   ├── ListagemCassinosChamadasRequest.php          # NOVO
│   │   ├── RelatorioCassinoRequest.php                  # NOVO
│   │   ├── ConfiguracoesCassinoRequest.php              # NOVO
│   │   └── ExtratoMinhaContaRequest.php                 # ALTERADO
│   └── Resources/
│       ├── JogoCassinoResource.php                      # NOVO  jogo no site
│       ├── CassinosJogosResource.php                    # NOVO  jogo com canais no painel
│       ├── CassinosProvedoresResource.php               # NOVO
│       ├── CassinosSincronizacoesResource.php           # NOVO
│       ├── CassinosAcoesResource.php                    # NOVO
│       ├── CassinosChamadasResource.php                 # NOVO
│       ├── RodadaCassinoResource.php                    # NOVO  rodada na Minha Conta
│       ├── ConfiguracoesCassinoResource.php             # NOVO
│       └── ExtratoMinhaContaResource.php                # ALTERADO
├── Jobs/
│   └── SincronizarCatalogoCassino.php                   # NOVO
├── Models/
│   ├── CassinosProvedores.php                           # NOVO
│   ├── CassinosJogos.php                                # NOVO
│   ├── CassinosJogosCanais.php                          # NOVO
│   ├── CassinosSincronizacoes.php                       # NOVO
│   ├── CassinosAcoes.php                                # NOVO
│   ├── CassinosSessoes.php                              # NOVO
│   ├── CassinosRodadas.php                              # NOVO
│   ├── CassinosRodadasRollovers.php                     # NOVO
│   ├── CassinosChamadas.php                             # NOVO
│   └── Configuracoes.php                                # ALTERADO
└── Services/
    ├── ProvedorCassino.php                              # NOVO  cliente HTTP (gamesList, openGame, gameSessionsLog), R-01
    ├── SincronizacaoCassino.php                         # NOVO  catálogo, R-02 a R-06
    ├── ListagemCassino.php                              # NOVO  início, grade, populares e cache, R-07
    ├── AberturaJogosCassino.php                         # NOVO  canal, troca de canal, sessão e demo, R-08 a R-11
    ├── AutenticacaoProvedorCassino.php                  # NOVO  hall, chave e sign, R-13
    ├── CarteiraCassino.php                              # NOVO  getBalance e writeBet, R-14
    ├── EscolhaCarteiraCassino.php                       # NOVO  carteira da sessão, R-17
    ├── CancelamentoRodadasCassino.php                   # NOVO  cancelamento e reembolso, R-19
    ├── RegistroChamadasCassino.php                      # NOVO  registro sem a chave, R-15
    ├── RelatorioCassino.php                             # NOVO  R-22
    └── RolloverClientes.php                             # ALTERADO

config/
├── cassino.php                                          # NOVO  apelidos de provedores, sufixos pré-pagos, prioridades, continente
└── services.php                                         # ALTERADO

database/migrations/                                     # 11 NOVAS (data-model.md, seção 14)
database/seeders/CassinoSeeder.php                       # NOVO
database/seeders/DatabaseSeeder.php                      # ALTERADO

public/images/cassino/provedores/                        # NOVO  10 logos do sistema antigo

resources/
├── views/close.blade.php                                # NOVO  página mínima do /close.php
└── js/
    ├── pages/Casino/index.jsx, styles.jsx               # NOVO  página do cassino (screens/casino)
    ├── components/
    │   ├── CasinoHeader/         # NOVO  menu, logo, Criar Conta, suporte
    │   ├── ProviderBar/          # NOVO  barra de provedores (Providers)
    │   ├── GameTypeBar/          # NOVO  barra de tipos (Types)
    │   ├── GameRow/              # NOVO  linha por tipo com "Veja mais" (Item)
    │   ├── GameCard/             # NOVO  cartão com Jogar/Demo (Game)
    │   ├── GameGrid/             # NOVO  grade filtrada com rolagem (Games .filter)
    │   ├── CasinoTabs/           # NOVO  barra inferior (Limpar, Promoções, Carteira, Minha conta)
    │   ├── CasinoMenu/           # NOVO  menu lateral (Esportes, Populares, Tipos, Provedores)
    │   ├── WalletPopup/          # NOVO  balão da carteira (modal/balance)
    │   ├── PromotionsModal/      # NOVO  modal Promoções (modal/bonus)
    │   ├── GameFrame/            # NOVO  jogo em moldura, voltar e tela cheia
    │   ├── CasinoUnavailable/    # NOVO  mensagem do cassino desligado
    │   ├── CasinoRoundsModal/    # NOVO  detalhe das rodadas (details_casino)
    │   └── BalanceCards/, StatementFilters/, StatementTable/   # ALTERADOS
    ├── hooks/
    │   ├── useCassino.js         # NOVO  início, filtros, paginação por rolagem
    │   └── useJogoCassino.js     # NOVO  abrir (real/demo), moldura ou página, fechar
    ├── utils/
    │   └── casino_types.js       # NOVO  rótulos dos tipos (Slots, Jogo rápido, Ao vivo...)
    └── pages/Home/index.jsx, pages/Account/index.jsx    # ALTERADOS

routes/api.php, routes/web.php, routes/console.php       # ALTERADOS
.env.example                                             # ALTERADO
docs/postman/wssports_api.postman_collection.json         # ALTERADO (regenerado)
```

**Componentes existentes reutilizados sem alteração**: `BannerCarousel`, `SearchBar`, `Footer`,
`AuthModal`, `DepositModal`, `WithdrawModal`, `Spinner`, `EmptyMessage`, `ModalCard`, `Backdrop`;
hooks `useSessao`, `useTelaMobile`, `useVoltarFecha`; utilitários `api.js`, `money.js`,
`alerts.js`.

**Structure Decision**: aplicação Laravel + React (Inertia) única, seguindo:

- **spec 007**: saldo só por `SaldoClientes`; rollover com ligação desfazível; área do cliente com
  `GarantirAcessoCliente`; acesso assistido pelo `AcessoAssistido`.
- **spec 004**: a carteira da aposta é escolhida sob trava, sem dividir o valor entre carteiras.
- **spec 003**: cliente HTTP do provedor e carga agendada com resumo.
- **spec 010**: configuração geral por rota própria com `configuracoes.editar`; permissões em grupo
  no `Funcao` com seeder próprio.

O painel continua só pela API (Postman), como nas specs anteriores. Página e componentes seguem o
Princípio III. Sem Policies, sem fakes e sem arquivos em `tests/`.

## Complexity Tracking

| Violação / custo | Por que é necessária | Alternativa mais simples rejeitada porque |
|---|---|---|
| Exclusão física em `cassinos_chamadas` (R-15) | Uma linha por chamada do provedor (cada giro): milhões de linhas por mês sem valor de negócio depois da conferência; o dinheiro está em `cassinos_rodadas` e `clientes_transacoes`, que nunca são apagados | Soft delete manteria as linhas, e a tabela cresceria sem limite; log em arquivo não pode ser filtrado pelo painel (FR-030) |
| Extrato agrupa as transações `Cassino` pela sessão (R-20) | FR-028 pede uma linha por sessão sem reescrever transações (FR-029) | `union` de transações e sessões complica a paginação e o formato da linha; transação reescrita é o erro do antigo |
| Rota `/close.php` fora do kebab-case | Endereço fixo que o provedor usa no `home_url` do jogo | Sem ela, o botão de saída do jogo leva a uma página inexistente |
| Callback público sem `auth` | O provedor não usa o login do site; a segurança é a chave do hall conferida em toda chamada (R-13) | Middleware de autenticação responderia fora do formato do provedor e sem registrar a chamada |
