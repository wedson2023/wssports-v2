<?php

namespace Database\Seeders;

use App\Enums\Funcao;
use App\Models\Configuracoes;
use App\Models\ConfrontosTetoCotacoes;
use App\Models\Usuarios;
use App\Models\UsuariosConfiguracoes;
use App\Models\VisitantesConfiguracoes;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Garante as permissões de confrontos e as distribui aos usuários já existentes conforme o padrão
 * da função; cria os registros únicos (configurações gerais, dos visitantes e teto de cotação) só
 * com os valores padrão das colunas e a configuração de cada vendedor que ainda não tem.
 */
class ConfrontosSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Funcao::PERMISSOES_CONFRONTOS as $permissao) {
            Permission::firstOrCreate(['name' => $permissao, 'guard_name' => 'api']);
        }

        // não existe mais tabela padrão de configurações dos clientes (spec 003, FR-079)
        Permission::where('name', 'clientes.editar_configuracoes_padrao')->where('guard_name', 'api')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // usuários cadastrados antes desta feature recebem as permissões de confrontos da sua função
        Usuarios::with('roles')->get()->each(function (Usuarios $usuario) {
            $permissoes = array_intersect($usuario->funcao()?->permissoes_padrao() ?? [], Funcao::PERMISSOES_CONFRONTOS);
            $usuario->givePermissionTo(array_values($permissoes));
        });

        // registros únicos: sem tabela padrão, valem os padrões das colunas
        foreach ([Configuracoes::class, VisitantesConfiguracoes::class, ConfrontosTetoCotacoes::class] as $model) {
            if (! $model::query()->exists()) {
                (new $model)->save();
            }
        }

        // todo vendedor precisa ter a sua configuração
        Usuarios::role(Funcao::Vendedor->value)
            ->whereDoesntHave('configuracoes')
            ->pluck('id')
            ->each(fn (int $usuarios_id) => UsuariosConfiguracoes::create(['usuarios_id' => $usuarios_id]));
    }
}
