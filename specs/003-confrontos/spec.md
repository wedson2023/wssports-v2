# Feature Specification: Confrontos (jogos e cotações do provedor)

**Feature Branch**: `003-confrontos`

**Created**: 2026-09-30

**Status**: Draft

**Input**: User description: "Spec: confrontos (jogos e cotações vindos do provedor de cotações).
Backend apenas. Trazer da API do provedor os campeonatos, confrontos, cotações (odds) e jogadores
do pré-jogo e do ao vivo, guardar no banco e expor uma listagem pública de jogos, já com a cotação
ajustada para quem está vendo. Reescreve, com mais velocidade, organização e segurança, a lógica
dos comandos antigos `fonte:campeonatos`, `fonte:confrontos`, `fonte:cotacao`, `fonte:aovivo` e
`comparar:aovivo` e do método `index` do `HomeController` antigo. Carga do pré-jogo em um único
comando e uma única chamada; carga do ao vivo separada, a cada 5 segundos, com trava por tempo sem
atualização; conferência do ao vivo com um segundo provedor e trava geral; tabelas de campeonatos,
confrontos, jogadores, ao vivo, porcentagens, tetos e não permitidos; listagem pública paginada com
filtros e fuso horário; cálculo da cotação exibida. Fora do escopo: painel de gestão, detalhe do
confronto com todas as cotações, apostas e bilhetes, resultados, cancelados e limpeza de jogos
antigos, modalidades especiais, acumuladão, slides, popups, cassino e identificação por pin ou
device_id."

> O texto completo do pedido (itens 1 a 7) está refletido nos requisitos abaixo. Os nomes de
> tabelas, colunas, comandos e filtros foram definidos pelo responsável no pedido e aparecem aqui
> por decisão dele, como nas specs 001 e 002.
>
> O pedido original acima foi ampliado nas Clarifications: entraram as rotas do painel para
> alterar cotações e para alimentar um campeonato manualmente, com suas permissões, e a listagem
> passou a ser por dia (hoje, amanhã e depois de amanhã). Em caso de divergência, valem as
> Clarifications e os requisitos.

## Clarifications

### Session 2026-09-30

- Q: De onde vem o teto de cotação que vale para visitantes e clientes do site? → A: De uma regra
  geral única, por código de cotação, que vale igual para visitantes, clientes e vendedores
  (tabela `confrontos_teto_cotacoes`). Não existe teto por usuário nem por cliente; a tabela de
  tetos por usuário prevista no pedido deixa de existir.
- Q: Esta spec deve criar a tabela de configurações gerais do sistema (a antiga `configs`)? → A:
  Sim. Cria a tabela `configuracoes`, de registro único, preenchida por seeder, só com os campos
  que esta spec usa (sistema só para cassino, intervalos do sorteio de `odd4` e `odd7`, tempo da
  trava do ao vivo, limite de permanência e situação da trava geral); outras specs acrescentam os
  campos delas. Endereços e chaves dos provedores ficam na configuração do servidor.
- Q: Quando um cliente tem uma regra de porcentagem só dele, ela substitui a regra geral dos
  clientes ou soma com ela? → A: Soma. Com a regra geral em −10% e a do cliente em −10%, a redução
  para ele é de −20%.
- Q: Os não permitidos e as porcentagens por campeonato e por confronto valem para quem? → A: Por
  público e por dono: alvo Clientes (vale para o site inteiro) ou alvo Vendedores pertencente a um
  usuário do painel (vale para ele e para quem está abaixo dele), como descrito em FR-033.
- Q: Campeonato novo vindo do provedor já aparece para todos ou nasce escondido? → A: Aparece na
  hora para todos. As configurações gerais ganham a opção `permitir_entrada_campeonatos`, marcada
  por padrão: com ela marcada, todo campeonato novo entra liberado na carga, e fica a critério do
  usuário bloquear depois. Com ela desmarcada, o campeonato novo entra desativado.
- Q: Quais jogos a listagem pública do pré-jogo mostra? → A: Os do dia (hoje), com filtro para
  escolher amanhã ou depois de amanhã. Os filtros `data_inicial` e `data_final` deixam de existir.
- Q: A busca por time procura em qual período? → A: Só entre hoje, amanhã e depois de amanhã, nunca
  antes nem depois.
- Q: O pré-jogo e o ao vivo são listagens separadas? → A: É a mesma listagem, que alterna entre
  pré-jogo e ao vivo por um filtro.
- Q: Como se chamam as tabelas de porcentagem e de teto? → A: As de porcentagem levam
  `porcentagens` na frente: `porcentagens_clientes`, `porcentagens_vendedores` (e as duas do ao
  vivo), `porcentagens_campeonatos` e `porcentagens_confrontos`. A de teto se chama
  `confrontos_teto_cotacoes`.
- Q: O painel de gestão continua todo fora do escopo? → A: Não. Entram nesta spec as rotas e os
  controllers para alterar as cotações (porcentagens de vendedores, de clientes, de campeonato, a
  cotação de um confronto e o teto) e para alimentar um campeonato manualmente, com uma permissão
  para cada ação. (As rotas de não permitidos, ativar e favoritar entraram depois, na resposta
  sobre as rotas do painel; telas e a edição das configurações gerais continuam fora.)
- Q: O que a análise do `AoVivoController` antigo acrescenta? → A: O jogo do ao vivo só aparece se
  existir como confronto do pré-jogo e até um minuto limite (antigo `tempo_ao_vivo`, 95); o ao vivo
  tem uma cotação máxima própria (antiga `odd_maxima_ao_vivo`, 30,00); e existe uma opção para
  desligar o ao vivo inteiro (antiga `jogos_ao_vivo`).
- Q: "Alimentar um campeonato de forma manual" é buscar no provedor na hora ou cadastrar à mão? →
  A: Cadastrar à mão. O usuário do painel cria o campeonato e os confrontos dele (times, data e
  cotações), sem provedor, e eles ficam nas mesmas tabelas `campeonatos` e `confrontos`.
- Q: As rotas do painel para não permitidos, ativar e desativar, e favoritar entram nesta spec? →
  A: Sim, as três: não permitidos (pré-jogo e ao vivo), ativar e desativar campeonato e confronto,
  e favoritar campeonato, cada uma com sua permissão.
- Q: No ao vivo, a redução do vendedor soma supervisor, gerente e vendedor, ou usa só a do
  gerente, como no antigo? → A: Soma a cadeia inteira (supervisor + gerente + vendedor), igual ao
  pré-jogo.
- Q: Deve existir um jeito de aplicar de uma vez para todos (site e vendedores) a cotação de um
  confronto, a porcentagem de um campeonato ou um não permitido? → A: Sim. Entra um terceiro alvo,
  Todos, que vale para visitantes, clientes e todos os vendedores; só Admin e Supervisor podem
  usá-lo.
- Q: O minuto limite e a cotação máxima do ao vivo são valores únicos ou cada gerente tem o seu? →
  A: Valores únicos em `configuracoes`, iguais para visitantes, clientes e vendedores (FR-035a).
  (Revisto na sessão de 2026-10-01: para vendedores, os dois passaram para as configurações de
  cada vendedor; `configuracoes` continua valendo para visitantes e clientes.)

### Session 2026-10-01

- Q: Como ficam as configurações por público? → A: Cada público tem a sua: o visitante, numa
  tabela própria de registro único (`visitantes_configuracoes`); o cliente logado, em
  `clientes_configuracoes` (spec 002); e o vendedor, que é quem aposta, em `usuarios_configuracoes`,
  uma linha por vendedor. As configurações de cada público evoluem separadas, sem precisar ter os
  mesmos campos.
- Q: Existe tabela padrão de configurações? → A: Não, para nenhum público. Os valores iniciais
  ficam no valor padrão de cada coluna. Isso vale também para os clientes: a tabela
  `clientes_configuracoes_padrao` da spec 002 é removida, e o cliente novo nasce com os valores
  padrão das colunas de `clientes_configuracoes`.
- Q: Como a hierarquia altera as configurações dos vendedores? → A: Escolhendo o alcance: o Admin
  escolhe uma supervisão e altera todos os vendedores dela; o Supervisor escolhe um gerente e
  altera todos os vendedores dele; o Gerente escolhe um vendedor e altera só aquele. Cada nível
  também pode descer mais (o Admin pode escolher um gerente ou um vendedor; o Supervisor, um
  vendedor), sempre dentro da própria hierarquia. Para consultar, a hierarquia acima seleciona o
  gerente e vê as configurações dos vendedores dele.
- Q: Com que valores nasce um vendedor cadastrado depois de uma alteração em massa? → A: Com uma
  cópia da configuração de outro vendedor do mesmo gerente; se for o primeiro, de um vendedor da
  mesma supervisão; se não houver nenhum, com os valores padrão das colunas.
- Q: Qual a versão do MySQL em produção? → A: MySQL 8.4. Com ela, colunas de lista (como
  `esportes_permitidos`) podem ter valor padrão na própria coluna, então todas as configurações
  ficam com os valores iniciais no banco, sem exceção.
- Q: Deve ser possível esconder um campeonato ou um jogo para um cliente específico? → A: Sim. As
  três tabelas de não permitidos ganham `clientes_id`: com o alvo Clientes, vazio vale para
  visitantes e todos os clientes; preenchido, só para aquele cliente. Só Admin e Supervisor marcam.
- Q: O valor sorteado de `odd4` e `odd7` fica fixo nas cargas seguintes ou é sorteado de novo? →
  A: Fica fixo enquanto o provedor continuar mandando zero; quando o provedor mandar um valor, vale
  o dele. `confrontos` guarda quais desses valores foram sorteados (`odd4_sorteada` e
  `odd7_sorteada`).

### Session 2026-10-05

- Q: A API do provedor muda para a chamada única aninhada do pré-jogo? → A: Não. A API do
  provedor já está montada e continua como no sistema antigo, com uma rota por assunto:
  campeonatos, confrontos do pré-jogo, cotações do pré-jogo e ao vivo (mais a conferência). O
  sistema se adapta a ela, com os nomes de campo do provedor, e traduz para os nomes do sistema na
  gravação. Isso substitui a decisão anterior de uma única chamada.
- Q: As rotas do pré-jogo rodam num cron só ou em crons separados, e com que intervalo? → A: Um
  comando e um agendamento por rota, em segundo plano e sem execuções sobrepostas, escalonados na
  ordem em que um depende do outro: campeonatos a cada 10 minutos (:00, :10...), confrontos a
  cada 5 minutos começando 1 minuto depois (:01, :06...), cotações a cada 5 minutos começando 2
  minutos depois (:02, :07...). O ao vivo continua a cada 5 segundos e a conferência a cada
  minuto.
- Q: Como vai a chave do provedor, se a API exige a chave na URL? → A: Como a API exige: nos
  parâmetros `key` e `app` da URL, lidos da configuração do servidor. Em troca, nenhuma URL do
  provedor nem a mensagem original de erro da chamada vai para o log.
- Q: O que fazer com os confrontos que o provedor manda com a situação `Bloqueado`? → A: Aceitar
  como situação válida, como no sistema antigo: o confronto é gravado e atualizado, mas não aparece
  na listagem (que só mostra Aguardando) até o provedor desbloquear. Não é opção do confronto
  manual.

### Session 2026-10-08

- Q: A listagem pública (pré-jogo e ao vivo) ordena os campeonatos por país? → A: Sim. A ordem
  passa a ser: favorito primeiro, país do campeonato, nome do campeonato, `data_inicio` e
  `time_casa`. No sistema antigo a API ordenava só por favorito e nome, e o agrupamento por país
  ficava a cargo do frontend com o índice `pais`; como a listagem nova é paginada, a ordem por país
  precisa vir da API para que as páginas continuem umas das outras sem repetir países. Os
  favoritos continuam no topo, como no sistema antigo.
