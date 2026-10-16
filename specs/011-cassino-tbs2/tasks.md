---

description: "Lista de tarefas da feature Cassino integrado ao provedor TBS2"
---

# Tasks: Cassino integrado ao provedor TBS2

**Input**: Documentos de design em `specs/011-cassino-tbs2/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md),
[data-model.md](data-model.md), [contracts/api.md](contracts/api.md),
[contracts/provedor.md](contracts/provedor.md), [contracts/comandos.md](contracts/comandos.md),
[quickstart.md](quickstart.md)

**Tests**: NÃO há tarefas de teste: a constituição proíbe testes automatizados. Cada user story é
validada manualmente pelos cenários do [quickstart.md](quickstart.md). O callback é simulado no
Postman ou, num hall de teste, com um túnel.

**Organization**: tarefas agrupadas por user story, na ordem de prioridade:

| User story | Prioridade | Assunto |
|---|---|---|
| US1 | P1 | Navegar no cassino |
| US2 | P1 | Jogar com o próprio saldo |
| US3 | P2 | Rodadas na Minha Conta |
| US4 | P2 | Gerir o catálogo |
| US5 | P3 | Relatórios |

A sincronização do catálogo fica na Foundational, porque US1 depende de jogos no banco.

> ✅ **Confirmado pelo responsável em 2026-10-16** (depois do `/speckit-analyze`): a lista de arquivos
> existentes do plan.md (Princípio IV), o prefixo `cassinos_` (R-25), as diferenças visuais e as
> regras da seção "Session 2026-10-16 (analyze)" da spec. A T066 foi removida (cassino desligado
> volta a ser alerta + redirecionamento, como no antigo).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: pode rodar em paralelo (arquivos diferentes, sem dependências pendentes).
- **[Story]**: user story da tarefa (US1 a US5).
- **Caminhos**: relativos à raiz do repositório.
- **⚠️**: arquivo existente; está na tabela do [plan.md](plan.md).

## Regras que valem para TODAS as tarefas

- **Sistema antigo (Princípio VII)**:
  - a referência de comportamento e de visual são os arquivos da seção "Sistema antigo" do
    [research.md](research.md);
  - a pasta `C:\Users\wedso\OneDrive\Área de Trabalho\projetos\wssports.bet` é **somente
    leitura**.
- **Fidelidade visual (Princípio VIII)**:
  - componentes do cassino reproduzem os styled-components de `resources/js/screens/casino/styles.js`
    e dos modais antigos: cores, tamanhos, espaçamentos, breakpoints (900 px), ícones Material e
    textos;
  - a cor principal vem do tema (`usePage().props.tema`), como nos componentes já existentes.
- **Nomes (Princípio I)**:
  - backend:
    - classes em `PascalCase`;
    - métodos, variáveis e chaves em `snake_case` português;
    - métodos do framework com o nome original (`rules`, `toArray`, `handle`…);
    - casos de enum em `PascalCase` com acento e valores gravados com espaço
      (`ApostaComPrêmio = 'Aposta com prêmio'`);
    - rotas em kebab-case e comandos `recurso:acao`;
  - frontend:
    - componentes em inglês `PascalCase`;
    - props, variáveis e funções em `snake_case` português;
    - transient props `$` + `snake_case`;
    - hooks `use` + camelCase português.
- **Idioma (Princípio II)**: comentários, logs, mensagens da API e textos da página em português.
  Exceção: os códigos de erro do provedor (`fail_balance`…), que são o contrato dele.
- **Componentes (Princípio III)**: cada componente e a página em pasta própria com `index.jsx` e
  `styles.jsx`.
- **Escopo (Princípio IV)**: só os arquivos existentes marcados com ⚠️ podem mudar, e só no trecho
  descrito. Se for preciso outro, avisar antes.
- **Banco**:
  - toda coluna nova com `->comment()` e o texto exato do [data-model.md](data-model.md);
  - toda tabela nova com `timestamps()` + `softDeletes()` e model com `SoftDeletes`;
  - exclusão física só no `cassinos_chamadas:limpar` (R-15).
- **Dinheiro**:
  - só centavos inteiros (`SaldoClientes::para_centavos`/`para_decimal`) ou `bcmath`, nunca
    `float`;
  - o saldo só muda por `App\Services\SaldoClientes`.
- **Credenciais**:
  - hall, chave e URL só de `config('services.provedor_cassino')`, nunca no código, no banco, no
    log ou no repositório;
  - a `key` e o `sign` nunca são registrados.
- **Callback**: toda resposta ao provedor em HTTP 200, no formato de
  [contracts/provedor.md](contracts/provedor.md), nunca vazia.
- **Paginação**: máximo de 100 por página em toda listagem.
- **Postman (constituição)**: a tarefa que cria rota em `routes/api.php` regenera, na mesma tarefa,
  `docs/postman/wssports_api.postman_collection.json` ⚠️. A coleção substitui o arquivo e mantém
  `base_url` e `token`.
- **Legibilidade (Princípio V)**: ao concluir cada tarefa, revisar o código alterado e informar se
  houve ou não refatoração.

---

## Phase 1: Setup

**Objetivo**: configuração do provedor, regras do catálogo e logos.

- [ ] T001 ⚠️ Em `config/services.php`, criar o bloco `provedor_cassino` com comentário em português (R-01):
  - `url_api`: `env('CASSINO_URL_API', 'http://tbs2api.aslot.net/API/')`;
  - `hall`: `env('CASSINO_HALL')`;
  - `chave`: `env('CASSINO_CHAVE')`;
  - `assinatura`: `(bool) env('CASSINO_ASSINATURA', false)`;
  - `prefixo_login`: `env('CASSINO_PREFIXO_LOGIN', 'wss')`;
  - tempos limite: `tempo_limite_lista` 60, `tempo_limite_abertura` 10 e `tempo_limite_historico` 10.
- [ ] T002 [P] ⚠️ Em `.env.example`, acrescentar `CASSINO_URL_API`, `CASSINO_HALL`, `CASSINO_CHAVE`, `CASSINO_ASSINATURA=false` e `CASSINO_PREFIXO_LOGIN=wss`, sem valores de credencial e com um comentário em português.
- [ ] T003 [P] No `.env` local (não versionado), colocar as credenciais de teste do hall informadas pelo responsável (R-01). Nenhum arquivo do repositório muda. Conferir com `git status` que o `.env` continua ignorado.
- [ ] T004 [P] Criar `config/cassino.php`, com cada chave comentada em português:
  - `apelidos_provedores`: `['netentp' => 'netent', 'nolimitcity' => 'nolimit_city']`, aplicado depois da normalização, que deixa minúsculas e remove espaço, `_` e `-`; a normalização faz `micro-gaming` virar `microgaming` e `net ent` virar `netent` (R-02);
  - `sufixos_pre_pagos`: `['_sr', '_sz']`;
  - `prioridade_sufixos`: sem sufixo e `_op` 10, `_kn` 20, `_sl` e `_xg` 30, `_asia` e `_premium` 40, padrão 50; pré-pagos 90 (R-06);
  - `continente`: `'eur'`;
  - `horas_indisponibilidade_canal`: 24 (R-08);
  - `minutos_cache_inicio`: 5 (R-07);
  - `horas_sessao_saldo`: 12 (R-17);
  - `dias_retencao_recusadas`: 90 (R-15).
- [ ] T005 [P] Copiar as 10 logos de `wssports.bet/public/images/cassino/provedor/` para `public/images/cassino/provedores/`, com os nomes pelo código novo do provedor:
  - `pgsoft.png`, `spribe.png`, `pragmatic.png`, `galaxsys.png`, `novomatic.png`, `red_tiger.png`, `amatic.png` e `booming.png`, com o mesmo nome;
  - `evolution.png`, a partir de `evolution_lobby.png`;
  - `netent.png`, a partir de `NetEnt.png`.

---

## Phase 2: Foundational (pré-requisito de todas as stories)

**Objetivo**: tabelas, enums, models, permissões, configuração geral, cliente do provedor e
sincronização do catálogo.

**⚠️ CRÍTICO**: nenhuma user story começa antes desta fase.

### Migrations (data-model.md, seções 1 a 10)

- [ ] T006 Criar `database/migrations/2026_10_16_000001_create_cassinos_provedores_table.php`:
  - colunas `codigo` string(60) único, `nome` string(100), `imagem` string(255) nulo, `ativo` boolean padrão false, `ordem` unsignedSmallInteger padrão 100, `porcentagem_comissao` decimal(5,2) padrão 0 e `titulos_provedor` json;
  - índice `(ativo, ordem)`;
  - comentários do data-model §1; `timestamps()` + `softDeletes()`.
- [ ] T007 Criar `database/migrations/2026_10_16_000002_create_cassinos_jogos_table.php`, conforme o data-model §2:
  - colunas:
    - `cassinos_provedores_id` foreignId constrained;
    - `nome` string(150);
    - `nome_normalizado` string(150);
    - `tipo` string(30);
    - `imagem` string(500) nulo;
    - `aparelhos` string(20);
    - `destaque_provedor` string(20) nulo;
    - `popular` boolean padrão false;
    - `ordem` unsignedSmallInteger padrão 1000;
    - `ativo` boolean padrão true;
    - `aberturas` unsignedInteger padrão 0;
    - `removido_em` timestamp nulo;
  - índices:
    - único `(cassinos_provedores_id, nome_normalizado)`;
    - `(ativo, removido_em, tipo, ordem)`;
    - `(popular, ordem)`;
    - `aberturas`;
    - `nome`;
  - comentários do data-model §2.
- [ ] T008 Criar `database/migrations/2026_10_16_000003_create_cassinos_jogos_canais_table.php`, conforme o data-model §3:
  - colunas:
    - `cassinos_jogos_id` foreignId constrained;
    - `canal` string(60);
    - `codigo_provedor` string(30);
    - `pre_pago` boolean padrão false;
    - `bonus_primeiro` boolean padrão false;
    - `prioridade` unsignedSmallInteger padrão 50;
    - `ativo` boolean padrão true;
    - `demo` boolean padrão false;
    - `flash` boolean padrão false;
    - `indisponivel_em` timestamp nulo;
    - `motivo_indisponibilidade` string(60) nulo;
    - `removido_em` timestamp nulo;
    - `dados_provedor` json nulo;
  - índices: único `(cassinos_jogos_id, canal)`, `(canal, codigo_provedor)` e `codigo_provedor`;
  - comentários do data-model §3.
- [ ] T009 [P] Criar `database/migrations/2026_10_16_000004_create_cassinos_sincronizacoes_table.php`, conforme o data-model §4:
  - colunas:
    - `origem` string(20);
    - `usuarios_id` foreignId nulo constrained em `usuarios`;
    - `situacao` string(20);
    - `recebidos`, `jogos_novos`, `canais_novos`, `alterados`, `desativados` e `reativados`, unsignedInteger padrão 0;
    - `falhas` json nulo;
    - `erro` string(255) nulo;
    - `iniciada_em` timestamp;
    - `concluida_em` timestamp nulo;
  - índice `created_at`;
  - comentários do data-model §4.
- [ ] T010 [P] Criar `database/migrations/2026_10_16_000005_create_cassinos_acoes_table.php`:
  - colunas `acao` string(40) único, `efeito` string(30), `ativo` boolean padrão true e `descricao` string(255) nulo;
  - comentários do data-model §5.
- [ ] T011 Criar `database/migrations/2026_10_16_000006_create_cassinos_sessoes_table.php`, conforme o data-model §6:
  - colunas:
    - `clientes_id` foreignId constrained;
    - `cassinos_jogos_id` foreignId nulo constrained;
    - `cassinos_jogos_canais_id` foreignId nulo constrained;
    - `codigo_provedor` string(30);
    - `sessao_provedor` string(100) nulo;
    - `carteira` string(30) nulo;
    - `bonus_primeiro` boolean padrão false;
    - `total_apostado` e `total_ganho`, decimal(15,2) padrão 0;
    - `quantidade_rodadas` unsignedInteger padrão 0;
    - `aberta_em` timestamp;
    - `ultima_rodada_em` timestamp nulo;
  - índices: único `sessao_provedor`, `(clientes_id, aberta_em)`, `(clientes_id, ultima_rodada_em)` e `cassinos_jogos_id`;
  - comentários do data-model §6.
- [ ] T012 Criar `database/migrations/2026_10_16_000007_create_cassinos_rodadas_table.php`, conforme o data-model §7:
  - colunas:
    - `cassinos_sessoes_id` foreignId constrained;
    - `clientes_id` foreignId constrained;
    - `cassinos_acoes_id` foreignId nulo constrained;
    - `acao` string(40) nulo;
    - `efeito` string(30);
    - `trade_id` string(100) único;
    - `rodada_provedor` string(100) nulo;
    - `valor_aposta` e `valor_premio`, decimal(15,2) padrão 0;
    - `carteira` string(30);
    - `saldo_anterior` e `saldo_posterior`, decimal(15,2);
    - `clientes_transacoes_id` foreignId nulo constrained;
    - `cassinos_rodadas_id` foreignId nulo constrained em `cassinos_rodadas`;
    - `cancelada_em` timestamp nulo;
    - `dados_provedor` json nulo;
  - índices: `(cassinos_sessoes_id, id)`, `(clientes_id, created_at)` e `created_at`;
  - comentários do data-model §7.
- [ ] T013 Criar `database/migrations/2026_10_16_000008_create_cassinos_rodadas_rollovers_table.php`:
  - colunas `cassinos_rodadas_id` foreignId constrained, `clientes_rollovers_id` foreignId constrained, `valor` decimal(15,2) e `desfeito_em` timestamp nulo;
  - índices nas duas chaves;
  - comentários do data-model §8.
- [ ] T014 [P] Criar `database/migrations/2026_10_16_000009_create_cassinos_chamadas_table.php`, conforme o data-model §9:
  - colunas:
    - `comando`, `login`, `trade_id` e `sessao_provedor`, strings nulas com os tamanhos do data-model;
    - `dados` json;
    - `resposta` json;
    - `resultado` string(20);
    - `erro` string(60) nulo;
    - `ip` string(45) nulo;
    - `duracao_ms` unsignedInteger;
  - índices `(resultado, created_at)`, `(login, created_at)`, `trade_id` e `created_at`;
  - comentários do data-model §9.
- [ ] T015 [P] Criar `database/migrations/2026_10_16_000010_add_cassino_to_configuracoes_table.php`:
  - colunas `cassino_habilitado` boolean padrão false, `cassino_demo_habilitado` boolean padrão false, `data_prestacao_contas_cassino` date nulo e `dias_registro_chamadas_cassino` unsignedSmallInteger padrão 15;
  - comentários do data-model §10;
  - `down()` remove as 4.

### Enums (data-model.md, seção 11)

- [ ] T016 [P] Criar `app/Enums/EfeitoAcaoCassino.php` com os casos `Aposta`, `Prêmio`, `ApostaComPrêmio = 'Aposta com prêmio'`, `RodadaGrátis = 'Rodada grátis'`, `Cancelamento` e `Reembolso`, e métodos:
  - `debita_aposta(): bool`, verdadeiro em Aposta e Aposta com prêmio;
  - `devolve_aposta(): bool`, verdadeiro em Cancelamento e Reembolso.
- [ ] T017 [P] Criar `app/Enums/AparelhoCassino.php` com os casos `Todos`, `Celular` e `Computador` e o método estático `do_provedor(string $device): self` (0 → Computador, 1 → Celular, 2 ou outro → Todos; R-05).
- [ ] T018 [P] Criar `app/Enums/DestaqueProvedorCassino.php` com os casos `Popular` e `Novo` e o método estático `do_provedor(?string $menu): ?self` (`popular` → Popular, `new` → Novo, outro → null).
- [ ] T019 [P] Criar `app/Enums/SituacaoSincronizacaoCassino.php` (`EmAndamento = 'Em andamento'`, `Concluída`, `Falha`) e `app/Enums/OrigemSincronizacaoCassino.php` (`Agendada`, `Painel`).
- [ ] T020 [P] Criar `app/Enums/ResultadoChamadaCassino.php` com os casos `Aceita`, `Repetida`, `Recusada` e `Erro`.
- [ ] T021 [P] ⚠️ Em `app/Enums/OrigemTransacao.php`, acrescentar o caso `Cassino = 'Cassino'`, com o comentário "cassino (spec 011): movimento líquido de uma rodada; referencia_id = sessão de jogo".
- [ ] T022 [P] ⚠️ Em `app/Enums/Funcao.php`, seguindo o padrão de `PERMISSOES_ENCERRAMENTOS`:
  - constante `PERMISSOES_CASSINO = ['cassinos_jogos.gerenciar', 'cassinos_rodadas.listar']`, com docblock "Só Admin e Supervisor";
  - incluir em `permissoes_padrao`;
  - incluir no `in_array` de Admin e Supervisor do `pode_usar`;
  - atualizar o docblock do `pode_usar`.

### Models

- [ ] T023 [P] Criar `app/Models/CassinosProvedores.php`:
  - `SoftDeletes` e `$guarded = ['id']`;
  - casts: `ativo` boolean, `ordem` integer, `porcentagem_comissao` decimal:2 e `titulos_provedor` array;
  - relação `jogos()`;
  - scope `ativos()`.
- [ ] T024 [P] Criar `app/Models/CassinosJogos.php`:
  - casts: `aparelhos` → `AparelhoCassino`, `destaque_provedor` → `DestaqueProvedorCassino`, booleanos e `removido_em` datetime;
  - relações `provedor()`, `canais()` e `sessoes()`;
  - scope `visiveis(AparelhoCassino $aparelho)`, que exige `ativo`, `removido_em` nulo, provedor ativo, `aparelhos` em (Todos, $aparelho) e `whereHas('canais', abrivel)`;
  - método estático `normalizar_nome(string $nome): string`, que deixa minúsculas, remove acentos e reduz espaços duplicados.
- [ ] T025 [P] Criar `app/Models/CassinosJogosCanais.php`:
  - casts booleanos, `indisponivel_em` e `removido_em` datetime, `dados_provedor` array;
  - relação `jogo()`;
  - scope `abriveis()`: `ativo`, `flash` false, `removido_em` nulo e `indisponivel_em` nulo ou anterior a agora − `config('cassino.horas_indisponibilidade_canal')` horas;
  - scope `por_prioridade()`, que ordena por `prioridade` e depois por `id`.
- [ ] T026 [P] Criar `app/Models/CassinosSincronizacoes.php`:
  - casts: `origem` → `OrigemSincronizacaoCassino`, `situacao` → `SituacaoSincronizacaoCassino`, `falhas` array e datas;
  - relação `usuario()`.
- [ ] T027 [P] Criar `app/Models/CassinosAcoes.php`:
  - casts: `efeito` → `EfeitoAcaoCassino` e `ativo` boolean;
  - mutator que grava `acao` em maiúsculas.
- [ ] T028 [P] Criar `app/Models/CassinosSessoes.php`:
  - casts: `carteira` → `Carteira`, `bonus_primeiro` boolean, `total_apostado` e `total_ganho` decimal:2, `aberta_em` e `ultima_rodada_em` datetime;
  - relações `cliente()` (`withTrashed`), `jogo()`, `canal()` e `rodadas()`.
- [ ] T029 [P] Criar `app/Models/CassinosRodadas.php`:
  - casts: `efeito` → `EfeitoAcaoCassino`, `carteira` → `Carteira`, valores e saldos decimal:2, `cancelada_em` datetime e `dados_provedor` array;
  - relações `sessao()`, `cliente()`, `acao_cassino()`, `transacao()`, `original()` (`cassinos_rodadas_id`) e `rollovers()`.
- [ ] T030 [P] Criar `app/Models/CassinosRodadasRollovers.php`, no padrão de `ApostasRollovers`: casts `valor` decimal:2 e `desfeito_em` datetime; relações `rodada()` e `rollover()`.
- [ ] T031 [P] Criar `app/Models/CassinosChamadas.php`: casts `dados` e `resposta` array, `resultado` → `ResultadoChamadaCassino` e `duracao_ms` integer.
- [ ] T032 [P] ⚠️ Em `app/Models/Configuracoes.php`, acrescentar ao `fillable` e aos casts:
  - `cassino_habilitado` boolean;
  - `cassino_demo_habilitado` boolean;
  - `data_prestacao_contas_cassino` date;
  - `dias_registro_chamadas_cassino` integer.

  Usar um bloco comentado "cassino (spec 011)".

### Seeders

- [ ] T033 Criar `database/seeders/CassinoSeeder.php`, idempotente com `updateOrCreate`, no padrão do `EncerramentoSeeder`:
  - **Permissões**: cria `cassinos_jogos.gerenciar` e `cassinos_rodadas.listar` (guard do painel) e as dá aos usuários Admin e Supervisor existentes.
  - **Provedores**: cria em `cassinos_provedores`, ativos e com `imagem` `/images/cassino/provedores/<codigo>.png`, estes 10:

    | Código | Nome | Ordem | Comissão |
    |---|---|---|---|
    | `pgsoft` | PG Soft | 1 | 15.50 |
    | `spribe` | Spribe | 2 | 14.00 |
    | `evolution` | Evolution | 3 | 18.50 |
    | `pragmatic` | Pragmatic | 4 | 12.00 |
    | `galaxsys` | Galaxsys | 5 | 0.00 |
    | `novomatic` | Novomatic | 6 | 14.00 |
    | `red_tiger` | Red Tiger | 7 | 17.50 |
    | `amatic` | Amatic | 8 | 12.00 |
    | `booming` | Booming | 9 | 17.50 |
    | `netent` | NetEnt | 10 | 12.00 |

  - **Aviator**: `fast_games`, nome "Aviator", ordem 11, comissão 14.00, ativo e sem imagem.
  - **Ações** em `cassinos_acoes` (R-16):

    | Ação | Efeito |
    |---|---|
    | BET, SPIN, RESPIN | Aposta |
    | BET_AND_WINS | Aposta com prêmio |
    | WIN | Prêmio |
    | FREESPIN, FREERESPIN | Rodada grátis |
    | CANCEL | Cancelamento |
    | REFUND | Reembolso |

- [ ] T034 ⚠️ Em `database/seeders/DatabaseSeeder.php`, chamar `CassinoSeeder` depois do `EncerramentoSeeder`.

### Configuração geral (contracts/api.md §3.8)

- [ ] T035 [P] Criar `app/Http/Requests/ConfiguracoesCassinoRequest.php`:
  - `authorize`: `Funcao::usuario_pode($usuario, 'configuracoes.editar')`, no padrão do `ConfiguracoesEncerramentoRequest`;
  - regras (todas `sometimes`):
    - `cassino_habilitado` boolean;
    - `cassino_demo_habilitado` boolean;
    - `data_prestacao_contas_cassino` nullable date;
    - `dias_registro_chamadas_cassino` integer entre 1 e 90;
  - mensagens em português.
- [ ] T036 [P] Criar `app/Http/Resources/ConfiguracoesCassinoResource.php` com os 4 campos e `url_callback` = `url('/api/cassino/carteira')`.
- [ ] T037 Criar `app/Http/Controllers/ConfiguracoesCassinoController.php`, com `show` e `update` no padrão do `ConfiguracoesEncerramentoController`. O `update` limpa o cache do início do cassino com `Cache::forget` das chaves `cassino.inicio.celular` e `cassino.inicio.computador`.
- [ ] T038 ⚠️ Em `routes/api.php`, no grupo `auth:api` + `garantir_acesso` das configurações, criar `GET` e `PUT` `configuracoes/cassino`, com o `use` do controller e o comentário "configurações do cassino (spec 011)". Regenerar a coleção do Postman ⚠️.
- [ ] T039 [P] ⚠️ Em `app/Http/Middleware/TratarRequisicoesInertia.php`, montar `'indicadores' => [...DadosFake::indicadores(), 'cassino' => (bool) Configuracoes::atual()->cassino_habilitado]` e ajustar o comentário (FR-035).
- [ ] T040 [P] ⚠️ Em `app/Fakes/DadosFake.php`, remover `'cassino' => true` de `indicadores()` e ajustar o docblock e o tipo de retorno para `array{acumuladao: bool}`.

### Cliente do provedor e sincronização (R-01 a R-06)

- [ ] T041 [P] Criar `app/Exceptions/FalhaProvedorCassinoException.php`:
  - propriedade `codigo_provedor` (`?string`, com o `error` do provedor ou null em tempo esgotado ou falha de rede);
  - método `recusou_canal(): bool`, verdadeiro para `games_not_activ` e `hall_balance_less_100`;
  - mensagem padrão "Não foi possível abrir o jogo. Tente novamente.".
- [ ] T042 Criar `app/Services/ProvedorCassino.php`, no padrão do `ProvedorCotacoes`:
  - métodos `listar_jogos(): array` (`gamesList`), `abrir_jogo(string $login, string $codigo_provedor, bool $demo): array` (`openGame` com os campos de contracts/provedor.md §1) e `historico_sessao(string $sessao_provedor): array` (`gameSessionsLog`);
  - `Http::asJson()->timeout(...)`;
  - lança `FalhaProvedorCassinoException` quando `status` ≠ `success`, a resposta não é JSON ou dá tempo esgotado;
  - nunca registra a `key`: o log só traz o comando e o código de erro.
- [ ] T043 Criar `app/Services/SincronizacaoCassino.php` com `sincronizar(OrigemSincronizacaoCassino $origem, ?Usuarios $usuario = null): CassinosSincronizacoes` (R-02 a R-06):
  - **Abertura**: cria o registro `Em andamento`; se já houver outro `Em andamento` com menos de 30 min, devolve null sem fazer nada.
  - **Busca**: chama `ProvedorCassino::listar_jogos`. Falha ou lista vazia: registra `Falha` com `erro` e não desativa nada.
  - **Carga em memória**: lê numa só vez provedores por `codigo`, jogos por `(provedor, nome_normalizado)` e canais por `(jogo, canal)`.
  - **Provedores**: provedor novo é criado `ativo=false`, `nome` = title original, `ordem` 100, e acrescenta o title em `titulos_provedor`.
  - **Jogos**:
    - o jogo novo recebe `tipo` (`categories` ou `outros`), `imagem`, `aparelhos` e `destaque_provedor`;
    - jogo existente: atualiza `imagem`, `tipo`, `aparelhos` e `destaque_provedor` e conta `alterados` quando algo muda.
  - **Canais**:
    - canal novo:
      - `pre_pago` e `bonus_primeiro` pelo sufixo em `config('cassino.sufixos_pre_pagos')`;
      - `ativo` = !`pre_pago`;
      - `prioridade` por `prioridade_sufixos`;
    - canal existente: atualiza `codigo_provedor` quando o ID muda (recadastro, conta `alterados`), além de `demo`, `flash` e `dados_provedor`.
  - **ID duplicado**: o mesmo nome e canal com dois IDs na mesma lista fica com o maior ID; o outro vai para `falhas`.
  - **Remoção e volta**: jogos e canais que não vieram recebem `removido_em` = agora (conta `desativados`); os que voltaram ficam com `removido_em` nulo (conta `reativados`).
  - **Indisponibilidade**: limpa `indisponivel_em` e `motivo_indisponibilidade` mais antigos que `horas_indisponibilidade_canal`.
  - **Transação**: as gravações de catálogo ficam numa só transação, em lotes de `upsert` quando possível.
  - **Fim**:
    - conclui com `Concluída` e os contadores;
    - chama `ListagemCassino::limpar_cache()` (criado em T047), por enquanto com `Cache::forget` das duas chaves;
    - exceção: `Falha` com `erro` e `Log::error`, sem a chave.
- [ ] T044 Criar `app/Console/Commands/SincronizarCatalogoCassinoCommand.php`:
  - assinatura `cassinos_jogos:sincronizar {--origem=Agendada} {--usuario=}` e descrição em português;
  - imprime o resumo;
  - código de saída 0 ou 1, conforme contracts/comandos.md.
- [ ] T045 ⚠️ Em `routes/console.php`, acrescentar `Schedule::command('cassinos_jogos:sincronizar')->hourlyAt(5)->runInBackground()->withoutOverlapping(30);` com o comentário de contracts/comandos.md.
- [ ] T046 Rodar `php artisan migrate`, `php artisan db:seed --class=CassinoSeeder` e `php artisan cassinos_jogos:sincronizar`, e conferir:
  - cerca de 6.365 recebidos;
  - Fortune Tiger com um único jogo, canal `pgsoft_op_kn` (código 9946);
  - Aviator visível uma vez só (canal `aviator_op`; o do `spribe_sr` fica sem canal abrível);
  - `spribe_sr` com `pre_pago` true e `ativo` false;
  - nenhum provedor novo ativo.

  Nenhum arquivo muda.

**Checkpoint**: banco com o catálogo; configuração e permissões prontas.

---

## Phase 3: User Story 1 - Navegar no cassino (Priority: P1) 🎯 MVP

**Goal**: área `/cassino` com o visual do antigo, jogos sem repetição, filtros, busca e rolagem.

**Independent Test**: quickstart US1, itens 1 a 6.

### Backend

- [ ] T047 [US1] Criar `app/Services/ListagemCassino.php` (R-07):
  - **`inicio(AparelhoCassino $aparelho): array`**, com `Cache::remember` em `cassino.inicio.<aparelho>` por `config('cassino.minutos_cache_inicio')` minutos. Devolve:
    - `provedores`: ativos com a quantidade de jogos visíveis, ordem e nome; só os que têm jogos;
    - `tipos`: `tipo` e quantidade, por quantidade decrescente;
    - `linhas`: `populares` mais uma por tipo, com até 10 jogos cada, numa consulta por tipo com `limit 10`, ordenada por `ordem` e depois `aberturas` decrescente;
    - `demo_habilitado`.
  - **`jogos(AparelhoCassino $aparelho, array $filtros): LengthAwarePaginator`**:
    - `busca` faz `nome LIKE %busca%` e ignora provedor e tipo;
    - `provedor` e `tipo` filtram; `tipo=populares` usa a regra da linha;
    - ordena por `ordem` e depois `aberturas` decrescente;
    - por página: 50, máximo 100.
  - **`populares(AparelhoCassino $aparelho, int $limite)`**: os marcados `popular` pela `ordem` e, até completar o limite, os mais abertos.
  - **`limpar_cache(): void`**.
  - Usa o scope `visiveis` e carrega o provedor e o `demo` dos canais abríveis com eager loading, sem N+1.
  - Em T043, troca os `Cache::forget` por `limpar_cache()`.
- [ ] T048 [P] [US1] Criar `app/Http/Resources/JogoCassinoResource.php`:
  - campos `id`, `nome`, `provedor` (nome exibido), `tipo` e `imagem`;
  - `demo` = `configuracoes.cassino_demo_habilitado` e algum canal abrível com `demo`.
- [ ] T049 [P] [US1] Criar `app/Http/Requests/ListagemJogosCassinoRequest.php`:
  - regras:
    - `aparelho` required `in:celular,computador`;
    - `provedor` nullable integer exists `cassinos_provedores,id`;
    - `tipo` nullable string max:30;
    - `busca` nullable string `between:2,60`;
    - `pagina` integer min:1;
    - `por_pagina` integer `between:1,100`;
  - mensagens em português;
  - método `aparelho(): AparelhoCassino`.
- [ ] T050 [US1] Criar `app/Http/Controllers/PublicoCassinoController.php` com os métodos `inicio` (`GET /publico/cassino`) e `jogos` (`GET /publico/cassino/jogos`), conforme contracts/api.md §1:
  - com o cassino desligado, devolve 403 "Acesso ao cassino não está disponível.";
  - os jogos saem pelo `JogoCassinoResource`.
- [ ] T051 [US1] Criar `app/Http/Controllers/CassinoController.php` com o método `index`, que renderiza a página Inertia `Casino` com as props `cassino` (`habilitado`, `demo_habilitado`), `banners` (`Banners::ativos()`) e `promocoes`:
  - `promocoes` traz as vigentes, com `campanha`, `modalidade`, `tipo_ganho`, `valor`, `ganho_em`, `quantidade_indicacoes` e `termina_em`, como o `modal/bonus` antigo;
  - usar direto o scope `ClientesPromocoes::vigentes()` (sem copiar a consulta do `RegrasController` e sem alterar esse arquivo), excluindo a categoria de primeiro cadastro, como o antigo (`ganho_em != 'Primeiro cadastro'`).
- [ ] T052 [US1] ⚠️ Rotas:
  - em `routes/api.php`: `GET publico/cassino` e `GET publico/cassino/jogos`, com `throttle:120,1` e o comentário "cassino (spec 011)";
  - em `routes/web.php` ⚠️: `GET cassino` → `CassinoController@index`, `name('cassino')`;
  - regenerar a coleção do Postman ⚠️.

### Frontend

- [ ] T053 [P] [US1] Criar `resources/js/utils/casino_types.js`:
  - exporta `rotulos_tipos`, com os rótulos do antigo (`populares`: Populares; `slots`: Slots; `fast_games`: Jogo rápido; `live_dealers`: Ao vivo; `lottery`: Loterias; `arcade`: Arcade; `sport`: Esportes; `card`: Cartas; `roulette`: Roleta; `video_poker`: Video Poker) mais `crash_games`: Crash e `outros`: Outros;
  - exporta a função `rotulo_tipo(tipo)`.
- [ ] T054 [US1] Criar `resources/js/hooks/useCassino.js`:
  - carrega `GET /publico/cassino?aparelho=` (aparelho por `useTelaMobile`);
  - guarda os filtros `{ provedor, tipo, busca }`;
  - com filtro, busca `GET /publico/cassino/jogos`, página 1;
  - `carregar_mais()` pede a próxima página quando faltam 500 px para o fim, como o `handleScroll` antigo;
  - `limpar()` volta ao início;
  - expõe `carregando`, `aguardando` (o "Carregando..." do antigo), `indisponivel` (403) e o erro, via `utils/alerts.js`.
- [ ] T055 [P] [US1] Criar `resources/js/components/CasinoHeader/` (`index.jsx`, `styles.jsx`), com o `Header`, `HIcon`, `Logo` e `HButton` de `screens/casino/styles.js`:
  - ícones `menu` (prop `ao_abrir_menu`) e `support_agent`, que abre o WhatsApp de `contatos`;
  - logo do tema;
  - botão "Criar Conta" (prop `ao_criar_conta`), escondido com cliente logado.
- [ ] T056 [P] [US1] Criar `resources/js/components/CasinoSearch/`, com o visual de `screens/components/search` do antigo:
  - largura 90%, placeholder "Digite nome do jogo", ícone `sports_esports`;
  - busca no Enter com o texto não vazio (prop `ao_buscar`).
- [ ] T057 [P] [US1] Criar `resources/js/components/ProviderBar/`, com `PContainer`, `Providers`, `Provider` e `Pimage` do antigo:
  - cada provedor mostra a logo, ou o nome sem logo;
  - o selecionado usa a cor do tema (`$selecionado`);
  - mostra um `Spinner` no item carregando;
  - no celular (< 900 px), cada item ocupa 1/4 da largura, com rolagem horizontal; no computador, divide a largura;
  - props `provedores`, `selecionado`, `carregando` e `ao_escolher`.
- [ ] T058 [P] [US1] Criar `resources/js/components/GameTypeBar/`, com `TContainer`, `Types` e `Type` do antigo e as mesmas regras de largura do `ProviderBar`:
  - rótulos de `utils/casino_types.js`, sem o item `populares`;
  - props `tipos`, `selecionado`, `carregando` e `ao_escolher`.
- [ ] T059 [P] [US1] Criar `resources/js/components/GameCard/`, com `Game`, `GButtons`, `GButton`, `GImage`, `GTitle` e `GProvider` do antigo:
  - botões "Jogar" e "Demo" (este só com `jogo.demo`);
  - mostra um `Spinner` quando `abrindo === jogo.id`;
  - props `jogo`, `abrindo`, `ao_jogar` e `ao_demo`.
- [ ] T060 [US1] Criar `resources/js/components/GameRow/`, com `Item`, `Title`, `Link` e `Games className="games"` do antigo:
  - título pelo rótulo do tipo;
  - "Veja mais" com a cor do tema, exceto em `populares`;
  - lista horizontal de `GameCard`.
- [ ] T061 [US1] Criar `resources/js/components/GameGrid/`, com `Games className="filter"` e `Message` do antigo:
  - grade de `GameCard`;
  - "Carregando..." enquanto aguarda e "Nenhum jogo encontrado." sem resultado.
- [ ] T062 [P] [US1] Criar `resources/js/components/CasinoTabs/`, com `Tabs`, `Tab`, `TIcon` e `TText` do antigo, nesta ordem:
  - Limpar (`cleaning_services`);
  - Promoções (`redeem`);
  - Carteira (`account_balance_wallet`);
  - Minha conta (`account_circle`).

  Uma prop `ao_*` para cada item.
- [ ] T063 [P] [US1] Criar `resources/js/components/CasinoMenu/`, com `Menu`, `MHeader`, `MTitle`, `MIcon`, `MDivider`, `MText`, `MItem`, `Text`, `Count` e `Screen` do antigo:
  - itens:
    - "Esportes" (`sports_soccer`, link `/`), escondido quando `somente_cassino`;
    - divisores Populares (`star`), Tipos (`checklist`) e Provedores (`factory`), com as contagens;
  - abre pela esquerda (left −60% → 0) com a tela escurecida;
  - o voltar do navegador fecha (`useVoltarFecha`).
- [ ] T064 [P] [US1] Criar `resources/js/components/PromotionsModal/`, com o visual de `screens/casino/modal/bonus`:
  - título "Promoções" e ícone `confirmation_number`;
  - o texto de cada item segue o modal antigo: "Bônus para {modalidade} disponível para você no valor de {valor ou %}, para resgatar esse valor basta {primeiro depósito ou indicar N amigos} até {data}.";
  - sem itens: "Nenhuma promoção encontrada.";
  - fecha pelo voltar.
- [ ] T065 [P] [US1] Criar `resources/js/components/WalletPopup/`, com o visual de `screens/casino/modal/balance`:
  - blocos "Saldo" e "Bônus", cada um com o valor e "Meta a cumprir", vindos de `GET /area-cliente/minha-conta` (`saldos.saldo` e `saldos.bonus_cassino`);
  - botões Depositar (`account_balance_wallet`, abre o `DepositModal` existente) e Sacar (`download`, abre o `WithdrawModal` existente);
  - o conteúdo da página fica com `blur(8px)` enquanto o balão está aberto, como no antigo.
- [ ] T067 [US1] Criar `resources/js/pages/Casino/` (`index.jsx`, `styles.jsx`), montando a página com `useCassino` e o `Container`, `Loading` e `Content` do antigo:
  - **Ordem**: `CasinoHeader` → `BannerCarousel` (banners) → `CasinoSearch` → `ProviderBar` → `GameTypeBar` → linhas (`GameRow`) ou `GameGrid` com filtro → `Footer` → `CasinoTabs` → `CasinoMenu`.
  - **Filtros**: ao filtrar, rolar até a barra de provedores (offset −70).
  - **Barra inferior**:
    - "Minha conta" vai para `/minha-conta` com cliente logado; sem login, abre o `AuthModal`;
    - "Carteira" abre o `WalletPopup` com cliente logado; sem login, abre o `AuthModal`.
  - **Cassino desligado** (prop ou 403), como no antigo: `alerta_atencao('Acesso ao cassino não está disponível.')` de `utils/alerts.js` e, 3 s depois, `router.visit('/')` (R-21).
  - **Jogar e Demo**: chegam ao `GameCard` como `ao_jogar`/`ao_demo`, ligados em US2; até lá, sem login, abrem o `AuthModal`.
  - **Visitante com `?user=`**: código de indicação abre o cadastro, como no antigo.
- [ ] T068 [US1] ⚠️ Em `resources/js/pages/Home/index.jsx`:
  - tirar `'CASSINO'` de `esportes_sem_acao`, que fica vazio, e ajustar o comentário;
  - em `escolher_esporte`, `CASSINO` faz `router.visit('/cassino')`.

**Checkpoint**: US1 validada pelo quickstart (itens 1 a 6).

---

## Phase 4: User Story 2 - Jogar com o próprio saldo (Priority: P1)

**Goal**: abrir o jogo com troca de canal e responder ao provedor (`getBalance`, `writeBet`) sem
perder nem duplicar dinheiro.

**Independent Test**: quickstart US2, itens 1 a 18.

### Carteira (callback)

- [ ] T069 [P] [US2] Criar `app/Services/AutenticacaoProvedorCassino.php` com `validar(array $dados): ?string` (R-13):
  - devolve null quando a chamada é válida, ou o código `wrong_hall`, `wrong_key` ou `wrong_sign`;
  - compara `hall` e `key` com `hash_equals`;
  - com `assinatura` ligada, confere o `sign` sha256, montado pelos campos na ordem do provedor; isolar a montagem num método privado comentado "conferir a ordem com o PDF/chamada real".
- [ ] T070 [P] [US2] Criar `app/Services/RegistroChamadasCassino.php` com `registrar(array $dados, array $resposta, ResultadoChamadaCassino $resultado, ?string $ip, int $duracao_ms): void` (R-15):
  - remove `key` e `sign` dos dados;
  - preenche `comando`, `login`, `trade_id`, `sessao_provedor` e `erro`;
  - grava fora da transação do saldo;
  - falha ao gravar vira `Log::warning` e nunca exceção.
- [ ] T071 [P] [US2] Criar `app/Services/EscolhaCarteiraCassino.php`, no padrão do `EscolhaCarteira` (R-17). Recebe sempre o `Clientes` já bloqueado.
  - **`ordem(CassinosSessoes $sessao): array`**: `[Saldo, PromoçãoCassino]`, ou o inverso com `bonus_primeiro`.
  - **`escolher(Clientes $cliente, CassinosSessoes $sessao, string $valor): ?Carteira`**:
    - devolve a carteira atual da sessão, se ela cobre o valor;
    - senão, a primeira da ordem que cobre;
    - nenhuma cobre: null;
    - compara com `bccomp` sobre `getRawOriginal`.
  - **`carteira_saldo(Clientes $cliente, ?CassinosSessoes $sessao): Carteira`**: carteira usada no `getBalance` (R-17): a carteira da sessão, quando houver; senão, a primeira da `ordem` com saldo maior que zero; se nenhuma tiver, `Saldo`. Sem sessão, a ordem padrão (`[Saldo, PromoçãoCassino]`).
- [ ] T072 [P] [US2] ⚠️ Em `app/Services/RolloverClientes.php`, acrescentar dois métodos sem mexer nos existentes:
  - **`somar_cassino(CassinosRodadas $rodada): void`**:
    - mesma lógica do `somar`, com o tipo e a carteira pela `carteira` da rodada (Saldo → Depósito/Saldo; Promoção cassino → Bônus/PromoçãoCassino);
    - valor = `valor_aposta`;
    - ligações em `CassinosRodadasRollovers`.
  - **`desfazer_cassino(CassinosRodadas $rodada): void`**: mesma lógica do `desfazer`, sobre `CassinosRodadasRollovers`.

  Docblocks citando a spec 011 (FR-025, R-18).
- [ ] T073 [US2] Criar `app/Services/CancelamentoRodadasCassino.php` com `devolver(Clientes $cliente, CassinosSessoes $sessao, CassinosRodadas $nova, array $dados): void` (R-19):
  - **Rodada original**, procurada nesta ordem:
    1. por `trade_id` em `refundTradeId`/`betTradeId`/`originalTradeId`, quando vierem;
    2. pela última aposta não cancelada da sessão com o mesmo `bet`.
  - **Valor**: limitado à `valor_aposta` da original.
  - **Crédito**: `SaldoClientes::creditar` na carteira da original, com origem `Cassino` e `referencia_id` = sessão.
  - **Efeitos na original**: `RolloverClientes::desfazer_cassino(original)` e `cancelada_em`.
  - **Totais**: `total_apostado` da sessão −= valor.
  - **Sem original**:
    - Reembolso: credita na carteira da sessão (ou Saldo);
    - Cancelamento: não move saldo.
  - O efeito Reembolso nunca confere saldo.
- [ ] T074 [US2] Criar `app/Services/CarteiraCassino.php`, com os métodos públicos `saldo(array $dados): array` (`getBalance`) e `lancar(array $dados): array` (`writeBet`), que devolvem a resposta no formato de contracts/provedor.md (R-14, R-16, R-17):
  - **Cliente**: `login` sem o prefixo de `prefixo_login` ou cliente inexistente → `user_not_found`.
  - **`saldo`**: sessão mais recente do cliente com `ultima_rodada_em` ou `aberta_em` nas últimas `horas_sessao_saldo` horas; responde o `balance` da carteira dela (`carteira_saldo`).
  - **`lancar`**:
    - **Validação**: `tradeId` obrigatório; `bet` e `win` numéricos e ≥ 0 → senão `wrong_params`.
    - **Transação**:
      1. `Clientes::lockForUpdate()`;
      2. rodada com o mesmo `trade_id` → resposta igual, com `saldo_posterior` e `operationId` = id, marcada `Repetida`;
      3. sessão pelo `sessionId`; sem ela, reaproveita a sessão mais recente do mesmo cliente e `codigo_provedor` com `ultima_rodada_em` ou `aberta_em` nas últimas `horas_sessao_saldo` horas, gravando o `sessionId` recebido em `sessao_provedor`; sem nenhuma, cria (login + `gameId` → canal por `codigo_provedor`) (R-11);
      4. ação: maiúsculas → `CassinosAcoes` ativa; vazia → `Aposta com prêmio`; desconhecida → `unknown_action`;
      5. efeito de devolução → `CancelamentoRodadasCassino`;
      6. senão:
         - aposta > 0 e cliente inativo → `fail_balance`;
         - carteira por `EscolhaCarteiraCassino::escolher` (null → `fail_balance`); a carteira nova grava em `sessao.carteira`;
         - Rodada grátis ignora `bet`;
         - Prêmio com `bet` > 0 → `wrong_params`;
         - Prêmio sem aposta vai para a carteira da última aposta da sessão;
      7. líquido em centavos = prêmio − aposta; > 0 → `creditar`, < 0 → `debitar`, ambos com `OrigemTransacao::Cassino` e `referencia_id` = sessão;
      8. cria a rodada com saldo anterior e posterior da carteira;
      9. `RolloverClientes::somar_cassino` quando aposta > 0;
      10. `increment` dos totais da sessão e `ultima_rodada_em`.
    - **Exceção**: `internal_error`, com `Log::error` sem a chave.
  - **Resposta de falha**: com o login reconhecido, inclui `login`, `balance` e `currency` (`BRL`).
- [ ] T075 [US2] Criar `app/Http/Controllers/CassinoCarteiraController.php`, método `__invoke`:
  - aceita JSON ou formulário;
  - marca o início (`hrtime`) e chama `AutenticacaoProvedorCassino::validar`; com erro, responde `fail` com o código;
  - despacha pelo `cmd`: `getBalance` → `saldo`, `writeBet` → `lancar`, outro → `unknown_command`;
  - registra com `RegistroChamadasCassino`: Aceita, Repetida, Recusada ou Erro;
  - devolve `response()->json($resposta, 200)`.

### Abertura do jogo

- [ ] T076 [US2] Criar `app/Services/AberturaJogosCassino.php` (R-08, R-10, R-11, R-12):
  - **`login(Clientes $cliente): string`**: `prefixo_login` + id.
  - **`abrir(Clientes $cliente, CassinosJogos $jogo): array`**:
    - percorre `canais()->abriveis()->por_prioridade()` e chama `ProvedorCassino::abrir_jogo`;
    - quando a exceção `recusou_canal`:
      - marca `indisponivel_em` e `motivo_indisponibilidade`;
      - `hall_balance_less_100` também marca `pre_pago` e `bonus_primeiro`;
      - limpa o cache e tenta o próximo canal;
    - outra exceção: sobe como 503;
    - sem canal: `HttpException` 422 "Jogo indisponível no momento.";
    - sucesso:
      - cria `CassinosSessoes`: cliente, jogo, canal, `codigo_provedor`, `sessao_provedor` de `content.gameRes.sessionId`, `bonus_primeiro` do canal, `aberta_em`;
      - `increment('aberturas')`;
      - devolve `url`, `moldura` (`withoutFrame` ≠ 1), `sessao_id` e `jogo`.
  - **`demo(CassinosJogos $jogo): array`**:
    - exige `cassino_demo_habilitado` e um canal abrível com `demo`, senão 422 "Demo indisponível para este jogo.";
    - login `prefixo_login` + `demo`, sem sessão e sem saldo.
- [ ] T077 [US2] Criar `app/Http/Controllers/AreaClienteCassinoController.php`, método `abrir(Request, CassinosJogos $jogo)`:
  - 403 "O acesso assistido não pode abrir jogos com o dinheiro do cliente." quando `AcessoAssistido::acesso($request)` não é nulo;
  - 403 "Acesso ao cassino não está disponível." com o cassino desligado;
  - jogo não visível: 422 "Jogo indisponível no momento.";
  - chama `AberturaJogosCassino::abrir` e converte `FalhaProvedorCassinoException` em 503.
- [ ] T078 [US2] Em `app/Http/Controllers/PublicoCassinoController.php` (criado em T050), acrescentar o método `demo(CassinosJogos $jogo)`, que chama `AberturaJogosCassino::demo`.
- [ ] T079 [US2] Em `app/Http/Controllers/CassinoController.php` (criado em T051), acrescentar o método `fechar()`, que devolve a view `resources/views/close.blade.php` com `Cache-Control: no-store` (R-09). A view é nova: HTML mínimo com `<script>` que faz `parent.postMessage('closeGame', '*')` quando está em iframe e, fora dele, `location.replace('/cassino')`; textos em português.
- [ ] T080 [US2] ⚠️ Rotas:
  - em `routes/api.php`:
    - `POST cassino/carteira` → `CassinoCarteiraController`, sem auth, com `throttle:1200,1` e o comentário "callback do provedor do cassino (spec 011): URL a cadastrar no hall";
    - `POST publico/cassino/jogos/{jogo}/demo`, com `throttle:20,1`;
    - no grupo `auth:clientes` + `GarantirAcessoCliente`: `POST area-cliente/cassino/jogos/{jogo}/abrir`, com `throttle:20,1`;
  - em `routes/web.php` ⚠️: `GET close.php` → `CassinoController@fechar`, `name('cassino.fechar')`;
  - conferir que `/api/cassino/carteira` não passa por CSRF (`php artisan route:list`);
  - regenerar a coleção do Postman ⚠️, com exemplos de `getBalance`, `writeBet` SPIN e `writeBet` CANCEL.
- [ ] T081 [P] [US2] Criar `app/Console/Commands/LimparChamadasCassinoCommand.php` (`cassinos_chamadas:limpar`):
  - apaga com `forceDelete` em lotes de 5.000:
    - `Aceita`/`Repetida` mais antigas que `dias_registro_chamadas_cassino`;
    - `Recusada`/`Erro` mais antigas que `dias_retencao_recusadas`;
  - imprime as quantidades;
  - no código, um comentário justificando a exclusão física (R-15).
- [ ] T082 [US2] ⚠️ Em `routes/console.php`, acrescentar `Schedule::command('cassinos_chamadas:limpar')->dailyAt('04:00')->withoutOverlapping(30);` com o comentário de contracts/comandos.md.

### Frontend

- [ ] T083 [US2] Criar `resources/js/hooks/useJogoCassino.js` (R-09):
  - `jogar(jogo)`:
    - sem cliente logado, abre o `AuthModal`; usa a sessão de `useSessao`;
    - vendedor ou acesso assistido recebe o aviso do antigo, "Você precisa está logado como apostador.", trocado pela mensagem do 403;
    - abre uma janela em branco no toque (`window.open('', '_blank')`), chama `POST /area-cliente/cassino/jogos/{id}/abrir` e, depois:
      - com `moldura`: fecha a janela em branco e mostra o `GameFrame`;
      - sem `moldura`: aponta a janela para a `url`;
  - `demo(jogo)` usa `POST /publico/cassino/jogos/{id}/demo`;
  - expõe `abrindo` (id), `jogo_aberto` e `fechar()`;
  - guarda a rolagem do conteúdo antes de abrir e a devolve ao fechar;
  - erros via `utils/alerts.js`.
- [ ] T084 [P] [US2] Criar `resources/js/components/GameFrame/`:
  - camada fixa em tela cheia, com o `iframe` (`allow="fullscreen; autoplay"`) e uma barra com voltar (`arrow_back`) e tela cheia (`fullscreen`/`fullscreen_exit`, pela Fullscreen API);
  - fecha:
    - com `message` `closeGame`, `close` ou `GAME_MODE:LOBBY` (string ou `data.action`/`data.type`);
    - quando o iframe navega para `/close.php`;
    - pelo voltar do navegador (`useVoltarFecha`);
  - props `url`, `nome` e `ao_fechar`;
  - cores do tema.
- [ ] T085 [US2] Em `resources/js/pages/Casino/index.jsx` (criado em T067), ligar `useJogoCassino`:
  - `ao_jogar` e `ao_demo` do `GameCard`;
  - itens Populares do `CasinoMenu`;
  - `abrindo` nos cartões;
  - `GameFrame` quando `jogo_aberto`.
- [ ] T086 [US2] Validar no Postman, seguindo os itens 4 a 16 do quickstart:
  - `getBalance`;
  - `writeBet` SPIN, repetido, BET_AND_WINS, saldo insuficiente, carteiras 3+3, só bônus, `bonus_primeiro`, CANCEL e REFUND;
  - chave errada;
  - ação desconhecida;
  - duas chamadas simultâneas (dois `curl` em paralelo).

  Conferir no banco os totais da sessão, as transações `Cassino` e `cassinos_chamadas` sem `key`. Abrir a Fortune Tiger pelo site com um cliente de teste. Nenhum arquivo muda.

**Checkpoint**: US1 + US2 = cassino jogável (MVP completo).

---

## Phase 5: User Story 3 - Rodadas na Minha Conta (Priority: P2)

**Goal**: uma linha por sessão no extrato; ao tocar, as rodadas; card "Bônus cassino" e filtro
escondidos com o cassino desligado.

**Independent Test**: quickstart US3, itens 1 a 4.

- [ ] T087 [P] [US3] Criar `app/Http/Resources/RodadaCassinoResource.php` com os campos de contracts/api.md §2:
  - `id`;
  - `data` (`-03:00`);
  - `trade_id`;
  - `acao`;
  - `efeito`;
  - `carteira`;
  - `saldo_anterior`;
  - `aposta` = `valor_aposta`;
  - `premio` = `valor_premio`;
  - `cancelada` = `cancelada_em` ≠ null.
- [ ] T088 [US3] Em `app/Http/Controllers/AreaClienteCassinoController.php` (criado em T077), acrescentar `rodadas(Request, CassinosSessoes $sessao)`:
  - sessão de outro cliente → 404;
  - responde `sessao` (id, jogo, provedor e totais) e as rodadas paginadas (padrão 50, máximo 100), em ordem crescente de id;
  - funciona no acesso assistido.
- [ ] T089 [US3] ⚠️ Em `routes/api.php`, no grupo `auth:clientes` + `GarantirAcessoCliente`, criar `GET area-cliente/cassino/sessoes/{sessao}/rodadas`. Regenerar a coleção do Postman ⚠️.
- [ ] T090 [P] [US3] ⚠️ Em `app/Http/Requests/ExtratoMinhaContaRequest.php`, acrescentar `cassino` à regra `in:` de `operacao`.
- [ ] T091 [US3] ⚠️ Em `app/Http/Controllers/AreaClienteMinhaContaController.php` (R-20):
  - `ORIGENS_POR_OPERACAO` ganha `'cassino' => [OrigemTransacao::Cassino]`;
  - no `extrato`, as transações de origem `Cassino` entram só pela mais recente de cada sessão: `where(origem != Cassino OR id IN (select max(id) from clientes_transacoes where clientes_id = ? and origem = 'Cassino' group by referencia_id))`, com o query builder;
  - em `carregar_relacionados`, carrega em lote as `CassinosSessoes` (com `jogo`) das linhas `Cassino` e faz `setRelation('sessao_cassino', ...)`.
- [ ] T092 [US3] ⚠️ Em `app/Http/Resources/ExtratoMinhaContaResource.php`:
  - acrescentar a chave `cassino`, nula fora das linhas de cassino;
  - nas linhas de cassino, o bloco traz `sessao_id`, `jogo`, `total_apostado`, `total_ganho` e `quantidade_rodadas`;
  - nessas linhas, `valor` = `total_apostado` e `tipo` = `Débito` se total ganho < total apostado, senão `Crédito`.
- [ ] T093 [P] [US3] Criar `resources/js/components/CasinoRoundsModal/`, com o visual de `modals/details_casino`:
  - título "Detalhes apostas no {jogo}";
  - tabela DATA, ID TRAN., SALDO (title "Saldo anterior."), VALOR e PRÊMIO, com a carteira em texto menor abaixo do ID;
  - carrega `GET /area-cliente/cassino/sessoes/{id}/rodadas`, com "Carregar mais" quando houver mais páginas;
  - rodada cancelada aparece riscada;
  - fecha pelo voltar.
- [ ] T094 [P] [US3] ⚠️ Em `resources/js/components/StatementFilters/index.jsx`, acrescentar a opção `{ valor: 'cassino', titulo: 'Cassino' }` (como o `<Option value="Cassino">` antigo), exibida só com a prop nova `com_cassino`.
- [ ] T095 [P] [US3] ⚠️ Em `resources/js/components/StatementTable/index.jsx`:
  - linha com `item.cassino` é clicável (`$clicavel`);
  - a coluna PRÊMIO mostra `item.cassino.total_ganho`.
- [ ] T096 [P] [US3] ⚠️ Em `resources/js/components/BalanceCards/index.jsx`, aceitar a prop `cartoes_ocultos` (lista de chaves, padrão `[]`) e filtrar `cartoes` por ela.
- [ ] T097 [US3] ⚠️ Em `resources/js/pages/Account/index.jsx`:
  - ler `indicadores.cassino` de `usePage().props`;
  - passar `cartoes_ocultos={indicadores.cassino ? [] : ['bonus_cassino']}` ao `BalanceCards` e `com_cassino={indicadores.cassino}` ao `StatementFilters`;
  - no `ao_tocar_linha`, a linha com `cassino` abre o `CasinoRoundsModal` com `sessao_id` e `jogo`.

**Checkpoint**: US3 validada pelo quickstart.

---

## Phase 6: User Story 4 - Gerir o catálogo no painel (Priority: P2)

**Goal**: ativar e desativar jogos, provedores e canais; populares e ordem; sincronização sob
demanda; tipos de ação; chamadas recusadas.

**Independent Test**: quickstart US4, itens 1 a 5.

Todas as alterações desta fase chamam `ListagemCassino::limpar_cache()`. Os `authorize` usam
`Funcao::usuario_pode(..., 'cassinos_jogos.gerenciar')`, exceto as chamadas, que usam
`cassinos_rodadas.listar`.

- [ ] T098 [P] [US4] Criar `app/Http/Requests/ListagemCassinosJogosRequest.php`:
  - regras:
    - `busca` nullable string max:60;
    - `provedor` nullable exists;
    - `tipo` nullable string;
    - `ativo` e `popular` nullable boolean;
    - `removido` nullable `in:sim,nao`;
    - `sem_canal_abrivel` nullable boolean;
    - `repetidos` nullable boolean (jogos cujo `nome_normalizado` também existe em outro provedor, R-02);
    - `pagina`;
    - `por_pagina` `between:1,100`;
  - `authorize` com `cassinos_jogos.gerenciar`.
- [ ] T099 [P] [US4] Criar `app/Http/Requests/CassinosJogosRequest.php` com regras `sometimes`: `ativo` boolean, `popular` boolean e `ordem` integer entre 0 e 65535.
- [ ] T100 [P] [US4] Criar `app/Http/Resources/CassinosJogosResource.php`:
  - campos do jogo, mais `provedor` (`id` e `nome`), `aberturas` e `removido_em`;
  - `canais`, todos, com `id`, `canal`, `codigo_provedor`, `pre_pago`, `bonus_primeiro`, `prioridade`, `ativo`, `demo`, `flash`, `indisponivel_em`, `motivo_indisponibilidade` e `removido_em`.
- [ ] T101 [US4] Criar `app/Http/Controllers/CassinosJogosController.php`:
  - `index`: lista paginada (padrão 20) com eager loading de provedor e canais; o filtro `repetidos` usa um `whereIn nome_normalizado` com subconsulta agrupada (`having count(distinct cassinos_provedores_id) > 1`);
  - `update`: ao ativar um jogo cujo nome já está visível em outro provedor, a resposta traz `aviso` "Já existe um jogo com este nome em outro provedor; ele aparecerá duas vezes na página." (R-02).
- [ ] T102 [P] [US4] Criar `app/Http/Requests/CassinosJogosCanaisRequest.php` com regras `sometimes`: `ativo`, `pre_pago` e `bonus_primeiro` boolean, `prioridade` integer entre 0 e 999, `disponivel` accepted.
- [ ] T103 [US4] Criar `app/Http/Controllers/CassinosJogosCanaisController.php`, método `update`: `disponivel` limpa `indisponivel_em` e `motivo_indisponibilidade`; responde com o canal.
- [ ] T104 [P] [US4] Criar `app/Http/Requests/CassinosProvedoresRequest.php` com regras `sometimes`:
  - `nome` string 2 a 100;
  - `ativo` boolean;
  - `ordem` integer entre 0 e 65535;
  - `porcentagem_comissao` numeric entre 0 e 100, `decimal:0,2`;
  - `imagem` `file|mimes:png,jpg,jpeg,webp|max:1024`.
- [ ] T105 [P] [US4] Criar `app/Http/Resources/CassinosProvedoresResource.php` com `id`, `codigo`, `nome`, `imagem`, `ativo`, `ordem`, `porcentagem_comissao`, `titulos_provedor` e `quantidade_jogos`.
- [ ] T106 [US4] Criar `app/Http/Controllers/CassinosProvedoresController.php`:
  - `index`: paginado, com `withCount` dos jogos ativos não removidos;
  - `update`: a `imagem` é gravada por `ArmazenamentoImagens::salvar($arquivo, 'cassino/provedores')`, e a anterior removida com `remover`.
- [ ] T107 [P] [US4] Criar `app/Jobs/SincronizarCatalogoCassino.php`:
  - fila `default`, `$tries = 1`, `$timeout = 120`;
  - chama `SincronizacaoCassino::sincronizar(OrigemSincronizacaoCassino::Painel, $usuario)`.
- [ ] T108 [P] [US4] Criar `app/Http/Resources/CassinosSincronizacoesResource.php` com todos os campos da tabela e o `usuario` (nome).
- [ ] T109 [US4] Criar `app/Http/Controllers/CassinosSincronizacoesController.php`:
  - `index`: paginado, padrão 20, mais recentes primeiro;
  - `store`:
    - com outra carga `Em andamento` → 409 "Já existe uma atualização do catálogo em andamento.";
    - senão, despacha o job e responde 202.
- [ ] T110 [P] [US4] Criar `app/Http/Requests/CassinosAcoesRequest.php`:
  - regras:
    - `acao` required no store, `regex:/^[A-Z0-9_]+$/` depois de converter para maiúsculas no `prepareForValidation`, max:40, `unique:cassinos_acoes,acao` ignorando o próprio id;
    - `efeito` required, `Rule::enum(EfeitoAcaoCassino::class)`;
    - `ativo` boolean;
    - `descricao` nullable string max:255;
  - mensagens em português.
- [ ] T111 [P] [US4] Criar `app/Http/Resources/CassinosAcoesResource.php`.
- [ ] T112 [US4] Criar `app/Http/Controllers/CassinosAcoesController.php`:
  - `index` paginado;
  - `store`;
  - `update`;
  - `destroy` com soft delete.
- [ ] T113 [P] [US4] Criar `app/Http/Requests/ListagemCassinosChamadasRequest.php`:
  - regras:
    - `resultado` `Rule::enum(ResultadoChamadaCassino::class)`;
    - `comando`;
    - `login`;
    - `trade_id`;
    - `data_inicial` e `data_final` date;
    - `pagina`;
    - `por_pagina` `between:1,100`;
  - `authorize` com `cassinos_rodadas.listar`.
- [ ] T114 [P] [US4] Criar `app/Http/Resources/CassinosChamadasResource.php` com `id`, `comando`, `login`, `trade_id`, `dados`, `resposta`, `resultado`, `erro`, `ip`, `duracao_ms` e `data`.
- [ ] T115 [US4] Criar `app/Http/Controllers/CassinosChamadasController.php`, método `index`: filtros e paginação (padrão 20), mais recentes primeiro.
- [ ] T116 [US4] ⚠️ Em `routes/api.php`, no grupo `auth:api` + `garantir_acesso` e com o comentário "cassino no painel (spec 011)":
  - `GET` e `PUT cassinos-jogos/{jogo}` (`index` sem parâmetro);
  - `PUT cassinos-jogos-canais/{canal}`;
  - `GET cassinos-provedores` e `PUT cassinos-provedores/{provedor}` (o `PUT` aceita multipart; documentar o `_method` no Postman);
  - `GET` e `POST cassinos-sincronizacoes` (`POST` com `throttle:1,1`);
  - `apiResource('cassinos-acoes')`, sem `show`;
  - `GET cassinos-chamadas`.

  Regenerar a coleção do Postman ⚠️.

**Checkpoint**: US4 validada pelo quickstart.

---

## Phase 7: User Story 5 - Relatórios do cassino (Priority: P3)

**Goal**: movimento por período e agrupamento, com GGR e comissão; histórico da sessão no provedor.

**Independent Test**: quickstart US5, itens 1 a 4.

- [ ] T117 [P] [US5] Criar `app/Http/Requests/RelatorioCassinoRequest.php`:
  - regras:
    - `data_inicial` e `data_final` required date;
    - `data_final` `after_or_equal:data_inicial`, com no máximo 366 dias de período (validação no `after`);
    - `agrupar` nullable `in:jogo,provedor,canal`;
    - `provedor` nullable exists;
    - `pagina`;
    - `por_pagina` `between:1,100`;
  - `authorize` com `cassinos_rodadas.listar`.
- [ ] T118 [US5] Criar `app/Services/RelatorioCassino.php` (R-22):
  - **`gerar(array $filtros): array`**, com o query builder sobre `cassinos_rodadas`:
    - joins em `cassinos_sessoes`, `cassinos_jogos`, `cassinos_jogos_canais` e `cassinos_provedores`;
    - `created_at` entre as datas, de 00:00:00 a 23:59:59;
    - entradas = soma de `valor_aposta` dos efeitos que debitam, menos a soma de `valor_aposta` dos efeitos de devolução;
    - saídas = soma de `valor_premio`;
    - rodadas = contagem dos efeitos que não são devolução;
    - agrupado conforme `agrupar`;
    - GGR e comissão calculados em PHP com bcmath sobre as somas (comissão = GGR × `porcentagem_comissao` ÷ 100, truncado em 2 casas);
    - paginado;
  - **`resumo`**: as mesmas somas sem agrupamento, mais `ggr_desde_prestacao`, desde `configuracoes.data_prestacao_contas_cassino` (nulo quando a data não está definida);
  - sem SQL concatenado: só bindings.
- [ ] T119 [US5] Criar `app/Http/Controllers/CassinosRodadasController.php`, método `relatorio`, no formato de contracts/api.md §3.6.
- [ ] T120 [US5] Criar `app/Http/Controllers/CassinosSessoesController.php`, método `historico(CassinosSessoes $sessao)`:
  - `authorize` inline com `cassinos_rodadas.listar`;
  - chama `ProvedorCassino::historico_sessao` com o `sessao_provedor` e devolve `sessao` e `provedor`;
  - sem `sessao_provedor` → 422 "Esta sessão não tem identificação no provedor.";
  - falha → 503.
- [ ] T121 [US5] ⚠️ Em `routes/api.php`, no grupo do painel, criar `GET cassinos-rodadas/relatorio` e `GET cassinos-sessoes/{sessao}/historico`. Regenerar a coleção do Postman ⚠️.

**Checkpoint**: todas as stories funcionando.

---

## Phase 8: Polish & Cross-Cutting Concerns

- [ ] T122 Conferir a coleção do Postman ⚠️: as 22 rotas novas (pasta "Cassino"), o filtro `operacao=cassino` do extrato e o callback com exemplos; `base_url` e `token` mantidos.
- [ ] T123 Revisão de legibilidade (Princípio V) de todo o código da feature: nomes, duplicação entre `CarteiraCassino` e `CancelamentoRodadasCassino`, métodos longos no `lancar` e no `sincronizar`, e comentários. Registrar em "Notas da implementação" o que foi refatorado, ou que nada foi.
- [ ] T124 Medir o desempenho:
  - `writeBet` com `duracao_ms` em `cassinos_chamadas`, alvo < 300 ms e SC-004 < 2 s;
  - `GET /publico/cassino` com e sem cache (SC-006);
  - tempo da sincronização completa, alvo < 60 s;
  - consultas do extrato com sessões de cassino, sem N+1.

  Registrar os números nas notas.
- [ ] T125 Validar o [quickstart.md](quickstart.md) inteiro e registrar em "Notas da implementação":
  - o que foi validado;
  - o que ficou pendente de validação manual (visual em 360 px e no computador nas cores do tema, jogo real com o callback apontando para o sistema);
  - as decisões tomadas;
  - os dados de teste criados.

  Informar ao responsável a URL de callback: `https://<domínio>/api/cassino/carteira`.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sem dependências.
