# Feature Specification: Painel — Confrontos (lista de jogos, limite, situação, mais apostados, pendentes, campeonatos e cadastro manual)

**Feature Branch**: `023-painel-confrontos`

**Created**: 2026-10-27

**Status**: Draft

**Input**: User description: "Spec 023: tela 'Confrontos' no painel (backend e frontend), no padrão
das specs 014 a 022. É o antigo 'Lista de Jogos'. A gestão de confrontos existe só como API desde a
spec 003 (listagem, ativar e desativar, não permitidos, limite de valor apostado por confronto
pré-jogo e ao vivo) e a spec 009 (resultado de jogo manual); as cotações de um jogo já têm tela na
spec 016 (aba Jogos). Falta a tela. Trazer do antigo (wssports.bet, main 408cf730; backend:
ConfrontosController index com as abas todos, favoritos e pendentes, habilitar,
alterarLimiteApostas, update (placar), e BilhetesController destaquesModalidade; telas:
screens/adm/matches 'Lista de Jogos - total N' /adm/lista-de-jogos, o modal
screens/adm/modals/scoreboard e screens/adm/featured-bet /adm/destaques-modalidade/:id) tudo o que
a tela tem [...]. Melhorias sobre o antigo: StatCards no topo; gaveta do confronto (dados, cotações
principais, ranking das opções mais apostadas, apostas do jogo e resultado); ações no menu de três
pontinhos; o limite e o placar em modais próprios; o atalho de cotações leva à aba Jogos da tela
Cotações (spec 016) já com o jogo escolhido; alcance por rede como na spec 019; permissões as que já
existem (spec 003 e 009), sem permissão nova se possível. Item 'Confrontos' no menu, rota
/painel/confrontos. Visual do painel novo; modais com campos e botões organizados em grade,
agrupados por seção; liga/desliga como campo select, não toggle. Sem exportação e sem dados fake."

## Clarifications

### Session 2026-10-27 (decisões tomadas antes do `/speckit-specify`)

- Q: Qual versão do sistema antigo é a referência? → A: A `main` atual do `wssports.bet`
  (`408cf730`), tela `screens/adm/matches` ("Lista de Jogos - total N"), o modal
  `screens/adm/modals/scoreboard` (placar) e a tela `screens/adm/featured-bet` (destaques da
  modalidade).
- Q: Como se chama a tela? → A: "Confrontos", item do menu do painel, rota `/painel/confrontos`.
- Q: A edição das cotações do jogo (antiga `alterar-odd-confronto`) é refeita aqui? → A: Não. A
  ação "Alterar cotações" leva à aba Jogos da tela Cotações (spec 016) com o jogo já aberto; o
  cadeado por cotação (antigo `bloquear-cotacao-confronto`) também já está lá.
- Q: O limite e o placar continuam editáveis na linha? → A: Não. Cada um ganha um modal próprio,
  aberto pelo menu de três pontinhos (diferença visual aprovada).
- Q: Há permissão nova? → A: Não, se possível; usam-se as das specs 003 e 009
  (`confrontos.listar`, `confrontos.alterar_situacao`, `confrontos_nao_permitidos.gerenciar`,
  `confrontos_ao_vivo_nao_permitidos.gerenciar`, `confrontos.alterar_limite`,
  `confrontos.lancar_resultado` e `porcentagens_confrontos.editar`).
- Q: A lista pode ser exportada? → A: Não, fica de fora desta spec.
- Q: O visual pode mudar em relação ao antigo (Princípio VIII)? → A: Sim, no padrão do painel novo
  (specs 014 a 022), como nas specs anteriores. Nos modais, campos e botões em grade e agrupados por
  seção; liga/desliga como campo de seleção, não interruptor.

### Session 2026-10-27 (respostas do responsável às perguntas do `/speckit-specify`)

- Q: A aba "favoritos" do antigo continua? → A: Sim, como aba própria **Mais apostados**: os jogos
  com mais apostas, da rede de quem consulta; o ranking das opções de cada jogo fica na gaveta
  (FR-001 e FR-019 a FR-021).
- Q: O Gerente altera o limite e lança o placar, como no antigo? → A: Não. Fica como na API do v2:
  o Gerente vê a lista, desativa jogos e campeonatos só para a rede dele e vê o limite sem editar;
  não vê a aba Pendentes. Limite e resultado são só de Admin e Supervisor, porque valem para todas
  as redes (FR-003). Diferença em relação ao antigo, aprovada.
- Q: A gestão de campeonatos e o cadastro manual entram nesta tela? → A: Sim. Entra a aba
  **Campeonatos** (o antigo `screens/adm/championships`: ativar/desativar, favoritar, atalho de
  cotações) e o cadastro, a edição e a exclusão manuais de campeonatos e jogos, que existem só como
  API desde a spec 003 (FR-025 a FR-032).

### Session 2026-10-27 (`/speckit-clarify`)

- Q: O que acontece ao excluir um jogo ou campeonato manual que já tem apostas em aberto? → A: A
  exclusão é recusada enquanto houver apostas não canceladas, com "Este jogo tem N apostas. Cancele
  o jogo em vez de excluir."; o campeonato só é excluído se nenhum jogo dele tiver apostas (FR-032).
  Muda a spec 003 (FR-063), que excluía sem conferir as apostas.
- Q: As abas Jogos e Mais apostados mostram dias que já passaram? → A: Sim. O dia tem os atalhos
  Ontem, Hoje (padrão), Amanhã, Depois de amanhã e "Escolher data", com datas de até 30 dias atrás e
  até depois de amanhã (FR-005 e FR-007). Muda o filtro de dia da listagem do painel (spec 003), que
  aceitava só hoje, amanhã e depois de amanhã; a listagem pública do site não muda.
- Q: A lista se atualiza sozinha? → A: Sim, a cada 30 segundos, só com o filtro "Ao vivo" ou na aba
  Pendentes (e o cartão "Ao vivo agora"), parando com a aba do navegador escondida; nas demais, só
  pelo botão "Atualizar" e ao trocar filtro (FR-039).
- Q: Como contam as apostas múltiplas no valor apostado e no possível retorno de Mais apostados? →
  A: Cada aposta conta inteira em cada jogo dela (valor e possível retorno totais), com a quantidade
  e o valor separados em Simples e Múltiplas (FR-016 e FR-019).

