<?php

namespace App\Http\Resources;

use App\Enums\FormaPagamento;
use App\Enums\SituacaoAposta;
use App\Models\Apostas;
use App\Models\ApostasPalpites;
use App\Models\Configuracoes;
use App\Models\UsuariosConfiguracoes;
use App\Support\FusoSistema;
use App\Support\NomesCotacoes;
use Carbon\CarbonTimeZone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Comprovante da aposta (contrato "Comprovante"): tudo o que o front precisa para montar ou enviar
 * o bilhete, sem dados internos (FR-053 a FR-055).
 *
 * @property Apostas $resource
 */
class ComprovanteApostaResource extends JsonResource
{
    /**
     * @param  bool  $mostrar_comissao  só para o vendedor da aposta ou a hierarquia dele
     */
    public function __construct(Apostas $aposta, private bool $mostrar_comissao = false)
    {
        parent::__construct($aposta);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $aposta = $this->resource;

        if ($aposta->situacao === SituacaoAposta::EmAnálise) {
            return [
                'codigo' => $aposta->codigo,
                'situacao' => $aposta->situacao->value,
                'segundos_restantes' => $aposta->segundos_restantes(),
            ];
        }

        $fuso = FusoSistema::do_pedido($request->input('fuso_horario'));
        $configuracoes = Configuracoes::atual();
        $total = $aposta->total_a_pagar();

        $dados = [
            'codigo' => $aposta->codigo,
            'situacao' => $aposta->situacao->value,
            'resultado' => $aposta->resultado->value,
            'tipo' => $aposta->tipo->value,
            'nome' => $aposta->nome,
            'vendedor' => $this->vendedor(),
            'nome_sistema' => $configuracoes->nome_sistema,
            'criada_em' => $this->data($aposta->recebida_em, $fuso),
            'validada_em' => $this->data($aposta->validada_em, $fuso),
            'valor' => (string) $aposta->valor,
            'cotacao_total' => (string) $aposta->cotacao_total,
            'premio' => (string) $aposta->premio,
            'valor_acrescido' => (string) $aposta->valor_acrescido,
            'total_a_pagar' => $total,
            'premio_liquido' => $this->premio_liquido($total),
            'forma_pagamento' => $aposta->forma_pagamento?->value,
            // a mensagem não é gravada na aposta: vale sempre a atual das configurações
            'mensagem_bilhete' => $aposta->usuarios_id !== null
                ? UsuariosConfiguracoes::where('usuarios_id', $aposta->usuarios_id)->value('mensagem_bilhete')
                : $configuracoes->mensagem_bilhete,
            'assinatura' => $aposta->assinatura,
            'palpites' => $aposta->palpites()
                ->with(['confronto', 'campeonato', 'jogador'])
                ->orderBy('id')
                ->get()
                ->map(fn (ApostasPalpites $palpite) => $this->palpite($palpite, $fuso))
                ->all(),
        ];

        if ($aposta->situacao === SituacaoAposta::Pendente) {
            $dados['expira_em'] = $this->data($aposta->expira_em, $fuso);
        }

        if (in_array($aposta->situacao, [SituacaoAposta::Recusada, SituacaoAposta::Expirada], true)) {
            $dados['motivo_recusa'] = $aposta->motivo_recusa;
        }

        if ($this->mostrar_comissao) {
            $dados['comissao'] = (string) $aposta->comissao;
            $dados['percentual_comissao'] = (string) $aposta->percentual_comissao;
        }

        return $dados;
    }

    /**
     * Quem vendeu: o vendedor ou "Cliente"; nulo enquanto a aposta do visitante está Pendente.
     */
    private function vendedor(): ?string
    {
        $aposta = $this->resource;

        return match (true) {
            $aposta->clientes_id !== null => 'Cliente',
            $aposta->usuarios_id !== null => $aposta->vendedor?->nome,
            default => null,
        };
    }

    /**
     * Total menos a comissão sobre o prêmio, só nas apostas pagas em dinheiro.
     */
    private function premio_liquido(string $total): string
    {
        $aposta = $this->resource;

        if ($aposta->forma_pagamento !== FormaPagamento::Dinheiro) {
            return $total;
        }

        $desconto = bcdiv(bcmul($total, (string) $aposta->comissao_por_premio, 10), '100', 2);

        return bcsub($total, $desconto, 2);
    }

    /**
     * @return array<string, mixed>
     */
    private function palpite(ApostasPalpites $palpite, CarbonTimeZone $fuso): array
    {
        $ao_vivo = $palpite->dados_ao_vivo_envio;

        return [
            'id' => $palpite->id,
            'situacao' => $palpite->situacao->value,
            'campeonato' => $palpite->campeonato?->nome,
            'time_casa' => $palpite->confronto?->time_casa,
            'time_fora' => $palpite->confronto?->time_fora,
            'data_inicio' => $this->data($palpite->confronto?->data_inicio, $fuso),
            'esporte' => $palpite->esporte,
            'codigo_cotacao' => $palpite->codigo_cotacao,
            'mercado' => NomesCotacoes::nome($palpite->codigo_cotacao, $palpite->jogador_tipo),
            'jogador' => $palpite->jogador?->nome,
            'jogador_tipo' => $palpite->jogador_tipo,
            'cotacao' => (string) $palpite->cotacao_final,
            'placar_casa' => $ao_vivo['placar_casa'] ?? null,
            'placar_fora' => $ao_vivo['placar_fora'] ?? null,
            'minuto' => $ao_vivo['minuto'] ?? null,
        ];
    }

    private function data(?Carbon $data, CarbonTimeZone $fuso): ?string
    {
        return $data?->copy()->setTimezone($fuso)->toIso8601String();
    }
}
