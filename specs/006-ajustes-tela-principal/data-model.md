# Data Model: Ajustes da tela principal

**Feature**: `006-ajustes-tela-principal` | **Data**: 2026-10-10

Todas as tabelas novas têm `created_at`, `updated_at` e `deleted_at` (soft delete) e o model usa
`SoftDeletes` (constituição). Dinheiro e cotações em `decimal`, calculados com `bcmath`.

## 1. `especiais` (categoria especial)

| Coluna | Tipo | Regra |
|---|---|---|
| `id` | bigint | PK |
| `nome` | varchar(150) | obrigatório; único entre as não removidas |
| `data_limite` | datetime (UTC) | obrigatório; até quando aceita palpite (antigo `horario`) |
| `situacao` | varchar(20) | enum `SituacaoEspecial`: `Aguardando` (padrão), `Encerrado`, `Cancelado` |
| `especiais_opcoes_id_vencedora` | bigint nulo | FK `especiais_opcoes`; obrigatório quando `Encerrado` |
| `ativo` | boolean | padrão `true` |
| `encerrado_em` | timestamp nulo | quando foi encerrada ou cancelada |
| `encerrado_por` | bigint nulo | FK `usuarios` |

Índices: `(situacao, ativo, data_limite)`.

**Transições**: `Aguardando` → `Encerrado` (com opção vencedora da própria categoria) ou
`Aguardando` → `Cancelado`. `Encerrado` e `Cancelado` são finais (não editáveis, não recebem
palpites). Editar e remover só em `Aguardando`; remover categoria com palpite em aposta Ativa é
recusado (cancele em vez de remover).

**Visível ao público**: `ativo = true`, `situacao = Aguardando`, `data_limite > agora` e ao menos
uma opção ativa.

## 2. `especiais_opcoes` (opção da categoria)

| Coluna | Tipo | Regra |
|---|---|---|
| `id` | bigint | PK |
| `especiais_id` | bigint | FK `especiais` |
| `nome` | varchar(150) | obrigatório; único por categoria entre as não removidas |
| `cotacao` | decimal(8,2) | obrigatório; mínimo 1,01 |
| `ativo` | boolean | padrão `true` |

Índice único: `(especiais_id, nome, deleted_at)`.

Opção com palpite em aposta Ativa não pode ser removida (só desativada). Alterar a cotação vale para
os próximos palpites; palpites já gravados mantêm a `cotacao_final`.

## 3. `apostas_palpites` (alteração)

| Coluna | Mudança |
|---|---|
| `confrontos_id` | passa a aceitar nulo (palpite especial) |
| `campeonatos_id` | passa a aceitar nulo (palpite especial) |
| `especiais_id` | nova, bigint nulo, FK `especiais` |
| `especiais_opcoes_id` | nova, bigint nulo, FK `especiais_opcoes` |
| `resultado` | nova, varchar(20), padrão `Aguardando`; enum `ResultadoAposta` (`Aguardando`, `Vencedor`, `Perdedor`) reaproveitado para o palpite |

Índice único novo: `(apostas_id, especiais_id)`. O único `(apostas_id, confrontos_id)` continua
(nulos não colidem).

Palpite especial: `codigo_cotacao = "especial"`, `esporte = "ESPECIAL"`, `confrontos_id`,
`confrontos_ao_vivo_id`, `campeonatos_id` e `confrontos_jogadores_id` nulos; `cotacao_original` e
`cotacao_final` = cotação da opção no envio.

## 4. `avisos`

| Coluna | Tipo | Regra |
|---|---|---|
| `id` | bigint | PK |
| `titulo` | varchar(100) nulo | texto alternativo da imagem e título do modal |
| `imagem` | varchar(255) | caminho no disco `public` (`avisos/{sha1}.{ext}`, research R-20) |
| `link` | varchar(500) nulo | URL `http(s)` |
| `inicio_em` | datetime nulo (UTC) | sem valor = já vale |
| `fim_em` | datetime nulo (UTC) | sem valor = sem fim; ≥ `inicio_em` |
| `ativo` | boolean | padrão `true` |

