<?php

namespace App\Http\Requests;

use App\Enums\AceitarAlteracoes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Pedido de encerramento de aposta (spec 010, contracts/api.md seção 2). O valor é conferido pelo
 * serviço contra a oferta assinada no token.
 */
class EncerrarApostaRequest extends FormRequest
{
    private const DUAS_CASAS = '/^\d+(\.\d{1,2})?$/';

    public function authorize(): bool
    {
        // só o dono, conferido pelo serviço (a rota já exige o token do cliente)
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'modalidade' => ['required', 'in:Total,Parcial'],
            'valor' => ['required', 'numeric', 'gt:0', 'regex:'.self::DUAS_CASAS],
            'token_oferta' => ['required', 'string', 'max:4000'],
            'chave_idempotencia' => ['required', 'uuid'],
            'aceitar_alteracoes' => ['nullable', Rule::enum(AceitarAlteracoes::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'modalidade.required' => 'Informe se o encerramento é total ou parcial.',
            'modalidade.in' => 'O encerramento deve ser Total ou Parcial.',
            'valor.required' => 'Informe o valor do encerramento.',
            'valor.numeric' => 'O valor do encerramento deve ser um número com até 2 casas.',
            'valor.gt' => 'O valor do encerramento deve ser maior que zero.',
            'valor.regex' => 'O valor do encerramento deve ser um número com até 2 casas.',
            'token_oferta.required' => 'A oferta não foi informada. Atualize o bilhete e tente de novo.',
            'chave_idempotencia.required' => 'O campo chave_idempotencia é obrigatório.',
            'chave_idempotencia.uuid' => 'O campo chave_idempotencia deve ser um UUID.',
            'aceitar_alteracoes.enum' => 'A preferência de alterações deve ser Nenhuma, Somente para maior ou Qualquer.',
        ];
    }

    /**
     * @return array{modalidade: string, valor: string, token_oferta: string, chave_idempotencia: string, aceitar_alteracoes: ?string}
     */
    public function dados(): array
    {
        return [
            'modalidade' => $this->validated('modalidade'),
            'valor' => (string) $this->validated('valor'),
            'token_oferta' => $this->validated('token_oferta'),
            'chave_idempotencia' => $this->validated('chave_idempotencia'),
            'aceitar_alteracoes' => $this->validated('aceitar_alteracoes'),
        ];
    }
}
