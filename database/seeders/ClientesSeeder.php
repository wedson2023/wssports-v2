<?php

namespace Database\Seeders;

use App\Enums\Funcao;
use App\Models\Clientes;
use App\Models\Usuarios;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Garante as permissões de clientes e as distribui aos usuários já existentes conforme o padrão
 * da função (Funcao::permissoes_padrao) e cria clientes de exemplo. As configurações de cada
 * cliente nascem com os valores padrão das colunas (não existe tabela padrão).
 */
class ClientesSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Funcao::PERMISSOES_CLIENTES as $permissao) {
            Permission::firstOrCreate(['name' => $permissao, 'guard_name' => 'api']);
        }

        // usuários cadastrados antes desta feature recebem as permissões de clientes da sua função
        Usuarios::with('roles')->get()->each(function (Usuarios $usuario) {
            $permissoes = array_intersect($usuario->funcao()?->permissoes_padrao() ?? [], Funcao::PERMISSOES_CLIENTES);
            $usuario->givePermissionTo(array_values($permissoes));
        });

        // clientes de exemplo para a validação manual
        Clientes::factory()->count(3)->create();
        Clientes::factory()->inativo()->create();
        Clientes::factory()->sem_cpf()->create();
    }
}
