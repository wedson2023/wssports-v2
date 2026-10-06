<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Configurações de um vendedor (o único que aposta). Sem tabela padrão: os valores iniciais
 * são os padrões das colunas.
 */
class UsuariosConfiguracoes extends Model
{
    use SoftDeletes;

    public const CAMPOS = [
        'esportes_permitidos',
        'apostar_outros_esportes',
        'ao_vivo_habilitado',
        'minuto_limite_ao_vivo',
        'cotacao_maxima_ao_vivo',
    ];

    protected $table = 'usuarios_configuracoes';

    /**
     * @var list<string>
     */
    protected $fillable = [...self::CAMPOS, 'usuarios_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'esportes_permitidos' => 'array',
            'apostar_outros_esportes' => 'boolean',
            'ao_vivo_habilitado' => 'boolean',
            'minuto_limite_ao_vivo' => 'integer',
            'cotacao_maxima_ao_vivo' => 'decimal:2',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'usuarios_id');
    }

    /**
     * Configuração do vendedor; se ainda não existir, é criada só com o usuário e relida, para
     * trazer os valores padrão das colunas (não existe tabela padrão).
     */
    public static function do_vendedor(int $usuarios_id): self
    {
        $configuracao = static::firstOrCreate(['usuarios_id' => $usuarios_id]);

        return $configuracao->wasRecentlyCreated ? $configuracao->refresh() : $configuracao;
    }

    /**
     * Esportes exibidos na listagem: só Futebol quando outros esportes estão desligados.
     *
     * @return list<string>
     */
    public function esportes_visiveis(): array
    {
        return $this->apostar_outros_esportes ? array_values($this->esportes_permitidos ?? []) : ['FUTEBOL'];
    }
}
