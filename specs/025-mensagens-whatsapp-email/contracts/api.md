# Contrato da API

Todas as rotas exigem `auth:api`, `garantir_acesso` e a permissão `configuracoes.editar_mensagens`.

## Novas

### `GET /configuracoes/mensagens`

```json
{
  "data": {
    "aviso_combinacao_whatsapp": true,
    "telefone_whatsapp_admin": "5582999999999",
    "email_admin": "admin@exemplo.com",
    "mensagens": [
      {
        "tipo": "BoasVindasCliente",
        "nome": "Boas-vindas",
        "destinatario": "Cliente",
        "canal": "Os dois",
        "reserva": false
      }
    ],
    "canais": ["WhatsApp", "E-mail", "Os dois"]
  }
}
```

### `PUT /configuracoes/mensagens`

Corpo: `aviso_combinacao_whatsapp`, `telefone_whatsapp_admin` (12 a 15 dígitos, nulo aceito), `email_admin` (e-mail válido, nulo aceito) e `canais` (`{ "<tipo>": "<canal>" }`). Grava pelo `HistoricoConfiguracoes`, seção `mensagens`.

Erros 422 em português. O aviso de combinação ligado exige telefone ou e-mail preenchido. Resposta igual à do `GET`.

### `POST /configuracoes/mensagens/teste`

Limite: 5 por minuto. Corpo: `{ "canal": "WhatsApp" | "E-mail" }`, sem campo de destinatário. O envio é síncrono, para mostrar o resultado na tela.

- 200: `{ "message": "Mensagem de teste enviada." }`
- 422: campo do Admin vazio, robô desconectado ou serviço com falha, com mensagem sem detalhes técnicos.

## Alteradas

### `GET /auth/eu`

Acrescenta, só para quem tem a permissão: `"robo_whatsapp_caido": true | false`, verdadeiro com o robô desconectado e sem desconexão pedida.

## Removidas

`GET` e `PUT /configuracoes/avisos-whatsapp`. Substituídas por `/configuracoes/mensagens`.

A coleção do Postman é regenerada na mesma tarefa.
