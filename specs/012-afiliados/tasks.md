---

description: "Lista de tarefas da feature Afiliados"
---

# Tasks: Afiliados

**Input**: Documentos de design em `specs/012-afiliados/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md),
[data-model.md](data-model.md), [contracts/api.md](contracts/api.md),
[contracts/paginas.md](contracts/paginas.md), [quickstart.md](quickstart.md)

**Tests**: NÃO há tarefas de teste: a constituição proíbe testes automatizados. Cada user story é
validada manualmente pelos roteiros do [quickstart.md](quickstart.md).

**Organization**: tarefas agrupadas por user story, na ordem da spec: US1 a US6 (P1), US7 e US8
(P2). A US3 (login) vem antes da US7 (painel), que depende do token do afiliado; a US5 (comissão)
vem antes da US6 (prestação), que fecha os lançamentos.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: pode rodar em paralelo (arquivos diferentes, sem dependências pendentes)
- **[Story]**: user story da tarefa (US1 a US8)
- Caminhos relativos à raiz do repositório

## Regras que valem para TODAS as tarefas

- **Sistema antigo (Princípios VII e VIII)**: `C:\Users\wedso\OneDrive\Área de Trabalho\projetos\wssports.bet`
  é **somente leitura**. Arquivos consultados no [research.md](research.md) R-01; diferenças
  aprovadas no R-02 e no R-21.
- **Visual**: `/cadastro-afiliado` reproduz `resources/js/screens/new-affiliated/index.js` e
  `styles.js`; o modal de cadastro reproduz `resources/js/modals/affiliated/index.js` e `styles.js`;
  `/afiliados` reproduz `resources/js/screens/affiliated/index.js` e `styles.js` (medidas, textos,
  ícones Material Icons, breakpoint de 900px), com as cores pelos tokens do tema. Os acréscimos e
  melhorias são só os de [contracts/paginas.md](contracts/paginas.md); os modais do painel usam o
  `ModalCard` existente.
- **Nomes (Princípio I)**: backend em português (classes `PascalCase`, resto `snake_case`);
  métodos do framework com o nome original; permissões `afiliados.<acao>`; rotas em kebab-case.
  Frontend: componentes e styled-components em inglês `PascalCase`; variáveis, funções, props e
  chaves em `snake_case` português; hook `useAfiliado`; props só de estilo com `$`.
- **Componentes (Princípio III)**: pasta própria com `index.jsx` e `styles.jsx`.
- **Idioma (Princípio II)**: comentários, textos e mensagens em português.
- **Escopo (Princípio IV)**: arquivos existentes marcados com ⚠️ estão na tabela do
  [plan.md](plan.md) e foram confirmados pelo responsável em 2026-10-17. Nenhum outro arquivo
  existente pode ser alterado; se for preciso, avisar antes.
- **Postman (constituição)**: toda tarefa que cria ou altera rota em `routes/api.php` regenera, na
  mesma tarefa, `docs/postman/wssports_api.postman_collection.json` ⚠️ (substituindo o arquivo,
  mantendo `base_url` e `token`).
- **Consistência (Princípio VI)**: gestão no padrão de `ClientesController` (controller com
  `HasMiddleware` + `GarantirPermissaoCliente::permissao_cliente`, `FormRequest` com
  `rules()`/`messages()` em português, `JsonResource`, `por_pagina` ≤ 100, sufixo de exclusão,
  restauração com conflitos); área do afiliado no padrão da área do cliente
  (`$request->user('afiliados')`, `AreaClienteAutenticacaoController`,
  `AreaClienteRecuperacaoSenhaController`); configuração no padrão de `ConfiguracoesCassinoController`.
- **Dinheiro**: `decimal(15,2)` / `decimal(5,2)` + `bcmath`; percentual pelo
  `EscolhaPromocoes::percentual` (meio para cima em centavos). Nunca `float`.
- **Banco**: tabelas novas com `timestamps()` + `softDeletes()` e model com `SoftDeletes`; **toda
  coluna com `->comment()` em português** (citando valores fixos e unidades); sem SQL concatenado
  com dados da requisição.
- **Legibilidade (Princípio V)**: ao concluir cada tarefa, revisar o código alterado e informar se
  houve ou não refatoração.

---

## Phase 1: Setup (infraestrutura compartilhada)

**Purpose**: enums, imagem e guard.

- [ ] T001 [P] Criar os enums em `app/Enums/` (data-model §8), cada um com comentário: `SituacaoAfiliado.php` (`EmAnálise = 'Em análise'`, `Ativo = 'Ativo'`, `Inativo = 'Inativo'`, `Recusado = 'Recusado'`), `OrigemCadastroAfiliado.php` (`Painel = 'Painel'`, `Público = 'Público'`), `TipoGanhoAfiliado.php` (`SaldoLíquido = 'Saldo líquido'`, `QualquerDepósito = 'Qualquer depósito'`, `PrimeiroDepósito = 'Primeiro depósito'`) e `EventoLancamentoAfiliado.php` (`Depósito = 'Depósito'`, `Saque = 'Saque'`, `EstornoDepósito = 'Estorno de depósito'`)
- [ ] T002 [P] Copiar `wssports.bet/public/images/afiliado.jpg` (somente leitura no antigo) para `public/images/afiliado.jpg`
- [ ] T003 ⚠️ Em `config/auth.php`, guard `'afiliados' => ['driver' => 'jwt', 'provider' => 'afiliados']` e provider `'afiliados' => ['driver' => 'eloquent', 'model' => Afiliados::class]`, com comentários no padrão do guard `clientes` (R-03)

---

## Phase 2: Foundational (pré-requisitos de várias stories)

**Purpose**: tabelas, models, permissões, middleware, regras comuns e base dos e-mails.

**⚠️ CRITICAL**: nenhuma user story começa antes desta fase.

### Migrations (data-model §1 a §7)

- [ ] T004 Criar `database/migrations/2026_10_17_000001_create_afiliados_table.php` com as colunas do data-model §1: `nome` varchar(150); `login` varchar(80); `password`; `email` varchar(150) nulo; `cpf` varchar(40) nulo; `ddi` varchar(3) padrão `'55'`; `telefone` varchar(20); `cidade` varchar(100) nulo; `estado` char(2) nulo ("UF ou EX (estrangeiro)"); `endereco` varchar(255) nulo; `codigo` varchar(80); `situacao` varchar(20) ("Em análise, Ativo, Inativo, Recusado"); `motivo_recusa` varchar(255) nulo; `origem_cadastro` varchar(20) ("Painel, Público"); `usuarios_id`, `cadastrado_por`, `decidido_por` FKs nulas para `usuarios`; `decidido_em` datetime nulo; `tokens_validos_desde` timestamp nulo; `timestamps()`, `softDeletes()`; `unique` em `login`, `email`, `cpf`, `codigo`; `index` em `situacao`, `usuarios_id`, `nome`; toda coluna com `->comment()`
- [ ] T005 Criar `database/migrations/2026_10_17_000002_create_afiliados_comissoes_table.php` (data-model §2): `afiliados_id` FK; `tipo_ganho` varchar(20) ("Saldo líquido, Qualquer depósito, Primeiro depósito"); `percentual` decimal(5,2) ("0 a 100, em %"); `vigente_desde` datetime; `vigente_ate` datetime nulo ("nulo = regra vigente"); `usuarios_id` FK nula; índice `(afiliados_id, vigente_ate)`; comentários
- [ ] T006 Criar `database/migrations/2026_10_17_000003_create_afiliados_prestacoes_contas_table.php` (data-model §4): `afiliados_id` FK; `valor_apurado` decimal(15,2) ("pode ser negativo"); `valor_pago` decimal(15,2); `quantidade_lancamentos` unsignedInteger; `periodo_inicio` e `periodo_fim` datetime; `usuarios_id` FK; `observacao` varchar(500) nulo; índice `(afiliados_id, periodo_fim)`; comentários
- [ ] T007 Criar `database/migrations/2026_10_17_000004_create_afiliados_lancamentos_table.php` (data-model §3): `afiliados_id` FK; `clientes_id` FK; `evento` varchar(30) ("Depósito, Saque, Estorno de depósito"); `depositos_id` e `saques_id` FKs nulas; `chave_evento` varchar(80) único ("deposito:{id}, saque:{id}, estorno:{deposito_id}:{valor_estornado_total}"); `tipo_ganho` varchar(20); `base` decimal(15,2); `percentual` decimal(5,2); `valor` decimal(15,2) ("comissão com sinal: + depósito, − saque e estorno"); `ocorrido_em` datetime; `afiliados_prestacoes_contas_id` FK nula ("nulo = em aberto"); índices `(afiliados_id, afiliados_prestacoes_contas_id)`, `(afiliados_id, ocorrido_em)`, `(depositos_id)`; comentários
- [ ] T008 [P] Criar `database/migrations/2026_10_17_000005_create_afiliados_meios_pagamento_table.php` (data-model §5): `afiliados_id` FK única; `pix_tipo_chave` varchar(20) ("CPF, CNPJ, E-mail, Telefone, Chave aleatória"); `pix_chave` varchar(150); comentários
- [ ] T009 [P] Criar `database/migrations/2026_10_17_000006_create_afiliados_codigos_recuperacao_table.php` igual a `2026_09_29_000007_create_clientes_codigos_recuperacao_table.php`, com `afiliados_id` FK; comentários
- [ ] T010 [P] Criar `database/migrations/2026_10_17_000007_add_afiliados_configuracoes_table.php` (data-model §7): em `configuracoes`, `afiliados_tipo_ganho_padrao` varchar(20) padrão `'Saldo líquido'` e `afiliados_percentual_padrao` decimal(5,2) padrão 0, com comentários; `down()` remove

### Models

- [ ] T011 Criar `app/Models/Afiliados.php` (data-model §1): `Authenticatable` + `JWTSubject`, `SoftDeletes`, `$table = 'afiliados'`, `fillable` sem `situacao`/`tokens_validos_desde`/`decidido_*`, `hidden: password`, casts (`password` hashed, `situacao` → `SituacaoAfiliado`, `origem_cadastro` → `OrigemCadastroAfiliado`, datas); métodos `pode_acessar()` (Ativo e fora da lixeira), `pode_receber_clientes()`, `invalidar_tokens()` (como em `Clientes`), `gerar_codigo()` estático (8 caracteres do alfabeto `ABCDEFGHJKLMNPQRSTUVWXYZ23456789`, únicos com excluídos, R-05); relações `gerente`, `comissoes`, `comissao_vigente` (`hasOne` com `vigente_ate` nulo), `lancamentos`, `prestacoes`, `ultima_prestacao` (`latestOfMany('periodo_fim')`), `meio_pagamento`, `clientes`, `codigos_recuperacao`
- [ ] T012 [P] Criar `app/Models/AfiliadosComissoes.php`, `app/Models/AfiliadosLancamentos.php`, `app/Models/AfiliadosPrestacoesContas.php`, `app/Models/AfiliadosMeiosPagamento.php` e `app/Models/AfiliadosCodigosRecuperacao.php` com `SoftDeletes`, `fillable`, casts (`decimal:2`, enums, datas) e relações do data-model; `AfiliadosLancamentos::scopeEmAberto` (`afiliados_prestacoes_contas_id` nulo); `AfiliadosCodigosRecuperacao::valido()` igual ao de `ClientesCodigosRecuperacao`
- [ ] T013 ⚠️ Em `app/Models/Configuracoes.php`, `afiliados_tipo_ganho_padrao` e `afiliados_percentual_padrao` no `fillable` e casts (`TipoGanhoAfiliado`, `decimal:2`)

### Permissões, acesso e regras comuns

- [ ] T014 ⚠️ Em `app/Enums/Funcao.php`: constante `PERMISSOES_AFILIADOS` (`afiliados.listar`, `afiliados.consultar`, `afiliados.cadastrar`, `afiliados.editar`, `afiliados.alterar_situacao`, `afiliados.prestar_contas`, `afiliados.editar_comissao`, `afiliados.excluir`, `afiliados.restaurar`) e `PERMISSOES_AFILIADOS_RESTRITAS` (`afiliados.excluir`, `afiliados.restaurar`); `pode_usar`: Vendedor nenhuma, Gerente todas menos as restritas, Admin e Supervisor todas; `permissoes_padrao`: inclui as que a função pode usar, **exceto `afiliados.editar_comissao` para o Gerente** (data-model §9); comentários atualizados
- [ ] T015 Criar `database/seeders/AfiliadosSeeder.php` no padrão de `CassinoSeeder`: cria as 9 permissões (`guard_name` `api`), entrega aos usuários existentes conforme `pode_usar` (Gerente sem `afiliados.editar_comissao`), cria 2 afiliados de exemplo (um Ativo com gerente e regra "Qualquer depósito" 10%, um "Em análise" de origem Público) com regra vigente e Pix; ⚠️ em `database/seeders/DatabaseSeeder.php`, `$this->call(AfiliadosSeeder::class)` depois do `CassinoSeeder`
- [ ] T016 Criar `app/Http/Middleware/GarantirAcessoAfiliado.php` copiando `GarantirAcessoCliente` sem o acesso assistido: 403 com a mensagem da situação ("Seu cadastro ainda está em análise.", "Seu cadastro não foi aprovado.", "Seu acesso está desativado.", excluído: "Afiliado sem permissão de acesso.") e 401 para `iat` anterior a `tokens_validos_desde`
- [ ] T017 ⚠️ Extrair `regra_chave_pix()` e `normalizar_chave_pix()` de `app/Http/Requests/ClientesMeiosPagamentoRequest.php` para o trait `app/Http/Requests/Concerns/ChavePix.php` (mesmos nomes e comportamento) e fazer o `ClientesMeiosPagamentoRequest` usar o trait (R-13)
- [ ] T018 Criar `app/Http/Requests/Concerns/DadosAfiliado.php`: normalização (CPF, telefone e DDI só dígitos; e-mail minúsculo; login com `trim`; vazios → nulo; chave Pix pelo `ChavePix`) e regras comuns: `nome` obrigatório até 150; `login` até 80, letras, números, ponto, hífen e sublinhado, único sem diferenciar maiúsculas entre `afiliados` e `usuarios` **contando excluídos**; `email` até 150 único em `afiliados`; `cpf` com `CpfValido` único em `afiliados`; telefone (DDI 55: 10 ou 11 dígitos; demais: 4 a 14); `estado` em UFs + `EX`; senha `Password::min(8)->letters()->numbers()` + `confirmed`; `pix_tipo_chave` `Rule::enum(TipoChavePix::class)`; `pix_chave` até 150 com `regra_chave_pix()`; mensagens em português
- [ ] T019 ⚠️ Em `app/Http/Resources/ClientesResource.php`, `mascarar_cpf`, `mascarar_telefone` e `mascarar_email` de `private static` para `public static` (sem outra mudança, R-12)
- [ ] T020 Criar `app/Http/Resources/AfiliadosResource.php` (contracts/api §2.1): campos do afiliado sem o sufixo de exclusão, `gerente` (`id`, `nome`), `comissao` vigente, `meio_pagamento`, `valor_a_receber`, `ultima_prestacao` e `totais_periodo` quando carregados (`whenLoaded`/atributos); CPF, telefone, e-mail e chave Pix mascarados com `ClientesResource::deve_mascarar` e as máscaras públicas; `deleted_at` só na lixeira
- [ ] T021 [P] Criar `app/Http/Resources/AfiliadosComissoesResource.php`, `AfiliadosLancamentosResource.php` e `AfiliadosPrestacoesContasResource.php` (contracts/api §1.7, §1.9, §2.9, §2.10, §2.12)
- [ ] T022 Criar `app/Services/RegraComissaoAfiliados.php` (R-08): `definir_inicial(Afiliados, ?array $regra, ?Usuarios $autor)` — usa a regra enviada só se `$autor` tem `afiliados.editar_comissao` (`Funcao::usuario_pode`), senão a padrão de `Configuracoes::atual()`; `trocar(Afiliados, TipoGanhoAfiliado, string $percentual, Usuarios)` — transação com `lockForUpdate` do afiliado, fecha a vigente com `now()` e abre a nova
- [ ] T023 Criar o layout de e-mail `resources/views/emails/layout.blade.php` no visual de `wssports.bet/resources/views/emails/filiado/cadastro.blade.php` (centralizado, título em caixa alta, logo de `Configuracoes::atual()->url_logo()`, botão na cor principal do tema), usado por todos os e-mails desta feature (R-10)

**Checkpoint**: `php artisan migrate` e `php artisan db:seed --class=AfiliadosSeeder` rodam sem erro.

---

## Phase 3: User Story 1 - Cadastrar afiliado pelo painel (Priority: P1) 🎯 MVP

**Goal**: Admin, Supervisor ou Gerente cadastra afiliado Ativo pela API, com código, regra e Pix.

**Independent Test**: quickstart §1.

- [ ] T024 [US1] Criar `app/Services/CadastroAfiliados.php` com `cadastrar_pelo_painel(array $dados, Usuarios $autor): Afiliados` — transação: cria o afiliado (`situacao` Ativo, `origem_cadastro` Painel, `cadastrado_por`, `codigo` por `Afiliados::gerar_codigo()`), responsável = o próprio autor quando Gerente, senão `usuarios_id` informado; cria o meio de pagamento; chama `RegraComissaoAfiliados::definir_inicial`; trata `UniqueConstraintViolationException` como 422 (como `CadastroClientes`)
- [ ] T025 [P] [US1] Criar `app/Http/Requests/StoreAfiliadosRequest.php` com `DadosAfiliado`: obrigatórios `nome`, `login`, `password` (+ confirmação), `email`, `cpf`, `telefone`, `pix_tipo_chave`, `pix_chave`; `usuarios_id` nulo existente com função Gerente (ignorado para Gerente); `tipo_ganho` `Rule::enum(TipoGanhoAfiliado::class)` e `percentual` numérico 0 a 100 com até 2 casas, juntos
- [ ] T026 [US1] Criar `app/Http/Controllers/AfiliadosController.php` (`HasMiddleware`, `GarantirPermissaoCliente`) com `store` (`afiliados.cadastrar`, 201 com `AfiliadosResource`) e `show` (`afiliados.consultar`, carrega gerente, regra, meio de pagamento, valor a receber e última prestação) e o método privado `garantir_alcance(Request, Afiliados)` (Gerente só os de `usuarios_id` dele; senão 404 "Afiliado não encontrado.", R-14)
- [ ] T027 [US1] ⚠️ Em `app/Http/Requests/StoreUsuariosRequest.php`, `regra_login_unico()` também confere `afiliados` com excluídos, sem diferenciar maiúsculas (mesma mensagem "O login informado já está em uso.", R-04)
- [ ] T028 [US1] ⚠️ Em `routes/api.php`, grupo `auth:api` + `garantir_acesso` com `POST afiliados` e `GET afiliados/{afiliado}` (`->missing(fn () => abort(404, 'Afiliado não encontrado.'))`); ⚠️ regenerar `docs/postman/wssports_api.postman_collection.json`

**Checkpoint**: quickstart §1 passa.

---

## Phase 4: User Story 2 - Cadastro público com aprovação (Priority: P1)

**Goal**: cadastro em `/cadastro-afiliado` como "Em análise", aprovação/recusa por Admin/Supervisor e e-mails.

**Independent Test**: quickstart §2.

- [ ] T029 [P] [US2] Criar os Mailables `app/Mail/AfiliadoCadastroRecebido.php` (assunto "[ CADASTRO ] Recebemos seu cadastro de afiliado"), `app/Mail/AfiliadoAprovado.php` ("[ CADASTRO ] Seu cadastro de afiliado foi aprovado", botão "ACESSAR" para `/cadastro-afiliado`) e `app/Mail/AfiliadoRecusado.php` ("[ CADASTRO ] Seu cadastro de afiliado não foi aprovado", com o motivo quando houver), com `afterCommit()`, e as views `resources/views/emails/afiliados/cadastro_recebido.blade.php`, `aprovado.blade.php` e `recusado.blade.php` sobre o layout da T023, com os textos do `cadastro.blade.php` antigo ("Seu cadastro está em analise." → "Seu cadastro está em análise.")
- [ ] T030 [US2] Em `app/Services/CadastroAfiliados.php`: `cadastrar_publico(array $dados): Afiliados` (situação Em análise, origem Público, sem gerente, regra padrão, e-mail `AfiliadoCadastroRecebido` por `Mail::to()->queue()`); `aprovar(Afiliados, Usuarios, array $opcoes)` (só de Em análise ou Recusado, senão 422; grava `decidido_por`/`decidido_em`; aplica `usuarios_id` e regra opcionais via `RegraComissaoAfiliados::trocar`; e-mail `AfiliadoAprovado`); `recusar(Afiliados, Usuarios, ?string $motivo)` (só de Em análise; `motivo_recusa`; e-mail `AfiliadoRecusado`)
- [ ] T031 [P] [US2] Criar `app/Http/Requests/CadastroPublicoAfiliadoRequest.php` com `DadosAfiliado`, os mesmos obrigatórios do `StoreAfiliadosRequest`, sem `usuarios_id`/`tipo_ganho`/`percentual`/`situacao` (removidos do `validated`), e limite de 5 cadastros por minuto por IP (chave `cadastro_afiliado|IP`, 429 em português, como `StoreClientesRequest`)
- [ ] T032 [P] [US2] Criar `app/Http/Requests/AprovarAfiliadoRequest.php` (`usuarios_id` Gerente existente, `tipo_ganho` + `percentual` opcionais juntos) e as regras de `recusar` (`motivo` nulo até 255) no próprio controller
- [ ] T033 [US2] Criar `app/Http/Controllers/AreaAfiliadoCadastroController.php` (`store`: 201 com a mensagem do contracts/api §1.1, sem token) e, em `AfiliadosController`, `aprovar` e `recusar` (`afiliados.alterar_situacao` + `AlcanceHierarquia::garantir_gestor_geral`)
- [ ] T034 [US2] ⚠️ Em `routes/api.php`: `POST area-afiliado/cadastro`, `POST afiliados/{afiliado}/aprovar`, `POST afiliados/{afiliado}/recusar`; ⚠️ regenerar a coleção do Postman
- [ ] T035 [US2] Criar `app/Http/Controllers/AfiliadosPaginasController.php` (`cadastro` → `Inertia::render('AffiliateLanding')`, `painel` → `Inertia::render('AffiliatePanel')`) e ⚠️ em `routes/web.php` `Route::get('cadastro-afiliado', …)->name('cadastro_afiliado')` e `Route::get('afiliados', …)->name('afiliados')`
- [ ] T036 [US2] Criar `resources/js/components/AffiliateSignupModal/` (`index.jsx`, `styles.jsx`) conforme contracts/paginas §1: título "Cadastro afiliados", campos do `modals/affiliated` antigo com ícone Material na cor do tema (nome, login, senha, confirmação, e-mail, CPF com máscara, cidade, estado com as UFs + "Estrangeiro", endereço, telefone com máscara, tipo de chave Pix e chave com máscara pelo tipo, desabilitada sem tipo), erro por campo (422), olho na senha, `useVoltarFecha`, Esc, spinner no botão "CRIAR CONTA", alerta de sucesso e fechar; envia `POST /area-afiliado/cadastro` com CPF, telefone e chave só com dígitos quando for o caso
- [ ] T037 [US2] Criar `resources/js/pages/AffiliateLanding/` (`index.jsx`, `styles.jsx`) reproduzindo `screens/new-affiliated` (cabeçalho `#222` com logo, botões "Cadastrar-se" e "Entrar", "Seja um afiliado!", texto com "excelentes" corrigido, botão "Quero me afiliar", imagem `/images/afiliado.jpg`, grade de duas colunas e coluna única abaixo de 900px); "Cadastrar-se" e "Quero me afiliar" abrem o `AffiliateSignupModal`; "Entrar" abre o `AuthModal` na aba "entrar"; com sessão `tipo: 'afiliado'`, os botões viram "Meu painel"
- [ ] T038 [US2] ⚠️ Em `resources/js/components/Footer/index.jsx`, "Afiliados" vira `TextLink as={Link} href="/cadastro-afiliado"` e o comentário passa a citar só "Quem somos"

