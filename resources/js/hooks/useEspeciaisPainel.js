import { useCallback, useEffect, useState } from 'react';

import api from '../utils/api';
import useSessao from './useSessao';

export const abas_especiais = ['especiais', 'lixeira'];

// escolhas do filtro de situação (FR-003); Aguardando é o padrão
export const situacoes_especiais = [
    { chave: 'Aguardando', rotulo: 'Aguardando' },
    { chave: 'Encerrado', rotulo: 'Encerrado' },
    { chave: 'Cancelado', rotulo: 'Cancelado' },
    { chave: 'Todas', rotulo: 'Todas' },
];

export const situacao_padrao = 'Aguardando';

const lista_inicial = { linhas: [], total: 0, ultima: 1, alcance: null, carregando: true, erro: null };
const resumo_inicial = { dados: null, carregando: false, erro: null };

// filtros da URL; valores fora da lista são ignorados
const filtros_da_url = () => {
    const parametros = new URLSearchParams(window.location.search);
    const situacao = parametros.get('situacao');

    return {
        aba: abas_especiais.includes(parametros.get('aba')) ? parametros.get('aba') : 'especiais',
        busca: parametros.get('busca') ?? '',
        pagina: Math.max(1, Number(parametros.get('page')) || 1),
        situacao: situacoes_especiais.some((item) => item.chave === situacao) ? situacao : situacao_padrao,
    };
};

// parâmetros da API (contracts/api §1.1); a Lixeira não filtra situação
const parametros_api = (filtros) => ({
    ...(filtros.aba === 'lixeira' ? { lixeira: 1 } : { situacao: filtros.situacao }),
    ...(filtros.busca.trim() ? { busca: filtros.busca.trim() } : {}),
});

// grava a aba e os filtros na URL, para recarregar e voltar da gaveta manterem a lista
const gravar_na_url = (filtros) => {
    const parametros = new URLSearchParams({ aba: filtros.aba });

    if (filtros.busca.trim()) parametros.set('busca', filtros.busca.trim());
    if (filtros.aba !== 'lixeira' && filtros.situacao !== situacao_padrao) parametros.set('situacao', filtros.situacao);
    if (filtros.pagina > 1) parametros.set('page', filtros.pagina);

    window.history.replaceState(window.history.state, '', `?${parametros}`);
};

// dados da tela Especiais do painel (spec 024, R-01): aba e filtros na URL, lista paginada da aba
// (GET /especiais, com lixeira=1 na Lixeira) e os cartões (GET /especiais/resumo, só na aba
// Especiais), cada um com carregando e erro próprios
export default function useEspeciaisPainel({ habilitado }) {
    const { sessao, expirar } = useSessao();
    const token = sessao?.token;
    const [filtros, definir_filtros] = useState(filtros_da_url);
    const [recarga, definir_recarga] = useState(0);
    const [lista, definir_lista] = useState(lista_inicial);
    const [resumo, definir_resumo] = useState(resumo_inicial);

    // trocar qualquer filtro volta para a página 1; trocar só a página mantém o resto
    const mudar = useCallback((novos) => {
        definir_filtros((atuais) => {
            const proximos = { ...atuais, pagina: 1, ...novos };
            gravar_na_url(proximos);

            return proximos;
        });
    }, []);

    // trocar de aba limpa a lista e a busca, para não mostrar as linhas da outra aba
    const trocar_aba = useCallback((aba) => {
        definir_lista(lista_inicial);
        mudar({ aba, busca: '', situacao: situacao_padrao });
    }, [mudar]);

    const recarregar = useCallback(() => definir_recarga((atual) => atual + 1), []);

    const chave = JSON.stringify(parametros_api(filtros));
    const pronto = habilitado && token;

    useEffect(() => {
        if (!pronto) return undefined;

        let ativo = true;

        definir_lista((atual) => ({ ...atual, carregando: true, erro: null }));
        api.get('/especiais', { params: { ...JSON.parse(chave), page: filtros.pagina }, headers: { Authorization: `Bearer ${token}` } })
            .then(({ data }) => ativo && definir_lista({
                linhas: data.data, total: data.meta.total, ultima: data.meta.last_page, alcance: data.alcance, carregando: false, erro: null,
            }))
            .catch((erro) => {
                if (!ativo) return;

                if (erro.response?.status === 401) expirar();
                definir_lista((atual) => ({ ...atual, carregando: false, erro }));
            });

        return () => {
            ativo = false;
        };
    }, [pronto, token, chave, filtros.pagina, recarga]);

    // os cartões seguem só a busca (FR-002) e não aparecem na Lixeira
    const chave_resumo = filtros.aba === 'lixeira' ? null : filtros.busca.trim();

    useEffect(() => {
        if (!pronto || chave_resumo === null) return undefined;

        let ativo = true;

        definir_resumo((atual) => ({ ...atual, carregando: true, erro: null }));
        api.get('/especiais/resumo', { params: chave_resumo ? { busca: chave_resumo } : {}, headers: { Authorization: `Bearer ${token}` } })
            .then(({ data }) => ativo && definir_resumo({ dados: data, carregando: false, erro: null }))
            .catch((erro) => ativo && definir_resumo((atual) => ({ ...atual, carregando: false, erro })));

        return () => {
            ativo = false;
        };
    }, [pronto, token, chave_resumo, recarga]);

    return { filtros, mudar, trocar_aba, lista, resumo, recarregar, token };
}
