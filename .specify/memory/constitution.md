<!--
Sync Impact Report
- Version change: 2.0.0 → 2.1.0 (MINOR: exceções novas e regra nova de stack; nenhum artefato
  existente deixa de cumprir a constituição)
- Princípios modificados:
  - I. Nomenclatura em snake_case → I. Nomenclatura: exceção para o frontend (componentes e
    styled-components em inglês PascalCase; variáveis, funções e hooks em camelCase português;
    chaves da API como chegam) e para as pastas de componentes (PascalCase)
  - III. Estrutura de Componentes React: pasta do componente em inglês PascalCase
- Princípios adicionados: nenhum
- Seções alteradas:
  - Stack e Restrições Técnicas: regra de atualização e cache do frontend (servidor como fonte
    da versão e das configurações; HTML nunca do cache com conexão)
- Impacto: as specs 001 a 004 são só de backend e não mudam. A spec 005 (`research.md`) passa a
  usar nomes de componentes em PascalCase. O `.claude/CLAUDE.md` é alinhado na mesma entrega
- Seções adicionadas: nenhuma
- Seções removidas: nenhuma
- TODOs pendentes: nenhum
-->

# WSSports Constitution

## Core Principles

### I. Nomenclatura

- Variáveis, funções, métodos, propriedades, chaves de arrays/objetos, parâmetros e nomes
  usados nas instruções (specs, planos, tarefas) DEVEM usar `snake_case` no backend (PHP).
  `camelCase` é proibido no PHP. O frontend segue a exceção abaixo.
- **Exceção — frontend (React/JavaScript)**:
  - componentes React e styled-components usam nomes em **inglês** e `PascalCase` (ex.:
    `OddButton`, `BetSlip`, `MatchCard`);
  - variáveis, funções, hooks e parâmetros usam `camelCase` em **português** (ex.:
    `adicionarPalpite`, `cupomAberto`, `useCupom`);
  - chaves de dados vindos do backend (JSON) são usadas como chegam, em `snake_case` (ex.:
    `time_casa`, `quantidade_cotacoes`), sem camada de conversão;
  - o backend (PHP) e o banco continuam com as regras deste princípio, sem a exceção.
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
- **Exceção — siglas e termos consagrados**: siglas e termos em inglês de uso consagrado na
  programação PODEM ser usados como estão em nomes de colunas, variáveis, chaves e campos (ex.:
  `ddi`, `email`, `url`, `ip`, `token`, `pix`), no lugar de uma tradução ou nome longo em
  português (ex.: `ddi` e não `codigo_pais`). Continuam em `snake_case` e minúsculas.
- **Enums (casos e valores)**: os casos de enum PHP DEVEM ser escritos em `PascalCase` em
  português, com acentos quando a palavra tiver (ex.: `Promoção`, `Estorno`, `Apostas`,
  `PrimeiroDepósito`). Os valores gravados no banco para esses enums (colunas `varchar` ou `enum`)
  DEVEM ser em português, com a primeira letra maiúscula, acentos e espaços normais (ex.:
  `'Promoção'`, `'Estorno'`, `'Apostas'`, `'Primeiro depósito'`). Nomes de permissões e papéis do
  spatie seguem as regras próprias deste princípio, e não esta.
- **Prefixo de tabelas relacionadas**: tabelas ligadas a uma tabela principal DEVEM usar o nome
  dela como prefixo, seguido do complemento (ex.: `clientes` → `clientes_transacoes`,
  `clientes_configuracoes`), para ficarem listadas juntas no banco.