- Q: Por que o ao vivo não aparecia para ninguém (nem visitante)? → A: O provedor manda o
  `tipo_esporte` do ao vivo com o sufixo ` AO VIVO` (`FUTEBOL AO VIVO`, como no sistema antigo, em
  que o sufixo marcava o ao vivo), e a carga gravava esse texto; a listagem filtra por `FUTEBOL` e
  a aposta confere o esporte nos permitidos, então nada aparecia e nada podia ser apostado. Decisão:
  retirar o sufixo na gravação (FR-014). Os jogos antigos que ficaram gravados com o sufixo já não
  são atualizados e saem da listagem pelo tempo de permanência; não há migração de dados.
- Q: Os esportes permitidos com grafia diferente da do provedor (padrão `HOQUEI NO GELO`, provedor
  `HÓQUEI NO GELO`) bloqueiam os jogos? → A: Não devem. A comparação passa a ignorar maiúsculas e
  acentos, como o banco já faz (FR-041); os padrões das configurações continuam como no sistema
  antigo.

## User Scenarios & Testing *(mandatory)*

> Conforme a constituição, o projeto não terá testes automatizados. Os cenários abaixo são
> critérios de aceite validados manualmente.

### User Story 1 - Carga do pré-jogo (Priority: P1)

O sistema busca no provedor de cotações, pelas rotas que o provedor já tem, os campeonatos (a cada
10 minutos), os confrontos (a cada 5 minutos) e as cotações com os jogadores (a cada 5 minutos),
em cargas separadas e escalonadas, e grava cada uma em lote. A banca passa a ter os jogos do dia e
dos próximos dias sempre atualizados, com as três cargas organizadas para não se atropelarem.

**Why this priority**: sem os jogos e as cotações no banco não há o que listar nem o que apostar;
é a base de todas as outras stories.

**Independent Test**: rodar `campeonatos:importar`, `confrontos:importar` e
`confrontos_cotacoes:importar`, nessa ordem, com o provedor respondendo um JSON conhecido e
conferir no banco os campeonatos, confrontos, cotações e jogadores; rodar de novo as cotações com
uma cotação alterada e conferir que o confronto foi atualizado, sem duplicar.

**Acceptance Scenarios**:

1. **Given** o banco sem jogos e o provedor devolvendo 2 campeonatos com 3 confrontos, **When** as
   cargas de campeonatos, confrontos e cotações rodam, **Then** existem 2 campeonatos e 3
   confrontos, cada confronto com suas cotações, seus jogadores e a quantidade de cotações
   disponíveis.
2. **Given** um confronto já gravado com `odd1` 1,40, **When** a carga roda e o provedor manda
   `odd1` 1,55 para o mesmo `codigo_externo`, **Then** o mesmo confronto passa a ter `odd1` 1,55 e
   nenhum registro novo é criado.
3. **Given** um confronto cujo provedor manda só `odd1` e `odd3`, **When** a carga roda, **Then**
   os demais códigos (de `odd2` a `odd323`) valem zero (indisponível).
4. **Given** um confronto de futebol em que o provedor manda `odd4` zerada e o intervalo de sorteio
   de "ambas marcam" configurado de 1,70 a 1,90, **When** a carga roda, **Then** `odd4` recebe um
   valor entre 1,70 e 1,90; **When** a carga roda de novo e o provedor continua mandando zero,
   **Then** `odd4` mantém o mesmo valor sorteado.
5. **Given** o mesmo confronto do cenário 4, **When** o provedor passa a mandar `odd4` 2,05,
   **Then** `odd4` passa a 2,05.
6. **Given** um confronto de basquete com `odd4` zerada, **When** a carga roda, **Then** `odd4`
   continua zerada (o sorteio só vale para futebol).
7. **Given** campeonatos desativados no sistema, **When** a carga chama o provedor, **Then** a
   lista desses campeonatos é enviada para que não retornem.
8. **Given** um campeonato "Série A" do Brasil com porcentagens e restrições de exibição, **When**
   o provedor manda "Série A" do Brasil com um `codigo_externo` novo, **Then** as porcentagens e as
   restrições passam para o campeonato novo.
9. **Given** o provedor fora do ar, respondendo erro ou um JSON inválido, **When** a carga roda,
   **Then** nenhum dado do banco muda e a falha fica registrada em log.
10. **Given** o sistema configurado só para cassino, **When** chega o horário da carga, **Then**
    ela não chama o provedor.
11. **Given** uma carga ainda em andamento, **When** chega o horário da próxima, **Then** a segunda
    não roda em paralelo.
12. **Given** um campeonato ou confronto marcado como não permitido, **When** a carga roda,
    **Then** ele é gravado e atualizado normalmente (as restrições só valem na listagem).
13. **Given** `permitir_entrada_campeonatos` marcado (padrão), **When** a carga traz um campeonato
    que o sistema não conhecia, **Then** ele é criado ativo e seus jogos já aparecem na listagem
    para todos.
14. **Given** `permitir_entrada_campeonatos` desmarcado, **When** a carga traz um campeonato que o
    sistema não conhecia, **Then** ele é criado desativado e seus jogos não aparecem na listagem
    até ele ser ativado; os campeonatos que já existiam não mudam.
15. **Given** um confronto cujo campeonato ainda não foi gravado, **When** a carga dos confrontos
    roda, **Then** ele é ignorado (contado no log) e entra na primeira carga dos confrontos depois
    da carga dos campeonatos que criar o campeonato.
16. **Given** cotações de um confronto que ainda não foi gravado, **When** a carga das cotações
    roda, **Then** elas são ignoradas (contadas no log) e entram na primeira carga das cotações
    depois da carga dos confrontos que criar o confronto.
17. **Given** um confronto já gravado, **When** a carga dos confrontos roda, **Then** times,
    situação e horário são atualizados e as cotações, o sorteio e os jogadores não mudam (são da
    carga das cotações).

---

### User Story 2 - Listagem pública de jogos com cotação ajustada (Priority: P1)

Um visitante, um cliente logado ou um vendedor logado abre a lista e vê os jogos de hoje, no seu
fuso horário, agrupados por campeonato, com as quatro cotações principais já no valor que vale
para ele. Pode trocar para os jogos de amanhã ou de depois de amanhã, buscar um time nesses três
dias e alternar entre pré-jogo e ao vivo.

**Why this priority**: é a vitrine do site; sem ela o apostador não vê os jogos.

**Independent Test**: com jogos carregados, chamar a listagem sem login e sem filtros e conferir
os jogos de hoje, agrupados por campeonato, com data e hora no fuso pedido e as cotações
ajustadas; trocar o filtro `dia` para amanhã e conferir os jogos de amanhã.

**Acceptance Scenarios**:

1. **Given** jogos futuros de hoje em dois campeonatos ativos, **When** um visitante abre a
   listagem sem filtros, **Then** vê os confrontos de hoje agrupados por campeonato, cada um com
   times, escudos, data e hora de início, minutos até o início, `odd1` a `odd4` e a quantidade de
   cotações disponíveis; vê também a lista de países com seus campeonatos e quantidade de jogos, e
   o total de jogos.
2. **Given** jogos de hoje, de amanhã, de depois de amanhã e de daqui a 3 dias, **When** a
   listagem é pedida com `dia` amanhã, **Then** só aparecem os de amanhã; com `dia` depois de
   amanhã, só os desse dia; os de daqui a 3 dias não aparecem em nenhuma opção.
3. **Given** um jogo que começa às 01:00 (UTC) do dia 1º e hoje é dia 30, **When** a listagem de
   hoje é pedida com `fuso_horario` `-03:00`, **Then** o jogo aparece, com início às 22:00 do dia
   30; com `fuso_horario` `+00:00`, ele só aparece em amanhã.
4. **Given** um `dia` diferente de hoje, amanhã e depois de amanhã, **When** a listagem é pedida,
   **Then** é recusada como dado inválido.
5. **Given** `busca` "Flamengo" e jogos do Flamengo amanhã e daqui a 5 dias, **When** a listagem
   é pedida, **Then** aparece só o jogo de amanhã: a busca procura em hoje, amanhã e depois de
   amanhã, ignorando o `dia` escolhido, e nunca fora desses três dias.
6. **Given** jogos de futebol e de basquete, **When** a listagem é pedida sem `esporte`, **Then**
   só aparecem os de futebol; com `esporte` basquete, só os de basquete.
7. **Given** `somente_favoritos` marcado, **When** a listagem é pedida, **Then** só aparecem jogos
   de campeonatos favoritos.
8. **Given** um jogo já iniciado, um jogo com situação diferente de "Aguardando", um confronto
   desativado e um jogo de campeonato desativado, **When** a listagem é pedida, **Then** nenhum
   deles aparece.
9. **Given** mais jogos que o tamanho da página, **When** a listagem é pedida, **Then** vem
   paginada, com no máximo 100 jogos por página.
10. **Given** um `fuso_horario` em formato inválido, **When** a listagem é pedida, **Then** é
    recusada como dado inválido.
11. **Given** um token de cliente ou de vendedor expirado, **When** a listagem é pedida com ele,
    **Then** a resposta vem como a de um visitante e indica que o token não foi aceito.

---

### User Story 3 - Cotação ajustada por porcentagens e teto (Priority: P1)

A banca reduz (ou aumenta) a cotação que cada público vê: uma regra para os clientes do site, uma
para cada nível da hierarquia de vendedores, uma por campeonato e um valor fixo por confronto,
com um teto por código de cotação. Quem vê a lista recebe só o valor final.

**Why this priority**: a margem da banca depende desse ajuste; exibir a cotação crua do provedor
dá prejuízo.

**Independent Test**: gravar uma porcentagem de −10 para clientes em `odd1`, listar como visitante
um jogo com `odd1` 2,00 e conferir 1,80; repetir como vendedor com porcentagens de supervisor,
gerente e vendedor e conferir a soma.

**Acceptance Scenarios**:

1. **Given** `odd1` 2,00 no provedor e porcentagem geral de clientes −10 em `odd1`, **When** um
   visitante lista o jogo, **Then** vê `odd1` 1,80.
2. **Given** o cenário 1 e um cliente com regra específica de −10 em `odd1`, **When** esse cliente
   logado lista o jogo, **Then** vê `odd1` 1,60 (a regra específica soma com a geral: −20%).
3. **Given** `odd1` 2,00, porcentagens de −4 do supervisor, −3 do gerente e −2 do vendedor e −1 do
   campeonato para vendedores, **When** o vendedor lista o jogo, **Then** vê `odd1` 1,80
   (2,00 − 10%).
4. **Given** `odd1` 2,00, porcentagem de clientes −10 e valor fixo do confronto −0,08 para
   clientes, **When** um visitante lista o jogo, **Then** vê `odd1` 1,72.
5. **Given** `odd1` 300,00 depois do ajuste e teto geral de 250,00 em `odd1`, **When** um
   visitante, um cliente ou um vendedor lista o jogo, **Then** todos veem `odd1` 250,00.
6. **Given** um ajuste que levaria a cotação a 0,95, **When** o jogo é listado, **Then** a cotação
   exibida é 1,00.
7. **Given** `odd2` zerada no provedor, **When** o jogo é listado, **Then** `odd2` vem zerada,
   qualquer que seja a porcentagem.
8. **Given** qualquer listagem, **When** a resposta é entregue, **Then** ela não contém as
   porcentagens, o valor fixo, o teto nem a cotação original do provedor.

---

### User Story 4 - Carga do ao vivo com trava automática (Priority: P1)

A cada 5 segundos, o sistema atualiza placar, minuto, situação e cotações dos jogos em andamento.
Se a atualização parar por qualquer motivo, o apostador deixa de receber cotação daquele jogo em
poucos segundos, em vez de continuar vendo um valor antigo.

**Why this priority**: no ao vivo, uma cotação atrasada vira prejuízo certo para a banca.