**Checkpoint**: quickstart §2 passa.

---

## Phase 5: User Story 3 - Entrar pela mesma tela dos demais usuários (Priority: P1)

**Goal**: login com token próprio pelo `AuthModal` e recuperação de senha por e-mail.

**Independent Test**: quickstart §3.

- [ ] T039 [P] [US3] Criar `app/Http/Requests/LoginAfiliadoRequest.php` copiando `LoginRequest` (5 erros por login + IP em 60 s), com a chave prefixada `afiliado|`
- [ ] T040 [US3] Criar `app/Http/Controllers/AreaAfiliadoAutenticacaoController.php` no padrão de `AreaClienteAutenticacaoController`: `login` (401 "Login ou senha inválidos."; 403 com a mensagem da situação de T016 e `logout` do token), `refresh`, `logout`
- [ ] T041 [P] [US3] Criar `app/Mail/AfiliadoCodigoRecuperacao.php` ("[ RECUPERAÇÃO ] Seu código para trocar a senha", código de 6 dígitos e validade de 15 minutos) com a view `resources/views/emails/afiliados/codigo_recuperacao.blade.php` sobre o layout da T023
- [ ] T042 [P] [US3] Criar `app/Http/Requests/RecuperarSenhaAfiliadoRequest.php` (`identificacao` obrigatória até 150, `trim` e minúsculas) e `app/Http/Requests/RedefinirSenhaAfiliadoRequest.php` (`identificacao`, `codigo` 6 dígitos, senha `min(8)->letters()->numbers()` + `confirmed`)
- [ ] T043 [US3] Criar `app/Http/Controllers/AreaAfiliadoRecuperacaoSenhaController.php` copiando `AreaClienteRecuperacaoSenhaController` (15 min, 5 tentativas, 1 pedido por identificação a cada 60 s, resposta sempre "Se o cadastro existir, enviaremos um código para o e-mail."), procurando o afiliado por `login` ou `email` (sem diferenciar maiúsculas), só para `pode_acessar()`, enviando `AfiliadoCodigoRecuperacao` por `queue()` depois do commit e chamando `invalidar_tokens()` na troca
- [ ] T044 [US3] ⚠️ Em `routes/api.php`, grupo `area-afiliado`: `POST auth/login`, `POST auth/recuperar-senha`, `POST auth/redefinir-senha` e, com `auth:afiliados` + `GarantirAcessoAfiliado`, `POST auth/refresh` e `POST auth/logout`; ⚠️ regenerar a coleção do Postman
- [ ] T045 [US3] ⚠️ Em `resources/js/hooks/useSessao.js`: `rotas_login.afiliado` (`POST /area-afiliado/auth/login` com `login` e `password`), `rotas_logout.afiliado`, ordem do `entrar` (telefone: `cliente`, `usuario`, `afiliado`; demais: `usuario`, `afiliado`, `cliente`, R-04), nome do afiliado por `GET /area-afiliado/meus-dados` (função `nome_do_afiliado`, como `nome_do_cliente`); `usa_token` continua sem o tipo afiliado; ⚠️ em `resources/js/utils/session.js`, comentário do formato com `'afiliado'`
- [ ] T046 [US3] ⚠️ Em `resources/js/components/AuthModal/index.jsx`, depois do login com sessão `tipo: 'afiliado'`, `router.visit('/afiliados')` (antes do `ao_entrar_com_sucesso`)
- [ ] T047 [US3] Criar `resources/js/components/AffiliateRecoveryModal/` (`index.jsx`, `styles.jsx`) conforme contracts/paginas §1: etapa 1 (login ou e-mail → "Enviar código") e etapa 2 (código, nova senha e confirmação → "Alterar senha"), mesmo padrão de campos, erros e fechamento do `AffiliateSignupModal`; no sucesso, alerta e abre o `AuthModal` na aba "entrar"; e em `resources/js/pages/AffiliateLanding/index.jsx` o link "Esqueci minha senha" que o abre

