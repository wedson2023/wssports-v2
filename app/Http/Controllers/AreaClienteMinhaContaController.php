<?php

namespace App\Http\Controllers;

use App\Enums\OrigemTransacao;
use App\Enums\TipoRollover;
use App\Http\Requests\ExtratoMinhaContaRequest;
use App\Http\Resources\ExtratoMinhaContaResource;
use App\Http\Resources\MinhaContaResource;
use App\Models\Apostas;
use App\Models\CassinosSessoes;
use App\Models\Clientes;
use App\Models\ClientesRollovers;
use App\Models\ClientesTransacoes;
use App\Models\Depositos;
use App\Models\Saques;
use App\Services\AcessoAssistido;
use App\Services\GestaoBonus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;

/**
 * Dados da tela de créditos do cliente logado: saldos e extrato (spec 007, US1).
 */
class AreaClienteMinhaContaController extends Controller
{
    /**
     * Origens de cada filtro de operação do extrato (FR-010). "Estornos" junta o estorno de
     * depósito e o estorno que já existia (cancelamento de aposta e estorno de promoção).
     */
    private const ORIGENS_POR_OPERACAO = [
        'palpites' => [OrigemTransacao::Aposta, OrigemTransacao::Prêmio, OrigemTransacao::EncerramentoAposta],
        'depositos' => [OrigemTransacao::Depósito],
        'saques' => [OrigemTransacao::Saque, OrigemTransacao::DevoluçãoSaque],
        'bonus' => [OrigemTransacao::Promoção, OrigemTransacao::ConversãoBônus, OrigemTransacao::BônusExpirado, OrigemTransacao::BônusExcluído],
        'cashback' => [OrigemTransacao::Cashback],
        'estornos' => [OrigemTransacao::EstornoDepósito, OrigemTransacao::Estorno],
        // cassino (spec 011): uma linha por sessão de jogo
        'cassino' => [OrigemTransacao::Cassino],
    ];

    public function __construct(private GestaoBonus $bonus) {}

    public function show(Request $request, AcessoAssistido $acesso_assistido): MinhaContaResource
    {
        $acesso = $acesso_assistido->acesso($request);

        return (new MinhaContaResource($this->cliente($request)))->com_acesso_assistido(
            $acesso ? ['usuario' => $acesso->usuario?->nome, 'expira_em' => $acesso->expira_em] : null,
        );
    }

    public function extrato(ExtratoMinhaContaRequest $request): AnonymousResourceCollection
    {
        $filtros = $request->validated();
        $cliente = $this->cliente($request);

        $transacoes = $cliente->transacoes()
            ->when(isset($filtros['operacao']), fn (Builder $c) => $c->whereIn('origem', self::ORIGENS_POR_OPERACAO[$filtros['operacao']]))
            // cassino (spec 011, R-20): cada sessão de jogo aparece uma vez, pela transação mais recente
            ->where(fn (Builder $c) => $c->where('origem', '!=', OrigemTransacao::Cassino)
                ->orWhereIn('id', fn ($ultima) => $ultima->selectRaw('max(id)')
                    ->from('clientes_transacoes')
                    ->where('clientes_id', $cliente->id)
                    ->where('origem', OrigemTransacao::Cassino->value)
                    ->whereNull('deleted_at')
                    ->groupBy('referencia_id')))
            ->when(isset($filtros['situacao']), fn (Builder $c) => $c->whereExists(fn ($aposta) => $aposta->selectRaw('1')
                ->from('apostas')
                ->whereColumn('apostas.id', 'clientes_transacoes.referencia_id')
                ->where('apostas.resultado', $filtros['situacao'])))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($filtros['por_pagina'] ?? 30, ['*'], 'pagina', $filtros['pagina'] ?? null)
            ->withQueryString();

        $this->carregar_relacionados($transacoes->getCollection());

        return ExtratoMinhaContaResource::collection($transacoes);
    }

    /**
     * Carrega em lote o que cada linha mostra (aposta e rollover), sem uma consulta por linha.
     *
     * @param  Collection<int, ClientesTransacoes>  $transacoes
     */
    private function carregar_relacionados(Collection $transacoes): void
    {
        $ids_apostas = $transacoes->filter(fn (ClientesTransacoes $t) => in_array($t->origem, [OrigemTransacao::Aposta, OrigemTransacao::Prêmio, OrigemTransacao::EncerramentoAposta], true))
            ->pluck('referencia_id')
            ->filter();

        $apostas = Apostas::whereIn('id', $ids_apostas)->get()->keyBy('id');
        $rollovers = ClientesRollovers::with('promocao')
            ->whereIn('clientes_transacoes_id', $transacoes->pluck('id'))
            ->get()
            ->keyBy('clientes_transacoes_id');
        $depositos = Depositos::whereIn('clientes_transacoes_id', $transacoes->pluck('id'))->get()->keyBy('clientes_transacoes_id');
        $ids_saques = $transacoes->filter(fn (ClientesTransacoes $t) => in_array($t->origem, [OrigemTransacao::Saque, OrigemTransacao::DevoluçãoSaque], true))
            ->pluck('referencia_id')
            ->filter();
        $saques = Saques::whereIn('id', $ids_saques)->get()->keyBy('id');
        $ids_sessoes = $transacoes->filter(fn (ClientesTransacoes $t) => $t->origem === OrigemTransacao::Cassino)->pluck('referencia_id')->filter();
        $sessoes_cassino = CassinosSessoes::with('jogo')->whereIn('id', $ids_sessoes)->get()->keyBy('id');

        foreach ($transacoes as $transacao) {
            if (in_array($transacao->origem, [OrigemTransacao::Aposta, OrigemTransacao::Prêmio, OrigemTransacao::EncerramentoAposta], true)) {
                $transacao->setRelation('aposta', $apostas->get($transacao->referencia_id));
            }

            if (in_array($transacao->origem, [OrigemTransacao::Saque, OrigemTransacao::DevoluçãoSaque], true)) {
                $transacao->setRelation('saque', $saques->get($transacao->referencia_id));
            }

            if ($transacao->origem === OrigemTransacao::Cassino) {
                $transacao->setRelation('sessao_cassino', $sessoes_cassino->get($transacao->referencia_id));
            }

            $rollover = $rollovers->get($transacao->id);
            $transacao->setRelation('rollover', $rollover);
            $transacao->setRelation('deposito', $depositos->get($transacao->id));

            if ($rollover?->tipo === TipoRollover::Bônus) {
                $transacao->setRelation('acoes_bonus', $this->bonus->acoes($rollover));
            }
        }
    }

    private function cliente(Request $request): Clientes
    {
        return $request->user('clientes');
    }
}