**Independent Test**: rodar `confrontos_ao_vivo:importar` com um jogo em andamento, conferir placar
e cotações; parar a carga, esperar 15 segundos e conferir que a listagem do ao vivo entrega o jogo
com todas as cotações zeradas; voltar a carga e conferir que as cotações voltam.

**Acceptance Scenarios**:

1. **Given** um jogo em andamento no provedor, **When** a carga do ao vivo roda, **Then** o jogo
   fica gravado com placar, gols por tempo, escanteios, minuto, cronômetro, situação, cotações e a
   data da última atualização.
2. **Given** um jogo atualizado há 6 segundos, **When** ele é listado, **Then** vem com as
   cotações ajustadas.
3. **Given** um jogo sem atualização há mais de 15 segundos, **When** ele é listado, **Then** vem
   com todas as cotações zeradas e marcado como travado.
4. **Given** o provedor devolvendo resposta vazia, erro, ou a gravação falhando, **When** a carga
   roda, **Then** a data da última atualização dos jogos não muda e a falha fica em log.
5. **Given** o comando do ao vivo parado (servidor ou agendador fora do ar), **When** passam 15
   segundos, **Then** todos os jogos do ao vivo são entregues travados, sem que nada precise
   gravar zeros.
6. **Given** um jogo travado, **When** chega uma atualização válida dele, **Then** ele volta a ser
   entregue com as cotações ajustadas.
7. **Given** uma carga do ao vivo que demora mais de 5 segundos, **When** chega o horário da
   próxima, **Then** a segunda não roda em paralelo.

---

### User Story 5 - Listagem pública do ao vivo (Priority: P2)

Na mesma listagem, o visitante, cliente ou vendedor alterna do pré-jogo para o ao vivo e vê os
jogos em andamento com placar, minuto, cronômetro e as cotações principais ajustadas pelas regras
do ao vivo.

**Why this priority**: entrega o ao vivo ao apostador, mas depende da carga (story 4) e do cálculo
(story 3).

**Independent Test**: com jogos ao vivo carregados, pedir a listagem com `tipo` `ao_vivo` e
conferir placar, minuto e cotações; gravar uma porcentagem do ao vivo e conferir o novo valor;
voltar para `tipo` `pre_jogo` e conferir os jogos do dia.

**Acceptance Scenarios**:

1. **Given** dois jogos em andamento, **When** o ao vivo é listado, **Then** aparecem agrupados
   por campeonato, com times, escudos, placar, minuto, cronômetro, situação, `odd1` a `odd4`
   ajustadas e a quantidade de cotações disponíveis.
2. **Given** porcentagem de clientes −10 no pré-jogo e −20 no ao vivo em `odd1`, **When** um
   visitante lista o ao vivo, **Then** o ajuste aplicado é −20.
3. **Given** um jogo marcado como não permitido só no ao vivo, **When** o pré-jogo e o ao vivo são
   pedidos, **Then** ele não aparece no ao vivo e, se ainda estiver no pré-jogo, aparece lá.
4. **Given** um jogo sem atualização há mais tempo que o limite de permanência, **When** o ao vivo
   é listado, **Then** o jogo não aparece mais.
5. **Given** um jogo em andamento que não existe como confronto do pré-jogo, ou cujo confronto do
   pré-jogo está desativado, **When** o ao vivo é listado, **Then** o jogo não aparece.
6. **Given** o minuto limite do ao vivo em 95 e um jogo no minuto 96, **When** o ao vivo é listado,
   **Then** o jogo não aparece.
7. **Given** a cotação máxima do ao vivo em 30,00 e uma cotação ajustada de 45,00, **When** o ao
   vivo é listado, **Then** a cotação exibida é 30,00.
8. **Given** `ao_vivo_habilitado` desmarcado, **When** o ao vivo é pedido, **Then** a listagem é
   recusada informando que o ao vivo não está disponível; o pré-jogo continua funcionando.

---

### User Story 6 - Conferência do ao vivo e trava geral (Priority: P2)

A cada minuto, o sistema sorteia um jogo em andamento e confere o minuto dele com um segundo
provedor. Se o sistema estiver atrasado, todo o ao vivo é travado até voltar ao normal.

**Why this priority**: protege contra um atraso do provedor principal que a trava por tempo não
enxerga (o dado chega, mas chega velho).

**Independent Test**: com um jogo no minuto 30 no sistema e o segundo provedor respondendo minuto
33, rodar `confrontos_ao_vivo:conferir` e conferir que o ao vivo inteiro vem zerado; responder
minuto 30 e conferir que destrava.

**Acceptance Scenarios**:

1. **Given** um jogo no minuto 30 no sistema e no minuto 33 no segundo provedor, **When** a
   conferência roda, **Then** a trava geral do ao vivo é acionada e a trava fica em log.
2. **Given** a trava geral acionada, **When** o ao vivo é listado, **Then** todos os jogos vêm com
   as cotações zeradas e marcados como travados.
3. **Given** a trava geral acionada e a diferença de volta a 1 minuto ou menos, **When** a
   conferência roda, **Then** a trava geral sai e a liberação fica em log.
4. **Given** nenhum jogo em andamento, ou o segundo provedor fora do ar, **When** a conferência
   roda, **Then** a situação da trava geral não muda (a falha do provedor fica em log).
5. **Given** qualquer horário do dia, **When** chega o minuto da conferência, **Then** ela roda
   (não existe janela de exceção).

---

### User Story 7 - Restrições de exibição (não permitidos) (Priority: P2)

A banca esconde um campeonato ou um confronto do site, e cada gestor esconde dos seus vendedores
o que não quer vender, sem afetar a carga nem os demais públicos.

**Why this priority**: controla o risco por público, mas a listagem já entrega valor sem nenhuma
restrição cadastrada.

**Independent Test**: marcar um campeonato como não permitido para clientes e conferir que ele
some para o visitante e continua aparecendo para o vendedor; marcar um confronto como não permitido
por um gerente e conferir que some só para os vendedores dele.

**Acceptance Scenarios**:

1. **Given** um campeonato não permitido com alvo Clientes, **When** um visitante ou um cliente
   lista os jogos, **Then** nenhum jogo desse campeonato aparece; **When** um vendedor lista,
   **Then** os jogos aparecem.
2. **Given** um confronto não permitido com alvo Vendedores pertencente ao gerente A, **When** um
   vendedor do gerente A lista os jogos, **Then** o confronto não aparece; **When** um vendedor do
   gerente B ou um visitante lista, **Then** aparece.
3. **Given** um campeonato não permitido pertencente a um supervisor, **When** qualquer vendedor
   abaixo dele (de qualquer gerente dele) lista os jogos, **Then** o campeonato não aparece.
4. **Given** um campeonato não permitido, **When** a lista de países e o total de jogos são
   montados, **Then** os jogos dele não entram na contagem de quem não pode vê-lo.
5. **Given** um confronto não permitido com alvo Todos, **When** um visitante, um cliente ou
   qualquer vendedor lista os jogos, **Then** o confronto não aparece para nenhum deles.
6. **Given** um campeonato não permitido com alvo Clientes indicado para o cliente Ana, **When** a
   Ana lista os jogos, **Then** o campeonato não aparece; **When** outro cliente ou um visitante
   lista, **Then** aparece.

---

### User Story 8 - Alterar as cotações pelo painel (Priority: P2)

Um usuário do painel com permissão ajusta o que cada público vê: muda as porcentagens de um
vendedor, gerente ou supervisor, as porcentagens dos clientes, as de um campeonato, a cotação de
um confronto específico e o teto geral. A mudança vale na listagem seguinte.

**Why this priority**: sem essas rotas, toda mudança de margem exigiria mexer direto no banco; a
listagem já funciona sem elas, com porcentagem zero.

**Independent Test**: com um Admin, alterar a porcentagem geral de clientes em `odd1` para −10 e
conferir na listagem pública que um jogo com `odd1` 2,00 passa a 1,80; alterar a cotação de um
confronto para 1,95 e conferir o novo valor; repetir com um usuário sem permissão e conferir a
recusa.

**Acceptance Scenarios**:

1. **Given** um Gerente com `porcentagens_vendedores.editar`, **When** ele altera para −5 a
   porcentagem de `odd1` de um vendedor dele, **Then** a listagem desse vendedor passa a refletir
   o novo valor.
2. **Given** o mesmo Gerente, **When** ele tenta alterar a porcentagem de um vendedor de outro
   gerente, **Then** a operação é recusada por falta de permissão.
3. **Given** um Admin com `porcentagens_clientes.editar`, **When** ele aplica −10 a todos os
   códigos da regra geral de clientes (alteração geral), **Then** os 323 códigos e o de jogador
   ficam com −10.
4. **Given** um Admin, **When** ele cria uma regra de −10 em `odd1` para um cliente específico,
   **Then** só esse cliente passa a ver a redução somada à geral.
5. **Given** um confronto com `odd1` 2,00 no provedor, **When** um usuário com
   `porcentagens_confrontos.editar` informa 1,95 para `odd1`, **Then** é guardado o valor fixo
   −0,05 e a listagem do alvo escolhido passa a partir de 1,95 antes das porcentagens do público.
6. **Given** um Admin com `confrontos_teto_cotacoes.editar`, **When** ele altera o teto de `odd1`
   para 200,00, **Then** nenhum público vê `odd1` acima de 200,00.
7. **Given** uma porcentagem fora de −100 a 100, um teto menor que 1,00 ou um código de cotação
   inexistente, **When** a alteração é enviada, **Then** é recusada como dado inválido.
8. **Given** um Gerente com `porcentagens_campeonatos.editar`, **When** ele tenta gravar uma
   porcentagem de campeonato com alvo Clientes, **Then** a operação é recusada por falta de
   permissão; com alvo Vendedores, é aceita e vale para os vendedores dele.
9. **Given** um usuário do painel sem a permissão da ação (incluindo qualquer Vendedor), **When**
   ele tenta consultar ou alterar, **Then** a operação é recusada por falta de permissão.

---

### User Story 9 - Alimentar um campeonato manualmente (Priority: P3)

Um Admin ou Supervisor com permissão cria à mão um campeonato que o provedor não tem (um torneio
local, por exemplo) e os confrontos dele, com times, data e cotações. Esses jogos ficam nas mesmas
tabelas dos jogos do provedor e aparecem na listagem pública como qualquer outro.

**Why this priority**: permite vender jogos que o provedor não cobre, mas o site já funciona só
com a carga automática.

**Independent Test**: criar um campeonato manual e um confronto para amanhã com `odd1`, `odd2` e
`odd3`, conferir que ele aparece na listagem pública de amanhã com as cotações ajustadas; rodar a
carga automática e conferir que o campeonato e o confronto manuais continuam iguais.

**Acceptance Scenarios**:

1. **Given** um Admin com `campeonatos.cadastrar`, **When** ele cria um campeonato informando nome,
   país e bandeira, **Then** o campeonato é criado ativo, marcado como manual e sem
   `codigo_externo`.
2. **Given** um campeonato manual e um Admin com `confrontos.cadastrar`, **When** ele cria um
   confronto informando time da casa, time de fora, esporte, data e hora de início e as cotações
   de `odd1`, `odd2` e `odd3`, **Then** o confronto é criado ativo, com situação "Aguardando",
   marcado como manual, com quantidade de cotações 3, e aparece na listagem pública do dia dele.
3. **Given** um confronto manual com `odd1` 2,00 e porcentagem geral de clientes −10, **When** um
   visitante lista o dia, **Then** vê `odd1` 1,80 (as mesmas regras de ajuste, teto e não
   permitidos valem para os manuais).
4. **Given** campeonatos e confrontos manuais, **When** a carga automática roda, **Then** nenhum
   deles é alterado nem apagado.
5. **Given** um confronto manual, **When** um usuário com `confrontos.editar` muda a data, uma
   cotação ou a situação para "Cancelado", **Then** a mudança é salva; cancelado, ele sai da
   listagem pública.