- **Foundational (Phase 2)**: depende do Setup e bloqueia todas as stories.
- **US1 (Phase 3)**: depende da Foundational.
- **US2 (Phase 4)**:
  - depende da Foundational;
  - no frontend, depende da página de US1 (T067);
  - o backend da carteira (T069 a T075) independe de US1.
- **US3 (Phase 5)**: depende de US2 (as rodadas precisam existir para validar); o código só precisa da Foundational.
- **US4 (Phase 6)**: depende da Foundational e do `ListagemCassino` (T047).
- **US5 (Phase 7)**: depende da Foundational; para validar, depende de rodadas de US2.
- **Polish (Phase 8)**: depois das stories desejadas.

### Dentro de cada fase

- **Ordem geral**: migrations → enums → models → seeder → serviços → requests e resources → controllers → rotas e Postman → frontend.
- **Migrations**: T006 → T007 → T008 → T011 → T012 → T013, por causa das chaves estrangeiras.
- **Carteira**: T069 a T072 → T073 → T074 → T075.
- **Abertura**: T076 → T077 e T078 → T080.
- **Arquivos editados por mais de uma tarefa**, sempre em sequência:
  - `routes/api.php`: T038, T052, T080, T089, T116 e T121;
  - `routes/console.php`: T045 e T082;
  - `PublicoCassinoController`: T050 → T078;
  - `CassinoController`: T051 → T079;
  - `AreaClienteCassinoController`: T077 → T088;
  - `pages/Casino`: T067 → T085.
