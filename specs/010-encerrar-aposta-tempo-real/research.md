# Research: Encerrar aposta, bilhete em tempo real e notificações push

**Feature**: `010-encerrar-aposta-tempo-real` | **Data**: 2026-10-15 | **Spec**: [spec.md](spec.md)

Decisões técnicas da Phase 0. Cada item tem **Decisão**, **Motivo** e **Alternativas
consideradas**. As diferenças de comportamento em relação ao sistema antigo já foram aprovadas e
estão nas Clarifications da spec; aqui só há decisões de implementação.

## Sistema antigo (Princípio VII)

Consultado em `C:\Users\wedso\OneDrive\Área de Trabalho\projetos\wssports.bet` (só leitura).

| Arquivo | O que faz |
|---|---|
| `resources/js/screens/ticket/index.js` e `styles.js` | Página `/bilhete/:code`. Busca `futebol-ao-vivo/{codigo}` ao abrir e **a cada 10 segundos** (`setInterval`), mesmo com a aposta decidida. Mostra logo, código, cliente (15 primeiros caracteres), vendedor, horário, valor, prêmio, situação e, por palpite, campeonato, horário, escudos, times, placar, gols do 1º e do 2º tempo, escanteios, período e minuto. O sino abre a inscrição de notificações (`subscribe`) e alterna o tipo (Todas/Avisos). |
| `resources/js/modals/ticket/index.js` | Modal "Conferir Bilhete" com o ícone "acompanhar em tempo real", que abre `/bilhete/:codigo`. |
| `app/Http/Controllers/AoVivoController.php` (`show`) | Monta o bilhete: aposta com vendedor e travas; palpites com `COALESCE(ao_vivo, confronto)` para placar, tempos, escanteios e situação; especiais à parte. Aposta não encontrada ou do Acumuladão responde 401 com `reload`. |
| `resources/js/utils/helpers.js` | Inscrição Web Push com chave VAPID fixa no código, enviada a um **servidor externo** (`messages.wssports.bet/subscribe`, `unsubscribe`, `get-subscription`, `change`), mais `POST inscritos` para marcar o cliente como inscrito. |
| `app/Http/Controllers/InscritosController.php` | Guarda em `creditos` só `inscrito` (Sim/Não) e `tipo_notificacao` (Todas, Gol, Avisos, Nenhuma), sem dado do aparelho. |
| `app/Http/Controllers/NotificacaoPushController.php` | Campanhas manuais (título, mensagem, link) com cota mensal (`qtde_notificacao`). Fica fora desta spec. |
| `app/Console/Commands/RenovarPushNotificationCommand.php` | Renova a cota mensal de envios. Sai (a cota deixa de existir). |
| `app/Http/Controllers/HomeController.php` (`notificacao_gols`) | O servidor externo envia os `fonte_id` dos jogos com gol; a rota devolve os clientes com palpite neles e o link `/bilhete/{codigo}`. Não confere a situação da aposta e só sai com cota. |
| `public/sw.js` | `push` mostra a notificação com `tag: fonte_id` (um aviso por jogo, substituído) e vibração; `notificationclick` foca a aba do link ou abre uma nova. |
| `resources/js/screens/credits/index.js` | "Cashout" do antigo é o **saque do saldo**. Não havia encerramento de aposta. |

**Resumo**: o antigo tinha bilhete por consulta a cada 10 s e push de gol por servidor externo com
cota. **O encerramento de aposta não existia no sistema antigo**: toda a US2 é nova, aprovada pelo
responsável (Clarifications).

## R-01 — Conexão contínua do bilhete

**Decisão**: Laravel Reverb (`laravel/reverb`), o servidor WebSocket oficial do Laravel, com o
broadcasting do framework. No navegador, `laravel-echo` + `pusher-js` (protocolo do Reverb). Só
**canais públicos**:

- `confrontos.{confrontos_id}`: evento `ConfrontoAtualizado` com placar, período, minuto, gols por
  tempo, escanteios e o indicador `cotacoes_alteradas`;
- `apostas.{codigo}`: evento `ApostaAtualizada` (situação, resultado, encerramento) quando a
  apuração, o encerramento ou o automático mudam a aposta.