6. **Given** um confronto ou campeonato vindo do provedor, **When** alguém tenta editá-lo ou
   excluí-lo por essas rotas, **Then** a operação é recusada, informando que só registros manuais
   podem ser alterados.
7. **Given** um confronto manual sem nenhuma cotação, com cotação menor que 1,00, com data de
   início no passado ou com código de cotação inexistente, **When** ele é enviado, **Then** é
   recusado como dado inválido.
8. **Given** um campeonato manual com confrontos, **When** um usuário com `campeonatos.excluir` o
   exclui, **Then** o campeonato e os confrontos dele deixam de aparecer (exclusão lógica).
9. **Given** um Gerente ou Vendedor, mesmo com a permissão, ou um usuário sem ela, **When** ele
   tenta cadastrar, editar ou excluir campeonato ou confronto manual, **Then** a operação é
   recusada por falta de permissão.

---

### User Story 10 - Ativar, favoritar e esconder campeonatos e confrontos pelo painel (Priority: P2)

Um usuário do painel com permissão lista os campeonatos e os confrontos, ativa ou desativa um
deles para o sistema inteiro, marca campeonatos como favoritos e marca campeonatos e confrontos
como não permitidos para o site ou para os seus vendedores.

**Why this priority**: é o que dá uso às restrições de exibição e à ativação criadas nesta spec;
sem essas rotas, tudo isso só mudaria direto no banco.

**Independent Test**: com um Gerente, marcar um confronto como não permitido e conferir que ele
some para os vendedores dele e continua para os demais; com um Admin, desativar um campeonato e
conferir que os jogos dele somem para todos e que ele deixa de vir na carga seguinte.

**Acceptance Scenarios**:

1. **Given** um usuário com `campeonatos.listar`, **When** ele lista os campeonatos, **Then** vê,
   paginado, nome, país, se é ativo, favorito e manual, e se está não permitido para ele.
2. **Given** um Admin com `campeonatos.alterar_situacao`, **When** ele desativa um campeonato,
   **Then** os jogos dele somem da listagem pública para todos e o campeonato entra na lista de
   desativados enviada ao provedor; ao reativar, volta a aparecer.
3. **Given** um Admin com `confrontos.alterar_situacao`, **When** ele desativa um confronto,
   **Then** o confronto some do pré-jogo e do ao vivo para todos.
4. **Given** um Admin com `campeonatos.favoritar`, **When** ele marca um campeonato como favorito,
   **Then** o campeonato passa a vir primeiro na listagem pública e no filtro `somente_favoritos`.
5. **Given** um Gerente com `campeonatos_nao_permitidos.gerenciar`, **When** ele marca um
   campeonato como não permitido, **Then** o registro nasce com alvo Vendedores pertencendo a ele,
   e o campeonato some só para ele e para os vendedores dele; ao desmarcar, volta a aparecer.
6. **Given** um Admin com a mesma permissão, **When** ele marca um campeonato como não permitido
   com alvo Clientes, **Then** o campeonato some para visitantes e clientes e continua para os
   vendedores.
7. **Given** um Gerente, **When** ele tenta marcar um não permitido com alvo Clientes ou Todos,
   desmarcar o
   não permitido de outro gerente ou ativar e desativar um campeonato, **Then** a operação é
   recusada por falta de permissão.
8. **Given** um usuário com `confrontos_ao_vivo_nao_permitidos.gerenciar`, **When** ele marca um
   jogo como não permitido no ao vivo, **Then** o jogo some só do ao vivo do alvo escolhido.
9. **Given** um usuário do painel sem a permissão da ação, **When** ele tenta listar ou alterar,
   **Then** a operação é recusada por falta de permissão.

---

### User Story 11 - Configurações do visitante e dos vendedores (Priority: P2)

Cada público tem a sua configuração de listagem: o visitante, o cliente logado e o vendedor. A
hierarquia altera as configurações dos vendedores escolhendo o alcance: o Admin por supervisão, o
Supervisor por gerente e o Gerente por vendedor.

**Why this priority**: o vendedor é quem aposta, e cada gerência precisa limitar o que os seus
vendedores veem e apostam; a listagem já funciona com os valores padrão.

**Independent Test**: com um Supervisor, desligar o ao vivo de todos os vendedores de um gerente e
conferir que esses vendedores não conseguem abrir o ao vivo, enquanto vendedores de outro gerente
e visitantes continuam vendo; cadastrar um vendedor novo nesse gerente e conferir que ele já nasce
com o ao vivo desligado.

**Acceptance Scenarios**:

1. **Given** um Admin com `usuarios_configuracoes.editar`, **When** ele escolhe uma supervisão e
   desmarca `ao_vivo_habilitado`, **Then** todos os vendedores daquela supervisão ficam com o ao
   vivo desligado e os das outras supervisões não mudam.
2. **Given** um Supervisor, **When** ele escolhe um gerente dele e muda `cotacao_maxima_ao_vivo`
   para 20,00, **Then** todos os vendedores daquele gerente passam a ter 20,00 e os demais campos
   de cada vendedor continuam como estavam.
3. **Given** um Gerente, **When** ele escolhe um vendedor dele e muda `esportes_permitidos` para
   só Futebol, **Then** só esse vendedor deixa de ver basquete na listagem.
4. **Given** um Gerente, **When** ele tenta alterar um vendedor de outro gerente, **Then** a
   operação é recusada por falta de permissão.
5. **Given** os vendedores de um gerente com o ao vivo desligado, **When** um vendedor novo é
   cadastrado para esse gerente, **Then** ele nasce com uma cópia da configuração de um colega,
   com o ao vivo desligado.
6. **Given** um vendedor com `minuto_limite_ao_vivo` 80, **When** ele lista o ao vivo, **Then**
   jogos depois do minuto 80 não aparecem para ele; para um visitante, valem os 95 de
   `configuracoes`.
7. **Given** um Admin com `visitantes_configuracoes.editar`, **When** ele desmarca
   `ao_vivo_habilitado` dos visitantes, **Then** quem não está logado não consegue abrir o ao
   vivo, e clientes e vendedores continuam conseguindo.
8. **Given** um cliente novo, **When** ele se cadastra, **Then** a configuração dele nasce com os
   valores padrão das colunas de `clientes_configuracoes`, sem nenhuma tabela padrão.

---

### Edge Cases

- Campeonato que chega do provedor sem nenhum confronto é gravado normalmente.
- Confronto que chega com situação diferente de "Aguardando" (Encerrado, Cancelado, Adiado,
  Bloqueado) é atualizado e deixa de aparecer na listagem do pré-jogo. O Bloqueado é uma suspensão
  do provedor: quando ele voltar a mandar Aguardando, o jogo volta a aparecer na carga seguinte.
- Confronto que some da resposta do provedor não é apagado pela carga; deixa de aparecer quando o
  horário de início passa.
- Cotação com código fora de `odd1` a `odd323`, valor negativo ou não numérico é tratada como dado
  inválido daquele confronto: o confronto é ignorado nessa carga e o motivo fica em log, sem
  derrubar o restante.
- Dois campeonatos com o mesmo nome em países diferentes são campeonatos diferentes (a herança por
  nome considera nome e país).
- Intervalo de sorteio não configurado ou com mínimo maior que o máximo: a cotação continua zerada.
- Jogo do ao vivo cujo campeonato ainda não existe no sistema é ignorado naquela carga e o motivo
  fica em log; entra assim que a carga dos campeonatos criar o campeonato.
- Jogo que termina deixa de vir do provedor do ao vivo: fica travado após 15 segundos e sai da
  listagem após o limite de permanência.
- "Hoje" no pré-jogo só traz os jogos de hoje que ainda vão começar; os já iniciados ficam no ao
  vivo.
- A virada do dia segue o fuso pedido: às 23:59 de um fuso, "amanhã" começa em 1 minuto, mesmo que
  em UTC já seja outro dia.
- O filtro `dia` enviado junto com `tipo` `ao_vivo` é ignorado.
- Jogo que começa exatamente agora não aparece no pré-jogo (só início futuro).
- Alterar a cotação de um confronto cuja cotação do provedor mudou depois mantém o valor fixo
  guardado: a cotação exibida acompanha a nova cotação do provedor mais o mesmo valor fixo.
- Alteração geral (todos os códigos) substitui os valores que os códigos tinham antes.
- Porcentagem que levaria a cotação abaixo de 1,00 resulta em 1,00; cotação zerada nunca vira 1,00.
- Usuário do painel sem porcentagem cadastrada conta como porcentagem zero.
- Código de cotação sem teto geral cadastrado não tem limite.
- Visitante e cliente sem regra específica usam só a regra geral de clientes; sem regra geral, a
  porcentagem de clientes é zero. Cliente com regra específica e sem regra geral usa só a dele.

## Requirements *(mandatory)*

### Functional Requirements

**Carga do pré-jogo**

- **FR-001**: O pré-jogo DEVE ser carregado por três comandos, um por rota do provedor, cada um
  agendado em segundo plano e sem execuções sobrepostas, escalonados para que cada carga encontre
  os dados da anterior:
  - `campeonatos:importar` (substitui `fonte:campeonatos`): a cada 10 minutos (:00, :10, ...);
  - `confrontos:importar` (substitui `fonte:confrontos`): a cada 5 minutos, de :01 em diante
    (:01, :06, ...);
  - `confrontos_cotacoes:importar` (substitui `fonte:cotacao`): a cada 5 minutos, de :02 em diante
    (:02, :07, ...).
- **FR-002**: O sistema DEVE consumir as rotas do provedor como elas existem hoje (detalhes em
  `contracts/provedor.md`), cada uma devolvendo uma lista:
  - campeonatos: `{ fonte_id, nome, pais, bandeira }`;
  - confrontos: `{ fonte_id, campeonatos_id, casa, escudo_casa, fora, escudo_fora, situacao,
    tipo_esporte, horario }`, com `campeonatos_id` sendo o código do campeonato no provedor e
    `horario` em horário universal (UTC);
  - cotações: `{ fonte_id, odd1 ... odd323, jogador[] { atletas_id, nome, opcao, tipo, odd } }`.
  Os nomes do provedor DEVEM ser traduzidos para os do sistema na gravação (`fonte_id` →
  `codigo_externo`, `casa` → `time_casa`, `tipo_esporte` → `esporte`, `horario` → `data_inicio`,
  `jogador` → jogadores, `atletas_id` → `codigo_externo` do jogador).
- **FR-003**: O sistema DEVE aceitar e guardar os 323 códigos de cotação (`odd1` a `odd323`);
  código ausente ou zerado DEVE valer zero (indisponível).
- **FR-004**: Nas chamadas de confrontos, cotações e ao vivo, o sistema DEVE enviar ao provedor a
  lista de `codigo_externo` dos campeonatos desativados (campo `campeonatos_id` do corpo), para
  que os jogos deles não retornem. A rota de campeonatos traz todos.
- **FR-005**: Cada carga DEVE gravar por inserir-ou-atualizar em lote, pela chave
  `codigo_externo`, numa única transação: ou aquela carga inteira é gravada, ou nada muda. Rodar a
  mesma carga duas vezes NÃO DEVE criar registros duplicados. A carga dos campeonatos grava os
  campeonatos; a dos confrontos, times, escudos, esporte, situação e horário; a das cotações, as
  cotações, o sorteio e os jogadores. Uma carga NÃO DEVE alterar o que é da outra.
- **FR-005a**: Confronto cujo campeonato ainda não existe, e cotação de confronto que ainda não
  existe, DEVEM ser ignorados naquela carga, com a quantidade em log; entram na carga seguinte,
  depois da carga de que dependem.
- **FR-006**: Campeonato novo DEVE ser criado não favorito e, conforme a configuração
  `permitir_entrada_campeonatos` (FR-035a): ativo quando ela estiver marcada (padrão) e desativado
  quando estiver desmarcada. Campeonato novo NÃO DEVE receber nenhuma restrição de exibição
  automática; bloquear fica a critério do usuário. A carga NÃO DEVE alterar `ativo` nem `favorito`
  de campeonatos e confrontos já existentes.
