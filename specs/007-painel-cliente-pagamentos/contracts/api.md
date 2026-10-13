# Contrato HTTP: painel do cliente, depósitos, saques e gateway

**Feature**: `007-painel-cliente-pagamentos` | **Data**: 2026-10-12 | **Plano**: [../plan.md](../plan.md)

Rotas em `routes/api.php` (prefixo `/api`) e duas em `routes/web.php`. Caminhos em kebab-case;
chaves JSON em snake_case; erros no padrão das specs anteriores (`401`, `403`, `404`, `422` com
`message` e `errors`, `429`). Valores em dinheiro como texto com duas casas (`"50.00"`).

```php
// público
Route::post('webhooks/mercado-pago', [WebhooksMercadoPagoController::class, 'store'])->middleware('throttle:120,1');

// área do cliente (guard clientes; aceita também o token do acesso assistido)
Route::prefix('area-cliente')->group(function () {
    Route::post('acesso-assistido', [AreaClienteAcessoAssistidoController::class, 'iniciar'])->middleware('throttle:10,1');

    Route::middleware(['auth:clientes', GarantirAcessoCliente::class])->group(function () {
        Route::post('acesso-assistido/encerrar', [AreaClienteAcessoAssistidoController::class, 'encerrar']);

        Route::get('minha-conta', [AreaClienteMinhaContaController::class, 'show']);
        Route::get('minha-conta/extrato', [AreaClienteMinhaContaController::class, 'extrato']);
        Route::post('bonus/{rollover}/converter', [AreaClienteBonusController::class, 'converter']);
        Route::post('bonus/{rollover}/excluir', [AreaClienteBonusController::class, 'excluir']);

        Route::get('depositos/opcoes', [AreaClienteDepositosController::class, 'opcoes']);
        Route::post('depositos', [AreaClienteDepositosController::class, 'store'])->middleware('throttle:10,1');
        Route::get('depositos/{codigo}', [AreaClienteDepositosController::class, 'show']);

        Route::post('saques', [AreaClienteSaquesController::class, 'store'])->middleware('throttle:10,1');

        Route::get('indicacoes', [AreaClienteIndicacoesController::class, 'index']);
    });
});

// painel (guard api)
Route::middleware(['auth:api', 'garantir_acesso'])->group(function () {
    Route::post('clientes/{cliente}/acesso-assistido', [AcessosAssistidosController::class, 'store']); // clientes.acessar_conta

    Route::get('saques', [SaquesController::class, 'index']);                       // saques.gerenciar
    Route::post('saques/{saque}/pagar', [SaquesController::class, 'pagar']);        // saques.gerenciar
    Route::post('saques/{saque}/recusar', [SaquesController::class, 'recusar']);    // saques.gerenciar

    Route::get('configuracoes/pagamentos', [ConfiguracoesPagamentosController::class, 'show']);   // configuracoes.editar
    Route::put('configuracoes/pagamentos', [ConfiguracoesPagamentosController::class, 'update']); // configuracoes.editar

    Route::get('configuracoes/gateway', [GatewayPagamentoController::class, 'show']);                        // gateway_pagamento.vincular
    Route::post('configuracoes/gateway/mercado-pago/autorizar', [GatewayPagamentoController::class, 'autorizar']); // gateway_pagamento.vincular
    Route::delete('configuracoes/gateway', [GatewayPagamentoController::class, 'destroy']);                  // gateway_pagamento.vincular
});
```

```php
// routes/web.php
Route::get('minha-conta', [MinhaContaController::class, 'index'])->name('minha_conta');
Route::get('mercado-pago/retorno', [RetornoMercadoPagoController::class, 'index'])->name('mercado_pago.retorno');
```

Total: **1 pública**, **11 da área do cliente**, **9 do painel** (21 na API) e **2 web**. As rotas
de promoções (`clientes-promocoes`) e de cadastro (`area-cliente/cadastro`) mudam de corpo
(seções 9 e 10).

## 1. `GET /api/area-cliente/minha-conta`

```json
{
  "data": {
    "cliente": { "id": 7, "nome": "Luana Rodrigues", "ativo": true, "codigo_indicacao": "K7Q2M9XA" },
    "saldos": {
      "saldo": { "valor": "0.50", "anterior": "5.50", "meta": "0.50", "negativo": false },
      "bonus_esportes": { "valor": "0.00", "anterior": "0.00", "meta": "0.00" },
      "bonus_cassino": { "valor": "0.00", "anterior": "0.00", "meta": "0.00" }
    },
    "cadastro": { "falta_para_depositar": [], "falta_para_sacar": ["pix"] },
    "menu": { "indicacao": { "vigente": true, "quantidade": 5, "valor": "20.00" } },
    "acesso_assistido": null
  }
}
```

