<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Percentual da comissão do gerente (spec 017, R-05): substitui o dado fake da Dashboard (spec 014).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('usuarios_configuracoes', function (Blueprint $table) {
            $table->decimal('percentual_comissao_gerente', 5, 2)->default(0)
                ->comment('Percentual (%) da comissão do gerente sobre o líquido dos vendedores dele no período (zero quando o líquido for ≤ 0); usado só na configuração de um Gerente');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('usuarios_configuracoes', function (Blueprint $table) {
            $table->dropColumn('percentual_comissao_gerente');
        });
    }
};
