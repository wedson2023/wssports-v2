# Data Model: Apuração de resultados e cancelamento de jogos

**Feature**: [spec.md](spec.md) | **Data**: 2026-10-14 | **Decisões**: [research.md](research.md)

Toda coluna criada ou alterada tem `->comment()` em português com o texto abaixo (constituição
3.3.0). Tabelas novas têm `timestamps()` e `softDeletes()`; o model usa `SoftDeletes`.

## 1. `confrontos_resultados` (nova, R-01)

Resultado de um jogo do pré-jogo recebido do provedor. Uma linha por jogo.

| Coluna | Tipo | Comentário |
|---|---|---|
| `id` | id | — |
| `confrontos_id` | foreignId → `confrontos`, único | Jogo da grade a que o resultado pertence |
| `placar_casa` | unsignedSmallInteger, nulo | Gols do time da casa no jogo inteiro; nulo enquanto o provedor não enviar |
| `placar_fora` | unsignedSmallInteger, nulo | Gols do time de fora no jogo inteiro; nulo enquanto o provedor não enviar |
| `gols_primeiro_tempo_casa` | unsignedSmallInteger, nulo | Gols do time da casa só no 1º tempo |
| `gols_primeiro_tempo_fora` | unsignedSmallInteger, nulo | Gols do time de fora só no 1º tempo |
| `gols_segundo_tempo_casa` | unsignedSmallInteger, nulo | Gols do time da casa só no 2º tempo |
| `gols_segundo_tempo_fora` | unsignedSmallInteger, nulo | Gols do time de fora só no 2º tempo |
| `escanteios_casa` | unsignedSmallInteger, nulo | Escanteios do time da casa; nulo sem dado (mercados de escanteio ficam Aguardando) |
| `escanteios_fora` | unsignedSmallInteger, nulo | Escanteios do time de fora; nulo sem dado |
| `situacao` | string(20) | Situação do jogo na carga de resultados (Aguardando, Encerrado, Cancelado, Adiado, Bloqueado); só Encerrado é apurado |
| `assinatura` | char(64) | SHA-256 do resultado e dos gols recebidos, para só reapurar quando algo mudar |
| `recebido_em` | timestamp | Última vez em que o resultado mudou na carga |
| timestamps, softDeletes | | |

**Regras**: placar com um lado preenchido e o outro nulo, ou valor negativo → item rejeitado na
validação. `apuravel()` = `situacao = Encerrado` e os dois placares não nulos (R-03).

## 2. `confrontos_gols` (nova, R-02)

| Coluna | Tipo | Comentário |
|---|---|---|
| `id` | id | — |
| `confrontos_id` | foreignId → `confrontos` | Jogo em que o gol foi marcado |
| `ordem` | unsignedTinyInteger | Número do gol no jogo (1 = primeiro gol), lido de "1º Gol" do provedor |
| `nome` | string(150) | Nome do jogador que marcou, como o provedor envia |
| `minuto` | unsignedSmallInteger, nulo | Minuto do gol |
| `tempo_jogo` | string(20), nulo | Tempo de jogo do gol como o provedor envia (ex.: 1º tempo) |
| timestamps, softDeletes | | |

Índice único (`confrontos_id`, `ordem`). Sincronização: ordens recebidas são atualizadas ou
restauradas; as que não vieram recebem soft delete.

## 3. `operacoes_apuracao` (nova, R-13)

Registro de tudo o que a apuração faz. Só inserção.

| Coluna | Tipo | Comentário |
|---|---|---|
| `id` | id | — |
| `tipo` | string(30) | Operação: Resultado do jogo, Situação do jogo, Resultado do palpite, Resultado da aposta, Palpite cancelado, Palpite restaurado, Prêmio pago, Prêmio ajustado, Prêmio estornado, Valor devolvido |
| `origem` | string(30) | O que disparou a operação: Carga de resultados, Carga de cancelados, Pagamento de prêmios, Reprocessamento manual, Encerramento de especial |
| `confrontos_id` | foreignId → `confrontos`, nulo | Jogo envolvido; nulo em operações só da aposta |
| `apostas_id` | foreignId → `apostas`, nulo | Aposta envolvida; nulo em operações só do jogo |
| `apostas_palpites_id` | foreignId → `apostas_palpites`, nulo | Palpite envolvido |
| `clientes_id` | foreignId → `clientes`, nulo | Cliente cujo saldo ou aposta foi afetado |
| `carteira` | string(30), nulo | Carteira movimentada (Saldo ou Promoção esportes); nulo sem movimentação |
| `clientes_transacoes_id` | foreignId → `clientes_transacoes`, nulo | Transação do extrato gerada pela operação |
| `valor` | decimal(15,2), nulo | Valor movimentado em reais; nulo sem movimentação |
| `antes` | json, nulo | Estado anterior (placar, situação, resultado, prêmio ou saldo, conforme o tipo) |
| `depois` | json, nulo | Estado posterior, mesmas chaves de antes |
| `motivo` | string(255), nulo | Explicação legível (ex.: "Placar corrigido pelo provedor") |
| timestamps, softDeletes | | |

