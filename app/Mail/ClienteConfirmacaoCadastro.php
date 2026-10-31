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
 * Boas-vindas com o botão de confirmar o e-mail (spec 025, FR-030), quando a banca exige confirmação.
 */
class ClienteConfirmacaoCadastro extends Mailable
{
    use DadosEmail, Queueable, SerializesModels;

    public function __construct(public Clientes $cliente, public string $link) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[ '.$this->dados_email()['nome_sistema'].' ] Confirme seu e-mail');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.clientes.confirmacao_cadastro', with: [
            ...$this->dados_email(),
            'nome' => $this->cliente->nome,
            'link' => $this->link,
        ]);
    }
}
