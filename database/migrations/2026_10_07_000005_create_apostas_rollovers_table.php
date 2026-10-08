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
        // quanto cada aposta somou em cada rollover, para o cancelamento desfazer exatamente
        Schema::create('apostas_rollovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apostas_id')->constrained('apostas');
            $table->foreignId('clientes_rollovers_id')->constrained('clientes_rollovers');
            $table->decimal('valor', 15, 2);
            $table->timestamp('desfeito_em')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['apostas_id', 'clientes_rollovers_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('apostas_rollovers');
    }
};
