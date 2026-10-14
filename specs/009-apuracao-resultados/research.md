# Research: Apuração de resultados e cancelamento de jogos

**Feature**: [spec.md](spec.md) | **Data**: 2026-10-14

## Sistema antigo (Princípio VII)

Pasta `C:\Users\wedso\OneDrive\Área de Trabalho\projetos\wssports.bet`, consultada só para leitura.

| Arquivo | O que faz |
|---|---|
| `app/Console/Commands/ResultadosCommand.php` (`fonte:resultados`, a cada 13 min) | `GET /api/resultados`; grava em `confrontos` placar, escanteios, gols de cada tempo e situação; grava os gols por jogador em `gols` (`updateOrCreate` por jogo + minuto + tipo do evento); chama `OperacaoController::operacao()` para todo jogo recebido |
| `app/Http/Controllers/OperacaoController.php` (2.374 linhas) | `operacao($confrontos_id)`: pega todos os bilhetes `Ativa` com palpite no jogo e reavalia **todos** os palpites deles (inclusive de outros jogos); um método por código (`odd1` … `odd323`); jogador por nome contido no texto do palpite e tipo (Qualquer Momento, Primeiro, Último); especial pela opção vencedora; grava só `status` e `winner_at` |
| `app/Console/Commands/CreditosCommand.php` (`apostas:premiadas`, a cada 5 min) | Bilhetes pagos com Crédito/Bônus dos últimos 7 dias: `Vencedor` sem baixa e com 5 min desde `winner_at` → credita `premio + valor_acrescido` (saldo ou saldo de bônus), transação `Prêmio`, `baixa = Sim`; não `Vencedor` com baixa → reversão |
| `app/Console/Commands/CanceladosCommand.php` (`fonte:cancelados`, a cada 15 min) | Com `remover_confronto_automatico = Sim` (padrão): `GET /api/cancelados` (`[{"fonte_id": [ids]}]`); apaga os palpites do jogo, recalcula o prêmio pela trava do gerente e chama `operacao()`; sem palpite restante marca `Vencedor` com comissão 0 |
| `app/Console/Kernel.php` | Agenda dos quatro comandos acima |
| `app/Models/Gols.php`, migration `configs` | Tabela de gols; `remover_confronto_automatico` enum `Sim`/`Não`, padrão `Sim` |

### Defeitos do antigo corrigidos (aprovados na spec, Clarifications)

1. `CanceladosCommand` apaga com `Palpites::where('confrontos_id', …)->delete()` o palpite do jogo
   em **todos** os bilhetes, inclusive os já decididos.
2. A reversão do `CreditosCommand` apaga **todas** as transações `Prêmio` do cliente e faz
   `saldo = saldo_anterior`, perdendo qualquer movimentação feita depois.
3. `odd164`/`odd165` apagam o palpite e gravam prêmio e comissão **dentro da regra de avaliação**,
   com SQL concatenado.
4. `operacao()` reprocessa todos os bilhetes de todos os jogos a cada 13 min e consulta `gols` uma
   vez por palpite.
5. Com todos os jogos cancelados, o bilhete só é marcado `Vencedor`; a devolução depende de outro
   comando.
6. A ordem do gol é lida só do primeiro caractere de `"10º Gol"` (`Str::substr(…, 0, 1)`), errando
   o "último gol" a partir do 10º.

## Decisões

### R-01 — Resultado do jogo em tabela própria (`confrontos_resultados`)

- **Decisão**: uma linha por jogo com placar final, gols de cada tempo, escanteios, a situação
  recebida na carga de resultados e uma assinatura (SHA-256) do resultado + gols. `confrontos`
  recebe só a situação (`Encerrado`) para a grade, como no antigo.
- **Motivo**: a carga dos confrontos (spec 003) regrava `confrontos.situacao` a cada 5 min; a
  apuração não pode depender de um campo que outra carga sobrescreve. A assinatura detecta mudança
  com uma comparação (FR-003).
