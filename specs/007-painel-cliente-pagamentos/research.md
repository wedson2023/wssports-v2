# Research: Painel do cliente online com depósito e saque

**Feature**: `007-painel-cliente-pagamentos` | **Data**: 2026-10-12 | **Spec**: [spec.md](spec.md)

## R-01. Arquivos do sistema antigo consultados (Princípio VII)

Sistema antigo: `wssports.bet` (somente leitura).

| Assunto | Arquivos |
|---|---|
| Tela de créditos | `resources/js/screens/credits/index.js` e `styles.js` (saldos, filtros, tabela, menu, `cashout`, `convert_bonus`, `deletar_bonus`, `logout`, tela de conta inativa) |
| Depósito Pix | `resources/js/modals/credits/index.js` (valor, aceite de bônus, QR Code, copia e cola, consulta a cada 2 s) |
| Meus dados | `resources/js/modals/edit_register/index.js` (campos, máscara da chave Pix, retomada do saque) |
| Indique um amigo | `resources/js/modals/indication/index.js` (contagem, link `?user=LOGIN` pelo WhatsApp) |
| Backend da tela | `app/Http/Controllers/CadastroController.php` (`index`: saldos, metas, extrato com filtros e limite de 100; `update`: validações; `converter_bonus`; `deletar_bonus`) |
| Pagamentos | `app/Http/Controllers/PagamentosController.php` (`cobranca`, `mp`, `mp_token`, `webhook`, `webhook_mp`, `payment`, `consulta`, `saque`, `dar_baixa_saque`, `consultar`) |
| Vínculo do gateway | `resources/js/screens/adm/config/index.js` (campo "GATEWAY de pagamentos" e redirecionamento ao OAuth), `resources/js/screens/adm/modals/auth-gateway/index.js` (PrimePag) |
| Expiração do bônus | `app/Console/Commands/BonusCommand.php`; `app/Http/Controllers/BonusController.php` (`dias_cumprir_bonus`) |
| Tabelas | `creditos`, `creditos_transacaos`, `creditos_bonuses`, `gateways`, `travas_gerentes`, `travas_vendedor`, `validar_tokens` |

## R-02. Como funcionava no sistema antigo

- **Tela**: `GET cadastro` devolvia o cliente (saldos, saldos anteriores, `rollover` somado por tipo:
  depósitos, bônus esportes e bônus cassino), a promoção de indicação vigente, o telefone do
  vendedor e as 100 últimas transações com bilhete e cassino. Filtros: operação (Palpite,
  Depósito, Saque = Saque/Saque falhou/Saque pago, Bônus, Cashback) e situação do bilhete.
  Inativo (`ativo = Não`) via só "Pagamento pendente ou conta desativada." com Atualizar e Sair.
- **Depósito**: `POST cobranca-pix` validava valor ≥ 1, cliente ativo e CPF (PrimePag); criava a
  transação `Depósito` com `pago = Não`, rollover = valor quando `travas_vendedor.rollover_deposito
  = Sim`, gravava `aceita_bonus` no cliente e criava o pagamento no Mercado Pago
  (`POST /v1/payments`, `pix`, `application_fee` = comissão do gateway, `notification_url` para
  `webhook-pix`, `X-Idempotency-Key`). A tela consultava `consulta-pix?id=` a cada 2 s (sem
  conferir o dono).
- **Confirmação**: `webhook-pix` (sem validar assinatura) buscava o access token pelo
  `reference_code`, consultava `GET /v1/payments/{id}` e, se `approved` e ainda não pago: cashback
  (percentual do vendedor) somado ao saldo **e ao rollover do depósito**; marca pago; promoção de
  depósito vigente (preferindo a do afiliado do cliente, depois a geral; "Qualquer depósito" antes
  de "Primeiro depósito"), se o cliente aceitou bônus e o valor ≤ depósito máximo; bônus com
  rollover = vezes × bônus; com `rollover_primeiro_deposito = Sim`, o rollover do depósito virava
  vezes × depósito (descartando o cashback somado).
- **Saque**: `POST saque-pix` validava saldo, chave Pix, CPF, gênero, nascimento, telefone, ativo,
  saque mínimo, quantidade de saques por dia, rollover de depósito pendente (1x) e o limite fixo
  de R$ 3.000 para o cliente 1027; debitava na hora, criava `Saque` e um token de 24 h e mandava
  mensagens de WhatsApp ao dono com a chave e o link `dar-baixa-saque`. No PrimePag havia saque
  automático com `authorized = false` acima do `saque_maximo`.
