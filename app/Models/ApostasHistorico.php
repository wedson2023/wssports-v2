<?php

namespace App\Models;

use App\Enums\AcaoHistoricoAposta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Edição de palpite feita pelo painel, com cotação total e prêmio antes e depois.
 */
class ApostasHistorico extends Model
{
    use SoftDeletes;

    protected $table = 'apostas_historico';

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
            'acao' => AcaoHistoricoAposta::class,
            'cotacao_total_anterior' => 'decimal:2',
            'cotacao_total_posterior' => 'decimal:2',
            'premio_anterior' => 'decimal:2',
            'premio_posterior' => 'decimal:2',
            'valor_acrescido_anterior' => 'decimal:2',
            'valor_acrescido_posterior' => 'decimal:2',
        ];
    }

    public function aposta(): BelongsTo
    {
        return $this->belongsTo(Apostas::class, 'apostas_id');
    }

    public function palpite(): BelongsTo
    {
        return $this->belongsTo(ApostasPalpites::class, 'apostas_palpites_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'usuarios_id')->withTrashed();
    }
}
