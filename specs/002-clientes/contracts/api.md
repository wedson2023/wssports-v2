# Contrato HTTP: Clientes (área do cliente e gestão no painel)

**Feature**: `002-clientes` | **Data**: 2026-09-29 | **Plano**: [../plan.md](../plan.md)

Rotas novas em `routes/api.php` (prefixo `/api`). As rotas da spec 001 não mudam.

```php
// área do cliente (guard clientes)
Route::prefix('area_cliente')->group(function () {
    Route::post('cadastro', [AreaClienteCadastroController::class, 'store']);
    Route::post('auth/login', [AreaClienteAutenticacaoController::class, 'login']);
    Route::post('auth/recuperar_senha', [AreaClienteRecuperacaoSenhaController::class, 'solicitar']);
    Route::post('auth/redefinir_senha', [AreaClienteRecuperacaoSenhaController::class, 'redefinir']);

    Route::middleware(['auth:clientes', GarantirAcessoCliente::class])->group(function () {
        Route::post('auth/refresh', [AreaClienteAutenticacaoController::class, 'refresh']);
        Route::post('auth/logout', [AreaClienteAutenticacaoController::class, 'logout']);
        Route::get('meus_dados', [AreaClienteMeusDadosController::class, 'show']);
        Route::patch('meus_dados', [AreaClienteMeusDadosController::class, 'update']);
        Route::put('meus_dados/senha', [AreaClienteMeusDadosController::class, 'alterar_senha']);
        Route::get('meus_dados/extrato', [AreaClienteMeusDadosController::class, 'extrato']);
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

    Route::get('clientes_configuracoes_padrao', [ClientesConfiguracoesPadraoController::class, 'show']);
    Route::put('clientes_configuracoes_padrao', [ClientesConfiguracoesPadraoController::class, 'update']);

    Route::apiResource('clientes_promocoes', ClientesPromocoesController::class);
});
```

Todas as rotas `{cliente}` usam `->missing(fn () => abort(404, 'Cliente não encontrado.'))`.

## Regras gerais

- JSON (`Accept: application/json`); rotas protegidas exigem `Authorization: Bearer <token>`.
- `401 {"message": "Não autenticado."}`: sem token, token inválido, expirado, invalidado, emitido
  para o outro guard (cliente ↔ painel) ou anterior a `tokens_validos_desde`.
- `403 {"message": "Cliente sem permissão de acesso."}`: cliente **inativo** (área do cliente).
  Cliente **excluído** recebe `401`: o guard não encontra registros com soft delete, e no login o
  telefone já tem o sufixo de exclusão (cai no `401` genérico).
- `403 {"message": "Você não tem permissão para esta ação."}`: usuário do painel sem a permissão
  ou com função não permitida para ela.
- `404 {"message": "Cliente não encontrado."}`: inexistente ou excluído (exceto em `restaurar`).
- `422`: validação, em português (`{"message": "...", "errors": {...}}`).
- `429 {"message": "Muitas tentativas. Tente novamente em N segundos."}`.
- `password` nunca aparece nas respostas. Valores monetários vêm como texto com 2 casas
  (`"100.00"`).
- Listagens paginadas: `por_pagina` de 1 a 100, padrão 20; resposta no formato de paginação do
  Laravel (`data`, `links`, `meta`).

## Objeto `cliente` (área do cliente e painel)

