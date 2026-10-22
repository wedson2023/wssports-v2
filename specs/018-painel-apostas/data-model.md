# Data Model: Painel — Apostas

**Spec**: [spec.md](spec.md) · **Research**: [research.md](research.md)

Esta spec **não cria tabelas nem colunas**. Ela lê as tabelas das specs 004, 008, 009 e 010 e acrescenta
as permissões (o índice que a lista usa já existe).

## 1. Tabelas lidas

| Tabela | Colunas usadas | Para quê |
|---|---|---|
| `apostas` | `id`, `codigo`, `usuarios_id`, `clientes_id`, `nome`, `situacao`, `resultado`, `tipo`, `forma_pagamento`, `valor`, `comissao`, `premio`, `valor_acrescido`, `fracao_restante`, `valor_pago`, `created_at`, `cancelada_em`, `cancelada_por`, `deleted_at` | Lista, filtros, totais, planilha e premiados |
| `apostas_palpites` | `apostas_id`, `situacao`, `resultado`, `confrontos_ao_vivo_id` | Jogos restantes/total; tipo Pré-jogo / Ao vivo |
| `apostas_encerramentos` | `apostas_id`, `situacao`, `valor` | Cartão Encerramentos (Admin e Supervisor) |
| `usuarios` | `id`, `nome`, `login`, `ativo`, `deleted_at`, `usuarios_id` | Nome do vendedor e do autor do cancelamento; etiqueta inativo/excluído; alcance |
| `clientes` | `id`, `nome` | Cliente das apostas do site, quando `apostas.nome` estiver vazio |
| `configuracoes` | `nome_sistema`, `logo` | Planilha e imagem de premiados |

## 2. Índices usados (já existentes)

| Tabela | Índice | Motivo |
|---|---|---|
| `apostas` | `(situacao, created_at)` (spec 014) | Lista e totais do Admin (sem filtro de vendedor), ordenados e filtrados pela data (R-04) |

Criado pela spec 014 (`2026_10_20_000003_add_indices_dashboard.php`); esta spec não cria índice nem
migration de estrutura.

## 3. Valores derivados (não gravados)

| Valor | Regra |
|---|---|
| Prêmio exibido | `total_a_pagar` = `TRUNCATE((premio + valor_acrescido) × fracao_restante, 2)`; na aposta `Encerrada`, `apostas.valor_pago` (valor pago nos encerramentos, spec 009/010) |
| Situação exibida | `resultado` quando diferente de `Aguardando`; senão `situacao` |
| Jogos | `restantes` = palpites `Ativo` com `resultado = Aguardando`; `total` = palpites `Ativo` |
| Tipo | Ao vivo = existe palpite com `confrontos_ao_vivo_id`; Pré-jogo = não existe |
| Vendedor exibido | nome do vendedor; "Site" quando `clientes_id` não é nulo |
| Situação do vendedor | `excluido` (com `deleted_at`), `inativo` (`ativo = Não`) ou `ativo` |
| Totais | R-03 (Apostas, Entradas, Comissão, Saídas, Em aberto, Encerramentos; Canceladas: quantidade e valor) |

## 4. Alcance (quem vê o quê)

| Função | Apostas do balcão | Apostas do site (cliente) |
|---|---|---|
| Admin | todas | todas |
| Supervisor | vendedores da sub-hierarquia (inclusive excluídos) | todas |
| Gerente | vendedores da sub-hierarquia (inclusive excluídos) | nenhuma |
| Vendedor | só as próprias | nenhuma |

Situações listadas: `Ativa` (Todas, Pré-jogo, Ao vivo) e `Cancelada` (Canceladas). Nunca `Pendente`,
`Em análise`, `Recusada` nem `Expirada`.

## 5. Permissões novas (`Funcao::PERMISSOES_APOSTAS`)

| Permissão | Para quê | Admin | Supervisor | Gerente | Vendedor |
|---|---|---|---|---|---|
| `apostas.listar` | Ver a tela Apostas, a lista, o bilhete e o filtro de vendedores | ✅ | ✅ | ✅ | ✅ (só as próprias) |
| `apostas.exportar` | Baixar planilha; premiados (só Gerente para cima) | ✅ | ✅ | ✅ | ✅ (só planilha) |
| `apostas.imprimir_segunda_via` | Imprimir a 2ª via de aposta aguardando resultado | ✅ | ✅ | ✅ | ✅ |

Migration de dados: `2026_10_24_000001_criar_permissoes_apostas_painel.php`, no padrão da
`2026_10_23_000004_criar_permissoes_caixa.php` (cria as três e dá aos usuários existentes as que a
função recebe por padrão).

Continuam valendo, sem mudança: `apostas.cancelar`, `apostas.cancelar_iniciada` e `apostas.editar`.
