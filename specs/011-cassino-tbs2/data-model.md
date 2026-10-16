# Data Model: Cassino integrado ao provedor TBS2

**Feature**: `011-cassino-tbs2` | **Data**: 2026-10-16

## Convenções

- **Colunas padrão**: todas as tabelas novas têm `id()`, `timestamps()` e `softDeletes()`, e o
  model usa `SoftDeletes`. A exceção é `cassinos_chamadas`, que também tem `softDeletes()`, mas é
  limpa fisicamente pelo comando de retenção (research R-15).
- **Comentários**: toda coluna criada ou alterada tem `->comment()` com o texto da coluna
  **Comentário** abaixo (constituição 3.3.0).
- **Tipos**: dinheiro em `decimal(15,2)`, percentuais em `decimal(5,2)` e cálculos em `bcmath` ou
  em centavos (`SaldoClientes::para_centavos`).

## 1. `cassinos_provedores` (nova)

Provedor real dos jogos (campo `title` normalizado, R-02). Model `CassinosProvedores`.

| Coluna | Tipo | Comentário |
|---|---|---|
| `codigo` | string(60), único | Código do provedor: title do provedor normalizado (minúsculas, sem espaço, _ ou -) e unificado pelos apelidos de config/cassino.php |
| `nome` | string(100) | Nome exibido no site e no painel (editável) |
| `imagem` | string(255), nulo | Caminho da logo do provedor na barra de provedores; nulo mostra o nome |
| `ativo` | boolean, padrão false | Provedor visível no site; provedor novo entra desativado |
| `ordem` | unsignedSmallInteger, padrão 100 | Ordem na barra e no menu de provedores (menor primeiro) |
| `porcentagem_comissao` | decimal(5,2), padrão 0 | Porcentagem do GGR usada no cálculo da comissão do relatório, em % |
| `titulos_provedor` | json | Grafias do title recebidas do provedor para este código (conferência) |

**Índices**: `(ativo, ordem)`.

## 2. `cassinos_jogos` (nova)

Jogo único por provedor e nome (FR-002, R-03). Model `CassinosJogos`.

| Coluna | Tipo | Comentário |
|---|---|---|
| `cassinos_provedores_id` | foreignId → `cassinos_provedores` | Provedor real do jogo |
| `nome` | string(150) | Nome exibido do jogo (do canal de maior prioridade) |
| `nome_normalizado` | string(150) | Nome em minúsculas, sem acentos e espaços duplicados; com o provedor, identifica o jogo |
| `tipo` | string(30) | Tipo do jogo (categories do provedor): slots, live_dealers, lottery, crash_games, fast_games, sport, arcade, roulette ou outros |
| `imagem` | string(500), nulo | URL da imagem do jogo no provedor |
| `aparelhos` | string(20) | Aparelhos suportados: Todos, Celular ou Computador |
| `destaque_provedor` | string(20), nulo | Marcação do provedor (menu): Popular ou Novo; nulo sem marcação |
| `popular` | boolean, padrão false | Marcado como popular pelo administrador (linha Populares) |
| `ordem` | unsignedSmallInteger, padrão 1000 | Ordem do jogo nas linhas e na grade (menor primeiro) |
| `ativo` | boolean, padrão true | Jogo visível no site, definido pelo administrador |
| `aberturas` | unsignedInteger, padrão 0 | Quantas vezes o jogo foi aberto com dinheiro (ordena Populares) |
| `removido_em` | timestamp, nulo | Quando o jogo deixou de vir na lista do provedor; nulo enquanto vem |

**Índices**:
- único: `(cassinos_provedores_id, nome_normalizado)`;
- `(ativo, removido_em, tipo, ordem)`;
- `(popular, ordem)`;
- `aberturas`;
- `nome` (busca por `LIKE`).

**Visível no site** (FR-010):
- `ativo` e `removido_em` nulo;
- provedor ativo;
- pelo menos um canal abrível (seção 3);
- `aparelhos` em (`Todos`, aparelho de quem vê).

## 3. `cassinos_jogos_canais` (nova)

