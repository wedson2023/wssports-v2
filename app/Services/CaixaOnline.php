<?php

namespace App\Services;

use App\Enums\FormaPagamento;
use App\Enums\ResultadoAposta;
use App\Enums\SituacaoAposta;
use App\Enums\SituacaoDeposito;
use App\Enums\SituacaoEncerramento;
use App\Enums\SituacaoSaque;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Caixa online da Dashboard (spec 014, FR-034 a FR-036, research R-05): apostas dos clientes no site
 * pagas com saldo real, encerramentos (cash out), depósitos e saques do dia, da banca toda. O saldo
 * líquido online é o resultado das apostas: entradas − saídas − encerramentos. O Caixa clientes da
 * spec 017 usa as mesmas contas num período e por cliente.
 */
class CaixaOnline
{
    public function __construct(private readonly AlcanceDashboard $alcance) {}

    /**
     * Totais do intervalo (sem intervalo = o dia atual, como na Dashboard).
     *
     * @return array<string, string>
     */
    public function calcular(?Carbon $inicio = null, ?Carbon $fim = null): array
    {
        [$inicio, $fim] = $inicio && $fim ? [$inicio, $fim] : $this->alcance->periodo_do_dia();

        $apostas = $this->somas_apostas($inicio, $fim)->first();
        $encerramentos = $this->soma('apostas_encerramentos', 'decidido_em', [SituacaoEncerramento::Registrado->value, SituacaoEncerramento::Creditado->value], $inicio, $fim)->value('total');

        // a quantidade de apostas só serve à lista por cliente; a Dashboard continua com os mesmos campos
        $valores = array_diff_key($this->formatar($apostas, $encerramentos), ['apostas' => true]);
        $valores['depositos'] = $this->dinheiro($this->soma('depositos', 'pago_em', [SituacaoDeposito::Aprovado->value], $inicio, $fim)->value('total'));
        $valores['saques_pagos'] = $this->dinheiro($this->soma('saques', 'pago_em', [SituacaoSaque::Pago->value], $inicio, $fim)->value('total'));

        return $valores;
    }

    /**
     * As mesmas somas por cliente (sem depósitos e saques, que o Caixa clientes soma à parte).
     *
     * @param  list<int>  $ids_clientes
     * @return array<int, array<string, string>>
     */
    public function por_cliente(array $ids_clientes, Carbon $inicio, Carbon $fim): array
    {
        if ($ids_clientes === []) {
            return [];
        }

        $apostas = $this->somas_apostas($inicio, $fim)
            ->whereIn('clientes_id', $ids_clientes)
            ->groupBy('clientes_id')
            ->addSelect('clientes_id')
            ->get()
            ->keyBy('clientes_id');

        $encerramentos = $this->soma('apostas_encerramentos', 'decidido_em', [SituacaoEncerramento::Registrado->value, SituacaoEncerramento::Creditado->value], $inicio, $fim)
            ->whereIn('clientes_id', $ids_clientes)
            ->groupBy('clientes_id')
            ->addSelect('clientes_id')
            ->pluck('total', 'clientes_id');

        $por_cliente = [];

        foreach ($ids_clientes as $id) {
            $por_cliente[$id] = $this->formatar($apostas[$id] ?? null, $encerramentos[$id] ?? 0);
        }

        return $por_cliente;
    }

    /**
     * Somas das apostas ativas dos clientes pagas com saldo real, feitas no intervalo.
     */
    private function somas_apostas(Carbon $inicio, Carbon $fim): Builder
    {
        $valor_por_resultado = 'COALESCE(SUM(CASE WHEN resultado = ? THEN valor ELSE 0 END), 0)';

        return DB::table('apostas')
            ->whereNotNull('clientes_id')
            ->whereNull('deleted_at')
            ->where('situacao', SituacaoAposta::Ativa->value)
            ->where('forma_pagamento', FormaPagamento::Saldo->value)
            ->whereBetween('created_at', [$inicio, $fim])
            ->selectRaw('COUNT(*) as apostas')
            ->selectRaw('COALESCE(SUM(valor), 0) as entradas')
            ->selectRaw($valor_por_resultado.' as em_aberto', [ResultadoAposta::Aguardando->value])
            ->selectRaw($valor_por_resultado.' as premiadas', [ResultadoAposta::Vencedor->value])
            ->selectRaw($valor_por_resultado.' as perdidas', [ResultadoAposta::Perdedor->value])
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN resultado = ? THEN premio WHEN resultado = ? THEN valor ELSE 0 END), 0) as saidas',
                [ResultadoAposta::Vencedor->value, ResultadoAposta::Devolvido->value],
            );
    }

    /**
     * Valores das apostas e o saldo líquido (entradas − saídas − encerramentos).
     *
     * @return array<string, string>
     */
    private function formatar(?object $apostas, mixed $encerramentos): array
    {
        $valores = [
            'apostas' => (string) (int) ($apostas->apostas ?? 0),
            'entradas' => $this->dinheiro($apostas->entradas ?? 0),
            'em_aberto' => $this->dinheiro($apostas->em_aberto ?? 0),
            'premiadas' => $this->dinheiro($apostas->premiadas ?? 0),
            'perdidas' => $this->dinheiro($apostas->perdidas ?? 0),
            'saidas' => $this->dinheiro($apostas->saidas ?? 0),
            'encerramentos' => $this->dinheiro($encerramentos),
        ];

        $valores['saldo_liquido'] = bcsub(bcsub($valores['entradas'], $valores['saidas'], 2), $valores['encerramentos'], 2);

        return $valores;
    }

    /**
     * Soma do valor dos registros da tabela nas situações informadas, com a data no intervalo.
     *
     * @param  list<string>  $situacoes
     */
    private function soma(string $tabela, string $coluna_data, array $situacoes, Carbon $inicio, Carbon $fim): Builder
    {
        return DB::table($tabela)
            ->whereIn('situacao', $situacoes)
            ->whereNull('deleted_at')
            ->whereBetween($coluna_data, [$inicio, $fim])
            ->selectRaw('COALESCE(SUM(valor), 0) as total');
    }

    private function dinheiro(mixed $valor): string
    {
        return bcadd((string) $valor, '0', 2);
    }
}