- **FR-007**: Quando chegar um campeonato com `codigo_externo` novo e com o mesmo nome e país de um
  campeonato já existente, as porcentagens e as restrições de exibição do antigo DEVEM passar para
  o novo.
- **FR-008**: Em confrontos de futebol do pré-jogo, quando `odd4` (ambas marcam) ou `odd7` (ambas
  não marcam) vier zerada, o sistema DEVE preenchê-la com um valor sorteado dentro do intervalo
  configurado para cada uma (mínimo e máximo, 2 casas decimais). O valor sorteado DEVE ser mantido
  nas cargas seguintes enquanto o provedor continuar mandando zero, e DEVE ser substituído quando o
  provedor mandar um valor.
- **FR-009**: Cada confronto DEVE guardar a quantidade de cotações disponíveis: o número de códigos
  com valor diferente de zero (depois do sorteio de FR-008) somado ao número de cotações de
  jogadores do confronto.
- **FR-010**: Os jogadores de um confronto DEVEM ser substituídos pelos que vierem na carga:
  jogador que não vier mais deixa de valer para aquele confronto.
- **FR-011**: Falha na chamada (provedor fora do ar, tempo esgotado, erro) ou resposta que não seja
  a lista de FR-002 NÃO DEVE alterar nenhum dado daquela carga e DEVE ser registrada em log; as
  outras cargas seguem normalmente. Um item com dado inválido DEVE ser ignorado, com o motivo em
  log, sem impedir a gravação dos demais.
- **FR-012**: As cargas do pré-jogo, a carga do ao vivo e a conferência NÃO DEVEM rodar quando o
  sistema estiver configurado só para cassino.
- **FR-013**: As cargas NÃO DEVEM consultar as tabelas de restrição de exibição nem as de
  porcentagem: gravam tudo o que o provedor mandar.

**Carga do ao vivo**

- **FR-014**: O sistema DEVE ter um comando separado, `confrontos_ao_vivo:importar`, que busca no
  provedor os jogos em andamento a cada 5 segundos, sem execuções sobrepostas, enviando a lista de
  campeonatos desativados (como em FR-004). Ele substitui o antigo `fonte:aovivo` e só roda com
  `ao_vivo_habilitado` marcado (FR-046c). A rota do ao vivo é a que o provedor já tem, com os nomes
  dele traduzidos na gravação (`minuto_exato` → `minuto`, `tempo` → `cronometro`, `g1_tempo_casa`
  → `gols_primeiro_tempo_casa`, `escanteio_casa` → `escanteios_casa` e equivalentes; detalhes em
  `contracts/provedor.md`). O `tipo_esporte` do ao vivo vem com o sufixo ` AO VIVO` (ex.:
  `FUTEBOL AO VIVO`), que DEVE ser retirado na gravação: o esporte do ao vivo é o mesmo do
  pré-jogo, e o que marca o ao vivo é a tabela `confrontos_ao_vivo`.
- **FR-015**: Para cada jogo em andamento, o sistema DEVE gravar ou atualizar, pela chave
  `codigo_externo`: campeonato, times, escudos, esporte, `data_inicio`, `placar_casa`,
  `placar_fora`, gols de cada time no primeiro e no segundo tempo, escanteios de cada time,
  `minuto`, `cronometro`, situação (1 tempo, Intervalo ou 2 tempo), as cotações (`odd1` a `odd323`,
  como em FR-003), a quantidade de cotações disponíveis e a data da última atualização recebida.
- **FR-016**: Os jogos do ao vivo DEVEM ficar em tabela própria (`confrontos_ao_vivo`), separada
  dos confrontos do pré-jogo, e a regra de sorteio de FR-008 NÃO se aplica a eles.
- **FR-017**: Trava do ao vivo: um jogo cuja última atualização tenha mais de 15 segundos (valor
  das configurações gerais, FR-035a) DEVE ser entregue ao apostador com todas as cotações zeradas
  e marcado como travado, nunca com valor antigo. A trava DEVE valer pela data da última atualização, sem depender
  de nenhuma gravação de zeros.
- **FR-018**: Resposta vazia, erro na chamada ou erro ao gravar NÃO DEVEM renovar a data da última
  atualização de nenhum jogo, e DEVEM ser registrados em log. Um jogo que não vier na resposta NÃO
  DEVE ter a data renovada.
- **FR-019**: Um jogo travado DEVE destravar sozinho na primeira atualização válida.
- **FR-020**: Um jogo sem atualização há mais tempo que o limite de permanência (padrão de 5
  minutos, configurável) NÃO DEVE mais aparecer na listagem do ao vivo.

**Conferência do ao vivo**

- **FR-021**: O sistema DEVE ter o comando `confrontos_ao_vivo:conferir`, agendado a cada minuto,
  em qualquer horário do dia, que sorteia um jogo em andamento e compara o `minuto` dele com o
  minuto do mesmo jogo num segundo provedor (campo `minuto_exato` da rota de confronto que ele já
  tem). Ele substitui o antigo `comparar:aovivo`.
- **FR-022**: Quando o minuto do segundo provedor estiver mais de 1 minuto à frente do minuto do
  sistema, DEVE ser acionada a trava geral do ao vivo: todos os jogos do ao vivo passam a ser
  entregues com as cotações zeradas e marcados como travados.
- **FR-023**: Com a trava geral acionada, quando a conferência encontrar diferença de 1 minuto ou
  menos, a trava geral DEVE sair sozinha.
- **FR-024**: Cada acionamento e cada liberação da trava geral DEVEM ser registrados em log, com o
  jogo conferido e os dois minutos comparados. Sem jogo em andamento, ou com falha do segundo
  provedor, a situação da trava geral NÃO DEVE mudar.

**Dados guardados**

- **FR-025**: Todas as datas e horas DEVEM ser gravadas em horário universal (UTC).
- **FR-026**: Todo registro vindo do provedor DEVE ter um id próprio do sistema e um
  `codigo_externo` único, que é só a referência do registro no provedor. As ligações entre tabelas
  DEVEM usar o id próprio. Registros manuais (FR-063) não têm `codigo_externo`.
- **FR-027**: `campeonatos` DEVE guardar: `codigo_externo`, `nome`, `pais`, `bandeira`, `ativo`,
  `favorito` e `manual`.
- **FR-028**: `confrontos` DEVE guardar: `codigo_externo`, `campeonatos_id`, `time_casa`,
  `escudo_casa`, `time_fora`, `escudo_fora`, `esporte`, `situacao`, `data_inicio`, `ativo`,
  `manual`, `odd4_sorteada` e `odd7_sorteada` (marcam o valor sorteado de FR-008),
  `quantidade_cotacoes` e `cotacoes` (as 323 cotações do confronto numa única coluna, no lugar das
  323 colunas do sistema antigo).
- **FR-029**: `confrontos_jogadores` (antiga `atletas`) DEVE guardar, por confronto:
  `codigo_externo`, `nome`, `opcao`, `tipo` e `odd`.
- **FR-030**: As regras de porcentagem DEVEM ficar em tabelas com `porcentagens` na frente:
  - `porcentagens_vendedores` (junta as antigas `porcentagens_supervisors`, `porcentagens_gerentes`
    e `porcentagens_vendedors`): porcentagem por usuário do painel (supervisor, gerente ou
    vendedor) e código de cotação (`odd1` a `odd323` e `jogador`), para o pré-jogo;
  - `porcentagens_vendedores_ao_vivo` (antiga `porcentagens_ao_vivos`): o mesmo, para o ao vivo;
  - `porcentagens_clientes` e `porcentagens_clientes_ao_vivo` (novas): porcentagem por código de
    cotação para os clientes do site, com uma regra geral (vale para visitantes e para todos os
    clientes) e, opcionalmente, uma regra de um cliente específico, que soma com a geral;
  - `porcentagens_campeonatos` (mesmo nome do sistema antigo): porcentagem por campeonato, código
    de cotação e alvo (FR-033);
  - `porcentagens_confrontos` (mesmo nome do sistema antigo): valor fixo, em cotação, somado por
    confronto, código de cotação e alvo (FR-033).
- **FR-031**: `confrontos_teto_cotacoes` (antiga `teto_cotacaos`) DEVE guardar a cotação máxima
  por código de cotação (`odd1` a `odd323` e `jogador`), numa regra geral única, sem dono: não há
  teto por usuário nem por cliente.
- **FR-032**: As restrições de exibição DEVEM ficar em `campeonatos_nao_permitidos` (antiga
  `campeonatos_gerencias`), `confrontos_nao_permitidos` (antiga `confrontos_gerencias`) e
  `confrontos_ao_vivo_nao_permitidos` (restrição do ao vivo, separada da do pré-jogo). Elas são
  usadas só nas listagens públicas, nunca nas cargas.
- **FR-033**: Cada registro de `porcentagens_campeonatos`, `porcentagens_confrontos` e das tabelas
  de não permitidos DEVE ter um alvo: **Clientes** (vale para o site inteiro: visitantes e
  clientes), **Vendedores** (o registro pertence a um usuário do painel e vale para ele e para
  todos os usuários abaixo dele na hierarquia) ou **Todos** (vale para visitantes, clientes e
  todos os usuários do painel). Os alvos Clientes e Todos não têm dono. Nas tabelas de não
  permitidos, o alvo Clientes PODE indicar um cliente (`clientes_id`): vazio, vale para visitantes
  e todos os clientes; preenchido, só para aquele cliente.
- **FR-034**: Porcentagens DEVEM aceitar valores negativos (reduzem a cotação) e positivos
  (aumentam), de −100 a 100, com 2 casas decimais. Tetos DEVEM ser maiores ou iguais a 1,00.
- **FR-035**: Esta spec cria as tabelas de porcentagens, teto e não permitidos e as aplica nas
  listagens. A alteração das porcentagens e do teto pelo painel (FR-058 a FR-062) e a marcação
  dos não permitidos (FR-071) fazem parte desta spec.
- **FR-035a**: O sistema DEVE ter um registro único de configurações gerais (tabela
  `configuracoes`, substitui a antiga `configs`), com os campos usados por esta spec:
  - `somente_cassino`: quando marcado, as cargas e a conferência não rodam (FR-012);
  - `permitir_entrada_campeonatos`: quando marcado, campeonato novo entra ativo na carga (FR-006);
  - intervalo do sorteio de `odd4` (ambas marcam): mínimo e máximo (FR-008);
  - intervalo do sorteio de `odd7` (ambas não marcam): mínimo e máximo (FR-008);
  - `ao_vivo_habilitado`: quando desmarcado, o ao vivo inteiro fica desligado (FR-046c);
  - tempo da trava do ao vivo, em segundos (FR-017);
  - limite de permanência do ao vivo, em minutos (FR-020);
  - minuto limite do ao vivo (FR-046b);
  - cotação máxima do ao vivo (FR-051);
  - situação da trava geral do ao vivo: travado ou liberado, alterada pela conferência (FR-022 e
    FR-023).
- **FR-035b**: Os valores iniciais das configurações gerais DEVEM ser os valores padrão das
  colunas (não existe tabela padrão), tirados do sistema antigo (`database.sql`) e desta spec:
  `somente_cassino` desmarcado; `permitir_entrada_campeonatos` marcado; sorteio de `odd4` de 1,30
  a 1,50; sorteio de `odd7` de 1,45 a 1,55; `ao_vivo_habilitado` marcado; trava do ao vivo em 15
  segundos; permanência em 5 minutos; minuto limite do ao vivo 95; cotação máxima do ao vivo
  30,00; trava geral liberada. O seeder DEVE criar o registro único usando esses valores. O
  minuto limite e a cotação máxima de `configuracoes` valem para visitantes e clientes; os
  vendedores usam os das próprias configurações (FR-074). A edição das configurações gerais pelo
  painel NÃO faz parte desta spec.
