<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Registro único com as configurações que cada cliente novo recebe no cadastro.
 */
class ClientesConfiguracoesPadrao extends Model
{
    use SoftDeletes;

    protected $table = 'clientes_configuracoes_padrao';

    /**
     * @var list<string>
     */
    protected $fillable = ClientesConfiguracoes::CAMPOS;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ClientesConfiguracoes::CASTS;
    }

    /**
     * O registro único de configurações padrão (criado pelo seeder).
     */
    public static function atual(): self
    {
        return static::query()->firstOrFail();
    }
}
