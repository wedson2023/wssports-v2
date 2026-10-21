# Contrato das telas: Painel — Caixa

Visual do painel (specs 014, 015 e 016) e regras da constituição: cores do tema (cor principal,
fundo claro ou escuro), `PanelTabs`, modais com `ModalHeader` (ícone sem fundo, título e descrição)
e o tamanho da constituição, campos com rótulo em cima e o espaçamento das specs anteriores, bordas
mais fortes no tema claro, itens existentes escolhidos de lista, `ActionsMenu` nas ações. Diferenças
em relação ao antigo aprovadas na spec ("Referências do sistema antigo").

## 1. Página `/painel/caixa` (`PanelCash`)

- Menu: item "Caixa" (ícone `point_of_sale`) no `PanelShell`, visível com `caixa.ver` ou
  `caixa.ver_clientes`; sem nenhuma das duas, o acesso direto volta para `/painel`.
- Título da barra: "Caixa". Abas principais (`PanelTabs`, só as permitidas, `?aba=` na URL):
  **Rede** (`account_tree`) e **Clientes** (`groups`, só Admin e Supervisor). O Vendedor vê só a
  Rede, sem a aba.
- Cabeçalho da área: `PeriodFilter` à esquerda; à direita, botão **Baixar planilha** (`table_view`,
  com `caixa.exportar`) do nível atual, botão de atualizar e, quando o foco é um Gerente e o usuário
  tem `caixa.prestar_contas` (inclusive o próprio Gerente), **Prestar contas de todos** (`get_app`).
- Estado na URL: `aba`, `tipo`, `de`, `ate`, `usuarios_id` (voltar do navegador refaz o filtro).

## 2. `PeriodFilter`

- Botões de atalho: **Hoje** (padrão), **Ontem**, **Esta semana**, **Este mês**, **Personalizado**.
  O ativo fica na cor principal.
- Personalizado abre dois campos de data com rótulo em cima ("De", "Até") e **Aplicar**; erro no
  campo quando "De" > "Até", período > 1 ano ou data futura.
- Abaixo, o texto do período em vigor: "21/10/2026" ou "14/10/2026 a 21/10/2026".
- Atalhos em balões do mesmo tamanho, numa linha só (no celular, rolável na horizontal);
  Personalizado abre os campos embaixo.
- Celular: as abas Rede/Clientes e Todas/Pré-jogo/Ao vivo dividem a largura toda (ajuste aprovado
  pelo responsável em 2026-10-21).

## 3. Aba Rede

### 3.1 Trilha (`CashBreadcrumb`)
- "Banca › Supervisor A › Gerente B", cada parte clicável, a atual em negrito. Some quando o usuário
  está no próprio nível sem ter descido (e para o Vendedor).
- Celular: "‹ Voltar" + nome do nível atual.

### 3.2 Tipo de aposta
- `PanelTabs` secundárias: **Todas** (`receipt_long`), **Pré-jogo** (`schedule`), **Ao vivo**
  (`play_circle_outline`). Fora de Todas, um aviso discreto: "Lançamentos, retiradas e saldo de caixa
  aparecem só em Todas."

