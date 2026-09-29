<?php

namespace App\Events;

use App\Models\Clientes;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Conta criada: dispara a mensagem de boas-vindas por WhatsApp.
 */
class ClienteCadastrado implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public Clientes $cliente) {}
}
