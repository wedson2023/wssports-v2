# Feature Specification: Painel — Cotações (porcentagens, valor do jogo e teto)

**Feature Branch**: `016-painel-cotacoes`

**Created**: 2026-10-21

**Status**: Draft

**Input**: User description: "Spec 016: tela de porcentagens (cotações) no painel. O backend já existe
desde a spec 003 (Confrontos): porcentagens de vendedores e de clientes (pré-jogo e ao vivo), por
campeonato e por jogo (alvo Todos/Clientes/Vendedores), teto das cotações e o cálculo da cotação
exibida. Falta a tela no painel. Telas do antigo: screens/adm/price, price-sellers e
championships-price. Proposta: tela 'Cotações' com abas por camada (Público, Campeonatos, Jogos e
Tetos), tipos de cotação agrupados por mercado com 'aplicar a todos', prévia do cálculo em tempo real
mostrando de onde vem cada cotação, selos de quem tem regra nas listas, busca de campeonatos com
filtro por país, alvo/rede/cliente escolhidos de lista, cada nível só na sua rede, uma permissão por
camada e a visão 'Regras ativas'. Design com UI e UX bem aplicadas, responsivo (prioridade ao
celular), usando os padrões das specs 014 e 015."

## Clarifications

### Session 2026-10-21 (decisões tomadas antes do `/speckit-specify`)

- Q: Precisa de backend novo para as regras? → A: Não para gravar: as regras, o cálculo e as rotas de
  consulta e alteração de cada camada existem desde a spec 003 e continuam como estão. Entram só as
  consultas que a tela precisa e ainda não existem (listas com a marca de quem tem regra, prévia do
  cálculo e Regras ativas) e o cadeado por cotação (ver as respostas abaixo).
- Q: As permissões das camadas são novas? → A: Não. Usa as permissões que já existem, uma por camada:
  porcentagens de vendedores, de clientes, de campeonatos, de jogos e teto (spec 003), com o mesmo
  padrão por função de `Funcao.php`.
- Q: O visual pode mudar em relação ao antigo (Princípio VIII)? → A: Sim. O responsável pediu UI e UX
  bem aplicadas no padrão do painel novo (specs 014 e 015). As diferenças aprovadas estão listadas em
  "Referências do sistema antigo".

### Session 2026-10-21 (respostas do responsável às perguntas do `/speckit-specify`)

- Q: "Porcentagem por região" é o país do campeonato, o local do cliente ou não é preciso? → A: Não é
  preciso. Não há atalho por país nem regra por região; o ajuste é feito campeonato por campeonato.
  O filtro por país na lista de campeonatos continua, só para encontrar os campeonatos.
- Q: As camadas continuam somando ou a mais específica substitui? → A: Continuam somando, como hoje
  (público + superiores + campeonato, mais o valor do jogo). O cálculo do site não muda; a tela só
  mostra cada parcela.
- Q: O cadeado por cotação do antigo entra? → A: Sim, nesta spec. O superior pode trancar uma cotação
  para os níveis abaixo dele na rede: com o cadeado fechado, quem está abaixo não altera a
  porcentagem daquela cotação (FR-019 a FR-019c). Com o histórico de alterações (FR-018a, decidido
  no `/speckit-clarify`), são as únicas regras novas no servidor desta spec.

### Session 2026-10-21 (`/speckit-clarify`)

- Q: Quando o Admin ou um Supervisor tranca uma cotação, a trava vale para toda a rede dele ou só
  para o usuário que está editando e quem está abaixo desse usuário? → A: Só para o usuário editado
  e quem está abaixo dele, como no antigo (o cadeado é posto na tela de um usuário escolhido).
- Q: O sistema deve guardar um histórico das alterações de cotação (quem mudou, quando, de qual valor
  para qual)? → A: Sim, histórico simples: cada gravação e cada trancar/destrancar registram quem,
  quando, a camada, o alvo e as cotações com o valor antes e depois; em Regras ativas, cada regra
  tem "ver histórico" (FR-018a). Sem tela própria de auditoria.

## Referências do sistema antigo *(Princípios VII e VIII)*

