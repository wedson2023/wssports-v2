<?php

namespace App\Mail;

use App\Mail\Concerns\DadosEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Aviso de saque aguardando há 30 minutos (spec 025, FR-011). O botão leva ao painel, que exige login;
 * nenhuma baixa é feita pelo link.
 */
class AdminSaqueParado extends Mailable
{
    use DadosEmail, Queueable;

    public function __construct(public string $nome, public string $valor, public string $tipo_chave, public string $chave) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[ '.$this->dados_email()['nome_sistema'].' ] Saque aguardando há 30 minutos');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin.saque_parado', with: [
            ...$this->dados_email(),
            'link' => url('/painel/clientes'),
        ]);
    }
}
