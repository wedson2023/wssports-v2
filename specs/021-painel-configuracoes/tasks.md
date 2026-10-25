---

description: "Lista de tarefas da spec 021 — Painel: Configurações gerais"
---

# Tasks: Painel — Configurações gerais

**Input**: Design documents from `specs/021-painel-configuracoes/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md),
[data-model.md](data-model.md), [contracts/api.md](contracts/api.md),
[contracts/paginas.md](contracts/paginas.md), [quickstart.md](quickstart.md)

**Tests**: nenhum teste automatizado (constituição). A validação é manual pelo
[quickstart.md](quickstart.md).

**Organization**: tarefas agrupadas por história da spec. Cada tarefa cita os arquivos; arquivos
existentes só os listados no plan.md (Princípio IV, **aguardando a confirmação do responsável**). Ao
concluir cada tarefa, revisão de legibilidade (Princípio V). Comentários e mensagens em português,
nomes em snake_case, valores com 2 casas, consultas pelo Eloquent/query builder com parâmetros,
segredos nunca em resposta, log ou histórico.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: pode rodar em paralelo (arquivos diferentes, sem depender de tarefa incompleta)
- **[Story]**: história da spec (US1 a US7)

---

## Phase 1: Setup

**Purpose**: dependências, configuração e estrutura do banco (data-model §1 e §2).

- [ ] T001 [P] Adicionar `ezyang/htmlpurifier` `^4.18` ao `composer.json` (`composer require`, atualiza o `composer.lock`) — research R-08
- [ ] T002 [P] Adicionar ao `package.json` (`npm install`, atualiza o `package-lock.json`) `@tiptap/react`, `@tiptap/pm`, `@tiptap/starter-kit`, `@tiptap/extension-text-align` e `@tiptap/extension-text-style`, todos `^3` — research R-07
- [ ] T003 [P] Em `config/services.php`, bloco `evolution` com comentário em português (R-10): `url` (`EVOLUTION_API_URL`), `chave` (`EVOLUTION_API_CHAVE`, chave global, nunca vai para log), `instancia` (`EVOLUTION_API_INSTANCIA`, vazio = host do `app.url` em minúsculas com só letras, números e hífen), `webhook_token` (`EVOLUTION_WEBHOOK_TOKEN`), `tempo_limite` 10; e as quatro variáveis comentadas em `.env.example`
- [ ] T004 [P] Criar `database/migrations/2026_10_27_000001_add_banca_visual_configuracoes_table.php` (data-model §1), cada coluna com o comentário do data-model: `whatsapp` string(20) nullable; `mensagem_whatsapp` string(500) nullable default `'Olá! Vim pelo site e gostaria de fazer uma aposta esportiva. Pode me ajudar?'`; `botao_whatsapp` boolean default true; `instagram`, `facebook`, `twitter`, `youtube` string(255) nullable; `jogo_responsavel` string(255) nullable default `'https://www.gamblingtherapy.org/pt-br/'`; `cor_tema` string(7) default `'#c40808'`; `cor_fundo` string(7) default `'#000000'`; `gtm_id` string(20) nullable; `pasta_icones` string(100) nullable; `robo_whatsapp_situacao` string(30) default `'Desconectado'`; `robo_whatsapp_numero` string(20) nullable; `robo_whatsapp_perfil` string(100) nullable; `robo_whatsapp_atualizado_em` timestamp nullable; `down()` remove todas
- [ ] T005 [P] Criar `database/migrations/2026_10_27_000002_create_configuracoes_historicos_table.php` (data-model §2): `id()`; `usuarios_id` `foreignId` → `usuarios` ("Autor da alteração"); `secao` string(30) ("Aba ou seção: banca, logo, banners, avisos, regras, mercado_pago, robo_whatsapp, pagamentos, criar_aposta, apuracao, encerramento, cassino, afiliados, avisos_whatsapp, ao_vivo, sorteio_cotacoes ou notificacoes"); `acao` string(20) ("Ação: Alteração, Cadastro, Exclusão, Vínculo, Desvínculo, Conexão ou Desconexão"); `item_id` unsignedBigInteger nullable ("Banner ou aviso alterado (seções banners e avisos); nulo nas demais"); `alteracoes` json nullable ("Campos alterados {campo: {antes, depois}}; segredos como \"***\"/\"alterada\"; nulo nas ações sem campos"); `timestamps()`; `softDeletes()`; índice `(secao, created_at)`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: enums, histórico, model, página com abas, formulário genérico e menu.

**⚠️ CRITICAL**: nenhuma história começa antes desta fase.

- [ ] T006 [P] Criar os enums com docblock em português (data-model §2 e §3): `app/Enums/AcaoConfiguracao.php` (`Alteração`, `Cadastro`, `Exclusão`, `Vínculo`, `Desvínculo`, `Conexão`, `Desconexão`, valores iguais ao nome) e `app/Enums/SituacaoRoboWhatsapp.php` (`Desconectado = 'Desconectado'`, `AguardandoLeitura = 'Aguardando leitura'`, `Conectado = 'Conectado'`)
- [ ] T007 Criar `app/Models/ConfiguracoesHistoricos.php` (tabela `configuracoes_historicos`, `SoftDeletes`, `fillable` = `usuarios_id`, `secao`, `acao`, `item_id`, `alteracoes`; casts `acao` → `AcaoConfiguracao`, `alteracoes` → `array`; relação `autor()` → `Usuarios` com `withTrashed`), no padrão de `PorcentagensHistoricos`
- [ ] T008 Criar `app/Services/HistoricoConfiguracoes.php` (R-16), no padrão de `HistoricoCotacoes`: constante `SEGREDOS = ['chave_callmebot']`; `alterar(Usuarios $autor, string $secao, Model $registro, array $dados): Model` que, em `DB::transaction`, guarda os originais das chaves de `$dados`, faz `update`, compara (enums por `->value`, datas em ISO, decimais com 2 casas, arrays por `json_encode`) e grava só os campos mudados com `acao` `Alteração` (segredo como `{"antes": "***", "depois": "alterada"}`); sem mudança não grava; `registrar(Usuarios $autor, string $secao, AcaoConfiguracao $acao, ?int $item_id = null, array $alteracoes = []): void`
- [ ] T009 Em `app/Models/Configuracoes.php` (data-model §1): `$fillable` ganha `whatsapp`, `mensagem_whatsapp`, `botao_whatsapp`, `instagram`, `facebook`, `twitter`, `youtube`, `jogo_responsavel`, `cor_tema`, `cor_fundo`, `gtm_id` (não `pasta_icones` nem `robo_whatsapp_*`); casts `botao_whatsapp` boolean, `robo_whatsapp_situacao` → `SituacaoRoboWhatsapp`, `robo_whatsapp_atualizado_em` datetime; constantes `CORES_TEMA` (`'#c40808' => ['nome' => 'Vermelho', 'escura' => '#a41f1a']`, `'#d0af01' => Amarelo/#9f8601`, `'#008000' => Verde/#005400`, `'#006eb1' => Azul/#024b77`, `'#fe6a00' => Laranja/#b94e02`, `'#b91552' => Rosa/#930137`) e `CORES_FUNDO = ['#000000', '#FFFFFF']`; métodos `tema(): array` (`temas`, `letter`, `cor_fundo`, `logo`) e `contatos(): array` (`whatsapp`, `mensagem_whatsapp`, `botao_whatsapp`, `instagram`, `youtube`, `twitter`, `facebook`, `jogo_responsavel`) no formato de contracts/paginas.md §1
- [ ] T010 Registrar `GET painel/configuracoes` em `routes/web.php` (nome `painel.configuracoes`, comentário "configurações gerais do painel (spec 021)") e a ação `configuracoes()` em `app/Http/Controllers/PainelController.php` (Inertia `PanelSettings`, docblock "aba e seção vêm na query string")
- [ ] T011 [P] Em `resources/js/components/PanelShell/index.jsx`: item "Configurações" (chave `configuracoes`, ícone `settings`, `href: '/painel/configuracoes'`) no fim da lista, visível com `configuracoes.editar`; comentário da lista cita a spec 021
- [ ] T012 [P] Criar `resources/js/hooks/useSecaoConfiguracoes.js` (R-17): recebe `{ rota, token, habilitado }`; carrega `GET rota` (com o token no cabeçalho, como `useBonusPainel`), guarda `originais` e `valores`, `mudar(campo, valor)`, `alterados` (só as chaves diferentes do original), `quantidade`, `desfazer()`, `salvar()` (`PUT rota` só com os alterados; 422 → `erros` por campo; sucesso → originais = resposta), `carregando`, `salvando`, `erro_carga`, `recarregar()`
- [ ] T013 [P] Criar `resources/js/components/SettingsForm/` (`index.jsx`, `styles.jsx`): recebe `campos` (lista `{ chave, rotulo, tipo, ajuda, opcoes, minimo, maximo, casas, somente_leitura, visivel(valores) }`), `valores`, `erros`, `ao_mudar`; renderiza em grade de 2 colunas (1 no celular) por tipo: `interruptor` (`ToggleSwitch`), `valor` (campo de reais com `useCampoValor`), `percentual`, `decimal`, `inteiro`, `selecao`, `chips` (marcar vários), `texto`, `texto_longo`, `telefone`, `link`, `segredo` (mostra "definida (final XXXX)" e um campo para trocar); rótulo, dica e erro embaixo de cada campo
- [ ] T014 [P] Criar `resources/js/components/SettingsSection/` (`index.jsx`, `styles.jsx`): cartão com ícone (Material Icons na cor do tema, sem fundo), título, frase do que controla, conteúdo e, quando `recolhivel`, cabeçalho clicável de sanfona (`aberta`, `ao_alternar`); estados de carregando (`Spinner`) e erro com "Tentar novamente"
- [ ] T015 Criar `resources/js/utils/settings_fields.js` (R-17) com o comentário do formato e, nesta tarefa, as definições da aba Banca: `campos_identificacao` (`nome_sistema` texto até 100, `mensagem_bilhete` texto_longo até 500), `campos_atendimento` (`whatsapp` telefone com ajuda "Com DDI e DDD, ex.: 5511999999999", `mensagem_whatsapp` texto_longo, `botao_whatsapp` interruptor "Botão flutuante do WhatsApp"), `campos_redes` (`instagram`, `facebook`, `twitter` com rótulo "Twitter/X", `youtube`, `jogo_responsavel` como link) e `campos_rastreamento` (`gtm_id` texto com ajuda "Deixe vazio para não usar")
- [ ] T016 Criar a página `resources/js/pages/PanelSettings/index.jsx` e `styles.jsx` (contracts/paginas.md §5): `PanelShell` título "Configurações", `PanelTabs` (Banca `storefront`, Visual `palette`, Regras `gavel`, Integrações `hub`, Operação `tune`) com a aba em `?aba=` (`router.get` com `preserveState`/`replace`); sem `configuracoes.editar`, volta para `/painel`; troca de aba com alterações não salvas pergunta (`confirmar` de `utils/alerts`) e `beforeunload` enquanto houver pendência; áreas vazias para as abas, preenchidas pelas histórias

