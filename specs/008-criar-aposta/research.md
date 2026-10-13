# Research: Criar Aposta

**Feature**: [spec.md](spec.md) | **Data**: 2026-10-13

## Sistema antigo (Princípio VII)

O Criar Aposta **não existe** no sistema antigo (`C:\Users\wedso\OneDrive\Área de Trabalho\projetos\wssports.bet`).
A busca por "criar aposta", "bet builder", "mesmo jogo", "same game" e "personaliz" no código (fora
de `vendor`, `node_modules`, `public` e `storage`) não encontrou nada. No antigo, a aposta aceita
**um palpite por jogo** (regra mantida na spec 004, FR-015: "Não é possível cadastrar jogos
repetidos na aposta").

Arquivos de referência visual (Princípio VIII), já usados nas specs 005 e 006: o cupom
(`BetSlip`/`BetSlipItem`), o modal de mercados (`MatchDetailsModal`) e o comprovante
(`TicketModal`, `utils/print.js`, `utils/thermal.js`). O bloco reaproveita esses visuais
(FR-026, diferença aprovada em 2026-10-13).

## Decisões

### R-01 — Bloco como tabela própria (`apostas_blocos`)

- **Decisão**: tabela `apostas_blocos` (um registro por jogo com Criar Aposta na aposta) com a
  cotação do bloco, a cotação vista e a situação (Ativo, Anulado); `apostas_palpites` ganha
  `apostas_blocos_id` (nulo no palpite simples).
- **Motivo**: a cotação do bloco precisa ser gravada (comprovante, assinatura, histórico, apuração
  futura) e muda na liquidação; a situação Anulado precisa ser guardada para a restauração.
- **Alternativas**: (a) só uma coluna `bloco` nos palpites e a cotação recalculada sempre — perde a
  cotação vista e exige o desconto em todo lugar; (b) JSON na aposta — sem integridade e difícil de
  consultar na apuração.

### R-02 — Índice único de `apostas_palpites`

- **Decisão**: trocar o único `(apostas_id, confrontos_id)` por um índice simples. A unicidade
  passa a ser garantida pelas regras: fora do bloco, um palpite por jogo (FR-001); dentro dele, um
  por mercado (FR-005). O único `(apostas_id, especiais_id)` não muda.
- **Motivo**: o bloco tem vários palpites do mesmo confronto.
- **Alternativa**: único em `(apostas_id, confrontos_id, codigo_cotacao, confrontos_jogadores_id)` —
  no MySQL o NULL de `confrontos_jogadores_id` não participa da unicidade, então não protege nada a
  mais do que as regras.

### R-03 — Pedido: palpites planos + `blocos` com a cotação vista

- **Decisão**: o corpo da aposta continua com a lista `palpites`; dois ou mais palpites do mesmo
  `confrontos_id` formam o bloco. Um campo novo `blocos` (lista de `{confrontos_id,
  cotacao_vista}`) é **obrigatório** para cada jogo repetido e proibido para jogo não repetido.
- **Motivo**: mantém o contrato da spec 004 para quem não usa o Criar Aposta e permite conferir a
  cotação do bloco (que muda se o desconto mudar, FR-009).
- **Alternativa**: lista aninhada (`pernas` com `palpites` dentro) — quebraria o cupom, a validação
  do código e a decisão do ao vivo, que trabalham com a lista plana.

### R-04 — Cálculo da cotação do bloco (`CalculoBlocos`)

- **Decisão**: serviço novo `App\Services\CalculoBlocos`:
  - `cotacao_bloco(cotacoes, desconto)`: produto em bcmath (escala 10) × (100 − desconto) ÷ 100,
    **truncado** em 2 casas e com mínimo 1,00 (FR-007); com um único palpite, a cotação dele sem
    desconto (FR-018);
  - `pernas(palpites, desconto)`: agrupa os palpites pelo bloco e devolve as "pernas" da aposta
    (palpite simples ou bloco), cada uma com a sua cotação. `CalculoPremio` recebe as cotações das
    pernas, sem mudança: o bloco conta como **um jogo** também no acréscimo (FR-011).
- **Motivo**: um só lugar para o cálculo, usado na criação, na decisão do ao vivo, na validação do
  código e na edição.
- **Truncar x arredondar**: as cotações exibidas são arredondadas (`CalculoCotacoes`), mas a do
  bloco é um valor novo calculado pelo sistema; truncar nunca favorece o apostador (FR-007).

### R-05 — Grupos de mercado e pares redundantes (`MercadosCotacao`)

- **Decisão**: classe nova `App\Support\MercadosCotacao` com:
  - o **grupo** de cada código, igual às categorias de `resources/js/utils/market_groups.js` (as do
    sistema antigo), com chave em `snake_case` (ex.: `vencedor`, `dupla_chance`, `vencedor_1t`);
    código fora das categorias = grupo próprio (o próprio código); jogador = `jogador:<nome>` (o
    mesmo jogador não entra duas vezes, em nenhum tipo);
  - a lista fixa de **pares redundantes** (FR-005a), por grupo, fechada em
    [data-model.md](data-model.md#5-pares-redundantes-fr-005a).
- O backend é a **fonte única**: grupos e pares vão para o frontend nas props da página
  (`configuracoes.criar_aposta`), e o cupom não duplica a regra. `market_groups.js` continua só para
  exibição das abas.
- **Faixas de gols**: "Mais de 1,5" e "Menos de 3,5" estão no mesmo grupo (GOLS MAIS/MENOS) e não
  podem ser combinados; registrado como limitação aceita no clarify.

### R-06 — Configurações por público

- **Decisão**: `Apostador` ganha `criar_aposta_habilitado()`, `desconto_criar_aposta()`,
  `quantidade_minima_criar_aposta()` e `quantidade_maxima_criar_aposta()`: vendedor lê de
  `usuarios_configuracoes`; cliente e visitante, de `configuracoes` (padrão de
  `cotacao_maxima_ao_vivo`). A liquidação vem sempre de `configuracoes`.
- **Edição**: as gerais por rota nova `GET/PUT /api/configuracoes/criar-aposta` (permissão
  `configuracoes.editar`, mesmo padrão de `configuracoes/pagamentos`); as do vendedor pelo fluxo
  existente `PATCH /api/usuarios-configuracoes` (campos novos em `CAMPOS` e no request).
- **Máximo**: o máximo de palpites por bloco fica entre 2 e 20 (o máximo de palpites da aposta é
  50 e a quantidade máxima de opções padrão é 20).

### R-07 — Valores gravados na aposta

- **Decisão**: `apostas.desconto_criar_aposta` e `apostas.liquidacao_criar_aposta` gravados na
  confirmação (nulos quando a aposta não tem bloco), como o multiplicador e o prêmio máximo
  (FR-015). Edição e decisão do ao vivo usam os gravados.
- **Validação do código**: o visitante gera o bloco com o desconto geral; na validação, o
  `preparar` recalcula com o desconto e a chave do **vendedor que valida** e grava os dele.

### R-08 — Limite por confronto com bloco

- **Decisão**: `CriacaoApostas::valor_apostado` passa a somar o valor de cada aposta **uma vez por
  jogo** (subconsulta com `DISTINCT apostas_id, jogo`), e `garantir_limite_por_confronto` usa os
  ids sem repetição (FR-012).
- **Motivo**: hoje a soma faz `JOIN` com os palpites; um bloco de 3 palpites contaria o valor 3
  vezes.

### R-09 — Mudança de cotação com bloco

- **Decisão**: `ConferenciaCotacoes::alteracoes` compara cada palpite (como hoje) e, para cada
  bloco, a `cotacao_vista` enviada em `blocos` com a cotação calculada agora; diferença fora da
  preferência entra no 409 com `bloco: true` e o `confrontos_id`. O frontend atualiza a cotação do
  palpite (por `indice`) ou do bloco (por `confrontos_id`).

### R-10 — Liquidação na edição (`EdicaoApostas`)

- **Decisão**:
  - **Recalcular**: cancela só o palpite; o bloco é recalculado com os palpites ativos e o desconto
    gravado (um único restante = cotação dele, sem desconto); bloco sem palpite ativo deixa de
    contar.
  - **Anular bloco**: cancela todos os palpites ativos do bloco, que fica `Anulado` e deixa de
    contar (equivale a 1,00). Aposta só com o bloco: continua Ativa com cotação total 1,00 e prêmio
    igual ao valor (FR-019); por isso a trava "último palpite ativo" não se aplica ao palpite de um
    bloco com "Anular bloco".
  - **Restaurar** (Anular bloco): restaura todos os palpites do bloco e o bloco volta a `Ativo`.
  - O histórico registra uma linha por ação, no palpite clicado (como hoje).
- `cancelar_palpite_pelo_sistema` (especiais) não muda: especial nunca está em bloco.

### R-11 — Assinatura

- **Decisão**: `AssinaturaApostas` inclui `blocos` (id, confronto, cotação, situação) e o
  `apostas_blocos_id` de cada palpite **só quando a aposta tem bloco**. As apostas já gravadas
  continuam com a mesma assinatura.

### R-12 — Cupom no frontend

- **Decisão**:
  - o cupom guarda os palpites como hoje (formato 1, sem migração do que está salvo no aparelho);
    vários palpites do mesmo jogo pré-jogo formam o bloco;
  - ao escolher uma cotação de um jogo que já está no cupom: mesmo palpite → remove; **mesmo
    grupo** → troca (como hoje); grupo diferente → **soma ao bloco**, se o Criar Aposta estiver
    habilitado, o jogo for do pré-jogo, não passar do máximo e o par não for redundante; senão,
    troca como hoje (desligado ou ao vivo) ou avisa (máximo, par redundante);
  - vale também para as 4 cotações da listagem (odd1 a odd4): "Casa" depois de "Ambas marcam" do
    mesmo jogo passa a formar um bloco;
  - a estimativa (`money.js`) calcula as pernas com o desconto das props, espelhando o backend;
  - o comprovante mostra a linha "Criar Aposta" com a cotação do bloco abaixo dos palpites do
    jogo; "Qtde. jogos" passa a ser a quantidade de pernas (`quantidade_jogos`).

### R-13 — Comentários de colunas (constituição 3.3.0)

- Todas as colunas novas desta spec têm `->comment()` em português; as alteradas (índice de
  `apostas_palpites`) não mudam coluna. Os comentários das colunas já existentes ficam para uma spec
  própria (pedido do responsável, adiado).

## Dados fake (Princípio IX)

Nenhum. Todos os dados vêm do backend.