`anterior` = `saldo_anterior` da última transação da carteira; `meta` = soma de
`valor_exigido − valor_apostado` dos rollovers pendentes (Depósito no saldo; Bônus por carteira).
`acesso_assistido` (no token assistido): `{ "usuario": "Supervisor X", "expira_em": "..." }`.
Cliente inativo: `403 {"message": "Pagamento pendente ou conta desativada."}`.

## 2. `GET /api/area-cliente/minha-conta/extrato`

Query: `operacao` (`palpites`, `depositos`, `saques`, `bonus`, `cashback`, `estornos`), `situacao`
(só com `palpites`: `Aguardando`, `Vencedor`, `Perdedor`), `pagina`, `por_pagina` (padrão 30,
máximo 100).

```json
{
  "data": [
    { "id": 901, "data": "2026-10-11T16:22:00-03:00", "operacao": "Palpite", "tipo": "Débito", "valor": "5.00",
      "aposta": { "codigo": "NNYMBT2Q", "premio": "341.03", "resultado": "Aguardando", "cancelada": false } },
    { "id": 880, "data": "2026-10-11T16:17:00-03:00", "operacao": "Depósito", "tipo": "Crédito", "valor": "10.00",
      "rollover": { "exigido": "10.50", "restante": "0.50" } },
    { "id": 870, "data": "...", "operacao": "Bônus", "tipo": "Crédito", "valor": "20.00",
      "bonus": { "rollover_id": 33, "restante": "180.00", "liberado": "0.00", "expira_em_dias": 3, "expirado": false,
                 "pode_converter": false, "pode_excluir": true } },
    { "id": 860, "data": "...", "operacao": "Saque", "tipo": "Débito", "valor": "50.00",
      "saque": { "situacao": "Recusado", "motivo": "Chave Pix inválida" } }
  ],
  "links": {}, "meta": { "current_page": 1, "per_page": 30, "total": 4 }
}
```

`data` = data do pagamento no depósito (`pago_em`). Rótulos de `operacao`: `Palpite`, `Prêmio`,
`Depósito`, `Cashback`, `Saque`, `Devolução de saque`, `Estorno de depósito`, `Bônus`,
`Conversão de bônus`, `Bônus expirado`, `Bônus excluído`, `Ajuste`.

## 3. `POST /api/area-cliente/bonus/{rollover}/converter` e `/excluir`

Sem corpo. `{rollover}` = id do rollover de bônus do próprio cliente (senão 404).

- Converter `200`: `{"message": "O valor de R$ 50,00 foi convertido para o seu saldo.", "valor": "50.00"}`.
- Excluir `200`: `{"message": "Bônus excluído com sucesso."}`.
- Recusas `422` com as mensagens do FR-017/FR-018 e "Cumpra o rollover dos outros bônus desta
  modalidade antes de converter." (research R-22).

## 4. `GET /api/area-cliente/depositos/opcoes`

```json
{
  "data": {
    "disponivel": true, "motivo": null,
    "minimo": "1.00", "maximo": "50000.00", "valores_rapidos": ["20.00", "50.00", "100.00", "200.00"],
    "meios": ["Pix", "Cartão de crédito"], "parcelas_maximas": 12,
    "public_key": "APP_USR-...", "aceita_bonus": true,
    "falta_no_cadastro": []
  }
}
```

`disponivel = false` com `motivo` "Depósitos indisponíveis no momento. Tente mais tarde." sem
gateway vinculado ou com depósitos desligados. `falta_no_cadastro` ∈ `cpf`, `email`.

## 5. `POST /api/area-cliente/depositos`

```json
{ "valor": "50.00", "meio": "Pix", "aceita_bonus": true }
{ "valor": "300.00", "meio": "Cartão de crédito", "aceita_bonus": false,
  "cartao": { "token": "ff8080...", "parcelas": 3, "payment_method_id": "visa", "issuer_id": "25" } }
```

Validação: `valor` entre mínimo e máximo, duas casas; `meio` habilitado; `cartao.*` obrigatórios
no cartão; `parcelas` 1..`parcelas_maximas`. Cadastro sem CPF/e-mail: `422` com
`faltando = ["cpf", "email"]` (e `errors.cadastro` com a mensagem) e message "Informe seu CPF e e-mail para depositar."

`201`:

```json
{ "data": { "codigo": "0b5c...", "meio": "Pix", "valor": "50.00", "situacao": "Pendente",
  "pix": { "copia_cola": "000201...", "qr_code_base64": "iVBORw0...", "expira_em": "2026-10-12T10:30:00-03:00" },
  "mensagem": null } }
```

Cartão aprovado: `situacao = "Aprovado"`; em análise: `"Em análise"`; recusado: `422` com a
mensagem do `status_detail` (ex.: "Pagamento recusado pelo banco emissor. Tente outro cartão.").

