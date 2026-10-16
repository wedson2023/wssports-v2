# Research: Cassino integrado ao provedor TBS2

**Feature**: [spec.md](spec.md) | **Plano**: [plan.md](plan.md) | **Data**: 2026-10-16

Este documento registra as decisões técnicas da spec 011. Cada uma traz a escolha, o motivo e as
alternativas rejeitadas. As decisões de negócio já fechadas na spec (Clarifications) não são
repetidas, apenas referenciadas.

## Sistema antigo (Princípio VII)

### Arquivos consultados (somente leitura)

Pasta `C:\Users\wedso\OneDrive\Área de Trabalho\projetos\wssports.bet`:

| Arquivo | O que faz |
|---|---|
| `app/Console/Commands/GamesCommand.php` (`command:cassino`) | Chama `gamesList` e grava cada item com `updateOrCreate` por `nome` + `label`; porcentagem fixa por provedor no código (pgsoft 15,5; spribe e aviator 14; evolution_lobby 18,5; pragmatic, amatic e NetEnt 12; red_tiger e booming 17,5; novomatic 14; galaxsys 0) |
| `app/Http/Controllers/CassinoController.php` | `index` (callback, comentado desde 27/03/2026), `getBalance`, `writeBet`, `bet`, `win`, `games`, `casino` (dados da página), `open` (openGame) e `relatorio` |
| `app/Models/JogosCassino.php`, `app/Models/Cassino.php` | Jogo (nome, código, provedor = label, tipo, imagem, demo, ativo, visitados, porcentagem) e rodada (tradeId, valor, prêmio, tipo, saldo anterior, transação) |
| `database/migrations/2023_06_04_*_jogos_cassinos`, `*_cassinos`, `*_sessao_cassino_creditos_transacaos`, `*_cassino_configs`, `*_prestar_contas_cassino_configs`, `*_max_ggr_cassino_configs`, `*_cassino_travas_gerentes`, `*_cassino_logs_gerentes` | Tabelas e colunas do cassino |
| `resources/js/screens/casino/index.js` e `styles.js` | Página do cassino: cabeçalho, carrossel, busca, provedores, tipos, linhas, grade filtrada com rolagem, rodapé, barra inferior e menu lateral |
| `resources/js/screens/casino/modal/balance` | Balão da carteira (Saldo e Bônus com "Meta a cumprir", Depositar e Sacar) |
| `resources/js/screens/casino/modal/bonus` | Modal Promoções |
| `resources/js/modals/game/index.js` | Abre o jogo com `window.open(url)` |
| `resources/js/modals/casino` e `modals/casino/components/thumb` | Cassino dentro do site de esportes (cartão do jogo) |
| `resources/js/screens/main/components/casinos` | Atalho do cassino na tela principal |
| `resources/js/screens/credits/index.js` e `components/casino` | Extrato com filtro "Cassino"; a linha da sessão abre o detalhe |
| `resources/js/modals/details_casino` | Detalhe das rodadas: DATA, ID TRAN., SALDO (anterior), VALOR, PRÊMIO |
| `resources/js/screens/adm/casino/index.js` | Relatório do painel: período, resumo e tabela Nome, Provedor, Tipo, Rodadas, Entradas, Saídas, Líquido |
| `routes/api.php` | `POST cassino` (callback), `GET games`, `GET cassino`, `POST abrir-jogos`, `GET cassino/relatorio` |
| `public/images/cassino/provedor/*.png` | Logos de 10 provedores (pgsoft, spribe, evolution_lobby, pragmatic, galaxsys, novomatic, red_tiger, amatic, booming, NetEnt) |

### Como funcionava

- **Catálogo**:
  - o `command:cassino` regravava a lista inteira;
  - o jogo era identificado por nome + canal, então o mesmo jogo aparecia uma vez por canal e só
    algum deles abria;
  - a página mostrava só uma lista fixa de 13 canais no código (`whereIn('provedor', [...])`).
- **Página**: o servidor devolvia tudo de uma vez e o navegador fatiava de 50 em 50. As linhas por
  tipo usavam variáveis de sessão do MySQL (`@n`, `@g`).
- **Abertura**: `openGame` com o `login` do cliente e o `exitUrl` vazio, aberto em nova janela.
- **Callback** (`POST /api/cassino`):
  - sem validar hall nem chave;
  - lista fixa de actions que debitam (BET, SPIN, RESPIN, FREESPIN, FREERESPIN, BET_AND_WINS);
    qualquer outra virava prêmio;
  - CANCEL ignorado;
  - carteira da sessão guardada em cache (`carteira_$sessionId`);
  - `sleep(1)` e cache global `double_bet` para o Aviator (gameId 12590);
  - saldo calculado em `float` e gravado sem trava;
  - erro com chave `'error '` (com espaço) e status 401;
  - exceção respondida vazia;
  - o bônus só era usado com o saldo real abaixo de R$ 0,40.
