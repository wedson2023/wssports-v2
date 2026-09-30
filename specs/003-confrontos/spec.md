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

## Clarifications

### Session 2026-09-30

- Q: De onde vem o teto de cotação que vale para visitantes e clientes do site? → A: De uma regra
  geral única, por código de cotação, que vale igual para visitantes, clientes e vendedores
  (tabela `tetos_cotacao`). Não existe teto por usuário nem por cliente; a tabela
  `usuarios_tetos_cotacao` prevista no pedido deixa de existir.
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

## User Scenarios & Testing *(mandatory)*

> Conforme a constituição, o projeto não terá testes automatizados. Os cenários abaixo são
> critérios de aceite validados manualmente.

### User Story 1 - Carga do pré-jogo (Priority: P1)

De 5 em 5 minutos, o sistema busca no provedor de cotações, numa única chamada, todos os
campeonatos com seus confrontos, cotações e jogadores, e grava tudo de uma vez. A banca passa a ter
os jogos do dia e dos próximos dias sempre atualizados, sem depender de três cargas em horários
diferentes.

**Why this priority**: sem os jogos e as cotações no banco não há o que listar nem o que apostar;
é a base de todas as outras stories.

**Independent Test**: rodar `confrontos:importar` com o provedor respondendo um JSON conhecido e
conferir no banco os campeonatos, confrontos, cotações e jogadores; rodar de novo com uma cotação
alterada e conferir que o confronto foi atualizado, sem duplicar.

**Acceptance Scenarios**:

1. **Given** o banco sem jogos e o provedor devolvendo 2 campeonatos com 3 confrontos, **When** a
   carga roda, **Then** existem 2 campeonatos e 3 confrontos, cada confronto com suas cotações,
   seus jogadores e a quantidade de cotações disponíveis.
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

---

### User Story 2 - Listagem pública de jogos com cotação ajustada (Priority: P1)

Um visitante, um cliente logado ou um vendedor logado abre a lista de jogos do período que quiser,
no seu fuso horário, e vê os confrontos agrupados por campeonato, com as quatro cotações
principais já no valor que vale para ele.

**Why this priority**: é a vitrine do site; sem ela o apostador não vê os jogos.

**Independent Test**: com jogos carregados, chamar a listagem sem login informando
`data_inicial` e `data_final` e conferir os jogos do período, agrupados por campeonato, com data
e hora no fuso pedido e as cotações ajustadas.

**Acceptance Scenarios**:

1. **Given** jogos futuros em dois campeonatos ativos, **When** um visitante lista o período que
   os contém, **Then** vê os confrontos agrupados por campeonato, cada um com times, escudos, data
   e hora de início, minutos até o início, `odd1` a `odd4` e a quantidade de cotações disponíveis;
   vê também a lista de países com seus campeonatos e quantidade de jogos, e o total de jogos.
2. **Given** um jogo que começa às 23:30 (UTC) do dia 30, **When** a listagem é pedida com
   `fuso_horario` `-03:00` para o dia 30, **Then** o jogo aparece com início às 20:30 do dia 30;
   com `fuso_horario` `-05:00`, aparece às 18:30.
3. **Given** um jogo que começa às 01:00 (UTC) do dia 1º, **When** a listagem é pedida com
   `fuso_horario` `-03:00` para o dia 30, **Then** o jogo aparece (22:00 do dia 30 nesse fuso).
4. **Given** a listagem sem `data_inicial` ou sem `data_final` e sem `busca`, **When** ela é
   pedida, **Then** é recusada como dado inválido.
5. **Given** `busca` "Flamengo", **When** a listagem é pedida, **Then** aparecem todos os jogos
   futuros em que o time da casa ou o de fora contém "Flamengo", mesmo fora do período informado.
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

O visitante, cliente ou vendedor vê os jogos em andamento com placar, minuto, cronômetro e as
cotações principais ajustadas pelas regras do ao vivo.

**Why this priority**: entrega o ao vivo ao apostador, mas depende da carga (story 4) e do cálculo
(story 3).

**Independent Test**: com jogos ao vivo carregados, listar o ao vivo e conferir placar, minuto e
cotações; gravar uma porcentagem do ao vivo e conferir o novo valor.

**Acceptance Scenarios**:

1. **Given** dois jogos em andamento, **When** o ao vivo é listado, **Then** aparecem agrupados
   por campeonato, com times, escudos, placar, minuto, cronômetro, situação, `odd1` a `odd4`
   ajustadas e a quantidade de cotações disponíveis.