**Checkpoint**: tela abre pelo menu (Admin e Supervisor) com as cinco abas.

---

## Phase 3: User Story 1 - Dados da banca e contatos do site (Priority: P1) 🎯 MVP

**Goal**: aba Banca grava nome, bilhete, WhatsApp, redes, jogo responsável, tema e GTM-ID; o site
usa os dados reais no lugar dos fakes.

**Independent Test**: quickstart §1 e §2.

### Implementation for User Story 1

- [ ] T017 [P] [US1] Criar `app/Http/Requests/ConfiguracoesBancaRequest.php` (data-model §1, contracts/api.md §1.1), todos `sometimes`: `nome_sistema` required string max:100; `mensagem_bilhete` required string max:500; `whatsapp` nullable, `prepareForValidation` deixa só dígitos (vazio → null), `regex:/^\d{12,13}$/`; `mensagem_whatsapp` nullable string max:500; `botao_whatsapp` boolean; `instagram`, `facebook`, `twitter`, `youtube`, `jogo_responsavel` nullable `url:http,https` max:255; `cor_tema` `Rule::in(array_keys(Configuracoes::CORES_TEMA))`; `cor_fundo` `Rule::in(Configuracoes::CORES_FUNDO)` (aceita minúsculas, grava maiúsculas); `gtm_id` nullable, maiúsculas e sem espaços no `prepareForValidation`, `regex:/^GTM-[A-Z0-9]{4,12}$/`; mensagens em português da contracts/api.md §1.1
- [ ] T018 [P] [US1] Criar `app/Http/Resources/ConfiguracoesBancaResource.php` com os campos de contracts/api.md §1.1 e `cores_tema` (lista `{cor, nome}` de `Configuracoes::CORES_TEMA`)
- [ ] T019 [US1] Criar `app/Http/Controllers/ConfiguracoesBancaController.php` (padrão de `ConfiguracoesPagamentosController`, docblock spec 021): middleware `permissao_cliente('configuracoes.editar')`; `show()` → resource; `update()` → `HistoricoConfiguracoes::alterar($request->user(), 'banca', Configuracoes::atual(), $request->validated())`; rotas `GET/PUT configuracoes/banca` em `routes/api.php`, no grupo das configurações gerais (comentário "dados da banca, contatos, tema e GTM (spec 021)")
- [ ] T020 [US1] Em `app/Http/Middleware/TratarRequisicoesInertia.php`: `tema` = `Configuracoes::atual()->tema()` e `contatos` = `->contatos()` (uma leitura do registro por pedido); comentário atualizado (dados reais, spec 021)
- [ ] T021 [US1] Trocar o tema fake nos demais usos: `app/Http/Controllers/ArquivosPwaController.php` (`background_color`/`theme_color` = `cor_fundo` da configuração) e `app/Mail/Concerns/DadosEmail.php` (`cor_principal` = `cor_tema`; comentário "FAKE" sai); depois remover `tema()`, `contatos()` e `CORES_DERIVADAS` de `app/Fakes/DadosFake.php` (docblock da classe atualizado)
- [ ] T022 [P] [US1] Em `resources/js/components/WhatsAppButton/index.jsx`: só aparece com `contatos.botao_whatsapp` e `contatos.whatsapp`; e em `resources/js/components/Footer/index.jsx`: imagem de jogo responsável sem clique e sem `title` quando `contatos.jogo_responsavel` vazio (FR-007)
- [ ] T023 [P] [US1] Criar `resources/js/components/ThemePicker/` (`index.jsx`, `styles.jsx`): 6 amostras redondas (44 px, rótulo embaixo, marcada com `check`) a partir de `cores_tema`, e o fundo Escuro/Claro em dois cartões de amostra; `valor_cor`, `valor_fundo`, `ao_mudar(campo, valor)`
- [ ] T024 [US1] Aba Banca em `resources/js/pages/PanelSettings/index.jsx`: `useSecaoConfiguracoes({ rota: '/configuracoes/banca' })`, `SettingsSection` Identificação (`badge`), Atendimento (`support_agent`), Redes sociais (`share`), Tema (`palette`, com `ThemePicker`) e Rastreamento (`analytics`), cada uma com `SettingsForm` e os campos de `settings_fields.js`; uma `SaveBar` para a aba; ao salvar, toast "Configurações salvas." e `router.reload({ only: ['tema', 'contatos', 'nome_sistema'] })` para o painel já mostrar a cor nova

