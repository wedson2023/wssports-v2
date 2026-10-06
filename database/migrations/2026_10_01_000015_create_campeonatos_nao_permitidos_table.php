<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // campeonatos que não são exibidos para um alvo (antiga campeonatos_gerencias); usado só nas listagens, nunca nas cargas
        Schema::create('campeonatos_nao_permitidos', function (Blueprint $table) {
            $table->id();
            // campeonato escondido
            $table->foreignId('campeonatos_id')->constrained('campeonatos');
            // Clientes, Vendedores ou Todos
            $table->string('alvo', 20);
            // dono, só no alvo Vendedores (vale para ele e para quem está abaixo dele)
            $table->foreignId('usuarios_id')->nullable()->constrained('usuarios');
            // só no alvo Clientes: nulo = visitantes e todos os clientes; preenchido = só aquele cliente
            $table->foreignId('clientes_id')->nullable()->constrained('clientes');
            // o MySQL não considera dois nulos iguais num índice único: as colunas geradas evitam registros repetidos
            $table->unsignedBigInteger('chave_usuario')->storedAs('COALESCE(usuarios_id, 0)');
            $table->unsignedBigInteger('chave_cliente')->storedAs('COALESCE(clientes_id, 0)');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['campeonatos_id', 'alvo', 'chave_usuario', 'chave_cliente'], 'campeonatos_nao_permitidos_unico');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campeonatos_nao_permitidos');
    }
};
