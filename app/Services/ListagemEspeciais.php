<?php

namespace App\Services;

use App\Models\Especiais;
use App\Models\EspeciaisOpcoes;
use Illuminate\Database\Eloquent\Builder;

/**
 * Listagem pública das categorias especiais (spec 006, R-12), no mesmo formato da listagem de jogos
 * para a tela usar o menu, a busca e a rolagem infinita: cada categoria é um "campeonato" com as
 * opções, e o menu tem o país "Especiais" com as categorias.
 */
class ListagemEspeciais
{
    private const POR_PAGINA_PADRAO = 50;

    public function __construct(private RegrasExibicao $regras) {}

    /**
     * @param  array<string, mixed>  $filtros  busca, especial (id da categoria), pagina e por_pagina
     * @return array<string, mixed>
     */
    public function listar(Publico $publico, array $filtros): array
    {
        $consulta = Especiais::visiveis()
            ->when(filled($filtros['busca'] ?? null), fn (Builder $c) => $c->where('nome', 'like', '%'.addcslashes($filtros['busca'], '%_\\').'%'));

        // sem outros esportes liberados, Especiais não aparece (mesma regra da barra de esportes)
        if (! ($this->regras->configuracao($publico)?->apostar_outros_esportes ?? true)) {
            $consulta->whereRaw('1 = 0');
        }

        $opcoes_ativas = fn ($opcoes) => $opcoes->where('ativo', true)->orderBy('id');

        // menu: todas as categorias do filtro, com a quantidade de opções ativas
        $menu = (clone $consulta)->withCount(['opcoes' => $opcoes_ativas])->orderBy('nome')->orderBy('data_limite')->get();

        $pagina = $consulta
            ->when(filled($filtros['especial'] ?? null), fn (Builder $c) => $c->whereKey((int) $filtros['especial']))
            ->with(['opcoes' => $opcoes_ativas])
            ->orderBy('nome')
            ->orderBy('data_limite')
            ->paginate($filtros['por_pagina'] ?? self::POR_PAGINA_PADRAO, ['*'], 'pagina', $filtros['pagina'] ?? 1);

        return [
            'token_recusado' => $publico->token_recusado,
            'tipo' => 'especial',
            'total' => $pagina->total(),
            'campeonatos' => $pagina->getCollection()->map(fn (Especiais $especial) => [
                'id' => $especial->id,
                'nome' => $especial->nome,
                'data_limite' => $especial->data_limite->setTimezone('-03:00')->toIso8601String(),
                'opcoes' => $especial->opcoes->map(fn (EspeciaisOpcoes $opcao) => [
                    'id' => $opcao->id,
                    'nome' => $opcao->nome,
                    'cotacao' => (string) $opcao->cotacao,
                ])->values()->all(),
            ])->values()->all(),
            'paises' => $menu->isEmpty() ? [] : [[
                'pais' => 'Especiais',
                'campeonatos' => $menu->map(fn (Especiais $especial) => [
                    'id' => $especial->id,
                    'nome' => $especial->nome,
                    'quantidade_confrontos' => (int) $especial->opcoes_count,
                    'bandeira' => null,
                ])->values()->all(),
            ]],
            'meta' => [
                'pagina_atual' => $pagina->currentPage(),
                'por_pagina' => $pagina->perPage(),
                'ultima_pagina' => $pagina->lastPage(),
                'total' => $pagina->total(),
            ],
        ];
    }
}
