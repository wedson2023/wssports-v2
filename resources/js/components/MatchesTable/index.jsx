import { Fragment, useState } from 'react';

import ActionsMenu from '../ActionsMenu';
import useTelaMobile from '../../hooks/useTelaMobile';
import { formatar_valor } from '../../utils/charts';
import { formatar_data_hora } from '../../utils/dates';
import {
    Actions, Cell, Code, Detail, Details, DetailsRow, Empty, Expand, HeadCell, Legend, Money, Retry, Row, Scroll, Skeleton, Status, Statuses, Sub, Table, Tag,
} from './styles';

const data_hora = (texto) => (texto ? formatar_data_hora(texto) : '—');

// "1h 05min" a partir dos minutos de atraso
const texto_atraso = (minutos) => {
    const horas = Math.floor(minutos / 60);

    return horas ? `${horas}h ${String(minutos % 60).padStart(2, '0')}min` : `${minutos}min`;
};

// "3 · R$ 120,00 · R$ 450,00" (quantidade · valor · possível retorno)
const texto_exposicao = (grupo) => `${grupo.apostas} · ${formatar_valor(grupo.valor)} · ${formatar_valor(grupo.retorno)}`;

// selos de situação do jogo (FR-006): situação geral, a da rede, a do campeonato e, para Admin e
// Supervisor, quantas redes o desativaram
export const situacoes_do_jogo = (jogo) => {
    const selos = [];

    if (['Encerrado', 'Cancelado', 'Adiado'].includes(jogo.situacao)) {
        selos.push(jogo.resultado && jogo.situacao === 'Encerrado' ? { chave: 'Encerrado', texto: `Encerrado ${jogo.resultado.placar_casa} x ${jogo.resultado.placar_fora}` } : { chave: jogo.situacao, texto: jogo.situacao });
    }

    selos.push(jogo.ativo ? { chave: 'Ativo', texto: 'Ativo' } : { chave: 'Desativado', texto: 'Desativado' });

    if (jogo.restricoes?.nao_permitido_proprio?.pre_jogo_id) selos.push({ chave: 'Desativado para a minha rede', texto: 'Desativado para a minha rede' });
    if (jogo.campeonato && jogo.campeonato.ativo === false) selos.push({ chave: 'Campeonato desativado', texto: 'Campeonato desativado' });
    if (jogo.restricoes?.redes_desativadas) selos.push({ chave: 'Desativado para a minha rede', texto: `Desativado em ${jogo.restricoes.redes_desativadas} ${jogo.restricoes.redes_desativadas === 1 ? 'rede' : 'redes'}` });

    return selos;
};

const selos = (jogo) => (
    <Statuses>
        {situacoes_do_jogo(jogo).map((selo) => <Status key={selo.texto} $situacao={selo.chave}>{selo.texto}</Status>)}
    </Statuses>
);

const campeonato = (jogo) => (jogo.campeonato ? `${jogo.campeonato.nome} (${jogo.campeonato.pais})` : '—');

// colunas de cada aba (contracts/paginas §1): principal = sempre na linha; secundaria = só no
// computador (no celular, ao expandir)
const colunas_por_aba = {
    jogos: [
        { chave: 'campeonato', rotulo: 'Campeonato', nivel: 'secundaria', texto: campeonato },
        { chave: 'inicio', rotulo: 'Início', nivel: 'principal', texto: (jogo) => data_hora(jogo.data_inicio) },
        { chave: 'id', rotulo: 'ID', nivel: 'secundaria', texto: (jogo) => jogo.id },
        { chave: 'situacao', rotulo: 'Situação', nivel: 'principal', texto: selos },
        { chave: 'limite', rotulo: 'Limite', nivel: 'secundaria', texto: (jogo) => <Money>{formatar_valor(jogo.limite_valor_apostado)}</Money> },
    ],
    mais_apostados: [
        { chave: 'campeonato', rotulo: 'Campeonato', nivel: 'secundaria', texto: campeonato },
        { chave: 'inicio', rotulo: 'Início', nivel: 'secundaria', texto: (jogo) => data_hora(jogo.data_inicio) },
        { chave: 'quantidade', rotulo: 'Apostas', nivel: 'principal', forte: true, texto: (jogo) => jogo.quantidade },
        { chave: 'simples', rotulo: 'Simples (qtd · valor · retorno)', nivel: 'secundaria', texto: (jogo) => texto_exposicao(jogo.simples) },
        { chave: 'multiplas', rotulo: 'Múltiplas (qtd · valor · retorno)', nivel: 'secundaria', texto: (jogo) => texto_exposicao(jogo.multiplas) },
    ],
    pendentes: [
        { chave: 'campeonato', rotulo: 'Campeonato', nivel: 'secundaria', texto: campeonato },
        { chave: 'inicio', rotulo: 'Início', nivel: 'secundaria', texto: (jogo) => data_hora(jogo.data_inicio) },
        { chave: 'atraso', rotulo: 'Atraso', nivel: 'principal', forte: true, texto: (jogo) => texto_atraso(jogo.atraso_minutos) },
        { chave: 'apostas', rotulo: 'Apostas', nivel: 'secundaria', texto: (jogo) => jogo.apostas },
        { chave: 'aberto', rotulo: 'Em aberto', nivel: 'secundaria', texto: (jogo) => <Money>{formatar_valor(jogo.valor_em_aberto)}</Money> },
    ],
};

