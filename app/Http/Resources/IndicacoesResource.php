<?php

namespace App\Http\Resources;

use App\Models\ClientesIndicacoes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Indicado na área "Minhas indicações" (spec 007, FR-044c): só primeiro nome, inicial do
 * sobrenome, data do cadastro e situação; nada de telefone, e-mail, CPF ou valores.
 *
 * @mixin ClientesIndicacoes
 */
class IndicacoesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $partes = preg_split('/\s+/', trim((string) $this->indicado?->nome));
        $nome = $partes[0] ?? '';

        if (count($partes) > 1) {
            $nome .= ' '.mb_strtoupper(mb_substr(end($partes), 0, 1)).'.';
        }

        return [
            'nome' => $nome,
            'cadastrado_em' => $this->created_at->copy()->setTimezone('-03:00')->toDateString(),
            'situacao' => $this->validada_em ? 'Depositou' : 'Pendente de depósito',
        ];
    }
}
