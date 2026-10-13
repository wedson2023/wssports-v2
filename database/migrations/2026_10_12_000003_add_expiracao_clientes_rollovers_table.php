<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Prazo do bônus e fim do rollover por expiração, exclusão ou conversão (spec 007, FR-018a).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clientes_rollovers', function (Blueprint $table) {
            // só bônus: data limite para cumprir o rollover
            $table->timestamp('expira_em')->nullable();
            $table->timestamp('expirado_em')->nullable();
            $table->timestamp('excluido_em')->nullable();
            $table->timestamp('convertido_em')->nullable();

            $table->index(['tipo', 'expira_em']);
        });

        // bônus já entregues ganham o prazo padrão de 30 dias a partir do recebimento
        DB::table('clientes_rollovers')
            ->where('tipo', 'Bônus')
            ->whereNull('cumprido_em')
            ->whereNull('cancelado_em')
            ->update(['expira_em' => DB::raw('DATE_ADD(created_at, INTERVAL 30 DAY)')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clientes_rollovers', function (Blueprint $table) {
            $table->dropIndex(['tipo', 'expira_em']);
            $table->dropColumn(['expira_em', 'expirado_em', 'excluido_em', 'convertido_em']);
        });
    }
};
