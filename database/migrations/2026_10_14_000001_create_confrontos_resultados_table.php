<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resultado dos jogos do pré-jogo (spec 009, R-01): fica fora de confrontos porque a carga dos
 * confrontos regrava a situação a cada 5 minutos.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('confrontos_resultados', function (Blueprint $table) {
            $table->id();
            // o comentário vem antes do constrained(), que devolve a definição da chave estrangeira
            $table->foreignId('confrontos_id')->unique()
                ->comment('Jogo da grade a que o resultado pertence')
                ->constrained('confrontos');
            $table->unsignedSmallInteger('placar_casa')->nullable()
                ->comment('Gols do time da casa no jogo inteiro; nulo enquanto o provedor não enviar');
            $table->unsignedSmallInteger('placar_fora')->nullable()
                ->comment('Gols do time de fora no jogo inteiro; nulo enquanto o provedor não enviar');
            $table->unsignedSmallInteger('gols_primeiro_tempo_casa')->nullable()
                ->comment('Gols do time da casa só no 1º tempo');
            $table->unsignedSmallInteger('gols_primeiro_tempo_fora')->nullable()
                ->comment('Gols do time de fora só no 1º tempo');
            $table->unsignedSmallInteger('gols_segundo_tempo_casa')->nullable()
                ->comment('Gols do time da casa só no 2º tempo');
            $table->unsignedSmallInteger('gols_segundo_tempo_fora')->nullable()
                ->comment('Gols do time de fora só no 2º tempo');
            $table->unsignedSmallInteger('escanteios_casa')->nullable()
                ->comment('Escanteios do time da casa; nulo sem dado (mercados de escanteio ficam Aguardando)');
            $table->unsignedSmallInteger('escanteios_fora')->nullable()
                ->comment('Escanteios do time de fora; nulo sem dado');
            $table->string('situacao', 20)
                ->comment('Situação do jogo na carga de resultados (Aguardando, Encerrado, Cancelado, Adiado, Bloqueado); só Encerrado é apurado');
            $table->char('assinatura', 64)
                ->comment('SHA-256 do resultado e dos gols recebidos, para só reapurar quando algo mudar');
            $table->timestamp('recebido_em')
                ->comment('Última vez em que o resultado mudou na carga');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('confrontos_resultados');
    }
};