- **FR-035c**: Os endereços e as chaves de acesso dos provedores NÃO DEVEM ficar na tabela
  `configuracoes`; ficam na configuração do servidor (FR-053).

**Listagem pública (regras comuns e pré-jogo)**

- **FR-036**: O sistema DEVE oferecer uma listagem pública de jogos, acessível sem login, que
  alterna entre pré-jogo e ao vivo pelo filtro `tipo` (FR-037). Ela DEVE aceitar também o token de
  um cliente ou de um usuário do painel; token inválido ou expirado DEVE ser tratado como
  visitante, e a resposta DEVE indicar que o token não foi aceito.
- **FR-037**: Filtros da listagem:
  - `tipo`: `pre_jogo` (padrão) ou `ao_vivo`;
  - `dia` (só no pré-jogo): `hoje` (padrão), `amanha` ou `depois_de_amanha`;
  - `busca`: parte do nome do time da casa ou do time de fora; no pré-jogo, com `busca` o `dia` é
    ignorado e a procura vale para hoje, amanhã e depois de amanhã juntos;
  - `esporte`: padrão Futebol;
  - `somente_favoritos`: só jogos de campeonatos favoritos;
  - `fuso_horario`: deslocamento no formato `±HH:MM`; padrão `-03:00`;
  - paginação: `pagina` e `por_pagina` (padrão 50, máximo 100).
- **FR-038**: Hoje, amanhã e depois de amanhã DEVEM ser dias do calendário no `fuso_horario`
  pedido (do primeiro ao último segundo do dia), e as datas e horas da resposta DEVEM ser
  convertidas para ele, pois o sistema atende regiões com fusos diferentes.
- **FR-039**: No pré-jogo só DEVEM entrar confrontos ativos, de campeonato ativo, com situação
  "Aguardando" e `data_inicio` futura. A listagem do pré-jogo NUNCA DEVE trazer jogo que comece
  depois do fim de depois de amanhã, nem com `busca`. Desde a spec 004 (FR-017 e FR-065 de lá),
  a listagem também respeita o `periodo_jogos` do público (Hoje, Amanhã ou Depois de amanhã), a
  `data_travamento_sistema` do vendedor e do visitante (nada a partir da data e só jogos que começam
  antes dela) e deixa de mostrar no pré-jogo o confronto que já está no ao vivo. Essas regras ficam
  no serviço `RegrasExibicao`, compartilhado com a aposta e com o detalhe do confronto (rotas da
  spec 004).
- **FR-040**: NÃO DEVEM entrar na listagem os campeonatos e confrontos não permitidos para quem
  está vendo: os de alvo Todos, para qualquer público; para o visitante, também os de alvo
  Clientes sem cliente indicado; para o cliente logado, também os de alvo Clientes sem cliente
  indicado e os indicados para ele; para usuário do painel, também os de alvo Vendedores que
  pertencem a ele ou a qualquer superior dele.
- **FR-041**: Só DEVEM entrar jogos dos esportes permitidos a quem está vendo, pelos
  `esportes_permitidos` e `apostar_outros_esportes` (desmarcado = só Futebol) da configuração do
  público dele: para o visitante, `visitantes_configuracoes`; para o cliente logado, as
  `clientes_configuracoes` dele; para o vendedor logado, as `usuarios_configuracoes` dele. Gerente,
  Supervisor e Admin logados veem todos os esportes. A comparação do esporte com os permitidos NÃO
  DEVE diferenciar maiúsculas nem acentos (como o banco, `utf8mb4_unicode_ci`): `HOQUEI NO GELO`
  permite os jogos gravados como `HÓQUEI NO GELO`.
- **FR-042**: A resposta DEVE trazer:
  - os confrontos da página agrupados por campeonato, ordenados por campeonato favorito primeiro,
    país do campeonato, nome do campeonato, `data_inicio` e `time_casa`;
  - em cada confronto: id, times, escudos, esporte, data e hora de início (no fuso pedido),
    minutos até o início, `odd1` a `odd4` já ajustadas (FR-047) e a quantidade de cotações
    disponíveis;
  - a lista de países, cada um com seus campeonatos (nome, bandeira e quantidade de jogos),
    considerando todo o resultado do filtro, não só a página;
  - o total de jogos do filtro e os dados de paginação.
- **FR-043**: Todo parâmetro da listagem DEVE ser validado; parâmetro inválido (`tipo` ou `dia`
  fora das opções, fuso em formato inválido, página fora do limite) DEVE ser recusado com mensagem
  clara em português.
- **FR-044**: A listagem pública DEVE ter limite de requisições por IP (padrão de 120 por
  minuto); acima dele, a requisição DEVE ser recusada informando o tempo de espera.

**Listagem pública do ao vivo**

- **FR-045**: Com `tipo` `ao_vivo`, a listagem DEVE seguir as mesmas regras de acesso (FR-036),
  esportes (FR-041), limite de requisições (FR-044) e paginação, com os filtros `busca`,
  `somente_favoritos` e `fuso_horario` (o filtro `dia` não se aplica). Ela DEVE trazer os jogos em
  andamento (situação 1 tempo, Intervalo ou 2 tempo) de campeonato ativo, agrupados por
  campeonato, cada um com times, escudos, placar, `minuto`, `cronometro`, situação, `odd1` a `odd4`
  ajustadas, a quantidade de cotações disponíveis e a indicação de travado.
- **FR-046**: A listagem do ao vivo DEVE usar as tabelas do ao vivo (`porcentagens_vendedores_ao_vivo`,
  `porcentagens_clientes_ao_vivo` e `confrontos_ao_vivo_nao_permitidos`), além das regras por
  campeonato (porcentagem e não permitidos), e DEVE respeitar a trava por jogo (FR-017), a trava
  geral (FR-022) e o limite de permanência (FR-020). O valor fixo por confronto
  (`porcentagens_confrontos`) NÃO se aplica ao ao vivo.
- **FR-046a**: Um jogo do ao vivo só DEVE aparecer se existir um confronto do pré-jogo com o mesmo
  `codigo_externo` e esse confronto estiver ativo (como no sistema antigo, o ao vivo só vende jogo
  que a banca já tinha na grade).
- **FR-046b**: Um jogo do ao vivo NÃO DEVE aparecer depois que o `minuto` dele passar do minuto
  limite do ao vivo (antigo `tempo_ao_vivo`): para o vendedor, o das configurações dele; para
  visitante, cliente, Gerente, Supervisor e Admin, o de `configuracoes` (padrão 95).
- **FR-046c**: Com a opção `ao_vivo_habilitado` desmarcada em `configuracoes`, a listagem do ao
  vivo DEVE ser recusada para todos, informando que o ao vivo não está disponível, e a carga do ao
  vivo e a conferência NÃO DEVEM rodar. Com ela marcada, o ao vivo ainda DEVE ser recusado ao
  visitante quando `ao_vivo_habilitado` estiver desmarcado em `visitantes_configuracoes`, e ao
  vendedor quando estiver desmarcado nas configurações dele.

**Cálculo da cotação exibida**

- **FR-047**: A cotação exibida DEVE ser calculada assim, para cada código de cotação:
  `cotação final = cotação do provedor + cotação do provedor × (soma das porcentagens aplicáveis)
  ÷ 100 + valor fixo do confronto`. Em seguida: limitada ao teto aplicável; nunca menor que 1,00;
  arredondada em 2 casas decimais.
- **FR-048**: Cotação zerada no provedor (indisponível) ou zerada por trava DEVE continuar zerada,
  sem nenhum ajuste.
- **FR-049**: Porcentagens aplicáveis:
  - para visitante e cliente: a regra geral de clientes, mais a regra específica do cliente
    logado quando existir (as duas se somam), mais as do campeonato com alvo Clientes e com alvo
    Todos;
  - para usuário do painel: a dele e a de todos os seus superiores (para o vendedor: supervisor,
    gerente e vendedor), mais as do campeonato com alvo Todos e com alvo Vendedores que pertencem
    a ele ou a um superior dele.
- **FR-050**: O valor fixo do confronto aplicável segue a mesma regra de alvo de FR-049 (alvo
  Todos para qualquer público; alvo Clientes para visitante e cliente; alvo Vendedores da cadeia
  do usuário do painel). Quando mais de um alvo se aplica, os valores se somam.
- **FR-051**: O teto aplicável DEVE ser o teto geral do código de cotação (FR-031), o mesmo para
  visitante, cliente e usuário do painel, no pré-jogo e no ao vivo; código sem teto cadastrado não
  tem limite. No ao vivo, além do teto do código, a cotação DEVE ser limitada à cotação máxima do
  ao vivo (antiga `odd_maxima_ao_vivo`): para o vendedor, a das configurações dele; para os demais,
  a de `configuracoes` (padrão 30,00). Vale o menor dos dois.
- **FR-052**: As porcentagens, os valores fixos, os tetos e a cotação original do provedor NUNCA
  DEVEM aparecer nas respostas das listagens públicas.

**Alteração das cotações pelo painel**

- **FR-058**: Usuários do painel com a permissão correspondente DEVEM poder consultar e alterar,
  por rotas do painel (token do painel):
  - as porcentagens de um usuário do painel, do pré-jogo (`porcentagens_vendedores`) e do ao vivo
    (`porcentagens_vendedores_ao_vivo`): permissão `porcentagens_vendedores.editar`;
  - as porcentagens dos clientes, do pré-jogo (`porcentagens_clientes`) e do ao vivo
    (`porcentagens_clientes_ao_vivo`), na regra geral ou na de um cliente específico: permissão
    `porcentagens_clientes.editar`;
  - as porcentagens de um campeonato (`porcentagens_campeonatos`): permissão
    `porcentagens_campeonatos.editar`;
  - a cotação de um confronto (`porcentagens_confrontos`): permissão
    `porcentagens_confrontos.editar`;
  - o teto geral (`confrontos_teto_cotacoes`): permissão `confrontos_teto_cotacoes.editar`.
- **FR-059**: Cada alteração de porcentagem ou de teto DEVE poder ser feita para um código de
  cotação (`odd1` a `odd323` ou `jogador`) ou, de uma vez, para todos os códigos com o mesmo valor
  (alteração geral, como no sistema antigo). Os valores DEVEM respeitar FR-034.
- **FR-060**: Para alterar a cotação de um confronto, o usuário informa o código e a cotação que
  quer exibir; o sistema DEVE guardar em `porcentagens_confrontos` a diferença entre a cotação
  informada e a cotação atual do provedor (valor fixo de FR-047), como no sistema antigo. Informar
  a própria cotação do provedor zera o valor fixo. Código com cotação zerada no provedor NÃO DEVE
  poder ser alterado.
- **FR-061**: Alcance por hierarquia:
  - em `porcentagens_vendedores`, o usuário só DEVE alterar as porcentagens dele mesmo ou de um
    subordinado (direto ou indireto);
  - em `porcentagens_campeonatos` e `porcentagens_confrontos` com alvo Vendedores, o registro
    pertence ao próprio usuário ou a um subordinado informado;
  - registros com alvo Clientes ou Todos em `porcentagens_campeonatos` e
    `porcentagens_confrontos`, `porcentagens_clientes` e `confrontos_teto_cotacoes` só DEVEM ser
    alterados por Admin ou Supervisor, mesmo que outra função receba a permissão.
- **FR-062**: As alterações DEVEM valer na listagem pública seguinte, sem esperar a próxima carga.

**Alimentação manual de campeonato**

- **FR-063**: Usuários do painel com permissão DEVEM poder, por rotas do painel, criar à mão
  campeonatos e confrontos, sem provedor, guardados nas mesmas tabelas `campeonatos` e
  `confrontos`. Todo registro criado assim DEVE ficar marcado como manual (`manual`) e sem
  `codigo_externo`.
