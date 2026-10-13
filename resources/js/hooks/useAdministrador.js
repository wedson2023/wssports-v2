import { useEffect, useState } from 'react';

import api from '../utils/api';
import useSessao from './useSessao';

// se quem entrou no site é o Admin do painel: só ele tem a permissão gateway_pagamento.vincular,
// então a consulta do gateway responde 200 só para ele; null enquanto confere
export default function useAdministrador() {
    const { sessao } = useSessao();
    const e_usuario = sessao?.tipo === 'usuario';
    const [administrador, definir_administrador] = useState(e_usuario ? null : false);

    useEffect(() => {
        if (!e_usuario) {
            definir_administrador(false);
            return undefined;
        }

        let ativo = true;
        definir_administrador(null);

        // o token vai direto: o gestor navega como visitante e não manda o token por padrão
        api.get('/configuracoes/gateway', { headers: { Authorization: `Bearer ${sessao.token}` } })
            .then(() => ativo && definir_administrador(true))
            .catch(() => ativo && definir_administrador(false));

        return () => {
            ativo = false;
        };
    }, [e_usuario, sessao?.token]);

    return administrador;
}
