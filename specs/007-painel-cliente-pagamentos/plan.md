# Implementation Plan: Painel do cliente online com depósito e saque

**Branch**: `007-painel-cliente-pagamentos` | **Date**: 2026-10-12 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/007-painel-cliente-pagamentos/spec.md`

## Summary

Cria a área do cliente `/minha-conta` (tela igual a `screens/credits` do antigo, modais novos) e o
fluxo de dinheiro pelo Mercado Pago:

- **Vínculo**: o admin escolhe "Mercado Pago" em "Gateway de pagamentos" e autoriza por OAuth na
  aplicação do programador; tokens criptografados em `gateways_pagamento`, renovados por comando.
- **Depósito**: Pix (QR Code em base64 do próprio Mercado Pago), cartão parcelado (campos seguros
  do MercadoPago.js) e NuPay quando a conta tiver; `application_fee` com o percentual de
  `configuracoes` (3%); confirmação por webhook assinado, fila e conferência a cada minuto;
  aprovação credita depósito, cashback (somado ao rollover), bônus de depósito (com afiliado e
  rollover primeiro depósito) e valida indicações; estorno deixa o saldo negativo.
- **Saque**: débito na hora, fila no painel (pagar/recusar) e pagamento automático pela API de
  Payouts até o limite configurado.
- **Bônus**: converter, excluir e expirar (`dias_cumprir` na promoção, `expira_em` no rollover).
- **Indicações**: código por cliente, válida no primeiro depósito, bônus a cada grupo.
- **Acesso assistido**: admin/supervisor abre a conta do cliente por código de uso único e token
  próprio, com autor registrado.

Decisões em [research.md](research.md) (R-01 a R-22), todas aprovadas.

## Technical Context

**Language/Version**: PHP 8.2 (^8.2) no backend; JavaScript (ES2022) com JSX no frontend

**Primary Dependencies**: as já instaladas (Laravel 12, Inertia 2, React 19, styled-components 6,
sweetalert2, `jwt-auth`, `spatie/laravel-permission`). **Nenhum pacote novo** no Composer nem no npm:
HTTP do Mercado Pago pelo cliente `Http` do Laravel (R-04); QR Code pela imagem que o Mercado Pago
devolve (R-07). **Script externo** MercadoPago.js v2, carregado só no cartão, justificado pela
captura segura obrigatória do cartão (R-08).

**Storage**: MySQL: 7 tabelas novas (`depositos`, `saques`, `gateways_pagamento`,
`notificacoes_mercado_pago`, `clientes_acessos_assistidos`, `clientes_indicacoes`,
`clientes_indicacoes_bonus`) e alterações em `clientes`, `clientes_promocoes`,
`clientes_rollovers` e `configuracoes`; fila `database` (já configurada); cache para o `state` do
OAuth e os meios da conta. No navegador: `sessionStorage` `wssports.acesso_assistido`.

**Testing**: nenhum teste automatizado (Constituição); validação manual pelo
[quickstart.md](quickstart.md)

**Target Platform**: navegadores atuais no desktop e no celular; servidor PHP 8.2 com
`queue:work` e `schedule:work`/cron; HTTPS público para o webhook e o retorno do OAuth

**Project Type**: aplicação web Laravel (monólito com Inertia) + API JWT existente

**Performance Goals**:

| Meta | Alvo |
|---|---|
| Depósito Pix confirmado na tela após o pagamento (SC-001) | ≤ 10 s em 95% (webhook + consulta a cada 3 s) |
| Resposta do webhook | < 1 s (grava e enfileira) |
| Webhook perdido | conferência a cada 1 min |
| Extrato | 30 por página (máx. 100) |

**Constraints**:

- dinheiro com `bcmath`/centavos; comissão arredondada meio para cima em centavos;
- idempotência: depósito bloqueado na aprovação/estorno, `X-Idempotency-Key` em pagamentos e saques;
- nenhuma credencial no código, no log, em resource ou na URL; tokens com cast `encrypted`;
- cartão nunca passa pelo servidor (token do MercadoPago.js);
- saldo só muda pelo `SaldoClientes` (débito com negativo só no estorno);
- paginação ≤ 100 (extrato, indicações, fila de saques);
- visual: tela igual a `screens/credits`; modais novos (exceção aprovada); cores pelo tema;
- nomes conforme a Constituição (tabelas/colunas em português; componentes em inglês PascalCase;
  funções/variáveis em snake_case português; hooks `useX`).

**Scale/Scope**:

| Item | Quantidade |
|---|---|
| Tabelas novas / alteradas | 7 / 4 |
| Migrations | 11 |
| Rotas da API novas / web novas | 21 / 2 |
| Controllers novos | 13 |
| Serviços novos | 11 (`ClienteMercadoPago`, `VinculoMercadoPago`, `CriacaoDepositos`, `ProcessamentoDepositos`, `EscolhaPromocoes`, `SolicitacaoSaques`, `PagamentoSaques`, `GestaoBonus`, `Indicacoes`, `AcessoAssistido`, `MensagensMercadoPago`) |
| Jobs / comandos novos | 2 / 3 |
| Componentes novos | 16 |
| Hooks / utilitários novos | 1 / 1 |

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Princípio / Regra | Verificação | Status |
|---|---|---|
| I. Nomes no backend | Tabelas e colunas em português (`depositos`, `saques`, `gateways_pagamento`, `notificacoes_mercado_pago`, `clientes_acessos_assistidos`, `clientes_indicacoes`, `dias_cumprir`, `expira_em`); classes em português PascalCase (`ProcessamentoDepositos`, `SolicitacaoSaques`); enums com valores em português; permissões `<recurso>.<acao>`; rotas em kebab-case. "Gateway" e "Mercado Pago" são nomes do domínio/produto | ✅ Pass |
| I. Nomes no frontend | Componentes em inglês PascalCase (`DepositModal`, `StatementTable`); funções e variáveis em snake_case português; hook `useMinhaConta`; utilitário `utils/mercado_pago.js`; props de estilo com `$` | ✅ Pass |
| II. Idioma | Artefatos e comentários em português | ✅ Pass |
| III. Componentes | Cada componente em pasta própria com `index.jsx` e `styles.jsx` | ✅ Pass |
| IV. Escopo estrito | Arquivos existentes listados abaixo, confirmados pelo responsável em 2026-10-12 | ✅ Pass |
| V. Legibilidade | Revisão ao final de cada tarefa | ✅ Pass |
| VI. Consistência | Saldo só pelo `SaldoClientes`; rollover pelo `RolloverClientes`; padrão de recursos (Request, Resource, permissões no `Funcao.php`); fila como `EstornarPromocao`; comandos agendados como os de apostas; modais sobre um `ModalCard` comum | ✅ Pass |
| VII. Sistema antigo | Arquivos e funcionamento em R-01/R-02; diferenças em R-03 aprovadas na spec e R-21/R-22 aprovadas em 2026-10-12 | ✅ Pass |
| VIII. Fidelidade visual | Tela igual a `screens/credits`; modais com visual novo aprovado (Clarifications) | ✅ Pass |
| IX. Dados fake | Nenhum dado fake | ✅ Pass |
| Stack: dependências | Nenhum pacote novo; script do MercadoPago.js justificado (R-08) | ✅ Pass |
| Timestamps e soft delete | Todas as tabelas novas com `timestamps()` + `softDeletes()` | ✅ Pass |
| Referências a tabelas inexistentes | `clientes_promocoes.codigo_afiliado` sem FK (tabela de afiliados não existe), registrado na spec e no data-model | ✅ Pass |
| Paginação ≤ 100 | Extrato (30/100), indicações (30/100), fila de saques (30/100) | ✅ Pass |
| Sem testes | Nenhum arquivo, tarefa ou dependência de teste | ✅ Pass |
| Postman | Coleção regenerada na mesma entrega | ✅ Pass |

**Resultado do gate**: aprovado; lista de arquivos existentes (Princípio IV) e R-21/R-22
(Princípio VII) confirmados pelo responsável em 2026-10-12. Reavaliado depois da Fase 1: sem
violação nova.

### Arquivos existentes que serão alterados (Princípio IV)

| Arquivo | Alteração | Motivo |
|---|---|---|
| `app/Enums/Funcao.php` | Constante `PERMISSOES_PAGAMENTOS` (`clientes.acessar_conta`, `saques.gerenciar`, `gateway_pagamento.vincular`); `permissoes_padrao` e `pode_usar` (gateway só Admin; demais Admin e Supervisor) | data-model §10 |
| `app/Enums/OrigemTransacao.php` | 8 casos novos | R-20 |
| `database/seeders/PapeisPermissoesSeeder.php` | Cria as permissões novas | data-model §10 |
| `database/seeders/ClientesPromocoesSeeder.php` | `dias_cumprir` e `quantidade_indicacoes` nas promoções padrão | FR-018h |
| `app/Models/Clientes.php` | `codigo_indicacao`, `indicado_por` e relações | R-17 |
| `app/Models/ClientesPromocoes.php` | `fillable`/`casts` dos 4 campos novos | R-15 |
| `app/Models/ClientesRollovers.php` | `casts` das datas novas; `scopePendentes` exclui expirados e excluídos | R-14 |
| `app/Models/Configuracoes.php` | `fillable`/`casts` das configurações de pagamento (sem a comissão) | R-18 |
| `app/Services/SaldoClientes.php` | Parâmetro `permitir_negativo` no `debitar` | R-12 |
| `app/Services/RolloverClientes.php` | `criar_bonus` grava `expira_em`; método `criar_deposito`; cancelamento por id | R-11, R-14 |
| `app/Services/CadastroClientes.php` | Gera `codigo_indicacao`; grava indicador; promoções de cadastro respeitam o afiliado | R-15, R-17 |
| `app/Http/Middleware/GarantirAcessoCliente.php` | Token assistido: acesso não encerrado nem vencido; mensagem de conta inativa | R-16, FR-005 |
| `app/Http/Requests/ClientesPromocoesRequest.php` | Regras e mensagens dos 4 campos | contracts §11 |
| `app/Http/Resources/ClientesPromocoesResource.php` | Devolve os 4 campos | contracts §11 |
| `app/Http/Requests/StoreClientesRequest.php` | `codigo_indicacao` opcional | contracts §11 |
| `app/Http/Requests/UpdateMeusDadosRequest.php` | `cpf`, `email` e `data_nascimento` aceitos só quando vazios | R-21 |
| `routes/api.php` | 21 rotas novas | contracts/api.md |
| `routes/web.php` | `minha-conta` e `mercado-pago/retorno` | contracts/paginas.md |
| `routes/console.php` | Agenda `depositos:conferir`, `bonus:expirar`, `mercado_pago:renovar_token` | R-05, R-10, R-14 |
| `config/services.php` | Bloco `mercado_pago` lendo o `.env` | R-06 |
| `.env.example` | Variáveis `MERCADO_PAGO_*` vazias | R-06 |
| `docs/postman/wssports_api.postman_collection.json` | Regenerada | Constituição |
| `resources/js/components/Header/index.jsx` e `styles.jsx` | "Olá, nome" vira link para `/minha-conta` | R-19 |
| `resources/js/components/AuthModal/index.jsx` | `codigo_indicacao` oculto; volta para `voltar` depois do login | contracts/paginas §2 |
| `resources/js/pages/Home/index.jsx` | Lê `?entrar`, `?voltar` e `?indicacao` | contracts/paginas §2 |
| `resources/js/hooks/useSessao.js` | Sessão assistida (`sessionStorage`) com prioridade na `/minha-conta` | R-16 |
| `resources/js/utils/storage.js` | Chave `wssports.acesso_assistido` | data-model §11 |

Nenhum outro arquivo das specs 001 a 006 muda.

## Project Structure

### Documentation (this feature)

```text
specs/007-painel-cliente-pagamentos/
├── spec.md
├── plan.md              # este arquivo
├── research.md          # R-01 a R-22
├── data-model.md        # tabelas, enums, permissões e estado no navegador
├── quickstart.md        # validação manual
├── contracts/
│   ├── api.md           # rotas novas e alteradas
│   └── paginas.md       # Account, GatewayReturn e mudanças na Home
├── checklists/
│   └── requirements.md
└── tasks.md             # /speckit-tasks
```

### Source Code (repository root)

```text
app/
├── Enums/
│   ├── MeioDeposito.php
│   ├── SituacaoDeposito.php
│   ├── SituacaoSaque.php
│   ├── ModoSaque.php
│   └── SituacaoGateway.php
├── Models/
│   ├── Depositos.php
│   ├── Saques.php
│   ├── GatewaysPagamento.php
│   ├── NotificacoesMercadoPago.php
│   ├── ClientesAcessosAssistidos.php
│   ├── ClientesIndicacoes.php
│   └── ClientesIndicacoesBonus.php
├── Services/
│   ├── ClienteMercadoPago.php          # chamadas HTTP (pagamentos, OAuth, meios, payouts) e assinatura
│   ├── VinculoMercadoPago.php          # autorização, retorno, renovação, desvínculo
│   ├── CriacaoDepositos.php            # validações e criação (Pix, cartão, NuPay)
│   ├── ProcessamentoDepositos.php      # aprovar, recusar, expirar, estornar (idempotente)
│   ├── EscolhaPromocoes.php            # promoção de depósito (afiliado, primeiro/qualquer)
│   ├── SolicitacaoSaques.php           # validações e débito
│   ├── PagamentoSaques.php             # baixa manual, recusa, envio automático
│   ├── GestaoBonus.php                 # converter, excluir, expirar, valor liberado
│   ├── Indicacoes.php                  # validar, pagar grupos, desfazer
│   ├── AcessoAssistido.php             # código, token, autor, encerramento
│   └── MensagensMercadoPago.php        # status_detail → mensagem em português
├── Jobs/
│   ├── ProcessarPagamentoMercadoPago.php
│   └── PagarSaqueMercadoPago.php
├── Console/Commands/
│   ├── ConferirDepositosCommand.php     # depositos:conferir
│   ├── ExpirarBonusCommand.php          # bonus:expirar
│   └── RenovarTokenMercadoPagoCommand.php  # mercado_pago:renovar_token
└── Http/
    ├── Controllers/
    │   ├── WebhooksMercadoPagoController.php
    │   ├── AreaClienteMinhaContaController.php
    │   ├── AreaClienteBonusController.php
    │   ├── AreaClienteDepositosController.php
    │   ├── AreaClienteSaquesController.php
    │   ├── AreaClienteIndicacoesController.php
    │   ├── AreaClienteAcessoAssistidoController.php
    │   ├── AcessosAssistidosController.php
    │   ├── SaquesController.php
    │   ├── ConfiguracoesPagamentosController.php
    │   ├── GatewayPagamentoController.php
    │   ├── MinhaContaController.php           # página Inertia
    │   └── RetornoMercadoPagoController.php   # página Inertia
    ├── Requests/
    │   ├── DepositosRequest.php
    │   ├── SaquesRequest.php
    │   ├── ExtratoMinhaContaRequest.php
    │   ├── RecusarSaqueRequest.php
    │   ├── PagarSaqueRequest.php
    │   ├── ConfiguracoesPagamentosRequest.php
    │   └── IniciarAcessoAssistidoRequest.php
    └── Resources/
        ├── MinhaContaResource.php
        ├── ExtratoMinhaContaResource.php
        ├── DepositosResource.php
        ├── SaquesResource.php
        ├── IndicacoesResource.php
        └── ConfiguracoesPagamentosResource.php

