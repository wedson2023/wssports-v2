---

description: "Lista de tarefas da spec 018 — Painel: Apostas"
---

# Tasks: Painel — Apostas (lista de apostas, bilhete, cancelamento, premiados e exportação)

**Input**: Design documents from `specs/018-painel-apostas/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md),
[data-model.md](data-model.md), [contracts/api.md](contracts/api.md),
[contracts/paginas.md](contracts/paginas.md), [quickstart.md](quickstart.md)

**Tests**: nenhum teste automatizado (constituição). A validação é manual pelo
[quickstart.md](quickstart.md) e por um passeio no Chrome sem janela pela tela nova (abas, filtros,
bilhete e modais, sem erro de página).

**Organization**: tarefas agrupadas por história da spec. Cada tarefa cita os arquivos; arquivos
existentes só os listados no plan.md (Princípio IV, confirmados pelo responsável em 2026-10-22,
com a opção A da planilha). Ao concluir cada tarefa, revisão de legibilidade (Princípio V). Valores
em bcmath, consultas pelo query builder com parâmetros, comentários e mensagens em português, nomes
em snake_case. Cancelamento e edição de palpites só pelos services existentes (`CancelamentoApostas`,
`EdicaoApostas`), sem mudá-los.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: pode rodar em paralelo (arquivos diferentes, sem depender de tarefa incompleta)
- **[Story]**: história da spec (US1 a US5)

---

## Phase 1: Setup

**Purpose**: conferir o índice do banco.

- [X] T001 Conferir o índice `apostas(situacao, created_at)`: já existe (spec 014, `2026_10_20_000003_add_indices_dashboard.php`), então nenhuma migration nova (data-model §2, R-04)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: permissões, filtros, consulta base, página e menu.

**⚠️ CRITICAL**: nenhuma história começa antes desta fase.

- [X] T002 Em `app/Enums/Funcao.php`: acrescentar `apostas.listar`, `apostas.exportar` e `apostas.imprimir_segunda_via` em `PERMISSOES_APOSTAS`; em `pode_usar`, fazer as três valerem para todas as funções, como `apostas.cancelar` (ex.: constante `PERMISSOES_APOSTAS_TODOS = ['apostas.cancelar', 'apostas.listar', 'apostas.exportar', 'apostas.imprimir_segunda_via']` usada no `match`); atualizar os docblocks da constante e do método (R-09). `PapeisPermissoesSeeder` não muda (já usa `PERMISSOES_APOSTAS`)
- [X] T003 Criar a migration de dados `database/migrations/2026_10_24_000001_criar_permissoes_apostas_painel.php` no padrão de `2026_10_23_000004_criar_permissoes_caixa.php`: constante local com as três permissões, limpar o cache, `Permission::firstOrCreate` no guard `api`, dar a cada usuário (`withTrashed`) as que estão em `permissoes_padrao()` da função dele; `down()` remove as três (FR-024)
- [X] T004 [P] Acrescentar em `resources/js/utils/permissions.js`, no grupo "Apostas": `apostas.listar` ("Ver apostas" — "Abre a lista de apostas da rede e o bilhete."), `apostas.exportar` ("Baixar apostas" — "Baixa a planilha e gera a imagem dos premiados.") e `apostas.imprimir_segunda_via` ("Imprimir 2ª via" — "Imprime a 2ª via de apostas aguardando resultado.")
- [X] T005 Criar `app/Http/Requests/ApostasFiltrosRequest.php` (contracts/api.md "Filtros comuns"): `de`/`ate` obrigatórios `date_format:Y-m-d`, `ate` ≥ `de`, ≤ hoje em Brasília, no máximo 366 dias; `aba` in `todas,pre_jogo,ao_vivo,canceladas` (padrão `todas`); `busca` string até 60; `todo_periodo` boolean; `resultado` in `Aguardando,Vencedor,Perdedor,Devolvido,Encerrada`; `usuarios_id` inteiro; `forma_pagamento` in os valores de `FormaPagamento`; `premio_minimo` numeric > 0 com até duas casas; `por_pagina` 1 a 100 (padrão 20); mensagens em português; métodos `intervalo()` (`[inicio, fim]` em UTC pelos dias de Brasília com `App\Support\FusoSistema`, como `CaixaPeriodoRequest`), `aba()` e `codigo_buscado(): ?string` (busca com 8 caracteres do alfabeto de `CodigoAposta`, normalizada por `CodigoAposta::normalizar`; senão null)
- [X] T006 Criar `app/Services/ListagemApostas.php` (R-02) com a consulta base `consulta(Usuarios $usuario, array $filtros): Builder` (Eloquent, `Apostas::query()` com o alias `a` via `from('apostas as a')`, para as linhas serem models `Apostas`): alcance por `AlcanceDashboard::ids_vendedores` em `a.usuarios_id` e `a.clientes_id` não nulo só para Admin e Supervisor (FR-003); `a.deleted_at` nulo; situação `Ativa` (abas `todas`, `pre_jogo`, `ao_vivo`) ou `Cancelada` (aba `canceladas`), nunca as demais (FR-004); período por `a.created_at` exceto com código buscado ou `todo_periodo` com busca por nome; tipo `ao_vivo`/`pre_jogo` com EXISTS/NOT EXISTS em `apostas_palpites.confrontos_ao_vivo_id` (mesma regra de `ApuracaoCaixa::somas_apostas`); busca por `a.codigo = ?` ou `a.nome LIKE ?` com `%`, `_` e `\` escapados; `resultado`, `usuarios_id` (conferido no alcance: fora dele → 403 "Você não tem permissão para esta ação."; ignorado para o Vendedor), `forma_pagamento` e `premio_minimo` (`TRUNCATE((a.premio + a.valor_acrescido) * a.fracao_restante, 2) >= ?`); docblock com o porquê de uma consulta única (corrige as cinco consultas do antigo)
- [X] T007 Registrar em `routes/web.php` `GET painel/apostas` → `PainelController@apostas` (nome `painel.apostas`, comentário "apostas do painel (spec 018)") e adicionar a ação `apostas()` em `app/Http/Controllers/PainelController.php` (Inertia `PanelBets`, docblock "Tela Apostas (spec 018); aba, período e filtros vêm na query string")
- [X] T008 [P] Em `resources/js/components/PanelShell/index.jsx`: item "Apostas" (chave `apostas`, ícone `receipt`, `href: '/painel/apostas'`) logo depois de "Caixa", visível com `usuario.permissoes.includes('apostas.listar')` (contracts/paginas.md §1)

**Checkpoint**: `php artisan migrate` cria as permissões; o menu mostra "Apostas".

---

## Phase 3: User Story 1 - Consultar as apostas da rede no período (Priority: P1) 🎯 MVP

**Goal**: lista paginada com cartões de totais, abas, busca e filtros, por nível.

**Independent Test**: quickstart §1 a §4.

### Implementation for User Story 1

- [X] T009 [US1] Em `app/Services/ListagemApostas.php`, `lista(Usuarios, array $filtros, int $por_pagina): LengthAwarePaginator`: `a.codigo`, `a.nome`, valores, `situacao`, `resultado`, `tipo`, `forma_pagamento`, `created_at`, `cancelada_em`; `LEFT JOIN usuarios v` (nome, `ativo`, `deleted_at` → situação `ativo|inativo|excluido`), `LEFT JOIN usuarios c` em `cancelada_por` (nome) e `LEFT JOIN clientes cl` em `a.clientes_id`, com o cliente exibido = `COALESCE(NULLIF(a.nome, ''), cl.nome)` (FR-007); `premio` exibido = total a pagar (`TRUNCATE((premio + valor_acrescido) * fracao_restante, 2)`), ou `valor_pago` na `Encerrada`; subconsultas `jogos_total` (palpites `Ativo`) e `jogos_restantes` (palpites `Ativo` com `resultado = Aguardando`); ordem `a.created_at desc, a.id desc`; `paginate` (padrão 20, máximo 100) (R-04, data-model §3)
- [X] T010 [US1] Em `app/Services/ListagemApostas.php`, `resumo(Usuarios, array $filtros): array` numa consulta agregada sobre a mesma base (R-03): `apostas` = COUNT; `entradas` = SUM(valor); `comissao` = SUM(comissao); `saidas` = `premio` das `Vencedor` + `valor` das `Devolvido` (mesma expressão de `ApuracaoCaixa::somas_apostas`); `abertas` e `em_aberto` das `Aguardando`; `encerramentos` (só Admin e Supervisor; null para os demais) = SUM(`apostas_encerramentos.valor`) das situações `Registrado` e `Creditado` das apostas do filtro; na aba `canceladas`, só `apostas` e `valor_cancelado`; valores como string com duas casas (bcmath); devolver também `periodo` e `limite_planilha` (50000)
- [X] T011a [US1] Em `app/Services/AlcanceApostas.php`: métodos públicos `pode_cancelar(Usuarios, Apostas): bool` e `pode_editar(Usuarios, Apostas): bool` que expõem a regra privada `alcanca`; `garantir_pode_cancelar` e `garantir_pode_editar` passam a usá-los, sem mudar a regra nem as mensagens (U2 do `/speckit-analyze`). Fazer antes de T011
- [X] T011 [US1] Em `app/Services/ListagemApostas.php`, `vendedores(Usuarios, ?string $busca): Collection` com até 20 vendedores do alcance (`AlcanceDashboard::ids_vendedores`, inclusive excluídos), por nome ou login, com `id`, `nome`, `login`, `situacao` (R-10); e `acoes_da_linha(Usuarios, Apostas $linha): array` (as linhas da lista são models `Apostas`, com as colunas extras dos joins) com `ver` e `cancelar` (com `apostas.cancelar`, `AlcanceApostas::pode_cancelar` de T011a e aposta `Ativa` não apurada) — sem repetir as regras de tempo e jogo iniciado, que ficam no service de cancelamento
- [X] T012 [P] [US1] Criar `app/Http/Resources/ApostasListaResource.php` com o formato de contracts/api.md §1.1: `codigo`, `vendedor` (`{id, nome, situacao}` ou null), `origem` (`balcao|site`), `cliente`, `valor`, `comissao`, `premio`, `situacao`, `resultado`, `situacao_exibida` (resultado quando ≠ Aguardando, senão situação), `tipo`, `forma_pagamento`, `jogos_restantes`, `jogos_total`, `criada_em` (ISO no fuso de Brasília), `cancelada_em`, `cancelada_por`, `acoes`
- [X] T013 [US1] Criar `app/Http/Controllers/ApostasListagemController.php` (middleware `permissao_cliente('apostas.listar')` como os controllers do Caixa, `GarantirPermissaoCliente`): `index(ApostasFiltrosRequest)` → `ApostasListaResource::collection` paginada; `resumo(ApostasFiltrosRequest)` → contracts/api.md §1.2; `vendedores(Request)` (busca até 60; Vendedor → 403 "Você não tem permissão para esta ação.") → §1.3
- [X] T014 [US1] Registrar em `routes/api.php`, no grupo `auth:api` + `garantir_acesso` das apostas do painel e antes de qualquer `apostas/{codigo}`: `GET apostas` (index), `GET apostas/resumo`, `GET apostas/vendedores`, com o comentário "lista de apostas do painel (spec 018)"
- [X] T015 [P] [US1] Criar `resources/js/components/ActiveFilters/{index,styles}.jsx`: etiquetas dos filtros ativos ("Vendedor: Fulano ✕", "Prêmio ≥ R$ 100,00 ✕", "Premiadas ✕", "Saldo ✕"), prop `filtros`, `ao_remover(chave)`; legível nos dois temas (contracts/paginas.md §3)
- [X] T016 [P] [US1] Criar `resources/js/components/SellerPicker/{index,styles}.jsx`: campo de busca com resultados de `GET /apostas/vendedores` (espera 300 ms ao digitar), mostra nome, login e etiqueta inativo/excluído, `valor` ({id, nome}) e `ao_escolher`; sem texto livre gravado (constituição, "Campos de itens existentes")
- [X] T017 [US1] Criar `resources/js/components/BetsFilters/{index,styles}.jsx`: chips de Resultado (Aguardando, Premiadas, Perdidas, Devolvidas, Encerradas; some na aba Canceladas), `SellerPicker` (não aparece para o Vendedor), chips de Forma de pagamento (Dinheiro, Saldo, Promoção esportes) e Prêmio mínimo com `useCampoValor`; no computador, painel abaixo do cabeçalho que aplica a cada mudança; no celular, modal de tela cheia com `ModalHeader` (ícone `filter_list`, "Filtros", "Escolha o que quer ver na lista de apostas.") e botões Limpar e Aplicar (contracts/paginas.md §3)
- [X] T018 [US1] Criar `resources/js/components/BetsTable/{index,styles}.jsx`: colunas CÓDIGO, VENDEDOR, CLIENTE, VALOR, COMISSÃO, PRÊMIO, SITUAÇÃO, JOGOS, HORÁRIO, AÇÃO (na aba Canceladas, CANCELADA no lugar de JOGOS); código em maiúsculas; vendedor com etiqueta inativo/excluído ou "Site"; etiqueta da forma de pagamento quando ≠ Dinheiro; selo da situação (Aguardando neutro, Vencedor verde, Perdedor vermelho, Devolvido azul, Encerrada roxo, Cancelada cinza, nos dois temas); linha clicável (`ao_abrir`); `ActionsMenu` com as `acoes(linha)` recebidas; no celular (até 900 px), cartões com código e situação no topo, cliente, valor e prêmio em destaque e "ver mais"; esqueleto, vazio ("Nenhuma aposta encontrada. Troque o período ou os filtros.") e erro com "Tentar novamente" (contracts/paginas.md §5, no padrão de `CashTable`)
- [X] T019 [US1] Criar `resources/js/hooks/useApostasPainel.js`: lê e grava na query string `aba`, `de`, `ate`, `busca`, `todo_periodo`, `resultado`, `usuarios_id` (+ nome do vendedor para a etiqueta), `forma_pagamento`, `premio_minimo`, `page` (FR-011); carrega `GET /apostas` e `GET /apostas/resumo` em paralelo, cada um com carregando/erro próprios (FR-028); `recarregar()`; trocar aba ou filtro volta para a página 1
- [X] T020 [US1] Criar `resources/js/pages/PanelBets/{index,styles}.jsx` no `PanelShell`: título "Apostas · total N"; `PeriodFilter` (spec 017); busca "Código ou nome do cliente" (Enter ou 400 ms) com a caixa "Buscar em todo o período" quando a busca não é código; botão Filtros com a contagem; atualizar; `PanelTabs` Todas (`receipt_long`), Pré-jogo (`schedule`), Ao vivo (`play_circle_outline`), Canceladas (`delete_outline`), dividindo a largura no celular; `ActiveFilters`; cartões `StatCard` na ordem de contracts/paginas.md §4 (Encerramentos só quando vier no resumo; aba Canceladas com Canceladas e Valor cancelado; duas colunas no celular); `BetsTable`; `Pagination`; sem `apostas.listar` → `router.visit('/painel')`

**Checkpoint**: quickstart §1 a §4 passam.

---

## Phase 4: User Story 2 - Conferir o bilhete de uma aposta (Priority: P1)

**Goal**: bilhete com dados completos, compartilhar, acompanhar e 2ª via.

**Independent Test**: quickstart §5.

### Implementation for User Story 2

- [X] T021 [US2] Em `app/Services/ListagemApostas.php`, `buscar_visivel(Usuarios, string $codigo): Apostas` (mesmo alcance e situações da lista; senão 404 "Aposta não encontrada.") e `acoes_do_bilhete(Usuarios, Apostas): array` com `cancelar` (como T011), `editar_palpites` (`apostas.editar`, `AlcanceApostas::pode_editar` de T011a, aposta `Ativa` não apurada) e `imprimir_segunda_via` (`apostas.imprimir_segunda_via`, `Ativa` com `resultado = Aguardando`)
- [X] T021a [P] [US2] Em `app/Http/Resources/ComprovanteApostaResource.php`, acrescentar `motivo_cancelamento` (valor do enum `MotivoCancelamentoPalpite` ou null) em cada palpite, sem mudar os demais campos (U1 do `/speckit-analyze`)
- [X] T022 [US2] Em `app/Http/Controllers/ApostasListagemController.php`, `show(Request, string $codigo)` → `{ data: new ComprovanteApostaResource($aposta, mostrar_comissao: true), painel: { cancelada_em, cancelada_por (nome), acoes } }` (contracts/api.md §2.1)
- [X] T023 [US2] Criar `app/Http/Controllers/ApostasSegundaViaController.php` (`__invoke`, middleware `permissao_cliente('apostas.imprimir_segunda_via')`): `ListagemApostas::buscar_visivel`; aposta não `Ativa` aguardando → 422 "A 2ª via só pode ser impressa em apostas aguardando resultado."; resposta `{ data: ComprovanteApostaResource }` (R-06, FR-016)
- [X] T024 [US2] Registrar em `routes/api.php`, depois das rotas fixas de T014: `GET apostas/{codigo}` (show) e `GET apostas/{codigo}/segunda-via`, com `->where('codigo', '[A-Za-z0-9]{8}')`
- [X] T025 [P] [US2] Em `resources/js/utils/print.js`: `imprimir_bilhete(comprovante, { segunda_via = false } = {})` acrescenta, com `segunda_via`, a linha centralizada "2ª via" logo abaixo do nome do sistema em `comprovante_html`; sem a opção, o comprovante fica igual (R-06)
- [X] T026 [P] [US2] Em `resources/js/utils/thermal.js`: `texto_bilhete(comprovante, largura, { segunda_via = false } = {})` com a linha "2a via" centralizada abaixo do nome do sistema quando pedida; sem a opção, igual (R-06)
- [X] T027 [US2] Em `resources/js/hooks/useImpressao.js`: `imprimir_bilhete(comprovante, opcoes = {})` repassa `segunda_via` para o navegador e o Bluetooth e, no modo APP, usa `true` no último segmento do endereço `app://` quando `segunda_via` (hoje sempre `false`); comentário explicando o segmento, como no antigo
- [X] T028 [US2] Em `resources/js/components/TicketModal/{index,styles}.jsx`: props opcionais `painel` (`{ cancelada_em, cancelada_por, acoes }`) e `ao_imprimir`; com `painel`, o cabeçalho passa a ser o `ModalHeader` (ícone `receipt`, "Bilhete {CÓDIGO}", "Confira os palpites, o andamento dos jogos e os valores da aposta.", com os ícones de ação à direita), e sem `painel` fica o cabeçalho atual (C1 do `/speckit-analyze`); palpite cancelado riscado com o motivo (`motivo_cancelamento`, ou "Cancelado no bilhete" sem motivo); com `comprovante.comissao`, linhas Comissão e Forma de pagamento no rodapé; na cancelada, a faixa "Cancelada em dd/mm/aaaa hh:mm por Fulano"; ícone `print` no cabeçalho só com `imprimir_segunda_via` em `painel.acoes`; compartilhar com fallback "Link copiado." quando o aparelho não compartilha; sem as props, site e Dashboard ficam iguais (R-05, contracts/paginas.md §6)
- [X] T029 [US2] Em `resources/js/pages/PanelBets/index.jsx`: abrir o bilhete pela linha e pelo menu (`GET /apostas/{codigo}`, carregando no modal), imprimir 2ª via (`GET /apostas/{codigo}/segunda-via` e `useImpressao().imprimir_bilhete(dados, { segunda_via: true })`, mensagem do servidor quando recusar) e "Compartilhar" no menu da linha com o link `/bilhete/{codigo}`; fechar o bilhete não perde filtros, página nem posição (FR-030)

