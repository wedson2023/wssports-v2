<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('campeonatos', function (Blueprint $table) {
            $table->id();
            // referência do registro no provedor; nulo nos campeonatos cadastrados à mão
            $table->unsignedBigInteger('codigo_externo')->nullable()->unique();
            $table->string('nome', 150);
            $table->string('pais', 100);
            $table->string('bandeira')->nullable();
            $table->boolean('ativo')->default(true);
            $table->boolean('favorito')->default(false);
            $table->boolean('manual')->default(false);
            $table->timestamps();
            $table->softDeletes();

            // herança de regras quando o provedor troca o código de um campeonato
            $table->index(['nome', 'pais']);
            $table->index(['ativo', 'favorito']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campeonatos');
    }
};
