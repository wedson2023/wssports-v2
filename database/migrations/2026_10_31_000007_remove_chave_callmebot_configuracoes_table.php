<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O CallMeBot sai do sistema (spec 025, FR-015). Exclusão física justificada em research R-07: é um
 * segredo sem uso, e a spec pede que seja apagado. O down recria a coluna vazia.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('configuracoes', function (Blueprint $table) {
            $table->dropColumn('chave_callmebot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('configuracoes', function (Blueprint $table) {
            $table->text('chave_callmebot')->nullable()
                ->comment('Chave do CallMeBot do telefone do Admin, criptografada');
        });
    }
};
