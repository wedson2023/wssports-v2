# Data Model: Painel — Configurações gerais

**Feature**: `021-painel-configuracoes` | **Data**: 2026-10-25

Todas as colunas novas têm `->comment()` em português (constituição). Nenhuma exclusão física.

## 1. `configuracoes` (registro único, já existe) — colunas novas

Migration `2026_10_27_000001_add_banca_visual_configuracoes_table.php`.

| Coluna | Tipo | Padrão | Comentário / regra |
|---|---|---|---|
| `whatsapp` | `varchar(20)` null | null | Telefone do WhatsApp de atendimento, só dígitos com DDI (ex.: 5511999999999); vazio = sem botão nem atalhos |
| `mensagem_whatsapp` | `varchar(500)` null | texto do fake (R-04) | Mensagem inicial ao abrir o WhatsApp de atendimento |
| `botao_whatsapp` | `boolean` | `true` | Se o botão flutuante do WhatsApp aparece no site (só com número preenchido) |
| `instagram` | `varchar(255)` null | null | Link do Instagram da banca (http/https); vazio = ícone escondido |
| `facebook` | `varchar(255)` null | null | Link do Facebook da banca |
| `twitter` | `varchar(255)` null | null | Link do Twitter/X da banca |
| `youtube` | `varchar(255)` null | null | Link do YouTube da banca |
| `jogo_responsavel` | `varchar(255)` null | `https://www.gamblingtherapy.org/pt-br/` | Link de jogo responsável do rodapé; vazio = imagem sem link |
| `cor_tema` | `varchar(7)` | `#c40808` | Cor principal do tema: #c40808, #d0af01, #008000, #006eb1, #fe6a00 ou #b91552 |
| `cor_fundo` | `varchar(7)` | `#000000` | Cor de fundo padrão do site: #000000 (escuro) ou #FFFFFF (claro) |
| `gtm_id` | `varchar(20)` null | null | Contêiner do Google Tag Manager (GTM-XXXX); vazio = sem GTM |
| `pasta_icones` | `varchar(100)` null | null | Pasta (disco public) dos ícones do aplicativo gerados da logo; vazio = ícones padrão |
| `robo_whatsapp_situacao` | `varchar(30)` | `Desconectado` | Situação do robô na Evolution API: Desconectado, Aguardando leitura ou Conectado |
| `robo_whatsapp_numero` | `varchar(20)` null | null | Número conectado ao robô (só dígitos) |
| `robo_whatsapp_perfil` | `varchar(100)` null | null | Nome do perfil do WhatsApp conectado |
| `robo_whatsapp_atualizado_em` | `timestamp` null | null | Última mudança de situação do robô |

**Alterada** (migration de dados `2026_10_27_000003_converter_regras_html.php`, R-09): `regras`
passa a guardar HTML limpo; o texto atual vira `<p>` por linha.

**Model `Configuracoes`** (alterado):

- `$fillable`: as colunas acima, menos `pasta_icones` e `robo_whatsapp_*` (gravadas só pelos
  services).
- `casts`: `botao_whatsapp` boolean, `robo_whatsapp_situacao` → `SituacaoRoboWhatsapp`,
  `robo_whatsapp_atualizado_em` datetime.
- `CORES_TEMA` (cor → cor escura), `CORES_FUNDO`; `tema()`, `contatos()`, `icones()`,
  `url_icone(int)`; `REGRAS_PADRAO` em parágrafos HTML; sai `paragrafos_regras()`.

**Validação** (`ConfiguracoesBancaRequest`):

| Campo | Regra |
|---|---|
| `nome_sistema` | obrigatório, até 100 |
| `mensagem_bilhete` | obrigatório, até 500 |
| `whatsapp` | opcional; só dígitos depois de limpar; 12 ou 13 dígitos começando com DDI (ex.: 55) |
| `mensagem_whatsapp` | opcional, até 500 |
| `botao_whatsapp` | boolean |
| `instagram`, `facebook`, `twitter`, `youtube`, `jogo_responsavel` | opcionais, `url:http,https`, até 255 |
| `cor_tema` | `in:` as 6 cores |
| `cor_fundo` | `in:#000000,#FFFFFF` |
| `gtm_id` | opcional, `regex:/^GTM-[A-Z0-9]{4,12}$/` (maiúsculas depois de limpar) |

