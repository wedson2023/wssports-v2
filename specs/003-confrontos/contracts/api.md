# Contrato HTTP: Confrontos (listagem pública e gestão no painel)

**Feature**: `003-confrontos` | **Data**: 2026-10-01 | **Plano**: [../plan.md](../plan.md)

Rotas em `routes/api.php` (prefixo `/api`). Caminhos em kebab-case; query string e chaves JSON em
snake_case (constituição v1.16.0). As rotas das specs 001 e 002 não mudam, exceto as 2 de
`clientes-configuracoes-padrao`, que são removidas (FR-079).

```php
// listagem pública: sem login; aceita token de cliente ou do painel (R-09)
Route::get('publico/confrontos', [PublicoConfrontosController::class, 'index']);

// gestão no painel (guard api, mesmo token da spec 001)
Route::middleware(['auth:api', 'garantir_acesso'])->group(function () {
    Route::patch('campeonatos/{campeonato}/situacao', [CampeonatosController::class, 'alterar_situacao']);
    Route::patch('campeonatos/{campeonato}/favorito', [CampeonatosController::class, 'alterar_favorito']);
    Route::apiResource('campeonatos', CampeonatosController::class);

    Route::get('confrontos-ao-vivo', [ConfrontosAoVivoController::class, 'index']);
    Route::patch('confrontos/{confronto}/situacao', [ConfrontosController::class, 'alterar_situacao']);
    Route::apiResource('confrontos', ConfrontosController::class);

    Route::apiResource('campeonatos-nao-permitidos', CampeonatosNaoPermitidosController::class)
        ->only(['index', 'store', 'destroy'])->parameters(['campeonatos-nao-permitidos' => 'registro']);
    Route::apiResource('confrontos-nao-permitidos', ConfrontosNaoPermitidosController::class)
        ->only(['index', 'store', 'destroy'])->parameters(['confrontos-nao-permitidos' => 'registro']);
    Route::apiResource('confrontos-ao-vivo-nao-permitidos', ConfrontosAoVivoNaoPermitidosController::class)
        ->only(['index', 'store', 'destroy'])->parameters(['confrontos-ao-vivo-nao-permitidos' => 'registro']);

    Route::get('porcentagens-vendedores/{usuario}', [PorcentagensVendedoresController::class, 'show']);
    Route::patch('porcentagens-vendedores/{usuario}', [PorcentagensVendedoresController::class, 'update']);
    Route::get('porcentagens-clientes', [PorcentagensClientesController::class, 'show']);
    Route::patch('porcentagens-clientes', [PorcentagensClientesController::class, 'update']);
    Route::get('porcentagens-campeonatos/{campeonato}', [PorcentagensCampeonatosController::class, 'show']);
    Route::patch('porcentagens-campeonatos/{campeonato}', [PorcentagensCampeonatosController::class, 'update']);
    Route::get('porcentagens-confrontos/{confronto}', [PorcentagensConfrontosController::class, 'show']);
    Route::patch('porcentagens-confrontos/{confronto}', [PorcentagensConfrontosController::class, 'update']);
    Route::get('confrontos-teto-cotacoes', [ConfrontosTetoCotacoesController::class, 'show']);
    Route::patch('confrontos-teto-cotacoes', [ConfrontosTetoCotacoesController::class, 'update']);

    Route::get('usuarios-configuracoes', [UsuariosConfiguracoesController::class, 'index']);
    Route::patch('usuarios-configuracoes', [UsuariosConfiguracoesController::class, 'update']);
    Route::get('visitantes-configuracoes', [VisitantesConfiguracoesController::class, 'show']);
    Route::put('visitantes-configuracoes', [VisitantesConfiguracoesController::class, 'update']);
});
```

Rotas com `{campeonato}`, `{confronto}`, `{usuario}` e `{registro}` usam `->missing()` com
"Campeonato não encontrado.", "Confronto não encontrado.", "Usuário não encontrado." e "Registro
não encontrado.". Total: **1 rota pública** e **37 do painel**; **2 removidas**.

## Regras gerais

- JSON (`Accept: application/json`); rotas do painel exigem `Authorization: Bearer <token>`.
- `401 {"message": "Não autenticado."}` e `403 {"message": "Usuário sem permissão de acesso."}`:
  como na spec 001 (painel).
