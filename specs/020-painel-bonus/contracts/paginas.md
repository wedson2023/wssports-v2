# Contrato das páginas: Painel — Bônus

Página Inertia `PanelBonus` em `/painel/bonus`, dentro do `PanelShell` (título "Bônus"). Visual do
painel (specs 014 a 019): cores do tema, `PanelTabs`, `StatCard`, `ActionsMenu` (três pontinhos),
`ActiveFilters`, `Pagination`, modais com `ModalHeader`, campos com rótulo em cima, `useCampoValor`
nos valores em reais. Celular até 900 px: linhas viram cartões, filtros, detalhe e modais em tela
cheia, sem rolagem horizontal, alvos de toque ≥ 40 px.

## 1. Cabeçalho e abas

- Menu do painel: item **Bônus** (ícone `redeem`), depois de Clientes, visível com
  `clientes_promocoes.gerenciar`.
- Barra superior: título "Bônus — total N" (total da aba e do filtro), busca (atraso de 400 ms), botão
  **Filtros** (`filter_alt`, com contador), **Cadastrar** (`add`, nas duas abas, como no antigo) e
  **Atualizar**.
- Abas (`PanelTabs`): **Promoções** (`card_giftcard`) e **Bônus recebidos** (`redeem`).
- Computador: abas do tamanho do texto, cada rótulo numa linha só, e busca com largura própria
  (300 px); os chips de situação ficam embaixo e não alargam a coluna das abas. Celular: as abas
  dividem a largura toda e a busca ocupa a linha (ajuste pedido pelo responsável em 2026-10-25).
- Estado no endereço: `aba`, `busca`, filtros, `situacao`, `page` (FR-009, FR-023).

## 2. Promoções (`PromotionsTable`)

- Colunas: ID · NOME (com categoria embaixo) · MODALIDADE · GANHO (R$ ou %, com "até R$ X" no
  percentual) · ROLLOVER (Nx · N dias) · AFILIADO (nome/código ou "Geral") · PERÍODO (início — fim ou
  "Sem fim") · SITUAÇÃO (etiqueta com cor: Vigente verde, Agendada azul, Encerrada cinza, Desativada
  cinza-escuro, Estornando laranja com "N de M", Estornada vermelha) · USO (clientes · creditado ·
  convertido) · AÇÕES.
- Menu (conforme `pode`): Ver detalhe (`visibility`), Editar (`edit`), Desativar/Ativar
  (`toggle_off`/`toggle_on`, com `ConfirmModal`), Ver bônus recebidos (`redeem`), Estornar promoção
  (`undo`, vermelho), Excluir (`delete_outline`, com `ConfirmModal`).
- Celular: cartão com nome, ganho e situação; o resto ao expandir.
- Vazio: "Nenhuma promoção encontrada. Troque a busca ou os filtros." / sem nenhuma: "Nenhuma promoção
  cadastrada." com o botão Cadastrar.

## 3. Filtros das promoções (`PromotionsFilters`)

Painel de filtros (tela cheia no celular): Situação e Modalidade (seleção, com "Todas") na mesma linha
de Limpar filtros e Aplicar; embaixo, cada um na sua linha, Categoria (chips: Todas e as quatro
categorias) e Afiliado (chips: Todos, Geral (sem afiliado) e De um afiliado; neste, a busca do afiliado,
`AffiliatePicker`, ao lado dos chips). Aplicar recarrega a lista; etiquetas removíveis em
`ActiveFilters`. Ajuste pedido pelo responsável em 2026-10-25.

## 4. Cadastrar / editar (`PromotionFormModal`)

`ModalHeader` com ícone `card_giftcard`, título "Cadastrar promoção" / "Editar promoção" e descrição
"Defina quem ganha o bônus, quanto e as regras para usar.". Altura fixa (`min(680px, 90dvh)`), corpo
rolável, rodapé com Cancelar e Salvar (carregando, sem clique duplo).

Campos em grade de duas colunas (uma no celular); as escolhas de lista fixa são campos de seleção,
com o mesmo visual dos outros campos (ajuste pedido pelo responsável em 2026-10-25, no lugar dos
interruptores e dos chips):

| Grupo | Campos |
|---|---|
| Identificação | Nome (linha toda) · Descrição (linha toda) · Ativa (seleção Sim/Não) · Afiliado (`AffiliatePicker`, obrigatório para Supervisor/Gerente; Admin pode limpar = "Geral") |
| Ganho | Categoria (seleção: Primeiro cadastro, Primeiro depósito, Qualquer depósito, Indicação) · Modalidade (seleção) · Tipo de ganho (seleção Fixo/Percentual; desabilitada em Fixo fora das categorias de depósito) · Valor (R$ ou %) · Depósito máximo (só Percentual) · Quantidade de indicações (só Indicação) |
| Período | Início · Fim (vazio = sem fim) |
| Rollover e prazo | Rollover (vezes) · Dias para cumprir · Rollover no primeiro depósito (seleção Sim/Não, só Primeiro depósito) |
| Regras de aposta | Valor mínimo e máximo de aposta · Odd mínima simples e múltipla · Valor máximo de conversão |

