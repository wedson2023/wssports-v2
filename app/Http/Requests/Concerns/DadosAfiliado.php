<?php

namespace App\Http\Requests\Concerns;

use App\Enums\Funcao;
use App\Enums\TipoChavePix;
use App\Enums\TipoGanhoAfiliado;
use App\Models\Afiliados;
use App\Models\Usuarios;
use App\Rules\CpfValido;
use Closure;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Normalização e regras dos dados do afiliado, comuns ao cadastro pelo painel, ao cadastro público,
 * à edição e aos meus dados (spec 012, data-model §1 e §5).
 */
trait DadosAfiliado
{
    use ChavePix;

    /**
     * UFs aceitas no endereço do afiliado, mais EX (estrangeiro), como no antigo.
     */
    protected const ESTADOS = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO', 'EX'];

    /**
     * Login com trim; CPF, telefone e DDI só com dígitos; e-mail em minúsculas; estado em
     * maiúsculas; chave Pix conforme o tipo; vazios viram nulo.
     */
    protected function normalizar_dados_afiliado(): void
    {
        $normalizados = [];

        foreach (['ddi', 'telefone', 'cpf'] as $campo) {
            if ($this->has($campo) && $this->input($campo) !== null) {
                $normalizados[$campo] = preg_replace('/\D/', '', (string) $this->input($campo));
            }
        }

        if ($this->has('login') && is_string($this->input('login'))) {
            $normalizados['login'] = trim($this->input('login'));
        }

        if ($this->has('email') && $this->input('email') !== null) {
            $normalizados['email'] = mb_strtolower(trim((string) $this->input('email')));
        }

        if ($this->has('estado') && $this->input('estado') !== null) {
            $normalizados['estado'] = mb_strtoupper(trim((string) $this->input('estado')));
        }

        if ($this->filled('pix_chave')) {
            $normalizados['pix_chave'] = $this->normalizar_chave_pix((string) $this->input('pix_chave'));
        }

        foreach (['cpf', 'email', 'telefone', 'cidade', 'estado', 'endereco'] as $campo) {
            $valor = $normalizados[$campo] ?? $this->input($campo);

            if ($this->has($campo) && is_string($valor) && trim($valor) === '') {
                $normalizados[$campo] = null;
            }
        }

        $this->merge($normalizados);
    }

    /**
     * Regras dos dados pessoais e do Pix. $presenca é "required" no cadastro e "sometimes" na edição.
     *
     * @return array<string, list<mixed>>
     */
    protected function regras_dados_afiliado(string $presenca, ?int $ignorar_id = null): array
    {
        $ddi = (string) ($this->input('ddi') ?: '55');

        return [
            'nome' => [$presenca, 'string', 'max:150'],
            'email' => [$presenca, 'email', 'max:150', Rule::unique('afiliados', 'email')->ignore($ignorar_id)],
            'cpf' => [$presenca, 'string', new CpfValido, Rule::unique('afiliados', 'cpf')->ignore($ignorar_id)],
            'ddi' => ['sometimes', 'digits_between:1,3'],
            'telefone' => [$presenca, 'string', $ddi === '55' ? 'digits_between:10,11' : 'digits_between:4,14'],
            'cidade' => ['sometimes', 'nullable', 'string', 'max:100'],
            'estado' => ['sometimes', 'nullable', Rule::in(self::ESTADOS)],
            'endereco' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pix_tipo_chave' => [$presenca, Rule::enum(TipoChavePix::class)],
            'pix_chave' => [$presenca, 'string', 'max:150', $this->regra_chave_pix()],
        ];
    }

    /**
     * Login de 3 a 50 caracteres (letras, números, ponto, hífen e sublinhado), único entre
     * afiliados e usuários do painel, sem diferenciar maiúsculas e contando excluídos (FR-002).
     *
     * @return list<mixed>
     */
    protected function regras_login(): array
    {
        return ['required', 'string', 'regex:/^[A-Za-z0-9._-]{3,50}$/', $this->regra_login_unico()];
    }

    protected function regra_senha(): Password
    {
        return Password::min(8)->letters()->numbers();
    }

