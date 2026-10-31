<?php

namespace App\Mail;

use App\Mail\Concerns\DadosEmail;
use App\Models\Clientes;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Código de recuperação de senha do cliente (spec 025, FR-027). O código vai em texto só na mensagem;
 * no banco fica o hash.
 */
class ClienteCodigoRecuperacao extends Mailable
{
    use DadosEmail, Queueable, SerializesModels;

    public function __construct(public Clientes $cliente, public string $codigo, public int $validade_minutos) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[ '.$this->dados_email()['nome_sistema'].' ] Seu código de recuperação');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.clientes.codigo_recuperacao', with: [
            ...$this->dados_email(),
            'nome' => $this->cliente->nome,
            'codigo' => $this->codigo,
            'validade_minutos' => $this->validade_minutos,
        ]);
    }
}
