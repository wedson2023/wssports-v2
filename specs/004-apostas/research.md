# Research: Apostas (criação, validação de código e cancelamento)

**Feature**: `004-apostas` | **Data**: 2026-10-07 | **Plano**: [plan.md](plan.md)

Decisões técnicas da feature. Não há itens `NEEDS CLARIFICATION` pendentes: as dúvidas de negócio
foram resolvidas nas sessões de Clarifications da [spec](spec.md). Os pontos que alteram código
já implementado estão marcados e listados no [plan.md](plan.md) (Princípio IV).

## R-01. Delay do ao vivo por job atrasado na fila `database`

- **Decisão**: a aposta com jogo ao vivo é gravada como `Em análise` e o controller despacha o job
  `DecidirApostaAoVivo` com `->delay(now()->addSeconds($delay_ao_vivo))` na fila `database` (já
  configurada: `QUEUE_CONNECTION=database` e tabela `jobs` existentes). A resposta HTTP volta na
  hora com `situacao` e `segundos_restantes`; o front consulta `GET .../apostas/{codigo}/situacao`
  a cada 2 s. O job é idempotente: trava a aposta (`lockForUpdate`) e só age se ela ainda estiver
  `Em análise`.
- **Rede de segurança**: o comando `apostas:recusar_analises_presas` roda a cada minuto e recusa
  as apostas `Em análise` com `recebida_em + delay + 60 s` no passado (FR-035), com o motivo "Não
  foi possível concluir a análise".
- **Operação**: exige um worker (`php artisan queue:work --queue=apostas,default`), já previsto no
  `composer dev` (`queue:listen`). A fila `apostas` é separada para que jobs lentos de outras
  features (ex.: `EstornarPromocao`) não atrasem a decisão.
- **Motivo**: nenhum `sleep` na requisição (o antigo prendia um worker do PHP por até 25 s); o
  delay corre uma única vez e a decisão é do servidor (SC-002, SC-006).
- **Alternativas**: `sleep` na requisição (rejeitado: bloqueia workers e permite reenvio com atalho
  de cache, como no antigo); comando agendado a cada 5 s procurando análises vencidas (rejeitado:
  atraso de até 5 s a mais e consulta constante; fica só como rede de segurança).

## R-02. Decisão do ao vivo: fotografia do jogo no envio e na decisão

- **Decisão**: no envio, cada palpite ao vivo guarda em `dados_ao_vivo_envio` (JSON) o placar, os
  gols por tempo, os escanteios, o minuto, a situação e `ultima_atualizacao_em` do jogo. Na decisão,
  o job relê o jogo e grava `dados_ao_vivo_decisao`. Recusa (FR-034a) quando:
  - qualquer campo de lance (placar, gols por tempo, escanteios, situação) mudou;
  - `ultima_atualizacao_em` da decisão não é posterior a `recebida_em` (sem dado novo);
  - o jogo saiu das situações em andamento (`SituacaoAoVivo`), passou do minuto limite, está
    travado por tempo (`segundos_trava_ao_vivo`) ou a trava geral está ligada;
  - a cotação ficou indisponível ou caiu fora da preferência.
- **Motivo**: o atraso do provedor faz o evento aparecer depois; exigir uma atualização posterior
  ao envio garante que o dado comparado já inclui o que aconteceu no momento da aposta.

## R-03. Cálculo de cotação: generalizar `CalculoCotacoes` (altera código da spec 003)

- **Decisão**: `CalculoCotacoes::ajustar()` hoje calcula só `odd1`…`odd4` (`CODIGOS_LISTAGEM`) e
  não calcula jogadores. Passa a aceitar a lista de códigos (`ajustar(..., array $codigos =
  self::CODIGOS_LISTAGEM)`) e ganha `ajustar_jogadores()`, que aplica ao `odd` do jogador as
  porcentagens do código `jogador` (público + campeonato) e o teto `jogador`. A listagem continua
  chamando com o padrão, sem mudança de comportamento.
- **Motivo**: FR-007 exige a mesma cotação da listagem para qualquer código apostado. Uma cópia do
  cálculo em outro serviço divergiria com o tempo.
