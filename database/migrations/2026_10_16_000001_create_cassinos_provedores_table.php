<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Provedores reais dos jogos do cassino (spec 011, R-02).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cassinos_provedores', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 60)->unique()
                ->comment('Código do provedor: title do provedor normalizado (minúsculas, sem espaço, _ ou -) e unificado pelos apelidos de config/cassino.php');
            $table->string('nome', 100)
                ->comment('Nome exibido no site e no painel (editável)');
            $table->string('imagem', 255)->nullable()
                ->comment('Caminho da logo do provedor na barra de provedores; nulo mostra o nome');
            $table->boolean('ativo')->default(false)
                ->comment('Provedor visível no site; provedor novo entra desativado');
            $table->unsignedSmallInteger('ordem')->default(100)
                ->comment('Ordem na barra e no menu de provedores (menor primeiro)');
            $table->decimal('porcentagem_comissao', 5, 2)->default(0)
                ->comment('Porcentagem do GGR usada no cálculo da comissão do relatório, em %');
            $table->json('titulos_provedor')
                ->comment('Grafias do title recebidas do provedor para este código (conferência)');
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
        Schema::dropIfExists('cassinos_provedores');
    }
};