- **Bônus**: converter passava `min(liberado, v_converter_bonus)` ao saldo e tirava todo o
  liberado do saldo de bônus; excluir zerava rollover e saldo do bônus e recalculava o saldo de
  bônus pelos demais bônus pendentes. `BonusCommand` retirava os bônus com
  `created_at + dias_cumprir_bonus < agora`.
- **Vínculo**: o admin escolhia "MERCADO PAGO" e ia para
  `auth.mercadopago.com.br/authorization` com o `client_id` fixo da aplicação do programador e
  `redirect_uri` central (`demo.wssports.bet/api/mercado-pago`), que trocava o `code` e repassava
  os tokens à instalação por `GET` com os tokens na URL.
- **Indicação**: link `?user=LOGIN`; a promoção de Indicação tinha `qtde_indicacoes`.

## R-03. Diferenças em relação ao antigo

Aprovadas na spec (Clarifications): rota `/minha-conta`; modais com visual novo (tela igual);
Pix e cartão parcelado (NuPay fora, R-09); uma conta do Mercado Pago por sistema; comissão em `configuracoes`
(padrão 3%); saque manual ou automático com limite; fim do limite fixo do cliente 1027; acesso
assistido com depósito e saque; indicação válida só com primeiro depósito e bônus recorrente;
estorno deixa o saldo negativo; expiração do bônus; cashback somado ao rollover inclusive com
rollover primeiro depósito.

Melhorias de código (Princípio VII), sem mudar o que o usuário vê: assinatura do webhook;
idempotência; fila; consulta da cobrança só pelo dono; nenhum token no código nem na URL;
dinheiro com `bcmath`/centavos; fim das mensagens de WhatsApp com a chave do cliente (fila no
painel no lugar).

Aprovadas pelo responsável em 2026-10-12: campos de Meus dados preenchidos uma vez (R-21) e
cálculo do valor liberado do bônus por carteira (R-22).

## R-04. API do Mercado Pago usada

- **Decisão**: API de pagamentos `POST /v1/payments` (Checkout Transparente/Checkout API) para
  Pix e cartão; `GET /v1/payments/{id}` para conferir; `POST /oauth/token` para vínculo e
  renovação.
- **Motivo**: é a API documentada com `application_fee` (marketplace) e a mesma que o antigo já
  usava em produção; um único cliente HTTP (`Http` do Laravel) atende tudo, sem SDK PHP.
- **Alternativas**: API de Orders (`/v1/orders`), recomendada pelo Mercado Pago para integrações
  novas, mas a comissão de marketplace e o parcelamento nela ainda exigiriam validação extra;
  SDK PHP oficial (pacote novo, sem ganho para quatro chamadas).

## R-05. Vínculo da conta (OAuth) e endereço de retorno

- **Fluxo**: `POST /api/configuracoes/gateway/mercado-pago/autorizar` (admin) gera um `state`
  aleatório (40 caracteres) guardado no cache por 10 min com o id do admin e devolve a URL
  `https://auth.mercadopago.com.br/authorization?client_id={MERCADO_PAGO_CLIENT_ID}&response_type=code&platform_id=mp&state={state}&redirect_uri={APP_URL}/mercado-pago/retorno`.
  O retorno (`GET /mercado-pago/retorno`, rota web) confere e consome o `state`, troca o `code`
  em `POST /oauth/token` (`grant_type=authorization_code`) e grava access token, refresh token,
  `user_id` e validade (180 dias) criptografados. Mostra a página `GatewayReturn` com sucesso ou
  motivo. Cancelamento (`error` na URL) mantém o vínculo anterior.
- **Endereço de retorno — decisão**: **por instalação** (`{APP_URL}/mercado-pago/retorno`),
  cadastrado pelo programador na aplicação dele uma vez por instalação (a aplicação aceita
  várias URLs de retorno).
- **Motivo**: os tokens nunca trafegam entre servidores nem na URL (o antigo repassava os tokens
  por `GET`); cada instalação valida o próprio `state`.
- **Alternativa**: endereço central que repassa o vínculo (antigo) — rejeitada pela exposição dos
  tokens e por exigir um servidor central sempre no ar.
