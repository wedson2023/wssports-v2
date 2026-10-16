<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rodadas lançadas pelo provedor e aceitas pela carteira (spec 011, FR-027, R-14).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cassinos_rodadas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cassinos_sessoes_id')
                ->comment('Sessão da rodada')
                ->constrained('cassinos_sessoes');
            $table->foreignId('clientes_id')
                ->comment('Cliente da rodada')
                ->constrained('clientes');
            $table->foreignId('cassinos_acoes_id')->nullable()
                ->comment('Tipo de ação reconhecido; nulo quando a ação veio vazia (efeito padrão)')
                ->constrained('cassinos_acoes');
            $table->string('acao', 40)->nullable()
                ->comment('Valor do action recebido do provedor');
            $table->string('efeito', 30)
                ->comment('Efeito aplicado: Aposta, Prêmio, Aposta com prêmio, Rodada grátis, Cancelamento ou Reembolso');
            $table->string('trade_id', 100)->unique()
                ->comment('Identificação do lançamento no provedor (tradeId); impede lançar a mesma rodada duas vezes');
            $table->string('rodada_provedor', 100)->nullable()
                ->comment('Identificação da rodada no jogo, quando o provedor envia (round ou betInfo)');
            $table->decimal('valor_aposta', 15, 2)->default(0)
                ->comment('Valor apostado na rodada (bet), em reais; no cancelamento ou reembolso, o valor devolvido');
            $table->decimal('valor_premio', 15, 2)->default(0)
                ->comment('Valor ganho na rodada (win), em reais');
            $table->string('carteira', 30)
                ->comment('Carteira movimentada: Saldo ou Promoção cassino');
            $table->decimal('saldo_anterior', 15, 2)
                ->comment('Saldo da carteira antes da rodada, em reais');
            $table->decimal('saldo_posterior', 15, 2)
                ->comment('Saldo da carteira depois da rodada (devolvido ao provedor), em reais');
            $table->foreignId('clientes_transacoes_id')->nullable()
                ->comment('Movimentação de saldo da rodada (prêmio − aposta); nula quando o líquido é zero')
                ->constrained('clientes_transacoes');
            $table->foreignId('cassinos_rodadas_id')->nullable()
                ->comment('Aposta original cancelada ou reembolsada por esta rodada')
                ->constrained('cassinos_rodadas');
            $table->timestamp('cancelada_em')->nullable()
                ->comment('Quando a aposta desta rodada foi cancelada ou reembolsada');
            $table->json('dados_provedor')->nullable()
                ->comment('Campos extras do lançamento para conferência (matrix, WinLines, betInfo, date)');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['cassinos_sessoes_id', 'id']);
            $table->index(['clientes_id', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cassinos_rodadas');
    }
};
