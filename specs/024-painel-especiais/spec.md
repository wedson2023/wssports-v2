# Feature Specification: Painel — Especiais (lista, cadastro com opções, exposição, encerramento com uma ou mais vencedoras e anulação de opção)

**Feature Branch**: `024-painel-especiais`

**Created**: 2026-10-28

**Status**: Draft

**Input**: User description: "Tela 'Especiais' do painel (a antiga 'modalidades'), no padrão das
outras telas do painel (specs 014 a 023), sobre o backend de especiais que já existe (spec 006:
tabelas especiais e especiais_opcoes, EspeciaisController, EspeciaisOpcoesController,
EncerramentoEspeciaisController, permissões especiais.listar, especiais.gerenciar e
especiais.encerrar). A tela deve ter: lista das categorias especiais com filtro por situação
(Aguardando, Encerrado, Cancelado) e busca por nome, paginada; modal de cadastro e edição da
categoria (nome, data limite para apostar, ativo) com as opções em grade (nome, cotação fixa,
ativo), permitindo adicionar, editar, desativar e remover opções enquanto a categoria estiver
Aguardando; ação Encerrar escolhendo a opção vencedora; ação Cancelar a categoria; e a exposição por
opção (quantidade de palpites, valor apostado e prêmio a pagar se a opção vencer), para o
administrador ajustar a cotação. Ajustes no backend incluídos nesta feature: (1) permitir encerrar a
categoria com uma ou mais opções vencedoras (ex.: 'Rebaixados' com 4 times), em vez de uma só; (2)
permitir anular uma opção sozinha (ex.: jogador que saiu do mercado): os palpites ativos nessa opção
são cancelados como cotação 1,00 e o prêmio das apostas é recalculado, sem encerrar a categoria.
Fora do escopo: agrupamento das categorias por tema, texto de regras por categoria e limite de valor
apostado por categoria."

## Clarifications

### Session 2026-10-28 (decisões tomadas antes do `/speckit-specify`)

- Q: Qual versão do sistema antigo é a referência? → A: A `main` atual do `wssports.bet`
  (`408cf730`), tela `screens/adm/modalities` ("Lista de modalidades - total N") e o modal
  `screens/adm/modals/modalities` ("Nova modalidade" / edição).
- Q: Como se chama a tela? → A: "Especiais", item do menu do painel, rota `/painel/especiais`. É o
  nome que o mercado usa para as apostas criadas pela própria casa (no exterior, "outrights" ou
  "apostas futuras"), e o mesmo nome que o site já mostra ao apostador desde a spec 006.
- Q: Há permissão nova? → A: Não. Usam-se as da spec 006: `especiais.listar`,
  `especiais.gerenciar` e `especiais.encerrar` (só Admin e Supervisor). Anular uma opção usa
  `especiais.encerrar`, porque mexe no resultado das apostas. (Alterado no `/speckit-clarify`:
  entra `especiais.corrigir_resultado`, só do Admin.)
- Q: A categoria pode ter mais de uma vencedora? → A: Sim. Ao encerrar, escolhem-se uma ou mais
  opções vencedoras (ex.: "Rebaixados 2026" com 4 times). Muda a spec 006 (FR-018), que aceitava só
  uma.
- Q: Uma opção pode ser anulada sem encerrar a categoria? → A: Sim. Os palpites ativos nela são
  cancelados como cotação 1,00, o prêmio das apostas é recalculado e a opção sai do site; o restante
  da categoria continua aceitando palpites. Novo em relação à spec 006 e ao antigo.
- Q: O que fica de fora? → A: Agrupamento das categorias por tema, texto de regras por categoria,
  limite de valor apostado por categoria e exportação da lista.
- Q: O visual pode mudar em relação ao antigo (Princípio VIII)? → A: Sim, no padrão do painel novo
  (specs 014 a 023), como nas specs anteriores. Nos modais, campos e botões organizados em grade e
  agrupados por seção; liga/desliga como campo de seleção, não interruptor.

### Session 2026-10-28 (respostas do responsável às perguntas do `/speckit-specify`)

- Q: A tela tem a "Lixeira" do antigo? → A: Sim. Aba "Lixeira" com as categorias removidas e a
  ação Restaurar, como no antigo e como na spec 022 (FR-025 e FR-029). Muda a spec 006, que não
  tinha restauração.
- Q: Com várias vencedoras, a cotação é paga inteira ou dividida? → A: Inteira por padrão; ao
  encerrar, a opção "Dividir por empate" ("dead heat") divide a cotação dos palpites vencedores
  empatados (regra detalhada no `/speckit-clarify`, FR-015).

### Session 2026-10-28 (`/speckit-clarify`)

- Q: Quantas opções vencedoras a categoria pode ter ao encerrar? → A: Um número definido no
  cadastro, "Quantidade de vencedoras" (padrão 1, editável enquanto Aguardando); o encerramento
  exige exatamente esse número. Marcar mais que ele só é aceito com "Dividir por empate" (empate na
  última vaga) (FR-008, FR-013 e FR-015).
