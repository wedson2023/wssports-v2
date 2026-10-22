# Research: Painel — Apostas

**Spec**: [spec.md](spec.md) · **Data**: 2026-10-22

## R-01 — O que existe hoje e como o antigo funcionava (Princípio VII)

- **No v2**: não há lista de apostas no painel. Já existem e não mudam:
  - criar e acompanhar (`ApostasController@store`, `@situacao`), validar código
    (`ValidacaoApostasController`), cancelar (`CancelamentoApostasController` →
    `CancelamentoApostas`), cancelar e restaurar palpite (`ApostasPalpitesController` →
    `EdicaoApostas`), com o alcance de `AlcanceApostas` e as permissões `apostas.*` de `Funcao.php`;
  - o comprovante `ComprovanteApostaResource` (com `mostrar_comissao`), o modal `TicketModal` (site e
    Dashboard: compartilhar, acompanhar, fechar) e a página do bilhete em tempo real
    (`/bilhete/{codigo}`, spec 010);
  - a impressão do vendedor (`useImpressao`, `utils/print.js`, `utils/thermal.js`: navegador,
    Bluetooth e modo APP);
  - as somas do Caixa (`ApuracaoCaixa::somas_apostas`, spec 017) e da Caixa online (`CaixaOnline`),
    o alcance da Dashboard (`AlcanceDashboard::ids_vendedores`) e a planilha do Caixa
    (`ExportacaoCaixa`, `phpoffice/phpspreadsheet`).
- **Sistema antigo consultado** (`wssports.bet`, `main` `408cf730`, somente leitura; a primeira
  leitura no commit `f2aad6fe` de 2023 foi refeita na `main`, ver Clarifications):
  - `app/Http/Controllers/BilhetesController.php`: `index` (linhas 204–649, cinco consultas, uma por
    aba), `excelApostas` (1696–2149), `premiados` (1632–1670), `destroy` (1449–1597);
  - `app/Http/Controllers/ConfrontosController.php@destroy` (remover/restaurar palpite e recalcular
    prêmio e comissão com SQL concatenado);
  - `routes/api.php` (`bilhetes`, `premiados`, `excel-apostas`, `deletar-bilhetes`, `subir-creditos`);
  - telas `resources/js/screens/adm/bets/index.js`, `screens/adm/components/bar/index.js`,
    `utils/helpers.js` (`data_tabs`, `printWinner`, `amount`), `modals/ticket/index.js` e o atalho de
    `screens/adm/financial/seller/index.js` (`lista-de-apostas?q=` com período e `usuarios_id` em
    base64).
