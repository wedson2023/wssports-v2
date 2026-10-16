# Contrato: comandos, jobs e agenda

**Feature**: `011-cassino-tbs2` | **Data**: 2026-10-16

## Comandos

### `cassinos_jogos:sincronizar {--origem=Agendada} {--usuario=}`

Busca o `gamesList` e atualiza o catálogo (R-04).

**Saída**: o resumo da carga (recebidos, jogos e canais novos, alterados, desativados,
reativados, falhas).

**Retorno**:
- 0 quando a carga é concluída;
- 1 em falha (nada é desativado);
- 0 sem fazer nada quando outra carga está em andamento.

### `cassinos_chamadas:limpar`

Apaga fisicamente as chamadas antigas (R-15):
- `Aceita` e `Repetida` com mais de `configuracoes.dias_registro_chamadas_cassino` dias;
- `Recusada` e `Erro` com mais de 90 dias.

Apaga em lotes de 5.000 para não travar a tabela. A saída informa quantas foram apagadas.

## Job

### `SincronizarCatalogoCassino` (fila `default`)

Disparado por `POST /cassinos-sincronizacoes`. Chama o mesmo serviço do comando com origem
`Painel` e o usuário. Uma tentativa e tempo limite de 120 s.

## Agenda (`routes/console.php`)

```php
// cassino (spec 011): catálogo do provedor de hora em hora, no minuto 5
Schedule::command('cassinos_jogos:sincronizar')->hourlyAt(5)->runInBackground()->withoutOverlapping(30);

// cassino: limpa o registro de chamadas do provedor
Schedule::command('cassinos_chamadas:limpar')->dailyAt('04:00')->withoutOverlapping(30);
```

O comando antigo `command:cassino` não existe no novo sistema. Nenhum outro agendamento muda.
