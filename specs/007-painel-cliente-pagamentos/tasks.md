---

description: "Lista de tarefas da feature Painel do cliente online com depósito e saque"
---

# Tasks: Painel do cliente online com depósito e saque

**Input**: Documentos de design em `specs/007-painel-cliente-pagamentos/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md),
[data-model.md](data-model.md), [contracts/api.md](contracts/api.md),
[contracts/paginas.md](contracts/paginas.md), [quickstart.md](quickstart.md)

**Tests**: NÃO há tarefas de teste: a constituição proíbe testes automatizados. Cada user story é
validada manualmente pelos roteiros do [quickstart.md](quickstart.md).

**Organization**: tarefas agrupadas por user story. Ordem de execução: US1, US8, US2, US3 (P1);
US4, US5, US6, US7 (P2); US9 (P3). A US8 (vínculo do Mercado Pago) vem antes da US2 porque todo
depósito e saque automático depende dela.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: pode rodar em paralelo (arquivos diferentes, sem dependências pendentes)
- **[Story]**: user story da tarefa (US1 a US9)
- Caminhos relativos à raiz do repositório

## Regras que valem para TODAS as tarefas

- **Sistema antigo (Princípios VII e VIII)**: `C:\Users\wedso\OneDrive\Área de Trabalho\projetos\wssports.bet`
  é **somente leitura**. Referências por assunto no [research.md](research.md) R-01; funcionamento
  no R-02; diferenças aprovadas no R-03, R-21 e R-22.
- **Visual**: a tela `/minha-conta` reproduz `resources/js/screens/credits/index.js` e `styles.js`
  do antigo (medidas, textos, ícones Material Icons, ordem, breakpoint de 900px), com as cores
  pelos tokens do tema. **Só os modais** usam o visual novo, sempre sobre o componente
  `ModalCard` (T020), no padrão do `TableModal`/`MatchDetailsModal` refeitos.
- **Nomes (Princípio I)**: backend em português (classes `PascalCase`, resto `snake_case`);
  métodos do framework com o nome original; permissões `<recurso>.<acao>`; rotas em kebab-case.
  Frontend: componentes e styled-components em inglês `PascalCase`; variáveis, funções, props e
  chaves em `snake_case` português; hooks `useXxx` em português; props só de estilo com `$`.
- **Componentes (Princípio III)**: pasta própria com `index.jsx` e `styles.jsx`.
- **Idioma (Princípio II)**: comentários, textos e mensagens em português.
- **Escopo (Princípio IV)**: arquivos existentes marcados com ⚠️ estão na tabela do
  [plan.md](plan.md) e foram confirmados pelo responsável em 2026-10-12. Nenhum outro arquivo
  existente pode ser alterado; se for preciso, avisar antes.
- **Postman (constituição)**: toda tarefa que cria ou altera rota em `routes/api.php` regenera, na
  mesma tarefa, `docs/postman/wssports_api.postman_collection.json` ⚠️ (substituindo o arquivo,
  mantendo `base_url` e `token`).
- **Consistência (Princípio VI)**: recursos do painel no padrão de `ClientesPromocoesController`
  (controller com `HasMiddleware` + `GarantirPermissaoCliente::permissao_cliente`, `FormRequest`
  com `rules()`/`messages()` em português, `JsonResource`, `por_pagina` ≤ 100); área do cliente no
  padrão de `AreaClienteMeusDadosController` (`$request->user('clientes')`); saldo **só** pelo
  `SaldoClientes`; rollover **só** pelo `RolloverClientes`; jobs no padrão de `EstornarPromocao`;
  comandos no padrão de `ExpirarApostasPendentesCommand`.
- **Dinheiro**: `decimal(15,2)` + `bcmath`/centavos (`SaldoClientes::para_centavos`) no backend;
  centavos inteiros no frontend (`utils/money.js`). Nunca `float`.
- **Segredos**: tokens e credenciais do Mercado Pago nunca em log, resource, resposta, URL ou
  código; só `config('services.mercado_pago.*')` e o model `GatewaysPagamento` (cast `encrypted`).
- **Legibilidade (Princípio V)**: ao concluir cada tarefa, revisar o código alterado e informar se
  houve ou não refatoração.
- **Banco**: tabelas novas com `timestamps()` + `softDeletes()` e model com `SoftDeletes`; datas em
  UTC; "dia" do cliente em `America/Sao_Paulo`.

---

## Phase 1: Setup (infraestrutura compartilhada)

**Purpose**: configuração do Mercado Pago e enums.

- [ ] T001 ⚠️ Em `config/services.php`, bloco `'mercado_pago' => ['client_id' => env('MERCADO_PAGO_CLIENT_ID'), 'client_secret' => env('MERCADO_PAGO_CLIENT_SECRET'), 'public_key' => env('MERCADO_PAGO_PUBLIC_KEY'), 'webhook_secret' => env('MERCADO_PAGO_WEBHOOK_SECRET'), 'payouts_chave_privada' => env('MERCADO_PAGO_PAYOUTS_CHAVE_PRIVADA'), 'url_api' => 'https://api.mercadopago.com', 'url_autorizacao' => 'https://auth.mercadopago.com.br/authorization']` com comentário (credenciais da aplicação do programador, conta master; R-06); ⚠️ em `.env.example`, as cinco variáveis `MERCADO_PAGO_*` vazias com comentário
- [ ] T002 [P] Criar os enums em `app/Enums/` (data-model §9), cada um com comentário: `MeioDeposito.php` (`Pix = 'Pix'`, `CartaoCredito = 'Cartão de crédito'`), `SituacaoDeposito.php` (`Pendente`, `EmAnalise = 'Em análise'`, `Aprovado`, `Recusado`, `Expirado`, `Cancelado`, `Estornado`; método `final(): bool` para `Recusado`, `Expirado`, `Cancelado`, `Estornado`), `SituacaoSaque.php` (`Pendente`, `Processando`, `Pago`, `Recusado`), `ModoSaque.php` (`Manual = 'Manual'`, `Automatico = 'Automático'`) e `SituacaoGateway.php` (`Vinculado`, `FalhaRenovacao = 'Falha na renovação'`, `Desvinculado`)
- [ ] T003 [P] ⚠️ Em `app/Enums/OrigemTransacao.php`, acrescentar `Deposito = 'Depósito'`, `Cashback = 'Cashback'`, `Saque = 'Saque'`, `DevolucaoSaque = 'Devolução de saque'`, `EstornoDeposito = 'Estorno de depósito'`, `ConversaoBonus = 'Conversão de bônus'`, `BonusExpirado = 'Bônus expirado'` e `BonusExcluido = 'Bônus excluído'` (R-20)

**Checkpoint**: `php artisan config:show services.mercado_pago` lista as chaves (vazias).

---

## Phase 2: Foundational (pré-requisitos de várias stories)

**Purpose**: permissões, configurações de pagamento, colunas novas em tabelas existentes,
serviços de saldo/rollover, cliente HTTP do Mercado Pago e base visual dos modais.

**⚠️ CRITICAL**: todas as stories dependem desta fase.

### Banco

- [ ] T004 Criar `database/migrations/2026_10_12_000001_add_pagamentos_configuracoes_table.php`: em `configuracoes`, com comentário por coluna, `gateway_pagamento` `string(30)->nullable()`, `percentual_comissao_gateway` `decimal(5,2)->default(3.00)` (comentário: "comissão da conta master; só no banco, nunca exibida"), `depositos_habilitados` `boolean()->default(true)`, `saques_habilitados` `boolean()->default(true)`, `deposito_minimo` `decimal(15,2)->default(1.00)`, `deposito_maximo` `decimal(15,2)->default(50000.00)`, `saque_minimo` `decimal(15,2)->default(10.00)`, `rollover_deposito` `boolean()->default(true)`, `rollover_deposito_vezes` `unsignedTinyInteger()->default(1)`, `cashback_percentual` `decimal(5,2)->default(0)`, `modo_saque` `string(20)->default('Manual')`, `saque_automatico_maximo` `decimal(15,2)->default(1000.00)`, `meios_deposito` `json()->nullable()` (preencher depois do `Schema::table` com `DB::table('configuracoes')->update(['meios_deposito' => json_encode(['Pix', 'Cartão de crédito'])])`), `parcelas_maximas` `unsignedTinyInteger()->default(12)`, `minutos_expiracao_pix` `unsignedSmallInteger()->default(30)`, `minutos_acesso_assistido` `unsignedSmallInteger()->default(60)`; `down()` remove todas (data-model §8)
- [ ] T005 [P] Criar `database/migrations/2026_10_12_000002_add_campos_clientes_promocoes_table.php`: `dias_cumprir` `unsignedSmallInteger()->default(30)` (dias para cumprir o rollover do bônus), `rollover_primeiro_deposito` `boolean()->default(false)`, `codigo_afiliado` `string(50)->nullable()` (comentário: "sem chave estrangeira: a tabela de afiliados ainda não existe; ajustar na spec de afiliados"), `quantidade_indicacoes` `unsignedSmallInteger()->nullable()`; depois, `DB::table('clientes_promocoes')->where('categoria', 'Indicação')->whereNull('quantidade_indicacoes')->update(['quantidade_indicacoes' => 5])`; `down()` remove (R-15)
- [ ] T006 [P] Criar `database/migrations/2026_10_12_000003_add_expiracao_clientes_rollovers_table.php`: `expira_em`, `expirado_em`, `excluido_em` e `convertido_em` `timestamp()->nullable()`; índice `(tipo, expira_em)`; depois, preencher `expira_em = DATE_ADD(created_at, INTERVAL 30 DAY)` nos rollovers `tipo = 'Bônus'` sem `cumprido_em` nem `cancelado_em` (pelo query builder com `DB::raw` só na expressão da data); `down()` remove (R-14)
- [ ] T007 [P] Criar `database/migrations/2026_10_12_000004_add_indicacao_clientes_table.php`: em `clientes`, `codigo_indicacao` `string(8)->nullable()->unique()` e `indicado_por` `foreignId()->nullable()->constrained('clientes')`; depois, gerar para cada cliente existente (em lotes de 500, `chunkById`) um código de 8 caracteres maiúsculos sem `0`, `O`, `1`, `I` (alfabeto `ABCDEFGHJKLMNPQRSTUVWXYZ23456789`, `random_int`), repetindo se já existir; `down()` remove FK e colunas (R-17)
- [ ] T008 ⚠️ Em `app/Models/Configuracoes.php`: `$fillable` com todas as colunas de T004 **exceto** `percentual_comissao_gateway` (comentário: "só alterada direto no banco, FR-028"); `casts` (`boolean`, `decimal:2`, `integer`, `meios_deposito` `array`); método `meios_habilitados(): array` (`meios_deposito ?? ['Pix', 'Cartão de crédito']`)
- [ ] T009 [P] ⚠️ Em `app/Models/ClientesPromocoes.php`: `fillable` e `casts` de `dias_cumprir` (`integer`), `rollover_primeiro_deposito` (`boolean`), `codigo_afiliado`, `quantidade_indicacoes` (`integer`); `$attributes` `dias_cumprir => 30`, `rollover_primeiro_deposito => false`; scope `paraAfiliado(Builder $consulta, ?string $codigo_afiliado)` → com código: `where(fn => whereNull('codigo_afiliado')->orWhere('codigo_afiliado', $codigo))`; sem código: `whereNull('codigo_afiliado')` (R-15)
- [ ] T010 [P] ⚠️ Em `app/Models/ClientesRollovers.php`: `casts` `datetime` de `expira_em`, `expirado_em`, `excluido_em`, `convertido_em`; `scopePendentes` passa a exigir também `whereNull('expirado_em')->whereNull('excluido_em')` (comentário: bônus expirado ou excluído não recebe mais apostas) (R-14)
- [ ] T011 [P] ⚠️ Em `app/Models/Clientes.php`: `codigo_indicacao` e `indicado_por` **fora** do `$fillable` (comentário: gravados só pelo cadastro); relações `indicador(): BelongsTo` (`indicado_por`) e `rollovers(): HasMany`; método estático `gerar_codigo_indicacao(): string` com o mesmo alfabeto de T007, tentando de novo enquanto existir (R-17)

### Permissões

- [ ] T012 ⚠️ Em `app/Enums/Funcao.php`: constante `PERMISSOES_PAGAMENTOS = ['clientes.acessar_conta', 'saques.gerenciar', 'gateway_pagamento.vincular']` com comentário; incluir em `permissoes_padrao()` dos gestores (filtrada por `pode_usar`); em `pode_usar()`, antes dos demais testes: `gateway_pagamento.vincular` → só `self::Admin`; as outras duas → `Admin` ou `Supervisor` (data-model §10)
- [ ] T013 ⚠️ Em `database/seeders/PapeisPermissoesSeeder.php`, incluir `...Funcao::PERMISSOES_PAGAMENTOS` na criação (`Permission::firstOrCreate` com `guard_name` `api`) e entregar as permissões novas aos usuários existentes conforme `permissoes_padrao()` da função (mesmo trecho que já entrega as demais); `forgetCachedPermissions` no fim

### Serviços base

- [ ] T014 ⚠️ Em `app/Services/SaldoClientes.php`: parâmetro nomeado `bool $permitir_negativo = false` em `debitar` (repassado a `movimentar`), que pula o teste de `SaldoInsuficienteException` quando `true`; comentário: "só o estorno de depósito e o cancelamento de bônus de indicação podem deixar o saldo negativo (R-12)". `para_centavos` já trata o sinal; conferir `para_decimal` com negativo
- [ ] T015 ⚠️ Em `app/Services/RolloverClientes.php` (R-11, R-14): (a) `criar_bonus` grava `expira_em = now()->addDays($promocao->dias_cumprir ?? 30)`; (b) novo `criar_deposito(Clientes $cliente, ClientesTransacoes $transacao, int $vezes, string $acrescimo = '0.00'): ?ClientesRollovers` — `valor_exigido = bcadd(bcmul(valor, vezes, 2), acrescimo, 2)`; não cria se o exigido for 0; `tipo` Depósito, carteira Saldo, `vezes`, `valor_creditado = transacao.valor`, sem regras de uso nem expiração; (c) novo `cancelar(ClientesRollovers $rollover): void` → `cancelado_em = now()` se ainda pendente; (d) novo `restante_deposito(int $clientes_id): string` → soma de `valor_exigido − valor_apostado` dos rollovers de Depósito pendentes (`bcmath`, `'0.00'` sem nenhum)
- [ ] T016 Criar `app/Services/ClienteMercadoPago.php` (R-04 a R-10, R-13): única classe que fala com a API do Mercado Pago, pelo `Http` do Laravel (`timeout(30)`, `acceptJson()`, `throw()` tratado). Métodos: `url_autorizacao(string $state): string`; `trocar_codigo(string $code): array` e `renovar_token(string $refresh_token): array` (`POST /oauth/token`, `grant_type` `authorization_code`/`refresh_token`, com `client_id`, `client_secret` e `redirect_uri = url('/mercado-pago/retorno')`); `criar_pagamento(array $corpo, string $chave_idempotencia): array` (`POST /v1/payments`, `X-Idempotency-Key`); `consultar_pagamento(string $id): array`; `pagar_pix_saque(array $corpo, string $chave_idempotencia): array` (`POST /v1/transaction-intents/process`, `X-Idempotency-Key`; com `services.mercado_pago.payouts_chave_privada` preenchida, cabeçalho `X-Signature` com a assinatura do corpo em base64 por `openssl_sign` SHA256); `consultar_saque(string $id): array` (antes de implementar os dois métodos de saque, conferir na documentação oficial de Payouts do Mercado Pago o corpo, os cabeçalhos e o formato da `X-Signature` e registrar no research R-13); `assinatura_valida(?string $x_signature, ?string $x_request_id, string $data_id): bool` (manifesto `id:{data_id};request-id:{x_request_id};ts:{ts};`, `hash_hmac('sha256', ..., webhook_secret)`, `hash_equals` com `v1`; falso sem segredo ou cabeçalho). O access token vem de `GatewaysPagamento::token_atual()` (T037); sem vínculo, lança `GatewayIndisponivelException` (criar em `app/Exceptions/GatewayIndisponivelException.php` com a mensagem "Depósitos indisponíveis no momento. Tente mais tarde."). Erros de HTTP logados **sem** cabeçalhos nem corpo com token
- [ ] T017 [P] Criar `app/Services/MensagensMercadoPago.php`: `recusa(?string $status_detail): string` com o mapa em português dos `status_detail` mais comuns (`cc_rejected_insufficient_amount` → "Saldo insuficiente no cartão.", `cc_rejected_bad_filled_card_number`/`_date`/`_security_code`/`_other` → "Confira os dados do cartão.", `cc_rejected_call_for_authorize` → "Autorize o pagamento com o banco emissor e tente de novo.", `cc_rejected_card_disabled` → "Cartão desativado. Fale com o banco emissor.", `cc_rejected_duplicated_payment` → "Pagamento duplicado. Aguarde alguns minutos.", `cc_rejected_high_risk` → "Pagamento recusado por segurança. Tente outro meio.", `cc_rejected_max_attempts` → "Limite de tentativas atingido. Tente outro cartão.", padrão → "Pagamento recusado pelo banco emissor. Tente outro cartão.") (FR-026)
- [ ] T018 ⚠️ Em `app/Http/Middleware/GarantirAcessoCliente.php`: cliente inativo (`ativo = false`, não excluído) → `403 {"message": "Pagamento pendente ou conta desativada."}` (FR-005); excluído continua com a mensagem atual
- [ ] T019 Rodar `php artisan migrate` e `php artisan db:seed --class=PapeisPermissoesSeeder`; conferir as colunas novas, `clientes.codigo_indicacao` preenchido e as permissões no admin

### Frontend base

- [ ] T020 [P] Criar `resources/js/components/ModalCard/index.jsx` e `styles.jsx`: base visual dos modais novos (cartão branco `min(520px, calc(100vw - 32px))`, cantos 16px, sombra, cabeçalho `#1c1c1c` com faixa inferior de 3px `theme.principal`, título e subtítulo opcionais, botão fechar redondo, corpo rolável, rodapé opcional, transição de opacidade/translate, `prefers-reduced-motion`; no mobile ≤ 900px, folha de baixo para cima com cantos superiores arredondados e `safe-area-inset-bottom`), com `Backdrop`, `useVoltarFecha` e Escape; props `aberto`, `titulo`, `subtitulo`, `ao_fechar`, `rodape`, `largura`, `children`. Exporta também `ModalButton` (`$secundario`, `$cor`) no padrão dos botões do `TableModal` refeito
- [ ] T021 [P] Criar `resources/js/components/ConfirmModal/index.jsx` e `styles.jsx` sobre o `ModalCard`: ícone, mensagem, botões "Cancelar" (secundário) e confirmar (rótulo por prop), estado `enviando` com `Spinner`; props `aberto`, `titulo`, `mensagem`, `rotulo_confirmar`, `ao_confirmar` (async), `ao_fechar`

