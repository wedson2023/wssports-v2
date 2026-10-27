<?php

namespace App\Enums;

/**
 * Rede social principal informada pelo afiliado no cadastro (spec 022, R-08); "Outra" para as que
 * não estão na lista, identificada pelo link do perfil.
 */
enum RedeSocialAfiliado: string
{
    case Instagram = 'Instagram';
    case TikTok = 'TikTok';
    case YouTube = 'YouTube';
    case Facebook = 'Facebook';
    case Kwai = 'Kwai';
    case X = 'X';
    case Outra = 'Outra';
}
