---

description: "Lista de tarefas da feature Ajustes da tela principal"
---

# Tasks: Ajustes da tela principal

**Input**: Documentos de design em `specs/006-ajustes-tela-principal/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md),
[data-model.md](data-model.md), [contracts/api.md](contracts/api.md),
[contracts/paginas.md](contracts/paginas.md), [quickstart.md](quickstart.md)

**Tests**: NÃO há tarefas de teste: a constituição proíbe testes automatizados. Cada user story é
validada manualmente pelos cenários do [quickstart.md](quickstart.md).

**Organization**: tarefas agrupadas por user story, na ordem de prioridade da spec (US1 e US2 são
P1; US3 e US4 são P2; US5 e US6 são P3).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: pode rodar em paralelo (arquivos diferentes, sem dependências pendentes)
- **[Story]**: user story da tarefa (US1 a US6)
- Caminhos relativos à raiz do repositório

## Regras que valem para TODAS as tarefas

- **Sistema antigo (Princípios VII e VIII)**: `C:\Users\wedso\OneDrive\Área de Trabalho\projetos\wssports.bet`
  é **somente leitura**. Os arquivos de referência de cada assunto estão no
  [research.md](research.md), R-01; o funcionamento, no R-02; as diferenças aprovadas, no R-03.
- **Nomes (Princípio I, Constituição 3.2.0)**:
  - backend: tabelas, colunas, classes, métodos, variáveis e chaves em português (classes em
    `PascalCase`, o resto em `snake_case`); métodos exigidos pelo framework mantêm o nome (`rules`,
    `messages`, `toArray`, `middleware`, `index`, `store`, `show`, `update`, `destroy`);
    permissões `<recurso>.<acao>`; rotas em kebab-case;
  - frontend: componentes e styled-components em inglês `PascalCase`; variáveis, funções, props,
    chaves e constantes em `snake_case` português; hooks `useXxx` em português; chaves do backend
    como chegam; APIs de bibliotecas e do navegador com o nome original; props só de estilo com
    `$` + snake_case português; utilitários em inglês snake_case (`utils/market_rules.js`).
  - o recurso "popup" do antigo se chama **aviso** em tudo o que é novo (R-13).
- **Componentes (Princípio III)**: cada componente novo em pasta própria (`PascalCase`) com
  `index.jsx` e `styles.jsx`.
- **Idioma (Princípio II)**: comentários, textos e mensagens em português.
- **Escopo (Princípio IV)**: arquivos existentes marcados com ⚠️ estão na tabela do
  [plan.md](plan.md) e foram confirmados pelo responsável em 2026-10-10 (aprovação do relatório
  de análise).
- **Postman (constituição)**: toda tarefa que cria ou altera rota em `routes/api.php` regenera, na
  mesma tarefa, `docs/postman/wssports_api.postman_collection.json` (substituindo o arquivo,
  mantendo `base_url` e `token`) ⚠️.
  Nenhum outro arquivo existente pode ser alterado; se for preciso, avisar antes.
- **Consistência (Princípio VI)**: recursos do painel no padrão de `ClientesPromocoesController` e
  `CampeonatosController` (controller com `HasMiddleware` + `GarantirPermissaoCliente::permissao_cliente`,
  `FormRequest` com `rules()`/`messages()` em português, `JsonResource`, paginação `por_pagina` ≤
  100, soft delete); seeders idempotentes no padrão do `ConfrontosSeeder` (`Permission::firstOrCreate`
  com `guard_name` `api`, `forgetCachedPermissions` e entrega das permissões aos usuários existentes
  pela `permissoes_padrao()` da função).
- **Legibilidade (Princípio V)**: ao concluir cada tarefa, revisar o código alterado e informar se
  houve ou não refatoração.
- **Banco**: tabelas novas com `timestamps()` + `softDeletes()` e model com `SoftDeletes`; datas em
  UTC; dinheiro e cotações em `decimal` + `bcmath` (nunca `float` no cálculo).
- **Frontend**: dinheiro em centavos inteiros (`utils/money.js`); `localStorage` só por
  `utils/storage.js`; cores só por tokens do tema (`props.theme.*`); chamadas de API por
  `utils/api.js` (com o token da sessão, como já faz a tela).
- **Fakes (Princípio IX)**: só em `app/Fakes/DadosFake.php` e `public/fakes/`; esta feature remove os
  de banners, logo e texto das regras (R-16).

---

## Phase 1: Setup (infraestrutura compartilhada)

**Purpose**: declaração da extensão GD e imagens padrão.

- [X] T001 ⚠️ Em `composer.json`, acrescentar `"ext-gd": "*"` em `require` (R-20) e atualizar o `composer.lock` com `composer update --lock` (sem atualizar pacotes)
- [X] T002 [P] ⚠️ Mover `public/fakes/logo.png` para `public/images/logo_padrao.png` e `public/fakes/banners/1.jpg` para `database/seeders/files/banner_padrao.jpg` (criar a pasta `database/seeders/files/`); apagar a pasta `public/fakes/banners/` vazia (R-16). Não apagar `public/fakes/icone.png` nem `public/fakes/icones/` (continuam fake)
- [X] T003 [P] Criar `database/seeders/files/aviso_padrao.png` gerada por um script PHP com GD rodado uma vez (script fora do projeto, no diretório temporário): imagem 500 × 500px com o fundo `#c40808`, o texto "Bem-vindo!" em branco e, abaixo, "Confira as regras antes de apostar." (aviso padrão, FR-027)

**Checkpoint**: `composer validate` sem erro; os três arquivos de imagem existem nos novos caminhos.

---

## Phase 2: Foundational (pré-requisitos de várias stories)

**Purpose**: permissões, configuração (logo e regras) e armazenamento de imagens.

**⚠️ CRITICAL**: as stories US3 a US6 dependem desta fase; US1 e US2 não dependem dela.