**Checkpoint**: quickstart §5 passa; o bilhete do site e da Dashboard continua igual.

---

## Phase 5: User Story 3 - Cancelar aposta e editar palpites (Priority: P2)

**Goal**: cancelar pela lista e pelo bilhete, cancelar/restaurar palpite, pelas regras atuais.

**Independent Test**: quickstart §6.

### Implementation for User Story 3

- [X] T030 [US3] Em `resources/js/components/TicketModal/{index,styles}.jsx`: props opcionais `ao_cancelar_aposta`, `ao_cancelar_palpite(palpite)`, `ao_restaurar_palpite(palpite)`; botão "Cancelar aposta" (vermelho) no rodapé só com `cancelar` em `painel.acoes`; por palpite, `delete_outline` (cancelar) ou `refresh` (restaurar) só com `editar_palpites` (FR-018, contracts/paginas.md §6)
- [X] T031 [US3] Em `resources/js/pages/PanelBets/index.jsx`: "Cancelar aposta" no menu da linha (com `cancelar` em `linha.acoes`) e no bilhete, com confirmação "Cancelar a aposta {CÓDIGO}? O valor volta para o limite do vendedor (ou para o saldo do cliente)." e `POST /apostas/{codigo}/cancelar`; cancelar e restaurar palpite com confirmação e `POST /apostas/{codigo}/palpites/{id}/cancelar|restaurar`; depois de cada ação, mensagem de sucesso, recarregar o bilhete (`GET /apostas/{codigo}`), a lista e o resumo sem perder a página; recusa → mensagem do servidor e nada muda (FR-017, FR-019)
- [X] T032 [US3] Em `resources/js/components/BetsTable/index.jsx`: na aba Canceladas, a coluna CANCELADA com data, hora e autor (no celular, no "ver mais") e nenhuma ação além de Ver bilhete e Compartilhar (Clarifications: aba só de consulta)

