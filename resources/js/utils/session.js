import { chave_sessao, gravar_json, ler_json, remover } from './storage';

// sessão de quem entrou pelo site (apostador ou usuário do painel), com o token JWT da API

// segundos de folga: o token é descartado um pouco antes de vencer
const folga_segundos = 30;

// { tipo: 'cliente' | 'usuario', token, nome, vence_em (ms), apostador? }
// apostador (só usuário do painel): 'vendedor' ou 'visitante' (gestor), informado pela tela
export const ler_sessao = () => {
    const sessao = ler_json(chave_sessao);

    if (!sessao?.token || !sessao?.vence_em || Date.now() >= sessao.vence_em) {
        if (sessao) remover(chave_sessao);
        return null;
    }

    return sessao;
};

// expira_em vem da API em segundos a partir de agora
export const criar_sessao = ({ tipo, token, expira_em, nome }) => {
    const sessao = { tipo, token, nome, vence_em: Date.now() + (expira_em - folga_segundos) * 1000 };

    gravar_json(chave_sessao, sessao);

    return sessao;
};

// grava a sessão alterada (mesmo token e vencimento)
export const atualizar_sessao = (sessao) => {
    gravar_json(chave_sessao, sessao);

    return sessao;
};

export const encerrar_sessao = () => remover(chave_sessao);
