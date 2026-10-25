<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices da tela Bônus (spec 020, R-13): promoções por afiliado e nome, rollover pelo crédito do
 * bônus e movimentos de bônus por período. `clientes_transacoes (origem, referencia_id)` já existe.
 */
return new class extends Migration
{
    /**
     * Tabela => colunas de cada índice criado aqui.
     *
     * @var list<array{0: string, 1: list<string>}>
     */
    private const INDICES = [
        ['clientes_promocoes', ['afiliados_id', 'data_inicio']],
        ['clientes_promocoes', ['nome']],
        ['clientes_rollovers', ['clientes_transacoes_id']],
        ['clientes_transacoes', ['origem', 'carteira', 'created_at']],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::INDICES as [$tabela, $colunas]) {
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
        foreach (self::INDICES as [$tabela, $colunas]) {
            if (Schema::hasIndex($tabela, $colunas)) {
                Schema::table($tabela, fn (Blueprint $table) => $table->dropIndex($colunas));
            }
        }
    }
};
