<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices da tela Clientes (spec 019, R-14): saldo líquido, filtros de depósito, ranking e saques por
 * cliente. `clientes.afiliados_id` e `afiliados.usuarios_id` já têm índice pela chave estrangeira.
 */
return new class extends Migration
{
    /**
     * Tabela => colunas de cada índice criado aqui.
     *
     * @var array<string, list<string>>
     */
    private const INDICES = [
        'depositos' => ['clientes_id', 'situacao', 'pago_em'],
        'saques' => ['clientes_id', 'situacao', 'pago_em'],
        'clientes_transacoes' => ['clientes_id', 'origem', 'carteira'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::INDICES as $tabela => $colunas) {
            if (! Schema::hasIndex($tabela, $colunas)) {
                Schema::table($tabela, fn (Blueprint $table) => $table->index($colunas));
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::INDICES as $tabela => $colunas) {
            if (Schema::hasIndex($tabela, $colunas)) {
                Schema::table($tabela, fn (Blueprint $table) => $table->dropIndex($colunas));
            }
        }
    }
};
