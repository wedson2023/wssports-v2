<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Falha ao consultar o provedor de cotações (fora do ar, tempo esgotado, status de erro ou
 * resposta fora do formato). A mensagem nunca leva a chave de acesso.
 */
class FalhaProvedorException extends RuntimeException {}
