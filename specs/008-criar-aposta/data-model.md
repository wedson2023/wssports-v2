# Data Model: Criar Aposta

**Feature**: [spec.md](spec.md) | **Decisões**: [research.md](research.md)

Toda coluna nova tem `->comment()` em português (constituição 3.3.0). Os comentários abaixo são os
que vão para a migration.

## 1. Tabela nova `apostas_blocos`

Um registro por jogo com Criar Aposta numa aposta (R-01).

| Coluna | Tipo | Regra | Comentário |
|---|---|---|---|
| `id` | bigint, PK | | (atalho `id()`) |
| `apostas_id` | FK `apostas` | obrigatório | Aposta a que o bloco Criar Aposta pertence |
| `confrontos_id` | FK `confrontos` | obrigatório; só pré-jogo | Jogo do pré-jogo cujos palpites formam o bloco |
| `cotacao_vista` | decimal(8,2) | ≥ 1,00 | Cotação do bloco que o apostador viu no cupom, com o desconto |
| `cotacao` | decimal(8,2) | ≥ 1,00 | Cotação atual do bloco: produto das cotações dos palpites ativos × (1 − desconto ÷ 100), truncada em 2 casas |
| `situacao` | varchar(20) | enum `SituacaoBloco`, padrão `Ativo` | Situação do bloco: Ativo ou Anulado (anulado conta como 1,00 na cotação total) |
| `created_at`, `updated_at`, `deleted_at` | | | (atalhos `timestamps()` e `softDeletes()`) |

- Índice único `(apostas_id, confrontos_id)`: no máximo um bloco por jogo (FR-002).
- Model `ApostasBlocos` com `SoftDeletes`, `$guarded = ['id']` (como `ApostasPalpites`) e casts
  `cotacao_vista`/`cotacao` `decimal:2`, `situacao` `SituacaoBloco`; relações `aposta()`,
  `confronto()` e `palpites()`.

## 2. Colunas novas

### `apostas_palpites`

| Coluna | Tipo | Comentário |
|---|---|---|
| `apostas_blocos_id` | FK `apostas_blocos`, nullable | Bloco Criar Aposta do palpite; nulo no palpite simples |

- O índice único `(apostas_id, confrontos_id)` vira índice simples (R-02).
- Relação `bloco()` no model.

### `apostas`

| Coluna | Tipo | Comentário |
|---|---|---|
| `desconto_criar_aposta` | decimal(5,2), nullable | Percentual de desconto do Criar Aposta usado na confirmação (0 a 100); nulo quando a aposta não tem bloco |
| `liquidacao_criar_aposta` | varchar(20), nullable | Regra de liquidação do Criar Aposta usada na confirmação: Recalcular ou Anular bloco; nulo quando a aposta não tem bloco |

Relação `blocos()` no model; casts `decimal:2` e `LiquidacaoCriarAposta`.

### `configuracoes` (geral: clientes e visitantes)

| Coluna | Tipo | Padrão | Comentário |
|---|---|---|---|
| `criar_aposta_habilitado` | boolean | `true` | Liga o Criar Aposta (vários palpites do mesmo jogo) para clientes e visitantes |
| `desconto_criar_aposta` | decimal(5,2) | `0` | Percentual de desconto sobre a cotação do bloco Criar Aposta de clientes e visitantes (0 = sem desconto) |
| `quantidade_minima_criar_aposta` | unsignedTinyInteger | `2` | Quantidade mínima de palpites em um bloco Criar Aposta de clientes e visitantes |
| `quantidade_maxima_criar_aposta` | unsignedTinyInteger | `10` | Quantidade máxima de palpites em um bloco Criar Aposta de clientes e visitantes |
| `liquidacao_criar_aposta` | varchar(20) | `'Recalcular'` | Regra quando um palpite do bloco é cancelado ou anulado, para todo o sistema: Recalcular ou Anular bloco |

### `usuarios_configuracoes` (vendedor)

| Coluna | Tipo | Padrão | Comentário |
|---|---|---|---|
| `criar_aposta_habilitado` | boolean | `true` | Liga o Criar Aposta (vários palpites do mesmo jogo) nas apostas do vendedor |
| `desconto_criar_aposta` | decimal(5,2) | `0` | Percentual de desconto sobre a cotação do bloco Criar Aposta do vendedor (0 = sem desconto) |
| `quantidade_minima_criar_aposta` | unsignedTinyInteger | `2` | Quantidade mínima de palpites em um bloco Criar Aposta do vendedor |
| `quantidade_maxima_criar_aposta` | unsignedTinyInteger | `10` | Quantidade máxima de palpites em um bloco Criar Aposta do vendedor |

Os 4 campos ficam na constante nova `UsuariosConfiguracoes::CAMPOS_CRIAR_APOSTA`, incluída em
`CAMPOS` (copiados do colega no vendedor novo, como os demais), e nos casts. Não entram em
`CAMPOS_APOSTA` porque as regras dessa lista vêm do concern `ConfiguracoesAposta`, que não é
alterado; as regras próprias ficam no `UsuariosConfiguracoesRequest`.

## 3. Enums novos

| Enum | Casos (valor gravado) |
|---|---|
| `LiquidacaoCriarAposta` | `Recalcular` (`'Recalcular'`), `AnularBloco` (`'Anular bloco'`) |
| `SituacaoBloco` | `Ativo` (`'Ativo'`), `Anulado` (`'Anulado'`) |

## 4. Validações das configurações (FR-014)