Os eventos usam `ShouldBroadcastNow`, enviados pelo próprio processo que detectou a mudança (carga
do ao vivo, apuração, encerramento), sem passar pela fila. Uma falha do Reverb vai para o log e
**nunca** interrompe a carga.

**Motivo**: a decisão "conexão contínua" já foi tomada na spec. O Reverb não tem custo, roda no
mesmo servidor e usa a API de broadcasting que o Laravel já traz. Com canal por jogo, a carga do ao
vivo envia uma mensagem por jogo alterado, e não uma por aposta (milhares de apostas no mesmo
jogo). O código da aposta já é o segredo da página pública, então o canal `apostas.{codigo}` não
expõe nada além do que a página mostra. Dados que dependem do cliente (cotação ajustada, oferta)
**não** vão no canal (R-03).

**Alternativas consideradas**:

- Pusher ou Ably: serviço pago de terceiros (premissa da spec: "sem serviço de terceiros pago").
- Server-Sent Events em PHP: cada página aberta prende um processo PHP; não escala no PHP-FPM.
- Soketi: servidor Node sem manutenção ativa; mais um runtime no servidor.
- Canal privado por cliente com a oferta calculada no servidor a cada carga: exigiria calcular a
  oferta de todas as apostas abertas a cada 5 segundos, mesmo sem ninguém olhando.

**Requisito de implantação**: o processo `php artisan reverb:start` precisa ficar no ar (supervisor),
com o proxy encaminhando o `wss`. Fica registrado no [quickstart.md](quickstart.md).

## R-02 — Atualização do bilhete na tela e plano B

**Decisão**: a página carrega `GET /api/publico/bilhetes/{codigo}` e assina os canais dos jogos em
aberto e o da aposta.

- `ConfrontoAtualizado`: placar, minuto e período mudam na hora, direto do evento. Em seguida, a
  página busca o bilhete de novo para os selos, as cotações atuais e a oferta. A busca é agrupada
  (no máximo uma a cada 2 segundos) e cancela a anterior.
- `ApostaAtualizada`: busca o bilhete de novo na hora.
- Com a conexão de pé, há também uma verificação de segurança a cada 60 segundos (cobre
  cancelamento ou edição pelo painel, que não emitem evento).
- **Conexão caída** (`state_change` do pusher-js para `unavailable`/`failed`/`disconnected`):
  indicador "Reconectando…" e consulta periódica a cada **10 s** com jogo ao vivo e **60 s** sem
  jogo ao vivo (FR-009). Quando o estado volta a `connected`, a página busca uma vez e volta ao modo
  contínuo.
- **Aposta no estado final** (decidida, Encerrada ou Cancelada): sai dos canais e para tudo
  (FR-010).
- O "atualizado há X s" usa o horário da última resposta ou evento recebido.

**Motivo**: os selos "ganhando/perdendo agora" e a oferta dependem do palpite e do cliente, então
saem do servidor (R-05, R-06). O evento traz o que muda na hora (placar), e a nova busca traz o
resto. Isso cumpre os 5 s do FR-008 sem calcular nada para quem não está olhando.

**Alternativas consideradas**: mandar o bilhete inteiro no evento (dados por cliente e muitas
mensagens); só consulta periódica (rejeitado na spec).

## R-03 — Identificação do dono na página pública

**Decisão**: a rota pública do bilhete aceita o token do cliente (opcional), como a listagem
pública. O dono é identificado por `IdentificacaoPublico` (cliente ativo, token posterior à data
de corte) e pelo `clientes_id` da aposta. Só para o dono a resposta traz o nome completo, o bloco
`encerramento` e o encerramento automático. As ações (encerrar, automático) ficam na área do
cliente, com `auth:clientes` + `GarantirAcessoCliente`, que validam também o acesso assistido.

No frontend, `aplicar_token` (hook `useSessao`) passa a usar o token do acesso assistido também nas
páginas `/bilhete/…`, e não só em `/minha-conta`. Assim, o usuário do painel que abre o bilhete
pelo extrato do cliente pode encerrar (FR-016).

**Motivo**: mesmo padrão das specs 005 a 007 (token JWT do aparelho, sem sessão de servidor). A
spec diz que a página identifica o dono "pela sessão do cliente já usada no site".

**Alternativas consideradas**: cookie de sessão próprio da página (segundo mecanismo de login);
página separada para o dono (duas telas para a mesma coisa).