- Telas: `resources/js/screens/adm/price` ("Alterar cotação – geral", rota `/adm/alterar-cotacao`),
  `price-sellers` (cotação de um vendedor, `/adm/alterar-odd-vendedor/:id`), `championships-price`
  (cotação de um campeonato, `/adm/alterar-odd-campeonato/:id`) e `matches-price` (cotação de um
  jogo, `/adm/alterar-odd-confronto/:id`).

**Como funcionava**:

- **Alterar cotação – geral**: abas PORCENTAGEM e TETO; seletor PRÉ JOGO / AO VIVO; seletor de
  usuário (para o Admin, "Sistema" = clientes do site, ou um usuário da rede); busca pelo nome da
  cotação; lista com uma linha por cotação (nome do mercado, campo do valor, botão de gravar a
  linha e cadeado para bloquear a cotação para o nível de baixo); botão "alterar todas as cotações
  de uma vez".
- **Vendedor, campeonato e jogo**: a mesma lista, aberta a partir da tela do vendedor, do campeonato
  ou do jogo; no jogo, o título mostra o confronto e quantas cotações ele tem.
- **Quem mexia**: Admin em tudo; Supervisor e Gerente conforme as "travas" de cada um.

**Diferenças aprovadas** (pedido do responsável):

- Uma tela só, "Cotações", com abas por camada, no lugar de quatro telas separadas.
- Cotações agrupadas por mercado (Vencedor, Ambas marcam, Dupla chance etc.), com busca, no lugar de
  uma lista única de 323 linhas.
- Prévia do cálculo mostrando de onde vem cada cotação.
- Gravar as alterações de uma vez por botão Salvar, no lugar do botão de gravar em cada linha (o
  cadeado de cada cotação continua, com efeito imediato).
- Selos de "tem regra" nas listas de campeonatos e jogos.
- Visão "Regras ativas" (não existia).
- Visual do painel novo: tema (cor principal, fundo claro ou escuro), abas, modais com
  `ModalHeader`, campos com rótulo em cima e listas do celular com as colunas principais.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Ajustar as cotações de um público (Priority: P1)

O usuário do painel abre "Cotações", escolhe o público (os clientes do site ou um usuário da rede
dele), o tipo (pré-jogo ou ao vivo) e ajusta as porcentagens por cotação, agrupadas por mercado,
podendo aplicar um valor a todas de uma vez. Ao lado, uma prévia mostra como fica a cotação de um
jogo de exemplo, com cada parcela do cálculo.

**Why this priority**: é o ajuste mais usado no dia a dia (margem da banca no site e de cada
vendedor) e, sozinho, já substitui a tela "Alterar cotação – geral" do antigo.

**Independent Test**: como Admin, escolher "Clientes do site", pré-jogo, digitar −10 em "Casa"
(odd1), ver na prévia um jogo com 2,00 passar a 1,80, salvar e conferir 1,80 na listagem pública;
como Gerente, repetir num vendedor dele e conferir que os clientes do site não aparecem como
opção.

**Acceptance Scenarios**:

1. **Given** um Admin na aba Público, **When** ele abre a lista de públicos, **Then** vê "Clientes do
   site" (regra geral), a opção de escolher um cliente específico e os usuários da rede dele
   agrupados por função, todos escolhidos de lista, sem digitar.
2. **Given** um público escolhido, **When** a tela carrega, **Then** as cotações aparecem agrupadas
   por mercado, cada uma com o nome legível e o valor atual (em branco quando não há ajuste), e o
   alternador pré-jogo / ao vivo mostra qual tipo está sendo editado.
3. **Given** valores alterados e ainda não salvos, **When** o usuário olha a tela, **Then** cada campo
   alterado fica destacado, o botão Salvar mostra quantas alterações há, e sair da tela ou trocar de
   público pede confirmação para não perder as mudanças.
4. **Given** o usuário digita −5 em "aplicar a todas" de um mercado (ou de todos os mercados), **When**
   ele confirma, **Then** todos os campos daquele grupo recebem −5 como alteração pendente, visível
   antes de salvar.
5. **Given** alterações pendentes, **When** o usuário salva, **Then** só as cotações alteradas são
   gravadas, aparece a confirmação de sucesso e a prévia passa a refletir os valores gravados.
