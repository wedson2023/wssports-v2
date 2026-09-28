# Feature Specification: Gerenciamento de Usuários

**Feature Branch**: `001-user_management`

**Created**: 2026-09-28

**Status**: Draft

**Input**: User description: "- Criação rotas resouces em routes/api.php para gerenciamento de usuários.
- Controller, model, migrations, seed, factory para gerenciamento de usuários.
- O sistema possui usuários organizados em hierarquia: Admin > Supervisor > Gerente > Vendedor.
- Cada usuário (exceto o Admin raiz) pertence a um usuário superior.
- Um usuário possui: nome, função, login único, senha, telefone, endereço, ativo, created_at,
  updated_at, deleted_at.
- respeitando a hierarquia: cada usuário só gerencia os usuários abaixo dele.
- Usuários desativados ou excluídos não podem acessar o sistema.
- Autenticação/login fica fora do escopo desta spec."

## Clarifications

### Session 2026-09-28

- Q: Como o sistema identifica quem faz cada requisição, para aplicar as regras de hierarquia?
  → A: Por autenticação JWT implementada nesta spec: rota de login que gera o token, e as rotas
  de usuários exigem o token; o login deixa de estar fora do escopo (substitui a orientação
  inicial de acessar as rotas diretamente).
- Q: Por quanto tempo o token vale, e haverá renovação e logout? → A: O token vale 60 minutos,
  com rota de renovação (novo token sem digitar a senha) e rota de logout que invalida o token
  na hora.
- Q: O sistema deve limitar tentativas de login erradas? → A: Sim; após 5 tentativas erradas
  em 1 minuto (mesmo login + mesmo IP), novas tentativas são recusadas por 1 minuto.
- Q: Como será feito o controle de permissões? → A: Com o pacote `spatie/laravel-permission`
  (papéis e permissões), e não com as Policies padrão do Laravel.
- Q: A função do usuário fica só como papel do spatie ou também numa coluna `funcao`? → A: Só
  como papel do spatie; não existe coluna `funcao`, e cada usuário tem um único papel.
- Q: As permissões de cada papel serão fixas ou alteráveis pelo sistema? → A: Além das
  permissões do papel (criadas pelo seeder), o gestor pode dar ou tirar permissões específicas
  de um subordinado.
- Q: Um gestor pode dar a um subordinado uma permissão que ele mesmo não tem? → A: Não; só
  pode dar permissões que ele mesmo tem (efetivas), e pode tirar qualquer permissão do
  subordinado.
- Q: Como o sistema tira de um usuário uma permissão que ele recebeu por padrão? → A: Como
  no outro sistema do responsável: as permissões ficam diretamente no usuário (copiadas do
  padrão da função no cadastro) e tirar é apagar o vínculo usuário–permissão; o papel serve
  apenas para identificar a função e não concede permissões.
- Q: As tabelas do `spatie/laravel-permission` seguem a regra de nomes em português? → A: Não;
  por exceção pedida pelo responsável, mantêm os nomes padrão do pacote em inglês (`roles`,
  `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`).

## User Scenarios & Testing *(mandatory)*

> Conforme a constituição (v1.4.0), o projeto não terá testes automatizados. Os cenários
> abaixo são critérios de aceite validados manualmente.

### User Story 1 - Autenticar com login e senha (Priority: P1)

Um usuário informa login e senha e recebe um token de acesso (JWT). Esse token identifica quem
faz cada requisição às rotas de gestão de usuários, e é a partir dele que as regras de
hierarquia são aplicadas.

**Why this priority**: sem identificar quem faz a requisição, nenhuma regra de hierarquia pode
ser aplicada; é pré-requisito para todas as outras stories.

**Independent Test**: validar manualmente fazendo login com o Admin do seeder, recebendo o
token e usando-o para listar usuários; sem token, a listagem é recusada.

**Acceptance Scenarios**:

