<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Apuração e pagamento das apostas (spec 009, R-10): quando a aposta foi apurada, quanto já foi
 * pago e se ainda falta liquidar.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('apostas', function (Blueprint $table) {
            $table->timestamp('apurada_em')->nullable()
                ->comment('Quando o resultado ou o total a pagar mudou pela última vez na apuração; base da janela de pagamento');
            $table->decimal('valor_pago', 15, 2)->default(0)
                ->comment('Total líquido já creditado ao cliente por esta aposta (prêmios menos estornos), em reais');
            $table->boolean('liquidacao_pendente')->default(false)
                ->comment('Verdadeiro quando o valor devido ainda difere do valor pago (aguardando a janela de pagamento)');

            $table->index(['liquidacao_pendente', 'apurada_em']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('apostas', function (Blueprint $table) {
            $table->dropIndex(['liquidacao_pendente', 'apurada_em']);
            $table->dropColumn(['apurada_em', 'valor_pago', 'liquidacao_pendente']);
        });
    }
};
