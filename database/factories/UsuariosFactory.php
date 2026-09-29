<?php

namespace Database\Factories;

use App\Enums\Funcao;
use App\Models\Usuarios;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Usuarios>
 */
class UsuariosFactory extends Factory
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
            'login' => fake()->unique()->userName(),
            'password' => static::$password ??= Hash::make('password'),
            'telefone' => fake()->numerify('(##) #####-####'),
            'endereco' => fake()->streetAddress(),
            'ativo' => true,
            'usuarios_id' => null,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Admin raiz: topo da hierarquia, sem superior, com todas as permissões.
     */
    public function admin_raiz(): static
    {
        return $this->state(fn (array $attributes) => ['usuarios_id' => null])
            ->afterCreating(fn (Usuarios $usuario) => $this->atribuir_funcao($usuario, Funcao::Admin));
    }

    /**
     * Usuário com a função imediatamente abaixo do superior informado, vinculado a ele.
     */
    public function subordinado_de(Usuarios $superior): static
    {
        return $this->state(fn (array $attributes) => ['usuarios_id' => $superior->id])
            ->afterCreating(fn (Usuarios $usuario) => $this->atribuir_funcao($usuario, $superior->funcao()->funcao_abaixo()));
    }

    public function inativo(): static
    {
        return $this->state(fn (array $attributes) => ['ativo' => false]);
    }

    /**
     * Atribui o papel da função e as permissões padrão dela.
     */
    private function atribuir_funcao(Usuarios $usuario, Funcao $funcao): void
    {
        $usuario->assignRole($funcao->value);
        $usuario->givePermissionTo($funcao->permissoes_padrao());
    }
}
