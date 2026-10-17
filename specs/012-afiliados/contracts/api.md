# Contrato da API: Afiliados

Prefixo `/api`. JSON em `snake_case`; erros de validação em 422 com `message` e `errors` por campo;
mensagens em português. Listagens paginadas no formato do Laravel (`data`, `links`, `meta`),
`por_pagina` de 1 a 100.

Legenda de autenticação:
- **público**: sem token;
- **afiliado**: `auth:afiliados` + `GarantirAcessoAfiliado`;
- **painel**: `auth:api` + `garantir_acesso` + permissão indicada (via `Funcao::usuario_pode`).

---

## 1. Área do afiliado (`/area-afiliado`)

### 1.1 `POST /area-afiliado/cadastro` — público

Cadastro público (FR-010 a FR-013). Limite: 5 por minuto por IP (429).

Corpo:

```json
{
  "nome": "Maria Souza", "login": "maria.souza", "password": "senha1234", "password_confirmation": "senha1234",
  "email": "maria@exemplo.com", "cpf": "123.456.789-09", "ddi": "55", "telefone": "(81) 99999-9999",
  "cidade": "Recife", "estado": "PE", "endereco": "Rua A, 10",
  "pix_tipo_chave": "CPF", "pix_chave": "123.456.789-09"
}
```

Obrigatórios: `nome`, `login`, `password` (+ confirmação), `email`, `cpf`, `telefone`,
`pix_tipo_chave`, `pix_chave`. Campos de situação, gerente e regra são ignorados.

`201`: `{ "message": "Cadastro recebido! Vamos analisar e avisaremos por e-mail." }` (sem token).

### 1.2 `POST /area-afiliado/auth/login` — público

Corpo: `{ "login": "maria.souza", "password": "..." }`. Limite: 5 erros por login + IP em 60 s.

- `200`: `{ "token": "...", "tipo": "bearer", "expira_em": 3600 }`
- `401`: `{ "message": "Login ou senha inválidos." }`
- `403`: `"Seu cadastro ainda está em análise."` | `"Seu cadastro não foi aprovado."` |
  `"Seu acesso está desativado."`

### 1.3 `POST /area-afiliado/auth/refresh` e `POST /area-afiliado/auth/logout` — afiliado

Mesmo formato do cliente (`refresh` devolve token novo; `logout` 204).

### 1.4 `POST /area-afiliado/auth/recuperar-senha` — público

Corpo: `{ "identificacao": "maria.souza" }` (login ou e-mail). Um pedido por identificação a cada
60 s (429). Sempre `200`: `{ "message": "Se o cadastro existir, enviaremos um código para o e-mail." }`.

### 1.5 `POST /area-afiliado/auth/redefinir-senha` — público

Corpo: `{ "identificacao": "...", "codigo": "123456", "password": "...", "password_confirmation": "..." }`.
`204` ou `422` `{ "message": "Código inválido ou expirado." }`.

### 1.6 `GET /area-afiliado/painel` — afiliado

```json
{
  "data": {
    "nome": "Maria Souza", "codigo": "K7M2Q9XA", "link": "https://site/?user=K7M2Q9XA",
    "valor_a_receber": "85.00",
    "ultima_prestacao": { "valor_pago": "120.00", "periodo_fim": "2026-10-10T14:00:00-03:00" },
    "comissao": { "tipo_ganho": "Qualquer depósito", "percentual": "10.00", "vigente_desde": "..." },
    "clientes_indicados": 12
  }
}
```

`ultima_prestacao` é `null` sem prestações.

### 1.7 `GET /area-afiliado/extrato` — afiliado

Query: `de`, `ate` (`Y-m-d`, opcionais), `situacao` (`aberto` | `prestado`, opcional),
`por_pagina` (padrão 30, máx. 100). Ordem: mais recente primeiro.

Item: `{ "id", "evento", "tipo_ganho", "base", "percentual", "valor", "ocorrido_em", "prestado": bool }`.

### 1.8 `GET /area-afiliado/clientes` — afiliado

Query: `por_pagina` (padrão 30, máx. 100). Item:
`{ "nome": "João S. P.", "cadastrado_em": "...", "total_depositado": "350.00" }` (R-12).

### 1.9 `GET /area-afiliado/prestacoes` — afiliado

Item: `{ "id", "valor_apurado", "valor_pago", "quantidade_lancamentos", "periodo_inicio", "periodo_fim", "observacao" }`.

### 1.10 `GET /area-afiliado/meus-dados` e `PATCH /area-afiliado/meus-dados` — afiliado

`GET`: nome, login, email, cpf (sem máscara: são os dados dele), ddi, telefone, cidade, estado,
endereço, `pix_tipo_chave`, `pix_chave`.

`PATCH`: só `ddi`, `telefone`, `cidade`, `estado`, `endereco`, `pix_tipo_chave`, `pix_chave`;
os demais campos enviados dão 422 (`prohibited`).

### 1.11 `PUT /area-afiliado/meus-dados/senha` — afiliado

Corpo: `{ "senha_atual", "password", "password_confirmation" }`. `200` com token novo (os demais
deixam de valer); `422` no campo `senha_atual` se errada.

---

## 2. Gestão no painel (`/afiliados`)

Afiliado fora do alcance do Gerente ou inexistente: `404 { "message": "Afiliado não encontrado." }`.

### 2.1 `GET /afiliados` — `afiliados.listar`

Query: `situacao` (`Em análise` | `Ativo` | `Inativo` | `Recusado`), `busca` (nome, login ou
código), `usuarios_id` (gerente; ignorado para Gerente), `de`, `ate` (`Y-m-d`, padrão hoje),
`por_pagina` (padrão 20, máx. 100). Ordem: nome.

