# Implementation Plan: Deploy contínuo

**Branch**: `013-deploy-continuo` | **Date**: 2026-10-18 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/013-deploy-continuo/spec.md`

## Summary

Pipeline no GitHub Actions que publica a `main` no servidor de teste `ai.wssports.bet` a cada push
(e por disparo manual):

- **verificar** (no GitHub): sintaxe de todos os `.php` e compilação do frontend; falhou, para sem
  tocar no servidor;
- **deploy** (por SSH, usuário `deploy` sem root): envia o build e roda um script versionado que põe
  o site em manutenção, atualiza o código para o commit, instala dependências, troca o frontend,
  copia o banco quando há migration nova, migra, gera caches, reinicia a fila e volta ao ar; em
  falha, volta ao commit anterior;
- **avisar**: WhatsApp pelo CallMeBot com o resultado.

Inclui a correção do `PapeisPermissoesSeeder` e a remoção dos 4 workflows herdados do Laravel.
Decisões em [research.md](research.md) (R-01 a R-14).

## Technical Context

**Language/Version**: YAML do GitHub Actions; Bash (servidor Ubuntu 24.04); PHP 8.3 no servidor e
na verificação; Node 20 para o build

**Primary Dependencies**: só GitHub Actions oficiais (`actions/checkout`, `actions/setup-node`,
`actions/upload-artifact`, `actions/download-artifact`); o PHP 8.3 já vem no runner `ubuntu-24.04`
(mesma versão do servidor); `ssh`, `rsync` e `curl` do runner; no servidor, `acl` (novo), `git`, `composer`, `mysqldump`, `supervisorctl`. Nenhum
pacote novo no Composer nem no npm.

**Storage**: nenhuma tabela nova; cópias do banco em `/var/backups/wssports` (5 mais recentes)

**Testing**: nenhum teste automatizado (constituição); validação manual pelo
[quickstart.md](quickstart.md)

**Target Platform**: GitHub Actions (`ubuntu-24.04`, fixo para não mudar de PHP sem aviso) +
servidor DigitalOcean `ai.wssports.bet`

**Project Type**: infraestrutura de deploy de uma aplicação web Laravel (monólito com Inertia)

**Performance Goals**: push → site atualizado em até 10 min (SC-001); manutenção até 60 s (SC-002)

**Constraints**: servidor com 1 vCPU e 2 GB (build fora dele); sem segredos do `.env` no GitHub;
nunca deixar o site em manutenção; um deploy por vez

**Scale/Scope**: 1 workflow novo, 4 removidos, 3 scripts (`deploy.sh`, `server_setup.sh`,
`notify.sh`), 1 guia, 1 seeder alterado

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Princípio / Regra | Verificação | Status |
|---|---|---|
| I. Nomes | Pastas em inglês (`scripts/deploy`); arquivos de script em inglês snake_case (`deploy.sh`, `server_setup.sh`); funções, variáveis e marcadores dos scripts em português snake_case; jobs em português (`verificar`, `deploy`, `avisar`) | ✅ Pass |
| II. Idioma | Comentários, mensagens e guia em português | ✅ Pass |
| III. Componentes React | Nenhum componente novo | ✅ N/A |
| IV. Escopo estrito | Arquivos existentes alterados: só `PapeisPermissoesSeeder` e os 4 workflows removidos, ambos pedidos na spec (FR-018 e FR-019) | ✅ Pass |
| V. Legibilidade | Revisão ao final de cada tarefa | ✅ Pass |
| VI. Consistência | Sem recurso novo do sistema; o seeder segue o padrão dos demais (`firstOrCreate` por constante do `Funcao`) | ✅ Pass |
| VII. Sistema antigo | Consultado (R-01); diferenças aprovadas na spec | ✅ Pass |
| VIII. Fidelidade visual | Sem mudança de tela | ✅ N/A |
| IX. Dados fake | Nenhum | ✅ N/A |
| Stack: dependências | Nenhum pacote novo no projeto; `acl` só no servidor; actions oficiais justificadas em R-02 | ✅ Pass |
| Timestamps / comentários de colunas | Sem migrations | ✅ N/A |
| Paginação | Sem listagens | ✅ N/A |
| Sem testes | `php -l` e build não são testes; nenhum teste criado; o `tests.yml` herdado sai | ✅ Pass |
| Atualização e cache do frontend | Build com hash e versão pelo manifest seguem iguais; o deploy só troca a pasta `public/build` | ✅ Pass |
| Postman | Nenhuma rota criada ou alterada | ✅ N/A |

**Resultado do gate**: aprovado. Reavaliado depois da Fase 1: sem violação nova.

### Arquivos existentes que serão alterados ou removidos (Princípio IV)

| Arquivo | Alteração | Motivo |
|---|---|---|
| `database/seeders/PapeisPermissoesSeeder.php` | Cria também `PERMISSOES_ENCERRAMENTOS`, `PERMISSOES_CASSINO` e `PERMISSOES_AFILIADOS` | FR-018, R-12 |
| `.github/workflows/tests.yml`, `issues.yml`, `pull-requests.yml`, `update-changelog.yml` | Removidos | FR-019, R-13 |

Mudanças só no servidor (fora do repositório), feitas pelo `server_setup.sh`: usuário `deploy`, dono
e ACL de `/var/www/wssports`, `/etc/sudoers.d/deploy`, `/var/backups/wssports`,
`/var/www/wssports-deploy`, pacote `acl`.

## Project Structure

### Documentation (this feature)

```text
specs/013-deploy-continuo/
├── spec.md
├── plan.md              # este arquivo
├── research.md          # R-01 a R-14
├── data-model.md        # execução, arquivos do servidor, segredos
├── quickstart.md        # validação manual
├── contracts/
│   ├── pipeline.md      # workflow, jobs e interface dos scripts
│   └── aviso-whatsapp.md
├── checklists/
│   └── requirements.md
└── tasks.md             # /speckit-tasks
```

### Source Code (repository root)

```text
.github/workflows/
└── deploy.yml                     # novo (os 4 herdados são removidos)

scripts/deploy/
├── deploy.sh                      # passos no servidor, volta e marcadores (R-04, R-05, R-07)
├── server_setup.sh                # preparação única do servidor (R-06)
└── notify.sh                      # monta o texto do aviso e do resumo da execução (R-10)

docs/deploy/
└── README.md                      # guia: configurar do zero, segredos, CallMeBot, volta manual, restaurar cópia (FR-020)

database/seeders/
└── PapeisPermissoesSeeder.php     # alterado (FR-018)
```

**Structure Decision**: workflow único em `.github/workflows`; o que roda no servidor fica em
`scripts/deploy` (versionado, enviado pela conexão SSH); o guia fica em `docs/deploy`, ao lado da
pasta `docs/postman` existente.

## Complexity Tracking

| Violação | Por que é necessária | Alternativa mais simples rejeitada porque |
|---|---|---|
| ACL em `storage` e `bootstrap/cache` | `deploy` e `www-data` escrevem nos mesmos logs e caches | Rodar o `artisan` como `www-data` daria ao pipeline todo o poder do usuário do servidor web |
| Linha de `sudo` para o `deploy` | Reiniciar a fila exige o Supervisor, que é do root | Sem reiniciar, a fila continuaria com o código antigo na memória |