- [X] T004 ⚠️ Em `app/Enums/Funcao.php`: criar `PERMISSOES_ESPECIAIS = ['especiais.listar', 'especiais.gerenciar', 'especiais.encerrar']` e `PERMISSOES_SITE = ['avisos.gerenciar', 'banners.gerenciar', 'configuracoes.editar']`, com comentário; incluir as duas listas em `permissoes_padrao()` de Admin e Supervisor; em `pode_usar()`, antes do teste das permissões de clientes, devolver `$this === self::Admin || $this === self::Supervisor` para permissões dessas duas listas (R-09, R-21)
- [X] T005 ⚠️ Em `database/seeders/PapeisPermissoesSeeder.php`, criar com `Permission::firstOrCreate([... 'guard_name' => 'api'])` todas as permissões de `Funcao::PERMISSOES_ESPECIAIS` e `Funcao::PERMISSOES_SITE` (a factory e o cadastro de usuários precisam delas) e entregar `configuracoes.editar` aos usuários Admin e Supervisor existentes; as demais são entregues aos usuários existentes pelos seeders de cada recurso (T039, T076, T083) (R-21)
- [X] T006 ⚠️ Em `app/Models/Configuracoes.php` (feita antes da migration T007, que usa a constante): constante `REGRAS_PADRAO` com as três linhas separadas por `\n`, exatamente: "Prazo de pagamento até 2 dias úteis.", "Não pagará jogos já realizados ou que já estejam rolando e, por falha, continuem no sistema, por erro de hora, cotação ou por jogo antecipado." e "Todos os jogos são definidos ao final dos 90 minutos de jogo, incluindo acréscimos definidos pelos árbitros. Não valerá prorrogação nem disputa de pênaltis." (FR-021b); `protected $attributes = ['regras' => self::REGRAS_PADRAO]`; `logo` e `regras` no `$fillable`; método `url_logo(): string` → `Storage::disk('public')->url($this->logo)` quando há logo, senão `asset('images/logo_padrao.png')`; método `paragrafos_regras(): array` → linhas de `regras` com `trim`, sem as vazias (R-19, R-22)
- [X] T007 Criar a migration `database/migrations/2026_10_10_000001_add_logo_regras_configuracoes_table.php`: em `configuracoes`, `logo` `string(255)->nullable()` (comentário: caminho no disco public, `logos/{sha1}.{ext}`; null = logo padrão) e `regras` `text()->nullable()` (texto das regras da banca, um parágrafo por linha); depois do `Schema::table`, `DB::table('configuracoes')->whereNull('regras')->update(['regras' => Configuracoes::REGRAS_PADRAO])`; `down()` remove as duas colunas (data-model §7, R-22)
- [X] T008 Criar `app/Services/ArmazenamentoImagens.php` (R-20): `salvar(UploadedFile $arquivo, string $pasta, ?int $largura = null, ?int $altura = null): string` — sem tamanho, lê o conteúdo original e usa a extensão pelo tipo (`png`, `jpg` ou `webp`); com tamanho, cria a imagem com `imagecreatefromstring`, redimensiona esticando (sem corte) com `imagecreatetruecolor` + `imagecopyresampled` e gera JPEG qualidade 85 com `imagejpeg` em buffer (`ob_start`); calcula `sha1` do conteúdo final e grava em `{pasta}/{sha1}.{ext}` no disco `public` só se ainda não existir; devolve o caminho relativo. `remover(?string $caminho): void` — não faz nada se nulo ou se ainda houver referência ao caminho em `avisos.imagem`, `banners.imagem` ou `configuracoes.logo` (consultas com `withTrashed` desligado, só registros não removidos); senão apaga do disco `public`. Comentários explicando o hash (cache) e a checagem de referência (dois envios iguais têm o mesmo nome)
- [X] T009 Rodar `php artisan migrate` e `php artisan db:seed --class=PapeisPermissoesSeeder` e conferir que `configuracoes.regras` tem o texto padrão e que o admin tem `configuracoes.editar`

**Checkpoint**: migrations aplicadas; permissões novas no banco; `Configuracoes::atual()->url_logo()` devolve `/images/logo_padrao.png`.

---

## Phase 3: User Story 1 - Vendedor valida a aposta pelo link do bilhete (Priority: P1) 🎯 MVP

**Goal**: `/?code=CÓDIGO` leva o vendedor direto à validação e os demais ao bilhete.

**Independent Test**: [quickstart.md](quickstart.md) seção 2.

- [X] T010 [US1] ⚠️ Em `resources/js/pages/Home/index.jsx` (R-04, FR-001 a FR-004): criar o estado `abriu_com_codigo` (lido uma vez de `new URLSearchParams(window.location.search).get('code')` na montagem) e um `useEffect` que, depois que a sessão estiver definida (`useSessao`, mesmo critério que a tela usa para saber se o apostador já é conhecido), chama uma única vez `buscar_codigo(codigo)` (a mesma da pesquisa, que já leva o vendedor a `abrir_validacao` com a confirmação de substituir o cupom e os demais ao `TicketModal`, e já trata código inexistente) e, em seguida, remove o parâmetro com `window.history.replaceState(window.history.state, '', url_sem_code)` (preservando os outros parâmetros e o `state` do Inertia, sem nova visita); usar um `useRef` para não repetir a busca em recargas parciais. Expor `abriu_com_codigo` para a US5 (o aviso não abre nesse caso)

**Checkpoint**: roteiro da seção 2 do quickstart (passos 1 a 5) completo.

---

## Phase 4: User Story 2 - Vendedor imprime bilhetes e a tabela de jogos (Priority: P1)

**Goal**: menu do vendedor com Impressão, Largura e Tabela; impressão Bluetooth, APP e navegador.

**Independent Test**: [quickstart.md](quickstart.md) seção 3.

### Backend

- [X] T011 [US2] ⚠️ Em `app/Services/ListagemConfrontos.php` (R-08): `pre_jogo` passa a aceitar em `$filtros` a chave opcional `campeonatos` (lista de ids; filtra `whereIn` pelos campeonatos dentro do `dia` pedido, como no `ModalTable` do antigo) e a chave opcional `codigos_cotacao` (lista de códigos; quando vem, só esses códigos são carregados e ajustados pelo `CalculoCotacoes`); sem essas chaves, o comportamento atual não muda
- [X] T012 [P] [US2] Criar `app/Http/Requests/TabelaJogosRequest.php`: `dia` `nullable|in:hoje,amanha` (padrão `hoje`), `esporte` `nullable|string|max:30` (padrão `FUTEBOL`, em maiúsculas), `campeonatos` `nullable|array|max:100`, `campeonatos.*` `integer|distinct`, `pagina` `nullable|integer|min:1`, `por_pagina` `nullable|integer|min:1|max:100` (padrão 100); mensagens em português
- [X] T013 [US2] Criar `app/Services/TabelaJogos.php` com `montar(Usuarios $vendedor, array $filtros): array`: monta o `Publico` do vendedor, recusa com `ValidationException` (`esporte`: "Esporte não permitido.") esporte fora dos `esportes_permitidos`, chama `ListagemConfrontos::pre_jogo` com `codigos_cotacao` = `['odd1','odd2','odd3','odd4','odd116','odd10','odd135','odd15','odd17','odd16','odd7','odd123','odd13','odd139']` e devolve `nome_sistema` (`Configuracoes::atual()`), `atualizada_em` (agora, `-03:00`), `campeonatos[{ id, nome, confrontos[{ id, data_inicio, time_casa, time_fora, cotacoes{codigo: valor} }] }]` (código ausente ou bloqueado → `"1.00"`) e `meta` (contracts/api.md §4)
- [X] T014 [US2] Criar `app/Http/Controllers/TabelaJogosController.php` (`HasMiddleware`, `permissao_cliente('apostas.criar')`) com `index(TabelaJogosRequest $request, TabelaJogos $tabela): JsonResponse` devolvendo o JSON do contrato (§4)
- [X] T015 [US2] ⚠️ Em `routes/api.php`, no grupo `['auth:api', 'garantir_acesso']` existente, acrescentar `Route::get('tabela-jogos', [TabelaJogosController::class, 'index']);` com o `use`; ⚠️ na mesma tarefa, regenerar `docs/postman/wssports_api.postman_collection.json` com as rotas novas (constituição)

### Frontend