```json
{
  "id": 12,
  "nome": "Maria Souza",
  "codigo_pais": "55",
  "telefone": "11988887777",
  "cpf": "12345678909",
  "data_nascimento": "1990-05-10",
  "genero": "feminino",
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

No painel, sem `clientes.ver_dados_completos`: `"cpf": "***.456.789-**"` e
`"telefone": "(11) *****-7777"` (código 55: DDD e 4 últimos dígitos; demais países: só os 4
últimos dígitos, ex.: `"******4567"`). Na listagem de excluídos aparece também
`deleted_at`, e `telefone`/`cpf` vêm sem o sufixo.

## Área do cliente

| Rota | Corpo / parâmetros | Sucesso | Erros |
|---|---|---|---|
| `POST /api/area_cliente/cadastro` | `nome`, `codigo_pais?` (padrão 55), `telefone`, `password`, `password_confirmation`, `cpf`, `data_nascimento`, `genero`, `codigo_afiliado?`, `aceita_promocao?` (padrão `true`) | `201` com `cliente` e `token` | `422` (duplicidade, CPF inválido, menor de 18, senha fraca ou confirmação diferente) |
| `POST /api/area_cliente/auth/login` | `codigo_pais?`, `telefone`, `password` | `200 {"token", "tipo": "bearer", "expira_em": 3600}` | `401 "Telefone ou senha inválidos."`; `403` inativo (excluído cai no `401` genérico); `422`; `429` |
| `POST /api/area_cliente/auth/refresh` | — | `200` com novo token | `401`; `403` |
| `POST /api/area_cliente/auth/logout` | — | `204` | `401` |
| `POST /api/area_cliente/auth/recuperar_senha` | `codigo_pais?`, `telefone` | `200 {"message": "Se o telefone estiver cadastrado, enviaremos um código."}` (sempre igual) | `422`; `429` (menos de 1 min desde o último pedido) |
| `POST /api/area_cliente/auth/redefinir_senha` | `codigo_pais?`, `telefone`, `codigo`, `password`, `password_confirmation` | `204` (tokens anteriores deixam de valer) | `422 "Código inválido ou expirado."`; `422` senha fraca |
| `GET /api/area_cliente/meus_dados` | — | `200` com `cliente` | `401`; `403` |
| `PATCH /api/area_cliente/meus_dados` | `nome?`, `genero?`, `aceita_promocao?` | `200` com `cliente` | `422` (inclui tentar enviar `telefone`, `codigo_pais`, `cpf` ou `data_nascimento`) |
| `PUT /api/area_cliente/meus_dados/senha` | `senha_atual`, `password`, `password_confirmation` | `204` (outros tokens deixam de valer; o cliente precisa entrar de novo) | `422 "A senha atual está incorreta."` |
| `GET /api/area_cliente/meus_dados/extrato` | `data_inicial?`, `data_final?` (Y-m-d), `carteira?`, `por_pagina?` | `200` paginado de `transacao` | `422` (data inicial > final) |

## Objeto `transacao`

```json
{
  "id": 40,
  "carteira": "saldo",
  "tipo": "debito",
  "origem": "ajuste_manual",
  "referencia_id": null,
  "valor": "30.00",
  "saldo_anterior": "100.00",
  "saldo_posterior": "70.00",
  "observacao": "Correção de lançamento",
  "autor": {"id": 1, "nome": "Administrador"},
  "created_at": "2026-09-29T15:00:00.000000Z"
}
```

`autor` é `null` quando a operação foi do sistema. Na área do cliente, `autor` não é exibido.
Ordem: da mais recente para a mais antiga.

## Gestão de clientes (painel)

| Rota | Permissão | Corpo / parâmetros | Sucesso | Erros |
|---|---|---|---|---|
| `GET /api/clientes` | `clientes.listar` | `busca?` (nome, telefone ou CPF), `ativo?`, `codigo_pais?`, `codigo_afiliado?`, `cadastro_de?`, `cadastro_ate?`, `idade_minima?`, `idade_maxima?`, `genero?`, `saldo_minimo?`, `saldo_maximo?`, `com_saldo_promocional?`, `ordenar_por?` (`nome`, `created_at`, `saldo`), `direcao?` (`asc`, `desc`), `por_pagina?` | `200` paginado de `cliente` | `422` |
| `GET /api/clientes/{cliente}` | `clientes.listar` | — | `200` `cliente` | `404` |
| `PUT/PATCH /api/clientes/{cliente}` | `clientes.editar` | `nome?`, `codigo_pais?`, `telefone?`, `cpf?`, `data_nascimento?`, `genero?`, `codigo_afiliado?`, `aceita_promocao?`, `password?` + `password_confirmation` | `200` `cliente` | `404`; `422` (duplicidade; saldos não são aceitos) |
| `PATCH /api/clientes/{cliente}/situacao` | `clientes.editar` | `ativo` (boolean) | `200` `cliente` | `404`; `422` |
| `DELETE /api/clientes/{cliente}` | `clientes.excluir` (Admin/Supervisor) | — | `204` (sufixo em telefone/CPF) | `403`; `404` |
| `GET /api/clientes/excluidos` | `clientes.restaurar` (Admin/Supervisor) | mesmos filtros da listagem | `200` paginado | `403` |
| `POST /api/clientes/{cliente}/restaurar` | `clientes.restaurar` (Admin/Supervisor) | `codigo_pais?`, `telefone?`, `cpf?` (só para resolver conflito) | `200` `cliente` | `403`; `404`; `422 "Cliente não está excluído."`; `422` com `errors.telefone` e/ou `errors.cpf` = "Já está em uso por outro cliente; informe um novo valor." |

## Saldos (painel)

| Rota | Permissão | Corpo / parâmetros | Sucesso | Erros |
|---|---|---|---|---|
| `GET /api/clientes/{cliente}/transacoes` | `clientes.listar` | `data_inicial?`, `data_final?`, `carteira?`, `por_pagina?` | `200` paginado de `transacao` | `404`; `422` |
| `POST /api/clientes/{cliente}/transacoes` | `clientes.movimentar_saldo` | `carteira`, `tipo` (`credito`/`debito`), `valor` (> 0, 2 casas), `observacao` (obrigatória) | `201` `transacao` (origem `ajuste_manual`, autor = usuário logado) | `404`; `422 "Saldo insuficiente."`; `422` |

## Configurações (painel)

| Rota | Permissão | Corpo | Sucesso | Erros |
|---|---|---|---|---|
| `GET /api/clientes/{cliente}/configuracoes` | `clientes.listar` | — | `200` `configuracoes` | `404` |
| `PUT /api/clientes/{cliente}/configuracoes` | `clientes.editar_configuracoes` | todos os campos de `configuracoes` | `200` `configuracoes` | `404`; `422` (mínimo > máximo etc.) |
| `GET /api/clientes_configuracoes_padrao` | `clientes.editar_configuracoes_padrao` (Admin/Supervisor) | — | `200` `configuracoes` | `403` |
| `PUT /api/clientes_configuracoes_padrao` | `clientes.editar_configuracoes_padrao` (Admin/Supervisor) | todos os campos | `200` `configuracoes` | `403`; `422` |

```json
{
  "realizar_aposta": true,
  "apostar_ao_vivo": true,
  "apostar_outros_esportes": true,
  "cancelar_aposta": false,
  "quantidade_minima_opcoes": 1,
  "quantidade_maxima_opcoes": 20,
  "valor_minimo_aposta": "2.00",
  "valor_maximo_aposta": "1000.00",
  "premio_maximo": "50000.00",
  "valor_maximo_diario": "5000.00",
  "odd_minima": "1.90",
  "odd_maxima": "30.00",
  "esportes_permitidos": ["FUTEBOL", "HOQUEI NO GELO", "BAISEBOL"],
  "updated_at": "2026-09-29T15:00:00.000000Z"
}
```

Não existe rota da área do cliente para alterar configurações (FR-045).

## Promoções (painel)

| Rota | Permissão | Corpo / parâmetros | Sucesso | Erros |
|---|---|---|---|---|
| `GET /api/clientes_promocoes` | `clientes_promocoes.gerenciar` | `ativa?`, `modalidade?`, `categoria?`, `por_pagina?` | `200` paginado | `403` |
| `GET /api/clientes_promocoes/{promocao}` | `clientes_promocoes.gerenciar` | — | `200` | `404 "Promoção não encontrada."` |
| `POST /api/clientes_promocoes` | `clientes_promocoes.gerenciar` | `nome`, `descricao?`, `modalidade`, `categoria`, `valor`, `data_inicio`, `data_fim?`, `ativa?` | `201` | `422` (inclui sobreposição: "Já existe uma promoção ativa desta categoria e modalidade no período.") |
| `PUT/PATCH /api/clientes_promocoes/{promocao}` | `clientes_promocoes.gerenciar` | mesmos campos (ativar/desativar pelo `ativa`) | `200` | `404`; `422` |
| `DELETE /api/clientes_promocoes/{promocao}` | `clientes_promocoes.gerenciar` | — | `204` (soft delete) | `404` |

```json
{
  "id": 1,
  "nome": "Bônus de boas-vindas",
  "descricao": "R$ 20 para apostar em esportes",
  "modalidade": "esportes",
  "categoria": "primeiro_cadastro",
  "valor": "20.00",
  "data_inicio": "2026-10-01T00:00:00.000000Z",
  "data_fim": null,
  "ativa": true,
  "vigente": true,
  "created_at": "2026-09-29T15:00:00.000000Z",
  "updated_at": "2026-09-29T15:00:00.000000Z"
}
```

## Uso interno (sem rota)

- `SaldoClientes::creditar(Clientes $cliente, Carteira $carteira, string $valor, OrigemTransacao $origem, ?Usuarios $autor = null, ?int $referencia_id = null, ?string $observacao = null): ClientesTransacoes`
- `SaldoClientes::debitar(...)`: mesma assinatura; lança `SaldoInsuficienteException` (convertida em
  `422 "Saldo insuficiente."`).
- Eventos `ClienteCadastrado(cliente)` e `CodigoRecuperacaoGerado(cliente, codigo)`: a futura spec
  de WhatsApp troca o listener `RegistrarMensagemWhatsapp`.
