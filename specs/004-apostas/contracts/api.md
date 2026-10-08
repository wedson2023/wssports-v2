# Contrato HTTP: Apostas

**Feature**: `004-apostas` | **Data**: 2026-10-07 | **Plano**: [../plan.md](../plan.md)

Rotas em `routes/api.php` (prefixo `/api`). Caminhos em kebab-case; chaves JSON em snake_case
(constituição). As rotas das specs 001 a 003 não mudam; as de configurações (`usuarios-configuracoes`,
`visitantes-configuracoes`, `clientes/{cliente}/configuracoes`) passam a aceitar os campos novos
([data-model.md](../data-model.md)).

```php
// detalhe de um confronto com todas as cotações: sem login; aceita token de cliente ou do painel
Route::get('publico/confrontos/{confronto}', [PublicoConfrontosDetalheController::class, 'pre_jogo']);
Route::get('publico/confrontos-ao-vivo/{confronto_ao_vivo}', [PublicoConfrontosDetalheController::class, 'ao_vivo']);

// site: sem login (visitante)
Route::post('publico/apostas', [PublicoApostasController::class, 'store']);
Route::get('publico/apostas/{codigo}', [PublicoApostasController::class, 'show']);
Route::post('publico/apostas/consultar', [PublicoApostasController::class, 'consultar']);

// área do cliente (guard clientes)
Route::prefix('area-cliente')->middleware(['auth:clientes', GarantirAcessoCliente::class])->group(function () {
    Route::post('apostas', [AreaClienteApostasController::class, 'store']);
    Route::get('apostas/{codigo}/situacao', [AreaClienteApostasController::class, 'situacao']);
});

// painel (guard api)
Route::middleware(['auth:api', 'garantir_acesso'])->group(function () {
    Route::post('apostas', [ApostasController::class, 'store']);                                   // vendedor
    Route::get('apostas/{codigo}/situacao', [ApostasController::class, 'situacao']);               // vendedor
    Route::get('apostas/pendentes/{codigo}', [ValidacaoApostasController::class, 'show']);         // simulação
    Route::post('apostas/pendentes/{codigo}/validar', [ValidacaoApostasController::class, 'store']);
    Route::post('apostas/{codigo}/cancelar', [CancelamentoApostasController::class, 'store']);
    Route::post('apostas/{codigo}/palpites/{palpite}/cancelar', [ApostasPalpitesController::class, 'cancelar']);
    Route::post('apostas/{codigo}/palpites/{palpite}/restaurar', [ApostasPalpitesController::class, 'restaurar']);
    Route::patch('confrontos/{confronto}/limite', [ConfrontosLimitesController::class, 'pre_jogo']);
    Route::patch('confrontos-ao-vivo/{confronto_ao_vivo}/limite', [ConfrontosLimitesController::class, 'ao_vivo']);
});
```

Total: **5 rotas públicas**, **2 da área do cliente** e **9 do painel** (16 novas).

## Regras gerais

- JSON (`Accept: application/json`). Painel: `Authorization: Bearer <token>` do guard `api`; área do
  cliente: token do guard `clientes`.
- `{codigo}` é o código de 8 caracteres (maiúsculas; o servidor converte para maiúsculas). Aposta
  inexistente ou fora do alcance de quem pede → `404 {"message": "Aposta não encontrada."}`.
- `403 {"message": "Você não tem permissão para esta ação."}`: sem a permissão ou função que não
  pode usá-la (`Funcao::usuario_pode`).
- `409`: cotação alterada; precisa confirmar (ver abaixo). Nada foi gravado.
- `422`: regra da aposta ou validação, em português (`{"message": "...", "errors": {...}}`). Com
  palpites indisponíveis, traz `indisponiveis`.
- `429 {"message": "Muitas tentativas. Tente novamente em N segundos."}` (R-10 do research).
- Valores e cotações como texto com 2 casas (`"10.00"`, `"1.85"`), para não perder precisão.
- Datas em ISO 8601 no fuso pedido em `fuso_horario` (query ou corpo; padrão `-03:00`).

## Corpo do pedido de aposta

