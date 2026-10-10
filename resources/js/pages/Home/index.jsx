import { useCallback, useContext, useEffect, useRef, useState } from 'react';
import { Head, router, usePage, usePoll } from '@inertiajs/react';

import AuthModal from '../../components/AuthModal';
import BannerCarousel from '../../components/BannerCarousel';
import BetSlip from '../../components/BetSlip';
import BetSlipSummary from '../../components/BetSlipSummary';
import DateTabs from '../../components/DateTabs';
import Footer from '../../components/Footer';
import Header from '../../components/Header';
import MatchDetailsModal from '../../components/MatchDetailsModal';
import MatchList from '../../components/MatchList';
import SearchBar from '../../components/SearchBar';
import SideMenu from '../../components/SideMenu';
import SportsBar, { esportes } from '../../components/SportsBar';
import SuccessModal from '../../components/SuccessModal';
import ThemeToggle from '../../components/ThemeToggle';
import TicketModal from '../../components/TicketModal';
import WhatsAppButton from '../../components/WhatsAppButton';
import useCupom from '../../hooks/useCupom';
import { ModoContext } from '../../hooks/useModo';
import useSessao from '../../hooks/useSessao';
import useVariacaoCotacoes from '../../hooks/useVariacaoCotacoes';
import api from '../../utils/api';
import { alerta_atencao, alerta_erro, alerta_sucesso, confirmar } from '../../utils/alerts';
import { para_centavos } from '../../utils/money';
import { Content } from './styles';

// atualização do ao vivo (FR-033)
const intervalo_ao_vivo_ms = 7000;

// esportes sem ação nesta spec (FR-011)
const esportes_sem_acao = ['CASSINO', 'ESPECIAL'];

// colunas do cabeçalho com o botão dia/noite antes de Criar Conta / Entrar (FR-053)
const colunas_cabecalho = { desktop: '40px 100px 100px', mobile: '40px 100px 100px' };

// compara nomes de esportes como o banco: sem diferenciar maiúsculas nem acentos
const normalizar = (nome) => nome.normalize('NFD').replace(/[̀-ͯ]/g, '').toUpperCase();

// junta a página nova à lista, unindo o campeonato dividido entre duas páginas (R-13)
const juntar_paginas = (atuais, novos) => {
    const juntos = [...atuais];

    novos.forEach((campeonato) => {
        const ultimo = juntos[juntos.length - 1];

        if (ultimo?.id === campeonato.id) {
            juntos[juntos.length - 1] = { ...ultimo, confrontos: [...ultimo.confrontos, ...campeonato.confrontos] };
        } else {
            juntos.push(campeonato);
        }
    });

    return juntos;
};

// filtros da URL sem os valores padrão nem vazios
const consulta_da_url = ({ tipo, esporte, dia, busca, campeonato }) => Object.fromEntries(Object.entries({
    tipo: tipo === 'ao_vivo' ? tipo : null,
    esporte: esporte && esporte !== 'FUTEBOL' ? esporte : null,
    dia: dia && dia !== 'hoje' ? dia : null,
    busca: busca || null,
    campeonato: campeonato || null,
}).filter(([, valor]) => valor !== null));

