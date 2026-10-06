<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Não existe tabela padrão de configurações (decisão do responsável, spec 003): os valores
 * iniciais das configurações do cliente passam para o padrão das colunas e a tabela
 * clientes_configuracoes_padrao é apagada.
 */
return new class extends Migration
{
    /**
     * Valores que a spec 002 punha na tabela padrão (FR-050 da spec 002).
     */
    private const PADROES = [
        'realizar_aposta' => true,
        'apostar_ao_vivo' => true,
        'apostar_outros_esportes' => true,
        'cancelar_aposta' => false,
        'aceita_promocao' => true,
        'bloquear_saque' => false,
        'quantidade_minima_opcoes' => 1,
        'quantidade_maxima_opcoes' => 20,
        'valor_minimo_aposta' => 2.00,
        'valor_maximo_aposta' => 1000.00,
        'premio_maximo' => 50000.00,
        'valor_maximo_diario' => 5000.00,
        'valor_maximo_saque_diario' => 5000.00,
        'quantidade_maxima_saques_diaria' => 5,
        'odd_minima' => 1.90,
        'odd_maxima' => 30.00,
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clientes_configuracoes', function (Blueprint $table) {
            $this->colunas($table, com_padrao: true);
        });

        Schema::dropIfExists('clientes_configuracoes_padrao');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('clientes_configuracoes_padrao', function (Blueprint $table) {
            $table->id();
            $this->colunas($table, com_padrao: false, alterar: false);
            $table->timestamps();
            $table->softDeletes();
        });

        DB::table('clientes_configuracoes_padrao')->insert([
            ...self::PADROES,
            'esportes_permitidos' => json_encode(['FUTEBOL', 'HOQUEI NO GELO', 'BAISEBOL']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('clientes_configuracoes', function (Blueprint $table) {
            $this->colunas($table, com_padrao: false);
        });
    }

    /**
     * Colunas de configuração, com ou sem os valores padrão; alterar = mudar colunas existentes.
     */
    private function colunas(Blueprint $table, bool $com_padrao, bool $alterar = true): void
    {
        $definir = function ($coluna, string $nome) use ($com_padrao, $alterar) {
            if ($com_padrao) {
                $coluna->default($nome === 'esportes_permitidos'
                    ? new Expression("(JSON_ARRAY('FUTEBOL','HOQUEI NO GELO','BAISEBOL'))")
                    : self::PADROES[$nome]);
            }

            if ($alterar) {
                $coluna->change();
            }
        };

        foreach (['realizar_aposta', 'apostar_ao_vivo', 'apostar_outros_esportes', 'cancelar_aposta', 'aceita_promocao', 'bloquear_saque'] as $nome) {
            $definir($table->boolean($nome), $nome);
        }

        foreach (['quantidade_minima_opcoes', 'quantidade_maxima_opcoes'] as $nome) {
            $definir($table->unsignedSmallInteger($nome), $nome);
        }

        foreach (['valor_minimo_aposta', 'valor_maximo_aposta', 'premio_maximo', 'valor_maximo_diario', 'valor_maximo_saque_diario'] as $nome) {
            $definir($table->decimal($nome, 15, 2), $nome);
        }

        $definir($table->unsignedSmallInteger('quantidade_maxima_saques_diaria'), 'quantidade_maxima_saques_diaria');

        foreach (['odd_minima', 'odd_maxima'] as $nome) {
            $definir($table->decimal($nome, 8, 2), $nome);
        }

        // lista de nomes de esporte, sem chave estrangeira: o cadastro de esportes ainda não existe
        $definir($table->json('esportes_permitidos'), 'esportes_permitidos');
    }
};