- [X] T016 [P] [US2] ⚠️ Em `resources/js/utils/storage.js`: `chave_impressao = 'wssports.impressao'` e `chave_aparelho = 'wssports.aparelho'`, incluídas em `limpar_dados_locais()` (R-05, R-13)
- [X] T017 [P] [US2] Criar `resources/js/utils/bluetooth.js` (R-06): variável do módulo `caracteristica = null`; `bluetooth_disponivel()` (`'bluetooth' in navigator`); `conectar()` → `navigator.bluetooth.requestDevice({ acceptAllDevices: true, optionalServices: ['e7810a71-73ae-499d-8c15-faa9aef0c3f2'] })`, `gatt.connect()`, percorre serviços e características e guarda a primeira com `write` ou `writeWithoutResponse`; ouve `gattserverdisconnected` para zerar `caracteristica`; `escrever(bytes)` → conecta se preciso e envia em blocos de 20 bytes em sequência (`writeValueWithoutResponse` quando disponível, senão `writeValue`); em erro zera `caracteristica` e relança; `NotFoundError` (vendedor cancelou) relançado com `cancelado = true`
- [X] T018 [P] [US2] Criar `resources/js/utils/thermal.js` (R-06): reproduzir `print_ticket_mobile` e `print_table_mobile` de `wssports.bet/resources/js/utils/helpers.js` com os mesmos comandos ESC/POS (`ESC @`, `ESC E 1`, `ESC a 1`, `GS ! 1`, `GS B 1/0`), 48 colunas para 80 mm e 32 para 58 mm, texto sem acentos (`sem_acentos`, equivalente ao `slug`), `linha_rotulo_valor(rotulo, valor, colunas)` (equivalente a `spacePrinter`); exportar `texto_bilhete(comprovante, largura)` (mesmos campos e ordem do antigo, incluindo o palpite especial "Vencedor: categoria") e `texto_tabela(tabela, largura)` (12 colunas em duas linhas: `CASA EMP FORA AMB +2.5 DP.C` = odd1, odd2, odd3, odd4, odd116, odd10 e `GMC GMF 2GMC N.A -2.5 DP.F` = odd15, odd17, odd16, odd7, odd123, odd13; cabeçalho repetido a cada 5 campeonatos), ambos devolvendo `Uint8Array`
- [X] T019 [US2] ⚠️ Em `resources/js/utils/print.js`: exportar `imprimir_tabela(tabela)` que abre a impressão do navegador com o HTML do `table_html` de `wssports.bet/resources/js/screens/main/index.js` (14 colunas: Casa, Empate, Fora, Ambas, +2.5, DPC, CGF, GMC, GMF, 2GMC, N.A, -2.5, DPF, FGC), no mesmo esquema de janela/iframe já usado por `imprimir_bilhete`; em `imprimir_bilhete`, mostrar o palpite especial como "Vencedor: categoria" com a opção e a cotação (FR-008, FR-019)
- [X] T020 [US2] Criar `resources/js/hooks/useImpressao.js` (R-05 a R-07): estado `{ modo, largura }` lido de `chave_impressao` (padrão `{ modo: 'PADRÃO', largura: 80 }`); `alternar_modo()` e `alternar_largura()` gravam no `localStorage`; `imprimir_bilhete(comprovante)`: desktop (`useTelaMobile` falso) → `print.js`; mobile APP → `window.location.href = \`app://${window.location.host}/${codigo}/${largura}/false\``; mobile PADRÃO → sem Bluetooth, `alerta_atencao('Este aparelho não aceita impressão Bluetooth. Use o modo APP no menu.')`; senão `escrever(texto_bilhete(...))`, ignorando cancelamento e mostrando `alerta_erro` nos demais erros; `imprimir_tabela(filtros)`: busca todas as páginas de `GET /api/tabela-jogos` (juntando os campeonatos), "Nenhum jogo encontrado." quando vazia, desktop → `print.js`, mobile → Bluetooth sempre (como no antigo, R-07)
- [X] T021 [P] [US2] Criar `resources/js/components/TableModal/` (`index.jsx`, `styles.jsx`) a partir do `ModalTable` de `wssports.bet/resources/js/modals/table/index.js`: lista de países/campeonatos de `listagem.paises` com marcação por campeonato e os botões "LIMPAR", "IMPR. DE HOJE" e "IMPR. DE AMANHÃ" (chama `ao_imprimir(dia, campeonatos_ids)`), como no antigo, `Backdrop`, fechar pelo X e pelo fundo; cores pelos tokens do tema
- [X] T022 [US2] ⚠️ Em `resources/js/components/SideMenu/index.jsx` (R-05, FR-005): novas props `vendedor` (bool), `impressao` (`{ modo, largura }`), `ao_alternar_modo`, `ao_alternar_largura` e `ao_escolher_tabela(opcao)`; com `vendedor`: no mobile, "Impressão: PADRÃO/APP" e "Largura: 80/58 mm" (texto e ícones iguais aos itens do `main/index.js` antigo) e, em qualquer largura, "Tabela" que abre as opções "Jogos de Hoje", "Jogos de Amanhã" e "Jogos por Campeonatos"; sem `vendedor`, nada muda
- [X] T023 [US2] ⚠️ Em `resources/js/components/SuccessModal/index.jsx`: o botão "Imprimir" chama a prop `ao_imprimir` (padrão: `imprimir_bilhete` de `utils/print.js`), que a `Home` preenche com o `imprimir_bilhete` do `useImpressao` (navegador, Bluetooth ou APP); assim o modal usa as preferências que o menu acabou de mudar
- [X] T024 [US2] ⚠️ Em `resources/js/pages/Home/index.jsx`: usar `useImpressao`; passar ao `SideMenu` `vendedor` (sessão de usuário com `apostador === 'vendedor'`), `impressao` e as ações; "Jogos de Hoje"/"Jogos de Amanhã" → `imprimir_tabela({ dia, esporte })` (ao vivo manda `FUTEBOL`); "Jogos por Campeonatos" → abre o `TableModal` e imprime com `campeonatos`

**Checkpoint**: roteiro da seção 3 do quickstart completo (celular Android com impressora e computador).

---

## Phase 5: User Story 3 - Apostador aposta nos Especiais (Priority: P2)

**Goal**: categorias especiais cadastradas pelo painel, listadas na tela, apostadas no cupom e encerradas.

**Independent Test**: [quickstart.md](quickstart.md) seção 4.

### Banco e models