- **Extrato**: uma transação "Palpite" e uma "Prêmio" por sessão, reescritas a cada rodada com
  `updateOrCreate` somando o valor; a coluna PRÊMIO da linha ficava vazia.
- **Bloqueio**: a página e a lista recusavam acesso quando o GGR desde `prestar_contas_cassino`
  passava de `max_ggr_cassino`.
- **Relatório**: SQL concatenado com as datas do pedido, comissão fixa de 18% e `COUNT(*) / 2` como
  número de rodadas.

As diferenças aprovadas estão na spec (Clarifications, "Diferenças em relação ao sistema antigo").
Ficam fora desta spec as travas e logs de cassino por gerente (`cassino_travas_gerentes`,
`cassino_logs_gerentes`), que no antigo controlavam o acesso de cambistas e não se aplicam ao
cliente online.

## API real do provedor (levantamento de 2026-10-16)

| Achado | Consequência no plano |
|---|---|
| 6.365 jogos, 77 canais (`label`), 49 títulos (`title`) | O provedor real é o `title`; o canal é o `label` (R-02) |
| `title` com grafias diferentes para o mesmo provedor (`netent`, `NetEnt`, `net ent`, `net ent P`; `microgaming`, `micro-gaming`; `nolimit_city`, `nolimitcity`) | Código do provedor normalizado e tabela de apelidos (R-02) |
| Aviator vem com `title` `fast_games` e canal `aviator_op` | O nome exibido do provedor é editável no painel (R-02) |
| 360 jogos em mais de um canal; 18 casos de mesmo nome e canal com dois IDs na mesma lista | Jogo único e canal como opção; no empate fica o maior ID (R-03) |
| `device`: 2 em 6.362 jogos, 1 em 2, 0 em 1 | 2 = todos os aparelhos, 1 = celular, 0 = computador; conferir na implementação com os 3 jogos fora do padrão (R-05) |
| `flash` 0 em todos; `demo` 1 em 354; `menu` `popular` 18 e `new` 15; `categories` vazia em 30 | Campos usados no catálogo (R-05) |
| `withoutFrame`, `rewriterule`, `exitButton` e `disableReload` na abertura | Moldura ou página própria (R-09) |
| `openGame` demo → `games_not_activ`; canais `_sr` e `_sz` em modo real → `hall_balance_less_100` | Canal indisponível e próximo canal (R-08); demo desligado por configuração (R-10) |
| Abriram em modo real: Fortune Tiger 9946 (`pgsoft_op_kn`), Aviator 8890 (`aviator_op`), Mines 11674 (`spribe`), Sweet Bonanza 7546 (`pragmatic`) | Canais `_op`, `_kn` e sem sufixo vêm ativos |
| Resposta do `openGame` traz `content.game.url`, `content.gameRes.sessionId` e campos extras (`act`, `area`) | Sessão criada na abertura (R-11) |
| O URL do jogo usa `home_url=<domain>/close.php` | Rota `close.php` no site (R-09) |

A documentação em PDF ("GAMES API EN") não traz `label`, `categories`, `demo`, `menu`,
`system_name2`, os erros acima nem a lista de actions do `writeBet`. Vale o comportamento real,
conferido pelo registro de chamadas (R-15).

## Decisões

### R-01: Credenciais e cliente HTTP

- **Decisão**:
  - bloco `provedor_cassino` em `config/services.php`, lido do `.env`:
    - `CASSINO_URL_API` (padrão `http://tbs2api.aslot.net/API/`);
    - `CASSINO_HALL`;
    - `CASSINO_CHAVE`;
    - `CASSINO_ASSINATURA` (liga a conferência do `sign`, padrão desligado);
    - `CASSINO_PREFIXO_LOGIN` (padrão `wss`);
  - o serviço `ProvedorCassino` usa o `Http` do Laravel, no padrão do `ProvedorCotacoes`;
  - tempos limite: 60 s no `gamesList`, 10 s no `openGame` e no `gameSessionsLog`;
  - a chave nunca vai para o log nem para o registro de chamadas.
- **Por quê**: credenciais só no ambiente (spec, Clarifications) e o mesmo padrão de cliente HTTP do
  projeto.
- **Alternativas rejeitadas**:
  - Guzzle direto, como no antigo: o `Http` já é o padrão do projeto;
  - credenciais no banco: proibido pela spec.

