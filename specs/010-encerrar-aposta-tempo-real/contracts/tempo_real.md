# Contrato: canais em tempo real e mensagens de push

**Feature**: `010-encerrar-aposta-tempo-real`

## 1. Broadcasting (Reverb, protocolo Pusher)

Só canais **públicos**, sem rota de autorização. Os eventos são `ShouldBroadcastNow`, e o nome
enviado é o `broadcastAs()`. Falha do Reverb vira `Log::warning` e não interrompe quem emitiu.

### Canal `confrontos.{confrontos_id}` · evento `confronto.atualizado`

Emitido por `ProcessamentoAoVivo` quando, na carga do ao vivo, mudou placar, período, minuto, gols
por tempo, escanteios ou cotações do jogo. Também é emitido quando o resultado final é gravado
(`periodo` = situação do resultado).

```json
{
  "confrontos_id": 381,
  "periodo": "2 tempo",
  "minuto": 67,
  "placar_casa": 1,
  "placar_fora": 0,
  "primeiro_tempo": { "casa": 1, "fora": 0 },
  "segundo_tempo": { "casa": 0, "fora": 0 },
  "escanteios": 7,
  "gol": true,
  "cotacoes_alteradas": true,
  "encerrado": false,
  "enviado_em": "2026-10-15T18:10:04Z"
}
```

`gol` = o placar mudou nesta carga (a página dá o destaque passageiro no placar, FR US1-2). Os gols
por tempo e os escanteios são nulos fora do futebol.

### Canal `apostas.{codigo}` · evento `aposta.atualizada`

Emitido depois da transação quando a apuração muda um palpite ou o resultado, quando um
encerramento é decidido, creditado ou desfeito, e quando um automático é executado.

```json
{ "codigo": "AB12CD34", "motivo": "encerramento", "enviado_em": "2026-10-15T18:10:04Z" }
```

`motivo`: `apuracao`, `encerramento`, `automatico`. O evento não traz valores: a página busca
`GET /publico/bilhetes/{codigo}` (cada pessoa vê o que pode ver, R-03).

### Comportamento da página (R-02)

| Estado da conexão (pusher-js) | Indicador | Atualização |
|---|---|---|
| `connected` | "Ao vivo" | eventos + nova busca agrupada (≤ 1 a cada 2 s) + verificação a cada 60 s |
| `connecting` / `unavailable` / `failed` / `disconnected` | "Reconectando…" | consulta a cada 10 s (jogo ao vivo) ou 60 s (sem jogo ao vivo) |
| aposta com `final = true` | sem indicador | nenhuma (sai dos canais) |

## 2. Web Push

### Mensagem enviada (payload criptografado, JSON)

```json
{
  "titulo": "⚽ GOL! Time A 1 x 0 Time B",
  "corpo": "67' · Brasil - Série A",
  "url": "/bilhete/AB12CD34",
  "tag": "confronto-381",
  "tipo": "Gol",
  "icone": "<ícone de 192 px do manifest>"
}
```

`icone` é o mesmo ícone de 192 px que o manifest do aplicativo usa (`DadosFake::icones()`, até a
spec de configurações visuais trocá-lo).

| Tipo | `tag` |
|---|---|
| Gol, Gol anulado, Intervalo, Fim de jogo | `confronto-{confrontos_id}` (o novo substitui o anterior, FR-045) |
| Resultado da aposta, Encerramento automático | `aposta-{codigo}` |

Opções no envio: `TTL` de 600 s nos avisos de jogo e de 86.400 s nos de aposta; `urgency: high`
nos gols. Textos na tabela da R-17 do [research.md](../research.md).

### Service worker (`/sw-notificacoes.js`, importado pelo `sw.js`)

- `push`: `showNotification(titulo, { body: corpo, icon, badge: icone, tag, renotify: true,
  data: { url } })`.
- `notificationclick`: fecha o aviso e foca uma aba já aberta em `data.url` ou abre uma nova.
- Sem `fetch` de confirmação de recebimento (o antigo registrava no servidor; aqui a auditoria é o
  `clientes_notificacoes`).

### Respostas do serviço de push

| Resposta | Ação |
|---|---|
| 201 / 2xx | `ultimo_envio_em` = agora; soma em `aparelhos` |
| 404 / 410 | inscrição removida (soft delete) e soma em `falhas` (FR-046) |
| outras | `Log::warning` e soma em `falhas`, sem nova tentativa |
