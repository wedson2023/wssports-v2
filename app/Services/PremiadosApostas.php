<?php

namespace App\Services;

use App\Enums\ResultadoAposta;
use App\Models\Apostas;
use App\Models\Configuracoes;
use App\Models\Usuarios;

/**
 * Premiados do período para a imagem de divulgação (spec 018, R-07, FR-020): até 23 apostas
 * premiadas, da maior para a menor, pela mesma base da lista (aba e vendedor da tela).
 */
class PremiadosApostas
{
    /**
     * Quantidade da imagem, como no antigo.
     */
    public const MAXIMO = 23;

    public function __construct(private readonly ListagemApostas $listagem) {}

    /**
     * @param  array<string, mixed>  $filtros  formato de ApostasFiltrosRequest::filtros()
     * @return array<string, mixed>
     */
    public function premiados(Usuarios $usuario, array $filtros): array
    {
        // a imagem segue só o período, a aba e o vendedor da tela; canceladas não têm premiados
        $filtros = [
            ...$filtros,
            'aba' => $filtros['aba'] === 'canceladas' ? 'todas' : $filtros['aba'],
            'codigo' => null, 'nome' => null, 'todo_periodo' => false,
            'resultado' => ResultadoAposta::Vencedor->value, 'forma_pagamento' => null, 'premio_minimo' => null,
        ];

        $apostas = $this->listagem->com_colunas_da_lista($this->listagem->consulta($usuario, $filtros))
            ->orderByDesc('premio_exibido')
            ->orderByDesc('apostas.id')
            ->limit(self::MAXIMO)
            ->get()
            ->map(fn (Apostas $aposta) => [
                'codigo' => $aposta->codigo,
                'vendedor' => $aposta->clientes_id !== null ? 'Site' : $aposta->vendedor_nome,
                'cliente' => $aposta->cliente_exibido,
                'valor' => (string) $aposta->valor,
                'premio' => bcadd((string) $aposta->premio_exibido, '0', 2),
                'situacao_exibida' => $aposta->resultado->value,
            ])
            ->all();

        $configuracoes = Configuracoes::atual();

        return [
            'data' => $apostas,
            'nome_sistema' => $configuracoes->nome_sistema,
            'url_logo' => $configuracoes->url_logo(),
            'periodo' => ['de' => $filtros['de'], 'ate' => $filtros['ate']],
        ];
    }
}
