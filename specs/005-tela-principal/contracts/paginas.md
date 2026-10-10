# Contrato: páginas e rotas web (área `/`)

**Feature**: `005-tela-principal` | **Data**: 2026-10-09

Rotas web (não são da API; a coleção do Postman não muda). As rotas da API usadas pela tela já
existem e estão nos contratos das specs 003 e 004.

## Rotas web

| Método | Rota | Resposta | Cache |
|---|---|---|---|
| GET | `/` | Página Inertia `Home` | `no-cache, private` |
| GET | `/regras` | Página Inertia `Rules` | `no-cache, private` |
| GET | `/sw.js` | Service worker (JavaScript), com `Service-Worker-Allowed: /` | `no-cache` |
| GET | `/manifest.webmanifest` | Manifest do PWA (JSON), montado no servidor | `no-cache` |

## Props compartilhadas (todas as páginas)

Enviadas pelo `TratarRequisicoesInertia::share()`.

```json
{
  "versao": "3f9a1c0b",
  "nome_sistema": "WSSports",
  "tema": { "temas": "#c40808", "letter": "#a41f1a", "cor_fundo": "#000000", "logo": "/fakes/logo.png" },
  "contatos": {
    "whatsapp": "5511999999999",
    "mensagem_whatsapp": "Olá! Vim pelo site e gostaria de fazer uma aposta esportiva. Pode me ajudar?",
    "instagram": "https://instagram.com/…", "youtube": null, "twitter": null, "facebook": null,
    "jogo_responsavel": "https://www.gamblingtherapy.org/pt-br/"
  },
  "indicadores": { "acumuladao": true, "cassino": true }
}
```

- `versao`: hash do manifesto do build, igual ao enviado no cabeçalho de versão do Inertia; a
  tela usa `router.reload({ only: ['versao'] })` para conferir a versão ao voltar para a aba.
- `tema`, `contatos`, `indicadores`: fakes (`app/Fakes/DadosFake.php`), no formato que os dados
  reais terão (Princípio IX).

## Página `Home` (`GET /`)

**Query** (mesmas regras de `ListagemPublicaRequest`, spec 003; inválida → filtros padrão):

| Parâmetro | Valores | Padrão |
|---|---|---|
| `tipo` | `pre_jogo`, `ao_vivo` | `pre_jogo` |
| `esporte` | `FUTEBOL`, `BASQUETE`, `LUTAS`, `VÔLEI`… | `FUTEBOL` |
| `dia` | `hoje`, `amanha`, `depois_de_amanha` | `hoje` |
| `busca` | até 100 caracteres | — |
| `campeonato` | id do campeonato (filtro do menu; parâmetro novo, R-25, autorizado) | — |
| `pagina` | inteiro ≥ 1 | 1 |

`por_pagina` é fixo em 50 (máximo 100, Constituição).

**Props**:

```json
{
  "filtros": { "tipo": "pre_jogo", "esporte": "FUTEBOL", "dia": "hoje", "busca": null, "campeonato": null },
  "listagem": { "tipo": "pre_jogo", "total": 123, "campeonatos": [ … ], "paises": [ … ], "meta": { … } },
  "configuracoes": {
    "mensagem_bilhete": "BOA SORTE!",
    "ao_vivo_habilitado": true,
    "esportes_permitidos": ["FUTEBOL", "BASQUETE"],
    "apostar_outros_esportes": true,
    "multiplicador": 1000,
    "premio_maximo": "5000.00",
    "ganho_multiplo_palpites": "0.00",
    "comissao_por_premio": "0.00",
    "valor_minimo_aposta": "2.00",
    "valor_maximo_aposta": "1000.00",
    "quantidade_minima_opcoes": 1,
    "quantidade_maxima_opcoes": 20,
    "periodo_jogos": "Depois de amanhã"
  },
  "banners": [ { "imagem": "/fakes/banners/1.jpg", "link": null } ],
  "aviso": null
}
```

- `listagem`: mesmo formato da resposta de `GET /api/publico/confrontos` (spec 003), gerado pelo
  mesmo serviço com o público visitante. Cada recarga traz só a página pedida; a tela soma as
  páginas e junta o campeonato dividido entre duas páginas (research.md, R-13).
- `banners`: fake (`DadosFake::banners()`).
- `periodo_jogos`: período de jogos do visitante (`Hoje`, `Amanhã`, `Depois de amanhã`); define
  quantas abas de data aparecem (0, 2 ou 3), como no sistema antigo.
- `aviso`: texto ou nulo; com `tipo=ao_vivo` e o ao vivo indisponível, a página volta ao pré-jogo
  e traz aqui o motivo (`"O ao vivo não está disponível."`), mostrado num alerta (T072).