| Campo | Regra | Mensagem |
|---|---|---|
| `criar_aposta_habilitado` | boolean | "O campo criar_aposta_habilitado deve ser verdadeiro ou falso." |
| `desconto_criar_aposta` | numérico, até 2 casas, 0 a 100 | "O desconto do Criar Aposta deve ser de 0 a 100%." |
| `quantidade_minima_criar_aposta` | inteiro, 2 a 20, ≤ máximo (o enviado ou o atual) | "O mínimo de palpites do Criar Aposta deve ser de 2 a 20 e não pode passar do máximo." |
| `quantidade_maxima_criar_aposta` | inteiro, 2 a 20, ≥ mínimo (o enviado ou o atual) | "O máximo de palpites do Criar Aposta deve ser de 2 a 20 e não pode ser menor que o mínimo." |
| `liquidacao_criar_aposta` (só geral) | enum | "A liquidação do Criar Aposta deve ser Recalcular ou Anular bloco." |

Nas configurações do vendedor, o alvo pode ser um alcance com vários vendedores (spec 003): o
mínimo ≤ máximo só é conferido quando os dois vêm no mesmo pedido, como já acontece com
`quantidade_minima_opcoes`. Nas gerais (registro único), compara com o valor atual quando só um
dos dois é enviado.

## 5. Pares redundantes (FR-005a)

Grupos (`MercadosCotacao`), com as categorias de `market_groups.js`:

| Grupo | Códigos |
|---|---|
| `vencedor` | odd1, odd2, odd3 |
| `ambas_equipes` | odd4, odd7 |
| `dupla_chance` | odd10, odd11, odd13 |
| `handicap_asiatico` | odd15–odd17, odd147–odd163 |
| `intervalo_final` | odd18–odd26 |
| `resultado_exato` | odd27–odd60 |
| `gols_mais_menos` | odd115–odd124 |
| `gols_par_impar` | odd125, odd127 |
| `total_gols` | odd85–odd91 |
| `vencedor_ambas_equipes` | odd135–odd140 |
| `resultado_total_gols` | odd141–odd146 |
| `empate_anula_aposta` | odd164, odd165 |
| `escanteios_acima_abaixo`, `escanteios_exatos`, `total_escanteios` | odd166–odd201, odd202–odd219, odd220–odd224 |
| `time_impar_par`, `ambas_marcam_tempos`, `tempo_mais_gols`, `time_tempo_mais_gols` | odd245–odd248, odd251–odd254, odd261–odd263, odd264–odd269 |
| `time_sem_sofrer_gol`, `time_sofre_gol`, `margem_vitoria`, `time_total_gols` | odd270/odd272, odd271/odd273, odd274–odd283, odd284–odd323 |
| `vencedor_1t`, `ambas_equipes_1t`, `dupla_chance_1t`, `handicap_asiatico_1t` | odd129–odd131, odd5/odd8, odd12/odd14, odd225–odd244 |
| `resultado_exato_1t`, `gols_mais_menos_1t`, `gols_par_impar_1t`, `total_gols_1t`, `vencedor_ambas_equipes_1t` | odd61–odd84, odd92–odd96, odd126/odd128, odd97–odd102, odd255–odd260 |
| `vencedor_2t`, `ambas_equipes_2t`, `gols_mais_menos_2t`, `total_gols_2t`, `gols_par_impar_2t` | odd132–odd134, odd6/odd9, odd103–odd110, odd111–odd114, odd249/odd250 |
| `jogador:<nome>` | código `jogador`, pelo nome do jogador |
| outro código | o próprio código |

Pares proibidos no mesmo bloco (a ordem não importa):

| Grupo | Não combina com |
|---|---|
| `vencedor` | `dupla_chance`, `empate_anula_aposta`, `vencedor_ambas_equipes`, `resultado_total_gols`, `margem_vitoria`, `intervalo_final`, `resultado_exato` |
| `ambas_equipes` | `vencedor_ambas_equipes`, `resultado_exato` |
| `gols_mais_menos` | `total_gols`, `resultado_total_gols`, `resultado_exato` |
| `resultado_exato` | também `dupla_chance`, `empate_anula_aposta`, `vencedor_ambas_equipes`, `resultado_total_gols`, `margem_vitoria`, `intervalo_final`, `total_gols`, `gols_par_impar`, `time_total_gols`, `time_sem_sofrer_gol`, `time_sofre_gol` |
| `vencedor_1t` | `dupla_chance_1t`, `vencedor_ambas_equipes_1t`, `resultado_exato_1t`, `intervalo_final` |
| `ambas_equipes_1t` | `vencedor_ambas_equipes_1t`, `resultado_exato_1t` |
| `gols_mais_menos_1t` | `total_gols_1t`, `resultado_exato_1t` |
| `resultado_exato_1t` | também `dupla_chance_1t`, `vencedor_ambas_equipes_1t`, `total_gols_1t`, `gols_par_impar_1t` |
| `gols_mais_menos_2t` | `total_gols_2t` |

## 6. Estruturas em memória

### Perna da aposta (`CalculoBlocos::pernas`)

```text
{ tipo: 'palpite' | 'bloco', confrontos_id, indices: [índices dos palpites], cotacao: '4.81' }
```

- A cotação total, o prêmio e as regras por quantidade (opções, comissão, limite simples/duplo,
  acréscimo) usam a lista de pernas (FR-008, FR-011).

### Cupom (frontend, `localStorage`)

Sem mudança de formato (`versao_formato` 1): o palpite continua
`{ tipo, confronto_id, codigo_cotacao, mercado, cotacao, jogador_id?, opcao_id?, ... }`, e o cupom
pode ter vários palpites do mesmo `tipo: 'pre_jogo'` e `confronto_id`. O bloco é derivado na tela.

## 7. Transições

```text
Bloco:   Ativo ──(cancelar palpite, Anular bloco)──▶ Anulado
         Anulado ──(restaurar palpite)──▶ Ativo
Palpite: Ativo ⇄ Cancelado (como na spec 004; com Anular bloco, todos os do bloco juntos)
```
