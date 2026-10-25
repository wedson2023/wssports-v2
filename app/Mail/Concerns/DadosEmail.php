<?php

namespace App\Mail\Concerns;

use App\Models\Configuracoes;

/**
 * Dados comuns aos e-mails (logo, nome do sistema e cor do botão), lidos de um único lugar (spec
 * 012, R-10 e R-19); a cor vem do tema configurado desde a spec 021.
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
            // cor principal do tema configurada (spec 021)
            'cor_principal' => $configuracoes->tema()['temas'],
        ];
    }
}
