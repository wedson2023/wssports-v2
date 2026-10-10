import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import axios from 'axios';

import api from '../utils/api';
import { atualizar_sessao, criar_sessao, encerrar_sessao, ler_sessao } from '../utils/session';

// sessão de login do site (apostador ou usuário do painel), entregue pelo PublicLayout
export const SessaoContext = createContext(null);

// telefone brasileiro com DDD: só dígitos, 10 ou 11
const parece_telefone = (texto) => /^\d{10,11}$/.test(texto);

// rotas de login da API: apostador entra com telefone, usuário do painel com login
const rotas_login = {
    cliente: (identificacao, senha) => api.post('/area-cliente/auth/login', { ddi: '55', telefone: identificacao, password: senha }),
    usuario: (identificacao, senha) => api.post('/auth/login', { login: identificacao, password: senha }),
};

const rotas_logout = { cliente: '/area-cliente/auth/logout', usuario: '/auth/logout' };

// nome do apostador, para o cabeçalho; sem ele, o cabeçalho mostra só "Olá"
const nome_do_cliente = async (token) => {
    try {
        const { data } = await api.get('/area-cliente/meus-dados', { headers: { Authorization: `Bearer ${token}` } });

        return data.data?.nome ?? null;
    } catch {
        return null;
    }
};

// props da tela que mudam com quem está logado (cotações, limites e saldo do cliente; apostador;
// limites de aposta da página de regras)
const props_do_publico = ['listagem', 'configuracoes', 'saldo', 'apostador', 'limites_aposta'];

// sessão que muda a tela: cliente, ou usuário do painel enquanto não se sabe se é gestor
const usa_token = (sessao) => sessao?.tipo === 'cliente' || (sessao?.tipo === 'usuario' && sessao.apostador !== 'visitante');

// com sessão de cliente ou de vendedor, o token vai em todas as requisições (Inertia e API), para
// a tela usar as cotações e os limites dele e o cupom apostar como ele; gestor aposta como visitante
const aplicar_token = (sessao) => {
    const valor = usa_token(sessao) ? `Bearer ${sessao.token}` : null;

    [axios.defaults.headers.common, api.defaults.headers.common].forEach((cabecalhos) => {
        if (valor) cabecalhos.Authorization = valor;
        else delete cabecalhos.Authorization;
    });
};

// estado do provedor (usado pelo PublicLayout)
export const useEstadoSessao = () => {
    const [sessao, definir_sessao] = useState(() => {
        const salva = ler_sessao();
        aplicar_token(salva);
        return salva;
    });
    const primeira_carga = useRef(true);

    // a página chega do servidor sem o token: com sessão de cliente ou vendedor, recarrega as props
    // dele; ao entrar ou sair, recarrega para trocar entre as regras dele e as do visitante
    useEffect(() => {
        aplicar_token(sessao);

        if (primeira_carga.current) {
            primeira_carga.current = false;
            if (!usa_token(sessao)) return;
        }

        router.reload({ only: props_do_publico });
    }, [sessao?.token]);

    // token recusado pelo servidor (vencido ou cliente bloqueado): encerra a sessão local
    const expirar = useCallback(() => {
        encerrar_sessao();
        definir_sessao(null);
    }, []);

    // a tela informa como o usuário do painel aposta (prop apostador, só com token): gestor deixa
    // de mandar o token e segue como visitante
    const definir_apostador = useCallback((apostador) => {
        definir_sessao((atual) => {
            if (atual?.tipo !== 'usuario' || !apostador || atual.apostador === apostador) return atual;

            const nova = atualizar_sessao({ ...atual, apostador: apostador === 'vendedor' ? 'vendedor' : 'visitante' });
            aplicar_token(nova);

            return nova;
        });
    }, []);

    // um campo para os dois públicos: tenta primeiro o que o texto parece ser e depois o outro;
    // devolve a sessão ou lança o erro da última tentativa
    const entrar = useCallback(async (identificacao, senha) => {
        const texto = identificacao.trim();
        const somente_digitos = texto.replace(/\D/g, '');
        const ordem = parece_telefone(somente_digitos) ? ['cliente', 'usuario'] : ['usuario', 'cliente'];
        let ultimo_erro = null;

        for (const tipo of ordem) {
            const valor = tipo === 'cliente' ? somente_digitos : texto;

            if (tipo === 'cliente' && !parece_telefone(valor)) continue;

            try {
                const { data } = await rotas_login[tipo](valor, senha);
                const nome = tipo === 'cliente' ? await nome_do_cliente(data.token) : texto;
                const nova = criar_sessao({ tipo, token: data.token, expira_em: data.expira_em, nome });

                definir_sessao(nova);

                return nova;
            } catch (erro) {
                ultimo_erro = erro;

                // só segue para o outro público quando o login foi recusado (credenciais)
                if (![401, 403, 422].includes(erro.response?.status)) throw erro;
            }
        }

        throw ultimo_erro;
    }, []);

    // cadastro do apostador: a API já devolve o token, então ele entra direto
    const cadastrar = useCallback(async (dados) => {
        const { data } = await api.post('/area-cliente/cadastro', dados);

        definir_sessao(criar_sessao({ tipo: 'cliente', token: data.token, expira_em: data.expira_em, nome: data.cliente?.nome ?? dados.nome }));

        return data;
    }, []);

    const sair = useCallback(async () => {
        const atual = ler_sessao();

        if (atual) {
            try {
                await api.post(rotas_logout[atual.tipo], {}, { headers: { Authorization: `Bearer ${atual.token}` } });
            } catch {
                // token já vencido ou sem conexão: a sessão local é encerrada do mesmo jeito
            }
        }

        encerrar_sessao();
        definir_sessao(null);
    }, []);

    return useMemo(() => ({ sessao, entrar, cadastrar, sair, expirar, definir_apostador }), [sessao, entrar, cadastrar, sair, expirar, definir_apostador]);
};

// sessão para os componentes
export default function useSessao() {
    return useContext(SessaoContext);
}
