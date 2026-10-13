<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Depósitos do cliente pelo Mercado Pago (spec 007, data-model §1).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('depositos', function (Blueprint $table) {
            $table->id();
            // UUID: external_reference e chave de idempotência no Mercado Pago
            $table->char('codigo', 36)->unique();
            $table->foreignId('clientes_id')->constrained('clientes');
            $table->string('meio', 30);
            // valor creditado no saldo (sem juros do parcelamento)
            $table->decimal('valor', 15, 2);
            $table->unsignedTinyInteger('parcelas')->nullable();
            // total cobrado do cliente com juros (cartão)
            $table->decimal('valor_total_pago', 15, 2)->nullable();
            // comissão da conta master (application_fee) e o percentual usado
            $table->decimal('comissao', 15, 2);
            $table->decimal('percentual_comissao', 5, 2);
            $table->boolean('aceita_bonus');
            $table->string('situacao', 20);
            $table->string('pagamento_id', 40)->nullable()->unique();
            // status_detail do Mercado Pago (motivo da recusa)
            $table->string('status_detalhe', 80)->nullable();
            $table->text('pix_copia_cola')->nullable();
            $table->mediumText('pix_qr_code')->nullable();
            $table->timestamp('expira_em')->nullable();
            $table->timestamp('pago_em')->nullable();
            $table->decimal('valor_estornado', 15, 2)->default(0);
            $table->timestamp('estornado_em')->nullable();
            $table->foreignId('clientes_transacoes_id')->nullable()->constrained('clientes_transacoes');
            $table->foreignId('clientes_promocoes_id')->nullable()->constrained('clientes_promocoes');
            $table->foreignId('bonus_clientes_rollovers_id')->nullable()->constrained('clientes_rollovers');
            $table->foreignId('rollover_clientes_rollovers_id')->nullable()->constrained('clientes_rollovers');
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
        Schema::dropIfExists('depositos');
    }
};
