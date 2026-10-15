# Contrato da API: bilhete em tempo real, encerramento e notificações

**Feature**: `010-encerrar-aposta-tempo-real` | Base: `/api` | JSON em `snake_case` | Valores em
reais como texto com 2 casas (`"12.50"`) | Datas ISO 8601 no fuso do pedido (`fuso_horario`,
como no comprovante).

Erros seguem o padrão do projeto: `{ "message": "..." }`, com `errors` na validação (422).

---

## 1. Bilhete em tempo real (público)

### `GET /publico/bilhetes/{codigo}`

Sem login. Aceita o token do cliente (`Authorization: Bearer`, opcional) para identificar o dono
(R-03). Limite de 120 consultas por minuto por IP.

**404** — código inexistente ou aposta `Pendente`, `Em análise`, `Recusada` ou `Expirada` (FR-011):

```json
{ "message": "Bilhete não encontrado ou ainda não validado." }
```

**200**:

```json
{
  "data": {
    "codigo": "AB12CD34",
    "situacao": "Ativa",
    "resultado": "Aguardando",
    "final": false,
    "cliente": "José W.",
    "dono": true,
    "criada_em": "2026-10-15T14:02:00-03:00",
    "valor": "50.00",
    "valor_em_jogo": "50.00",
    "cotacao_total": "6.12",
    "total_a_pagar": "306.00",
    "valor_acrescido": "0.00",
    "progresso": { "decididos": 1, "ganhando": 1, "perdendo": 0, "aguardando": 0, "nao_iniciados": 1 },
    "atualizado_em": "2026-10-15T15:10:04-03:00",
    "canais": { "aposta": "apostas.AB12CD34", "confrontos": ["confrontos.381", "confrontos.402"] },
    "blocos": [],
    "palpites": [
      {
        "id": 91,
        "apostas_blocos_id": null,
        "confrontos_id": 381,
        "situacao": "Ativo",
        "resultado": "Aguardando",
        "selo": "Ganhando agora",
        "estado": "ao_vivo",
        "campeonato": "Brasil - Série A",
        "data_inicio": "2026-10-15T14:00:00-03:00",
        "time_casa": "Time A",
        "time_fora": "Time B",
        "escudo_casa": "https://…/a.png",
        "escudo_fora": "https://…/b.png",
        "esporte": "FUTEBOL",
        "codigo_cotacao": "odd1",
        "mercado": "Vencedor - Casa",
        "jogador": null,
        "jogador_tipo": null,
        "cotacao": "2.10",
        "cotacao_atual": "1.45",
        "periodo": "2 tempo",
        "minuto": 67,
        "placar_casa": 1,
        "placar_fora": 0,
        "primeiro_tempo": { "casa": 1, "fora": 0 },
        "segundo_tempo": { "casa": 0, "fora": 0 },
        "escanteios": 7,
        "minutos_para_inicio": null
      }
    ],
    "encerramento": null
  }
}
```

| Campo | Regra |
|---|---|
| `final` | `true` com a aposta decidida (Vencedor/Perdedor), `Encerrada` ou `Cancelada`: a página para a atualização (FR-010) |
| `cliente` | primeiro nome + inicial do último sobrenome com ponto; nome completo quando `dono` (FR-002) |
| `dono` | o token é do cliente da aposta (inclui o acesso assistido) |
| `valor_em_jogo` / `total_a_pagar` | já multiplicados por `fracao_restante` (R-07) |
| `progresso` | uma contagem por palpite fora de bloco e por bloco (FR-002) |
| `palpites[].estado` | `nao_iniciado`, `ao_vivo`, `encerrado` (resultado gravado), `cancelado` |
| `palpites[].selo` | só com `estado = ao_vivo` e resultado `Aguardando`: `Ganhando agora`, `Perdendo agora` ou `Aguardando` (R-06); nulo nos demais |
| `palpites[].cotacao_atual` | cotação atual do mesmo mercado, ajustada para quem vê (o cliente dono ou o público do site); nula com o jogo encerrado, o palpite decidido ou o mercado sem cotação |
| `palpites[].primeiro_tempo`, `segundo_tempo`, `escanteios` | só no futebol (FR-004) |
| `palpites[].minutos_para_inicio` | só com `estado = nao_iniciado` |
| `palpites[]` especial | mesmo formato do comprovante (time_casa "Vencedor", categoria em time_fora) |
| `canais` | canais a assinar (só os jogos em aberto); vazio quando `final` |
| `encerramento` | só para o dono e só com a aposta `Ativa`/`Aguardando`; nulo nos demais (ver abaixo) |

**`encerramento`** (dono):