### R-02: Provedor real e canal

- **Decisão**:
  - o provedor é o `title`, normalizado (minúsculas, sem espaço, `_` nem `-`);
  - a tabela de apelidos em `config/cassino.php` junta grafias do mesmo provedor (`netentp` →
    `netent`, `microgaming` e `micro-gaming` → `microgaming`, `nolimitcity` → `nolimit_city`);
  - `cassinos_provedores` guarda:
    - o código normalizado;
    - o nome exibido (editável);
    - a imagem;
    - se está ativo;
    - a ordem;
    - a porcentagem de comissão;
  - provedor novo entra **desativado**, exceto os 10 do sistema antigo, que o seeder cria ativos, com
    as imagens antigas e as porcentagens da spec;
  - o canal é o `label`, guardado em `cassinos_jogos_canais`.
- **Por quê**:
  - o antigo mostrava só 10 provedores; os outros 39 entrariam sem ninguém ter conferido;
  - entrar desativado mantém a página igual à antiga e deixa o administrador escolher o que liga.
- **Alternativas rejeitadas**:
  - provedor pelo `label`, como no antigo: é a causa da repetição;
  - ativar todos: 49 provedores na barra mudariam o visual e incluiriam canais sem crédito.

### R-03: Jogo único e recadastro

- **Decisão**:
  - `cassinos_jogos` é único por (`cassinos_provedores_id`, `nome_normalizado`); o nome normalizado
    é minúsculo e sem acentos nem espaços duplicados;
  - `cassinos_jogos_canais` é único por (`cassinos_jogos_id`, `canal`) e guarda o `codigo_provedor`
    (o `id` do `gamesList`);
  - jogo recadastrado (mesmo nome e canal, ID novo) atualiza o `codigo_provedor` do canal;
  - dois IDs para o mesmo nome e canal na mesma lista: fica o maior (o mais novo) e o outro é
    registrado como falha no resumo da carga;
  - rodadas antigas continuam ligadas ao canal, e o código usado fica gravado na sessão.
- **Por quê**: resolve a duplicação (FR-002, FR-003) sem perder o histórico.
- **Alternativas rejeitadas**:
  - chave pelo `id` do provedor: o ID muda e geraria jogo novo;
  - chave pelo `system_name2`: vem vazio em parte dos jogos e muda entre canais.

### R-04: Sincronização

- **Decisão**:
  - comando `cassinos_jogos:sincronizar`, agendado de hora em hora no minuto 5, com
    `withoutOverlapping`;
  - sob demanda pelo painel (`POST /cassinos-sincronizacoes`, máximo 1 por minuto) com um job;
  - tudo numa transação:
    - cria e atualiza provedores, jogos e canais;
    - marca `presente_na_ultima_carga`;
    - desativa (`removido_em`) o que não veio;
    - reativa o que voltou;
  - resposta vazia, com `status` diferente de `success` ou sem `gameList` não desativa nada e é
    registrada como falha;
  - o resumo vai para `cassinos_sincronizacoes`;
  - o cache da página é limpo no fim.
- **Por quê**: FR-001, FR-004, FR-005 e SC-008 (1 h + 5 min).
- **Alternativas rejeitadas**:
  - apagar e regravar, como no antigo: perde ordem, popular e histórico;
  - carga a cada 5 minutos: o provedor pede no máximo 1 por hora.

### R-05: Campos do catálogo

| Campo do provedor | Coluna | Regra |
|---|---|---|
| `name` | `cassinos_jogos.nome` | O nome do canal de maior prioridade |
| `categories` | `cassinos_jogos.tipo` | Vazio vira `outros` |
| `img` | `cassinos_jogos.imagem` | URL do provedor (não é baixada) |
| `device` | `cassinos_jogos.aparelhos` | 0 → `Computador`, 1 → `Celular`, 2 → `Todos` |
| `demo` | `cassinos_jogos_canais.demo` | O jogo tem demo se algum canal ativo tiver |
| `menu` | `cassinos_jogos.destaque_provedor` | `popular` → `Popular`, `new` → `Novo`, vazio → nulo |
| `flash` | `cassinos_jogos_canais.flash` | Canal com `flash = 1` nunca abre |
| `vertical`, `rewriterule`, `exitButton`, `disableReload`, `system_name2` | `cassinos_jogos_canais.dados_provedor` (JSON) | Guardados para conferência |

- **Por quê**: FR-006, FR-010 e FR-011.
- **Alternativas rejeitadas**: baixar as imagens, porque são 6 mil arquivos e o provedor já serve
  por CDN.

### R-06: Prioridade e pré-pago dos canais

