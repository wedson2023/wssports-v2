<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aviso de combinação de risco nova no WhatsApp do Admin (spec 014, FR-048, R-13).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('configuracoes', function (Blueprint $table) {
            $table->boolean('aviso_combinacao_whatsapp')->default(false)
                ->comment('Liga o aviso de combinação de risco nova no WhatsApp do Admin');
            $table->string('telefone_whatsapp_admin', 15)->nullable()
                ->comment('Telefone do WhatsApp do Admin, só dígitos com DDI (ex.: 5582999999999)');
            $table->text('chave_callmebot')->nullable()
                ->comment('Chave do CallMeBot do telefone do Admin, criptografada');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('configuracoes', function (Blueprint $table) {
            $table->dropColumn(['aviso_combinacao_whatsapp', 'telefone_whatsapp_admin', 'chave_callmebot']);
        });
    }
};
