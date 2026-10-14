# Implementation Plan: Apuração de resultados e cancelamento de jogos

**Branch**: `009-apuracao-resultados` | **Date**: 2026-10-14 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/009-apuracao-resultados/spec.md`

## Summary

Substitui `fonte:resultados`, `fonte:cancelados` e `apostas:premiadas` do sistema antigo por três
cargas/comandos no padrão do v2 e um comando manual.

**Resultado do jogo**: a carga `confrontos_resultados:importar` grava placar, gols por tempo,
escanteios e situação numa tabela própria (`confrontos_resultados`) e os gols por jogador em
`confrontos_gols`. Uma assinatura (SHA-256) do resultado evita reapurar o que não mudou.

**Apuração**: `App\Support\ResultadosCotacao` reproduz as regras dos 323 códigos do antigo numa
tabela por mercado (regras parametrizadas). O serviço `ApuracaoApostas` decide palpites e apostas
com a aposta travada, uma transação por aposta, só com jogos `Encerrado`. "Empate anula aposta"
vira palpite `Devolvido` pelo cancelamento já existente (`EdicaoApostas`), que recalcula prêmio,
bloco e assinatura. A correção do empate restaura o palpite.

**Pagamento**: `LiquidacaoApostas` movimenta só a diferença entre o valor devido e
`apostas.valor_pago`, sempre por `SaldoClientes`: crédito após a janela, estorno na hora (pode
negativar). Isso torna o pagamento idempotente e cobre correções e ajustes.
`apostas:liquidar_premios` roda a cada minuto para respeitar a janela.

**Cancelados**: `confrontos_cancelados:importar` marca o jogo e, com a opção ligada, cancela pelo
sistema os palpites das apostas ainda sem resultado. Sem palpite restante, a aposta fica
`Vencedor` com prêmio igual ao valor e a devolução segue a liquidação.

**Registro**: toda operação grava uma linha em `operacoes_apuracao`.

**Jogo manual**: o administrador lança o resultado pela rota `PUT confrontos/{confronto}/resultado`
(permissão nova `confrontos.lancar_resultado`). A rota grava pelo mesmo método da carga
automática.

**Grade**: a carga de confrontos deixa de voltar para Aguardando um jogo Cancelado, Adiado ou
Encerrado.

**Configurações**: duas colunas em `configuracoes`, com rota `configuracoes/apuracao`.

Decisões em [research.md](research.md).

## Technical Context

**Language/Version**: PHP 8.2 (^8.2)

**Primary Dependencies**: Laravel 12, `bcmath` (já habilitada). **Nenhuma dependência nova.**

**Storage**: MySQL 8.4 local `wssports` (só `migrate`)

**Testing**: nenhum teste automatizado (constituição); validação manual pelo
[quickstart.md](quickstart.md) com o mock do provedor

**Target Platform**: servidor PHP 8.2 com o agendador (`schedule:run`)

**Project Type**: aplicação web Laravel + React (Inertia); esta feature é só backend

**Performance Goals**:

| Meta | Alvo |
|---|---|
| Carga de resultados sem mudanças (milhares de jogos) | < 30 s, sem apurar nenhuma aposta |
| Apuração de um jogo com 1.000 apostas | < 60 s, dentro do intervalo de 5 min |
| Prêmio disponível (SC-006) | janela + até 1 min |

**Constraints**:

- dinheiro sem `float` (bcmath / centavos do `SaldoClientes`);
- uma transação e trava por aposta; falha isolada (FR-040);
- nenhuma transação apagada nem saldo sobrescrito (SC-004);
- o sistema antigo é só leitura;
- mensagens e logs em português, sem a chave do provedor.

**Scale/Scope**:

| Item | Quantidade |
|---|---|
| Tabelas | 3 novas (`confrontos_resultados`, `confrontos_gols`, `operacoes_apuracao`), 4 alteradas |
| Enums | 3 novos, 2 alterados |
| Serviços | 6 novos (`ImportacaoResultados`, `CancelamentoConfrontos`, `ApuracaoApostas`, `LiquidacaoApostas`, `RegistroApuracao`, avaliação em `Support`) |
| Comandos | 4 novos |
| Rotas | 3 novas (`GET`/`PUT /api/configuracoes/apuracao`, `PUT /api/confrontos/{confronto}/resultado`) |
| Permissões | 1 nova (`confrontos.lancar_resultado`, Admin e Supervisor); `configuracoes.editar` já existe |
| Seeders | 1 novo (`ApuracaoSeeder`) |
| Frontend | nenhum arquivo |

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Princípio / Regra | Verificação | Status |
|---|---|---|
| I. `snake_case` | Métodos (`apurar_aposta`, `liquidar`), variáveis, chaves e comandos (`apostas:liquidar_premios`) | ✅ Pass |
| I. Banco em português | `confrontos_resultados`, `confrontos_gols`, `operacoes_apuracao`, `valor_pago`, `motivo_cancelamento` | ✅ Pass |
| I. Prefixo de tabelas | `confrontos_resultados` e `confrontos_gols` (prefixo `confrontos`); `operacoes_apuracao` é tabela principal (liga jogo, aposta e cliente) | ✅ Pass |
| I. Enums | Casos em `PascalCase` português com acento (`DevoluçãoAposta`, `PrêmioPago`); valores com espaço (`'Devolução de aposta'`) | ✅ Pass |
| I. Rotas em kebab-case | `/api/configuracoes/apuracao` | ✅ Pass |
| II. Idioma | Artefatos, comentários, logs e mensagens em português | ✅ Pass |
| III. Componentes React | Sem frontend | ✅ N/A |
| IV. Escopo estrito | Arquivos existentes listados abaixo, confirmados pelo responsável em 2026-10-14 (incluídos `ImportacaoConfrontos`, `Funcao` e `DatabaseSeeder` pelas decisões da análise) | ✅ Pass (confirmado) |
| V. Legibilidade | Revisão ao final de cada tarefa | ✅ Pass |
| VI. Consistência | Cargas com `ExecutarCargaPreJogo` + `ProvedorCotacoes` + `ValidacaoCargaProvedor` (spec 003); configuração no padrão de `configuracoes/criar-aposta`; saldo só por `SaldoClientes`; regras de mercado em `app/Support` como `MercadosCotacao` | ✅ Pass |
| VII. Sistema antigo | Consultado e listado no research; diferenças aprovadas na spec (Clarifications). Divergências de nome × regra (`odd114`, `odd245`/`odd247`) mantidas como no antigo por decisão do responsável (2026-10-14) | ✅ Pass |
| VIII. Fidelidade visual | Sem tela | ✅ N/A |
| IX. Dados fake | Nenhum | ✅ Pass |
| Timestamps e soft delete | 3 tabelas novas com `timestamps()` + `softDeletes()`; gols removidos por soft delete; nenhuma exclusão física | ✅ Pass |
| Comentários de colunas (3.3.0) | Todas as colunas novas e alteradas com `->comment()`, textos no [data-model.md](data-model.md) | ✅ Pass |
| Referências a tabelas inexistentes | Nenhuma: todas as chaves estrangeiras apontam para tabelas existentes | ✅ Pass |
| Paginação ≤ 100 | Sem listagens | ✅ N/A |
| Sem testes | Nenhum arquivo, tarefa ou dependência de teste | ✅ Pass |
| Postman | Coleção regenerada com as 2 rotas | ✅ Pass |
| Novas dependências | Nenhuma | ✅ Pass |

**Resultado do gate**: aprovado.

**Confirmações do responsável (2026-10-14)**: lista de arquivos existentes; reavaliação do
"empate anula aposta"; cancelamento só de apostas `Aguardando`; proteção da situação na carga de
confrontos (R-19); rota do resultado de jogo manual (R-18); medição de desempenho no quickstart.

### Arquivos existentes que serão alterados (Princípio IV)

| Arquivo | Alteração | Motivo |
|---|---|---|
| `app/Services/ProvedorCotacoes.php` | `buscar_resultados()` e `buscar_cancelados()` (`GET`) | FR-001, FR-030, R-14 |
| `app/Services/ValidacaoCargaProvedor.php` | `validar_resultados()` e `validar_cancelados()` | contrato do provedor |
| `config/services.php` | `tempo_limite_resultados` (180) e `tempo_limite_cancelados` (60) em `provedor_cotacoes` | R-14 |
| `app/Services/EdicaoApostas.php` | `cancelar_palpite_pelo_sistema` aceita autor/ip nulos e um motivo, e aplica a regra do bloco (Anular bloco), sem a trava do último palpite; método novo `restaurar_palpite_pelo_sistema`; `recalcular_e_registrar` aceita autor nulo | R-06, R-08, FR-017, FR-033 |
| `app/Services/EncerramentoEspeciais.php` | `decidir_apostas` passa a chamar `ApuracaoApostas` (decide e liquida apostas mistas) | R-09, FR-011 |
| `app/Enums/ResultadoAposta.php` | caso `Devolvido` | FR-006 |
| `app/Enums/OrigemTransacao.php` | caso `DevoluçãoAposta` | R-12 |
| `app/Models/Apostas.php` | casts de `apurada_em`, `valor_pago`, `liquidacao_pendente` | data-model |
| `app/Models/ApostasPalpites.php` | cast de `motivo_cancelamento` | data-model |
| `app/Models/Confrontos.php` | relações `resultado()` e `gols()` | data-model |
| `app/Models/Configuracoes.php` | `fillable` e casts das 2 colunas | R-16 |
| `app/Services/ImportacaoConfrontos.php` | `situacao` no `upsert` preserva `Cancelado`, `Adiado` e `Encerrado` | R-19, FR-044 |
| `app/Enums/Funcao.php` | `confrontos.lancar_resultado` em `PERMISSOES_CONFRONTOS` | R-18, FR-046 |
| `database/seeders/DatabaseSeeder.php` | chama `ApuracaoSeeder` depois do `ApostasSeeder` | R-18 |
| `routes/console.php` | agenda dos 3 comandos | R-14 |
| `routes/api.php` | 3 rotas e `use` dos 2 controllers | contrato |
| `docs/postman/wssports_api.postman_collection.json` | regenerada | constituição |

**Specs anteriores (artefatos coerentes com o código)**

| Arquivo | Alteração |
|---|---|
| `specs/004-apostas/data-model.md` | Nota: `apostas_historico.usuarios_id` e `ip` aceitam nulo (ação do sistema, spec 009); `resultado` ganha `Devolvido` |
| `specs/006-ajustes-tela-principal/spec.md` | Nota em FR-018: a decisão e o pagamento das apostas com especial passam pela apuração da spec 009 |

**Nenhum outro arquivo existente muda**: `SaldoClientes`, `CalculoPremio`, `CalculoBlocos`,
`RolloverClientes`, `ExecutarCargaPreJogo`, `CancelamentoApostas`,
`ComprovanteApostaResource` e o frontend.

**Impacto no banco local**: `php artisan migrate` (3 tabelas novas, colunas com padrão em
`apostas`, `apostas_palpites` e `configuracoes`; `usuarios_id` e `ip` de `apostas_historico`
passam a aceitar nulo). Nenhuma tabela do legado é tocada.

### Re-check pós-design (Phase 1)

[data-model.md](data-model.md), [contracts/provedor.md](contracts/provedor.md),
[contracts/api.md](contracts/api.md) e [contracts/comandos.md](contracts/comandos.md) conferidos:
nomes em português e `snake_case`; tabelas novas com timestamps, soft delete e comentário em todas
as colunas; enums com valores em português; rota em kebab-case; comandos no padrão
`recurso:acao`; nenhuma tarefa de teste nem dependência nova.

**Status: aprovado.**

## Project Structure

### Documentation (this feature)

```text
specs/009-apuracao-resultados/
├── plan.md              # Este arquivo
├── research.md          # Phase 0
├── data-model.md        # Phase 1
├── quickstart.md        # Phase 1: validação manual
├── contracts/
│   ├── provedor.md      # rotas resultados e cancelados
│   ├── api.md           # configuracoes/apuracao
│   └── comandos.md      # comandos e agenda
├── checklists/
│   └── requirements.md
└── tasks.md             # Phase 2 (/speckit-tasks)
```

### Source Code (repository root)

```text
app/
├── Console/Commands/
│   ├── ImportarResultadosCommand.php                    # NOVO  confrontos_resultados:importar
│   ├── ImportarCanceladosCommand.php                    # NOVO  confrontos_cancelados:importar
│   ├── LiquidarPremiosCommand.php                       # NOVO  apostas:liquidar_premios
│   └── ReapurarConfrontoCommand.php                     # NOVO  apostas:reapurar
├── Enums/
│   ├── MotivoCancelamentoPalpite.php                    # NOVO
│   ├── OrigemApuracao.php                               # NOVO
│   ├── TipoOperacaoApuracao.php                         # NOVO
│   ├── Funcao.php                                       # ALTERADO  permissão nova
│   ├── OrigemTransacao.php                              # ALTERADO
│   └── ResultadoAposta.php                              # ALTERADO
├── Http/
│   ├── Controllers/ConfiguracoesApuracaoController.php  # NOVO
│   ├── Controllers/ConfrontosResultadosController.php   # NOVO  R-18
│   ├── Requests/ConfiguracoesApuracaoRequest.php        # NOVO
│   ├── Requests/ConfrontosResultadosRequest.php         # NOVO  R-18
│   ├── Resources/ConfiguracoesApuracaoResource.php      # NOVO
│   └── Resources/ConfrontosResultadosResource.php       # NOVO  R-18
├── Models/
│   ├── ConfrontosResultados.php                         # NOVO
│   ├── ConfrontosGols.php                               # NOVO
│   ├── OperacoesApuracao.php                            # NOVO
│   ├── Apostas.php, ApostasPalpites.php, Confrontos.php, Configuracoes.php   # ALTERADOS
├── Services/
│   ├── ImportacaoResultados.php                         # NOVO  grava resultado/gols e dispara a apuração
│   ├── CancelamentoConfrontos.php                       # NOVO  R-15
│   ├── ApuracaoApostas.php                              # NOVO  R-09
│   ├── LiquidacaoApostas.php                            # NOVO  R-10
│   ├── RegistroApuracao.php                             # NOVO  R-13
│   ├── EdicaoApostas.php                                # ALTERADO
│   ├── EncerramentoEspeciais.php                        # ALTERADO
│   ├── ImportacaoConfrontos.php                         # ALTERADO  R-19
│   ├── ProvedorCotacoes.php                             # ALTERADO
│   └── ValidacaoCargaProvedor.php                       # ALTERADO
└── Support/
    ├── PlacarConfronto.php                              # NOVO  placar só leitura (resultado + gols)
    └── ResultadosCotacao.php                            # NOVO  R-04

