<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirGerencia;
use App\Http\Requests\PermissaoUsuarioRequest;
use App\Models\Usuarios;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Models\Permission;

/**
 * Consulta, concessão e retirada de permissões diretas de um subordinado.
 */
class PermissoesUsuariosController extends Controller implements HasMiddleware
{
    use GarantirGerencia;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:usuarios.gerenciar_permissoes'),
        ];
    }

    public function index(Request $request, Usuarios $usuario): JsonResponse
    {
        $this->garantir_gerencia($request, $usuario);

        return $this->resposta_permissoes($usuario);
    }

    /**
     * Dá uma permissão ao subordinado; só é possível repassar o que o solicitante possui.
     */
    public function store(PermissaoUsuarioRequest $request, Usuarios $usuario): JsonResponse
    {
        $this->garantir_gerencia($request, $usuario);

        $permissao = $request->validated('permissao');

        abort_unless(
            $request->user()->hasPermissionTo($permissao),
            403,
            'Você só pode conceder permissões que possui.'
        );

        $usuario->givePermissionTo($permissao);

        return $this->resposta_permissoes($usuario);
    }

    /**
     * Tira uma permissão do subordinado, removendo o vínculo usuário–permissão.
     */
    public function destroy(Request $request, Usuarios $usuario, string $permissao): JsonResponse
    {
        $this->garantir_gerencia($request, $usuario);

        $permissao_existe = Permission::where(['name' => $permissao, 'guard_name' => 'api'])->exists();

        if (! $permissao_existe || ! $usuario->hasDirectPermission($permissao)) {
            return response()->json([
                'message' => 'A permissão informada não existe ou o usuário não a possui.',
            ], 422);
        }

        $usuario->revokePermissionTo($permissao);

        return $this->resposta_permissoes($usuario);
    }

    private function resposta_permissoes(Usuarios $usuario): JsonResponse
    {
        return response()->json([
            'permissoes' => $usuario->getPermissionNames()->values(),
        ]);
    }
}
