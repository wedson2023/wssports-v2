<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configurações do Criar Aposta (spec 008) de cada vendedor.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('usuarios_configuracoes', function (Blueprint $table) {
            $table->boolean('criar_aposta_habilitado')->default(true)
                ->comment('Liga o Criar Aposta (vários palpites do mesmo jogo) nas apostas do vendedor');
            $table->decimal('desconto_criar_aposta', 5, 2)->default(0)
                ->comment('Percentual de desconto sobre a cotação do bloco Criar Aposta do vendedor (0 = sem desconto)');
            $table->unsignedTinyInteger('quantidade_minima_criar_aposta')->default(2)
                ->comment('Quantidade mínima de palpites em um bloco Criar Aposta do vendedor');
            $table->unsignedTinyInteger('quantidade_maxima_criar_aposta')->default(10)
                ->comment('Quantidade máxima de palpites em um bloco Criar Aposta do vendedor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('usuarios_configuracoes', function (Blueprint $table) {
            $table->dropColumn([
                'criar_aposta_habilitado',
                'desconto_criar_aposta',
                'quantidade_minima_criar_aposta',
                'quantidade_maxima_criar_aposta',
            ]);
        });
    }
};
