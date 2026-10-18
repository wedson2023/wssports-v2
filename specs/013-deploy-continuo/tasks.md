---

description: "Lista de tarefas da feature Deploy contínuo"
---

# Tasks: Deploy contínuo

**Input**: Documentos de design em `specs/013-deploy-continuo/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md),
[data-model.md](data-model.md), [contracts/pipeline.md](contracts/pipeline.md),
[contracts/aviso-whatsapp.md](contracts/aviso-whatsapp.md), [quickstart.md](quickstart.md)

**Tests**: NÃO há tarefas de teste: a constituição proíbe testes automatizados. A validação é manual,
pelo [quickstart.md](quickstart.md).

**Organization**: tarefas por user story, na ordem da spec: US1 a US3 (P1), US4 a US6 (P2), US7
(P3). O acesso restrito (US6) é pré-requisito do deploy, então a preparação do servidor e da chave
fica na fase Foundational; a US6 fecha com a validação de segurança.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: pode rodar em paralelo (arquivos diferentes, sem dependências pendentes)
- **[Story]**: user story da tarefa (US1 a US7)
- Caminhos relativos à raiz do repositório

## Regras que valem para TODAS as tarefas

- **Idioma e nomes (Princípios I e II)**: comentários, mensagens, marcadores e o guia em português;
  funções e variáveis dos scripts em `snake_case` português; pastas e nomes de arquivo de script em
  inglês (`scripts/deploy/deploy.sh`, `scripts/deploy/server_setup.sh`).
- **Escopo (Princípio IV)**: arquivos existentes só os da tabela do [plan.md](plan.md)
  (`PapeisPermissoesSeeder` e os 4 workflows removidos). Nenhum outro arquivo existente muda.
- **Segredos**: nenhum valor do `.env` em repositório, segredos do GitHub ou logs. Nos scripts, nunca
  `set -x`; a senha do banco vai para o `mysqldump` pela variável `MYSQL_PWD`, nunca pela linha de
  comando.
- **Servidor**: acesso administrativo pelo atalho `ssh wssports-teste` (root, chave da sessão de
  configuração); o pipeline usa só o usuário `deploy`.
- **Legibilidade (Princípio V)**: ao concluir cada tarefa, revisar o que mudou e informar se houve
  refatoração.

---

## Phase 1: Setup

**Purpose**: limpeza dos workflows herdados e pastas novas.

- [ ] T001 [P] Remover `.github/workflows/tests.yml`, `.github/workflows/issues.yml`, `.github/workflows/pull-requests.yml` e `.github/workflows/update-changelog.yml` (FR-019, R-13)
- [ ] T002 [P] Criar as pastas `scripts/deploy/` e `docs/deploy/` (vazias até as próximas tarefas)

---

## Phase 2: Foundational (servidor, chave e esqueleto do workflow)

**Purpose**: o pipeline precisa do usuário `deploy`, da chave e do build antes de qualquer deploy.

**⚠️ CRITICAL**: nenhuma user story começa antes desta fase.

- [ ] T003 Criar `scripts/deploy/server_setup.sh` (contracts/pipeline §3, R-06), idempotente, rodado como root com a chave pública do pipeline como argumento: `apt-get install -y acl`; usuário `deploy` (`useradd -m -s /bin/bash`, `passwd -l`, grupo extra `www-data`); `/home/deploy/.ssh/authorized_keys` só com a chave recebida (700/600); `chown -R deploy:www-data /var/www/wssports` mantendo o `.env` como `root:www-data 640`; `setfacl -R -m u:deploy:rwX,u:www-data:rwX` e `setfacl -dR -m u:deploy:rwX,u:www-data:rwX` em `storage` e `bootstrap/cache`; cópia da deploy key do GitHub (`/root/.ssh/github_wssports`) e do `config`/`known_hosts` de `github.com` para `/home/deploy/.ssh`; `/etc/sudoers.d/deploy` com a única linha `deploy ALL=(root) NOPASSWD: /usr/bin/supervisorctl restart wssports-fila\:*`, modo 440, validado com `visudo -cf`; pastas `/var/backups/wssports` (`deploy`, 700) e `/var/www/wssports-deploy` (`deploy`, 750; pasta de trabalho onde o deploy cria `build-novo` e `build.anterior`, fora da pasta pública, porque o `deploy` não pode criar pastas direto em `/var/www`); mensagens em português em cada etapa
- [ ] T004 Gerar na máquina do responsável o par de chaves exclusivo do pipeline `~/.ssh/wssports_pipeline` (ed25519, sem senha, comentário `pipeline-deploy-ai.wssports.bet`), fora do repositório
- [ ] T005 Rodar o `server_setup.sh` no servidor (`ssh wssports-teste 'bash -s' -- "<chave pública>" < scripts/deploy/server_setup.sh`) e conferir com a chave do pipeline: entra como `deploy`; `git -C /var/www/wssports fetch` funciona; `sudo -n supervisorctl restart 'wssports-fila:*'` funciona; `sudo -n ls /root` é recusado; `deploy` escreve em `storage/logs` e `www-data` continua escrevendo
- [ ] T006 Cadastrar no GitHub (repositório `wedson2023/wssports-v2`) os segredos `DEPLOY_HOST` (`174.138.49.43`), `DEPLOY_USUARIO` (`deploy`), `DEPLOY_CHAVE_SSH` (privada da T004) e `DEPLOY_KNOWN_HOSTS` (linha `ssh-keyscan -t ed25519` do servidor, conferida pela impressão digital `SHA256:kYDRPMRmlUP5UQYETNOJ8iM1vfNn2ZRelRsdlg7WKJU`), por `gh secret set` se o GitHub CLI estiver autenticado, senão pedindo ao responsável pela tela (R-11)
- [ ] T007 Criar `.github/workflows/deploy.yml` (contracts/pipeline §1) com `name: Deploy`, `on: push: branches: [main]` + `workflow_dispatch`, `concurrency: { group: deploy-ai-wssports, cancel-in-progress: false }`, `permissions: contents: read` e o job `verificar` em `runs-on: ubuntu-24.04`: `actions/checkout`, `actions/setup-node` (Node 20, cache npm), `npm ci`, `npm run build`, `actions/upload-artifact` de `public/build` (nome `build`, retenção 1 dia); outputs `mensagem_commit` (primeira linha de `git log -1 --format=%s`, até 80 caracteres), `commit_curto` e `inicio` (horário em segundos), usados pelos jobs seguintes

**Checkpoint**: usuário `deploy` funcionando e o workflow compila o frontend no GitHub.

---

## Phase 3: User Story 1 - Deploy automático a cada push na main (Priority: P1) 🎯 MVP

**Goal**: push na `main` atualiza o servidor sozinho.

**Independent Test**: quickstart §1 e §2.

- [ ] T008 [US1] Criar `scripts/deploy/deploy.sh` (contracts/pipeline §2, R-05) com o caminho de sucesso: `set -Eeuo pipefail`; valida o argumento (40 caracteres hexadecimais, senão sai com erro antes de qualquer passo); função `passo` que guarda o passo atual e imprime `::passo:: <nome>`; passos `manutencao` (`php artisan down --retry=15`), `guardar_versao` (commit atual em variável e cópia de `public/build` para `/var/www/wssports-deploy/build.anterior`, substituindo a anterior), `atualizar_codigo` (`git fetch --prune origin` + `git reset --hard <commit>`), `dependencias` (`composer install --no-dev --optimize-autoloader --no-interaction --no-progress`), `frontend` (`mv public/build` para fora e `mv /var/www/wssports-deploy/build-novo public/build`; a pasta `build-novo` é recriada pelo `rsync` do próximo deploy), `migrations` (`php artisan migrate --force`), `caches` (`php artisan optimize`), `fila` (`sudo -n supervisorctl restart 'wssports-fila:*'`), `no_ar` (`php artisan up`) e `::sucesso:: <commit7>`; nunca roda seeders nem toca o `.env`
- [ ] T009 [US1] No `.github/workflows/deploy.yml`, job `deploy` (`needs: verificar`, `ubuntu-24.04`): `actions/download-artifact` do `build`; grava `DEPLOY_CHAVE_SSH` em arquivo 600 e `DEPLOY_KNOWN_HOSTS` em `~/.ssh/known_hosts` (`StrictHostKeyChecking=yes`, FR-014); `rsync -az --delete` do build para `/var/www/wssports-deploy/build-novo/`; `actions/checkout` para ter o script; `ssh $DEPLOY_USUARIO@$DEPLOY_HOST 'bash -s' -- "$GITHUB_SHA" < scripts/deploy/deploy.sh 2>&1 | tee saida.log` preservando o código de saída (`set -o pipefail`); passo final `if: always()` que extrai de `saida.log` os marcadores `::falha::`, `::volta::` e `::copia::` para os outputs `passo_falhou`, `volta` e `copia`; apaga a chave do disco no fim (`if: always()`)
- [ ] T010 [US1] **Só depois da T011, T013 e T014** (o primeiro deploy real já sai com verificação e volta): commitar, fazer merge na `main` e validar o primeiro deploy real pelo quickstart §1 (site com o commit novo, manutenção de segundos, fila rodando, `/var/www/wssports-deploy/build.anterior` criado); fazer um segundo push logo depois para confirmar que o `build-novo` é recriado e §2 (push em outra branch não dispara)

**Checkpoint**: deploy automático funcionando (MVP).

---

## Phase 4: User Story 2 - Verificações barram código quebrado (Priority: P1)

**Goal**: erro de sintaxe ou de build para antes do servidor.

**Independent Test**: quickstart §3.

- [ ] T011 [US2] No job `verificar` do `.github/workflows/deploy.yml`, antes do Node, passo "Sintaxe do PHP" com `find app bootstrap config database routes -name '*.php' -print0 | xargs -0 -n1 -P4 php -l > /dev/null` (R-09), falhando ao primeiro erro; conferir que `php -v` do runner é 8.3
- [ ] T012 [US2] Validar o bloqueio sem quebrar a `main`: numa branch temporária, um `.php` com erro de sintaxe; rodar localmente o mesmo comando da T011 (falha) e conferir no YAML que `deploy` tem `needs: verificar`; apagar a branch

**Checkpoint**: só código válido chega ao servidor.

---

## Phase 5: User Story 3 - Falha no meio do deploy não deixa o site quebrado (Priority: P1)

**Goal**: volta ao commit anterior e site sempre fora da manutenção.

**Independent Test**: quickstart §4.

- [ ] T013 [US3] No `scripts/deploy/deploy.sh`, a volta (FR-008, R-05): `trap` de `ERR` que imprime `::falha:: <passo atual>` e chama `voltar_versao`, que desfaz só o que já aconteceu: sem commit guardado (falha em `manutencao` ou `guardar_versao`), não mexe no código; com ele, `git reset --hard <commit guardado>`; devolve `/var/www/wssports-deploy/build.anterior` para `public/build` só se o passo `frontend` já tinha trocado as pastas; `composer install --no-dev --optimize-autoloader --no-interaction`, `php artisan optimize`, `sudo -n supervisorctl restart 'wssports-fila:*'` e imprime `::volta:: ok` ou `::volta:: falhou`; `trap` de `EXIT` que sempre roda `php artisan up` (FR-009); sai com código diferente de 0 em qualquer falha
- [ ] T014 [US3] No `scripts/deploy/deploy.sh`, passo `copia_banco` antes das `migrations` (FR-005a, R-07): se `php artisan migrate:status --pending` listar pendentes, lê `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` e `DB_PASSWORD` do `.env` sem imprimir, roda `MYSQL_PWD=... mysqldump --single-transaction --no-tablespaces --routines` com `gzip` para `/var/backups/wssports/wssports-v2_<AAAAMMDD-HHMMSS>_<commit7>.sql.gz` (600), imprime `::copia:: <arquivo>` e apaga as cópias além das 5 mais recentes; sem pendentes, não copia; falha na cópia dispara a volta antes das migrations
- [ ] T015 [US3] Validar a volta sem quebrar a `main` (quickstart §4): numa branch temporária com uma migration que falha, publicar esse commit rodando o `deploy.sh` direto pelo usuário `deploy` (`ssh ... 'bash -s' -- <commit da branch> < scripts/deploy/deploy.sh`), conferir a cópia criada, o `::falha:: migrations`, o `::volta:: ok`, o site no commit anterior e fora da manutenção; depois rodar de novo com o commit da `main` e apagar a branch

**Checkpoint**: falha no servidor não derruba o site.

---

## Phase 6: User Story 4 - Aviso no WhatsApp (Priority: P2)

**Goal**: mensagem com o resultado de cada deploy.

**Independent Test**: um deploy com sucesso e uma falha simulada geram as mensagens do contrato.

- [ ] T016 [US4] No `.github/workflows/deploy.yml`, job `avisar` (`needs: [verificar, deploy]`, `if: always()`, `ubuntu-24.04`): define o resultado (`sucesso`, `falha_verificacao` ou `falha_servidor`) pelos `needs.*.result`; monta o texto chamando `scripts/deploy/notify.sh` (T016a) com `commit_curto`, `mensagem_commit`, `github.actor`, duração (agora − `inicio`, em "Xmin Ys"), `passo_falhou`, `volta`, `copia` e o link `https://github.com/${{ github.repository }}/actions/runs/${{ github.run_id }}`; grava o mesmo texto no resumo da execução (`$GITHUB_STEP_SUMMARY`, FR-010); envia com `curl --get --max-time 30 --data-urlencode "text=..."` para `https://api.callmebot.com/whatsapp.php` com `phone` e `apikey` dos segredos; passo com `continue-on-error: true` e aviso no log se falhar (FR-017); se os segredos do CallMeBot não existirem, pula o envio com aviso
- [ ] T016a [US4] Criar `scripts/deploy/notify.sh` (R-10): lê `RESULTADO` (`sucesso`, `falha_verificacao` ou `falha_servidor`), `COMMIT_CURTO`, `MENSAGEM_COMMIT`, `AUTOR`, `DURACAO`, `PASSO_FALHOU`, `VOLTA`, `COPIA` e `LINK_LOG` do ambiente e imprime exatamente os textos de [contracts/aviso-whatsapp.md](contracts/aviso-whatsapp.md) (linha "Cópia do banco" só com `COPIA`; linha "Atenção" só com passo `migrations`; volta `falhou` com o texto de intervenção manual)
- [ ] T017 [US4] Pedir ao responsável a ativação do CallMeBot (mensagem de autorização indicada no site do CallMeBot, a partir do WhatsApp dele) e cadastrar os segredos `CALLMEBOT_TELEFONE` (`5582981761793`) e `CALLMEBOT_CHAVE` (R-10)
- [ ] T018 [US4] Validar: deploy com sucesso gera a mensagem "✅ Deploy concluído" e o resumo da execução; rodar o `notify.sh` localmente com os valores da falha da T015 (`RESULTADO=falha_servidor PASSO_FALHOU=migrations VOLTA=ok COPIA=<arquivo> ...`) e com `falha_verificacao`, conferindo os textos com o contrato, sem precisar quebrar a `main`