1. **Given** um usuário ativo, **When** ele informa login e senha corretos, **Then** recebe um
   token de acesso.
2. **Given** login ou senha incorretos, **When** o usuário tenta se autenticar, **Then** a
   autenticação é recusada com mensagem genérica, sem indicar qual dos dois está errado.
3. **Given** um usuário inativo ou excluído, **When** ele tenta se autenticar com a senha
   correta, **Then** a autenticação é recusada.
4. **Given** uma requisição às rotas de usuários sem token, com token inválido ou expirado,
   **When** ela é feita, **Then** é recusada como não autenticada.
5. **Given** um token válido, **When** o usuário pede a renovação, **Then** recebe um novo token
   válido por mais 60 minutos, sem informar a senha.
6. **Given** um token válido, **When** o usuário faz logout, **Then** esse token deixa de ser
   aceito imediatamente.
7. **Given** 5 tentativas de login erradas em 1 minuto para o mesmo login e IP, **When** uma
   nova tentativa é feita, **Then** ela é recusada por 1 minuto com mensagem informando o tempo
   de espera, mesmo que a senha esteja correta.

---

### User Story 2 - Cadastrar usuário subordinado (Priority: P1)

Um usuário com função de gestão (Admin, Supervisor ou Gerente) cadastra um novo usuário com a
função imediatamente abaixo da sua (Admin → Supervisor, Supervisor → Gerente, Gerente →
Vendedor), informando nome, login, senha, telefone e endereço. O novo usuário fica vinculado a
quem o cadastrou, que passa a ser o seu superior.

**Why this priority**: sem cadastro não existe hierarquia nem operação de vendas; é a base
para todas as outras funcionalidades.

**Independent Test**: validar manualmente cadastrando um Supervisor com o Admin, um Gerente
com o Supervisor e um Vendedor com o Gerente, e conferir que cada um ficou vinculado a quem o
cadastrou.

**Acceptance Scenarios**:

1. **Given** um Gerente ativo, **When** ele cadastra um Vendedor com dados válidos,
   **Then** o Vendedor é criado ativo e vinculado a esse Gerente.
2. **Given** um login já utilizado por outro usuário (inclusive um usuário excluído),
   **When** alguém tenta cadastrar um novo usuário com o mesmo login, **Then** o cadastro é
   recusado com mensagem informando que o login já existe.
3. **Given** um Gerente, **When** ele tenta cadastrar um Supervisor ou um Admin, **Then** o
   cadastro é recusado.
4. **Given** o Admin, **When** ele tenta cadastrar um Gerente ou um Vendedor, **Then** o
   cadastro é recusado, pois cada gestor só cadastra a função imediatamente abaixo da sua.
5. **Given** um Vendedor, **When** ele tenta cadastrar qualquer usuário, **Then** a operação é
   recusada, pois o Vendedor não possui subordinados.

---

### User Story 3 - Listar e consultar usuários da própria hierarquia (Priority: P1)

Um usuário de gestão lista e consulta os usuários abaixo dele na hierarquia, em qualquer
nível, podendo filtrar por função e situação (ativo/inativo) e buscar por nome ou login.

**Why this priority**: gestores precisam enxergar sua equipe para acompanhar a operação; é
pré-requisito para editar e desativar.

**Independent Test**: com uma hierarquia de exemplo, conferir que o Gerente vê só os seus
Vendedores, o Supervisor vê seus Gerentes e os Vendedores deles, e o Admin vê todos os outros
usuários.

**Acceptance Scenarios**:

1. **Given** um Supervisor com Gerentes e Vendedores abaixo dele, **When** ele lista os
   usuários, **Then** vê toda a sua sub-hierarquia e nenhum usuário de outro Supervisor.
2. **Given** qualquer listagem ou consulta, **When** ela é exibida, **Then** a senha de nenhum
   usuário é retornada.
