<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bônus de indicação pagos ao indicador, um por grupo completo de indicações válidas (spec 007,
 * data-model §7).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('clientes_indicacoes_bonus', function (Blueprint $table) {
            $table->id();
            // indicador que recebeu o bônus
            $table->foreignId('clientes_id')->constrained('clientes');
            $table->foreignId('clientes_promocoes_id')->constrained('clientes_promocoes');
            // indicações válidas do grupo
            $table->unsignedSmallInteger('quantidade');
            $table->foreignId('clientes_transacoes_id')->constrained('clientes_transacoes');
            $table->foreignId('clientes_rollovers_id')->nullable()->constrained('clientes_rollovers');
            // estorno do primeiro depósito de um indicado do grupo
            $table->timestamp('cancelado_em')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes_indicacoes_bonus');
    }
};