**Checkpoint**: responsável avisado de todo deploy.

---

## Phase 7: User Story 5 - Disparo manual (Priority: P2)

**Goal**: rodar o deploy da `main` pelo GitHub.

**Independent Test**: quickstart §5.

- [ ] T019 [US5] Validar o `workflow_dispatch` da T007: Actions → Deploy → "Run workflow" na `main`; conferir que `mensagem_commit` vem do `git log` (não há `head_commit` no disparo manual) e que deploy e aviso saem normais

---

## Phase 8: User Story 6 - Acesso restrito do pipeline ao servidor (Priority: P2)

**Goal**: pipeline sem root e sem segredos expostos.

**Independent Test**: quickstart §6 e o checklist de acesso da T005.

- [ ] T020 [US6] Validar a segurança: com a chave do pipeline, `sudo -n` só aceita o restart da fila; procurar no repositório (`git grep`) e nos logs das execuções por valores do `.env` do servidor (senha do banco, `JWT_SECRET`, chaves do Mercado Pago e do cassino) e não encontrar nada; conferir que o `known_hosts` recusa conexão se a impressão digital mudar

---

## Phase 9: User Story 7 - Instalação num banco novo sem erro (Priority: P3)

**Goal**: `migrate:fresh --seed` funciona num banco vazio.

