<?php

namespace App\Enums;

/**
 * Situação de um bloco Criar Aposta: o anulado fica fora da cotação total (vale 1,00).
 */
enum SituacaoBloco: string
{
    case Ativo = 'Ativo';
    case Anulado = 'Anulado';
}
