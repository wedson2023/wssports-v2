# Contrato das páginas: Painel — Especiais

Visual do painel novo (specs 014 a 023), modais com `ModalHeader` e regras de tamanho da
constituição. Celular = até 900 px. API em [api.md](api.md).

## 1. `/painel/especiais` — `PanelSpecials`

**Acesso**: `especiais.listar`; sem ela, volta para `/painel`. Item "Especiais" (ícone
`emoji_events`) no `PanelShell`, logo depois de "Confrontos". Rota web
`painel/especiais` → `PainelController@especiais`.

**Topo** (`PanelHeader`): título "Especiais", total do filtro, busca por nome, botão "Filtros",
botão "Atualizar" e "Novo especial" (aba Especiais, com `especiais.gerenciar`).

**Cartões** (`StatCard`, `GET /especiais/resumo`, só na aba Especiais): Aceitando palpites ·
Aguardando resultado · Valor apostado em aberto · Possível retorno em aberto. Com
`alcance = "rede"`, a legenda "Números da sua rede" abaixo dos cartões.

**Abas** (`PanelTabs`): Especiais · Lixeira.

**Filtros** (aba Especiais; painel com etiquetas removíveis, `ActiveFilters`): Situação —
Aguardando (padrão), Encerrado, Cancelado, Todas. Na URL: `aba`, `busca`, `situacao`, `page`.

**Colunas** (`SpecialsTable`; no celular, cartões):

| Aba | Colunas |
|---|---|
| Especiais | ID · Nome · Opções · Data limite · Situação (selo; "Aguardando resultado" em âmbar quando a data passou) · Ativo · Vencedoras · Apostas em aberto · Valor em aberto · ações |
| Lixeira | ID · Nome · Opções · Data limite · Situação · Removida em · ações |

Clicar na linha abre a gaveta. Ações (`ActionsMenu`, pelas `acoes` da API): Ver detalhes, Editar,
Encerrar, Corrigir resultado, Cancelar, Remover; na Lixeira, Ver detalhes e Restaurar.

## 2. Gaveta — `SpecialDrawer`

Cabeçalho com nome, situação e data limite; botões Editar, Encerrar, Cancelar, Corrigir resultado
(conforme `acoes`).

- **Dados**: quantidade de vencedoras, ativo, vencedoras (com "empatada"), divisão por empate,
  encerrado em/por.
- **Exposição** (`SpecialExposure`, `GET /especiais/{id}/exposicao`): tabela por opção — Opção ·
  Cotação · Situação · Simples (qtd / valor / retorno) · Múltiplas (qtd / valor / retorno) · Total
  (qtd / valor / retorno) · ação Anular (com `especiais.encerrar`, categoria Aguardando, opção não
  anulada). Linha de total; a opção de `maior_retorno_opcoes_id` destacada; "Números da sua rede"
  quando `alcance = "rede"`. No celular, um cartão por opção.
- **Correções** (só quando houver): lista com data, usuário, vencedoras de → para e apostas
  afetadas.

## 3. Modais

### 3.1 `SpecialFormModal` — Novo especial / Editar especial

`ModalHeader` (ícone `emoji_events`; "Preencha os dados e as opções do especial."). Altura fixa
(`min(680px, 90dvh)`), corpo rola.

- **Seção Dados** (grade de 2 colunas; 1 no celular): Nome (placeholder "Ex.: Campeão Brasileiro
  2026") · Data limite (data e hora) · Quantidade de vencedoras (número, padrão 1) · Ativo
  (select Sim/Não).
- **Seção Opções** (grade): por linha Nome · Cotação (`DecimalField`) · Ativo (select) · remover
  (ícone). Botão "Adicionar opção". Cadastro abre com 2 linhas vazias. Opções anuladas aparecem
  desabilitadas com o selo "Anulada".
- Aviso acima do rodapé quando a data limite está no passado: "A data limite já passou: o especial
  não vai aceitar palpites.".
- Rodapé: Cancelar · Salvar. Na edição, salva a categoria e depois cada opção alterada (R-12); no
  primeiro erro, para, mostra o erro no campo e o aviso "Os dados até aqui já foram salvos.".

### 3.2 `SpecialCloseModal` — Encerrar / Corrigir resultado

`ModalHeader` (ícone `flag`; "Marque as opções vencedoras para apurar as apostas." / "Troque as
vencedoras: as apostas serão reapuradas."). Altura do conteúdo.

- Texto "Escolha {Q} opção(ões) vencedora(s)." e a quantidade de apostas em aberto.
- Lista das opções com caixa de marcar (anuladas desabilitadas); na correção, as vencedoras atuais
  já marcadas.
- Quando marcadas > Q: select "Dividir por empate" (Não/Sim) e, com Sim, a marcação "Empatada"
  em cada vencedora, com a prévia "Cotação aplicada: X" por empatada.
- Aviso quando a data limite ainda não passou (só no encerrar).
- Correção: campo Senha e o aviso "Prêmios já pagos a mais serão estornados; o saldo do cliente
  pode ficar negativo.".
- Rodapé: Cancelar · Encerrar / Corrigir (bloqueado enquanto envia). Sucesso: mensagem com
  `apostas_afetadas`, fecha e recarrega lista, cartões e gaveta.

### 3.3 `SpecialVoidOptionModal` — Anular opção

`ModalHeader` (ícone `block`; "Os palpites nesta opção serão devolvidos."). Mostra a opção, a
cotação e a quantidade de apostas em aberto nela (da exposição). Rodapé: Cancelar · Anular.

### 3.4 Confirmações (`ConfirmModal`)

- Cancelar especial: "Todos os palpites deste especial serão devolvidos (cotação 1,00). {N}
  apostas em aberto." · Cancelar especial.
- Remover: "O especial vai para a lixeira." · Remover.
- Restaurar: "O especial volta para a lista com as opções." · Restaurar.

## 4. Comprovante (`TicketModal`)

Palpite especial com `cotacao_empate`: abaixo da cotação, "Dividida por empate: X (original Y)".

## 5. Permissões (`utils/permissions.js`)

Grupo Especiais ganha `especiais.corrigir_resultado`: "Corrigir resultado de especiais" — "Troca as
vencedoras de um especial encerrado e reapura as apostas.".
