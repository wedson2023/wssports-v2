# Contrato da API: Painel — Cotações

Base `/api`, JWT (`Authorization: Bearer`), respostas JSON, erros no padrão do projeto
(`{message, errors}` com 422; `{message}` com 403/404). "Permissão de cotação" = qualquer uma de
`porcentagens_vendedores.editar`, `porcentagens_clientes.editar`, `porcentagens_campeonatos.editar`,
`porcentagens_confrontos.editar`, `confrontos_teto_cotacoes.editar`, checada com
`Funcao::usuario_pode`. Paginação: `page`, `por_pagina` (padrão 20, máximo 100), resposta com
`data`, `links` e `meta`.

## Rotas novas (`CotacoesPainelController`)

### 1. `GET /cotacoes/nomes`
Permissão de cotação. Resposta: `{ "nomes": { "odd1": "Casa", ..., "jogador": "Jogador" } }`.

### 2. `GET /cotacoes/publicos?busca=`
`porcentagens_vendedores.editar`. Usuários cujas porcentagens o solicitante pode editar (ele mesmo e
a rede; Admin e Supervisor: todos), ativos, agrupados no frontend por função.
`data: [{ id, nome, login, funcao, superior: {id, nome}|null }]`, paginado (por_pagina até 100).

### 3. `GET /cotacoes/clientes?busca=`
`porcentagens_clientes.editar`. Busca por nome, telefone ou e-mail. `data: [{ id, nome, telefone,
email }]`, paginado.

### 4. `GET /cotacoes/usuarios/{usuario}`
`porcentagens_vendedores.editar` + `garantir_dono`. Com `?campeonatos_id=`, traz também
`cadeados_campeonato` (cadeados da regra de campeonato com alvo Vendedores deste usuário). Resposta:
```json
{
  "usuarios_id": 7,
  "pre_jogo": { "odd1": -5 },
  "ao_vivo": {},
  "superiores": { "pre_jogo": { "odd1": -4 }, "ao_vivo": {} },
  "cadeados": [
    { "id": 3, "tipo": "pre_jogo", "codigo": "odd1", "trancado_por": { "id": 2, "nome": "Melissa" },
      "no_usuario": { "id": 5, "nome": "Gerente X" }, "pode_destrancar": false }
  ]
}
```
`superiores` = soma das porcentagens dos superiores do usuário (`ids_hierarquia_acima` sem ele).
`cadeados` = os que valem para ele (postos nele ou em um superior dele).

### 5. `GET /cotacoes/paises`
Permissão `porcentagens_campeonatos.editar`. `{ "paises": [{ "pais": "Brasil", "bandeira": "url" }] }`.

### 6. `GET /cotacoes/campeonatos?busca=&pais=&com_ajuste=1`
`porcentagens_campeonatos.editar`. `data: [{ id, nome, pais, bandeira, jogos_futuros, resumo:
{ quantidade, alvos: ["Clientes"], minimo: -5, maximo: 3 } | null }]`, ordenado pelos favoritos
primeiro e depois pelo nome.
`resumo` só com as regras visíveis ao solicitante (`ids_donos_visiveis`).

### 7. `GET /cotacoes/confrontos?busca=&campeonatos_id=&dia=hoje|amanha|depois_de_amanha&com_ajuste=1`
`porcentagens_confrontos.editar`. Só pré-jogo de hoje a depois de amanhã (Brasília).
`data: [{ id, time_casa, time_fora, data_inicio, campeonato: {id, nome, pais, bandeira}, resumo }]`.

