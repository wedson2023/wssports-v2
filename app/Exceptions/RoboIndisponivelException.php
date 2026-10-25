<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A Evolution API não respondeu, demorou mais que o tempo limite ou não está configurada no servidor
 * (spec 021, FR-027). A mensagem pode ir para a tela: não leva endereço nem chave.
 */
class RoboIndisponivelException extends RuntimeException
{
    public function __construct(string $mensagem = 'Serviço do robô indisponível. Confira o endereço e a chave da Evolution API no servidor.')
    {
        parent::__construct($mensagem);
    }
}
