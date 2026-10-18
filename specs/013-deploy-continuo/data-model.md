# Data Model: Deploy contínuo

**Feature**: `013-deploy-continuo` | **Date**: 2026-10-18

Esta feature **não cria tabelas nem colunas** no banco do sistema. Os "dados" são o estado de cada
execução do pipeline (guardado no GitHub) e os arquivos que o deploy mantém no servidor.

## 1. Execução de deploy (no GitHub)

| Campo | Origem | Uso |
|---|---|---|
| gatilho | `push` ou `workflow_dispatch` | aviso e histórico |
| commit | `github.sha` (código curto = 7 primeiros caracteres) | atualização do servidor e aviso |
| mensagem do commit | `github.event.head_commit.message` (primeira linha, até 80 caracteres); no disparo manual, `git log -1` | aviso |
| autor | `github.actor` | aviso |
| duração | do início do job `verificar` ao fim do job `deploy` | aviso de sucesso |
| resultado | `sucesso` · `falha_verificacao` · `falha_servidor` | aviso |
| passo que falhou | marcador `::falha::` do script (R-04) | aviso de falha |
| volta | marcador `::volta:: ok\|falhou` | aviso de falha |
| cópia do banco | marcador `::copia::` | aviso de falha numa migration |
| link do log | `https://github.com/<repo>/actions/runs/<run_id>` | aviso de falha |

### Estados

```text
verificar ──falha──► falha_verificacao (servidor intocado)
    │
    ok
    ▼
deploy ──falha──► volta ──ok──────► falha_servidor (site no commit anterior)
    │                └─falhou────► falha_servidor (intervenção manual; site fora da manutenção)
    ok
    ▼
sucesso
```

## 2. Arquivos no servidor

| Caminho | Dono / modo | Conteúdo |
|---|---|---|
| `/var/www/wssports` | `deploy:www-data` | código (repositório git) |
| `/var/www/wssports/.env` | `root:www-data 640` | configuração; nunca alterada pelo deploy |
| `/var/www/wssports/storage`, `bootstrap/cache` | ACL `deploy` + `www-data` (rwX, padrão herdado) | logs, sessões, caches |
| `/var/www/wssports/public/build` | `deploy:www-data` | frontend em uso |
| `/var/www/wssports-deploy` | `deploy`, 750 | pasta de trabalho do deploy (fora da pasta pública) |
| `/var/www/wssports-deploy/build.anterior` | `deploy` | frontend do deploy anterior (para a volta) |
| `/var/www/wssports-deploy/build-novo` | `deploy` | build recebido do pipeline, antes da troca; recriado a cada deploy |
| `/var/backups/wssports/*.sql.gz` | `deploy`, 600 (pasta 700) | cópias do banco; só as 5 mais recentes |
| `/etc/sudoers.d/deploy` | `root`, 440 | permissão única de reiniciar a fila |

## 3. Segredos (no GitHub)

Lista e formato em [research.md](research.md) R-11. Nenhum valor do `.env` (FR-013).
