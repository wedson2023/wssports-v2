<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resumo de cada carga do catálogo do cassino (spec 011, FR-005).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cassinos_sincronizacoes', function (Blueprint $table) {
            $table->id();
            $table->string('origem', 20)
                ->comment('Quem disparou a carga: Agendada ou Painel');
            $table->foreignId('usuarios_id')->nullable()
                ->comment('Usuário do painel que pediu a carga; nulo na agendada')
                ->constrained('usuarios');
            $table->string('situacao', 20)
                ->comment('Situação: Em andamento, Concluída ou Falha');
            $table->unsignedInteger('recebidos')->default(0)
                ->comment('Itens recebidos do provedor (um por jogo e canal)');
            $table->unsignedInteger('jogos_novos')->default(0)
                ->comment('Jogos criados');
            $table->unsignedInteger('canais_novos')->default(0)
                ->comment('Canais criados');
            $table->unsignedInteger('alterados')->default(0)
                ->comment('Jogos ou canais com algum dado alterado (inclusive código recadastrado)');
            $table->unsignedInteger('desativados')->default(0)
                ->comment('Jogos e canais que deixaram de vir na lista');
            $table->unsignedInteger('reativados')->default(0)
                ->comment('Jogos e canais que voltaram à lista');
            $table->json('falhas')->nullable()
                ->comment('Itens recusados (sem nome, sem id, ID duplicado no mesmo canal) e o motivo');
            $table->string('erro', 255)->nullable()
                ->comment('Motivo da falha da carga (resposta vazia, erro do provedor, tempo esgotado)');
            $table->timestamp('iniciada_em')->useCurrent()
                ->comment('Início da carga');
            $table->timestamp('concluida_em')->nullable()
                ->comment('Fim da carga; nulo enquanto em andamento');
            $table->timestamps();
            $table->softDeletes();

            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cassinos_sincronizacoes');
    }
};
