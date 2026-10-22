import { useCallback, useEffect, useState } from 'react';

import api from '../utils/api';
import { periodo_do_atalho, validar_periodo } from '../utils/periods';
import useSessao from './useSessao';

export const abas_apostas = ['todas', 'pre_jogo', 'ao_vivo', 'canceladas'];

const resultados = ['Aguardando', 'Vencedor', 'Perdedor', 'Devolvido', 'Encerrada'];
const formas_pagamento = ['Dinheiro', 'Saldo', 'Promoção esportes'];

const estado_inicial = { dados: null, carregando: true, erro: null };
const lista_inicial = { linhas: [], total: 0, ultima: 1, carregando: true, erro: null };

// filtros da URL (FR-011); período inválido vale hoje, valores fora da lista são ignorados
const filtros_da_url = () => {
    const parametros = new URLSearchParams(window.location.search);
    const periodo = { de: parametros.get('de') ?? '', ate: parametros.get('ate') ?? '' };
    const da_lista = (chave, lista) => (lista.includes(parametros.get(chave)) ? parametros.get(chave) : null);

    return {
        aba: da_lista('aba', abas_apostas) ?? 'todas',
        periodo: validar_periodo(periodo) ? periodo_do_atalho('hoje') : periodo,
        busca: parametros.get('busca') ?? '',
        todo_periodo: parametros.get('todo_periodo') === '1',
        resultado: da_lista('resultado', resultados),
        vendedor: Number(parametros.get('usuarios_id')) ? { id: Number(parametros.get('usuarios_id')), nome: parametros.get('vendedor') ?? 'Vendedor' } : null,
        forma_pagamento: da_lista('forma_pagamento', formas_pagamento),
        premio_minimo: /^\d+(\.\d{1,2})?$/.test(parametros.get('premio_minimo') ?? '') ? parametros.get('premio_minimo') : null,
        pagina: Math.max(1, Number(parametros.get('page')) || 1),
    };
};

// parâmetros da API (contracts/api.md "Filtros comuns") a partir dos filtros da tela
export const parametros_api = (filtros) => ({
    de: filtros.periodo.de,
    ate: filtros.periodo.ate,
    aba: filtros.aba,
    ...(filtros.busca.trim() ? { busca: filtros.busca.trim(), ...(filtros.todo_periodo ? { todo_periodo: 1 } : {}) } : {}),
    ...(filtros.resultado && filtros.aba !== 'canceladas' ? { resultado: filtros.resultado } : {}),
    ...(filtros.vendedor ? { usuarios_id: filtros.vendedor.id } : {}),
    ...(filtros.forma_pagamento ? { forma_pagamento: filtros.forma_pagamento } : {}),
    ...(filtros.premio_minimo ? { premio_minimo: filtros.premio_minimo } : {}),
});

// grava os filtros na URL, para recarregar, voltar e o atalho do Caixa manterem a lista
const gravar_na_url = (filtros) => {
    const { usuarios_id, ...resto } = parametros_api(filtros);
    const parametros = new URLSearchParams(resto);

    if (usuarios_id) {
        parametros.set('usuarios_id', usuarios_id);
        parametros.set('vendedor', filtros.vendedor.nome);
    }

    if (filtros.pagina > 1) parametros.set('page', filtros.pagina);

    window.history.replaceState(window.history.state, '', `?${parametros}`);
};

// dados da tela Apostas do painel (spec 018): filtros na URL, lista paginada (GET /apostas) e totais
// do filtro (GET /apostas/resumo), cada um com carregando e erro próprios; os dados antigos ficam na
// tela enquanto atualiza; recarregar() depois de cancelar ou editar
export default function useApostasPainel({ habilitado }) {
    const { sessao, expirar } = useSessao();
    const token = sessao?.token;
    const [filtros, definir_filtros] = useState(filtros_da_url);
    const [recarga, definir_recarga] = useState(0);
    const [lista, definir_lista] = useState(lista_inicial);
    const [resumo, definir_resumo] = useState(estado_inicial);

    // trocar qualquer filtro volta para a página 1; trocar só a página mantém o resto
    const mudar = useCallback((novos) => {
        definir_filtros((atuais) => {
            const proximos = { ...atuais, pagina: 1, ...novos };
            gravar_na_url(proximos);

            return proximos;
        });
    }, []);

    const recarregar = useCallback(() => definir_recarga((atual) => atual + 1), []);

    const chave = JSON.stringify(parametros_api(filtros));

    useEffect(() => {
        if (!habilitado || !token) return undefined;

        let ativo = true;
        const config = { params: { ...JSON.parse(chave), page: filtros.pagina }, headers: { Authorization: `Bearer ${token}` } };
        const falhou = (erro) => erro.response?.status === 401 && expirar();

        definir_lista((atual) => ({ ...atual, carregando: true, erro: null }));
        api.get('/apostas', config)
            .then(({ data }) => ativo && definir_lista({ linhas: data.data, total: data.meta.total, ultima: data.meta.last_page, carregando: false, erro: null }))
            .catch((erro) => {
                if (!ativo) return;

                falhou(erro);
                definir_lista((atual) => ({ ...atual, carregando: false, erro }));
            });

        return () => {
            ativo = false;
        };
    }, [habilitado, token, chave, filtros.pagina, recarga]);

    useEffect(() => {
        if (!habilitado || !token) return undefined;

        let ativo = true;

        definir_resumo((atual) => ({ ...atual, carregando: true, erro: null }));
        api.get('/apostas/resumo', { params: JSON.parse(chave), headers: { Authorization: `Bearer ${token}` } })
            .then(({ data }) => ativo && definir_resumo({ dados: data, carregando: false, erro: null }))
            .catch((erro) => {
                if (!ativo) return;

                if (erro.response?.status === 401) expirar();
                definir_resumo((atual) => ({ ...atual, carregando: false, erro }));
            });

        return () => {
            ativo = false;
        };
    }, [habilitado, token, chave, recarga]);

    return { filtros, mudar, lista, resumo, recarregar, token };
}
