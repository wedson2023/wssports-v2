<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Acessos de admin e supervisor à conta de um cliente pelo painel (spec 007, US7): quem, qual
 * cliente, quando começou e terminou.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('clientes_acessos_assistidos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clientes_id')->constrained('clientes');
            $table->foreignId('usuarios_id')->constrained('usuarios');
            // SHA-256 do código de uso único que vai na URL
            $table->char('codigo_hash', 64)->unique();
            $table->timestamp('codigo_expira_em');
            $table->timestamp('iniciado_em')->nullable();
            $table->timestamp('expira_em')->nullable();
            $table->timestamp('encerrado_em')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes_acessos_assistidos');
    }
};
