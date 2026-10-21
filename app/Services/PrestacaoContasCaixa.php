<?php

namespace App\Services;

use App\Enums\Funcao;
use App\Enums\ResultadoAposta;
use App\Enums\SituacaoAposta;
use App\Enums\TipoLancamentoCaixa;
use App\Models\CaixaLancamentos;
use App\Models\CaixaPrestacoesContas;
use App\Models\Usuarios;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Prestação de contas do caixa do vendedor (spec 017, FR-017 a FR-022a, research R-04): resumo do
 * período desde a última prestação, prestar (individual ou em lote pelo gerente, tudo ou nada) e
 * desfazer só a última. O bloqueio da linha do vendedor em `usuarios` serializa prestar, desfazer e
 * lançar, para não haver períodos sobrepostos.
 */
class PrestacaoContasCaixa
{
    public const SEM_MOVIMENTO = 'Nenhum movimento a prestar no período.';

    public const CAIXA_MUDOU = 'O caixa deste vendedor mudou; confira e tente de novo.';

    public function __construct(private readonly SaldoCaixa $saldo) {}

    /**
     * Última prestação não desfeita do vendedor.
     */
    public function ultima(Usuarios $vendedor): ?CaixaPrestacoesContas
    {
        return CaixaPrestacoesContas::where('usuarios_id', $vendedor->id)
            ->latest('periodo_fim')
            ->latest('id')
            ->first();
    }

