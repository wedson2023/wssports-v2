import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { useTheme } from 'styled-components';
import Swal from 'sweetalert2';

import ChartCanvas from '../../components/ChartCanvas';
import CombinationModal from '../../components/CombinationModal';
import DashboardSection from '../../components/DashboardSection';
import PanelShell from '../../components/PanelShell';
import RankingChart from '../../components/RankingChart';
import RiskCombinations from '../../components/RiskCombinations';
import SingleGameBets from '../../components/SingleGameBets';
import Spinner from '../../components/Spinner';
import StatCard from '../../components/StatCard';
import TicketModal from '../../components/TicketModal';
import usePainel from '../../hooks/usePainel';
import useSecaoDashboard from '../../hooks/useSecaoDashboard';
import useSessao from '../../hooks/useSessao';
import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import { cores_graficos, cores_rosca, formatar_valor, opcoes_padrao, valor_grafico } from '../../utils/charts';
import { Cards, Charts, Grid, Loading, SoundIcon, Summary } from './styles';

// intervalo da consulta dos avisos de combinação registrados pelo agendador (FR-047)
const INTERVALO_AVISOS = 60 * 1000;

// tempo em que a linha da combinação avisada fica destacada
const DESTAQUE_AVISO = 60 * 1000;

// lê e grava preferências do aparelho; sem localStorage (aba privada), segue só em memória
const ler_local = (chave) => {
    try {
        return window.localStorage.getItem(chave);
    } catch {
        return null;
    }
};

const gravar_local = (chave, valor) => {
    try {
        window.localStorage.setItem(chave, valor);
    } catch {
        // sem armazenamento: vale só nesta aba
    }
};

// som curto do aviso, gerado no navegador (sem arquivo de áudio)
const tocar_aviso = () => {
    try {
        const contexto = new (window.AudioContext || window.webkitAudioContext)();
        const oscilador = contexto.createOscillator();
        const volume = contexto.createGain();

        oscilador.frequency.value = 880;
        volume.gain.setValueAtTime(0.2, contexto.currentTime);
        volume.gain.exponentialRampToValueAtTime(0.001, contexto.currentTime + 0.6);
        oscilador.connect(volume).connect(contexto.destination);
        oscilador.start();
        oscilador.stop(contexto.currentTime + 0.6);
    } catch {
        // navegador sem áudio: só o balão
    }
};

// cartões do Apurado na ordem e com os textos explicativos do sistema antigo
const cartoes_apurado = [
    { chave: 'entradas', rotulo: 'Entradas', icone: 'bar_chart', titulo: 'ENTRADAS, também considerada como valor bruto todo o valor que entra na banca nesse dia.' },
    { chave: 'depositos_online', rotulo: 'Depósitos online', icone: 'credit_card', titulo: 'DEPÓSITOS ONLINE, depósitos dos clientes confirmados nesse dia (já somados às entradas).' },
    { chave: 'em_aberto', rotulo: 'Entradas em Aberto', icone: 'access_time', titulo: 'ENTRADAS EM ABERTO, é o valor total das apostas que estão em aberto nesse dia.' },
    { chave: 'premiadas', rotulo: 'Entradas Premiadas', icone: 'attach_money', titulo: 'ENTRADAS PREMIADAS, é o valor total em valor apostado nas apostas premiadas nesse dia.' },
    { chave: 'perdidas', rotulo: 'Entradas Perdidas', icone: 'clear', titulo: 'ENTRADAS PERDIDAS, é o valor total em valor apostado nas apostas perdidas nesse dia.' },
    { chave: 'saidas', rotulo: 'Saídas', icone: 'emoji_events', titulo: 'SAÍDAS, é o valor total de premiações pagas nesse dia.' },
    { chave: 'comissao_vendedor', rotulo: 'Comissão Vendedor', icone: 'monetization_on', titulo: 'COMISSÃO VENDEDOR, é o valor total de comissões que tem direito o vendedor nesse dia.' },
    { chave: 'lancamentos', rotulo: 'Lançamentos', icone: 'redo', titulo: 'LANÇAMENTOS, é o valor total de suprimento que repôs no caixa nesse dia.' },
    { chave: 'retiradas', rotulo: 'Retiradas', icone: 'undo', titulo: 'RETIRADAS, é o valor total que foi retirado do caixa nesse dia.' },
    { chave: 'comissao_gerente', rotulo: 'Comissão gerente', icone: 'pie_chart', titulo: 'COMISSÃO DO GERENTE, é o valor abatido de todas as gerências em cima do saldo líquido.' },
];

