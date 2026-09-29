# Research: Clientes (Apostadores)

**Feature**: `002-clientes` | **Data**: 2026-09-29 | **Plano**: [plan.md](plan.md)

Decisões técnicas da feature. Não há itens `NEEDS CLARIFICATION` pendentes: as dúvidas de negócio
foram resolvidas no `/speckit-clarify` (seção Clarifications da [spec](spec.md)).

## R-01. Guard próprio de clientes (JWT)

- **Decisão**: novo guard `clientes` (driver `jwt`) com o provider `clientes` (model
  `App\Models\Clientes`) em `config/auth.php`. O model implementa `JWTSubject`. O pacote
  `php-open-source-saver/jwt-auth` já está instalado e o `config/jwt.php` já tem
  `lock_subject => true`: cada token leva a claim `prv` (hash da classe do model) e um guard
  recusa tokens emitidos para outro model. Isso garante FR-015 e SC-004 sem código extra.
- **Motivo**: reaproveita o pacote e a configuração da spec 001 (TTL de 60 min, blacklist
  ligada), sem nova dependência.
- **Alternativas**: usar o mesmo guard `api` com uma coluna de tipo (misturaria usuários e
  clientes na mesma tabela e nas mesmas permissões); Sanctum (nova dependência e outro modelo de
  token).

## R-02. Bloqueio de clientes inativos, excluídos e tokens anteriores à troca de senha

- **Decisão**: novo middleware `App\Http\Middleware\GarantirAcessoCliente` nas rotas autenticadas
  da área do cliente. Ele recusa com 403 quando `Clientes::pode_acessar()` é falso (inativo ou
  excluído) e com 401 quando o `iat` do token é anterior a `clientes.tokens_validos_desde`. A
  coluna `tokens_validos_desde` é preenchida na recuperação de senha (FR-025), na troca de senha
  e na desativação ou exclusão pelo painel.
- **Motivo**: o JWT não guarda estado; a blacklist só invalida o token usado na requisição. Com
  a data de corte no cliente, todos os tokens antigos deixam de valer de uma vez (FR-025,
  FR-017, FR-054).
- **Alternativas**: reaproveitar o `GarantirAcesso` da spec 001 (não conhece a data de corte do
  token); guardar cada token emitido numa tabela (estado desnecessário).

## R-03. Permissões de clientes no spatie e restrição por função

- **Decisão**: as 9 permissões da spec entram no guard `api` com os nomes definidos nela
  (`ver_clientes`, `ver_dados_completos_clientes`, `editar_clientes`, `excluir_clientes`,
  `restaurar_clientes`, `editar_travas_clientes`, `movimentar_saldo_clientes`,
  `editar_configuracoes_padrao_clientes`, `gerenciar_promocoes`). Elas ficam num novo enum
  `App\Enums\PermissaoCliente`, que também informa quais funções podem usar cada uma:
  - Admin, Supervisor e Gerente: `ver_clientes`, `ver_dados_completos_clientes`,
    `editar_clientes`, `editar_travas_clientes`, `movimentar_saldo_clientes`,
    `gerenciar_promocoes`;
  - somente Admin e Supervisor: `excluir_clientes`, `restaurar_clientes`,
    `editar_configuracoes_padrao_clientes`;
  - Vendedor: nenhuma.

  Os controllers do painel checam as duas coisas numa única verificação (trait
  `GarantirPermissaoCliente`): ter a permissão direta **e** ter uma função permitida. Assim, um
  Gerente que receba `excluir_clientes` continua recusado (FR-049).
- **Motivo**: segue o modelo da spec 001 (permissões diretas no usuário) sem alterar o
  `PermissoesUsuariosController` nem o enum `Funcao`.
- **Distribuição padrão**: o novo `ClientesSeeder` cria as permissões e as dá ao(s) usuário(s) com
  papel Admin. Os demais recebem pela gestão de permissões da spec 001.
- **Observação**: a spec 001 usa o formato `usuarios.listar`; aqui foram mantidos os nomes
  escritos na spec 002. Se preferir o mesmo formato (`clientes.listar`...), ajustar spec e plano
  antes do `/speckit-tasks`.

## R-04. Saldos em centavos inteiros e bloqueio de linha

