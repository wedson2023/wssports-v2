<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chave Pix do afiliado, uma por afiliado (spec 012, data-model §5, R-13).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('afiliados_meios_pagamento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('afiliados_id')->unique()
                ->comment('Afiliado dono da chave (uma por afiliado)')
                ->constrained('afiliados');
            $table->string('pix_tipo_chave', 20)
                ->comment('Tipo da chave Pix: CPF, CNPJ, E-mail, Telefone ou Chave aleatória');
            $table->string('pix_chave', 150)
                ->comment('Chave Pix normalizada conforme o tipo (só dígitos em CPF, CNPJ e Telefone)');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('afiliados_meios_pagamento');
    }
};
