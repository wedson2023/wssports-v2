<?php

namespace App\Http\Resources;

use App\Enums\Carteira;
use App\Enums\Funcao;
use App\Enums\SituacaoBonus;
use App\Enums\TipoEstornoBonus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Bônus recebido por um cliente (spec 020, contracts/api.md §2): linha da consulta de
 * ListagemBonusRecebidos; com detalhe = true, também as regras gravadas e o histórico.
 */
class BonusRecebidosResource extends JsonResource
{
    public bool $detalhe = false;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $situacao = SituacaoBonus::from($this->situacao);
        $usuario = $request->user();

        return [
            'id' => $this->id,
            'cliente' => [
                'id' => $this->clientes_id,
                'nome' => $this->cliente_nome,
                'excluido' => $this->cliente_excluido_em !== null,
            ],
            'promocao' => [
                'id' => $this->clientes_promocoes_id,
                'nome' => $this->promocao_nome,
                'afiliado' => $this->afiliado_codigo,
            ],
            'modalidade' => $this->carteira === Carteira::PromoçãoEsportes->value ? 'Esportes' : 'Cassino',
            'valor' => self::dinheiro($this->valor),
            'saldo_promocional' => self::dinheiro($this->saldo_promocional),
            'rollover' => $this->rollover_id === null ? null : [
                'vezes' => (int) $this->vezes,
                'exigido' => self::dinheiro($this->valor_exigido),
                'apostado' => self::dinheiro($this->valor_apostado),
                'falta' => self::dinheiro($this->falta),
            ],
            'recebido_em' => self::data($this->recebido_em),
            'expira_em' => self::data($this->expira_em),
            'situacao' => $situacao->value,
            'encerrado_em' => self::data($this->encerrado_em($situacao)),
            'estorno' => $this->estornado_em === null ? null : [
                'tipo' => $this->estorno_tipo,
                'motivo' => $this->estorno_motivo,
                'autor' => $this->estorno_usuarios_id === null ? null : ['id' => $this->estorno_usuarios_id, 'nome' => $this->estorno_autor_nome],
                'em' => self::data($this->estornado_em),
                'valor_retirado' => $this->valor_retirado(),
            ],
            'pode' => [
                'estornar' => $usuario !== null
                    && Funcao::usuario_pode($usuario, 'clientes_promocoes.estornar')
                    && $situacao->estornavel(),
            ],
            'regras' => $this->when($this->detalhe, fn () => $this->rollover_id === null ? null : [
                'valor_minimo_aposta' => $this->valor_minimo_aposta === null ? null : self::dinheiro($this->valor_minimo_aposta),
                'valor_maximo_aposta' => $this->valor_maximo_aposta === null ? null : self::dinheiro($this->valor_maximo_aposta),
                'odd_minima_aposta_simples' => $this->odd_minima_aposta_simples,
                'odd_minima_aposta_multipla' => $this->odd_minima_aposta_multipla,
            ]),
            'historico' => $this->when($this->detalhe, fn () => $this->historico()),
        ];
    }

    /**
     * Data em que o bônus deixou de valer (ou cumpriu o rollover), conforme a situação.
     */
    private function encerrado_em(SituacaoBonus $situacao): ?string
    {
        return match ($situacao) {
            SituacaoBonus::Estornado => $this->estornado_em ?? $this->cancelado_em,
            SituacaoBonus::Convertido => $this->convertido_em,
            SituacaoBonus::Expirado => $this->expirado_em,
            SituacaoBonus::ExcluídoPeloCliente => $this->excluido_em,
            SituacaoBonus::Cumprido => $this->cumprido_em,
            SituacaoBonus::EmAndamento => null,
        };
    }

    /**
     * Valor retirado no estorno; os estornos gerais anteriores à spec 020 não guardaram o valor por
     * bônus (R-12) e voltam null, para a tela mostrar "—".
     */
    private function valor_retirado(): ?string
    {
        if ($this->estorno_tipo === TipoEstornoBonus::Geral->value && $this->estorno_debito_id === null && bccomp((string) $this->estorno_valor_retirado, '0', 2) === 0) {
            return null;
        }

        return self::dinheiro($this->estorno_valor_retirado);
    }

    /**
     * Linha do tempo do bônus, em ordem de data.
     *
     * @return list<array{evento: string, em: ?string, valor: ?string, autor: ?string, observacao: ?string}>
     */
    private function historico(): array
    {
        $eventos = [
            ['evento' => 'Recebido', 'em' => $this->recebido_em, 'valor' => self::dinheiro($this->valor), 'autor' => null, 'observacao' => $this->promocao_nome],
            ['evento' => 'Cumprido', 'em' => $this->cumprido_em, 'valor' => null, 'autor' => null, 'observacao' => null],
            ['evento' => 'Convertido', 'em' => $this->convertido_em, 'valor' => null, 'autor' => null, 'observacao' => null],
            ['evento' => 'Expirado', 'em' => $this->expirado_em, 'valor' => null, 'autor' => null, 'observacao' => null],
            ['evento' => 'Excluído pelo cliente', 'em' => $this->estornado_em === null ? $this->excluido_em : null, 'valor' => null, 'autor' => null, 'observacao' => null],
            [
                'evento' => 'Estornado',
                'em' => $this->estornado_em ?? ($this->excluido_em === null ? $this->cancelado_em : null),
                'valor' => $this->estornado_em === null ? null : $this->valor_retirado(),
                'autor' => $this->estorno_autor_nome,
                'observacao' => $this->estorno_motivo,
            ],
        ];

        return collect($eventos)
            ->filter(fn (array $evento) => $evento['em'] !== null)
            ->map(fn (array $evento) => [...$evento, 'em' => self::data($evento['em'])])
            ->sortBy('em')
            ->values()
            ->all();
    }

    private static function dinheiro(mixed $valor): string
    {
        return bcadd((string) ($valor ?? '0'), '0', 2);
    }

    /**
     * Datas do banco (UTC) em ISO 8601.
     */
    private static function data(?string $valor): ?string
    {
        return $valor === null ? null : Carbon::parse($valor, 'UTC')->toIso8601ZuluString();
    }
}
