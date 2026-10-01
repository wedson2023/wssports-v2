# Research: Confrontos (jogos e cotações do provedor)

**Feature**: `003-confrontos` | **Data**: 2026-10-01 | **Plano**: [plan.md](plan.md)

Decisões técnicas da feature. Não há itens `NEEDS CLARIFICATION` pendentes: as dúvidas de negócio
foram resolvidas nas sessões de Clarifications da [spec](spec.md). O formato das respostas dos
provedores foi definido aqui ([contracts/provedor.md](contracts/provedor.md)), porque a API do
provedor é do próprio responsável e será ajustada a ele.

## R-01. Cotações numa coluna JSON (medido)

- **Decisão**: `confrontos.cotacoes` e `confrontos_ao_vivo.cotacoes` são colunas `json` com um
  objeto `{"odd1": 1.40, "odd3": 2.75}` só com os códigos diferentes de zero. Código ausente vale
  zero (FR-003). A mesma ideia vale para as regras: `valores` nas tabelas de porcentagem e `tetos`
  em `confrontos_teto_cotacoes`.
- **Medição** (2026-09-30, MySQL 8.4 local, dados reais do `database.sql`: 7.257 campeonatos,
  3.889 confrontos, 157.780 cotações diferentes de zero, 23.196 jogadores):

  | Formato | Gravar (inserir / atualizar) | Listagem de 220 jogos | Detalhe de 1 jogo |
  |---|---|---|---|
  | 323 colunas | 1,1 s / 1,4 s | 1,8 ms | 0,55 ms |
  | uma linha por cotação | 1,4 s / 1,3 s | 9,8 ms | 0,41 ms |
  | **coluna JSON** | **0,3 s / 0,3 s** | 2,2 ms | 0,14 ms |

  JSON do provedor só com cotações diferentes de zero: 5,8 MB (0,8 MB com gzip), lido em 67 ms.
  Carga completa (campeonatos + confrontos + jogadores) abaixo de 1 s, longe dos 5 s de SC-001.
- **Motivo**: gravação 4× mais rápida, nenhuma das 323 cotações fica de fora e a tabela não tem 323
  colunas. A listagem só precisa de `odd1` a `odd4` de no máximo 100 linhas por página, lidas no
  PHP.
- **Alternativas**: 323 colunas (como o antigo; mais lento para gravar e regras com 324 colunas
  cada); uma linha por cotação (mais lento para listar e 160 mil linhas reescritas a cada carga).

## R-02. Gravação em lote, numa transação, sem SQL montado por texto

- **Decisão**: serviço `App\Services\ImportacaoPreJogo`. Valida o JSON, monta os arrays e grava com
  `DB::table(...)->upsert($linhas, ['codigo_externo'], $colunas_atualizadas)` em lotes de 500
  (bindings do PDO, FR-054), tudo dentro de um `DB::transaction` (FR-005). Ordem: campeonatos →
  mapa `codigo_externo → id` (uma consulta) → confrontos → mapa → jogadores.
- **Colunas atualizadas**: o upsert de campeonatos atualiza só `nome`, `pais`, `bandeira`,
  `updated_at` (nunca `ativo`, `favorito`, `manual`, FR-006); o de confrontos não atualiza `ativo`
  nem `manual`. Registros manuais têm `codigo_externo` nulo e nunca casam com o upsert (FR-064).
- **Jogadores (FR-010)**: upsert por (`confrontos_id`, `codigo_externo`, `tipo`) com
  `deleted_at = null`; em seguida, soft delete dos jogadores dos confrontos da carga que não vieram
  (`updated_at` anterior ao início da carga). Assim a tabela não cresce sem limite e não há
  exclusão física.
- **Validação**: estrutura de topo inválida (sem `campeonatos`, tipo errado) recusa a carga inteira
  (FR-011). Cada confronto é validado por uma classe simples (`App\Services\ValidacaoCargaProvedor`),
  sem o `Validator` do Laravel, que seria lento para 4 mil itens com até 323 chaves: código fora de
  `odd1`–`odd323`, valor não numérico ou negativo, data inválida → confronto ignorado e motivo no
  log, sem derrubar os demais.

## R-03. Sorteio de `odd4` e `odd7` (FR-008)

