# Feature Specification: Painel — Usuários (Supervisores, Gerentes e Vendedores)

**Feature Branch**: `015-painel-usuarios`

**Created**: 2026-10-20

**Status**: Draft

**Input**: User description: "Painel — Usuários (Supervisores, Gerentes e Vendedores). O backend de
usuários existe desde a spec 001 e as configurações do vendedor desde as specs 003/004, mas não há
telas. Criar as telas de usuários no painel (base da spec 014), trazendo as telas do sistema antigo
(screens/adm/register e modals/register de supervisor, gerente e vendedor; menu 'Usuários'), com as
mesmas informações e nos mesmos lugares, no visual aprovado da Dashboard. Menu por função, alcance por
nível, listas com abas Ativos/Lixeira, cadastro em qualquer nível abaixo com escolha do superior,
modais de cadastro/edição (vendedor com Comissões e Limites e regras; gerente com Regras da equipe),
modal de permissões agrupadas no lugar das 'travas' do antigo e Lixeira só com Restaurar."

## Clarifications

### Session 2026-10-20 (decisões tomadas antes do `/speckit-specify`)

- Q: Quem pode cadastrar quem? → A: Qualquer nível abaixo, como no antigo: Admin cadastra supervisor,
  gerente e vendedor; Supervisor cadastra gerente e vendedor; Gerente cadastra vendedor. Abaixo do
  nível imediato, quem cadastra escolhe o superior dentro da sua rede. Muda a regra da API da spec
  001 (só a função logo abaixo, sempre vinculada a quem cadastra).
- Q: Como fica a Lixeira? → A: Só com Restaurar. Sem "apagar para sempre" (exclusão física proibida
  pela constituição; as apostas do usuário seguem no histórico e no caixa).
- Q: Onde ficam comissões, limites e regras do vendedor? → A: No modal do vendedor, em abas, usando a
  API de configurações do vendedor que já existe.
- Q: As regras de aposta da equipe do gerente (antigo modal do gerente) entram? → A: Sim, na aba
  "Regras da equipe" do modal do gerente, aplicadas de uma vez a todos os vendedores dele.
- Q: As "travas" Sim/Não do antigo continuam? → A: Viram o modal de permissões, com as permissões
  agrupadas por módulo em chaves liga/desliga, no visual da Dashboard (diferença visual aprovada).

### Session 2026-10-20 (`/speckit-clarify`)

- Q: As permissões de usuários continuam valendo para qualquer função abaixo ou são separadas por
  função, como as travas do antigo? → A: Separadas por função alvo em cadastrar, editar e excluir
  (`usuarios.cadastrar_supervisor|gerente|vendedor`, idem `editar_*` e `excluir_*`), no padrão do
  `Funcao.php`: cada função só pode usar as permissões das funções abaixo dela (`pode_usar`), mesmo
  que receba outra — o Vendedor nunca cadastra vendedor e o Gerente nunca altera gerente. Listar,
  consultar, alterar situação e gerenciar permissões continuam gerais (sempre limitados à rede).
- Q: Ao cadastrar um vendedor só com as comissões de 1, 2 e 3 jogos, quanto ele ganha de 4 a 12
  jogos e no ao vivo? → A: As faixas 4 a 12 recebem a comissão de 3 jogos, e o ao vivo recebe as
  mesmas (1, 2, 3 e a de 3 para 4 a 12).
- Q: O modal do supervisor também tem uma aba que aplica regras e comissões a todos os vendedores
  da rede dele? → A: Sim, a aba "Regras da rede", com confirmação mostrando quantos vendedores serão
  alterados antes de gravar (alcance supervisão da API existente).

## Referências do sistema antigo *(Princípios VII e VIII)*

- Telas: `resources/js/screens/adm/register/supervisor`, `register/manager` e `register/seller`
  (listas), `resources/js/screens/adm/modals/register/supervisor`, `manager` e `seller` (modais de
  cadastro e edição), `screens/adm/components/header` (menu "Usuários") e
  `screens/adm/components/components` (abas, tabela, paginação, aviso de lista vazia).
