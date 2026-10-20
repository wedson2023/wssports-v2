<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices das somas do dia da Dashboard (spec 014, research R-08): apostas da banca toda, depósitos
 * aprovados, saques pagos e encerramentos decididos no dia.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('apostas', function (Blueprint $table) {
            $table->index(['situacao', 'created_at']);
        });

        Schema::table('depositos', function (Blueprint $table) {
            $table->index(['situacao', 'pago_em']);
        });

        Schema::table('saques', function (Blueprint $table) {
            $table->index(['situacao', 'pago_em']);
        });

        Schema::table('apostas_encerramentos', function (Blueprint $table) {
            $table->index(['situacao', 'decidido_em']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('apostas', function (Blueprint $table) {
            $table->dropIndex(['situacao', 'created_at']);
        });

        Schema::table('depositos', function (Blueprint $table) {
            $table->dropIndex(['situacao', 'pago_em']);
        });

        Schema::table('saques', function (Blueprint $table) {
            $table->dropIndex(['situacao', 'pago_em']);
        });

        Schema::table('apostas_encerramentos', function (Blueprint $table) {
            $table->dropIndex(['situacao', 'decidido_em']);
        });
    }
};
