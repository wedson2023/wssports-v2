# Implementation Plan: Criar Aposta (vários palpites do mesmo jogo)

**Branch**: `008-criar-aposta` | **Date**: 2026-10-13 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/008-criar-aposta/spec.md`

## Summary

A aposta passa a aceitar, para um jogo do pré-jogo, vários palpites agrupados num **bloco Criar
Aposta**. O bloco é gravado numa tabela própria (`apostas_blocos`) e entra na aposta como uma
"perna", ao lado de palpites simples de outros jogos.

**Cotação do bloco**: um serviço novo (`CalculoBlocos`) multiplica as cotações já exibidas ao
público (porcentagens, valor fixo e teto, sem mudança no `CalculoCotacoes`) e aplica
`desconto_criar_aposta`, truncando em 2 casas e com mínimo 1,00. O `CalculoPremio` não muda:
recebe as cotações das pernas, então o bloco conta como **um jogo** no acréscimo, na comissão, nos
limites de venda e na quantidade de opções.

**Regras**: `RegrasAposta` confere chave ligada, só pré-jogo, mínimo/máximo e combinações
proibidas (mesmo grupo de mercado ou par redundante), com os grupos e pares numa classe única
(`MercadosCotacao`) que também abastece o cupom.

**Pós-aposta**: a regra de liquidação gravada na aposta (Recalcular ou Anular bloco) é aplicada
pela edição de palpites; a assinatura passa a incluir os blocos (só nas apostas com bloco).

**Configurações**: 5 colunas em `configuracoes` (rota nova `configuracoes/criar-aposta`) e 4 em
`usuarios_configuracoes` (fluxo de hierarquia existente).

**Frontend**: o cupom agrupa os palpites do mesmo jogo, calcula a estimativa com o desconto e envia
`blocos`; o comprovante mostra a linha "Criar Aposta"; tudo com os componentes e estilos atuais.

Decisões em [research.md](research.md).

## Technical Context

**Language/Version**: PHP 8.2 (^8.2); JavaScript (React 18 com Vite)

**Primary Dependencies**: Laravel 12, Inertia, styled-components, `bcmath` (já habilitada).
**Nenhuma dependência nova.**

**Storage**: MySQL 8.4 local `wssports` (só `migrate`); cupom no `localStorage` (formato atual)

**Testing**: nenhum teste automatizado (constituição); validação manual pelo
[quickstart.md](quickstart.md)

**Target Platform**: servidor web PHP 8.2 + worker da fila (decisão do ao vivo) e navegador (PWA)

**Project Type**: aplicação web Laravel + React (Inertia)

**Performance Goals**:

| Meta | Alvo |
|---|---|
| Aposta com bloco de 10 palpites + 10 palpites simples | < 1 s (como a SC-001 da spec 004) |
| Montar o bloco no cupom (SC-001) | < 1 min para 3 palpites |

**Constraints**:

- nenhum valor de dinheiro ou cotação em `float`; cotação do bloco truncada (FR-007);
- grupos e pares só no backend (fonte única), enviados ao cupom pelas props;
- assinaturas das apostas já gravadas continuam válidas (R-11);
- cupom salvo no aparelho continua válido (sem mudar `versao_formato`);
- mensagens em português.

**Scale/Scope**:

| Item | Quantidade |
|---|---|
| Tabelas | 1 nova (`apostas_blocos`), 4 alteradas (colunas novas) |
| Enums | 2 novos |
| Rotas | 2 novas (`GET`/`PUT /api/configuracoes/criar-aposta`); 2 com campos novos |
| Permissões | nenhuma nova (`configuracoes.editar` já existe) |

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Princípio / Regra | Verificação | Status |
|---|---|---|
| I. `snake_case` | Métodos, variáveis, chaves JSON, colunas e chaves de grupo (`dupla_chance`) em `snake_case` | ✅ Pass |
| I. Banco em português | `apostas_blocos`, `desconto_criar_aposta`, `liquidacao_criar_aposta`, `cotacao_vista` | ✅ Pass |
| I. Prefixo de tabelas | `apostas_blocos` (prefixo `apostas`) | ✅ Pass |
| I. Enums | `LiquidacaoCriarAposta` (`AnularBloco` = `'Anular bloco'`), `SituacaoBloco` | ✅ Pass |
| I. Rotas em kebab-case | `/api/configuracoes/criar-aposta` | ✅ Pass |
| I. Frontend | Componentes existentes; funções em `snake_case` português; utilitário novo `utils/bet_builder.js` (inglês `snake_case`) | ✅ Pass |
| II. Idioma | Artefatos e comentários em português | ✅ Pass |
| III. Componentes React | Nenhum componente novo; os alterados mantêm `index.jsx` + `styles.jsx` | ✅ Pass |
| IV. Escopo estrito | Arquivos existentes listados abaixo, confirmados pelo responsável em 2026-10-13 | ✅ Pass (confirmado) |
| V. Legibilidade | Revisão ao final de cada tarefa | ✅ Pass |
| VI. Consistência | Rota de configurações gerais no padrão de `configuracoes/pagamentos` (controller + request + resource, `configuracoes.editar`); campos do vendedor no fluxo de `usuarios-configuracoes`; regras em `RegrasAposta`, cálculo em serviço próprio, como `CalculoPremio` | ✅ Pass |
| VII. Sistema antigo | Consultado: a funcionalidade não existe (registrado no research) | ✅ Pass |
| VIII. Fidelidade visual | Bloco com o visual atual dos palpites (diferença aprovada em 2026-10-13, FR-026) | ✅ Pass |
| IX. Dados fake | Nenhum | ✅ Pass |
| Timestamps e soft delete | `apostas_blocos` com `timestamps()` + `softDeletes()`; nenhuma exclusão física | ✅ Pass |
| **Comentários de colunas (3.3.0)** | Todas as colunas novas com `->comment()` em português, textos em [data-model.md](data-model.md) | ✅ Pass |
| Paginação ≤ 100 | Sem listagens novas | ✅ Pass |
| Sem testes | Nenhum arquivo, tarefa ou dependência de teste | ✅ Pass |
| Postman | Coleção regenerada na mesma entrega | ✅ Pass |
| Novas dependências | Nenhuma | ✅ Pass |

**Resultado do gate**: aprovado.

**Confirmações do responsável (2026-10-13)**: lista de arquivos existentes abaixo, troca do índice
único de `apostas_palpites` e a regra do cupom também nas 4 cotações da listagem (R-12).

### Arquivos existentes que serão alterados (Princípio IV)

**Backend**

| Arquivo | Alteração | Motivo |
|---|---|---|
| `app/Services/CriacaoApostas.php` | `preparar` monta as pernas (`CalculoBlocos`), confere blocos e conta pernas; `gravar`/`gravar_palpites` gravam os blocos e o desconto/liquidação; `valor_apostado` soma cada aposta uma vez por jogo; `garantir_limite_por_confronto` sem ids repetidos | FR-001 a FR-012, FR-015, R-08 |
| `app/Services/RegrasAposta.php` | `conferir_palpites` aceita jogo repetido só como bloco; método novo `conferir_blocos` (chave, ao vivo, mínimo/máximo, grupos e pares) | FR-001 a FR-005a, FR-013, FR-016 |
| `app/Services/ConferenciaCotacoes.php` | Compara também a cotação vista de cada bloco (item `bloco: true`) | FR-009, R-09 |
| `app/Services/DecisaoAoVivo.php` | Recalcula com as pernas e o desconto gravado; atualiza a cotação do bloco na aceitação | FR-008, R-07 |
| `app/Services/EdicaoApostas.php` | Liquidação Recalcular / Anular bloco ao cancelar e restaurar; recálculo pelas pernas | FR-018 a FR-021, R-10 |
| `app/Services/ValidacaoCodigos.php` | Pedidos com `blocos`; chave dos palpites por confronto + código + jogador; simulação com os blocos do vendedor | FR-013, R-07 |
| `app/Services/AssinaturaApostas.php` | Inclui os blocos só quando a aposta tem bloco | R-11 |
| `app/Support/Apostador.php` | Métodos das configurações do Criar Aposta por público | R-06 |
| `app/Models/Apostas.php` | Relação `blocos()` e casts das colunas novas | data-model |
| `app/Models/ApostasPalpites.php` | Relação `bloco()` | data-model |
| `app/Models/Configuracoes.php` | `fillable` e casts das 5 colunas | FR-013 |
| `app/Models/UsuariosConfiguracoes.php` | 4 campos em `CAMPOS_APOSTA` e casts | FR-013 |
| `app/Http/Requests/ApostasRequest.php` | Campo `blocos` e mensagens | R-03 |
| `app/Http/Requests/UsuariosConfiguracoesRequest.php` | Regras e mensagens dos 4 campos | FR-014 |
| `app/Http/Resources/UsuariosConfiguracoesResource.php` | Devolver os 4 campos | FR-013 |
| `app/Http/Resources/ComprovanteApostaResource.php` | `quantidade_jogos`, `blocos` e `apostas_blocos_id` | FR-025 |
| `app/Http/Resources/SimulacaoApostaResource.php` | `blocos` e `apostas_blocos_id` | FR-025 |
| `app/Http/Controllers/PaginaInicialController.php` | `configuracoes.criar_aposta` nas props | R-05, R-12 |
| `routes/api.php` | 2 rotas e `use` do controller | contrato |
| `docs/postman/wssports_api.postman_collection.json` | Regenerada | constituição |

**Frontend**

| Arquivo | Alteração | Motivo |
|---|---|---|
| `resources/js/hooks/useCupom.js` | Regra de escolha com bloco (R-12); `remover_palpite` por palpite; `blocos` no envio; 409 do bloco; totais pelas pernas | FR-023, FR-024 |
| `resources/js/utils/money.js` | `cotacao_bloco` (truncada, mínimo 1,00) e prêmio pelas pernas | FR-006, FR-007 |
| `resources/js/components/MatchDetailsModal/index.jsx` | Seleção de vários palpites do jogo | FR-023 |
| `resources/js/pages/Home/index.jsx` | `selecao_do_jogo` devolve a lista de códigos; simulação carrega os blocos | FR-023 |
| `resources/js/components/MatchList/index.jsx`, `MatchCard/index.jsx`, `OddGroup/index.jsx` | `codigo_selecionado` vira `codigos_selecionados` (lista) | FR-023 |
| `resources/js/components/BetSlip/index.jsx`, `BetSlipItem/index.jsx` | Palpites do jogo juntos e a linha "Criar Aposta", com os estilos atuais | FR-023, FR-026 |
| `resources/js/components/BetSlipSummary/index.jsx`, `BetSlipActions/index.jsx` | Contagem de jogos (pernas) | FR-011 |
| `resources/js/components/TicketModal/index.jsx`, `resources/js/utils/print.js`, `resources/js/utils/thermal.js` | Linha "Criar Aposta" e `quantidade_jogos` | FR-025 |

**Specs anteriores (artefatos coerentes com o código)**

| Arquivo | Alteração |
|---|---|
| `specs/004-apostas/spec.md`, `contracts/api.md`, `data-model.md` | Nota em FR-015 (jogo repetido permitido como bloco, spec 008), no corpo do pedido (`blocos`), no comprovante e no índice de `apostas_palpites` |

**Nenhum outro arquivo existente muda**: `CalculoCotacoes`, `CalculoPremio`, `DetalheConfrontos`,
`RolloverClientes` (recebe a quantidade de pernas pelo `confirmar`), `EncerramentoEspeciais`,
`market_groups.js` e os controllers de apostas.

**Impacto no banco local**: `php artisan migrate` (1 tabela nova, colunas com padrão em 4 tabelas e
troca do índice único de `apostas_palpites`). Nenhuma tabela do legado é tocada.

### Re-check pós-design (Phase 1)

[data-model.md](data-model.md), [contracts/api.md](contracts/api.md) e
[contracts/paginas.md](contracts/paginas.md) conferidos: nomes em português e `snake_case`;
`apostas_blocos` com timestamps, soft delete e comentários em todas as colunas; enums com valores em
português; rota em kebab-case; nenhum componente novo; nenhuma tarefa de teste nem dependência
nova.

**Status: aprovado.**

## Project Structure

### Documentation (this feature)

```text
specs/008-criar-aposta/
├── plan.md              # Este arquivo
├── research.md          # Phase 0
├── data-model.md        # Phase 1
├── quickstart.md        # Phase 1: validação manual
├── contracts/
│   ├── api.md           # Phase 1: rotas e formatos
│   └── paginas.md       # Phase 1: telas
├── checklists/
│   └── requirements.md
└── tasks.md             # Phase 2 (/speckit-tasks)
```

### Source Code (repository root)

```text
app/
├── Enums/
│   ├── LiquidacaoCriarAposta.php                       # NOVO
│   └── SituacaoBloco.php                               # NOVO
├── Http/
│   ├── Controllers/
│   │   ├── ConfiguracoesCriarApostaController.php      # NOVO
│   │   └── PaginaInicialController.php                 # ALTERADO
│   ├── Requests/
│   │   ├── ApostasRequest.php                          # ALTERADO
│   │   ├── ConfiguracoesCriarApostaRequest.php         # NOVO
│   │   └── UsuariosConfiguracoesRequest.php            # ALTERADO
│   └── Resources/
│       ├── ComprovanteApostaResource.php               # ALTERADO
│       ├── ConfiguracoesCriarApostaResource.php        # NOVO
│       ├── SimulacaoApostaResource.php                 # ALTERADO
│       └── UsuariosConfiguracoesResource.php           # ALTERADO
├── Models/
│   ├── Apostas.php                                     # ALTERADO
│   ├── ApostasBlocos.php                               # NOVO
│   ├── ApostasPalpites.php                             # ALTERADO
│   ├── Configuracoes.php                               # ALTERADO
│   └── UsuariosConfiguracoes.php                       # ALTERADO
├── Services/
│   ├── AssinaturaApostas.php                           # ALTERADO
│   ├── CalculoBlocos.php                               # NOVO (R-04)
│   ├── ConferenciaCotacoes.php                         # ALTERADO
│   ├── CriacaoApostas.php                              # ALTERADO
│   ├── DecisaoAoVivo.php                               # ALTERADO
│   ├── EdicaoApostas.php                               # ALTERADO
│   ├── RegrasAposta.php                                # ALTERADO
│   └── ValidacaoCodigos.php                            # ALTERADO
└── Support/
    ├── Apostador.php                                   # ALTERADO
    └── MercadosCotacao.php                             # NOVO (R-05)

