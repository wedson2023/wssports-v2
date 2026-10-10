# Contrato: páginas (mudanças sobre a spec 005)

**Feature**: `006-ajustes-tela-principal` | **Data**: 2026-10-10 | **Plano**: [../plan.md](../plan.md)

Base: [contrato de páginas da spec 005](../../005-tela-principal/contracts/paginas.md). Só as
mudanças estão aqui. Nenhuma rota web nova.

## Página `Home` (`GET /`)

### Query

| Parâmetro | Mudança |
|---|---|
| `esporte` | aceita `ESPECIAL` (antes sem ação) |
| `campeonato` | com `esporte=ESPECIAL`, é o id da categoria especial |
| `code` | **só frontend**: lido pela tela ao abrir, depois removido da URL (FR-001 a FR-004); o servidor ignora |

### Props

- `filtros.esporte = "ESPECIAL"` → `listagem` no formato de `GET /api/publico/especiais`
  ([api.md](api.md) seção 1), com `tipo: "especial"`. `filtros.dia` é ignorado (sem abas de data).
- `banners`: mesmo formato (`[{ imagem, link }]`), agora real: banners ativos por `ordem` e `id`,
  com a URL pública da imagem; lista vazia quando não há banner ativo (o carrossel não aparece).
- `aviso` (spec 005, mensagem de atenção da listagem): sem mudança; não tem relação com o aviso da
  banca, que vem pela API pública.
- Demais props sem mudança.

## Props compartilhadas (todas as páginas)

- `tema.logo`: URL da logo da configuração (`logos/{sha1}.{ext}` no disco público) ou
  `/images/logo_padrao.png` quando nenhuma foi enviada. As demais chaves de `tema` continuam fake.

### Comportamento novo da tela

| Situação | Ação |
|---|---|
| Abriu com `?code=` | Espera a sessão; chama a busca de código (vendedor → validação; demais → bilhete); `replaceState` sem o `code`; não pede o aviso |
| Lista carregada sem `?code=` | `GET /api/publico/avisos/atual?aparelho=` (com token de cliente, se houver); havendo aviso, carrega a imagem e abre o `NoticeModal` (estado `aviso_banca`) |
| "Lido" confirmado | `POST /api/publico/avisos/{id}/leituras` e fecha |
| Vendedor → Tabela | Busca todas as páginas de `GET /api/tabela-jogos` e imprime (Bluetooth no mobile, navegador no desktop) |
| Vendedor → Imprimir (bilhete cadastrado ou validado) | Desktop: navegador; mobile PADRÃO: Bluetooth; mobile APP: `app://{host}/{codigo}/{largura}/false` |

## Página `Rules` (`GET /regras`)

### Props

```json
{
  "regras": [
    "Prazo de pagamento até 2 dias úteis.",
    "Não pagará jogos já realizados ou que já estejam rolando e, por falha, continuem no sistema, por erro de hora, cotação ou por jogo antecipado.",
    "Todos os jogos são definidos ao final dos 90 minutos de jogo, incluindo acréscimos definidos pelos árbitros. Não valerá prorrogação nem disputa de pênaltis."
  ],
  "regras_bonus": [
    {
      "id": 1,
      "nome": "Bônus de primeiro depósito",
      "categoria": "Primeiro depósito",
      "modalidade": "Esportes",
      "tipo_ganho": "Percentual",
      "valor": "100.00",
      "rollover": 10,
      "valor_maximo_deposito": "100.00",
      "valor_maximo_conversao": "500.00",
      "valor_minimo_aposta": "1.00",
      "valor_maximo_aposta": "100.00",
      "odd_minima_aposta_simples": "1.50",
      "odd_minima_aposta_multipla": "1.30",
      "data_inicio": "2026-10-10T00:00:00-03:00",
      "data_fim": null
    }
  ],
  "limites_aposta": {
    "valor_minimo_aposta": "2.00",
    "valor_maximo_aposta": "1000.00",
    "premio_maximo": "5000.00",
    "multiplicador": 1000,
    "quantidade_minima_opcoes": 1,
    "quantidade_maxima_opcoes": 20,
    "periodo_jogos": "Depois de amanhã"
  }
}
```

- `regras`: texto da banca (`configuracoes.regras`), quebrado por linha, sem linhas vazias; lista
  vazia → o bloco não aparece. Cada item é mostrado como texto (sem HTML).
- `regras_bonus`: promoções ativas e vigentes (`ClientesPromocoes::vigentes()`), por categoria e
  nome; lista vazia → o bloco não aparece.
- `limites_aposta`: configuração de quem vê (visitante, cliente ou vendedor; gestor = visitante),
  com o mesmo `Authorization` da `Home`. Com sessão de cliente ou vendedor, a tela recarrega
  `limites_aposta` depois da primeira carga (`useSessao`).

### Tela

Na ordem (FR-021a): barra com "Voltar"; logo (`tema.logo`); bloco da banca (`RulesBlock` sem
título, um parágrafo por item de `regras`); "Regras de bônus" (`BonusRules`); "Regras de apostas"
(`MarketRules`, textos fixos de `utils/market_rules.js`); "Limites de aposta" (`BetLimits`); atalho
do WhatsApp. Todos os itens usam o `RuleItem` (cabeçalho com ícone e bordas na cor do tema).
