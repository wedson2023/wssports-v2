<?php

namespace App\Enums;

/**
 * Situação da mensagem no WhatsApp do Admin sobre uma combinação de risco nova (spec 014): Pendente
 * (a enviar, até 3 tentativas), Enviado, Falhou (3 tentativas sem sucesso) ou Dispensado (aviso
 * desligado ou sem telefone/chave quando a combinação surgiu).
 */
enum SituacaoAvisoWhatsapp: string
{
    case Pendente = 'Pendente';
    case Enviado = 'Enviado';
    case Falhou = 'Falhou';
    case Dispensado = 'Dispensado';
}