// tela principal de apostas da área "/" (spec 005)
export default function Home({ filtros, listagem, configuracoes, banners, aviso, saldo = null, apostador = null }) {
    const { indicadores, nome_sistema, tema } = usePage().props;
    const { modo, alternar_modo } = useContext(ModoContext);
    const { cupom, enviando, alternar_palpite, enviar_cupom, carregar_validacao } = useCupom();
    const { sessao, sair, expirar, definir_apostador } = useSessao();

    const [campeonatos, definir_campeonatos] = useState(listagem.campeonatos);
    const [carregando, definir_carregando] = useState(false);
    const [menu_aberto, definir_menu_aberto] = useState(false);
    const [cupom_aberto, definir_cupom_aberto] = useState(false);
    const [jogo_detalhe, definir_jogo_detalhe] = useState(null);
    const [comprovante, definir_comprovante] = useState(null);
    const [bilhete, definir_bilhete] = useState(null);
    // link de indicação (?user=código) abre o cadastro com o código de afiliado, como no antigo
    const [codigo_indicacao] = useState(() => new URLSearchParams(window.location.search).get('user') ?? '');
    const [aba_acesso, definir_aba_acesso] = useState(() => (codigo_indicacao ? 'cadastrar' : null));

    const lista = useRef(null);
    const pedindo_pagina = useRef(false);
    const enviando_antes = useRef(enviando);

    const ao_vivo = filtros.tipo === 'ao_vivo';
    const variacoes = useVariacaoCotacoes(campeonatos, ao_vivo);

    // página 1 substitui a lista; as seguintes somam (rolagem infinita)
    useEffect(() => {
        pedindo_pagina.current = false;
        definir_campeonatos((atuais) => (listagem.meta.pagina_atual <= 1 ? listagem.campeonatos : juntar_paginas(atuais, listagem.campeonatos)));
    }, [listagem]);

    useEffect(() => {
        if (aviso) alerta_atencao(aviso);
    }, [aviso]);

    // o servidor recusou o token do cliente ou do vendedor (vencido ou bloqueado): encerra a sessão
    useEffect(() => {
        if (listagem.token_recusado && sessao?.tipo === 'cliente') {
            expirar();
            alerta_atencao('Sua sessão expirou. Entre novamente para apostar com seu saldo.');
        }

        if (listagem.token_recusado && sessao?.tipo === 'usuario') {
            expirar();
            alerta_atencao('Sua sessão expirou. Entre novamente para apostar.');
        }
    }, [listagem.token_recusado, sessao?.tipo, expirar]);

    // usuário do painel: a tela diz se ele aposta como vendedor ou, sendo gestor, como visitante
    useEffect(() => definir_apostador(apostador), [apostador, definir_apostador]);

    // ao vivo: atualiza a cada 7s só com a aba aberta e pausa durante o envio do código (FR-050a)
    const poll = useRef(null);
    // start/stop do usePoll mudam a cada render: ficam numa ref para não reiniciar o timer
    poll.current = usePoll(intervalo_ao_vivo_ms, { only: ['listagem', 'aviso'] }, { autoStart: false });

    useEffect(() => {
        const terminou_envio = enviando_antes.current && !enviando;
        enviando_antes.current = enviando;

        if (!ao_vivo || enviando) {
            poll.current.stop();
            return;
        }

        if (terminou_envio) router.reload({ only: ['listagem', 'aviso'] });

        poll.current.start();
    }, [ao_vivo, enviando]);

    const aplicar_filtros = (novos) => {
        const proximos = { ...filtros, ...novos };
        lista.current?.scrollTo({ top: 0 });

        // atualização do ao vivo em andamento chegaria depois e trocaria a lista e a URL pelas do ao
        // vivo (palpites novos sairiam como ao vivo): para o timer e cancela a que estiver a caminho
        poll.current.stop();
        router.cancelAll({ sync: false, prefetch: false });

        router.get('/', consulta_da_url(proximos), {
            only: ['listagem', 'filtros', 'aviso'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => definir_carregando(true),
            onFinish: () => {
                definir_carregando(false);
                // continuando no ao vivo, o efeito acima não roda de novo: retoma o timer aqui
                if (proximos.tipo === 'ao_vivo') poll.current.start();
            },
        });
    };

    const carregar_proxima_pagina = () => {
        const { pagina_atual, ultima_pagina } = listagem.meta;

        if (pedindo_pagina.current || carregando || pagina_atual >= ultima_pagina) return;

        pedindo_pagina.current = true;

        router.get('/', { ...consulta_da_url(filtros), pagina: pagina_atual + 1 }, {
            only: ['listagem'],
            preserveState: true,
            preserveScroll: true,
            preserveUrl: true,
            onFinish: () => { pedindo_pagina.current = false; },
        });
    };

    // esportes visíveis ao visitante (FR-010); sem outros esportes, a barra some
    const permitidos = (configuracoes.esportes_permitidos ?? []).map(normalizar);
    const visiveis = esportes.map(({ chave }) => chave).filter((chave) => ({
        CASSINO: indicadores.cassino,
        FUTEBOL: true,
        'AO VIVO': configuracoes.ao_vivo_habilitado,
        ESPECIAL: configuracoes.apostar_outros_esportes,
    })[chave] ?? (configuracoes.apostar_outros_esportes && permitidos.includes(normalizar(chave))));
    const barra_visivel = configuracoes.apostar_outros_esportes;

    const abrir_ao_vivo = () => aplicar_filtros({ tipo: 'ao_vivo', esporte: 'FUTEBOL', dia: null, busca: null, campeonato: null });

    const escolher_esporte = (chave) => {
        if (esportes_sem_acao.includes(chave)) return;

        if (chave === 'AO VIVO') {
            abrir_ao_vivo();
            return;
        }

        aplicar_filtros({ tipo: 'pre_jogo', esporte: chave, dia: 'hoje', busca: null, campeonato: null });
    };

    const selecao_do_jogo = (confronto) => cupom.palpites
        .find((palpite) => palpite.tipo === listagem.tipo && palpite.confronto_id === confronto.id)?.codigo_cotacao ?? null;

    const escolher_cotacao = (confronto, campeonato, { codigo, mercado, cotacao }) => alternar_palpite({
        tipo: listagem.tipo,
        confronto_id: confronto.id,
        codigo_cotacao: codigo,
        mercado,
        cotacao: Number(cotacao).toFixed(2),
        time_casa: confronto.time_casa,
        time_fora: confronto.time_fora,
        campeonato: campeonato.nome,
        data_inicio: confronto.data_inicio,
    });

    const abrir_detalhes = (confronto) => definir_jogo_detalhe({ tipo: listagem.tipo, confronto_id: confronto.id });
    const fechar_detalhes = useCallback(() => definir_jogo_detalhe(null), []);
    const fechar_acesso = useCallback(() => definir_aba_acesso(null), []);

    const sair_da_conta = async () => {
        if (!(await confirmar('Deseja sair da sua conta?', tema.temas))) return;

        await sair();
        alerta_sucesso('Você saiu da sua conta.');
    };

    // busca por time: no ao vivo não há busca, como no sistema antigo (FR-027)
    const buscar_time = (texto) => {
        if (!ao_vivo) aplicar_filtros({ busca: texto, campeonato: null });
    };

    const limpar_busca = () => {
        if (filtros.busca) aplicar_filtros({ busca: null });
    };

    // vendedor abre o código pendente do visitante: em vez do bilhete, a simulação vai para o cupom
    // (cotações e regras do vendedor) e o "Finalizar" vira "Validar", como no sistema antigo
    const abrir_validacao = async (codigo) => {
        const { data } = await api.get(`/apostas/pendentes/${encodeURIComponent(codigo)}`);
        const simulacao = data.data;
        const disponiveis = simulacao.palpites.filter((palpite) => palpite.disponivel);
        const indisponiveis = simulacao.palpites.filter((palpite) => !palpite.disponivel);

        if (!disponiveis.length) {
            alerta_atencao(`Nenhum palpite deste código pode ser validado. ${indisponiveis[0]?.motivo ?? ''}`.trim());
            return;
        }

        if (cupom.palpites.length && !(await confirmar('Substituir os palpites do cupom pelos do código?', tema.temas))) return;

        carregar_validacao(simulacao.codigo, {
            nome: simulacao.nome ?? '',
            valor_centavos: para_centavos(simulacao.valor),
            palpites: disponiveis.map((palpite) => ({
                tipo: 'pre_jogo',
                confronto_id: palpite.confrontos_id,
                codigo_cotacao: palpite.codigo_cotacao,
                ...(palpite.confrontos_jogadores_id ? { jogador_id: palpite.confrontos_jogadores_id } : {}),
                mercado: palpite.jogador ? `${palpite.jogador} (${palpite.mercado})` : palpite.mercado,
                cotacao: Number(palpite.cotacao).toFixed(2),
                time_casa: palpite.time_casa,
                time_fora: palpite.time_fora,
                campeonato: null,
                data_inicio: palpite.data_inicio,
            })),
        });
        definir_cupom_aberto(true);

        if (indisponiveis.length) {
            alerta_atencao(`Palpites fora do cupom: ${indisponiveis.map((palpite) => `${palpite.confronto ?? 'jogo'} (${palpite.motivo})`).join('; ')}.`);
        }
    };

    // bilhete pelo código (FR-044); código pendente com vendedor logado vai para a validação
    const buscar_codigo = async (codigo) => {
        try {
            const { data } = await api.get(`/publico/apostas/${encodeURIComponent(codigo.replace(/\s/g, '').toUpperCase())}`);

            if (data.data.situacao === 'Pendente' && sessao?.tipo === 'usuario' && sessao.apostador === 'vendedor') {
                await abrir_validacao(data.data.codigo);
                return;
            }

            definir_bilhete(data.data);
        } catch (erro) {
            if (erro.response?.status === 404) alerta_atencao(erro.response.data?.message ?? 'Bilhete não encontrado.');
            else alerta_erro(erro);
        }
    };

    // código da aposta do visitante (FR-041 a FR-043), aposta do cliente ou do vendedor
    const finalizar = async () => {
        const recebido = await enviar_cupom(cupom, tema.temas, sessao, expirar);

        if (recebido) {
            definir_cupom_aberto(false);
            // quem apostou define a tela de sucesso (o vendedor imprime e envia o bilhete)
            definir_comprovante({ ...recebido, apostador: sessao?.tipo === 'usuario' ? sessao.apostador : sessao?.tipo ?? 'visitante' });

            // aposta do cliente sai do saldo: atualiza o valor do cabeçalho
            if (sessao?.tipo === 'cliente') router.reload({ only: ['saldo'] });
        }
    };

    const ultima_pagina_carregada = listagem.meta.pagina_atual >= listagem.meta.ultima_pagina;

    return (
        <>
            <Head title={nome_sistema} />
            <Header
                ao_abrir_menu={() => definir_menu_aberto(true)}
                colunas={colunas_cabecalho}
                extra={<ThemeToggle modo={modo} ao_alternar={alternar_modo} />}
                sessao={sessao}
                saldo={saldo}
                ao_entrar={() => definir_aba_acesso('entrar')}
                ao_cadastrar={() => definir_aba_acesso('cadastrar')}
                ao_sair={sair_da_conta}
            />
            {barra_visivel && <SportsBar visiveis={visiveis} esporte_ativo={ao_vivo ? 'AO VIVO' : filtros.esporte} ao_escolher={escolher_esporte} />}
            <BetSlipSummary ao_conferir={() => definir_cupom_aberto(true)} />
            <Content $com_barra={barra_visivel}>
                <SideMenu
                    paises={listagem.paises}
                    aberto={menu_aberto}
                    ao_fechar={() => definir_menu_aberto(false)}
                    ao_escolher_campeonato={(campeonato) => aplicar_filtros({ campeonato: campeonato.id })}
                    ao_abrir_ao_vivo={!barra_visivel && configuracoes.ao_vivo_habilitado ? abrir_ao_vivo : null}
                />
                <MatchList
                    ref={lista}
                    carregando={carregando}
                    tem_mais={!ultima_pagina_carregada}
                    topo={(
                        <>
                            <BannerCarousel banners={banners} />
                            <SearchBar ao_buscar_time={buscar_time} ao_limpar_busca={limpar_busca} ao_buscar_codigo={buscar_codigo} />
                            <DateTabs periodo={configuracoes.periodo_jogos} ao_escolher={(dia) => aplicar_filtros({ dia, busca: null, campeonato: null })} />
                        </>
                    )}
                    rodape={ultima_pagina_carregada ? <Footer /> : null}
                    campeonatos={campeonatos}
                    ao_vivo={ao_vivo}
                    selecao_do_jogo={selecao_do_jogo}
                    variacoes={variacoes}
                    ao_escolher={escolher_cotacao}
                    ao_abrir_detalhes={abrir_detalhes}
                    ao_chegar_perto_do_fim={carregar_proxima_pagina}
                />
                <BetSlip
                    aberto={cupom_aberto}
                    ao_fechar={() => definir_cupom_aberto(false)}
                    ao_finalizar={finalizar}
                    ao_abrir_detalhes={(palpite) => definir_jogo_detalhe({ tipo: palpite.tipo, confronto_id: palpite.confronto_id })}
                />
            </Content>
            <WhatsAppButton />
            <MatchDetailsModal jogo={jogo_detalhe} ao_fechar={fechar_detalhes} />
            <SuccessModal comprovante={comprovante} ao_fechar={() => definir_comprovante(null)} />
            <TicketModal comprovante={bilhete} ao_fechar={() => definir_bilhete(null)} />
            <AuthModal aba={aba_acesso} codigo_afiliado={codigo_indicacao} ao_trocar_aba={definir_aba_acesso} ao_fechar={fechar_acesso} />
        </>
    );
}
