<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gols por jogador dos jogos do pré-jogo (spec 009, R-02), usados nos palpites de jogador.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('confrontos_gols', function (Blueprint $table) {
            $table->id();
            $table->foreignId('confrontos_id')
                ->comment('Jogo em que o gol foi marcado')
                ->constrained('confrontos');
            $table->unsignedTinyInteger('ordem')
                ->comment('Número do gol no jogo (1 = primeiro gol), lido de "1º Gol" do provedor');
            $table->string('nome', 150)->nullable()
                ->comment('Nome do jogador que marcou, como o provedor envia; nulo quando o provedor não informa o autor');
            $table->string('minuto', 10)->nullable()
                ->comment('Minuto do gol como o provedor envia, com acréscimos (ex.: 45+1)');
            $table->string('tempo_jogo', 20)->nullable()
                ->comment('Tempo de jogo do gol como o provedor envia (ex.: 1º tempo)');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['confrontos_id', 'ordem']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('confrontos_gols');
    }
};
