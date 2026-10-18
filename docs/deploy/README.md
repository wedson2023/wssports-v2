# Deploy contínuo — ai.wssports.bet

Guia do pipeline de deploy do servidor de teste (spec 013).

## Como funciona

A cada push na `main` (ou por **Actions → Deploy → Run workflow**), o workflow
[`.github/workflows/deploy.yml`](../../.github/workflows/deploy.yml) roda três jobs:

1. **verificar** (no GitHub, sem tocar no servidor): confere a sintaxe de todos os `.php` e compila o
   frontend. Se falhar, nada é enviado ao servidor.
2. **deploy**: envia o frontend compilado para `/var/www/wssports-deploy/build-novo` e roda
   [`scripts/deploy/deploy.sh`](../../scripts/deploy/deploy.sh) no servidor, pelo usuário `deploy`.
   Os passos são:
   1. coloca o site em manutenção;
   2. guarda o commit atual;
   3. atualiza o código;
   4. roda `composer install --no-dev`;
   5. troca o `public/build`;
   6. faz uma cópia do banco, só se houver migration nova;
   7. roda as migrations;
   8. roda o `optimize`;
   9. reinicia a fila;
   10. tira o site da manutenção.
   
   Se qualquer passo falhar, o script volta ao commit anterior e tira o site da manutenção.
   **Migrations já aplicadas não são desfeitas.**
3. **avisar**: escreve o resultado no resumo da execução e manda no WhatsApp, pelo CallMeBot.

Um deploy roda por vez. Se chegarem vários pushes enquanto um deploy roda, só o mais recente entra
na fila. O GitHub continua mandando o e-mail de falha normalmente.

## Preparar um servidor do zero

Pré-requisitos no servidor:
- o sistema clonado em `/var/www/wssports`, com o `.env` configurado;
- a deploy key do GitHub em `/root/.ssh/github_wssports`;
- o Supervisor com o programa `wssports-fila`.

1. Na sua máquina, gere a chave exclusiva do pipeline (sem senha):

   ```bash
   ssh-keygen -t ed25519 -N "" -C pipeline-deploy-ai.wssports.bet -f ~/.ssh/wssports_pipeline
   ```

2. Rode a preparação como root, passando a chave **pública**:

   ```bash
   ssh root@<servidor> 'bash -s' -- "$(cat ~/.ssh/wssports_pipeline.pub)" < scripts/deploy/server_setup.sh
   ```

   O script pode ser repetido sem problema. Ele faz o seguinte:
   - cria o usuário `deploy` (sem senha e sem root);
   - ajusta o dono e a ACL de `storage` e `bootstrap/cache`;
   - libera no `sudo` só o `supervisorctl restart wssports-fila:*`;
   - cria as pastas `/var/www/wssports-deploy` e `/var/backups/wssports`.

3. Confira a impressão digital do servidor e gere a linha do `known_hosts`:

   ```bash
   ssh-keyscan -t ed25519 <servidor> > servidor.txt
   ssh-keygen -lf servidor.txt    # deve bater com: ssh root@<servidor> 'ssh-keygen -lf /etc/ssh/ssh_host_ed25519_key.pub'
   ```

## Segredos do GitHub

Cadastre-os em **Settings → Secrets and variables → Actions → New repository secret**.
Nenhum valor do `.env` vai para o GitHub.

| Segredo | Valor |
|---|---|
| `DEPLOY_HOST` | IP do servidor (`174.138.49.43`) |
| `DEPLOY_USUARIO` | `deploy` |
| `DEPLOY_CHAVE_SSH` | conteúdo inteiro de `~/.ssh/wssports_pipeline` (a chave **privada**) |
| `DEPLOY_KNOWN_HOSTS` | a linha do `servidor.txt` (impressão digital atual `SHA256:kYDRPMRmlUP5UQYETNOJ8iM1vfNn2ZRelRsdlg7WKJU`) |
| `CALLMEBOT_TELEFONE` | número com DDI, só dígitos (ex.: `5582999999999`) |
| `CALLMEBOT_CHAVE` | chave recebida do CallMeBot |

Se o servidor for recriado, a impressão digital muda e o deploy passa a recusar a conexão. Nesse
caso, atualize o `DEPLOY_KNOWN_HOSTS`.

## Ativar o CallMeBot (uma vez)

1. Salve nos contatos o número indicado em <https://www.callmebot.com/blog/free-api-whatsapp-messages/>.
2. Pelo seu WhatsApp, mande para esse número a mensagem de autorização indicada na mesma página.
3. Cadastre a chave que chegar por WhatsApp em `CALLMEBOT_CHAVE` e o seu número em
   `CALLMEBOT_TELEFONE`.

Sem esses segredos, o deploy roda normalmente e só registra um aviso no log. Uma falha no envio
também não muda o resultado do deploy.

## Voltar manualmente a uma versão

O caminho preferido é reverter na `main` (`git revert <commit>` + push). O pipeline publica a
reversão.

Para publicar um commit específico direto no servidor, sem o pipeline:

```bash
# o frontend desse commit precisa estar em build-novo: compile localmente e envie
git checkout <commit> && npm ci && npm run build
rsync -az --delete -e "ssh -i ~/.ssh/wssports_pipeline" public/build/ deploy@<servidor>:/var/www/wssports-deploy/build-novo/
ssh -i ~/.ssh/wssports_pipeline deploy@<servidor> 'bash -s' -- "$(git rev-parse HEAD)" < scripts/deploy/deploy.sh
```

Se o aviso disser **"Volta ao commit anterior: FALHOU"**, entre como root e confira:
- `git -C /var/www/wssports log -1`;
- `php artisan up`;
- `supervisorctl status`.

Depois repita o comando acima com um commit que funcione.

## Restaurar uma cópia do banco

As cópias são feitas só quando há migration nova. Ficam em `/var/backups/wssports`, com as 5 mais
recentes. Cada nome tem a data e o commit: `wssports-v2_<AAAAMMDD-HHMMSS>_<commit7>.sql.gz`.

> ⚠️ Restaurar apaga tudo o que foi gravado no banco depois da cópia (apostas, depósitos, cadastros).

```bash
ssh root@<servidor>
cd /var/www/wssports && php artisan down
gunzip -c /var/backups/wssports/<arquivo>.sql.gz | mysql -u <usuário do .env> -p wssports-v2
php artisan up
```

Depois de restaurar, publique o commit correspondente à cópia (seção anterior). Assim o código fica
igual ao banco.
