<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sessões de jogo dos clientes no cassino (spec 011, FR-017, R-11, R-17).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cassinos_sessoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clientes_id')
                ->comment('Cliente que jogou')
                ->constrained('clientes');
            $table->foreignId('cassinos_jogos_id')->nullable()
                ->comment('Jogo da sessão; nulo quando a rodada chegou de um jogo fora do catálogo')
                ->constrained('cassinos_jogos');
            $table->foreignId('cassinos_jogos_canais_id')->nullable()
                ->comment('Canal usado na abertura; nulo quando desconhecido')
                ->constrained('cassinos_jogos_canais');
            $table->string('codigo_provedor', 30)
                ->comment('Código do jogo no provedor usado nesta sessão (gameId)');
            $table->string('sessao_provedor', 100)->nullable()->unique()
                ->comment('Identificação da sessão no provedor (sessionId)');
            $table->string('carteira', 30)->nullable()
                ->comment('Carteira da sessão em uso: Saldo ou Promoção cassino; nula antes da primeira aposta');
            $table->boolean('bonus_primeiro')->default(false)
                ->comment('Cópia da marcação do canal na abertura: a regra de carteira começa pelo bônus de cassino');
            $table->decimal('total_apostado', 15, 2)->default(0)
                ->comment('Soma das apostas das rodadas, menos as canceladas ou reembolsadas, em reais');
            $table->decimal('total_ganho', 15, 2)->default(0)
                ->comment('Soma dos prêmios das rodadas, em reais');
            $table->unsignedInteger('quantidade_rodadas')->default(0)
                ->comment('Rodadas lançadas na sessão');
            $table->timestamp('aberta_em')->useCurrent()
                ->comment('Quando a sessão foi aberta (abertura do jogo ou primeira chamada do provedor)');
            $table->timestamp('ultima_rodada_em')->nullable()
                ->comment('Quando chegou a última rodada');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['clientes_id', 'aberta_em']);
            $table->index(['clientes_id', 'ultima_rodada_em']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cassinos_sessoes');
    }
};