**Checkpoint**: quickstart §3 passa (a parte do `/afiliados` termina na US7).

---

## Phase 6: User Story 4 - Indicação de clientes pelo link (Priority: P1)

**Goal**: cliente cadastrado pelo `?user=CODIGO` fica ligado ao afiliado por FK; promoções por `afiliados_id`.

**Independent Test**: quickstart §4.

- [ ] T048 [US4] Criar `database/migrations/2026_10_17_000008_alterar_afiliado_clientes_e_promocoes_table.php` (R-09): em `clientes` e `clientes_promocoes`, `afiliados_id` FK nula para `afiliados` (com `index` e comentário), preenchida por `UPPER(TRIM(codigo_afiliado)) = afiliados.codigo` pelo query builder, e remoção de `codigo_afiliado`; `down()` recria `codigo_afiliado` (varchar(50) nulo) a partir do `codigo` do afiliado ligado e remove `afiliados_id`
- [ ] T049 [US4] Criar `app/Services/VinculoAfiliados.php` (R-05): `resolver(?string $codigo): ?Afiliados` — `trim` + maiúsculas; procura com excluídos por `codigo = X` ou `codigo LIKE 'X\_deleted\_%'`, preferindo o não excluído; `afiliados_id_para_cliente(?string $codigo): ?int` — id só se `pode_receber_clientes()`
- [ ] T050 [US4] ⚠️ Em `app/Models/Clientes.php`, `afiliados_id` no lugar de `codigo_afiliado` no `fillable` e relação `afiliado()` (`belongsTo(Afiliados::class)`); ⚠️ em `app/Models/ClientesPromocoes.php`, `afiliados_id` no `fillable`, relação `afiliado()` e `scopeParaAfiliado(Builder, ?int $afiliados_id)` (sem afiliado: só `afiliados_id` nulo; com: nulos ou o dele)
- [ ] T051 [US4] ⚠️ Em `app/Http/Requests/StoreClientesRequest.php`, regra no `codigo_afiliado`: código não nulo que `VinculoAfiliados::resolver` não encontra → "Código de afiliado não encontrado."; ⚠️ em `app/Http/Requests/Concerns/DadosCliente.php`, a mensagem
- [ ] T052 [US4] ⚠️ Em `app/Services/CadastroClientes.php`, troca `codigo_afiliado` por `afiliados_id` = `VinculoAfiliados::afiliados_id_para_cliente($dados['codigo_afiliado'] ?? null)` ao criar o cliente e `paraAfiliado($cliente->afiliados_id)` nas promoções de cadastro
- [ ] T053 [P] [US4] ⚠️ Em `app/Services/EscolhaPromocoes.php`, `app/Services/Indicacoes.php` e `app/Http/Resources/MinhaContaResource.php`, `paraAfiliado(...->afiliados_id)` e `orderByRaw('afiliados_id is null')` no lugar das versões com `codigo_afiliado` (sem outra mudança)
- [ ] T054 [P] [US4] ⚠️ Em `app/Http/Requests/ClientesPromocoesRequest.php`, `afiliados_id` (nulo, `Rule::exists('afiliados', 'id')->whereNull('deleted_at')`, mensagem "Afiliado não encontrado.") no lugar de `codigo_afiliado`; ⚠️ em `app/Http/Resources/ClientesPromocoesResource.php`, `afiliados_id` e `afiliado` (`id`, `nome`, `codigo`)
- [ ] T055 [P] [US4] ⚠️ Em `app/Http/Resources/ClientesResource.php`, `afiliados_id` e `afiliado` (`id`, `nome`, `codigo`, via `whenLoaded`) no lugar de `codigo_afiliado`; ⚠️ em `app/Http/Requests/UpdateClientesRequest.php`, `afiliados_id` (`sometimes`, nulo, existente fora da lixeira) no lugar de `codigo_afiliado`; ⚠️ em `app/Http/Controllers/ClientesController.php`, filtro `afiliados_id` (inteiro) no lugar de `codigo_afiliado` e `with('afiliado')` em `show`/`listar`
- [ ] T056 [US4] ⚠️ Regenerar a coleção do Postman com os corpos e filtros alterados (contracts/api §4)

