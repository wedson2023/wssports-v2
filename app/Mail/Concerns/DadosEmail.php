<?php

namespace App\Mail\Concerns;

use App\Fakes\DadosFake;
use App\Models\Configuracoes;

/**
 * Dados comuns aos e-mails (logo, nome do sistema e cor do botão), lidos de um único lugar para a
 * troca do tema fake não exigir mexer nos e-mails (spec 012, R-10 e R-19).
 */
trait DadosEmail
{
    /**
     * @return array{logo: string, nome_sistema: string, cor_principal: string}
     */
    protected function dados_email(): array
    {
        $configuracoes = Configuracoes::atual();

        return [
            'logo' => $configuracoes->url_logo(),
            'nome_sistema' => (string) $configuracoes->nome_sistema,
            // FAKE — tema da spec 005 (DadosFake::tema), ver research R-19 da spec 012
            'cor_principal' => DadosFake::tema()['temas'],
        ];
    }
}
