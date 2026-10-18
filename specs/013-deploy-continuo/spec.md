# Feature Specification: Deploy contínuo

**Feature Branch**: `013-deploy-continuo`

**Created**: 2026-10-18

**Status**: Draft

**Input**: User description: "Deploy contínuo. Criar um pipeline no GitHub Actions que faz o deploy
automático do sistema no servidor de teste ai.wssports.bet (DigitalOcean) a cada push na branch
main, com opção de disparo manual. Sem testes automatizados (constituição). Verificações: antes do
deploy, o pipeline verifica a sintaxe do PHP e compila o frontend no próprio GitHub Actions; se algo
falhar, nada é enviado ao servidor. Deploy na mesma pasta do servidor (/var/www/wssports): coloca o
site em manutenção, atualiza o código para o commit enviado, instala as dependências do PHP, recebe
o frontend compilado, roda as migrations e gera os caches, reinicia a fila e tira o site da
manutenção; a parada esperada é de segundos. Se qualquer passo falhar, o código volta ao commit
anterior, o site sai da manutenção e o aviso informa a falha (uma migration aplicada não é desfeita
sozinha). Segurança: usuário deploy sem acesso root, com chave SSH própria nos segredos do GitHub e
permissão só para reiniciar a fila; nenhum segredo do .env vai para o GitHub. Avisos: mensagem no
WhatsApp do responsável pelo CallMeBot (gratuito) a cada deploy: sucesso com o commit, o autor e a
duração; falha com o passo que falhou e o link do log no GitHub. Número e chave do CallMeBot nos
segredos do GitHub; o e-mail do GitHub em caso de falha continua valendo. Ajuste incluído: corrigir
o PapeisPermissoesSeeder para criar todas as permissões, porque hoje a instalação num banco novo
quebra."

## Clarifications

### Session 2026-10-18 (decisões tomadas antes do `/speckit-specify`)

- Q: Onde o frontend é compilado? → A: No GitHub Actions; o servidor só recebe os arquivos prontos
  (o servidor de teste tem 1 vCPU e 2 GB de RAM).
- Q: O deploy usa uma pasta nova por versão (sem parada)? → A: Não. Atualiza a mesma pasta, com o
  site em manutenção por alguns segundos; em caso de falha, volta ao commit anterior.
- Q: Quais verificações barram o deploy? → A: Sintaxe do PHP e compilação do frontend. O Pint não
  entra agora, porque já falha em arquivos antigos (`routes/api.php` e
  `ClientesMeiosPagamentoRequest.php`).
- Q: Com qual usuário o pipeline entra no servidor? → A: Um usuário `deploy` sem acesso root, com
  permissão só para reiniciar a fila.
- Q: Quando o deploy roda? → A: A cada push na `main` e por um botão de disparo manual no GitHub.
- Q: Como o responsável é avisado? → A: Mensagem no WhatsApp pelo CallMeBot (gratuito) em todo
  deploy, além do e-mail do GitHub nas falhas.
- Q: Os 4 workflows herdados do esqueleto do Laravel ficam? → A: Não; os 4 são removidos
  (`tests.yml`, `issues.yml`, `pull-requests.yml`, `update-changelog.yml`).

### Session 2026-10-18 (`/speckit-clarify`)

- Q: O pipeline deve fazer uma cópia do banco do servidor antes de rodar as migrations de cada
  deploy? → A: Sim, só quando o deploy tem migration nova, guardando as últimas 5 cópias no servidor.
- Q: Depois do deploy, o pipeline deve abrir o site para conferir se ele responde e voltar ao commit
  anterior se não responder? → A: Não; o sucesso dos passos basta para considerar o deploy concluído.

## Referências do sistema antigo *(Princípio VII)*

- `wssports.bet/.github/workflows/main.yml`: a cada push na `main` (e por disparo manual),
  montava uma imagem Docker, publicava no Docker Hub e atualizava a imagem de cada cliente num
  cluster Kubernetes da DigitalOcean (`doctl` + `kubectl set image`), um passo por domínio.
- `wssports.bet/.github/workflows/dev.yml`: o mesmo fluxo para a branch `notificacao`.
- `wssports.bet/init.sh` e `Dockerfile`: subida do contêiner (PHP 7.3, Nginx, cron,
  `migrate --seed` a cada início).
