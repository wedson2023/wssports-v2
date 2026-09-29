<?php

namespace App\Http\Requests;

use App\Enums\CategoriaPromocao;
use App\Enums\ModalidadePromocao;
use App\Enums\TipoGanho;
use App\Models\ClientesPromocoes;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cadastro e edição de promoções. Na edição, os campos não enviados são completados com os
 * gravados, para que as regras entre campos valham para a promoção resultante.
 */
class ClientesPromocoesRequest extends FormRequest
{
    private const VALOR = 'regex:/^\d{1,13}(\.\d{1,2})?$/';

    public function authorize(): bool
    {
        // a permissão é checada pelo middleware do controller
        return true;
    }

    protected function prepareForValidation(): void
    {
        $promocao = $this->route('promocao');

        if (! $promocao instanceof ClientesPromocoes) {
            return;
        }

        $gravados = collect($promocao->only($promocao->getFillable()))
            ->map(fn (mixed $valor) => match (true) {
                $valor instanceof BackedEnum => $valor->value,
                $valor instanceof DateTimeInterface => $valor->format('Y-m-d H:i:s'),
                default => $valor,
            })
            ->reject(fn (mixed $valor, string $campo) => $this->has($campo));

        $this->merge($gravados->all());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $categoria = CategoriaPromocao::tryFrom((string) $this->input('categoria'));
        $percentual = $this->input('tipo_ganho') === TipoGanho::Percentual->value;

        return [
            'nome' => ['required', 'string', 'max:150'],
            'descricao' => ['nullable', 'string'],
            'modalidade' => ['required', Rule::enum(ModalidadePromocao::class)],
            'categoria' => ['required', Rule::enum(CategoriaPromocao::class)],
            'tipo_ganho' => [
                'required',
                Rule::enum(TipoGanho::class),
                // percentual só faz sentido quando há um depósito como base
                Rule::when($categoria !== null && ! $categoria->aceita_percentual(), [Rule::in([TipoGanho::Fixo->value])]),
            ],
            'valor' => ['required', 'numeric', self::VALOR, 'gt:0', ...($percentual ? ['max:100'] : [])],
            'rollover' => ['required', 'integer', $categoria === CategoriaPromocao::PrimeiroDepósito ? 'min:1' : 'min:0'],
            'valor_minimo_aposta' => ['required', 'numeric', self::VALOR, 'gt:0', 'lte:valor_maximo_aposta'],
            'valor_maximo_aposta' => ['required', 'numeric', self::VALOR, 'gt:0'],
            'valor_maximo_deposito' => [$percentual ? 'required' : 'nullable', 'numeric', self::VALOR, 'gt:0'],
            'valor_maximo_conversao' => ['required', 'numeric', self::VALOR, 'gt:0'],
            'odd_minima_aposta_simples' => ['required', 'numeric', 'min:1'],
            'odd_minima_aposta_multipla' => ['required', 'numeric', 'min:1'],
            'data_inicio' => ['required', 'date'],
            'data_fim' => ['nullable', 'date', 'after_or_equal:data_inicio'],
            'ativa' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            '*.required' => 'O campo :attribute é obrigatório.',
            '*.numeric' => 'O campo :attribute deve ser um número.',
            '*.regex' => 'O campo :attribute deve ter até 2 casas decimais.',
            '*.gt' => 'O campo :attribute deve ser maior que zero.',
            '*.date' => 'O campo :attribute deve ser uma data válida.',
            'nome.max' => 'O nome deve ter no máximo 150 caracteres.',
            'modalidade.enum' => 'A modalidade deve ser Esportes ou Cassino.',
            'categoria.enum' => 'A categoria informada é inválida.',
            'tipo_ganho.enum' => 'O tipo de ganho deve ser Fixo ou Percentual.',
            'tipo_ganho.in' => 'O ganho percentual só é aceito nas categorias de depósito.',
            'valor.max' => 'O percentual deve ser de no máximo 100.',
            'rollover.integer' => 'O rollover deve ser um número inteiro.',
            'rollover.min' => 'O rollover deve ser pelo menos 1 nas promoções de primeiro depósito e não pode ser negativo.',
            'valor_minimo_aposta.lte' => 'O valor mínimo de aposta não pode ser maior que o máximo.',
            'valor_maximo_deposito.required' => 'O valor máximo de depósito é obrigatório no ganho percentual.',
            '*.min' => 'O campo :attribute deve ser pelo menos 1,00.',
            'data_fim.after_or_equal' => 'A data de fim deve ser igual ou posterior à data de início.',
            'ativa.boolean' => 'O campo ativa deve ser verdadeiro ou falso.',
        ];
    }
}
