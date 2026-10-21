import { useCallback, useEffect, useState } from 'react';

import api from '../utils/api';
import useSessao from './useSessao';

// atraso da busca enquanto a pessoa digita
const ATRASO_BUSCA = 400;

// lista de usuários de uma função no painel (spec 015): aba Ativos (GET /usuarios) ou Lixeira
// (GET /usuarios/excluidos), com busca e paginação de 15
export default function useUsuariosPainel(funcao, aba) {
    const { sessao, expirar } = useSessao();
    const [busca, definir_busca] = useState('');
    const [busca_aplicada, definir_busca_aplicada] = useState('');
    const [pagina, definir_pagina] = useState(1);
    const [recarga, definir_recarga] = useState(0);
    const [estado, definir_estado] = useState({ usuarios: [], meta: null, carregando: true, erro: null });

    // a busca só é aplicada depois que a pessoa para de digitar e volta para a primeira página
    useEffect(() => {
        const espera = window.setTimeout(() => {
            definir_busca_aplicada(busca.trim());
            definir_pagina(1);
        }, ATRASO_BUSCA);

        return () => window.clearTimeout(espera);
    }, [busca]);

    // trocar de função ou de aba volta para a primeira página e limpa a lista anterior (a Lixeira
    // não pode desenhar, nem por um instante, os usuários da aba Ativos)
    useEffect(() => {
        definir_pagina(1);
        definir_estado({ usuarios: [], meta: null, carregando: true, erro: null });
    }, [funcao, aba]);

    useEffect(() => {
        if (!sessao?.token) return undefined;

        let ativo = true;
        definir_estado((atual) => ({ ...atual, carregando: true, erro: null }));

        api.get(aba === 'lixeira' ? '/usuarios/excluidos' : '/usuarios', {
            params: { funcao, busca: busca_aplicada || undefined, page: pagina, por_pagina: 15 },
            headers: { Authorization: `Bearer ${sessao.token}` },
        })
            .then(({ data }) => ativo && definir_estado({ usuarios: data.data, meta: data.meta, carregando: false, erro: null }))
            .catch((erro) => {
                if (!ativo) return;
                if (erro.response?.status === 401) expirar();

                definir_estado((atual) => ({ ...atual, carregando: false, erro }));
            });

        return () => {
            ativo = false;
        };
    }, [funcao, aba, busca_aplicada, pagina, recarga, sessao?.token]);

    const recarregar = useCallback(() => definir_recarga((atual) => atual + 1), []);

    return { ...estado, busca, definir_busca, pagina, definir_pagina, recarregar };
}