- **Decisão**:
  - na criação, o canal recebe:
    - `pre_pago` = sufixo em `config('cassino.sufixos_pre_pagos')` (`_sr`, `_sz`);
    - `bonus_primeiro` = `pre_pago`;
    - `ativo` = não pré-pago;
    - `prioridade` pela ordem de `config('cassino.prioridade_sufixos')`: sem sufixo e `_op` 10,
      `_kn` 20, `_sl` e `_xg` 30, `_asia` e `_premium` 40, pré-pagos 90;
  - quando a abertura recebe `hall_balance_less_100`, o canal passa a `pre_pago` e
    `bonus_primeiro`;
  - o painel edita os quatro campos.
- **Por quê**: FR-007 e a decisão do canal "bônus primeiro" (Clarifications).
- **Alternativas rejeitadas**: decidir só pelo erro, porque o primeiro cliente receberia a recusa.

### R-07: Página do cassino (área `/cassino`)

- **Decisão**:
  - rota web `GET /cassino` (Inertia `Casino`), com props:
    - `cassino` (habilitado e demo);
    - `banners` (`Banners::ativos()`);
    - `promocoes` (promoções vigentes, como o modal antigo);
  - os jogos vêm da API pública, em JSON, para filtrar e paginar sem recarregar a página:
    - `GET /api/publico/cassino`: provedores, tipos com contagem e linhas iniciais (Populares + até
      10 por tipo);
    - `GET /api/publico/cassino/jogos`: grade filtrada, paginada (padrão 50, máximo 100);
  - o parâmetro `aparelho` (`celular` ou `computador`) vem do `useTelaMobile`;
  - a resposta de `GET /publico/cassino` fica em cache de 5 minutos por aparelho e é limpa na
    sincronização e em toda alteração no painel;
  - linhas por tipo com consulta por tipo (`limit 10`), sem variáveis de sessão do MySQL;
  - Populares: jogos marcados `popular` pela ordem e, até completar 10, os mais abertos.
- **Por quê**: SC-006 (3 s no celular) e paginação ≤ 100.
- **Alternativas rejeitadas**:
  - tudo nas props do Inertia: a grade com rolagem pediria recargas parciais com 6 mil jogos;
  - fatiar no navegador, como no antigo: carregava a lista inteira.

### R-08: Abertura e troca de canal

- **Decisão**:
  - `POST /api/area-cliente/cassino/jogos/{jogo}/abrir` (`auth:clientes` + `GarantirAcessoCliente`);
  - recusa (403) quando:
    - o token é de acesso assistido (`AcessoAssistido::acesso`);
    - o cassino está desligado;
    - o cliente está inativo;
  - o serviço `AberturaJogosCassino` percorre os canais ativos e disponíveis pela prioridade e chama
    `openGame` com:
    - `login` (R-12);
    - `gameId` = `codigo_provedor`;
    - `demo` 0;
    - `language` `pt`;
    - `domain` = `APP_URL`;
    - `exitUrl` = `APP_URL/close.php`;
    - `continent` da config;
  - quando o erro é `games_not_activ` ou `hall_balance_less_100`, marca `indisponivel_em` e
    `motivo_indisponibilidade` e tenta o próximo;
  - outros erros e tempo esgotado: "Não foi possível abrir o jogo. Tente novamente." (503), sem
    marcar o canal;
  - sem canal: 422 "Jogo indisponível no momento.";
  - a sincronização limpa as indisponibilidades com mais de 24 h, e o painel pode limpar antes.
- **Por quê**: FR-013, FR-015 e US2 (cenário 7).
- **Alternativas rejeitadas**: desativar o canal de vez, porque o hall pode receber crédito e o canal
  voltar a funcionar.

### R-09: Moldura, fechamento e `close.php`

- **Decisão**:
  - quando `withoutFrame` é 0, o jogo abre no `GameFrame`, um iframe em tela cheia com botão
    voltar e tela cheia;
  - quando é 1, abre em página própria: a janela é aberta antes da chamada (no toque) e recebe o
    URL depois, para não ser bloqueada;
  - o `GameFrame` fecha:
    - com as mensagens `closeGame`, `close` e `GAME_MODE:LOBBY` (`message` do `window`);
    - quando o iframe carrega o `/close.php`;
    - com o botão voltar do navegador (`useVoltarFecha`);
  - a rota web `GET close.php` devolve uma página mínima que envia `closeGame` ao `parent` e, fora de
    moldura, redireciona para `/cassino`;
  - ao fechar, a página volta à posição de rolagem salva.
- **Por quê**: FR-016 e o `home_url` real do provedor.
- **Alternativas rejeitadas**: sempre em nova janela, como no antigo, porque a spec pede o jogo
  dentro do site quando o provedor permite.