**Checkpoint**: quickstart §4 passa.

---

## Phase 7: User Story 5 - Comissão gerada a cada movimento do cliente (Priority: P1)

**Goal**: lançamentos gravados na transação do depósito aprovado, do estorno e do saque pago.

**Independent Test**: quickstart §5.

- [ ] T057 [US5] Criar `app/Services/ComissoesAfiliados.php` (R-06, data-model §3): `registrar_deposito(Depositos, bool $primeiro_deposito)`, `registrar_estorno(Depositos, string $valor_estornado_agora)` e `registrar_saque(Saques)`; afiliado do cliente por `clientes.afiliados_id` sem excluídos (na lixeira não gera); regra vigente na hora; tabela de geração do data-model §3; percentual pelo `EscolhaPromocoes::percentual`; estorno procura o lançamento `deposito:{id}` e usa o mesmo tipo e percentual sobre o valor estornado agora; `chave_evento` e captura de `UniqueConstraintViolationException` para ignorar o repetido (FR-033); `ocorrido_em` = `pago_em` do depósito/saque ou `now()` no estorno
- [ ] T058 [US5] ⚠️ Em `app/Services/ProcessamentoDepositos.php`, injetar `ComissoesAfiliados` no construtor; chamar `registrar_deposito($deposito, $primeiro_deposito)` em `depois_de_aprovar` e `registrar_estorno($deposito, $diferenca)` em `estornar`, logo depois do débito (parcial e total); sem outra mudança
- [ ] T059 [US5] ⚠️ Em `app/Services/PagamentoSaques.php`, injetar `ComissoesAfiliados` no construtor e chamar `registrar_saque($saque)` em `dar_baixa` (dentro da transação) e em `confirmar` quando passa a Pago; sem outra mudança
- [ ] T060 [US5] Criar `app/Http/Requests/ComissaoAfiliadoRequest.php` (`tipo_ganho` obrigatório enum, `percentual` obrigatório 0 a 100 com até 2 casas) e `app/Http/Controllers/AfiliadosComissoesController.php` (`index` paginado com `afiliados.consultar`; `update` com `afiliados.editar_comissao` chamando `RegraComissaoAfiliados::trocar`), com o alcance do Gerente
- [ ] T061 [US5] ⚠️ Em `routes/api.php`: `GET afiliados/{afiliado}/comissoes` e `PUT afiliados/{afiliado}/comissao`; ⚠️ regenerar a coleção do Postman

