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
 * Cadastro público do afiliado recebido e em análise (spec 012, FR-013; FiliadoMail do antigo).
 */
class AfiliadoCadastroRecebido extends Mailable implements ShouldQueue
{
    use DadosEmail, Queueable, SerializesModels;

    public function __construct(public Afiliados $afiliado)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[ CADASTRO ] Recebemos seu cadastro de afiliado');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.afiliados.cadastro_recebido', with: [
            ...$this->dados_email(),
            'nome' => $this->afiliado->nome,
        ]);
    }
}
