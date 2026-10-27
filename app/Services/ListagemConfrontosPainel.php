<?php

namespace App\Services;

use App\Enums\AlvoRegra;
use App\Enums\Funcao;
use App\Enums\ResultadoAposta;
use App\Enums\SituacaoAposta;
use App\Enums\SituacaoPalpite;
use App\Models\Apostas;
use App\Models\Configuracoes;
use App\Models\Confrontos;
use App\Models\ConfrontosAoVivoNaoPermitidos;
use App\Models\ConfrontosNaoPermitidos;
use App\Models\Usuarios;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Lista de confrontos da tela Confrontos do painel (spec 023, R-02 a R-08): filtros, dia de 30 dias
 * atrás até depois de amanhã, tipo Todos / Pré-jogo / Ao vivo, o ao vivo de agora, as restrições
 * por rede, as apostas em aberto e os cartões. Usada pela lista, pela gaveta, pelos cartões e pela
 * aba Mais apostados.
 */
class ListagemConfrontosPainel
{
    // deslocamento de cada atalho de dia em relação a hoje
    private const DESLOCAMENTO_DIA = [
        'ontem' => -1,
        'hoje' => 0,
        'amanha' => 1,
        'depois_de_amanha' => 2,
    ];

    public function __construct(
        private readonly AlcanceHierarquia $alcance,
        private readonly ListagemApostas $listagem_apostas,
    ) {}

    /**
     * Confrontos do filtro, ordenados por início e time da casa.
     *
     * @param  array<string, mixed>  $filtros  formato de FiltrosConfrontosPainelRequest::filtros()
     */
    public function consulta(array $filtros): Builder
    {
        return Confrontos::query()
            ->when($filtros['busca'] ?? null, function (Builder $c, string $busca) {
                $termo = '%'.addcslashes($busca, '%_\\').'%';
                // agrupada, para a busca não escapar dos demais filtros (defeito do antigo)
                $c->where(fn (Builder $b) => $b->where('time_casa', 'like', $termo)
                    ->orWhere('time_fora', 'like', $termo)
                    ->orWhereHas('campeonato', fn (Builder $ca) => $ca->where('nome', 'like', $termo)));
            })
            ->when(isset($filtros['campeonatos_id']), fn (Builder $c) => $c->where('campeonatos_id', $filtros['campeonatos_id']))
            ->when(isset($filtros['ativo']), fn (Builder $c) => $c->where('ativo', $filtros['ativo']))
            ->when(isset($filtros['manual']), fn (Builder $c) => $c->where('manual', $filtros['manual']))
            ->when(isset($filtros['esporte']), fn (Builder $c) => $c->where('esporte', mb_strtoupper($filtros['esporte'])))
            ->when(isset($filtros['situacao']), fn (Builder $c) => $c->where('situacao', $filtros['situacao']))
            ->when($this->intervalo_do_dia($filtros), fn (Builder $c, array $intervalo) => $c->whereBetween('data_inicio', $intervalo))
            ->when(($filtros['tipo'] ?? 'todos') === 'ao_vivo', fn (Builder $c) => $c->whereHas('ao_vivo', fn (Builder $av) => $this->ao_vivo_agora($av)))
            ->when(($filtros['tipo'] ?? 'todos') === 'pre_jogo', fn (Builder $c) => $c->whereDoesntHave('ao_vivo', fn (Builder $av) => $this->ao_vivo_agora($av)))
            ->orderBy('data_inicio')
            ->orderBy('time_casa');
    }

    /**
     * Acrescenta o que a lista e a gaveta mostram: campeonato, ao vivo de agora, resultado,
     * restrições por rede (data-model §3) e apostas em aberto na rede de quem consulta (R-21).
     */
    public function com_detalhes(Builder $consulta, Usuarios $usuario): Builder
    {
        $consulta->with([
            'campeonato',
            'resultado',
            'ao_vivo' => fn (HasOne|Builder $av) => $this->ao_vivo_agora($av),
        ])
            ->withExists([
                'nao_permitidos as nao_permitido' => fn (Builder $c) => $this->alcance->nao_permitidos_do_usuario($c, $usuario),
            ])
            ->addSelect([
                'nao_permitido_proprio_pre_jogo_id' => $this->proprio(ConfrontosNaoPermitidos::query(), $usuario),
                'nao_permitido_proprio_ao_vivo_id' => $this->proprio(ConfrontosAoVivoNaoPermitidos::query(), $usuario),
                'apostas_em_aberto' => $this->apostas_em_aberto($usuario),
            ]);

        if ($this->alcance->e_gestor_geral($usuario)) {
            $consulta->addSelect([
                'redes_desativadas' => ConfrontosNaoPermitidos::query()
                    ->selectRaw('COUNT(DISTINCT usuarios_id)')
                    ->whereColumn('confrontos_id', 'confrontos.id')
                    ->where('alvo', AlvoRegra::Vendedores),
            ]);
        }

        return $consulta;
    }

