<?php

namespace App\Http\Requests;

use App\Enums\FormaPagamento;
use App\Enums\ResultadoAposta;
use App\Support\CodigoAposta;
use App\Support\FusoSistema;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Filtros da lista de apostas do painel (spec 018, contracts/api.md "Filtros comuns"): período em
 * dias de Brasília (até 366 dias, sem data futura), aba, busca por código ou nome do cliente,
 * resultado, vendedor, cliente (spec 019), forma de pagamento, prêmio mínimo e paginação.
 */
class ApostasFiltrosRequest extends FormRequest
{
    private const DIAS_MAXIMOS = 366;

    public const ABAS = ['todas', 'pre_jogo', 'ao_vivo', 'canceladas'];

    public function authorize(): bool
    {
        // a permissão é checada pelo middleware do controller e o alcance pelo ListagemApostas
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'de' => ['required', 'date_format:Y-m-d'],
            'ate' => ['required', 'date_format:Y-m-d', 'after_or_equal:de'],
            'aba' => ['nullable', Rule::in(self::ABAS)],
            'busca' => ['nullable', 'string', 'max:60'],
            'todo_periodo' => ['nullable', 'boolean'],
            'resultado' => ['nullable', Rule::in(array_map(fn (ResultadoAposta $resultado) => $resultado->value, ResultadoAposta::cases()))],
            'usuarios_id' => ['nullable', 'integer'],
            // cliente do site (spec 019, FR-016): atalho "Ver apostas" da tela Clientes
            'clientes_id' => ['nullable', 'integer'],
            'forma_pagamento' => ['nullable', Rule::enum(FormaPagamento::class)],
            'premio_minimo' => ['nullable', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999'],
            'page' => ['nullable', 'integer', 'min:1'],
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [function (Validator $validador) {
            if ($validador->errors()->isNotEmpty()) {
                return;
            }

            $de = CarbonImmutable::parse($this->input('de'));
            $ate = CarbonImmutable::parse($this->input('ate'));
            $hoje = CarbonImmutable::now(FusoSistema::FUSO)->startOfDay();

            if ($ate->greaterThan($hoje)) {
                $validador->errors()->add('ate', 'O período não pode terminar depois de hoje.');
            } elseif ($de->diffInDays($ate) > self::DIAS_MAXIMOS) {
                $validador->errors()->add('ate', 'O período deve ter no máximo 1 ano.');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'de.required' => 'Informe a data inicial do período.',
            'ate.required' => 'Informe a data final do período.',
            '*.date_format' => 'A data informada é inválida.',
            'ate.after_or_equal' => 'A data final deve ser igual ou posterior à inicial.',
            'aba.in' => 'A aba deve ser todas, pre_jogo, ao_vivo ou canceladas.',
            'busca.*' => 'A busca deve ter no máximo 60 caracteres.',
            'todo_periodo.boolean' => 'O campo buscar em todo o período é inválido.',
            'resultado.in' => 'O resultado informado é inválido.',
            'usuarios_id.integer' => 'O vendedor informado é inválido.',
            'clientes_id.integer' => 'O cliente informado é inválido.',
            'forma_pagamento.*' => 'A forma de pagamento informada é inválida.',
            'premio_minimo.*' => 'Informe um prêmio mínimo maior que zero, com até duas casas.',
            'page.*' => 'A página informada é inválida.',
            'por_pagina.*' => 'O campo por página deve estar entre 1 e 100.',
        ];
    }

    /**
     * Início do primeiro dia e fim do último dia do período em Brasília, em UTC.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function intervalo(): array
    {
        $inicio = Carbon::createFromFormat('Y-m-d', $this->validated('de'), FusoSistema::FUSO)->startOfDay()->utc();
        $fim = Carbon::createFromFormat('Y-m-d', $this->validated('ate'), FusoSistema::FUSO)->endOfDay()->utc();

        return [$inicio, $fim];
    }

    public function aba(): string
    {
        return $this->validated('aba') ?? 'todas';
    }

    /**
     * A busca, quando ela pode ser um código completo de aposta (8 letras ou números, já
     * normalizada); senão null. Não se limita ao alfabeto atual dos códigos porque há apostas com
     * códigos de antes dele; a busca por nome continua valendo junto (ListagemApostas).
     */
    public function codigo_buscado(): ?string
    {
        $codigo = CodigoAposta::normalizar((string) $this->validated('busca'));

        return preg_match('/^[A-Z0-9]{'.CodigoAposta::TAMANHO.'}$/', $codigo) === 1 ? $codigo : null;
    }

    public function por_pagina(): int
    {
        return (int) ($this->validated('por_pagina') ?? 20);
    }

    /**
     * Filtros no formato que o ListagemApostas recebe.
     *
     * @return array{inicio: Carbon, fim: Carbon, de: string, ate: string, aba: string, codigo: ?string, nome: ?string, todo_periodo: bool, resultado: ?string, usuarios_id: ?int, clientes_id: ?int, forma_pagamento: ?string, premio_minimo: ?string}
     */
    public function filtros(): array
    {
        [$inicio, $fim] = $this->intervalo();
        $codigo = $this->codigo_buscado();
        $busca = trim((string) $this->validated('busca'));

        return [
            'inicio' => $inicio,
            'fim' => $fim,
            'de' => $this->validated('de'),
            'ate' => $this->validated('ate'),
            'aba' => $this->aba(),
            'codigo' => $codigo,
            'nome' => $busca !== '' ? $busca : null,
            'todo_periodo' => $this->boolean('todo_periodo'),
            'resultado' => $this->validated('resultado'),
            'usuarios_id' => $this->filled('usuarios_id') ? (int) $this->validated('usuarios_id') : null,
            'clientes_id' => $this->filled('clientes_id') ? (int) $this->validated('clientes_id') : null,
            'forma_pagamento' => $this->validated('forma_pagamento'),
            'premio_minimo' => $this->filled('premio_minimo') ? bcadd((string) $this->validated('premio_minimo'), '0', 2) : null,
        ];
    }
}