3. **Given** usuários excluídos logicamente, **When** a listagem é consultada, **Then** eles não
   aparecem.
4. **Given** um usuário fora da sub-hierarquia de quem consulta (ou o próprio consultante),
   **When** ele é consultado individualmente, **Then** a resposta é "não encontrado".

---

### User Story 4 - Editar dados de um usuário subordinado (Priority: P2)

Um usuário de gestão altera os dados de um usuário abaixo dele: nome, senha, telefone,
endereço e, quando permitido, o superior ao qual ele está vinculado.

**Why this priority**: dados cadastrais mudam com frequência, mas o sistema já entrega valor
apenas com cadastro e listagem.

**Independent Test**: um Gerente altera o telefone de um de seus Vendedores e a alteração
aparece na consulta; ao tentar alterar um Vendedor de outro Gerente, a operação é recusada.

**Acceptance Scenarios**:

1. **Given** um Gerente e um Vendedor dele, **When** o Gerente altera o telefone do Vendedor,
   **Then** o novo telefone é salvo.
2. **Given** um Gerente, **When** ele tenta editar um Vendedor de outro Gerente, **Then** a
   operação é recusada com "não encontrado".
3. **Given** uma edição sem informar nova senha, **When** ela é salva, **Then** a senha atual
   permanece inalterada.
4. **Given** uma edição, **When** ela tenta alterar a função ou o login, **Then** a operação é
   recusada, pois esses campos não mudam após o cadastro.
5. **Given** um Supervisor, **When** ele move um Vendedor para outro Gerente da sua equipe,
   **Then** o Vendedor passa a ficar vinculado ao novo Gerente.

---

### User Story 5 - Desativar, reativar e excluir usuários (Priority: P2)

Um usuário de gestão desativa temporariamente um subordinado (podendo reativá-lo depois) ou o
exclui logicamente. A ação vale para o usuário e para toda a equipe abaixo dele. Usuários
desativados ou excluídos ficam impedidos de acessar o sistema, mas seus registros são
preservados.

**Why this priority**: controlar quem pode operar é essencial em um sistema que movimenta
dinheiro, mas depende de o cadastro já existir.

**Independent Test**: desativar um Gerente e conferir que ele e seus Vendedores ficam
inativos; reativá-lo e conferir que todos voltam a ficar ativos; excluí-lo e conferir que todos
somem da listagem, mas continuam registrados.

**Acceptance Scenarios**:

1. **Given** um Gerente ativo com Vendedores, **When** seu Supervisor o desativa, **Then** o
   Gerente e todos os seus Vendedores passam a constar como inativos e sem acesso.
2. **Given** um Gerente inativo com Vendedores inativos, **When** seu Supervisor o reativa,
   **Then** o Gerente e todos os seus Vendedores voltam a ficar ativos.
3. **Given** um Gerente com Vendedores, **When** seu Supervisor o exclui, **Then** o Gerente e
   todos os seus Vendedores são marcados como excluídos, deixam de aparecer na listagem e seus
   registros são mantidos.
4. **Given** um usuário desativado ou excluído, **When** ele tenta gerenciar outros usuários,
   **Then** a operação é recusada por falta de permissão.

---

### User Story 6 - Ajustar permissões de um subordinado (Priority: P3)

Um usuário de gestão consulta as permissões de um subordinado e pode dar ou tirar permissões
dele, a partir das que ele recebeu por padrão no cadastro.

**Why this priority**: permite ajustes finos (por exemplo, impedir um Gerente de excluir
usuários), mas o sistema funciona com as permissões padrão de cada função.

**Independent Test**: tirar de um Gerente a permissão de excluir usuários e conferir que a
exclusão passa a ser recusada para ele; devolver a permissão e conferir que volta a funcionar.

**Acceptance Scenarios**:

1. **Given** um Supervisor e um Gerente da sua equipe, **When** o Supervisor consulta as
   permissões do Gerente, **Then** vê a lista de permissões que o Gerente tem.
