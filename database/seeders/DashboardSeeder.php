<?php

namespace Database\Seeders;

use App\Enums\Funcao;
use App\Models\Usuarios;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Dashboard do painel (spec 014): cria as permissões de seção e dá a cada usuário já cadastrado as do
 * seu nível. Pode rodar mais de uma vez: não duplica nem retira permissões.
 */
class DashboardSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $novas = Funcao::PERMISSOES_DASHBOARD;

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
