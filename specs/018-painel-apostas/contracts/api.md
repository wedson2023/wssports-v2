# Contrato da API: Painel — Apostas

Base `/api`, JWT (`Authorization: Bearer`), dentro do grupo `auth:api` + `garantir_acesso`. Respostas
JSON; erros no padrão do projeto (`{message, errors}` com 422; `{message}` com 403/404/409).
Permissões checadas com `Funcao::usuario_pode`. Valores em reais como string com duas casas
(`"123.45"`). Paginação: `page`, `por_pagina` (padrão 20, máximo 100), resposta com `data`, `links` e
`meta`.

**Filtros comuns** (lista, resumo, exportação; os premiados usam só `de`, `ate`, `aba` e
`usuarios_id`):

| Parâmetro | Regra |
|---|---|
| `de`, `ate` | `AAAA-MM-DD`, obrigatórios, `ate` ≥ `de`, no máximo 366 dias, não futuro; Brasília; pela data em que a aposta foi feita |
| `aba` | `todas` (padrão) \| `pre_jogo` \| `ao_vivo` \| `canceladas` |
| `busca` | até 60 caracteres; parte do nome do cliente (no período) ou, com 8 letras ou números, também o código da aposta em qualquer data |
| `todo_periodo` | `0` (padrão) \| `1`: a busca por nome ignora o período |
| `resultado` | `Aguardando` \| `Vencedor` \| `Perdedor` \| `Devolvido` \| `Encerrada` (ignorado em `canceladas`) |
| `usuarios_id` | vendedor da rede; fora do alcance → 403 "Você não tem permissão para esta ação."; ignorado para o Vendedor |
| `forma_pagamento` | `Dinheiro` \| `Saldo` \| `Promoção esportes` |
| `premio_minimo` | valor > 0, duas casas; total a pagar ≥ valor |

O registro das rotas fixas (`resumo`, `exportacao`, `premiados`, `vendedores`) vem antes de
`apostas/{codigo}`, e `{codigo}` aceita só 8 letras ou números.

## 1. Lista (`ApostasListagemController`)

### 1.1 `GET /apostas?{filtros}&page&por_pagina`
`apostas.listar`.
```json
{
  "data": [{
    "codigo": "AB3K9XQZ",
    "vendedor": { "id": 9, "nome": "Vendedor C", "situacao": "ativo|inativo|excluido" },
    "origem": "balcao|site",
    "cliente": "João",
    "valor": "10.00", "comissao": "1.00", "premio": "152.40",
    "situacao": "Ativa", "resultado": "Aguardando", "situacao_exibida": "Aguardando",
    "tipo": "Pré-jogo", "forma_pagamento": "Dinheiro",
    "jogos_restantes": 2, "jogos_total": 3,
    "criada_em": "2026-10-22T14:05:00-03:00",
    "cancelada_em": null, "cancelada_por": null,
    "acoes": ["ver", "cancelar"]
  }],
  "links": {}, "meta": {}
}
```
- `vendedor` = null e `origem = "site"` nas apostas de cliente (a tela mostra "Site").
- `premio` = total a pagar; nas `Encerrada`, o valor pago.
- `acoes`: `ver` sempre; `cancelar` quando o usuário tem `apostas.cancelar`, alcance de cancelamento
  e a aposta é `Ativa` ainda não apurada (as demais regras ficam no servidor, ao cancelar).

### 1.2 `GET /apostas/resumo?{filtros}`
`apostas.listar`. Totais de todas as apostas do filtro (não só da página).
```json
{
  "totais": {
    "apostas": 42, "entradas": "980.00", "comissao": "98.00", "saidas": "310.00",
    "abertas": 12, "em_aberto": "260.00",
    "encerramentos": "45.00"
  },
  "periodo": { "de": "2026-10-22", "ate": "2026-10-22" },
  "limite_planilha": 50000
}
```
- `encerramentos` só para Admin e Supervisor (null para os demais).
- Na aba `canceladas`: `{ "apostas": 3, "valor_cancelado": "30.00" }` e os demais campos null.

### 1.3 `GET /apostas/vendedores?busca`
`apostas.listar`; Vendedor → 403. Até 20 vendedores da rede do usuário, por nome ou login.
```json
{ "data": [{ "id": 9, "nome": "Vendedor C", "login": "vendc", "situacao": "ativo" }] }
```

