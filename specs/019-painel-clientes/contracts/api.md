# Contrato da API: Painel — Clientes

Base `/api`, JWT (`Authorization: Bearer`), grupo `auth:api` + `garantir_acesso`. Erros no padrão do
projeto (`{message, errors}` com 422; `{message}` com 403/404). Permissões por `Funcao::usuario_pode`
(`GarantirPermissaoCliente`). Valores em reais como string com duas casas. Paginação: `page`,
`por_pagina` (padrão 20, máximo 100), resposta com `data`, `links` e `meta`.

**Alcance (FR-003)**: toda rota abaixo que lista clientes, saques ou movimentos considera só os
clientes da rede do usuário (`AlcanceClientes`); cliente ou saque fora da rede responde **404
"Cliente não encontrado."** (ou "Saque não encontrado."). Admin vê todos.

Rotas fixas (`clientes/excluidos`, `clientes/ranking`, `clientes/exportacao`,
`clientes/notificacoes/*`) vêm antes de `clientes/{cliente}`.

## 1. Clientes — alterações em rotas existentes (spec 002)

### 1.1 `GET /clientes` e `GET /clientes/excluidos` (alterados)
`clientes.listar` / `clientes.restaurar`. Filtros atuais mantidos; novos:

| Parâmetro | Regra |
|---|---|
| `deposito` | `ja_depositou` \| `nunca_depositou` |
| `pix_em_aberto` | `1`: último depósito Pix do cliente em `Pendente` ou `Expirado` |
| `modalidade` | `esportes` \| `cassino` (exige `periodo_de`/`periodo_ate`) |
| `aniversariantes` | `1` (exige `periodo_de`/`periodo_ate`) |
| `periodo_de`, `periodo_ate` | `AAAA-MM-DD`, `ate` ≥ `de`, até 366 dias, Brasília |
| `afiliados_id` | conferido no alcance (fora → lista vazia) |

Cada item ganha `saldo_liquido` (string) e, em `excluidos`, `deleted_at`. Resposta igual à atual no
mais. Mensagens: "Informe o período do filtro.", "O período do filtro pode ter no máximo 366 dias."

