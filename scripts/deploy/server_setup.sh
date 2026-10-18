#!/usr/bin/env bash
# Preparação única do servidor para o deploy contínuo (spec 013, research R-06).
# Rodar como root, uma vez (pode repetir sem problema):
#   bash server_setup.sh "<chave pública do pipeline>"
# Cria o usuário deploy (sem senha e sem root), ajusta dono e ACL da pasta do sistema, libera só o
# restart da fila no sudo e cria as pastas de trabalho e de cópias do banco.
set -Eeuo pipefail

readonly PASTA_SISTEMA=/var/www/wssports
readonly PASTA_TRABALHO=/var/www/wssports-deploy
readonly PASTA_COPIAS=/var/backups/wssports
readonly USUARIO=deploy
readonly CHAVE_GITHUB=/root/.ssh/github_wssports

informar() { printf '\n==> %s\n' "$1"; }

chave_publica="${1:-}"

if [[ $EUID -ne 0 ]]; then
    echo "Rode como root." >&2
    exit 1
fi

if [[ ! "$chave_publica" =~ ^ssh-(ed25519|rsa)\  ]]; then
    echo "Informe a chave pública do pipeline como primeiro argumento." >&2
    exit 1
fi

if [[ ! -d "$PASTA_SISTEMA/.git" ]]; then
    echo "Não encontrei o repositório em $PASTA_SISTEMA." >&2
    exit 1
fi

informar "Instalando o pacote acl"
DEBIAN_FRONTEND=noninteractive apt-get install -y -q acl >/dev/null

informar "Usuário $USUARIO (sem senha, grupo www-data)"
if ! id "$USUARIO" >/dev/null 2>&1; then
    useradd --create-home --shell /bin/bash "$USUARIO"
fi
usermod --append --groups www-data "$USUARIO"
passwd --lock "$USUARIO" >/dev/null

informar "Chave do pipeline em authorized_keys (só ela)"
install -d -m 700 -o "$USUARIO" -g "$USUARIO" "/home/$USUARIO/.ssh"
printf '%s\n' "$chave_publica" > "/home/$USUARIO/.ssh/authorized_keys"
chown "$USUARIO:$USUARIO" "/home/$USUARIO/.ssh/authorized_keys"
chmod 600 "/home/$USUARIO/.ssh/authorized_keys"

informar "Acesso de leitura ao GitHub (deploy key do servidor)"
if [[ ! -f "$CHAVE_GITHUB" ]]; then
    echo "Não encontrei a deploy key do GitHub em $CHAVE_GITHUB." >&2
    exit 1
fi
install -m 600 -o "$USUARIO" -g "$USUARIO" "$CHAVE_GITHUB" "/home/$USUARIO/.ssh/github_wssports"
printf 'Host github.com\n    IdentityFile ~/.ssh/github_wssports\n    IdentitiesOnly yes\n' > "/home/$USUARIO/.ssh/config"
ssh-keyscan -t ed25519 github.com 2>/dev/null > "/home/$USUARIO/.ssh/known_hosts"
chown "$USUARIO:$USUARIO" "/home/$USUARIO/.ssh/config" "/home/$USUARIO/.ssh/known_hosts"
chmod 600 "/home/$USUARIO/.ssh/config" "/home/$USUARIO/.ssh/known_hosts"

informar "Dono e permissões de $PASTA_SISTEMA"
chown -R "$USUARIO:www-data" "$PASTA_SISTEMA"
# o .env continua do root, legível só pelo grupo do servidor web (o deploy lê pelo grupo)
chown root:www-data "$PASTA_SISTEMA/.env"
chmod 640 "$PASTA_SISTEMA/.env"
# storage e bootstrap/cache: deploy e www-data escrevem nos mesmos logs e caches
for pasta in storage bootstrap/cache; do
    setfacl -R -m "u:$USUARIO:rwX,u:www-data:rwX" "$PASTA_SISTEMA/$pasta"
    setfacl -dR -m "u:$USUARIO:rwX,u:www-data:rwX" "$PASTA_SISTEMA/$pasta"
done

informar "Pastas de trabalho e de cópias do banco"
install -d -m 750 -o "$USUARIO" -g "$USUARIO" "$PASTA_TRABALHO"
install -d -m 700 -o "$USUARIO" -g "$USUARIO" "$PASTA_COPIAS"

informar "Sudo: só reiniciar a fila"
arquivo_sudo=/etc/sudoers.d/deploy
printf '%s ALL=(root) NOPASSWD: /usr/bin/supervisorctl restart wssports-fila\\:*\n' "$USUARIO" > "$arquivo_sudo.tmp"
chmod 440 "$arquivo_sudo.tmp"
visudo -cf "$arquivo_sudo.tmp" >/dev/null
mv "$arquivo_sudo.tmp" "$arquivo_sudo"

informar "Pronto"
echo "Usuário $USUARIO configurado; dono de $PASTA_SISTEMA; restart da fila liberado no sudo."
