<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configurações gerais do cassino (spec 011, FR-031, R-10, R-21).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('configuracoes', function (Blueprint $table) {
            $table->boolean('cassino_habilitado')->default(false)
                ->comment('Cassino disponível no site (área /cassino, atalho e card Bônus cassino)');
            $table->boolean('cassino_demo_habilitado')->default(false)
                ->comment('Modo demo liberado no hall do provedor (botão Demo)');
            $table->date('data_prestacao_contas_cassino')->nullable()
                ->comment('Data da última prestação de contas do cassino; o relatório mostra o GGR desde ela');
            $table->unsignedSmallInteger('dias_registro_chamadas_cassino')->default(15)
                ->comment('Dias em que as chamadas aceitas do provedor ficam guardadas, em dias');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('configuracoes', function (Blueprint $table) {
            $table->dropColumn([
                'cassino_habilitado',
                'cassino_demo_habilitado',
                'data_prestacao_contas_cassino',
                'dias_registro_chamadas_cassino',
            ]);
        });
    }
};