### Session 2026-10-27 (`/speckit-plan`, resposta do responsável)

- Q: Na aba Pendentes, o placar pode ser lançado em jogos do provedor (a spec 009 só permitia jogos
  manuais)? → A: Sim, como no sistema antigo, e só pelo Admin. O lançamento vale para qualquer jogo
  (do provedor ou manual), exige placar final, 1º e 2º tempo e escanteios e confere que a soma dos
  gols dos tempos bate com o placar ("A soma dos gols dos times não bate com os gols dos tempos.");
  se depois a carga do provedor trouxer um resultado diferente, ele substitui o lançado e as apostas
  são reapuradas, como no antigo. `confrontos.lancar_resultado` passa a ser só do Admin (FR-003,
  FR-022, FR-023 e FR-033). Muda a spec 009 (FR-045 e FR-046).

## Referências do sistema antigo *(Princípios VII e VIII)*

- Backend (`wssports.bet`, `main` `408cf730`): `app/Http/Controllers/ConfrontosController.php`
  (`index` com as abas `todos`, `favoritos` e `pendentes`, período `de`/`ate`, `pesquisar` e
  `periodo` TODOS / PRÉ JOGO / AO VIVO; `habilitar`; `alterarLimiteApostas`; `update`, o placar) e
  `app/Http/Controllers/BilhetesController.php` (`destaquesModalidade`). Rotas
  `habilitar-confronto/{id}`, `alterar-limite-apostas-confrontos/{id}` e
  `destaques-modalidade/{id}`.
- Telas: `resources/js/screens/adm/matches` ("Lista de Jogos - total N", rota
  `/adm/lista-de-jogos`), `resources/js/screens/adm/modals/scoreboard` (placar) e
  `resources/js/screens/adm/featured-bet` (rota `/adm/destaques-modalidade/:id`).
- Campeonatos: tela `resources/js/screens/adm/championships` ("Campeonatos - total N", com a
  pesquisa, a quantidade de jogos disponíveis e os ícones de favoritar e ativar/desativar) e as rotas
  `campeonatos`, `favoritos/{id}` e `habilitar-campeonato/{id}`. O cadastro manual de campeonatos e
  jogos não existia no antigo; nasceu na spec 003.

**Como funcionava**:

- **Aba "todos"**: casa x fora, horário (dd/mm hh:mm no fuso de quem vê) e ID do jogo, selo "AO
  VIVO" quando o jogo estava no ao vivo; seleção TODOS / PRÉ JOGO / AO VIVO; período de/até (até 2
  dias à frente) e pesquisa por time ou campeonato; 100 por página, ordenado por casa e horário.
- **Na linha**: campo do limite de apostas do jogo com o botão de gravar; atalho para alterar as
  cotações; cadeado para ativar/desativar. Para Admin e Supervisor, o cadeado desativava o jogo
  para todos (pré-jogo e ao vivo); para o Gerente, só para a rede dele (tabela
  `confrontos_gerencias`). O Supervisor dependia das travas `editar_cotacao` e
  `bloquear_confronto`, e o Gerente da trava `alterar_cotacao`.
- **Aba "favoritos"**: jogos com mais palpites no período (do Gerente, só da rede dele), com a
  quantidade e o atalho para "destaques da modalidade": ranking das opções (modalidades) mais
  apostadas no jogo, contando só bilhetes ativos de usuários ativos.
- **Aba "pendentes"**: jogos ativos, ainda "Aguardando", com bilhetes ativos aguardando, que
  começaram há mais de 2 horas e há menos de 7 dias; mostrava a quantidade de bilhetes e os minutos
  de atraso e abria o modal do placar (placar final, escanteios, 1º e 2º tempo).
- **Problemas encontrados**: o limite era global, mas o Gerente podia alterá-lo para todos; o valor
  aceitava qualquer texto; a gravação do limite era separada da digitação (fácil esquecer de
  gravar); a pesquisa com `orWhere` escapava do período; as abas mudavam de colunas sem aviso; nada
  de cartões de resumo; a tela não funcionava bem no celular.
- **Tela "Campeonatos"**: lista com nome e a quantidade de jogos disponíveis, pesquisa, favoritar
  (coração) e ativar/desativar (cadeado); o ícone de alterar as cotações do campeonato não fazia
  nada.

**Diferenças aprovadas** (padrão das specs 014 a 022):

- Cartões de resumo no topo; gaveta do confronto com dados, cotações principais, mais apostados,
  apostas do jogo e resultado.
- Ações no menu de três pontinhos; limite e placar em modais próprios.
- "Alterar cotações" leva à tela Cotações (spec 016), aba Jogos, com o jogo aberto.
- Travas do Supervisor e do Gerente substituídas pelas permissões por função (spec 001/003); o
  Gerente não altera mais o limite nem lança o placar (valem para todas as redes).
- "Favoritos" vira a aba "Mais apostados"; a tela Campeonatos do antigo vira a aba Campeonatos, com
  o atalho de cotações funcionando (spec 016, aba Campeonatos); entra o cadastro manual da spec 003.
- Alcance por rede da spec 019 nas contagens de apostas (mais apostados, pendentes e apostas do
  jogo).
- Visual do painel novo: tema, cabeçalho, abas, `StatCard`, filtros com etiquetas, lista que vira
  cartões no celular, gaveta, `ModalHeader`, campos em grade e campos de seleção.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Consultar os confrontos (Priority: P1)

O usuário do painel abre "Confrontos", vê os cartões de resumo e a lista dos jogos do dia (casa x
fora, campeonato, horário, ID, situação, selo Ao vivo e limite), alterna entre Todos, Pré-jogo e Ao
vivo, escolhe o dia (de até 30 dias atrás até depois de amanhã) e busca por time ou campeonato.

**Why this priority**: é a consulta do dia a dia e a base de todas as outras ações; sozinha já
substitui a aba "todos" do antigo para consulta.

**Independent Test**: com jogos hoje e amanhã, um deles no ao vivo, abrir Confrontos como Admin e
conferir cartões e colunas; escolher "Ao vivo" e ver só o jogo ao vivo; escolher "Amanhã" e ver os
jogos de amanhã; buscar pelo nome de um time e conferir que só aparecem jogos daquele dia.

**Acceptance Scenarios**:

