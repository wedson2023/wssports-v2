# Research: Painel — Especiais

**Feature**: [spec.md](spec.md) | **Plano**: [plan.md](plan.md)

## Sistema antigo consultado (Princípio VII)

`wssports.bet`, `main` `408cf730` (somente leitura):

- `app/Http/Controllers/ModalidadesController.php`:
  - `index`: abas `ativos` e `lixeira` (`onlyTrashed`), `pesquisar` por nome (`LIKE`), datas
    formatadas no fuso do cabeçalho `timezone` (`encerra_em`, `criado_em`), 250 por página, da mais
    nova para a mais antiga; devolvia também `auth`, `travas_gerentes`, `travas_supervisor` e
    `config`.
  - `store`: `nome`, `horario` e `palpites.*.nome`/`palpites.*.odd` obrigatórios (só a primeira
    mensagem de erro); somava o cabeçalho `hour` ao horário; criava as opções com `createMany`.
  - `show`: categoria com as opções e o horário no fuso de quem vê.
  - `update`: gravava nome, horário e `vencedor` (o **nome** da opção); com vencedor, situação
    "Encerrado"; **apagava de vez** (`forceDelete`) todas as opções e criava de novo; com vencedor,
    buscava os bilhetes com palpite na modalidade feitos entre `created_at` e `horario` e
    recalculava o `status` de cada um pelo `OperacaoController`.
  - `destroy`: alternava remover e restaurar (categoria e opções).
- Models `Modalidades` (`palpites()` → `ModalidadesEspeciais`) e `ModalidadesEspeciais` (`nome`,
  `odd`, `modalidades_id`).
- Telas `resources/js/screens/adm/modalities` ("Lista de modalidades - total N"; botão "Nova
  modalidade" só com a trava `cadastrar_vendedor`; colunas ID, NOME, VENCEDOR, SITUAÇÃO, ATIVO,
  DATA CRIAÇÃO, ENCERRA EM, AÇÃO — VENCEDOR e SITUAÇÃO com os valores trocados) e
  `resources/js/screens/adm/modals/modalities` (Nome, Horario, Ativo e Vencedor só na edição, opções
  com nome e "Valor Opção" e o botão "add").

Os problemas encontrados e as diferenças aprovadas estão na spec ("Referências do sistema antigo").
Não havia: cancelamento, anulação de opção, mais de um vencedor, divisão por empate, correção com
estorno, exposição.

## Código atual do v2 consultado

- Especiais (spec 006): migrations `2026_10_10_000002/3/4`, models `Especiais` e `EspeciaisOpcoes`,
  `EspeciaisController` (index com `situacao`/`busca`/`por_pagina`, show com
  `quantidade_palpites`, store com as opções, update, destroy recusado com palpites em apostas
  ativas), `EspeciaisOpcoesController`, `EncerramentoEspeciaisController` +
  `EncerramentoEspeciais` (encerrar com **uma** vencedora `especiais_opcoes_id_vencedora`,
  cancelar), `EncerrarEspecialRequest`, `EspeciaisResource` (`opcao_vencedora`),
  `ListagemEspeciais` (lista pública), `Funcao::PERMISSOES_ESPECIAIS`.
- Apuração (spec 009): `ApuracaoApostas::decidir` e `fotografia` (públicos),
  `LiquidacaoApostas::liquidar` (crédito da diferença depois da janela, estorno na hora, valor
  pago guardado em `apostas.valor_pago`), `OrigemApuracao`, `RegistroApuracao`.
- Edição de apostas: `EdicaoApostas::cancelar_palpite_pelo_sistema` (público, com
  `MotivoCancelamentoPalpite`) e `recalcular` (privado; usa `cotacao_final` dos palpites ativos).
- Exposição (spec 023): `ExposicaoConfrontos` e `ListagemApostas::aplicar_alcance` (público).
- Senha de quem faz: `ExcluirAfiliadoRequest` / `PrestacaoContasAfiliadoRequest` (campo
  `password`, "Senha incorreta.").
