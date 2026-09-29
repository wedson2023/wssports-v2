<?php

namespace App\Enums;

/**
 * Gênero informado pelo cliente no cadastro.
 */
enum Genero: string
{
    case Masculino = 'Masculino';
    case Feminino = 'Feminino';
    case Outro = 'Outro';
    case NãoInformado = 'Não informado';
}
