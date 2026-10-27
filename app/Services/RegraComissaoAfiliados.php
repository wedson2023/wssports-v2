<?php

namespace App\Services;

use App\Enums\TipoGanhoAfiliado;
use App\Models\Afiliados;
use App\Models\AfiliadosComissoes;
use App\Models\Usuarios;
use Illuminate\Support\Facades\DB;

/**
 * Regra de comissão do afiliado com vigência (spec 012, R-08): a regra inicial, definida por quem
 * cadastra pelo painel (spec 022, FR-012), e a troca, que encerra a vigente e abre a nova sem mexer
 * nos lançamentos já gerados (FR-028). O cadastro público fica sem regra até a aprovação.
 */
class RegraComissaoAfiliados
{
    /**
     * Regra do afiliado recém-cadastrado pelo painel: a informada no cadastro, com o autor; não
     * depende de afiliados.editar_comissao, que só vale para trocar a regra depois.
     *
     * @param  array{tipo_ganho: string, percentual: mixed}  $regra
     */
    public function definir_inicial(Afiliados $afiliado, array $regra, Usuarios $autor): AfiliadosComissoes
    {
        return $afiliado->comissoes()->create([
            'tipo_ganho' => TipoGanhoAfiliado::from($regra['tipo_ganho']),
            'percentual' => bcadd((string) $regra['percentual'], '0', 2),
            'vigente_desde' => now(),
            'usuarios_id' => $autor->id,
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
