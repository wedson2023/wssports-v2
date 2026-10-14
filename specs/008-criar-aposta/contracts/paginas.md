# Contrato de telas: Criar Aposta

Sem tela nova e sem componente com visual novo (FR-026). As configurações são editadas pela API
(não há tela de configurações no frontend).

| Tela / componente | Mudança |
|---|---|
| `MatchDetailsModal` | Marca como selecionados **todos** os palpites do jogo no cupom; escolher uma cotação segue a regra de R-12 (remove, troca no mesmo grupo, soma ao bloco ou avisa) |
| Listagem (`pages/Home`, `MatchCard`) | As 4 cotações seguem a mesma regra; a seleção do jogo passa a ser uma lista |
| `BetSlip` / `BetSlipItem` | Palpites do mesmo jogo aparecem juntos: times uma vez, uma linha por palpite (remover "x" à esquerda, na cor do tema, no lugar do "mais opções", mercado no meio e cotação à direita; pedido do responsável em 2026-10-13) e uma linha "Criar Aposta" com a cotação do bloco, com os mesmos estilos do item atual |
| `BetSlipSummary` / `BetSlipActions` | A contagem passa a ser de jogos (pernas) |
| `TicketModal` | O bloco é um item só: jogo, horário, campeonato e tipo uma vez; "Palpite: Criar Aposta"; uma linha por palpite (mercado e cotação, "(cancelado)" quando for o caso), a linha "Cotação" com a cotação do bloco e a situação do bloco (pedido do responsável em 2026-10-14); "Qtde. jogos" = `quantidade_jogos` |
| `utils/print.js`, `utils/thermal.js` | Palpites do bloco seguidos da linha "Criar Aposta" com a cotação; "Qtde. jogos" = `quantidade_jogos` |

Avisos do cupom (mesmo alerta já usado, `alerts.js`):

- "O Criar Aposta aceita no máximo N palpites."
- "Não é possível combinar dois palpites do mesmo mercado no Criar Aposta."
