<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opções de cada categoria especial (antiga "modalidades_especiais"), com a cotação fixa.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('especiais_opcoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('especiais_id')->constrained('especiais');
            $table->string('nome', 150);
            // cotação fixa cadastrada pelo administrador (mínimo 1,01)
            $table->decimal('cotacao', 8, 2);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['especiais_id', 'nome', 'deleted_at']);
        });

        Schema::table('especiais', function (Blueprint $table) {
            $table->foreign('especiais_opcoes_id_vencedora')->references('id')->on('especiais_opcoes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('especiais', function (Blueprint $table) {
            $table->dropForeign(['especiais_opcoes_id_vencedora']);
        });

        Schema::dropIfExists('especiais_opcoes');
    }
};