**Checkpoint**: quickstart §6 passa.

---

## Phase 6: User Story 4 - Ver as apostas de um vendedor a partir do Caixa (Priority: P3)

**Goal**: atalho "Ver apostas" na linha do vendedor do Caixa.

**Independent Test**: quickstart §7.

### Implementation for User Story 4

- [X] T033 [US4] Em `resources/js/pages/PanelCash/index.jsx`: acrescentar a ação `ver_apostas` (ícone `receipt_long`, título "Ver apostas") em `acoes_da_linha` quando a lista é de vendedores e `usuario.permissoes` inclui `apostas.listar`, abrindo `/painel/apostas?usuarios_id={id}&vendedor={nome}&de={de}&ate={ate}&aba={tipo}` com o período e o tipo atuais do Caixa (`todas`, `pre_jogo`, `ao_vivo`); as `acoes` da API do Caixa não mudam (R-11, FR-025)
- [X] T034 [P] [US4] Em `specs/017-painel-caixa/spec.md`, acrescentar à Clarification "O atalho 'ver apostas do vendedor' do antigo entra?" a nota "Alterado pela spec 018: o atalho 'Ver apostas' passou a existir no menu da linha do vendedor."

**Checkpoint**: quickstart §7 passa.

---

## Phase 7: User Story 5 - Premiados do período e exportação (Priority: P4)

