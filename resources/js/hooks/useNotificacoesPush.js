import { useCallback, useEffect, useState } from 'react';

import api from '../utils/api';
import { alerta_erro } from '../utils/alerts';
import { chave_para_bytes, inscricao_atual, precisa_instalar, suporta_push } from '../utils/web_push';

// notificações push do aparelho (spec 010, R-15, FR-040 e FR-041): só para o cliente logado (ou o
// acesso assistido). situacao: carregando | indisponivel | instalar | bloqueada | desativada | ativa
export default function useNotificacoesPush(ativo) {
    const [situacao, definir_situacao] = useState('carregando');
    const [chave_publica, definir_chave_publica] = useState(null);
    const [preferencias, definir_preferencias] = useState(null);
    const [enviando, definir_enviando] = useState(false);

    // lê a configuração do servidor e o estado do aparelho
    const conferir = useCallback(async () => {
        if (!ativo) {
            definir_situacao('indisponivel');
            return;
        }

        try {
            const { data } = await api.get('/publico/notificacoes/configuracao');

            if (!data.data.habilitado) {
                definir_situacao('indisponivel');
                return;
            }

            definir_chave_publica(data.data.chave_publica);
        } catch {
            definir_situacao('indisponivel');
            return;
        }

        if (precisa_instalar()) {
            definir_situacao('instalar');
            return;
        }

        if (!suporta_push()) {
            definir_situacao('indisponivel');
            return;
        }

        if (Notification.permission === 'denied') {
            definir_situacao('bloqueada');
            return;
        }

        const inscricao = Notification.permission === 'granted' ? await inscricao_atual() : null;

        if (!inscricao) {
            definir_situacao('desativada');
            return;
        }

        try {
            const { data } = await api.get('/area-cliente/notificacoes/inscricao', { params: { endpoint: inscricao.endpoint } });
            definir_preferencias(data.data);
            definir_situacao('ativa');
        } catch {
            // aparelho inscrito no navegador, mas não no servidor (outro cliente ou removida)
            definir_situacao('desativada');
        }
    }, [ativo]);

    useEffect(() => {
        conferir();
    }, [conferir]);

    const gravar = async (inscricao, novas_preferencias = undefined) => {
        const json = inscricao.toJSON();
        const { data } = await api.put('/area-cliente/notificacoes/inscricao', {
            endpoint: json.endpoint,
            chaves: json.keys,
            codificacao: (window.PushManager?.supportedContentEncodings ?? ['aes128gcm'])[0],
            preferencias: novas_preferencias,
        });

        definir_preferencias(data.data);
        definir_situacao('ativa');
    };

    // só depois de um toque do cliente (FR-040): pede a permissão e inscreve o aparelho
    const ativar = useCallback(async () => {
        if (!chave_publica) return;

        definir_enviando(true);

        try {
            const permissao = await Notification.requestPermission();

            if (permissao !== 'granted') {
                definir_situacao(permissao === 'denied' ? 'bloqueada' : 'desativada');
                return;
            }

            const registro = await navigator.serviceWorker.ready;
            const inscricao = await registro.pushManager.getSubscription()
                ?? await registro.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: chave_para_bytes(chave_publica) });

            await gravar(inscricao);
        } catch (erro) {
            alerta_erro(erro);
        } finally {
            definir_enviando(false);
        }
    }, [chave_publica]);

    const salvar_preferencias = useCallback(async (novas) => {
        const inscricao = await inscricao_atual();

        if (!inscricao) {
            definir_situacao('desativada');
            return;
        }

        definir_enviando(true);

        try {
            await gravar(inscricao, novas);
        } catch (erro) {
            alerta_erro(erro);
        } finally {
            definir_enviando(false);
        }
    }, []);

    const desativar = useCallback(async () => {
        definir_enviando(true);

        try {
            const inscricao = await inscricao_atual();

            if (inscricao) {
                await api.delete('/area-cliente/notificacoes/inscricao', { params: { endpoint: inscricao.endpoint } });
                await inscricao.unsubscribe();
            }

            definir_preferencias(null);
            definir_situacao('desativada');
        } catch (erro) {
            alerta_erro(erro);
        } finally {
            definir_enviando(false);
        }
    }, []);

    return { situacao, preferencias, enviando, ativar, salvar_preferencias, desativar };
}
