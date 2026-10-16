<?php

namespace App\Enums;

/**
 * Aparelhos em que um jogo do cassino funciona (spec 011, R-05).
 */
enum AparelhoCassino: string
{
    case Todos = 'Todos';
    case Celular = 'Celular';
    case Computador = 'Computador';

    /**
     * Campo "device" do provedor: 0 computador, 1 celular, 2 (e outros) todos.
     */
    public static function do_provedor(string $device): self
    {
        return match ($device) {
            '0' => self::Computador,
            '1' => self::Celular,
            default => self::Todos,
        };
    }

    /**
     * Aparelho de quem vê o site, pelo parâmetro "aparelho" (celular ou computador).
     */
    public static function de_quem_ve(string $aparelho): self
    {
        return $aparelho === 'computador' ? self::Computador : self::Celular;
    }
}
