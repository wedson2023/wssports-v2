# Research: Painel — Configurações gerais

**Feature**: `021-painel-configuracoes` | **Data**: 2026-10-25

## R-01 — Sistema antigo consultado (Princípio VII)

**Arquivos lidos** (`wssports.bet`, `main` `408cf730`, somente leitura):

- `resources/js/screens/adm/config/index.js` e `styles.js`: tela "Configuração" (`/adm/config`).
- `app/Http/Controllers/ConfigController.php` (`index`, `store`, banners e popups).
- `app/Http/Controllers/WhatsAppController.php` e `app/Jobs/EnviarMensagemWhatsApp.php` (Z-API).
- `app/Http/Controllers/CadastroController.php` e `PagamentosController.php` (Sellflux).
- `resources/views/app.blade.php` (GTM) e `resources/js/modals/register` e `modals/credits`
  (`dataLayer`).
- `resources/js/screens/adm/components/header/index.js` (menu: "Configuração" só para Admin e
  Supervisor).

**Resumo**: descrito na spec, seção "Referências do sistema antigo". Pontos que guiam o plano:

| Antigo | Novo |
|---|---|
| Um `POST config` com tudo, inclusive arquivos | Uma rota por aba/seção (as existentes + as novas), cada uma salva só os seus campos |
| Logo esticada para 512x512 e copiada para ícones com nome fixo | Logo com nome por hash (já existe) + ícones gerados sem distorcer, numa pasta por hash (R-05) |
| Slide e popup sem ordem nem ativo | CRUDs de `banners` e `avisos` (spec 006), com ordem, ativo e período |
| TinyMCE com chave no código, texto em HTML sem limpeza | Editor TipTap (sem chave) + HTML limpo por lista de permitidos no servidor (R-07, R-08) |
| Z-API com credenciais fixas | Evolution API (R-10 a R-12) |
| GTM no `app.blade.php` de todas as páginas; `pucharse` só com o modal aberto | GTM carregado pelo React só nas páginas do apostador; `purchase` com acompanhamento do depósito mesmo com o modal fechado (R-14, R-15) |

## R-02 — Uma rota por aba, reaproveitando as existentes

**Decisão**: a tela chama as rotas `configuracoes/*` já existentes para as seções da aba Operação
(pagamentos, criar-aposta, apuracao, encerramento, cassino, afiliados, avisos-whatsapp), a de regras,
a de logo, o CRUD de `banners` e `avisos` e as de `configuracoes/gateway`. Rotas novas só para o que
não tem rota: `configuracoes/banca`, `configuracoes/ao-vivo`, `configuracoes/sorteio-cotacoes` e
`configuracoes/robo-whatsapp` (+ `conectar`) e o aviso `webhooks/evolution`.

**Rationale**: segue o padrão show/update + Request + Resource dos controllers `Configuracoes*`
(Princípio VI); cada seção salva só os seus campos (FR-003) sem rota "tudo de uma vez".

**Alternativas**: uma rota única `configuracoes` com todos os campos — rejeitada: repetiria as
validações já existentes e faria abas sobrescreverem umas às outras (Edge Cases).

## R-03 — Limite diário de notificações manuais

**Decisão**: a seção usa as rotas existentes `GET/PUT clientes/notificacoes/limite` (spec 019), que
já devolvem `pode_alterar`; o campo fica só leitura para o Supervisor, como a spec 019 definiu (só o
Admin altera). A spec 021 foi ajustada (FR-029) para registrar essa exceção.

**Rationale**: a Clarification 5 da spec 021 foi "como o servidor já permite"; para este campo o
servidor só permite o Admin. Manter evita mudar a regra da spec 019.

**Alternativas**: liberar o Supervisor — mudaria a regra da spec 019 sem pedido.

## R-04 — Tema e contatos reais