**Checkpoint**: US1 validável pelo quickstart §1 e §2.

---

## Phase 4: User Story 2 - Logo e ícones do aplicativo (Priority: P1)

**Goal**: a logo gera os ícones do aplicativo, do navegador e da Apple, no lugar dos fakes.

**Independent Test**: quickstart §4.

### Implementation for User Story 2

- [ ] T025 [US2] Mover `public/fakes/icone.png` e `public/fakes/icones/*` para `public/images/icones/` (mesmos nomes; o `icone.png` vira `icon-favicon.png`) e remover a pasta `public/fakes` vazia; em `vite.config.js`, tirar `|| url.pathname.startsWith('/fakes/')` da regra de cache
- [ ] T026 [US2] Criar `app/Services/IconesAplicativo.php` (R-05, GD): `TAMANHOS = [32, 64, 96, 128, 168, 192, 256, 512]`, `TAMANHOS_APPLE = [152, 167, 180]`; `gerar(string $conteudo_logo, string $cor_fundo): string` cria `icones/{sha1}/` no disco `public` (se já existe, só devolve) com `icon-{t}.png` (quadrado do maior lado, fundo transparente, imagem centralizada, `imagecopyresampled`, `imagesavealpha`) e `touch-icon-{t}.png` (fundo na `cor_fundo`); falha de leitura → `RuntimeException('Não foi possível ler a imagem enviada.')` sem deixar pasta pela metade; `remover(?string $pasta)` apaga a pasta se nenhuma configuração (não excluída) a usa; `pequena(string $conteudo): bool` (< 192 px no menor lado)
- [ ] T027 [US2] Em `app/Models/Configuracoes.php`: `icones(): array` (`favicon`, `apple` {152,167,180}, `manifest` lista `{src, sizes, type}`) da `pasta_icones` ou, sem ela, dos padrão `/images/icones/...`; `url_icone(int $tamanho): string`; `tema()` passa a incluir `icones`
- [ ] T028 [US2] Em `app/Http/Controllers/ConfiguracoesLogoController.php`: depois de salvar a logo, `IconesAplicativo::gerar` com o conteúdo e a `cor_fundo`; grava `logo` e `pasta_icones` juntos (só se a geração deu certo); remove a logo e a pasta anteriores quando mudaram; registra o histórico (`HistoricoConfiguracoes::registrar(..., 'logo', Alteração, null, ['logo' => [antes, depois]])`); resposta com `logo`, `icones` e `aviso` (contracts/api.md §2)
- [ ] T029 [US2] Trocar os ícones fake: `app/Http/Controllers/ArquivosPwaController.php` (`icons` = `icones()['manifest']`), `app/Services/NotificacoesPush.php` (`icone` = `url_icone(192)`) e `resources/views/app.blade.php` (favicon e os três `apple-touch-icon` de `$page['props']['tema']['icones']`, contracts/paginas.md §2); remover `icones()` e `TAMANHOS_ICONES` de `app/Fakes/DadosFake.php`
- [ ] T030 [P] [US2] Criar `resources/js/components/LogoUploader/` (`index.jsx`, `styles.jsx`): prévia da logo atual sobre fundo escuro e claro, botão "Enviar nova logo" (`input type=file` png/jpg/jpeg/webp), dica "PNG quadrado de 512x512, até 1 MB", envio `multipart` para `POST /configuracoes/logo` com o token, toast de sucesso, grade com os ícones 32/96/192/512 da resposta, faixa amarela com `aviso`; erro 422 embaixo do botão
- [ ] T031 [US2] Aba Visual em `resources/js/pages/PanelSettings/index.jsx`: `SettingsSection` Logo (`image`) com `LogoUploader`; ao enviar, `router.reload({ only: ['tema'] })`

