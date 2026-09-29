<!--
Sync Impact Report
- Version change: 1.11.0 → 1.12.0 (MINOR: nova exceção para colunas exigidas pela autenticação)
- Princípios modificados:
  - I. Nomenclatura em snake_case: `password` e `remember_token` mantêm o nome em inglês nas
    tabelas de quem se autentica (guard do Laravel e `jwt-auth`); regulariza `usuarios` (spec 001)
    e `clientes` (spec 002)
- Princípios adicionados: nenhum
- Seções alteradas: nenhuma
- Seções adicionadas: nenhuma
- Seções removidas: nenhuma
- TODOs pendentes: nenhum
-->

# WSSports Constitution

## Core Principles

### I. Nomenclatura em snake_case

- Variáveis, funções, métodos, propriedades, chaves de arrays/objetos, parâmetros e nomes
  usados nas instruções (specs, planos, tarefas) DEVEM usar `snake_case`. `camelCase` é
  proibido nesses casos, tanto no PHP quanto no JavaScript/React.
- **Exceção — métodos do framework e de pacotes**: métodos sobrescritos ou exigidos pelo
  framework (Laravel) ou por pacotes de terceiros mantêm o nome original (ex.: `casts`,
  `rules`, `messages`, `authorize`, `prepareForValidation`, `toArray`, `definition`,
  `viewAny`, `view`, `create`, `update`, `delete`, `before`, `up`, `down`, e
  `getJWTIdentifier`/`getJWTCustomClaims` do `jwt-auth`). Todos os demais métodos DEVEM usar
  `snake_case`.
- Nomes de classes PHP e de componentes React continuam em `PascalCase`, pois são o nome do
  tipo/componente e definem o nome da pasta do componente (ver Princípio III).
- Toda nomenclatura de banco de dados (migrations, tabelas, colunas, índices, chaves
  estrangeiras, seeders e factories que as referenciem) DEVE ser escrita em **português**
  (ex.: tabela `apostas`, já created_at, updated_at, deleted_at são em inglês).
- **Exceção — tabelas de pacotes de terceiros**: tabelas criadas pela migration de um pacote de
  terceiros (ex.: `spatie/laravel-permission` — `roles`, `permissions`, `model_has_roles`,
  `model_has_permissions`, `role_has_permissions`) mantêm os nomes padrão do pacote em inglês
  (tabelas e colunas) e NÃO precisam ter `deleted_at`. Tabelas criadas pelo próprio projeto
  continuam em português, com `created_at`, `updated_at` e `deleted_at`.
- **Exceção — colunas exigidas pela autenticação do framework**: as colunas `password` e
  `remember_token` mantêm o nome em inglês nas tabelas de quem se autentica (ex.: `usuarios`,
  `clientes`), pois o guard do Laravel e o `jwt-auth` dependem desses nomes. As demais colunas
  dessas tabelas continuam em português.
- **Prefixo de tabelas relacionadas**: tabelas ligadas a uma tabela principal DEVEM usar o nome
  dela como prefixo, seguido do complemento (ex.: `clientes` → `clientes_transacoes`,
  `clientes_configuracoes`), para ficarem listadas juntas no banco.
- **Nomes de permissões**: toda permissão DEVE seguir o padrão `<recurso>.<acao>`, em que
  `recurso` é o nome da tabela principal a que a ação se refere e `acao` é um verbo ou expressão
  em `snake_case` (ex.: `usuarios.listar`, `clientes.excluir`, `clientes.movimentar_saldo`,
  `clientes_promocoes.gerenciar`).
- Nomes de pastas DEVEM ser escritos em **inglês** e `snake_case` (ex.: `components`,
  `services`, `pages`, `user_roles`).
- **Exceção — pastas PSR-4**: pastas dentro de `app/` que correspondem a namespaces PSR-4
  (ex.: `app/Enums`, `app/Policies`, `app/Http/Requests`, `app/Http/Resources`) usam
  `PascalCase`, igual a `app/Http` e `app/Models`. As demais pastas continuam em inglês e
  `snake_case`.