1. **Given** um usuário com `confrontos.listar`, **When** ele abre Confrontos, **Then** vê os cartões
   e a aba Jogos com o total no cabeçalho e a lista (casa x fora, campeonato e país, data e hora no
   fuso de quem vê, ID do jogo, selo "Ao vivo" com o minuto e o placar quando estiver no ao vivo,
   situação, limite de valor apostado e indicação "Desativado para a minha rede" quando for o caso),
   paginada, no dia de hoje.
2. **Given** o filtro de tipo, **When** o usuário escolhe Pré-jogo, Ao vivo ou Todos, **Then** a
   lista e os cartões são recarregados com os jogos daquele tipo.
3. **Given** o painel de filtros, **When** o usuário escolhe o dia (Ontem, Hoje, Amanhã, Depois de
   amanhã ou uma data de até 30 dias atrás),
   o campeonato, a situação (Ativos, Desativados ou Todos) ou "Só manuais", **Then** a lista é
   recarregada com os filtros combinados e eles aparecem como etiquetas removíveis.
4. **Given** a busca, **When** o usuário digita parte do nome de um time ou do campeonato, **Then** a
   lista mostra só os jogos que casam, sem escapar do dia e dos demais filtros.
5. **Given** um Gerente, **When** ele abre Confrontos, **Then** vê os mesmos jogos, com a marca dos
   que ele desativou para a rede dele, sem as ações que a função dele não tem.

---

### User Story 2 - Ativar e desativar o jogo (Priority: P1)

O usuário desativa um jogo que não quer oferecer (ou reativa). Admin e Supervisor desativam para
todos; o Gerente desativa só para a rede dele.

**Why this priority**: é a ação mais usada do antigo (o cadeado) e protege a banca de jogos
suspeitos.

**Independent Test**: como Admin, desativar um jogo e conferir que ele some do site e do ao vivo;
reativar e conferir que volta. Como Gerente, desativar um jogo e conferir que ele some para os
vendedores e clientes da rede dele, mas continua para os de outro gerente.

**Acceptance Scenarios**:

1. **Given** Admin ou Supervisor com `confrontos.alterar_situacao`, **When** escolhe "Desativar" e
   confirma, **Then** o jogo fica desativado para todos, no pré-jogo e no ao vivo, e a linha mostra
   "Desativado"; **When** escolhe "Ativar", **Then** volta a aparecer.
2. **Given** um Gerente com `confrontos_nao_permitidos.gerenciar`, **When** escolhe "Desativar para
   a minha rede" e confirma, **Then** o jogo entra nos não permitidos da rede dele (pré-jogo e, com
   `confrontos_ao_vivo_nao_permitidos.gerenciar`, ao vivo), e a linha mostra "Desativado para a
   minha rede"; **When** escolhe "Ativar para a minha rede", **Then** o registro é retirado.
3. **Given** Admin ou Supervisor, **When** abre o menu de um jogo que algum gerente desativou para a
   rede, **Then** vê a indicação de quantas redes o desativaram, sem precisar abrir outra tela.
4. **Given** a confirmação, **When** o jogo tem apostas em aberto, **Then** ela avisa que as apostas
   já feitas continuam valendo e que o jogo só deixa de receber apostas novas.

---

### User Story 3 - Alterar o limite de valor apostado (Priority: P1)

O usuário com `confrontos.alterar_limite` abre o modal de limite de um jogo, vê o limite atual do
pré-jogo e do ao vivo e grava novos valores.

**Why this priority**: é a proteção da banca contra exposição alta num jogo; no antigo ficava na
própria linha.

**Independent Test**: alterar o limite do pré-jogo de um jogo para R$ 1.000,00; tentar apostar mais
que isso no jogo e conferir a recusa; tentar gravar zero ou texto e conferir a mensagem do servidor.

**Acceptance Scenarios**:

1. **Given** um jogo, **When** o usuário escolhe "Alterar limite", **Then** abre o modal com o limite
   do pré-jogo e, quando o jogo estiver no ao vivo, o do ao vivo, em campos de valor em reais, e os
   botões Cancelar e Salvar no rodapé.
2. **Given** um valor válido, **When** o usuário salva, **Then** o limite é gravado, o modal fecha e
   a linha e a gaveta mostram o valor novo.
3. **Given** zero, negativo ou mais de duas casas, **When** o usuário salva, **Then** a mensagem do
   servidor aparece no campo e nada muda.
4. **Given** um usuário sem `confrontos.alterar_limite`, **When** abre o menu, **Then** "Alterar
   limite" não aparece e a linha mostra o limite só para leitura.

---

### User Story 4 - Ver o confronto e os mais apostados (Priority: P1)

O usuário toca num jogo e abre a gaveta com os dados, as cotações principais, o ranking das opções
mais apostadas, as apostas do jogo e o resultado.

**Why this priority**: substitui o "destaques da modalidade" do antigo e responde "qual é a
exposição neste jogo?" sem sair da tela.

**Independent Test**: com três apostas em "Casa" e uma em "Empate" num jogo, abrir a gaveta e
conferir o ranking (Casa 3, Empate 1) e a lista das quatro apostas; entrar como um Gerente e
conferir que só as apostas da rede dele contam.

**Acceptance Scenarios**:

1. **Given** um jogo na lista, **When** o usuário toca na linha ou escolhe "Ver detalhe", **Then**
   abre a gaveta com o topo (casa x fora, campeonato, horário, situação, selo Ao vivo) e as abas
   **Dados**, **Mais apostados**, **Apostas** e **Resultado**.
2. **Given** a aba Dados, **When** ela abre, **Then** mostra ID, campeonato e país, esporte, data e
   hora, origem (provedor ou manual), situação geral, desativações por rede, limites e as cotações
   principais (Casa, Empate e Fora) como o site mostra.
3. **Given** a aba Mais apostados, **When** ela abre, **Then** mostra o ranking das opções apostadas
   no jogo (nome da opção como no cupom, quantidade de palpites, valor apostado e possível retorno),
   da mais para a menos apostada, contando só apostas não canceladas da rede de quem consulta.
4. **Given** a aba Apostas, **When** ela abre, **Then** mostra as apostas com palpite no jogo (código,
   data, vendedor ou cliente, opção, valor e situação), da rede de quem consulta, com o atalho para a
   aposta na tela Apostas (spec 018) para quem tem `apostas.listar`.