### R-10: Demo

- **Decisão**:
  - coluna `configuracoes.cassino_demo_habilitado` (padrão desligado, porque o hall atual responde
    `games_not_activ`);
  - `POST /api/publico/cassino/jogos/{jogo}/demo` aceita visitante, com `login` fixo
    `<prefixo>demo` e `demo` 1;
  - não cria sessão nem mexe em saldo;
  - o botão só aparece com a configuração ligada e o jogo com demo.
- **Por quê**: FR-011. Demo não usa dinheiro, então não exige login.
- **Alternativas rejeitadas**: exigir login, como no antigo: sem motivo, pois não há dinheiro.

### R-11: Sessão de jogo

- **Decisão**:
  - a abertura cria `cassinos_sessoes` com:
    - cliente;
    - jogo;
    - canal;
    - `codigo_provedor` usado;
    - `sessao_provedor` (`content.gameRes.sessionId`);
    - carteira nula;
  - o `writeBet` localiza a sessão pelo `sessionId`;
  - sessão desconhecida (lobby da Evolution, jogo aberto em outra aba) é criada na hora pelo
    `login` + `gameId` (canal pelo `codigo_provedor`; jogo fora do catálogo continua sendo
    processado, com canal nulo);
  - a contagem de aberturas do jogo (`aberturas`) sobe na abertura, não a cada rodada.
- **Por quê**: FR-017 e o caso da edge case "rodada de jogo fora do catálogo".
- **Alternativas rejeitadas**: sessão só pelo callback, porque perde o canal escolhido.

### R-12: Login do cliente no provedor

- **Decisão**:
  - `login` = `CASSINO_PREFIXO_LOGIN` + id do cliente (ex.: `wss1532`);
  - o callback extrai o id e confere o prefixo;
  - sem coluna nova em `clientes`.
- **Por quê**:
  - estável, sem dados pessoais (FR-014);
  - o callback já é autenticado pela chave do hall (R-13).
- **Alternativas rejeitadas**:
  - telefone ou CPF: dado pessoal;
  - token aleatório numa coluna nova de `clientes`: exige alterar tabela e model existentes, sem
    ganho de segurança, porque quem chama já é autenticado.

### R-13: Callback (carteira)

- **Decisão**:
  - `POST /api/cassino/carteira`, público, `throttle:1200,1`, sem sessão nem CSRF;
  - o controller `CassinoCarteiraController` só despacha pelo `cmd` (`getBalance`, `writeBet`) para
    o serviço `CarteiraCassino`;
  - toda chamada passa por `AutenticacaoProvedorCassino`, que confere:
    - `hall` = `CASSINO_HALL`;
    - `key` = `CASSINO_CHAVE` com `hash_equals`;
    - com a assinatura ligada, `sign` = sha256 dos campos na ordem do provedor;
  - respostas sempre HTTP 200 em JSON no formato do provedor:
    - sucesso: `status` `success`, `error` vazio;
    - falha: `status` `fail` e `error` (`user_not_found`, `fail_balance`, `wrong_hall`, `wrong_key`,
      `wrong_sign`, `unknown_action`, `internal_error`);
  - toda exceção vira `internal_error`, registrada no log e no registro de chamadas, nunca resposta
    vazia;
  - `cmd` desconhecido: `fail` / `unknown_command`.
- **Por quê**: FR-018, FR-019 e SC-005.
- **Alternativas rejeitadas**:
  - status 401 como no antigo: o provedor trata erro HTTP como falha de rede e repete;
  - middleware próprio para a chave: o serviço precisa responder no formato do provedor e registrar
    a chamada mesmo quando a chave falha.

### R-14: Lançamento da rodada (`writeBet`)

- **Decisão**:
  - tudo numa transação, com `Clientes::lockForUpdate()` na linha do cliente, o que serializa as
    chamadas simultâneas (Aviator com duas apostas) sem `sleep` nem cache;
  - ordem dentro da transação:
    1. idempotência: `cassinos_rodadas.trade_id` é único; se já existe, devolve a mesma resposta a
       partir da rodada (saldo posterior e `operationId` = id da rodada), sem mover nada;
    2. tipo de ação pela tabela `cassinos_acoes` (R-16); desconhecido → `unknown_action`;
    3. carteira (R-17) e conferência do saldo contra o valor da aposta;
    4. movimento único: líquido = prêmio − aposta; positivo → `SaldoClientes::creditar`, negativo →
       `debitar`, zero → sem transação; origem `Cassino`, `referencia_id` = sessão;
    5. rollover da aposta (R-18);
    6. grava a rodada com carteira, saldo antes e depois, transação e valores;
    7. atualiza os totais da sessão (`total_apostado`, `total_ganho`, `quantidade_rodadas`) com
       incremento atômico;
  - valores em centavos (`SaldoClientes::para_centavos`), nunca `float`;
  - cliente desativado: aposta recusada (`fail_balance`), prêmio e reembolso aceitos.
