<?php

namespace App\Enums;

/**
 * Preferência do apostador sobre mudanças de cotação entre o que viu e o envio.
 */
enum AceitarAlteracoes: string
{
    case Nenhuma = 'Nenhuma';
    case SomenteParaMaior = 'Somente para maior';
    case Qualquer = 'Qualquer';

    /**
     * Se a mudança de cotação (vista → atual) é aceita sem pedir confirmação.
     */
    public function aceita(string $cotacao_vista, string $cotacao_atual): bool
    {
        $comparacao = bccomp($cotacao_atual, $cotacao_vista, 2);

        return match ($this) {
            self::Nenhuma => $comparacao === 0,
            self::SomenteParaMaior => $comparacao >= 0,
            self::Qualquer => true,
        };
    }
}