- **Exceção — tabelas de porcentagem de cotação (`porcentagens_*`)**: as tabelas que guardam
  porcentagens de ajuste de cotação DEVEM usar `porcentagens` como nome principal, seguido do
  público ou do recurso a que a regra se aplica (ex.: `porcentagens_clientes`,
  `porcentagens_clientes_ao_vivo`, `porcentagens_vendedores`, `porcentagens_vendedores_ao_vivo`,
  `porcentagens_campeonatos`, `porcentagens_confrontos`), e não o nome da tabela a que se ligam,
  para que todas as regras de cotação fiquem listadas juntas no banco. `porcentagens_<complemento>`
  é o recurso dessas tabelas também no nome das permissões (ex.: `porcentagens_clientes.editar`).
  A exceção vale só para tabelas de porcentagem de cotação; as demais tabelas relacionadas
  continuam com o prefixo da tabela principal.
- **Nomes de permissões**: toda permissão DEVE seguir o padrão `<recurso>.<acao>`, em que
  `recurso` é o nome da tabela principal a que a ação se refere e `acao` é um verbo ou expressão
  em `snake_case` (ex.: `usuarios.listar`, `clientes.excluir`, `clientes.movimentar_saldo`,
  `clientes_promocoes.gerenciar`).
- **Exceção — caminhos de rota (kebab-case)**: os segmentos de rota e os prefixos de grupo DEVEM
  usar hífen no lugar de underline (ex.: `/area-cliente/meus-dados`, `/clientes-promocoes`).
  Parâmetros de query string continuam em `snake_case` (ex.: `?por_pagina=20&data_inicial=2026-09-01`),
  assim como chaves de JSON (corpo e resposta), nomes de parâmetros de rota no código (ex.:
  `{meio_pagamento}`), variáveis e colunas.
- Nomes de pastas DEVEM ser escritos em **inglês** e `snake_case` (ex.: `components`,
  `services`, `pages`, `user_roles`).
- **Exceção — pastas PSR-4**: pastas dentro de `app/` que correspondem a namespaces PSR-4
  (ex.: `app/Enums`, `app/Policies`, `app/Http/Requests`, `app/Http/Resources`) usam
  `PascalCase`, igual a `app/Http` e `app/Models`. As demais pastas continuam em inglês e
  `snake_case`.
- **Exceção — pastas de componentes React**: a pasta de cada componente usa o nome do
  componente, em inglês e `PascalCase` (Princípio III). As demais pastas do frontend (ex.:
  `components`, `pages`, `hooks`, `utils`) continuam em inglês e `snake_case`.

**Rationale**: um padrão de nomes por camada elimina a ambiguidade; o
banco em português reflete o domínio do negócio, e as pastas em inglês seguem a convenção do
ecossistema Laravel/React. As exceções existem porque o autoload PSR-4 liga a pasta ao
namespace e o Laravel e os pacotes só reconhecem seus métodos pelo nome original; renomeá-los
quebraria o funcionamento. Tabelas de pacotes seguem o padrão do pacote para manter
compatibilidade com a sua documentação e com outros sistemas que usam o mesmo pacote. No
frontend, `camelCase` e componentes em inglês seguem a convenção do ecossistema React, enquanto
variáveis e funções em português mantêm o vocabulário do negócio; as chaves da API ficam como
chegam para existir um único contrato entre backend e frontend.

### II. Idioma Português

- Specs, planos, tarefas e demais instruções DEVEM ser escritos em **português**, tanto no
  backend (PHP/Laravel) quanto no frontend (React/JavaScript).
- Comentários no código DEVEM estar em **português**, no backend e no frontend.
- Artefatos que abrangem backend e frontend usam português em todas as partes.
- Nomes no código, no banco e nas pastas continuam seguindo o Princípio I.

**Rationale**: um único idioma em todos os artefatos facilita a leitura e a revisão pelo
responsável e pela equipe, sem precisar alternar de idioma entre backend e frontend.

### III. Estrutura de Componentes React

- Cada componente React DEVE ter sua própria pasta, nomeada exatamente com o nome do
  componente, em inglês e `PascalCase` (ex.: `components/OddButton/`).
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

### VI. Consistência de Padrões entre Recursos

- Todo recurso novo (ex.: `clientes`) DEVE seguir a mesma estrutura já usada pelos recursos
  existentes (ex.: `usuarios`): onde ficam as permissões e seus padrões por função, enums,
  seeders, requests, resources, controllers e rotas.