O jogo num canal (`label`) do provedor (FR-002, R-03, R-06). Model `CassinosJogosCanais`.

| Coluna | Tipo | Comentário |
|---|---|---|
| `cassinos_jogos_id` | foreignId → `cassinos_jogos` | Jogo deste canal |
| `canal` | string(60) | Canal do provedor (label), ex.: pgsoft_op_kn, spribe_sr |
| `codigo_provedor` | string(30) | Código do jogo no provedor neste canal (id do gamesList); atualizado quando o provedor recadastra o jogo |
| `pre_pago` | boolean, padrão false | Canal pré-pago, que exige crédito do hall no provedor |
| `bonus_primeiro` | boolean, padrão false | Apostas neste canal usam primeiro o bônus de cassino e, se ele não cobrir, o saldo real |
| `prioridade` | unsignedSmallInteger, padrão 50 | Ordem de tentativa na abertura do jogo (menor primeiro) |
| `ativo` | boolean, padrão true | Canal liberado pelo administrador; canal pré-pago entra desativado |
| `demo` | boolean, padrão false | O provedor oferece modo demo neste canal |
| `flash` | boolean, padrão false | O canal exige Flash (nunca abre) |
| `indisponivel_em` | timestamp, nulo | Quando o provedor recusou abrir o jogo por este canal; nulo quando disponível |
| `motivo_indisponibilidade` | string(60), nulo | Erro do provedor que tornou o canal indisponível: games_not_activ ou hall_balance_less_100 |
| `removido_em` | timestamp, nulo | Quando o canal deixou de vir na lista do provedor; nulo enquanto vem |
| `dados_provedor` | json, nulo | Campos do provedor guardados para conferência: vertical, rewriterule, exitButton, disableReload, system_name2, lines, bm |

**Índices**: único `(cassinos_jogos_id, canal)`; `(canal, codigo_provedor)`; `codigo_provedor`.

**Abrível**: `ativo`, sem `flash`, `removido_em` nulo e `indisponivel_em` nulo (ou com mais de
24 h; a sincronização limpa).

## 4. `cassinos_sincronizacoes` (nova)

Resumo de cada carga do catálogo (FR-005). Model `CassinosSincronizacoes`.

| Coluna | Tipo | Comentário |
|---|---|---|
| `origem` | string(20) | Quem disparou a carga: Agendada ou Painel |
| `usuarios_id` | foreignId → `usuarios`, nulo | Usuário do painel que pediu a carga; nulo na agendada |
| `situacao` | string(20) | Situação: Em andamento, Concluída ou Falha |
| `recebidos` | unsignedInteger, padrão 0 | Itens recebidos do provedor (um por jogo e canal) |
| `jogos_novos` | unsignedInteger, padrão 0 | Jogos criados |
| `canais_novos` | unsignedInteger, padrão 0 | Canais criados |
| `alterados` | unsignedInteger, padrão 0 | Jogos ou canais com algum dado alterado (inclusive código recadastrado) |
| `desativados` | unsignedInteger, padrão 0 | Jogos e canais que deixaram de vir na lista |
| `reativados` | unsignedInteger, padrão 0 | Jogos e canais que voltaram à lista |
| `falhas` | json, nulo | Itens recusados (sem nome, sem id, ID duplicado no mesmo canal) e o motivo |
| `erro` | string(255), nulo | Motivo da falha da carga (resposta vazia, erro do provedor, tempo esgotado) |
| `iniciada_em` | timestamp | Início da carga |
| `concluida_em` | timestamp, nulo | Fim da carga; nulo enquanto em andamento |

**Índices**: `created_at`.

## 5. `cassinos_acoes` (nova)

Tipos de ação do `writeBet` e o efeito no saldo (FR-022, R-16). Model `CassinosAcoes`.

| Coluna | Tipo | Comentário |
|---|---|---|
| `acao` | string(40), único | Valor do campo action enviado pelo provedor, em maiúsculas (ex.: SPIN, BET_AND_WINS, CANCEL) |
| `efeito` | string(30) | Efeito no saldo: Aposta, Prêmio, Aposta com prêmio, Rodada grátis, Cancelamento ou Reembolso |
| `ativo` | boolean, padrão true | Ação aceita no lançamento; desativada é recusada como desconhecida |
| `descricao` | string(255), nulo | Observação do administrador sobre a ação |