6. **Given** um valor fora de −100 a 100, **When** o usuário tenta salvar, **Then** o campo fica
   marcado com a mensagem do erro e nada é gravado até corrigir.
7. **Given** um Vendedor, **When** ele tenta abrir "Cotações", **Then** o item não aparece no menu e o
   acesso direto volta para a Dashboard.
8. **Given** um usuário da rede escolhido, **When** a tela mostra as porcentagens dele, **Then** mostra
   também, só para leitura, a soma que ele já recebe dos superiores, para que fique claro o total
   que vale para os vendedores abaixo.
9. **Given** um Supervisor que trancou "Casa" no pré-jogo, **When** um Gerente da rede dele abre as
   porcentagens, **Then** "Casa" aparece só para leitura com o cadeado fechado e "trancada por
   <Supervisor>", e "aplicar a todas" altera as outras cotações e avisa que pulou 1 trancada.

---

### User Story 2 - Ajustar as cotações de um campeonato (Priority: P2)

O usuário encontra um campeonato pela busca ou pelo filtro de país (com bandeira), vê na lista quais
já têm regra, abre o campeonato e ajusta as porcentagens para um alvo (Todos, Clientes ou os
vendedores de uma rede), com a mesma grade por mercado e a mesma prévia.

**Why this priority**: é o segundo ajuste mais comum (campeonatos de maior risco); depende do
formulário da P1, mas é testável sozinho.

**Independent Test**: buscar "Brasileirão", ver o selo "sem ajuste", abrir, escolher alvo Clientes,
aplicar +3 em "Casa", salvar, ver o selo "+3% · Clientes" na lista e o valor refletido na listagem
pública de um jogo desse campeonato.

**Acceptance Scenarios**:

1. **Given** a aba Campeonatos, **When** ela abre, **Then** lista os campeonatos paginados (até 100
   por página), com nome, país com bandeira, quantidade de jogos futuros e o selo de regras
   (ex.: "+3% · Clientes", "2 regras"), com busca por nome e filtro por país.
2. **Given** o filtro "só com ajuste", **When** ele é marcado, **Then** a lista mostra só os
   campeonatos com alguma regra que o usuário pode ver.
3. **Given** um campeonato aberto, **When** o usuário escolhe o alvo, **Then** as opções vêm de lista
   e só aparecem as que ele pode usar (Gerente: só os vendedores da rede dele; Todos só para Admin e
   Supervisor), e a grade mostra os valores daquele alvo.
4. **Given** porcentagens salvas para um alvo, **When** o usuário volta à lista, **Then** o selo do
   campeonato é atualizado sem recarregar a página inteira.
5. **Given** um campeonato com regras de outra rede, **When** um Gerente o abre, **Then** ele não vê
   nem altera as regras da outra rede.

---

### User Story 3 - Cotação de um jogo e teto das cotações (Priority: P3)

O usuário encontra um jogo (por data, campeonato ou time), define a cotação desejada de cada opção
para um alvo (o sistema guarda a diferença em relação ao provedor) e vê a prévia final. Em outra aba,
o Admin ajusta o teto: o valor máximo que cada cotação pode atingir para qualquer público.

**Why this priority**: são ajustes pontuais (jogo com risco, cotação muito alta); o teto é de uso
raro e só do Admin.

**Independent Test**: abrir um jogo com "Casa" 2,00, informar 1,95 para Clientes, ver na prévia
"provedor 2,00 → jogo −0,05 → 1,95", salvar e conferir; na aba Tetos, definir 50,00 para "Casa" e
conferir que nenhuma cotação de "Casa" passa disso na prévia e na listagem.

**Acceptance Scenarios**:

1. **Given** a aba Jogos, **When** ela abre, **Then** lista os jogos de hoje, amanhã e depois de
   amanhã, paginados, com busca por time, filtro por campeonato e selo de quem tem ajuste.
2. **Given** um jogo aberto, **When** o usuário informa a cotação desejada para uma opção, **Then** a
   tela mostra a cotação do provedor ao lado e a diferença que será guardada, e a prévia mostra o
   resultado final para o alvo escolhido.
3. **Given** uma opção que o provedor não oferece neste jogo, **When** a grade aparece, **Then** o
   campo fica desabilitado com a indicação "indisponível no provedor".
