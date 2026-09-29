<?php

namespace App\Events;

use App\Models\Clientes;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Código de recuperação de senha gerado: dispara o envio por WhatsApp.
 */
class CodigoRecuperacaoGerado implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public Clientes $cliente, public string $codigo) {}
}
