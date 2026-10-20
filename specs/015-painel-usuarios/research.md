# Research: Painel — Usuários

**Feature**: [spec.md](spec.md) | **Plano**: [plan.md](plan.md) | **Data**: 2026-10-20

## R-01 — Sistema antigo consultado (Princípio VII)

Arquivos lidos (`wssports.bet`, somente leitura): `resources/js/screens/adm/register/{supervisor,manager,seller}/index.js`,
`resources/js/screens/adm/modals/register/{supervisor,manager,seller}/index.js`,
`resources/js/screens/adm/components/header/index.js` (menu "Usuários") e
`screens/adm/components/components` (abas, tabela, paginação). Funcionamento resumido na spec
("Referências do sistema antigo"). Diferenças aprovadas: Clarifications da spec.

## R-02 — Permissões por função alvo (FR-004a)

**Decisão**: trocar `usuarios.cadastrar`, `usuarios.editar` e `usuarios.excluir` por nove permissões
`usuarios.{cadastrar|editar|excluir}_{supervisor|gerente|vendedor}` em `Funcao::PERMISSOES_GESTAO`.
`pode_usar` libera só as de funções abaixo de quem usa (pelo `nivel()`): Admin as três funções,
Supervisor gerente e vendedor, Gerente só vendedor, Vendedor nenhuma. `permissoes_padrao` passa a
filtrar `PERMISSOES_GESTAO` por `pode_usar` (hoje entrega todas; o `pode_usar` impede o uso).

O controller troca o middleware fixo por checagem pela função alvo:
`Funcao::usuario_pode($solicitante, "usuarios.cadastrar_{funcao}")` (store, pela função pedida),
`editar_{funcao do alvo}` (update de dados e troca de superior) e `excluir_{funcao do alvo}` (destroy e
restaurar). Listar, consultar, alterar situação e gerenciar permissões continuam gerais.

**Migração dos usuários atuais**: uma **migration de dados** (não seeder), porque o deploy contínuo
(spec 013) roda migrations e não seeders: sem ela, os usuários do servidor perderiam o acesso ao
cadastro no deploy. Para cada usuário com a permissão antiga, dá as novas equivalentes que a função dele
pode usar; depois apaga as três permissões antigas (o spatie remove os vínculos). O `down` faz o
caminho inverso. Os seeders (`PapeisPermissoesSeeder`/`DatabaseSeeder`) passam a criar as novas.

**Alternativas**: seeder (rejeitado: não roda no deploy); manter as gerais (rejeitado pelo responsável).

## R-03 — Cadastro em qualquer nível abaixo (FR-009)

**Decisão**: `StoreUsuariosRequest` aceita qualquer função com `nivel()` maior que a de quem cadastra.
`usuarios_id` (superior) deixa de ser proibido:

- obrigatório quando a função não é a imediatamente abaixo; deve ser da função logo acima da cadastrada
  e estar na rede de quem cadastra (`gerencia`), igual à regra de troca de superior já existente;
- proibido para a função imediatamente abaixo (o superior é sempre quem cadastra), como hoje.

O Gerente só cadastra Vendedor e sempre na equipe dele (função imediatamente abaixo).

## R-04 — Comissões no cadastro do vendedor (FR-012)

**Decisão**: o cadastro aceita `comissao_1`, `comissao_2` e `comissao_3` (opcionais, 0 a 100, duas
casas) só para Vendedor. Depois do `ConfiguracoesVendedores::criar_para` (cópia do colega), se vierem,
grava `comissao_pre_jogo_1..3` e `comissao_ao_vivo_1..3` com os valores e as faixas 4 a 12 dos dois
com a de 3 jogos (Clarifications). Mesma transação do cadastro.

## R-05 — Lixeira (FR-023)

**Decisão**:

- `GET /usuarios/excluidos`: excluídos (`onlyTrashed`) da sub-hierarquia de quem lista, **incluindo os
  excluídos no caminho** (subida com `withTrashed`, como em `AlcanceDashboard` da spec 014), com os
  mesmos filtros e paginação da lista.
