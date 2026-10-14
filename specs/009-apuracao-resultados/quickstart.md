# Quickstart: validação manual da apuração

Sem testes automatizados (constituição). Os cenários seguem a [spec](spec.md); formatos em
[contracts/provedor.md](contracts/provedor.md), [contracts/comandos.md](contracts/comandos.md) e
[contracts/api.md](contracts/api.md); estados em [data-model.md](data-model.md).

## Preparação

```bash
php artisan migrate
```

- **Provedor simulado**: no mesmo mock server do Postman da spec 003, criar as rotas
  `GET /api/resultados` e `GET /api/cancelados` com os exemplos de
  [contracts/provedor.md](contracts/provedor.md), trocando `fonte_id` pelos `codigo_externo` dos
  jogos de teste. `PROVEDOR_COTACOES_URL_PRE_JOGO` aponta para o mock.
- Rodar as cargas à mão: `php artisan confrontos_resultados:importar`,
  `php artisan confrontos_cancelados:importar`, `php artisan apostas:liquidar_premios`.
- Para não esperar a janela: `PUT /api/configuracoes/apuracao` com `minutos_pagamento_premio: 0`
  (cenários de janela usam 5).
- Massa: dois jogos de teste (A e B) com cotações; um cliente com R$ 100,00 em Saldo e R$ 50,00 em
  Promoção esportes; um vendedor.
- Conferir os comentários: `SHOW FULL COLUMNS FROM confrontos_resultados;` (e `confrontos_gols`,
  `operacoes_apuracao`, `apostas`, `apostas_palpites`, `apostas_historico`, `configuracoes`).
- Conferir os registros: `SELECT tipo, origem, apostas_id, valor, antes, depois FROM
  operacoes_apuracao ORDER BY id DESC;`

## Cenários

| # | Passos | Esperado | Requisito |
|---|---|---|---|
| 1 | Cliente aposta R$ 10,00 com Saldo em "Casa" (A). Mock: A `Encerrado` 2 x 1. Rodar resultados | Palpite e aposta `Vencedor`; `apurada_em` preenchido; `liquidacao_pendente` verdadeiro | US1-1, FR-015 |
| 2 | Rodar `apostas:liquidar_premios` (janela 0) | Crédito do total a pagar em Saldo, origem `Prêmio`, `referencia_id` = aposta; `valor_pago` igual; `liquidacao_pendente` falso; registro "Prêmio pago" | US2-1, FR-020 a FR-024 |
| 3 | Rodar liquidação e resultados de novo, sem mudar o mock | Nenhuma transação nem registro novo | US1-8, US2-5, SC-002, SC-007 |
| 4 | Mock: A com `situacao` `2 tempo` e placar 1 x 0 (antes de encerrar), aposta nova em A | Resultado guardado; palpite segue `Aguardando`; nada pago | FR-004 |
| 5 | Múltipla A + B; A encerrado com acerto, B sem resultado | Palpite de A `Vencedor`, aposta `Aguardando` | US1-2 |
| 6 | Múltipla A + B; A encerrado com erro | Aposta `Perdedor` | US1-3 |
| 7 | Palpite de escanteios em A; mock sem `escanteio_*` | Palpite `Aguardando`; com escanteios no mock e nova carga → decidido | US1-4, FR-008 |
| 8 | Palpite de jogador "Primeiro" e "Qualquer Momento"; mock com o jogador no `2º Gol` | "Qualquer Momento" `Vencedor`; "Primeiro" `Perdedor` | US1-5, FR-009 |
| 9 | Múltipla com "Empate anula - Casa" (A) e outro jogo; A termina 1 x 1 | Palpite `Cancelado`, `resultado` `Devolvido`, motivo `Empate anula aposta`; cotação total e prêmio recalculados; histórico sem usuário | US1-6, FR-010, FR-016 |
| 10 | Corrigir o mock do 9 para 2 x 1 | Palpite volta a `Ativo` e `Vencedor`; prêmio recalculado | FR-019, R-06 |
| 11 | Bloco Criar Aposta (liquidação `Anular bloco`) com "Empate anula" + outro palpite do bloco; empate | Bloco `Anulado` (todos os palpites do bloco valem 1,00) | FR-017 |
| 12 | Aposta do cenário 2 já paga; mock corrigido para 0 x 1 | Aposta `Perdedor`; débito do valor pago em Saldo, origem `Estorno`; registro "Prêmio estornado"; outras transações intactas | US3-1, FR-027, SC-004 |
| 13 | Como o 12, mas cliente com saldo menor que o estorno | Saldo fica negativo pela diferença | US3-2, FR-028 |
| 14 | Mock volta para 2 x 1 | Aposta `Vencedor`, novo crédito após a janela | US3-3 |
| 15 | Aposta paga em múltipla; um palpite muda para `Devolvido` por correção | Crédito ou débito só da diferença; registro "Prêmio ajustado" | US3-4, FR-029 |
| 16 | Janela 5 min; aposta decide `Vencedor` e, antes de 5 min, a correção a torna `Perdedor` | Nenhum crédito nem estorno | US2-4, US3-5 |
| 17 | Aposta paga com Promoção esportes vence | Crédito na carteira Promoção esportes | US2-2, FR-021 |
| 18 | Aposta de vendedor (Dinheiro) vence | Resultado `Vencedor`, sem transação | US2-3, FR-022 |
| 19 | Múltipla A + B; mock de cancelados com B; opção ligada | Palpite de B `Cancelado` (motivo `Jogo cancelado`), prêmio recalculado com histórico; aposta decidida por A | US4-1, FR-032 |
| 20 | Simples com Saldo só em B; B cancelado | Aposta `Vencedor`, prêmio = valor, cotação 1,00; crédito do valor em Saldo, origem `Devolução de aposta`; registro "Valor devolvido" | US4-2, FR-035 |
| 21 | Como o 20 para aposta de vendedor | `Vencedor` com prêmio = valor, sem transação | US4-3 |
| 22 | `PUT configuracoes/apuracao` com `remover_confronto_cancelado: false`; cancelar outro jogo | Jogo `Cancelado`; palpites seguem `Aguardando` | US4-4, FR-034 |
| 23 | Jogo cancelado depois volta no mock de resultados | Resultado gravado e registrado; palpites cancelados continuam cancelados | Edge, clarify |
| 24 | Aposta já paga com palpite em jogo depois cancelado | Aposta não muda | US4-6 |
| 25 | `php artisan apostas:reapurar {id de A}` sem mudanças | Nada muda; com mudança no banco → reapura com origem "Reprocessamento manual" | US6, FR-042 |
| 26 | `php artisan apostas:reapurar 999999` | "Jogo não encontrado." | US6-2 |
| 27 | Mock com resposta que não é lista / status 500 | Log de falha; nada gravado | Edge |
| 28 | Item com só `placar_casa` | Item ignorado com motivo no log | Edge |
| 29 | `somente_cassino` ligado | Cargas não executadas | FR-005 |
| 30 | Encerrar uma categoria especial numa aposta especial + jogo já `Vencedor` | Aposta decidida e paga pela mesma regra | FR-011, R-09 |
| 31 | `GET`/`PUT /api/configuracoes/apuracao` sem permissão; com valor 2000 | 403; 422 com a mensagem do contrato | contrato |
