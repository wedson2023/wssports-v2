<?php

use App\Models\Usuarios;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Cria a permissão de restaurar aposta cancelada (spec 018, R-13) e dá a cada usuário já cadastrado
 * a que a função dele recebe por padrão (Admin, Supervisor e Gerente). É uma migration de dados, e
 * não um seeder, porque o deploy contínuo (spec 013) roda migrations e não seeders.
 */
return new class extends Migration
{
    /**
     * Permissão criada por esta migration.
     */
    private const PERMISSOES = [
        'apostas.restaurar',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->limpar_cache();

        foreach (self::PERMISSOES as $permissao) {
            Permission::firstOrCreate(['name' => $permissao, 'guard_name' => 'api']);
        }

        $this->limpar_cache();

        Usuarios::withTrashed()->with(['roles', 'permissions'])->get()->each(function (Usuarios $usuario) {
            $funcao = $usuario->funcao();

            if ($funcao === null) {
                return;
            }

            $permissoes = array_values(array_intersect($funcao->permissoes_padrao(), self::PERMISSOES));

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

        Permission::whereIn('name', self::PERMISSOES)->where('guard_name', 'api')->delete();

        $this->limpar_cache();
    }

    private function limpar_cache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
