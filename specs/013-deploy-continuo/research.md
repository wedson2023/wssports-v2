# Research: Deploy contínuo

**Feature**: `013-deploy-continuo` | **Date**: 2026-10-18 | **Spec**: [spec.md](spec.md)

Decisões R-01 a R-14. As escolhas de comportamento (onde compilar, pasta única, verificações,
usuário `deploy`, gatilhos, WhatsApp, cópia do banco, sem conferência do site depois do deploy,
remoção dos workflows herdados) já foram tomadas pelo responsável na spec (Clarifications).

---

## R-01 — Sistema antigo consultado (Princípio VII)

| Arquivo (`wssports.bet`) | O que faz |
|---|---|
| `.github/workflows/main.yml` | Push na `main` e disparo manual: monta imagem Docker, publica no Docker Hub e troca a imagem de cada cliente no Kubernetes da DigitalOcean (`kubectl set image`), um passo por domínio |
| `.github/workflows/dev.yml` | Mesmo fluxo para a branch `notificacao` |
| `init.sh`, `Dockerfile` | Inicialização do contêiner: PHP 7.3, Nginx, cron e `migrate --seed` a cada início |

Diferenças (aprovadas na spec): servidor único sem Docker/Kubernetes; verificação antes do deploy;
volta ao commit anterior em falha; aviso no WhatsApp; seeders nunca rodam no deploy.

## R-02 — Estrutura do pipeline: três jobs encadeados

- **Decisão**: um único workflow `.github/workflows/deploy.yml` com três jobs:
  1. `verificar` (sem acesso ao servidor, runner `ubuntu-24.04`, que já traz o PHP 8.3 do servidor):
     checkout, `php -l` em todos os `.php` fora de
     `vendor/` e `node_modules/`, Node 20, `npm ci`, `npm run build`; publica `public/build` como
     artefato da execução;
  2. `deploy` (`needs: verificar`): baixa o artefato, abre o SSH com a chave do segredo, envia o
     build para uma pasta temporária no servidor e roda o script de deploy;
  3. `avisar` (`needs: [verificar, deploy]`, `if: always()`): monta e manda a mensagem do WhatsApp
     com o resultado dos dois jobs.
- **Por quê**: separa "nada foi alterado" (falha em `verificar`) de "falhou no servidor" (falha em
  `deploy`), que é exatamente o que o aviso precisa dizer (FR-015).
- **Alternativas**: um job só (rejeitada: o aviso não saberia se o servidor foi tocado); actions de
  terceiros para SSH ou para instalar o PHP (rejeitada: `ssh`/`rsync` e o PHP do runner bastam, e a
  chave não passa por código de terceiros). Só actions oficiais do GitHub.

## R-03 — Gatilhos e concorrência

- **Decisão**: `on: push: branches: [main]` e `workflow_dispatch`. Bloco
  `concurrency: { group: deploy-ai-wssports, cancel-in-progress: false }`.
- **Efeito**: nunca dois deploys ao mesmo tempo e o que está rodando nunca é cancelado (FR-002). Com
  vários pushes enquanto um deploy roda, o GitHub mantém só o **mais recente** na fila; os
  intermediários são descartados, o que é seguro porque o mais recente já contém os anteriores
  (ponto deixado para o plano no `/speckit-clarify`).

## R-04 — Script de deploy versionado e enviado pela conexão

- **Decisão**: o passo a passo do servidor fica em `scripts/deploy/deploy.sh`, versionado no
  repositório, e é executado por `ssh deploy@servidor 'bash -s' -- <commit> < scripts/deploy/deploy.sh`.
- **Por quê**: o servidor sempre roda a versão do script do commit que está sendo publicado, sem
  precisar dele estar no servidor antes; mudanças no deploy passam por revisão como qualquer código.
- **Marcadores de passo**: o script imprime `::passo:: <nome>` ao começar cada passo e, ao falhar,
  `::falha:: <nome>` e `::volta:: ok|falhou`. O job `deploy` grava a saída e extrai esses marcadores
  para os `outputs` do job, usados pelo aviso.

## R-05 — Passos no servidor e volta ao commit anterior

Ordem (FR-005), com `set -Eeuo pipefail` e um `trap ERR` que dispara a volta:

