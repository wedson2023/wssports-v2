<?php

namespace App\Http\Resources;

use App\Models\EspeciaisOpcoes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Correção de resultado na gaveta da tela Especiais (spec 024, contracts/api §1.2).
 */
class EspeciaisCorrecoesResource extends JsonResource
{
    /**
     * Nomes das opções da categoria (inclusive removidas), carregados uma vez por categoria.
     *
     * @var array<int, Collection<int, string>>
     */
    private static array $nomes_por_categoria = [];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'created_at' => $this->created_at?->setTimezone('-03:00')->toIso8601String(),
            'usuario' => $this->usuario?->login,
            'vencedoras_anteriores' => $this->opcoes($this->vencedoras_anteriores),
            'vencedoras_novas' => $this->opcoes($this->vencedoras_novas),
            'empatadas_anteriores' => $this->empatadas_anteriores,
            'empatadas_novas' => $this->empatadas_novas,
            'dividir_empate_anterior' => $this->dividir_empate_anterior,
            'dividir_empate_novo' => $this->dividir_empate_novo,
            'apostas_afetadas' => $this->apostas_afetadas,
        ];
    }

    /**
     * @param  list<int>  $ids
     * @return list<array{id: int, nome: ?string}>
     */
    private function opcoes(array $ids): array
    {
        $nomes = self::$nomes_por_categoria[$this->especiais_id] ??= EspeciaisOpcoes::withTrashed()
            ->where('especiais_id', $this->especiais_id)->pluck('nome', 'id');

        return array_map(fn (int $id) => ['id' => $id, 'nome' => $nomes->get($id)], $ids);
    }
}