- **Como funcionava**: deploy por imagem de contêiner, sem verificação antes e sem aviso do
  resultado. **O que muda**: o sistema novo roda num servidor único, sem Docker nem Kubernetes; o
  deploy atualiza a pasta do servidor, verifica antes de enviar, volta ao commit anterior em caso
  de falha e avisa pelo WhatsApp. Os seeders não rodam a cada deploy (no antigo, `migrate --seed`
  rodava a cada início do contêiner).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Deploy automático a cada push na main (Priority: P1) 🎯 MVP

O responsável faz o push (ou o merge) na `main` e, sem nenhum comando a mais, o servidor de teste
passa a rodar o código desse commit: dependências instaladas, frontend novo, migrations aplicadas,
caches regenerados e fila reiniciada.

**Why this priority**: é o objetivo da feature; hoje o deploy é manual, por SSH.

**Independent Test**: fazer um push na `main` com uma mudança visível (ex.: um texto) e conferir,
em poucos minutos, a mudança no `https://ai.wssports.bet` e o deploy concluído no GitHub.

**Acceptance Scenarios**:

1. **Given** um push na `main`, **When** o pipeline termina, **Then** o servidor está no mesmo commit
   do push, o site responde e a fila está rodando.
2. **Given** um push com uma migration nova, **When** o deploy termina, **Then** a migration está
   aplicada no banco do servidor.
3. **Given** um push com mudança no frontend, **When** o deploy termina, **Then** os usuários passam
   a receber o frontend novo na próxima interação, sem limpar cache (regra da constituição).
4. **Given** um push em outra branch, **When** ele chega ao GitHub, **Then** nenhum deploy acontece.
5. **Given** um deploy em andamento, **When** chega outro push na `main`, **Then** o segundo deploy
   espera o primeiro terminar e depois roda; nunca há dois deploys ao mesmo tempo.

---

### User Story 2 - Verificações barram código quebrado (Priority: P1)

Antes de mexer no servidor, o pipeline confere a sintaxe de todos os arquivos PHP e compila o
frontend. Se algo falhar, o deploy para ali e o servidor continua com a versão anterior, intacto.

**Why this priority**: evita derrubar o site com um erro de digitação ou um import quebrado.

**Independent Test**: fazer um push com um erro de sintaxe num arquivo PHP e conferir que o
pipeline falha antes de acessar o servidor e que o site segue no ar com a versão anterior.

**Acceptance Scenarios**:

1. **Given** um erro de sintaxe em PHP, **When** o pipeline roda, **Then** falha na verificação e
   não acessa o servidor.
2. **Given** um erro que quebra a compilação do frontend, **When** o pipeline roda, **Then** falha
   na verificação e não acessa o servidor.
3. **Given** código sem erros, **When** as verificações passam, **Then** o deploy segue.

---

### User Story 3 - Falha no meio do deploy não deixa o site quebrado (Priority: P1)

Se um passo falhar já no servidor (ex.: dependência que não instala, migration com erro), o código
volta ao commit anterior, os caches são refeitos com ele e o site sai da manutenção.

**Why this priority**: o deploy atualiza a pasta em uso; sem a volta, um erro deixaria o site fora
do ar ou com código pela metade.

**Independent Test**: fazer um push com uma migration que falha e conferir que o site volta a
responder com o commit anterior e que o aviso aponta o passo que falhou.

**Acceptance Scenarios**:

1. **Given** uma falha em qualquer passo no servidor, **When** o pipeline a detecta, **Then** o
   código volta ao commit que estava no ar, os caches são refeitos, a fila é reiniciada e o site sai
   da manutenção.
2. **Given** uma migration que falhou no meio, **When** a volta acontece, **Then** o aviso informa
   que alterações já aplicadas no banco não foram desfeitas.
3. **Given** que a própria volta falhe, **When** isso acontece, **Then** o site sai da manutenção do
   mesmo jeito e o aviso diz que é preciso intervenção manual.

---

### User Story 4 - Aviso no WhatsApp (Priority: P2)

A cada deploy, o responsável recebe uma mensagem no WhatsApp: no sucesso, o commit, o autor e a
duração; na falha, o passo que falhou, se houve volta ao commit anterior e o link do log no GitHub.

**Why this priority**: o responsável fica sabendo do resultado sem abrir o GitHub; o deploy em si
funciona sem o aviso.

**Independent Test**: fazer um deploy com sucesso e outro com falha e conferir as duas mensagens.

**Acceptance Scenarios**:

