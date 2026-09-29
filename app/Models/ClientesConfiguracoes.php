<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Configurações de aposta e saque de um cliente (no sistema antigo, "travas").
 */
class ClientesConfiguracoes extends Model
{
    use SoftDeletes;

    /**
     * Campos de configuração, compartilhados com as configurações padrão.
     */
    public const CAMPOS = [
        'realizar_aposta',
        'apostar_ao_vivo',
        'apostar_outros_esportes',
        'cancelar_aposta',
        'aceita_promocao',
        'bloquear_saque',
        'quantidade_minima_opcoes',
        'quantidade_maxima_opcoes',
        'valor_minimo_aposta',
        'valor_maximo_aposta',
        'premio_maximo',
        'valor_maximo_diario',
        'valor_maximo_saque_diario',
        'quantidade_maxima_saques_diaria',
        'odd_minima',
        'odd_maxima',
        'esportes_permitidos',
    ];

    /**
     * Conversões dos campos de configuração, compartilhadas com as configurações padrão.
     */
    public const CASTS = [
        'realizar_aposta' => 'boolean',
        'apostar_ao_vivo' => 'boolean',
        'apostar_outros_esportes' => 'boolean',
        'cancelar_aposta' => 'boolean',
        'aceita_promocao' => 'boolean',
        'bloquear_saque' => 'boolean',
        'quantidade_minima_opcoes' => 'integer',
        'quantidade_maxima_opcoes' => 'integer',
        'valor_minimo_aposta' => 'decimal:2',
        'valor_maximo_aposta' => 'decimal:2',
        'premio_maximo' => 'decimal:2',
        'valor_maximo_diario' => 'decimal:2',
        'valor_maximo_saque_diario' => 'decimal:2',
        'quantidade_maxima_saques_diaria' => 'integer',
        'odd_minima' => 'decimal:2',
        'odd_maxima' => 'decimal:2',
        'esportes_permitidos' => 'array',
    ];

    protected $table = 'clientes_configuracoes';

    /**
     * @var list<string>
     */
    protected $fillable = [...self::CAMPOS, 'clientes_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return self::CASTS;
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Clientes::class, 'clientes_id');
    }
}
