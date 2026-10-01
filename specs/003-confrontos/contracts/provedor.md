# Contrato com os provedores de cotações

**Feature**: `003-confrontos` | **Data**: 2026-10-01 | **Plano**: [../plan.md](../plan.md)

Formato que o sistema **consome**. A API do provedor principal é do próprio responsável e deve
ser ajustada a este contrato (dependência registrada na spec). Endereços e chave vêm de
`config/services.php` → `provedor_cotacoes` (R-05).

## Regras comuns

- Chave no cabeçalho `PROVEDOR_COTACOES_CABECALHO_CHAVE` (padrão `X-Api-Key`); nunca na URL.
- O sistema envia `Accept: application/json` e `Accept-Encoding: gzip`; a resposta pode vir
  compactada.
- Datas em UTC, no formato `YYYY-MM-DD HH:MM:SS`.
- Cotações: objeto com códigos `odd1` a `odd323` e valores numéricos ≥ 0 (zero ou ausente =
  indisponível). Recomendado mandar só os diferentes de zero.
- Status diferente de 2xx, tempo esgotado ou JSON fora do formato = falha (nada é gravado e a
  falha vai para o log).

## 1. Pré-jogo (`confrontos:importar`, a cada 5 min)

`POST {PROVEDOR_COTACOES_URL_PRE_JOGO}` — tempo limite 60 s

Corpo enviado:

```json
{ "campeonatos_desativados": [2, 1503, 8812] }
```

Resposta:

```json
{
  "gerado_em": "2026-10-01T12:00:00Z",
  "campeonatos": [
    {
      "codigo_externo": 2,
      "nome": "Australia New South Wales League 2",
      "pais": "Australia",
      "bandeira": "au",
      "confrontos": [
        {
          "codigo_externo": 198389236,
          "time_casa": "Sydney United",
          "escudo_casa": "https://.../4741.png",
          "time_fora": "Rockdale Ilinden",
          "escudo_fora": "https://.../4748.png",
          "esporte": "FUTEBOL",
          "situacao": "Aguardando",
          "data_inicio": "2026-10-01 09:00:00",
          "cotacoes": { "odd1": 1.40, "odd2": 4.20, "odd3": 2.75, "odd5": 1.85 },
          "jogadores": [
            { "codigo_externo": 1503330, "nome": "Rebecca Rayner", "opcao": "Marcadores", "tipo": "Primeiro", "odd": 21.00 }
          ]
        }
      ]
    }
  ]
}
```

| Campo | Obrigatório | Regra |
|---|---|---|
| `campeonatos` | sim | lista (pode ser vazia) |
| `campeonatos[].codigo_externo`, `nome`, `pais` | sim | inteiro > 0; texto |
| `campeonatos[].confrontos` | sim | lista (pode ser vazia) |
| `confrontos[].codigo_externo`, `time_casa`, `time_fora`, `esporte`, `situacao`, `data_inicio` | sim | `situacao` ∈ Aguardando, Encerrado, Cancelado, Adiado |
| `confrontos[].cotacoes` | sim | objeto (pode ser vazio) |
| `confrontos[].jogadores` | não | lista; `odd` > 0 |

## 2. Ao vivo (`confrontos_ao_vivo:importar`, a cada 5 s)

`POST {PROVEDOR_COTACOES_URL_AO_VIVO}` — tempo limite 4 s

Corpo enviado: igual ao pré-jogo (`campeonatos_desativados`).

Resposta (só jogos em andamento):

```json
{
  "confrontos": [
    {
      "codigo_externo": 201614792,
      "campeonato_codigo_externo": 6,
      "time_casa": "Australia",
      "escudo_casa": "https://.../4741.png",
      "time_fora": "Brasil",
      "escudo_fora": "https://.../4748.png",
      "esporte": "FUTEBOL",
      "data_inicio": "2026-10-01 10:00:00",
      "situacao": "Intervalo",
      "minuto": 45,
      "cronometro": "45:00",
      "placar_casa": 0,
      "placar_fora": 0,
      "gols_primeiro_tempo_casa": 0,
      "gols_primeiro_tempo_fora": 0,
      "gols_segundo_tempo_casa": 0,
      "gols_segundo_tempo_fora": 0,
      "escanteios_casa": 8,
      "escanteios_fora": 1,
      "cotacoes": { "odd1": 6.50, "odd2": 3.00, "odd3": 1.73 }
    }
  ]
}
```

- `situacao` ∈ `1 tempo`, `Intervalo`, `2 tempo`.
- Lista vazia = nenhum jogo atualizado nessa rodada (a trava por tempo cuida do resto).

## 3. Conferência (`confrontos_ao_vivo:conferir`, a cada minuto)

`GET {PROVEDOR_COTACOES_URL_CONFERENCIA}/confrontos/{codigo_externo}` — tempo limite 10 s

Resposta:

```json
{ "codigo_externo": 201614792, "minuto": 47 }
```

Substitui a consulta antiga a `api.oddbrasil.com/bet/v2/confrontos/{id}`, mantendo só o campo
usado (`minuto`, antigo `minuto_exato`).
