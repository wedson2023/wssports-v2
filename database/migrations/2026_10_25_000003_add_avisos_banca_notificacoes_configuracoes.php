<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Avisos manuais da banca (spec 019, R-10, data-model §2): preferência do aparelho, ligação do aviso
 * com o envio manual e o limite de envios por dia.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clientes_inscricoes_notificacoes', function (Blueprint $table) {
            $table->boolean('receber_avisos_banca')->default(true)->after('receber_encerramento_automatico')
                ->comment('Se o aparelho recebe os avisos manuais da banca (tipo Aviso da banca)');
        });

        Schema::table('clientes_notificacoes', function (Blueprint $table) {
            $table->foreignId('clientes_notificacoes_envios_id')->nullable()->after('apostas_id')
                ->comment('Envio manual que gerou o aviso; nulo nos avisos automáticos')
                ->constrained('clientes_notificacoes_envios');
        });

        Schema::table('configuracoes', function (Blueprint $table) {
            $table->unsignedTinyInteger('limite_notificacoes_manuais_dia')->default(3)
                ->comment('Máximo de envios manuais de notificação push por dia, para a banca toda (1 a 50)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('configuracoes', function (Blueprint $table) {
            $table->dropColumn('limite_notificacoes_manuais_dia');
        });

        Schema::table('clientes_notificacoes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('clientes_notificacoes_envios_id');
        });

        Schema::table('clientes_inscricoes_notificacoes', function (Blueprint $table) {
            $table->dropColumn('receber_avisos_banca');
        });
    }
};