| # | Passo (`::passo::`) | Comando principal |
|---|---|---|
| 1 | manutencao | `php artisan down --retry=15` |
| 2 | guardar_versao | grava o commit atual (`git rev-parse HEAD`) e copia `public/build` para `/var/www/wssports-deploy/build.anterior` |
| 3 | atualizar_codigo | `git fetch --prune origin` + `git reset --hard <commit>` (descarta mudanças locais; `.env` e `storage` são ignorados pelo git) |
| 4 | dependencias | `composer install --no-dev --optimize-autoloader --no-interaction` |
| 5 | frontend | troca `public/build` pelo build recebido em `/var/www/wssports-deploy/build-novo` |
| 6 | copia_banco | só se houver migration pendente (R-07) |
| 7 | migrations | `php artisan migrate --force` |
| 8 | caches | `php artisan optimize` (config, rotas, views e eventos) |
| 9 | fila | `sudo supervisorctl restart wssports-fila:*` |
| 10 | no_ar | `php artisan up` |

**Volta (FR-008)**: desfaz só os passos que já aconteceram (U2 do `/speckit-analyze`): sem commit
guardado ainda, apenas `php artisan up`; com ele, `git reset --hard <commit guardado>`, devolve
`/var/www/wssports-deploy/build.anterior` (se o frontend já tinha sido trocado),
`composer install` do commit antigo, `php artisan optimize`, reinicia a fila e `php artisan up`. O
`php artisan up` roda também num `trap EXIT` final, para o site nunca ficar em manutenção depois do
fim (FR-009), mesmo que a volta falhe.

## R-06 — Usuário `deploy` e permissões

- **Decisão** (feita uma vez, pelo script `scripts/deploy/server_setup.sh`, rodado como root):
  - usuário `deploy`, sem senha (`passwd -l`), shell `bash`, no grupo `www-data`;
  - `authorized_keys` só com a chave pública do pipeline;
  - `/var/www/wssports` passa a ser `deploy:www-data`;
  - `storage` e `bootstrap/cache` com ACL (`setfacl`, padrão e atual) dando leitura e escrita a
    `deploy` e a `www-data`: o PHP-FPM, a fila e o cron (`www-data`) e o deploy (`deploy`) escrevem
    nos mesmos logs e caches sem erro de permissão;
  - a deploy key do GitHub (só leitura) copiada para `/home/deploy/.ssh`;
  - `/etc/sudoers.d/deploy` com uma única linha:
    `deploy ALL=(root) NOPASSWD: /usr/bin/supervisorctl restart wssports-fila\:*` (FR-012);
  - pasta de cópias `/var/backups/wssports` (`deploy`, 700);
  - pasta de trabalho `/var/www/wssports-deploy` (`deploy`, 750), onde ficam `build-novo` (recebido do
    pipeline e recriado a cada deploy) e `build.anterior` (para a volta); fica fora de
    `/var/www/wssports/public`, então os arquivos antigos não são servidos pela web. Como `/var/www` é
    do root, o `deploy` não conseguiria recriar uma pasta direto ali depois do primeiro `mv`.
- **`.env`**: continua `root:www-data 640`; o `deploy` lê porque está no grupo `www-data`, e nunca o
  altera (FR-006).
- **Alternativas**: rodar o `artisan` como `www-data` via `sudo -u` (rejeitada: daria ao `deploy` todo
  o poder do usuário do servidor web); manter o root (rejeitada pelo responsável).

## R-07 — Cópia do banco antes das migrations (FR-005a)

- **Detecção**: `php artisan migrate:status --pending` (lista só as pendentes); com zero pendentes,
  não copia.
- **Cópia**: `mysqldump --single-transaction --no-tablespaces --routines` com as credenciais lidas do
  `.env` em tempo de execução (nunca impressas), compactada com `gzip`, em
  `/var/backups/wssports/wssports-v2_<AAAAMMDD-HHMMSS>_<commit7>.sql.gz`; mantém as 5 mais recentes.
  `--no-tablespaces` evita exigir o privilégio `PROCESS` do usuário do banco.
- **Falha da cópia**: aborta antes das migrations e dispara a volta.
- **Aviso**: o nome da cópia sai num marcador `::copia:: <arquivo>`, citado no aviso de falha.

## R-08 — Envio do frontend compilado

- **Decisão**: `rsync -az --delete public/build/ deploy@servidor:/var/www/wssports-deploy/build-novo/`
  antes do script (o `rsync` cria a subpasta, porque `/var/www/wssports-deploy` é do `deploy`); o passo
  5 move `public/build` para `build.anterior` e `build-novo` para `public/build` com `mv` (atômico na
  mesma partição).