database/migrations/
├── 2026_10_13_000001_create_apostas_blocos_table.php                     # NOVO
├── 2026_10_13_000002_add_criar_aposta_apostas_palpites_table.php        # NOVO (FK + índice)
├── 2026_10_13_000003_add_criar_aposta_apostas_table.php                 # NOVO
├── 2026_10_13_000004_add_criar_aposta_configuracoes_table.php           # NOVO
└── 2026_10_13_000005_add_criar_aposta_usuarios_configuracoes_table.php  # NOVO

resources/js/
├── components/  (BetSlip, BetSlipItem, BetSlipSummary, BetSlipActions, MatchDetailsModal,
│                 MatchList, MatchCard, OddGroup, TicketModal)          # ALTERADOS
├── hooks/useCupom.js                                                    # ALTERADO
├── pages/Home/index.jsx                                                 # ALTERADO
└── utils/
    ├── bet_builder.js                                                   # NOVO: pernas e combinações no cupom
    ├── money.js                                                         # ALTERADO
    ├── print.js                                                         # ALTERADO
    └── thermal.js                                                       # ALTERADO

routes/api.php                                                           # ALTERADO
docs/postman/wssports_api.postman_collection.json                       # ALTERADO (regenerado)
```

**Structure Decision**: aplicação Laravel + React única, seguindo as specs 004 a 007: regras em
`RegrasAposta`, cálculo em serviço próprio (`CalculoBlocos`, como `CalculoPremio`), tabelas de
mercado em `app/Support` (como `NomesCotacoes`), configurações gerais por rota própria com
`configuracoes.editar` e componentes React existentes com `index.jsx` + `styles.jsx`. Sem Policies e
sem arquivos em `tests/`.

## Complexity Tracking

| Violação | Por que é necessária | Alternativa mais simples rejeitada porque |
|---|---|---|
| Troca do índice único `(apostas_id, confrontos_id)` de `apostas_palpites` (spec 004) | O bloco tem vários palpites do mesmo jogo | Manter o único impediria o bloco; a unicidade passa para as regras (FR-001, FR-005) |
| Grupos de mercado no backend além de `market_groups.js` | As combinações proibidas precisam ser conferidas no servidor | Conferir só no cupom deixaria a regra contornável pela API; o frontend recebe os grupos do backend para não duplicar a regra |
