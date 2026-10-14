# Contrato HTTP: Apuração de resultados

Só uma rota nova, no grupo `auth:api` + `garantir_acesso` das configurações de pagamento, com o
mesmo padrão de `configuracoes/criar-aposta` (spec 008). Permissão `configuracoes.editar` (já
existe). As demais rotas não mudam: o comprovante e o extrato passam a mostrar os valores novos
(`resultado` do palpite `Devolvido` e a origem `Devolução de aposta`) pelos campos que já existem.

## `GET /api/configuracoes/apuracao`

200:

```json
{
  "data": {
    "remover_confronto_cancelado": true,
    "minutos_pagamento_premio": 5
  }
}
```

## `PUT /api/configuracoes/apuracao`

Corpo (só os campos enviados são gravados):

```json
{
  "remover_confronto_cancelado": false,
  "minutos_pagamento_premio": 10
}
```

| Campo | Regra |
|---|---|
| `remover_confronto_cancelado` | `sometimes`, boolean |
| `minutos_pagamento_premio` | `sometimes`, inteiro de 0 a 1440 |

Mensagens (422):

- `remover_confronto_cancelado.boolean`: "O campo remover_confronto_cancelado deve ser verdadeiro ou falso.";
- `minutos_pagamento_premio.integer` / `.between`: "A espera para pagar o prêmio deve ser de 0 a 1440 minutos.".

200: mesmo corpo do `GET`. 403 sem a permissão.

## Postman

A coleção `docs/postman/wssports_api.postman_collection.json` é regenerada com as 2 rotas,
mantendo `base_url` e `token`.
