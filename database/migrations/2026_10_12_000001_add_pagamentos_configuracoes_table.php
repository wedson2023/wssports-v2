<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Configurações de pagamento do sistema (spec 007): gateway, comissão da conta master, limites de
 * depósito e saque, rollover de depósito, cashback e tempos.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('configuracoes', function (Blueprint $table) {
            // gateway escolhido no painel ("Mercado Pago"); null = sem gateway
            $table->string('gateway_pagamento', 30)->nullable();
            // comissão da conta master sobre cada depósito; só no banco, nunca exibida
            $table->decimal('percentual_comissao_gateway', 5, 2)->default(3.00);
            $table->boolean('depositos_habilitados')->default(true);
            $table->boolean('saques_habilitados')->default(true);
            $table->decimal('deposito_minimo', 15, 2)->default(1.00);
            $table->decimal('deposito_maximo', 15, 2)->default(50000.00);
            $table->decimal('saque_minimo', 15, 2)->default(10.00);
            // todo depósito exige ser apostado antes do saque, pelo número de vezes abaixo
            $table->boolean('rollover_deposito')->default(true);
            $table->unsignedTinyInteger('rollover_deposito_vezes')->default(1);
            // percentual do depósito devolvido como cashback (somado ao rollover do depósito)
            $table->decimal('cashback_percentual', 5, 2)->default(0);
            // Manual (baixa no painel) ou Automático (pelo Mercado Pago até o valor abaixo)
            $table->string('modo_saque', 20)->default('Manual');
            $table->decimal('saque_automatico_maximo', 15, 2)->default(1000.00);
            // meios de depósito oferecidos ao cliente
            $table->json('meios_deposito')->nullable();
            $table->unsignedTinyInteger('parcelas_maximas')->default(12);
            $table->unsignedSmallInteger('minutos_expiracao_pix')->default(30);
            $table->unsignedSmallInteger('minutos_acesso_assistido')->default(60);
        });

        DB::table('configuracoes')->update(['meios_deposito' => json_encode(['Pix', 'Cartão de crédito'])]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('configuracoes', function (Blueprint $table) {
            $table->dropColumn([
                'gateway_pagamento',
                'percentual_comissao_gateway',
                'depositos_habilitados',
                'saques_habilitados',
                'deposito_minimo',
                'deposito_maximo',
                'saque_minimo',
                'rollover_deposito',
                'rollover_deposito_vezes',
                'cashback_percentual',
                'modo_saque',
                'saque_automatico_maximo',
                'meios_deposito',
                'parcelas_maximas',
                'minutos_expiracao_pix',
                'minutos_acesso_assistido',
            ]);
        });
    }
};
