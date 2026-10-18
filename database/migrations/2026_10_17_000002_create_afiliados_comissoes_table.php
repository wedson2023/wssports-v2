<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Regras de comissão do afiliado com vigência (spec 012, data-model §2, R-08).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('afiliados_comissoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('afiliados_id')
                ->comment('Afiliado dono da regra')
                ->constrained('afiliados');
            $table->string('tipo_ganho', 20)
                ->comment('Base da comissão: Saldo líquido, Qualquer depósito ou Primeiro depósito');
            $table->decimal('percentual', 5, 2)
                ->comment('Percentual da comissão, de 0 a 100, em %');
            $table->dateTime('vigente_desde')
                ->comment('Início da vigência da regra');
            $table->dateTime('vigente_ate')->nullable()
                ->comment('Fim da vigência; nulo = regra vigente');
            $table->foreignId('usuarios_id')->nullable()
                ->comment('Usuário do painel que definiu a regra; nulo quando veio da regra padrão do cadastro público')
                ->constrained('usuarios');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['afiliados_id', 'vigente_ate']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('afiliados_comissoes');
    }
};
