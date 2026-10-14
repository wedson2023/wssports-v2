# Contrato HTTP: Criar Aposta

Mudanças sobre o contrato da spec 004 ([../../004-apostas/contracts/api.md](../../004-apostas/contracts/api.md)).
O que não aparece aqui continua igual.

## Corpo do pedido de aposta (todas as rotas de criação e a validação do código)

Campo novo `blocos`; `palpites` continua igual.

```json
{
  "chave_idempotencia": "1b4e28ba-2fa1-11d2-883f-0016d3cca427",
  "nome": "João",
  "valor": "10.00",
  "aceitar_alteracoes": "Nenhuma",
  "palpites": [
    { "confrontos_id": 101, "codigo_cotacao": "odd1", "cotacao_vista": "1.80" },
    { "confrontos_id": 101, "codigo_cotacao": "odd116", "cotacao_vista": "2.10" },
    { "confrontos_id": 101, "codigo_cotacao": "odd4", "cotacao_vista": "1.50" },
    { "confrontos_id": 202, "codigo_cotacao": "odd2", "cotacao_vista": "3.00" }
  ],
  "blocos": [
    { "confrontos_id": 101, "cotacao_vista": "4.81" }
  ]
}
```

| Campo | Regra |
|---|---|
| `blocos` | opcional; lista de até 25 itens |
| `blocos.*.confrontos_id` | inteiro, sem repetição; precisa ter 2 ou mais palpites com o mesmo `confrontos_id` |
| `blocos.*.cotacao_vista` | numérico, ≥ 1,00, até 2 casas |

Mensagens (422):

- jogo repetido sem item em `blocos`: "Informe a cotação do Criar Aposta do jogo CASA x FORA.";
- item em `blocos` sem palpites repetidos: "O Criar Aposta precisa de palpites do mesmo jogo.";
- Criar Aposta desligado para o público: "O Criar Aposta não está habilitado.";
- jogo do ao vivo repetido: "O Criar Aposta não está disponível para jogos ao vivo.";
- fora do mínimo: "O Criar Aposta do jogo CASA x FORA precisa de pelo menos N palpites.";
- acima do máximo: "O Criar Aposta aceita no máximo N palpites.";
- mesmo grupo ou par redundante: "Não é possível combinar dois palpites do mesmo mercado no Criar Aposta.".

As regras de quantidade da aposta (`quantidade_minima_opcoes`/`quantidade_maxima_opcoes`, "Só é
permitido apostar entre X e Y jogos."), a comissão, o limite simples/duplo e o acréscimo contam o
bloco como **um jogo**.

## 409 — cotação alterada

Item novo em `alteracoes` para o bloco (R-09):

```json
{
  "message": "Houve alteração nas cotações. Confira o novo prêmio e confirme para continuar.",
  "alteracoes": [
    { "indice": 1, "confrontos_id": 101, "codigo_cotacao": "odd116", "cotacao_vista": "2.10", "cotacao_atual": "2.00" },
    { "bloco": true, "confrontos_id": 101, "cotacao_vista": "4.81", "cotacao_atual": "4.59" }
  ],
  "cotacao_total": "13.77",
  "premio": "137.70",
  "valor_acrescido": "0.00",
  "total_a_pagar": "137.70"
}
```

## Comprovante (`ComprovanteApostaResource`)

Campos novos:

```json
{
  "quantidade_jogos": 2,
  "blocos": [
    { "id": 7, "confrontos_id": 101, "cotacao": "4.81", "situacao": "Ativo" }
  ],
  "palpites": [
    { "id": 1, "apostas_blocos_id": 7, "situacao": "Ativo", "codigo_cotacao": "odd1", "mercado": "Casa", "cotacao": "1.80", "...": "..." },
    { "id": 4, "apostas_blocos_id": null, "situacao": "Ativo", "codigo_cotacao": "odd2", "mercado": "Empate", "cotacao": "3.00", "...": "..." }
  ]
}
```

- `quantidade_jogos`: quantidade de pernas (palpites simples + blocos), usada em "Qtde. jogos".
- `blocos`: lista vazia quando a aposta não tem Criar Aposta; o desconto **não** é exibido (FR-025).
- A simulação do código (`GET /api/apostas/pendentes/{codigo}`) acrescenta `blocos` como
  `[{ "confrontos_id": 101, "cotacao": "4.81" }]`, já com o desconto do vendedor (ainda não gravados,
  por isso sem `id`); os palpites de cada bloco são os disponíveis com o mesmo `confrontos_id`. Bloco
  fora das regras do vendedor vem com os palpites `disponivel: false` e o motivo.

## Edição (`POST /api/apostas/{codigo}/palpites/{palpite}/cancelar` e `.../restaurar`)

Mesmas rotas e respostas; o efeito depende de `liquidacao_criar_aposta` gravada na aposta (R-10).
Com "Anular bloco", cancelar o palpite de um bloco que é a única perna da aposta é permitido
(cotação total 1,00).

## Rotas novas

### `GET /api/configuracoes/criar-aposta` e `PUT /api/configuracoes/criar-aposta`

Permissão `configuracoes.editar` (mesmo padrão de `configuracoes/pagamentos`). `PUT` aceita
qualquer subconjunto dos campos (regras em [data-model.md](../data-model.md#4-validações-das-configurações-fr-014)).

```json
{
  "data": {
    "criar_aposta_habilitado": true,
    "desconto_criar_aposta": "15.00",
    "quantidade_minima_criar_aposta": 2,
    "quantidade_maxima_criar_aposta": 10,
    "liquidacao_criar_aposta": "Recalcular"
  }
}
```

## Rotas existentes com campos novos

### `GET /api/usuarios-configuracoes` e `PATCH /api/usuarios-configuracoes`

Campos novos do vendedor: `criar_aposta_habilitado`, `desconto_criar_aposta`,
`quantidade_minima_criar_aposta`, `quantidade_maxima_criar_aposta` (mesmas regras; fluxo de
hierarquia da spec 003 sem mudança).

## Props da página inicial (`PaginaInicialController`, Inertia)

`configuracoes` ganha `criar_aposta`, do público de quem vê:

```json
{
  "criar_aposta": {
    "habilitado": true,
    "desconto": "15.00",
    "quantidade_minima": 2,
    "quantidade_maxima": 10,
    "grupos": { "odd1": "vencedor", "odd2": "vencedor", "odd116": "gols_mais_menos" },
    "pares_redundantes": [["vencedor", "dupla_chance"], ["vencedor", "empate_anula_aposta"]]
  }
}
```

- `grupos`: todos os códigos de cotação de `MercadosCotacao`; jogador usa `jogador:<nome>` na tela.

## Postman

A coleção `docs/postman/wssports_api.postman_collection.json` é regenerada com as 2 rotas novas e o
campo `blocos` nos exemplos de aposta.
