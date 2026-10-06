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
        // jogos do pré-jogo
        Schema::create('confrontos', function (Blueprint $table) {
            $table->id();
            // referência do registro no provedor; nulo nos confrontos cadastrados à mão
            $table->unsignedBigInteger('codigo_externo')->nullable()->unique();
            $table->foreignId('campeonatos_id')->constrained('campeonatos');
            $table->string('time_casa', 150);
            $table->string('escudo_casa')->nullable();
            $table->string('time_fora', 150);
            $table->string('escudo_fora')->nullable();
            // nome do esporte como o provedor envia, sem chave estrangeira: o cadastro de esportes ainda não existe
            $table->string('esporte', 50);
            $table->string('situacao', 20)->default('Aguardando');
            $table->dateTime('data_inicio');
            $table->boolean('ativo')->default(true);
            $table->boolean('manual')->default(false);
            // marcam os valores de "ambas marcam" (odd4) e "ambas não marcam" (odd7) sorteados pelo sistema
            $table->boolean('odd4_sorteada')->default(false);
            $table->boolean('odd7_sorteada')->default(false);
            $table->unsignedSmallInteger('quantidade_cotacoes')->default(0);
            // as 323 cotações numa única coluna, só com os códigos diferentes de zero
            $table->json('cotacoes');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['situacao', 'esporte', 'data_inicio']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('confrontos');
    }
};
