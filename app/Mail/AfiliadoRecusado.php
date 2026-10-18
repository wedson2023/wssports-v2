<?php

namespace App\Mail;

use App\Mail\Concerns\DadosEmail;
use App\Models\Afiliados;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Cadastro do afiliado recusado, com o motivo quando informado (spec 012, FR-013).
 */
class AfiliadoRecusado extends Mailable implements ShouldQueue
{
    use DadosEmail, Queueable, SerializesModels;

    public function __construct(public Afiliados $afiliado)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[ CADASTRO ] Seu cadastro de afiliado não foi aprovado');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.afiliados.recusado', with: [
            ...$this->dados_email(),
            'nome' => $this->afiliado->nome,
            'motivo' => $this->afiliado->motivo_recusa,
        ]);
    }
}