2. **Given** porcentagem de clientes −10 no pré-jogo e −20 no ao vivo em `odd1`, **When** um
   visitante lista o ao vivo, **Then** o ajuste aplicado é −20.
3. **Given** um jogo marcado como não permitido só no ao vivo, **When** as duas listagens são
   pedidas, **Then** ele não aparece no ao vivo e, se ainda estiver no pré-jogo, aparece lá.
4. **Given** um jogo sem atualização há mais tempo que o limite de permanência, **When** o ao vivo
   é listado, **Then** o jogo não aparece mais.

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

---

### Edge Cases

- Campeonato que chega do provedor sem nenhum confronto é gravado normalmente.
- Confronto que chega com situação diferente de "Aguardando" (Encerrado, Cancelado, Adiado) é
  atualizado e deixa de aparecer na listagem do pré-jogo.
- Confronto que some da resposta do provedor não é apagado pela carga; deixa de aparecer quando o
  horário de início passa.
- Cotação com código fora de `odd1` a `odd323`, valor negativo ou não numérico é tratada como dado
  inválido daquele confronto: o confronto é ignorado nessa carga e o motivo fica em log, sem
  derrubar o restante.
- Dois campeonatos com o mesmo nome em países diferentes são campeonatos diferentes (a herança por
  nome considera nome e país).
- Intervalo de sorteio não configurado ou com mínimo maior que o máximo: a cotação continua zerada.
- Jogo do ao vivo cujo campeonato ainda não existe no sistema é ignorado naquela carga e o motivo
  fica em log; entra assim que a carga do pré-jogo criar o campeonato.
- Jogo que termina deixa de vir do provedor do ao vivo: fica travado após 15 segundos e sai da
  listagem após o limite de permanência.
- `data_inicial` maior que `data_final` é recusada como dado inválido.
- Período informado só com datas (sem hora) cobre do primeiro ao último segundo de cada dia, no
  fuso pedido.
- Jogo que começa exatamente agora não aparece no pré-jogo (só início futuro).
- Porcentagem que levaria a cotação abaixo de 1,00 resulta em 1,00; cotação zerada nunca vira 1,00.
- Usuário do painel sem porcentagem cadastrada conta como porcentagem zero.
- Código de cotação sem teto geral cadastrado não tem limite.
- Visitante e cliente sem regra específica usam só a regra geral de clientes; sem regra geral, a
  porcentagem de clientes é zero. Cliente com regra específica e sem regra geral usa só a dele.

## Requirements *(mandatory)*

### Functional Requirements

**Carga do pré-jogo**

- **FR-001**: O sistema DEVE ter um único comando, `confrontos:importar`, que busca o pré-jogo no
  provedor de cotações em uma única chamada, agendado a cada 5 minutos e sem execuções sobrepostas.
  Ele substitui os antigos `fonte:campeonatos`, `fonte:confrontos` e `fonte:cotacao`.
- **FR-002**: A resposta do provedor DEVE ser um JSON aninhado no formato:
  `campeonatos[] { codigo_externo, nome, pais, bandeira, confrontos[] { codigo_externo, time_casa,
  escudo_casa, time_fora, escudo_fora, esporte, situacao, data_inicio, cotacoes { odd1, odd3, ... },
  jogadores[] { codigo_externo, nome, opcao, tipo, odd } } }`, com `data_inicio` em horário
  universal (UTC).
- **FR-003**: O sistema DEVE aceitar e guardar os 323 códigos de cotação (`odd1` a `odd323`);
  código ausente ou zerado DEVE valer zero (indisponível).
- **FR-004**: Na chamada, o sistema DEVE enviar ao provedor a lista de `codigo_externo` dos
  campeonatos desativados, para que não retornem.
- **FR-005**: Campeonatos, confrontos e jogadores DEVEM ser gravados por inserir-ou-atualizar em
  lote, pela chave `codigo_externo`, numa única transação: ou a carga inteira é gravada, ou nada
  muda. Rodar a mesma carga duas vezes NÃO DEVE criar registros duplicados.
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
- **FR-011**: Falha na chamada (provedor fora do ar, tempo esgotado, erro) ou JSON fora do formato
  de FR-002 NÃO DEVE alterar nenhum dado e DEVE ser registrada em log. Um confronto com dado
  inválido DEVE ser ignorado, com o motivo em log, sem impedir a gravação dos demais.