- Backend: rotas `resource usuarios` (com `funcao`, `pesquisar` e `tab=ativos|lixeira`) e
  `DELETE forcar-remocao/{id}` (apagar para sempre com senha).

**Como funcionava**:

- **Menu**: "Usuários" com Supervisores (só Admin), Gerentes e Vendedores (Admin e Supervisor) e
  Clientes; o Gerente via só "Cadastrar Vendedor"; o Vendedor não via.
- **Listas**: título "Lista das gerências/vendedores/supervisor – total N", busca, abas ativos e
  lixeira, paginação e botão "Novo ...". Colunas ID (ou PIN), Nome, Login, Comissão (vendedor
  "1/2/3 jogos"; gerente %), Vendedor (quantidade, na lista de gerentes), Gerente (na de
  supervisores), Telefone, Ativo, Data criação, Alterado em e Ação (editar, deletar; na lixeira,
  restaurar e apagar para sempre com senha).
- **Modal do vendedor**: Nome, Login, Senha, comissões 1/2/3 jogos (no cadastro), Gerente e, na
  edição, comissão por prêmio, comissão acumuladão, limites geral/duplo/simples, telefone, endereço,
  ativo, vendedor PIX e seus campos, ao vivo, créditos, segunda via, cancelar aposta, saldo de caixa,
  realizar aposta e as comissões de 1 a 12 jogos do pré-jogo e do ao vivo.
- **Modal do gerente**: dados, supervisor, comissão do gerente, "travas" Sim/Não (alterar comissão e
  limite do vendedor, cancelar aposta, cadastrar/editar/deletar vendedor, cancelar aposta iniciada,
  lançamentos, realizar aposta...) e as regras de aposta da equipe (prêmio máximo, multiplicador,
  número mínimo e máximo de jogos, valor mínimo e máximo, odd mínima, odds máximas, tempo de
  cancelamento, delay e tempo do ao vivo, data trava, período de jogos, jogadores, outros esportes,
  mensagem do bilhete...).
- **Modal do supervisor**: dados e "travas" Sim/Não (cadastrar/editar/deletar gerente e vendedor,
  editar limite e comissão, ver caixa, lançamentos, prestar contas, editar cotação, bloquear
  campeonato e confronto, editar confronto, editar/deletar bilhete, realizar aposta, subir/deletar
  créditos, editar travas).

**Problemas corrigidos**: travas Sim/Não como texto, sem ligação com o que a API realmente permite;
regras da equipe gravadas no gerente e copiadas na mão; "apagar para sempre" que apagava as apostas e
mudava o caixa; lista sem alcance no servidor (o filtro era só da tela).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Ver os usuários da minha rede (Priority: P1)

Admin, Supervisor e Gerente abrem o menu "Usuários" e veem, em listas separadas por função, só os
usuários que estão abaixo deles, com busca, paginação e as mesmas colunas do antigo.

**Why this priority**: é a base de todas as outras ações e a primeira coisa que o gestor procura.

**Independent Test**: entrar com cada nível e conferir os subitens do menu e quem aparece em cada
lista.

**Acceptance Scenarios**:

1. **Given** o Admin, **When** abre o menu, **Then** vê Usuários > Supervisores, Gerentes e
   Vendedores, e cada lista mostra todos os usuários daquela função.
2. **Given** um Supervisor, **When** abre o menu, **Then** vê Gerentes e Vendedores, só com os
   gerentes dele e os vendedores desses gerentes.
3. **Given** um Gerente, **When** abre o menu, **Then** vê só Vendedores, com os vendedores dele.
4. **Given** um Vendedor, **When** abre o painel, **Then** não vê o item "Usuários" e o endereço das
   telas o leva de volta à Dashboard.
