<?php

namespace App\Enums;

enum TipoMeioPagamento: string
{
    case Pix = 'Pix';
    case TransferênciaBancária = 'Transferência bancária';
}
