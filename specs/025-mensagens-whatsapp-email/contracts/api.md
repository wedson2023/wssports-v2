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

Corpo: `aviso_combinacao_whatsapp`, `telefone_whatsapp_admin` (12 a 15 dígitos, nulo aceito), `email_admin` (e-mail válido, nulo aceito) e `mensagens` (lista de `{ "tipo": "<tipo>", "canal": "<canal>" }`, como vem no `GET`). Grava pelo `HistoricoConfiguracoes`, seção `mensagens`.

Erros 422 em português. O aviso de combinação ligado exige telefone ou e-mail preenchido. Resposta igual à do `GET`.

### `POST /configuracoes/mensagens/teste`

Limite: 5 por minuto. Corpo: `{ "canal": "WhatsApp" | "E-mail" }`, sem campo de destinatário. O envio é síncrono, para mostrar o resultado na tela.

- 200: `{ "message": "Mensagem de teste enviada." }`
- 422: campo do Admin vazio, robô desconectado ou serviço com falha, com mensagem sem detalhes técnicos.

`GET` e `PUT /configuracoes/mensagens` também levam `confirmacao_cadastro` (booleano).

## Confirmação de cadastro

### `POST /area-cliente/confirmar-email`

Público, `throttle:10,1`. Corpo: `{ "token": "..." }`.

- 200: mesmos campos de login do cliente (`cliente`, `token`, `tipo`, `expira_em`)
- 422: `{ "message": "Link expirado. Peça um novo link." }`

### `POST /area-cliente/confirmar-email/reenviar`

Público, com o limite de 1 por minuto por telefone da recuperação de senha. Corpo: `{ "ddi": "55", "telefone": "..." }`. Sempre 200: `{ "message": "Se houver cadastro pendente, enviaremos um novo link." }`

### `POST /clientes/{cliente}/confirmar-email`

Painel, permissão `clientes.editar` e alcance do cliente. Sem corpo. Responde o cliente atualizado.

## Alteradas

### `POST /area-cliente/cadastro`

Com a opção ligada: `email` obrigatório e resposta 201 sem token: `{ "cliente": { ... }, "confirmacao_pendente": true }`.

### `POST /area-cliente/auth/login`

Com a confirmação pendente: 403 com `{ "message": "Confirme seu e-mail para entrar.", "confirmacao_pendente": true }`.

### `GET /auth/eu`

Acrescenta, só para quem tem a permissão: `"robo_whatsapp_caido": true | false`, verdadeiro com o robô desconectado e sem desconexão pedida.

## Removidas

`GET` e `PUT /configuracoes/avisos-whatsapp`. Substituídas por `/configuracoes/mensagens`.

A coleção do Postman é regenerada na mesma tarefa.
