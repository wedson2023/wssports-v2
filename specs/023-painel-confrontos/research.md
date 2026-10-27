# Research: Painel — Confrontos

**Feature**: `023-painel-confrontos` | **Date**: 2026-10-27

## Sistema antigo consultado (Princípio VII)

Repositório `wssports.bet`, `main` `408cf730` (somente leitura):

- `app/Http/Controllers/ConfrontosController.php`:
  - `index`: abas `todos` (casa, fora, campeonato, `horario_partida` no fuso do cabeçalho
    `timezone`, `limite_apostas`, `aovivo` por subconsulta em `confrontos_ao_vivos`; `periodo`
    TODOS / PRÉ JOGO / AO VIVO por `HAVING`; pesquisa com `orWhere` em casa, fora e campeonato, que
    escapava do período; 100 por página), `pendentes` (confronto ativo e "Aguardando", bilhete Ativo
    e "Aguardando", início entre 7 dias atrás e 2 horas atrás; `minutos_atraso`; quantidade de
    bilhetes) e `favoritos` (palpites agrupados por jogo no período, do Gerente só da rede).
  - `habilitar`: Admin e Supervisor invertiam `ativo` do confronto e do ao vivo; o Gerente criava ou
    apagava um registro em `confrontos_gerencias` (desativação só para a rede dele).
  - `alterarLimiteApostas`: gravava `limite_apostas` (global) sem validar o valor; Gerente e
    Supervisor com trava também gravavam.
  - `update` (placar): placar, 1º e 2º tempo e escanteios **obrigatórios**; recusava quando
    `placar_casa + placar_fora` ≠ soma dos gols dos tempos ("A soma dos gols dos times não bate com
    os gols dos tempos."); valia para **qualquer** jogo; marcava "Encerrado" e chamava a
    `OperacaoController::operacao` (apuração).
- `app/Console/Commands/ResultadosCommand.php`: a carga sobrescrevia placar, tempos, escanteios e
  situação de todo jogo que o provedor enviasse, inclusive os lançados à mão (o provedor vence).
- `app/Http/Controllers/BilhetesController.php` `destaquesModalidade`: palpites do jogo agrupados
  por `modalidade`, contando bilhetes Ativos de usuários ativos (do Gerente, só da rede), do mais
  para o menos apostado.
- Telas: `resources/js/screens/adm/matches` ("Lista de Jogos - total N", `/adm/lista-de-jogos`,
  abas todos/favoritos/pendentes, campo de limite + botão gravar na linha, lápis de cotações,
  cadeado), `resources/js/screens/adm/modals/scoreboard` (placar), `resources/js/screens/adm/featured-bet`
  (`/adm/destaques-modalidade/:id`), `resources/js/screens/adm/championships` ("Campeonatos - total
  N", quantidade de jogos, favoritar e cadeado; o lápis de cotações não fazia nada).

Não existia no antigo: cartões de resumo, gaveta do jogo, apostas do jogo, cadastro manual de
campeonatos e jogos (nasceu na spec 003), consulta de dias passados, atualização automática.

## Código atual do v2 consultado

- Backend (spec 003, 009, 016, 018, 019): `ConfrontosController` (index com `busca`, `dia`
  hoje/amanhã/depois, `ativo`, `manual`, `situacao`, `campeonatos_id`; show; store/update/destroy só
  manuais; `alterar_situacao`), `ConfrontosAoVivoController`, `CampeonatosController`,
  `GerenciarNaoPermitidos` (+ os 3 controllers de não permitidos), `ConfrontosLimitesController`
  (pré-jogo e ao vivo, `confrontos.alterar_limite`), `ConfrontosResultadosController` (só jogos
  manuais, `confrontos.lancar_resultado`), `ConfrontosResultadosRequest`, `ImportacaoResultados`
  (`gravar_resultado`, assinatura SHA-256; a carga casa por `codigo_externo`, sem olhar a origem),
  `RegrasExibicao` (`consulta_pre_jogo`, `consulta_ao_vivo`, `situacoes_ao_vivo`),
  `CalculoCotacoes::ajustar` + `Publico::visitante`, `AlcanceHierarquia`
  (`nao_permitidos_do_usuario`, `ids_donos_visiveis`), `ListagemApostas` (`aplicar_alcance`,
  privado), `Funcao.php` (`PERMISSOES_CONFRONTOS`, `PERMISSOES_CONFRONTOS_GERENTE`, `pode_usar`),
  migration `2026_10_24_000003_criar_permissao_restaurar_apostas` (padrão de migration de
  permissão).
- Tabelas: `confrontos` (índice `situacao, esporte, data_inicio`), `confrontos_ao_vivo`
  (`confrontos_id`, placar, minuto, situacao, `ultima_atualizacao_em`, limite), `campeonatos`,
  `confrontos_resultados`, `confrontos_gols`, `apostas` (`situacao`, `resultado`, `valor`,
  `premio`, `usuarios_id`, `clientes_id`), `apostas_palpites` (`confrontos_id` com índice,
  `codigo_cotacao`, `confrontos_jogadores_id`, `situacao`, `resultado`).
- Frontend: `PanelAffiliates` + `useAfiliadosPainel` (padrão de página mais recente), `PanelShell`,
  `PanelHeader`, `PanelTabs`, `StatCard`, `ActiveFilters`, `ActionsMenu`, `Pagination`,
  `ModalHeader`, `ModalCard`, `ConfirmModal`, `DecimalField`, `EmptyMessage`, `AffiliateDrawer`
  (gaveta com abas), `PanelQuotes` (lê só `?aba=`; abre o editor com `{ id, nome }`),
  `QuoteEditorModal`, `useNomesCotacoes` (`GET /cotacoes/nomes`), `utils/quotes.js`
  (`grupos_mercado`), `PanelBets` (busca por código completo acha a aposta em qualquer data).

## Decisões

### R-01 — Uma página com quatro abas

- **Decision**: página `PanelMatches` (`/painel/confrontos`) com as abas Jogos, Mais apostados,
  Pendentes e Campeonatos (`PanelTabs`), hook `useConfrontosPainel` com aba, filtros e página na
  URL, gaveta `MatchDrawer`.
- **Rationale**: mesmo padrão de `PanelAffiliates`/`PanelBonus`; as abas Jogos e Mais apostados
  compartilham os filtros (FR-020).
- **Alternatives**: telas separadas para Campeonatos (como o antigo) — descartado: a spec juntou na
  mesma tela (Q3 B).

### R-02 — Listagem do painel no `ConfrontosController@index`, com serviço próprio

- **Decision**: a consulta do painel sai do controller para um serviço novo
  `ListagemConfrontosPainel` (filtros, dia, tipo, marcas de rede, ao vivo e limites), usado pela
  lista, pelos cartões e pela aba Mais apostados. O `index` continua na mesma rota
  (`GET /confrontos`), com filtros novos e campos novos no resource; os filtros antigos continuam
  aceitos.
- **Rationale**: a consulta é compartilhada por três rotas (FR-004, FR-005, FR-019); o padrão de
  serviço de listagem é o de `ListagemAfiliados` e `ListagemApostas`.
- **Alternatives**: rota nova `GET /painel/confrontos` — descartado: duplicaria a rota da spec 003,
  que não tem outro consumidor além do painel.

### R-03 — Dia: de 30 dias atrás até depois de amanhã

- **Decision**: `dia` aceita `ontem`, `hoje`, `amanha`, `depois_de_amanha`; parâmetro novo `data`
  (`Y-m-d`), entre hoje −30 e hoje +2 no fuso informado (`fuso_horario`, padrão `-03:00`), com
  "A data deve estar entre os últimos 30 dias e depois de amanhã.". `data` vence `dia`. Sem nenhum
  dos dois, a lista não filtra por dia (comportamento atual, mantido para o Postman); a tela sempre
  envia o dia. Índice novo em `confrontos.data_inicio` (o índice atual começa por `situacao`).
- **Rationale**: Clarifications 2026-10-27 (Q2 do clarify); a listagem pública não muda (ela usa
  `ListagemConfrontos`, outro serviço).

### R-04 — Tipo Todos / Pré-jogo / Ao vivo e o selo

- **Decision**: "ao vivo" = existe `confrontos_ao_vivo` do jogo, não excluído, com `situacao` em
  `RegrasExibicao::situacoes_ao_vivo()` e `ultima_atualizacao_em` dentro de
  `minutos_permanencia_ao_vivo` (mesma regra da `consulta_ao_vivo`, sem o minuto limite nem os não
  permitidos, que são do público). `tipo=ao_vivo` filtra os que têm; `tipo=pre_jogo`, os que não
  têm. O resource ganha `ao_vivo: { id, minuto, cronometro, situacao, placar_casa, placar_fora,
  limite_valor_apostado } | null`.
- **Rationale**: o antigo contava `confrontos_ao_vivos` não encerrados; a regra do v2 é a de
  permanência.

### R-05 — Busca também pelo campeonato

- **Decision**: `busca` passa a procurar em `time_casa`, `time_fora` **e** no nome do campeonato,
  agrupada num `where` (não escapa dos demais filtros, defeito do antigo). Os curingas `%`, `_` e `\`
  continuam escapados.

### R-06 — Desativar para todos e para a rede

- **Decision**:
  - Para todos: `PATCH /confrontos/{id}/situacao` (já existe; `ativo=false` tira o jogo do pré-jogo e
    do ao vivo, porque a `consulta_ao_vivo` exige `co.ativo`). Campeonato:
    `PATCH /campeonatos/{id}/situacao`.
  - Para a rede (Gerente): `POST /confrontos-nao-permitidos` com `alvo=Vendedores` e
    `usuarios_id` = o próprio, e, com a permissão do ao vivo, `POST
    /confrontos-ao-vivo-nao-permitidos` igual; reativar = `DELETE` dos registros. Campeonato:
    `POST/DELETE /campeonatos-nao-permitidos`. As rotas já existem.
  - O resource ganha `restricoes`: `nao_permitido_proprio` (`{ pre_jogo_id, ao_vivo_id }` dos
    registros com dono no próprio usuário, para o Gerente reativar), `redes_desativadas` (quantidade
    de donos distintos com alvo Vendedores, só para Admin e Supervisor) e o `nao_permitido` atual
    (vale para quem consulta, inclusive o de um superior).
- **Rationale**: FR-010, FR-011, FR-026, FR-027; substitui `confrontos_gerencias` sem tabela nova.
- **Alternatives**: rota única "desativar para a minha rede" — descartado: duplicaria as rotas de
  não permitidos e as regras de alcance delas (`garantir_alvo`).

### R-07 — Limite no modal, sem rota nova

- **Decision**: o resource ganha `limite_valor_apostado` (pré-jogo) e o do ao vivo dentro de
  `ao_vivo`; o modal chama as duas rotas de limite que já existem (só a do ao vivo quando houver).
- **Rationale**: FR-013; as rotas da spec 003 já validam (maior que zero, duas casas).

### R-08 — Cartões

- **Decision**: rota nova `GET /confrontos/resumo` com os filtros da lista (menos `situacao` e
  página): `jogos` (quantidade do filtro), `ao_vivo` (quantidade ao vivo agora, sem o filtro de dia),
  `desativados` (do filtro; para quem não é Admin nem Supervisor, conta também os desativados para a
  rede dele) e `pendentes` (só para quem pode usar `confrontos.lancar_resultado`; `null` para os
  demais).
- **Rationale**: FR-004; mesma forma de `GET /afiliados/resumo`.

### R-09 — Alcance das apostas

- **Decision**: `ListagemApostas::aplicar_alcance` passa a ser público (sem mudar a regra) e é usado
  por Mais apostados, Apostas do jogo e Pendentes: Admin, todas; Supervisor, as dos vendedores da
  rede e as dos clientes da rede; Gerente, as dos vendedores da rede (sem as do site); só apostas
  `Ativa` e palpites `Ativo`.
- **Rationale**: FR-002 e FR-021; a regra é a mesma da tela Apostas (spec 018/019), então os números
  batem com ela (SC-003).

### R-10 — Mais apostados (aba e ranking)

- **Decision**:
  - Aba: `GET /confrontos/mais-apostados` com os filtros da lista; cada item é o do
    `ConfrontosResource` (com `acoes`, para o mesmo menu da aba Jogos) mais, por jogo, `apostas_simples`,
    `apostas_multiplas`, `valor_simples`, `valor_multiplas`, `retorno_simples`,
    `retorno_multiplas` e `quantidade` (soma), ordenado por `quantidade` desc e `data_inicio`; só
    jogos com pelo menos uma aposta na rede.
  - Ranking da gaveta: `GET /confrontos/{id}/mais-apostados` agrupado por `codigo_cotacao` +
    `confrontos_jogadores_id`, com as mesmas colunas; o nome vem de `useNomesCotacoes` (código) e do
    jogador (nome), como no cupom. `GET /cotacoes/nomes` exige hoje uma permissão de porcentagem;
    passa a aceitar também `confrontos.listar`, para quem vê esta tela sem editar cotações.
  - Simples = aposta com um palpite ativo; múltipla = mais de um. A múltipla conta com o `valor` e o
    `premio` inteiros da aposta (Q4 do clarify). Valores somados no SQL (`DECIMAL`) e devolvidos como
    texto com 2 casas.
- **Rationale**: FR-016 e FR-019.

### R-11 — Apostas do jogo

- **Decision**: `GET /confrontos/{id}/apostas` (padrão 30, máximo 100): código, `created_at`, vendedor
  ou cliente (nome), tipo (simples ou "Múltipla (N jogos)"), opção do palpite deste jogo, valor,
  possível retorno, resultado; ordenado do mais novo para o mais antigo. O atalho abre
  `/painel/apostas?busca=CODIGO` (a busca por código completo já acha a aposta em qualquer data).
  Exige `confrontos.listar`; o atalho aparece com `apostas.listar`.
- **Rationale**: FR-017, sem mexer na tela Apostas.

### R-12 — Pendentes e lançamento do placar (Admin, como o antigo)

- **Decision**:
  - `GET /confrontos/pendentes` (`confrontos.lancar_resultado`): confronto ativo, `situacao`
    Aguardando, `data_inicio` entre agora −7 dias e agora −2 horas, com pelo menos um palpite
    `Ativo` + `resultado` Aguardando de aposta `Ativa`; colunas `atraso_minutos`, `apostas` e
    `valor_em_aberto`; ordenado por atraso desc.
  - `PUT /confrontos/{id}/resultado`: deixa de recusar jogos do provedor; `gols_primeiro_tempo_*`,
    `gols_segundo_tempo_*` e `escanteios_*` passam a obrigatórios; regra nova da soma
    (`placar_casa + placar_fora` = soma dos quatro campos dos tempos), com a mensagem do antigo no
    campo `placar_casa`.
  - Provedor vence: nada a fazer — a carga casa por `codigo_externo` e regrava quando a assinatura
    muda (`ImportacaoResultados::gravar_resultado`), reapurando com origem "Carga de resultados".
  - `confrontos.lancar_resultado` só do Admin: `Funcao::pode_usar` devolve `false` para Supervisor
    (como `gateway_pagamento.vincular`) e uma migration retira a permissão dos Supervisores que já a
    têm.
- **Rationale**: resposta do responsável no `/speckit-plan` ("apenas admin lança o resultado e é
  para deixar como no sistema anterior"); muda a spec 009 (FR-045, FR-046).
- **Alternatives**: travar o resultado manual contra a carga — descartado: o antigo deixava o
  provedor vencer.

### R-21 — Apostas em aberto na lista e lançamento só depois do início

- **Decision**: a lista (`com_detalhes`) traz `apostas_em_aberto` (quantidade de apostas `Ativa`
  distintas com palpite `Ativo` + `resultado` Aguardando no jogo, na rede de quem consulta, pelo
  mesmo alcance de R-09), usada para mostrar o aviso da desativação só quando for maior que zero.
  `acoes.lancar_resultado` exige `data_inicio <= agora`, e `PUT /confrontos/{id}/resultado` recusa
  jogo que não começou ("O resultado só pode ser lançado depois do início do jogo.", campo
  `confronto`).
- **Rationale**: `/speckit-analyze` (F2 e F3), recomendações aceitas pelo responsável.

### R-13 — Gaveta do jogo

- **Decision**: `GET /confrontos/{id}` ganha `resultado` (com gols e `origem` = origem da última
  operação de apuração do jogo, ou "Provedor"/"Manual" pelo `confrontos.manual` quando não houver),
  `ao_vivo`, `restricoes`, `limite_valor_apostado`, campeonato com `ativo`, e `cotacoes_site`
  (`odd1`, `odd2`, `odd3` como o visitante vê, por `CalculoCotacoes::ajustar(Publico::visitante())`).
  As abas Mais apostados e Apostas usam R-10 e R-11.
- **Rationale**: FR-015 e FR-018; o visitante é o público de referência do site (a prévia por
  público continua na tela Cotações).

### R-14 — Campeonatos

- **Decision**: `GET /campeonatos` ganha `quantidade_jogos` (`withCount` dos confrontos ativos,
  Aguardando e com início no futuro), `total_jogos` (todos os não excluídos, para o aviso da
  exclusão), `restricoes` (como R-06) e a ordem `favorito desc, nome`; rota
  nova `GET /campeonatos/paises` (países distintos, `campeonatos.listar`) para o filtro e o modal.
- **Rationale**: FR-025 a FR-029; `GET /cotacoes/paises` exige as permissões da tela Cotações, que o
  Gerente pode não ter.

### R-15 — Cadastro manual

- **Decision**: modais `ChampionshipFormModal` e `MatchFormModal` sobre as rotas da spec 003
  (`POST/PUT /campeonatos`, `POST/PUT /confrontos`). Esportes: rota nova `GET /confrontos/esportes`
  (distintos de `confrontos.esporte`, `confrontos.listar`). Cotações: Casa/Empate/Fora (`odd1`,
  `odd2`, `odd3`) sempre visíveis e "Adicionar cotação" com os códigos de `GET /cotacoes/nomes`
  agrupados por `grupos_mercado` (`utils/quotes.js`).
- **Rationale**: FR-029 a FR-031; nenhuma regra nova de cadastro.

### R-16 — Exclusão manual com apostas

- **Decision**: `ConfrontosController@destroy` e `CampeonatosController@destroy` recusam (422) quando
  há palpites `Ativo` de apostas vivas (`situacao` em Ativa, Pendente ou Em análise; Cancelada,
  Recusada e Expirada não contam) no jogo / em algum jogo do campeonato: "Este jogo tem N apostas. Cancele o jogo em vez de excluir." e "Este campeonato tem
  jogos com apostas.".
- **Rationale**: Clarifications 2026-10-27 (Q1 do clarify); muda a spec 003 (FR-063).

### R-17 — Atalho para a tela Cotações

- **Decision**: `PanelQuotes` passa a ler `?confrontos_id=&nome=` (aba Jogos) e `?campeonatos_id=&nome=`
  (aba Campeonatos) e abre o `QuoteEditorModal` com `{ id, nome }`, como já faz a partir de Regras
  ativas; ao fechar, limpa os parâmetros.
- **Rationale**: FR-024 e FR-028; mudança pequena numa tela da spec 016 (Princípio IV: confirmar).

### R-18 — Atualização automática

- **Decision**: no hook, um intervalo de 30 s ativo só com `tipo=ao_vivo` ou na aba Pendentes, que
  recarrega a lista e o resumo sem trocar o estado de carregando (mantém os dados e mostra o aviso
  "Não foi possível atualizar" em caso de erro); pausa com `document.visibilityState === 'hidden'` e
  recarrega ao voltar.
- **Rationale**: FR-039.

### R-19 — Sem interruptor nos modais

- **Decision**: situação do jogo manual em `select`; não há outro liga/desliga nos modais desta tela
  (ativar/desativar e favoritar são ações do menu com `ConfirmModal`).
- **Rationale**: FR-034.

### R-20 — Specs anteriores atualizadas na mesma entrega

- **Decision**: nota "alterado pela spec 023" em `specs/003-confrontos/spec.md` (FR-063, filtro de
  dia e busca da listagem do painel) e `contracts/api.md`, e em `specs/009-apuracao-resultados/spec.md`
  (FR-045, FR-046) e no contrato dela.
- **Rationale**: regra da constituição ("Alterações manuais") e memória "emenda atualiza o plano".