**Checkpoint**: migrations e seeders aplicados; `ModalCard` e `ConfirmModal` renderizam (montar temporariamente na Home para ver e retirar).

---

## Phase 3: User Story 1 - Cliente acompanha saldos e extrato (Priority: P1) 🎯 MVP

**Goal**: `/minha-conta` com saudação, logo, três saldos, aviso, filtros, extrato paginado, menu
e tela de conta inativa, igual ao `screens/credits` do antigo.

**Independent Test**: [quickstart.md](quickstart.md) seção 3.

### Backend

- [ ] T022 [P] [US1] Criar `app/Http/Requests/ExtratoMinhaContaRequest.php`: `operacao` `nullable|in:palpites,depositos,saques,bonus,cashback,estornos`; `situacao` `nullable|in:Aguardando,Vencedor,Perdedor` e `prohibited_unless:operacao,palpites`; `pagina` `nullable|integer|min:1`; `por_pagina` `nullable|integer|between:1,100`; mensagens em português
- [ ] T023 [US1] Criar `app/Http/Resources/MinhaContaResource.php` (contracts/api.md §1): `cliente` (id, nome, ativo, codigo_indicacao), `saldos` (para Saldo, Promoção esportes e Promoção cassino: `valor`, `anterior` = `saldo_anterior` da última `clientes_transacoes` da carteira ou o próprio valor sem transações, `meta` = soma `valor_exigido − valor_apostado` dos rollovers pendentes — Depósito no saldo, Bônus por carteira —, `negativo` só no saldo), `cadastro.falta_para_depositar` (`cpf`, `email` vazios) e `falta_para_sacar` (`email`, `cpf`, `pix` = sem meio Pix principal), `menu.indicacao` (promoção de Indicação vigente: `vigente`, `quantidade`, `valor`; senão `vigente: false`) e `acesso_assistido` (preenchido na US7; por ora `null`)
- [ ] T024 [US1] Criar `app/Http/Resources/ExtratoMinhaContaResource.php` (contracts/api.md §2, R-20): `id`, `data` (ISO -03:00; no depósito, `pago_em`), `operacao` (rótulo pela origem: Aposta → "Palpite", Prêmio → "Prêmio", Promoção → "Bônus", AjusteManual → "Ajuste", Estorno → "Estorno", demais pelo valor do enum), `tipo`, `valor`; `aposta` (código, prêmio, resultado, cancelada) quando a origem é Aposta/Prêmio (relação por `referencia_id`, carregada em lote); `rollover` (`exigido`, `restante`) quando houver rollover ligado à transação; `bonus` (rollover_id, restante, `expira_em_dias` = dias inteiros até `expira_em` ou 0, `expirado`, e `liberado`/`pode_converter`/`pode_excluir` = `"0.00"`/`false`/`false` até a US5); `saque` (situação, motivo) quando houver saque ligado (US3)
- [ ] T025 [US1] Criar `app/Http/Controllers/AreaClienteMinhaContaController.php`: `show` → `MinhaContaResource` do `$request->user('clientes')`; `extrato` → `clientes_transacoes` do cliente filtradas pelo `ExtratoMinhaContaRequest` (mapa operação → origens: palpites = Aposta e Prêmio; depositos = Depósito; saques = Saque e Devolução de saque; bonus = Promoção, Estorno (estorno de promoção da spec 002), Conversão de bônus, Bônus expirado, Bônus excluído; cashback = Cashback; estornos = Estorno de depósito; `situacao` filtra pela aposta ligada com `whereExists` em `apostas.resultado`), `orderByDesc('created_at')->orderByDesc('id')`, `paginate(por_pagina ?? 30, ['*'], 'pagina')`, com eager loading sem N+1
- [ ] T026 [US1] ⚠️ Em `routes/api.php`, no grupo autenticado de `area-cliente`, `GET minha-conta` e `GET minha-conta/extrato`; ⚠️ regenerar o Postman
- [ ] T027 [US1] Criar `app/Http/Controllers/MinhaContaController.php`: `index(Request)` → `Inertia::render('Account', ['acesso' => $request->query('acesso')])`; ⚠️ em `routes/web.php`, `Route::get('minha-conta', ...)->name('minha_conta')`

