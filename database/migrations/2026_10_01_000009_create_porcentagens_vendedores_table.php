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
        // porcentagens de ajuste do pré-jogo por usuário do painel (supervisor, gerente ou vendedor)
        Schema::create('porcentagens_vendedores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuarios_id')->unique()->constrained('usuarios');
            // porcentagem por código (odd1 a odd323 e jogador), de −100 a 100; código ausente = 0
            $table->json('valores')->default(new Expression('(JSON_OBJECT())'));
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('porcentagens_vendedores');
    }
};
