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
        Schema::create('clientes_meios_pagamento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clientes_id')->constrained('clientes');
            $table->string('tipo', 30);
            $table->boolean('principal')->default(false);
            // dados do Pix (nulos na transferência bancária)
            $table->string('pix_nome_titular', 150)->nullable();
            $table->string('pix_tipo_chave', 20)->nullable();
            $table->string('pix_chave', 150)->nullable();
            // dados da transferência bancária (nulos no Pix)
            $table->string('banco_codigo', 3)->nullable();
            $table->string('banco_nome', 100)->nullable();
            $table->string('agencia', 10)->nullable();
            $table->string('conta', 20)->nullable();
            $table->string('conta_digito', 2)->nullable();
            $table->string('conta_tipo', 20)->nullable();
            $table->string('titular_nome', 150)->nullable();
            $table->string('titular_documento', 14)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['clientes_id', 'principal']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes_meios_pagamento');
    }
};
