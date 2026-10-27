<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\FiltrosConfrontosPainelRequest;
use App\Http\Resources\ApostasConfrontoResource;
use App\Http\Resources\ConfrontosResource;
use App\Models\Confrontos;
use App\Services\ExposicaoConfrontos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Exposição dos jogos na tela Confrontos (spec 023): aba Mais apostados, aba Pendentes e, na gaveta,
 * o ranking das opções e as apostas do jogo. O alcance é o da tela Apostas.
 */
class ConfrontosApostasController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public function __construct(private ExposicaoConfrontos $exposicao) {}

    public static function middleware(): array
    {
        return [
            self::permissao_cliente('confrontos.listar', only: ['mais_apostados', 'ranking', 'apostas']),
            // lançar resultado é só do Admin (spec 023, R-12)
            self::permissao_cliente('confrontos.lancar_resultado', only: ['pendentes']),
        ];
    }

    public function mais_apostados(FiltrosConfrontosPainelRequest $request): AnonymousResourceCollection
    {
        return ConfrontosResource::collection(
            $this->exposicao->mais_apostados($request->user(), $request->filtros(), $request->por_pagina())
        );
    }

    public function pendentes(Request $request): JsonResponse
    {
        $dados = $this->validar_pagina($request, ['busca' => ['nullable', 'string', 'max:150']]);
        $busca = trim((string) ($dados['busca'] ?? ''));

        $pagina = $this->exposicao->pendentes($request->user(), $busca !== '' ? $busca : null)
            ->paginate($dados['por_pagina'] ?? 20)
            ->withQueryString();

        return response()->json([
            ...$pagina->toArray(),
            'data' => collect($pagina->items())->map(fn (Confrontos $confronto) => [
                'id' => $confronto->id,
                'time_casa' => $confronto->time_casa,
                'time_fora' => $confronto->time_fora,
                'campeonato' => [
                    'id' => $confronto->campeonato->id,
                    'nome' => $confronto->campeonato->nome,
                    'pais' => $confronto->campeonato->pais,
                ],
                'data_inicio' => $confronto->data_inicio,
                'atraso_minutos' => (int) $confronto->atraso_minutos,
                'apostas' => (int) $confronto->apostas,
                'valor_em_aberto' => ConfrontosResource::valor($confronto->valor_em_aberto),
                'manual' => $confronto->manual,
            ]),
        ]);
    }

    public function ranking(Request $request, Confrontos $confronto): JsonResponse
    {
        return response()->json(['data' => $this->exposicao->ranking($confronto, $request->user())]);
    }

    public function apostas(Request $request, Confrontos $confronto): AnonymousResourceCollection
    {
        $dados = $this->validar_pagina($request);

        return ApostasConfrontoResource::collection(
            $this->exposicao->apostas($confronto, $request->user(), $dados['por_pagina'] ?? 30)->withQueryString()
        );
    }

    /**
     * @param  array<string, mixed>  $regras
     * @return array<string, mixed>
     */
    private function validar_pagina(Request $request, array $regras = []): array
    {
        return $request->validate([
            ...$regras,
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ], [
            'busca.max' => 'A busca deve ter no máximo 150 caracteres.',
            'por_pagina.integer' => 'O campo por página deve ser um número inteiro.',
            'por_pagina.between' => 'O campo por página deve estar entre 1 e 100.',
        ]);
    }
}
