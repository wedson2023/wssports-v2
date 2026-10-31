<?php

namespace App\Mail;

use App\Mail\Concerns\DadosEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Alerta de queda do robô do WhatsApp (spec 025, FR-014): sempre por e-mail, porque o WhatsApp é o
 * canal que caiu.
 */
class AdminRoboDesconectado extends Mailable
{
    use DadosEmail, Queueable;

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[ '.$this->dados_email()['nome_sistema'].' ] Robô do WhatsApp desconectado');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin.robo_desconectado', with: [
            ...$this->dados_email(),
            'link' => url('/painel/configuracoes?aba=integracoes'),
        ]);
    }
}
