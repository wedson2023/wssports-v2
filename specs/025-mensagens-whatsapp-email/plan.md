# Implementation Plan: Disparo de mensagens por WhatsApp e e-mail

**Branch**: `025-mensagens-whatsapp-email` | **Date**: 2026-10-31 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/025-mensagens-whatsapp-email/spec.md`

## Summary

Cria um único caminho de envio para as mensagens do sistema:

- **Envio** (R-01 a R-04): serviço `DisparoMensagens`, que lê o canal configurado, grava uma linha em `mensagens_envios` por canal e despacha o job `EnviarMensagem`. O WhatsApp sai pelo `ClienteEvolution` e o e-mail, pelos Mailables.
- **Reserva de canal** (R-05): códigos de recuperação e avisos ao Admin mudam de canal quando o escolhido falha ou não pode ser usado.
- **Avisos ao Admin** (R-06, R-07): combinação de risco sem CallMeBot; comando novo para saque parado há 30 minutos.
- **Queda do robô** (R-08): e-mail e alerta no painel, com conferência periódica caso o aviso automático se perca.
- **Configurações** (R-09): card "Mensagens" na aba Integrações, com canais, telefone, e-mail e teste, só para o Admin.
- **Modelos de e-mail** (R-10): 5 novos, na base visual existente.
- **Deploy** (R-11): o aviso do GitHub continua pelo CallMeBot, sem alteração.
- **Segurança** (R-03): o código não vai para log nem para registro, e os jobs que o transportam são criptografados.

Decisões em [research.md](research.md).

## Technical Context

**Language/Version**: PHP 8.2 (^8.2); JavaScript (ES2022) com JSX

**Primary Dependencies**: as instaladas (Laravel 12, Inertia 2, React 19, styled-components 6, sweetalert2, jwt-auth, spatie/laravel-permission). Nenhuma nova.

**Storage**: MySQL; 4 migrations: tabela `mensagens_envios`, colunas em `configuracoes`, coluna em `saques` e a permissão nova.

**Testing**: nenhum teste automatizado (Constituição); validação pelo [quickstart.md](quickstart.md)

**Target Platform**: servidor Linux com Supervisor (fila) e agendador; deploy contínuo (spec 013); Evolution API v2 em `EVOLUTION_API_URL`; SMTP em `mail.wssports.bet`

**Project Type**: monólito Laravel + Inertia com API JWT

**Performance Goals**: despacho do envio sem atrasar a requisição de origem; código de recuperação entregue em até 1 minuto, na fila prioritária; queda do robô percebida em até 5 minutos.

**Constraints**: chaves e códigos nunca em log; a Evolution API só é chamada pelo `ClienteEvolution` no sistema; nenhuma rota com destinatário livre; falha de envio não desfaz a operação de origem; sem testes automatizados.

**Scale/Scope**:

| Item | Quantidade |
|---|---|
| Migrations | 4 |
| Rotas da API novas / removidas | 3 / 2 |
| Comandos agendados novos | 2 |
| Componente novo | 1 (`MessagesCard`) |
| Modelos de e-mail novos | 5 |
| Filas novas | 2 |

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Princípio | Situação |
|---|---|
| I. Nomenclatura | ✅ tabela `mensagens_envios`, colunas em português, enums `TipoMensagem`, `CanalMensagem` e `SituacaoEnvioMensagem`, permissão `configuracoes.editar_mensagens`, rotas kebab-case, componente `MessagesCard` |
| II. Português | ✅ artefatos, mensagens e comentários em português |
| III. Componentes | ✅ `MessagesCard` com pasta própria, `index.jsx` e `styles.jsx` |
| IV. Escopo estrito | ✅ altera arquivos das specs 002, 012, 014 e 021; a lista está em "Project Structure" e foi autorizada pelo responsável em 2026-10-31 |
| V. Revisão de legibilidade | ✅ prevista no fim de cada tarefa |
| VI. Padrões | ✅ jobs com `FILA`, serviço + command agendado, permissão por migration como `criar_permissao_robo_whatsapp`, histórico por `HistoricoConfiguracoes`, card como `WhatsAppBotCard`, e-mails com `DadosEmail` e `emails.layout` |
| VII. Sistema antigo | ✅ consultado e listado em research.md; diferenças aprovadas na spec |
| VIII. Visual | ✅ os e-mails usam a base já derivada do antigo; o card segue o padrão aprovado da tela Configurações (spec 021) |
| IX. Dados fake | ✅ nenhum |
| Stack e restrições | ✅ timestamps, soft delete e comentários na tabela nova; a remoção da coluna `chave_callmebot` é física e justificada em R-07; canal escolhido de lista; o registro não tem listagem |
| Fluxo | ✅ sem testes; tarefa explícita para regenerar o Postman |

**Resultado**: aprovado, sem violação a justificar.

**Re-check pós-design**: mantido.

## Project Structure

### Documentation (this feature)

```text
specs/025-mensagens-whatsapp-email/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── api.md
│   └── mensagens.md
└── tasks.md             # gerado pelo /speckit-tasks
```

### Source Code (repository root)

```text
database/migrations/
├── ..._create_mensagens_envios_table.php                  # novo
├── ..._add_mensagens_configuracoes_table.php              # novo; remove chave_callmebot
├── ..._add_aviso_parado_saques_table.php                  # novo
└── ..._criar_permissao_editar_mensagens.php               # novo

