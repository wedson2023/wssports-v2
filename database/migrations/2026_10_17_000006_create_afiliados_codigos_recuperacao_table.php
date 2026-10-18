<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Códigos de "Esqueci minha senha" do afiliado, enviados por e-mail (spec 012, data-model §6, R-11).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('afiliados_codigos_recuperacao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('afiliados_id')
                ->comment('Afiliado que pediu a recuperação')
                ->constrained('afiliados');
            $table->string('codigo')
                ->comment('Hash do código de 6 dígitos');
            $table->unsignedTinyInteger('tentativas')->default(0)
                ->comment('Tentativas erradas; 5 invalidam o código');
            $table->dateTime('expira_em')
                ->comment('Validade do código (15 minutos depois do pedido)');
            $table->dateTime('usado_em')->nullable()
                ->comment('Quando o código foi usado para trocar a senha');
            $table->dateTime('invalidado_em')->nullable()
                ->comment('Quando o código deixou de valer (novo pedido ou excesso de tentativas)');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('afiliados_codigos_recuperacao');
    }
};
