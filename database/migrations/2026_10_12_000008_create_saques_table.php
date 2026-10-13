<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saques do cliente (spec 007, data-model §2): débito na hora e pagamento manual (painel) ou
 * automático (Mercado Pago).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('saques', function (Blueprint $table) {
            $table->id();
            // UUID: chave de idempotência do pagamento automático
            $table->char('codigo', 36)->unique();
            $table->foreignId('clientes_id')->constrained('clientes');
            $table->decimal('valor', 15, 2);
            // cópia da chave Pix principal no momento do pedido
            $table->string('pix_tipo_chave', 20);
            $table->string('pix_chave', 150);
            $table->string('pix_nome_titular', 150);
            $table->string('modo', 20);
            $table->string('situacao', 20);
            $table->string('motivo_recusa', 255)->nullable();
            // falha do pagamento automático (o saque volta para a fila manual)
            $table->string('motivo_falha', 255)->nullable();
            $table->string('pagamento_id', 60)->nullable();
            $table->foreignId('clientes_transacoes_id')->constrained('clientes_transacoes');
            $table->foreignId('devolucao_clientes_transacoes_id')->nullable()->constrained('clientes_transacoes');
            $table->foreignId('decidido_por')->nullable()->constrained('usuarios');
            $table->timestamp('decidido_em')->nullable();
            $table->timestamp('pago_em')->nullable();
            // autor no acesso assistido pelo painel
            $table->foreignId('usuarios_id')->nullable()->constrained('usuarios');
            $table->string('ip', 45)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['clientes_id', 'created_at']);
            $table->index(['situacao', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saques');
    }
};