app/Enums/{TipoMensagem,CanalMensagem,SituacaoEnvioMensagem}.php       # novos
app/Exceptions/DestinatarioSemWhatsappException.php                    # novo
app/Models/MensagensEnvios.php                                         # novo
app/Models/Configuracoes.php                                           # alterado
app/Models/Saques.php                                                  # alterado

app/Services/DisparoMensagens.php                                      # novo: canal, registro e reserva
app/Services/TextosMensagens.php                                       # novo: textos do WhatsApp e Mailable de cada tipo
app/Services/ClienteEvolution.php                                      # alterado: enviar_texto
app/Services/RoboWhatsapp.php                                          # alterado: queda e desconexão pedida
app/Services/HistoricoConfiguracoes.php                                # alterado: tira chave_callmebot
app/Services/CadastroAfiliados.php                                     # alterado: usa DisparoMensagens
app/Services/AvisosWhatsapp.php                                        # alterado: fica só com o texto; sai o CallMeBot

app/Jobs/EnviarMensagem.php                                            # novo
app/Console/Commands/AvisarSaquesParadosCommand.php                    # novo
app/Console/Commands/ConferirRoboWhatsappCommand.php                   # novo
app/Console/Commands/AvisarCombinacoesRiscoCommand.php                 # alterado
routes/console.php                                                     # alterado

app/Listeners/DispararBoasVindasCliente.php                            # novo
app/Listeners/DispararCodigoRecuperacaoCliente.php                     # novo
app/Listeners/RegistrarMensagemWhatsapp.php                            # removido
app/Listeners/EnviarEmailCadastroCliente.php                           # removido

app/Http/Controllers/ConfiguracoesMensagensController.php              # novo
app/Http/Requests/ConfiguracoesMensagensRequest.php                    # novo
app/Http/Resources/ConfiguracoesMensagensResource.php                  # novo
app/Http/Controllers/ConfiguracoesAvisosWhatsappController.php         # removido
app/Http/Requests/ConfiguracoesAvisosWhatsappRequest.php               # removido
app/Http/Resources/ConfiguracoesAvisosWhatsappResource.php             # removido
app/Http/Controllers/AreaClienteRecuperacaoSenhaController.php         # alterado: passa o id do código
app/Http/Controllers/AreaAfiliadoRecuperacaoSenhaController.php        # alterado: usa DisparoMensagens
app/Http/Resources/UsuarioLogadoResource.php                           # alterado: alerta de queda
routes/api.php                                                         # alterado

app/Mail/{ClienteCadastroRealizado,AfiliadoAprovado,AfiliadoRecusado,AfiliadoCadastroRecebido,AfiliadoCodigoRecuperacao}.php  # alterados: saem da fila própria
app/Mail/{ClienteCodigoRecuperacao,AdminCombinacaoRisco,AdminSaqueParado,AdminRoboDesconectado,MensagemTeste}.php             # novos
resources/views/emails/clientes/codigo_recuperacao.blade.php                     # novo
resources/views/emails/admin/{combinacao_risco,saque_parado,robo_desconectado,teste}.blade.php  # novos

resources/js/components/MessagesCard/{index.jsx,styles.jsx}            # novo
resources/js/components/PanelShell/{index.jsx,styles.jsx}              # alterado: faixa de alerta
resources/js/pages/PanelSettings/index.jsx                             # alterado
resources/js/utils/settings_fields.js                                  # alterado: sai avisos_whatsapp

docs/deploy/README.md                                                  # alterado: filas novas no Supervisor
composer.json                                                          # alterado: filas novas no script dev
docs/postman/wssports_api.postman_collection.json                      # regenerada
```

**Structure Decision**: monólito existente, com o envio concentrado em `DisparoMensagens` e `EnviarMensagem`. Nenhum outro ponto do sistema chama o `Mail` ou a Evolution para mensagens.

## Complexity Tracking

Sem violações a justificar.
