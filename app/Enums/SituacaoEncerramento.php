<?php

namespace App\Enums;

/**
 * Situação de um pedido de encerramento de aposta (spec 010, data-model seção 1).
 */
enum SituacaoEncerramento: string
{
    // aguardando o tempo de confirmação do ao vivo
    case EmAnálise = 'Em análise';
    case Recusado = 'Recusado';
    // aceito, aguardando a janela de pagamento
    case Registrado = 'Registrado';
    case Creditado = 'Creditado';
    case Desfeito = 'Desfeito';

    /**
     * O encerramento foi aceito e continua valendo (já pago ou a pagar).
     */
    public function vale(): bool
    {
        return $this === self::Registrado || $this === self::Creditado;
    }

    /**
     * @return list<string>
     */
    public static function valores_que_valem(): array
    {
        return [self::Registrado->value, self::Creditado->value];
    }
}
