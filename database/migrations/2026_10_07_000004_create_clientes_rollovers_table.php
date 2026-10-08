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
        // valor que o cliente precisa apostar por causa de um crédito (depósito ou bônus)
        Schema::create('clientes_rollovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clientes_id')->constrained('clientes');
            $table->string('tipo', 20);
            $table->string('carteira', 30);
            $table->foreignId('clientes_transacoes_id')->constrained('clientes_transacoes');
            $table->foreignId('clientes_promocoes_id')->nullable()->constrained('clientes_promocoes');
            $table->decimal('valor_creditado', 15, 2);
            $table->unsignedSmallInteger('vezes');
            $table->decimal('valor_exigido', 15, 2);
            $table->decimal('valor_apostado', 15, 2)->default(0);
            // regras de uso do bônus gravadas no recebimento
            $table->decimal('valor_minimo_aposta', 15, 2)->nullable();
            $table->decimal('valor_maximo_aposta', 15, 2)->nullable();
            $table->decimal('odd_minima_aposta_simples', 8, 2)->nullable();
            $table->decimal('odd_minima_aposta_multipla', 8, 2)->nullable();
            $table->timestamp('cumprido_em')->nullable();
            $table->timestamp('cancelado_em')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['clientes_id', 'tipo', 'carteira', 'cumprido_em', 'cancelado_em'], 'clientes_rollovers_pendentes_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes_rollovers');
    }
};
