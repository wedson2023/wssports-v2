import { useCallback, useEffect, useState } from 'react';

import api from '../utils/api';
import useSessao from './useSessao';

export const abas_clientes = ['cadastros', 'saques', 'ranking', 'lixeira'];

export const situacoes_saque = ['Pendente', 'Processando', 'Pago', 'Recusado'];
export const periodos_ranking = ['24h', '7d', '30d', '90d', 'personalizado'];
export const criterios_ranking = ['depositos', 'valor_apostado', 'resultado', 'saques'];

// filtros de Cadastros e Lixeira (contracts/api.md §1.1) gravados na URL como chegam à API
export const chaves_filtros = [
    'ativo', 'genero', 'ddi', 'cadastro_de', 'cadastro_ate', 'idade_minima', 'idade_maxima', 'saldo_minimo', 'saldo_maximo',
    'com_saldo_promocional', 'saque_bloqueado', 'com_cpf', 'com_email', 'deposito', 'pix_em_aberto', 'modalidade', 'aniversariantes',
    'periodo_de', 'periodo_ate',
];

const lista_inicial = { linhas: [], total: 0, ultima: 1, carregando: true, erro: null };
const resumo_inicial = { dados: null, carregando: false, erro: null };

// filtros da URL (FR-009); valores fora da lista são ignorados
const filtros_da_url = () => {
    const parametros = new URLSearchParams(window.location.search);
    const da_lista = (chave, lista, padrao) => (lista.includes(parametros.get(chave)) ? parametros.get(chave) : padrao);

    return {
        aba: da_lista('aba', abas_clientes, 'cadastros'),
        busca: parametros.get('busca') ?? '',
        pagina: Math.max(1, Number(parametros.get('page')) || 1),
        ordenar_por: parametros.get('ordenar_por') ?? 'created_at',
        direcao: da_lista('direcao', ['asc', 'desc'], 'desc'),
        ...Object.fromEntries(chaves_filtros.map((chave) => [chave, parametros.get(chave) || null])),
        afiliado: Number(parametros.get('afiliados_id')) ? { id: Number(parametros.get('afiliados_id')), nome: parametros.get('afiliado') ?? 'Afiliado' } : null,
        situacao: da_lista('situacao', situacoes_saque, 'Pendente'),
        saques_de: parametros.get('saques_de') || null,
        saques_ate: parametros.get('saques_ate') || null,
        ranking_periodo: da_lista('ranking_periodo', periodos_ranking, '30d'),
        ranking_de: parametros.get('ranking_de') || null,
        ranking_ate: parametros.get('ranking_ate') || null,
        criterio: da_lista('criterio', criterios_ranking, 'depositos'),
    };
};

// parâmetros da API da aba (contracts/api.md §1, §3 e §4)
export const parametros_api = (filtros) => {
    const busca = filtros.busca.trim() ? { busca: filtros.busca.trim() } : {};

    if (filtros.aba === 'saques') {
        return {
            situacao: filtros.situacao,
            ...busca,
            ...(filtros.saques_de && filtros.saques_ate ? { de: filtros.saques_de, ate: filtros.saques_ate } : {}),
        };
    }

    if (filtros.aba === 'ranking') {
        return {
            periodo: filtros.ranking_periodo,
            criterio: filtros.criterio,
            ...(filtros.ranking_periodo === 'personalizado' ? { de: filtros.ranking_de, ate: filtros.ranking_ate } : {}),
        };
    }

    return {
        ...busca,
        ordenar_por: filtros.aba === 'lixeira' ? 'deleted_at' : filtros.ordenar_por,
        direcao: filtros.aba === 'lixeira' ? 'desc' : filtros.direcao,
        ...Object.fromEntries(chaves_filtros.filter((chave) => filtros[chave] !== null && filtros[chave] !== '').map((chave) => [chave, filtros[chave]])),
        ...(filtros.afiliado ? { afiliados_id: filtros.afiliado.id } : {}),
    };
};

// endereço da lista de cada aba
const rota_da_aba = { cadastros: '/clientes', lixeira: '/clientes/excluidos', saques: '/saques', ranking: '/clientes/ranking' };

