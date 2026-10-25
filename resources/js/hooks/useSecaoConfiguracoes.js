import { useCallback, useEffect, useMemo, useState } from 'react';

import api from '../utils/api';
import useSessao from './useSessao';

// a maioria das rotas responde { data: {...} }; a do limite de notificações (spec 019) responde o
// objeto direto
const dados_da_resposta = (corpo) => corpo?.data ?? corpo ?? {};

// valores iguais para o formulário: listas comparadas pelo conteúdo, números e textos pelo texto
const iguais = (a, b) => {
    if (Array.isArray(a) || Array.isArray(b)) return JSON.stringify(a ?? []) === JSON.stringify(b ?? []);

    return String(a ?? '') === String(b ?? '');
};

// uma seção da tela Configurações (spec 021, research R-17): carrega os valores atuais, guarda o
// original, conta e desfaz as alterações e salva só o que mudou (PUT na mesma rota); os erros 422
// voltam por campo
export default function useSecaoConfiguracoes({ rota, habilitado = true }) {
    const { sessao, expirar } = useSessao();
    const token = sessao?.token;
    const [originais, definir_originais] = useState(null);
    const [valores, definir_valores] = useState(null);
    const [erros, definir_erros] = useState({});
    const [carregando, definir_carregando] = useState(true);
    const [salvando, definir_salvando] = useState(false);
    const [erro_carga, definir_erro_carga] = useState(null);
    const [recarga, definir_recarga] = useState(0);

    const autorizacao = useMemo(() => ({ headers: { Authorization: `Bearer ${token}` } }), [token]);

    useEffect(() => {
        if (!habilitado || !token) return undefined;

        let ativo = true;
        definir_carregando(true);
        definir_erro_carga(null);

        api.get(rota, autorizacao)
            .then(({ data }) => {
                if (!ativo) return;

                const dados = dados_da_resposta(data);
                definir_originais(dados);
                definir_valores(dados);
                definir_erros({});
            })
            .catch((falha) => {
                if (!ativo) return;
                if (falha.response?.status === 401) expirar();

                definir_erro_carga(falha);
            })
            .finally(() => ativo && definir_carregando(false));

        return () => {
            ativo = false;
        };
    }, [rota, habilitado, token, recarga]);

    // só as chaves diferentes do original
    const alterados = useMemo(() => {
        if (!valores || !originais) return {};

        return Object.fromEntries(Object.entries(valores).filter(([chave, valor]) => !iguais(valor, originais[chave])));
    }, [valores, originais]);

    const quantidade = Object.keys(alterados).length;

    const mudar = useCallback((campo, valor) => {
        definir_valores((atuais) => ({ ...atuais, [campo]: valor }));
        definir_erros((atuais) => {
            if (!atuais[campo]) return atuais;

            const { [campo]: _removido, ...resto } = atuais;

            return resto;
        });
    }, []);

    const desfazer = useCallback(() => {
        definir_valores(originais);
        definir_erros({});
    }, [originais]);

    // devolve true quando salvou; 422 preenche os erros por campo e os demais erros sobem
    const salvar = useCallback(async (extras = {}) => {
        definir_salvando(true);

        try {
            const { data } = await api.put(rota, { ...alterados, ...extras }, autorizacao);
            const dados = { ...originais, ...dados_da_resposta(data) };

            definir_originais(dados);
            definir_valores(dados);
            definir_erros({});

            return true;
        } catch (falha) {
            if (falha.response?.status !== 422) throw falha;

            const recebidos = falha.response.data?.errors ?? {};
            definir_erros(Object.fromEntries(Object.entries(recebidos).map(([campo, mensagens]) => [campo, mensagens[0]])));

            return false;
        } finally {
            definir_salvando(false);
        }
    }, [rota, alterados, originais, autorizacao]);

    const recarregar = useCallback(() => definir_recarga((atual) => atual + 1), []);

    return {
        valores, originais, alterados, quantidade, erros, carregando, salvando, erro_carga, token, autorizacao,
        mudar, desfazer, salvar, recarregar,
    };
}
