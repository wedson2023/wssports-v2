# Research: Afiliados

**Feature**: `012-afiliados` | **Date**: 2026-10-17 | **Spec**: [spec.md](spec.md)

Decisões R-01 a R-21. As regras de negócio que mudam o comportamento do antigo foram aprovadas na
spec (Clarifications de 2026-10-17). As decisões de estrutura marcadas com **(confirmado)** mexem em
arquivos existentes ou criam padrão novo e foram aprovadas pelo responsável em 2026-10-17
(Princípios IV e VI).

---

## R-01 — Arquivos do sistema antigo consultados (Princípio VII)

Pasta `C:\Users\wedso\OneDrive\Área de Trabalho\projetos\wssports.bet` (somente leitura):

| Arquivo | O que tem |
|---|---|
| `app/Http/Controllers/FiliadosController.php` | `index` (ativos/lixeira com totais em SQL), `store`, `show`, `update`, `destroy` (soft, restaurar, forçar), `filiado` (painel público), `prestarContas` |
| `app/Models/Filiados.php` | model com `SoftDeletes`, `password` e `cpf` ocultos |
| `app/Mail/FiliadoMail.php` + `resources/views/emails/filiado/cadastro.blade.php` | e-mail "em análise" e "confirmado" |
| `app/Http/Controllers/AuthController.php` (login) | procura em `filiados` antes dos usuários; devolve o registro sem token |
| `app/Http/Controllers/CadastroController.php` (linhas 570–720 e 830–970) | `codigo` do cadastro: login de cliente (indicação) ou login de afiliado (`filiados_id`) |
| `app/Http/Controllers/BonusController.php`, `app/Console/Commands/MPCommand.php` | bônus por afiliado (`creditos_bonuses.filiados_id`), com fallback para o bônus geral |
| `database/migrations/2023_08_18_125101_create_filiados_table.php` e `add_filiados_id_creditos`, `add_usuarios_id_filiados`, `add_email_filiados` | estrutura da tabela |
| `routes/api.php` | `resource filiados` (JWT), `GET get-filiado/{id}` (pública), `POST prestar-contas-filiado` |
| `resources/js/screens/new-affiliated/*` | página pública `/cadastro-afiliado` (referência visual) |
| `resources/js/modals/affiliated/index.js` | modal do cadastro público (referência visual) |
| `resources/js/screens/affiliated/*` | painel `/afiliados` (referência visual) |
| `resources/js/modals/login/index.js` | redirecionamento do afiliado para `/afiliados` |
| `resources/js/screens/adm/affiliated/index.js`, `screens/adm/modals/affiliated/index.js` | gestão (só referência de regras: a gestão nesta spec é API) |
| `public/images/afiliado.jpg` | imagem da página pública |

**Como funcionava**: resumo na spec ("Referências do sistema antigo"). Os totais e o saldo eram
recalculados em SQL a cada consulta, sobre `creditos_transacaos` posteriores a
`prestado_contas_em`. A prestação gravava só `saldo_anterior` e a nova data.

## R-02 — Diferenças em relação ao antigo (Princípio VII)

Todas aprovadas na spec: gestão só por API; saldo negativo zerado; código separado do login
(migrados com o login antigo); sem saque do afiliado; prestação sempre total e com histórico;
melhorias no painel e nos modais; sem GGR; inativo continua ganhando; regra de comissão com
permissão própria; código de afiliado não ativo cadastra o cliente sem ligação; recuperação de
senha por e-mail. Correções técnicas (rota pública, token, JOIN, primeiro depósito, `->first()`,
CPF, SQL concatenado, float, paginação) listadas na spec.

## R-03 — Autenticação do afiliado: guard próprio `afiliados`

- **Decisão**: model `Afiliados` (`Authenticatable` + `JWTSubject`), provider `afiliados` e guard
  JWT `afiliados` em `config/auth.php`, no mesmo formato do guard `clientes`. Rotas em
  `/api/area-afiliado/*` protegidas por `auth:afiliados` + middleware `GarantirAcessoAfiliado`
  (situação Ativo, fora da lixeira e `iat` posterior a `tokens_validos_desde`), cópia do
  `GarantirAcessoCliente` sem o acesso assistido.
