<?php

namespace App\Http\Controllers;

use App\Enums\CategoriaPromocao;
use App\Models\Banners;
use App\Models\ClientesPromocoes;
use App\Models\Configuracoes;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Área do cassino (/cassino, spec 011): a página com o visual do screens/casino antigo. Os jogos vêm
 * da API pública (PublicoCassinoController), para filtrar e paginar sem recarregar a página.
 */
class CassinoController extends Controller
{
    public function index(): Response
    {
        $configuracoes = Configuracoes::atual();

        return Inertia::render('Casino', [
            'cassino' => [
                'habilitado' => (bool) $configuracoes->cassino_habilitado,
                'demo_habilitado' => (bool) $configuracoes->cassino_demo_habilitado,
                // sistema só de cassino: o menu lateral não mostra o atalho "Esportes"
                'somente_cassino' => (bool) $configuracoes->somente_cassino,
            ],
            'banners' => Banners::ativos(),
            'promocoes' => $this->promocoes(),
        ]);
    }

    /**
     * Página de saída do jogo (home_url do provedor, R-09): dentro da moldura, avisa a página do
     * cassino para fechar o jogo; fora dela, volta ao cassino.
     */
    public function fechar(): HttpResponse
    {
        return response()->view('close')->header('Cache-Control', 'no-store');
    }

    /**
     * Promoções vigentes do modal Promoções (modal/bonus do antigo), sem as de primeiro cadastro.
     *
     * @return list<array<string, mixed>>
     */
    private function promocoes(): array
    {
        return ClientesPromocoes::vigentes()
            ->where('categoria', '!=', CategoriaPromocao::PrimeiroCadastro)
            ->orderBy('categoria')
            ->orderBy('nome')
            ->get()
            ->map(fn (ClientesPromocoes $promocao) => [
                'id' => $promocao->id,
                'nome' => $promocao->nome,
                'categoria' => $promocao->categoria->value,
                'modalidade' => $promocao->modalidade->value,
                'tipo_ganho' => $promocao->tipo_ganho->value,
                'valor' => (string) $promocao->valor,
                'quantidade_indicacoes' => $promocao->quantidade_indicacoes,
                'data_fim' => $promocao->data_fim?->copy()->setTimezone('-03:00')->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
