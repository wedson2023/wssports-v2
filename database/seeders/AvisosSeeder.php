<?php

namespace Database\Seeders;

use App\Models\Avisos;
use App\Models\Usuarios;
use App\Services\ArmazenamentoImagens;
use Illuminate\Database\Seeder;
use Illuminate\Http\File;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permissão de avisos (entregue aos usuários já existentes conforme a função) e o aviso padrão da
 * instalação (spec 006, FR-027). O aviso só é criado se a tabela nunca teve nenhum, nem removido:
 * rodar de novo não duplica nem recria o que o administrador apagou (FR-034).
 */
class AvisosSeeder extends Seeder
{
    public function run(ArmazenamentoImagens $imagens): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'avisos.gerenciar', 'guard_name' => 'api']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Usuarios::with('roles')->get()->each(function (Usuarios $usuario) {
            if (in_array('avisos.gerenciar', $usuario->funcao()?->permissoes_padrao() ?? [], true)) {
                $usuario->givePermissionTo('avisos.gerenciar');
            }
        });

        if (Avisos::withTrashed()->exists()) {
            return;
        }

        Avisos::create([
            'titulo' => 'Bem-vindo',
            'imagem' => $imagens->salvar(new File(database_path('seeders/files/aviso_padrao.png')), 'avisos'),
            'link' => null,
            'ativo' => true,
        ]);
    }
}