**Goal**: imagem dos premiados e planilha .xlsx.

**Independent Test**: quickstart §8.

### Implementation for User Story 5

- [X] T035 [US5] Criar `app/Services/PremiadosApostas.php`: `premiados(Usuarios, array $filtros): array` com até 23 apostas da base de `ListagemApostas` (aba e vendedor do filtro, sem resultado, busca e forma de pagamento) com `resultado = Vencedor`, ordenadas pelo total a pagar desc (`codigo`, `vendedor` ou "Site", `cliente`, `valor`, `premio`, `situacao_exibida`), mais `nome_sistema`, `url_logo` (`Configuracoes::atual()->url_logo()`) e `periodo` (R-07)
- [X] T036 [US5] Criar `app/Http/Controllers/ApostasPremiadosController.php` (`__invoke(ApostasFiltrosRequest)`, middleware `permissao_cliente('apostas.exportar')`; Vendedor → 403 "Você não tem permissão para esta ação.") → contracts/api.md §3.1
- [X] T037 [US5] Criar `app/Support/PlanilhaPainel.php` movendo de `app/Services/ExportacaoCaixa.php` o método `gerar` e os auxiliares `escrever_linha`, `escrever_valores`, `colorir_sinal` e `coluna` (com as constantes de cor e o formato de moeda), como métodos públicos/estáticos que recebem título, cabeçalho, colunas, linhas, total e o nome da aba da planilha, gravando num arquivo temporário único e devolvendo o caminho (R-08, opção A)
- [X] T038 [US5] Alterar `app/Services/ExportacaoCaixa.php` para usar `PlanilhaPainel` no lugar dos métodos movidos em T037, sem mudar o resultado da planilha do Caixa (mesmas abas, cores, formatos e nomes); conferir gerando a planilha da rede e dos clientes antes e depois
- [X] T039 [US5] Criar `app/Services/ExportacaoApostas.php`: conta as apostas do filtro (`ListagemApostas::consulta`); acima de 50.000 → `RegraApostaException`/422 "O filtro tem N apostas; o máximo por planilha é 50.000. Reduza o período ou use filtros."; senão monta pelo `PlanilhaPainel` o título "Apostas", o quadro com período, aba, filtros aplicados e totais (`ListagemApostas::resumo`) e as colunas CÓDIGO, VENDEDOR, CLIENTE, VALOR, COMISSÃO, PRÊMIO, SITUAÇÃO, JOGOS (restantes/total), HORÁRIO (dd/mm/aaaa hh:mm) e, na aba Canceladas, CANCELADA EM e CANCELADA POR, lendo as linhas em blocos (`chunkById`) com as mesmas expressões da lista (R-08, FR-021)
- [X] T040 [US5] Criar `app/Http/Controllers/ApostasExportacaoController.php` (`__invoke(ApostasFiltrosRequest)`, middleware `permissao_cliente('apostas.exportar')`) → download `apostas-{de}-{ate}.xlsx` com `deleteFileAfterSend`, como `CaixaExportacaoController` (contracts/api.md §4.1)
- [X] T041 [US5] Registrar em `routes/api.php`, junto das rotas fixas de T014 (antes de `apostas/{codigo}`): `GET apostas/premiados` e `GET apostas/exportacao`
- [X] T042 [P] [US5] Criar `resources/js/utils/winners_image.js`: `gerar_imagem_premiados(dados, cores): Promise<Blob>` desenhando com Canvas 2D o logo (carregado de `url_logo`), "Premiados · {período}" e a tabela CÓDIGO, VENDEDOR, CLIENTE, VALOR, PRÊMIO, SITUAÇÃO nas cores do tema, com textos longos cortados com reticências (R-07)
- [X] T043 [US5] Criar `resources/js/components/WinnersModal/{index,styles}.jsx`: `ModalHeader` (ícone `emoji_events`, "Premiados do período", "Imagem com os maiores prêmios para compartilhar."), prévia da imagem, "Nenhuma aposta premiada no período." quando vazio, botões Compartilhar (`navigator.share` com o arquivo PNG, só quando `navigator.canShare({ files })`) e Baixar (`premiados-{de}-{ate}.png`); altura do conteúdo e 100% no celular (constituição, "Tamanho dos modais")
- [X] T044 [US5] Em `resources/js/pages/PanelBets/index.jsx`: botão Premiados (`emoji_events`, só Admin, Supervisor e Gerente com `apostas.exportar`) que abre o `WinnersModal` com `GET /apostas/premiados`; botão Baixar planilha (`table_view`, com `apostas.exportar`) com carregando e sem duplo clique, que avisa antes de baixar quando `resumo.totais.apostas > resumo.limite_planilha` e baixa `GET /apostas/exportacao` como blob (mensagem do servidor no erro) (contracts/paginas.md §7 e §8)

