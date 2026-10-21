# Data Model: Painel — Cotações

**Spec**: [spec.md](spec.md) · **Pesquisa**: [research.md](research.md)

Tabelas existentes usadas sem mudança (spec 003): `porcentagens_vendedores`,
`porcentagens_vendedores_ao_vivo`, `porcentagens_clientes`, `porcentagens_clientes_ao_vivo`,
`porcentagens_campeonatos`, `porcentagens_confrontos` e `confrontos_teto_cotacoes` (todas com
`valores`/`tetos` em JSON `{codigo: valor}`, código ausente = sem ajuste).

Duas tabelas novas. Toda coluna com `->comment()` em português (constituição v3.3.0), com
`timestamps()` e `softDeletes()`.

## porcentagens_cadeados (nova)

Cadeado de uma cotação posto por Admin ou Supervisor na tela de um usuário da rede (R-04).

| Coluna | Tipo | Regra / comentário |
|---|---|---|
| `id` | bigint | — |
| `usuarios_id` | FK `usuarios` | Usuário travado: o cadeado vale para ele e para todos abaixo dele |
| `trancado_por` | FK `usuarios` | Quem trancou (Admin ou Supervisor); só ele ou um superior dele destranca |
| `camada` | varchar(20) | `vendedores` (porcentagens do usuário) ou `campeonatos` (regra de campeonato com alvo Vendedores do usuário) |
| `tipo` | varchar(10) null | `pre_jogo` ou `ao_vivo` na camada `vendedores`; nulo em `campeonatos` (a regra do campeonato vale para os dois) |
| `campeonatos_id` | FK `campeonatos` null | Campeonato da camada `campeonatos`; nulo em `vendedores` |
| `codigo` | varchar(10) | Código trancado: `odd1` a `odd323` ou `jogador` |
| `chave_tipo` | varchar (gerada) | `COALESCE(tipo, '')`, para o índice único com nulos |
| `chave_campeonato` | bigint (gerada) | `COALESCE(campeonatos_id, 0)`, idem |
| `created_at`, `updated_at`, `deleted_at` | timestamps | Destrancar = soft delete; trancar de novo restaura |

- Índice único: (`usuarios_id`, `trancado_por`, `camada`, `chave_tipo`, `chave_campeonato`, `codigo`).
- Índice de consulta: (`camada`, `usuarios_id`, `codigo`).
- Validação: `camada` e `tipo`/`campeonatos_id` coerentes; `codigo` em `CodigosCotacao::e_regra`;
  `trancado_por` com função Admin ou Supervisor; `usuarios_id` gerenciado por `trancado_por`
  (`gerencia`), ou qualquer usuário para o Admin.
- Bloqueio (para quem grava S uma regra do usuário U'): existe cadeado ativo com o mesmo código,
  camada e tipo/campeonato, com `usuarios_id ∈ U'->ids_hierarquia_acima()` e `trancado_por ≠ S` e
  `trancado_por ∉ S->ids_sub_hierarquia()`.
- Model `PorcentagensCadeados` (SoftDeletes), relações `usuario`, `autor` (`trancado_por`) e
  `campeonato`.

## porcentagens_historicos (nova)

Uma linha por gravação de regra ou por trancar/destrancar (R-05). A tela só lê.

| Coluna | Tipo | Regra / comentário |
|---|---|---|
| `id` | bigint | — |
| `usuarios_id` | FK `usuarios` | Autor da alteração (o nome continua aparecendo se ele for excluído: relação `withTrashed`) |
| `camada` | varchar(20) | `vendedores`, `clientes`, `campeonatos`, `confrontos` ou `tetos` |
| `acao` | varchar(20) | `alterar`, `trancar` ou `destrancar` |
| `tipo` | varchar(10) null | `pre_jogo` ou `ao_vivo` (vendedores e clientes); nulo nas demais |
| `alvo` | varchar(20) null | `Todos`, `Clientes` ou `Vendedores` (campeonatos e confrontos) |
| `alvo_usuarios_id` | FK `usuarios` null | Usuário da regra: dono das porcentagens (vendedores) ou dono do alvo Vendedores |
| `clientes_id` | FK `clientes` null | Cliente específico (clientes); nulo = regra geral dos clientes |
| `campeonatos_id` | FK `campeonatos` null | Campeonato da regra (campeonatos) ou do cadeado |
| `confrontos_id` | FK `confrontos` null | Jogo da regra (confrontos) |
| `alteracoes` | json | `{codigo: {antes: número/null, depois: número/null}}` só dos códigos alterados (null = sem ajuste) |
| `created_at`, `updated_at`, `deleted_at` | timestamps | `created_at` é a data mostrada no histórico |

- Índices: (`camada`, `alvo_usuarios_id`), (`campeonatos_id`), (`confrontos_id`), (`clientes_id`),
  (`created_at`).
- Sem diferença entre antes e depois, nenhuma linha é gravada.
- "Aplicar a todas" vira uma linha com todos os códigos alterados.
- Model `PorcentagensHistoricos` (SoftDeletes), cast `alteracoes` array, relação `autor`.

## Objetos de leitura (sem tabela)

- **Resumo de regras** (listas de campeonatos e jogos, Regras ativas): `quantidade` de regras com
  algum código, `alvos` (lista), `minimo`, `maximo` (valores entre todos os códigos), `atualizado_em`.
- **Partes da prévia** por código: `provedor`, `publico`, `superiores` (vendedores) ou `geral`
  (cliente específico), `campeonato`, `jogo`, `teto`, `maximo_ao_vivo`. Resultado no frontend:
  `provedor ≤ 0 → 0`; senão `provedor + provedor × (soma das porcentagens) ÷ 100 + jogo`, limitado ao
  teto e ao máximo, nunca menor que 1,00, 2 casas (igual a `CalculoCotacoes::aplicar`).
- **Estado do editor** (frontend, `useGradeCotacoes`): `original {codigo: valor}`, `pendentes
  {codigo: valor|null}`, `erros {codigo: mensagem}`, `trancados {codigo: {por, pode_destrancar}}`.

## Transições

- Cadeado: inexistente → trancado (POST) → destrancado (DELETE, soft delete) → trancado (restaura).
- Regra: os códigos entram e saem de `valores` (0 ou vazio remove, como na spec 003); a regra sem
  códigos some das listas e de Regras ativas, mas o histórico continua.
