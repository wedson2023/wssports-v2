# Contrato de telas: Criar Aposta

Sem tela nova e sem componente com visual novo (FR-026). As configurações são editadas pela API
(não há tela de configurações no frontend).

| Tela / componente | Mudança |
|---|---|
| `MatchDetailsModal` | Marca como selecionados **todos** os palpites do jogo no cupom; escolher uma cotação segue a regra de R-12 (remove, troca no mesmo grupo, soma ao bloco ou avisa) |
| Listagem (`pages/Home`, `MatchCard`) | As 4 cotações seguem a mesma regra; a seleção do jogo passa a ser uma lista |
| `BetSlip` / `BetSlipItem` | Palpites do mesmo jogo aparecem juntos: times uma vez, uma linha (mercado + cotação + remover) por palpite e uma linha "Criar Aposta" com a cotação do bloco, com os mesmos estilos do item atual |
| `BetSlipSummary` / `BetSlipActions` | A contagem passa a ser de jogos (pernas) |
| `TicketModal`, `utils/print.js`, `utils/thermal.js` | Palpites do bloco seguidos da linha "Criar Aposta" com a cotação; "Qtde. jogos" = `quantidade_jogos` |

Avisos do cupom (mesmo alerta já usado, `alerts.js`):

- "O Criar Aposta aceita no máximo N palpites."
- "Não é possível combinar dois palpites do mesmo mercado no Criar Aposta."
