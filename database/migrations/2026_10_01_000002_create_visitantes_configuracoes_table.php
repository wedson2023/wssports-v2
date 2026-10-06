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
        // registro único com o que quem não está logado vê
        Schema::create('visitantes_configuracoes', function (Blueprint $table) {
            $table->id();
            // lista de nomes de esporte, sem chave estrangeira: o cadastro de esportes ainda não existe
            $table->json('esportes_permitidos')->default(new Expression("(JSON_ARRAY('FUTEBOL','HOQUEI NO GELO','BAISEBOL'))"));
            $table->boolean('apostar_outros_esportes')->default(true);
            $table->boolean('ao_vivo_habilitado')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visitantes_configuracoes');
    }
};
