// definições dos campos da tela Configurações (spec 021, research R-17): cada seção é dado, não
// componente; o SettingsForm desenha os campos pelo tipo.
//
// campo = { chave, rotulo, tipo, ajuda?, opcoes?, maximo?, exemplo?, inteiro?, somente_leitura?,
//           visivel?(valores), formatar?(valor, valores), texto_ligado?, texto_desligado? }
// tipos: interruptor, sim_nao (seleção Sim/Não de um campo verdadeiro/falso, usada na Operação),
//        valor (R$), percentual (%), decimal, inteiro, selecao, chips, texto, texto_longo, telefone,
//        link, data, segredo, informacao (só leitura)

// ---- aba Banca ----

export const campos_identificacao = [
    { chave: 'nome_sistema', rotulo: 'Nome do sistema', tipo: 'texto', maximo: 100, ajuda: 'Aparece no título do site, no bilhete e nos e-mails.' },
    { chave: 'mensagem_bilhete', rotulo: 'Mensagem do bilhete', tipo: 'texto_longo', maximo: 500, ajuda: 'Sai no fim do bilhete das apostas sem vendedor.' },
];

export const campos_atendimento = [
    { chave: 'whatsapp', rotulo: 'WhatsApp de atendimento', tipo: 'telefone', exemplo: '5511999999999', ajuda: 'Com DDI e DDD, só números (ex.: 5511999999999). Vazio = sem WhatsApp no site.' },
    { chave: 'botao_whatsapp', rotulo: 'Botão flutuante do WhatsApp', tipo: 'interruptor', texto_ligado: 'Aparece no site', texto_desligado: 'Escondido', ajuda: 'Só aparece com o número preenchido.' },
    { chave: 'mensagem_whatsapp', rotulo: 'Mensagem inicial', tipo: 'texto_longo', maximo: 500, inteiro: true, ajuda: 'Texto que já vem escrito quando o apostador abre o WhatsApp.' },
];

export const campos_redes = [
    { chave: 'instagram', rotulo: 'Instagram', tipo: 'link' },
    { chave: 'facebook', rotulo: 'Facebook', tipo: 'link' },
    { chave: 'twitter', rotulo: 'Twitter/X', tipo: 'link' },
    { chave: 'youtube', rotulo: 'YouTube', tipo: 'link' },
    { chave: 'jogo_responsavel', rotulo: 'Jogo responsável', tipo: 'link', inteiro: true, ajuda: 'Link da imagem de jogo responsável no rodapé. Vazio = imagem sem link.' },
];

export const campos_rastreamento = [
    { chave: 'gtm_id', rotulo: 'GTM-ID (Google Tag Manager)', tipo: 'texto', exemplo: 'GTM-XXXXXXX', maximo: 20, ajuda: 'Deixe vazio para não usar. Carregado só nas páginas do apostador.' },
];

// ---- aba Operação (FR-028 e FR-029): uma sanfona por seção, cada uma com a sua rota ----

// "2026-10-27T13:00:00-03:00" → "27/10/2026 13:00"
const data_hora = (data) => {
    if (!data) return null;

    const [dia, hora] = data.split('T');

    return `${dia.split('-').reverse().join('/')} ${hora.slice(0, 5)}`;
};

