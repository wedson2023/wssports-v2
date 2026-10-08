<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Regras da aposta do visitante (código Pendente) acrescentadas pela spec 004.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('visitantes_configuracoes', function (Blueprint $table) {
            $table->boolean('apostar_jogadores')->default(true);
            $table->string('periodo_jogos', 20)->default('Depois de amanhã');
            $table->dateTime('data_travamento_sistema')->nullable();
            $table->unsignedSmallInteger('quantidade_minima_opcoes')->default(1);
            $table->unsignedSmallInteger('quantidade_maxima_opcoes')->default(20);
            $table->decimal('valor_minimo_aposta', 15, 2)->default(2.00);
            $table->decimal('valor_maximo_aposta', 15, 2)->default(1000.00);
            $table->decimal('odd_minima', 8, 2)->default(1.00);
            $table->decimal('premio_maximo', 15, 2)->default(5000.00);
            $table->unsignedInteger('multiplicador')->default(1000);
            $table->decimal('ganho_multiplo_palpites', 5, 2)->default(0);
            $table->unsignedSmallInteger('horas_validade_codigo')->default(48);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('visitantes_configuracoes', function (Blueprint $table) {
            $table->dropColumn([
                'apostar_jogadores', 'periodo_jogos', 'data_travamento_sistema', 'quantidade_minima_opcoes',
                'quantidade_maxima_opcoes', 'valor_minimo_aposta', 'valor_maximo_aposta', 'odd_minima',
                'premio_maximo', 'multiplicador', 'ganho_multiplo_palpites', 'horas_validade_codigo',
            ]);
        });
    }
};
