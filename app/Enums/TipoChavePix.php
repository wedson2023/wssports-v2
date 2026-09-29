<?php

namespace App\Enums;

enum TipoChavePix: string
{
    case Cpf = 'CPF';
    case Cnpj = 'CNPJ';
    case Email = 'E-mail';
    case Telefone = 'Telefone';
    case ChaveAleatória = 'Chave aleatória';
}
