# Data Model: Confrontos (jogos e cotações do provedor)

**Feature**: `003-confrontos` | **Data**: 2026-10-01 | **Plano**: [plan.md](plan.md)

17 tabelas novas, 1 tabela alterada (`clientes_configuracoes`) e 1 removida
(`clientes_configuracoes_padrao`). Todas as tabelas novas têm `id`, `created_at`, `updated_at` e
`deleted_at` (constituição); datas em UTC. Não existe tabela padrão: os valores iniciais ficam no
valor padrão de cada coluna (MySQL 8.4). Decisões em [research.md](research.md).

**Formato JSON das cotações e regras** (R-01): objeto com só os códigos diferentes de zero, chaves
`odd1`…`odd323` (e `jogador` nas regras), valores numéricos com 2 casas. Ex.:
`{"odd1": 1.40, "odd3": 2.75}`. Código ausente vale 0 (cotação indisponível; porcentagem ou valor
fixo zero; teto sem limite).

## Configurações

### Tabela `configuracoes` (registro único, criado pelo seeder)

| Coluna | Tipo | Padrão | Regras |
|---|---|---|---|
| somente_cassino | boolean | `false` | marcado: cargas e conferência não rodam |
| permitir_entrada_campeonatos | boolean | `true` | campeonato novo nasce ativo (marcado) ou desativado |
| sorteio_ambas_marcam_minimo | decimal(8,2) | 1.30 | sorteio de `odd4` |
| sorteio_ambas_marcam_maximo | decimal(8,2) | 1.50 | ≥ mínimo, senão não sorteia |
| sorteio_ambas_nao_marcam_minimo | decimal(8,2) | 1.45 | sorteio de `odd7` |
| sorteio_ambas_nao_marcam_maximo | decimal(8,2) | 1.55 | |
| ao_vivo_habilitado | boolean | `true` | chave mestra do ao vivo |
| segundos_trava_ao_vivo | unsigned smallint | 15 | tempo sem atualização até travar |
| minutos_permanencia_ao_vivo | unsigned smallint | 5 | tempo até sair da listagem |
| minuto_limite_ao_vivo | unsigned smallint | 95 | visitantes, clientes e gestores |
| cotacao_maxima_ao_vivo | decimal(8,2) | 30.00 | visitantes, clientes e gestores |
| ao_vivo_travado | boolean | `false` | trava geral; muda só pela conferência |
| ao_vivo_travado_em | timestamp null | | quando a trava geral foi acionada |

Sem rota de edição nesta spec (alterado direto no banco).

### Tabela `visitantes_configuracoes` (registro único, criado pelo seeder)

| Coluna | Tipo | Padrão |
|---|---|---|
| esportes_permitidos | json | `["FUTEBOL", "HOQUEI NO GELO", "BAISEBOL"]` |
| apostar_outros_esportes | boolean | `true` |
| ao_vivo_habilitado | boolean | `true` |

### Tabela `usuarios_configuracoes` (uma linha por vendedor)

| Coluna | Tipo | Padrão | Regras |
|---|---|---|---|
| usuarios_id | FK → `usuarios.id`, único | | só vendedores |
| esportes_permitidos | json | `["FUTEBOL", "HOQUEI NO GELO", "BAISEBOL"]` | pelo menos 1 |
| apostar_outros_esportes | boolean | `true` | desmarcado: só `FUTEBOL` |
| ao_vivo_habilitado | boolean | `true` | |
| minuto_limite_ao_vivo | unsigned smallint | 95 | 1 a 130 |
| cotacao_maxima_ao_vivo | decimal(8,2) | 30.00 | ≥ 1,00 |

Criada no cadastro do vendedor como cópia de um colega (R-16); os vendedores existentes recebem a
linha pelo seeder.

### Tabela `clientes_configuracoes` (spec 002, **alterada**)

Mesmas colunas; passam a ter valor padrão (FR-079): `realizar_aposta` `true`, `apostar_ao_vivo`
`true`, `apostar_outros_esportes` `true`, `cancelar_aposta` `false`, `aceita_promocao` `true`,
`bloquear_saque` `false`, `quantidade_minima_opcoes` 1, `quantidade_maxima_opcoes` 20,
`valor_minimo_aposta` 2.00, `valor_maximo_aposta` 1000.00, `premio_maximo` 50000.00,
`valor_maximo_diario` 5000.00, `valor_maximo_saque_diario` 5000.00,
`quantidade_maxima_saques_diaria` 5, `odd_minima` 1.90, `odd_maxima` 30.00, `esportes_permitidos`
`["FUTEBOL", "HOQUEI NO GELO", "BAISEBOL"]`.

### Tabela `clientes_configuracoes_padrao` (spec 002, **removida**)

Apagada pela migration desta spec (R-17).

## Jogos

### Tabela `campeonatos`

