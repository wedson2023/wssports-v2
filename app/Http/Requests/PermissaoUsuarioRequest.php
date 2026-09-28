<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PermissaoUsuarioRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // a permissão de gerenciar permissões é checada pelo middleware do controller
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'permissao' => ['required', 'string', Rule::exists('permissions', 'name')->where('guard_name', 'api')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'permissao.required' => 'A permissão é obrigatória.',
            'permissao.string' => 'A permissão deve ser um texto.',
            'permissao.exists' => 'A permissão informada não existe.',
        ];
    }
}
