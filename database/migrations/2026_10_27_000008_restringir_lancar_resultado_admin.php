<?php

use App\Enums\Funcao;
use App\Models\Usuarios;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

/**
 * Lançar e corrigir o resultado de um jogo passa a ser só do Admin (spec 023, R-12): retira a
 * permissão de quem não é Admin. É uma migration de dados, e não um seeder, porque o deploy
 * contínuo (spec 013) roda migrations e não seeders.
 */
return new class extends Migration
{
    /**
     * Permissão restringida por esta migration.
     */
    private const PERMISSAO = 'confrontos.lancar_resultado';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->limpar_cache();

        Usuarios::withTrashed()->with(['roles', 'permissions'])->get()->each(function (Usuarios $usuario) {
            if ($usuario->funcao() !== Funcao::Admin && $usuario->hasDirectPermission(self::PERMISSAO)) {
                $usuario->revokePermissionTo(self::PERMISSAO);
            }
        });

        $this->limpar_cache();
    }

    /**
     * Reverse the migrations: devolve a permissão aos Supervisores, como era na spec 009.
     */
    public function down(): void
    {
        $this->limpar_cache();

        Usuarios::withTrashed()->with(['roles', 'permissions'])->get()->each(function (Usuarios $usuario) {
            if ($usuario->funcao() === Funcao::Supervisor && ! $usuario->hasDirectPermission(self::PERMISSAO)) {
                $usuario->givePermissionTo(self::PERMISSAO);
            }
        });

        $this->limpar_cache();
    }

    private function limpar_cache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
