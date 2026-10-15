<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Avisos enviados a cada cliente; a chave única impede o mesmo aviso duas vezes (spec 010, SC-008).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('clientes_notificacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clientes_id')
                ->comment('Cliente avisado')
                ->constrained('clientes');
            $table->string('tipo', 30)
                ->comment('Tipo do aviso: Gol, Gol anulado, Intervalo, Fim de jogo, Resultado da aposta ou Encerramento automático');
            $table->string('chave', 80)
                ->comment('Identificador do fato avisado (ex.: evento:15, aposta:9:Vencedor, encerramento:4), único por cliente');
            $table->foreignId('confrontos_eventos_id')->nullable()
                ->comment('Evento do jogo avisado')
                ->constrained('confrontos_eventos');
            $table->foreignId('apostas_id')->nullable()
                ->comment('Aposta aberta pelo link do aviso')
                ->constrained('apostas');
            $table->string('titulo', 120)
                ->comment('Título exibido no aviso');
            $table->string('corpo', 255)->nullable()
                ->comment('Texto exibido no aviso');
            $table->string('url', 255)
                ->comment('Endereço aberto ao tocar no aviso (bilhete em tempo real)');
            $table->unsignedSmallInteger('aparelhos')->default(0)
                ->comment('Quantidade de aparelhos a que o aviso foi entregue');
            $table->unsignedSmallInteger('falhas')->default(0)
                ->comment('Quantidade de aparelhos que recusaram o aviso');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['clientes_id', 'chave']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes_notificacoes');
    }
};
