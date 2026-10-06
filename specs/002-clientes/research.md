# Research: Clientes (Apostadores)

**Feature**: `002-clientes` | **Data**: 2026-09-29 | **Plano**: [plan.md](plan.md)

Decisões técnicas da feature. Não há itens `NEEDS CLARIFICATION` pendentes: as dúvidas de negócio
foram resolvidas nas sessões de Clarifications da [spec](spec.md).

## R-01. Guard próprio de clientes (JWT)

- **Decisão**: novo guard `clientes` (driver `jwt`) com o provider `clientes` (model
  `App\Models\Clientes`) em `config/auth.php`. O model implementa `JWTSubject`. O
  `config/jwt.php` já tem `lock_subject => true`: cada token leva a claim `prv` (hash da classe do
  model) e um guard recusa tokens emitidos para outro model (FR-015, SC-004).
- **Motivo**: reaproveita o pacote e a configuração da spec 001 (TTL de 60 min, blacklist ligada).
- **Alternativas**: mesmo guard `api` com coluna de tipo (misturaria usuários e clientes);
  Sanctum (nova dependência).

## R-02. Bloqueio de inativos e tokens anteriores à data de corte

- **Decisão**: middleware `App\Http\Middleware\GarantirAcessoCliente` nas rotas autenticadas da
  área do cliente. Responde `403` quando `Clientes::pode_acessar()` é falso (na prática, cliente
  inativo: o excluído nem chega ao middleware, porque o guard não encontra registros com soft
  delete e responde `401`) e `401` quando o `iat` do token é anterior a
  `clientes.tokens_validos_desde`. Essa coluna é preenchida na recuperação e na troca de senha e na
  desativação ou exclusão pelo painel.
- **Motivo**: o JWT não guarda estado; com a data de corte, todos os tokens antigos deixam de
  valer de uma vez (FR-017, FR-025, FR-030, FR-059).
- **Limitação aceita**: o `iat` tem precisão de segundos; com `refresh_iat = false` (config
  atual), o token renovado mantém o `iat` original e não burla a data de corte.

## R-03. Permissões de clientes e restrição por função

- **Decisão (revisada em 2026-09-29, Princípio VI)**: as 9 permissões (guard `api`, formato
  `<recurso>.<acao>`; eram 10, e a `clientes.editar_configuracoes_padrao` foi removida pela spec
  003 em 2026-10-01) ficam no enum `App\Enums\Funcao`, no mesmo padrão das permissões de
  usuários: constante `PERMISSOES_CLIENTES` (e `PERMISSOES_CLIENTES_RESTRITAS`), com
  `permissoes_padrao()` e `pode_usar()` definindo o que cada função recebe e pode usar:
  - Admin, Supervisor e Gerente: `clientes.listar`, `clientes.ver_dados_completos`,
    `clientes.editar`, `clientes.editar_configuracoes`, `clientes.movimentar_saldo`,
    `clientes_promocoes.gerenciar`;
  - somente Admin e Supervisor: `clientes.excluir`, `clientes.restaurar`,
    `clientes_promocoes.estornar`;
  - Vendedor: nenhuma.

  Os controllers do painel checam as duas coisas numa única verificação
  (`Funcao::usuario_pode()`, usado pelo trait `GarantirPermissaoCliente`): permissão direta **e**
  função permitida (FR-055, FR-081).
- **Distribuição padrão**: `Funcao::permissoes_padrao()` inclui as permissões de clientes — Admin e
  Supervisor recebem as 9, Gerente as 6 não restritas, Vendedor nenhuma. Usuários novos recebem
  no cadastro (fluxo da spec 001, sem mudar o `UsuariosController`); o `PapeisPermissoesSeeder`
  cria as permissões e o `ClientesSeeder` distribui aos usuários já existentes.
- **Histórico**: a primeira versão usava um enum separado (`PermissaoCliente`) só com o Admin
  recebendo as permissões, para não alterar o `Funcao.php`. Foi substituída por decisão do
  responsável, para seguir um único padrão entre recursos.

## R-04. Saldos em centavos inteiros e bloqueio de linha

- **Decisão**: serviço `App\Services\SaldoClientes` (`creditar()` e `debitar()`): dentro de
  `DB::transaction`, relê o cliente com `lockForUpdate()`, converte o saldo da carteira de texto
  decimal para centavos inteiros (sem `float`), calcula o posterior, recusa débito maior que o
  saldo, grava o novo valor e cria a transação com saldo anterior e posterior.
