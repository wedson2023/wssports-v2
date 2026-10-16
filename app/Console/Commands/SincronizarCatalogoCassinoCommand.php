<?php

namespace App\Console\Commands;

use App\Enums\OrigemSincronizacaoCassino;
use App\Enums\SituacaoSincronizacaoCassino;
use App\Models\Usuarios;
use App\Services\SincronizacaoCassino;
use Illuminate\Console\Command;

/**
 * Carga do catálogo do provedor do cassino (spec 011, R-04): agendada de hora em hora e também
 * disparada pelo painel. Substitui o command:cassino do sistema antigo.
 */
class SincronizarCatalogoCassinoCommand extends Command
{
    protected $signature = 'cassinos_jogos:sincronizar
        {--origem=Agendada : Quem disparou a carga (Agendada ou Painel)}
        {--usuario= : Id do usuário do painel que pediu a carga}';

    protected $description = 'Atualiza o catálogo de jogos do cassino com a lista do provedor';

    public function handle(SincronizacaoCassino $sincronizacao): int
    {
        $origem = OrigemSincronizacaoCassino::tryFrom((string) $this->option('origem')) ?? OrigemSincronizacaoCassino::Agendada;
        $usuario = $this->option('usuario') ? Usuarios::find($this->option('usuario')) : null;

        $carga = $sincronizacao->sincronizar($origem, $usuario);

        if ($carga === null) {
            $this->info('Já existe uma atualização do catálogo em andamento: nada feito.');

            return self::SUCCESS;
        }

        if ($carga->situacao === SituacaoSincronizacaoCassino::Falha) {
            $this->error("Falha na atualização do catálogo: {$carga->erro}");

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Catálogo atualizado: %d recebidos, %d jogos novos, %d canais novos, %d alterados, %d desativados, %d reativados, %d falhas.',
            $carga->recebidos,
            $carga->jogos_novos,
            $carga->canais_novos,
            $carga->alterados,
            $carga->desativados,
            $carga->reativados,
            count($carga->falhas ?? []),
        ));

        return self::SUCCESS;
    }
}
