<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Banners do carrossel da tela principal (antiga "slides").
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            // caminho no disco public (banners/{sha1}.jpg), ajustada para 1280x405
            $table->string('imagem', 255);
            // URL http(s) aberta ao tocar no banner
            $table->string('link', 500)->nullable();
            // menor aparece primeiro; empate pelo id
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['ativo', 'ordem']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