5. **Given** a lista de vendedores, **When** abre, **Then** mostra ID, Nome, Login, Comissão
   (1/2/3 jogos), Gerente (para quem está acima do gerente), Telefone, Ativo, Data de criação,
   Alterado em e Ação, com o título "Lista de vendedores – total N".
6. **Given** a lista de gerentes, **When** abre, **Then** a coluna Vendedores mostra quantos
   vendedores ativos cada gerente tem; na de supervisores, a coluna Gerentes faz o mesmo.
7. **Given** uma busca por parte do nome ou do login, **When** confirma, **Then** a lista mostra só
   os que combinam, na rede de quem busca.
8. **Given** o celular, **When** abre uma lista, **Then** cada usuário aparece como um cartão com as
   mesmas informações e as ações.

---

### User Story 2 - Cadastrar e editar usuários (Priority: P1)

O gestor cadastra um usuário em qualquer nível abaixo do seu, escolhendo o superior quando não for o
nível imediato, e edita os dados, a senha, a situação e o superior.

**Why this priority**: é a operação diária de montar e manter a equipe.

**Independent Test**: com o Admin, cadastrar um vendedor escolhendo o gerente; com o Gerente,
cadastrar um vendedor; editar a senha e mover um vendedor de gerente.

**Acceptance Scenarios**:

1. **Given** o Admin na lista de vendedores, **When** clica em "Novo Vendedor", **Then** o modal pede
   Nome, Login, Senha, Confirme a senha, Gerente (lista de todos os gerentes), Telefone, Endereço e
   as comissões de 1, 2 e 3 jogos.
2. **Given** um Supervisor cadastrando vendedor, **When** abre o campo Gerente, **Then** só aparecem
   os gerentes dele; com um único gerente, ele já vem escolhido.
3. **Given** um Gerente cadastrando vendedor, **When** abre o modal, **Then** não há campo Gerente: o
   vendedor entra na equipe dele.
4. **Given** um Gerente, **When** tenta cadastrar um gerente ou supervisor (pela tela ou direto pela
   API), **Then** é recusado, mesmo que tenha recebido a permissão de cadastrar gerente.
5. **Given** um login já usado por outro usuário ou afiliado (inclusive excluído), **When** salva,
   **Then** aparece o erro no campo Login.
6. **Given** senha e confirmação diferentes, **When** salva, **Then** aparece "As senhas não
   conferem." sem enviar.
7. **Given** a edição de um usuário, **When** deixa a senha em branco, **Then** a senha atual é
   mantida; o Login aparece sem poder ser alterado.
8. **Given** a edição de um vendedor, **When** troca o gerente por outro da sua rede, **Then** o
   vendedor passa para a equipe do novo gerente.
9. **Given** um vendedor novo sem comissões informadas, **When** é cadastrado, **Then** recebe a
   configuração de um colega da mesma equipe (ou os valores padrão), como hoje; com as comissões
   5/7/10 informadas, fica com 5/7/10 e 10 de 4 a 12 jogos, no pré-jogo e no ao vivo.
10. **Given** um Supervisor sem a permissão de cadastrar gerente e com a de cadastrar vendedor,
    **When** abre as listas, **Then** o botão "Novo Gerente" não aparece e o "Novo Vendedor" sim.

---

### User Story 3 - Comissões e regras do vendedor e da equipe (Priority: P1)

O gestor ajusta, no modal do vendedor, as comissões do pré-jogo e do ao vivo, os limites e as regras
de aposta; no modal do gerente, aplica regras e comissões a toda a equipe de uma vez.

**Why this priority**: comissão e limites mexem no dinheiro do vendedor e da banca todos os dias.

**Independent Test**: alterar a comissão de 2 jogos de um vendedor e conferir na próxima aposta;
aplicar um prêmio máximo na aba Regras da equipe de um gerente e conferir em todos os vendedores dele.

**Acceptance Scenarios**:

1. **Given** a edição de um vendedor, **When** abre a aba Comissões, **Then** vê as comissões de 1 a
   12 jogos do pré-jogo e do ao vivo (a de 12 vale para 12 ou mais) e a comissão por prêmio, em %.
