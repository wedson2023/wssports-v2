<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Envio da logo da banca (multipart).
 */
class ConfiguracoesLogoRequest extends FormRequest
{
    private const LOGO_INVALIDA = 'Envie uma imagem png, jpg ou webp de até 1 MB.';

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
            'logo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'logo.required' => 'Envie a logo.',
            'logo.image' => self::LOGO_INVALIDA,
            'logo.mimes' => self::LOGO_INVALIDA,
            'logo.max' => self::LOGO_INVALIDA,
        ];
    }
}