- **Motivo**: sem erro de arredondamento (FR-040, FR-044) e com movimentações do mesmo cliente
  serializadas (FR-046, SC-002). Clientes diferentes não esperam uns pelos outros.
- **Alternativas**: `bcmath` (extensão pode não estar instalada); `UPDATE saldo = saldo + ?`
  sem ler antes (não permite gravar o saldo anterior nem recusar o débito antes).

## R-05. Enums: casos com acento e valores em português (constituição v1.13.0)

- **Decisão**: enums PHP backed `string`, com **nome da classe sem acento** (o nome do arquivo e o
  autoload PSR-4 dependem dele) e **casos em `PascalCase` com acento**. Os valores gravados no
  banco ficam em português, com a primeira letra maiúscula e acentos:

  | Enum | Casos → valores |
  |---|---|
  | `Genero` | `Masculino`, `Feminino`, `Outro`, `NãoInformado` → `'Não informado'` |
  | `Carteira` | `Saldo` → `'Saldo'`, `PromoçãoEsportes` → `'Promoção esportes'`, `PromoçãoCassino` → `'Promoção cassino'` |
  | `TipoTransacao` | `Crédito`, `Débito` |
  | `OrigemTransacao` | `AjusteManual` → `'Ajuste manual'`, `Promoção`, `Aposta`, `Prêmio`, `Estorno` |
  | `ModalidadePromocao` | `Esportes`, `Cassino` |
  | `CategoriaPromocao` | `PrimeiroCadastro` → `'Primeiro cadastro'`, `PrimeiroDepósito` → `'Primeiro depósito'`, `QualquerDepósito` → `'Qualquer depósito'`, `Indicação` |
  | `TipoGanho` | `Fixo`, `Percentual` |
  | `SituacaoEstorno` | `EmAndamento` → `'Em andamento'`, `Concluído` |
  | `TipoMeioPagamento` | `Pix`, `TransferênciaBancária` → `'Transferência bancária'` |
  | `TipoChavePix` | `Cpf` → `'CPF'`, `Cnpj` → `'CNPJ'`, `Email` → `'E-mail'`, `Telefone`, `ChaveAleatória` → `'Chave aleatória'` |
  | `TipoConta` | `Corrente`, `Poupança` |

- **Carteira × coluna**: como o valor do enum não é o nome da coluna, `Carteira` tem o método
  `coluna(): string` (`Saldo` → `saldo`, `PromoçãoEsportes` → `saldo_promocao_esportes`,
  `PromoçãoCassino` → `saldo_promocao_cassino`).
- **Banco**: colunas `varchar` com `utf8mb4` (padrão do projeto), sem `ENUM` do MySQL, para
  acrescentar valores sem alterar coluna. Validação com `Rule::enum`.
- **Risco aceito**: casos com acento funcionam no PHP 8.2 (identificadores UTF-8), mas exigem os
  arquivos em UTF-8 — já é o padrão do projeto.

## R-06. Campos Sim/Não como boolean

- **Decisão**: flags (`ativo`, `aceita_promocao`, `bloquear_saque`, `ativa`, `principal`,
  permissões das configurações) são `boolean`.

## R-07. Nomes das tabelas e colunas

- **Decisão**: prefixo `clientes_` em todas as tabelas ligadas a `clientes` (v1.9.0):
  `clientes_transacoes`, `clientes_configuracoes`, `clientes_meios_pagamento`, `clientes_promocoes`, `clientes_codigos_recuperacao`.
  `ddi` e `email` usam a exceção de siglas consagradas (v1.13.0). Nomes completos no lugar das
  abreviações do sistema antigo (`v_apostas_minima` → `valor_minimo_aposta`,
  `v_converter_bonus` → `valor_maximo_conversao`...). FKs no formato `<tabela>_id`.
- **Senha**: coluna `password`, coberta pela exceção de colunas de autenticação (v1.12.0).
- **Meios de pagamento**: `clientes_meios_pagamento` (plural correto de "meio de pagamento").

## R-08. Colunas sem chave estrangeira (constituição v1.10.0)

- **Decisão**: `clientes.codigo_afiliado` (`varchar`), `clientes_transacoes.referencia_id` (id solto
  do registro de origem: promoção agora, aposta no futuro) e `esportes_permitidos` (`json`).
  Registradas nas premissas da spec; ajustadas nas specs de afiliados, apostas e esportes.

## R-09. Dados únicos opcionais, exclusão com sufixo e restauração

