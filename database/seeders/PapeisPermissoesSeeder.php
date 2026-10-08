<?php

namespace Database\Seeders;

use App\Enums\Funcao;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Cria os papéis (funções) e as permissões de gestão de usuários, de clientes, de confrontos e de
 * apostas.
 * Os papéis não recebem permissões: elas são atribuídas diretamente a cada usuário.
 */
class PapeisPermissoesSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ([...Funcao::PERMISSOES_GESTAO, ...Funcao::PERMISSOES_CLIENTES, ...Funcao::PERMISSOES_CONFRONTOS, ...Funcao::PERMISSOES_APOSTAS] as $permissao) {
            Permission::firstOrCreate(['name' => $permissao, 'guard_name' => 'api']);
        }

        foreach (Funcao::cases() as $funcao) {
            Role::firstOrCreate(['name' => $funcao->value, 'guard_name' => 'api']);
        }
    }
}
