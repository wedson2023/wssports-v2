<?php

namespace App\Fakes;

/**
 * Local único dos dados fake do frontend (Princípio IX). Cada método devolve, no formato que o dado
 * real terá, um dado que o backend ainda não fornece; a spec indicada em cada método o substitui e
 * remove o fake correspondente. Nenhum destes dados entra no envio de uma aposta.
 */
class DadosFake
{
    /**
     * FAKE — indicador de Acumuladão. Substituído pela spec própria (o de Cassino passou a vir da
     * configuração na spec 011).
     *
     * @return array{acumuladao: bool}
     */
    public static function indicadores(): array
    {
        return [
            'acumuladao' => true,
        ];
    }
}
