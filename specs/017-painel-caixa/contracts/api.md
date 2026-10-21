# Contrato da API: Painel — Caixa

Base `/api`, JWT (`Authorization: Bearer`), dentro do grupo `auth:api` + `garantir_acesso`. Respostas
JSON; erros no padrão do projeto (`{message, errors}` com 422; `{message}` com 403/404/409).
Permissões checadas com `Funcao::usuario_pode`. Valores em reais como string com duas casas
(`"123.45"`). Paginação: `page`, `por_pagina` (padrão 20, máximo 100), resposta com `data`, `links`
e `meta`.

**Parâmetros de período** (todas as consultas): `de` e `ate` (`AAAA-MM-DD`, obrigatórios, `ate` ≥
`de`, no máximo 366 dias, não futuro), em Brasília. **Tipo**: `tipo=todas|pre_jogo|ao_vivo`
(padrão `todas`).

**Alcance**: `usuarios_id` (foco) é opcional; vazio = o próprio usuário (Admin = banca). Fora da rede
→ 403 "Você não tem permissão para esta ação.". Rotas com `{usuario}` exigem a função indicada no
caminho (vendedor ou gerente) → 404 "Usuário não encontrado." se não for.

## 1. Caixa da rede (`CaixaController`)

### 1.1 `GET /caixa/resumo?de&ate&tipo&usuarios_id`
`caixa.ver`. Cartões do foco e a trilha.
```json
{
  "foco": { "id": 5, "nome": "Gerente B", "funcao": "Gerente" },
  "trilha": [{ "id": null, "nome": "Banca" }, { "id": 2, "nome": "Supervisor A" }, { "id": 5, "nome": "Gerente B" }],
  "periodo": { "de": "2026-10-21", "ate": "2026-10-21" },
  "totais": {
    "apostas": 12, "abertas": 3, "premiadas": 2, "perdidas": 7,
    "entradas": "500.00", "em_aberto": "80.00", "valor_premiadas": "60.00", "valor_perdidas": "360.00",
    "saidas": "240.00", "comissao_vendedor": "50.00", "comissao_gerente": "21.00",
    "lancamentos": "100.00", "retiradas": "40.00", "liquido": "189.00",
    "saldo_caixa": "310.00", "recebido_periodo": "0.00",
    "percentual_comissao_gerente": "10.00",
    "limites": null
  }
}
```
`comissao_gerente` = null para o Vendedor; `lancamentos`, `retiradas`, `saldo_caixa` = null fora do
tipo `todas`; `percentual_comissao_gerente` só quando o foco é um Gerente; `limites`
(`{geral, duplo, simples}`) só quando o foco é um Vendedor.

### 1.2 `GET /caixa/usuarios?de&ate&tipo&usuarios_id&busca&ordenar&direcao&page&por_pagina`
`caixa.ver`. Lista do nível abaixo do foco (Admin → supervisores; Supervisor → gerentes; Gerente →
vendedores). Para o Vendedor: 403. `ordenar=nome|entradas|liquido|saldo_caixa` (padrão `nome`),
`direcao=asc|desc`. Busca por nome ou login. Inclui inativos e excluídos com movimento ou saldo.
```json
{
  "funcao": "Vendedor",
  "data": [{
    "id": 9, "nome": "Vendedor C", "login": "vendc", "situacao": "ativo|inativo|excluido",
    "apostas": 4, "entradas": "200.00", "em_aberto": "...", "valor_premiadas": "...", "valor_perdidas": "...",
    "saidas": "...", "comissao_vendedor": "...", "comissao_gerente": null,
    "lancamentos": "...", "retiradas": "...", "liquido": "...", "saldo_caixa": "...",
    "recebido_periodo": null, "percentual_comissao_gerente": null,
    "limites": { "geral": "4800.00", "duplo": "5000.00", "simples": "4900.00" },
    "acoes": ["lancar", "prestar_contas", "transacoes", "ajustar_limite", "grafico", "exportar"]
  }],
  "links": {}, "meta": {}
}
```
`acoes` = o que o solicitante pode fazer na linha (permissão + alcance), para o menu não oferecer o
que o servidor recusaria. No gerente: `prestar_contas`, `comissao_gerente`, `exportar`.

### 1.3 `GET /caixa/vendedores/{usuario}/grafico?de&ate`
`caixa.ver` + alcance. `{ "faixas": [{ "palpites": "1", "apostas": 10 }, ..., { "palpites": "10", "apostas": 0 }, { "palpites": "Mais de 10", "apostas": 2 }] }`.

### 1.4 `POST /caixa/vendedores/{usuario}/limites`
`usuarios_configuracoes.editar` + alcance. Corpo: `{ "geral": "500.00", "duplo": "0.00", "simples": "0.00" }`
(valores a somar ou subtrair, com até duas casas, ao menos um diferente de zero; 422 se algum limite ficaria negativo, sem gravar nenhum). Resposta: `{ "limites": { "geral", "duplo", "simples" } }`.

### 1.5 `PUT /caixa/gerentes/{usuario}/comissao`
`caixa.editar_comissao_gerente` + alcance. Corpo: `{ "percentual": "10.00" }` (0 a 100, duas casas).
Resposta: `{ "percentual_comissao_gerente": "10.00" }`.

## 2. Lançamentos (`CaixaLancamentosController`)

