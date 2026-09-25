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
        // Usuarios::factory(10)->create();

        Usuarios::factory()->create([
            'nome' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