// limites do vendedor no Apurado (só para ele)
const cartoes_limites = [
    { chave: 'geral', rotulo: 'Limite Geral', titulo: 'LIMITE GERAL, limite em apostas realizadas.' },
    { chave: 'duplo', rotulo: 'Limite Duplo', titulo: 'LIMITE DUPLO, limite em apostas de dois jogos.' },
    { chave: 'simples', rotulo: 'Limite Simples', titulo: 'LIMITE SIMPLES, limite em apostas em apenas um jogo.' },
];

// situação dos jogos e grupos de apostas, com os ícones do antigo
const situacoes_jogos = [
    { chave: 'aguardando', rotulo: 'Aguardando', icone: 'access_time' },
    { chave: 'encerrados', rotulo: 'Encerrados', icone: 'watch_later' },
    { chave: 'adiados', rotulo: 'Adiados', icone: 'refresh' },
    { chave: 'cancelados', rotulo: 'Cancelados', icone: 'clear' },
];

// rótulo dos cartões (como no antigo) e o curto da legenda do gráfico
const grupos_apostas = [
    { chave: 'abertas', rotulo: 'Apostas em Aberto', curto: 'Em aberto', icone: 'access_time' },
    { chave: 'premiadas', rotulo: 'Apostas Premiadas', curto: 'Premiadas', icone: 'emoji_events' },
    { chave: 'perdidas', rotulo: 'Apostas Perdidas', curto: 'Perdidas', icone: 'clear' },
    { chave: 'canceladas', rotulo: 'Apostas Canceladas', curto: 'Canceladas', icone: 'delete' },
];

const cartoes_caixa_online = [
    { chave: 'entradas', rotulo: 'Entradas', icone: 'bar_chart' },
    { chave: 'em_aberto', rotulo: 'Em aberto', icone: 'access_time' },
    { chave: 'premiadas', rotulo: 'Premiadas', icone: 'attach_money' },
    { chave: 'perdidas', rotulo: 'Perdidas', icone: 'clear' },
    { chave: 'saidas', rotulo: 'Saídas', icone: 'emoji_events' },
    { chave: 'encerramentos', rotulo: 'Encerramentos', icone: 'price_check' },
    { chave: 'depositos', rotulo: 'Depósitos', icone: 'credit_card' },
    { chave: 'saques_pagos', rotulo: 'Saques pagos', icone: 'payments' },
];

