<?php

use App\Models\Clientes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Código de indicação de cada cliente e quem o indicou (spec 007, US9).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            // código do link de indicação (?indicacao=CODIGO)
            $table->string('codigo_indicacao', 8)->nullable()->unique();
            $table->foreignId('indicado_por')->nullable()->constrained('clientes');
        });

        // clientes que já existem recebem um código
        DB::table('clientes')->whereNull('codigo_indicacao')->orderBy('id')->chunkById(500, function ($clientes) {
            foreach ($clientes as $cliente) {
                DB::table('clientes')->where('id', $cliente->id)->update(['codigo_indicacao' => Clientes::gerar_codigo_indicacao()]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('indicado_por');
            $table->dropUnique(['codigo_indicacao']);
            $table->dropColumn('codigo_indicacao');
        });
    }
};
