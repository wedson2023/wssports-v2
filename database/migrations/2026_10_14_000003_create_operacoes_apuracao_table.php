<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de tudo o que a apuração faz (spec 009, R-13). Só inserção.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('operacoes_apuracao', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 30)
                ->comment('Operação: Resultado do jogo, Situação do jogo, Resultado do palpite, Resultado da aposta, Palpite cancelado, Palpite restaurado, Prêmio pago, Prêmio ajustado, Prêmio estornado, Valor devolvido');
            $table->string('origem', 30)
                ->comment('O que disparou a operação: Carga de resultados, Carga de cancelados, Pagamento de prêmios, Reprocessamento manual, Lançamento manual, Encerramento de especial');
            $table->foreignId('confrontos_id')->nullable()
                ->comment('Jogo envolvido; nulo em operações só da aposta')
                ->constrained('confrontos');
            $table->foreignId('apostas_id')->nullable()
                ->comment('Aposta envolvida; nulo em operações só do jogo')
                ->constrained('apostas');
            $table->foreignId('apostas_palpites_id')->nullable()
                ->comment('Palpite envolvido')
                ->constrained('apostas_palpites');
            $table->foreignId('clientes_id')->nullable()
                ->comment('Cliente cujo saldo ou aposta foi afetado')
                ->constrained('clientes');
            $table->string('carteira', 30)->nullable()
                ->comment('Carteira movimentada (Saldo ou Promoção esportes); nulo sem movimentação');
            $table->foreignId('clientes_transacoes_id')->nullable()
                ->comment('Transação do extrato gerada pela operação')
                ->constrained('clientes_transacoes');
            $table->decimal('valor', 15, 2)->nullable()
                ->comment('Valor movimentado em reais; nulo sem movimentação');
            $table->json('antes')->nullable()
                ->comment('Estado anterior (placar, situação, resultado, prêmio ou saldo, conforme o tipo)');
            $table->json('depois')->nullable()
                ->comment('Estado posterior, mesmas chaves de antes');
            $table->string('motivo', 255)->nullable()
                ->comment('Explicação legível (ex.: "Placar corrigido pelo provedor")');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['confrontos_id', 'created_at']);
            $table->index(['apostas_id', 'created_at']);
            $table->index(['clientes_id', 'created_at']);
            $table->index(['tipo', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operacoes_apuracao');
    }
};
