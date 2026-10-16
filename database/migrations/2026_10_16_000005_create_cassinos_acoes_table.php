<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tipos de ação do lançamento de rodada do provedor e o efeito no saldo (spec 011, FR-022, R-16).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cassinos_acoes', function (Blueprint $table) {
            $table->id();
            $table->string('acao', 40)->unique()
                ->comment('Valor do campo action enviado pelo provedor, em maiúsculas (ex.: SPIN, BET_AND_WINS, CANCEL)');
            $table->string('efeito', 30)
                ->comment('Efeito no saldo: Aposta, Prêmio, Aposta com prêmio, Rodada grátis, Cancelamento ou Reembolso');
            $table->boolean('ativo')->default(true)
                ->comment('Ação aceita no lançamento; desativada é recusada como desconhecida');
            $table->string('descricao', 255)->nullable()
                ->comment('Observação do administrador sobre a ação');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cassinos_acoes');
    }
};
