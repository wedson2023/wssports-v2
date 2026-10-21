<?php

use App\Enums\Funcao;
use App\Models\Usuarios;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Cria as permissões do caixa (spec 017, research R-08, FR-033) e dá a cada usuário já cadastrado as
 * que a função dele recebe por padrão. É uma migration de dados, e não um seeder, porque o deploy
 * contínuo (spec 013) roda migrations e não seeders.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->limpar_cache();

        foreach (Funcao::PERMISSOES_CAIXA as $permissao) {
            Permission::firstOrCreate(['name' => $permissao, 'guard_name' => 'api']);
        }

        $this->limpar_cache();

        Usuarios::withTrashed()->with(['roles', 'permissions'])->get()->each(function (Usuarios $usuario) {
            $funcao = $usuario->funcao();

            if ($funcao === null) {
                return;
            }

            $permissoes = array_values(array_intersect($funcao->permissoes_padrao(), Funcao::PERMISSOES_CAIXA));

            if ($permissoes !== []) {
                $usuario->givePermissionTo($permissoes);
            }
        });

        $this->limpar_cache();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->limpar_cache();

        Permission::whereIn('name', Funcao::PERMISSOES_CAIXA)->where('guard_name', 'api')->delete();

        $this->limpar_cache();
    }

    private function limpar_cache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
