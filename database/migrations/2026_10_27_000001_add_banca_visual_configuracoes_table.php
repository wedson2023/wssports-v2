<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configurações gerais do painel (spec 021, data-model §1): contatos, tema, GTM, ícones do aplicativo
 * e situação do robô do WhatsApp. Os valores iniciais do tema e dos textos são os do antigo dado fake;
 * o WhatsApp e as redes começam vazios (o número do fake era inventado).
 */
return new class extends Migration
{
    /**
     * Colunas criadas por esta migration.
     */
    private const COLUNAS = [
        'whatsapp', 'mensagem_whatsapp', 'botao_whatsapp', 'instagram', 'facebook', 'twitter', 'youtube',
        'jogo_responsavel', 'cor_tema', 'cor_fundo', 'gtm_id', 'pasta_icones', 'robo_whatsapp_situacao',
        'robo_whatsapp_numero', 'robo_whatsapp_perfil', 'robo_whatsapp_atualizado_em',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('configuracoes', function (Blueprint $table) {
            $table->string('whatsapp', 20)->nullable()
                ->comment('Telefone do WhatsApp de atendimento, só dígitos com DDI (ex.: 5511999999999); vazio = sem botão nem atalhos');
            $table->string('mensagem_whatsapp', 500)->nullable()
                ->default('Olá! Vim pelo site e gostaria de fazer uma aposta esportiva. Pode me ajudar?')
                ->comment('Mensagem inicial ao abrir o WhatsApp de atendimento');
            $table->boolean('botao_whatsapp')->default(true)
                ->comment('Se o botão flutuante do WhatsApp aparece no site (só com número preenchido)');
            $table->string('instagram', 255)->nullable()
                ->comment('Link do Instagram da banca (http/https); vazio = ícone escondido');
            $table->string('facebook', 255)->nullable()
                ->comment('Link do Facebook da banca (http/https); vazio = ícone escondido');
            $table->string('twitter', 255)->nullable()
                ->comment('Link do Twitter/X da banca (http/https); vazio = ícone escondido');
            $table->string('youtube', 255)->nullable()
                ->comment('Link do YouTube da banca (http/https); vazio = ícone escondido');
            $table->string('jogo_responsavel', 255)->nullable()
                ->default('https://www.gamblingtherapy.org/pt-br/')
                ->comment('Link de jogo responsável do rodapé; vazio = imagem sem link');
            $table->string('cor_tema', 7)->default('#c40808')
                ->comment('Cor principal do tema: #c40808, #d0af01, #008000, #006eb1, #fe6a00 ou #b91552');
            $table->string('cor_fundo', 7)->default('#000000')
                ->comment('Cor de fundo padrão do site: #000000 (escuro) ou #FFFFFF (claro)');
            $table->string('gtm_id', 20)->nullable()
                ->comment('Contêiner do Google Tag Manager (GTM-XXXX); vazio = sem GTM');
            $table->string('pasta_icones', 100)->nullable()
                ->comment('Pasta (disco public) dos ícones do aplicativo gerados da logo; vazio = ícones padrão');
            $table->string('robo_whatsapp_situacao', 30)->default('Desconectado')
                ->comment('Situação do robô na Evolution API: Desconectado, Aguardando leitura ou Conectado');
            $table->string('robo_whatsapp_numero', 20)->nullable()
                ->comment('Número conectado ao robô (só dígitos)');
            $table->string('robo_whatsapp_perfil', 100)->nullable()
                ->comment('Nome do perfil do WhatsApp conectado');
            $table->timestamp('robo_whatsapp_atualizado_em')->nullable()
                ->comment('Última mudança de situação do robô');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('configuracoes', function (Blueprint $table) {
            $table->dropColumn(self::COLUNAS);
        });
    }
};
