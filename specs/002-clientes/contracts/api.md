# Contrato HTTP: Clientes (área do cliente e gestão no painel)

**Feature**: `002-clientes` | **Data**: 2026-09-29 | **Plano**: [../plan.md](../plan.md)

Rotas novas em `routes/api.php` (prefixo `/api`). Caminhos de rota e prefixos em kebab-case
(constituição v1.16.0); parâmetros de query string e chaves JSON em snake_case. As rotas da spec 001
não mudam.

```php
// área do cliente (guard clientes)
Route::prefix('area-cliente')->group(function () {
    // limite de 5 cadastros por minuto por IP aplicado no StoreClientesRequest
    Route::post('cadastro', [AreaClienteCadastroController::class, 'store']);
    Route::post('auth/login', [AreaClienteAutenticacaoController::class, 'login']);
    Route::post('auth/recuperar-senha', [AreaClienteRecuperacaoSenhaController::class, 'solicitar']);
    Route::post('auth/redefinir-senha', [AreaClienteRecuperacaoSenhaController::class, 'redefinir']);

    Route::middleware(['auth:clientes', GarantirAcessoCliente::class])->group(function () {
        Route::post('auth/refresh', [AreaClienteAutenticacaoController::class, 'refresh']);
        Route::post('auth/logout', [AreaClienteAutenticacaoController::class, 'logout']);
        Route::get('meus-dados', [AreaClienteMeusDadosController::class, 'show']);
        Route::patch('meus-dados', [AreaClienteMeusDadosController::class, 'update']);
        Route::put('meus-dados/senha', [AreaClienteMeusDadosController::class, 'alterar_senha']);
        Route::get('meus-dados/extrato', [AreaClienteMeusDadosController::class, 'extrato']);
        Route::apiResource('meios-pagamento', AreaClienteMeiosPagamentoController::class)
            ->parameters(['meios-pagamento' => 'meio_pagamento']);
    });
});

// gestão no painel (guard api, mesmo token da spec 001)
Route::middleware(['auth:api', 'garantir_acesso'])->group(function () {
    Route::get('clientes/excluidos', [ClientesController::class, 'excluidos']);
    Route::post('clientes/{cliente}/restaurar', [ClientesController::class, 'restaurar'])->withTrashed();
    Route::patch('clientes/{cliente}/situacao', [ClientesController::class, 'alterar_situacao']);
    Route::apiResource('clientes', ClientesController::class)->except('store');

    Route::get('clientes/{cliente}/transacoes', [ClientesTransacoesController::class, 'index']);
    Route::post('clientes/{cliente}/transacoes', [ClientesTransacoesController::class, 'store']);

    Route::get('clientes/{cliente}/configuracoes', [ClientesConfiguracoesController::class, 'show']);
    Route::put('clientes/{cliente}/configuracoes', [ClientesConfiguracoesController::class, 'update']);

    Route::apiResource('clientes.meios-pagamento', ClientesMeiosPagamentoController::class)
        ->except('show')->parameters(['meios-pagamento' => 'meio_pagamento']);  // pertença ao cliente checada no controller

    Route::post('clientes-promocoes/{promocao}/estornar', [ClientesPromocoesController::class, 'estornar']);
    Route::apiResource('clientes-promocoes', ClientesPromocoesController::class)
        ->parameters(['clientes-promocoes' => 'promocao']);
});
```

Rotas com `{cliente}` usam `->missing(fn () => abort(404, 'Cliente não encontrado.'))`; com
`{promocao}`, "Promoção não encontrada."; com `{meio_pagamento}`, "Meio de pagamento não
encontrado.". Total: **15 rotas** na área do cliente e **21** no painel (as 2 rotas de
`clientes-configuracoes-padrao` foram removidas pela spec 003 em 2026-10-01).

## Regras gerais

- JSON (`Accept: application/json`); rotas protegidas exigem `Authorization: Bearer <token>`.
- `401 {"message": "Não autenticado."}`: sem token, token inválido, expirado, invalidado, emitido
  para o outro guard (cliente ↔ painel), anterior a `tokens_validos_desde` ou de cliente excluído
  (o guard não encontra registros com soft delete).
- `403 {"message": "Cliente sem permissão de acesso."}`: cliente **inativo** (área do cliente).
- `403 {"message": "Você não tem permissão para esta ação."}`: usuário do painel sem a permissão
  ou com função não permitida para ela.
- `404`: registro inexistente, excluído (exceto em `restaurar`) ou de outro cliente.
- `422`: validação, em português (`{"message": "...", "errors": {...}}`).
- `429 {"message": "Muitas tentativas. Tente novamente em N segundos."}`.
- `password` nunca aparece. Valores monetários vêm como texto com 2 casas (`"100.00"`). Valores de
  enum vêm em português com acentos (`"Promoção esportes"`, `"Ajuste manual"`).
