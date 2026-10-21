<?php

namespace App\Http\Controllers;

use App\Enums\Funcao;
use App\Enums\SituacaoAposta;
use App\Http\Controllers\Concerns\GarantirAlcanceCaixa;
use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\CaixaPeriodoRequest;
use App\Http\Requests\ComissaoGerenteCaixaRequest;
use App\Http\Requests\LimitesVendedorCaixaRequest;
use App\Models\Usuarios;
use App\Models\UsuariosConfiguracoes;
use App\Services\ApuracaoCaixa;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Caixa da rede no painel (spec 017, contracts/api.md §1): cartões do foco com a trilha, lista do
 * nível abaixo, gráfico por quantidade de palpites, ajuste dos limites do vendedor e comissão do
 * gerente. Alcance pela sub-hierarquia (GarantirAlcanceCaixa); somas pelo ApuracaoCaixa.
 */
class CaixaController extends Controller implements HasMiddleware
{
    use GarantirAlcanceCaixa, GarantirPermissaoCliente;

    /**
     * Faixas do gráfico: 1 a 10 palpites e "Mais de 10".
     */
    private const MAXIMO_FAIXAS = 10;

    public function __construct(private readonly ApuracaoCaixa $apuracao) {}

    public static function middleware(): array
    {
        return [
            self::permissao_cliente('caixa.ver', only: ['resumo', 'usuarios', 'grafico']),
            self::permissao_cliente('usuarios_configuracoes.editar', only: ['limites']),
            self::permissao_cliente('caixa.editar_comissao_gerente', only: ['comissao_gerente']),
        ];
    }

    /**
     * Cartões do foco no período e tipo, com a trilha até ele.
     */
    public function resumo(CaixaPeriodoRequest $request): JsonResponse
    {
        $foco = $this->foco_caixa($request, $this->usuarios_id($request));
        [$inicio, $fim] = $request->intervalo();
        $apuracao = $this->apuracao->apurar($foco, $inicio, $fim, $request->tipo());

        $totais = $apuracao['total'];
        $totais['percentual_comissao_gerente'] = $foco?->funcao() === Funcao::Gerente
            ? ($this->apuracao->percentuais_gerentes([$foco->id])[$foco->id] ?? '0.00')
            : null;
        $totais['limites'] = $foco?->funcao() === Funcao::Vendedor ? $this->apuracao->limites([$foco->id])[$foco->id] ?? null : null;

        return response()->json([
            'foco' => $foco ? ['id' => $foco->id, 'nome' => $foco->nome, 'funcao' => $foco->funcao()?->value] : ['id' => null, 'nome' => 'Banca', 'funcao' => 'Banca'],
            'trilha' => $this->trilha($request->user(), $foco),
            'periodo' => ['de' => $request->validated('de'), 'ate' => $request->validated('ate')],
            'totais' => $totais,
        ]);
    }

    /**
     * Lista do nível abaixo do foco, com busca, ordenação e paginação. Inativos e excluídos só
     * aparecem com movimento ou saldo de caixa.
     */
    public function usuarios(CaixaPeriodoRequest $request): JsonResponse
    {
        $solicitante = $request->user();
        $foco = $this->foco_caixa($request, $this->usuarios_id($request));

        // o vendedor não tem nível abaixo: vê só o próprio caixa
        abort_if($foco?->funcao() === Funcao::Vendedor, 403, 'Você não tem permissão para esta ação.');

        [$inicio, $fim] = $request->intervalo();
        $apuracao = $this->apuracao->apurar($foco, $inicio, $fim, $request->tipo());
        ['funcao' => $abaixo, 'linhas' => $linhas] = $this->apuracao->linhas_do_nivel($apuracao, $foco);

        $busca = mb_strtolower(trim((string) $request->validated('busca')));

        if ($busca !== '') {
            $linhas = array_values(array_filter($linhas, fn (array $linha) => str_contains(mb_strtolower($linha['nome']), $busca) || str_contains(mb_strtolower($linha['login']), $busca)));
        }

        $linhas = $this->ordenar($linhas, $request->validated('ordenar') ?? 'nome', $request->validated('direcao') ?? 'asc');

        $por_pagina = $request->por_pagina();
        $pagina = max(1, (int) ($request->validated('page') ?? 1));
        $da_pagina = array_slice($linhas, ($pagina - 1) * $por_pagina, $por_pagina);
        $da_pagina = $this->completar_linhas($solicitante, $abaixo, $da_pagina);

        $paginacao = new LengthAwarePaginator($da_pagina, count($linhas), $por_pagina, $pagina, ['path' => $request->url()]);

        return response()->json(['funcao' => $abaixo->value, ...$paginacao->withQueryString()->toArray()]);
    }

