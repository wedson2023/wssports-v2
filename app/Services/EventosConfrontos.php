<?php

namespace App\Services;

use App\Enums\ResultadoAposta;
use App\Enums\SituacaoAposta;
use App\Enums\SituacaoPalpite;
use App\Enums\TipoEventoConfronto;
use App\Enums\TipoNotificacao;
use App\Models\ConfrontosEventos;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Eventos dos jogos (spec 010, FR-042, R-16, R-17): gol, gol anulado, intervalo e fim de jogo,
 * registrados uma vez e avisados aos clientes com aposta em aberto no jogo (um aviso por cliente,
 * com o link da aposta mais recente dele nesse jogo).
 */
class EventosConfrontos
{
    public function __construct(private NotificacoesPush $notificacoes) {}

    /**
     * Registra o evento; nulo quando o intervalo ou o fim de jogo já foram registrados (uma vez por
     * jogo). Gols não têm chave: um gol anulado seguido de outro com o mesmo placar são dois fatos.
     */
    public function registrar(int $confrontos_id, ?int $confrontos_ao_vivo_id, TipoEventoConfronto $tipo, int $placar_casa, int $placar_fora, ?int $minuto): ?ConfrontosEventos
    {
        $unico = in_array($tipo, [TipoEventoConfronto::Intervalo, TipoEventoConfronto::FimDeJogo], true);

        try {
            return ConfrontosEventos::create([
                'confrontos_id' => $confrontos_id,
                'confrontos_ao_vivo_id' => $confrontos_ao_vivo_id,
                'tipo' => $tipo,
                'placar_casa' => $placar_casa,
                'placar_fora' => $placar_fora,
                'minuto' => $minuto,
                'chave' => $unico ? "{$tipo->value}:{$confrontos_id}" : null,
            ]);
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    /**
     * Registra e avisa; falha vai para o log sem interromper quem chamou (a carga).
     */
    public function registrar_e_avisar(int $confrontos_id, ?int $confrontos_ao_vivo_id, TipoEventoConfronto $tipo, int $placar_casa, int $placar_fora, ?int $minuto): void
    {
        try {
            $evento = $this->registrar($confrontos_id, $confrontos_ao_vivo_id, $tipo, $placar_casa, $placar_fora, $minuto);

            if ($evento !== null) {
                $this->avisar($evento);
            }
        } catch (Throwable $erro) {
            Log::warning("Evento {$tipo->value} do jogo {$confrontos_id} não avisado: ".class_basename($erro).' - '.$erro->getMessage());
        }
    }

    /**
     * Avisa os clientes com palpite ativo no jogo em aposta Ativa e Aguardando (FR-043).
     */
    public function avisar(ConfrontosEventos $evento): void
    {
        if (! NotificacoesPush::habilitado()) {
            return;
        }

        $confronto = $evento->confronto()->with('campeonato')->first();

        // aposta mais recente de cada cliente nesse jogo (link do aviso, FR-045)
        $apostas = DB::table('apostas_palpites as ap')
            ->join('apostas as a', 'a.id', '=', 'ap.apostas_id')
            ->where('ap.confrontos_id', $evento->confrontos_id)
            ->where('ap.situacao', SituacaoPalpite::Ativo->value)
            ->where('a.situacao', SituacaoAposta::Ativa->value)
            ->where('a.resultado', ResultadoAposta::Aguardando->value)
            ->whereNotNull('a.clientes_id')
            ->whereNull('ap.deleted_at')
            ->whereNull('a.deleted_at')
            ->groupBy('a.clientes_id')
            ->selectRaw('a.clientes_id, MAX(a.id) as apostas_id')
            ->get();

        if ($apostas->isNotEmpty()) {
            $codigos = DB::table('apostas')->whereIn('id', $apostas->pluck('apostas_id'))->pluck('codigo', 'id');
            [$titulo, $corpo] = $this->textos($evento, $confronto?->time_casa, $confronto?->time_fora, $confronto?->campeonato?->nome);

            foreach ($apostas as $aposta) {
                $this->notificacoes->avisar(
                    (int) $aposta->clientes_id,
                    TipoNotificacao::do_evento($evento->tipo),
                    "evento:{$evento->id}",
                    $titulo,
                    $corpo,
                    '/bilhete/'.$codigos[$aposta->apostas_id],
                    $evento->id,
                    (int) $aposta->apostas_id,
                );
            }
        }

        $evento->forceFill(['notificado_em' => now()])->save();
    }

    /**
     * Título e corpo de cada evento (R-17).
     *
     * @return array{0: string, 1: ?string}
     */
    private function textos(ConfrontosEventos $evento, ?string $casa, ?string $fora, ?string $campeonato): array
    {
        $placar = "{$casa} {$evento->placar_casa} x {$evento->placar_fora} {$fora}";
        $minuto = $evento->minuto !== null ? "{$evento->minuto}'" : null;

        return match ($evento->tipo) {
            TipoEventoConfronto::Gol => ["⚽ GOL! {$placar}", trim(implode(' · ', array_filter([$minuto, $campeonato])))],
            TipoEventoConfronto::GolAnulado => ["Gol anulado: {$placar}", $minuto],
            TipoEventoConfronto::Intervalo => ["Intervalo: {$placar}", null],
            TipoEventoConfronto::FimDeJogo => ["Fim de jogo: {$placar}", null],
        };
    }
}
