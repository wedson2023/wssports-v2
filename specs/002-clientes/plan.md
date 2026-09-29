# Implementation Plan: Clientes (Apostadores)

**Branch**: `002-clientes` | **Date**: 2026-09-29 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/002-clientes/spec.md`

## Summary

API dos clientes (apostadores), separados dos usuários do painel. Cadastro público (e-mail e CPF
opcionais), login por DDI + telefone e senha num guard JWT próprio (`clientes`), recuperação de
senha por código (evento registrado no log até existir a spec de WhatsApp), "meus dados", extrato
e meios de pagamento (Pix ou transferência bancária). Três saldos movimentados só pelo serviço
`SaldoClientes` (bloqueio de linha e cálculo em centavos), com transações de saldo anterior e
posterior. Configurações de aposta e saque por cliente, copiadas de um registro padrão. Promoções
com categoria, tipo de ganho, rollover e regras de uso (cadastradas e validadas; só "Primeiro
cadastro" é aplicada nesta spec) e estorno de promoção em segundo plano, por fila. Gestão no painel
com 10 permissões do spatie restritas por função, mascaramento de dados pessoais e exclusão com
sufixo `_deleted_<timestamp>` + restauração. Decisões em [research.md](research.md).

## Technical Context

**Language/Version**: PHP 8.2 (^8.2)

**Primary Dependencies**: Laravel 12, `php-open-source-saver/jwt-auth` e
`spatie/laravel-permission` (já instalados na spec 001). **Nenhuma dependência nova.**

**Storage**: MySQL local `wssports` (compartilhado com o legado; só `migrate` e seeder novo,
R-20); cache `database` para blacklist JWT e `RateLimiter`; fila `database` (já configurada) para
o estorno de promoções

**Testing**: nenhum teste automatizado (constituição); validação manual pelo
[quickstart.md](quickstart.md)

**Target Platform**: servidor web com PHP 8.2 + worker de fila (`php artisan queue:work`)

**Project Type**: API web Laravel (frontend fora do escopo)

**Performance Goals**: listagem/busca com até 100.000 clientes e extrato com até 10.000
transações em < 2 s (SC-009); estorno de promoção de 10.000 clientes em < 10 min (SC-010)

**Constraints**: paginação ≤ 100 (padrão 20); token de 60 min; 5 tentativas de login/min por
telefone + IP; 1 pedido de recuperação/min por telefone; saldos nunca negativos; movimentações
serializadas por cliente; estorno idempotente; mensagens em português

**Scale/Scope**: 7 tabelas, 12 enums, 10 permissões, 38 rotas (15 da área do cliente, 23 do
painel), 1 job

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Princípio / Regra | Verificação | Status |
|---|---|---|
| I. `snake_case` | Métodos, variáveis, parâmetros, chaves JSON, rotas e colunas em `snake_case`; métodos exigidos pelo framework/pacotes mantêm o nome (exceção) | ✅ Pass |
| I. Banco em português | Tabelas e colunas em português; `password` coberta pela exceção de autenticação (v1.12.0); `ddi` e `email` pela exceção de siglas consagradas (v1.13.0) | ✅ Pass |
| I. Prefixo de tabelas (v1.9.0) | `clientes_transacoes`, `clientes_configuracoes`, `clientes_configuracoes_padrao`, `clientes_meios_pagamento`, `clientes_promocoes`, `clientes_codigos_recuperacao` | ✅ Pass |
| I. Permissões `<recurso>.<acao>` (v1.11.0) | `clientes.listar`, `clientes.excluir`, `clientes_promocoes.estornar`... | ✅ Pass |
| I. Enums (v1.13.0) | Casos em `PascalCase` com acento (`Promoção`, `NãoInformado`); valores gravados em português com inicial maiúscula e acentos (`'Ajuste manual'`, `'Primeiro depósito'`). Nomes de classe sem acento por causa do autoload (R-05) | ✅ Pass |
| I. Pastas | Novas pastas PSR-4 em `PascalCase` (`app/Services`, `app/Events`, `app/Listeners`, `app/Jobs`, `app/Rules`, `app/Exceptions`) — exceção do Princípio I | ✅ Pass |
| II. Idioma | Artefatos e comentários do backend em português | ✅ Pass |
| III. Componentes React | Sem frontend | ➖ N/A |
| IV. Escopo estrito | Arquivos existentes alterados listados abaixo — mesma lista já autorizada em 2026-09-29 | ✅ Pass (confirmado) |
| V. Legibilidade | Revisão ao final de cada tarefa | ✅ Pass |
| VI. Consistência entre recursos (v1.14.0) | Permissões de clientes no enum `Funcao`, no mesmo padrão das de usuários (R-03) | ✅ Pass |
| I. Caminhos de rota em kebab-case (v1.16.0) | `/area-cliente/meus-dados`, `/clientes-promocoes`; query string (`?por_pagina=`) e chaves JSON em snake_case | ✅ Pass |
| Timestamps e soft delete | As 7 tabelas com `timestamps()` + `softDeletes()` e models com `SoftDeletes` | ✅ Pass |
| Colunas sem FK (v1.10.0) | `codigo_afiliado`, `referencia_id`, `esportes_permitidos` sem FK, registradas nas premissas da spec (R-08) | ✅ Pass |
| Paginação ≤ 100 | `por_pagina` 1–100, padrão 20 | ✅ Pass |
| Sem testes | Nenhum arquivo/tarefa/dependência de teste | ✅ Pass |
| Postman | Coleção regenerada na mesma entrega das rotas, com a variável `token_cliente` | ✅ Pass |
| Novas dependências | Nenhuma (fila `database` e tabela `jobs` já existem) | ✅ Pass |

**Resultado do gate**: aprovado.

**Confirmações do responsável (2026-09-29)**:

- Alteração dos arquivos existentes listados abaixo: autorizada.
- Nomes das permissões: padrão `<recurso>.<acao>`, igual à spec 001.
- Permissões de clientes no `Funcao.php` e caminhos de rota em kebab-case: autorizado
  (2026-09-29). Parâmetros de query string continuam em snake_case, sem mudança na spec 001.

### Arquivos existentes que serão alterados (Princípio IV)

| Arquivo | Alteração | Motivo |
|---|---|---|
| `config/auth.php` | Acrescentar o guard `clientes` (driver `jwt`) e o provider `clientes` (model `Clientes`); guard `api` e provider `users` intactos | R-01, FR-014 |
| `routes/api.php` | Acrescentar os grupos `area-cliente` e de gestão de clientes; rotas da spec 001 intactas | contrato |
| `database/seeders/DatabaseSeeder.php` | Acrescentar `$this->call(ClientesSeeder::class)` ao final | FR-050, FR-062 |
| `docs/postman/wssports_api.postman_collection.json` | Regenerado com as rotas novas e a variável `token_cliente` | Constituição |
| `app/Enums/Funcao.php` | `PERMISSOES_CLIENTES`, `PERMISSOES_CLIENTES_RESTRITAS`, `permissoes_padrao()` com as de clientes, `pode_usar()` e `usuario_pode()` | R-03, Princípio VI |
| `database/seeders/PapeisPermissoesSeeder.php` | Cria também as permissões de clientes | R-03 |

Nenhum outro arquivo existente muda (`Usuarios`, `GarantirAcesso`,
`PermissoesUsuariosController`, `bootstrap/app.php`, `config/queue.php` e migrations da spec 001
ficam intactos).

**Impacto no banco local**: `php artisan migrate` (7 tabelas novas) e
`php artisan db:seed --class=ClientesSeeder`. Nada é apagado.

### Re-check pós-design (Phase 1)

Após [data-model.md](data-model.md) e [contracts/api.md](contracts/api.md): nomes em português e
`snake_case`, prefixo `clientes_` em todas as tabelas relacionadas, siglas e enums conforme
v1.13.0, colunas sem FK documentadas, paginação ≤ 100, nenhuma tarefa de teste, nenhuma
dependência nova. **Status: aprovado.**

## Project Structure

### Documentation (this feature)

```text
specs/002-clientes/
├── plan.md              # Este arquivo
├── research.md          # Phase 0
├── data-model.md        # Phase 1
├── quickstart.md        # Phase 1: validação manual
├── contracts/
│   └── api.md           # Phase 1: contrato HTTP
├── checklists/
│   └── requirements.md
└── tasks.md             # Phase 2 (/speckit-tasks) — precisa ser gerado de novo
```

### Source Code (repository root)

```text
app/
├── Enums/
│   ├── Carteira.php                                  # NOVO (+ coluna())
│   ├── CategoriaPromocao.php                         # NOVO (+ aceita_percentual())
│   ├── Genero.php                                    # NOVO
│   ├── ModalidadePromocao.php                        # NOVO (+ carteira())
│   ├── OrigemTransacao.php                           # NOVO
│   ├── SituacaoEstorno.php                           # NOVO
│   ├── TipoChavePix.php                              # NOVO
│   ├── TipoConta.php                                 # NOVO
│   ├── TipoGanho.php                                 # NOVO
│   ├── TipoMeioPagamento.php                         # NOVO
│   └── TipoTransacao.php                             # NOVO
├── Events/
│   ├── ClienteCadastrado.php                         # NOVO
│   └── CodigoRecuperacaoGerado.php                   # NOVO
├── Exceptions/
│   └── SaldoInsuficienteException.php                # NOVO: vira 422
├── Http/
│   ├── Controllers/
│   │   ├── AreaClienteAutenticacaoController.php     # NOVO: login, refresh, logout
│   │   ├── AreaClienteCadastroController.php         # NOVO
│   │   ├── AreaClienteMeiosPagamentoController.php   # NOVO
│   │   ├── AreaClienteMeusDadosController.php        # NOVO: dados, senha, extrato
│   │   ├── AreaClienteRecuperacaoSenhaController.php # NOVO
│   │   ├── ClientesController.php                    # NOVO: gestão, situação, exclusão, restauração
│   │   ├── ClientesConfiguracoesController.php       # NOVO
│   │   ├── ClientesConfiguracoesPadraoController.php # NOVO
│   │   ├── ClientesMeiosPagamentoController.php      # NOVO (painel)
│   │   ├── ClientesPromocoesController.php           # NOVO: CRUD + estornar
│   │   ├── ClientesTransacoesController.php          # NOVO
│   │   └── Concerns/
│   │       └── GarantirPermissaoCliente.php          # NOVO (R-03)
│   ├── Middleware/
│   │   └── GarantirAcessoCliente.php                 # NOVO (R-02)
│   ├── Requests/
│   │   ├── AlterarSenhaClienteRequest.php            # NOVO
│   │   ├── ClientesConfiguracoesRequest.php          # NOVO: configurações e padrão
│   │   ├── ClientesMeiosPagamentoRequest.php         # NOVO: área do cliente e painel
│   │   ├── ClientesPromocoesRequest.php              # NOVO
│   │   ├── ClientesTransacoesRequest.php             # NOVO
│   │   ├── EstornarPromocaoRequest.php               # NOVO
│   │   ├── LoginClienteRequest.php                   # NOVO
│   │   ├── RecuperarSenhaClienteRequest.php          # NOVO
│   │   ├── RedefinirSenhaClienteRequest.php          # NOVO
│   │   ├── StoreClientesRequest.php                  # NOVO: cadastro público
│   │   ├── UpdateClientesRequest.php                 # NOVO: edição pelo painel
│   │   └── UpdateMeusDadosRequest.php                # NOVO
│   └── Resources/
│       ├── ClientesConfiguracoesResource.php         # NOVO
│       ├── ClientesMeiosPagamentoResource.php        # NOVO: mascaramento
│       ├── ClientesPromocoesResource.php             # NOVO
│       ├── ClientesResource.php                      # NOVO: mascaramento (R-12)
│       └── ClientesTransacoesResource.php            # NOVO
├── Jobs/
│   └── EstornarPromocao.php                          # NOVO (R-17)
├── Listeners/
│   └── RegistrarMensagemWhatsapp.php                 # NOVO (R-14)
├── Models/
│   ├── Clientes.php                                  # NOVO: JWTSubject, pode_acessar()
│   ├── ClientesCodigosRecuperacao.php                # NOVO
│   ├── ClientesConfiguracoes.php                     # NOVO
│   ├── ClientesConfiguracoesPadrao.php               # NOVO
│   ├── ClientesMeiosPagamento.php                    # NOVO
│   ├── ClientesPromocoes.php                         # NOVO
│   └── ClientesTransacoes.php                        # NOVO
├── Rules/
│   ├── CnpjValido.php                                # NOVO
│   └── CpfValido.php                                 # NOVO
└── Services/
    ├── CadastroClientes.php                          # NOVO (R-15)
    ├── MeiosPagamentoClientes.php                    # NOVO: principal e unicidade (R-18)
    └── SaldoClientes.php                             # NOVO (R-04)