    /**
     * Quantidade de apostas ativas do vendedor no período por número de palpites.
     */
    public function grafico(CaixaPeriodoRequest $request, Usuarios $usuario): JsonResponse
    {
        $this->garantir_vendedor($request, $usuario);
        [$inicio, $fim] = $request->intervalo();

        $por_quantidade = DB::query()
            ->fromSub(
                DB::table('apostas as a')
                    ->join('apostas_palpites as p', 'p.apostas_id', '=', 'a.id')
                    ->where('a.usuarios_id', $usuario->id)
                    ->whereNull('a.clientes_id')
                    ->whereNull('a.deleted_at')
                    ->where('a.situacao', SituacaoAposta::Ativa->value)
                    ->whereBetween('a.created_at', [$inicio, $fim])
                    ->groupBy('a.id')
                    ->selectRaw('COUNT(*) as palpites'),
                'por_aposta',
            )
            ->groupBy('palpites')
            ->selectRaw('palpites, COUNT(*) as apostas')
            ->pluck('apostas', 'palpites');

        $faixas = [];

        for ($palpites = 1; $palpites <= self::MAXIMO_FAIXAS; $palpites++) {
            $faixas[] = ['palpites' => (string) $palpites, 'apostas' => (int) ($por_quantidade[$palpites] ?? 0)];
        }

        $faixas[] = [
            'palpites' => 'Mais de 10',
            'apostas' => (int) $por_quantidade->filter(fn ($apostas, $palpites) => $palpites > self::MAXIMO_FAIXAS)->sum(),
        ];

        return response()->json(['faixas' => $faixas]);
    }

    /**
     * Soma (ou subtrai) os valores informados aos limites do vendedor; nenhum limite pode ficar
     * abaixo de zero (FR-028). Bloqueia a configuração porque as apostas consomem os limites ao
     * mesmo tempo.
     */
    public function limites(LimitesVendedorCaixaRequest $request, Usuarios $usuario): JsonResponse
    {
        $this->garantir_vendedor($request, $usuario);
        UsuariosConfiguracoes::do_vendedor($usuario->id);

        $configuracao = DB::transaction(function () use ($request, $usuario) {
            $configuracao = UsuariosConfiguracoes::where('usuarios_id', $usuario->id)->lockForUpdate()->firstOrFail();
            $nomes = array_flip(LimitesVendedorCaixaRequest::LIMITES);

            foreach ($request->ajustes() as $coluna => $ajuste) {
                $novo = bcadd((string) $configuracao->{$coluna}, $ajuste, 2);

                if (bccomp($novo, '0', 2) < 0) {
                    throw ValidationException::withMessages([
                        $nomes[$coluna] => 'O limite '.$nomes[$coluna].' ficaria negativo; o disponível agora é R$ '.number_format((float) $configuracao->{$coluna}, 2, ',', '.').'.',
                    ]);
                }

                $configuracao->{$coluna} = $novo;
            }

            $configuracao->save();

            return $configuracao;
        });

        return response()->json(['limites' => $this->apuracao->limites([$usuario->id])[$usuario->id]]);
    }

