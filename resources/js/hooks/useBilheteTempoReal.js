import { useCallback, useEffect, useRef, useState } from 'react';

import api from '../utils/api';
import { criar_conexao, estado_conexao } from '../utils/realtime';

// intervalos da atualização (R-02, FR-009), em milissegundos
const intervalo_minimo_busca = 2000;
const verificacao_conectado = 60000;
const consulta_ao_vivo = 10000;
const consulta_sem_ao_vivo = 60000;
const duracao_destaque_gol = 2500;

// campos do jogo que o evento confronto.atualizado traz e que mudam na tela na hora
const campos_jogo = ['periodo', 'minuto', 'placar_casa', 'placar_fora', 'primeiro_tempo', 'segundo_tempo', 'escanteios'];

const tem_jogo_ao_vivo = (bilhete) => (bilhete?.palpites ?? []).some((palpite) => palpite.estado === 'ao_vivo');

// bilhete em tempo real (spec 010, R-02): carrega pela API, assina os canais do Reverb e, sem
// conexão, consulta periodicamente; para tudo quando a aposta chega ao estado final
export default function useBilheteTempoReal(codigo, tempo_real) {
    const [bilhete, definir_bilhete] = useState(null);
    const [carregando, definir_carregando] = useState(true);
    const [erro, definir_erro] = useState(null); // null | 'nao_encontrado' | 'falha'
    const [conectado, definir_conectado] = useState(false);
    const [atualizado_em, definir_atualizado_em] = useState(null);
    // confrontos_id com gol recente, para o destaque passageiro do placar
    const [gols, definir_gols] = useState({});

    const echo = useRef(null);
    const pedido = useRef(null);
    const ultima_busca = useRef(0);
    const busca_agendada = useRef(null);
    const oferta_anterior = useRef(null);
    const canais_assinados = useRef(new Set());

    const buscar = useCallback(async () => {
        pedido.current?.abort();
        const controle = new AbortController();
        pedido.current = controle;
        ultima_busca.current = Date.now();

        try {
            const { data } = await api.get(`/publico/bilhetes/${encodeURIComponent(codigo)}`, {
                params: { oferta_anterior: oferta_anterior.current ?? undefined },
                signal: controle.signal,
            });

            oferta_anterior.current = data.data.encerramento?.oferta ?? oferta_anterior.current;
            definir_bilhete(data.data);
            definir_erro(null);
            definir_atualizado_em(Date.now());

            // quem pediu a busca (ex.: abrir a confirmação) recebe o bilhete atualizado
            return data.data;
        } catch (falha) {
            if (falha.code === 'ERR_CANCELED') return null;

            definir_erro(falha.response?.status === 404 ? 'nao_encontrado' : 'falha');

            return null;
        } finally {
            if (pedido.current === controle) definir_carregando(false);
        }
    }, [codigo]);

    // agrupa as buscas pedidas pelos eventos: no máximo uma a cada 2 segundos
    const agendar_busca = useCallback(() => {
        if (busca_agendada.current) return;

        const espera = Math.max(0, ultima_busca.current + intervalo_minimo_busca - Date.now());

        busca_agendada.current = setTimeout(() => {
            busca_agendada.current = null;
            buscar();
        }, espera);
    }, [buscar]);

    // placar, minuto e período mudam na hora; o resto (selos, cotações, oferta) vem da nova busca
    const aplicar_evento_jogo = useCallback((evento) => {
        definir_bilhete((atual) => {
            if (!atual) return atual;

            const palpites = atual.palpites.map((palpite) => {
                if (palpite.confrontos_id !== evento.confrontos_id || palpite.estado === 'encerrado') return palpite;

                const novos = Object.fromEntries(campos_jogo.filter((campo) => evento[campo] !== null && evento[campo] !== undefined).map((campo) => [campo, evento[campo]]));

                return { ...palpite, ...novos };
            });

            return { ...atual, palpites };
        });

        if (evento.gol) {
            definir_gols((atuais) => ({ ...atuais, [evento.confrontos_id]: Date.now() }));
            setTimeout(() => definir_gols((atuais) => {
                const { [evento.confrontos_id]: _removido, ...restantes } = atuais;
                return restantes;
            }), duracao_destaque_gol);
        }

        definir_atualizado_em(Date.now());
        agendar_busca();
    }, [agendar_busca]);

    // primeira carga
    useEffect(() => {
        buscar();

        return () => {
            pedido.current?.abort();
            clearTimeout(busca_agendada.current);
            busca_agendada.current = null;
        };
    }, [buscar]);

    // conexão contínua: criada uma vez por página
    useEffect(() => {
        const conexao = criar_conexao(tempo_real);

        if (!conexao) return undefined;

        echo.current = conexao;
        const parar = estado_conexao(conexao, (ligada) => {
            definir_conectado((antes) => {
                // ao voltar a conexão, busca uma vez para cobrir o que foi perdido
                if (ligada && !antes) agendar_busca();
                return ligada;
            });
        });

        return () => {
            parar();
            conexao.disconnect();
            echo.current = null;
            canais_assinados.current = new Set();
        };
        // a conexão é criada só uma vez por página
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // assina os canais do bilhete e sai dos que não valem mais (aposta final ou jogo decidido)
    const lista_canais = bilhete ? [bilhete.canais.aposta, ...bilhete.canais.confrontos].filter(Boolean) : [];
    const chave_canais = lista_canais.join('|');

    useEffect(() => {
        const conexao = echo.current;

        if (!conexao) return;

        const desejados = new Set(chave_canais ? chave_canais.split('|') : []);

        canais_assinados.current.forEach((canal) => {
            if (!desejados.has(canal)) {
                conexao.leave(canal);
                canais_assinados.current.delete(canal);
            }
        });

        desejados.forEach((canal) => {
            if (canais_assinados.current.has(canal)) return;

            const assinatura = conexao.channel(canal);

            if (canal.startsWith('confrontos.')) assinatura.listen('.confronto.atualizado', aplicar_evento_jogo);
            else assinatura.listen('.aposta.atualizada', () => buscar());

            canais_assinados.current.add(canal);
        });
    }, [chave_canais, aplicar_evento_jogo, buscar]);

    // verificação de segurança (conectado) ou consulta periódica (sem conexão); nada no estado final
    const final = Boolean(bilhete?.final);
    const ao_vivo = tem_jogo_ao_vivo(bilhete);

    useEffect(() => {
        if (final || erro === 'nao_encontrado') return undefined;

        const intervalo = conectado ? verificacao_conectado : (ao_vivo ? consulta_ao_vivo : consulta_sem_ao_vivo);
        const timer = setInterval(buscar, intervalo);

        return () => clearInterval(timer);
    }, [final, conectado, ao_vivo, erro, buscar]);

    return {
        bilhete,
        carregando,
        erro,
        // sem Reverb configurado a página fica só na consulta periódica
        conexao: final ? 'final' : (conectado ? 'ao_vivo' : 'reconectando'),
        atualizado_em,
        gols,
        recarregar: buscar,
    };
}
