<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O jogo em cada canal (label) do provedor (spec 011, FR-002, R-03, R-06).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cassinos_jogos_canais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cassinos_jogos_id')
                ->comment('Jogo deste canal')
                ->constrained('cassinos_jogos');
            $table->string('canal', 60)
                ->comment('Canal do provedor (label), ex.: pgsoft_op_kn, spribe_sr');
            $table->string('codigo_provedor', 30)
                ->comment('Código do jogo no provedor neste canal (id do gamesList); atualizado quando o provedor recadastra o jogo');
            $table->boolean('pre_pago')->default(false)
                ->comment('Canal pré-pago, que exige crédito do hall no provedor');
            $table->boolean('bonus_primeiro')->default(false)
                ->comment('Apostas neste canal usam primeiro o bônus de cassino e, se ele não cobrir, o saldo real');
            $table->unsignedSmallInteger('prioridade')->default(50)
                ->comment('Ordem de tentativa na abertura do jogo (menor primeiro)');
            $table->boolean('ativo')->default(true)
                ->comment('Canal liberado pelo administrador; canal pré-pago entra desativado');
            $table->boolean('demo')->default(false)
                ->comment('O provedor oferece modo demo neste canal');
            $table->boolean('flash')->default(false)
                ->comment('O canal exige Flash (nunca abre)');
            $table->timestamp('indisponivel_em')->nullable()
                ->comment('Quando o provedor recusou abrir o jogo por este canal; nulo quando disponível');
            $table->string('motivo_indisponibilidade', 60)->nullable()
                ->comment('Erro do provedor que tornou o canal indisponível: games_not_activ ou hall_balance_less_100');
            $table->timestamp('removido_em')->nullable()
                ->comment('Quando o canal deixou de vir na lista do provedor; nulo enquanto vem');
            $table->json('dados_provedor')->nullable()
                ->comment('Campos do provedor guardados para conferência: vertical, rewriterule, exitButton, disableReload, system_name2, lines, bm');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['cassinos_jogos_id', 'canal']);
            $table->index(['canal', 'codigo_provedor']);
            $table->index('codigo_provedor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cassinos_jogos_canais');
    }
};