- Listagens: `por_pagina` de 1 a 100, padrão 20, formato de paginação do Laravel.
- Filtros de query string enviados vazios (ex.: `?busca=&ativo=`) são ignorados, como se não
  tivessem sido enviados; valores preenchidos e inválidos continuam retornando `422`.

## Objeto `cliente`

```json
{
  "id": 12,
  "nome": "Maria Souza",
  "ddi": "55",
  "telefone": "11988887777",
  "email": "maria@mail.com",
  "cpf": "12345678909",
  "data_nascimento": "1990-05-10",
  "genero": "Feminino",
  "codigo_afiliado": "PARCEIRO10",
  "aceita_promocao": true,
  "ativo": true,
  "saldo": "70.00",
  "saldo_promocao_esportes": "20.00",
  "saldo_promocao_cassino": "0.00",
  "created_at": "2026-09-29T14:10:28.000000Z",
  "updated_at": "2026-09-29T14:10:28.000000Z"
}
```

`aceita_promocao` vem de `clientes_configuracoes`. No painel, sem `clientes.ver_dados_completos`:
`"cpf": "***.456.789-**"`, `"telefone": "(11) *****-7777"` (DDI 55; demais: só os 4 últimos
dígitos), `"email": "m***@mail.com"`. Na listagem de excluídos aparece `deleted_at`, e os dados
únicos vêm sem o sufixo.

## Área do cliente

| Rota | Corpo / parâmetros | Sucesso | Erros |
|---|---|---|---|
| `POST /api/area-cliente/cadastro` | `nome`, `ddi?` (padrão 55), `telefone`, `email?`, `password`, `password_confirmation`, `cpf?`, `data_nascimento`, `genero`, `codigo_afiliado?`, `aceita_promocao?` (padrão `true`) | `201 {"cliente", "token", "tipo": "bearer", "expira_em"}` | `429` (mais de 5 por minuto do mesmo IP); `422` (duplicidade, CPF/e-mail inválido, menor de 18, senha fraca ou confirmação diferente) |
| `POST /api/area-cliente/auth/login` | `ddi?`, `telefone`, `password` | `200 {"token", "tipo": "bearer", "expira_em": 3600}` | `401 "Telefone ou senha inválidos."` (inclui excluído); `403` inativo; `422`; `429` |
| `POST /api/area-cliente/auth/refresh` | — | `200` com novo token | `401`; `403` |
| `POST /api/area-cliente/auth/logout` | — | `204` | `401` |
| `POST /api/area-cliente/auth/recuperar-senha` | `ddi?`, `telefone` | `200 {"message": "Se o telefone estiver cadastrado, enviaremos um código."}` (sempre igual) | `422`; `429` (menos de 1 min) |
| `POST /api/area-cliente/auth/redefinir-senha` | `ddi?`, `telefone`, `codigo`, `password`, `password_confirmation` | `204` (tokens anteriores deixam de valer) | `422 "Código inválido ou expirado."`; `422` senha fraca |
| `GET /api/area-cliente/meus-dados` | — | `200` `cliente` | `401`; `403` |
| `PATCH /api/area-cliente/meus-dados` | `nome?`, `genero?`, `email?`, `aceita_promocao?` | `200` `cliente` | `422` (inclui enviar `ddi`, `telefone`, `cpf` ou `data_nascimento`) |
| `PUT /api/area-cliente/meus-dados/senha` | `senha_atual`, `password`, `password_confirmation` | `204` (todos os tokens, inclusive o atual, deixam de valer) | `422 "A senha atual está incorreta."` |
| `GET /api/area-cliente/meus-dados/extrato` | `data_inicial?`, `data_final?` (Y-m-d), `carteira?`, `por_pagina?` | `200` paginado de `transacao` (sem `autor`) | `422` |
| `GET /api/area-cliente/meios-pagamento` | — | `200` lista de `meio_pagamento` (sem máscara) | `401` |
| `POST /api/area-cliente/meios-pagamento` | campos de `meio_pagamento` conforme `tipo` | `201` | `422` (tipo de chave × chave, duplicidade) |
| `GET /api/area-cliente/meios-pagamento/{meio_pagamento}` | — | `200` | `404` (inclui de outro cliente) |
| `PUT/PATCH /api/area-cliente/meios-pagamento/{meio_pagamento}` | campos; `principal: true` marca como principal | `200` | `404`; `422` |
| `DELETE /api/area-cliente/meios-pagamento/{meio_pagamento}` | — | `204` (se era o principal, o mais antigo restante vira principal) | `404` |

## Objeto `meio_pagamento`

