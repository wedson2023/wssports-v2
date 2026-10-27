# Contrato da API: Painel — Confrontos

Prefixo `/api`, mesmo formato da spec 003 (`specs/003-confrontos/contracts/api.md`): JSON em
`snake_case`, 422 com `message` e `errors` por campo, listagens paginadas do Laravel (`data`,
`links`, `meta`), `por_pagina` de 1 a 100. Autenticação do painel (`auth:api` + `garantir_acesso` +
permissão). Valores em reais como texto com 2 casas. Datas em ISO 8601 (UTC), exibidas no fuso de
quem vê.

Aqui só o que é **novo** ou **muda**; o resto continua como na spec 003 e na 009. As rotas novas
`confrontos/*` e `campeonatos/paises` ficam **antes** do `apiResource` correspondente em
`routes/api.php` (senão caem no `{confronto}`).

**Filtros da lista** (usados em §1.1, §2.1 e §2.2): `busca`, `campeonatos_id`, `ativo`, `manual`,
`esporte`, `situacao`, `dia` (`ontem`, `hoje`, `amanha`, `depois_de_amanha`), `data` (`Y-m-d`, de
hoje −30 a hoje +2), `tipo` (`todos` padrão, `pre_jogo`, `ao_vivo`), `fuso_horario` (`±HH:MM`,
padrão `-03:00`). Regras e mensagens em [data-model.md §4](../data-model.md).

---

## 1. Rotas alteradas

### 1.1 `GET /confrontos` — `confrontos.listar`

Query: os filtros da lista + `por_pagina` (padrão 20). `busca` passa a incluir o nome do
campeonato. Cada item ganha:

```json
{
  "id": 812,
  "time_casa": "Flamengo", "time_fora": "Palmeiras",
  "campeonato": { "id": 14, "nome": "Brasileirão Série A", "pais": "Brasil", "ativo": true },
  "data_inicio": "2026-10-27T22:00:00Z",
  "situacao": "Aguardando", "ativo": true, "manual": false,
  "limite_valor_apostado": "50000.00",
  "ao_vivo": { "id": 3301, "minuto": 63, "cronometro": "63:12", "situacao": "2 tempo", "placar_casa": 1, "placar_fora": 0, "limite_valor_apostado": "5000.00" },
  "resultado": { "placar_casa": 2, "placar_fora": 1 },
  "nao_permitido": false,
  "restricoes": { "nao_permitido_proprio": { "pre_jogo_id": null, "ao_vivo_id": null }, "redes_desativadas": 2 },
  "acoes": { "alterar_situacao": true, "desativar_rede": false, "alterar_limite": true, "lancar_resultado": true, "alterar_cotacoes": true, "editar": false, "excluir": false }
}
```

- `ao_vivo`: `null` quando o jogo não está ao vivo agora (research R-04).
- `resultado`: `null` sem resultado; na lista só o placar.
- `restricoes.redes_desativadas`: só para Admin e Supervisor; `null` para os demais.
- `acoes`: o que a linha mostra, pelas permissões, pela função e pelo jogo (`editar`/`excluir` só
  manuais; `lancar_resultado` só Admin; `desativar_rede` para quem não é Admin nem Supervisor e tem
  `confrontos_nao_permitidos.gerenciar`).

### 1.2 `GET /confrontos/{confronto}` — `confrontos.listar`

Igual ao item de §1.1, mais `jogadores` (como hoje) e:

```json
{
  "cotacoes_site": { "odd1": "1.85", "odd2": "3.40", "odd3": "4.10" },
  "resultado": {
    "placar_casa": 2, "placar_fora": 1,
    "gols_primeiro_tempo_casa": 1, "gols_primeiro_tempo_fora": 0,
    "gols_segundo_tempo_casa": 1, "gols_segundo_tempo_fora": 1,
    "escanteios_casa": 6, "escanteios_fora": 3,
    "gols": [{ "ordem": 1, "nome": "Pedro", "minuto": "23", "tempo_jogo": "1T" }],
    "origem": "Lançamento manual"
  },
  "restricoes": { "nao_permitido_proprio": { "pre_jogo_id": null, "ao_vivo_id": null }, "redes_desativadas": 2, "nao_permitido_todos": false }
}
```

- `cotacoes_site`: as cotações principais como o visitante vê (research R-13); `null` para códigos
  que o jogo não tem.

### 1.3 `PUT /confrontos/{confronto}/resultado` — `confrontos.lancar_resultado` (só Admin)

- Aceita jogo do provedor e manual (sai a recusa de jogos do provedor).
- Corpo: `placar_casa`, `placar_fora`, `gols_primeiro_tempo_casa`, `gols_primeiro_tempo_fora`,
  `gols_segundo_tempo_casa`, `gols_segundo_tempo_fora`, `escanteios_casa`, `escanteios_fora` (todos
  obrigatórios, inteiros 0 a 99) e `gols` (opcional, como hoje).
- 422 `errors.placar_casa`: "A soma dos gols dos times não bate com os gols dos tempos." quando a soma
  do placar ≠ soma dos tempos.
- Resposta: como hoje (`ConfrontosResultadosResource`, com `apostas_apuradas`).

### 1.4 `DELETE /confrontos/{confronto}` — `confrontos.excluir` (só manuais)

- 422 `errors.confronto`: "Este jogo tem N apostas. Cancele o jogo em vez de excluir." quando houver
  palpites ativos de apostas não canceladas (research R-16).

### 1.5 `GET /campeonatos` — `campeonatos.listar`