2. **Given** um Gerente com a permissão de excluir usuários, **When** o Supervisor
   retira essa permissão dele, **Then** o Gerente passa a ter a exclusão recusada por falta de
   permissão.
3. **Given** um usuário fora da equipe de quem solicita, **When** alguém tenta alterar as
   permissões dele, **Then** a operação é recusada com "não encontrado".
4. **Given** um Gerente sem a permissão de excluir usuários, **When** ele tenta dar essa
   permissão a um Vendedor seu, **Then** a operação é recusada, pois ele só pode dar permissões
   que ele mesmo tem.

---

### Edge Cases

- O Admin raiz não está abaixo de ninguém; portanto não pode ser consultado, editado,
  desativado nem excluído pela gestão de usuários.
- Nenhum usuário pode consultar, editar, desativar ou excluir o próprio registro pela gestão de
  usuários (resposta "não encontrado").
- Alterar o superior de um usuário não pode criar ciclos: o novo superior precisa ter a função
  imediatamente acima e estar na equipe de quem faz a alteração (ou ser ele mesmo).
- Um login pertencente a um usuário excluído não pode ser reutilizado.
- Logins são comparados sem diferenciar maiúsculas e minúsculas e sem espaços nas pontas
  (`Admw` e ` admw ` são o mesmo login).
- Operação sobre usuário inexistente ou excluído retorna "não encontrado", sem revelar dados.

## Requirements *(mandatory)*

### Functional Requirements

**Hierarquia**

- **FR-001**: O sistema DEVE suportar exatamente quatro funções, em ordem hierárquica:
  Admin > Supervisor > Gerente > Vendedor.
- **FR-002**: Todo usuário, exceto o Admin raiz, DEVE estar vinculado a exatamente um usuário
  superior.
- **FR-003**: O superior de um usuário DEVE ter a função imediatamente acima da dele
  (hierarquia estrita): Vendedor somente abaixo de Gerente, Gerente somente abaixo de
  Supervisor e Supervisor somente abaixo de Admin.
- **FR-004**: Um usuário DEVE poder gerenciar (consultar, editar, desativar, reativar,
  excluir) somente os usuários abaixo dele na hierarquia, em qualquer nível. O Admin raiz
  gerencia todos os demais usuários.
- **FR-005**: Um usuário DEVE poder cadastrar somente usuários com a função imediatamente
  abaixo da sua, e o novo usuário DEVE ficar vinculado a quem o cadastrou. O superior não é
  informado no cadastro.
- **FR-006**: A alteração de superior NÃO DEVE gerar ciclos na hierarquia.

**Dados do usuário**

- **FR-007**: Cada usuário DEVE possuir: nome, função, login, senha, telefone, endereço,
  indicação de ativo, superior e as datas `created_at`, `updated_at` e `deleted_at`. A função
  é o papel (role) do `spatie/laravel-permission` atribuído ao usuário; NÃO existe coluna
  `funcao` na tabela de usuários.
- **FR-008**: Nome, função, login e senha DEVEM ser obrigatórios; telefone e endereço DEVEM
  ser opcionais.
- **FR-009**: O login DEVE ser único entre todos os usuários, incluindo os excluídos, sem
  diferenciar maiúsculas/minúsculas e ignorando espaços nas pontas.
- **FR-010**: A senha DEVE ser armazenada de forma irreversível e NUNCA DEVE ser retornada em
  consultas ou listagens.
- **FR-011**: Senhas DEVEM ter no mínimo 6 caracteres.
- **FR-012**: Novos usuários DEVEM ser criados como ativos.
- **FR-013**: Função e login NÃO DEVEM ser alterados após o cadastro.

**Operações**

- **FR-014**: O sistema DEVE permitir listar os usuários da sub-hierarquia de quem solicita,
  com filtros por função e situação e busca por nome ou login, em páginas.
