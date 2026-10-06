<?php

namespace App\Enums;

/**
 * Para quem vale uma regra de cotação ou um não permitido: o site (visitantes e clientes), os
 * vendedores de um usuário da hierarquia ou todo mundo.
 */
enum AlvoRegra: string
{
    case Clientes = 'Clientes';
    case Vendedores = 'Vendedores';
    case Todos = 'Todos';
}
