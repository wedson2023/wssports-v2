import { useCallback, useEffect, useState } from 'react';
import { router } from '@inertiajs/react';

import useSessao from './useSessao';
import api from '../utils/api';
import { formatar_real, para_centavos } from '../utils/money';

// valor da API ("85.00", "-4.00") → "R$ 85,00" / "R$ -4,00", sem ponto flutuante
export const formatar_valor = (valor) => {
    const texto = String(valor ?? '0');
    const negativo = texto.startsWith('-');

    return `R$ ${negativo ? '-' : ''}${formatar_real(para_centavos(texto.replace('-', '')))}`;
};

// dados do painel do afiliado (spec 012, US7) com o token da sessão dele; o token só vai nas rotas
// /area-afiliado. Token vencido ou acesso cortado (401/403): encerra a sessão e volta para a entrada
export default function useAfiliado() {
    const { sessao, sair, expirar } = useSessao();
    const token = sessao?.tipo === 'afiliado' ? sessao.token : null;

    const [painel, definir_painel] = useState(null);
    const [carregando, definir_carregando] = useState(true);

    const requisitar = useCallback(async (config) => {
        try {
            const { data } = await api.request({ ...config, headers: { ...config.headers, Authorization: `Bearer ${token}` } });

            return data;
        } catch (erro) {
            if ([401, 403].includes(erro.response?.status)) {
                expirar();
                router.visit('/?entrar=1');
            }

            throw erro;
        }
    }, [token, expirar]);

    const recarregar_painel = useCallback(async () => {
        const { data } = await requisitar({ url: '/area-afiliado/painel' });

        definir_painel(data);

        return data;
    }, [requisitar]);

    // sem sessão de afiliado, vai para a entrada do site (FR-058)
    useEffect(() => {
        if (!token) {
            router.visit('/?entrar=1');
            return;
        }

        recarregar_painel()
            .catch(() => {})
            .finally(() => definir_carregando(false));
    }, [token, recarregar_painel]);

    const sair_do_painel = useCallback(async () => {
        await sair();
        router.visit('/cadastro-afiliado');
    }, [sair]);

    return { token, painel, carregando, requisitar, recarregar_painel, sair: sair_do_painel };
}