- Ordem `favorito desc, nome`. Cada item ganha `quantidade_jogos`, `restricoes`
  (`{ nao_permitido_proprio_id, redes_desativadas }`) e `acoes` (`alterar_situacao`,
  `desativar_rede`, `favoritar`, `alterar_cotacoes`, `editar`, `excluir`, `adicionar_jogo`).

### 1.6 `DELETE /campeonatos/{campeonato}` — `campeonatos.excluir` (só manuais)

- 422 `errors.campeonato`: "Este campeonato tem jogos com apostas." quando algum jogo dele cair em
  §1.4.

---

## 2. Rotas novas

### 2.1 `GET /confrontos/resumo` — `confrontos.listar`

Query: os filtros da lista, menos `situacao`.

```json
{ "data": { "jogos": 148, "ao_vivo": 12, "desativados": 5, "pendentes": 3 } }
```

- `ao_vivo`: jogos ao vivo agora, sem o filtro de dia.
- `desativados`: do filtro, com `ativo = false`; para o Gerente, soma os desativados para a rede dele.
- `pendentes`: total da §2.3; `null` para quem não pode usar `confrontos.lancar_resultado`.

### 2.2 `GET /confrontos/mais-apostados` — `confrontos.listar`

Query: os filtros da lista + `por_pagina`. Só jogos com aposta na rede (research R-09/R-10).

```json
{
  "data": [{
    "id": 812, "time_casa": "Flamengo", "time_fora": "Palmeiras",
    "campeonato": { "id": 14, "nome": "Brasileirão Série A", "pais": "Brasil" },
    "data_inicio": "2026-10-27T22:00:00Z",
    "quantidade": 37,
    "simples": { "apostas": 21, "valor": "840.00", "retorno": "1720.50" },
    "multiplas": { "apostas": 16, "valor": "160.00", "retorno": "9870.00" }
  }],
  "links": {}, "meta": {}
}
```

### 2.3 `GET /confrontos/pendentes` — `confrontos.lancar_resultado`

Query: `busca`, `por_pagina`.

```json
{
  "data": [{
    "id": 790, "time_casa": "Ceará", "time_fora": "Sport",
    "campeonato": { "id": 20, "nome": "Série B", "pais": "Brasil" },
    "data_inicio": "2026-10-27T15:00:00Z",
    "atraso_minutos": 185,
    "apostas": 4, "valor_em_aberto": "120.00",
    "manual": false
  }],
  "links": {}, "meta": {}
}
```

Regra em research R-12. Ordem: `atraso_minutos` desc.

### 2.4 `GET /confrontos/{confronto}/mais-apostados` — `confrontos.listar`

```json
{
  "data": [{
    "codigo_cotacao": "odd1", "jogador": null,
    "quantidade": 12,
    "simples": { "apostas": 8, "valor": "320.00", "retorno": "592.00" },
    "multiplas": { "apostas": 4, "valor": "40.00", "retorno": "2210.00" }
  }]
}
```

- `jogador`: `{ "id": 55, "nome": "Pedro" }` nos palpites de jogador. Sem paginação (as opções de um
  jogo são poucas); ordem `quantidade` desc.

### 2.5 `GET /confrontos/{confronto}/apostas` — `confrontos.listar`

Query: `por_pagina` (padrão 30).

```json
{
  "data": [{
    "codigo": "A7K2M9QX", "created_at": "2026-10-27T18:02:11Z",
    "vendedor": { "id": 31, "nome": "João" }, "cliente": null,
    "palpites": 3,
    "codigo_cotacao": "odd1", "jogador": null,
    "valor": "10.00", "premio": "92.40", "resultado": "Aguardando"
  }],
  "links": {}, "meta": {}
}
```

- `palpites` > 1 = múltipla ("Múltipla (N jogos)" na tela). Ordem `created_at` desc.

### 2.6 `GET /campeonatos/paises` — `campeonatos.listar`

```json
{ "data": ["Brasil", "Espanha", "Inglaterra"] }
```

### 2.7 `GET /confrontos/esportes` — `confrontos.listar`

```json
{ "data": ["FUTEBOL", "BASQUETE"] }
```

---

## 3. Rotas usadas sem mudança

| Ação | Rota | Permissão |
|---|---|---|
| Ativar/desativar jogo para todos | `PATCH /confrontos/{id}/situacao` `{ ativo }` | `confrontos.alterar_situacao` |
| Desativar jogo para a rede | `POST /confrontos-nao-permitidos` `{ confrontos_id, alvo: "Vendedores", usuarios_id }` e `POST /confrontos-ao-vivo-nao-permitidos` | `confrontos_nao_permitidos.gerenciar` / `confrontos_ao_vivo_nao_permitidos.gerenciar` |
| Reativar para a rede | `DELETE /confrontos-nao-permitidos/{registro}` e `DELETE /confrontos-ao-vivo-nao-permitidos/{registro}` | idem |
| Limite | `PATCH /confrontos/{id}/limite` e `PATCH /confrontos-ao-vivo/{id}/limite` `{ limite_valor_apostado }` | `confrontos.alterar_limite` |
| Cadastro e edição manuais | `POST/PUT /confrontos`, `POST/PUT /campeonatos` | `confrontos.cadastrar`/`editar`, `campeonatos.cadastrar`/`editar` |
| Campeonato: situação, favorito, rede | `PATCH /campeonatos/{id}/situacao`, `PATCH /campeonatos/{id}/favorito`, `POST/DELETE /campeonatos-nao-permitidos` | `campeonatos.alterar_situacao`, `campeonatos.favoritar`, `campeonatos_nao_permitidos.gerenciar` |
| Nomes das cotações | `GET /cotacoes/nomes` | **muda**: além das permissões de porcentagem (spec 016), passa a aceitar `confrontos.listar` (`CotacoesPainelController::nomes`) |
