import { useEffect, useState } from 'react';

import api from '../../utils/api';
import { formatar_dia_mes, formatar_hora } from '../../utils/dates';
import { calcular_cotacao, formatar_ajuste, formatar_cotacao } from '../../utils/quotes';
import {
    Body, Card, Game, GameButton, GameList, Header, Line, Lines, Pending, Result, Strip, Switch, Title, Warning,
} from './styles';

// junta as partes do servidor com o valor da camada em edição (pendente ou gravado)
const montar = (partes, camada, valor) => {
    const publico = camada === 'publico' ? (valor ?? 0) : partes.publico;
    const campeonato = partes.campeonato + (camada === 'campeonato' ? (valor ?? 0) : 0);
    const jogo = partes.jogo + (camada === 'jogo' && valor !== null ? Math.round((valor - partes.provedor) * 100) / 100 : 0);
    const teto = camada === 'teto' ? (valor ?? null) : partes.teto;

    return {
        publico,
        campeonato,
        jogo,
        teto,
        resultado: calcular_cotacao({
            provedor: partes.provedor,
            porcentagem: publico + partes.superiores + campeonato,
            jogo,
            teto,
            maximo: partes.maximo_ao_vivo,
        }),
    };
};

// prévia do cálculo de uma cotação de um jogo real (spec 016, FR-009/FR-010): busca as partes em
// GET /cotacoes/previa com os parâmetros do público em edição e mostra cada parcela, o resultado e
// os avisos de teto, mínimo e indisponível; no celular, faixa fixa embaixo que expande ao tocar
export default function QuotePreview({
    parametros, codigo, nome, camada, valor, pendente, rotulo_superiores = 'Superiores', token, recarga = 0, acima_da_barra = false,
}) {
    const [expandida, definir_expandida] = useState(false);
    const [jogos, definir_jogos] = useState(null);
    const [confrontos_id, definir_confrontos_id] = useState(parametros?.confrontos_id ?? null);
    const [estado, definir_estado] = useState({ previa: null, carregando: true });
    const chave = JSON.stringify(parametros);

    // o jogo da tela (aba Jogos) manda; nas outras, o escolhido em "Trocar jogo" ou o próximo
    useEffect(() => definir_confrontos_id(parametros?.confrontos_id ?? null), [parametros?.confrontos_id]);

    useEffect(() => {
        if (!parametros || !token) return undefined;

        let ativo = true;
        definir_estado((atual) => ({ ...atual, carregando: true }));

        api.get('/cotacoes/previa', {
            params: { ...parametros, confrontos_id: confrontos_id ?? undefined },
            headers: { Authorization: `Bearer ${token}` },
        })
            .then(({ data }) => ativo && definir_estado({ previa: data, carregando: false }))
            .catch(() => ativo && definir_estado({ previa: null, carregando: false }));

        return () => {
            ativo = false;
        };
    }, [chave, confrontos_id, recarga, token]);

    const { previa, carregando } = estado;
    const confronto = previa?.confronto;
    const ao_trocar_jogo = (id) => definir_confrontos_id(id);
    const partes = previa?.partes?.[codigo];
    // jogo de outro campeonato (escolhido em "Trocar jogo"): a regra em edição não vale para ele
    const de_outro_campeonato = camada === 'campeonato' && parametros?.campeonatos_id && confronto?.campeonatos_id !== parametros.campeonatos_id;
    const conta = partes ? montar(partes, camada, de_outro_campeonato ? null : valor) : null;

    const abrir_jogos = async () => {
        if (jogos) {
            definir_jogos(null);
            return;
        }

        try {
            const { data } = await api.get('/cotacoes/previa/jogos', { headers: { Authorization: `Bearer ${token}` } });
            definir_jogos(data.data);
        } catch {
            definir_jogos([]);
        }
    };

    const linhas = conta ? [
        { rotulo: 'Provedor', texto: formatar_cotacao(partes.provedor), mostrar: true },
        { rotulo: 'Público', texto: formatar_ajuste(conta.publico), mostrar: camada === 'publico' || conta.publico !== 0, pendente: camada === 'publico' && pendente },
        { rotulo: rotulo_superiores, texto: formatar_ajuste(partes.superiores), mostrar: partes.superiores !== 0 },
        { rotulo: 'Campeonato', texto: formatar_ajuste(conta.campeonato), mostrar: camada === 'campeonato' || conta.campeonato !== 0, pendente: camada === 'campeonato' && pendente },
        { rotulo: 'Jogo', texto: formatar_ajuste(conta.jogo, ''), mostrar: camada === 'jogo' || conta.jogo !== 0, pendente: camada === 'jogo' && pendente },
        { rotulo: 'Teto', texto: conta.teto !== null ? formatar_cotacao(conta.teto) : 'sem teto', mostrar: camada === 'teto' || conta.teto !== null, pendente: camada === 'teto' && pendente },
        { rotulo: 'Máximo ao vivo', texto: partes.maximo_ao_vivo !== null ? formatar_cotacao(partes.maximo_ao_vivo) : '', mostrar: partes.maximo_ao_vivo !== null },
    ].filter((linha) => linha.mostrar) : [];

    const resumo = conta && !conta.resultado.indisponivel
        ? `${nome} ${formatar_cotacao(partes.provedor)} → ${formatar_cotacao(conta.resultado.valor)}`
        : nome;

    return (
        <Card $expandida={expandida} $acima_da_barra={acima_da_barra}>
            <Strip type="button" onClick={() => definir_expandida((atual) => !atual)} aria-expanded={expandida}>
                <i className="material-icons" aria-hidden="true">calculate</i>
                <span>{resumo}</span>
                <i className="material-icons" aria-hidden="true">{expandida ? 'expand_more' : 'expand_less'}</i>
            </Strip>
            <Body $expandida={expandida}>
                <Header>
                    <Title>
                        <i className="material-icons" aria-hidden="true">calculate</i>
                        Prévia
                    </Title>
                    <Switch type="button" onClick={abrir_jogos}>{jogos ? 'Fechar' : 'Trocar jogo'}</Switch>
                </Header>

                {jogos && (
                    <GameList>
                        {!jogos.length && <span>Nenhum jogo encontrado.</span>}
                        {jogos.map((jogo) => (
                            <GameButton
                                key={jogo.id}
                                type="button"
                                onClick={() => {
                                    definir_jogos(null);
                                    ao_trocar_jogo?.(jogo.id);
                                }}
                            >
                                <strong>{jogo.time_casa} x {jogo.time_fora}</strong>
                                <small>{formatar_dia_mes(jogo.data_inicio)} {formatar_hora(jogo.data_inicio)} · {jogo.campeonato?.nome}</small>
                            </GameButton>
                        ))}
                    </GameList>
                )}

                {carregando && <Warning>Carregando a prévia...</Warning>}
                {!carregando && !confronto && (
                    <Warning>
                        {parametros?.campeonatos_id
                            ? 'Este campeonato não tem jogos futuros no pré-jogo. Escolha um jogo em "Trocar jogo" para ver a conta (sem a regra deste campeonato).'
                            : 'Sem jogo do pré-jogo para a prévia agora.'}
                    </Warning>
                )}

                {!carregando && confronto && (
                    <>
                        <Game>
                            <strong>{confronto.time_casa} x {confronto.time_fora}</strong>
                            <small>{formatar_dia_mes(confronto.data_inicio)} {formatar_hora(confronto.data_inicio)} · {confronto.campeonato?.nome}</small>
                        </Game>
                        <Lines>
                            <Line $destaque>
                                <span>Cotação em foco</span>
                                <strong>{nome}</strong>
                            </Line>
                            {linhas.map((linha) => (
                                <Line key={linha.rotulo}>
                                    <span>
                                        {linha.rotulo}
                                        {linha.pendente && <Pending>não salvo</Pending>}
                                    </span>
                                    <strong>{linha.texto}</strong>
                                </Line>
                            ))}
                        </Lines>
                        {conta?.resultado.indisponivel ? (
                            <Warning>Indisponível no provedor para este jogo.</Warning>
                        ) : (
                            <Result>
                                <span>Resultado</span>
                                <strong>{conta ? formatar_cotacao(conta.resultado.valor) : '—'}</strong>
                            </Result>
                        )}
                        {conta?.resultado.limitada_teto && <Warning>Limitada ao teto.</Warning>}
                        {conta?.resultado.limitada_minimo && <Warning>Limitada ao mínimo de 1,00.</Warning>}
                    </>
                )}
            </Body>
        </Card>
    );
}