- **Decisão**: antes do upsert, uma consulta traz, para os `codigo_externo` da carga, os valores
  atuais de `odd4`/`odd7` (`cotacoes->'$.odd4'`) e as marcações `odd4_sorteada`/`odd7_sorteada`.
  Para confronto de futebol (`esporte = 'FUTEBOL'`), não manual:
  - provedor mandou valor > 0 → vale o do provedor e a marcação vira `false`;
  - provedor mandou zero e já havia valor sorteado → mantém o valor e a marcação `true`;
  - provedor mandou zero e não havia sorteio → sorteia em centésimos com `random_int` dentro do
    intervalo de `configuracoes` e marca `true`; intervalo vazio ou inválido (mínimo > máximo) →
    continua zero.
- **Motivo**: a cotação não muda a cada 5 minutos sem motivo (Clarifications 2026-10-01).

## R-04. Herança de campeonato por nome e país (FR-007)

- **Decisão**: dentro da transação, antes do upsert, procura campeonatos da carga com
  `codigo_externo` ainda inexistente cujo (`nome`, `pais`) já existe num campeonato do provedor
  (não manual) com outro `codigo_externo`. Depois do upsert, move `campeonatos_id` do antigo para o
  novo em `porcentagens_campeonatos` e `campeonatos_nao_permitidos` (um `UPDATE` por tabela). O
  campeonato antigo fica como está.

## R-05. Cliente HTTP dos provedores

- **Decisão**: `App\Services\ProvedorCotacoes`, com o `Http` do Laravel (Guzzle, já instalado):
  - endereços e chave em `config/services.php` → `provedor_cotacoes`, lidos de variáveis de
    ambiente (`PROVEDOR_COTACOES_URL_PRE_JOGO`, `..._URL_AO_VIVO`, `..._URL_CONFERENCIA`,
    `PROVEDOR_COTACOES_CHAVE`, `PROVEDOR_COTACOES_CABECALHO_CHAVE` com padrão `X-Api-Key`);
    nunca `env()` fora do arquivo de config (funciona com `config:cache`);
  - chave sempre em cabeçalho, nunca na URL nem no log (FR-053); `Accept-Encoding: gzip`
    (descompressão automática do Guzzle, FR-056);
  - tempo limite: 60 s no pré-jogo, 4 s no ao vivo (cabe no ciclo de 5 s), 10 s na conferência;
  - qualquer exceção ou status ≠ 2xx vira `App\Exceptions\FalhaProvedorException`, registrada no
    log sem a chave.
- **Alternativas**: Guzzle direto (mais código); SDK próprio (desnecessário).

## R-06. Agendamento

- **Decisão**: em `routes/console.php` (Laravel 12):
  - `confrontos:importar` → `everyFiveMinutes()->withoutOverlapping(10)`;
  - `confrontos_ao_vivo:importar` → `everyFiveSeconds()->withoutOverlapping(1)`;
  - `confrontos_ao_vivo:conferir` → `everyMinute()->withoutOverlapping(2)`.
  As travas de sobreposição usam o cache `database` já configurado. Em produção, o cron chama
  `php artisan schedule:run` a cada minuto (o Laravel repete as tarefas de segundos dentro do
  minuto); localmente, `php artisan schedule:work`.
- **Checagens no início de cada comando**: `somente_cassino` → não roda (FR-012); no ao vivo e na
  conferência, `ao_vivo_habilitado` desmarcado em `configuracoes` → não roda (FR-046c).
- **Comandos** em `app/Console/Commands/` (descobertos automaticamente pelo Laravel 12, sem mexer
  em `bootstrap/app.php`).

## R-07. Carga do ao vivo e trava por tempo (FR-014 a FR-020)

- **Decisão**: `App\Services\ImportacaoAoVivo` faz upsert em `confrontos_ao_vivo` por
  `codigo_externo` e grava `ultima_atualizacao_em = now()` só para os jogos que vieram válidos.
  `campeonatos_id` e `confrontos_id` são resolvidos pelo `codigo_externo` (uma consulta cada); jogo
  com campeonato desconhecido é ignorado e vai para o log. Resposta vazia ou falha: nada é gravado.
- **Trava calculada na leitura**: um jogo está travado quando `configuracoes.ao_vivo_travado` é
  verdadeiro **ou** `now() - ultima_atualizacao_em > segundos_trava_ao_vivo`. Travado → todas as
  cotações saem zeradas e `travado: true`. Nada precisa gravar zeros, então a trava funciona mesmo
  com o comando parado (FR-017, SC-004).