**Decisão**: colunas novas em `configuracoes` (data-model §1): `cor_tema` (uma das 6 cores),
`cor_fundo` (`#000000`/`#FFFFFF`), `whatsapp`, `mensagem_whatsapp`, `botao_whatsapp`, `instagram`,
`facebook`, `twitter`, `youtube`, `jogo_responsavel`, `gtm_id`. O mapa cor principal → cor escura
(`CORES_DERIVADAS` do `DadosFake`) passa para `Configuracoes::CORES_TEMA`, com os métodos
`tema()` e `contatos()` no model devolvendo exatamente o formato das props atuais (`temas`,
`letter`, `cor_fundo`; `whatsapp`, `mensagem_whatsapp`, `instagram`, `youtube`, `twitter`,
`facebook`, `jogo_responsavel`) + `botao_whatsapp`. `DadosFake::tema`, `contatos` e `icones` são
removidos (Princípio IX); `DadosFake::indicadores` continua.

**Usos trocados**: `TratarRequisicoesInertia` (props `tema` e `contatos`), `ArquivosPwaController`
(cor do manifest e ícones), `DadosEmail` (cor dos e-mails), `NotificacoesPush` (ícone 192).

**Valores iniciais** (migration): `cor_tema` `#c40808` e `cor_fundo` `#000000` (os do fake);
`mensagem_whatsapp` e `jogo_responsavel` com os textos do fake; `whatsapp` e redes vazios (o número
do fake é inventado e não pode ir para produção); `botao_whatsapp` ligado.

**Rationale**: mesmo formato das props → componentes não mudam, só os que precisam esconder item
vazio (`Footer`, `WhatsAppButton`).

## R-05 — Ícones gerados da logo

**Decisão**: service novo `IconesAplicativo` (GD, já exigido no `composer.json`):

- recebe o conteúdo da logo, cria um quadrado do maior lado com fundo transparente e centraliza a
  imagem (sem distorcer), e gera PNG de 32, 64, 96, 128, 168, 192, 256 e 512 (manifest) e 152, 167
  e 180 (Apple, com fundo na `cor_fundo`, porque o iOS pinta o transparente de preto);
- grava em `icones/{sha1 da logo}/icon-{tamanho}.png` e `touch-icon-{tamanho}.png` no disco
  `public`; a coluna `pasta_icones` guarda a pasta;
- `Configuracoes::icones()` monta a lista do manifest; `url_icone(tamanho)` o endereço de um ícone;
  sem pasta, os ícones padrão do projeto (`/images/icones/...`, os atuais `public/fakes/icones`
  movidos, deixando de ser fake);
- a troca roda dentro do `ConfiguracoesLogoController::update`: gera a pasta nova, grava logo e
  pasta, e só depois apaga a pasta anterior (se nenhuma outra logo a usa). Se a geração falhar,
  nada é gravado (Edge Case).
- favicon: o `icon-32`; Apple: `touch-icon-152/167/180`.
- Imagem menor que 192x192: aceita; a resposta traz `aviso` e a tela mostra (FR-013).

**Rationale**: pasta por hash = endereço novo a cada troca (FR-012), sem cache velho no navegador
nem no service worker.

**Alternativas**: gerar no navegador (canvas) — rejeitada: o servidor é a fonte da configuração;
intervention/image — dependência nova sem necessidade.

## R-06 — Onde os ícones e o GTM chegam à página

**Decisão**: tudo pela prop compartilhada do Inertia (`tema.icones` e `rastreamento.gtm_id`). O
`app.blade.php` usa `$page['props']['tema']['icones']` para o `<link rel="icon">` e os
`apple-touch-icon` (troca os `/fakes/...`). O GTM não vai no blade (R-14).

**Rationale**: o root view do Inertia já recebe `$page`; não precisa de view composer.

## R-07 — Editor das regras

**Decisão**: TipTap 3 (`@tiptap/react`, `@tiptap/pm`, `@tiptap/starter-kit` — que já traz link e
sublinhado —, `@tiptap/extension-text-align`, `@tiptap/extension-text-style` com cor). Componente
`RulesEditor` com barra: parágrafo/título 2/título 3, negrito, itálico, sublinhado, cor (as 6 cores
do tema + preto/cinza), listas, alinhamento, link, desfazer/refazer; e "Ver como fica" (prévia com o
mesmo estilo da página de regras).

**Rationale**: MIT, sem chave de API, compatível com React 19, sem jQuery; gera HTML semântico.

**Alternativas**: TinyMCE (chave de API, como o antigo), Quill (`react-quill` não suporta React 19),
Lexical (exige montar toda a barra e serialização HTML à mão).

