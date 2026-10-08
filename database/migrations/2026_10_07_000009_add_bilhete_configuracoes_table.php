<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dados do comprovante: nome do sistema e mensagem do bilhete das apostas sem vendedor.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('configuracoes', function (Blueprint $table) {
            $table->string('nome_sistema', 100)->default('WSSports');
            $table->string('mensagem_bilhete', 500)->default('BOA SORTE!');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('configuracoes', function (Blueprint $table) {
            $table->dropColumn(['nome_sistema', 'mensagem_bilhete']);
        });
    }
};
