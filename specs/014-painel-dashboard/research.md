# Research: Painel de controle — base do painel e Dashboard

**Feature**: [spec.md](spec.md) | **Plano**: [plan.md](plan.md) | **Data**: 2026-10-20

## R-01 — Sistema antigo consultado (Princípio VII)

**Arquivos lidos** (`wssports.bet`, somente leitura):

- `app/Http/Controllers/DashboardController.php` — `index`, `apuradoDiaAdm`, `apuradoDia`,
  `rankingConfrontos`, `modalidades`, `vendedores`, `apostas`, `apostasPifadas`, `confrontos`,
  `combinacaoDeRisco`.
- `routes/api.php` — `resource dashboard`, `GET apurado-dia`, `GET combinacao-de-risco`.
- `resources/js/screens/adm/dashboard/index.js` e `styles.js` — tela.
- `resources/js/screens/adm/components/header` (barra preta com logo, menu, APOSTAR e SAIR; menu
  horizontal de ícones no desktop e gaveta de 70% no celular) e `components/bar` (barra de título).
- `resources/js/modals/Combination` e `resources/js/modals/ticket`.

**Como funcionava**:

| Seção | Regra antiga |
|---|---|
| Apurado do dia | Vendedor: limites das travas + bilhetes em dinheiro + acumuladão + créditos vendidos + lançamentos dele. Gerente: o mesmo dos vendedores dele + `comissao_gerente = travas_gerentes.comissao% × (entradas − saídas − comissão)`, zero se o líquido ≤ 0. Supervisor/Admin: vinha vazio no carregamento e era calculado só no botão (`apurado-dia`), em laço por gerente. |
| Situação dos jogos | `COUNT(palpites)` por `confrontos.situacao` (conta palpites, não jogos); a consulta era executada duas vezes. |
| Apostas | Totais e valores de abertas, premiadas, perdidas e excluídas (`deleted_at`), bilhetes + acumuladão. |
| Combinação de risco | Tabela temporária com palpites de apostas abertas de ontem e hoje, agrupadas por código e depois por `confrontos + modalidades + primeiro_jogo` com `HAVING combinacao > 1`; filtro de gerente sempre verdadeiro (ignora hierarquia). Só Admin e Supervisor. |
| Apostas por um jogo | Bilhetes abertos com exatamente 1 palpite em jogo Aguardando/Bloqueado, top 50 por prêmio. |
| Vendedores | SQL concatenado (`UNION` de créditos, bilhetes e acumuladão), top 10 por entradas. |
| Jogos / Opções mais apostados | Top 10 por quantidade de palpites, filtro por hierarquia. |

**Problemas a corrigir** (todos aprovados na spec): apurado vazio para Admin/Supervisor; combinação
sem hierarquia; contagem de palpites como jogos; tudo numa requisição sem cache; laço em PHP; tabela
temporária; SQL concatenado; `float`; restrição por nível só na tela.

## R-02 — Diferenças em relação ao antigo (todas aprovadas na spec)

| Antigo | Novo | Onde foi aprovado |
|---|---|---|
| Entradas em créditos (crédito vendido por vendedor) | Depósitos online dos clientes, só Admin e Supervisor, somados às entradas; saques pagos nas saídas | Clarifications |
| Acumuladão nas somas | Não existe no novo | spec (não há o recurso) |
| Apostas online misturadas | Seção nova Caixa online | Clarifications |
| Lançamentos, retiradas e % do gerente reais | Fake zerado com selo "em breve" | Clarifications (`/speckit-clarify`) |
| Combinação de risco só Admin/Supervisor, sob demanda | Todos menos Vendedor, por rede, atualização automática e aviso | Clarifications |
| Atualização manual | Automática a cada 1 min com a aba visível | Clarifications |
| Menu horizontal de ícones no desktop | Menu lateral (gaveta no celular) | Input da spec, aprovado |
| Botão de créditos do Gerente e aviso do Admin | Removidos | Clarifications |
| Visual em listas | Cartões e gráficos (Princípio VIII, diferença aprovada) | Clarifications |

## R-03 — Alcance por nível

