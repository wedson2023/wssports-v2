# Data Model: Encerrar aposta, bilhete em tempo real e notificações push

**Feature**: `010-encerrar-aposta-tempo-real` | **Data**: 2026-10-15

Todas as tabelas novas têm `id()`, `timestamps()` e `softDeletes()`, e o model usa `SoftDeletes`.
Toda coluna criada ou alterada tem `->comment()` com o texto da coluna **Comentário** abaixo
(constituição 3.3.0). Dinheiro em `decimal(15,2)`, percentuais em `decimal(5,2)` e cálculos em
`bcmath`.

## 1. `apostas_encerramentos` (nova)

Cada pedido de encerramento de uma aposta, aceito ou recusado (FR-035). Model
`ApostasEncerramentos`.

| Coluna | Tipo | Comentário |
|---|---|---|
| `apostas_id` | foreignId → `apostas` | Aposta encerrada (total ou em parte) |
| `clientes_id` | foreignId → `clientes` | Cliente dono da aposta |
| `usuarios_id` | foreignId → `usuarios`, nulo | Usuário do painel que encerrou em acesso assistido; nulo quando foi o cliente ou o sistema |
| `apostas_encerramentos_automaticos_id` | foreignId → `apostas_encerramentos_automaticos`, nulo | Encerramento automático que gerou este encerramento; nulo no manual |
| `modalidade` | string(20) | Modalidade: Total, Parcial ou Automático |
| `situacao` | string(20) | Situação: Em análise, Recusado, Registrado (aguardando a janela), Creditado ou Desfeito |
| `chave_idempotencia` | char(36), único | Chave do pedido gerada pela página; o reenvio devolve o mesmo registro |
| `aceitar_alteracoes` | string(20) | Preferência usada na decisão: Nenhuma, Somente para maior ou Qualquer |
| `oferta_exibida` | decimal(15,2) | Oferta total exibida ao cliente e assinada no token, em reais |
| `oferta` | decimal(15,2), nulo | Oferta total recalculada na decisão, em reais; nula enquanto Em análise |
| `valor_pedido` | decimal(15,2) | Valor que o cliente pediu para receber (no total, igual à oferta exibida), em reais |
| `valor` | decimal(15,2), nulo | Valor encerrado que será creditado, em reais; nulo enquanto Em análise ou quando Recusado |
| `margem` | decimal(5,2) | Margem da casa aplicada na oferta, em % (pré-jogo ou ao vivo) |
| `proporcao` | decimal(11,10), nulo | Parte da aposta encerrada neste pedido, de 0 a 1 (1 no total) |
| `fracao_antes` | decimal(11,10) | Fração da aposta que valia antes do encerramento (apostas.fracao_restante) |
| `fracao_depois` | decimal(11,10), nulo | Fração da aposta que continua valendo depois; 0 no total |
| `valor_apostado_antes` | decimal(15,2) | Valor apostado que valia antes (valor × fração), em reais |
| `valor_apostado_depois` | decimal(15,2), nulo | Valor apostado que continua valendo depois, em reais |
| `premio_antes` | decimal(15,2) | Total a pagar que valia antes (prêmio + acréscimo × fração), em reais |
| `premio_depois` | decimal(15,2), nulo | Total a pagar que continua valendo depois, em reais |
| `palpites` | json | Fotografia por palpite em aberto: id, cotação original, cotação atual, placar, período, minuto, última atualização do ao vivo e se o jogo tinha resultado gravado |
| `rollovers` | json, nulo | Valores retirados de cada rollover (clientes_rollovers_id e valor), para devolver se o encerramento for desfeito |
| `motivo_recusa` | string(255), nulo | Motivo da recusa (lance, oferta alterada, inelegível) |
| `decidido_em` | timestamp, nulo | Quando o pedido foi aceito ou recusado |
| `creditar_em` | timestamp, nulo | Quando o valor pode ser creditado (decisão + janela de pagamento da apuração) |
| `creditado_em` | timestamp, nulo | Quando o valor entrou no saldo |
| `clientes_transacoes_id` | foreignId → `clientes_transacoes`, nulo | Transação de crédito do valor encerrado |
| `desfeito_em` | timestamp, nulo | Quando o encerramento foi desfeito (FR-033) |
| `motivo_desfeito` | string(255), nulo | Por que o encerramento foi desfeito |
| `estorno_clientes_transacoes_id` | foreignId → `clientes_transacoes`, nulo | Transação de estorno quando o encerramento desfeito já tinha sido creditado |
| `ip` | string(45), nulo | IP do pedido; nulo no automático |
| `user_agent` | string(255), nulo | Aparelho (user agent) do pedido; nulo no automático |

