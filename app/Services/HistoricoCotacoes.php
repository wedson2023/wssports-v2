<?php

namespace App\Services;

use App\Models\PorcentagensHistoricos;
use App\Models\Usuarios;

/**
 * Histórico simples das regras de cotação (spec 016, FR-018a): uma linha por gravação ou por
 * trancar/destrancar, só com os códigos que mudaram e o valor antes e depois (null = sem ajuste).
 */
class HistoricoCotacoes
{
    public const ALTERAR = 'alterar';

    public const TRANCAR = 'trancar';

    public const DESTRANCAR = 'destrancar';

    /**
     * Campos de contexto aceitos (os demais são ignorados).
     */
    private const CONTEXTO = ['tipo', 'alvo', 'alvo_usuarios_id', 'clientes_id', 'campeonatos_id', 'confrontos_id'];

    /**
     * Registra a alteração; sem diferença entre antes e depois, não grava nada.
     *
     * @param  array<string, mixed>  $contexto  tipo, alvo, alvo_usuarios_id, clientes_id, campeonatos_id, confrontos_id
     * @param  array<string, mixed>  $antes  {codigo: valor} antes da gravação
     * @param  array<string, mixed>  $depois  {codigo: valor} depois da gravação
     */
    public function registrar(Usuarios $autor, string $camada, string $acao, array $contexto, array $antes, array $depois): void
    {
        $alteracoes = $this->diferencas($antes, $depois);

        if ($alteracoes === []) {
            return;
        }

        $contexto = array_intersect_key($contexto, array_flip(self::CONTEXTO));

        // o alvo pode chegar como enum
        if (isset($contexto['alvo']) && $contexto['alvo'] instanceof \BackedEnum) {
            $contexto['alvo'] = $contexto['alvo']->value;
        }

        PorcentagensHistoricos::create([
            ...$contexto,
            'usuarios_id' => $autor->id,
            'camada' => $camada,
            'acao' => $acao,
            'alteracoes' => $alteracoes,
        ]);
    }

    /**
     * Códigos cujo valor mudou, com antes e depois em 2 casas (null = ausente).
     *
     * @return array<string, array{antes: float|null, depois: float|null}>
     */
    private function diferencas(array $antes, array $depois): array
    {
        $normalizar = fn ($valor) => $valor === null ? null : round((float) $valor, 2);
        $alteracoes = [];

        foreach (array_unique([...array_keys($antes), ...array_keys($depois)]) as $codigo) {
            $valor_antes = $normalizar($antes[$codigo] ?? null);
            $valor_depois = $normalizar($depois[$codigo] ?? null);

            if ($valor_antes !== $valor_depois) {
                $alteracoes[$codigo] = ['antes' => $valor_antes, 'depois' => $valor_depois];
            }
        }

        return $alteracoes;
    }
}
