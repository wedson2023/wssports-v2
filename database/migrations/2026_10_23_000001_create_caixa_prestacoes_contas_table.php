<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prestações de contas do caixa dos vendedores (spec 017, data-model, R-04): cada uma fecha o caixa
 * do vendedor até uma data e hora. Nunca são editadas; desfazer a última é um soft delete.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('caixa_prestacoes_contas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuarios_id')
                ->comment('Vendedor cujo caixa foi prestado')
                ->constrained('usuarios');
            $table->dateTime('periodo_inicio')->nullable()
                ->comment('Início do período da prestação (fim da prestação anterior); vazio na primeira prestação do vendedor');
            $table->dateTime('periodo_fim')
                ->comment('Fim do período da prestação: data e hora até onde o caixa foi fechado');
            $table->decimal('saldo_anterior', 15, 2)
                ->comment('Diferença deixada pela prestação anterior (apurado − recebido), em reais, já incluída no valor apurado');
            $table->decimal('valor_apurado', 15, 2)
                ->comment('Saldo de caixa do vendedor no fim do período, em reais; negativo quando a banca deve ao vendedor');
            $table->decimal('valor_recebido', 15, 2)
                ->comment('Valor efetivamente recebido do vendedor, em reais; negativo quando a banca pagou ao vendedor');
            $table->decimal('diferenca', 15, 2)
                ->comment('Diferença que fica para a próxima prestação (apurado − recebido), em reais');
            $table->char('lote', 36)->nullable()
                ->comment('Identificador (UUID) da prestação em lote feita pela linha do gerente; vazio na prestação individual');
            $table->foreignId('prestado_por')
                ->comment('Usuário do painel que fez a prestação de contas')
                ->constrained('usuarios');
            $table->string('observacao', 500)->nullable()
                ->comment('Observação opcional da prestação');
            $table->foreignId('desfeita_por')->nullable()
                ->comment('Usuário que desfez a prestação; vazio enquanto ela vale')
                ->constrained('usuarios');
            $table->string('motivo_desfazer', 500)->nullable()
                ->comment('Motivo informado ao desfazer a prestação');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['usuarios_id', 'periodo_fim']);
            $table->index('lote');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('caixa_prestacoes_contas');
    }
};
