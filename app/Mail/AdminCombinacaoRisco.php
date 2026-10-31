<?php

namespace App\Mail;

use App\Mail\Concerns\DadosEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Aviso de combinação de risco nova ao Admin (spec 025, FR-027), com os mesmos dados do WhatsApp.
 */
class AdminCombinacaoRisco extends Mailable
{
    use DadosEmail, Queueable;

    public function __construct(public string $texto) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[ '.$this->dados_email()['nome_sistema'].' ] Combinação de risco nova');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin.combinacao_risco', with: [
            ...$this->dados_email(),
            'texto' => $this->texto,
            'link' => url('/painel'),
        ]);
    }
}