- **Alternativas**: colunas em `confrontos` (como no antigo) — misturam dados da grade com o
  resultado e ficam sujeitas à outra carga; comparar campo a campo — mais código e esquece os gols.

### R-02 — Gols por jogador (`confrontos_gols`)

- **Decisão**: uma linha por gol com `ordem` (número do gol no jogo, lido do número inteiro de
  `"10º Gol"`), nome, minuto e tempo de jogo; única por (`confrontos_id`, `ordem`). Cada carga
  sincroniza: atualiza ou restaura as ordens recebidas e remove (soft delete) as que sumiram.
- **Motivo**: o provedor manda a lista inteira de gols do jogo a cada carga; a ordem identifica o
  gol e resolve "Primeiro" (ordem 1) e "Último" (maior ordem). Corrige o defeito 6.

### R-03 — Quando apurar (FR-004, clarify)

- **Decisão**: palpites de jogos são decididos só com `confrontos_resultados.situacao = Encerrado`
  e placar final dos dois times. Escanteios ausentes deixam os mercados de escanteio (`odd166` a
  `odd224`) `Aguardando`; gols de tempo ausentes deixam os mercados de tempo `Aguardando`.
- **Motivo**: decisão do responsável (opção A): não pagar com placar parcial.

### R-04 — Regras dos mercados (`App\Support\ResultadosCotacao`)

- **Decisão**: classe única com uma tabela `código → [regra, parâmetros]`, agrupada pelos mesmos
  grupos de `MercadosCotacao`. As regras são poucas funções parametrizadas que recebem um objeto de
  placar (`PlacarConfronto`, só leitura) e devolvem `Vencedor`, `Perdedor`, `Devolvido` ou
  `Aguardando`:

  | Regra | Parâmetros | Códigos (grupo) |
  |---|---|---|
  | `vencedor` | período (jogo, 1º, 2º), lado (casa, empate, fora) | 1-3, 129-134 |
  | `dupla_chance` | período, lados aceitos | 10-14 |
  | `ambas_marcam` | período, sim/não | 4-9 |
  | `ambas_marcam_tempos` | 1º sim/não, 2º sim/não | 251-254 |
  | `handicap` | período, lado, linha (-4.5 … +4.5) | 15-17, 147-163, 225-244 |
  | `intervalo_final` | lado no 1º tempo, lado no final | 18-26 |
  | `placar_exato` | período, gols casa, gols fora | 27-84 |
  | `total_gols` | período, comparação (=, ≥, ≤), valor | 85-124 |
  | `par_impar` | período ou time, par/ímpar | 125-128, 245-250 |
  | `vencedor_ambas` | período, lado, ambas sim/não | 135-140, 255-260 |
  | `vencedor_total` | lado, acima/abaixo de 2.5 | 141-146 |
  | `empate_anula` | lado | 164-165 (empate → `Devolvido`) |
  | `escanteios` | comparação (>, <, =, faixa), valores | 166-224 |
  | `tempo_mais_gols` | 1º, 2º, empate | 261-263 |
  | `time_tempo_mais_gols` | time, 1º/2º/empate | 264-269 |
  | `sem_sofrer_gol` | time, sim/não | 270-273 |
  | `margem_vitoria` | lado, margem (1, 2, 3, 4+), empate com gols, sem gols | 274-283 |
  | `total_time` | time, acima/abaixo, linha | 284-323 |

  Cada código reproduz exatamente a expressão do método `oddN` do antigo (a tabela completa entra
  na classe, com o comentário do mercado de `NomesCotacoes`).
- **Motivo**: FR-007 (catálogo por mercado, sem 323 métodos); o mesmo padrão de `MercadosCotacao`
  (`app/Support`, fonte única).
- **Alternativas**: 323 métodos (como o antigo) — o que a spec pede para evitar; regras no banco —
  sem ganho, as regras não são configuráveis.

#### Divergências encontradas entre o nome e a regra do antigo (mantidas como no antigo)

