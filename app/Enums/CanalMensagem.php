<?php

namespace App\Enums;

/**
 * Canal de uma mensagem do sistema (spec 025): "Os dois" só existe na escolha; o envio é sempre por
 * WhatsApp ou E-mail.
 */
enum CanalMensagem: string
{
    case WhatsApp = 'WhatsApp';
    case Email = 'E-mail';
    case OsDois = 'Os dois';

    /**
     * @return list<self>
     */
    public function canais_de_envio(): array
    {
        return $this === self::OsDois ? [self::WhatsApp, self::Email] : [$this];
    }

    public function outro(): self
    {
        return $this === self::WhatsApp ? self::Email : self::WhatsApp;
    }
}
