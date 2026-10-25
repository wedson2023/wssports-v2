import { useCallback, useEffect, useState } from 'react';

import api from '../utils/api';
import useSessao from './useSessao';

export const abas_bonus = ['promocoes', 'recebidos'];

export const situacoes_promocao = ['Vigente', 'Agendada', 'Encerrada', 'Desativada', 'Estornando', 'Estornada'];
export const situacoes_bonus = ['Em andamento', 'Cumprido', 'Convertido', 'Expirado', 'Excluído pelo cliente', 'Estornado', 'Todos'];
export const modalidades = ['Esportes', 'Cassino'];
export const categorias = ['Primeiro cadastro', 'Primeiro depósito', 'Qualquer depósito', 'Indicação'];

// intervalo da consulta do progresso do estorno geral (R-11)
const INTERVALO_ESTORNO = 3000;

const lista_inicial = { linhas: [], total: 0, ultima: 1, carregando: true, erro: null };
const resumo_inicial = { dados: null, carregando: false, erro: null };

// filtros da URL (FR-009, FR-023); valores fora da lista são ignorados
const filtros_da_url = () => {
    const parametros = new URLSearchParams(window.location.search);
    const da_lista = (chave, lista, padrao) => (lista.includes(parametros.get(chave)) ? parametros.get(chave) : padrao);
    const afiliado = parametros.get('afiliados_id');
    const promocao = Number(parametros.get('clientes_promocoes_id'));

    return {
        aba: da_lista('aba', abas_bonus, 'promocoes'),
        busca: parametros.get('busca') ?? '',
        pagina: Math.max(1, Number(parametros.get('page')) || 1),
        situacao_promocao: da_lista('situacao_promocao', situacoes_promocao, null),
        modalidade: da_lista('modalidade', modalidades, null),
        categoria: da_lista('categoria', categorias, null),
        // afiliado escolhido da lista, ou "geral" (promoções sem afiliado)
        afiliado: afiliado === 'geral' ? 'geral' : (Number(afiliado) ? { id: Number(afiliado), nome: parametros.get('afiliado') ?? 'Afiliado' } : null),
        situacao_bonus: da_lista('situacao', situacoes_bonus, 'Em andamento'),
        promocao: promocao ? { id: promocao, nome: parametros.get('promocao') ?? 'Promoção' } : null,
        data_inicial: parametros.get('data_inicial') || null,
        data_final: parametros.get('data_final') || null,
    };
};

// parâmetros da API da aba (contracts/api.md §1.1 e §2.1)
export const parametros_api = (filtros) => {
    const busca = filtros.busca.trim() ? { busca: filtros.busca.trim() } : {};
    const modalidade = filtros.modalidade ? { modalidade: filtros.modalidade } : {};

    if (filtros.aba === 'recebidos') {
        return {
            situacao: filtros.situacao_bonus,
            ...busca,
            ...modalidade,
            ...(filtros.promocao ? { clientes_promocoes_id: filtros.promocao.id } : {}),
            ...(filtros.data_inicial && filtros.data_final ? { data_inicial: filtros.data_inicial, data_final: filtros.data_final } : {}),
        };
    }

    return {
        ...busca,
        ...modalidade,
        ...(filtros.situacao_promocao ? { situacao: filtros.situacao_promocao } : {}),
        ...(filtros.categoria ? { categoria: filtros.categoria } : {}),
        ...(filtros.afiliado ? { afiliados_id: filtros.afiliado === 'geral' ? 'geral' : filtros.afiliado.id } : {}),
    };
};

// endereço da lista de cada aba
const rota_da_aba = { promocoes: '/clientes-promocoes', recebidos: '/clientes-bonus' };

// grava a aba e os filtros na URL, para recarregar e voltar do detalhe manterem a lista; os nomes do
// afiliado e da promoção vão junto para as etiquetas
const gravar_na_url = (filtros) => {
    const { situacao, afiliados_id, clientes_promocoes_id, ...resto } = parametros_api(filtros);
    const parametros = new URLSearchParams({ aba: filtros.aba, ...resto });

    if (filtros.aba === 'recebidos') parametros.set('situacao', situacao);
    if (filtros.aba === 'promocoes' && situacao) parametros.set('situacao_promocao', situacao);

    if (afiliados_id) {
        parametros.set('afiliados_id', afiliados_id);
        if (afiliados_id !== 'geral') parametros.set('afiliado', filtros.afiliado.nome);
    }

    if (clientes_promocoes_id) {
        parametros.set('clientes_promocoes_id', clientes_promocoes_id);
        parametros.set('promocao', filtros.promocao.nome);
    }

    if (filtros.pagina > 1) parametros.set('page', filtros.pagina);

    window.history.replaceState(window.history.state, '', `?${parametros}`);
};

// dados da tela Bônus do painel (spec 020): aba e filtros na URL, lista paginada da aba e, em Bônus
// recebidos, os cartões (GET /clientes-bonus/resumo), cada um com carregando e erro próprios; enquanto
// houver promoção "Estornando" na página, consulta só ela a cada 3 s até concluir
export default function useBonusPainel({ habilitado }) {
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
    const trocar_aba = useCallback((aba, extra = {}) => {
        definir_lista(lista_inicial);
        mudar({ aba, busca: '', ...extra });
    }, [mudar]);

    const recarregar = useCallback(() => definir_recarga((atual) => atual + 1), []);

    // troca uma promoção da lista pela versão nova, sem recarregar a página inteira
    const atualizar_linha = useCallback((promocao) => {
        definir_lista((atual) => ({ ...atual, linhas: atual.linhas.map((linha) => (linha.id === promocao.id ? promocao : linha)) }));
    }, []);

    const chave = JSON.stringify({ aba: filtros.aba, ...parametros_api(filtros) });
    const pronto = habilitado && token;

    useEffect(() => {
        if (!pronto) return undefined;

        let ativo = true;
        const { aba, ...parametros } = JSON.parse(chave);

        definir_lista((atual) => ({ ...atual, carregando: true, erro: null }));
        api.get(rota_da_aba[aba], { params: { ...parametros, page: filtros.pagina }, headers: { Authorization: `Bearer ${token}` } })
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
        if (!pronto || filtros.aba !== 'recebidos') return undefined;

        let ativo = true;
        const { aba, situacao, ...parametros } = JSON.parse(chave);

        definir_resumo((atual) => ({ ...atual, carregando: true, erro: null }));
        api.get('/clientes-bonus/resumo', { params: parametros, headers: { Authorization: `Bearer ${token}` } })
            .then(({ data }) => ativo && definir_resumo({ dados: data, carregando: false, erro: null }))
            .catch((erro) => ativo && definir_resumo((atual) => ({ ...atual, carregando: false, erro })));

        return () => {
            ativo = false;
        };
    }, [pronto, token, chave, recarga]);

    // progresso do estorno geral: só as promoções "Estornando" da página
    const estornando = filtros.aba === 'promocoes'
        ? lista.linhas.filter((linha) => linha.situacao === 'Estornando').map((linha) => linha.id).join(',')
        : '';

    useEffect(() => {
        if (!pronto || !estornando) return undefined;

        const intervalo = window.setInterval(() => {
            estornando.split(',').forEach((id) => {
                api.get(`/clientes-promocoes/${id}`, { headers: { Authorization: `Bearer ${token}` } })
                    .then(({ data }) => atualizar_linha(data.data))
                    .catch(() => {});
            });
        }, INTERVALO_ESTORNO);

        return () => window.clearInterval(intervalo);
    }, [pronto, token, estornando]);

    return { filtros, mudar, trocar_aba, lista, resumo, recarregar, token };
}
