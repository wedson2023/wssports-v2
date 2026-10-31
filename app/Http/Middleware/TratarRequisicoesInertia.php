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
        // uma leitura do registro por pedido
        $configuracoes = Configuracoes::atual();

        return [
            ...parent::share($request),
            'versao' => $this->version($request),
            'nome_sistema' => $configuracoes->nome_sistema,
            // tema, logo e contatos da configuração (spec 021)
            'tema' => $configuracoes->tema(),
            'contatos' => $configuracoes->contatos(),
            // Google Tag Manager das páginas do apostador (spec 021, FR-009)
            'rastreamento' => ['gtm_id' => $configuracoes->gtm_id],
            // Acumuladão ainda fake; o cassino vem da configuração (spec 011, FR-035)
            'indicadores' => [...DadosFake::indicadores(), 'cassino' => (bool) $configuracoes->cassino_habilitado],
            // com a confirmação ligada, o e-mail é obrigatório no cadastro (spec 025, FR-029)
            'confirmacao_cadastro' => (bool) $configuracoes->confirmacao_cadastro,
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
