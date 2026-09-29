<?php

namespace App\Enums;

/**
 * Evento que dá direito à promoção.
 */
enum CategoriaPromocao: string
{
    case PrimeiroCadastro = 'Primeiro cadastro';
    case PrimeiroDepósito = 'Primeiro depósito';
    case QualquerDepósito = 'Qualquer depósito';
    case Indicação = 'Indicação';

    /**
     * Só as categorias de depósito têm um valor base para o ganho percentual.
     */
    public function aceita_percentual(): bool
    {
        return in_array($this, [self::PrimeiroDepósito, self::QualquerDepósito], true);
    }
}