**Checkpoint**: US2 validável pelo quickstart §4.

---

## Phase 5: User Story 3 - Banners do slide e aviso da banca (Priority: P1)

**Goal**: gestão dos banners e dos avisos pela aba Visual, com ordem e histórico.

**Independent Test**: quickstart §5.

### Implementation for User Story 3

- [ ] T032 [P] [US3] Em `app/Http/Controllers/BannersController.php` e `app/Http/Controllers/AvisosController.php`: registrar o histórico (`banners`/`avisos`, `Cadastro` no store, `Alteração` no update com os campos mudados — imagem como endereço —, `Exclusão` no destroy), com `item_id`; injetar `HistoricoConfiguracoes`
- [ ] T033 [P] [US3] Criar `resources/js/components/ImagePreviewModal/` (`index.jsx`, `styles.jsx`): `ModalCard` + `ModalHeader` (ícone `visibility`, título recebido, "Veja a imagem como aparece no site."), imagem em tamanho real limitada à tela, link embaixo quando houver
- [ ] T034 [P] [US3] Criar `resources/js/components/BannerFormModal/` (`index.jsx`, `styles.jsx`): `ModalHeader` (`view_carousel`, "Cadastrar banner"/"Editar banner", "A imagem é ajustada para 1280x405 e aparece no carrossel da página inicial."); campos imagem (prévia na proporção 1280x405; obrigatória no cadastro), link (http/https, opcional), ativo; envio `multipart` para `POST /banners` ou `POST /banners/{id}` com `_method=PUT`; erros 422 nos campos
- [ ] T035 [P] [US3] Criar `resources/js/components/NoticeFormModal/` (`index.jsx`, `styles.jsx`): `ModalHeader` (`campaign`, "Cadastrar aviso"/"Editar aviso", "Imagem que aparece ao abrir o site até o apostador marcar como lido. Tamanho recomendado 500x500."); campos imagem, título (até 100), link, início e fim (data e hora, Brasília), ativo; envio para `/avisos` como no `BannerFormModal`
- [ ] T036 [US3] Criar `resources/js/components/BannersManager/` (`index.jsx`, `styles.jsx`): `GET /banners?por_pagina=20` com o token; linhas com miniatura 128x40, link, chip Ativo/Inativo, botões subir/descer (R-18: troca a `ordem` dos vizinhos com dois `PUT /banners/{id}`; ordens iguais renumeradas de 10 em 10 pela ordem exibida) e `ActionsMenu` (Ver, Editar, Ativar/Desativar, Excluir com `ConfirmModal`); `Pagination`; vazio "Nenhum banner cadastrado. O carrossel não aparece no site."; cartões no celular; botão "Cadastrar banner" abre o `BannerFormModal`
- [ ] T037 [US3] Criar `resources/js/components/NoticesManager/` (`index.jsx`, `styles.jsx`): mesmo padrão do `BannersManager` para `GET /avisos?por_pagina=20`, colunas miniatura, título, período (dd/mm/aaaa hh:mm, "Sem fim"), situação; sem subir/descer; vazio "Nenhum aviso cadastrado."; "Cadastrar aviso" abre o `NoticeFormModal`
- [ ] T038 [US3] Aba Visual em `resources/js/pages/PanelSettings/index.jsx`: seções Banners do slide (`view_carousel`) com `BannersManager` e Avisos da banca (`campaign`) com `NoticesManager`, abaixo da Logo; `ImagePreviewModal` compartilhado