- `POST /usuarios/{usuario}/restaurar` (binding `withTrashed`): exige `excluir_{funcao do alvo}`;
  recusa (422) se o superior ainda está excluído; restaura o alvo e os da sub-hierarquia dele com o
  **mesmo `deleted_at`** (a exclusão em cascata grava todos no mesmo comando, logo com o mesmo
  instante), ou seja, os excluídos junto com ele.
- Sem exclusão definitiva (Constituição).

## R-06 — Listas: colunas extras (FR-006)

**Decisão**: o `UsuariosResource` ganha campos opcionais, carregados só quando pedidos pela listagem
(sem custo nas demais rotas):

- `superior` (`{id, nome}`) com `with('superior')`;
- `comissoes` (`{1, 2, 3}` do pré-jogo) para vendedores, com `with('configuracoes')`;
- `quantidade_vendedores` (gerente: vendedores ativos dele; supervisor: da rede) e
  `quantidade_gerentes` (supervisor), por `withCount` em subconsulta — sem laço por linha.

`index` aceita `funcao` (já existe) e passa a ordenar por nome com `por_pagina` padrão 15.

## R-07 — Configuração de um vendedor (aba Comissões e Limites e regras)

**Decisão**: rota nova `GET /usuarios-configuracoes/{usuario}` (permissão
`usuarios_configuracoes.editar` para editar; leitura liberada a quem pode consultar o usuário, para
as abas aparecerem só para leitura — FR-015). A gravação usa o `PATCH /usuarios-configuracoes`
existente (`usuarios_id` = vendedor, gerente ou supervisor = alcance), que já devolve quantos
vendedores foram alterados. Regras da equipe/rede começam vazias: só os campos alterados são enviados.

Para a confirmação da Regras da rede (FR-014a), a quantidade vem de `quantidade_vendedores` da linha do
supervisor (R-06).

## R-08 — Dados do usuário logado (menu e botões)

**Decisão**: `GET /auth/eu` (spec 014) ganha `permissoes`: os nomes das permissões que o usuário tem
**e** pode usar. O menu "Usuários", os subitens e cada botão leem dela; o servidor continua recusando
de forma independente.

## R-09 — Frontend

- **Rotas** (`routes/web.php`): `/painel/supervisores`, `/painel/gerentes` e `/painel/vendedores`, no
  `PainelController`, renderizando a página `PanelUsers` com a prop `funcao`. Sem permissão de ver a
  função, a página leva à Dashboard.
- **Menu**: o `PanelShell` (spec 014) passa a aceitar grupos com subitens ("Usuários" > Supervisores,
  Gerentes, Vendedores), visíveis por função e permissão (R-08), com o item ativo destacado.
- **Componentes novos**: `UsersTable` (tabela no desktop, cartões no celular), `PanelTabs` (Ativos e
  Lixeira; Dados, Comissões...), `UserFormModal` (cadastro e edição com abas), `CommissionFields`
  (1 a 12 do pré-jogo e do ao vivo), `BettingRulesFields` (limites e regras), `PermissionsPanel`
  (grupos e chaves; usado na aba Permissões e no `PermissionsModal`), `PermissionsModal`,
  `ToggleSwitch` e `Pagination`.
- **Nomes das permissões**: `resources/js/utils/permissions.js` — lugar único com grupo, nome
  amigável e descrição de cada permissão (spec, Assumptions).
- **Hook**: `useUsuariosPainel(funcao, aba)` — busca, paginação, busca com atraso e recarga.
- Reuso: `ConfirmModal`/`confirmar` (sweetalert2) para as confirmações, `formatar_telefone` e
  `erros_por_campo` de `utils/forms.js`, `useVoltarFecha`, tema e visual da Dashboard.

## R-10 — Dependências

Nenhum pacote novo.
