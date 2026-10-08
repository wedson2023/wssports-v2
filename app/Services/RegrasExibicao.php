<?php

namespace App\Services;

use App\Enums\AlvoRegra;
use App\Enums\PeriodoJogos;
use App\Enums\SituacaoAoVivo;
use App\Enums\SituacaoConfronto;
use App\Models\ClientesConfiguracoes;
use App\Models\Configuracoes;
use App\Models\UsuariosConfiguracoes;
use App\Models\VisitantesConfiguracoes;
use App\Support\FusoSistema;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * O que cada público pode ver — e, por isso, apostar. Usado pela listagem, pelo detalhe do
 * confronto e pela aposta, para que o apostável seja exatamente o exibido.
 */
class RegrasExibicao
{
    private const ESPORTE_PADRAO = 'FUTEBOL';

    /**
     * Configurações já lidas nesta requisição, por público.
     *
     * @var array<string, UsuariosConfiguracoes|ClientesConfiguracoes|VisitantesConfiguracoes|null>
     */
    private array $configuracoes = [];

    /**
     * Configuração do público: a do visitante, a do cliente, a do vendedor; null para gestores.
     */
    public function configuracao(Publico $publico): UsuariosConfiguracoes|ClientesConfiguracoes|VisitantesConfiguracoes|null
    {
        $chave = $publico->tipo.'|'.($publico->cliente?->id ?? $publico->usuario?->id ?? 0);

        if (! array_key_exists($chave, $this->configuracoes)) {
            $this->configuracoes[$chave] = match (true) {
                $publico->e_visitante() => VisitantesConfiguracoes::atual(),
                $publico->e_cliente() => ClientesConfiguracoes::where('clientes_id', $publico->cliente->id)->first(),
                $publico->e_vendedor() => UsuariosConfiguracoes::do_vendedor($publico->usuario->id),
                default => null,
            };
        }

        return $this->configuracoes[$chave];
    }

    /**
     * Base dos jogos do pré-jogo visíveis ao público (alias co e ca): ativos, ainda não
     * iniciados, que não estão no ao vivo, dentro do período e da data de travamento e fora dos
     * não permitidos. O filtro de esporte fica com quem usa a consulta.
     */
    public function consulta_pre_jogo(Publico $publico): Builder
    {
        $consulta = DB::table('confrontos as co')
            ->join('campeonatos as ca', 'ca.id', '=', 'co.campeonatos_id')
            ->whereNull('co.deleted_at')
            ->whereNull('ca.deleted_at')
            ->where('co.ativo', true)
            ->where('ca.ativo', true)
            ->where('co.situacao', SituacaoConfronto::Aguardando->value)
            ->where('co.data_inicio', '>', now());

        $limite = $this->limite_pre_jogo($publico);

        if ($limite === null) {
            // sistema travado: nenhum jogo
            $consulta->whereRaw('1 = 0');
        } else {
            $consulta->where('co.data_inicio', '<=', $limite);
        }

        $this->excluir_jogos_no_ao_vivo($consulta, 'co.id');
        $this->excluir_nao_permitidos($consulta, $publico, 'campeonatos_nao_permitidos', 'campeonatos_id', 'co.campeonatos_id');
        $this->excluir_nao_permitidos($consulta, $publico, 'confrontos_nao_permitidos', 'confrontos_id', 'co.id');

        return $consulta;
    }

    /**
     * Base dos jogos do ao vivo visíveis ao público (alias av, co e ca): com o confronto da grade
     * ativo, em andamento, dentro do minuto limite e do tempo de permanência, sem a data de
     * travamento passada e fora dos não permitidos. O filtro de esporte fica com quem usa a
     * consulta; a trava por tempo é marcada por quem lê (o jogo continua visível, travado).
     */
    public function consulta_ao_vivo(Publico $publico): Builder
    {
        $configuracoes = Configuracoes::atual();

        $consulta = DB::table('confrontos_ao_vivo as av')
            // o ao vivo só exibe jogos que a banca tem na grade, com o confronto do pré-jogo ativo
            ->join('confrontos as co', 'co.id', '=', 'av.confrontos_id')
            ->join('campeonatos as ca', 'ca.id', '=', 'av.campeonatos_id')
            ->whereNull('av.deleted_at')
            ->whereNull('co.deleted_at')
            ->whereNull('ca.deleted_at')
            ->where('co.ativo', true)
            ->where('ca.ativo', true)
            ->whereIn('av.situacao', self::situacoes_ao_vivo())
            ->where('av.minuto', '<=', $this->minuto_limite_ao_vivo($publico))
            ->where('av.ultima_atualizacao_em', '>=', now()->subMinutes($configuracoes->minutos_permanencia_ao_vivo));

        if ($this->sistema_travado($publico)) {
            $consulta->whereRaw('1 = 0');
        }

        $this->excluir_nao_permitidos($consulta, $publico, 'campeonatos_nao_permitidos', 'campeonatos_id', 'av.campeonatos_id');
        $this->excluir_nao_permitidos($consulta, $publico, 'confrontos_ao_vivo_nao_permitidos', 'confrontos_id', 'av.confrontos_id');

        return $consulta;
    }

    /**
     * Situações de um jogo em andamento.
     *
     * @return list<string>
     */
    public static function situacoes_ao_vivo(): array
    {
        return array_column(SituacaoAoVivo::cases(), 'value');
    }