**Rationale**: um único padrão de nomes elimina a ambiguidade entre backend e frontend; o
banco em português reflete o domínio do negócio, e as pastas em inglês seguem a convenção do
ecossistema Laravel/React. As exceções existem porque o autoload PSR-4 liga a pasta ao
namespace e o Laravel e os pacotes só reconhecem seus métodos pelo nome original; renomeá-los
quebraria o funcionamento. Tabelas de pacotes seguem o padrão do pacote para manter
compatibilidade com a sua documentação e com outros sistemas que usam o mesmo pacote.

### II. Idioma por Contexto

- **Backend (PHP/Laravel)**: specs, planos, tarefas e demais instruções DEVEM ser escritos em
  **português**. Comentários no código DEVEM estar em **português**.
- **Frontend (React/JavaScript)**: specs, planos, tarefas e demais instruções DEVEM ser
  escritos em **inglês**. Comentários no código DEVEM estar em **português**.
- Artefatos que abrangem backend e frontend DEVEM separar as partes por contexto, aplicando o
  idioma correspondente a cada uma.

**Rationale**: define de forma verificável o idioma de cada artefato e mantém os comentários
de código em um único idioma para toda a equipe.

### III. Estrutura de Componentes React

- Cada componente React DEVE ter sua própria pasta, nomeada exatamente com o nome do
  componente (ex.: `components/user_roles/`).
- A pasta do componente DEVE conter os arquivos:
  - `index.jsx`: lógica e marcação do componente;
  - `styles.jsx`: estilos do componente.
- Não é permitido declarar um componente em arquivo solto fora dessa estrutura.

**Rationale**: uma estrutura previsível facilita localizar, revisar e reutilizar componentes,
mantendo estilos isolados junto ao componente que os utiliza.

### IV. Escopo Estrito de Edição (INEGOCIÁVEL)

- Uma alteração DEVE se limitar exatamente ao trecho de código solicitado. É proibido
  refatorar, reformatar, renomear ou "melhorar" código fora do escopo pedido.
- Se for necessário alterar outro trecho para a tarefa funcionar, isso DEVE ser comunicado
  antes, informando o arquivo, o trecho e o motivo, e a alteração só pode ser feita após a
  confirmação.

**Rationale**: alterações fora do escopo geram regressões inesperadas e diffs difíceis de
revisar; a confirmação prévia mantém o controle do código com o responsável pelo projeto.

### V. Revisão de Legibilidade Pós-Implementação

- Ao concluir a implementação de uma tarefa, TODO o código alterado nela DEVE ser analisado em
  conjunto (não apenas trecho a trecho), buscando oportunidades de refatoração que melhorem a
  legibilidade.
- A análise DEVE verificar, no mínimo: nomes claros e aderentes ao Princípio I, duplicação de
  código, funções/métodos longos ou com múltiplas responsabilidades, condicionais aninhadas
  que possam ser simplificadas e comentários desatualizados ou desnecessários.
- As refatorações DEVEM preservar o comportamento existente e ficar restritas ao código
  alterado na tarefa. Melhorias identificadas em código fora desse escopo DEVEM seguir o
  Princípio IV (comunicar antes e aguardar confirmação).
- A tarefa só é considerada concluída após essa revisão; se nenhuma refatoração for
  necessária, isso DEVE ser informado explicitamente.

**Rationale**: revisar o conjunto das alterações ao final revela problemas de legibilidade que
não aparecem durante a implementação incremental, mantendo o código fácil de ler e manter sem
violar o escopo estrito de edição.

## Stack e Restrições Técnicas

- Backend: PHP ^8.2 com Laravel ^12.
- Frontend: React/JavaScript com build via Vite; componentes em arquivos `.jsx`.
- Novas dependências ou mudanças de stack DEVEM ser justificadas no plano da feature
  (`/speckit-plan`) antes de serem adotadas, respeitando o Princípio IV.
