<?php

namespace App\Models;

use App\Enums\TipoGanhoAfiliado;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Regra de comissão do afiliado com vigência; a vigente tem vigente_ate nulo. Criada e encerrada
 * só pelo serviço RegraComissaoAfiliados.
 */
class AfiliadosComissoes extends Model
{
    use SoftDeletes;

    protected $table = 'afiliados_comissoes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'afiliados_id',
        'tipo_ganho',
        'percentual',
        'vigente_desde',
        'vigente_ate',
        'usuarios_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo_ganho' => TipoGanhoAfiliado::class,
            'percentual' => 'decimal:2',
            'vigente_desde' => 'datetime',
            'vigente_ate' => 'datetime',
        ];
    }

    public function afiliado(): BelongsTo
    {
        return $this->belongsTo(Afiliados::class, 'afiliados_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'usuarios_id');
    }
}