- Lixeira: `AfiliadosController::restaurar` e `AffiliateRestoreModal` (spec 022).
- Permissão nova por migration: `2026_10_27_000004_criar_permissao_robo_whatsapp.php`.
- Frontend do painel: `PanelAffiliates`/`useAfiliadosPainel`, `PanelMatches`/`MatchDrawer`,
  `PanelShell`, `PanelHeader`, `PanelTabs`, `StatCard`, `ActiveFilters`, `ActionsMenu`,
  `ModalHeader`, `ConfirmModal`, `DecimalField`; comprovante em `TicketModal`.

## Decisões

### R-01 — Uma página com duas abas

**Decisão**: página `PanelSpecials` em `/painel/especiais`, com as abas "Especiais" (filtro de
situação) e "Lixeira", a gaveta `SpecialDrawer` e os modais `SpecialFormModal`,
`SpecialCloseModal` (encerrar e corrigir), `SpecialVoidOptionModal` (anular) e `ConfirmModal`
(cancelar, remover, restaurar).
**Motivo**: mesmo desenho de `PanelAffiliates` (aba de lixeira) e `PanelMatches` (gaveta).
**Alternativas**: abas por situação (Aguardando, Encerrado, Cancelado) — rejeitado: a spec pede
filtro de situação com etiqueta, e a lixeira é a única visão com ações diferentes.

### R-02 — Listagem do painel por serviço próprio

**Decisão**: `EspeciaisController@index` passa a usar o serviço `ListagemEspeciaisPainel` e o request
`FiltrosEspeciaisPainelRequest` (`situacao` com `Todas`, `busca`, `lixeira`, `por_pagina` até 100,
padrão 20). Cada linha traz `quantidade_opcoes` (ativas, não anuladas), `apostas_em_aberto` e
`valor_em_aberto` (subconsulta agregada por `especiais_id` sobre `apostas_palpites` + `apostas`, no
alcance de quem consulta), as vencedoras e `acoes` (o que o usuário pode fazer, como na spec 023).
Ordem: Aguardando por `data_limite` crescente; Encerrado/Cancelado por `encerrado_em`
decrescente; "Todas" primeiro as Aguardando; Lixeira por `deleted_at` decrescente.
Rota nova `GET /especiais/resumo` para os cartões (mesmos filtros de busca).
**Motivo**: o index atual não tem as contagens nem a lixeira, e o padrão das specs 019 a 023 é o
serviço de listagem com request de filtros.
**Alternativas**: calcular os números no frontend pela gaveta de cada categoria — rejeitado (N
chamadas).

### R-03 — Alcance das apostas

**Decisão**: todo número de apostas (cartões, lista, gaveta) usa `ListagemApostas::aplicar_alcance`
com quem consulta, como `ExposicaoConfrontos`; a resposta traz `alcance: "todas" | "rede"` para a
tela mostrar "Números da sua rede" quando for `rede` (Clarifications, Q3).
**Motivo**: os números batem com a tela Apostas e com a spec 023.

### R-04 — Exposição por opção

**Decisão**: serviço `ExposicaoEspeciais` e rota `GET /especiais/{especial}/exposicao`. Conta só
palpites `Ativo` em apostas `Ativa` com `resultado = Aguardando` (FR-022), agrupados por
`especiais_opcoes_id`; Simples = aposta com um só palpite ativo, Múltipla = com mais de um, contada
inteira (`valor` e `total_a_pagar`). Devolve por opção quantidade, valor e possível retorno em
simples, múltiplas e total, mais o total da categoria e a opção de maior possível retorno. Somas em
`DECIMAL` no SQL.
**Motivo**: mesma regra de "múltipla conta inteira" da spec 023 (FR-016), adaptada: o especial não
tem "jogos", então a separação é pela quantidade de palpites ativos.

### R-05 — Vencedoras guardadas na opção

**Decisão**: colunas novas em `especiais_opcoes`: `vencedora` (boolean, nulo até o encerramento),
`empatada` (boolean, padrão falso), `anulada_em` e `anulada_por`. Em `especiais`:
`quantidade_vencedoras` (padrão 1) e `dividir_empate` (padrão falso). A coluna
`especiais_opcoes_id_vencedora` é migrada (a opção dela recebe `vencedora = true` e as demais da
categoria encerrada `false`) e removida, com a chave estrangeira. O `EspeciaisResource` troca
`opcao_vencedora` por `vencedoras` (lista).
**Motivo**: a vencedora passa a ser uma propriedade da opção; uma tabela de ligação só para isso
seria a mesma informação com um `JOIN` a mais.
**Alternativas**: tabela `especiais_vencedoras` — rejeitada (a opção já é da categoria; não há
dado próprio da ligação além de `empatada`).

