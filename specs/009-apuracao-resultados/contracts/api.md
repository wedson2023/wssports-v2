# Contrato HTTP: Apuração de resultados

Rotas novas: `configuracoes/apuracao` (no grupo das configurações de pagamento, mesmo padrão de
`configuracoes/criar-aposta` da spec 008, permissão `configuracoes.editar`, que já existe) e
`confrontos/{confronto}/resultado` (no grupo de gestão de confrontos da spec 003, permissão nova
`confrontos.lancar_resultado`). As demais rotas não mudam: o extrato passa a mostrar a origem
`Devolução de aposta` pelos campos que já existem.

> Alterado pela spec 023: `PUT /confrontos/{confronto}/resultado` aceita jogos do provedor e
> manuais, só depois do início (422 em `confronto`); 1º e 2º tempo e escanteios obrigatórios; 422 em
> `placar_casa` quando a soma dos tempos não bate com o placar; permissão só do Admin. Contrato em
> `specs/023-painel-confrontos/contracts/api.md` §1.3.

## Comprovante (todas as rotas que devolvem `ComprovanteApostaResource`)

Cada palpite ganha o andamento do jogo, como no comprovante do sistema antigo:

```json
{
  "situacao": "Ativo", "resultado": "Perdedor", "periodo": "Encerrado",
  "placar_casa": 1, "placar_fora": 3, "minuto": null,
  "primeiro_tempo": { "casa": 1, "fora": 0 }, "segundo_tempo": { "casa": 0, "fora": 3 },
  "escanteios": 9
}
```

- `resultado`: `Aguardando`, `Vencedor`, `Perdedor` ou `Devolvido`.
- Com resultado gravado: `periodo` é a situação do resultado (`Encerrado`) e o placar é o final.
- Sem resultado e com o jogo no ao vivo: `periodo`, placar e `minuto` atuais do ao vivo.
- Antes do início: `periodo`, placar e demais campos nulos. O placar do momento da aposta ao vivo
  não vai mais no comprovante.
- `primeiro_tempo`, `segundo_tempo` e `escanteios` (soma dos dois times): só no futebol e só com
  resultado; nulos quando o provedor não mandou.

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
    { "ordem": 1, "nome": "Rebecca Rayner", "minuto": "12", "tempo_jogo": "1º tempo" },
    { "ordem": 2, "nome": "Ana Souza", "minuto": "45+2", "tempo_jogo": "2º tempo" }
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
| `gols.*.minuto` | `nullable`, minuto com acréscimos no formato `45` ou `45+1` |
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
    "gols": [{ "ordem": 1, "nome": "Rebecca Rayner", "minuto": "12", "tempo_jogo": "1º tempo" }],
    "apostas_apuradas": 12
  }
}
```

O mesmo resultado enviado de novo devolve 200 com `apostas_apuradas: 0` e nada muda (FR-047).
403 sem a permissão.

## Postman

A coleção `docs/postman/wssports_api.postman_collection.json` é regenerada com as 3 rotas,
mantendo `base_url` e `token`.
