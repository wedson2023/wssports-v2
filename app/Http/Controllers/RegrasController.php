<?php

namespace App\Http\Controllers;

use App\Models\ClientesPromocoes;
use App\Models\Configuracoes;
use App\Services\IdentificacaoPublico;
use App\Services\Publico;
use App\Services\RegrasExibicao;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Regulamento da banca exibido no site (spec 006, US4): texto das regras do administrador, regras
 * de bônus das promoções ativas e limites de aposta de quem está vendo (as regras de cada mercado
 * são fixas no frontend). Token de gestor do painel vê os limites do visitante, como na tela
 * principal.
 */
class RegrasController extends Controller
{
    public function index(Request $request, IdentificacaoPublico $identificacao, RegrasExibicao $regras): Response
    {
        $publico = $identificacao->identificar($request);

        if ($publico->e_gestor()) {
            $publico = Publico::visitante();
        }

        return Inertia::render('Rules', [
            'regras' => Configuracoes::atual()->paragrafos_regras(),
            'regras_bonus' => $this->regras_bonus(),
            'limites_aposta' => $this->limites_aposta($publico, $regras),
        ]);
    }

    /**
     * Promoções ativas e dentro do período, com os campos usados no texto de cada uma.
     *
     * @return list<array<string, mixed>>
     */
    private function regras_bonus(): array
    {
        return ClientesPromocoes::vigentes()
            ->orderBy('categoria')
            ->orderBy('nome')
            ->get()
            ->map(fn (ClientesPromocoes $promocao) => [
                'id' => $promocao->id,
                'nome' => $promocao->nome,
                'categoria' => $promocao->categoria->value,
                'modalidade' => $promocao->modalidade->value,
                'tipo_ganho' => $promocao->tipo_ganho->value,
                'valor' => (string) $promocao->valor,
                'rollover' => (int) $promocao->rollover,
                'valor_maximo_deposito' => $promocao->valor_maximo_deposito !== null ? (string) $promocao->valor_maximo_deposito : null,
                'valor_maximo_conversao' => (string) $promocao->valor_maximo_conversao,
                'valor_minimo_aposta' => (string) $promocao->valor_minimo_aposta,
                'valor_maximo_aposta' => (string) $promocao->valor_maximo_aposta,
                'odd_minima_aposta_simples' => (string) $promocao->odd_minima_aposta_simples,
                'odd_minima_aposta_multipla' => (string) $promocao->odd_minima_aposta_multipla,
                'data_inicio' => $promocao->data_inicio->copy()->setTimezone('-03:00')->toIso8601String(),
                'data_fim' => $promocao->data_fim?->copy()->setTimezone('-03:00')->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * Limites de aposta da configuração de quem vê (visitante, cliente ou vendedor).
     *
     * @return array<string, mixed>|null
     */
    private function limites_aposta(Publico $publico, RegrasExibicao $regras): ?array
    {
        $configuracao = $regras->configuracao($publico);

        if ($configuracao === null) {
            return null;
        }

        return [
            'valor_minimo_aposta' => (string) $configuracao->valor_minimo_aposta,
            'valor_maximo_aposta' => (string) $configuracao->valor_maximo_aposta,
            'premio_maximo' => (string) $configuracao->premio_maximo,
            'multiplicador' => (int) $configuracao->multiplicador,
            'quantidade_minima_opcoes' => (int) $configuracao->quantidade_minima_opcoes,
            'quantidade_maxima_opcoes' => (int) $configuracao->quantidade_maxima_opcoes,
            'periodo_jogos' => $regras->periodo($publico)->value,
        ];
    }
}