Índices: (`confrontos_id`, `created_at`), (`apostas_id`, `created_at`), (`clientes_id`,
`created_at`), (`tipo`, `created_at`).

## 4. Tabelas alteradas

### `apostas`

| Coluna nova | Tipo | Comentário |
|---|---|---|
| `apurada_em` | timestamp, nulo | Quando o resultado ou o total a pagar mudou pela última vez na apuração; base da janela de pagamento |
| `valor_pago` | decimal(15,2), padrão 0 | Total líquido já creditado ao cliente por esta aposta (prêmios menos estornos), em reais |
| `liquidacao_pendente` | boolean, padrão falso | Verdadeiro quando o valor devido ainda difere do valor pago (aguardando a janela de pagamento) |

Índice (`liquidacao_pendente`, `apurada_em`).

### `apostas_palpites`

| Coluna nova | Tipo | Comentário |
|---|---|---|
| `motivo_cancelamento` | string(30), nulo | Por que o sistema cancelou o palpite: Jogo cancelado ou Empate anula aposta; nulo no cancelamento pelo painel ou de categoria especial |

`resultado` ganha o valor `Devolvido` (enum, sem mudança de coluna).

### `apostas_historico`

| Coluna alterada | Mudança | Comentário |
|---|---|---|
| `usuarios_id` | passa a aceitar nulo | Usuário que editou a aposta; nulo quando a ação foi do sistema (apuração) |
| `ip` | passa a aceitar nulo | IP de quem editou; nulo quando a ação foi do sistema |

### `configuracoes`

| Coluna nova | Tipo | Comentário |
|---|---|---|
| `remover_confronto_cancelado` | boolean, padrão verdadeiro | Cancela automaticamente os palpites de jogos cancelados ou adiados nas apostas ainda sem resultado |
| `minutos_pagamento_premio` | unsignedSmallInteger, padrão 5 | Minutos de espera entre a decisão da aposta e o crédito do prêmio (0 a 1440) |

## 5. Enums

| Enum | Casos (valor gravado) | Situação |
|---|---|---|
| `ResultadoAposta` | + `Devolvido` (`'Devolvido'`) | alterado; só palpite usa Devolvido |
| `OrigemTransacao` | + `DevoluçãoAposta` (`'Devolução de aposta'`) | alterado |
| `MotivoCancelamentoPalpite` | `JogoCancelado` (`'Jogo cancelado'`), `EmpateAnulaAposta` (`'Empate anula aposta'`) | novo |
| `TipoOperacaoApuracao` | `ResultadoJogo`, `SituaçãoJogo`, `ResultadoPalpite`, `ResultadoAposta`, `PalpiteCancelado`, `PalpiteRestaurado`, `PrêmioPago`, `PrêmioAjustado`, `PrêmioEstornado`, `ValorDevolvido` (valores como na tabela 3) | novo |
| `OrigemApuracao` | `CargaResultados`, `CargaCancelados`, `PagamentoPrêmios`, `ReprocessamentoManual`, `EncerramentoEspecial` (valores como na tabela 3) | novo |

## 6. Estados

### Palpite de jogo

```text
Aguardando ──(jogo Encerrado, regra)──► Vencedor | Perdedor
Aguardando ──(empate anula, empate)──► Devolvido  [situacao Cancelado, motivo Empate anula aposta]
Devolvido  ──(correção desfaz o empate)──► Vencedor | Perdedor  [situacao Ativo de novo]
Vencedor/Perdedor ──(correção)──► outro resultado
Ativo ──(jogo cancelado/adiado, opção ligada)──► Cancelado [motivo Jogo cancelado; não volta sozinho]
```

### Aposta (`situacao = Ativa`)

```text
resultado Aguardando ◄──► Vencedor ◄──► Perdedor     (toda mudança: apurada_em = agora)
valor devido = Vencedor ? premio + valor_acrescido : 0
liquidacao_pendente = (cliente com Saldo/Promoção) e valor devido ≠ valor_pago
```

### Liquidação

| Diferença (devido − pago) | Ação | Origem | Quando |
|---|---|---|---|
| > 0, sem palpite ativo | crédito | Devolução de aposta | após a janela |
| > 0 | crédito | Prêmio | após a janela |
| < 0 | débito (pode negativar) | Estorno | na hora |
| = 0 | nada; `liquidacao_pendente = falso` | — | — |