- **FR-063a**: Campeonato manual: nome, país e bandeira (opcional); nasce ativo e não favorito.
  NÃO DEVE existir dois campeonatos manuais com o mesmo nome e país. Permissões:
  `campeonatos.cadastrar`, `campeonatos.editar` e `campeonatos.excluir`.
- **FR-063b**: Confronto manual: campeonato (manual), time da casa, time de fora, escudos
  (opcionais), esporte, data e hora de início (informada no `fuso_horario` de quem cadastra e
  gravada em UTC, obrigatoriamente futura) e as cotações, por código (`odd1` a `odd323`), com pelo
  menos uma cotação e todas maiores ou iguais a 1,00. Nasce ativo, com situação "Aguardando" e a
  quantidade de cotações calculada (FR-009). Permissões: `confrontos.cadastrar`,
  `confrontos.editar` e `confrontos.excluir`.
- **FR-063c**: Em um confronto manual DEVE ser possível editar times, escudos, esporte, data de
  início, cotações e a situação (Aguardando, Adiado ou Cancelado). A exclusão de campeonato e de
  confronto manual DEVE ser lógica; excluir um campeonato manual exclui também os confrontos dele.
- **FR-064**: Registros manuais e registros do provedor NÃO se misturam:
  - as rotas de FR-063 só DEVEM editar e excluir registros manuais; tentar alterar um registro do
    provedor DEVE ser recusado;
  - as cargas NUNCA DEVEM alterar nem apagar registros manuais, e campeonatos manuais não entram
    na lista de desativados enviada ao provedor (FR-004);
  - o sorteio de `odd4` e `odd7` (FR-008) e a herança por nome e país (FR-007) NÃO se aplicam a
    registros manuais;
  - confronto manual não tem ao vivo: quando a data de início passa, ele sai da listagem do
    pré-jogo.
- **FR-064a**: Na listagem pública, confrontos manuais DEVEM seguir exatamente as mesmas regras dos
  confrontos do provedor: filtros, porcentagens, valor fixo, teto, não permitidos e esportes
  permitidos.

**Gestão de campeonatos e confrontos pelo painel**

- **FR-068**: Usuários do painel com `campeonatos.listar` DEVEM poder listar os campeonatos, e com
  `confrontos.listar` os confrontos do pré-jogo e do ao vivo, paginados (padrão 20, máximo 100),
  com busca por nome (campeonato) ou time (confronto) e filtros por ativo, favorito, manual, país,
  esporte e dia. Cada item DEVE indicar se está ativo, favorito, manual e se está não permitido
  para quem consulta. A cotação original do provedor PODE aparecer nas rotas do painel.
- **FR-069**: Usuários com `campeonatos.alterar_situacao` DEVEM poder ativar e desativar um
  campeonato, e com `confrontos.alterar_situacao` um confronto. A mudança vale para o sistema
  inteiro: desativado, não aparece em nenhuma listagem pública, para nenhum público. Campeonato
  desativado entra na lista enviada ao provedor (FR-004). Confronto desativado também some do ao
  vivo (FR-046a).
- **FR-070**: Usuários com `campeonatos.favoritar` DEVEM poder marcar e desmarcar um campeonato
  como favorito; a mudança vale para o sistema inteiro.
- **FR-071**: Usuários com `campeonatos_nao_permitidos.gerenciar`,
  `confrontos_nao_permitidos.gerenciar` ou `confrontos_ao_vivo_nao_permitidos.gerenciar` DEVEM
  poder marcar e desmarcar, na tabela correspondente, um campeonato, um confronto do pré-jogo ou
  um confronto do ao vivo como não permitido, escolhendo o alvo (FR-033):
  - alvo Vendedores: o registro pertence ao próprio usuário (ou a um subordinado informado) e vale
    para ele e para quem está abaixo dele; o usuário só DEVE desmarcar registros dele ou de
    subordinados;
  - alvos Clientes (para todos os clientes ou para um cliente indicado) e Todos: só DEVEM ser
    marcados e desmarcados por Admin ou Supervisor.
  Marcar duas vezes o mesmo item para o mesmo alvo e dono NÃO DEVE criar registro duplicado.
- **FR-072**: Ativar e desativar (FR-069) e favoritar (FR-070) só DEVEM ser usados por Admin e
  Supervisor, mesmo que outra função receba a permissão. Todas essas alterações DEVEM valer na
  listagem pública seguinte.

**Configurações por público**

- **FR-073**: O sistema DEVE ter uma configuração para cada público, sem tabela padrão: os valores
  iniciais são os valores padrão das colunas.
  - `visitantes_configuracoes`: registro único, para quem não está logado, com
    `esportes_permitidos` (padrão FUTEBOL, HOQUEI NO GELO e BAISEBOL; todos os esportes desde a spec 005), `apostar_outros_esportes`
    (padrão marcado) e `ao_vivo_habilitado` (padrão marcado). O seeder DEVE criar o registro.
  - `clientes_configuracoes` (spec 002): uma linha por cliente, como já existe.
  - `usuarios_configuracoes`: uma linha por vendedor (o único que aposta), com `usuarios_id`
    (único), `esportes_permitidos`, `apostar_outros_esportes`, `ao_vivo_habilitado`,
    `minuto_limite_ao_vivo` (padrão 95) e `cotacao_maxima_ao_vivo` (padrão 30,00), com os mesmos
    padrões de esportes e marcações do visitante.
  As configurações de cada público PODEM ganhar campos próprios em outras specs, sem precisar
  espelhar as dos outros públicos.
- **FR-074**: Na listagem pública, o vendedor logado DEVE usar as próprias
  `usuarios_configuracoes` (FR-041, FR-046b, FR-046c e FR-051). Gerente, Supervisor e Admin
  logados não têm configuração de listagem: veem todos os esportes e usam os valores de
  `configuracoes`.
- **FR-075**: Usuários com `usuarios_configuracoes.editar` DEVEM poder consultar e alterar as
  configurações dos vendedores escolhendo o alcance, sempre dentro da própria hierarquia:
  - Admin: uma supervisão (todos os vendedores abaixo do supervisor), um gerente (todos os
    vendedores dele) ou um vendedor;
  - Supervisor: um gerente dele (todos os vendedores do gerente) ou um vendedor dele;
  - Gerente: um vendedor dele.
  Na consulta, a hierarquia acima seleciona o gerente e vê as configurações de cada vendedor dele.
  Na alteração em massa, só os campos enviados DEVEM ser gravados em todos os vendedores do
  alcance; os campos não enviados mantêm o valor de cada vendedor. Escolher alguém fora da própria
  hierarquia DEVE ser recusado por falta de permissão.
- **FR-076**: Todo vendedor DEVE ter a sua linha em `usuarios_configuracoes`. Ao ser cadastrado
  (cadastro de usuários da spec 001), o vendedor DEVE receber uma cópia da configuração de outro
  vendedor do mesmo gerente; se for o primeiro do gerente, de um vendedor da mesma supervisão; se
  não houver nenhum, os valores padrão das colunas. Os vendedores que já existem DEVEM receber a
  linha com os valores padrão das colunas pelo seeder. Desde a spec 004, os limites de venda
  (`limite_simples`, `limite_duplo` e `limite_geral`) nunca são copiados do colega: o vendedor novo
  os recebe com o valor padrão da coluna.
- **FR-077**: Usuários com `visitantes_configuracoes.editar` DEVEM poder consultar e alterar as
  configurações dos visitantes. Só Admin e Supervisor DEVEM usar essa permissão, mesmo que outra
  função a receba.
- **FR-078**: As alterações de configuração DEVEM valer na listagem pública seguinte. Valores
  DEVEM ser coerentes: `minuto_limite_ao_vivo` de 1 a 130 e `cotacao_maxima_ao_vivo` maior ou
  igual a 1,00; `esportes_permitidos` com pelo menos um esporte.

**Alterações na spec 002 (clientes)**

- **FR-079**: Por decisão do responsável, não existe tabela padrão de configurações. Esta spec
  DEVE remover da spec 002 a tabela `clientes_configuracoes_padrao`, as rotas, o controller e o
  seeder dela e a permissão `clientes.editar_configuracoes_padrao`. As colunas de
  `clientes_configuracoes` DEVEM passar a ter como valor padrão os valores que a spec 002 punha na
  tabela padrão (FR-050 da spec 002), e o cadastro de cliente DEVE criar a configuração do cliente
  novo com esses valores padrão das colunas.
- **FR-080**: Os artefatos da spec 002 (`spec.md`, `plan.md`, `data-model.md`, `contracts/`,
  `quickstart.md` e `tasks.md`) e a coleção do Postman DEVEM ser atualizados na mesma entrega, para
  descreverem o comportamento sem a tabela padrão, como pede a constituição.

**Permissões**

- **FR-065**: DEVEM ser criadas pelo seeder vinte e uma permissões:
  - cotações: `porcentagens_vendedores.editar`, `porcentagens_clientes.editar`,
    `porcentagens_campeonatos.editar`, `porcentagens_confrontos.editar` e
    `confrontos_teto_cotacoes.editar`;
  - campeonatos: `campeonatos.listar`, `campeonatos.cadastrar`, `campeonatos.editar`,
    `campeonatos.excluir`, `campeonatos.alterar_situacao` e `campeonatos.favoritar`;
  - confrontos: `confrontos.listar`, `confrontos.cadastrar`, `confrontos.editar`,
    `confrontos.excluir` e `confrontos.alterar_situacao`;
  - não permitidos: `campeonatos_nao_permitidos.gerenciar`, `confrontos_nao_permitidos.gerenciar`
    e `confrontos_ao_vivo_nao_permitidos.gerenciar`;
  - configurações: `usuarios_configuracoes.editar` e `visitantes_configuracoes.editar`.
  Elas DEVEM seguir as regras de atribuição da spec 001 (permissões diretas no usuário; um gestor
  só dá a subordinados permissões que ele mesmo tem), no mesmo lugar e padrão das permissões de
  usuários e de clientes (`Funcao`). Quem tem a permissão de alterar também consulta os valores
  correspondentes.
- **FR-066**: Permissões padrão por função: Admin e Supervisor recebem as vinte e uma; Gerente
  recebe `porcentagens_vendedores.editar`, `porcentagens_campeonatos.editar`,
  `porcentagens_confrontos.editar`, `campeonatos.listar`, `confrontos.listar`, as três de não
  permitidos e `usuarios_configuracoes.editar`; Vendedor não recebe nenhuma. As demais
  (`porcentagens_clientes.editar`, `confrontos_teto_cotacoes.editar`,
  `visitantes_configuracoes.editar`, cadastrar, editar, excluir, alterar situação e favoritar) só
  DEVEM ser usadas por Admin e Supervisor, mesmo que outra função as receba.
- **FR-067**: Operações do painel sem a permissão correspondente, ou fora do alcance de FR-061,
  FR-071 e FR-072, DEVEM ser recusadas por falta de permissão.

**Segurança e desempenho**

- **FR-053**: A chave de acesso aos provedores DEVE ser lida da configuração do servidor e enviada
  como a API do provedor exige (parâmetros `key` e `app` da URL); a chave, as URLs chamadas e a
  mensagem original de erro das chamadas NUNCA DEVEM aparecer em log.
- **FR-054**: Nenhum dado vindo do provedor ou de parâmetro de requisição DEVE ser usado para
  montar comandos de banco por concatenação de texto.
- **FR-055**: NÃO DEVE existir lista fixa de ids de clientes ou usuários no código; toda redução
  específica DEVE vir das tabelas de porcentagem.
- **FR-056**: A resposta do provedor DEVE poder ser recebida compactada.
- **FR-057**: Operações recusadas DEVEM retornar mensagens claras em português indicando o motivo
  (dado inválido, limite de requisições, sem permissão, não encontrado, não autenticado, ao vivo
  indisponível).

### Key Entities

