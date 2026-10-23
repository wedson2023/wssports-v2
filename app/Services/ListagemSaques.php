<?php

namespace App\Services;

use App\Enums\SituacaoSaque;
use App\Models\Saques;
use App\Models\Usuarios;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Fila de saques do painel (spec 007; spec 019, R-07): a mesma consulta para a lista, os cartões e
 * a planilha, sempre só com os saques dos clientes da rede do usuário (AlcanceClientes). Pendentes
 * vêm do mais antigo para o mais novo; o histórico, ao contrário.
 */
class ListagemSaques
{
    /**
     * Período máximo da busca por data do pedido, em dias.
     */
    private const DIAS_MAXIMOS_PERIODO = 366;

    public function __construct(private readonly AlcanceClientes $alcance) {}

    /**
     * Filtros da fila (lista, cartões e planilha): situação, busca, período e paginação.
     *
     * @return array<string, mixed>
     */
    public function validar(Request $request): array
    {
        $filtros = $request->validate([
            'situacao' => ['nullable', Rule::enum(SituacaoSaque::class)],
            'busca' => ['nullable', 'string', 'max:60'],
            'de' => ['nullable', 'required_with:ate', 'date_format:Y-m-d'],
            'ate' => ['nullable', 'required_with:de', 'date_format:Y-m-d', 'after_or_equal:de'],
            'pagina' => ['nullable', 'integer', 'min:1'],
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ], [
            'situacao.enum' => 'A situação informada é inválida.',
            'busca.*' => 'A busca deve ter no máximo 60 caracteres.',
            'de.required_with' => 'Informe a data inicial do período.',
            'ate.required_with' => 'Informe a data final do período.',
            '*.date_format' => 'A data informada é inválida.',
            'ate.after_or_equal' => 'A data final deve ser igual ou posterior à inicial.',
            'por_pagina.between' => 'O campo por página deve estar entre 1 e 100.',
        ]);

        if (isset($filtros['de'], $filtros['ate'])
            && Carbon::parse($filtros['de'])->diffInDays(Carbon::parse($filtros['ate'])) >= self::DIAS_MAXIMOS_PERIODO) {
            throw ValidationException::withMessages(['ate' => 'O período pode ter no máximo 366 dias.']);
        }

        return $filtros;
    }

    /**
     * @param  array{situacao?: ?string, busca?: ?string, de?: ?string, ate?: ?string}  $filtros
     */
    public function consulta(Usuarios $usuario, array $filtros): Builder
    {
        $pendentes = ($filtros['situacao'] ?? null) === SituacaoSaque::Pendente->value;

        return $this->base($usuario, $filtros)
            ->select('saques.*')
            ->with(['cliente', 'decidido_por_usuario'])
            ->when(isset($filtros['situacao']), fn (Builder $c) => $c->where('situacao', $filtros['situacao']))
            ->when(isset($filtros['de'], $filtros['ate']), fn (Builder $c) => $c->whereBetween('saques.created_at', $this->periodo($filtros)))
            ->orderBy('saques.created_at', $pendentes ? 'asc' : 'desc')
            ->orderBy('saques.id', $pendentes ? 'asc' : 'desc');
    }

    /**
     * Cartões da aba: pendentes e processando de todos os tempos e, com período, os pagos nele (pela
     * data do pagamento).
     *
     * @param  array{busca?: ?string, de?: ?string, ate?: ?string}  $filtros
     * @return array{pendentes: array{quantidade: int, valor: string}, processando: array{quantidade: int, valor: string}, pagos: array{quantidade: int, valor: string}|null}
     */
    public function resumo(Usuarios $usuario, array $filtros): array
    {
        $somar = fn (Builder $consulta) => (fn (object $linha) => [
            'quantidade' => (int) $linha->quantidade,
            'valor' => bcadd((string) $linha->valor, '0', 2),
        ])($consulta->toBase()->selectRaw('COUNT(*) as quantidade, COALESCE(SUM(saques.valor), 0) as valor')->first());

        $na_situacao = fn (SituacaoSaque $situacao) => $this->base($usuario, $filtros)->where('situacao', $situacao->value);

        return [
            'pendentes' => $somar($na_situacao(SituacaoSaque::Pendente)),
            'processando' => $somar($na_situacao(SituacaoSaque::Processando)),
            'pagos' => isset($filtros['de'], $filtros['ate'])
                ? $somar($na_situacao(SituacaoSaque::Pago)->whereBetween('saques.pago_em', $this->periodo($filtros)))
                : null,
        ];
    }

    /**
     * Saques da rede com a busca por nome do cliente (trecho) ou ID do cliente.
     *
     * @param  array{busca?: ?string}  $filtros
     */
    private function base(Usuarios $usuario, array $filtros): Builder
    {
        $consulta = Saques::query();
        $busca = trim((string) ($filtros['busca'] ?? ''));

        if ($busca !== '') {
            $consulta->whereHas('cliente', fn (Builder $cliente) => $cliente->where(fn (Builder $c) => ctype_digit($busca)
                ? $c->where('clientes.id', (int) $busca)->orWhere('clientes.nome', 'like', '%'.$busca.'%')
                : $c->where('clientes.nome', 'like', '%'.$busca.'%')));
        }

        $this->alcance->aplicar($consulta, $usuario, 'saques.clientes_id');

        return $consulta;
    }

    /**
     * Início e fim do período (dias de Brasília) em UTC.
     *
     * @param  array{de: string, ate: string}  $filtros
     * @return array{0: Carbon, 1: Carbon}
     */
    private function periodo(array $filtros): array
    {
        return ListagemAfiliados::periodo($filtros['de'], $filtros['ate']);
    }
}
