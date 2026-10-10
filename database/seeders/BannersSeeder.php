<?php

namespace Database\Seeders;

use App\Models\Banners;
use App\Models\Usuarios;
use App\Services\ArmazenamentoImagens;
use Illuminate\Database\Seeder;
use Illuminate\Http\File;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permissão de banners (entregue aos usuários já existentes conforme a função) e o banner padrão
 * da instalação (spec 006, FR-032b). O banner só é criado se a tabela nunca teve nenhum, nem
 * removido: rodar de novo não duplica nem recria o que o administrador apagou (FR-034).
 */
class BannersSeeder extends Seeder
{
    public function run(ArmazenamentoImagens $imagens): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'banners.gerenciar', 'guard_name' => 'api']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Usuarios::with('roles')->get()->each(function (Usuarios $usuario) {
            if (in_array('banners.gerenciar', $usuario->funcao()?->permissoes_padrao() ?? [], true)) {
                $usuario->givePermissionTo('banners.gerenciar');
            }
        });

        if (Banners::withTrashed()->exists()) {
            return;
        }

        Banners::create([
            'imagem' => $imagens->salvar(new File(database_path('seeders/files/banner_padrao.jpg')), 'banners', 1280, 405),
            'link' => null,
            'ordem' => 0,
            'ativo' => true,
        ]);
    }
}