**Independent Test**: quickstart §7.

- [ ] T021 [US7] ⚠️ Em `database/seeders/PapeisPermissoesSeeder.php`, incluir `...Funcao::PERMISSOES_ENCERRAMENTOS`, `...Funcao::PERMISSOES_CASSINO` e `...Funcao::PERMISSOES_AFILIADOS` na lista de permissões criadas (FR-018, R-12) e atualizar o comentário da classe; sem outra mudança
- [ ] T022 [US7] Validar localmente num banco temporário (`wssports_teste_seed`, criado e apagado na tarefa, sem tocar no banco de desenvolvimento): `DB_DATABASE=wssports_teste_seed php artisan migrate:fresh --seed` termina sem erro e o Admin tem todas as permissões de `Funcao::Admin->permissoes_padrao()`

---

## Phase 10: Polish & Cross-Cutting Concerns

- [ ] T023 Criar `docs/deploy/README.md` (FR-020): como funciona o pipeline; preparar um servidor do zero (`server_setup.sh`); gerar a chave e cadastrar os 6 segredos; ativar o CallMeBot; voltar manualmente a uma versão (`git reset --hard <commit>` + passos do `deploy.sh`, ou disparo manual depois de reverter na `main`); restaurar uma cópia do banco (`gunzip -c arquivo.sql.gz | mysql ...`) com aviso de que apaga dados mais novos
- [ ] T024 Revisão de legibilidade final (Princípio V) do `deploy.sh`, do `server_setup.sh` e do `deploy.yml`
- [ ] T025 Rodar o roteiro completo do [quickstart.md](quickstart.md) (§1 a §9) e registrar o resultado no fim deste arquivo

