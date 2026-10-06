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
        // jogos em andamento, separados do pré-jogo
        Schema::create('confrontos_ao_vivo', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('codigo_externo')->unique();
            // confronto da grade com o mesmo código externo; o ao vivo só exibe jogos que existem no pré-jogo
            $table->foreignId('confrontos_id')->nullable()->constrained('confrontos');
            $table->foreignId('campeonatos_id')->constrained('campeonatos');
            $table->string('time_casa', 150);
            $table->string('escudo_casa')->nullable();
            $table->string('time_fora', 150);
            $table->string('escudo_fora')->nullable();
            $table->string('esporte', 50);
            $table->dateTime('data_inicio');
            $table->unsignedSmallInteger('placar_casa')->default(0);
            $table->unsignedSmallInteger('placar_fora')->default(0);
            $table->unsignedSmallInteger('gols_primeiro_tempo_casa')->nullable();
            $table->unsignedSmallInteger('gols_primeiro_tempo_fora')->nullable();
            $table->unsignedSmallInteger('gols_segundo_tempo_casa')->nullable();
            $table->unsignedSmallInteger('gols_segundo_tempo_fora')->nullable();
            $table->unsignedSmallInteger('escanteios_casa')->nullable();
            $table->unsignedSmallInteger('escanteios_fora')->nullable();
            $table->unsignedSmallInteger('minuto')->default(0);
            $table->string('cronometro', 10)->nullable();
            $table->string('situacao', 20);
            $table->json('cotacoes');
            $table->unsignedSmallInteger('quantidade_cotacoes')->default(0);
            // base da trava por tempo e da permanência na listagem
            $table->timestamp('ultima_atualizacao_em');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['situacao', 'ultima_atualizacao_em']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('confrontos_ao_vivo');
    }
};
