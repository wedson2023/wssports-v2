<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extrato de comissões do afiliado: um lançamento por depósito aprovado, saque pago ou estorno de
 * depósito de um cliente ligado, com a regra da hora gravada (spec 012, data-model §3, R-06).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('afiliados_lancamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('afiliados_id')
                ->comment('Afiliado que recebe a comissão')
                ->constrained('afiliados');
            $table->foreignId('clientes_id')
                ->comment('Cliente indicado que fez o movimento')
                ->constrained('clientes');
            $table->string('evento', 30)
                ->comment('Movimento de origem: Depósito, Saque ou Estorno de depósito');
            $table->foreignId('depositos_id')->nullable()
                ->comment('Depósito de origem (depósito e estorno)')
                ->constrained('depositos');
            $table->foreignId('saques_id')->nullable()
                ->comment('Saque de origem')
                ->constrained('saques');
            $table->string('chave_evento', 80)->unique()
                ->comment('Chave única do evento contra lançamento repetido: deposito:{id}, saque:{id} ou estorno:{deposito_id}:{valor_estornado_total}');
            $table->string('tipo_ganho', 20)
                ->comment('Tipo de ganho usado: Saldo líquido, Qualquer depósito ou Primeiro depósito');
            $table->decimal('base', 15, 2)
                ->comment('Valor do depósito, do saque ou estornado agora, em reais (positivo)');
            $table->decimal('percentual', 5, 2)
                ->comment('Percentual usado, em %');
            $table->decimal('valor', 15, 2)
                ->comment('Comissão com sinal, em reais: + depósito; − saque e estorno');
            $table->dateTime('ocorrido_em')
                ->comment('Quando o movimento aconteceu (pagamento do depósito/saque ou estorno)');
            $table->foreignId('afiliados_prestacoes_contas_id')->nullable()
                ->comment('Prestação de contas que fechou o lançamento; nulo = em aberto')
                ->constrained('afiliados_prestacoes_contas');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['afiliados_id', 'afiliados_prestacoes_contas_id'], 'afiliados_lancamentos_abertos_index');
            $table->index(['afiliados_id', 'ocorrido_em']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('afiliados_lancamentos');
    }
};
