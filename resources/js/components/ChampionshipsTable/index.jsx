import { Fragment, useState } from 'react';

import ActionsMenu from '../ActionsMenu';
import useTelaMobile from '../../hooks/useTelaMobile';
import {
    Actions, Cell, Code, Detail, Details, DetailsRow, Empty, Expand, HeadCell, Retry, Row, Scroll, Skeleton, Star, Status, Statuses, Sub, Table, Tag,
} from './styles';

// selos de situação do campeonato: geral, a da rede e, para Admin e Supervisor, quantas redes o
// desativaram (FR-025 e FR-027)
const selos = (campeonato) => {
    const lista = [campeonato.ativo ? { chave: 'Ativo', texto: 'Ativo' } : { chave: 'Desativado', texto: 'Desativado' }];

    if (campeonato.restricoes?.nao_permitido_proprio_id) lista.push({ chave: 'Desativado para a minha rede', texto: 'Desativado para a minha rede' });

    if (campeonato.restricoes?.redes_desativadas) {
        const redes = campeonato.restricoes.redes_desativadas;
        lista.push({ chave: 'Desativado para a minha rede', texto: `Desativado em ${redes} ${redes === 1 ? 'rede' : 'redes'}` });
    }

    return (
        <Statuses>
            {lista.map((selo) => <Status key={selo.texto} $situacao={selo.chave}>{selo.texto}</Status>)}
        </Statuses>
    );
};

const colunas = [
    { chave: 'pais', rotulo: 'País', nivel: 'secundaria', texto: (campeonato) => campeonato.pais },
    { chave: 'jogos', rotulo: 'Jogos disponíveis', nivel: 'principal', forte: true, texto: (campeonato) => campeonato.quantidade_jogos ?? 0 },
    { chave: 'situacao', rotulo: 'Situação', nivel: 'principal', texto: selos },
    {
        chave: 'favorito',
        rotulo: 'Favorito',
        nivel: 'secundaria',
        texto: (campeonato) => (
            <Star className="material-icons" $marcada={campeonato.favorito} aria-label={campeonato.favorito ? 'Favorito' : 'Não favorito'}>
                {campeonato.favorito ? 'star' : 'star_border'}
            </Star>
        ),
    },
];

// campeonatos da aba Campeonatos da tela Confrontos (spec 023, FR-025; a antiga tela
// "Campeonatos"): bandeira e nome na primeira coluna, jogos disponíveis, situação, favorito e o menu
// de ações; no celular, cartões com nome, jogos e situação e o restante ao expandir
export default function ChampionshipsTable({ linhas, carregando, erro, ao_tentar_novamente, acoes }) {
    const [abertos, definir_abertos] = useState([]);
    const e_mobile = useTelaMobile();
    const colunas_visiveis = (e_mobile ? colunas.filter((coluna) => coluna.nivel === 'principal') : colunas).length + 2;

    const alternar = (id) => definir_abertos((atuais) => (atuais.includes(id) ? atuais.filter((item) => item !== id) : [...atuais, id]));

    if (carregando && !linhas.length) {
        return <Skeleton>{Array.from({ length: 6 }, (_, indice) => <span key={indice} />)}</Skeleton>;
    }

    if (erro && !linhas.length) {
        return (
            <Empty>
                {erro.response?.data?.message ?? 'Não foi possível carregar os campeonatos.'}
                <Retry type="button" onClick={ao_tentar_novamente}>Tentar novamente</Retry>
            </Empty>
        );
    }

    if (!linhas.length) {
        return (
            <Empty>
                <i className="material-icons" aria-hidden="true">emoji_events</i>
                Nenhum campeonato encontrado. Troque a busca ou os filtros.
            </Empty>
        );
    }

    return (
        <Scroll $atualizando={carregando}>
            <Table>
                <thead>
                    <Row>
                        <HeadCell $codigo>Campeonato</HeadCell>
                        {colunas.map((coluna) => (
                            <HeadCell key={coluna.chave} $secundaria={coluna.nivel === 'secundaria'}>{coluna.rotulo}</HeadCell>
                        ))}
                        <HeadCell $acoes><span>Ação</span></HeadCell>
                    </Row>
                </thead>
                <tbody>
                    {linhas.map((campeonato) => {
                        const aberto = abertos.includes(campeonato.id);
                        const itens = acoes(campeonato);

                        return (
                            <Fragment key={campeonato.id}>
                                <Row $aberto={aberto}>
                                    <Cell>
                                        <Code>{campeonato.nome}</Code>
                                        <Sub>
                                            <span>{`ID ${campeonato.id}`}</span>
                                            {campeonato.manual && <Tag>Manual</Tag>}
                                        </Sub>
                                    </Cell>
                                    {colunas.map((coluna) => (
                                        <Cell key={coluna.chave} $secundaria={coluna.nivel === 'secundaria'} $forte={coluna.forte}>{coluna.texto(campeonato)}</Cell>
                                    ))}
                                    <Cell>
                                        <Actions>
                                            {itens.length > 0 && <ActionsMenu acoes={itens} rotulo={`Ações do campeonato ${campeonato.nome}`} />}
                                            {e_mobile && (
                                                <Expand
                                                    type="button"
                                                    title={aberto ? 'Ver menos' : 'Ver todas as informações'}
                                                    aria-label={aberto ? 'Ver menos do campeonato' : 'Ver todas as informações do campeonato'}
                                                    aria-expanded={aberto}
                                                    onClick={() => alternar(campeonato.id)}
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
                                                        <span>{coluna.texto(campeonato)}</span>
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
    );
}