- `403 {"message": "Você não tem permissão para esta ação."}`: sem a permissão, função que não
  pode usá-la (Funcao::usuario_pode) ou fora do alcance da hierarquia (FR-061, FR-071, FR-075).
- `404`: registro inexistente ou excluído.
- `422`: validação, em português (`{"message": "...", "errors": {...}}`); também
  "Só registros manuais podem ser alterados." (FR-064).
- `429 {"message": "Muitas requisições. Tente novamente em N segundos."}` (listagem pública).
- Cotações e valores com 2 casas, como número (`1.85`). Datas em ISO 8601.
- Listagens do painel: `por_pagina` 1–100, padrão 20; filtros vazios são ignorados (R-19 da spec
  002).
- Códigos de cotação aceitos nas regras: `odd1`…`odd323` e `jogador`.

## Listagem pública

### `GET /api/publico/confrontos`

> Spec 004: o pré-jogo também respeita o período de jogos e a data de travamento do público e não
> mostra o confronto que já está no ao vivo; com a data de travamento passada (vendedor e
> visitante), a listagem vem vazia. O detalhe de um confronto com todas as cotações
> (`GET /api/publico/confrontos/{confronto}` e `GET /api/publico/confrontos-ao-vivo/{confronto_ao_vivo}`)
> e as rotas de limite por confronto estão no contrato da spec 004.

| Parâmetro | Valores | Padrão |
|---|---|---|
| `tipo` | `pre_jogo`, `ao_vivo` | `pre_jogo` |
| `dia` | `hoje`, `amanha`, `depois_de_amanha` (ignorado no ao vivo e com `busca`) | `hoje` |
| `busca` | texto até 100 (time da casa ou de fora) | |
| `esporte` | texto (como o provedor: `FUTEBOL`, `BASQUETE`…) | `FUTEBOL` |
| `somente_favoritos` | booleano | `false` |
| `fuso_horario` | `±HH:MM` entre `-12:00` e `+14:00` | `-03:00` |
| `pagina` / `por_pagina` | inteiro / 1–100 | 1 / 50 |

Cabeçalho opcional `Authorization: Bearer <token de cliente ou do painel>`.

**200** (pré-jogo):

```json
{
  "token_recusado": false,
  "tipo": "pre_jogo",
  "total": 123,
  "campeonatos": [
    {
      "id": 15,
      "nome": "Brasileirão Série A",
      "pais": "Brasil",
      "bandeira": "br",
      "confrontos": [
        {
          "id": 9001,
          "time_casa": "Flamengo",
          "escudo_casa": "https://...",
          "time_fora": "Palmeiras",
          "escudo_fora": "https://...",
          "esporte": "FUTEBOL",
          "data_inicio": "2026-10-01T21:30:00-03:00",
          "minutos_para_inicio": 95,
          "cotacoes": { "odd1": 2.10, "odd2": 3.20, "odd3": 3.40, "odd4": 1.85 },
          "quantidade_cotacoes": 142
        }
      ]
    }
  ],
  "paises": [
    { "pais": "Brasil", "campeonatos": [ { "id": 15, "nome": "Brasileirão Série A", "bandeira": "br", "quantidade_confrontos": 10 } ] }
  ],
  "meta": { "pagina_atual": 1, "por_pagina": 50, "ultima_pagina": 3, "total": 123 }
}
```

**200** (ao vivo): mesmo formato, e cada confronto traz também `placar_casa`, `placar_fora`,
`minuto`, `cronometro`, `situacao` e `travado` (`true` = cotações zeradas, FR-017/FR-022); sem
`minutos_para_inicio`.

- `cotacoes` traz sempre `odd1` a `odd4` (0 = indisponível), já ajustadas (FR-047). Nunca traz
  porcentagens, valores fixos, tetos nem a cotação do provedor (FR-052).
- `token_recusado: true` quando veio token inválido, expirado ou de quem não pode acessar; a
  resposta é a de visitante.
- **403** `{"message": "O ao vivo não está disponível."}`: `tipo=ao_vivo` com `ao_vivo_habilitado`
  desmarcado no sistema, no visitante ou no vendedor (FR-046c).
- **422**: `tipo`, `dia` ou `fuso_horario` inválidos; `por_pagina` fora de 1–100.
- **429**: mais de 120 requisições por minuto do mesmo IP.

## Campeonatos (painel)

