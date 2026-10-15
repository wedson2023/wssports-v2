<?php

namespace App\Http\Resources;

use App\Enums\ModalidadeEncerramento;
use App\Enums\ResultadoAposta;
use App\Enums\SituacaoPalpite;
use App\Models\Apostas;
use App\Models\ApostasEncerramentos;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Encerramento na listagem do painel (spec 010, FR-037): valor pago e quanto a aposta teria pago
 * pelo resultado final.
 *
 * @mixin ApostasEncerramentos
 */
class ApostasEncerramentosResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->aposta?->codigo,
            'cliente' => ['id' => $this->clientes_id, 'nome' => $this->cliente?->nome],
            'autor' => $this->autor(),
            'modalidade' => $this->modalidade->value,
            'situacao' => $this->situacao->value,
            'oferta' => $this->oferta !== null ? (string) $this->oferta : null,
            'valor' => $this->valor !== null ? (string) $this->valor : null,
            'margem' => (string) $this->margem,
            'valor_apostado_antes' => (string) $this->valor_apostado_antes,
            'premio_antes' => (string) $this->premio_antes,
            'teria_pago' => $this->aposta !== null ? self::teria_pago($this->aposta) : null,
            'motivo_recusa' => $this->motivo_recusa,
            'decidido_em' => $this->decidido_em?->toIso8601String(),
            'creditado_em' => $this->creditado_em?->toIso8601String(),
            'desfeito_em' => $this->desfeito_em?->toIso8601String(),
        ];
    }

    /**
     * Prêmio + acréscimo cheios se todos os palpites ativos ganharam; 0 com algum perdido; nulo com
     * palpite ainda em aberto (R-19). Espera a aposta com palpites_ativos carregados.
     */
    public static function teria_pago(Apostas $aposta): ?string
    {
        $resultados = $aposta->palpites_ativos->pluck('resultado');

        return match (true) {
            $resultados->contains(ResultadoAposta::Perdedor) => '0.00',
            $resultados->contains(ResultadoAposta::Aguardando) => null,
            default => bcadd((string) $aposta->premio, (string) $aposta->valor_acrescido, 2),
        };
    }

    private function autor(): string
    {
        return match (true) {
            $this->modalidade === ModalidadeEncerramento::Automático => 'Sistema',
            $this->usuarios_id !== null => (string) $this->usuario?->nome,
            default => 'Cliente',
        };
    }
}
