<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Combinações de risco novas registradas pelo agendador (spec 014, R-11): única origem dos avisos
 * do painel e do WhatsApp do Admin, e o controle para não avisar duas vezes.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('apostas_combinacoes_risco', function (Blueprint $table) {
            $table->id();
            $table->date('dia')
                ->comment('Dia (Brasília) em que a combinação surgiu');
            $table->char('chave', 40)
                ->comment('sha1 da assinatura de jogos e opções que identifica a combinação');
            $table->json('jogos')
                ->comment('Nomes dos jogos no momento do aviso, na ordem da assinatura');
            $table->json('opcoes')
                ->comment('Nomes das opções no momento do aviso, na mesma ordem dos jogos');
            $table->json('codigos')
                ->comment('Códigos dos bilhetes da combinação quando surgiu');
            $table->json('usuarios_ids')
                ->comment('Ids dos vendedores dos bilhetes, para filtrar os avisos do painel pela rede');
            $table->unsignedInteger('quantidade_bilhetes')
                ->comment('Número de bilhetes da combinação quando surgiu');
            $table->decimal('valor_apostado', 15, 2)
                ->comment('Soma apostada nos bilhetes quando surgiu, em reais');
            $table->decimal('premio', 15, 2)
                ->comment('Prêmio somado dos bilhetes quando surgiu, em reais');
            $table->string('situacao_whatsapp', 20)
                ->comment('Mensagem ao Admin: Pendente, Enviado, Falhou ou Dispensado');
            $table->unsignedTinyInteger('tentativas_whatsapp')->default(0)
                ->comment('Tentativas de envio da mensagem ao Admin (máx. 3)');
            $table->timestamp('enviado_em')->nullable()
                ->comment('Quando o CallMeBot aceitou a mensagem');
            $table->string('erro_whatsapp', 255)->nullable()
                ->comment('Último erro de envio da mensagem');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['dia', 'chave']);
            $table->index(['situacao_whatsapp', 'tentativas_whatsapp'], 'apostas_combinacoes_risco_whatsapp_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('apostas_combinacoes_risco');
    }
};