```json
{
  "id": 3,
  "tipo": "Pix",
  "principal": true,
  "pix_nome_titular": "Maria Souza",
  "pix_tipo_chave": "E-mail",
  "pix_chave": "maria@mail.com",
  "banco_codigo": null, "banco_nome": null, "agencia": null, "conta": null,
  "conta_digito": null, "conta_tipo": null, "titular_nome": null, "titular_documento": null,
  "created_at": "2026-09-29T15:00:00.000000Z"
}
```

Tipo `"Transferência bancária"`: `banco_codigo`, `banco_nome`, `agencia`, `conta`, `conta_digito`,
`conta_tipo` (`"Corrente"`/`"Poupança"`), `titular_nome`, `titular_documento` obrigatórios e os
campos `pix_*` nulos. No painel, sem `clientes.ver_dados_completos`, `pix_chave`, `conta` e
`titular_documento` vêm com só os 4 últimos caracteres visíveis.

## Objeto `transacao`

```json
{
  "id": 40,
  "carteira": "Saldo",
  "tipo": "Débito",
  "origem": "Ajuste manual",
  "referencia_id": null,
  "valor": "30.00",
  "saldo_anterior": "100.00",
  "saldo_posterior": "70.00",
  "observacao": "Correção de lançamento",
  "autor": {"id": 1, "nome": "Administrador"},
  "created_at": "2026-09-29T15:00:00.000000Z"
}
```

`autor` é `null` quando a operação foi do sistema e não aparece na área do cliente.

## Gestão de clientes (painel)

| Rota | Permissão | Corpo / parâmetros | Sucesso | Erros |
|---|---|---|---|---|
| `GET /api/clientes` | `clientes.listar` | `busca?` (nome, telefone, CPF ou e-mail), `ativo?`, `ddi?`, `codigo_afiliado?`, `cadastro_de?`, `cadastro_ate?`, `idade_minima?`, `idade_maxima?`, `genero?`, `saldo_minimo?`, `saldo_maximo?`, `com_saldo_promocional?`, `saque_bloqueado?`, `com_cpf?`, `com_email?`, `ordenar_por?` (`nome`, `created_at`, `saldo`), `direcao?`, `por_pagina?` | `200` paginado | `422` |
| `GET /api/clientes/{cliente}` | `clientes.listar` | — | `200` | `404` |
| `PUT/PATCH /api/clientes/{cliente}` | `clientes.editar` | `nome?`, `ddi?`, `telefone?`, `email?`, `cpf?`, `data_nascimento?`, `genero?`, `codigo_afiliado?`, `password?` + `password_confirmation` | `200` | `404`; `422` (duplicidade; saldos não aceitos) |
| `PATCH /api/clientes/{cliente}/situacao` | `clientes.editar` | `ativo` | `200` | `404`; `422` |
| `DELETE /api/clientes/{cliente}` | `clientes.excluir` (Admin/Supervisor) | — | `204` (sufixo em telefone/CPF/e-mail) | `403`; `404` |
| `GET /api/clientes/excluidos` | `clientes.restaurar` (Admin/Supervisor) | mesmos filtros | `200` paginado | `403` |
| `POST /api/clientes/{cliente}/restaurar` | `clientes.restaurar` (Admin/Supervisor) | `ddi?`, `telefone?`, `cpf?`, `email?` (só para resolver conflito) | `200` | `403`; `404`; `422 "Cliente não está excluído."`; `422` com `errors.<campo>` = "Já está em uso por outro cliente; informe um novo valor." |
| `GET /api/clientes/{cliente}/transacoes` | `clientes.listar` | `data_inicial?`, `data_final?`, `carteira?`, `por_pagina?` | `200` paginado | `404`; `422` |
| `POST /api/clientes/{cliente}/transacoes` | `clientes.movimentar_saldo` | `carteira`, `tipo`, `valor` (> 0, 2 casas), `observacao` | `201` (origem `"Ajuste manual"`) | `404`; `422 "Saldo insuficiente."`; `422` |
| `GET /api/clientes/{cliente}/configuracoes` | `clientes.listar` | — | `200` `configuracoes` | `404` |
| `PUT /api/clientes/{cliente}/configuracoes` | `clientes.editar_configuracoes` | todos os campos de `configuracoes` | `200` | `404`; `422` |
| `GET /api/clientes/{cliente}/meios-pagamento` | `clientes.listar` | — | `200` lista (mascarada sem `ver_dados_completos`) | `404` |
| `POST /api/clientes/{cliente}/meios-pagamento` | `clientes.editar` | campos de `meio_pagamento` | `201` | `404`; `422` |
| `PUT/PATCH /api/clientes/{cliente}/meios-pagamento/{meio_pagamento}` | `clientes.editar` | campos; `principal?` | `200` | `404`; `422` |
| `DELETE /api/clientes/{cliente}/meios-pagamento/{meio_pagamento}` | `clientes.editar` | — | `204` | `404` |

