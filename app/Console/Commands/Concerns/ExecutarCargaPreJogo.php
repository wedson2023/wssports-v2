<?php

namespace App\Console\Commands\Concerns;

use App\Exceptions\FalhaProvedorException;
use App\Models\Configuracoes;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Execução comum às cargas do pré-jogo (campeonatos, confrontos e cotações): não roda com o
 * sistema só para cassino, registra falhas sem dados sensíveis (nada é gravado) e o resumo com o
 * tempo gasto.
 */
trait ExecutarCargaPreJogo
{
    /**
     * @param  callable(): array<string, int>  $carga
     * @param  callable(array<string, int>): string  $resumo
     */
    protected function executar_carga(string $nome, callable $carga, callable $resumo): int
    {
        if (Configuracoes::atual()->somente_cassino) {
            $this->info("Sistema configurado só para cassino: carga {$nome} não executada.");

            return self::SUCCESS;
        }

        $inicio = microtime(true);

        try {
            $resultado = $carga();
        } catch (FalhaProvedorException $erro) {
            Log::error("Carga {$nome} não executada: {$erro->getMessage()}");
            $this->error($erro->getMessage());

            return self::FAILURE;
        } catch (Throwable $erro) {
            Log::error("Carga {$nome} desfeita por erro ao gravar: ".class_basename($erro).' - '.$erro->getMessage());
            $this->error("Erro ao gravar a carga {$nome}; nada foi alterado.");

            return self::FAILURE;
        }

        $segundos = round(microtime(true) - $inicio, 2);
        $mensagem = "Carga {$nome} concluída em {$segundos} s: {$resumo($resultado)}.";

        Log::info($mensagem);
        $this->info($mensagem);

        return self::SUCCESS;
    }
}
