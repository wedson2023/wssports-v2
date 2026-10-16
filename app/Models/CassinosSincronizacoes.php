<?php

namespace App\Models;

use App\Enums\OrigemSincronizacaoCassino;
use App\Enums\SituacaoSincronizacaoCassino;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Resumo de uma carga do catálogo do cassino (spec 011, data-model seção 4).
 */
class CassinosSincronizacoes extends Model
{
    use SoftDeletes;

    protected $table = 'cassinos_sincronizacoes';

    /**
     * @var list<string>
     */
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'origem' => OrigemSincronizacaoCassino::class,
            'situacao' => SituacaoSincronizacaoCassino::class,
            'falhas' => 'array',
            'iniciada_em' => 'datetime',
            'concluida_em' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'usuarios_id')->withTrashed();
    }
}
