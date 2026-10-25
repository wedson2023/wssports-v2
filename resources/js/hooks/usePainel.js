import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';

import api from '../utils/api';
import useSessao from './useSessao';

// usuário já carregado nesta aba, pelo token: ao trocar de tela no menu, o painel (menu e cabeçalho)
// aparece na hora e só o conteúdo carrega; a consulta continua sendo feita para manter as permissões
// em dia (spec 021, ajuste pedido pelo responsável)
let em_memoria = { token: null, usuario: null };

// usuário do painel logado (spec 014): sem sessão de usuário, volta para a tela principal (FR-006);
// com ela, busca nome, função e seções da Dashboard em GET /auth/eu
export default function usePainel() {
    const { sessao, expirar } = useSessao();
    const e_usuario = sessao?.tipo === 'usuario';
    const [usuario, definir_usuario] = useState(() => (e_usuario && em_memoria.token === sessao.token ? em_memoria.usuario : null));

    useEffect(() => {
        if (!e_usuario) {
            router.visit('/');
            return undefined;
        }

        let ativo = true;

        // o token vai direto: o gestor navega como visitante e não manda o token por padrão
        api.get('/auth/eu', { headers: { Authorization: `Bearer ${sessao.token}` } })
            .then(({ data }) => {
                em_memoria = { token: sessao.token, usuario: data.data };

                if (ativo) definir_usuario(data.data);
            })
            .catch((erro) => {
                em_memoria = { token: null, usuario: null };

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
