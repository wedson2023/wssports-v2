---

description: "Lista de tarefas da spec 024 — Painel: Especiais"
---

# Tasks: Painel — Especiais (lista, cadastro com opções, exposição, encerramento com uma ou mais vencedoras e anulação de opção)

**Input**: Design documents from `specs/024-painel-especiais/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md),
[data-model.md](data-model.md), [contracts/api.md](contracts/api.md),
[contracts/paginas.md](contracts/paginas.md), [quickstart.md](quickstart.md)

**Tests**: nenhum teste automatizado (constituição). A validação é manual pelo
[quickstart.md](quickstart.md).

**Organization**: tarefas agrupadas por história da spec (US1 a US7, na ordem de prioridade). Cada
tarefa cita os arquivos; arquivos existentes só os listados no plan.md (Princípio IV — lista
aprovada pelo responsável em 2026-10-28, "autorizado alteração"). Ao concluir cada tarefa, revisão
de legibilidade (Princípio V). Dinheiro e cotações com `bcmath` e `DECIMAL` no SQL, devolvidos como
texto com 2 casas; consultas pelo query builder com parâmetros e curingas escapados
(`addcslashes(..., '%_\\')`); comentários e mensagens em português; nomes em snake_case.
Permissões por `permissao_cliente` no controller e `Funcao::usuario_pode` no resource. Modais com
`ModalHeader`, corpo em grade de 2 colunas no computador e 1 no celular, seções com título, rodapé
com "Cancelar" e a ação principal à direita, e `select` Sim/Não no lugar de `ToggleSwitch` (R-14).
Rotas novas com nome fixo (`especiais/resumo`) **antes** do `apiResource('especiais')` em
`routes/api.php`.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: pode rodar em paralelo (arquivos diferentes, sem depender de tarefa incompleta)
- **[Story]**: história da spec (US1 a US7)

---

## Phase 1: Setup

**Purpose**: banco e permissão (data-model §1 a §4 e §6).

- [ ] T001 [P] Criar `database/migrations/2026_10_28_000001_add_vencedoras_especiais_table.php` (data-model §1 e §2, R-05): em `especiais`, `unsignedTinyInteger('quantidade_vencedoras')->default(1)->after('data_limite')->comment('Quantidade de opções vencedoras exigida ao encerrar (padrão 1)')` e `boolean('dividir_empate')->default(false)->after('quantidade_vencedoras')->comment('Se o encerramento dividiu a cotação das opções empatadas na última vaga (dead heat)')`; em `especiais_opcoes`, `boolean('vencedora')->nullable()->after('cotacao')->comment('Resultado da opção ao encerrar: nulo até o encerramento, verdadeiro se venceu')`, `boolean('empatada')->default(false)->after('vencedora')->comment('Vencedora empatada na última vaga, com a cotação dividida (dead heat)')`, `timestamp('anulada_em')->nullable()->comment('Quando a opção foi anulada (palpites devolvidos como cotação 1,00)')` e `foreignId('anulada_por')->nullable()->constrained('usuarios')->comment('Usuário do painel que anulou a opção')`; migrar os dados com o query builder (para cada `especiais` com `especiais_opcoes_id_vencedora`: a opção dela `vencedora = true`, as demais da categoria `false`); depois `dropForeign(['especiais_opcoes_id_vencedora'])` e `dropColumn('especiais_opcoes_id_vencedora')`. `down()` recria a coluna e a FK, preenche com a primeira opção `vencedora = true` de cada categoria e remove as colunas novas
- [ ] T002 [P] Criar `database/migrations/2026_10_28_000002_add_cotacao_empate_apostas_palpites_table.php` (data-model §3): `decimal('cotacao_empate', 8, 2)->nullable()->after('cotacao_final')->comment('Cotação aplicada ao palpite especial pela divisão por empate (dead heat); nula quando não houve divisão')`; `down()` remove
- [ ] T003 [P] Criar `database/migrations/2026_10_28_000003_create_especiais_correcoes_table.php` (data-model §4): `id()`, `foreignId('especiais_id')->constrained('especiais')`, `json` `vencedoras_anteriores`, `empatadas_anteriores`, `vencedoras_novas`, `empatadas_novas`, `boolean` `dividir_empate_anterior` e `dividir_empate_novo`, `unsignedInteger('apostas_afetadas')`, `foreignId('usuarios_id')->constrained('usuarios')`, `timestamps()`, `softDeletes()`; cada coluna com o comentário da tabela do data-model §4; comentário no topo "Histórico das correções de resultado das categorias especiais (spec 024)"
- [ ] T004 [P] Criar `database/migrations/2026_10_28_000004_criar_permissao_corrigir_resultado_especiais.php` no padrão de `2026_10_27_000004_criar_permissao_robo_whatsapp.php`: `const PERMISSAO = 'especiais.corrigir_resultado'`; `Permission::firstOrCreate(['name' => ..., 'guard_name' => 'api'])`; dar aos usuários Admin existentes; limpar o cache do `PermissionRegistrar` antes e depois; `down()` remove a permissão
- [ ] T005 [P] Em `app/Enums/Funcao.php` (data-model §6): incluir `'especiais.corrigir_resultado'` em `PERMISSOES_ESPECIAIS` (docblock: "... e corrigir o resultado (spec 024), só Admin") e, na regra de `pode_usar` que já libera `gateway_pagamento.vincular` e `robo_whatsapp.conectar` só ao Admin, acrescentar `especiais.corrigir_resultado` com o comentário "corrigir o resultado reapura e estorna prêmios de todas as redes (spec 024): só o Admin"
- [ ] T006 [P] Em `resources/js/utils/permissions.js`: no grupo Especiais, `'especiais.corrigir_resultado': { grupo: 'Especiais', nome: 'Corrigir resultado de especiais', descricao: 'Troca as vencedoras de um especial encerrado e reapura as apostas.' }`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: enums, models, resources, serviço da lista, página e menu.

**⚠️ CRITICAL**: nenhuma história começa antes desta fase.

- [ ] T007 [P] Casos novos de enum (data-model §5): `OpçãoAnulada = 'Opção anulada'` em `app/Enums/MotivoCancelamentoPalpite.php`, `CorreçãoEspecial = 'Correção de especial'` em `app/Enums/OrigemApuracao.php` e `DivisãoEmpate = 'Divisão por empate'` em `app/Enums/AcaoHistoricoAposta.php`, cada um com comentário de uma linha (spec 024)
- [ ] T008 Em `app/Models/Especiais.php`: `quantidade_vencedoras` no `$fillable` e `$attributes` (1); casts `quantidade_vencedoras` integer e `dividir_empate` boolean; remover `opcao_vencedora()`; novas relações `vencedoras(): HasMany` (`opcoes()->where('vencedora', true)`) e `correcoes(): HasMany` (`EspeciaisCorrecoes`, mais recente primeiro); método `aguardando_resultado(): bool` (Aguardando e `data_limite` passada); atualizar o docblock ("Encerrada com uma ou mais opções vencedoras...")
- [ ] T009 [P] Em `app/Models/EspeciaisOpcoes.php`: casts `vencedora` e `empatada` boolean e `anulada_em` datetime; método `anulada(): bool` (`anulada_em !== null`); relação `anulada_por_usuario()` (`Usuarios`, `withTrashed`)
- [ ] T010 [P] Em `app/Models/ApostasPalpites.php`: cast `'cotacao_empate' => 'decimal:2'` e o docblock da classe citando a cotação dividida por empate
- [ ] T011 [P] Criar `app/Models/EspeciaisCorrecoes.php` (data-model §4): `SoftDeletes`, tabela `especiais_correcoes`, `$guarded = ['id']` (gravado só pelo serviço), casts `array` nas quatro listas, `boolean` nas duas divisões, relações `especial()` e `usuario()` (`withTrashed`)
- [ ] T012 Em `app/Http/Resources/EspeciaisOpcoesResource.php` (contracts/api §3): acrescentar `situacao` (Anulada / Vencedora / Perdedora / Ativa / Desativada pela tabela do data-model §2, lendo a categoria pela relação `especial` quando carregada ou pelo `especiais_id`), `vencedora`, `empatada` e `anulada_em` (`-03:00`, ISO)
- [ ] T013 Em `app/Http/Resources/EspeciaisResource.php` (contracts/api §1.1–1.2): trocar `opcao_vencedora` por `vencedoras` (`[{id, nome, empatada}]`, das opções carregadas com `vencedora = true`); acrescentar `quantidade_vencedoras`, `dividir_empate`, `aguardando_resultado`, `quantidade_opcoes`, `apostas_em_aberto` e `valor_em_aberto` (quando o atributo veio da consulta), `deleted_at` (`-03:00`), `correcoes` (`whenLoaded`, `EspeciaisCorrecoesResource`) e `acoes` (método privado com `Funcao::usuario_pode($request->user(), ...)`: `detalhes` sempre; fora da lixeira e Aguardando, `editar`/`remover` com `especiais.gerenciar` e `encerrar`/`cancelar` com `especiais.encerrar`; Encerrado, `corrigir_resultado` com `especiais.corrigir_resultado`; na lixeira, `restaurar` com `especiais.gerenciar`)
- [ ] T014 [P] Criar `app/Http/Resources/EspeciaisCorrecoesResource.php` (contracts/api §1.2): `id`, `created_at` (`-03:00`), `usuario` (login), `vencedoras_anteriores`/`vencedoras_novas` como `[{id, nome}]` (nomes das opções `withTrashed`, carregadas uma vez por categoria), `empatadas_anteriores`/`empatadas_novas` (ids), `dividir_empate_anterior`/`dividir_empate_novo`, `apostas_afetadas`
- [ ] T015 Criar `app/Http/Requests/FiltrosEspeciaisPainelRequest.php` (R-02): `authorize()` true; `situacao` `nullable|in:Aguardando,Encerrado,Cancelado,Todas` ("A situação informada é inválida."), `busca` `nullable|string|max:150`, `lixeira` `nullable|boolean`, `por_pagina` `nullable|integer|between:1,100`, com as mensagens atuais do `EspeciaisController@index`; método `filtros(): array` (`situacao` padrão `Aguardando`, `lixeira` padrão falso)
- [ ] T016 Criar `app/Services/ListagemEspeciaisPainel.php` (R-02, R-03) com docblock em português: `consulta(Usuarios $usuario, array $filtros): Builder` — `lixeira` → `Especiais::onlyTrashed()` ordenado por `deleted_at` desc; senão `situacao` (Todas = sem filtro) com a ordem do R-02 (Aguardando por `data_limite` asc; Encerrado/Cancelado por `encerrado_em` desc; Todas com `orderByRaw` Aguardando primeiro); `busca` em `nome` escapada; `withCount` de `quantidade_opcoes` (`ativo` e `anulada_em` nulo); `with('opcoes')`; subconsulta agregada `em_aberto` (`apostas_palpites` `situacao = Ativo` com `especiais_id`, `JOIN apostas` `situacao = Ativa` e `resultado = Aguardando`, no alcance `ListagemApostas::aplicar_alcance` de quem consulta; `COUNT(DISTINCT apostas.id)` como `apostas_em_aberto`, `SUM(apostas.valor)` como `valor_em_aberto`, agrupada por `especiais_id`) por `leftJoinSub`; `resumo(Usuarios, array $filtros): array` com `aceitando_palpites`, `aguardando_resultado`, `valor_em_aberto` e `possivel_retorno_em_aberto` (`premio + valor_acrescido` com a fração de `Apostas::total_a_pagar`), respeitando `busca`; `alcance(Usuarios): string` (`todas` para quem vê todas as redes, senão `rede`, pelo mesmo critério de `ListagemApostas`)
- [ ] T017 Em `app/Http/Controllers/EspeciaisController.php`: `index(FiltrosEspeciaisPainelRequest $request, ListagemEspeciaisPainel $listagem)` passa a usar o serviço (`paginate($filtros['por_pagina'] ?? 20)->withQueryString()`) e devolve `EspeciaisResource::collection(...)->additional(['alcance' => $listagem->alcance($request->user())])`; remover a validação inline que foi para o request; método novo `resumo(Request, ListagemEspeciaisPainel)` (contracts/api §2.1); `show` carrega `opcoes`, `vencedoras` e `correcoes.usuario`; incluir `resumo` no middleware de `especiais.listar`; atualizar o docblock da classe ("... e a tela Especiais, spec 024")
- [ ] T018 Em `routes/api.php`, no grupo de especiais do painel: `Route::get('especiais/resumo', [EspeciaisController::class, 'resumo'])` **antes** do `apiResource('especiais')`, com o comentário "tela Especiais do painel (spec 024)"
- [ ] T019 Registrar `GET painel/especiais` em `routes/web.php` (nome `painel.especiais`, comentário "especiais do painel (spec 024)") e a ação `especiais()` em `app/Http/Controllers/PainelController.php` (Inertia `PanelSpecials`, docblock "Tela "Especiais" (spec 024); aba, busca e filtros vêm na query string.")
- [ ] T020 [P] Em `resources/js/components/PanelShell/index.jsx`: item "Especiais" (chave `especiais`, rótulo `Especiais`, ícone `emoji_events`, `href: '/painel/especiais'`, `visivel` com `especiais.listar`) logo depois de "Confrontos" (contracts/paginas.md §1)
- [ ] T021 Criar `resources/js/hooks/useEspeciaisPainel.js` (no padrão de `useAfiliadosPainel`): filtros no endereço (`aba` = `especiais` | `lixeira`, `busca`, `situacao` (`Aguardando` padrão), `page`) com `router.get(..., { preserveState: true, replace: true })`; `mudar(parcial)` volta à página 1; `trocar_aba(aba)`; carrega `GET /especiais` (`lixeira=1` na aba Lixeira, padrão 20) e, na aba Especiais, `GET /especiais/resumo`; guarda `alcance`; `recarregar()`; exporta `abas_especiais` e `situacoes_especiais`
- [ ] T022 Criar a página `resources/js/pages/PanelSpecials/index.jsx` e `styles.jsx` (contracts/paginas.md §1): `PanelShell` título "Especiais", barra "Especiais — total N", busca com atraso de 400 ms, botões Filtros/Atualizar, `PanelTabs` (Especiais `emoji_events`, Lixeira `delete_outline`), áreas com esqueleto, vazio e erro com "Tentar novamente"; sem `especiais.listar`, volta para `/painel`

**Checkpoint**: migrations rodam; a página abre pelo menu; `GET /api/especiais?situacao=Todas` e `GET /api/especiais/resumo` respondem com os campos novos.

---

## Phase 3: User Story 1 - Consultar os especiais (Priority: P1) 🎯 MVP

**Goal**: cartões, lista filtrada por situação, busca, selo "Aguardando resultado" e cartões no
celular.

**Independent Test**: quickstart §1.

- [ ] T023 [P] [US1] Criar `resources/js/components/SpecialsTable/index.jsx` e `styles.jsx` (contracts/paginas.md §1): colunas da aba Especiais (ID, Nome, Opções, Data limite dd/mm/aaaa hh:mm, Situação com selo e "Aguardando resultado" em âmbar quando `aguardando_resultado`, Ativo, Vencedoras com "empatada", Apostas em aberto, Valor em aberto em reais) e da aba Lixeira (ID, Nome, Opções, Data limite, Situação, Removida em); clique na linha chama `ao_abrir`; `ActionsMenu` com as `acoes` da API (rótulos Ver detalhes, Editar, Encerrar, Corrigir resultado, Cancelar, Remover, Restaurar); no celular (≤ 900 px) um cartão por categoria com as mesmas informações; exporta `rotulos_situacao`
- [ ] T024 [P] [US1] Criar `resources/js/components/SpecialsFilters/index.jsx` e `styles.jsx`: painel de filtros com Situação (Aguardando, Encerrado, Cancelado, Todas) e `ActiveFilters` com a etiqueta removível (remover volta para Aguardando)
- [ ] T025 [US1] Em `resources/js/pages/PanelSpecials/index.jsx`: cartões `StatCard` (Aceitando palpites, Aguardando resultado, Valor apostado em aberto, Possível retorno em aberto) só na aba Especiais, a legenda "Números da sua rede" quando `alcance === 'rede'`, `SpecialsFilters` e `SpecialsTable` ligados ao `useEspeciaisPainel`, paginação

**Checkpoint**: quickstart §1 completo (sem as ações).

---

## Phase 4: User Story 2 - Cadastrar e editar a categoria com as opções (Priority: P1)

**Goal**: modal em grade para cadastrar e editar a categoria e as opções.

**Independent Test**: quickstart §2.

- [ ] T026 [US2] Em `app/Http/Requests/EspeciaisRequest.php` (contracts/api §1.3, R-12): `quantidade_vencedoras` (`sometimes|integer|min:1` e closure: no cadastro, menor que `count(opcoes)`; na edição, menor que as opções da categoria com `anulada_em` nulo) com as mensagens "Informe a quantidade de vencedoras.", "A quantidade de vencedoras deve ser um número inteiro." e "A quantidade de vencedoras deve ser menor que a quantidade de opções."; `dados_categoria()` inclui `quantidade_vencedoras`
- [ ] T027 [US2] Em `app/Http/Controllers/EspeciaisOpcoesController.php` (contracts/api §1.5, R-08): `update` e `destroy` recusam opção anulada com `ValidationException::withMessages(['opcao' => 'A opção foi anulada.'])` (método privado `garantir_nao_anulada`), antes das demais checagens
- [ ] T028 [US2] Criar `resources/js/components/SpecialFormModal/index.jsx` e `styles.jsx` (contracts/paginas.md §3.1): `ModalHeader` (ícone `emoji_events`, "Novo especial" / "Editar especial", "Preencha os dados e as opções do especial."), altura `min(680px, 90dvh)`, corpo rola; seção Dados em grade (Nome com placeholder "Ex.: Campeão Brasileiro 2026", Data limite `datetime-local` no horário de Brasília, Quantidade de vencedoras número padrão 1, Ativo select Sim/Não); seção Opções em grade (Nome, Cotação com `DecimalField`, Ativo select, ícone remover; "Adicionar opção"; cadastro com 2 linhas vazias; anuladas desabilitadas com o selo "Anulada"); aviso "A data limite já passou: o especial não vai aceitar palpites." quando a data é passada; erros do servidor por campo (`opcoes.N.nome` na linha certa); salvar: cadastro em `POST /especiais`; edição em `PUT /especiais/{id}` e depois, em sequência, `POST` das novas, `PUT` das alteradas e `DELETE` das removidas em `/especiais/{id}/opcoes`, parando no primeiro erro com o erro no campo e o aviso "Os dados até aqui já foram salvos." (FR-010); botão salvar com carregando
- [ ] T029 [US2] Em `resources/js/pages/PanelSpecials/index.jsx`: botão "Novo especial" (aba Especiais, com `especiais.gerenciar`), ação Editar (carrega `GET /especiais/{id}` e abre o `SpecialFormModal`) e, ao salvar, mensagem de sucesso e `recarregar()`

**Checkpoint**: quickstart §2.

---

## Phase 5: User Story 3 - Encerrar com uma ou mais vencedoras (Priority: P1)

**Goal**: encerrar com a quantidade de vencedoras da categoria e, com empate na última vaga,
dividir a cotação das empatadas.

**Independent Test**: quickstart §5.

- [ ] T030 [US3] Em `app/Services/EdicaoApostas.php` (R-06): em `recalcular`, a perna usa `'cotacao_final' => (string) ($palpite->cotacao_empate ?? $palpite->cotacao_final)` com o comentário "palpite especial dividido por empate (spec 024) entra com a cotação aplicada"; método público novo `recalcular_pelo_sistema(Apostas $aposta, ApostasPalpites $palpite, AcaoHistoricoAposta $acao, ?Usuarios $autor, ?string $ip, ?string $user_agent): void` que chama o `recalcular_e_registrar` existente, com docblock ("Recalcula o prêmio depois de o sistema mudar a cotação aplicada de um palpite (divisão por empate, spec 024). Deve rodar dentro da transação de quem chama, com a aposta bloqueada.")
- [ ] T031 [US3] Criar `app/Http/Requests/Concerns/VencedorasEspecial.php` (trait, R-07, contracts/api §1.4): regras `vencedoras` `required|array|min:1`, `vencedoras.*` `integer|distinct`, `empatadas` `nullable|array`, `empatadas.*` `integer|distinct`, `dividir_empate` `boolean`; `after()` com a regra de Q, M, E e V da tabela do contracts/api §1.4 e as mensagens exatas ("A opção não pertence a esta categoria ou foi anulada.", "Escolha {Q} opções vencedoras." — "Escolha 1 opção vencedora." quando Q = 1, "Para todas vencerem, cancele a categoria.", "A divisão por empate só vale com mais vencedoras que a quantidade da categoria.", "Marque as vencedoras empatadas na última vaga."); método `resultado(): array` com `vencedoras`, `empatadas` (vazio sem divisão) e `dividir_empate`
- [ ] T032 [US3] Reescrever `app/Http/Requests/EncerrarEspecialRequest.php` para usar a trait `VencedorasEspecial` (sai `especiais_opcoes_id` e o `da_categoria`); docblock "Encerramento de uma categoria especial com uma ou mais vencedoras (spec 024)"
- [ ] T033 [US3] Em `app/Services/EncerramentoEspeciais.php` (R-06, R-07): `encerrar(Especiais $especial, array $resultado, Usuarios $autor, string $ip, ?string $user_agent): int` — bloqueia a categoria Aguardando; grava `situacao`, `dividir_empate`, `encerrado_em`, `encerrado_por`; nas opções da categoria, `vencedora` (true nas `vencedoras`, false nas demais não anuladas) e `empatada`; método privado `cotacao_empate(string $cotacao, int $vagas, int $empatadas): string` = `bcdiv(bcmul($cotacao, (string) $vagas, 4), (string) $empatadas, 2)` (trunca); método privado `aplicar_resultado(Especiais, Collection $palpites, ...)` que, para cada palpite, grava `resultado` (Vencedor/Perdedor) e `cotacao_empate` (só nas empatadas com divisão; `null` nas demais) e, quando a `cotacao_empate` mudou, chama `EdicaoApostas::recalcular_pelo_sistema` com `AcaoHistoricoAposta::DivisãoEmpate` na aposta bloqueada; depois `decidir_apostas`; remove o parâmetro `EspeciaisOpcoes $vencedora` e o `use` sem uso; atualizar o docblock da classe (várias vencedoras, divisão, anulação e correção)
- [ ] T034 [US3] Em `app/Http/Controllers/EncerramentoEspeciaisController.php`: `encerrar` passa `$request->resultado()`, o usuário, o IP e o user agent; a resposta carrega `opcoes` e `vencedoras`; atualizar o docblock
- [ ] T035 [US3] Em `app/Http/Resources/ComprovanteApostaResource.php` (R-13, contracts/api §4): `palpite_especial` ganha `'cotacao_empate' => $palpite->cotacao_empate === null ? null : (string) $palpite->cotacao_empate`
- [ ] T036 [P] [US3] Em `resources/js/components/TicketModal/index.jsx` (contracts/paginas.md §4): no palpite especial com `cotacao_empate`, abaixo da cotação, "Dividida por empate: X (original Y)" com X e Y formatados como as demais cotações
- [ ] T037 [US3] Criar `resources/js/components/SpecialCloseModal/index.jsx` e `styles.jsx` (contracts/paginas.md §3.2), modo `encerrar` | `corrigir`: `ModalHeader` (ícone `flag`, títulos e descrições do contrato); texto "Escolha {Q} opção(ões) vencedora(s)." e as apostas em aberto; lista das opções com caixa de marcar (anuladas desabilitadas; na correção, vencedoras atuais já marcadas); com marcadas > Q, select "Dividir por empate" (Não/Sim) e, com Sim, a marcação "Empatada" por vencedora e a prévia "Cotação aplicada: X" (mesma conta do T033, feita em centésimos inteiros no JS e truncada; só prévia, o valor gravado é o do servidor); aviso da data limite futura no modo encerrar; no modo corrigir, campo Senha e o aviso "Prêmios já pagos a mais serão estornados; o saldo do cliente pode ficar negativo."; erros do servidor nos campos; botão bloqueado enquanto envia; sucesso chama `ao_concluir(resposta)`
- [ ] T038 [US3] Em `resources/js/pages/PanelSpecials/index.jsx`: ação Encerrar (lista e gaveta) abre o `SpecialCloseModal` em `encerrar` (`POST /especiais/{id}/encerrar`); sucesso mostra "Especial encerrado. {N} apostas afetadas.", fecha e recarrega lista, cartões e gaveta aberta; erro "A categoria já foi encerrada ou cancelada." recarrega a lista

**Checkpoint**: quickstart §5.

---

## Phase 6: User Story 4 - Ver a exposição por opção (Priority: P2)

**Goal**: gaveta com os dados e a exposição por opção.

**Independent Test**: quickstart §3.

- [ ] T039 [US4] Criar `app/Services/ExposicaoEspeciais.php` (R-04, contracts/api §2.2) com docblock: `por_opcao(Especiais $especial, Usuarios $usuario): array` — palpites `Ativo` da categoria em apostas `Ativa` e `resultado = Aguardando`, no alcance `ListagemApostas::aplicar_alcance`; subconsulta com a quantidade de palpites ativos por aposta para separar Simples (1) e Múltiplas (> 1); agrupado por `especiais_opcoes_id` com `COUNT(DISTINCT apostas.id)`, `SUM(apostas.valor)` e o possível retorno (`premio + valor_acrescido` com a fração de `total_a_pagar`), somas em `DECIMAL`; todas as opções da categoria (com zeros, inclusive anuladas) com `nome`, `cotacao`, `situacao` (como no `EspeciaisOpcoesResource`) e `empatada`; `total` da categoria; `maior_retorno_opcoes_id`; `alcance`
- [ ] T040 [US4] Em `app/Http/Controllers/EspeciaisController.php`: método `exposicao(Request, Especiais, ExposicaoEspeciais)` (middleware `especiais.listar`) e, em `routes/api.php`, `Route::get('especiais/{especial}/exposicao', ...)` com o `missing` de "Categoria especial não encontrada."
- [ ] T041 [P] [US4] Criar `resources/js/components/SpecialExposure/index.jsx` e `styles.jsx` (contracts/paginas.md §2): tabela Opção · Cotação · Situação · Simples · Múltiplas · Total (quantidade / valor / retorno em reais), linha de total, destaque na `maior_retorno_opcoes_id`, legenda "Números da sua rede" com `alcance === 'rede'`, botão Anular por opção quando `pode_anular` (prop) e a opção não está anulada (`ao_anular(opcao)`); um cartão por opção no celular; esqueleto, vazio e erro com "Tentar novamente"
- [ ] T042 [P] [US4] Criar `resources/js/components/SpecialCorrections/index.jsx` e `styles.jsx` (contracts/paginas.md §2): lista das correções (data, usuário, vencedoras "de → para", empatadas e divisão quando houver, apostas afetadas); não renderiza nada sem correções
- [ ] T043 [US4] Criar `resources/js/components/SpecialDrawer/index.jsx` e `styles.jsx` (no padrão de `MatchDrawer`, com `Backdrop` e `useVoltarFecha`): carrega `GET /especiais/{id}` e `GET /especiais/{id}/exposicao`; cabeçalho com nome, situação e data limite; botões Editar, Encerrar, Cancelar e Corrigir resultado pelas `acoes`; seção Dados (quantidade de vencedoras, ativo, vencedoras com "empatada", divisão por empate, encerrado em/por); `SpecialExposure`; `SpecialCorrections`; método `recarregar` exposto por prop/chave
- [ ] T044 [US4] Em `resources/js/pages/PanelSpecials/index.jsx`: clique na linha e "Ver detalhes" abrem o `SpecialDrawer`; os botões da gaveta abrem os mesmos modais da lista

**Checkpoint**: quickstart §3.

---

## Phase 7: User Story 5 - Anular uma opção (Priority: P2)

**Goal**: anular uma opção, devolvendo os palpites e mantendo a categoria aberta.

**Independent Test**: quickstart §4.

- [ ] T045 [US5] Em `app/Services/EncerramentoEspeciais.php` (R-08): `anular_opcao(Especiais $especial, EspeciaisOpcoes $opcao, Usuarios $autor, string $ip, ?string $user_agent): int` — numa transação, bloqueia a categoria Aguardando (`bloquear_aguardando`) e a opção; recusa já anulada (`ValidationException::withMessages(['opcao' => 'A opção já foi anulada.'])`); grava `anulada_em`, `anulada_por` e `ativo = false`; para cada palpite `Ativo` da opção em aposta `Ativa` e `resultado = Aguardando` (como `palpites_ativos`, filtrando `especiais_opcoes_id`), `EdicaoApostas::cancelar_palpite_pelo_sistema($aposta, $palpite, $autor, $ip, $user_agent, MotivoCancelamentoPalpite::OpçãoAnulada)`; `decidir_apostas` com as apostas afetadas; devolve a quantidade
- [ ] T046 [US5] Em `app/Http/Controllers/EncerramentoEspeciaisController.php` e `routes/api.php`: `anular(Request, Especiais, EspeciaisOpcoes)` (middleware `especiais.encerrar`; 404 "Categoria ou opção não encontrada." quando a opção não é da categoria) respondendo `EspeciaisOpcoesResource` + `apostas_afetadas`; rota `POST especiais/{especial}/opcoes/{opcao}/anular` com o `missing` do grupo
- [ ] T047 [P] [US5] Criar `resources/js/components/SpecialVoidOptionModal/index.jsx` e `styles.jsx` (contracts/paginas.md §3.3): `ModalHeader` (ícone `block`, "Anular opção", "Os palpites nesta opção serão devolvidos."); opção, cotação e apostas em aberto nela; Cancelar · Anular (bloqueado enquanto envia); erro do servidor
- [ ] T048 [US5] Em `resources/js/pages/PanelSpecials/index.jsx`: o Anular do `SpecialExposure` (com `especiais.encerrar` e categoria Aguardando) abre o `SpecialVoidOptionModal` (`POST /especiais/{id}/opcoes/{opcao}/anular`); sucesso mostra "Opção anulada. {N} apostas afetadas." e recarrega a gaveta, a lista e os cartões

**Checkpoint**: quickstart §4.

---

## Phase 8: User Story 7 - Corrigir o resultado de uma categoria encerrada (Priority: P2)

**Goal**: o Admin troca as vencedoras com senha e as apostas são reapuradas.

**Independent Test**: quickstart §6.

- [ ] T049 [US7] Criar `app/Http/Requests/CorrigirResultadoEspecialRequest.php` (R-09, contracts/api §2.4): usa a trait `VencedorasEspecial`; `password` `required|string` com a closure de `ExcluirAfiliadoRequest` ("Confirme com a sua senha." / "Senha incorreta.")
- [ ] T050 [US7] Em `app/Services/EncerramentoEspeciais.php` (R-09, R-10): `corrigir(Especiais $especial, array $resultado, Usuarios $autor, string $ip, ?string $user_agent): int` — numa transação, `Especiais::lockForUpdate()`; recusa não Encerrado (`especial`: "Só uma categoria encerrada pode ter o resultado corrigido."); recusa resultado igual (vencedoras, empatadas e divisão, comparando conjuntos ordenados; `vencedoras`: "O resultado é o mesmo."); grava as opções e `dividir_empate` como no encerrar; seleciona **todos** os palpites `Ativo` da categoria em apostas `Ativa` (qualquer resultado) com `lockForUpdate`; por aposta: `fotografia`, `aplicar_resultado` (mesmo método do T033, que recalcula quando a `cotacao_empate` muda, inclusive para `null`), `ApuracaoApostas::decidir` e `LiquidacaoApostas::liquidar` com `OrigemApuracao::CorreçãoEspecial`; cria o `EspeciaisCorrecoes` com os valores anteriores e novos, o autor e `apostas_afetadas`; devolve a quantidade. Extrair de `decidir_apostas` um parâmetro de origem para reaproveitá-lo aqui
- [ ] T051 [US7] Em `app/Http/Controllers/EncerramentoEspeciaisController.php` e `routes/api.php`: `corrigir(CorrigirResultadoEspecialRequest, Especiais)` com o middleware `especiais.corrigir_resultado` (no `middleware()` da classe, `only: ['corrigir']`, e `especiais.encerrar` passa a `except: ['corrigir']`), resposta como o encerrar (com `correcoes`); rota `POST especiais/{especial}/corrigir-resultado`
- [ ] T052 [US7] Em `resources/js/pages/PanelSpecials/index.jsx`: ação Corrigir resultado (lista e gaveta, pelas `acoes`) abre o `SpecialCloseModal` em `corrigir` (`POST /especiais/{id}/corrigir-resultado` com `password`); sucesso mostra "Resultado corrigido. {N} apostas reapuradas." e recarrega lista, cartões e gaveta

**Checkpoint**: quickstart §6.

---

## Phase 9: User Story 6 - Cancelar, remover e restaurar (Priority: P3)

**Goal**: cancelar e remover pela tela, e restaurar da lixeira.

**Independent Test**: quickstart §7.

- [ ] T053 [US6] Em `app/Http/Controllers/EspeciaisController.php` e `routes/api.php` (R-11, contracts/api §2.5): `restaurar(Especiais $especial)` (middleware `especiais.gerenciar`) — 404 se não estiver removida; recusa nome repetido entre as não removidas (`ValidationException::withMessages(['nome' => 'Já existe uma categoria especial com este nome.'])`); `restore()`; responde `EspeciaisResource` com `opcoes`; rota `POST especiais/{especial}/restaurar` com `->withTrashed()` e o `missing` do grupo
- [ ] T054 [US6] Em `resources/js/pages/PanelSpecials/index.jsx` (contracts/paginas.md §3.4): ações Cancelar (`ConfirmModal` com "Todos os palpites deste especial serão devolvidos (cotação 1,00). {N} apostas em aberto.", `POST /especiais/{id}/cancelar`, sucesso "Especial cancelado. {N} apostas afetadas."), Remover (`ConfirmModal` "O especial vai para a lixeira.", `DELETE /especiais/{id}`, erro do servidor no aviso) e Restaurar na aba Lixeira (`ConfirmModal` "O especial volta para a lista com as opções.", `POST /especiais/{id}/restaurar`); cada sucesso recarrega lista e cartões

**Checkpoint**: quickstart §7.

---

## Phase 10: Polish & Cross-Cutting Concerns

- [ ] T055 Regenerar `docs/postman/wssports_api.postman_collection.json` (FR-028) na pasta de especiais: rotas novas de contracts/api §2 e as alteradas de §1 (filtros de `GET /especiais`, `quantidade_vencedoras`, corpo novo de encerrar), mantendo `base_url` e `token`
- [ ] T056 [P] Atualizar artefatos das specs anteriores (R-15), com a nota "alterado pela spec 024": `specs/006-ajustes-tela-principal/spec.md` (FR-018: uma ou mais vencedoras, quantidade de vencedoras, divisão por empate, anulação de opção, correção de resultado; FR-012: restaurar) e `specs/006-ajustes-tela-principal/contracts/api.md` (encerrar, opções, restaurar, anular, corrigir); `specs/009-apuracao-resultados/spec.md` (origem "Correção de especial" e o motivo "Opção anulada")
- [ ] T057 Conferir em toda a tela, gaveta e modais: esqueleto, vazio e erro com "Tentar novamente" por área; botões com carregando e sem clique duplo; 360 px sem rolagem horizontal; alvos de toque ≥ 40 px; nenhum `ToggleSwitch`; tamanhos de modal da constituição
- [ ] T058 Rodar `php artisan migrate`, `npm run build` e o roteiro do [quickstart.md](quickstart.md) §1 a §8, conferindo no `PermissionsPanel` que `especiais.corrigir_resultado` só é oferecida ao Admin
- [ ] T059 Revisão de legibilidade de todo o código alterado (Princípio V): nomes, comentários, `use` sem uso (o `EspeciaisOpcoes` e o `Rule` que sobrarem em `EncerramentoEspeciais`/`EspeciaisController`), duplicações entre encerrar e corrigir; registrar o resultado no fim deste arquivo

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sem dependências; T001–T006 em paralelo.
- **Foundational (Phase 2)**: depende do Setup (colunas e permissão); bloqueia todas as histórias.
- **US1 (Phase 3)**: depende da Foundational. É o MVP.
- **US2 (Phase 4)**: depende da US1 (tabela e página).
- **US3 (Phase 5)**: depende da US1; T031 é base da US7, T033 é base da US5 e da US7.
- **US4 (Phase 6)**: depende da US1; a gaveta (T043) é usada pelas ações da US5 e da US7.
- **US5 (Phase 7)**: depende de T033 (`decidir_apostas`) e de T041/T043 (botão Anular na gaveta).
- **US7 (Phase 8)**: depende de T031, T033 e T037 (modal no modo corrigir).
- **US6 (Phase 9)**: depende da US1; independente das demais.
- **Polish**: depois das histórias desejadas.

### Within Each User Story

- Backend (request → serviço → controller → rotas) antes dos componentes que chamam as rotas novas.
- Componentes [P] antes da integração na página `PanelSpecials` (as tarefas na página não são
  paralelas entre si, nem as de `EncerramentoEspeciais.php`).

### Parallel Opportunities

- T001–T006 juntos; na Foundational, T007, T009, T010, T011, T014 e T020 em paralelo.
- US1: T023 e T024 em paralelo, depois T025.
- Componentes de histórias diferentes (T036, T041, T042, T047) em paralelo com o backend da mesma
  história.

### Parallel Example: User Story 4

```text
T039 ExposicaoEspeciais → T040 controller e rota
T041 SpecialExposure | T042 SpecialCorrections
                    ↓
          T043 SpecialDrawer → T044 página
```

## Implementation Strategy

1. **MVP**: Setup + Foundational + US1 → a banca consulta os especiais com os cartões e o alcance
   (quickstart §1).
2. **Operação P1**: US2 (cadastro e edição) → US3 (encerrar com várias vencedoras e divisão).
3. **P2**: US4 (gaveta e exposição) → US5 (anular opção) → US7 (corrigir resultado).
4. **P3 e fechamento**: US6 (cancelar, remover, restaurar), Postman, specs 006 e 009, quickstart
   completo e revisão.
