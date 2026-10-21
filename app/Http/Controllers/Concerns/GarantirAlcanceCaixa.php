<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\Funcao;
use App\Models\Usuarios;
use Illuminate\Http\Request;

/**
 * Alcance do caixa (spec 017, R-08): o Admin vê a banca toda; os demais, eles mesmos e a sua
 * sub-hierarquia, inclusive inativos e excluídos (o caixa de quem saiu continua a ser cobrado,
 * FR-014a); o Vendedor, só ele.
 */
trait GarantirAlcanceCaixa
{
    private const MENSAGEM_SEM_PERMISSAO = 'Você não tem permissão para esta ação.';

    /**
     * Usuário em foco na consulta: vazio ou o próprio = quem consulta (o Admin no topo = null, a
     * banca); outro usuário só dentro do alcance.
     */
    private function foco_caixa(Request $request, ?int $usuarios_id): ?Usuarios
    {
        $solicitante = $request->user();

        if ($usuarios_id === null || $usuarios_id === $solicitante->id) {
            return $solicitante->funcao() === Funcao::Admin ? null : $solicitante;
        }

        $alvo = Usuarios::withTrashed()->find($usuarios_id);

        abort_unless($alvo !== null && $this->alcanca_caixa($solicitante, $alvo), 403, self::MENSAGEM_SEM_PERMISSAO);

        return $alvo;
    }

    /**
     * O alvo é o próprio solicitante, está abaixo dele ou o solicitante é o Admin.
     */
    private function alcanca_caixa(Usuarios $solicitante, Usuarios $alvo): bool
    {
        return $solicitante->funcao() === Funcao::Admin
            || $alvo->id === $solicitante->id
            || in_array($alvo->id, $solicitante->ids_sub_hierarquia_com_excluidos(), true);
    }

    /**
     * O usuário da rota é um vendedor dentro do alcance.
     */
    private function garantir_vendedor(Request $request, Usuarios $usuario): void
    {
        $this->garantir_funcao($request, $usuario, Funcao::Vendedor);
    }

    /**
     * O usuário da rota é um gerente dentro do alcance.
     */
    private function garantir_gerente(Request $request, Usuarios $usuario): void
    {
        $this->garantir_funcao($request, $usuario, Funcao::Gerente);
    }

    private function garantir_funcao(Request $request, Usuarios $usuario, Funcao $funcao): void
    {
        abort_unless($usuario->funcao() === $funcao, 404, 'Usuário não encontrado.');
        abort_unless($this->alcanca_caixa($request->user(), $usuario), 403, self::MENSAGEM_SEM_PERMISSAO);
    }
}
