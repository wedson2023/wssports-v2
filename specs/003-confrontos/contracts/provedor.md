# Contrato com os provedores de cotações

**Feature**: `003-confrontos` | **Data**: 2026-10-01 (revisto em 2026-10-05) | **Plano**: [../plan.md](../plan.md)

Formato que o sistema **consome**. É o formato que as rotas do provedor já têm hoje, as mesmas do
sistema antigo; a API do provedor **não muda** (Clarifications 2026-10-05). O sistema traduz os
nomes do provedor para os seus na gravação. Endereços e chave vêm de `config/services.php` →
`provedor_cotacoes` (R-05).

## Regras comuns

- Autenticação nos parâmetros da URL, como a API exige: `?key={PROVEDOR_COTACOES_CHAVE}&app={PROVEDOR_COTACOES_APP}`
  (`app` vazio usa o `APP_URL`). A URL chamada e a mensagem original de erro nunca vão para o log
  (FR-053).
- O sistema envia `Accept: application/json` e `Accept-Encoding: gzip`; a resposta pode vir
  compactada.
- Toda resposta é uma **lista** JSON. Qualquer outra coisa, status diferente de 2xx ou tempo
  esgotado = falha daquela carga (nada é gravado e a falha vai para o log).
- Corpo das rotas `POST`: `{ "campeonatos_id": [<fonte_id dos campeonatos desativados>] }`, para
  que os jogos deles não retornem (FR-004).
- Datas em UTC, no formato `YYYY-MM-DD HH:MM:SS` (o formato ISO também é aceito).
- Cotações: um campo por código, `odd1` a `odd323`, no próprio item; valor numérico ≥ 0 (zero,
  nulo ou ausente = indisponível). Só os diferentes de zero são gravados.
- Item com dado inválido é ignorado, com o motivo no log, sem derrubar os demais.

Endereços base (padrão em `config/services.php`, alteráveis por variável de ambiente):

| Variável | Padrão |
|---|---|
| `PROVEDOR_COTACOES_URL_PRE_JOGO` | `https://apiprejogo.wssports.bet/api` |
| `PROVEDOR_COTACOES_URL_AO_VIVO` | `https://apiaovivo.wssports.bet/api` |
| `PROVEDOR_COTACOES_URL_CONFERENCIA` | `https://api.oddbrasil.com/bet/v2` |

## 1. Campeonatos (`campeonatos:importar`, a cada 10 min, :00)

`GET {URL_PRE_JOGO}/campeonatos` — sem corpo — tempo limite 60 s

```json
[
  { "fonte_id": 2, "nome": "Australia New South Wales League 2", "pais": "Australia", "bandeira": "au" }
]
```

| Campo do provedor | Campo do sistema | Regra |
|---|---|---|
| `fonte_id` | `campeonatos.codigo_externo` | inteiro > 0, obrigatório |
| `nome`, `pais` | `nome`, `pais` | texto, obrigatório |
| `bandeira` | `bandeira` | texto, opcional |

## 2. Confrontos (`confrontos:importar`, a cada 5 min, :01)

`POST {URL_PRE_JOGO}/confrontos` — corpo `campeonatos_id` — tempo limite 180 s

```json
[
  {
    "fonte_id": 198389236,
    "campeonatos_id": 2,
    "casa": "Sydney United",
    "escudo_casa": "https://.../4741.png",
    "escudo_fora": "https://.../4748.png",
    "fora": "Rockdale Ilinden",
    "situacao": "Aguardando",
    "tipo_esporte": "FUTEBOL",
    "horario": "2026-10-01 09:00:00",
    "created_at": "...",
    "updated_at": "..."
  }
]
```

| Campo do provedor | Campo do sistema | Regra |
|---|---|---|
| `fonte_id` | `confrontos.codigo_externo` | inteiro > 0, obrigatório |
| `campeonatos_id` | `campeonatos_id` (pelo `codigo_externo` do campeonato) | campeonato ainda inexistente → confronto ignorado nesta carga (FR-005a) |
| `casa`, `fora` | `time_casa`, `time_fora` | texto, obrigatório |
| `escudo_casa`, `escudo_fora` | `escudo_casa`, `escudo_fora` | texto, opcional |
| `tipo_esporte` | `esporte` | texto, obrigatório |
| `situacao` | `situacao` | Aguardando, Encerrado, Cancelado, Adiado ou Bloqueado (outro valor → confronto ignorado) |
| `horario` | `data_inicio` | data UTC, obrigatório |
| `created_at`, `updated_at` | — | ignorados |

