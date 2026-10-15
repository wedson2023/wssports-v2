<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gol, gol anulado, intervalo e fim de jogo detectados nas cargas (spec 010, FR-042).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('confrontos_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('confrontos_id')
                ->comment('Jogo da grade onde o evento aconteceu')
                ->constrained('confrontos');
            $table->foreignId('confrontos_ao_vivo_id')->nullable()
                ->comment('Jogo do ao vivo de onde o evento foi detectado; nulo no fim de jogo vindo do resultado')
                ->constrained('confrontos_ao_vivo');
            $table->string('tipo', 20)
                ->comment('Tipo: Gol, Gol anulado, Intervalo ou Fim de jogo');
            $table->unsignedSmallInteger('placar_casa')
                ->comment('Gols do time da casa depois do evento');
            $table->unsignedSmallInteger('placar_fora')
                ->comment('Gols do time de fora depois do evento');
            $table->unsignedSmallInteger('minuto')->nullable()
                ->comment('Minuto do jogo no evento; nulo no fim de jogo');
            $table->string('chave', 40)->nullable()->unique()
                ->comment('Garante uma vez por jogo (Intervalo:{id}, Fim de jogo:{id}); nula nos gols');
            $table->timestamp('notificado_em')->nullable()
                ->comment('Quando os avisos do evento foram despachados');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['confrontos_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('confrontos_eventos');
    }
};