## R-04 — Fórmula da oferta (FR-019 a FR-022)

**Decisão**: tudo em `bcmath`, sem `float`:

```text
P  = premio da aposta (sem valor_acrescido; já limitado por prêmio máximo e multiplicador)
     × fracao_restante (R-07)
C  = produto das cotações atuais dos palpites em aberto (Vencedor/Devolvido/Cancelado = 1,00)
m  = margem_encerramento_pre_jogo se nenhum jogo em aberto começou; senão margem_encerramento_ao_vivo
O  = truncar_centavos( P ÷ C × (100 − m) ÷ 100 ), limitada a P
mínimo = maior entre valor_minimo_encerramento e
         valor × fracao_restante × percentual_minimo_encerramento ÷ 100
O < mínimo → sem oferta
```

As cotações atuais são calculadas pelo `CriacaoApostas::montar` já existente, com o `Apostador` do
cliente. Assim valem as mesmas porcentagens, tetos e cotação máxima do ao vivo da criação da
aposta (FR-020), sem duplicar a regra. Para cada palpite em aberto, o pedido montado é:

| Situação do jogo | Pedido para `montar` |
|---|---|
| Não começou (`data_inicio` > agora, situação `Aguardando`) | `confrontos_id` + código (pré-jogo) |
| Começou e tem linha no ao vivo | `confrontos_ao_vivo_id` da linha atual + código (ao vivo) |
| Começou sem cobertura do ao vivo | — (inelegível, premissa da spec) |
| Palpite de jogador | pré-jogo antes do início; depois do início, inelegível (FR-018) |
| Especial em aberto | — (inelegível, FR-018) |

**Motivo**: é a fórmula que as casas usam (valor justo pelas cotações atuais, menos a margem). Usar
o `montar` garante que a cotação do encerramento é a mesma que o cliente veria se fosse apostar
agora.

**Alternativas consideradas**: cotação do provedor sem ajuste (daria ao cliente uma oferta
diferente da tela); margem sobre o lucro (padrão menos comum e mais difícil de explicar nas Regras).

## R-05 — Critérios de elegibilidade e motivos (FR-017, FR-023)

**Decisão**: `ElegibilidadeEncerramento` devolve `elegivel` + `motivo` (o primeiro que falhar), na
ordem abaixo. A página mostra o motivo no lugar da oferta.

| # | Critério | Motivo exibido |
|---|---|---|
| 1 | Encerramento ligado (geral) | "Encerramento de apostas indisponível no momento." |
| 2 | Aposta de cliente (`clientes_id`), paga com `Saldo` | "Esta aposta não pode ser encerrada." |
| 3 | Sem bloco Criar Aposta | "Apostas com Criar Aposta não podem ser encerradas." |
| 4 | Cliente ativo e `encerrar_aposta` ligado na configuração dele | "Encerramento indisponível para a sua conta." |
| 5 | `Ativa` e resultado `Aguardando` | — (estado final, sem bloco) |
| 6 | Nenhum palpite `Perdedor` | "Um palpite da aposta já está perdido." |
| 7 | Jogos não iniciados permitidos, ou algum jogo já começou | "O encerramento fica disponível quando o primeiro jogo começar." |
| 8 | Nenhum jogo em aberto com resultado `Encerrado` gravado | "Aguardando a apuração de {jogo}." |
| 9 | Nenhum jogo do ao vivo em aberto travado (trava geral ou sem atualização) | "Jogo travado em {jogo}." |
| 10 | Todo palpite em aberto com cotação atual > 0 | "Mercado suspenso em {jogo}." |
| 11 | Oferta ≥ mínimo | "Oferta abaixo do mínimo para encerrar." |

O critério 8 não está escrito no FR-017, mas decorre dele: um jogo já encerrado não tem mais
cotação válida. Ele também fecha a corrida entre a carga de resultados e um pedido de encerramento
(R-11).

## R-06 — Selos "Ganhando agora", "Perdendo agora" e "Aguardando" (FR-005)