    /**
     * Percentual da comissão do gerente (FR-027).
     */
    public function comissao_gerente(ComissaoGerenteCaixaRequest $request, Usuarios $usuario): JsonResponse
    {
        $this->garantir_gerente($request, $usuario);

        $configuracao = UsuariosConfiguracoes::firstOrCreate(['usuarios_id' => $usuario->id]);
        $configuracao->percentual_comissao_gerente = bcadd((string) $request->validated('percentual'), '0', 2);
        $configuracao->save();

        return response()->json(['percentual_comissao_gerente' => (string) $configuracao->percentual_comissao_gerente]);
    }

    private function usuarios_id(CaixaPeriodoRequest $request): ?int
    {
        return $request->filled('usuarios_id') ? (int) $request->validated('usuarios_id') : null;
    }

    /**
     * Caminho do topo visível a quem consulta até o foco (o Admin começa na "Banca").
     *
     * @return list<array{id: ?int, nome: string}>
     */
    private function trilha(Usuarios $solicitante, ?Usuarios $foco): array
    {
        $caminho = [];
        $atual = $foco;

        while ($atual !== null) {
            array_unshift($caminho, ['id' => $atual->id, 'nome' => $atual->nome]);

            if ($atual->id === $solicitante->id) {
                break;
            }

            $atual = $atual->usuarios_id ? Usuarios::withTrashed()->find($atual->usuarios_id, ['id', 'nome', 'usuarios_id']) : null;
        }

        if ($solicitante->funcao() === Funcao::Admin) {
            $caminho = array_values(array_filter($caminho, fn (array $parte) => $parte['id'] !== $solicitante->id));
            array_unshift($caminho, ['id' => null, 'nome' => 'Banca']);
        }

        return $caminho;
    }

    /**
     * @param  list<array<string, mixed>>  $linhas
     * @return list<array<string, mixed>>
     */
    private function ordenar(array $linhas, string $ordenar, string $direcao): array
    {
        $campo = in_array($ordenar, ['entradas', 'liquido', 'saldo_caixa'], true) ? $ordenar : 'nome';

        usort($linhas, function (array $a, array $b) use ($campo) {
            $comparacao = $campo === 'nome'
                ? strcasecmp($a['nome'], $b['nome'])
                : bccomp($a[$campo] ?? '0', $b[$campo] ?? '0', 2);

            return $comparacao !== 0 ? $comparacao : $a['id'] <=> $b['id'];
        });

        return $direcao === 'desc' ? array_reverse($linhas) : $linhas;
    }

    /**
     * Limites, percentual da comissão e ações de cada linha da página (uma consulta por página).
     *
     * @param  list<array<string, mixed>>  $linhas
     * @return list<array<string, mixed>>
     */
    private function completar_linhas(Usuarios $solicitante, Funcao $funcao, array $linhas): array
    {
        $ids = array_column($linhas, 'id');
        $limites = $funcao === Funcao::Vendedor ? $this->apuracao->limites($ids) : [];
        $percentuais = $funcao === Funcao::Gerente ? $this->apuracao->percentuais_gerentes($ids) : [];
        $pode = fn (string $permissao) => Funcao::usuario_pode($solicitante, $permissao);

        $acoes = match ($funcao) {
            Funcao::Vendedor => array_keys(array_filter([
                'lancar' => $pode('caixa.lancar'),
                'prestar_contas' => $pode('caixa.prestar_contas'),
                'transacoes' => true,
                'ajustar_limite' => $pode('usuarios_configuracoes.editar'),
                'grafico' => true,
                'exportar' => $pode('caixa.exportar'),
            ])),
            Funcao::Gerente => array_keys(array_filter([
                'prestar_contas' => $pode('caixa.prestar_contas'),
                'comissao_gerente' => $pode('caixa.editar_comissao_gerente'),
                'exportar' => $pode('caixa.exportar'),
            ])),
            default => array_keys(array_filter(['exportar' => $pode('caixa.exportar')])),
        };

        return array_map(fn (array $linha) => [
            ...$linha,
            'limites' => $funcao === Funcao::Vendedor ? ($limites[$linha['id']] ?? null) : null,
            'percentual_comissao_gerente' => $funcao === Funcao::Gerente ? ($percentuais[$linha['id']] ?? '0.00') : null,
            'acoes' => $acoes,
        ], $linhas);
    }

}
