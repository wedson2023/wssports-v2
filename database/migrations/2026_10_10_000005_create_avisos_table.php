<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Avisos da banca (antigo "popups"): imagem mostrada ao abrir o site, com "Fechar" e "Lido".
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('avisos', function (Blueprint $table) {
            $table->id();
            // texto alternativo da imagem e título do modal
            $table->string('titulo', 100)->nullable();
            // caminho no disco public (avisos/{sha1}.{ext})
            $table->string('imagem', 255);
            // URL http(s) aberta ao tocar na imagem
            $table->string('link', 500)->nullable();
            // período de exibição (UTC): sem início já vale; sem fim não acaba
            $table->dateTime('inicio_em')->nullable();
            $table->dateTime('fim_em')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('avisos');
    }
};
