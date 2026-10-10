<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Categorias especiais (antiga "modalidades"): aposta de vencedor com cotação fixa por opção.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('especiais', function (Blueprint $table) {
            $table->id();
            // único entre as não removidas (conferido na validação)
            $table->string('nome', 150);
            // até quando aceita palpite (UTC; antigo "horario")
            $table->dateTime('data_limite');
            $table->string('situacao', 20)->default('Aguardando');
            // opção vencedora, preenchida ao encerrar (chave estrangeira criada com a tabela de opções)
            $table->unsignedBigInteger('especiais_opcoes_id_vencedora')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamp('encerrado_em')->nullable();
            $table->foreignId('encerrado_por')->nullable()->constrained('usuarios');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['situacao', 'ativo', 'data_limite']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('especiais');
    }
};
