import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';

import api from '../utils/api';
import useSessao from './useSessao';

// usuário do painel logado (spec 014): sem sessão de usuário, volta para a tela principal (FR-006);
// com ela, busca nome, função e seções da Dashboard em GET /auth/eu
export default function usePainel() {
    const { sessao, expirar } = useSessao();
    const [usuario, definir_usuario] = useState(null);
    const e_usuario = sessao?.tipo === 'usuario';

    useEffect(() => {
        if (!e_usuario) {
            router.visit('/');
            return undefined;
        }

        let ativo = true;

        // o token vai direto: o gestor navega como visitante e não manda o token por padrão
        api.get('/auth/eu', { headers: { Authorization: `Bearer ${sessao.token}` } })
            .then(({ data }) => ativo && definir_usuario(data.data))
            .catch((erro) => {
                if (!ativo) return;

                if (erro.response?.status === 401) expirar();

                router.visit('/');
            });

        return () => {
            ativo = false;
        };
    }, [e_usuario, sessao?.token]);

    return { usuario, carregando: usuario === null };
}
