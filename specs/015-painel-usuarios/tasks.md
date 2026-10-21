---

description: "Lista de tarefas da feature 015 — Painel: Usuários (Supervisores, Gerentes e Vendedores)"
---

# Tasks: Painel — Usuários (Supervisores, Gerentes e Vendedores)

**Input**: Documentos de design em `specs/015-painel-usuarios/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md),
[data-model.md](data-model.md), [contracts/api.md](contracts/api.md),
[contracts/paginas.md](contracts/paginas.md), [quickstart.md](quickstart.md)

**Tests**: sem testes automatizados (Constituição); validação manual pelo [quickstart.md](quickstart.md).

**Regras para toda tarefa**: alterar só os arquivos citados (Princípio IV; lista aprovada no plan.md);
nomes em snake_case português, componentes em inglês PascalCase com `index.jsx` + `styles.jsx`;
comentários em português; mensagens de erro em português; alcance e permissões sempre checados no
servidor; visual do painel da spec 014 (`PanelShell`, `DashboardSection`, temas, `mobile` de
`theme/tokens`); revisão de legibilidade ao fim de cada tarefa (Princípio V).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: pode rodar em paralelo (arquivos diferentes, sem dependência pendente)
- **[Story]**: história da spec (US1 a US5)

---

## Phase 1: Setup

- [X] T001 Criar `resources/js/utils/permissions.js` com o mapa `{ chave: { grupo, nome, descricao } }` de todas as permissões de `app/Enums/Funcao.php` (incluindo as 9 novas `usuarios.{cadastrar|editar|excluir}_{supervisor|gerente|vendedor}`) e a ordem dos grupos: Usuários, Clientes, Confrontos e cotações, Apostas, Especiais, Site e configurações, Pagamentos, Cassino, Afiliados, Dashboard (data-model §5); nomes amigáveis e descrições curtas em português (ex.: `usuarios.cadastrar_vendedor` → "Cadastrar vendedor", "Cria vendedores na sua rede")

---

## Phase 2: Foundational (bloqueia todas as histórias)

**Purpose**: permissões por função, checagem pela função alvo, menu e página base

### Backend

- [X] T002 Em `app/Enums/Funcao.php`: em `PERMISSOES_GESTAO`, trocar `usuarios.cadastrar`, `usuarios.editar` e `usuarios.excluir` por `usuarios.cadastrar_supervisor`, `usuarios.cadastrar_gerente`, `usuarios.cadastrar_vendedor`, `usuarios.editar_supervisor`, `usuarios.editar_gerente`, `usuarios.editar_vendedor`, `usuarios.excluir_supervisor`, `usuarios.excluir_gerente`, `usuarios.excluir_vendedor` (listar, consultar, alterar_situacao e gerenciar_permissoes ficam); criar `PERMISSOES_USUARIOS_POR_FUNCAO` (mapa função alvo → as 3 permissões); em `pode_usar`, para essas 9: só quando o `nivel()` da função alvo é maior que o da função de quem usa (Vendedor: nenhuma; listar/consultar/alterar_situacao/gerenciar_permissoes: Admin, Supervisor e Gerente); em `permissoes_padrao`, filtrar `PERMISSOES_GESTAO` por `pode_usar`; atualizar os docblocks (research R-02, data-model §1)
- [X] T003 Criar a migration de dados `database/migrations/2026_10_21_000001_dividir_permissoes_usuarios_por_funcao.php`: `up` cria as 9 permissões (guard `api`), dá a cada usuário (com `roles`) que tinha `usuarios.cadastrar`/`editar`/`excluir` as novas da mesma ação que a função dele pode usar (`Funcao::pode_usar`), apaga as 3 permissões antigas (`Permission::where('name', ...)->delete()`, o spatie remove os vínculos) e limpa o cache do spatie; `down` recria as 3 antigas, dá cada uma a quem tinha alguma das novas da mesma ação, apaga as 9 e limpa o cache; docblock citando a spec 015 e o motivo de ser migration (o deploy não roda seeders) (depende de T002)
- [X] T004 Em `app/Http/Controllers/UsuariosController.php`: tirar os middlewares fixos de `cadastrar` e `excluir` (manter `listar` e `consultar`); criar `garantir_permissao_funcao(Request, string $acao, Funcao $funcao)` que usa `Funcao::usuario_pode($request->user(), "usuarios.{$acao}_".strtolower($funcao->value))` e responde 403 "Você não tem permissão para esta ação."; usar `cadastrar` no `store` (função pedida), `editar` no `update` quando altera dados ou superior (função do alvo) e `excluir` no `destroy` (função do alvo); `update` de `ativo` continua com `usuarios.alterar_situacao`; ajustar o docblock em `app/Http/Requests/UpdateUsuariosRequest.php` (permissão `usuarios.editar_<função>`) (depende de T002)
- [X] T005 [P] Em `app/Http/Resources/UsuarioLogadoResource.php`, acrescentar `permissoes`: nomes das permissões do usuário em que `Funcao::usuario_pode` é verdadeiro (contracts/api §1) (depende de T002)
- [X] T006 Em `app/Http/Controllers/PainelController.php`, criar as ações `supervisores`, `gerentes` e `vendedores` que renderizam `Inertia::render('PanelUsers', ['funcao' => 'Supervisor'|'Gerente'|'Vendedor'])`; em `routes/web.php`, registrar `GET painel/supervisores`, `painel/gerentes` e `painel/vendedores` com nomes `painel.supervisores` etc.

### Frontend

- [X] T007 [P] Criar `resources/js/components/PanelTabs/index.jsx` e `styles.jsx`: abas horizontais (rótulo, ícone opcional, contador opcional), aba ativa com borda na cor do tema, rolagem horizontal no celular; props `abas`, `ativa`, `ao_trocar` (Tabs de `screens/adm/components/components` do antigo)
- [X] T008 [P] Criar `resources/js/components/Pagination/index.jsx` e `styles.jsx`: anterior/próxima e números (até 5 visíveis), props `pagina`, `ultima`, `ao_trocar`; some com uma página só (Paginator do antigo)
- [X] T009 [P] Criar `resources/js/components/ToggleSwitch/index.jsx` e `styles.jsx`: chave acessível (`role="switch"`, `aria-checked`, teclado), props `ligado`, `ao_trocar`, `desabilitado`, `carregando`, na cor do tema
- [X] T010 Em `resources/js/components/PanelShell/index.jsx` e `styles.jsx`: trocar `itens_menu` por uma lista com item simples (Dashboard) e grupo "Usuários" (ícone `people`) com subitens Supervisores (`/painel/supervisores`, só Admin), Gerentes (`/painel/gerentes`, Admin e Supervisor) e Vendedores (`/painel/vendedores`, Admin, Supervisor e Gerente); o grupo aparece com `usuarios.listar` em `usuario.permissoes` e abre/fecha (aberto quando o subitem atual está nele); item ativo pelo endereço atual (`usePage().url`); receber `titulo` como já faz (FR-001, contracts/paginas §1) (depende de T005)
- [X] T011 Criar `resources/js/hooks/useUsuariosPainel.js`: `useUsuariosPainel(funcao, aba)` busca `GET /usuarios` (aba Ativos) ou `GET /usuarios/excluidos` (aba Lixeira) com `funcao`, `busca` (atraso de 400 ms), `pagina` e `por_pagina` 15, com o token da sessão de usuário; devolve `{ usuarios, meta, carregando, erro, busca, definir_busca, pagina, definir_pagina, recarregar }`; 401 → `expirar()`
- [X] T012 Criar `resources/js/pages/PanelUsers/index.jsx` e `styles.jsx` (base): usa `usePainel`; sem `usuarios.listar` ou sem acesso à função (Supervisores só Admin; Gerentes Admin e Supervisor) → `router.visit('/painel')`; `PanelShell` com título; cabeçalho "Lista de <função> – total N", campo de busca e o botão "Novo <função>" (só com `usuarios.cadastrar_<função>`); `PanelTabs` Ativos/Lixeira; o conteúdo da lista entra na US1 (depende de T006, T007, T010, T011)

**Checkpoint**: permissões novas convertidas, controller checando pela função, menu e página base

---

## Phase 3: User Story 1 — Ver os usuários da minha rede (Priority: P1) 🎯 MVP

**Goal**: listas por função com as colunas do antigo, busca e paginação, no alcance do nível

**Independent Test**: quickstart §2

- [X] T013 [US1] Em `app/Http/Controllers/UsuariosController.php` (`index` e `consulta_sub_hierarquia`): carregar `superior:id,nome`, `configuracoes` só quando `funcao=Vendedor`, `withCount` de vendedores ativos e não excluídos (gerente: subordinados com papel Vendedor; supervisor: vendedores da rede) e de gerentes ativos (supervisor), sem laço por linha; `por_pagina` padrão 15, máximo 100 (research R-06)
- [X] T014 [US1] Em `app/Http/Resources/UsuariosResource.php`, acrescentar `superior` (`{id, nome}` quando carregado), `comissoes` (`{"1","2","3"}` de `comissao_pre_jogo_1..3` quando a configuração estiver carregada), `quantidade_vendedores`, `quantidade_gerentes` e `deleted_at` (quando presentes), com `whenLoaded`/`whenCounted` (data-model §4) (depende de T013)
- [X] T015 [P] [US1] Criar `resources/js/components/UsersTable/index.jsx` e `styles.jsx`: tabela (desktop) com as colunas por função (contracts/paginas §2): ID, Nome, Login, Comissão "5/7/10" (vendedor), Gerente/Supervisor (só se `mostrar_superior`), Vendedores/Gerentes (quantidades), Telefone, Ativo (bolinha verde/vermelha + Sim/Não), Data de criação e Alterado em (`formatar_data_hora`), Ações (ícones Material com `title`, cada um só quando a prop de ação vier); no celular (≤ 900 px) cartões com nome, login, superior, ativo e ações; estados carregando (esqueleto), vazio ("Nenhum usuário encontrado.") e erro com "Tentar novamente"
- [X] T016 [US1] Em `resources/js/pages/PanelUsers/index.jsx`, ligar o `useUsuariosPainel` à `UsersTable` e à `Pagination`, total no título, busca e troca de aba; `mostrar_superior` quando a função listada não é a imediatamente abaixo de quem vê (depende de T012, T014, T015, T008)

**Checkpoint**: US1 validada (quickstart §2)

---

## Phase 4: User Story 2 — Cadastrar e editar (Priority: P1)

**Goal**: cadastro em qualquer nível abaixo com superior e comissões 1/2/3; edição de dados, senha e superior

**Independent Test**: quickstart §3

- [X] T017 [US2] Em `app/Http/Requests/StoreUsuariosRequest.php`: `funcao` aceita qualquer função com `nivel()` maior que a de quem cadastra (mensagem "Você não pode cadastrar usuários com a função X."); `usuarios_id`: `required` quando a função não é a imediatamente abaixo ("Informe o superior.") e `prohibited` quando é; validação de que o superior é da função logo acima da cadastrada e está na rede de quem cadastra (`gerencia`), com a mensagem "O superior informado é inválido para a função deste usuário."; `comissao_1`, `comissao_2`, `comissao_3`: `sometimes|nullable|numeric|between:0,100|regex:/^\d+(\.\d{1,2})?$/`, só para `funcao=Vendedor` (`prohibited` nas demais), mensagens em português; `login` ganha `regex:/^[A-Za-z0-9._-]+$/` com a mensagem "O login deve ter só letras sem acento, números, ponto, hífen ou sublinhado, sem espaços." (a regra atual só tira os espaços das pontas), além da regra de login único que já existe (research R-03, R-04)
- [X] T018 [US2] Em `app/Services/ConfiguracoesVendedores.php`, criar `aplicar_comissoes_cadastro(Usuarios $vendedor, ?string $um, ?string $dois, ?string $tres): void`: quando os três vierem, grava `comissao_pre_jogo_1..3` e `comissao_ao_vivo_1..3` com os valores e as faixas 4 a 12 dos dois com o de 3 jogos; quando algum faltar, não altera nada (fica a cópia do colega) (Clarifications, research R-04)
- [X] T019 [US2] Em `app/Http/Controllers/UsuariosController.php` (`store`): `usuarios_id` = o informado quando a função não é a imediata, senão o de quem cadastra; dentro da transação, depois de `criar_para`, chamar `aplicar_comissoes_cadastro` com as comissões do pedido; `atribuir_permissoes_padrao` continua (depende de T017, T018)
- [X] T020 [P] [US2] Criar `resources/js/components/UserFormModal/index.jsx` e `styles.jsx` (aba Dados): modal no visual do painel (cabeçalho escuro, título "Cadastrar/Editar <função>", botão salvar com `Spinner`, fechar com Esc, fundo e `useVoltarFecha`); campos Nome, Login (só leitura na edição), Senha e Confirme a senha (na edição, em branco mantém; diferentes → "As senhas não conferem." sem enviar), Superior (campo com busca: `GET /usuarios?funcao=<função acima>&busca=<texto>` paginado, sem lista fixa; o inativo aparece com "(inativo)" e pode ser escolhido; só quando aplicável; com um único superior na rede já vem escolhido; nenhum → "Cadastre um <função acima> antes"), Telefone com `formatar_telefone`, Endereço, Ativo (edição) e, no cadastro de vendedor, Comissão 1, 2 e 3 jogos; erros por campo com `erros_por_campo`; `PanelTabs` para as abas (as demais entram nas US3/US4); `POST /usuarios` ou `PATCH /usuarios/{id}` com o token
- [X] T021 [US2] Em `resources/js/pages/PanelUsers/index.jsx`, abrir o `UserFormModal` pelo botão "Novo <função>" e pela ação editar (só com `usuarios.editar_<função>`), recarregando a lista ao salvar com o aviso de sucesso (depende de T016, T020)
- [X] T022 [US2] Em `specs/001-user_management/spec.md`, acrescentar no fim a nota "Alterado pela spec 015": cadastro em qualquer nível abaixo com superior e permissões `usuarios.cadastrar|editar|excluir` divididas por função

**Checkpoint**: US2 validada (quickstart §3)

---

## Phase 5: User Story 3 — Comissões e regras do vendedor e da equipe (Priority: P1)

**Goal**: abas Comissões e Limites e regras do vendedor; Regras da equipe (gerente) e da rede (supervisor)

**Independent Test**: quickstart §4

- [X] T023 [US3] Em `app/Http/Controllers/UsuariosConfiguracoesController.php`, criar `show(Request, Usuarios $usuario)`: só vendedor da rede de quem pede (`gerencia`; fora → 404 "Usuário não encontrado."), exige `usuarios.consultar` (middleware só nesta ação; `index` e `update` mantêm `usuarios_configuracoes.editar`), devolve `UsuariosConfiguracoesResource` de `UsuariosConfiguracoes::do_vendedor`; em `routes/api.php`, `GET usuarios-configuracoes/{usuario}` com `->missing(...)` (research R-07)
- [X] T024 [P] [US3] Criar `resources/js/components/CommissionFields/index.jsx` e `styles.jsx`: duas grades (Pré-jogo e Ao vivo) de 1 a 12 jogos ("12 ou mais" na última) e Comissão por prêmio, em %, com campos numéricos de 2 casas; props `valores`, `ao_mudar(campo, valor)`, `somente_leitura`, `erros`; 3 colunas no celular, 6 no desktop
- [X] T025 [P] [US3] Criar `resources/js/components/BettingRulesFields/index.jsx` e `styles.jsx`: grupos Limites (limite_geral, limite_duplo, limite_simples), Apostas (realizar_aposta, cancelar_aposta, tempo_cancelamento_aposta, valor_minimo_aposta, valor_maximo_aposta, odd_minima, premio_maximo, multiplicador, quantidade_minima_opcoes, quantidade_maxima_opcoes, ganho_multiplo_palpites), Ao vivo (ao_vivo_habilitado, minuto_limite_ao_vivo, cotacao_maxima_ao_vivo, delay_ao_vivo), Jogos (periodo_jogos, data_travamento_sistema, apostar_jogadores, esportes_permitidos, apostar_outros_esportes), Criar Aposta (criar_aposta_habilitado, desconto_criar_aposta, quantidade_minima_criar_aposta, quantidade_maxima_criar_aposta) e Bilhete (mensagem_bilhete); booleanos com `ToggleSwitch`, com os rótulos e `title` do antigo; props `valores`, `ao_mudar`, `somente_leitura`, `erros` (depende de T009)
- [X] T026 [US3] Em `resources/js/components/UserFormModal/index.jsx`: aba Comissões e aba Limites e regras para vendedor (carregar de `GET /usuarios-configuracoes/{id}`, salvar só os campos alterados em `PATCH /usuarios-configuracoes` com `usuarios_id` do vendedor); aba "Regras da equipe" para gerente e "Regras da rede" para supervisor com `CommissionFields` + `BettingRulesFields` em branco (só os alterados são enviados, com `usuarios_id` do gerente/supervisor); na rede, `confirmar("Isso vai alterar N vendedores.")` com `quantidade_vendedores` da linha; aviso com a quantidade devolvida; sem `usuarios_configuracoes.editar`, vendedor só leitura e equipe/rede escondidas; aviso "Não há vendedores" quando a quantidade é 0 (depende de T020, T023, T024, T025)

**Checkpoint**: US3 validada (quickstart §4)

---

## Phase 6: User Story 4 — Permissões do usuário (Priority: P2)

**Goal**: modal de permissões agrupadas com chaves, só o que pode ser concedido

**Independent Test**: quickstart §5

- [X] T027 [US4] Em `app/Http/Controllers/PermissoesUsuariosController.php`: no `store`, além de "só o que possui", recusar com 422 "Esta permissão não vale para a função do usuário." quando a função do alvo não pode usar a permissão (`Funcao::pode_usar`); em `resposta_permissoes`, acrescentar `disponiveis` = permissões de quem edita que a função do alvo pode usar (recebendo o solicitante), nas respostas de `index`, `store` e `destroy` (contracts/api §9)
- [X] T028 [P] [US4] Criar `resources/js/components/PermissionsPanel/index.jsx` e `styles.jsx`: lista, agrupadas na ordem de `utils/permissions.js`, só as permissões de `disponiveis` devolvidas pela API (sem regra de função no frontend; a que não tiver texto em `utils/permissions.js` aparece com o próprio nome no grupo "Outras"), ligadas conforme `permissoes`; cada linha com nome, descrição e `ToggleSwitch`; "Marcar todas"/"Desmarcar todas" por grupo; cada mudança chama `POST` ou `DELETE /usuarios/{id}/permissoes`, mostra carregando na chave, aviso de sucesso (toast) e volta a chave com a mensagem em erro (depende de T001, T009)
- [X] T029 [P] [US4] Criar `resources/js/components/PermissionsModal/index.jsx` e `styles.jsx`: modal "Permissões de <nome>" com o `PermissionsPanel`, carregando `GET /usuarios/{id}/permissoes`; Esc, fundo e voltar fecham (depende de T028)
- [X] T030 [US4] Ligar a ação 🔑 da `UsersTable` ao `PermissionsModal` em `resources/js/pages/PanelUsers/index.jsx` (só com `usuarios.gerenciar_permissoes`) e a aba Permissões do `UserFormModal` (`resources/js/components/UserFormModal/index.jsx`, só na edição) ao `PermissionsPanel` (depende de T021, T029)

**Checkpoint**: US4 validada (quickstart §5)

---

## Phase 7: User Story 5 — Ativar, desativar, excluir e restaurar (Priority: P2)

**Goal**: situação e exclusão em cascata com confirmação; Lixeira só com Restaurar

**Independent Test**: quickstart §6

- [X] T031 [US5] Em `app/Models/Usuarios.php`: criar `ids_sub_hierarquia_com_excluidos()` (subida por nível com `withTrashed`) e `restaurar_em_cascata(): int` que, numa transação, restaura este usuário e os da sub-hierarquia (com excluídos) cujo `deleted_at` é igual ao dele, devolvendo quantos voltaram (research R-05)
- [X] T032 [US5] Em `app/Http/Controllers/UsuariosController.php`: ação `excluidos` (mesmos filtros e paginação do `index`, `onlyTrashed` sobre `ids_sub_hierarquia_com_excluidos` de quem pede, exige `usuarios.listar`) e ação `restaurar(Request, Usuarios $usuario)` (binding `withTrashed`; só excluído e da rede, senão 404 "Usuário não encontrado."; exige `usuarios.excluir_<função do alvo>`; superior ainda excluído → 422 "Restaure antes o superior."; chama `restaurar_em_cascata`); em `routes/api.php`, `GET usuarios/excluidos` antes do `apiResource` e `POST usuarios/{usuario}/restaurar` com `->withTrashed()` (depende de T031)
- [X] T033 [US5] Em `resources/js/pages/PanelUsers/index.jsx` e `resources/js/components/UsersTable/index.jsx`: ação ⏻ (com `usuarios.alterar_situacao`) com `confirmar` — ao desativar, "Isso também vai desativar N usuários abaixo dele.", com N = vendedores (gerente) ou gerentes + vendedores (supervisor), só quando N > 0 —, `PATCH /usuarios/{id}` com `ativo`; ação 🗑 (com `usuarios.excluir_<função>`) com confirmação e `DELETE`; na aba Lixeira, só ↺ Restaurar com confirmação e `POST /usuarios/{id}/restaurar`, mostrando a mensagem 422 quando o superior está excluído; recarregar a lista com aviso de sucesso (depende de T016, T032)

**Checkpoint**: US5 validada (quickstart §6)

---

## Phase 8: Polish & Cross-Cutting

- [X] T034 Regenerar `docs/postman/wssports_api.postman_collection.json` substituindo o arquivo, com todas as rotas atuais: as novas `GET usuarios/excluidos`, `POST usuarios/{usuario}/restaurar`, `GET usuarios-configuracoes/{usuario}` e as alteradas (`GET/POST usuarios` com os campos novos, `GET auth/eu`), mantendo `base_url` e `token` (Constituição)
- [X] T035 Revisão de legibilidade de todo o código alterado na feature (Princípio V); registrar em nota no fim de `specs/015-painel-usuarios/tasks.md` o que foi refatorado ou que nada foi necessário
- [ ] T036 Executar o [quickstart.md](quickstart.md) (§1 a §7) no ambiente local e registrar o resultado em nota no fim de `specs/015-painel-usuarios/tasks.md`

---

## Dependencies & Execution Order

- **Setup → Foundational → US1 → US2 → US3**; **US4** e **US5** dependem de US1 (lista) e podem ir em
  qualquer ordem depois de US2 (US4 usa a aba Permissões do modal da US2).
- `UsuariosController.php`, `PanelUsers/index.jsx` e `UserFormModal/index.jsx` são arquivos únicos: as
  tarefas que os alteram são sequenciais.
- A migration (T003) precisa rodar antes de validar qualquer história no banco local.

## Parallel Example

```text
# Phase 2: T005 · T007 · T008 · T009 (arquivos diferentes)
# US1: T015 enquanto T013/T014 são feitas
# US3: T024 · T025 (depois de T009)
# US4: T028 → T029
```

## Implementation Strategy

1. **MVP**: Setup + Foundational + US1 (ver as listas) + US2 (cadastrar e editar).
2. Depois: US3 (comissões e regras), US4 (permissões), US5 (situação e Lixeira), Polish.
3. Cada história validada pelo quickstart antes da próxima; o deploy aplica a migration de permissões
   sozinho (sem seeder).

## Notes

- Sem tarefas de teste (Constituição). Commits só pelo `/speckit-git-commit`.
- Qualquer arquivo fora da lista do plan.md: parar e pedir confirmação (Princípio IV).

## Notas da implementação (2026-10-20)

**Revisão de legibilidade (T035)**: código da feature revisado em conjunto; Pint sem apontamentos.
Refatorado durante a revisão: filtros da listagem extraídos para `UsuariosController::filtros` (usados
pela lista e pela Lixeira); nome da permissão por função centralizado em `Funcao::permissao_usuarios`
(controller e migration); o modal só mostra Salvar na aba Dados para quem pode editar.

**Ajustes em relação ao contrato** (artefatos corrigidos):

- A paginação usa `page`, padrão das outras listagens do projeto (contracts/api §2).
- `comissoes` sai como lista `["5.00","7.00","10.00"]` (data-model §4).
- A gravação em massa responde `vendedores_alterados` (resposta já existente da spec 004).

**Validação feita (T036, parcial)**:

- Migration de permissões: `up` → Admin 9 novas, Supervisor as de gerente e vendedor, Gerente só as de
  vendedor, antigas apagadas; `rollback` devolve as 3 antigas; `migrate` de novo converte outra vez.
- API com o servidor local: listas com superior, comissões e contagens; cadastro do Admin sem superior
  → "Informe o superior."; login com espaço → mensagem do login; cadastro com 5/7/10 → 10 de 4 a 12 e o
  ao vivo igual; Gerente cadastrando gerente → recusado; Gerente informando superior → recusado;
  Vendedor listando → 403; permissões com `disponiveis`; dar `cadastrar_gerente` a um gerente → 422;
  excluir → Lixeira → restaurar; excluir gerente em cascata, restaurar vendedor antes → "Restaure antes o
  superior.", restaurar gerente → volta com os vendedores.
- `npm run build` sem erros; `/painel/supervisores`, `/painel/gerentes` e `/painel/vendedores` → 200.

**Falta validar no navegador** (quickstart §2 a §6): menu e subitens por nível, tabela e cartões no
celular, modal com abas, Regras da equipe/rede com a confirmação, painel de permissões e as
confirmações de situação, exclusão e restauração.

## Ajustes visuais após o teste no navegador (2026-10-21)

Pedidos do responsável (Clarifications 2026-10-21), fora do `/speckit-implement`:

- `PermissionsPanel`: busca, contador, grupos que abrem e fecham, chave "Todas", linha clicável,
  contraste; grupos sem encolher (antes os itens ficavam escondidos).
- `UsersTable`: botões coloridos por ação; no celular tabela só com as colunas principais e
  `ActionsMenu` (novo) nos três pontinhos; cartões removidos.
- Lixeira deixava a tela preta: ao trocar de aba a tabela desenhava os ativos procurando
  `deleted_at` e a data inválida quebrava a página. `useUsuariosPainel` limpa a lista ao trocar de
  aba e a tabela mostra "—" sem data.
- `PanelUsers`: barra em uma linha no celular (busca na lupa, novo só com ícone).
- `UserFormModal`, `PermissionsModal` e `PanelTabs`: tamanho conforme a Constituição v3.6.0 (altura do conteúdo no cadastro, fixa na edição e nas permissões), borda em
  degradê; as abas não somem mais em "Regras da rede".
- `ModalHeader` (novo): ícone, título e descrição nos dois modais (Constituição v3.5.0).
- `npm run build` sem erros; falta conferir no navegador.
- "Esportes permitidos" (`BettingRulesFields`): no lugar do campo de digitar, os esportes do site
  (lista `esportes` do `SportsBar`) para marcar e desmarcar, comparando sem acento; ao salvar ficam
  só os da lista, o que limpa repetidos como "TÊNIS DE MESA"/"TENIS DE MESA" (Constituição v3.7.0).
- `ModalHeader`: ícone sem fundo, na cor do tema (Constituição v3.7.0).
- `BettingRulesFields`: regras de sim/não (Realizar aposta, Cancelar aposta etc.) numa lista "Sim / Não"
  no lugar das chaves; em massa continua com "Não alterar".
- `UserFormModal`: superior numa lista com todos os gerentes/supervisores da rede (carregados de 100
  em 100), no lugar do campo de busca digitável; na edição permite mover o usuário para outro superior
  (`PATCH /usuarios/{id}` com `usuarios_id`, já aceito pela API).
- Campos dos modais (`UserFormModal`, `CommissionFields`, `BettingRulesFields`): sempre rótulo em cima e
  campo embaixo (as regras de sim/não deixaram de ter o rótulo ao lado) e mais espaço entre campos
  (16px entre linhas) e entre grupos (24px).
- Tema claro: bordas dos campos dos modais (`UserFormModal`, `CommissionFields`, `BettingRulesFields`
  e a busca do `PermissionsPanel`) em cinza mais forte (`#a8a8a8`); no escuro continuam iguais.
