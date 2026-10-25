<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estornos de bônus por cliente (spec 020, FR-026 a FR-028, data-model §1.1): um registro por bônus
 * estornado, individual, geral ou automático; o crédito é único para nunca estornar duas vezes.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('clientes_promocoes_estornos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clientes_promocoes_id')
                ->comment('Promoção do bônus estornado')
                ->constrained('clientes_promocoes');
            $table->foreignId('clientes_id')
                ->comment('Cliente que recebeu o bônus')
                ->constrained('clientes');
            $table->foreignId('clientes_transacoes_id')
                ->unique()
                ->comment('Crédito do bônus (origem Promoção) que foi estornado; único para não estornar duas vezes')
                ->constrained('clientes_transacoes');
            $table->foreignId('clientes_rollovers_id')->nullable()
                ->comment('Rollover do bônus cancelado pelo estorno (nulo quando a promoção não exigia rollover)')
                ->constrained('clientes_rollovers');
            $table->string('tipo', 20)
                ->comment('Como o bônus foi estornado: Individual, Geral ou Automático');
            $table->string('motivo', 255)
                ->comment('Motivo informado no estorno (no automático, o evento que o causou)');
            $table->foreignId('usuarios_id')->nullable()
                ->comment('Usuário do painel que estornou (nulo no automático)')
                ->constrained('usuarios');
            $table->decimal('valor_retirado', 15, 2)->default(0)
                ->comment('Valor retirado do saldo promocional da modalidade (R$)');
            $table->foreignId('debito_clientes_transacoes_id')->nullable()
                ->comment('Transação de débito do estorno (nula quando não havia saldo para retirar)')
                // nome curto: o padrão passa do limite de 64 caracteres do MySQL
                ->constrained('clientes_transacoes', indexName: 'clientes_promocoes_estornos_debito_foreign');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['clientes_promocoes_id', 'tipo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes_promocoes_estornos');
    }
};
