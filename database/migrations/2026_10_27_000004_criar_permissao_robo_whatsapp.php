<?php

use App\Models\Usuarios;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Cria a permissão de conectar o robô do WhatsApp (spec 021, research R-13) e a dá aos usuários cuja
 * função a recebe por padrão (só o Admin). Migration de dados, e não seeder, porque o deploy contínuo
 * (spec 013) roda migrations e não seeders.
 */
return new class extends Migration
{
    private const PERMISSAO = 'robo_whatsapp.conectar';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->limpar_cache();

        Permission::firstOrCreate(['name' => self::PERMISSAO, 'guard_name' => 'api']);

        $this->limpar_cache();

        Usuarios::withTrashed()->with(['roles', 'permissions'])->get()->each(function (Usuarios $usuario) {
            if (in_array(self::PERMISSAO, $usuario->funcao()?->permissoes_padrao() ?? [], true)) {
                $usuario->givePermissionTo(self::PERMISSAO);
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

        Permission::where('name', self::PERMISSAO)->where('guard_name', 'api')->delete();

        $this->limpar_cache();
    }

    private function limpar_cache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
