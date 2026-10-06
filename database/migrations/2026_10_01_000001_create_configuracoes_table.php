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
        // registro único com as configurações gerais do sistema (antiga "configs");
        // os valores iniciais ficam no padrão de cada coluna
        Schema::create('configuracoes', function (Blueprint $table) {
            $table->id();
            $table->boolean('somente_cassino')->default(false);
            $table->boolean('permitir_entrada_campeonatos')->default(true);
            $table->decimal('sorteio_ambas_marcam_minimo', 8, 2)->default(1.30);
            $table->decimal('sorteio_ambas_marcam_maximo', 8, 2)->default(1.50);
            $table->decimal('sorteio_ambas_nao_marcam_minimo', 8, 2)->default(1.45);
            $table->decimal('sorteio_ambas_nao_marcam_maximo', 8, 2)->default(1.55);
            $table->boolean('ao_vivo_habilitado')->default(true);
            $table->unsignedSmallInteger('segundos_trava_ao_vivo')->default(15);
            $table->unsignedSmallInteger('minutos_permanencia_ao_vivo')->default(5);
            // minuto limite e cotação máxima do ao vivo de visitantes, clientes e gestores
            $table->unsignedSmallInteger('minuto_limite_ao_vivo')->default(95);
            $table->decimal('cotacao_maxima_ao_vivo', 8, 2)->default(30.00);
            // trava geral do ao vivo, ligada e desligada só pela conferência
            $table->boolean('ao_vivo_travado')->default(false);
            $table->timestamp('ao_vivo_travado_em')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('configuracoes');
    }
};
