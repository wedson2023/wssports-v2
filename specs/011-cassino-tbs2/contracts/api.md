# Contrato: rotas REST e páginas do cassino

**Feature**: `011-cassino-tbs2` | **Data**: 2026-10-16

## Convenções

- **Base**: `/api`.
- **Formato**: JSON em `snake_case`; dinheiro em texto com duas casas (`"12.50"`); datas em ISO
  8601 com `-03:00`.
- **Erros**: seguem o padrão do projeto (`{"message": "..."}`, 422 com `errors`).
- **Paginação**: listas paginadas no padrão do projeto (`data`, `links`, `meta`), com `pagina` e
  `por_pagina`, máximo 100.
- **Callback do provedor**: segue o formato do provedor e está em [provedor.md](provedor.md).

## 1. Site (público)

### `GET /publico/cassino?aparelho=celular|computador`

Dados da página inicial do cassino (R-07). Usa cache de 5 minutos por aparelho. Com o cassino
desligado: 403 `{"message": "Acesso ao cassino não está disponível."}`.

```json
{
  "provedores": [
    { "id": 1, "codigo": "pgsoft", "nome": "PG Soft", "imagem": "/images/cassino/provedores/pgsoft.png", "quantidade": 142 }
  ],
  "tipos": [
    { "tipo": "slots", "quantidade": 3120 }
  ],
  "linhas": [
    { "tipo": "populares", "jogos": [ { "...": "jogo" } ] },
    { "tipo": "slots", "jogos": [ { "...": "até 10 jogos" } ] }
  ],
  "demo_habilitado": false
}
```

**Jogo** (mesmo formato em todas as listas):

```json
{ "id": 812, "nome": "Fortune Tiger", "provedor": "PG Soft", "tipo": "slots", "imagem": "https://...", "demo": false }
```

### `GET /publico/cassino/jogos`

Grade filtrada (FR-009, FR-010).

**Parâmetros**:

| Parâmetro | Regra |
|---|---|
| `aparelho` | Obrigatório: `celular` ou `computador` |
| `provedor` | Id do provedor (opcional) |
| `tipo` | Tipo do jogo (opcional); `populares` usa a regra da linha Populares |
| `busca` | Opcional, 2 a 60 caracteres, busca no nome |
| `pagina` | Página |
| `por_pagina` | Padrão 50, máximo 100 |

A busca ignora `provedor` e `tipo`, como no antigo.