- **Renovação**: comando `mercado_pago:renovar_token` diário; renova quando faltam menos de 30
  dias (`grant_type=refresh_token`); falha grava `falha_renovacao` e mantém o token até vencer.

## R-06. Credenciais e comissão

- **Ambiente** (`.env` de cada instalação, do programador): `MERCADO_PAGO_CLIENT_ID`,
  `MERCADO_PAGO_CLIENT_SECRET`, `MERCADO_PAGO_PUBLIC_KEY` (chave pública da aplicação, usada no
  navegador para capturar o cartão, como manda a documentação de marketplace),
  `MERCADO_PAGO_WEBHOOK_SECRET` (assinatura secreta das notificações da aplicação) e, para o
  saque automático, `MERCADO_PAGO_PAYOUTS_CHAVE_PRIVADA` (R-13). Lidas por `config/services.php`.
- **Comissão**: `application_fee = arredondar(valor × percentual / 100)` em centavos (meio para
  cima, `bcmath`), sobre o valor do depósito (sem juros do cartão), gravada no depósito.
  Percentual em `configuracoes.percentual_comissao_gateway` (padrão 3,00); fora de todo `fillable`,
  request e resource (FR-028).
- **Depósito mínimo efetivo**: o depósito é recusado se a comissão arredondada for ≥ o valor
  (edge case da spec).

## R-07. Pix

- `POST /v1/payments` com `transaction_amount`, `payment_method_id = pix`,
  `date_of_expiration = agora + minutos_expiracao_pix`, `payer.email`, `payer.identification`
  (CPF), `external_reference = depositos.codigo`, `notification_url = {APP_URL}/api/webhooks/mercado-pago`,
  `application_fee` e `X-Idempotency-Key = depositos.codigo` (UUID).
- A resposta traz `point_of_interaction.transaction_data.qr_code` (copia e cola) e
  `qr_code_base64` (imagem PNG): a tela mostra a imagem pronta — **nenhum pacote de QR Code**.
- A tela consulta `GET /api/area-cliente/depositos/{codigo}` a cada 3 s enquanto o modal está
  aberto.

## R-08. Cartão de crédito e parcelamento

- **Captura**: MercadoPago.js v2 (`https://sdk.mercadopago.com/js/v2`) carregado por script só ao
  abrir o cartão (`utils/mercado_pago.js`), com os campos seguros (`mp.fields`: número, validade e
  código) dentro do nosso modal, estilizados com o tema. O navegador gera o token do cartão; o
  número nunca chega ao servidor (FR-024). O SDK gera o identificador do aparelho usado na análise
  antifraude.
- **Parcelas**: `mp.getInstallments({ amount, bin })` no navegador, limitado a
  `parcelas_maximas`; o cliente vê a parcela e o total com juros (`recommended_message`).
- **Pagamento**: `POST /v1/payments` com `token`, `installments`, `payment_method_id`,
  `issuer_id`, `payer`, `transaction_amount` = valor do depósito, `application_fee`. Resposta
  síncrona: `approved` (processa na hora), `in_process` (aguarda webhook), `rejected` (mensagem
  pelo `status_detail`, tabela de mensagens em `MensagensMercadoPago`).
- **Dependência**: script externo do Mercado Pago, sem pacote npm; justificado porque a captura
  segura do cartão é obrigatória (PCI) e só existe pelo SDK do provedor.
- **Alternativa**: Card Payment Brick (formulário pronto) — rejeitado por impor o visual dele no
  modal.

## R-09. NuPay — fora desta spec (decisão de 2026-10-12)

- O NuPay é um meio de pagamento do Nubank, não do Mercado Pago: o cliente escolhe NuPay, é
  redirecionado ao aplicativo do Nubank, paga e volta (fluxo usado no iFood). Só serve a quem tem
  conta no Nubank e exige integração direta com o NuPay for Business (contrato, credenciais e
  notificações próprios); a comissão da conta master, que é do Mercado Pago (`application_fee`),
  não se aplica a ele.
- O pagamento com redirecionamento para **qualquer** banco é o Pix por Open Finance (iniciação de
  pagamento); a documentação do Checkout API do Mercado Pago não traz esse meio (pesquisa de
  2026-10-12).
- **Decisão**: a 007 fica com Pix e cartão; NuPay e Pix por Open Finance vão para uma spec
  própria, que avaliará o provedor, a comissão e o fluxo de retorno.