- **Decisão**: o serviço `App\Services\SaldoClientes` (métodos `creditar()` e `debitar()`) abre
  uma transação no banco, lê o cliente com `lockForUpdate()`, converte o saldo da carteira de
  texto decimal para centavos inteiros (sem `float`), calcula o saldo posterior, recusa débito
  maior que o saldo, grava o novo valor e cria a transação com saldo anterior e posterior. O
  retorno volta ao formato decimal com 2 casas.
- **Motivo**: evita erros de arredondamento de `float` com dinheiro (FR-032, FR-036) e serializa
  movimentações simultâneas no mesmo cliente (FR-038, SC-002). O bloqueio é por cliente, então
  clientes diferentes não esperam uns pelos outros.
- **Alternativas**: `bcmath` (extensão pode não estar instalada); `UPDATE ... SET saldo = saldo +
  ?` sem ler antes (não permite gravar o saldo anterior com segurança nem recusar débito antes).

## R-05. Carteiras, tipos e origens como enums PHP

- **Decisão**: enums string `Carteira` (`saldo`, `saldo_promocao_esportes`,
  `saldo_promocao_cassino` — o valor é o nome da coluna em `clientes`), `TipoTransacao`
  (`credito`, `debito`), `OrigemTransacao` (`ajuste_manual`, `promocao`, `aposta`, `premio`,
  `estorno`), `Genero` (`masculino`, `feminino`, `outro`, `nao_informado`), `ModalidadePromocao`
  (`esportes`, `cassino`) e `GatilhoPromocao` (`cadastro`). No banco ficam como `varchar`.
- **Motivo**: validação com `Rule::enum`, sem `ENUM` do MySQL (acrescentar valores depois não
  exige alterar a coluna — a spec de apostas vai usar `aposta` e `premio`).

## R-06. Campos Sim/Não como boolean

- **Decisão**: travas e flags (`ativo`, `aceita_promocao`, `ativa`, permissões das travas) são
  `boolean`, e não `enum('Sim','Não')` como no sistema antigo.
- **Motivo**: liberado pelo responsável na clarificação; mais simples de validar e consultar.

## R-07. Nomes das tabelas e colunas

- **Decisão**: prefixo `clientes_` em todas as tabelas ligadas a `clientes` (constituição v1.9.0):
  `clientes_transacoes`, `clientes_configuracoes`, `clientes_configuracoes_padrao`,
  `clientes_promocoes`, `clientes_codigos_recuperacao`. Colunas renomeadas para nomes claros (ex.:
  `n_minimo_confrontos` → `quantidade_minima_opcoes`, `v_aposta_maxima` → `valor_maximo_aposta`).
  Chaves estrangeiras no formato `<tabela>_id` (`clientes_id`, `usuarios_id`), como na spec 001.
- **Senha**: coluna `password`, como em `usuarios`, porque o guard e o `attempt()` do Laravel
  esperam esse nome.

## R-08. Colunas sem chave estrangeira (constituição v1.10.0)

- **Decisão**: `clientes.codigo_afiliado` (`varchar`, afiliados ainda não existem),
  `clientes_transacoes.referencia_id` (id solto do registro de origem — promoção agora, aposta no
  futuro) e `esportes_permitidos` (`json` com nomes de esporte). Ficam registradas nas premissas da
  spec e serão ajustadas nas specs de afiliados, apostas e esportes.

## R-09. Exclusão com sufixo e restauração

- **Decisão**: `ClientesController@destroy` grava, na mesma transação, `telefone` e `cpf` com o
  sufixo `_deleted_<timestamp Unix>` e preenche `deleted_at` e `tokens_validos_desde`. A
  restauração (`POST /api/clientes/{cliente}/restaurar`) remove o sufixo com expressão regular
  `/_deleted_\d+$/`, verifica conflito entre os clientes não excluídos e, se houver, responde 422
  com os campos em conflito; aceita `telefone`, `codigo_pais` e `cpf` novos no corpo para resolver.
  As colunas `telefone` e `cpf` têm 40 caracteres para caber o sufixo.
- **Motivo**: a unicidade continua garantida pelos índices únicos do banco, sem índice parcial
  (que o MySQL não tem).