### R-06 — Divisão por empate na cotação do palpite

**Decisão**: coluna nova `apostas_palpites.cotacao_empate` (decimal 8,2, nula): a cotação aplicada
pela divisão (FR-015). `EdicaoApostas::recalcular` passa a usar `cotacao_empate ?? cotacao_final`
nas pernas; método público novo `EdicaoApostas::recalcular_pelo_sistema(Apostas, ApostasPalpites,
AcaoHistoricoAposta, ?Usuarios, ?string, ?string)` chama o `recalcular_e_registrar` existente. Caso
novo em `AcaoHistoricoAposta`: `DivisãoEmpate = 'Divisão por empate'`. Conta:
`cotacao_empate = bcdiv(bcmul(cotacao_final, V, 4), E, 2)` (o `bcdiv` trunca = arredondar para
baixo), com `V = Q − (M − E)`. Pode ficar abaixo de 1,00 (decidido na spec).
**Motivo**: o prêmio já é recalculado a partir das cotações dos palpites ativos; guardar a cotação
aplicada ao lado da original mantém o comprovante e permite a correção desfazer a divisão
(`cotacao_empate = null`).
**Alternativas**: sobrescrever `cotacao_final` — rejeitado (perde a cotação original e a correção
não teria como voltar).

### R-07 — Encerrar com várias vencedoras

