import { useCallback, useEffect, useRef, useState } from 'react';

import api from '../utils/api';
import useSessao from './useSessao';

// intervalo da atualização automática (spec 014, FR-044)
const INTERVALO = 60 * 1000;

// dados de uma seção da Dashboard (GET /dashboard/<caminho>): mantém os dados antigos enquanto
// atualiza, ⟳ ignora o cache (?atualizar=1), 403 esconde a seção e a atualização automática roda a
// cada minuto só com a aba visível; ignorar_cache faz a automática também passar por cima do cache
export default function useSecaoDashboard(caminho, { habilitada = true, ignorar_cache = false } = {}) {
    const { sessao, expirar } = useSessao();
    const [estado, definir_estado] = useState({ dados: null, gerado_em: null, carregando: true, atualizando: false, erro: null, sem_permissao: false });
    const em_andamento = useRef(false);
    const token = sessao?.token;

    const buscar = useCallback(async (atualizar) => {
        if (!token || em_andamento.current) return;

        em_andamento.current = true;
        definir_estado((atual) => ({ ...atual, atualizando: !atual.carregando }));

        try {
            const { data } = await api.get(`/dashboard/${caminho}`, {
                params: atualizar ? { atualizar: 1 } : {},
                headers: { Authorization: `Bearer ${token}` },
            });

            definir_estado({ dados: data.data, gerado_em: data.gerado_em, carregando: false, atualizando: false, erro: null, sem_permissao: false });
        } catch (erro) {
            const status = erro.response?.status;

            if (status === 401) expirar();

            definir_estado((atual) => ({ ...atual, carregando: false, atualizando: false, erro, sem_permissao: status === 403 }));
        } finally {
            em_andamento.current = false;
        }
    }, [caminho, token, expirar]);

    // primeira carga e atualização automática com a aba visível
    useEffect(() => {
        if (!habilitada) return undefined;

        buscar(false);

        const automatica = () => {
            if (document.visibilityState === 'visible') buscar(ignorar_cache);
        };
        const intervalo = window.setInterval(automatica, INTERVALO);

        document.addEventListener('visibilitychange', automatica);

        return () => {
            window.clearInterval(intervalo);
            document.removeEventListener('visibilitychange', automatica);
        };
    }, [habilitada, buscar, ignorar_cache]);

    const atualizar = useCallback(() => buscar(true), [buscar]);

    return { ...estado, atualizar };
}
