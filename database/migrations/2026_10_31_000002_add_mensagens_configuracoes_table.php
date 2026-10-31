<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Destinos e canais das mensagens e controle da queda do robô do WhatsApp (spec 025, data-model).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('configuracoes', function (Blueprint $table) {
            $table->string('email_admin', 150)->nullable()
                ->comment('E-mail do Admin, destino dos avisos da operação e do alerta de queda do robô');
            $table->json('mensagens_canais')->nullable()
                ->comment('Canal de cada tipo de mensagem: WhatsApp, E-mail ou Os dois; tipo ausente usa o padrão do enum TipoMensagem');
            $table->boolean('robo_whatsapp_desconexao_pedida')->default(false)
                ->comment('Verdadeiro quando o Admin desconectou o robô pelo painel');
            $table->timestamp('robo_whatsapp_alerta_queda_em')->nullable()
                ->comment('Último alerta de queda do robô; limita a um e-mail a cada 30 minutos');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('configuracoes', function (Blueprint $table) {
            $table->dropColumn(['email_admin', 'mensagens_canais', 'robo_whatsapp_desconexao_pedida', 'robo_whatsapp_alerta_queda_em']);
        });
    }
};
