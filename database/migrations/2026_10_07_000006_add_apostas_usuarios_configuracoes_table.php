<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Regras de aposta do vendedor (só dele): permissões, limites, comissões por quantidade de
 * jogos (pré-jogo e ao vivo) e limites de venda. Os valores iniciais ficam no padrão das colunas.
 */
return new class extends Migration
{
    /**
     * Quantidade de faixas de comissão (a 12 vale para 12 ou mais palpites).
     */
    private const FAIXAS_COMISSAO = 12;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('usuarios_configuracoes', function (Blueprint $table) {
            $table->boolean('realizar_aposta')->default(true);
            $table->boolean('cancelar_aposta')->default(true);
            $table->unsignedSmallInteger('tempo_cancelamento_aposta')->default(5);
            $table->boolean('apostar_jogadores')->default(true);
            $table->string('periodo_jogos', 20)->default('Depois de amanhã');
            $table->dateTime('data_travamento_sistema')->nullable();
            $table->string('mensagem_bilhete', 500)->default('BOA SORTE!');
            $table->unsignedSmallInteger('delay_ao_vivo')->default(15);
            $table->unsignedSmallInteger('quantidade_minima_opcoes')->default(1);
            $table->unsignedSmallInteger('quantidade_maxima_opcoes')->default(20);
            $table->decimal('valor_minimo_aposta', 15, 2)->default(2.00);
            $table->decimal('valor_maximo_aposta', 15, 2)->default(1000.00);
            $table->decimal('odd_minima', 8, 2)->default(1.00);
            $table->decimal('premio_maximo', 15, 2)->default(5000.00);
            $table->unsignedInteger('multiplicador')->default(1000);
            $table->decimal('ganho_multiplo_palpites', 5, 2)->default(0);

            foreach (['pre_jogo', 'ao_vivo'] as $tipo) {
                for ($faixa = 1; $faixa <= self::FAIXAS_COMISSAO; $faixa++) {
                    $table->decimal("comissao_{$tipo}_{$faixa}", 5, 2)->default(0);
                }
            }

            $table->decimal('comissao_por_premio', 5, 2)->default(0);
            // saldos disponíveis de venda; a reposição fica para a spec de caixa
            $table->decimal('limite_simples', 15, 2)->default(5000.00);
            $table->decimal('limite_duplo', 15, 2)->default(5000.00);
            $table->decimal('limite_geral', 15, 2)->default(5000.00);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $comissoes = [];

        foreach (['pre_jogo', 'ao_vivo'] as $tipo) {
            for ($faixa = 1; $faixa <= self::FAIXAS_COMISSAO; $faixa++) {
                $comissoes[] = "comissao_{$tipo}_{$faixa}";
            }
        }

        Schema::table('usuarios_configuracoes', function (Blueprint $table) use ($comissoes) {
            $table->dropColumn([
                'realizar_aposta', 'cancelar_aposta', 'tempo_cancelamento_aposta', 'apostar_jogadores',
                'periodo_jogos', 'data_travamento_sistema', 'mensagem_bilhete', 'delay_ao_vivo',
                'quantidade_minima_opcoes', 'quantidade_maxima_opcoes', 'valor_minimo_aposta',
                'valor_maximo_aposta', 'odd_minima', 'premio_maximo', 'multiplicador',
                'ganho_multiplo_palpites', ...$comissoes, 'comissao_por_premio', 'limite_simples',
                'limite_duplo', 'limite_geral',
            ]);
        });
    }
};
