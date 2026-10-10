# Contrato HTTP: Especiais, avisos, banners, logo, regras e tabela de jogos

**Feature**: `006-ajustes-tela-principal` | **Data**: 2026-10-10 | **Plano**: [../plan.md](../plan.md)

Rotas em `routes/api.php` (prefixo `/api`). Caminhos em kebab-case; chaves JSON em snake_case
(constituição). Erros seguem o padrão das specs 001 a 004 (`401`, `403 {"message": "Você não tem
permissão para esta ação."}`, `404`, `422` com `message` e `errors`, `429`).

```php
// público (sem login; aceita token de cliente ou do painel, como as rotas públicas existentes)
Route::get('publico/especiais', [PublicoEspeciaisController::class, 'index']);
Route::get('publico/avisos/atual', [PublicoAvisosController::class, 'atual'])->middleware('throttle:30,1');
Route::post('publico/avisos/{aviso}/leituras', [PublicoAvisosController::class, 'ler'])->middleware('throttle:30,1');

// painel (guard api)
Route::middleware(['auth:api', 'garantir_acesso'])->group(function () {
    Route::get('tabela-jogos', [TabelaJogosController::class, 'index']);                       // apostas.criar (vendedor)

    Route::post('especiais/{especial}/encerrar', [EncerramentoEspeciaisController::class, 'encerrar']);  // especiais.encerrar
    Route::post('especiais/{especial}/cancelar', [EncerramentoEspeciaisController::class, 'cancelar']);  // especiais.encerrar
    Route::apiResource('especiais', EspeciaisController::class)
        ->parameters(['especiais' => 'especial']);                                              // listar / gerenciar
    Route::apiResource('especiais.opcoes', EspeciaisOpcoesController::class)
        ->parameters(['especiais' => 'especial', 'opcoes' => 'opcao'])
        ->except(['index', 'show']);                                                            // gerenciar

    Route::apiResource('avisos', AvisosController::class)
        ->parameters(['avisos' => 'aviso']);                                                    // avisos.gerenciar
    Route::apiResource('banners', BannersController::class);                                    // banners.gerenciar

    Route::get('configuracoes/regras', [ConfiguracoesRegrasController::class, 'show']);         // configuracoes.editar
    Route::put('configuracoes/regras', [ConfiguracoesRegrasController::class, 'update']);       // configuracoes.editar
    Route::post('configuracoes/logo', [ConfiguracoesLogoController::class, 'update']);          // configuracoes.editar
});
```

Total: **3 rotas públicas** e **24 do painel** (27 novas; cada `apiResource` conta 5: listar,
cadastrar, ver, editar e remover). As rotas de aposta existentes passam a aceitar o palpite
especial (seção 6).

Upload de arquivo (avisos, banners e logo): `multipart/form-data`; para editar com arquivo, `POST`
com `_method=PUT`. Os arquivos são salvos com o hash do conteúdo no nome (research R-20), então a
URL devolvida muda quando a imagem muda.

## 1. `GET /api/publico/especiais`

Query: `busca` (nome da categoria), `especial` (id; filtro do menu), `pagina`, `por_pagina` (padrão
50, máximo 100).

```json
{
  "tipo": "especial",
  "campeonatos": [
    {
      "id": 7,
      "nome": "Campeão Brasileiro 2026",
      "data_limite": "2026-12-01T18:00:00-03:00",
      "opcoes": [
        { "id": 31, "nome": "Flamengo", "cotacao": "3.50" },
        { "id": 32, "nome": "Palmeiras", "cotacao": "4.00" }
      ]
    }
  ],
  "paises": [
    { "pais": "Especiais", "campeonatos": [ { "id": 7, "nome": "Campeão Brasileiro 2026", "quantidade_confrontos": 2, "bandeira": null } ] }
  ],
  "meta": { "pagina_atual": 1, "ultima_pagina": 1, "por_pagina": 50, "total": 1 },
  "token_recusado": false
}
```

Só categorias visíveis ([data-model.md](../data-model.md) seção 1) e opções ativas, por `nome` e
`data_limite`. Com `outros esportes` desligado para quem vê, devolve lista vazia (mesma regra da
barra de esportes).

## 2. `GET /api/publico/avisos/atual?aparelho={uuid}`

