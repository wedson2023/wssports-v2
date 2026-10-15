<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configurações gerais do encerramento de aposta (spec 010, FR-036, R-18).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('configuracoes', function (Blueprint $table) {
            $table->boolean('encerramento_habilitado')->default(true)
                ->comment('Liga o encerramento de aposta para os clientes');
            $table->boolean('encerramento_parcial_habilitado')->default(true)
                ->comment('Liga o encerramento parcial');
            $table->boolean('encerramento_automatico_habilitado')->default(true)
                ->comment('Liga o encerramento automático por valor-alvo');
            $table->boolean('encerramento_jogos_nao_iniciados')->default(true)
                ->comment('Permite encerrar antes de qualquer jogo da aposta começar');
            $table->decimal('margem_encerramento_pre_jogo', 5, 2)->default(7.00)
                ->comment('Margem da casa na oferta quando nenhum jogo em aberto começou, em %');
            $table->decimal('margem_encerramento_ao_vivo', 5, 2)->default(10.00)
                ->comment('Margem da casa na oferta quando algum jogo em aberto está ao vivo, em %');
            $table->decimal('valor_minimo_encerramento', 15, 2)->default(1.00)
                ->comment('Menor oferta (e menor parcial) aceita, em reais');
            $table->decimal('percentual_minimo_encerramento', 5, 2)->default(5.00)
                ->comment('Menor oferta aceita em % do valor apostado que continua valendo; vale o maior dos dois mínimos');
            $table->unsignedSmallInteger('segundos_validade_oferta')->default(8)
                ->comment('Validade da oferta na confirmação, em segundos');
            $table->unsignedSmallInteger('limite_encerramentos_parciais')->default(5)
                ->comment('Quantidade máxima de encerramentos parciais por aposta');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('configuracoes', function (Blueprint $table) {
            $table->dropColumn([
                'encerramento_habilitado',
                'encerramento_parcial_habilitado',
                'encerramento_automatico_habilitado',
                'encerramento_jogos_nao_iniciados',
                'margem_encerramento_pre_jogo',
                'margem_encerramento_ao_vivo',
                'valor_minimo_encerramento',
                'percentual_minimo_encerramento',
                'segundos_validade_oferta',
                'limite_encerramentos_parciais',
            ]);
        });
    }
};