---

## Dependencies & Execution Order

- **Setup (Phase 1)**: sem dependências.
- **Foundational (Phase 2)**: T003 → T005 (precisa da T004); T006 depende da T004 e da T005; T007
  independente. Bloqueia todas as stories.
- **US1**: depende da Foundational; T008 → T009 → T010.
- **US2**: depende da T007; pode ser feita antes da T010 (melhor: entra junto no primeiro deploy).
- **US3**: depende da T008; T013 e T014 alteram o mesmo arquivo (sequenciais); T015 depois delas.
- **US4**: depende da T009 (outputs); T017 precisa do responsável.
- **US5**: depende da US1 (e da US4 para conferir o aviso).
- **US6**: depende da US1 e da US4 (logs a conferir).
- **US7**: independente das demais (só o seeder).
- **Polish**: depois de todas.

### Parallel Opportunities

- T001 e T002; T004 e T007; T021–T022 (US7) em paralelo com qualquer fase depois da Setup.

---

## Implementation Strategy

### MVP (US1)

1. Setup + Foundational (servidor preparado, chave, segredos, build no GitHub).
2. US1 + US2 + US3 juntas antes do primeiro push real: o primeiro deploy já sai verificado e com
   volta (a pasta é a mesma do site no ar).
3. Validar pelo quickstart §1 a §4.

### Depois

4. US4 (aviso) assim que o CallMeBot estiver ativado; US5 e US6 são validações; US7 a qualquer
   momento.

---

## Notes

- Sem testes automatizados; validação pelo quickstart.
- Commit só quando o responsável pedir (`/speckit-git-commit`).
- O primeiro deploy pelo pipeline troca as dependências do PHP do servidor para as de produção (o
  Faker sai; os seeders de exemplo só rodam na instalação do zero).
