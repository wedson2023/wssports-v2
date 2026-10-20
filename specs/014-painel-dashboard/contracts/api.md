# Contrato da API — Dashboard

Todas as rotas abaixo ficam em `routes/api.php`, no grupo `['auth:api', 'garantir_acesso']` (token
do usuário do painel, usuário ativo). Respostas em JSON; dinheiro como string com 2 casas.

Erros comuns: `401 {"message": "Não autenticado."}` · `403 {"message": "Você não tem permissão para
esta ação."}` (sem a permissão da seção ou função que não pode usá-la, FR-011/FR-012).

## 1. `GET /auth/eu`

Dados do usuário logado, para o painel e o destino do login (R-14). Sem permissão específica.

```json
{
  "data": {
    "id": 1,
    "nome": "Administrador",
    "login": "admin",
    "funcao": "Admin",
    "secoes": ["apurado", "situacao_jogos", "apostas", "combinacao_risco", "apostas_um_jogo",
               "vendedores", "jogos_mais_apostados", "opcoes_mais_apostadas", "caixa_online"]
  }
}
```

`secoes` = permissões `dashboard.ver_*` que o usuário tem **e** pode usar, sem o prefixo, na ordem
da tela.

## 2. Seções da Dashboard — `GET /dashboard/<secao>`

Parâmetro comum: `?atualizar=1` ignora o cache de 60 s (FR-042). Todas respeitam o alcance do nível
(FR-008). Cada uma exige a permissão `dashboard.ver_<secao>` com `permissao_cliente`.

Todas as respostas trazem `"gerado_em": "2026-10-20T14:03:00-03:00"` (momento do cálculo).

### 2.1 `GET /dashboard/apurado` — `dashboard.ver_apurado`

```json
{
  "data": {
    "limites": { "geral": "5000.00", "duplo": "5000.00", "simples": "5000.00" },
    "entradas": "1520.00",
    "em_aberto": "300.00",
    "premiadas": "200.00",
    "perdidas": "1020.00",
    "saidas": "950.00",
    "comissao_vendedor": "152.00",
    "depositos_online": "800.00",
    "saques_pagos": "250.00",
    "lancamentos": "0.00",
    "retiradas": "0.00",
    "comissao_gerente": "0.00",
    "saldo_liquido": "968.00",
    "provisorios": ["lancamentos", "retiradas", "comissao_gerente"]
  },
  "gerado_em": "…"
}
```

- `limites`: só para o Vendedor; `null` para os demais.
- `depositos_online` e `saques_pagos`: só Admin e Supervisor (banca toda); `null` para os demais.
  Quando presentes, já estão somados em `entradas` e `saidas`.
- `comissao_gerente`: `null` para o Vendedor.
- `provisorios`: chaves com dado fake (selo "em breve"); o Vendedor recebe só `lancamentos` e
  `retiradas`.

### 2.2 `GET /dashboard/situacao-jogos` — `dashboard.ver_situacao_jogos`

```json
{ "data": { "total": 42, "aguardando": 20, "encerrados": 18, "adiados": 1, "cancelados": 3 }, "gerado_em": "…" }
```

Jogos distintos; Bloqueado conta em `aguardando`.

### 2.3 `GET /dashboard/apostas` — `dashboard.ver_apostas`

```json
{
  "data": {
    "total":      { "quantidade": 120, "valor": "1520.00" },
    "abertas":    { "quantidade": 30,  "valor": "300.00" },
    "premiadas":  { "quantidade": 10,  "valor": "200.00" },
    "perdidas":   { "quantidade": 80,  "valor": "1020.00" },
    "canceladas": { "quantidade": 4,   "valor": "40.00" }
  },
  "gerado_em": "…"
}
```

### 2.4 `GET /dashboard/combinacoes-risco` — `dashboard.ver_combinacao_risco`

Até 100 linhas, maior prêmio primeiro.

```json
{
  "data": [
    {
      "chave": "9f1c…(sha1)",
      "jogos": ["Flamengo x Vasco", "Bahia x Sport"],
      "opcoes": ["Casa", "Ambas marcam"],
      "quantidade_bilhetes": 3,
      "codigos": ["A1B2C3D4", "E5F6G7H8", "J9K8L7M6"],
      "valor_apostado": "30.00",
      "premio": "2400.00",
      "primeiro_jogo_em": "2026-10-20T19:00:00-03:00"
    }
  ],
  "gerado_em": "…"
}
```

### 2.5 `GET /dashboard/apostas-um-jogo` — `dashboard.ver_apostas_um_jogo`

Até 50, maior prêmio primeiro.

```json
{ "data": [ { "codigo": "A1B2C3D4", "apostador": "João", "valor": "10.00", "premio": "850.00" } ], "gerado_em": "…" }
```

### 2.6 `GET /dashboard/vendedores` — `dashboard.ver_vendedores`

```json
{ "data": [ { "id": 4, "nome": "Agustina", "quantidade": 35, "entradas": "420.00" } ], "gerado_em": "…" }
```

### 2.7 `GET /dashboard/jogos-mais-apostados` — `dashboard.ver_jogos_mais_apostados`

```json
{ "data": [ { "confrontos_id": 77, "confronto": "Flamengo x Vasco", "quantidade": 58, "entradas": "610.00" } ], "gerado_em": "…" }
```

`quantidade` = apostas distintas com o jogo; `entradas` = soma do valor dessas apostas.

### 2.8 `GET /dashboard/opcoes-mais-apostadas` — `dashboard.ver_opcoes_mais_apostadas`

```json
{ "data": [ { "codigo_cotacao": "odd1", "opcao": "Casa", "quantidade": 74 } ], "gerado_em": "…" }
```

### 2.9 `GET /dashboard/caixa-online` — `dashboard.ver_caixa_online`

Banca toda (sem rede).

```json
{
  "data": {
    "entradas": "900.00",
    "em_aberto": "150.00",
    "premiadas": "120.00",
    "perdidas": "630.00",
    "saidas": "500.00",
    "encerramentos": "80.00",
    "depositos": "800.00",
    "saques_pagos": "250.00",
    "saldo_liquido": "320.00"
  },
  "gerado_em": "…"
}
```

`saldo_liquido` = entradas − saidas − encerramentos (Clarifications).

## 3. `GET /configuracoes/avisos-whatsapp` e `PUT /configuracoes/avisos-whatsapp`

Permissão `configuracoes.editar` (Admin e Supervisor), padrão do `ConfiguracoesCassinoController`.

**PUT** (campos opcionais):

```json
{ "aviso_combinacao_whatsapp": true, "telefone_whatsapp_admin": "5582999999999", "chave_callmebot": "123456" }
```

**Resposta** (GET e PUT):

```json
{
  "data": {
    "aviso_combinacao_whatsapp": true,
    "telefone_whatsapp_admin": "5582999999999",
    "chave_callmebot_definida": true,
    "chave_callmebot_final": "3456"
  }
}
```

422 de exemplo: `{"message": "Informe o telefone e a chave do CallMeBot para ligar o aviso.", "errors": {...}}`.

## 4. Comando agendado

`php artisan apostas:avisar_combinacoes_risco` — a cada minuto (`withoutOverlapping(2)`), banca
toda, sem cache (R-11). Mensagem (texto puro):

```text
⚠️ Combinação de risco nova (2)

1) Flamengo x Vasco: Casa | Bahia x Sport: Ambas marcam
   3 bilhetes · apostado R$ 30,00 · prêmio R$ 2.400,00

2) …
```

## 5. Postman

A coleção `docs/postman/wssports_api.postman_collection.json` é regenerada com as 12 rotas novas
(Constituição).