2. **Given** a aba Limites e regras, **When** abre, **Then** vê os limites geral, duplo e simples e as
   regras de aposta do vendedor, com o valor atual de cada uma.
3. **Given** uma comissão alterada e salva, **When** o vendedor faz a próxima aposta, **Then** a
   comissão dela usa o novo percentual.
4. **Given** um gestor sem a permissão de editar configurações de vendedor, **When** abre o modal,
   **Then** as abas Comissões, Limites e regras, Regras da equipe e Regras da rede ficam só para
   leitura.
5. **Given** o modal de um gerente, **When** salva um campo na aba Regras da equipe, **Then** o valor
   passa a valer para todos os vendedores daquele gerente e o aviso diz quantos foram alterados.
6. **Given** a aba Regras da equipe, **When** um campo não é alterado, **Then** ele não é enviado e
   cada vendedor mantém o valor que já tinha.
7. **Given** um valor fora do permitido (ex.: comissão acima de 100%, limite negativo), **When**
   salva, **Then** aparece o erro no campo e nada é gravado.
8. **Given** o modal de um supervisor, **When** salva um campo na aba Regras da rede, **Then** aparece
   a confirmação "Isso vai alterar N vendedores" e, ao confirmar, o valor passa a valer para todos os
   vendedores dos gerentes dele.

---

### User Story 4 - Permissões do usuário (Priority: P2)

O gestor abre o modal de permissões de um usuário da sua rede e liga ou desliga, por módulo, o que ele
pode fazer, no lugar das "travas" do antigo.

**Why this priority**: controla o que cada um faz, mas os padrões por função já atendem a operação.

**Independent Test**: tirar de um gerente a permissão de excluir usuários e conferir que o botão some
para ele e a ação é recusada.

**Acceptance Scenarios**:

1. **Given** o modal de permissões de um gerente, **When** abre, **Then** as permissões aparecem em
   grupos (Usuários, Clientes, Confrontos e cotações, Apostas, Especiais, Site e configurações,
   Pagamentos, Cassino, Afiliados, Dashboard), cada uma com nome amigável, descrição curta e chave
   liga/desliga.
2. **Given** um Supervisor editando um gerente, **When** abre o modal, **Then** só aparecem as
   permissões que o Supervisor tem e que um Gerente pode usar.
3. **Given** uma chave alterada, **When** solta o clique, **Then** a permissão é gravada na hora e
   aparece o aviso de sucesso; em erro, a chave volta e aparece a mensagem.
4. **Given** um grupo, **When** usa "Marcar todas" ou "Desmarcar todas", **Then** todas as permissões
   visíveis do grupo mudam.
5. **Given** a permissão retirada, **When** o usuário editado tenta a ação, **Then** o botão não
   aparece para ele e a ação é recusada.

---

### User Story 5 - Ativar, desativar, excluir e restaurar (Priority: P2)

O gestor desativa ou exclui um usuário (e a equipe abaixo dele) e restaura excluídos pela Lixeira.

**Why this priority**: necessário para desligar vendedores e corrigir exclusões, mas menos frequente.

**Independent Test**: excluir um gerente com vendedores, conferir a Lixeira e restaurar.

**Acceptance Scenarios**:

1. **Given** um gerente ativo com vendedores, **When** o gestor o desativa e confirma, **Then** ele e
   os vendedores dele ficam inativos e não entram mais no sistema; ao reativar, voltam juntos.
2. **Given** a confirmação de exclusão, **When** o gestor confirma, **Then** o usuário e a equipe
   abaixo dele saem da aba Ativos e aparecem na Lixeira.
3. **Given** a Lixeira, **When** o gestor clica em Restaurar e confirma, **Then** o usuário e a equipe
   excluída junto com ele voltam para Ativos.
4. **Given** um usuário excluído cujo superior também está excluído, **When** tenta restaurar só ele,
   **Then** aparece o aviso para restaurar antes o superior.