## 3. Cotações (`confrontos_cotacoes:importar`, a cada 5 min, :02)

`POST {URL_PRE_JOGO}/cotacao` — corpo `campeonatos_id` — tempo limite 180 s

```json
[
  {
    "fonte_id": 198389236,
    "odd1": 1.40, "odd2": 4.20, "odd3": 2.75, "odd4": 0, "...": "...", "odd323": 0,
    "jogador": [
      { "atletas_id": 1503330, "nome": "Rebecca Rayner", "odd": 21.00, "opcao": "Marcadores", "tipo": "Primeiro", "confrontos_id": 198389236 }
    ]
  }
]
```

| Campo do provedor | Campo do sistema | Regra |
|---|---|---|
| `fonte_id` | confronto pelo `codigo_externo` | confronto ainda inexistente → ignorado nesta carga (FR-005a) |
| `odd1` … `odd323` | `confrontos.cotacoes` (JSON só com os ≠ 0) | numérico ≥ 0; inválido → item ignorado |
| `jogador` | `confrontos_jogadores` | lista ou `null`; jogador inválido é ignorado sozinho |
| `jogador[].atletas_id` | `codigo_externo` | inteiro > 0 |
| `jogador[].nome`, `opcao`, `tipo` | `nome`, `opcao`, `tipo` | texto |
| `jogador[].odd` | `odd` | > 0 |
| `jogador[].confrontos_id` | — | ignorado (o jogador fica no confronto do item) |

O sorteio de `odd4`/`odd7` (FR-008) usa o esporte já gravado pela carga dos confrontos.

## 4. Ao vivo (`confrontos_ao_vivo:importar`, a cada 5 s)

`POST {URL_AO_VIVO}/aovivo` — corpo `campeonatos_id` — tempo limite 4 s

```json
[
  {
    "fonte_id": 201614792,
    "campeonatos_id": 6,
    "casa": "Australia", "escudo_casa": "https://.../4741.png",
    "fora": "Brasil", "escudo_fora": "https://.../4748.png",
    "tipo_esporte": "FUTEBOL AO VIVO",
    "horario": "2026-10-01 10:00:00",
    "situacao": "Intervalo",
    "minuto_exato": "45",
    "tempo": "45:00",
    "placar_casa": 0, "placar_fora": 0,
    "g1_tempo_casa": 0, "g1_tempo_fora": 0, "g2_tempo_casa": 0, "g2_tempo_fora": 0,
    "escanteio_casa": 8, "escanteio_fora": 1,
    "odd1": 6.50, "odd2": 3.00, "odd3": 1.73, "...": "...", "odd323": 0
  }
]
```

| Campo do provedor | Campo do sistema | Regra |
|---|---|---|
| `fonte_id`, `campeonatos_id`, `casa`, `fora`, escudos, `horario` | como nos confrontos | iguais à rota 2 |
| `tipo_esporte` | `esporte` | o provedor manda o esporte com o sufixo ` AO VIVO` (ex.: `FUTEBOL AO VIVO`, como no sistema antigo); o sufixo é retirado na gravação, e o esporte fica igual ao do pré-jogo (`FUTEBOL`) |
| `situacao` | `situacao` | 1 tempo, Intervalo ou 2 tempo |
| `minuto_exato` | `minuto` | inteiro ≥ 0, obrigatório |
| `tempo` | `cronometro` | texto, opcional |
| `placar_casa`, `placar_fora` | `placar_casa`, `placar_fora` | inteiro ≥ 0, obrigatório |
| `g1_tempo_casa`, `g1_tempo_fora` | `gols_primeiro_tempo_casa`, `gols_primeiro_tempo_fora` | inteiro ≥ 0, opcional |
| `g2_tempo_casa`, `g2_tempo_fora` | `gols_segundo_tempo_casa`, `gols_segundo_tempo_fora` | inteiro ≥ 0, opcional |
| `escanteio_casa`, `escanteio_fora` | `escanteios_casa`, `escanteios_fora` | inteiro ≥ 0, opcional |
| `odd1` … `odd323` | `confrontos_ao_vivo.cotacoes` | como na rota 3 |

Lista vazia = nenhum jogo atualizado nessa rodada (a trava por tempo cuida do resto).

## 5. Conferência (`confrontos_ao_vivo:conferir`, a cada minuto)

`GET {URL_CONFERENCIA}/confrontos/{fonte_id}` — tempo limite 10 s

```json
{ "fonte_id": 201614792, "minuto_exato": 47 }
```

Só o campo `minuto_exato` é usado (numérico); sem ele, a conferência não muda nada (FR-024).