**Checkpoint**: US3 validável pelo quickstart §5.

---

## Phase 6: User Story 4 - Regras da banca com editor de texto (Priority: P2)

**Goal**: regras em HTML limpo, escritas no editor e mostradas formatadas em `/regras`.

**Independent Test**: quickstart §6.

### Implementation for User Story 4

- [ ] T039 [US4] Criar `app/Services/LimpezaHtml.php` (R-08) com `HTMLPurifier`: `HTML.Allowed` = `p,h2,h3,strong,em,u,s,ul,ol,li,br,a[href|target|rel],span[style],p[style],h2[style],h3[style]`; `CSS.AllowedProperties` = `color,text-align`; `URI.AllowedSchemes` = `http,https,mailto,tel`; `HTML.TargetBlank` true e `rel` `noopener noreferrer`; cache em `storage/framework/cache/htmlpurifier`; métodos `limpar(?string $html): string` e `texto(string $html): string` (`strip_tags` + `html_entity_decode`, para contar caracteres)
- [ ] T040 [US4] Criar `database/migrations/2026_10_27_000003_converter_regras_html.php` (R-09, migration de dados): `up()` converte `configuracoes.regras` de texto puro em `<p>` por linha não vazia (`htmlspecialchars`), pulando registros que já começam com `<`; vazio continua vazio; `down()` volta a uma linha por parágrafo (`strip_tags`)
- [ ] T041 [US4] Em `app/Models/Configuracoes.php`: `REGRAS_PADRAO` com os mesmos três parágrafos em `<p>`; remover `paragrafos_regras()`
- [ ] T042 [US4] Em `app/Http/Requests/ConfiguracoesRegrasRequest.php`: `regras` `present|nullable|string|max:50000`; `after()` recusa mais de 10.000 caracteres de texto (`LimpezaHtml::texto`) com "As regras devem ter no máximo 10.000 caracteres de texto."; `regras_limpas(): string` devolve `LimpezaHtml::limpar`. Em `app/Http/Controllers/ConfiguracoesRegrasController.php`: `update` grava `regras_limpas()` pelo `HistoricoConfiguracoes::alterar(..., 'regras', ...)`; docblock atualizado (HTML, spec 021)
- [ ] T043 [US4] Em `app/Http/Controllers/RegrasController.php`: `'regras' => LimpezaHtml::limpar(Configuracoes::atual()->regras)`; docblock atualizado
- [ ] T044 [US4] Em `resources/js/components/RulesContent/index.jsx` e `styles.jsx`: prop `regras` string; bloco `RulesBlock` com um `BankRichText` (`dangerouslySetInnerHTML`, comentário "HTML limpo no servidor, research R-08") e estilos de `h2`, `h3`, `p`, listas, `a` (cor do tema) e alinhamento; vazio esconde o bloco; remover `BankRules`/`BankRule` se ficarem sem uso
- [ ] T045 [P] [US4] Criar `resources/js/components/RulesEditor/` (`index.jsx`, `styles.jsx`): `useEditor` do TipTap com `StarterKit` (link com `openOnClick: false`), `TextAlign` (`paragraph`, `heading`), `TextStyle` + cor; barra fixa no topo com botões (Material Icons, `title` e `aria-pressed`): Parágrafo/Título/Subtítulo (seleção), negrito, itálico, sublinhado, cor (6 cores do tema + `#000000` e `#757575`), lista, lista numerada, alinhar esquerda/centro/direita/justificar, link (pede o endereço; vazio remove), desfazer, refazer; contador "N de 10.000 caracteres" (vermelho acima); botão "Ver como fica" que alterna o editor com a prévia no estilo do `RulesContent`; props `valor`, `ao_mudar(html)`
- [ ] T046 [US4] Aba Regras em `resources/js/pages/PanelSettings/index.jsx`: `useSecaoConfiguracoes({ rota: '/configuracoes/regras' })`, `SettingsSection` (`gavel`, "Regras da banca", "Aparecem no topo da página de regras do site.") com `RulesEditor` e `SaveBar`; HTML vazio do editor (`<p></p>`) enviado como `''`

**Checkpoint**: US4 validável pelo quickstart §6.

---

## Phase 7: User Story 5 - Integrações: Mercado Pago e robô do WhatsApp (Priority: P2)

**Goal**: situação e ações do Mercado Pago e do robô (Evolution API), só o Admin age.

**Independent Test**: quickstart §7.

### Implementation for User Story 5

