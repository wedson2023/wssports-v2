<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quanto a aposta de uma rodada do cassino somou num rollover do cliente (spec 011, R-18).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cassinos_rodadas_rollovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cassinos_rodadas_id')
                ->comment('Rodada que somou no rollover')
                ->constrained('cassinos_rodadas');
            $table->foreignId('clientes_rollovers_id')
                ->comment('Rollover que recebeu o valor')
                ->constrained('clientes_rollovers');
            $table->decimal('valor', 15, 2)
                ->comment('Valor somado no rollover, em reais');
            $table->timestamp('desfeito_em')->nullable()
                ->comment('Quando o valor foi retirado (cancelamento ou reembolso da rodada)');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cassinos_rodadas_rollovers');
    }
};
