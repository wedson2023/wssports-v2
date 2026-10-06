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
        // jogos que não são exibidos no ao vivo para um alvo; usado só nas listagens, nunca nas cargas
        Schema::create('confrontos_ao_vivo_nao_permitidos', function (Blueprint $table) {
            $table->id();
            // jogo da grade (pré-jogo): permite esconder do ao vivo antes de o jogo começar
            $table->foreignId('confrontos_id')->constrained('confrontos');
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

            $table->unique(['confrontos_id', 'alvo', 'chave_usuario', 'chave_cliente'], 'confrontos_ao_vivo_nao_permitidos_unico');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('confrontos_ao_vivo_nao_permitidos');
    }
};