| Coluna | Tipo | Regras |
|---|---|---|
| codigo_externo | unsigned bigint null, único | nulo nos manuais |
| nome | varchar(150) | |
| pais | varchar(100) | |
| bandeira | varchar(255) null | |
| ativo | boolean | padrão `true`; a carga só define na criação (`permitir_entrada_campeonatos`) |
| favorito | boolean | padrão `false`; a carga nunca altera |
| manual | boolean | padrão `false` |

**Índices**: (`nome`, `pais`) para a herança (R-04); (`ativo`, `favorito`). Manuais: não pode
haver dois manuais não excluídos com o mesmo `nome` e `pais` (checado na aplicação).

### Tabela `confrontos` (pré-jogo)

| Coluna | Tipo | Regras |
|---|---|---|
| codigo_externo | unsigned bigint null, único | nulo nos manuais |
| campeonatos_id | FK → `campeonatos.id` | manual só em campeonato manual |
| time_casa / time_fora | varchar(150) | |
| escudo_casa / escudo_fora | varchar(255) null | |
| esporte | varchar(50) | como o provedor envia (`FUTEBOL`, `BASQUETE`…) |
| situacao | varchar(20) | `SituacaoConfronto`: `'Aguardando'`, `'Encerrado'`, `'Cancelado'`, `'Adiado'` |
| data_inicio | datetime | UTC |
| ativo | boolean | padrão `true` |
| manual | boolean | padrão `false` |
| odd4_sorteada / odd7_sorteada | boolean | padrão `false`; valor sorteado (R-03) |
| quantidade_cotacoes | unsigned smallint | códigos ≠ 0 + jogadores |
| cotacoes | json | só códigos ≠ 0 |

**Índices**: (`situacao`, `esporte`, `data_inicio`); `campeonatos_id`.

### Tabela `confrontos_jogadores` (antiga `atletas`)

| Coluna | Tipo | Regras |
|---|---|---|
| confrontos_id | FK → `confrontos.id` | |
| codigo_externo | unsigned bigint | |
| nome | varchar(150) | |
| opcao | varchar(60) | ex.: `Marcadores` |
| tipo | varchar(60) | ex.: `Primeiro` |
| odd | decimal(8,2) | > 0 |

**Índice único**: (`confrontos_id`, `codigo_externo`, `tipo`). Jogador que não vem mais na carga
recebe soft delete; voltando, é restaurado (R-02).

### Tabela `confrontos_ao_vivo`

| Coluna | Tipo | Regras |
|---|---|---|
| codigo_externo | unsigned bigint, único | |
| confrontos_id | FK → `confrontos.id` null | confronto da grade com o mesmo `codigo_externo` (FR-046a) |
| campeonatos_id | FK → `campeonatos.id` | |
| time_casa / time_fora | varchar(150) | |
| escudo_casa / escudo_fora | varchar(255) null | |
| esporte | varchar(50) | |
| data_inicio | datetime | UTC |
| placar_casa / placar_fora | unsigned smallint | |
| gols_primeiro_tempo_casa / _fora | unsigned smallint null | |
| gols_segundo_tempo_casa / _fora | unsigned smallint null | |
| escanteios_casa / escanteios_fora | unsigned smallint null | |
| minuto | unsigned smallint | |
| cronometro | varchar(10) | ex.: `45:00` |
| situacao | varchar(20) | `SituacaoAoVivo`: `'1 tempo'`, `'Intervalo'`, `'2 tempo'` |
| cotacoes | json | só códigos ≠ 0 |
| quantidade_cotacoes | unsigned smallint | |
| ultima_atualizacao_em | timestamp | base da trava e da permanência (R-07) |

**Índices**: (`situacao`, `ultima_atualizacao_em`); `confrontos_id`; `campeonatos_id`.

**Estado calculado na leitura** (não gravado): `travado` = `configuracoes.ao_vivo_travado` ou
`now() - ultima_atualizacao_em > segundos_trava_ao_vivo`; fora da listagem quando
`now() - ultima_atualizacao_em > minutos_permanencia_ao_vivo`.

## Regras de cotação

### Tabela `confrontos_teto_cotacoes` (registro único, criado pelo seeder)

| Coluna | Tipo | Regras |
|---|---|---|
| tetos | json | padrão `{}`; cada valor ≥ 1,00; código ausente = sem teto |

### Tabelas `porcentagens_vendedores` e `porcentagens_vendedores_ao_vivo`

| Coluna | Tipo | Regras |
|---|---|---|
| usuarios_id | FK → `usuarios.id`, único | supervisor, gerente ou vendedor (também Admin) |
| valores | json | padrão `{}`; de −100 a 100, 2 casas |

### Tabelas `porcentagens_clientes` e `porcentagens_clientes_ao_vivo`