- **Verificado**: o frontend não lê variáveis do `.env` na compilação (nenhum `import.meta.env`
  próprio), então o build do GitHub é igual ao do servidor. O `sw.js` do PWA sai dentro de
  `public/build` e é servido pela rota `sw.js` (`ArquivosPwaController`); a versão do build continua
  avisando os aparelhos (constituição, atualização sem limpar cache).

## R-09 — Verificação de sintaxe do PHP

- **Decisão**: `find app bootstrap config database routes -name '*.php' -print0 | xargs -0 -n1 -P4 php -l`
  com PHP 8.3 (o do servidor), falhando ao primeiro erro. Não é teste automatizado: só confere se o
  arquivo é PHP válido (FR-004 e constituição).

## R-10 — Aviso pelo CallMeBot

- **Montagem do texto**: em `scripts/deploy/notify.sh`, que recebe o resultado por variáveis de ambiente
  (`RESULTADO`, `COMMIT_CURTO`, `MENSAGEM_COMMIT`, `AUTOR`, `DURACAO`, `PASSO_FALHOU`, `VOLTA`, `COPIA`,
  `LINK_LOG`) e imprime a mensagem; dá para rodar localmente com valores de falha, sem quebrar a
  `main` (U3 do `/speckit-analyze`). O mesmo texto vai para o resumo da execução
  (`$GITHUB_STEP_SUMMARY`, FR-010).
- **Decisão**: no job `avisar`, `curl --get --max-time 30` em
  `https://api.callmebot.com/whatsapp.php` com `phone` (segredo `CALLMEBOT_TELEFONE`, formato
  `55DDNÚMERO`), `apikey` (segredo `CALLMEBOT_CHAVE`) e `text` codificado pelo próprio `curl`
  (`--data-urlencode`). Falha no envio vira só um aviso no log (`continue-on-error`), sem mudar o
  resultado (FR-017).
- **Ativação única** (guia): o responsável manda a mensagem de autorização indicada no site do
  CallMeBot para o número dele e recebe a chave por WhatsApp.
- **Formato** em [contracts/aviso-whatsapp.md](contracts/aviso-whatsapp.md).

## R-11 — Segredos do GitHub

| Segredo | Conteúdo |
|---|---|
| `DEPLOY_HOST` | `174.138.49.43` |
| `DEPLOY_USUARIO` | `deploy` |
| `DEPLOY_CHAVE_SSH` | chave privada exclusiva do pipeline (ed25519, sem senha) |
| `DEPLOY_KNOWN_HOSTS` | linha do `ssh-keyscan` do servidor, conferida pela impressão digital `SHA256:kYDRPMRmlUP5UQYETNOJ8iM1vfNn2ZRelRsdlg7WKJU` (FR-014) |
| `CALLMEBOT_TELEFONE` | número do responsável com DDI |
| `CALLMEBOT_CHAVE` | chave do CallMeBot |

Os segredos são cadastrados pelo `gh secret set` (se o GitHub CLI estiver autenticado na máquina do
responsável) ou pela tela do GitHub (guia). Nenhum valor do `.env` entra nos segredos (FR-013).

## R-12 — Correção do `PapeisPermissoesSeeder` (FR-018)

- **Causa**: o seeder só cria as permissões das specs 001 a 007; as de encerramento (010), cassino
  (011) e afiliados (012) são criadas pelos seeders próprios, que rodam depois de o Admin de exemplo
  receber `permissoes_padrao()` (que já inclui todas).
- **Decisão**: o `PapeisPermissoesSeeder` passa a criar também `PERMISSOES_ENCERRAMENTOS`,
  `PERMISSOES_CASSINO` e `PERMISSOES_AFILIADOS`. Os seeders próprios continuam distribuindo as
  permissões aos usuários existentes (idempotentes, `firstOrCreate`). Validação: `migrate:fresh
  --seed` num banco vazio.

## R-13 — Remoção dos workflows herdados (FR-019)

`.github/workflows/tests.yml`, `issues.yml`, `pull-requests.yml` e `update-changelog.yml` são
apagados (vieram do commit "Projeto base Laravel"). O `deploy.yml` fica sendo o único.

## R-14 — Dependências e custo

Nenhum pacote novo no Composer nem no npm. No servidor, só o pacote `acl` (para o `setfacl`). GitHub
Actions dentro da cota gratuita do repositório privado; CallMeBot gratuito.