**Decisão**: novo construtor `PlacarConfronto::do_ao_vivo(object $jogo)`, com placar, gols por
tempo e escanteios da linha do ao vivo, sem autores de gol. O selo é
`ResultadosCotacao::avaliar(codigo, placar)` (as mesmas regras da apuração): `Vencedor` → "Ganhando
agora", `Perdedor` → "Perdendo agora", `Aguardando` (dado ausente) → "Aguardando". Palpite de
jogador no ao vivo é sempre "Aguardando" (o ao vivo não traz o autor do gol). Os gols do 2º tempo
que ainda não vieram contam como 0 enquanto o período for o 1º tempo.

**Motivo**: o FR-005 exige as mesmas regras da apuração. O `PlacarConfronto` já é o formato que as
regras aceitam, e só ganha um segundo construtor.

**Alternativa considerada**: reescrever as regras em JavaScript (duplicaria os 323 códigos e poderia
divergir da apuração).

## R-07 — Como a aposta guarda a parte que continua valendo (encerramento parcial)

**Decisão**: nova coluna `apostas.fracao_restante` (decimal 11,10, padrão 1). É a fração da aposta
que continua valendo. O encerramento parcial que recebe `r` de uma oferta `O` faz
`fracao_restante ← fracao_restante × (1 − r ÷ O)`. `Apostas::total_a_pagar()` passa a devolver
`truncar_centavos((premio + valor_acrescido) × fracao_restante)`. A API devolve o valor apostado e o
prêmio que continuam valendo, já calculados.

**Motivo**:

- `valor`, `premio` e `valor_acrescido` não mudam. Por isso não mudam a assinatura da aposta
  (`AssinaturaApostas` assina esses campos), o limite por jogo nem o valor diário (premissas da
  spec: exposição e comissão não mudam).
- O recálculo do prêmio quando um palpite é cancelado (`EdicaoApostas`, spec 009) continua
  calculando o prêmio cheio. A fração aplica a redução por cima, então o FR-034 (pagar só a parte
  restante) e o caso "jogo cancelado depois do parcial" ficam corretos sem mexer nesse recálculo.
- A liquidação (`LiquidacaoApostas`) já paga `total_a_pagar()`, e por isso não muda.

**Alternativa considerada**: colunas `valor_restante`, `premio_restante` e
`valor_acrescido_restante`. Rejeitada porque cada recálculo de prêmio (cancelamento, restauração e
empate anula da spec 009) teria de atualizá-las também, com risco de ficarem defasadas.

## R-08 — Resultado `Encerrada` e convivência com a apuração (FR-027, FR-034)

**Decisão**:

- `ResultadoAposta` ganha `Encerrada`. A situação continua `Ativa`. O encerramento total grava
  `resultado = Encerrada`.
- `ApuracaoApostas::decidir` **mantém** `Encerrada`: os palpites continuam sendo apurados para o
  histórico, mas o resultado da aposta não é recalculado (salvo o desfazimento, R-11).
  `LiquidacaoApostas::devido` já devolve 0 para tudo que não é `Vencedor`, então a aposta encerrada
  não recebe prêmio.
- Numa aposta com encerramento parcial, o resultado segue a regra normal e o prêmio pago é
  `total_a_pagar()` (já reduzido, R-07).

**Motivo**: a apuração da spec 009 continua a única que decide palpites. A aposta encerrada fica
fora do pagamento sem precisar de um ramo novo na liquidação.

## R-09 — Confirmação, validade da oferta e tempo de confirmação do ao vivo (FR-024 a FR-026)

**Decisão**:

1. A oferta vem com um `token_oferta`: HMAC-SHA256 (chave do `APP_KEY`) de
   `{apostas_id, clientes_id, oferta, fracao_restante, margem, palpites: [{id, cotacao_atual, placar_casa, placar_fora, periodo}], expira_em}`,
   com `expira_em` = agora + `segundos_validade_oferta`. A página faz a contagem regressiva pelo
   `expira_em` e desfaz a confirmação quando ele vence.
2. `POST …/encerramentos` confere a assinatura e a validade. Token vencido ou adulterado →
   **409** com a oferta atual.
3. **Sem jogo ao vivo em aberto**: decide na hora (R-10).
4. **Com jogo ao vivo em aberto**: grava o encerramento `Em análise` e despacha
   `DecidirEncerramento` na fila `apostas` com atraso de `Apostas::delay_ao_vivo()` (o mesmo tempo
   de confirmação das apostas do ao vivo). A página acompanha pelo evento `ApostaAtualizada` ou
   consultando `GET …/encerramentos/{chave}`. Um encerramento `Em análise` preso é recusado pelo
   mesmo comando que recusa as análises presas das apostas (padrão da spec 004).
