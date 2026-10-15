<?php

namespace App\Models;

use App\Enums\TipoEventoConfronto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Gol, gol anulado, intervalo ou fim de jogo detectado nas cargas (spec 010, data-model seção 5).
 */
class ConfrontosEventos extends Model
{
    use SoftDeletes;

    protected $table = 'confrontos_eventos';

    /**
     * Gravado só pelo serviço EventosConfrontos.
     *
     * @var list<string>
     */
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoEventoConfronto::class,
            'placar_casa' => 'integer',
            'placar_fora' => 'integer',
            'minuto' => 'integer',
            'notificado_em' => 'datetime',
        ];
    }

    public function confronto(): BelongsTo
    {
        return $this->belongsTo(Confrontos::class, 'confrontos_id')->withTrashed();
    }

    public function confronto_ao_vivo(): BelongsTo
    {
        return $this->belongsTo(ConfrontosAoVivo::class, 'confrontos_ao_vivo_id')->withTrashed();
    }
}
