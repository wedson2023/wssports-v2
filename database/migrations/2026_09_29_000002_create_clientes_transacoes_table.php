<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('clientes_transacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clientes_id')->constrained('clientes');
            $table->string('carteira', 30);
            $table->string('tipo', 10);
            $table->string('origem', 30);
            // sem chave estrangeira: a origem pode ser uma promoção ou, no futuro, uma aposta
            $table->unsignedBigInteger('referencia_id')->nullable();
            $table->decimal('valor', 15, 2);
            $table->decimal('saldo_anterior', 15, 2);
            $table->decimal('saldo_posterior', 15, 2);
            // autor no painel; nulo quando a operação foi do sistema
            $table->foreignId('usuarios_id')->nullable()->constrained('usuarios');
            $table->string('observacao')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['clientes_id', 'created_at']);
            $table->index(['clientes_id', 'carteira']);
            $table->index(['origem', 'referencia_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes_transacoes');
    }
};