- **Permanência**: jogos com `ultima_atualizacao_em` mais antiga que `minutos_permanencia_ao_vivo`
  saem da listagem (FR-020).

## R-08. Conferência do ao vivo (FR-021 a FR-024)

- **Decisão**: `App\Services\ConferenciaAoVivo` sorteia (`inRandomOrder()->first()`) um jogo em
  andamento atualizado dentro da permanência, pede o minuto ao segundo provedor e compara. Se o
  minuto do provedor for maior que o do sistema em mais de 1, grava `ao_vivo_travado = true` e
  `ao_vivo_travado_em`; se a diferença for ≤ 1 e estava travado, libera. Cada mudança vai para o log
  com o jogo e os dois minutos. Sem jogo ou com falha do provedor: nada muda.

## R-09. Quem está vendo a listagem pública

- **Decisão**: `App\Services\IdentificacaoPublico` lê o `Authorization: Bearer` (se houver) e tenta,
  nesta ordem, o guard `clientes` e o guard `api`. Os dois usam JWT com `lock_subject`, então um
  token de cliente nunca vale no guard do painel e vice-versa. Aplica as mesmas regras de acesso
  dos middlewares existentes (`Clientes::pode_acessar()` + `tokens_validos_desde`;
  `Usuarios::pode_acessar()`). Devolve um objeto `App\Services\Publico` com o tipo (Visitante,
  Cliente, Vendedor, Gestor), o cliente ou o usuário e `token_recusado`. Token inválido, expirado
  ou recusado → visitante com `token_recusado = true` (FR-036). Nenhuma exceção vaza.
- **Por que não middleware `auth`**: a rota precisa funcionar sem token.

## R-10. Cálculo da cotação (FR-047 a FR-051)

- **Decisão**: `App\Services\CalculoCotacoes` recebe o público, o tipo (pré-jogo ou ao vivo) e os
  confrontos da página, e carrega as regras com **uma consulta por tabela** (não por confronto):
  - porcentagem do público: vendedores → linhas de `porcentagens_vendedores[_ao_vivo]` do usuário e
    de todos os superiores (`Usuarios::ids_hierarquia_acima()`, novo); clientes e visitantes → a
    regra geral de `porcentagens_clientes[_ao_vivo]` mais a do cliente logado;
  - porcentagens de campeonato e valores fixos de confronto dos itens da página, filtrados pelos
    alvos que valem para o público (Todos; Clientes para visitante e cliente; Vendedores com dono
    na cadeia do usuário);
  - teto: o registro único de `confrontos_teto_cotacoes`; no ao vivo, também a cotação máxima
    (configuração do vendedor ou `configuracoes`).
- **Fórmula** por código: `base + base × soma_porcentagens / 100 + soma_valores_fixos`; depois
  `min(teto)`; depois `max(1.00)`; arredonda em 2 casas (`round`, metade para cima). Base zero (ou
  jogo travado) → 0, sem ajuste (FR-048). Os valores intermediários nunca saem na resposta
  (FR-052).
- **Motivo**: o cálculo do antigo fazia uma consulta por confronto e por código; aqui são no máximo
  6 consultas por página.

## R-11. Listagem pública

- **Decisão**: rota única `GET /api/publico/confrontos`, filtro `tipo` (`pre_jogo` | `ao_vivo`).
  `App\Services\ListagemConfrontos` monta a consulta com o Query Builder:
  - **dia** (`hoje` | `amanha` | `depois_de_amanha`): o início e o fim do dia são calculados com
    Carbon no fuso pedido e convertidos para UTC antes da consulta (`whereBetween('data_inicio')`),
    sem `CONVERT_TZ` no SQL; `hoje` começa em `now()`. Com `busca`, a janela vai de `now()` ao fim
    de depois de amanhã (FR-039, SC-011);
  - **não permitidos**: `whereNotExists` com subconsultas parametrizadas para os alvos do público;
  - **esportes**: `whereIn('esporte', ...)` com a configuração do público (FR-041, FR-074);
  - ordenação: `campeonatos.favorito desc`, `campeonatos.nome`, `data_inicio`, `time_casa`;
  - paginação de confrontos: `por_pagina` 1–100, padrão 50; o agrupamento por campeonato é feito
    sobre a página;
  - **países**: uma segunda consulta agregada (mesmos filtros, sem paginação) com a contagem por
    campeonato (FR-042).