**Decisão**: serviço `AlcanceDashboard` devolve os ids dos vendedores alcançados (`null` = banca
toda para o Admin). Supervisor e Gerente: `ids_sub_hierarquia` **incluindo usuários excluídos
logicamente** (consulta própria com `withTrashed`), para que as vendas de vendedor excluído no dia
continuem na rede (edge case da spec). Vendedor: `[id]`.

**Rationale**: o filtro fica num lugar só e entra em todas as consultas como `whereIn(usuarios_id)`,
aplicado no servidor (FR-008, FR-012). `Usuarios::ids_sub_hierarquia` não traz excluídos e não é
alterado (Princípio IV): o serviço novo faz a mesma subida com `withTrashed`.

**Alternativas**: juntar `usuarios` duas vezes como o antigo (rejeitado: consulta mais pesada e
regra repetida em cada seção).

## R-04 — Período e fuso

**Decisão**: "dia atual" = de `FusoSistema::inicio_do_dia()` até o fim do dia em Brasília,
convertidos para UTC (o banco é UTC). Combinação de risco e Apostas por um jogo: de
`inicio_do_dia() − 1 dia` até agora. O dia entra na chave do cache.

## R-05 — Definição dos números (bcmath, sem float)

Base comum: `apostas` com `situacao = Ativa` e `created_at` no período.
**Balcão** = `usuarios_id` no alcance e `clientes_id` nulo. **Online** = `clientes_id` não nulo.

| Número | Regra |
|---|---|
| Entradas | `SUM(valor)` |
| Em aberto / Premiadas / Perdidas | `SUM(valor)` por `resultado` Aguardando / Vencedor / Perdedor |
| Saídas | `SUM(premio)` das Vencedor + `SUM(valor)` das Devolvido |
| Comissão do vendedor | `SUM(comissao)` |
| Canceladas | `COUNT` e `SUM(valor)` com `situacao = Cancelada` no período |
| Depósitos online (Admin/Supervisor) | `depositos` Aprovado com `pago_em` no período: `SUM(valor)`; entram nas Entradas |
| Saques pagos (Admin/Supervisor) | `saques` Pago com `pago_em` no período: `SUM(valor)`; entram nas Saídas |
| Comissão do gerente | por gerente: `percentual × (entradas − saídas − comissão)` dos vendedores dele, zero se ≤ 0; percentual fake = `0.00` (R-10) |
| Saldo líquido | Entradas − Saídas − Comissão vendedor − Comissão gerente + Lançamentos − Retiradas |
| Caixa online — Encerramentos | `apostas_encerramentos` Registrado ou Creditado com `decidido_em` no período: `SUM(valor)` |
| Caixa online — Entradas | apostas online com `forma_pagamento = Saldo` (promoção fica fora, FR-035) |
| Saldo líquido online | Entradas − Saídas − Encerramentos (Clarifications) |

As somas vêm do banco como string decimal e são combinadas com `bcadd`/`bcsub`/`bcmul` (2 casas,
como em `RelatorioCassino`).

## R-06 — Situação dos jogos e Apostas por um jogo

**Decisão**: Situação dos jogos = `COUNT(DISTINCT confrontos.id)` por situação, a partir de
`apostas_palpites` ativos (`situacao = Ativo`) das apostas do período; Bloqueado soma em
Aguardando. Palpites de especiais (`confrontos_id` nulo) não entram nessa seção.

Apostas por um jogo = apostas abertas (Ativa + Aguardando) de ontem e hoje cujos **itens ainda não
decididos** somam exatamente 1, onde item = jogo distinto com palpite ativo em confronto Aguardando
ou Bloqueado, ou palpite de especial com `resultado` Aguardando. Blocos do Criar Aposta (vários
palpites no mesmo jogo) contam como um jogo. Ordenado por prêmio, limite 50.

## R-07 — Combinação de risco numa consulta

**Decisão**: uma consulta com subconsulta agregada, sem tabela temporária:

