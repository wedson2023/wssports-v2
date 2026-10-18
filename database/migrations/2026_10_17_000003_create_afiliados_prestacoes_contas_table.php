<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prestações de contas do afiliado: cada uma fecha todos os lançamentos em aberto (spec 012,
 * data-model §4, R-07). Nunca são editadas nem excluídas.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('afiliados_prestacoes_contas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('afiliados_id')
                ->comment('Afiliado da prestação')
                ->constrained('afiliados');
            $table->decimal('valor_apurado', 15, 2)
                ->comment('Soma dos lançamentos fechados, em reais; pode ser negativo');
            $table->decimal('valor_pago', 15, 2)
                ->comment('Valor pago ao afiliado, em reais: o apurado quando positivo, senão zero');
            $table->unsignedInteger('quantidade_lancamentos')
                ->comment('Quantidade de lançamentos fechados nesta prestação');
            $table->dateTime('periodo_inicio')
                ->comment('Início do período: fim da prestação anterior ou cadastro do afiliado');
            $table->dateTime('periodo_fim')
                ->comment('Fim do período: momento da prestação');
            $table->foreignId('usuarios_id')
                ->comment('Usuário do painel que prestou contas')
                ->constrained('usuarios');
            $table->string('observacao', 500)->nullable()
                ->comment('Observação informada na prestação (ex.: forma de pagamento)');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['afiliados_id', 'periodo_fim']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('afiliados_prestacoes_contas');
    }
};