### Frontend

- [ ] T028 [P] [US1] Criar `resources/js/hooks/useMinhaConta.js`: carrega `GET /area-cliente/minha-conta` (estados `dados`, `carregando`, `inativa` em `403`, `expirada` em `401`) e o extrato paginado (`filtros`, `itens`, `carregar_mais`, `fim`, `recarregar`), cancelando pedidos antigos ao trocar filtro; `recarregar_tudo()` para depois de depósito, saque e bônus
- [ ] T029 [P] [US1] Criar `resources/js/components/AccountHeader/` (voltar `keyboard_arrow_left` para `/`, "Olá {nome} Bem Vindo!", ícone `more_vert` que abre o menu no mobile; logo da banca abaixo) igual ao `Header`/`Wellcome`/`Logo` de `screens/credits/styles.js`
- [ ] T030 [P] [US1] Criar `resources/js/components/BalanceCards/` com os três cartões (Meu saldo, Bônus esportes, Bônus cassino; "Saldo anterior R$ X" e "Meta a cumprir R$ X"; `--,--` sem valor) igual a `Balances`/`Balance` do antigo; saldo negativo em vermelho com "Saldo negativo por estorno de pagamento" (FR-007); e o aviso "Obs.: Para apostas premiadas o novo saldo estará disponível até 24 hrs." (`Message`)
- [ ] T031 [P] [US1] Criar `resources/js/components/StatementFilters/` (ícone `filter_list` ou `Spinner` carregando; selects "Todas operações/Palpites/Depósitos/Saque/Bônus/Cashback/Estornos" e "Todos palpites/Aguardando/Perdedor/Vencedor", o segundo desabilitado fora de Palpites) igual a `ContainerFilter`/`Select`
- [ ] T032 [P] [US1] Criar `resources/js/components/StatementTable/` (cabeçalho DATA, OP., VALOR, PRÊMIO, EXPIRA EM, ROLLOVER, V. LIBERADO, SITUAÇÃO, AÇÃO na cor do tema; linhas alternadas; ícones de situação do antigo — `query_builder`, `emoji_events`, `highlight_off`, `not_interested`, `add` verde, `remove` vermelho, `attach_money`, `remove_red_eye` —; "---" nas colunas que não se aplicam; "Expirado"/"N dia(s)"; rolagem horizontal no mobile como no antigo; sentinela com `IntersectionObserver` chamando `carregar_mais`; "Nenhum registro encontrado"); props `itens`, `ao_tocar_linha`, `acoes_bonus` (render prop, usada na US5)
- [ ] T033 [P] [US1] Criar `resources/js/components/AccountMenu/` (fixo à direita no desktop, gaveta com `Backdrop` no mobile; cabeçalho "Menu" e fechar; itens com ícones do antigo: Meus dados `edit_calendar`, Realizar aposta `arrow_back`, Solicitar saque `download`, Realizar depósito `account_balance_wallet`, Indique um amigo `group_add` (só com `indicacao.vigente`), Atendimento `support_agent` (só com WhatsApp), Regras `receipt`, Sair `exit_to_app`); props `aberto`, `ao_fechar`, `itens_visiveis`, `ao_escolher(chave)`
- [ ] T034 [P] [US1] Criar `resources/js/components/InactiveAccount/` ("Pagamento pendente ou conta desativada.", botões "Atualizar" e "Sair") igual a `Pending` do antigo
- [ ] T035 [US1] Criar `resources/js/pages/Account/index.jsx` e `styles.jsx` (layout `PublicLayout`, contracts/paginas.md §1): sem sessão de cliente → `router.visit('/?entrar=1&voltar=/minha-conta')`; monta `AccountHeader`, `BalanceCards`, `StatementFilters`, `StatementTable`, `AccountMenu`, `InactiveAccount`; tocar em palpite abre o `TicketModal` existente com o código; tocar em saque recusado mostra o motivo (`ConfirmModal` só com "Fechar" ou alerta do `utils/alerts.js`); "Sair" com `ConfirmModal` ("Deseja sair da sua conta e voltar para tela de login?") e logout pelo `useSessao`; "Realizar aposta" e "Regras" navegam; "Atendimento" abre o WhatsApp de `usePage().props.contatos.whatsapp` (prop compartilhada já existente; continua fake até a spec que trouxer os contatos reais); Depositar, Sacar, Meus dados e indicações ficam como pontos de ligação para as próximas stories
- [ ] T036 [US1] ⚠️ Em `resources/js/components/Header/index.jsx` e `styles.jsx`, com `sessao.tipo === 'cliente'`, o "Olá, nome" vira `Link` do Inertia para `/minha-conta` (cursor, hover); ⚠️ em `resources/js/pages/Home/index.jsx`, ler `?entrar=1` (abre o `AuthModal` na aba Entrar) e `?voltar=` (só caminho interno começando por `/` e sem `//`) e, depois do login de cliente, `router.visit(voltar)`; apagar os dois parâmetros da URL com `history.replaceState`; ⚠️ em `resources/js/components/AuthModal/index.jsx`, prop `ao_entrar_com_sucesso` chamada depois do login (contracts/paginas.md §2)

