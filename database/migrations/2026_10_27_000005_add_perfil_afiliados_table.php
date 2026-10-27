<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perfil do afiliado na rede social: rede principal, link, seguidores e "sobre você" (spec 022,
 * data-model §1). Nulos para os afiliados que já existem.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('afiliados', function (Blueprint $table) {
            $table->string('rede_social', 20)->nullable()->after('endereco')
                ->comment('Rede social principal do afiliado: Instagram, TikTok, YouTube, Facebook, Kwai, X ou Outra');
            $table->string('link_perfil', 255)->nullable()->after('rede_social')
                ->comment('Endereço do perfil do afiliado na rede social principal (http ou https)');
            $table->unsignedInteger('quantidade_seguidores')->nullable()->after('link_perfil')
                ->comment('Quantidade de seguidores informada pelo afiliado (0 a 999.999.999)');
            $table->text('sobre')->nullable()->after('quantidade_seguidores')
                ->comment("Texto 'Sobre você' do afiliado, até 1.000 caracteres");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('afiliados', function (Blueprint $table) {
            $table->dropColumn(['rede_social', 'link_perfil', 'quantidade_seguidores', 'sobre']);
        });
    }
};
