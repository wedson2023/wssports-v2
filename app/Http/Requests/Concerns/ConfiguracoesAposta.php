<?php

namespace App\Http\Requests\Concerns;

use App\Enums\PeriodoJogos;
use App\Models\UsuariosConfiguracoes;
use App\Support\FusoSistema;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Regras das configurações de aposta (spec 004, FR-064), compartilhadas pelas configurações de
 * vendedores, clientes e visitantes. Os campos são opcionais, para não mudar as rotas existentes.
 */
trait ConfiguracoesAposta
{
    private string $valor_duas_casas = 'regex:/^\d{1,13}(\.\d{1,2})?$/';

    /**
     * A data de travamento sem fuso explícito vale no fuso do sistema (-03:00) e é gravada em UTC.
     */
    protected function converter_data_travamento(): void
    {
        $data = $this->input('data_travamento_sistema');

        if (! is_string($data) || trim($data) === '') {
            return;
        }

        try {
            $this->merge([
                'data_travamento_sistema' => Carbon::parse($data, FusoSistema::FUSO)->utc()->format('Y-m-d H:i:s'),
            ]);
        } catch (Throwable) {
            // texto que não é data: a regra "date" recusa com a mensagem em português
        }
    }

    /**
     * @param  list<string>  $campos
     * @return array<string, list<mixed>>
     */
    protected function regras_aposta(array $campos): array
    {
        $regras = [];

        foreach ($campos as $campo) {
            $regras[$campo] = array_values(array_filter(['sometimes', ...$this->regra_aposta($campo)]));
        }

        return $regras;
    }

    /**
     * @param  list<string>  $campos
     * @return array<string, string>
     */
    protected function mensagens_aposta(array $campos): array
    {
        $mensagens = [];

        foreach ($campos as $campo) {
            $nome = 'O campo '.str_replace('_', ' ', $campo);

            $mensagens += [
                "{$campo}.boolean" => "{$nome} deve ser verdadeiro ou falso.",
                "{$campo}.integer" => "{$nome} deve ser um número inteiro.",
                "{$campo}.numeric" => "{$nome} deve ser um número.",
                "{$campo}.regex" => "{$nome} deve ter até 2 casas decimais.",
                "{$campo}.gt" => "{$nome} deve ser maior que zero.",
                "{$campo}.min" => "{$nome} está abaixo do mínimo permitido.",
                "{$campo}.max" => "{$nome} está acima do máximo permitido.",
                "{$campo}.between" => "{$nome} deve estar entre 0 e 100.",
                "{$campo}.string" => "{$nome} deve ser um texto.",
                "{$campo}.date" => "{$nome} deve ser uma data válida.",
                "{$campo}.enum" => "{$nome} deve ser Hoje, Amanhã ou Depois de amanhã.",
            ];
        }

        return [
            ...$mensagens,
            'quantidade_minima_opcoes.lte' => 'A quantidade mínima de opções não pode ser maior que a máxima.',
            'valor_minimo_aposta.lte' => 'O valor mínimo por aposta não pode ser maior que o máximo.',
        ];
    }

    /**
     * @return list<mixed>
     */
    private function regra_aposta(string $campo): array
    {
        $percentual = ['numeric', 'between:0,100', $this->valor_duas_casas];

        if (in_array($campo, [...UsuariosConfiguracoes::COMISSOES_PRE_JOGO, ...UsuariosConfiguracoes::COMISSOES_AO_VIVO], true)) {
            return $percentual;
        }

        return match ($campo) {
            'realizar_aposta', 'cancelar_aposta', 'apostar_jogadores' => ['boolean'],
            'tempo_cancelamento_aposta' => ['integer', 'min:0', 'max:1440'],
            'periodo_jogos' => [Rule::enum(PeriodoJogos::class)],
            'data_travamento_sistema' => ['nullable', 'date'],
            'mensagem_bilhete' => ['string', 'max:500'],
            'delay_ao_vivo' => ['integer', 'min:0', 'max:300'],
            'quantidade_minima_opcoes' => ['integer', 'min:1', $this->has('quantidade_maxima_opcoes') ? 'lte:quantidade_maxima_opcoes' : null],
            'quantidade_maxima_opcoes' => ['integer', 'min:1'],
            'valor_minimo_aposta' => ['numeric', 'gt:0', $this->valor_duas_casas, $this->has('valor_maximo_aposta') ? 'lte:valor_maximo_aposta' : null],
            'valor_maximo_aposta', 'premio_maximo' => ['numeric', 'gt:0', $this->valor_duas_casas],
            'odd_minima' => ['numeric', 'min:1', $this->valor_duas_casas],
            'multiplicador' => ['integer', 'min:1'],
            'ganho_multiplo_palpites', 'comissao_por_premio' => $percentual,
            'limite_simples', 'limite_duplo', 'limite_geral' => ['numeric', 'min:0', $this->valor_duas_casas],
            'horas_validade_codigo' => ['integer', 'min:1', 'max:720'],
        };
    }
}