- `aparelho` obrigatório (UUID). Com token de cliente válido, também exclui os lidos pelo cliente.
- `200 {"data": {"id": 3, "titulo": "Aviso", "imagem": "https://.../storage/avisos/9f2c...e1.png", "link": null}}`
  ou `200 {"data": null}` quando não há aviso elegível.

## 3. `POST /api/publico/avisos/{aviso}/leituras`

Corpo: `{"aparelho": "uuid"}`. Grava a leitura do aparelho (e do cliente, com token de cliente);
repetir não duplica. `204`. Aviso inexistente ou inativo → `404 {"message": "Aviso não encontrado."}`.

## 4. `GET /api/tabela-jogos` (vendedor)

Permissão `apostas.criar`. Query: `dia` (`hoje` | `amanha`, padrão `hoje`), `esporte` (padrão
`FUTEBOL`), `campeonatos[]` (ids; opcional, filtra esses campeonatos dentro do `dia` pedido,
como no `ModalTable` do antigo), `pagina`, `por_pagina` (padrão e máximo 100).

```json
{
  "nome_sistema": "WSSports",
  "atualizada_em": "2026-10-10T09:30:00-03:00",
  "campeonatos": [
    {
      "id": 12,
      "nome": "Brasileirão Série A",
      "confrontos": [
        {
          "id": 501,
          "data_inicio": "2026-10-10T16:00:00-03:00",
          "time_casa": "Flamengo",
          "time_fora": "Vasco",
          "cotacoes": {
            "odd1": "1.85", "odd2": "3.40", "odd3": "4.20", "odd4": "1.90", "odd116": "1.95",
            "odd10": "1.25", "odd135": "1.30", "odd15": "1.10", "odd17": "1.70", "odd16": "3.10",
            "odd7": "1.85", "odd123": "1.80", "odd13": "1.95", "odd139": "1.40"
          }
        }
      ]
    }
  ],
  "meta": { "pagina_atual": 1, "ultima_pagina": 2, "por_pagina": 100, "total": 143 }
}
```

Cotações com as porcentagens do vendedor; código ausente ou bloqueado → `"1.00"`. Erros: `422`
(`dia` ou `esporte` inválidos; esporte não permitido ao vendedor), `403` (sem permissão).

## 5. Painel: especiais e opções

| Rota | Permissão | Corpo / resposta |
|---|---|---|
| `GET /api/especiais` | `especiais.listar` | Query `situacao`, `busca`, `pagina`, `por_pagina` (≤ 100). Lista paginada com as opções |
| `GET /api/especiais/{especial}` | `especiais.listar` | Categoria com opções, vencedora e quantidade de palpites em apostas Ativas |
| `POST /api/especiais` | `especiais.gerenciar` | `nome`, `data_limite` (fuso `-03:00` quando sem fuso), `ativo?`, `opcoes[{nome, cotacao}]` (mín. 2) → `201` |
| `PUT /api/especiais/{especial}` | `especiais.gerenciar` | `nome`, `data_limite`, `ativo` (só `Aguardando`) |
| `DELETE /api/especiais/{especial}` | `especiais.gerenciar` | Soft delete; recusado com palpite em aposta Ativa → `422` |
| `POST /api/especiais/{especial}/opcoes` | `especiais.gerenciar` | `nome`, `cotacao` (≥ 1,01), `ativo?` → `201` |
| `PUT /api/especiais/{especial}/opcoes/{opcao}` | `especiais.gerenciar` | `nome`, `cotacao`, `ativo` |
| `DELETE /api/especiais/{especial}/opcoes/{opcao}` | `especiais.gerenciar` | Soft delete; com palpite em aposta Ativa → `422` "Desative a opção em vez de remover." |
| `POST /api/especiais/{especial}/encerrar` | `especiais.encerrar` | `especiais_opcoes_id` (da própria categoria) → `200` com a categoria e `apostas_afetadas` |
| `POST /api/especiais/{especial}/cancelar` | `especiais.encerrar` | sem corpo → `200` com `apostas_afetadas` |

Mensagens: categoria fora de `Aguardando` → `422 {"message": "A categoria já foi encerrada ou
cancelada."}`; opção de outra categoria → `422 {"message": "A opção vencedora não pertence a esta
categoria."}`; nome repetido → `422 {"message": "Já existe uma opção com este nome na categoria."}`.

