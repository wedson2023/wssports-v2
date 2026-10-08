<?php

namespace Database\Seeders;

use App\Enums\Funcao;
use App\Models\Usuarios;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Garante as permissões de apostas (e a de alterar o limite do confronto) e as distribui aos
 * usuários já existentes conforme o padrão da função, sem tirar nenhuma que eles já tenham.
 */
class ApostasSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $novas = [...Funcao::PERMISSOES_APOSTAS, 'confrontos.alterar_limite'];

        foreach ($novas as $permissao) {
            Permission::firstOrCreate(['name' => $permissao, 'guard_name' => 'api']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Usuarios::with('roles')->get()->each(function (Usuarios $usuario) use ($novas) {
            $permissoes = array_intersect($usuario->funcao()?->permissoes_padrao() ?? [], $novas);
            $usuario->givePermissionTo(array_values($permissoes));
        });
    }
}