Promoção já aplicada: Valor, Tipo de ganho, Categoria e Modalidade travados com o aviso "Esta promoção
já foi dada a clientes; esses campos não podem mudar.". Erros do servidor aparecem no campo.

## 5. Detalhe da promoção (`PromotionDrawer`)

Gaveta lateral (tela cheia no celular) com os campos agrupados como no modal, os cartões de uso
(Clientes, Creditado, Cumpridos, Convertido, Retirado — `StatCard`), datas de cadastro e alteração e,
quando houver, o bloco **Estorno** (situação, barra de progresso "N de M clientes", motivo, autor,
início, conclusão, valor retirado). Ações no rodapé conforme `pode` (as mesmas do menu).

## 6. Estornar promoção (`PromotionReversalModal`)

`ModalHeader` (`undo`, "Estornar promoção", "O bônus será retirado de todos que o receberam e a
promoção será desativada."). Mostra clientes que receberam e valor creditado; aviso: "Sai do saldo de
bônus de cada cliente o que ainda não foi convertido. Bônus convertidos, expirados ou excluídos não são
mexidos. As apostas não são alteradas."; Motivo (obrigatório, contador 255) e Senha. Botão "Estornar"
vermelho. Depois de confirmar, a linha mostra "Estornando N de M" e atualiza a cada 3 s até
"Estornada".

## 7. Bônus recebidos (`ReceivedBonusTable`, `ReceivedBonusFilters`)

- Cartões (`StatCard`): Em andamento (quantidade · falta apostar), Creditado, Convertido e Retirado
  (do período escolhido, ou "Desde sempre").
- Situação em chips acima da lista: Em andamento (padrão) · Cumpridos · Convertidos · Expirados ·
  Excluídos pelo cliente · Estornados · Todos. No celular, numa linha só, com rolagem para o lado.
- Filtros (painel de filtros), numa linha com Limpar filtros e Aplicar: Promoção (`PromotionPicker`, mais larga), Modalidade
  (seleção: Todas, Esportes, Cassino) e Recebidos no período (seleção: Desde sempre — o padrão —,
  Hoje, Ontem, Esta semana, Este mês e Personalizado); no Personalizado, as datas De/Até embaixo (até
  1 ano). Ajuste pedido pelo responsável em 2026-10-25.
- Colunas: CLIENTE (ID · nome) · PROMOÇÃO · MODALIDADE · VALOR · SALDO BÔNUS (atual) · ROLLOVER
  (apostado / exigido, barra de progresso, "falta R$ X") · RECEBIDO EM · EXPIRA EM · SITUAÇÃO
  (etiqueta: Em andamento azul, Cumprido verde, Convertido verde-escuro, Expirado cinza, Excluído
  cinza-escuro, Estornado vermelho) · AÇÕES.
- Menu: Ver detalhe (`visibility`), Ver cliente (`person`, com `clientes.listar`), Estornar bônus
  (`undo`, vermelho, com `pode.estornar`).
- Celular: cartão com cliente, valor e "falta R$ X" (ou a situação), o resto ao expandir.
- Vazio: "Nenhum bônus nesta situação. Troque a situação, a busca ou os filtros.".

## 8. Detalhe do bônus (`BonusDrawer`)

Gaveta com os dados da linha, as regras gravadas (valor mínimo/máximo, odds mínimas), o histórico em
linha do tempo (Recebido, Cumprido, Convertido, Expirado, Excluído pelo cliente, Estornado — data,
valor, autor e motivo) e os botões "Ver cliente" (abre o `ClientDrawer` da spec 019 sem ações) e
"Estornar bônus".

## 9. Estornar bônus (`BonusReversalModal`)

`ModalHeader` (`undo`, "Estornar bônus", "O bônus será retirado só deste cliente."). Mostra cliente,
promoção, valor do bônus e "Será retirado: R$ X" (menor entre o saldo de bônus atual e o valor);
Motivo e Senha. Depois: aviso de sucesso e lista, cartões e detalhe atualizados (FR-034).

## 10. Referências visuais do antigo (Princípio VIII)

`resources/js/screens/adm/bonus` (lista, abas Todos/Ativos, títulos das colunas, busca, botão
Cadastrar) e `resources/js/screens/adm/modals/bonus` (campos e textos de ajuda do cadastro). Diferenças
aprovadas na spec: visual do painel novo, abas renomeadas (Promoções e Bônus recebidos), ações em menu
de três pontinhos no lugar dos ícones soltos, campos agrupados e escondidos quando não se aplicam.