**Checkpoint**: quickstart §8 passa; a planilha do Caixa continua igual.

---

## Phase 8: Polish & Cross-Cutting Concerns

- [X] T045 Regenerar `docs/postman/wssports_api.postman_collection.json` (substituindo o arquivo) com as 7 rotas novas: `GET /apostas`, `/apostas/resumo`, `/apostas/vendedores`, `/apostas/premiados`, `/apostas/exportacao`, `/apostas/{codigo}`, `/apostas/{codigo}/segunda-via`, mantendo `base_url` e `token`
- [X] T046 Revisão de legibilidade de todo o código alterado na spec (Princípio V): nomes, duplicação entre lista/resumo/planilha/premiados, métodos longos em `ListagemApostas` e `PanelBets`, comentários; registrar o resultado nas notas abaixo
- [X] T047 Validação: `php artisan migrate` → `migrate:rollback --step=1` → `migrate`; `npm run build` sem erros; desempenho do SC-007 com uma massa de 50.000 apostas no período (dentro de transação desfeita no fim): `GET /apostas` e `GET /apostas/resumo` em até 2 s para Admin, Gerente e Vendedor, registrando os tempos; quickstart §0 a §10 pela API e pela tela; Chrome sem janela em `/painel/apostas` (1366 px e 360 px; Admin, Gerente e Vendedor; abas, filtros, bilhete, premiados) sem erro de página e sem rolagem horizontal; registrar nas notas

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)** e **Foundational (Phase 2)**: antes de todas as histórias.
- **US1 (Phase 3)**: depende da Phase 2. É o MVP.
- **US2 (Phase 4)**: depende de US1 (a página e a consulta base); T025 e T026 podem começar junto com US1.
- **US3 (Phase 5)**: depende de US2 (o bilhete com `painel.acoes`).
- **US4 (Phase 6)**: depende de US1 (a tela que recebe os filtros pela URL).
- **US5 (Phase 7)**: depende de US1 (consulta base e resumo); T037/T038 (planilha) independem do frontend.
- **Polish (Phase 8)**: depois das histórias.

