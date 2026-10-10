<?php

namespace App\Http\Controllers;

use App\Http\Requests\LeituraAvisoRequest;
use App\Models\Avisos;
use App\Services\EscolhaAvisos;
use App\Services\IdentificacaoPublico;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Aviso da banca no site (sem login): o aviso a mostrar ao abrir a tela e o "Lido", por aparelho
 * e, com token de cliente, também pelo cliente (spec 006, FR-028 e FR-030).
 */
class PublicoAvisosController extends Controller
{
    public function __construct(private EscolhaAvisos $escolha, private IdentificacaoPublico $identificacao) {}

    public function atual(LeituraAvisoRequest $request): JsonResponse
    {
        $aviso = $this->escolha->atual($request->validated('aparelho'), $this->identificacao->identificar($request)->cliente);

        return response()->json([
            'data' => $aviso !== null
                ? ['id' => $aviso->id, 'titulo' => $aviso->titulo, 'imagem' => $aviso->url_imagem(), 'link' => $aviso->link]
                : null,
        ]);
    }

    public function ler(LeituraAvisoRequest $request, Avisos $aviso): Response
    {
        abort_unless($aviso->ativo, 404, 'Aviso não encontrado.');

        $this->escolha->ler($aviso, $request->validated('aparelho'), $this->identificacao->identificar($request)->cliente, $request->ip());

        return response()->noContent();
    }
}