- **Fuso**: só o formato `±HH:MM` entre `-12:00` e `+14:00`, validado por regex (o fuso nunca entra
  no SQL). Datas na resposta em ISO 8601 com o deslocamento pedido.
- **Limite de requisições**: 120 por minuto por IP com `RateLimiter` no `ListagemPublicaRequest`,
  como os limites da spec 002 (mensagem em português e `429`).
- **Índices**: `confrontos (situacao, esporte, data_inicio)` e `confrontos_ao_vivo (situacao,
  ultima_atualizacao_em)`; com a janela de 3 dias, a busca `LIKE '%x%'` por time percorre poucas
  linhas.
- **Ao vivo**: só jogos com `confrontos_id` de confronto ativo (FR-046a), `minuto` ≤ minuto limite
  do público (FR-046b), dentro da permanência; `ao_vivo_habilitado` desmarcado no sistema, no
  visitante ou no vendedor → `403` "O ao vivo não está disponível." (FR-046c).

## R-12. Regras sem dono e chaves únicas com valores nulos

- **Decisão**: nas tabelas com alvo, `usuarios_id` e `clientes_id` são nulos quando não se aplicam.
  Como o MySQL não considera dois `NULL` iguais num índice único, cada tabela ganha colunas geradas
  (`chave_usuario = COALESCE(usuarios_id, 0)`, `chave_cliente = COALESCE(clientes_id, 0)`) e o índice
  único usa elas. Em `porcentagens_clientes[_ao_vivo]`, o mesmo para a regra geral (`clientes_id`
  nulo).
- **Desmarcar e marcar de novo**: desmarcar um não permitido é soft delete; marcar de novo
  restaura o registro (`withTrashed()->firstOrNew()` + `restore()`), sem duplicar (FR-071).

## R-13. Não permitido do ao vivo ligado ao confronto do pré-jogo

- **Decisão**: `confrontos_ao_vivo_nao_permitidos.confrontos_id` aponta para `confrontos` (o jogo
  da grade), e não para a linha de `confrontos_ao_vivo`.
- **Motivo**: dá para esconder um jogo do ao vivo antes de ele começar, e todo jogo do ao vivo já
  precisa ter um confronto do pré-jogo (FR-046a). A tabela continua separada da restrição do
  pré-jogo (FR-032).

## R-14. Rotas de alteração das regras

- **Decisão**: alteração parcial por `PATCH` com dois formatos aceitos no mesmo corpo:
  - `valores`: objeto com só os códigos alterados (`{"odd1": -5, "jogador": -2}`), mesclado aos
    valores atuais;
  - `todos`: um número aplicado aos 324 códigos de uma vez (alteração geral, FR-059), que substitui
    os valores anteriores.
  Porcentagens de −100 a 100 (2 casas); tetos ≥ 1,00 (FR-034). Valor 0 numa porcentagem remove o
  código do JSON.
- **Cotação de um confronto (FR-060)**: o usuário manda `cotacoes: {"odd1": 1.95}` e o serviço
  grava a diferença para a cotação atual do provedor; código com base zero → `422`.
- **Alcance**: checagens em `App\Services\AlcanceHierarquia` (dono informado = o próprio usuário ou
  alguém da sub-hierarquia dele, `Usuarios::gerencia()`; alvos Clientes e Todos só para Admin e
  Supervisor), FR-061 e FR-071.

## R-15. Permissões (Princípio VI)

- **Decisão**: as 21 permissões ficam no `App\Enums\Funcao`, no mesmo padrão das de usuários e de
  clientes: constantes `PERMISSOES_CONFRONTOS` e `PERMISSOES_CONFRONTOS_GERENTE` (as 9 que o
  Gerente recebe e pode usar). `permissoes_padrao()` passa a incluí-las e `pode_usar()` restringe:
  Admin e Supervisor usam todas; Gerente só as 9; Vendedor nenhuma. Os controllers usam o trait
  existente `GarantirPermissaoCliente` (que já chama `Funcao::usuario_pode()` e serve para qualquer
  permissão; o nome não muda para não mexer em código da spec 002).
- **Distribuição**: o `PapeisPermissoesSeeder` cria as permissões; o `ConfrontosSeeder` as dá aos
  usuários existentes conforme a função.
