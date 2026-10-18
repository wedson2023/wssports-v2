<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resumo do painel do afiliado (contracts/api §1.6): valor a receber, última prestação, regra
 * vigente e link de indicação.
 */
class PainelAfiliadoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'nome' => $this->nome,
            'codigo' => $this->codigo,
            'link' => url('/').'?user='.$this->codigo,
            'valor_a_receber' => AfiliadosResource::valor($this->valor_a_receber),
            'ultima_prestacao' => $this->ultima_prestacao === null ? null : [
                'valor_pago' => $this->ultima_prestacao->valor_pago,
                'periodo_fim' => $this->ultima_prestacao->periodo_fim,
            ],
            'comissao' => $this->comissao_vigente === null ? null : [
                'tipo_ganho' => $this->comissao_vigente->tipo_ganho,
                'percentual' => $this->comissao_vigente->percentual,
                'vigente_desde' => $this->comissao_vigente->vigente_desde,
            ],
            'clientes_indicados' => (int) $this->clientes_count,
        ];
    }
}