| Rota | Permissão | Regras |
|---|---|---|
| `GET /campeonatos` | `campeonatos.listar` | filtros `busca` (nome), `ativo`, `favorito`, `manual`, `pais`; ordena por nome |
| `GET /campeonatos/{campeonato}` | `campeonatos.listar` | |
| `POST /campeonatos` | `campeonatos.cadastrar` | manual: `nome` (≤150), `pais` (≤100), `bandeira` (opcional); único por nome+país entre manuais; **201** |
| `PUT /campeonatos/{campeonato}` | `campeonatos.editar` | só manual (`422` se do provedor) |
| `DELETE /campeonatos/{campeonato}` | `campeonatos.excluir` | só manual; soft delete com os confrontos dele; **204** |
| `PATCH /campeonatos/{campeonato}/situacao` | `campeonatos.alterar_situacao` | `{"ativo": false}` |
| `PATCH /campeonatos/{campeonato}/favorito` | `campeonatos.favoritar` | `{"favorito": true}` |

Objeto `campeonato`:

```json
{ "id": 15, "codigo_externo": 325, "nome": "Brasileirão Série A", "pais": "Brasil", "bandeira": "br",
  "ativo": true, "favorito": false, "manual": false, "nao_permitido": false,
  "created_at": "2026-10-01T12:00:00.000000Z", "updated_at": "2026-10-01T12:00:00.000000Z" }
```

`nao_permitido`: se há um não permitido que vale para quem consulta (FR-068).

## Confrontos (painel)

| Rota | Permissão | Regras |
|---|---|---|
| `GET /confrontos` | `confrontos.listar` | pré-jogo; filtros `busca` (time), `campeonatos_id`, `ativo`, `manual`, `esporte`, `situacao`, `dia` + `fuso_horario` |
| `GET /confrontos/{confronto}` | `confrontos.listar` | com as cotações do provedor (`cotacoes`) e os jogadores |
| `POST /confrontos` | `confrontos.cadastrar` | manual (abaixo); **201** |
| `PUT /confrontos/{confronto}` | `confrontos.editar` | só manual; campos do cadastro + `situacao` ∈ Aguardando, Adiado, Cancelado |
| `DELETE /confrontos/{confronto}` | `confrontos.excluir` | só manual; soft delete; **204** |
| `PATCH /confrontos/{confronto}/situacao` | `confrontos.alterar_situacao` | `{"ativo": false}`; some também do ao vivo |
| `GET /confrontos-ao-vivo` | `confrontos.listar` | jogos em andamento dentro da permanência, com `travado` |

Corpo do confronto manual:

```json
{
  "campeonatos_id": 77,
  "time_casa": "Time A", "escudo_casa": null,
  "time_fora": "Time B", "escudo_fora": null,
  "esporte": "FUTEBOL",
  "data_inicio": "2026-10-02 16:00",
  "fuso_horario": "-03:00",
  "cotacoes": { "odd1": 2.00, "odd2": 3.10, "odd3": 3.50 }
}
```

`campeonatos_id` de campeonato manual; `data_inicio` futura (interpretada no `fuso_horario`,
gravada em UTC); ao menos 1 cotação, todas ≥ 1,00, códigos `odd1`…`odd323`.

## Não permitidos (painel)

Mesmo formato para as três rotas; o item é `campeonatos_id` em `campeonatos-nao-permitidos` e
`confrontos_id` nas duas de confrontos (no ao vivo, o confronto da grade, R-13).

| Rota | Permissão |
|---|---|
| `campeonatos-nao-permitidos` | `campeonatos_nao_permitidos.gerenciar` |
| `confrontos-nao-permitidos` | `confrontos_nao_permitidos.gerenciar` |
| `confrontos-ao-vivo-nao-permitidos` | `confrontos_ao_vivo_nao_permitidos.gerenciar` |

- `GET`: registros visíveis a quem consulta (Admin e Supervisor: todos; Gerente: os de alvo
  Vendedores dele e da sub-hierarquia), filtros `alvo`, `usuarios_id`, `clientes_id` e o item.
- `POST`:

  ```json
  { "campeonatos_id": 15, "alvo": "Vendedores", "usuarios_id": null, "clientes_id": null }
  ```

  - `Vendedores`: `usuarios_id` opcional (padrão = quem marca); se informado, ele mesmo ou alguém
    da sub-hierarquia; `clientes_id` proibido.
  - `Clientes`: só Admin e Supervisor; `clientes_id` opcional (vazio = todos os clientes e
    visitantes); `usuarios_id` proibido.
  - `Todos`: só Admin e Supervisor; sem `usuarios_id` e `clientes_id`.
  - Já existe → **200** com o registro (restaurado se estava desmarcado); novo → **201**.
