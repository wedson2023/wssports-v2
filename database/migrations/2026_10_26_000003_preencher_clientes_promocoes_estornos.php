<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Dados (spec 020, R-12): grava em clientes_promocoes_estornos os estornos que aconteceram antes da
 * tela Bônus, para esses bônus aparecerem como Estornados. Os estornos gerais antigos debitavam o
 * total por cliente, então o valor por bônus não é conhecido: ficam com valor 0 e sem débito, e a tela
 * mostra "—". Idempotente (o crédito é único na tabela).
 */
return new class extends Migration
{
    private const MOTIVO_DEPOSITO = 'Cancelado pelo estorno do depósito';

    private const MOTIVO_INDICACAO = 'Cancelado pelo estorno do depósito de um indicado';

    /**
     * Observações gravadas pelos services nos débitos dos cancelamentos automáticos.
     */
    private const OBSERVACAO_DEPOSITO = 'Bônus cancelado pelo estorno do depósito';

    private const OBSERVACAO_INDICACAO = 'Bônus de indicação cancelado pelo estorno do depósito de um indicado';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->estornos_gerais();
        $this->cancelamentos_indicacao();
        $this->cancelamentos_deposito();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // gerais antigos: sem débito e com valor 0; automáticos: com o motivo dos cancelamentos
        DB::table('clientes_promocoes_estornos')
            ->where(fn ($c) => $c->where('tipo', 'Geral')->whereNull('debito_clientes_transacoes_id')->where('valor_retirado', 0))
            ->orWhere(fn ($c) => $c->where('tipo', 'Automático')->whereIn('motivo', [self::MOTIVO_DEPOSITO, self::MOTIVO_INDICACAO]))
            ->delete();
    }

    /**
     * Bônus de promoções estornadas por inteiro cujo rollover foi cancelado pelo estorno (ou que não
     * tinham rollover).
     */
    private function estornos_gerais(): void
    {
        $promocoes = DB::table('clientes_promocoes')->where('estorno_situacao', 'Concluído')->get();

        foreach ($promocoes as $promocao) {
            $creditos = DB::table('clientes_transacoes')
                ->leftJoin('clientes_rollovers', 'clientes_rollovers.clientes_transacoes_id', '=', 'clientes_transacoes.id')
                ->where('clientes_transacoes.origem', 'Promoção')
                ->where('clientes_transacoes.referencia_id', $promocao->id)
                ->where(fn ($c) => $c->whereNull('clientes_rollovers.id')->orWhere(fn ($r) => $r->whereNotNull('clientes_rollovers.cancelado_em')
                    ->whereNull('clientes_rollovers.convertido_em')
                    ->whereNull('clientes_rollovers.expirado_em')
                    ->whereNull('clientes_rollovers.excluido_em')))
                ->select('clientes_transacoes.id', 'clientes_transacoes.clientes_id', 'clientes_rollovers.id as rollover_id')
                ->get();

            $em = $promocao->estorno_concluido_em ?? $promocao->estorno_iniciado_em ?? now();

            DB::table('clientes_promocoes_estornos')->insertOrIgnore($creditos->map(fn (object $credito) => [
                'clientes_promocoes_id' => $promocao->id,
                'clientes_id' => $credito->clientes_id,
                'clientes_transacoes_id' => $credito->id,
                'clientes_rollovers_id' => $credito->rollover_id,
                'tipo' => 'Geral',
                'motivo' => $promocao->estorno_motivo ?? 'Estorno da promoção',
                'usuarios_id' => $promocao->estorno_usuarios_id,
                'valor_retirado' => '0.00',
                'debito_clientes_transacoes_id' => null,
                'created_at' => $em,
                'updated_at' => $em,
            ])->all());
        }
    }

    /**
     * Grupos de indicação cancelados: o crédito vem do grupo; o débito é o da mesma promoção e cliente
     * gravado mais perto do cancelamento.
     */
    private function cancelamentos_indicacao(): void
    {
        $grupos = DB::table('clientes_indicacoes_bonus')->whereNotNull('cancelado_em')->get();

        foreach ($grupos as $grupo) {
            $debito = $this->debito_mais_proximo($grupo->clientes_id, $grupo->clientes_promocoes_id, self::OBSERVACAO_INDICACAO, $grupo->cancelado_em);
            $this->registrar_automatico($grupo->clientes_transacoes_id, $grupo->clientes_rollovers_id, self::MOTIVO_INDICACAO, $debito, $grupo->cancelado_em);
        }
    }

    /**
     * Bônus cancelados pelo estorno total do depósito: o rollover tem cancelado_em e excluido_em e há um
     * débito com a observação do cancelamento perto dessa data.
     */
    private function cancelamentos_deposito(): void
    {
        $rollovers = DB::table('clientes_rollovers')
            ->where('tipo', 'Bônus')
            ->whereNotNull('cancelado_em')
            ->whereNotNull('excluido_em')
            ->whereNotNull('clientes_promocoes_id')
            ->get();

        foreach ($rollovers as $rollover) {
            $debito = $this->debito_mais_proximo($rollover->clientes_id, $rollover->clientes_promocoes_id, self::OBSERVACAO_DEPOSITO, $rollover->cancelado_em);

            // sem o débito do cancelamento, foi o próprio cliente que excluiu
            if ($debito !== null) {
                $this->registrar_automatico($rollover->clientes_transacoes_id, $rollover->id, self::MOTIVO_DEPOSITO, $debito, $rollover->cancelado_em);
            }
        }
    }

    private function debito_mais_proximo(int $clientes_id, int $clientes_promocoes_id, string $observacao, string $em): ?object
    {
        $momento = Carbon::parse($em);

        return DB::table('clientes_transacoes')
            ->where('clientes_id', $clientes_id)
            ->where('referencia_id', $clientes_promocoes_id)
            ->where('origem', 'Bônus excluído')
            ->where('observacao', $observacao)
            ->whereBetween('created_at', [$momento->copy()->subMinute(), $momento->copy()->addMinute()])
            ->orderByRaw('ABS(TIMESTAMPDIFF(SECOND, created_at, ?))', [$momento->toDateTimeString()])
            ->first();
    }

    private function registrar_automatico(int $credito_id, ?int $rollover_id, string $motivo, ?object $debito, string $em): void
    {
        $credito = DB::table('clientes_transacoes')->find($credito_id);

        if ($credito === null) {
            return;
        }

        DB::table('clientes_promocoes_estornos')->insertOrIgnore([
            'clientes_promocoes_id' => $credito->referencia_id,
            'clientes_id' => $credito->clientes_id,
            'clientes_transacoes_id' => $credito->id,
            'clientes_rollovers_id' => $rollover_id,
            'tipo' => 'Automático',
            'motivo' => $motivo,
            'usuarios_id' => null,
            'valor_retirado' => $debito->valor ?? '0.00',
            'debito_clientes_transacoes_id' => $debito?->id,
            'created_at' => $em,
            'updated_at' => $em,
        ]);
    }
};
