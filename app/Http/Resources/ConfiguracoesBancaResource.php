<?php

namespace App\Http\Resources;

use App\Models\Configuracoes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Dados da banca da aba Banca (spec 021, contracts/api.md §1.1), com a lista das cores do tema.
 *
 * @mixin Configuracoes
 */
class ConfiguracoesBancaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'nome_sistema' => $this->nome_sistema,
            'mensagem_bilhete' => $this->mensagem_bilhete,
            'whatsapp' => $this->whatsapp,
            'mensagem_whatsapp' => $this->mensagem_whatsapp,
            'botao_whatsapp' => (bool) $this->botao_whatsapp,
            'instagram' => $this->instagram,
            'facebook' => $this->facebook,
            'twitter' => $this->twitter,
            'youtube' => $this->youtube,
            'jogo_responsavel' => $this->jogo_responsavel,
            'cor_tema' => $this->cor_tema,
            'cor_fundo' => $this->cor_fundo,
            'gtm_id' => $this->gtm_id,
            'cores_tema' => collect(Configuracoes::CORES_TEMA)
                ->map(fn (array $cor, string $codigo) => ['cor' => $codigo, 'nome' => $cor['nome']])
                ->values(),
        ];
    }
}