Usado em `POST /publico/apostas`, `POST /area-cliente/apostas`, `POST /apostas` e
`POST /apostas/pendentes/{codigo}/validar`.

```json
{
  "chave_idempotencia": "5f6c2b8e-6a0b-4c1e-9d3f-2a7b1c9e4d10",
  "nome": "João",
  "valor": "10.00",
  "aceitar_alteracoes": "Nenhuma",
  "palpites": [
    { "confrontos_id": 101, "codigo_cotacao": "odd1", "cotacao_vista": "2.00" },
    { "confrontos_ao_vivo_id": 55, "codigo_cotacao": "odd3", "cotacao_vista": "1.50" },
    { "confrontos_id": 102, "codigo_cotacao": "jogador", "confrontos_jogadores_id": 9001, "cotacao_vista": "3.20" }
  ]
}
```

| Campo | Regras |
|---|---|
| chave_idempotencia | obrigatório; UUID (na validação, opcional) |
| nome | obrigatório; até 100 caracteres; HTML removido |
| valor | obrigatório; > 0; até 2 casas |
| aceitar_alteracoes | `Nenhuma` (padrão), `Somente para maior`, `Qualquer` |
| palpites | 1 a 50 itens |
| palpites.*.confrontos_id **ou** confrontos_ao_vivo_id | exatamente um: pré-jogo ou ao vivo (o servidor confere: pré-jogo que já está no ao vivo é recusado, FR-017) |
| palpites.*.codigo_cotacao | `odd1`…`odd323` ou `jogador` |
| palpites.*.confrontos_jogadores_id | obrigatório com `jogador`; só pré-jogo; o registro do jogador já define o tipo (Primeiro, Último, Qualquer momento), lido do banco |
| palpites.*.cotacao_vista | obrigatório; ≥ 1.00; 2 casas |

Campos fora desta lista são ignorados (prêmio, esporte, horários etc. nunca são lidos, FR-005).

## Respostas da criação e da validação

**201 — aposta Ativa** (pré-jogo) ou **201 — Pendente** (visitante): `{"data": <Comprovante>}`.

**202 — Em análise** (ao vivo):

```json
{ "data": { "codigo": "K7M2Q9XA", "situacao": "Em análise", "segundos_restantes": 15 } }
```

**200 — envio repetido** com a mesma chave de idempotência e o mesmo apostador: a aposta já gravada.

**409 — cotação alterada** (FR-029, FR-039a):

```json
{
  "message": "Houve alteração nas cotações. Confira o novo prêmio e confirme para continuar.",
  "alteracoes": [
    { "indice": 0, "confrontos_id": 101, "codigo_cotacao": "odd1", "cotacao_vista": "2.00", "cotacao_atual": "1.80" }
  ],
  "cotacao_total": "2.70",
  "premio": "27.00",
  "valor_acrescido": "0.00",
  "total_a_pagar": "27.00"
}
```

O apostador confirma reenviando com `cotacao_vista` = `cotacao_atual` (pode repetir a chave de
idempotência, pois nada foi gravado).

**422 — palpites indisponíveis** (FR-027):

```json
{
  "message": "Há palpites indisponíveis. Remova-os para continuar.",
  "indisponiveis": [ { "indice": 1, "confrontos_ao_vivo_id": 55, "motivo": "Cotação indisponível." } ]
}
```