5. **Given** a Lixeira, **When** é exibida, **Then** não existe "apagar para sempre".

---

### Edge Cases

- Gerente sem vendedores: coluna Vendedores com 0; a aba Regras da equipe avisa que não há vendedores
  (o mesmo na Regras da rede de um supervisor sem vendedores na rede).
- Supervisor sem gerentes, ao cadastrar vendedor: o campo Gerente vem vazio com o aviso "Cadastre um
  gerente antes".
- Mover vendedor para gerente inativo: permitido; o vendedor só entra quando o gerente estiver ativo.
- Desativar a si mesmo ou o próprio superior: não é possível (o usuário não aparece na própria lista).
- Sessão expirada no meio da edição: leva ao login como no resto do painel.
- Permissão retirada com a tela aberta: a próxima ação é recusada e o botão some ao recarregar.
- Dois gestores editando o mesmo usuário: vale o último a salvar.
- Login com espaço, acento ou maiúsculas: recusado com a mensagem do campo (sem espaço e sem
  caractere especial); maiúsculas e minúsculas contam como o mesmo login.

## Requirements *(mandatory)*

### Functional Requirements

**Acesso e menu**

- **FR-001**: O menu lateral do painel DEVE ter o item "Usuários" com os subitens Supervisores (só
  Admin), Gerentes (Admin e Supervisor) e Vendedores (Admin, Supervisor e Gerente), exibidos a quem
  tem a permissão de listar usuários.
- **FR-002**: O Vendedor NÃO DEVE ver o item nem acessar as telas de usuários; quem tenta é levado à
  Dashboard.
- **FR-003**: Todas as listas e ações DEVEM respeitar o alcance no servidor: Admin, todos; Supervisor,
  os gerentes dele e os vendedores desses gerentes; Gerente, os vendedores dele.
- **FR-004**: Cada ação DEVE exigir a permissão correspondente (listar, consultar, cadastrar, editar,
  alterar situação, excluir, gerenciar permissões, editar configurações de vendedor); sem ela o botão
  não aparece e a ação é recusada.
- **FR-004a**: Cadastrar, editar e excluir DEVEM ter uma permissão por função alvo
  (`usuarios.cadastrar_supervisor`, `usuarios.cadastrar_gerente`, `usuarios.cadastrar_vendedor` e o
  mesmo para `editar_*` e `excluir_*`), no lugar de `usuarios.cadastrar`, `usuarios.editar` e
  `usuarios.excluir`. Cada função só pode usar as de funções abaixo dela (Admin: supervisor, gerente e
  vendedor; Supervisor: gerente e vendedor; Gerente: vendedor; Vendedor: nenhuma), mesmo que receba
  outra. Os usuários já cadastrados recebem as novas permissões equivalentes às que tinham, pelo
  seeder, e as antigas deixam de existir.

**Listas**

- **FR-005**: Cada lista DEVE ter o título "Lista de <função> – total N", busca por nome ou login,
  paginação (padrão 15, máximo 100 por página), abas Ativos e Lixeira e o botão "Novo <função>".
- **FR-006**: As colunas DEVEM ser ID, Nome, Login, Telefone, Ativo, Data de criação, Alterado em e
  Ação; Vendedores com Comissão (1/2/3 jogos do pré-jogo) e Gerente; Gerentes com Vendedores
  (quantidade de vendedores ativos) e Supervisor; Supervisores com Gerentes (quantidade). A coluna do
  superior só aparece para quem está acima dele.
- **FR-007**: As ações por linha DEVEM ser editar, permissões, ativar/desativar e excluir; na Lixeira,
  só restaurar. Ações destrutivas pedem confirmação.
- **FR-008**: No celular, as listas DEVEM virar cartões com as mesmas informações e ações.

**Cadastro e edição**

