<?php

namespace Database\Seeders;

use App\Models\Usuarios;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(PapeisPermissoesSeeder::class);

        // hierarquia de exemplo: Admin raiz > Supervisor > Gerente > 2 Vendedores
        $admin = Usuarios::factory()->admin_raiz()->create([
            'nome' => 'Administrador',
            'login' => 'admin',
        ]);

        $supervisor = Usuarios::factory()->subordinado_de($admin)->create();
        $gerente = Usuarios::factory()->subordinado_de($supervisor)->create();

        Usuarios::factory()->count(2)->subordinado_de($gerente)->create();

        $this->call(ClientesSeeder::class);
        $this->call(ConfrontosSeeder::class);
    }
}
