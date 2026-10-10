<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Filtros da listagem pública de jogos, com limite de requisições por IP.
 */
class ListagemPublicaRequest extends FormRequest
{
    /**
     * Máximo de requisições por IP dentro da janela.
     */
    private const MAXIMO_REQUISICOES = 120;

    /**
     * Janela, em segundos, em que as requisições são contadas.
     */
    private const JANELA_SEGUNDOS = 60;

    public function authorize(): bool
    {
        // rota pública: qualquer um pode listar
        return true;
    }

    /**
     * Conta a requisição e interrompe com 429 quando o limite foi atingido.
     */
    protected function prepareForValidation(): void
    {
        $chave = 'listagem_publica|'.$this->ip();

        if (RateLimiter::tooManyAttempts($chave, self::MAXIMO_REQUISICOES)) {
            $segundos = RateLimiter::availableIn($chave);

            throw new HttpResponseException(response()->json([
                'message' => "Muitas requisições. Tente novamente em {$segundos} segundos.",
            ], 429));
        }

        RateLimiter::hit($chave, self::JANELA_SEGUNDOS);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tipo' => ['nullable', 'in:pre_jogo,ao_vivo'],
            'dia' => ['nullable', 'in:hoje,amanha,depois_de_amanha'],
            'busca' => ['nullable', 'string', 'max:100'],
            'esporte' => ['nullable', 'string', 'max:50'],
            'somente_favoritos' => ['nullable', 'boolean'],
            'campeonato' => ['nullable', 'integer', 'min:1'],
            'fuso_horario' => ['nullable', 'regex:/^[+-]\d{2}:[0-5]\d$/', $this->fuso_no_intervalo()],
            'pagina' => ['nullable', 'integer', 'min:1'],
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipo.in' => 'O tipo deve ser pre_jogo ou ao_vivo.',
            'dia.in' => 'O dia deve ser hoje, amanha ou depois_de_amanha.',
            'busca.string' => 'A busca deve ser um texto.',
            'busca.max' => 'A busca deve ter no máximo 100 caracteres.',
            'esporte.string' => 'O esporte deve ser um texto.',
            'esporte.max' => 'O esporte deve ter no máximo 50 caracteres.',
            'somente_favoritos.boolean' => 'O campo somente favoritos deve ser verdadeiro ou falso.',
            'campeonato.integer' => 'O campeonato deve ser um número inteiro.',
            'campeonato.min' => 'O campeonato deve ser um número inteiro.',
            'fuso_horario.regex' => 'O fuso horário deve estar no formato ±HH:MM (ex.: -03:00).',
            'pagina.integer' => 'A página deve ser um número inteiro.',
            'pagina.min' => 'A página deve ser pelo menos 1.',
            'por_pagina.integer' => 'O campo por página deve ser um número inteiro.',
            'por_pagina.between' => 'O campo por página deve estar entre 1 e 100.',
        ];
    }

    /**
     * Fusos válidos vão de -12:00 a +14:00.
     */
    private function fuso_no_intervalo(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $falhar) {
            if (! is_string($valor) || ! preg_match('/^([+-])(\d{2}):(\d{2})$/', $valor, $partes)) {
                return;
            }

            $minutos = ((int) $partes[2] * 60 + (int) $partes[3]) * ($partes[1] === '-' ? -1 : 1);

            if ($minutos < -12 * 60 || $minutos > 14 * 60) {
                $falhar('O fuso horário deve estar entre -12:00 e +14:00.');
            }
        };
    }
}
