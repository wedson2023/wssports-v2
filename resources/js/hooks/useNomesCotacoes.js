import { useEffect, useState } from 'react';

import api from '../utils/api';
import useSessao from './useSessao';

// nomes legíveis das cotações (GET /cotacoes/nomes), buscados uma vez por sessão do navegador
let em_memoria = null;

export default function useNomesCotacoes() {
    const { sessao } = useSessao();
    const [nomes, definir_nomes] = useState(em_memoria);

    useEffect(() => {
        if (em_memoria || !sessao?.token) return undefined;

        let ativo = true;

        api.get('/cotacoes/nomes', { headers: { Authorization: `Bearer ${sessao.token}` } })
            .then(({ data }) => {
                em_memoria = data.nomes;
                if (ativo) definir_nomes(data.nomes);
            })
            .catch(() => ativo && definir_nomes({}));

        return () => {
            ativo = false;
        };
    }, [sessao?.token]);

    return nomes;
}
