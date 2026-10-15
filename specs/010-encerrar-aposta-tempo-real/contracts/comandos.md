# Contrato: comandos, jobs e agenda

**Feature**: `010-encerrar-aposta-tempo-real`

## Comandos novos

| Comando | Agenda | O que faz |
|---|---|---|
| `apostas:liquidar_encerramentos` | a cada minuto, `withoutOverlapping(5)` | Credita os encerramentos `Registrado` com `creditar_em` ≤ agora, um por transação, com a aposta travada (R-10). Resumo: `creditados`, `falhas`. Erro num encerramento vai para o log e não para os demais. |
| `apostas:conferir_encerramentos_automaticos` | a cada minuto, `withoutOverlapping(2)` | Confere todos os automáticos `Ativo` (cobre as apostas só com jogos do pré-jogo, R-12). |
| `notificacoes_push:gerar_chaves` | manual | Gera e mostra o par VAPID para o `.env`. Não grava nada; avisa que trocar as chaves invalida as inscrições existentes. |

## Comandos existentes alterados

| Comando | Mudança |
|---|---|
| `confrontos_ao_vivo:importar` | Sem mudança no comando. O serviço `ImportacaoAoVivo` passa a chamar `ProcessamentoAoVivo` depois de gravar (R-16), que dispara broadcast, eventos/push e conferência dos automáticos. Falhas nesse pós-processamento vão para o log e não desfazem a carga. |
| `confrontos_cotacoes:importar` | Sem mudança no comando. O serviço `ImportacaoCotacoes` passa a emitir `ConfrontoAtualizado` e a despachar `ConferirEncerramentosAutomaticos` para os jogos da carga com aposta em aberto (R-22); falha → warning. |
| `apostas:recusar_analises_presas` | Também recusa encerramentos `Em análise` presos há mais que o delay + 60 s ("Tempo de confirmação esgotado."), sem mover nada (R-09). |

## Jobs novos

| Job | Fila | Tentativas | O que faz |
|---|---|---|---|
| `DecidirEncerramento(apostas_encerramentos_id)` | `apostas` | 1 | Decide o encerramento `Em análise` no fim do tempo de confirmação do ao vivo (R-09). |
| `ConferirEncerramentosAutomaticos(confrontos_ids)` | `apostas` | 1 | Confere os automáticos `Ativo` das apostas com algum dos jogos alterados na carga (R-12). |
| `EnviarNotificacaoPush(clientes_notificacoes_id)` | `notificacoes` | 1 | Envia um aviso já registrado para as inscrições do cliente com o tipo ligado (R-13). |

Os jobs são despachados com `afterCommit()` quando nascem dentro de uma transação.

## Worker da fila

Além das filas que já existem, o worker precisa atender as filas `apostas` e `notificacoes`:

```text
php artisan queue:work --queue=apostas,notificacoes,default --tries=1
```

## Processo do tempo real

```text
php artisan reverb:start --host=0.0.0.0 --port=8080
```

Em produção, sob supervisor e atrás do proxy (`wss://`). Em desenvolvimento, o script
`composer dev` passa a subir também o Reverb, e o `queue:listen` dele passa a atender as filas
`apostas,notificacoes,default`.

## `routes/console.php` (acréscimos)

```php
// encerramento de aposta (spec 010): credita os encerramentos vencidos na janela de segurança
Schedule::command('apostas:liquidar_encerramentos')->everyMinute()->withoutOverlapping(5);

// encerramentos automáticos das apostas só com jogos do pré-jogo (os do ao vivo vão pela carga)
Schedule::command('apostas:conferir_encerramentos_automaticos')->everyMinute()->withoutOverlapping(2);
```
