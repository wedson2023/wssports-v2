<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\LimiteConfrontoRequest;
use App\Models\Confrontos;
use App\Models\ConfrontosAoVivo;
use App\Services\CriacaoApostas;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Limite de valor apostado por confronto (FR-063), separado no pré-jogo e no ao vivo.
 */
class ConfrontosLimitesController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public function __construct(private CriacaoApostas $criacao) {}

    public static function middleware(): array
    {
        return [
            self::permissao_cliente('confrontos.alterar_limite'),
        ];
    }

    public function pre_jogo(LimiteConfrontoRequest $request, Confrontos $confronto): JsonResponse
    {
        $confronto->update(['limite_valor_apostado' => $request->validated('limite_valor_apostado')]);

        return $this->resposta($confronto->id, (string) $confronto->limite_valor_apostado, 'confrontos_id');
    }

    public function ao_vivo(LimiteConfrontoRequest $request, ConfrontosAoVivo $confronto_ao_vivo): JsonResponse
    {
        $confronto_ao_vivo->update(['limite_valor_apostado' => $request->validated('limite_valor_apostado')]);

        return $this->resposta($confronto_ao_vivo->id, (string) $confronto_ao_vivo->limite_valor_apostado, 'confrontos_ao_vivo_id');
    }

    private function resposta(int $id, string $limite, string $coluna): JsonResponse
    {
        return response()->json(['data' => [
            'id' => $id,
            'limite_valor_apostado' => $limite,
            'valor_apostado' => bcadd((string) ($this->criacao->valor_apostado($coluna, [$id])[$id] ?? '0'), '0', 2),
        ]]);
    }
}