### 3.3 Cartões (`StatCard`)
Ordem: Apostas (quantidade, com "3 abertas · 2 premiadas · 7 perdidas" embaixo), Entradas, Em aberto,
Premiadas, Perdidas, Saídas, Comissão vendedor, Comissão gerente (dica "calculada sobre o período
inteiro; a soma de dias separados pode ser diferente"; com o percentual quando o foco é um gerente),
Lançamentos, Retiradas, **Líquido** (destaque, verde/vermelho/cinza) e **Saldo de caixa** (destaque,
só em Todas). No foco Vendedor, os limites (geral, duplo, simples) com cadeado, como na Dashboard.
Grid: 4 colunas no desktop, 2 no celular; esqueleto enquanto carrega.

### 3.4 Lista (`CashTable`)
- Barra: busca ("Buscar por nome ou login"), ordenação (Nome, Entradas, Líquido, Saldo de caixa;
  asc/desc).
- Colunas do vendedor: Nome (login embaixo, selo "inativo"/"excluído"), Apostas, Entradas,
  Lançamentos, Retiradas, Limites ("4.800 / 5.000 / 4.900" com dica "geral / duplo / simples"),
  Comissão, Saídas, **Líquido**, **Saldo de caixa**, ações.
- Colunas de gerente e supervisor: Nome, Apostas, Entradas, Comissão vendedor, Comissão gerente,
  Saídas, Lançamentos, Retiradas, Recebido, **Líquido**, **Saldo de caixa**, ações. Em aberto,
  Premiadas e Perdidas (valores e quantidades) e o % do gerente ficam só nos detalhes da linha (pedido
  do responsável em 2026-10-21). Clicar no nome desce para o nível abaixo (cursor e seta
  `chevron_right`).
- Cada linha tem o botão de expandir (`expand_more`), que mostra todas as informações em pares
  rótulo/valor; tocar na linha (fora dos botões) também expande.
- Cores: líquido e saldo positivos em verde, negativos em vermelho, zero em cinza (nos dois temas).
- Ações (`ActionsMenu`, só as de `acoes` da linha):
  - Vendedor: Lançamento (`redo`), Prestar contas (`get_app`), Transações (`compare_arrows`),
    Ajustar limite (`attach_money`), Gráfico (`insert_chart_outlined`), Baixar planilha
    (`table_view`).
  - Gerente: Prestar contas de todos (`get_app`), Comissão do gerente (`pie_chart`), Baixar planilha.
- Celular (até 900 px), como a lista de usuários: tabela só com as colunas principais (nome com o
  login embaixo e um valor: Saldo de caixa em Todas, Líquido nas outras abas), o menu de três
  pontinhos e o botão de expandir; tocar na linha também expande as demais colunas; tocar no nome (gestor) desce de nível.
- Vazio: "Nenhum movimento neste período. Experimente outro período." Erro: mensagem e "Tentar
  novamente". Paginação (`Pagination`) embaixo.

### 3.5 Vendedor (próprio caixa)
Cartões do próprio caixa, limites, Saldo de caixa, e dois botões: **Transações** e **Gráfico**
(abrem os mesmos modais, só leitura). Sem lista, trilha, lançar ou prestar contas.

## 4. Modais

Todos com `ModalHeader`; no celular, 100% da tela; botão principal na cor do tema, com carregando
enquanto grava; erros do servidor no campo ou num aviso no topo do corpo.

- **`CashEntryModal`** ("Lançamento", `redo`, descrição "Suprimento entregue ao vendedor ou retirada
  do caixa dele."): tipo (dois botões: Suprimento / Retirada, com a explicação de cada um), Valor
  (campo de reais do painel), Data e hora (padrão agora), Observação (até 500, contador). **Lançar**.
  Para vendedor inativo ou excluído, só Retirada, com o aviso "Vendedor inativo: só é possível
  recolher o que ele deve." (FR-014a).
- **`SettlementModal`** ("Prestar contas", `get_app`):
  - Vendedor: "Até" (data e hora, padrão agora); quadro do resumo (saldo anterior, entradas,
    comissão, saídas, suprimentos, retiradas, ajustes, **valor apurado** em destaque com cor; "a
    banca deve ao vendedor" quando negativo); "Apostas em aberto: 2 (R$ 20,00) — o resultado delas
    entra na próxima prestação"; Valor recebido (preenchido com o apurado); quando diferente, aviso
    "Fica para a próxima prestação: R$ X"; Observação; **Continuar** → passo de senha ("Confirme
    com a sua senha") → **Confirmar prestação**.
  - Gerente (lote): "Até"; lista dos vendedores com o valor apurado de cada um e o total; "N sem
    movimento serão pulados"; Observação; senha; **Prestar contas de todos**.
- **`UndoSettlementModal`** ("Desfazer prestação", `undo`): resumo da prestação (período, apurado,
  recebido), Motivo (obrigatório, até 500), senha, **Desfazer** (cor de perigo).
- **`CashTransactionsModal`** ("Transações", `compare_arrows`, altura fixa desde a abertura): filtro
  por tipo (Todos, Suprimentos, Retiradas, Prestações) e período opcional; lista com tipo (ícone e
  cor), valor, data/hora, observação e quem registrou; excluídos e desfeitos riscados com "excluído
  por {nome}" / "desfeita por {nome}: {motivo}"; ações por item: Excluir (lançamento aberto) e
  Desfazer (última prestação), conforme `pode_excluir`/`pode_desfazer`; paginação.
- **`SellerLimitsModal`** ("Ajustar limite", `attach_money`): limites atuais (geral, duplo, simples)
  e um campo "somar ou subtrair" para cada (negativo reduz); prévia do novo valor, com erro no campo
  quando ficaria abaixo de zero; **Salvar**.
- **`ManagerCommissionModal`** ("Comissão do gerente", `pie_chart`): Percentual (0 a 100, sufixo %),
  explicação "sobre o líquido dos vendedores dele no período; zero quando não houver lucro";
  **Salvar**.
- **`BetsByPicksModal`** ("Apostas por quantidade de palpites", `insert_chart_outlined`): gráfico de
  barras (`ChartCanvas`, cores de `utils/charts.js`) com 1 a 10 e "Mais de 10"; vazio "Nenhuma
  aposta no período".

## 5. Aba Clientes

- `PeriodFilter` igual (sem as abas de tipo).
- Blocos de cartões com título: **Esportes** (Entradas, Em aberto, Premiadas, Perdidas, Saídas,
  Encerramentos, Saldo líquido), **Cassino** (Entradas, Saídas, Resultado, Comissão dos provedores),
  **Bônus** (Concedidos — dica "não é dinheiro de verdade" —, Convertidos), **Movimento** (Depósitos,
  Saques) e **Resultado do site** (destaque, verde/vermelho/cinza, dica com a fórmula).
- Lista (`CashTable`): Cliente (nome, com o e-mail ou o telefone embaixo), Apostas, Entradas, Saídas, Encerramentos, Resultado
  cassino, Bônus convertidos, Depósitos, Saques, **Líquido**; busca e ordenação (Nome, Entradas,
  Resultado); sem ações por linha; no celular, só Nome e Líquido, com o botão de expandir.
- Botão **Baixar planilha** do escopo Clientes.