## R-10. Webhook, fila e conferência

- **Rota**: `POST /api/webhooks/mercado-pago` (pública, sem login, `throttle:120,1`).
- **Assinatura**: cabeçalhos `x-signature` (`ts=...,v1=...`) e `x-request-id`; manifesto
  `id:{data.id};request-id:{x-request-id};ts:{ts};` com HMAC-SHA256 e
  `MERCADO_PAGO_WEBHOOK_SECRET`; comparação com `hash_equals`. Inválida → grava em
  `notificacoes_mercado_pago` com `assinatura_valida = false` e responde 200 sem processar.
- **Processamento**: grava a notificação e despacha o job `ProcessarPagamentoMercadoPago`
  (fila `database`, já configurada) e responde 200 em seguida. O job consulta
  `GET /v1/payments/{id}` com o token da conta e chama `ProcessamentoDepositos::atualizar`.
- **Idempotência**: bloqueio da linha do depósito (`lockForUpdate`) e transições só a partir de
  estados permitidos (data-model); a mesma notificação repetida não muda nada.
- **Conferência ativa**: comando `depositos:conferir` a cada minuto consulta os depósitos
  pendentes (até 2 h) e expira os Pix vencidos — cobre webhook perdido e ambiente local sem
  endereço público.
- **Estornos**: status `refunded`/`charged_back` (e o tópico `chargebacks`) → estorno do depósito
  (R-12).

## R-11. Aprovação do depósito (uma transação)

Ordem em `ProcessamentoDepositos::aprovar` (depósito bloqueado):

1. Crédito do valor no `Saldo` (`OrigemTransacao::Depósito`, referência = depósito), que cobre
   primeiro um saldo negativo (é só uma soma).
2. Cashback (`configuracoes.cashback_percentual` > 0): crédito `OrigemTransacao::Cashback`.
3. Promoção de depósito (`EscolhaPromocoes::para_deposito`): só se o cliente aceitou bônus no
   depósito; candidata do afiliado do cliente e, sem ela, a sem afiliado; "Qualquer depósito"
   antes de "Primeiro depósito" (como no antigo); "Primeiro depósito" só se este for o primeiro
   depósito aprovado do cliente; valor ≤ `valor_maximo_deposito`. Bônus fixo ou percentual
   creditado na carteira da modalidade com rollover e expiração (`RolloverClientes::criar_bonus`).
4. Rollover do depósito (`RolloverClientes::criar_deposito`): exigido = valor × vezes
   (`rollover_deposito_vezes`, ou as vezes da promoção quando ela tem rollover primeiro depósito)
   + cashback; sem rollover de depósito e sem cashback, não cria.
5. Indicação (`Indicacoes::validar`): se for o primeiro depósito aprovado e o cliente tiver
   indicador.

## R-12. Saldo negativo e estorno

- `SaldoClientes::debitar` ganha o parâmetro `permitir_negativo = false`; só o estorno de
  depósito e o cancelamento de bônus de indicação o usam.
- Aposta e saque continuam recusados com saldo negativo sem mudança: o débito já exige saldo
  ≥ valor.
- Estorno (`ProcessamentoDepositos::estornar`): débito do valor estornado
  (`OrigemTransacao::EstornoDeposito`), cancela o rollover do depósito e o bônus da promoção ligado
  (débito do saldo de bônus que restar desse bônus, com negativo permitido, e rollover cancelado),
  e desfaz a indicação (R-17). Estorno parcial debita só o valor estornado e mantém bônus e
  indicação.

## R-13. Saque

- **Solicitação** (`SolicitacaoSaques::solicitar`): validações na ordem do FR-035; dia de
  `America/Sao_Paulo`; quantidade e soma diárias contam saques não recusados;
  rollover pendente = soma de `valor_exigido − valor_apostado` dos rollovers de Depósito pendentes.
  Débito pelo `SaldoClientes` (o bloqueio da linha impede dois saques consumirem o mesmo saldo)
  e saque `Pendente`.
- **Modo manual**: fila no painel; "Pagar" marca `Pago` (baixa manual), "Recusar" marca
  `Recusado` com motivo e devolve (`OrigemTransacao::DevolucaoSaque`).