5. **Decisão**: com a aposta travada (`lockForUpdate`), refaz a elegibilidade e a oferta. Placar ou
   período diferente do token → `Recusado` ("Houve lance em {jogo} durante a confirmação."). A
   comparação da oferta nova `O'` com a aceita `O` segue a preferência de aceitar alterações
   (enviada no pedido; sem ela, a gravada na aposta):
   - `Nenhuma`: só aceita `O' = O`;
   - `Somente para maior`: aceita `O' ≥ O`;
   - `Qualquer`: aceita qualquer `O'` acima do mínimo.

   Aceito, o valor pago é `O'` (no total) ou o `r` pedido (no parcial, desde que `r < O'`), com a
   proporção recalculada sobre `O'`.
6. **Idempotência (FR-026)**: `chave_idempotencia` (UUID gerado pela página ao abrir a confirmação)
   é única em `apostas_encerramentos`. O reenvio devolve o mesmo registro. A trava da aposta
   impede dois encerramentos simultâneos.

**Motivo**: é o mesmo mecanismo das apostas do ao vivo (delay + job + análise presa recusada) e da
preferência de cotações (spec 004). O token impede que o cliente altere a oferta e prova o que foi
exibido (SC-005). A preferência é aplicada ao **valor da oferta**, porque é o que o cliente aceita
no encerramento. Na aposta, ela é aplicada a cada cotação.

**Alternativa considerada**: segurar a requisição HTTP durante o delay (`sleep`) prenderia um
processo PHP por 15 s.

## R-10 — Efeitos do encerramento aceito

**Decisão** (uma transação, com a aposta travada), no serviço `EncerramentoApostas`:

| Modalidade | Aposta | Rollover (FR-038) | Automático |
|---|---|---|---|
| Total / Automático | `resultado = Encerrada`; `fracao_restante` = 0 | retira todo o progresso que a aposta ainda soma | cancelado (se for manual) ou executado |
| Parcial | `fracao_restante × (1 − r ÷ O')` | retira o progresso × proporção encerrada | cancelado (FR-031) |

- O registro `apostas_encerramentos` fica `Registrado`, com `creditar_em` = decisão +
  `minutos_pagamento_premio` (a janela da spec 009).
- O comando `apostas:liquidar_encerramentos` (a cada minuto) credita os vencidos pelo
  `SaldoClientes`, carteira `Saldo`, origem nova `Encerramento de aposta`, e muda para `Creditado`.
  SC-007: janela + 1 min.
- `RolloverClientes` ganha `retirar(Apostas, proporcao)`, que devolve o que retirou de cada
  rollover (gravado no encerramento), e `devolver(lista)` para o desfazimento. O total usa
  `retirar(…, 1)`, que equivale ao `desfazer` do cancelamento, mas guarda os valores para poderem
  voltar.
- Depois da transação: `ApostaAtualizada` e, no automático, a notificação (FR-044).

## R-11 — Encerramento desfeito (FR-033)

**Decisão**: na apuração, quando um palpite que estava em aberto num encerramento `Registrado` ou
`Creditado` é decidido `Perdedor`, o encerramento é desfeito se o jogo dele já estava decidido no
momento do encerramento. Isso vale quando:

- existe em `operacoes_apuracao` um registro `Resultado do jogo` com `depois.situacao = Encerrado`
  criado **antes** do encerramento; ou
- a fotografia do encerramento mostra o jogo do ao vivo **sem atualização** além do limite da trava
  naquele momento.

O desfazimento:

- `Registrado` → `Desfeito`, sem crédito;
- `Creditado` → `Desfeito` + débito com origem `Estorno` (pode negativar, como o estorno de prêmio
  da spec 009);
- devolve o rollover retirado (`RolloverClientes::devolver`);
- devolve a fração ao que era antes (`fracao_antes`);
- no total, o resultado volta a ser decidido pelos palpites.

Fica registrado no próprio encerramento (`desfeito_em`, `motivo_desfeito`) e numa operação da
apuração. Outras correções de resultado não mexem no valor encerrado.

