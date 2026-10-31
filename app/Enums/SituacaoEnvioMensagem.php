<?php

namespace App\Enums;

/**
 * Situação de um envio de mensagem (spec 025, data-model).
 */
enum SituacaoEnvioMensagem: string
{
    case Pendente = 'Pendente';
    case Enviado = 'Enviado';
    case Falhou = 'Falhou';
    case Dispensado = 'Dispensado';
}