### Within Each User Story

- Service → resource/controller → rotas → componentes → página.
- `routes/api.php`: T014 → T024 → T041, nesta ordem (rotas fixas antes de `apostas/{codigo}`).
- `PanelBets/index.jsx`: T020 → T029 → T031 → T044.
- `TicketModal`: T028 → T030.
- `AlcanceApostas`: T011a antes de T011 e T021. `ComprovanteApostaResource`: T021a antes de T028.

### Parallel Opportunities

- Phase 2: T004 e T008 junto com T002/T003/T005/T006.
- US1: T012, T015 e T016 em paralelo; depois T017 e T018.
- US2: T025 e T026 em paralelo (arquivos diferentes), antes de T027.
- US4: T034 em paralelo com T033.
- US5: T042 em paralelo com o backend (T035 a T041); T037 → T038 em sequência.

## Parallel Example: User Story 1

```text
Task: "T012 [P] [US1] ApostasListaResource em app/Http/Resources/ApostasListaResource.php"
Task: "T015 [P] [US1] ActiveFilters em resources/js/components/ActiveFilters/"
Task: "T016 [P] [US1] SellerPicker em resources/js/components/SellerPicker/"
```

## Implementation Strategy

### MVP First (User Story 1)

1. Phase 1 e Phase 2.
2. Phase 3 (US1): lista, cartões, abas, busca e filtros.
3. **Parar e validar** pelo quickstart §1 a §4.

