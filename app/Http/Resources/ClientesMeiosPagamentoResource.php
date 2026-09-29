<?php

namespace App\Http\Resources;

use App\Models\ClientesMeiosPagamento;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Meio de pagamento. No painel, sem clientes.ver_dados_completos, chave Pix, conta e
 * documento do titular saem com só os 4 últimos caracteres visíveis.
 */
class ClientesMeiosPagamentoResource extends JsonResource
{
    private const CAMPOS_MASCARADOS = ['pix_chave', 'conta', 'titular_documento'];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $dados = [
            'id' => $this->id,
            'tipo' => $this->tipo,
            'principal' => $this->principal,
            ...$this->resource->only([...ClientesMeiosPagamento::CAMPOS_PIX, ...ClientesMeiosPagamento::CAMPOS_TRANSFERENCIA]),
            'created_at' => $this->created_at,
        ];

        if (ClientesResource::deve_mascarar($request)) {
            foreach (self::CAMPOS_MASCARADOS as $campo) {
                $dados[$campo] = ClientesResource::mascarar_ultimos($dados[$campo]);
            }
        }

        return $dados;
    }
}