- `DELETE /…/{registro}`: desmarca (soft delete), respeitando o mesmo alcance; **204**.

## Regras de cotação (painel)

Corpo comum de alteração (`PATCH`), com **um** dos dois formatos:

```json
{ "valores": { "odd1": -5, "odd3": -2.5, "jogador": -3 } }
```

```json
{ "todos": -10 }
```

`valores` mescla com os atuais (0 remove o código); `todos` substitui os 324 códigos (FR-059).
Porcentagens de −100 a 100; tetos ≥ 1,00; 2 casas.

| Rota | Permissão | Corpo além de `valores`/`todos` | Alcance |
|---|---|---|---|
| `GET`/`PATCH /porcentagens-vendedores/{usuario}` | `porcentagens_vendedores.editar` | `tipo`: `pre_jogo` \| `ao_vivo` (obrigatório no PATCH) | o próprio usuário ou alguém da sub-hierarquia |
| `GET`/`PATCH /porcentagens-clientes` | `porcentagens_clientes.editar` | `tipo`; `clientes_id` opcional (vazio = regra geral) | Admin e Supervisor |
| `GET`/`PATCH /porcentagens-campeonatos/{campeonato}` | `porcentagens_campeonatos.editar` | `alvo`; `usuarios_id` (só em Vendedores, padrão = quem altera) | Clientes e Todos só Admin e Supervisor |
| `GET`/`PATCH /porcentagens-confrontos/{confronto}` | `porcentagens_confrontos.editar` | `alvo`, `usuarios_id`; em vez de `valores`/`todos`, `cotacoes: {"odd1": 1.95}` (cotação desejada) | idem |
| `GET`/`PATCH /confrontos-teto-cotacoes` | `confrontos_teto_cotacoes.editar` | — | Admin e Supervisor |

- `GET` devolve `{"pre_jogo": {...}, "ao_vivo": {...}}` (vendedores e clientes) ou a lista de
  regras visíveis por alvo (campeonatos e confrontos). Em `porcentagens-confrontos`, cada código
  traz também a cotação do provedor e a resultante sem as porcentagens do público.
- `porcentagens-confrontos`: guarda `cotação desejada − cotação do provedor` (FR-060); código com
  cotação zerada no provedor → `422` "A cotação {código} está indisponível no provedor.".

## Configurações (painel)

### `GET /api/usuarios-configuracoes?gerente_id={id}` — `usuarios_configuracoes.editar`

Lista paginada dos vendedores do gerente (que deve ser o próprio solicitante ou estar na
sub-hierarquia dele) com as configurações; vendedor sem linha aparece com os valores padrão.

```json
{ "data": [ { "usuarios_id": 31, "nome": "Vendedor 1", "esportes_permitidos": ["FUTEBOL"],
  "apostar_outros_esportes": true, "ao_vivo_habilitado": true, "minuto_limite_ao_vivo": 95,
  "cotacao_maxima_ao_vivo": 30.00 } ], "links": {}, "meta": {} }
```

### `PATCH /api/usuarios-configuracoes` — `usuarios_configuracoes.editar`

```json
{ "usuarios_id": 12, "ao_vivo_habilitado": false }
```

- `usuarios_id` = alvo: Admin → supervisor, gerente ou vendedor; Supervisor → gerente ou
  vendedor; Gerente → vendedor; sempre da sub-hierarquia (senão `403`).
- Só os campos enviados (`esportes_permitidos`, `apostar_outros_esportes`, `ao_vivo_habilitado`,
  `minuto_limite_ao_vivo`, `cotacao_maxima_ao_vivo`) são gravados em todos os vendedores do alcance
  (FR-075); pelo menos um é obrigatório.
- **200** `{"vendedores_alterados": 42}`.

### `GET` / `PUT /api/visitantes-configuracoes` — `visitantes_configuracoes.editar`

```json
{ "esportes_permitidos": ["FUTEBOL", "BASQUETE"], "apostar_outros_esportes": true, "ao_vivo_habilitado": true }
```

PUT exige os três campos. Só Admin e Supervisor (FR-077).

## Rotas removidas (spec 002)

- `GET /api/clientes-configuracoes-padrao`
- `PUT /api/clientes-configuracoes-padrao`
