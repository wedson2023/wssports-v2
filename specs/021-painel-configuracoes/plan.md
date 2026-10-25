# Implementation Plan: Painel — Configurações gerais

**Branch**: `021-painel-configuracoes` | **Date**: 2026-10-25 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/021-painel-configuracoes/spec.md`

## Summary

Cria a tela "Configurações" do painel (a antiga "Configuração", `/adm/config`), backend e frontend,
reaproveitando as rotas de configuração que já existem (specs 006 a 014 e 019) e acrescentando o que
falta:

- **Banca** (R-04): contatos, tema (6 cores + fundo) e GTM-ID gravados em `configuracoes`, trocando
  `DadosFake::tema` e `DadosFake::contatos` no site, no manifest, nos e-mails e nas notificações.
- **Visual** (R-05, R-06, R-18): logo gerando os ícones do aplicativo (`IconesAplicativo`, GD, pasta
  por hash) no lugar de `DadosFake::icones`; banners e avisos pela tela, com os CRUDs existentes.
- **Regras** (R-07 a R-09): editor TipTap; HTML limpo pelo HTMLPurifier; texto atual convertido em
  parágrafos; `/regras` mostra a formatação.
- **Integrações** (R-10 a R-13): situação e vínculo do Mercado Pago (spec 007) e robô do WhatsApp
  pela Evolution API (conectar por QR, aviso automático de conexão, desconectar), só o Admin age.
- **GTM** (R-14, R-15): carregado pelo React só nas páginas do apostador; `subscribe`, `addtocart` e
  `purchase` (este acompanhando o depósito mesmo com o modal fechado).
- **Operação** (R-02, R-03): seções para as 7 configurações com rota + ao vivo, sorteio de cotação e
  limite de notificações.
- **Histórico** (R-16): `configuracoes_historicos` com autor, seção, ação e antes/depois.
- **Frontend** (R-17): página `PanelSettings` com 5 abas e formulários por definição de campos
  (`SettingsForm` + `useSecaoConfiguracoes`).

Decisões em [research.md](research.md) (R-01 a R-20).

## Technical Context

**Language/Version**: PHP 8.2 (^8.2); JavaScript (ES2022) com JSX

**Primary Dependencies**: as instaladas (Laravel 12, Inertia 2, React 19, styled-components 6,
sweetalert2, jwt-auth, spatie/laravel-permission, GD). **Novas** (justificadas em R-07/R-08):
`ezyang/htmlpurifier` ^4.18 (composer); `@tiptap/react`, `@tiptap/pm`, `@tiptap/starter-kit`,
`@tiptap/extension-text-align`, `@tiptap/extension-text-style` ^3 (npm).

**Storage**: MySQL; 16 colunas novas em `configuracoes`, 1 tabela nova
(`configuracoes_historicos`), 2 migrations de dados (regras em HTML; permissão do robô). Disco
`public`: `icones/{hash}/`.

**Testing**: nenhum teste automatizado (Constituição); validação pelo [quickstart.md](quickstart.md)

**Target Platform**: navegadores atuais, desktop e celular; deploy contínuo (spec 013); Evolution API
v2 (`evoapicloud/evolution-api`) acessível pelo servidor e alcançando o `APP_URL` para o webhook.

**Project Type**: monólito Laravel + Inertia com API JWT

**Performance Goals**: abas abrem com os dados em até 1 s (uma consulta ao registro único por
seção); geração dos 11 ícones em até 2 s para uma logo de 1 MB; situação do robô em até 5 s depois
da leitura do QR (SC-006: webhook + consulta a cada 3 s); chamadas à Evolution com tempo limite de
10 s sem travar o resto da tela.

**Constraints**: permissões sempre no servidor; segredos (chave da Evolution, token do webhook,
chave do CallMeBot) nunca no navegador nem no histórico; HTML só pelo purificador; props
compartilhadas com o mesmo formato atual (troca de fake sem mexer nos componentes); paginação de
banners e avisos 20 (máx. 100); visual e componentes do painel (specs 014 a 020) e regras de
modais, cabeçalho e campos da constituição.

**Scale/Scope**:

| Item | Quantidade |
|---|---|
| Migrations | 4 (colunas em `configuracoes`, tabela de histórico, dados das regras, permissão) |
| Rotas da API novas / alteradas | 9 (banca ×2, ao-vivo ×2, sorteio-cotacoes ×2, robo-whatsapp GET/DELETE, conectar) + 1 webhook / 1 (`GET configuracoes/gateway`: permissão) |
| Rotas web novas | 1 (`/painel/configuracoes`) |
| Services novos / alterados | 5 (`HistoricoConfiguracoes`, `IconesAplicativo`, `LimpezaHtml`, `ClienteEvolution`, `RoboWhatsapp`; a conversão das regras fica na migration de dados, R-09) / 2 (`NotificacoesPush`, `VinculoMercadoPago`) |
| Controllers novos / alterados | 5 (`ConfiguracoesBancaController`, `ConfiguracoesAoVivoController`, `ConfiguracoesSorteioCotacoesController`, `RoboWhatsappController`, `WebhooksEvolutionController`) / 16 (tabela abaixo) |
| Models / Enums novos | 1 (`ConfiguracoesHistoricos`) / 2 (`AcaoConfiguracao`, `SituacaoRoboWhatsapp`) |
| Requests / Resources novos | 3 (`ConfiguracoesBancaRequest`, `ConfiguracoesAoVivoRequest`, `ConfiguracoesSorteioCotacoesRequest`) / 3 (`ConfiguracoesBancaResource`, `ConfiguracoesAoVivoResource`, `ConfiguracoesSorteioCotacoesResource`) |
| Exceções novas | 1 (`RoboIndisponivelException`) |
| Componentes novos / páginas novas | 12 (R-17) / 1 (`PanelSettings`) |
| Hooks / utils novos | 1 (`useSecaoConfiguracoes`) / 2 (`utils/tracking.js`, `utils/settings_fields.js`) |

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Princípio / Regra | Verificação | Status |
|---|---|---|
| I. Nomes no backend | Tabela `configuracoes_historicos` (prefixo da principal); colunas em português (`whatsapp`, `url`-likes e `gtm_id` como siglas/termos consagrados); enums `AcaoConfiguracao` (`Alteração`, `Exclusão`…) e `SituacaoRoboWhatsapp` (`AguardandoLeitura = 'Aguardando leitura'`); permissão `robo_whatsapp.conectar`; rotas `robo-whatsapp`, `sorteio-cotacoes`, `ao-vivo` (kebab-case) | ✅ Pass |
| I. Nomes no frontend | Componentes em inglês PascalCase (`PanelSettings`, `SettingsForm`, `WhatsAppBotCard`…); hook `useSecaoConfiguracoes`; utils `tracking.js` e `settings_fields.js` (inglês snake_case); variáveis e props snake_case em português | ✅ Pass |
| II. Idioma | Artefatos, comentários, mensagens e textos em português | ✅ Pass |
| III. Componentes | Cada componente em pasta própria com `index.jsx` e `styles.jsx` | ✅ Pass |
| IV. Escopo estrito | Arquivos existentes listados abaixo, confirmados pelo responsável em 2026-10-25 ("sigo o recomendado", no `/speckit-implement`, depois do `/speckit-analyze` recomendar a autorização) | ✅ Pass |
| V. Legibilidade | Revisão ao final de cada tarefa | ✅ Pass |
| VI. Consistência | Controllers `Configuracoes*` com show/update + Request + Resource + `GarantirPermissaoCliente`; histórico no padrão do `HistoricoCotacoes`; cliente HTTP externo no padrão do `ClienteMercadoPago`; webhook no padrão do `WebhooksMercadoPagoController`; permissão nova em `Funcao.php` + migration de dados (padrão da spec 019); componentes do painel reaproveitados | ✅ Pass |
| VII. Sistema antigo | `screens/adm/config`, `ConfigController`, `WhatsAppController`, `app.blade.php`, modais `register` e `credits` analisados na `main` `408cf730` (R-01); diferenças aprovadas em Clarifications e listadas na spec | ✅ Pass |
| VIII. Fidelidade visual | Visual do painel novo aprovado (Clarifications); referências em [contracts/paginas.md](contracts/paginas.md) §5; o site não muda de visual (só passa a usar os dados reais) | ✅ Pass |
| IX. Dados fake | Remove `DadosFake::tema`, `contatos` e `icones` e os arquivos `public/fakes` (R-20); fica só `indicadores` | ✅ Pass |
| Stack | 6 dependências novas justificadas (R-07, R-08) | ✅ Pass |
| Timestamps / soft delete | `configuracoes_historicos` com `timestamps()` + `softDeletes()` e model com `SoftDeletes` | ✅ Pass |
| Comentários de colunas | Todas as colunas novas com `->comment()` ([data-model.md](data-model.md)) | ✅ Pass |
| Referências a tabelas inexistentes | Nenhuma (`item_id` aponta para banners ou avisos conforme a seção, sem FK porque são duas tabelas) | ✅ Pass |
| Paginação ≤ 100 | Banners e avisos 20 por página; histórico sem tela | ✅ Pass |
| Atualização do frontend | Configurações vindas do servidor a cada carga; ícones com endereço novo por troca; GTM e tema sem publicar versão | ✅ Pass |
| Tamanho dos modais | `BannerFormModal`/`NoticeFormModal` com altura do conteúdo; `ImagePreviewModal` largura da imagem limitada à tela; tela cheia no celular; cabeçalho e rodapé sem encolher | ✅ Pass |
| Cabeçalho dos modais | Todos com `ModalHeader` | ✅ Pass |
| Campos de itens existentes | Cores e fundo escolhidos de amostras; situações e opções em seleção/interruptor; meios de depósito em chips | ✅ Pass |
| Sem testes | Nenhum arquivo, tarefa ou dependência de teste | ✅ Pass |
| Postman | Regenerado na entrega (tarefa explícita) | ✅ Pass |

**Resultado do gate**: aprovado. A lista de arquivos existentes abaixo foi confirmada pelo responsável
em 2026-10-25 (Princípio IV).

Reavaliado depois da Fase 1: sem violação nova.

### Arquivos existentes que serão alterados (Princípio IV)

| Arquivo | Alteração | Motivo |
|---|---|---|
| `composer.json` / `composer.lock`, `package.json` / `package-lock.json` | Dependências novas | R-07, R-08 |
| `config/services.php`, `.env.example` | Bloco `evolution` | R-10 |
| `app/Models/Configuracoes.php` | Colunas novas, casts, `CORES_TEMA`, `tema()`, `contatos()`, `icones()`, `url_icone()`, `REGRAS_PADRAO` em HTML; remove `paragrafos_regras()` | R-04, R-05, R-09 |
| `app/Fakes/DadosFake.php` | Remove `tema`, `contatos`, `icones` e as constantes | R-20 |
| `app/Http/Middleware/TratarRequisicoesInertia.php` | Props `tema`, `contatos`, `rastreamento` reais | R-04, R-06 |
| `app/Http/Controllers/ArquivosPwaController.php` | Cor e ícones do manifest | R-04, R-05 |
| `app/Mail/Concerns/DadosEmail.php` | Cor principal real | R-04 |
| `app/Services/NotificacoesPush.php` | Ícone 192 real | R-05 |
| `app/Enums/Funcao.php` | `robo_whatsapp.conectar` em `PERMISSOES_SITE`, só Admin | R-13 |
| `app/Http/Controllers/ConfiguracoesLogoController.php` | Gera ícones, apaga a pasta anterior, `aviso`, histórico | R-05, R-16 |
| `app/Http/Controllers/ConfiguracoesRegrasController.php`, `app/Http/Requests/ConfiguracoesRegrasRequest.php` | HTML limpo, limites, histórico | R-08, R-16 |
| `app/Http/Controllers/RegrasController.php` | `regras` em HTML (limpo de novo na saída) | R-08 |
| `app/Http/Controllers/Configuracoes{Pagamentos,CriarAposta,Apuracao,Encerramento,Cassino,Afiliados,AvisosWhatsapp}Controller.php` | `update` pelo `HistoricoConfiguracoes` (1 linha cada) | R-16 |
| `app/Http/Controllers/BannersController.php`, `AvisosController.php` | Histórico em store/update/destroy; banner novo sem ordem entra no fim (maior + 10, até 999) | R-16, R-18 |
| `app/Http/Controllers/GatewayPagamentoController.php` | `show` com `configuracoes.editar` + `pode_alterar`; histórico no `destroy` | R-13, R-16 |
| `app/Services/VinculoMercadoPago.php` (`concluir`) | Histórico `Vínculo` | R-16 |
| `app/Http/Controllers/ClientesNotificacoesController.php` (`alterar_limite`) | Histórico `notificacoes` | R-16 |
| `app/Http/Controllers/PainelController.php`, `routes/api.php`, `routes/web.php` | Página e rotas | contracts |
| `resources/views/app.blade.php` | Favicon e ícones Apple reais | R-06 |
| `vite.config.js` | Remove a regra `/fakes/` do cache | R-20 |
| `public/fakes/icone.png`, `public/fakes/icones/*` | Movidos para `public/images/icones/` | R-20 |
| `resources/js/layouts/PublicLayout/index.jsx` | Carrega o GTM | R-14 |
| `resources/js/components/WhatsAppButton/index.jsx` | `botao_whatsapp` | FR-007 |
| `resources/js/components/Footer/index.jsx` | Jogo responsável vazio | FR-007 |
| `resources/js/components/RulesContent/index.jsx` e `styles.jsx` | Regras em HTML | R-08 |
| `resources/js/components/AuthModal/index.jsx` | Evento `subscribe` | R-15 |
| `resources/js/components/DepositModal/index.jsx` | Eventos `addtocart` e `purchase`; entrega o pendente ao fechar | R-15 |
| `resources/js/components/PanelShell/index.jsx` | Item "Configurações" | R-17 |
| `docs/postman/wssports_api.postman_collection.json` | Regenerada | Constituição |
| `specs/005-tela-principal/spec.md`, `specs/006-ajustes-tela-principal/spec.md`, `specs/007-painel-cliente-pagamentos/spec.md` | Nota "Alterado pela spec 021" (fakes removidos; regras em HTML; Supervisor vê a situação do gateway) | coerência dos artefatos |

## Project Structure

### Documentation (this feature)

```text
specs/021-painel-configuracoes/
├── plan.md · research.md · data-model.md · quickstart.md
├── contracts/ (api.md, paginas.md)
├── checklists/requirements.md
└── tasks.md   (/speckit-tasks)
```

### Source Code

```text
app/
├── Enums/{AcaoConfiguracao,SituacaoRoboWhatsapp}.php                           # novos
├── Enums/Funcao.php                                                            # alterado
├── Exceptions/RoboIndisponivelException.php                                    # novo
├── Fakes/DadosFake.php                                                         # alterado
├── Models/ConfiguracoesHistoricos.php                                          # novo
├── Models/Configuracoes.php                                                    # alterado
├── Mail/Concerns/DadosEmail.php                                                # alterado
├── Services/{HistoricoConfiguracoes,IconesAplicativo,LimpezaHtml,ClienteEvolution,RoboWhatsapp}.php  # novos
├── Services/{NotificacoesPush,VinculoMercadoPago}.php                          # alterados
└── Http/
    ├── Controllers/{ConfiguracoesBanca,ConfiguracoesAoVivo,ConfiguracoesSorteioCotacoes,RoboWhatsapp,WebhooksEvolution}Controller.php  # novos
    ├── Controllers/... (14 existentes, tabela acima)                           # alterados
    ├── Middleware/TratarRequisicoesInertia.php                                 # alterado
    ├── Requests/{ConfiguracoesBanca,ConfiguracoesAoVivo,ConfiguracoesSorteioCotacoes}Request.php  # novos
    ├── Requests/ConfiguracoesRegrasRequest.php                                 # alterado
    └── Resources/{ConfiguracoesBanca,ConfiguracoesAoVivo,ConfiguracoesSorteioCotacoes}Resource.php  # novos
config/services.php                                                             # alterado
database/migrations/
├── 2026_10_27_000001_add_banca_visual_configuracoes_table.php
├── 2026_10_27_000002_create_configuracoes_historicos_table.php
├── 2026_10_27_000003_converter_regras_html.php                                 # dados
└── 2026_10_27_000004_criar_permissao_robo_whatsapp.php                         # dados
routes/{api,web}.php                                                            # alterados
resources/views/app.blade.php                                                   # alterado
public/images/icones/                                                           # ícones padrão (movidos)
resources/js/
├── pages/PanelSettings/{index,styles}.jsx                                      # novo
├── components/{SettingsForm,SettingsSection,ThemePicker,LogoUploader,BannersManager,BannerFormModal,NoticesManager,NoticeFormModal,ImagePreviewModal,RulesEditor,MercadoPagoCard,WhatsAppBotCard}/  # novos
├── hooks/useSecaoConfiguracoes.js                                              # novo
├── utils/{tracking,settings_fields}.js                                         # novos
├── layouts/PublicLayout/index.jsx                                              # alterado
└── components/{PanelShell,WhatsAppButton,Footer,RulesContent,AuthModal,DepositModal}/  # alterados
docs/postman/wssports_api.postman_collection.json                               # regenerada
```

**Structure Decision**: monólito existente; página Inertia no `PanelShell`; API no padrão dos
controllers `Configuracoes*` (show/update, Request, Resource, `GarantirPermissaoCliente`); integração
externa separada em cliente HTTP + service de regra; formulários do painel guiados por definição de
campos para não criar um componente por seção.

## Complexity Tracking

Nenhuma violação a justificar. Dependências novas (TipTap e HTMLPurifier) justificadas em R-07/R-08:
o editor amigável foi pedido na spec e HTML vindo do navegador não pode ser limpo com segurança sem
um purificador.
