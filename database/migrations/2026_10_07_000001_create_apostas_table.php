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
        // apostas de visitantes (código Pendente), vendedores e clientes
        Schema::create('apostas', function (Blueprint $table) {
            $table->id();
            $table->char('codigo', 8)->unique();
            $table->char('chave_idempotencia', 36)->unique();
            // chave do pedido de validação do código (a repetição devolve o comprovante)
            $table->char('chave_validacao', 36)->nullable()->unique();
            $table->string('nome', 100);
            $table->string('situacao', 20);
            $table->string('resultado', 20)->default('Aguardando');
            $table->string('tipo', 20);
            $table->string('forma_pagamento', 30)->nullable();
            $table->string('aceitar_alteracoes', 20)->default('Nenhuma');
            $table->decimal('valor', 15, 2);
            $table->decimal('cotacao_total', 14, 2);
            $table->decimal('premio', 15, 2);
            $table->decimal('valor_acrescido', 15, 2)->default(0);
            $table->decimal('comissao', 15, 2)->default(0);
            // valores de configuração usados na confirmação (não mudam se a configuração mudar)
            $table->decimal('percentual_comissao', 5, 2)->default(0);
            $table->decimal('comissao_por_premio', 5, 2)->default(0);
            $table->unsignedInteger('multiplicador');
            $table->decimal('premio_maximo', 15, 2);
            $table->decimal('ganho_multiplo_palpites', 5, 2)->default(0);
            $table->unsignedSmallInteger('tempo_cancelamento_aposta')->nullable();
            $table->foreignId('usuarios_id')->nullable()->constrained('usuarios');
            $table->foreignId('clientes_id')->nullable()->constrained('clientes');
            $table->timestamp('recebida_em');
            $table->timestamp('validada_em')->nullable();
            $table->timestamp('decidida_em')->nullable();
            $table->timestamp('confirmada_em')->nullable();
            $table->timestamp('expira_em')->nullable();
            $table->timestamp('cancelada_em')->nullable();
            $table->foreignId('cancelada_por')->nullable()->constrained('usuarios');
            $table->string('motivo_recusa')->nullable();
            $table->char('assinatura', 64)->nullable();
            $table->string('ip_criacao', 45)->nullable();
            $table->string('ip_validacao', 45)->nullable();
            $table->string('ip_cancelamento', 45)->nullable();
            $table->string('user_agent_criacao')->nullable();
            $table->string('user_agent_validacao')->nullable();
            $table->string('user_agent_cancelamento')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['usuarios_id', 'situacao', 'created_at']);
            $table->index(['clientes_id', 'situacao', 'created_at']);
            $table->index(['situacao', 'expira_em']);
            $table->index(['situacao', 'recebida_em']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('apostas');
    }
};
