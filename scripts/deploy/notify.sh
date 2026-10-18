#!/usr/bin/env bash
# Texto do aviso do deploy no WhatsApp e no resumo da execução (spec 013, research R-10;
# contracts/aviso-whatsapp.md). Só imprime a mensagem; quem envia é o job avisar do pipeline.
# Lê do ambiente:
#   RESULTADO        sucesso, falha_verificacao ou falha_servidor
#   COMMIT_CURTO     commit com 7 caracteres
#   MENSAGEM_COMMIT  primeira linha da mensagem do commit
#   AUTOR            usuário do GitHub que publicou
#   DURACAO          duração da execução (ex.: 2min 41s)
#   PASSO_FALHOU     passo do servidor que falhou
#   VOLTA            ok ou falhou
#   COPIA            nome da cópia do banco (vazio quando não houve)
#   LINK_LOG         link da execução no GitHub
# Exemplo local:
#   RESULTADO=falha_servidor PASSO_FALHOU=migrations VOLTA=ok COMMIT_CURTO=1a2b3c4 bash notify.sh
set -Eeuo pipefail

readonly SITE=ai.wssports.bet

resultado="${RESULTADO:-}"
mensagem_commit="${MENSAGEM_COMMIT:-}"
linha_commit="Commit: ${COMMIT_CURTO:-?} - ${mensagem_commit:0:80}"
linha_log="Log: ${LINK_LOG:-}"

case "$resultado" in
    sucesso)
        echo "✅ Deploy concluído - $SITE"
        echo "$linha_commit"
        echo "Autor: ${AUTOR:-}"
        echo "Duração: ${DURACAO:-}"
        ;;

    falha_verificacao)
        echo "❌ Deploy falhou - $SITE"
        echo "Passo: verificação (sintaxe do PHP ou compilação do frontend)"
        echo "O servidor não foi alterado."
        echo "$linha_commit"
        echo "$linha_log"
        ;;

    falha_servidor)
        echo "❌ Deploy falhou - $SITE"
        echo "Passo: ${PASSO_FALHOU:-conexão ou envio ao servidor}"

        if [[ "${VOLTA:-}" == ok ]]; then
            echo "Volta ao commit anterior: ok (site no ar com a versão anterior)"
        elif [[ -z "${VOLTA:-}" ]]; then
            # falha antes do script do servidor (conexão ou envio do frontend)
            echo "Volta ao commit anterior: não foi necessária (o script do servidor não chegou a rodar; ver log)"
        else
            echo "Volta ao commit anterior: FALHOU - é preciso intervenção manual (ver guia)"
        fi

        if [[ -n "${COPIA:-}" ]]; then
            echo "Cópia do banco antes das migrations: $COPIA"
        fi

        if [[ "${PASSO_FALHOU:-}" == migrations ]]; then
            echo "Atenção: alterações já aplicadas no banco não foram desfeitas."
        fi

        echo "$linha_commit"
        echo "$linha_log"
        ;;

    *)
        echo "RESULTADO inválido: '$resultado' (use sucesso, falha_verificacao ou falha_servidor)." >&2
        exit 1
        ;;
esac
