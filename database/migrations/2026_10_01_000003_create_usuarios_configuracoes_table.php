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
        // uma linha por vendedor (o único que aposta); sem tabela padrão: valem os padrões das colunas
        Schema::create('usuarios_configuracoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuarios_id')->unique()->constrained('usuarios');
            // lista de nomes de esporte, sem chave estrangeira: o cadastro de esportes ainda não existe
            $table->json('esportes_permitidos')->default(new Expression("(JSON_ARRAY('FUTEBOL','HOQUEI NO GELO','BAISEBOL'))"));
            $table->boolean('apostar_outros_esportes')->default(true);
            $table->boolean('ao_vivo_habilitado')->default(true);
            $table->unsignedSmallInteger('minuto_limite_ao_vivo')->default(95);
            $table->decimal('cotacao_maxima_ao_vivo', 8, 2)->default(30.00);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usuarios_configuracoes');
    }
};
