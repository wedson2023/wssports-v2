<?php

namespace App\Services;

use App\Enums\AlvoRegra;
use App\Models\Configuracoes;
use App\Models\ConfrontosTetoCotacoes;
use App\Models\PorcentagensCampeonatos;
use App\Models\PorcentagensClientes;
use App\Models\PorcentagensClientesAoVivo;
use App\Models\PorcentagensConfrontos;
use App\Models\PorcentagensVendedores;
use App\Models\PorcentagensVendedoresAoVivo;
use App\Models\UsuariosConfiguracoes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Cotação exibida ao público: cotação do provedor + cotação × (soma das porcentagens) ÷ 100 +
 * valor fixo do confronto; limitada ao teto (e, no ao vivo, à cotação máxima); nunca menor que
 * 1,00; arredondada em 2 casas. Cotação zerada continua zerada. As regras de uma página são
 * carregadas com uma consulta por tabela, e nenhum valor intermediário sai daqui.
 */
class CalculoCotacoes
{
    public const PRE_JOGO = 'pre_jogo';

    public const AO_VIVO = 'ao_vivo';

    /**
     * Códigos exibidos na listagem.
     */
    public const CODIGOS_LISTAGEM = ['odd1', 'odd2', 'odd3', 'odd4'];

    /**
     * @param  Collection<int, object>  $itens  cada item com id, campeonatos_id, confrontos_id e cotacoes (array)
     * @return array<int, array<string, float>> cotações ajustadas por id do item
     */
    public function ajustar(Publico $publico, string $tipo, Collection $itens): array
    {
        if ($itens->isEmpty()) {
            return [];
        }

        $porcentagens_publico = $this->porcentagens_publico($publico, $tipo);
        $porcentagens_campeonatos = $this->regras_por_item(
            PorcentagensCampeonatos::query(), 'campeonatos_id', $itens->pluck('campeonatos_id')->unique(), $publico,
        );
        // o valor fixo por confronto não se aplica ao ao vivo
        $valores_fixos = $tipo === self::PRE_JOGO
            ? $this->regras_por_item(PorcentagensConfrontos::query(), 'confrontos_id', $itens->pluck('confrontos_id')->unique(), $publico)
            : [];
        $tetos = ConfrontosTetoCotacoes::atual()->tetos ?? [];
        $maximo_ao_vivo = $tipo === self::AO_VIVO ? $this->cotacao_maxima_ao_vivo($publico) : null;

        $ajustadas = [];

        foreach ($itens as $item) {
            foreach (self::CODIGOS_LISTAGEM as $codigo) {
                $base = (float) ($item->cotacoes[$codigo] ?? 0);

                if ($base <= 0) {
                    $ajustadas[$item->id][$codigo] = 0.0;

                    continue;
                }

                $porcentagem = ($porcentagens_publico[$codigo] ?? 0)
                    + ($porcentagens_campeonatos[$item->campeonatos_id][$codigo] ?? 0);
                $valor = $base + $base * $porcentagem / 100 + ($valores_fixos[$item->confrontos_id][$codigo] ?? 0);

                if (isset($tetos[$codigo])) {
                    $valor = min($valor, (float) $tetos[$codigo]);
                }

                if ($maximo_ao_vivo !== null) {
                    $valor = min($valor, $maximo_ao_vivo);
                }

                $ajustadas[$item->id][$codigo] = round(max($valor, 1.00), 2);
            }
        }

        return $ajustadas;
    }

    /**
     * Soma das porcentagens do público por código: para o painel, a do usuário e a de todos os
     * superiores; para o site, a regra geral de clientes mais a do cliente logado.
     *
     * @return array<string, float>
     */
    private function porcentagens_publico(Publico $publico, string $tipo): array
    {
        $ao_vivo = $tipo === self::AO_VIVO;

        $regras = $publico->e_site()
            ? ($ao_vivo ? PorcentagensClientesAoVivo::query() : PorcentagensClientes::query())
                ->where(fn (Builder $consulta) => $consulta->whereNull('clientes_id')
                    ->when($publico->e_cliente(), fn (Builder $c) => $c->orWhere('clientes_id', $publico->cliente->id)))
                ->get()
            : ($ao_vivo ? PorcentagensVendedoresAoVivo::query() : PorcentagensVendedores::query())
                ->whereIn('usuarios_id', $publico->ids_hierarquia_acima())
                ->get();

        return $this->somar($regras->pluck('valores'));
    }

    /**
     * Regras por campeonato ou por confronto que valem para o público, somadas por item e código:
     * alvo Todos; alvo Clientes para o site; alvo Vendedores com dono na cadeia do usuário.
     *
     * @param  Collection<int, int>  $ids
     * @return array<int, array<string, float>>
     */
    private function regras_por_item(Builder $consulta, string $coluna, Collection $ids, Publico $publico): array
    {
        $ids = $ids->filter()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return $this->filtrar_alvos($consulta->whereIn($coluna, $ids->all()), $publico)
            ->get([$coluna, 'valores'])
            ->groupBy($coluna)
            ->map(fn (Collection $regras) => $this->somar($regras->pluck('valores')))
            ->all();
    }

    /**
     * Filtra as regras com alvo que valem para o público.
     */
    private function filtrar_alvos(Builder $consulta, Publico $publico): Builder
    {
        return $consulta->where(function (Builder $alvos) use ($publico) {
            $alvos->where('alvo', AlvoRegra::Todos);

            if ($publico->e_site()) {
                $alvos->orWhere('alvo', AlvoRegra::Clientes);
            } else {
                $alvos->orWhere(fn (Builder $c) => $c->where('alvo', AlvoRegra::Vendedores)
                    ->whereIn('usuarios_id', $publico->ids_hierarquia_acima()));
            }
        });
    }

    /**
     * Cotação máxima do ao vivo: a do vendedor ou, para os demais, a das configurações gerais.
     */
    private function cotacao_maxima_ao_vivo(Publico $publico): float
    {
        return $publico->e_vendedor()
            ? (float) UsuariosConfiguracoes::do_vendedor($publico->usuario->id)->cotacao_maxima_ao_vivo
            : (float) Configuracoes::atual()->cotacao_maxima_ao_vivo;
    }

    /**
     * @param  Collection<int, array<string, mixed>|null>  $lista_valores
     * @return array<string, float>
     */
    private function somar(Collection $lista_valores): array
    {
        $soma = [];

        foreach ($lista_valores as $valores) {
            foreach ($valores ?? [] as $codigo => $valor) {
                $soma[$codigo] = ($soma[$codigo] ?? 0) + (float) $valor;
            }
        }

        return $soma;
    }
}
