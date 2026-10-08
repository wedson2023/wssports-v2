<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // edições de palpite feitas pelo painel (cancelar e restaurar)
        Schema::create('apostas_historico', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apostas_id')->constrained('apostas');
            $table->foreignId('apostas_palpites_id')->constrained('apostas_palpites');
            $table->string('acao', 30);
            $table->decimal('cotacao_total_anterior', 14, 2);
            $table->decimal('cotacao_total_posterior', 14, 2);
            $table->decimal('premio_anterior', 15, 2);
            $table->decimal('premio_posterior', 15, 2);
            $table->decimal('valor_acrescido_anterior', 15, 2);
            $table->decimal('valor_acrescido_posterior', 15, 2);
            $table->foreignId('usuarios_id')->constrained('usuarios');
            $table->string('ip', 45);
            $table->string('user_agent')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['apostas_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('apostas_historico');
    }
};
