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
 * Boas-vindas do cliente que informou e-mail no cadastro (spec 012, FR-013a, research R-21), no
 * visual do CadastroMail antigo, sem o link de confirmação (a conta já nasce ativa).
 */
class ClienteCadastroRealizado extends Mailable
{
    use DadosEmail, Queueable, SerializesModels;

    public function __construct(public Clientes $cliente) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[ CADASTRO ] Cadastro realizado com sucesso!');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.clientes.cadastro_realizado', with: [
            ...$this->dados_email(),
            'nome' => $this->cliente->nome,
            'link' => url('/'),
        ]);
    }
}