- **FR-012**: A carga do pré-jogo, a carga do ao vivo e a conferência NÃO DEVEM rodar quando o
  sistema estiver configurado só para cassino.
- **FR-013**: As cargas NÃO DEVEM consultar as tabelas de restrição de exibição nem as de
  porcentagem: gravam tudo o que o provedor mandar.

**Carga do ao vivo**

- **FR-014**: O sistema DEVE ter um comando separado, `confrontos_ao_vivo:importar`, que busca no
  provedor os jogos em andamento a cada 5 segundos, sem execuções sobrepostas, enviando a lista de
  campeonatos desativados (como em FR-004). Ele substitui o antigo `fonte:aovivo`.
- **FR-015**: Para cada jogo em andamento, o sistema DEVE gravar ou atualizar, pela chave
  `codigo_externo`: campeonato, times, escudos, esporte, `data_inicio`, `placar_casa`,
  `placar_fora`, gols de cada time no primeiro e no segundo tempo, escanteios de cada time,
  `minuto`, `cronometro`, situação (1 tempo, Intervalo ou 2 tempo), as cotações (`odd1` a `odd323`,
  como em FR-003), a quantidade de cotações disponíveis e a data da última atualização recebida.
- **FR-016**: Os jogos do ao vivo DEVEM ficar em tabela própria (`confrontos_ao_vivo`), separada
  dos confrontos do pré-jogo, e a regra de sorteio de FR-008 NÃO se aplica a eles.
- **FR-017**: Trava do ao vivo: um jogo cuja última atualização tenha mais de 15 segundos (valor
  das configurações gerais, FR-035a) DEVE ser entregue ao apostador com todas as cotações zeradas e marcado como
  travado, nunca com valor antigo. A trava DEVE valer pela data da última atualização, sem depender
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
  minuto do mesmo jogo num segundo provedor. Ele substitui o antigo `comparar:aovivo`.
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
  DEVEM usar o id próprio.
- **FR-027**: `campeonatos` DEVE guardar: `codigo_externo`, `nome`, `pais`, `bandeira`, `ativo` e
  `favorito`.
- **FR-028**: `confrontos` DEVE guardar: `codigo_externo`, `campeonatos_id`, `time_casa`,
  `escudo_casa`, `time_fora`, `escudo_fora`, `esporte`, `situacao`, `data_inicio`, `ativo`,
  `quantidade_cotacoes` e `cotacoes` (as 323 cotações do confronto numa única coluna, no lugar das
  323 colunas do sistema antigo).
- **FR-029**: `confrontos_jogadores` (antiga `atletas`) DEVE guardar, por confronto:
  `codigo_externo`, `nome`, `opcao`, `tipo` e `odd`.
- **FR-030**: As regras de porcentagem DEVEM ficar em:
  - `usuarios_porcentagens` (antigas `porcentagens_supervisors`, `porcentagens_gerentes` e
    `porcentagens_vendedors`): porcentagem por usuário do painel e código de cotação (`odd1` a
    `odd323` e `jogador`), para o pré-jogo;
  - `usuarios_porcentagens_ao_vivo` (antiga `porcentagens_ao_vivos`): o mesmo, para o ao vivo;
  - `clientes_porcentagens` e `clientes_porcentagens_ao_vivo` (novas): porcentagem por código de
    cotação para os clientes do site, com uma regra geral (vale para visitantes e para todos os
    clientes) e, opcionalmente, uma regra de um cliente específico, que soma com a geral;
  - `campeonatos_porcentagens` (antiga `porcentagens_campeonatos`): porcentagem por campeonato,
    código de cotação e alvo (FR-033);
  - `confrontos_porcentagens` (antiga `porcentagens_confrontos`): valor fixo, em cotação, somado
    por confronto, código de cotação e alvo (FR-033).
- **FR-031**: `tetos_cotacao` (antiga `teto_cotacaos`) DEVE guardar a cotação máxima por código de
  cotação (`odd1` a `odd323` e `jogador`), numa regra geral única, sem dono: não há teto por
  usuário nem por cliente.
- **FR-032**: As restrições de exibição DEVEM ficar em `campeonatos_nao_permitidos` (antiga
  `campeonatos_gerencias`), `confrontos_nao_permitidos` (antiga `confrontos_gerencias`) e
  `confrontos_ao_vivo_nao_permitidos` (restrição do ao vivo, separada da do pré-jogo). Elas são
  usadas só nas listagens públicas, nunca nas cargas.
