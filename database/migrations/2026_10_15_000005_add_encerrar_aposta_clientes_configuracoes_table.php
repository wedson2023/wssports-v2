<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permissão do cliente para encerrar as próprias apostas (spec 010, FR-036).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clientes_configuracoes', function (Blueprint $table) {
            $table->boolean('encerrar_aposta')->default(true)
                ->comment('Permite ao cliente encerrar as próprias apostas');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clientes_configuracoes', function (Blueprint $table) {
            $table->dropColumn('encerrar_aposta');
        });
    }
};