**Motivo**: com a elegibilidade travada pela aposta (critérios 8 e 9 da R-05), um encerramento só
acontece com dados recentes do jogo. O desfazimento fica como rede de segurança para a única
corrida possível (resultado gravado e aposta ainda não apurada) e para dados do ao vivo atrasados.
Os dois casos são verificáveis pelo que o sistema já grava, sem precisar do horário real do lance,
que o provedor não envia.

## R-12 — Encerramento automático (FR-030, FR-031)

**Decisão**: tabela `apostas_encerramentos_automaticos`, com no máximo um `Ativo` por aposta
(garantido no serviço, com a aposta travada). O valor-alvo precisa ser maior que a oferta atual. Os
atalhos (+20%, +50%, +100%, "recuperar o valor apostado" = `valor × fracao_restante`) são
calculados na página. A conferência é feita:

- pelo job `ConferirEncerramentosAutomaticos`, despachado depois de cada carga do ao vivo com os
  jogos alterados (SC-006: até 10 s);
- pelo comando `apostas:conferir_encerramentos_automaticos` a cada minuto, que cobre as apostas só
  com jogos do pré-jogo (as cotações mudam na carga de 5 min).

Com a oferta ≥ alvo, o encerramento é feito na hora como `Automático`, inteiro, pelo valor da oferta
daquele momento, sem tempo de confirmação (o próprio sistema leu os dados atuais), e o automático
fica `Executado`. Sem oferta (inelegível no momento), o automático continua aguardando. Quando a
aposta é decidida, fica `Cancelado` ("Aposta decidida").

## R-13 — Notificações push: envio

**Decisão**: Web Push com chaves VAPID próprias, pela biblioteca `minishlink/web-push`, sem
servidor externo. Chaves em `.env` (`VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT`),
geradas uma vez por `notificacoes_push:gerar_chaves`. O envio é feito pelo job
`EnviarNotificacaoPush` (fila `notificacoes`), que agrupa as inscrições e usa o envio em lote da
biblioteca. Uma resposta 404 ou 410 descarta a inscrição (soft delete, FR-046). Outras falhas ficam
no log, sem nova tentativa (aviso de jogo atrasado não tem valor).

**Deduplicação (SC-008, FR-043)**: tabela `clientes_notificacoes` com `chave` única por cliente
(ex.: `evento:{id}`, `aposta:{id}:Vencedor`, `encerramento:{id}`). O envio só acontece se a linha
for criada, então várias apostas do mesmo cliente no jogo geram um aviso por cliente, enviado a cada
aparelho com aquele tipo ligado.

**Motivo**: é o padrão da web, sem custo nem cota (diferença aprovada). A tabela de enviadas dá
auditoria e garante um único envio mesmo com job repetido.

**Alternativas consideradas**: Firebase Cloud Messaging (terceiro e SDK próprio, desnecessário para
PWA); `laravel-notification-channels/webpush` (canal do Notifications que exige o trait no model
`Clientes` e tabela própria do pacote; a biblioteca direta mantém o padrão de serviço do projeto).

## R-14 — Notificações push: service worker

**Decisão**: o `generateSW` do `vite-plugin-pwa` continua e ganha `workbox.importScripts:
['/sw-notificacoes.js']`. O arquivo `resources/pwa/push_notifications.js` (JavaScript puro) trata
`push` e `notificationclick`:

- mostra o aviso com `tag: confronto-{id}` ou `aposta-{codigo}` e `renotify` (o aviso novo do
  mesmo jogo substitui o anterior, FR-045);
- ao tocar, foca a aba do bilhete ou abre `/bilhete/{codigo}`.

O arquivo é servido pela rota `GET /sw-notificacoes.js` do `ArquivosPwaController`, sem cache, como
o `sw.js`.

**Motivo**: mantém a geração automática do pré-cache e a atualização sem versão manual (regra de
cache da constituição). Trocar para `injectManifest` obrigaria a reescrever o service worker
inteiro.

## R-15 — Notificações push: inscrição no aparelho (FR-040, FR-041, FR-047)

**Decisão**: o hook `useNotificacoesPush`:

1. Lê a chave pública em `GET /api/publico/notificacoes/configuracao`.
2. Só depois do toque no sino chama `Notification.requestPermission()` e
   `pushManager.subscribe({ userVisibleOnly: true, applicationServerKey })`.