- **Modo automático**: até `saque_automatico_maximo`, job `PagarSaqueMercadoPago` chama a API de
  Payouts `POST /v1/transaction-intents/process` (Pix para a chave principal, `X-Idempotency-Key`
  = código do saque, `X-Signature` com `MERCADO_PAGO_PAYOUTS_CHAVE_PRIVADA` em produção,
  `notification_url` do webhook). Confirmação pelo webhook ou consulta → `Pago`; falha → volta
  à fila manual com `motivo_falha` (sem devolver). Acima do limite, fila manual; ao aprovar no
  painel, o painel escolhe "Pagar pelo Mercado Pago" ou "Dar baixa manual".
- **Risco**: a API de Payouts precisa ser liberada pelo Mercado Pago na conta da banca; sem a
  liberação, o modo automático falha e tudo cai na fila manual (spec, Assumptions).

## R-14. Expiração e gestão do bônus

- `clientes_promocoes.dias_cumprir` (padrão 30) e `clientes_rollovers.expira_em`/`expirado_em`.
- `RolloverClientes::criar_bonus` grava `expira_em = agora + dias_cumprir` (vale também para os
  bônus de cadastro da spec 002 e de indicação).
- Comando `bonus:expirar` a cada 10 min: rollovers de Bônus pendentes com `expira_em < agora` →
  `expirado_em = agora` e débito do saldo restante do bônus (R-22) com
  `OrigemTransacao::BonusExpirado`.
- Migration preenche `expira_em = created_at + 30 dias` nos bônus pendentes existentes.

## R-15. Afiliado e rollover primeiro depósito na promoção

- `clientes_promocoes.codigo_afiliado` (texto, sem FK — a tabela de afiliados não existe;
  constituição: será ajustado na spec de afiliados), `rollover_primeiro_deposito` (boolean),
  `quantidade_indicacoes` (só Indicação, padrão 5).
- Escolha por afiliado no depósito (R-11) e também no cadastro (`CadastroClientes`): uma promoção
  com afiliado nunca é aplicada a cliente de outro afiliado ou sem afiliado.

## R-16. Acesso assistido

- **Início** (painel): `POST /api/clientes/{cliente}/acesso-assistido` (permissão
  `clientes.acessar_conta`, só Admin e Supervisor) cria `clientes_acessos_assistidos` com um código
  de uso único (40 caracteres, guardado com hash, válido por 2 min) e devolve
  `url = {APP_URL}/minha-conta?acesso={codigo}`.
- **Troca** (site): `POST /api/area-cliente/acesso-assistido` com o código → token JWT do guard
  `clientes` com as claims `acesso_assistido_id` e `assistido_por`, TTL =
  `minutos_acesso_assistido`. O código é consumido.
- **Validação**: `GarantirAcessoCliente` passa a recusar token assistido de acesso encerrado ou
  expirado. O autor das operações vem de `AcessoAssistido::autor($request)` e vai em
  `usuarios_id` das transações, depósitos e saques.
- **Encerrar**: `POST /api/area-cliente/acesso-assistido/encerrar`.
- **Navegador**: a sessão assistida fica em `sessionStorage` (`wssports.acesso_assistido`), sem
  tocar na sessão do cliente salva no aparelho; fechar a aba encerra.
- **Alternativa**: logar o painel como o cliente (antigo `?id=`) — rejeitada: misturava as
  sessões e não registrava o autor.

## R-17. Indicações

- `clientes.codigo_indicacao` (8 caracteres, único, gerado no cadastro e preenchido pela
  migration nos existentes) e `clientes.indicado_por` (FK `clientes`, nulo).
- Link `{APP_URL}/?indicacao={codigo}`; o cadastro (AuthModal) envia `codigo_indicacao`. O
  `?user=` continua preenchendo o código de afiliado (spec 002).
- `clientes_indicacoes` (um por indicado) e `clientes_indicacoes_bonus` (um por grupo pago).
  Ao validar: indicações válidas sem bônus do indicador ≥ `quantidade_indicacoes` da promoção de
  Indicação vigente → bônus com as N mais antigas; repete enquanto houver grupo completo.
- Estorno do primeiro depósito do indicado: indicação volta a pendente; se tinha bônus, o bônus é
  cancelado (débito com negativo permitido, rollover cancelado) e as outras indicações do grupo
  voltam a ficar sem bônus.

## R-18. Configurações de pagamento

