# Contrato da API — Configurações gerais

Base `/api`, JSON, token JWT do usuário do painel (`auth:api` + `garantir_acesso`). Erros: 401 sem
token, 403 `{"message": "Você não tem permissão para esta ação."}`, 422
`{"message", "errors": {campo: [mensagem]}}`. Toda alteração bem-sucedida grava o histórico (R-16).

## 1. Rotas novas

### 1.1 `GET /configuracoes/banca` · `PUT /configuracoes/banca`

Permissão `configuracoes.editar` (Admin, Supervisor). `PUT` aceita qualquer subconjunto (`sometimes`).

```json
{
  "data": {
    "nome_sistema": "WSSports",
    "mensagem_bilhete": "BOA SORTE!",
    "whatsapp": "5511988887777",
    "mensagem_whatsapp": "Olá! Vim pelo site...",
    "botao_whatsapp": true,
    "instagram": "https://www.instagram.com/banca",
    "facebook": null,
    "twitter": null,
    "youtube": null,
    "jogo_responsavel": "https://www.gamblingtherapy.org/pt-br/",
    "cor_tema": "#006eb1",
    "cor_fundo": "#000000",
    "gtm_id": "GTM-ABC1234",
    "cores_tema": [{ "cor": "#c40808", "nome": "Vermelho" }, "... 6 itens"]
  }
}
```

Mensagens (exemplos): `"Informe o nome do sistema."`, `"Informe o WhatsApp com DDI e DDD, só
números (ex.: 5511999999999)."`, `"O link do Instagram deve começar com http:// ou https://."`,
`"Escolha uma das cores do tema."`, `"O GTM-ID deve estar no formato GTM-XXXXXXX."`.

### 1.2 `GET /configuracoes/ao-vivo` · `PUT /configuracoes/ao-vivo`

Permissão `configuracoes.editar`.

```json
{
  "data": {
    "ao_vivo_habilitado": true,
    "segundos_trava_ao_vivo": 15,
    "minutos_permanencia_ao_vivo": 5,
    "minuto_limite_ao_vivo": 95,
    "cotacao_maxima_ao_vivo": "30.00",
    "ao_vivo_travado": false,
    "ao_vivo_travado_em": null
  }
}
```

`ao_vivo_travado` e `ao_vivo_travado_em` são só leitura (ignorados no `PUT`).

### 1.3 `GET /configuracoes/sorteio-cotacoes` · `PUT /configuracoes/sorteio-cotacoes`

Permissão `configuracoes.editar`.

```json
{ "data": { "sorteio_ambas_marcam_minimo": "1.30", "sorteio_ambas_marcam_maximo": "1.50", "sorteio_ambas_nao_marcam_minimo": "1.45", "sorteio_ambas_nao_marcam_maximo": "1.55" } }
```

Mensagem do par inválido: `"O mínimo de \"ambas marcam\" não pode ser maior que o máximo."`.

### 1.4 `GET /configuracoes/robo-whatsapp`

Permissão `configuracoes.editar`. Consulta a Evolution API (até 10s) e corrige a situação gravada.

```json
{
  "data": {
    "situacao": "Conectado",
    "numero": "5511977776666",
    "perfil": "Banca WS",
    "atualizado_em": "2026-10-27T13:00:00-03:00",
    "disponivel": true,
    "pode_alterar": true
  }
}
```

Sem Evolution configurada ou fora do ar: `200` com `disponivel: false`, a situação gravada e
`"mensagem": "Serviço do robô indisponível. Confira o endereço e a chave da Evolution API no servidor."`.

### 1.5 `POST /configuracoes/robo-whatsapp/conectar`

Permissão `robo_whatsapp.conectar` (só Admin). Cria/garante a instância e o webhook e devolve o QR.
Throttle 10/min.

```json
{ "data": { "situacao": "Aguardando leitura", "qrcode": "data:image/png;base64,...." } }
```

Já conectado: `200` com `situacao: "Conectado"` e `qrcode: null`. Evolution indisponível: `503`
`{"message": "Serviço do robô indisponível..."}`. Histórico: `Conexão` (só na primeira chamada de
cada tentativa, não a cada QR renovado).

### 1.6 `DELETE /configuracoes/robo-whatsapp`

Permissão `robo_whatsapp.conectar`. Desconecta (`logout`) e grava `Desconectado`. `204`. Histórico:
`Desconexão`.

### 1.7 `POST /webhooks/evolution` (público)

Sem token JWT; throttle 120/min. Cabeçalho `x-webhook-token` obrigatório (igual a
`services.evolution.webhook_token`, `hash_equals`).

```json
{ "event": "connection.update", "instance": "banca-exemplo-com", "data": { "state": "open", "wuid": "5511977776666@s.whatsapp.net", "profileName": "Banca WS" } }
```

| Caso | Resposta | Efeito |
|---|---|---|
| Token errado/ausente | `401` | nenhum |
| Instância diferente da banca | `404` | nenhum |
| `connection.update` | `200 {"recebido": true}` | `open` → Conectado (número e perfil); `connecting` → Aguardando leitura; `close` → Desconectado |
| Outros eventos | `200` | ignorados |

## 2. Rotas existentes alteradas

| Rota | Mudança |
|---|---|
| `GET /configuracoes/gateway` | Permissão passa a `configuracoes.editar` (Supervisor vê); resposta ganha `pode_alterar` (tem `gateway_pagamento.vincular`) |
| `POST /configuracoes/gateway/mercado-pago/autorizar`, `DELETE /configuracoes/gateway` | Sem mudança de contrato; gravam histórico (`Vínculo` ao concluir no retorno, `Desvínculo`) |
| `POST /configuracoes/logo` | Resposta ganha `icones` (lista do manifest) e `aviso` (`"A imagem tem menos de 192x192 pixels; os ícones grandes podem ficar borrados."` ou `null`); gera os ícones (R-05) e grava histórico |
| `GET/PUT /configuracoes/regras` | `regras` passa a ser HTML limpo (R-08); `PUT` limpa e devolve o HTML gravado; limites: 10.000 caracteres de texto e 50.000 de HTML (`"As regras devem ter no máximo 10.000 caracteres de texto."`) |
| `PUT /configuracoes/{pagamentos,criar-aposta,apuracao,encerramento,cassino,afiliados,avisos-whatsapp}` | Sem mudança de contrato; gravam histórico |
| `POST/PUT/DELETE /banners`, `/avisos` | Sem mudança de contrato; gravam histórico (`Cadastro`, `Alteração`, `Exclusão`, com `item_id`) |
| `PUT /clientes/notificacoes/limite` | Sem mudança (só Admin, spec 019); grava histórico (`notificacoes`) |

`GET /configuracoes/avisos-whatsapp` continua sem a chave (só `chave_callmebot_definida` e
`chave_callmebot_final`).

O `Vínculo` do Mercado Pago é registrado no `VinculoMercadoPago::concluir` (o autor é o admin guardado
no `state`), porque o retorno do OAuth chega sem token.

## 3. Páginas web

| Rota | Controller | Página Inertia |
|---|---|---|
| `GET /painel/configuracoes` | `PainelController::configuracoes` | `PanelSettings` |