- Q: Se a categoria for encerrada com a vencedora errada, há como corrigir? → A: Sim. Ação
  "Corrigir resultado", só do Admin e com a senha de quem faz: escolher as vencedoras de novo; as
  apostas são reapuradas, com estorno ou pagamento da diferença como na reapuração dos jogos (spec
  009), e o histórico guarda a troca (FR-030 a FR-033). Para isso entra a permissão nova
  `especiais.corrigir_resultado`, só do Admin, no lugar de "sem permissão nova".
- Q: A exposição do Supervisor conta as apostas de todo o sistema ou só da rede dele? → A: Só da
  rede dele, como nas outras telas (spec 019), com o aviso "Números da sua rede" na gaveta e nos
  cartões; o Admin vê todas (FR-002 e FR-022).
- Q: Com quantidade de vencedoras maior que 1 e empate na última vaga, a divisão reduz a cotação de
  todas as vencedoras ou só das empatadas? → A: Só das empatadas. No modal, quem encerra marca as
  vencedoras empatadas na última vaga; cada uma recebe a cotação × vagas que sobraram ÷ empatadas, e
  as demais recebem a cotação inteira (FR-015).

## Referências do sistema antigo *(Princípios VII e VIII)*

- Backend (`wssports.bet`, `main` `408cf730`): `app/Http/Controllers/ModalidadesController.php`
  (`index` com as abas `ativos` e `lixeira`, `pesquisar` e 250 por página; `store`; `show`; `update`,
  que também gravava o vencedor e apurava os bilhetes; `destroy`, que removia ou restaurava) e os
  models `Modalidades` e `ModalidadesEspeciais`. Rota `apiResource('modalidades')`.
