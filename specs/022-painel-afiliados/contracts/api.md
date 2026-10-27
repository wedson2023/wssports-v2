# Contrato da API: Painel — Afiliados

Prefixo `/api`, mesmo formato da spec 012 (`specs/012-afiliados/contracts/api.md`): JSON em
`snake_case`, 422 com `message` e `errors` por campo, listagens paginadas do Laravel (`data`,
`links`, `meta`), `por_pagina` de 1 a 100. Autenticação do painel (`auth:api` + `garantir_acesso` +
permissão). Afiliado fora da rede: `404 { "message": "Afiliado não encontrado." }`.

Aqui só o que é **novo** ou **muda**; o resto continua como na spec 012.

---

## 1. Rotas novas

### 1.1 `GET /afiliados/resumo` — `afiliados.listar`

Cartões da tela (FR-004). Query: os mesmos filtros de `GET /afiliados` (§2.1), menos `situacao`,
`grupo` e `por_pagina`.

```json
{
  "data": {
    "total_afiliados": 42,
    "em_analise": 3,
    "comissao_periodo": "1250.00",
    "a_receber": "830.50",
    "periodo": { "de": "2026-10-01", "ate": "2026-10-27" }
  }
}
```

- `total_afiliados`: Ativos e Inativos do filtro, na rede (o período não muda a contagem).
- `em_analise`: todos os cadastros Em análise, sem busca nem filtros; só para o Admin com
  `afiliados.alterar_situacao`; `null` para os demais.
- `comissao_periodo` (no período) e `a_receber` (em aberto, sem período): dos afiliados do filtro
  (Ativos e Inativos).

---

## 2. Rotas alteradas

### 2.1 `GET /afiliados` — `afiliados.listar`

Query nova:

| Parâmetro | Valores | Efeito |
|---|---|---|
| `grupo` | `aprovados` \| `analise` | `aprovados` = Ativo + Inativo; `analise` = Em análise + Recusado (ordem: cadastro mais antigo primeiro). Combina com `situacao` |
| `de`, `ate` | `Y-m-d` | sem datas, **do dia 1 do mês até hoje** (antes: hoje) |

Com `grupo=analise`, quem não é Admin recebe a lista vazia (os cadastros sem gerente estão fora da
rede deles).

Campos novos no item (`AfiliadosResource`):

```json
{
  "entradas": "4200.00",
  "rede_social": "Instagram",
  "link_perfil": "https://instagram.com/maria",
  "quantidade_seguidores": 12000,
  "sobre": "Trabalho com conteúdo de futebol...",
  "cadastrado_por": { "id": 3, "nome": "Gerente A" },
  "decidido_por": { "id": 1, "nome": "Admin" },
  "comissao": null
}
```

- `entradas`: soma das bases dos lançamentos em aberto (só na listagem).
- `comissao`: `null` quando o afiliado ainda não tem regra (Em análise ou Recusado sem regra).
- `cadastrado_por` / `decidido_por`: `null` quando não houver.
- `GET /afiliados/excluidos` devolve os mesmos campos, mais `deleted_at`.

### 2.2 `GET /afiliados/{afiliado}` — `afiliados.consultar`

Mesmos campos novos do §2.1 (sem `entradas` e `totais_periodo`).

### 2.3 `POST /afiliados` — `afiliados.cadastrar`

Corpo da spec 012 + campos novos. Mudanças:

| Campo | Regra |
|---|---|
| `tipo_ganho` | **obrigatório** (antes: só valia com `afiliados.editar_comissao`) — `Saldo líquido` \| `Qualquer depósito` \| `Primeiro depósito` |
| `percentual` | **obrigatório**, 0 a 100, até 2 casas |
| `rede_social` | obrigatório, enum `RedeSocialAfiliado` |
| `link_perfil` | obrigatório, `http://` ou `https://`, até 255 |
| `quantidade_seguidores` | obrigatório, inteiro de 0 a 999.999.999 |
| `sobre` | opcional, até 1.000 caracteres |

Mensagens: "O tipo de ganho é obrigatório.", "O percentual é obrigatório.", "A rede social é
obrigatória.", "A rede social informada é inválida.", "O link do perfil é obrigatório.", "O link do
perfil deve começar com http:// ou https://.", "O link do perfil deve ter no máximo 255
caracteres.", "A quantidade de seguidores é obrigatória.", "A quantidade de seguidores deve ser um
número inteiro.", "A quantidade de seguidores deve estar entre 0 e 999.999.999.", "O texto sobre
você deve ter no máximo 1.000 caracteres.".

### 2.4 `PATCH /afiliados/{afiliado}` — `afiliados.editar`

Aceita `rede_social`, `link_perfil`, `quantidade_seguidores` e `sobre` (`sometimes`). Os três
primeiros são `required` quando o afiliado já tem valor (não voltam a vazio).

### 2.5 `POST /afiliados/{afiliado}/aprovar` — `afiliados.alterar_situacao` + **só Admin**

Corpo: `{ "usuarios_id": 7, "tipo_ganho": "Saldo líquido", "percentual": 20 }`, os três
**obrigatórios** (antes: opcionais). Quem não é Admin: `404 "Afiliado não encontrado."` (antes:
Admin e Supervisor). Afiliado com regra: a vigente é encerrada e a nova aberta.

### 2.6 `POST /afiliados/{afiliado}/recusar` — `afiliados.alterar_situacao` + **só Admin**

Sem mudança no corpo. Quem não é Admin: `404 "Afiliado não encontrado."`.

### 2.7 `DELETE /afiliados/{afiliado}` — `afiliados.excluir` (Admin/Supervisor)

Corpo novo: `{ "password": "senha do usuário do painel" }`.

- Sem senha: `422` no campo `password` ("Confirme com a sua senha.").
- Senha errada: `422` no campo `password` ("Senha incorreta."), nada muda.
- `204` como antes.

### 2.8 `GET /afiliados/{afiliado}/lancamentos` — `afiliados.consultar`

`meta` ganha `soma` (valor dos lançamentos do filtro, todas as páginas): `"soma": "85.00"`.

### 2.9 Área do afiliado

| Rota | Mudança |
|---|---|
| `POST /area-afiliado/cadastro` | aceita e exige `rede_social`, `link_perfil`, `quantidade_seguidores`; `sobre` opcional; não cria regra de ganho (antes: a padrão da configuração) |
| `GET /area-afiliado/meus-dados` | devolve os quatro campos |
| `PATCH /area-afiliado/meus-dados` | aceita os quatro campos (mesmas regras do §2.4) |

---

## 3. Rotas usadas sem mudança

| Rota | Uso |
|---|---|
| `GET /usuarios?funcao=Gerente&por_pagina=100` (`usuarios.listar`) | lista de gerentes do filtro, do cadastro, da edição e da aprovação; já limitada à sub-hierarquia de quem consulta (R-15) |

## 3a. Rotas removidas

| Rota | Motivo |
|---|---|
| `GET /configuracoes/afiliados` | regra padrão do cadastro público saiu (spec 012, FR-015) |
| `PUT /configuracoes/afiliados` | idem |

---

## 4. Postman

A coleção `docs/postman/wssports_api.postman_collection.json` é regenerada (substituída) com a
rota nova, as alteradas e sem as removidas (constituição).