5. **Given** a aba Resultado, **When** o jogo já tem resultado, **Then** mostra o placar final, os
   tempos, os escanteios e os gols por jogador, com a origem (provedor ou manual).

---

### User Story 5 - Jogos pendentes de resultado e placar manual (Priority: P1)

O Admin abre a aba "Pendentes", vê os jogos (do provedor ou manuais) que já deviam ter resultado e
não têm, com o atraso e as apostas em aberto, e lança o placar, como no antigo.

**Why this priority**: sem resultado, as apostas não são apuradas nem pagas (spec 009); é a rotina
de conferência do antigo.

**Independent Test**: com um jogo que começou há 3 horas, sem resultado e com duas apostas
aguardando, abrir Pendentes e conferir o atraso e a quantidade; lançar o placar 2 x 1 e conferir que
o jogo sai da aba e as apostas são apuradas.

**Acceptance Scenarios**:

1. **Given** um usuário com `confrontos.lancar_resultado`, **When** abre a aba Pendentes, **Then** vê
   os jogos ativos, sem resultado, com apostas aguardando, que começaram há mais de 2 horas e há
   menos de 7 dias (casa x fora, campeonato, início, atraso em horas e minutos, quantidade de apostas
   e valor em aberto), do maior para o menor atraso.
2. **Given** um jogo pendente, do provedor ou manual, **When** o Admin escolhe "Lançar resultado",
   **Then** abre o modal em grade com as seções Placar final (casa e fora), Tempos (1º e 2º tempo,
   casa e fora) e Escanteios (casa e fora), todos obrigatórios, e Gols (jogador, minuto e tempo,
   opcionais), com Cancelar e Salvar no rodapé.
3. **Given** o modal preenchido, **When** o usuário salva, **Then** o resultado é gravado com origem
   "Lançamento manual", as apostas são apuradas pelas regras da spec 009, o jogo sai de Pendentes e a
   gaveta mostra o resultado; **When** a soma dos gols dos tempos não bate com o placar, **Then**
   aparece "A soma dos gols dos times não bate com os gols dos tempos." e nada é gravado; com outro
   erro, a mensagem do servidor aparece no campo.
4. **Given** um jogo que já tem resultado, **When** o usuário escolhe "Corrigir resultado" (na aba
   Jogos ou na gaveta), **Then** o mesmo modal abre preenchido, e a correção segue a reapuração da
   spec 009.
5. **Given** um usuário sem `confrontos.lancar_resultado`, **When** abre a tela, **Then** a aba
   Pendentes, o cartão Pendentes e as ações de resultado não aparecem, e o acesso direto é recusado.

---

### User Story 6 - Alterar as cotações do jogo (Priority: P2)

O usuário com `porcentagens_confrontos.editar` escolhe "Alterar cotações" num jogo e vai para a tela
Cotações, aba Jogos, com o jogo aberto no editor.

**Why this priority**: a edição já existe na spec 016; aqui é só o atalho.

**Independent Test**: escolher "Alterar cotações" num jogo e conferir que a tela Cotações abre na aba
Jogos com o editor desse jogo; voltar e conferir que a lista de Confrontos mantém os filtros.

**Acceptance Scenarios**:

1. **Given** um jogo, **When** o usuário escolhe "Alterar cotações", **Then** a tela Cotações abre na
   aba Jogos com o editor do jogo aberto.
2. **Given** a volta pelo navegador, **When** o usuário retorna, **Then** Confrontos mostra a mesma
   aba, filtros e página.
3. **Given** um usuário sem `porcentagens_confrontos.editar`, **When** abre o menu, **Then** a ação
   não aparece.

---

### User Story 7 - Jogos mais apostados (Priority: P2)

O usuário abre a aba "Mais apostados" (o antigo "favoritos") e vê os jogos com mais apostas da rede
dele, para acompanhar a exposição da banca.

**Why this priority**: é uma consulta de acompanhamento; a gaveta (US4) já mostra o ranking de cada
jogo.

**Independent Test**: com três jogos de hoje com 5, 2 e 0 apostas, abrir Mais apostados e conferir a
ordem (5, 2) e que o jogo sem aposta não aparece; entrar como Gerente e conferir que só contam as
apostas da rede dele.

**Acceptance Scenarios**:

1. **Given** um usuário com `confrontos.listar`, **When** abre a aba Mais apostados, **Then** vê os
   jogos do dia e do tipo escolhidos com pelo menos uma aposta da rede dele (casa x fora, campeonato,
   horário, quantidade de apostas, valor apostado e possível retorno), da maior para a menor
   quantidade, paginada.
2. **Given** um jogo da aba, **When** o usuário toca na linha, **Then** abre a gaveta já na aba Mais
   apostados, com o ranking das opções.
3. **Given** os filtros, **When** o usuário troca o dia, o tipo, o campeonato ou busca, **Then** a
   aba é recarregada com os mesmos filtros da aba Jogos.

---

### User Story 8 - Gerenciar os campeonatos (Priority: P2)

O usuário abre a aba "Campeonatos" (a antiga tela "Campeonatos"), vê os campeonatos com a quantidade
de jogos, busca e filtra, ativa ou desativa (para todos ou para a rede dele), favorita e vai para as
cotações ou para os jogos do campeonato.

**Why this priority**: bloquear um campeonato inteiro é mais rápido que bloquear jogo a jogo, mas a
lista de jogos funciona sem esta aba.

**Independent Test**: como Admin, desativar um campeonato e conferir que todos os jogos dele somem do
site; favoritá-lo e conferir que ele sobe no site; como Gerente, desativar outro campeonato só para a
rede dele e conferir o efeito só nos vendedores dele; escolher "Ver jogos" e conferir a aba Jogos
filtrada pelo campeonato.

**Acceptance Scenarios**:

1. **Given** um usuário com `campeonatos.listar`, **When** abre a aba Campeonatos, **Then** vê a
   lista (bandeira, nome, país, origem provedor ou manual, quantidade de jogos disponíveis, situação,
   favorito e "Desativado para a minha rede" quando for o caso), com busca por nome e filtros de
   país, situação, favoritos e "Só manuais", paginada.