- **FR-009**: O cadastro DEVE aceitar qualquer função abaixo da de quem cadastra (Admin: supervisor,
  gerente e vendedor; Supervisor: gerente e vendedor; Gerente: vendedor). Quando a função não é a
  imediatamente abaixo, quem cadastra DEVE escolher o superior, que precisa ser da função logo acima
  e estar na sua rede; o Gerente cadastra vendedor sempre na equipe dele.
- **FR-010**: O modal DEVE ter Nome, Login, Senha, Confirme a senha, Superior (quando aplicável),
  Telefone com máscara, Endereço e, na edição, Ativo. O login é único (sem diferenciar maiúsculas,
  incluindo excluídos e afiliados), sem espaço, e não pode ser alterado depois.
- **FR-011**: Na edição, senha em branco DEVE manter a atual; a troca de superior DEVE aceitar só
  superior da função certa e da rede de quem edita.
- **FR-012**: O cadastro de vendedor DEVE mostrar as comissões de 1, 2 e 3 jogos. Quando informadas,
  as faixas 4 a 12 recebem a comissão de 3 jogos e o ao vivo recebe as mesmas comissões do pré-jogo
  (1, 2, 3 e a de 3 para 4 a 12). Quando não informadas, e para as demais configurações, vale a
  cópia de um colega da equipe ou os valores padrão, como hoje.
- **FR-013**: O modal do vendedor DEVE ter as abas Dados, Comissões, Limites e regras e Permissões.
  Comissões: 1 a 12 jogos do pré-jogo e do ao vivo e comissão por prêmio. Limites e regras: limites
  geral, duplo e simples; realizar aposta; cancelar aposta e tempo de cancelamento; ao vivo, minuto
  limite e cotação máxima do ao vivo; delay do ao vivo; valor mínimo e máximo; odd mínima; prêmio
  máximo; multiplicador; quantidade mínima e máxima de opções; ganho por múltiplos palpites; período
  de jogos; data de travamento; apostar em jogadores; esportes permitidos e outros esportes; Criar
  Aposta (ligado, desconto e quantidades); mensagem do bilhete.
- **FR-014**: O modal do gerente DEVE ter a aba "Regras da equipe" com os mesmos campos de Comissões e
  de Limites e regras; só os campos alterados são gravados, em todos os vendedores daquele gerente, e
  o aviso informa quantos vendedores foram alterados.
- **FR-014a**: O modal do supervisor DEVE ter a aba "Regras da rede", com os mesmos campos da Regras
  da equipe, aplicados a todos os vendedores de todos os gerentes daquele supervisor. Antes de gravar,
  DEVE pedir confirmação mostrando quantos vendedores serão alterados; só os campos alterados são
  gravados.
- **FR-015**: Sem a permissão de editar configurações de vendedor, as abas de Comissões, Limites e
  regras, Regras da equipe e Regras da rede DEVEM ficar só para leitura.
- **FR-016**: Os modais DEVEM mostrar erro por campo, carregando no botão de salvar e fechar com Esc,
  clique no fundo e botão voltar do aparelho.

**Permissões**

- **FR-017**: O modal de permissões DEVE mostrar as permissões agrupadas por módulo, com nome
  amigável, descrição curta e chave liga/desliga, e os botões "Marcar todas" e "Desmarcar todas" por
  grupo.
- **FR-018**: Só DEVEM aparecer as permissões que quem edita possui e que a função do usuário editado
  pode usar.
- **FR-019**: Cada mudança DEVE ser gravada na hora, com aviso de sucesso; em erro, a chave volta ao
  estado anterior com a mensagem do servidor.
- **FR-020**: As "travas" do antigo DEVEM corresponder às permissões do novo (tabela em Assumptions);
  as travas sem recurso no novo não aparecem.

**Situação, exclusão e Lixeira**

- **FR-021**: Ativar e desativar DEVEM valer em cascata para a equipe abaixo, com confirmação.
- **FR-022**: Excluir DEVE ser lógico e em cascata, com confirmação; o usuário e a equipe vão para a
  Lixeira e as apostas continuam no histórico.