## R-10. Senha forte e confirmação

- **Decisão**: `Password::min(8)->letters()->numbers()` + `confirmed` (campo
  `password_confirmation`) no cadastro, troca, recuperação e edição pelo painel. Mensagens
  customizadas em português, como nos FormRequests da spec 001.

## R-11. CPF e telefone

- **Decisão**: regra de validação `App\Rules\CpfValido` (11 dígitos, não todos iguais, dígitos
  verificadores). Telefone e CPF são normalizados para só dígitos no `prepareForValidation()` dos
  FormRequests. Telefone: 10 ou 11 dígitos para o código 55; 4 a 14 para os demais; código do país
  de 1 a 3 dígitos, padrão `55`.

## R-12. Mascaramento de CPF e telefone no painel

- **Decisão**: `ClientesResource` mascara `cpf` (`***.456.789-**`) e `telefone` (só os 4 últimos
  dígitos) quando o usuário do painel não tem `ver_dados_completos_clientes`. Sem essa permissão, a
  busca por CPF e telefone passa a ser exata (`=`) em vez de por prefixo (`LIKE 'x%'`).
- **Busca**: nome por `LIKE '%x%'`; telefone e CPF por prefixo (quem tem a permissão). Busca de
  excluídos considera o valor antes do sufixo (o prefixo continua batendo).

## R-13. Recuperação de senha

- **Decisão**: tabela `clientes_codigos_recuperacao`. O código de 6 dígitos (`random_int`) é
  gravado com `Hash::make`, validade de 15 minutos, contador de tentativas e datas de uso e de
  invalidação. Um novo pedido invalida o código anterior. O limite de 1 pedido por minuto usa o
  `RateLimiter` (chave `codigo_pais+telefone`), como o login da spec 001. A resposta ao pedido é
  sempre a mesma (FR-023).
- **Motivo**: guardar o código em hash impede o uso de códigos lidos direto do banco.

## R-14. WhatsApp por eventos, registrado no log

- **Decisão**: eventos `App\Events\ClienteCadastrado` e `App\Events\CodigoRecuperacaoGerado`
  (implementam `ShouldDispatchAfterCommit`) e o listener `App\Listeners\RegistrarMensagemWhatsapp`,
  que só grava a mensagem no log da aplicação (`Log::info`) dentro de `try/catch`. Listeners em
  `app/Listeners` são descobertos automaticamente pelo Laravel 12.
- **Motivo**: a spec de WhatsApp só vai trocar o listener; o cadastro e a recuperação não mudam.

## R-15. Cadastro atômico e promoção de cadastro

- **Decisão**: o serviço `App\Services\CadastroClientes` cria, numa transação: o cliente, a cópia
  das travas padrão em `clientes_configuracoes` e, se `aceita_promocao`, um crédito por promoção
  de cadastro vigente (via `SaldoClientes`, origem `promocao`, `referencia_id` = id da promoção).
  O evento `ClienteCadastrado` sai depois do commit.
- **Sobreposição de promoções (FR-063)**: ao salvar uma promoção ativa de gatilho `cadastro`, o
  controller procura outra ativa, não excluída, da mesma modalidade, com período que se sobrepõe
  (`inicio_a <= fim_b` e `inicio_b <= fim_a`, fim nulo = sem fim) e recusa com 422.

## R-16. Paginação e ordenação

- **Decisão**: parâmetro `por_pagina` de 1 a 100, padrão 20 (listagem de clientes, extratos e
  promoções), como na spec 001. Ordenação da listagem: `ordenar_por` (`nome`, `created_at`,
  `saldo`) e `direcao` (`asc`, `desc`).

## R-17. Banco local

- **Decisão**: só migrations novas, aplicadas com `php artisan migrate`, e o seeder novo com
  `php artisan db:seed --class=ClientesSeeder`. Nenhuma tabela existente muda, e não há
  `migrate:refresh` nem `migrate:fresh` (o banco local é compartilhado com o legado).

## R-18. Validação sem testes automatizados

- **Decisão**: nenhum arquivo em `tests/`; validação manual pelo [quickstart.md](quickstart.md)
  e pela coleção do Postman regenerada (constituição).
