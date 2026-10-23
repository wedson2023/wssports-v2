<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Envios manuais de notificação push pelo painel (spec 019, FR-031 a FR-033a, data-model §1.2): quem
 * enviou, para qual público, o texto e quantos foram alcançados e entregues.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('clientes_notificacoes_envios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuarios_id')
                ->comment('Usuário do painel que enviou')
                ->constrained('usuarios');
            $table->string('publico', 20)
                ->comment('Público do envio: Todos, Por ID ou Sem interagir');
            $table->json('clientes_ids')->nullable()
                ->comment('Ids dos clientes escolhidos no público Por ID');
            $table->unsignedSmallInteger('dias_sem_interagir')->nullable()
                ->comment('Dias sem apostar nem depositar no público Sem interagir (1 a 365)');
            $table->string('titulo', 50)
                ->comment('Título mostrado na notificação');
            $table->string('mensagem', 150)
                ->comment('Texto mostrado na notificação');
            $table->unsignedInteger('alcancados')
                ->comment('Clientes inscritos no público no momento do envio');
            $table->unsignedInteger('entregues')->default(0)
                ->comment('Aparelhos que receberam a notificação (somado durante o envio)');
            $table->unsignedInteger('falhas')->default(0)
                ->comment('Aparelhos que recusaram a notificação (somado durante o envio)');
            $table->timestamp('concluido_em')->nullable()
                ->comment('Quando o envio terminou');
            $table->timestamps();
            $table->softDeletes();

            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes_notificacoes_envios');
    }
};