1. **Given** um deploy com sucesso, **When** termina, **Then** chega no WhatsApp "✅ Deploy concluído"
   com a mensagem do commit (resumida), o código curto do commit, o autor e a duração.
2. **Given** um deploy com falha, **When** termina, **Then** chega "❌ Deploy falhou" com o passo que
   falhou, se o site voltou ao commit anterior e o link do log.
3. **Given** uma falha na verificação, **When** o pipeline para, **Then** chega o aviso de falha
   dizendo que o servidor não foi alterado.
4. **Given** o serviço de WhatsApp fora do ar, **When** o envio falha, **Then** o resultado do deploy
   não muda e o e-mail do GitHub continua valendo nas falhas.

---

### User Story 5 - Disparo manual (Priority: P2)

O responsável pode rodar o deploy da `main` por um botão no GitHub, sem precisar de um push (ex.:
depois de mudar o `.env` do servidor ou para reaplicar a versão atual).

**Why this priority**: útil para corrigir o servidor sem criar commits vazios.

**Independent Test**: acionar o disparo manual no GitHub e conferir o deploy e o aviso.

**Acceptance Scenarios**:

1. **Given** o botão de disparo manual, **When** é acionado, **Then** o deploy da `main` roda com as
   mesmas verificações e o mesmo aviso.

---

### User Story 6 - Acesso restrito do pipeline ao servidor (Priority: P2)

O pipeline entra no servidor com um usuário próprio, `deploy`, sem acesso root, que só consegue
mexer na pasta do sistema e reiniciar a fila. A chave fica nos segredos do GitHub e nenhum dado do
`.env` sai do servidor.

**Why this priority**: se o GitHub ou a chave vazarem, o estrago fica limitado ao sistema.

**Independent Test**: com a chave do pipeline, tentar ler arquivos do root ou instalar pacotes e ser
recusado; o deploy funciona normalmente.

**Acceptance Scenarios**:

1. **Given** a chave do pipeline, **When** é usada para entrar no servidor, **Then** entra como
   `deploy`, consegue atualizar a pasta do sistema e reiniciar a fila, e não consegue usar root.
2. **Given** o repositório e os logs do pipeline, **When** são lidos, **Then** não aparece nenhum
   valor do `.env` (senhas, chaves de integração, token JWT).

---

### User Story 7 - Instalação num banco novo sem erro (Priority: P3)

Rodar as migrations e os seeders num banco vazio funciona de primeira, com todas as permissões
criadas antes dos usuários de exemplo.

**Why this priority**: hoje quebra (as permissões das specs 010, 011 e 012 só são criadas depois do
Admin de exemplo); afeta só a instalação do zero, não o deploy do dia a dia.

**Independent Test**: num banco vazio, rodar migrations e seeders e conferir que terminam sem erro e
que o Admin tem todas as permissões da função dele.

**Acceptance Scenarios**:

1. **Given** um banco vazio, **When** migrations e seeders rodam, **Then** terminam sem erro.
2. **Given** a instalação concluída, **When** as permissões do Admin são consultadas, **Then** ele
   tem todas as permissões padrão da função (inclusive encerramentos, cassino e afiliados).

---

### Edge Cases

- **Servidor fora do ar ou SSH recusado**: o deploy falha antes de alterar qualquer coisa; aviso de
  falha com o passo "conexão com o servidor".
- **Dois pushes seguidos**: os deploys rodam um de cada vez, em ordem; o segundo só começa quando o
  primeiro termina (com sucesso ou não).
- **Push que só muda documentação (`specs/`, `.md`)**: o deploy roda do mesmo jeito (simples e
  previsível); a parada de segundos é aceita.
- **Commit que não existe no servidor ainda**: o servidor busca o commit no GitHub antes de
  atualizar; se não conseguir, falha sem alterar nada.
- **Mudanças locais no servidor** (alguém editou um arquivo direto lá): o deploy descarta e deixa o
  código igual ao commit; `.env`, `storage` e uploads nunca são tocados.
- **Migration que falha no meio**: o que já foi aplicado no banco fica; o código volta ao commit
  anterior e o aviso pede atenção ao banco, indicando a cópia feita antes das migrations para
  restauração manual (o guia do FR-020 explica como restaurar).
- **Disco sem espaço para a cópia do banco**: a cópia falha, as migrations não rodam e o deploy volta
  ao commit anterior.
