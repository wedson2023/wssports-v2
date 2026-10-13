import { useCallback, useEffect, useRef, useState } from 'react';

import api from '../utils/api';

const por_pagina = 30;

// dados da tela de créditos (spec 007): cabeçalho e saldos, e o extrato paginado com rolagem
// infinita; o token vai pelo padrão do api (sessão do cliente ou do acesso assistido). Meus dados,
// meios de pagamento, opções de depósito e resumo das indicações vêm junto, para os modais abrirem
// sem carregar
export default function useMinhaConta(ativo) {
    const [dados, definir_dados] = useState(null);
    const [perfil, definir_perfil] = useState(null); // { meus, meios }
    // opcionais: se falharem (ex.: depósito desligado), o modal busca sozinho ao abrir
    const [opcoes_deposito, definir_opcoes_deposito] = useState(null);
    const [resumo_indicacoes, definir_resumo_indicacoes] = useState(null);
    // primeira página do extrato já respondeu: a tela só aparece depois disso
    const [extrato_pronto, definir_extrato_pronto] = useState(false);
    const [situacao, definir_situacao] = useState('carregando'); // carregando | pronta | inativa | expirada
    const [filtros, definir_filtros] = useState({ operacao: '', situacao: '' });
    const [itens, definir_itens] = useState([]);
    const [pagina, definir_pagina] = useState(0);
    const [fim, definir_fim] = useState(false);
    const [carregando_extrato, definir_carregando_extrato] = useState(false);
    // pedido mais recente do extrato: respostas de filtros antigos são descartadas
    const pedido_atual = useRef(0);

    const tratar_erro = (erro) => {
        const status = erro.response?.status;

        if (status === 403) definir_situacao('inativa');
        else if (status === 401) definir_situacao('expirada');
        else throw erro;
    };

    const carregar_dados = useCallback(async () => {
        try {
            const opcional = (pedido) => pedido.then(({ data }) => data).catch(() => null);

            const [{ data }, { data: meus }, { data: meios }, opcoes, indicacoes] = await Promise.all([
                api.get('/area-cliente/minha-conta'),
                api.get('/area-cliente/meus-dados'),
                api.get('/area-cliente/meios-pagamento'),
                opcional(api.get('/area-cliente/depositos/opcoes')),
                opcional(api.get('/area-cliente/indicacoes', { params: { por_pagina: 1 } })),
            ]);
            definir_dados(data.data);
            definir_perfil({ meus: meus.data, meios: meios.data });
            definir_opcoes_deposito(opcoes?.data ?? null);
            definir_resumo_indicacoes(indicacoes?.resumo ?? null);
            definir_situacao('pronta');
        } catch (erro) {
            tratar_erro(erro);
        }
    }, []);

    const carregar_pagina = useCallback(async (numero, filtros_atuais) => {
        const pedido = ++pedido_atual.current;
        definir_carregando_extrato(true);

        try {
            const { data } = await api.get('/area-cliente/minha-conta/extrato', {
                params: {
                    pagina: numero,
                    por_pagina,
                    operacao: filtros_atuais.operacao || undefined,
                    situacao: filtros_atuais.operacao === 'palpites' && filtros_atuais.situacao ? filtros_atuais.situacao : undefined,
                },
            });

            if (pedido !== pedido_atual.current) return;

            definir_itens((atuais) => (numero === 1 ? data.data : [...atuais, ...data.data]));
            definir_pagina(numero);
            definir_fim(data.meta.current_page >= data.meta.last_page);
        } catch (erro) {
            if (pedido === pedido_atual.current) tratar_erro(erro);
        } finally {
            if (pedido === pedido_atual.current) {
                definir_carregando_extrato(false);
                definir_extrato_pronto(true);
            }
        }
    }, []);

    useEffect(() => {
        if (ativo) carregar_dados();
    }, [ativo, carregar_dados]);

    // trocar o filtro recomeça o extrato da primeira página
    useEffect(() => {
        if (!ativo) return;

        definir_itens([]);
        definir_fim(false);
        carregar_pagina(1, filtros);
    }, [ativo, filtros, carregar_pagina]);

    const carregar_mais = useCallback(() => {
        if (!carregando_extrato && !fim) carregar_pagina(pagina + 1, filtros);
    }, [carregando_extrato, fim, pagina, filtros, carregar_pagina]);

    // depois de depósito, saque ou ação de bônus: saldos e extrato do zero
    const recarregar_tudo = useCallback(() => {
        carregar_dados();
        carregar_pagina(1, filtros);
    }, [carregar_dados, carregar_pagina, filtros]);

    return {
        dados,
        perfil,
        opcoes_deposito,
        resumo_indicacoes,
        extrato_pronto,
        situacao,
        filtros,
        definir_filtros,
        itens,
        fim,
        carregando_extrato,
        carregar_mais,
        recarregar_tudo,
    };
}
