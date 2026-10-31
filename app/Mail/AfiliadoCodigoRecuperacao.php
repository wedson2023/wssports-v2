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
 * Código de "Esqueci minha senha" do afiliado (spec 012, FR-021a). O código vai em texto só no
 * e-mail; no banco fica o hash.
 */
class AfiliadoCodigoRecuperacao extends Mailable
{
    use DadosEmail, Queueable, SerializesModels;

    public function __construct(public Afiliados $afiliado, public string $codigo, public int $validade_minutos) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[ RECUPERAÇÃO ] Seu código para trocar a senha');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.afiliados.codigo_recuperacao', with: [
            ...$this->dados_email(),
            'nome' => $this->afiliado->nome,
            'codigo' => $this->codigo,
            'validade_minutos' => $this->validade_minutos,
        ]);
    }
}