- **Site com erro depois de todos os passos** (ex.: página 500 por configuração): o deploy conta como
  sucesso, porque não há conferência do site depois dos passos (decisão do responsável); o problema
  aparece no uso e se corrige com um novo push ou com a volta manual do guia (FR-020).
- **Seeders**: nunca rodam no deploy (só na instalação do zero).
- **Dependências de desenvolvimento**: o deploy instala só as de produção; os dados de exemplo são
  criados apenas na instalação do zero.
- **Fila ocupada**: a reinicialização espera o job em andamento terminar ou o reinicia de forma
  segura, como o Supervisor já faz.
- **CallMeBot fora do ar ou chave inválida**: o aviso não chega, o deploy não muda de resultado e o
  log do pipeline registra que o aviso falhou.

## Requirements *(mandatory)*

### Functional Requirements

**Gatilho**

- **FR-001**: O deploy DEVE rodar a cada push na branch `main` e por disparo manual no GitHub; pushes
  em outras branches NÃO DEVEM disparar deploy.
- **FR-002**: Só um deploy DEVE rodar por vez; um novo push durante um deploy DEVE esperar o atual
  terminar (sem cancelar o que está em andamento).

**Verificações (antes de acessar o servidor)**

- **FR-003**: O pipeline DEVE verificar a sintaxe de todos os arquivos PHP do projeto (fora
  `vendor/`) e compilar o frontend; qualquer falha DEVE encerrar o pipeline sem acessar o servidor.
- **FR-004**: O pipeline NÃO DEVE rodar testes automatizados (constituição).

**Deploy no servidor**

- **FR-005**: O deploy DEVE acontecer na pasta em uso (`/var/www/wssports`), nesta ordem: colocar o
  site em manutenção; guardar o commit atual; atualizar o código para o commit do push; instalar as
  dependências do PHP de produção; receber o frontend compilado pelo pipeline; copiar o banco quando
  houver migration nova (FR-005a); rodar as migrations;
  gerar os caches de produção; reiniciar a fila; tirar o site da manutenção.
- **FR-005a**: Quando o commit trouxer migrations ainda não aplicadas, o deploy DEVE gravar uma cópia
  compactada do banco no próprio servidor (fora da pasta pública) antes de rodá-las, guardando só as
  5 cópias mais recentes. Sem migration nova, não faz cópia. Se a cópia falhar, o deploy DEVE parar
  sem rodar as migrations e seguir a volta do FR-008. O aviso de falha numa migration DEVE informar
  o nome da cópia feita antes dela.
- **FR-006**: O deploy NUNCA DEVE alterar o `.env`, a pasta `storage` (logs, uploads, sessões em
  arquivo) nem rodar seeders.
- **FR-007**: A parada do site (manutenção) DEVE durar só o tempo dos passos no servidor; o frontend
  já chega compilado.
- **FR-008**: Se qualquer passo no servidor falhar, o pipeline DEVE voltar o código ao commit
  guardado, refazer os caches e as dependências dele, reiniciar a fila e tirar o site da
  manutenção; o resultado DEVE ser "falha" mesmo com a volta bem-sucedida.
- **FR-009**: O site NUNCA DEVE ficar em manutenção depois do fim do pipeline, com sucesso ou falha
  (inclusive se a volta falhar).
- **FR-010**: O pipeline DEVE registrar no GitHub, em cada execução, o commit, o autor, a duração e
  o passo que falhou, se houver.

**Segurança**

- **FR-011**: O pipeline DEVE entrar no servidor com o usuário `deploy`, sem acesso root e sem senha,
  autenticado por uma chave SSH exclusiva guardada nos segredos do GitHub.
- **FR-012**: O usuário `deploy` DEVE poder escrever só na pasta do sistema (junto com o usuário do
  servidor web) e reiniciar só a fila do sistema; NÃO DEVE ter outros comandos de administrador.
- **FR-013**: Nenhum valor do `.env` do servidor DEVE ir para o repositório, para os segredos do
  GitHub ou para os logs do pipeline.
- **FR-014**: O pipeline DEVE conferir a identidade do servidor (impressão digital da chave SSH)
  antes de conectar.

**Avisos**

- **FR-015**: Ao fim de cada execução, o pipeline DEVE mandar uma mensagem de WhatsApp ao responsável
  pelo CallMeBot: no sucesso, título, mensagem resumida do commit, código curto do commit, autor e
  duração; na falha, o passo que falhou, se o servidor foi alterado, se houve volta ao commit
  anterior e o link do log da execução.
