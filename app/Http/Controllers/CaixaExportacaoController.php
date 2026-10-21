<?php

namespace App\Http\Controllers;

use App\Enums\Funcao;
use App\Http\Controllers\Concerns\GarantirAlcanceCaixa;
use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\CaixaPeriodoRequest;
use App\Services\ExportacaoCaixa;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Exportação do caixa em planilha (spec 017, US6, contracts/api.md §5.1): escopo `rede` (o foco e
 * o nível abaixo) ou `clientes` (só Admin e Supervisor com a permissão do Caixa clientes).
 */
class CaixaExportacaoController extends Controller implements HasMiddleware
{
    use GarantirAlcanceCaixa, GarantirPermissaoCliente;

    public function __construct(private readonly ExportacaoCaixa $exportacao) {}

    public static function middleware(): array
    {
        return [
            self::permissao_cliente('caixa.exportar'),
        ];
    }

    public function __invoke(CaixaPeriodoRequest $request): BinaryFileResponse
    {
        $escopo = $request->validate(
            ['escopo' => ['nullable', 'in:rede,clientes']],
            ['escopo.in' => 'O escopo deve ser rede ou clientes.'],
        )['escopo'] ?? 'rede';

        [$inicio, $fim] = $request->intervalo();
        $de = $request->validated('de');
        $ate = $request->validated('ate');

        if ($escopo === 'clientes') {
            $solicitante = $request->user();

            abort_unless(
                Funcao::usuario_pode($solicitante, 'caixa.ver_clientes') && in_array($solicitante->funcao(), [Funcao::Admin, Funcao::Supervisor], true),
                403,
                'Você não tem permissão para esta ação.',
            );

            $caminho = $this->exportacao->clientes($inicio, $fim, $de, $ate);
            $nome = "caixa-clientes-{$de}-{$ate}.xlsx";
        } else {
            $foco = $this->foco_caixa($request, $request->filled('usuarios_id') ? (int) $request->validated('usuarios_id') : null);
            $caminho = $this->exportacao->rede($foco, $inicio, $fim, $request->tipo(), $de, $ate);
            $nivel = $foco ? Str::slug($foco->nome) : 'banca';
            $nome = "caixa-{$nivel}-{$de}-{$ate}.xlsx";
        }

        return response()->download($caminho, $nome, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }
}