**Decisão**: `POST /especiais/{especial}/encerrar` passa a receber `vencedoras` (lista de ids),
`empatadas` (lista de ids, subconjunto de `vencedoras`) e `dividir_empate` (boolean). Validação no
`EncerrarEspecialRequest`: opções da categoria e não anuladas; `M = Q` sem divisão; `M > Q` só com
`dividir_empate = true` e `empatadas` com `M − E < Q` e `E > V`; nunca todas as não anuladas; sem
`empatadas` quando `M = Q`. Mensagens da spec ("Escolha 4 opções vencedoras.", "Para todas
vencerem, cancele a categoria."). `EncerramentoEspeciais::encerrar` grava `vencedora`/`empatada`
nas opções, `dividir_empate` na categoria, o resultado e a `cotacao_empate` de cada palpite e chama
`recalcular_pelo_sistema` nos palpites divididos antes de `decidir_apostas`. A rota troca o campo
`especiais_opcoes_id` por `vencedoras` (nenhuma tela usa a rota antiga ainda).
**Motivo**: a regra de quantidade e de empate decidida no `/speckit-clarify`.

### R-08 — Anular uma opção

**Decisão**: rota nova `POST /especiais/{especial}/opcoes/{opcao}/anular` (`especiais.encerrar`),
método `EncerramentoEspeciais::anular_opcao`: bloqueia a categoria Aguardando, recusa opção já
anulada, grava `anulada_em`, `anulada_por` e `ativo = false` (a lista pública e a criação de apostas
já ignoram opção inativa), cancela cada palpite ativo dela em aposta ativa e sem resultado com
`cancelar_palpite_pelo_sistema(..., MotivoCancelamentoPalpite::OpçãoAnulada)` (caso novo `'Opção
anulada'`) e decide/liquida as apostas pelo mesmo `decidir_apostas`, com
`OrigemApuracao::EncerramentoEspecial`. Resposta com `apostas_afetadas`. `EspeciaisOpcoesController`
recusa editar, reativar ou remover opção anulada ("A opção foi anulada.").
**Motivo**: mesmo caminho do cancelamento da categoria, só que para uma opção.

### R-09 — Corrigir resultado

**Decisão**: rota nova `POST /especiais/{especial}/corrigir-resultado` com `vencedoras`,
`empatadas`, `dividir_empate` e `password` (`CorrigirResultadoEspecialRequest`, mesmas regras do
R-07 + senha de quem faz, como `ExcluirAfiliadoRequest`). Permissão nova
`especiais.corrigir_resultado`, criada por migration e dada ao Admin (padrão de
`criar_permissao_robo_whatsapp`), com o grupo "Especiais" em `utils/permissions.js`.
`EncerramentoEspeciais::corrigir`: só categoria Encerrado; recusa "O resultado é o mesmo." quando
vencedoras, empatadas e divisão não mudam; grava a correção em `especiais_correcoes`; atualiza as
opções; para **todos** os palpites ativos da categoria em apostas `Ativa` (qualquer resultado,
inclusive as já pagas) grava o resultado e a `cotacao_empate` novos, recalcula o prêmio quando a
cotação aplicada muda e, por aposta, `fotografia` → `decidir` → `liquidar` com a origem nova
`OrigemApuracao::CorreçãoEspecial = 'Correção de especial'`. O `liquidar` já estorna o pago a mais
na hora (saldo pode ficar negativo) e credita a diferença depois da janela, com transações novas
(spec 009).
**Motivo**: reaproveita a regra de reapuração da spec 009 sem código de dinheiro novo.
**Alternativas**: reabrir a categoria para Aguardando e encerrar de novo — rejeitada no clarify (Q2,
opção C).

### R-10 — Histórico de correções

**Decisão**: tabela nova `especiais_correcoes` (categoria, vencedoras/empatadas/divisão anteriores e
novas em JSON e boolean, `usuarios_id`, `apostas_afetadas`, timestamps e soft delete), mostrada na
gaveta (`GET /especiais/{especial}` com `correcoes`). O `RegistroApuracao` continua registrando
cada aposta (operações de apuração da spec 009).

### R-11 — Lixeira e restaurar

**Decisão**: `lixeira=1` no index lista `onlyTrashed()`; rota nova `POST
/especiais/{especial}/restaurar` (`especiais.gerenciar`, binding `withTrashed`) restaura a categoria
(as opções não são removidas junto na remoção atual, então voltam como estavam); recusa nome
repetido entre as não removidas ("Já existe uma categoria especial com este nome."). Sem exclusão
definitiva.
**Motivo**: mesmo desenho do `AfiliadosController::restaurar` (spec 022).

### R-12 — Edição em grade sobre as rotas existentes

**Decisão**: o modal de edição salva a categoria (`PUT /especiais/{id}`) e depois cada opção
alterada (`POST`/`PUT`/`DELETE /especiais/{id}/opcoes/...`), em sequência, parando no primeiro erro
e mostrando no campo o erro e o que já foi salvo (FR-010). `EspeciaisRequest` ganha
`quantidade_vencedoras` (inteiro ≥ 1, menor que a quantidade de opções no cadastro; na edição,
menor que as opções não anuladas).
**Motivo**: as rotas de opções já existem com as regras e mensagens; uma rota em lote duplicaria a
validação.
**Alternativas**: rota única de "salvar tudo" em transação — rejeitada por duplicar
`EspeciaisOpcoesRequest`; o risco de salvar pela metade é tratado pelo aviso do FR-010.

### R-13 — Comprovante

**Decisão**: `ComprovanteApostaResource::palpite_especial` ganha `cotacao_empate`;
`TicketModal` mostra, quando houver, "Cotação dividida por empate: X (original Y)". A vencedora já
aparece pelo `resultado` do palpite.
**Motivo**: FR-016; mudança mínima no site do apostador.

### R-14 — Sem interruptor nos modais

**Decisão**: "Ativo" da categoria e de cada opção e "Dividir por empate" são `select` Sim/Não, como
nas specs 022 e 023.

### R-15 — Specs anteriores atualizadas na mesma entrega

**Decisão**: `specs/006-ajustes-tela-principal/spec.md` (FR-018: uma ou mais vencedoras; anulação;
restaurar — nota "alterado pela spec 024") e `contracts/api.md` (encerrar, opções, restaurar);
`specs/009-apuracao-resultados/spec.md` (origem "Correção de especial"). Coleção do Postman
regenerada.

## Dados fake (Princípio IX)

Nenhum.