- **Decisão**: `cpf` e `email` são `nullable` com índice único (o MySQL aceita vários `NULL` num
  índice único, então clientes sem CPF ou sem e-mail não conflitam). Strings vazias são convertidas
  para `NULL` antes de gravar.
- **Exclusão**: na mesma transação, acrescenta `_deleted_<timestamp Unix>` a `telefone`, `cpf` e
  `email` (os preenchidos), preenche `deleted_at` e `tokens_validos_desde`. `email` tem 150
  caracteres e `telefone`/`cpf` 40, para caber o sufixo.
- **Restauração**: remove o sufixo com `/_deleted_\d+$/`, verifica conflito com clientes não
  excluídos e responde `422` com os campos em conflito; aceita `ddi`, `telefone`, `cpf` e `email`
  novos no corpo para resolver (FR-060, FR-061).

## R-10. Senha forte e confirmação

- **Decisão**: `Password::min(8)->letters()->numbers()` + `confirmed`, com mensagens em português,
  no cadastro, troca, recuperação e edição pelo painel.

## R-11. Validações de documentos, telefone, e-mail e chave Pix

- **Decisão**: regras `App\Rules\CpfValido` e `App\Rules\CnpjValido` (dígitos verificadores, não
  todos iguais). Telefone, CPF e CNPJ normalizados para só dígitos no `prepareForValidation()`;
  e-mail em minúsculas e sem espaços. Telefone: 10 ou 11 dígitos com DDI 55; 4 a 14 nos demais;
  DDI de 1 a 3 dígitos, padrão `55`.
- **Chave Pix por tipo** (FR-033): CPF → `CpfValido`; CNPJ → `CnpjValido`; E-mail → `email`;
  Telefone → só dígitos, de 10 a 13 (com ou sem DDI); Chave aleatória → UUID
  (`/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i`).

## R-12. Mascaramento no painel

- **Decisão**: resources de cliente e de meio de pagamento mascaram quando o usuário do painel não
  tem `clientes.ver_dados_completos` (FR-057): CPF `***.456.789-**`; telefone `(11) *****-7777`
  (DDI 55) ou só os 4 últimos dígitos; e-mail `a***@mail.com`; chave Pix e conta com só os 4
  últimos caracteres. Sem a permissão, a busca por CPF, telefone e e-mail passa a ser exata.
- **Busca**: nome por `LIKE '%x%'`; telefone, CPF e e-mail por prefixo (quem tem a permissão). A
  busca de excluídos casa com o valor antes do sufixo.

## R-13. Recuperação de senha

- **Decisão**: tabela `clientes_codigos_recuperacao`; código de 6 dígitos (`random_int`) gravado
  com `Hash::make`, validade de 15 min, contador de tentativas, datas de uso e invalidação. Novo
  pedido invalida o anterior. Limite de 1 pedido/min por `ddi.telefone` com `RateLimiter`. Resposta
  sempre igual (FR-023).

## R-14. WhatsApp por eventos, registrado no log

- **Decisão**: eventos `ClienteCadastrado` e `CodigoRecuperacaoGerado` (`ShouldDispatchAfterCommit`)
  e o listener `RegistrarMensagemWhatsapp`, que só grava no log (`Log::info`) dentro de
  `try/catch`. A futura spec de WhatsApp só troca o listener.

## R-15. Cadastro atômico e promoção de primeiro cadastro

- **Decisão**: serviço `App\Services\CadastroClientes` cria, numa transação, o cliente, as
  configurações (valores padrão das colunas, com `aceita_promocao` do cadastro; sem tabela padrão
  desde a spec 003) e, se `aceita_promocao`, um crédito por
  promoção vigente de `Primeiro cadastro` (origem `Promoção`, `referencia_id` = id da promoção). O
  evento `ClienteCadastrado` sai depois do commit.

## R-16. Regras da promoção

- **Sobreposição (FR-070)**: ao salvar uma promoção ativa, procura outra ativa, não excluída, não
  estornada, da **mesma categoria e mesma modalidade**, com período sobreposto
  (`inicio_a <= fim_b` e `inicio_b <= fim_a`, fim nulo = sem fim) e recusa com `422`.
- **Tipo de ganho (FR-065)**: `Percentual` só em `Primeiro depósito` e `Qualquer depósito`, com
  valor maior que 0 e até 100 e `valor_maximo_deposito` obrigatório; `Primeiro cadastro` e
  `Indicação` só `Fixo`.
