<?php

namespace App\Enums;

/**
 * Evento de um jogo detectado nas cargas, usado para avisar os clientes (spec 010, FR-042).
 */
enum TipoEventoConfronto: string
{
    case Gol = 'Gol';
    case GolAnulado = 'Gol anulado';
    case Intervalo = 'Intervalo';
    case FimDeJogo = 'Fim de jogo';
}
