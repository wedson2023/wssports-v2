<?php

namespace App\Console\Commands;

use App\Exceptions\FalhaProvedorException;
use App\Models\Configuracoes;
use App\Services\ImportacaoAoVivo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Carga do ao vivo a cada 5 segundos (substitui fonte:aovivo). Em qualquer falha nada é gravado:
 * os jogos ficam sem atualização e travam sozinhos.
 */
class ImportarConfrontosAoVivoCommand extends Command
{
    protected $signature = 'confrontos_ao_vivo:importar';

    protected $description = 'Busca no provedor e grava os jogos em andamento (placar, minuto e cotações)';

    public function handle(ImportacaoAoVivo $importacao): int
    {
        $configuracoes = Configuracoes::atual();

        if ($configuracoes->somente_cassino || ! $configuracoes->ao_vivo_habilitado) {
            $this->info('Ao vivo desligado ou sistema só para cassino: carga do ao vivo não executada.');

            return self::SUCCESS;
        }

        try {
            $resumo = $importacao->importar();
        } catch (FalhaProvedorException $erro) {
            Log::error("Carga do ao vivo não executada: {$erro->getMessage()}");
            $this->error($erro->getMessage());

            return self::FAILURE;
        } catch (Throwable $erro) {
            Log::error('Carga do ao vivo desfeita por erro ao gravar: '.class_basename($erro).' - '.$erro->getMessage());
            $this->error('Erro ao gravar a carga do ao vivo; nada foi alterado.');

            return self::FAILURE;
        }

        $this->info("Carga do ao vivo: {$resumo['gravados']} de {$resumo['recebidos']} jogos gravados.");

        return self::SUCCESS;
    }
}
