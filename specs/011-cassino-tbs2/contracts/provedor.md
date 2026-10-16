# Contrato: integração com o provedor TBS2

**Feature**: `011-cassino-tbs2` | **Data**: 2026-10-16

Base: PDF "GAMES API EN" e as chamadas reais de 2026-10-16. Onde o PDF e a API real divergem, vale a
API real (research, "API real do provedor").

As credenciais vêm de `config('services.provedor_cassino')` (R-01). A `key` nunca é registrada.

## 1. Chamadas do sistema para o provedor (`ProvedorCassino`)

Todas são `POST` JSON (`Content-Type: application/json`).

### `gamesList` → `CASSINO_URL_API`

**Pedido**:

```json
{ "cmd": "gamesList", "hall": "<hall>", "key": "<chave>" }
```

**Resposta usada**:

```json
{
  "status": "success",
  "error": "",
  "content": {
    "gameLabels": ["pgsoft_op", "..."],
    "gameTitles": ["pgsoft", "..."],
    "gameList": [
      {
        "id": "9946",
        "name": "Fortune Tiger",
        "img": "https://...",
        "label": "pgsoft_op_kn",
        "title": "pgsoft",
        "categories": "slots",
        "device": "2",
        "flash": "0",
        "demo": "0",
        "menu": "",
        "bm": "0",
        "vertical": "1",
        "rewriterule": "0",
        "exitButton": "1",
        "disableReload": "0",
        "system_name2": "pgsoft/FortuneTiger"
      }
    ]
  }
}
```

**Tratamento**:
- `status` diferente de `success`, `gameList` vazio ou ausente, ou tempo esgotado (60 s): a carga
  falha sem desativar nada (R-04);
- `name_cn`, `name_kr`, `lines`, `width`, `wager`, `bonus` e `localhost` são ignorados, exceto o
  que vai para `dados_provedor`.

### `openGame` → `CASSINO_URL_API` + `openGame/`

**Pedido**:

```json
{
  "cmd": "openGame",
  "hall": "<hall>",
  "key": "<chave>",
  "login": "wss1532",
  "gameId": "9946",
  "demo": "0",
  "domain": "https://<APP_URL>",
  "exitUrl": "https://<APP_URL>/close.php",
  "language": "pt",
  "continent": "eur"
}
```

**Resposta de sucesso**:

```json
{
  "status": "success",
  "error": "",
  "content": {
    "game": { "url": "https://...", "iframe": "0", "withoutFrame": "0", "exitButton": "1", "rewriterule": "0" },
    "gameRes": { "sessionId": "..." }
  }
}
```

**Erros tratados** (R-08):

| `error` | Ação |
|---|---|
| `games_not_activ` | Canal indisponível; tenta o próximo |
| `hall_balance_less_100` | Canal indisponível, passa a pré-pago e "bônus primeiro"; tenta o próximo |
| `hall_not_exist`, `wrong_key`, outros | 503 ao cliente; registrado no log sem a chave |
| Tempo esgotado (10 s) | 503 ao cliente |

### `gameSessionsLog` → `CASSINO_URL_API`

**Pedido**:

```json
{ "cmd": "gameSessionsLog", "hall": "<hall>", "key": "<chave>", "sessionsId": "<sessao_provedor>", "count": 100, "page": 1 }
```

**Resposta**: o `content` é devolvido ao painel sem transformação (R-23). Os nomes dos parâmetros de
filtro são conferidos no PDF na implementação.

## 2. Chamadas do provedor para o sistema (callback)

**Endereço a cadastrar no hall** (Callback URL): `https://<APP_URL>/api/cassino/carteira`.

- **Formato**: `POST` com JSON (ou formulário, aceito também).
- **Resposta**: sempre HTTP 200 e JSON.
- **Validação** (R-13), em toda chamada e antes de qualquer outra coisa:
  - `hall` igual ao configurado → senão `wrong_hall`;
  - `key` igual à configurada → senão `wrong_key`;
  - com `CASSINO_ASSINATURA` ligado: `sign` = sha256 dos campos na ordem do provedor → senão
    `wrong_sign`.
- **Registro**: toda chamada, inclusive as recusadas, vai para `cassinos_chamadas` (R-15).

### `getBalance`

**Pedido**:

```json
{ "cmd": "getBalance", "hall": "<hall>", "key": "<chave>", "login": "wss1532" }
```

**Resposta**:

```json
{ "status": "success", "error": "", "login": "wss1532", "balance": "50.00", "currency": "BRL" }
```

`balance` é o saldo da carteira da sessão (R-17), com duas casas.

### `writeBet`

**Pedido** (campos usados; os demais vão para `dados_provedor`):

```json
{
  "cmd": "writeBet",
  "hall": "<hall>",
  "key": "<chave>",
  "login": "wss1532",
  "bet": "0.50",
  "win": "1.20",
  "tradeId": "a1b2c3",
  "action": "SPIN",
  "sessionId": "...",
  "gameId": "9946",
  "betInfo": "...",
  "round": "...",
  "matrix": "...",
  "WinLines": "...",
  "date": "..."
}
```

**Sucesso** (inclusive a repetição do mesmo `tradeId`):

```json
{ "status": "success", "error": "", "login": "wss1532", "balance": "50.70", "currency": "BRL", "operationId": 88001 }
```

`operationId` é o id da rodada (inteiro). Na repetição, devolve o mesmo id e o `saldo_posterior`
gravado.

**Processamento**: research R-14 (ordem), R-16 (ações), R-17 (carteira), R-18 (rollover) e R-19
(cancelamento e reembolso).

### Falhas (todas com HTTP 200)

Formato:

```json
{ "status": "fail", "error": "fail_balance" }
```

| `error` | Quando |
|---|---|
| `wrong_hall` / `wrong_key` / `wrong_sign` | Validação falhou |
| `user_not_found` | Login sem o prefixo, id inexistente ou cliente excluído |
| `fail_balance` | Nenhuma carteira cobre a aposta; cliente desativado apostando |
| `unknown_action` | Action fora de `cassinos_acoes` ou desativada |
| `unknown_command` | `cmd` diferente de `getBalance` e `writeBet` |
| `wrong_params` | `tradeId` ausente, `bet` ou `win` negativo ou não numérico |
| `internal_error` | Qualquer exceção (registrada); nunca resposta vazia |

Quando o login é reconhecido, a falha leva também `login`, `balance` e `currency` com o saldo
atual, para o jogo atualizar a tela.
