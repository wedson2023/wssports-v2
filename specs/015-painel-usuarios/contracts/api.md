# Contrato da API — Usuários do painel

Rotas em `routes/api.php`, grupo `['auth:api', 'garantir_acesso']`. Mensagens em português.
Erros comuns: `401 Não autenticado.`, `403 Você não tem permissão para esta ação.`,
`404 Usuário não encontrado.` (fora da rede é tratado como inexistente).

## 1. `GET /auth/eu` (alterada, spec 014)

Acrescenta `permissoes`: nomes das permissões que o usuário tem e pode usar.

```json
{ "data": { "id": 2, "nome": "...", "login": "...", "funcao": "Supervisor", "secoes": ["..."],
  "permissoes": ["usuarios.listar", "usuarios.cadastrar_vendedor", "..."] } }
```

## 2. `GET /usuarios` (alterada)

Query: `funcao`, `ativo`, `busca`, `por_pagina` (1–100, padrão 15), `pagina`. Exige `usuarios.listar`.
Cada item ganha `superior`, `comissoes` (vendedor), `quantidade_vendedores` (gerente, supervisor) e
`quantidade_gerentes` (supervisor) — data-model §4.

## 3. `GET /usuarios/excluidos` (nova)

Mesmos filtros e paginação; só excluídos da rede (incluindo os excluídos no caminho). Exige
`usuarios.listar`. Itens com `deleted_at`.

## 4. `POST /usuarios` (alterada)

Exige `usuarios.cadastrar_{funcao}` (que a função de quem cadastra possa usar).

```json
{ "nome": "Carlos", "login": "carlos", "password": "123456", "funcao": "Vendedor",
  "usuarios_id": 3, "telefone": "(82) 99999-0000", "endereco": "...",
  "comissao_1": "5.00", "comissao_2": "7.00", "comissao_3": "10.00" }
```

- `funcao`: abaixo da de quem cadastra (data-model §2), senão 422 "Você não pode cadastrar usuários com
  a função X.".
- `usuarios_id`: obrigatório fora da função imediata ("Informe o superior."), proibido na imediata;
  inválido → "O superior informado é inválido para a função deste usuário.".
- `comissao_*`: só Vendedor; `numeric|between:0,100`, 2 casas.
- 201 com o usuário.

## 5. `PUT/PATCH /usuarios/{usuario}` (alterada)

Dados e troca de superior exigem `usuarios.editar_{funcao do alvo}`; `ativo` continua exigindo
`usuarios.alterar_situacao` (cascata).

## 6. `DELETE /usuarios/{usuario}` (alterada)

Exige `usuarios.excluir_{funcao do alvo}`. Exclusão lógica em cascata. 204.

## 7. `POST /usuarios/{usuario}/restaurar` (nova)

Exige `usuarios.excluir_{funcao do alvo}`; alvo precisa estar excluído e na rede. Restaura o alvo e os
excluídos junto com ele. 200 com o usuário; 422 "Restaure antes o superior." quando o superior ainda
está excluído; 404 se não estiver na Lixeira.

## 8. `GET /usuarios-configuracoes/{usuario}` (nova)

Configuração de um vendedor da rede (formato de `UsuariosConfiguracoesResource`). Exige
`usuarios.consultar`; o vendedor sem linha a recebe com os padrões. A gravação continua no
`PATCH /usuarios-configuracoes` existente (`usuarios_id` = vendedor, gerente ou supervisor), que exige
`usuarios_configuracoes.editar` e devolve quantos vendedores foram alterados.

## 9. Permissões por usuário (sem mudança de rota)

`GET/POST/DELETE /usuarios/{usuario}/permissoes` (spec 001): o `POST` passa a recusar também a
permissão que a função do alvo não pode usar (422 "Esta permissão não vale para a função do usuário.").

## 10. Postman

Coleção regenerada com as rotas novas (3) e as alteradas.
