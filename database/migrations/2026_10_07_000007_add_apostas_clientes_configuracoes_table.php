<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Regras de aposta do cliente acrescentadas pela spec 004.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clientes_configuracoes', function (Blueprint $table) {
            $table->boolean('apostar_jogadores')->default(true);
            $table->string('periodo_jogos', 20)->default('Depois de amanhã');
            $table->unsignedSmallInteger('delay_ao_vivo')->default(15);
            $table->unsignedInteger('multiplicador')->default(1000);
            $table->decimal('ganho_multiplo_palpites', 5, 2)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clientes_configuracoes', function (Blueprint $table) {
            $table->dropColumn(['apostar_jogadores', 'periodo_jogos', 'delay_ao_vivo', 'multiplicador', 'ganho_multiplo_palpites']);
        });
    }
};
