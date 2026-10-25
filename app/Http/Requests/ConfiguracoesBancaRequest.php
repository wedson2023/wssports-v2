<?php

namespace App\Http\Requests;

use App\Models\Configuracoes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Dados da banca editados na aba Banca da tela Configurações (spec 021, FR-005, FR-006 e FR-009):
 * nome, bilhete, WhatsApp, redes sociais, tema e GTM-ID. Cada campo é opcional no envio (a tela
 * manda só os alterados).
 */
class ConfiguracoesBancaRequest extends FormRequest
{
    private const LINKS = ['instagram', 'facebook', 'twitter', 'youtube', 'jogo_responsavel'];

    public function authorize(): bool
    {
        // a permissão é checada pelo middleware do controller
        return true;
    }

    /**
     * WhatsApp só com dígitos; GTM-ID em maiúsculas e sem espaços; fundo em maiúsculas; campos de
     * texto vazios viram null (o middleware de strings vazias já faz isso).
     */
    protected function prepareForValidation(): void
    {
        $dados = [];

        if ($this->has('whatsapp')) {
            $dados['whatsapp'] = preg_replace('/\D/', '', (string) $this->input('whatsapp')) ?: null;
        }

        if ($this->has('gtm_id')) {
            $dados['gtm_id'] = strtoupper(preg_replace('/\s+/', '', (string) $this->input('gtm_id'))) ?: null;
        }

        if ($this->has('cor_fundo')) {
            $dados['cor_fundo'] = strtoupper((string) $this->input('cor_fundo'));
        }

        if ($this->has('cor_tema')) {
            $dados['cor_tema'] = strtolower((string) $this->input('cor_tema'));
        }

        $this->merge($dados);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $links = array_fill_keys(self::LINKS, ['sometimes', 'nullable', 'url:http,https', 'max:255']);

        return [
            'nome_sistema' => ['sometimes', 'required', 'string', 'max:100'],
            'mensagem_bilhete' => ['sometimes', 'required', 'string', 'max:500'],
            'whatsapp' => ['sometimes', 'nullable', 'regex:/^\d{12,13}$/'],
            'mensagem_whatsapp' => ['sometimes', 'nullable', 'string', 'max:500'],
            'botao_whatsapp' => ['sometimes', 'boolean'],
            ...$links,
            'cor_tema' => ['sometimes', Rule::in(array_keys(Configuracoes::CORES_TEMA))],
            'cor_fundo' => ['sometimes', Rule::in(Configuracoes::CORES_FUNDO)],
            'gtm_id' => ['sometimes', 'nullable', 'regex:/^GTM-[A-Z0-9]{4,12}$/'],
        ];
    }

    /**
     * Mensagens específicas primeiro: o Laravel usa a primeira que casar.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nome_sistema.required' => 'Informe o nome do sistema.',
            'nome_sistema.max' => 'O nome do sistema deve ter no máximo 100 caracteres.',
            'mensagem_bilhete.required' => 'Informe a mensagem do bilhete.',
            'mensagem_bilhete.max' => 'A mensagem do bilhete deve ter no máximo 500 caracteres.',
            'whatsapp.regex' => 'Informe o WhatsApp com DDI e DDD, só números (ex.: 5511999999999).',
            'mensagem_whatsapp.max' => 'A mensagem inicial deve ter no máximo 500 caracteres.',
            'instagram.url' => 'O link do Instagram deve começar com http:// ou https://.',
            'facebook.url' => 'O link do Facebook deve começar com http:// ou https://.',
            'twitter.url' => 'O link do Twitter/X deve começar com http:// ou https://.',
            'youtube.url' => 'O link do YouTube deve começar com http:// ou https://.',
            'jogo_responsavel.url' => 'O link de jogo responsável deve começar com http:// ou https://.',
            'cor_tema.in' => 'Escolha uma das cores do tema.',
            'cor_fundo.in' => 'Escolha o fundo escuro ou claro.',
            'gtm_id.regex' => 'O GTM-ID deve estar no formato GTM-XXXXXXX.',
            '*.max' => 'O link deve ter no máximo 255 caracteres.',
            '*.boolean' => 'O campo :attribute deve ser verdadeiro ou falso.',
            '*.string' => 'O campo :attribute deve ser um texto.',
        ];
    }
}
