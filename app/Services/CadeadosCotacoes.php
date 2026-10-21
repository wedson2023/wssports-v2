<?php

namespace App\Services;

use App\Enums\Funcao;
use App\Models\PorcentagensCadeados;
use App\Models\Usuarios;
use App\Support\NomesCotacoes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cadeado por cotação (spec 016, FR-019 a FR-019c): Admin ou Supervisor tranca um código na tela de
 * um usuário da rede; o cadeado vale para ele e para quem está abaixo dele. Quem trancou, ou um
 * superior dele, continua podendo alterar e destrancar.
 */
class CadeadosCotacoes
{
    public function __construct(private HistoricoCotacoes $historico) {}

    /**
     * Códigos que o solicitante não pode alterar numa regra do usuário, com o nome de quem trancou.
     *
     * @return array<string, string> {codigo: nome do autor}
     */
    public function codigos_trancados(Usuarios $solicitante, string $camada, Usuarios $usuario, ?string $tipo, ?int $campeonatos_id = null): array
    {
        $acima_do_solicitante = [$solicitante->id, ...$solicitante->ids_sub_hierarquia()];

        return $this->que_valem_para($usuario, $camada, $tipo, $campeonatos_id)
            ->reject(fn (PorcentagensCadeados $cadeado) => in_array($cadeado->trancado_por, $acima_do_solicitante, true))
            ->mapWithKeys(fn (PorcentagensCadeados $cadeado) => [$cadeado->codigo => $cadeado->autor?->nome])
            ->all();
    }

    /**
     * Cadeados que valem para o usuário (postos nele ou num superior), para a tela.
     *
     * @return list<array<string, mixed>>
     */
    public function para_tela(Usuarios $solicitante, string $camada, Usuarios $usuario, ?int $campeonatos_id = null): array
    {
        $pode_destrancar = [$solicitante->id, ...$solicitante->ids_sub_hierarquia()];

        return $this->que_valem_para($usuario, $camada, null, $campeonatos_id)
            ->map(fn (PorcentagensCadeados $cadeado) => [
                'id' => $cadeado->id,
                'tipo' => $cadeado->tipo,
                'codigo' => $cadeado->codigo,
                'trancado_por' => ['id' => $cadeado->trancado_por, 'nome' => $cadeado->autor?->nome],
                'no_usuario' => ['id' => $cadeado->usuarios_id, 'nome' => $cadeado->usuario?->nome],
                'pode_destrancar' => in_array($cadeado->trancado_por, $pode_destrancar, true),
            ])
            ->values()
            ->all();
    }

    /**
     * Tranca os códigos para o usuário (e quem está abaixo dele); devolve os cadeados para a tela.
     *
     * @param  list<string>  $codigos
     * @return list<array<string, mixed>>
     */
    public function trancar(Usuarios $autor, Usuarios $usuario, string $camada, ?string $tipo, ?int $campeonatos_id, array $codigos): array
    {
        $this->garantir_quem_tranca($autor, $usuario);

        DB::transaction(function () use ($autor, $usuario, $camada, $tipo, $campeonatos_id, $codigos) {
            $novos = [];

            foreach ($codigos as $codigo) {
                $cadeado = PorcentagensCadeados::withTrashed()->firstOrNew([
                    'usuarios_id' => $usuario->id,
                    'trancado_por' => $autor->id,
                    'camada' => $camada,
                    'tipo' => $tipo,
                    'campeonatos_id' => $campeonatos_id,
                    'codigo' => $codigo,
                ]);

                if ($cadeado->exists && ! $cadeado->trashed()) {
                    continue;
                }

                $cadeado->deleted_at = null;
                $cadeado->save();
                $novos[$codigo] = 1;
            }

            // 1 = trancado; o histórico mostra "trancou Casa"
            $this->historico->registrar($autor, $camada, HistoricoCotacoes::TRANCAR, $this->contexto($usuario, $tipo, $campeonatos_id), [], $novos);
        });

        return $this->para_tela($autor, $camada, $usuario, $campeonatos_id);
    }

