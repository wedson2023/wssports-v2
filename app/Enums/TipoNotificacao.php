<?php

namespace App\Enums;

/**
 * Tipo de aviso push enviado ao cliente (spec 010, FR-041 a FR-044).
 */
enum TipoNotificacao: string
{
    case Gol = 'Gol';
    case GolAnulado = 'Gol anulado';
    case Intervalo = 'Intervalo';
    case FimDeJogo = 'Fim de jogo';
    case ResultadoAposta = 'Resultado da aposta';
    case EncerramentoAutomático = 'Encerramento automático';
    // aviso manual enviado pelo painel (spec 019, FR-031 a FR-033a)
    case AvisoBanca = 'Aviso da banca';

    /**
     * Coluna da inscrição que liga ou desliga este tipo de aviso no aparelho.
     */
    public function coluna_preferencia(): string
    {
        return match ($this) {
            self::Gol, self::GolAnulado => 'receber_gols',
            self::Intervalo => 'receber_intervalo',
            self::FimDeJogo => 'receber_fim_jogo',
            self::ResultadoAposta => 'receber_resultado_aposta',
            self::EncerramentoAutomático => 'receber_encerramento_automatico',
            self::AvisoBanca => 'receber_avisos_banca',
        };
    }

    /**
     * Aviso de um evento de jogo; o resto é aviso de aposta.
     */
    public function e_de_jogo(): bool
    {
        return in_array($this, [self::Gol, self::GolAnulado, self::Intervalo, self::FimDeJogo], true);
    }

    public static function do_evento(TipoEventoConfronto $evento): self
    {
        return match ($evento) {
            TipoEventoConfronto::Gol => self::Gol,
            TipoEventoConfronto::GolAnulado => self::GolAnulado,
            TipoEventoConfronto::Intervalo => self::Intervalo,
            TipoEventoConfronto::FimDeJogo => self::FimDeJogo,
        };
    }
}
