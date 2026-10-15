<?php

namespace App\Models;

use App\Enums\Carteira;
use App\Enums\OrigemApuracao;
use App\Enums\TipoOperacaoApuracao;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Operação registrada pela apuração (spec 009, R-13). Só inserção: nada altera nem apaga.
 */
class OperacoesApuracao extends Model
{
    use SoftDeletes;

    protected $table = 'operacoes_apuracao';

    /**
     * Gravado só pelo RegistroApuracao.
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
            'tipo' => TipoOperacaoApuracao::class,
            'origem' => OrigemApuracao::class,
            'carteira' => Carteira::class,
            'valor' => 'decimal:2',
            'antes' => 'array',
            'depois' => 'array',
        ];
    }
}
