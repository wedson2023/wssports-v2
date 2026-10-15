<?php

namespace App\Http\Controllers;

use App\Support\CodigoAposta;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Página do bilhete em tempo real (/bilhete/{codigo}, spec 010). Os dados vêm da API; a página só
 * recebe o código e a conexão do tempo real, lida da configuração a cada carga (o servidor é a
 * fonte das configurações, constituição).
 */
class BilheteController extends Controller
{
    public function index(string $codigo): Response
    {
        $reverb = config('broadcasting.connections.reverb');
        $habilitado = config('broadcasting.default') === 'reverb' && ! empty($reverb['key']);

        return Inertia::render('Ticket', [
            'codigo' => CodigoAposta::normalizar($codigo),
            // sem o Reverb configurado, a página funciona só com a consulta periódica
            'tempo_real' => $habilitado ? [
                'chave' => $reverb['key'],
                'host' => $reverb['options']['host'],
                'porta' => (int) $reverb['options']['port'],
                'esquema' => $reverb['options']['scheme'],
            ] : null,
        ]);
    }
}
