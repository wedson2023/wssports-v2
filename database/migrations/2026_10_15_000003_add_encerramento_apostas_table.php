<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Encerramento de aposta (spec 010, R-07 e R-08): a parte da aposta que continua valendo e o
 * resultado Encerrada.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('apostas', function (Blueprint $table) {
            $table->decimal('fracao_restante', 11, 10)->default(1)
                ->comment('Parte da aposta que continua valendo depois de encerramentos parciais (1 = inteira, 0 = encerrada); multiplica o total a pagar');
            $table->string('resultado', 20)->default('Aguardando')
                ->comment('Resultado da aposta: Aguardando, Vencedor, Perdedor ou Encerrada')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('apostas', function (Blueprint $table) {
            $table->dropColumn('fracao_restante');
            $table->string('resultado', 20)->default('Aguardando')->comment('')->change();
        });
    }
};