## 6. `cassinos_sessoes` (nova)

Sessão de jogo do cliente (FR-017, R-11, R-17). Model `CassinosSessoes`.

| Coluna | Tipo | Comentário |
|---|---|---|
| `clientes_id` | foreignId → `clientes` | Cliente que jogou |
| `cassinos_jogos_id` | foreignId → `cassinos_jogos`, nulo | Jogo da sessão; nulo quando a rodada chegou de um jogo fora do catálogo |
| `cassinos_jogos_canais_id` | foreignId → `cassinos_jogos_canais`, nulo | Canal usado na abertura; nulo quando desconhecido |
| `codigo_provedor` | string(30) | Código do jogo no provedor usado nesta sessão (gameId) |
| `sessao_provedor` | string(100), nulo | Identificação da sessão no provedor (sessionId) |
| `carteira` | string(30), nulo | Carteira da sessão em uso: Saldo ou Promoção cassino; nula antes da primeira aposta |
| `bonus_primeiro` | boolean, padrão false | Cópia da marcação do canal na abertura: a regra de carteira começa pelo bônus de cassino |
| `total_apostado` | decimal(15,2), padrão 0 | Soma das apostas das rodadas, menos as canceladas ou reembolsadas, em reais |
| `total_ganho` | decimal(15,2), padrão 0 | Soma dos prêmios das rodadas, em reais |
| `quantidade_rodadas` | unsignedInteger, padrão 0 | Rodadas lançadas na sessão |
| `aberta_em` | timestamp | Quando a sessão foi aberta (abertura do jogo ou primeira chamada do provedor) |
| `ultima_rodada_em` | timestamp, nulo | Quando chegou a última rodada |

**Índices**:
- único `sessao_provedor` (nulos permitidos);
- `(clientes_id, aberta_em)`;
- `(clientes_id, ultima_rodada_em)`, para o `getBalance`;
- `cassinos_jogos_id`.

**Regras**:
- os totais são incrementados na mesma transação da rodada, nunca regravados a partir da tela;
- a soma das rodadas é sempre igual aos totais (SC-003).

## 7. `cassinos_rodadas` (nova)

Cada lançamento do provedor aceito (FR-027, R-14). Model `CassinosRodadas`.

| Coluna | Tipo | Comentário |
|---|---|---|
| `cassinos_sessoes_id` | foreignId → `cassinos_sessoes` | Sessão da rodada |
| `clientes_id` | foreignId → `clientes` | Cliente da rodada |
| `cassinos_acoes_id` | foreignId → `cassinos_acoes`, nulo | Tipo de ação reconhecido; nulo quando a ação veio vazia (efeito padrão) |
| `acao` | string(40), nulo | Valor do action recebido do provedor |
| `efeito` | string(30) | Efeito aplicado: Aposta, Prêmio, Aposta com prêmio, Rodada grátis, Cancelamento ou Reembolso |
| `trade_id` | string(100), único | Identificação do lançamento no provedor (tradeId); impede lançar a mesma rodada duas vezes |
| `rodada_provedor` | string(100), nulo | Identificação da rodada no jogo, quando o provedor envia (round ou betInfo) |
| `valor_aposta` | decimal(15,2), padrão 0 | Valor apostado na rodada (bet), em reais; no cancelamento ou reembolso, o valor devolvido |
| `valor_premio` | decimal(15,2), padrão 0 | Valor ganho na rodada (win), em reais |
| `carteira` | string(30) | Carteira movimentada: Saldo ou Promoção cassino |
| `saldo_anterior` | decimal(15,2) | Saldo da carteira antes da rodada, em reais |
| `saldo_posterior` | decimal(15,2) | Saldo da carteira depois da rodada (devolvido ao provedor), em reais |
| `clientes_transacoes_id` | foreignId → `clientes_transacoes`, nulo | Movimentação de saldo da rodada (prêmio − aposta); nula quando o líquido é zero |
| `cassinos_rodadas_id` | foreignId → `cassinos_rodadas`, nulo | Aposta original cancelada ou reembolsada por esta rodada |
| `cancelada_em` | timestamp, nulo | Quando a aposta desta rodada foi cancelada ou reembolsada |
| `dados_provedor` | json, nulo | Campos extras do lançamento para conferência (matrix, WinLines, betInfo, date) |

