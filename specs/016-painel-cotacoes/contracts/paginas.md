# Contrato das telas: Painel — Cotações

Visual do painel (specs 014 e 015) e regras da constituição v3.4.0 a v3.7.0: cores do tema (cor
principal, fundo claro ou escuro), `PanelTabs`, modais com `ModalHeader` (ícone sem fundo, título e
descrição) e tamanho da constituição, campos com rótulo em cima e o espaçamento das specs 014/015,
bordas mais fortes no tema claro, itens existentes escolhidos de lista, `ActionsMenu` no celular.
Diferenças em relação ao antigo aprovadas na spec (Referências do sistema antigo).

## 1. Página `/painel/cotacoes` (`PanelQuotes`)

- Menu: item "Cotações" (ícone `price_change`) no `PanelShell`.
- Título da barra: "Cotações". Abas (`PanelTabs`, só as permitidas, `?aba=` na URL):
  **Público** (`groups`), **Campeonatos** (`emoji_events`), **Jogos** (`sports_soccer`),
  **Tetos** (`vertical_align_top`), **Regras ativas** (`fact_check`).
- Celular: abas roláveis na horizontal (como hoje no `PanelTabs`), sem rolagem horizontal da página.

## 2. Editor de cotações (comum)

Layout no computador: duas colunas, grade à esquerda (flexível) e prévia à direita (320 px, fixa ao
rolar). No celular: grade em uma coluna e prévia numa faixa fixa embaixo (uma linha com
"Casa 2,00 → 1,80", toca para expandir as parcelas).

**`QuoteGrid`**:
- Barra: busca ("Buscar cotação ou mercado"), contador "N cotações com ajuste", "Aplicar a todas"
  (campo + botão, pede confirmação com a quantidade), abrir/fechar todos os grupos.
- Grupo de mercado (abre e fecha, como os grupos do painel de permissões): título, contagem
  "3 de 10 ajustadas" com a cor da contagem (verde todas, amarelo algumas, cinza nenhuma) e
  "aplicar a todas do grupo".
- Linha da cotação: nome (ex.: "Casa", "Ambas marcam"), campo com sufixo (`%`, ou cotação no jogo e
  no teto), cadeado (se a camada usa cadeado), valor original pequeno quando há pendente
  ("era −5%"). Positivo em verde com "+", negativo em vermelho com "−", vazio = sem ajuste.
- Campo alterado: borda e fundo na cor principal suave; erro: borda vermelha e mensagem embaixo.
- Trancada por um superior: campo só leitura, cadeado fechado, texto "trancada por {nome}".
  Admin/Supervisor: cadeado clicável (abre/fecha na hora, com toast); cadeado de outro superior
  aparece fechado e desabilitado com o nome.
- Jogo: ao lado de cada campo, "Provedor 2,00" e a diferença que será guardada ("−0,05"); opção
  indisponível no provedor desabilitada com "indisponível".
- Linha inteira com altura mínima de 44 px no celular.

**`QuotePreview`** (cartão com `ModalHeader`-like título "Prévia"):
- Jogo de exemplo (times, campeonato, hora) com botão "Trocar jogo" (lista dos próximos jogos).
- Para a cotação em foco (a do campo focado; padrão "Casa"): uma linha por parcela —
  "Provedor 2,00", "Público −10%", "Superiores −4%", "Campeonato +3%", "Jogo +0,10", "Teto 50,00" —
  e o resultado em destaque; avisos "limitada ao teto" / "limitada ao mínimo 1,00" / "indisponível".
- Parcelas pendentes aparecem com o selo "não salvo".

**`SaveBar`** (rodapé fixo do editor): "N alterações não salvas", **Desfazer** e **Salvar** (cor
principal, carregando enquanto grava). Some quando não há pendentes. Sair da aba, trocar de item ou
fechar o modal com pendentes → confirmação "Você tem N alterações não salvas. Sair mesmo assim?".

## 3. Aba Público

- Topo: seletor de público (`AudiencePicker`): "Clientes do site", "Cliente específico" (busca com
  resultados) e os usuários da rede agrupados por função (Supervisores, Gerentes, Vendedores), com
  busca; só as opções permitidas. Ao lado, alternador **Pré-jogo / Ao vivo** (segmentado).
- Usuário escolhido: linha de informação "Superiores somam: Casa −4%, ..." (resumo, com "ver tudo"
  que mostra a coluna "Superiores" na grade). Cliente específico: "Regra geral dos clientes soma".
- Editor (seção 2) com cadeado para Admin e Supervisor quando o público é um usuário.
- Vazio inicial: ilustração com o texto "Escolha um público para ajustar as cotações".