database/migrations/
├── 2026_10_14_000001_create_confrontos_resultados_table.php
├── 2026_10_14_000002_create_confrontos_gols_table.php
├── 2026_10_14_000003_create_operacoes_apuracao_table.php
├── 2026_10_14_000004_add_apuracao_apostas_table.php
├── 2026_10_14_000005_add_motivo_cancelamento_apostas_palpites_table.php
├── 2026_10_14_000006_alterar_autor_apostas_historico_table.php
└── 2026_10_14_000007_add_apuracao_configuracoes_table.php

database/seeders/
├── ApuracaoSeeder.php                                    # NOVO  permissão confrontos.lancar_resultado
└── DatabaseSeeder.php                                    # ALTERADO

config/services.php                                       # ALTERADO
routes/console.php, routes/api.php                         # ALTERADOS
docs/postman/wssports_api.postman_collection.json          # ALTERADO (regenerado)
```

**Structure Decision**: backend Laravel único, seguindo as specs 003 (cargas do provedor: comando
fino + serviço + validação + `ExecutarCargaPreJogo`), 007 (saldo só por `SaldoClientes`) e 008
(configuração geral por rota própria com `configuracoes.editar`; tabelas de mercado em
`app/Support`). Sem Policies, sem frontend e sem arquivos em `tests/`.

## Complexity Tracking

| Violação | Por que é necessária | Alternativa mais simples rejeitada porque |
|---|---|---|
| `apostas_historico.usuarios_id` e `ip` passam a aceitar nulo (spec 004) | A apuração roda no agendador, sem usuário (R-08) | Um usuário "sistema" falso em `usuarios` apareceria em listagens, permissões e hierarquia |
| `cancelar_palpite_pelo_sistema` muda de assinatura (spec 006) | Precisa de autor nulo, motivo e regra do bloco | Um segundo método de cancelamento duplicaria o recálculo e o histórico |
| `ImportacaoConfrontos` (spec 003) passa a preservar situações finais | A carga reabriria para apostas um jogo cancelado (FR-044) | Tirar `situacao` da atualização impediria Aguardando ↔ Bloqueado |