**422 — regras**: mensagens de FR-014 a FR-023 e FR-043 a FR-045 (ex.: "O confronto CASA x FORA já
iniciou, retire-o para concluir.", "Restam R$ 5,00 do seu limite simples.", "Você não tem saldo
suficiente para realizar esta aposta.").

## Comprovante (`ComprovanteApostaResource`)

```json
{
  "codigo": "K7M2Q9XA",
  "situacao": "Ativa",
  "resultado": "Aguardando",
  "tipo": "Pré-jogo",
  "nome": "João",
  "vendedor": "Carlos",
  "nome_sistema": "WSSports",
  "criada_em": "2026-10-07T14:30:00-03:00",
  "validada_em": null,
  "valor": "10.00",
  "cotacao_total": "3.00",
  "premio": "30.00",
  "valor_acrescido": "0.00",
  "total_a_pagar": "30.00",
  "premio_liquido": "27.00",
  "forma_pagamento": "Dinheiro",
  "mensagem_bilhete": "BOA SORTE!",
  "assinatura": "9f2c…",
  "palpites": [
    {
      "id": 1, "situacao": "Ativo", "campeonato": "Brasileirão", "time_casa": "Time A", "time_fora": "Time B",
      "data_inicio": "2026-10-07T16:00:00-03:00", "esporte": "FUTEBOL", "codigo_cotacao": "odd1",
      "mercado": "Casa", "jogador": null, "jogador_tipo": null, "cotacao": "2.00",
      "placar_casa": null, "placar_fora": null, "minuto": null
    }
  ]
}
```

- `vendedor`: nome do vendedor ou `"Cliente"`; nulo na Pendente.
- `premio_liquido`: total menos `comissao_por_premio`% só em `Dinheiro`; igual ao total nos demais.
- `mensagem_bilhete`: lida na hora das configurações atuais (não fica gravada na aposta): a do
  vendedor da aposta; sem vendedor (cliente ou visitante Pendente), a de `configuracoes`.
- `comissao` e `percentual_comissao` só aparecem para o vendedor da aposta e para a hierarquia dele.
- Pendente: `assinatura` nula; Em análise: só `codigo`, `situacao`, `segundos_restantes`;
  Recusada/Expirada: acrescenta `motivo_recusa`.

## Rotas

### `GET /api/publico/confrontos/{confronto}` e `GET /api/publico/confrontos-ao-vivo/{confronto_ao_vivo}` — detalhe (FR-065a, FR-065b)

Sem login; com token de cliente ou do painel, identifica o público como a listagem
(`IdentificacaoPublico`). Query `fuso_horario` (padrão `-03:00`). 120/min por IP, como a listagem.
Confronto não visível para o público (não permitido, inativo, fora do período, data de travamento,
esporte, já no ao vivo para o pré-jogo) → `404 {"message": "Confronto não encontrado."}`.

```json
{
  "data": {
    "token_recusado": false,
    "id": 101, "tipo": "pre_jogo", "campeonato": "Brasileirão", "pais": "Brasil",
    "time_casa": "Time A", "escudo_casa": "…", "time_fora": "Time B", "escudo_fora": "…",
    "esporte": "FUTEBOL", "data_inicio": "2026-10-07T16:00:00-03:00",
    "cotacoes": [
      { "codigo_cotacao": "odd1", "mercado": "Casa", "cotacao": "2.00" },
      { "codigo_cotacao": "odd2", "mercado": "Empate", "cotacao": "3.10" }
    ],
    "jogadores": [
      { "confrontos_jogadores_id": 9001, "nome": "Fulano", "opcao": "Time A",
        "tipo": "Qualquer momento", "cotacao": "3.20" }
    ]
  }
}
```

- `cotacoes`: só os códigos com cotação ajustada maior que zero, na ordem dos códigos.
- `jogadores`: só no pré-jogo e com `apostar_jogadores` liberado; caso contrário, lista vazia.
- Ao vivo: acrescenta `placar_casa`, `placar_fora`, `minuto`, `cronometro`, `situacao` e `travado`;
  travado → todas as cotações `"0.00"`.

### `POST /api/publico/apostas` — visitante gera o código (FR-037)

Sem token. Só pré-jogo. 10/min por IP. **201** com o comprovante (`situacao: "Pendente"`,
`expira_em`). Jogo ao vivo → 422 "Para apostar no ao vivo é preciso fazer login." Com
`data_travamento_sistema` passada → 422 "Sistema travado, procure seu gerente."

### `GET /api/publico/apostas/{codigo}` — comprovante pelo código (FR-056)

Sem token. 60/min por IP. **200** `{"data": <Comprovante>}`; sem dados internos (FR-054).

### `POST /api/publico/apostas/consultar` — vários códigos (FR-056)

Corpo `{"codigos": ["K7M2Q9XA", "..."]}` (1 a 50). **200** `{"data": [{codigo, valor, premio,
total_a_pagar, situacao, resultado, criada_em}]}`; códigos inexistentes são ignorados.

### `POST /api/area-cliente/apostas` — cliente aposta (FR-043 a FR-048)

Corpo padrão. **201** Ativa, **202** Em análise, **409**, **422**.

### `GET /api/area-cliente/apostas/{codigo}/situacao` e `GET /api/apostas/{codigo}/situacao`

Só o apostador da aposta (cliente ou vendedor). 60/min. **200** com o comprovante (Ativa), a
situação e `segundos_restantes` (Em análise) ou o `motivo_recusa` (Recusada).

### `POST /api/apostas` — vendedor aposta

Permissão `apostas.criar` (só Vendedor). **201** Ativa, **202** Em análise, **409**, **422**.

### `GET /api/apostas/pendentes/{codigo}` — simulação (FR-038)

Permissão `apostas.validar`. 20/min por vendedor e 60/min por IP. Não grava nada.

```json
{
  "data": {
    "codigo": "K7M2Q9XA", "nome": "João", "valor": "10.00", "expira_em": "2026-10-09T14:30:00-03:00",
    "cotacao_total": "3.00", "premio": "30.00", "valor_acrescido": "0.00", "total_a_pagar": "30.00",
    "palpites": [
      { "indice": 0, "confrontos_id": 101, "codigo_cotacao": "odd1", "mercado": "Casa",
        "time_casa": "Time A", "time_fora": "Time B", "data_inicio": "…",
        "cotacao": "2.00", "disponivel": true, "motivo": null }
    ]
  }
}
```

`cotacao` já é a do vendedor; palpites fora das regras vêm com `disponivel: false` e `motivo`.
Expirada ou já validada → 404 "Aposta não encontrada ou já validada." / 422 "Aposta expirada."

### `POST /api/apostas/pendentes/{codigo}/validar` — validação (FR-039, FR-039a)

Permissão `apostas.validar`. Corpo padrão (palpites só pré-jogo, ajustados pelo vendedor;
`cotacao_vista` = a cotação da simulação). **200** com o comprovante (mesmo código, `Ativa`), **409**
se a cotação mudou desde a simulação, **404** se já validada, **422** regras.

### `POST /api/apostas/{codigo}/cancelar` — cancelamento (FR-050 a FR-052)

Permissão `apostas.cancelar`. Vendedor: só as próprias, dentro do tempo, sem jogo iniciado e sem
ao vivo. Gerente/Supervisor/Admin: hierarquia e apostas de cliente; jogo iniciado só com
`apostas.cancelar_iniciada`. Só resultado `Aguardando`. **200** com o comprovante (`Cancelada`);
**422** com o motivo.

### `POST /api/apostas/{codigo}/palpites/{palpite}/cancelar` e `.../restaurar` (FR-052a a FR-052e)

Permissão `apostas.editar` (Gerente, Supervisor e Admin). `{palpite}` é o `id` do palpite na
aposta. **200** com o comprovante recalculado; **422** "Não é possível cancelar o último palpite
ativo. Cancele a aposta." ou situação inválida.

### `PATCH /api/confrontos/{confronto}/limite` e `PATCH /api/confrontos-ao-vivo/{confronto_ao_vivo}/limite` (FR-063)

Permissão `confrontos.alterar_limite` (Admin e Supervisor). Corpo `{"limite_valor_apostado":
"30000.00"}` (> 0, 2 casas). **200** `{"data": {"id": 101, "limite_valor_apostado": "30000.00",
"valor_apostado": "1250.00"}}`.

## Configurações (rotas existentes, campos novos)

- `PATCH /api/usuarios-configuracoes` (spec 003): aceita os campos novos de
  `usuarios_configuracoes` (FR-059), com as validações de FR-064.
- `PUT /api/visitantes-configuracoes` (spec 003): aceita os campos novos de
  `visitantes_configuracoes` (FR-061).
- `PUT /api/clientes/{cliente}/configuracoes` (spec 002): aceita os campos novos de
  `clientes_configuracoes` (FR-060).
- `nome_sistema` e `mensagem_bilhete` de `configuracoes` continuam sem rota (alterados no banco,
  como as demais configurações gerais da spec 003).
