# Data Model: Tela principal de apostas esportivas (área `/`)

**Feature**: `005-tela-principal` | **Data**: 2026-10-09

**Nenhuma tabela, coluna ou migration nova.** Esta spec só lê dados que o backend já tem (specs
003 e 004) e dados fake (Princípio IX). Este documento descreve os dados que a tela recebe e o
estado que ela guarda no aparelho.

Chaves vindas do backend ficam como chegam, em `snake_case` (Constituição 2.1.0, Princípio I). O
estado interno do frontend usa `camelCase` em português.

## 1. Dados recebidos do servidor (props do Inertia)

Contrato completo em [contracts/paginas.md](contracts/paginas.md).

### Tema (fake: `DadosFake::tema()`)

| Campo | Tipo | Regra |
|---|---|---|
| `temas` | texto | Uma das 6 cores: `#c40808`, `#d0af01`, `#008000`, `#006eb1`, `#fe6a00`, `#b91552` |
| `letter` | texto | Cor derivada da principal, pela tabela do research.md (R-04) |
| `cor_fundo` | texto | `#000000` (modo inicial escuro) ou `#FFFFFF` (modo inicial claro) |
| `logo` | texto | Caminho da imagem (`/fakes/logo.png`) |

### Contatos (fake: `DadosFake::contatos()`)

| Campo | Tipo | Regra |
|---|---|---|
| `whatsapp` | texto ou nulo | Telefone com DDI só com dígitos; nulo → botão não aparece |
| `mensagem_whatsapp` | texto | "Olá! Vim pelo site e gostaria de fazer uma aposta esportiva. Pode me ajudar?" |
| `instagram`, `youtube`, `twitter`, `facebook` | texto ou nulo | URL; nulo → ícone não aparece |
| `jogo_responsavel` | texto | `https://www.gamblingtherapy.org/pt-br/` |

### Indicadores (fake: `DadosFake::indicadores()`)

| Campo | Tipo | Regra |
|---|---|---|
| `acumuladao` | booleano | Mostra o item "Acumuladão" no menu (sem ação) |
| `cassino` | booleano | Mostra "Cassino" na barra de esportes (sem ação) |

### Banner (fake: `DadosFake::banners()`)

| Campo | Tipo | Regra |
|---|---|---|
| `imagem` | texto | Caminho da imagem (`/fakes/banners/*.png`) |
| `link` | texto ou nulo | URL externa aberta em nova aba; nulo → sem clique |

### Configurações reais

| Campo | Origem | Uso |
|---|---|---|
| `nome_sistema` | `configuracoes.nome_sistema` | Rodapé (copyright), manifest |
| `mensagem_bilhete` | `configuracoes.mensagem_bilhete` | Modal de sucesso e do bilhete |
| `ao_vivo_habilitado` | `configuracoes` e `visitantes_configuracoes` | Mostra a aba "Ao vivo" |
| `esportes_permitidos`, `apostar_outros_esportes` | `visitantes_configuracoes` | Esportes visíveis (FR-010) |
| `multiplicador`, `premio_maximo`, `ganho_multiplo_palpites` | `visitantes_configuracoes` | Estimativa do cupom (R-16) |
| `valor_minimo_aposta`, `valor_maximo_aposta`, `quantidade_minima_opcoes`, `quantidade_maxima_opcoes` | `visitantes_configuracoes` | Só exibição; quem valida é o backend |
| `comissao_por_premio` | sempre `"0.00"` para o visitante | "Vendedor paga" oculto (FR-037a) |

### Listagem de jogos (real: `ListagemConfrontos`)

Formato igual ao da API (`GET /api/publico/confrontos`, contrato da spec 003): `tipo`,
`campeonatos[]` (`id`, `nome`, `pais`, `bandeira`, `confrontos[]`), `paises[]` e `meta`
(`pagina_atual`, `por_pagina`, `ultima_pagina`, `total`).

| Campo do confronto | Uso na tela |
|---|---|
| `id` | Chave do palpite (`confrontos_id` ou `confrontos_ao_vivo_id`, conforme `tipo`) |
| `time_casa`, `time_fora`, `escudo_casa`, `escudo_fora` | Card do jogo |
| `data_inicio` | Horário (fuso `-03:00`) |
| `minutos_para_inicio` | Horário na cor do tema quando menor que 60 |
| `cotacoes.odd1..odd4` | Botões C, E, F, A; `0` ou ausente → cadeado |
| `quantidade_cotacoes` | "+N" |
| `placar_casa`, `placar_fora`, `minuto`, `situacao` (ao vivo) | Placar, período e minuto |