**Dependências novas** (justificadas, Stack): as 5 do TipTap no `package.json` e
`ezyang/htmlpurifier` no `composer.json` (R-08).

## R-08 — HTML das regras limpo no servidor

**Decisão**: `ezyang/htmlpurifier` (^4.18) num service `LimpezaHtml` com lista de permitidos igual
ao que o editor gera: `p, h2, h3, strong, em, u, s, ul, ol, li, a[href|target|rel], br, span[style]`
e `style` só com `color` e `text-align`; links só `http`, `https`, `mailto` e `tel`, com
`target="_blank" rel="noopener noreferrer"`. Aplicado no `ConfiguracoesRegrasRequest` (depois de
validar) e de novo na saída do `RegrasController` (defesa em profundidade). Limite: 10.000
caracteres de **texto visível** (`strip_tags`) e 50.000 do HTML, porque as marcações aumentam o
tamanho sem o administrador ver.

**Rationale**: limpar com expressão regular é inseguro; o HTMLPurifier é a referência em PHP, sem
dependências.

**Página pública**: `RulesContent` passa a receber `regras` (HTML) e renderiza com
`dangerouslySetInnerHTML` num bloco com estilo próprio (títulos, listas, links na cor do tema). O
método `Configuracoes::paragrafos_regras` sai (único uso era o `RegrasController`).

## R-09 — Conversão das regras atuais

**Decisão**: migration de dados que converte o texto puro de `configuracoes.regras` em
`<p>linha</p>` (com `htmlspecialchars`), pulando linhas vazias; texto vazio continua vazio. A
`REGRAS_PADRAO` passa a ser o mesmo texto já em parágrafos. `down` faz o inverso (`strip_tags` por
parágrafo).

## R-10 — Evolution API: instância e chamadas

**Decisão**: `config/services.php` ganha `evolution` (`url`, `chave` global, `instancia`,
`webhook_token`, `tempo_limite` 10s) lidos de `EVOLUTION_API_URL`, `EVOLUTION_API_CHAVE`,
`EVOLUTION_API_INSTANCIA` (padrão: host do `app.url` em minúsculas, só letras, números e hífen) e
`EVOLUTION_WEBHOOK_TOKEN`. Service `ClienteEvolution` (só HTTP, como o `ClienteMercadoPago`):

| Ação | Evolution API v2 |
|---|---|
| Criar instância (se não existir) com o webhook | `POST /instance/create` `{instanceName, integration: "WHATSAPP-BAILEYS", qrcode: true, webhook: {url, byEvents: false, base64: false, headers: {"x-webhook-token": ...}, events: ["CONNECTION_UPDATE"]}}` |
| QR code / pedir conexão | `GET /instance/connect/{instancia}` → `base64`, `code` |
| Situação | `GET /instance/connectionState/{instancia}` → `state`: `open`, `connecting`, `close` |
| Número e perfil | `GET /instance/fetchInstances?instanceName=` → `ownerJid`, `profileName` |
| Desconectar | `DELETE /instance/logout/{instancia}` |
| Garantir webhook (instância já existente) | `POST /webhook/set/{instancia}` |

Cabeçalho `apikey` com a chave global; tempo limite de 10s; falha → `RoboIndisponivelException`
tratada como "Serviço do robô indisponível" (FR-027), sem log da chave.

Service `RoboWhatsapp` (regra de negócio): `situacao()` (consulta e corrige a gravada), `conectar()`
(cria/garante instância e webhook, devolve QR), `desconectar()`, `tratar_aviso(payload)`.

**Rationale**: um servidor da Evolution pode atender várias bancas (cada uma com a sua instância);
o nome derivado do domínio evita colisão sem configurar à mão.

## R-11 — Situação do robô e aviso automático (Clarification 2)

**Decisão**: rota pública `POST api/webhooks/evolution` (throttle 120/min) →
`WebhooksEvolutionController`: confere `x-webhook-token` com `hash_equals` e a instância do payload
(`instance`) com a da banca; recusa com 401/404 sem mexer na situação. Evento `connection.update`:
`open` → Conectado (busca número e perfil), `connecting` → Aguardando leitura, `close` →
Desconectado. Grava em `configuracoes` (`robo_whatsapp_situacao`, `_numero`, `_perfil`,
`_atualizado_em`). Responde 200 rápido. Ao abrir a aba, `GET configuracoes/robo-whatsapp` consulta a
situação e corrige a gravada (reforço).