const vazios = {
    jogos: 'Nenhum jogo encontrado. Troque o dia, a busca ou os filtros.',
    mais_apostados: 'Nenhum jogo com aposta da sua rede neste filtro.',
    pendentes: 'Nenhum jogo pendente de resultado.',
};

// jogos das abas Jogos, Mais apostados e Pendentes da tela Confrontos (spec 023, FR-006, FR-019 e
// FR-022), no padrão das listas do painel: casa x fora com os selos Ao vivo e Manual na primeira
// coluna e o menu de ações; tocar na linha abre a gaveta; no celular, cartões com o restante ao
// expandir. acoes(jogo) devolve os itens do menu
export default function MatchesTable({ aba, linhas, carregando, erro, ao_tentar_novamente, ao_abrir, acoes }) {
    const [abertos, definir_abertos] = useState([]);
    const e_mobile = useTelaMobile();
    const colunas = colunas_por_aba[aba] ?? colunas_por_aba.jogos;
    const colunas_visiveis = (e_mobile ? colunas.filter((coluna) => coluna.nivel === 'principal') : colunas).length + 2;

    const alternar = (id) => definir_abertos((atuais) => (atuais.includes(id) ? atuais.filter((item) => item !== id) : [...atuais, id]));

    if (carregando && !linhas.length) {
        return <Skeleton>{Array.from({ length: 6 }, (_, indice) => <span key={indice} />)}</Skeleton>;
    }

    if (erro && !linhas.length) {
        return (
            <Empty>
                {erro.response?.data?.message ?? 'Não foi possível carregar os jogos.'}
                <Retry type="button" onClick={ao_tentar_novamente}>Tentar novamente</Retry>
            </Empty>
        );
    }

    if (!linhas.length) {
        return (
            <Empty>
                <i className="material-icons" aria-hidden="true">sports_soccer</i>
                {vazios[aba] ?? vazios.jogos}
            </Empty>
        );
    }

    return (
        <>
            <Scroll $atualizando={carregando}>
                <Table>
                    <thead>
                        <Row>
                            <HeadCell $codigo>Jogo</HeadCell>
                            {colunas.map((coluna) => (
                                <HeadCell key={coluna.chave} $secundaria={coluna.nivel === 'secundaria'}>{coluna.rotulo}</HeadCell>
                            ))}
                            <HeadCell $acoes><span>Ação</span></HeadCell>
                        </Row>
                    </thead>
                    <tbody>
                        {linhas.map((jogo) => {
                            const aberto = abertos.includes(jogo.id);
                            const itens = acoes(jogo);

                            return (
                                <Fragment key={jogo.id}>
                                    <Row $aberto={aberto} onClick={(evento) => !evento.target.closest('button, a') && ao_abrir(jogo)}>
                                        <Cell>
                                            <Code>{`${jogo.time_casa} x ${jogo.time_fora}`}</Code>
                                            <Sub>
                                                {jogo.ao_vivo && <Tag $ao_vivo>{`Ao vivo ${jogo.ao_vivo.minuto}' ${jogo.ao_vivo.placar_casa}–${jogo.ao_vivo.placar_fora}`}</Tag>}
                                                {jogo.manual && <Tag>Manual</Tag>}
                                                {e_mobile && aba !== 'jogos' && <span>{data_hora(jogo.data_inicio)}</span>}
                                            </Sub>
                                        </Cell>
                                        {colunas.map((coluna) => (
                                            <Cell key={coluna.chave} $secundaria={coluna.nivel === 'secundaria'} $forte={coluna.forte}>{coluna.texto(jogo)}</Cell>
                                        ))}
                                        <Cell>
                                            <Actions>
                                                {itens.length > 0 && <ActionsMenu acoes={itens} rotulo={`Ações do jogo ${jogo.time_casa} x ${jogo.time_fora}`} />}
                                                {e_mobile && (
                                                    <Expand
                                                        type="button"
                                                        title={aberto ? 'Ver menos' : 'Ver todas as informações'}
                                                        aria-label={aberto ? 'Ver menos do jogo' : 'Ver todas as informações do jogo'}
                                                        aria-expanded={aberto}
                                                        onClick={() => alternar(jogo.id)}
                                                    >
                                                        <i className="material-icons" aria-hidden="true">{aberto ? 'expand_less' : 'expand_more'}</i>
                                                    </Expand>
                                                )}
                                            </Actions>
                                        </Cell>
                                    </Row>
                                    {aberto && e_mobile && (
                                        <DetailsRow>
                                            <td colSpan={colunas_visiveis}>
                                                <Details>
                                                    {colunas.filter((coluna) => coluna.nivel === 'secundaria').map((coluna) => (
                                                        <Detail key={coluna.chave} $so_celular>
                                                            <span>{coluna.rotulo}</span>
                                                            <span>{coluna.texto(jogo)}</span>
                                                        </Detail>
                                                    ))}
                                                </Details>
                                            </td>
                                        </DetailsRow>
                                    )}
                                </Fragment>
                            );
                        })}
                    </tbody>
                </Table>
            </Scroll>
            {aba === 'mais_apostados' && <Legend>Múltiplas contam inteiras em cada jogo delas: a soma de vários jogos não é o total apostado na banca.</Legend>}
        </>
    );
}