**Ao vivo** (`ConfiguracoesAoVivoRequest`, colunas existentes): `ao_vivo_habilitado` boolean;
`segundos_trava_ao_vivo` 0–120; `minutos_permanencia_ao_vivo` 1–60; `minuto_limite_ao_vivo` 1–130;
`cotacao_maxima_ao_vivo` 1,01–1000 (2 casas). `ao_vivo_travado`/`_em` só leitura (FR-030).

**Sorteio de cotação** (`ConfiguracoesSorteioCotacoesRequest`, colunas existentes):
`sorteio_ambas_marcam_minimo/maximo` e `sorteio_ambas_nao_marcam_minimo/maximo`, 1,01–50, 2 casas,
mínimo ≤ máximo de cada par.

## 2. `configuracoes_historicos` (nova)

Migration `2026_10_27_000002_create_configuracoes_historicos_table.php`. Model
`ConfiguracoesHistoricos` com `SoftDeletes`, `alteracoes` → array, relação `autor` (withTrashed).

| Coluna | Tipo | Comentário |
|---|---|---|
| `id` | `id()` | |
| `usuarios_id` | `foreignId` → `usuarios` | Autor da alteração |
| `secao` | `varchar(30)` | Aba ou seção: banca, logo, banners, avisos, regras, mercado_pago, robo_whatsapp, pagamentos, criar_aposta, apuracao, encerramento, cassino, afiliados, avisos_whatsapp, ao_vivo, sorteio_cotacoes ou notificacoes |
| `acao` | `varchar(20)` | Ação: Alteração, Cadastro, Exclusão, Vínculo, Desvínculo, Conexão ou Desconexão |
| `item_id` | `unsignedBigInteger` null | Banner ou aviso alterado (seções banners e avisos); nulo nas demais |
| `alteracoes` | `json` null | Campos alterados {campo: {antes, depois}}; segredos como "***"/"alterada"; nulo nas ações sem campos |
| `created_at`, `updated_at`, `deleted_at` | | |

Índices: `(secao, created_at)`, `usuarios_id` (FK).

**Enum** `AcaoConfiguracao`: `Alteração`, `Cadastro`, `Exclusão`, `Vínculo`, `Desvínculo`,
`Conexão`, `Desconexão`.

## 3. Enum `SituacaoRoboWhatsapp` (novo)

`Desconectado`, `AguardandoLeitura = 'Aguardando leitura'`, `Conectado`.

Transições (pelo aviso da Evolution ou pela consulta ao abrir a aba):

```text
Desconectado ──conectar──▶ Aguardando leitura ──QR lido (open)──▶ Conectado
     ▲                           │ (close/expirou)                    │
     └───────────────────────────┴────────── desconectar / close ─────┘
```

## 4. Permissões

Migration de dados `2026_10_27_000004_criar_permissao_robo_whatsapp.php`: cria
`robo_whatsapp.conectar` (guard `api`) e dá aos usuários cuja função recebe por padrão (só Admin).
`Funcao::PERMISSOES_SITE` ganha a permissão; `pode_usar` a libera só ao Admin (R-13).

## 5. Já existentes, sem mudança de estrutura

- `banners` (`imagem`, `link`, `ordem`, `ativo`) e `avisos` (`titulo`, `imagem`, `link`,
  `inicio_em`, `fim_em`, `ativo`) — spec 006.
- `gateways_pagamento` — spec 007.

## 6. Arquivos no disco `public`

```text
logos/{sha1}.{ext}                              # já existe (spec 006)
icones/{sha1 da logo}/icon-{32..512}.png        # novo (R-05)
icones/{sha1 da logo}/touch-icon-{152,167,180}.png
public/images/icones/...                        # ícones padrão (movidos de public/fakes)
```