    /**
     * Destranca os cadeados postos no usuário por quem pode (o solicitante ou quem está abaixo dele).
     *
     * @param  list<string>  $codigos
     * @return array{cadeados: list<array<string, mixed>>, nao_destrancados: int}
     */
    public function destrancar(Usuarios $solicitante, Usuarios $usuario, string $camada, ?string $tipo, ?int $campeonatos_id, array $codigos): array
    {
        $this->garantir_quem_tranca($solicitante, $usuario);

        $pode = [$solicitante->id, ...$solicitante->ids_sub_hierarquia()];
        $cadeados = PorcentagensCadeados::where('usuarios_id', $usuario->id)
            ->where('camada', $camada)
            ->when($tipo !== null, fn (Builder $c) => $c->where('tipo', $tipo))
            ->when($campeonatos_id !== null, fn (Builder $c) => $c->where('campeonatos_id', $campeonatos_id))
            ->whereIn('codigo', $codigos)
            ->get();

        [$permitidos, $outros] = $cadeados->partition(fn (PorcentagensCadeados $cadeado) => in_array($cadeado->trancado_por, $pode, true));

        DB::transaction(function () use ($permitidos, $solicitante, $usuario, $camada, $tipo, $campeonatos_id) {
            $permitidos->each->delete();

            $this->historico->registrar(
                $solicitante,
                $camada,
                HistoricoCotacoes::DESTRANCAR,
                $this->contexto($usuario, $tipo, $campeonatos_id),
                $permitidos->mapWithKeys(fn (PorcentagensCadeados $cadeado) => [$cadeado->codigo => 1])->all(),
                [],
            );
        });

        return [
            'cadeados' => $this->para_tela($solicitante, $camada, $usuario, $campeonatos_id),
            'nao_destrancados' => $outros->count(),
        ];
    }

    /**
     * Recusa a gravação de códigos trancados (valores) com a mensagem por código.
     *
     * @param  array<string, mixed>  $valores
     * @param  array<string, string>  $trancados
     */
    public function recusar_trancados(array $valores, array $trancados): void
    {
        $erros = [];

        foreach (array_intersect_key($trancados, $valores) as $codigo => $autor) {
            $erros["valores.{$codigo}"] = 'A cotação '.NomesCotacoes::nome($codigo)." está trancada por {$autor}.";
        }

        if ($erros !== []) {
            throw ValidationException::withMessages($erros);
        }
    }

    /**
     * Devolve aos códigos trancados o valor que tinham ("todos" altera só os demais).
     *
     * @param  array<string, mixed>  $novos
     * @param  array<string, mixed>  $antes
     * @param  array<string, string>  $trancados
     * @return array<string, mixed>
     */
    public function manter_trancados(array $novos, array $antes, array $trancados): array
    {
        foreach (array_keys($trancados) as $codigo) {
            if (array_key_exists($codigo, $antes)) {
                $novos[$codigo] = $antes[$codigo];
            } else {
                unset($novos[$codigo]);
            }
        }

        return $novos;
    }

    /**
     * Cadeados ativos postos no usuário ou num superior dele.
     *
     * @return Collection<int, PorcentagensCadeados>
     */
    private function que_valem_para(Usuarios $usuario, string $camada, ?string $tipo, ?int $campeonatos_id): Collection
    {
        return PorcentagensCadeados::with(['autor:id,nome', 'usuario:id,nome'])
            ->where('camada', $camada)
            ->whereIn('usuarios_id', $usuario->ids_hierarquia_acima())
            ->when($tipo !== null, fn (Builder $c) => $c->where('tipo', $tipo))
            ->when($campeonatos_id !== null, fn (Builder $c) => $c->where('campeonatos_id', $campeonatos_id))
            ->orderBy('id')
            ->get();
    }

    /**
     * Só Admin e Supervisor trancam, e só num usuário abaixo deles (não em si mesmos).
     */
    private function garantir_quem_tranca(Usuarios $autor, Usuarios $usuario): void
    {
        abort_unless(in_array($autor->funcao(), [Funcao::Admin, Funcao::Supervisor], true), 403, 'Você não tem permissão para esta ação.');

        if ($autor->id === $usuario->id) {
            throw ValidationException::withMessages(['usuarios_id' => 'Escolha um usuário da sua rede para trancar a cotação.']);
        }

        abort_unless($autor->funcao() === Funcao::Admin || $autor->gerencia($usuario), 403, 'Você não tem permissão para esta ação.');
    }

    /**
     * @return array<string, mixed>
     */
    private function contexto(Usuarios $usuario, ?string $tipo, ?int $campeonatos_id): array
    {
        return ['tipo' => $tipo, 'alvo_usuarios_id' => $usuario->id, 'campeonatos_id' => $campeonatos_id];
    }
}
