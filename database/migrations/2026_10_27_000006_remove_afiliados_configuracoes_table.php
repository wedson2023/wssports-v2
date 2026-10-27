<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sai a regra de comissão padrão do cadastro público de afiliados: o ganho passa a ser definido por
 * quem gerencia, no cadastro pelo painel e na aprovação (spec 022, R-05).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('configuracoes', function (Blueprint $table) {
            $table->dropColumn(['afiliados_tipo_ganho_padrao', 'afiliados_percentual_padrao']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('configuracoes', function (Blueprint $table) {
            $table->string('afiliados_tipo_ganho_padrao', 20)->default('Saldo líquido')
                ->comment('Tipo de ganho dado ao afiliado do cadastro público: Saldo líquido, Qualquer depósito ou Primeiro depósito');
            $table->decimal('afiliados_percentual_padrao', 5, 2)->default(0)
                ->comment('Percentual de comissão dado ao afiliado do cadastro público, em %');
        });
    }
};
