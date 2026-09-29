# Implementation Plan: Clientes (Apostadores)

**Branch**: `002-clientes` | **Date**: 2026-09-29 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/002-clientes/spec.md`

## Summary

API dos clientes (apostadores), separados dos usuários do painel. Cadastro público, login por
código do país + telefone e senha num guard JWT próprio (`clientes`), recuperação de senha por
código (enviado por evento, registrado no log até existir a spec de WhatsApp), "meus dados" e
extrato. Três saldos (`saldo`, `saldo_promocao_esportes`, `saldo_promocao_cassino`) movimentados
só pelo serviço `SaldoClientes`, com bloqueio de linha e cálculo em centavos, gerando transações
com saldo anterior e posterior. Configurações por cliente copiadas de um registro de configurações padrão,
promoções de primeiro cadastro e gestão no painel com 9 permissões do spatie restritas por função,
mascaramento de CPF/telefone e exclusão com sufixo `_deleted_<timestamp>` + restauração. Decisões
em [research.md](research.md).

## Technical Context

**Language/Version**: PHP 8.2 (^8.2)

**Primary Dependencies**: Laravel 12, `php-open-source-saver/jwt-auth` e
`spatie/laravel-permission` (já instalados na spec 001). **Nenhuma dependência nova.**

**Storage**: MySQL local `wssports` (compartilhado com o legado; só `migrate` e seeder novo,
R-17); cache `database` para blacklist JWT e `RateLimiter`

**Testing**: nenhum teste automatizado (constituição); validação manual pelo
[quickstart.md](quickstart.md)

**Target Platform**: servidor web com PHP 8.2

**Project Type**: API web Laravel (frontend fora do escopo)

**Performance Goals**: listagem/busca com até 100.000 clientes e extrato com até 10.000
transações em < 2 s (SC-009) — índices em `nome`, `created_at`, (`codigo_pais`, `telefone`),
`cpf` e (`clientes_id`, `created_at`)

**Constraints**: paginação ≤ 100 (padrão 20); token de 60 min; 5 tentativas de login/min por
telefone + IP; 1 pedido de recuperação/min por telefone; saldos nunca negativos; movimentações
serializadas por cliente; mensagens em português

**Scale/Scope**: 6 tabelas, 7 enums, 9 permissões, 28 rotas (10 da área do cliente, 18 do painel)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Princípio / Regra | Verificação | Status |
|---|---|---|
| I. `snake_case` | Métodos, variáveis, parâmetros, chaves JSON, rotas e permissões em `snake_case` (`pode_acessar()`, `alterar_situacao`, `clientes.listar`, `/area_cliente/meus_dados`); métodos exigidos pelo framework/pacotes mantêm o nome (exceção) | ✅ Pass |
| I. Banco em português | Tabelas e colunas em português (`clientes`, `saldo_promocao_esportes`, `data_nascimento`...); `password` coberta pela exceção de colunas de autenticação do framework (constituição v1.12.0, R-07) | ✅ Pass |
| I. Prefixo de tabelas (v1.9.0) | `clientes_transacoes`, `clientes_configuracoes`, `clientes_configuracoes_padrao`, `clientes_promocoes`, `clientes_codigos_recuperacao` | ✅ Pass |
| I. Pastas | Novas pastas PSR-4 em `PascalCase` (`app/Services`, `app/Events`, `app/Listeners`, `app/Rules`, `app/Exceptions`) — exceção do Princípio I | ✅ Pass |
| II. Idioma | Artefatos e comentários do backend em português | ✅ Pass |
| III. Componentes React | Sem frontend | ➖ N/A |
| IV. Escopo estrito | Arquivos existentes alterados listados abaixo; alteração autorizada pelo responsável em 2026-09-29 | ✅ Pass (confirmado) |
| V. Legibilidade | Revisão ao final de cada tarefa | ✅ Pass |
| Timestamps e soft delete | Todas as 6 tabelas com `timestamps()` + `softDeletes()` e models com `SoftDeletes` (incluindo transações, que nunca são excluídas) | ✅ Pass |
| Colunas sem FK (v1.10.0) | `codigo_afiliado`, `referencia_id` e `esportes_permitidos` sem FK, registradas nas premissas da spec (R-08) | ✅ Pass |
| Paginação ≤ 100 | `por_pagina` 1–100, padrão 20 | ✅ Pass |
| Sem testes | Nenhum arquivo/tarefa/dependência de teste | ✅ Pass |
| Postman | Coleção regenerada na mesma entrega das rotas, com a nova variável `token_cliente` | ✅ Pass |
| Novas dependências | Nenhuma | ✅ Pass |

**Resultado do gate**: aprovado.

**Confirmações do responsável (2026-09-29)**:

- Alteração dos arquivos existentes listados abaixo: autorizada.
- Nomes das permissões: padrão `<recurso>.<acao>`, igual à spec 001 (`usuarios.listar`), por
  exemplo `clientes.listar`, `clientes.excluir` e `clientes_promocoes.gerenciar`.

### Arquivos existentes que serão alterados (Princípio IV)

| Arquivo | Alteração | Motivo |
|---|---|---|
| `config/auth.php` | Acrescentar o guard `clientes` (driver `jwt`) e o provider `clientes` (model `Clientes`); guard `api` e provider `users` intactos | R-01, FR-014 |
| `routes/api.php` | Acrescentar os grupos `area_cliente` e de gestão de clientes; rotas da spec 001 intactas | contrato |
| `database/seeders/DatabaseSeeder.php` | Acrescentar `$this->call(ClientesSeeder::class)` ao final | FR-043a, FR-058 |
| `docs/postman/wssports_api.postman_collection.json` | Regenerado com as rotas novas e a variável `token_cliente` | Constituição |

Nenhum outro arquivo existente muda (`Funcao`, `Usuarios`, `GarantirAcesso`,
`PermissoesUsuariosController`, `bootstrap/app.php` e migrations da spec 001 ficam intactos).

**Impacto no banco local**: `php artisan migrate` (6 tabelas novas) e
`php artisan db:seed --class=ClientesSeeder`. Nada é apagado.

### Re-check pós-design (Phase 1)

Após [data-model.md](data-model.md) e [contracts/api.md](contracts/api.md): nomes em português e
`snake_case`, prefixo `clientes_` em todas as tabelas relacionadas, colunas sem FK documentadas,
paginação ≤ 100, nenhuma tarefa de teste, nenhuma dependência nova. **Status: aprovado** (todas as
confirmações recebidas).

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
└── tasks.md             # Phase 2 (/speckit-tasks)
```