// Dashboard do painel (spec 014): primeira tela do painel para Admin, Supervisor, Gerente e
// Vendedor; cada seção carrega sozinha, se atualiza a cada minuto e só aparece com a permissão dela
export default function Dashboard() {
    const { nome_sistema, tema: tema_servidor } = usePage().props;
    const tema = useTheme();
    const { sessao } = useSessao();
    const { usuario, carregando } = usePainel();
    const tem = (secao) => Boolean(usuario?.secoes.includes(secao));

    const apurado = useSecaoDashboard('apurado', { habilitada: tem('apurado') });
    const situacao = useSecaoDashboard('situacao-jogos', { habilitada: tem('situacao_jogos') });
    const apostas = useSecaoDashboard('apostas', { habilitada: tem('apostas') });
    const combinacoes = useSecaoDashboard('combinacoes-risco', { habilitada: tem('combinacao_risco'), ignorar_cache: true });
    const um_jogo = useSecaoDashboard('apostas-um-jogo', { habilitada: tem('apostas_um_jogo') });
    const vendedores = useSecaoDashboard('vendedores', { habilitada: tem('vendedores') });
    const jogos = useSecaoDashboard('jogos-mais-apostados', { habilitada: tem('jogos_mais_apostados') });
    const opcoes = useSecaoDashboard('opcoes-mais-apostadas', { habilitada: tem('opcoes_mais_apostadas') });
    const caixa = useSecaoDashboard('caixa-online', { habilitada: tem('caixa_online') });

    const [bilhete, definir_bilhete] = useState(null);
    const [combinacao_aberta, definir_combinacao_aberta] = useState(null);
    const [chaves_novas, definir_chaves_novas] = useState([]);
    const [som_ligado, definir_som_ligado] = useState(() => ler_local('dashboard_som_avisos') !== 'nao');
    const som = useRef(som_ligado);
    som.current = som_ligado;

    // bilhete pelo código, o mesmo da tela principal (GET /publico/apostas/{codigo})
    const ver_bilhete = useCallback(async (codigo) => {
        try {
            const { data } = await api.get(`/publico/apostas/${encodeURIComponent(codigo)}`);
            definir_bilhete(data.data);
        } catch (erro) {
            alerta_erro(erro);
        }
    }, []);

    const alternar_som = () => {
        const ligado = !som_ligado;
        definir_som_ligado(ligado);
        gravar_local('dashboard_som_avisos', ligado ? 'sim' : 'nao');
    };

    // avisos de combinação nova: só os registrados pelo agendador depois do último já visto; a
    // primeira consulta só guarda o marcador, e recarregar ou atualizar nunca gera aviso (FR-047)
    const pode_ver_combinacoes = tem('combinacao_risco');
    const atualizar_combinacoes = useRef(combinacoes.atualizar);
    atualizar_combinacoes.current = combinacoes.atualizar;

    useEffect(() => {
        if (!pode_ver_combinacoes || !sessao?.token) return undefined;

        const chave_local = `dashboard_ultimo_aviso_combinacao:${usuario.id}`;
        let ultimo_visto = ler_local(chave_local);
        let ativo = true;

        const consultar = async () => {
            if (document.visibilityState !== 'visible') return;

            try {
                const { data } = await api.get('/dashboard/combinacoes-risco/avisos', {
                    params: ultimo_visto !== null ? { depois_de: ultimo_visto } : {},
                    headers: { Authorization: `Bearer ${sessao.token}` },
                });

                if (!ativo) return;

                ultimo_visto = String(data.ultimo_id);
                gravar_local(chave_local, ultimo_visto);

                if (!data.data.length) return;

                const chaves = data.data.map((aviso) => aviso.chave);
                definir_chaves_novas((atuais) => [...new Set([...atuais, ...chaves])]);
                window.setTimeout(() => definir_chaves_novas((atuais) => atuais.filter((chave) => !chaves.includes(chave))), DESTAQUE_AVISO);
                atualizar_combinacoes.current();

                if (som.current) tocar_aviso();

                data.data.forEach((aviso) => Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'warning',
                    title: 'Nova combinação de risco',
                    text: `${aviso.jogos.join(' | ')} · ${aviso.quantidade_bilhetes} bilhetes · prêmio ${formatar_valor(aviso.premio)}`,
                    showConfirmButton: false,
                    showCloseButton: true,
                    timer: 15000,
                }));
            } catch {
                // sem conexão: tenta de novo na próxima consulta
            }
        };

        consultar();
        const intervalo = window.setInterval(consultar, INTERVALO_AVISOS);

        return () => {
            ativo = false;
            window.clearInterval(intervalo);
        };
    }, [pode_ver_combinacoes, usuario?.id, sessao?.token]);

    // dados dos gráficos (memorizados para o gráfico só atualizar quando os dados mudam)
    const cores = cores_graficos(tema);

    const grafico_apurado = useMemo(() => apurado.dados && ({
        labels: ['Entradas', 'Saídas', 'Comissões', 'Saldo'],
        datasets: [{
            data: [
                valor_grafico(apurado.dados.entradas),
                valor_grafico(apurado.dados.saidas),
                valor_grafico(apurado.dados.comissao_vendedor) + valor_grafico(apurado.dados.comissao_gerente ?? '0'),
                valor_grafico(apurado.dados.saldo_liquido),
            ],
            backgroundColor: [cores.entradas, cores.saidas, cores.comissoes, cores.principal],
            borderRadius: 3,
        }],
    }), [apurado.dados, tema]);

    const grafico_situacao = useMemo(() => situacao.dados && ({
        labels: situacoes_jogos.map((item) => item.rotulo),
        datasets: [{ data: situacoes_jogos.map((item) => situacao.dados[item.chave]), backgroundColor: cores_rosca(tema) }],
    }), [situacao.dados, tema]);

    const grafico_apostas = useMemo(() => apostas.dados && ({
        labels: grupos_apostas.map((item) => item.curto),
        datasets: [{ data: grupos_apostas.map((item) => apostas.dados[item.chave].quantidade), backgroundColor: cores_rosca(tema) }],
    }), [apostas.dados, tema]);

    const grafico_caixa = useMemo(() => caixa.dados && ({
        labels: ['Entradas', 'Saídas', 'Cash out', 'Saldo'],
        datasets: [{
            data: [caixa.dados.entradas, caixa.dados.saidas, caixa.dados.encerramentos, caixa.dados.saldo_liquido].map(valor_grafico),
            backgroundColor: [cores.entradas, cores.saidas, cores.comissoes, cores.principal],
            borderRadius: 3,
        }],
    }), [caixa.dados, tema]);

    const grafico_fluxo = useMemo(() => caixa.dados && ({
        labels: ['Depósitos', 'Saques'],
        datasets: [{
            data: [valor_grafico(caixa.dados.depositos), valor_grafico(caixa.dados.saques_pagos)],
            backgroundColor: [cores.entradas, cores.saidas],
            borderRadius: 3,
        }],
    }), [caixa.dados, tema]);

    const opcoes_dinheiro = useMemo(() => opcoes_padrao('bar', tema, { dinheiro: true }), [tema]);
    const opcoes_rosca = useMemo(() => opcoes_padrao('doughnut', tema), [tema]);

    // seção visível: liberada para o usuário e sem 403 (permissão retirada com a tela aberta)
    const mostrar = (secao, estado) => tem(secao) && !estado.sem_permissao;

    // propriedades comuns da moldura de cada seção; com dados na tela, um erro de atualização não
    // apaga o que já está sendo mostrado
    const moldura = (estado, vazio) => ({
        carregando: estado.carregando,
        atualizando: estado.atualizando,
        erro: estado.dados ? null : estado.erro,
        vazio,
        ao_atualizar: estado.atualizar,
    });

    if (carregando) {
        return (
            <>
                <Head title={nome_sistema} />
                <Loading><Spinner tamanho={40} cor={tema_servidor.temas} /></Loading>
            </>
        );
    }

    return (
        <>
            <Head title={nome_sistema} />
            <PanelShell usuario={usuario} titulo="Dashboard">
                <Grid>
                    {mostrar('apurado', apurado) && (
                        <DashboardSection titulo="Apurado do dia" largura_total {...moldura(apurado, false)}>
                            {apurado.dados && (
                                <Summary>
                                    <Cards>
                                        {apurado.dados.limites && cartoes_limites.map((cartao) => (
                                            <StatCard
                                                key={cartao.chave}
                                                rotulo={cartao.rotulo}
                                                titulo={cartao.titulo}
                                                valor={apurado.dados.limites[cartao.chave]}
                                                cadeado={valor_grafico(apurado.dados.limites[cartao.chave]) > 0 ? 'aberto' : 'fechado'}
                                            />
                                        ))}
                                        {cartoes_apurado.filter((cartao) => apurado.dados[cartao.chave] !== null).map((cartao) => (
                                            <StatCard
                                                key={cartao.chave}
                                                icone={cartao.icone}
                                                rotulo={cartao.rotulo}
                                                titulo={cartao.titulo}
                                                valor={apurado.dados[cartao.chave]}
                                                em_breve={apurado.dados.provisorios.includes(cartao.chave)}
                                            />
                                        ))}
                                        <StatCard
                                            icone="pie_chart"
                                            rotulo="Saldo Líquido"
                                            titulo="SALDO LÍQUIDO, é o valor bruto/total da banca menos comissões, premiações, retiradas, mais lançamentos nesse dia."
                                            valor={apurado.dados.saldo_liquido}
                                            destaque
                                        />
                                    </Cards>
                                    <ChartCanvas tipo="bar" dados={grafico_apurado} opcoes={opcoes_dinheiro} />
                                </Summary>
                            )}
                        </DashboardSection>
                    )}

                    {mostrar('situacao_jogos', situacao) && (
                        <DashboardSection titulo="Situação dos jogos" esqueleto="grafico" {...moldura(situacao, situacao.dados?.total === 0)}>
                            {situacao.dados && (
                                <Charts>
                                    <ChartCanvas
                                        tipo="doughnut"
                                        dados={grafico_situacao}
                                        opcoes={opcoes_rosca}
                                        legenda
                                        centro={{ valor: situacao.dados.total, rotulo: situacao.dados.total === 1 ? 'jogo' : 'jogos' }}
                                    />
                                    <Cards $colunas={1}>
                                        <StatCard icone="bar_chart" rotulo="Total" valor={situacao.dados.total} dinheiro={false} />
                                        {situacoes_jogos.map((item) => (
                                            <StatCard key={item.chave} icone={item.icone} rotulo={item.rotulo} valor={situacao.dados[item.chave]} dinheiro={false} />
                                        ))}
                                    </Cards>
                                </Charts>
                            )}
                        </DashboardSection>
                    )}

                    {mostrar('apostas', apostas) && (
                        <DashboardSection
                            titulo="Apostas"
                            esqueleto="grafico"
                            {...moldura(apostas, Boolean(apostas.dados) && apostas.dados.total.quantidade + apostas.dados.canceladas.quantidade === 0)}
                        >
                            {apostas.dados && (
                                <Charts>
                                    <ChartCanvas
                                        tipo="doughnut"
                                        dados={grafico_apostas}
                                        opcoes={opcoes_rosca}
                                        legenda
                                        centro={{ valor: apostas.dados.total.quantidade, rotulo: apostas.dados.total.quantidade === 1 ? 'aposta' : 'apostas' }}
                                    />
                                    <Cards $colunas={1}>
                                        <StatCard icone="bar_chart" rotulo={`Total (${apostas.dados.total.quantidade})`} valor={apostas.dados.total.valor} />
                                        {grupos_apostas.map((item) => (
                                            <StatCard
                                                key={item.chave}
                                                icone={item.icone}
                                                rotulo={`${item.rotulo} (${apostas.dados[item.chave].quantidade})`}
                                                valor={apostas.dados[item.chave].valor}
                                            />
                                        ))}
                                    </Cards>
                                </Charts>
                            )}
                        </DashboardSection>
                    )}

                    {mostrar('combinacao_risco', combinacoes) && (
                        <DashboardSection
                            titulo="Combinação de risco"
                            esqueleto="tabela"
                            largura_total
                            acoes_extras={(
                                <SoundIcon title={som_ligado ? 'Silenciar o som dos avisos' : 'Ligar o som dos avisos'} onClick={alternar_som}>
                                    {som_ligado ? 'notifications_active' : 'notifications_off'}
                                </SoundIcon>
                            )}
                            {...moldura(combinacoes, combinacoes.dados?.length === 0)}
                        >
                            {combinacoes.dados && (
                                <RiskCombinations combinacoes={combinacoes.dados} chaves_novas={chaves_novas} ao_ver_bilhetes={definir_combinacao_aberta} />
                            )}
                        </DashboardSection>
                    )}

                    {mostrar('apostas_um_jogo', um_jogo) && (
                        <DashboardSection titulo="Apostas por um jogo" esqueleto="tabela" {...moldura(um_jogo, um_jogo.dados?.length === 0)}>
                            {um_jogo.dados && <SingleGameBets apostas={um_jogo.dados} ao_ver_bilhete={ver_bilhete} />}
                        </DashboardSection>
                    )}

                    {mostrar('vendedores', vendedores) && (
                        <DashboardSection titulo="Vendedores" esqueleto="grafico" {...moldura(vendedores, vendedores.dados?.length === 0)}>
                            {vendedores.dados && (
                                <RankingChart itens={vendedores.dados.map((item) => ({ nome: item.nome, quantidade: item.quantidade, valor: item.entradas }))} />
                            )}
                        </DashboardSection>
                    )}

                    {mostrar('jogos_mais_apostados', jogos) && (
                        <DashboardSection titulo="Jogos mais apostados" esqueleto="grafico" {...moldura(jogos, jogos.dados?.length === 0)}>
                            {jogos.dados && (
                                <RankingChart itens={jogos.dados.map((item) => ({ nome: item.confronto, quantidade: item.quantidade, valor: item.entradas }))} />
                            )}
                        </DashboardSection>
                    )}

                    {mostrar('opcoes_mais_apostadas', opcoes) && (
                        <DashboardSection titulo="Opções mais apostadas" esqueleto="grafico" {...moldura(opcoes, opcoes.dados?.length === 0)}>
                            {opcoes.dados && <RankingChart itens={opcoes.dados.map((item) => ({ nome: item.opcao, quantidade: item.quantidade }))} />}
                        </DashboardSection>
                    )}

                    {mostrar('caixa_online', caixa) && (
                        <DashboardSection titulo="Caixa online" largura_total {...moldura(caixa, false)}>
                            {caixa.dados && (
                                <Summary>
                                    <Cards>
                                        {cartoes_caixa_online.map((cartao) => (
                                            <StatCard key={cartao.chave} icone={cartao.icone} rotulo={cartao.rotulo} valor={caixa.dados[cartao.chave]} />
                                        ))}
                                        <StatCard
                                            icone="pie_chart"
                                            rotulo="Saldo líquido online"
                                            titulo="SALDO LÍQUIDO ONLINE, resultado das apostas dos clientes no site: entradas menos saídas e encerramentos."
                                            valor={caixa.dados.saldo_liquido}
                                            destaque
                                        />
                                    </Cards>
                                    <Charts>
                                        <ChartCanvas tipo="bar" dados={grafico_caixa} opcoes={opcoes_dinheiro} />
                                        <ChartCanvas tipo="bar" dados={grafico_fluxo} opcoes={opcoes_dinheiro} />
                                    </Charts>
                                </Summary>
                            )}
                        </DashboardSection>
                    )}
                </Grid>
            </PanelShell>
            <CombinationModal combinacao={combinacao_aberta} ao_fechar={() => definir_combinacao_aberta(null)} ao_ver_bilhete={ver_bilhete} />
            <TicketModal comprovante={bilhete} ao_fechar={() => definir_bilhete(null)} />
        </>
    );
}
