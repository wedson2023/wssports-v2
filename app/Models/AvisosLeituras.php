<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * "Lido" de um aviso por um aparelho e, quando havia sessão, pelo cliente.
 */
class AvisosLeituras extends Model
{
    use SoftDeletes;

    protected $table = 'avisos_leituras';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'avisos_id',
        'aparelho',
        'clientes_id',
        'ip',
    ];

    public function aviso(): BelongsTo
    {
        return $this->belongsTo(Avisos::class, 'avisos_id')->withTrashed();
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Clientes::class, 'clientes_id')->withTrashed();
    }
}
