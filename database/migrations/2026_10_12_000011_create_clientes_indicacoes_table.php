<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indicações entre clientes (spec 007, data-model §6): válida quando o indicado faz o primeiro
 * depósito aprovado.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('clientes_indicacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indicador_clientes_id')->constrained('clientes');
            $table->foreignId('indicado_clientes_id')->unique()->constrained('clientes');
            // primeiro depósito aprovado do indicado; nulo = pendente de depósito
            $table->timestamp('validada_em')->nullable();
            $table->foreignId('depositos_id')->nullable()->constrained('depositos');
            // grupo pago em que a indicação contou
            $table->foreignId('clientes_indicacoes_bonus_id')->nullable()->constrained('clientes_indicacoes_bonus');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['indicador_clientes_id', 'validada_em', 'clientes_indicacoes_bonus_id'], 'clientes_indicacoes_contagem_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes_indicacoes');
    }
};
