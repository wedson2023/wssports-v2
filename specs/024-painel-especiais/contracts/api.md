# Contrato da API: Painel — Especiais

Prefixo `/api`, JWT do painel (`auth:api`). Erros de validação: 422 com `errors` por campo; regra de
negócio: 422 com `errors.especial` ou `errors.opcao`; sem permissão: 403; não encontrado: 404
("Categoria especial não encontrada."). Datas no horário de Brasília (`-03:00`), ISO 8601. Valores
em reais como texto com duas casas.

## 1. Rotas alteradas

### 1.1 `GET /especiais` — lista do painel (`especiais.listar`)

Query: `situacao` (`Aguardando` padrão, `Encerrado`, `Cancelado`, `Todas`), `busca` (≤ 150),
`lixeira` (`1` = só removidas; ignora `situacao`), `por_pagina` (1–100, padrão 20), `page`.

```json
{
  "data": [{
    "id": 12, "nome": "Rebaixados 2026", "data_limite": "2026-12-06T16:00:00-03:00",
    "situacao": "Aguardando", "aguardando_resultado": false, "ativo": true,
    "quantidade_vencedoras": 4, "dividir_empate": false, "quantidade_opcoes": 20,
    "vencedoras": [], "apostas_em_aberto": 37, "valor_em_aberto": "1840.00",
    "encerrado_em": null, "deleted_at": null,
    "acoes": ["detalhes", "editar", "remover", "encerrar", "cancelar"]
  }],
  "alcance": "todas",
  "meta": { "current_page": 1, "per_page": 20, "last_page": 1, "total": 1 }
}
```

`acoes`: `detalhes`, `editar`, `remover`, `encerrar`, `cancelar`, `corrigir_resultado`, `restaurar`
— conforme situação, lixeira e permissões (FR-006). `vencedoras`: `[{id, nome, empatada}]`.

### 1.2 `GET /especiais/{especial}` — gaveta (`especiais.listar`)

Igual ao item da lista, mais `opcoes` (§3) e `correcoes`:

```json
"correcoes": [{
  "id": 3, "created_at": "2026-12-07T10:12:00-03:00", "usuario": "admin",
  "vencedoras_anteriores": [{"id": 5, "nome": "Time A"}], "vencedoras_novas": [{"id": 6, "nome": "Time B"}],
  "empatadas_anteriores": [], "empatadas_novas": [],
  "dividir_empate_anterior": false, "dividir_empate_novo": false, "apostas_afetadas": 14
}]
```

### 1.3 `POST /especiais` e `PUT /especiais/{especial}` (`especiais.gerenciar`)

Campo novo `quantidade_vencedoras` (inteiro ≥ 1; padrão 1 no cadastro). No cadastro, menor que a
quantidade de `opcoes`; na edição, menor que as opções não anuladas. Mensagens: "Informe a
quantidade de vencedoras.", "A quantidade de vencedoras deve ser um número inteiro.", "A quantidade
de vencedoras deve ser menor que a quantidade de opções.". Demais campos sem mudança.

### 1.4 `POST /especiais/{especial}/encerrar` (`especiais.encerrar`)

Corpo (substitui `especiais_opcoes_id`):

```json
{ "vencedoras": [5, 7, 9, 11, 13], "empatadas": [11, 13], "dividir_empate": true }
```

Regras (Q = `quantidade_vencedoras`, M = vencedoras, E = empatadas, V = Q − (M − E)):

| Situação | Erro |
|---|---|
| opção de outra categoria ou anulada | `vencedoras.*`: "A opção não pertence a esta categoria ou foi anulada." |
| M < Q, ou M > Q sem `dividir_empate` | `vencedoras`: "Escolha {Q} opções vencedoras." |
| M = todas as não anuladas | `vencedoras`: "Para todas vencerem, cancele a categoria." |
| `dividir_empate` com M = Q | `dividir_empate`: "A divisão por empate só vale com mais vencedoras que a quantidade da categoria." |
| empatada fora de `vencedoras`, M − E ≥ Q ou E ≤ V | `empatadas`: "Marque as vencedoras empatadas na última vaga." |
| categoria não Aguardando | `especial`: "A categoria já foi encerrada ou cancelada." |

