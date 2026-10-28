# Data Model: Painel — Especiais

**Feature**: [spec.md](spec.md) | **Decisões**: [research.md](research.md)

Toda coluna nova com `->comment(...)` em português (constituição). Valores de enum em português com
inicial maiúscula.

## 1. `especiais` (alterada)

| Coluna | Tipo | Regra |
|---|---|---|
| `quantidade_vencedoras` | unsigned tinyint, padrão 1 | **nova**. Quantas opções vencem ao encerrar; ≥ 1 e menor que as opções não anuladas. Comentário: "Quantidade de opções vencedoras exigida ao encerrar (padrão 1)" |
| `dividir_empate` | boolean, padrão falso | **nova**. Se o encerramento dividiu a cotação das empatadas (FR-015). Comentário: "Se o encerramento dividiu a cotação das opções empatadas na última vaga (dead heat)" |
| `especiais_opcoes_id_vencedora` | — | **removida** (com a chave estrangeira), depois de migrada para `especiais_opcoes.vencedora` (R-05) |

Demais colunas sem mudança (`nome`, `data_limite`, `situacao`, `ativo`, `encerrado_em`,
`encerrado_por`, timestamps, `deleted_at`).

**Situações** (`SituacaoEspecial`, sem mudança):

```text
Aguardando ──encerrar──▶ Encerrado ──corrigir resultado──▶ Encerrado (vencedoras novas)
     │
     └────cancelar────▶ Cancelado (final)

Aguardando ──remover (sem palpites em apostas ativas)──▶ lixeira ──restaurar──▶ Aguardando
```

## 2. `especiais_opcoes` (alterada)

| Coluna | Tipo | Regra |
|---|---|---|
| `vencedora` | boolean, nulo | **nova**. Nulo enquanto a categoria não foi encerrada; `true`/`false` depois. Comentário: "Resultado da opção ao encerrar: nulo até o encerramento, verdadeiro se venceu" |
| `empatada` | boolean, padrão falso | **nova**. Vencedora empatada na última vaga (só com `dividir_empate`). Comentário: "Vencedora empatada na última vaga, com a cotação dividida (dead heat)" |
| `anulada_em` | timestamp, nulo | **nova**. Comentário: "Quando a opção foi anulada (palpites devolvidos como cotação 1,00)" |
| `anulada_por` | FK `usuarios`, nulo | **nova**. Comentário: "Usuário do painel que anulou a opção" |

**Situação exibida da opção** (calculada, `EspeciaisOpcoesResource.situacao`):

| Condição | Situação |
|---|---|
| `anulada_em` preenchido | Anulada |
| categoria Encerrado e `vencedora` | Vencedora (com "empatada" quando `empatada`) |
| categoria Encerrado e não `vencedora` | Perdedora |
| `ativo` | Ativa |
| senão | Desativada |

Regras: opção anulada fica com `ativo = false` e não volta; não é editada, removida nem marcada
como vencedora (FR-012). Nome único na categoria (índice atual).

**Migração dos dados** (na mesma migration): para cada categoria com `especiais_opcoes_id_vencedora`,
a opção recebe `vencedora = true` e as demais da categoria `false`; categorias Encerrado sem
vencedora não existem (o encerramento sempre gravava). `quantidade_vencedoras` = 1 para todas.

## 3. `apostas_palpites` (alterada)

| Coluna | Tipo | Regra |
|---|---|---|
| `cotacao_empate` | decimal(8,2), nulo | **nova**. Cotação aplicada pela divisão por empate; nula sem divisão. Comentário: "Cotação aplicada ao palpite especial pela divisão por empate (dead heat); nula quando não houve divisão" |

Cálculo (R-06): `cotacao_empate = trunc2(cotacao_final × V ÷ E)`, com `Q` = quantidade de
vencedoras, `M` = marcadas, `E` = empatadas, `V = Q − (M − E)`. O prêmio da aposta usa
`cotacao_empate ?? cotacao_final`.

## 4. `especiais_correcoes` (nova)

Histórico das correções de resultado (FR-032).

| Coluna | Tipo | Comentário |
|---|---|---|
| `id` | id | |
| `especiais_id` | FK `especiais` | Categoria corrigida |
| `vencedoras_anteriores` | json | Ids das opções vencedoras antes da correção |
| `empatadas_anteriores` | json | Ids das vencedoras empatadas antes da correção |
| `dividir_empate_anterior` | boolean | Divisão por empate antes da correção |
| `vencedoras_novas` | json | Ids das opções vencedoras depois da correção |
| `empatadas_novas` | json | Ids das vencedoras empatadas depois da correção |
| `dividir_empate_novo` | boolean | Divisão por empate depois da correção |
| `apostas_afetadas` | unsigned int | Quantidade de apostas reapuradas |
| `usuarios_id` | FK `usuarios` | Quem corrigiu |
| timestamps, `deleted_at` | | |

Model `EspeciaisCorrecoes` (`SoftDeletes`, casts `array` e `boolean`); relação
`Especiais::correcoes()`.

## 5. Enums (casos novos)

| Enum | Caso novo | Valor |
|---|---|---|
| `MotivoCancelamentoPalpite` | `OpçãoAnulada` | `Opção anulada` |
| `OrigemApuracao` | `CorreçãoEspecial` | `Correção de especial` |
| `AcaoHistoricoAposta` | `DivisãoEmpate` | `Divisão por empate` |

## 6. Permissão nova

| Nome | Grupo | Padrão |
|---|---|---|
| `especiais.corrigir_resultado` | Especiais — "Corrigir resultado de especiais" ("Troca as vencedoras de um especial encerrado e reapura as apostas.") | só Admin |

Criada por migration (`firstOrCreate` + dada aos Admin existentes, padrão de
`criar_permissao_robo_whatsapp`), incluída em `Funcao::PERMISSOES_ESPECIAIS` e liberada só para o
Admin na mesma regra de `robo_whatsapp.conectar` em `Funcao`; as três permissões da spec 006 não
mudam.

## 7. Agregações (sem tabela)

- **Em aberto por categoria** (lista e cartões): palpites `Ativo` com `especiais_id`, em apostas
  `Ativa` e `resultado = Aguardando`, no alcance de quem consulta: `COUNT(DISTINCT apostas.id)`,
  `SUM(apostas.valor)` e possível retorno (`premio + valor_acrescido`, fração do encerramento
  parcial como em `Apostas::total_a_pagar`).
- **Exposição por opção** (gaveta): o mesmo, agrupado por `especiais_opcoes_id` e separado em
  Simples (1 palpite ativo na aposta) e Múltiplas (mais de 1).
