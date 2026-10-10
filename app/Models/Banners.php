<?php

namespace App\Models;

use App\Services\ArmazenamentoImagens;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * Banner do carrossel da tela principal: imagem 1280x405, link opcional, ordem e se está ativo.
 */
class Banners extends Model
{
    use SoftDeletes;

    protected $table = 'banners';

    /**
     * Padrões das colunas, para o banner recém-criado já responder com eles.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'ordem' => 0,
        'ativo' => true,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'imagem',
        'link',
        'ordem',
        'ativo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ordem' => 'integer',
            'ativo' => 'boolean',
        ];
    }

    public function url_imagem(): string
    {
        return ArmazenamentoImagens::url($this->imagem);
    }

    /**
     * Banners do carrossel: ativos, na ordem, só os que têm o arquivo da imagem (spec 006, FR-032c).
     *
     * @return list<array{imagem: string, link: string|null}>
     */
    public static function ativos(): array
    {
        return static::where('ativo', true)
            ->orderBy('ordem')
            ->orderBy('id')
            ->get()
            ->filter(fn (self $banner) => Storage::disk('public')->exists($banner->imagem))
            ->map(fn (self $banner) => ['imagem' => $banner->url_imagem(), 'link' => $banner->link])
            ->values()
            ->all();
    }
}
