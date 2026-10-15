<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\ListagemEncerramentosRequest;
use App\Http\Resources\ApostasEncerramentosResource;
use App\Models\ApostasEncerramentos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Encerramentos de aposta no painel (spec 010, FR-037): Admin e Supervisor, com filtros por período,
 * cliente, modalidade e situação, o valor pago e quanto a aposta teria pago.
 */
class ApostasEncerramentosController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public static function middleware(): array
    {
        return [self::permissao_cliente('apostas_encerramentos.listar')];
    }

    public function index(ListagemEncerramentosRequest $request): AnonymousResourceCollection
    {
        [$inicio, $fim] = $request->periodo();

        $consulta = ApostasEncerramentos::query()
            ->whereBetween('created_at', [$inicio, $fim])
            ->when($request->validated('clientes_id'), fn (Builder $c, $id) => $c->where('clientes_id', $id))
            ->when($request->validated('modalidade'), fn (Builder $c, $modalidade) => $c->where('modalidade', $modalidade))
            ->when($request->validated('situacao'), fn (Builder $c, $situacao) => $c->where('situacao', $situacao));

        $totais = $this->totais(clone $consulta);

        $encerramentos = $consulta
            ->with(['aposta.palpites_ativos', 'cliente', 'usuario'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->por_pagina(), ['*'], 'pagina', $request->validated('pagina'))
            ->withQueryString();

        return ApostasEncerramentosResource::collection($encerramentos)->additional(['totais' => $totais]);
    }

    /**
     * Soma do valor pago (só encerramentos que valem: registrados ou creditados) e de quanto as
     * apostas teriam pago, na consulta filtrada (só as que já têm resultado entram no "teria pago").
     *
     * @return array{valor: string, teria_pago: string}
     */
    private function totais(Builder $consulta): array
    {
        $valor = '0.00';
        $teria_pago = '0.00';

        $consulta->queValem()->with('aposta.palpites_ativos')->select(['id', 'apostas_id', 'valor'])->chunkById(500, function ($encerramentos) use (&$valor, &$teria_pago) {
            foreach ($encerramentos as $encerramento) {
                $valor = bcadd($valor, (string) $encerramento->valor, 2);
                $teria = $encerramento->aposta !== null ? ApostasEncerramentosResource::teria_pago($encerramento->aposta) : null;
                $teria_pago = bcadd($teria_pago, $teria ?? '0', 2);
            }
        });

        return ['valor' => $valor, 'teria_pago' => $teria_pago];
    }
}