**Índices**: único `trade_id`; `(cassinos_sessoes_id, id)`; `(clientes_id, created_at)`;
`created_at` (relatório).

**Regras**:
- nunca é editada, exceto `cancelada_em` e o vínculo de cancelamento;
- `saldo_posterior` = `saldo_anterior` + prêmio − aposta (ou + devolução).

## 8. `cassinos_rodadas_rollovers` (nova)

Quanto a aposta de uma rodada somou num rollover do cliente (R-18), no padrão de
`apostas_rollovers`. Model `CassinosRodadasRollovers`.

| Coluna | Tipo | Comentário |
|---|---|---|
| `cassinos_rodadas_id` | foreignId → `cassinos_rodadas` | Rodada que somou no rollover |
| `clientes_rollovers_id` | foreignId → `clientes_rollovers` | Rollover que recebeu o valor |
| `valor` | decimal(15,2) | Valor somado no rollover, em reais |
| `desfeito_em` | timestamp, nulo | Quando o valor foi retirado (cancelamento ou reembolso da rodada) |

**Índices**: `cassinos_rodadas_id`; `clientes_rollovers_id`.

## 9. `cassinos_chamadas` (nova)

Registro de toda chamada recebida do provedor (FR-026, R-15). Model `CassinosChamadas`.

| Coluna | Tipo | Comentário |
|---|---|---|
| `comando` | string(30), nulo | Comando recebido (cmd): getBalance, writeBet ou outro |
| `login` | string(60), nulo | Login do cliente enviado pelo provedor |
| `trade_id` | string(100), nulo | tradeId do lançamento, quando houver |
| `sessao_provedor` | string(100), nulo | sessionId recebido, quando houver |
| `dados` | json | Dados recebidos, sem os campos key e sign |
| `resposta` | json | Resposta devolvida ao provedor |
| `resultado` | string(20) | Resultado: Aceita, Repetida, Recusada ou Erro |
| `erro` | string(60), nulo | Código de erro devolvido (ex.: fail_balance, wrong_key, unknown_action) |
| `ip` | string(45), nulo | IP de origem da chamada |
| `duracao_ms` | unsignedInteger | Tempo de processamento da chamada, em milissegundos |

**Índices**: `(resultado, created_at)`; `(login, created_at)`; `trade_id`; `created_at`.

**Retenção**: `cassinos_chamadas:limpar` apaga fisicamente `Aceita`/`Repetida` com mais de
`dias_registro_chamadas_cassino` dias e `Recusada`/`Erro` com mais de 90 dias (R-15).

## 10. Alterações em tabelas existentes

### `configuracoes`

| Coluna | Tipo | Comentário |
|---|---|---|
| `cassino_habilitado` | boolean, padrão false | Cassino disponível no site (área /cassino, atalho e card Bônus cassino) |
| `cassino_demo_habilitado` | boolean, padrão false | Modo demo liberado no hall do provedor (botão Demo) |
| `data_prestacao_contas_cassino` | date, nulo | Data da última prestação de contas do cassino; o relatório mostra o GGR desde ela |
| `dias_registro_chamadas_cassino` | unsignedSmallInteger, padrão 15 | Dias em que as chamadas aceitas do provedor ficam guardadas, em dias |

Model: `fillable` e casts (`boolean`, `date`, `integer`). Não há bloqueio por GGR (FR-031), então
`max_ggr_cassino` não é trazido.

### `clientes_transacoes`

Sem migration. A origem `Cassino` passa a existir só no enum `OrigemTransacao` (a coluna `origem` é
`string(30)`, sem comentário, criada antes da regra 3.3.0, e fica como está). O `referencia_id`
aponta para `cassinos_sessoes.id` nessas transações.