- **T066**: removida depois do `/speckit-analyze` (o cassino desligado não tem componente próprio; o comportamento está na T067). Os números das demais tarefas foram mantidos.

### Parallel Opportunities

| Fase | Em paralelo |
|---|---|
| Setup | T002 a T005 |
| Foundational | Migrations T009, T010, T014 e T015; enums T016 a T022; models T023 a T032; T035, T036, T039, T040 e T041 |
| US1 | T048 e T049; T053 e os componentes T055 a T059 e T062 a T065 |
| US2 | T069 a T072; T081; T084 |
| US3 | T087, T090, T093, T094, T095 e T096 |
| US4 | Requests e resources T098 a T100, T102, T104, T105, T107, T108, T110, T111, T113 e T114 |
| US5 | T117 |

---

## Parallel Example: User Story 1

```bash
# depois do T047, em paralelo:
Task: "T048 JogoCassinoResource"
Task: "T049 ListagemJogosCassinoRequest"
Task: "T053 utils/casino_types.js"

# componentes, em paralelo:
Task: "T055 CasinoHeader"
Task: "T056 CasinoSearch"
Task: "T057 ProviderBar"
Task: "T058 GameTypeBar"
Task: "T059 GameCard"
Task: "T062 CasinoTabs"
Task: "T063 CasinoMenu"
Task: "T064 PromotionsModal"
Task: "T065 WalletPopup"
```

