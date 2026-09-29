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
        Schema::create('clientes_promocoes', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 150);
            $table->text('descricao')->nullable();
            $table->string('modalidade', 20);
            $table->string('categoria', 30);
            $table->string('tipo_ganho', 20);
            $table->decimal('valor', 15, 2);
            $table->unsignedSmallInteger('rollover')->default(0);
            // regras de uso do bônus (aplicadas pelas specs de apostas, depósitos e saques)
            $table->decimal('valor_minimo_aposta', 15, 2);
            $table->decimal('valor_maximo_aposta', 15, 2);
            $table->decimal('valor_maximo_deposito', 15, 2)->nullable();
            $table->decimal('valor_maximo_conversao', 15, 2);
            $table->decimal('odd_minima_aposta_simples', 8, 2);
            $table->decimal('odd_minima_aposta_multipla', 8, 2);
            $table->dateTime('data_inicio');
            $table->dateTime('data_fim')->nullable();
            $table->boolean('ativa')->default(true);
            // dados do estorno (rollback) da promoção
            $table->string('estorno_situacao', 20)->nullable();
            $table->string('estorno_motivo')->nullable();
            $table->foreignId('estorno_usuarios_id')->nullable()->constrained('usuarios');
            $table->dateTime('estorno_iniciado_em')->nullable();
            $table->dateTime('estorno_concluido_em')->nullable();
            $table->unsignedInteger('estorno_total_clientes')->default(0);
            $table->unsignedInteger('estorno_clientes_processados')->default(0);
            $table->decimal('estorno_valor_total', 15, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['categoria', 'modalidade', 'ativa']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes_promocoes');
    }
};
