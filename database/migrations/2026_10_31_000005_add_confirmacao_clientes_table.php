<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Confirmação de cadastro por e-mail (spec 025, research R-12): desligada por padrão.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->boolean('confirmacao_pendente')->default(false)
                ->comment('Verdadeiro no cadastro público com a confirmação ligada, até confirmar o e-mail');
            $table->timestamp('email_confirmado_em')->nullable()
                ->comment('Quando o e-mail foi confirmado');
        });

        Schema::table('configuracoes', function (Blueprint $table) {
            $table->boolean('confirmacao_cadastro')->default(false)
                ->comment('Exige que o cliente confirme o e-mail para entrar na conta');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn(['confirmacao_pendente', 'email_confirmado_em']);
        });

        Schema::table('configuracoes', function (Blueprint $table) {
            $table->dropColumn('confirmacao_cadastro');
        });
    }
};
