<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Antecipações de pendentes pelo painel (spec 019, FR-019, data-model §1.1): prêmios e encerramentos
 * que estavam na janela de segurança e foram creditados na hora, com quem antecipou e quanto.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('clientes_antecipacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clientes_id')
                ->comment('Cliente que recebeu a antecipação')
                ->constrained('clientes');
            $table->foreignId('usuarios_id')
                ->comment('Usuário do painel que antecipou')
                ->constrained('usuarios');
            $table->unsignedInteger('quantidade_premios')
                ->comment('Quantidade de apostas com prêmio (ou devolução) creditado na antecipação');
            $table->decimal('valor_premios', 15, 2)
                ->comment('Soma creditada das apostas, em reais');
            $table->unsignedInteger('quantidade_encerramentos')
                ->comment('Quantidade de encerramentos (cash out) creditados na antecipação');
            $table->decimal('valor_encerramentos', 15, 2)
                ->comment('Soma creditada dos encerramentos, em reais');
            $table->string('ip', 45)->nullable()
                ->comment('IP de quem antecipou');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['clientes_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes_antecipacoes');
    }
};
