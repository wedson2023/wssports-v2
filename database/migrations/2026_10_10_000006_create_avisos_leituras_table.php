<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Lido" dos avisos: por aparelho (identificador guardado no navegador) e, com sessão de cliente,
 * também pelo cliente. O IP fica só como registro (não esconde o aviso de outras pessoas da rede).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('avisos_leituras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('avisos_id')->constrained('avisos');
            // UUID do navegador
            $table->char('aparelho', 36);
            $table->foreignId('clientes_id')->nullable()->constrained('clientes');
            $table->string('ip', 45)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['avisos_id', 'aparelho']);
            $table->index(['avisos_id', 'clientes_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('avisos_leituras');
    }
};
