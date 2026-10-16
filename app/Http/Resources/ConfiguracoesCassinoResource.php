<?php

namespace App\Http\Resources;

use App\Models\Configuracoes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Configurações gerais do cassino no painel (spec 011), com a URL de callback a cadastrar no hall do
 * provedor.
 *
 * @mixin Configuracoes
 */
class ConfiguracoesCassinoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'cassino_habilitado' => $this->cassino_habilitado,
            'cassino_demo_habilitado' => $this->cassino_demo_habilitado,
            'data_prestacao_contas_cassino' => $this->data_prestacao_contas_cassino?->toDateString(),
            'dias_registro_chamadas_cassino' => $this->dias_registro_chamadas_cassino,
            // endereço público do site (APP_URL), não o da requisição
            'url_callback' => rtrim((string) config('app.url'), '/').'/api/cassino/carteira',
        ];
    }
}