**Índices**: `(apostas_id, situacao)`, `(situacao, creditar_em)` para a liquidação,
`(clientes_id, created_at)` e `(modalidade, created_at)` para a listagem.

**Transições de situação**:

```text
            ┌── (pré-jogo) ───────────────────────────┐
pedido ─────┤                                          ├─► Registrado ──► Creditado
            └── (ao vivo) Em análise ──► decisão ──────┤        │             │
                                                       └─► Recusado          ▼
                                    Registrado / Creditado ──(FR-033)──► Desfeito
```

**Regras**: no máximo um encerramento `Em análise` por aposta. `Recusado` não muda aposta, saldo
nem rollover. `Creditado` e `Desfeito` são finais.

## 2. `apostas_encerramentos_automaticos` (nova)

Valor-alvo programado pelo cliente (FR-030, FR-031). Model `ApostasEncerramentosAutomaticos`.

| Coluna | Tipo | Comentário |
|---|---|---|
| `apostas_id` | foreignId → `apostas` | Aposta com o encerramento automático |
| `clientes_id` | foreignId → `clientes` | Cliente dono da aposta |
| `usuarios_id` | foreignId → `usuarios`, nulo | Usuário do painel que programou em acesso assistido; nulo quando foi o cliente |
| `valor_alvo` | decimal(15,2) | Oferta que dispara o encerramento, em reais |
| `situacao` | string(20) | Situação: Ativo, Executado ou Cancelado |
| `executado_em` | timestamp, nulo | Quando a oferta alcançou o alvo e a aposta foi encerrada |
| `cancelado_em` | timestamp, nulo | Quando o automático foi desativado ou substituído |
| `motivo_cancelamento` | string(100), nulo | Por que foi cancelado: pelo cliente, alterado, encerramento manual ou aposta decidida |

**Índices**: `(situacao, apostas_id)`.

**Regras**: um único `Ativo` por aposta, garantido no serviço com a aposta travada. Alterar o
valor-alvo cancela o atual ("Alterado") e cria outro, para guardar o histórico.

## 3. `clientes_inscricoes_notificacoes` (nova)

Aparelho do cliente que aceitou receber avisos (FR-040, FR-041, FR-046). Model
`ClientesInscricoesNotificacoes`.

| Coluna | Tipo | Comentário |
|---|---|---|
| `clientes_id` | foreignId → `clientes` | Cliente dono da inscrição |
| `endpoint` | text | Endereço de entrega fornecido pelo navegador (Web Push) |
| `endpoint_hash` | char(64), único | SHA-256 do endpoint, para buscar e evitar inscrição repetida |
| `chave_p256dh` | string(255) | Chave pública do aparelho (p256dh) usada para criptografar o aviso |
| `chave_auth` | string(255) | Segredo de autenticação do aparelho (auth) |
| `codificacao` | string(20) | Codificação do conteúdo informada pelo navegador (aes128gcm ou aesgcm) |
| `aparelho` | string(255), nulo | Descrição do aparelho (user agent) para o cliente reconhecer |
| `receber_gols` | boolean, padrão true | Recebe avisos de gol e de gol anulado |
| `receber_intervalo` | boolean, padrão true | Recebe aviso de intervalo |
| `receber_fim_jogo` | boolean, padrão true | Recebe aviso de fim de jogo |
| `receber_resultado_aposta` | boolean, padrão true | Recebe aviso quando a aposta é decidida |
| `receber_encerramento_automatico` | boolean, padrão true | Recebe aviso quando um encerramento automático é executado |
| `ultimo_envio_em` | timestamp, nulo | Último aviso entregue ao serviço de push com sucesso |

**Índices**: `endpoint_hash` único, `clientes_id`.

**Regras**: o `PUT` grava pelo `endpoint_hash`. Se o hash já existir em outro cliente, a inscrição
passa para o cliente atual. Remover a inscrição (pelo cliente, por logout ou por 404/410 do serviço
de push) é soft delete.

## 4. `clientes_notificacoes` (nova)

Registro de cada aviso enviado a um cliente, que também impede envio repetido (SC-008). Model
`ClientesNotificacoes`.