### 2.1 `GET /caixa/vendedores/{usuario}/transacoes?tipo=todos|suprimento|retirada|prestacao&de&ate&page&por_pagina`
`caixa.ver` + alcance (o Vendedor só o próprio). Período opcional aqui. Itens do mais novo ao mais
antigo (por data/hora):
```json
{ "data": [
  { "chave": "lancamento-12", "tipo": "Suprimento", "valor": "50.00", "ocorrido_em": "ISO", "observacao": null,
    "registrado_por": { "id": 5, "nome": "Gerente B" }, "excluido": false, "excluido_por": null,
    "prestado": false, "pode_excluir": true },
  { "chave": "prestacao-3", "tipo": "Prestação de contas", "valor_apurado": "100.00", "valor_recebido": "80.00",
    "diferenca": "20.00", "periodo_inicio": "ISO|null", "periodo_fim": "ISO", "em_lote": false,
    "registrado_por": {}, "desfeita": false, "desfeita_por": null, "motivo_desfazer": null,
    "pode_desfazer": true, "id": 3 }
], "links": {}, "meta": {} }
```

### 2.2 `POST /caixa/vendedores/{usuario}/lancamentos`
`caixa.lancar` + alcance. Corpo: `{ "tipo": "Suprimento|Retirada", "valor": "50.00", "ocorrido_em": "ISO", "observacao": "" }`.
422: valor ≤ 0 ou com mais de duas casas; data futura; "Data anterior à última prestação de contas."; "Vendedor inativo ou excluído não recebe suprimento." (FR-014a). Vendedores inativos e excluídos são aceitos em todas as rotas `{usuario}` do caixa (retirada, prestação, desfazer, transações).
201 com o lançamento.

### 2.3 `DELETE /caixa/lancamentos/{lancamento}`
`caixa.excluir_lancamento` + alcance sobre o vendedor. 422 "Este lançamento já entrou numa prestação
de contas e não pode ser excluído.". 204.

## 3. Prestação de contas (`CaixaPrestacoesController`)

### 3.1 `GET /caixa/vendedores/{usuario}/prestacao?ate=ISO`
`caixa.prestar_contas` + alcance. Resumo (sem gravar), `ate` padrão agora:
```json
{ "periodo_inicio": "ISO|null", "periodo_fim": "ISO", "saldo_anterior": "0.00", "entradas": "100.00",
  "comissao": "10.00", "saidas": "30.00", "suprimentos": "0.00", "retiradas": "0.00", "ajustes": "0.00",
  "valor_apurado": "60.00", "apostas_em_aberto": { "quantidade": 2, "valor": "20.00" }, "tem_movimento": true }
```

### 3.2 `POST /caixa/vendedores/{usuario}/prestacoes`
`caixa.prestar_contas` + alcance. Corpo: `{ "ate": "ISO", "valor_recebido": "60.00", "observacao": "", "senha": "..." }`.
422: "Senha incorreta."; data futura ou não posterior à última prestação; "Nenhum movimento a prestar
no período.". 409: "O caixa deste vendedor mudou; confira e tente de novo." 201 com a prestação.

### 3.3 `GET /caixa/gerentes/{usuario}/prestacao?ate=ISO`
`caixa.prestar_contas` + alcance. `{ "vendedores": [{ "id", "nome", "valor_apurado", "tem_movimento" }], "total": "...", "pulados": 1 }`.

### 3.4 `POST /caixa/gerentes/{usuario}/prestacoes`
`caixa.prestar_contas` + alcance. Corpo: `{ "ate": "ISO", "observacao": "", "senha": "..." }`. Valor
recebido = apurado de cada vendedor. Tudo ou nada. 201: `{ "lote": "uuid", "prestacoes": 3, "pulados": 1, "total": "..." }`.

### 3.5 `POST /caixa/prestacoes/{prestacao}/desfazer`
`caixa.prestar_contas` + quem fez ou superior dele + alcance sobre o vendedor. Corpo:
`{ "motivo": "...", "senha": "..." }`. 422: "Só a última prestação de contas do vendedor pode ser
desfeita."; "Senha incorreta."; motivo obrigatório (até 500). 200 com a prestação desfeita.

## 4. Caixa clientes (`CaixaClientesController`)

Só Admin e Supervisor com `caixa.ver_clientes` (o Gerente recebe 403 mesmo com a permissão).

### 4.1 `GET /caixa/clientes/resumo?de&ate`
```json
{ "esportes": { "entradas", "em_aberto", "premiadas", "perdidas", "saidas", "encerramentos", "saldo_liquido" },
  "cassino": { "entradas", "saidas", "resultado", "comissao_provedores" },
  "bonus": { "concedidos", "convertidos" },
  "depositos": "...", "saques": "...", "resultado_site": "..." }
```

### 4.2 `GET /caixa/clientes?de&ate&busca&ordenar=nome|entradas|resultado&direcao&page&por_pagina`
Clientes com movimento no período: `data: [{ id, nome, login, apostas, entradas, saidas,
encerramentos, resultado_cassino, bonus_convertidos, depositos, saques, liquido }]`.

## 5. Exportação (`CaixaExportacaoController`)

### 5.1 `GET /caixa/exportar?escopo=rede|clientes&de&ate&tipo&usuarios_id`
`caixa.exportar` (+ `caixa.ver_clientes` no escopo `clientes`) + alcance. Devolve o `.xlsx`
(`Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`,
`Content-Disposition: attachment; filename="caixa-<nivel>-<de>-<ate>.xlsx"`). Escopo `rede`: o foco
e uma linha por usuário do nível abaixo (no vendedor, só o vendedor). Até 5.000 linhas.

## Rotas web

### `GET /painel/caixa` → `PainelController::caixa` → página Inertia `PanelCash`
Query string: `aba=rede|clientes`, `tipo`, `de`, `ate`, `usuarios_id`. Quem pode ver é conferido na
API (como as demais páginas do painel).

## Dashboard (alterada, sem rota nova)

`GET /dashboard/apurado`: `lancamentos`, `retiradas` e `comissao_gerente` passam a ser reais (do
dia, pela `ApuracaoCaixa`) e `provisorios` passa a ser `[]`. Formato da resposta inalterado.