```json
{
  "disponivel": true,
  "motivo": null,
  "oferta": "182.40",
  "variacao": "subiu",
  "minimo": "2.50",
  "margem": "10.00",
  "ao_vivo": true,
  "token_oferta": "eyJ…",
  "expira_em": "2026-10-15T15:10:12-03:00",
  "segundos_validade": 8,
  "minutos_credito": 5,
  "parcial": { "disponivel": true, "motivo": null, "restantes": 5 },
  "automatico": { "disponivel": true, "ativo": { "valor_alvo": "250.00", "criado_em": "…" } },
  "em_analise": null,
  "registrados": [
    { "modalidade": "Parcial", "valor": "40.00", "situacao": "Registrado", "creditar_em": "…" }
  ]
}
```

- `disponivel = false`: `oferta`, `token_oferta` e `expira_em` nulos, e `motivo` com o texto da
  R-05 (FR-023).
- `variacao`: `subiu`, `caiu` ou `null`, comparando com a oferta anterior da mesma aposta (a página
  envia `oferta_anterior` na query) (FR-023).
- `em_analise`: `{ "chave": "uuid", "segundos_restantes": 9 }` enquanto houver encerramento `Em
  análise`.

---

## 2. Encerramento (área do cliente)

Todas exigem `auth:clientes` + `GarantirAcessoCliente`. Só o dono da aposta: outra aposta → 404
"Aposta não encontrada.". No acesso assistido, o autor gravado é o usuário do painel (FR-016,
FR-035).

### `POST /area-cliente/apostas/{codigo}/encerramentos`

Limite de 10 por minuto por cliente.

```json
{
  "modalidade": "Total",
  "valor": "182.40",
  "token_oferta": "eyJ…",
  "chave_idempotencia": "8f2a…uuid",
  "aceitar_alteracoes": "Somente para maior"
}
```

| Campo | Regra |
|---|---|
| `modalidade` | obrigatório; `Total` ou `Parcial` |
| `valor` | obrigatório; no total, igual à oferta do token; no parcial, entre o mínimo e menor que a oferta |
| `token_oferta` | obrigatório; assinatura válida e não vencida (R-09) |
| `chave_idempotencia` | obrigatório; UUID |
| `aceitar_alteracoes` | opcional; `Nenhuma`, `Somente para maior` ou `Qualquer`; padrão = o da aposta |

Respostas:

| Status | Quando | Corpo |
|---|---|---|
| **201** | aceito (pré-jogo) | `{ "data": Encerramento }` com `situacao: "Registrado"` |
| **202** | aguardando o tempo de confirmação do ao vivo | `{ "data": Encerramento }` com `situacao: "Em análise"` e `segundos_restantes` |
| **200** | reenvio da mesma `chave_idempotencia` | o registro existente |
| **409** | oferta vencida, adulterada ou fora da preferência | `{ "message": "A oferta mudou.", "encerramento": {…bloco atualizado…} }` |
| **422** | aposta inelegível, parcial desligado, limite de parciais, valor fora da faixa | `{ "message": "<motivo da R-05 ou da validação>" }` |

**Encerramento**:

```json
{
  "chave": "8f2a…uuid",
  "modalidade": "Total",
  "situacao": "Registrado",
  "valor": "182.40",
  "oferta": "182.40",
  "valor_apostado_depois": "0.00",
  "premio_depois": "0.00",
  "creditar_em": "2026-10-15T15:15:20-03:00",
  "motivo_recusa": null,
  "segundos_restantes": null
}
```

### `GET /area-cliente/apostas/{codigo}/encerramentos/{chave}`

Situação de um pedido (acompanha o `Em análise`). **200** `{ "data": Encerramento }`; **404** se a
chave não for desta aposta.

### `PUT /area-cliente/apostas/{codigo}/encerramento-automatico`

```json
{ "valor_alvo": "250.00" }
```

`valor_alvo` deve ser maior que a oferta atual e no máximo o prêmio potencial. Exige o automático
ligado (geral). Substitui o ativo (o anterior fica `Cancelado` com motivo "Alterado").

| Status | Corpo |
|---|---|
| **200** | `{ "data": { "valor_alvo": "250.00", "situacao": "Ativo", "criado_em": "…" } }` |
| **422** | "O valor-alvo deve ser maior que a oferta atual de R$ 182,40." / inelegível / desligado |

### `DELETE /area-cliente/apostas/{codigo}/encerramento-automatico`

Desativa o ativo (motivo "Cancelado pelo cliente"). **204**; **404** sem automático ativo.

---

## 3. Notificações push

### `GET /publico/notificacoes/configuracao`

