# Implementation Plan: Apostas (criação, validação de código e cancelamento)

**Branch**: `004-apostas` | **Date**: 2026-10-07 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/004-apostas/spec.md`

## Summary

Um serviço central de aposta (`CriacaoApostas`) recebe os palpites, recalcula tudo no servidor e
grava a aposta nas três formas:

- **Vendedor** (painel): grava `Ativa` (pré-jogo) ou `Em análise` (com ao vivo), abate limites de
  venda e calcula a comissão.
- **Cliente** (área do cliente): mesma regra, paga com uma carteira só (saldo real primeiro, depois
  bônus de esportes) e soma no rollover.
- **Visitante** (público): só pré-jogo, grava `Pendente` com código de 8 caracteres; o vendedor
  consulta a simulação e valida o código, que vira `Ativa` com as regras dele.

**Cotação**: `CalculoCotacoes` (spec 003) passa a calcular qualquer código e os jogadores; as regras
de exibição da listagem viram um serviço compartilhado (`RegrasExibicao`), para que o apostável seja
igual ao exibido. Cotação diferente da vista → 409 com o novo prêmio para confirmar; cotação
indisponível → 422, nunca 1,00.

**Ao vivo**: delay único por job atrasado na fila `database`; no fim, o job compara a fotografia do
jogo no envio e na decisão e aceita ou recusa sozinho. Um comando recusa análises presas.

**Detalhe do confronto**: duas rotas públicas novas (pré-jogo e ao vivo) devolvem todas as
cotações e os jogadores de um jogo, com o mesmo cálculo e as mesmas regras de exibição; a listagem
continua com as 4 cotações principais.

**Pós-aposta**: cancelamento (vendedor no tempo; hierarquia com permissão), edição de palpites com
histórico e recálculo do prêmio, comprovante completo com assinatura HMAC.

**Concorrência**: transação com bloqueios em ordem fixa (aposta → confrontos → vendedor/cliente →
rollovers) e chave de idempotência.

Decisões em [research.md](research.md).

## Technical Context

**Language/Version**: PHP 8.2 (^8.2)

**Primary Dependencies**: Laravel 12 (fila `database`, Scheduler, `RateLimiter`, Eloquent),
`php-open-source-saver/jwt-auth` e `spatie/laravel-permission` (já instalados); extensão `bcmath`
do PHP (já habilitada). **Nenhuma dependência nova.**

**Storage**: MySQL 8.4 local `wssports` (compartilhado com o legado; só `migrate` e seeders); fila e
cache `database` (já configurados)

**Testing**: nenhum teste automatizado (constituição); validação manual pelo
[quickstart.md](quickstart.md)

**Target Platform**: servidor web com PHP 8.2 + agendador (`schedule:run` a cada minuto) + worker
da fila (`queue:work --queue=apostas,default`)

**Project Type**: API web Laravel (frontend fora do escopo)

**Performance Goals**:

| Meta | Alvo |
|---|---|
| Aposta do pré-jogo com 20 palpites confirmada (SC-001) | < 1 s |
| Decisão do ao vivo após o fim do delay (SC-002) | ≤ 5 s |
| 50 apostas simultâneas do mesmo cliente sem passar do saldo (SC-007) | 100% |

**Constraints**:

- nenhum valor de dinheiro em `float`; prêmio truncado em centavos (R-06);
- código de cotação só da lista fechada, nunca em SQL montado por texto (FR-006);
- nada de `sleep` na requisição (R-01);
- bloqueios sempre na mesma ordem (R-07);
- respostas sem porcentagens, cotação original, IPs nem comissão para terceiros (FR-054);
- limites de tentativas de R-10; mensagens em português.

**Scale/Scope**:

| Item | Quantidade |
|---|---|
| Tabelas | 5 novas, 6 alteradas (colunas novas) |
| Enums | 9 novos |
| Permissões | 6 novas (5 de apostas e `confrontos.alterar_limite`) |
| Rotas | 16 novas (5 públicas, 2 da área do cliente, 9 do painel); 3 existentes com campos novos |
| Comandos agendados | 2 |
| Jobs | 1 (`DecidirApostaAoVivo`) |

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Princípio / Regra | Verificação | Status |
|---|---|---|
| I. `snake_case` | Métodos, variáveis, chaves JSON e colunas em `snake_case`; métodos do framework mantêm o nome | ✅ Pass |
| I. Banco em português | `apostas`, `apostas_palpites`, `cotacao_vista`, `valor_acrescido`… | ✅ Pass |
| I. Prefixo de tabelas | `apostas_palpites`, `apostas_historico`, `apostas_rollovers` (prefixo `apostas`); `clientes_rollovers` (prefixo `clientes`) | ✅ Pass |
| I. Permissões `<recurso>.<acao>` | `apostas.criar`, `apostas.validar`, `apostas.cancelar`, `apostas.cancelar_iniciada`, `apostas.editar`, `confrontos.alterar_limite` | ✅ Pass |
| I. Enums | Casos em `PascalCase` português (`EmAnálise`, `PréJogo`, `DepoisDeAmanhã`); valores com acento e espaço (`'Em análise'`) | ✅ Pass |
| I. Siglas consagradas | `ip`, `user_agent` | ✅ Pass |
| I. Rotas em kebab-case | `/publico/apostas`, `/apostas/pendentes/{codigo}/validar`, `/confrontos-ao-vivo/{id}/limite` | ✅ Pass |
| II. Idioma | Artefatos e comentários em português | ✅ Pass |
| III. Componentes React | Sem frontend | ➖ N/A |
| IV. Escopo estrito | Arquivos existentes listados abaixo, todos decididos na spec ou autorizados pelo responsável em 2026-10-07 | ✅ Pass (confirmado) |
| V. Legibilidade | Revisão ao final de cada tarefa | ✅ Pass |
| VI. Consistência entre recursos | Controllers com `HasMiddleware` + `GarantirPermissaoCliente`; FormRequests com mensagens em português; Resources; serviços em `app/Services`; permissões no `Funcao`; limite de tentativas no FormRequest (padrão da spec 002). Desvio: resposta **409** para "cotação alterada, confirme" (padrão novo), autorizado em 2026-10-07 | ✅ Pass (confirmado) |
| Timestamps e soft delete | As 5 tabelas novas com `timestamps()` + `softDeletes()`; nenhuma exclusão física | ✅ Pass |
| Colunas sem FK | `clientes_transacoes.referencia_id` continua sem FK (aponta para promoções e apostas), como já registrado na spec 002 | ✅ Pass |
| Paginação ≤ 100 | Sem listagens novas; a consulta de vários códigos é limitada a 50 | ✅ Pass |
| Sem testes | Nenhum arquivo, tarefa ou dependência de teste | ✅ Pass |
| Postman | Coleção regenerada na mesma entrega | ✅ Pass |
| Novas dependências | Nenhuma (`bcmath` já habilitada) | ✅ Pass |

**Resultado do gate**: aprovado.

**Confirmações do responsável (2026-10-07)**:

- Alteração de `CalculoCotacoes`, extração das regras de exibição de `ListagemConfrontos` para
  `RegrasExibicao`, `EstornarPromocao`, `Funcao`, rotas, agendamentos e seeders: autorizadas.
- Atualização dos artefatos das specs 002 e 003: autorizada.
- Resposta 409 para "cotação alterada, confirme": autorizada.
- Detalhe do confronto com todas as cotações numa rota pública separada da listagem: decisão
  delegada e registrada nas Clarifications (FR-065a, FR-065b).

### Arquivos existentes que serão alterados (Princípio IV)

| Arquivo | Alteração | Motivo | Situação |
|---|---|---|---|
| `app/Services/CalculoCotacoes.php` | `ajustar()` aceita a lista de códigos (padrão = os 4 da listagem) e novo `ajustar_jogadores()` | FR-007, R-03 | autorizado (2026-10-07) |
| `app/Services/ListagemConfrontos.php` | Usar `RegrasExibicao` (métodos privados de exibição saem daqui); período de jogos, data de travamento e exclusão do jogo que está no ao vivo do pré-jogo | FR-017, FR-065, R-04 | comportamento decidido na spec; extração autorizada (2026-10-07) |
| `app/Services/ConfiguracoesVendedores.php` | `criar_para` não copia os limites de venda do colega (nascem com o padrão da coluna) | FR-059 | autorizado (2026-10-08) |
| `app/Services/CadastroClientes.php` | Criar o registro de `clientes_rollovers` ao creditar o bônus de Primeiro cadastro com rollover > 0 | FR-047 | decidido na spec |
| `app/Jobs/EstornarPromocao.php` | Marcar `cancelado_em` nos rollovers pendentes da promoção estornada | R-13 | autorizado (2026-10-07) |
| `app/Enums/Funcao.php` | `PERMISSOES_APOSTAS`; `confrontos.alterar_limite` em `PERMISSOES_CONFRONTOS`; `pode_usar()` e `permissoes_padrao()` (o Vendedor passa a receber as de apostas) | FR-003, R-15 | autorizado (2026-10-07) |
| `app/Models/UsuariosConfiguracoes.php` | `CAMPOS`, `fillable` e `casts` das colunas novas | FR-059 | decidido na spec |
| `app/Models/ClientesConfiguracoes.php` | `fillable` e `casts` das colunas novas | FR-060 | decidido na spec |
| `app/Models/VisitantesConfiguracoes.php` | `CAMPOS`, `fillable` e `casts` das colunas novas | FR-061 | decidido na spec |
| `app/Models/Configuracoes.php` | `fillable` de `nome_sistema` e `mensagem_bilhete` | FR-062 | decidido na spec |
| `app/Models/Confrontos.php`, `app/Models/ConfrontosAoVivo.php` | `limite_valor_apostado` em `fillable`/`casts` | FR-063 | decidido na spec |
| `app/Http/Requests/UsuariosConfiguracoesRequest.php` | Regras e mensagens dos campos novos (FR-064) | FR-059 | decidido na spec |
| `app/Http/Requests/ClientesConfiguracoesRequest.php` | Idem | FR-060 | decidido na spec |
| `app/Http/Requests/VisitantesConfiguracoesRequest.php` | Idem | FR-061 | decidido na spec |
| `app/Http/Resources/UsuariosConfiguracoesResource.php` | Devolver os campos novos (o `ClientesConfiguracoesResource` não muda: já devolve `ClientesConfiguracoes::CAMPOS`) | FR-059, FR-060 | decidido na spec |
| `routes/api.php` | 16 rotas novas e `use` dos controllers | contrato | autorizado (2026-10-07) |
| `routes/console.php` | Agendar `apostas:expirar_pendentes` e `apostas:recusar_analises_presas` | R-18 | autorizado (2026-10-07) |
| `database/seeders/PapeisPermissoesSeeder.php` | Criar também as permissões de apostas | R-15 | autorizado (2026-10-07) |
| `database/seeders/DatabaseSeeder.php` | `$this->call(ApostasSeeder::class)` ao final | R-15 | autorizado (2026-10-07) |
| `docs/postman/wssports_api.postman_collection.json` | Regenerada (pasta "Apostas") | constituição | obrigatório |
| `specs/002-clientes/*` e `specs/003-confrontos/*` | Registrar o rollover no cadastro (002) e as regras novas da listagem e o limite por confronto (003) | constituição (artefatos coerentes com o código) | autorizado (2026-10-07) |

**Nenhum outro arquivo existente muda**: `SaldoClientes` (usado como está), `IdentificacaoPublico`,
`Publico`, `AlcanceHierarquia`, `VisitantesConfiguracoesController`
(usa `VisitantesConfiguracoes::CAMPOS`), middlewares, `config/*`, cargas do
provedor (o upsert não inclui `limite_valor_apostado`) e os controllers de confrontos (o limite usa
um controller novo).

**Impacto no banco local**: `php artisan migrate` (5 tabelas novas e colunas novas em 6 tabelas, com
padrão), `db:seed --class=PapeisPermissoesSeeder` e `db:seed --class=ApostasSeeder` (permissões dos
usuários existentes). Nenhuma tabela do legado é tocada.

### Re-check pós-design (Phase 1)

O [data-model.md](data-model.md) e o [contracts/api.md](contracts/api.md) foram conferidos:

- nomes em português e `snake_case`; prefixos `apostas_*` e `clientes_rollovers`;
- 5 tabelas novas com timestamps e soft delete; colunas novas com valor padrão;
- 9 enums com valores em português;
- rotas em kebab-case; nenhuma listagem acima de 100;
- nenhuma tarefa de teste e nenhuma dependência nova.

**Status: aprovado.**

## Project Structure

### Documentation (this feature)

```text
specs/004-apostas/
├── plan.md              # Este arquivo
├── research.md          # Phase 0
├── data-model.md        # Phase 1
├── quickstart.md        # Phase 1: validação manual
├── contracts/
│   └── api.md           # Phase 1: rotas
├── checklists/
│   └── requirements.md
└── tasks.md             # Phase 2 (/speckit-tasks)
```

### Source Code (repository root)

```text
app/
├── Console/
│   └── Commands/
│       ├── ExpirarApostasPendentesCommand.php         # NOVO: apostas:expirar_pendentes
│       └── RecusarAnalisesPresasCommand.php           # NOVO: apostas:recusar_analises_presas
├── Enums/
│   ├── AceitarAlteracoes.php                          # NOVO
│   ├── AcaoHistoricoAposta.php                        # NOVO
│   ├── FormaPagamento.php                             # NOVO
│   ├── Funcao.php                                     # ALTERADO
│   ├── PeriodoJogos.php                               # NOVO
│   ├── ResultadoAposta.php                            # NOVO
│   ├── SituacaoAposta.php                             # NOVO
│   ├── SituacaoPalpite.php                            # NOVO
│   ├── TipoAposta.php                                 # NOVO
│   └── TipoRollover.php                               # NOVO
├── Exceptions/
│   ├── CotacoesAlteradasException.php                 # NOVO: vira 409
│   └── RegraApostaException.php                       # NOVO: vira 422 (com indisponiveis)
├── Http/
│   ├── Controllers/
│   │   ├── ApostasController.php                      # NOVO: vendedor cria e acompanha
│   │   ├── ApostasPalpitesController.php              # NOVO: cancelar/restaurar palpite
│   │   ├── AreaClienteApostasController.php           # NOVO: cliente cria e acompanha
│   │   ├── CancelamentoApostasController.php          # NOVO
│   │   ├── Concerns/
│   │   │   └── RespostasApostas.php                   # NOVO: limite de tentativas e status da criação
│   │   ├── ConfrontosLimitesController.php            # NOVO: limite por confronto
│   │   ├── PublicoApostasController.php               # NOVO: visitante, comprovante, consulta
│   │   ├── PublicoConfrontosDetalheController.php     # NOVO: detalhe com todas as cotações
│   │   └── ValidacaoApostasController.php             # NOVO: simulação e validação
│   ├── Requests/
│   │   ├── ApostasRequest.php                         # NOVO: corpo da aposta
│   │   ├── ApostaVisitanteRequest.php                 # NOVO: + limite por IP
│   │   ├── Concerns/
│   │   │   └── ConfiguracoesAposta.php                # NOVO: regras FR-064 dos 3 requests de configuração
│   │   ├── ConsultarApostasRequest.php                # NOVO: até 50 códigos
│   │   ├── LimiteConfrontoRequest.php                 # NOVO
│   │   ├── ClientesConfiguracoesRequest.php           # ALTERADO
│   │   ├── UsuariosConfiguracoesRequest.php           # ALTERADO
│   │   └── VisitantesConfiguracoesRequest.php         # ALTERADO
│   └── Resources/
│       ├── ComprovanteApostaResource.php              # NOVO
│       ├── SimulacaoApostaResource.php                # NOVO
│       └── UsuariosConfiguracoesResource.php          # ALTERADO
├── Jobs/
│   ├── DecidirApostaAoVivo.php                        # NOVO (R-01, R-02)
│   └── EstornarPromocao.php                           # ALTERADO
├── Models/
│   ├── Apostas.php                                    # NOVO
│   ├── ApostasHistorico.php                           # NOVO
│   ├── ApostasPalpites.php                            # NOVO
│   ├── ApostasRollovers.php                           # NOVO
│   ├── ClientesRollovers.php                          # NOVO
│   ├── ClientesConfiguracoes.php                      # ALTERADO
│   ├── Configuracoes.php                              # ALTERADO
│   ├── Confrontos.php                                 # ALTERADO
│   ├── ConfrontosAoVivo.php                           # ALTERADO
│   ├── UsuariosConfiguracoes.php                      # ALTERADO
│   └── VisitantesConfiguracoes.php                    # ALTERADO
├── Services/
│   ├── AlcanceApostas.php                             # NOVO: quem cancela/edita qual aposta (R-15)
│   ├── AssinaturaApostas.php                          # NOVO: HMAC (R-11)
│   ├── CalculoCotacoes.php                            # ALTERADO (R-03)
│   ├── CalculoPremio.php                              # NOVO: bcmath, multiplicador, acréscimo (R-06)
│   ├── CadastroClientes.php                           # ALTERADO (R-13)
│   ├── CancelamentoApostas.php                        # NOVO
│   ├── ConferenciaCotacoes.php                        # NOVO: vista × atual (R-16)
│   ├── CriacaoApostas.php                             # NOVO: fluxo comum das 3 formas
│   ├── DecisaoAoVivo.php                              # NOVO: regras do fim do delay (R-02)
│   ├── DetalheConfrontos.php                          # NOVO: todas as cotações e jogadores (R-03)
│   ├── EdicaoApostas.php                              # NOVO: cancelar/restaurar palpite (R-19)
│   ├── EscolhaCarteira.php                            # NOVO (R-14)
│   ├── ListagemConfrontos.php                         # ALTERADO (R-04)
│   ├── RegrasAposta.php                               # NOVO: FR-014 a FR-024 por público
│   ├── RegrasExibicao.php                             # NOVO: compartilhado com a listagem (R-04)
│   ├── RolloverClientes.php                           # NOVO: somar e desfazer (R-13)
│   └── ValidacaoCodigos.php                           # NOVO: simulação e validação
└── Support/
    ├── Apostador.php                                  # NOVO: apostador e as regras do seu público
    ├── CodigoAposta.php                               # NOVO: gerador de 8 caracteres (R-09)
    ├── FusoSistema.php                                # NOVO: -03:00 (R-05)
    └── NomesCotacoes.php                              # NOVO: nomes dos mercados (R-17)

database/
├── migrations/
│   ├── 2026_10_07_000001_create_apostas_table.php                        # NOVO
│   ├── 2026_10_07_000002_create_apostas_palpites_table.php               # NOVO
│   ├── 2026_10_07_000003_create_apostas_historico_table.php              # NOVO
│   ├── 2026_10_07_000004_create_clientes_rollovers_table.php             # NOVO
│   ├── 2026_10_07_000005_create_apostas_rollovers_table.php              # NOVO
│   ├── 2026_10_07_000006_add_apostas_usuarios_configuracoes_table.php    # NOVO
│   ├── 2026_10_07_000007_add_apostas_clientes_configuracoes_table.php    # NOVO
│   ├── 2026_10_07_000008_add_apostas_visitantes_configuracoes_table.php  # NOVO
│   ├── 2026_10_07_000009_add_bilhete_configuracoes_table.php             # NOVO
│   └── 2026_10_07_000010_add_limite_valor_apostado_confrontos_table.php  # NOVO (confrontos e ao vivo)
└── seeders/
    ├── ApostasSeeder.php                              # NOVO: permissões dos usuários existentes
    ├── DatabaseSeeder.php                             # ALTERADO
    └── PapeisPermissoesSeeder.php                     # ALTERADO

docs/postman/wssports_api.postman_collection.json     # ALTERADO (regenerado)

routes/
├── api.php                                            # ALTERADO
└── console.php                                        # ALTERADO
```

**Structure Decision**: aplicação Laravel única, seguindo os padrões das specs 001 a 003:

- FormRequests com mensagens em português, Resources e controllers finos com `HasMiddleware`;
- permissões checadas pelo trait `GarantirPermissaoCliente` (`Funcao::usuario_pode`) e alcance em
  `AlcanceApostas`;
- regras de negócio em `app/Services`, movimentação de saldo sempre pelo `SaldoClientes`;
- comandos agendados em `app/Console/Commands` e o job em `app/Jobs`.

Sem Policies e sem arquivos em `tests/`.

## Complexity Tracking

| Violação | Por que é necessária | Alternativa mais simples rejeitada porque |
|---|---|---|
| Resposta 409 para "cotação alterada, confirme" (padrão novo; as specs anteriores só usam 4xx de erro) | Separa "precisa da confirmação do apostador" de "erro de regra" (422) sem o front interpretar a mensagem | 422 com um campo `alteracoes` misturaria confirmação e erro na mesma resposta |
| Alteração de serviços da spec 003 (`CalculoCotacoes`, `ListagemConfrontos`) | FR-007 e FR-008 exigem a mesma cotação e as mesmas regras de exibição na aposta e na listagem | Duplicar o cálculo e as regras num serviço de apostas divergiria com o tempo |