**Checkpoint**: quickstart seção 3 completo.

---

## Phase 4: User Story 8 - Banca vincula a conta do Mercado Pago (Priority: P1)

**Goal**: o admin vincula a conta pelo campo "Gateway de pagamentos" (OAuth) e configura os
pagamentos; o token é renovado sozinho.

**Independent Test**: [quickstart.md](quickstart.md) seção 2.

- [ ] T037 [US8] Criar `database/migrations/2026_10_12_000005_create_gateways_pagamento_table.php` (data-model §3: `gateway` `string(30)`, `situacao` `string(20)`, `access_token` `text`, `refresh_token` `text`, `conta_id` `string(40)`, `expira_em` `timestamp`, `vinculado_em` `timestamp`, `vinculado_por` FK `usuarios`, `renovado_em` `timestamp nullable`, `falha_renovacao` `string(255) nullable`, timestamps, softDeletes) e `app/Models/GatewaysPagamento.php` (`SoftDeletes`; casts `access_token`/`refresh_token` `encrypted`, enum `SituacaoGateway`, datas; `$hidden` com os dois tokens; estático `atual(): ?self` = último não removido com situação ≠ Desvinculado; estático `token_atual(): string` lança `GatewayIndisponivelException` sem vínculo ou com `expira_em` passado)
- [ ] T038 [US8] Criar `app/Services/VinculoMercadoPago.php` (R-05): `iniciar(Usuarios $admin): string` (sem `client_id`/`client_secret` → `ValidationException` "Credenciais da aplicação não configuradas no servidor."; `state` = `Str::random(40)` em `Cache::put("mercado_pago.state.{$state}", $admin->id, 600)`; devolve `ClienteMercadoPago::url_autorizacao`); `concluir(?string $code, ?string $state, ?string $erro): array{sucesso: bool, mensagem: string}` (`Cache::pull` do state — inexistente → "Pedido de vínculo inválido ou expirado. Tente de novo pelo painel."; `erro` → "Vínculo cancelado no Mercado Pago."; troca o código; numa transação, marca o vínculo anterior como `Desvinculado` (soft delete) e cria o novo `Vinculado` com `expira_em = now + expires_in`, `conta_id = user_id`; grava `configuracoes.gateway_pagamento = 'Mercado Pago'`; mensagem "Conta do Mercado Pago vinculada com sucesso."); `renovar(): void` (faltando < 30 dias: `renovar_token`; sucesso grava tokens, `expira_em`, `renovado_em`, `situacao` Vinculado e limpa falha; erro grava `falha_renovacao` e `situacao` Falha na renovação, sem apagar o token válido); `desvincular(): void` (situação Desvinculado, tokens apagados, soft delete; `gateway_pagamento = null`)
- [ ] T039 [US8] Criar `app/Http/Controllers/GatewayPagamentoController.php` (`gateway_pagamento.vincular`): `show` → gateway, situação, `vinculado_em`, `expira_em`, `falha_renovacao` (nunca tokens; sem vínculo, situação "Desvinculado"); `autorizar` → `{"url": ...}`; `destroy` → `204`; ⚠️ em `routes/api.php`, as três rotas no grupo do painel (contracts/api.md §10); ⚠️ regenerar o Postman
- [ ] T040 [US8] Criar `app/Http/Controllers/RetornoMercadoPagoController.php` (`index` → `VinculoMercadoPago::concluir($request->query('code'), $request->query('state'), $request->query('error'))` e `Inertia::render('GatewayReturn', $resultado)`); ⚠️ em `routes/web.php`, `Route::get('mercado-pago/retorno', ...)->name('mercado_pago.retorno')`; criar `resources/js/pages/GatewayReturn/index.jsx` e `styles.jsx` (logo, ícone `check_circle`/`error`, mensagem e botão "Fechar" com `window.close()`, cores do tema)
- [ ] T041 [P] [US8] Criar `app/Http/Requests/ConfiguracoesPagamentosRequest.php` (data-model §8: `depositos_habilitados`/`saques_habilitados`/`rollover_deposito` boolean; `deposito_minimo` numeric `min:1` com 2 casas `lte:deposito_maximo`; `deposito_maximo`; `saque_minimo` `min:0.01`; `rollover_deposito_vezes` `integer|between:1,20`; `cashback_percentual` `numeric|between:0,100`; `modo_saque` `Rule::enum(ModoSaque)`; `saque_automatico_maximo` `min:0.01`; `meios_deposito` `array|min:1` com `in:Pix,Cartão de crédito`; `parcelas_maximas` `integer|between:1,12`; `minutos_expiracao_pix` `integer|between:5,1440`; `minutos_acesso_assistido` `integer|between:5,480`; tudo `sometimes`; `percentual_comissao_gateway` **ignorado**) e `app/Http/Resources/ConfiguracoesPagamentosResource.php` (os mesmos campos, sem a comissão)
- [ ] T042 [US8] Criar `app/Http/Controllers/ConfiguracoesPagamentosController.php` (`configuracoes.editar`): `show` e `update` (só os validados) sobre `Configuracoes::atual()`; ⚠️ em `routes/api.php`, `GET` e `PUT configuracoes/pagamentos`; ⚠️ regenerar o Postman
- [ ] T043 [US8] Criar `app/Console/Commands/RenovarTokenMercadoPagoCommand.php` (`mercado_pago:renovar_token`, chama `VinculoMercadoPago::renovar`, saída em português); ⚠️ em `routes/console.php`, `Schedule::command('mercado_pago:renovar_token')->dailyAt('03:00')->withoutOverlapping(10)` com comentário

**Checkpoint**: quickstart seção 2 completo.

---

## Phase 5: User Story 2 - Cliente deposita via Pix (Priority: P1)

**Goal**: depósito Pix com QR Code, confirmação por webhook/conferência, cashback, bônus de
depósito, rollover e comissão.

**Independent Test**: [quickstart.md](quickstart.md) seção 4, passos 1 a 8 e 12.

### Banco e models

- [ ] T044 [P] [US2] Criar `database/migrations/2026_10_12_000006_create_depositos_table.php` com todas as colunas, FKs e índices do data-model §1 (`codigo` `char(36)->unique()`, `pagamento_id` `string(40)->nullable()->unique()`, `pix_qr_code` `mediumText`, `valor_estornado` `decimal(15,2)->default(0)`, FKs `clientes_transacoes_id`, `clientes_promocoes_id`, `bonus_clientes_rollovers_id` e `rollover_clientes_rollovers_id` para as tabelas certas, `usuarios_id`) e `app/Models/Depositos.php` (`SoftDeletes`; casts enums `MeioDeposito`/`SituacaoDeposito`, `decimal:2`, `boolean`, datas; relações `cliente`, `transacao`, `promocao`, `rollover_bonus`, `rollover_deposito`, `autor`; `getRouteKeyName()` não muda — a busca por código é explícita)
- [ ] T045 [P] [US2] Criar `database/migrations/2026_10_12_000007_create_notificacoes_mercado_pago_table.php` (data-model §4) e `app/Models/NotificacoesMercadoPago.php` (`SoftDeletes`; casts)

### Serviços