- **Detalhe do confronto (Clarifications 2026-10-07)**: a listagem pública continua só com
  `odd1`…`odd4`. As demais cotações e os jogadores saem de duas rotas públicas de detalhe
  (`GET /publico/confrontos/{confronto}` e `GET /publico/confrontos-ao-vivo/{confronto_ao_vivo}`),
  num serviço novo `DetalheConfrontos`, que usa o mesmo `CalculoCotacoes` e o mesmo
  `RegrasExibicao`. Assim, a cotação mostrada no detalhe é a mesma conferida na aposta.
- **Alternativa rejeitada**: devolver as 323 cotações e os jogadores de cada jogo na listagem
  (até 100 jogos por página): resposta muito maior em toda navegação, quando o apostador só
  precisa delas ao abrir um jogo.
- **Cotação zerada**: continua zerada (indisponível) e recusa o palpite; o piso de 1,00 do cálculo
  só vale para cotação disponível ajustada abaixo de 1, que é a mesma exibida na listagem. Nunca há
  troca de cotação bloqueada por 1,00 (FR-027).

## R-04. Regras de exibição compartilhadas entre listagem e aposta (altera código da spec 003)

- **Decisão**: os métodos privados de `ListagemConfrontos` que decidem o que o público vê
  (`esportes_permitidos`, `excluir_nao_permitidos`, `garantir_ao_vivo_habilitado`) passam para um
  serviço novo `RegrasExibicao`, usado pela listagem e pela aposta. O serviço também concentra as
  regras novas: período de jogos (FR-018), data de travamento (FR-019), jogo no ao vivo fora do
  pré-jogo (FR-017) e `apostar_jogadores`.
- **Na listagem (FR-065, FR-017)**: a janela do pré-jogo é limitada pelo fim do período do público
  e pela data de travamento; com a trava passada, a listagem devolve vazia; o pré-jogo exclui
  confrontos com registro ativo em `confrontos_ao_vivo` (situação em andamento). A listagem não
  mostra jogadores (detalhe fora do escopo), então `apostar_jogadores` só tem efeito na aposta.
- **Motivo**: FR-008 e SC-015 exigem que o apostável seja exatamente o exibido; duas
  implementações das mesmas regras divergiriam.

## R-05. Fuso do sistema

- **Decisão**: o "dia" do valor máximo diário, do período de jogos e das validades usa o fuso
  `-03:00` (Brasília), o mesmo padrão da listagem (`ListagemConfrontos::FUSO_PADRAO`), definido
  numa constante compartilhada `App\Support\FusoSistema`. O banco continua em UTC.
- **Motivo**: `config('app.timezone')` é `UTC`; o negócio opera no horário de Brasília.

## R-06. Dinheiro e cotações sem ponto flutuante (bcmath)

- **Decisão**: valores em centavos inteiros com `SaldoClientes::para_centavos`/`para_decimal`
  (spec 002). Cotações como texto com 2 casas. Produto das cotações e prêmio com `bcmath` (extensão
  disponível no PHP local), com escala de 10 casas no intermediário:
  - `cotacao_total` = produto das cotações, arredondado em 2 casas (meio para cima) só para gravar e
    exibir;
  - `premio_bruto` = valor × produto exato, truncado em centavos (nunca arredonda a favor do
    apostador);
  - multiplicador, prêmio máximo e acréscimo (FR-025) em centavos inteiros.
- **Motivo**: o antigo usava `float` e comparava prêmios com `number_format`; o produto de até 20
  cotações em float acumula erro.
- **Alternativas**: inteiros em centésimos (rejeitado: o produto de 20 cotações estoura 64 bits).

## R-07. Concorrência: ordem fixa de bloqueios

- **Decisão**: toda operação que move dinheiro ou limite roda num `DB::transaction` com
  `lockForUpdate` nesta ordem, para não haver deadlock:
  1. a aposta (validação, decisão do ao vivo, cancelamento e edição);
  2. a linha de `confrontos` / `confrontos_ao_vivo` de cada palpite, em ordem de id (limite por
     confronto: soma das apostas Ativas e Em análise lida depois do bloqueio);
  3. a configuração do vendedor (`usuarios_configuracoes`, limites de venda) ou o cliente
     (`clientes`, saldo e valor diário), este pelo próprio `SaldoClientes`;
  4. os registros de `clientes_rollovers` pendentes, em ordem de id.
- **Uma análise por apostador (FR-032)**: conferida depois do bloqueio da configuração do vendedor
  ou da linha do cliente.
