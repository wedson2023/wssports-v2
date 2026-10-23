# Contrato da API: Painel — Bônus

Base `/api`, JWT (`Authorization: Bearer`), grupo `auth:api` + `garantir_acesso`. Erros no padrão do
projeto (`{message, errors}` com 422; `{message}` com 403/404). Permissões por `Funcao::usuario_pode`
(`GarantirPermissaoCliente`). Valores em reais como string com duas casas. Datas em ISO 8601 (UTC).
Paginação: `page`, `por_pagina` (máximo 100), resposta com `data`, `links` e `meta`.

**Alcance** (FR-002, R-06): promoção de afiliado fora da rede, ou de afiliado sem gerente para quem não
é Admin → **404 "Promoção não encontrada."**; promoção geral para Supervisor/Gerente é visível, mas
alterar responde **403 "Só o Admin pode alterar promoções gerais."**. Bônus de cliente fora da rede →
**404 "Bônus não encontrado."**.

Rotas fixas (`clientes-bonus/resumo`) vêm antes das com `{bonus}`.

## 1. Promoções — rotas existentes alteradas

### 1.1 `GET /clientes-promocoes` (alterada)
`clientes_promocoes.gerenciar`. Padrão 20 por página. Filtros:

| Parâmetro | Regra |
|---|---|
| `busca` | trecho do nome |
| `situacao` | `Vigente` \| `Agendada` \| `Encerrada` \| `Desativada` \| `Estornando` \| `Estornada` |
| `modalidade`, `categoria` | como hoje |
| `afiliados_id` | id de afiliado da rede, ou `geral` |
| `ativa` | como hoje (continua aceito) |

Cada item (`ClientesPromocoesResource`) ganha:

```json
{
  "id": 12, "nome": "Boas-vindas 100%", "...": "campos atuais",
  "afiliado": { "id": 3, "nome": "Afiliado X", "codigo": "AFI10", "excluido": false },
  "situacao": "Vigente",
  "uso": { "clientes": 3, "creditado": "150.00", "cumpridos": 1, "convertido": "40.00", "retirado": "20.00" },
  "pode": { "editar": true, "ativar": true, "excluir": false, "estornar": true }
}
```

`afiliado` é `null` nas gerais. `uso` considera só os clientes da rede para Supervisor/Gerente.

### 1.2 `GET /clientes-promocoes/{promocao}` (alterada)
Mesmo item de 1.1 (com `uso` e `estorno`). Usada também para acompanhar o progresso do estorno.

### 1.3 `POST /clientes-promocoes` e `PATCH|PUT /clientes-promocoes/{promocao}` (alteradas)
Corpo atual. Mudanças de regra ([data-model.md](../data-model.md) §6):

- `afiliados_id`: obrigatório e da rede para Supervisor/Gerente; 422 "Afiliado não encontrado.";
- `nome`: único entre as não excluídas; 422 "Já existe uma promoção com este nome.";
- `data_fim` enviada e diferente da gravada no passado: 422 "A data de fim já passou.";
- sobreposição por afiliado: 422 em `data_inicio` "Já existe uma promoção ativa desta categoria e
  modalidade no período: {nome} (ID {id}).";
- promoção geral por Supervisor/Gerente: 403.

Ativar/desativar usa esta rota com `{ "ativa": true|false }`.

### 1.4 `DELETE /clientes-promocoes/{promocao}` (alterada)
204 se ninguém recebeu e não estornada; senão 422 `{"errors": {"promocao": ["Promoção já aplicada não
pode ser excluída; desative ou estorne."]}}`. Geral por Supervisor/Gerente: 403.

### 1.5 `POST /clientes-promocoes/{promocao}/estornar` (alterada)
`clientes_promocoes.estornar`. Corpo:

```json
{ "motivo": "Promoção criada com valor errado", "senha": "******" }
```

422 `{"errors": {"senha": ["Senha incorreta."]}}` sem gravar nada. 202 com o item de 1.1 (situação
`Estornando`). O processamento em fila passa a estornar bônus a bônus (R-05).

## 2. Bônus recebidos — rotas novas

### 2.1 `GET /clientes-bonus`
`clientes_promocoes.gerenciar`. Padrão 30 por página.

| Parâmetro | Regra |
|---|---|
| `situacao` | `Em andamento` (padrão) \| `Cumprido` \| `Convertido` \| `Expirado` \| `Excluído pelo cliente` \| `Estornado` \| `Todos` |
| `busca` | nome do cliente (trecho) ou ID numérico |
| `clientes_promocoes_id` | promoção visível ao usuário |
| `modalidade` | `Esportes` \| `Cassino` |
| `data_inicial`, `data_final` | `AAAA-MM-DD` (Brasília), `final ≥ inicial`, até 366 dias |

Item (`BonusRecebidosResource`):

```json
{
  "id": 9876,
  "cliente": { "id": 55, "nome": "Maria Souza", "excluido": false },
  "promocao": { "id": 12, "nome": "Boas-vindas 100%", "afiliado": "AFI10" },
  "modalidade": "Esportes",
  "valor": "50.00",
  "saldo_promocional": "30.00",
  "rollover": { "vezes": 5, "exigido": "250.00", "apostado": "120.00", "falta": "130.00" },
  "recebido_em": "2026-10-20T13:00:00Z",
  "expira_em": "2026-10-27T13:00:00Z",
  "situacao": "Em andamento",
  "encerrado_em": null,
  "valor_encerramento": null,
  "estorno": null,
  "pode": { "estornar": true }
}
```

`rollover` é `null` quando a promoção não exige rollover. `encerrado_em` / `valor_encerramento`: data e
valor convertido, expirado, excluído ou retirado. `estorno`: `{ "tipo", "motivo", "autor": {id, nome} |
null, "em", "valor_retirado" }`.

### 2.2 `GET /clientes-bonus/resumo`
`clientes_promocoes.gerenciar`. Mesmos filtros de 2.1 (menos `situacao`).

```json
{
  "em_andamento": { "quantidade": 14, "falta": "1830.00" },
  "creditado": { "quantidade": 40, "valor": "2000.00" },
  "convertido": { "quantidade": 6, "valor": "310.00" },
  "retirado": { "quantidade": 9, "valor": "275.50" }
}
```

`em_andamento` ignora o período.

### 2.3 `GET /clientes-bonus/{bonus}`
`clientes_promocoes.gerenciar`. Item de 2.1 + `regras` (`valor_minimo_aposta`, `valor_maximo_aposta`,
`odd_minima_aposta_simples`, `odd_minima_aposta_multipla`) + `historico` (lista de
`{ "evento": "Recebido" | "Cumprido" | "Convertido" | "Expirado" | "Excluído pelo cliente" | "Estornado", "em", "valor", "autor", "observacao" }`).
404 "Bônus não encontrado." fora da rede ou se o id não for crédito de promoção.

### 2.4 `POST /clientes-bonus/{bonus}/estornar`
`clientes_promocoes.estornar`. Corpo `{ "motivo": "...", "senha": "******" }`. 200 com o item de 2.1.
422: "Senha incorreta." (`senha`), "Este bônus não pode mais ser estornado." (`bonus`), motivo
obrigatório/255.

## 3. Página

`GET /painel/bonus` (web, Inertia `PanelBonus`), no grupo das páginas do painel.

## 4. Postman

`docs/postman/wssports_api.postman_collection.json` regenerada com as rotas de 1 e 2.
