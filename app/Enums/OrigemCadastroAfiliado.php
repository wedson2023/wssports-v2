<?php

namespace App\Enums;

/**
 * Por onde o afiliado foi cadastrado: pela gestão do painel (já ativo) ou pela página pública
 * (em análise).
 */
enum OrigemCadastroAfiliado: string
{
    case Painel = 'Painel';
    case Público = 'Público';
}