- **FR-016**: O número do responsável e a chave do CallMeBot DEVEM ficar nos segredos do GitHub,
  nunca no repositório.
- **FR-017**: Uma falha no envio do WhatsApp NÃO DEVE mudar o resultado do deploy; o e-mail padrão do
  GitHub nas falhas continua valendo.

**Instalação do zero**

- **FR-018**: O seeder de papéis e permissões DEVE criar todas as permissões declaradas na
  `Funcao` (gestão, clientes, confrontos, apostas, especiais, site, pagamentos, encerramentos,
  cassino e afiliados) antes de qualquer usuário de exemplo, para que migrations e seeders num banco
  vazio terminem sem erro.

**Workflows herdados do esqueleto**

- **FR-019**: Os workflows que vieram do esqueleto do Laravel (`tests.yml`, `issues.yml`,
  `pull-requests.yml`, `update-changelog.yml`) DEVEM ser removidos: o `tests.yml` roda testes todo
  dia e em todo pull request, o que contraria a constituição, e os outros três chamam fluxos do
  repositório do próprio Laravel. O workflow de deploy passa a ser o único do projeto.

**Documentação**

- **FR-020**: DEVE existir um guia curto de como configurar o pipeline do zero (segredos do GitHub,
  usuário `deploy`, ativação do CallMeBot), de como voltar manualmente a uma versão anterior e de como
  restaurar uma das cópias do banco.

### Key Entities

- **Execução de deploy**: uma rodada do pipeline, com gatilho (push ou manual), commit, autor,
  resultado (sucesso, falha na verificação, falha no servidor com ou sem volta), passo que falhou e
  duração. Fica no histórico do GitHub; nada é gravado no banco do sistema.
- **Segredos do pipeline**: chave SSH do usuário `deploy`, endereço e impressão digital do servidor,
  número do WhatsApp e chave do CallMeBot. Ficam só nos segredos do GitHub.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Do push na `main` até o site rodando o commit novo leva no máximo 10 minutos.
- **SC-002**: A parada do site (manutenção) em um deploy normal dura no máximo 60 segundos.
- **SC-003**: 100% dos pushes com erro de sintaxe PHP ou de compilação do frontend param antes de
  acessar o servidor, sem nenhuma alteração nele.
- **SC-004**: Em 100% das falhas no servidor, o site volta a responder com o commit anterior e sai da
  manutenção em até 3 minutos depois da falha.
- **SC-005**: Em 100% dos deploys (sucesso ou falha), o resultado aparece no GitHub; quando o
  CallMeBot está disponível, a mensagem chega ao WhatsApp em até 2 minutos.
- **SC-006**: Nenhum valor do `.env` aparece no repositório, nos segredos ou nos logs do pipeline.
- **SC-007**: Num banco vazio, migrations e seeders terminam sem erro na primeira tentativa.

## Assumptions

- O servidor de teste é o `ai.wssports.bet` configurado nesta conversa (Ubuntu 24.04, Nginx, PHP
  8.3, MySQL 8.4 local, Supervisor com a fila e cron com o agendador), com o código clonado do
  GitHub em `/var/www/wssports` e a deploy key de leitura já cadastrada no repositório.
- O pipeline faz deploy só nesse servidor de teste; produção e outros ambientes ficam para outra
  spec.
- O CallMeBot é gratuito para avisos ao próprio número e exige uma ativação única pelo responsável
  (mandar a mensagem de autorização ao número do CallMeBot e receber a chave); não há garantia de
  disponibilidade.
- O GitHub Actions gratuito do repositório privado (2.000 minutos por mês) é suficiente; cada deploy
  usa poucos minutos.
- O Reverb segue desligado (`BROADCAST_CONNECTION=null`); quando for ligado, a reinicialização dele
  entra no mesmo passo da fila.
- O deploy instala só as dependências de produção do PHP; hoje o servidor tem as de desenvolvimento
  porque os seeders de exemplo usam o Faker, e elas deixam de existir no primeiro deploy.
- O Pint não barra o deploy agora; pode virar verificação depois de corrigidos os arquivos antigos.
- A correção do `PapeisPermissoesSeeder` (FR-018) altera um arquivo da spec 001; a alteração foi
  pedida pelo responsável nesta spec.
