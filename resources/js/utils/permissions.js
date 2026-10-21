// nomes amigáveis, descrições e grupos das permissões do painel (spec 015, data-model §5): lugar
// único dos textos. QUAIS permissões aparecem para cada usuário vem do servidor (disponiveis); aqui
// não há regra de função

// ordem dos grupos no modal de permissões
export const grupos_permissoes = [
    'Usuários',
    'Clientes',
    'Confrontos e cotações',
    'Apostas',
    'Especiais',
    'Site e configurações',
    'Pagamentos',
    'Cassino',
    'Afiliados',
    'Dashboard',
];

// grupo das permissões sem texto cadastrado aqui (aparecem com o próprio nome)
export const grupo_outras = 'Outras';

const usuarios = (acao, funcao, descricao) => ({ grupo: 'Usuários', nome: `${acao} ${funcao}`, descricao });

export const permissoes = {
    // Usuários
    'usuarios.listar': { grupo: 'Usuários', nome: 'Ver usuários', descricao: 'Abre as listas de usuários da sua rede.' },
    'usuarios.consultar': { grupo: 'Usuários', nome: 'Consultar usuário', descricao: 'Vê os dados e as configurações de um usuário da rede.' },
    'usuarios.cadastrar_supervisor': usuarios('Cadastrar', 'supervisor', 'Cria supervisores.'),
    'usuarios.cadastrar_gerente': usuarios('Cadastrar', 'gerente', 'Cria gerentes na sua rede.'),
    'usuarios.cadastrar_vendedor': usuarios('Cadastrar', 'vendedor', 'Cria vendedores na sua rede.'),
    'usuarios.editar_supervisor': usuarios('Editar', 'supervisor', 'Altera dados e senha do supervisor.'),
    'usuarios.editar_gerente': usuarios('Editar', 'gerente', 'Altera dados, senha e supervisor do gerente.'),
    'usuarios.editar_vendedor': usuarios('Editar', 'vendedor', 'Altera dados, senha e gerente do vendedor.'),
    'usuarios.excluir_supervisor': usuarios('Excluir', 'supervisor', 'Exclui e restaura supervisores (com a rede abaixo).'),
    'usuarios.excluir_gerente': usuarios('Excluir', 'gerente', 'Exclui e restaura gerentes (com os vendedores).'),
    'usuarios.excluir_vendedor': usuarios('Excluir', 'vendedor', 'Exclui e restaura vendedores.'),
    'usuarios.alterar_situacao': { grupo: 'Usuários', nome: 'Ativar e desativar', descricao: 'Ativa ou desativa usuários da rede (com quem está abaixo).' },
    'usuarios.gerenciar_permissoes': { grupo: 'Usuários', nome: 'Gerenciar permissões', descricao: 'Concede e retira permissões dos usuários da rede.' },
    'usuarios_configuracoes.editar': { grupo: 'Usuários', nome: 'Editar comissões e regras', descricao: 'Altera comissões, limites e regras de aposta dos vendedores.' },

    // Clientes
    'clientes.listar': { grupo: 'Clientes', nome: 'Ver clientes', descricao: 'Abre a lista de clientes.' },
    'clientes.ver_dados_completos': { grupo: 'Clientes', nome: 'Ver dados completos', descricao: 'Vê CPF, telefone e e-mail sem máscara.' },
    'clientes.editar': { grupo: 'Clientes', nome: 'Editar clientes', descricao: 'Altera os dados dos clientes.' },
    'clientes.editar_configuracoes': { grupo: 'Clientes', nome: 'Editar configurações', descricao: 'Altera limites e regras de aposta do cliente.' },
    'clientes.movimentar_saldo': { grupo: 'Clientes', nome: 'Movimentar saldo', descricao: 'Lança créditos e débitos no saldo do cliente.' },
    'clientes.excluir': { grupo: 'Clientes', nome: 'Excluir clientes', descricao: 'Exclui clientes.' },
    'clientes.restaurar': { grupo: 'Clientes', nome: 'Restaurar clientes', descricao: 'Restaura clientes excluídos.' },
    'clientes_promocoes.gerenciar': { grupo: 'Clientes', nome: 'Gerenciar promoções', descricao: 'Cria e altera promoções e bônus.' },
    'clientes_promocoes.estornar': { grupo: 'Clientes', nome: 'Estornar promoções', descricao: 'Desfaz bônus já concedidos.' },
    'clientes.acessar_conta': { grupo: 'Clientes', nome: 'Acessar conta do cliente', descricao: 'Entra na conta do cliente para ajudá-lo (acesso assistido).' },

    // Confrontos e cotações
    'porcentagens_vendedores.editar': { grupo: 'Confrontos e cotações', nome: 'Cotação dos vendedores', descricao: 'Ajusta a porcentagem de cotação dos vendedores.' },
    'porcentagens_clientes.editar': { grupo: 'Confrontos e cotações', nome: 'Cotação dos clientes', descricao: 'Ajusta a porcentagem de cotação dos clientes.' },
    'porcentagens_campeonatos.editar': { grupo: 'Confrontos e cotações', nome: 'Cotação por campeonato', descricao: 'Ajusta a cotação de um campeonato.' },
    'porcentagens_confrontos.editar': { grupo: 'Confrontos e cotações', nome: 'Cotação por jogo', descricao: 'Ajusta a cotação de um jogo.' },
    'confrontos_teto_cotacoes.editar': { grupo: 'Confrontos e cotações', nome: 'Teto de cotações', descricao: 'Define a cotação máxima dos jogos.' },
    'campeonatos.listar': { grupo: 'Confrontos e cotações', nome: 'Ver campeonatos', descricao: 'Abre a lista de campeonatos.' },
    'campeonatos.cadastrar': { grupo: 'Confrontos e cotações', nome: 'Cadastrar campeonatos', descricao: 'Cria campeonatos.' },
    'campeonatos.editar': { grupo: 'Confrontos e cotações', nome: 'Editar campeonatos', descricao: 'Altera campeonatos.' },
    'campeonatos.excluir': { grupo: 'Confrontos e cotações', nome: 'Excluir campeonatos', descricao: 'Exclui campeonatos.' },
    'campeonatos.alterar_situacao': { grupo: 'Confrontos e cotações', nome: 'Ativar campeonatos', descricao: 'Ativa ou desativa campeonatos.' },
    'campeonatos.favoritar': { grupo: 'Confrontos e cotações', nome: 'Campeonatos favoritos', descricao: 'Marca campeonatos em destaque.' },
    'confrontos.listar': { grupo: 'Confrontos e cotações', nome: 'Ver jogos', descricao: 'Abre a lista de jogos.' },
    'confrontos.cadastrar': { grupo: 'Confrontos e cotações', nome: 'Cadastrar jogos', descricao: 'Cria jogos manualmente.' },
    'confrontos.editar': { grupo: 'Confrontos e cotações', nome: 'Editar jogos', descricao: 'Altera jogos.' },
    'confrontos.excluir': { grupo: 'Confrontos e cotações', nome: 'Excluir jogos', descricao: 'Exclui jogos.' },
    'confrontos.alterar_situacao': { grupo: 'Confrontos e cotações', nome: 'Situação dos jogos', descricao: 'Bloqueia, cancela ou adia jogos.' },
    'confrontos.alterar_limite': { grupo: 'Confrontos e cotações', nome: 'Limite por jogo', descricao: 'Define o valor máximo apostado por jogo.' },
    'confrontos.lancar_resultado': { grupo: 'Confrontos e cotações', nome: 'Lançar resultado', descricao: 'Informa o resultado de um jogo manualmente.' },
    'campeonatos_nao_permitidos.gerenciar': { grupo: 'Confrontos e cotações', nome: 'Bloquear campeonatos', descricao: 'Esconde campeonatos de vendedores ou clientes.' },
    'confrontos_nao_permitidos.gerenciar': { grupo: 'Confrontos e cotações', nome: 'Bloquear jogos', descricao: 'Esconde jogos do pré-jogo.' },
    'confrontos_ao_vivo_nao_permitidos.gerenciar': { grupo: 'Confrontos e cotações', nome: 'Bloquear jogos ao vivo', descricao: 'Esconde jogos do ao vivo.' },
    'visitantes_configuracoes.editar': { grupo: 'Confrontos e cotações', nome: 'Regras do visitante', descricao: 'Altera as regras de aposta de quem não está logado.' },

    // Apostas
    'apostas.criar': { grupo: 'Apostas', nome: 'Fazer apostas', descricao: 'Registra apostas no balcão.' },
    'apostas.validar': { grupo: 'Apostas', nome: 'Validar código', descricao: 'Confirma apostas feitas pelo código do cliente.' },
    'apostas.cancelar': { grupo: 'Apostas', nome: 'Cancelar apostas', descricao: 'Cancela apostas antes do jogo começar.' },
    'apostas.cancelar_iniciada': { grupo: 'Apostas', nome: 'Cancelar aposta iniciada', descricao: 'Cancela apostas com jogo já começado.' },
    'apostas.editar': { grupo: 'Apostas', nome: 'Editar bilhete', descricao: 'Cancela ou restaura palpites de um bilhete.' },
    'apostas_encerramentos.listar': { grupo: 'Apostas', nome: 'Ver encerramentos', descricao: 'Lista os cash outs dos clientes.' },

    // Especiais
    'especiais.listar': { grupo: 'Especiais', nome: 'Ver especiais', descricao: 'Abre a lista de apostas especiais.' },
    'especiais.gerenciar': { grupo: 'Especiais', nome: 'Gerenciar especiais', descricao: 'Cria e altera categorias e opções.' },
    'especiais.encerrar': { grupo: 'Especiais', nome: 'Encerrar especiais', descricao: 'Define o resultado ou cancela especiais.' },

    // Site e configurações
    'avisos.gerenciar': { grupo: 'Site e configurações', nome: 'Avisos', descricao: 'Cria e altera os avisos do site.' },
    'banners.gerenciar': { grupo: 'Site e configurações', nome: 'Banners', descricao: 'Cria e altera os banners do site.' },
    'configuracoes.editar': { grupo: 'Site e configurações', nome: 'Configurações gerais', descricao: 'Altera logo, regras e configurações do sistema.' },

    // Pagamentos
    'saques.gerenciar': { grupo: 'Pagamentos', nome: 'Saques', descricao: 'Aprova, recusa e paga saques.' },
    'gateway_pagamento.vincular': { grupo: 'Pagamentos', nome: 'Vincular Mercado Pago', descricao: 'Liga a conta de pagamento da banca.' },

    // Cassino
    'cassinos_jogos.gerenciar': { grupo: 'Cassino', nome: 'Catálogo do cassino', descricao: 'Jogos, provedores e sincronização.' },
    'cassinos_rodadas.listar': { grupo: 'Cassino', nome: 'Movimento do cassino', descricao: 'Relatório, histórico e chamadas do provedor.' },

    // Afiliados
    'afiliados.listar': { grupo: 'Afiliados', nome: 'Ver afiliados', descricao: 'Abre a lista de afiliados.' },
    'afiliados.consultar': { grupo: 'Afiliados', nome: 'Consultar afiliado', descricao: 'Vê dados, extrato e clientes do afiliado.' },
    'afiliados.cadastrar': { grupo: 'Afiliados', nome: 'Cadastrar afiliados', descricao: 'Cria afiliados.' },
    'afiliados.editar': { grupo: 'Afiliados', nome: 'Editar afiliados', descricao: 'Altera dados dos afiliados.' },
    'afiliados.alterar_situacao': { grupo: 'Afiliados', nome: 'Situação dos afiliados', descricao: 'Aprova, recusa, ativa ou desativa afiliados.' },
    'afiliados.prestar_contas': { grupo: 'Afiliados', nome: 'Prestar contas', descricao: 'Fecha e paga o valor a receber do afiliado.' },
    'afiliados.editar_comissao': { grupo: 'Afiliados', nome: 'Comissão do afiliado', descricao: 'Altera a regra de comissão do afiliado.' },
    'afiliados.excluir': { grupo: 'Afiliados', nome: 'Excluir afiliados', descricao: 'Exclui afiliados.' },
    'afiliados.restaurar': { grupo: 'Afiliados', nome: 'Restaurar afiliados', descricao: 'Restaura afiliados excluídos.' },

    // Dashboard
    'dashboard.ver_apurado': { grupo: 'Dashboard', nome: 'Apurado do dia', descricao: 'Vê o apurado financeiro do dia.' },
    'dashboard.ver_situacao_jogos': { grupo: 'Dashboard', nome: 'Situação dos jogos', descricao: 'Vê a situação dos jogos apostados.' },
    'dashboard.ver_apostas': { grupo: 'Dashboard', nome: 'Apostas', descricao: 'Vê o resumo das apostas do dia.' },
    'dashboard.ver_combinacao_risco': { grupo: 'Dashboard', nome: 'Combinação de risco', descricao: 'Vê e recebe avisos de combinações de risco.' },
    'dashboard.ver_apostas_um_jogo': { grupo: 'Dashboard', nome: 'Apostas por um jogo', descricao: 'Vê apostas que dependem de um jogo.' },
    'dashboard.ver_vendedores': { grupo: 'Dashboard', nome: 'Ranking de vendedores', descricao: 'Vê os vendedores que mais venderam.' },
    'dashboard.ver_jogos_mais_apostados': { grupo: 'Dashboard', nome: 'Jogos mais apostados', descricao: 'Vê os jogos mais apostados.' },
    'dashboard.ver_opcoes_mais_apostadas': { grupo: 'Dashboard', nome: 'Opções mais apostadas', descricao: 'Vê as opções mais apostadas.' },
    'dashboard.ver_caixa_online': { grupo: 'Dashboard', nome: 'Caixa online', descricao: 'Vê o caixa das apostas online.' },
};

// texto de uma permissão; sem cadastro, o próprio nome no grupo "Outras"
export const texto_permissao = (chave) => permissoes[chave] ?? { grupo: grupo_outras, nome: chave, descricao: '' };

// permissões agrupadas na ordem dos grupos: [{ grupo, itens: [{ chave, nome, descricao }] }]
export const agrupar_permissoes = (chaves) => {
    const grupos = new Map([...grupos_permissoes, grupo_outras].map((grupo) => [grupo, []]));

    chaves.forEach((chave) => {
        const texto = texto_permissao(chave);
        grupos.get(texto.grupo)?.push({ chave, nome: texto.nome, descricao: texto.descricao });
    });

    return [...grupos].filter(([, itens]) => itens.length).map(([grupo, itens]) => ({ grupo, itens }));
};