- **FR-023**: A Lixeira DEVE listar os excluídos do alcance de quem vê, com busca e paginação, e o
  botão Restaurar, que devolve o usuário e a equipe excluída junto com ele; não é possível restaurar
  um usuário cujo superior continua excluído.
- **FR-024**: NÃO DEVE existir exclusão definitiva ("apagar para sempre").

**Visual**

- **FR-025**: As telas DEVEM seguir o visual do painel aprovado na Dashboard (barra, menu lateral,
  seções, cartões, modais, temas claro e escuro), mantendo nomes, colunas e posição das ações do
  antigo, com prioridade para o celular.

### Key Entities

- **Usuário do painel**: nome, login, senha, telefone, endereço, situação (ativo), função
  (Supervisor, Gerente ou Vendedor) e superior; pode estar excluído (Lixeira).
- **Configuração do vendedor**: comissões por quantidade de jogos (pré-jogo e ao vivo), comissão por
  prêmio, limites e regras de aposta; uma por vendedor.
- **Permissão**: ação que o usuário pode fazer; concedida por um superior que também a possui e
  limitada pelo que a função pode usar.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Um gestor cadastra um vendedor completo (dados e comissões 1/2/3) em até 1 minuto.
- **SC-002**: Em 100% das conferências, cada nível vê e altera só usuários da sua rede.
- **SC-003**: Uma alteração de regra na aba Regras da equipe chega a todos os vendedores do gerente
  em uma única ação.
- **SC-004**: Um gestor do sistema antigo encontra cada informação das telas antigas (colunas, campos
  e travas equivalentes) sem ajuda, nos mesmos lugares.
- **SC-005**: As listas abrem em até 2 segundos com 500 usuários na rede.
- **SC-006**: Uma exclusão feita por engano é desfeita pela Lixeira sem perder apostas nem a equipe.

## Assumptions

- O backend da spec 001 (usuários e permissões) e das specs 003/004 (configurações do vendedor, com
  alcance supervisão, gerente e vendedor) é reutilizado; esta spec acrescenta o cadastro em qualquer
  nível abaixo, a Lixeira (listagem de excluídos e restauração) e as colunas de contagem e de
  comissão nas listas.
- Equivalência das travas do antigo com as permissões do novo:

  | Trava do antigo | Permissão no novo |
  |---|---|
  | Cadastrar / Editar / Deletar gerente | usuarios.cadastrar_gerente / usuarios.editar_gerente / usuarios.excluir_gerente |
  | Cadastrar / Editar / Deletar vendedor | usuarios.cadastrar_vendedor / usuarios.editar_vendedor / usuarios.excluir_vendedor |
  | Editar travas | usuarios.gerenciar_permissoes |
  | Editar limite / Editar comissão / Alterar comissão e limite do vendedor | usuarios_configuracoes.editar |
  | Cancelar aposta / Deletar bilhete | apostas.cancelar |
  | Cancelar aposta iniciada | apostas.cancelar_iniciada |
  | Editar bilhete | apostas.editar |
  | Editar cotação | porcentagens_vendedores/campeonatos/confrontos.editar |
  | Bloquear campeonato / Bloquear confronto | campeonatos_nao_permitidos / confrontos_nao_permitidos.gerenciar |
  | Editar confronto | confrontos.editar |
  | Subir / Deletar créditos | clientes.movimentar_saldo |
  | Cadastrar cliente | clientes.editar (cadastro de clientes na spec própria) |
  | Ver caixa, Lançamentos, Prestar contas, Tipo caixa | sem equivalente (spec de caixa) |
  | Função admin, PIN, Vendedor PIX, Créditos, Acumuladão, Segunda via, QR Code, Compartilhar | sem recurso no novo |

- A quantidade nas colunas Vendedores e Gerentes conta só os ativos e não excluídos.
- Os nomes amigáveis e descrições das permissões ficam no frontend, num lugar único.
- A cotação do vendedor (porcentagens de cotação) continua na tela própria, fora desta spec.
