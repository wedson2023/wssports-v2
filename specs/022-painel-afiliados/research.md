# Research: Painel — Afiliados

**Feature**: `022-painel-afiliados` | **Date**: 2026-10-27

## Sistema antigo consultado (Princípio VII)

Repositório `wssports.bet`, `main` `408cf730` (somente leitura):

- `app/Http/Controllers/FiliadosController.php`: `index` (abas `ativos` e `lixeira`, período
  `de`/`ate`, `pesquisar`, 250 por página; `entradas` calculadas em SQL conforme o tipo de ganho
  desde `prestado_contas_em`; `cadastros`, `depositos` e `saques`), `store`, `update`, `destroy`
  (com a senha; na lixeira, `forcar-remocao` apagava de vez) e a rota `prestar-contas-filiado`
  (data e hora livres + senha).
- `resources/js/screens/adm/affiliated/index.js`: "Lista de filiados - total N", botão "Novo
  Filiado", abas `ativos`/`lixeira`, colunas ID, NOME, GANHO, CADASTROS, DEPÓSITOS, SAQUES,
  COMISSÃO (%), ENTRADAS, APURADO (`comissao / 100 * entradas`), BAIXA (data da última
  prestação), menu com copiar link (`?user=LOGIN`), editar, acessar transações (abria
  `/afiliados` com o id do afiliado), prestar contas, excluir, restaurar e deletar para sempre.
- `resources/js/screens/adm/modals/affiliated/index.js`: nome, login, senha, e-mail, CPF, ativo
  (seleção), cidade, estado, endereço, telefone, tipo de PIX, PIX, tipo de ganho, comissão; uma
  coluna só.

Não existia no antigo: aprovação de cadastro público pela tela, seguidores, rede social, link do
perfil, "sobre você", histórico de regras, histórico de prestações, cartões de resumo.

## Código atual do v2 consultado

- Backend da gestão (spec 012 + 019): `AfiliadosController`, `AfiliadosComissoesController`,
  `AfiliadosExtratoController`, `AfiliadosPrestacoesController`, `ConfiguracoesAfiliadosController`,
  `CadastroAfiliados`, `RegraComissaoAfiliados`, `ListagemAfiliados`, `PrestacaoContasAfiliados`,
  `ComissoesAfiliados`, `GarantirAlcanceAfiliado`, `AlcanceHierarquia`, requests
  `DadosAfiliado`, `StoreAfiliadosRequest`, `UpdateAfiliadosRequest`, `AprovarAfiliadoRequest`,
  `PrestacaoContasAfiliadoRequest`, `CadastroPublicoAfiliadoRequest`,
  `UpdateMeusDadosAfiliadoRequest`, `AfiliadosResource`, `MeusDadosAfiliadoResource`.
- Frontend: `PanelBonus` + `useBonusPainel` (padrão de página do painel), `PanelShell` (menu),
  `PanelTabs`, `StatCard`, `ActiveFilters`, `PeriodFilter`, `ActionsMenu`, `Pagination`,
  `ModalHeader`, `ConfirmModal`, `PromotionDrawer`/`ClientDrawer` (gavetas), `RestoreConflictModal`
  (só de cliente), `AffiliateSignupModal`, `AffiliateProfileModal`, `utils/settings_fields.js`
  (seção "Afiliados" das Configurações).

## Decisões

### R-01 — Página e rota

- **Decision**: página Inertia `PanelAffiliates` em `/painel/afiliados` (`PainelController@afiliados`),
  item "Afiliados" no `PanelShell` depois de "Bônus", visível com `afiliados.listar`; hook
  `useAfiliadosPainel` no padrão de `useBonusPainel` (filtros na URL, lista, resumo, recarregar).
- **Rationale**: mesmo padrão das specs 014 a 021 (Princípio VI).
- **Alternatives considered**: aba dentro de "Clientes" — rejeitada, a spec pede item próprio.

### R-02 — Abas e grupos de situação na listagem

- **Decision**: `GET /afiliados` ganha o filtro `grupo` (`aprovados` = Ativo + Inativo; `analise` =
  Em análise + Recusado). Com `grupo=analise`, a ordem é `created_at` crescente (mais antigo
  primeiro); nos demais, nome. O filtro `situacao` continua e combina com o grupo. Aba Lixeira usa
  `GET /afiliados/excluidos` (já existe).
- **Rationale**: a aba Ativos mostra Ativo e Inativo juntos (escolha Todos), coisa que o filtro de
  uma situação só não cobre; mudança pequena e compatível.
- **Alternatives considered**: `situacao[]` em lista — mais genérico, mas abre combinações que
  nenhuma tela usa.

### R-03 — Período padrão da listagem

- **Decision**: na listagem da gestão (`consulta_gestao`), sem `de`/`ate` o período passa a ser do
  dia 1 do mês até hoje (fuso de Brasília). `ListagemAfiliados::periodo()` ganha um parâmetro do
  padrão (`hoje` ou `mes`); o extrato e a área do afiliado continuam com o padrão atual.
