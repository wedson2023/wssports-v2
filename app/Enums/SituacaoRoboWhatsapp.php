<?php

namespace App\Enums;

/**
 * Situação do robô do WhatsApp na Evolution API (spec 021, data-model §3): desconectado, aguardando
 * a leitura do QR code ou conectado. "Serviço indisponível" não é gravado: é a resposta quando a
 * consulta à Evolution falha.
 */
enum SituacaoRoboWhatsapp: string
{
    case Desconectado = 'Desconectado';
    case AguardandoLeitura = 'Aguardando leitura';
    case Conectado = 'Conectado';

    /**
     * Situação a partir do estado da conexão na Evolution API (open, connecting ou close).
     */
    public static function do_estado(string $estado): self
    {
        return match ($estado) {
            'open' => self::Conectado,
            'connecting' => self::AguardandoLeitura,
            default => self::Desconectado,
        };
    }
}
