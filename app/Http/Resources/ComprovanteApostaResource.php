<?php

namespace App\Http\Resources;

use App\Enums\FormaPagamento;
use App\Enums\SituacaoAposta;
use App\Models\Apostas;
use App\Models\ApostasBlocos;
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
    private const FUTEBOL = 'FUTEBOL';

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
            'quantidade_jogos' => $this->quantidade_jogos(),
            // blocos Criar Aposta (spec 008); o desconto é dado interno e não aparece
            'blocos' => $aposta->blocos()
                ->orderBy('id')
                ->get()
                ->map(fn (ApostasBlocos $bloco) => [
                    'id' => $bloco->id,
                    'confrontos_id' => $bloco->confrontos_id,
                    'cotacao' => (string) $bloco->cotacao,
                    'situacao' => $bloco->situacao->value,
                ])
                ->all(),
            'palpites' => $aposta->palpites()
                ->with(['confronto.resultado', 'confronto.ao_vivo', 'campeonato', 'jogador', 'especial', 'opcao_especial'])
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
     * Quantidade de jogos da aposta ("Qtde. jogos"): cada palpite ativo fora de bloco e cada bloco
     * Criar Aposta com palpite ativo contam um (spec 008, FR-011).
     */
    private function quantidade_jogos(): int
    {
        $ativos = $this->resource->palpites_ativos()->get(['apostas_blocos_id']);

        return $ativos->whereNull('apostas_blocos_id')->count()
            + $ativos->whereNotNull('apostas_blocos_id')->unique('apostas_blocos_id')->count();
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
        if ($palpite->e_especial()) {
            return $this->palpite_especial($palpite, $fuso);
        }

        return [
            'id' => $palpite->id,
            'apostas_blocos_id' => $palpite->apostas_blocos_id,
            'situacao' => $palpite->situacao->value,
            'resultado' => $palpite->resultado->value,
            // motivo do cancelamento automático do palpite (spec 018); null quando cancelado por um usuário
            'motivo_cancelamento' => $palpite->motivo_cancelamento?->value,
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
            ...$this->andamento($palpite),
        ];
    }

    /**
     * Placar e período do jogo como no comprovante do sistema antigo (spec 009): o resultado
     * gravado pela apuração ou, com o jogo em andamento, o placar atual do ao vivo. Gols por
     * tempo e escanteios só no futebol; tudo nulo antes do início.
     *
     * @return array<string, mixed>
     */
    private function andamento(ApostasPalpites $palpite): array
    {
        $resultado = $palpite->confronto?->resultado;
        $ao_vivo = $palpite->confronto?->ao_vivo;
        $futebol = $palpite->esporte === self::FUTEBOL;

        if ($resultado !== null) {
            return [
                'periodo' => $resultado->situacao->value,
                'placar_casa' => $resultado->placar_casa,
                'placar_fora' => $resultado->placar_fora,
                'minuto' => null,
                'primeiro_tempo' => $futebol ? $this->tempo($resultado->gols_primeiro_tempo_casa, $resultado->gols_primeiro_tempo_fora) : null,
                'segundo_tempo' => $futebol ? $this->tempo($resultado->gols_segundo_tempo_casa, $resultado->gols_segundo_tempo_fora) : null,
                'escanteios' => $futebol && $resultado->escanteios_casa !== null && $resultado->escanteios_fora !== null
                    ? $resultado->escanteios_casa + $resultado->escanteios_fora
                    : null,
            ];
        }

        return [
            'periodo' => $ao_vivo?->situacao->value,
            'placar_casa' => $ao_vivo?->placar_casa,
            'placar_fora' => $ao_vivo?->placar_fora,
            'minuto' => $ao_vivo?->minuto,
            'primeiro_tempo' => null,
            'segundo_tempo' => null,
            'escanteios' => null,
        ];
    }

    /**
     * Gols de um tempo ("casa x fora"); nulo quando o provedor não mandou.
     *
     * @return array{casa: int, fora: int}|null
     */
    private function tempo(?int $casa, ?int $fora): ?array
    {
        return $casa !== null && $fora !== null ? ['casa' => $casa, 'fora' => $fora] : null;
    }

    /**
     * Palpite especial como no sistema antigo: "Vencedor" e a categoria no lugar dos times, a opção
     * como mercado e a data limite como horário (spec 006, FR-019).
     *
     * @return array<string, mixed>
     */
    private function palpite_especial(ApostasPalpites $palpite, CarbonTimeZone $fuso): array
    {
        return [
            'id' => $palpite->id,
            'apostas_blocos_id' => null,
            'situacao' => $palpite->situacao->value,
            'resultado' => $palpite->resultado->value,
            // motivo do cancelamento automático do palpite (spec 018); null quando cancelado por um usuário
            'motivo_cancelamento' => $palpite->motivo_cancelamento?->value,
            'campeonato' => $palpite->especial?->nome,
            'time_casa' => 'Vencedor',
            'time_fora' => $palpite->especial?->nome,
            'data_inicio' => $this->data($palpite->especial?->data_limite, $fuso),
            'esporte' => $palpite->esporte,
            'codigo_cotacao' => $palpite->codigo_cotacao,
            'especiais_opcoes_id' => $palpite->especiais_opcoes_id,
            'mercado' => $palpite->opcao_especial?->nome,
            'jogador' => null,
            'jogador_tipo' => null,
            'cotacao' => (string) $palpite->cotacao_final,
            // cotação aplicada pela divisão por empate (spec 024); null sem divisão
            'cotacao_empate' => $palpite->cotacao_empate === null ? null : (string) $palpite->cotacao_empate,
            'periodo' => null,
            'placar_casa' => null,
            'placar_fora' => null,
            'minuto' => null,
            'primeiro_tempo' => null,
            'segundo_tempo' => null,
            'escanteios' => null,
        ];
    }

    private function data(?Carbon $data, CarbonTimeZone $fuso): ?string
    {
        return $data?->copy()->setTimezone($fuso)->toIso8601String();
    }
}
