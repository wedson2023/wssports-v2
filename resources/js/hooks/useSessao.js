import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import axios from 'axios';

import api from '../utils/api';
import { atualizar_sessao, criar_sessao, encerrar_sessao, ler_sessao } from '../utils/session';
import { gravar_acesso_assistido, ler_acesso_assistido, remover_acesso_assistido } from '../utils/storage';
import { inscricao_atual } from '../utils/web_push';

// sessão de login do site (apostador ou usuário do painel), entregue pelo PublicLayout
export const SessaoContext = createContext(null);

// telefone brasileiro com DDD: só dígitos, 10 ou 11
const parece_telefone = (texto) => /^\d{10,11}$/.test(texto);

// rotas de login da API: apostador entra com telefone; usuário do painel e afiliado (spec 012) com login
const rotas_login = {
    cliente: (identificacao, senha) => api.post('/area-cliente/auth/login', { ddi: '55', telefone: identificacao, password: senha }),
    usuario: (identificacao, senha) => api.post('/auth/login', { login: identificacao, password: senha }),
    afiliado: (identificacao, senha) => api.post('/area-afiliado/auth/login', { login: identificacao, password: senha }),
};

const rotas_logout = { cliente: '/area-cliente/auth/logout', usuario: '/auth/logout', afiliado: '/area-afiliado/auth/logout' };

// nome do apostador, para o cabeçalho; sem ele, o cabeçalho mostra só "Olá"
const nome_do_cliente = async (token) => {
    try {
        const { data } = await api.get('/area-cliente/meus-dados', { headers: { Authorization: `Bearer ${token}` } });

        return data.data?.nome ?? null;
    } catch {
        return null;
    }
};

// nome do afiliado, para o painel dele (spec 012)
const nome_do_afiliado = async (token) => {
    try {
        const { data } = await api.get('/area-afiliado/meus-dados', { headers: { Authorization: `Bearer ${token}` } });

        return data.data?.nome ?? null;
    } catch {
        return null;
    }
};

// nome e função do usuário do painel (spec 014), para o cabeçalho e o destino depois do login
const dados_do_usuario = async (token) => {
    try {
        const { data } = await api.get('/auth/eu', { headers: { Authorization: `Bearer ${token}` } });

        return { nome: data.data?.nome ?? null, funcao: data.data?.funcao ?? null };
    } catch {
        return { nome: null, funcao: null };
    }
};

// nome guardado na sessão de cada público; o usuário do painel fica com o nome da API (ou o login
// digitado, se a consulta falhar)
const nome_da_sessao = { cliente: nome_do_cliente, afiliado: nome_do_afiliado };

// props da tela que mudam com quem está logado (cotações, limites e saldo do cliente; apostador;
// limites de aposta da página de regras)
const props_do_publico = ['listagem', 'configuracoes', 'saldo', 'apostador', 'limites_aposta'];

// remove a inscrição push deste aparelho no servidor e no navegador; falhas não impedem a saída
const remover_inscricao_push = async (token) => {
    try {
        const inscricao = await inscricao_atual();

        if (!inscricao) return;

        await api.delete('/area-cliente/notificacoes/inscricao', {
            params: { endpoint: inscricao.endpoint },
            headers: { Authorization: `Bearer ${token}` },
        });
        await inscricao.unsubscribe();
    } catch {
        // sem conexão ou inscrição já removida: segue saindo
    }
};

// sessão que muda a tela: cliente, ou usuário do painel enquanto não se sabe se é gestor
const usa_token = (sessao) => sessao?.tipo === 'cliente' || (sessao?.tipo === 'usuario' && sessao.apostador !== 'visitante');

// com sessão de cliente ou de vendedor, o token vai em todas as requisições (Inertia e API), para
// a tela usar as cotações e os limites dele e o cupom apostar como ele; gestor aposta como visitante
// na tela de créditos e no bilhete em tempo real (spec 010), o acesso assistido pelo painel (spec
// 007) tem prioridade sobre a sessão salva no aparelho, que fica intacta
const usa_acesso_assistido = (caminho) => caminho === '/minha-conta' || caminho.startsWith('/bilhete/');

