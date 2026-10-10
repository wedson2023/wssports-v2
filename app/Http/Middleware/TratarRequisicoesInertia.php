<?php

namespace App\Http\Middleware;

use App\Fakes\DadosFake;
use App\Models\Configuracoes;
use Closure;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware do Inertia no site: props compartilhadas por todas as páginas e respostas sem cache
 * no navegador (research.md, R-15). A versão do build vem da classe do pacote (hash do manifesto
 * do Vite); quando muda, o navegador recarrega sozinho na versão nova.
 */
class TratarRequisicoesInertia extends Middleware
{
    protected $rootView = 'app';

    /**
     * Props enviadas a todas as páginas (contracts/paginas.md).
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'versao' => $this->version($request),
            'nome_sistema' => Configuracoes::atual()->nome_sistema,
            // cores ainda fake; a logo vem da configuração (spec 006)
            'tema' => [...DadosFake::tema(), 'logo' => Configuracoes::atual()->url_logo()],
            'contatos' => DadosFake::contatos(),
            'indicadores' => DadosFake::indicadores(),
        ];
    }

    public function handle(Request $request, Closure $next): Response
    {
        $resposta = parent::handle($request, $next);

        // páginas e recargas parciais trazem jogos e cotações: nunca guardar em cache
        $resposta->headers->set('Cache-Control', 'no-cache, private');

        return $resposta;
    }
}
