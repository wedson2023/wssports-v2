<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cada pedido de encerramento de uma aposta, aceito ou recusado (spec 010, FR-035).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('apostas_encerramentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apostas_id')
                ->comment('Aposta encerrada (total ou em parte)')
                ->constrained('apostas');
            $table->foreignId('clientes_id')
                ->comment('Cliente dono da aposta')
                ->constrained('clientes');
            $table->foreignId('usuarios_id')->nullable()
                ->comment('Usuário do painel que encerrou em acesso assistido; nulo quando foi o cliente ou o sistema')
                ->constrained('usuarios');
            $table->foreignId('apostas_encerramentos_automaticos_id')->nullable()
                ->comment('Encerramento automático que gerou este encerramento; nulo no manual')
                // nome curto: o padrão passaria do limite de 64 caracteres do MySQL
                ->constrained('apostas_encerramentos_automaticos', indexName: 'apostas_encerramentos_automatico_foreign');
            $table->string('modalidade', 20)
                ->comment('Modalidade: Total, Parcial ou Automático');
            $table->string('situacao', 20)
                ->comment('Situação: Em análise, Recusado, Registrado (aguardando a janela), Creditado ou Desfeito');
            $table->char('chave_idempotencia', 36)->unique()
                ->comment('Chave do pedido gerada pela página; o reenvio devolve o mesmo registro');
            $table->string('aceitar_alteracoes', 20)
                ->comment('Preferência usada na decisão: Nenhuma, Somente para maior ou Qualquer');
            $table->decimal('oferta_exibida', 15, 2)
                ->comment('Oferta total exibida ao cliente e assinada no token, em reais');
            $table->decimal('oferta', 15, 2)->nullable()
                ->comment('Oferta total recalculada na decisão, em reais; nula enquanto Em análise');
            $table->decimal('valor_pedido', 15, 2)
                ->comment('Valor que o cliente pediu para receber (no total, igual à oferta exibida), em reais');
            $table->decimal('valor', 15, 2)->nullable()
                ->comment('Valor encerrado que será creditado, em reais; nulo enquanto Em análise ou quando Recusado');
            $table->decimal('margem', 5, 2)
                ->comment('Margem da casa aplicada na oferta, em % (pré-jogo ou ao vivo)');
            $table->decimal('proporcao', 11, 10)->nullable()
                ->comment('Parte da aposta encerrada neste pedido, de 0 a 1 (1 no total)');
            $table->decimal('fracao_antes', 11, 10)
                ->comment('Fração da aposta que valia antes do encerramento (apostas.fracao_restante)');
            $table->decimal('fracao_depois', 11, 10)->nullable()
                ->comment('Fração da aposta que continua valendo depois; 0 no total');
            $table->decimal('valor_apostado_antes', 15, 2)
                ->comment('Valor apostado que valia antes (valor × fração), em reais');
            $table->decimal('valor_apostado_depois', 15, 2)->nullable()
                ->comment('Valor apostado que continua valendo depois, em reais');
            $table->decimal('premio_antes', 15, 2)
                ->comment('Total a pagar que valia antes (prêmio + acréscimo × fração), em reais');
            $table->decimal('premio_depois', 15, 2)->nullable()
                ->comment('Total a pagar que continua valendo depois, em reais');
            $table->json('palpites')
                ->comment('Fotografia por palpite em aberto: id, cotação original, cotação atual, placar, período, minuto, última atualização do ao vivo e se o jogo tinha resultado gravado');
            $table->json('rollovers')->nullable()
                ->comment('Valores retirados de cada rollover (clientes_rollovers_id e valor), para devolver se o encerramento for desfeito');
            $table->string('motivo_recusa', 255)->nullable()
                ->comment('Motivo da recusa (lance, oferta alterada, inelegível)');
            $table->timestamp('decidido_em')->nullable()
                ->comment('Quando o pedido foi aceito ou recusado');
            $table->timestamp('creditar_em')->nullable()
                ->comment('Quando o valor pode ser creditado (decisão + janela de pagamento da apuração)');
            $table->timestamp('creditado_em')->nullable()
                ->comment('Quando o valor entrou no saldo');
            $table->foreignId('clientes_transacoes_id')->nullable()
                ->comment('Transação de crédito do valor encerrado')
                ->constrained('clientes_transacoes');
            $table->timestamp('desfeito_em')->nullable()
                ->comment('Quando o encerramento foi desfeito (FR-033)');
            $table->string('motivo_desfeito', 255)->nullable()
                ->comment('Por que o encerramento foi desfeito');
            $table->foreignId('estorno_clientes_transacoes_id')->nullable()
                ->comment('Transação de estorno quando o encerramento desfeito já tinha sido creditado')
                ->constrained('clientes_transacoes');
            $table->string('ip', 45)->nullable()
                ->comment('IP do pedido; nulo no automático');
            $table->string('user_agent', 255)->nullable()
                ->comment('Aparelho (user agent) do pedido; nulo no automático');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['apostas_id', 'situacao']);
            $table->index(['situacao', 'creditar_em']);
            $table->index(['clientes_id', 'created_at']);
            $table->index(['modalidade', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('apostas_encerramentos');
    }
};