- [ ] T046 [US2] Criar `app/Services/EscolhaPromocoes.php` (R-11, R-15): `para_deposito(Clientes $cliente, string $valor, bool $primeiro_deposito): ?ClientesPromocoes` — vigentes das categorias `Qualquer depósito` e `Primeiro depósito` (esta só com `$primeiro_deposito`), `paraAfiliado($cliente->codigo_afiliado)`, com `valor_maximo_deposito` nulo ou ≥ valor; ordem: a do afiliado do cliente antes da sem afiliado (`orderByRaw('codigo_afiliado is null')`), "Qualquer depósito" antes de "Primeiro depósito", mais antiga primeiro; devolve a primeira; `valor_do_bonus(ClientesPromocoes $promocao, string $valor): string` (Fixo → valor; Percentual → `bcdiv(bcmul(valor, percentual), 100)` arredondado em centavos meio para cima)
- [ ] T047 [US2] Criar `app/Services/CriacaoDepositos.php` (R-06, R-07, FR-019 a FR-023, FR-027): `opcoes(Clientes): array` (contracts/api.md §4: disponibilidade — `depositos_habilitados` e `GatewaysPagamento::atual()` —, mínimo, máximo, valores rápidos fixos `20, 50, 100, 200` dentro dos limites, meios = `meios_habilitados()`, `parcelas_maximas`, `public_key`, `aceita_bonus` de `clientes_configuracoes.aceita_promocao`, `falta_no_cadastro`); `criar_pix(Clientes, string $valor, bool $aceita_bonus, ?Usuarios $autor, ?string $ip): Depositos` — valida cadastro (`422` com `errors.cadastro` e "Informe seu CPF e e-mail para depositar."), limites ("O depósito mínimo é de R$ X." / "O depósito máximo é de R$ X."), comissão `< valor`; grava `aceita_promocao` nas configurações do cliente; cria o depósito `Pendente` (`codigo` UUID, `percentual_comissao`, `comissao`, `expira_em`); chama `criar_pagamento` com o corpo de R-07 (`notification_url = url('/api/webhooks/mercado-pago')`, `application_fee` em reais com 2 casas, `payer.first_name`, `payer.identification.type = CPF`); grava `pagamento_id`, `pix_copia_cola`, `pix_qr_code`; falha da API → depósito `Cancelado` e `ValidationException` "Não foi possível gerar o Pix. Tente de novo." Método privado `calcular_comissao(string $valor, string $percentual): string` (centavos, meio para cima)
- [ ] T048 [US2] Criar `app/Services/ProcessamentoDepositos.php` (R-10 a R-12, FR-029 a FR-032): `atualizar(array $pagamento): void` (localiza pelo `external_reference`/`pagamento_id`; numa transação com o depósito `lockForUpdate`, aplica conforme `status`: `approved` → `aprovar`; `in_process`/`pending` → `Em análise` (cartão); `rejected` → `Recusado` + `status_detalhe`; `cancelled` → `Cancelado`/`Expirado` (Pix vencido); `refunded`/`charged_back` → `estornar` com `transaction_amount_refunded`; transições só a partir dos estados do data-model §1, o resto ignorado); `aprovar(Depositos)` na ordem de R-11: crédito `OrigemTransacao::Deposito` (referência = depósito), cashback (`Cashback`, se `cashback_percentual` > 0), promoção (`EscolhaPromocoes`, só com `aceita_bonus`; crédito `Promoção` na carteira da modalidade + `RolloverClientes::criar_bonus`), rollover do depósito (`criar_deposito` com vezes = da promoção quando `rollover_primeiro_deposito`, senão `rollover_deposito_vezes` se `rollover_deposito`, senão 0; acréscimo = cashback) e grava no depósito `pago_em`, ids das transações/rollovers/promoção; ponto de extensão `validar_indicacao` (preenchido na US9); `expirar_vencidos(): int` (Pix `Pendente` com `expira_em` passado → `Expirado`); `estornar(Depositos, string $valor_estornado)`: débito `EstornoDeposito` com `permitir_negativo: true` (só a diferença ainda não estornada); estorno total → `Estornado`, cancela o rollover do depósito e, se houver bônus, debita `min(saldo da carteira, valor do bônus)` com `permitir_negativo: true` (`BonusExcluido`) e cancela o rollover do bônus; parcial soma `valor_estornado` e mantém o resto
- [ ] T049 [US2] Criar `app/Jobs/ProcessarPagamentoMercadoPago.php` (`ShouldQueue`, `tries = 5`, `backoff = [10, 30, 60, 120]`; recebe o id da notificação; consulta o pagamento, chama `ProcessamentoDepositos::atualizar` e marca a notificação `Processada`/`Ignorada`/`Falhou` com o motivo)
- [ ] T050 [US2] Criar `app/Http/Controllers/WebhooksMercadoPagoController.php` (`store`): lê `type`/`topic` e `data.id`; grava a notificação com `assinatura_valida` (`ClienteMercadoPago::assinatura_valida`); inválida → `Ignorada` com "assinatura inválida"; `payment` → despacha o job; `payouts`/outros → tratados na US3 ou `Ignorada`; responde sempre `200 {"recebido": true}`; ⚠️ em `routes/api.php`, `Route::post('webhooks/mercado-pago', ...)->middleware('throttle:120,1')` fora dos grupos autenticados; ⚠️ regenerar o Postman
- [ ] T051 [US2] Criar `app/Console/Commands/ConferirDepositosCommand.php` (`depositos:conferir`: `expirar_vencidos()` e, para os depósitos `Pendente`/`Em análise` das últimas 2 h com `pagamento_id`, `consultar_pagamento` + `atualizar`; saída com a contagem); ⚠️ em `routes/console.php`, `Schedule::command('depositos:conferir')->everyMinute()->withoutOverlapping(2)`

### API do cliente

- [ ] T052 [P] [US2] Criar `app/Http/Requests/DepositosRequest.php` (contracts/api.md §5: `valor` `required|numeric|regex 2 casas|gt:0`; `meio` `required|Rule::enum(MeioDeposito)`; `aceita_bonus` `required|boolean`; `cartao` `required_if:meio,Cartão de crédito|array` com `token`, `parcelas` `integer|min:1`, `payment_method_id`, `issuer_id` nullable; mensagens em português) e `app/Http/Resources/DepositosResource.php` (`codigo`, `meio`, `valor`, `situacao`, `pago_em`, `pix` {copia_cola, qr_code_base64, expira_em} só no Pix pendente, `mensagem`)
- [ ] T053 [US2] Criar `app/Http/Controllers/AreaClienteDepositosController.php`: `opcoes`; `store` (Pix → `criar_pix`; cartão ligado na US4; autor = `null` até a US7; `201`); `show(string $codigo)` só do cliente do token (`404` "Depósito não encontrado."); ⚠️ em `routes/api.php`, `GET depositos/opcoes`, `POST depositos` (`throttle:10,1`) e `GET depositos/{codigo}` no grupo autenticado da área do cliente; ⚠️ regenerar o Postman

### Frontend

- [ ] T054 [P] [US2] Criar `resources/js/components/PixCharge/` (título "Cobrança gerada", ícone Pix, imagem `data:image/png;base64,...` só no desktop, código em caixa com botão "Copiar código" — `navigator.clipboard.writeText` com fallback — e aviso "Código Pix copiado", prazo restante em minutos, "Após o pagamento, é só aguardar nesta tela.", estado expirado com "Gerar novo Pix")
- [ ] T055 [US2] Criar `resources/js/components/DepositModal/` sobre o `ModalCard` (FR-019): etapa 1 valor (campo com `useCampoValor` e atalhos de `valores_rapidos`, mínimo/máximo), etapa 2 "Aceito receber bônus" (checkbox na cor do tema e aviso do rollover), etapa 3 meios disponíveis (cartões de escolha com ícone); "Continuar"/"Voltar"; ao confirmar Pix, `POST depositos` e mostra `PixCharge`; consulta `GET depositos/{codigo}` a cada 3 s enquanto aberto e `Pendente`; `Aprovado` → mensagem "Depósito realizado com sucesso!", fecha e chama `ao_concluir`; `422` com `errors.cadastro` → chama `ao_completar_cadastro(faltando, retomar)` (ligado à US6); `disponivel = false` → só a mensagem
- [ ] T056 [US2] Em `resources/js/pages/Account/index.jsx`, ligar "Realizar depósito" ao `DepositModal` (`ao_concluir` → `recarregar_tudo`)

**Checkpoint**: quickstart seção 4, passos 1 a 8 e 12.

---

## Phase 6: User Story 3 - Cliente solicita saque e a banca paga (Priority: P1)

**Goal**: saque com débito na hora, fila no painel e pagamento automático até o limite.

**Independent Test**: [quickstart.md](quickstart.md) seção 5.

