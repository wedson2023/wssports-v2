<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jogos do cassino, únicos por provedor e nome (spec 011, FR-002, R-03).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cassinos_jogos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cassinos_provedores_id')
                ->comment('Provedor real do jogo')
                ->constrained('cassinos_provedores');
            $table->string('nome', 150)
                ->comment('Nome exibido do jogo (do canal de maior prioridade)');
            $table->string('nome_normalizado', 150)
                ->comment('Nome em minúsculas, sem acentos e espaços duplicados; com o provedor, identifica o jogo');
            $table->string('tipo', 30)
                ->comment('Tipo do jogo (categories do provedor): slots, live_dealers, lottery, crash_games, fast_games, sport, arcade, roulette ou outros');
            $table->string('imagem', 500)->nullable()
                ->comment('URL da imagem do jogo no provedor');
            $table->string('aparelhos', 20)
                ->comment('Aparelhos suportados: Todos, Celular ou Computador');
            $table->string('destaque_provedor', 20)->nullable()
                ->comment('Marcação do provedor (menu): Popular ou Novo; nulo sem marcação');
            $table->boolean('popular')->default(false)
                ->comment('Marcado como popular pelo administrador (linha Populares)');
            $table->unsignedSmallInteger('ordem')->default(1000)
                ->comment('Ordem do jogo nas linhas e na grade (menor primeiro)');
            $table->boolean('ativo')->default(true)
                ->comment('Jogo visível no site, definido pelo administrador');
            $table->unsignedInteger('aberturas')->default(0)
                ->comment('Quantas vezes o jogo foi aberto com dinheiro (ordena Populares)');
            $table->timestamp('removido_em')->nullable()
                ->comment('Quando o jogo deixou de vir na lista do provedor; nulo enquanto vem');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['cassinos_provedores_id', 'nome_normalizado']);
            $table->index(['ativo', 'removido_em', 'tipo', 'ordem']);
            $table->index(['popular', 'ordem']);
            $table->index('aberturas');
            $table->index('nome');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cassinos_jogos');
    }
};
