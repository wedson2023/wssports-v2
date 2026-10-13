<?php

namespace App\Services;

/**
 * Motivo de recusa do Mercado Pago (status_detail) em português para o cliente (FR-026).
 */
class MensagensMercadoPago
{
    private const PADRAO = 'Pagamento recusado pelo banco emissor. Tente outro cartão.';

    private const RECUSAS = [
        'cc_rejected_insufficient_amount' => 'Saldo insuficiente no cartão.',
        'cc_rejected_bad_filled_card_number' => 'Confira os dados do cartão.',
        'cc_rejected_bad_filled_date' => 'Confira os dados do cartão.',
        'cc_rejected_bad_filled_security_code' => 'Confira os dados do cartão.',
        'cc_rejected_bad_filled_other' => 'Confira os dados do cartão.',
        'cc_rejected_call_for_authorize' => 'Autorize o pagamento com o banco emissor e tente de novo.',
        'cc_rejected_card_disabled' => 'Cartão desativado. Fale com o banco emissor.',
        'cc_rejected_duplicated_payment' => 'Pagamento duplicado. Aguarde alguns minutos.',
        'cc_rejected_high_risk' => 'Pagamento recusado por segurança. Tente outro meio.',
        'cc_rejected_max_attempts' => 'Limite de tentativas atingido. Tente outro cartão.',
    ];

    public function recusa(?string $status_detail): string
    {
        return self::RECUSAS[$status_detail] ?? self::PADRAO;
    }
}
