<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índice de data_inicio para a lista do painel por dia (spec 023): o índice atual começa por
 * situacao, e a tela Confrontos filtra o dia sem a situação.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('confrontos', function (Blueprint $table) {
            $table->index('data_inicio');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('confrontos', function (Blueprint $table) {
            $table->dropIndex(['data_inicio']);
        });
    }
};
