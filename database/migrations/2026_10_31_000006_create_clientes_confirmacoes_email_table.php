<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links de confirmação de e-mail do cliente (spec 025, data-model): uso único e 24 horas de validade.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('clientes_confirmacoes_email', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clientes_id')->constrained('clientes')->comment('Cliente que confirma o e-mail');
            $table->char('token', 64)->unique()->comment('Hash SHA-256 do token enviado; o token em si não é guardado');
            $table->timestamp('expira_em')->comment('Fim da validade, 24 horas após a criação');
            $table->timestamp('usado_em')->nullable()->comment('Quando o link foi usado');
            $table->timestamp('invalidado_em')->nullable()->comment('Quando um novo link invalidou este');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes_confirmacoes_email');
    }
};