- [ ] T047 [US5] Permissão `robo_whatsapp.conectar` (R-13): em `app/Enums/Funcao.php`, incluir em `PERMISSOES_SITE` (docblock: "e conectar o robô do WhatsApp, só Admin") e liberar só ao Admin no `pode_usar`, junto da regra do `gateway_pagamento.vincular`; criar `database/migrations/2026_10_27_000004_criar_permissao_robo_whatsapp.php` no padrão de `2026_10_25_000005_criar_permissoes_clientes_painel.php` (cria a permissão no guard `api` e dá aos usuários cuja função a recebe por padrão; `down()` remove)
- [ ] T048 [P] [US5] Criar `app/Exceptions/RoboIndisponivelException.php` (mensagem padrão "Serviço do robô indisponível. Confira o endereço e a chave da Evolution API no servidor.")
- [ ] T049 [US5] Criar `app/Services/ClienteEvolution.php` (R-10, padrão do `ClienteMercadoPago`): `Http::baseUrl(url)->withHeaders(['apikey' => chave])->timeout(10)`; sem `url`/`chave` → `RoboIndisponivelException`; métodos `instancia(): string`, `existe(): bool` (`GET /instance/fetchInstances?instanceName=`), `criar(string $url_webhook)` (`POST /instance/create` com `integration` `WHATSAPP-BAILEYS`, `qrcode` true e `webhook` `{url, byEvents: false, base64: false, headers: {"x-webhook-token"}, events: ["CONNECTION_UPDATE"]}`), `configurar_webhook(string $url)` (`POST /webhook/set/{instancia}`), `qrcode(): ?string` (`GET /instance/connect/{instancia}` → `base64` como data URL), `estado(): string` (`GET /instance/connectionState/{instancia}` → `instance.state`), `perfil(): array{numero: ?string, perfil: ?string}` (`ownerJid` sem `@s.whatsapp.net`, `profileName`), `sair()` (`DELETE /instance/logout/{instancia}`); erro de conexão ou 5xx → `RoboIndisponivelException`; nunca registra a chave em log
- [ ] T050 [US5] Criar `app/Services/RoboWhatsapp.php` (R-10 a R-12): `situacao(): array` (consulta `estado`, converte `open`/`connecting`/`close` em `SituacaoRoboWhatsapp`, grava se mudou com número/perfil no `open`; indisponível → situação gravada + `disponivel: false` + mensagem); `conectar(Usuarios $autor): array` (cria a instância com o webhook `route('webhooks.evolution')` se não existir, senão `configurar_webhook`; se já `open` devolve Conectado sem QR; senão grava Aguardando leitura, registra `Conexão` no histórico só quando a situação anterior era Desconectado, e devolve o QR); `desconectar(Usuarios $autor)` (`sair`, grava Desconectado e limpa número/perfil, histórico `Desconexão`); `tratar_aviso(array $payload): void` (só `connection.update`; mesma conversão; `open` busca o perfil); `gravar(SituacaoRoboWhatsapp, ?numero, ?perfil)` atualiza as colunas `robo_whatsapp_*` com `forceFill` e `robo_whatsapp_atualizado_em = now()`
- [ ] T051 [US5] Criar `app/Http/Controllers/RoboWhatsappController.php`: middleware `configuracoes.editar` em `show` e `robo_whatsapp.conectar` em `conectar` e `destroy`; `show` → contracts/api.md §1.4 (`pode_alterar` pelo `Funcao::usuario_pode` da permissão nova); `conectar` → §1.5 (`RoboIndisponivelException` → 503 com a mensagem); `destroy` → 204; rotas em `routes/api.php`: `GET configuracoes/robo-whatsapp`, `POST configuracoes/robo-whatsapp/conectar` (`throttle:10,1`), `DELETE configuracoes/robo-whatsapp`, com comentário "robô do WhatsApp pela Evolution API (spec 021)"
- [ ] T052 [US5] Criar `app/Http/Controllers/WebhooksEvolutionController.php` (contracts/api.md §1.7, padrão do `WebhooksMercadoPagoController`): `x-webhook-token` com `hash_equals` (sem token configurado ou diferente → 401 `{"message": "Não autorizado."}`), `instance` diferente de `ClienteEvolution::instancia()` → 404, demais → `RoboWhatsapp::tratar_aviso` e 200 `{"recebido": true}`; rota pública `POST webhooks/evolution` (nome `webhooks.evolution`, `throttle:120,1`) ao lado da do Mercado Pago em `routes/api.php`
- [ ] T053 [US5] Em `app/Http/Controllers/GatewayPagamentoController.php`: `show` com `configuracoes.editar` e `autorizar`/`destroy` com `gateway_pagamento.vincular` (middleware `->only`/`->except`); `show` ganha `pode_alterar`; `destroy` registra `Desvínculo` no histórico (`mercado_pago`). Em `app/Services/VinculoMercadoPago.php` (`concluir`): ao gravar o vínculo novo, registrar `Vínculo` com o admin do `state` como autor
- [ ] T054 [P] [US5] Criar `resources/js/components/MercadoPagoCard/` (`index.jsx`, `styles.jsx`): `GET /configuracoes/gateway`; chip de situação (Vinculado verde, Falha na renovação amarelo, Desvinculado cinza), "Vinculado em", "Expira em", "Renovado em"; com `pode_alterar`: "Vincular Mercado Pago" (desvinculado) ou "Sincronizar novamente" (com falha), que pede `POST /configuracoes/gateway/mercado-pago/autorizar` e abre a `url` em nova aba, e "Desvincular" com `ConfirmModal`; sem: "Só o administrador pode alterar."; recarrega ao voltar para a aba (`visibilitychange`)
- [ ] T055 [P] [US5] Criar `resources/js/components/WhatsAppBotCard/` (`index.jsx`, `styles.jsx`): `GET /configuracoes/robo-whatsapp`; chip (Conectado verde, Aguardando leitura azul, Desconectado cinza, Indisponível vermelho com a mensagem), número formatado e perfil; com `pode_alterar`: "Conectar" (`POST .../conectar`, mostra o QR 264x264 com as instruções de contracts/paginas.md §5; enquanto Aguardando leitura, `GET` a cada 3 s e novo `conectar` a cada 30 s; para ao conectar, ao desmontar ou ao trocar de aba) e "Desconectar" com `ConfirmModal`; sem: só leitura
- [ ] T056 [US5] Aba Integrações em `resources/js/pages/PanelSettings/index.jsx`: `SettingsSection` Mercado Pago (`account_balance_wallet`) e Robô do WhatsApp (`smart_toy`) com os cartões

