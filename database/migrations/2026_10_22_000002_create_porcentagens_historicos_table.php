<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Histórico das regras de cotação: uma linha por gravação de porcentagem, valor do jogo ou teto, e
 * por trancar/destrancar uma cotação (spec 016, FR-018a). A tela só lê.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('porcentagens_historicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuarios_id')
                ->comment('Autor da alteração')
                ->constrained('usuarios');
            $table->string('camada', 20)
                ->comment('Camada alterada: vendedores, clientes, campeonatos, confrontos ou tetos');
            $table->string('acao', 20)
                ->comment('Ação: alterar, trancar ou destrancar');
            $table->string('tipo', 10)->nullable()
                ->comment('pre_jogo ou ao_vivo (vendedores e clientes); nulo nas demais camadas');
            $table->string('alvo', 20)->nullable()
                ->comment('Alvo da regra de campeonato ou jogo: Todos, Clientes ou Vendedores; nulo nas demais');
            $table->foreignId('alvo_usuarios_id')->nullable()
                ->comment('Usuário da regra: dono das porcentagens (vendedores) ou dono do alvo Vendedores')
                ->constrained('usuarios');
            $table->foreignId('clientes_id')->nullable()
                ->comment('Cliente específico (camada clientes); nulo = regra geral dos clientes')
                ->constrained('clientes');
            $table->foreignId('campeonatos_id')->nullable()
                ->comment('Campeonato da regra (campeonatos) ou do cadeado')
                ->constrained('campeonatos');
            $table->foreignId('confrontos_id')->nullable()
                ->comment('Jogo da regra (confrontos)')
                ->constrained('confrontos');
            $table->json('alteracoes')
                ->comment('Códigos alterados: {codigo: {antes, depois}}, em % (porcentagens), diferença de cotação (jogo) ou cotação máxima (teto); null = sem ajuste');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['camada', 'alvo_usuarios_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('porcentagens_historicos');
    }
};
