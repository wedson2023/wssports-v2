<?php

namespace App\Http\Requests;

use App\Enums\EfeitoAcaoCassino;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Tipo de ação do lançamento de rodada do provedor do cassino (spec 011, FR-022, R-16).
 */
class CassinosAcoesRequest extends FormRequest
{
    public function authorize(): bool
    {
        // a permissão é checada pelo middleware do controller
        return true;
    }

    /**
     * A ação é comparada em maiúsculas com o valor recebido do provedor.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('acao'))) {
            $this->merge(['acao' => mb_strtoupper(trim($this->input('acao')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $cadastro = $this->isMethod('post');
        $acao = $this->route('acao');

        return [
            'acao' => [$cadastro ? 'required' : 'sometimes', 'string', 'max:40', 'regex:/^[A-Z0-9_]+$/', Rule::unique('cassinos_acoes', 'acao')->ignore($acao?->id)->whereNull('deleted_at')],
            'efeito' => [$cadastro ? 'required' : 'sometimes', Rule::enum(EfeitoAcaoCassino::class)],
            'ativo' => ['sometimes', 'boolean'],
            'descricao' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'acao.required' => 'Informe a ação enviada pelo provedor.',
            'acao.regex' => 'A ação deve ter só letras, números e _.',
            'acao.max' => 'A ação deve ter até 40 caracteres.',
            'acao.unique' => 'Esta ação já está cadastrada.',
            'efeito.required' => 'Informe o efeito no saldo.',
            'efeito.*' => 'O efeito deve ser Aposta, Prêmio, Aposta com prêmio, Rodada grátis, Cancelamento ou Reembolso.',
            'ativo.boolean' => 'O campo ativo deve ser verdadeiro ou falso.',
            'descricao.max' => 'A descrição deve ter até 255 caracteres.',
        ];
    }
}