**Checkpoint**: US5 validável pelo quickstart §7.

---

## Phase 8: User Story 6 - Rastreamento com Google Tag Manager (Priority: P2)

**Goal**: GTM nas páginas do apostador e os eventos `subscribe`, `addtocart` e `purchase`.

**Independent Test**: quickstart §3.

### Implementation for User Story 6

- [ ] T057 [US6] Em `app/Http/Middleware/TratarRequisicoesInertia.php`: prop `rastreamento` = `['gtm_id' => Configuracoes::atual()->gtm_id]` (contracts/paginas.md §1)
- [ ] T058 [P] [US6] Criar `resources/js/utils/tracking.js` (R-14, R-15): `carregar_gtm(id)` (uma vez; `window.dataLayer`, evento `gtm.js` e script `https://www.googletagmanager.com/gtm.js?id=`), `enviar_evento(evento, dados = {})` (nada sem GTM carregado), `enviar_compra(codigo, valor)` (dedup em `localStorage` `wssports.compras_enviadas`, últimos 50, com `try/catch`), `acompanhar_compra(codigo, valor, expira_em)` (consulta `GET /area-cliente/depositos/{codigo}` a cada 10 s até `Aprovado` → `enviar_compra`, ou situação final/expiração; um acompanhamento por código)
- [ ] T059 [US6] Em `resources/js/layouts/PublicLayout/index.jsx`: `useEffect` que chama `carregar_gtm(rastreamento.gtm_id)` quando há id e o componente da página não é do painel nem do afiliado (`Dashboard`, `Panel*`, `AffiliatePanel`, `AffiliateLanding`, `GatewayReturn`), comentário com a limitação do R-14
- [ ] T060 [P] [US6] Em `resources/js/components/AuthModal/index.jsx`: `enviar_evento('subscribe')` depois do `cadastrar` concluído com sucesso
- [ ] T061 [US6] Em `resources/js/components/DepositModal/index.jsx`: `enviar_evento('addtocart', { amount })` ao entrar na etapa `pix`; `enviar_compra(codigo, amount)` no `concluir` (Pix e cartão aprovados); ao fechar com depósito `Pendente`/`Em análise`, `acompanhar_compra(codigo, amount, expira_em)`; `amount` número com 2 casas

**Checkpoint**: US6 validável pelo quickstart §3.

---

## Phase 9: User Story 7 - Configurações de operação (Priority: P3)

**Goal**: aba Operação com as seções existentes + ao vivo, sorteio e limite de notificações, todas
com histórico.

**Independent Test**: quickstart §8 e §9.

### Implementation for User Story 7

- [ ] T062 [P] [US7] Criar `app/Http/Requests/ConfiguracoesAoVivoRequest.php` (data-model §1, todos `sometimes`): `ao_vivo_habilitado` boolean; `segundos_trava_ao_vivo` integer between:0,120; `minutos_permanencia_ao_vivo` integer between:1,60; `minuto_limite_ao_vivo` integer between:1,130; `cotacao_maxima_ao_vivo` numeric 2 casas between:1.01,1000; mensagens em português; e `app/Http/Resources/ConfiguracoesAoVivoResource.php` (contracts/api.md §1.2, inclui `ao_vivo_travado` e `ao_vivo_travado_em`)
- [ ] T063 [P] [US7] Criar `app/Http/Requests/ConfiguracoesSorteioCotacoesRequest.php` (todos `sometimes`, numeric 2 casas between:1.01,50; mínimo `lte` máximo de cada par considerando o valor gravado quando só um vem; mensagem "O mínimo de \"ambas marcam\" não pode ser maior que o máximo." e a de "ambas não marcam") e `app/Http/Resources/ConfiguracoesSorteioCotacoesResource.php` (§1.3)
- [ ] T064 [US7] Criar `app/Http/Controllers/ConfiguracoesAoVivoController.php` e `app/Http/Controllers/ConfiguracoesSorteioCotacoesController.php` (padrão de T019, seções `ao_vivo` e `sorteio_cotacoes`) e as rotas `GET/PUT configuracoes/ao-vivo` e `GET/PUT configuracoes/sorteio-cotacoes` em `routes/api.php`
- [ ] T065 [US7] Histórico nas configurações existentes (R-16), trocando `$configuracoes->update($request->validated())` por `HistoricoConfiguracoes::alterar($request->user(), '<secao>', $configuracoes, $request->validated())` em `app/Http/Controllers/ConfiguracoesPagamentosController.php` (`pagamentos`), `ConfiguracoesCriarApostaController.php` (`criar_aposta`), `ConfiguracoesApuracaoController.php` (`apuracao`), `ConfiguracoesEncerramentoController.php` (`encerramento`), `ConfiguracoesCassinoController.php` (`cassino`), `ConfiguracoesAfiliadosController.php` (`afiliados`) e `ConfiguracoesAvisosWhatsappController.php` (`avisos_whatsapp`, chave como segredo); e em `app/Http/Controllers/ClientesNotificacoesController.php` (`alterar_limite`, seção `notificacoes`), sem mudar as regras de cada um
- [ ] T066 [US7] Em `resources/js/utils/settings_fields.js`: definições das seções da Operação a partir dos Requests/Resources de cada rota — `pagamentos` (`/configuracoes/pagamentos`), `criar_aposta`, `apuracao`, `encerramento`, `cassino`, `afiliados`, `avisos_whatsapp` (chave como `segredo`), `ao_vivo` (`ao_vivo_travado` só leitura com a data), `sorteio_cotacoes` e `notificacoes` (`/clientes/notificacoes/limite`, campo `limite`, só leitura sem `pode_alterar`) — cada uma com `rota`, `titulo`, `icone`, `descricao` e `campos` (rótulos e ajudas em português; meios de depósito em `chips`; enums em `selecao`)
- [ ] T067 [US7] Aba Operação em `resources/js/pages/PanelSettings/index.jsx`: sanfona de `SettingsSection` recolhíveis na ordem de contracts/paginas.md §5, uma aberta por vez (`?secao=`), cada uma com `useSecaoConfiguracoes` da sua rota, `SettingsForm` e `SaveBar` própria; trocar de seção com alterações pergunta antes

