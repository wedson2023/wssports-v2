import { useEffect, useState } from 'react';

import Backdrop from '../Backdrop';
import MatchBetsList from '../MatchBetsList';
import MatchPicksRanking from '../MatchPicksRanking';
import ModalHeader from '../ModalHeader';
import PanelTabs from '../PanelTabs';
import Spinner from '../Spinner';
import { situacoes_do_jogo } from '../MatchesTable';
import useVoltarFecha from '../../hooks/useVoltarFecha';
import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import { formatar_valor } from '../../utils/charts';
import { formatar_data_hora } from '../../utils/dates';
import {
    Actions, Body, Button, Container, Data, DataItem, Empty, Loading, Retry, Status, Statuses, Summary, Tag, Title, Top,
} from './styles';

const abas = [
    { chave: 'dados', rotulo: 'Dados', icone: 'info' },
    { chave: 'mais_apostados', rotulo: 'Mais apostados', icone: 'local_fire_department' },
    { chave: 'apostas', rotulo: 'Apostas', icone: 'receipt_long' },
    { chave: 'resultado', rotulo: 'Resultado', icone: 'scoreboard' },
];

const POR_PAGINA = 30;

const data_hora = (texto) => (texto ? formatar_data_hora(texto) : '—');
const cotacao = (valor) => (valor ? Number(valor).toFixed(2).replace('.', ',') : '—');
const numero = (valor) => (valor === null || valor === undefined ? '—' : valor);

const lista_inicial = { linhas: [], pagina: 1, ultima: 1, carregando: true, erro: null };