- **FR-033**: Cada registro de `campeonatos_porcentagens`, `confrontos_porcentagens` e das tabelas
  de não permitidos DEVE ter um alvo: **Clientes** (vale para o site inteiro: visitantes e
  clientes) ou **Vendedores** (o registro pertence a um usuário do painel e vale para ele e para
  todos os usuários abaixo dele na hierarquia).
- **FR-034**: Porcentagens DEVEM aceitar valores negativos (reduzem a cotação) e positivos
  (aumentam), de −100 a 100, com 2 casas decimais. Tetos DEVEM ser maiores ou iguais a 1,00.
- **FR-035**: Esta spec cria as tabelas de porcentagens, tetos e não permitidos e as aplica nas
  listagens; o cadastro e a edição desses registros pelo painel NÃO fazem parte desta spec.
- **FR-035a**: O sistema DEVE ter um registro único de configurações gerais (tabela
  `configuracoes`, substitui a antiga `configs`), com os campos usados por esta spec:
  - `somente_cassino`: quando marcado, as cargas e a conferência não rodam (FR-012);
  - `permitir_entrada_campeonatos`: quando marcado, campeonato novo entra ativo na carga (FR-006);
  - intervalo do sorteio de `odd4` (ambas marcam): mínimo e máximo (FR-008);
  - intervalo do sorteio de `odd7` (ambas não marcam): mínimo e máximo (FR-008);
  - tempo da trava do ao vivo, em segundos (FR-017);
  - limite de permanência do ao vivo, em minutos (FR-020);
  - situação da trava geral do ao vivo: travado ou liberado, alterada pela conferência (FR-022 e
    FR-023).
- **FR-035b**: O seeder DEVE criar o registro de configurações gerais com os valores do sistema
  antigo (`database.sql`) e os padrões desta spec: `somente_cassino` desmarcado;
  `permitir_entrada_campeonatos` marcado; sorteio de `odd4`
  de 1,30 a 1,50; sorteio de `odd7` de 1,45 a 1,55; trava do ao vivo em 15 segundos; permanência em
  5 minutos; trava geral liberada. A edição dessas configurações pelo painel NÃO faz parte desta
  spec.
- **FR-035c**: Os endereços e as chaves de acesso dos provedores NÃO DEVEM ficar na tabela
  `configuracoes`; ficam na configuração do servidor (FR-053).

**Listagem pública do pré-jogo**

- **FR-036**: O sistema DEVE oferecer uma listagem pública de jogos do pré-jogo, acessível sem
  login. Ela DEVE aceitar também o token de um cliente ou de um usuário do painel; token inválido
  ou expirado DEVE ser tratado como visitante, e a resposta DEVE indicar que o token não foi
  aceito.
- **FR-037**: Filtros da listagem:
  - `data_inicial` e `data_final`: obrigatórios quando não houver `busca`; aceitam data ou data e
    hora;
  - `busca`: parte do nome do time da casa ou do time de fora; com `busca`, o período informado é
    ignorado e valem todos os jogos futuros;
  - `esporte`: padrão Futebol;
  - `somente_favoritos`: só jogos de campeonatos favoritos;
  - `fuso_horario`: deslocamento no formato `±HH:MM`; padrão `-03:00`;
  - paginação: `pagina` e `por_pagina` (padrão 50, máximo 100).
- **FR-038**: O período informado DEVE ser interpretado no `fuso_horario` pedido, e as datas e
  horas da resposta DEVEM ser convertidas para ele, pois o sistema atende regiões com fusos
  diferentes.
- **FR-039**: Só DEVEM entrar na listagem confrontos ativos, de campeonato ativo, com situação
  "Aguardando" e `data_inicio` futura.
- **FR-040**: NÃO DEVEM entrar na listagem os campeonatos e confrontos não permitidos para quem
  está vendo: para visitante e cliente, os de alvo Clientes; para usuário do painel, os de alvo
  Vendedores que pertencem a ele ou a qualquer superior dele.
- **FR-041**: Só DEVEM entrar jogos dos esportes permitidos a quem está vendo: para o cliente
  logado, os `esportes_permitidos` das configurações dele e, se `apostar_outros_esportes` estiver
  desmarcado, só Futebol; para o visitante, o mesmo, pelas configurações padrão de clientes; para
  usuário do painel, todos os esportes.
