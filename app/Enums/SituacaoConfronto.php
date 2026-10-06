<?php

namespace App\Enums;

/**
 * Situação de um confronto do pré-jogo, como o provedor envia.
 */
enum SituacaoConfronto: string
{
    case Aguardando = 'Aguardando';
    case Encerrado = 'Encerrado';
    case Cancelado = 'Cancelado';
    case Adiado = 'Adiado';
    // suspenso pelo provedor: fica gravado, fora da listagem, até voltar a Aguardando
    case Bloqueado = 'Bloqueado';

    /**
     * Situações que o usuário pode escolher num confronto manual.
     *
     * @return list<self>
     */
    public static function editaveis_manualmente(): array
    {
        return [self::Aguardando, self::Adiado, self::Cancelado];
    }
}