**Checkpoint**: quickstart §5 passa (conferido pelo `GET /afiliados/{id}/lancamentos` da US8 ou direto no banco).

---

## Phase 8: User Story 6 - Prestação de contas (Priority: P1)

**Goal**: prestação total com a senha de quem presta, negativo zerado e histórico.

**Independent Test**: quickstart §6.

- [ ] T062 [US6] Criar `app/Services/PrestacaoContasAfiliados.php` (R-07, data-model §4): `prestar(Afiliados, Usuarios, ?string $observacao)` — transação com `lockForUpdate` do afiliado; soma `bcadd` dos lançamentos em aberto; `valor_pago` = apurado se > 0, senão `0.00`; `periodo_inicio` = `periodo_fim` da prestação anterior ou `created_at` do afiliado; `periodo_fim` = agora; `quantidade_lancamentos`; liga os lançamentos à prestação
- [ ] T063 [P] [US6] Criar `app/Http/Requests/PrestacaoContasAfiliadoRequest.php` (`password` obrigatório conferido com `Hash::check` contra o usuário logado → 422 "Senha incorreta." no campo; `observacao` nula até 500)
- [ ] T064 [US6] Criar `app/Http/Controllers/AfiliadosPrestacoesController.php`: `index` (`afiliados.consultar`, paginado, com `usuario` `id`/`nome`) e `store` (`afiliados.prestar_contas`, 201), com o alcance do Gerente; afiliado na lixeira → 404 pelo route binding
- [ ] T065 [US6] ⚠️ Em `routes/api.php`: `GET` e `POST afiliados/{afiliado}/prestacoes`; ⚠️ regenerar a coleção do Postman

