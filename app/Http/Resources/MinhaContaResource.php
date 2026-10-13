<?php

namespace App\Http\Resources;

use App\Enums\Carteira;
use App\Enums\CategoriaPromocao;
use App\Enums\TipoMeioPagamento;
use App\Enums\TipoRollover;
use App\Models\Clientes;
use App\Models\ClientesPromocoes;
use App\Models\ClientesRollovers;
use App\Models\ClientesTransacoes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Cabeçalho, saldos, pendências de cadastro e itens do menu da tela de créditos (spec 007,
 * contracts/api.md §1).
 *
 * @mixin Clientes
 */
class MinhaContaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $tem_pix = $this->meios_pagamento()
            ->where('tipo', TipoMeioPagamento::Pix)
            ->where('principal', true)
            ->exists();

        return [
            'cliente' => [
                'id' => $this->id,
                'nome' => $this->nome,
                'ativo' => $this->ativo,
                'codigo_indicacao' => $this->codigo_indicacao,
            ],
            'saldos' => [
                'saldo' => [...$this->carteira(Carteira::Saldo, TipoRollover::Depósito), 'negativo' => bccomp((string) $this->saldo, '0', 2) < 0],
                'bonus_esportes' => $this->carteira(Carteira::PromoçãoEsportes, TipoRollover::Bônus),
                'bonus_cassino' => $this->carteira(Carteira::PromoçãoCassino, TipoRollover::Bônus),
            ],
            'cadastro' => [
                'falta_para_depositar' => array_keys(array_filter(['cpf' => ! $this->cpf, 'email' => ! $this->email])),
                'falta_para_sacar' => array_keys(array_filter(['email' => ! $this->email, 'cpf' => ! $this->cpf, 'pix' => ! $tem_pix])),
            ],
            'menu' => [
                'indicacao' => $this->indicacao_vigente(),
            ],
            // preenchido no acesso assistido pelo painel (US7)
            'acesso_assistido' => $this->acesso_assistido,
        ];
    }

    /**
     * @var array{usuario: ?string, expira_em: mixed}|null
     */
    private ?array $acesso_assistido = null;

    /**
     * Quem do painel está acessando a conta (tarja da tela).
     *
     * @param  array{usuario: ?string, expira_em: mixed}|null  $acesso
     */
    public function com_acesso_assistido(?array $acesso): self
    {
        $this->acesso_assistido = $acesso;

        return $this;
    }

    /**
     * Valor atual, saldo anterior (da última movimentação da carteira) e meta de rollover.
     *
     * @return array{valor: string, anterior: string, meta: string}
     */
    private function carteira(Carteira $carteira, TipoRollover $tipo): array
    {
        $valor = (string) $this->{$carteira->coluna()};
        $ultima = ClientesTransacoes::where('clientes_id', $this->id)
            ->where('carteira', $carteira)
            ->latest('id')
            ->first();

        $meta = ClientesRollovers::where('clientes_id', $this->id)
            ->where('tipo', $tipo)
            ->where('carteira', $carteira)
            ->pendentes()
            ->get()
            ->reduce(fn (string $total, ClientesRollovers $rollover) => bcadd($total, $rollover->restante(), 2), '0.00');

        return [
            'valor' => $valor,
            'anterior' => $ultima ? (string) $ultima->saldo_anterior : $valor,
            'meta' => $meta,
        ];
    }

    /**
     * Promoção de Indicação vigente para o afiliado do cliente (item "Indique um amigo").
     *
     * @return array{vigente: bool, quantidade?: int, valor?: string}
     */
    private function indicacao_vigente(): array
    {
        $promocao = ClientesPromocoes::vigentes()
            ->where('categoria', CategoriaPromocao::Indicação)
            ->paraAfiliado($this->codigo_afiliado)
            ->orderByRaw('codigo_afiliado is null')
            ->orderBy('id')
            ->first();

        if ($promocao === null) {
            return ['vigente' => false];
        }

        return ['vigente' => true, 'quantidade' => $promocao->quantidade_indicacoes ?? 5, 'valor' => (string) $promocao->valor];
    }
}