### Source Code (repository root)

```text
app/
├── Enums/
│   ├── Carteira.php                                  # NOVO
│   ├── Genero.php                                    # NOVO
│   ├── CategoriaPromocao.php                           # NOVO
│   ├── ModalidadePromocao.php                        # NOVO
│   ├── OrigemTransacao.php                           # NOVO
│   ├── PermissaoCliente.php                          # NOVO: 9 permissões + funções permitidas
│   └── TipoTransacao.php                             # NOVO
├── Events/
│   ├── ClienteCadastrado.php                         # NOVO
│   └── CodigoRecuperacaoGerado.php                   # NOVO
├── Exceptions/
│   └── SaldoInsuficienteException.php                # NOVO: vira 422
├── Http/
│   ├── Controllers/
│   │   ├── AreaClienteAutenticacaoController.php     # NOVO: login, refresh, logout
│   │   ├── AreaClienteCadastroController.php         # NOVO: cadastro público
│   │   ├── AreaClienteMeusDadosController.php        # NOVO: dados, senha, extrato
│   │   ├── AreaClienteRecuperacaoSenhaController.php # NOVO: solicitar e redefinir
│   │   ├── ClientesController.php                    # NOVO: gestão, situação, exclusão, restauração
│   │   ├── ClientesConfiguracoesController.php       # NOVO: configurações do cliente
│   │   ├── ClientesConfiguracoesPadraoController.php # NOVO: configurações padrão
│   │   ├── ClientesPromocoesController.php           # NOVO
│   │   ├── ClientesTransacoesController.php          # NOVO: extrato e ajuste manual
│   │   └── Concerns/
│   │       └── GarantirPermissaoCliente.php          # NOVO: permissão + função (R-03)
│   ├── Middleware/
│   │   └── GarantirAcessoCliente.php                 # NOVO (R-02)
│   ├── Requests/
│   │   ├── AlterarSenhaClienteRequest.php            # NOVO
│   │   ├── ClientesConfiguracoesRequest.php          # NOVO: configurações e configurações padrão
│   │   ├── ClientesPromocoesRequest.php              # NOVO
│   │   ├── ClientesTransacoesRequest.php             # NOVO
│   │   ├── LoginClienteRequest.php                   # NOVO: limite de tentativas
│   │   ├── RecuperarSenhaClienteRequest.php          # NOVO
│   │   ├── RedefinirSenhaClienteRequest.php          # NOVO
│   │   ├── StoreClientesRequest.php                  # NOVO: cadastro público
│   │   ├── UpdateClientesRequest.php                 # NOVO: edição pelo painel
│   │   └── UpdateMeusDadosRequest.php                # NOVO
│   └── Resources/
│       ├── ClientesConfiguracoesResource.php         # NOVO
│       ├── ClientesPromocoesResource.php             # NOVO
│       ├── ClientesResource.php                      # NOVO: mascaramento (R-12)
│       └── ClientesTransacoesResource.php            # NOVO
├── Listeners/
│   └── RegistrarMensagemWhatsapp.php                 # NOVO: grava no log (R-14)
├── Models/
│   ├── Clientes.php                                  # NOVO: JWTSubject, pode_acessar()
│   ├── ClientesCodigosRecuperacao.php                # NOVO
│   ├── ClientesConfiguracoes.php                     # NOVO
│   ├── ClientesConfiguracoesPadrao.php               # NOVO
│   ├── ClientesPromocoes.php                         # NOVO
│   └── ClientesTransacoes.php                        # NOVO
├── Rules/
│   └── CpfValido.php                                 # NOVO
└── Services/
    ├── CadastroClientes.php                          # NOVO: cadastro atômico + promoção (R-15)
    └── SaldoClientes.php                             # NOVO: creditar/debitar (R-04)

config/auth.php                                       # ALTERADO: guard e provider clientes

database/
├── factories/
│   └── ClientesFactory.php                           # NOVO
├── migrations/
│   ├── 2026_09_29_000001_create_clientes_table.php                       # NOVO
│   ├── 2026_09_29_000002_create_clientes_transacoes_table.php            # NOVO
│   ├── 2026_09_29_000003_create_clientes_configuracoes_table.php         # NOVO
│   ├── 2026_09_29_000004_create_clientes_configuracoes_padrao_table.php  # NOVO
│   ├── 2026_09_29_000005_create_clientes_promocoes_table.php             # NOVO
│   └── 2026_09_29_000006_create_clientes_codigos_recuperacao_table.php   # NOVO
└── seeders/
    ├── ClientesSeeder.php                            # NOVO: permissões, configurações padrão, exemplos
    └── DatabaseSeeder.php                            # ALTERADO: chama ClientesSeeder

docs/postman/wssports_api.postman_collection.json     # ALTERADO (regenerado)

routes/api.php                                        # ALTERADO: rotas novas
```

**Structure Decision**: aplicação Laravel única, estrutura padrão, seguindo os padrões da spec 001
(FormRequests com mensagens em português, Resources, controllers com `HasMiddleware`). Regras de
negócio com transação ficam em `app/Services`. Sem Policies e sem arquivos em `tests/`.

## Complexity Tracking

Nenhuma violação da constituição a justificar.
