<?php

namespace App\Events;

use App\Models\Clientes;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Código de recuperação de senha gerado: dispara o envio pelo canal configurado (spec 025). O id
 * permite conferir, na hora do envio, se o código continua válido.
 */
class CodigoRecuperacaoGerado implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public Clientes $cliente, public string $codigo, public int $codigo_id, public int $validade_minutos) {}
}