- [X] T025 [P] [US3] Criar `app/Enums/SituacaoEspecial.php`: enum string com `Aguardando = 'Aguardando'`, `Encerrado = 'Encerrado'`, `Cancelado = 'Cancelado'` (data-model §9)
- [X] T026 [P] [US3] Criar a migration `database/migrations/2026_10_10_000002_create_especiais_table.php` (data-model §1): `id`; `nome` `string(150)` ("obrigatório; único entre as não removidas"); `data_limite` `dateTime` (UTC; "até quando aceita palpite"); `situacao` `string(20)` padrão `Aguardando`; `especiais_opcoes_id_vencedora` `unsignedBigInteger` nulo (FK criada na T027); `ativo` `boolean` padrão `true`; `encerrado_em` `timestamp` nulo; `encerrado_por` FK `usuarios` nula; `timestamps`, `softDeletes`; índice `(situacao, ativo, data_limite)`
- [X] T027 [P] [US3] Criar a migration `database/migrations/2026_10_10_000003_create_especiais_opcoes_table.php` (data-model §2): `id`; `especiais_id` FK `especiais`; `nome` `string(150)`; `cotacao` `decimal(8,2)` ("mínimo 1,01"); `ativo` `boolean` padrão `true`; `timestamps`, `softDeletes`; índice único `(especiais_id, nome, deleted_at)`; depois, FK `especiais.especiais_opcoes_id_vencedora` → `especiais_opcoes`
- [X] T028 [P] [US3] Criar a migration `database/migrations/2026_10_10_000004_add_especiais_apostas_palpites_table.php` (data-model §3): `confrontos_id` e `campeonatos_id` passam a `nullable()` (com `->change()`); novas `especiais_id` e `especiais_opcoes_id` (FK nulas) e `resultado` `string(20)` padrão `Aguardando`; índice único `(apostas_id, especiais_id)`; `down()` reverte
- [X] T029 [P] [US3] Criar `app/Models/Especiais.php` e `app/Models/EspeciaisOpcoes.php` (`SoftDeletes`, `fillable`, `casts`: `data_limite` e `encerrado_em` `datetime`, `situacao` `SituacaoEspecial`, `ativo` `boolean`, `cotacao` `decimal:2`); relações `opcoes()`, `opcao_vencedora()`, `encerrado_por_usuario()`, `especial()`; escopo `visiveis()` em `Especiais`: `ativo`, `situacao = Aguardando`, `data_limite > agora` e `whereHas` de opção ativa
- [X] T030 [US3] ⚠️ Em `app/Models/ApostasPalpites.php`: `especiais_id`, `especiais_opcoes_id` e `resultado` no `fillable`; `resultado` com cast `ResultadoAposta`; relações `especial()` e `opcao_especial()` (R-10)

### Painel: cadastro e encerramento

- [X] T031 [P] [US3] Criar `app/Http/Requests/EspeciaisRequest.php`: `nome` `required|string|max:150` e único entre as não removidas (ignorando a própria no update), `data_limite` `required|date` (sem fuso → `-03:00`, convertido para UTC), `ativo` `boolean`; no `store`, `opcoes` `required|array|min:2`, `opcoes.*.nome` `required|string|max:150|distinct`, `opcoes.*.cotacao` `required|decimal:0,2|min:1.01`; mensagens em português
- [X] T032 [P] [US3] Criar `app/Http/Requests/EspeciaisOpcoesRequest.php` (`nome` `required|string|max:150`, único na categoria entre as não removidas → "Já existe uma opção com este nome na categoria."; `cotacao` `required|decimal:0,2|min:1.01`; `ativo` `boolean`) e `app/Http/Requests/EncerrarEspecialRequest.php` (`especiais_opcoes_id` `required|integer`, da própria categoria → "A opção vencedora não pertence a esta categoria.")
- [X] T033 [P] [US3] Criar `app/Http/Resources/EspeciaisResource.php` e `app/Http/Resources/EspeciaisOpcoesResource.php` (cotação como texto de 2 casas, datas em `-03:00`, opções, vencedora, `quantidade_palpites` em apostas Ativas quando carregada)
- [X] T034 [US3] Criar `app/Http/Controllers/EspeciaisController.php` (contracts/api.md §5): `especiais.listar` em `index`/`show`, `especiais.gerenciar` no resto; `index` com `situacao`, `busca`, `pagina`, `por_pagina` ≤ 100; `store` cria categoria e opções numa transação (`201`); `update` e `destroy` só em `Aguardando` ("A categoria já foi encerrada ou cancelada."); `destroy` recusa com palpite em aposta Ativa (`422`)
- [X] T035 [US3] Criar `app/Http/Controllers/EspeciaisOpcoesController.php` (`store`, `update`, `destroy`; `especiais.gerenciar`; só em categoria `Aguardando`; `destroy` com palpite em aposta Ativa → `422` "Desative a opção em vez de remover.")
- [X] T036 [US3] ⚠️ Em `app/Services/EdicaoApostas.php`: método público `cancelar_palpite_pelo_sistema(Apostas $aposta, ApostasPalpites $palpite, Usuarios $autor): Apostas` que reaproveita `marcar`/`recalcular` e o histórico da spec 004 e permite cancelar o último palpite ativo (sem palpite ativo: cotação total 1,00 e prêmio igual ao valor) (R-11)
- [X] T037 [US3] Criar `app/Services/EncerramentoEspeciais.php` (R-11, data-model §12): `encerrar(Especiais, EspeciaisOpcoes, Usuarios)` e `cancelar(Especiais, Usuarios)`, cada um numa transação com `lockForUpdate` da categoria e recusa fora de `Aguardando`; encerrar grava vencedora, `situacao`, `encerrado_em`/`encerrado_por`, marca `resultado` dos palpites ativos da categoria em apostas Ativas (Vencedor na opção vencedora, Perdedor nas demais); cancelar marca `Cancelado` e cancela cada palpite ativo pelo `cancelar_palpite_pelo_sistema`; depois, recalcula o resultado de cada aposta afetada (Ativa com `resultado = Aguardando`): Perdedor se algum palpite ativo perdeu; Vencedor se não há palpite ativo ou todos os ativos são especiais vencedores; senão Aguardando; nenhuma transação de saldo; devolve `apostas_afetadas`
- [X] T038 [US3] Criar `app/Http/Controllers/EncerramentoEspeciaisController.php` (`encerrar(EncerrarEspecialRequest, Especiais)` e `cancelar(Especiais)`, `especiais.encerrar`, `200` com a categoria e `apostas_afetadas`)
- [X] T039 [US3] Criar `database/seeders/EspeciaisSeeder.php` (permissões de `PERMISSOES_ESPECIAIS`, idempotente, entregues aos Admin e Supervisor existentes) e ⚠️ chamá-lo em `database/seeders/DatabaseSeeder.php`

### Listagem pública e Home

- [X] T040 [P] [US3] Criar `app/Http/Requests/ListagemEspeciaisRequest.php` (`busca` `nullable|string|max:150`, `especial` `nullable|integer`, `pagina`, `por_pagina` `max:100`, padrão 50)
- [X] T041 [US3] Criar `app/Services/ListagemEspeciais.php`: `listar(Publico, array $filtros): array` no formato de contracts/api.md §1 (`tipo: "especial"`, `campeonatos[{ id, nome, data_limite, opcoes[{ id, nome, cotacao }] }]`, `paises: [{ pais: "Especiais", campeonatos[{ id, nome, quantidade_confrontos, bandeira: null }] }]`, `meta`), só `visiveis()` com opções ativas, ordem por `nome` e `data_limite`; lista vazia quando `apostar_outros_esportes` do apostador está desligado (`RegrasExibicao`)
- [X] T042 [US3] Criar `app/Http/Controllers/PublicoEspeciaisController.php` (`index`, identificação do público como em `PublicoConfrontosController`, com `token_recusado`) e ⚠️ em `routes/api.php` acrescentar a rota pública `publico/especiais` e as rotas do painel `especiais/{especial}/encerrar`, `especiais/{especial}/cancelar`, `apiResource('especiais')` e `apiResource('especiais.opcoes')->except(['index','show'])` com os `parameters` de contracts/api.md; ⚠️ na mesma tarefa, regenerar `docs/postman/wssports_api.postman_collection.json` com as rotas novas (constituição)
- [X] T043 [US3] ⚠️ Em `app/Http/Controllers/PaginaInicialController.php`: com `esporte = ESPECIAL`, `listagem` = `ListagemEspeciais::listar` (filtro `campeonato` → `especial`, `busca` mantida) (R-12)

### Aposta com especial