Sem login. **200** `{ "data": { "chave_publica": "BElx…", "habilitado": true } }`.
`habilitado = false` quando as chaves VAPID não estão configuradas (o sino não aparece).

### `GET /area-cliente/notificacoes/inscricao?endpoint=…`

Inscrição deste aparelho. **200** `{ "data": Inscricao }`; **404** se não houver.

### `PUT /area-cliente/notificacoes/inscricao`

Cria ou atualiza a inscrição do aparelho pelo `endpoint` (R-15). Limite de 20 por minuto por
cliente.

```json
{
  "endpoint": "https://fcm.googleapis.com/fcm/send/…",
  "chaves": { "p256dh": "BN…", "auth": "k8…" },
  "codificacao": "aes128gcm",
  "preferencias": {
    "receber_gols": true,
    "receber_intervalo": true,
    "receber_fim_jogo": true,
    "receber_resultado_aposta": true,
    "receber_encerramento_automatico": true
  }
}
```

`endpoint` URL https (máx. 2.000); `chaves.*` obrigatórias na criação; `preferencias` opcional (na
criação, todas ligadas, FR-041). **200** `{ "data": Inscricao }`.

**Inscricao**: `{ "aparelho": "Chrome no Android", "receber_gols": true, … }`.

### `DELETE /area-cliente/notificacoes/inscricao?endpoint=…`

Desativa as notificações deste aparelho (FR-041). **204**.

---

## 4. Painel

Exigem `auth:api` + `garantir_acesso`.

### `GET /configuracoes/encerramento` · `PUT /configuracoes/encerramento`

Permissão `configuracoes.editar`. Mesmo padrão de `configuracoes/apuracao`: o `PUT` grava só os
campos enviados.

```json
{
  "data": {
    "encerramento_habilitado": true,
    "encerramento_parcial_habilitado": true,
    "encerramento_automatico_habilitado": true,
    "encerramento_jogos_nao_iniciados": true,
    "margem_encerramento_pre_jogo": "7.00",
    "margem_encerramento_ao_vivo": "10.00",
    "valor_minimo_encerramento": "1.00",
    "percentual_minimo_encerramento": "5.00",
    "segundos_validade_oferta": 8,
    "limite_encerramentos_parciais": 5
  }
}
```

Validação (faixas da R-18), com mensagens em português, por exemplo: "A margem do pré-jogo deve ser
de 0 a 50%.".

### `GET /apostas-encerramentos`

Permissão `apostas_encerramentos.listar` (FR-037).

| Query | Regra |
|---|---|
| `data_inicial`, `data_final` | `Y-m-d`, no fuso do sistema; padrão: hoje |
| `clientes_id` | opcional |
| `modalidade` | opcional; `Total`, `Parcial` ou `Automático` |
| `situacao` | opcional; valores de `SituacaoEncerramento` |
| `pagina`, `por_pagina` | `por_pagina` padrão 20, máximo 100 (constituição) |

**200**:

```json
{
  "data": [
    {
      "id": 4,
      "codigo": "AB12CD34",
      "cliente": { "id": 7, "nome": "José Wilson" },
      "autor": "Cliente",
      "modalidade": "Total",
      "situacao": "Creditado",
      "oferta": "182.40",
      "valor": "182.40",
      "margem": "10.00",
      "valor_apostado_antes": "50.00",
      "premio_antes": "306.00",
      "teria_pago": "306.00",
      "decidido_em": "…",
      "creditado_em": "…",
      "desfeito_em": null
    }
  ],
  "meta": { "current_page": 1, "last_page": 3, "per_page": 20, "total": 51 },
  "totais": { "valor": "1840.20", "teria_pago": "2210.00" }
}
```

`autor`: "Cliente", "Sistema" (automático) ou o nome do usuário do painel (acesso assistido).

### `PUT /clientes/{cliente}/configuracoes` (existente)

Aceita o campo novo opcional `encerrar_aposta` (boolean). `GET` passa a devolvê-lo.

---

## 5. Páginas e arquivos (web)

| Rota | Resposta |
|---|---|
| `GET /bilhete/{codigo}` | página Inertia `Ticket`, com as props `codigo` e `tempo_real` (`{ chave, host, porta, esquema }` do Reverb, lidas da configuração a cada carga) |
| `GET /sw-notificacoes.js` | `resources/pwa/push_notifications.js`, `application/javascript`, `Cache-Control: no-cache` (R-14) |

## 6. Coleção do Postman

`docs/postman/wssports_api.postman_collection.json` regenerada com as 12 rotas novas da API e o
campo novo de `clientes/{cliente}/configuracoes`.
