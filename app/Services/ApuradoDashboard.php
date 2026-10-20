<?php

namespace App\Services;

use App\Enums\Funcao;
use App\Enums\ResultadoAposta;
use App\Enums\SituacaoAposta;
use App\Enums\SituacaoDeposito;
use App\Enums\SituacaoSaque;
use App\Fakes\DadosFake;
use App\Models\Usuarios;
use App\Models\UsuariosConfiguracoes;
use Illuminate\Support\Facades\DB;

/**
 * Apurado do dia da Dashboard (spec 014, FR-018 a FR-024, research R-05): somas das apostas do
 * balcão da rede, comissão do gerente por gerente, depósitos online e saques pagos para Admin e
 * Supervisor, e lançamentos/retiradas fake. Somas no banco; contas em bcmath.
 */
class ApuradoDashboard
{
    public function __construct(private readonly AlcanceDashboard $alcance) {}

    /**
     * @return array<string, mixed>
     */
    public function calcular(Usuarios $usuario): array
    {
        $funcao = $usuario->funcao();
        $ids = $this->alcance->ids_vendedores($usuario);
        $por_gerente = $this->somas_por_gerente($ids);

        $totais = ['entradas' => '0.00', 'em_aberto' => '0.00', 'premiadas' => '0.00', 'perdidas' => '0.00', 'saidas' => '0.00', 'comissao_vendedor' => '0.00'];
        $comissao_gerente = '0.00';
        $percentual_gerente = DadosFake::percentual_comissao_gerente();

        foreach ($por_gerente as $grupo) {
            foreach (array_keys($totais) as $chave) {
                $totais[$chave] = bcadd($totais[$chave], (string) $grupo->{$chave}, 2);
            }

            // comissão do gerente: % sobre o líquido dos vendedores dele, zero quando não há lucro
            $liquido_grupo = bcsub(bcsub((string) $grupo->entradas, (string) $grupo->saidas, 2), (string) $grupo->comissao_vendedor, 2);

            if (bccomp($liquido_grupo, '0', 2) > 0) {
                $comissao_gerente = bcadd($comissao_gerente, bcdiv(bcmul($liquido_grupo, $percentual_gerente, 4), '100', 2), 2);
            }
        }

        $e_vendedor = $funcao === Funcao::Vendedor;
        $e_gestor_geral = in_array($funcao, [Funcao::Admin, Funcao::Supervisor], true);
        $depositos = $e_gestor_geral ? $this->depositos_online() : null;
        $saques = $e_gestor_geral ? $this->saques_pagos() : null;
        ['lancamentos' => $lancamentos, 'retiradas' => $retiradas] = DadosFake::caixa_usuario();

        // os depósitos online e os saques pagos entram nas entradas e saídas de Admin e Supervisor
        $entradas = $depositos !== null ? bcadd($totais['entradas'], $depositos, 2) : $totais['entradas'];
        $saidas = $saques !== null ? bcadd($totais['saidas'], $saques, 2) : $totais['saidas'];
        $comissao_gerente = $e_vendedor ? null : $comissao_gerente;

        $saldo = bcsub(bcsub($entradas, $saidas, 2), $totais['comissao_vendedor'], 2);
        $saldo = bcsub($saldo, $comissao_gerente ?? '0.00', 2);
        $saldo = bcsub(bcadd($saldo, $lancamentos, 2), $retiradas, 2);

        return [
            'limites' => $e_vendedor ? $this->limites($usuario) : null,
            'entradas' => $entradas,
            'em_aberto' => $totais['em_aberto'],
            'premiadas' => $totais['premiadas'],
            'perdidas' => $totais['perdidas'],
            'saidas' => $saidas,
            'comissao_vendedor' => $totais['comissao_vendedor'],
            'depositos_online' => $depositos,
            'saques_pagos' => $saques,
            'lancamentos' => $lancamentos,
            'retiradas' => $retiradas,
            'comissao_gerente' => $comissao_gerente,
            'saldo_liquido' => $saldo,
            'provisorios' => $e_vendedor ? ['lancamentos', 'retiradas'] : ['lancamentos', 'retiradas', 'comissao_gerente'],
        ];
    }

    /**
     * Somas das apostas ativas do balcão do dia, agrupadas pelo gerente (superior) de cada vendedor.
     *
     * @param  list<int>|null  $ids
     * @return list<object>
     */
    private function somas_por_gerente(?array $ids): array
    {
        [$inicio, $fim] = $this->alcance->periodo_do_dia();
        $valor_por_resultado = 'COALESCE(SUM(CASE WHEN a.resultado = ? THEN a.valor ELSE 0 END), 0)';

        $consulta = DB::table('apostas as a')
            ->join('usuarios as u', 'u.id', '=', 'a.usuarios_id')
            ->where('a.situacao', SituacaoAposta::Ativa->value)
            ->whereNull('a.clientes_id')
            ->whereNull('a.deleted_at')
            ->whereBetween('a.created_at', [$inicio, $fim])
            ->groupBy('u.usuarios_id')
            ->select('u.usuarios_id as gerente_id')
            ->selectRaw('COALESCE(SUM(a.valor), 0) as entradas')
            ->selectRaw($valor_por_resultado.' as em_aberto', [ResultadoAposta::Aguardando->value])
            ->selectRaw($valor_por_resultado.' as premiadas', [ResultadoAposta::Vencedor->value])
            ->selectRaw($valor_por_resultado.' as perdidas', [ResultadoAposta::Perdedor->value])
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN a.resultado = ? THEN a.premio WHEN a.resultado = ? THEN a.valor ELSE 0 END), 0) as saidas',
                [ResultadoAposta::Vencedor->value, ResultadoAposta::Devolvido->value],
            )
            ->selectRaw('COALESCE(SUM(a.comissao), 0) as comissao_vendedor');

        return $this->alcance->aplicar($consulta, $ids, 'a.usuarios_id')->get()->all();
    }

    private function depositos_online(): string
    {
        [$inicio, $fim] = $this->alcance->periodo_do_dia();

        return bcadd((string) DB::table('depositos')
            ->where('situacao', SituacaoDeposito::Aprovado->value)
            ->whereNull('deleted_at')
            ->whereBetween('pago_em', [$inicio, $fim])
            ->selectRaw('COALESCE(SUM(valor), 0) as total')
            ->value('total'), '0', 2);
    }

    private function saques_pagos(): string
    {
        [$inicio, $fim] = $this->alcance->periodo_do_dia();

        return bcadd((string) DB::table('saques')
            ->where('situacao', SituacaoSaque::Pago->value)
            ->whereNull('deleted_at')
            ->whereBetween('pago_em', [$inicio, $fim])
            ->selectRaw('COALESCE(SUM(valor), 0) as total')
            ->value('total'), '0', 2);
    }

    /**
     * Limites configurados do vendedor, como no antigo.
     *
     * @return array{geral: string, duplo: string, simples: string}
     */
    private function limites(Usuarios $usuario): array
    {
        $configuracao = UsuariosConfiguracoes::do_vendedor($usuario->id);

        return [
            'geral' => (string) $configuracao->limite_geral,
            'duplo' => (string) $configuracao->limite_duplo,
            'simples' => (string) $configuracao->limite_simples,
        ];
    }
}
