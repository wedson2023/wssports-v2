# Research: Painel — Cotações

**Spec**: [spec.md](spec.md) · **Data**: 2026-10-21

## R-01 — O que já existe no backend (spec 003) e o que muda

- **Decision**: manter as cinco rotas de gravação e consulta (`/porcentagens-vendedores/{usuario}`,
  `/porcentagens-clientes`, `/porcentagens-campeonatos/{campeonato}`,
  `/porcentagens-confrontos/{confronto}` e `/confrontos-teto-cotacoes`), a validação
  (`RegrasCotacaoRequest`, `PorcentagensConfrontosRequest`), o alcance (`AlcanceHierarquia`) e o
  cálculo (`CalculoCotacoes`). Os controllers das cinco rotas ganham só duas coisas: gravar o
  histórico (R-05) e, em vendedores e campeonatos, respeitar o cadeado (R-04).
- **Rationale**: a spec 016 é de tela; as regras de quem pode o quê (Gerente só alvo Vendedores da
  rede, alvo Todos e Clientes só Admin e Supervisor, `garantir_dono`, `ids_donos_visiveis`) já estão
  testadas desde a spec 003 e a spec pede para não mudá-las (FR-003).
- **Alternatives considered**: uma API nova só para a tela (duplicaria as regras de alcance); gravar
  pela tela com outra validação (dois caminhos para a mesma regra).

**Como o antigo funcionava (Princípio VII)**: `screens/adm/price` (abas PORCENTAGEM e TETO, seletor
PRÉ JOGO/AO VIVO, seletor de usuário com "Sistema", busca, uma linha por cotação com gravar e
cadeado, "alterar todas"), `price-sellers`, `championships-price` e `matches-price` (a mesma lista
para um vendedor, campeonato ou jogo). O cadeado chamava `PUT bloquear-porcentagem-cotacao` com o
usuário escolhido e a função de baixo.

## R-02 — Consultas novas para a tela (`CotacoesPainelController`)

- **Decision**: um controller novo, prefixo `/cotacoes`, só com o que a tela precisa e a API não tem:
  nomes das cotações, públicos editáveis, busca de clientes, porcentagens de um usuário com a soma
  dos superiores e os cadeados, países, campeonatos e jogos com o resumo das regras, prévia, Regras
  ativas, histórico e trancar/destrancar (contratos em [contracts/api.md](contracts/api.md)).
- **Rationale**: as listas de campeonatos e confrontos da spec 003 exigem `campeonatos.listar` e
  `confrontos.listar` e não trazem regras; alterá-las misturaria a tela de cotações com a de
  campeonatos. Um controller próprio, exigindo a permissão da camada (`Funcao::usuario_pode`),
  deixa a tela independente das permissões de listagem e não toca nos controllers de campeonatos e
  confrontos.
- **Alternatives considered**: parâmetro `com_regras` em `GET /campeonatos` e `GET /confrontos`
  (dependeria de `*.listar` e alteraria arquivos de outra spec); calcular tudo no frontend (exigiria
  baixar todas as regras).

## R-03 — Prévia do cálculo

- **Decision**: `GET /cotacoes/previa` devolve, para um jogo, um público e um tipo, as **partes** do
  cálculo por código: cotação do provedor, porcentagem do público, soma dos superiores (vendedores)
  ou regra geral (cliente específico), porcentagem do campeonato, valor do jogo, teto e máximo do
  ao vivo. O frontend junta as partes com os valores pendentes da camada em edição usando a mesma
  fórmula de `CalculoCotacoes::aplicar` (`utils/quotes.js`). `CalculoCotacoes` ganha um método
  público `detalhar()` que reaproveita os métodos privados existentes (`porcentagens_publico`,
  `regras_por_item`, tetos), sem mudar `ajustar()`.
- **Rationale**: a prévia precisa reagir a cada tecla (FR-009) sem ida ao servidor, e as partes vêm
  do mesmo código que calcula o site, então só a soma final é repetida no navegador (5 linhas). O
  quickstart confere prévia × listagem (SC-002).
