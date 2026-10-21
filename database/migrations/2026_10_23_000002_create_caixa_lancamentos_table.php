<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lançamentos do caixa dos vendedores (spec 017, data-model): suprimentos e retiradas. Ficam abertos
 * até entrarem numa prestação de contas; só os abertos podem ser excluídos (soft delete).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('caixa_lancamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuarios_id')
                ->comment('Vendedor em cujo caixa o lançamento foi feito')
                ->constrained('usuarios');
            $table->string('tipo', 20)
                ->comment('Tipo do lançamento: Suprimento (dinheiro entregue ao vendedor, aumenta o que ele deve) ou Retirada (dinheiro recolhido, diminui o que ele deve)');
            $table->decimal('valor', 15, 2)
                ->comment('Valor do lançamento em reais (sempre positivo)');
            $table->dateTime('ocorrido_em')
                ->comment('Data e hora em que o dinheiro foi entregue ou recolhido');
            $table->string('observacao', 500)->nullable()
                ->comment('Observação opcional do lançamento');
            $table->foreignId('lancado_por')
                ->comment('Usuário do painel que registrou o lançamento')
                ->constrained('usuarios');
            $table->foreignId('caixa_prestacoes_contas_id')->nullable()
                ->comment('Prestação de contas em que o lançamento foi incluído; vazio enquanto está aberto')
                ->constrained('caixa_prestacoes_contas');
            $table->foreignId('excluido_por')->nullable()
                ->comment('Usuário que excluiu o lançamento')
                ->constrained('usuarios');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['usuarios_id', 'ocorrido_em']);
            $table->index(['usuarios_id', 'caixa_prestacoes_contas_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('caixa_lancamentos');
    }
};
