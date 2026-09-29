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
        // registro único com os valores que cada cliente novo recebe
        Schema::create('clientes_configuracoes_padrao', function (Blueprint $table) {
            $table->id();
            $table->boolean('realizar_aposta');
            $table->boolean('apostar_ao_vivo');
            $table->boolean('apostar_outros_esportes');
            $table->boolean('cancelar_aposta');
            $table->boolean('aceita_promocao');
            $table->boolean('bloquear_saque');
            $table->unsignedSmallInteger('quantidade_minima_opcoes');
            $table->unsignedSmallInteger('quantidade_maxima_opcoes');
            $table->decimal('valor_minimo_aposta', 15, 2);
            $table->decimal('valor_maximo_aposta', 15, 2);
            $table->decimal('premio_maximo', 15, 2);
            $table->decimal('valor_maximo_diario', 15, 2);
            $table->decimal('valor_maximo_saque_diario', 15, 2);
            $table->unsignedSmallInteger('quantidade_maxima_saques_diaria');
            $table->decimal('odd_minima', 8, 2);
            $table->decimal('odd_maxima', 8, 2);
            // lista de nomes de esporte, sem chave estrangeira: o cadastro de esportes ainda não existe
            $table->json('esportes_permitidos');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes_configuracoes_padrao');
    }
};
