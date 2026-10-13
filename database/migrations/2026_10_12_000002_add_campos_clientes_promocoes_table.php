<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Campos da tela "Cadastrar bônus" do sistema antigo que faltavam (spec 007, FR-018e).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clientes_promocoes', function (Blueprint $table) {
            // dias para cumprir o rollover do bônus; depois disso o bônus é retirado
            $table->unsignedSmallInteger('dias_cumprir')->default(30);
            // no primeiro depósito, o rollover do próprio depósito passa a ser vezes × depósito
            $table->boolean('rollover_primeiro_deposito')->default(false);
            // sem chave estrangeira: a tabela de afiliados ainda não existe; ajustar na spec de afiliados
            $table->string('codigo_afiliado', 50)->nullable();
            // só na categoria Indicação: indicações válidas exigidas para cada bônus
            $table->unsignedSmallInteger('quantidade_indicacoes')->nullable();
        });

        DB::table('clientes_promocoes')
            ->where('categoria', 'Indicação')
            ->whereNull('quantidade_indicacoes')
            ->update(['quantidade_indicacoes' => 5]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clientes_promocoes', function (Blueprint $table) {
            $table->dropColumn(['dias_cumprir', 'rollover_primeiro_deposito', 'codigo_afiliado', 'quantidade_indicacoes']);
        });
    }
};