### Incremental Delivery

1. US1 → consulta das apostas (MVP).
2. US2 → bilhete, compartilhar e 2ª via.
3. US3 → cancelar e editar pela tela.
4. US4 → atalho do Caixa.
5. US5 → premiados e planilha.
6. Polish → Postman, revisão e validação.

## Notes

- Commits só via `/speckit-git-commit`.
- Arquivos existentes alterados: só os da tabela do plan.md (Princípio IV), incluindo
  `AlcanceApostas.php` e `ComprovanteApostaResource.php`, aceitos após o `/speckit-analyze`.
- Datas de exemplo na validação: horário de Brasília.

## Notas da implementação (2026-10-22)

**Ajustes em relação ao plano e aos contratos** (artefatos corrigidos):

- T001: o índice `apostas(situacao, created_at)` já existia (spec 014,
  `2026_10_20_000003_add_indices_dashboard.php`); a única migration da spec é a de permissões, que
  passou a ser `2026_10_24_000001_criar_permissoes_apostas_painel.php`.
- Busca: há apostas com códigos de antes do alfabeto atual (ex.: `Y607HZVO`). Por isso a busca com 8
  letras ou números procura o código em qualquer data **ou** o nome no período (contracts/api.md,
  research R-02).
- Bilhete no painel: o `ModalHeader` compartilhado só tem o botão de fechar, então os ícones
  compartilhar, acompanhar e imprimir 2ª via ficam numa barra logo abaixo dele (contracts/paginas §6).
  O site e a Dashboard continuam com o cabeçalho atual; o motivo do palpite cancelado aparece também
  no site (aceito no `/speckit-analyze`).
- Compartilhar: usa o mesmo utilitário do site (`utils/share.js`, fora da lista de arquivos), que abre
  o WhatsApp quando o aparelho não compartilha, no lugar de copiar o link (spec FR-015 e Edge Cases
  ajustados).
- Planilha: `PlanilhaPainel::gerar` ganhou o parâmetro opcional `$larguras`. Com ele, as linhas
  alternadas viram uma regra de formatação condicional e as colunas têm largura fixa. A planilha do
  Caixa continua igual, conferida célula a célula (valores, formatos, cores, negrito, preenchimento,
  filtro e painel congelado) antes e depois da mudança. A rota da planilha de apostas tem
  `set_time_limit(180)`.

**Validação feita**:

- `migrate` sem erro; Admin, Supervisor, Gerente e Vendedores com as três permissões novas.
- API (em transações desfeitas no fim):
  - alcance: Admin 86 e Supervisor 86 (com as do site), Gerente 56, Vendedor 36;
  - os totais do Gerente batem com o Caixa (Entradas R$ 1.284,00, Saídas R$ 496,45);
  - abas Canceladas (11), Ao vivo (3) e Pré-jogo (83);
  - filtros de resultado, prêmio mínimo, forma de pagamento e vendedor;
  - vendedor fora da rede → 403; período futuro → 422;
  - busca por nome no período e em todo o período; código em outra data encontrado;
  - bilhete com `painel.acoes` por nível; aposta de cliente → 404 para o Gerente e 200 para o
    Supervisor;
  - 2ª via: 200 em aposta aguardando e 422 em decidida;
  - premiados: 16 para o Admin e 403 para o Vendedor; vendedores do filtro: 403 para o Vendedor;
  - cancelar e restaurar palpite com o prêmio recalculado e o motivo no bilhete;
  - cancelar a aposta: ela vai para Canceladas com o autor; cancelar de novo → 422;
  - planilha das abas Todas e Canceladas com cabeçalho, filtros, totais e linhas.
- Desempenho (SC-007), com 50.076 apostas no banco: lista, totais e busca em 110 a 240 ms para
  Admin, Gerente e Vendedor.
- `npm run build` sem erros; Pint sem pendências nos arquivos da spec. O aviso de ordem dos imports
  em `routes/api.php` já existia antes.
- Chrome sem janela em `/painel/apostas`, 1366 px e 360 px, como Admin, Gerente e Vendedor:
  - lista, abas, filtros (chip Premiadas grava `resultado=Vencedor` na URL), bilhete com
    `ModalHeader` e imagem dos premiados funcionando;
  - sem erro de página e sem rolagem horizontal;
  - o único 403 do console vem de `/configuracoes/gateway`, chamado por outra parte do painel.

**Pendente de decisão do responsável**: a planilha de 37 mil linhas levou 44 a 48 s e 330 MB, e
7,4 mil linhas levaram 8 s. No limite de 50.000 linhas, a estimativa é de cerca de 60 s e 450 MB,
perto do `memory_limit` de 512 MB do PHP local. Opções: manter 50.000 (com o tempo maior e o risco de
memória no servidor) ou baixar o limite (ex.: 10.000, perto de 10 s).