- **Motivo**: SC-007, SC-008, SC-011; o antigo lia e gravava saldo e limites sem bloqueio.

## R-08. Idempotência

- **Decisão**: `chave_idempotencia` (UUID obrigatório no corpo) com índice único em `apostas`. Um
  envio repetido com a mesma chave e o mesmo apostador devolve a aposta já gravada (200, mesmo
  corpo); a mesma chave de outro apostador devolve 422 ("Chave de idempotência já usada"). Recusas por regra
  (422) e pedidos de confirmação de cotação não gravam nada, então a chave pode ser reusada no
  reenvio.
- **Visitante** (sem identidade): a aposta existente só é devolvida se ainda estiver Pendente e
  tiver sido criada pelo mesmo IP; nos demais casos, 422.
- **Validação do código**: a chave também é obrigatória e fica em `apostas.chave_validacao`; a
  repetição pelo mesmo vendedor com a mesma chave devolve o comprovante já validado (200), em vez
  de "já validada".
- **Motivo**: duplo clique e reenvio de rede (FR-009, SC-008).

## R-09. Código da aposta

- **Decisão**: 8 caracteres do alfabeto `ABCDEFGHJKLMNPQRSTUVWXYZ23456789` (32 símbolos, sem 0, O,
  1 e I), sorteados com `random_int`; índice único; nova tentativa em caso de colisão (até 5). Todo
  apostador recebe código (não só o visitante).
- **Força**: 32⁸ ≈ 1,1 trilhão de combinações; com o limite de 60 consultas/min por IP e 20 por
  vendedor, encontrar um código pendente por tentativa é inviável (SC-012).

## R-10. Limites de tentativas (`RateLimiter`)

- **Decisão**: `RateLimiter::attempt` com mensagem em português (padrão do `StoreClientesRequest`
  da spec 002):
  - criação de aposta do visitante: 10/min por IP;
  - consulta pública por código e por lista de códigos: 60/min por IP;
  - consulta e validação de código pelo vendedor: 20/min por vendedor e 60/min por IP;
  - acompanhamento da situação (`/situacao`): 60/min por apostador.
- **Motivo**: FR-042; valores iniciais das Premissas da spec.

## R-11. Assinatura da aposta (HMAC)

- **Decisão**: `hash_hmac('sha256', $canonico, $chave)`, com `$chave = hash_hmac('sha256',
  'apostas', config('app.key'))` (chave derivada, não a `APP_KEY` direta). `$canonico` é o JSON
  com chaves ordenadas de: código, valor, cotação total, prêmio, acréscimo, data de confirmação e
  palpites ativos (confronto, código, jogador, tipo, cotação final). Gravada na confirmação e
  refeita a cada edição (FR-057, FR-052d). A conferência usa `hash_equals`.
- **Motivo**: substitui o `md5` + base64 do antigo, que não usava segredo.

## R-12. Máquina de estados da aposta

| De | Para | Quando |
|---|---|---|
| (nova) | Pendente | aposta do visitante |
| (nova) | Ativa | aposta do vendedor ou cliente só com pré-jogo |
| (nova) | Em análise | aposta com jogo ao vivo |
| Pendente | Ativa | validação pelo vendedor |
| Pendente | Expirada | primeiro jogo começou ou validade do código passou |
| Em análise | Ativa | decisão aceita |
| Em análise | Recusada | decisão recusada ou análise presa |
| Ativa | Cancelada | cancelamento (só com resultado Aguardando) |

`resultado` nasce `Aguardando` e só muda na spec de apuração. Nenhuma outra transição é aceita.

## R-13. Rollover: `clientes_rollovers` + ligação `apostas_rollovers`

- **Decisão**: `clientes_rollovers` guarda um registro por crédito com rollover (tipo Depósito ou
  Bônus). `apostas_rollovers` (ligação aposta × rollover, com o valor somado) permite desfazer o
  cancelamento exatamente (FR-048, FR-051). O valor apostado soma no pendente mais antigo do tipo
  (Depósito para saldo real; Bônus da modalidade Esportes para a Promoção esportes) e o excedente
  passa ao seguinte.
