<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de cada envio de mensagem por canal (spec 025, data-model): nunca guarda o conteúdo.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mensagens_envios', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 40)->comment('Tipo da mensagem (enum TipoMensagem)');
            $table->string('canal', 20)->comment('Canal usado: WhatsApp ou E-mail');
            $table->string('destinatario', 150)->comment('Telefone com DDI ou e-mail de destino; vazio quando o dado não existe');
            $table->string('situacao', 20)->comment('Pendente, Enviado, Falhou ou Dispensado');
            $table->boolean('reserva')->default(false)->comment('Verdadeiro quando saiu pelo canal de reserva');
            $table->unsignedTinyInteger('tentativas')->default(0)->comment('Quantidade de tentativas de envio');
            $table->string('erro', 255)->nullable()->comment('Resumo do erro, sem chaves, códigos nem endereços de serviço');
            $table->timestamp('enviado_em')->nullable()->comment('Quando a mensagem foi entregue ao serviço de envio');
            $table->string('referencia_tipo', 40)->nullable()->comment('Origem da mensagem: Cliente, Afiliado ou Saque');
            $table->unsignedBigInteger('referencia_id')->nullable()->comment('Id da origem, sem chave estrangeira porque aponta para tabelas diferentes');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tipo', 'created_at']);
            $table->index(['situacao', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mensagens_envios');
    }
};
