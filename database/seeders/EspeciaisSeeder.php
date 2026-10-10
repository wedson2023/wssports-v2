<?php

namespace Database\Seeders;

use App\Enums\Funcao;
use App\Models\Usuarios;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Garante as permissões de especiais e as distribui aos usuários já existentes conforme o padrão
 * da função (só Admin e Supervisor), sem tirar nenhuma que eles já tenham.
 */
class EspeciaisSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Funcao::PERMISSOES_ESPECIAIS as $permissao) {
            Permission::firstOrCreate(['name' => $permissao, 'guard_name' => 'api']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Usuarios::with('roles')->get()->each(function (Usuarios $usuario) {
            $permissoes = array_intersect($usuario->funcao()?->permissoes_padrao() ?? [], Funcao::PERMISSOES_ESPECIAIS);
            $usuario->givePermissionTo(array_values($permissoes));
        });
    }
}