- **Campeonato** (tabela `campeonatos`): competição vinda do provedor. Atributos: `codigo_externo`,
  nome, país, bandeira, ativo, favorito. Tem muitos confrontos.
- **Confronto** (tabela `confrontos`): jogo do pré-jogo, de um campeonato. Atributos:
  `codigo_externo`, times, escudos, esporte, situação, `data_inicio` (UTC), ativo, quantidade de
  cotações e as 323 cotações.
- **Jogador do confronto** (tabela `confrontos_jogadores`, antiga `atletas`): cotação de um
  jogador num confronto, com opção e tipo.
- **Confronto ao vivo** (tabela `confrontos_ao_vivo`): jogo em andamento, separado do pré-jogo.
  Atributos: `codigo_externo`, campeonato, times, escudos, placar, gols por tempo, escanteios,
  minuto, cronômetro, situação, cotações e data da última atualização.
- **Porcentagem** (tabelas `porcentagens_vendedores`, `porcentagens_vendedores_ao_vivo`,
  `porcentagens_clientes`, `porcentagens_clientes_ao_vivo`, `porcentagens_campeonatos`): ajuste
  percentual da cotação por código, por dono (usuário, clientes em geral ou um cliente) ou por
  campeonato e alvo.
- **Valor fixo do confronto** (tabela `porcentagens_confrontos`): valor somado à cotação de um
  confronto, por código e alvo.
- **Teto de cotação** (tabela `confrontos_teto_cotacoes`): cotação máxima por código, regra geral
  única para todos os públicos.
- **Não permitido** (tabelas `campeonatos_nao_permitidos`, `confrontos_nao_permitidos`,
  `confrontos_ao_vivo_nao_permitidos`): campeonato ou confronto que não é exibido para um alvo
  (Clientes, todos ou um cliente indicado; Vendedores de um usuário da hierarquia; ou Todos).
- **Configurações gerais** (tabela `configuracoes`): registro único com `somente_cassino`,
  `permitir_entrada_campeonatos`, os intervalos do sorteio de `odd4` e `odd7`,
  `ao_vivo_habilitado`, o tempo da trava do ao vivo, o limite de permanência, o minuto limite e a
  cotação máxima do ao vivo (estes dois para visitantes e clientes), e a situação da trava geral do
  ao vivo (travado ou liberado), mudada pela conferência.
- **Configurações do visitante** (tabela `visitantes_configuracoes`): registro único com o que
  quem não está logado vê (esportes, outros esportes e ao vivo).
- **Configurações do vendedor** (tabela `usuarios_configuracoes`): uma linha por vendedor, com
  esportes, outros esportes, ao vivo, minuto limite e cotação máxima do ao vivo; alterada pela
  hierarquia por alcance (supervisão, gerente ou vendedor).
- **Permissões do painel**: as vinte e uma permissões de FR-065 (cotações, campeonatos,
  confrontos, não permitidos e configurações).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Com cerca de 7 mil campeonatos, 4 mil confrontos e 25 mil jogadores, cada carga do
  pré-jogo (campeonatos, confrontos e cotações) termina em menos de 5 segundos.
- **SC-002**: Depois de cada carga bem-sucedida, 100% dos confrontos enviados pelo provedor estão
  no sistema com as mesmas cotações enviadas (exceto `odd4` e `odd7` sorteadas).
- **SC-003**: Em 100% das falhas de uma carga do pré-jogo, nenhum dado daquela carga é alterado.
- **SC-004**: Com a carga do ao vivo parada, nenhum apostador recebe cotação do ao vivo diferente
  de zero depois de 15 segundos da última atualização.
- **SC-005**: Uma mudança de cotação ou de placar no provedor do ao vivo aparece na listagem em
  até 10 segundos.
- **SC-006**: Quando o sistema está mais de 1 minuto atrasado em relação ao segundo provedor, o ao
  vivo inteiro é travado em até 1 minuto.
- **SC-007**: A listagem do pré-jogo de um dia com 500 jogos é entregue em menos de 1 segundo.
- **SC-008**: Em 100% das respostas, a cotação exibida é igual ao resultado do cálculo de FR-047,
  e nenhuma resposta contém porcentagem, valor fixo, teto ou cotação original.
- **SC-009**: Em 100% das listagens, nenhum campeonato ou confronto não permitido para quem está
  vendo aparece, nem entra nas contagens.
- **SC-010**: Em 100% das listagens, as datas e horas exibidas correspondem ao fuso pedido.
- **SC-011**: Em 100% das listagens do pré-jogo, com ou sem busca, nenhum jogo exibido começa
  depois do fim de depois de amanhã no fuso pedido.
- **SC-012**: Uma alteração de porcentagem, de cotação de confronto ou de teto feita pelo painel
  aparece na listagem pública em até 5 segundos.
- **SC-013**: Um usuário do painel cria um campeonato manual com um confronto em menos de 2
  minutos, e o confronto aparece na listagem pública em até 5 segundos depois de salvo.
- **SC-014**: Em 100% das tentativas, um usuário sem a permissão da ação, ou fora da sua
  hierarquia, não consegue consultar nem alterar porcentagens, cotações e teto, nem cadastrar,
  editar ou excluir campeonato e confronto manual, nem ativar, favoritar ou marcar não permitidos.
- **SC-015**: Em 100% das cargas automáticas, nenhum campeonato ou confronto manual é alterado ou
  apagado.
- **SC-016**: Uma alteração em massa nas configurações de uma supervisão com 500 vendedores
  termina em menos de 5 segundos e vale na listagem seguinte de todos eles.
- **SC-017**: Em 100% dos cadastros de vendedor, o vendedor novo já tem configuração, igual à de
  um colega do mesmo gerente quando houver.

## Assumptions

- Telas (frontend) estão fora do escopo. Do painel, esta spec entrega as rotas de alteração das
  cotações (porcentagens, cotação de confronto e teto), o cadastro manual de campeonatos e
  confrontos, a listagem de campeonatos e confrontos, ativar e desativar, favoritar e os não
  permitidos, e as configurações dos vendedores e dos visitantes. Só a edição das configurações
  gerais (`configuracoes`) continua fora; até existir, esses valores são alterados direto no banco.
- Esta spec altera código já implementado de outras specs, por decisão do responsável (Princípio
  IV da constituição): o cadastro de usuários da spec 001 (criar a configuração do vendedor novo,
  FR-076) e as configurações de clientes da spec 002 (remover a tabela padrão, FR-079 e FR-080).
- Por decisão do responsável, as tabelas de porcentagem usam `porcentagens` como nome principal
  (`porcentagens_clientes`, `porcentagens_vendedores`, `porcentagens_campeonatos`,
  `porcentagens_confrontos`), para ficarem listadas juntas; `porcentagens` é tratado como o recurso
  principal delas para a regra de prefixo da constituição e para o nome das permissões.
- Confrontos manuais só podem ser criados em campeonatos manuais; não é possível acrescentar à mão
  um jogo a um campeonato do provedor. Cotações de jogadores não são cadastradas à mão.
- O resultado dos confrontos manuais (placar e encerramento) fica para a spec de resultados; aqui
  o usuário só pode marcar o confronto manual como Adiado ou Cancelado.
- O "bloquear cotação" do sistema antigo (zerar um código de um campeonato ou confronto) é feito
  informando a porcentagem −100 no código; não há rota própria para isso.
- O histórico de alterações de porcentagens (antiga `logs_porcentagens`) não é criado aqui; fica
  para o sistema de logs geral, como definido na spec 002.
- Do `AoVivoController` antigo não foram trazidos: o limite de 2 horas depois do início (o limite
  de permanência e o minuto limite já tiram o jogo encerrado da lista), a lista `variations` da
  resposta (o frontend compara a cotação recebida com a anterior), o `delay_ao_vivo` (é regra de
  aposta) e a `data_trava`.
- A API do provedor não muda: as rotas de campeonatos, confrontos, cotações, ao vivo e conferência
  são as que o sistema antigo já usa, com os mesmos nomes de campo e a chave na URL. Os nomes novos
  (`codigo_externo`, `time_casa`, `minuto`, `cronometro`) existem só dentro do sistema.
- A tabela `configuracoes` nasce só com os campos desta spec; os demais campos da antiga `configs`
  (nome do site, redes sociais, senhas mestre, temas e outros) serão acrescentados pelas specs que
  precisarem deles. Enquanto o painel não existir, os valores são alterados direto no banco.
- A regra antiga de bloquear automaticamente campeonatos novos por gerente (`autorizacao_campeonato`)
  não foi trazida, porque as restrições de exibição não participam da carga; no lugar dela existe a
  opção geral `permitir_entrada_campeonatos`.
- Campeonato que nasce desativado entra na lista de desativados enviada ao provedor (FR-004) e
  deixa de receber confrontos até ser ativado.
- O esporte é guardado como o provedor envia (ex.: `FUTEBOL`, `BASQUETE`, `LUTAS`), igual aos nomes
  já usados em `esportes_permitidos` na spec 002; o cadastro de esportes continua sem existir.
- As situações do confronto seguem o sistema antigo: Aguardando, Encerrado, Cancelado, Adiado e
  Bloqueado no pré-jogo (Bloqueado só vem do provedor; o confronto manual não usa); 1 tempo,
  Intervalo e 2 tempo no ao vivo.
- Os códigos de cotação (`odd1` a `odd323`) são os do provedor; o significado de cada código
  (nome do mercado) não é cadastrado nesta spec.
- A quantidade de cotações disponíveis sempre inclui as cotações de jogadores; a antiga opção de
  ligar e desligar a modalidade de jogadores por gerente não foi trazida.
- As travas antigas `data_trava` e `periodo_jogos` por gerente não existem no sistema novo; no
  lugar delas, a listagem do pré-jogo é sempre limitada a hoje, amanhã e depois de amanhã.
- As listagens só filtram o que é exibido. Permissões de aposta do cliente (`realizar_aposta`,
  `apostar_ao_vivo`) e os limites de odd das configurações do cliente serão aplicados pela spec de
  apostas.
- Usuário do painel de qualquer função pode usar a listagem com seu token; as porcentagens e
  restrições aplicadas são as dele e as de seus superiores. Só o vendedor tem configuração de
  listagem (FR-074), porque é o único que aposta.
- O nome `usuarios_configuracoes` (e não "vendedores") segue o prefixo da tabela `usuarios`; hoje
  só vendedores têm linha nela.
- Se um vendedor mudar de gerente, ele mantém as próprias configurações; não há cópia automática
  nessa troca.
- Quando mais de um usuário da cadeia tem porcentagem de campeonato ou valor fixo de confronto
  para o mesmo código, os valores se somam, como as porcentagens por usuário.
- O teto por código vale para o pré-jogo e o ao vivo; o ao vivo tem, além dele, a cotação máxima
  única das configurações gerais, como no sistema antigo (`odd_maxima_ao_vivo`).
- No ao vivo antigo só a porcentagem do gerente era aplicada; por decisão do responsável, aqui
  valem as porcentagens do ao vivo de toda a cadeia (supervisor, gerente e vendedor) e a de
  clientes, com a mesma regra do pré-jogo (FR-049).
- A conferência considera atraso apenas quando o sistema está atrás do segundo provedor, como no
  sistema antigo.
- Confrontos antigos não são apagados nesta spec; a limpeza fica para a spec de resultados e
  limpeza. As tabelas seguem a constituição (`created_at`, `updated_at` e `deleted_at`).
- Os dados do sistema antigo (`database.sql`) não são migrados; o banco é preenchido pelas cargas.
- O banco de produção é MySQL 8.4, o que permite valor padrão em colunas de lista (FR-073 e
  FR-079).
- As vinte e uma permissões novas seguem o padrão `<recurso>.<acao>` da constituição e o mesmo
  lugar das permissões de usuários e de clientes (`Funcao`). Ajustes individuais continuam pela gestão de
  permissões da spec 001.
