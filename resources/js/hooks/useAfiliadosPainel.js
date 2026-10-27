import { useCallback, useEffect, useState } from 'react';

import api from '../utils/api';
import { periodo_do_atalho } from '../utils/periods';
import useSessao from './useSessao';

export const abas_afiliados = ['ativos', 'analise', 'lixeira'];

// escolhas de situação de cada aba (FR-005 e FR-021); null = todas do grupo da aba
export const situacoes_por_aba = {
    ativos: [{ chave: null, rotulo: 'Todos' }, { chave: 'Ativo', rotulo: 'Ativos' }, { chave: 'Inativo', rotulo: 'Inativos' }],
    analise: [{ chave: 'Em análise', rotulo: 'Em análise' }, { chave: 'Recusado', rotulo: 'Recusados' }],
    lixeira: [],
};

export const tipos_ganho = ['Saldo líquido', 'Qualquer depósito', 'Primeiro depósito'];
export const redes_sociais = ['Instagram', 'TikTok', 'YouTube', 'Facebook', 'Kwai', 'X', 'Outra'];

// grupo de situações que a API filtra em cada aba (R-02)
const grupo_da_aba = { ativos: 'aprovados', analise: 'analise' };

const situacao_padrao = (aba) => situacoes_por_aba[aba]?.[0]?.chave ?? null;

const lista_inicial = { linhas: [], total: 0, ultima: 1, carregando: true, erro: null };
const resumo_inicial = { dados: null, carregando: false, erro: null };

// filtros da URL (FR-008); valores fora da lista são ignorados; sem período, este mês (R-03)
const filtros_da_url = () => {
    const parametros = new URLSearchParams(window.location.search);
    const aba = abas_afiliados.includes(parametros.get('aba')) ? parametros.get('aba') : 'ativos';
    const situacoes = situacoes_por_aba[aba].map((item) => item.chave);
    const situacao = parametros.get('situacao');
    const gerente = Number(parametros.get('usuarios_id'));
    const mes = periodo_do_atalho('mes');

    return {
        aba,
        busca: parametros.get('busca') ?? '',
        pagina: Math.max(1, Number(parametros.get('page')) || 1),
        situacao: situacoes.includes(situacao) ? situacao : situacao_padrao(aba),
        gerente: gerente ? { id: gerente, nome: parametros.get('gerente') ?? 'Gerente' } : null,
        sem_gerente: parametros.get('sem_gerente') === '1',
        data_inicial: parametros.get('data_inicial') || mes.de,
        data_final: parametros.get('data_final') || mes.ate,
    };
};

// parâmetros da API (contracts/api.md §1.1 e §2.1); a Lixeira não tem grupo nem situação
export const parametros_api = (filtros) => ({
    ...(grupo_da_aba[filtros.aba] ? { grupo: grupo_da_aba[filtros.aba] } : {}),
    ...(filtros.situacao && filtros.aba !== 'lixeira' ? { situacao: filtros.situacao } : {}),
    ...(filtros.busca.trim() ? { busca: filtros.busca.trim() } : {}),
    ...(filtros.gerente ? { usuarios_id: filtros.gerente.id } : {}),
    ...(filtros.sem_gerente ? { sem_gerente: 1 } : {}),
    de: filtros.data_inicial,
    ate: filtros.data_final,
});

// grava a aba e os filtros na URL, para recarregar e voltar da gaveta manterem a lista; o nome do
// gerente vai junto para a etiqueta
const gravar_na_url = (filtros) => {
    const parametros = new URLSearchParams({ aba: filtros.aba });

    if (filtros.busca.trim()) parametros.set('busca', filtros.busca.trim());
    if (filtros.situacao && filtros.situacao !== situacao_padrao(filtros.aba)) parametros.set('situacao', filtros.situacao);

    if (filtros.gerente) {
        parametros.set('usuarios_id', filtros.gerente.id);
        parametros.set('gerente', filtros.gerente.nome);
    }

    if (filtros.sem_gerente) parametros.set('sem_gerente', '1');

    const mes = periodo_do_atalho('mes');

    if (filtros.data_inicial !== mes.de || filtros.data_final !== mes.ate) {
        parametros.set('data_inicial', filtros.data_inicial);
        parametros.set('data_final', filtros.data_final);
    }

    if (filtros.pagina > 1) parametros.set('page', filtros.pagina);

    window.history.replaceState(window.history.state, '', `?${parametros}`);
};

// gerentes da rede de quem consulta (R-15: GET /usuarios, já limitado à sub-hierarquia), todas as
// páginas; erro 403 = sem usuarios.listar
export const carregar_gerentes = async (token) => {
    const gerentes = [];
    let pagina = 1;
    let ultima = 1;

    do {
        const { data } = await api.get('/usuarios', { params: { funcao: 'Gerente', page: pagina, por_pagina: 100 }, headers: { Authorization: `Bearer ${token}` } });
        gerentes.push(...data.data.map((gerente) => ({ id: gerente.id, nome: gerente.nome })));
        ultima = data.meta?.last_page ?? 1;
        pagina += 1;
    } while (pagina <= ultima);

    return gerentes;
};

// dados da tela Afiliados do painel (spec 022, R-01): aba e filtros na URL, lista paginada da aba
// (GET /afiliados ou /afiliados/excluidos) e os cartões (GET /afiliados/resumo), cada um com
// carregando e erro próprios
export default function useAfiliadosPainel({ habilitado }) {
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

    // trocar de aba limpa a lista, a busca e a situação, para não mostrar as linhas de outra aba
    const trocar_aba = useCallback((aba) => {
        definir_lista(lista_inicial);
        mudar({ aba, busca: '', situacao: situacao_padrao(aba) });
    }, [mudar]);

    const recarregar = useCallback(() => definir_recarga((atual) => atual + 1), []);

    const chave = JSON.stringify({ aba: filtros.aba, ...parametros_api(filtros) });
    const pronto = habilitado && token;

    useEffect(() => {
        if (!pronto) return undefined;

        let ativo = true;
        const { aba, ...parametros } = JSON.parse(chave);
        const rota = aba === 'lixeira' ? '/afiliados/excluidos' : '/afiliados';

        definir_lista((atual) => ({ ...atual, carregando: true, erro: null }));
        api.get(rota, { params: { ...parametros, page: filtros.pagina }, headers: { Authorization: `Bearer ${token}` } })
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

    // os cartões seguem a busca, o gerente e o período, não a aba nem a situação (R-16)
    const { aba: _aba, grupo: _grupo, situacao: _situacao, ...filtros_resumo } = JSON.parse(chave);
    const chave_resumo = JSON.stringify(filtros_resumo);

    useEffect(() => {
        if (!pronto) return undefined;

        let ativo = true;

        definir_resumo((atual) => ({ ...atual, carregando: true, erro: null }));
        api.get('/afiliados/resumo', { params: JSON.parse(chave_resumo), headers: { Authorization: `Bearer ${token}` } })
            .then(({ data }) => ativo && definir_resumo({ dados: data.data, carregando: false, erro: null }))
            .catch((erro) => ativo && definir_resumo((atual) => ({ ...atual, carregando: false, erro })));

        return () => {
            ativo = false;
        };
    }, [pronto, token, chave_resumo, recarga]);

    return { filtros, mudar, trocar_aba, lista, resumo, recarregar, token };
}