- **Rollover (FR-066)**: inteiro ≥ 0; ≥ 1 em `Primeiro depósito`.
- **Regras de uso (FR-067)**: valores > 0, `valor_minimo_aposta ≤ valor_maximo_aposta`, odds
  ≥ 1,00. Só armazenadas e validadas (FR-072).
- **Imutabilidade (FR-073)**: se existir transação de origem `Promoção` com `referencia_id` da
  promoção, `valor`, `tipo_ganho`, `categoria` e `modalidade` não podem mudar (`422`). Promoção
  estornada não pode ser editada nem reativada (FR-068).

## R-17. Estorno de promoção em segundo plano

- **Decisão**: `POST /api/clientes-promocoes/{promocao}/estornar` valida (não estornada, sem
  estorno em andamento), grava na promoção `ativa = false`, `estorno_situacao = 'Em andamento'`,
  motivo, autor, início e o total de clientes a processar, e despacha o job
  `App\Jobs\EstornarPromocao` na fila `database` (já configurada: `QUEUE_CONNECTION=database` e
  migration `jobs` existente). Responde `202`.
- **Job**: percorre, em lotes de 500 (`chunkById` pela coluna `clientes_id`), os `clientes_id` distintos com transação de
  origem `Promoção` e `referencia_id` da promoção (inclui clientes inativos e excluídos, via
  `withTrashed`). Para cada cliente, **pula** se já existir transação de origem `Estorno` com o
  mesmo `referencia_id` (idempotência, FR-080); senão calcula o total recebido da promoção e
  debita `min(total recebido, saldo promocional da modalidade)` via `SaldoClientes::debitar()`
  (origem `Estorno`, observação = motivo), ou não faz nada se o saldo for 0,00. Recalcula os
  contadores da promoção (`estorno_clientes_processados`, `estorno_valor_total`) a partir do banco
  a cada lote, para que a retomada não conte em dobro. No
  fim grava `estorno_situacao = 'Concluído'` e `estorno_concluido_em`.
- **Retomada**: se o worker cair, o job volta para a fila após o `retry_after` e recomeça; a
  checagem de idempotência impede estornar alguém duas vezes. `tries = 5`, `ShouldBeUnique` por
  promoção.
- **Sem pendência**: o que faltar é descartado; `saldo` real e a outra modalidade nunca mudam
  (FR-079).
- **Desempenho (SC-010)**: 10.000 clientes em lotes de 500, com uma transação curta por cliente.
- **Alternativas**: processar na própria requisição (estouraria o tempo de resposta); `UPDATE` em
  massa (não gera as transações de estorno exigidas por FR-041).

## R-18. Meios de pagamento

- **Decisão**: tabela única `clientes_meios_pagamento` com colunas dos dois tipos (as do tipo que
  não se aplica ficam nulas) e validação condicional por `tipo` (`required_if`). Unicidade por
  cliente (FR-035) verificada na aplicação, entre os não excluídos. `principal` mantido pela
  aplicação numa transação (desmarca os outros; ao excluir o principal, promove o mais antigo).
- **Rotas**: `apiResource` na área do cliente (`/area_cliente/meios_pagamento`) e no painel
  (`/clientes/{cliente}/meios_pagamento`, sem `show`). Marcar principal = `update` com
  `principal: true`.
- **Alternativas**: uma tabela por tipo (mais joins e duas rotas por operação).

## R-19. Paginação e ordenação

- **Decisão**: `por_pagina` de 1 a 100, padrão 20. Ordenação da listagem de clientes por
  `ordenar_por` (`nome`, `created_at`, `saldo`) e `direcao` (`asc`, `desc`).
- **Filtros vazios**: as regras dos filtros usam `nullable` (e não `sometimes`), porque o Laravel
  converte `?busca=` em `null`; assim, um filtro vazio é ignorado em vez de gerar `422`. Vale para a
  listagem de clientes, a de excluídos, a de promoções e os extratos.

## R-20. Banco local e fila

- **Decisão**: só migrations novas com `php artisan migrate` e o seeder novo com
  `php artisan db:seed --class=ClientesSeeder`. Sem `migrate:refresh`/`migrate:fresh` (banco
  compartilhado com o legado). Para o estorno, rodar `php artisan queue:work`.

## R-21. Validação sem testes automatizados

- **Decisão**: nenhum arquivo em `tests/`; validação manual pelo [quickstart.md](quickstart.md) e
  pela coleção do Postman regenerada (constituição).
