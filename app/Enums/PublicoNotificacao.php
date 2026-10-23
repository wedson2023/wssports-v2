<?php

namespace App\Enums;

/**
 * Público de um envio manual de notificação push pelo painel (spec 019, FR-031).
 */
enum PublicoNotificacao: string
{
    case Todos = 'Todos';
    case PorId = 'Por ID';
    // sem apostar nem depositar há N dias, incluindo quem nunca apostou nem depositou
    case SemInteragir = 'Sem interagir';
}