// grava a aba e os filtros na URL, para recarregar e voltar do detalhe manterem a lista
const gravar_na_url = (filtros) => {
    const { afiliados_id, ...resto } = filtros.aba === 'cadastros' || filtros.aba === 'lixeira' ? parametros_api(filtros) : {};
    const parametros = new URLSearchParams({ aba: filtros.aba, ...resto });

    if (filtros.aba === 'saques') {
        parametros.set('situacao', filtros.situacao);
        if (filtros.busca.trim()) parametros.set('busca', filtros.busca.trim());
        if (filtros.saques_de && filtros.saques_ate) {
            parametros.set('saques_de', filtros.saques_de);
            parametros.set('saques_ate', filtros.saques_ate);
        }
    }

    if (filtros.aba === 'ranking') {
        parametros.set('ranking_periodo', filtros.ranking_periodo);
        parametros.set('criterio', filtros.criterio);
        if (filtros.ranking_periodo === 'personalizado') {
            parametros.set('ranking_de', filtros.ranking_de ?? '');
            parametros.set('ranking_ate', filtros.ranking_ate ?? '');
        }
    }

    if (afiliados_id) {
        parametros.set('afiliados_id', afiliados_id);
        parametros.set('afiliado', filtros.afiliado.nome);
    }

    if (filtros.pagina > 1) parametros.set('page', filtros.pagina);

    window.history.replaceState(window.history.state, '', `?${parametros}`);
};

// dados da tela Clientes do painel (spec 019): aba e filtros na URL, lista paginada da aba e, em
// Saques, os cartões (GET /saques/resumo), cada um com carregando e erro próprios; os dados antigos
// ficam na tela enquanto atualiza; recarregar() depois de cada ação
export default function useClientesPainel({ habilitado }) {
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

    // trocar de aba limpa a lista e a busca, para não mostrar as linhas de outra aba
    const trocar_aba = useCallback((aba) => {
        definir_lista(lista_inicial);
        mudar({ aba, busca: '' });
    }, [mudar]);

    const recarregar = useCallback(() => definir_recarga((atual) => atual + 1), []);

    const chave = JSON.stringify({ aba: filtros.aba, ...parametros_api(filtros) });
    const pronto = habilitado && token && !(filtros.aba === 'ranking' && filtros.ranking_periodo === 'personalizado' && !(filtros.ranking_de && filtros.ranking_ate));

    useEffect(() => {
        if (!pronto) return undefined;

        let ativo = true;
        const { aba, ...parametros } = JSON.parse(chave);
        const pagina = aba === 'saques' ? { pagina: filtros.pagina } : { page: filtros.pagina };

        definir_lista((atual) => ({ ...atual, carregando: true, erro: null }));
        api.get(rota_da_aba[aba], { params: { ...parametros, ...pagina }, headers: { Authorization: `Bearer ${token}` } })
            .then(({ data }) => ativo && definir_lista({ linhas: data.data, total: data.meta.total, ultima: data.meta.last_page, carregando: false, erro: null }))
            .catch((erro) => {
                if (!ativo) return;

                if (erro.response?.status === 401) expirar();
                definir_lista((atual) => ({ ...atual, carregando: false, erro }));
            });

        return () => {
            ativo = false;
        };
    }, [pronto, token, chave, filtros.pagina, recarga]);

    useEffect(() => {
        if (!pronto || filtros.aba !== 'saques') return undefined;

        let ativo = true;
        const { aba, situacao, ...parametros } = JSON.parse(chave);

        definir_resumo((atual) => ({ ...atual, carregando: true, erro: null }));
        api.get('/saques/resumo', { params: parametros, headers: { Authorization: `Bearer ${token}` } })
            .then(({ data }) => ativo && definir_resumo({ dados: data, carregando: false, erro: null }))
            .catch((erro) => ativo && definir_resumo((atual) => ({ ...atual, carregando: false, erro })));

        return () => {
            ativo = false;
        };
    }, [pronto, token, chave, recarga]);

    return { filtros, mudar, trocar_aba, lista, resumo, recarregar, token };
}
