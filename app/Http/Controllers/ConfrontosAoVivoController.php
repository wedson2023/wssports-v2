<?php

namespace App\Http\Controllers;

use App\Enums\SituacaoAoVivo;
use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Resources\ConfrontosAoVivoResource;
use App\Models\Configuracoes;
use App\Models\ConfrontosAoVivo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Jogos em andamento no painel, dentro do limite de permanência, com a indicação de travado.
 */
class ConfrontosAoVivoController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public static function middleware(): array
    {
        return [
            self::permissao_cliente('confrontos.listar'),
        ];
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate(
            ['por_pagina' => ['nullable', 'integer', 'between:1,100']],
            [
                'por_pagina.integer' => 'O campo por página deve ser um número inteiro.',
                'por_pagina.between' => 'O campo por página deve estar entre 1 e 100.',
            ],
        );

        $jogos = ConfrontosAoVivo::whereIn('situacao', array_column(SituacaoAoVivo::cases(), 'value'))
            ->where('ultima_atualizacao_em', '>=', now()->subMinutes(Configuracoes::atual()->minutos_permanencia_ao_vivo))
            ->orderBy('data_inicio')
            ->paginate($filtros['por_pagina'] ?? 20)
            ->withQueryString();

        return ConfrontosAoVivoResource::collection($jogos);
    }
}
