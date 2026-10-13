<?php

namespace App\Http\Resources;

use App\Enums\MeioDeposito;
use App\Enums\SituacaoDeposito;
use App\Models\Depositos;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Depósito para o cliente (spec 007, contracts/api.md §5 e §6). O QR Code só vai com o Pix
 * pendente; a comissão nunca aparece.
 *
 * @mixin Depositos
 */
class DepositosResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $pix_aberto = $this->meio === MeioDeposito::Pix && $this->situacao === SituacaoDeposito::Pendente;

        return [
            'codigo' => $this->codigo,
            'meio' => $this->meio,
            'valor' => $this->valor,
            'parcelas' => $this->parcelas,
            'valor_total_pago' => $this->valor_total_pago,
            'situacao' => $this->situacao,
            'pago_em' => $this->pago_em?->copy()->setTimezone('-03:00')->toIso8601String(),
            'pix' => $pix_aberto ? [
                'copia_cola' => $this->pix_copia_cola,
                'qr_code_base64' => $this->pix_qr_code,
                'expira_em' => $this->expira_em?->copy()->setTimezone('-03:00')->toIso8601String(),
            ] : null,
        ];
    }
}