- `comissao_por_premio`: sempre `"0.00"` para o visitante; o "vendedor paga" mostra o mesmo valor
  do prêmio (FR-037a).

**Recargas parciais usadas pela tela**:

| Ação | Chamada |
|---|---|
| Trocar esporte, dia, busca ou campeonato | `router.reload({ only: ['listagem', 'filtros'], data: {…}, preserveState: true })`, com a página voltando a 1 |
| Rolagem infinita | `router.reload({ only: ['listagem'], data: { pagina: n + 1 } })` |
| Ao vivo | `usePoll(7000, { only: ['listagem'] })` com `tipo=ao_vivo` |
| Conferir versão ao voltar para a aba | `router.reload({ only: ['versao'] })` |

**Erros**:

- 429 (limite de requisições por IP, o mesmo da listagem pública): alerta com a mensagem
  "Muitas requisições. Tente novamente em N segundos."; a lista atual continua na tela.
- Ao vivo indisponível (`ao_vivo_habilitado` falso ou ao vivo travado): a tela volta para
  `tipo=pre_jogo` e `esporte=FUTEBOL` e mostra a mensagem.
- Versão diferente: 409 do Inertia → recarga completa automática (R-15).

**Cliente logado**: com o cabeçalho `Authorization: Bearer <token do cliente>` (enviado pela tela
em todas as requisições quando há sessão de cliente), `listagem` e `configuracoes` seguem as regras
do cliente e a prop `saldo` (texto com 2 casas) traz o saldo dele; sem token, `saldo` é nulo. Token
inválido: `listagem.token_recusado` verdadeiro e a tela encerra a sessão.

**Vendedor logado**: com o token do vendedor, `listagem` e `configuracoes` seguem as regras dele
(`comissao_por_premio` real). Token de gestor do painel é tratado como visitante. A prop `apostador`
diz como o cupom aposta: `cliente`, `vendedor` ou `visitante` (gestor ou token recusado); é nula
sem o cabeçalho `Authorization`. A tela usa essa prop para deixar de mandar o token do gestor.

## Página `Rules` (`GET /regras`)

**Props**:

```json
{ "regras": ["Parágrafo 1…", "Parágrafo 2…"] }
```

- `regras`: fake (`DadosFake::regras()`), lista de parágrafos em texto simples (sem HTML).

## Manifest (`GET /manifest.webmanifest`)

```json
{
  "name": "WSSports",
  "short_name": "WSSports",
  "description": "WSSports",
  "display": "standalone",
  "start_url": "/",
  "scope": "/",
  "id": "/",
  "background_color": "#000000",
  "theme_color": "#000000",
  "icons": [ { "src": "/fakes/icones/icon-192.png", "sizes": "192x192", "type": "image/png" } ]
}
```

- `name` e `short_name`: `nome_sistema` (real).
- `icons`: fake (`DadosFake::icones()`), nos tamanhos do manifest atual (32, 64, 96, 128, 168,
  192, 256 e 512).
- `background_color` e `theme_color`: `cor_fundo` do tema (fake).

## API pública consumida (já existe)

| Uso | Rota | Contrato |
|---|---|---|
| Detalhe do "+N" (pré-jogo) | `GET /api/publico/confrontos/{confronto}` | spec 004, `contracts/api.md` |
| Detalhe do "+N" (ao vivo) | `GET /api/publico/confrontos-ao-vivo/{confronto_ao_vivo}` | spec 004 |
| Código da aposta | `POST /api/publico/apostas` | spec 004 (201, 409, 422, 429) |
| Bilhete pelo código | `GET /api/publico/apostas/{codigo}` | spec 004 (200, 404, 429) |
| Entrar (usuário do painel) | `POST /api/auth/login`, `POST /api/auth/logout` | spec 001 |
| Entrar (apostador) | `POST /api/area-cliente/auth/login`, `POST /api/area-cliente/auth/logout`, `GET /api/area-cliente/meus-dados` | spec 002 |
| Criar conta (apostador) | `POST /api/area-cliente/cadastro` | spec 002 |
| Aposta do cliente logado | `POST /api/area-cliente/apostas`, `GET /api/area-cliente/apostas/{codigo}/situacao` | spec 004 (201, 202, 409, 422) |
| Aposta do vendedor logado | `POST /api/apostas`, `GET /api/apostas/{codigo}/situacao` | spec 004 (201, 202, 409, 422) |
| Validar código (vendedor) | `GET /api/apostas/pendentes/{codigo}`, `POST /api/apostas/pendentes/{codigo}/validar` | spec 004 (200, 409, 422, 429) |
