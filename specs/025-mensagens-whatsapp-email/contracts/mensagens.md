# Contrato das mensagens

`{banca}` é o nome do sistema. Valores em dinheiro no formato `R$ 1.234,56`.

| Tipo | Texto no WhatsApp | Assunto do e-mail | Botão do e-mail |
|---|---|---|---|
| Código do cliente | `Seu código de recuperação é {codigo}, válido por 15 minutos.` | `[ {banca} ] Seu código de recuperação` | nenhum |
| Boas-vindas | `Olá, {nome}! Sua conta foi criada com sucesso.` | o atual | o atual |
| Combinação de risco | o atual, de `AvisosWhatsapp::texto_combinacoes` | `[ {banca} ] Combinação de risco nova` | Ver no painel → `/painel` |
| Saque parado | ver abaixo | `[ {banca} ] Saque aguardando há 30 minutos` | Ver saque → `/painel/clientes` |
| Afiliado: recebido, aprovado e recusado | texto curto com o mesmo sentido do e-mail | os atuais | os atuais |
| Código do afiliado | `Seu código de recuperação é {codigo}, válido por {validade} minutos.` | o atual | nenhum |
| Robô desconectado | não tem | `[ {banca} ] Robô do WhatsApp desconectado` | Reconectar → `/painel/configuracoes` |
| Teste | `Teste de mensagem da {banca}. O WhatsApp está funcionando.` | `[ {banca} ] Teste de e-mail` | nenhum |

## Saque parado

```text
⚠️ Saque aguardando há 30 minutos

Cliente: {nome}
Valor: {valor}
Chave Pix ({tipo}): {chave}

Confira e dê baixa no painel: {link}
```

O link leva à tela que exige login e nenhuma baixa é feita por ele.

## Regra de envio

1. `DisparoMensagens` lê o canal do tipo e o destinatário.
2. Grava uma linha por canal e despacha `EnviarMensagem` na fila do tipo.
3. O job confere se a mensagem ainda vale, envia e atualiza a linha.
4. Ao falhar de vez, aciona a reserva quando o tipo a tiver, sem encadear outra.

Telefone do cliente e do afiliado: `ddi` + `telefone`. Telefone do Admin: `telefone_whatsapp_admin`.
