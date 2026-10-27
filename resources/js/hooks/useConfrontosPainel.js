import { useCallback, useEffect, useState } from 'react';

import api from '../utils/api';
import useSessao from './useSessao';

export const abas_confrontos = ['jogos', 'mais_apostados', 'pendentes', 'campeonatos'];

// atalhos de dia das abas Jogos e Mais apostados (FR-005); "data" é o "Escolher data"
export const atalhos_dia = [
    { chave: 'ontem', rotulo: 'Ontem' },
    { chave: 'hoje', rotulo: 'Hoje' },
    { chave: 'amanha', rotulo: 'Amanhã' },
    { chave: 'depois_de_amanha', rotulo: 'Depois de amanhã' },
];

export const tipos_jogo = [
    { chave: 'todos', rotulo: 'Todos' },
    { chave: 'pre_jogo', rotulo: 'Pré-jogo' },
    { chave: 'ao_vivo', rotulo: 'Ao vivo' },
];

// rota da lista de cada aba (contracts/api)
const rota_da_aba = {
    jogos: '/confrontos',
    mais_apostados: '/confrontos/mais-apostados',
    pendentes: '/confrontos/pendentes',
    campeonatos: '/campeonatos',
};

// atualização automática (FR-039): a cada 30 s, só nas visões que mudam rápido
const INTERVALO_ATUALIZACAO = 30000;

const lista_inicial = { linhas: [], total: 0, ultima: 1, carregando: true, erro: null, falha_atualizacao: false };
const resumo_inicial = { dados: null, carregando: false, erro: null };

// fuso do navegador no formato da API (±HH:MM), para o dia e as datas da lista
export const fuso_do_navegador = () => {
    const minutos = -new Date().getTimezoneOffset();
    const sinal = minutos >= 0 ? '+' : '-';
    const absoluto = Math.abs(minutos);

    return `${sinal}${String(Math.floor(absoluto / 60)).padStart(2, '0')}:${String(absoluto % 60).padStart(2, '0')}`;
};

// "aaaa-mm-dd" de hoje + dias, no fuso do navegador (limites do "Escolher data")
export const data_relativa = (dias) => {
    const data = new Date();
    data.setDate(data.getDate() + dias);

    return [data.getFullYear(), String(data.getMonth() + 1).padStart(2, '0'), String(data.getDate()).padStart(2, '0')].join('-');
};

// filtros da URL (FR-008); valores fora da lista são ignorados
const filtros_da_url = () => {
    const parametros = new URLSearchParams(window.location.search);
    const da_lista = (chave, lista, padrao) => (lista.includes(parametros.get(chave)) ? parametros.get(chave) : padrao);
    const campeonato = Number(parametros.get('campeonatos_id'));
    const data = /^\d{4}-\d{2}-\d{2}$/.test(parametros.get('data') ?? '') ? parametros.get('data') : null;

    return {
        aba: da_lista('aba', abas_confrontos, 'jogos'),
        busca: parametros.get('busca') ?? '',
        tipo: da_lista('tipo', tipos_jogo.map((tipo) => tipo.chave), 'todos'),
        dia: data ? null : da_lista('dia', atalhos_dia.map((dia) => dia.chave), 'hoje'),
        data,
        campeonato: campeonato ? { id: campeonato, nome: parametros.get('campeonato') ?? 'Campeonato' } : null,
        situacao: da_lista('situacao', ['ativos', 'desativados'], null),
        manual: parametros.get('manual') === '1',
        pais: parametros.get('pais') || null,
        favorito: parametros.get('favorito') === '1',
        pagina: Math.max(1, Number(parametros.get('page')) || 1),
    };
};

// parâmetros da API de cada aba (contracts/api "Filtros da lista" e §1.5)
export const parametros_api = (filtros) => {
    const comuns = {
        ...(filtros.busca.trim() ? { busca: filtros.busca.trim() } : {}),
        ...(filtros.situacao ? { ativo: filtros.situacao === 'ativos' ? 1 : 0 } : {}),
        ...(filtros.manual ? { manual: 1 } : {}),
    };

    if (filtros.aba === 'campeonatos') {
        return { ...comuns, ...(filtros.pais ? { pais: filtros.pais } : {}), ...(filtros.favorito ? { favorito: 1 } : {}) };
    }

    if (filtros.aba === 'pendentes') return filtros.busca.trim() ? { busca: filtros.busca.trim() } : {};

    return {
        ...comuns,
        ...(filtros.data ? { data: filtros.data } : { dia: filtros.dia ?? 'hoje' }),
        ...(filtros.tipo !== 'todos' ? { tipo: filtros.tipo } : {}),
        ...(filtros.campeonato ? { campeonatos_id: filtros.campeonato.id } : {}),
        fuso_horario: fuso_do_navegador(),
    };
};