2. **Given** Admin ou Supervisor com `campeonatos.alterar_situacao`, **When** escolhe Desativar e
   confirma, **Then** o campeonato e os jogos dele deixam de aparecer para todos; Ativar faz voltar.
3. **Given** um Gerente com `campeonatos_nao_permitidos.gerenciar`, **When** escolhe "Desativar para
   a minha rede" e confirma, **Then** o campeonato some só para a rede dele; "Ativar para a minha
   rede" desfaz.
4. **Given** `campeonatos.favoritar`, **When** o usuário escolhe Favoritar ou Desfavoritar, **Then**
   a marca muda na hora e o site passa a mostrar o campeonato entre os favoritos.
5. **Given** `porcentagens_campeonatos.editar`, **When** o usuário escolhe "Alterar cotações",
   **Then** a tela Cotações (spec 016) abre na aba Campeonatos com o editor do campeonato aberto.
6. **Given** um campeonato, **When** o usuário escolhe "Ver jogos", **Then** a aba Jogos abre filtrada
   por ele.

---

### User Story 9 - Cadastrar campeonatos e jogos manuais (Priority: P2)

O Admin ou Supervisor cadastra à mão um campeonato e os jogos dele (times, esporte, data e
cotações), que aparecem no site como os do provedor, e depois edita, muda a situação ou exclui.

**Why this priority**: cobre jogos que o provedor não traz (eventos locais); é menos frequente que a
gestão dos jogos do provedor.

**Independent Test**: cadastrar o campeonato "Copa do Bairro"; cadastrar nele um jogo amanhã às 16h
com Casa 1,80, Empate 3,20 e Fora 4,00; conferir no site; editar a cotação de Casa e conferir; tentar
editar um jogo do provedor e conferir que a ação não existe; excluir o jogo e o campeonato.

**Acceptance Scenarios**:

1. **Given** `campeonatos.cadastrar`, **When** o usuário escolhe "Cadastrar campeonato" na aba
   Campeonatos, **Then** abre o modal com nome, país (seleção) e bandeira (opcional); ao salvar, o
   campeonato aparece como manual e ativo.
2. **Given** `confrontos.cadastrar`, **When** o usuário escolhe "Cadastrar jogo" na aba Jogos (ou
   "Adicionar jogo" no menu de um campeonato manual), **Then** abre o modal em grade com as seções
   Jogo (campeonato manual, escolhido de uma lista; esporte; data e hora no fuso de quem vê), Times
   (casa e fora, com escudo opcional) e Cotações (Casa, Empate e Fora e o botão "Adicionar cotação"
   para escolher outros códigos agrupados por mercado), com Cancelar e Salvar no rodapé.
3. **Given** o modal preenchido, **When** o usuário salva, **Then** o jogo é gravado como manual e
   aparece na lista e no site; **When** a data está no passado, uma cotação é menor que 1,00 ou falta
   cotação, **Then** a mensagem do servidor aparece no campo.
4. **Given** um jogo ou campeonato manual, **When** o usuário com `confrontos.editar` ou
   `campeonatos.editar` escolhe Editar, **Then** abre o mesmo modal preenchido; no jogo, também a
   situação (seleção com as situações que a spec 003 permite mudar à mão).
5. **Given** um jogo ou campeonato do provedor, **When** o usuário abre o menu, **Then** Editar e
   Excluir não aparecem, e o acesso direto é recusado com a mensagem do servidor.
6. **Given** `campeonatos.excluir` ou `confrontos.excluir`, **When** o usuário escolhe Excluir num
   registro manual, **Then** a confirmação mostra o que será apagado (no campeonato, a quantidade de
   jogos dele) e, ao confirmar, o registro sai das listas; se o servidor recusar, a mensagem aparece.

---

### Edge Cases

- Jogo no ao vivo que não existe no pré-jogo: não aparece (regra da spec 003).
- Atualização automática que falha (rede caiu): a lista mantém os dados anteriores com o aviso
  "Não foi possível atualizar" e tenta de novo no próximo ciclo.
- Jogo que sai do filtro "Ao vivo" (encerrou) durante a atualização com a gaveta dele aberta: some da
  lista, e a gaveta continua aberta até o usuário fechar.
- Jogo que entrou no ao vivo e saiu (encerrado): perde o selo e o limite do ao vivo some do modal.
- Jogo desativado para todos e também por um gerente: aparece "Desativado"; reativar para todos não
  apaga a desativação da rede do gerente.
- Jogo manual (spec 003): aparece com a marca "Manual"; o resultado só vem pelo placar manual.
- Jogo cancelado ou adiado (spec 009): aparece com a situação; não entra em Pendentes.
- Jogo pendente cujo resultado chega do provedor com a tela aberta: a próxima atualização o tira de
  Pendentes; salvar o placar depois disso segue a correção da spec 009.
- Placar lançado à mão num jogo do provedor e, depois, resultado diferente na carga: vale o do
  provedor, e as apostas são reapuradas (estornos e pagamentos da spec 009); o histórico de apuração
  mostra as duas origens.
- Dois usuários lançando o resultado do mesmo jogo ao mesmo tempo: vale o último, com a reapuração
  da spec 009.
- Limite alterado com o modal aberto por outro usuário: vale o último gravado.
- Mais apostados sem nenhum palpite: estado vazio "Nenhuma aposta neste jogo.".
- Múltipla com cinco jogos: aparece nos cinco, com o valor e o retorno inteiros em cada um; por isso
  a soma de Mais apostados de vários jogos não é o total apostado na banca, e a tela avisa isso na
  legenda das colunas de Múltiplas.
- Busca com caracteres especiais (`%`, `_`): tratados como texto.
- Campeonato desativado com jogos ativos: os jogos continuam "Ativo" na lista, com a indicação
  "Campeonato desativado", e não aparecem no site.
- Campeonato manual sem jogos: aparece com "0 jogos" e a ação "Adicionar jogo".
- Jogo manual cuja data passou sem resultado: entra em Pendentes como os demais.
- Data escolhida antes de 30 dias atrás ou depois de depois de amanhã: recusada no próprio campo.
- Jogo de dia passado com resultado: aparece na aba Jogos com a situação e o placar, e "Corrigir
  resultado" fica disponível para quem tem `confrontos.lancar_resultado`.