- **Rationale**: clarificação de 2026-10-27; o frontend também manda as datas, mas a API fica
  coerente com a tela.

### R-04 — Coluna "Entradas" e resumo (cartões)

- **Decision**: `consulta_gestao` soma também `base` dos lançamentos em aberto
  (`withSum(... emAberto(), 'base') as entradas`); o resource devolve `entradas`. Rota nova
  `GET /afiliados/resumo` com os mesmos filtros da lista (rede, busca, gerente, sem gerente,
  período) devolvendo `total_afiliados` (Ativo + Inativo), `em_analise` (só para o Admin; `null`
  para os demais), `comissao_periodo` e `a_receber`, em consultas agregadas (sem carregar linhas).
- **Rationale**: "Entradas" é a base do antigo (a soma sobre a qual a comissão incide); os cartões
  precisam do total do filtro, não só da página.
- **Alternatives considered**: somar no frontend a página atual — errado com mais de uma página.

### R-05 — Regra de ganho obrigatória e fim da regra padrão

- **Decision**:
  - `StoreAfiliadosRequest`: `tipo_ganho` e `percentual` passam a `required` para todos com
    `afiliados.cadastrar`; `RegraComissaoAfiliados::definir_inicial` passa a gravar a regra
    informada com o autor, sem olhar `afiliados.editar_comissao` nem a configuração.
  - Cadastro público: não cria regra (`definir_inicial` não é chamado sem regra).
  - `AprovarAfiliadoRequest`: `usuarios_id`, `tipo_ganho` e `percentual` sempre `required`;
    `CadastroAfiliados::aprovar` troca a regra (encerra a antiga, se houver) ou cria a primeira.
  - Sai a configuração: migration que remove `afiliados_tipo_ganho_padrao` e
    `afiliados_percentual_padrao` de `configuracoes`; removidos `ConfiguracoesAfiliadosController`,
    `ConfiguracoesAfiliadosRequest`, `ConfiguracoesAfiliadosResource`, as rotas
    `GET/PUT /configuracoes/afiliados`, os campos no model `Configuracoes` e a seção `afiliados` de
    `utils/settings_fields.js`.
- **Rationale**: clarificação "o ganho é classificado no cadastro por quem gerencia"; afiliado sem
  regra não gera lançamento (`ComissoesAfiliados` já trata `regra === null`).
- **Alternatives considered**: manter a configuração só como sugestão no modal — rejeitado pelo
  responsável.

### R-06 — Aprovar e recusar só pelo Admin

- **Decision**: `aprovar` e `recusar` trocam `garantir_gestor_geral` por uma checagem de Admin; para
  os demais, 404 "Afiliado não encontrado." (os cadastros sem gerente são invisíveis para eles). A
  aba e o cartão Em análise só aparecem para o Admin com `afiliados.alterar_situacao`. A seed de
  permissões não muda (o Supervisor continua com `afiliados.alterar_situacao`, que usa para
  ativar/inativar).
- **Rationale**: clarificação B; a rede da spec 019 sem exceção.
- **Alternatives considered**: permissão nova `afiliados.aprovar` — mais flexível, mas não pedida
  (Princípio VI: sem padrão novo sem necessidade).

### R-07 — Senha na exclusão

- **Decision**: `DELETE /afiliados/{afiliado}` passa a exigir `password` no corpo, com o novo
  `ExcluirAfiliadoRequest`, que confere a senha do usuário logado como o
  `PrestacaoContasAfiliadoRequest` (mesma regra e mensagem "Senha incorreta."). A conferência
  acontece antes da transação.
- **Rationale**: clarificação 1 do `/speckit-specify`; padrão já usado na prestação.
- **Alternatives considered**: reaproveitar o trait `ConfirmacaoCaixa` — ele traz regras de data
  do caixa que não se aplicam; a regra de senha é copiada do request de prestação, que já é do
  recurso afiliados.

### R-08 — Campos novos do afiliado

- **Decision**: colunas `rede_social` (varchar 20, enum `RedeSocialAfiliado`: Instagram, TikTok,
  YouTube, Facebook, Kwai, X, Outra), `link_perfil` (varchar 255), `quantidade_seguidores`
  (unsigned int) e `sobre` (text, validado até 1.000 caracteres), todas nulas no banco (afiliados
  existentes). Regras em `DadosAfiliado::regras_perfil_afiliado($presenca)`: `required` no
  cadastro pelo painel e no público; na edição e em "Meus dados", `sometimes` + `required` quando o
  afiliado já tem o valor (não volta a vazio). Link com `url:http,https`.
- **Rationale**: clarificação 1 do `/speckit-clarify`; nulo no banco para não inventar dado nos
  antigos.
- **Alternatives considered**: tabela `afiliados_redes_sociais` com várias redes — rejeitada
  (opção C).

