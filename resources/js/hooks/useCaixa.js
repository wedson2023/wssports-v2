import { useCallback, useEffect, useRef, useState } from 'react';

import api from '../utils/api';
import useSessao from './useSessao';

// espera depois da última tecla antes de buscar
const ATRASO_BUSCA = 400;

const estado_inicial = { dados: null, carregando: true, erro: null };
const lista_inicial = { linhas: [], ultima: 1, carregando: true, erro: null };

// dados da tela Caixa (spec 017): cartões e lista da aba (Rede: /caixa/resumo e /caixa/usuarios;
// Clientes: /caixa/clientes/resumo e /caixa/clientes) com o período, tipo, foco, busca (com atraso),
// ordenação e página; os dados antigos ficam na tela enquanto atualiza; recarregar() depois das ações
export default function useCaixa({ aba, periodo, tipo, foco, com_lista, habilitado }) {
    const { sessao, expirar } = useSessao();
    const token = sessao?.token;
    const [busca, definir_busca] = useState('');
    const [busca_aplicada, definir_busca_aplicada] = useState('');
    const [ordenacao, definir_ordenacao] = useState({ campo: 'nome', direcao: 'asc' });
    const [pagina, definir_pagina] = useState(1);
    const [recarga, definir_recarga] = useState(0);
    const [resumo, definir_resumo] = useState(estado_inicial);
    const [lista, definir_lista] = useState(lista_inicial);
    const espera = useRef(null);

    // trocar de aba, nível ou período volta para a primeira página e limpa a busca
    useEffect(() => {
        definir_busca('');
        definir_busca_aplicada('');
        definir_pagina(1);
        definir_ordenacao({ campo: 'nome', direcao: 'asc' });
        definir_lista(lista_inicial);
    }, [aba, foco]);

    useEffect(() => definir_pagina(1), [periodo.de, periodo.ate, tipo]);

    const buscar = useCallback((texto) => {
        definir_busca(texto);
        window.clearTimeout(espera.current);
        espera.current = window.setTimeout(() => {
            definir_busca_aplicada(texto.trim());
            definir_pagina(1);
        }, ATRASO_BUSCA);
    }, []);

    const ordenar = useCallback((nova) => {
        definir_ordenacao(nova);
        definir_pagina(1);
    }, []);

    const falhou = (erro) => {
        if (erro.response?.status === 401) expirar();
    };

    const base = aba === 'clientes' ? '/caixa/clientes' : '/caixa';
    const filtros = aba === 'clientes'
        ? { de: periodo.de, ate: periodo.ate }
        : { de: periodo.de, ate: periodo.ate, tipo, ...(foco ? { usuarios_id: foco } : {}) };

    // cartões
    useEffect(() => {
        if (!habilitado || !token) return undefined;

        let ativo = true;
        definir_resumo((atual) => ({ ...atual, carregando: true, erro: null }));

        api.get(`${base}/resumo`, { params: filtros, headers: { Authorization: `Bearer ${token}` } })
            .then(({ data }) => ativo && definir_resumo({ dados: data, carregando: false, erro: null }))
            .catch((erro) => {
                if (!ativo) return;

                falhou(erro);
                definir_resumo((atual) => ({ ...atual, carregando: false, erro }));
            });

        return () => {
            ativo = false;
        };
    }, [habilitado, token, aba, periodo.de, periodo.ate, tipo, foco, recarga]);

    // lista do nível abaixo (ou dos clientes)
    useEffect(() => {
        if (!habilitado || !token || !com_lista) return undefined;

        let ativo = true;
        definir_lista((atual) => ({ ...atual, carregando: true, erro: null }));

        const params = { ...filtros, page: pagina, ordenar: ordenacao.campo, direcao: ordenacao.direcao, ...(busca_aplicada ? { busca: busca_aplicada } : {}) };

        api.get(aba === 'clientes' ? base : `${base}/usuarios`, { params, headers: { Authorization: `Bearer ${token}` } })
            .then(({ data }) => ativo && definir_lista({ linhas: data.data, ultima: data.last_page, carregando: false, erro: null }))
            .catch((erro) => {
                if (!ativo) return;

                falhou(erro);
                definir_lista((atual) => ({ ...atual, carregando: false, erro }));
            });

        return () => {
            ativo = false;
        };
    }, [habilitado, token, com_lista, aba, periodo.de, periodo.ate, tipo, foco, pagina, ordenacao, busca_aplicada, recarga]);

    const recarregar = useCallback(() => definir_recarga((atual) => atual + 1), []);

    return { resumo, lista, busca, buscar, ordenacao, ordenar, pagina, definir_pagina, recarregar };
}
