# Contrato dos comandos (Artisan)

Agenda em `routes/console.php`, junto das cargas do pré-jogo.

| Comando | Agenda | Opções da agenda |
|---|---|---|
| `confrontos_resultados:importar` | `3-59/5 * * * *` (:03, :08…) | `runInBackground()`, `withoutOverlapping(10)` |
| `confrontos_cancelados:importar` | `4-59/5 * * * *` (:04, :09…) | `runInBackground()`, `withoutOverlapping(10)` |
| `apostas:liquidar_premios` | a cada minuto | `withoutOverlapping(5)` |
| `apostas:reapurar {confronto}` | manual | — |

As duas cargas usam `ExecutarCargaPreJogo`: com `somente_cassino` não rodam; falha do provedor →
log e `FAILURE`, nada gravado; resumo no log com o tempo gasto.

## `confrontos_resultados:importar`

1. Busca e valida ([provedor.md](provedor.md#6-resultados-confrontos_resultadosimportar-a-cada-5-min-03)).
2. Para cada jogo existente: calcula a assinatura; igual à gravada → nada; diferente → grava o
   resultado e os gols numa transação, registra "Resultado do jogo" (antes/depois) e apura as
   apostas `Ativa` com palpite no jogo, uma transação por aposta. Erro numa aposta → log, segue.
3. Resumo: `"N jogos recebidos, N alterados, N apostas apuradas, N ignorados, N falhas"`.

## `confrontos_cancelados:importar`

1. Busca e valida ([provedor.md](provedor.md#7-cancelados-confrontos_canceladosimportar-a-cada-5-min-04)).
2. Para cada jogo existente: situação diferente de `Cancelado`/`Adiado` → grava `Cancelado` (ou
   mantém `Adiado`) e registra "Situação do jogo".
3. Com `remover_confronto_cancelado` ligado: para cada aposta `Ativa`, resultado `Aguardando`, com
   palpite `Ativo` e `Aguardando` no jogo → cancela o palpite pelo sistema (motivo `Jogo
   cancelado`, histórico, regra do bloco), registra "Palpite cancelado" e reapura a aposta. Sem
   palpite ativo, a aposta fica `Vencedor` com prêmio igual ao valor e a devolução segue a
   liquidação.
4. Resumo: `"N jogos recebidos, N cancelados agora, N palpites cancelados, N falhas"`.

## `apostas:liquidar_premios`

Apostas com `liquidacao_pendente = true` e `apurada_em <= agora − minutos_pagamento_premio`, em
lotes de 500, uma transação por aposta (aposta travada): liquida a diferença (ver
[data-model.md](../data-model.md#liquidação)), registra a operação com origem "Pagamento de
prêmios". Resumo: `"N apostas liquidadas, R$ X creditados, R$ Y estornados, N falhas"`.

## `apostas:reapurar {confronto}`

- `confronto`: id do jogo (`confrontos.id`).
- Jogo inexistente → `"Jogo não encontrado."` e `FAILURE`.
- Reapura todas as apostas `Ativa` com palpite no jogo, mesmo sem mudança de assinatura, com origem
  "Reprocessamento manual". Sem mudança real, nada é gravado nem movimentado.
- Resumo: `"N apostas reapuradas, N alteradas, N falhas"`.
