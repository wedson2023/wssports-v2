<?php

namespace Database\Seeders;

use App\Enums\Funcao;
use App\Models\Usuarios;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Cria os papéis (funções) e as permissões de gestão de usuários, de clientes, de confrontos, de
 * apostas, de especiais e dos recursos do site.
 * Os papéis não recebem permissões: elas são atribuídas diretamente a cada usuário.
 */
class PapeisPermissoesSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissoes = [
            ...Funcao::PERMISSOES_GESTAO,
            ...Funcao::PERMISSOES_CLIENTES,
            ...Funcao::PERMISSOES_CONFRONTOS,
            ...Funcao::PERMISSOES_APOSTAS,
            ...Funcao::PERMISSOES_ESPECIAIS,
            ...Funcao::PERMISSOES_SITE,
        ];

        foreach ($permissoes as $permissao) {
            Permission::firstOrCreate(['name' => $permissao, 'guard_name' => 'api']);
        }

        foreach (Funcao::cases() as $funcao) {
            Role::firstOrCreate(['name' => $funcao->value, 'guard_name' => 'api']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // usuários cadastrados antes recebem a permissão das configurações do site (logo e
        // regras); as de especiais, avisos e banners são entregues pelos seeders desses recursos
        Usuarios::with('roles')->get()->each(function (Usuarios $usuario) {
            if ($usuario->funcao()?->pode_usar('configuracoes.editar')) {
                $usuario->givePermissionTo('configuracoes.editar');
            }
        });
    }
}