**Falta validar no navegador** (quickstart): impressão real da 2ª via (navegador, Bluetooth e APP),
cancelar e editar pela tela com o usuário logado, o download da planilha pelo navegador, o tema claro
e o atalho "Ver apostas" a partir do Caixa.

**Revisão de legibilidade (Princípio V)**: feita no conjunto. Os filtros ficam numa consulta única
(`ListagemApostas::consulta`), usada pela lista, pelos totais, pela planilha e pelos premiados, e as
expressões de prêmio e de jogos ficam num lugar só (`EXPRESSAO_PREMIO` e `com_colunas_da_lista`). Não
encontrei outra refatoração necessária.

**Ajuste pedido pelo responsável depois da implementação (2026-10-22)**: no bilhete do painel, a
ação de cada palpite virou um botão de texto ("Cancelar palpite" em vermelho, "Restaurar palpite" em
verde) alinhado à direita no rodapé do palpite (no bloco Criar Aposta, só o ícone ao lado da
cotação). O modal do painel ficou com 92% da altura e até 640 px de largura
(`components/TicketModal/{index,styles}.jsx`). Conferido no Chrome sem janela em 1366 e 360 px; o
bilhete do site não muda.

**Segundo ajuste pedido pelo responsável (2026-10-22)**: até 400 px, as abas Todas, Pré-jogo, Ao vivo e
Canceladas ficam com a mesma largura (120 px) numa linha só, com rolagem para o lado; Filtros e
Premiados dividem a primeira linha das ferramentas, e Baixar planilha desce para a segunda linha, à
esquerda, ao lado do Atualizar (`pages/PanelBets/{index,styles}.jsx`). Conferido no Chrome sem janela
em 380 px.

**Terceiro ajuste pedido pelo responsável (2026-10-22)**: no computador, a busca fica à direita das
abas, ocupando o resto da linha (no celular continua acima das abas), e a caixa passou a se chamar
"Todo o período", com a explicação na dica. O "Limpar filtros" do painel de filtros fica na linha
do Prêmio mínimo, alinhado ao campo, com borda e texto na cor de destaque do tema
(`pages/PanelBets/{index,styles}.jsx`, `components/BetsFilters/{index,styles}.jsx`). Conferido no
Chrome sem janela em 1366 e 380 px.

**Quarto ajuste pedido pelo responsável (2026-10-22)**: o topo da tela virou uma grade no computador.
As abas ficam na mesma largura dos balões do período, divididas por igual; a busca fica na mesma
largura do grupo de ferramentas; o painel de filtros e as etiquetas ocupam a largura toda entre as
duas linhas (`pages/PanelBets/{index,styles}.jsx`; os estilos `Header` e `TabsRow` saíram, e entraram
`Top` e `Area`). Medido no Chrome sem janela em 1366 px: abas e balões de 214 a 758 px; busca e
ferramentas de 942 a 1352 px. Em 380 px, a ordem do celular continua a mesma.

## Phase 9: Restaurar aposta cancelada (pedido do responsável depois da implementação, FR-019a)

- [X] T050 Criar `database/migrations/2026_10_24_000002_add_restauracao_apostas_table.php` com `restaurada_em`, `restaurada_por` (FK `usuarios`), `ip_restauracao` e `user_agent_restauracao`, todas com `->comment()` (data-model §6)
- [X] T051 Criar `database/migrations/2026_10_24_000003_criar_permissao_restaurar_apostas.php` e acrescentar `apostas.restaurar` em `Funcao::PERMISSOES_APOSTAS` (`app/Enums/Funcao.php`) e em `resources/js/utils/permissions.js`
- [X] T052 Criar `app/Services/RestauracaoApostas.php` (R-13) e tornar público `CancelamentoApostas::tem_jogo_iniciado` (`app/Services/CancelamentoApostas.php`)
- [X] T053 Criar `app/Http/Controllers/RestauracaoApostasController.php` e a rota `POST apostas/{codigo}/restaurar` em `routes/api.php`
- [X] T054 Em `app/Services/ListagemApostas.php`, a ação `restaurar` em `acoes_da_linha` e `acoes_do_bilhete`
- [X] T055 Em `resources/js/pages/PanelBets/index.jsx` e `resources/js/components/TicketModal/{index,styles}.jsx`, "Restaurar aposta" no menu da linha (aba Canceladas) e no rodapé do bilhete cancelado, com confirmação
- [X] T056 Acrescentar "Restaurar aposta cancelada" em `docs/postman/wssports_api.postman_collection.json`

**Validação da restauração** (API em transação desfeita):

- Vendedor → 403.
- Sem limite → 422, e a aposta continua cancelada.
- Gerente restaura e o limite geral é abatido de novo (1000 → 980); como havia jogo encerrado, a
  aposta foi apurada de novo (Perdedor).
- Restaurar de novo → 422.
- Aposta do site: Gerente → 404; Admin restaura e o saldo é debitado de novo (6,10 → 16,10 → 6,10).
- Cliente sem saldo → 422.
- Bilhete e linha mostram `restaurar` só na cancelada e para quem pode.

No Chrome sem janela, o bilhete cancelado aberto pelo Gerente mostra "Restaurar aposta". `migrate`
sem erro; Pint sem pendências.