- [X] T044 [US3] ⚠️ Em `app/Support/CodigosCotacao.php`: constante `ESPECIAL = 'especial'` e método novo `e_aposta` (regras + especial), usado no `ApostasRequest`; o `e_regra` não muda, para "especial" não entrar nas regras de porcentagem (R-10)
- [X] T045 [US3] ⚠️ Em `app/Http/Requests/ApostasRequest.php`: `palpites.*.especiais_opcoes_id` `nullable|integer`; exigir um entre `confrontos_id`, `confrontos_ao_vivo_id` e `especiais_opcoes_id`; `codigo_cotacao = especial` exige `especiais_opcoes_id`; duas opções da mesma categoria → "Escolha só uma opção por categoria especial." (contracts/api.md §6)
- [X] T046 [US3] ⚠️ Em `app/Services/RegrasAposta.php` e `app/Services/ConferenciaCotacoes.php`: recusar especial com categoria inativa, fora de `Aguardando`, com `data_limite` passada, ou opção inativa/removida ("A categoria especial {nome} não aceita mais palpites." / "A opção {nome} não está disponível."); cotação vista diferente → mesma regra de cotação alterada dos jogos (409)
- [X] T047 [US3] ⚠️ Em `app/Services/CriacaoApostas.php`: `montar` carrega opções e categorias pedidas e monta o palpite especial com `cotacao_original = cotacao_atual = opcao.cotacao` (sem porcentagens nem teto, FR-016); `garantir_limite_por_confronto` ignora especiais; `gravar_palpites` grava `especiais_id`, `especiais_opcoes_id`, `codigo_cotacao = especial`, `esporte = ESPECIAL`, `confrontos_id`/`campeonatos_id` nulos (R-10)
- [X] T048 [US3] ⚠️ Em `app/Services/ValidacaoCodigos.php`: simulação com especiais, marcando o especial indisponível com o motivo, como os jogos
- [X] T049 [US3] ⚠️ Em `app/Http/Resources/ComprovanteApostaResource.php` e `app/Http/Resources/SimulacaoApostaResource.php`: palpite especial com `time_casa: "Vencedor"`, `time_fora` e `campeonato` = nome da categoria, `mercado` = nome da opção, `data_inicio` = data limite, `esporte: "ESPECIAL"`, `codigo_cotacao: "especial"`, `especiais_opcoes_id` e `resultado`
- [X] T050 [P] [US3] ⚠️ Em `specs/004-apostas/contracts/api.md`, acrescentar a nota do palpite especial nas rotas de aposta, apontando para [contracts/api.md](contracts/api.md) §6

### Frontend

- [X] T051 [P] [US3] Criar `resources/js/components/SpecialCard/` (`index.jsx`, `styles.jsx`) a partir de `wssports.bet/resources/js/screens/main/components/specials/index.js`: cabeçalho da categoria com o `ChampionshipHeader` (nome e data/hora) e as opções com nome e `OddButton` (cotação), selecionada quando está no cupom; props `especial`, `selecionada_id`, `ao_escolher(opcao)`
- [X] T052 [US3] Criar `resources/js/components/SpecialList/` (`index.jsx`, `styles.jsx`): lista de `SpecialCard` com a rolagem infinita e a mensagem de lista vazia da `MatchList`
- [X] T053 [US3] ⚠️ Em `resources/js/hooks/useCupom.js` (R-12): palpite `{ tipo: 'especial', confronto_id: especiais_id, codigo_cotacao: 'especial', opcao_id, mercado, cotacao, time_casa: 'Vencedor', time_fora: categoria, campeonato: categoria, data_inicio: data_limite }`; `mesmo_jogo` já troca/remove por categoria; `palpite_valido` aceita `especial`; `montar_envio` manda `{ especiais_opcoes_id: opcao_id, codigo_cotacao: 'especial', cotacao_vista }`; `versao_formato` não muda
- [X] T054 [US3] ⚠️ Em `resources/js/pages/Home/index.jsx`: com `listagem.tipo === 'especial'`, mostrar `SpecialList` no lugar da `MatchList` e esconder as abas de data; clicar numa opção chama a ação de alternar palpite com o palpite especial

**Checkpoint**: roteiro da seção 4 do quickstart completo.

---

## Phase 6: User Story 4 - Apostador lê as regras da banca (Priority: P2)

**Goal**: `/regras` em blocos: banca, bônus, apostas por mercado e limites; promoções padrão inativas.

**Independent Test**: [quickstart.md](quickstart.md) seção 5.

### Backend

- [X] T055 [P] [US4] Criar `app/Http/Requests/ConfiguracoesRegrasRequest.php`: `regras` `present|nullable|string|max:10000`; mensagens em português
- [X] T056 [US4] Criar `app/Http/Controllers/ConfiguracoesRegrasController.php` (`configuracoes.editar`): `show` → `{"data": {"regras": ...}}`; `update` grava `$request->validated('regras') ?? ''` (string vazia gravada como `""`, R-22) e devolve o texto; ⚠️ em `routes/api.php`, `GET` e `PUT configuracoes/regras` no grupo do painel; ⚠️ na mesma tarefa, regenerar `docs/postman/wssports_api.postman_collection.json` com as rotas novas (constituição)
- [X] T057 [US4] ⚠️ Em `app/Http/Controllers/RegrasController.php` (contracts/paginas.md): props `regras` = `Configuracoes::atual()->paragrafos_regras()`; `regras_bonus` = `ClientesPromocoes::vigentes()->where('ativa', true)` ordenadas por categoria e nome, com os campos do contrato (decimais como texto de 2 casas, datas `-03:00`); `limites_aposta` = configuração de quem vê (`IdentificacaoPublico` + `RegrasExibicao::configuracao`, gestor = visitante) com `valor_minimo_aposta`, `valor_maximo_aposta`, `premio_maximo`, `multiplicador`, `quantidade_minima_opcoes`, `quantidade_maxima_opcoes`, `periodo_jogos`; atualizar o comentário da classe (o texto não é mais fake)
- [X] T058 [US4] ⚠️ Em `app/Fakes/DadosFake.php`, remover o método `regras()` (R-16)
- [X] T059 [US4] Criar `database/seeders/ClientesPromocoesSeeder.php` (R-15): uma promoção por categoria com os valores da tabela do R-15, todas `ativa = false`, `data_inicio = now()`, `data_fim = null`, nome e descrição em português e regras preenchidas; idempotente por `categoria` + `nome` (`firstOrCreate`); ⚠️ chamá-lo em `database/seeders/DatabaseSeeder.php`

### Frontend