- **Crédito do bônus de Primeiro cadastro (altera código da spec 002)**: `CadastroClientes` cria o
  registro de rollover junto com o crédito, quando `rollover > 0`, copiando as regras de uso da
  promoção (valor mínimo e máximo de aposta e odds mínimas simples e múltipla).
- **Estorno da promoção (altera código da spec 002)**: o job `EstornarPromocao` passa a marcar
  `cancelado_em` nos rollovers pendentes da promoção estornada, para que as regras do bônus deixem
  de valer.
- **Nome**: `apostas_rollovers` segue o prefixo da tabela principal (`apostas`).

## R-14. Carteira do cliente

- **Decisão**: `EscolhaCarteira` decide com os saldos lidos depois do bloqueio do cliente: saldo
  real ≥ valor → `Saldo`; senão `saldo_promocao_esportes` ≥ valor → `Promoção esportes`; senão
  `SaldoInsuficienteException` → 422 "Você não tem saldo suficiente para realizar esta aposta".
  Nunca combina; nunca usa `Promoção cassino` (FR-043).

## R-15. Permissões (altera `Funcao`)

- **Decisão**: nova constante `PERMISSOES_APOSTAS` = `apostas.criar`, `apostas.validar`,
  `apostas.cancelar`, `apostas.cancelar_iniciada`, `apostas.editar`; e `confrontos.alterar_limite`
  em `PERMISSOES_CONFRONTOS` (só Admin e Supervisor, por ser um limite global do confronto).
  - `pode_usar`: `apostas.criar` e `apostas.validar` só Vendedor; `apostas.cancelar` todas as
    funções; `apostas.cancelar_iniciada` e `apostas.editar` Gerente, Supervisor e Admin.
  - `permissoes_padrao()`: hoje devolve `[]` para o Vendedor; passa a devolver
    `apostas.criar`, `apostas.validar` e `apostas.cancelar`. Gestores recebem também as de apostas
    que podem usar.
  - Usuários já existentes recebem as permissões novas pelo seeder `ApostasSeeder`.
- **Alcance** (`AlcanceApostas`): vendedor → só as próprias; Gerente/Supervisor/Admin → apostas
  cujo `usuarios_id` está na sub-hierarquia (Admin: todas); apostas de cliente → qualquer
  Gerente, Supervisor ou Admin com a permissão (Clarifications 2026-10-07). Fora do alcance → 404,
  como `GarantirGerencia`.

## R-16. Cotação vista e confirmação

- **Decisão**: cada palpite do pedido traz `cotacao_vista`. O serviço `ConferenciaCotacoes`
  compara com a atual pela preferência (FR-029) e devolve 409 com `alteracoes` (palpite, cotação
  vista, atual, novo prêmio) ou 422 com `indisponiveis`. Na validação do código, a cotação vista é
  a que a simulação devolveu ao vendedor (FR-039a).
- **Motivo**: 409 separa "precisa confirmar" de "erro de regra" (422), facilitando o front.

## R-17. Nomes legíveis dos mercados

- **Decisão**: `App\Support\NomesCotacoes` com o mapa `odd1`…`odd323` → nome (ex.: `odd1` →
  "Casa"), copiado de `ConfigController::categorias()` do sistema antigo, e "Jogador: tipo" para os
  palpites em jogador. Usado só no comprovante.

## R-18. Expiração de códigos e análises presas

- **Decisão**: dois comandos agendados a cada minuto, com `withoutOverlapping`:
  `apostas:expirar_pendentes` (FR-041) e `apostas:recusar_analises_presas` (FR-035). A validação e
  a consulta conferem a expiração na hora, além do comando.

## R-19. Edição de palpites

- **Decisão**: `apostas_palpites.situacao` (`Ativo`, `Cancelado`) com `cancelado_em`,
  `cancelado_por`, `restaurado_em` e `restaurado_por`. O recálculo usa as cotações finais dos
  palpites ativos e os valores de configuração gravados na aposta (FR-052b). Cada edição gera uma
  linha em `apostas_historico`. Valor, saldo, limites, comissão e rollover não mudam (FR-052c).
- **Motivo**: o antigo apagava o palpite (soft delete) sem histórico de quem fez.

## R-20. Banco compartilhado com o legado

- **Decisão**: só `php artisan migrate` e seeders, como nas specs 001 a 003 (R-20 da spec 003).
  Colunas novas em tabelas existentes entram com valor padrão, sem apagar dados.