4. **Given** um jogo do ao vivo, **When** o usuário tenta ajustá-lo, **Then** a tela informa que o
   valor do jogo vale só para o pré-jogo.
5. **Given** a aba Tetos e um Admin, **When** ele altera o teto de uma cotação, **Then** o valor
   mínimo aceito é 1,00 e a prévia passa a limitar o resultado ao novo teto.
6. **Given** um usuário sem a permissão de teto, **When** ele abre "Cotações", **Then** a aba Tetos
   não aparece.

---

### User Story 4 - Regras ativas (Priority: P4)

O usuário vê numa lista única tudo o que está ajustado e que ele pode ver: público, campeonato,
jogo e teto, com o alvo, quantas cotações têm ajuste e a última alteração, e vai direto para a
edição de cada regra.

**Why this priority**: dá a visão geral e evita "ajuste esquecido", mas a operação funciona sem ela.

**Independent Test**: com regras em clientes, em um vendedor, em um campeonato e em um jogo, abrir
Regras ativas e conferir as quatro linhas, os filtros por camada e o atalho para editar cada uma.

**Acceptance Scenarios**:

1. **Given** regras gravadas em várias camadas, **When** o usuário abre Regras ativas, **Then** vê uma
   linha por regra (camada, a quem se aplica, alvo, tipo, quantidade de cotações ajustadas e faixa
   de valores, ex.: "−10% a +3%"), paginada, com filtro por camada.
2. **Given** uma linha, **When** o usuário clica em editar, **Then** abre a aba e o item certos já
   carregados.
3. **Given** um Gerente, **When** ele abre Regras ativas, **Then** só vê as regras da rede dele.

---

### Edge Cases

- Público, campeonato ou jogo sem nenhuma regra: a grade mostra todos os campos em branco e o selo
  "sem ajuste"; a prévia mostra só a cotação do provedor.
- Valor apagado (campo em branco) numa cotação que tinha ajuste: conta como remover o ajuste daquela
  cotação ao salvar.
- Soma das camadas que levaria a cotação abaixo de 1,00: a prévia mostra 1,00 com o aviso
  "limitada ao mínimo"; acima do teto, mostra o teto com o aviso "limitada ao teto".
- Cotação zerada no provedor: a prévia mostra "indisponível" e nenhum ajuste a faz aparecer.
- Duas pessoas editando o mesmo público ao mesmo tempo: vale a última gravação; quem salvar depois
  recebe a tela atualizada com os valores gravados.
- Permissão retirada com a tela aberta: a próxima gravação é recusada com a mensagem do servidor, e
  a aba some ao recarregar.
- Usuário da rede inativo ou excluído: não aparece na lista de públicos, mas as regras dele continuam
  em Regras ativas, marcadas como "usuário inativo" ou "usuário excluído".
- Jogo que começou ou terminou com a tela aberta: a gravação do valor do jogo é recusada com a
  mensagem do servidor.
- Sessão expirada: leva ao login como no restante do painel, sem perder a confirmação de alterações
  pendentes.
- Cotação trancada por mais de um superior (ex.: Admin e Supervisor): vale a mais alta; o Supervisor
  só destranca a dele, e a do Admin continua.
- Superior que tranca uma cotação depois de o usuário abaixo já ter um ajuste nela: o ajuste
  continua valendo, só não pode mais ser alterado.
- Usuário que muda de superior: passa a obedecer os cadeados da nova rede.
- "Aplicar a todas" que muda dezenas de cotações: entra no histórico como uma alteração só, com todas
  as cotações alteradas dentro dela.
- Usuário autor de uma alteração que depois foi excluído: o histórico continua mostrando o nome dele.
- Celular: a grade vira uma lista de mercados que abrem e fecham, e a prévia fica numa faixa fixa
  embaixo que expande ao tocar.

## Requirements *(mandatory)*

### Functional Requirements

**Acesso e navegação**

- **FR-001**: O menu do painel DEVE ter o item "Cotações", visível para quem tiver ao menos uma
  permissão de cotação (porcentagens de vendedores, de clientes, de campeonatos, de jogos ou teto),
  levando à tela com uma aba por camada que o usuário pode usar: Público, Campeonatos, Jogos, Tetos e
  Regras ativas.
