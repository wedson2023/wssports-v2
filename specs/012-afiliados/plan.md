# Implementation Plan: Afiliados

**Branch**: `012-afiliados` | **Date**: 2026-10-17 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/012-afiliados/spec.md`

## Summary

Cria o cadastro de afiliados, separado de usuários e clientes, com login próprio e comissão
gravada a cada movimento dos clientes indicados:

- **Cadastro**: pelo painel (API, já Ativo) e público em `/cadastro-afiliado` (Em análise até
  Admin/Supervisor aprovar ou recusar), com e-mails de recebimento, aprovação e recusa.
- **Login**: guard JWT `afiliados`, pela mesma tela de entrada do site (`AuthModal`), que passa a
  tentar painel, afiliado e cliente; "Esqueci minha senha" por e-mail.
- **Indicação**: código público próprio no link `?user=`; clientes e promoções passam a se ligar
  ao afiliado por `afiliados_id` (FK), no lugar do `codigo_afiliado` em texto.
- **Comissão**: lançamentos gravados dentro das transações de depósito aprovado, estorno e saque
  pago, com a regra vigente (Saldo líquido, Qualquer depósito, Primeiro depósito) e chave de
  evento única.
- **Prestação de contas**: sempre total, com a senha de quem presta; negativo zerado; histórico.
- **Painel do afiliado** `/afiliados` no visual do antigo, com extrato por período, clientes,
  prestações, copiar link, Meus dados e troca de senha.
- **Gestão** só pela API, no padrão de `clientes`, com permissões `afiliados.*`.
- **E-mail do cliente**: boas-vindas no cadastro de clientes com e-mail, por um listener novo do
  evento `ClienteCadastrado` (R-21).

Decisões em [research.md](research.md) (R-01 a R-21).

## Technical Context

**Language/Version**: PHP 8.2 (^8.2) no backend; JavaScript (ES2022) com JSX no frontend

**Primary Dependencies**: as já instaladas (Laravel 12, Inertia 2, React 19, styled-components 6,
sweetalert2, `php-open-source-saver/jwt-auth`, `spatie/laravel-permission`). **Nenhum pacote novo**
(R-20). E-mail pelo `Mail` do Laravel.

**Storage**: MySQL: 6 tabelas novas (`afiliados`, `afiliados_comissoes`, `afiliados_lancamentos`,
`afiliados_prestacoes_contas`, `afiliados_meios_pagamento`, `afiliados_codigos_recuperacao`) e
alterações em `clientes`, `clientes_promocoes` e `configuracoes`; fila `database` (já
configurada) para os e-mails. No navegador: só a sessão existente, com `tipo: 'afiliado'`.

**Testing**: nenhum teste automatizado (Constituição); validação manual pelo
[quickstart.md](quickstart.md)

**Target Platform**: navegadores atuais no desktop e no celular; servidor PHP 8.2 com
`queue:work`; envio de e-mail configurado no `.env` (`log` em desenvolvimento)

**Project Type**: aplicação web Laravel (monólito com Inertia) + API JWT existente

**Performance Goals**:

| Meta | Alvo |
|---|---|
| Painel do afiliado com até 10.000 lançamentos (SC-007) | ≤ 2 s (somas por índice `afiliados_id` + prestação) |
| Lançamento de comissão | dentro da transação do depósito/saque, sem consulta extra pesada |
| Listagens | 20 (gestão) / 30 (painel) por página, máximo 100 |

**Constraints**:

- dinheiro com `bcmath`, arredondamento meio para cima em centavos (`EscolhaPromocoes::percentual`);
- idempotência por `chave_evento` única; prestação com bloqueio da linha do afiliado;
- nenhum SQL concatenado com dados da requisição; totais por subconsultas do query builder;
- tokens separados por guard (`lock_subject`); dados mascarados na gestão sem
  `clientes.ver_dados_completos`;
- visual: páginas iguais a `screens/new-affiliated` e `screens/affiliated`; modal de cadastro com os
  campos do `modals/affiliated`; melhorias aprovadas na spec;
- nomes conforme a Constituição.

**Scale/Scope**:

| Item | Quantidade |
|---|---|
| Tabelas novas / alteradas | 6 / 3 |
| Migrations | 8 |
| Rotas da API novas / web novas | 31 / 2 |
| Controllers novos | 11 |
| Serviços novos | 6 (`CadastroAfiliados`, `RegraComissaoAfiliados`, `ComissoesAfiliados`, `PrestacaoContasAfiliados`, `VinculoAfiliados`, `ListagemAfiliados`) |
| Mailables / views de e-mail | 5 / 5 (4 de afiliados + 1 de cliente) |
| Listeners novos | 1 (`EnviarEmailCadastroCliente`) |
| Componentes novos / páginas novas | 7 / 2 |
| Hooks novos | 1 (`useAfiliado`) |

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Princípio / Regra | Verificação | Status |
|---|---|---|
| I. Nomes no backend | Tabelas com o prefixo da principal (`afiliados_comissoes`, `afiliados_lancamentos`, …) e colunas em português; enums com valores em português e acentos (`Em análise`, `Saldo líquido`); permissões `afiliados.<acao>`; rotas em kebab-case (`/area-afiliado/meus-dados`, `/cadastro-afiliado`); `login`, `email`, `cpf`, `pix`, `password` pelas exceções da constituição | ✅ Pass |
| I. Nomes no frontend | Componentes em inglês PascalCase (`AffiliatePanel`, `AffiliateSignupModal`); hook `useAfiliado`; funções e variáveis em snake_case português; props de estilo com `$` | ✅ Pass |
| II. Idioma | Artefatos, comentários e textos em português | ✅ Pass |
| III. Componentes | Cada componente e página em pasta própria com `index.jsx` e `styles.jsx` | ✅ Pass |
| IV. Escopo estrito | Arquivos existentes listados abaixo, confirmados pelo responsável em 2026-10-17 | ✅ Pass |
| V. Legibilidade | Revisão ao final de cada tarefa | ✅ Pass |
| VI. Consistência | Mesmo padrão de `clientes`: guard JWT + middleware de acesso, Request/Resource, `permissao_cliente`, sufixo de exclusão, restauração com conflitos, recuperação de senha, seeder por recurso, configuração como `ConfiguracoesCassino`. Padrão novo: primeiros Mailables do projeto (R-10, R-21), aprovado pelo responsável em 2026-10-17 | ✅ Pass |
| VII. Sistema antigo | Arquivos e funcionamento em R-01; diferenças em R-02, todas aprovadas na spec | ✅ Pass |
| VIII. Fidelidade visual | Páginas iguais às do antigo; melhorias aprovadas na spec (Clarifications); referências em contracts/paginas.md | ✅ Pass |
| IX. Dados fake | Nenhum fake novo; usa o fake existente `DadosFake::tema()` (spec 005) nas cores das páginas e dos e-mails, fora de operações com dinheiro, lido de um ponto único (R-19) | ✅ Pass |
| Stack: dependências | Nenhum pacote novo | ✅ Pass |
| Timestamps, soft delete e comentários | Todas as tabelas novas com `timestamps()` + `softDeletes()`; toda coluna com `->comment()` | ✅ Pass |
| Referências a tabelas inexistentes | `clientes.codigo_afiliado` e `clientes_promocoes.codigo_afiliado` (specs 002 e 007) viram `afiliados_id` com FK (R-09) | ✅ Pass |
| Paginação ≤ 100 | Gestão 20/100; painel 30/100 | ✅ Pass |
| Sem testes | Nenhum arquivo, tarefa ou dependência de teste | ✅ Pass |
| Postman | Coleção regenerada na mesma entrega | ✅ Pass |

**Resultado do gate**: aprovado; lista de arquivos existentes (Princípio IV) e Mailables
(Princípio VI) confirmados pelo responsável em 2026-10-17, com o acréscimo do e-mail de
boas-vindas do cadastro de clientes (R-21). Reavaliado depois da Fase 1: sem violação nova.

### Arquivos existentes que serão alterados (Princípio IV)

| Arquivo | Alteração | Motivo |
|---|---|---|
| `config/auth.php` | Guard `afiliados` (JWT) e provider `afiliados` | R-03 |
| `app/Enums/Funcao.php` | `PERMISSOES_AFILIADOS`; `permissoes_padrao` (Gerente sem `editar_comissao`) e `pode_usar` | data-model §9 |
| `database/seeders/DatabaseSeeder.php` | Chama o `AfiliadosSeeder` | R-17 |
| `app/Models/Clientes.php` | `afiliados_id` no lugar de `codigo_afiliado` no `fillable`; relação `afiliado` | R-09 |
| `app/Models/ClientesPromocoes.php` | `afiliados_id` no `fillable`; `scopeParaAfiliado(?int)`; relação `afiliado` | R-09 |
| `app/Models/Configuracoes.php` | `fillable`/`casts` da regra padrão de afiliados | R-08 |
| `app/Services/CadastroClientes.php` | Grava `afiliados_id` pelo código (`VinculoAfiliados`); promoções pelo id | R-05, R-09 |
| `app/Services/EscolhaPromocoes.php` | `paraAfiliado($cliente->afiliados_id)` e `afiliados_id is null` | R-09 |
| `app/Services/Indicacoes.php` | Idem em `promocao_vigente` | R-09 |
| `app/Http/Resources/MinhaContaResource.php` | Idem na promoção de indicação | R-09 |
| `app/Services/ProcessamentoDepositos.php` | Injeta `ComissoesAfiliados`; chama em `depois_de_aprovar` e em `estornar` | R-06 |
| `app/Services/PagamentoSaques.php` | Injeta `ComissoesAfiliados`; chama em `dar_baixa` e `confirmar` | R-06 |
| `app/Http/Requests/StoreClientesRequest.php` | Regra "código de afiliado existe" | R-05, FR-023 |
| `app/Http/Requests/UpdateClientesRequest.php` | `afiliados_id` no lugar de `codigo_afiliado` | R-09 |
| `app/Http/Requests/Concerns/DadosCliente.php` | Mensagens do código de afiliado | R-05 |
| `app/Http/Requests/ClientesPromocoesRequest.php` | `afiliados_id` no lugar de `codigo_afiliado` (regra e mensagem) | R-09 |
| `app/Http/Requests/ClientesMeiosPagamentoRequest.php` | Usa o trait `Concerns/ChavePix` (mesmo comportamento) | R-13 |
| `app/Http/Requests/StoreUsuariosRequest.php` | Login também não pode repetir o de um afiliado | R-04 |
| `app/Http/Resources/ClientesResource.php` | `afiliados_id` e `afiliado`; `mascarar_cpf`, `mascarar_telefone`, `mascarar_email` públicos | R-09, R-12 |
| `app/Http/Resources/ClientesPromocoesResource.php` | `afiliados_id` e `afiliado` | R-09 |
| `app/Http/Controllers/ClientesController.php` | Filtro `afiliados_id` no lugar de `codigo_afiliado` | R-09 |
| `routes/api.php` | 31 rotas novas | contracts/api.md |
| `routes/web.php` | `cadastro-afiliado` e `afiliados` | contracts/paginas.md |
| `docs/postman/wssports_api.postman_collection.json` | Regenerada | Constituição |
| `specs/002-clientes/spec.md`, `specs/007-painel-cliente-pagamentos/spec.md` | Notas "Alterado pela spec 012" (código de afiliado em texto → FK) | R-18 |
| `resources/js/components/Footer/index.jsx` | "Afiliados" vira link para `/cadastro-afiliado` | contracts/paginas §3 |
| `resources/js/components/AuthModal/index.jsx` | Sessão de afiliado vai para `/afiliados` | contracts/paginas §3 |
| `resources/js/hooks/useSessao.js` | Tipo `afiliado` no login/logout e ordem de tentativa | R-04 |
| `resources/js/utils/session.js` | Comentário do formato da sessão | data-model §10 |
| `public/images/afiliado.jpg` (novo arquivo, copiado do antigo) | Imagem da página pública | R-16 |

Nenhum outro arquivo das specs 001 a 011 muda.

## Project Structure

### Documentation (this feature)

```text
specs/012-afiliados/
├── spec.md
├── plan.md              # este arquivo
├── research.md          # R-01 a R-20
├── data-model.md        # tabelas, ciclo de vida, enums, permissões
├── quickstart.md        # validação manual
├── contracts/
│   ├── api.md           # rotas novas e alteradas
│   └── paginas.md       # AffiliateLanding, AffiliatePanel e mudanças em telas existentes
├── checklists/
│   └── requirements.md
└── tasks.md             # /speckit-tasks
```

### Source Code (repository root)

```text
app/
├── Enums/
│   ├── SituacaoAfiliado.php
│   ├── OrigemCadastroAfiliado.php
│   ├── TipoGanhoAfiliado.php
│   └── EventoLancamentoAfiliado.php
├── Models/
│   ├── Afiliados.php
│   ├── AfiliadosComissoes.php
│   ├── AfiliadosLancamentos.php
│   ├── AfiliadosPrestacoesContas.php
│   ├── AfiliadosMeiosPagamento.php
│   └── AfiliadosCodigosRecuperacao.php
├── Services/
│   ├── CadastroAfiliados.php          # cadastro painel/público, aprovação, recusa, meio de pagamento
│   ├── RegraComissaoAfiliados.php     # regra inicial (padrão ou informada) e troca com vigência
│   ├── ComissoesAfiliados.php         # lançamentos de depósito, estorno e saque (idempotentes)
│   ├── PrestacaoContasAfiliados.php   # prestação total com bloqueio
│   ├── VinculoAfiliados.php           # resolve o código do link (com excluídos)
│   └── ListagemAfiliados.php          # totais do período, valor a receber, última prestação
├── Mail/
│   ├── AfiliadoCadastroRecebido.php
│   ├── AfiliadoAprovado.php
│   ├── AfiliadoRecusado.php
│   ├── Concerns/DadosEmail.php        # logo, nome e cor (fake do tema, R-19) dos e-mails
│   ├── AfiliadoCodigoRecuperacao.php
│   └── ClienteCadastroRealizado.php   # boas-vindas do cliente (R-21)
├── Listeners/
│   └── EnviarEmailCadastroCliente.php # ouve ClienteCadastrado (existente); só com e-mail
└── Http/
    ├── Middleware/
    │   └── GarantirAcessoAfiliado.php
    ├── Controllers/
    │   ├── AreaAfiliadoCadastroController.php
    │   ├── AreaAfiliadoAutenticacaoController.php
    │   ├── AreaAfiliadoRecuperacaoSenhaController.php
    │   ├── AreaAfiliadoPainelController.php       # painel, extrato, clientes, prestações
    │   ├── AreaAfiliadoMeusDadosController.php    # meus dados e senha
    │   ├── AfiliadosController.php                # gestão, situação, aprovar, recusar, excluir, restaurar
    │   ├── AfiliadosComissoesController.php
    │   ├── AfiliadosExtratoController.php         # lançamentos e clientes do afiliado
    │   ├── AfiliadosPrestacoesController.php
    │   ├── ConfiguracoesAfiliadosController.php
    │   └── AfiliadosPaginasController.php         # páginas Inertia
    ├── Requests/
    │   ├── Concerns/DadosAfiliado.php             # normalização e regras comuns
    │   ├── Concerns/ChavePix.php                  # extraído do ClientesMeiosPagamentoRequest
    │   ├── CadastroPublicoAfiliadoRequest.php
    │   ├── StoreAfiliadosRequest.php
    │   ├── UpdateAfiliadosRequest.php
    │   ├── RestaurarAfiliadosRequest.php
    │   ├── AprovarAfiliadoRequest.php
    │   ├── ComissaoAfiliadoRequest.php
    │   ├── PrestacaoContasAfiliadoRequest.php
    │   ├── LoginAfiliadoRequest.php
    │   ├── RecuperarSenhaAfiliadoRequest.php
    │   ├── RedefinirSenhaAfiliadoRequest.php
    │   ├── UpdateMeusDadosAfiliadoRequest.php
    │   ├── AlterarSenhaAfiliadoRequest.php
    │   └── ConfiguracoesAfiliadosRequest.php
    └── Resources/
        ├── AfiliadosResource.php
        ├── AfiliadosComissoesResource.php
        ├── AfiliadosLancamentosResource.php
        ├── AfiliadosPrestacoesContasResource.php
        ├── PainelAfiliadoResource.php
        ├── ClienteIndicadoResource.php
        ├── MeusDadosAfiliadoResource.php
        └── ConfiguracoesAfiliadosResource.php

