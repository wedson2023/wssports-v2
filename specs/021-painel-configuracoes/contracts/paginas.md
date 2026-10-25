# Contrato das páginas — Configurações gerais

## 1. Props compartilhadas (todas as páginas, `TratarRequisicoesInertia`)

Mesmo formato de hoje; muda só a origem (configuração no lugar do `DadosFake`) e entram os campos
marcados como **novo**.

```json
{
  "nome_sistema": "WSSports",
  "tema": {
    "temas": "#006eb1",
    "letter": "#024b77",
    "cor_fundo": "#000000",
    "logo": "/storage/logos/ab12....png",
    "icones": {
      "favicon": "/storage/icones/ab12.../icon-32.png",
      "apple": { "152": "...", "167": "...", "180": "..." },
      "manifest": [{ "src": "/storage/icones/ab12.../icon-192.png", "sizes": "192x192", "type": "image/png" }]
    }
  },
  "contatos": {
    "whatsapp": "5511988887777",
    "mensagem_whatsapp": "Olá! ...",
    "botao_whatsapp": true,
    "instagram": "https://...",
    "youtube": null,
    "twitter": null,
    "facebook": null,
    "jogo_responsavel": "https://www.gamblingtherapy.org/pt-br/"
  },
  "rastreamento": { "gtm_id": "GTM-ABC1234" },
  "indicadores": { "acumuladao": true, "cassino": true }
}
```

- `tema.icones` (**novo**): sem logo, os ícones padrão `/images/icones/...`.
- `contatos.botao_whatsapp` (**novo**).
- `rastreamento` (**novo**): `gtm_id` ou `null`.

## 2. `app.blade.php`

Troca os `/fakes/...` por `$page['props']['tema']['icones']`: `<link rel="icon">` com o `favicon` e
os três `apple-touch-icon` (152, 167, 180; o sem `sizes` usa o 180). O manifest continua em
`/manifest.webmanifest`. Nenhum código do GTM no blade (research R-14).

## 3. Manifest (`/manifest.webmanifest`)

`background_color`/`theme_color` = `tema.cor_fundo` da configuração; `icons` = `tema.icones.manifest`.

## 4. Componentes do site alterados

| Componente | Mudança |
|---|---|
| `PublicLayout` | Carrega o GTM (`carregar_gtm`) nas páginas do apostador (R-14) |
| `WhatsAppButton` | Só aparece com `botao_whatsapp` ligado e `whatsapp` preenchido (FR-007) |
| `Footer` | Imagem de jogo responsável sem link quando `jogo_responsavel` vazio |
| `RulesContent` | Bloco das regras da banca com o HTML (`regras` string) no lugar da lista de parágrafos |
| `AuthModal` | `subscribe` ao concluir o cadastro |
| `DepositModal` | `addtocart` ao gerar o Pix; `purchase` ao aprovar; entrega o pendente ao `tracking.js` ao fechar |

Página `Rules`: prop `regras` passa de `string[]` para `string` (HTML limpo, vazio = bloco escondido).

## 5. Tela `PanelSettings` (`/painel/configuracoes`)

Referência visual do antigo: `screens/adm/config` (formulário em grade de campos com rótulo e dica,
lista de banners/popups com link e ícones de excluir, ver e salvar). Visual do painel novo (specs
014 a 020): `PanelShell`, `PanelTabs`, cartões de seção, `SaveBar`, modais com `ModalHeader`.

Query: `?aba=banca|visual|regras|integracoes|operacao` (padrão `banca`); na Operação,
`&secao=pagamentos|...` abre a sanfona.

### Aba Banca

Seções (cartões): **Identificação** (nome do sistema, mensagem do bilhete); **Atendimento**
(WhatsApp, mensagem inicial, botão flutuante); **Redes sociais** (Instagram, Facebook, Twitter/X,
YouTube, jogo responsável); **Tema** (`ThemePicker`: 6 amostras redondas com nome, marcada a atual;
fundo Escuro/Claro); **Rastreamento** (GTM-ID com dica "Deixe vazio para não usar"). Uma `SaveBar`
para a aba.

### Aba Visual

- **Logo** (`LogoUploader`): prévia atual sobre fundo escuro e claro, botão "Enviar nova logo",
  dica "PNG quadrado de 512x512, até 1 MB"; depois do envio, toast e grade com 4 ícones gerados
  (32, 96, 192, 512); `aviso` em faixa amarela.
- **Banners do slide** (`BannersManager`): botão "Cadastrar banner"; linhas com miniatura 128x40,
  link, situação (chip Ativo/Inativo), subir/descer e menu (Ver, Editar, Ativar/Desativar,
  Excluir); vira cartões no celular; vazio: "Nenhum banner cadastrado. O carrossel não aparece no
  site." `BannerFormModal`: imagem (prévia 1280x405), link, ativo.
- **Avisos da banca (pop-up)** (`NoticesManager`): mesmo padrão; colunas miniatura, título,
  período, situação. `NoticeFormModal`: imagem (dica 500x500), título, link, início, fim, ativo.
- `ImagePreviewModal`: imagem em tamanho real.

### Aba Regras

`RulesEditor`: barra (parágrafo/títulos, negrito, itálico, sublinhado, cor, listas, alinhamento,
link, desfazer/refazer) fixa no topo do editor; contador "N de 10.000 caracteres"; botão "Ver como
fica" (prévia com o estilo da página de regras); `SaveBar`.

### Aba Integrações

- `MercadoPagoCard`: logo do Mercado Pago, chip de situação (Vinculado verde, Falha na renovação
  amarelo, Desvinculado cinza), datas; botões (só Admin): "Vincular Mercado Pago" /
  "Sincronizar novamente" e "Desvincular" (confirmação). Ao voltar da aba do Mercado Pago
  (`visibilitychange`), recarrega a situação.
- `WhatsAppBotCard`: ícone do WhatsApp, chip (Conectado verde, Aguardando leitura azul,
  Desconectado cinza, Indisponível vermelho), número e perfil; botões (só Admin): "Conectar" (abre
  o QR 264x264 com "Abra o WhatsApp no celular da banca > Aparelhos conectados > Conectar
  aparelho") e "Desconectar" (confirmação). Supervisor: só leitura, com a frase "Só o
  administrador pode alterar".

### Aba Operação

Sanfona com as seções: Pagamentos, Criar Aposta, Apuração, Encerramento de aposta, Cassino,
Afiliados, Aviso de combinação (WhatsApp), Ao vivo, Sorteio de cotação, Notificações manuais. Cada
seção: título, frase do que controla, `SettingsForm` com os campos da definição
(`utils/settings_fields.js`) e `SaveBar` própria. Trocar de seção com alterações pergunta antes.
