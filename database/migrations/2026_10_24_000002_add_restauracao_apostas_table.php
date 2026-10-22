<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restauração de aposta cancelada pelo painel (spec 018, R-13): quem restaurou, quando, de onde. Os
 * dados do último cancelamento continuam gravados, para o histórico.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('apostas', function (Blueprint $table) {
            $table->timestamp('restaurada_em')->nullable()->after('user_agent_cancelamento')
                ->comment('Quando a aposta cancelada foi restaurada (voltou a Ativa) pela última vez');
            $table->foreignId('restaurada_por')->nullable()->after('restaurada_em')->constrained('usuarios')
                ->comment('Usuário do painel que restaurou a aposta cancelada');
            $table->string('ip_restauracao', 45)->nullable()->after('restaurada_por')
                ->comment('IP de quem restaurou a aposta');
            $table->string('user_agent_restauracao')->nullable()->after('ip_restauracao')
                ->comment('Navegador de quem restaurou a aposta (até 255 caracteres)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('apostas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('restaurada_por');
            $table->dropColumn(['restaurada_em', 'ip_restauracao', 'user_agent_restauracao']);
        });
    }
};