## 2. Bilhete

### 2.1 `GET /apostas/{codigo}`
`apostas.listar`; fora do alcance da lista ou situação não listada → 404 "Aposta não encontrada.".
```json
{
  "data": { "...": "ComprovanteApostaResource com mostrar_comissao (comissao, percentual_comissao); cada palpite com motivo_cancelamento" },
  "painel": {
    "cancelada_em": null, "cancelada_por": null,
    "acoes": ["cancelar", "editar_palpites", "imprimir_segunda_via"]
  }
}
```
`acoes` pelo mesmo critério do servidor: `cancelar` (como em 1.1); `editar_palpites` com
`apostas.editar`, alcance de edição e aposta `Ativa` não apurada; `imprimir_segunda_via` com
`apostas.imprimir_segunda_via` e aposta `Ativa` com `resultado = Aguardando`.

### 2.2 `GET /apostas/{codigo}/segunda-via` (`ApostasSegundaViaController`)
`apostas.imprimir_segunda_via`; alcance da lista (404). Aposta que não está `Ativa` aguardando
resultado → 422 "A 2ª via só pode ser impressa em apostas aguardando resultado.". Resposta:
`{ "data": ComprovanteApostaResource }` para impressão.

### 2.3 Ações existentes (sem mudança)
- `POST /apostas/{codigo}/cancelar` (spec 004) → `{ data: ComprovanteApostaResource }`.
- `POST /apostas/{codigo}/palpites/{palpite}/cancelar` e `/restaurar` → `{ data: ComprovanteApostaResource }`.

A tela recarrega a linha, o resumo e o bilhete (`GET /apostas/{codigo}`) depois de cada ação.

## 3. Premiados (`ApostasPremiadosController`)

### 3.1 `GET /apostas/premiados?de&ate&aba&usuarios_id`
`apostas.exportar`; só Admin, Supervisor e Gerente (Vendedor → 403 "Você não tem permissão para esta
ação."). Até 23 apostas `Ativa` com `resultado = Vencedor` feitas no período, na rede do usuário,
ordenadas pelo prêmio (total a pagar) desc.
```json
{
  "data": [{ "codigo": "AB3K9XQZ", "vendedor": "Vendedor C", "cliente": "João", "valor": "10.00", "premio": "152.40", "situacao_exibida": "Vencedor" }],
  "nome_sistema": "WSSports", "url_logo": "/storage/logo.png",
  "periodo": { "de": "2026-10-22", "ate": "2026-10-22" }
}
```

## 4. Planilha (`ApostasExportacaoController`)

### 4.1 `GET /apostas/exportacao?{filtros}`
`apostas.exportar`. Arquivo `apostas-{de}-{ate}.xlsx` (download; arquivo temporário único, apagado
após o envio). Mais de 50.000 apostas no filtro → 422 "O filtro tem N apostas; o máximo por planilha
é 50.000. Reduza o período ou use filtros.".

Conteúdo: título com o nome do sistema; quadro com período, aba, filtros aplicados e totais (1.2);
colunas CÓDIGO, VENDEDOR, CLIENTE, VALOR, COMISSÃO, PRÊMIO, SITUAÇÃO, JOGOS (restantes/total),
HORÁRIO (dd/mm/aaaa hh:mm) e, na aba Canceladas, CANCELADA EM e CANCELADA POR; valores em formato de
moeda.

## 5. Rotas web

| Rota | Controller | Página |
|---|---|---|
| `GET /painel/apostas` | `PainelController@apostas` | `PanelBets` (filtros na query string) |

## 6. Erros

| Situação | Status | Mensagem |
|---|---|---|
| Sem a permissão | 403 | "Você não tem permissão para esta ação." |
| Vendedor do filtro fora da rede | 403 | "Você não tem permissão para esta ação." |
| Aposta fora do alcance ou não listada | 404 | "Aposta não encontrada." |
| Período inválido | 422 | por campo (`de`, `ate`) |
| 2ª via fora de aposta aguardando | 422 | "A 2ª via só pode ser impressa em apostas aguardando resultado." |
| Planilha acima do limite | 422 | "O filtro tem N apostas; o máximo por planilha é 50.000. Reduza o período ou use filtros." |
| Cancelar/editar recusado | 422/404 | a mensagem dos services existentes (spec 004) |
