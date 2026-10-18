# Contrato: aviso no WhatsApp (CallMeBot)

Envio: `GET https://api.callmebot.com/whatsapp.php?phone=<CALLMEBOT_TELEFONE>&text=<texto>&apikey=<CALLMEBOT_CHAVE>`
(texto codificado para URL; tempo máximo de 30 s; falha no envio não muda o resultado do deploy).

## Sucesso

```text
✅ Deploy concluído - ai.wssports.bet
Commit: 1a2b3c4 - Ajusta o painel do afiliado
Autor: wedson2023
Duração: 2min 41s
```

## Falha na verificação (servidor intocado)

```text
❌ Deploy falhou - ai.wssports.bet
Passo: verificação (sintaxe do PHP ou compilação do frontend)
O servidor não foi alterado.
Commit: 1a2b3c4 - Ajusta o painel do afiliado
Log: https://github.com/wedson2023/wssports-v2/actions/runs/123456789
```

## Falha no servidor

```text
❌ Deploy falhou - ai.wssports.bet
Passo: migrations
Volta ao commit anterior: ok (site no ar com a versão anterior)
Cópia do banco antes das migrations: wssports-v2_20261018-101500_1a2b3c4.sql.gz
Atenção: alterações já aplicadas no banco não foram desfeitas.
Commit: 1a2b3c4 - Ajusta o painel do afiliado
Log: https://github.com/wedson2023/wssports-v2/actions/runs/123456789
```

- A linha "Cópia do banco" só aparece quando houve cópia; a linha "Atenção" só quando o passo é
  `migrations`.
- Com volta `falhou`: "Volta ao commit anterior: FALHOU - é preciso intervenção manual (ver guia)".
- Mensagem do commit: primeira linha, até 80 caracteres.
