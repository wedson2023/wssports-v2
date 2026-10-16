<?php

namespace App\Models;

use App\Enums\EfeitoAcaoCassino;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tipo de ação do lançamento de rodada do provedor e o efeito no saldo (spec 011, data-model seção 5,
 * R-16).
 */
class CassinosAcoes extends Model
{
    use SoftDeletes;

    protected $table = 'cassinos_acoes';

    /**
     * @var list<string>
     */
    protected $fillable = ['acao', 'efeito', 'ativo', 'descricao'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'efeito' => EfeitoAcaoCassino::class,
            'ativo' => 'boolean',
        ];
    }

    /**
     * A ação é comparada com o valor recebido do provedor em maiúsculas.
     */
    protected function acao(): Attribute
    {
        return Attribute::make(set: fn (string $valor) => mb_strtoupper(trim($valor)));
    }
}
