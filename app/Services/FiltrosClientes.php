<?php

namespace App\Services;

use App\Enums\Carteira;
use App\Enums\Genero;
use App\Enums\MeioDeposito;
use App\Enums\OrigemTransacao;
use App\Enums\SituacaoAposta;
use App\Enums\SituacaoDeposito;
use App\Enums\SituacaoSaque;
use App\Enums\TipoTransacao;
use App\Http\Resources\ClientesResource;
use App\Models\Usuarios;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Consulta dos clientes do painel (spec 019, research R-05), a mesma para a lista, a Lixeira, a
 * planilha e o público das notificações: alcance pela rede, busca agrupada (nunca escapa dos demais
 * filtros, como acontecia no antigo), filtros da spec 002 e os do antigo (depósito, Pix em aberto,
 * modalidade e aniversariantes no período) e o saldo líquido. Tudo pelo query builder, com
 * parâmetros (o antigo montava SQL com os valores do pedido).
 */
class FiltrosClientes
{
    /**
     * Período máximo dos filtros de modalidade e aniversariantes, em dias.
     */
    private const DIAS_MAXIMOS_PERIODO = 366;

    public function __construct(private readonly AlcanceClientes $alcance) {}

    /**
     * Valida os filtros da requisição; filtros enviados vazios (ex.: ?busca=) chegam como null e são
     * ignorados.
     *
     * @return array<string, mixed>
     */
    public function validar(Request $request): array
    {
        $filtros = $request->validate(self::regras(), self::mensagens());

        $usa_periodo = isset($filtros['modalidade']) || (bool) ($filtros['aniversariantes'] ?? false);

        if ($usa_periodo && ! isset($filtros['periodo_de'], $filtros['periodo_ate'])) {
            throw ValidationException::withMessages(['periodo_de' => 'Informe o período do filtro.']);
        }

        if (isset($filtros['periodo_de'], $filtros['periodo_ate'])
            && Carbon::parse($filtros['periodo_de'])->diffInDays(Carbon::parse($filtros['periodo_ate'])) >= self::DIAS_MAXIMOS_PERIODO) {
            throw ValidationException::withMessages(['periodo_ate' => 'O período do filtro pode ter no máximo 366 dias.']);
        }

        return $filtros;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function regras(): array
    {
        return [
            'busca' => ['nullable', 'string', 'max:150'],
            'ativo' => ['nullable', 'boolean'],
            'ddi' => ['nullable', 'digits_between:1,3'],
            'afiliados_id' => ['nullable', 'integer'],
            'cadastro_de' => ['nullable', 'date_format:Y-m-d'],
            'cadastro_ate' => ['nullable', 'date_format:Y-m-d'],
            'idade_minima' => ['nullable', 'integer', 'min:0'],
            'idade_maxima' => ['nullable', 'integer', 'min:0'],
            'genero' => ['nullable', Rule::enum(Genero::class)],
            'saldo_minimo' => ['nullable', 'numeric'],
            'saldo_maximo' => ['nullable', 'numeric'],
            'com_saldo_promocional' => ['nullable', 'boolean'],
            'saque_bloqueado' => ['nullable', 'boolean'],
            'com_cpf' => ['nullable', 'boolean'],
            'com_email' => ['nullable', 'boolean'],
            // filtros do modal antigo (spec 019, FR-008)
            'deposito' => ['nullable', Rule::in(['ja_depositou', 'nunca_depositou'])],
            'pix_em_aberto' => ['nullable', 'boolean'],
            'modalidade' => ['nullable', Rule::in(['esportes', 'cassino'])],
            'aniversariantes' => ['nullable', 'boolean'],
            'periodo_de' => ['nullable', 'date_format:Y-m-d'],
            'periodo_ate' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:periodo_de'],
            'ordenar_por' => ['nullable', Rule::in(['nome', 'created_at', 'saldo', 'id', 'deleted_at'])],
            'direcao' => ['nullable', Rule::in(['asc', 'desc'])],
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function mensagens(): array
    {
        return [
            '*.string' => 'O filtro :attribute deve ser um texto.',
            '*.max' => 'O filtro :attribute é longo demais.',
            '*.min' => 'O filtro :attribute não pode ser negativo.',
            'ddi.digits_between' => 'O DDI deve ter de 1 a 3 dígitos.',
            '*.boolean' => 'O filtro :attribute deve ser verdadeiro ou falso.',
            '*.date_format' => 'O filtro :attribute deve estar no formato AAAA-MM-DD.',
            '*.integer' => 'O filtro :attribute deve ser um número inteiro.',
            '*.numeric' => 'O filtro :attribute deve ser um número.',
            'genero.enum' => 'O gênero informado é inválido.',
            'deposito.in' => 'Escolha Já depositou ou Nunca depositou.',
            'modalidade.in' => 'Escolha Esportes ou Cassino.',
            'periodo_ate.after_or_equal' => 'O fim do período não pode ser antes do início.',
            'ordenar_por.in' => 'Ordene por nome, created_at, saldo, id ou deleted_at.',
            'direcao.in' => 'A direção deve ser asc ou desc.',
            'por_pagina.between' => 'O campo por página deve estar entre 1 e 100.',
        ];
    }

    /**
     * Consulta dos clientes da rede com os filtros, o saldo líquido e a ordenação (sem paginar).
     *
     * @param  array<string, mixed>  $filtros
     */
    public function consulta(Usuarios $usuario, Builder $base, array $filtros, Request $request): Builder
    {
        $busca_completa = ! ClientesResource::deve_mascarar($request);
        $periodo = isset($filtros['periodo_de'], $filtros['periodo_ate'])
            ? ListagemAfiliados::periodo($filtros['periodo_de'], $filtros['periodo_ate'])
            : null;

        $consulta = $base
            ->select('clientes.*')
            ->addSelect(['saldo_liquido' => self::subconsulta_saldo_liquido()])
            ->with(['configuracoes', 'afiliado'])
            ->when(isset($filtros['busca']), fn (Builder $c) => $this->aplicar_busca($c, $filtros['busca'], $busca_completa))
            ->when(isset($filtros['ativo']), fn (Builder $c) => $c->where('ativo', (bool) $filtros['ativo']))
            ->when(isset($filtros['ddi']), fn (Builder $c) => $c->where('ddi', $filtros['ddi']))
            ->when(isset($filtros['afiliados_id']), fn (Builder $c) => $c->where('afiliados_id', (int) $filtros['afiliados_id']))
            ->when(isset($filtros['cadastro_de']), fn (Builder $c) => $c->where('created_at', '>=', $filtros['cadastro_de'].' 00:00:00'))
            ->when(isset($filtros['cadastro_ate']), fn (Builder $c) => $c->where('created_at', '<=', $filtros['cadastro_ate'].' 23:59:59'))
            ->when(isset($filtros['idade_minima']), fn (Builder $c) => $c->where('data_nascimento', '<=', now()->subYears((int) $filtros['idade_minima'])->toDateString()))
            ->when(isset($filtros['idade_maxima']), fn (Builder $c) => $c->where('data_nascimento', '>', now()->subYears((int) $filtros['idade_maxima'] + 1)->toDateString()))
            ->when(isset($filtros['genero']), fn (Builder $c) => $c->where('genero', $filtros['genero']))
            ->when(isset($filtros['saldo_minimo']), fn (Builder $c) => $c->where('saldo', '>=', $filtros['saldo_minimo']))
            ->when(isset($filtros['saldo_maximo']), fn (Builder $c) => $c->where('saldo', '<=', $filtros['saldo_maximo']))
            ->when(isset($filtros['com_saldo_promocional']), fn (Builder $c) => $this->filtrar_saldo_promocional($c, (bool) $filtros['com_saldo_promocional']))
            ->when(isset($filtros['saque_bloqueado']), fn (Builder $c) => $c->whereHas(
                'configuracoes', fn (Builder $conf) => $conf->where('bloquear_saque', (bool) $filtros['saque_bloqueado'])
            ))
            ->when(isset($filtros['com_cpf']), fn (Builder $c) => $filtros['com_cpf'] ? $c->whereNotNull('cpf') : $c->whereNull('cpf'))
            ->when(isset($filtros['com_email']), fn (Builder $c) => $filtros['com_email'] ? $c->whereNotNull('email') : $c->whereNull('email'))
            ->when(isset($filtros['deposito']), fn (Builder $c) => $filtros['deposito'] === 'ja_depositou'
                ? $c->whereExists($this->deposito_pago())
                : $c->whereNotExists($this->deposito_pago()))
            ->when((bool) ($filtros['pix_em_aberto'] ?? false), fn (Builder $c) => $this->filtrar_pix_em_aberto($c))
            ->when(isset($filtros['modalidade']) && $periodo !== null, fn (Builder $c) => $this->filtrar_modalidade($c, $filtros['modalidade'], $periodo))
            ->when((bool) ($filtros['aniversariantes'] ?? false) && isset($filtros['periodo_de']), fn (Builder $c) => $this->filtrar_aniversariantes($c, $filtros['periodo_de'], $filtros['periodo_ate']));

        $this->alcance->aplicar($consulta, $usuario);

        return $consulta
            ->orderBy($filtros['ordenar_por'] ?? 'nome', $filtros['direcao'] ?? 'asc')
            ->orderBy('clientes.id');
    }

    /**
     * Saldo líquido do cliente desde o cadastro (FR-006): depósitos pagos (no estorno parcial, só a
     * parte não estornada) + créditos manuais na carteira Saldo − saques pagos − débitos manuais na
     * carteira Saldo. Ajustes nas carteiras promocionais não entram.
     */
    public static function subconsulta_saldo_liquido(): QueryBuilder
    {
        $depositos = DB::table('depositos')
            ->selectRaw('COALESCE(SUM(valor - valor_estornado), 0)')
            ->whereColumn('depositos.clientes_id', 'clientes.id')
            ->whereIn('situacao', [SituacaoDeposito::Aprovado->value, SituacaoDeposito::Estornado->value])
            ->whereNotNull('pago_em')
            ->whereNull('depositos.deleted_at');

        $saques = DB::table('saques')
            ->selectRaw('COALESCE(SUM(valor), 0)')
            ->whereColumn('saques.clientes_id', 'clientes.id')
            ->where('situacao', SituacaoSaque::Pago->value)
            ->whereNull('saques.deleted_at');

        $ajustes = DB::table('clientes_transacoes')
            ->selectRaw('COALESCE(SUM(CASE WHEN tipo = ? THEN valor ELSE -valor END), 0)', [TipoTransacao::Crédito->value])
            ->whereColumn('clientes_transacoes.clientes_id', 'clientes.id')
            ->where('origem', OrigemTransacao::AjusteManual->value)
            ->where('carteira', Carteira::Saldo->value)
            ->whereNull('clientes_transacoes.deleted_at');

        return DB::query()->selectRaw(
            'CAST(('.$depositos->toSql().') + ('.$ajustes->toSql().') - ('.$saques->toSql().') AS DECIMAL(15,2))',
            [...$depositos->getBindings(), ...$ajustes->getBindings(), ...$saques->getBindings()],
        );
    }

    /**
     * Nome por trecho; telefone, CPF e e-mail por prefixo para quem vê os dados completos e só pelo
     * valor completo para os demais (casa também com o valor antes do sufixo de exclusão). Tudo
     * dentro de um grupo, para a busca nunca escapar dos demais filtros nem do alcance.
     */
    private function aplicar_busca(Builder $consulta, string $busca, bool $busca_completa): Builder
    {
        $digitos = preg_replace('/\D/', '', $busca);
        $email = mb_strtolower(trim($busca));

        return $consulta->where(function (Builder $c) use ($busca, $digitos, $email, $busca_completa) {
            $c->where('nome', 'like', '%'.$busca.'%');

            if ($busca_completa) {
                if ($digitos !== '') {
                    $c->orWhere('telefone', 'like', $digitos.'%')->orWhere('cpf', 'like', $digitos.'%');
                }

                $c->orWhere('email', 'like', $email.'%');

                return;
            }

            foreach (['telefone' => $digitos, 'cpf' => $digitos, 'email' => $email] as $campo => $valor) {
                if ($valor !== '') {
                    $c->orWhere($campo, $valor)->orWhere($campo, 'like', $valor.'\_deleted\_%');
                }
            }
        });
    }

    private function filtrar_saldo_promocional(Builder $consulta, bool $com_saldo): Builder
    {
        return $com_saldo
            ? $consulta->where(fn (Builder $c) => $c->where('saldo_promocao_esportes', '>', 0)->orWhere('saldo_promocao_cassino', '>', 0))
            : $consulta->where('saldo_promocao_esportes', 0)->where('saldo_promocao_cassino', 0);
    }

    /**
     * Existe depósito pago do cliente (aprovado ou estornado depois de pago).
     */
    private function deposito_pago(): Closure
    {
        return fn (QueryBuilder $q) => $q->select(DB::raw(1))
            ->from('depositos')
            ->whereColumn('depositos.clientes_id', 'clientes.id')
            ->whereIn('depositos.situacao', [SituacaoDeposito::Aprovado->value, SituacaoDeposito::Estornado->value])
            ->whereNotNull('depositos.pago_em')
            ->whereNull('depositos.deleted_at');
    }

    /**
     * O último depósito do cliente é um Pix em aberto ou que expirou sem pagamento.
     */
    private function filtrar_pix_em_aberto(Builder $consulta): Builder
    {
        return $consulta->whereExists(fn (QueryBuilder $q) => $q->select(DB::raw(1))
            ->from('depositos as ultimo')
            ->whereColumn('ultimo.clientes_id', 'clientes.id')
            ->where('ultimo.meio', MeioDeposito::Pix->value)
            ->whereIn('ultimo.situacao', [SituacaoDeposito::Pendente->value, SituacaoDeposito::Expirado->value])
            ->whereNull('ultimo.deleted_at')
            ->whereRaw('ultimo.id = (SELECT MAX(d.id) FROM depositos d WHERE d.clientes_id = clientes.id AND d.deleted_at IS NULL)'));
    }

    /**
     * Apostou em esportes (aposta efetivada) ou jogou cassino no período.
     *
     * @param  array{0: Carbon, 1: Carbon}  $periodo
     */
    private function filtrar_modalidade(Builder $consulta, string $modalidade, array $periodo): Builder
    {
        return $modalidade === 'esportes'
            ? $consulta->whereExists(fn (QueryBuilder $q) => $q->select(DB::raw(1))
                ->from('apostas')
                ->whereColumn('apostas.clientes_id', 'clientes.id')
                ->where('apostas.situacao', SituacaoAposta::Ativa->value)
                ->whereNull('apostas.deleted_at')
                ->whereBetween('apostas.created_at', $periodo))
            : $consulta->whereExists(fn (QueryBuilder $q) => $q->select(DB::raw(1))
                ->from('cassinos_rodadas')
                ->whereColumn('cassinos_rodadas.clientes_id', 'clientes.id')
                ->whereNull('cassinos_rodadas.deleted_at')
                ->whereBetween('cassinos_rodadas.created_at', $periodo));
    }

    /**
     * Dia e mês do nascimento dentro do período, inclusive quando ele atravessa o ano (corrige o
     * antigo, que comparava dia e mês separados). Período de um ano inteiro: todos com nascimento.
     */
    private function filtrar_aniversariantes(Builder $consulta, string $de, string $ate): Builder
    {
        $inicio = Carbon::parse($de);
        $fim = Carbon::parse($ate);
        $consulta->whereNotNull('data_nascimento');

        if ($inicio->diffInDays($fim) >= 365) {
            return $consulta;
        }

        $mmdd_inicio = $inicio->format('md');
        $mmdd_fim = $fim->format('md');
        $expressao = "DATE_FORMAT(data_nascimento, '%m%d')";

        return $mmdd_inicio <= $mmdd_fim
            ? $consulta->whereRaw("{$expressao} BETWEEN ? AND ?", [$mmdd_inicio, $mmdd_fim])
            : $consulta->where(fn (Builder $c) => $c->whereRaw("{$expressao} >= ?", [$mmdd_inicio])->orWhereRaw("{$expressao} <= ?", [$mmdd_fim]));
    }
}
