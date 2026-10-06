<?php

namespace App\Http\Requests;

use App\Models\Campeonatos;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Cadastro e edição de campeonato manual. Não pode haver dois campeonatos manuais com o mesmo
 * nome e país.
 */
class CampeonatosRequest extends FormRequest
{
    public function authorize(): bool
    {
        // a permissão é checada pelo middleware do controller
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:150', $this->unico_entre_manuais()],
            'pais' => ['required', 'string', 'max:100'],
            'bandeira' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nome.required' => 'O nome é obrigatório.',
            'nome.string' => 'O nome deve ser um texto.',
            'nome.max' => 'O nome deve ter no máximo 150 caracteres.',
            'pais.required' => 'O país é obrigatório.',
            'pais.string' => 'O país deve ser um texto.',
            'pais.max' => 'O país deve ter no máximo 100 caracteres.',
            'bandeira.string' => 'A bandeira deve ser um texto.',
            'bandeira.max' => 'A bandeira deve ter no máximo 255 caracteres.',
        ];
    }

    private function unico_entre_manuais(): Closure
    {
        return function (string $atributo, mixed $nome, Closure $falhar) {
            $atual = $this->route('campeonato');

            $existe = Campeonatos::where('manual', true)
                ->where('nome', $nome)
                ->where('pais', $this->input('pais'))
                ->when($atual instanceof Campeonatos, fn ($c) => $c->where('id', '!=', $atual->id))
                ->exists();

            if ($existe) {
                $falhar('Já existe um campeonato manual com este nome e país.');
            }
        };
    }
}