- **Como funcionava** e **problemas encontrados**: resumidos na spec ("Referências do sistema
  antigo"). Na `main`, a coluna Prêmio já somava `valor_acrescido`, e a aba "créditos" incluía as
  apostas pagas com bônus.

## R-02 — Uma consulta única com filtros (`ListagemApostas`)

- **Decision**: um service `ListagemApostas` monta uma única consulta base (query builder) para a
  lista, os totais, a planilha e os premiados, com os mesmos filtros:
  - alcance: `AlcanceDashboard::ids_vendedores($usuario)` (Admin = todos; Supervisor e Gerente = a
    sub-hierarquia, com os excluídos; Vendedor = ele) aplicado em `apostas.usuarios_id`; apostas de
    cliente (`clientes_id` não nulo) entram só para Admin e Supervisor (FR-003);
  - situação: `Ativa` nas abas Todas, Pré-jogo e Ao vivo; `Cancelada` na aba Canceladas; nunca
    Pendente, Em análise, Recusada nem Expirada (FR-004); sem `deleted_at`;
  - período: `created_at` entre o início de `de` e o fim de `ate` em Brasília (`FusoSistema`), como o
    Caixa; a busca por nome respeita o período (salvo `todo_periodo=1`); a busca por código vale em
    qualquer data;
  - tipo: Ao vivo = `EXISTS` palpite com `confrontos_ao_vivo_id`; Pré-jogo = `NOT EXISTS` (mesma
    regra do `ApuracaoCaixa`);
  - busca: `apostas.nome LIKE %termo%` (termo escapado, no período) **ou**, quando o termo tem 8
    letras ou números (normalizado), `codigo = termo` em qualquer data. Não se limita ao alfabeto
    atual de `CodigoAposta` porque há apostas com códigos de antes dele (ex.: `Y607HZVO`), e o OU
    mantém a busca por um nome de 8 letras;
  - resultado, vendedor (`usuarios_id`, conferido no alcance), forma de pagamento e prêmio mínimo
    (`TRUNCATE((premio + valor_acrescido) * fracao_restante, 2) >= ?`, a mesma conta de
    `Apostas::total_a_pagar()`).
- **Rationale**: corrige as cinco consultas divergentes do antigo; garante que lista, totais,
  planilha e premiados vejam exatamente as mesmas apostas (SC-002).
- **Alternatives considered**: uma consulta por aba (o antigo); estender `ApuracaoCaixa` (ele soma
  por vendedor e só apostas do balcão; misturaria duas responsabilidades e mexeria em código da spec
  017 sem necessidade).

## R-03 — Totais iguais aos do Caixa

- **Decision**: os totais (FR-012) saem de uma consulta agregada sobre a mesma base, com as mesmas
  expressões do `ApuracaoCaixa::somas_apostas`: Apostas = `COUNT(*)`; Entradas = `SUM(valor)`;
  Comissão = `SUM(comissao)`; Saídas = `premio` das `Vencedor` + `valor` das `Devolvido`; Em aberto =
  quantidade e `SUM(valor)` das `Aguardando`. Encerramentos (só Admin e Supervisor): soma dos
  encerramentos `Registrado` e `Creditado` (as mesmas situações da `CaixaOnline`) das apostas do
  filtro. Diferença conhecida: a Caixa clientes soma os encerramentos pela data em que foram feitos
  (`decidido_em`), e a lista, pelas apostas feitas no período (a base da tela); com o mesmo período,
  os números só diferem quando uma aposta é encerrada num dia diferente do que foi feita. A dica do
  cartão diz "Encerramentos das apostas feitas no período". Na aba
  Canceladas: quantidade e `SUM(valor)`. Tudo em bcmath sobre os decimais do banco.
- **Rationale**: para o mesmo período, tipo e vendedor, os números batem com a linha do vendedor no
  Caixa (SC-002, US4 cenário 3).
- **Observação para o responsável**: as Saídas do Caixa (spec 017) e da Dashboard usam `premio` das
  vencedoras, sem o `valor_acrescido` do ganho múltiplo de palpites, que o apostador também recebe
  (`total_a_pagar`). Esta spec segue a mesma regra para os números baterem, e a coluna Prêmio mostra
  o total a pagar. Corrigir as Saídas nas três telas fica para uma decisão à parte (Princípio IV).
- **Alternatives considered**: chamar `ApuracaoCaixa` (exigiria tornar `somas_apostas` público e
  aceitar os filtros desta tela).

## R-04 — Lista paginada e desempenho

- **Decision**: paginação do Laravel (`paginate`, padrão 20, máximo 100) ordenada por `created_at`
  desc e `id` desc; colunas calculadas por subconsulta: `jogos_total` = palpites `Ativo`;
  `jogos_restantes` = palpites `Ativo` com `resultado = Aguardando` (a mesma regra na lista e na
  planilha, corrigindo o antigo); nome do vendedor por `LEFT JOIN usuarios` (com `deleted_at` e
  `ativo` para a etiqueta "inativo/excluído"); `cancelada_por` com o nome do autor.
- **Índices**: `apostas(situacao, created_at)`, já criado pela spec 014 (`2026_10_20_000003_add_indices_dashboard.php`), para a consulta do Admin (sem filtro de
  `usuarios_id`); as dos demais níveis usam o índice existente `(usuarios_id, situacao, created_at)`
  e as de cliente o `(clientes_id, situacao, created_at)`.
- **Rationale**: SC-007 (2 s com até 50.000 apostas no período); sem N+1.

## R-05 — Bilhete no painel

- **Decision**: `GET /apostas/{codigo}` devolve o `ComprovanteApostaResource` com
  `mostrar_comissao: true` (o mesmo que o cancelamento e a edição já devolvem) e um bloco `painel`
  com `cancelada_em`, `cancelada_por` e `acoes` (o que o usuário pode fazer: `cancelar`,
  `editar_palpites`, `imprimir_segunda_via`), calculado pelas mesmas regras do servidor (permissão,
  alcance, situação), para a tela não oferecer ação que será recusada (FR-017). A decisão final
  continua nos services existentes.
- **Alcance**: o mesmo da lista (R-02); fora dele → 404 "Aposta não encontrada." (não revela que
  existe).
- **Ações sem exceção**: `AlcanceApostas` ganha `pode_cancelar()` e `pode_editar()` públicos (a regra
  privada `alcanca` exposta como sim/não); `garantir_pode_cancelar` e `garantir_pode_editar`
  passam a usá-los, sem mudar a regra.
- **Motivo do palpite**: o `ComprovanteApostaResource` passa a enviar `motivo_cancelamento` em cada
  palpite (a coluna já existe), para o bilhete mostrar o palpite cancelado com o motivo.
- **Frontend**: o `TicketModal` existente ganha props opcionais (`painel`, `ao_cancelar_aposta`,
  `ao_cancelar_palpite`, `ao_restaurar_palpite`, `ao_imprimir`); com `painel`, o cabeçalho passa a ser
  o `ModalHeader` (constituição, "Cabeçalho dos modais do painel"); sem as props, o site e a
  Dashboard continuam iguais.
- **Alternatives considered**: um modal novo só do painel (duplicaria o comprovante); usar
  `GET /publico/apostas/{codigo}` (público, sem comissão e sem alcance).

## R-06 — 2ª via

- **Decision**: `GET /apostas/{codigo}/segunda-via` com `apostas.imprimir_segunda_via`, alcance da
  lista e aposta `Ativa` com `resultado = Aguardando` (Clarifications); senão 422 "A 2ª via só pode
  ser impressa em apostas aguardando resultado." (403/404 para permissão e alcance). Devolve o
  comprovante para impressão. O frontend imprime pelo `useImpressao.imprimir_bilhete` com um
  parâmetro opcional `segunda_via`: no navegador e no Bluetooth acrescenta a linha "2ª via" no topo
  do comprovante (`print.js`, `thermal.js`); no modo APP envia `true` no último segmento do endereço
  `app://` (hoje sempre `false`), como o antigo fazia.
- **Rationale**: a regra fica no servidor (o antigo só escondia o ícone); a impressão reaproveita os
  três meios que o vendedor já usa.

## R-07 — Premiados (imagem)

- **Decision**: `GET /apostas/premiados?de&ate` (com `apostas.exportar`, Gerente para cima, alcance
  da lista) devolve até 23 apostas `Ativa` com `resultado = Vencedor` feitas no período, ordenadas
  pelo total a pagar desc, e a `url_logo` e o `nome_sistema` das configurações. A imagem é desenhada
  no navegador com a API Canvas 2D nativa (logo, título com o período e a tabela código, vendedor,
  cliente, valor, prêmio, situação, nas cores do tema) e compartilhada com `navigator.share` (arquivo
  PNG) ou baixada quando o aparelho não compartilha arquivos.
- **Rationale**: sem dependência nova (o antigo usava `html2canvas`); o logo é servido pelo próprio
  site, então o canvas não fica bloqueado para exportar.
- **Alternatives considered**: `html2canvas` (dependência nova); gerar a imagem no servidor com GD
  (mais código de layout no backend e fontes no servidor).

## R-08 — Planilha

- **Decision**: `GET /apostas/exportacao` (com `apostas.exportar`) gera o .xlsx a partir da mesma
  base (R-02), sem paginação, até 50.000 linhas: antes de gerar, conta as apostas do filtro; acima do
  limite → 422 "O filtro tem N apostas; o máximo por planilha é 50.000. Reduza o período ou use
  filtros." (a tela consulta a contagem no resumo e avisa antes). Cabeçalho com período e filtros,
  totais e uma linha por aposta (código, vendedor, cliente, valor, comissão, prêmio, situação, jogos,
  horário), valores em formato de moeda.
- **Arquivo**: gerado num arquivo temporário único por pedido e apagado após o envio
  (`deleteFileAfterSend`), como o Caixa; corrige o arquivo fixo do antigo.
- **Reuso do estilo**: a montagem da planilha (`ExportacaoCaixa::gerar`, privada) passa para uma
  classe compartilhada `app/Support/PlanilhaPainel.php`, usada pelo Caixa e por esta tela, sem mudar
  o resultado da planilha do Caixa. Confirmado pelo responsável em 2026-10-22 (altera
  `ExportacaoCaixa.php`, Princípio IV); a alternativa rejeitada era copiar o método para
  `ExportacaoApostas` (duplicação de ~140 linhas).
- **Linhas**: leitura por cursor (uma consulta, um model por vez) e montagem linha a linha.
- **Planilha grande**: o `PlanilhaPainel` aceita larguras fixas por coluna; com elas, as linhas
  alternadas viram uma regra de formatação condicional (no lugar de um estilo por linha) e as colunas
  não se ajustam ao conteúdo. O Caixa continua com o modo de antes (planilha idêntica, conferida
  célula a célula). A rota da planilha tem tempo máximo de 180 s.
- **Medição** (2026-10-22, máquina local): 7,4 mil linhas em 8 s; 37 mil linhas em 44 a 48 s e
  330 MB de memória (a biblioteca de planilhas leva cerca de 9 s escrevendo e 11 s salvando). No
  limite de 50.000 linhas, a estimativa é de cerca de 60 s e 450 MB, perto do `memory_limit` de
  512 MB. Ver as notas de `tasks.md`.

## R-09 — Permissões

- **Decision**: `apostas.listar`, `apostas.exportar` e `apostas.imprimir_segunda_via` entram em
  `Funcao::PERMISSOES_APOSTAS` (o seeder `PapeisPermissoesSeeder` já espalha essa constante e não
  muda). Em `pode_usar`, as três valem para todas as funções (como `apostas.cancelar`); os premiados
  ficam fora do Vendedor por regra fixa no controller (FR-020). Migration de dados cria as três e as
  dá aos usuários existentes conforme `permissoes_padrao`, como a da spec 017 (R-08 de lá). Nomes e
  descrições em `utils/permissions.js` (tela de permissões da spec 015).
- **Padrão por função** (FR-023): Admin, Supervisor e Gerente recebem as três (os gestores já
  recebem todas de `PERMISSOES_APOSTAS`); Vendedor recebe as três pelo filtro de `pode_usar`.

## R-10 — Lista de vendedores para o filtro

- **Decision**: `GET /apostas/vendedores?busca` (com `apostas.listar`; não para o Vendedor) devolve
  até 20 vendedores da rede do usuário (id, nome, login, situação), pelo mesmo alcance, para o campo
  de busca com resultados (constituição, "Campos de itens existentes").
- **Alternatives considered**: `GET /usuarios` da spec 015 (exige `usuarios.listar`, que quem só
  consulta apostas pode não ter).

## R-11 — Frontend e atalho do Caixa

- **Decision**: página Inertia `PanelBets` (`/painel/apostas`) no `PanelShell`, com `PeriodFilter`
  (spec 017), `PanelTabs`, `StatCard`, `Pagination`, `ActionsMenu` e `TicketModal`; estado da tela
  (aba, período, filtros, página) na query string (FR-011). Componentes novos: `BetsTable` (tabela
  que vira cartões no celular), `BetsFilters` (painel de filtros e etiquetas), `SellerPicker` (busca
  de vendedor), `WinnersModal` (prévia da imagem com compartilhar/baixar); hook `useApostasPainel`;
  util `winners_image.js` (desenho no canvas).
- **Atalho do Caixa**: `PanelCash` acrescenta o item "Ver apostas" (`receipt_long`) no menu da linha
  quando a lista é de vendedores e o usuário tem `apostas.listar`, abrindo
  `/painel/apostas?usuarios_id=&de=&ate=&aba=` com o tipo do Caixa (`todas`, `pre_jogo`, `ao_vivo`).
  É só no frontend: as `acoes` da API do Caixa não mudam.

## R-12 — Dados fake

Nenhum. Todos os dados vêm do backend existente ou desta spec.