    /**
     * @return array<string, string>
     */
    protected function mensagens_dados_afiliado(): array
    {
        $regra_senha = 'A senha deve ter no mínimo 8 caracteres, com letras e números.';

        return [
            'nome.required' => 'O nome é obrigatório.',
            'nome.max' => 'O nome deve ter no máximo 150 caracteres.',
            'login.required' => 'O login é obrigatório.',
            'login.regex' => 'O login deve ter de 3 a 50 caracteres, só com letras, números, ponto, hífen ou sublinhado.',
            'email.required' => 'O e-mail é obrigatório.',
            'email.email' => 'O e-mail informado é inválido.',
            'email.max' => 'O e-mail deve ter no máximo 150 caracteres.',
            'email.unique' => 'O e-mail já está cadastrado.',
            'cpf.required' => 'O CPF é obrigatório.',
            'cpf.unique' => 'O CPF já está cadastrado.',
            'ddi.digits_between' => 'O DDI deve ter de 1 a 3 dígitos.',
            'telefone.required' => 'O telefone é obrigatório.',
            'telefone.digits_between' => 'O telefone informado é inválido.',
            'cidade.max' => 'A cidade deve ter no máximo 100 caracteres.',
            'estado.in' => 'O estado informado é inválido.',
            'endereco.max' => 'O endereço deve ter no máximo 255 caracteres.',
            'pix_tipo_chave.required' => 'O tipo de chave Pix é obrigatório.',
            'pix_tipo_chave.enum' => 'O tipo de chave Pix informado é inválido.',
            'pix_chave.required' => 'A chave Pix é obrigatória.',
            'pix_chave.max' => 'A chave Pix deve ter no máximo 150 caracteres.',
            'password.required' => 'A senha é obrigatória.',
            'password.confirmed' => 'A confirmação da senha não confere.',
            'password.min' => $regra_senha,
            'password.letters' => $regra_senha,
            'password.numbers' => $regra_senha,
        ];
    }

    /**
     * O responsável precisa ser um usuário com a função Gerente e, para o Supervisor, da rede dele
     * (spec 019, FR-003d); o Admin escolhe qualquer gerente.
     */
    protected function regra_gerente(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $falhar) {
            $gerente = Usuarios::find($valor);

            if ($gerente === null || $gerente->funcao() !== Funcao::Gerente) {
                $falhar('O responsável deve ser um usuário com a função Gerente.');

                return;
            }

            $solicitante = $this->user();

            if ($solicitante?->funcao() === Funcao::Supervisor && ! $solicitante->gerencia($gerente)) {
                $falhar('O gerente escolhido não é da sua rede.');
            }
        };
    }

    /**
     * Regra de comissão opcional: tipo de ganho e percentual andam juntos.
     *
     * @return array<string, list<mixed>>
     */
    protected function regras_comissao_opcional(): array
    {
        return [
            'tipo_ganho' => ['nullable', 'required_with:percentual', Rule::enum(TipoGanhoAfiliado::class)],
            'percentual' => ['nullable', 'required_with:tipo_ganho', 'numeric', 'between:0,100', 'decimal:0,2'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function mensagens_comissao(): array
    {
        return [
            'usuarios_id.integer' => 'O gerente informado é inválido.',
            'tipo_ganho.required' => 'O tipo de ganho é obrigatório.',
            'tipo_ganho.required_with' => 'Informe o tipo de ganho junto com o percentual.',
            'tipo_ganho.enum' => 'O tipo de ganho deve ser Saldo líquido, Qualquer depósito ou Primeiro depósito.',
            'percentual.required' => 'O percentual é obrigatório.',
            'percentual.required_with' => 'Informe o percentual junto com o tipo de ganho.',
            'percentual.numeric' => 'O percentual deve ser um número.',
            'percentual.between' => 'O percentual deve estar entre 0 e 100.',
            'percentual.decimal' => 'O percentual deve ter no máximo 2 casas decimais.',
        ];
    }

    private function regra_login_unico(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $falhar) {
            $login = mb_strtolower((string) $valor);

            $em_uso = Afiliados::withTrashed()->whereRaw('LOWER(login) = ?', [$login])->exists()
                || Usuarios::withTrashed()->whereRaw('LOWER(login) = ?', [$login])->exists();

            if ($em_uso) {
                $falhar('O login informado já está em uso.');
            }
        };
    }
}
