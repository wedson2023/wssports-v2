<?php

namespace App\Services;

use App\Enums\Funcao;
use App\Models\Apostas;
use App\Models\Usuarios;

/**
 * Quem pode cancelar e editar qual aposta (R-15): o vendedor, só as próprias; Gerente e
 * Supervisor, as dos vendedores da sua hierarquia; o Admin, todas; as apostas de cliente, que não
 * têm vendedor, qualquer Gerente, Supervisor ou Admin com a permissão. Fora do alcance, a aposta é
 * tratada como inexistente.
 */
class AlcanceApostas
{
    private const NAO_ENCONTRADA = 'Aposta não encontrada.';

    public function garantir_pode_cancelar(Usuarios $usuario, Apostas $aposta): void
    {
        abort_unless($this->alcanca($usuario, $aposta, vendedor_alcanca_proprias: true), 404, self::NAO_ENCONTRADA);
    }

    public function garantir_pode_editar(Usuarios $usuario, Apostas $aposta): void
    {
        abort_unless($this->alcanca($usuario, $aposta, vendedor_alcanca_proprias: false), 404, self::NAO_ENCONTRADA);
    }

    private function alcanca(Usuarios $usuario, Apostas $aposta, bool $vendedor_alcanca_proprias): bool
    {
        $funcao = $usuario->funcao();

        if ($funcao === Funcao::Vendedor) {
            return $vendedor_alcanca_proprias && $aposta->clientes_id === null && $aposta->usuarios_id === $usuario->id;
        }

        if ($funcao === null) {
            return false;
        }

        if ($aposta->clientes_id !== null || $funcao === Funcao::Admin) {
            return true;
        }

        return $aposta->usuarios_id !== null && in_array($aposta->usuarios_id, $usuario->ids_sub_hierarquia(), true);
    }
}