config/auth.php                                       # ALTERADO

database/
├── factories/
│   └── ClientesFactory.php                           # NOVO
├── migrations/
│   ├── 2026_09_29_000001_create_clientes_table.php                       # NOVO
│   ├── 2026_09_29_000002_create_clientes_transacoes_table.php            # NOVO
│   ├── 2026_09_29_000003_create_clientes_configuracoes_table.php         # NOVO
│   ├── 2026_09_29_000004_create_clientes_configuracoes_padrao_table.php  # NOVO
│   ├── 2026_09_29_000005_create_clientes_meios_pagamento_table.php       # NOVO
│   ├── 2026_09_29_000006_create_clientes_promocoes_table.php             # NOVO
│   └── 2026_09_29_000007_create_clientes_codigos_recuperacao_table.php   # NOVO
└── seeders/
    ├── ClientesSeeder.php                            # NOVO
    └── DatabaseSeeder.php                            # ALTERADO

docs/postman/wssports_api.postman_collection.json     # ALTERADO (regenerado)

routes/api.php                                        # ALTERADO
```

**Structure Decision**: aplicação Laravel única, estrutura padrão, seguindo os padrões da spec 001
(FormRequests com mensagens em português, Resources, controllers com `HasMiddleware`). Regras de
negócio com transação ficam em `app/Services`; o estorno em `app/Jobs`. Sem Policies e sem arquivos
em `tests/`.

## Complexity Tracking

Nenhuma violação da constituição a justificar.