**Elegível**: `ativo`, `inicio_em` nulo ou ≤ agora, `fim_em` nulo ou ≥ agora, e sem leitura do
aparelho (nem do cliente logado). Um sorteado (`inRandomOrder`).

## 5. `avisos_leituras`

| Coluna | Tipo | Regra |
|---|---|---|
| `id` | bigint | PK |
| `avisos_id` | bigint | FK `avisos` |
| `aparelho` | char(36) | UUID do navegador |
| `clientes_id` | bigint nulo | FK `clientes`, quando havia sessão de cliente |
| `ip` | varchar(45) nulo | registro, não usado na regra |

Índices: único `(avisos_id, aparelho)`; `(avisos_id, clientes_id)`.

## 6. `banners`

| Coluna | Tipo | Regra |
|---|---|---|
| `id` | bigint | PK |
| `imagem` | varchar(255) | caminho no disco `public` (`banners/{sha1}.jpg`), ajustada para 1280×405 |
| `link` | varchar(500) nulo | URL `http(s)` |
| `ordem` | unsigned smallint | padrão `0`; menor aparece primeiro, empate por `id` |
| `ativo` | boolean | padrão `true` |

Índice: `(ativo, ordem)`. **No carrossel**: `ativo = true`, ordenados por `ordem` e `id`.

## 7. `configuracoes` (alteração)

| Coluna | Mudança |
|---|---|
| `logo` | nova, varchar(255) nula; caminho no disco `public` (`logos/{sha1}.{ext}`); `null` = logo padrão `public/images/logo_padrao.png` |
| `regras` | nova, text nula; texto das regras da banca, um parágrafo por linha; `""` = sem bloco; preenchida pela migration e pelo `$attributes` do model com `Configuracoes::REGRAS_PADRAO` (research R-22) |

## 8. `clientes_promocoes` (sem mudança de estrutura)

Quatro registros padrão criados pelo `ClientesPromocoesSeeder`, inativos (research R-15).

## 9. Enums

- `SituacaoEspecial`: `Aguardando`, `Encerrado`, `Cancelado`.
- `ResultadoAposta` (existente): passa a ser usado também em `apostas_palpites.resultado`.

## 10. Permissões (`Funcao`)

| Constante | Permissões | Quem usa |
|---|---|---|
| `PERMISSOES_ESPECIAIS` | `especiais.listar`, `especiais.gerenciar`, `especiais.encerrar` | Admin, Supervisor |
| `PERMISSOES_SITE` | `avisos.gerenciar`, `banners.gerenciar`, `configuracoes.editar` (logo e texto das regras) | Admin, Supervisor |

A tabela de jogos usa a permissão existente `apostas.criar` (só Vendedor).

## 11. Estado no aparelho (frontend)

| Chave `localStorage` | Conteúdo | Apagada por "Limpar cache" |
|---|---|---|
| `wssports.impressao` | `{ modo: "PADRÃO" \| "APP", largura: 58 \| 80 }` | sim |
| `wssports.aparelho` | UUID do aparelho (avisos) | sim (um novo é gerado) |
| `wssports.cupom` (existente) | palpite especial: `{ tipo: "especial", confronto_id: especiais_id, codigo_cotacao: "especial", opcao_id, mercado, cotacao, time_casa: "Vencedor", time_fora: categoria, campeonato: categoria, data_inicio: data_limite }` | sim |

Em memória (não persistida): a característica Bluetooth da impressora conectada.

## 12. Resultado da aposta depois de encerrar ou cancelar um especial (FR-018a)

Considerando só os palpites `Ativo`:

1. algum com `resultado = Perdedor` → aposta `Perdedor`;
2. nenhum palpite ativo, ou todos especiais com `resultado = Vencedor` → aposta `Vencedor`;
3. senão → `Aguardando`.

Só apostas `Ativa` com `resultado = Aguardando` são recalculadas.
