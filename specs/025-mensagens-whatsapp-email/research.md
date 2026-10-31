# Research: Disparo de mensagens por WhatsApp e e-mail

## Sistema antigo consultado (Princípio VII)

`wssports.bet`, `main` `408cf730` (somente leitura): `app/Jobs/EnviarMensagemWhatsApp.php`, `app/Http/Controllers/WhatsAppController.php`, `app/Console/Commands/VerificarSaquesCommand.php`, `app/Console/Commands/NotificacaoCommand.php`, `app/Http/Controllers/CadastroController.php`, `app/Mail/*`, `resources/js/services/whatsapp` e `resources/js/screens/adm/components/header`.

Funcionamento e problemas estão na seção "Referências do sistema antigo" da spec. As diferenças foram aprovadas nas Clarifications.

As credenciais de e-mail vieram do `.env` antigo. `MAIL_ENCRYPTION=tls` não foi copiada porque o Laravel 12 usa `MAIL_SCHEME`; na porta 587, a conexão negocia STARTTLS. O login só é confirmado no primeiro envio real (quickstart §1).

## R-01 — Formato do envio na Evolution API

**Decision**: `POST /message/sendText/{instancia}`, cabeçalho `apikey` e corpo `{ "number": "<DDI+DDD+número>", "text": "..." }`.

**Rationale**: o código-fonte oficial (`sendMessage.router.ts`, `sendMessage.dto.ts`, `message.schema.ts`) confirma `POST`, os campos obrigatórios `number` e `text` e o nome da rota `sendText`. O prefixo `/message` segue a convenção das rotas e não foi confirmado nesses arquivos; o `apikey` é o cabeçalho já usado pelo `ClienteEvolution`. A página oficial da documentação respondeu 404.

**Confirmação obrigatória**: quickstart §1, antes das demais tarefas de envio.

**Alternatives considered**: nenhum pacote cliente; `ClienteEvolution` já é a única classe que fala com a Evolution (spec 021).

## R-02 — Falha temporária ou definitiva

**Decision**: conexão perdida, tempo esgotado ou 5xx são falhas temporárias. Resposta 4xx é definitiva, com `DestinatarioSemWhatsappException`, e não repete.

**Rationale**: a resposta para número sem WhatsApp não foi encontrada no código lido. Tratar todo 4xx como definitivo evita repetir um pedido recusado. A resposta real é registrada no quickstart §1. Credencial errada também é 4xx: fica como falhou, e o erro resumido a identifica.

## R-03 — Código fora de logs, registros e fila em texto puro

**Decision**: `EnviarMensagem` implementa `ShouldBeEncrypted`. `mensagens_envios` não guarda conteúdo. O `RegistrarMensagemWhatsapp` é removido.

**Rationale**: a fila fica no banco, então o código ficaria legível em `jobs` e `failed_jobs`. O problema também alcança o e-mail do afiliado, que hoje entra na fila com o código, e deixa de existir ao passar por este job.

**Antes de enviar**, o job confere se o código de origem continua válido (não usado, não invalidado e dentro da validade). Isso cobre os dois casos de borda da spec.

## R-04 — Um job único e as filas

**Decision**: um job `EnviarMensagem` por canal, `$tries = 4` (primeira tentativa + 3) e `$backoff = [10, 30, 60]`. Filas `mensagens_prioritarias` (códigos) e `mensagens` (demais).

**Rationale**: mantém as tentativas do antigo e o padrão de `FILA` dos jobs atuais. O job envia o e-mail de forma síncrona para saber o resultado e poder registrá-lo; por isso os Mailables deixam de implementar `ShouldQueue`, já que o Laravel enfileiraria de novo um Mailable com esse contrato.

**Ação no servidor**: a lista de filas do `wssports-fila` está no Supervisor, fora do repositório, e precisa incluir as duas novas, com `mensagens_prioritarias` primeiro. Sem isso, as mensagens não saem. Está no `docs/deploy/README.md` e no quickstart.

**Alternatives considered**: reaproveitar a fila `notificacoes` não dá prioridade aos códigos, porque concorre com as notificações push.

## R-05 — Reserva de canal

**Decision**: `DisparoMensagens` decide o canal; ao falhar de vez, `EnviarMensagem::failed` aciona a reserva dos tipos que a têm (códigos e avisos ao Admin). Robô já gravado como desconectado, ou destinatário sem o dado, usa a reserva na hora, sem gastar tentativas.

**Rationale**: o código precisa chegar em até 1 minuto; esperar 100 segundos de tentativas não atende isso quando o sistema já sabe que o robô caiu. O registro do canal original fica como falhou ou dispensado, e a reserva tem linha própria. A reserva não aciona outra reserva.

## R-06 — Saque parado

**Decision**: `saques:avisar_parados` a cada minuto: situação `Pendente`, criado há 30 minutos ou mais e `aviso_parado_em` nulo. Marca a coluna na mesma transação do despacho. A migration preenche a coluna dos pendentes já existentes.

**Rationale**: a coluna garante um aviso por saque, mesmo com o comando reexecutado. O preenchimento evita uma rajada de avisos antigos na primeira execução, e `Processando` fica de fora porque o pagamento já está em andamento.

## R-07 — Retirada do CallMeBot

**Decision**: `AvisosWhatsapp` fica só com o texto das combinações. O comando chama `DisparoMensagens::enviar_agora`, síncrono, e mantém as 3 tentativas por minuto. `chave_callmebot` é removida fisicamente.

**Rationale**: o comando já tem o agrupamento e o controle de tentativas por combinação (spec 014). As colunas `situacao_whatsapp`, `tentativas_whatsapp` e `erro_whatsapp` ficam com o nome atual, pelo Princípio IV, e passam a valer para o canal usado. A remoção física é justificada: é um segredo sem uso, e a spec pede que seja apagado.

## R-08 — Queda do robô

**Decision**: `RoboWhatsapp` compara a situação anterior com a nova. Passar de Conectado para Desconectado sem `robo_whatsapp_desconexao_pedida` envia o e-mail, com no máximo um a cada 30 minutos (`robo_whatsapp_alerta_queda_em`). `robo_whatsapp:conferir` chama `situacao()` a cada 2 minutos. O alerta do painel vem em `UsuarioLogadoResource`, só para quem tem a permissão nova.

**Rationale**: o aviso automático pode se perder, e a conferência garante os 5 minutos do SC-004. O antigo consultava a cada 5 segundos na tela, o que só avisava quem estivesse com o painel aberto.

## R-09 — Tela

**Decision**: componente `MessagesCard` na aba Integrações, ao lado do robô: interruptor do aviso de combinação, telefone, e-mail, seleção de canal por mensagem, `SaveBar` e botões de teste. A seção `avisos_whatsapp` sai da aba Operação. A permissão nova vale só para o Admin.

**Rationale**: a seção precisa de uma lista por mensagem e de ações de teste, o que os campos genéricos de `settings_fields.js` não cobrem. Os canais ficam num JSON em `configuracoes`, e o padrão vem do enum, para que uma mensagem nova não exija outra coluna.

## R-10 — Modelos de e-mail

**Decision**: novos modelos estendendo `emails.layout`, com `DadosEmail`: um assunto objetivo, até 3 linhas de texto, os dados em lista simples e no máximo um botão. Os modelos existentes não mudam.

## R-11 — Aviso do deploy

**Decision**: `.github/workflows/deploy.yml` e os segredos `CALLMEBOT_*` do GitHub não mudam.

**Rationale**: decisão do responsável em 2026-10-31: o CallMeBot sai do sistema, mas continua no GitHub.
