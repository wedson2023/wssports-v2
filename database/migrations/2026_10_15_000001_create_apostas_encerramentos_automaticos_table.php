<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Encerramento automático por valor-alvo programado pelo cliente (spec 010, FR-030 e FR-031).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('apostas_encerramentos_automaticos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apostas_id')
                ->comment('Aposta com o encerramento automático')
                ->constrained('apostas');
            $table->foreignId('clientes_id')
                ->comment('Cliente dono da aposta')
                ->constrained('clientes');
            $table->foreignId('usuarios_id')->nullable()
                ->comment('Usuário do painel que programou em acesso assistido; nulo quando foi o cliente')
                ->constrained('usuarios');
            $table->decimal('valor_alvo', 15, 2)
                ->comment('Oferta que dispara o encerramento, em reais');
            $table->string('situacao', 20)
                ->comment('Situação: Ativo, Executado ou Cancelado');
            $table->timestamp('executado_em')->nullable()
                ->comment('Quando a oferta alcançou o alvo e a aposta foi encerrada');
            $table->timestamp('cancelado_em')->nullable()
                ->comment('Quando o automático foi desativado ou substituído');
            $table->string('motivo_cancelamento', 100)->nullable()
                ->comment('Por que foi cancelado: pelo cliente, alterado, encerramento manual ou aposta decidida');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['situacao', 'apostas_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('apostas_encerramentos_automaticos');
    }
};
