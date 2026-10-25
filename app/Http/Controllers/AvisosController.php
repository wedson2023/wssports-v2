<?php

namespace App\Http\Controllers;

use App\Enums\AcaoConfiguracao;
use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\AvisosRequest;
use App\Http\Resources\AvisosResource;
use App\Models\Avisos;
use App\Services\ArmazenamentoImagens;
use App\Services\HistoricoConfiguracoes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Avisos da banca no painel (spec 006, FR-026): cadastro com upload da imagem (nome pelo hash do
 * conteúdo), edição, ativação e remoção. Só Admin e Supervisor.
 */
class AvisosController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    // campos do aviso registrados no histórico (spec 021, FR-003a)
    private const CAMPOS_HISTORICO = ['imagem', 'titulo', 'link', 'inicio_em', 'fim_em', 'ativo'];

    public function __construct(private ArmazenamentoImagens $imagens, private HistoricoConfiguracoes $historico) {}

    public static function middleware(): array
    {
        return [self::permissao_cliente('avisos.gerenciar')];
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ], [
            'por_pagina.integer' => 'O campo por página deve ser um número inteiro.',
            'por_pagina.between' => 'O campo por página deve estar entre 1 e 100.',
        ]);

        $avisos = Avisos::withCount('leituras')
            ->orderByDesc('id')
            ->paginate($filtros['por_pagina'] ?? 20)
            ->withQueryString();

        return AvisosResource::collection($avisos);
    }

    public function show(Avisos $aviso): AvisosResource
    {
        return new AvisosResource($aviso->loadCount('leituras'));
    }

    public function store(AvisosRequest $request): AvisosResource
    {
        $aviso = Avisos::create([
            ...$request->dados_aviso(),
            'imagem' => $this->imagens->salvar($request->file('imagem'), 'avisos'),
        ]);

        $this->historico->registrar($request->user(), 'avisos', AcaoConfiguracao::Cadastro, $aviso->id);

        return new AvisosResource($aviso);
    }

    public function update(AvisosRequest $request, Avisos $aviso): AvisosResource
    {
        $anterior = $aviso->imagem;
        $antes = $this->historico->valores($aviso, self::CAMPOS_HISTORICO);
        $aviso->fill($request->dados_aviso());

        if ($request->hasFile('imagem')) {
            $aviso->imagem = $this->imagens->salvar($request->file('imagem'), 'avisos');
        }

        $aviso->save();

        // trocou a imagem: a anterior sai do disco (se nenhum outro registro a usa)
        if ($aviso->imagem !== $anterior) {
            $this->imagens->remover($anterior);
        }

        $alteracoes = $this->historico->diferencas($antes, $this->historico->valores($aviso, self::CAMPOS_HISTORICO));

        if ($alteracoes !== []) {
            $this->historico->registrar($request->user(), 'avisos', AcaoConfiguracao::Alteração, $aviso->id, $alteracoes);
        }

        return new AvisosResource($aviso->loadCount('leituras'));
    }

    public function destroy(Request $request, Avisos $aviso): Response
    {
        $aviso->delete();
        $this->imagens->remover($aviso->imagem);
        $this->historico->registrar($request->user(), 'avisos', AcaoConfiguracao::Exclusão, $aviso->id);

        return response()->noContent();
    }
}