## 6. Palpite especial nas rotas de aposta existentes

`POST /api/publico/apostas`, `POST /api/area-cliente/apostas`, `POST /api/apostas` e
`POST /api/apostas/pendentes/{codigo}/validar` aceitam, em `palpites[]`:

```json
{ "especiais_opcoes_id": 31, "codigo_cotacao": "especial", "cotacao_vista": "3.50" }
```

Regras: um entre `confrontos_id`, `confrontos_ao_vivo_id` e `especiais_opcoes_id`; `codigo_cotacao =
"especial"` exige `especiais_opcoes_id`; duas opções da mesma categoria → `422 {"message": "Escolha só
uma opção por categoria especial."}`. Indisponível → `422` com o motivo ("A categoria especial ...
não aceita mais palpites." / "A opção ... não está disponível."). Cotação diferente → `409` como nos
jogos.

Comprovante (`GET /api/publico/apostas/{codigo}` e demais) e simulação devolvem o palpite especial
com `time_casa: "Vencedor"`, `time_fora` e `campeonato` = nome da categoria, `mercado` = nome da
opção, `data_inicio` = data limite, `esporte: "ESPECIAL"`, `codigo_cotacao: "especial"`,
`especiais_opcoes_id` e `resultado`.

## 7. Painel: avisos

| Rota | Corpo / resposta |
|---|---|
| `GET /api/avisos` | Paginada (≤ 100), com quantidade de leituras |
| `GET /api/avisos/{aviso}` | Aviso |
| `POST /api/avisos` | `multipart/form-data`: `imagem` (obrigatória; jpg, png ou webp; até 2 MB; sem redimensionar), `titulo?`, `link?` (URL), `inicio_em?`, `fim_em?` (≥ início), `ativo?` → `201` |
| `PUT /api/avisos/{aviso}` | Mesmos campos, `imagem` opcional; trocar a imagem remove o arquivo anterior |
| `DELETE /api/avisos/{aviso}` | Soft delete e remoção do arquivo → `204` |

Todas com `avisos.gerenciar`. Resposta de um aviso:
`{"data": {"id", "titulo", "imagem" (URL), "link", "inicio_em", "fim_em", "ativo", "quantidade_leituras"}}`.

## 8. Painel: banners

| Rota | Corpo / resposta |
|---|---|
| `GET /api/banners` | Paginada (≤ 100), por `ordem` e `id`; filtro `ativo?` |
| `GET /api/banners/{banner}` | Banner |
| `POST /api/banners` | `multipart/form-data`: `imagem` (obrigatória; jpg, png ou webp; até 4 MB; ajustada para 1280×405 em JPEG), `link?` (URL), `ordem?` (0 a 999, padrão 0), `ativo?` → `201` |
| `PUT /api/banners/{banner}` | Mesmos campos, `imagem` opcional; trocar a imagem remove o arquivo anterior |
| `DELETE /api/banners/{banner}` | Soft delete e remoção do arquivo → `204` (o último banner também pode ser removido) |

Todas com `banners.gerenciar`. Resposta de um banner:
`{"data": {"id": 1, "imagem": "https://.../storage/banners/4b1d...7a.jpg", "link": null, "ordem": 0, "ativo": true}}`.
Arquivo inválido → `422 {"message": "Envie uma imagem jpg, png ou webp de até 4 MB."}`.

## 9. Painel: regras da banca e logo

| Rota | Corpo / resposta |
|---|---|
| `GET /api/configuracoes/regras` | `{"data": {"regras": "Prazo de pagamento até 2 dias úteis.\n..."}}` |
| `PUT /api/configuracoes/regras` | `{"regras": "texto"}` (texto simples, até 10.000 caracteres, um parágrafo por linha; vazio permitido e esconde o bloco) → `200` com o texto gravado |
| `POST /api/configuracoes/logo` | `multipart/form-data`: `logo` (obrigatória; png, jpg ou webp; até 1 MB; sem redimensionar) → `200 {"data": {"logo": "https://.../storage/logos/77ac...0d.png"}}`; a logo anterior é removida |

Todas com `configuracoes.editar`. Logo inválida → `422 {"message": "Envie uma imagem png, jpg ou webp de até 1 MB."}`.
O texto não aceita HTML: ele é gravado como veio e a página mostra tudo como texto.
