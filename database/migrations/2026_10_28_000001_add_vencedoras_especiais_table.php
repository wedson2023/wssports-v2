<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Uma ou mais vencedoras por categoria especial, divisão por empate e anulação de opção (spec 024,
 * R-05). A vencedora passa a ser marcada na opção; a coluna antiga especiais_opcoes_id_vencedora é
 * migrada e removida.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('especiais', function (Blueprint $table) {
            $table->unsignedTinyInteger('quantidade_vencedoras')->default(1)->after('data_limite')
                ->comment('Quantidade de opções vencedoras exigida ao encerrar (padrão 1)');
            $table->boolean('dividir_empate')->default(false)->after('quantidade_vencedoras')
                ->comment('Se o encerramento dividiu a cotação das opções empatadas na última vaga (dead heat)');
        });

        Schema::table('especiais_opcoes', function (Blueprint $table) {
            $table->boolean('vencedora')->nullable()->after('cotacao')
                ->comment('Resultado da opção ao encerrar: nulo até o encerramento, verdadeiro se venceu');
            $table->boolean('empatada')->default(false)->after('vencedora')
                ->comment('Vencedora empatada na última vaga, com a cotação dividida (dead heat)');
            $table->timestamp('anulada_em')->nullable()->after('ativo')
                ->comment('Quando a opção foi anulada (palpites devolvidos como cotação 1,00)');
            $table->foreignId('anulada_por')->nullable()->after('anulada_em')->constrained('usuarios')
                ->comment('Usuário do painel que anulou a opção');
        });

        // a vencedora antiga vira a marca na opção; as demais opções da categoria perderam
        DB::table('especiais')->whereNotNull('especiais_opcoes_id_vencedora')->orderBy('id')
            ->each(function (object $especial) {
                DB::table('especiais_opcoes')->where('especiais_id', $especial->id)->update(['vencedora' => false]);
                DB::table('especiais_opcoes')->where('id', $especial->especiais_opcoes_id_vencedora)->update(['vencedora' => true]);
            });

        Schema::table('especiais', function (Blueprint $table) {
            $table->dropForeign(['especiais_opcoes_id_vencedora']);
            $table->dropColumn('especiais_opcoes_id_vencedora');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('especiais', function (Blueprint $table) {
            $table->unsignedBigInteger('especiais_opcoes_id_vencedora')->nullable()->after('situacao')
                ->comment('Opção vencedora, preenchida ao encerrar');
            $table->foreign('especiais_opcoes_id_vencedora')->references('id')->on('especiais_opcoes');
        });

        // volta só uma vencedora por categoria: a primeira marcada
        DB::table('especiais_opcoes')->where('vencedora', true)->orderBy('id')
            ->each(function (object $opcao) {
                DB::table('especiais')->where('id', $opcao->especiais_id)->whereNull('especiais_opcoes_id_vencedora')
                    ->update(['especiais_opcoes_id_vencedora' => $opcao->id]);
            });

        Schema::table('especiais_opcoes', function (Blueprint $table) {
            $table->dropForeign(['anulada_por']);
            $table->dropColumn(['vencedora', 'empatada', 'anulada_em', 'anulada_por']);
        });

        Schema::table('especiais', function (Blueprint $table) {
            $table->dropColumn(['quantidade_vencedoras', 'dividir_empate']);
        });
    }
};
