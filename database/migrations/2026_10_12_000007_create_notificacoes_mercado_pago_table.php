<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Avisos (webhooks) recebidos do Mercado Pago, para auditoria e para não processar duas vezes
 * (spec 007, data-model §4).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notificacoes_mercado_pago', function (Blueprint $table) {
            $table->id();
            // type/topic do aviso (payment, chargebacks...)
            $table->string('tipo', 40);
            // data.id do aviso
            $table->string('recurso_id', 60);
            $table->string('request_id', 80)->nullable();
            $table->boolean('assinatura_valida');
            // Recebida, Processada, Ignorada ou Falhou
            $table->string('situacao', 20);
            $table->string('motivo', 255)->nullable();
            $table->timestamp('processada_em')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tipo', 'recurso_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notificacoes_mercado_pago');
    }
};
