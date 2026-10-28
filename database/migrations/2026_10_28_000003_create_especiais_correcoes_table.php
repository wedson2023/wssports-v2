<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Histórico das correções de resultado das categorias especiais (spec 024).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('especiais_correcoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('especiais_id')->constrained('especiais')
                ->comment('Categoria corrigida');
            $table->json('vencedoras_anteriores')->comment('Ids das opções vencedoras antes da correção');
            $table->json('empatadas_anteriores')->comment('Ids das vencedoras empatadas antes da correção');
            $table->boolean('dividir_empate_anterior')->comment('Divisão por empate antes da correção');
            $table->json('vencedoras_novas')->comment('Ids das opções vencedoras depois da correção');
            $table->json('empatadas_novas')->comment('Ids das vencedoras empatadas depois da correção');
            $table->boolean('dividir_empate_novo')->comment('Divisão por empate depois da correção');
            $table->unsignedInteger('apostas_afetadas')->comment('Quantidade de apostas reapuradas');
            $table->foreignId('usuarios_id')->constrained('usuarios')->comment('Quem corrigiu');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('especiais_correcoes');
    }
};
