<?php

namespace App\Services;

use App\Enums\FormaPagamento;
use App\Enums\ResultadoAposta;
use App\Enums\SituacaoAposta;
use App\Enums\SituacaoDeposito;
use App\Enums\SituacaoEncerramento;
use App\Enums\SituacaoSaque;
use Illuminate\Support\Facades\DB;

/**
 * Caixa online da Dashboard (spec 014, FR-034 a FR-036, research R-05): apostas dos clientes no site
 * pagas com saldo real, encerramentos (cash out), depósitos e saques do dia, da banca toda. O saldo
 * líquido online é o resultado das apostas: entradas − saídas − encerramentos.
 */
class CaixaOnline
{
    public function __construct(private readonly AlcanceDashboard $alcance) {}

    /**
     * @return array<string, string>
     */
    public function calcular(): array
    {
        [$inicio, $fim] = $this->alcance->periodo_do_dia();
        $valor_por_resultado = 'COALESCE(SUM(CASE WHEN resultado = ? THEN valor ELSE 0 END), 0)';

        $apostas = DB::table('apostas')
            ->whereNotNull('clientes_id')
            ->whereNull('deleted_at')
            ->where('situacao', SituacaoAposta::Ativa->value)
            ->where('forma_pagamento', FormaPagamento::Saldo->value)
            ->whereBetween('created_at', [$inicio, $fim])
            ->selectRaw('COALESCE(SUM(valor), 0) as entradas')
            ->selectRaw($valor_por_resultado.' as em_aberto', [ResultadoAposta::Aguardando->value])
            ->selectRaw($valor_por_resultado.' as premiadas', [ResultadoAposta::Vencedor->value])
            ->selectRaw($valor_por_resultado.' as perdidas', [ResultadoAposta::Perdedor->value])
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN resultado = ? THEN premio WHEN resultado = ? THEN valor ELSE 0 END), 0) as saidas',
                [ResultadoAposta::Vencedor->value, ResultadoAposta::Devolvido->value],
            )
            ->first();

        $encerramentos = $this->soma('apostas_encerramentos', 'decidido_em', [SituacaoEncerramento::Registrado->value, SituacaoEncerramento::Creditado->value]);

        $valores = [
            'entradas' => bcadd((string) $apostas->entradas, '0', 2),
            'em_aberto' => bcadd((string) $apostas->em_aberto, '0', 2),
            'premiadas' => bcadd((string) $apostas->premiadas, '0', 2),
            'perdidas' => bcadd((string) $apostas->perdidas, '0', 2),
            'saidas' => bcadd((string) $apostas->saidas, '0', 2),
            'encerramentos' => $encerramentos,
            'depositos' => $this->soma('depositos', 'pago_em', [SituacaoDeposito::Aprovado->value]),
            'saques_pagos' => $this->soma('saques', 'pago_em', [SituacaoSaque::Pago->value]),
        ];

        $valores['saldo_liquido'] = bcsub(bcsub($valores['entradas'], $valores['saidas'], 2), $encerramentos, 2);

        return $valores;
    }

    /**
     * Soma do valor dos registros da tabela nas situações informadas, com a data no dia atual.
     *
     * @param  list<string>  $situacoes
     */
    private function soma(string $tabela, string $coluna_data, array $situacoes): string
    {
        [$inicio, $fim] = $this->alcance->periodo_do_dia();

        return bcadd((string) DB::table($tabela)
            ->whereIn('situacao', $situacoes)
            ->whereNull('deleted_at')
            ->whereBetween($coluna_data, [$inicio, $fim])
            ->selectRaw('COALESCE(SUM(valor), 0) as total')
            ->value('total'), '0', 2);
    }
}
