#!/usr/bin/env bash
# Deploy do sistema no servidor (spec 013, research R-04, R-05 e R-07; contracts/pipeline §2).
# Roda como o usuário deploy, enviado pela conexão SSH pelo pipeline:
#   ssh deploy@servidor 'bash -s' -- <commit de 40 caracteres> < scripts/deploy/deploy.sh
# Antes, o pipeline envia o frontend compilado para /var/www/wssports-deploy/build-novo.
# Imprime marcadores (::passo::, ::copia::, ::falha::, ::volta::, ::sucesso::) lidos pelo pipeline.
# Nunca imprime valores do .env, nunca altera o .env nem roda seeders e sempre termina com o site
# fora da manutenção; em qualquer falha volta ao commit anterior.
set -Eeuo pipefail

readonly PASTA_SISTEMA=/var/www/wssports
readonly PASTA_TRABALHO=/var/www/wssports-deploy
readonly BUILD_NOVO="$PASTA_TRABALHO/build-novo"
readonly BUILD_ANTERIOR="$PASTA_TRABALHO/build.anterior"
readonly PASTA_COPIAS=/var/backups/wssports
readonly COPIAS_GUARDADAS=5
readonly PROGRAMA_FILA='wssports-fila:*'

commit_novo="${1:-}"

if [[ ! "$commit_novo" =~ ^[0-9a-f]{40}$ ]]; then
    echo "::falha:: argumento (informe o commit completo, com 40 caracteres)"
    exit 2
fi

cd "$PASTA_SISTEMA"

# estado usado pela volta: só desfaz o que já aconteceu (U2 do /speckit-analyze)
passo_atual=inicio
commit_anterior=''
frontend_trocado=0
inicio_manutencao=0

passo() {
    passo_atual="$1"
    echo "::passo:: $1"
}

artisan() {
    php artisan "$@" --no-interaction
}

instalar_dependencias() {
    composer install --no-dev --optimize-autoloader --no-interaction --no-progress
}

reiniciar_fila() {
    sudo -n supervisorctl restart "$PROGRAMA_FILA" >/dev/null
}

# valor de uma chave do .env, sem aspas; nunca é impresso
valor_env() {
    grep -m1 "^$1=" .env | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//"
}

copiar_banco() {
    local pendentes
    pendentes="$(artisan migrate:status --pending --no-ansi)"

    if ! grep -q 'Pending' <<< "$pendentes"; then
        echo "Sem migration nova: cópia do banco dispensada."
        return 0
    fi

    local arquivo
    arquivo="$PASTA_COPIAS/$(valor_env DB_DATABASE)_$(date -u +%Y%m%d-%H%M%S)_${commit_novo:0:7}.sql.gz"

    # a senha vai pela variável de ambiente, nunca pela linha de comando
    MYSQL_PWD="$(valor_env DB_PASSWORD)" mysqldump \
        --host="$(valor_env DB_HOST)" --port="$(valor_env DB_PORT)" --user="$(valor_env DB_USERNAME)" \
        --single-transaction --no-tablespaces --routines --set-gtid-purged=OFF \
        "$(valor_env DB_DATABASE)" | gzip > "$arquivo.tmp"
    mv "$arquivo.tmp" "$arquivo"
    chmod 600 "$arquivo"
    echo "::copia:: $(basename "$arquivo")"

    # guarda só as cópias mais recentes
    find "$PASTA_COPIAS" -maxdepth 1 -name '*.sql.gz' -printf '%T@ %p\n' \
        | sort -rn | tail -n +$((COPIAS_GUARDADAS + 1)) | cut -d' ' -f2- | xargs -r rm -f
}

voltar_versao() {
    local resultado=0

    if [[ -n "$commit_anterior" ]]; then
        git reset --quiet --hard "$commit_anterior" || resultado=1

        if (( frontend_trocado )); then
            rm -rf public/build && cp -a "$BUILD_ANTERIOR" public/build || resultado=1
        fi

        instalar_dependencias || resultado=1
        artisan optimize >/dev/null || resultado=1
        reiniciar_fila || resultado=1
    fi

    artisan up >/dev/null || resultado=1

    return "$resultado"
}

ao_falhar() {
    local codigo=$?
    trap - ERR
    set +e

    echo "::falha:: $passo_atual"

    if voltar_versao; then
        echo "::volta:: ok"
    else
        echo "::volta:: falhou"
    fi

    exit $(( codigo == 0 ? 1 : codigo ))
}

# o site nunca fica em manutenção depois do fim, nem se a volta falhar (FR-009)
ao_sair() {
    php artisan up --no-interaction >/dev/null 2>&1 || true
}

trap ao_falhar ERR
trap ao_sair EXIT

passo manutencao
artisan down --retry=15 >/dev/null
inicio_manutencao=$SECONDS

passo guardar_versao
commit_anterior="$(git rev-parse HEAD)"
rm -rf "$BUILD_ANTERIOR"
cp -a public/build "$BUILD_ANTERIOR"

passo atualizar_codigo
git fetch --quiet --prune origin
git cat-file -e "${commit_novo}^{commit}"
git reset --quiet --hard "$commit_novo"

passo dependencias
instalar_dependencias

passo frontend
[[ -d "$BUILD_NOVO" ]]
rm -rf public/build
mv "$BUILD_NOVO" public/build
frontend_trocado=1

passo copia_banco
copiar_banco

passo migrations
artisan migrate --force

passo caches
artisan optimize >/dev/null

passo fila
reiniciar_fila

passo no_ar
artisan up >/dev/null

echo "Site em manutenção por $((SECONDS - inicio_manutencao)) s."
echo "::sucesso:: ${commit_novo:0:7}"