- Quando a implementação exigir um padrão diferente do existente, ou quando uma restrição (como
  o Princípio IV) impedir seguir o padrão, a decisão NÃO PODE ser tomada sozinha: DEVE ser
  apresentada ao responsável, com as opções e o impacto de cada uma, antes de entrar na spec, no
  plano ou no código.

**Rationale**: dois padrões para a mesma coisa tornam o código imprevisível e difícil de manter;
decidir um desvio sem consultar o responsável tira dele o controle sobre a arquitetura do
projeto.

### VII. Consulta ao Sistema Antigo

- Ao especificar (`/speckit-specify`, `/speckit-clarify`), planejar (`/speckit-plan`) ou
  implementar (`/speckit-implement` ou alteração manual) uma funcionalidade, o sistema antigo
  DEVE ser consultado para entender como ela funcionava: rotas, controllers, models, regras de
  negócio, validações e mensagens. O sistema antigo fica na pasta
  `C:\Users\wedso\OneDrive\Área de Trabalho\projetos\wssports.bet` (repositório
  `wssports/api`).
- O sistema antigo é **somente leitura**: nenhum arquivo dele DEVE ser criado, alterado ou
  excluído.
- A partir do que foi consultado, DEVEM ser propostas melhorias e refatorações para o novo
  sistema (ex.: regras fixas no código passando a ser configuração, consultas com SQL
  concatenado passando a usar o query builder, cálculos de dinheiro sem ponto flutuante). O
  sistema antigo é referência de comportamento, não de estrutura: o código novo continua
  seguindo os padrões do novo sistema (Princípio VI).
- Toda diferença de comportamento em relação ao sistema antigo (regra removida, alterada ou
  acrescentada) DEVE ser apresentada ao responsável antes de entrar na spec, no plano ou no
  código, e a decisão DEVE ficar registrada na spec (Clarifications) ou no `research.md`.
- O `research.md` da feature DEVE listar os arquivos do sistema antigo consultados e resumir
  como a funcionalidade funcionava. Quando a funcionalidade não existir no sistema antigo, isso
  DEVE ser registrado explicitamente.

**Rationale**: o novo sistema substitui um sistema em produção; conhecer o comportamento antigo
evita perder regras de negócio de que os usuários dependem e permite corrigir, de forma
consciente, os problemas que ele tinha, em vez de repeti-los ou descartá-los sem decisão.

### VIII. Fidelidade Visual ao Sistema Antigo

- O frontend novo DEVE ser visualmente idêntico ao do sistema antigo
  (`wssports.bet/resources/js`): layout, cores, tipografia (Roboto), ícones (Material Icons),
  imagens, espaçamentos, tamanhos, textos e rótulos, animações e comportamento responsivo
  (mesmos breakpoints).
- **Tema** significa apenas as cores, e o sistema de temas do sistema antigo DEVE ser mantido:
  - cor principal (`temas`), com as 6 opções existentes (vermelho `#c40808`, amarelo `#d0af01`,
    verde `#008000`, azul `#006eb1`, laranja `#fe6a00`, rosa `#b91552`), e a cor escura
    derivada de cada uma (`letter`);
  - cor de fundo (`cor_fundo`), preto `#000000` ou branco `#FFFFFF`.
- **Áreas**: os antigos modos de layout `SITE`, `APP` e `CASINO` não são tema; são áreas com
  rota própria:
  - `/`: site de apostas (antigo `SITE`);
  - `/app`: sistema com layout de aplicativo (antigo `APP`);
  - `/cassino`: estrutura do cassino (antigo `CASINO`).
  A área é definida pela URL e NÃO DEVE ser guardada no aparelho (`localStorage`). Essa troca
  de mecanismo foi aprovada pelo responsável e não conta como diferença visual. Cada área tem
  spec própria e reutiliza os componentes compartilhados (ex.: odd, card de jogo, cupom).