// grava a aba e os filtros na URL, para recarregar e voltar da tela Cotações manterem a lista; o
// nome do campeonato vai junto para a etiqueta
const gravar_na_url = (filtros) => {
    const parametros = new URLSearchParams({ aba: filtros.aba });

    if (filtros.busca.trim()) parametros.set('busca', filtros.busca.trim());
    if (filtros.tipo !== 'todos') parametros.set('tipo', filtros.tipo);
    if (filtros.data) parametros.set('data', filtros.data);
    else if (filtros.dia && filtros.dia !== 'hoje') parametros.set('dia', filtros.dia);

    if (filtros.campeonato) {
        parametros.set('campeonatos_id', filtros.campeonato.id);
        parametros.set('campeonato', filtros.campeonato.nome);
    }

    if (filtros.situacao) parametros.set('situacao', filtros.situacao);
    if (filtros.manual) parametros.set('manual', '1');
    if (filtros.pais) parametros.set('pais', filtros.pais);
    if (filtros.favorito) parametros.set('favorito', '1');
    if (filtros.pagina > 1) parametros.set('page', filtros.pagina);

    window.history.replaceState(window.history.state, '', `?${parametros}`);
};

// dados da tela Confrontos do painel (spec 023, R-01): aba e filtros na URL, lista paginada da aba
// e os cartões (GET /confrontos/resumo, fora da aba Campeonatos), cada um com carregando e erro
// próprios; com o tipo Ao vivo ou na aba Pendentes, atualiza sozinho a cada 30 s (R-18)
export default function useConfrontosPainel({ habilitado }) {
    const { sessao, expirar } = useSessao();
    const token = sessao?.token;
    const [filtros, definir_filtros] = useState(filtros_da_url);
    const [recarga, definir_recarga] = useState({ vez: 0, silencioso: false });
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

    // trocar de aba limpa a lista e a busca, para não mostrar as linhas de outra aba
    const trocar_aba = useCallback((aba, extras = {}) => {
        definir_lista(lista_inicial);
        mudar({ aba, busca: '', ...extras });
    }, [mudar]);

    // silencioso = sem esqueleto: mantém a lista e só avisa se falhar (atualização automática)
    const recarregar = useCallback((opcoes = {}) => definir_recarga((atual) => ({ vez: atual.vez + 1, silencioso: Boolean(opcoes.silencioso) })), []);

    const chave = JSON.stringify({ aba: filtros.aba, ...parametros_api(filtros) });
    const pronto = habilitado && token;

    useEffect(() => {
        if (!pronto) return undefined;

        let ativo = true;
        const { aba, ...parametros } = JSON.parse(chave);
        const sem_esqueleto = recarga.silencioso;

        definir_lista((atual) => ({ ...atual, carregando: !sem_esqueleto, erro: null }));
        api.get(rota_da_aba[aba], { params: { ...parametros, page: filtros.pagina }, headers: { Authorization: `Bearer ${token}` } })
            .then(({ data }) => ativo && definir_lista({ linhas: data.data, total: data.meta?.total ?? data.total ?? 0, ultima: data.meta?.last_page ?? data.last_page ?? 1, carregando: false, erro: null, falha_atualizacao: false }))
            .catch((erro) => {
                if (!ativo) return;

                if (erro.response?.status === 401) expirar();

                // na atualização automática, mantém os dados anteriores com o aviso
                if (sem_esqueleto) definir_lista((atual) => ({ ...atual, carregando: false, falha_atualizacao: true }));
                else definir_lista((atual) => ({ ...atual, carregando: false, erro }));
            });

        return () => {
            ativo = false;
        };
    }, [pronto, token, chave, filtros.pagina, recarga]);

    // os cartões seguem os filtros das abas de jogos, menos a situação; a aba Campeonatos não tem
    const { aba: aba_resumo, ativo: _ativo, ...filtros_resumo } = JSON.parse(chave);
    const chave_resumo = aba_resumo === 'campeonatos' ? null : JSON.stringify(aba_resumo === 'pendentes' ? { dia: 'hoje', fuso_horario: fuso_do_navegador() } : filtros_resumo);

    useEffect(() => {
        if (!pronto || !chave_resumo) return undefined;

        let ativo = true;

        definir_resumo((atual) => ({ ...atual, carregando: true, erro: null }));
        api.get('/confrontos/resumo', { params: JSON.parse(chave_resumo), headers: { Authorization: `Bearer ${token}` } })
            .then(({ data }) => ativo && definir_resumo({ dados: data.data, carregando: false, erro: null }))
            .catch((erro) => ativo && definir_resumo((atual) => ({ ...atual, carregando: false, erro: recarga.silencioso ? null : erro })));

        return () => {
            ativo = false;
        };
    }, [pronto, token, chave_resumo, recarga]);

    // atualização automática: só com o tipo Ao vivo (Jogos e Mais apostados) ou na aba Pendentes;
    // pausa com a aba do navegador escondida e recarrega ao voltar
    const automatica = pronto && (filtros.aba === 'pendentes' || (['jogos', 'mais_apostados'].includes(filtros.aba) && filtros.tipo === 'ao_vivo'));

    useEffect(() => {
        if (!automatica) return undefined;

        const tique = () => document.visibilityState === 'visible' && recarregar({ silencioso: true });
        const intervalo = window.setInterval(tique, INTERVALO_ATUALIZACAO);
        const ao_mudar_visibilidade = () => document.visibilityState === 'visible' && recarregar({ silencioso: true });

        document.addEventListener('visibilitychange', ao_mudar_visibilidade);

        return () => {
            window.clearInterval(intervalo);
            document.removeEventListener('visibilitychange', ao_mudar_visibilidade);
        };
    }, [automatica, filtros.aba, filtros.tipo]);

    return { filtros, mudar, trocar_aba, lista, resumo, recarregar, token };
}
