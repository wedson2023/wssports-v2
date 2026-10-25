<?php

namespace App\Http\Resources;

use App\Enums\Funcao;
use App\Services\AlcancePromocoes;
use App\Services\ListagemPromocoes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientesPromocoesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // a lista já traz ja_aplicada na consulta; sem ela (show, store, update), consulta o banco
        $ja_aplicada = $this->resource->getAttribute('ja_aplicada');
        $aplicada = $ja_aplicada === null ? $this->aplicada() : (bool) $ja_aplicada;

        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'descricao' => $this->descricao,
            'modalidade' => $this->modalidade,
            'categoria' => $this->categoria,
            'tipo_ganho' => $this->tipo_ganho,
            'valor' => $this->valor,
            'rollover' => $this->rollover,
            'valor_minimo_aposta' => $this->valor_minimo_aposta,
            'valor_maximo_aposta' => $this->valor_maximo_aposta,
            'valor_maximo_deposito' => $this->valor_maximo_deposito,
            'valor_maximo_conversao' => $this->valor_maximo_conversao,
            'odd_minima_aposta_simples' => $this->odd_minima_aposta_simples,
            'odd_minima_aposta_multipla' => $this->odd_minima_aposta_multipla,
            'data_inicio' => $this->data_inicio,
            'data_fim' => $this->data_fim,
            'ativa' => $this->ativa,
            'dias_cumprir' => $this->dias_cumprir,
            'rollover_primeiro_deposito' => $this->rollover_primeiro_deposito,
            'afiliados_id' => $this->afiliados_id,
            'afiliado' => $this->whenLoaded('afiliado', fn () => $this->afiliado === null ? null : [
                'id' => $this->afiliado->id,
                'nome' => $this->afiliado->nome,
                'codigo' => $this->afiliado->codigo,
                'excluido' => $this->afiliado->deleted_at !== null,
            ]),
            'quantidade_indicacoes' => $this->quantidade_indicacoes,
            'vigente' => $this->vigente(),
            'aplicada' => $aplicada,
            // tela Bônus (spec 020): situação calculada, números de uso e ações que o usuário pode fazer
            'situacao' => $this->resource->getAttribute('situacao') ?? ListagemPromocoes::situacao($this->resource)->value,
            'uso' => $this->when($this->resource->getAttribute('uso') !== null, fn () => $this->resource->getAttribute('uso')),
            'pode' => $this->pode($request, $aplicada),
            'estorno' => [
                'situacao' => $this->estorno_situacao,
                'motivo' => $this->estorno_motivo,
                'autor' => $this->autor_estorno ? ['id' => $this->autor_estorno->id, 'nome' => $this->autor_estorno->nome] : null,
                'iniciado_em' => $this->estorno_iniciado_em,
                'concluido_em' => $this->estorno_concluido_em,
                'total_clientes' => $this->estorno_total_clientes,
                'clientes_processados' => $this->estorno_clientes_processados,
                'valor_total' => $this->estorno_valor_total,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Ações da promoção para o usuário (spec 020, FR-010): pela permissão, pelo alcance (a geral só o
     * Admin altera), pelo estorno (estornada não muda mais) e pelo uso (aplicada não é excluída).
     *
     * @return array{editar: bool, ativar: bool, excluir: bool, estornar: bool}
     */
    private function pode(Request $request, bool $aplicada): array
    {
        $usuario = $request->user();
        $alteravel = $usuario !== null
            && ! $this->estornada()
            && app(AlcancePromocoes::class)->alteravel($usuario, $this->resource);
        $gerenciar = $alteravel && Funcao::usuario_pode($usuario, 'clientes_promocoes.gerenciar');

        return [
            'editar' => $gerenciar,
            'ativar' => $gerenciar,
            'excluir' => $gerenciar && ! $aplicada,
            'estornar' => $alteravel && Funcao::usuario_pode($usuario, 'clientes_promocoes.estornar'),
        ];
    }
}