- **FR-015**: A listagem NÃO DEVE incluir usuários excluídos.
- **FR-016**: O sistema DEVE permitir consultar individualmente um usuário da sub-hierarquia.
- **FR-017**: O sistema DEVE permitir editar os dados de um usuário subordinado; campos não
  informados (especialmente a senha) DEVEM permanecer inalterados.
- **FR-018**: Ao desativar um usuário, toda a sua sub-hierarquia DEVE ser desativada; ao
  reativar, toda a sua sub-hierarquia DEVE ser reativada.
- **FR-019**: A exclusão DEVE ser sempre lógica (preenchendo `deleted_at`) e DEVE ser aplicada
  ao usuário e a toda a sua sub-hierarquia, preservando os registros.
- **FR-020**: O sistema DEVE disponibilizar uma verificação única de "usuário pode acessar o
  sistema", que retorna negativo para usuários inativos ou excluídos, para uso pela futura
  autenticação.
- **FR-021**: Usuários inativos ou excluídos NÃO DEVEM conseguir executar nenhuma operação de
  gestão de usuários.
- **FR-022**: Nenhum usuário DEVE poder consultar, editar, desativar ou excluir o próprio
  registro pela gestão de usuários; a tentativa DEVE retornar "não encontrado".
- **FR-023**: Operações recusadas DEVEM retornar mensagens claras em português indicando o
  motivo (login duplicado, sem permissão, não encontrado, dado inválido).

**Autenticação**

- **FR-027**: O sistema DEVE permitir que um usuário se autentique com login e senha e receba
  um token de acesso JWT válido por 60 minutos.
- **FR-028**: A autenticação DEVE ser recusada para login/senha incorretos (com mensagem
  genérica) e para usuários que não passem na verificação de "pode acessar" (FR-020).
- **FR-029**: Todas as rotas de gestão de usuários DEVEM exigir um token válido; requisições
  sem token, com token inválido ou expirado DEVEM ser recusadas como não autenticadas.
- **FR-030**: O usuário identificado pelo token é quem solicita a operação, e é sobre ele que as
  regras de hierarquia (FR-004, FR-005, FR-021, FR-022) são aplicadas.
- **FR-031**: O sistema DEVE permitir renovar um token válido, gerando um novo token de 60
  minutos sem exigir a senha; a renovação DEVE ser recusada para usuários que não passem na
  verificação de "pode acessar" (FR-020).
- **FR-032**: O sistema DEVE permitir o logout, que invalida o token usado imediatamente; um
  token invalidado NÃO DEVE ser aceito em nenhuma rota.
- **FR-033**: Após 5 tentativas de login erradas em 1 minuto para o mesmo login e IP, o sistema
  DEVE recusar novas tentativas desse par por 1 minuto, informando o tempo de espera.

**Entregáveis técnicos solicitados**

- **FR-024**: As rotas de gerenciamento de usuários DEVEM ser criadas como rotas *resource* em
  `routes/api.php` (listar, cadastrar, consultar, editar e excluir).
- **FR-025**: A feature DEVE entregar controller, model, migration, seeder e factory de
  usuários. A migration DEVE criar `created_at`, `updated_at` e `deleted_at` (timestamps e
  soft delete), conforme a constituição.
- **FR-026**: O seeder DEVE criar o Admin raiz e uma hierarquia de exemplo (Supervisor,
  Gerente e Vendedores) para validação manual.
- **FR-034**: O controle de permissões DEVE usar o pacote `spatie/laravel-permission` (papéis e
  permissões), e NÃO as Policies padrão do Laravel. As quatro funções (Admin, Supervisor,
  Gerente e Vendedor) DEVEM existir como papéis do pacote e cada usuário DEVE ter exatamente um
  papel, que identifica a sua função e NÃO concede permissões. As ações de gestão de usuários
  (listar, consultar, cadastrar, editar, desativar/reativar, excluir e gerenciar permissões)
  DEVEM ser permissões atribuídas diretamente a cada usuário. O seeder DEVE criar os papéis e as
  permissões.