## 11. Enums

| Enum | Casos (valor gravado) | Situação |
|---|---|---|
| `EfeitoAcaoCassino` | `Aposta`, `Prêmio`, `ApostaComPrêmio` (`'Aposta com prêmio'`), `RodadaGrátis` (`'Rodada grátis'`), `Cancelamento`, `Reembolso` | novo |
| `AparelhoCassino` | `Todos`, `Celular`, `Computador` | novo |
| `DestaqueProvedorCassino` | `Popular`, `Novo` | novo |
| `SituacaoSincronizacaoCassino` | `EmAndamento` (`'Em andamento'`), `Concluída`, `Falha` | novo |
| `OrigemSincronizacaoCassino` | `Agendada`, `Painel` | novo |
| `ResultadoChamadaCassino` | `Aceita`, `Repetida`, `Recusada`, `Erro` | novo |
| `OrigemTransacao` | `+ Cassino` (`'Cassino'`) | alterado |

A carteira usa o enum existente `Carteira` (`Saldo`, `PromoçãoCassino`).

## 12. Relações

- `CassinosProvedores` hasMany `CassinosJogos`.
- `CassinosJogos`:
  - belongsTo `CassinosProvedores`;
  - hasMany `CassinosJogosCanais`;
  - hasMany `CassinosSessoes`.
- `CassinosJogosCanais` belongsTo `CassinosJogos`.
- `CassinosSessoes`:
  - belongsTo `Clientes`, `CassinosJogos` e `CassinosJogosCanais`;
  - hasMany `CassinosRodadas`.
- `CassinosRodadas`:
  - belongsTo `CassinosSessoes`, `Clientes`, `CassinosAcoes` e `ClientesTransacoes`;
  - belongsTo `CassinosRodadas` (`cassinos_rodadas_id`, original cancelada);
  - hasMany `CassinosRodadasRollovers`.
- `CassinosRodadasRollovers` belongsTo `CassinosRodadas` e `ClientesRollovers`.
- `CassinosSincronizacoes` belongsTo `Usuarios`.

## 13. Valores derivados (não gravados)

| Valor | Cálculo |
|---|---|
| Login no provedor | `config('services.provedor_cassino.prefixo_login')` + `clientes.id` (R-12) |
| Canal da abertura | Primeiro canal abrível pela `prioridade` (R-08) |
| Jogo tem demo | `configuracoes.cassino_demo_habilitado` e algum canal abrível com `demo` |
| GGR | Σ `valor_aposta` das apostas − Σ devoluções − Σ `valor_premio` |
| Comissão | GGR × `porcentagem_comissao` ÷ 100 (bcmath, R-22) |

## 14. Migrations

Ordem de criação (as chaves estrangeiras respeitam a ordem):

1. `create_cassinos_provedores_table`
2. `create_cassinos_jogos_table`
3. `create_cassinos_jogos_canais_table`
4. `create_cassinos_sincronizacoes_table`
5. `create_cassinos_acoes_table`
6. `create_cassinos_sessoes_table`
7. `create_cassinos_rodadas_table`
8. `create_cassinos_rodadas_rollovers_table`
9. `create_cassinos_chamadas_table`
10. `add_cassino_to_configuracoes_table` (4 colunas)

Seeder `CassinoSeeder` (chamado pelo `DatabaseSeeder` depois do `EncerramentoSeeder`):

- permissões `cassinos_jogos.gerenciar` e `cassinos_rodadas.listar` para Admin e Supervisor;
- os 10 provedores do antigo, ativos, com imagem e porcentagem:
  - `pgsoft` 15,5;
  - `spribe` 14;
  - `evolution` 18,5;
  - `pragmatic` 12;
  - `galaxsys` 0;
  - `novomatic` 14;
  - `red_tiger` 17,5;
  - `amatic` 12;
  - `booming` 17,5;
  - `netent` 12;
- `fast_games` (nome exibido "Aviator") 14, ativo;
- as ações de R-16.