- [X] T060 [P] [US4] Criar `resources/js/utils/market_rules.js`: constante `regras_mercados` = lista de `{ titulo, itens: [{ rotulo, texto }] }` com TODOS os mercados e textos de `wssports.bet/resources/js/screens/rules/index.js` (linhas 70 a 1050), na mesma ordem: Principal, Ambas as equipes marcam, Dupla chance, Handicap asiático, Intervalo | Final de jogo, Resultado exato, Total de gols, Gols mais/menos, Gols par/ímpar, Vencedor e ambas equipes, Resultados e total de gols, Empate anula aposta, Escanteios acima/abaixo, Escanteios exatos, Total de escanteios, Time ímpar/par, Ambas marcam 1º/2º tempo, Tempo com mais gols, Time e tempo com mais gols, Time sem sofrer gol, Time sofre gol, Margem de vitória e Time - total de gols; `rotulo` é o `<strong>` do antigo (ex.: "CASA") e `texto` o `<span>`; itens "E assim por diante." com `rotulo` nulo; corrigir só erros de digitação evidentes ("mandamente" → "mandante")
- [X] T061 [P] [US4] Criar `resources/js/components/RulesBlock/` (`index.jsx`, `styles.jsx`) (R-14): seção com fundo `superficie_campeonato` (token do tema), padding 15px, largura 90% centralizada (98% no `mobile`), margem superior 25px; título opcional (`titulo`) centralizado, caixa alta, 11px, peso 500, cor `texto_secundario`, margem inferior 10px (o `h6` de `screens/rules/styles.js`); `children`
- [X] T062 [P] [US4] Criar `resources/js/components/RuleItem/` (`index.jsx`, `styles.jsx`) (R-14): cabeçalho igual ao `Header` de `wssports.bet/resources/js/screens/rules/styles.js` (bordas esquerda e inferior `2px solid theme.principal`, padding 5px, margens 25px acima e 15px abaixo, 0.9em, peso 500, `display: flex`, ícone Material `directions_run` na cor do tema, 1.3em, margem direita 10px) com o `titulo` em caixa alta; abaixo, `children`; exportar também o styled `RuleLine` (parágrafo 0.9em, margem inferior 16px, `strong` para o rótulo)
- [X] T063 [P] [US4] Criar `resources/js/components/BonusRules/` (`index.jsx`, `styles.jsx`): `RulesBlock` "Regras de bônus" com um `RuleItem` por promoção (título "{categoria}: {nome}") e o parágrafo montado como no `screens/rules/index.js` antigo (linhas 41 a 56) a partir dos campos de `regras_bonus` (valor fixo em reais ou percentual do depósito, modalidade, rollover, valor máximo convertido, validade, depósito máximo só em Primeiro depósito e Qualquer depósito, e para Esportes valores mínimo e máximo de aposta e cotações mínimas simples e múltipla), omitindo trechos de campos nulos; valores por `utils/money.js`; não renderiza nada com a lista vazia (FR-022, FR-023)
- [X] T064 [P] [US4] Criar `resources/js/components/MarketRules/` (`index.jsx`, `styles.jsx`): `RulesBlock` "Regras de apostas" com um `RuleItem` por mercado de `regras_mercados` e uma `RuleLine` por item ("**RÓTULO:** texto", sem rótulo quando nulo) (FR-023a)
- [X] T065 [P] [US4] Criar `resources/js/components/BetLimits/` (`index.jsx`, `styles.jsx`): `RulesBlock` "Limites de aposta" com um `RuleItem` "Seus limites" e as linhas: valor mínimo e máximo da aposta e prêmio máximo em reais, multiplicador máximo do prêmio, quantidade mínima e máxima de palpites e período de jogos (texto do `periodo_jogos`) (FR-024)
- [X] T066 [US4] ⚠️ Em `resources/js/components/RulesContent/index.jsx` e `styles.jsx`: receber `regras`, `regras_bonus` e `limites_aposta`; manter `TopBar`/`BackLink` e o atalho do WhatsApp (`Help`); trocar `Hero`, `Card` e a lista numerada por, nesta ordem: `Logo` (`tema.logo`, centralizada), `RulesBlock` sem título com um parágrafo por item de `regras` (texto puro pelo React, sem HTML; não aparece com a lista vazia), `BonusRules`, `MarketRules` e `BetLimits` (FR-021a, FR-025); remover os styled que deixarem de ser usados
- [X] T067 [US4] ⚠️ Em `resources/js/pages/Rules/index.jsx`, receber e repassar `regras_bonus` e `limites_aposta`; ⚠️ em `resources/js/hooks/useSessao.js`, incluir `'limites_aposta'` em `props_do_publico`

**Checkpoint**: roteiro da seção 5 do quickstart completo, nos modos claro e escuro.

---

## Phase 7: User Story 5 - Apostador vê o aviso da banca (Priority: P3)

**Goal**: aviso com Fechar e Lido, por aparelho e por cliente, gerenciado pelo painel.

**Independent Test**: [quickstart.md](quickstart.md) seção 6.

### Backend

- [X] T068 [P] [US5] Criar a migration `database/migrations/2026_10_10_000005_create_avisos_table.php` (data-model §4): `id`; `titulo` `string(100)` nulo ("texto alternativo da imagem e título do modal"); `imagem` `string(255)` ("caminho no disco public, `avisos/{sha1}.{ext}`"); `link` `string(500)` nulo ("URL http(s)"); `inicio_em` e `fim_em` `dateTime` nulos (UTC; "sem valor = já vale" / "sem valor = sem fim; ≥ inicio_em"); `ativo` `boolean` padrão `true`; `timestamps`, `softDeletes`
- [X] T069 [P] [US5] Criar a migration `database/migrations/2026_10_10_000006_create_avisos_leituras_table.php` (data-model §5): `id`; `avisos_id` FK `avisos`; `aparelho` `char(36)` ("UUID do navegador"); `clientes_id` FK `clientes` nula; `ip` `string(45)` nulo ("registro, não usado na regra"); `timestamps`, `softDeletes`; índices único `(avisos_id, aparelho)` e `(avisos_id, clientes_id)`
- [X] T070 [P] [US5] Criar `app/Models/Avisos.php` (`SoftDeletes`, casts de datas e `ativo`, relação `leituras()`, `url_imagem()` pelo disco `public`, escopo `vigentes()`: ativo, `inicio_em` nulo ou ≤ agora, `fim_em` nulo ou ≥ agora) e `app/Models/AvisosLeituras.php` (`SoftDeletes`, relações `aviso()` e `cliente()`)
- [X] T071 [US5] Criar `app/Services/EscolhaAvisos.php`: `atual(string $aparelho, ?Clientes $cliente): ?Avisos` (um `vigentes()` sem leitura do aparelho nem do cliente, `inRandomOrder()`, só com arquivo existente no disco); `ler(Avisos, string $aparelho, ?Clientes, ?string $ip): void` com `firstOrCreate` por `(avisos_id, aparelho)`, preenchendo `clientes_id` quando houver
- [X] T072 [P] [US5] Criar `app/Http/Requests/AvisosRequest.php` (`imagem` obrigatória no store e opcional no update, `image|mimes:jpg,jpeg,png,webp|max:2048`; `titulo` `nullable|string|max:100`; `link` `nullable|url:http,https|max:500`; `inicio_em` `nullable|date`; `fim_em` `nullable|date|after_or_equal:inicio_em`; `ativo` `boolean`) e `app/Http/Requests/LeituraAvisoRequest.php` (`aparelho` `required|uuid`)
- [X] T073 [P] [US5] Criar `app/Http/Resources/AvisosResource.php` (`id`, `titulo`, `imagem` como URL, `link`, `inicio_em`, `fim_em` em `-03:00`, `ativo`, `quantidade_leituras` quando carregada)
- [X] T074 [US5] Criar `app/Http/Controllers/AvisosController.php` (`avisos.gerenciar`; `index` paginado ≤ 100 com `withCount('leituras')`; `store`/`update` salvam a imagem com `ArmazenamentoImagens::salvar($arquivo, 'avisos')` sem redimensionar e, ao trocar, removem a anterior com `remover` depois da transação; `destroy` com soft delete e `remover` da imagem, `204`) (contracts/api.md §7)
- [X] T075 [US5] Criar `app/Http/Controllers/PublicoAvisosController.php` (`atual` com `aparelho` obrigatório e o cliente do token, se houver, `{"data": aviso|null}`; `ler` → `204`, aviso inexistente ou inativo → `404 {"message": "Aviso não encontrado."}`) e ⚠️ em `routes/api.php` acrescentar `publico/avisos/atual` e `publico/avisos/{aviso}/leituras` com `throttle:30,1` e, no painel, `apiResource('avisos')->parameters(['avisos' => 'aviso'])`; ⚠️ na mesma tarefa, regenerar `docs/postman/wssports_api.postman_collection.json` com as rotas novas (constituição)
- [X] T076 [US5] Criar `database/seeders/AvisosSeeder.php` (permissão `avisos.gerenciar` idempotente; se `Avisos::withTrashed()->exists()` for falso, salva `database/seeders/files/aviso_padrao.png` com `ArmazenamentoImagens` e cria o aviso ativo sem link, título "Bem-vindo") e ⚠️ chamá-lo em `database/seeders/DatabaseSeeder.php`

