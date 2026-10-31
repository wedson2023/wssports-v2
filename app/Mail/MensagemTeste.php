<?php

namespace App\Mail;

use App\Mail\Concerns\DadosEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * E-mail do botão de teste da tela Configurações (spec 025, US5).
 */
class MensagemTeste extends Mailable
{
    use DadosEmail, Queueable;

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[ '.$this->dados_email()['nome_sistema'].' ] Teste de e-mail');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin.teste', with: $this->dados_email());
    }
}