const aplicar_token = (sessao) => {
    const assistido = usa_acesso_assistido(window.location.pathname) ? ler_acesso_assistido() : null;
    const valor = assistido ? `Bearer ${assistido.token}` : (usa_token(sessao) ? `Bearer ${sessao.token}` : null);

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

    // um campo para os três públicos: tenta primeiro o que o texto parece ser e depois os outros
    // (painel antes do afiliado, research R-04 da spec 012); devolve a sessão ou lança o erro da
    // última tentativa
    const entrar = useCallback(async (identificacao, senha) => {
        const texto = identificacao.trim();
        const somente_digitos = texto.replace(/\D/g, '');
        const ordem = parece_telefone(somente_digitos) ? ['cliente', 'usuario', 'afiliado'] : ['usuario', 'afiliado', 'cliente'];
        let ultimo_erro = null;

        for (const tipo of ordem) {
            const valor = tipo === 'cliente' ? somente_digitos : texto;

            if (tipo === 'cliente' && !parece_telefone(valor)) continue;

            try {
                const { data } = await rotas_login[tipo](valor, senha);
                const usuario = tipo === 'usuario' ? await dados_do_usuario(data.token) : null;
                const nome = usuario ? (usuario.nome ?? texto) : (nome_da_sessao[tipo] ? await nome_da_sessao[tipo](data.token) : texto);
                const criada = criar_sessao({ tipo, token: data.token, expira_em: data.expira_em, nome });
                const nova = usuario?.funcao ? atualizar_sessao({ ...criada, funcao: usuario.funcao }) : criada;

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

    // token novo da mesma sessão (ex.: troca de senha do afiliado, spec 012), mantendo tipo e nome
    const trocar_token = useCallback((token, expira_em) => {
        const atual = ler_sessao();

        if (!atual) return;

        definir_sessao(criar_sessao({ tipo: atual.tipo, token, expira_em, nome: atual.nome }));
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
            // o aparelho deixa de receber os avisos do cliente que saiu (spec 010, R-15)
            if (atual.tipo === 'cliente') await remover_inscricao_push(atual.token);

            try {
                await api.post(rotas_logout[atual.tipo], {}, { headers: { Authorization: `Bearer ${atual.token}` } });
            } catch {
                // token já vencido ou sem conexão: a sessão local é encerrada do mesmo jeito
            }
        }

        encerrar_sessao();
        definir_sessao(null);
    }, []);

    // acesso assistido pelo painel (spec 007, US7): vale só nesta aba e só na tela de créditos
    const [acesso_assistido, definir_acesso_assistido] = useState(ler_acesso_assistido);
    const sessao_atual = useRef(sessao);
    sessao_atual.current = sessao;

    // ao trocar de página, o token volta a ser o da sessão do aparelho fora da tela de créditos
    useEffect(() => router.on('navigate', () => aplicar_token(sessao_atual.current)), []);

    const iniciar_acesso_assistido = useCallback(async (codigo) => {
        const { data } = await api.post('/area-cliente/acesso-assistido', { codigo });
        const acesso = { token: data.token, nome: data.cliente.nome, usuario: data.usuario, vence_em: Date.now() + data.expira_em * 1000 };

        gravar_acesso_assistido(acesso);
        definir_acesso_assistido(acesso);
        aplicar_token(sessao_atual.current);

        return acesso;
    }, []);

    const encerrar_acesso_assistido = useCallback(async () => {
        try {
            await api.post('/area-cliente/acesso-assistido/encerrar');
        } catch {
            // já encerrado ou vencido: segue limpando
        }

        remover_acesso_assistido();
        definir_acesso_assistido(null);
        aplicar_token(sessao_atual.current);
    }, []);

    return useMemo(
        () => ({ sessao, entrar, cadastrar, sair, expirar, trocar_token, definir_apostador, acesso_assistido, iniciar_acesso_assistido, encerrar_acesso_assistido }),
        [sessao, entrar, cadastrar, sair, expirar, trocar_token, definir_apostador, acesso_assistido, iniciar_acesso_assistido, encerrar_acesso_assistido],
    );
};

// sessão para os componentes
export default function useSessao() {
    return useContext(SessaoContext);
}