- **Remoção**: `clientes.editar_configuracoes_padrao` sai do `Funcao` e do banco (FR-079).

## R-16. Configurações do vendedor e alteração em massa (FR-073 a FR-078)

- **Decisão**: `App\Services\ConfiguracoesVendedores`:
  - `criar_para(Usuarios $vendedor)`: copia a configuração de outro vendedor do mesmo gerente; se
    não houver, de um vendedor da mesma supervisão; senão, cria só com `usuarios_id` (valores padrão
    das colunas). Chamado no `UsuariosController::store` quando a função é Vendedor, dentro da
    transação já existente;
  - `alterar(Usuarios $solicitante, Usuarios $alvo, array $campos)`: valida o alcance (Gerente →
    vendedor dele; Supervisor → gerente ou vendedor dele; Admin → supervisor, gerente ou vendedor
    abaixo dele), resolve os vendedores (o alvo, se for vendedor; senão os vendedores da
    sub-hierarquia), cria as linhas que faltarem e grava **só os campos enviados** com um único
    `UPDATE ... WHERE usuarios_id IN (...)` (SC-016).
- **Consulta**: `GET /api/usuarios-configuracoes?gerente_id=` lista os vendedores do gerente com as
  configurações; quem não tem linha aparece com os valores padrão.

## R-17. Remoção da tabela padrão dos clientes (FR-079 e FR-080)

- **Decisão**: migration nova que (1) define como valor padrão das colunas de
  `clientes_configuracoes` os valores do FR-050 da spec 002 (inclusive
  `esportes_permitidos` = `JSON_ARRAY('FUTEBOL','HOQUEI NO GELO','BAISEBOL')`, aceito pelo MySQL
  8.4) e (2) apaga a tabela `clientes_configuracoes_padrao`. O `down()` recria a tabela e o registro.
- **Código**: `CadastroClientes` e `ClientesFactory` criam a configuração só com `clientes_id` e
  `aceita_promocao` e recarregam o registro (valores do banco); saem o model
  `ClientesConfiguracoesPadrao`, o `ClientesConfiguracoesPadraoController`, as 2 rotas e a criação
  do padrão no `ClientesSeeder`. A migration antiga da tabela padrão fica no histórico.
- **Exclusão física justificada**: apagar a tabela e a permissão é a remoção decidida pelo
  responsável; nada de cliente é perdido (cada cliente já tem a sua cópia).
- **Risco aceito**: se o padrão tiver sido editado no banco local, essas edições não viram os
  valores padrão das colunas (valem os do FR-050 da spec 002).

## R-18. Enums e valores gravados

| Enum | Casos → valores |
|---|---|
| `AlvoRegra` | `Clientes`, `Vendedores`, `Todos` |
| `SituacaoConfronto` | `Aguardando`, `Encerrado`, `Cancelado`, `Adiado` |
| `SituacaoAoVivo` | `PrimeiroTempo` → `'1 tempo'`, `Intervalo`, `SegundoTempo` → `'2 tempo'` |

- Colunas `varchar`, sem `ENUM` do MySQL (como na spec 002). Os valores de situação seguem o
  provedor e o sistema antigo. Esporte é texto livre como o provedor envia (`FUTEBOL`), sem enum,
  porque o cadastro de esportes não existe.
- Códigos de cotação: classe `App\Support\CodigosCotacao` com a lista `odd1`…`odd323` e `jogador`
  (não é enum: seriam 324 casos).

## R-19. Datas em UTC

- **Decisão**: `config/app.php` já está em `UTC`; o provedor manda `data_inicio` em UTC; toda
  conversão de fuso acontece só na leitura (listagem) e na entrada do confronto manual (data
  informada no fuso de quem cadastra, FR-063b).

## R-20. Banco local

- **Decisão**: só `php artisan migrate` e `php artisan db:seed --class=ConfrontosSeeder`. Sem
  `migrate:refresh`/`migrate:fresh` (banco compartilhado com o legado), como nas specs anteriores.

## R-21. Validação sem testes automatizados

- **Decisão**: nenhum arquivo em `tests/`; validação manual pelo [quickstart.md](quickstart.md),
  com o provedor simulado por um mock server do Postman a partir dos exemplos de
  [contracts/provedor.md](contracts/provedor.md), e pela coleção do Postman regenerada.