database/migrations/
├── xxxx_create_gateways_pagamento_table.php
├── xxxx_create_depositos_table.php
├── xxxx_create_saques_table.php
├── xxxx_create_notificacoes_mercado_pago_table.php
├── xxxx_create_clientes_acessos_assistidos_table.php
├── xxxx_create_clientes_indicacoes_bonus_table.php
├── xxxx_create_clientes_indicacoes_table.php
├── xxxx_add_indicacao_clientes_table.php            # codigo_indicacao (preenche), indicado_por
├── xxxx_add_campos_clientes_promocoes_table.php     # dias_cumprir, rollover_primeiro_deposito, codigo_afiliado, quantidade_indicacoes
├── xxxx_add_expiracao_clientes_rollovers_table.php  # expira_em (preenche), expirado_em, excluido_em, convertido_em
└── xxxx_add_pagamentos_configuracoes_table.php

resources/js/
├── pages/
│   ├── Account/                        # /minha-conta
│   └── GatewayReturn/                  # retorno do OAuth
├── components/
│   ├── AccountHeader/                  # voltar, saudação, menu (visual do antigo)
│   ├── BalanceCards/                   # três saldos
│   ├── StatementFilters/               # filtros de operação e situação
│   ├── StatementTable/                 # tabela do extrato com rolagem infinita
│   ├── AccountMenu/                    # menu lateral/gaveta
│   ├── InactiveAccount/                # conta inativa
│   ├── AssistedBanner/                 # tarja do acesso assistido
│   ├── ModalCard/                      # base visual dos modais novos
│   ├── DepositModal/                   # etapas do depósito
│   ├── PixCharge/                      # QR Code e copia e cola
│   ├── CardPayment/                    # campos seguros e parcelas
│   ├── WithdrawModal/                  # saque
│   ├── ProfileModal/                   # Meus dados
│   ├── ReferralModal/                  # Indique um amigo
│   ├── ReferralsModal/                 # Minhas indicações
│   └── ConfirmModal/                   # confirmações (converter, excluir, sair) no visual novo
├── hooks/
│   └── useMinhaConta.js
└── utils/
    └── mercado_pago.js                 # carrega o MercadoPago.js e monta os campos seguros
```

**Structure Decision**: monólito Laravel com Inertia (spec 005). Backend no padrão de recursos das
specs 002 a 006; frontend nas pastas da spec 005; nova página fora da Home, no `PublicLayout`.

## Complexity Tracking

| Violação | Por que é necessária | Alternativa mais simples rejeitada porque |
|---|---|---|
| Script externo MercadoPago.js | Captura do cartão sem o número passar pelo servidor (PCI) | Formulário próprio exigiria certificação PCI; Card Payment Brick impõe o visual dele |
| `SaldoClientes::debitar` com saldo negativo | Estorno de cartão e cancelamento de bônus de indicação depois de gasto (decisão da spec) | Recusar o estorno deixaria o crédito indevido no saldo |
| Valor liberado por carteira, não por bônus (R-22) | O sistema novo não guarda saldo por bônus e prêmios ainda não são pagos | Controle por bônus mexeria em apostas e na apuração, fora desta spec |
| URL de retorno do OAuth por instalação | Tokens não trafegam entre servidores | Endereço central exige servidor sempre no ar e repassava tokens na URL |