### 1.2 `PATCH /clientes/{cliente}` (alterado)
`clientes.editar`. `afiliados_id`: para Supervisor e Gerente, obrigatório e da rede ("Escolha um
afiliado da sua rede."); Admin pode `null`.

### 1.3 `POST /clientes/{cliente}/transacoes` (alterado — ajuste de saldo)
`clientes.movimentar_saldo`. Corpo atual + **`senha`** obrigatória.
```json
{ "carteira": "Saldo", "tipo": "Crédito", "valor": "10.00", "observacao": "Pagou por fora", "senha": "******" }
```
422 `{"errors": {"senha": ["Senha incorreta."]}}` sem gravar nada.

### 1.4 Demais rotas de cliente (alcance novo, sem outra mudança)
`GET /clientes/{cliente}`, `PATCH /clientes/{cliente}/situacao`, `DELETE /clientes/{cliente}`,
`POST /clientes/{cliente}/restaurar`, `GET /clientes/{cliente}/transacoes`,
`GET|PUT /clientes/{cliente}/configuracoes`, `/clientes/{cliente}/meios-pagamento*`,
`POST /clientes/{cliente}/acesso-assistido`.

## 2. Antecipar pendentes (novas)

### 2.1 `GET /clientes/{cliente}/pendentes`
`clientes.antecipar_pendentes` (Admin e Supervisor).
```json
{ "premios": { "quantidade": 2, "valor": "350.00" }, "encerramentos": { "quantidade": 1, "valor": "42.10" }, "total": "392.10" }
```

### 2.2 `POST /clientes/{cliente}/antecipar`
`clientes.antecipar_pendentes`. Corpo `{ "senha": "******" }`.
```json
{ "creditado": "392.10", "premios": { "quantidade": 2, "valor": "350.00" }, "encerramentos": { "quantidade": 1, "valor": "42.10" }, "saldo": "512.35" }
```
422 "Senha incorreta." · 422 "Nenhum prêmio ou encerramento pendente."

## 3. Saques (alterados / nova)

### 3.1 `GET /saques` (alterada)
`saques.gerenciar`. `situacao` (`Pendente` padrão na tela, `Processando`, `Pago`, `Recusado`),
**`busca`** (nome ou ID do cliente, até 60), **`de`/`ate`** (data do pedido, até 366 dias),
`pagina`, `por_pagina` (padrão 30). Item: o atual + `cliente.saldo`; `cliente.telefone` mascarado sem
`clientes.ver_dados_completos`.

### 3.2 `GET /saques/resumo` (nova)
`saques.gerenciar`. `de`/`ate` opcionais (só para `pagos`); `busca` opcional.
```json
{ "pendentes": { "quantidade": 4, "valor": "820.00" }, "processando": { "quantidade": 1, "valor": "100.00" }, "pagos": { "quantidade": 12, "valor": "3200.00" } }
```
`pagos` = `null` sem período.

### 3.3 `POST /saques/{saque}/pagar` e `/recusar`
Sem mudança de corpo; saque de cliente fora da rede → 404 "Saque não encontrado."

## 4. Ranking (nova)

### 4.1 `GET /clientes/ranking`
`clientes.ver_ranking`.

| Parâmetro | Regra |
|---|---|
| `periodo` | `24h` \| `7d` \| `30d` (padrão) \| `90d` \| `personalizado` |
| `de`, `ate` | obrigatórios com `personalizado`; até 366 dias |
| `criterio` | `depositos` (padrão) \| `valor_apostado` \| `resultado` \| `saques` |
| `page`, `por_pagina` | padrão 20, máximo 100 |

```json
{
  "data": [{
    "posicao": 1, "id": 3037, "nome": "Mauro Lima", "saldo": "0.75", "saldo_liquido": "25.00", "ativo": true,
    "depositos": { "quantidade": 3, "valor": "150.00" },
    "valor_apostado": null, "resultado": null, "saques": null
  }],
  "meta": { "criterio": "depositos", "inicio": "2026-09-22T14:00:00Z", "fim": "2026-10-22T14:00:00Z", "...": "..." }
}
```
Por critério, só o bloco dele vem preenchido: `valor_apostado: {esportes, cassino, total}`,
`resultado: "−120.40"`, `saques: {quantidade, valor}`.

## 5. Exportação (nova)

### 5.1 `GET /clientes/exportacao?aba={cadastros|saques|ranking|lixeira}&{filtros da aba}`
`clientes.exportar` (Admin e Supervisor) + a permissão da aba. Resposta `.xlsx`
(`clientes-{aba}-{AAAA-MM-DD}.xlsx`). 422 "O filtro tem N registros; o máximo por planilha é 50.000.
Use filtros para reduzir."

## 6. Notificações manuais (novas)

### 6.1 `GET /clientes/notificacoes/limite`
`clientes.enviar_notificacao`. `{ "limite": 3, "enviados_hoje": 1, "restantes": 2, "pode_alterar": true }`
(`pode_alterar` só para o Admin).

### 6.2 `PUT /clientes/notificacoes/limite`
Só Admin (`clientes.enviar_notificacao` + função Admin). `{ "limite": 5 }` (1 a 50).

### 6.3 `POST /clientes/notificacoes/alcance`
`clientes.enviar_notificacao`. `{ "publico": "Todos|Por ID|Sem interagir", "clientes_ids": [1,2], "dias": 10 }`
→ `{ "alcancados": 1234 }`.

### 6.4 `POST /clientes/notificacoes`
`clientes.enviar_notificacao`. Corpo = 6.3 + `titulo` (≤ 50) e `mensagem` (≤ 150).
→ 201 `{ "id": 7, "alcancados": 1234, "restantes": 1 }` (envio em segundo plano).
422 "Nenhum cliente inscrito neste público." · 422 "Limite de envios de hoje atingido."

## 7. Afiliados — alterações (spec 012)

- `POST /afiliados`: `usuarios_id` obrigatório para Admin e Supervisor; gerente da rede do
  Supervisor ("O gerente escolhido não é da sua rede.").
- `PATCH /afiliados/{afiliado}`: `usuarios_id` não pode ser `null` ("O gerente responsável é
  obrigatório.").
- `POST /afiliados/{afiliado}/aprovar`: `usuarios_id` obrigatório se o afiliado não tem gerente.
- `GET /afiliados` e `/afiliados/excluidos`: filtro `sem_gerente=1`; item com `sem_gerente`;
  Supervisor só vê os afiliados dos gerentes da rede (R-04, confirmado em 2026-10-22).

## 8. Caixa clientes e Apostas — alcance do Supervisor (FR-003c)

- `GET /caixa/clientes/resumo`, `GET /caixa/clientes` e `GET /caixa/exportar` (parte dos clientes): somas e linhas
  só dos clientes da rede do Supervisor (Admin, banca toda). Sem mudança de formato.
- `GET /apostas*` (lista, resumo, exportação, premiados, bilhete, cancelar, restaurar, palpites):
  apostas do site só dos clientes da rede do Supervisor; novo filtro **`clientes_id`** (Admin e
  Supervisor; fora do alcance → 403 "Você não tem permissão para esta ação."). Aposta de cliente fora
  da rede → 404 "Aposta não encontrada.".

## 9. Página

`GET /painel/clientes` (web, Inertia `PanelClients`).
