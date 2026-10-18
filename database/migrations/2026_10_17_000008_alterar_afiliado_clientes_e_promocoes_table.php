<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Liga clientes e promoções ao afiliado por chave estrangeira no lugar do código em texto, criado
 * sem FK nas specs 002 e 007 porque a tabela de afiliados não existia (spec 012, R-09). Códigos sem
 * afiliado correspondente ficam sem ligação (FR-024).
 */
return new class extends Migration
{
    /**
     * Tabelas que guardavam o código do afiliado em texto.
     */
    private const TABELAS = [
        'clientes' => 'Afiliado que indicou o cliente pelo link (?user=); nulo = sem afiliado',
        'clientes_promocoes' => 'Afiliado dono da promoção: vale só para os clientes dele; nulo = promoção geral',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::TABELAS as $tabela => $comentario) {
            Schema::table($tabela, function (Blueprint $table) use ($comentario) {
                $table->foreignId('afiliados_id')->nullable()->after('codigo_afiliado')
                    ->comment($comentario)
                    ->constrained('afiliados');
            });

            DB::table($tabela)
                ->whereNotNull('codigo_afiliado')
                ->orderBy('id')
                ->chunkById(500, function ($registros) use ($tabela) {
                    foreach ($registros as $registro) {
                        $afiliados_id = DB::table('afiliados')
                            ->where('codigo', mb_strtoupper(trim($registro->codigo_afiliado)))
                            ->value('id');

                        if ($afiliados_id !== null) {
                            DB::table($tabela)->where('id', $registro->id)->update(['afiliados_id' => $afiliados_id]);
                        }
                    }
                });

            Schema::table($tabela, function (Blueprint $table) {
                $table->dropColumn('codigo_afiliado');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (array_keys(self::TABELAS) as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->string('codigo_afiliado', 50)->nullable()->after('afiliados_id');
            });

            DB::table($tabela)
                ->whereNotNull('afiliados_id')
                ->orderBy('id')
                ->chunkById(500, function ($registros) use ($tabela) {
                    foreach ($registros as $registro) {
                        $codigo = DB::table('afiliados')->where('id', $registro->afiliados_id)->value('codigo');

                        DB::table($tabela)->where('id', $registro->id)->update(['codigo_afiliado' => $codigo]);
                    }
                });

            Schema::table($tabela, function (Blueprint $table) {
                $table->dropConstrainedForeignId('afiliados_id');
            });
        }
    }
};