- [ ] T057 [P] [US3] Criar `database/migrations/2026_10_12_000008_create_saques_table.php` (data-model §2) e `app/Models/Saques.php` (`SoftDeletes`; casts `TipoChavePix`, `ModoSaque`, `SituacaoSaque`, `decimal:2`, datas; relações `cliente`, `transacao`, `devolucao`, `decidido_por_usuario`, `autor`)
- [ ] T058 [US3] Criar `app/Services/SolicitacaoSaques.php` (R-13, FR-035 a FR-037, FR-040): `solicitar(Clientes, string $valor, ?Usuarios $autor, ?string $ip): Saques` — validações nesta ordem, cada uma com `ValidationException` e a mensagem do FR: saques habilitados ("Saques indisponíveis no momento."), cliente ativo, `clientes_configuracoes.bloquear_saque` ("Seu saque está bloqueado. Fale com o atendimento."), cadastro completo (`errors.cadastro` com `email`, `cpf`, `pix` faltando e "Complete seus dados para sacar."), saldo não negativo ("Seu saldo está negativo por estorno de pagamento."), saldo ≥ valor ("Só restam R$ X do seu saldo."), `saque_minimo` ("O saque mínimo é de R$ X."), quantidade do dia ≥ `quantidade_maxima_saques_diaria` ("Só é possível realizar N saques por dia."), soma do dia + valor > `valor_maximo_saque_diario` ("O limite de saque diário é de R$ X; restam R$ Y hoje."), `RolloverClientes::restante_deposito` > 0 ("Só é possível sacar depois de cumprir o rollover do depósito. Faltam R$ X."); dia em `America/Sao_Paulo`, contando saques não `Recusado`. Numa transação: débito `OrigemTransacao::Saque` (o bloqueio do `SaldoClientes` serializa saques simultâneos; `SaldoInsuficienteException` → "Só restam R$ X do seu saldo."), saque `Pendente` com a chave Pix principal copiada, `modo` pela configuração; com modo Automático e valor ≤ `saque_automatico_maximo`, despacha `PagarSaqueMercadoPago` depois do commit (`afterCommit`)
- [ ] T059 [US3] Criar `app/Services/PagamentoSaques.php`: `dar_baixa(Saques, Usuarios)` (`Pendente` → `Pago`, `decidido_*`, `pago_em`); `recusar(Saques, Usuarios, string $motivo)` (`Pendente` → `Recusado`, crédito `DevolucaoSaque` com referência ao saque, `devolucao_clientes_transacoes_id`); `enviar(Saques)` (`Pendente` → `Processando`; corpo do Payouts com `amount`, `external_reference = codigo`, `point_of_interaction.type = PSP_TRANSFER`, destino pela chave Pix e CPF do cliente, `notification_url`; grava `pagamento_id`; falha → volta a `Pendente` com `motivo_falha` e `modo` Manual); `confirmar(array $retorno)` (aprovado → `Pago`; recusado/falhou → `Pendente` com `motivo_falha`); saque não pendente → `ValidationException` "Este saque já foi decidido."
- [ ] T060 [US3] Criar `app/Jobs/PagarSaqueMercadoPago.php` (`ShouldQueue`, `tries = 1`; chama `PagamentoSaques::enviar`) e, em `app/Http/Controllers/WebhooksMercadoPagoController.php`, tratar o tipo de notificação de payouts (nome do tipo conferido na documentação oficial e registrado no research R-13) consultando `consultar_saque` e chamando `PagamentoSaques::confirmar` (síncrono, é só atualizar a situação)
- [ ] T061 [P] [US3] Criar `app/Http/Requests/SaquesRequest.php` (`valor` `required|numeric|2 casas|gt:0`), `app/Http/Requests/PagarSaqueRequest.php` (`forma` `required|in:manual,mercado_pago`), `app/Http/Requests/RecusarSaqueRequest.php` (`motivo` `required|string|max:255`) e `app/Http/Resources/SaquesResource.php` (para o painel: cliente {id, nome, telefone}, valor, chave Pix, modo, situação, motivos, datas, decidido por; para o cliente: código, valor, situação)
- [ ] T062 [US3] Criar `app/Http/Controllers/AreaClienteSaquesController.php` (`store` → `201` com "Saque solicitado com sucesso. O valor estará disponível em até 24 horas.") e `app/Http/Controllers/SaquesController.php` (`saques.gerenciar`: `index` filtrado por `situacao`, `pagina`, `por_pagina` ≤ 100, mais antigos primeiro na fila `Pendente`; `pagar` → `dar_baixa` ou `enviar` conforme `forma`; `recusar`); ⚠️ em `routes/api.php`, `POST area-cliente/saques` (`throttle:10,1`) e `GET saques`, `POST saques/{saque}/pagar`, `POST saques/{saque}/recusar` no painel; ⚠️ regenerar o Postman
- [ ] T063 [US3] Em `app/Http/Resources/ExtratoMinhaContaResource.php`, preencher `saque` (situação e motivo) para as transações de Saque/Devolução ligadas ao saque
- [ ] T064 [US3] Criar `resources/js/components/WithdrawModal/` sobre o `ModalCard`: saldo disponível, campo de valor, chave Pix de destino (tipo, chave mascarada, titular), resumo "Saldo depois do saque", botão "Sacar"; `422` com `errors.cadastro` → `ao_completar_cadastro(faltando, retomar)`; sucesso → mensagem e `ao_concluir`; em `resources/js/pages/Account/index.jsx`, ligar "Solicitar saque"

**Checkpoint**: quickstart seção 5.

---

## Phase 7: User Story 4 - Cliente deposita com cartão de crédito (Priority: P2)

**Goal**: cartão parcelado com campos seguros e estorno (NuPay fora desta spec, R-09).

**Independent Test**: [quickstart.md](quickstart.md) seção 4, passos 9 a 11.

- [ ] T065 [US4] Em `app/Services/CriacaoDepositos.php`, `criar_cartao(Clientes, string $valor, bool $aceita_bonus, array $cartao, ?Usuarios, ?string)` (mesmas validações do Pix; `parcelas` ≤ `parcelas_maximas`; corpo com `token`, `installments`, `payment_method_id`, `issuer_id`, `payer` (e-mail e CPF), `transaction_amount` = valor, `application_fee`, `external_reference`, `notification_url`, `statement_descriptor` = nome do sistema truncado em 13; resposta: `approved` → `ProcessamentoDepositos::atualizar` na hora; `in_process` → `Em análise`; `rejected` → depósito `Recusado` + `ValidationException` com `MensagensMercadoPago::recusa`; grava `parcelas` e `valor_total_pago` = `transaction_details.total_paid_amount`) (R-08)
- [ ] T066 [US4] Em `app/Http/Controllers/AreaClienteDepositosController.php`, `store` chama `criar_cartao` quando o `meio` for cartão (meio fora de `opcoes.meios` → `422` "Meio de pagamento indisponível.")
- [ ] T067 [P] [US4] Criar `resources/js/utils/mercado_pago.js`: `carregar_sdk()` (injeta `https://sdk.mercadopago.com/js/v2` uma vez, promessa reaproveitada), `criar_campos(public_key, ids, estilo)` (`new MercadoPago(public_key, { locale: 'pt-BR' })`, `mp.fields.create('cardNumber'|'expirationDate'|'securityCode', { placeholder, style })` montados nos ids; evento `binChange` → `mp.getPaymentMethods` e `mp.getInstallments({ amount, bin })`; `gerar_token({ cardholderName, identificationType: 'CPF', identificationNumber })` via `mp.fields.createCardToken`; `desmontar()`); comentário: o número do cartão nunca passa pelo nosso servidor
- [ ] T068 [US4] Criar `resources/js/components/CardPayment/` (campos seguros com o visual do modal: número, validade, código, nome impresso no cartão, CPF do titular pré-preenchido; bandeira detectada; select de parcelas com `recommended_message` até `parcelas_maximas`; total a pagar; botão "Pagar R$ X"; erros por campo; estado enviando); props `valor`, `public_key`, `parcelas_maximas`, `ao_pagar(dados_cartao)`
- [ ] T069 [US4] Em `resources/js/components/DepositModal/`, etapa do cartão com `CardPayment` (envia `cartao` no `POST depositos`; `Aprovado` → sucesso; `Em análise` → "Pagamento em análise. Você verá o depósito assim que for aprovado." e consulta como no Pix; `422` → mensagem de recusa)

**Checkpoint**: quickstart seção 4, passos 9 a 11.

---

## Phase 8: User Story 5 - Cliente converte, exclui e perde bônus expirado (Priority: P2)

**Goal**: converter, excluir e expirar bônus; promoções com os campos da tela antiga.

**Independent Test**: [quickstart.md](quickstart.md) seção 6.