3. Grava a inscrição com `PUT /api/area-cliente/notificacoes/inscricao`. O registro é único pelo
   `endpoint`: se o aparelho trocar de cliente, a inscrição passa para o novo.

A preferência por tipo fica no registro da inscrição. iPhone sem o app instalado
(`navigator.standalone !== true` e sem `display-mode: standalone`) mostra a orientação de instalar.
Permissão bloqueada mostra como liberar nas configurações do navegador (no antigo era um vídeo; aqui
é texto, sem link externo). Ao sair da conta (logout), a inscrição do aparelho é removida.

## R-16 — Detecção dos eventos de jogo (FR-042)

**Decisão**: `ImportacaoAoVivo::importar` passa a ler o estado anterior dos jogos recebidos (uma
consulta por `codigo_externo` antes do `upsert`) e, depois da transação, chama
`ProcessamentoAoVivo::processar($antes, $depois)`. O serviço novo compara:

| Mudança | Evento | Notificação |
|---|---|---|
| placar total aumentou | `Gol` (um por carga, com o placar novo) | gols |
| placar total diminuiu | `Gol anulado` | gols |
| situação mudou para `Intervalo` | `Intervalo` (uma vez por jogo) | intervalo |
| placar, período, minuto, gols por tempo, escanteios ou hash das cotações mudou | — | broadcast `ConfrontoAtualizado` |

O **fim do jogo** vem da carga de resultados: `ImportacaoResultados::gravar_resultado`, quando o
jogo passa a ser apurável (`Encerrado`), registra `Fim de jogo` com o placar final. O ao vivo do
provedor só envia `1 tempo`, `Intervalo` e `2 tempo`; o jogo encerrado some do ao vivo, e o sumiço
pode ser só uma falha da carga.

Os eventos ficam em `confrontos_eventos`, com `chave` única para `Intervalo` e `Fim de jogo` (uma
vez por jogo). Gols não têm chave única: um gol anulado seguido de outro gol com o mesmo placar são
dois eventos reais, e a carga é serial (`withoutOverlapping`). Jogo do ao vivo sem confronto na
grade (`confrontos_id` nulo) é ignorado (não há aposta nele).

**Motivo**: a comparação com o estado anterior é a única fonte disponível (premissa da spec). Fazer
a comparação dentro da carga evita uma segunda leitura do provedor.

## R-17 — Quem recebe cada aviso (FR-043, FR-045)

**Decisão**: para um evento do jogo X:

1. Clientes com aposta `Ativa`, resultado `Aguardando` e palpite **ativo** em X.
2. Para cada cliente, a aposta mais recente nesse jogo dá o link.
3. A inscrição recebe se o tipo estiver ligado nela.

Texto:

| Evento | Título | Corpo |
|---|---|---|
| Gol | `⚽ GOL! Time A 1 x 0 Time B` | `{minuto}' · {campeonato}` |
| Gol anulado | `Gol anulado: Time A 0 x 0 Time B` | `{minuto}'` |
| Intervalo | `Intervalo: Time A 1 x 0 Time B` | — |
| Fim de jogo | `Fim de jogo: Time A 2 x 1 Time B` | — |
| Resultado da aposta (ganhou) | `Você ganhou! Aposta {código}` | `Prêmio de R$ X` |
| Resultado da aposta (perdeu) | `Aposta {código} perdida` | — |
| Encerramento automático | `Aposta {código} encerrada` | `R$ X entram no seu saldo às HH:MM` |

O resultado da aposta é avisado quando a apuração (spec 009) muda o resultado de `Aguardando` para
`Vencedor` ou `Perdedor`. Correções posteriores não geram novo aviso (`chave` com o resultado).

## R-18 — Configurações (FR-036)

**Decisão**: colunas novas em `configuracoes`, com a rota `GET/PUT /api/configuracoes/encerramento`
e permissão `configuracoes.editar`, no mesmo padrão de `configuracoes/apuracao` e
`configuracoes/criar-aposta`. O cliente ganha `clientes_configuracoes.encerrar_aposta`, editada pela
rota já existente `PUT clientes/{cliente}/configuracoes` (campo opcional, para não quebrar o painel
que ainda não o envia).

