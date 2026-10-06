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
        // cotações de jogadores de cada confronto (antiga "atletas")
        Schema::create('confrontos_jogadores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('confrontos_id')->constrained('confrontos');
            $table->unsignedBigInteger('codigo_externo');
            $table->string('nome', 150);
            $table->string('opcao', 60);
            $table->string('tipo', 60);
            $table->decimal('odd', 8, 2);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['confrontos_id', 'codigo_externo', 'tipo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('confrontos_jogadores');
    }
};
