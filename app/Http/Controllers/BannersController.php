<?php

namespace App\Http\Controllers;

use App\Enums\AcaoConfiguracao;
use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\BannersRequest;
use App\Http\Resources\BannersResource;
use App\Models\Banners;
use App\Services\ArmazenamentoImagens;
use App\Services\HistoricoConfiguracoes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Banners do carrossel no painel (spec 006, FR-032a): a imagem é ajustada para 1280x405, como no
 * sistema antigo, e salva com o hash do conteúdo no nome. O último banner também pode ser removido
 * (sem banner ativo, o carrossel some). Só Admin e Supervisor.
 */
class BannersController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    private const LARGURA = 1280;

    private const ALTURA = 405;

    // maior ordem aceita pelo cadastro (BannersRequest)
    private const ORDEM_MAXIMA = 999;

    // campos do banner registrados no histórico (spec 021, FR-003a)
    private const CAMPOS_HISTORICO = ['imagem', 'link', 'ordem', 'ativo'];

    public function __construct(private ArmazenamentoImagens $imagens, private HistoricoConfiguracoes $historico) {}

    public static function middleware(): array
    {
        return [self::permissao_cliente('banners.gerenciar')];
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'ativo' => ['nullable', 'boolean'],
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ], [
            'ativo.boolean' => 'O filtro ativo deve ser verdadeiro ou falso.',
            'por_pagina.integer' => 'O campo por página deve ser um número inteiro.',
            'por_pagina.between' => 'O campo por página deve estar entre 1 e 100.',
        ]);

        $banners = Banners::query()
            ->when(isset($filtros['ativo']), fn (Builder $c) => $c->where('ativo', (bool) $filtros['ativo']))
            ->orderBy('ordem')
            ->orderBy('id')
            ->paginate($filtros['por_pagina'] ?? 20)
            ->withQueryString();

        return BannersResource::collection($banners);
    }

    public function show(Banners $banner): BannersResource
    {
        return new BannersResource($banner);
    }

    public function store(BannersRequest $request): BannersResource
    {
        $dados = $request->dados_banner();

        // sem ordem informada, o banner novo entra no fim do carrossel (spec 021, US3)
        if (! array_key_exists('ordem', $dados)) {
            $dados['ordem'] = min(self::ORDEM_MAXIMA, (int) Banners::max('ordem') + 10);
        }

        $banner = Banners::create([
            ...$dados,
            'imagem' => $this->imagens->salvar($request->file('imagem'), 'banners', self::LARGURA, self::ALTURA),
        ]);

        $this->historico->registrar($request->user(), 'banners', AcaoConfiguracao::Cadastro, $banner->id);

        return new BannersResource($banner);
    }

    public function update(BannersRequest $request, Banners $banner): BannersResource
    {
        $anterior = $banner->imagem;
        $antes = $this->historico->valores($banner, self::CAMPOS_HISTORICO);
        $banner->fill($request->dados_banner());

        if ($request->hasFile('imagem')) {
            $banner->imagem = $this->imagens->salvar($request->file('imagem'), 'banners', self::LARGURA, self::ALTURA);
        }

        $banner->save();

        // trocou a imagem: a anterior sai do disco (se nenhum outro registro a usa)
        if ($banner->imagem !== $anterior) {
            $this->imagens->remover($anterior);
        }

        $alteracoes = $this->historico->diferencas($antes, $this->historico->valores($banner, self::CAMPOS_HISTORICO));

        if ($alteracoes !== []) {
            $this->historico->registrar($request->user(), 'banners', AcaoConfiguracao::Alteração, $banner->id, $alteracoes);
        }

        return new BannersResource($banner);
    }

    public function destroy(Request $request, Banners $banner): Response
    {
        $banner->delete();
        $this->imagens->remover($banner->imagem);
        $this->historico->registrar($request->user(), 'banners', AcaoConfiguracao::Exclusão, $banner->id);

        return response()->noContent();
    }
}
