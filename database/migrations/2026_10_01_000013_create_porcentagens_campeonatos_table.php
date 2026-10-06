<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // porcentagens de ajuste por campeonato, por alvo
        Schema::create('porcentagens_campeonatos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campeonatos_id')->constrained('campeonatos');
            // Clientes, Vendedores ou Todos
            $table->string('alvo', 20);
            // dono da regra, só no alvo Vendedores (vale para ele e para quem está abaixo dele)
            $table->foreignId('usuarios_id')->nullable()->constrained('usuarios');
            // o MySQL não considera dois nulos iguais num índice único: a coluna gerada evita regras repetidas
            $table->unsignedBigInteger('chave_usuario')->storedAs('COALESCE(usuarios_id, 0)');
            // porcentagem por código (odd1 a odd323 e jogador), de −100 a 100; código ausente = 0
            $table->json('valores')->default(new Expression('(JSON_OBJECT())'));
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['campeonatos_id', 'alvo', 'chave_usuario'], 'porcentagens_campeonatos_unico');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('porcentagens_campeonatos');
    }
};
