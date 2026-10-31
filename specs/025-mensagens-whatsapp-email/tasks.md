# Tasks: Disparo de mensagens por WhatsApp e e-mail

**Input**: documentos de `specs/025-mensagens-whatsapp-email/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/api.md, contracts/mensagens.md, quickstart.md

**Tests**: sem testes automatizados (Constituição). A validação é manual, pelo `quickstart.md`.

**Regra de cada tarefa**: consultar o sistema antigo quando indicado (Princípio VII), alterar só os arquivos citados (Princípio IV) e fazer a revisão de legibilidade ao concluir (Princípio V). Toda coluna nova leva `->comment()`.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: pode rodar em paralelo (arquivos diferentes, sem dependência pendente)
- **[Story]**: história da spec

---

## Phase 1: Setup

- [ ] T001 Criar a migration da tabela em `database/migrations/*_create_mensagens_envios_table.php` conforme data-model: `tipo` varchar(40), `canal` varchar(20), `destinatario` varchar(150), `situacao` varchar(20), `reserva` boolean, `tentativas` unsigned tinyint padrão 0, `erro` varchar(255) nulo, `enviado_em` timestamp nulo, `referencia_tipo` varchar(40) nulo, `referencia_id` unsigned bigint nulo, timestamps, softDeletes, índices `(tipo, created_at)` e `(situacao, created_at)`
- [ ] T002 Criar a migration em `database/migrations/*_add_mensagens_configuracoes_table.php`: `email_admin` varchar(150) nulo, `mensagens_canais` json nulo, `robo_whatsapp_desconexao_pedida` boolean padrão falso, `robo_whatsapp_alerta_queda_em` timestamp nulo
- [ ] T003 Criar a migration em `database/migrations/*_add_aviso_parado_saques_table.php`: `aviso_parado_em` timestamp nulo, índice `(situacao, aviso_parado_em, created_at)` e preenchimento com a data atual nos saques `Pendente` existentes (R-06)
- [ ] T004 Criar a permissão `configuracoes.editar_mensagens`, só para o Admin, em `database/migrations/*_criar_permissao_editar_mensagens.php`, no padrão de `2026_10_27_000004_criar_permissao_robo_whatsapp.php`
- [ ] T005 [P] Criar os enums em `app/Enums/TipoMensagem.php`, `app/Enums/CanalMensagem.php` e `app/Enums/SituacaoEnvioMensagem.php`, com os casos, valores, canal padrão e indicação de reserva da tabela do data-model
- [ ] T006 [P] Criar o model em `app/Models/MensagensEnvios.php` com `SoftDeletes`, `fillable` e casts dos enums
- [ ] T007 Alterar `app/Models/Configuracoes.php`: novos campos e casts; adicionar método que devolve o canal de um `TipoMensagem`, com o padrão do enum quando ausente

---

## Phase 2: Foundational

**Bloqueia todas as histórias.**

- [ ] T008 Adicionar `enviar_texto(string $numero, string $texto)` em `app/Services/ClienteEvolution.php`: `POST /message/sendText/{instancia}` com `number` e `text`; 4xx lança `DestinatarioSemWhatsappException`; conexão perdida ou 5xx lança `RoboIndisponivelException`; sem texto nem código em log (R-01, R-02)
- [ ] T009 [P] Criar a exceção em `app/Exceptions/DestinatarioSemWhatsappException.php`
- [ ] T010 [P] Criar `app/Services/TextosMensagens.php`: textos de WhatsApp de cada `TipoMensagem`, conforme contracts/mensagens.md, e a estrutura do mapa de Mailables; cada Mailable é registrado na tarefa que passa a disparar o tipo (T015, T018, T022, T023, T026, T036, T039 e T052)
- [ ] T011 Criar `app/Jobs/EnviarMensagem.php` com `ShouldQueue` e `ShouldBeEncrypted`, `$tries = 4`, `$backoff = [10, 30, 60]`; códigos na fila `notificacoes` e demais na `default`; confere se a mensagem ainda vale, envia, atualiza `mensagens_envios`; falha definitiva vai direto para `failed`; `failed` aciona a reserva sem encadear outra (R-03, R-04, R-05)
- [ ] T012 Criar `app/Services/DisparoMensagens.php`: `enviar` lê o canal, grava uma linha por canal (`Dispensado` sem o dado), despacha com `afterCommit` e aplica a reserva imediata quando o robô não estiver `Conectado` (inclui `Aguardando leitura`); `enviar_agora` envia de forma síncrona e devolve o resultado (R-05, R-07)

**Checkpoint**: a base está pronta quando `enviar` despacha e registra um envio.

---

## Phase 3: User Story 1 - Cliente recupera a senha (P1) 🎯 MVP

**Goal**: o código chega ao cliente e nunca fica exposto.

**Independent Test**: quickstart §2.

- [ ] T013 [P] [US1] Criar `app/Mail/ClienteCodigoRecuperacao.php` e `resources/views/emails/clientes/codigo_recuperacao.blade.php`, no padrão de `emails/afiliados/codigo_recuperacao.blade.php`, sem `ShouldQueue`
- [ ] T014 [US1] Incluir em `app/Events/CodigoRecuperacaoGerado.php` o id do código, e em `app/Http/Controllers/AreaClienteRecuperacaoSenhaController.php` passar esse id ao disparar, para o job conferir a validade
- [ ] T015 [US1] Criar `app/Listeners/DispararCodigoRecuperacaoCliente.php`, chamando `DisparoMensagens`
- [ ] T016 [US1] Remover `app/Listeners/RegistrarMensagemWhatsapp.php` (FR-018)

**Checkpoint**: quickstart §1 itens 2 a 4 dependem da US5; até lá, validar pelo §2.

---

## Phase 4: User Story 2 - Cliente recebe boas-vindas (P2)

**Independent Test**: quickstart §3 itens 1 e 3.

- [ ] T017 [US2] Retirar `ShouldQueue` e `afterCommit` de `app/Mail/ClienteCadastroRealizado.php`, porque o envio passa a ser feito pelo job (R-04)
- [ ] T018 [US2] Criar `app/Listeners/DispararBoasVindasCliente.php` e remover `app/Listeners/EnviarEmailCadastroCliente.php`

---

## Phase 5: User Story 3 - Admin recebe os avisos da operação (P2)

**Independent Test**: quickstart §4.

- [ ] T019 [P] [US3] Criar `app/Mail/AdminCombinacaoRisco.php` e `resources/views/emails/admin/combinacao_risco.blade.php`, com os dados de `AvisosWhatsapp::texto_combinacoes` e botão para `/painel`
- [ ] T020 [P] [US3] Criar `app/Mail/AdminSaqueParado.php` e `resources/views/emails/admin/saque_parado.blade.php`, com cliente, valor, chave Pix e botão para `/painel/clientes`
- [ ] T021 [US3] Em `app/Services/AvisosWhatsapp.php`, remover `enviar` e a URL do CallMeBot, mantendo `texto_combinacoes`
- [ ] T022 [US3] Em `app/Console/Commands/AvisarCombinacoesRiscoCommand.php`, trocar o envio por `DisparoMensagens::enviar_agora` com reserva, e `pode_enviar` passa a exigir telefone ou e-mail do Admin, mantendo o agrupamento e as 3 tentativas
- [ ] T023 [US3] Criar `app/Console/Commands/AvisarSaquesParadosCommand.php` (`saques:avisar_parados`): saques `Pendente`, criados há 30 minutos ou mais e com `aviso_parado_em` nulo; marca a coluna e despacha na mesma transação, com trava de linha. Adicionar `aviso_parado_em` em `app/Models/Saques.php`
- [ ] T024 [US3] Agendar `saques:avisar_parados` a cada minuto em `routes/console.php`, com `withoutOverlapping`
- [ ] T060 [US3] Depois de T022 e T032, criar `database/migrations/*_remove_chave_callmebot_configuracoes_table.php` (exclusão física justificada em R-07; o `down` recria a coluna vazia) e retirar `chave_callmebot` de `fillable`, `hidden` e casts em `app/Models/Configuracoes.php`

---

## Phase 6: User Story 4 - Admin sabe quando o robô cai (P2)

**Independent Test**: quickstart §5.

- [ ] T025 [P] [US4] Criar `app/Mail/AdminRoboDesconectado.php` e `resources/views/emails/admin/robo_desconectado.blade.php`, com botão para `/painel/configuracoes`
- [ ] T026 [US4] Em `app/Services/RoboWhatsapp.php`: `desconectar` marca `robo_whatsapp_desconexao_pedida`; `conectar` e a volta da conexão a desmarcam; passar de Conectado para Desconectado sem o pedido envia o e-mail, com no máximo um a cada 30 minutos, usando `robo_whatsapp_alerta_queda_em` (R-08)
- [ ] T027 [US4] Criar `app/Console/Commands/ConferirRoboWhatsappCommand.php` (`robo_whatsapp:conferir`) chamando `situacao()`, agendado a cada 2 minutos em `routes/console.php`; com a Evolution configurada e sem resposta em 2 conferências seguidas, grava o robô como desconectado por `app/Services/RoboWhatsapp.php`, o que aciona o alerta de T026; o contador zera a cada resposta (R-08)
- [ ] T028 [US4] Acrescentar `robo_whatsapp_caido` em `app/Http/Resources/UsuarioLogadoResource.php`, só para quem tem `configuracoes.editar_mensagens` (contracts/api.md)
- [ ] T029 [US4] Mostrar a faixa de alerta, com link para Configurações, em `resources/js/components/PanelShell/index.jsx` e `styles.jsx`

---

## Phase 7: User Story 6 - Admin escolhe o canal de cada mensagem (P2)

**Independent Test**: quickstart §3 itens 2 e 4.

- [ ] T030 [US6] Criar `app/Http/Requests/ConfiguracoesMensagensRequest.php` conforme contracts/api.md: `telefone_whatsapp_admin` regex `^\d{12,15}$` ou nulo, `email_admin` e-mail válido de até 150 caracteres ou nulo, `canais` só com tipos da lista e valores de `CanalMensagem`; aviso de combinação ligado exige telefone ou e-mail; mensagens em português
- [ ] T031 [US6] Criar `app/Http/Resources/ConfiguracoesMensagensResource.php` e `app/Http/Controllers/ConfiguracoesMensagensController.php` (`show`, `update`), gravando por `HistoricoConfiguracoes`, seção `mensagens`
- [ ] T032 [US6] Em `routes/api.php`, adicionar `GET` e `PUT /configuracoes/mensagens` e remover `configuracoes/avisos-whatsapp`; remover `ConfiguracoesAvisosWhatsappController.php`, `ConfiguracoesAvisosWhatsappRequest.php` e `ConfiguracoesAvisosWhatsappResource.php`
- [ ] T033 [US6] Em `app/Services/HistoricoConfiguracoes.php`, retirar `chave_callmebot` de `SEGREDOS`
- [ ] T034 [US6] Criar `resources/js/components/MessagesCard/index.jsx` e `styles.jsx`: interruptor do aviso de combinação, telefone, e-mail, lista de mensagens com seleção de canal e `SaveBar`; alerta para o campo vazio do canal escolhido
- [ ] T035 [US6] Em `resources/js/pages/PanelSettings/index.jsx`, mostrar o `MessagesCard` na aba Integrações só para o Admin; em `resources/js/utils/settings_fields.js`, remover a seção `avisos_whatsapp`
- [ ] T036 [US6] Afiliados: em `app/Services/CadastroAfiliados.php` e `app/Http/Controllers/AreaAfiliadoRecuperacaoSenhaController.php`, usar `DisparoMensagens`, passando o id do código para o job conferir a validade e tirando a condição de só gerar o código com e-mail, já que o afiliado pode ter só telefone (FR-008); retirar `ShouldQueue` de `app/Mail/AfiliadoAprovado.php`, `AfiliadoRecusado.php`, `AfiliadoCadastroRecebido.php` e `AfiliadoCodigoRecuperacao.php`

---

## Phase 8: User Story 7 - Destinatário entende o e-mail de imediato (P2)

**Independent Test**: quickstart §6.

- [ ] T037 [US7] Revisar com as regras de FR-026 e FR-027, e ajustar os assuntos conforme contracts/mensagens.md, em `resources/views/emails/clientes/codigo_recuperacao.blade.php`, `resources/views/emails/admin/combinacao_risco.blade.php`, `resources/views/emails/admin/saque_parado.blade.php`, `resources/views/emails/admin/robo_desconectado.blade.php`, `app/Mail/ClienteCodigoRecuperacao.php`, `app/Mail/AdminCombinacaoRisco.php`, `app/Mail/AdminSaqueParado.php` e `app/Mail/AdminRoboDesconectado.php`

---

## Phase 9: User Story 5 - Admin testa os canais (P3)

**Independent Test**: quickstart §1.

- [ ] T038 [P] [US5] Criar `app/Mail/MensagemTeste.php` e `resources/views/emails/admin/teste.blade.php`
- [ ] T039 [US5] Adicionar `teste` em `app/Http/Controllers/ConfiguracoesMensagensController.php` e `POST /configuracoes/mensagens/teste` em `routes/api.php`, com `throttle:5,1`, sem campo de destinatário, por `enviar_agora`, e erros sem detalhes técnicos
- [ ] T040 [US5] Adicionar os botões "Testar WhatsApp" e "Testar e-mail" em `resources/js/components/MessagesCard/index.jsx`

---

## Phase 9A: User Story 8 - Cliente confirma o e-mail no cadastro (P2)

**Goal**: com a opção ligada, o cliente só entra depois de confirmar o e-mail.

**Independent Test**: quickstart §6a.

**Antes**: consultar `wssports.bet/app/Http/Controllers/CadastroController.php` (`confirmar`) e `app/Mail/CadastroMail.php`.

- [ ] T046 [US8] Criar a migration em `database/migrations/*_add_confirmacao_clientes_table.php`: `clientes.confirmacao_pendente` boolean padrão falso, `clientes.email_confirmado_em` timestamp nulo e `configuracoes.confirmacao_cadastro` boolean padrão falso
- [ ] T047 [US8] Criar a migration em `database/migrations/*_create_clientes_confirmacoes_email_table.php`: `clientes_id` foreignId, `token` char(64) único, `expira_em`, `usado_em` nulo, `invalidado_em` nulo, timestamps e softDeletes; e o model `app/Models/ClientesConfirmacoesEmail.php`
- [ ] T048 [US8] Em `app/Models/Clientes.php` e `app/Models/Configuracoes.php`, adicionar campos e casts; `pode_acessar` passa a recusar `confirmacao_pendente` só enquanto `confirmacao_cadastro` estiver ligada. Conferir o efeito em `AreaClienteAutenticacaoController.php`, `AreaClienteRecuperacaoSenhaController.php`, `GarantirAcessoCliente.php`, `CarteiraCassino.php`, `ElegibilidadeEncerramento.php`, `IdentificacaoPublico.php` e `Apostador.php` e avisar antes de alterar qualquer um
- [ ] T049 [US8] Adicionar `ConfirmaçãoCadastro` em `app/Enums/TipoMensagem.php` e o texto de WhatsApp em `app/Services/TextosMensagens.php`
- [ ] T050 [US8] Criar `app/Mail/ClienteConfirmacaoCadastro.php` e `resources/views/emails/clientes/confirmacao_cadastro.blade.php`, com o botão "Confirmar e-mail" e a validade de 24 horas
- [ ] T051 [US8] Criar `app/Services/ConfirmacaoCadastro.php`: gera o token aleatório, grava o hash SHA-256, invalida os anteriores, envia; `confirmar` valida e marca em transação; reenvio
- [ ] T052 [US8] Alterar `app/Services/CadastroClientes.php` e `app/Listeners/DispararBoasVindasCliente.php`: com a opção ligada, cria pendente e dispara `ConfirmaçãoCadastro` no lugar de `BoasVindasCliente`: um e-mail sempre e, com o WhatsApp no canal escolhido, o aviso de verificar o e-mail, sem o e-mail comum de boas-vindas (FR-030)
- [ ] T053 [US8] Em `app/Http/Requests/StoreClientesRequest.php`, exigir `email` com a opção ligada; em `app/Http/Controllers/AreaClienteCadastroController.php`, responder sem token; em `app/Http/Controllers/AreaClienteAutenticacaoController.php`, responder 403 com `confirmacao_pendente`
- [ ] T054 [US8] Criar `app/Http/Controllers/AreaClienteConfirmacaoEmailController.php` e as rotas `POST /area-cliente/confirmar-email` e `/reenviar` em `routes/api.php`, com os limites de contracts/api.md; cliente bloqueado não recebe token
- [ ] T055 [US8] Adicionar `confirmar_email` em `app/Http/Controllers/ClientesController.php` e `POST /clientes/{cliente}/confirmar-email` em `routes/api.php`, com a permissão `clientes.editar` e alcance
- [ ] T056 [US8] Criar `app/Http/Controllers/PaginaConfirmacaoEmailController.php`, a rota web `confirmar-email` em `routes/web.php` e a página `resources/js/pages/ConfirmEmail/index.jsx` e `styles.jsx`, com o botão que chama o `POST` e tela de link expirado, que pede DDI e telefone para reenviar
- [ ] T057 [US8] Em `resources/js/components/AuthModal/index.jsx`, tornar o e-mail obrigatório com a opção ligada, mostrar o aviso sem entrar na conta e oferecer o reenvio no login pendente, mantendo o evento de cadastro do GTM ao concluir o cadastro; expor `confirmacao_cadastro` ao site em `app/Http/Middleware/TratarRequisicoesInertia.php`
- [ ] T058 [US8] Adicionar a opção em `resources/js/components/MessagesCard/index.jsx` e nos arquivos `ConfiguracoesMensagensRequest.php` e `ConfiguracoesMensagensResource.php`
- [ ] T059 [US8] Em `resources/js/components/ClientDrawer/index.jsx`, mostrar "E-mail não confirmado" com o botão de confirmar manualmente

---

## Phase 10: Polish

- [ ] T041 Regenerar `docs/postman/wssports_api.postman_collection.json`, substituindo o arquivo e mantendo `base_url` e `token`
- [ ] T042 Atualizar `.env.example` se faltar alguma variável usada, sem credenciais reais
- [ ] T043 Conferir que "callmebot" não aparece mais em `app` e `resources`; `.github/workflows/deploy.yml` não muda (FR-016)
- [ ] T044 Registrar nas specs 002, 012, 014 e 021 (`specs/*/spec.md`) a nota de que o envio passou para a spec 025
- [ ] T045 Validar todo o `specs/025-mensagens-whatsapp-email/quickstart.md`, anotar em R-01 e R-02 da `research.md` as respostas reais da Evolution e fazer a revisão final do conjunto (Princípio V)

---

## Dependencies & Execution Order

- **Setup → Foundational → histórias**.
- **US1**: base do MVP, sem dependência de outra história.
- **US2**: depende só da Foundational.
- **US6**: depende só da Foundational e DEVE rodar antes de US3 e US4, porque o telefone e o e-mail do Admin são preenchidos na tela dela. Executar a Phase 7 antes das Phases 5 e 6. T036 depende dos Mailables de afiliados e é validada com ela.
- **US3 e US4**: dependem da US6. T060 roda só depois de T022 e T032.
- **US7**: depende das histórias que criam os modelos.
- **US5**: depende do `MessagesCard` (US6).
- **US8**: depende de US2 e US6. As tarefas T046 a T059 rodam antes da fase Polish, mesmo com IDs maiores.
- **Polish**: por último.

## Parallel Example

```text
Setup: T005 e T006
Foundational: T009 e T010
US3: T019 e T020
```

## Implementation Strategy

1. **MVP**: Phases 1 a 3. O cliente recupera a senha, e o código sai do log.
2. **Validação antecipada**: como o caminho da Evolution (R-01) ainda não foi confirmado, testar T008 com um envio real logo depois de T012.
3. **Incremental**: US2, US6, US3, US4, US7, US5 e US8, validando cada uma pelo quickstart.