**Checkpoint**: quickstart §6 passa.

---

## Phase 9: User Story 7 - Painel do afiliado (Priority: P2)

**Goal**: `/afiliados` no visual do antigo com extrato, clientes, prestações, link, Meus dados e senha.

**Independent Test**: quickstart §7.

- [ ] T066 [P] [US7] Criar `app/Http/Resources/PainelAfiliadoResource.php` (contracts/api §1.6, com `link` = `url('/').'?user='.codigo`), `app/Http/Resources/ClienteIndicadoResource.php` (nome como "João S. P.", `cadastrado_em`, `total_depositado`, R-12) e `app/Http/Resources/MeusDadosAfiliadoResource.php` (contracts/api §1.10, sem máscara)
- [ ] T067 [US7] Criar `app/Http/Controllers/AreaAfiliadoPainelController.php` (`$request->user('afiliados')`): `painel`, `extrato` (`de`, `ate`, `situacao` aberto/prestado, 30 por página, máx. 100, mais recente primeiro), `clientes` (com `total_depositado` = soma de `valor − valor_estornado` dos depósitos Aprovado/Estornado por subconsulta) e `prestacoes`
- [ ] T068 [P] [US7] Criar `app/Http/Requests/UpdateMeusDadosAfiliadoRequest.php` (só `ddi`, `telefone`, `cidade`, `estado`, `endereco`, `pix_tipo_chave`, `pix_chave`; `nome`, `login`, `email`, `cpf`, `codigo` `prohibited`) e `app/Http/Requests/AlterarSenhaAfiliadoRequest.php` (`senha_atual` conferida → 422 "A senha atual está incorreta.", nova senha `min(8)->letters()->numbers()` + `confirmed`)
- [ ] T069 [US7] Criar `app/Http/Controllers/AreaAfiliadoMeusDadosController.php`: `show`, `update` (afiliado e meio de pagamento numa transação) e `alterar_senha` (troca, `invalidar_tokens()` e devolve token novo no formato do login)
- [ ] T070 [US7] ⚠️ Em `routes/api.php`, no grupo `area-afiliado` autenticado: `GET painel`, `GET extrato`, `GET clientes`, `GET prestacoes`, `GET` e `PATCH meus-dados`, `PUT meus-dados/senha`; ⚠️ regenerar a coleção do Postman
- [ ] T071 [US7] Criar `resources/js/hooks/useAfiliado.js`: com a sessão `tipo: 'afiliado'`, carrega painel, extrato (filtros e "carregar mais"), clientes e prestações com `Authorization` do token da sessão; 401/403 → `sair()` e `router.visit('/?entrar=1')`
- [ ] T072 [P] [US7] Criar `resources/js/components/AffiliateStatement/` (tabela ID, OP., VALOR, COMISSÃO, DATA do `screens/affiliated`, comissão negativa com "-", "Nenhum registro encontrado.", filtro de período + Em aberto/Prestados e "Carregar mais"), `resources/js/components/AffiliateClients/` (NOME, CADASTRO, DEPOSITADO) e `resources/js/components/AffiliateSettlements/` (PERÍODO, APURADO, PAGO, OBS.), cada um com `index.jsx` e `styles.jsx`
- [ ] T073 [P] [US7] Criar `resources/js/components/AffiliateProfileModal/` e `resources/js/components/AffiliatePasswordModal/` sobre o `ModalCard` (contracts/paginas §2): campos travados com "Para alterar, fale com a banca.", máscaras do Pix como no `ProfileModal`, erro por campo, olho na senha; a troca de senha grava o token novo com `criar_sessao`
- [ ] T074 [US7] Criar `resources/js/pages/AffiliatePanel/` (`index.jsx`, `styles.jsx`) reproduzindo `screens/affiliated` (seta de voltar para `/`, "Olá, {nome}!", logo, "Meu Saldo", linha "Última prestação R$ X em dd/mm hh:mm" ou "--,--", "Obs.: Sua comissão é de X% em cima de {tipo}.") com os acréscimos de contracts/paginas §2: botão "Copiar meu link" (alerta "Link copiado!"), abas Extrato | Clientes | Prestações, menu `more_vert` com Meus dados, Alterar senha e Sair; sem sessão de afiliado → `router.visit('/?entrar=1')`