### Frontend

- [X] T077 [P] [US5] Criar `resources/js/components/NoticeModal/` (`index.jsx`, `styles.jsx`) conforme R-13/FR-031: desktop → cartão centralizado (máx. 480px) sobre o `Backdrop`, imagem `object-fit: contain` com altura máxima de 70vh; `mobile` → folha presa embaixo, cantos superiores arredondados, puxador visual, altura máxima de 85vh; botão X no canto superior; rodapé com "Lido" (botão cheio `theme.principal`) e "Fechar" (texto); imagem com link abre em outra aba (`target="_blank"`, `rel="noopener noreferrer"`); `role="dialog"`, `aria-modal="true"`, `aria-label` = título ou "Aviso"; foco inicial no "Lido", Tab preso no modal, Esc fecha; transição de 200 ms (opacidade + deslocamento) com `prefers-reduced-motion`; cores por tokens (modos claro e escuro); props `aviso`, `ao_fechar`, `ao_ler`
- [X] T078 [US5] ⚠️ Em `resources/js/pages/Home/index.jsx` (R-13, FR-028 a FR-030, FR-032): depois da primeira carga da lista e só se `abriu_com_codigo` for falso, obter o aparelho (ler `chave_aparelho`; sem valor, gerar `crypto.randomUUID()` e gravar), chamar `GET /api/publico/avisos/atual?aparelho=` e, havendo aviso, pré-carregar a imagem (`new Image()`) e só então guardar em `aviso_banca` e abrir o `NoticeModal`; "Fechar"/fundo/Esc só fecham; "Lido" pede `confirmar('Realizando essa ação esse aviso não irá aparecer mais para você, confirma?')`, chama `POST /api/publico/avisos/{id}/leituras` com `aparelho` e fecha; erro → `alerta_erro` e o aviso continua aberto. Não confundir com a prop `aviso` da spec 005

**Checkpoint**: roteiro da seção 6 do quickstart completo.

---

## Phase 8: User Story 6 - A banca mostra os próprios banners e a logo (Priority: P3)

**Goal**: carrossel e logo reais, gerenciados pelo painel, com imagens que não ficam presas no cache.

**Independent Test**: [quickstart.md](quickstart.md) seção 7.

### Banners

- [X] T079 [P] [US6] Criar a migration `database/migrations/2026_10_10_000007_create_banners_table.php` (data-model §6): `id`; `imagem` `string(255)` ("caminho no disco public, `banners/{sha1}.jpg`, ajustada para 1280×405"); `link` `string(500)` nulo ("URL http(s)"); `ordem` `unsignedSmallInteger` padrão `0` ("menor aparece primeiro, empate por id"); `ativo` `boolean` padrão `true`; `timestamps`, `softDeletes`; índice `(ativo, ordem)`
- [X] T080 [P] [US6] Criar `app/Models/Banners.php` (`SoftDeletes`, casts, `url_imagem()`, método estático `ativos(): array` → ativos por `ordem` e `id`, só os que têm o arquivo no disco `public` (caso de borda da spec) no formato `[{ imagem: url, link }]`)
- [X] T081 [P] [US6] Criar `app/Http/Requests/BannersRequest.php` (`imagem` obrigatória no store e opcional no update, `image|mimes:jpg,jpeg,png,webp|max:4096` → "Envie uma imagem jpg, png ou webp de até 4 MB."; `link` `nullable|url:http,https|max:500`; `ordem` `nullable|integer|min:0|max:999`; `ativo` `boolean`) e `app/Http/Resources/BannersResource.php` (`id`, `imagem` URL, `link`, `ordem`, `ativo`)
- [X] T082 [US6] Criar `app/Http/Controllers/BannersController.php` (`banners.gerenciar`; `index` paginado ≤ 100 por `ordem` e `id`, filtro `ativo`; `store`/`update` salvam com `ArmazenamentoImagens::salvar($arquivo, 'banners', 1280, 405)` e removem a anterior ao trocar; `destroy` com soft delete e remoção do arquivo, sem recusar o último banner) e ⚠️ em `routes/api.php` acrescentar `apiResource('banners')` no grupo do painel (contracts/api.md §8); ⚠️ na mesma tarefa, regenerar `docs/postman/wssports_api.postman_collection.json` com as rotas novas (constituição)
- [X] T083 [US6] Criar `database/seeders/BannersSeeder.php` (permissão `banners.gerenciar` idempotente; se `Banners::withTrashed()->exists()` for falso, salva `database/seeders/files/banner_padrao.jpg` com `ArmazenamentoImagens::salvar(..., 'banners', 1280, 405)` e cria o banner ativo, `ordem` 0, sem link) e ⚠️ chamá-lo em `database/seeders/DatabaseSeeder.php`
- [X] T084 [US6] ⚠️ Em `app/Http/Controllers/PaginaInicialController.php`, `banners` = `Banners::ativos()` no lugar de `DadosFake::banners()`; ⚠️ em `app/Fakes/DadosFake.php`, remover `banners()` (R-16, R-18)

### Logo

- [X] T085 [P] [US6] Criar `app/Http/Requests/ConfiguracoesLogoRequest.php` (`logo` `required|image|mimes:png,jpg,jpeg,webp|max:1024` → "Envie uma imagem png, jpg ou webp de até 1 MB.")
- [X] T086 [US6] Criar `app/Http/Controllers/ConfiguracoesLogoController.php` (`configuracoes.editar`; `update`: salva com `ArmazenamentoImagens::salvar($arquivo, 'logos')`, grava `configuracoes.logo`, remove a anterior se o caminho mudou e devolve `{"data": {"logo": url_logo()}}`) e ⚠️ em `routes/api.php` acrescentar `Route::post('configuracoes/logo', ...)` no grupo do painel (contracts/api.md §9); ⚠️ na mesma tarefa, regenerar `docs/postman/wssports_api.postman_collection.json` com as rotas novas (constituição)
- [X] T087 [US6] ⚠️ Em `app/Http/Middleware/TratarRequisicoesInertia.php`, `tema` = `[...DadosFake::tema(), 'logo' => Configuracoes::atual()->url_logo()]`; ⚠️ em `app/Fakes/DadosFake.php`, remover a chave `logo` de `tema()` e do PHPDoc (R-19)