- Exclusão de jogo ou campeonato manual com apostas não canceladas: recusada com a mensagem do
  servidor na confirmação; o caminho é mudar a situação do jogo para Cancelado (FR-031), que devolve
  os palpites pela spec 009. Com todas as apostas canceladas, a exclusão é permitida.
- Nome de campeonato manual repetido: recusado no campo com a mensagem do servidor.
- Permissão retirada com a tela aberta: a próxima ação é recusada com a mensagem do servidor e a ação
  some ao recarregar.
- Sessão expirada: leva ao login como no restante do painel.
- Celular: as linhas viram cartões (casa x fora, horário e situação) com o restante ao expandir;
  gaveta e modais ocupam a tela toda.

## Requirements *(mandatory)*

### Functional Requirements

**Acesso e abas**

- **FR-001**: O menu do painel DEVE ter o item "Confrontos", visível para quem tem
  `confrontos.listar`, levando à tela `/painel/confrontos` com as abas **Jogos**, **Mais
  apostados**, **Pendentes** (só para quem tem `confrontos.lancar_resultado`) e **Campeonatos** (só
  para quem tem `campeonatos.listar`).
- **FR-002**: Os jogos são os mesmos para todos os níveis (catálogo da spec 003); o alcance por rede
  da spec 019 DEVE valer para tudo o que conta apostas (Mais apostados, Apostas, Pendentes e os
  cartões de apostas): Admin, todas; Supervisor, as da rede dele; Gerente, as da rede dele; Vendedor
  não acessa a tela.
- **FR-003**: O Gerente DEVE ver a lista, as abas Mais apostados e Campeonatos e a gaveta, desativar
  jogos e campeonatos só para a rede dele (FR-011 e FR-027) e ver o limite só para leitura. Alterar
  o limite, ativar/desativar para todos, favoritar e o cadastro manual DEVEM ser só de Admin e
  Supervisor, como as permissões padrão de hoje (`Funcao.php`), porque valem para todas as redes.
  Lançar e corrigir o resultado (`confrontos.lancar_resultado`) DEVE ser só do Admin: a função
  Supervisor deixa de poder usá-la e perde a permissão; a aba Pendentes só aparece para o Admin.
  Diferenças em relação ao antigo, aprovadas.

**Cartões e lista**

- **FR-004**: A tela DEVE mostrar os cartões **Jogos** (quantidade no dia e no tipo escolhidos),
  **Ao vivo agora**, **Desativados** (para todos e, para o Gerente, na rede dele) e **Pendentes de
  resultado** (só para quem tem `confrontos.lancar_resultado`), calculados no servidor e respeitando
  o dia, o tipo, a busca e o campeonato.
- **FR-005**: A aba Jogos DEVE listar os confrontos do dia escolhido (atalhos Ontem, Hoje (padrão),
  Amanhã, Depois de amanhã e "Escolher data", de até 30 dias atrás até depois de amanhã; fora disso,
  recusado no campo), no fuso de quem vê, ordenados por horário e time da casa, paginada
  (padrão 20, máximo 100), com o total do filtro no cabeçalho.
- **FR-006**: Cada linha DEVE mostrar casa x fora, campeonato e país, data e hora, ID do jogo, selo
  "Ao vivo" (com minuto e placar) quando o jogo estiver no ao vivo, marca "Manual" quando for o caso,
  situação geral (Ativo/Desativado), "Desativado para a minha rede" para o Gerente e o limite de
  valor apostado do pré-jogo.
- **FR-007**: A lista DEVE ter o filtro de tipo Todos / Pré-jogo / Ao vivo, a busca por time ou
  campeonato e o painel de filtros com dia (FR-005), campeonato (escolhido de uma lista), situação (Ativos,
  Desativados, Todos) e "Só manuais", com etiquetas removíveis. A busca NÃO DEVE escapar dos demais
  filtros.
- **FR-008**: Os filtros, a busca, a aba e a página DEVEM ficar no endereço da tela, para que
  recarregar ou voltar da tela Cotações mantenha a mesma lista.
- **FR-009**: O menu de ações (três pontinhos) DEVE ter, conforme a permissão: Ver detalhe, Alterar
  cotações, Alterar limite, Ativar/Desativar (para todos ou para a minha rede, conforme a função) e
  Lançar/Corrigir resultado e, nos jogos manuais, Editar e Excluir. Tocar na linha abre a gaveta.
  Ativar/Desativar pede confirmação. O cabeçalho da aba Jogos DEVE ter o botão "Cadastrar jogo" para
  quem tem `confrontos.cadastrar`.

**Situação**

- **FR-010**: Ativar/desativar para todos DEVE exigir `confrontos.alterar_situacao` e valer para o
  pré-jogo e o ao vivo do jogo, como no antigo.
- **FR-011**: Desativar para a minha rede DEVE usar os não permitidos da spec 003 com alvo Vendedores
  do próprio usuário (pré-jogo com `confrontos_nao_permitidos.gerenciar` e ao vivo com
  `confrontos_ao_vivo_nao_permitidos.gerenciar`), substituindo o antigo `confrontos_gerencias`.
  Admin e Supervisor DEVEM ver, na linha ou na gaveta, quantas redes desativaram o jogo.
- **FR-012**: A desativação NÃO DEVE mexer nas apostas já feitas; a confirmação DEVE avisar isso
  quando houver apostas em aberto no jogo.

**Limite**

- **FR-013**: O modal "Alterar limite" DEVE ter o limite do pré-jogo e, quando o jogo estiver no ao
  vivo, o do ao vivo, em campos de valor em reais (maior que zero, duas casas), e gravar pelas rotas
  de limite que já existem; as mensagens são as do servidor, no campo.

**Gaveta do confronto**

- **FR-014**: A gaveta (tela cheia no celular) DEVE ter no topo casa x fora, campeonato, horário,
  situação e selo Ao vivo, e as abas **Dados**, **Mais apostados**, **Apostas** e **Resultado**, cada
  uma carregada ao ser aberta; Apostas paginada (padrão 30, máximo 100).
- **FR-015**: **Dados**: ID, campeonato e país, esporte, data e hora, origem, situação geral,
  desativações por rede, limites e as cotações Casa, Empate e Fora como o site mostra para quem
  consulta.
