<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Histórico das alterações feitas na tela Configurações (spec 021, FR-003a, data-model §2).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('configuracoes_historicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuarios_id')
                ->comment('Autor da alteração')
                ->constrained('usuarios');
            $table->string('secao', 30)
                ->comment('Aba ou seção: banca, logo, banners, avisos, regras, mercado_pago, robo_whatsapp, pagamentos, criar_aposta, apuracao, encerramento, cassino, afiliados, avisos_whatsapp, ao_vivo, sorteio_cotacoes ou notificacoes');
            $table->string('acao', 20)
                ->comment('Ação: Alteração, Cadastro, Exclusão, Vínculo, Desvínculo, Conexão ou Desconexão');
            $table->unsignedBigInteger('item_id')->nullable()
                ->comment('Banner ou aviso alterado (seções banners e avisos); nulo nas demais');
            $table->json('alteracoes')->nullable()
                ->comment('Campos alterados {campo: {antes, depois}}; segredos como "***"/"alterada"; nulo nas ações sem campos');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['secao', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('configuracoes_historicos');
    }
};