**Checkpoint**: roteiro da seção 7 do quickstart completo.

---

## Phase 9: Polish & Cross-Cutting Concerns

**Purpose**: documentação, revisão e validação final.

- [X] T088 ⚠️ Conferência final: regenerar `docs/postman/wssports_api.postman_collection.json` com as pastas novas de R-17: Especiais (painel, com opções e encerrar/cancelar), Avisos (painel, multipart), Banners (painel, multipart), Configurações (`configuracoes/regras` e `configuracoes/logo`), Público (`publico/especiais`, `publico/avisos/atual`, leituras) e Apostas (`tabela-jogos` e o exemplo de palpite especial no corpo)
- [X] T089 Conferir com `grep` que não restou uso de `DadosFake::banners`, `DadosFake::regras`, `/fakes/logo.png` nem `/fakes/banners` em `app/`, `resources/` e `routes/`, e que nenhum nome novo usa "popup"
- [X] T090 Rodar `vendor/bin/pint` nos arquivos PHP novos e alterados e `npm run build`; corrigir avisos
- [X] T091 Revisão de legibilidade (Princípio V) de toda a feature e validação manual completa pelo [quickstart.md](quickstart.md) (seções 1 a 8), registrando no fim deste arquivo o que foi validado e o que ficou para o responsável conferir no aparelho

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sem dependências.
- **Foundational (Phase 2)**: depende da Setup; bloqueia US3 a US6.
- **US1 (Phase 3)** e **US2 (Phase 4)**: dependem só da Setup (não usam permissões novas nem a
  configuração); podem começar logo.
- **US3 a US6 (Phases 5 a 8)**: dependem da Foundational.
- **Polish (Phase 9)**: depois das stories desejadas.

### User Story Dependencies

- **US1**: independente. Cria `abriu_com_codigo`, usado pela US5 (sem ela, a US5 considera falso).
- **US2**: independente; T019 (`print.js`) já mostra o especial se a US3 estiver pronta, e funciona
  sem ela.
- **US3**: independente; o comprovante especial impresso (T018/T019) aparece quando a US2 existe.
- **US4**: depende da Foundational (T006, T007); independente das outras stories.
- **US5**: depende da Foundational (T004, T005, T008) e de T016 (`chave_aparelho`, US2); se a US2
  não estiver pronta, fazer T016 antes.
- **US6**: depende da Foundational (T004 a T008).

### Arquivos compartilhados (fazer em sequência)

- `routes/api.php`: T015 → T042 → T056 → T075 → T082 → T086.
- `resources/js/pages/Home/index.jsx`: T010 → T024 → T054 → T078.
- `app/Fakes/DadosFake.php`: T058 → T084 → T087.
- `database/seeders/DatabaseSeeder.php`: T039 → T059 → T076 → T083.
- `app/Http/Controllers/PaginaInicialController.php`: T043 → T084.

### Within Each User Story

- Migrations e models → services → requests/resources → controllers e rotas → seeders → frontend.

---

## Parallel Example: User Story 3

```text
# Banco e models juntos:
T025 SituacaoEspecial.php
T026 create_especiais_table
T027 create_especiais_opcoes_table
T028 add_especiais_apostas_palpites_table

# Requests, resources e frontend base juntos (depois de T029/T030):
T031 EspeciaisRequest.php
T032 EspeciaisOpcoesRequest.php + EncerrarEspecialRequest.php
T033 EspeciaisResource.php + EspeciaisOpcoesResource.php
T040 ListagemEspeciaisRequest.php
T051 SpecialCard/
```

## Parallel Example: User Story 4

```text
T060 utils/market_rules.js
T061 RulesBlock/
T062 RuleItem/
T063 BonusRules/   (depois de T061 e T062 se for usar os componentes já prontos)
T064 MarketRules/
T065 BetLimits/
```

---

## Implementation Strategy

### MVP First (User Story 1)

1. Phase 1: Setup.
2. Phase 3: US1 (só frontend).
3. **Parar e validar**: quickstart seção 2.

### Incremental Delivery

1. Setup → US1 (link do bilhete) → validar.
2. US2 (impressão e tabela) → validar no Android e no computador.
3. Foundational → US4 (regras) → validar.
4. US3 (especiais) → validar.
5. US5 (avisos) → US6 (banners e logo) → validar.
6. Polish.

---

## Notes

- [P] = arquivos diferentes, sem dependência pendente.
- O responsável confirmou a lista de arquivos existentes do plano (Princípio IV, marcados com ⚠️).
- Commits só quando o responsável rodar `/speckit-git-commit`.

---

## Registro da implementação (2026-10-10)

### Validado pela API e pelo build

- `npm run build`, `php -l` e `vendor/bin/pint` sem erro; 27 rotas novas (`route:list`) e todas na
  coleção do Postman, com `base_url` e `token`.
- **US2**: `GET /api/tabela-jogos` como vendedor devolve os campeonatos com as 14 colunas de cotação.
- **US3**: código de visitante com especial e jogo; duas opções da mesma categoria → 422; simulação
  com "Vencedor: categoria"; aposta do vendedor só com especial; encerrar (vencedora e perdedora) e
  cancelar com os resultados de FR-018a (pendente não é tocada; só especial vencedor → Vencedor;
  com jogo → Aguardando; cancelada só com especial → cotação 1,00 e prêmio = valor); encerrar de
  novo e opção de outra categoria → 422.
- **US4**: `/regras` com os 3 parágrafos padrão, limites do visitante e sem bloco de bônus (as 4
  promoções padrão nascem inativas; seeder rodado 2 vezes sem duplicar).
- **US5**: aviso padrão criado uma vez; aviso atual → "Lido" (204, repetido não duplica) → não volta
  no mesmo aparelho e aparece em outro; aparelho inválido → 422.
- **US6**: banner padrão em 1280×405; mesma logo mantém o endereço, logo nova troca o endereço e
  apaga a anterior; banner PDF → 422; excluir banner apaga o arquivo; `tema.logo` e `banners` da
  tela vêm do banco.

### Ajustes feitos durante a implementação (registrados nos artefatos)

- Permissões novas criadas no `PapeisPermissoesSeeder` (a factory precisa delas); os seeders de
  cada recurso só entregam aos usuários existentes (R-21).
- "Jogos por Campeonatos" segue o `ModalTable` do antigo: campeonatos marcados + "IMPR. DE HOJE" /
  "IMPR. DE AMANHÃ" (R-08, contrato §4).
- `CodigosCotacao::e_aposta` no lugar de mudar `e_regra` (R-10).
- `SuccessModal` recebe a impressão por prop (`ao_imprimir`), para usar as preferências que o menu
  acabou de mudar.
- Imagens servidas por caminho relativo (`/storage/...`), sem depender do `APP_URL` (funciona ao
  abrir pelo IP da rede no celular).

### Para o responsável conferir no aparelho

- Impressão Bluetooth (58 e 80 mm) num Android com impressora térmica, modo APP e iPhone (seção 3).
- Visual da lista de Especiais, do cupom com especial, do `NoticeModal` (cartão/folha, claro e
  escuro) e da página de regras em blocos (seções 4 a 7).
- Link `/?code=` logado como vendedor (seção 2).