- **Por quê**:
  - FR-021 e FR-029: cada rodada move o saldo uma vez, e os totais vêm das rodadas;
  - a sessão nunca é reescrita como movimentação de saldo.
- **Alternativas rejeitadas**:
  - duas transações por rodada (aposta e prêmio): dobra o volume de `clientes_transacoes` sem
    informação nova, porque a rodada já guarda os dois valores;
  - uma transação por sessão reescrita, como no antigo: proibido (FR-029).

### R-15: Registro das chamadas

- **Decisão**:
  - `cassinos_chamadas` guarda:
    - comando;
    - login;
    - `trade_id`;
    - os dados recebidos sem `key` e `sign`;
    - a resposta;
    - o resultado (`Aceita`, `Repetida`, `Recusada`, `Erro`);
    - o IP;
    - a duração em ms;
  - gravada depois da resposta montada, fora da transação do saldo, para que a falha do registro
    nunca desfaça a rodada;
  - o comando `cassinos_chamadas:limpar` (diário, 04:00) apaga **fisicamente** as chamadas
    `Aceita` e `Repetida` com mais de `dias_registro_chamadas_cassino` (padrão 15); as `Recusada` e
    `Erro` ficam 90 dias.
- **Por quê**:
  - FR-026 e a conferência dos formatos reais;
  - exclusão física justificada: é um log técnico de alto volume (uma linha por giro), sem valor
    de negócio depois da conferência; o dinheiro fica em `cassinos_rodadas` e
    `clientes_transacoes`, que nunca são apagados.
- **Alternativas rejeitadas**:
  - log em arquivo: não dá para filtrar pelo painel (FR-030);
  - soft delete: a tabela cresceria sem limite.

### R-16: Tipos de ação

- **Decisão**:
  - tabela `cassinos_acoes` (`acao` única, maiúscula; `efeito`; `ativo`) editável no painel;
  - efeitos (enum `EfeitoAcaoCassino`):

| Efeito | Saldo |
|---|---|
| `Aposta` | Debita `bet` (e credita `win`, se vier) |
| `Prêmio` | Credita `win`; `bet` maior que zero é recusado |
| `Aposta com prêmio` | Debita `bet` e credita `win` num movimento só |
| `Rodada grátis` | Ignora `bet` e credita `win` |
| `Cancelamento` | Devolve `bet` (R-19), confere a sessão |
| `Reembolso` | Devolve `bet` (R-19) sem conferir saldo |

  - o seeder cria:
    - BET, SPIN e RESPIN → `Aposta`;
    - BET_AND_WINS → `Aposta com prêmio`;
    - WIN → `Prêmio`;
    - FREESPIN e FREERESPIN → `Rodada grátis`;
    - CANCEL → `Cancelamento`;
    - REFUND → `Reembolso`;
  - ação vazia ou ausente usa `Aposta com prêmio`, comportamento padrão do PDF;
  - action desconhecida é recusada e aparece no painel (`GET /cassinos-chamadas?resultado=Recusada`)
    para ser cadastrada.
- **Por quê**: FR-022 e a reclamação das "várias adaptações" no código.
- **Alternativas rejeitadas**: deduzir o efeito só pelos valores (`bet`/`win`), porque cancelamento e
  reembolso trazem `bet` e seriam debitados de novo.

### R-17: Carteira da sessão

- **Decisão**:
  - regra da spec (FR-024), num método `escolher` do serviço `EscolhaCarteiraCassino`, no padrão
    do `EscolhaCarteira` das apostas:
    - ordem: `[Saldo, PromoçãoCassino]`, ou o inverso se o canal da sessão for `bonus_primeiro`;
    - se a carteira atual da sessão cobre a aposta, usa ela;
    - senão, a primeira da ordem que cobre;
    - nenhuma cobre: `fail_balance`;
    - quando muda, grava a carteira nova na sessão;
  - o prêmio vai para a carteira da rodada da aposta:
    - no `Aposta com prêmio`, a mesma rodada;
    - no `Prêmio` separado, a carteira da última aposta da sessão;
    - sem aposta na sessão, a carteira da sessão ou, se nula, `Saldo`;
  - `getBalance` (que só traz o `login`) responde o saldo da carteira da sessão mais recente do
    cliente aberta nas últimas 12 h; sem sessão, ou sessão sem carteira, responde o saldo da
    carteira que a regra escolheria;
  - o bônus de cassino vencido no meio da sessão já foi retirado pela expiração de bônus, então a
    regra passa naturalmente ao saldo real.