database/
├── migrations/
│   ├── xxxx_create_afiliados_table.php
│   ├── xxxx_create_afiliados_comissoes_table.php
│   ├── xxxx_create_afiliados_prestacoes_contas_table.php
│   ├── xxxx_create_afiliados_lancamentos_table.php
│   ├── xxxx_create_afiliados_meios_pagamento_table.php
│   ├── xxxx_create_afiliados_codigos_recuperacao_table.php
│   ├── xxxx_alterar_afiliado_clientes_e_promocoes_table.php   # codigo_afiliado → afiliados_id
│   └── xxxx_add_afiliados_configuracoes_table.php
└── seeders/
    ├── AfiliadosSeeder.php
    └── AfiliadosVolumeSeeder.php      # 10.000 lançamentos para medir o SC-007

resources/views/emails/
├── afiliados/
│   ├── cadastro_recebido.blade.php
│   ├── aprovado.blade.php
│   ├── recusado.blade.php
│   └── codigo_recuperacao.blade.php
└── clientes/
    └── cadastro_realizado.blade.php

resources/js/
├── pages/
│   ├── AffiliateLanding/          # /cadastro-afiliado
│   └── AffiliatePanel/            # /afiliados
├── components/
│   ├── AffiliateSignupModal/
│   ├── AffiliateRecoveryModal/
│   ├── AffiliateProfileModal/
│   ├── AffiliatePasswordModal/
│   ├── AffiliateStatement/
│   ├── AffiliateClients/
│   └── AffiliateSettlements/
└── hooks/
    └── useAfiliado.js

public/images/afiliado.jpg
```

**Structure Decision**: monólito Laravel com Inertia. Backend no padrão de recursos de `clientes`
(spec 002) e da área do cliente (spec 007); frontend nas pastas da spec 005, com páginas novas no
`PublicLayout`.

## Complexity Tracking

| Violação | Por que é necessária | Alternativa mais simples rejeitada porque |
|---|---|---|
| Lançamento gravado dentro de `ProcessamentoDepositos` e `PagamentoSaques` | A comissão precisa nascer na mesma transação do dinheiro, com a regra da hora (FR-029, FR-033) | Calcular na consulta (como o antigo) repete os erros e não guarda o percentual da época; evento assíncrono pode perder o lançamento |
| Remover `codigo_afiliado` de `clientes` e `clientes_promocoes` | Regra da constituição para colunas criadas sem FK (specs 002 e 007) | Manter as duas colunas deixa duas fontes para a mesma ligação |
| Login único entre duas tabelas (`usuarios` e `afiliados`) | A mesma tela de entrada atende os dois públicos (FR-002, FR-016) | Permitir repetição faria o login do painel "esconder" o afiliado |
