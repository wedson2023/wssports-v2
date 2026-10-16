<?php

namespace App\Models;

use App\Enums\ResultadoChamadaCassino;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Chamada recebida do provedor do cassino, sem a chave (spec 011, data-model seção 9, R-15).
 */
class CassinosChamadas extends Model
{
    use SoftDeletes;

    protected $table = 'cassinos_chamadas';

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
            'dados' => 'array',
            'resposta' => 'array',
            'resultado' => ResultadoChamadaCassino::class,
            'duracao_ms' => 'integer',
        ];
    }
}
