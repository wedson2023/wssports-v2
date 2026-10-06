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
        // porcentagens de ajuste do ao vivo para os clientes do site
        Schema::create('porcentagens_clientes_ao_vivo', function (Blueprint $table) {
            $table->id();
            // nulo = regra geral (visitantes e todos os clientes); preenchido = regra de um cliente, somada à geral
            $table->foreignId('clientes_id')->nullable()->constrained('clientes');
            // o MySQL não considera dois nulos iguais num índice único: a coluna gerada garante uma só regra geral
            $table->unsignedBigInteger('chave_cliente')->storedAs('COALESCE(clientes_id, 0)')->unique();
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
        Schema::dropIfExists('porcentagens_clientes_ao_vivo');
    }
};
