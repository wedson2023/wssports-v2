<?php

namespace App\Enums;

/**
 * Situação de uma categoria especial: aceita palpites enquanto Aguardando; Encerrado (com a opção
 * vencedora) e Cancelado são finais.
 */
enum SituacaoEspecial: string
{
    case Aguardando = 'Aguardando';
    case Encerrado = 'Encerrado';
    case Cancelado = 'Cancelado';
}
