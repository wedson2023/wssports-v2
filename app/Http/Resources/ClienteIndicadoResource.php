<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Cliente indicado, como o afiliado o vê (FR-055, research R-12): nome parcialmente oculto, data
 * do cadastro e total depositado; nunca telefone, e-mail ou CPF.
 */
class ClienteIndicadoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'nome' => self::nome_parcial($this->nome),
            'cadastrado_em' => $this->created_at,
            'total_depositado' => AfiliadosResource::valor($this->total_depositado),
        ];
    }

    /**
     * Primeira palavra completa e as demais pela inicial: "João Silva Pereira" → "João S. P.".
     */
    public static function nome_parcial(string $nome): string
    {
        $palavras = preg_split('/\s+/', trim($nome)) ?: [];
        $primeira = array_shift($palavras) ?? '';
        $iniciais = array_map(fn (string $palavra) => mb_strtoupper(mb_substr($palavra, 0, 1)).'.', $palavras);

        return trim($primeira.' '.implode(' ', $iniciais));
    }
}
