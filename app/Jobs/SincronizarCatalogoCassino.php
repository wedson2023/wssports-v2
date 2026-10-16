<?php

namespace App\Jobs;

use App\Enums\OrigemSincronizacaoCassino;
use App\Models\Usuarios;
use App\Services\SincronizacaoCassino;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Carga do catálogo do cassino pedida pelo painel (spec 011, R-04).
 */
class SincronizarCatalogoCassino implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public ?int $usuarios_id = null) {}

    public function handle(SincronizacaoCassino $sincronizacao): void
    {
        $sincronizacao->sincronizar(OrigemSincronizacaoCassino::Painel, $this->usuarios_id ? Usuarios::find($this->usuarios_id) : null);
    }
}
