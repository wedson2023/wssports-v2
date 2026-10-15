<?php

namespace App\Http\Controllers;

use App\Enums\OrigemApuracao;
use App\Enums\SituacaoConfronto;
use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\ConfrontosResultadosRequest;
use App\Http\Resources\ConfrontosResultadosResource;
use App\Models\Confrontos;
use App\Models\ConfrontosResultados;
use App\Services\ImportacaoResultados;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Validation\ValidationException;

/**
 * Lançamento à mão do resultado de um jogo manual (spec 009, US7): grava pelo mesmo método da
 * carga automática e apura as apostas do jogo.
 */
class ConfrontosResultadosController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public static function middleware(): array
    {
        return [self::permissao_cliente('confrontos.lancar_resultado')];
    }

    public function update(ConfrontosResultadosRequest $request, Confrontos $confronto, ImportacaoResultados $importacao): ConfrontosResultadosResource
    {
        if (! $confronto->manual) {
            throw ValidationException::withMessages(['confronto' => 'O resultado de jogos do provedor vem da carga automática.']);
        }

        $dados = $request->validated();

        $gravacao = $importacao->gravar_resultado($confronto, [
            ...$dados,
            'situacao' => SituacaoConfronto::Encerrado->value,
            // sem o campo, os gols gravados são mantidos; lista vazia apaga os gols
            'gols' => $request->has('gols') ? array_values($dados['gols'] ?? []) : null,
        ], OrigemApuracao::LançamentoManual);

        $resultado = ConfrontosResultados::with('confronto.gols')->where('confrontos_id', $confronto->id)->firstOrFail();

        return new ConfrontosResultadosResource($resultado, $gravacao['apostas']);
    }
}