    /**
     * Cartões da tela (contracts/api §2.1).
     *
     * @param  array<string, mixed>  $filtros
     * @return array{jogos: int, ao_vivo: int, desativados: int, pendentes: ?int}
     */
    public function resumo(Usuarios $usuario, array $filtros, ExposicaoConfrontos $exposicao): array
    {
        $sem_situacao = [...$filtros, 'situacao' => null];
        $sem_dia = [...$sem_situacao, 'dia' => null, 'data' => null, 'tipo' => 'ao_vivo'];

        $desativados = $this->consulta($sem_situacao)->where(function (Builder $c) use ($usuario) {
            $c->where('ativo', false);

            // quem não é Admin nem Supervisor conta também os que desativou para a rede dele
            if (! $this->alcance->e_gestor_geral($usuario)) {
                $c->orWhereHas('nao_permitidos', fn (Builder $np) => $np->where('alvo', AlvoRegra::Vendedores)->where('usuarios_id', $usuario->id));
            }
        });

        return [
            'jogos' => $this->consulta($sem_situacao)->count(),
            'ao_vivo' => $this->consulta($sem_dia)->count(),
            'desativados' => $desativados->count(),
            'pendentes' => Funcao::usuario_pode($usuario, 'confrontos.lancar_resultado')
                ? $exposicao->pendentes($usuario, null)->toBase()->getCountForPagination()
                : null,
        ];
    }

    /**
     * Ao vivo de agora (R-04): em andamento e atualizado dentro do tempo de permanência, a mesma
     * regra da consulta do público, sem o minuto limite e os não permitidos (que são do público).
     */
    public function ao_vivo_agora(HasOne|Builder $consulta): HasOne|Builder
    {
        return $consulta->whereIn('situacao', RegrasExibicao::situacoes_ao_vivo())
            ->where('ultima_atualizacao_em', '>=', now()->subMinutes(Configuracoes::atual()->minutos_permanencia_ao_vivo));
    }

    /**
     * Início e fim, em UTC, do dia pedido no fuso de quem vê; null sem dia (a lista não filtra).
     *
     * @param  array<string, mixed>  $filtros
     * @return array{0: string, 1: string}|null
     */
    private function intervalo_do_dia(array $filtros): ?array
    {
        $fuso = $filtros['fuso_horario'] ?? '-03:00';

        if (! empty($filtros['data'])) {
            $inicio = CarbonImmutable::createFromFormat('Y-m-d', $filtros['data'], $fuso)->startOfDay();
        } elseif (isset(self::DESLOCAMENTO_DIA[$filtros['dia'] ?? ''])) {
            $inicio = CarbonImmutable::now($fuso)->startOfDay()->addDays(self::DESLOCAMENTO_DIA[$filtros['dia']]);
        } else {
            return null;
        }

        return [$inicio->utc()->format('Y-m-d H:i:s'), $inicio->endOfDay()->utc()->format('Y-m-d H:i:s')];
    }

    /**
     * Id do não permitido com dono em quem consulta (alvo Vendedores), para ele reativar.
     */
    private function proprio(Builder $nao_permitidos, Usuarios $usuario): Builder
    {
        return $nao_permitidos->select('id')
            ->whereColumn('confrontos_id', 'confrontos.id')
            ->where('alvo', AlvoRegra::Vendedores)
            ->where('usuarios_id', $usuario->id)
            ->limit(1);
    }

    /**
     * Quantidade de apostas ativas aguardando resultado no jogo, no alcance de quem consulta.
     */
    private function apostas_em_aberto(Usuarios $usuario): Builder
    {
        $consulta = Apostas::query()
            ->selectRaw('COUNT(DISTINCT apostas.id)')
            ->join('apostas_palpites as pal', 'pal.apostas_id', '=', 'apostas.id')
            ->whereColumn('pal.confrontos_id', 'confrontos.id')
            ->whereNull('pal.deleted_at')
            ->where('apostas.situacao', SituacaoAposta::Ativa->value)
            ->where('pal.situacao', SituacaoPalpite::Ativo->value)
            ->where('pal.resultado', ResultadoAposta::Aguardando->value);

        $this->listagem_apostas->aplicar_alcance($consulta, $usuario, null);

        return $consulta;
    }
}
