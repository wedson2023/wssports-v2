<?php

use App\Models\Usuarios;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Cria a permissão de configurar as mensagens (spec 025, FR-022) e a dá aos usuários cuja função a
 * recebe por padrão (só o Admin), no padrão de criar_permissao_robo_whatsapp.
 */
return new class extends Migration
{
    private const PERMISSAO = 'configuracoes.editar_mensagens';

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
