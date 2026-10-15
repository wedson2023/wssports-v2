<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motivo do cancelamento do palpite pelo sistema (spec 009, R-07): a apuração só restaura os que
 * ela mesma devolveu por empate.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('apostas_palpites', function (Blueprint $table) {
            $table->string('motivo_cancelamento', 30)->nullable()->after('restaurado_por')
                ->comment('Por que o sistema cancelou o palpite: Jogo cancelado ou Empate anula aposta; nulo no cancelamento pelo painel ou de categoria especial');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('apostas_palpites', function (Blueprint $table) {
            $table->dropColumn('motivo_cancelamento');
        });
    }
};
