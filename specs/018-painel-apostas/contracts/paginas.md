# Contrato das telas: Painel — Apostas

Visual do painel (specs 014 a 017) e regras da constituição: cores do tema (cor principal, fundo
claro ou escuro), `PanelTabs`, modais com `ModalHeader` (ícone sem fundo, título e descrição) e o
tamanho da constituição, campos com rótulo em cima, itens existentes escolhidos de lista,
`ActionsMenu` nas ações. Diferenças em relação ao antigo aprovadas na spec ("Referências do sistema
antigo").

## 1. Página `/painel/apostas` (`PanelBets`)

- Menu: item "Apostas" (ícone `receipt`) no `PanelShell`, logo depois de "Caixa", visível com
  `apostas.listar`; sem ela, o acesso direto volta para `/painel`.
- Título da barra: "Apostas · total N" (total do filtro).
- Cabeçalho da área: `PeriodFilter` (spec 017) à esquerda; à direita: busca (ícone `search`,
  placeholder "Código ou nome do cliente", Enter ou ao parar de digitar por 400 ms), botão
  **Filtros** (`filter_list`, com a quantidade de filtros ativos), **Premiados** (`emoji_events`,
  Gerente para cima com `apostas.exportar`), **Baixar planilha** (`table_view`, com
  `apostas.exportar`) e atualizar (`refresh`).
- Com busca por nome, a caixa "Buscar em todo o período" aparece ao lado da busca.
- Estado na URL: `aba`, `de`, `ate`, `busca`, `todo_periodo`, `resultado`, `usuarios_id`,
  `vendedor` (nome, só para a etiqueta), `forma_pagamento`, `premio_minimo`, `page` (recarregar,
  voltar e o atalho do Caixa mantêm a lista).

## 2. Abas

`PanelTabs`: **Todas** (`receipt_long`), **Pré-jogo** (`schedule`), **Ao vivo**
(`play_circle_outline`), **Canceladas** (`delete_outline`). Trocar de aba volta para a página 1 e
mantém o período e os filtros. No celular, as abas dividem a largura toda (como no Caixa).

## 3. Filtros (`BetsFilters`)

- Painel que abre abaixo do cabeçalho (no celular, modal de tela cheia com `ModalHeader`: ícone
  `filter_list`, "Filtros", "Escolha o que quer ver na lista de apostas."):
  - **Resultado**: chips Aguardando, Premiadas, Perdidas, Devolvidas, Encerradas (um ou nenhum; some
    na aba Canceladas);
  - **Vendedor** (`SellerPicker`): busca com resultados da rede (`GET /apostas/vendedores`); não
    aparece para o Vendedor;
  - **Forma de pagamento**: chips Dinheiro, Saldo, Promoção esportes;
  - **Prêmio mínimo**: campo de valor em reais do painel (`useCampoValor`).
  - Botões **Limpar** e **Aplicar** (no computador, cada mudança já aplica).
- Abaixo do cabeçalho, as etiquetas dos filtros ativos (ex.: "Vendedor: Vendedor C ✕",
  "Prêmio ≥ R$ 100,00 ✕"); tocar no ✕ remove o filtro e recarrega.

## 4. Cartões (`StatCard`)

Ordem: **Apostas** (quantidade, com "12 abertas" embaixo), **Entradas**, **Comissão**, **Saídas**
(dica "Mesma regra do Caixa: prêmio sem o valor acrescido + devoluções."), **Em aberto** (valor das aguardando) e, para Admin e
Supervisor, **Encerramentos** (dica "Encerramentos das apostas feitas no período."). Na aba
Canceladas: **Canceladas** (quantidade) e **Valor cancelado**. Celular: duas colunas.

## 5. Lista (`BetsTable`)

- Colunas no computador: CÓDIGO, VENDEDOR, CLIENTE, VALOR, COMISSÃO, PRÊMIO, SITUAÇÃO, JOGOS,
  HORÁRIO, AÇÃO. Na aba Canceladas, CANCELADA (data/hora e autor) no lugar de JOGOS.
- Código em maiúsculas; vendedor com a etiqueta "inativo"/"excluído" quando for o caso, ou "Site";
  forma de pagamento como etiqueta pequena ao lado do valor quando não for Dinheiro.
- Situação em selo colorido: Aguardando (neutro), Vencedor (verde), Perdedor (vermelho), Devolvido
  (azul), Encerrada (roxo), Cancelada (cinza), legível nos dois temas.
- Tocar na linha abre o bilhete; a coluna AÇÃO tem o `ActionsMenu` com **Ver bilhete**
  (`remove_red_eye`), **Compartilhar** (`share`) e, quando permitido, **Cancelar aposta**
  (`delete`).
- Celular: cada aposta vira um cartão com código e situação no topo, cliente, valor e prêmio em
  destaque, e "ver mais" com vendedor, comissão, jogos e horário; o menu de três pontinhos no canto.
- Estados: esqueleto ao carregar; vazio com "Nenhuma aposta encontrada. Troque o período ou os
  filtros."; erro com "Tentar novamente".
- Paginação: `Pagination` do painel (20 por página).

## 6. Bilhete (`TicketModal`, existente)

- Mesmo conteúdo do site/Dashboard (dados, palpites, andamento, cotação total, valor, prêmio, valor
  acrescido, "Vendedor paga"), mais **Comissão** e **Forma de pagamento**; na cancelada, a faixa
  "Cancelada em dd/mm/aaaa hh:mm por Fulano".
- Cabeçalho `ModalHeader` (aberto pelo painel): ícone `receipt`, título "Bilhete {CÓDIGO}",
  descrição "Confira os palpites, o andamento dos jogos e os valores da aposta." e o fechar; logo
  abaixo, uma barra com os ícones compartilhar, acompanhar em tempo real e **imprimir 2ª via**
  (`print`, só com `imprimir_segunda_via` em `painel.acoes`), porque o `ModalHeader` compartilhado
  só tem o botão de fechar. No site, o cabeçalho continua o atual.
- Palpite cancelado riscado, com o motivo ("Jogo cancelado", "Empate anula aposta" ou, sem motivo
  gravado, "Cancelado no bilhete").
- Rodapé (só com `cancelar`): botão **Cancelar aposta** (vermelho), com confirmação "Cancelar a
  aposta AB3K9XQZ? O valor volta para o limite do vendedor (ou para o saldo do cliente)."
- Por palpite (só com `editar_palpites`): ícone `delete_outline` (cancelar) ou `refresh`
  (restaurar), com confirmação; o bilhete é atualizado com o prêmio recalculado.
- Depois de qualquer ação: mensagem de sucesso, bilhete, linha e cartões atualizados, sem perder a
  página nem os filtros.

## 7. Premiados (`WinnersModal`)

- `ModalHeader`: ícone `emoji_events`, "Premiados do período", "Imagem com os maiores prêmios para
  compartilhar."
- Corpo: prévia da imagem (logo, "Premiados · 22/10/2026", tabela CÓDIGO, VENDEDOR, CLIENTE, VALOR,
  PRÊMIO, SITUAÇÃO nas cores do tema); sem premiadas, "Nenhuma aposta premiada no período.".
- Rodapé: **Compartilhar** (`share`, quando o aparelho compartilha arquivos) e **Baixar**
  (`download`, `premiados-{de}-{ate}.png`).

## 8. Planilha

- **Baixar planilha** com carregando no botão; se o resumo indicar mais de 50.000 apostas, a tela
  avisa antes ("O filtro tem N apostas; o máximo por planilha é 50.000. Reduza o período ou use
  filtros.") e não baixa.

## 9. Atalho do Caixa (`PanelCash`, alterado)

- Na lista de vendedores do Caixa, o menu de três pontinhos ganha **Ver apostas** (`receipt_long`)
  para quem tem `apostas.listar`, abrindo
  `/painel/apostas?usuarios_id={id}&vendedor={nome}&de={de}&ate={ate}&aba={tipo}`.

## 10. Componentes do sistema antigo usados como referência (Princípio VIII)

`screens/adm/bets` (lista, colunas, ícones de ação, selo de status), `screens/adm/components/bar`
(busca e prêmio), `modals/ticket` (bilhete, ícones de compartilhar, acompanhar, imprimir e
remover/restaurar palpite) e `utils/helpers.js` (`printWinner`: logo e tabela dos premiados).
