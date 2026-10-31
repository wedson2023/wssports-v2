<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A Evolution API recusou o envio com uma resposta 4xx, como número sem WhatsApp (spec 025, research
 * R-02). É uma falha definitiva: não adianta tentar de novo.
 */
class DestinatarioSemWhatsappException extends RuntimeException
{
    public function __construct(int $status)
    {
        parent::__construct("A Evolution API recusou o envio (HTTP {$status}).");
    }
}
