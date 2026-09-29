<?php

namespace Database\Seeders;

use App\Enums\Funcao;
use App\Models\Clientes;
use App\Models\ClientesConfiguracoesPadrao;
use App\Models\Usuarios;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Garante as permissões de clientes e as distribui aos usuários já existentes conforme o padrão
 * da função (Funcao::permissoes_padrao); cria as configurações padrão com os valores do sistema
 * antigo (travas_gerentes / travas_vendedors) e clientes de exemplo.
 */
class ClientesSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Funcao::PERMISSOES_CLIENTES as $permissao) {
            Permission::firstOrCreate(['name' => $permissao, 'guard_name' => 'api']);
        }

        // usuários cadastrados antes desta feature recebem as permissões de clientes da sua função
        Usuarios::with('roles')->get()->each(function (Usuarios $usuario) {
            $permissoes = array_intersect($usuario->funcao()?->permissoes_padrao() ?? [], Funcao::PERMISSOES_CLIENTES);
            $usuario->givePermissionTo(array_values($permissoes));
        });

        if (! ClientesConfiguracoesPadrao::query()->exists()) {
            ClientesConfiguracoesPadrao::create([
                'realizar_aposta' => true,
                'apostar_ao_vivo' => true,
                'apostar_outros_esportes' => true,
                'cancelar_aposta' => false,
                'aceita_promocao' => true,
                'bloquear_saque' => false,
                'quantidade_minima_opcoes' => 1,
                'quantidade_maxima_opcoes' => 20,
                'valor_minimo_aposta' => '2.00',
                'valor_maximo_aposta' => '1000.00',
                'premio_maximo' => '50000.00',
                'valor_maximo_diario' => '5000.00',
                'valor_maximo_saque_diario' => '5000.00',
                'quantidade_maxima_saques_diaria' => 5,
                'odd_minima' => '1.90',
                'odd_maxima' => '30.00',
                'esportes_permitidos' => ['FUTEBOL', 'HOQUEI NO GELO', 'BAISEBOL'],
            ]);
        }

        // clientes de exemplo para a validação manual
        Clientes::factory()->count(3)->create();
        Clientes::factory()->inativo()->create();
        Clientes::factory()->sem_cpf()->create();
    }
}