**Checkpoint**: quickstart §7 passa.

---

## Phase 10: User Story 8 - Gerir afiliados pela API (Priority: P2)

**Goal**: listar com totais, editar, ativar/inativar, excluir, restaurar e consultar extrato e clientes.

**Independent Test**: quickstart §8.

- [ ] T075 [US8] Criar `app/Services/ListagemAfiliados.php` (R-15): consulta com filtros (`situacao`, `busca` por nome/login/código, `usuarios_id`, alcance do Gerente) e totais do período por `withCount`/`withSum` com closures (`cadastros` por `clientes.created_at`; `depositos` = `valor − valor_estornado` de Aprovado/Estornado por `pago_em`; `saques` Pago por `pago_em`; `comissao` por `ocorrido_em`), `valor_a_receber` (lançamentos em aberto) e `ultima_prestacao`; `de`/`ate` padrão hoje no fuso `America/Sao_Paulo`
- [ ] T076 [P] [US8] Criar `app/Http/Requests/UpdateAfiliadosRequest.php` (`DadosAfiliado` com `ignore` do próprio id; `login` `prohibited`; `password` opcional + confirmação; `usuarios_id` e `codigo` — letras e números de 4 a 50, único — só para Admin/Supervisor, senão `prohibited`) e `app/Http/Requests/RestaurarAfiliadosRequest.php` (valores novos opcionais de `login`, `email`, `cpf`, `codigo`)
- [ ] T077 [US8] Em `app/Http/Controllers/AfiliadosController.php`: `index` e `excluidos` (`ListagemAfiliados`, `por_pagina` padrão 20 entre 1 e 100), `update` (`afiliados.editar`; senha nova → `invalidar_tokens()`), `alterar_situacao` (`afiliados.alterar_situacao`; só entre Ativo e Inativo; inativar → `invalidar_tokens()`), `destroy` (`afiliados.excluir`; sufixo `_deleted_<timestamp>` em `login`, `email`, `cpf`, `codigo`, `invalidar_tokens()`, soft delete) e `restaurar` (`afiliados.restaurar`; conflitos de `login` também contra `usuarios`, 422 por campo), no padrão de `ClientesController`, todos com o alcance do Gerente
- [ ] T078 [US8] Criar `app/Http/Controllers/AfiliadosExtratoController.php`: `lancamentos` (filtros do contracts/api §2.10, com `cliente` `id`/`nome`) e `clientes` (`ClientesResource` com máscara + `total_depositado`), com `afiliados.consultar` e o alcance do Gerente
- [ ] T079 [P] [US8] Criar `app/Http/Requests/ConfiguracoesAfiliadosRequest.php`, `app/Http/Resources/ConfiguracoesAfiliadosResource.php` e `app/Http/Controllers/ConfiguracoesAfiliadosController.php` (`show`/`update` com `configuracoes.editar`, padrão de `ConfiguracoesCassinoController`)
- [ ] T080 [US8] ⚠️ Em `routes/api.php`: `GET afiliados/excluidos` e `POST afiliados/{afiliado}/restaurar` (`withTrashed`) antes do resource, `GET afiliados`, `PATCH afiliados/{afiliado}`, `PATCH afiliados/{afiliado}/situacao`, `DELETE afiliados/{afiliado}`, `GET afiliados/{afiliado}/lancamentos`, `GET afiliados/{afiliado}/clientes`, `GET` e `PUT configuracoes/afiliados`; ⚠️ regenerar a coleção do Postman

