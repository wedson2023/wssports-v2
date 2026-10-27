<?php

namespace App\Http\Resources;

use App\Enums\Funcao;
use App\Enums\OrigemApuracao;
use App\Models\ConfrontosGols;
use App\Models\Usuarios;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Confronto do pré-jogo no painel: a cotação do provedor pode aparecer aqui. Na tela Confrontos
 * (spec 023) traz também o ao vivo de agora, o resultado, as restrições por rede, as apostas em
 * aberto, a exposição (aba Mais apostados) e as ações que a linha mostra.
 */
class ConfrontosResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo_externo' => $this->codigo_externo,
            'campeonatos_id' => $this->campeonatos_id,
            'campeonato' => $this->whenLoaded('campeonato', fn () => [
                'id' => $this->campeonato->id,
                'nome' => $this->campeonato->nome,
                'pais' => $this->campeonato->pais,
                'ativo' => $this->campeonato->ativo,
            ]),
            'time_casa' => $this->time_casa,
            'escudo_casa' => $this->escudo_casa,
            'time_fora' => $this->time_fora,
            'escudo_fora' => $this->escudo_fora,
            'esporte' => $this->esporte,
            'situacao' => $this->situacao,
            'data_inicio' => $this->data_inicio,
            'ativo' => $this->ativo,
            'manual' => $this->manual,
            'odd4_sorteada' => $this->odd4_sorteada,
            'odd7_sorteada' => $this->odd7_sorteada,
            'quantidade_cotacoes' => $this->quantidade_cotacoes,
            'cotacoes' => (object) ($this->cotacoes ?? []),
            'limite_valor_apostado' => self::valor($this->limite_valor_apostado),
            'jogadores' => $this->whenLoaded('jogadores', fn () => $this->jogadores->map(fn ($jogador) => [
                'codigo_externo' => $jogador->codigo_externo,
                'nome' => $jogador->nome,
                'opcao' => $jogador->opcao,
                'tipo' => $jogador->tipo,
                'odd' => $jogador->odd,
            ])),
            'nao_permitido' => (bool) ($this->nao_permitido ?? false),
            // campos da tela Confrontos (spec 023): só quando a consulta os trouxe
            'apostas_em_aberto' => $this->when($this->tem_atributo('apostas_em_aberto'), fn () => (int) $this->apostas_em_aberto),
            'ao_vivo' => $this->whenLoaded('ao_vivo', fn () => $this->ao_vivo === null ? null : [
                'id' => $this->ao_vivo->id,
                'minuto' => $this->ao_vivo->minuto,
                'cronometro' => $this->ao_vivo->cronometro,
                'situacao' => $this->ao_vivo->situacao,
                'placar_casa' => $this->ao_vivo->placar_casa,
                'placar_fora' => $this->ao_vivo->placar_fora,
                'limite_valor_apostado' => self::valor($this->ao_vivo->limite_valor_apostado),
            ]),
            'resultado' => $this->whenLoaded('resultado', fn () => $this->resultado()),
            'restricoes' => $this->when($this->tem_atributo('nao_permitido_proprio_pre_jogo_id'), fn () => [
                'nao_permitido_proprio' => [
                    'pre_jogo_id' => $this->nao_permitido_proprio_pre_jogo_id,
                    'ao_vivo_id' => $this->nao_permitido_proprio_ao_vivo_id,
                ],
                'redes_desativadas' => $this->tem_atributo('redes_desativadas') ? (int) $this->redes_desativadas : null,
                ...($this->tem_atributo('nao_permitido_todos') ? ['nao_permitido_todos' => (bool) $this->nao_permitido_todos] : []),
            ]),
            'cotacoes_site' => $this->when($this->tem_atributo('cotacoes_site'), fn () => $this->cotacoes_site),
            // exposição da aba Mais apostados (contracts/api §2.2)
            'quantidade' => $this->when($this->tem_atributo('quantidade'), fn () => (int) $this->quantidade),
            'simples' => $this->when($this->tem_atributo('quantidade'), fn () => [
                'apostas' => (int) $this->simples_apostas,
                'valor' => self::valor($this->simples_valor),
                'retorno' => self::valor($this->simples_retorno),
            ]),
            'multiplas' => $this->when($this->tem_atributo('quantidade'), fn () => [
                'apostas' => (int) $this->multiplas_apostas,
                'valor' => self::valor($this->multiplas_valor),
                'retorno' => self::valor($this->multiplas_retorno),
            ]),
            'acoes' => $this->when($this->tem_atributo('nao_permitido_proprio_pre_jogo_id'), fn () => $this->acoes($request->user())),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Valor do banco (texto ou nulo) com 2 casas, sem ponto flutuante.
     */
    public static function valor(mixed $valor): string
    {
        return bcadd((string) ($valor ?? '0'), '0', 2);
    }

    /**
     * Placar na lista; o resultado completo, com os gols e a origem, na gaveta (quando os gols
     * foram carregados). Sem placar gravado, null.
     *
     * @return array<string, mixed>|null
     */
    private function resultado(): ?array
    {
        if ($this->resultado === null || $this->resultado->placar_casa === null) {
            return null;
        }

        $placar = ['placar_casa' => $this->resultado->placar_casa, 'placar_fora' => $this->resultado->placar_fora];

        if (! $this->relationLoaded('gols')) {
            return $placar;
        }

        return [
            ...$placar,
            'gols_primeiro_tempo_casa' => $this->resultado->gols_primeiro_tempo_casa,
            'gols_primeiro_tempo_fora' => $this->resultado->gols_primeiro_tempo_fora,
            'gols_segundo_tempo_casa' => $this->resultado->gols_segundo_tempo_casa,
            'gols_segundo_tempo_fora' => $this->resultado->gols_segundo_tempo_fora,
            'escanteios_casa' => $this->resultado->escanteios_casa,
            'escanteios_fora' => $this->resultado->escanteios_fora,
            'gols' => $this->gols->map(fn (ConfrontosGols $gol) => [
                'ordem' => $gol->ordem,
                'nome' => $gol->nome,
                'minuto' => $gol->minuto,
                'tempo_jogo' => $gol->tempo_jogo,
            ]),
            // origem da última apuração do jogo; sem nenhuma, pela origem do jogo
            'origem' => $this->origem_resultado
                ?? ($this->manual ? OrigemApuracao::LançamentoManual->value : OrigemApuracao::CargaResultados->value),
        ];
    }

    /**
     * Ações que a linha mostra, pela permissão, pela função e pelo jogo (contracts/api §1.1).
     *
     * @return array<string, bool>
     */
    private function acoes(Usuarios $usuario): array
    {
        $pode = fn (string $permissao) => Funcao::usuario_pode($usuario, $permissao);
        $gestor_geral = in_array($usuario->funcao(), [Funcao::Admin, Funcao::Supervisor], true);

        return [
            'alterar_situacao' => $gestor_geral && $pode('confrontos.alterar_situacao'),
            'desativar_rede' => ! $gestor_geral && $pode('confrontos_nao_permitidos.gerenciar'),
            'desativar_rede_ao_vivo' => ! $gestor_geral && $pode('confrontos_ao_vivo_nao_permitidos.gerenciar'),
            'alterar_limite' => $pode('confrontos.alterar_limite'),
            // só depois do início do jogo (R-21)
            'lancar_resultado' => $pode('confrontos.lancar_resultado') && $this->data_inicio !== null && $this->data_inicio->lte(now()),
            'alterar_cotacoes' => $pode('porcentagens_confrontos.editar'),
            'editar' => $this->manual && $pode('confrontos.editar'),
            'excluir' => $this->manual && $pode('confrontos.excluir'),
        ];
    }

    private function tem_atributo(string $nome): bool
    {
        return array_key_exists($nome, $this->resource->getAttributes());
    }
}
