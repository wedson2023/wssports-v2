<?php

use App\Enums\Funcao;
use App\Models\Usuarios;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Divide as permissões de usuários por função alvo (spec 015, research R-02): usuarios.cadastrar,
 * editar e excluir viram uma por função (supervisor, gerente e vendedor). É uma migration de dados,
 * e não um seeder, porque o deploy contínuo (spec 013) roda migrations e não seeders: sem ela os
 * usuários do servidor perderiam o acesso ao cadastro no deploy.
 */
return new class extends Migration
{
    /**
     * Permissão antiga → ação das novas.
     */
    private const ACOES = [
        'usuarios.cadastrar' => 'cadastrar',
        'usuarios.editar' => 'editar',
        'usuarios.excluir' => 'excluir',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->limpar_cache();

        foreach (array_merge(...array_values(Funcao::PERMISSOES_USUARIOS_POR_FUNCAO)) as $permissao) {
            Permission::firstOrCreate(['name' => $permissao, 'guard_name' => 'api']);
        }

        $this->limpar_cache();

        // quem tinha a antiga recebe as novas da mesma ação que a função dele pode usar
        Usuarios::withTrashed()->with(['roles', 'permissions'])->get()->each(function (Usuarios $usuario) {
            $funcao = $usuario->funcao();
            $antigas = $usuario->permissions->pluck('name')->intersect(array_keys(self::ACOES));

            if ($funcao === null || $antigas->isEmpty()) {
                return;
            }

            $novas = $antigas->flatMap(fn (string $antiga) => array_map(
                fn (Funcao $alvo) => Funcao::permissao_usuarios(self::ACOES[$antiga], $alvo),
                [Funcao::Supervisor, Funcao::Gerente, Funcao::Vendedor],
            ))->filter(fn (string $permissao) => $funcao->pode_usar($permissao));

            $usuario->givePermissionTo($novas->values()->all());
        });

        Permission::whereIn('name', array_keys(self::ACOES))->where('guard_name', 'api')->delete();

        $this->limpar_cache();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->limpar_cache();

        foreach (array_keys(self::ACOES) as $permissao) {
            Permission::firstOrCreate(['name' => $permissao, 'guard_name' => 'api']);
        }

        $this->limpar_cache();

        // quem tinha alguma das novas de uma ação volta a ter a antiga dessa ação
        Usuarios::withTrashed()->with('permissions')->get()->each(function (Usuarios $usuario) {
            $nomes = $usuario->permissions->pluck('name');
            $antigas = array_keys(array_filter(
                self::ACOES,
                fn (string $acao) => $nomes->contains(fn (string $nome) => str_starts_with($nome, "usuarios.{$acao}_")),
            ));

            if ($antigas !== []) {
                $usuario->givePermissionTo($antigas);
            }
        });

        Permission::whereIn('name', array_merge(...array_values(Funcao::PERMISSOES_USUARIOS_POR_FUNCAO)))->where('guard_name', 'api')->delete();

        $this->limpar_cache();
    }

    private function limpar_cache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
