<?php

namespace App\Services;

use App\Enums\PublicoNotificacao;
use App\Enums\SituacaoAposta;
use App\Enums\SituacaoDeposito;
use App\Jobs\EnviarNotificacoesManuais;
use App\Models\Clientes;
use App\Models\ClientesNotificacoesEnvios;
use App\Models\Configuracoes;
use App\Models\Usuarios;
use App\Support\FusoSistema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Notificação push manual da banca pelo painel (spec 019, FR-031 a FR-033a, research R-10; o sino do
 * antigo): público (todos os inscritos, ids escolhidos ou sem interagir há N dias), sempre só
 * clientes ativos da rede com a preferência "Avisos da banca" ligada, e o limite de envios por dia
 * da banca toda. O envio em si é feito em segundo plano (EnviarNotificacoesManuais).
 */
class NotificacoesManuais
{
    public function __construct(private readonly AlcanceClientes $alcance) {}

    /**
     * Clientes alcançados pelo público, dentro da rede do usuário.
     *
     * @param  array{publico: string, clientes_ids?: ?list<int>, dias?: ?int}  $dados
     */
    public function publico(Usuarios $usuario, array $dados): Builder
    {
        $consulta = Clientes::query()
            ->where('ativo', true)
            ->whereExists(fn (QueryBuilder $q) => $q->select(DB::raw(1))
                ->from('clientes_inscricoes_notificacoes as i')
                ->whereColumn('i.clientes_id', 'clientes.id')
                ->where('i.receber_avisos_banca', true)
                ->whereNull('i.deleted_at'));

        match (PublicoNotificacao::from($dados['publico'])) {
            PublicoNotificacao::PorId => $consulta->whereIn('clientes.id', $dados['clientes_ids'] ?? []),
            PublicoNotificacao::SemInteragir => $this->sem_interagir($consulta, (int) $dados['dias']),
            PublicoNotificacao::Todos => null,
        };

        $this->alcance->aplicar($consulta, $usuario);

        return $consulta;
    }

    /**
     * @param  array{publico: string, clientes_ids?: ?list<int>, dias?: ?int}  $dados
     */
    public function contar(Usuarios $usuario, array $dados): int
    {
        return $this->publico($usuario, $dados)->count();
    }

    /**
     * Limite do dia (fuso do sistema): envios com alguém alcançado contam.
     *
     * @return array{limite: int, enviados_hoje: int, restantes: int}
     */
    public function situacao_limite(): array
    {
        $limite = (int) Configuracoes::atual()->limite_notificacoes_manuais_dia;
        $enviados = $this->enviados_hoje();

        return ['limite' => $limite, 'enviados_hoje' => $enviados, 'restantes' => max(0, $limite - $enviados)];
    }

    /**
     * Registra o envio e o manda para a fila; recusa sem envios restantes no dia ou sem ninguém
     * alcançado. A trava na configuração impede que dois envios simultâneos passem do limite.
     *
     * @param  array{publico: string, clientes_ids?: ?list<int>, dias?: ?int, titulo: string, mensagem: string}  $dados
     */
    public function enviar(Usuarios $usuario, array $dados): ClientesNotificacoesEnvios
    {
        return DB::transaction(function () use ($usuario, $dados) {
            $configuracoes = Configuracoes::query()->lockForUpdate()->firstOrFail();

            if ($this->enviados_hoje() >= (int) $configuracoes->limite_notificacoes_manuais_dia) {
                throw ValidationException::withMessages(['publico' => 'Limite de envios de hoje atingido.']);
            }

            $alcancados = $this->contar($usuario, $dados);

            if ($alcancados === 0) {
                throw ValidationException::withMessages(['publico' => 'Nenhum cliente inscrito neste público.']);
            }

            $publico = PublicoNotificacao::from($dados['publico']);

            $envio = ClientesNotificacoesEnvios::create([
                'usuarios_id' => $usuario->id,
                'publico' => $publico,
                'clientes_ids' => $publico === PublicoNotificacao::PorId ? array_values($dados['clientes_ids']) : null,
                'dias_sem_interagir' => $publico === PublicoNotificacao::SemInteragir ? (int) $dados['dias'] : null,
                'titulo' => $dados['titulo'],
                'mensagem' => $dados['mensagem'],
                'alcancados' => $alcancados,
            ]);

            EnviarNotificacoesManuais::dispatch($envio->id)->onQueue(EnviarNotificacoesManuais::FILA)->afterCommit();

            return $envio;
        });
    }

    private function enviados_hoje(): int
    {
        return ClientesNotificacoesEnvios::where('created_at', '>=', FusoSistema::inicio_do_dia())
            ->where('alcancados', '>', 0)
            ->count();
    }

    /**
     * Sem aposta efetivada, rodada de cassino nem depósito pago nos últimos N dias (inclui quem
     * nunca apostou nem depositou).
     */
    private function sem_interagir(Builder $consulta, int $dias): void
    {
        $desde = now()->subDays($dias);

        $consulta
            ->whereNotExists(fn (QueryBuilder $q) => $q->select(DB::raw(1))->from('apostas')
                ->whereColumn('apostas.clientes_id', 'clientes.id')
                ->where('apostas.situacao', SituacaoAposta::Ativa->value)
                ->whereNull('apostas.deleted_at')
                ->where('apostas.created_at', '>=', $desde))
            ->whereNotExists(fn (QueryBuilder $q) => $q->select(DB::raw(1))->from('cassinos_rodadas')
                ->whereColumn('cassinos_rodadas.clientes_id', 'clientes.id')
                ->whereNull('cassinos_rodadas.deleted_at')
                ->where('cassinos_rodadas.created_at', '>=', $desde))
            ->whereNotExists(fn (QueryBuilder $q) => $q->select(DB::raw(1))->from('depositos')
                ->whereColumn('depositos.clientes_id', 'clientes.id')
                ->whereIn('depositos.situacao', [SituacaoDeposito::Aprovado->value, SituacaoDeposito::Estornado->value])
                ->whereNull('depositos.deleted_at')
                ->where('depositos.pago_em', '>=', $desde));
    }
}