## 6. `GET /api/area-cliente/depositos/{codigo}`

Só o dono (ou o acesso assistido dele); senão `404`. `{ "data": { "codigo", "situacao", "valor", "pago_em" } }`.

## 7. `POST /api/area-cliente/saques`

`{ "valor": "100.00" }` → `201 {"data": {"codigo", "valor", "situacao": "Pendente"}, "message": "Saque solicitado com sucesso. O valor estará disponível em até 24 horas."}`.
Recusas `422` com as mensagens do FR-035; cadastro incompleto:
`faltando = ["email", "cpf", "pix"]` (e `errors.cadastro` com a mensagem) e message "Complete seus dados para sacar.".

## 8. `GET /api/area-cliente/indicacoes`

Query `pagina`, `por_pagina` (padrão 30, máximo 100).

```json
{ "data": [ { "nome": "Carlos S.", "cadastrado_em": "2026-10-01", "situacao": "Depositou" },
            { "nome": "Ana P.", "cadastrado_em": "2026-10-05", "situacao": "Pendente de depósito" } ],
  "resumo": { "validas": 6, "meta": 5, "faltam_para_proximo": 4, "bonus_recebidos": 1, "valor_bonus": "20.00",
              "link": "https://banca.com/?indicacao=K7Q2M9XA" },
  "meta": { "current_page": 1, "per_page": 30, "total": 7 } }
```

Nome do indicado: primeiro nome + inicial do sobrenome; sem telefone, e-mail, CPF ou valores.

## 9. Acesso assistido

- `POST /api/clientes/{cliente}/acesso-assistido` (painel) → `201 {"url": "https://banca.com/minha-conta?acesso=...", "expira_em": "..."}`; cliente excluído → `404`.
- `POST /api/area-cliente/acesso-assistido` `{ "codigo": "..." }` → `{"token", "tipo": "bearer", "expira_em", "cliente": {"nome"}}`; código inválido, usado ou vencido → `422 "Acesso expirado. Gere um novo pelo painel."`.
- `POST /api/area-cliente/acesso-assistido/encerrar` → `204`.

## 10. Painel: saques, configurações e gateway

- `GET /api/saques?situacao=Pendente&pagina=1&por_pagina=30` → lista com cliente (nome,
  telefone), valor, chave Pix, modo, situação, `motivo_falha`, datas; paginada (máx. 100).
- `POST /api/saques/{saque}/pagar` `{ "forma": "manual" | "mercado_pago" }` → `Pago` (manual) ou
  `Processando` (envio automático); saque não pendente → `422`.
- `POST /api/saques/{saque}/recusar` `{ "motivo": "Chave Pix inválida" }` (obrigatório, até 255).
- `GET/PUT /api/configuracoes/pagamentos`: campos da `configuracoes` do data-model, **sem**
  `percentual_comissao_gateway` (ignorado se enviado). `meios_deposito` ⊆ Pix, Cartão de crédito;
  `parcelas_maximas` 1..12; `deposito_minimo` ≤ `deposito_maximo`.
- `GET /api/configuracoes/gateway` → `{"gateway": "Mercado Pago", "situacao": "Vinculado", "vinculado_em": "...", "expira_em": "...", "falha_renovacao": null}` (sem tokens).
- `POST /api/configuracoes/gateway/mercado-pago/autorizar` → `{"url": "https://auth.mercadopago.com.br/authorization?..."}`; sem credenciais no ambiente → `422 "Credenciais da aplicação não configuradas no servidor."`.
- `DELETE /api/configuracoes/gateway` → `204` (situação `Desvinculado`, tokens apagados).

## 11. Rotas existentes que mudam

- `POST/PUT /api/clientes-promocoes`: campos novos `dias_cumprir` (obrigatório, inteiro ≥ 1,
  padrão 30), `rollover_primeiro_deposito` (boolean; `true` só em Primeiro depósito),
  `codigo_afiliado` (texto até 50, opcional), `quantidade_indicacoes` (obrigatório ≥ 1 em
  Indicação; proibido nas demais). O resource devolve os quatro.
- `POST /api/area-cliente/cadastro`: campo opcional `codigo_indicacao` (8 caracteres; inexistente
  → ignorado sem erro, para não travar o cadastro).
- `PATCH /api/area-cliente/meus-dados`: `cpf`, `email` e `data_nascimento` aceitos só quando
  estiverem vazios no cliente; preenchidos → `422 "Este dado só pode ser alterado pelo
  atendimento."` (research R-21). `telefone` continua proibido.

## 12. Webhook

`POST /api/webhooks/mercado-pago` (corpo do Mercado Pago, ex.: `{"type": "payment", "data": {"id": "123"}}`)
→ sempre `200 {"recebido": true}` depois de gravar a notificação; o processamento é na fila.
