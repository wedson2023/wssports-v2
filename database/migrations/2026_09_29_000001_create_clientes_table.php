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
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 150);
            $table->string('ddi', 3)->default('55');
            // telefone, cpf e email recebem o sufixo _deleted_<timestamp> na exclusão, por isso o tamanho maior
            $table->string('telefone', 40);
            $table->string('email', 150)->nullable()->unique();
            $table->string('password');
            $table->string('cpf', 40)->nullable()->unique();
            $table->date('data_nascimento');
            $table->string('genero', 20);
            // sem chave estrangeira: a tabela de afiliados ainda não existe (constituição v1.10.0)
            $table->string('codigo_afiliado', 50)->nullable();
            $table->boolean('ativo')->default(true);
            $table->decimal('saldo', 15, 2)->default(0);
            $table->decimal('saldo_promocao_esportes', 15, 2)->default(0);
            $table->decimal('saldo_promocao_cassino', 15, 2)->default(0);
            // tokens emitidos antes desta data deixam de ser aceitos
            $table->timestamp('tokens_validos_desde')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['ddi', 'telefone']);
            $table->index('nome');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