## Parallel Example: User Story 2

```bash
Task: "T069 AutenticacaoProvedorCassino"
Task: "T070 RegistroChamadasCassino"
Task: "T071 EscolhaCarteiraCassino"
Task: "T072 RolloverClientes::somar_cassino/desfazer_cassino"
Task: "T081 LimparChamadasCassinoCommand"
Task: "T084 GameFrame"
```

## Parallel Example: User Story 4

```bash
Task: "T098 ListagemCassinosJogosRequest"
Task: "T102 CassinosJogosCanaisRequest"
Task: "T104 CassinosProvedoresRequest"
Task: "T107 SincronizarCatalogoCassino (job)"
Task: "T110 CassinosAcoesRequest"
Task: "T113 ListagemCassinosChamadasRequest"
```

---

## Implementation Strategy

### MVP First (US1 + US2)

1. Phase 1 (Setup) e Phase 2 (Foundational): catálogo no banco.
2. Phase 3 (US1): página navegável. **Validar** pelos itens 1 a 6 do quickstart.
3. Phase 4 (US2): jogar com saldo. **Validar** pelos itens 1 a 18. Com US1 e US2, o cassino pode ir
   ao ar com o callback cadastrado.

### Entrega incremental

1. Setup + Foundational → catálogo sincronizado.
2. US1 → página.
3. US2 → jogo com dinheiro (MVP).
4. US3 → rodadas na Minha Conta.
5. US4 → gestão do catálogo pelo painel.
6. US5 → relatórios.
7. Polish → Postman, legibilidade, desempenho, quickstart completo e URL de callback.

---

## Notes

- [P] = arquivos diferentes, sem dependências pendentes.
- Commit manual pelo `/speckit-git-commit` (não commitar a cada tarefa sem pedido).
- Cada checkpoint é um ponto de validação independente pelo quickstart.
- Não alterar a URL de callback do hall de produção; testes de jogo real só num hall de teste.
