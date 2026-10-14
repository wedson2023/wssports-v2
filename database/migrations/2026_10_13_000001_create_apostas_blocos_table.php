<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blocos Criar Aposta (spec 008): vários palpites de um mesmo jogo do pré-jogo com uma cotação
 * combinada, que entram na aposta como um jogo.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('apostas_blocos', function (Blueprint $table) {
            $table->id();
            // o comentário vem antes do constrained(), que devolve a definição da chave estrangeira
            $table->foreignId('apostas_id')
                ->comment('Aposta a que o bloco Criar Aposta pertence')
                ->constrained('apostas');
            $table->foreignId('confrontos_id')
                ->comment('Jogo do pré-jogo cujos palpites formam o bloco')
                ->constrained('confrontos');
            $table->decimal('cotacao_vista', 8, 2)
                ->comment('Cotação do bloco que o apostador viu no cupom, com o desconto');
            $table->decimal('cotacao', 8, 2)
                ->comment('Cotação atual do bloco: produto das cotações dos palpites ativos × (1 − desconto ÷ 100), truncada em 2 casas');
            $table->string('situacao', 20)->default('Ativo')
                ->comment('Situação do bloco: Ativo ou Anulado (anulado conta como 1,00 na cotação total)');
            $table->timestamps();
            $table->softDeletes();

            // um bloco por jogo na aposta
            $table->unique(['apostas_id', 'confrontos_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('apostas_blocos');
    }
};