| Coluna | Tipo | Comentário |
|---|---|---|
| `clientes_id` | foreignId → `clientes` | Cliente avisado |
| `tipo` | string(30) | Tipo do aviso: Gol, Gol anulado, Intervalo, Fim de jogo, Resultado da aposta ou Encerramento automático |
| `chave` | string(80) | Identificador do fato avisado (ex.: evento:15, aposta:9:Vencedor, encerramento:4), único por cliente |
| `confrontos_eventos_id` | foreignId → `confrontos_eventos`, nulo | Evento do jogo avisado |
| `apostas_id` | foreignId → `apostas`, nulo | Aposta aberta pelo link do aviso |
| `titulo` | string(120) | Título exibido no aviso |
| `corpo` | string(255), nulo | Texto exibido no aviso |
| `url` | string(255) | Endereço aberto ao tocar no aviso (bilhete em tempo real) |
| `aparelhos` | unsignedSmallInteger, padrão 0 | Quantidade de aparelhos a que o aviso foi entregue |
| `falhas` | unsignedSmallInteger, padrão 0 | Quantidade de aparelhos que recusaram o aviso |

**Índices**: único `(clientes_id, chave)`.

## 5. `confrontos_eventos` (nova)

Gol, gol anulado, intervalo e fim de jogo detectados (FR-042). Model `ConfrontosEventos`.

| Coluna | Tipo | Comentário |
|---|---|---|
| `confrontos_id` | foreignId → `confrontos` | Jogo da grade onde o evento aconteceu |
| `confrontos_ao_vivo_id` | foreignId → `confrontos_ao_vivo`, nulo | Jogo do ao vivo de onde o evento foi detectado; nulo no fim de jogo vindo do resultado |
| `tipo` | string(20) | Tipo: Gol, Gol anulado, Intervalo ou Fim de jogo |
| `placar_casa` | unsignedSmallInteger | Gols do time da casa depois do evento |
| `placar_fora` | unsignedSmallInteger | Gols do time de fora depois do evento |
| `minuto` | unsignedSmallInteger, nulo | Minuto do jogo no evento; nulo no fim de jogo |
| `chave` | string(40), nulo, único | Garante uma vez por jogo (Intervalo:{id}, Fim de jogo:{id}); nula nos gols |
| `notificado_em` | timestamp, nulo | Quando os avisos do evento foram despachados |

**Índices**: `chave` único, `(confrontos_id, created_at)`.

## 6. Alterações em tabelas existentes

### `apostas`

| Coluna | Tipo | Comentário |
|---|---|---|
| `fracao_restante` | decimal(11,10), padrão 1 | Parte da aposta que continua valendo depois de encerramentos parciais (1 = inteira, 0 = encerrada); multiplica o total a pagar |

`resultado` aceita `Encerrada` (coluna `varchar(20)`, sem mudança de tipo; o comentário passa a
citar os valores: Aguardando, Vencedor, Perdedor, Encerrada).

**Model `Apostas`**: cast `fracao_restante => 'decimal:10'`. `total_a_pagar()` passa a
`truncar((premio + valor_acrescido) × fracao_restante, 2)`. Métodos novos:
`valor_em_jogo()` (`truncar(valor × fracao_restante, 2)`) e as relações `encerramentos()` e
`encerramento_automatico()` (o `Ativo`).

### `configuracoes`

Colunas e padrões da R-18. Comentários:

| Coluna | Tipo | Comentário |
|---|---|---|
| `encerramento_habilitado` | boolean, padrão true | Liga o encerramento de aposta para os clientes |
| `encerramento_parcial_habilitado` | boolean, padrão true | Liga o encerramento parcial |
| `encerramento_automatico_habilitado` | boolean, padrão true | Liga o encerramento automático por valor-alvo |
| `encerramento_jogos_nao_iniciados` | boolean, padrão true | Permite encerrar antes de qualquer jogo da aposta começar |
| `margem_encerramento_pre_jogo` | decimal(5,2), padrão 7.00 | Margem da casa na oferta quando nenhum jogo em aberto começou, em % |
| `margem_encerramento_ao_vivo` | decimal(5,2), padrão 10.00 | Margem da casa na oferta quando algum jogo em aberto está ao vivo, em % |
| `valor_minimo_encerramento` | decimal(15,2), padrão 1.00 | Menor oferta (e menor parcial) aceita, em reais |
| `percentual_minimo_encerramento` | decimal(5,2), padrão 5.00 | Menor oferta aceita em % do valor apostado que continua valendo; vale o maior dos dois mínimos |
| `segundos_validade_oferta` | unsignedSmallInteger, padrão 8 | Validade da oferta na confirmação, em segundos |
| `limite_encerramentos_parciais` | unsignedSmallInteger, padrão 5 | Quantidade máxima de encerramentos parciais por aposta |

