<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aparelhos dos clientes inscritos nas notificações push (spec 010, FR-040, FR-041 e FR-046).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('clientes_inscricoes_notificacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clientes_id')
                ->comment('Cliente dono da inscrição')
                ->constrained('clientes');
            $table->text('endpoint')
                ->comment('Endereço de entrega fornecido pelo navegador (Web Push)');
            $table->char('endpoint_hash', 64)->unique()
                ->comment('SHA-256 do endpoint, para buscar e evitar inscrição repetida');
            $table->string('chave_p256dh', 255)
                ->comment('Chave pública do aparelho (p256dh) usada para criptografar o aviso');
            $table->string('chave_auth', 255)
                ->comment('Segredo de autenticação do aparelho (auth)');
            $table->string('codificacao', 20)
                ->comment('Codificação do conteúdo informada pelo navegador (aes128gcm ou aesgcm)');
            $table->string('aparelho', 255)->nullable()
                ->comment('Descrição do aparelho (user agent) para o cliente reconhecer');
            $table->boolean('receber_gols')->default(true)
                ->comment('Recebe avisos de gol e de gol anulado');
            $table->boolean('receber_intervalo')->default(true)
                ->comment('Recebe aviso de intervalo');
            $table->boolean('receber_fim_jogo')->default(true)
                ->comment('Recebe aviso de fim de jogo');
            $table->boolean('receber_resultado_aposta')->default(true)
                ->comment('Recebe aviso quando a aposta é decidida');
            $table->boolean('receber_encerramento_automatico')->default(true)
                ->comment('Recebe aviso quando um encerramento automático é executado');
            $table->timestamp('ultimo_envio_em')->nullable()
                ->comment('Último aviso entregue ao serviço de push com sucesso');
            $table->timestamps();
            $table->softDeletes();

            $table->index('clientes_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes_inscricoes_notificacoes');
    }
};