- **FR-042**: A resposta DEVE trazer:
  - os confrontos da página agrupados por campeonato, ordenados por campeonato favorito primeiro,
    nome do campeonato, `data_inicio` e `time_casa`;
  - em cada confronto: id, times, escudos, esporte, data e hora de início (no fuso pedido),
    minutos até o início, `odd1` a `odd4` já ajustadas (FR-047) e a quantidade de cotações
    disponíveis;
  - a lista de países, cada um com seus campeonatos (nome, bandeira e quantidade de jogos),
    considerando todo o resultado do filtro, não só a página;
  - o total de jogos do filtro e os dados de paginação.
- **FR-043**: Todo parâmetro da listagem DEVE ser validado; parâmetro inválido (período ausente ou
  invertido, fuso em formato inválido, página fora do limite) DEVE ser recusado com mensagem clara
  em português.
- **FR-044**: As listagens públicas DEVEM ter limite de requisições por IP (padrão de 120 por
  minuto); acima dele, a requisição DEVE ser recusada informando o tempo de espera.

**Listagem pública do ao vivo**

- **FR-045**: O sistema DEVE oferecer uma listagem pública do ao vivo, com as mesmas regras de
  acesso (FR-036), esportes (FR-041), limite de requisições (FR-044) e paginação, e com os filtros
  `busca` e `fuso_horario`. Ela DEVE trazer os jogos em andamento (situação 1 tempo, Intervalo ou
  2 tempo) de campeonato ativo, agrupados por campeonato, cada um com times, escudos, placar,
  `minuto`, `cronometro`, situação, `odd1` a `odd4` ajustadas, a quantidade de cotações disponíveis
  e a indicação de travado.
- **FR-046**: A listagem do ao vivo DEVE usar as tabelas do ao vivo (`usuarios_porcentagens_ao_vivo`,
  `clientes_porcentagens_ao_vivo` e `confrontos_ao_vivo_nao_permitidos`), além das regras por
  campeonato (porcentagem e não permitidos), e DEVE respeitar a trava por jogo (FR-017), a trava
  geral (FR-022) e o limite de permanência (FR-020).

**Cálculo da cotação exibida**

- **FR-047**: A cotação exibida DEVE ser calculada assim, para cada código de cotação:
  `cotação final = cotação do provedor + cotação do provedor × (soma das porcentagens aplicáveis)
  ÷ 100 + valor fixo do confronto`. Em seguida: limitada ao teto aplicável; nunca menor que 1,00;
  arredondada em 2 casas decimais.
- **FR-048**: Cotação zerada no provedor (indisponível) ou zerada por trava DEVE continuar zerada,
  sem nenhum ajuste.
- **FR-049**: Porcentagens aplicáveis:
  - para visitante e cliente: a regra geral de clientes, mais a regra específica do cliente
    logado quando existir (as duas se somam), mais a do campeonato com alvo Clientes;
  - para usuário do painel: a dele e a de todos os seus superiores (para o vendedor: supervisor,
    gerente e vendedor), mais as do campeonato com alvo Vendedores que pertencem a ele ou a um
    superior dele.
- **FR-050**: O valor fixo do confronto aplicável segue a mesma regra de alvo de FR-049 (alvo
  Clientes para visitante e cliente; alvo Vendedores da cadeia do usuário do painel).
- **FR-051**: O teto aplicável DEVE ser o teto geral do código de cotação (FR-031), o mesmo para
  visitante, cliente e usuário do painel, no pré-jogo e no ao vivo; código sem teto cadastrado não
  tem limite.
- **FR-052**: As porcentagens, os valores fixos, os tetos e a cotação original do provedor NUNCA
  DEVEM aparecer nas respostas das listagens.

**Segurança e desempenho**

- **FR-053**: A chave de acesso aos provedores DEVE ser enviada em cabeçalho e lida da
  configuração do sistema; NUNCA DEVE ir na URL nem aparecer em log.
- **FR-054**: Nenhum dado vindo do provedor ou de parâmetro de requisição DEVE ser usado para
  montar comandos de banco por concatenação de texto.
- **FR-055**: NÃO DEVE existir lista fixa de ids de clientes ou usuários no código; toda redução
  específica DEVE vir das tabelas de porcentagem.