- **Por quê**: FR-020 e FR-024, decididos no clarify.
- **Alternativas rejeitadas**: somar as carteiras no `getBalance`, porque o jogo mostraria saldo que
  uma aposta não consegue usar.

### R-18: Rollover

- **Decisão**:
  - `RolloverClientes` ganha `somar_cassino(CassinosRodadas)` e `desfazer_cassino(CassinosRodadas)`:
    - mesma lógica de `somar`/`desfazer`;
    - carteira `Saldo` → rollover `Depósito`;
    - `PromoçãoCassino` → `Bônus` de cassino;
  - a ligação fica em `cassinos_rodadas_rollovers`, criada só quando há rollover pendente;
  - o cancelamento ou reembolso desfaz exatamente o que a aposta cancelada somou;
  - as regras de valor mínimo e máximo do bônus de esportes não se aplicam ao cassino.
- **Por quê**: FR-025, no mesmo padrão de `apostas_rollovers`.
- **Alternativas rejeitadas**: somar sem ligação, porque não daria para desfazer no cancelamento.

### R-19: Cancelamento e reembolso

- **Decisão**:
  - a rodada original é localizada nesta ordem:
    1. pelo `trade_id` informado no cancelamento (campos `refundTradeId`/`betTradeId` quando vierem,
       conferidos no registro de chamadas);
    2. pela última aposta da sessão ainda não cancelada com o mesmo valor;
  - devolve o `bet` recebido, limitado ao valor da aposta original, à carteira dela;
  - desfaz o rollover dela e a marca `cancelada_em`;
  - a própria chamada vira uma rodada (efeito `Cancelamento` ou `Reembolso`), idempotente pelo
    `tradeId` dela;
  - sem rodada original: o reembolso credita o `bet` na carteira da sessão (sem conferir saldo); o
    cancelamento é aceito sem mover saldo e fica registrado para conferência.
- **Por quê**: FR-023 e US2 (cenário 5). O formato exato do cancelamento não está documentado, por
  isso a busca em duas etapas e o registro (R-15).
- **Alternativas rejeitadas**: ignorar o CANCEL, como no antigo: o cliente perdia a aposta.

### R-20: Extrato com uma linha por sessão

- **Decisão**:
  - nova origem `OrigemTransacao::Cassino` (`'Cassino'`), com `referencia_id` = id da sessão;
  - no extrato, as transações `Cassino` aparecem só pela mais recente de cada sessão
    (`whereIn id (select max(id) … group by referencia_id)`), e a linha traz os totais da sessão:
    - `operacao` `Cassino`;
    - `valor` = total apostado;
    - prêmio = total ganho;
    - o nome do jogo;
  - novo filtro `operacao=cassino`;
  - tocar na linha abre `GET /area-cliente/cassino/sessoes/{sessao}/rodadas`, paginado (padrão 50,
    máximo 100), com:
    - data;
    - `trade_id`;
    - ação;
    - saldo antes;
    - aposta;
    - prêmio;
    - carteira.
- **Por quê**:
  - FR-028 e SC-007 (2 toques);
  - a paginação continua sendo a do extrato, sem `union`.
- **Limite aceito**: sessão em que nenhuma rodada moveu saldo (toda aposta igual ao prêmio) não tem
  transação e não aparece no extrato; as rodadas continuam no relatório do painel.
- **Alternativas rejeitadas**:
  - `union` de transações e sessões: duas formas de linha na mesma página e contagem mais cara;
  - lista separada só do cassino: o antigo mostrava as sessões no extrato geral.

### R-21: Cassino desativado

- **Decisão**:
  - coluna `configuracoes.cassino_habilitado` (padrão desligado);
  - o indicador `indicadores.cassino` passa a vir dela (sai do `DadosFake`, FR-035);
  - com o cassino desligado:
    - a página `/cassino` mostra "Acesso ao cassino não está disponível." e o link para os esportes;
    - a barra de esportes esconde "Cassino";
    - a Minha Conta esconde o card "Bônus cassino" e o filtro Cassino;
    - a abertura é recusada;
  - o callback continua respondendo as sessões abertas (edge case da spec);
  - o atalho "Cassino" da tela principal passa a navegar para `/cassino`.
- **Por quê**: FR-012 e FR-035.
- **Alternativas rejeitadas**: esconder pelo `somente_cassino`: é outra regra (sistema só de cassino).

