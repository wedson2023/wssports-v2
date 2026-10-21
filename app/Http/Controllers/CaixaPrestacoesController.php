<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirAlcanceCaixa;
use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\DesfazerPrestacaoCaixaRequest;
use App\Http\Requests\PrestacaoContasCaixaRequest;
use App\Http\Requests\PrestacaoContasLoteCaixaRequest;
use App\Http\Resources\CaixaPrestacoesContasResource;
use App\Models\CaixaPrestacoesContas;
use App\Models\Usuarios;
use App\Services\PrestacaoContasCaixa;
use App\Support\FusoSistema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Prestação de contas do caixa (spec 017, US2, contracts/api.md §3): resumo e prestação de um
 * vendedor, resumo e prestação em lote dos vendedores de um gerente, e desfazer a última.
 */
class CaixaPrestacoesController extends Controller implements HasMiddleware
{
    use GarantirAlcanceCaixa, GarantirPermissaoCliente;

    public function __construct(private readonly PrestacaoContasCaixa $prestacoes) {}

    public static function middleware(): array
    {
        return [
            self::permissao_cliente('caixa.prestar_contas'),
        ];
    }

    public function resumo_vendedor(Request $request, Usuarios $usuario): JsonResponse
    {
        $this->garantir_vendedor($request, $usuario);

        return response()->json($this->prestacoes->resumo($usuario, $this->ate_do_resumo($request, $usuario)));
    }

    public function store_vendedor(PrestacaoContasCaixaRequest $request, Usuarios $usuario): JsonResponse
    {
        $this->garantir_vendedor($request, $usuario);
        $this->garantir_depois_da_ultima($usuario, $request->ate());

        $prestacao = $this->prestacoes->prestar($usuario, $request->user(), $request->ate(), $request->valor_recebido(), $request->validated('observacao'));

        return (new CaixaPrestacoesContasResource($prestacao->load('autor')))->response()->setStatusCode(201);
    }

    public function resumo_gerente(Request $request, Usuarios $usuario): JsonResponse
    {
        $this->garantir_gerente($request, $usuario);

        return response()->json($this->prestacoes->resumo_lote($usuario, $this->ate_do_resumo($request, null)));
    }

    public function store_gerente(PrestacaoContasLoteCaixaRequest $request, Usuarios $usuario): JsonResponse
    {
        $this->garantir_gerente($request, $usuario);

        return response()->json($this->prestacoes->prestar_lote($usuario, $request->user(), $request->ate(), $request->validated('observacao')), 201);
    }

    public function desfazer(DesfazerPrestacaoCaixaRequest $request, CaixaPrestacoesContas $prestacao): JsonResponse
    {
        $this->garantir_vendedor($request, Usuarios::withTrashed()->findOrFail($prestacao->usuarios_id));

        $prestacao = $this->prestacoes->desfazer($prestacao, $request->user(), $request->validated('motivo'));

        return (new CaixaPrestacoesContasResource($prestacao->load(['autor', 'desfeita_por_usuario'])))->response();
    }

    /**
     * `ate` do resumo (horário de Brasília; vazio = agora), não futuro e, no vendedor, depois da
     * última prestação.
     */
    private function ate_do_resumo(Request $request, ?Usuarios $vendedor): Carbon
    {
        $request->validate(['ate' => ['nullable', 'date']], ['ate.date' => 'A data informada é inválida.']);

        $ate = $request->filled('ate') ? Carbon::parse($request->input('ate'), FusoSistema::FUSO)->utc() : now();

        if ($ate->greaterThan(now())) {
            throw ValidationException::withMessages(['ate' => 'A data e hora não pode ser futura.']);
        }

        if ($vendedor !== null) {
            $this->garantir_depois_da_ultima($vendedor, $ate);
        }

        return $ate;
    }

    private function garantir_depois_da_ultima(Usuarios $vendedor, Carbon $ate): void
    {
        $ultima = $this->prestacoes->ultima($vendedor);

        if ($ultima !== null && $ate->lessThanOrEqualTo($ultima->periodo_fim)) {
            throw ValidationException::withMessages(['ate' => 'A data deve ser posterior à última prestação de contas ('.$ultima->periodo_fim->setTimezone(FusoSistema::FUSO)->format('d/m/Y H:i').').']);
        }
    }
}