```json
{
  "realizar_aposta": true,
  "apostar_ao_vivo": true,
  "apostar_outros_esportes": true,
  "cancelar_aposta": false,
  "aceita_promocao": true,
  "bloquear_saque": false,
  "quantidade_minima_opcoes": 1,
  "quantidade_maxima_opcoes": 20,
  "valor_minimo_aposta": "2.00",
  "valor_maximo_aposta": "1000.00",
  "premio_maximo": "50000.00",
  "valor_maximo_diario": "5000.00",
  "valor_maximo_saque_diario": "5000.00",
  "quantidade_maxima_saques_diaria": 5,
  "odd_minima": "1.90",
  "odd_maxima": "30.00",
  "esportes_permitidos": ["FUTEBOL", "HOQUEI NO GELO", "BAISEBOL"],
  "updated_at": "2026-09-29T15:00:00.000000Z"
}
```

## Promoções (painel)

| Rota | Permissão | Corpo / parâmetros | Sucesso | Erros |
|---|---|---|---|---|
| `GET /api/clientes-promocoes` | `clientes_promocoes.gerenciar` | `ativa?`, `modalidade?`, `categoria?`, `por_pagina?` | `200` paginado | `403` |
| `GET /api/clientes-promocoes/{promocao}` | `clientes_promocoes.gerenciar` | — | `200` | `404` |
| `POST /api/clientes-promocoes` | `clientes_promocoes.gerenciar` | `nome`, `descricao?`, `modalidade`, `categoria`, `tipo_ganho`, `valor`, `rollover`, `valor_minimo_aposta`, `valor_maximo_aposta`, `valor_maximo_deposito?`, `valor_maximo_conversao`, `odd_minima_aposta_simples`, `odd_minima_aposta_multipla`, `data_inicio`, `data_fim?`, `ativa?` | `201` | `422` (sobreposição: "Já existe uma promoção ativa desta categoria e modalidade no período."; Percentual fora de depósito; rollover < 1 em primeiro depósito; regras incoerentes) |
| `PUT/PATCH /api/clientes-promocoes/{promocao}` | `clientes_promocoes.gerenciar` | mesmos campos | `200` | `404`; `422` (inclui "Promoção já aplicada: valor, tipo de ganho, categoria e modalidade não podem mudar." e "Promoção estornada não pode ser alterada.") |
| `DELETE /api/clientes-promocoes/{promocao}` | `clientes_promocoes.gerenciar` | — | `204` | `404` |
| `POST /api/clientes-promocoes/{promocao}/estornar` | `clientes_promocoes.estornar` (Admin/Supervisor) | `motivo` (obrigatório, até 255) | `202` com a promoção (`estorno.situacao = "Em andamento"`) | `403`; `404`; `422 "Esta promoção já foi estornada."` |

```json
{
  "id": 1,
  "nome": "Bônus de boas-vindas",
  "descricao": "R$ 20 para apostar em esportes",
  "modalidade": "Esportes",
  "categoria": "Primeiro cadastro",
  "tipo_ganho": "Fixo",
  "valor": "20.00",
  "rollover": 5,
  "valor_minimo_aposta": "2.00",
  "valor_maximo_aposta": "100.00",
  "valor_maximo_deposito": null,
  "valor_maximo_conversao": "200.00",
  "odd_minima_aposta_simples": "1.50",
  "odd_minima_aposta_multipla": "1.30",
  "data_inicio": "2026-10-01T00:00:00.000000Z",
  "data_fim": null,
  "ativa": true,
  "vigente": true,
  "aplicada": false,
  "estorno": {
    "situacao": null, "motivo": null, "autor": null, "iniciado_em": null, "concluido_em": null,
    "total_clientes": 0, "clientes_processados": 0, "valor_total": "0.00"
  },
  "created_at": "2026-09-29T15:00:00.000000Z",
  "updated_at": "2026-09-29T15:00:00.000000Z"
}
```

## Uso interno (sem rota)

- `SaldoClientes::creditar(Clientes $cliente, Carteira $carteira, string $valor, OrigemTransacao $origem, ?Usuarios $autor = null, ?int $referencia_id = null, ?string $observacao = null): ClientesTransacoes`
- `SaldoClientes::debitar(...)`: mesma assinatura; lança `SaldoInsuficienteException` (`422
  "Saldo insuficiente."`).
- Job `EstornarPromocao(promocao_id)`: processa o estorno em lotes (research.md R-17).
- Eventos `ClienteCadastrado(cliente)` e `CodigoRecuperacaoGerado(cliente, codigo)`.