- **FR-002**: Cada aba DEVE aparecer só para quem tem a permissão da camada; o Vendedor NÃO DEVE ver o
  item nem acessar a tela (acesso direto volta para a Dashboard).
- **FR-003**: As regras de quem pode usar cada camada e cada alvo DEVEM continuar as já existentes
  (spec 003 e `Funcao.php`): Gerente só com alvo Vendedores da própria rede; alvo Todos só Admin e
  Supervisor; clientes do site e teto conforme a permissão de cada um. A tela NÃO DEVE oferecer
  opções que o servidor recusaria.

**Formulário de cotações (comum a todas as camadas)**

- **FR-004**: As cotações DEVEM aparecer agrupadas por mercado, na ordem e com os nomes já usados no
  site (abas e categorias do modal de mercados), mais o grupo "Jogadores" (código de jogador), com
  busca pelo nome da cotação ou do mercado.
- **FR-005**: Cada grupo DEVE abrir e fechar, mostrar quantas cotações têm ajuste ("3 de 10
  ajustadas") e ter "aplicar a todas" do grupo; a tela DEVE ter também "aplicar a todas" geral.
- **FR-006**: As alterações DEVEM ficar pendentes até o usuário clicar em Salvar: campos alterados
  destacados, contador de alterações no botão, botão Desfazer para voltar aos valores gravados, e
  confirmação ao sair da tela, trocar de aba ou de item com alterações pendentes.
- **FR-007**: Ao salvar, DEVEM ser enviadas só as cotações alteradas; campo apagado remove o ajuste
  da cotação; valores fora da faixa aceita (−100 a 100 nas porcentagens; teto mínimo 1,00) DEVEM ser
  apontados no próprio campo, sem gravar nada.
- **FR-008**: Os campos DEVEM aceitar vírgula ou ponto, mostrar o símbolo da unidade (% nas
  porcentagens; cotação no jogo e no teto) e exibir positivo com "+" e negativo com "−", com cor que
  diferencie aumento e redução, nos dois temas.

**Prévia do cálculo**

- **FR-009**: Ao lado do formulário (no celular, numa faixa fixa embaixo), DEVE haver uma prévia com um
  jogo de exemplo real (escolhido automaticamente entre os próximos, com opção de trocar) mostrando,
  para a cotação em foco: cotação do provedor, cada camada que soma (público, superiores, campeonato,
  jogo) com o valor de cada uma, o teto quando limitar e o resultado final, atualizada enquanto o
  usuário digita, já com os valores pendentes.
- **FR-010**: A prévia DEVE usar a mesma regra de cálculo do site (porcentagens somadas sobre a
  cotação do provedor, mais o valor do jogo, limitada ao teto, nunca menor que 1,00, em 2 casas) e
  DEVE indicar quando o resultado foi limitado pelo teto ou pelo mínimo. As camadas continuam
  somando, como hoje (Clarifications 2026-10-21); esta spec não muda o cálculo do site.

**Camada Público (P1)**

- **FR-011**: A aba Público DEVE permitir escolher, de lista: "Clientes do site" (regra geral), um
  cliente específico (busca por nome, login ou telefone) ou um usuário da rede (agrupados por
  função, com busca), conforme as permissões; e o tipo pré-jogo ou ao vivo.
- **FR-012**: Para um usuário da rede, a tela DEVE mostrar, só para leitura, a soma que ele já recebe
  dos superiores em cada cotação; para um cliente específico, a regra geral dos clientes que soma
  com a dele.

**Camada Campeonatos (P2)**

- **FR-013**: A aba Campeonatos DEVE listar os campeonatos com nome, país com bandeira, quantidade de
  jogos futuros e selo das regras visíveis ao usuário, com busca por nome, filtro por país (escolhido
  de lista, com bandeira) e filtro "só com ajuste", paginada (padrão 20, máximo 100).
- **FR-014**: Ao abrir um campeonato, o usuário DEVE escolher o alvo de lista (Todos, Clientes ou os
  vendedores de um usuário da rede, conforme FR-003) e editar a grade daquele alvo; a regra do
  campeonato vale para pré-jogo e ao vivo juntos (spec 003), e a tela DEVE dizer isso.

**Camada Jogos e Tetos (P3)**

- **FR-015**: A aba Jogos DEVE listar os jogos de hoje, amanhã e depois de amanhã com confronto,
  campeonato, data/hora e selo de ajuste, com busca por time, filtro por campeonato e por data,
  paginada (padrão 20, máximo 100).
- **FR-016**: Ao abrir um jogo, o usuário DEVE escolher o alvo e informar a cotação desejada por opção,
  vendo ao lado a cotação do provedor e a diferença que será guardada; opções indisponíveis no
  provedor ficam desabilitadas; o ajuste vale só para o pré-jogo, e a tela DEVE dizer isso.
- **FR-017**: A aba Tetos DEVE mostrar o teto de cada cotação, no mesmo formulário por mercado, e só
  aparecer para quem tem a permissão de teto.

**Regras ativas (P4)**

- **FR-018**: A aba Regras ativas DEVE listar todas as regras que o usuário pode ver (camada, a quem se
  aplica, alvo, tipo, quantidade de cotações ajustadas, faixa de valores e data da última
  alteração), com filtro por camada, paginada, e atalho para editar cada uma na aba certa.
- **FR-018a**: Toda gravação de porcentagem, valor do jogo ou teto, e todo trancar/destrancar, DEVE
  registrar no histórico: quem fez, quando, a camada, a quem se aplica (público, campeonato ou
  jogo), o alvo, o tipo (pré-jogo ou ao vivo) e cada cotação alterada com o valor antes e depois.
  Em Regras ativas, cada regra DEVE ter "ver histórico", que abre, num modal, a lista das alterações
  daquela regra, da mais nova para a mais antiga, paginada, mostrando antes → depois de cada
  cotação. Cada usuário só vê o histórico das regras que pode ver; o histórico não pode ser
  alterado nem apagado pela tela.

**Cadeado por cotação (do antigo)**

- **FR-019**: Admin e Supervisor com a permissão da camada DEVEM poder trancar e destrancar cada
  cotação de um usuário da rede que estão editando (o Gerente não tranca: abaixo dele só há
  vendedores, que não editam cotações), separadamente no pré-jogo e no ao vivo, com um cadeado ao
  lado do campo. O cadeado vale para o usuário editado e para todos abaixo dele, não para o resto
  da rede de quem trancou (Clarifications 2026-10-21, `/speckit-clarify`).
- **FR-019a**: Com a cotação trancada por um superior, o usuário abaixo DEVE ver o campo só para
  leitura, com o cadeado fechado e a indicação de quem trancou, e o servidor DEVE recusar a
  alteração daquela cotação (inclusive por "aplicar a todas", que pula as trancadas e informa
  quantas pulou).
- **FR-019b**: O cadeado DEVE valer para as porcentagens dos usuários da rede (aba Público) e para as
  porcentagens de campeonato com alvo Vendedores da rede (a regra do campeonato vale para pré-jogo e
  ao vivo juntos, então o cadeado dela também). Não se aplica a Clientes do site, a alvo Todos
  nem ao teto (só Admin e Supervisor editam) e, por decisão de escopo, não se aplica ao valor do
  jogo, mesmo que o Gerente o edite para a rede dele.
- **FR-019c**: Só quem trancou, ou um superior dele, DEVE poder destrancar. Trancar ou destrancar
  NÃO DEVE mudar o valor da cotação; a cotação trancada mantém o ajuste que tinha.

**Visual, responsividade e padrões do painel**

- **FR-020**: A tela DEVE seguir os padrões do painel (specs 014 e 015 e constituição): cores do tema
  (cor principal e fundo claro ou escuro), abas do painel, cabeçalho padrão nos modais (ícone,
  título e descrição), modais com o tamanho da constituição, campos com rótulo em cima e o
  espaçamento já usado, bordas mais fortes no tema claro, paginação e o menu de três pontinhos nas
  ações das listas no celular.
- **FR-021**: No celular (até 900 px), a tela DEVE: virar as abas numa lista rolável, mostrar nas listas
  só as colunas principais (nome, selo e ações) com textos longos cortados, mostrar os mercados como
  grupos que abrem e fecham e a prévia como faixa fixa embaixo; nada pode exigir rolagem horizontal.
- **FR-022**: Cada área DEVE ter estados de carregando (esqueleto), vazio (com orientação do que
  fazer) e erro com "tentar novamente", sem travar as outras áreas da tela.
- **FR-023**: Toda gravação DEVE dar retorno imediato (botão com carregando, confirmação de sucesso
  ou mensagem do servidor no erro), e ações de alcance grande ("aplicar a todas" geral, alvo Todos)
  DEVEM pedir confirmação dizendo quantas cotações ou campeonatos serão afetados.

### Key Entities

- **Camada de regra**: nível em que um ajuste é feito: Público (clientes do site, cliente específico
  ou usuário da rede, pré-jogo e ao vivo), Campeonato, Jogo e Teto.
- **Regra de porcentagem**: conjunto de ajustes por cotação (−100 a 100) de uma camada, para um alvo.
- **Valor do jogo**: diferença entre a cotação desejada e a do provedor, por opção, para um alvo, só no
  pré-jogo.
- **Teto**: valor máximo de cada cotação para qualquer público.
- **Alvo**: a quem a regra de campeonato ou de jogo se aplica: Todos, Clientes ou os vendedores de um
  usuário da rede.
- **Mercado**: grupo de cotações exibido junto (Vencedor, Dupla chance, Gols mais/menos etc.).
- **Cadeado de cotação**: trava de uma cotação (pré-jogo ou ao vivo) posta por Admin ou Supervisor
  na tela de um usuário da rede, que impede esse usuário e os que estão abaixo dele de alterar a
  porcentagem daquela cotação; guarda quem trancou e para quem vale.
- **Histórico de cotação**: registro de cada alteração de uma regra (quem, quando, camada, a quem se
  aplica, alvo, tipo e cotações com valor antes e depois), e de cada trancar/destrancar.
- **Prévia**: cálculo de exemplo de uma cotação de um jogo real com todas as camadas que valem para o
  público escolhido.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Um Admin ajusta a porcentagem de uma cotação para os clientes do site em menos de 1
  minuto a partir do menu, sem ajuda.
- **SC-002**: Em 100% das conferências manuais, o valor mostrado na prévia é igual ao que o público
  escolhido vê na listagem depois de salvar.
- **SC-003**: Em teste com usuários do painel, 9 de 10 respondem corretamente "por que esta cotação
  está com este valor" olhando só a prévia.
- **SC-004**: Um Gerente nunca vê nem altera regras fora da rede dele em nenhum dos testes.
- **SC-005**: Toda a tela funciona no celular (360 px de largura) sem rolagem horizontal e com todos os
  alvos de toque com pelo menos 40 px.
- **SC-006**: Abrir um público, campeonato ou jogo mostra a grade em até 2 segundos numa conexão
  comum, e salvar dá retorno em até 1 segundo.
- **SC-007**: Nenhuma alteração é perdida sem aviso: em 100% dos testes de sair da tela com
  alterações pendentes, a confirmação aparece.
- **SC-008**: Em 100% das conferências, toda alteração salva aparece no histórico da regra com o
  autor, a data e os valores antes e depois corretos.

## Assumptions

- O cálculo, as tabelas, a faixa de valores e as rotas de consulta e alteração de cada camada são
  os da spec 003 e não mudam nesta spec; as únicas regras novas no servidor são o cadeado por
  cotação e o histórico de alterações.
- As permissões de cada camada já existem (spec 003) e já são distribuídas por função; esta spec não
  cria permissão nova: trancar e destrancar usam a permissão da camada (quem pode editar a camada
  pode trancar para baixo).
- Os nomes e a ordem dos mercados são os do modal de mercados do site; cotações sem mercado
  conhecido ficam no grupo "Outras".
- A lista de clientes para o cliente específico é paginada e com busca (há muitos clientes).
- "Hoje, amanhã e depois de amanhã" na aba Jogos seguem o horário de Brasília, como a listagem
  pública.
- A tela de campeonatos e de jogos desta spec serve só para cotações; cadastrar, ativar, favoritar e
  não permitidos de campeonatos e jogos ficam para outra spec.
- Não há dados fake nesta spec: tudo o que a tela mostra já existe no servidor.
- Sem testes automatizados (constituição); a validação é manual pelo quickstart da spec.
