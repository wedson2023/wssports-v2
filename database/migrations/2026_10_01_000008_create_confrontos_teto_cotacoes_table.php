<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // registro único com o teto de cotação por código, igual para todos os públicos
        Schema::create('confrontos_teto_cotacoes', function (Blueprint $table) {
            $table->id();
            // código ausente = sem teto
            $table->json('tetos')->default(new Expression('(JSON_OBJECT())'));
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('confrontos_teto_cotacoes');
    }
};
