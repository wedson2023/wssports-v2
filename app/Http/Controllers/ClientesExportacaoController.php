<?php

namespace App\Http\Controllers;

use App\Enums\Funcao;
use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\RankingClientesRequest;
use App\Services\ExportacaoClientes;
use App\Services\FiltrosClientes;
use App\Services\ListagemSaques;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Planilha da aba atual da tela Clientes (spec 019, FR-030): exige a permissão de exportar (só Admin
 * e Supervisor) e a permissão da própria aba.
 */
class ClientesExportacaoController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    /**
     * Permissão de cada aba, além de clientes.exportar.
     */
    private const PERMISSAO_DA_ABA = [
        'cadastros' => 'clientes.listar',
        'saques' => 'saques.gerenciar',
        'ranking' => 'clientes.ver_ranking',
        'lixeira' => 'clientes.restaurar',
    ];

    /**
     * Tempo máximo para montar a planilha: com dezenas de milhares de linhas, a biblioteca de
     * planilhas leva bem mais que o limite padrão de uma requisição.
     */
    private const TEMPO_MAXIMO_SEGUNDOS = 180;

    public function __construct(
        private readonly ExportacaoClientes $exportacao,
        private readonly FiltrosClientes $filtros,
        private readonly ListagemSaques $saques,
    ) {}

    public static function middleware(): array
    {
        return [self::permissao_cliente('clientes.exportar')];
    }

    public function __invoke(Request $request): BinaryFileResponse
    {
        $aba = $request->validate(
            ['aba' => ['required', Rule::in(array_keys(self::PERMISSAO_DA_ABA))]],
            ['aba.required' => 'Informe a aba.', 'aba.in' => 'A aba deve ser cadastros, saques, ranking ou lixeira.'],
        )['aba'];

        $usuario = $request->user();
        abort_unless(Funcao::usuario_pode($usuario, self::PERMISSAO_DA_ABA[$aba]), 403, 'Você não tem permissão para esta ação.');

        set_time_limit(self::TEMPO_MAXIMO_SEGUNDOS);

        $caminho = match ($aba) {
            'saques' => $this->exportacao->saques($usuario, $request, $this->saques->validar($request)),
            'ranking' => $this->ranking($request),
            default => $this->exportacao->clientes($usuario, $request, $this->filtros->validar($request), $aba === 'lixeira'),
        };

        return response()->download($caminho, 'clientes-'.$aba.'-'.now()->toDateString().'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    /**
     * Ranking com as mesmas regras de período e critério da aba.
     */
    private function ranking(Request $request): string
    {
        $ranking = RankingClientesRequest::createFrom($request);
        $filtros = $request->validate(
            array_diff_key($ranking->rules(), array_flip(['page', 'por_pagina'])),
            $ranking->messages(),
        );

        $criterio = $filtros['criterio'] ?? 'depositos';
        [$inicio, $fim] = RankingClientesRequest::intervalo_de($filtros['periodo'] ?? '30d', $filtros['de'] ?? null, $filtros['ate'] ?? null);

        return $this->exportacao->ranking($request->user(), $criterio, $inicio, $fim);
    }
}
