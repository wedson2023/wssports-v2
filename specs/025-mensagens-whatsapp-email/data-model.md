# Data Model: Disparo de mensagens por WhatsApp e e-mail

Toda coluna nova tem comentário no banco (Constituição).

## `mensagens_envios` (nova)

Uma linha por tentativa de canal. Sem conteúdo de mensagem e sem listagem nesta spec.

| Coluna | Tipo | Regra |
|---|---|---|
| `id` | id | |
| `tipo` | varchar(40) | `TipoMensagem` |
| `canal` | varchar(20) | `WhatsApp` ou `E-mail` |
| `destinatario` | varchar(150) | telefone com DDI ou e-mail |
| `situacao` | varchar(20) | `SituacaoEnvioMensagem` |
| `reserva` | boolean | verdadeiro quando saiu pelo canal de reserva |
| `tentativas` | unsigned tinyint | padrão 0 |
| `erro` | varchar(255), nulo | resumo, sem chave, sem código e sem endereço do serviço |
| `enviado_em` | timestamp, nulo | |
| `referencia_tipo` | varchar(40), nulo | `Cliente`, `Afiliado` ou `Saque` |
| `referencia_id` | unsigned bigint, nulo | id solto, pois aponta para tabelas diferentes |
| `created_at`, `updated_at`, `deleted_at` | | |

Índices: `(tipo, created_at)` e `(situacao, created_at)`.

**Transições**: `Pendente → Enviado`; `Pendente → Falhou`; `Pendente → Dispensado` (sem destinatário, ou código já inválido). Criada diretamente como `Dispensado` quando falta o dado do canal.

## Enums

- **`TipoMensagem`** (com canal padrão e indicação de reserva):

  | Caso | Valor | Padrão | Reserva |
  |---|---|---|---|
  | `CódigoRecuperaçãoCliente` | Código de recuperação do cliente | WhatsApp | Sim |
  | `BoasVindasCliente` | Boas-vindas do cliente | Os dois | Não |
  | `CombinaçãoRisco` | Combinação de risco | WhatsApp | Sim |
  | `SaqueParado` | Saque parado | WhatsApp | Sim |
  | `AfiliadoCadastroRecebido` | Cadastro de afiliado recebido | E-mail | Não |
  | `AfiliadoAprovado` | Afiliado aprovado | E-mail | Não |
  | `AfiliadoRecusado` | Afiliado recusado | E-mail | Não |
  | `CódigoRecuperaçãoAfiliado` | Código de recuperação do afiliado | E-mail | Sim |
  | `RobôDesconectado` | Robô desconectado | E-mail, fixo | Não |
  | `Teste` | Teste | o escolhido no botão | Não |

  `RobôDesconectado` e `Teste` não aparecem na lista de escolha.

- **`CanalMensagem`**: `WhatsApp`, `E-mail`, `Os dois`.
- **`SituacaoEnvioMensagem`**: `Pendente`, `Enviado`, `Falhou`, `Dispensado`.

## `configuracoes` (alterada)

| Coluna | Tipo | Regra |
|---|---|---|
| `email_admin` | varchar(150), nulo | destino dos e-mails ao Admin |
| `mensagens_canais` | json, nulo | `{ "<caso>": "<canal>" }`; tipo ausente usa o padrão do enum |
| `robo_whatsapp_desconexao_pedida` | boolean, padrão falso | verdadeiro ao desconectar pelo painel; falso ao conectar |
| `robo_whatsapp_alerta_queda_em` | timestamp, nulo | último e-mail de queda |
| `chave_callmebot` | **removida** | R-07 |

`telefone_whatsapp_admin` e `aviso_combinacao_whatsapp` continuam como estão.

## `saques` (alterada)

| Coluna | Tipo | Regra |
|---|---|---|
| `aviso_parado_em` | timestamp, nulo | quando o aviso foi despachado; preenchida nos pendentes existentes na migration |

Índice: `(situacao, aviso_parado_em, created_at)`.

## `clientes` (alterada)

| Coluna | Tipo | Regra |
|---|---|---|
| `confirmacao_pendente` | boolean, padrão falso | verdadeiro no cadastro público com a opção ligada; falso ao confirmar |
| `email_confirmado_em` | timestamp, nulo | quando o e-mail foi confirmado |

## `clientes_confirmacoes_email` (nova)

| Coluna | Tipo | Regra |
|---|---|---|
| `id` | id | |
| `clientes_id` | foreignId | `clientes` |
| `token` | char(64), único | hash SHA-256 do token enviado; o token em si não é guardado |
| `expira_em` | timestamp | 24 horas após a criação |
| `usado_em` | timestamp, nulo | |
| `invalidado_em` | timestamp, nulo | preenchido quando um novo link é gerado |
| `created_at`, `updated_at`, `deleted_at` | | |

Em `configuracoes`: `confirmacao_cadastro` boolean, padrão falso.

`TipoMensagem` ganha `ConfirmaçãoCadastro` (valor "Confirmação de cadastro"), sempre por e-mail, fora da lista de escolha.

## Permissão

`configuracoes.editar_mensagens`, criada por migration como `criar_permissao_robo_whatsapp`, só para o Admin.
