<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Afiliados: parceiros que indicam clientes pelo link e recebem comissão (spec 012, data-model §1).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('afiliados', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 150)
                ->comment('Nome completo do afiliado');
            // login, email, cpf e codigo recebem o sufixo _deleted_<timestamp> na exclusão, por isso o tamanho maior
            $table->string('login', 80)->unique()
                ->comment('Login de acesso; único também entre os usuários do painel, sem diferenciar maiúsculas');
            $table->string('password')
                ->comment('Hash da senha (mínimo 8 caracteres, com letras e números)');
            $table->string('email', 150)->nullable()->unique()
                ->comment('E-mail em minúsculas; obrigatório em cadastro novo, pode faltar nos migrados');
            $table->string('cpf', 40)->nullable()->unique()
                ->comment('CPF só com dígitos; obrigatório em cadastro novo, pode faltar nos migrados');
            $table->string('ddi', 3)->default('55')
                ->comment('Código do país do telefone (55 = Brasil)');
            $table->string('telefone', 20)->nullable()
                ->comment('Telefone só com dígitos; obrigatório em cadastro novo, pode faltar nos migrados');
            $table->string('cidade', 100)->nullable()
                ->comment('Cidade do afiliado');
            $table->char('estado', 2)->nullable()
                ->comment('UF do afiliado ou EX (estrangeiro)');
            $table->string('endereco', 255)->nullable()
                ->comment('Endereço do afiliado');
            $table->string('codigo', 80)->unique()
                ->comment('Código público do link de indicação (?user=), em maiúsculas');
            $table->string('situacao', 20)
                ->comment('Situação: Em análise, Ativo, Inativo ou Recusado; só Ativo entra e recebe clientes novos');
            $table->string('motivo_recusa', 255)->nullable()
                ->comment('Motivo informado na recusa do cadastro público');
            $table->string('origem_cadastro', 20)
                ->comment('Origem do cadastro: Painel ou Público');
            $table->foreignId('usuarios_id')->nullable()
                ->comment('Gerente responsável pelo afiliado (função Gerente); nulo = sem responsável')
                ->constrained('usuarios');
            $table->foreignId('cadastrado_por')->nullable()
                ->comment('Usuário do painel que cadastrou; nulo no cadastro público')
                ->constrained('usuarios');
            $table->foreignId('decidido_por')->nullable()
                ->comment('Usuário do painel que aprovou ou recusou o cadastro público')
                ->constrained('usuarios');
            $table->dateTime('decidido_em')->nullable()
                ->comment('Quando o cadastro foi aprovado ou recusado');
            $table->timestamp('tokens_validos_desde')->nullable()
                ->comment('Tokens emitidos antes desta data deixam de valer (inativação, exclusão, troca de senha)');
            $table->timestamps();
            $table->softDeletes();

            $table->index('situacao');
            $table->index('nome');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('afiliados');
    }
};