    /**
     * Resumo do período da última prestação até `ate`. As partes somam o valor apurado: o resto
     * (estornos e re-apurações de apostas já prestadas) vai em `ajustes` (R-03).
     *
     * @return array<string, mixed>
     */
    public function resumo(Usuarios $vendedor, Carbon $ate): array
    {
        $ultima = $this->ultima($vendedor);
        $inicio = $ultima?->periodo_fim;
        $saldo_anterior = $ultima ? (string) $ultima->diferenca : '0.00';

        $apostas = $this->apostas_do_vendedor($vendedor)
            ->where('created_at', '<=', $ate)
            ->when($inicio, fn ($consulta) => $consulta->where('created_at', '>', $inicio))
            ->selectRaw('COUNT(*) as quantidade, COALESCE(SUM(valor), 0) as entradas, COALESCE(SUM(comissao), 0) as comissao')
            ->first();

        // prêmios e devoluções pela data da decisão, mesmo de apostas feitas antes (Clarifications Q1);
        // aposta decidida sem data de apuração (lançamento manual antigo) usa a data da aposta
        $decidida_em = DB::raw('COALESCE(apurada_em, created_at)');
        $saidas = $this->apostas_do_vendedor($vendedor)
            ->where('created_at', '<=', $ate)
            ->where($decidida_em, '<=', $ate)
            ->when($inicio, fn ($consulta) => $consulta->where($decidida_em, '>', $inicio))
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN resultado = ? THEN premio WHEN resultado = ? THEN valor ELSE 0 END), 0) as saidas',
                [ResultadoAposta::Vencedor->value, ResultadoAposta::Devolvido->value],
            )
            ->value('saidas');

        $lancamentos = DB::table('caixa_lancamentos')
            ->where('usuarios_id', $vendedor->id)
            ->whereNull('deleted_at')
            ->where('ocorrido_em', '<=', $ate)
            ->when($inicio, fn ($consulta) => $consulta->where('ocorrido_em', '>', $inicio))
            ->selectRaw('COUNT(*) as quantidade')
            ->selectRaw('COALESCE(SUM(CASE WHEN tipo = ? THEN valor ELSE 0 END), 0) as suprimentos', [TipoLancamentoCaixa::Suprimento->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN tipo = ? THEN valor ELSE 0 END), 0) as retiradas', [TipoLancamentoCaixa::Retirada->value])
            ->first();

        $em_aberto = $this->apostas_do_vendedor($vendedor)
            ->where('created_at', '<=', $ate)
            ->where('resultado', ResultadoAposta::Aguardando->value)
            ->selectRaw('COUNT(*) as quantidade, COALESCE(SUM(valor), 0) as valor')
            ->first();

        $entradas = bcadd((string) $apostas->entradas, '0', 2);
        $comissao = bcadd((string) $apostas->comissao, '0', 2);
        $saidas = bcadd((string) $saidas, '0', 2);
        $suprimentos = bcadd((string) $lancamentos->suprimentos, '0', 2);
        $retiradas = bcadd((string) $lancamentos->retiradas, '0', 2);
        $apurado = $this->saldo->saldos([$vendedor->id], $ate)[$vendedor->id];

        $partes = bcadd(bcsub(bcsub(bcadd($saldo_anterior, $entradas, 2), $comissao, 2), $saidas, 2), bcsub($suprimentos, $retiradas, 2), 2);

        return [
            'periodo_inicio' => $inicio,
            'periodo_fim' => $ate,
            'saldo_anterior' => $saldo_anterior,
            'entradas' => $entradas,
            'comissao' => $comissao,
            'saidas' => $saidas,
            'suprimentos' => $suprimentos,
            'retiradas' => $retiradas,
            'ajustes' => bcsub($apurado, $partes, 2),
            'valor_apurado' => $apurado,
            'apostas_em_aberto' => ['quantidade' => (int) $em_aberto->quantidade, 'valor' => bcadd((string) $em_aberto->valor, '0', 2)],
            'tem_movimento' => bccomp($apurado, '0', 2) !== 0 || (int) $apostas->quantidade > 0 || (int) $lancamentos->quantidade > 0,
        ];
    }

    /**
     * Presta contas de um vendedor até `ate`, numa transação; o valor recebido vazio é o apurado.
     */
    public function prestar(Usuarios $vendedor, Usuarios $autor, Carbon $ate, ?string $valor_recebido, ?string $observacao): CaixaPrestacoesContas
    {
        return DB::transaction(function () use ($vendedor, $autor, $ate, $valor_recebido, $observacao) {
            $prestacao = $this->fechar($vendedor, $autor, $ate, $valor_recebido, $observacao, null);

            if ($prestacao === null) {
                throw ValidationException::withMessages(['ate' => self::SEM_MOVIMENTO]);
            }

            return $prestacao;
        });
    }

    /**
     * Presta contas de todos os vendedores do gerente até `ate`, tudo ou nada; o valor recebido é o
     * apurado de cada um e os sem movimento são pulados.
     *
     * @return array{lote: string, prestacoes: int, pulados: int, total: string}
     */
    public function prestar_lote(Usuarios $gerente, Usuarios $autor, Carbon $ate, ?string $observacao): array
    {
        return DB::transaction(function () use ($gerente, $autor, $ate, $observacao) {
            $lote = (string) Str::uuid();
            $resultado = ['lote' => $lote, 'prestacoes' => 0, 'pulados' => 0, 'total' => '0.00'];

            foreach ($this->vendedores_do_gerente($gerente) as $vendedor) {
                $prestacao = $this->fechar($vendedor, $autor, $ate, null, $observacao, $lote);

                if ($prestacao === null) {
                    $resultado['pulados']++;

                    continue;
                }

                $resultado['prestacoes']++;
                $resultado['total'] = bcadd($resultado['total'], (string) $prestacao->valor_recebido, 2);
            }

            if ($resultado['prestacoes'] === 0) {
                throw ValidationException::withMessages(['ate' => self::SEM_MOVIMENTO]);
            }

            return $resultado;
        });
    }

    /**
     * Resumo de cada vendedor do gerente para o lote.
     *
     * @return array{vendedores: list<array{id: int, nome: string, valor_apurado: string, tem_movimento: bool}>, total: string, pulados: int}
     */
    public function resumo_lote(Usuarios $gerente, Carbon $ate): array
    {
        $vendedores = [];
        $total = '0.00';
        $pulados = 0;

        foreach ($this->vendedores_do_gerente($gerente) as $vendedor) {
            $resumo = $this->resumo($vendedor, $ate);
            $ultima = $this->ultima($vendedor);
            $tem_movimento = $resumo['tem_movimento'] && ($ultima === null || $ate->greaterThan($ultima->periodo_fim));

            $vendedores[] = ['id' => $vendedor->id, 'nome' => $vendedor->nome, 'valor_apurado' => $resumo['valor_apurado'], 'tem_movimento' => $tem_movimento];

            if ($tem_movimento) {
                $total = bcadd($total, $resumo['valor_apurado'], 2);
            } else {
                $pulados++;
            }
        }

        return compact('vendedores', 'total', 'pulados');
    }

    /**
     * Desfaz a última prestação do vendedor (FR-022a): quem fez ou um superior dele; os lançamentos
     * dela voltam a ficar abertos.
     */
    public function desfazer(CaixaPrestacoesContas $prestacao, Usuarios $autor, string $motivo): CaixaPrestacoesContas
    {
        return DB::transaction(function () use ($prestacao, $autor, $motivo) {
            $vendedor = $this->bloquear($prestacao->usuarios_id);
            $ultima = $this->ultima($vendedor);

            if ($ultima === null || $ultima->id !== $prestacao->id) {
                throw ValidationException::withMessages(['prestacao' => 'Só a última prestação de contas do vendedor pode ser desfeita.']);
            }

            if (! $this->pode_desfazer($prestacao, $autor)) {
                throw new HttpException(403, 'Você não tem permissão para esta ação.');
            }

            CaixaLancamentos::where('caixa_prestacoes_contas_id', $prestacao->id)->update(['caixa_prestacoes_contas_id' => null]);

            $prestacao->desfeita_por = $autor->id;
            $prestacao->motivo_desfazer = $motivo;
            $prestacao->save();
            $prestacao->delete();

            return $prestacao;
        });
    }

    /**
     * Quem fez a prestação, um superior dele ou o Admin pode desfazê-la.
     */
    public function pode_desfazer(CaixaPrestacoesContas $prestacao, Usuarios $autor): bool
    {
        if ($autor->funcao() === Funcao::Admin || $autor->id === $prestacao->prestado_por) {
            return true;
        }

        $quem_fez = Usuarios::withTrashed()->find($prestacao->prestado_por);

        return $quem_fez !== null && in_array($autor->id, $this->ids_acima($quem_fez), true);
    }

    /**
     * Fecha o caixa do vendedor (dentro de uma transação): bloqueia o vendedor, confere a data e
     * grava a prestação; null quando não há movimento a prestar.
     */
    private function fechar(Usuarios $vendedor, Usuarios $autor, Carbon $ate, ?string $valor_recebido, ?string $observacao, ?string $lote): ?CaixaPrestacoesContas
    {
        $vendedor = $this->bloquear($vendedor->id);
        $ultima = $this->ultima($vendedor);

        // outra prestação gravada até esta data enquanto o usuário conferia o resumo; no lote, o
        // vendedor já prestado até ali só é pulado
        if ($ultima !== null && $ate->lessThanOrEqualTo($ultima->periodo_fim)) {
            if ($lote !== null) {
                return null;
            }

            throw new HttpException(409, self::CAIXA_MUDOU);
        }

        $resumo = $this->resumo($vendedor, $ate);

        if (! $resumo['tem_movimento']) {
            return null;
        }

        $recebido = bcadd($valor_recebido ?? $resumo['valor_apurado'], '0', 2);

        $prestacao = CaixaPrestacoesContas::create([
            'usuarios_id' => $vendedor->id,
            'periodo_inicio' => $resumo['periodo_inicio'],
            'periodo_fim' => $ate,
            'saldo_anterior' => $resumo['saldo_anterior'],
            'valor_apurado' => $resumo['valor_apurado'],
            'valor_recebido' => $recebido,
            'diferenca' => bcsub($resumo['valor_apurado'], $recebido, 2),
            'lote' => $lote,
            'prestado_por' => $autor->id,
            'observacao' => $observacao,
        ]);

        CaixaLancamentos::where('usuarios_id', $vendedor->id)
            ->emAberto()
            ->where('ocorrido_em', '<=', $ate)
            ->update(['caixa_prestacoes_contas_id' => $prestacao->id]);

        return $prestacao;
    }

    /**
     * Bloqueia a linha do vendedor até o fim da transação (inclusive excluídos, FR-014a).
     */
    public function bloquear(int $usuarios_id): Usuarios
    {
        return Usuarios::withTrashed()->lockForUpdate()->findOrFail($usuarios_id);
    }

    /**
     * Vendedores do gerente (inclusive inativos e excluídos), em ordem de id para o bloqueio.
     *
     * @return list<Usuarios>
     */
    private function vendedores_do_gerente(Usuarios $gerente): array
    {
        return Usuarios::withTrashed()
            ->with('roles')
            ->where('usuarios_id', $gerente->id)
            ->orderBy('id')
            ->get()
            ->filter(fn (Usuarios $usuario) => $usuario->funcao() === Funcao::Vendedor)
            ->values()
            ->all();
    }

    private function apostas_do_vendedor(Usuarios $vendedor): Builder
    {
        return DB::table('apostas')
            ->where('usuarios_id', $vendedor->id)
            ->whereNull('clientes_id')
            ->whereNull('deleted_at')
            ->where('situacao', SituacaoAposta::Ativa->value);
    }

    /**
     * Ids do usuário e dos superiores dele, inclusive excluídos.
     *
     * @return list<int>
     */
    private function ids_acima(Usuarios $usuario): array
    {
        $ids = [$usuario->id];
        $id_superior = $usuario->usuarios_id;

        while ($id_superior !== null && ! in_array($id_superior, $ids, true)) {
            $ids[] = $id_superior;
            $id_superior = Usuarios::withTrashed()->whereKey($id_superior)->value('usuarios_id');
        }

        return $ids;
    }
}
