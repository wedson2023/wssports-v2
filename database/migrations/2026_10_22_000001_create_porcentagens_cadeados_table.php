<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cadeado por cotação: Admin ou Supervisor tranca um código na tela de um usuário da rede, e ele e
 * quem está abaixo dele não alteram mais a porcentagem daquele código (spec 016, FR-019).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('porcentagens_cadeados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuarios_id')
                ->comment('Usuário travado: o cadeado vale para ele e para todos abaixo dele')
                ->constrained('usuarios');
            $table->foreignId('trancado_por')
                ->comment('Quem trancou (Admin ou Supervisor); só ele ou um superior dele destranca')
                ->constrained('usuarios');
            $table->string('camada', 20)
                ->comment('Camada travada: vendedores (porcentagens do usuário) ou campeonatos (regra de campeonato com alvo Vendedores do usuário)');
            $table->string('tipo', 10)->nullable()
                ->comment('pre_jogo ou ao_vivo na camada vendedores; nulo em campeonatos (a regra do campeonato vale para os dois)');
            $table->foreignId('campeonatos_id')->nullable()
                ->comment('Campeonato da camada campeonatos; nulo em vendedores')
                ->constrained('campeonatos');
            $table->string('codigo', 10)
                ->comment('Código trancado: odd1 a odd323 ou jogador');
            // o MySQL não considera dois nulos iguais num índice único: as colunas geradas evitam cadeados repetidos
            $table->string('chave_tipo', 10)->storedAs("COALESCE(tipo, '')")
                ->comment('Tipo sem nulo, só para o índice único');
            $table->unsignedBigInteger('chave_campeonato')->storedAs('COALESCE(campeonatos_id, 0)')
                ->comment('Campeonato sem nulo (0), só para o índice único');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['usuarios_id', 'trancado_por', 'camada', 'chave_tipo', 'chave_campeonato', 'codigo'], 'porcentagens_cadeados_unico');
            $table->index(['camada', 'usuarios_id', 'codigo'], 'porcentagens_cadeados_consulta');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('porcentagens_cadeados');
    }
};