## 4. Aba Campeonatos

- Barra: busca por nome, filtro de país (lista com bandeira), chave "Só com ajuste".
- Lista (tabela no computador; no celular só nome com país embaixo, selo e três pontinhos):
  bandeira + nome, país, jogos futuros, selo (`RuleBadge`: "sem ajuste" cinza; "+3% · Clientes";
  "2 regras · −5% a +3%"), ações **Editar** e **Histórico**. `Pagination`.
- Editar abre `QuoteEditorModal` (altura fixa desde a abertura): `ModalHeader` ícone
  `emoji_events`, título "Cotações · {campeonato}", descrição "Ajuste as porcentagens deste campeonato
  para o público escolhido. Vale para pré-jogo e ao vivo." Dentro: seletor de alvo (`TargetPicker`:
  Todos, Clientes, ou "Vendedores de" + usuário da rede), editor completo e `SaveBar` no rodapé.

## 5. Aba Jogos

- Barra: busca por time, filtro de campeonato (lista com busca), dia (Hoje/Amanhã/Depois de amanhã),
  "Só com ajuste".
- Lista: hora, confronto, campeonato com bandeira, selo, ações. Celular: confronto com hora embaixo,
  selo e três pontinhos.
- Editar: `QuoteEditorModal` (ícone `sports_soccer`, "Cotações · Time A x Time B", descrição "Informe
  a cotação desejada para o público escolhido. Vale só para o pré-jogo.") com `TargetPicker` e editor
  em modo cotação (campo = cotação final do jogo antes das porcentagens do público).

## 6. Aba Tetos

- Aviso no topo: "O teto vale para todos os públicos: nenhuma cotação passa deste valor."
- Editor em modo teto (campo = cotação máxima, mínimo 1,00), sem cadeado.

## 7. Aba Regras ativas

- Filtro por camada (Público, Clientes, Campeonatos, Jogos, Tetos). Tabela: camada (ícone), a quem se
  aplica, alvo, tipo, "N cotações", faixa ("−10% a +3%"), atualizado em, ações **Editar** e
  **Histórico**. Usuário inativo: selo "inativo". Celular: a quem se aplica + camada embaixo, faixa e
  três pontinhos.
- **Histórico** abre `QuoteHistoryModal` (altura fixa, ícone `history`, "Histórico · {regra}",
  descrição "Alterações desta regra, da mais nova para a mais antiga."): itens com autor, data,
  ação ("alterou 12 cotações", "trancou Casa") e, ao abrir, a lista "Casa: −5% → −10%". Paginado.

## 8. Estados (todas as áreas)

Carregando com esqueleto no formato (lista, grade, prévia); vazio com orientação ("Nenhum campeonato
encontrado. Limpe os filtros."); erro com "Tentar novamente"; nenhuma área trava as outras.

## Componentes

| Nome | Pasta | Papel |
|---|---|---|
| `PanelQuotes` | `pages/PanelQuotes` | página e abas |
| `QuoteGrid` | `components/QuoteGrid` | grade por mercado, busca, aplicar a todas, cadeados |
| `QuotePreview` | `components/QuotePreview` | prévia do cálculo |
| `SaveBar` | `components/SaveBar` | contador, Desfazer, Salvar |
| `AudiencePicker` | `components/AudiencePicker` | escolha do público (aba Público) |
| `TargetPicker` | `components/TargetPicker` | escolha do alvo (campeonato e jogo) |
| `QuoteEditorModal` | `components/QuoteEditorModal` | editor de um campeonato ou jogo |
| `RuleBadge` | `components/RuleBadge` | selo das regras |
| `QuoteListTable` | `components/QuoteListTable` | lista de campeonatos/jogos/regras com selo e ações |
| `QuoteHistoryModal` | `components/QuoteHistoryModal` | histórico da regra |
| `useGradeCotacoes` | `hooks/useGradeCotacoes.js` | pendentes, erros, desfazer, aviso ao sair |
| `useNomesCotacoes` | `hooks/useNomesCotacoes.js` | nomes das cotações (cache da sessão) |
| `useListaCotacoes` | `hooks/useListaCotacoes.js` | listas paginadas com filtros |
| — | `utils/quotes.js` | grupos de mercado, cálculo da prévia, formatação de ajuste |

Reaproveitados: `PanelShell` (alterado: item do menu), `PanelTabs`, `Pagination`, `ActionsMenu`,
`ModalHeader`, `Spinner`, `Backdrop`, `utils/market_groups.js`, `utils/alerts.js`.