Item (`AfiliadosResource` + totais):

```json
{
  "id": 1, "nome": "Maria Souza", "login": "maria.souza", "codigo": "K7M2Q9XA",
  "email": "m***@exemplo.com", "cpf": "***.456.789-**", "ddi": "55", "telefone": "(81) *****-9999",
  "situacao": "Ativo", "origem_cadastro": "Painel",
  "gerente": { "id": 7, "nome": "Gerente A" },
  "comissao": { "tipo_ganho": "Qualquer depósito", "percentual": "10.00" },
  "totais_periodo": { "cadastros": 3, "depositos": "500.00", "saques": "100.00", "comissao": "50.00" },
  "valor_a_receber": "85.00",
  "ultima_prestacao": { "valor_pago": "120.00", "periodo_fim": "..." },
  "created_at": "..."
}
```

### 2.2 `GET /afiliados/excluidos` — `afiliados.restaurar`

Mesmos filtros e formato, só a lixeira.

### 2.3 `POST /afiliados` — `afiliados.cadastrar`

Corpo do 1.1 + `usuarios_id` (só Admin/Supervisor; Gerente fica responsável), `tipo_ganho` e
`percentual` (valem só com `afiliados.editar_comissao`; sem ela, a regra padrão). Nasce `Ativo`,
origem `Painel`. `201` com o afiliado.

### 2.4 `GET /afiliados/{afiliado}` — `afiliados.consultar`

Afiliado com `meio_pagamento`, `comissao` vigente, `valor_a_receber`, `ultima_prestacao`.

### 2.5 `PATCH /afiliados/{afiliado}` — `afiliados.editar`

Campos: `nome`, `email`, `cpf`, `ddi`, `telefone`, `cidade`, `estado`, `endereco`, `pix_tipo_chave`,
`pix_chave`, `password` (+ confirmação; invalida os tokens), `usuarios_id` (só Admin/Supervisor),
`codigo` (só Admin/Supervisor). `login` é `prohibited`.

### 2.6 `PATCH /afiliados/{afiliado}/situacao` — `afiliados.alterar_situacao`

Corpo: `{ "situacao": "Ativo" | "Inativo" }`. Só entre Ativo e Inativo (Em análise e Recusado
usam 2.7/2.8). Inativar invalida os tokens.

### 2.7 `POST /afiliados/{afiliado}/aprovar` — `afiliados.alterar_situacao` + Admin/Supervisor

Corpo opcional: `usuarios_id`, `tipo_ganho`, `percentual`. Só de `Em análise` ou `Recusado` (422 nos
demais). Vira `Ativo`; e-mail de aprovação.

### 2.8 `POST /afiliados/{afiliado}/recusar` — `afiliados.alterar_situacao` + Admin/Supervisor

Corpo: `{ "motivo": "..." }` (opcional, até 255). Só de `Em análise`. Vira `Recusado`; e-mail de
recusa.

### 2.9 `PUT /afiliados/{afiliado}/comissao` — `afiliados.editar_comissao`

Corpo: `{ "tipo_ganho": "Saldo líquido", "percentual": 15 }`. Encerra a vigente e abre a nova.
`GET /afiliados/{afiliado}/comissoes` (`afiliados.consultar`): histórico paginado.

### 2.10 `GET /afiliados/{afiliado}/lancamentos` — `afiliados.consultar`

Query como 1.7, mais `clientes_id`. Item com `cliente` (`id`, `nome`).

### 2.11 `GET /afiliados/{afiliado}/clientes` — `afiliados.consultar`

Clientes ligados (`ClientesResource`, com a máscara do painel) + `total_depositado`.

### 2.12 `GET /afiliados/{afiliado}/prestacoes` — `afiliados.consultar`

Histórico com `usuario` (`id`, `nome`).

### 2.13 `POST /afiliados/{afiliado}/prestacoes` — `afiliados.prestar_contas`

Corpo: `{ "password": "senha do usuário do painel", "observacao": "Pago via Pix" }`. Afiliado na
lixeira: 404. Senha errada: 422 no campo `password`. `201` com a prestação (pode ter valores zero).

### 2.14 `DELETE /afiliados/{afiliado}` — `afiliados.excluir` (Admin/Supervisor)

204. Sufixo nos dados únicos, tokens invalidados.

### 2.15 `POST /afiliados/{afiliado}/restaurar` — `afiliados.restaurar` (Admin/Supervisor)

Corpo opcional com valores novos para `login`, `email`, `cpf`, `codigo`. Conflito: 422 com o campo.

---

## 3. Configuração

### 3.1 `GET /configuracoes/afiliados` e `PUT /configuracoes/afiliados` — `configuracoes.editar`

`{ "afiliados_tipo_ganho_padrao": "Saldo líquido", "afiliados_percentual_padrao": "0.00" }`.

---

## 4. Rotas existentes alteradas

| Rota | Alteração |
|---|---|
| `POST /area-cliente/cadastro` | `codigo_afiliado` continua no corpo; código inexistente → 422 "Código de afiliado não encontrado."; afiliado não Ativo ou na lixeira → cliente sem ligação |
| `GET /clientes`, `GET /clientes/excluidos` | filtro `afiliados_id` no lugar de `codigo_afiliado` |
| `GET/PATCH /clientes/{cliente}` | resposta: `afiliados_id` e `afiliado` (`id`, `nome`, `codigo`) no lugar de `codigo_afiliado`; `PATCH` aceita `afiliados_id` no lugar de `codigo_afiliado` |
| `POST/PUT /clientes-promocoes`, `GET /clientes-promocoes` | `afiliados_id` (existente, fora da lixeira) no lugar de `codigo_afiliado` no corpo e na resposta |
| `POST /usuarios` | login também não pode repetir o de um afiliado |
