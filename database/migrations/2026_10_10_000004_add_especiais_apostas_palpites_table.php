<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Palpite especial na aposta: sem confronto nem campeonato, com a categoria e a opção escolhida, e
 * o resultado do palpite (marcado ao encerrar a categoria).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('apostas_palpites', function (Blueprint $table) {
            $table->unsignedBigInteger('confrontos_id')->nullable()->change();
            $table->unsignedBigInteger('campeonatos_id')->nullable()->change();
            $table->foreignId('especiais_id')->nullable()->after('campeonatos_id')->constrained('especiais');
            $table->foreignId('especiais_opcoes_id')->nullable()->after('especiais_id')->constrained('especiais_opcoes');
            // resultado do palpite: Aguardando até a apuração (especial: ao encerrar a categoria)
            $table->string('resultado', 20)->default('Aguardando')->after('situacao');

            // um palpite por categoria especial na aposta
            $table->unique(['apostas_id', 'especiais_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('apostas_palpites', function (Blueprint $table) {
            $table->dropUnique(['apostas_id', 'especiais_id']);
            $table->dropConstrainedForeignId('especiais_opcoes_id');
            $table->dropConstrainedForeignId('especiais_id');
            $table->dropColumn('resultado');
            $table->unsignedBigInteger('confrontos_id')->nullable(false)->change();
            $table->unsignedBigInteger('campeonatos_id')->nullable(false)->change();
        });
    }
};