- **FR-056**: A resposta do provedor DEVE poder ser recebida compactada.
- **FR-057**: Operações recusadas DEVEM retornar mensagens claras em português indicando o motivo
  (dado inválido, limite de requisições).

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
- **Porcentagem** (tabelas `usuarios_porcentagens`, `usuarios_porcentagens_ao_vivo`,
  `clientes_porcentagens`, `clientes_porcentagens_ao_vivo`, `campeonatos_porcentagens`): ajuste
  percentual da cotação por código, por dono (usuário, clientes em geral ou um cliente) ou por
  campeonato e alvo.
- **Valor fixo do confronto** (tabela `confrontos_porcentagens`): valor somado à cotação de um
  confronto, por código e alvo.
- **Teto de cotação** (tabela `tetos_cotacao`): cotação máxima por código, regra geral única para
  todos os públicos.
- **Não permitido** (tabelas `campeonatos_nao_permitidos`, `confrontos_nao_permitidos`,
  `confrontos_ao_vivo_nao_permitidos`): campeonato ou confronto que não é exibido para um alvo
  (Clientes, ou Vendedores de um usuário da hierarquia).
- **Configurações gerais** (tabela `configuracoes`): registro único com `somente_cassino`,
  `permitir_entrada_campeonatos`, os
  intervalos do sorteio de `odd4` e `odd7`, o tempo da trava do ao vivo, o limite de permanência e
  a situação da trava geral do ao vivo (travado ou liberado), mudada pela conferência.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A carga completa do pré-jogo, com cerca de 7 mil campeonatos, 4 mil confrontos e
  25 mil jogadores, termina em menos de 5 segundos.
- **SC-002**: Depois de cada carga bem-sucedida, 100% dos confrontos enviados pelo provedor estão
  no sistema com as mesmas cotações enviadas (exceto `odd4` e `odd7` sorteadas).
- **SC-003**: Em 100% das falhas de carga do pré-jogo, nenhum dado existente é alterado.
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

## Assumptions

- Telas (frontend) e o painel de gestão estão fora do escopo. Enquanto o painel não existir,
  porcentagens, tetos, não permitidos, `ativo` e `favorito` são preenchidos direto no banco ou por
  seeder para a validação manual.
- O provedor de cotações passará a devolver o pré-jogo no formato aninhado de FR-002; esse ajuste
  na API do provedor é uma dependência externa a esta spec.
- O formato da resposta do provedor do ao vivo e do segundo provedor (conferência) segue o que já
  existe hoje, com os nomes novos (`codigo_externo`, `time_casa`, `minuto`, `cronometro`); os
  detalhes ficam para o plano.
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
- As situações do confronto seguem o sistema antigo: Aguardando, Encerrado, Cancelado e Adiado no
  pré-jogo; 1 tempo, Intervalo e 2 tempo no ao vivo.
- Os códigos de cotação (`odd1` a `odd323`) são os do provedor; o significado de cada código
  (nome do mercado) não é cadastrado nesta spec.
- A quantidade de cotações disponíveis sempre inclui as cotações de jogadores; a antiga opção de
  ligar e desligar a modalidade de jogadores por gerente não foi trazida.
- As travas antigas `data_trava` e `periodo_jogos` (limite de dias à frente) não existem no sistema
  novo e não são aplicadas; com `busca`, valem todos os jogos futuros.
- As listagens só filtram o que é exibido. Permissões de aposta do cliente (`realizar_aposta`,
  `apostar_ao_vivo`) e os limites de odd das configurações do cliente serão aplicados pela spec de
  apostas.
- Usuário do painel de qualquer função pode usar a listagem com seu token; as porcentagens e
  restrições aplicadas são as dele e as de seus superiores. Usuários do painel veem todos os
  esportes, pois ainda não existem configurações por usuário.
- Quando mais de um usuário da cadeia tem porcentagem de campeonato ou valor fixo de confronto
  para o mesmo código, os valores se somam, como as porcentagens por usuário.
- O teto de cotação é um só para o pré-jogo e o ao vivo, como no sistema antigo.
- A conferência considera atraso apenas quando o sistema está atrás do segundo provedor, como no
  sistema antigo.
- Confrontos antigos não são apagados nesta spec; a limpeza fica para a spec de resultados e
  limpeza. As tabelas seguem a constituição (`created_at`, `updated_at` e `deleted_at`).
- Os dados do sistema antigo (`database.sql`) não são migrados; o banco é preenchido pelas cargas.
- Nenhuma permissão nova é criada, pois não há rotas de gestão nesta spec.