### R-09 — Edição com situação e senha no mesmo modal

- **Decision**: o modal de edição envia a situação para `PATCH /afiliados/{afiliado}/situacao` (rota
  existente) só quando ela mudou, depois do `PATCH /afiliados/{afiliado}` dos dados; senha nova vai
  no `PATCH` dos dados (`password` + `password_confirmation`, já aceitos). Campos mascarados só são
  enviados quando o usuário digita um valor novo.
- **Rationale**: as rotas já existem e separam as permissões (`editar` x `alterar_situacao`); não há
  motivo para juntá-las no servidor.

### R-10 — Gaveta do afiliado

- **Decision**: componente `AffiliateDrawer` (padrão de `PromotionDrawer`), abas Dados (`GET
  /afiliados/{afiliado}`), Extrato (`GET .../lancamentos`, com soma do filtro devolvida em `meta.soma`),
  Clientes (`GET .../clientes`), Prestações (`GET .../prestacoes`) e Regras (`GET .../comissoes`),
  cada uma carregada ao abrir. `AfiliadosResource` ganha `cadastrado_por` e `decidido_por` (`id`,
  `nome`) com as relações novas `autor_cadastro` e `autor_decisao` no model.
- **Rationale**: as rotas existem; falta só a soma do extrato e os autores.

### R-11 — Restauração com conflito

- **Decision**: componente novo `AffiliateRestoreModal`, no padrão do `RestoreConflictModal` (que é
  só de cliente e não muda, Princípio IV), pedindo o valor novo dos campos devolvidos em `errors`.
- **Rationale**: o `RestoreConflictModal` está preso ao cliente (rota e campos); generalizá-lo
  mexeria em código da spec 019.

### R-12 — Modais em grade e sem interruptor

- **Decision**: `AffiliateFormModal` (cadastro e edição), `AffiliateApprovalModal`,
  `AffiliateRuleModal` (trocar regra), `AffiliateSettlementModal` (prestação) e
  `AffiliateDeleteModal` (exclusão com aviso e senha) e `AffiliateRejectModal` (recusa com motivo,
  no padrão do `WithdrawalDecisionModal`), todos com `ModalHeader`, corpo em grade de duas colunas
  no computador e uma no celular, seções com título, rodapé fixo com Cancelar e a ação principal à
  direita, e seleções nativas (`select`) no lugar de `ToggleSwitch`. O `ConfirmModal` (só título e
  mensagem) é usado sem alteração para ativar/inativar, restaurar sem conflito e mostrar o link para
  copiar à mão (o campo vai dentro da `mensagem`).
- **Alternatives considered**: acrescentar um campo de motivo ao `ConfirmModal` — rejeitado: ele não
  está na lista de arquivos alterados (Princípio IV) e não mostra erro por campo.
- **Rationale**: pedido do responsável e decisões visuais da spec 020 (sessão de 2026-10-25).

### R-13 — Cadastro público e "Meus dados"

- **Decision**: `AffiliateSignupModal` e `AffiliateProfileModal` ganham os quatro campos (rede
  social em seleção, link, seguidores só números, "sobre você" com contador), no visual atual de
  cada um; `CadastroPublicoAfiliadoRequest` e `UpdateMeusDadosAfiliadoRequest` aceitam os campos;
  `MeusDadosAfiliadoResource` os devolve.
- **Rationale**: FR-017; mudança autorizada na spec 012.

### R-14 — Observação da prestação

- **Decision**: manter o limite atual de 500 caracteres da observação (coluna e request da spec 012);
  a spec é ajustada de 255 para 500.
- **Rationale**: evitar migration e mudança de regra sem ganho.

### R-15 — Lista de gerentes

- **Decision**: os campos de gerente (filtro, cadastro, edição e aprovação) usam a rota atual
  `GET /usuarios?funcao=Gerente&por_pagina=100` (spec 015), que já devolve só a sub-hierarquia de
  quem consulta (Admin: todos; Supervisor: os da rede dele) e exige `usuarios.listar` (padrão de
  Admin e Supervisor). Sem essa permissão, o campo mostra "Você não tem acesso à lista de
  gerentes." e o Gerente não vê o campo (ele é o responsável).
- **Rationale**: mesma fonte do `UserFormModal`; nenhuma rota nova.
- **Alternatives considered**: rota `GET /afiliados/gerentes` com `afiliados.cadastrar` — só se a
  dependência de `usuarios.listar` virar problema na prática.

### R-16 — Resumo e filtros

- **Decision**: "Total de afiliados", "Comissão do período" e "A receber" seguem rede, busca, gerente
  e sem gerente; só "Comissão do período" usa o período; "Em análise" conta todos os cadastros Em
  análise, sem busca nem filtros (só o Admin vê).
- **Rationale**: clarificação do `/speckit-analyze` (I1, I2); os cadastros em análise não têm gerente.

## Dados fake

Nenhum (Princípio IX).
