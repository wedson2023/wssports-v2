<?php

namespace App\Http\Controllers;

use App\Enums\TipoGanhoAfiliado;
use App\Http\Controllers\Concerns\GarantirAlcanceAfiliado;
use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\ComissaoAfiliadoRequest;
use App\Http\Resources\AfiliadosComissoesResource;
use App\Models\Afiliados;
use App\Services\RegraComissaoAfiliados;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Regras de comissão do afiliado no painel (spec 012, FR-027 e FR-028): histórico e troca.
 */
class AfiliadosComissoesController extends Controller implements HasMiddleware
{
    use GarantirAlcanceAfiliado, GarantirPermissaoCliente;

    public static function middleware(): array
    {
        return [
            self::permissao_cliente('afiliados.consultar', only: ['index']),
            self::permissao_cliente('afiliados.editar_comissao', only: ['update']),
        ];
    }

    public function index(Request $request, Afiliados $afiliado): AnonymousResourceCollection
    {
        $this->garantir_alcance($request, $afiliado);

        $por_pagina = $request->validate(
            ['por_pagina' => ['nullable', 'integer', 'between:1,100']],
            ['por_pagina.between' => 'O campo por página deve estar entre 1 e 100.', 'por_pagina.integer' => 'O campo por página deve ser um número inteiro.'],
        )['por_pagina'] ?? 20;

        $regras = $afiliado->comissoes()->with('usuario')->latest('vigente_desde')->latest('id')->paginate($por_pagina)->withQueryString();

        return AfiliadosComissoesResource::collection($regras);
    }

    /**
     * Encerra a regra vigente e abre a nova; os lançamentos já gerados não mudam.
     */
    public function update(ComissaoAfiliadoRequest $request, Afiliados $afiliado, RegraComissaoAfiliados $regras): AfiliadosComissoesResource
    {
        $this->garantir_alcance($request, $afiliado);

        $regra = $regras->trocar(
            $afiliado,
            TipoGanhoAfiliado::from($request->validated('tipo_ganho')),
            (string) $request->validated('percentual'),
            $request->user(),
        );

        return new AfiliadosComissoesResource($regra->load('usuario'));
    }
}
