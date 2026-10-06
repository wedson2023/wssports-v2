<?php

namespace App\Http\Controllers;

use App\Enums\SituacaoConfronto;
use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\ConfrontosRequest;
use App\Http\Resources\ConfrontosResource;
use App\Models\Confrontos;
use App\Services\AlcanceHierarquia;
use Carbon\CarbonTimeZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Confrontos do pré-jogo no painel: listagem, ativar e desativar e cadastro manual.
 */
class ConfrontosController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public function __construct(private AlcanceHierarquia $alcance) {}

    public static function middleware(): array
    {
        return [
            self::permissao_cliente('confrontos.listar', only: ['index', 'show']),
            self::permissao_cliente('confrontos.cadastrar', only: ['store']),
            self::permissao_cliente('confrontos.editar', only: ['update']),
            self::permissao_cliente('confrontos.excluir', only: ['destroy']),
            self::permissao_cliente('confrontos.alterar_situacao', only: ['alterar_situacao']),
        ];
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        // filtros enviados vazios chegam como null e são ignorados
        $filtros = $request->validate([
            'busca' => ['nullable', 'string', 'max:150'],
            'campeonatos_id' => ['nullable', 'integer'],
            'ativo' => ['nullable', 'boolean'],
            'manual' => ['nullable', 'boolean'],
            'esporte' => ['nullable', 'string', 'max:50'],
            'situacao' => ['nullable', Rule::enum(SituacaoConfronto::class)],
            'dia' => ['nullable', 'in:hoje,amanha,depois_de_amanha'],
            'fuso_horario' => ['nullable', 'regex:/^[+-](0\d|1[0-4]):[0-5]\d$/'],
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ], [
            'busca.max' => 'A busca deve ter no máximo 150 caracteres.',
            'campeonatos_id.integer' => 'O campeonato deve ser um número inteiro.',
            'ativo.boolean' => 'O filtro ativo deve ser verdadeiro ou falso.',
            'manual.boolean' => 'O filtro manual deve ser verdadeiro ou falso.',
            'esporte.max' => 'O esporte deve ter no máximo 50 caracteres.',
            'situacao.enum' => 'A situação informada é inválida.',
            'dia.in' => 'O dia deve ser hoje, amanha ou depois_de_amanha.',
            'fuso_horario.regex' => 'O fuso horário deve estar no formato ±HH:MM (ex.: -03:00).',
            'por_pagina.integer' => 'O campo por página deve ser um número inteiro.',
            'por_pagina.between' => 'O campo por página deve estar entre 1 e 100.',
        ]);

        $confrontos = $this->com_nao_permitido($request, Confrontos::with('campeonato'))
            ->when(isset($filtros['busca']), function (Builder $c) use ($filtros) {
                $termo = '%'.addcslashes($filtros['busca'], '%_\\').'%';
                $c->where(fn (Builder $b) => $b->where('time_casa', 'like', $termo)->orWhere('time_fora', 'like', $termo));
            })
            ->when(isset($filtros['campeonatos_id']), fn (Builder $c) => $c->where('campeonatos_id', $filtros['campeonatos_id']))
            ->when(isset($filtros['ativo']), fn (Builder $c) => $c->where('ativo', (bool) $filtros['ativo']))
            ->when(isset($filtros['manual']), fn (Builder $c) => $c->where('manual', (bool) $filtros['manual']))
            ->when(isset($filtros['esporte']), fn (Builder $c) => $c->where('esporte', mb_strtoupper($filtros['esporte'])))
            ->when(isset($filtros['situacao']), fn (Builder $c) => $c->where('situacao', $filtros['situacao']))
            ->when(isset($filtros['dia']), fn (Builder $c) => $c->whereBetween('data_inicio', $this->dia_em_utc($filtros['dia'], $filtros['fuso_horario'] ?? null)))
            ->orderBy('data_inicio')
            ->orderBy('time_casa')
            ->paginate($filtros['por_pagina'] ?? 20)
            ->withQueryString();

        return ConfrontosResource::collection($confrontos);
    }

    public function show(Request $request, Confrontos $confronto): ConfrontosResource
    {
        return new ConfrontosResource(
            $this->com_nao_permitido($request, Confrontos::with(['campeonato', 'jogadores']))->findOrFail($confronto->id)
        );
    }

    /**
     * Confronto manual: em campeonato manual, ativo, aguardando e com a quantidade de cotações
     * calculada.
     */
    public function store(ConfrontosRequest $request): ConfrontosResource
    {
        $confronto = new Confrontos($this->dados_confronto($request));
        $confronto->situacao = SituacaoConfronto::Aguardando;
        $confronto->forceFill(['manual' => true, 'ativo' => true])->save();

        return new ConfrontosResource($confronto->refresh()->load('campeonato'));
    }

    public function update(ConfrontosRequest $request, Confrontos $confronto): ConfrontosResource
    {
        $this->garantir_manual($confronto);

        $confronto->fill($this->dados_confronto($request));

        if ($request->filled('situacao')) {
            $confronto->situacao = SituacaoConfronto::from($request->validated('situacao'));
        }

        $confronto->save();

        return new ConfrontosResource($confronto->load('campeonato'));
    }

    public function destroy(Confrontos $confronto): Response
    {
        $this->garantir_manual($confronto);
        $confronto->delete();

        return response()->noContent();
    }

    /**
     * Ativa ou desativa para o sistema inteiro; desativado, some também do ao vivo (só Admin e
     * Supervisor).
     */
    public function alterar_situacao(Request $request, Confrontos $confronto): ConfrontosResource
    {
        $dados = $request->validate(
            ['ativo' => ['required', 'boolean']],
            ['ativo.required' => 'O campo ativo é obrigatório.', 'ativo.boolean' => 'O campo ativo deve ser verdadeiro ou falso.'],
        );

        $confronto->forceFill(['ativo' => (bool) $dados['ativo']])->save();

        return new ConfrontosResource($confronto);
    }

    /**
     * @return array<string, mixed>
     */
    private function dados_confronto(ConfrontosRequest $request): array
    {
        $cotacoes = $request->cotacoes();

        return [
            'campeonatos_id' => $request->validated('campeonatos_id'),
            'time_casa' => $request->validated('time_casa'),
            'escudo_casa' => $request->validated('escudo_casa'),
            'time_fora' => $request->validated('time_fora'),
            'escudo_fora' => $request->validated('escudo_fora'),
            'esporte' => mb_strtoupper($request->validated('esporte')),
            'data_inicio' => $request->data_inicio_utc(),
            'cotacoes' => $cotacoes,
            'quantidade_cotacoes' => count($cotacoes),
        ];
    }

    /**
     * Início e fim do dia pedido no fuso informado, em UTC.
     *
     * @return array{0: string, 1: string}
     */
    private function dia_em_utc(string $dia, ?string $fuso): array
    {
        $inicio = now()->setTimezone(new CarbonTimeZone($fuso ?? '-03:00'))->startOfDay()
            ->addDays(match ($dia) {
                'amanha' => 1,
                'depois_de_amanha' => 2,
                default => 0,
            });

        return [$inicio->copy()->utc()->format('Y-m-d H:i:s'), $inicio->copy()->endOfDay()->utc()->format('Y-m-d H:i:s')];
    }

    /**
     * Indica se há um não permitido do pré-jogo que vale para quem consulta.
     */
    private function com_nao_permitido(Request $request, Builder $consulta): Builder
    {
        return $consulta->withExists([
            'nao_permitidos as nao_permitido' => fn (Builder $c) => $this->alcance->nao_permitidos_do_usuario($c, $request->user()),
        ]);
    }

    private function garantir_manual(Confrontos $confronto): void
    {
        if (! $confronto->manual) {
            throw ValidationException::withMessages(['confronto' => 'Só registros manuais podem ser alterados.']);
        }
    }
}
