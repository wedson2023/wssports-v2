<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Limite de valor apostado por confronto, separado no pré-jogo e no ao vivo. As cargas do
 * provedor não alteram a coluna (o upsert não a inclui).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('confrontos', function (Blueprint $table) {
            $table->decimal('limite_valor_apostado', 15, 2)->default(50000.00);
        });

        Schema::table('confrontos_ao_vivo', function (Blueprint $table) {
            $table->decimal('limite_valor_apostado', 15, 2)->default(5000.00);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('confrontos', function (Blueprint $table) {
            $table->dropColumn('limite_valor_apostado');
        });

        Schema::table('confrontos_ao_vivo', function (Blueprint $table) {
            $table->dropColumn('limite_valor_apostado');
        });
    }
};
