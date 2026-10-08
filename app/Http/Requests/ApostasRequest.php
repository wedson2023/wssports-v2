<?php

namespace App\Http\Requests;

use App\Enums\AceitarAlteracoes;
use App\Support\CodigosCotacao;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Corpo do pedido de aposta (vendedor, cliente, visitante e validação do código). Só os campos
 * daqui são lidos: prêmio, esporte, horários e demais valores são sempre do servidor (FR-005).
 */
class ApostasRequest extends FormRequest
{
    private const TAMANHO_NOME = 100;

    private const MAXIMO_PALPITES = 50;

    private const DUAS_CASAS = '/^\d+(\.\d{1,2})?$/';

    public function authorize(): bool
    {
        // a permissão é checada pelo middleware do controller
        return true;
    }

    protected function prepareForValidation(): void
    {
        // o nome vai para o bilhete: sem HTML (FR-010)
        if (is_string($this->input('nome'))) {
            $this->merge(['nome' => trim(strip_tags($this->input('nome')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'chave_idempotencia' => ['required', 'uuid'],
            'nome' => ['required', 'string', 'max:'.self::TAMANHO_NOME],
            'valor' => ['required', 'numeric', 'gt:0', 'regex:'.self::DUAS_CASAS],
            'aceitar_alteracoes' => ['nullable', Rule::enum(AceitarAlteracoes::class)],
            'palpites' => ['required', 'array', 'min:1', 'max:'.self::MAXIMO_PALPITES],
            'palpites.*' => ['required', 'array'],
            'palpites.*.confrontos_id' => ['nullable', 'integer', 'required_without:palpites.*.confrontos_ao_vivo_id', 'prohibits:palpites.*.confrontos_ao_vivo_id'],
            'palpites.*.confrontos_ao_vivo_id' => ['nullable', 'integer'],
            'palpites.*.codigo_cotacao' => ['required', 'string', $this->codigo_valido()],
            'palpites.*.confrontos_jogadores_id' => ['nullable', 'integer', 'required_if:palpites.*.codigo_cotacao,'.CodigosCotacao::JOGADOR],
            'palpites.*.cotacao_vista' => ['required', 'numeric', 'min:1', 'regex:'.self::DUAS_CASAS],
            'fuso_horario' => ['nullable', 'regex:/^[+-]\d{2}:[0-5]\d$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'chave_idempotencia.required' => 'Informe a chave de idempotência.',
            'chave_idempotencia.uuid' => 'A chave de idempotência deve ser um UUID.',
            'nome.required' => 'Informe o nome do apostador.',
            'nome.string' => 'O nome do apostador deve ser um texto.',
            'nome.max' => 'O nome do apostador deve ter no máximo '.self::TAMANHO_NOME.' caracteres.',
            'valor.required' => 'Informe o valor da aposta.',
            'valor.numeric' => 'O valor da aposta deve ser um número.',
            'valor.gt' => 'O valor da aposta deve ser maior que zero.',
            'valor.regex' => 'O valor da aposta deve ter até 2 casas decimais.',
            'aceitar_alteracoes.enum' => 'A preferência de alterações deve ser Nenhuma, Somente para maior ou Qualquer.',
            'palpites.required' => 'Escolha os jogos antes de concluir a aposta.',
            'palpites.array' => 'Os palpites devem ser uma lista.',
            'palpites.min' => 'Escolha os jogos antes de concluir a aposta.',
            'palpites.max' => 'A aposta pode ter no máximo '.self::MAXIMO_PALPITES.' palpites.',
            'palpites.*.required' => 'Palpite inválido.',
            'palpites.*.array' => 'Palpite inválido.',
            'palpites.*.confrontos_id.integer' => 'O confronto do palpite deve ser um número inteiro.',
            'palpites.*.confrontos_id.required_without' => 'Informe o confronto do pré-jogo ou do ao vivo em cada palpite.',
            'palpites.*.confrontos_id.prohibits' => 'Informe só um confronto por palpite: do pré-jogo ou do ao vivo.',
            'palpites.*.confrontos_ao_vivo_id.integer' => 'O jogo do ao vivo do palpite deve ser um número inteiro.',
            'palpites.*.codigo_cotacao.required' => 'Informe o código da cotação em cada palpite.',
            'palpites.*.codigo_cotacao.string' => 'O código da cotação deve ser um texto.',
            'palpites.*.confrontos_jogadores_id.integer' => 'O jogador do palpite deve ser um número inteiro.',
            'palpites.*.confrontos_jogadores_id.required_if' => 'Informe o jogador no palpite em jogador.',
            'palpites.*.cotacao_vista.required' => 'Informe a cotação vista em cada palpite.',
            'palpites.*.cotacao_vista.numeric' => 'A cotação vista deve ser um número.',
            'palpites.*.cotacao_vista.min' => 'A cotação vista deve ser maior ou igual a 1,00.',
            'palpites.*.cotacao_vista.regex' => 'A cotação vista deve ter até 2 casas decimais.',
            'fuso_horario.regex' => 'O fuso horário deve estar no formato +HH:MM ou -HH:MM.',
        ];
    }

    /**
     * Dados validados no formato usado pelos serviços de aposta.
     *
     * @return array<string, mixed>
     */
    public function dados(): array
    {
        $dados = $this->validated();

        $dados['aceitar_alteracoes'] ??= AceitarAlteracoes::Nenhuma->value;
        $dados['valor'] = (string) $dados['valor'];
        $dados['palpites'] = array_values(array_map(fn (array $palpite) => [
            ...$palpite,
            'cotacao_vista' => (string) $palpite['cotacao_vista'],
        ], $dados['palpites']));

        return $dados;
    }

    /**
     * Só os códigos da lista fechada (odd1 a odd323 e jogador), nunca usados em SQL (FR-006).
     */
    private function codigo_valido(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $falha) {
            if (! is_string($valor) || ! CodigosCotacao::e_regra($valor)) {
                $falha('Código de cotação inválido.');
            }
        };
    }
}