**Enum** `SituacaoRoboWhatsapp`: `Desconectado`, `AguardandoLeitura = 'Aguardando leitura'`,
`Conectado`. "Serviço indisponível" não é gravado: é a resposta quando a consulta falha.

## R-12 — QR code na tela

**Decisão**: `POST configuracoes/robo-whatsapp/conectar` devolve `{situacao, qrcode}` (data URL
base64). A aba mostra o QR e, enquanto `Aguardando leitura`: consulta `GET` a cada 3s (para mostrar
"Conectado" rápido, SC-006) e pede `conectar` de novo a cada 30s (o QR da Evolution expira em ~40s).
Para ao sair da aba ou fechar a tela.

## R-13 — Acesso às integrações (Clarification 1)

**Decisão**: permissão nova `robo_whatsapp.conectar`, em `Funcao::PERMISSOES_SITE`, liberada só ao
Admin no `pode_usar` (mesma regra do `gateway_pagamento.vincular`); criada e dada aos Admins por
migration de dados (padrão da spec 019, o deploy não roda seeders). Ver a situação (Mercado Pago e
robô) usa `configuracoes.editar` (Admin e Supervisor): o `GatewayPagamentoController::show` passa
de `gateway_pagamento.vincular` para `configuracoes.editar`; `autorizar` e `destroy` continuam só
Admin. A resposta traz `pode_alterar` para a tela esconder os botões.

## R-14 — GTM só nas páginas do apostador (Clarification 3)

**Decisão**: `utils/tracking.js` com `carregar_gtm(id)` (injeta o script
`googletagmanager.com/gtm.js?id=` uma vez, com `window.dataLayer`) e `enviar_evento(evento,
dados)`. O `PublicLayout` chama `carregar_gtm` quando `rastreamento.gtm_id` existe e a página
atual não é do painel nem do afiliado (componentes `Dashboard`, `Panel*`, `AffiliatePanel`,
`AffiliateLanding`, `GatewayReturn`). `enviar_evento` não faz nada sem GTM carregado.

**Limitação registrada**: o Inertia troca de página sem recarregar; quem sai do site para o painel
na mesma aba continua com o script já carregado (não dá para descarregar), mas nenhum evento é
enviado do painel. Quem abre o painel direto não carrega o GTM.

**Rationale**: injetar pelo React funciona na navegação do Inertia; no blade só valeria na primeira
página. O trecho `noscript` não se aplica: o site não funciona sem JavaScript.

**Validação**: `gtm_id` com `regex:/^GTM-[A-Z0-9]{4,12}$/`.

## R-15 — Eventos do dataLayer

**Decisão**:

- `subscribe`: no `AuthModal`, depois do `cadastrar` concluído com sucesso.
- `addtocart` `{amount}`: no `DepositModal`, quando a cobrança Pix é criada (etapa `pix`).
- `purchase` `{amount}`: quando o depósito vira `Aprovado` (Pix ou cartão). O `DepositModal`, ao
  fechar com depósito pendente, entrega o código ao `tracking.js` (`acompanhar_compra(codigo,
  valor, expira_em)`), que continua consultando `GET area-cliente/depositos/{codigo}` a cada 10s até
  a situação final ou a expiração, enquanto a página estiver aberta. Os códigos já enviados ficam em
  `localStorage` (`wssports.compras_enviadas`, últimos 50) para não repetir (Edge Case de aviso
  duplicado e de reabrir o modal).

`amount` é número com 2 casas (ex.: `50`), como o antigo (`valor` numérico).

## R-16 — Histórico de configurações (Clarification 4)

**Decisão**: tabela `configuracoes_historicos` (data-model §2) e service `HistoricoConfiguracoes`,
no padrão do `HistoricoCotacoes`:

- `alterar(Usuarios $autor, string $secao, Model $registro, array $dados)`: guarda os valores
  originais dos campos enviados, aplica `update`, compara e grava só os que mudaram, numa transação;
  sem mudança, não grava (FR-003a).