1. Subconsulta por aposta aberta do período e do alcance: assinatura =
   `GROUP_CONCAT(DISTINCT CONCAT(COALESCE(confrontos_id, CONCAT('e', especiais_id)), ':', codigo_cotacao, ':', COALESCE(confrontos_jogadores_id, ''), ':', COALESCE(especiais_opcoes_id, '')) ORDER BY ... SEPARATOR '|')`,
   só palpites ativos, com pelo menos um jogo não encerrado.
2. Consulta externa: `GROUP BY assinatura HAVING COUNT(*) >= 2`, com `COUNT`, `SUM(valor)`,
   `SUM(premio)`, `GROUP_CONCAT(codigo)` e `MIN(primeiro horário)`; ordenado pelo prêmio somado.
3. Nomes dos jogos e opções montados em PHP só para as linhas devolvidas (máx. 100), pelos ids da
   assinatura, com `NomesCotacoes::nome`.

A chave pública da combinação é `sha1(assinatura)`, usada pelo aviso (R-11) e pela tela (R-12).
Toda a SQL é fixa (`selectRaw` sem dados da requisição); os filtros entram por bindings.

**Alternativas**: tabela temporária como no antigo (rejeitado: escrita a cada consulta); montar em
PHP todas as apostas (rejeitado: memória e tempo com volume alto).

## R-08 — Desempenho e cache

**Decisão**:

- Uma rota por seção (`GET /dashboard/<secao>`), pedidas em paralelo pela página.
- `Cache::remember("dashboard:{secao}:{usuarios_id}:{dia}", 60 s)`; `?atualizar=1` faz
  `Cache::forget` antes (FR-042). Cache store atual (`database`).
- Índices novos: `apostas (clientes_id, situacao, created_at)` já existe; criar
  `apostas (situacao, created_at)` para o Admin (sem filtro de vendedor),
  `depositos (situacao, pago_em)`, `saques (situacao, pago_em)` e
  `apostas_encerramentos (situacao, decidido_em)`.
- Rankings e combinação limitados (10, 50, 100 linhas): respeitam o máximo de 100 da Constituição;
  não são listagens paginadas.

**Rationale**: o cache curto absorve a atualização automática de 1 min de vários usuários (SC-004).

## R-09 — Permissões (Princípios I e VI)

**Decisão**: `Funcao::PERMISSOES_DASHBOARD` com as 9 permissões `dashboard.ver_*` (nome aprovado na
spec). Em `pode_usar`:

- Vendedor: só `ver_apurado`, `ver_situacao_jogos`, `ver_apostas`, `ver_apostas_um_jogo`;
- Gerente: todas menos `ver_caixa_online`;
- Admin e Supervisor: todas.

`permissoes_padrao` passa a incluir as de dashboard filtradas por `pode_usar` (o Supervisor recebe
todas; o Vendedor, as 4 dele). Seeder `DashboardSeeder` no padrão do `CassinoSeeder` (cria as
permissões e dá aos usuários existentes as do seu nível). Controller usa `permissao_cliente` (trait
`GarantirPermissaoCliente`), que já combina permissão direta e `pode_usar`.

## R-10 — Dados fake (Princípio IX)

**Decisão**: em `App\Fakes\DadosFake` (local único já usado):

- `caixa_usuario(): array{lancamentos: string, retiradas: string}` → `'0.00'` e `'0.00'`;
- `percentual_comissao_gerente(): string` → `'0.00'`.

A resposta do Apurado leva `provisorios: ['lancamentos', 'retiradas', 'comissao_gerente']` para a
tela mostrar o selo "em breve". Substituídos pela spec de caixa, que remove os dois métodos.
Nenhum fake entra em operação com dinheiro (só exibição).

## R-11 — Aviso de combinação nova no WhatsApp (Admin)

**Decisão**:

- Tabela `apostas_combinacoes_risco` (prefixo da principal `apostas`): uma linha por combinação
  avisada no dia (chave única `dia` + `chave`). Guarda descrição, contagens, situação do envio e
  tentativas (data-model §2).
- Comando `apostas:avisar_combinacoes_risco`, agendado `everyMinute()->withoutOverlapping(2)`: roda
  `CombinacoesRisco` para a banca toda (sem cache), grava as chaves novas e manda **uma** mensagem
  com todas as novas do minuto; falha → `tentativas + 1`, nova tentativa no minuto seguinte, até 3,
  depois `Falhou`. Sem telefone/chave ou aviso desligado → `Dispensado` (não envia depois).