### 8. `GET /cotacoes/previa?confrontos_id=&campeonatos_id=&publico=clientes|cliente|usuario&publico_id=&tipo=pre_jogo|ao_vivo&camada=campeonatos|confrontos&alvo=&alvo_usuarios_id=`
Permissão de cotação. Sem `confrontos_id`, escolhe o próximo jogo de pré-jogo (R-03). Com `campeonatos_id`, o próximo jogo daquele campeonato. O público
segue a tela em edição; `camada` + `alvo`/`alvo_usuarios_id` indicam a regra de campeonato ou jogo em edição.
```json
{
  "confronto": { "id": 10, "time_casa": "A", "time_fora": "B", "data_inicio": "...", "campeonatos_id": 3 },
  "partes": {
    "odd1": { "provedor": 2.0, "publico": -10, "superiores": 0, "geral": 0, "campeonato": 3,
              "jogo": 0.1, "teto": 50.0, "maximo_ao_vivo": null }
  }
}
```
`publico`/`campeonato`/`jogo` já excluem a regra que está sendo editada quando ela é informada, para o
frontend somar os valores pendentes no lugar dela.

### 8b. `GET /cotacoes/previa/jogos?busca=`
Permissão de cotação. Próximos 20 jogos de pré-jogo (a partir de agora, Brasília) para o "Trocar
jogo" da prévia, com busca por time: `data: [{ id, time_casa, time_fora, data_inicio, campeonato:
{ id, nome } }]`. Não depende da permissão de jogos.

### 9. `GET /cotacoes/regras-ativas?camada=vendedores|clientes|campeonatos|confrontos|tetos`
Permissão de cotação; cada camada só com a sua permissão e o alcance. `data: [{ camada, referencia:
{ id, nome }, alvo, usuario: { id, nome, ativo }|null, tipo, quantidade, minimo, maximo,
atualizado_em }]`, paginado.

### 10. `GET /cotacoes/historico?camada=&usuarios_id=&clientes_id=&campeonatos_id=&confrontos_id=&tipo=&alvo=`
Permissão da camada + alcance da referência. `data: [{ id, acao, autor: { id, nome }, criado_em,
tipo, alvo, alteracoes: { "odd1": { "antes": null, "depois": -5 } } }]`, mais nova primeiro, paginado.

### 11. `POST /cotacoes/cadeados` · 12. `DELETE /cotacoes/cadeados`
Admin ou Supervisor com a permissão da camada (`porcentagens_vendedores.editar` ou
`porcentagens_campeonatos.editar`). Corpo:
```json
{ "camada": "vendedores", "usuarios_id": 5, "tipo": "pre_jogo", "campeonatos_id": null, "codigos": ["odd1"] }
```
- `camada=vendedores` exige `tipo`; `camada=campeonatos` exige `campeonatos_id` e proíbe `tipo`.
- `usuarios_id` dentro do alcance (`garantir_dono`); Gerente → 403.
- DELETE só destranca os cadeados do próprio autor ou de quem está abaixo dele; os demais são
  ignorados e informados em `nao_destrancados`.
- Resposta: `{ "cadeados": [...como no item 4...], "nao_destrancados": 0 }`. Grava histórico.

## Rotas existentes alteradas (spec 003)

| Rota | Mudança |
|---|---|
| `PATCH /porcentagens-vendedores/{usuario}` | `valores` com código trancado → 422 `valores.{codigo}` "A cotação {nome} está trancada por {autor}."; `todos` pula os trancados e responde `pulados`; grava histórico |
| `PATCH /porcentagens-campeonatos/{campeonato}` | Idem para alvo Vendedores (cadeado da camada `campeonatos`); grava histórico |
| `PATCH /porcentagens-clientes` | Grava histórico |
| `PATCH /porcentagens-confrontos/{confronto}` | Grava histórico; jogo que já começou → 422 `confronto` "O jogo já começou; o valor do jogo vale só antes do início." |
| `PATCH /confrontos-teto-cotacoes` | Grava histórico |

As consultas (`GET`) dessas rotas não mudam.

## Rota web nova

`GET /painel/cotacoes` → Inertia `PanelQuotes` (aba por `?aba=`).

## Postman

`docs/postman/wssports_api.postman_collection.json` regenerada com as 13 rotas novas (pasta
"Cotações do painel") e os exemplos novos das 5 alteradas.
