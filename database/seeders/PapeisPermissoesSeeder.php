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
 * apostas, de especiais, dos recursos do site, de pagamentos, de encerramentos, do cassino e de
 * afiliados, para a instalação num banco novo não depender da ordem dos outros seeders.
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
            ...Funcao::PERMISSOES_PAGAMENTOS,
            ...Funcao::PERMISSOES_ENCERRAMENTOS,
            ...Funcao::PERMISSOES_CASSINO,
            ...Funcao::PERMISSOES_AFILIADOS,
            ...Funcao::PERMISSOES_CAIXA,
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

            // permissões de pagamentos (spec 007), conforme a função
            foreach (Funcao::PERMISSOES_PAGAMENTOS as $permissao) {
                if ($usuario->funcao()?->pode_usar($permissao)) {
                    $usuario->givePermissionTo($permissao);
                }
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