// detalhe do jogo (spec 023, FR-014 a FR-018, contracts/paginas §3; substitui o "destaques da
// modalidade" do antigo): gaveta à direita (tela cheia no celular) com os dados, o ranking das
// opções, as apostas e o resultado, cada aba carregada ao abrir; versao muda quando uma ação de fora
// altera o jogo. acoes(jogo) devolve os atalhos; ao_lancar_resultado abre o modal do placar
export default function MatchDrawer({ jogo, aba_inicial = 'dados', versao, token, pode_abrir_aposta, acoes, ao_lancar_resultado, ao_fechar }) {
    const aberto = Boolean(jogo);
    const [aba, definir_aba] = useState(aba_inicial);
    const [detalhe, definir_detalhe] = useState(null);
    const [ranking, definir_ranking] = useState({ opcoes: [], carregando: true, erro: null });
    const [apostas, definir_apostas] = useState(lista_inicial);
    const [recarga, definir_recarga] = useState(0);

    const cabecalhos = { headers: { Authorization: `Bearer ${token}` } };

    useVoltarFecha(aberto, ao_fechar);

    useEffect(() => {
        if (!aberto) return;

        definir_aba(aba_inicial);
        definir_apostas(lista_inicial);
    }, [jogo?.id, aba_inicial]);

    useEffect(() => {
        if (!aberto) return undefined;

        let ativo = true;
        api.get(`/confrontos/${jogo.id}`, cabecalhos)
            .then(({ data }) => ativo && definir_detalhe(data.data))
            .catch((erro) => {
                if (!ativo) return;

                alerta_erro(erro);
                ao_fechar();
            });

        return () => {
            ativo = false;
        };
    }, [jogo?.id, versao]);

    useEffect(() => {
        if (!aberto) return undefined;

        const ao_teclar = (evento) => { if (evento.key === 'Escape') ao_fechar(); };
        window.addEventListener('keydown', ao_teclar);

        return () => window.removeEventListener('keydown', ao_teclar);
    }, [aberto, ao_fechar]);

    useEffect(() => {
        if (!aberto || aba !== 'mais_apostados') return undefined;

        let ativo = true;
        definir_ranking((atual) => ({ ...atual, carregando: true, erro: null }));
        api.get(`/confrontos/${jogo.id}/mais-apostados`, cabecalhos)
            .then(({ data }) => ativo && definir_ranking({ opcoes: data.data, carregando: false, erro: null }))
            .catch((erro) => ativo && definir_ranking((atual) => ({ ...atual, carregando: false, erro })));

        return () => {
            ativo = false;
        };
    }, [jogo?.id, aba, versao, recarga]);

    useEffect(() => {
        if (!aberto || aba !== 'apostas') return undefined;

        let ativo = true;
        definir_apostas((atual) => ({ ...atual, carregando: true, erro: null }));
        api.get(`/confrontos/${jogo.id}/apostas`, { params: { page: apostas.pagina, por_pagina: POR_PAGINA }, ...cabecalhos })
            .then(({ data }) => ativo && definir_apostas((atual) => ({ ...atual, linhas: data.data, ultima: data.meta.last_page, carregando: false, erro: null })))
            .catch((erro) => ativo && definir_apostas((atual) => ({ ...atual, carregando: false, erro })));

        return () => {
            ativo = false;
        };
    }, [jogo?.id, aba, apostas.pagina, versao, recarga]);

    const dados = detalhe && detalhe.id === jogo?.id ? detalhe : null;

    const linha = (rotulo, valor) => (
        <DataItem key={rotulo}>
            <span>{rotulo}</span>
            <span>{valor ?? '—'}</span>
        </DataItem>
    );

    // carregando, erro com "Tentar novamente" e o conteúdo de uma aba de lista (FR-036)
    const estado_da_aba = (estado, conteudo) => {
        if (estado.carregando && !(estado.opcoes ?? estado.linhas).length) return <Loading><Spinner tamanho={30} cor="currentColor" /></Loading>;

        if (estado.erro && !(estado.opcoes ?? estado.linhas).length) {
            return (
                <Empty>
                    {estado.erro.response?.data?.message ?? 'Não foi possível carregar.'}
                    <Retry type="button" onClick={() => definir_recarga((atual) => atual + 1)}>Tentar novamente</Retry>
                </Empty>
            );
        }

        return conteudo;
    };

    const desativacoes = () => {
        const textos = [
            !dados.ativo && 'Desativado para todos',
            dados.restricoes?.nao_permitido_todos && 'Não permitido para todos',
            dados.restricoes?.redes_desativadas ? `Desativado em ${dados.restricoes.redes_desativadas} ${dados.restricoes.redes_desativadas === 1 ? 'rede' : 'redes'}` : null,
            dados.restricoes?.nao_permitido_proprio?.pre_jogo_id && 'Desativado para a minha rede',
            dados.campeonato?.ativo === false && 'Campeonato desativado',
        ].filter(Boolean);

        return textos.length ? textos.join(' · ') : 'Nenhuma';
    };

    const aba_dados = () => (
        <>
            <Title>Jogo</Title>
            <Data>
                {linha('ID', dados.id)}
                {linha('Campeonato', dados.campeonato ? `${dados.campeonato.nome} (${dados.campeonato.pais})` : '—')}
                {linha('Esporte', dados.esporte)}
                {linha('Início', data_hora(dados.data_inicio))}
                {linha('Origem', dados.manual ? 'Manual' : 'Provedor')}
                {linha('Situação', dados.situacao)}
                {linha('Desativações', desativacoes())}
            </Data>

            <Title>Limites</Title>
            <Data>
                {linha('Pré-jogo', formatar_valor(dados.limite_valor_apostado))}
                {dados.ao_vivo && linha('Ao vivo', formatar_valor(dados.ao_vivo.limite_valor_apostado))}
            </Data>

            <Title>Cotações do site</Title>
            <Data>
                {linha('Casa', cotacao(dados.cotacoes_site?.odd1))}
                {linha('Empate', cotacao(dados.cotacoes_site?.odd2))}
                {linha('Fora', cotacao(dados.cotacoes_site?.odd3))}
            </Data>
            <Summary>Como o visitante do site vê; a prévia por público fica na tela Cotações.</Summary>

            <Actions>
                {acoes(dados).map((acao) => (
                    <Button key={acao.chave} type="button" $tom={acao.tom} onClick={acao.ao_clicar}>
                        <i className="material-icons" aria-hidden="true">{acao.icone}</i>
                        {acao.titulo}
                    </Button>
                ))}
            </Actions>
        </>
    );

    const aba_resultado = () => {
        const resultado = dados.resultado;

        return (
            <>
                {!resultado && <Empty>Sem resultado.</Empty>}
                {resultado && (
                    <>
                        <Title>Placar</Title>
                        <Data>
                            {linha('Placar final', `${resultado.placar_casa} x ${resultado.placar_fora}`)}
                            {linha('1º tempo', `${numero(resultado.gols_primeiro_tempo_casa)} x ${numero(resultado.gols_primeiro_tempo_fora)}`)}
                            {linha('2º tempo', `${numero(resultado.gols_segundo_tempo_casa)} x ${numero(resultado.gols_segundo_tempo_fora)}`)}
                            {linha('Escanteios', `${numero(resultado.escanteios_casa)} x ${numero(resultado.escanteios_fora)}`)}
                            {linha('Origem', resultado.origem)}
                        </Data>
                        {resultado.gols?.length > 0 && (
                            <>
                                <Title>Gols</Title>
                                <Data>
                                    {resultado.gols.map((gol) => linha(`${gol.ordem}º gol`, [gol.nome, gol.minuto && `${gol.minuto}'`, gol.tempo_jogo].filter(Boolean).join(' · ')))}
                                </Data>
                            </>
                        )}
                    </>
                )}
                {dados.acoes?.lancar_resultado && (
                    <Actions>
                        <Button type="button" onClick={() => ao_lancar_resultado(dados)}>
                            <i className="material-icons" aria-hidden="true">scoreboard</i>
                            {resultado ? 'Corrigir resultado' : 'Lançar resultado'}
                        </Button>
                    </Actions>
                )}
            </>
        );
    };

    const conteudo = {
        dados: aba_dados,
        mais_apostados: () => estado_da_aba(ranking, <MatchPicksRanking opcoes={ranking.opcoes} />),
        apostas: () => estado_da_aba(apostas, (
            <MatchBetsList apostas={apostas} pode_abrir={pode_abrir_aposta} ao_trocar_pagina={(pagina) => definir_apostas((atual) => ({ ...atual, pagina }))} />
        )),
        resultado: aba_resultado,
    };

    return (
        <>
            <Backdrop visivel={aberto} ao_fechar={ao_fechar} camada={45} />
            <Container $visivel={aberto} role="dialog" aria-modal="true" aria-label={jogo ? `Jogo ${jogo.time_casa} x ${jogo.time_fora}` : 'Jogo'}>
                {aberto && (
                    <>
                        <ModalHeader icone="sports_soccer" titulo={`${jogo.time_casa} x ${jogo.time_fora}`} descricao="Dados, mais apostados, apostas e resultado do jogo." ao_fechar={ao_fechar} />
                        {dados && (
                            <Top>
                                <span>{`${dados.campeonato?.nome ?? ''} · ${data_hora(dados.data_inicio)}`}</span>
                                <Statuses>
                                    {dados.ao_vivo && <Tag $ao_vivo>{`Ao vivo ${dados.ao_vivo.minuto}' ${dados.ao_vivo.placar_casa}–${dados.ao_vivo.placar_fora}`}</Tag>}
                                    {situacoes_do_jogo(dados).map((selo) => <Status key={selo.texto} $situacao={selo.chave}>{selo.texto}</Status>)}
                                </Statuses>
                            </Top>
                        )}
                        <PanelTabs abas={abas} ativa={aba} ao_trocar={definir_aba} />
                        <Body>
                            {!dados && <Loading><Spinner tamanho={30} cor="currentColor" /></Loading>}
                            {dados && conteudo[aba]()}
                        </Body>
                    </>
                )}
            </Container>
        </>
    );
}
