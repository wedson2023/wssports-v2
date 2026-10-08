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
        Schema::create('apostas_palpites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apostas_id')->constrained('apostas');
            // sempre o confronto da grade; o palpite do ao vivo aponta também para o jogo ao vivo
            $table->foreignId('confrontos_id')->constrained('confrontos');
            $table->foreignId('confrontos_ao_vivo_id')->nullable()->constrained('confrontos_ao_vivo');
            $table->foreignId('campeonatos_id')->constrained('campeonatos');
            $table->string('esporte', 50);
            $table->string('codigo_cotacao', 10);
            $table->foreignId('confrontos_jogadores_id')->nullable()->constrained('confrontos_jogadores');
            // copiado do registro do jogador, nunca do pedido
            $table->string('jogador_tipo', 60)->nullable();
            $table->decimal('cotacao_vista', 8, 2);
            $table->decimal('cotacao_original', 8, 2);
            $table->decimal('cotacao_final', 8, 2);
            // fotografia do jogo ao vivo no envio e na decisão (R-02)
            $table->json('dados_ao_vivo_envio')->nullable();
            $table->json('dados_ao_vivo_decisao')->nullable();
            $table->string('situacao', 20)->default('Ativo');
            $table->timestamp('cancelado_em')->nullable();
            $table->foreignId('cancelado_por')->nullable()->constrained('usuarios');
            $table->timestamp('restaurado_em')->nullable();
            $table->foreignId('restaurado_por')->nullable()->constrained('usuarios');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['apostas_id', 'confrontos_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('apostas_palpites');
    }
};
