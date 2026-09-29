<?php

namespace App\Http\Controllers;

use App\Enums\Genero;
use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\RestaurarClientesRequest;
use App\Http\Requests\UpdateClientesRequest;
use App\Http\Resources\ClientesResource;
use App\Models\Clientes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Gestão de clientes no painel: listagem, consulta, edição, situação, exclusão e restauração.
 */
class ClientesController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    /**
     * Dados únicos que recebem o sufixo de exclusão.
     */
    private const DADOS_UNICOS = ['telefone', 'cpf', 'email'];

    public static function middleware(): array
    {
        return [
            self::permissao_cliente('clientes.listar', only: ['index', 'show']),
            self::permissao_cliente('clientes.editar', only: ['update', 'alterar_situacao']),
            self::permissao_cliente('clientes.excluir', only: ['destroy']),
            self::permissao_cliente('clientes.restaurar', only: ['excluidos', 'restaurar']),
        ];
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return $this->listar($request, Clientes::query());
    }

    public function excluidos(Request $request): AnonymousResourceCollection
    {
        return $this->listar($request, Clientes::onlyTrashed());
    }

    public function show(Clientes $cliente): ClientesResource
    {
        return new ClientesResource($cliente->load('configuracoes'));
    }

    /**
     * Senha só muda quando enviada; saldos e situação têm rotas próprias.
     */
    public function update(UpdateClientesRequest $request, Clientes $cliente): ClientesResource
    {
        $cliente->update($request->validated());

        return new ClientesResource($cliente->load('configuracoes'));
    }

    /**
     * Ao desativar, os tokens já emitidos deixam de valer imediatamente.
     */
    public function alterar_situacao(Request $request, Clientes $cliente): ClientesResource
    {
        $ativo = $request->validate(
            ['ativo' => ['required', 'boolean']],
            ['ativo.required' => 'Informe se o cliente fica ativo.', 'ativo.boolean' => 'O campo ativo deve ser verdadeiro ou falso.'],
        )['ativo'];

        $cliente->update(['ativo' => (bool) $ativo]);

        if (! $cliente->ativo) {
            $cliente->invalidar_tokens();
        }

        return new ClientesResource($cliente->load('configuracoes'));
    }

    /**
     * Exclusão lógica com sufixo nos dados únicos, que ficam livres para novos cadastros.
     */
    public function destroy(Clientes $cliente): Response
    {
        $sufixo = '_deleted_'.now()->timestamp;

        DB::transaction(function () use ($cliente, $sufixo) {
            foreach (self::DADOS_UNICOS as $campo) {
                if ($cliente->{$campo} !== null) {
                    $cliente->{$campo} .= $sufixo;
                }
            }

            $cliente->save();
            $cliente->invalidar_tokens();
            $cliente->delete();
        });

        return response()->noContent();
    }

    /**
     * Devolve os dados únicos originais; se algum já estiver em uso por outro cliente, a
     * restauração só é concluída com um valor novo informado no corpo.
     */
    public function restaurar(RestaurarClientesRequest $request, Clientes $cliente): ClientesResource|JsonResponse
    {
        if (! $cliente->trashed()) {
            return response()->json(['message' => 'Cliente não está excluído.'], 422);
        }

        $dados = ['ddi' => $request->validated('ddi', $cliente->ddi)];

        foreach (self::DADOS_UNICOS as $campo) {
            $original = $cliente->{$campo} === null ? null : preg_replace(ClientesResource::SUFIXO_EXCLUSAO, '', $cliente->{$campo});
            $dados[$campo] = $request->has($campo) ? $request->validated($campo) : $original;
        }

        $conflitos = $this->conflitos_de_dados_unicos($dados, $cliente->id);

        if ($conflitos !== []) {
            return response()->json([
                'message' => 'Há dados já em uso por outro cliente.',
                'errors' => array_fill_keys($conflitos, ['Já está em uso por outro cliente; informe um novo valor.']),
            ], 422);
        }

        DB::transaction(function () use ($cliente, $dados) {
            $cliente->forceFill($dados);
            $cliente->restore();
        });

        return new ClientesResource($cliente->load('configuracoes'));
    }

    /**
     * @param  array{ddi: string, telefone: ?string, cpf: ?string, email: ?string}  $dados
     * @return list<string> campos em conflito
     */
    private function conflitos_de_dados_unicos(array $dados, int $ignorar_id): array
    {
        $em_uso = fn (string $campo, callable $filtro) => $dados[$campo] !== null
            && Clientes::where('id', '!=', $ignorar_id)->where($filtro)->exists();

        return array_values(array_filter([
            $em_uso('telefone', fn (Builder $c) => $c->where('ddi', $dados['ddi'])->where('telefone', $dados['telefone'])) ? 'telefone' : null,
            $em_uso('cpf', fn (Builder $c) => $c->where('cpf', $dados['cpf'])) ? 'cpf' : null,
            $em_uso('email', fn (Builder $c) => $c->where('email', $dados['email'])) ? 'email' : null,
        ]));
    }

    /**
     * Listagem com busca, filtros, ordenação e paginação (máximo 100), usada também pelos excluídos.
     */
    private function listar(Request $request, Builder $consulta): AnonymousResourceCollection
    {
        // filtros enviados vazios (ex.: ?busca=) chegam como null e são ignorados
        $filtros = $request->validate([
            'busca' => ['nullable', 'string', 'max:150'],
            'ativo' => ['nullable', 'boolean'],
            'ddi' => ['nullable', 'digits_between:1,3'],
            'codigo_afiliado' => ['nullable', 'string', 'max:50'],
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
            'ordenar_por' => ['nullable', Rule::in(['nome', 'created_at', 'saldo'])],
            'direcao' => ['nullable', Rule::in(['asc', 'desc'])],
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ], [
            '*.string' => 'O filtro :attribute deve ser um texto.',
            '*.max' => 'O filtro :attribute é longo demais.',
            '*.min' => 'O filtro :attribute não pode ser negativo.',
            'ddi.digits_between' => 'O DDI deve ter de 1 a 3 dígitos.',
            '*.boolean' => 'O filtro :attribute deve ser verdadeiro ou falso.',
            '*.date_format' => 'O filtro :attribute deve estar no formato AAAA-MM-DD.',
            '*.integer' => 'O filtro :attribute deve ser um número inteiro.',
            '*.numeric' => 'O filtro :attribute deve ser um número.',
            'genero.enum' => 'O gênero informado é inválido.',
            'ordenar_por.in' => 'Ordene por nome, created_at ou saldo.',
            'direcao.in' => 'A direção deve ser asc ou desc.',
            'por_pagina.between' => 'O campo por página deve estar entre 1 e 100.',
        ]);

        $busca_completa = ! ClientesResource::deve_mascarar($request);

        $clientes = $consulta
            ->with('configuracoes')
            ->when(isset($filtros['busca']), fn (Builder $c) => $this->aplicar_busca($c, $filtros['busca'], $busca_completa))
            ->when(isset($filtros['ativo']), fn (Builder $c) => $c->where('ativo', (bool) $filtros['ativo']))
            ->when(isset($filtros['ddi']), fn (Builder $c) => $c->where('ddi', $filtros['ddi']))
            ->when(isset($filtros['codigo_afiliado']), fn (Builder $c) => $c->where('codigo_afiliado', $filtros['codigo_afiliado']))
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
            ->orderBy($filtros['ordenar_por'] ?? 'nome', $filtros['direcao'] ?? 'asc')
            ->orderBy('id')
            ->paginate($filtros['por_pagina'] ?? 20)
            ->withQueryString();

        return ClientesResource::collection($clientes);
    }

    /**
     * Nome por trecho; telefone, CPF e e-mail por prefixo para quem vê os dados completos
     * e só pelo valor completo para os demais (casa também com o valor antes do sufixo de exclusão).
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
}