- **FR-016**: **Mais apostados**: ranking das opções apostadas no jogo, com nome da opção como no
  cupom, quantidade de palpites, soma do valor apostado e do possível retorno, cada um separado em
  Simples e Múltiplas (a múltipla conta inteira, com o valor e o possível retorno totais da aposta,
  em cada jogo dela), da mais para a menos apostada (pela quantidade total), contando só apostas não canceladas da rede de quem consulta (antigo
  `destaquesModalidade`, que contava bilhetes ativos de usuários ativos).
- **FR-017**: **Apostas**: apostas com palpite no jogo, com código, data, vendedor ou cliente, opção,
  valor e situação, da rede de quem consulta, e o atalho para a aposta na tela Apostas (spec 018)
  para quem tem `apostas.listar`. Simples e múltiplas aparecem; nas múltiplas, só o palpite deste
  jogo é mostrado, com a indicação "Múltipla (N jogos)".
- **FR-018**: **Resultado**: placar final, 1º e 2º tempo, escanteios, gols por jogador e a origem
  (provedor ou manual), ou "Sem resultado".

**Mais apostados**

- **FR-019**: A aba Mais apostados (antiga "favoritos") DEVE listar os jogos com pelo menos uma
  aposta não cancelada da rede de quem consulta, com casa x fora, campeonato, horário, quantidade de
  apostas, valor apostado e possível retorno, separados em Simples e Múltiplas pela mesma regra de
  FR-016, da maior para a menor quantidade total, paginada (padrão 20,
  máximo 100).
- **FR-020**: A aba DEVE usar os mesmos filtros e a mesma busca da aba Jogos (dia, tipo, campeonato),
  e tocar num jogo DEVE abrir a gaveta já na aba Mais apostados (FR-016).
- **FR-021**: As quantidades e os valores DEVEM ser calculados no servidor, com o alcance por rede de
  FR-002, e bater com a aba Apostas da gaveta do mesmo jogo.

**Pendentes e resultado**

- **FR-022**: A aba Pendentes DEVE listar os jogos ativos, sem resultado, com apostas aguardando, que
  começaram há mais de 2 horas e há menos de 7 dias (regra do antigo), com casa x fora, campeonato,
  início, atraso, quantidade de apostas e valor em aberto, do maior para o menor atraso, paginada
  (padrão 20, máximo 100), com a busca da aba Jogos.
- **FR-023**: O modal de resultado DEVE ter, em grade e agrupados por seção: Placar final (casa e
  fora), Tempos (1º e 2º tempo, casa e fora) e Escanteios (casa e fora), todos obrigatórios, de 0 a
  99, e Gols (lista de jogador, minuto e tempo, opcional), e gravar pela rota de resultado manual da
  spec 009, com a apuração e a reapuração dela. A rota DEVE aceitar qualquer jogo, do provedor ou
  manual (como o antigo), e recusar quando a soma dos gols do 1º e do 2º tempo for diferente da soma
  do placar final, com "A soma dos gols dos times não bate com os gols dos tempos.". Um resultado
  diferente trazido depois pela carga do provedor substitui o lançado e reapura as apostas (como o
  antigo). A correção usa o mesmo modal, preenchido. Muda a spec 009 (FR-045 e FR-046).

**Cotações**

- **FR-024**: "Alterar cotações" DEVE levar à tela Cotações (spec 016), aba Jogos, com o editor do
  jogo aberto, e só aparecer para quem tem `porcentagens_confrontos.editar`.

**Campeonatos**

- **FR-025**: A aba Campeonatos DEVE listar os campeonatos com bandeira, nome, país, origem (provedor
  ou manual), quantidade de jogos disponíveis (antigo "N jogos disponível"), situação, favorito e,
  para o Gerente, "Desativado para a minha rede"; busca por nome e filtros de país, situação
  (Ativos, Desativados, Todos), favoritos e "Só manuais", com etiquetas; ordenados por favorito e
  nome; paginada (padrão 20, máximo 100). O cabeçalho DEVE ter "Cadastrar campeonato" para quem tem
  `campeonatos.cadastrar`.
- **FR-026**: Ativar/desativar o campeonato para todos DEVE exigir `campeonatos.alterar_situacao`,
  pedir confirmação e tirar do site o campeonato e os jogos dele; os jogos mostram "Campeonato
  desativado" na aba Jogos.
- **FR-027**: Desativar o campeonato para a minha rede DEVE usar os não permitidos de campeonato da
  spec 003 com alvo Vendedores do próprio usuário (`campeonatos_nao_permitidos.gerenciar`), com
  confirmação; Admin e Supervisor DEVEM ver quantas redes o desativaram.
- **FR-028**: O menu do campeonato DEVE ter, conforme a permissão: Ver jogos (abre a aba Jogos
  filtrada), Alterar cotações (tela Cotações, aba Campeonatos, com o editor aberto;
  `porcentagens_campeonatos.editar`), Favoritar/Desfavoritar (`campeonatos.favoritar`),
  Ativar/Desativar e, nos manuais, Adicionar jogo, Editar e Excluir.

**Cadastro manual**

- **FR-029**: O modal de campeonato manual DEVE ter nome, país (seleção) e bandeira (opcional), e
  criar o campeonato manual e ativo; o nome repetido entre os manuais é recusado com a mensagem do
  servidor.
- **FR-030**: O modal de jogo manual DEVE ter, em grade e por seção: **Jogo** (campeonato manual,
  escolhido de uma lista; esporte, seleção; data e hora no fuso de quem vê, só no futuro), **Times**
  (casa e fora, com escudo opcional) e **Cotações** (Casa, Empate e Fora e "Adicionar cotação" para
  escolher outros códigos de cotação agrupados por mercado, cada um maior ou igual a 1,00, com duas
  casas; pelo menos uma). Os valores e as mensagens são os da spec 003.
- **FR-031**: Editar DEVE existir só para registros manuais (`campeonatos.editar` e
  `confrontos.editar`), com o mesmo modal preenchido; no jogo, também a situação, como seleção com as
  situações que a spec 003 permite mudar à mão. Registros do provedor NÃO DEVEM mostrar Editar nem
  Excluir, e o servidor DEVE recusar o acesso direto (regra atual).