- Timestamps e soft delete: toda tabela criada pelo projeto DEVE conter as colunas `created_at`,
  `updated_at` e `deleted_at` (na migration, `$table->timestamps()` e
  `$table->softDeletes()`), e o model correspondente DEVE usar a trait `SoftDeletes`.
  Exclusões de registros DEVEM ser lógicas (soft delete); exclusão física só é permitida se
  justificada no plano da feature.
- Referências a tabelas ainda inexistentes: colunas que dependem de uma tabela ainda não criada
  DEVEM ser criadas sem chave estrangeira (como `varchar` ou como id solto) e DEVEM ser ajustadas
  (tipo e chave estrangeira) na spec que criar a tabela de destino. A spec que cria essas colunas
  DEVE registrá-las nas suas premissas, para que a spec futura saiba o que ajustar.
- Paginação: toda listagem (backend e frontend) DEVE ser paginada e NÃO DEVE retornar mais de
  **100 registros por página**. Pedidos de tamanho de página acima de 100 NÃO DEVEM ser
  atendidos acima desse limite. O tamanho padrão de cada listagem é definido na spec ou no plano
  da feature, respeitando esse máximo.

**Rationale**: timestamps padronizados garantem rastreabilidade de quando cada registro foi
criado e alterado, e o soft delete preserva o histórico e permite recuperar dados excluídos,
essencial em um sistema de apostas. A paginação limitada mantém o tempo de resposta e o consumo
de memória previsíveis mesmo com grandes volumes de dados.

## Fluxo de Desenvolvimento

- Toda spec, plano e lista de tarefas DEVE passar pelo Constitution Check, verificando os
  Princípios I a V antes da implementação.
- Tarefas geradas DEVEM declarar explicitamente os arquivos que serão alterados, para que o
  escopo de edição (Princípio IV) seja verificável.
- Antes de concluir cada tarefa, DEVE ser executada a revisão de legibilidade do código
  alterado (Princípio V).
- Na revisão de código, qualquer diff fora do escopo solicitado e não previamente comunicado
  DEVE ser rejeitado.
- Sem testes automatizados: o projeto NÃO terá testes automatizados, nem no backend
  (PHP/Laravel) nem no frontend (React/JavaScript). Specs, planos e listas de tarefas NÃO DEVEM
  prever arquivos, tarefas ou dependências de teste, e nenhum teste DEVE ser criado na
  implementação. A validação das features é manual, seguindo os cenários de aceite da spec e o
  `quickstart.md`. Os arquivos de exemplo já existentes em `tests/` não são alterados
  (Princípio IV).
- Coleção do Postman: sempre que uma rota da API for criada, alterada ou removida, a coleção
  `docs/postman/wssports_api.postman_collection.json` DEVE ser regenerada na mesma tarefa,
  **substituindo** o arquivo existente (sem criar cópias ou versões paralelas). A coleção DEVE
  conter todas as rotas atuais da API e manter as variáveis `base_url` e `token` (o token é
  preenchido automaticamente pelas rotas de login e renovação). Toda lista de tarefas que crie ou
  altere rotas DEVE incluir uma tarefa explícita para essa atualização.

## Governance

- Esta constituição prevalece sobre qualquer outra prática ou convenção do projeto. Em caso de
  conflito, a constituição vence.
- Emendas DEVEM ser feitas via `/speckit-constitution`, com o motivo registrado no Sync Impact
  Report e aprovação do responsável pelo projeto.
- Versionamento semântico:
  - MAJOR: remoção ou redefinição incompatível de princípios ou regras de governança;
  - MINOR: novo princípio/seção ou ampliação relevante de orientação;
  - PATCH: esclarecimentos, redação e correções sem mudança de significado.
- A conformidade DEVE ser verificada em todo plano (Constitution Check), em toda lista de
  tarefas e em toda revisão de código.

**Version**: 1.12.0 | **Ratified**: 2026-09-25 | **Last Amended**: 2026-09-29
