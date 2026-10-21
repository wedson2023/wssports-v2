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
use App\Support\CodigosCotacao;
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
     * @param  list<string>  $codigos  códigos a calcular (a listagem usa só os 4 principais)
     * @return array<int, array<string, float>> cotações ajustadas por id do item
     */
    public function ajustar(Publico $publico, string $tipo, Collection $itens, array $codigos = self::CODIGOS_LISTAGEM): array
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
            foreach ($codigos as $codigo) {
                $porcentagem = ($porcentagens_publico[$codigo] ?? 0)
                    + ($porcentagens_campeonatos[$item->campeonatos_id][$codigo] ?? 0);

                $ajustadas[$item->id][$codigo] = $this->aplicar(
                    (float) ($item->cotacoes[$codigo] ?? 0),
                    $porcentagem,
                    (float) ($valores_fixos[$item->confrontos_id][$codigo] ?? 0),
                    isset($tetos[$codigo]) ? (float) $tetos[$codigo] : null,
                    $maximo_ao_vivo,
                );
            }
        }

        return $ajustadas;
    }

    /**
     * Cotação de jogadores do pré-jogo para o público: odd do provedor ajustada pelas
     * porcentagens do código "jogador" (público e campeonato) e limitada ao teto "jogador".
     *
     * @param  Collection<int, object>  $jogadores  cada um com id, campeonatos_id e odd
     * @return array<int, float> cotação ajustada por id do jogador
     */
    public function ajustar_jogadores(Publico $publico, Collection $jogadores): array
    {
        if ($jogadores->isEmpty()) {
            return [];
        }

        $codigo = CodigosCotacao::JOGADOR;
        $porcentagem_publico = $this->porcentagens_publico($publico, self::PRE_JOGO)[$codigo] ?? 0;
        $porcentagens_campeonatos = $this->regras_por_item(
            PorcentagensCampeonatos::query(), 'campeonatos_id', $jogadores->pluck('campeonatos_id')->unique(), $publico,
        );
        $teto = ConfrontosTetoCotacoes::atual()->tetos[$codigo] ?? null;

        $ajustadas = [];

        foreach ($jogadores as $jogador) {
            $ajustadas[$jogador->id] = $this->aplicar(
                (float) $jogador->odd,
                $porcentagem_publico + ($porcentagens_campeonatos[$jogador->campeonatos_id][$codigo] ?? 0),
                0.0,
                $teto !== null ? (float) $teto : null,
                null,
            );
        }

        return $ajustadas;
    }

    /**
     * Partes do cálculo de cada código de um jogo para a prévia da tela de cotações (spec 016, R-03):
     * cotação do provedor, porcentagem do próprio público, dos superiores (vendedor) ou da regra geral
     * (cliente específico), do campeonato, valor do jogo, teto e máximo do ao vivo. A soma final é
     * feita na tela com a mesma regra de aplicar(), já com os valores ainda não salvos.
     *
     * @param  list<string>  $codigos
     * @param  array{camada?: string, alvo?: AlvoRegra, usuarios_id?: int|null}  $excluir  regra de campeonato
     *                                                                                    ou jogo em edição, fora das parcelas
     * @return array<string, array<string, float|null>>
     */
    public function detalhar(Publico $publico, string $tipo, object $item, array $codigos, array $excluir = []): array
    {
        $ao_vivo = $tipo === self::AO_VIVO;
        [$proprias, $acima] = $this->porcentagens_publico_separadas($publico, $ao_vivo);

        $campeonato = $this->somar($this->sem_regra_em_edicao(
            $this->filtrar_alvos(PorcentagensCampeonatos::query()->where('campeonatos_id', $item->campeonatos_id), $publico),
            $excluir,
            'campeonatos',
        )->pluck('valores'));

        // o valor fixo por confronto não se aplica ao ao vivo
        $jogo = $ao_vivo ? [] : $this->somar($this->sem_regra_em_edicao(
            $this->filtrar_alvos(PorcentagensConfrontos::query()->where('confrontos_id', $item->confrontos_id), $publico),
            $excluir,
            'confrontos',
        )->pluck('valores'));

        $tetos = ConfrontosTetoCotacoes::atual()->tetos ?? [];
        $maximo_ao_vivo = $ao_vivo ? $this->cotacao_maxima_ao_vivo($publico) : null;
        $partes = [];

        foreach ($codigos as $codigo) {
            $partes[$codigo] = [
                'provedor' => (float) ($item->cotacoes[$codigo] ?? 0),
                'publico' => (float) ($proprias[$codigo] ?? 0),
                'superiores' => (float) ($acima[$codigo] ?? 0),
                'campeonato' => (float) ($campeonato[$codigo] ?? 0),
                'jogo' => (float) ($jogo[$codigo] ?? 0),
                'teto' => isset($tetos[$codigo]) ? (float) $tetos[$codigo] : null,
                'maximo_ao_vivo' => $maximo_ao_vivo,
            ];
        }

        return $partes;
    }

    /**
     * Porcentagens do público separadas em [próprias, de cima]: no painel, as do usuário e a soma dos
     * superiores; no site, a do cliente (ou a geral, para visitante) e a geral somada à do cliente.
     *
     * @return array{0: array<string, float>, 1: array<string, float>}
     */
    private function porcentagens_publico_separadas(Publico $publico, bool $ao_vivo): array
    {
        if ($publico->e_site()) {
            $model = $ao_vivo ? PorcentagensClientesAoVivo::class : PorcentagensClientes::class;
            $geral = $this->somar(collect([$model::whereNull('clientes_id')->value('valores')]));

            if (! $publico->e_cliente()) {
                return [$geral, []];
            }

            return [$this->somar(collect([$model::where('clientes_id', $publico->cliente->id)->value('valores')])), $geral];
        }

        $model = $ao_vivo ? PorcentagensVendedoresAoVivo::class : PorcentagensVendedores::class;
        $id = $publico->usuario->id;
        $regras = $model::whereIn('usuarios_id', $publico->ids_hierarquia_acima())->get(['usuarios_id', 'valores']);

        return [
            $this->somar($regras->where('usuarios_id', $id)->pluck('valores')),
            $this->somar($regras->where('usuarios_id', '!=', $id)->pluck('valores')),
        ];
    }

    /**
     * Tira da consulta a regra que está sendo editada (mesma camada, alvo e dono), para a tela somar
     * os valores pendentes no lugar dela.
     *
     * @param  array{camada?: string, alvo?: AlvoRegra, usuarios_id?: int|null}  $excluir
     */
    private function sem_regra_em_edicao(Builder $consulta, array $excluir, string $camada): Builder
    {
        if (($excluir['camada'] ?? null) !== $camada || ! isset($excluir['alvo'])) {
            return $consulta;
        }

        return $consulta->whereNot(fn (Builder $c) => $c->where('alvo', $excluir['alvo'])
            ->where('chave_usuario', $excluir['usuarios_id'] ?? 0));
    }

    /**
     * Regra do cálculo para uma cotação: zerada continua zerada; senão base + base × porcentagem
     * ÷ 100 + valor fixo, limitada ao teto e à cotação máxima, nunca menor que 1,00, em 2 casas.
     */
    private function aplicar(float $base, float $porcentagem, float $valor_fixo, ?float $teto, ?float $maximo): float
    {
        if ($base <= 0) {
            return 0.0;
        }

        $valor = $base + $base * $porcentagem / 100 + $valor_fixo;

        if ($teto !== null) {
            $valor = min($valor, $teto);
        }

        if ($maximo !== null) {
            $valor = min($valor, $maximo);
        }

        return round(max($valor, 1.00), 2);
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
