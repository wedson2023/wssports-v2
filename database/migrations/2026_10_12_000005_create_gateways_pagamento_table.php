<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vínculo da conta da banca no gateway de pagamento (spec 007, US8): um único vínculo ativo;
 * os anteriores ficam removidos (soft delete) como histórico.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('gateways_pagamento', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 30);
            $table->string('situacao', 20);
            // gravados criptografados pelo model (cast encrypted)
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            // user_id da conta vinculada no Mercado Pago
            $table->string('conta_id', 40);
            // validade do access token
            $table->timestamp('expira_em');
            $table->timestamp('vinculado_em');
            $table->foreignId('vinculado_por')->constrained('usuarios');
            $table->timestamp('renovado_em')->nullable();
            $table->string('falha_renovacao', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gateways_pagamento');
    }
};