**Variação da cotação** (piscar verde ou vermelho): a tela guarda em memória as cotações da
atualização anterior de cada jogo (`cotacoesAnteriores[id][codigo]`) e compara com as novas; não
vai para o armazenamento.

## 2. Estado guardado no aparelho

Leitura e escrita em `try/catch`; com o armazenamento indisponível, a tela funciona sem
persistência. "Limpar cache" apaga as duas chaves (FR-016).

### Cupom (`localStorage` `wssports.cupom`)

```json
{
  "versao_formato": 1,
  "nome": "João",
  "valorCentavos": 1000,
  "palpites": [
    {
      "tipo": "pre_jogo",
      "confrontoId": 9001,
      "codigoCotacao": "odd1",
      "mercado": "Casa",
      "cotacao": "2.10",
      "timeCasa": "Flamengo",
      "timeFora": "Palmeiras",
      "campeonato": "Brasileirão Série A",
      "dataInicio": "2026-10-09T21:30:00-03:00"
    }
  ]
}
```

| Campo | Tipo | Regra |
|---|---|---|
| `versao_formato` | inteiro | `1`; outro valor ou formato inválido → cupom descartado (FR-039) |
| `nome` | texto | Até 100 caracteres (regra da API) |
| `valorCentavos` | inteiro | ≥ 0; valores digitados com vírgula ou ponto viram centavos |
| `palpites` | lista | Um por jogo (`tipo` + `confrontoId`); até 50 (regra da API) |
| `palpites[].tipo` | texto | `pre_jogo` ou `ao_vivo` |
| `palpites[].codigoCotacao` | texto | `odd1`…`odd323` ou `jogador` |
| `palpites[].jogadorId` | inteiro | Só com `jogador` (detalhe do "+N") |
| `palpites[].cotacao` | texto com 2 casas | Cotação vista; vira `cotacao_vista` no envio |

**Transições (reducer `useCupom`)**:

| Ação | Efeito |
|---|---|
| `alternarPalpite(jogo, codigo, cotacao)` | Sem palpite no jogo → adiciona; mesmo código → remove; outro código → troca (FR-034) |
| `removerPalpite(tipo, confrontoId)` | Remove o palpite do jogo |
| `definirValor(centavos)` | Substitui o valor (botões rápidos e campo) |
| `definirNome(texto)` | Atualiza o nome do apostador |
| `atualizarCotacoes(alteracoes)` | Aplica as cotações atuais devolvidas no 409 (R-16) |
| `limpar()` | Cupom vazio (Limpar, sucesso do envio) |

O cupom restaurado não é alterado pela tela (FR-039): jogos começados e cotações alteradas são
resolvidos pelo backend no envio.

**Valores derivados (não guardados)**: `cotacaoTotal`, `premioCentavos`, `acrescimoCentavos`,
`totalCentavos`, `vendedorPagaCentavos` e `quantidade`, calculados por `utils/dinheiro.js`
(R-16).

### Modo (`localStorage` `wssports.modo`)

| Valor | Efeito |
|---|---|
| `escuro` | Paleta do sistema antigo |
| `claro` | Paleta clara (R-19) |
| ausente | Segue `cor_fundo` do tema |

## 3. Mapeamento para o envio (`POST /api/publico/apostas`)

| Campo enviado | Origem |
|---|---|
| `chave_idempotencia` | `crypto.randomUUID()` gerado a cada tentativa de envio |
| `nome` | `cupom.nome` |
| `valor` | `valorCentavos` em texto com 2 casas (`1000` → `"10.00"`) |
| `aceitar_alteracoes` | `"Nenhuma"` |
| `palpites[].confrontos_id` ou `confrontos_ao_vivo_id` | `confrontoId`, conforme `tipo` |
| `palpites[].codigo_cotacao` | `codigoCotacao` |
| `palpites[].confrontos_jogadores_id` | `jogadorId` (só `jogador`) |
| `palpites[].cotacao_vista` | `cotacao` |

## 4. Comprovante (modal do bilhete e de sucesso)

Formato do `ComprovanteApostaResource` (contrato da spec 004). A tela mostra `codigo`,
`situacao`, `resultado`, `nome`, `criada_em`, `valor`, `cotacao_total`, `total_a_pagar`,
`mensagem_bilhete` e os `palpites` (campeonato, times, data, mercado, cotação e situação), com
as cores de situação do R-21.
