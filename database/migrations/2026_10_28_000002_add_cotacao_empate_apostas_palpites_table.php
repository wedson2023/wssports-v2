<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cotação aplicada ao palpite especial pela divisão por empate (spec 024, R-06), guardada ao lado
 * da cotação original para o comprovante e para a correção de resultado poder desfazê-la.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('apostas_palpites', function (Blueprint $table) {
            $table->decimal('cotacao_empate', 8, 2)->nullable()->after('cotacao_final')
                ->comment('Cotação aplicada ao palpite especial pela divisão por empate (dead heat); nula quando não houve divisão');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('apostas_palpites', function (Blueprint $table) {
            $table->dropColumn('cotacao_empate');
        });
    }
};