- [ ] T070 [US5] Criar `app/Services/GestaoBonus.php` (R-22, FR-015 a FR-018d): `liberado(ClientesRollovers $bonus): string` (cumprido e sem outro bônus pendente na mesma carteira → saldo da carteira; senão `'0.00'`); `pode_converter`/`pode_excluir` (para o resource); `converter(Clientes, ClientesRollovers)` (recusas: não é do cliente → 404; expirado → "Seu bônus está expirado."; já convertido/excluído → "Este bônus já cumpriu a meta ou o rollover foi removido."; liberado 0 e outro pendente → "Cumpra o rollover dos outros bônus desta modalidade antes de converter."; liberado 0 → "Você não tem valor liberado para conversão."; numa transação: valor = `min(liberado, promocao.valor_maximo_conversao)`; débito `ConversaoBonus` de todo o liberado na carteira de bônus e crédito `ConversaoBonus` do valor no Saldo; `convertido_em`); `excluir(Clientes, ClientesRollovers)` (recusas do FR-018 na ordem: expirado, liberado > 0 → "Antes de excluir o bônus, converta o valor liberado.", cumprido/excluído/cancelado → "Este bônus já cumpriu a meta ou o rollover foi removido."; débito `BonusExcluido` de `min(saldo da carteira, valor_creditado)` quando > 0; `excluido_em`, `cancelado_em`); `expirar_vencidos(): int` (rollovers de Bônus pendentes com `expira_em < now()`; para cada um, numa transação com o cliente bloqueado: débito `BonusExpirado` de `min(saldo da carteira, valor_creditado)` quando > 0 e `expirado_em = now()`)
- [ ] T071 [US5] Criar `app/Http/Controllers/AreaClienteBonusController.php` (`converter` → `{"message": "O valor de R$ X foi convertido para o seu saldo.", "valor"}`; `excluir` → `{"message": "Bônus excluído com sucesso."}`; `{rollover}` só do cliente do token, tipo Bônus, senão `404` "Bônus não encontrado."); ⚠️ em `routes/api.php`, `POST bonus/{rollover}/converter` e `POST bonus/{rollover}/excluir` no grupo autenticado da área do cliente; ⚠️ regenerar o Postman
- [ ] T072 [US5] Criar `app/Console/Commands/ExpirarBonusCommand.php` (`bonus:expirar`, chama `GestaoBonus::expirar_vencidos`, saída com a contagem); ⚠️ em `routes/console.php`, `Schedule::command('bonus:expirar')->everyTenMinutes()->withoutOverlapping(5)`
- [ ] T073 [US5] Em `app/Http/Resources/ExtratoMinhaContaResource.php`, preencher `bonus.liberado`, `pode_converter` e `pode_excluir` pela `GestaoBonus`
- [ ] T074 [P] [US5] ⚠️ Em `app/Http/Requests/ClientesPromocoesRequest.php`: `dias_cumprir` `required|integer|min:1` ("Informe os dias para cumprir o bônus."), `rollover_primeiro_deposito` `sometimes|boolean` + `declined` fora de Primeiro depósito ("O rollover no primeiro depósito só vale para promoções de primeiro depósito."), `codigo_afiliado` `nullable|string|max:50`, `quantidade_indicacoes` `required_if:categoria,Indicação|integer|min:1` e `prohibited` fora de Indicação; ⚠️ em `app/Http/Resources/ClientesPromocoesResource.php`, devolver os quatro campos; ⚠️ em `database/seeders/ClientesPromocoesSeeder.php`, `dias_cumprir => 30` em todas e `quantidade_indicacoes => 5` na de Indicação (FR-018e, FR-018h)
- [ ] T075 [US5] ⚠️ Em `app/Services/CadastroClientes.php`, as promoções de Primeiro cadastro passam por `->paraAfiliado($cliente->codigo_afiliado)` (FR-018g)
- [ ] T076 [US5] Em `resources/js/components/StatementTable/` (render prop `acoes_bonus`) e `resources/js/pages/Account/index.jsx`: ícones `download` (Converter) e `delete` (Excluir) na cor do tema nas linhas de bônus, com `ConfirmModal` ("Converter o valor de R$ X para seu saldo real? Dúvidas sobre o valor convertido, consulte as regras de bônus." / "Tem certeza que deseja excluir o bônus {nome}? Além de remover o rollover, removerá seu saldo em bônus {modalidade}."), mensagens de recusa da API e `recarregar_tudo` no sucesso

**Checkpoint**: quickstart seção 6.

---

## Phase 9: User Story 6 - Cliente atualiza os próprios dados (Priority: P2)

**Goal**: Meus dados com campos preenchidos uma vez e retomada do depósito/saque.

**Independent Test**: [quickstart.md](quickstart.md) seção 7 e seção 4 passo 1 / seção 5 passo 4.

- [ ] T077 [US6] ⚠️ Em `app/Http/Requests/UpdateMeusDadosRequest.php` (R-21): `cpf` (regras de `regras_cpf` com o id do cliente) e `email` aceitos só quando estão vazios no cliente; `data_nascimento` (`date|before_or_equal:-18 years`, "É preciso ter pelo menos 18 anos.") aceito só quando vazio; preenchidos → `prohibited` com "Este dado só pode ser alterado pelo atendimento."; `telefone`/`ddi` continuam proibidos
- [ ] T078 [US6] Criar `resources/js/components/ProfileModal/` sobre o `ModalCard`: seções "Dados pessoais" (nome, e-mail, CPF, data de nascimento, gênero; telefone só leitura; campos travados com cadeado e "Para alterar, fale com o atendimento."), "Senha" (nova senha e confirmação, `PUT meus-dados/senha`, regra "mínimo de 6 caracteres com letras e números") e "Chave Pix para saque" (tipo, chave com máscara por tipo — CPF, CNPJ, Telefone, E-mail, Chave aleatória —, nome do titular; usa as rotas `meios-pagamento` existentes, criando ou atualizando o principal); erros da API por campo; prop `foco` (lista de campos faltando, destacados) e `ao_salvar`
- [ ] T079 [US6] Em `resources/js/pages/Account/index.jsx`: "Meus dados" abre o `ProfileModal`; `ao_completar_cadastro(faltando, retomar)` do `DepositModal`/`WithdrawModal` mostra o aviso, abre o `ProfileModal` com `foco` e, ao salvar, chama `retomar()`

**Checkpoint**: quickstart seção 7.

---

## Phase 10: User Story 7 - Admin ou supervisor acessa a conta de um cliente (Priority: P2)

**Goal**: acesso assistido por código de uso único, com tarja e autor registrado.

**Independent Test**: [quickstart.md](quickstart.md) seção 8.

- [ ] T080 [P] [US7] Criar `database/migrations/2026_10_12_000009_create_clientes_acessos_assistidos_table.php` (data-model §5) e `app/Models/ClientesAcessosAssistidos.php` (`SoftDeletes`; casts; relações `cliente`, `usuario`; método `ativo(): bool` = iniciado, não encerrado e `expira_em` futuro)
- [ ] T081 [US7] Criar `app/Services/AcessoAssistido.php` (R-16): `gerar(Usuarios, Clientes, ?string $ip): array{url, expira_em}` (cliente excluído → 404; código `Str::random(40)`, grava `hash('sha256')`, `codigo_expira_em` + 2 min); `iniciar(string $codigo, ?string $ip): array` (busca pelo hash, não iniciado, código válido — senão `ValidationException` "Acesso expirado. Gere um novo pelo painel."; `iniciado_em`, `expira_em` = + `minutos_acesso_assistido`; token por `auth('clientes')->claims(['acesso_assistido_id' => id, 'assistido_por' => usuarios_id])->setTTL(minutos)->login($cliente)`); `autor(Request): ?Usuarios` (lê a claim do token atual); `acesso(Request): ?ClientesAcessosAssistidos`; `encerrar(Request)`
- [ ] T082 [US7] ⚠️ Em `app/Http/Middleware/GarantirAcessoCliente.php`, com a claim `acesso_assistido_id`: recusar (`401` "Acesso assistido encerrado.") se o acesso não estiver `ativo()`, gravando `encerrado_em = expira_em` quando ele venceu sem ter sido encerrado (FR-048); o teste de `tokens_validos_desde` continua valendo
- [ ] T083 [US7] Criar `app/Http/Controllers/AcessosAssistidosController.php` (painel, `clientes.acessar_conta`, `store` → `201 {url, expira_em}`) e `app/Http/Controllers/AreaClienteAcessoAssistidoController.php` (`iniciar` com `IniciarAcessoAssistidoRequest` — criar `app/Http/Requests/IniciarAcessoAssistidoRequest.php` com `codigo` `required|string|size:40` —, `encerrar` → `204`); ⚠️ em `routes/api.php`, `POST clientes/{cliente}/acesso-assistido` no painel e `POST area-cliente/acesso-assistido` (`throttle:10,1`, fora do grupo autenticado) e `POST area-cliente/acesso-assistido/encerrar` (autenticado); ⚠️ regenerar o Postman
- [ ] T084 [US7] Passar o autor do acesso assistido (`AcessoAssistido::autor($request)`) para `CriacaoDepositos` (T053/T066), `SolicitacaoSaques` (T062) e `GestaoBonus` (T071) nos controllers da área do cliente, gravando `usuarios_id` no depósito/saque e nas transações; em `app/Http/Resources/MinhaContaResource.php`, preencher `acesso_assistido` (`usuario` = nome do usuário do painel, `expira_em`)
- [ ] T085 [US7] ⚠️ Em `resources/js/utils/storage.js`, chave `wssports.acesso_assistido` em `sessionStorage` (ler, gravar, apagar com try/catch); ⚠️ em `resources/js/hooks/useSessao.js`, quando houver sessão assistida e a página for `/minha-conta`, usar o token assistido nas requisições da API, sem alterar nem apagar a sessão do cliente salva no aparelho
- [ ] T086 [US7] Criar `resources/js/components/AssistedBanner/` (tarja fixa no topo na cor de alerta do tema: "Você está acessando a conta de {nome}", tempo restante e botão "Voltar ao painel" que chama `encerrar`, apaga a sessão assistida e fecha a aba ou volta); em `resources/js/pages/Account/index.jsx`, com `acesso` nas props: trocar o código (`POST area-cliente/acesso-assistido`), gravar a sessão assistida, apagar `?acesso` da URL e mostrar a tarja; `401` de acesso encerrado → mensagem "Acesso assistido encerrado." e fim da sessão assistida

**Checkpoint**: quickstart seção 8.

---

## Phase 11: User Story 9 - Cliente indica amigos e acompanha as indicações (Priority: P3)

**Goal**: link de indicação, validação no primeiro depósito, bônus a cada grupo e área "Minhas
indicações".

**Independent Test**: [quickstart.md](quickstart.md) seção 9.