export const secoes_operacao = [
    {
        chave: 'pagamentos',
        rota: '/configuracoes/pagamentos',
        titulo: 'Pagamentos',
        icone: 'payments',
        descricao: 'Depósitos, saques, Pix, cashback e rollover do depósito.',
        campos: [
            { chave: 'depositos_habilitados', rotulo: 'Depósitos', tipo: 'sim_nao' },
            { chave: 'saques_habilitados', rotulo: 'Saques', tipo: 'sim_nao' },
            { chave: 'meios_deposito', rotulo: 'Meios de depósito', tipo: 'chips', opcoes: ['Pix', 'Cartão de crédito'], inteiro: true, ajuda: 'Pelo menos um.' },
            { chave: 'deposito_minimo', rotulo: 'Depósito mínimo', tipo: 'valor', ajuda: 'A partir de R$ 1,00 e até o máximo.' },
            { chave: 'deposito_maximo', rotulo: 'Depósito máximo', tipo: 'valor' },
            { chave: 'saque_minimo', rotulo: 'Saque mínimo', tipo: 'valor' },
            { chave: 'modo_saque', rotulo: 'Modo de saque', tipo: 'selecao', opcoes: ['Manual', 'Automático'] },
            { chave: 'saque_automatico_maximo', rotulo: 'Saque automático até', tipo: 'valor', ajuda: 'Acima disso, o saque espera aprovação no painel.', visivel: (valores) => valores.modo_saque === 'Automático' },
            { chave: 'cashback_percentual', rotulo: 'Cashback', tipo: 'percentual', ajuda: 'De 0 a 100% do depósito.' },
            { chave: 'rollover_deposito', rotulo: 'Rollover do depósito', tipo: 'sim_nao' },
            { chave: 'rollover_deposito_vezes', rotulo: 'Vezes do rollover', tipo: 'inteiro', ajuda: 'De 1 a 20 vezes o valor depositado.', visivel: (valores) => valores.rollover_deposito },
            { chave: 'parcelas_maximas', rotulo: 'Parcelas no cartão (máximo)', tipo: 'inteiro', ajuda: 'De 1 a 12.' },
            { chave: 'minutos_expiracao_pix', rotulo: 'Validade do Pix (minutos)', tipo: 'inteiro', ajuda: 'De 5 a 1440 minutos.' },
            { chave: 'minutos_acesso_assistido', rotulo: 'Acesso assistido (minutos)', tipo: 'inteiro', ajuda: 'De 5 a 480 minutos.' },
            { chave: 'gateway_pagamento', rotulo: 'Gateway de pagamento', tipo: 'informacao', formatar: (valor) => valor ?? 'Nenhum (vincule na aba Integrações)' },
        ],
    },
    {
        chave: 'criar_aposta',
        rota: '/configuracoes/criar-aposta',
        titulo: 'Criar Aposta',
        icone: 'post_add',
        descricao: 'Apostas montadas pelo apostador com vários mercados do mesmo jogo.',
        campos: [
            { chave: 'criar_aposta_habilitado', rotulo: 'Criar Aposta', tipo: 'sim_nao' },
            { chave: 'desconto_criar_aposta', rotulo: 'Desconto na cotação', tipo: 'percentual', ajuda: 'De 0 a 100%.' },
            { chave: 'quantidade_minima_criar_aposta', rotulo: 'Mínimo de seleções', tipo: 'inteiro', ajuda: 'De 2 a 20.' },
            { chave: 'quantidade_maxima_criar_aposta', rotulo: 'Máximo de seleções', tipo: 'inteiro', ajuda: 'De 2 a 20.' },
            { chave: 'liquidacao_criar_aposta', rotulo: 'Quando um mercado é anulado', tipo: 'selecao', opcoes: ['Recalcular', 'Anular bloco'] },
        ],
    },
    {
        chave: 'apuracao',
        rota: '/configuracoes/apuracao',
        titulo: 'Apuração',
        icone: 'fact_check',
        descricao: 'Jogos cancelados e prazo para pagar os prêmios.',
        campos: [
            { chave: 'remover_confronto_cancelado', rotulo: 'Remover jogos adiados e cancelados', tipo: 'sim_nao' },
            { chave: 'minutos_pagamento_premio', rotulo: 'Pagar prêmios depois de (minutos)', tipo: 'inteiro', ajuda: 'De 0 a 1440 minutos.' },
        ],
    },
    {
        chave: 'encerramento',
        rota: '/configuracoes/encerramento',
        titulo: 'Encerramento de aposta',
        icone: 'price_check',
        descricao: 'Cash out: quando o apostador pode encerrar e quanto recebe.',
        campos: [
            { chave: 'encerramento_habilitado', rotulo: 'Encerramento', tipo: 'sim_nao' },
            { chave: 'encerramento_parcial_habilitado', rotulo: 'Encerramento parcial', tipo: 'sim_nao' },
            { chave: 'encerramento_automatico_habilitado', rotulo: 'Encerramento automático', tipo: 'sim_nao' },
            { chave: 'encerramento_jogos_nao_iniciados', rotulo: 'Encerrar antes do jogo começar', tipo: 'sim_nao' },
            { chave: 'margem_encerramento_pre_jogo', rotulo: 'Margem pré-jogo', tipo: 'percentual', ajuda: 'De 0 a 50%.' },
            { chave: 'margem_encerramento_ao_vivo', rotulo: 'Margem ao vivo', tipo: 'percentual', ajuda: 'De 0 a 50%.' },
            { chave: 'valor_minimo_encerramento', rotulo: 'Valor mínimo da oferta', tipo: 'valor', ajuda: 'De R$ 0,01 a R$ 10.000,00.' },
            { chave: 'percentual_minimo_encerramento', rotulo: 'Oferta mínima (% do apostado)', tipo: 'percentual', ajuda: 'De 0 a 100%.' },
            { chave: 'segundos_validade_oferta', rotulo: 'Validade da oferta (segundos)', tipo: 'inteiro', ajuda: 'De 3 a 60 segundos.' },
            { chave: 'limite_encerramentos_parciais', rotulo: 'Encerramentos parciais por aposta', tipo: 'inteiro', ajuda: 'De 1 a 50.' },
        ],
    },
    {
        chave: 'cassino',
        rota: '/configuracoes/cassino',
        titulo: 'Cassino',
        icone: 'casino',
        descricao: 'Cassino no site, modo demonstração e prestação de contas.',
        campos: [
            { chave: 'cassino_habilitado', rotulo: 'Cassino', tipo: 'sim_nao' },
            { chave: 'cassino_demo_habilitado', rotulo: 'Modo demonstração', tipo: 'sim_nao' },
            { chave: 'data_prestacao_contas_cassino', rotulo: 'Data da prestação de contas', tipo: 'data' },
            { chave: 'dias_registro_chamadas_cassino', rotulo: 'Guardar chamadas do provedor (dias)', tipo: 'inteiro', ajuda: 'De 1 a 90 dias.' },
            { chave: 'url_callback', rotulo: 'Endereço de retorno para o provedor', tipo: 'informacao', inteiro: true },
        ],
    },
    {
        chave: 'afiliados',
        rota: '/configuracoes/afiliados',
        titulo: 'Afiliados',
        icone: 'handshake',
        descricao: 'Regra de comissão de quem se cadastra pela página de afiliados.',
        campos: [
            { chave: 'afiliados_tipo_ganho_padrao', rotulo: 'Comissão sobre', tipo: 'selecao', opcoes: ['Saldo líquido', 'Qualquer depósito', 'Primeiro depósito'] },
            { chave: 'afiliados_percentual_padrao', rotulo: 'Percentual da comissão', tipo: 'percentual', ajuda: 'De 0 a 100%.' },
        ],
    },
    {
        chave: 'avisos_whatsapp',
        rota: '/configuracoes/avisos-whatsapp',
        titulo: 'Aviso de combinação (WhatsApp)',
        icone: 'notification_important',
        descricao: 'Aviso no WhatsApp do Admin quando aparece uma combinação de risco nova (CallMeBot).',
        campos: [
            { chave: 'aviso_combinacao_whatsapp', rotulo: 'Aviso no WhatsApp', tipo: 'sim_nao' },
            { chave: 'telefone_whatsapp_admin', rotulo: 'WhatsApp do Admin', tipo: 'telefone', exemplo: '5582999999999', ajuda: 'Com DDI e DDD, só números.' },
            { chave: 'chave_callmebot', rotulo: 'Chave do CallMeBot', tipo: 'segredo' },
        ],
    },
    {
        chave: 'ao_vivo',
        rota: '/configuracoes/ao-vivo',
        titulo: 'Ao vivo',
        icone: 'live_tv',
        descricao: 'Apostas em jogos que estão acontecendo.',
        campos: [
            { chave: 'ao_vivo_habilitado', rotulo: 'Ao vivo', tipo: 'sim_nao' },
            { chave: 'segundos_trava_ao_vivo', rotulo: 'Trava na mudança de cotação (segundos)', tipo: 'inteiro', ajuda: 'De 0 a 120 segundos.' },
            { chave: 'minutos_permanencia_ao_vivo', rotulo: 'Permanência sem atualização (minutos)', tipo: 'inteiro', ajuda: 'De 1 a 60 minutos.' },
            { chave: 'minuto_limite_ao_vivo', rotulo: 'Aceitar apostas até o minuto', tipo: 'inteiro', ajuda: 'De 1 a 130.' },
            { chave: 'cotacao_maxima_ao_vivo', rotulo: 'Cotação máxima', tipo: 'decimal', ajuda: 'De 1,01 a 1.000.' },
            {
                chave: 'ao_vivo_travado',
                rotulo: 'Trava geral (conferência)',
                tipo: 'informacao',
                inteiro: true,
                formatar: (valor, valores) => (valor ? `Travado desde ${data_hora(valores.ao_vivo_travado_em) ?? '—'}` : 'Liberado'),
            },
        ],
    },
    {
        chave: 'sorteio_cotacoes',
        rota: '/configuracoes/sorteio-cotacoes',
        titulo: 'Sorteio de cotação',
        icone: 'casino',
        descricao: 'Cotação sorteada de "ambas marcam" quando o provedor não manda (antigos ADD ambas).',
        campos: [
            { chave: 'sorteio_ambas_marcam_minimo', rotulo: 'Ambas marcam — mínimo', tipo: 'decimal' },
            { chave: 'sorteio_ambas_marcam_maximo', rotulo: 'Ambas marcam — máximo', tipo: 'decimal' },
            { chave: 'sorteio_ambas_nao_marcam_minimo', rotulo: 'Ambas não marcam — mínimo', tipo: 'decimal' },
            { chave: 'sorteio_ambas_nao_marcam_maximo', rotulo: 'Ambas não marcam — máximo', tipo: 'decimal' },
        ],
    },
    {
        chave: 'notificacoes',
        rota: '/clientes/notificacoes/limite',
        titulo: 'Notificações manuais',
        icone: 'notifications_active',
        descricao: 'Quantas notificações push a banca pode enviar por dia.',
        somente_leitura: (valores) => !valores.pode_alterar,
        aviso_leitura: 'Só o administrador pode alterar o limite.',
        campos: [
            { chave: 'limite', rotulo: 'Limite por dia', tipo: 'inteiro', ajuda: 'De 1 a 50 envios.' },
            { chave: 'enviados_hoje', rotulo: 'Enviados hoje', tipo: 'informacao' },
        ],
    },
];
