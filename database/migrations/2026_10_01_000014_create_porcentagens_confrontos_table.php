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
        // valor fixo somado à cotação de um confronto do pré-jogo, por alvo
        Schema::create('porcentagens_confrontos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('confrontos_id')->constrained('confrontos');
            // Clientes, Vendedores ou Todos
            $table->string('alvo', 20);
            // dono da regra, só no alvo Vendedores (vale para ele e para quem está abaixo dele)
            $table->foreignId('usuarios_id')->nullable()->constrained('usuarios');
            // o MySQL não considera dois nulos iguais num índice único: a coluna gerada evita regras repetidas
            $table->unsignedBigInteger('chave_usuario')->storedAs('COALESCE(usuarios_id, 0)');
            // valor fixo por código, somado à cotação (pode ser negativo); código ausente = 0
            $table->json('valores')->default(new Expression('(JSON_OBJECT())'));
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['confrontos_id', 'alvo', 'chave_usuario'], 'porcentagens_confrontos_unico');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('porcentagens_confrontos');
    }
};
