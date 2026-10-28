<?php

namespace App\Http\Requests\Concerns;

use App\Models\Especiais;
use Illuminate\Validation\Validator;

/**
 * Regras das vencedoras ao encerrar e ao corrigir o resultado de uma categoria especial (spec 024,
 * R-07, contracts/api §1.4). Com Q = quantidade de vencedoras, M = marcadas e E = empatadas: M = Q
 * sem divisão; M > Q só com "Dividir por empate", com as não empatadas (M − E) menos que Q e as
 * empatadas mais que as vagas que sobram para elas (V = Q − (M − E)); nunca todas as não anuladas.
 */
trait VencedorasEspecial
{
    /**
     * @return array<string, mixed>
     */
    private function regras_vencedoras(): array
    {
        return [
            'vencedoras' => ['required', 'array', 'min:1'],
            'vencedoras.*' => ['integer', 'distinct'],
            'empatadas' => ['nullable', 'array'],
            'empatadas.*' => ['integer', 'distinct'],
            'dividir_empate' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function mensagens_vencedoras(): array
    {
        return [
            'vencedoras.required' => 'Escolha as opções vencedoras.',
            'vencedoras.array' => 'As vencedoras devem ser uma lista.',
            'vencedoras.min' => 'Escolha as opções vencedoras.',
            'vencedoras.*.integer' => 'A opção vencedora é inválida.',
            'vencedoras.*.distinct' => 'Há opções vencedoras repetidas.',
            'empatadas.array' => 'As empatadas devem ser uma lista.',
            'empatadas.*.integer' => 'A opção empatada é inválida.',
            'empatadas.*.distinct' => 'Há opções empatadas repetidas.',
            'dividir_empate.boolean' => 'O campo dividir por empate deve ser verdadeiro ou falso.',
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [function (Validator $validador) {
            if ($validador->errors()->isNotEmpty()) {
                return;
            }

            $this->conferir_vencedoras($validador);
        }];
    }

    /**
     * Resultado validado no formato do EncerramentoEspeciais: sem divisão, sem empatadas.
     *
     * @return array{vencedoras: list<int>, empatadas: list<int>, dividir_empate: bool}
     */
    public function resultado(): array
    {
        $dividir = $this->boolean('dividir_empate');

        return [
            'vencedoras' => array_map('intval', $this->validated('vencedoras')),
            'empatadas' => $dividir ? array_map('intval', $this->validated('empatadas') ?? []) : [],
            'dividir_empate' => $dividir,
        ];
    }

    private function conferir_vencedoras(Validator $validador): void
    {
        /** @var Especiais $especial */
        $especial = $this->route('especial');
        $nao_anuladas = $especial->opcoes()->whereNull('anulada_em')->pluck('id')->all();
        $vencedoras = array_map('intval', $this->input('vencedoras'));
        $quantidade = $especial->quantidade_vencedoras;
        $dividir = $this->boolean('dividir_empate');
        $marcadas = count($vencedoras);

        foreach ($vencedoras as $indice => $id) {
            if (! in_array($id, $nao_anuladas, true)) {
                $validador->errors()->add("vencedoras.{$indice}", 'A opção não pertence a esta categoria ou foi anulada.');

                return;
            }
        }

        if ($marcadas >= count($nao_anuladas)) {
            $validador->errors()->add('vencedoras', 'Para todas vencerem, cancele a categoria.');

            return;
        }

        if ($marcadas < $quantidade || ($marcadas > $quantidade && ! $dividir)) {
            $validador->errors()->add('vencedoras', $quantidade === 1 ? 'Escolha 1 opção vencedora.' : "Escolha {$quantidade} opções vencedoras.");

            return;
        }

        if (! $dividir) {
            return;
        }

        if ($marcadas === $quantidade) {
            $validador->errors()->add('dividir_empate', 'A divisão por empate só vale com mais vencedoras que a quantidade da categoria.');

            return;
        }

        $empatadas = array_map('intval', $this->input('empatadas') ?? []);
        $vagas = $quantidade - ($marcadas - count($empatadas));

        if (array_diff($empatadas, $vencedoras) !== [] || $marcadas - count($empatadas) >= $quantidade || count($empatadas) <= $vagas) {
            $validador->errors()->add('empatadas', 'Marque as vencedoras empatadas na última vaga.');
        }
    }
}