- **Alternatives considered**: chamar o servidor a cada tecla (lento, muitas requisições); repetir no
  frontend a leitura das camadas (duas fontes da verdade).
- **Jogo de exemplo**: o primeiro jogo de pré-jogo futuro do campeonato aberto (aba Campeonatos), o
  próprio jogo (aba Jogos) ou o próximo jogo do dia com a cotação em foco disponível (Público e
  Tetos); o usuário pode trocar pelo seletor de jogo da prévia.

## R-04 — Cadeado por cotação

- **Decision**: tabela `porcentagens_cadeados` (ver [data-model.md](data-model.md)). Admin e
  Supervisor trancam, na tela de um usuário U, um código no pré-jogo ou no ao vivo (camada
  `vendedores`) ou num campeonato com alvo Vendedores de U (camada `campeonatos`). Regra de
  bloqueio para quem grava (S) uma regra de U' com o código c: está trancado se existe cadeado ativo
  com o código c, na mesma camada (e no mesmo tipo ou campeonato), cujo `usuarios_id` está em
  `U'->ids_hierarquia_acima()` (U' ou um superior dele) e cujo autor (`trancado_por`) não é S nem
  está abaixo de S. Destrancar: só o autor ou um superior dele. Serviço `CadeadosCotacoes`
  (`codigos_trancados(solicitante, camada, usuario, tipo|campeonato)`, `trancar`, `destrancar`).
- **Gravação**: em `valores`, código trancado → 422 "A cotação {nome} está trancada por {nome}.";
  em `todos`, os trancados são pulados e a resposta traz `pulados` (quantidade). O frontend já
  mostra os trancados só para leitura e o "aplicar a todas" pula e avisa (FR-019a).
- **Rationale**: segue o antigo (cadeado posto na tela do usuário escolhido, Clarifications do
  `/speckit-clarify`) e guarda quem trancou, para mostrar "trancada por" e permitir travas de dois
  níveis (vale a mais alta; cada um destranca a sua).
- **Alternatives considered**: coluna JSON de travados dentro de `porcentagens_vendedores` (perde o
  autor e não serve aos campeonatos); permissão por código (explosão de permissões).
- **Gerente**: não tranca (abaixo dele só há vendedores, que não editam cotações); a rota recusa com
  403 para quem não é Admin nem Supervisor.

## R-05 — Histórico de alterações

- **Decision**: tabela `porcentagens_historicos`, uma linha por gravação ou por trancar/destrancar,
  com o autor, a camada, a referência (usuário, cliente, campeonato ou jogo), alvo, tipo e, em
  `alteracoes`, `{codigo: {antes, depois}}` só dos códigos que mudaram. Serviço `HistoricoCotacoes::
  registrar()` chamado pelos cinco controllers de gravação depois de salvar (mesma transação) e pelo
  serviço de cadeados. Sem diferença, não grava. Leitura em `GET /cotacoes/historico`, paginada,
  da mais nova para a mais antiga, filtrada pelo que o usuário pode ver.
- **Rationale**: atende o "histórico simples" escolhido no `/speckit-clarify`: rastreio de quem mudou
  a margem, sem tela de auditoria. Uma linha por gravação mantém o "aplicar a todas" como uma
  alteração só (caso de borda da spec).
- **Retenção**: guardado sempre (sem expurgo); o volume é baixo (gravações manuais). Soft delete só
  por exigência da constituição; a tela não apaga.
- **Alternatives considered**: pacote de auditoria (dependência nova, Princípio IV); log em arquivo
  (não consultável pela tela).

## R-06 — Nomes e grupos das cotações

- **Decision**: `GET /cotacoes/nomes` devolve `{codigo: nome}` dos 323 códigos e de `jogador`, a
  partir de `App\Support\NomesCotacoes` (que já serve o comprovante). Os grupos e a ordem vêm de
  `resources/js/utils/market_groups.js` (`abas_cotacoes`, a mesma do modal de mercados do site),
  sem a aba "Favoritas" (repete códigos); entram o grupo "Jogadores" (`jogador`) e "Outras" (códigos
  sem grupo). A busca procura no nome da cotação e no título do mercado, sem acento.
- **Rationale**: uma fonte só para os nomes (backend) e para os grupos (frontend, já usada no site);
  nada copiado à mão (Princípio VI). O resultado fica em cache no navegador durante a sessão.
- **Alternatives considered**: copiar os 323 nomes para o frontend (duas listas para manter).

## R-07 — Listas de campeonatos e jogos com o resumo das regras

- **Decision**: `GET /cotacoes/campeonatos` (busca, país, `com_ajuste`, paginação padrão 20, máx. 100)
  traz nome, país, bandeira, jogos futuros (`withCount` de confrontos de pré-jogo com início a partir
  de agora) e `resumo` das regras visíveis com algum código: quantidade, alvos e faixa de valores
  (ex.: "+3% · Clientes", "2 regras · −5% a +3%"). O resumo da página é montado com uma consulta para
  os ids da página (sem laço por linha). `com_ajuste` usa `whereExists`. `GET /cotacoes/confrontos`
  faz o mesmo para os jogos de hoje, amanhã e depois de amanhã (Brasília), com busca por time,
  campeonato e dia. `GET /cotacoes/paises` devolve os países distintos com bandeira.
- **Rationale**: cumpre FR-013/FR-015 e SC-006 com consultas agregadas e o alcance do usuário
  (`ids_donos_visiveis`).
- **Alternatives considered**: carregar o resumo item a item (N+1).

## R-08 — Regras ativas

- **Decision**: `GET /cotacoes/regras-ativas` junta (UNION) as seis tabelas de regra e o teto,
  normalizadas em `camada, referencia_id, referencia_nome, alvo, usuarios_id, tipo, quantidade,
  minimo, maximo, updated_at`, só com `JSON_LENGTH(valores) > 0`, filtradas pelo alcance, com filtro
  por camada e paginação (padrão 20, máx. 100). Usuários inativos ou excluídos aparecem marcados.
- **Rationale**: uma lista única e paginada no banco (FR-018), sem carregar tudo na memória.
- **Alternatives considered**: tabela-resumo atualizada a cada gravação (mais um lugar para manter).

## R-09 — Frontend: estrutura da tela e reaproveitamento

- **Decision**: página Inertia `PanelQuotes` em `/painel/cotacoes` (`?aba=publico|campeonatos|jogos|
  tetos|regras`), no `PanelShell`, com `PanelTabs`. O editor (`QuoteGrid` + `QuotePreview` +
  `SaveBar`) é o mesmo nas camadas: inline nas abas Público e Tetos; dentro de um modal
  (`QuoteEditorModal`, altura fixa desde a abertura, `ModalHeader`) para um campeonato ou jogo
  escolhido na lista. Estado pendente no hook `useGradeCotacoes` (original, pendentes, erros,
  desfazer, contagem, aviso ao sair com `beforeunload` e com o evento `before` do Inertia).
  Reaproveita `PanelTabs`, `Pagination`, `ActionsMenu`, `ModalHeader`, `ToggleSwitch`, `Spinner`,
  `alerts.js` e o padrão de campos e cores das specs 014/015 (constituição v3.4.0 a v3.7.0).
- **Rationale**: um formulário só para todas as camadas (FR-004 a FR-008) e o visual já aprovado.
- **Alternatives considered**: uma página por camada (quatro telas como no antigo; a spec pede uma).

## R-10 — Menu e acesso

- **Decision**: item "Cotações" (ícone `price_change`) no `PanelShell`, visível com qualquer uma das
  cinco permissões de cotação; cada aba pela sua permissão; Regras ativas para quem tem qualquer
  uma. O Vendedor não tem as permissões (spec 003), então não vê o item; `/painel/cotacoes` sem
  permissão volta para `/painel` como as listas da spec 015.
- **Rationale**: FR-001/FR-002 com as permissões que já existem.
- **Supervisor**: segue a spec 003 (gestor geral: vê e altera as regras de todos os donos);
  "cada nível só na sua rede" vale para o Gerente, como já funciona hoje.
