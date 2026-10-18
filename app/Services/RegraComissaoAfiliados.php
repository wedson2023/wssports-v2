<?php

namespace App\Services;

use App\Enums\Funcao;
use App\Enums\TipoGanhoAfiliado;
use App\Models\Afiliados;
use App\Models\AfiliadosComissoes;
use App\Models\Configuracoes;
use App\Models\Usuarios;
use Illuminate\Support\Facades\DB;

/**
 * Regra de comissão do afiliado com vigência (spec 012, R-08): a regra inicial do cadastro e a
 * troca, que encerra a vigente e abre a nova sem mexer nos lançamentos já gerados (FR-028).
 */
class RegraComissaoAfiliados
{
    /**
     * Regra do afiliado recém-cadastrado: a informada só vale para quem tem
     * afiliados.editar_comissao; sem ela (ou no cadastro público), a padrão da configuração.
     *
     * @param  array{tipo_ganho?: ?string, percentual?: mixed}  $regra
     */
    public function definir_inicial(Afiliados $afiliado, array $regra = [], ?Usuarios $autor = null): AfiliadosComissoes
    {
        $pode_definir = $autor !== null && Funcao::usuario_pode($autor, 'afiliados.editar_comissao');
        $informada = isset($regra['tipo_ganho'], $regra['percentual']);

        if ($pode_definir && $informada) {
            $tipo_ganho = TipoGanhoAfiliado::from($regra['tipo_ganho']);
            $percentual = (string) $regra['percentual'];
        } else {
            $configuracoes = Configuracoes::atual();
            $tipo_ganho = $configuracoes->afiliados_tipo_ganho_padrao;
            $percentual = (string) $configuracoes->afiliados_percentual_padrao;
        }

        return $afiliado->comissoes()->create([
            'tipo_ganho' => $tipo_ganho,
            'percentual' => bcadd($percentual, '0', 2),
            'vigente_desde' => now(),
            'usuarios_id' => $pode_definir && $informada ? $autor->id : null,
        ]);
    }

    /**
     * Encerra a regra vigente agora e abre a nova; o bloqueio do afiliado evita duas vigentes.
     */
    public function trocar(Afiliados $afiliado, TipoGanhoAfiliado $tipo_ganho, string $percentual, Usuarios $autor): AfiliadosComissoes
    {
        return DB::transaction(function () use ($afiliado, $tipo_ganho, $percentual, $autor) {
            Afiliados::withTrashed()->lockForUpdate()->findOrFail($afiliado->id);

            $agora = now();

            $afiliado->comissoes()->whereNull('vigente_ate')->update(['vigente_ate' => $agora]);

            return $afiliado->comissoes()->create([
                'tipo_ganho' => $tipo_ganho,
                'percentual' => bcadd($percentual, '0', 2),
                'vigente_desde' => $agora,
                'usuarios_id' => $autor->id,
            ]);
        });
    }
}