| Código | Nome (`NomesCotacoes`) | Regra do antigo |
|---|---|---|
| `odd114` | "Mais de 3 gols - 2º tempo" | total do 2º tempo **igual a** 3 |
| `odd245`, `odd247` | "Casa par", "Fora par" | 0 gol **não** conta como par (no total do jogo, `odd127`, e no 2º tempo, `odd250`, 0 conta como par) |

**Decisão do responsável (2026-10-14)**: manter as regras do antigo nos três códigos. Trocar no
futuro é só mudar o parâmetro desses códigos na tabela.

### R-05 — Palpite de jogador

- **Decisão**: compara o nome do jogador do palpite (`confrontos_jogadores.nome`, copiado no
  palpite pelo vínculo) com o nome de cada gol do jogo, sem diferenciar maiúsculas, acentos e
  espaços nas pontas. `jogador_tipo`: "Qualquer Momento" (marcou algum), "Primeiro" (gol de ordem
  1), "Último" (gol de maior ordem). Sem gol do jogador → `Perdedor`. Tipo desconhecido → fica
  `Aguardando` e vai para o log.
- **Motivo**: o antigo procurava o nome dentro do texto do palpite; no v2 o palpite guarda o nome
  exato do jogador, então a comparação normalizada é mais segura (evita "Silva" casar com
  "Silvano"). Mesmo comportamento para os nomes iguais.

### R-06 — "Devolvido" pelo cancelamento do palpite pelo sistema

- **Decisão**: o palpite `Devolvido` (empate anula aposta) é cancelado pelo sistema: `situacao =
  Cancelado`, `resultado = Devolvido`, `motivo_cancelamento = Empate anula aposta`, com recálculo e
  histórico pelo fluxo existente (`EdicaoApostas::cancelar_palpite_pelo_sistema`), incluindo a
  regra do bloco (Recalcular ou Anular bloco). Se uma correção desfizer o empate, o sistema
  restaura o palpite (`restaurar_palpite_pelo_sistema`, novo) e aplica o novo resultado.
- **Motivo**: "vale 1,00" é exatamente o que o cancelamento já faz (cotação total, prêmio,
  acréscimo, bloco, assinatura e histórico). Reusar evita uma segunda regra de "1,00" no cálculo.
  O palpite não é apagado (corrige o defeito 3).
- **Alternativas**: palpite `Ativo` com `Devolvido` contando 1,00 no cálculo — exigiria mudar o
  recálculo, os blocos e a assinatura para ignorar o resultado.

### R-07 — Motivo do cancelamento do palpite

- **Decisão**: coluna nova `apostas_palpites.motivo_cancelamento` (enum `MotivoCancelamentoPalpite`:
  `Jogo cancelado`, `Empate anula aposta`); nula nos cancelamentos pelo painel e de categoria
  especial (fluxos existentes não mudam).
- **Motivo**: a reapuração precisa saber quais palpites cancelados ela mesma pode restaurar (só
  `Empate anula aposta`); os de jogo cancelado ficam cancelados (clarify, pergunta 3).

### R-08 — Histórico de edição sem autor

