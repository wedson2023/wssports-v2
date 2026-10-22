<?php

namespace App\Http\Resources;

use App\Enums\ResultadoAposta;
use App\Models\Apostas;
use App\Support\FusoSistema;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Linha da lista de apostas do painel (spec 018, contracts/api.md §1.1), com as colunas extras de
 * ListagemApostas::com_colunas_da_lista; `acoes` e `situacao_vendedor` vêm preenchidos pelo
 * controller.
 *
 * @mixin Apostas
 */
class ApostasListaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $do_site = $this->clientes_id !== null;

        return [
            'codigo' => $this->codigo,
            'vendedor' => $do_site || $this->usuarios_id === null ? null : [
                'id' => $this->usuarios_id,
                'nome' => $this->vendedor_nome,
                'situacao' => $this->situacao_vendedor,
            ],
            'origem' => $do_site ? 'site' : 'balcao',
            'cliente' => $this->cliente_exibido,
            'valor' => (string) $this->valor,
            'comissao' => (string) $this->comissao,
            'premio' => bcadd((string) $this->premio_exibido, '0', 2),
            'situacao' => $this->situacao->value,
            'resultado' => $this->resultado->value,
            'situacao_exibida' => $this->resultado !== ResultadoAposta::Aguardando ? $this->resultado->value : $this->situacao->value,
            'tipo' => $this->tipo->value,
            'forma_pagamento' => $this->forma_pagamento?->value,
            'jogos_restantes' => (int) $this->jogos_restantes,
            'jogos_total' => (int) $this->jogos_total,
            'criada_em' => $this->data($this->created_at),
            'cancelada_em' => $this->data($this->cancelada_em),
            'cancelada_por' => $this->cancelada_por_nome,
            'acoes' => $this->acoes ?? ['ver'],
        ];
    }

    private function data(?Carbon $data): ?string
    {
        return $data?->copy()->setTimezone(FusoSistema::FUSO)->toIso8601String();
    }
}