- Telas: `resources/js/screens/adm/modalities` ("Lista de modalidades - total N", botão "Nova
  modalidade", abas "ativos" e "lixeira") e o modal `resources/js/screens/adm/modals/modalities`.
- Tela do apostador (já refeita na spec 006): `resources/js/screens/main/components/specials`.

**Como funcionava**:

- **Lista**: abas "ativos" e "lixeira", pesquisa por nome, 250 por página, da mais nova para a mais
  antiga. Colunas ID, NOME, VENCEDOR, SITUAÇÃO, ATIVO, DATA CRIAÇÃO e ENCERRA EM (dd/mm hh:mm no
  fuso de quem vê) e AÇÃO (editar; remover, ou restaurar na lixeira, com confirmação).
- **Modal**: nome (ex.: "Eleições 2022"), horário de encerramento; na edição também ativo (Sim/Não)
  e vencedor (seleção pelo nome da opção); opções digitadas com nome e "odd", adicionadas uma a uma
  e removidas pelo "x".
- **Encerramento**: escolher o vencedor na edição mudava a situação para "Encerrado" e recalculava
  os bilhetes com palpites na modalidade feitos entre a criação e o horário de encerramento.
- **Problemas encontrados**: o botão "Nova modalidade" dependia da trava `cadastrar_vendedor`; as
  colunas VENCEDOR e SITUAÇÃO mostravam os valores trocados; o vencedor era gravado pelo nome
  (texto), não pela opção; salvar a edição apagava de vez todas as opções e as criava de novo,
  quebrando o vínculo com os palpites já feitos; o vencedor podia ser trocado depois de encerrado e
  a modalidade continuava editável; só cabia um vencedor; não havia como cancelar a modalidade nem
  anular uma opção; não havia visão de quanto estava apostado em cada opção; a remoção não conferia
  se havia apostas; as dicas dos campos falavam em "usuário"; o modal era uma coluna só.

**Diferenças aprovadas** (padrão das specs 014 a 023):

- Permissões por função da spec 006 no lugar das travas.
- A aba "ativos" vira a aba "Especiais", com filtro por situação (Aguardando, Encerrado,
  Cancelado); a aba "Lixeira" continua, com Restaurar; a remoção continua lógica e só é aceita sem
  palpites em apostas ativas, como no backend da spec 006.
- Opções com vínculo próprio: editar o nome ou a cotação não recria a opção; a cotação nova vale
  para os próximos palpites.
- Encerrar e cancelar são ações próprias, com confirmação; a categoria encerrada ou cancelada não é
  mais editada. O resultado do encerramento só muda pela ação "Corrigir resultado" (Admin, com
  senha), que reapura as apostas; o cancelamento é final.
- Uma ou mais vencedoras; anulação de uma opção; exposição por opção na gaveta.
- Visual do painel novo: tema, cabeçalho, `StatCard`, filtros com etiquetas, lista que vira cartões
  no celular, menu de ações de três pontinhos, gaveta, `ModalHeader`, campos em grade e campos de
  seleção.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Consultar os especiais (Priority: P1)

O usuário do painel abre "Especiais", vê os cartões de resumo e a lista das categorias (nome,
quantidade de opções, data limite, situação, vencedoras, apostas em aberto e valor apostado), filtra
pela situação e busca pelo nome.

**Why this priority**: é a consulta do dia a dia e a base das outras ações; sozinha já substitui a
"Lista de modalidades" do antigo para consulta.

**Independent Test**: com três categorias (uma aceitando palpites, uma com a data limite já passada
e outra encerrada), abrir Especiais como Admin e conferir os cartões e as colunas; filtrar por
Encerrado e ver só a encerrada; buscar parte do nome e ver só as que casam.

**Acceptance Scenarios**:

1. **Given** um usuário com `especiais.listar`, **When** ele abre Especiais, **Then** vê os cartões
   e a lista com o total no cabeçalho, filtrada por "Aguardando", paginada, com ID, nome, quantidade
   de opções ativas, data limite (dd/mm/aaaa hh:mm no horário de Brasília), situação, ativo,
   vencedoras (quando encerrada), quantidade de apostas em aberto e valor apostado em aberto.
2. **Given** uma categoria Aguardando com a data limite já passada, **When** ela aparece na lista,
   **Then** tem a marca "Aguardando resultado", para o usuário saber que falta encerrá-la.
3. **Given** o filtro de situação, **When** o usuário escolhe Aguardando, Encerrado, Cancelado ou
   Todas, **Then** a lista e o total são recarregados e o filtro aparece como etiqueta removível.
4. **Given** a busca, **When** o usuário digita parte do nome, **Then** a lista mostra só as
   categorias que casam, sem escapar do filtro de situação.
5. **Given** um usuário sem `especiais.listar`, **When** ele abre o painel, **Then** não vê o item
   "Especiais" no menu e a rota `/painel/especiais` não abre a tela.
6. **Given** a tela no celular, **When** o usuário abre Especiais, **Then** cada categoria aparece
   como um cartão, com as mesmas informações e o mesmo menu de ações.

---

### User Story 2 - Cadastrar e editar a categoria com as opções (Priority: P1)

O usuário cadastra uma categoria (ex.: "Campeão Brasileiro 2026") com a data limite para apostar e
as opções com a cotação de cada uma, e depois altera o nome, a data limite, se está ativa e as
opções (adicionar, alterar nome ou cotação, desativar e remover).

**Why this priority**: sem cadastro pela tela os especiais só existem pela API; é o que o
administrador mais faz depois de consultar.

**Independent Test**: cadastrar "Artilheiro da Copa" com três opções, conferir que aparece na lista
e no site; editar a cotação de uma opção, conferir que o site mostra a cotação nova e que um palpite
feito antes mantém a cotação antiga; desativar uma opção e conferir que ela some do site.

**Acceptance Scenarios**:

1. **Given** um usuário com `especiais.gerenciar`, **When** ele clica em "Novo especial", **Then**
   abre o modal com nome, data limite (data e hora, horário de Brasília), ativo (Sim/Não, padrão Sim)
   e a grade de opções com nome e cotação, já com duas linhas vazias.
2. **Given** o modal de cadastro, **When** o usuário preenche nome, data limite e pelo menos duas
   opções válidas e salva, **Then** a categoria é criada como Aguardando, o modal fecha, a lista é
   recarregada e aparece a mensagem de sucesso.
3. **Given** dados inválidos (nome vazio ou repetido, data limite vazia, menos de duas opções, nome
   de opção repetido, cotação menor que 1,01 ou com mais de duas casas), **When** o usuário salva,
   **Then** o modal continua aberto com a mensagem do servidor em cada campo.
4. **Given** uma categoria Aguardando, **When** o usuário abre "Editar", **Then** o modal mostra os
   dados e as opções, cada uma com nome, cotação e ativo, e permite adicionar uma opção, alterar o
   nome ou a cotação, desativar e remover.
5. **Given** uma opção com palpites em apostas ativas, **When** o usuário tenta removê-la, **Then** a
   remoção é recusada com "Desative a opção em vez de remover." e ela continua na grade.
6. **Given** a cotação de uma opção alterada, **When** o usuário salva, **Then** os próximos palpites
   usam a cotação nova e os palpites já feitos mantêm a deles.
7. **Given** uma categoria Encerrada ou Cancelada, **When** o usuário abre o menu de ações, **Then**
   não há "Editar", e a gaveta mostra os dados só para consulta.
8. **Given** um usuário com `especiais.listar` sem `especiais.gerenciar`, **When** ele abre a tela,
   **Then** não vê "Novo especial", "Editar" nem "Remover".

---

### User Story 3 - Encerrar a categoria com uma ou mais vencedoras (Priority: P1)

Quando o resultado sai, o usuário encerra a categoria escolhendo a opção vencedora, ou as
vencedoras (ex.: os 4 rebaixados). Os palpites recebem o resultado e as apostas que já podem ser
decididas são apuradas e pagas.

**Why this priority**: sem encerramento as apostas especiais nunca são pagas; é o fechamento do
ciclo e o motivo da mudança no backend.

**Independent Test**: com uma categoria "Rebaixados" de 6 opções e apostas simples em três delas,
encerrar marcando duas dessas como vencedoras; conferir que as apostas nelas ficam Vencedor e são
pagas, que a da terceira fica Perdedor e que a categoria aparece como Encerrado com as duas
vencedoras.

**Acceptance Scenarios**:

1. **Given** uma categoria Aguardando e um usuário com `especiais.encerrar`, **When** ele escolhe
   "Encerrar", **Then** abre o modal com as opções da categoria (as anuladas não podem ser marcadas)
   para marcar uma ou mais vencedoras, e a quantidade de apostas em aberto que serão apuradas.
2. **Given** o modal de encerrar, **When** o usuário confirma com pelo menos uma vencedora, **Then**
   a categoria fica Encerrado com as vencedoras, cada palpite ativo fica Vencedor (opção vencedora)
   ou Perdedor (as demais), as apostas que já podem ser decididas são apuradas e pagas pela regra da
   apuração dos jogos (spec 009), e aparece a mensagem com a quantidade de apostas afetadas.
3. **Given** uma aposta com o especial e um jogo ainda sem resultado, **When** a categoria é
   encerrada com a opção dela vencedora, **Then** o palpite especial fica Vencedor e a aposta
   continua aguardando o jogo.
4. **Given** uma categoria com "Quantidade de vencedoras" 4, **When** o usuário confirma com menos
   de 4 marcadas, ou com mais de 4 sem "Dividir por empate", ou com todas as opções não anuladas
   marcadas, **Then** o encerramento é recusado com a mensagem do servidor ("Escolha 4 opções
   vencedoras." ou "Para todas vencerem, cancele a categoria.").
5. **Given** uma categoria com a data limite ainda no futuro, **When** o usuário abre "Encerrar",
   **Then** o modal avisa "A data limite ainda não passou: a categoria deixa de aceitar palpites ao
   encerrar." e permite seguir.
6. **Given** uma categoria com "Quantidade de vencedoras" 1, duas vencedoras marcadas e "Dividir por
   empate" escolhido, **When** o usuário confirma, **Then** cada palpite vencedor é pago com a
   cotação dele dividida por 2 (ex.: 5,00 vira 2,50), e o comprovante mostra a cotação original e a
   aplicada.
7. **Given** "Rebaixados" com quantidade 4, cinco vencedoras marcadas, duas delas como empatadas e
   "Dividir por empate" escolhido, **When** o usuário confirma, **Then** os palpites nas três não
   empatadas recebem a cotação inteira e os palpites nas duas empatadas recebem a cotação ÷ 2.
8. **Given** uma categoria já Encerrada ou Cancelada, **When** alguém tenta encerrá-la de novo (outra
   aba, outro usuário), **Then** é recusado com "A categoria já foi encerrada ou cancelada." e a lista
   é recarregada.

---

### User Story 4 - Ver a exposição por opção (Priority: P2)

O usuário abre a gaveta da categoria e vê, para cada opção, quantas apostas estão nela, quanto foi
apostado e quanto a casa pagaria se ela vencesse, para decidir se ajusta a cotação ou desativa a
opção.

**Why this priority**: é o controle de risco que o antigo não tinha; útil, mas a tela já funciona
sem ele.

**Independent Test**: com duas apostas simples e uma múltipla na mesma opção, abrir a gaveta e
conferir a quantidade, o valor apostado e o possível retorno, separados em Simples e Múltiplas, e o
total da categoria.

**Acceptance Scenarios**:

1. **Given** um usuário com `especiais.listar`, **When** ele clica na categoria (ou em "Ver
   detalhes"), **Then** abre a gaveta com os dados da categoria e a tabela das opções: nome,
   cotação, situação (Ativa, Desativada, Anulada, Vencedora, Perdedora), quantidade de apostas,
   valor apostado e possível retorno, separados em Simples e Múltiplas.
2. **Given** a tabela da gaveta, **When** o usuário a lê, **Then** a opção com o maior possível
   retorno fica destacada e há uma linha de total da categoria (apostas, valor apostado).
3. **Given** um Supervisor, **When** ele abre a gaveta, **Then** os números contam só as apostas da
   rede dele, como nas outras telas do painel (spec 019), com o aviso "Números da sua rede"; o Admin
   vê todas, sem o aviso.
4. **Given** a gaveta de uma categoria Aguardando e um usuário com `especiais.gerenciar`, **When**
   ele clica em "Editar" na gaveta, **Then** abre o modal de edição da categoria.

---

### User Story 5 - Anular uma opção (Priority: P2)

Uma opção deixa de fazer sentido antes do resultado (ex.: o jogador foi cortado). O usuário anula a
opção: os palpites nela são devolvidos (contam como cotação 1,00), o prêmio das apostas é
recalculado e a categoria continua aceitando palpites nas outras opções.

**Why this priority**: evita que apostas numa opção impossível fiquem perdidas até o fim da
categoria; acontece menos que o encerramento.

**Independent Test**: com uma aposta simples na opção A e uma múltipla (opção A + um jogo), anular a
opção A; conferir que a simples fica com o valor devolvido, que a múltipla segue só com o jogo e o
prêmio recalculado, que a opção some do site e que as outras opções continuam.

**Acceptance Scenarios**:

1. **Given** uma categoria Aguardando e um usuário com `especiais.encerrar`, **When** ele escolhe
   "Anular" numa opção (na gaveta), **Then** a confirmação mostra a opção e a quantidade de apostas
   em aberto nela e explica que os palpites serão devolvidos.
2. **Given** a confirmação, **When** o usuário confirma, **Then** a opção fica Anulada, cada palpite
   ativo nela em aposta ativa e ainda sem resultado é cancelado pelo sistema com o motivo "Opção
   anulada", o prêmio da aposta é recalculado e registrado no histórico, a aposta que fica sem
   palpites ativos é decidida pela regra da apuração, e aparece a mensagem com a quantidade de
   apostas afetadas.
3. **Given** uma opção Anulada, **When** o apostador abre Especiais no site, **Then** ela não
   aparece; e no painel ela não pode ser reativada, editada nem marcada como vencedora.
4. **Given** uma categoria em que todas as opções restantes foram anuladas, **When** o usuário vê a
   lista, **Then** a categoria some do site e só pode ser cancelada.

---

### User Story 6 - Cancelar e remover a categoria (Priority: P3)

O usuário cancela uma categoria que não vai ter resultado (todos os palpites são devolvidos) ou
remove uma categoria cadastrada por engano, sem apostas.

**Why this priority**: são ações raras; o backend já as faz e a tela só precisa oferecê-las.

**Independent Test**: cancelar uma categoria com uma aposta simples e conferir que o valor é
devolvido e a categoria fica Cancelado; remover uma categoria sem apostas e conferir que ela some da
lista; tentar remover uma com apostas e ver a recusa.

**Acceptance Scenarios**:

1. **Given** uma categoria Aguardando e um usuário com `especiais.encerrar`, **When** ele confirma
   "Cancelar", **Then** a categoria fica Cancelado, os palpites ativos são cancelados como cotação
   1,00, os prêmios são recalculados e aparece a quantidade de apostas afetadas.
2. **Given** uma categoria Aguardando sem palpites em apostas ativas e um usuário com
   `especiais.gerenciar`, **When** ele confirma "Remover", **Then** ela some da lista e do site.
3. **Given** uma categoria com palpites em apostas ativas, **When** o usuário tenta removê-la,
   **Then** é recusado com "A categoria tem palpites em apostas ativas: cancele a categoria em vez de
   remover."
4. **Given** uma categoria removida, **When** o usuário abre a aba "Lixeira" e confirma
   "Restaurar", **Then** ela volta para a aba Especiais com as opções que tinha, na mesma situação.
5. **Given** uma categoria na lixeira com o mesmo nome de uma categoria cadastrada depois, **When**
   o usuário tenta restaurá-la, **Then** é recusado com "Já existe uma categoria especial com este
   nome."

---

### User Story 7 - Corrigir o resultado de uma categoria encerrada (Priority: P2)

O Admin encerrou a categoria com a vencedora errada. Ele escolhe as vencedoras certas, confirma com
a senha, e as apostas são apuradas de novo: quem deixou de ganhar tem o prêmio estornado, quem
passou a ganhar recebe.

**Why this priority**: o erro é raro, mas sem a correção o prejuízo é imediato (prêmios já pagos) e
o conserto à mão, aposta por aposta, é inviável.

**Independent Test**: encerrar uma categoria com a opção A vencedora e conferir o pagamento das
apostas em A; corrigir para a opção B; conferir que as apostas em A têm o prêmio estornado (saldo
pode ficar negativo), que as apostas em B são pagas e que o histórico mostra a troca.

**Acceptance Scenarios**:

1. **Given** uma categoria Encerrada e um Admin, **When** ele escolhe "Corrigir resultado", **Then**
   abre o modal com as vencedoras atuais marcadas, as mesmas regras de quantidade e de divisão por
   empate do encerramento (FR-013 e FR-015), o campo de senha e o aviso de que as apostas serão
   reapuradas, com estorno de prêmios já pagos.
2. **Given** o modal com outras vencedoras e a senha correta, **When** o Admin confirma, **Then** as
   vencedoras são trocadas, cada palpite recebe o resultado novo, as apostas são reapuradas como na
   correção de placar da spec 009 (estorno do valor exato pago e crédito do prêmio novo, com
   transações novas, sem apagar as antigas) e aparece a quantidade de apostas afetadas.
3. **Given** a senha errada, **When** o Admin confirma, **Then** a correção é recusada com o erro no
   campo de senha e nada muda.
4. **Given** as mesmas vencedoras e a mesma escolha de divisão, **When** o Admin confirma, **Then** é
   recusado com "O resultado é o mesmo." e nenhuma aposta é reapurada.
5. **Given** um Supervisor, **When** ele abre o menu de uma categoria Encerrada, **Then** não vê
   "Corrigir resultado".

---

### Edge Cases

- Dois usuários editam a mesma categoria ao mesmo tempo e um deles a encerra: a edição do outro é
  recusada com "A categoria já foi encerrada ou cancelada.".
- Cotação alterada ou opção desativada depois de o palpite entrar no cupom do apostador: vale a regra
  da spec 006 (o cupom avisa e pede para aceitar a cotação nova ou remover o palpite).
- Aposta com dois palpites especiais de categorias diferentes: cada encerramento decide o seu
  palpite; a aposta só é paga quando todos os palpites ativos tiverem resultado.
- Aposta pendente (código não validado) ou em análise com palpite numa opção anulada ou numa
  categoria encerrada: não é tocada, como no encerramento da spec 006; o especial fica indisponível
  nela.
- Data limite alterada para o futuro numa categoria que já passou da data: a categoria volta a
  aceitar palpites.
- Data limite no passado no cadastro: aceita (a categoria nasce fechada para palpites); o modal
  avisa antes de salvar.
- Categoria sem nenhuma opção ativa: não aparece no site (spec 006), mas continua na lista do painel.
- Opção desativada (não anulada) com palpites: os palpites continuam valendo e a opção pode ser
  marcada como vencedora.
- Encerramento ou anulação com muitas apostas: a confirmação fica bloqueada até a resposta, para não
  enviar duas vezes.

## Requirements *(mandatory)*

### Functional Requirements

**Menu, lista e cartões**

- **FR-001**: O menu do painel DEVE ter o item "Especiais", rota `/painel/especiais`, visível para
  quem tem `especiais.listar`.
- **FR-002**: A tela DEVE ter quatro cartões de resumo: "Aceitando palpites" (Aguardando, ativas, com
  data limite futura), "Aguardando resultado" (Aguardando com a data limite passada), "Valor apostado
  em aberto" e "Possível retorno em aberto" (das apostas ativas sem resultado com palpite especial,
  na rede de quem consulta), que respeitam a busca; para quem não vê todas as redes, os cartões
  têm o aviso "Números da sua rede".
- **FR-003**: A lista DEVE ser paginada (padrão 20, máximo 100), com filtro de situação (Aguardando
  — padrão —, Encerrado, Cancelado e Todas) e busca por parte do nome; os filtros aparecem como
  etiquetas removíveis.
- **FR-004**: Cada linha DEVE mostrar ID, nome, quantidade de opções ativas, data limite no horário
  de Brasília, situação (com a marca "Aguardando resultado" quando a data passou), ativo, vencedoras
  (encerradas), quantidade de apostas em aberto e valor apostado em aberto.
- **FR-005**: A ordem DEVE ser pela data limite (a mais próxima primeiro) em Aguardando e pela data
  do encerramento (a mais recente primeiro) em Encerrado e Cancelado; em Todas, as Aguardando
  primeiro.
- **FR-006**: O menu de ações (três pontinhos) DEVE ter, conforme a situação e as permissões: Ver
  detalhes, Editar e Remover (`especiais.gerenciar`, só Aguardando), Encerrar e Cancelar
  (`especiais.encerrar`, só Aguardando), Corrigir resultado (`especiais.corrigir_resultado`, só
  Encerrado); na aba Lixeira, Ver detalhes e Restaurar
  (`especiais.gerenciar`).
- **FR-007**: No celular a lista DEVE virar cartões com as mesmas informações e ações.

**Cadastro e edição**

- **FR-008**: O modal de cadastro DEVE ter nome (até 150 caracteres, único entre as não removidas),
  data limite (data e hora, horário de Brasília), ativo (seleção Sim/Não), quantidade de vencedoras
  (número inteiro, padrão 1, menor que a quantidade de opções) e a grade de opções (nome
  até 150 caracteres, único na categoria, sem diferenciar maiúsculas; cotação a partir de 1,01 com
  até duas casas), com no mínimo 2 e no máximo 100 opções.
- **FR-009**: O modal de edição DEVE alterar nome, data limite, ativo e quantidade de vencedoras da
  categoria e, na grade,
  adicionar uma opção, alterar nome, cotação e ativo de cada opção e remover a opção sem palpites em
  apostas ativas; as opções anuladas aparecem só para consulta.
- **FR-010**: Salvar a edição DEVE gravar a categoria e cada opção alterada sem recriar as opções
  (o vínculo com os palpites é mantido); se parte falhar, o modal continua aberto com o erro no campo
  e o aviso do que já foi salvo.
- **FR-011**: O modal DEVE avisar antes de salvar quando a data limite estiver no passado.
- **FR-012**: Categoria Encerrada ou Cancelada NÃO DEVE ser editada nem removida (a Encerrada só
  muda pela correção de resultado, FR-030); opção Anulada NÃO DEVE ser editada, reativada, removida
  nem marcada como vencedora, inclusive na correção.

**Encerramento com uma ou mais vencedoras (muda a spec 006, FR-018)**

- **FR-013**: Encerrar DEVE aceitar opções vencedoras da própria categoria, não anuladas, em
  quantidade exatamente igual à "Quantidade de vencedoras" da categoria; mais que ela só com
  "Dividir por empate" (FR-015). DEVE recusar menos que a quantidade e todas as opções não anuladas
  como vencedoras (nesse caso, o caminho é cancelar). Se as opções não anuladas não chegarem à
  quantidade de vencedoras (ex.: depois de anulações), o caminho também é cancelar.
- **FR-014**: Ao encerrar, cada palpite ativo da categoria em aposta ativa e sem resultado DEVE ficar
  Vencedor, se a opção dele está entre as vencedoras, ou Perdedor, nos demais casos; as apostas DEVEM
  ser decididas e pagas pela regra da apuração da spec 009, como já acontece com uma vencedora.
- **FR-015**: O palpite vencedor DEVE pagar a cotação gravada nele, inteira, quando o número de
  vencedoras marcadas é igual à "Quantidade de vencedoras". Quando o usuário marca mais vencedoras
  que ela (empate na última vaga), o modal DEVE exigir a escolha "Dividir por empate" (seleção
  Não/Sim; sem Sim, o encerramento é recusado) e a marcação de quais vencedoras empataram na última
  vaga. Sejam Q a quantidade de vencedoras, M as marcadas e E as empatadas: as não empatadas (M − E)
  DEVEM ser menos que Q; as vagas que sobram para as empatadas são V = Q − (M − E), e E DEVE ser
  maior que V. Cada palpite numa empatada recebe a cotação × V ÷ E (ex.: 4 rebaixados, 3 certos e 2
  empatados na 4ª vaga → V = 1, cotação ÷ 2; campeão com 2 empatados → V = 1, cotação ÷ 2); cada
  palpite numa não empatada recebe a cotação inteira. A cotação aplicada é arredondada para baixo em
  duas casas e é ela que entra na cotação total e no prêmio da aposta, mesmo que fique abaixo de
  1,00 (como no "dead heat" das casas grandes). O palpite guarda a cotação original e a aplicada.
- **FR-016**: A categoria encerrada DEVE guardar todas as vencedoras, quais empataram na última vaga,
  se houve divisão por empate,
  quem encerrou e quando; a lista, a gaveta, o comprovante e o histórico da aposta DEVEM mostrar as
  vencedoras e, com divisão, a cotação original e a aplicada.
- **FR-017**: O modal de encerrar DEVE mostrar a quantidade de apostas em aberto que serão apuradas
  e avisar quando a data limite ainda não passou.

**Anulação de uma opção (nova)**

- **FR-018**: Com a categoria Aguardando, quem tem `especiais.encerrar` DEVE poder anular uma opção
  não anulada; a opção passa a Anulada, sai do site e não aceita mais palpites.
- **FR-019**: Ao anular, cada palpite ativo na opção, em aposta ativa e sem resultado, DEVE ser
  cancelado pelo sistema com o motivo "Opção anulada" (fora da cotação total, como cotação 1,00),
  com o prêmio recalculado e o registro no histórico da aposta, pela mesma regra do cancelamento de
  palpite da spec 006; a aposta que ficar sem palpite ativo pendente DEVE ser decidida e paga pela
  regra da apuração (ex.: aposta simples devolvida).
- **FR-020**: A anulação DEVE pedir confirmação mostrando a quantidade de apostas afetadas e DEVE
  responder com a quantidade de apostas alteradas.

**Exposição (gaveta)**

- **FR-021**: A gaveta da categoria DEVE mostrar os dados da categoria e, por opção, nome, cotação
  atual, situação, quantidade de apostas, valor apostado e possível retorno, separados em Simples e
  Múltiplas, mais o total da categoria; a opção com o maior possível retorno fica destacada.
- **FR-022**: A exposição DEVE contar só apostas Ativas ainda sem resultado, com o palpite ativo na
  opção, na rede de quem consulta (spec 019: o Admin vê todas; o Supervisor, só a rede dele, com o
  aviso "Números da sua rede"); cada aposta múltipla conta inteira (valor e possível retorno
  totais), como na spec 023.
- **FR-023**: A gaveta DEVE ter os atalhos Editar, Encerrar, Cancelar e, em cada opção, Anular,
  conforme a situação e as permissões.

**Cancelar e remover**

- **FR-024**: Cancelar DEVE seguir a regra atual da spec 006 (FR-018a), com confirmação que mostra a
  quantidade de apostas afetadas.
- **FR-025**: Remover DEVE seguir a regra atual (remoção lógica, recusada com palpites em apostas
  ativas), com confirmação; a categoria removida vai para a aba "Lixeira", com as opções dela.
- **FR-029**: A aba "Lixeira" DEVE listar as categorias removidas (nome, quantidade de opções, data
  limite, situação e data da remoção, da mais recente para a mais antiga), paginada e com a busca, e
  permitir Restaurar a quem tem `especiais.gerenciar`, com confirmação: a categoria volta com as
  opções que tinha e na mesma situação; é recusado se já existir outra categoria não removida com o
  mesmo nome. Não há exclusão definitiva.

**Correção de resultado (nova)**

- **FR-030**: Numa categoria Encerrada, quem tem `especiais.corrigir_resultado` (permissão nova,
  padrão só do Admin) DEVE poder trocar as vencedoras, as empatadas e a escolha de divisão por
  empate, com as
  mesmas regras do encerramento (FR-013 e FR-015) e a senha de quem faz; mesmas vencedoras e mesma
  divisão são recusadas com "O resultado é o mesmo.".
- **FR-031**: Ao corrigir, cada palpite da categoria DEVE receber o resultado novo e a cotação
  aplicada nova, e todas as apostas com palpite nela, inclusive as já decididas e pagas, DEVEM ser
  reapuradas pela regra da correção de resultado da spec 009: estorno do valor exato pago na mesma
  carteira (o saldo pode ficar negativo) e crédito do prêmio novo, com transações novas ligadas às
  originais, sem apagar nenhuma.
- **FR-032**: A categoria DEVE guardar o histórico das correções (vencedoras anteriores e novas,
  divisão anterior e nova, quem corrigiu e quando), mostrado na gaveta; o histórico de cada aposta
  afetada DEVE registrar a reapuração com a origem "Correção de especial".
- **FR-033**: A correção NÃO DEVE ser feita em categoria Cancelada, e a resposta DEVE trazer a
  quantidade de apostas afetadas.

**Geral**

- **FR-026**: Todas as ações DEVEM ser conferidas no servidor pelas permissões da spec 006 e pela
  permissão nova `especiais.corrigir_resultado` (grupo Especiais, "Corrigir resultado de
  especiais"); o frontend só esconde o que a função não tem.
- **FR-027**: Mensagens de erro do servidor DEVEM aparecer no campo do modal ou como aviso; as ações
  de confirmação DEVEM ficar bloqueadas enquanto esperam a resposta.
- **FR-028**: A coleção do Postman DEVE ser regenerada com as rotas novas e alteradas (encerrar com
  várias vencedoras e divisão por empate, anular opção, corrigir resultado, exposição, lixeira,
  restaurar e o que a
  lista passar a devolver).

### Key Entities *(include if feature involves data)*

- **Categoria especial** (spec 006): nome, data limite, situação (Aguardando, Encerrado,
  Cancelado), ativo, quem encerrou e quando. Ganha a **quantidade de vencedoras** e passa a ter
  **uma ou mais** opções vencedoras, a
  indicação de divisão por empate, a restauração da lixeira e o histórico de correções de
  resultado.
- **Correção de resultado**: vencedoras e divisão anteriores e novas, quem corrigiu, quando e a
  quantidade de apostas reapuradas.
- **Opção especial** (spec 006): nome, cotação fixa, ativo. Ganha a situação **Anulada** (com quem
  anulou e quando), além de vencedora/perdedora depois do encerramento.
- **Palpite especial** (spec 006): a opção escolhida e a cotação gravada; ganha a cotação aplicada
  (com divisão por empate) e o motivo de cancelamento "Opção anulada".
- **Exposição da opção** (calculada, não gravada): quantidade de apostas, valor apostado e possível
  retorno, separados em Simples e Múltiplas.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: O administrador cadastra uma categoria com 5 opções em menos de 2 minutos, sem usar a
  API.
- **SC-002**: Ao encerrar uma categoria com uma ou mais vencedoras, 100% dos palpites ativos dela
  recebem o resultado e 100% das apostas que já podem ser decididas são pagas na mesma ação.
- **SC-003**: Ao anular uma opção, 100% das apostas afetadas ficam com o prêmio recalculado e o
  registro no histórico, e as demais opções continuam aceitando palpites sem interrupção.
- **SC-004**: O administrador identifica, em até 3 cliques a partir do menu, a opção com o maior
  possível retorno de qualquer categoria em aberto.
- **SC-005**: Nenhuma categoria cancelada e nenhuma opção anulada pode ser alterada pela tela nem
  pela API; a categoria encerrada só muda pela correção de resultado do Admin.
- **SC-007**: Depois de uma correção de resultado, 100% das apostas da categoria ficam com o saldo
  igual ao que teriam se o resultado certo tivesse sido lançado da primeira vez, somando pagamentos
  e estornos.
- **SC-006**: A tela funciona no celular sem rolagem horizontal, com todas as ações disponíveis.

## Assumptions

- O backend de especiais da spec 006 (cadastro, opções, encerramento, cancelamento e lista pública)
  e a apuração da spec 009 continuam como estão, exceto o que esta spec muda: várias vencedoras,
  anulação de opção, exposição e os campos novos da lista.
- As permissões continuam só para Admin e Supervisor (spec 006), e a nova
  `especiais.corrigir_resultado` só para o Admin; o Gerente e o Vendedor não veem a tela. O alcance por rede da spec 019 vale só nos números de apostas (cartões, lista e gaveta); as
  categorias são as mesmas para todos.
- Cancelado é final; Encerrado só muda pela correção de resultado (FR-030 a FR-033). A correção não
  reabre a categoria para palpites.
- Encerrar antes da data limite é permitido, com aviso, como no antigo.
- As categorias que já existem passam a ter "Quantidade de vencedoras" 1, o comportamento da spec
  006.
- O site do apostador (spec 006) não muda, exceto deixar de mostrar as opções anuladas e mostrar as
  vencedoras no comprovante e no histórico.
- Sem dados fake: todos os dados vêm do backend.
- Fora do escopo: agrupamento por tema, texto de regras por categoria, limite de valor apostado por
  categoria e exportação.