    /**
     * Esportes que o público pode ver; null = todos (gestores).
     *
     * @return list<string>|null
     */
    public function esportes_permitidos(Publico $publico): ?array
    {
        if ($publico->e_gestor()) {
            return null;
        }

        $configuracao = $this->configuracao($publico);

        if ($configuracao === null) {
            return [self::ESPORTE_PADRAO];
        }

        return $configuracao->apostar_outros_esportes ? array_values($configuracao->esportes_permitidos ?? []) : [self::ESPORTE_PADRAO];
    }

    public function esporte_permitido(Publico $publico, string $esporte): bool
    {
        $permitidos = $this->esportes_permitidos($publico);

        return $permitidos === null || in_array(mb_strtoupper($esporte), array_map('mb_strtoupper', $permitidos), true);
    }

    /**
     * Período de jogos do público; gestores veem até depois de amanhã.
     */
    public function periodo(Publico $publico): PeriodoJogos
    {
        return $this->configuracao($publico)?->periodo_jogos ?? PeriodoJogos::DepoisDeAmanhã;
    }

    /**
     * Data de travamento do sistema (só vendedor e visitante); null = sem trava.
     */
    public function data_travamento(Publico $publico): ?Carbon
    {
        if (! $publico->e_vendedor() && ! $publico->e_visitante()) {
            return null;
        }

        return $this->configuracao($publico)?->data_travamento_sistema;
    }

    /**
     * A data de travamento já passou: o público não vê jogos nem aposta.
     */
    public function sistema_travado(Publico $publico): bool
    {
        $travamento = $this->data_travamento($publico);

        return $travamento !== null && now()->greaterThanOrEqualTo($travamento);
    }

    /**
     * Último início de jogo do pré-jogo que o público pode ver: o fim do período, limitado à
     * data de travamento; null quando o sistema está travado.
     */
    public function limite_pre_jogo(Publico $publico): ?Carbon
    {
        if ($this->sistema_travado($publico)) {
            return null;
        }

        $limite = FusoSistema::fim_do_dia_em($this->periodo($publico)->dias_a_frente());
        $travamento = $this->data_travamento($publico);

        return $travamento !== null ? $limite->min($travamento) : $limite;
    }

    public function pode_apostar_jogadores(Publico $publico): bool
    {
        return $this->configuracao($publico)?->apostar_jogadores ?? true;
    }

    /**
     * Minuto limite do ao vivo: o do vendedor ou, para os demais, o das configurações gerais.
     */
    public function minuto_limite_ao_vivo(Publico $publico): int
    {
        return $publico->e_vendedor()
            ? (int) $this->configuracao($publico)->minuto_limite_ao_vivo
            : (int) Configuracoes::atual()->minuto_limite_ao_vivo;
    }

    /**
     * O ao vivo está ligado para o público (chave geral, visitantes e vendedor).
     */
    public function ao_vivo_habilitado(Publico $publico): bool
    {
        $configuracao = $this->configuracao($publico);

        return Configuracoes::atual()->ao_vivo_habilitado
            && (! $publico->e_visitante() || $configuracao->ao_vivo_habilitado)
            && (! $publico->e_vendedor() || $configuracao->ao_vivo_habilitado);
    }

    public function garantir_ao_vivo_habilitado(Publico $publico): void
    {
        if (! $this->ao_vivo_habilitado($publico)) {
            throw new HttpException(403, 'O ao vivo não está disponível.');
        }
    }

    /**
     * Tira da consulta os confrontos que estão no ao vivo (em andamento), que não podem mais ser
     * vistos nem apostados como pré-jogo.
     */
    public function excluir_jogos_no_ao_vivo(Builder $consulta, string $coluna): void
    {
        $consulta->whereNotExists(function (Builder $ao_vivo) use ($coluna) {
            $ao_vivo->selectRaw('1')
                ->from('confrontos_ao_vivo as em_jogo')
                ->whereColumn('em_jogo.confrontos_id', $coluna)
                ->whereNull('em_jogo.deleted_at')
                ->whereIn('em_jogo.situacao', self::situacoes_ao_vivo());
        });
    }

    /**
     * Tira da consulta os itens não permitidos para o público: alvo Todos; alvo Clientes sem
     * cliente indicado (site) ou indicado para o cliente logado; alvo Vendedores com dono na
     * cadeia do usuário do painel.
     */
    public function excluir_nao_permitidos(Builder $consulta, Publico $publico, string $tabela, string $coluna, string $referencia): void
    {
        $consulta->whereNotExists(function (Builder $restricao) use ($publico, $tabela, $coluna, $referencia) {
            $restricao->selectRaw('1')
                ->from("{$tabela} as np")
                ->whereColumn("np.{$coluna}", $referencia)
                ->whereNull('np.deleted_at')
                ->where(function (Builder $alvos) use ($publico) {
                    $alvos->where('np.alvo', AlvoRegra::Todos->value);

                    if ($publico->e_site()) {
                        $alvos->orWhere(fn (Builder $c) => $c->where('np.alvo', AlvoRegra::Clientes->value)
                            ->where(fn (Builder $cliente) => $cliente->whereNull('np.clientes_id')
                                ->when($publico->e_cliente(), fn (Builder $x) => $x->orWhere('np.clientes_id', $publico->cliente->id))));
                    } else {
                        $alvos->orWhere(fn (Builder $c) => $c->where('np.alvo', AlvoRegra::Vendedores->value)
                            ->whereIn('np.usuarios_id', $publico->ids_hierarquia_acima()));
                    }
                });
        });
    }
}
