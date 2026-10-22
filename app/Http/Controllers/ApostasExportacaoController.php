<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\ApostasFiltrosRequest;
use App\Services\ExportacaoApostas;
use Illuminate\Routing\Controllers\HasMiddleware;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Planilha .xlsx da lista de apostas do painel (spec 018, contracts/api.md §4.1): arquivo
 * temporário único por pedido, apagado depois do envio.
 */
class ApostasExportacaoController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public function __construct(private readonly ExportacaoApostas $exportacao) {}

    public static function middleware(): array
    {
        return [
            self::permissao_cliente('apostas.exportar'),
        ];
    }

    /**
     * Tempo máximo para montar a planilha: com dezenas de milhares de linhas, a biblioteca de
     * planilhas leva bem mais que o limite padrão de uma requisição.
     */
    private const TEMPO_MAXIMO_SEGUNDOS = 180;

    public function __invoke(ApostasFiltrosRequest $request): BinaryFileResponse
    {
        set_time_limit(self::TEMPO_MAXIMO_SEGUNDOS);

        $caminho = $this->exportacao->gerar($request->user(), $request->filtros());
        $nome = "apostas-{$request->validated('de')}-{$request->validated('ate')}.xlsx";

        return response()->download($caminho, $nome, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }
}
