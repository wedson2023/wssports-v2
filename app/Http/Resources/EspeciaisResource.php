<?php

namespace App\Http\Resources;

use App\Enums\Funcao;
use App\Enums\SituacaoEspecial;
use App\Models\EspeciaisOpcoes;
use App\Models\Usuarios;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Categoria especial no painel; na tela Especiais (spec 024) traz também as vencedoras, os números
 * em aberto, as correções e as ações que a linha mostra.
 */
class EspeciaisResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'data_limite' => $this->data_limite->setTimezone('-03:00')->toIso8601String(),
            'situacao' => $this->situacao,
            'aguardando_resultado' => $this->aguardando_resultado(),
            'ativo' => $this->ativo,
            'quantidade_vencedoras' => $this->quantidade_vencedoras,
            'dividir_empate' => $this->dividir_empate,
            'opcoes' => EspeciaisOpcoesResource::collection($this->whenLoaded('opcoes')),
            'vencedoras' => $this->whenLoaded('opcoes', fn () => $this->opcoes
                ->filter(fn (EspeciaisOpcoes $opcao) => $opcao->vencedora)
                ->map(fn (EspeciaisOpcoes $opcao) => ['id' => $opcao->id, 'nome' => $opcao->nome, 'empatada' => $opcao->empatada])
                ->values()),
            // números da lista (spec 024): só quando a consulta os trouxe
            'quantidade_opcoes' => $this->when($this->tem_atributo('quantidade_opcoes'), fn () => (int) $this->quantidade_opcoes),
            'apostas_em_aberto' => $this->when($this->tem_atributo('apostas_em_aberto'), fn () => (int) $this->apostas_em_aberto),
            'valor_em_aberto' => $this->when($this->tem_atributo('valor_em_aberto'), fn () => bcadd((string) ($this->valor_em_aberto ?? '0'), '0', 2)),
            'encerrado_em' => $this->encerrado_em?->setTimezone('-03:00')->toIso8601String(),
            'encerrado_por' => $this->whenLoaded('encerrado_por_usuario', fn () => $this->encerrado_por_usuario?->login),
            'quantidade_palpites' => $this->whenCounted('palpites'),
            'correcoes' => EspeciaisCorrecoesResource::collection($this->whenLoaded('correcoes')),
            'acoes' => $this->when($request->user() instanceof Usuarios, fn () => $this->acoes($request->user())),
            'deleted_at' => $this->deleted_at?->setTimezone('-03:00')->toIso8601String(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Ações que a linha mostra, pela situação, pela lixeira e pelas permissões (FR-006).
     *
     * @return list<string>
     */
    private function acoes(Usuarios $usuario): array
    {
        $pode = fn (string $permissao) => Funcao::usuario_pode($usuario, $permissao);

        if ($this->trashed()) {
            return array_values(array_filter(['detalhes', $pode('especiais.gerenciar') ? 'restaurar' : null]));
        }

        $aguardando = $this->situacao === SituacaoEspecial::Aguardando;

        return array_values(array_filter([
            'detalhes',
            $aguardando && $pode('especiais.gerenciar') ? 'editar' : null,
            $aguardando && $pode('especiais.encerrar') ? 'encerrar' : null,
            $this->situacao === SituacaoEspecial::Encerrado && $pode('especiais.corrigir_resultado') ? 'corrigir_resultado' : null,
            $aguardando && $pode('especiais.encerrar') ? 'cancelar' : null,
            $aguardando && $pode('especiais.gerenciar') ? 'remover' : null,
        ]));
    }

    private function tem_atributo(string $nome): bool
    {
        return array_key_exists($nome, $this->resource->getAttributes());
    }
}