| Coluna | Padrão | Faixa aceita |
|---|---|---|
| `encerramento_habilitado` | verdadeiro | — |
| `encerramento_parcial_habilitado` | verdadeiro | — |
| `encerramento_automatico_habilitado` | verdadeiro | — |
| `encerramento_jogos_nao_iniciados` | verdadeiro | — |
| `margem_encerramento_pre_jogo` | 7,00 % | 0 a 50 |
| `margem_encerramento_ao_vivo` | 10,00 % | 0 a 50 |
| `valor_minimo_encerramento` | R$ 1,00 | 0,01 a 10.000 |
| `percentual_minimo_encerramento` | 5,00 % | 0 a 100 |
| `segundos_validade_oferta` | 8 | 3 a 60 |
| `limite_encerramentos_parciais` | 5 | 1 a 50 |

## R-19 — Permissão e listagem no painel (FR-037)

**Decisão**: a permissão `apostas_encerramentos.listar` vai num grupo novo `PERMISSOES_ENCERRAMENTOS`
em `App\Enums\Funcao`, que entra no `permissoes_padrao` e na regra "só Admin e Supervisor" do
`pode_usar` (como `PERMISSOES_ESPECIAIS`, `PERMISSOES_SITE` e `PERMISSOES_PAGAMENTOS`). O seeder
`EncerramentoSeeder` cria a permissão e a distribui aos usuários existentes, no padrão do
`ApuracaoSeeder`. A listagem `GET /api/apostas-encerramentos` aceita os filtros `data_inicial`,
`data_final`, `clientes_id` e `modalidade`, com 20 por página por padrão e máximo de 100. O "quanto
teria pago" é `(premio + valor_acrescido)` cheio se a aposta acabou `Vencedor` pelos palpites, 0 se
`Perdedor`, e nulo enquanto houver palpite em aberto.

**Observação**: a spec diz "no mesmo padrão das permissões de clientes", mas as de clientes vão
também para o Gerente. Para cumprir "Admin e Supervisor por padrão", o grupo segue o padrão das
permissões só de Admin e Supervisor, que já existe no `Funcao`.

## R-20 — Página de Regras (FR-039)

**Decisão**: `RegrasController` passa à página a prop `encerramento` (ligado, parcial, automático,
jogos não iniciados, margens, mínimos, validade, limite de parciais, minutos da janela). O
componente novo `CashOutRules` monta o texto com esses valores, dentro do `RulesContent`. Com o
encerramento desligado, a seção não aparece.

## Dependências novas (constituição: Stack e Restrições Técnicas)

| Pacote | Lado | Por quê |
|---|---|---|
| `laravel/reverb` ^1.0 | Composer | Servidor WebSocket próprio (R-01); traz `pusher/pusher-php-server` para o driver de broadcasting |
| `minishlink/web-push` ^9.0 | Composer | Envio Web Push com VAPID e criptografia do payload (R-13); exige as extensões `gmp` ou `bcmath` (já habilitada), `mbstring` e `openssl` |
| `laravel-echo` ^2.0 | npm | Cliente dos canais do Reverb (R-02) |
| `pusher-js` ^8.4 | npm | Transporte usado pelo Echo com o Reverb |

## Dados fake (Princípio IX)

Nenhum dado fake novo. A página usa as cores do tema que já vêm de `DadosFake::tema()`
(compartilhadas pelo Inertia desde a spec 005), e o aviso push usa o ícone que o manifest já
recebe de `DadosFake::icones()`. Os dois fakes continuam com a mesma spec substituta (configurações
visuais). Nenhum fake participa do cálculo ou do pagamento do encerramento.

## Pontos para confirmação do responsável (Princípios IV e VI)

**Todos confirmados pelo responsável em 2026-10-15**, como propostos:

1. Lista de arquivos existentes alterados (plan.md).
2. As quatro dependências novas e o processo `reverb:start` em produção.
3. R-07 (`fracao_restante` e mudança em `Apostas::total_a_pagar()`).
4. R-19 (grupo de permissão só de Admin e Supervisor, em vez do padrão das de clientes).
5. R-11 (critério objetivo do "já estava decidido" do FR-033).
6. R-05, critério 8 (jogo com resultado gravado torna a aposta inelegível até a apuração).
