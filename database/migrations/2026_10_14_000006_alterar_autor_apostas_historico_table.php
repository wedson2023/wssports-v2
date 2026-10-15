<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Histórico de edição sem autor quando a ação é do sistema (spec 009, R-08): a apuração roda no
 * agendador, sem usuário.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('apostas_historico', function (Blueprint $table) {
            $table->unsignedBigInteger('usuarios_id')->nullable()
                ->comment('Usuário que editou a aposta; nulo quando a ação foi do sistema (apuração)')
                ->change();
            $table->string('ip', 45)->nullable()
                ->comment('IP de quem editou; nulo quando a ação foi do sistema')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('apostas_historico', function (Blueprint $table) {
            $table->unsignedBigInteger('usuarios_id')->nullable(false)->comment('')->change();
            $table->string('ip', 45)->nullable(false)->comment('')->change();
        });
    }
};