- **Decisão**: `apostas_historico.usuarios_id` e `ip` passam a aceitar nulo ("nulo quando a ação
  foi do sistema", como `clientes_transacoes.usuarios_id`); `cancelar_palpite_pelo_sistema` passa a
  aceitar autor, ip e user agent nulos e aplica a regra do bloco (Anular bloco), como o
  cancelamento pelo painel.
- **Motivo**: a carga é do agendador, sem usuário; inventar um usuário "sistema" criaria um
  registro falso em `usuarios`. O especial cancelado continua passando o autor.

### R-09 — Decisão da aposta (`ApuracaoApostas`)

- **Decisão**: serviço novo, com a aposta travada (`lockForUpdate`) e uma transação por aposta:
  1. reavalia os palpites de jogo `Ativo` e os cancelados com motivo `Empate anula aposta`;
  2. aplica `Devolvido` (cancela) ou desfaz (restaura) conforme R-06;
  3. decide a aposta pelos palpites `Ativo`: algum `Perdedor` → `Perdedor`; algum `Aguardando` →
     `Aguardando`; senão (todos `Vencedor` ou nenhum ativo) → `Vencedor`;
  4. grava `resultado`, `apurada_em` e `liquidacao_pendente` quando o resultado ou o total a pagar
     mudam, com registro em `operacoes_apuracao`;
  5. chama a liquidação (R-10).
  Só apostas `Ativa`. Os especiais usam o resultado gravado pelo encerramento da categoria.
  `EncerramentoEspeciais::decidir_apostas` passa a chamar este serviço, para que apostas mistas
  (especial + jogos) sejam decididas e pagas pela mesma regra.
- **Motivo**: FR-014 a FR-019 e FR-040; uma regra só de decisão para jogos e especiais.

### R-10 — Liquidação idempotente (`LiquidacaoApostas`)

- **Decisão**: `apostas.valor_pago` guarda o líquido já creditado; o valor devido é
  `premio + valor_acrescido` se `Vencedor`, senão 0. Na liquidação, com a aposta travada:
  - diferença > 0 → crédito (origem `Prêmio`, ou `Devolução de aposta` quando nenhum palpite ficou
    ativo), só se já passou a janela desde `apurada_em`;
  - diferença < 0 → débito na hora (origem `Estorno`, `permitir_negativo`);
  - sempre por `SaldoClientes`, com `referencia_id` = id da aposta; `valor_pago` e
    `liquidacao_pendente` atualizados na mesma transação.
  Carteira: `Saldo` para forma de pagamento Saldo; `Promoção esportes` para Promoção esportes.
  Aposta de vendedor (`Dinheiro`) ou sem cliente nunca liquida (`liquidacao_pendente` falso).
- **Motivo**: FR-020 a FR-029; idempotente por construção (só movimenta a diferença), corrige o
  defeito 2; o débito não espera a janela para não deixar prêmio errado disponível.
- **Alternativas**: marca booleana de "pago" (como `baixa` no antigo) — não resolve o ajuste de
  valor (FR-029).

### R-11 — Janela de segurança e comando de pagamento

- **Decisão**: `configuracoes.minutos_pagamento_premio` (padrão 5). Comando
  `apostas:liquidar_premios` a cada minuto processa `liquidacao_pendente = true` com
  `apurada_em <= agora - janela`, aposta por aposta.
- **Motivo**: a apuração roda a cada 5 min; sem o comando, o prêmio esperaria a próxima carga.

### R-12 — Novo valor de origem no extrato

- **Decisão**: `OrigemTransacao::DevoluçãoAposta = 'Devolução de aposta'`.
- **Motivo**: o cliente distingue no extrato o prêmio da devolução do valor apostado (FR-035).

### R-13 — Registro de operações (`operacoes_apuracao`)

- **Decisão**: tabela própria, só inserção, com tipo, origem, jogo, aposta, palpite, cliente,
  carteira, transação do cliente, `valor`, `antes`/`depois` (JSON) e motivo. Gravação por um
  serviço (`RegistroApuracao`) chamado dentro da mesma transação da operação.
- **Motivo**: FR-036 a FR-038; JSON para guardar placar, resultado ou saldo conforme o tipo, sem
  uma coluna para cada caso. Sem tela nesta spec.

### R-14 — Cargas, rotas do provedor e agenda

- **Decisão**:
  - `ProvedorCotacoes::buscar_resultados()` (`GET {url_pre_jogo}/resultados`) e
    `buscar_cancelados()` (`GET {url_pre_jogo}/cancelados`), tempos limite novos em
    `config/services.php` (180 s e 60 s);
  - validação em `ValidacaoCargaProvedor` (`validar_resultados`, `validar_cancelados`), mesmo
    padrão: lista ou falha da carga; item inválido ignorado com motivo;
  - comandos `confrontos_resultados:importar` (`3-59/5`) e `confrontos_cancelados:importar`
    (`4-59/5`), com `ExecutarCargaPreJogo` (somente cassino, log, resumo), `runInBackground` e
    `withoutOverlapping(10)`; `apostas:liquidar_premios` a cada minuto; `apostas:reapurar
    {confronto}` manual.
- **Motivo**: FR-001, FR-005, FR-030, FR-041 a FR-043; minutos escalonados depois de campeonatos
  (:00), confrontos (:01) e cotações (:02).

### R-15 — Cancelamento de jogo (`CancelamentoConfrontos`)

- **Decisão**: para cada código recebido: grava `confrontos.situacao` (`Cancelado` ou `Adiado`,
  pela situação atual do jogo; sem informação, `Cancelado`) e registra a mudança. Com
  `remover_confronto_cancelado` ligado, cancela pelo sistema (motivo `Jogo cancelado`) os palpites
  `Ativo` e `Aguardando` do jogo em apostas `Ativa` com resultado `Aguardando`, e reapura cada
  aposta (R-09), que liquida a devolução quando não sobra palpite. Idempotente: palpite já
  cancelado não é tocado.
- **Motivo**: FR-030 a FR-035; corrige o defeito 1 (só a aposta afetada) e o 5 (devolução real).
- **Observação**: a rota do antigo só manda os códigos, sem dizer se foi cancelado ou adiado; os
  dois têm o mesmo tratamento (clarify, pergunta 1).

### R-16 — Configurações (`configuracoes/apuracao`)

- **Decisão**: colunas `remover_confronto_cancelado` (boolean, padrão verdadeiro) e
  `minutos_pagamento_premio` (0 a 1440, padrão 5) em `configuracoes`; rota `GET`/`PUT
  /api/configuracoes/apuracao` no padrão de `configuracoes/criar-aposta` (controller + request +
  resource, permissão `configuracoes.editar`). Sem tela nesta spec, como na 008.

### R-18 — Resultado de jogo manual (`PUT /api/confrontos/{confronto}/resultado`)

- **Decisão**: rota no grupo de gestão de confrontos (spec 003), controller
  `ConfrontosResultadosController` + request + resource, permissão nova `confrontos.lancar_resultado`
  em `Funcao::PERMISSOES_CONFRONTOS` (fora de `PERMISSOES_CONFRONTOS_GERENTE`, logo só Admin e
  Supervisor) e seeder `ApuracaoSeeder` no padrão do `ApostasSeeder` (cria a permissão e a dá aos
  usuários existentes conforme a função). Só jogo `manual`; o corpo usa os nomes do sistema e a
  gravação reusa o mesmo método de `ImportacaoResultados` (assinatura, gols, situação `Encerrado`,
  registro e apuração), com origem `Lançamento manual`.
- **Motivo**: decisão do responsável na análise; reusar a gravação garante a mesma regra da carga
  automática (FR-045 a FR-047).
- **Alternativas**: tela no painel — fica para spec futura, como as outras configurações.

### R-19 — Proteção da situação na carga de confrontos

- **Decisão**: em `ImportacaoConfrontos`, a coluna `situacao` sai da lista simples de atualização
  do `upsert` e entra como expressão: mantém o valor atual quando ele é `Cancelado`, `Adiado` ou
  `Encerrado` (`IF(situacao IN (...), situacao, VALUES(situacao))`, com os valores vindos de
  `SituacaoConfronto`). As demais colunas continuam iguais.
- **Motivo**: decisão do responsável na análise (FR-044): a carga de confrontos regrava a situação
  a cada 5 min e poderia reabrir para apostas um jogo cancelado.
- **Alternativas**: tirar `situacao` da atualização — impediria a carga de confrontos de mudar a
  situação de Aguardando para Bloqueado e vice-versa.

### R-17 — Desempenho

- **Decisão**: só jogos com assinatura diferente são apurados; as apostas do jogo vêm de
  `apostas_palpites` pelo índice da chave estrangeira `confrontos_id` (já existe), em lotes de 500
  ids; os resultados e gols dos jogos da aposta são carregados uma vez
  por aposta (`whereIn`), sem consulta por palpite (corrige o defeito 4).
- **Verificação**: cenário de massa grande no quickstart (32), com o tempo do resumo dos comandos.