**Checkpoint**: quickstart §8 passa.

---

## Phase 11: Polish & Cross-Cutting Concerns

**Purpose**: e-mail de boas-vindas do cliente (FR-013a), documentação e revisão.

- [ ] T081 Criar `app/Mail/ClienteCadastroRealizado.php` ("[ CADASTRO ] Cadastro realizado com sucesso!", `afterCommit()`) com a view `resources/views/emails/clientes/cadastro_realizado.blade.php` sobre o layout da T023 ("OLÁ, SEJA BEM VINDO :)", nome do cliente, texto de boas-vindas e botão "ACESSAR O SITE" para `url('/')`), e o listener `app/Listeners/EnviarEmailCadastroCliente.php` que ouve `ClienteCadastrado` e, só com `email` preenchido, faz `Mail::to()->queue()` dentro de `try/catch` com `Log::error` (R-21; descoberto automaticamente, sem alterar arquivos existentes)
- [ ] T082 [P] ⚠️ Notas "Alterado pela spec 012" em `specs/002-clientes/spec.md` (FR-009: código de afiliado guardado como ligação ao afiliado) e `specs/007-painel-cliente-pagamentos/spec.md` (FR-018g e "Afiliado sem chave estrangeira": agora `afiliados_id`) (R-18)
- [ ] T083 Conferir a coleção do Postman final: todas as rotas de contracts/api §1 a §3 e as alteradas do §4, com `base_url` e `token`
- [ ] T084 Revisão de legibilidade final (Princípio V) dos serviços `CadastroAfiliados`, `ComissoesAfiliados`, `PrestacaoContasAfiliados`, `ListagemAfiliados` e do `AfiliadosController`
- [ ] T085 Rodar o roteiro completo do [quickstart.md](quickstart.md) (§1 a §10) e registrar o resultado

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sem dependências.
- **Foundational (Phase 2)**: depende do Setup; bloqueia todas as stories.
- **US1 (Phase 3)**: depende da Foundational.
- **US2 (Phase 4)**: depende da US1 (`CadastroAfiliados`, `AfiliadosController`).
- **US3 (Phase 5)**: depende da Foundational; usa afiliados Ativos (seeder ou US1/US2).
- **US4 (Phase 6)**: depende da Foundational (tabela `afiliados`).
- **US5 (Phase 7)**: depende da US4 (`clientes.afiliados_id`).
- **US6 (Phase 8)**: depende da US5 (lançamentos).
- **US7 (Phase 9)**: depende da US3 (token) e, para ter dados, da US5 e da US6.
- **US8 (Phase 10)**: depende da US1; o extrato depende da US5.
- **Polish (Phase 11)**: T081 depende só da T023; as demais, de todas as stories.

### Within Each User Story

- Migrations → models → serviços → requests → controllers → rotas/Postman → frontend.
- Arquivos ⚠️ só nas tarefas indicadas.

### Parallel Opportunities

- Setup: T001 e T002.
- Foundational: T008, T009 e T010; T012 depois da T011; T021 com T020.
- US2: T029, T031 e T032; frontend (T036 a T038) em paralelo ao backend depois da T034.
- US3: T039, T041 e T042.
- US4: T053, T054 e T055 depois da T050.
- US7: T066 e T068; T072 e T073 em paralelo depois da T071.
- US8: T076 e T079.

---

## Parallel Example: Foundational

```text
Task: "T008 Criar migration afiliados_meios_pagamento"
Task: "T009 Criar migration afiliados_codigos_recuperacao"
Task: "T010 Criar migration de configuracoes de afiliados"
```

## Parallel Example: User Story 4

```text
Task: "T053 paraAfiliado por afiliados_id em EscolhaPromocoes, Indicacoes e MinhaContaResource"
Task: "T054 afiliados_id no ClientesPromocoesRequest e ClientesPromocoesResource"
Task: "T055 afiliados_id no ClientesResource, UpdateClientesRequest e ClientesController"
```

## Parallel Example: User Story 7

```text
Task: "T072 AffiliateStatement, AffiliateClients e AffiliateSettlements"
Task: "T073 AffiliateProfileModal e AffiliatePasswordModal"
```

---

## Implementation Strategy

### MVP First (User Story 1)

1. Phase 1 (Setup) e Phase 2 (Foundational).
2. Phase 3 (US1): cadastro pelo painel.
3. **Parar e validar** pelo quickstart §1.

### Incremental Delivery

1. US1 → US2 (cadastro público) → US3 (login): afiliados conseguem se cadastrar e entrar.
2. US4 → US5 → US6: indicação, comissão e prestação (o dinheiro).
3. US7 (painel) e US8 (gestão completa).
4. Polish: e-mail do cliente, notas nas specs 002 e 007, revisão final e quickstart completo.

---

## Notes

- [P] = arquivos diferentes, sem dependência pendente.
- Sem testes automatizados; validação pelo quickstart.
- Commit só quando o responsável pedir (`/speckit-git-commit`).
- Toda tarefa termina com a revisão de legibilidade (Princípio V) informada.
