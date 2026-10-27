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
 * Lançamento à mão do resultado de um jogo (spec 009, US7, alterado pela spec 023): grava pelo
 * mesmo método da carga automática e apura as apostas do jogo. Vale para qualquer jogo, como o
 * antigo; um resultado diferente trazido depois pela carga do provedor substitui o lançado.
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
        // qualquer jogo, do provedor ou manual, como o antigo (spec 023); só depois do início (R-21)
        if ($confronto->data_inicio->isFuture()) {
            throw ValidationException::withMessages(['confronto' => 'O resultado só pode ser lançado depois do início do jogo.']);
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
