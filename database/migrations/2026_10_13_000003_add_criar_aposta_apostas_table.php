<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Valores do Criar Aposta (spec 008) gravados na confirmação da aposta, que não mudam se a
 * configuração mudar depois.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('apostas', function (Blueprint $table) {
            $table->decimal('desconto_criar_aposta', 5, 2)->nullable()
                ->comment('Percentual de desconto do Criar Aposta usado na confirmação (0 a 100); nulo quando a aposta não tem bloco');
            $table->string('liquidacao_criar_aposta', 20)->nullable()
                ->comment('Regra de liquidação do Criar Aposta usada na confirmação: Recalcular ou Anular bloco; nulo quando a aposta não tem bloco');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('apostas', function (Blueprint $table) {
            $table->dropColumn(['desconto_criar_aposta', 'liquidacao_criar_aposta']);
        });
    }
};