- [ ] T087 [P] [US9] Criar `database/migrations/2026_10_12_000010_create_clientes_indicacoes_bonus_table.php` e `2026_10_12_000011_create_clientes_indicacoes_table.php` (data-model §6 e §7; `indicado_clientes_id` único) e os models `app/Models/ClientesIndicacoesBonus.php` e `app/Models/ClientesIndicacoes.php` (`SoftDeletes`; casts; relações)
- [ ] T088 [US9] Criar `app/Services/Indicacoes.php` (R-17, FR-044 a FR-044c): `registrar(Clientes $indicado, ?string $codigo_indicacao)` (código existente de outro cliente → `indicado_por` e `clientes_indicacoes`; inválido → ignora); `validar(Clientes $indicado, Depositos $deposito)` (só no primeiro depósito aprovado: `validada_em`, `depositos_id`; depois `pagar_grupos($indicador)`); `pagar_grupos(Clientes $indicador)` (promoção de Indicação vigente para o afiliado do indicador; enquanto houver ≥ `quantidade_indicacoes` válidas sem bônus, se o indicador estiver ativo e com `aceita_promocao`: crédito `Promoção` do valor na carteira da modalidade, `criar_bonus`, `clientes_indicacoes_bonus` e marca as N mais antigas); `desfazer(Depositos $deposito)` (indicação validada por esse depósito volta a pendente; se tinha bônus: débito `BonusExcluido` de `min(saldo da carteira, valor do bônus)` com `permitir_negativo: true`, cancela o rollover, `cancelado_em` no grupo e solta as outras indicações do grupo — `clientes_indicacoes_bonus_id = null` —, e chama `pagar_grupos` de novo); `resumo(Clientes)` (válidas, meta, faltam para o próximo, bônus recebidos, valor, link `url('/?indicacao='.codigo)`)
- [ ] T089 [US9] Em `app/Services/ProcessamentoDepositos.php`, chamar `Indicacoes::validar` em `aprovar` (primeiro depósito aprovado do cliente) e `Indicacoes::desfazer` no estorno total
- [ ] T090 [US9] ⚠️ Em `app/Http/Requests/StoreClientesRequest.php`, `codigo_indicacao` `nullable|string|size:8`; ⚠️ em `app/Services/CadastroClientes.php`, gravar `codigo_indicacao = Clientes::gerar_codigo_indicacao()` (`forceFill`) e chamar `Indicacoes::registrar` dentro da transação do cadastro
- [ ] T091 [US9] Criar `app/Http/Resources/IndicacoesResource.php` (nome = primeiro nome + inicial do sobrenome, `cadastrado_em` data, `situacao` "Depositou"/"Pendente de depósito"; nada de telefone, e-mail, CPF ou valores) e `app/Http/Controllers/AreaClienteIndicacoesController.php` (`index` paginado ≤ 100, padrão 30, com `resumo`); ⚠️ em `routes/api.php`, `GET area-cliente/indicacoes`; ⚠️ regenerar o Postman
- [ ] T092 [US9] Criar `resources/js/components/ReferralModal/` ("Indique um amigo": "Você tem X indicações válidas; faltam Y para ganhar R$ Z de bônus", link com "Copiar", botão "Indicar" que abre `https://api.whatsapp.com/send?text=` com a mensagem e o link, botão "Minhas indicações") e `resources/js/components/ReferralsModal/` (lista paginada com nome, data e selo da situação, resumo no topo, rolagem infinita) sobre o `ModalCard`; em `resources/js/pages/Account/index.jsx`, ligar o item do menu
- [ ] T093 [US9] ⚠️ Em `resources/js/pages/Home/index.jsx`, ler `?indicacao=` e abrir o cadastro passando o código; ⚠️ em `resources/js/components/AuthModal/index.jsx`, prop `codigo_indicacao` enviada no cadastro (campo oculto, com aviso "Você foi indicado por um amigo.")

**Checkpoint**: quickstart seção 9.

---

## Phase 12: Polish & Cross-Cutting Concerns

- [ ] T094 ⚠️ Conferir que `docs/postman/wssports_api.postman_collection.json` tem as 21 rotas novas e as alteradas (corpos de `clientes-promocoes`, `area-cliente/cadastro` e `meus-dados`), com `base_url` e `token`
- [ ] T095 [P] Rodar `vendor/bin/pint` nos arquivos PHP criados/alterados e `npm run build` sem erros
- [ ] T096 Revisar segredos: `grep` por `access_token`, `refresh_token` e `client_secret` em `app/` e `storage/logs` — nenhum em resource, log ou resposta
- [ ] T097 Executar o [quickstart.md](quickstart.md) completo e registrar no fim deste arquivo o que foi validado e o que ficou para o responsável conferir no aparelho (contas de teste do Mercado Pago e Payouts)
- [ ] T098 Revisão de legibilidade final (Princípio V) dos serviços de pagamento (`ProcessamentoDepositos`, `SolicitacaoSaques`, `GestaoBonus`, `Indicacoes`)
- [ ] T099 [P] ⚠️ Acrescentar notas "> Alterado pela spec 007: ..." (sem reescrever o texto original) nos trechos que a 007 muda: em `specs/002-clientes/spec.md`, no FR-074 (expiração do saldo promocional passa a existir, spec 007 FR-018a a FR-018d), na regra "Este dado só pode ser alterado pelo atendimento" de Meus dados (CPF, e-mail e data de nascimento preenchidos uma vez pelo cliente, spec 007 FR-041) e no cadastro de promoções (campos `dias_cumprir`, `rollover_primeiro_deposito`, `codigo_afiliado`, `quantidade_indicacoes`, spec 007 FR-018e); em `specs/004-apostas/spec.md`, no FR-047 (rollover de depósito criado pela spec 007, com vezes configuráveis — padrão 1 —, cashback somado e rollover primeiro depósito)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sem dependências.
- **Foundational (Phase 2)**: depende da Setup; bloqueia todas as stories.
- **US1 (Phase 3)**: depende da Foundational.
- **US8 (Phase 4)**: depende da Foundational; bloqueia US2, US3 (modo automático) e US4.
- **US2 (Phase 5)**: depende da US8 e da US1 (tela onde o modal abre).
- **US3 (Phase 6)**: depende da US1; o modo automático depende da US8.
- **US4 (Phase 7)**: depende da US2.
- **US5 (Phase 8)**: depende da US1; bônus de depósito para testar vêm da US2 (ou de promoção de cadastro).
- **US6 (Phase 9)**: depende da US1; a retomada usa os modais da US2 e da US3 se existirem.
- **US7 (Phase 10)**: depende da US1; T084 toca controllers da US2, US3 e US5 (fazer depois delas ou ajustar só os existentes).
- **US9 (Phase 11)**: depende da US2 (validação no depósito).
- **Polish (Phase 12)**: depois das stories desejadas.

### Arquivos compartilhados (fazer em sequência)

- `routes/api.php`: T026 → T039 → T042 → T050 → T053 → T062 → T071 → T083 → T091.
- `routes/console.php`: T043 → T051 → T072.
- `routes/web.php`: T027 → T040.
- `resources/js/pages/Account/index.jsx`: T035 → T056 → T064 → T076 → T079 → T086 → T092.
- `resources/js/components/DepositModal/`: T055 → T069.
- `app/Services/CriacaoDepositos.php`: T047 → T065.
- `app/Services/ProcessamentoDepositos.php`: T048 → T089.
- `app/Services/CadastroClientes.php`: T075 → T090.
- `app/Http/Resources/ExtratoMinhaContaResource.php`: T024 → T063 → T073.
- `app/Http/Controllers/WebhooksMercadoPagoController.php`: T050 → T060.
- `app/Http/Middleware/GarantirAcessoCliente.php`: T018 → T082.
- `resources/js/pages/Home/index.jsx` e `AuthModal`: T036 → T093.

### Within Each User Story

- Migrations e models → serviços → requests/resources → controllers e rotas → comandos/jobs → frontend.

---

## Parallel Example: Foundational

```text
T005 add_campos_clientes_promocoes
T006 add_expiracao_clientes_rollovers
T007 add_indicacao_clientes
T009 ClientesPromocoes.php / T010 ClientesRollovers.php / T011 Clientes.php
T017 MensagensMercadoPago.php
T020 ModalCard/ / T021 ConfirmModal/
```

## Parallel Example: User Story 1

```text
T022 ExtratoMinhaContaRequest.php
T028 useMinhaConta.js
T029 AccountHeader/ / T030 BalanceCards/ / T031 StatementFilters/
T032 StatementTable/ / T033 AccountMenu/ / T034 InactiveAccount/
```

## Parallel Example: User Story 2

```text
T044 depositos (migration + model)
T045 notificacoes_mercado_pago (migration + model)
T052 DepositosRequest + DepositosResource
T054 PixCharge/
```

---

## Implementation Strategy

### MVP First

1. Setup + Foundational.
2. US1 (tela e extrato) → validar a seção 3 do quickstart.
3. US8 + US2 (vínculo e Pix) → primeiro dinheiro entrando, seções 2 e 4.
4. US3 (saque manual) → ciclo completo de dinheiro.

### Incremental Delivery

- US4 (cartão) → US5 (bônus) → US6 (Meus dados) → US7 (acesso assistido) → US9 (indicações),
  cada uma validada pela sua seção do quickstart antes da próxima.

## Notes

- [P] = arquivos diferentes, sem dependência pendente.
- Sem testes automatizados: validação manual pelo quickstart.
- Commit pelo `/speckit-git-commit` ao fim de cada fase ou story.
- Ambiente local sem HTTPS público: a conferência `depositos:conferir` substitui o webhook.
