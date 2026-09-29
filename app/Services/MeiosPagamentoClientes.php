<?php

namespace App\Services;

use App\Enums\TipoMeioPagamento;
use App\Models\Clientes;
use App\Models\ClientesMeiosPagamento;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Regras dos meios de pagamento: sem duplicidade por cliente e exatamente um principal.
 */
class MeiosPagamentoClientes
{
    /**
     * @param  array<string, mixed>  $dados  dados validados
     */
    public function cadastrar(Clientes $cliente, array $dados): ClientesMeiosPagamento
    {
        return DB::transaction(function () use ($cliente, $dados) {
            $meios = $this->meios_bloqueados($cliente);

            $meio = new ClientesMeiosPagamento($this->somente_campos_do_tipo($dados));
            $meio->clientes_id = $cliente->id;
            $this->garantir_sem_duplicidade($meio, $meios);

            // o primeiro meio vira principal automaticamente
            $meio->principal = $meios->isEmpty() || (bool) ($dados['principal'] ?? false);
            $meio->save();

            if ($meio->principal) {
                $this->desmarcar_outros_principais($meio);
            }

            return $meio;
        });
    }

    /**
     * @param  array<string, mixed>  $dados  dados validados
     */
    public function atualizar(ClientesMeiosPagamento $meio, array $dados): ClientesMeiosPagamento
    {
        return DB::transaction(function () use ($meio, $dados) {
            $meios = $this->meios_bloqueados($meio->cliente);

            $meio->fill($this->somente_campos_do_tipo([...$meio->only($meio->getFillable()), ...$dados]));
            $this->garantir_sem_duplicidade($meio, $meios);

            if ($dados['principal'] ?? false) {
                $meio->principal = true;
            }

            $meio->save();

            if ($meio->principal) {
                $this->desmarcar_outros_principais($meio);
            }

            return $meio;
        });
    }

    /**
     * Exclusão lógica; se era o principal, o mais antigo restante passa a ser.
     */
    public function excluir(ClientesMeiosPagamento $meio): void
    {
        DB::transaction(function () use ($meio) {
            $this->meios_bloqueados($meio->cliente);

            $era_principal = $meio->principal;
            $meio->delete();

            if ($era_principal) {
                $meio->cliente->meios_pagamento()->oldest('id')->first()?->update(['principal' => true]);
            }
        });
    }

    /**
     * Bloqueia os meios do cliente durante a operação, evitando dois principais em pedidos simultâneos.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, ClientesMeiosPagamento>
     */
    private function meios_bloqueados(Clientes $cliente)
    {
        return $cliente->meios_pagamento()->lockForUpdate()->get();
    }

    /**
     * Mantém só os campos do tipo escolhido; os do outro tipo ficam nulos.
     *
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    private function somente_campos_do_tipo(array $dados): array
    {
        $tipo = TipoMeioPagamento::from($dados['tipo'] instanceof TipoMeioPagamento ? $dados['tipo']->value : $dados['tipo']);
        $campos_do_tipo = $tipo === TipoMeioPagamento::Pix ? ClientesMeiosPagamento::CAMPOS_PIX : ClientesMeiosPagamento::CAMPOS_TRANSFERENCIA;
        $campos_do_outro = $tipo === TipoMeioPagamento::Pix ? ClientesMeiosPagamento::CAMPOS_TRANSFERENCIA : ClientesMeiosPagamento::CAMPOS_PIX;

        return [
            'tipo' => $tipo,
            ...array_intersect_key($dados, array_flip($campos_do_tipo)),
            ...array_fill_keys($campos_do_outro, null),
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, ClientesMeiosPagamento>  $meios
     */
    private function garantir_sem_duplicidade(ClientesMeiosPagamento $meio, $meios): void
    {
        $duplicado = $meios
            ->reject(fn (ClientesMeiosPagamento $outro) => $outro->id === $meio->id)
            ->contains(fn (ClientesMeiosPagamento $outro) => $this->chave_de_unicidade($outro) === $this->chave_de_unicidade($meio));

        if ($duplicado) {
            throw ValidationException::withMessages(['tipo' => 'Este meio de pagamento já está cadastrado.']);
        }
    }

    private function chave_de_unicidade(ClientesMeiosPagamento $meio): string
    {
        return $meio->tipo === TipoMeioPagamento::Pix
            ? 'pix|'.$meio->pix_chave
            : 'banco|'.implode('|', [$meio->banco_codigo, $meio->agencia, $meio->conta, $meio->conta_digito]);
    }

    private function desmarcar_outros_principais(ClientesMeiosPagamento $meio): void
    {
        ClientesMeiosPagamento::where('clientes_id', $meio->clientes_id)
            ->where('id', '!=', $meio->id)
            ->where('principal', true)
            ->update(['principal' => false]);
    }
}