- **FR-032**: Excluir DEVE existir só para registros manuais (`campeonatos.excluir` e
  `confrontos.excluir`), com confirmação que mostra o que será apagado (no campeonato, a quantidade
  de jogos que saem junto) e a mensagem do servidor quando ele recusar. O servidor DEVE recusar a
  exclusão de jogo com apostas não canceladas ("Este jogo tem N apostas. Cancele o jogo em vez de
  excluir.") e de campeonato com algum jogo nessa condição ("Este campeonato tem jogos com
  apostas."), também por acesso direto. Muda a spec 003 (FR-063).

**Permissões**

- **FR-033**: As permissões DEVEM ser as que já existem (specs 003, 009 e 016), com os mesmos padrões
  por função, salvo `confrontos.lancar_resultado`, que passa a ser só do Admin (FR-003); não há
  permissão nova. As permissões e o alcance DEVEM ser aplicados no
  servidor em toda consulta e ação, e as ações sem permissão NÃO DEVEM aparecer na tela.

**Visual, responsividade e padrões do painel**

- **FR-034**: A tela DEVE seguir os padrões do painel (specs 014 a 022 e constituição): cores do
  tema, cabeçalho do painel, abas do painel, cartões de valor, painel de filtros com etiquetas,
  gaveta de detalhe, cabeçalho padrão nos modais (`ModalHeader`), campos com rótulo em cima, campo de
  valor em reais, paginação e menu de três pontinhos. Os modais DEVEM ter os campos em grade (duas
  colunas no computador, uma no celular), agrupados por seção, com Cancelar e Salvar no rodapé fixo;
  NÃO DEVE haver interruptor (toggle): escolhas de liga/desliga são campos de seleção.
- **FR-035**: No celular (até 900 px), as linhas DEVEM virar cartões (casa x fora, horário e
  situação; no campeonato, nome, jogos e situação) com o restante ao expandir; a gaveta e os modais ocupam a tela toda; os filtros abrem num
  painel próprio; nada pode exigir rolagem horizontal.
- **FR-036**: Cada área (cartões, lista, gaveta e cada aba dela) DEVE ter estados de carregando
  (esqueleto), vazio (com orientação) e erro com "tentar novamente", sem travar as outras.
- **FR-037**: Toda ação DEVE dar retorno imediato (botão com carregando e sem clique duplo,
  confirmação de sucesso ou mensagem do servidor) e atualizar a lista, os cartões e a gaveta sem
  recarregar a página nem perder os filtros e a página.
- **FR-039**: Com o filtro de tipo "Ao vivo" ou na aba Pendentes, a lista e o cartão "Ao vivo agora"
  DEVEM se atualizar sozinhos a cada 30 segundos, sem perder a página, os filtros, a gaveta ou um
  modal abertos, e parar enquanto a aba do navegador estiver escondida (voltando a atualizar ao
  reaparecer). Nas demais abas e filtros, a atualização é pelo botão "Atualizar" do cabeçalho e ao
  trocar filtro.

**Documentação**

- **FR-038**: A coleção do Postman DEVE ser regenerada com as rotas novas e alteradas (listagem de
  confrontos e de campeonatos com os campos e filtros novos, cartões, pendentes, mais apostados e
  apostas do jogo).

### Key Entities

- **Confronto** e **Confronto ao vivo** (existentes, spec 003): vistos na lista e na gaveta; ganham
  na resposta da listagem o selo ao vivo, a marca da rede e os limites; sem mudança de estrutura.
- **Campeonato** (existente, spec 003): visto na aba Campeonatos, com a quantidade de jogos; os
  manuais são cadastrados, editados e excluídos aqui.
- **Confronto não permitido**, **Confronto ao vivo não permitido** e **Campeonato não permitido**
  (existentes, spec 003): usados na desativação por rede.
- **Resultado do confronto** (existente, spec 009): visto na gaveta e lançado no modal.
- **Aposta** e **Palpite** (existentes, specs 004 e 018): contados em Mais apostados, Apostas e
  Pendentes, conforme a rede.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Um usuário encontra um jogo pelo nome do time e o desativa em até 4 toques a partir do
  menu.
- **SC-002**: Um usuário lança o placar de um jogo pendente em menos de 1 minuto a partir da aba
  Pendentes.
- **SC-003**: Em 100% das conferências manuais, o ranking de Mais apostados bate com as apostas da
  aba Apostas do mesmo jogo, na mesma rede.
- **SC-004**: Nenhuma ação ou aba aparece para quem não tem a permissão dela, todo acesso direto sem
  permissão é recusado pelo servidor, e em nenhum teste um Supervisor ou Gerente vê apostas de fora
  da rede dele.
- **SC-005**: Um gestor do sistema antigo encontra na tela nova cada informação e ação da "Lista de
  Jogos" (lista, tipo, período, busca, limite, cotações, cadeado, favoritos, destaques e pendentes
  com placar) e da tela "Campeonatos" (lista, pesquisa, favoritar e cadeado).
- **SC-007**: Um usuário cadastra um campeonato manual e um jogo com as três cotações principais em
  menos de 3 minutos a partir da aba Campeonatos.
- **SC-006**: A tela funciona no celular (360 px de largura) sem rolagem horizontal e com todos os
  alvos de toque com pelo menos 40 px; abrir a tela, trocar de aba ou de filtro mostra a primeira
  página em até 2 segundos numa conexão comum.

## Assumptions

- A listagem, a situação, os não permitidos, os limites (spec 003), o resultado manual com a
  reapuração (spec 009), o editor de cotações (spec 016) e a gestão e o cadastro manual de
  campeonatos e jogos (spec 003) já existem; esta spec os mostra e chama, acrescentando os cartões,
  os campos das listagens (inclusive a quantidade de jogos do campeonato e a marca da rede),
  Pendentes, Mais apostados e Apostas do jogo.
- A lista de países do campeonato manual e a de esportes do jogo manual vêm dos valores já usados
  pelos campeonatos e jogos do provedor.
- O período do antigo (de/até, até 2 dias à frente) vira o filtro de um dia por vez (FR-005), de até
  30 dias atrás até depois de amanhã; o filtro de dia da listagem pública do site (spec 003) não
  muda.
- Os valores (limite, apostado e possível retorno) são calculados no servidor em reais com duas
  casas, sem ponto flutuante.
- Datas no fuso de quem vê, como na listagem da spec 003.
- Não há exportação em planilha nesta tela.
- Não há dados fake nesta spec.
- Sem testes automatizados (constituição); a validação é manual pelo quickstart da spec.