Colunas novas em `configuracoes` (data-model): gateway, comissão, depósito mínimo/máximo, saque
mínimo, rollover de depósito e vezes, cashback, modo de saque, saque automático máximo, meios de
depósito, parcelas máximas, minutos do Pix, minutos do acesso assistido, depósitos e saques
habilitados. Edição: `GET/PUT /api/configuracoes/pagamentos` (`configuracoes.editar`), sem a
comissão.

## R-19. Frontend

- Página Inertia `Account` em `/minha-conta` (rota web), dentro do `PublicLayout` (tema, sessão).
  Os dados vêm da API com o token do cliente (ou o assistido), como a Home faz com o saldo.
- Sem sessão de cliente: redireciona para `/?entrar=1&voltar=/minha-conta`; a Home abre o
  `AuthModal` e, depois do login, volta.
- **Visual da tela igual ao antigo** (`screens/credits/styles.js`): `AccountHeader`,
  `BalanceCards`, `StatementFilters`, `StatementTable`, `AccountMenu`, `InactiveAccount`, com as
  cores pelos tokens do tema.
- **Modais com visual novo** sobre um componente base `ModalCard` (cartão, cabeçalho escuro com
  faixa do tema, folha no mobile): `DepositModal`, `PixCharge`, `CardPayment`, `WithdrawModal`,
  `ProfileModal`, `ReferralModal`, `ReferralsModal`; `AssistedBanner` para a tarja.
- Hooks `useMinhaConta` (dados e extrato paginado) e utilitário `utils/mercado_pago.js`.
- O "Olá, nome" do `Header` vira link para `/minha-conta` com sessão de cliente (como no antigo).

## R-20. Extrato e operações

`OrigemTransacao` ganha: `Depósito`, `Cashback`, `Saque`, `Devolução de saque`,
`Estorno de depósito`, `Conversão de bônus`, `Bônus expirado`, `Bônus excluído`. O extrato do
painel (`GET /api/area-cliente/minha-conta/extrato`) lista `clientes_transacoes` do cliente com:
`operacao` (rótulo da tela a partir da origem), aposta ligada (código, prêmio, resultado) para
`Aposta`/`Prêmio`, rollover ligado para bônus e depósitos, saque ligado (situação e motivo). Filtro
`operacao` mapeia para origens; `situacao` para `apostas.resultado`.

## R-21. Meus dados: campos preenchidos uma vez — **aprovado em 2026-10-12**

A spec 002 travou CPF, telefone e data de nascimento para o cliente ("Este dado só pode ser
alterado pelo atendimento"), e o e-mail é opcional. A spec 007 exige CPF e e-mail para depositar e
todos os campos para sacar. **Decisão** (lógica do antigo, em que o e-mail ficava desabilitado
para o cliente e só o supervisor alterava):

- **Uma vez pelo cliente, depois só pelo painel** (admin/supervisor, rotas de clientes da spec
  002): CPF, e-mail e data de nascimento. Vazios, o cliente informa em Meus dados; preenchidos,
  aparecem só para leitura com o aviso "Para alterar, fale com o atendimento."
- **Só pelo painel**: telefone (é o login).
- **Livres para o cliente**: nome, gênero, senha e chave Pix de saque (tipo, chave e titular,
  pelos meios de pagamento existentes).

Ajusta o `UpdateMeusDadosRequest` (spec 002: `cpf`, `email` e `data_nascimento` aceitos só quando
vazios no cliente) e o FR-041 da spec 007.

## R-22. Valor liberado do bônus — **aprovado em 2026-10-12** (sugerido e aplicado)

O sistema novo guarda o saldo de bônus por carteira, não por bônus (o antigo guardava o saldo de
cada bônus na transação), e o pagamento de prêmios ainda não existe (spec de apuração). **Decisão**:

- **Valor liberado**: o bônus cumpriu o rollover e não há outro bônus pendente na mesma
  modalidade → liberado = saldo da carteira de bônus da modalidade; senão, 0 (a tela informa
  "Cumpra o rollover dos outros bônus desta modalidade").
- **Converter**: passa `min(liberado, valor_maximo_conversao)` ao saldo e zera a carteira (como o
  antigo, que tirava todo o liberado).
- **Excluir e expirar**: tiram `min(saldo da carteira, valor creditado do bônus)`.
- O controle por bônus fica para a spec de apuração, se necessário.
