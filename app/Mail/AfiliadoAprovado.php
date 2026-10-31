<?php

namespace App\Mail;

use App\Mail\Concerns\DadosEmail;
use App\Models\Afiliados;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Cadastro do afiliado aprovado, com o endereço para entrar (spec 012, FR-013; FiliadoMail do antigo).
 */
class AfiliadoAprovado extends Mailable
{
    use DadosEmail, Queueable, SerializesModels;

    public function __construct(public Afiliados $afiliado) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[ CADASTRO ] Seu cadastro de afiliado foi aprovado');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.afiliados.aprovado', with: [
            ...$this->dados_email(),
            'nome' => $this->afiliado->nome,
            'link' => url('/cadastro-afiliado'),
        ]);
    }
}
