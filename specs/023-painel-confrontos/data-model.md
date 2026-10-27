# Data Model: Painel — Confrontos

**Feature**: `023-painel-confrontos` | **Date**: 2026-10-27

Nenhuma tabela nova e nenhuma coluna nova. Esta spec lê as tabelas das specs 003, 004, 009 e 018 e
muda só um índice, uma permissão e regras de validação.

## 1. Migrations

| Migration | O que faz |
|---|---|
| `2026_10_27_000007_add_indice_data_inicio_confrontos_table.php` | índice `confrontos.data_inicio` (a lista do painel filtra por dia sem `situacao`; o índice atual começa por `situacao`). `down` remove o índice |
| `2026_10_27_000008_restringir_lancar_resultado_admin.php` | retira `confrontos.lancar_resultado` de todo usuário cuja função não seja Admin (`revokePermissionTo`), com o cache de permissões limpo antes e depois, no padrão de `2026_10_24_000003_criar_permissao_restaurar_apostas`. `down` devolve a permissão aos Supervisores |

## 2. Permissões (`app/Enums/Funcao.php`)

- `pode_usar('confrontos.lancar_resultado')` → só `Funcao::Admin` (regra junto da de
  `gateway_pagamento.vincular`). Com isso, `permissoes_padrao()` do Supervisor deixa de incluí-la,
  e `PermissionsPanel` não a oferece ao Supervisor.
- As demais continuam: Admin e Supervisor todas as de `PERMISSOES_CONFRONTOS`; Gerente só as de
  `PERMISSOES_CONFRONTOS_GERENTE` (`confrontos.listar`, `campeonatos.listar`, os três
  `*_nao_permitidos.gerenciar`, porcentagens de vendedores, de campeonatos e de jogos); Vendedor
  nenhuma.

## 3. Entidades lidas e regras

### `confrontos` (spec 003)

- Lista do painel (`ListagemConfrontosPainel`): não excluídos; filtros `busca` (times + nome do
  campeonato), `campeonatos_id`, `ativo`, `manual`, `situacao`, `esporte`, `dia`/`data`, `tipo`.
- Exclusão manual (R-16): recusada se existir `apostas_palpites` com `confrontos_id` do jogo,
  `situacao = Ativo`, em `apostas` com `situacao` em `Ativa`, `Pendente` ou `Em análise` ("aposta
  viva"; `Cancelada`, `Recusada` e `Expirada` não contam).
- `apostas_em_aberto` (R-21): quantidade de apostas `Ativa` distintas com palpite `Ativo` e
  `resultado = Aguardando` no jogo, no alcance de quem consulta.
- Lançar resultado (R-21): só com `data_inicio <= agora`.
- Ordem: `data_inicio`, `time_casa`.

### `confrontos_ao_vivo` (spec 003)

- "Ao vivo agora" (R-04): `confrontos_id` do jogo, não excluído, `situacao` ∈ {`1 tempo`,
  `Intervalo`, `2 tempo`}, `ultima_atualizacao_em ≥ agora − minutos_permanencia_ao_vivo`.

### `campeonatos` (spec 003)

- `quantidade_jogos`: confrontos não excluídos, `ativo`, `situacao = Aguardando`,
  `data_inicio > agora`.
- `total_jogos`: todos os confrontos não excluídos (aviso da exclusão).
- Ordem: `favorito desc`, `nome`.
- Exclusão manual: recusada se algum jogo dele cair na regra de exclusão de `confrontos`.

### Não permitidos (spec 003)

`confrontos_nao_permitidos`, `confrontos_ao_vivo_nao_permitidos`, `campeonatos_nao_permitidos`
(`alvo`, `usuarios_id`, `clientes_id`):

- `nao_permitido` (já existe): vale para quem consulta (`AlcanceHierarquia::nao_permitidos_do_usuario`).
- `nao_permitido_proprio`: id do registro com `alvo = Vendedores` e `usuarios_id` = quem consulta
  (pré-jogo e ao vivo; no campeonato, só um).
- `redes_desativadas` (só Admin e Supervisor): quantidade de `usuarios_id` distintos com
  `alvo = Vendedores` no item.

### `apostas` + `apostas_palpites` (specs 004, 009, 018)

Base comum (R-09): `apostas.situacao = Ativa`, palpite `situacao = Ativo`, palpite do jogo
(`apostas_palpites.confrontos_id`), alcance de `ListagemApostas::aplicar_alcance`.

| Campo calculado | Regra |
|---|---|
| tipo da aposta | simples = 1 palpite `Ativo` na aposta; múltipla = mais de 1 (`COUNT` por `apostas_id`) |
| valor | `apostas.valor` inteiro, por aposta (a múltipla conta inteira em cada jogo) |
| possível retorno | `apostas.premio` inteiro, por aposta |
| opção | `codigo_cotacao` + `confrontos_jogadores_id` (nome do jogador quando houver) |
| em aberto (Pendentes) | palpite com `resultado = Aguardando` |

Somas em `DECIMAL` no SQL e devolvidas com 2 casas (texto); nenhuma conta em ponto flutuante.

### `confrontos_resultados` + `confrontos_gols` (spec 009)

- Gravados só por `ImportacaoResultados::gravar_resultado` (carga ou lançamento manual).
- `origem` exibida: `origem` da operação mais recente de `operacoes_apuracao` com o `confrontos_id`
  do jogo; sem operação, "Carga de resultados" para jogo do provedor e "Lançamento manual" para jogo
  manual.

## 4. Validações alteradas

### `ConfrontosResultadosRequest` (spec 009, alterado — R-12)

| Campo | Antes | Depois |
|---|---|---|
| `placar_casa`, `placar_fora` | obrigatórios, 0 a 99 | iguais |
| `gols_primeiro_tempo_casa/fora`, `gols_segundo_tempo_casa/fora` | opcionais, 0 a 99 | **obrigatórios**, 0 a 99 |
| `escanteios_casa/fora` | opcionais, 0 a 99 | **obrigatórios**, 0 a 99 |
| soma | — | `placar_casa + placar_fora` = soma dos quatro campos dos tempos; erro em `placar_casa`: "A soma dos gols dos times não bate com os gols dos tempos." |
| `gols.*` | opcionais | iguais |

- O controller deixa de recusar jogos do provedor (sai a mensagem "O resultado de jogos do provedor
  vem da carga automática.") e passa a recusar jogo que não começou: "O resultado só pode ser
  lançado depois do início do jogo." (campo `confronto`).

### Filtros da lista (`FiltrosConfrontosPainelRequest`, novo)

| Parâmetro | Regra | Mensagem |
|---|---|---|
| `dia` | `ontem`, `hoje`, `amanha`, `depois_de_amanha` | "O dia deve ser ontem, hoje, amanha ou depois_de_amanha." |
| `data` | `Y-m-d`, de hoje −30 a hoje +2 no `fuso_horario` | "A data deve estar entre os últimos 30 dias e depois de amanhã." |
| `tipo` | `todos`, `pre_jogo`, `ao_vivo` | "O tipo deve ser todos, pre_jogo ou ao_vivo." |
| os atuais | `busca`, `campeonatos_id`, `ativo`, `manual`, `esporte`, `situacao`, `fuso_horario`, `por_pagina` | as mensagens atuais do `ConfrontosController@index` |