- Envio por `Http::timeout(30)->get('https://api.callmebot.com/whatsapp.php', [phone, text, apikey])`
  num serviço `AvisosWhatsapp`, mesmo serviço usado no deploy (spec 013), com telefone e chave das
  configurações.
- Combinação que some e volta no mesmo dia não gera nova linha (chave já existe).

## R-12 — Aviso na tela do painel

**Decisão**: a página compara as chaves de cada atualização com as já vistas. Primeira carga do dia
marca tudo como visto, sem aviso; chave nova → balão (sweetalert2 em toast, já instalado), som curto
e destaque da linha. As chaves vistas ficam em `localStorage` por usuário e dia (conveniência do
aparelho; sem ele, só em memória). Silenciar o som também fica no `localStorage`.

**Rationale**: o "novo" é por rede (a combinação pode existir na banca e só depois na rede do
gerente); a página já recebe a lista da rede a cada minuto, então não precisa de canal novo.

**Alternativas**: tempo real por WebSocket/Echo (rejeitado: o painel não usa e a atualização de 1
min atende a SC-009); push (fora do escopo).

## R-13 — Configurações do aviso

**Decisão**: 3 colunas novas em `configuracoes`: `aviso_combinacao_whatsapp` (bool, padrão falso),
`telefone_whatsapp_admin` (string, só dígitos com DDI) e `chave_callmebot` (texto, cast
`encrypted`, em `$hidden`, como os tokens de `GatewaysPagamento`). Rota `GET/PUT
/configuracoes/avisos-whatsapp`, permissão `configuracoes.editar`, no padrão do
`ConfiguracoesCassinoController`. A resposta mostra `chave_callmebot_definida` e só os 4 últimos
caracteres. Sem tela nesta spec (spec de configurações).

## R-14 — Dados do usuário do painel e destino do login

**Decisão**: rota `GET /auth/eu` (token do painel) devolve `id`, `nome`, `login`, `funcao` e
`secoes` (as permissões `dashboard.ver_*` que ele pode usar). O `useSessao` passa a buscar esses
dados no login de usuário (como já faz com cliente e afiliado) e guarda `nome` e `funcao` na sessão.
O `AuthModal` leva Admin, Supervisor e Gerente para `/painel`; o Vendedor fica. O `Header` faz a
saudação do usuário do painel virar link para `/painel`.

## R-15 — Frontend do painel

**Decisão**:

- `/painel` passa a renderizar a página `Dashboard` (a página `Panel` com o botão do Mercado Pago é
  removida, FR-007). O `PublicLayout` continua como layout (só providers); a página monta o
  `PanelShell` (barra superior com logo, menu, Apostar e Sair; menu lateral/gaveta), no visual do
  `screens/adm/components/header`.
- Guarda no cliente: sem sessão de usuário → `router.visit('/')`; o servidor também recusa as rotas
  da API sem token/permissão.
- Gráficos com **Chart.js 4** (dependência nova, aprovada na spec), importado com `import()` dinâmico
  dentro do componente `ChartCanvas`, para virar um pedaço separado do build e não pesar a tela
  principal (FR-043, SC-007). Sem `react-chartjs-2`: um componente próprio com `useRef` + `useEffect`
  basta e evita outra dependência.
- Hook `useSecaoDashboard(secao)`: busca, mantém os dados antigos durante a atualização, `atualizar()`
  manual com `?atualizar=1`, intervalo de 60 s só com `document.visibilityState === 'visible'`.
- Ver bilhete reutiliza o `TicketModal` existente com `GET /publico/bilhetes/{codigo}`.
- Nomes das opções (Opções mais apostadas e combinação) vêm prontos do backend (`NomesCotacoes`).

## R-16 — Dependências

| Pacote | Versão | Motivo |
|---|---|---|
| `chart.js` | ^4.4 | gráficos de barras, rosca e barras horizontais (decisão do responsável) |

Nenhum pacote PHP novo. CallMeBot via `Http` do Laravel.
