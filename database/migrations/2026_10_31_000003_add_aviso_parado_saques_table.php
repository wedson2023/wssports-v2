<?php

use App\Enums\SituacaoSaque;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aviso único de saque parado (spec 025, research R-06). Os pendentes que já existem saem marcados,
 * para a primeira execução não mandar uma rajada de avisos antigos.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('saques', function (Blueprint $table) {
            $table->timestamp('aviso_parado_em')->nullable()
                ->comment('Quando o aviso de saque parado há 30 minutos foi despachado ao Admin');

            $table->index(['situacao', 'aviso_parado_em', 'created_at']);
        });

        DB::table('saques')
            ->where('situacao', SituacaoSaque::Pendente->value)
            ->update(['aviso_parado_em' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('saques', function (Blueprint $table) {
            $table->dropIndex(['situacao', 'aviso_parado_em', 'created_at']);
            $table->dropColumn('aviso_parado_em');
        });
    }
};
