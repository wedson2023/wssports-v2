<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configurações da apuração (spec 009, R-16): remoção automática dos jogos cancelados e janela de
 * pagamento dos prêmios.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('configuracoes', function (Blueprint $table) {
            $table->boolean('remover_confronto_cancelado')->default(true)
                ->comment('Cancela automaticamente os palpites de jogos cancelados ou adiados nas apostas ainda sem resultado');
            $table->unsignedSmallInteger('minutos_pagamento_premio')->default(5)
                ->comment('Minutos de espera entre a decisão da aposta e o crédito do prêmio (0 a 1440)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('configuracoes', function (Blueprint $table) {
            $table->dropColumn(['remover_confronto_cancelado', 'minutos_pagamento_premio']);
        });
    }
};