- `registrar(Usuarios $autor, string $secao, AcaoConfiguracao $acao, ?int $item_id, array
  $alteracoes = [])`: banners, avisos, logo, vincular/desvincular e conectar/desconectar.
- Campos secretos (`chave_callmebot`) viram `{"antes": "***", "depois": "alterada"}`; enums e datas
  são gravados como texto; decimais com 2 casas.

Os controllers `Configuracoes*` trocam `$configuracoes->update(...)` por
`$historico->alterar(...)` (uma linha cada). Sem tela de consulta.

**Seções** (`secao`): `banca`, `logo`, `banners`, `avisos`, `regras`, `mercado_pago`,
`robo_whatsapp`, `pagamentos`, `criar_aposta`, `apuracao`, `encerramento`, `cassino`, `afiliados`,
`avisos_whatsapp`, `ao_vivo`, `sorteio_cotacoes`, `notificacoes`.

## R-17 — Tela no painel

**Decisão**: página `PanelSettings` em `/painel/configuracoes` (`PainelController::configuracoes`,
item "Configurações", ícone `settings`, visível com `configuracoes.editar`), abas `banca`, `visual`,
`regras`, `integracoes`, `operacao` na query (`?aba=`).

- Formulários por **definição de campos** (`utils/settings_fields.js`: chave, rótulo, tipo —
  `interruptor`, `valor`, `percentual`, `inteiro`, `selecao`, `texto`, `telefone`, `link`,
  `segredo` —, ajuda, opções, limites) renderizados por um componente genérico `SettingsForm`, e um
  hook `useSecaoConfiguracoes(rota)` (carrega, guarda o original, conta alterados, desfaz, salva só
  os alterados, erros 422 por campo). As 12 seções de formulário viram dados, não 12 componentes.
- Barra de salvar: `SaveBar` existente (quantidade, Desfazer, Salvar), uma por seção aberta.
- Aviso ao trocar de aba/sair com alterações: `confirmar` de `utils/alerts` e `beforeunload`.
- Aba Operação: seções em sanfona (uma aberta por vez), para a página não ficar enorme no celular.
- Reaproveitados: `PanelShell`, `PanelTabs`, `SaveBar`, `ToggleSwitch`, `ModalCard`, `ModalHeader`,
  `ConfirmModal`, `ActionsMenu`, `Pagination`, `EmptyMessage`, `Spinner`, `useCampoValor`.

**Componentes novos**: `SettingsForm`, `SettingsSection`, `ThemePicker`, `LogoUploader`,
`BannersManager`, `BannerFormModal`, `NoticesManager`, `NoticeFormModal`, `ImagePreviewModal`,
`RulesEditor`, `MercadoPagoCard`, `WhatsAppBotCard`.

## R-18 — Ordem dos banners

**Decisão**: botões "subir"/"descer" no `BannersManager`, que trocam a `ordem` dos dois banners
vizinhos com dois `PUT banners/{id}` (rota existente). Quem não tinha ordem distinta (todos `0`) é
renumerado de 10 em 10 na primeira troca, pela ordem exibida.

**Alternativas**: arrastar e soltar — exige dependência ou muito código de toque no celular; rota
nova de reordenação — o CRUD já aceita `ordem`.

## R-19 — Diferenças em relação à spec 006/007/014/019 (registradas)

- Regras passam de texto puro para HTML limpo (spec 006, FR-021b) — pedido da spec 021.
- `GET configuracoes/gateway` passa a aceitar o Supervisor (só leitura) — Clarification 1.
- Contatos, tema e ícones deixam de ser fake (spec 005) — Princípio IX.

## R-20 — Dados fake

Removidos nesta spec: `DadosFake::tema`, `DadosFake::contatos`, `DadosFake::icones` (spec 005) e os
arquivos `public/fakes/icone.png` e `public/fakes/icones/*` (movidos para `public/images/icones/`
como ícones padrão do projeto). A regra `/fakes/` do `vite.config.js` (cache do service worker) sai
junto. Continua fake: `DadosFake::indicadores` (Acumuladão, spec própria).
