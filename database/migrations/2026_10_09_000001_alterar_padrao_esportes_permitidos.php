<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Esportes permitidos por padrão (spec 005, pedido do responsável): todos os esportes do provedor
 * passam a vir liberados para visitantes, clientes e vendedores novos. Só muda o valor padrão da
 * coluna; as configurações já gravadas continuam como estão.
 */
return new class extends Migration
{
    /**
     * Tabelas com a lista de esportes permitidos.
     */
    private const TABELAS = ['visitantes_configuracoes', 'clientes_configuracoes', 'usuarios_configuracoes'];

    /**
     * Todos os esportes, com os nomes como o provedor envia.
     */
    private const TODOS = [
        'FUTEBOL', 'BASQUETE', 'LUTAS', 'VÔLEI', 'TÊNIS', 'TÊNIS DE MESA', 'E-SPORTS',
        'FUTEBOL AMERICANO', 'RUGBY', 'HÓQUEI NO GELO', 'HANDEBOL', 'BAISEBOL',
    ];

    /**
     * Padrão anterior (migrations de 2026-10-01).
     */
    private const ANTERIOR = ['FUTEBOL', 'HOQUEI NO GELO', 'BAISEBOL'];

    public function up(): void
    {
        $this->definir_padrao(self::TODOS);
    }

    public function down(): void
    {
        $this->definir_padrao(self::ANTERIOR);
    }

    /**
     * @param  list<string>  $esportes
     */
    private function definir_padrao(array $esportes): void
    {
        $lista = implode(',', array_map(fn (string $esporte) => "'{$esporte}'", $esportes));

        foreach (self::TABELAS as $tabela) {
            Schema::table($tabela, function (Blueprint $table) use ($lista) {
                $table->json('esportes_permitidos')->default(new Expression("(JSON_ARRAY({$lista}))"))->change();
            });
        }
    }
};
