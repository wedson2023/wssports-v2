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
        Schema::create('clientes_codigos_recuperacao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clientes_id')->constrained('clientes');
            // hash do código de 6 dígitos
            $table->string('codigo');
            $table->unsignedTinyInteger('tentativas')->default(0);
            $table->dateTime('expira_em');
            $table->dateTime('usado_em')->nullable();
            $table->dateTime('invalidado_em')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes_codigos_recuperacao');
    }
};
