# Implementation Plan: Confrontos (jogos e cotações do provedor)

**Branch**: `003-confrontos` | **Date**: 2026-10-01 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/003-confrontos/spec.md`

## Summary

Três comandos agendados trazem os jogos do provedor:

- **`confrontos:importar`** (a cada 5 min): numa única chamada, grava campeonatos, confrontos, as
  323 cotações (numa coluna JSON) e jogadores, por upsert em lote numa transação. Também sorteia
  `odd4`/`odd7` quando vêm zeradas e passa as regras de um campeonato para o novo quando ele chega
  com outro código e o mesmo nome e país.
- **`confrontos_ao_vivo:importar`** (a cada 5 s): atualiza os jogos em andamento numa tabela
  própria. A trava é calculada na leitura pela data da última atualização, então funciona mesmo
  com o comando parado.
- **`confrontos_ao_vivo:conferir`** (a cada minuto): compara o minuto com um segundo provedor e liga
  ou desliga a trava geral.

**Listagem pública:** uma rota sem login, que alterna pré-jogo e ao vivo, mostra hoje, amanhã ou
depois de amanhã no fuso pedido e identifica visitante, cliente ou usuário do painel pelo token. A
cotação exibida é calculada com as porcentagens do público, do campeonato e o valor fixo do
confronto, com o teto e, no ao vivo, a cotação máxima.

**Painel:**

- rotas para alterar porcentagens, cotação de confronto e teto;
- cadastro manual de campeonatos e confrontos;
- ativar, favoritar e não permitidos (alvos Clientes, Vendedores e Todos, com cliente específico);
- configurações dos vendedores (alteração em massa por supervisão, gerente ou vendedor) e dos
  visitantes;
- 21 permissões no `Funcao`.

**Mudanças em código já implementado:** remove a tabela padrão de configurações dos clientes
(spec 002) e cria a configuração do vendedor no cadastro de usuários (spec 001).

Decisões em [research.md](research.md).

## Technical Context

**Language/Version**: PHP 8.2 (^8.2)

**Primary Dependencies**: Laravel 12 (Query Builder `upsert`, `Http`, Scheduler com tarefas de
segundos), `php-open-source-saver/jwt-auth` e `spatie/laravel-permission` (já instalados).
**Nenhuma dependência nova.**

**Storage**: MySQL 8.4 local `wssports` (compartilhado com o legado; só `migrate` e seeders, R-20);
colunas `json` com valor padrão; cache `database` para as travas de sobreposição do agendador e o
`RateLimiter`

**Testing**: nenhum teste automatizado (constituição); validação manual pelo
[quickstart.md](quickstart.md), com o provedor simulado num mock server do Postman

**Target Platform**: servidor web com PHP 8.2 + agendador (`schedule:run` no cron a cada minuto, ou
`schedule:work`)

**Project Type**: API web Laravel (frontend fora do escopo)

**Performance Goals**:

| Meta | Alvo | Medido |
|---|---|---|
| Carga completa do pré-jogo (SC-001) | < 5 s | < 1 s no teste, R-01 |
| Ao vivo refletido na listagem (SC-005) | até 10 s | — |
| Trava após parada da carga (SC-004) | 15 s | — |
| Listagem de 500 jogos (SC-007) | < 1 s | — |
| Alteração em massa de 500 vendedores (SC-016) | < 5 s | — |

**Constraints**:

- ciclo do ao vivo de 5 s, com tempo limite de 4 s;
- carga atômica;
- nada de SQL montado por texto, e o fuso nunca entra no SQL;
- chave do provedor só em cabeçalho;
- paginação ≤ 100 (padrão 50 na listagem pública e 20 no painel);
- 120 requisições/min por IP na listagem pública;
- mensagens em português.

**Scale/Scope**:

| Item | Quantidade |
|---|---|
| Tabelas | 17 novas, 1 alterada, 1 removida |
| Enums | 3 |
| Permissões | 21 novas, 1 removida |
| Rotas | 38 novas (1 pública e 37 do painel), 2 removidas |
| Comandos agendados | 3 |
| Volume da carga | ~7 mil campeonatos, ~4 mil confrontos e ~25 mil jogadores |

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Princípio / Regra | Verificação | Status |
|---|---|---|
| I. `snake_case` | Métodos, variáveis, parâmetros, chaves JSON, query string e colunas em `snake_case`; métodos do framework mantêm o nome | ✅ Pass |
| I. Banco em português | Tabelas e colunas em português; `codigo_externo` no lugar de `fonte_id` | ✅ Pass |
| I. Prefixo de tabelas | `confrontos_jogadores`, `confrontos_ao_vivo`, `confrontos_teto_cotacoes`, `campeonatos_nao_permitidos`, `confrontos_*_nao_permitidos`, `usuarios_configuracoes`, `visitantes_configuracoes`. As de porcentagem (`porcentagens_clientes`, `porcentagens_vendedores`, as duas `_ao_vivo`, `porcentagens_campeonatos`, `porcentagens_confrontos`) seguem a "Exceção — tabelas de porcentagem de cotação" (v1.17.0) | ✅ Pass |
| I. Permissões `<recurso>.<acao>` | `campeonatos.listar`, `porcentagens_clientes.editar`, `usuarios_configuracoes.editar`… | ✅ Pass |
| I. Enums | `AlvoRegra`, `SituacaoConfronto`, `SituacaoAoVivo`; valores em português (`'1 tempo'` segue o provedor) | ✅ Pass |
| I. Pastas | Novas pastas PSR-4 em `PascalCase` (`app/Console/Commands`, `app/Support`) | ✅ Pass |
| I. Rotas em kebab-case | `/publico/confrontos`, `/porcentagens-clientes`, `/confrontos-ao-vivo-nao-permitidos` | ✅ Pass |
| II. Idioma | Artefatos e comentários do backend em português | ✅ Pass |
| III. Componentes React | Sem frontend | ➖ N/A |
| IV. Escopo estrito | Arquivos existentes alterados listados abaixo; as mudanças nas specs 001 e 002 foram decididas pelo responsável na spec e as demais foram autorizadas em 2026-10-01 | ✅ Pass (confirmado) |
| V. Legibilidade | Revisão ao final de cada tarefa | ✅ Pass |
| VI. Consistência entre recursos | Permissões no `Funcao` (R-15); controllers com `HasMiddleware` e o trait `GarantirPermissaoCliente`; FormRequests com mensagens em português; Resources; serviços em `app/Services` | ✅ Pass |
| Timestamps e soft delete | As 17 tabelas com `timestamps()` + `softDeletes()`; jogadores e não permitidos removidos por soft delete | ✅ Pass |
| Exclusão física | Só a remoção da tabela `clientes_configuracoes_padrao` e da permissão `clientes.editar_configuracoes_padrao`, decidida pelo responsável (R-17) | ⚠️ Justificado |
| Colunas sem FK | `esporte` e `esportes_permitidos` (texto/JSON, cadastro de esportes não existe), já registradas nas specs anteriores | ✅ Pass |
| Paginação ≤ 100 | Todas as listagens | ✅ Pass |
| Sem testes | Nenhum arquivo, tarefa ou dependência de teste | ✅ Pass |
| Postman | Coleção regenerada na mesma entrega (rotas novas e removidas) | ✅ Pass |
| Alterações em código já implementado | Artefatos da spec 002 atualizados na mesma entrega (FR-080) | ✅ Pass |
| Novas dependências | Nenhuma | ✅ Pass |

**Resultado do gate**: aprovado.

**Confirmações do responsável (2026-10-01)**:

- Alteração de todos os arquivos existentes listados abaixo: autorizada.
- Exceção de prefixo para as tabelas `porcentagens_*`: emenda da constituição v1.17.0.

### Arquivos existentes que serão alterados (Princípio IV)

| Arquivo | Alteração | Motivo | Situação |
|---|---|---|---|
| `app/Http/Controllers/UsuariosController.php` | No `store`, criar a configuração do vendedor novo (`ConfiguracoesVendedores::criar_para`) dentro da transação | FR-076 | decidido na spec |
| `app/Services/CadastroClientes.php` | Criar a configuração só com `aceita_promocao` (valores do banco); tirar o uso do padrão | FR-079 | decidido na spec |
| `database/factories/ClientesFactory.php` | Idem, sem o padrão | FR-079 | decidido na spec |
| `database/seeders/ClientesSeeder.php` | Tirar a criação do padrão | FR-079 | decidido na spec |
| `routes/api.php` | Acrescentar a rota pública e o grupo do painel; remover as 2 rotas e o `use` do padrão | contrato, FR-079 | autorizado (2026-10-01) |
| `routes/console.php` | Agendar os 3 comandos | R-06 | autorizado (2026-10-01) |
| `config/services.php` | Bloco `provedor_cotacoes` (URLs, chave, nome do cabeçalho) | R-05, FR-053 | autorizado (2026-10-01) |
| `.env.example` | Variáveis `PROVEDOR_COTACOES_*` (sem valores reais) | R-05 | autorizado (2026-10-01) |
| `app/Enums/Funcao.php` | `PERMISSOES_CONFRONTOS`, `PERMISSOES_CONFRONTOS_GERENTE`, `permissoes_padrao()` e `pode_usar()`; tirar `clientes.editar_configuracoes_padrao` | R-15, FR-079 | autorizado (2026-10-01) |
| `app/Models/Usuarios.php` | Métodos `ids_hierarquia_acima()` e relação `configuracoes()` | R-10, R-16 | autorizado (2026-10-01) |
| `database/seeders/PapeisPermissoesSeeder.php` | Criar também as permissões de confrontos | R-15 | autorizado (2026-10-01) |
| `database/seeders/DatabaseSeeder.php` | `$this->call(ConfrontosSeeder::class)` ao final | R-15 | autorizado (2026-10-01) |
| `app/Http/Requests/ClientesConfiguracoesRequest.php` | Só o comentário da classe ("do cliente ou as padrão" → "do cliente") | FR-079 | autorizado (2026-10-01) |
| `docs/postman/wssports_api.postman_collection.json` | Regenerada | constituição | obrigatório |
| `specs/002-clientes/*` (`spec.md`, `plan.md`, `research.md`, `data-model.md`, `contracts/api.md`, `quickstart.md`, `tasks.md`) | Descrever o funcionamento sem a tabela padrão | FR-080 | decidido na spec |

**Arquivos removidos** (FR-079, decidido na spec): `app/Http/Controllers/ClientesConfiguracoesPadraoController.php`,
`app/Models/ClientesConfiguracoesPadrao.php`. A migration antiga de `clientes_configuracoes_padrao`
fica no histórico; a tabela é apagada por uma migration nova.

**Nenhum outro arquivo existente muda**: `GarantirAcesso`, `GarantirAcessoCliente`,
`GarantirPermissaoCliente`, `bootstrap/app.php`, `config/auth.php`, models e controllers de
clientes fora os listados.

**Impacto no banco local**: `php artisan migrate` (17 tabelas novas, `clientes_configuracoes`
alterada, `clientes_configuracoes_padrao` apagada), `db:seed --class=PapeisPermissoesSeeder` e
`db:seed --class=ConfrontosSeeder`. Nenhuma tabela do legado é tocada.

### Re-check pós-design (Phase 1)

O [data-model.md](data-model.md) e os contratos ([contracts/api.md](contracts/api.md),
[contracts/provedor.md](contracts/provedor.md)) foram conferidos:

- nomes em português e `snake_case`;
- prefixos conforme a tabela acima (porcentagens pela exceção da v1.17.0);
- 17 tabelas com timestamps e soft delete;
- valores de enum em português;
- paginação ≤ 100;
- rotas em kebab-case;
- nenhuma tarefa de teste e nenhuma dependência nova.

**Status: aprovado.**

## Project Structure

### Documentation (this feature)

```text
specs/003-confrontos/
├── plan.md              # Este arquivo
├── research.md          # Phase 0
├── data-model.md        # Phase 1
├── quickstart.md        # Phase 1: validação manual
├── contracts/
│   ├── api.md           # Phase 1: rotas do sistema
│   └── provedor.md      # Phase 1: formato consumido dos provedores
├── checklists/
│   └── requirements.md
└── tasks.md             # Phase 2 (/speckit-tasks)
```

### Source Code (repository root)

```text
app/
├── Console/
│   └── Commands/
│       ├── ConferirConfrontosAoVivoCommand.php        # NOVO: confrontos_ao_vivo:conferir
│       ├── ImportarConfrontosAoVivoCommand.php        # NOVO: confrontos_ao_vivo:importar
│       └── ImportarConfrontosCommand.php              # NOVO: confrontos:importar
├── Enums/
│   ├── AlvoRegra.php                                  # NOVO
│   ├── Funcao.php                                     # ALTERADO
│   ├── SituacaoAoVivo.php                             # NOVO
│   └── SituacaoConfronto.php                          # NOVO
├── Exceptions/
│   └── FalhaProvedorException.php                     # NOVO
├── Http/
│   ├── Controllers/
│   │   ├── CampeonatosController.php                  # NOVO: listagem, manual, situação, favorito
│   │   ├── CampeonatosNaoPermitidosController.php     # NOVO
│   │   ├── ClientesConfiguracoesPadraoController.php  # REMOVIDO
│   │   ├── ConfrontosAoVivoController.php             # NOVO: listagem do painel
│   │   ├── ConfrontosAoVivoNaoPermitidosController.php # NOVO
│   │   ├── ConfrontosController.php                   # NOVO: listagem, manual, situação
│   │   ├── ConfrontosNaoPermitidosController.php      # NOVO
│   │   ├── ConfrontosTetoCotacoesController.php       # NOVO
│   │   ├── PorcentagensCampeonatosController.php      # NOVO
│   │   ├── PorcentagensClientesController.php         # NOVO
│   │   ├── PorcentagensConfrontosController.php       # NOVO
│   │   ├── PorcentagensVendedoresController.php       # NOVO
│   │   ├── PublicoConfrontosController.php            # NOVO: listagem pública
│   │   ├── UsuariosConfiguracoesController.php        # NOVO
│   │   ├── UsuariosController.php                     # ALTERADO (store)
│   │   └── VisitantesConfiguracoesController.php      # NOVO
│   ├── Requests/
│   │   ├── CampeonatosRequest.php                     # NOVO: campeonato manual
│   │   ├── ClientesConfiguracoesRequest.php           # ALTERADO (só comentário)
│   │   ├── ConfrontosRequest.php                      # NOVO: confronto manual
│   │   ├── ListagemPublicaRequest.php                 # NOVO: filtros + limite de 120/min
│   │   ├── NaoPermitidosRequest.php                   # NOVO: comum às 3 tabelas
│   │   ├── PorcentagensConfrontosRequest.php          # NOVO: cotação desejada
│   │   ├── RegrasCotacaoRequest.php                   # NOVO: valores/todos (porcentagens e teto)
│   │   ├── UsuariosConfiguracoesRequest.php           # NOVO
│   │   └── VisitantesConfiguracoesRequest.php         # NOVO
│   └── Resources/
│       ├── CampeonatosResource.php                    # NOVO
│       ├── ConfrontosAoVivoResource.php               # NOVO (painel)
│       ├── ConfrontosResource.php                     # NOVO (painel)
│       ├── NaoPermitidosResource.php                  # NOVO
│       └── UsuariosConfiguracoesResource.php          # NOVO
├── Models/
│   ├── Campeonatos.php                                # NOVO
│   ├── CampeonatosNaoPermitidos.php                   # NOVO
│   ├── ClientesConfiguracoesPadrao.php                # REMOVIDO
│   ├── Configuracoes.php                              # NOVO: atual()
│   ├── Confrontos.php                                 # NOVO
│   ├── ConfrontosAoVivo.php                           # NOVO
│   ├── ConfrontosAoVivoNaoPermitidos.php              # NOVO
│   ├── ConfrontosJogadores.php                        # NOVO
│   ├── ConfrontosNaoPermitidos.php                    # NOVO
│   ├── ConfrontosTetoCotacoes.php                     # NOVO: atual()
│   ├── PorcentagensCampeonatos.php                    # NOVO
│   ├── PorcentagensClientes.php                       # NOVO
│   ├── PorcentagensClientesAoVivo.php                 # NOVO
│   ├── PorcentagensConfrontos.php                     # NOVO
│   ├── PorcentagensVendedores.php                     # NOVO
│   ├── PorcentagensVendedoresAoVivo.php               # NOVO
│   ├── Usuarios.php                                   # ALTERADO
│   ├── UsuariosConfiguracoes.php                      # NOVO
│   └── VisitantesConfiguracoes.php                    # NOVO: atual()
├── Services/
│   ├── AlcanceHierarquia.php                          # NOVO: dono e alvos permitidos (R-14)
│   ├── CadastroClientes.php                           # ALTERADO
│   ├── CalculoCotacoes.php                            # NOVO (R-10)
│   ├── ConferenciaAoVivo.php                          # NOVO (R-08)
│   ├── ConfiguracoesVendedores.php                    # NOVO (R-16)
│   ├── IdentificacaoPublico.php                       # NOVO (R-09)
│   ├── ImportacaoAoVivo.php                           # NOVO (R-07)
│   ├── ImportacaoPreJogo.php                          # NOVO (R-02 a R-04)
│   ├── ListagemConfrontos.php                         # NOVO (R-11)
│   ├── ProvedorCotacoes.php                           # NOVO (R-05)
│   ├── Publico.php                                    # NOVO: quem está vendo
│   ├── RegrasCotacao.php                              # NOVO: mescla valores/todos e cotação desejada
│   └── ValidacaoCargaProvedor.php                     # NOVO (R-02)
└── Support/
    └── CodigosCotacao.php                             # NOVO: odd1…odd323 + jogador

config/services.php                                    # ALTERADO
.env.example                                           # ALTERADO

database/
├── factories/
│   └── ClientesFactory.php                            # ALTERADO
├── migrations/
│   ├── 2026_10_01_000001_create_configuracoes_table.php                        # NOVO
│   ├── 2026_10_01_000002_create_visitantes_configuracoes_table.php             # NOVO
│   ├── 2026_10_01_000003_create_usuarios_configuracoes_table.php               # NOVO
│   ├── 2026_10_01_000004_create_campeonatos_table.php                          # NOVO
│   ├── 2026_10_01_000005_create_confrontos_table.php                           # NOVO
│   ├── 2026_10_01_000006_create_confrontos_jogadores_table.php                 # NOVO
│   ├── 2026_10_01_000007_create_confrontos_ao_vivo_table.php                   # NOVO
│   ├── 2026_10_01_000008_create_confrontos_teto_cotacoes_table.php             # NOVO
│   ├── 2026_10_01_000009_create_porcentagens_vendedores_table.php              # NOVO
│   ├── 2026_10_01_000010_create_porcentagens_vendedores_ao_vivo_table.php      # NOVO
│   ├── 2026_10_01_000011_create_porcentagens_clientes_table.php                # NOVO
│   ├── 2026_10_01_000012_create_porcentagens_clientes_ao_vivo_table.php        # NOVO
│   ├── 2026_10_01_000013_create_porcentagens_campeonatos_table.php             # NOVO
│   ├── 2026_10_01_000014_create_porcentagens_confrontos_table.php              # NOVO
│   ├── 2026_10_01_000015_create_campeonatos_nao_permitidos_table.php           # NOVO
│   ├── 2026_10_01_000016_create_confrontos_nao_permitidos_table.php            # NOVO
│   ├── 2026_10_01_000017_create_confrontos_ao_vivo_nao_permitidos_table.php    # NOVO
│   └── 2026_10_01_000018_remover_clientes_configuracoes_padrao.php             # NOVO: padrões nas colunas + drop
└── seeders/
    ├── ClientesSeeder.php                             # ALTERADO
    ├── ConfrontosSeeder.php                           # NOVO: permissões, registros únicos, configurações dos vendedores
    ├── DatabaseSeeder.php                             # ALTERADO
    └── PapeisPermissoesSeeder.php                     # ALTERADO

docs/postman/wssports_api.postman_collection.json     # ALTERADO (regenerado)

routes/
├── api.php                                            # ALTERADO
└── console.php                                        # ALTERADO

specs/002-clientes/                                    # ALTERADO (FR-080)
```

**Structure Decision**: aplicação Laravel única, seguindo os padrões das specs 001 e 002:

- FormRequests com mensagens em português, Resources e controllers com `HasMiddleware`;
- permissões checadas pelo trait `GarantirPermissaoCliente` (que usa `Funcao::usuario_pode`) e
  alcance da hierarquia em `AlcanceHierarquia`;
- regras de negócio e consultas pesadas em `app/Services`;
- comandos agendados em `app/Console/Commands`.

Sem Policies e sem arquivos em `tests/`.

## Complexity Tracking

Os nomes `porcentagens_*` deixaram de ser violação com a emenda da constituição v1.17.0
(2026-10-01), que criou a exceção para as tabelas de porcentagem de cotação.

| Violação | Por que é necessária | Alternativa mais simples rejeitada porque |
|---|---|---|
| Exclusão física da tabela `clientes_configuracoes_padrao` e da permissão `clientes.editar_configuracoes_padrao` | Decisão do responsável: não existe tabela padrão de configurações (Clarifications 2026-10-01) | Manter a tabela sem uso deixaria dois lugares para o mesmo padrão (coluna e tabela) |
