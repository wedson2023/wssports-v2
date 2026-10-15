import { useCallback, useEffect, useRef, useState } from 'react';

import api from '../utils/api';
import { alerta_erro } from '../utils/alerts';

const intervalo_acompanhamento = 2000;

// ações do encerramento de aposta no bilhete (spec 010, contracts/api.md seção 2): pedido total ou
// parcial, acompanhamento do "Em análise" e encerramento automático; ao final, recarrega o bilhete
export default function useEncerramento(codigo, recarregar) {
    const [enviando, definir_enviando] = useState(false);
    // último pedido: { chave, modalidade, situacao, valor, creditar_em, motivo_recusa, segundos_restantes }
    const [pedido, definir_pedido] = useState(null);
    const acompanhamento = useRef(null);

    const base = `/area-cliente/apostas/${encodeURIComponent(codigo)}`;

    const parar_acompanhamento = () => {
        clearTimeout(acompanhamento.current);
        acompanhamento.current = null;
    };

    useEffect(() => parar_acompanhamento, []);

    // pedido Em análise (tempo de confirmação do ao vivo): consulta até sair dessa situação
    const acompanhar = useCallback((chave) => {
        parar_acompanhamento();

        acompanhamento.current = setTimeout(async () => {
            try {
                const { data } = await api.get(`${base}/encerramentos/${chave}`);
                definir_pedido(data.data);

                if (data.data.situacao === 'Em análise') acompanhar(chave);
                else recarregar();
            } catch {
                // sem conexão: tenta de novo no próximo intervalo
                acompanhar(chave);
            }
        }, intervalo_acompanhamento);
    }, [base, recarregar]);

    // devolve { ok } ou { oferta_alterada, mensagem } (409: a página mostra a oferta nova)
    const encerrar = useCallback(async ({ modalidade, valor, token_oferta, chave_idempotencia, aceitar_alteracoes }) => {
        if (enviando) return { ok: false };

        definir_enviando(true);

        try {
            const { data, status } = await api.post(`${base}/encerramentos`, {
                modalidade, valor, token_oferta, chave_idempotencia, aceitar_alteracoes,
            });

            definir_pedido(data.data);

            if (status === 202 || data.data.situacao === 'Em análise') acompanhar(data.data.chave);
            else recarregar();

            return { ok: true };
        } catch (erro) {
            if (erro.response?.status === 409) {
                recarregar();

                return { ok: false, oferta_alterada: true, mensagem: erro.response.data.message };
            }

            alerta_erro(erro);
            recarregar();

            return { ok: false };
        } finally {
            definir_enviando(false);
        }
    }, [enviando, base, acompanhar, recarregar]);

    const programar_automatico = useCallback(async (valor_alvo) => {
        definir_enviando(true);

        try {
            await api.put(`${base}/encerramento-automatico`, { valor_alvo });
            recarregar();

            return true;
        } catch (erro) {
            alerta_erro(erro);

            return false;
        } finally {
            definir_enviando(false);
        }
    }, [base, recarregar]);

    const cancelar_automatico = useCallback(async () => {
        definir_enviando(true);

        try {
            await api.delete(`${base}/encerramento-automatico`);
        } catch (erro) {
            if (erro.response?.status !== 404) alerta_erro(erro);
        } finally {
            definir_enviando(false);
            recarregar();
        }
    }, [base, recarregar]);

    return { enviando, pedido, limpar_pedido: () => definir_pedido(null), encerrar, programar_automatico, cancelar_automatico };
}
