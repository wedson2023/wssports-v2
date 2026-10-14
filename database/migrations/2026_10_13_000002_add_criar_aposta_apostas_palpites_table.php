<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Palpite dentro de um bloco Criar Aposta (spec 008): o jogo pode repetir na aposta quando os
 * palpites formam um bloco, por isso o único (aposta, confronto) vira um índice simples e a regra
 * passa para os serviços de aposta.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('apostas_palpites', function (Blueprint $table) {
            $table->foreignId('apostas_blocos_id')->nullable()->after('apostas_id')
                ->comment('Bloco Criar Aposta do palpite; nulo no palpite simples')
                ->constrained('apostas_blocos');
            // o índice simples entra antes de sair o único, para a chave estrangeira de apostas_id
            $table->index(['apostas_id', 'confrontos_id']);
        });

        Schema::table('apostas_palpites', function (Blueprint $table) {
            $table->dropUnique(['apostas_id', 'confrontos_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('apostas_palpites', function (Blueprint $table) {
            $table->unique(['apostas_id', 'confrontos_id']);
        });

        Schema::table('apostas_palpites', function (Blueprint $table) {
            $table->dropIndex(['apostas_id', 'confrontos_id']);
            $table->dropConstrainedForeignId('apostas_blocos_id');
        });
    }
};