### R-22: Relatórios

- **Decisão**:
  - `GET /cassinos-rodadas/relatorio` com `data_inicial`, `data_final`, `agrupar` (`jogo`,
    `provedor`, `canal`), `pagina` e `por_pagina` (padrão 20, máximo 100);
  - devolve o `resumo` e os `itens`, cada um com:
    - rodadas;
    - entradas (apostado);
    - saídas (ganho);
    - GGR;
    - comissão = GGR × porcentagem do provedor ÷ 100, com bcmath sobre as somas;
  - a soma é feita com o query builder agrupado, sem SQL concatenado;
  - o resumo traz também o GGR desde `data_prestacao_contas_cassino`;
  - cancelamentos e reembolsos entram como saída negativa (descontam da entrada).
- **Por quê**: FR-032 e US5.
- **Alternativas rejeitadas**: tabela de totais por dia: desnecessária no volume atual e exigiria
  outro processo.

### R-23: Histórico no provedor

- **Decisão**: `GET /cassinos-sessoes/{sessao}/historico` chama `gameSessionsLog` com o
  `sessao_provedor` (ou login + período) e devolve o conteúdo do provedor, sem gravar.
- **Por quê**: FR-033.
- **Alternativas rejeitadas**: copiar o log para o banco: o registro de chamadas já guarda o que
  chegou.

### R-24: Permissões e configuração

- **Decisão**:
  - permissões novas no `Funcao`, grupo `PERMISSOES_CASSINO`, só de Admin e Supervisor, com o
    seeder `CassinoSeeder` no padrão do `EncerramentoSeeder`:
    - `cassinos_jogos.gerenciar`: jogos, provedores, canais, tipos de ação e sincronização;
    - `cassinos_rodadas.listar`: relatório, histórico e chamadas;
  - configuração geral em `GET/PUT /configuracoes/cassino` com `configuracoes.editar`, no padrão de
    `configuracoes/encerramento`. Campos:
    - `cassino_habilitado`;
    - `cassino_demo_habilitado`;
    - `data_prestacao_contas_cassino`;
    - `dias_registro_chamadas_cassino`.
- **Por quê**: FR-031 e FR-034.
- **Alternativas rejeitadas**: reaproveitar `configuracoes.editar` para o catálogo: o catálogo mexe
  no que o cliente vê e merece permissão própria (FR-034).

### R-25: Nomes das tabelas

- **Decisão**: todas as tabelas novas usam o prefixo `cassinos_` (área do cassino), para ficarem
  listadas juntas:
  - `cassinos_provedores`;
  - `cassinos_jogos`;
  - `cassinos_jogos_canais`;
  - `cassinos_sincronizacoes`;
  - `cassinos_acoes`;
  - `cassinos_sessoes`;
  - `cassinos_rodadas`;
  - `cassinos_rodadas_rollovers`;
  - `cassinos_chamadas`.
- **Por quê**:
  - não existe uma tabela `cassinos`;
  - o prefixo segue a ideia da regra de prefixos, em que o recurso principal é a área do cassino;
  - `cassinos_jogos_canais` e `cassinos_rodadas_rollovers` aplicam o prefixo da tabela a que se
    ligam.
- **Alternativas rejeitadas**:
  - `clientes_cassinos_sessoes`: separaria sessões e rodadas do restante do cassino;
  - manter os nomes antigos (`jogos_cassinos`, `cassinos`): não seguem a regra de prefixo.
- **A confirmar com o responsável**: o uso de `cassinos_` como prefixo sem uma tabela `cassinos`
  (Princípio VI). Ver plan.md, Constitution Check.

## Dependências novas (constituição, Stack e Restrições Técnicas)

Nenhuma. O cliente HTTP, o cache, as filas e o agendador já existem. O iframe e o carrossel usam
componentes do projeto (`BannerCarousel`).

## Dados fake (Princípio IX)

- **Removido**: `DadosFake::indicadores()['cassino']` passa a vir de
  `configuracoes.cassino_habilitado` (FR-035). O `acumuladao` continua fake até a spec própria.
- **Nenhum fake novo**.

## Pontos a conferir na implementação

| Ponto | Como conferir |
|---|---|
| Significado de `device` 0 e 1 | Abrir os 3 jogos fora do padrão no celular e no computador |
| Campos do `writeBet` de cancelamento e reembolso | Registro de chamadas (R-15) com o callback apontando para o sistema |
| Ordem dos campos do `sign` | PDF + chamada real com a assinatura ligada no hall |
| Ações reais além das semeadas | `GET /cassinos-chamadas?resultado=Recusada` |