- **Separação entre guards**: o `jwt-auth` está com `lock_subject => true`, então o token leva a
  claim `prv` com o model; um token de cliente ou do painel com o mesmo `sub` é recusado pelo guard
  `afiliados` e vice-versa (FR-017), sem código extra.
- **Alternativas**: reutilizar `usuarios` com uma função "Afiliado" (rejeitada: misturaria o
  afiliado com a hierarquia e as permissões do spatie); token opaco próprio (rejeitada: foge do
  padrão JWT do projeto).

## R-04 — Login pela mesma tela e unicidade do login

- **Decisão (frontend)**: `useSessao.entrar` ganha o tipo `afiliado`
  (`POST /area-afiliado/auth/login` com `login` e `password`). Ordem: texto que parece telefone →
  `cliente`, `usuario`, `afiliado`; demais → `usuario`, `afiliado`, `cliente`. A sessão é gravada
  com `tipo: 'afiliado'`; o token **não** é aplicado nas requisições da tela de apostas
  (`usa_token` continua só com cliente e vendedor), então o afiliado navega no site como visitante.
  O `AuthModal` leva a `/afiliados` quando a sessão nova é de afiliado.
- **Mensagens**: o backend devolve 403 com mensagem própria por situação ("Seu cadastro ainda está
  em análise.", "Seu cadastro não foi aprovado.", "Seu acesso está desativado."). Como o laço já
  segue em 401/403/422 e lança o último erro, a mensagem do afiliado aparece quando ele é o último
  público tentado.
- **Limite de tentativas**: `LoginAfiliadoRequest` igual ao `LoginRequest` (5 por login + IP em
  60 s), com chave prefixada `afiliado|` para não somar com o do painel. Efeito aceito: como a tela
  tenta o painel antes do afiliado, cada senha errada de afiliado também conta no limite do painel,
  então o 429 pode vir do painel; em qualquer caso, o máximo continua 5 tentativas por minuto.
- **Unicidade**: login único sem diferenciar maiúsculas, contando excluídos, nos dois sentidos:
  o cadastro de afiliado confere `usuarios` e `afiliados`; o `StoreUsuariosRequest` passa a
  conferir também `afiliados` **(confirmado — alteração na spec 001, Princípio IV)**.
- **Alternativa rejeitada**: um único endpoint de login que descobre o público (mudaria o login do
  painel e do cliente, fora do escopo).

## R-05 — Código público de indicação

- **Decisão**: coluna `afiliados.codigo` (até 50), único, gravado em maiúsculas. Novo cadastro:
  8 caracteres do mesmo alfabeto de `Clientes::gerar_codigo_indicacao` (sem 0, O, 1, I). Migrados:
  o login antigo em maiúsculas (o antigo copiava `?user=LOGIN.toUpperCase()`). Admin e Supervisor
  podem alterar pela API (`codigo` aceito no update só para gestor geral), com letras, números,
  ponto, hífen e sublinhado, de 4 a 50 (`/^[A-Z0-9._-]{4,50}$/` depois das maiúsculas), para caber o
  login antigo (decisão do `/speckit-analyze`, U1).
- **Resolução** (serviço `VinculoAfiliados::resolver`): normaliza (`trim` + maiúsculas) e procura,
  com excluídos, por `codigo = X` ou `codigo LIKE 'X\_deleted\_%'`, preferindo o não excluído.
  Devolve `null` (não existe → erro 422 no cadastro do cliente) ou o afiliado; o cadastro só grava
  a ligação se `pode_receber_clientes()` (Ativo e fora da lixeira).
- **Alternativa rejeitada**: usar o `id` no link (expõe a contagem de afiliados e quebra os links
  antigos).

## R-06 — Lançamentos gerados no momento do evento

- **Decisão**: serviço `ComissoesAfiliados` com três entradas, chamadas **dentro das transações já
  existentes** (o depósito e o saque já estão bloqueados por `lockForUpdate`):
  - `registrar_deposito(Depositos $deposito, bool $primeiro_deposito)` — chamado em
    `ProcessamentoDepositos::depois_de_aprovar` (o `$primeiro_deposito` já é calculado ali:
    nenhum outro depósito Aprovado ou Estornado do cliente);
  - `registrar_estorno(Depositos $deposito, string $valor_estornado_agora)` — chamado em
    `ProcessamentoDepositos::estornar` **depois de gravar o novo `valor_estornado`** (e antes do
    `return` do estorno parcial), para a `chave_evento` usar o total já atualizado; vale no estorno
    parcial e no total;
  - `registrar_saque(Saques $saque)` — chamado em `PagamentoSaques::dar_baixa` e
    `PagamentoSaques::confirmar` quando o saque passa a Pago.
  **(confirmado — alteração em `ProcessamentoDepositos` e `PagamentoSaques`, Princípio IV)**
- **Regra**: busca o afiliado do cliente (`clientes.afiliados_id`, sem excluídos; na lixeira não
  gera). Usa a regra vigente na hora do evento. Qualquer depósito e Saldo líquido: depósito gera
  `+`; Primeiro depósito: só com `$primeiro_deposito`; Saldo líquido: saque pago gera `−`. Estorno:
  procura o lançamento do depósito; se existir, gera `−` com o mesmo tipo e percentual, sobre o
  valor estornado agora.
- **Cálculo**: `bcmath`, com o mesmo arredondamento meio para cima em centavos de
  `EscolhaPromocoes::percentual` (reaproveitado). Valor gravado com sinal.
- **Idempotência (FR-033)**: coluna `chave_evento` única: `deposito:{id}`, `saque:{id}`,
  `estorno:{deposito_id}:{valor_estornado_total}`. A segunda gravação do mesmo evento cai na
  restrição única e é ignorada.
- **Alternativas**: calcular na consulta como o antigo (rejeitada: é a origem dos erros e não
  guarda o percentual da época); evento/listener assíncrono (rejeitada: o lançamento ficaria fora
  da transação do dinheiro e poderia se perder).

## R-07 — Prestação de contas

- **Decisão**: serviço `PrestacaoContasAfiliados::prestar(Afiliados, Usuarios, ?string $observacao)`
  numa transação: bloqueia a linha do afiliado (`lockForUpdate`), soma os lançamentos em aberto,
  cria a prestação (valor apurado, valor pago = apurado se positivo senão 0, início = fim da
  prestação anterior ou `created_at` do afiliado, fim = agora, quantidade) e liga os lançamentos a
  ela. A senha é conferida antes (`Hash::check` com a senha do usuário do painel; 422 no campo
  `password`). A segunda prestação simultânea espera o bloqueio e não encontra lançamentos: grava
  uma prestação zerada (permitido pelo FR-039, nunca paga duas vezes).
- **Lançamento gerado durante a prestação**: entra depois do bloqueio e fica em aberto para a
  próxima.
- **Alternativa rejeitada**: escolher a data final (o antigo permitia data no passado; a spec pede
  prestação sempre até o momento).

## R-08 — Regras de comissão com vigência e regra padrão

- **Decisão**: tabela `afiliados_comissoes` (`tipo_ganho`, `percentual`, `vigente_desde`,
  `vigente_ate` nulo na vigente). Trocar = fechar a vigente com `now()` e abrir a nova, numa
  transação com bloqueio do afiliado. Enum novo `TipoGanhoAfiliado` (`Saldo líquido`,
  `Qualquer depósito`, `Primeiro depósito`); o `TipoGanho` existente é de promoção (Fixo/
  Percentual) e não serve.
- **Regra padrão** (FR-015): colunas novas em `configuracoes` (`afiliados_tipo_ganho_padrao`,
  padrão `Saldo líquido`; `afiliados_percentual_padrao`, padrão 0), editadas por
  `GET/PUT /configuracoes/afiliados` (`configuracoes.editar`), no padrão de
  `ConfiguracoesCassinoController`.
- **Permissão**: `afiliados.editar_comissao` decide se a regra enviada no cadastro vale; sem ela,
  usa a padrão.

## R-09 — Ligação de clientes e promoções por chave estrangeira

- **Decisão**: migration que, em `clientes` e em `clientes_promocoes`, cria `afiliados_id`
  (FK nula para `afiliados`), preenche pelo código (`UPPER(TRIM(codigo_afiliado)) = afiliados.codigo`)
  e remove `codigo_afiliado` (constituição, "Referências a tabelas ainda inexistentes"). Como a
  tabela `afiliados` nasce vazia nesta entrega, os códigos digitados até hoje ficam sem ligação
  (FR-024); a importação dos afiliados do antigo (fora da spec) liga os clientes importados pelo
  `filiados_id` antigo.
- **Contrato da API**:
  - cadastro público do cliente continua recebendo `codigo_afiliado` (texto do link); o
    `StoreClientesRequest` valida que o código existe (R-05) e o `CadastroClientes` grava
    `afiliados_id` quando o afiliado pode receber clientes;
  - `ClientesResource` passa a devolver `afiliados_id` e `afiliado` (`id`, `nome`, `codigo`) no
    lugar de `codigo_afiliado`; filtro da listagem de clientes: `afiliados_id`;
  - `UpdateClientesRequest` (painel): `afiliados_id` no lugar de `codigo_afiliado`;
  - promoções: `afiliados_id` (existente, sem excluídos) no lugar de `codigo_afiliado` no request
    e no resource;
  - `ClientesPromocoes::scopeParaAfiliado(?int $afiliados_id)` e
    `orderByRaw('afiliados_id is null')` em `EscolhaPromocoes`, `Indicacoes`, `CadastroClientes` e
    `MinhaContaResource`.
  **(confirmado — alterações nos arquivos das specs 002 e 007, listadas no plano)**
- **Alternativa rejeitada**: manter `codigo_afiliado` em texto ao lado do id (duas fontes para a
  mesma ligação).

## R-10 — E-mails

- **Decisão**: primeiros Mailables do projeto em `app/Mail/` (`AfiliadoCadastroRecebido`,
  `AfiliadoAprovado`, `AfiliadoRecusado`, `AfiliadoCodigoRecuperacao`), com views em
  `resources/views/emails/afiliados/` no texto do `cadastro.blade.php` antigo (logo pelo
  `Configuracoes::url_logo()`, botão na cor do tema). Enviados com `Mail::to()->queue()` depois do
  commit (`afterCommit`), na fila `database` já configurada: falha no envio não desfaz cadastro nem
  decisão (FR-013). Em desenvolvimento, `MAIL_MAILER=log` (já no `.env.example`).
- **Alternativa rejeitada**: evento + listener que só registra em log, como o WhatsApp (a spec pede
  o e-mail de fato, e o Laravel já tem o envio pronto).

## R-11 — Recuperação de senha

- **Decisão**: `AreaAfiliadoRecuperacaoSenhaController` copiando o do cliente (6 dígitos com hash,
  15 minutos, 5 tentativas, um pedido por identificação a cada 60 s, resposta sempre igual), com
  identificação por login **ou** e-mail e envio pelo Mailable `AfiliadoCodigoRecuperacao`. Tabela
  `afiliados_codigos_recuperacao` igual a `clientes_codigos_recuperacao`. Só afiliado Ativo recebe.
  Na troca, `invalidar_tokens()`.

## R-12 — Máscaras e dados do afiliado na gestão

- **Decisão**: `AfiliadosResource` mascara CPF, telefone, e-mail e chave Pix com os mesmos
  formatos dos clientes, pela mesma permissão `clientes.ver_dados_completos` (FR-046). Os métodos
  `mascarar_cpf`, `mascarar_telefone` e `mascarar_email` do `ClientesResource` passam de `private`
  para `public` para serem reaproveitados **(confirmado — alteração mínima em arquivo da spec
  002)**.
- **Clientes indicados no painel do afiliado**: nome com a primeira palavra completa e as demais
  pela inicial (`João S. P.`), data do cadastro e total depositado (aprovado menos estornado). Nada
  de telefone, e-mail ou CPF (FR-055).

## R-13 — Meio de pagamento do afiliado

- **Decisão**: `afiliados_meios_pagamento` só com Pix (`pix_tipo_chave` do enum `TipoChavePix`
  existente e `pix_chave`), um registro por afiliado (`afiliados_id` único). A validação e a
  normalização da chave (`regra_chave_pix`, `normalizar_chave_pix`) saem do
  `ClientesMeiosPagamentoRequest` para um trait `Concerns/ChavePix`, usado pelos dois
  **(confirmado — extração em arquivo da spec 002, sem mudar o comportamento)**.
- **Alternativa**: duplicar as duas funções no request do afiliado (fica como plano B se a extração
  não for aprovada).

## R-14 — Exclusão, restauração e alcance

- **Decisão**: exclusão como `ClientesController::destroy`: sufixo `_deleted_<timestamp>` em
  `login`, `email`, `cpf` e `codigo`, `invalidar_tokens()` e soft delete. Restauração confere
  conflitos (login também contra `usuarios`) e aceita valor novo no corpo, como nos clientes.
- **Alcance**: Gerente só vê afiliados com `usuarios_id` igual ao dele; fora do alcance → 404
  "Afiliado não encontrado." (como `GarantirGerencia`). Admin e Supervisor veem todos
  (`AlcanceHierarquia::e_gestor_geral`). Aprovar e recusar exigem gestor geral além de
  `afiliados.alterar_situacao`.
- **Permissões**: constante `Funcao::PERMISSOES_AFILIADOS`; `pode_usar`: Vendedor nenhuma; Gerente
  todas menos `afiliados.excluir` e `afiliados.restaurar`; `afiliados.editar_comissao` usável pelo
  Gerente, mas fora do `permissoes_padrao` dele. Middleware de controller pelo
  `GarantirPermissaoCliente::permissao_cliente` (já valida permissão + função).

## R-15 — Listagem com totais do período

- **Decisão**: query builder com subconsultas (`withCount` e `withSum` com closures), sem SQL
  concatenado. Período (`de`/`ate`, `Y-m-d`, padrão: hoje) aplicado a: clientes cadastrados
  (`clientes.created_at`), depósitos (`pago_em`, situação Aprovado ou Estornado, soma de
  `valor − valor_estornado`), saques (`pago_em`, situação Pago), comissão (`afiliados_lancamentos.ocorrido_em`).
  Valor a receber (lançamentos sem prestação) e última prestação (`latestOfMany`) não dependem do
  período. Padrão 20 por página, máximo 100.
- **Índices**: `clientes(afiliados_id)`, `afiliados_lancamentos(afiliados_id, afiliados_prestacoes_contas_id)`,
  `afiliados_lancamentos(afiliados_id, ocorrido_em)`.

## R-16 — Páginas e componentes do frontend

- **Decisão**: duas páginas Inertia no `PublicLayout` (para ter tema e sessão):
  - `pages/AffiliateLanding` em `/cadastro-afiliado` (visual de `screens/new-affiliated`);
  - `pages/AffiliatePanel` em `/afiliados` (visual de `screens/affiliated`).
  Controllers de página `AfiliadosPaginasController` (`cadastro`, `painel`), sem dados: tudo vem
  da API com o token do afiliado, como na `/minha-conta`.
- **Componentes novos** (pasta própria, `index.jsx` + `styles.jsx`):
  `AffiliateSignupModal` (campos e visual do `modals/affiliated` antigo, com o comportamento do
  `AuthModal`), `AffiliateRecoveryModal` (pedir código e redefinir), `AffiliateProfileModal`
  (Meus dados e Pix, sobre o `ModalCard`), `AffiliatePasswordModal` (sobre o `ModalCard`),
  `AffiliateStatement` (tabela do extrato com filtro de período), `AffiliateClients`,
  `AffiliateSettlements`.
- **Hook novo**: `hooks/useAfiliado.js` (carrega painel, extrato, clientes e prestações com o token
  da sessão de afiliado; trata 401/403 encerrando a sessão).
- **Imagem**: copiar `public/images/afiliado.jpg` do antigo para `public/images/afiliado.jpg`.
- **Rodapé**: "Afiliados" vira link para `/cadastro-afiliado`.
- **Sem sessão**: `/afiliados` manda para `/?entrar=1` (o `AuthModal` leva de volta ao painel ao
  entrar como afiliado).

## R-17 — Seeders

- **Decisão**: `AfiliadosSeeder` (chamado pelo `DatabaseSeeder`, como `CassinoSeeder`): cria as 9
  permissões, entrega aos usuários existentes conforme a função (Gerente sem
  `afiliados.editar_comissao`) e cria 2 afiliados de exemplo (um Ativo com gerente e um Em análise)
  para a validação manual. Configuração padrão pela migration (padrão das colunas).

## R-18 — Postman e artefatos de outras specs

- Coleção `docs/postman/wssports_api.postman_collection.json` regenerada (rotas novas e alteradas).
- Notas "Alterado pela spec 012" em `specs/002-clientes/spec.md` (FR-009, código de afiliado) e
  `specs/007-painel-cliente-pagamentos/spec.md` (FR-018g, coluna `codigo_afiliado`).

## R-19 — Dados fake (Princípio IX)

Nenhum dado fake **novo**. Os dados do painel do afiliado vêm da API desde a primeira entrega.

Fake **existente** usado:

| Dado fake | Onde é usado nesta spec | Será substituído por |
|---|---|---|
| `DadosFake::tema()` (cor principal `temas` e `letter`, spec 005) | cores das páginas `/cadastro-afiliado` e `/afiliados` (pelos tokens do tema, como todas as páginas) e cor do botão dos e-mails | spec de configurações do tema (cor salva em `configuracoes`) |

Para a troca não mexer nos e-mails, o layout de e-mail lê a cor de um único método
(`App\Mail\Concerns\DadosEmail::dados_email()`, chave `cor_principal`), que hoje devolve
`DadosFake::tema()['temas']`.
Fora de qualquer operação com dinheiro.

## R-21 — E-mail de boas-vindas do cadastro de clientes (FR-013a)

- **Pedido do responsável** (aprovação dos Mailables, 2026-10-17): aproveitar e criar o e-mail do
  cadastro de clientes.
- **Sistema antigo**: `app/Mail/CadastroMail.php` + `resources/views/emails/cadastro.blade.php`,
  enviado em `CadastroController` (linhas 754–757 e 1014–1017) só com `confirmacao_cadastro = Sim`;
  o e-mail trazia o link "CONFIRMAR CADASTRO" (`?recovery=`) e a conta ficava inativa até o clique.
- **Decisão**: Mailable `ClienteCadastroRealizado` (assunto "[ CADASTRO ] Cadastro realizado com
  sucesso!") com view `resources/views/emails/clientes/cadastro_realizado.blade.php` no visual do
  antigo ("OLÁ, SEJA BEM VINDO :)", logo, texto de boas-vindas e botão "ACESSAR O SITE"). Enviado
  pelo listener novo `EnviarEmailCadastroCliente`, que ouve o evento existente `ClienteCadastrado`
  (já `ShouldDispatchAfterCommit`), só quando o cliente tem e-mail, com `Mail::to()->queue()`.
  O listener é descoberto automaticamente pelo Laravel: **nenhum arquivo existente muda**, nem o
  `CadastroClientes`, nem o `RegistrarMensagemWhatsapp`.
- **Diferença em relação ao antigo** (aprovada): sem link de confirmação e sem conta inativa até o
  clique, porque na spec 002 a conta nasce ativa e o cadastro já devolve o token.
- **Alternativa rejeitada**: enviar dentro do `CadastroClientes` (alteraria arquivo da spec 002 sem
  necessidade; o evento já existe para isso).

## R-20 — Dependências

Nenhum pacote novo no Composer nem no npm. E-mail pelo `Mail` do Laravel; fila `database` já
existente.