**Checkpoint**: US7 validável pelo quickstart §8 e §9.

---

## Phase 10: Polish & Cross-Cutting Concerns

- [ ] T068 [P] Regenerar `docs/postman/wssports_api.postman_collection.json` (substituindo o arquivo) com as rotas novas (`configuracoes/banca`, `configuracoes/ao-vivo`, `configuracoes/sorteio-cotacoes`, `configuracoes/robo-whatsapp` GET/DELETE, `configuracoes/robo-whatsapp/conectar`, `webhooks/evolution` com o cabeçalho `x-webhook-token`) e as respostas alteradas (`configuracoes/logo` com `icones`/`aviso`; `configuracoes/regras` em HTML; `configuracoes/gateway` com `pode_alterar`)
- [ ] T069 [P] Nota "Alterado pela spec 021" em `specs/005-tela-principal/spec.md` (tema, contatos e ícones deixam de ser fake), `specs/006-ajustes-tela-principal/spec.md` (regras da banca em HTML limpo com editor) e `specs/007-painel-cliente-pagamentos/spec.md` (Supervisor vê a situação do gateway; vínculo e desvínculo no histórico)
- [ ] T070 Conferir responsividade a 360 px (sem rolagem horizontal, alvos ≥ 40 px), modais pela constituição (tamanho, `ModalHeader`, só o corpo rola), estados de carregando/vazio/erro em todas as abas e o aviso de alterações não salvas
- [ ] T071 Executar o [quickstart.md](quickstart.md) inteiro e registrar o resultado em "Notas da implementação" neste arquivo; revisão de legibilidade final do conjunto (Princípio V)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)** → **Foundational (Phase 2)** → histórias.
- **US1 (Phase 3)**: depende só da Phase 2. É o MVP.
- **US2 (Phase 4)**: depende da Phase 2 (T009 e o `tema()`); T029 depois de T021 (mesmo `DadosFake`).
- **US3 (Phase 5)**: depende só da Phase 2; T038 depois de T031 (mesma aba).
- **US4 (Phase 6)**: depende só da Phase 2 e de T001/T002.
- **US5 (Phase 7)**: depende da Phase 2 e de T003.
- **US6 (Phase 8)**: depende da US1 (campo `gtm_id` gravado por T017–T019).
- **US7 (Phase 9)**: depende da Phase 2 (T008, T013).
- **Polish (Phase 10)**: depois de tudo.

### Within Each User Story

- Backend (requests, services, controllers, rotas) antes da tela; componentes [P] em paralelo; a aba
  da página por último. Tarefas que mexem no mesmo arquivo (`PanelSettings/index.jsx`,
  `Configuracoes.php`, `TratarRequisicoesInertia.php`, `DadosFake.php`, `routes/api.php`) em
  sequência.

### Parallel Opportunities

- Phase 1: T001–T005 em paralelo.
- Phase 2: T006, T011, T012, T013, T014 em paralelo; T007 → T008; T015 → T016.
- US1: T017, T018, T022, T023 em paralelo.
- US3: T032–T035 em paralelo.
- US5: T048, T054, T055 em paralelo depois de T047.
- US6: T058 e T060 em paralelo.
- US7: T062 e T063 em paralelo.
- Depois da Phase 2, US1, US3, US4, US5 e US7 podem andar em paralelo (backends independentes).

---

## Parallel Example: User Story 5

```text
T048 RoboIndisponivelException · T054 MercadoPagoCard · T055 WhatsAppBotCard   (em paralelo)
T047 → T049 → T050 → T051 → T052 ; T053 ; depois T056
```

---

## Implementation Strategy

### MVP First (User Story 1)

1. Phases 1 e 2 → 2. Phase 3 (US1) → 3. validar quickstart §1 e §2 → a banca já troca WhatsApp,
   redes e cor sem fake.

### Incremental Delivery

1. US1 (banca) → US2 (logo e ícones) → US3 (banners e avisos) → US4 (regras) → US5 (integrações) →
   US6 (GTM) → US7 (operação) → Polish.
2. Cada etapa validada pela seção correspondente do quickstart antes da próxima.

---

## Notes

- Sem testes automatizados; nenhuma tarefa de teste.
- Toda rota criada ou alterada entra na coleção do Postman (T068).
- Mudanças fora da lista do plan.md exigem aviso e confirmação antes (Princípio IV).