| Coluna | Tipo | Regras |
|---|---|---|
| clientes_id | FK → `clientes.id` null | nulo = regra geral (visitantes e todos os clientes) |
| chave_cliente | gerada: `COALESCE(clientes_id, 0)`, único | uma regra geral e uma por cliente (R-12) |
| valores | json | padrão `{}`; de −100 a 100; soma com a geral |

### Tabela `porcentagens_campeonatos`

| Coluna | Tipo | Regras |
|---|---|---|
| campeonatos_id | FK → `campeonatos.id` | |
| alvo | varchar(20) | `AlvoRegra`: `'Clientes'`, `'Vendedores'`, `'Todos'` |
| usuarios_id | FK → `usuarios.id` null | dono; obrigatório só em `'Vendedores'` |
| chave_usuario | gerada: `COALESCE(usuarios_id, 0)` | |
| valores | json | padrão `{}`; de −100 a 100 |

**Índice único**: (`campeonatos_id`, `alvo`, `chave_usuario`).

### Tabela `porcentagens_confrontos`

| Coluna | Tipo | Regras |
|---|---|---|
| confrontos_id | FK → `confrontos.id` | só pré-jogo (FR-046) |
| alvo | varchar(20) | `AlvoRegra` |
| usuarios_id | FK → `usuarios.id` null | dono em `'Vendedores'` |
| chave_usuario | gerada: `COALESCE(usuarios_id, 0)` | |
| valores | json | padrão `{}`; valor fixo somado (pode ser negativo) |

**Índice único**: (`confrontos_id`, `alvo`, `chave_usuario`).

## Não permitidos

### Tabelas `campeonatos_nao_permitidos`, `confrontos_nao_permitidos` e `confrontos_ao_vivo_nao_permitidos`

| Coluna | Tipo | Regras |
|---|---|---|
| campeonatos_id **ou** confrontos_id | FK | nas duas tabelas de confronto, FK → `confrontos.id` (no ao vivo, o jogo da grade, R-13) |
| alvo | varchar(20) | `AlvoRegra` |
| usuarios_id | FK → `usuarios.id` null | dono, só em `'Vendedores'` |
| clientes_id | FK → `clientes.id` null | só em `'Clientes'`; nulo = visitantes e todos os clientes |
| chave_usuario / chave_cliente | geradas: `COALESCE(..., 0)` | |

**Índice único**: (item, `alvo`, `chave_usuario`, `chave_cliente`). Desmarcar = soft delete;
marcar de novo restaura (R-12).

## Relações

- `campeonatos` 1–N `confrontos`; `confrontos` 1–N `confrontos_jogadores`; `confrontos` 1–N
  `confrontos_ao_vivo` (na prática 1–1, pelo `codigo_externo`).
- `campeonatos` 1–N `porcentagens_campeonatos` e `campeonatos_nao_permitidos`; `confrontos` 1–N
  `porcentagens_confrontos`, `confrontos_nao_permitidos` e `confrontos_ao_vivo_nao_permitidos`.
- `usuarios` 1–1 `usuarios_configuracoes`, 1–1 `porcentagens_vendedores` e
  `porcentagens_vendedores_ao_vivo`; `clientes` 1–1 `porcentagens_clientes` e
  `porcentagens_clientes_ao_vivo` (mais a linha geral sem cliente).

## Ciclos de vida

- **Confronto do provedor**: criado pela carga (`Aguardando`); `situacao` acompanha o provedor; sai
  do pré-jogo quando `data_inicio` passa ou a situação muda; nunca é apagado pela carga.
- **Confronto manual**: criado pelo painel (`Aguardando`); pode ir para `Adiado` ou `Cancelado`;
  exclusão lógica (com o campeonato manual, em cascata).
- **Jogo ao vivo**: criado/atualizado a cada 5 s → travado após 15 s sem atualização → fora da
  listagem após 5 min sem atualização ou depois do minuto limite.
- **Trava geral**: `ao_vivo_travado` `false` ↔ `true`, só pela conferência.

## Validações principais

| Origem | Regra |
|---|---|
| Carga do provedor | estrutura de topo inválida recusa tudo; confronto com código de cotação fora da lista, valor negativo ou não numérico, data inválida ou campeonato ausente é ignorado e vai para o log |
| Porcentagens | −100 a 100, 2 casas; códigos da lista (`odd1`…`odd323`, `jogador`) |
| Teto | ≥ 1,00, 2 casas |
| Cotação de confronto | > 0; código com base zero no provedor é recusado |
| Confronto manual | campeonato manual; data futura; ao menos 1 cotação; todas ≥ 1,00; situação `Aguardando`, `Adiado` ou `Cancelado` |
| Configurações | `esportes_permitidos` com ≥ 1 item (texto até 50); `minuto_limite_ao_vivo` 1–130; `cotacao_maxima_ao_vivo` ≥ 1,00 |
| Listagem | `tipo`, `dia` nas opções; `fuso_horario` `±HH:MM` entre −12:00 e +14:00; `por_pagina` 1–100 |