- A estrutura do código e as tecnologias PODEM ser novas: o sistema antigo é referência de
  visual e de comportamento, não de estrutura (Princípio VII). Melhorias de código, desempenho
  ou acessibilidade são permitidas desde que não mudem o que o usuário vê.
- Toda diferença visual em relação ao sistema antigo DEVE ser apresentada ao responsável e
  aprovada antes de entrar na spec, no plano ou no código, e a decisão DEVE ficar registrada na
  spec (Clarifications) ou no `research.md`.
- Toda spec de frontend DEVE listar as telas e os componentes do sistema antigo usados como
  referência visual.

**Rationale**: os usuários já operam o sistema atual no dia a dia (vendedores, gerentes e
apostadores); manter o visual idêntico permite trocar o sistema sem retreinamento e sem
estranhamento, enquanto a reescrita corrige o código por trás da tela.

### IX. Dados Fake Provisórios

- Quando o frontend precisar de dados que o backend novo ainda não fornece, DEVEM ser usados
  dados fake até que uma nova spec os substitua por dados reais.
- Os dados fake DEVEM:
  - ficar isolados em um local único e identificável, definido no plano da feature;
  - ter o mesmo formato (nomes de chaves e tipos) que os dados reais terão, para que a troca
    não exija mudar os componentes;
  - nunca ser misturados nem gravados no banco junto com dados reais;
  - nunca ser usados em operações com dinheiro real (registrar aposta, saldo, pagamento).
- A spec e o `research.md` da feature DEVEM listar cada dado fake usado e qual spec ou recurso
  do backend o substituirá.
- A spec que trouxer o dado real DEVE remover o fake correspondente na mesma entrega.

**Rationale**: o frontend pode avançar sem esperar todo o backend, mas fakes espalhados ou com
formato diferente viram dívida invisível; isolá-los e listá-los garante que cada um seja
trocado de forma controlada e que nenhum dado inventado chegue a operações reais.

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
- Atualização e cache do frontend: o servidor é a fonte da versão e das configurações.
  - Ao publicar uma versão nova, os usuários DEVEM passar a usá-la automaticamente na próxima
    interação ou ao voltar para a aba, sem limpar cache, sem reinstalar o PWA e sem número de
    versão mantido à mão.
  - O HTML NÃO DEVE ser servido do cache quando houver conexão; só arquivos estáticos com hash
    no nome PODEM ficar em cache.
  - Configurações (tema, banners, textos) DEVEM vir do servidor a cada carga, para que mudar uma
    configuração não exija publicar uma versão.

**Rationale**: timestamps padronizados garantem rastreabilidade de quando cada registro foi
criado e alterado, e o soft delete preserva o histórico e permite recuperar dados excluídos,
essencial em um sistema de apostas. A paginação limitada mantém o tempo de resposta e o consumo
de memória previsíveis mesmo com grandes volumes de dados. A regra de atualização evita usuários
presos em versões antigas, problema do sistema antigo, que dependia de versão manual no service
worker e de limpar o cache.

## Fluxo de Desenvolvimento

- Toda spec, plano e lista de tarefas DEVE passar pelo Constitution Check, verificando os
  Princípios I a IX antes da implementação.
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
- Alterações manuais em código já implementado: quando o responsável pedir uma alteração direta em
  código de uma feature (fora dos comandos `/speckit-*`), a mesma entrega DEVE atualizar todos os
  artefatos afetados para manter o sistema e a documentação coerentes: `spec.md` (requisitos,
  cenários e Clarifications com a decisão), `plan.md`, `research.md`, `data-model.md`,
  `contracts/`, `quickstart.md`, `tasks.md` (descrição das tarefas e nota da revisão), além de
  seeders, migrations, rotas e a coleção do Postman quando envolvidos. Nenhum artefato pode ficar
  descrevendo um comportamento diferente do código.
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

**Version**: 2.1.0 | **Ratified**: 2026-09-25 | **Last Amended**: 2026-10-09
