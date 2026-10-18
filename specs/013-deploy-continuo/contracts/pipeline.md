# Contrato: pipeline de deploy

## 1. Workflow `.github/workflows/deploy.yml`

| Item | Valor |
|---|---|
| Nome | `Deploy` |
| Gatilhos | `push` na `main`; `workflow_dispatch` (sem entradas: publica a `main`) |
| Concorrência | grupo `deploy-ai-wssports`, `cancel-in-progress: false` |
| Permissões | `contents: read` |

### Jobs

| Job | Depende de | Acesso ao servidor | Saídas |
|---|---|---|---|
| `verificar` | — | não | artefato `build` (`public/build`) |
| `deploy` | `verificar` | sim (usuário `deploy`) | `passo_falhou`, `volta`, `copia` |
| `avisar` | `verificar`, `deploy` (`if: always()`) | não | — |

Segredos usados: ver [research.md](../research.md) R-11.

## 2. Script `scripts/deploy/deploy.sh`

- **Execução**: `ssh deploy@<host> 'bash -s' -- <commit-completo> < scripts/deploy/deploy.sh`
- **Entrada**: 1 argumento, o commit a publicar (40 caracteres hexadecimais; qualquer outro valor
  encerra com erro antes de qualquer passo).
- **Pré-requisitos no servidor**: build novo já em `/var/www/wssports-build-novo`; usuário e
  permissões do `server_setup.sh`.
- **Saída padrão** (marcadores lidos pelo job `deploy`):

| Marcador | Quando |
|---|---|
| `::passo:: <nome>` | início de cada passo: `manutencao`, `guardar_versao`, `atualizar_codigo`, `dependencias`, `frontend`, `copia_banco`, `migrations`, `caches`, `fila`, `no_ar` |
| `::copia:: <arquivo>` | cópia do banco gravada (só com migration pendente) |
| `::falha:: <nome>` | passo que falhou |
| `::volta:: ok` ou `::volta:: falhou` | resultado da volta ao commit anterior |
| `::sucesso:: <commit7>` | fim sem erro |

- **Código de saída**: `0` no sucesso; diferente de `0` em qualquer falha (mesmo com volta `ok`).
- **Garantias**: nunca imprime valores do `.env`; nunca altera `.env` nem `storage`; nunca roda
  seeders; sempre termina com o site fora da manutenção.

## 3. Script `scripts/deploy/server_setup.sh`

- **Execução**: uma vez, como root, no servidor: `bash server_setup.sh "<chave pública do pipeline>"`.
- **Faz** (idempotente): instala `acl`; cria o usuário `deploy`; grava a chave em
  `authorized_keys`; ajusta dono e ACL de `/var/www/wssports`; copia a deploy key do GitHub para o
  `deploy`; cria `/etc/sudoers.d/deploy` (validado com `visudo -c`); cria `/var/backups/wssports`.
- **Não faz**: não mexe no `.env`, no banco, no Nginx nem no Supervisor.