**Resposta**: lista paginada de jogos. Sem resultado: `data` vazio (a página mostra "Nenhum jogo
encontrado.").

### `POST /publico/cassino/jogos/{jogo}/demo`

Abre o demo (R-10), `throttle:20,1`.

| Situação | Resposta |
|---|---|
| Demo desligado na configuração ou jogo sem demo | 422 "Demo indisponível para este jogo." |
| Demo aberto | 200 com o mesmo formato da abertura (§2) e `sessao_id` nulo |

## 2. Área do cliente (`auth:clientes` + `GarantirAcessoCliente`)

### `POST /area-cliente/cassino/jogos/{jogo}/abrir`

Abre o jogo com dinheiro (FR-013 a FR-017, R-08), `throttle:20,1`.

**200**:

```json
{
  "url": "https://...",
  "moldura": true,
  "sessao_id": 4521,
  "jogo": { "id": 812, "nome": "Fortune Tiger" }
}
```

`moldura` vem de `withoutFrame` = 0.

**Erros**:

| Situação | Status | Mensagem |
|---|---|---|
| Acesso assistido | 403 | "O acesso assistido não pode abrir jogos com o dinheiro do cliente." |
| Cassino desligado | 403 | "Acesso ao cassino não está disponível." |
| Cliente inativo | 403 | Mensagem do `GarantirAcessoCliente` |
| Jogo inativo, removido ou sem canal abrível | 422 | "Jogo indisponível no momento." |
| Provedor fora do ar ou tempo esgotado | 503 | "Não foi possível abrir o jogo. Tente novamente." |

### `GET /area-cliente/minha-conta/extrato` (existente, alterado)

- **Parâmetro**: `operacao` aceita `cassino`.
- **Linhas de cassino**: aparecem uma por sessão (R-20).
- **Formato da linha**: ganha a chave `cassino` (nula nas demais linhas):

```json
{
  "id": 99120,
  "data": "2026-10-16T21:04:11-03:00",
  "operacao": "Cassino",
  "tipo": "Débito",
  "valor": "35.00",
  "aposta": null,
  "cassino": {
    "sessao_id": 4521,
    "jogo": "Fortune Tiger",
    "total_apostado": "35.00",
    "total_ganho": "12.40",
    "quantidade_rodadas": 70
  }
}
```

Na linha de cassino:
- `valor` = `total_apostado`;
- `tipo` = `Débito` se o líquido da sessão for negativo, senão `Crédito`.

### `GET /area-cliente/cassino/sessoes/{sessao}/rodadas`

Rodadas de uma sessão do próprio cliente (FR-028), paginado (padrão 50, máximo 100). Sessão de
outro cliente: 404. Funciona no acesso assistido (só consulta).

```json
{
  "sessao": { "id": 4521, "jogo": "Fortune Tiger", "provedor": "PG Soft", "total_apostado": "35.00", "total_ganho": "12.40" },
  "data": [
    {
      "id": 88001,
      "data": "2026-10-16T21:01:02-03:00",
      "trade_id": "a1b2c3",
      "acao": "SPIN",
      "efeito": "Aposta com prêmio",
      "carteira": "Saldo",
      "saldo_anterior": "50.00",
      "aposta": "0.50",
      "premio": "1.20",
      "cancelada": false
    }
  ],
  "links": {},
  "meta": {}
}
```

## 3. Painel (`auth:api` + `garantir_acesso`)

Permissões (R-24):
- `cassinos_jogos.gerenciar`: §3.1 a §3.4;
- `cassinos_rodadas.listar`: §3.5 a §3.7;
- `configuracoes.editar`: §3.8.

Toda alteração do catálogo limpa o cache da página.

### 3.1 Jogos

#### `GET /cassinos-jogos`

**Filtros**:
- `busca`;
- `provedor`;
- `tipo`;
- `ativo`;
- `popular`;
- `removido` (`sim`/`nao`);
- `sem_canal_abrivel`;
- `repetidos`: jogos cujo nome também existe em outro provedor (R-02);
- `pagina`, `por_pagina` (padrão 20, máximo 100).

**Item da lista**:
- jogo;
- provedor;
- `aberturas`;
- `canais`: todos, com `canal`, `codigo_provedor`, `pre_pago`, `bonus_primeiro`, `prioridade`,
  `ativo`, `demo`, `indisponivel_em`, `motivo_indisponibilidade` e `removido_em`.

#### `PUT /cassinos-jogos/{jogo}`

Campos (`sometimes`):

| Campo | Regra |
|---|---|
| `ativo` | boolean |
| `popular` | boolean |
| `ordem` | inteiro, 0 a 65535 |

O nome do jogo vem sempre do provedor (é a chave do jogo, R-03) e não é editado no painel.

Quando a ativação deixa o jogo com o mesmo nome de outro jogo visível de outro provedor, a resposta
traz `"aviso": "Já existe um jogo com este nome em outro provedor; ele aparecerá duas vezes na
página."` (R-02).

### 3.2 Canais

#### `PUT /cassinos-jogos-canais/{canal}`

Campos (`sometimes`):

| Campo | Regra |
|---|---|
| `ativo` | boolean |
| `pre_pago` | boolean |
| `bonus_primeiro` | boolean |
| `prioridade` | inteiro, 0 a 999 |
| `disponivel` | `true` limpa `indisponivel_em` e `motivo_indisponibilidade` |

### 3.3 Provedores

#### `GET /cassinos-provedores`

Lista paginada com a quantidade de jogos visíveis.

#### `PUT /cassinos-provedores/{provedor}`

Campos (`sometimes`):

| Campo | Regra |
|---|---|
| `nome` | Nome exibido |
| `ativo` | boolean |
| `ordem` | inteiro |
| `porcentagem_comissao` | 0 a 100, duas casas |
| `imagem` | Arquivo PNG/JPG/WEBP até 1 MB, gravado com `ArmazenamentoImagens` |

### 3.4 Sincronização e tipos de ação

#### `POST /cassinos-sincronizacoes`

Dispara a carga sob demanda (job), `throttle:1,1`. Responde 202 com a sincronização
`Em andamento`. Com outra carga em andamento: 409 "Já existe uma atualização do catálogo em
andamento.".

#### `GET /cassinos-sincronizacoes`

Lista paginada das cargas (padrão 20).

#### Tipos de ação: `GET` · `POST` · `PUT` · `DELETE /cassinos-acoes`

| Campo | Regra |
|---|---|
| `acao` | Obrigatório, maiúsculas, `[A-Z0-9_]`, único |
| `efeito` | Valor de `EfeitoAcaoCassino` |
| `ativo` | boolean |
| `descricao` | Até 255 caracteres |

`DELETE` faz soft delete. A ação usada em rodadas continua ligada a elas.

### 3.5 Chamadas do provedor

#### `GET /cassinos-chamadas`

**Filtros**:
- `resultado`;
- `comando`;
- `login`;
- `trade_id`;
- `data_inicial`, `data_final`;
- `pagina`, `por_pagina` (padrão 20, máximo 100).

**Item da lista**: `dados`, `resposta`, `resultado`, `erro`, `ip`, `duracao_ms` e `data`.

### 3.6 Relatório

#### `GET /cassinos-rodadas/relatorio`

**Parâmetros**:

| Parâmetro | Regra |
|---|---|
| `data_inicial` | Obrigatório, data |
| `data_final` | Obrigatório, data; período de no máximo 366 dias |
| `agrupar` | `jogo` (padrão), `provedor` ou `canal` |
| `provedor` | Opcional |
| `pagina`, `por_pagina` | Padrão 20, máximo 100 |

```json
{
  "resumo": {
    "rodadas": 15230,
    "entradas": "8450.00",
    "saidas": "7210.50",
    "ggr": "1239.50",
    "comissao": "201.11",
    "ggr_desde_prestacao": "3120.00",
    "data_prestacao_contas": "2026-10-01"
  },
  "data": [
    {
      "grupo": "Fortune Tiger",
      "provedor": "PG Soft",
      "canal": null,
      "tipo": "slots",
      "rodadas": 7020,
      "entradas": "4010.00",
      "saidas": "3550.00",
      "ggr": "460.00",
      "porcentagem_comissao": "15.50",
      "comissao": "71.30"
    }
  ],
  "links": {},
  "meta": {}
}
```

### 3.7 Histórico no provedor

#### `GET /cassinos-sessoes/{sessao}/historico`

Chama `gameSessionsLog` (R-23) e devolve:

```json
{ "sessao": { "id": 4521, "cliente": "…", "jogo": "…" }, "provedor": { "...": "conteúdo do provedor" } }
```

Provedor fora do ar: 503.

### 3.8 Configuração

#### `GET /configuracoes/cassino` · `PUT /configuracoes/cassino`

| Campo | Regra |
|---|---|
| `cassino_habilitado` | boolean |
| `cassino_demo_habilitado` | boolean |
| `data_prestacao_contas_cassino` | data ou nulo |
| `dias_registro_chamadas_cassino` | inteiro, 1 a 90 |

O GET também devolve `url_callback` (`APP_URL/api/cassino/carteira`), para o administrador
cadastrar no provedor.

## 4. Páginas e arquivos (web)

| Rota | Resposta |
|---|---|
| `GET /cassino` | Página Inertia `Casino`. Props: `cassino` (`habilitado`, `demo_habilitado`, `somente_cassino`: sem o atalho "Esportes" no menu lateral), `banners` e `promocoes` (vigentes: campanha, modalidade, valor, tipo de ganho, regra e fim). Com o cassino desligado, a página mostra o alerta e volta para `/` em 3 s, como no antigo (FR-012, R-21) |
| `GET /close.php` | HTML mínimo sem cache: envia `closeGame` ao `parent` e, fora de moldura, redireciona para `/cassino` (R-09) |

Props compartilhadas (alteradas): `indicadores.cassino` passa a vir de
`configuracoes.cassino_habilitado`.

## 5. Coleção do Postman

A coleção `docs/postman/wssports_api.postman_collection.json` é regenerada com:
- a pasta "Cassino", com as rotas das seções 1 a 3;
- o callback `POST /cassino/carteira`, com exemplos de `getBalance` e `writeBet`;
- o filtro `operacao=cassino` no extrato.

As variáveis `base_url` e `token` são mantidas.