**Model `Configuracoes`**: `fillable` e casts das 10 colunas.

### `clientes_configuracoes`

| Coluna | Tipo | Comentário |
|---|---|---|
| `encerrar_aposta` | boolean, padrão true | Permite ao cliente encerrar as próprias apostas |

**Model `ClientesConfiguracoes`**: `encerrar_aposta` em `CAMPOS` e `CASTS`.

## 7. Enums

| Enum | Casos (valor gravado) | Situação |
|---|---|---|
| `ModalidadeEncerramento` | `Total`, `Parcial`, `Automático` | novo |
| `SituacaoEncerramento` | `EmAnálise` ('Em análise'), `Recusado`, `Registrado`, `Creditado`, `Desfeito` | novo |
| `SituacaoEncerramentoAutomatico` | `Ativo`, `Executado`, `Cancelado` | novo |
| `TipoEventoConfronto` | `Gol`, `GolAnulado` ('Gol anulado'), `Intervalo`, `FimDeJogo` ('Fim de jogo') | novo |
| `TipoNotificacao` | `Gol`, `GolAnulado`, `Intervalo`, `FimDeJogo`, `ResultadoAposta` ('Resultado da aposta'), `EncerramentoAutomático` ('Encerramento automático'); método `coluna_preferencia()` → `receber_*` | novo |
| `ResultadoAposta` | + `Encerrada` | alterado |
| `OrigemTransacao` | + `EncerramentoAposta` ('Encerramento de aposta') | alterado |
| `TipoOperacaoApuracao` | + `EncerramentoDesfeito` ('Encerramento desfeito') | alterado |

## 8. Relações

```text
apostas 1 ── N apostas_encerramentos N ── 1 clientes
apostas 1 ── N apostas_encerramentos_automaticos (no máximo 1 Ativo)
apostas_encerramentos_automaticos 1 ── 0..1 apostas_encerramentos
apostas_encerramentos N ── 0..1 clientes_transacoes (crédito) / 0..1 (estorno)
clientes 1 ── N clientes_inscricoes_notificacoes
clientes 1 ── N clientes_notificacoes N ── 0..1 confrontos_eventos
confrontos 1 ── N confrontos_eventos
```

## 9. Valores derivados (não gravados)

| Valor | Fórmula | Onde |
|---|---|---|
| Valor apostado em jogo | `truncar(valor × fracao_restante, 2)` | bilhete, encerramento |
| Prêmio potencial | `total_a_pagar()` (R-07) | bilhete, liquidação |
| Oferta | R-04 | `CalculoEncerramento` |
| Mínimo da oferta | `max(valor_minimo_encerramento, valor em jogo × percentual ÷ 100)` | `CalculoEncerramento` |
| Selo do palpite | `ResultadosCotacao::avaliar` sobre `PlacarConfronto::do_ao_vivo` (R-06) | bilhete |
| Quanto teria pago | prêmio + acréscimo cheios se Vencedor pelos palpites; 0 se Perdedor; nulo em aberto (R-19) | listagem do painel |

## 10. Migrations

```text
database/migrations/
├── 2026_10_15_000001_create_apostas_encerramentos_automaticos_table.php
├── 2026_10_15_000002_create_apostas_encerramentos_table.php
├── 2026_10_15_000003_add_encerramento_apostas_table.php           # fracao_restante + comentário de resultado
├── 2026_10_15_000004_add_encerramento_configuracoes_table.php
├── 2026_10_15_000005_add_encerrar_aposta_clientes_configuracoes_table.php
├── 2026_10_15_000006_create_confrontos_eventos_table.php
├── 2026_10_15_000007_create_clientes_inscricoes_notificacoes_table.php
└── 2026_10_15_000008_create_clientes_notificacoes_table.php
```

A tabela de automáticos vem antes da de encerramentos por causa da chave estrangeira. Nenhuma coluna
aponta para tabela inexistente.
