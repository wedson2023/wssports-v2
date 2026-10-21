import { useCallback, useEffect, useState } from 'react';

import api from '../utils/api';
import useSessao from './useSessao';

// atraso da busca enquanto a pessoa digita
const ATRASO_BUSCA = 400;

// lista paginada da tela "Cotações" (campeonatos, jogos, Regras ativas) com busca e filtros, no
// padrão de useUsuariosPainel: a busca espera a pessoa parar de digitar e trocar de filtro limpa a lista
export default function useListaCotacoes(rota, filtros = {}, ativa = true) {
    const { sessao, expirar } = useSessao();
    const [busca, definir_busca] = useState('');
    const [busca_aplicada, definir_busca_aplicada] = useState('');
    const [pagina, definir_pagina] = useState(1);
    const [recarga, definir_recarga] = useState(0);
    const [estado, definir_estado] = useState({ itens: [], meta: null, carregando: true, erro: null });
    const chave_filtros = JSON.stringify(filtros);

    useEffect(() => {
        const espera = window.setTimeout(() => {
            definir_busca_aplicada(busca.trim());
            definir_pagina(1);
        }, ATRASO_BUSCA);

        return () => window.clearTimeout(espera);
    }, [busca]);

    useEffect(() => {
        definir_pagina(1);
        definir_estado({ itens: [], meta: null, carregando: true, erro: null });
    }, [rota, chave_filtros]);

    useEffect(() => {
        if (!ativa || !sessao?.token) return undefined;

        let ativo = true;
        definir_estado((atual) => ({ ...atual, carregando: true, erro: null }));

        api.get(rota, {
            params: { ...filtros, busca: busca_aplicada || undefined, page: pagina },
            headers: { Authorization: `Bearer ${sessao.token}` },
        })
            .then(({ data }) => ativo && definir_estado({
                itens: data.data,
                meta: { current_page: data.current_page ?? data.meta?.current_page, last_page: data.last_page ?? data.meta?.last_page, total: data.total ?? data.meta?.total },
                carregando: false,
                erro: null,
            }))
            .catch((erro) => {
                if (!ativo) return;
                if (erro.response?.status === 401) expirar();

                definir_estado((atual) => ({ ...atual, carregando: false, erro }));
            });

        return () => {
            ativo = false;
        };
    }, [rota, chave_filtros, busca_aplicada, pagina, recarga, ativa, sessao?.token]);

    const recarregar = useCallback(() => definir_recarga((atual) => atual + 1), []);

    // troca um item da página sem recarregar (ex.: selo depois de salvar)
    const atualizar_item = useCallback((id, mudancas) => definir_estado((atual) => ({
        ...atual,
        itens: atual.itens.map((item) => (item.id === id ? { ...item, ...mudancas } : item)),
    })), []);

    return { ...estado, busca, definir_busca, pagina, definir_pagina, recarregar, atualizar_item };
}
