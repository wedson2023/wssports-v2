<?php

namespace Database\Factories;

use App\Enums\Genero;
use App\Models\Clientes;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Clientes>
 */
class ClientesFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome' => fake()->name(),
            'ddi' => '55',
            'telefone' => fake()->unique()->numerify('119########'),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('senha123'),
            'cpf' => $this->cpf_valido(),
            'data_nascimento' => fake()->dateTimeBetween('-70 years', '-18 years')->format('Y-m-d'),
            'genero' => fake()->randomElement(Genero::cases()),
            'ativo' => true,
        ];
    }

    /**
     * Todo cliente nasce com configurações; os valores vêm do padrão das colunas.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Clientes $cliente) {
            $cliente->configuracoes()->create([]);
        });
    }

    public function inativo(): static
    {
        return $this->state(fn () => ['ativo' => false]);
    }

    public function sem_cpf(): static
    {
        return $this->state(fn () => ['cpf' => null]);
    }

    public function sem_email(): static
    {
        return $this->state(fn () => ['email' => null]);
    }

    /**
     * CPF único com dígitos verificadores corretos.
     */
    private function cpf_valido(): string
    {
        $cpf = fake()->unique()->numerify('#########');

        for ($posicao = 9; $posicao < 11; $posicao++) {
            $soma = 0;

            for ($indice = 0; $indice < $posicao; $indice++) {
                $soma += (int) $cpf[$indice] * ($posicao + 1 - $indice);
            }

            $cpf .= ((10 * $soma) % 11) % 10;
        }

        return $cpf;
    }
}
