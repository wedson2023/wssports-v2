<?php

namespace App\Services;

use App\Enums\CategoriaPromocao;
use App\Enums\OrigemTransacao;
use App\Models\Clientes;
use App\Models\ClientesIndicacoes;
use App\Models\ClientesIndicacoesBonus;
use App\Models\ClientesPromocoes;
use App\Models\ClientesTransacoes;
use App\Models\Depositos;
use Illuminate\Support\Facades\DB;

/**
 * Indicações entre clientes (spec 007, US9, research R-17): a indicação vale quando o indicado faz
 * o primeiro depósito aprovado; a cada grupo completo da quantidade exigida pela promoção de
 * Indicação, o indicador recebe o bônus. O estorno do primeiro depósito desfaz a indicação e o
 * bônus do grupo.
 */
class Indicacoes
{
    public function __construct(private SaldoClientes $saldo, private RolloverClientes $rollover) {}

    /**
     * Cadastro feito pelo link de outro cliente; código inexistente é ignorado.
     */
    public function registrar(Clientes $indicado, ?string $codigo_indicacao): void
    {
        if (! $codigo_indicacao) {
            return;
        }

        $indicador = Clientes::where('codigo_indicacao', mb_strtoupper($codigo_indicacao))
            ->where('id', '!=', $indicado->id)
            ->first();

        if ($indicador === null) {
            return;
        }

        $indicado->forceFill(['indicado_por' => $indicador->id])->save();
        ClientesIndicacoes::create(['indicador_clientes_id' => $indicador->id, 'indicado_clientes_id' => $indicado->id]);
    }

    /**
     * Primeiro depósito aprovado do indicado: a indicação passa a valer e os grupos completos
     * são pagos.
     */
    public function validar(Clientes $indicado, Depositos $deposito): void
    {
        $indicacao = ClientesIndicacoes::where('indicado_clientes_id', $indicado->id)->whereNull('validada_em')->first();

        if ($indicacao === null) {
            return;
        }

        $indicacao->forceFill(['validada_em' => now(), 'depositos_id' => $deposito->id])->save();
        $this->pagar_grupos($indicacao->indicador_clientes_id);
    }

    /**
     * Um bônus por grupo completo de indicações válidas ainda sem bônus (recorrente: 5, 10, 15...).
     */
    public function pagar_grupos(int $indicador_id): void
    {
        DB::transaction(function () use ($indicador_id) {
            // o bloqueio do indicador impede dois depósitos simultâneos de pagarem o mesmo grupo
            $indicador = Clientes::lockForUpdate()->find($indicador_id);
            $promocao = $indicador ? $this->promocao_vigente($indicador) : null;

            if ($promocao === null || ! $indicador->ativo || ! $indicador->configuracoes?->aceita_promocao) {
                return;
            }

            $quantidade = max(1, (int) ($promocao->quantidade_indicacoes ?? 5));

            while (true) {
                $livres = ClientesIndicacoes::where('indicador_clientes_id', $indicador->id)
                    ->whereNotNull('validada_em')
                    ->whereNull('clientes_indicacoes_bonus_id')
                    ->orderBy('validada_em')
                    ->orderBy('id')
                    ->limit($quantidade)
                    ->get();

                if ($livres->count() < $quantidade) {
                    return;
                }

                $credito = $this->saldo->creditar($indicador, $promocao->modalidade->carteira(), (string) $promocao->valor, OrigemTransacao::Promoção, null, $promocao->id, "Promoção: {$promocao->nome}");
                $rollover = $this->rollover->criar_bonus($indicador, $credito, $promocao);

                $grupo = ClientesIndicacoesBonus::create([
                    'clientes_id' => $indicador->id,
                    'clientes_promocoes_id' => $promocao->id,
                    'quantidade' => $quantidade,
                    'clientes_transacoes_id' => $credito->id,
                    'clientes_rollovers_id' => $rollover?->id,
                ]);

                ClientesIndicacoes::whereIn('id', $livres->pluck('id'))->update(['clientes_indicacoes_bonus_id' => $grupo->id]);
            }
        });
    }

    /**
     * Estorno do primeiro depósito do indicado: a indicação volta a pendente; se ela tinha
     * completado um grupo, o bônus é cancelado (mesmo deixando o saldo de bônus negativo) e as
     * outras indicações do grupo ficam livres para um novo grupo.
     */
    public function desfazer(Depositos $deposito): void
    {
        $indicacao = ClientesIndicacoes::with('bonus.rollover')->where('depositos_id', $deposito->id)->first();

        if ($indicacao === null) {
            return;
        }

        $grupo = $indicacao->bonus;
        $indicacao->forceFill(['validada_em' => null, 'depositos_id' => null, 'clientes_indicacoes_bonus_id' => null])->save();

        if ($grupo !== null && $grupo->cancelado_em === null) {
            $this->cancelar_grupo($grupo);
        }

        $this->pagar_grupos($indicacao->indicador_clientes_id);
    }

    /**
     * Resumo da área "Minhas indicações" (contracts/api.md §8).
     *
     * @return array<string, mixed>
     */
    public function resumo(Clientes $cliente): array
    {
        $promocao = $this->promocao_vigente($cliente);
        $meta = (int) ($promocao?->quantidade_indicacoes ?? 5);
        $validas = ClientesIndicacoes::where('indicador_clientes_id', $cliente->id)->whereNotNull('validada_em')->count();
        $livres = ClientesIndicacoes::where('indicador_clientes_id', $cliente->id)->whereNotNull('validada_em')->whereNull('clientes_indicacoes_bonus_id')->count();

        return [
            'validas' => $validas,
            'meta' => $meta,
            'faltam_para_proximo' => max(0, $meta - $livres),
            'bonus_recebidos' => ClientesIndicacoesBonus::where('clientes_id', $cliente->id)->whereNull('cancelado_em')->count(),
            'valor_bonus' => $promocao ? (string) $promocao->valor : null,
            'vigente' => $promocao !== null,
            'link' => rtrim(url('/'), '/').'/?indicacao='.$cliente->codigo_indicacao,
        ];
    }

    /**
     * Debita o bônus do grupo (pode deixar o saldo de bônus negativo), cancela o rollover dele e
     * solta as indicações do grupo.
     */
    private function cancelar_grupo(ClientesIndicacoesBonus $grupo): void
    {
        $indicador = Clientes::withTrashed()->findOrFail($grupo->clientes_id);
        $credito = ClientesTransacoes::findOrFail($grupo->clientes_transacoes_id);

        $this->saldo->debitar($indicador, $credito->carteira, (string) $credito->valor, OrigemTransacao::BônusExcluído, null, $grupo->clientes_promocoes_id, 'Bônus de indicação cancelado pelo estorno do depósito de um indicado', permitir_negativo: true);

        if ($grupo->rollover !== null) {
            $this->rollover->cancelar($grupo->rollover);
        }

        $grupo->forceFill(['cancelado_em' => now()])->save();
        ClientesIndicacoes::where('clientes_indicacoes_bonus_id', $grupo->id)->update(['clientes_indicacoes_bonus_id' => null]);
    }

    private function promocao_vigente(Clientes $indicador): ?ClientesPromocoes
    {
        return ClientesPromocoes::vigentes()
            ->where('categoria', CategoriaPromocao::Indicação)
            ->paraAfiliado($indicador->codigo_afiliado)
            ->orderByRaw('codigo_afiliado is null')
            ->orderBy('id')
            ->first();
    }
}
