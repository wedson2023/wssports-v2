# Contrato HTTP: Apuração de resultados

Rotas novas: `configuracoes/apuracao` (no grupo das configurações de pagamento, mesmo padrão de
`configuracoes/criar-aposta` da spec 008, permissão `configuracoes.editar`, que já existe) e
`confrontos/{confronto}/resultado` (no grupo de gestão de confrontos da spec 003, permissão nova
`confrontos.lancar_resultado`). As demais rotas não mudam: o comprovante e o extrato passam a mostrar os valores novos
(`resultado` do palpite `Devolvido` e a origem `Devolução de aposta`) pelos campos que já existem.

## `GET /api/configuracoes/apuracao`

200:

```json
{
  "data": {
    "remover_confronto_cancelado": true,
    "minutos_pagamento_premio": 5
  }
}
```

## `PUT /api/configuracoes/apuracao`

Corpo (só os campos enviados são gravados):

```json
{
  "remover_confronto_cancelado": false,
  "minutos_pagamento_premio": 10
}
```

| Campo | Regra |
|---|---|
| `remover_confronto_cancelado` | `sometimes`, boolean |
| `minutos_pagamento_premio` | `sometimes`, inteiro de 0 a 1440 |

Mensagens (422):

- `remover_confronto_cancelado.boolean`: "O campo remover_confronto_cancelado deve ser verdadeiro ou falso.";
- `minutos_pagamento_premio.integer` / `.between`: "A espera para pagar o prêmio deve ser de 0 a 1440 minutos.".

200: mesmo corpo do `GET`. 403 sem a permissão.

## `PUT /api/confrontos/{confronto}/resultado`

Lança ou corrige o resultado de um jogo **manual** (R-18). Grupo de gestão de confrontos
(`auth:api` + `garantir_acesso`), permissão `confrontos.lancar_resultado`. Jogo inexistente → 404
"Confronto não encontrado." (o `missing` já usado nas rotas de confrontos).

Corpo (nomes do sistema):

```json
{
  "placar_casa": 2,
  "placar_fora": 1,
  "gols_primeiro_tempo_casa": 1,
  "gols_primeiro_tempo_fora": 0,
  "gols_segundo_tempo_casa": 1,
  "gols_segundo_tempo_fora": 1,
  "escanteios_casa": 7,
  "escanteios_fora": 3,
  "gols": [
    { "ordem": 1, "nome": "Rebecca Rayner", "minuto": 12, "tempo_jogo": "1º tempo" },
    { "ordem": 2, "nome": "Ana Souza", "minuto": 58, "tempo_jogo": "2º tempo" }
  ]
}
```

| Campo | Regra |
|---|---|
| `placar_casa`, `placar_fora` | `required`, inteiro, 0 a 99 |
| `gols_primeiro_tempo_*`, `gols_segundo_tempo_*` | `nullable`, inteiro, 0 a 99; os quatro juntos (todos informados ou todos em branco) |
| `escanteios_casa`, `escanteios_fora` | `nullable`, inteiro, 0 a 99; os dois juntos |
| `gols` | `nullable`, lista de até 99 itens; ausente = mantém os gols gravados; lista vazia = nenhum gol |
| `gols.*.ordem` | `required`, inteiro de 1 a 99, sem repetição |
| `gols.*.nome` | `required`, texto até 150 |
| `gols.*.minuto` | `nullable`, inteiro de 0 a 200 |
| `gols.*.tempo_jogo` | `nullable`, texto até 20 |

Mensagens (422):

- jogo do provedor: "O resultado de jogos do provedor vem da carga automática.";
- placar ausente ou inválido: "Informe o placar final do jogo (0 a 99 gols para cada time).";
- gols de tempo incompletos: "Informe os gols do 1º e do 2º tempo dos dois times ou deixe os quatro em branco.";
- escanteios incompletos: "Informe os escanteios dos dois times ou deixe os dois em branco.";
- gol inválido: "Cada gol precisa da ordem (1 a 99, sem repetir) e do nome do jogador.".

200:

```json
{
  "data": {
    "confrontos_id": 501,
    "situacao": "Encerrado",
    "placar_casa": 2,
    "placar_fora": 1,
    "gols_primeiro_tempo_casa": 1,
    "gols_primeiro_tempo_fora": 0,
    "gols_segundo_tempo_casa": 1,
    "gols_segundo_tempo_fora": 1,
    "escanteios_casa": 7,
    "escanteios_fora": 3,
    "gols": [{ "ordem": 1, "nome": "Rebecca Rayner", "minuto": 12, "tempo_jogo": "1º tempo" }],
    "apostas_apuradas": 12
  }
}
```

O mesmo resultado enviado de novo devolve 200 com `apostas_apuradas: 0` e nada muda (FR-047).
403 sem a permissão.

## Postman

A coleção `docs/postman/wssports_api.postman_collection.json` é regenerada com as 3 rotas,
mantendo `base_url` e `token`.
