import { useCallback, useEffect, useState } from 'react';

import api from '../utils/api';
import useSessao from './useSessao';

// histórico de transações de um vendedor (spec 017, contracts/api §2.1): lançamentos e prestações,
// com filtro de tipo e período opcional, paginado; recarregar() depois de excluir ou desfazer
export default function useTransacoesCaixa(vendedor) {
    const { sessao, expirar } = useSessao();
    const token = sessao?.token;
    const [filtros, definir_filtros] = useState({ tipo: 'todos', de: '', ate: '' });
    const [pagina, definir_pagina] = useState(1);
    const [recarga, definir_recarga] = useState(0);
    const [estado, definir_estado] = useState({ itens: [], ultima: 1, carregando: true, erro: null });

    useEffect(() => {
        definir_filtros({ tipo: 'todos', de: '', ate: '' });
        definir_pagina(1);
    }, [vendedor?.id]);

    useEffect(() => {
        if (!vendedor || !token) return undefined;

        let ativo = true;
        definir_estado((atual) => ({ ...atual, carregando: true, erro: null }));

        const params = { tipo: filtros.tipo, page: pagina, ...(filtros.de ? { de: filtros.de } : {}), ...(filtros.ate ? { ate: filtros.ate } : {}) };

        api.get(`/caixa/vendedores/${vendedor.id}/transacoes`, { params, headers: { Authorization: `Bearer ${token}` } })
            .then(({ data }) => ativo && definir_estado({ itens: data.data, ultima: data.last_page, carregando: false, erro: null }))
            .catch((erro) => {
                if (!ativo) return;
                if (erro.response?.status === 401) expirar();

                definir_estado({ itens: [], ultima: 1, carregando: false, erro });
            });

        return () => {
            ativo = false;
        };
    }, [vendedor?.id, token, filtros, pagina, recarga]);

    const filtrar = useCallback((novos) => {
        definir_filtros((atuais) => ({ ...atuais, ...novos }));
        definir_pagina(1);
    }, []);

    const recarregar = useCallback(() => definir_recarga((atual) => atual + 1), []);

    return { ...estado, filtros, filtrar, pagina, definir_pagina, recarregar };
}
