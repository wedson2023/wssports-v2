<?php

namespace App\Services;

use App\Models\Usuarios;
use App\Support\FusoSistema;
use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Cache curto de cada seção da Dashboard por usuário e dia (spec 014, FR-042, R-08): absorve a
 * atualização automática de vários usuários; o botão de atualizar recalcula na hora.
 */
class CacheDashboard
{
    private const SEGUNDOS = 60;

    /**
     * @param  Closure(): mixed  $calcular
     * @return array{data: mixed, gerado_em: string}
     */
    public function lembrar(string $secao, Usuarios $usuario, bool $atualizar, Closure $calcular): array
    {
        $dia = now()->setTimezone(FusoSistema::FUSO)->toDateString();
        $chave = "dashboard:{$secao}:{$usuario->id}:{$dia}";

        if ($atualizar) {
            Cache::forget($chave);
        }

        return Cache::remember($chave, self::SEGUNDOS, fn () => [
            'data' => $calcular(),
            'gerado_em' => now()->setTimezone(FusoSistema::FUSO)->toIso8601String(),
        ]);
    }
}
