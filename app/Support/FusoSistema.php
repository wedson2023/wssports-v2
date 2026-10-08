<?php

namespace App\Support;

use Carbon\CarbonTimeZone;
use Illuminate\Support\Carbon;

/**
 * Fuso do negócio (Brasília). O banco continua em UTC; este fuso define o "dia" do valor máximo
 * diário, do período de jogos e das validades.
 */
class FusoSistema
{
    public const FUSO = '-03:00';

    /**
     * Início do dia (no fuso do sistema) do momento informado, em UTC.
     */
    public static function inicio_do_dia(?Carbon $momento = null): Carbon
    {
        return ($momento ?? now())->copy()->setTimezone(self::FUSO)->startOfDay()->utc();
    }

    /**
     * Fim do dia (no fuso do sistema) daqui a N dias (0 = hoje), em UTC.
     */
    public static function fim_do_dia_em(int $dias_a_frente): Carbon
    {
        return now()->setTimezone(self::FUSO)->startOfDay()->addDays($dias_a_frente)->endOfDay()->utc();
    }

    /**
     * Fuso pedido pelo front (±HH:MM); inválido ou ausente vale o fuso do sistema.
     */
    public static function do_pedido(mixed $fuso): CarbonTimeZone
    {
        return new CarbonTimeZone(is_string($fuso) && preg_match('/^[+-]\d{2}:[0-5]\d$/', $fuso) === 1 ? $fuso : self::FUSO);
    }
}
