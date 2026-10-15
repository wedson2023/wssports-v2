# Contrato com o provedor: resultados e cancelados

Complementa [../../003-confrontos/contracts/provedor.md](../../003-confrontos/contracts/provedor.md):
mesmas regras comuns (chave e `app` na URL, `Accept: application/json`, gzip, resposta em lista,
item inválido ignorado com motivo no log, URL e erro original fora do log). As rotas são as do
sistema antigo; a API do provedor **não muda**.

## 6. Resultados (`confrontos_resultados:importar`, a cada 5 min, :03)

`GET {URL_PRE_JOGO}/resultados` — tempo limite 180 s

```json
[
  {
    "fonte_id": 198389236,
    "situacao": "Encerrado",
    "placar_casa": 2, "placar_fora": 1,
    "g1_tempo_casa": 1, "g1_tempo_fora": 0,
    "g2_tempo_casa": 1, "g2_tempo_fora": 1,
    "escanteio_casa": 7, "escanteio_fora": 3,
    "eventos": [
      { "confrontos_id": 198389236, "minuto": 12, "tipo_evento": "1º Gol", "nome": "Rebecca Rayner", "tempo_jogo": "1º tempo" },
      { "confrontos_id": 198389236, "minuto": 58, "tipo_evento": "2º Gol", "nome": "Ana Souza", "tempo_jogo": "2º tempo" }
    ]
  }
]
```

| Campo do provedor | Campo do sistema | Regra |
|---|---|---|
| `fonte_id` | jogo pelo `codigo_externo` | inteiro > 0; jogo inexistente → ignorado (sem log por item, só a contagem) |
| `situacao` | `confrontos_resultados.situacao` | valor de `SituacaoConfronto`; inválido → item ignorado |
| `placar_casa`, `placar_fora` | `placar_casa`, `placar_fora` | inteiro ≥ 0 ou nulo; um nulo e o outro não → item ignorado |
| `g1_tempo_*`, `g2_tempo_*` | `gols_primeiro_tempo_*`, `gols_segundo_tempo_*` | inteiro ≥ 0 ou nulo (gols **só daquele tempo**) |
| `escanteio_casa`, `escanteio_fora` | `escanteios_casa`, `escanteios_fora` | inteiro ≥ 0 ou nulo |
| `eventos` | `confrontos_gols` | lista ou `null` (`null` = sem gols informados, mantém os gravados); evento inválido é ignorado sozinho |
| `eventos[].tipo_evento` | `ordem` | número inteiro no início do texto ("10º Gol" → 10); sem número → evento ignorado |
| `eventos[].nome` | `nome` | texto, até 150 caracteres; **o provedor manda `null`** em boa parte dos gols (autor não informado): o gol entra assim mesmo, porque conta na ordem (primeiro e último gol) |
| `eventos[].minuto` | `minuto` | texto como o provedor envia, com acréscimos (`"89"`, `"45+1"`), até 10 caracteres; inválido vira nulo sem descartar o gol |
| `eventos[].tempo_jogo` | `tempo_jogo` | texto, opcional, até 20 caracteres (o provedor manda `"1"` ou `"2"`) |
| `_id`, `__v`, `created_at`, `updated_at` | — | ignorados (campos internos do provedor, conferidos na resposta real em 2026-10-14) |
| `eventos[].confrontos_id` | — | ignorado (o gol fica no jogo do item) |

Efeitos: grava o resultado; se a assinatura mudou, registra "Resultado do jogo" e reapura as
apostas do jogo; `situacao = Encerrado` também é gravada em `confrontos.situacao`.

## 7. Cancelados (`confrontos_cancelados:importar`, a cada 5 min, :04)

`GET {URL_PRE_JOGO}/cancelados` — tempo limite 60 s

```json
[
  { "fonte_id": [198389236, 198389240] }
]
```

| Campo | Regra |
|---|---|
| resposta | lista; o primeiro item traz `fonte_id` (lista de inteiros > 0). Lista vazia ou `fonte_id` vazio = nenhum jogo cancelado |
| `fonte_id[]` | código inválido é ignorado; jogo inexistente é ignorado |

Efeitos: ver [comandos.md](comandos.md#confrontos_canceladosimportar).
