<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de toda chamada recebida do provedor do cassino (spec 011, FR-026, R-15). Log técnico
 * de alto volume: o comando cassinos_chamadas:limpar apaga fisicamente as antigas.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cassinos_chamadas', function (Blueprint $table) {
            $table->id();
            $table->string('comando', 30)->nullable()
                ->comment('Comando recebido (cmd): getBalance, writeBet ou outro');
            $table->string('login', 60)->nullable()
                ->comment('Login do cliente enviado pelo provedor');
            $table->string('trade_id', 100)->nullable()
                ->comment('tradeId do lançamento, quando houver');
            $table->string('sessao_provedor', 100)->nullable()
                ->comment('sessionId recebido, quando houver');
            $table->json('dados')
                ->comment('Dados recebidos, sem os campos key e sign');
            $table->json('resposta')
                ->comment('Resposta devolvida ao provedor');
            $table->string('resultado', 20)
                ->comment('Resultado: Aceita, Repetida, Recusada ou Erro');
            $table->string('erro', 60)->nullable()
                ->comment('Código de erro devolvido (ex.: fail_balance, wrong_key, unknown_action)');
            $table->string('ip', 45)->nullable()
                ->comment('IP de origem da chamada');
            $table->unsignedInteger('duracao_ms')
                ->comment('Tempo de processamento da chamada, em milissegundos');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['resultado', 'created_at']);
            $table->index(['login', 'created_at']);
            $table->index('trade_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cassinos_chamadas');
    }
};