- **FR-035**: A regra de que cada usuário só gerencia a própria sub-hierarquia (FR-004, FR-022)
  continua valendo além das permissões: ter a permissão de uma ação não dá acesso a usuários
  fora da equipe de quem solicita.
- **FR-036**: As permissões de um usuário DEVEM ser exatamente as atribuídas diretamente a ele.
  No cadastro, o novo usuário DEVE receber as permissões padrão da sua função que o cadastrante
  também possui (o que garante FR-038 já no cadastro).
- **FR-037**: O sistema DEVE permitir que um gestor consulte as permissões de um usuário da sua
  sub-hierarquia e dê ou tire permissões dele; tirar uma permissão DEVE apenas remover o vínculo
  entre o usuário e a permissão. A operação segue as mesmas regras de sub-hierarquia (FR-004, FR-022).
- **FR-038**: Um gestor SÓ DEVE poder dar a um subordinado permissões que ele mesmo tem
  (conforme FR-036); ele PODE tirar qualquer permissão do subordinado.

### Key Entities

- **Usuário**: pessoa que opera o sistema de apostas. Atributos: nome, função, login, senha,
  telefone, endereço, ativo, `created_at`, `updated_at` e `deleted_at`. Cada usuário pertence a
  um superior (exceto o Admin raiz) e pode ter vários subordinados.
- **Função**: nível hierárquico do usuário (Admin, Supervisor, Gerente ou Vendedor),
  representado como papel (role) do `spatie/laravel-permission`, um por usuário. Define o que o
  usuário pode cadastrar, quais permissões ele tem e qual função o seu superior deve ter.
- **Hierarquia**: árvore formada pelos vínculos usuário → superior, com o Admin raiz no topo. A
  sub-hierarquia de um usuário é o conjunto de todos os usuários abaixo dele, em qualquer nível.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Em 100% das tentativas, um usuário não consegue consultar, editar, desativar ou
  excluir usuários fora da sua sub-hierarquia.
- **SC-002**: Em 100% das verificações de acesso, usuários inativos ou excluídos são
  identificados como sem permissão.
- **SC-003**: Nenhuma consulta ou listagem expõe a senha de qualquer usuário.
- **SC-004**: Desativar, reativar ou excluir um gestor atinge 100% da sua equipe abaixo, sem
  deixar nenhum subordinado em situação diferente.
- **SC-005**: A listagem de um gestor com até 1.000 subordinados é exibida em menos de 2
  segundos.

## Assumptions

- Existe um único Admin raiz, criado pelo seeder; ninguém pode cadastrar outro Admin.
- A autenticação por login e senha com token JWT faz parte desta spec (FR-027 a FR-030).
  Recuperação de senha, cadastro público e troca de senha pelo próprio usuário continuam fora do
  escopo.
- Telas (frontend) estão fora do escopo; esta spec cobre somente o backend.
- A estrutura atual de usuários do projeto (baseada em e-mail) será substituída por esta, pois
  os usuários são identificados por login.
- Não há restauração de usuários excluídos nesta spec.
- Permissões padrão por função (confirmadas pelo responsável): Admin, Supervisor e Gerente
  recebem todas as permissões de gestão de usuários; o Vendedor não recebe nenhuma, pois não
  possui subordinados.
- Alterar a lista padrão de uma função depois não muda as permissões de quem já foi
  cadastrado; vale apenas para novos cadastros.
- Papéis e a lista de permissões existentes são criados pelo seeder; criar papéis ou
  permissões novas pelo sistema está fora do escopo.
- As regras de cascata, hierarquia estrita, cadastro somente do nível imediatamente abaixo e
  bloqueio do próprio registro seguem as decisões tomadas pelo responsável na versão anterior
  desta spec.