Resposta 200: a categoria (§1.2) + `apostas_afetadas`.

### 1.5 `PUT` e `DELETE /especiais/{especial}/opcoes/{opcao}` (`especiais.gerenciar`)

Recusam opção anulada: `opcao`: "A opção foi anulada.". Sem outra mudança.

## 2. Rotas novas

| Método e rota | Permissão | Descrição |
|---|---|---|
| `GET /especiais/resumo` | `especiais.listar` | Cartões (§2.1) |
| `GET /especiais/{especial}/exposicao` | `especiais.listar` | Exposição por opção (§2.2) |
| `POST /especiais/{especial}/opcoes/{opcao}/anular` | `especiais.encerrar` | Anula a opção (§2.3) |
| `POST /especiais/{especial}/corrigir-resultado` | `especiais.corrigir_resultado` | Corrige as vencedoras (§2.4) |
| `POST /especiais/{especial}/restaurar` | `especiais.gerenciar` | Restaura da lixeira (§2.5) |

As rotas com nome fixo (`resumo`) ficam antes do `apiResource('especiais')`.

### 2.1 `GET /especiais/resumo`

Query: `busca`.

```json
{ "aceitando_palpites": 4, "aguardando_resultado": 1, "valor_em_aberto": "5230.00",
  "possivel_retorno_em_aberto": "48120.50", "alcance": "rede" }
```

### 2.2 `GET /especiais/{especial}/exposicao`

```json
{
  "alcance": "todas",
  "opcoes": [{
    "id": 5, "nome": "Time A", "cotacao": "3.50", "situacao": "Ativa", "empatada": false,
    "simples": { "quantidade": 8, "valor": "320.00", "possivel_retorno": "1120.00" },
    "multiplas": { "quantidade": 3, "valor": "60.00", "possivel_retorno": "910.40" },
    "total": { "quantidade": 11, "valor": "380.00", "possivel_retorno": "2030.40" }
  }],
  "total": { "quantidade": 37, "valor": "1840.00" },
  "maior_retorno_opcoes_id": 5
}
```

Só palpites ativos em apostas Ativas e Aguardando, no alcance de quem consulta (R-03, R-04).
Todas as opções aparecem, inclusive as sem apostas (zeros) e as anuladas.

### 2.3 `POST /especiais/{especial}/opcoes/{opcao}/anular`

Sem corpo. Recusas: categoria não Aguardando (`especial`), opção de outra categoria (404), opção já
anulada (`opcao`: "A opção já foi anulada."). Resposta 200: a opção (§3) + `apostas_afetadas`.

### 2.4 `POST /especiais/{especial}/corrigir-resultado`

Corpo: o mesmo do §1.4 + `password`. Recusas: as do §1.4 (exceto Aguardando), categoria não
Encerrado (`especial`: "Só uma categoria encerrada pode ter o resultado corrigido."), resultado igual
(`vencedoras`: "O resultado é o mesmo."), senha (`password`: "Confirme com a sua senha." /
"Senha incorreta."). Resposta 200: a categoria (§1.2, com a correção nova) + `apostas_afetadas`.

### 2.5 `POST /especiais/{especial}/restaurar`

Sem corpo; a categoria precisa estar removida (senão 404). Recusa nome repetido entre as não
removidas (`nome`: "Já existe uma categoria especial com este nome."). Resposta 200: a categoria.

## 3. Opção (`EspeciaisOpcoesResource`)

```json
{ "id": 5, "nome": "Time A", "cotacao": "3.50", "ativo": true, "situacao": "Ativa",
  "vencedora": null, "empatada": false, "anulada_em": null }
```

`EspeciaisResource` troca `opcao_vencedora` por `vencedoras` e ganha `quantidade_vencedoras`,
`dividir_empate`, `aguardando_resultado` e `deleted_at`.

## 4. Comprovante

`ComprovanteApostaResource`, palpite especial: chave nova `cotacao_empate` (texto ou `null`).
