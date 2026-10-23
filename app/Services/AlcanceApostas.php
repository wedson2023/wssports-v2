<?php

namespace App\Services;

use App\Enums\Funcao;
use App\Models\Apostas;
use App\Models\Usuarios;

/**
 * Quem pode cancelar e editar qual aposta (R-15): o vendedor, só as próprias; Gerente e
 * Supervisor, as dos vendedores da sua hierarquia; o Admin, todas; as apostas de cliente, que não
 * têm vendedor, o Gerente e o Supervisor só as dos clientes da rede (spec 019, AlcanceClientes).
 * Fora do alcance, a aposta é tratada como inexistente.
 */
class AlcanceApostas
{
    private const NAO_ENCONTRADA = 'Aposta não encontrada.';

    public function __construct(private readonly AlcanceClientes $alcance_clientes) {}

    public function garantir_pode_cancelar(Usuarios $usuario, Apostas $aposta): void
    {
        abort_unless($this->pode_cancelar($usuario, $aposta), 404, self::NAO_ENCONTRADA);
    }

    public function garantir_pode_editar(Usuarios $usuario, Apostas $aposta): void
    {
        abort_unless($this->pode_editar($usuario, $aposta), 404, self::NAO_ENCONTRADA);
    }

    /**
     * Mesmo alcance de garantir_pode_cancelar, como sim/não (ações da lista do painel, spec 018).
     */
    public function pode_cancelar(Usuarios $usuario, Apostas $aposta): bool
    {
        return $this->alcanca($usuario, $aposta, vendedor_alcanca_proprias: true);
    }

    /**
     * Mesmo alcance de garantir_pode_editar, como sim/não (ações do bilhete do painel, spec 018).
     */
    public function pode_editar(Usuarios $usuario, Apostas $aposta): bool
    {
        return $this->alcanca($usuario, $aposta, vendedor_alcanca_proprias: false);
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

        if ($funcao === Funcao::Admin) {
            return true;
        }

        // aposta de cliente: só dos clientes da rede (spec 019, FR-003c)
        if ($aposta->clientes_id !== null) {
            return $this->alcance_clientes->contem($usuario, $aposta->clientes_id);
        }

        return $aposta->usuarios_id !== null && in_array($aposta->usuarios_id, $usuario->ids_sub_hierarquia(), true);
    }
}
