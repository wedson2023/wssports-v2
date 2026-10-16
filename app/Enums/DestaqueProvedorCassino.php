<?php

namespace App\Enums;

/**
 * Marcação do jogo feita pelo provedor do cassino no campo "menu" (spec 011, R-05).
 */
enum DestaqueProvedorCassino: string
{
    case Popular = 'Popular';
    case Novo = 'Novo';

    public static function do_provedor(?string $menu): ?self
    {
        return match ($menu) {
            'popular' => self::Popular,
            'new' => self::Novo,
            default => null,
        };
    }
}
